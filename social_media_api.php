<?php
declare(strict_types=1);
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function social_error(string $message, int $status = 400): never {
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_SESSION['user_id'])) social_error('Não autenticado', 401);
$pdo = get_pdo();
$stmt = $pdo->prepare('SELECT username, role FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$user || ($user['role'] ?? '') !== 'admin') social_error('Acesso negado', 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token()) social_error('CSRF inválido', 403);

$configFile = __DIR__ . '/.env.social.php';
if (!is_readable($configFile)) social_error('Integração social ainda não configurada', 503);
$config = require $configFile;
$secret = is_array($config) ? (string)($config['secret'] ?? '') : '';
$endpoint = is_array($config) ? (string)($config['endpoint'] ?? 'http://127.0.0.1:5680/internal/admin') : '';
if ($secret === '' || !str_starts_with($endpoint, 'http://127.0.0.1:')) social_error('Configuração social inválida', 503);

$allowed = ['list_content','list_interactions','update_content','approve','reject','regenerate','mark_handled'];
$action = (string)($_POST['action'] ?? '');
if (!in_array($action, $allowed, true)) social_error('Ação inválida');
$payload = ['action' => $action, 'reviewer' => (string)$user['username']];
foreach (['id','caption','scheduled_at','comment'] as $key) {
    if (isset($_POST[$key])) $payload[$key] = $_POST[$key];
}

$ch = curl_init($endpoint);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secret, 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
]);
$body = curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$error = curl_error($ch);
curl_close($ch);
if ($body === false || $status < 200 || $status >= 300) {
    error_log('Social service indisponível: status=' . $status . ' erro=' . $error);
    social_error('Serviço social temporariamente indisponível', 502);
}
$decoded = json_decode($body, true);
if (!is_array($decoded)) social_error('Resposta inválida do serviço social', 502);
if ($action === 'list_content' && isset($decoded['items']) && is_array($decoded['items'])) {
    foreach ($decoded['items'] as &$item) {
        if (!empty($item['media_url'])) {
            $key = basename((string)parse_url((string)$item['media_url'], PHP_URL_PATH));
            $item['media_url'] = 'social_media_asset.php?key=' . rawurlencode($key);
        }
    }
    unset($item);
}
echo json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
