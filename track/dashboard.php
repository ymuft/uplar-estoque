<?php

require __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    uplar_track_json(['ok' => false, 'error' => 'Não autenticado'], 401);
}

$meId = (int)$_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$meId]);
$role = $stmt->fetchColumn();

if ($role !== 'admin') {
    uplar_track_json(['ok' => false, 'error' => 'Acesso negado'], 403);
}

$days = isset($_GET['days']) ? (int)$_GET['days'] : 7;

try {
    $summary = uplar_tracking_dashboard_summary($pdo, $days);
    uplar_track_json(['ok' => true, 'summary' => $summary]);
} catch (Throwable $e) {
    error_log('track/dashboard.php: ' . $e->getMessage());
    uplar_track_json(['ok' => false, 'error' => 'Falha ao carregar dashboard'], 500);
}
