<?php
// api.php - API central do sistema
// Integra produtos, vendedores, vendas e clientes
// Mantém autenticação por sessão, CSRF e respostas JSON consistentes

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

session_start();
require 'db.php';
require 'helpers.php';
require_once __DIR__ . '/includes/commercial_origin.php';
require_once __DIR__ . '/includes/product_sizes.php';
require_once __DIR__ . '/includes/product_complements.php';
require_once __DIR__ . '/includes/product_complements_admin.php';
send_security_headers();

if (!function_exists('uplar_api_json')) {
    function uplar_api_json(array $data, int $httpCode = 200): void
    {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('uplar_api_csrf_check')) {
    function uplar_api_csrf_check(array $input): void
    {
        $posted = $input['csrf_token'] ?? '';
        $session = $_SESSION['csrf_token'] ?? '';

        if ($posted === '' || $session === '' || !hash_equals($session, $posted)) {
            uplar_api_json(['ok' => false, 'error' => 'Token CSRF inválido ou ausente'], 403);
        }
    }
}

// Captura fatal errors e devolve JSON em vez de HTML quebrado
register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatalTypes, true)) {
        return;
    }

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    error_log('[api.php fatal] ' . $err['message'] . ' em ' . $err['file'] . ':' . $err['line']);
    echo json_encode([
        'ok' => false,
        'error' => 'Erro fatal no servidor'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

$acao = $_GET['acao'] ?? '';
$public_actions = ['listar_publico'];

// Permite ações públicas sem sessão
if (!in_array($acao, $public_actions, true) && !isset($_SESSION['user_id'])) {
    uplar_api_json(['ok' => false, 'error' => 'Você precisa estar logado para acessar esta API'], 401);
}

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0 && !in_array($acao, $public_actions, true)) {
    session_destroy();
    uplar_api_json(['ok' => false, 'error' => 'Sessão inválida'], 401);
}

// Suporte a POST via form-data e JSON
$input = $_POST;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (empty($input) && stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $input = json_decode($raw, true);
        if (!is_array($input)) {
            $input = [];
        }
    }
    if (!in_array($acao, $public_actions, true)) {
        uplar_api_csrf_check($input);
    }
}

try {
    $pdo = get_pdo();
    commercial_origin_ensure_schema($pdo);
    product_sizes_ensure_schema($pdo);
} catch (PDOException $e) {
    error_log("Erro conexão banco: " . $e->getMessage());
    uplar_api_json(['ok' => false, 'error' => 'Erro de conexão com o banco'], 500);
}

$stmt_role = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt_role->execute([$user_id]);
$user_role = $stmt_role->fetchColumn() ?: 'user';
$is_admin = ($user_role === 'admin');

function uplar_complementos_api_instalado(PDO $pdo): void
{
    if (!uplar_complementos_schema_disponivel($pdo)) {
        uplar_api_json(['ok' => false, 'error' => 'A funcionalidade de complementos ainda não foi instalada. Aplique a migration antes de usá-la.'], 409);
    }
}

function uplar_complementos_api_produto(PDO $pdo, int $ownerId, int $productId): void
{
    uplar_complementos_produto_do_dono($pdo, $ownerId, $productId);
}

// ========================================
// COMPLEMENTOS DE PRODUTO (administração)
// ========================================
if ($acao === 'complementos_grupos') {
    try {
        uplar_complementos_api_instalado($pdo);
        $productId = (int)($_GET['produto_id'] ?? 0);
        if ($productId < 0) {
            throw new InvalidArgumentException('Produto inválido.');
        }
        if ($productId > 0) {
            uplar_complementos_api_produto($pdo, $user_id, $productId);
        }
        $groups = $pdo->prepare(
            'SELECT g.id, g.nome, g.descricao, g.minimo, g.maximo, g.ordem, g.ativo,
                    v.id AS vinculo_id, v.ativo AS vinculo_ativo, v.ordem AS vinculo_ordem
             FROM produto_complemento_grupos g
             LEFT JOIN produto_complemento_vinculos v
               ON v.grupo_id = g.id AND v.produto_id = ? AND v.user_id = g.user_id
             WHERE g.user_id = ?
             ORDER BY g.ativo DESC, g.ordem ASC, g.id ASC'
        );
        $groups->execute([$productId, $user_id]);
        $result = $groups->fetchAll(PDO::FETCH_ASSOC);
        $options = $pdo->prepare('SELECT id, grupo_id, nome, preco_adicional, custo_adicional, ordem, ativo, max_quantidade FROM produto_complemento_opcoes WHERE user_id = ? AND grupo_id = ? ORDER BY ativo DESC, ordem ASC, id ASC');
        foreach ($result as &$group) {
            $options->execute([$user_id, (int)$group['id']]);
            $group['id'] = (int)$group['id'];
            $group['minimo'] = (int)$group['minimo'];
            $group['maximo'] = (int)$group['maximo'];
            $group['ordem'] = (int)$group['ordem'];
            $group['ativo'] = (bool)$group['ativo'];
            $group['associado'] = $group['vinculo_id'] !== null && (bool)$group['vinculo_ativo'];
            $group['vinculo_ordem'] = $group['vinculo_ordem'] === null ? null : (int)$group['vinculo_ordem'];
            unset($group['vinculo_id'], $group['vinculo_ativo']);
            $group['opcoes'] = $options->fetchAll(PDO::FETCH_ASSOC);
            foreach ($group['opcoes'] as &$option) {
                $option['id'] = (int)$option['id'];
                $option['grupo_id'] = (int)$option['grupo_id'];
                $option['ordem'] = (int)$option['ordem'];
                $option['ativo'] = (bool)$option['ativo'];
                $option['max_quantidade'] = (int)$option['max_quantidade'];
                $option['preco_adicional'] = number_format((float)$option['preco_adicional'], 2, '.', '');
                $option['custo_adicional'] = $option['custo_adicional'] === null ? null : number_format((float)$option['custo_adicional'], 2, '.', '');
            }
            unset($option);
            $context = uplar_complementos_admin_contexto_grupo($pdo, $user_id, $group['id']);
            $group['qtd_produtos'] = $context['qtd_produtos'];
            $group['produtos'] = $context['produtos'];
            $group['versao'] = $context['versao'];
        }
        unset($group);
        uplar_api_json(['ok' => true, 'grupos' => $result]);
    } catch (Throwable $e) {
        uplar_api_json(['ok' => false, 'error' => $e->getMessage()], 422);
    }
}

if ($acao === 'complementos_biblioteca') {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        header('Allow: GET');
        uplar_api_json(['ok' => false, 'error' => 'Método não permitido.'], 405);
    }
    try {
        uplar_complementos_api_instalado($pdo);
        uplar_api_json(['ok' => true, 'grupos' => uplar_complementos_admin_listar_grupos($pdo, $user_id)]);
    } catch (Throwable $e) {
        error_log('[api.php complementos_biblioteca] ' . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Não foi possível carregar a biblioteca de complementos.'], 500);
    }
}

if ($acao === 'complementos_biblioteca_salvar') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        uplar_api_json(['ok' => false, 'error' => 'Método não permitido.'], 405);
    }
    try {
        uplar_complementos_api_instalado($pdo);
        $rawGroup = $input['grupo'] ?? null;
        if (is_string($rawGroup)) {
            $rawGroup = json_decode($rawGroup, true);
        }
        if (!is_array($rawGroup)) {
            throw new InvalidArgumentException('Grupo inválido.');
        }
        $idRaw = $rawGroup['id'] ?? 0;
        if ((!is_int($idRaw) && !is_string($idRaw)) || !preg_match('/^\d+$/D', (string)$idRaw)) {
            throw new InvalidArgumentException('Grupo inválido.');
        }
        $groupId = (int)$idRaw;
        $name = uplar_complementos_texto($rawGroup['nome'] ?? '', 'Nome do grupo', 100, true);
        $description = uplar_complementos_texto($rawGroup['descricao'] ?? '', 'Descrição', 255) ?: null;
        $minimum = filter_var($rawGroup['minimo'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]);
        $maximum = filter_var($rawGroup['maximo'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
        if ($minimum === false || $maximum === false || $minimum > $maximum || !is_bool($rawGroup['ativo'] ?? null)) {
            throw new InvalidArgumentException('Defina limites e status válidos para o grupo.');
        }
        $active = $rawGroup['ativo'];
        $order = filter_var($rawGroup['ordem'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 32767]]);
        if ($order === false) {
            throw new InvalidArgumentException('Ordem do grupo inválida.');
        }
        $rawOptions = $rawGroup['opcoes'] ?? null;
        if (!is_array($rawOptions) || count($rawOptions) > 100) {
            throw new InvalidArgumentException('Lista de opções inválida.');
        }
        $options = [];
        $seenOptions = [];
        $activeCapacity = 0;
        foreach (array_values($rawOptions) as $index => $rawOption) {
            if (!is_array($rawOption)) {
                throw new InvalidArgumentException('Opção inválida.');
            }
            $optionIdRaw = $rawOption['id'] ?? 0;
            if ((!is_int($optionIdRaw) && !is_string($optionIdRaw)) || !preg_match('/^\d+$/D', (string)$optionIdRaw)) {
                throw new InvalidArgumentException('Opção inválida.');
            }
            $optionId = (int)$optionIdRaw;
            if ($groupId === 0 && $optionId > 0) {
                throw new InvalidArgumentException('Grupo novo não pode usar uma opção existente.');
            }
            if ($optionId && isset($seenOptions[$optionId])) {
                throw new InvalidArgumentException('Uma opção não pode ser enviada mais de uma vez.');
            }
            $seenOptions[$optionId] = true;
            if (!is_bool($rawOption['ativo'] ?? null)) {
                throw new InvalidArgumentException('Status da opção inválido.');
            }
            $maxQuantity = filter_var($rawOption['max_quantidade'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
            $optionOrder = filter_var($rawOption['ordem'] ?? $index, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 32767]]);
            if ($maxQuantity === false || $optionOrder === false) {
                throw new InvalidArgumentException('Limites da opção inválidos.');
            }
            $priceRaw = $rawOption['preco_adicional'] ?? null;
            if ((!is_string($priceRaw) && !is_int($priceRaw) && !is_float($priceRaw)) || trim((string)$priceRaw) === '') {
                throw new InvalidArgumentException('Informe o preço adicional, inclusive zero para opções gratuitas.');
            }
            $costRaw = $rawOption['custo_adicional'] ?? null;
            if ($costRaw !== null && !is_string($costRaw) && !is_int($costRaw) && !is_float($costRaw)) {
                throw new InvalidArgumentException('Custo adicional inválido.');
            }
            $option = [
                'id' => $optionId,
                'nome' => uplar_complementos_texto($rawOption['nome'] ?? '', 'Nome da opção', 100, true),
                'preco_adicional' => uplar_complementos_money(uplar_complementos_cents($priceRaw)),
                'custo_adicional' => $costRaw === null ? null : uplar_complementos_money(uplar_complementos_cents($costRaw)),
                'max_quantidade' => $maxQuantity,
                'ordem' => $optionOrder,
                'ativo' => $rawOption['ativo'],
            ];
            if ($option['ativo']) {
                $activeCapacity += $option['max_quantidade'];
            }
            $options[] = $option;
        }
        if ($active && $activeCapacity < $minimum) {
            throw new InvalidArgumentException('O grupo ativo não possui capacidade suficiente para o mínimo configurado.');
        }

        $pdo->beginTransaction();
        $existingOptionIds = [];
        if ($groupId > 0) {
            $context = uplar_complementos_admin_exigir_confirmacao($pdo, $user_id, $groupId, !empty($input['confirmar_compartilhado']), $input['versao'] ?? null, true);
            foreach ($context['opcoes'] as $existingOption) {
                $existingOptionIds[(int)$existingOption['id']] = true;
            }
            foreach ($options as $option) {
                if ($option['id'] > 0 && !isset($existingOptionIds[$option['id']])) {
                    throw new InvalidArgumentException('Opção não pertence ao grupo informado.');
                }
            }
            $updateGroup = $pdo->prepare('UPDATE produto_complemento_grupos SET nome=?,descricao=?,ordem=?,minimo=?,maximo=?,ativo=? WHERE id=? AND user_id=?');
            $updateGroup->execute([$name, $description, $order, $minimum, $maximum, $active ? 1 : 0, $groupId, $user_id]);
        } else {
            $insertGroup = $pdo->prepare('INSERT INTO produto_complemento_grupos (user_id,nome,descricao,ordem,minimo,maximo,ativo) VALUES (?,?,?,?,?,?,?)');
            $insertGroup->execute([$user_id, $name, $description, $order, $minimum, $maximum, $active ? 1 : 0]);
            $groupId = (int)$pdo->lastInsertId();
            if ($groupId < 1) {
                throw new PDOException('ID do grupo não foi gerado.');
            }
        }
        $insertOption = $pdo->prepare('INSERT INTO produto_complemento_opcoes (user_id,grupo_id,nome,preco_adicional,custo_adicional,ordem,max_quantidade,ativo) VALUES (?,?,?,?,?,?,?,?)');
        $updateOption = $pdo->prepare('UPDATE produto_complemento_opcoes SET nome=?,preco_adicional=?,custo_adicional=?,ordem=?,max_quantidade=?,ativo=? WHERE id=? AND grupo_id=? AND user_id=?');
        $submittedOptionIds = [];
        foreach ($options as $option) {
            if ($option['id'] > 0) {
                $submittedOptionIds[$option['id']] = true;
                $updateOption->execute([$option['nome'], $option['preco_adicional'], $option['custo_adicional'], $option['ordem'], $option['max_quantidade'], $option['ativo'] ? 1 : 0, $option['id'], $groupId, $user_id]);
            } else {
                $insertOption->execute([$user_id, $groupId, $option['nome'], $option['preco_adicional'], $option['custo_adicional'], $option['ordem'], $option['max_quantidade'], $option['ativo'] ? 1 : 0]);
            }
        }
        if ($existingOptionIds) {
            $deactivate = $pdo->prepare('UPDATE produto_complemento_opcoes SET ativo=0 WHERE id=? AND grupo_id=? AND user_id=?');
            foreach (array_keys($existingOptionIds) as $existingOptionId) {
                if (!isset($submittedOptionIds[$existingOptionId])) {
                    $deactivate->execute([$existingOptionId, $groupId, $user_id]);
                }
            }
        }
        $saved = uplar_complementos_admin_normalizar_grupo(uplar_complementos_admin_contexto_grupo($pdo, $user_id, $groupId, true));
        $pdo->commit();
        uplar_api_json(['ok' => true, 'grupo' => $saved]);
    } catch (UplarComplementosAdminConflictException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        uplar_api_json(['ok' => false, 'error' => $e->getMessage()], 409);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[api.php complementos_biblioteca_salvar] ' . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro interno ao salvar complemento.'], 500);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        uplar_api_json(['ok' => false, 'error' => $e->getMessage()], 422);
    }
}

if ($acao === 'complemento_grupo_copiar') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        uplar_api_json(['ok' => false, 'error' => 'Método não permitido.'], 405);
    }
    try {
        uplar_complementos_api_instalado($pdo);
        $sourceId = (int)($input['id'] ?? 0);
        $copyName = uplar_complementos_texto($input['nome'] ?? '', 'Nome do grupo', 100, true);
        $token = $input['token'] ?? '';
        if ($sourceId < 1 || !is_string($token) || !preg_match('/^[a-zA-Z0-9-]{16,80}$/D', $token)) {
            throw new InvalidArgumentException('Dados para cópia inválidos.');
        }
        $copyHash = hash('sha256', json_encode(['owner_id' => $user_id, 'id' => $sourceId, 'nome' => $copyName, 'token' => $token], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $copyCache = $_SESSION['complemento_grupo_copiar_cache'] ?? [];
        foreach (is_array($copyCache) ? array_reverse($copyCache) : [] as $cachedCopy) {
            if (is_array($cachedCopy) && isset($cachedCopy['hash'], $cachedCopy['result']) && is_string($cachedCopy['hash']) && hash_equals($cachedCopy['hash'], $copyHash)) {
                uplar_api_json($cachedCopy['result']);
            }
        }
        $pdo->beginTransaction();
        $source = uplar_complementos_admin_contexto_grupo($pdo, $user_id, $sourceId, true);
        $sourceGroup = $source['grupo'];
        $insertGroup = $pdo->prepare('INSERT INTO produto_complemento_grupos (user_id,nome,descricao,ordem,minimo,maximo,ativo) VALUES (?,?,?,?,?,?,?)');
        $insertGroup->execute([$user_id, $copyName, $sourceGroup['descricao'], $sourceGroup['ordem'], $sourceGroup['minimo'], $sourceGroup['maximo'], $sourceGroup['ativo']]);
        $newId = (int)$pdo->lastInsertId();
        if ($newId < 1) throw new PDOException('ID do grupo não foi gerado.');
        $insertOption = $pdo->prepare('INSERT INTO produto_complemento_opcoes (user_id,grupo_id,nome,preco_adicional,custo_adicional,ordem,max_quantidade,ativo) VALUES (?,?,?,?,?,?,?,?)');
        foreach ($source['opcoes'] as $option) {
            $insertOption->execute([$user_id, $newId, $option['nome'], $option['preco_adicional'], $option['custo_adicional'], $option['ordem'], $option['max_quantidade'], $option['ativo']]);
        }
        $result = ['ok' => true, 'grupo' => uplar_complementos_admin_normalizar_grupo(uplar_complementos_admin_contexto_grupo($pdo, $user_id, $newId, true))];
        $pdo->commit();
        $copyCache = is_array($copyCache) ? array_values($copyCache) : [];
        $copyCache[] = ['hash' => $copyHash, 'result' => $result];
        $_SESSION['complemento_grupo_copiar_cache'] = array_slice($copyCache, -10);
        uplar_api_json($result);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[api.php complemento_grupo_copiar] ' . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro interno ao copiar complemento.'], 500);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        uplar_api_json(['ok' => false, 'error' => $e->getMessage()], 422);
    }
}

if ($acao === 'complementos_grupo_estado' || $acao === 'complementos_grupo_excluir') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        uplar_api_json(['ok' => false, 'error' => 'Método não permitido.'], 405);
    }
    try {
        uplar_complementos_api_instalado($pdo);
        $groupId = (int)($input['id'] ?? 0);
        if ($groupId < 1) throw new InvalidArgumentException('Grupo inválido.');
        $pdo->beginTransaction();
        if ($acao === 'complementos_grupo_excluir') {
            $context = uplar_complementos_admin_contexto_grupo($pdo, $user_id, $groupId, true);
            if ($context['qtd_vinculos'] > 0 || uplar_complementos_admin_historico_referencia_grupo($pdo, $user_id, $groupId)) {
                throw new UplarComplementosAdminConflictException('O grupo possui vínculos ou histórico comercial. Desative-o para preservar os registros.');
            }
            $deleteOptions = $pdo->prepare('DELETE FROM produto_complemento_opcoes WHERE grupo_id=? AND user_id=?');
            $deleteOptions->execute([$groupId, $user_id]);
            $deleteGroup = $pdo->prepare('DELETE FROM produto_complemento_grupos WHERE id=? AND user_id=?');
            $deleteGroup->execute([$groupId, $user_id]);
            $pdo->commit();
            uplar_api_json(['ok' => true, 'id' => $groupId]);
        }
        if (!is_bool($input['ativo'] ?? null)) throw new InvalidArgumentException('Status do grupo inválido.');
        uplar_complementos_admin_exigir_confirmacao($pdo, $user_id, $groupId, !empty($input['confirmar_compartilhado']), $input['versao'] ?? null, true);
        $stmt = $pdo->prepare('UPDATE produto_complemento_grupos SET ativo=? WHERE id=? AND user_id=?');
        $stmt->execute([$input['ativo'] ? 1 : 0, $groupId, $user_id]);
        $result = ['ok' => true, 'grupo' => uplar_complementos_admin_normalizar_grupo(uplar_complementos_admin_contexto_grupo($pdo, $user_id, $groupId, true))];
        $pdo->commit();
        uplar_api_json($result);
    } catch (UplarComplementosAdminConflictException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        uplar_api_json(['ok' => false, 'error' => $e->getMessage()], 409);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[api.php complementos_grupo_estado] ' . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro interno ao atualizar complemento.'], 500);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        uplar_api_json(['ok' => false, 'error' => $e->getMessage()], 422);
    }
}

if ($acao === 'complementos_previa') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        uplar_api_json(['ok' => false, 'error' => 'Método não permitido.'], 405);
    }
    try {
        $rawGroups = $input['grupos'] ?? null;
        $rawSelections = $input['selecoes'] ?? [];
        foreach (['rawGroups', 'rawSelections'] as $variable) {
            if (is_string($$variable)) {
                $$variable = json_decode($$variable, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new InvalidArgumentException('Dados da prévia inválidos.');
                }
            }
        }
        if (!is_array($rawGroups) || !is_array($rawSelections) || count($rawGroups) > 30) {
            throw new InvalidArgumentException('Dados da prévia inválidos.');
        }
        $baseRaw = $input['preco_base'] ?? null;
        if (!is_string($baseRaw) && !is_int($baseRaw) && !is_float($baseRaw)) {
            throw new InvalidArgumentException('Preço base inválido.');
        }
        $baseCents = uplar_complementos_cents($baseRaw);
        $productId = (int)($input['produto_id'] ?? 0);
        if ($productId < 0) {
            throw new InvalidArgumentException('Produto inválido.');
        }
        if ($productId > 0) {
            uplar_complementos_api_produto($pdo, $user_id, $productId);
        }
        $config = [];
        $previewGroupId = 0;
        foreach (array_values($rawGroups) as $rawGroup) {
            if (!is_array($rawGroup) || !is_bool($rawGroup['ativo'] ?? null)) {
                throw new InvalidArgumentException('Grupo da prévia inválido.');
            }
            if (!$rawGroup['ativo']) {
                continue;
            }
            $minimum = filter_var($rawGroup['minimo'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]);
            $maximum = filter_var($rawGroup['maximo'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
            $rawOptions = $rawGroup['opcoes'] ?? null;
            if ($minimum === false || $maximum === false || $minimum > $maximum || !is_array($rawOptions) || count($rawOptions) > 100) {
                throw new InvalidArgumentException('Grupo da prévia inválido.');
            }
            $previewOptions = [];
            $capacity = 0;
            foreach (array_values($rawOptions) as $rawOption) {
                if (!is_array($rawOption) || !is_bool($rawOption['ativo'] ?? null)) {
                    throw new InvalidArgumentException('Opção da prévia inválida.');
                }
                if (!$rawOption['ativo']) {
                    continue;
                }
                $maxQuantity = filter_var($rawOption['max_quantidade'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
                if ($maxQuantity === false) {
                    throw new InvalidArgumentException('Opção da prévia inválida.');
                }
                $previewOptions[] = [
                    'id' => count($previewOptions) + 1,
                    'nome' => uplar_complementos_texto($rawOption['nome'] ?? '', 'Nome da opção', 100, true),
                    'preco_adicional' => uplar_complementos_money(uplar_complementos_cents($rawOption['preco_adicional'] ?? '0')),
                    'custo_adicional' => null,
                    'max_quantidade' => $maxQuantity,
                ];
                $capacity += $maxQuantity;
            }
            if ($capacity < $minimum) {
                throw new InvalidArgumentException('O grupo da prévia não possui capacidade suficiente para o mínimo configurado.');
            }
            $config[] = [
                'id' => ++$previewGroupId,
                'nome' => uplar_complementos_texto($rawGroup['nome'] ?? '', 'Nome do grupo', 100, true),
                'descricao' => uplar_complementos_texto($rawGroup['descricao'] ?? '', 'Descrição', 255),
                'minimo' => $minimum,
                'maximo' => $maximum,
                'opcoes' => $previewOptions,
            ];
        }
        $result = uplar_complementos_calcular_configuracao(
            ['preco' => uplar_complementos_money($baseCents), 'preco_custo' => '0.00'],
            $config,
            ['grupos' => $rawSelections, 'observacao' => '']
        );
        uplar_api_json([
            'ok' => true,
            'valido' => true,
            'preco_centavos' => $result['preco_unitario_centavos'],
            'preco' => $result['preco_unitario'],
            'total_adicional_centavos' => $result['total_adicional_centavos'],
        ]);
    } catch (Throwable $e) {
        uplar_api_json(['ok' => false, 'valido' => false, 'error' => $e->getMessage()], 422);
    }
}

if ($acao === 'complementos_produto_salvar') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        uplar_api_json(['ok' => false, 'error' => 'Método não permitido.'], 405);
    }

    $asInteger = static function ($value, string $field, int $minimum, int $maximum): int {
        if (is_int($value)) {
            $number = $value;
        } elseif (is_string($value) && preg_match('/^-?\d+$/D', $value)) {
            $number = (int)$value;
        } else {
            throw new InvalidArgumentException($field . ' inválido.');
        }
        if ($number < $minimum || $number > $maximum) {
            throw new InvalidArgumentException($field . ' inválido.');
        }
        return $number;
    };
    $asBoolean = static function ($value, string $field): bool {
        if (!is_bool($value)) {
            throw new InvalidArgumentException($field . ' inválido.');
        }
        return $value;
    };
    $asText = static function ($value, string $field, int $maximum, bool $required = false): string {
        if (!is_string($value)) {
            throw new InvalidArgumentException($field . ' inválido.');
        }
        return uplar_complementos_texto($value, $field, $maximum, $required);
    };

    try {
        uplar_complementos_api_instalado($pdo);
        $productId = $asInteger($input['produto_id'] ?? null, 'Produto', 1, PHP_INT_MAX);
        $draftToken = $input['rascunho_token'] ?? null;
        if (!is_string($draftToken) || !preg_match('/^[a-zA-Z0-9-]{16,80}$/D', $draftToken)) {
            throw new InvalidArgumentException('Token do rascunho inválido.');
        }
        $originalGroups = $input['grupos'] ?? null;
        $draftJson = json_encode([
            'owner_id' => $user_id,
            'produto_id' => $productId,
            'rascunho_token' => $draftToken,
            'grupos' => $originalGroups,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($draftJson === false) {
            throw new InvalidArgumentException('Lista de grupos inválida.');
        }
        $draftHash = hash('sha256', $draftJson);
        $draftCache = $_SESSION['complementos_produto_salvar_cache'] ?? [];
        if (is_array($draftCache)) {
            foreach (array_reverse($draftCache) as $cachedDraft) {
                if (is_array($cachedDraft)
                    && isset($cachedDraft['hash'], $cachedDraft['result'])
                    && is_string($cachedDraft['hash'])
                    && hash_equals($cachedDraft['hash'], $draftHash)
                    && is_array($cachedDraft['result'])) {
                    uplar_api_json($cachedDraft['result']);
                }
            }
        } else {
            $draftCache = [];
        }

        $rawGroups = $originalGroups;
        if (is_string($rawGroups)) {
            $rawGroups = json_decode($rawGroups, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('Lista de grupos inválida.');
            }
        }
        if (!is_array($rawGroups) || count($rawGroups) > 30) {
            throw new InvalidArgumentException('Lista de grupos inválida.');
        }

        $groups = [];
        $seenGroupIds = [];
        foreach (array_values($rawGroups) as $groupIndex => $rawGroup) {
            if (!is_array($rawGroup)) {
                throw new InvalidArgumentException('Grupo inválido.');
            }
            $groupId = $asInteger($rawGroup['id'] ?? null, 'Grupo', 0, PHP_INT_MAX);
            $changed = $asBoolean($rawGroup['alterado'] ?? null, 'Alteração do grupo');
            if ($groupId === 0) {
                $changed = true;
            }
            if ($groupId > 0) {
                if (isset($seenGroupIds[$groupId])) {
                    throw new InvalidArgumentException('Um grupo não pode ser enviado mais de uma vez.');
                }
                $seenGroupIds[$groupId] = true;
            }

            $group = [
                'id' => $groupId,
                'alterado' => $changed,
                'ordem_vinculo' => $groupIndex,
            ];
            if ($groupId > 0 && $changed) {
                $group['confirmar_compartilhado'] = !empty($rawGroup['confirmar_compartilhado']);
                $group['versao'] = $rawGroup['versao'] ?? null;
            }
            if ($groupId === 0 || $changed) {
                $group['nome'] = $asText($rawGroup['nome'] ?? '', 'Nome do grupo', 100, true);
                $group['descricao'] = $asText($rawGroup['descricao'] ?? '', 'Descrição', 255) ?: null;
                $group['minimo'] = $asInteger($rawGroup['minimo'] ?? null, 'Mínimo do grupo', 0, 99);
                $group['maximo'] = $asInteger($rawGroup['maximo'] ?? null, 'Máximo do grupo', 1, 99);
                $group['ativo'] = $asBoolean($rawGroup['ativo'] ?? null, 'Status do grupo');
                if ($group['minimo'] > $group['maximo']) {
                    throw new InvalidArgumentException('Defina limites de escolha válidos.');
                }

                $rawOptions = $rawGroup['opcoes'] ?? null;
                if (!is_array($rawOptions) || count($rawOptions) > 100) {
                    throw new InvalidArgumentException('Lista de opções inválida.');
                }
                $group['opcoes'] = [];
                $seenOptionIds = [];
                $activeCapacity = 0;
                $activeOptions = 0;
                foreach (array_values($rawOptions) as $optionIndex => $rawOption) {
                    if (!is_array($rawOption)) {
                        throw new InvalidArgumentException('Opção inválida.');
                    }
                    $optionId = $asInteger($rawOption['id'] ?? null, 'Opção', 0, PHP_INT_MAX);
                    if ($groupId === 0 && $optionId > 0) {
                        throw new InvalidArgumentException('Grupo novo não pode usar uma opção existente.');
                    }
                    if ($optionId > 0) {
                        if (isset($seenOptionIds[$optionId])) {
                            throw new InvalidArgumentException('Uma opção não pode ser enviada mais de uma vez.');
                        }
                        $seenOptionIds[$optionId] = true;
                    }
                    $priceRaw = $rawOption['preco_adicional'] ?? null;
                    if ((!is_string($priceRaw) && !is_int($priceRaw) && !is_float($priceRaw)) || trim((string)$priceRaw) === '') {
                        throw new InvalidArgumentException('Preço adicional inválido.');
                    }
                    $costRaw = $rawOption['custo_adicional'] ?? null;
                    if ($costRaw !== null && !is_string($costRaw) && !is_int($costRaw) && !is_float($costRaw)) {
                        throw new InvalidArgumentException('Custo adicional inválido.');
                    }
                    $option = [
                        'id' => $optionId,
                        'nome' => $asText($rawOption['nome'] ?? '', 'Nome da opção', 100, true),
                        'preco_adicional' => uplar_complementos_money(uplar_complementos_cents((string)$priceRaw)),
                        'custo_adicional' => $costRaw === null ? null : uplar_complementos_money(uplar_complementos_cents((string)$costRaw)),
                        'max_quantidade' => $asInteger($rawOption['max_quantidade'] ?? null, 'Quantidade máxima da opção', 1, 99),
                        'ativo' => $asBoolean($rawOption['ativo'] ?? null, 'Status da opção'),
                        'ordem' => $asInteger($rawOption['ordem'] ?? $optionIndex, 'Ordem da opção', 0, 32767),
                    ];
                    if ($option['ativo']) {
                        $activeOptions++;
                        $activeCapacity += $option['max_quantidade'];
                    }
                    $group['opcoes'][] = $option;
                }
                if ($group['ativo'] && ($activeOptions === 0 || $activeCapacity < $group['minimo'])) {
                    throw new InvalidArgumentException('O grupo ativo precisa ter opções ativas com capacidade suficiente.');
                }
            }
            $groups[] = $group;
        }

        $pdo->beginTransaction();
        $lockProduct = $pdo->prepare('SELECT id FROM produtos WHERE id=? AND user_id=? FOR UPDATE');
        $lockProduct->execute([$productId, $user_id]);
        if (!$lockProduct->fetchColumn()) {
            throw new RuntimeException('Produto não encontrado.');
        }

        $findGroup = $pdo->prepare('SELECT id FROM produto_complemento_grupos WHERE id=? AND user_id=?');
        $findOption = $pdo->prepare('SELECT id FROM produto_complemento_opcoes WHERE id=? AND grupo_id=? AND user_id=?');
        foreach ($groups as $group) {
            if ($group['id'] <= 0) {
                continue;
            }
            $findGroup->execute([$group['id'], $user_id]);
            if (!$findGroup->fetchColumn()) {
                throw new RuntimeException('Grupo não encontrado.');
            }
            if (!$group['alterado']) {
                continue;
            }
            $context = uplar_complementos_admin_exigir_confirmacao(
                $pdo,
                $user_id,
                $group['id'],
                $group['confirmar_compartilhado'],
                $group['versao'],
                false
            );
            $ownedOptions = [];
            foreach ($context['opcoes'] as $ownedOption) {
                $ownedOptions[(int)$ownedOption['id']] = true;
            }
            foreach ($group['opcoes'] as $option) {
                if ($option['id'] <= 0) {
                    continue;
                }
                if (!isset($ownedOptions[$option['id']])) {
                    throw new RuntimeException('Opção não encontrada no grupo informado.');
                }
            }
        }

        $insertGroup = $pdo->prepare('INSERT INTO produto_complemento_grupos (user_id,nome,descricao,ordem,minimo,maximo,ativo) VALUES (?,?,?,?,?,?,?)');
        $updateGroup = $pdo->prepare('UPDATE produto_complemento_grupos SET nome=?, descricao=?, ordem=?, minimo=?, maximo=?, ativo=? WHERE id=? AND user_id=?');
        $insertOption = $pdo->prepare('INSERT INTO produto_complemento_opcoes (user_id,grupo_id,nome,preco_adicional,custo_adicional,ordem,max_quantidade,ativo) VALUES (?,?,?,?,?,?,?,?)');
        $updateOption = $pdo->prepare('UPDATE produto_complemento_opcoes SET nome=?, preco_adicional=?, custo_adicional=?, ordem=?, max_quantidade=?, ativo=? WHERE id=? AND grupo_id=? AND user_id=?');
        $deactivateOption = $pdo->prepare('UPDATE produto_complemento_opcoes SET ativo=0 WHERE id=? AND grupo_id=? AND user_id=?');
        $previousOptions = $pdo->prepare('SELECT id FROM produto_complemento_opcoes WHERE grupo_id=? AND user_id=?');
        $upsertLink = $pdo->prepare('INSERT INTO produto_complemento_vinculos (user_id,produto_id,grupo_id,ordem,ativo) VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE ordem=VALUES(ordem), ativo=1');
        $submittedIds = [];

        foreach ($groups as &$group) {
            $groupId = $group['id'];
            if ($groupId === 0) {
                $insertGroup->execute([$user_id, $group['nome'], $group['descricao'], $group['ordem_vinculo'], $group['minimo'], $group['maximo'], $group['ativo'] ? 1 : 0]);
                $groupId = (int)$pdo->lastInsertId();
                $group['id'] = $groupId;
            } elseif ($group['alterado']) {
                $updateGroup->execute([$group['nome'], $group['descricao'], $group['ordem_vinculo'], $group['minimo'], $group['maximo'], $group['ativo'] ? 1 : 0, $groupId, $user_id]);
            }

            if ($group['alterado']) {
                $previousOptions->execute([$groupId, $user_id]);
                $previousIds = array_map('intval', $previousOptions->fetchAll(PDO::FETCH_COLUMN));
                $submittedOptionIds = [];
                foreach ($group['opcoes'] as &$option) {
                    if ($option['id'] > 0) {
                        $submittedOptionIds[$option['id']] = true;
                        $updateOption->execute([$option['nome'], $option['preco_adicional'], $option['custo_adicional'], $option['ordem'], $option['max_quantidade'], $option['ativo'] ? 1 : 0, $option['id'], $groupId, $user_id]);
                    } else {
                        $insertOption->execute([$user_id, $groupId, $option['nome'], $option['preco_adicional'], $option['custo_adicional'], $option['ordem'], $option['max_quantidade'], $option['ativo'] ? 1 : 0]);
                        $option['id'] = (int)$pdo->lastInsertId();
                    }
                }
                unset($option);
                foreach ($previousIds as $previousId) {
                    if (!isset($submittedOptionIds[$previousId])) {
                        $deactivateOption->execute([$previousId, $groupId, $user_id]);
                    }
                }
            } elseif (isset($group['opcoes'])) {
                foreach ($group['opcoes'] as &$option) {
                    $insertOption->execute([$user_id, $groupId, $option['nome'], $option['preco_adicional'], $option['custo_adicional'], $option['ordem'], $option['max_quantidade'], $option['ativo'] ? 1 : 0]);
                    $option['id'] = (int)$pdo->lastInsertId();
                }
                unset($option);
            }
            $upsertLink->execute([$user_id, $productId, $groupId, $group['ordem_vinculo']]);
            $submittedIds[] = $groupId;
        }
        unset($group);

        if ($submittedIds) {
            $placeholders = implode(',', array_fill(0, count($submittedIds), '?'));
            $deactivateLinks = $pdo->prepare('UPDATE produto_complemento_vinculos SET ativo=0 WHERE user_id=? AND produto_id=? AND grupo_id NOT IN (' . $placeholders . ')');
            $deactivateLinks->execute(array_merge([$user_id, $productId], $submittedIds));
        } else {
            $deactivateLinks = $pdo->prepare('UPDATE produto_complemento_vinculos SET ativo=0 WHERE user_id=? AND produto_id=?');
            $deactivateLinks->execute([$user_id, $productId]);
        }

        $loadGroup = $pdo->prepare('SELECT id,nome,descricao,minimo,maximo,ordem,ativo FROM produto_complemento_grupos WHERE id=? AND user_id=?');
        $loadOptions = $pdo->prepare('SELECT id,grupo_id,nome,preco_adicional,custo_adicional,ordem,ativo,max_quantidade FROM produto_complemento_opcoes WHERE grupo_id=? AND user_id=? ORDER BY ativo DESC, ordem ASC, id ASC');
        $responseGroups = [];
        foreach ($submittedIds as $index => $groupId) {
            $loadGroup->execute([$groupId, $user_id]);
            $responseGroup = $loadGroup->fetch(PDO::FETCH_ASSOC);
            if (!$responseGroup) {
                throw new RuntimeException('Grupo não encontrado.');
            }
            $responseGroup['id'] = (int)$responseGroup['id'];
            $responseGroup['minimo'] = (int)$responseGroup['minimo'];
            $responseGroup['maximo'] = (int)$responseGroup['maximo'];
            $responseGroup['ordem'] = (int)$responseGroup['ordem'];
            $responseGroup['ativo'] = (bool)$responseGroup['ativo'];
            $responseGroup['associado'] = true;
            $responseGroup['vinculo_ordem'] = $index;
            $responseGroup['alterado'] = false;
            $loadOptions->execute([$groupId, $user_id]);
            $responseGroup['opcoes'] = $loadOptions->fetchAll(PDO::FETCH_ASSOC);
            foreach ($responseGroup['opcoes'] as &$option) {
                $option['id'] = (int)$option['id'];
                $option['grupo_id'] = (int)$option['grupo_id'];
                $option['ordem'] = (int)$option['ordem'];
                $option['ativo'] = (bool)$option['ativo'];
                $option['max_quantidade'] = (int)$option['max_quantidade'];
                $option['preco_adicional'] = number_format((float)$option['preco_adicional'], 2, '.', '');
                $option['custo_adicional'] = $option['custo_adicional'] === null ? null : number_format((float)$option['custo_adicional'], 2, '.', '');
            }
            unset($option);
            $context = uplar_complementos_admin_contexto_grupo($pdo, $user_id, $groupId, true);
            $responseGroup['qtd_produtos'] = $context['qtd_produtos'];
            $responseGroup['produtos'] = $context['produtos'];
            $responseGroup['versao'] = $context['versao'];
            $responseGroups[] = $responseGroup;
        }
        $pdo->commit();
        $response = ['ok' => true, 'grupos' => $responseGroups];
        $draftCache = array_values(array_filter($draftCache, static function ($cachedDraft): bool {
            return is_array($cachedDraft) && isset($cachedDraft['hash'], $cachedDraft['result']);
        }));
        $draftCache[] = ['hash' => $draftHash, 'result' => $response];
        $_SESSION['complementos_produto_salvar_cache'] = array_slice($draftCache, -10);
        uplar_api_json($response);
    } catch (UplarComplementosAdminConflictException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        uplar_api_json(['ok' => false, 'error' => $e->getMessage()], 409);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[api.php complementos_produto_salvar] ' . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro interno ao salvar complementos.'], 500);
    } catch (InvalidArgumentException | RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        uplar_api_json(['ok' => false, 'error' => $e->getMessage()], 422);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[api.php complementos_produto_salvar] ' . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro interno ao salvar complementos.'], 500);
    }
}

if ($acao === 'complementos_grupo_salvar') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        uplar_api_json(['ok' => false, 'error' => 'Método não permitido.'], 405);
    }
    try {
        uplar_complementos_api_instalado($pdo);
        $id = (int)($input['id'] ?? 0);
        $name = uplar_complementos_texto($input['nome'] ?? '', 'Nome do grupo', 100, true);
        $description = uplar_complementos_texto($input['descricao'] ?? '', 'Descrição', 255);
        $minimum = max(0, (int)($input['minimo'] ?? 0));
        $maximum = max(0, (int)($input['maximo'] ?? 0));
        $order = max(0, min(32767, (int)($input['ordem'] ?? 0)));
        $active = !empty($input['ativo']) ? 1 : 0;
        if ($maximum < 1 || $minimum > $maximum || $maximum > 99) throw new InvalidArgumentException('Defina limites de escolha válidos.');
        if ($id) {
            $pdo->beginTransaction();
            uplar_complementos_admin_exigir_confirmacao($pdo, $user_id, $id, !empty($input['confirmar_compartilhado']), $input['versao'] ?? null, true);
            $stmt = $pdo->prepare('UPDATE produto_complemento_grupos SET nome=?, descricao=?, minimo=?, maximo=?, ordem=?, ativo=? WHERE id=? AND user_id=?');
            $stmt->execute([$name, $description ?: null, $minimum, $maximum, $order, $active, $id, $user_id]);
            $pdo->commit();
        } else {
            $stmt = $pdo->prepare('INSERT INTO produto_complemento_grupos (user_id,nome,descricao,ordem,minimo,maximo,ativo) VALUES (?,?,?,?,?,?,?)');
            $stmt->execute([$user_id, $name, $description ?: null, $order, $minimum, $maximum, $active]);
            $id = (int)$pdo->lastInsertId();
        }
        uplar_api_json(['ok' => true, 'id' => $id]);
    } catch (UplarComplementosAdminConflictException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        uplar_api_json(['ok' => false, 'error' => $e->getMessage()], 409);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        uplar_api_json(['ok' => false, 'error' => $e->getMessage()], 422);
    }
}

if ($acao === 'complementos_opcao_salvar') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header('Allow: POST');
        uplar_api_json(['ok' => false, 'error' => 'Método não permitido.'], 405);
    }
    try {
        uplar_complementos_api_instalado($pdo);
        $id = (int)($input['id'] ?? 0);
        $groupId = (int)($input['grupo_id'] ?? 0);
        $pdo->beginTransaction();
        uplar_complementos_admin_exigir_confirmacao($pdo, $user_id, $groupId, !empty($input['confirmar_compartilhado']), $input['versao'] ?? null, true);
        $name = uplar_complementos_texto($input['nome'] ?? '', 'Nome da opção', 100, true);
        $price = uplar_complementos_money(uplar_complementos_cents($input['preco_adicional'] ?? '0'));
        $costRaw = trim((string)($input['custo_adicional'] ?? ''));
        $cost = $costRaw === '' ? null : uplar_complementos_money(uplar_complementos_cents($costRaw));
        $order = max(0, min(32767, (int)($input['ordem'] ?? 0)));
        $maxQuantity = max(1, min(99, (int)($input['max_quantidade'] ?? 1)));
        $active = !empty($input['ativo']) ? 1 : 0;
        if ($id) {
            $stmt = $pdo->prepare('UPDATE produto_complemento_opcoes SET nome=?, preco_adicional=?, custo_adicional=?, ordem=?, max_quantidade=?, ativo=? WHERE id=? AND grupo_id=? AND user_id=?');
            $stmt->execute([$name, $price, $cost, $order, $maxQuantity, $active, $id, $groupId, $user_id]);
            if (!$stmt->rowCount()) {
                $exists = $pdo->prepare('SELECT id FROM produto_complemento_opcoes WHERE id=? AND user_id=?');
                $exists->execute([$id, $user_id]);
                if (!$exists->fetchColumn()) throw new RuntimeException('Opção não encontrada.');
            }
        } else {
            $stmt = $pdo->prepare('INSERT INTO produto_complemento_opcoes (user_id,grupo_id,nome,preco_adicional,custo_adicional,ordem,max_quantidade,ativo) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([$user_id, $groupId, $name, $price, $cost, $order, $maxQuantity, $active]);
            $id = (int)$pdo->lastInsertId();
        }
        $pdo->commit();
        uplar_api_json(['ok' => true, 'id' => $id]);
    } catch (UplarComplementosAdminConflictException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        uplar_api_json(['ok' => false, 'error' => $e->getMessage()], 409);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        uplar_api_json(['ok' => false, 'error' => $e->getMessage()], 422);
    }
}

if ($acao === 'complementos_reordenar') {
    try {
        uplar_complementos_api_instalado($pdo);
        $productId = (int)($input['produto_id'] ?? 0);
        uplar_complementos_api_produto($pdo, $user_id, $productId);
        $orderedIds = json_decode((string)($input['grupos'] ?? ''), true);
        if (!is_array($orderedIds) || count($orderedIds) > 30) throw new InvalidArgumentException('Ordem de grupos inválida.');
        $orderedIds = array_map('intval', $orderedIds);
        if (count(array_unique($orderedIds)) !== count($orderedIds) || in_array(0, $orderedIds, true)) {
            throw new InvalidArgumentException('Ordem de grupos inválida.');
        }
        $currentStmt = $pdo->prepare('SELECT grupo_id FROM produto_complemento_vinculos WHERE user_id=? AND produto_id=? AND ativo=1');
        $currentStmt->execute([$user_id, $productId]);
        $currentIds = array_map('intval', $currentStmt->fetchAll(PDO::FETCH_COLUMN));
        $expected = $currentIds;
        sort($expected, SORT_NUMERIC);
        $received = $orderedIds;
        sort($received, SORT_NUMERIC);
        if ($expected !== $received) throw new InvalidArgumentException('A lista precisa conter todos os grupos ativos deste produto.');
        $pdo->beginTransaction();
        $updateOrder = $pdo->prepare('UPDATE produto_complemento_vinculos SET ordem=? WHERE user_id=? AND produto_id=? AND grupo_id=? AND ativo=1');
        foreach ($orderedIds as $index => $groupId) {
            $updateOrder->execute([$index, $user_id, $productId, $groupId]);
        }
        $pdo->commit();
        uplar_api_json(['ok' => true]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        uplar_api_json(['ok' => false, 'error' => $e->getMessage()], 422);
    }
}

if ($acao === 'complementos_associar' || $acao === 'complementos_desassociar') {
    try {
        uplar_complementos_api_instalado($pdo);
        $productId = (int)($input['produto_id'] ?? 0);
        $groupId = (int)($input['grupo_id'] ?? 0);
        uplar_complementos_api_produto($pdo, $user_id, $productId);
        $pdo->beginTransaction();
        uplar_complementos_admin_contexto_grupo($pdo, $user_id, $groupId, true);
        if ($acao === 'complementos_associar') {
            $order = max(0, min(32767, (int)($input['ordem'] ?? 0)));
            $stmt = $pdo->prepare('INSERT INTO produto_complemento_vinculos (user_id,produto_id,grupo_id,ordem,ativo) VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE ordem=VALUES(ordem), ativo=1');
            $stmt->execute([$user_id, $productId, $groupId, $order]);
        } else {
            $stmt = $pdo->prepare('UPDATE produto_complemento_vinculos SET ativo=0 WHERE user_id=? AND produto_id=? AND grupo_id=?');
            $stmt->execute([$user_id, $productId, $groupId]);
        }
        $pdo->commit();
        uplar_api_json(['ok' => true]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        uplar_api_json(['ok' => false, 'error' => $e->getMessage()], 422);
    }
}

// ========================================
// LISTAR PRODUTOS (painel privado)
// ========================================
if ($acao === "listar") {
    try {
        $stmt = $pdo->prepare("SELECT * FROM produtos WHERE user_id = ? ORDER BY id DESC");
        $stmt->execute([$user_id]);
        $items=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $ids=array_map('intval',array_column($items,'id'));
        $by=[];
        if($ids){$q=$pdo->prepare('SELECT produto_id,tamanho,quantidade,ordem,ativo FROM produto_tamanhos WHERE produto_id IN ('.implode(',',array_fill(0,count($ids),'?')).') ORDER BY ordem,id');$q->execute($ids);foreach($q->fetchAll(PDO::FETCH_ASSOC) as $row)$by[(int)$row['produto_id']][]=$row;}
        foreach($items as &$item){$item['usa_tamanhos']=(bool)$item['usa_tamanhos'];$item['tamanhos']=$by[(int)$item['id']]??[];}unset($item);
        uplar_api_json($items);
    } catch (Exception $e) {
        error_log("Erro ao listar produtos: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao listar produtos'], 500);
    }
}

if ($acao === 'salvar_tamanhos') {
    $id=(int)($_POST['produto_id']??0); $raw=json_decode((string)($_POST['tamanhos']??'[]'),true);
    if($id<1||!is_array($raw)) uplar_api_json(['ok'=>false,'error'=>'Dados de tamanhos inválidos'],422);
    $check=$pdo->prepare('SELECT id FROM produtos WHERE id=? AND user_id=?');$check->execute([$id,$user_id]);if(!$check->fetchColumn())uplar_api_json(['ok'=>false,'error'=>'Produto não encontrado'],404);
    try{$pdo->beginTransaction();$total=product_sizes_save($pdo,$id,$user_id,$raw);$pdo->commit();uplar_api_json(['ok'=>true,'total'=>$total]);}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();uplar_api_json(['ok'=>false,'error'=>$e->getMessage()],422);}
}

// ========================================
// ADICIONAR PRODUTO
// ========================================
if ($acao === "adicionar") {
    $nome = is_string($_POST['nome'] ?? null) ? trim($_POST['nome']) : '';
    $codigo = is_string($_POST['codigo'] ?? null) ? trim($_POST['codigo']) : '';
    $precoRaw = $_POST['preco'] ?? null;
    $custoRaw = $_POST['preco_custo'] ?? 0;
    if ($nome === '') {
        uplar_api_json(['ok' => false, 'error' => 'Nome inválido']);
    }
    if ($codigo === '') {
        uplar_api_json(['ok' => false, 'error' => 'Código inválido']);
    }
    if ((!is_string($precoRaw) && !is_int($precoRaw) && !is_float($precoRaw)) || trim((string)$precoRaw) === '' || !is_numeric($precoRaw) || !is_finite((float)$precoRaw) || (float)$precoRaw <= 0) {
        uplar_api_json(['ok' => false, 'error' => 'Preço inválido']);
    }
    if ((!is_string($custoRaw) && !is_int($custoRaw) && !is_float($custoRaw)) || trim((string)$custoRaw) === '' || !is_numeric($custoRaw) || !is_finite((float)$custoRaw) || (float)$custoRaw < 0) {
        uplar_api_json(['ok' => false, 'error' => 'Custo inválido']);
    }
    $preco = (float)$precoRaw;
    $preco_custo = (float)$custoRaw;
    $qtd = (int)($_POST['quantidade'] ?? 0);
    $desc = trim($_POST['descricao'] ?? '');
    $imagem = null;

    if (!empty($_FILES['imagem']['name'])) {
        try {
            $imagem = save_uploaded_file($_FILES['imagem'], 'imagens', 'img_', 'imagem');
        } catch (RuntimeException $e) {
            uplar_api_json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "INSERT INTO produtos (user_id, nome, codigo, preco, preco_custo, quantidade, descricao, imagem)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$user_id, $nome, $codigo, $preco, $preco_custo, $qtd, $desc, $imagem]);
        $newProductId = (int)$pdo->lastInsertId();
        if ($newProductId <= 0) {
            throw new PDOException('ID do produto não foi gerado.');
        }
        $pdo->commit();
        uplar_api_json(["ok" => true, "id" => $newProductId]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($imagem) {
            delete_managed_file($imagem, ['imagens']);
        }
        if ($e->getCode() === '23000' && strpos($e->getMessage(), 'Duplicate entry') !== false) {
            uplar_api_json(['ok' => false, 'error' => 'Já existe um produto com este código']);
        }
        error_log("Erro adicionar produto: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro interno ao salvar produto'], 500);
    }
}

// ========================================
// EDITAR PRODUTO
// ========================================
if ($acao === "editar") {
    $idRaw = $_POST['id'] ?? null;
    if ((!is_string($idRaw) && !is_int($idRaw)) || !preg_match('/^[1-9]\d*$/D', (string)$idRaw)) {
        uplar_api_json(['ok' => false, 'error' => 'ID inválido']);
    }
    $id = (int)$idRaw;
    $nome = is_string($_POST['nome'] ?? null) ? trim($_POST['nome']) : '';
    $codigo = is_string($_POST['codigo'] ?? null) ? trim($_POST['codigo']) : '';
    $precoRaw = $_POST['preco'] ?? null;
    $custoRaw = $_POST['preco_custo'] ?? 0;
    if ($nome === '') {
        uplar_api_json(['ok' => false, 'error' => 'Nome inválido']);
    }
    if ($codigo === '') {
        uplar_api_json(['ok' => false, 'error' => 'Código inválido']);
    }
    if ((!is_string($precoRaw) && !is_int($precoRaw) && !is_float($precoRaw)) || trim((string)$precoRaw) === '' || !is_numeric($precoRaw) || !is_finite((float)$precoRaw) || (float)$precoRaw <= 0) {
        uplar_api_json(['ok' => false, 'error' => 'Preço inválido']);
    }
    if ((!is_string($custoRaw) && !is_int($custoRaw) && !is_float($custoRaw)) || trim((string)$custoRaw) === '' || !is_numeric($custoRaw) || !is_finite((float)$custoRaw) || (float)$custoRaw < 0) {
        uplar_api_json(['ok' => false, 'error' => 'Custo inválido']);
    }
    $preco = (float)$precoRaw;
    $preco_custo = (float)$custoRaw;
    $qtd = (int)($_POST['quantidade'] ?? 0);
    $desc = trim($_POST['descricao'] ?? '');

    $stmt = $pdo->prepare("SELECT id, imagem FROM produtos WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $produto = $stmt->fetch();

    if (!$produto) {
        uplar_api_json(['ok' => false, 'error' => 'Produto não encontrado ou não pertence a você']);
    }

    $imagem = $produto['imagem'];

    if (!empty($_FILES['imagem']['name'])) {
        try {
            $imagem = save_uploaded_file($_FILES['imagem'], 'imagens', 'img_', 'imagem');
        } catch (RuntimeException $e) {
            uplar_api_json(['ok' => false, 'error' => $e->getMessage()]);
        }
        delete_managed_file($produto['imagem'], ['imagens']);
    }

    try {
        $pdo->beginTransaction();
        $sql = "UPDATE produtos SET nome = ?, codigo = ?, preco = ?, preco_custo = ?, quantidade = ?, descricao = ?";
        $params = [$nome, $codigo, $preco, $preco_custo, $qtd, $desc];
        if ($imagem !== $produto['imagem']) {
            $sql .= ", imagem = ?";
            $params[] = $imagem;
        }
        $sql .= " WHERE id = ? AND user_id = ?";
        $params[] = $id;
        $params[] = $user_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pdo->commit();
        uplar_api_json(["ok" => true]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro editar produto ID $id: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro interno ao salvar produto'], 500);
    }
}

// ========================================
// DELETAR PRODUTO
// ========================================
if ($acao === "deletar") {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        uplar_api_json(['ok' => false, 'error' => 'ID inválido']);
    }

    $stmt = $pdo->prepare("SELECT imagem FROM produtos WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $produto = $stmt->fetch();

    if (!$produto) {
        uplar_api_json(['ok' => false, 'error' => 'Produto não encontrado ou não pertence a você']);
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        delete_managed_file($produto['imagem'], ['imagens']);
        $pdo->commit();
        uplar_api_json(["ok" => true]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro deletar produto ID $id: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao deletar produto'], 500);
    }
}

// ========================================
// ATUALIZAR CUSTOS EM MASSA
// ========================================
if ($acao === "atualizar_custos") {
    $itens = json_decode((string)($_POST['itens'] ?? ''), true);
    if (!is_array($itens) || count($itens) > 5000) {
        uplar_api_json(['ok' => false, 'error' => 'Lista de custos inválida']);
    }
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE produtos SET preco_custo = ? WHERE id = ? AND user_id = ?");
        foreach ($itens as $item) {
            $id = (int)($item['id'] ?? 0);
            $custo = (float)($item['preco_custo'] ?? 0);
            if ($id <= 0 || $custo < 0) throw new InvalidArgumentException('Custo inválido');
            $stmt->execute([$custo, $id, $user_id]);
        }
        $pdo->commit();
        uplar_api_json(['ok' => true, 'atualizados' => count($itens)]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Erro atualizar custos em massa: ' . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Não foi possível atualizar os custos'], 500);
    }
}

// ========================================
// VENDER PRODUTO
// ========================================
if ($acao === "vender") {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        uplar_api_json(['ok' => false, 'error' => 'ID inválido']);
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE produtos SET quantidade = quantidade - 1
            WHERE id = ? AND user_id = ? AND quantidade > 0
        ");
        $stmt->execute([$id, $user_id]);
        if ($stmt->rowCount() === 0) {
            uplar_api_json(['ok' => false, 'error' => 'Estoque zerado ou produto não encontrado']);
        }
        uplar_api_json(["ok" => true]);
    } catch (PDOException $e) {
        error_log("Erro vender produto ID $id: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao registrar venda'], 500);
    }
}

// ========================================
// LISTAR PRODUTOS PÚBLICOS
// ========================================
if ($acao === "listar_publico") {
    $slug = trim((string)($_GET['slug'] ?? ''));
    if ($slug === '' || !preg_match('/^[a-zA-Z0-9_-]{3,80}$/D', $slug)) {
        uplar_api_json(['ok' => false, 'error' => 'Vitrine inválida'], 404);
    }

    $sql = "SELECT p.nome, p.preco, p.descricao, p.imagem
            FROM produtos p
            INNER JOIN users u ON u.id = p.user_id
            WHERE u.vitrine_slug = ?
              AND u.vitrine_ativa = 1
              AND p.vitrine_exposto = 1
              AND p.quantidade > 0
            ORDER BY p.id DESC";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$slug]);
        uplar_api_json($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        error_log("Erro ao listar produtos públicos: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao listar produtos públicos'], 500);
    }
}

// ========================================
// OBTER WHATSAPP
// ========================================
if ($acao === "get_whatsapp") {
    try {
        $stmt = $pdo->prepare("SELECT whatsapp_number FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();
        uplar_api_json([
            'ok' => true,
            'whatsapp_number' => $row['whatsapp_number'] ?? ''
        ]);
    } catch (Exception $e) {
        error_log("Erro get_whatsapp: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao obter WhatsApp'], 500);
    }
}

// ========================================
// SALVAR WHATSAPP
// ========================================
if ($acao === "save_whatsapp") {
    $number = trim($_POST['whatsapp_number'] ?? '');
    if (!preg_match('/^\d{10,15}$/', $number)) {
        uplar_api_json(['ok' => false, 'error' => 'Número inválido (10-15 dígitos)']);
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET whatsapp_number = ? WHERE id = ?");
        $stmt->execute([$number, $user_id]);
        uplar_api_json(['ok' => true, 'message' => 'Número salvo']);
    } catch (PDOException $e) {
        error_log("Erro save_whatsapp: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao salvar número'], 500);
    }
}

// ========================================
// LISTAR VENDEDORES
// ========================================
if ($acao === "listar_vendedores") {
    try {
        $stmt = $pdo->prepare("
            SELECT v.*,
                   COUNT(vp.produto_id) as num_produtos
            FROM vendedores v
            LEFT JOIN vendedor_produto vp ON v.id = vp.vendedor_id
            WHERE v.user_id = ?
            GROUP BY v.id
            ORDER BY v.criado_em DESC
        ");
        $stmt->execute([$user_id]);
        uplar_api_json($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        error_log("Erro na query listar_vendedores: " . $e->getMessage());
        uplar_api_json([
            'ok' => false,
            'error' => 'Erro interno ao listar vendedores'
        ], 500);
    }
}

// ========================================
// ADICIONAR VENDEDOR
// ========================================
if ($acao === "adicionar_vendedor") {
    $nome = trim($_POST['nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $observacao = trim($_POST['observacao'] ?? '');
    $foto = null;

    if (empty($nome)) {
        uplar_api_json(['ok' => false, 'error' => 'Nome é obrigatório']);
    }

    if (!empty($_FILES['foto']['name'])) {
        try {
            $foto = save_uploaded_file($_FILES['foto'], 'fotos_vendedores', 'vnd_', 'foto');
        } catch (RuntimeException $e) {
            uplar_api_json(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare(
            "INSERT INTO vendedores (user_id, nome, telefone, email, observacao, foto)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$user_id, $nome, $telefone, $email, $observacao, $foto]);
        $pdo->commit();
        uplar_api_json(["ok" => true, "id" => $pdo->lastInsertId()]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        if ($foto) {
            delete_managed_file($foto, ['fotos_vendedores']);
        }
        error_log("Erro adicionar vendedor: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro interno ao salvar vendedor'], 500);
    }
}

// ========================================
// EDITAR VENDEDOR
// ========================================
if ($acao === "editar_vendedor") {
    $id = (int)($_POST['id'] ?? 0);
    $nome = trim($_POST['nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $observacao = trim($_POST['observacao'] ?? '');
    $ativo = (int)($_POST['ativo'] ?? 1);

    if ($id <= 0 || empty($nome)) {
        uplar_api_json(['ok' => false, 'error' => 'Dados inválidos']);
    }

    $stmt = $pdo->prepare("SELECT id, foto FROM vendedores WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $vendedor = $stmt->fetch();

    if (!$vendedor) {
        uplar_api_json(['ok' => false, 'error' => 'Vendedor não encontrado ou não pertence a você']);
    }

    $foto = $vendedor['foto'];

    if (!empty($_FILES['foto']['name'])) {
        try {
            $foto = save_uploaded_file($_FILES['foto'], 'fotos_vendedores', 'vnd_', 'foto');
        } catch (RuntimeException $e) {
            uplar_api_json(['ok' => false, 'error' => $e->getMessage()]);
        }
        delete_managed_file($vendedor['foto'], ['fotos_vendedores']);
    }

    try {
        $pdo->beginTransaction();
        $sql = "UPDATE vendedores SET nome = ?, telefone = ?, email = ?, observacao = ?, ativo = ?";
        $params = [$nome, $telefone, $email, $observacao, $ativo];
        if ($foto !== $vendedor['foto']) {
            $sql .= ", foto = ?";
            $params[] = $foto;
        }
        $sql .= " WHERE id = ? AND user_id = ?";
        $params[] = $id;
        $params[] = $user_id;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $pdo->commit();
        uplar_api_json(["ok" => true]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro editar vendedor ID $id: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro interno ao salvar vendedor'], 500);
    }
}

// ========================================
// DELETAR VENDEDOR
// ========================================
if ($acao === "deletar_vendedor") {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        uplar_api_json(['ok' => false, 'error' => 'ID inválido']);
    }

    $stmt = $pdo->prepare("SELECT foto FROM vendedores WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $vendedor = $stmt->fetch();

    if (!$vendedor) {
        uplar_api_json(['ok' => false, 'error' => 'Vendedor não encontrado ou não pertence a você']);
    }

    try {
        $pdo->beginTransaction();
        $stmt_del_assoc = $pdo->prepare("DELETE FROM vendedor_produto WHERE vendedor_id = ?");
        $stmt_del_assoc->execute([$id]);

        $stmt = $pdo->prepare("DELETE FROM vendedores WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);

        delete_managed_file($vendedor['foto'], ['fotos_vendedores']);
        $pdo->commit();
        uplar_api_json(["ok" => true]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro deletar vendedor ID $id: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao deletar vendedor'], 500);
    }
}

// ========================================
// ASSOCIAR PRODUTO A VENDEDOR
// ========================================
if ($acao === "associar_produto_vendedor") {
    $vendedor_id = (int)($_POST['vendedor_id'] ?? 0);
    $produto_id = (int)($_POST['produto_id'] ?? 0);

    if ($vendedor_id <= 0 || $produto_id <= 0) {
        uplar_api_json(['ok' => false, 'error' => 'IDs inválidos']);
    }

    $stmt_prod = $pdo->prepare("SELECT id FROM produtos WHERE id = ? AND user_id = ?");
    $stmt_prod->execute([$produto_id, $user_id]);
    if (!$stmt_prod->fetch()) {
        uplar_api_json(['ok' => false, 'error' => 'Produto não encontrado ou não pertence a você']);
    }

    $stmt_vend = $pdo->prepare("SELECT id FROM vendedores WHERE id = ? AND user_id = ?");
    $stmt_vend->execute([$vendedor_id, $user_id]);
    if (!$stmt_vend->fetch()) {
        uplar_api_json(['ok' => false, 'error' => 'Vendedor não encontrado ou não pertence a você']);
    }

    try {
        $pdo->beginTransaction();
        $stmt_check = $pdo->prepare("SELECT 1 FROM vendedor_produto WHERE vendedor_id = ? AND produto_id = ?");
        $stmt_check->execute([$vendedor_id, $produto_id]);
        if (!$stmt_check->fetch()) {
            $stmt = $pdo->prepare("INSERT INTO vendedor_produto (vendedor_id, produto_id) VALUES (?, ?)");
            $stmt->execute([$vendedor_id, $produto_id]);
        }
        $pdo->commit();
        uplar_api_json(["ok" => true]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro associar produto a vendedor: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao associar'], 500);
    }
}

// ========================================
// DESASSOCIAR PRODUTO DE VENDEDOR
// ========================================
if ($acao === "desassociar_produto_vendedor") {
    $vendedor_id = (int)($_POST['vendedor_id'] ?? 0);
    $produto_id = (int)($_POST['produto_id'] ?? 0);

    if ($vendedor_id <= 0 || $produto_id <= 0) {
        uplar_api_json(['ok' => false, 'error' => 'IDs inválidos']);
    }

    $stmt_vend = $pdo->prepare("SELECT id FROM vendedores WHERE id = ? AND user_id = ?");
    $stmt_vend->execute([$vendedor_id, $user_id]);
    if (!$stmt_vend->fetch()) {
        uplar_api_json(['ok' => false, 'error' => 'Vendedor não encontrado ou não pertence a você']);
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM vendedor_produto WHERE vendedor_id = ? AND produto_id = ?");
        $stmt->execute([$vendedor_id, $produto_id]);
        uplar_api_json(["ok" => true]);
    } catch (PDOException $e) {
        error_log("Erro desassociar: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao desassociar'], 500);
    }
}

// ========================================
// ALOCAR QUANTIDADE DE PRODUTO PARA VENDEDOR
// ========================================
if ($acao === "alocar_produto_vendedor") {
    $vendedor_id = (int)($_POST['vendedor_id'] ?? 0);
    $produto_id = (int)($_POST['produto_id'] ?? 0);
    $quantidade = (int)($_POST['quantidade'] ?? 0);

    if ($vendedor_id <= 0 || $produto_id <= 0 || $quantidade <= 0) {
        uplar_api_json(['ok' => false, 'error' => 'IDs e quantidade inválidos']);
    }

    $stmt_prod = $pdo->prepare("SELECT id, quantidade FROM produtos WHERE id = ? AND user_id = ?");
    $stmt_prod->execute([$produto_id, $user_id]);
    $produto = $stmt_prod->fetch();
    if (!$produto || (int)$produto['quantidade'] < $quantidade) {
        uplar_api_json(['ok' => false, 'error' => 'Produto não encontrado, não pertence a você ou estoque insuficiente']);
    }

    $stmt_vend = $pdo->prepare("SELECT id FROM vendedores WHERE id = ? AND user_id = ?");
    $stmt_vend->execute([$vendedor_id, $user_id]);
    if (!$stmt_vend->fetch()) {
        uplar_api_json(['ok' => false, 'error' => 'Vendedor não encontrado ou não pertence a você']);
    }

    try {
        $pdo->beginTransaction();

        $stmt_check = $pdo->prepare("SELECT quantidade_alocada FROM vendedor_produto WHERE vendedor_id = ? AND produto_id = ?");
        $stmt_check->execute([$vendedor_id, $produto_id]);
        $existente = $stmt_check->fetch();

        if ($existente) {
            $novaQuantidade = (int)$existente['quantidade_alocada'] + $quantidade;
            $stmt_update = $pdo->prepare("UPDATE vendedor_produto SET quantidade_alocada = ? WHERE vendedor_id = ? AND produto_id = ?");
            $stmt_update->execute([$novaQuantidade, $vendedor_id, $produto_id]);
        } else {
            $novaQuantidade = $quantidade;
            $stmt_insert = $pdo->prepare("INSERT INTO vendedor_produto (vendedor_id, produto_id, quantidade_alocada) VALUES (?, ?, ?)");
            $stmt_insert->execute([$vendedor_id, $produto_id, $quantidade]);
        }

        $stmt_estoque = $pdo->prepare("UPDATE produtos SET quantidade = quantidade - ? WHERE id = ? AND user_id = ?");
        $stmt_estoque->execute([$quantidade, $produto_id, $user_id]);

        $pdo->commit();
        uplar_api_json(["ok" => true, "nova_quantidade_alocada" => $novaQuantidade]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro alocar produto: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao alocar produto'], 500);
    }
}

// ========================================
// LISTAR PRODUTOS ASSOCIADOS A UM VENDEDOR
// ========================================
if ($acao === "listar_produtos_vendedor") {
    $vendedor_id = (int)($_GET['vendedor_id'] ?? 0);

    if ($vendedor_id <= 0) {
        uplar_api_json(['ok' => false, 'error' => 'Vendedor inválido']);
    }

    try {
        $stmt_check = $pdo->prepare("SELECT id FROM vendedores WHERE id = ? AND user_id = ?");
        $stmt_check->execute([$vendedor_id, $user_id]);
        if (!$stmt_check->fetch()) {
            uplar_api_json(['ok' => false, 'error' => 'Vendedor não encontrado ou não pertence a você']);
        }

        $stmt = $pdo->prepare("
            SELECT
                vp.produto_id,
                p.nome,
                p.codigo,
                p.preco,
                vp.quantidade_alocada
            FROM vendedor_produto vp
            INNER JOIN produtos p ON vp.produto_id = p.id
            WHERE vp.vendedor_id = ?
              AND p.user_id = ?
            ORDER BY p.nome ASC
        ");
        $stmt->execute([$vendedor_id, $user_id]);
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($produtos as &$prod) {
            $prod['preco'] = number_format((float)$prod['preco'], 2, '.', '');
            $prod['quantidade_alocada'] = (int)$prod['quantidade_alocada'];
        }
        unset($prod);

        uplar_api_json($produtos);
    } catch (Exception $e) {
        error_log("Erro listar_produtos_vendedor: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao listar produtos associados'], 500);
    }
}

// ========================================
// AJUSTAR QUANTIDADE VENDEDOR
// ========================================
if ($acao === "ajustar_quantidade_vendedor") {
    $vendedor_id = (int)($_POST['vendedor_id'] ?? 0);
    $produto_id = (int)($_POST['produto_id'] ?? 0);
    $nova_quantidade = (int)($_POST['nova_quantidade'] ?? 0);

    if ($vendedor_id <= 0 || $produto_id <= 0) {
        uplar_api_json(['ok' => false, 'error' => 'IDs inválidos']);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT vp.quantidade_alocada
            FROM vendedor_produto vp
            INNER JOIN vendedores v ON v.id = vp.vendedor_id
            INNER JOIN produtos p ON p.id = vp.produto_id
            WHERE vp.vendedor_id = ?
              AND vp.produto_id = ?
              AND v.user_id = ?
              AND p.user_id = ?
            FOR UPDATE
        ");
        $stmt->execute([$vendedor_id, $produto_id, $user_id, $user_id]);
        $row = $stmt->fetch();
        if (!$row) {
            $pdo->rollBack();
            uplar_api_json(['ok' => false, 'error' => 'Nenhuma alocação encontrada']);
        }

        $quantidade_atual = (int)$row['quantidade_alocada'];
        $diferenca = $nova_quantidade - $quantidade_atual;

        if ($diferenca > 0) {
            $stmt_estoque = $pdo->prepare("SELECT quantidade FROM produtos WHERE id = ? AND user_id = ?");
            $stmt_estoque->execute([$produto_id, $user_id]);
            $estoque = (int)$stmt_estoque->fetchColumn();
            if ($estoque < $diferenca) {
                $pdo->rollBack();
                uplar_api_json(['ok' => false, 'error' => 'Estoque insuficiente para aumentar']);
            }
            $stmt_update_estoque = $pdo->prepare("UPDATE produtos SET quantidade = quantidade - ? WHERE id = ? AND user_id = ?");
            $stmt_update_estoque->execute([$diferenca, $produto_id, $user_id]);
        } elseif ($diferenca < 0) {
            $stmt_update_estoque = $pdo->prepare("UPDATE produtos SET quantidade = quantidade + ? WHERE id = ? AND user_id = ?");
            $stmt_update_estoque->execute([abs($diferenca), $produto_id, $user_id]);
        }

        if ($nova_quantidade <= 0) {
            $stmt_delete = $pdo->prepare("DELETE FROM vendedor_produto WHERE vendedor_id = ? AND produto_id = ?");
            $stmt_delete->execute([$vendedor_id, $produto_id]);
        } else {
            $stmt_update = $pdo->prepare("UPDATE vendedor_produto SET quantidade_alocada = ? WHERE vendedor_id = ? AND produto_id = ?");
            $stmt_update->execute([$nova_quantidade, $vendedor_id, $produto_id]);
        }

        $pdo->commit();
        uplar_api_json(['ok' => true]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro ajustar quantidade: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao ajustar quantidade'], 500);
    }
}

// ========================================
// OBTER DADOS DE UM VENDEDOR ESPECÍFICO
// ========================================
if ($acao === "obter_vendedor") {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        uplar_api_json(['ok' => false, 'error' => 'ID inválido'], 400);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, nome, telefone, email, observacao, foto, ativo
            FROM vendedores
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$id, $user_id]);
        $vendedor = $stmt->fetch();

        if (!$vendedor) {
            uplar_api_json(['ok' => false, 'error' => 'Vendedor não encontrado ou não pertence a você'], 404);
        }

        uplar_api_json($vendedor);
    } catch (Exception $e) {
        error_log("Erro obter_vendedor: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro interno ao buscar vendedor'], 500);
    }
}

// ========================================
// DECLARAR VENDA
// ========================================
if ($acao === "declarar_venda") {
    $produto_id  = (int)($_POST['produto_id'] ?? 0);
    $quantidade  = (int)($_POST['quantidade'] ?? 0);
    $preco       = (float)($_POST['preco_unitario'] ?? 0);
    $vendedor_id = (int)($_POST['vendedor_id'] ?? 0) ?: null;
    $observacao  = trim($_POST['observacao'] ?? '');

    if ($produto_id <= 0 || $quantidade < 1 || $preco <= 0) {
        uplar_api_json(['ok' => false, 'error' => 'Produto, quantidade e preço são obrigatórios']);
    }

    $stmt = $pdo->prepare("SELECT id, quantidade, preco_custo FROM produtos WHERE id = ? AND user_id = ?");
    $stmt->execute([$produto_id, $user_id]);
    $produto = $stmt->fetch();

    if (!$produto) {
        uplar_api_json(['ok' => false, 'error' => 'Produto não encontrado ou não pertence a você']);
    }
    if ((int)$produto['quantidade'] < $quantidade) {
        uplar_api_json(['ok' => false, 'error' => "Estoque insuficiente (disponível: {$produto['quantidade']})"]);
    }

    if ($vendedor_id !== null) {
        $stmt_v = $pdo->prepare("SELECT id FROM vendedores WHERE id = ? AND user_id = ?");
        $stmt_v->execute([$vendedor_id, $user_id]);
        if (!$stmt_v->fetch()) {
            uplar_api_json(['ok' => false, 'error' => 'Vendedor não encontrado ou não pertence a você']);
        }
    }

    try {
        $pdo->beginTransaction();

        $stmt_ins = $pdo->prepare("
            INSERT INTO vendas (user_id, produto_id, vendedor_id, origem, quantidade, preco_unitario, custo_unitario, observacao)
            VALUES (?, ?, ?, 'venda_direta', ?, ?, ?, ?)
        ");
        $stmt_ins->execute([$user_id, $produto_id, $vendedor_id, $quantidade, $preco, (float)$produto['preco_custo'], $observacao]);
        $venda_id = $pdo->lastInsertId();

        $stmt_est = $pdo->prepare("UPDATE produtos SET quantidade = quantidade - ? WHERE id = ? AND user_id = ?");
        $stmt_est->execute([$quantidade, $produto_id, $user_id]);

        if ($vendedor_id !== null) {
            $stmt_aloc = $pdo->prepare("
                UPDATE vendedor_produto
                SET quantidade_alocada = GREATEST(quantidade_alocada - ?, 0)
                WHERE vendedor_id = ? AND produto_id = ?
            ");
            $stmt_aloc->execute([$quantidade, $vendedor_id, $produto_id]);

            $pdo->prepare("
                DELETE FROM vendedor_produto
                WHERE vendedor_id = ? AND produto_id = ? AND quantidade_alocada = 0
            ")->execute([$vendedor_id, $produto_id]);
        }

        $pdo->commit();
        uplar_api_json(['ok' => true, 'id' => $venda_id]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro declarar_venda: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro interno ao registrar venda'], 500);
    }
}

// ========================================
// LISTAR VENDAS
// ========================================
if ($acao === 'listar_origens_vendas') {
    try {
        $stmt = $pdo->prepare("SELECT origem, COUNT(*) AS total FROM vendas WHERE user_id = ? GROUP BY origem ORDER BY total DESC");
        $stmt->execute([$user_id]);
        $origens = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $origin = commercial_origin_normalize($row['origem'] ?? null);
            $origens[$origin] = ($origens[$origin] ?? 0) + (int)$row['total'];
        }
        uplar_api_json(['ok' => true, 'origens' => $origens]);
    } catch (Exception $e) {
        error_log('Erro listar origens de vendas: ' . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao listar origens'], 500);
    }
}

if ($acao === "listar_vendas") {
    $periodo     = (int)($_GET['periodo'] ?? 30);
    $vendedor_id = (int)($_GET['vendedor_id'] ?? 0);
    $produto_id  = (int)($_GET['produto_id'] ?? 0);
    $origem      = trim((string)($_GET['origem'] ?? ''));
    $inicio      = trim($_GET['inicio'] ?? '');
    $fim         = trim($_GET['fim'] ?? '');

    $where = ["v.user_id = ?"];
    $params = [$user_id];

    if ($inicio !== '' && $fim !== '') {
        $start = DateTime::createFromFormat('Y-m-d', $inicio);
        $end = DateTime::createFromFormat('Y-m-d', $fim);
        if (!$start || !$end || $start > $end) {
            uplar_api_json(['ok' => false, 'error' => 'Período personalizado inválido'], 422);
        }
        $where[] = "v.criado_em >= ? AND v.criado_em < DATE_ADD(?, INTERVAL 1 DAY)";
        $params[] = $inicio;
        $params[] = $fim;
    } elseif ($periodo > 0) {
        $where[] = "v.criado_em >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params[] = $periodo;
    }
    if ($vendedor_id > 0) {
        $where[] = "v.vendedor_id = ?";
        $params[] = $vendedor_id;
    }
    if ($produto_id > 0) {
        $where[] = "v.produto_id = ?";
        $params[] = $produto_id;
    }
    if ($origem !== '') {
        $origem = commercial_origin_normalize($origem);
        $where[] = "v.origem = ?";
        $params[] = $origem;
    }

    $complementsReady = uplar_complementos_schema_disponivel($pdo);
    $personalizationSelect = $complementsReady ? 'v.personalizacao_json' : 'NULL AS personalizacao_json';
    $costCompletenessSelect = $complementsReady ? 'v.custo_completo' : 'NULL AS custo_completo';
    $sizeSelect = 'v.tamanho';
    $sql = "
        SELECT
            v.id,
            v.produto_id,
            p.nome  AS produto_nome,
            p.codigo AS produto_codigo,
            v.vendedor_id,
            vd.nome AS vendedor_nome,
            v.origem,
            {$sizeSelect},
            v.quantidade,
            v.preco_unitario,
            v.custo_unitario,
            {$costCompletenessSelect},
            {$personalizationSelect},
            v.total,
            v.observacao,
            v.criado_em
        FROM vendas v
        LEFT JOIN produtos  p   ON v.produto_id = p.id
        LEFT JOIN vendedores vd ON v.vendedor_id = vd.id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY v.criado_em DESC
        LIMIT 2000
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $vendas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($vendas as &$venda) {
            $storedCostCompleteness = $venda['custo_completo'] === null ? null : (int)$venda['custo_completo'];
            $venda['personalizacao'] = is_string($venda['personalizacao_json'] ?? null)
                ? json_decode($venda['personalizacao_json'], true)
                : null;
            unset($venda['personalizacao_json']);
            $venda['preco_unitario'] = number_format((float)$venda['preco_unitario'], 2, '.', '');
            $venda['total'] = number_format((float)$venda['total'], 2, '.', '');
            $venda['custo_unitario'] = number_format((float)$venda['custo_unitario'], 2, '.', '');
            $venda['tamanho'] = $venda['tamanho'] !== null ? (string)$venda['tamanho'] : null;
            $venda['custo_completo'] = $storedCostCompleteness === null ? ((float)$venda['custo_unitario'] > 0) : $storedCostCompleteness === 1;
            $venda['custo_informado'] = $venda['custo_completo'] || (float)$venda['custo_unitario'] > 0;
            $venda['custo_total'] = $venda['custo_completo'] ? number_format((float)$venda['custo_unitario'] * (int)$venda['quantidade'], 2, '.', '') : null;
            $lucro = $venda['custo_completo'] ? ((float)$venda['preco_unitario'] - (float)$venda['custo_unitario']) * (int)$venda['quantidade'] : null;
            $venda['lucro_bruto'] = $lucro === null ? null : number_format($lucro, 2, '.', '');
            $venda['margem_lucro'] = $lucro !== null && (float)$venda['preco_unitario'] > 0 ? round($lucro / (float)$venda['total'] * 100, 1) : null;
            $venda['quantidade'] = (int)$venda['quantidade'];
            $venda['origem'] = commercial_origin_normalize($venda['origem'] ?? null);
        }
        unset($venda);

        uplar_api_json($vendas);
    } catch (Exception $e) {
        error_log("Erro listar_vendas: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao listar vendas'], 500);
    }
}

// ========================================
// EXCLUIR VENDA
// ========================================
if ($acao === "excluir_venda") {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        uplar_api_json(['ok' => false, 'error' => 'ID inválido']);
    }

    $stmt = $pdo->prepare("SELECT * FROM vendas WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $venda = $stmt->fetch();

    if (!$venda) {
        uplar_api_json(['ok' => false, 'error' => 'Venda não encontrada ou não pertence a você']);
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare("UPDATE produtos SET quantidade = quantidade + ? WHERE id = ? AND user_id = ?")
            ->execute([$venda['quantidade'], $venda['produto_id'], $user_id]);

        $pdo->prepare("DELETE FROM vendas WHERE id = ? AND user_id = ?")
            ->execute([$id, $user_id]);

        $pdo->commit();
        uplar_api_json(['ok' => true]);
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Erro excluir_venda: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao excluir venda'], 500);
    }
}

// ========================================
// CLIENTES
// ========================================

// LISTAR CLIENTES
if ($acao === 'listar_clientes') {
    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM clientes
            WHERE user_id = ?
            ORDER BY id DESC
        ");
        $stmt->execute([$user_id]);
        $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($clientes as &$c) {
            $p = $pdo->prepare("
                SELECT
                    cp.produto_id,
                    cp.quantidade,
                    cp.preco_snap,
                    p.nome,
                    p.codigo
                FROM cliente_produto cp
                LEFT JOIN produtos p
                    ON p.id = cp.produto_id
                WHERE cp.cliente_id = ?
                ORDER BY cp.id ASC
            ");
            $p->execute([$c['id']]);
            $c['produtos'] = $p->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($c);

        uplar_api_json($clientes);
    } catch (Exception $e) {
        error_log("Erro listar_clientes: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao listar clientes'], 500);
    }
}

// OBTER CLIENTE
if ($acao === 'obter_cliente') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        uplar_api_json(['ok' => false, 'error' => 'ID inválido'], 400);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM clientes
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ");
        $stmt->execute([$id, $user_id]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            uplar_api_json(['ok' => false, 'error' => 'Cliente não encontrado'], 404);
        }

        $p = $pdo->prepare("
            SELECT
                cp.produto_id,
                cp.quantidade,
                cp.preco_snap,
                p.nome,
                p.codigo,
                p.preco
            FROM cliente_produto cp
            LEFT JOIN produtos p
                ON p.id = cp.produto_id
            WHERE cp.cliente_id = ?
            ORDER BY cp.id ASC
        ");
        $p->execute([$id]);
        $cliente['produtos'] = $p->fetchAll(PDO::FETCH_ASSOC);

        uplar_api_json($cliente);
    } catch (Exception $e) {
        error_log("Erro obter_cliente: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao buscar cliente'], 500);
    }
}

// ADICIONAR CLIENTE
if ($acao === 'adicionar_cliente') {
    $nome_empresa = trim($_POST['nome_empresa'] ?? '');

    if ($nome_empresa === '') {
        uplar_api_json(['ok' => false, 'error' => 'Nome da empresa obrigatório']);
    }

    $produto_ids = json_decode($_POST['produto_ids'] ?? '[]', true);
    if (!is_array($produto_ids)) {
        $produto_ids = [];
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO clientes (
                user_id,
                nome_empresa,
                cnpj,
                contato_nome,
                telefone,
                email,
                cep,
                logradouro,
                numero,
                complemento,
                bairro,
                cidade,
                estado,
                ultima_visita,
                observacao,
                ativo
            ) VALUES (
                ?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?
            )
        ");

        $stmt->execute([
            $user_id,
            $nome_empresa,
            trim($_POST['cnpj'] ?? ''),
            trim($_POST['contato_nome'] ?? ''),
            trim($_POST['telefone'] ?? ''),
            trim($_POST['email'] ?? ''),
            trim($_POST['cep'] ?? ''),
            trim($_POST['logradouro'] ?? ''),
            trim($_POST['numero'] ?? ''),
            trim($_POST['complemento'] ?? ''),
            trim($_POST['bairro'] ?? ''),
            trim($_POST['cidade'] ?? ''),
            trim($_POST['estado'] ?? ''),
            !empty($_POST['ultima_visita']) ? $_POST['ultima_visita'] : null,
            trim($_POST['observacao'] ?? ''),
            (int)($_POST['ativo'] ?? 1)
        ]);

        $cliente_id = (int)$pdo->lastInsertId();

        $stmtCheck = $pdo->prepare("
            SELECT id, preco
            FROM produtos
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ");
        $stmtIns = $pdo->prepare("
            INSERT INTO cliente_produto (
                cliente_id,
                produto_id,
                quantidade,
                preco_snap
            ) VALUES (
                ?, ?, ?, ?
            )
        ");

        foreach ($produto_ids as $pid) {
            $pid = (int)$pid;
            if ($pid <= 0) {
                continue;
            }
            $stmtCheck->execute([$pid, $user_id]);
            $prod = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$prod) {
                continue;
            }
            $stmtIns->execute([
                $cliente_id,
                $pid,
                1,
                (float)$prod['preco']
            ]);
        }

        $pdo->commit();
        uplar_api_json(['ok' => true, 'id' => $cliente_id]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro adicionar_cliente: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao salvar cliente'], 500);
    }
}

// EDITAR CLIENTE
if ($acao === 'editar_cliente') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        uplar_api_json(['ok' => false, 'error' => 'ID inválido']);
    }

    $check = $pdo->prepare("
        SELECT id
        FROM clientes
        WHERE id = ? AND user_id = ?
        LIMIT 1
    ");
    $check->execute([$id, $user_id]);

    if (!$check->fetch()) {
        uplar_api_json(['ok' => false, 'error' => 'Cliente não encontrado']);
    }

    $nome_empresa = trim($_POST['nome_empresa'] ?? '');
    if ($nome_empresa === '') {
        uplar_api_json(['ok' => false, 'error' => 'Nome da empresa obrigatório']);
    }

    $produto_ids = json_decode($_POST['produto_ids'] ?? '[]', true);
    if (!is_array($produto_ids)) {
        $produto_ids = [];
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE clientes SET
                nome_empresa = ?,
                cnpj = ?,
                contato_nome = ?,
                telefone = ?,
                email = ?,
                cep = ?,
                logradouro = ?,
                numero = ?,
                complemento = ?,
                bairro = ?,
                cidade = ?,
                estado = ?,
                ultima_visita = ?,
                observacao = ?,
                ativo = ?
            WHERE id = ? AND user_id = ?
        ");

        $stmt->execute([
            $nome_empresa,
            trim($_POST['cnpj'] ?? ''),
            trim($_POST['contato_nome'] ?? ''),
            trim($_POST['telefone'] ?? ''),
            trim($_POST['email'] ?? ''),
            trim($_POST['cep'] ?? ''),
            trim($_POST['logradouro'] ?? ''),
            trim($_POST['numero'] ?? ''),
            trim($_POST['complemento'] ?? ''),
            trim($_POST['bairro'] ?? ''),
            trim($_POST['cidade'] ?? ''),
            trim($_POST['estado'] ?? ''),
            !empty($_POST['ultima_visita']) ? $_POST['ultima_visita'] : null,
            trim($_POST['observacao'] ?? ''),
            (int)($_POST['ativo'] ?? 1),
            $id,
            $user_id
        ]);

        $pdo->prepare("DELETE FROM cliente_produto WHERE cliente_id = ?")->execute([$id]);

        $stmtCheck = $pdo->prepare("
            SELECT id, preco
            FROM produtos
            WHERE id = ? AND user_id = ?
            LIMIT 1
        ");
        $stmtIns = $pdo->prepare("
            INSERT INTO cliente_produto (
                cliente_id,
                produto_id,
                quantidade,
                preco_snap
            ) VALUES (
                ?, ?, ?, ?
            )
        ");

        foreach ($produto_ids as $pid) {
            $pid = (int)$pid;
            if ($pid <= 0) {
                continue;
            }
            $stmtCheck->execute([$pid, $user_id]);
            $prod = $stmtCheck->fetch(PDO::FETCH_ASSOC);
            if (!$prod) {
                continue;
            }
            $stmtIns->execute([
                $id,
                $pid,
                1,
                (float)$prod['preco']
            ]);
        }

        $pdo->commit();
        uplar_api_json(['ok' => true]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Erro editar_cliente: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao atualizar cliente'], 500);
    }
}

// DELETAR CLIENTE
if ($acao === 'deletar_cliente') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        uplar_api_json(['ok' => false, 'error' => 'ID inválido']);
    }

    try {
        $stmt = $pdo->prepare("
            DELETE FROM clientes
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$id, $user_id]);

        uplar_api_json(['ok' => true]);
    } catch (PDOException $e) {
        error_log("Erro deletar_cliente: " . $e->getMessage());
        uplar_api_json(['ok' => false, 'error' => 'Erro ao deletar cliente'], 500);
    }
}

// Ação inválida
uplar_api_json(['ok' => false, 'error' => 'Ação inválida'], 400);
