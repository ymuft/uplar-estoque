<?php

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    uplar_track_json(['ok' => false, 'error' => 'Método inválido'], 405);
}

if (!uplar_tracking_rate_limit_ok('track-event', 60, 60)) {
    uplar_track_json(['ok' => false, 'error' => 'Rate limit excedido'], 429);
}

if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 131072) {
    uplar_track_json(['ok' => false, 'error' => 'Payload muito grande'], 413);
}

$input = uplar_track_request_body();
uplar_track_require_csrf($input);

try {
    if (isset($input['events'])) {
        $result = uplar_tracking_track_events($pdo, $input, uplar_track_current_user_id());
        uplar_track_json(['ok' => true] + $result);
    }
    $event = uplar_tracking_track_event($pdo, $input, uplar_track_current_user_id());
    uplar_track_json(['ok' => true, 'event_count' => 1] + $event);
} catch (InvalidArgumentException $e) {
    uplar_track_json(['ok' => false, 'error' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('track/event.php: ' . $e->getMessage());
    uplar_track_json(['ok' => false, 'error' => 'Falha ao registrar evento'], 500);
}
