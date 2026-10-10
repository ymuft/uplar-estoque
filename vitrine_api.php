<?php
/**
 * vitrine_api.php — v4
 * Novidades v4:
 *  - listar_produtos: filtra por vitrine_exposto = 1
 *  - toggle_produto: liga/desliga exibição individual de produto na vitrine
 *  - fazer_pedido: calcula frete pela configuração da vitrine; a despesa só
 *    é registrada quando o pedido é confirmado ou o pagamento é confirmado
 *  - get_config: retorna valor_frete configurado
 *  - salvar_config: aceita e persiste valor_frete
 *  - URL pública: https://uplar.cloud/{slug} (sem /GG/vitrine.php?loja=)
 */
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/includes/vitrine_analytics.php';
require __DIR__ . '/includes/vitrine_featured.php';
require_once __DIR__ . '/includes/vitrine_experience.php';
require_once __DIR__ . '/includes/catalog_categories.php';
require_once __DIR__ . '/includes/commercial_origin.php';
require_once __DIR__ . '/includes/product_sizes.php';
require_once __DIR__ . '/includes/product_complements.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$pdo  = get_pdo();
$acao = trim($_GET['acao'] ?? $_POST['acao'] ?? '');

vitrine_ensure_featured_schema($pdo);
commercial_origin_ensure_schema($pdo);
product_sizes_ensure_schema($pdo);
$complementsReady = uplar_complementos_schema_disponivel($pdo);

/* ── helpers globais ── */
function jout(array $data): void { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
function apierr(string $msg, int $code = 400): void { http_response_code($code); jout(['ok' => false, 'error' => $msg]); }
function apiok(array $data = []): void { jout(['ok' => true] + $data); }

function vitrineComplementosInput(array $snapshot): array {
    $groups = [];
    foreach (is_array($snapshot['grupos'] ?? null) ? $snapshot['grupos'] : [] as $group) {
        if (!is_array($group)) continue;
        $options = [];
        foreach (is_array($group['opcoes'] ?? null) ? $group['opcoes'] : [] as $option) {
            if (!is_array($option)) continue;
            $options[] = ['opcao_id' => (int)($option['opcao_id'] ?? $option['id'] ?? 0), 'quantidade' => max(1, (int)($option['quantidade'] ?? 1))];
        }
        $groups[] = ['grupo_id' => (int)($group['grupo_id'] ?? $group['id'] ?? 0), 'opcoes' => $options];
    }
    return ['grupos' => $groups, 'observacao' => mb_substr(trim((string)($snapshot['observacao'] ?? '')), 0, 500)];
}

function vitrineComplementosUnitCents(array $snapshot, float $basePrice): int {
    return max(0, (int)($snapshot['preco_unitario_centavos'] ?? ((int)round($basePrice * 100) + (int)($snapshot['total_adicional_centavos'] ?? 0))));
}

function resolveUser(PDO $pdo, string $slug): ?array {
    if ($slug === '') return null;
    $s = $pdo->prepare("SELECT id, username, vitrine_ativa, whatsapp_number FROM users WHERE vitrine_slug = ? LIMIT 1");
    $s->execute([$slug]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Lê as preferências já sanitizadas pelo helper de experiência. */
function vitrineExperiencePreferences(PDO $pdo, int $userId): array {
    if (!uplar_vitrine_experience_schema_disponivel($pdo)) {
        return uplar_vitrine_experience_preferences(null);
    }
    $stmt = $pdo->prepare('SELECT experiencia_json FROM vitrine_config WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    return uplar_vitrine_experience_preferences($stmt->fetchColumn() ?: null);
}

function vitrineRequireOrderingEnabled(array $preferences): void {
    if (empty($preferences['pedidos_habilitados'])) {
        apierr('Esta loja está temporariamente sem receber pedidos.', 409);
    }
}

function vitrineRequireOrderType(array $preferences, string $type): void {
    $key = $type === 'entrega' ? 'entrega_habilitada' : 'retirada_habilitada';
    if (empty($preferences[$key])) {
        apierr($type === 'entrega' ? 'A loja não está aceitando pedidos para entrega.' : 'A loja não está aceitando pedidos para retirada.', 409);
    }
}

function vitrineCartEditReplay(string $token): ?array {
    if ($token === '') return null;
    $cache = $_SESSION['vitrine_cart_edit_tokens'] ?? [];
    $key = hash('sha256', $token);
    $entry = $cache[$key] ?? null;
    if (!is_array($entry) || (int)($entry['expires'] ?? 0) < time() || !is_array($entry['response'] ?? null)) return null;
    return $entry['response'];
}

function vitrineCartEditRemember(string $token, array $response): void {
    if ($token === '') return;
    $cache = $_SESSION['vitrine_cart_edit_tokens'] ?? [];
    $now = time();
    foreach ($cache as $key => $entry) {
        if (!is_array($entry) || (int)($entry['expires'] ?? 0) < $now) unset($cache[$key]);
    }
    if (count($cache) >= 30) $cache = array_slice($cache, -29, null, true);
    $cache[hash('sha256', $token)] = ['expires' => $now + 900, 'response' => $response];
    $_SESSION['vitrine_cart_edit_tokens'] = $cache;
}

function requireOwner(): int {
    if (empty($_SESSION['user_id'])) apierr('Não autenticado', 401);
    return (int)$_SESSION['user_id'];
}

/** Exige o Pass Code configurado antes de uma decisão operacional irreversível. */
function requireOrderPasscode(PDO $pdo, int $userId): void {
    $userStmt = $pdo->prepare('SELECT passcode_hash FROM users WHERE id = ? LIMIT 1');
    $userStmt->execute([$userId]);
    $hash = (string)($userStmt->fetchColumn() ?: '');
    if ($hash === '') return;

    $ip = get_client_ip();
    $attemptStmt = $pdo->prepare('SELECT tentativas, GREATEST(0, TIMESTAMPDIFF(SECOND, ultima_tentativa, NOW())) AS elapsed FROM passcode_tentativas WHERE user_id = ? AND ip = ? LIMIT 1');
    $attemptStmt->execute([$userId, $ip]);
    $attempt = $attemptStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    $maxAttempts = 5;
    $blockSeconds = 900;

    if ($attempt && (int)$attempt['tentativas'] >= $maxAttempts && (int)$attempt['elapsed'] < $blockSeconds) {
        apierr('Muitas tentativas de Pass Code. Aguarde alguns minutos.', 429);
    }
    if ($attempt && (int)$attempt['elapsed'] >= $blockSeconds) {
        $pdo->prepare('DELETE FROM passcode_tentativas WHERE user_id = ? AND ip = ?')->execute([$userId, $ip]);
        $attempt = null;
    }

    $passcode = (string)($_POST['passcode'] ?? '');
    if (!preg_match('/^\d{6}$/D', $passcode) || !password_verify($passcode, $hash)) {
        $pdo->prepare('INSERT INTO passcode_tentativas (user_id, ip, tentativas, ultima_tentativa) VALUES (?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE tentativas = tentativas + 1, ultima_tentativa = NOW()')
            ->execute([$userId, $ip]);
        apierr('Pass Code incorreto. Tente novamente.', 403);
    }

    $pdo->prepare('DELETE FROM passcode_tentativas WHERE user_id = ?')->execute([$userId]);
}

function syncConfirmedShippingExpense(PDO $pdo, array $pedido, int $userId): bool {
    $orderId = (int)($pedido['id'] ?? 0);
    $shipping = (float)($pedido['valor_frete'] ?? 0);
    if ($orderId < 1) return false;

    if (($pedido['tipo_entrega'] ?? '') !== 'entrega' || $shipping <= 0) {
        $pdo->prepare('DELETE FROM despesas WHERE user_id = ? AND pedido_vitrine_id = ?')
            ->execute([$userId, $orderId]);
        return false;
    }

    $label = ($pedido['origem'] ?? '') === 'site_pro' ? 'Pedido Site PRO' : 'Pedido vitrine';
    $pdo->prepare("INSERT INTO despesas
        (pedido_vitrine_id, user_id, descricao, categoria, tipo, valor, data_despesa, status, observacao)
        VALUES (?, ?, ?, 'Frete', 'variavel', ?, ?, 'pago', ?)
        ON DUPLICATE KEY UPDATE descricao = VALUES(descricao), valor = VALUES(valor), data_despesa = VALUES(data_despesa), status = 'pago', observacao = VALUES(observacao)")
        ->execute([
            $orderId,
            $userId,
            "Frete — {$label} #{$orderId}",
            round($shipping, 2),
            date('Y-m-d'),
            "Frete confirmado — Pedido #{$orderId}",
        ]);
    return true;
}

final class VitrineImageException extends RuntimeException {}

function vitrineMediaDir(): string {
    $dir = __DIR__ . '/imagens/vitrine';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        apierr('Não foi possível preparar a pasta de imagens.', 500);
    }
    return $dir;
}

function uploadVitrineImagem(
    array $file,
    string $prefixo,
    int $maxBytes,
    int $targetWidth,
    int $targetHeight,
    int $minWidth,
    int $minHeight
): string {
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new VitrineImageException('Falha no upload da imagem.');
    }
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > $maxBytes) {
        throw new VitrineImageException('O arquivo ultrapassa o tamanho permitido.');
    }
    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new VitrineImageException('Upload de imagem inválido.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($tmpName);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new VitrineImageException('Formato não suportado. Utilize JPG, PNG ou WebP.');
    }

    $bytes = file_get_contents($tmpName);
    if ($bytes === false) {
        throw new VitrineImageException('Não foi possível processar esta imagem.');
    }
    $imageInfo = @getimagesizefromstring($bytes);
    if ($imageInfo === false || ($imageInfo['mime'] ?? '') !== $mime) {
        throw new VitrineImageException('O arquivo enviado não é uma imagem válida.');
    }
    $width = (int)$imageInfo[0];
    $height = (int)$imageInfo[1];
    if ($width < $minWidth || $height < $minHeight) {
        throw new VitrineImageException("A imagem é pequena demais. Dimensão mínima: {$minWidth} × {$minHeight} px.");
    }
    if ($width > ($targetWidth * 2) || $height > ($targetHeight * 2)) {
        throw new VitrineImageException('A imagem possui dimensões grandes demais.');
    }
    $expectedRatio = $targetWidth / $targetHeight;
    if (abs(($width / $height) - $expectedRatio) > 0.02) {
        throw new VitrineImageException('O enquadramento enviado possui proporção inválida.');
    }

    $source = @imagecreatefromstring($bytes);
    unset($bytes);
    if ($source === false) {
        throw new VitrineImageException('Não foi possível decodificar esta imagem.');
    }
    $destination = imagecreatetruecolor($targetWidth, $targetHeight);
    if ($destination === false) {
        imagedestroy($source);
        throw new VitrineImageException('Não foi possível normalizar esta imagem.');
    }

    $useWebP = function_exists('imagewebp');
    if ($useWebP) {
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 0, 0, 0, 127);
        imagefill($destination, 0, 0, $transparent);
        imagealphablending($destination, true);
    } else {
        $white = imagecolorallocate($destination, 255, 255, 255);
        imagefill($destination, 0, 0, $white);
    }
    $resampled = imagecopyresampled(
        $destination,
        $source,
        0,
        0,
        0,
        0,
        $targetWidth,
        $targetHeight,
        $width,
        $height
    );
    imagedestroy($source);
    if (!$resampled) {
        imagedestroy($destination);
        throw new VitrineImageException('Não foi possível redimensionar esta imagem.');
    }

    $random = bin2hex(random_bytes(16));
    $extension = $useWebP ? 'webp' : 'jpg';
    $filename = $prefixo . '_' . $random . '.' . $extension;
    $directory = vitrineMediaDir();
    $temporary = $directory . '/.' . $random . '.tmp';
    $saved = $useWebP
        ? imagewebp($destination, $temporary, 85)
        : imagejpeg($destination, $temporary, 88);
    imagedestroy($destination);
    if (!$saved || !is_file($temporary)) {
        @unlink($temporary);
        throw new VitrineImageException('Não foi possível salvar a imagem processada.');
    }

    $destinationPath = $directory . '/' . $filename;
    if (!rename($temporary, $destinationPath)) {
        @unlink($temporary);
        throw new VitrineImageException('Não foi possível concluir o upload da imagem.');
    }
    @chmod($destinationPath, 0644);
    return 'imagens/vitrine/' . $filename;
}

function apagarVitrineImagem(?string $path): void {
    if (!$path || str_contains($path, '..')) {
        return;
    }

    $permitidos = ['imagens/vitrine/', 'logos/'];
    foreach ($permitidos as $prefixo) {
        if (str_starts_with($path, $prefixo)) {
            $fullPath = __DIR__ . '/' . ltrim($path, '/');
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
            break;
        }
    }
}

/* ── CSRF para ações autenticadas do dono ── */
$csrf_required = ['salvar_config', 'pedido_status', 'toggle_produto', 'toggle_produto_bulk', 'set_destaque_produto', 'set_destaque_bulk', 'ordenar_destaques'];
if (in_array($acao, $csrf_required, true) && !verify_csrf_token()) {
    apierr('Token CSRF inválido', 403);
}

/* ═══════════════════════════════════════════════════════════════
   ROTEAMENTO
═══════════════════════════════════════════════════════════════ */
switch ($acao) {

/* ── GET: config pública da vitrine ─────────────────────────── */
case 'get_config':
    $slug = trim($_GET['slug'] ?? '');
    $user = resolveUser($pdo, $slug);
    if (!$user || !(int)$user['vitrine_ativa']) apierr('Vitrine não encontrada', 404);

    $s = $pdo->prepare("SELECT * FROM vitrine_config WHERE user_id = ? LIMIT 1");
    $s->execute([(int)$user['id']]);
    $cfg = $s->fetch(PDO::FETCH_ASSOC) ?: [];
    $experiencePreferences = uplar_catalogo_public_prefs($pdo, (int)$user['id'], uplar_vitrine_experience_preferences($cfg['experiencia_json'] ?? null));

    apiok([
        'user_id'         => (int)$user['id'],
        'username'        => $user['username'],
        'nome_loja'       => ($cfg['nome_loja'] ?? '') ?: $user['username'],
        'descricao'       => $cfg['descricao']    ?? '',
        'banner_path'     => $cfg['banner_path']  ?? null,
        'logo_path'       => $cfg['logo_path']    ?? null,
        'cor_primaria'    => $cfg['cor_primaria']  ?? '#3b6ff5',
        'whatsapp'        => $cfg['whatsapp'] ?? $user['whatsapp_number'] ?? '',
        'horario_ret'     => $cfg['horario_ret'] ?? 'Aberto das 10:00 às 18:00',
        'horario_ent'     => $cfg['horario_ent'] ?? 'Aberto das 12:00 às 18:00',
        'endereco'        => $cfg['endereco']  ?? '',
        'cidade_uf'       => $cfg['cidade_uf'] ?? '',
        'mostrar_estoque' => (bool)($cfg['mostrar_estoque'] ?? false),
        'valor_frete'     => (float)($cfg['valor_frete'] ?? 0),
        'modo_experiencia'=> uplar_vitrine_experience_mode($cfg['experiencia_json'] ?? null),
        'preferencias_experiencia' => $experiencePreferences,
    ]);
    break;

/* ── GET: listar produtos da vitrine (somente os expostos) ───── */
case 'listar_produtos':
    $slug  = trim($_GET['slug'] ?? '');
    $busca = trim($_GET['q']    ?? '');
    $user  = resolveUser($pdo, $slug);
    if (!$user || !(int)$user['vitrine_ativa']) apierr('Vitrine não encontrada', 404);

    $experienceSchemaReady = uplar_vitrine_experience_schema_disponivel($pdo);
    $cs = $pdo->prepare('SELECT mostrar_estoque' . ($experienceSchemaReady ? ', experiencia_json' : '') . ' FROM vitrine_config WHERE user_id = ? LIMIT 1');
    $cs->execute([(int)$user['id']]);
    $cfgRow      = $cs->fetch(PDO::FETCH_ASSOC);
    $mostrar_est = (bool)($cfgRow['mostrar_estoque'] ?? false);
    $experiencePreferences = uplar_catalogo_public_prefs($pdo, (int)$user['id'], uplar_vitrine_experience_preferences($cfgRow['experiencia_json'] ?? null));
    $showUnavailable = ($experiencePreferences['indisponiveis'] ?? 'ocultar') === 'mostrar';
    $showHighlights = !empty($experiencePreferences['mostrar_destaques']);

    // Filtra apenas produtos marcados como vitrine_exposto = 1
    $highlightValue = $showHighlights ? "COALESCE(p.vitrine_destaque, 'none')" : "'none'";
    $sql    = "SELECT p.id, p.nome, p.codigo, p.descricao, p.preco, p.quantidade, p.imagem, p.usa_tamanhos,
                      {$highlightValue} AS vitrine_destaque
               FROM produtos p
               WHERE p.user_id = ? AND p.vitrine_exposto = 1" . ($showUnavailable ? '' : ' AND p.quantidade > 0');
    $params = [(int)$user['id']];

    if ($busca !== '') {
        $sql   .= " AND (p.nome LIKE ? OR p.codigo LIKE ? OR p.descricao LIKE ?)";
        $like   = "%$busca%";
        $params = array_merge($params, [$like, $like, $like]);
    }
    $sql .= $showHighlights
        ? " ORDER BY CASE p.vitrine_destaque WHEN 'promotion' THEN 0 WHEN 'featured' THEN 1 ELSE 2 END, p.vitrine_destaque_ordem ASC, p.nome ASC"
        : " ORDER BY p.nome ASC";

    $s        = $pdo->prepare($sql);
    $s->execute($params);
    $produtos = $s->fetchAll(PDO::FETCH_ASSOC);

    $productCategories = [];
    foreach (($experiencePreferences['categorias'] ?? []) as $category) {
        foreach (($category['produto_ids'] ?? []) as $productId) {
            $productId = (int)$productId;
            if (!isset($productCategories[$productId])) {
                $productCategories[$productId] = ['categoria_id' => (string)$category['id'], 'categoria_nome' => (string)$category['nome']];
            }
        }
    }

    $customizableProducts = [];
    if ($complementsReady && $produtos) {
        $productIds = array_map('intval', array_column($produtos, 'id'));
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $customStmt = $pdo->prepare("SELECT v.produto_id FROM produto_complemento_vinculos v INNER JOIN produto_complemento_grupos g ON g.id=v.grupo_id AND g.user_id=v.user_id WHERE v.user_id=? AND v.ativo=1 AND g.ativo=1 AND v.produto_id IN ({$placeholders}) GROUP BY v.produto_id");
        $customStmt->execute(array_merge([(int)$user['id']], $productIds));
        $customizableProducts = array_fill_keys(array_map('intval', $customStmt->fetchAll(PDO::FETCH_COLUMN)), true);
    }

    $sizeStmt=$pdo->prepare('SELECT tamanho,quantidade,ordem,ativo FROM produto_tamanhos WHERE produto_id=? AND ativo=1 ORDER BY ordem,id');

    foreach ($produtos as &$p) {
        $p['id']    = (int)$p['id'];
        $p['preco'] = (float)$p['preco'];
        $p['estoque_disponivel'] = (int)$p['quantidade'];
        $p['disponivel'] = $p['estoque_disponivel'] > 0;
        $p['categoria_id'] = $productCategories[$p['id']]['categoria_id'] ?? null;
        $p['categoria_nome'] = $productCategories[$p['id']]['categoria_nome'] ?? null;
        $p['usa_tamanhos'] = (bool)$p['usa_tamanhos'];
        $p['tem_complementos'] = isset($customizableProducts[$p['id']]);
        $p['tamanhos'] = [];
        if($p['usa_tamanhos']){$sizeStmt->execute([$p['id']]);$p['tamanhos']=$sizeStmt->fetchAll(PDO::FETCH_ASSOC);foreach($p['tamanhos'] as &$size){$size['quantidade']=(int)$size['quantidade'];}unset($size);}
        $p['vitrine_destaque'] = in_array($p['vitrine_destaque'], ['featured', 'promotion'], true) ? $p['vitrine_destaque'] : 'none';
        if (!$mostrar_est) unset($p['quantidade']);
        else $p['quantidade'] = (int)$p['quantidade'];
    }
    unset($p);

    apiok(['produtos' => $produtos, 'total' => count($produtos)]);
    break;

/* ── GET (autenticado): listar TODOS os produtos com flag de exposição ── */
case 'listar_produtos_admin':
    $uid = requireOwner();

    $s = $pdo->prepare("
        SELECT id, nome, codigo, preco, quantidade, imagem,
               COALESCE(vitrine_exposto, 0) AS vitrine_exposto,
               COALESCE(vitrine_destaque, 'none') AS vitrine_destaque,
               COALESCE(vitrine_destaque_ordem, 0) AS vitrine_destaque_ordem
        FROM produtos
        WHERE user_id = ?
        ORDER BY nome ASC
    ");
    $s->execute([$uid]);
    $produtos = $s->fetchAll(PDO::FETCH_ASSOC);

    foreach ($produtos as &$p) {
        $p['id']             = (int)$p['id'];
        $p['preco']          = (float)$p['preco'];
        $p['quantidade']     = (int)$p['quantidade'];
        $p['vitrine_exposto'] = (bool)$p['vitrine_exposto'];
        $p['vitrine_destaque'] = in_array($p['vitrine_destaque'], ['featured', 'promotion'], true) ? $p['vitrine_destaque'] : 'none';
        $p['vitrine_destaque_ordem'] = (int)$p['vitrine_destaque_ordem'];
    }
    unset($p);

    apiok(['produtos' => $produtos]);
    break;

/* ── GET: personalizações e variações do produto ───────────── */
case 'produto_complementos':
    $slug = trim((string)($_GET['slug'] ?? ''));
    $productId = (int)($_GET['produto_id'] ?? 0);
    $user = resolveUser($pdo, $slug);
    if (!$user || !(int)$user['vitrine_ativa']) apierr('Vitrine não encontrada', 404);
    $productStmt = $pdo->prepare('SELECT id, usa_tamanhos FROM produtos WHERE id = ? AND user_id = ? AND quantidade > 0 AND vitrine_exposto = 1 LIMIT 1');
    $productStmt->execute([$productId, (int)$user['id']]);
    $product = $productStmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) apierr('Produto indisponível', 404);
    $groups = $complementsReady ? uplar_complementos_publicar_grupos(uplar_complementos_listar($pdo, (int)$user['id'], $productId)) : [];
    apiok([
        'configurado' => (bool)$groups,
        'grupos' => $groups,
        'usa_tamanhos' => (bool)$product['usa_tamanhos'],
        'tamanhos' => array_map(static fn($size) => [
            'tamanho' => (string)$size['tamanho'],
            'quantidade' => (int)$size['quantidade'],
        ], product_sizes_list($pdo, $productId, true)),
    ]);
    break;

/* ── POST (autenticado): liga/desliga exibição de produto na vitrine ── */
case 'toggle_produto':
    $uid = requireOwner();
    $pid = (int)($_POST['produto_id'] ?? 0);
    if ($pid < 1) apierr('Produto inválido');

    // Verifica que o produto pertence ao usuário
    $s = $pdo->prepare("SELECT id, vitrine_exposto FROM produtos WHERE id = ? AND user_id = ? LIMIT 1");
    $s->execute([$pid, $uid]);
    $prod = $s->fetch(PDO::FETCH_ASSOC);
    if (!$prod) apierr('Produto não encontrado', 404);

    $novo = $prod['vitrine_exposto'] ? 0 : 1;
    $pdo->prepare("UPDATE produtos SET vitrine_exposto = ? WHERE id = ? AND user_id = ?")
        ->execute([$novo, $pid, $uid]);

    apiok(['vitrine_exposto' => (bool)$novo]);
    break;

/* ── POST (autenticado): expor/ocultar múltiplos de uma vez ──── */
case 'toggle_produto_bulk':
    $uid = requireOwner();
    if (empty($_SESSION['csrf_token']) || !verify_csrf_token()) apierr('CSRF inválido', 403);

    $ids   = json_decode($_POST['ids'] ?? '[]', true) ?: [];
    $valor = (int)($_POST['vitrine_exposto'] ?? 0) ? 1 : 0;

    if (empty($ids)) apierr('Nenhum produto informado');

    // Filtra só IDs inteiros positivos que pertençam ao usuário
    $ids = array_filter(array_map('intval', $ids), fn($v) => $v > 0);
    if (empty($ids)) apierr('IDs inválidos');

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params       = array_values($ids);
    $params[]     = $uid;
    $pdo->prepare("UPDATE produtos SET vitrine_exposto = $valor WHERE id IN ($placeholders) AND user_id = ?")
        ->execute($params);

    apiok(['atualizados' => count($ids)]);
    break;

/* ── POST (autenticado): define o tratamento editorial de um produto ── */
case 'set_destaque_produto':
    $uid = requireOwner();
    $pid = (int)($_POST['produto_id'] ?? 0);
    $tipo = $_POST['tipo'] ?? 'none';
    if ($pid < 1 || !in_array($tipo, ['none', 'featured', 'promotion'], true)) apierr('Dados inválidos');

    $s = $pdo->prepare("SELECT id FROM produtos WHERE id = ? AND user_id = ? LIMIT 1");
    $s->execute([$pid, $uid]);
    if (!$s->fetch()) apierr('Produto não encontrado', 404);

    $ordem = $tipo === 'none' ? 0 : $pid;
    $pdo->prepare("UPDATE produtos SET vitrine_destaque = ?, vitrine_destaque_ordem = ? WHERE id = ? AND user_id = ?")
        ->execute([$tipo, $ordem, $pid, $uid]);
    apiok(['vitrine_destaque' => $tipo, 'vitrine_destaque_ordem' => $ordem]);
    break;

/* ── POST (autenticado): define tratamento editorial em massa ───────── */
case 'set_destaque_bulk':
    $uid = requireOwner();
    $tipo = $_POST['tipo'] ?? 'none';
    $ids = json_decode($_POST['ids'] ?? '[]', true) ?: [];
    if (!in_array($tipo, ['none', 'featured', 'promotion'], true)) apierr('Tipo inválido');
    $ids = array_values(array_filter(array_map('intval', $ids), fn($id) => $id > 0));
    if (!$ids) apierr('Nenhum produto informado');

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = [$tipo, $tipo === 'none' ? 0 : 1];
    foreach ($ids as $id) $params[] = $id;
    $params[] = $uid;
    $pdo->prepare("UPDATE produtos
        SET vitrine_destaque = ?, vitrine_destaque_ordem = CASE WHEN ? = 0 THEN 0 ELSE id END
        WHERE id IN ($placeholders) AND user_id = ?")
        ->execute($params);
    apiok(['atualizados' => count($ids), 'vitrine_destaque' => $tipo]);
    break;

/* ── POST (autenticado): persiste a prioridade dentro de cada tipo editorial */
case 'ordenar_destaques':
    $uid = requireOwner();
    $tipo = $_POST['tipo'] ?? '';
    $ids = json_decode($_POST['ids'] ?? '[]', true) ?: [];
    if (!in_array($tipo, ['featured', 'promotion'], true)) apierr('Tipo inválido');
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
    if (!$ids) apierr('Nenhum produto informado');

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $check = $pdo->prepare("SELECT id FROM produtos WHERE user_id = ? AND vitrine_destaque = ? AND id IN ($placeholders)");
    $check->execute(array_merge([$uid, $tipo], $ids));
    if (count($check->fetchAll(PDO::FETCH_COLUMN)) !== count($ids)) apierr('Produtos inválidos');

    $update = $pdo->prepare("UPDATE produtos SET vitrine_destaque_ordem = ? WHERE id = ? AND user_id = ? AND vitrine_destaque = ?");
    $pdo->beginTransaction();
    try {
        foreach ($ids as $order => $id) $update->execute([$order + 1, $id, $uid, $tipo]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Erro ao ordenar destaques: ' . $e->getMessage());
        apierr('Não foi possível salvar a ordem.', 500);
    }
    apiok(['ordenados' => count($ids)]);
    break;

/* ── POST: adicionar produto ao carrinho ─────────────────────── */
case 'carrinho_add':
    $slug       = trim($_POST['slug']        ?? '');
    $produto_id = (int)($_POST['produto_id'] ?? 0);
    $qtd        = max(1, (int)($_POST['quantidade'] ?? 1));
    $tamanhoRaw = trim((string)($_POST['tamanho'] ?? ''));
    $user       = resolveUser($pdo, $slug);

    if (!$user || !(int)$user['vitrine_ativa']) apierr('Vitrine inválida');
    if ($produto_id < 1) apierr('Produto inválido');

    vitrineRequireOrderingEnabled(vitrineExperiencePreferences($pdo, (int)$user['id']));

    $ps = $pdo->prepare("SELECT id, quantidade, preco, usa_tamanhos FROM produtos WHERE id = ? AND user_id = ? AND quantidade > 0 AND vitrine_exposto = 1 LIMIT 1");
    $ps->execute([$produto_id, (int)$user['id']]);
    $prod = $ps->fetch(PDO::FETCH_ASSOC);
    if (!$prod) apierr('Produto indisponível');
    $tamanho = $tamanhoRaw !== '' ? product_sizes_normalize($tamanhoRaw) : null;
    $estoque=(int)$prod['quantidade'];
    if((int)$prod['usa_tamanhos']){
        if($tamanho===null) apierr('Escolha um tamanho.');
        $vs=$pdo->prepare('SELECT quantidade FROM produto_tamanhos WHERE produto_id=? AND tamanho=? AND ativo=1 LIMIT 1');$vs->execute([$produto_id,$tamanho]);$estoque=(int)$vs->fetchColumn();
        if($estoque<1) apierr('Tamanho indisponível.');
    } elseif($tamanho!==null) apierr('Este produto não possui tamanhos.');

    $submittedGroups = json_decode((string)($_POST['personalizacao'] ?? '[]'), true);
    if (!is_array($submittedGroups)) apierr('Personalização inválida.');
    $itemNote = mb_substr(trim((string)($_POST['observacao_item'] ?? '')), 0, 500);
    if (!$complementsReady && ($submittedGroups || $itemNote !== '')) apierr('A personalização ainda não está disponível nesta loja.', 503);
    $validated = $complementsReady
        ? uplar_complementos_validar($pdo, (int)$user['id'], $produto_id, ['grupos' => $submittedGroups, 'observacao' => $itemNote])
        : null;
    $personalization = $validated['snapshot'] ?? null;
    $hasPersonalization = $personalization && (!empty($personalization['grupos']) || !empty($personalization['observacao']));
    $personalizationHash = $hasPersonalization ? uplar_complementos_hash($personalization) : '';

    $sess = session_id();
    if ($complementsReady) {
        $es = $pdo->prepare("SELECT id, quantidade FROM vitrine_carrinho WHERE sessao = ? AND user_id = ? AND produto_id = ? AND tamanho <=> ? AND personalizacao_hash = ? LIMIT 1");
        $es->execute([$sess, (int)$user['id'], $produto_id, $tamanho, $personalizationHash]);
    } else {
        $es = $pdo->prepare("SELECT id, quantidade FROM vitrine_carrinho WHERE sessao = ? AND user_id = ? AND produto_id = ? AND tamanho <=> ? LIMIT 1");
        $es->execute([$sess, (int)$user['id'], $produto_id, $tamanho]);
    }
    $existing = $es->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $nova_qtd = min((int)$existing['quantidade'] + $qtd, $estoque);
        $pdo->prepare("UPDATE vitrine_carrinho SET quantidade = ? WHERE id = ?")
            ->execute([$nova_qtd, (int)$existing['id']]);
    } else {
        if ($complementsReady) {
            $pdo->prepare("INSERT INTO vitrine_carrinho (sessao, user_id, produto_id, tamanho, quantidade, personalizacao_hash, personalizacao_json) VALUES (?,?,?,?,?,?,?)")
                ->execute([$sess, (int)$user['id'], $produto_id, $tamanho, min($qtd, $estoque), $personalizationHash, $hasPersonalization ? json_encode($personalization, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null]);
        } else {
            $pdo->prepare("INSERT INTO vitrine_carrinho (sessao, user_id, produto_id, tamanho, quantidade) VALUES (?,?,?,?,?)")
                ->execute([$sess, (int)$user['id'], $produto_id, $tamanho, min($qtd, $estoque)]);
        }
    }

    apiok(['message' => 'Adicionado ao carrinho']);
    break;

/* ── POST: editar item do carrinho sem substituir ou perder seu ID ── */
case 'carrinho_editar':
    $slug = trim((string)($_POST['slug'] ?? ''));
    $cartId = filter_var($_POST['carrinho_id'] ?? null, FILTER_VALIDATE_INT);
    $productId = filter_var($_POST['produto_id'] ?? null, FILTER_VALIDATE_INT);
    $quantity = filter_var($_POST['quantidade'] ?? null, FILTER_VALIDATE_INT);
    $sizeRaw = trim((string)($_POST['tamanho'] ?? ''));
    $token = trim((string)($_POST['request_token'] ?? ''));
    if (!$cartId || $cartId < 1 || !$productId || $productId < 1 || !$quantity || $quantity < 1 || $token === '' || strlen($token) > 128) {
        apierr('Revise os dados do item antes de salvar.', 422);
    }
    $user = resolveUser($pdo, $slug);
    if (!$user || !(int)$user['vitrine_ativa']) apierr('Vitrine inválida', 404);
    vitrineRequireOrderingEnabled(vitrineExperiencePreferences($pdo, (int)$user['id']));
    $replayKey = hash('sha256', (int)$user['id'] . ':' . $token);
    $replay = vitrineCartEditReplay($replayKey);
    if ($replay !== null) {
        if ((int)($replay['carrinho_id'] ?? 0) !== (int)$cartId) apierr('Este identificador de tentativa já foi usado para outro item. Tente novamente.', 409);
        apiok($replay);
    }

    $submittedGroups = json_decode((string)($_POST['personalizacao'] ?? '[]'), true);
    if (!is_array($submittedGroups)) apierr('Revise as opções de personalização.', 422);
    $itemNote = mb_substr(trim((string)($_POST['observacao_item'] ?? '')), 0, 500);
    try {
        $size = $sizeRaw !== '' ? product_sizes_normalize($sizeRaw) : null;
    } catch (InvalidArgumentException $e) {
        apierr('O tamanho informado é inválido. Escolha uma opção disponível.', 422);
    }

    try {
        $pdo->beginTransaction();
        $cartStmt = $pdo->prepare('SELECT id FROM vitrine_carrinho WHERE id = ? AND sessao = ? AND user_id = ? FOR UPDATE');
        $cartStmt->execute([(int)$cartId, session_id(), (int)$user['id']]);
        if (!$cartStmt->fetchColumn()) {
            $pdo->rollBack();
            apierr('O item não está mais no seu carrinho. Atualize a página e tente novamente.', 404);
        }

        $productStmt = $pdo->prepare('SELECT id, quantidade, usa_tamanhos FROM produtos WHERE id = ? AND user_id = ? AND quantidade > 0 AND vitrine_exposto = 1 LIMIT 1 FOR UPDATE');
        $productStmt->execute([(int)$productId, (int)$user['id']]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            $pdo->rollBack();
            apierr('Este produto não está mais disponível nesta vitrine.', 409);
        }

        $stock = (int)$product['quantidade'];
        if ((int)$product['usa_tamanhos']) {
            if ($size === null) {
                $pdo->rollBack();
                apierr('Escolha um tamanho disponível.', 422);
            }
            $sizeStmt = $pdo->prepare('SELECT quantidade FROM produto_tamanhos WHERE produto_id = ? AND tamanho = ? AND ativo = 1 LIMIT 1 FOR UPDATE');
            $sizeStmt->execute([(int)$productId, $size]);
            $sizeStock = $sizeStmt->fetchColumn();
            if ($sizeStock === false || (int)$sizeStock < 1) {
                $pdo->rollBack();
                apierr('O tamanho escolhido não está mais disponível. Escolha outro.', 409);
            }
            $stock = min($stock, (int)$sizeStock);
        } elseif ($size !== null) {
            $pdo->rollBack();
            apierr('Este produto não possui tamanhos.', 422);
        }
        if ($quantity > $stock) {
            $pdo->rollBack();
            apierr('A quantidade excede o estoque disponível. Reduza a quantidade para salvar.', 409);
        }

        if (!$complementsReady && ($submittedGroups || $itemNote !== '')) {
            $pdo->rollBack();
            apierr('A personalização ainda não está disponível nesta loja.', 503);
        }
        try {
            $validated = $complementsReady
                ? uplar_complementos_validar($pdo, (int)$user['id'], (int)$productId, ['grupos' => $submittedGroups, 'observacao' => $itemNote])
                : null;
        } catch (Throwable $e) {
            $pdo->rollBack();
            apierr('Revise as opções: uma delas mudou ou não está mais disponível.', 409);
        }
        $snapshot = $validated['snapshot'] ?? null;
        $hasPersonalization = is_array($snapshot) && (!empty($snapshot['grupos']) || !empty($snapshot['observacao']));
        $personalizationHash = $hasPersonalization ? uplar_complementos_hash($snapshot) : '';
        if ($complementsReady) {
            $update = $pdo->prepare('UPDATE vitrine_carrinho SET produto_id = ?, tamanho = ?, quantidade = ?, personalizacao_hash = ?, personalizacao_json = ? WHERE id = ? AND sessao = ? AND user_id = ?');
            $update->execute([
                (int)$productId,
                $size,
                (int)$quantity,
                $personalizationHash,
                $hasPersonalization ? json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                (int)$cartId,
                session_id(),
                (int)$user['id'],
            ]);
        } else {
            $pdo->prepare('UPDATE vitrine_carrinho SET produto_id = ?, tamanho = ?, quantidade = ? WHERE id = ? AND sessao = ? AND user_id = ?')
                ->execute([(int)$productId, $size, (int)$quantity, (int)$cartId, session_id(), (int)$user['id']]);
        }
        $pdo->commit();
        $response = ['message' => 'Item atualizado.', 'carrinho_id' => (int)$cartId];
        vitrineCartEditRemember($replayKey, $response);
        apiok($response);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Erro ao editar item da vitrine: ' . $e->getMessage());
        apierr('Não foi possível atualizar o item. Tente novamente.', 500);
    }
    break;

/* ── POST: restaurar carrinho salvo no navegador ─────────────── */
case 'carrinho_sincronizar':
    $slug = trim($_POST['slug'] ?? '');
    $user = resolveUser($pdo, $slug);
    if (!$user || !(int)$user['vitrine_ativa']) apierr('Vitrine inválida');

    $rawItems = json_decode((string)($_POST['itens'] ?? '[]'), true);
    if (!is_array($rawItems)) apierr('Carrinho inválido');
    if (count($rawItems) > 100) apierr('Carrinho inválido');

    $requested = [];
    foreach ($rawItems as $item) {
        if (!is_array($item)) continue;
        $produtoId = (int)($item['produto_id'] ?? 0);
        $tamanho = trim((string)($item['tamanho'] ?? ''));
        $tamanho = $tamanho === '' ? null : product_sizes_normalize($tamanho);
        $quantidade = (int)($item['quantidade'] ?? 0);
        if ($produtoId < 1 || $quantidade < 1) continue;
        $selections = is_array($item['personalizacao'] ?? null) ? $item['personalizacao'] : [];
        $itemNote = mb_substr(trim((string)($item['observacao_item'] ?? '')), 0, 500);
        $requested[] = [
            'produto_id' => $produtoId,
            'tamanho' => $tamanho,
            'quantidade' => min(999, $quantidade),
            'grupos' => $selections,
            'observacao' => $itemNote,
        ];
    }

    $product = $pdo->prepare('SELECT id, quantidade, preco, usa_tamanhos FROM produtos WHERE id = ? AND user_id = ? AND quantidade > 0 AND vitrine_exposto = 1 LIMIT 1');
    $variant = $pdo->prepare('SELECT quantidade FROM produto_tamanhos WHERE produto_id=? AND tamanho=? AND ativo=1');
    $preparedItems = [];
    foreach ($requested as $item) {
        $product->execute([$item['produto_id'], (int)$user['id']]);
        $current = $product->fetch(PDO::FETCH_ASSOC);
        if (!$current) apierr('Um produto salvo no carrinho não está mais disponível. Revise o carrinho antes de continuar.', 409);
        if ((int)$current['usa_tamanhos'] && $item['tamanho'] === null) apierr('Revise os tamanhos salvos no carrinho.', 409);
        if (!(int)$current['usa_tamanhos'] && $item['tamanho'] !== null) apierr('Revise os produtos salvos no carrinho.', 409);
        $available = (int)$current['quantidade'];
        if ($item['tamanho'] !== null) {
            $variant->execute([$item['produto_id'], $item['tamanho']]);
            $available = (int)$variant->fetchColumn();
        }
        if ($available < 1 || $item['quantidade'] > $available) {
            apierr('O estoque de um item salvo no carrinho mudou. Revise o carrinho antes de continuar.', 409);
        }
        if (!$complementsReady && ($item['grupos'] || $item['observacao'] !== '')) apierr('Revise as personalizações salvas no carrinho.', 409);
        try {
            $validated = $complementsReady
                ? uplar_complementos_validar($pdo, (int)$user['id'], $item['produto_id'], ['grupos' => $item['grupos'], 'observacao' => $item['observacao']])
                : null;
        } catch (Throwable $e) {
            apierr('Uma personalização mudou. Revise esse produto antes de continuar.', 409);
        }
        $snapshot = $validated['snapshot'] ?? null;
        $hasPersonalization = $snapshot && (!empty($snapshot['grupos']) || !empty($snapshot['observacao']));
        $preparedItems[] = [
            'produto_id' => $item['produto_id'],
            'tamanho' => $item['tamanho'],
            'quantidade' => min($item['quantidade'], $available),
            'snapshot' => $snapshot,
            'hash' => $hasPersonalization ? uplar_complementos_hash($snapshot) : '',
        ];
    }

    $sess = session_id();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM vitrine_carrinho WHERE sessao = ? AND user_id = ?')
            ->execute([$sess, (int)$user['id']]);

        $insert = $complementsReady
            ? $pdo->prepare('INSERT INTO vitrine_carrinho (sessao, user_id, produto_id, tamanho, quantidade, personalizacao_hash, personalizacao_json) VALUES (?,?,?,?,?,?,?)')
            : $pdo->prepare('INSERT INTO vitrine_carrinho (sessao, user_id, produto_id, tamanho, quantidade) VALUES (?,?,?,?,?)');
        $restored = 0;
        foreach ($preparedItems as $item) {
            $validQuantity = (int)$item['quantidade'];
            if ($validQuantity < 1) continue;
            if ($complementsReady) {
                $hasPersonalization = $item['snapshot'] && (!empty($item['snapshot']['grupos']) || !empty($item['snapshot']['observacao']));
                $insert->execute([$sess, (int)$user['id'], $item['produto_id'], $item['tamanho'], $validQuantity, $item['hash'], $hasPersonalization ? json_encode($item['snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null]);
            } else {
                $insert->execute([$sess, (int)$user['id'], $item['produto_id'], $item['tamanho'], $validQuantity]);
            }
            $restored += $validQuantity;
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('Erro ao sincronizar carrinho: ' . $e->getMessage());
        apierr('Não foi possível restaurar o carrinho.', 500);
    }

    apiok(['quantidade' => $restored]);
    break;

/* ── GET|POST: obter carrinho atual ─────────────────────────── */
case 'carrinho_get':
    $slug = trim($_GET['slug'] ?? $_POST['slug'] ?? '');
    $user = resolveUser($pdo, $slug);
    if (!$user) apierr('Vitrine inválida');

    $sess = session_id();
    $pdo->prepare("DELETE FROM vitrine_carrinho WHERE adicionado < DATE_SUB(NOW(), INTERVAL 24 HOUR)")->execute();

    $personalizationSelect = $complementsReady ? 'vc.personalizacao_json' : 'NULL AS personalizacao_json';

    $st = $pdo->prepare("
        SELECT vc.id,
               vc.quantidade            AS qtd_carrinho,
               vc.tamanho,
               {$personalizationSelect},
               p.id                    AS produto_id,
               p.nome,
               p.preco,
               p.imagem,
               CASE WHEN vc.tamanho IS NULL THEN p.quantidade ELSE pt.quantidade END AS estoque
        FROM vitrine_carrinho vc
        JOIN produtos p ON p.id = vc.produto_id AND p.user_id = vc.user_id
        LEFT JOIN produto_tamanhos pt ON pt.produto_id=vc.produto_id AND pt.tamanho=vc.tamanho AND pt.ativo=1
        WHERE vc.sessao = ? AND vc.user_id = ?
        ORDER BY vc.adicionado DESC
    ");
    $st->execute([$sess, (int)$user['id']]);
    $itens = $st->fetchAll(PDO::FETCH_ASSOC);

    // Busca valor_frete configurado
    $fs = $pdo->prepare("SELECT valor_frete FROM vitrine_config WHERE user_id = ? LIMIT 1");
    $fs->execute([(int)$user['id']]);
    $frRow     = $fs->fetch(PDO::FETCH_ASSOC);
    $val_frete = (float)($frRow['valor_frete'] ?? 0);

    $totalCents = 0;
    $count = 0;
    $reviewRequired = false;
    foreach ($itens as &$i) {
        $i['produto_id']   = (int)$i['produto_id'];
        $requestedQuantity = max(1, (int)$i['qtd_carrinho']);
        $availableStock = max(0, (int)$i['estoque']);
        $stockStale = $requestedQuantity > $availableStock;
        $i['qtd_carrinho'] = $requestedQuantity;
        $basePrice = (float)$i['preco'];
        $personalization = is_string($i['personalizacao_json'] ?? null)
            ? json_decode($i['personalizacao_json'], true)
            : null;
        $personalization = is_array($personalization) ? $personalization : null;
        $stale = $stockStale;
        if ($complementsReady) {
            try {
                $fresh = uplar_complementos_validar($pdo, (int)$user['id'], $i['produto_id'], vitrineComplementosInput($personalization ?? []));
                if ($personalization && json_encode($personalization['grupos'] ?? [], JSON_UNESCAPED_UNICODE) !== json_encode($fresh['snapshot']['grupos'] ?? [], JSON_UNESCAPED_UNICODE)) {
                    $stale = true;
                }
                $appliedCents = $personalization
                    ? vitrineComplementosUnitCents($personalization, $basePrice)
                    : (int)$fresh['preco_unitario_centavos'];
                if ($personalization && $appliedCents !== (int)$fresh['preco_unitario_centavos']) $stale = true;
            } catch (Throwable $e) {
                $stale = true;
                $appliedCents = $personalization
                    ? vitrineComplementosUnitCents($personalization, $basePrice)
                    : (int)round($basePrice * 100);
            }
        } else {
            $appliedCents = (int)round($basePrice * 100);
        }
        $i['personalizacao'] = $personalization ? uplar_complementos_publicar_snapshot($personalization) : null;
        unset($i['personalizacao_json']);
        $i['preco'] = $appliedCents / 100;
        $i['estoque']      = $availableStock;
        $i['tamanho']      = $i['tamanho'] !== null ? (string)$i['tamanho'] : null;
        $i['revisao_requerida'] = $stale;
        $i['aviso'] = $stockStale
            ? 'O estoque disponível mudou. Ajuste a quantidade ou remova este item antes de finalizar.'
            : ($stale ? 'Uma opção ou preço mudou. Remova e configure novamente antes de finalizar.' : null);
        $reviewRequired = $reviewRequired || $stale;
        $totalCents += $appliedCents * $i['qtd_carrinho'];
        $count            += $i['qtd_carrinho'];
    }
    unset($i);

    apiok([
        'itens'       => $itens,
        'total'       => $totalCents / 100,
        'count'       => $count,
        'valor_frete' => $val_frete,
        'revisao_requerida' => $reviewRequired,
    ]);
    break;

/* ── POST: remover um item do carrinho ───────────────────────── */
case 'carrinho_remover':
    $slug        = trim($_POST['slug']         ?? '');
    $carrinho_id = (int)($_POST['carrinho_id'] ?? 0);
    $user        = resolveUser($pdo, $slug);
    if (!$user || $carrinho_id < 1) apierr('Dados inválidos');

    $sess = session_id();
    $pdo->prepare("DELETE FROM vitrine_carrinho WHERE id = ? AND sessao = ? AND user_id = ?")
        ->execute([$carrinho_id, $sess, (int)$user['id']]);
    apiok();
    break;

/* ── POST: limpar todo o carrinho ────────────────────────────── */
case 'carrinho_limpar':
    $slug = trim($_POST['slug'] ?? '');
    $user = resolveUser($pdo, $slug);
    if (!$user) apierr('Dados inválidos');

    $pdo->prepare("DELETE FROM vitrine_carrinho WHERE sessao = ? AND user_id = ?")
        ->execute([session_id(), (int)$user['id']]);
    apiok();
    break;

/* ── POST: atualizar quantidade de um item do carrinho ───────── */
case 'carrinho_qtd':
    $slug        = trim($_POST['slug']         ?? '');
    $carrinho_id = (int)($_POST['carrinho_id'] ?? 0);
    $qtd         = max(1, (int)($_POST['quantidade'] ?? 1));
    $user        = resolveUser($pdo, $slug);
    if (!$user || $carrinho_id < 1) apierr('Dados inválidos');

    $sess  = session_id();
    $check = $pdo->prepare("
        SELECT vc.id, vc.tamanho, p.usa_tamanhos, CASE WHEN vc.tamanho IS NULL THEN p.quantidade ELSE pt.quantidade END AS estoque
        FROM vitrine_carrinho vc
        JOIN produtos p ON p.id = vc.produto_id AND p.user_id = vc.user_id AND p.vitrine_exposto = 1
        LEFT JOIN produto_tamanhos pt ON pt.produto_id=vc.produto_id AND pt.tamanho=vc.tamanho AND pt.ativo=1
        WHERE vc.id = ? AND vc.sessao = ? AND vc.user_id = ?
        LIMIT 1
    ");
    $check->execute([$carrinho_id, $sess, (int)$user['id']]);
    $row = $check->fetch(PDO::FETCH_ASSOC);
    if (!$row) apierr('Item não encontrado', 404);

    if ((int)$row['estoque'] < 1) {
        $pdo->prepare('DELETE FROM vitrine_carrinho WHERE id = ?')->execute([$carrinho_id]);
        apiok(['quantidade' => 0, 'removido' => true]);
    }

    $nova_qtd = min($qtd, (int)$row['estoque']);
    $pdo->prepare("UPDATE vitrine_carrinho SET quantidade = ? WHERE id = ?")
        ->execute([$nova_qtd, $carrinho_id]);
    apiok(['quantidade' => $nova_qtd]);
    break;

/* ── POST: finalizar pedido ──────────────────────────────────── */
case 'fazer_pedido':
    $slug         = trim($_POST['slug']         ?? '');
    $tipo_raw     = $_POST['tipo']              ?? 'retirada';
    if (!in_array($tipo_raw, ['retirada', 'entrega'], true)) apierr('Escolha entrega ou retirada.', 422);
    $tipo         = $tipo_raw;
    $nome         = substr(trim($_POST['nome']     ?? ''), 0, 120);
    $tel          = substr(trim($_POST['tel']      ?? ''), 0, 30);
    $endereco_ent = substr(trim($_POST['endereco'] ?? ''), 0, 255);
    $obs          = substr(trim($_POST['obs']      ?? ''), 0, 500);
    $user         = resolveUser($pdo, $slug);

    if (!$user) apierr('Vitrine inválida');
    $experiencePreferences = vitrineExperiencePreferences($pdo, (int)$user['id']);
    vitrineRequireOrderingEnabled($experiencePreferences);
    vitrineRequireOrderType($experiencePreferences, $tipo);
    if ($tipo === 'entrega' && $endereco_ent === '') apierr('Informe o endereço de entrega');

    $sess  = session_id();
    $personalizationSelect = $complementsReady ? 'vc.personalizacao_json' : 'NULL AS personalizacao_json';
    $items = $pdo->prepare("
        SELECT vc.quantidade AS qtd,
               vc.tamanho,
               {$personalizationSelect},
               p.id          AS pid,
               p.nome,
               p.preco,
               p.preco_custo,
               p.codigo,
               p.usa_tamanhos,
               CASE WHEN vc.tamanho IS NULL THEN p.quantidade ELSE pt.quantidade END AS estoque
        FROM vitrine_carrinho vc
        JOIN produtos p ON p.id = vc.produto_id AND p.user_id = vc.user_id
        LEFT JOIN produto_tamanhos pt ON pt.produto_id=vc.produto_id AND pt.tamanho=vc.tamanho AND pt.ativo=1
        WHERE vc.sessao = ? AND vc.user_id = ?
    ");
    $items->execute([$sess, (int)$user['id']]);
    $itens = $items->fetchAll(PDO::FETCH_ASSOC);

    if (!$itens) apierr('Carrinho vazio');

    $totalCents = 0;
    $itens_out = [];
    foreach ($itens as $i) {
        if (((int)$i['usa_tamanhos'] && empty($i['tamanho'])) || (!(int)$i['usa_tamanhos'] && !empty($i['tamanho']))) {
            apierr('Revise o tamanho ou a variação de um item do carrinho.', 409);
        }
        $qtd_real = min((int)$i['qtd'], (int)$i['estoque']);
        if ($qtd_real < 1 || $qtd_real < (int)$i['qtd']) apierr('O estoque mudou. Ajuste a quantidade do item antes de finalizar.', 409);
        $personalization = is_string($i['personalizacao_json'] ?? null) ? json_decode($i['personalizacao_json'], true) : null;
        $personalization = is_array($personalization) ? $personalization : null;
        $validated = null;
        if ($complementsReady) {
            try {
                $validated = uplar_complementos_validar($pdo, (int)$user['id'], (int)$i['pid'], vitrineComplementosInput($personalization ?? []));
            } catch (Throwable $e) {
                apierr('Uma personalização ou preço mudou. Remova e configure novamente os itens indicados antes de finalizar.', 409);
            }
            if ($personalization) {
                if (json_encode($personalization['grupos'] ?? [], JSON_UNESCAPED_UNICODE) !== json_encode($validated['snapshot']['grupos'] ?? [], JSON_UNESCAPED_UNICODE)
                    || vitrineComplementosUnitCents($personalization, (float)$i['preco']) !== (int)$validated['preco_unitario_centavos']) {
                    apierr('Uma personalização ou preço mudou. Remova e configure novamente os itens indicados antes de finalizar.', 409);
                }
            }
        }
        $unitCents = $validated ? (int)$validated['preco_unitario_centavos'] : (int)round((float)$i['preco'] * 100);
        $unitPrice = $unitCents / 100;
        $subCents = $qtd_real * $unitCents;
        $sub = $subCents / 100;
        $totalCents += $subCents;
        if ($validated) {
            $personalization = $validated['snapshot'];
            $personalization['custos_completos'] = (bool)($validated['custos_completos'] ?? false) && (float)($i['preco_custo'] ?? 0) > 0;
            $personalization['custo_base_centavos'] = (int)round((float)($i['preco_custo'] ?? 0) * 100);
        }
        $itens_out[] = [
            'produto_id' => (int)$i['pid'],
            'nome'       => $i['nome'],
            'codigo'     => $i['codigo'] ?? '',
            'tamanho'    => $i['tamanho'] !== null ? (string)$i['tamanho'] : null,
            'preco'      => $unitPrice,
            'quantidade' => $qtd_real,
            'subtotal'   => $sub,
            'personalizacao' => $personalization,
            'observacao_item' => trim((string)($personalization['observacao'] ?? '')),
        ];
    }
    if (!$itens_out) apierr('Nenhum item disponível em estoque');

    $valor_frete = 0.0;
    // O valor financeiro do frete vem exclusivamente da configuração da vitrine.
    if ($tipo === 'entrega') {
        $fs = $pdo->prepare("SELECT valor_frete FROM vitrine_config WHERE user_id = ? LIMIT 1");
        $fs->execute([(int)$user['id']]);
        $frRow       = $fs->fetch(PDO::FETCH_ASSOC);
        $valor_frete = max(0, (float)($frRow['valor_frete'] ?? 0));
    }

    $total_com_frete = ($totalCents / 100) + ($tipo === 'entrega' ? $valor_frete : 0);

    $pdo->prepare("
        INSERT INTO vitrine_pedidos
            (user_id, origem, sessao, cliente_nome, cliente_tel, tipo_entrega, endereco_ent,
             total, valor_frete, itens_json, obs)
        VALUES (?,'vitrine',?,?,?,?,?,?,?,?,?)
    ")->execute([
        (int)$user['id'],
        $sess,
        $nome ?: null,
        $tel  ?: null,
        $tipo,
        $tipo === 'entrega' ? $endereco_ent : null,
        round($total_com_frete, 2),
        $tipo === 'entrega' ? round($valor_frete, 2) : 0,
        json_encode($itens_out, JSON_UNESCAPED_UNICODE),
        $obs  ?: null,
    ]);
    $pedido_id = (int)$pdo->lastInsertId();

    try {
        vitrine_analytics_record($pdo, (int)$user['id'], 'order_created', [
            'session_id' => $sess,
            'visitor_id' => $sess,
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'value' => (int)round($total_com_frete * 100),
        ]);
    } catch (Throwable $e) {
        error_log('[vitrine analytics order] ' . $e->getMessage());
    }

    $pdo->prepare("DELETE FROM vitrine_carrinho WHERE sessao = ? AND user_id = ?")
        ->execute([$sess, (int)$user['id']]);

    $cfg_s = $pdo->prepare("SELECT whatsapp FROM vitrine_config WHERE user_id = ? LIMIT 1");
    $cfg_s->execute([(int)$user['id']]);
    $cfgWa = $cfg_s->fetch(PDO::FETCH_ASSOC);
    $wa    = ($cfgWa['whatsapp'] ?? '') ?: ($user['whatsapp_number'] ?? '');

    $msg  = "*PEDIDO #{$pedido_id}*\n\n";
    $msg .= $nome ? " *Cliente:* $nome\n" : '';
    $msg .= $tel  ? " *Contato:* $tel\n" : '';
    $msg .= " *Tipo:* " . ($tipo === 'retirada' ? 'Retirada na loja' : 'Entrega') . "\n";
    if ($tipo === 'entrega' && $endereco_ent) $msg .= " *Endereço:* $endereco_ent\n";
    $msg .= "\n*Itens:*\n";
    foreach ($itens_out as $it) {
        $msg .= "\n*{$it['quantidade']}x {$it['nome']}*\n" . ($it['tamanho'] ? "Tamanho: {$it['tamanho']}\n" : '');
        foreach (($it['personalizacao']['grupos'] ?? []) as $group) {
            if (!is_array($group)) continue;
            $options = [];
            foreach (($group['opcoes'] ?? []) as $option) {
                if (!is_array($option)) continue;
                $name = trim((string)($option['nome'] ?? ''));
                if ($name === '') continue;
                $qtyLabel = (int)($option['quantidade'] ?? 1) > 1 ? ' × ' . (int)$option['quantidade'] : '';
                $options[] = $name . $qtyLabel;
            }
            if ($options) $msg .= trim((string)($group['nome'] ?? 'Personalização')) . ":\n  • " . implode("\n  • ", $options) . "\n";
        }
        $itemNote = trim((string)($it['observacao_item'] ?? ''));
        if ($itemNote !== '') $msg .= "Observação: {$itemNote}\n";
        $msg .= "Unitário: R$ " . number_format($it['preco'], 2, ',', '.') . "\nTotal do item: R$ " . number_format($it['subtotal'], 2, ',', '.') . "\n";
    }
    if ($tipo === 'entrega' && $valor_frete > 0) {
        $msg .= " *Frete:* R$ " . number_format($valor_frete, 2, ',', '.') . "\n";
    }
    $msg .= "\n*Total: R$ " . number_format($total_com_frete, 2, ',', '.') . "*";
    if ($obs) $msg .= "\n\n *Obs:* $obs";

    apiok([
        'pedido_id'   => $pedido_id,
        'total'       => round($total_com_frete, 2),
        'valor_frete' => round($valor_frete, 2),
        'whatsapp'    => preg_replace('/\D/', '', $wa),
        'wa_msg'      => urlencode($msg),
    ]);
    break;

/* ── GET: ler config da minha vitrine (autenticado) ─────────── */
case 'get_minha_config':
    $uid = requireOwner();
    $s   = $pdo->prepare("SELECT * FROM vitrine_config WHERE user_id = ? LIMIT 1");
    $s->execute([$uid]);
    $cfg = $s->fetch(PDO::FETCH_ASSOC) ?: [];

    $us = $pdo->prepare("SELECT vitrine_slug, vitrine_ativa FROM users WHERE id = ? LIMIT 1");
    $us->execute([$uid]);
    $udata = $us->fetch(PDO::FETCH_ASSOC);

    apiok([
        'config' => $cfg,
        'slug'   => $udata['vitrine_slug'] ?? '',
        'ativa'  => (bool)($udata['vitrine_ativa'] ?? 1),
    ]);
    break;

/* ── POST: salvar config da vitrine (autenticado) ────────────── */
case 'salvar_config':
    $uid = requireOwner();

    $experienceSchemaReady = uplar_vitrine_experience_schema_disponivel($pdo);
    $cfgAtualSql = 'SELECT banner_path, logo_path, valor_frete';
    if ($experienceSchemaReady) {
        $cfgAtualSql .= ', experiencia_json';
    }
    $cfgAtualSql .= ' FROM vitrine_config WHERE user_id = ? LIMIT 1';
    $cfgAtualStmt = $pdo->prepare($cfgAtualSql);
    $cfgAtualStmt->execute([$uid]);
    $cfgAtual = $cfgAtualStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $nome_loja   = substr(trim($_POST['nome_loja']    ?? ''), 0, 120);
    $descricao   = substr(trim($_POST['descricao']    ?? ''), 0, 500);
    $whatsapp    = preg_replace('/\D/', '', $_POST['whatsapp'] ?? '');
    $hor_ret     = substr(trim($_POST['horario_ret']  ?? ''), 0, 80);
    $hor_ent     = substr(trim($_POST['horario_ent']  ?? ''), 0, 80);
    $endereco    = substr(trim($_POST['endereco']     ?? ''), 0, 255);
    $cidade_uf   = substr(trim($_POST['cidade_uf']   ?? ''), 0, 80);
    $cor         = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['cor_primaria'] ?? '')
                    ? $_POST['cor_primaria'] : '#3b6ff5';
    $mostrar_est = !empty($_POST['mostrar_estoque']) ? 1 : 0;
    $vitrine_on  = !empty($_POST['vitrine_ativa'])   ? 1 : 0;
    $valor_frete = max(0, (float)($cfgAtual['valor_frete'] ?? 0));
    if (array_key_exists('valor_frete', $_POST)) {
        if (!is_string($_POST['valor_frete']) || trim($_POST['valor_frete']) === '') apierr('Informe uma taxa de entrega válida.', 422);
        try { $valor_frete = uplar_complementos_cents($_POST['valor_frete']) / 100; }
        catch (InvalidArgumentException $e) { apierr('Informe uma taxa de entrega válida, com até duas casas decimais.', 422); }
    }
    $modoEnviado = array_key_exists('modo_experiencia', $_POST);
    $modoExperiencia = is_string($_POST['modo_experiencia'] ?? null) ? $_POST['modo_experiencia'] : '';
    $experienceOptionsPosted = array_key_exists('experiencia_opcoes', $_POST);

    if ($modoEnviado && !in_array($modoExperiencia, ['padrao', 'loja', 'cardapio'], true)) {
        apierr('Modo de experiência inválido.', 422);
    }
    if (!$experienceSchemaReady && $modoEnviado && $modoExperiencia !== 'padrao') {
        apierr('A experiência selecionada exige a atualização da configuração da vitrine.', 409);
    }
    $experienciaJson = null;
    if ($experienceSchemaReady) {
        try {
            $experienceOptions = null;
            if ($experienceOptionsPosted) {
                $experienceOptions = json_decode((string)$_POST['experiencia_opcoes'], true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($experienceOptions)) apierr('As opções da experiência são inválidas.', 422);
            }
            if ($modoEnviado || $experienceOptionsPosted) {
                $mergedExperience = uplar_vitrine_experience_merge($cfgAtual['experiencia_json'] ?? null, $experienceOptions ?? []);
                if (is_string($mergedExperience)) {
                    $experienciaJson = $mergedExperience;
                } else {
                    $experienciaJson = json_encode($mergedExperience, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                }
                if ($modoEnviado) $experienciaJson = uplar_vitrine_experience_json_com_modo($experienciaJson, $modoExperiencia);
            } else {
                $experienciaJson = ($cfgAtual['experiencia_json'] ?? null) ?: uplar_vitrine_experience_json_com_modo(null, 'padrao');
            }
            if ($experienceOptionsPosted) {
                $savedPreferences = uplar_vitrine_experience_preferences($experienciaJson);
                $assignedIds = [];
                foreach (($savedPreferences['categorias'] ?? []) as $category) {
                    foreach (($category['produto_ids'] ?? []) as $productId) $assignedIds[] = (int)$productId;
                }
                if ($assignedIds) {
                    $assignedIds = array_values(array_unique($assignedIds));
                    $placeholders = implode(',', array_fill(0, count($assignedIds), '?'));
                    $ownership = $pdo->prepare("SELECT COUNT(DISTINCT id) FROM produtos WHERE user_id = ? AND id IN ({$placeholders})");
                    $ownership->execute(array_merge([$uid], $assignedIds));
                    if ((int)$ownership->fetchColumn() !== count($assignedIds)) {
                        apierr('Uma ou mais categorias incluem produtos que não pertencem à sua loja.', 422);
                    }
                }
            }
        } catch (JsonException $e) {
            apierr('Não foi possível salvar o modo de experiência.', 422);
        } catch (InvalidArgumentException $e) {
            apierr($e->getMessage() ?: 'As opções da experiência são inválidas.', 422);
        }
    } elseif ($experienceOptionsPosted) {
        apierr('As opções da experiência exigem a atualização da configuração da vitrine.', 409);
    }

    $slug = strtolower(trim($_POST['slug'] ?? ''));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', trim($slug, '-'));

    if ($slug === '')      apierr('Endereço da vitrine inválido');
    if ($nome_loja === '') apierr('Nome da loja é obrigatório');

    $ck = $pdo->prepare("SELECT id FROM users WHERE vitrine_slug = ? AND id != ? LIMIT 1");
    $ck->execute([$slug, $uid]);
    if ($ck->fetch()) apierr('Este endereço já está em uso, escolha outro');

    $bannerPath = $cfgAtual['banner_path'] ?? null;
    $logoPath   = $cfgAtual['logo_path'] ?? null;
    $oldBannerPath = $bannerPath;
    $oldLogoPath = $logoPath;
    $newPaths = [];

    try {
        if (!empty($_FILES['banner']['name'])) {
            $bannerPath = uploadVitrineImagem(
                $_FILES['banner'],
                'banner',
                5 * 1024 * 1024,
                1500,
                500,
                1200,
                400
            );
            $newPaths[] = $bannerPath;
        }

        if (!empty($_FILES['logo']['name'])) {
            $logoPath = uploadVitrineImagem(
                $_FILES['logo'],
                'perfil',
                3 * 1024 * 1024,
                600,
                600,
                500,
                500
            );
            $newPaths[] = $logoPath;
        }

        $pdo->beginTransaction();
        $pdo->prepare("UPDATE users SET vitrine_slug = ?, vitrine_ativa = ? WHERE id = ?")
            ->execute([$slug, $vitrine_on, $uid]);

        $configColumns = 'user_id, nome_loja, descricao, banner_path, logo_path, whatsapp, horario_ret, horario_ent,
                 endereco, cidade_uf, cor_primaria, mostrar_estoque, valor_frete';
        $configValues = '?,?,?,?,?,?,?,?,?,?,?,?,?';
        $configUpdates = "nome_loja       = VALUES(nome_loja),
                descricao       = VALUES(descricao),
                banner_path     = VALUES(banner_path),
                logo_path       = VALUES(logo_path),
                whatsapp        = VALUES(whatsapp),
                horario_ret     = VALUES(horario_ret),
                horario_ent     = VALUES(horario_ent),
                endereco        = VALUES(endereco),
                cidade_uf       = VALUES(cidade_uf),
                cor_primaria    = VALUES(cor_primaria),
                mostrar_estoque = VALUES(mostrar_estoque),
                valor_frete     = VALUES(valor_frete)";
        $configParams = [
            $uid, $nome_loja, $descricao ?: null, $bannerPath, $logoPath, $whatsapp ?: null,
            $hor_ret ?: null, $hor_ent ?: null,
            $endereco ?: null, $cidade_uf ?: null,
            $cor, $mostrar_est, $valor_frete,
        ];
        if ($experienceSchemaReady) {
            $configColumns .= ', experiencia_json';
            $configValues .= ',?';
            $configUpdates .= ', experiencia_json = VALUES(experiencia_json)';
            $configParams[] = $experienciaJson;
        }
        $pdo->prepare("INSERT INTO vitrine_config ({$configColumns}) VALUES ({$configValues}) ON DUPLICATE KEY UPDATE {$configUpdates}")
            ->execute($configParams);
        $pdo->commit();
    } catch (VitrineImageException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($newPaths as $newPath) apagarVitrineImagem($newPath);
        apierr($e->getMessage(), 422);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($newPaths as $newPath) apagarVitrineImagem($newPath);
        error_log('Erro salvar_config da vitrine: ' . $e->getMessage());
        apierr('Não foi possível salvar as configurações.', 500);
    }

    if ($bannerPath !== $oldBannerPath) apagarVitrineImagem($oldBannerPath);
    if ($logoPath !== $oldLogoPath) apagarVitrineImagem($oldLogoPath);

    // URL pública com slug direto no domínio canônico da vitrine.
    $url_publica = "https://uplar.cloud/$slug";

    apiok([
        'slug' => $slug,
        'url' => $url_publica,
        'banner_path' => $bannerPath,
        'logo_path' => $logoPath,
    ]);
    break;

/* ── GET: pedidos da minha vitrine (autenticado) ─────────────── */
case 'listar_pedidos':
    $uid    = requireOwner();
    $status = $_GET['status'] ?? 'todos';
    $where  = in_array($status, ['pendente','confirmado','cancelado'], true)
              ? "AND status = ?" : '';

    $sql = "SELECT id, cliente_nome, cliente_tel, tipo_entrega, total, valor_frete,
                   status, DATE_FORMAT(criado_em,'%d/%m/%Y %H:%i') AS data_fmt,
                   itens_json, endereco_ent
            FROM vitrine_pedidos
            WHERE user_id = ? $where
            ORDER BY criado_em DESC
            LIMIT 100";

    $params = [$uid];
    if ($where) $params[] = $status;

    $s = $pdo->prepare($sql);
    $s->execute($params);
    $pedidos = $s->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pedidos as &$p) {
        $p['itens_json']  = json_decode($p['itens_json'], true) ?? [];
        $p['total']       = (float)$p['total'];
        $p['valor_frete'] = (float)($p['valor_frete'] ?? 0);
        $p['id']          = (int)$p['id'];
    }
    unset($p);

    apiok(['pedidos' => $pedidos]);
    break;

/* ── POST: alterar status de um pedido (autenticado) ─────────── */
case 'pedido_status':
    $uid    = requireOwner();
    $pid    = (int)($_POST['pedido_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if ($pid < 1) apierr('Pedido inválido');
    if (!in_array($status, ['pendente','confirmado','cancelado'], true)) apierr('Status inválido');
    if (in_array($status, ['confirmado', 'cancelado'], true)) requireOrderPasscode($pdo, $uid);

    $stmt_ped = $pdo->prepare("SELECT * FROM vitrine_pedidos WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt_ped->execute([$pid, $uid]);
    $pedido = $stmt_ped->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) apierr('Pedido não encontrado', 404);

    $status_anterior = $pedido['status'];
    $origem_pedido = commercial_origin_normalize($pedido['origem'] ?? null);

    $pdo->prepare("UPDATE vitrine_pedidos SET status = ? WHERE id = ? AND user_id = ?")
        ->execute([$status, $pid, $uid]);

    $warning = null;
    if ($status === 'confirmado' && $status_anterior !== 'confirmado') {

        $check_dup = $pdo->prepare("SELECT COUNT(*) FROM vendas WHERE pedido_vitrine_id = ?");
        $check_dup->execute([$pid]);

        if ((int)$check_dup->fetchColumn() === 0) {
            $itens = json_decode($pedido['itens_json'], true) ?? [];

            if (!empty($itens)) {
                try {
                    $pdo->beginTransaction();

                    foreach ($itens as $item) {
                        $produto_id = (int)($item['produto_id'] ?? 0);
                        $quantidade = (int)($item['quantidade'] ?? 0);
                        $preco      = (float)($item['preco']    ?? 0);
                        $tamanho    = isset($item['tamanho']) && $item['tamanho'] !== null && $item['tamanho'] !== '' ? product_sizes_normalize($item['tamanho']) : null;

                        if ($produto_id < 1 || $quantidade < 1 || $preco <= 0) continue;

                        $stmt_prod = $pdo->prepare("SELECT id, quantidade, preco_custo, usa_tamanhos FROM produtos WHERE id = ? AND user_id = ? FOR UPDATE");
                        $stmt_prod->execute([$produto_id, $uid]);
                        $produto = $stmt_prod->fetch(PDO::FETCH_ASSOC);
                        if (!$produto) continue;
                        if ((int)$produto['usa_tamanhos'] && $tamanho === null) continue;
                        if (!(int)$produto['usa_tamanhos'] && $tamanho !== null) continue;
                        if ($tamanho !== null) {
                            $deduct=$pdo->prepare('UPDATE produto_tamanhos SET quantidade=quantidade-? WHERE produto_id=? AND tamanho=? AND ativo=1 AND quantidade>=?');
                            $deduct->execute([$quantidade,$produto_id,$tamanho,$quantidade]);
                            if($deduct->rowCount()!==1) continue;
                            product_sizes_sync_total($pdo,$produto_id,$uid);
                        } elseif ((int)$produto['quantidade'] < $quantidade) continue;

                        $obs_venda = "Pedido vitrine #$pid"
                            . ($pedido['cliente_nome'] ? " — {$pedido['cliente_nome']}" : '')
                            . ($pedido['tipo_entrega'] === 'entrega' ? ' (entrega)' : ' (retirada)');
                        $customization = is_array($item['personalizacao'] ?? null) ? $item['personalizacao'] : null;
                        $baseCostCents = is_numeric($customization['custo_base_centavos'] ?? null)
                            ? (int)$customization['custo_base_centavos']
                            : (int)round((float)$produto['preco_custo'] * 100);
                        $additionalCostCents = is_numeric($customization['custo_adicional_centavos'] ?? null)
                            ? (int)$customization['custo_adicional_centavos']
                            : 0;
                        $costComplete = $baseCostCents > 0 && (!$customization || !empty($customization['custos_completos']));
                        $unitCost = ($baseCostCents + $additionalCostCents) / 100;
                        if ($complementsReady) {
                            $pdo->prepare("INSERT INTO vendas
                                (user_id, produto_id, tamanho, vendedor_id, origem, quantidade, preco_unitario, custo_unitario, observacao, pedido_vitrine_id, personalizacao_json, custo_completo)
                                VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)")
                                ->execute([$uid, $produto_id, $tamanho, $origem_pedido, $quantidade, $preco, $unitCost, $obs_venda, $pid,
                                    $customization ? json_encode($customization, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, $costComplete ? 1 : 0]);
                        } else {
                            $pdo->prepare("INSERT INTO vendas
                                (user_id, produto_id, tamanho, vendedor_id, origem, quantidade, preco_unitario, custo_unitario, observacao, pedido_vitrine_id)
                                VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)")
                                ->execute([$uid, $produto_id, $tamanho, $origem_pedido, $quantidade, $preco, $unitCost, $obs_venda, $pid]);
                        }

                        if ($tamanho === null) $pdo->prepare("
                            UPDATE produtos
                               SET quantidade = GREATEST(quantidade - ?, 0)
                             WHERE id = ? AND user_id = ?
                        ")->execute([$quantidade, $produto_id, $uid]);
                    }

                    $pdo->commit();

                } catch (PDOException $e) {
                    $pdo->rollBack();
                    error_log("Erro ao declarar vendas do pedido vitrine #$pid (user $uid): " . $e->getMessage());
                    $warning = 'Status atualizado, mas houve erro ao registrar vendas.';
                }
            }
        }

        syncConfirmedShippingExpense($pdo, $pedido, $uid);
    }

    apiok(['pedido_confirmado_agora' => $status === 'confirmado' && $status_anterior !== 'confirmado'] + ($warning ? ['aviso' => $warning] : []));
    break;

/* ── POST (autenticado): salvar frete de um pedido de entrega ── */
case 'salvar_frete':
    $uid = requireOwner();
    if (!verify_csrf_token()) apierr('CSRF inválido', 403);

    $pid         = (int)($_POST['pedido_id']   ?? 0);
    $valor_frete = max(0, (float)($_POST['valor_frete'] ?? 0));

    if ($pid < 1) apierr('Pedido inválido');

    // Busca pedido (deve ser do dono e do tipo entrega)
    $st = $pdo->prepare("SELECT * FROM vitrine_pedidos WHERE id = ? AND user_id = ? AND tipo_entrega = 'entrega' LIMIT 1");
    $st->execute([$pid, $uid]);
    $pedido = $st->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) apierr('Pedido não encontrado ou não é entrega', 404);

    $total_itens = (float)$pedido['total'] - (float)($pedido['valor_frete'] ?? 0);
    $total_novo  = round($total_itens + $valor_frete, 2);

    // Atualiza pedido
    $pdo->prepare("UPDATE vitrine_pedidos SET valor_frete = ?, total = ? WHERE id = ? AND user_id = ?")
        ->execute([$valor_frete, $total_novo, $pid, $uid]);

    $pedido['valor_frete'] = $valor_frete;
    $despesa_criada = ($pedido['status'] ?? '') === 'confirmado'
        ? syncConfirmedShippingExpense($pdo, $pedido, $uid)
        : false;

    apiok([
        'total_novo'      => $total_novo,
        'valor_frete'     => $valor_frete,
        'despesa_criada'  => $despesa_criada,
    ]);
    break;

default:
    apierr('Ação inválida', 404);
}
