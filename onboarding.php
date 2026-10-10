<?php
require_once __DIR__ . '/includes/business_profile_runtime.php';

function onboarding_ensure_schema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;

    $pdo->exec("CREATE TABLE IF NOT EXISTS onboarding_state (
        user_id INT NOT NULL PRIMARY KEY,
        status ENUM('in_progress','completed','dismissed') NOT NULL DEFAULT 'in_progress',
        collapsed TINYINT(1) NOT NULL DEFAULT 0,
        shared_at DATETIME NULL,
        completed_at DATETIME NULL,
        celebration_seen_at DATETIME NULL,
        tour_seen_at DATETIME NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_onboarding_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ready = true;
}

function onboarding_scalar(PDO $pdo, string $sql, array $params = []): int
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function get_onboarding_data(int $userId, PDO $pdo, bool $isAdmin = false): array
{
    onboarding_ensure_schema($pdo);

    $userStmt = $pdo->prepare("SELECT username, role, created_at, vitrine_slug, vitrine_ativa, whatsapp_number FROM users WHERE id = ? LIMIT 1");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stateStmt = $pdo->prepare("SELECT * FROM onboarding_state WHERE user_id = ? LIMIT 1");
    $stateStmt->execute([$userId]);
    $state = $stateStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $products = onboarding_scalar($pdo, "SELECT COUNT(*) FROM produtos WHERE user_id = ?", [$userId]);
    $sales = onboarding_scalar($pdo, "SELECT COUNT(*) FROM vendas WHERE user_id = ?", [$userId]);
    $clients = onboarding_scalar($pdo, "SELECT COUNT(*) FROM clientes WHERE user_id = ?", [$userId]);
    $orders = onboarding_scalar($pdo, "SELECT COUNT(*) FROM vitrine_pedidos WHERE user_id = ?", [$userId]);

    $storeStmt = $pdo->prepare("SELECT nome_loja, descricao, banner_path, logo_path, cor_primaria, whatsapp, endereco, cidade_uf FROM vitrine_config WHERE user_id = ? LIMIT 1");
    $storeStmt->execute([$userId]);
    $store = $storeStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $storeViews = 0;
    try {
        $storeViews = onboarding_scalar($pdo, "SELECT COUNT(*) FROM vitrine_analytics_events WHERE owner_user_id = ? AND event_type = 'store_view'", [$userId]);
    } catch (Throwable $e) {
        $storeViews = 0;
    }

    $username = trim((string)($user['username'] ?? ''));
    $storeName = trim((string)($store['nome_loja'] ?? ''));
    $storeWhatsapp = trim((string)($store['whatsapp'] ?? '')) ?: trim((string)($user['whatsapp_number'] ?? ''));
    $slug = trim((string)($user['vitrine_slug'] ?? ''));
    $customized = trim((string)($store['logo_path'] ?? '')) !== ''
        || trim((string)($store['banner_path'] ?? '')) !== ''
        || trim((string)($store['descricao'] ?? '')) !== ''
        || ($storeName !== '' && strcasecmp($storeName, $username) !== 0)
        || (!empty($store['cor_primaria']) && strtolower((string)$store['cor_primaria']) !== '#3b6ff5');
    $configured = $slug !== '' && $storeName !== '' && $storeWhatsapp !== '';
    $shared = !empty($state['shared_at']) || $storeViews > 0 || $orders > 0;
    $businessProfile = uplar_business_profile_read($pdo, $userId);
    $businessPreset = uplar_business_profile_get($businessProfile['segmento']);
    $isFood = ($businessPreset['familia'] ?? '') === 'alimentacao';

    $steps = [
        ['key'=>'account', 'title'=>'Conta criada', 'description'=>'Seu acesso à Uplar já está pronto.', 'done'=>true, 'href'=>null, 'action'=>null],
        ['key'=>'products', 'title'=>$isFood ? 'Cadastre seu primeiro item do cardápio' : 'Adicione seus primeiros produtos', 'description'=>$isFood ? 'Inclua pratos, bebidas e os adicionais necessários.' : 'Monte seu catálogo e organize o estoque.', 'done'=>$products > 0, 'href'=>'produtos.php', 'action'=>'Adicionar'],
        ['key'=>'store', 'title'=>'Personalize sua vitrine', 'description'=>'Adicione identidade, descrição ou imagens da loja.', 'done'=>$customized, 'href'=>'vitrine_config.php', 'action'=>'Personalizar'],
        ['key'=>'settings', 'title'=>'Configure as informações da loja', 'description'=>'Defina nome, endereço público e WhatsApp.', 'done'=>$configured, 'href'=>'vitrine_config.php', 'action'=>'Configurar'],
        ['key'=>'share', 'title'=>'Compartilhe sua loja', 'description'=>'Envie o link para seus primeiros clientes.', 'done'=>$shared, 'href'=>$slug !== '' ? '/' . rawurlencode($slug) : 'vitrine_config.php', 'action'=>$slug !== '' ? 'Compartilhar' : 'Criar link'],
    ];
    $completedSteps = count(array_filter($steps, fn(array $step): bool => (bool)$step['done']));
    $allComplete = $completedSteps === count($steps);

    $createdAt = isset($user['created_at']) ? strtotime((string)$user['created_at']) : false;
    $createdAfterLaunch = $createdAt !== false && $createdAt >= strtotime('2026-07-23 00:00:00');
    $operationallyEmpty = $products === 0 && $sales === 0 && $orders === 0;
    $eligible = !$isAdmin && ($state !== null || $createdAfterLaunch || $operationallyEmpty);

    if ($eligible && $state === null) {
        $pdo->prepare("INSERT IGNORE INTO onboarding_state (user_id) VALUES (?)")->execute([$userId]);
        $state = ['status'=>'in_progress','collapsed'=>0,'shared_at'=>null,'completed_at'=>null,'celebration_seen_at'=>null,'tour_seen_at'=>null];
    }
    if ($eligible && $allComplete && !in_array(($state['status'] ?? ''), ['completed','dismissed'], true)) {
        $pdo->prepare("UPDATE onboarding_state SET status='completed', completed_at=COALESCE(completed_at,NOW()), collapsed=0 WHERE user_id=?")->execute([$userId]);
        $state['status'] = 'completed';
        $state['completed_at'] = date('Y-m-d H:i:s');
        $state['collapsed'] = 0;
    }

    $status = (string)($state['status'] ?? 'in_progress');
    $showCelebration = $eligible && $status === 'completed' && empty($state['celebration_seen_at']);
    $visible = $eligible && $status !== 'dismissed' && ($status !== 'completed' || $showCelebration);
    $stage = $products === 0 ? 'new' : (($sales === 0 && $orders === 0) ? 'hybrid' : 'operational');

    return [
        'eligible'=>$eligible, 'visible'=>$visible, 'status'=>$status,
        'business_profile'=>$businessProfile, 'business_preset'=>$businessPreset,
        'collapsed'=>!empty($state['collapsed']), 'show_celebration'=>$showCelebration,
        'tour_seen'=>!empty($state['tour_seen_at']),
        'steps'=>$steps, 'completed_steps'=>$completedSteps, 'total_steps'=>count($steps),
        'progress'=>(int)round(($completedSteps / count($steps)) * 100), 'stage'=>$stage,
        'counts'=>['products'=>$products,'sales'=>$sales,'clients'=>$clients,'orders'=>$orders,'store_views'=>$storeViews],
        'store'=>[
            'name'=>$storeName !== '' ? $storeName : ($username !== '' ? $username : 'Minha loja'),
            'logo'=>(string)($store['logo_path'] ?? ''), 'banner'=>(string)($store['banner_path'] ?? ''),
            'slug'=>$slug, 'url'=>$slug !== '' ? '/' . rawurlencode($slug) : 'vitrine_config.php',
            'active'=>(int)($user['vitrine_ativa'] ?? 1) === 1,
        ],
    ];
}
