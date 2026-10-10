<?php

require_once dirname(__DIR__) . '/tracking.php';

try {
    if (function_exists('get_pdo')) {
        $trackingPdo = get_pdo();
        uplar_tracking_ensure_schema($trackingPdo);
        uplar_tracking_backend_monitor(
            $trackingPdo,
            isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
        );
    }
} catch (Throwable $e) {
    error_log('Tracking runtime indisponível: ' . $e->getMessage());
}
