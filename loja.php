<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/subscription.php';
require_once __DIR__ . '/site_pro_helpers.php';

$slug = trim((string)($_GET['loja'] ?? ''));
if ($slug === '' || !preg_match('/^[a-zA-Z0-9_-]{3,80}$/', $slug)) {
    http_response_code(404);
    exit('Loja não encontrada.');
}

$pdo = get_pdo();
$ownerStmt = $pdo->prepare('SELECT id FROM users WHERE vitrine_slug = ? LIMIT 1');
$ownerStmt->execute([$slug]);
$ownerId = (int)$ownerStmt->fetchColumn();

if ($ownerId > 0) {
    try {
        $siteConfig = site_pro_config($pdo, $ownerId, ['slug_site' => $slug]);
        if ($siteConfig['canal_publico'] === 'site_pro' && has_pro_access($ownerId, $pdo)) {
            if (!$siteConfig['ativo']) {
                http_response_code(404);
                exit('O Site PRO desta loja está temporariamente indisponível.');
            }
            require __DIR__ . '/site.php';
            exit;
        }
    } catch (Throwable $e) {
        error_log('[public store resolver] ' . $e->getMessage());
    }
}

require __DIR__ . '/vitrine.php';
