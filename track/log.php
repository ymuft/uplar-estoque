<?php

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    uplar_track_json(['ok' => false, 'error' => 'Método inválido'], 405);
}

$input = uplar_track_request_body();
uplar_track_require_csrf($input);

if (!uplar_tracking_rate_limit_ok('track-log', 120, 60)) {
    uplar_track_json(['ok' => false, 'error' => 'Rate limit excedido'], 429);
}

try {
    $logId = uplar_tracking_track_log($pdo, $input, uplar_track_current_user_id());
    uplar_track_json(['ok' => true, 'log_id' => $logId]);
} catch (Throwable $e) {
    error_log('track/log.php: ' . $e->getMessage());
    uplar_track_json(['ok' => false, 'error' => 'Falha ao registrar log'], 500);
}
