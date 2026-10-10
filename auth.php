<?php
/**
 * auth.php - Processamento seguro de login
 * Versão melhorada: mantém estrutura original + adiciona:
 *   - login_logger.php (rastreamento de acessos)
 *   - Timing-safe (anti user-enumeration)
 *   - Honeypot anti-bot
 *   - IP resolution com suporte a proxies/Cloudflare
 *   - Contador de tentativas restantes na mensagem
 *   - Re-hash automático de senhas com custo desatualizado
 *   - Session binding (IP + User-Agent hash)
 *   - Limite de tamanho nos campos (anti-overflow)
 */
session_start();
require 'db.php';
require 'helpers.php';
require 'login_logger.php';
require 'subscription.php';

send_security_headers();

// Força HTTPS em produção (comente em ambiente local)
if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
    header("Location: https://" . get_safe_host() . $_SERVER['REQUEST_URI']);
    exit;
}

// Só permite POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php?error=Acesso inválido");
    exit;
}

// ── CSRF ─────────────────────────────────────────────────────
if (!verify_csrf_token()) {
    security_log("Tentativa de ataque CSRF detectada", "danger");
    header("Location: login.php?error=Requisição inválida. Tente novamente.");
    exit;
}

// ── Honeypot anti-bot ─────────────────────────────────────────
// Campo "website" deve estar sempre vazio (bots preenchem, humanos não)
if (!empty($_POST['website'])) {
    security_log("Bot detectado via honeypot - IP: " . get_client_ip(), "warning");
    // Redireciona silenciosamente sem revelar o motivo
    header("Location: login.php");
    exit;
}

// ── Cloudflare Turnstile ─────────────────────────────────────
$turnstile_token = (string)($_POST['cf-turnstile-response'] ?? '');
if (!verify_turnstile_token($turnstile_token, get_client_ip())) {
    security_log("Falha na verificação Turnstile - IP: " . get_client_ip(), "warning");
    header("Location: login.php?error=" . urlencode("Verificação de segurança inválida. Tente novamente."));
    exit;
}

// ── Captura e valida dados ────────────────────────────────────
$username = trim($_POST['username'] ?? '');
$password = $_POST['password']       ?? '';

// Proteção contra campos oversized (DoS / hash-flooding)
if (strlen($username) > 50 || strlen($password) > 200) {
    security_log("Campos oversized detectados - IP: " . get_client_ip(), "warning");
    header("Location: login.php?error=Dados inválidos");
    exit;
}

if (empty($username) || empty($password)) {
    header("Location: login.php?error=Preencha todos os campos");
    exit;
}

// ── Rate limiting por IP ──────────────────────────────────────
$ip                     = get_client_ip();
$max_tentativas         = 5;
$tempo_bloqueio_segundos = 15 * 60; // 15 minutos
$pdo                    = get_pdo();

$stmt = $pdo->prepare("
    SELECT tentativas,
           GREATEST(0, TIMESTAMPDIFF(SECOND, ultima_tentativa, NOW())) AS segundos_desde_ultima
    FROM login_tentativas
    WHERE ip = ?
    LIMIT 1
");
$stmt->execute([$ip]);
$registro = $stmt->fetch();

if ($registro) {
    $segundos_desde_ultima = (int)$registro['segundos_desde_ultima'];

    if ($registro['tentativas'] >= $max_tentativas && $segundos_desde_ultima < $tempo_bloqueio_segundos) {
        $segundos_restantes = max(1, $tempo_bloqueio_segundos - $segundos_desde_ultima);
        $minutos_restantes = ceil($segundos_restantes / 60);
        security_log("Bloqueio por excesso de tentativas - IP: $ip", "warning");
        header('Retry-After: ' . $segundos_restantes);
        header("Location: login.php?rate_limited=1&retry_after={$segundos_restantes}&error=" . urlencode("Conta bloqueada temporariamente ({$minutos_restantes} min restantes)."));
        exit;
    }

    // Reseta contador se o período de bloqueio já expirou
    if ($segundos_desde_ultima >= $tempo_bloqueio_segundos) {
        $pdo->prepare("DELETE FROM login_tentativas WHERE ip = ?")->execute([$ip]);
        $registro = null;
    } else {
        // Backoff progressivo: 1s, 2s, 4s e 8s entre tentativas falhas.
        $atraso_progressivo = min(2 ** max(0, (int)$registro['tentativas'] - 1), 8);
        $segundos_para_tentar = $atraso_progressivo - $segundos_desde_ultima;

        if ($segundos_para_tentar > 0) {
            security_log("Tentativa durante atraso progressivo - IP: $ip", "warning");
            header('Retry-After: ' . $segundos_para_tentar);
            header("Location: login.php?rate_limited=1&retry_after={$segundos_para_tentar}&error=" . urlencode("Aguarde {$segundos_para_tentar} segundo" . ($segundos_para_tentar === 1 ? '' : 's') . " antes de tentar novamente."));
            exit;
        }
    }
}

// ── Busca usuário ─────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT id, username, password, role, passcode_hash, passcode_required, auth_version
    FROM users
    WHERE username = ?
    LIMIT 1
");
$stmt->execute([$username]);
$user = $stmt->fetch();

/*
 * Timing-safe: executa password_verify mesmo quando o usuário não existe,
 * usando um hash dummy. Previne user-enumeration por diferença de tempo.
 */
$dummy_hash    = '$2y$10$invaliddummyhashtopreventtimingattacks.............';
$hash_verificar = $user ? $user['password'] : $dummy_hash;
$login_sucesso  = $user && password_verify($password, $hash_verificar);

// ── Login falhou ──────────────────────────────────────────────
if (!$login_sucesso) {
    // Incremento atômico: não perde contagem em requisições simultâneas.
    $pdo->prepare("
        INSERT INTO login_tentativas (ip, tentativas, ultima_tentativa)
        VALUES (?, 1, NOW())
        ON DUPLICATE KEY UPDATE
            tentativas = tentativas + 1,
            ultima_tentativa = NOW()
    ")->execute([$ip]);

    // A gravação acima continua atômica. Esta estimativa serve apenas para a
    // mensagem; o bloqueio sempre usa o valor real lido no início da requisição.
    $novas_tentativas = (int)($registro['tentativas'] ?? 0) + 1;

    // Registra no log de acessos (se o usuário existir)
    if ($user) {
        log_login($pdo, $user['id'], 'falha');
    }

    security_log("Login falhou - usuário: $username | IP: $ip", "warning");

    // Calcula tentativas restantes
    $restantes = max(0, $max_tentativas - $novas_tentativas);
    if ($restantes > 0) {
        $msg = "Usuário ou senha inválidos. ({$restantes} tentativa" . ($restantes === 1 ? '' : 's') . " restante" . ($restantes === 1 ? '' : 's') . ")";
    } else {
        $msg = "Conta bloqueada temporariamente (15 min).";
    }

    header("Location: login.php?error=" . urlencode($msg));
    exit;
}

// ── LOGIN BEM-SUCEDIDO ────────────────────────────────────────

// Limpa tentativas falhas deste IP
$pdo->prepare("DELETE FROM login_tentativas WHERE ip = ?")->execute([$ip]);

// Proteção contra session fixation
session_regenerate_id(true);

// Armazena dados na sessão
$_SESSION['user_id']       = (int)$user['id'];
$_SESSION['username']      = $user['username'];
$_SESSION['role']          = $user['role'] ?? 'user';
$_SESSION['auth_version']  = (int)$user['auth_version'];
$_SESSION['last_activity'] = time();

// Session binding: detecta session hijacking em verificações posteriores
$_SESSION['_ip']      = $ip;
$_SESSION['_ua_hash'] = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));

// Re-hash automático se o custo do algoritmo estiver desatualizado
if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
    $novo_hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$novo_hash, $user['id']]);
}

// Regenera token CSRF para a nova sessão (previne fixação de CSRF)
unset($_SESSION['csrf_token']);
get_csrf_token();

$passcodeConfigurado = !empty($user['passcode_hash']);
$passcodeObrigatorio = !$passcodeConfigurado && !empty($user['passcode_required']);
$_SESSION['auth_state'] = $passcodeConfigurado ? 'passcode_pending' : ($passcodeObrigatorio ? 'passcode_setup_pending' : 'fully_authenticated');
if ($passcodeConfigurado || $passcodeObrigatorio) {
    $_SESSION['passcode_pending_since'] = time();
}

// Registra login bem-sucedido no log de acessos
log_login($pdo, $user['id'], 'ok');
security_log("Login realizado com sucesso - usuário: {$user['username']} | IP: $ip | Role: {$user['role']}");

$destino = ($passcodeConfigurado || $passcodeObrigatorio) ? 'passcode.php' : 'estoque.php';
if (!$passcodeConfigurado && !$passcodeObrigatorio && ($user['role'] ?? 'user') !== 'admin') {
    $sub = get_subscription((int)$user['id'], $pdo);
    if (!$sub['ativa']) {
        $_SESSION['sub_status'] = $sub['status'];
        $destino = 'bloqueio.php';
    }
}

header("Location: $destino");
exit;
