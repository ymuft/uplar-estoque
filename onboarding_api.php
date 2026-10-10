<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require 'db.php';
require 'helpers.php';
require 'onboarding.php';
send_security_headers();

function onboarding_json(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId < 1) onboarding_json(['ok'=>false,'error'=>'Sessão inválida.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') onboarding_json(['ok'=>false,'error'=>'Método inválido.'], 405);
if (!verify_csrf_token()) onboarding_json(['ok'=>false,'error'=>'Token CSRF inválido.'], 403);

$pdo = get_pdo();
onboarding_ensure_schema($pdo);
$pdo->prepare("INSERT IGNORE INTO onboarding_state (user_id) VALUES (?)")->execute([$userId]);
$action = (string)($_POST['action'] ?? '');

if ($action === 'collapse') {
    $pdo->prepare("UPDATE onboarding_state SET collapsed=1 WHERE user_id=? AND status='in_progress'")->execute([$userId]);
} elseif ($action === 'expand') {
    $pdo->prepare("UPDATE onboarding_state SET collapsed=0 WHERE user_id=? AND status='in_progress'")->execute([$userId]);
} elseif ($action === 'dismiss') {
    $pdo->prepare("UPDATE onboarding_state SET status='dismissed', collapsed=1 WHERE user_id=?")->execute([$userId]);
} elseif ($action === 'share') {
    $pdo->prepare("UPDATE onboarding_state SET shared_at=COALESCE(shared_at,NOW()) WHERE user_id=?")->execute([$userId]);
} elseif ($action === 'celebration_seen') {
    $pdo->prepare("UPDATE onboarding_state SET celebration_seen_at=COALESCE(celebration_seen_at,NOW()) WHERE user_id=? AND status='completed'")->execute([$userId]);
} elseif ($action === 'tour_seen') {
    $pdo->prepare("UPDATE onboarding_state SET tour_seen_at=COALESCE(tour_seen_at,NOW()) WHERE user_id=?")->execute([$userId]);
} else {
    onboarding_json(['ok'=>false,'error'=>'Ação inválida.'], 422);
}

onboarding_json(['ok'=>true]);
