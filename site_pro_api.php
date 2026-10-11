<?php
session_start();
require __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require __DIR__ . '/subscription.php';
require __DIR__ . '/site_pro_helpers.php';
require __DIR__ . '/site_pro_commerce_helpers.php';
require __DIR__ . '/includes/site_pro_analytics.php';
require_once __DIR__ . '/includes/product_sizes.php';
require_once __DIR__ . '/includes/product_complements.php';
require_once __DIR__ . '/includes/commercial_availability.php';
require_once __DIR__ . '/includes/vitrine_experience.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

$pdo = get_pdo();
product_sizes_ensure_schema($pdo);
$availabilitySchemaReady = uplar_commercial_availability_schema_ready($pdo);
$action = trim((string)($_GET['acao'] ?? $_POST['acao'] ?? ''));

function site_pro_api_output(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function site_pro_api_error(string $message, int $status = 400): void
{
    site_pro_api_output(['ok' => false, 'error' => $message], $status);
}

function site_pro_api_owner(PDO $pdo, string $slug): array
{
    if ($slug === '' || !preg_match('/^[a-zA-Z0-9_-]{3,80}$/', $slug)) {
        site_pro_api_error('Site inválido', 404);
    }

    site_pro_ensure_schema($pdo);
    $stmt = $pdo->prepare('SELECT id, username, whatsapp_number FROM users WHERE vitrine_slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $owner = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$owner || !has_pro_access((int)$owner['id'], $pdo)) {
        site_pro_api_error('Site indisponível', 404);
    }

    $config = site_pro_config($pdo, (int)$owner['id'], ['nome_loja' => $owner['username']]);
    if (!$config['ativo'] || $config['canal_publico'] !== 'site_pro') {
        site_pro_api_error('Site indisponível', 404);
    }

    return [$owner, $config];
}

function site_pro_cart_session(): string
{
    return 'site_pro:' . session_id();
}

function site_pro_complementos_input(array $snapshot): array
{
    $groups = [];
    foreach (is_array($snapshot['grupos'] ?? null) ? $snapshot['grupos'] : [] as $group) {
        if (!is_array($group)) continue;
        $options = [];
        foreach (is_array($group['opcoes'] ?? null) ? $group['opcoes'] : [] as $option) {
            if (!is_array($option)) continue;
            $options[] = ['opcao_id' => (int)($option['opcao_id'] ?? $option['id'] ?? 0), 'quantidade' => max(1, (int)($option['quantidade'] ?? 1))];
        }
        $groups[] = ['grupo_id' => (int)($group['grupo_id'] ?? $group['id'] ?? 0), 'opcoes' => $options];
    }
    return ['grupos' => $groups, 'observacao' => mb_substr(trim((string)($snapshot['observacao'] ?? '')), 0, 500)];
}

function site_pro_complementos_unit_cents(array $snapshot, float $basePrice): int
{
    return max(0, (int)($snapshot['preco_unitario_centavos'] ?? ((int)round($basePrice * 100) + (int)($snapshot['total_adicional_centavos'] ?? 0))));
}

function site_pro_order_schema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    $exists = $pdo->query("SHOW COLUMNS FROM vitrine_pedidos LIKE 'origem'")->fetchColumn();
    if (!$exists) {
        $pdo->exec("ALTER TABLE vitrine_pedidos ADD COLUMN origem VARCHAR(20) NOT NULL DEFAULT 'vitrine' AFTER user_id");
    }
    site_pro_commerce_ensure_schema($pdo);
    $ready = true;
}

$adminActions = [
    'admin_listar_produtos',
    'admin_toggle_produto',
    'admin_produtos_bulk',
    'admin_listar_cupons',
    'admin_salvar_cupom',
    'admin_toggle_cupom',
    'admin_excluir_cupom',
];
if (in_array($action, $adminActions, true)) {
    if (empty($_SESSION['user_id'])) {
        site_pro_api_error('Não autenticado', 401);
    }
    $adminUserId = (int)$_SESSION['user_id'];
    if (!has_pro_access($adminUserId, $pdo)) {
        site_pro_api_error('Recurso exclusivo do Plano PRO', 403);
    }
    site_pro_ensure_schema($pdo);
    site_pro_commerce_ensure_schema($pdo);

    if (!in_array($action, ['admin_listar_produtos', 'admin_listar_cupons'], true) && !verify_csrf_token()) {
        site_pro_api_error('Token de segurança inválido', 403);
    }

    if ($action === 'admin_listar_cupons') {
        $stmt = $pdo->prepare('SELECT id, codigo, desconto_percentual, ativo, criado_em
            FROM site_pro_cupons WHERE user_id = ? ORDER BY ativo DESC, criado_em DESC');
        $stmt->execute([$adminUserId]);
        $coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($coupons as &$coupon) {
            $coupon['id'] = (int)$coupon['id'];
            $coupon['desconto_percentual'] = (float)$coupon['desconto_percentual'];
            $coupon['ativo'] = (int)$coupon['ativo'] === 1;
        }
        unset($coupon);
        site_pro_api_output(['ok' => true, 'cupons' => $coupons]);
    }

    if ($action === 'admin_salvar_cupom') {
        $code = site_pro_normalize_coupon_code((string)($_POST['codigo'] ?? ''));
        $percentage = filter_var($_POST['desconto_percentual'] ?? null, FILTER_VALIDATE_FLOAT);
        if (!preg_match('/^[A-Z0-9_-]{3,32}$/', $code)) {
            site_pro_api_error('Use de 3 a 32 letras, números, hífen ou sublinhado.');
        }
        if ($percentage === false || $percentage <= 0 || $percentage > 100) {
            site_pro_api_error('Informe um desconto entre 0,01% e 100%.');
        }
        $pdo->prepare('INSERT INTO site_pro_cupons (user_id, codigo, desconto_percentual, ativo)
            VALUES (?, ?, ?, 1)
            ON DUPLICATE KEY UPDATE desconto_percentual = VALUES(desconto_percentual), ativo = 1')
            ->execute([$adminUserId, $code, round((float)$percentage, 2)]);
        site_pro_api_output(['ok' => true, 'message' => 'Cupom salvo e ativado.']);
    }

    if ($action === 'admin_toggle_cupom' || $action === 'admin_excluir_cupom') {
        $couponId = (int)($_POST['cupom_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT id, ativo FROM site_pro_cupons WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$couponId, $adminUserId]);
        $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$coupon) {
            site_pro_api_error('Cupom não encontrado.', 404);
        }
        if ($action === 'admin_excluir_cupom') {
            $pdo->prepare('DELETE FROM site_pro_cupons WHERE id = ? AND user_id = ?')->execute([$couponId, $adminUserId]);
            site_pro_api_output(['ok' => true]);
        }
        $active = (int)$coupon['ativo'] === 1 ? 0 : 1;
        $pdo->prepare('UPDATE site_pro_cupons SET ativo = ? WHERE id = ? AND user_id = ?')
            ->execute([$active, $couponId, $adminUserId]);
        site_pro_api_output(['ok' => true, 'ativo' => (bool)$active]);
    }

    if ($action === 'admin_listar_produtos') {
        $availabilitySelect = $availabilitySchemaReady ? uplar_commercial_availability_product_select($pdo, 'p') : '';
        $stmt = $pdo->prepare('
            SELECT p.id, p.nome, p.codigo, p.preco, p.quantidade, p.imagem, COALESCE(p.site_pro_exposto, 0) AS site_pro_exposto' . $availabilitySelect . '
            FROM produtos p WHERE p.user_id = ? ORDER BY p.nome ASC
        ');
        $stmt->execute([$adminUserId]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($products as &$product) {
            $product = uplar_commercial_availability_normalize_product($product);
            $product['id'] = (int)$product['id'];
            $product['preco'] = (float)$product['preco'];
            $product['quantidade'] = (int)$product['quantidade'];
            $product['site_pro_exposto'] = (bool)$product['site_pro_exposto'];
        }
        unset($product);
        site_pro_api_output(['ok' => true, 'produtos' => $products]);
    }

    if ($action === 'admin_toggle_produto') {
        $productId = (int)($_POST['produto_id'] ?? 0);
        $stmt = $pdo->prepare('SELECT id, site_pro_exposto FROM produtos WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$productId, $adminUserId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            site_pro_api_error('Produto não encontrado', 404);
        }
        $newValue = (int)$product['site_pro_exposto'] === 1 ? 0 : 1;
        $pdo->prepare('UPDATE produtos SET site_pro_exposto = ? WHERE id = ? AND user_id = ?')
            ->execute([$newValue, $productId, $adminUserId]);
        site_pro_api_output(['ok' => true, 'site_pro_exposto' => (bool)$newValue]);
    }

    $ids = json_decode((string)($_POST['ids'] ?? '[]'), true);
    $ids = is_array($ids) ? array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0))) : [];
    if (!$ids) {
        site_pro_api_error('Nenhum produto informado');
    }
    $newValue = !empty($_POST['site_pro_exposto']) ? 1 : 0;
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $params = $ids;
    $params[] = $adminUserId;
    $pdo->prepare("UPDATE produtos SET site_pro_exposto = ? WHERE id IN ({$placeholders}) AND user_id = ?")
        ->execute(array_merge([$newValue], $params));
    site_pro_api_output(['ok' => true, 'atualizados' => count($ids)]);
}

site_pro_order_schema($pdo);

$slug = trim((string)($_GET['slug'] ?? $_POST['slug'] ?? ''));
[$owner, $config] = site_pro_api_owner($pdo, $slug);
$ownerId = (int)$owner['id'];
$complementsReady = uplar_complementos_schema_disponivel($pdo);
$cartSession = site_pro_cart_session();
$couponSessionKey = 'site_pro_coupon_' . $ownerId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sentToken = (string)($_POST['csrf_token'] ?? '');
    $sessionToken = (string)($_SESSION['site_pro_public_csrf'] ?? '');
    if ($sessionToken === '' || $sentToken === '' || !hash_equals($sessionToken, $sentToken)) {
        site_pro_api_error('Sua sessão expirou. Atualize a página e tente novamente.', 403);
    }
}

switch ($action) {
    case 'produto_complementos':
        $productId = (int)($_GET['produto_id'] ?? 0);
        $availabilitySelect = $availabilitySchemaReady ? uplar_commercial_availability_product_select($pdo, 'p') : '';
        $productStmt = $pdo->prepare('SELECT p.id, p.quantidade, p.usa_tamanhos' . $availabilitySelect . ' FROM produtos p WHERE p.id = ? AND p.user_id = ? AND p.site_pro_exposto = 1 LIMIT 1');
        $productStmt->execute([$productId, $ownerId]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) site_pro_api_error('Produto indisponível', 404);
        $product = uplar_commercial_availability_normalize_product($product);
        $sizes = product_sizes_list($pdo, $productId, true);
        if ($product['usa_tamanhos'] && !$sizes) site_pro_api_error('Produto indisponível', 404);
        $catalogAvailability = uplar_commercial_availability_evaluate_product($product, $product['usa_tamanhos'] ? '__catalogo__' : null, (int)$product['quantidade'], (int)$product['quantidade']);
        if (!$catalogAvailability['available']) site_pro_api_error('Produto indisponível', 404);
        $groups = $complementsReady ? uplar_complementos_publicar_grupos(uplar_complementos_listar($pdo, $ownerId, $productId)) : [];
        site_pro_api_output([
            'ok' => true,
            'configurado' => (bool)$groups,
            'grupos' => $groups,
            'usa_tamanhos' => (bool)$product['usa_tamanhos'],
            'modo_disponibilidade' => $catalogAvailability['mode'],
            'limita_estoque' => $catalogAvailability['stock_limited'],
            'tamanhos' => array_map(static fn($size) => [
                'tamanho' => (string)$size['tamanho'],
                'quantidade' => (int)$size['quantidade'],
                'disponivel' => uplar_commercial_availability_evaluate_product($product, (string)$size['tamanho'], (int)$size['quantidade'], (int)$product['quantidade'])['available'],
            ], $sizes),
        ]);

    case 'carrinho_add':
        $productId = (int)($_POST['produto_id'] ?? 0);
        $quantity = max(1, min(99, (int)($_POST['quantidade'] ?? 1)));
        $availabilitySelect = $availabilitySchemaReady ? uplar_commercial_availability_product_select($pdo, 'p') : '';
        $productStmt = $pdo->prepare('SELECT p.id, p.quantidade, p.preco, p.usa_tamanhos' . $availabilitySelect . ' FROM produtos p WHERE p.id = ? AND p.user_id = ? AND p.site_pro_exposto = 1 LIMIT 1');
        $productStmt->execute([$productId, $ownerId]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            site_pro_api_error('Produto indisponível');
        }
        $product = uplar_commercial_availability_normalize_product($product);
        $sizeRaw = trim((string)($_POST['tamanho'] ?? ''));
        try {
            $size = $sizeRaw !== '' ? product_sizes_normalize($sizeRaw) : null;
        } catch (Throwable $e) {
            site_pro_api_error('Tamanho inválido.');
        }
        if ((int)$product['usa_tamanhos'] === 1 && $size === null) site_pro_api_error('Escolha um tamanho.');
        if ((int)$product['usa_tamanhos'] !== 1 && $size !== null) site_pro_api_error('Este produto não possui tamanhos.');
        $variantStock = null;
        if ($size !== null) {
            $sizeStmt = $pdo->prepare('SELECT quantidade FROM produto_tamanhos WHERE produto_id = ? AND tamanho = ? AND ativo = 1 LIMIT 1');
            $sizeStmt->execute([$productId, $size]);
            $sizeRow = $sizeStmt->fetch(PDO::FETCH_ASSOC);
            if (!$sizeRow) site_pro_api_error('Tamanho indisponível.');
            $variantStock = (int)$sizeRow['quantidade'];
        }
        $availability = uplar_commercial_availability_evaluate_product($product, $size, $variantStock, (int)$product['quantidade']);
        if (!$availability['available']) site_pro_api_error('Produto indisponível.');
        $stock = $availability['stock'];

        $submittedGroups = json_decode((string)($_POST['personalizacao'] ?? '[]'), true);
        if (!is_array($submittedGroups)) site_pro_api_error('Personalização inválida.');
        $itemNote = mb_substr(trim((string)($_POST['observacao_item'] ?? '')), 0, 500);
        if (!$complementsReady && ($submittedGroups || $itemNote !== '')) {
            site_pro_api_error('A personalização ainda não está disponível nesta loja.', 503);
        }
        $validated = $complementsReady
            ? uplar_complementos_validar($pdo, $ownerId, $productId, ['grupos' => $submittedGroups, 'observacao' => $itemNote])
            : null;
        $personalization = $validated['snapshot'] ?? null;
        $hasPersonalization = $personalization && (!empty($personalization['grupos']) || !empty($personalization['observacao']));
        $personalizationHash = $hasPersonalization ? uplar_complementos_hash($personalization) : '';
        if ($availability['stock_limited']) $quantity = min($quantity, (int)$stock);

        if ($complementsReady) {
            $existingStmt = $pdo->prepare('SELECT id, quantidade FROM vitrine_carrinho WHERE sessao = ? AND user_id = ? AND produto_id = ? AND tamanho <=> ? AND personalizacao_hash = ? LIMIT 1');
            $existingStmt->execute([$cartSession, $ownerId, $productId, $size, $personalizationHash]);
        } else {
            $existingStmt = $pdo->prepare('SELECT id, quantidade FROM vitrine_carrinho WHERE sessao = ? AND user_id = ? AND produto_id = ? AND tamanho <=> ? LIMIT 1');
            $existingStmt->execute([$cartSession, $ownerId, $productId, $size]);
        }
        $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $newQuantity = (int)$existing['quantidade'] + $quantity;
            if ($availability['stock_limited']) $newQuantity = min((int)$stock, $newQuantity);
            $pdo->prepare('UPDATE vitrine_carrinho SET quantidade = ? WHERE id = ?')->execute([$newQuantity, (int)$existing['id']]);
        } else {
            if ($complementsReady) {
                $pdo->prepare('INSERT INTO vitrine_carrinho (sessao, user_id, produto_id, tamanho, quantidade, personalizacao_hash, personalizacao_json) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$cartSession, $ownerId, $productId, $size, $quantity, $personalizationHash, $hasPersonalization ? json_encode($personalization, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null]);
            } else {
                $pdo->prepare('INSERT INTO vitrine_carrinho (sessao, user_id, produto_id, tamanho, quantidade) VALUES (?, ?, ?, ?, ?)')
                    ->execute([$cartSession, $ownerId, $productId, $size, $quantity]);
            }
        }
        site_pro_api_output(['ok' => true]);

    case 'carrinho_get':
        $pdo->prepare('DELETE FROM vitrine_carrinho WHERE adicionado < DATE_SUB(NOW(), INTERVAL 24 HOUR)')->execute();
        $personalizationSelect = $complementsReady ? 'vc.personalizacao_json' : 'NULL AS personalizacao_json';
        $availabilitySelect = $availabilitySchemaReady ? uplar_commercial_availability_product_select($pdo, 'p') : '';
        $itemsStmt = $pdo->prepare('
            SELECT vc.id, vc.quantidade AS qtd_carrinho, vc.tamanho, ' . $personalizationSelect . ',
                   p.id AS produto_id, p.nome, p.preco, p.imagem, p.quantidade AS estoque_produto, p.usa_tamanhos,
                   CASE WHEN vc.tamanho IS NULL THEN p.quantidade ELSE pt.quantidade END AS estoque' . $availabilitySelect . '
            FROM vitrine_carrinho vc
            JOIN produtos p ON p.id = vc.produto_id AND p.user_id = vc.user_id
            LEFT JOIN produto_tamanhos pt ON pt.produto_id = vc.produto_id AND pt.tamanho = vc.tamanho AND pt.ativo = 1
            WHERE vc.sessao = ? AND vc.user_id = ? AND p.site_pro_exposto = 1
            ORDER BY vc.adicionado DESC
        ');
        $itemsStmt->execute([$cartSession, $ownerId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        $totalCents = 0;
        $units = 0;
        $reviewRequired = false;
        $cartReservedStock = [];
        foreach ($items as &$item) {
            $item = uplar_commercial_availability_normalize_product($item);
            $item['id'] = (int)$item['id'];
            $item['produto_id'] = (int)$item['produto_id'];
            $item['qtd_carrinho'] = max(1, (int)$item['qtd_carrinho']);
            $variantStock = $item['tamanho'] === null ? null : (is_numeric($item['estoque']) ? (int)$item['estoque'] : null);
            $availability = uplar_commercial_availability_evaluate_product($item, $item['tamanho'], $variantStock, (int)$item['estoque_produto']);
            $stockStale = !$availability['available'] || !uplar_commercial_availability_reserve_quantity($cartReservedStock, (int)$item['produto_id'], $item['tamanho'] !== null ? (string)$item['tamanho'] : null, $item['qtd_carrinho'], $availability);
            $basePrice = (float)$item['preco'];
            $personalization = is_string($item['personalizacao_json'] ?? null) ? json_decode($item['personalizacao_json'], true) : null;
            $personalization = is_array($personalization) ? $personalization : null;
            $stale = $stockStale;
            if ($complementsReady) {
                try {
                    $fresh = uplar_complementos_validar($pdo, $ownerId, $item['produto_id'], site_pro_complementos_input($personalization ?? []));
                    if ($personalization && json_encode($personalization['grupos'] ?? [], JSON_UNESCAPED_UNICODE) !== json_encode($fresh['snapshot']['grupos'] ?? [], JSON_UNESCAPED_UNICODE)) $stale = true;
                    $appliedCents = $personalization
                        ? site_pro_complementos_unit_cents($personalization, $basePrice)
                        : (int)$fresh['preco_unitario_centavos'];
                    if ($personalization && $appliedCents !== (int)$fresh['preco_unitario_centavos']) $stale = true;
                } catch (Throwable $e) {
                    $stale = true;
                    $appliedCents = $personalization ? site_pro_complementos_unit_cents($personalization, $basePrice) : (int)round($basePrice * 100);
                }
            } else {
                $appliedCents = (int)round($basePrice * 100);
            }
            $item['personalizacao'] = $personalization ? uplar_complementos_publicar_snapshot($personalization) : null;
            unset($item['personalizacao_json']);
            $item['tamanho'] = $item['tamanho'] !== null ? (string)$item['tamanho'] : null;
            $item['preco'] = $appliedCents / 100;
            $item['estoque'] = $availability['stock_limited'] ? max(0, (int)$availability['stock']) : null;
            $item['modo_disponibilidade'] = $availability['mode'];
            $item['limita_estoque'] = $availability['stock_limited'];
            $item['revisao_requerida'] = $stale;
            $item['aviso'] = $stockStale
                ? 'O estoque disponível mudou. Ajuste a quantidade ou remova este item antes de finalizar.'
                : ($stale ? 'Uma opção ou preço mudou. Remova e configure novamente antes de finalizar.' : null);
            $reviewRequired = $reviewRequired || $stale;
            $totalCents += $appliedCents * $item['qtd_carrinho'];
            $units += $item['qtd_carrinho'];
        }
        unset($item);
        $coupon = null;
        $savedCouponCode = (string)($_SESSION[$couponSessionKey] ?? '');
        if ($savedCouponCode !== '') {
            $coupon = site_pro_find_coupon($pdo, $ownerId, $savedCouponCode);
            if (!$coupon) {
                unset($_SESSION[$couponSessionKey]);
            }
        }
        $pricing = site_pro_apply_coupon($totalCents / 100, $coupon);
        site_pro_api_output([
            'ok' => true,
            'itens' => $items,
            'subtotal' => $pricing['subtotal'],
            'desconto' => $pricing['desconto'],
            'total_produtos' => $pricing['total_produtos'],
            'total' => $pricing['total_produtos'],
            'count' => $units,
            'revisao_requerida' => $reviewRequired,
            'valor_frete' => (float)$config['valor_frete'],
            'cupom' => $coupon ? [
                'codigo' => $pricing['codigo'],
                'percentual' => $pricing['percentual'],
            ] : null,
        ]);

    case 'cupom_aplicar':
        $coupon = site_pro_find_coupon($pdo, $ownerId, (string)($_POST['codigo'] ?? ''));
        if (!$coupon) {
            site_pro_api_error('Cupom inválido ou indisponível.');
        }
        $_SESSION[$couponSessionKey] = $coupon['codigo'];
        site_pro_api_output([
            'ok' => true,
            'cupom' => [
                'codigo' => $coupon['codigo'],
                'percentual' => $coupon['desconto_percentual'],
            ],
        ]);

    case 'cupom_remover':
        unset($_SESSION[$couponSessionKey]);
        site_pro_api_output(['ok' => true]);

    case 'carrinho_qtd':
        $cartId = (int)($_POST['carrinho_id'] ?? 0);
        $quantity = max(1, min(99, (int)($_POST['quantidade'] ?? 1)));
        $availabilitySelect = $availabilitySchemaReady ? uplar_commercial_availability_product_select($pdo, 'p') : '';
        $checkStmt = $pdo->prepare('
            SELECT vc.id, vc.tamanho, p.quantidade AS estoque_produto, p.usa_tamanhos,
                   CASE WHEN vc.tamanho IS NULL THEN p.quantidade ELSE pt.quantidade END AS estoque' . $availabilitySelect . '
            FROM vitrine_carrinho vc
            JOIN produtos p ON p.id = vc.produto_id AND p.user_id = vc.user_id
            LEFT JOIN produto_tamanhos pt ON pt.produto_id = vc.produto_id AND pt.tamanho = vc.tamanho AND pt.ativo = 1
            WHERE vc.id = ? AND vc.sessao = ? AND vc.user_id = ? LIMIT 1
        ');
        $checkStmt->execute([$cartId, $cartSession, $ownerId]);
        $item = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if (!$item) {
            site_pro_api_error('Item não encontrado', 404);
        }
        $item = uplar_commercial_availability_normalize_product($item);
        $variantStock = $item['tamanho'] === null ? null : (is_numeric($item['estoque']) ? (int)$item['estoque'] : null);
        $availability = uplar_commercial_availability_evaluate_product($item, $item['tamanho'], $variantStock, (int)$item['estoque_produto']);
        if (!$availability['available']) site_pro_api_error('Produto indisponível.', 409);
        $newQuantity = $availability['stock_limited'] ? min($quantity, (int)$availability['stock']) : $quantity;
        $pdo->prepare('UPDATE vitrine_carrinho SET quantidade = ? WHERE id = ?')->execute([$newQuantity, $cartId]);
        site_pro_api_output(['ok' => true, 'quantidade' => $newQuantity]);

    case 'carrinho_remover':
        $cartId = (int)($_POST['carrinho_id'] ?? 0);
        $pdo->prepare('DELETE FROM vitrine_carrinho WHERE id = ? AND sessao = ? AND user_id = ?')
            ->execute([$cartId, $cartSession, $ownerId]);
        site_pro_api_output(['ok' => true]);

    case 'carrinho_limpar':
        $pdo->prepare('DELETE FROM vitrine_carrinho WHERE sessao = ? AND user_id = ?')->execute([$cartSession, $ownerId]);
        site_pro_api_output(['ok' => true]);

    case 'fazer_pedido':
        $checkoutChoice = trim((string)($_POST['finalizacao'] ?? ''));
        $allowedCheckoutChoices = match ($config['finalizacao_pedido']) {
            'infinitepay' => ['infinitepay'],
            'ambos' => ['whatsapp', 'infinitepay'],
            default => ['whatsapp'],
        };
        if (!in_array($checkoutChoice, $allowedCheckoutChoices, true)) {
            site_pro_api_error('Forma de finalização indisponível.');
        }
        if ($checkoutChoice === 'infinitepay'
            && !preg_match('/^[a-z0-9._-]{2,80}$/', (string)$config['infinitepay_tag'])
        ) {
            site_pro_api_error('A loja ainda não concluiu a configuração da InfinitePay.', 409);
        }
        $deliveryType = trim((string)($_POST['tipo'] ?? 'retirada'));
        if (!in_array($deliveryType, ['entrega', 'retirada'], true)) {
            site_pro_api_error('Escolha entrega ou retirada.', 422);
        }
        $experienceJson = null;
        if (uplar_vitrine_experience_schema_disponivel($pdo)) {
            $experienceStmt = $pdo->prepare('SELECT experiencia_json FROM vitrine_config WHERE user_id = ? LIMIT 1');
            $experienceStmt->execute([$ownerId]);
            $experienceJson = $experienceStmt->fetchColumn() ?: null;
        }
        $serviceState = uplar_commercial_availability_service_state(
            uplar_storefront_schedule_preferences($experienceJson),
            $deliveryType,
            new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
        if (!$serviceState['allowed']) {
            site_pro_api_error($serviceState['message'] ?: 'Esta loja não está recebendo pedidos no momento.', 409);
        }
        $customerName = mb_substr(trim((string)($_POST['nome'] ?? '')), 0, 120);
        $customerPhone = mb_substr(trim((string)($_POST['tel'] ?? '')), 0, 30);
        $deliveryAddress = mb_substr(trim((string)($_POST['endereco'] ?? '')), 0, 255);
        $notes = mb_substr(trim((string)($_POST['obs'] ?? '')), 0, 500);
        if ($deliveryType === 'entrega' && $deliveryAddress === '') {
            site_pro_api_error('Informe o endereço de entrega.');
        }

        $personalizationSelect = $complementsReady ? 'vc.personalizacao_json' : 'NULL AS personalizacao_json';
        $availabilitySelect = $availabilitySchemaReady ? uplar_commercial_availability_product_select($pdo, 'p') : '';
        $itemsStmt = $pdo->prepare('
            SELECT vc.quantidade AS qtd, vc.tamanho, ' . $personalizationSelect . ',
                   p.id, p.nome, p.codigo, p.preco, p.preco_custo, p.usa_tamanhos,
                   p.quantidade AS estoque_produto,
                   CASE WHEN vc.tamanho IS NULL THEN p.quantidade ELSE pt.quantidade END AS estoque' . $availabilitySelect . '
            FROM vitrine_carrinho vc
            JOIN produtos p ON p.id = vc.produto_id AND p.user_id = vc.user_id
            LEFT JOIN produto_tamanhos pt ON pt.produto_id = vc.produto_id AND pt.tamanho = vc.tamanho AND pt.ativo = 1
            WHERE vc.sessao = ? AND vc.user_id = ? AND p.site_pro_exposto = 1
        ');
        $itemsStmt->execute([$cartSession, $ownerId]);
        $cartItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$cartItems) {
            site_pro_api_error('Carrinho vazio.');
        }

        $subtotalCents = 0;
        $orderItems = [];
        $reservedStock = [];
        foreach ($cartItems as $item) {
            $item = uplar_commercial_availability_normalize_product($item);
            if ((int)$item['usa_tamanhos'] && empty($item['tamanho'])) site_pro_api_error('Escolha novamente os tamanhos do carrinho.', 409);
            if (!(int)$item['usa_tamanhos'] && !empty($item['tamanho'])) site_pro_api_error('Revise as variações do carrinho.', 409);
            $quantity = (int)$item['qtd'];
            $variantStock = $item['tamanho'] === null ? null : (is_numeric($item['estoque']) ? (int)$item['estoque'] : null);
            $availability = uplar_commercial_availability_evaluate_product($item, $item['tamanho'], $variantStock, (int)$item['estoque_produto']);
            if (!$availability['available'] || !uplar_commercial_availability_reserve_quantity($reservedStock, (int)$item['id'], $item['tamanho'] !== null ? (string)$item['tamanho'] : null, $quantity, $availability)) {
                site_pro_api_error('O estoque mudou. Ajuste a quantidade do item antes de finalizar.', 409);
            }
            $personalization = is_string($item['personalizacao_json'] ?? null) ? json_decode($item['personalizacao_json'], true) : null;
            $personalization = is_array($personalization) ? $personalization : null;
            $validated = null;
            if ($complementsReady) {
                try {
                    $validated = uplar_complementos_validar($pdo, $ownerId, (int)$item['id'], site_pro_complementos_input($personalization ?? []));
                } catch (Throwable $e) {
                    site_pro_api_error('Uma personalização ou preço mudou. Remova e configure novamente os itens indicados antes de finalizar.', 409);
                }
                if ($personalization && (json_encode($personalization['grupos'] ?? [], JSON_UNESCAPED_UNICODE) !== json_encode($validated['snapshot']['grupos'] ?? [], JSON_UNESCAPED_UNICODE)
                    || site_pro_complementos_unit_cents($personalization, (float)$item['preco']) !== (int)$validated['preco_unitario_centavos'])) {
                    site_pro_api_error('Uma personalização ou preço mudou. Remova e configure novamente os itens indicados antes de finalizar.', 409);
                }
            }
            $unitCents = $validated ? (int)$validated['preco_unitario_centavos'] : (int)round((float)$item['preco'] * 100);
            $unitPrice = $unitCents / 100;
            $lineCents = $quantity * $unitCents;
            $itemSubtotal = $lineCents / 100;
            $subtotalCents += $lineCents;
            if ($validated) {
                $personalization = $validated['snapshot'];
                $personalization['custos_completos'] = (bool)($validated['custos_completos'] ?? false) && (float)$item['preco_custo'] > 0;
                $personalization['custo_base_centavos'] = (int)round((float)$item['preco_custo'] * 100);
            }
            $orderItems[] = [
                'produto_id' => (int)$item['id'],
                'nome' => $item['nome'],
                'codigo' => $item['codigo'] ?? '',
                'tamanho' => $item['tamanho'] !== null ? (string)$item['tamanho'] : null,
                'preco' => $unitPrice,
                'quantidade' => $quantity,
                'subtotal' => $itemSubtotal,
                'modo_disponibilidade' => $availability['mode'],
                'personalizacao' => $personalization,
                'observacao_item' => trim((string)($personalization['observacao'] ?? '')),
            ];
        }
        if (!$orderItems) {
            site_pro_api_error('Nenhum item disponível em estoque.');
        }

        $coupon = null;
        $savedCouponCode = (string)($_SESSION[$couponSessionKey] ?? '');
        if ($savedCouponCode !== '') {
            $coupon = site_pro_find_coupon($pdo, $ownerId, $savedCouponCode);
            if (!$coupon) {
                unset($_SESSION[$couponSessionKey]);
            }
        }
        $pricing = site_pro_apply_coupon($subtotalCents / 100, $coupon);
        if ($pricing['percentual'] > 0) {
            $discountFactor = 1 - ($pricing['percentual'] / 100);
            foreach ($orderItems as &$orderItem) {
                $orderItem['preco_original'] = $orderItem['preco'];
                $orderItem['subtotal_original'] = $orderItem['subtotal'];
                $discountedUnitPrice = round($orderItem['preco'] * $discountFactor, 2);
                $orderItem['preco'] = $discountedUnitPrice > 0 ? $discountedUnitPrice : 0.0001;
                $orderItem['subtotal'] = round($orderItem['subtotal_original'] * $discountFactor, 2);
            }
            unset($orderItem);
        }
        $shipping = $deliveryType === 'entrega' ? (float)$config['valor_frete'] : 0.0;
        $total = round($pricing['total_produtos'] + $shipping, 2);
        if ($checkoutChoice === 'infinitepay' && $total < 0.01) {
            site_pro_api_error('O total precisa ser maior que zero para pagar online.');
        }
        site_pro_order_schema($pdo);
        $pdo->prepare('
            INSERT INTO vitrine_pedidos
                (user_id, origem, sessao, cliente_nome, cliente_tel, tipo_entrega, endereco_ent,
                 total, valor_frete, cupom_codigo, desconto, pagamento_status, itens_json, obs)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ')->execute([
            $ownerId,
            'site_pro',
            $cartSession,
            $customerName ?: null,
            $customerPhone ?: null,
            $deliveryType,
            $deliveryType === 'entrega' ? $deliveryAddress : null,
            $total,
            $shipping,
            $coupon['codigo'] ?? null,
            $pricing['desconto'],
            $checkoutChoice === 'infinitepay' ? 'pendente' : 'nao_aplicavel',
            json_encode($orderItems, JSON_UNESCAPED_UNICODE),
            $notes ?: null,
        ]);
        $orderId = (int)$pdo->lastInsertId();

        $checkout = null;
        if ($checkoutChoice === 'infinitepay') {
            $rate = $_SESSION['site_pro_checkout_rate'] ?? ['inicio' => time(), 'tentativas' => 0];
            if (!is_array($rate) || time() - (int)($rate['inicio'] ?? 0) > 600) {
                $rate = ['inicio' => time(), 'tentativas' => 0];
            }
            if ((int)$rate['tentativas'] >= 10) {
                $pdo->prepare('DELETE FROM vitrine_pedidos WHERE id = ? AND user_id = ?')->execute([$orderId, $ownerId]);
                site_pro_api_error('Muitas tentativas de pagamento. Aguarde alguns minutos.', 429);
            }
            $rate['tentativas'] = (int)$rate['tentativas'] + 1;
            $_SESSION['site_pro_checkout_rate'] = $rate;
            try {
                $checkout = site_pro_create_infinitepay_checkout(
                    $pdo,
                    $orderId,
                    $ownerId,
                    (string)$config['infinitepay_tag'],
                    (int)round($total * 100),
                    (string)$config['nome_site']
                );
            } catch (Throwable $e) {
                $pdo->prepare('DELETE FROM vitrine_pedidos WHERE id = ? AND user_id = ?')->execute([$orderId, $ownerId]);
                error_log('[site pro checkout] ' . $e->getMessage());
                site_pro_api_error($e->getMessage(), 502);
            }
        }

        try {
            site_pro_analytics_record($pdo, $ownerId, 'order_created');
        } catch (Throwable $e) {
            error_log('[site pro analytics order] ' . $e->getMessage());
        }
        $pdo->prepare('DELETE FROM vitrine_carrinho WHERE sessao = ? AND user_id = ?')->execute([$cartSession, $ownerId]);
        unset($_SESSION[$couponSessionKey]);

        $message = "*Novo pedido pelo Site PRO #{$orderId}*\n\n";
        if ($customerName !== '') {
            $message .= "*Cliente:* {$customerName}\n";
        }
        if ($customerPhone !== '') {
            $message .= "*Contato:* {$customerPhone}\n";
        }
        $message .= '*Tipo:* ' . ($deliveryType === 'entrega' ? 'Entrega' : 'Retirada na loja') . "\n";
        if ($deliveryType === 'entrega') {
            $message .= "*Endereço:* {$deliveryAddress}\n";
        }
        $message .= "\n*Itens:*\n";
        foreach ($orderItems as $item) {
            $displaySubtotal = $item['subtotal_original'] ?? $item['subtotal'];
            $message .= "• {$item['quantidade']}x {$item['nome']}" . (!empty($item['tamanho']) ? " · Tamanho: {$item['tamanho']}" : '') . "\n";
            foreach (($item['personalizacao']['grupos'] ?? []) as $group) {
                if (!is_array($group)) continue;
                $options = [];
                foreach (($group['opcoes'] ?? []) as $option) {
                    if (!is_array($option)) continue;
                    $name = trim((string)($option['nome'] ?? ''));
                    if ($name === '') continue;
                    $optionQuantity = max(1, (int)($option['quantidade'] ?? 1));
                    $options[] = $name . ($optionQuantity > 1 ? ' × ' . $optionQuantity : '');
                }
                if ($options) $message .= '  ' . trim((string)($group['nome'] ?? 'Personalização')) . ': ' . implode(', ', $options) . "\n";
            }
            $itemNote = trim((string)($item['observacao_item'] ?? ''));
            if ($itemNote !== '') $message .= "  Observação: {$itemNote}\n";
            $message .= '  R$ ' . number_format($displaySubtotal, 2, ',', '.') . "\n";
        }
        if ($pricing['desconto'] > 0) {
            $message .= '*Cupom ' . $pricing['codigo'] . ':* - R$ ' . number_format($pricing['desconto'], 2, ',', '.') . "\n";
        }
        if ($shipping > 0) {
            $message .= '*Frete:* R$ ' . number_format($shipping, 2, ',', '.') . "\n";
        }
        $message .= "\n*Total: R$ " . number_format($total, 2, ',', '.') . '*';
        if ($notes !== '') {
            $message .= "\n\n*Observações:* {$notes}";
        }

        if ($checkoutChoice === 'infinitepay') {
            site_pro_api_output([
                'ok' => true,
                'pedido_id' => $orderId,
                'total' => $total,
                'checkout_url' => $checkout['checkout_url'],
                'order_nsu' => $checkout['order_nsu'],
            ]);
        }

        $whatsapp = preg_replace('/\D/', '', (string)($config['whatsapp'] ?: $owner['whatsapp_number']));
        site_pro_api_output([
            'ok' => true,
            'pedido_id' => $orderId,
            'total' => $total,
            'whatsapp' => $whatsapp,
            'wa_msg' => urlencode($message),
        ]);

    default:
        site_pro_api_error('Ação inválida', 404);
}
