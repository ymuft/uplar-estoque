<?php
/**
 * Perfil de negócio — contrato de persistência isolado.
 * Não altera schema, não cria categorias/produtos e não publica vitrines.
 */
declare(strict_types=1);
require_once __DIR__ . '/business_profiles.php';
require_once __DIR__ . '/vitrine_experience.php';

function uplar_business_profile_schema_available(PDO $pdo): bool
{
    try {
        $s = $pdo->query("SHOW COLUMNS FROM empresa_config LIKE 'perfil_negocio_json'");
        return (bool)$s->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

function uplar_business_profile_decode($raw): array
{
    if (!is_string($raw) || $raw === '') return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) return [];
    $allowed = ['em_andamento', 'concluida', 'dispensada'];
    if (!in_array($data['status_configuracao'] ?? null, $allowed, true)) return [];
    $segment = $data['segmento'] ?? null;
    if ($segment !== null && (!is_string($segment) || !uplar_business_profile_is_valid($segment))) return [];
    return $data;
}

function uplar_business_profile_read(PDO $pdo, int $userId): array
{
    $empty = ['ready' => false, 'segmento' => null, 'status_configuracao' => 'nao_selecionado',
        'categorias_pendentes' => [], 'modo_pendente' => null];
    if (!uplar_business_profile_schema_available($pdo)) return $empty;
    $empty['ready'] = true;
    $s = $pdo->prepare('SELECT perfil_negocio_json FROM empresa_config WHERE user_id = ? LIMIT 1');
    $s->execute([$userId]);
    $raw = $s->fetchColumn();
    $profile = uplar_business_profile_decode($raw);
    return array_replace($empty, $profile);
}

function uplar_business_profile_categories($value): array
{
    if (!is_array($value) || !array_is_list($value) || count($value) > 30) {
        throw new InvalidArgumentException('Informe até 30 categorias.');
    }
    $result = []; $seen = [];
    foreach ($value as $name) {
        if (!is_string($name)) throw new InvalidArgumentException('Nome de categoria inválido.');
        $name = trim(preg_replace('/\\s+/u', ' ', $name) ?? '');
        if ($name === '' || mb_strlen($name) > 80) throw new InvalidArgumentException('Cada categoria deve ter entre 1 e 80 caracteres.');
        $key = mb_strtolower($name);
        if (isset($seen[$key])) throw new InvalidArgumentException('Remova as categorias duplicadas.');
        $seen[$key] = true;
        $result[] = $name;
    }
    return $result;
}

/**
 * Guarda somente escolhas explícitas. A aplicação do modo da vitrine é
 * opt-in e nunca força INSERT de vitrine_config (pode ainda não existir).
 */
function uplar_business_profile_store(PDO $pdo, int $userId, array $input): array
{
    if (!uplar_business_profile_schema_available($pdo)) {
        throw new RuntimeException('Configuração de perfil indisponível até a migração de banco.');
    }
    $status = $input['status_configuracao'] ?? null;
    if (!in_array($status, ['em_andamento', 'concluida', 'dispensada'], true)) {
        throw new InvalidArgumentException('Estado de configuração inválido.');
    }
    $segment = $input['segmento'] ?? null;
    if ($status === 'dispensada') {
        $segment = null;
    } elseif (!is_string($segment) || !uplar_business_profile_is_valid($segment)) {
        throw new InvalidArgumentException('Escolha um segmento válido.');
    }
    $preset = $segment !== null ? uplar_business_profile_get($segment) : null;
    $categories = $status === 'concluida'
        ? uplar_business_profile_categories($input['categorias_pendentes'] ?? [])
        : [];
    $applyMode = $status === 'concluida' && ($input['aplicar_modo'] ?? false) === true;
    $applyDelivery = $status === 'concluida' && ($input['entrega'] ?? false) === true;
    $applyPickup = $status === 'concluida' && ($input['retirada'] ?? false) === true;
    if ($status === 'concluida' && $applyMode && !uplar_vitrine_experience_schema_disponivel($pdo)) {
        throw new RuntimeException('O modo de vitrine ainda não está disponível no banco.');
    }

    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT IGNORE INTO empresa_config (user_id) VALUES (?)')->execute([$userId]);
        $oldStmt = $pdo->prepare('SELECT perfil_negocio_json FROM empresa_config WHERE user_id = ? FOR UPDATE');
        $oldStmt->execute([$userId]);
        $old = uplar_business_profile_decode($oldStmt->fetchColumn());
        // Não perder categorias configuradas ao repetir a mesma submissão.
        $profile = [
            'schema_version' => 1,
            'segmento' => $segment,
            'versao_preset' => $preset['versao_preset'] ?? null,
            'status_configuracao' => $status,
            'categorias_pendentes' => $categories,
            'modo_pendente' => null,
            'preferencias_atendimento_pendentes' => [
                'entrega' => $applyDelivery, 'retirada' => $applyPickup,
            ],
            'configurado_em' => $status === 'concluida'
                ? ($old['configurado_em'] ?? date('c')) : null,
        ];
        if ($applyMode) {
            $mode = $preset['modo_sugerido'];
            $cfg = $pdo->prepare('SELECT experiencia_json FROM vitrine_config WHERE user_id = ? LIMIT 1 FOR UPDATE');
            $cfg->execute([$userId]);
            $existing = $cfg->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $json = uplar_vitrine_experience_json_com_modo($existing['experiencia_json'], $mode);
                $pdo->prepare('UPDATE vitrine_config SET experiencia_json = ? WHERE user_id = ?')
                    ->execute([$json, $userId]);
            } else {
                $profile['modo_pendente'] = $mode;
            }
        }
        $pdo->prepare('UPDATE empresa_config SET perfil_negocio_json = ? WHERE user_id = ?')
            ->execute([json_encode($profile, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), $userId]);
        if ($startedTransaction) $pdo->commit();
        return ['ready' => true] + $profile;
    } catch (Throwable $e) {
        if ($startedTransaction && $pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

/**
 * Redirecionamento opcional e controlado na implantação.
 * Definir UPLAR_BUSINESS_ONBOARDING_LAUNCH_AT=YYYY-MM-DD HH:MM:SS
 * e UPLAR_BUSINESS_ONBOARDING_AUTOSTART=1 SOMENTE após schema pronto.
 */
function uplar_business_profile_should_autostart(PDO $pdo, int $userId, bool $isAdmin): bool
{
    if ($isAdmin || getenv('UPLAR_BUSINESS_ONBOARDING_AUTOSTART') !== '1') return false;
    $launch = trim((string)(getenv('UPLAR_BUSINESS_ONBOARDING_LAUNCH_AT') ?: ''));
    $timestamp = $launch !== '' ? strtotime($launch) : false;
    if ($timestamp === false) return false;
    $profile = uplar_business_profile_read($pdo, $userId);
    if (!$profile['ready'] || $profile['status_configuracao'] !== 'nao_selecionado') return false;
    $stmt = $pdo->prepare('SELECT created_at FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $created = strtotime((string)($stmt->fetchColumn() ?: ''));
    return $created !== false && $created >= $timestamp;
}
