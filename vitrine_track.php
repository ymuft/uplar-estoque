<?php

session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/includes/vitrine_analytics.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function vitrine_track_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    vitrine_track_response(['ok' => false, 'error' => 'Método inválido'], 405);
}
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) {
    vitrine_track_response(['ok' => false, 'error' => 'Payload muito grande'], 413);
}
if (!vitrine_analytics_rate_limit('public-events', 120)) {
    vitrine_track_response(['ok' => false, 'error' => 'Muitas requisições'], 429);
}

$input = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($input)) {
    vitrine_track_response(['ok' => false, 'error' => 'JSON inválido'], 400);
}

$slug = strtolower(trim((string)($input['slug'] ?? '')));
$eventType = strtolower(trim((string)($input['event_type'] ?? '')));
$publicEvents = ['store_view', 'product_view', 'search', 'category_filter', 'add_to_cart', 'cart_view', 'checkout_start', 'whatsapp_click', 'page_load', 'scroll_depth', 'session_end'];
if (!preg_match('/^[a-z0-9-]{2,80}$/', $slug) || !in_array($eventType, $publicEvents, true)) {
    vitrine_track_response(['ok' => false, 'error' => 'Evento inválido'], 422);
}

try {
    $pdo = get_pdo();
    $ownerStmt = $pdo->prepare('SELECT id FROM users WHERE vitrine_slug = ? AND vitrine_ativa = 1 LIMIT 1');
    $ownerStmt->execute([$slug]);
    $ownerId = (int)$ownerStmt->fetchColumn();
    if ($ownerId < 1) {
        vitrine_track_response(['ok' => false, 'error' => 'Vitrine não encontrada'], 404);
    }

    $productId = isset($input['product_id']) ? (int)$input['product_id'] : null;
    if (in_array($eventType, ['product_view', 'add_to_cart'], true)) {
        $productStmt = $pdo->prepare('SELECT id FROM produtos WHERE id = ? AND user_id = ? AND vitrine_exposto = 1 LIMIT 1');
        $productStmt->execute([$productId, $ownerId]);
        if (!$productStmt->fetchColumn()) {
            vitrine_track_response(['ok' => false, 'error' => 'Produto inválido'], 422);
        }
    } else {
        $productId = null;
    }

    vitrine_analytics_record($pdo, $ownerId, $eventType, [
        'product_id' => $productId,
        'visitor_id' => $input['visitor_id'] ?? '',
        'session_id' => $input['session_id'] ?? '',
        'referrer' => $input['referrer'] ?? '',
        'value' => $input['value'] ?? null,
    ]);
    vitrine_track_response(['ok' => true], 202);
} catch (Throwable $e) {
    error_log('[vitrine_track] ' . $e->getMessage());
    vitrine_track_response(['ok' => false, 'error' => 'Falha ao registrar evento'], 500);
}
