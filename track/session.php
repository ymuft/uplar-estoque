<?php

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    uplar_track_json(['ok' => false, 'error' => 'Método inválido'], 405);
}

$input = uplar_track_request_body();
uplar_track_require_csrf($input);

if (!uplar_tracking_rate_limit_ok('track-session', 120, 60)) {
    uplar_track_json(['ok' => false, 'error' => 'Rate limit excedido'], 429);
}

try {
    $session = uplar_tracking_touch_session($pdo, $input, uplar_track_current_user_id());
    uplar_track_json([
        'ok'         => true,
        'session_id' => $session['session_uuid'],
        'device_id'  => $session['device_hash'],
    ]);
} catch (Throwable $e) {
    error_log('track/session.php: ' . $e->getMessage());
    uplar_track_json(['ok' => false, 'error' => 'Falha ao registrar sessão'], 500);
}
