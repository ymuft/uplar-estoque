<?php
/** Configuração assistida do perfil comercial. Nunca executa DDL. */
declare(strict_types=1);
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/includes/business_profile_runtime.php';
send_security_headers();
header('Content-Type: application/json; charset=utf-8');

function uplar_business_api_reply(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!is_fully_authenticated()) uplar_business_api_reply(['ok'=>false,'error'=>'Sessão inválida.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') uplar_business_api_reply(['ok'=>false,'error'=>'Método inválido.'], 405);
if (!verify_csrf_token()) uplar_business_api_reply(['ok'=>false,'error'=>'Token de segurança inválido.'], 403);
$userId = (int)$_SESSION['user_id'];
$pdo = get_pdo();
if (!uplar_business_profile_schema_available($pdo)) {
    uplar_business_api_reply(['ok'=>false,'error'=>'A configuração de perfil aguarda atualização de banco autorizada.'], 409);
}
$action = (string)($_POST['action'] ?? '');
$status = match ($action) {
    'draft' => 'em_andamento',
    'complete' => 'concluida',
    'dismiss' => 'dispensada',
    default => null,
};
if ($status === null) uplar_business_api_reply(['ok'=>false,'error'=>'Ação inválida.'], 422);
$segment = $_POST['segmento'] ?? null;
if (!is_string($segment) && $segment !== null) uplar_business_api_reply(['ok'=>false,'error'=>'Segmento inválido.'], 422);
$categories = [];
if (isset($_POST['categorias'])) {
    try { $categories = json_decode((string)$_POST['categorias'], true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { uplar_business_api_reply(['ok'=>false,'error'=>'Categorias inválidas.'], 422); }
}
try {
    $profile = uplar_business_profile_store($pdo, $userId, [
        'status_configuracao' => $status,
        'segmento' => $segment,
        'categorias_pendentes' => $categories,
        'categorias_definidas' => ($_POST['categorias_definidas'] ?? '') === '1',
        'aplicar_modo' => ($_POST['aplicar_modo'] ?? '') === '1',
        'entrega' => ($_POST['entrega'] ?? '') === '1',
        'retirada' => ($_POST['retirada'] ?? '') === '1',
    ]);
    uplar_business_api_reply(['ok'=>true,'perfil'=>$profile]);
} catch (InvalidArgumentException $e) {
    uplar_business_api_reply(['ok'=>false,'error'=>$e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('[perfil_negocio] '.$e->getMessage());
    uplar_business_api_reply(['ok'=>false,'error'=>'Não foi possível salvar o perfil. Confira as opções e tente novamente.'], 500);
}
