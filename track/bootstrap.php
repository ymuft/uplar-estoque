<?php

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

session_start();

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/helpers.php';
require_once dirname(__DIR__) . '/tracking.php';

if (function_exists('send_security_headers')) {
    send_security_headers();
}

function uplar_track_json(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function uplar_track_require_csrf(array $input): void
{
    $token = (string)($input['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['csrf_token'] ?? '');
    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        uplar_track_json(['ok' => false, 'error' => 'CSRF inválido'], 403);
    }
}

function uplar_track_request_body(): array
{
    $input = $_POST;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (empty($input) && stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $input = $decoded;
            }
        }
    }
    return is_array($input) ? $input : [];
}

function uplar_track_current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

try {
    $pdo = get_pdo();
    uplar_tracking_ensure_schema($pdo);
} catch (Throwable $e) {
    uplar_track_json(['ok' => false, 'error' => 'Falha ao iniciar tracking'], 500);
}
