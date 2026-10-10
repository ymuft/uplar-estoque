<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function passcode_redirect(string $error, int $retryAfter = 0): void {
    $query = 'error=' . urlencode($error);
    if ($retryAfter > 0) $query .= '&retry_after=' . $retryAfter;
    header('Location: passcode.php?' . $query);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token() || (!passcode_is_pending() && !passcode_setup_is_pending()) || empty($_SESSION['user_id'])) {
    force_logout();
    header('Location: login.php');
    exit;
}

$pdo = get_pdo();
$userId = (int)$_SESSION['user_id'];
$ip = get_client_ip();
$action = (string)($_POST['action'] ?? '');
$setupRequired = passcode_setup_is_pending();

$stmt = $pdo->prepare('SELECT passcode_hash, passcode_required FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user || ($setupRequired && empty($user['passcode_required'])) || (!$setupRequired && empty($user['passcode_hash']))) {
    force_logout();
    header('Location: login.php');
    exit;
}

if ($setupRequired) {
    $passcode = (string)($_POST['passcode'] ?? '');
    $confirm = (string)($_POST['passcode_confirm'] ?? '');
    if ($action !== 'setup' || !preg_match('/^\d{6}$/D', $passcode) || !hash_equals($passcode, $confirm)) {
        passcode_redirect('Informe e confirme um Pass Code com 6 números.');
    }
    $pdo->prepare('UPDATE users SET passcode_hash = ?, passcode_configured_at = NOW(), passcode_required = 0, auth_version = auth_version + 1 WHERE id = ?')
        ->execute([password_hash($passcode, PASSWORD_DEFAULT), $userId]);
    $pdo->prepare('DELETE FROM passcode_tentativas WHERE user_id = ?')->execute([$userId]);
    $_SESSION['auth_version'] = (int)($_SESSION['auth_version'] ?? 1) + 1;
    session_regenerate_id(true);
    $_SESSION['auth_state'] = 'fully_authenticated';
    unset($_SESSION['passcode_pending_since']);
    unset($_SESSION['csrf_token']); get_csrf_token();
    header('Location: estoque.php');
    exit;
}

if ($action !== 'verify') passcode_redirect('Requisição inválida.');

$attempt = $pdo->prepare('SELECT tentativas, GREATEST(0, TIMESTAMPDIFF(SECOND, ultima_tentativa, NOW())) AS elapsed FROM passcode_tentativas WHERE user_id = ? AND ip = ? LIMIT 1');
$attempt->execute([$userId, $ip]);
$rate = $attempt->fetch();
$maxAttempts = 5; $blockSeconds = 900;
if ($rate && (int)$rate['tentativas'] >= $maxAttempts && (int)$rate['elapsed'] < $blockSeconds) {
    passcode_redirect('Muitas tentativas. Aguarde antes de tentar novamente.', max(1, $blockSeconds - (int)$rate['elapsed']));
}
if ($rate && (int)$rate['elapsed'] >= $blockSeconds) {
    $pdo->prepare('DELETE FROM passcode_tentativas WHERE user_id = ? AND ip = ?')->execute([$userId, $ip]);
    $rate = null;
}

$passcode = (string)($_POST['passcode'] ?? '');
$valid = preg_match('/^\d{6}$/D', $passcode) && password_verify($passcode, $user['passcode_hash']);
if (!$valid) {
    $pdo->prepare('INSERT INTO passcode_tentativas (user_id, ip, tentativas, ultima_tentativa) VALUES (?, ?, 1, NOW()) ON DUPLICATE KEY UPDATE tentativas = tentativas + 1, ultima_tentativa = NOW()')->execute([$userId, $ip]);
    $count = (int)($rate['tentativas'] ?? 0) + 1;
    $retry = $count >= $maxAttempts ? $blockSeconds : 0;
    passcode_redirect($count >= $maxAttempts ? 'Muitas tentativas. Aguarde antes de tentar novamente.' : 'Pass Code incorreto. Tente novamente.', $retry);
}

$pdo->prepare('DELETE FROM passcode_tentativas WHERE user_id = ?')->execute([$userId]);
session_regenerate_id(true);
$_SESSION['auth_state'] = 'fully_authenticated';
unset($_SESSION['passcode_pending_since']);
unset($_SESSION['csrf_token']); get_csrf_token();
header('Location: estoque.php');
exit;
