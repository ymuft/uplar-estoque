<?php
declare(strict_types=1);

// Short-lived upload grant for uDrop. The PHP session remains on this host.
$allowedOrigins = ['https://uplar.cloud', 'https://www.uplar.cloud'];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Vary: Origin');

if (!in_array($origin, $allowedOrigins, true)) {
    http_response_code(403);
    echo json_encode(['error' => 'Origem não permitida.']);
    exit;
}

header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST, OPTIONS');
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

$authenticated = function_exists('is_fully_authenticated') && is_fully_authenticated();
// Compatibility with the current Estoque repository, whose session validator
// is is_session_valid() and whose login stores these server-side fields.
if (!$authenticated && function_exists('is_session_valid')) {
    $authenticated = isset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role'])
        && is_session_valid();
}
$clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $ipHeader) {
    if (!empty($_SERVER[$ipHeader])) {
        $candidate = trim(explode(',', (string)$_SERVER[$ipHeader])[0]);
        if (filter_var($candidate, FILTER_VALIDATE_IP)) { $clientIp = $candidate; break; }
    }
}
if (isset($_SESSION['_ip']) && !hash_equals((string)$_SESSION['_ip'], $clientIp)) $authenticated = false;
if (isset($_SESSION['_ua_hash']) && !hash_equals((string)$_SESSION['_ua_hash'], md5((string)($_SERVER['HTTP_USER_AGENT'] ?? '')))) $authenticated = false;
if (!$authenticated || !isset($_SESSION['user_id'], $_SESSION['username'])) {
    session_write_close();
    http_response_code(401);
    echo json_encode(['error' => 'Entre na sua conta Uplar para continuar.']);
    exit;
}

$secret = @file_get_contents(__DIR__ . '/.udrop_sso_secret');
$secret = is_string($secret) ? trim($secret) : '';
if (!is_string($secret) || strlen($secret) < 32) {
    session_write_close();
    http_response_code(503);
    echo json_encode(['error' => 'A autorização de arquivos grandes não está configurada.']);
    exit;
}

$requestBody = file_get_contents('php://input', false, null, 0, 4097);
if (!is_string($requestBody) || strlen($requestBody) > 4096) {
    session_write_close(); http_response_code(413);
    echo json_encode(['error' => 'Solicitação muito grande.']); exit;
}
$input = json_decode($requestBody, true);
$scope = is_array($input) && isset($input['scope']) ? $input['scope'] : 'udrop-upload';
if (!is_string($scope) || !in_array($scope, ['udrop-upload', 'udrop-session'], true)) {
    session_write_close(); http_response_code(400);
    echo json_encode(['error' => 'Escopo de autorização inválido.']); exit;
}
$now = time();
$payload = [
    'scope' => $scope,
    'sub' => (int) $_SESSION['user_id'],
    'username' => mb_substr((string)$_SESSION['username'], 0, 190),
    'role' => isset($_SESSION['role']) ? mb_substr((string)$_SESSION['role'], 0, 64) : null,
    'iat' => $now,
    'exp' => $now + ($scope === 'udrop-session' ? 180 : 300),
    'jti' => bin2hex(random_bytes(16)),
];
session_write_close();

$json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$encoded = rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
$signature = hash_hmac('sha256', $encoded, $secret, true);
$ticket = $encoded . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

echo json_encode(['ticket' => $ticket, 'expires_at' => $payload['exp']]);
