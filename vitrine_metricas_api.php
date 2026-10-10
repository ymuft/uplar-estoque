<?php

session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/includes/vitrine_analytics.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function vitrine_metrics_out(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function vitrine_metrics_ensure_order_origin(PDO $pdo): void
{
    if (!$pdo->query("SHOW COLUMNS FROM vitrine_pedidos LIKE 'origem'")->fetchColumn()) {
        $pdo->exec("ALTER TABLE vitrine_pedidos ADD COLUMN origem VARCHAR(20) NOT NULL DEFAULT 'vitrine' AFTER user_id");
    }
}

if (empty($_SESSION['user_id'])) {
    vitrine_metrics_out(['ok' => false, 'error' => 'Não autenticado'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    vitrine_metrics_out(['ok' => false, 'error' => 'Método inválido'], 405);
}

$userId = (int)$_SESSION['user_id'];
$days = (int)($_GET['days'] ?? 30);
if (!in_array($days, [7, 30, 90], true)) {
    $days = 30;
}
$interval = $days - 1;
$previousStart = ($days * 2) - 1;
$periodStart = date('Y-m-d 00:00:00', strtotime("-{$interval} days"));

try {
    $pdo = get_pdo();
    vitrine_analytics_ensure_schema($pdo);
    vitrine_metrics_ensure_order_origin($pdo);

    $trackingStartStmt = $pdo->prepare("SELECT MIN(created_at) FROM vitrine_analytics_events WHERE owner_user_id = ? AND device_type <> 'bot'");
    $trackingStartStmt->execute([$userId]);
    $trackingStart = $trackingStartStmt->fetchColumn() ?: null;
    $effectiveStart = $trackingStart
        ? ($trackingStart > $periodStart ? $trackingStart : $periodStart)
        : date('Y-m-d H:i:s');

    $summaryStmt = $pdo->prepare("SELECT
        COUNT(DISTINCT CASE WHEN event_type = 'store_view' THEN session_hash END) AS store_views,
        SUM(event_type = 'store_view') AS page_views,
        COUNT(DISTINCT CASE WHEN event_type = 'store_view' THEN visitor_hash END) AS unique_visitors,
        SUM(event_type = 'product_view') AS product_views,
        SUM(event_type = 'search') AS searches,
        SUM(event_type = 'add_to_cart') AS add_to_cart,
        SUM(event_type = 'cart_view') AS cart_views,
        SUM(event_type = 'checkout_start') AS checkout_starts,
        SUM(event_type = 'whatsapp_click') AS whatsapp_clicks,
        ROUND(AVG(CASE WHEN event_type = 'page_load' THEN metric_value END)) AS avg_load_ms,
        ROUND(AVG(CASE WHEN event_type = 'session_end' THEN metric_value END)) AS avg_session_ms,
        ROUND(AVG(CASE WHEN event_type = 'scroll_depth' THEN metric_value END)) AS avg_scroll_depth,
        COUNT(DISTINCT CASE WHEN event_type = 'product_view' THEN session_hash END) AS product_sessions,
        COUNT(DISTINCT CASE WHEN event_type = 'add_to_cart' THEN session_hash END) AS cart_sessions,
        COUNT(DISTINCT CASE WHEN event_type = 'checkout_start' THEN session_hash END) AS checkout_sessions
        FROM vitrine_analytics_events
        WHERE owner_user_id = ? AND created_at >= ? AND device_type <> 'bot'");
    $summaryStmt->execute([$userId, $effectiveStart]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $sessionStmt = $pdo->prepare("SELECT COUNT(*) AS sessions,
        SUM(interaction_count > 0) AS engaged_sessions,
        SUM(interaction_count = 0) AS bounced_sessions
        FROM (
            SELECT session_hash,
                SUM(event_type IN ('product_view', 'search', 'category_filter', 'add_to_cart', 'cart_view', 'checkout_start', 'whatsapp_click')) AS interaction_count
            FROM vitrine_analytics_events
            WHERE owner_user_id = ? AND created_at >= ? AND device_type <> 'bot'
            GROUP BY session_hash
            HAVING SUM(event_type = 'store_view') > 0
        ) session_activity");
    $sessionStmt->execute([$userId, $effectiveStart]);
    $sessions = $sessionStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $ordersStmt = $pdo->prepare("SELECT COUNT(*) AS orders,
        COALESCE(SUM(CASE WHEN status = 'confirmado' THEN total ELSE 0 END), 0) AS revenue
        FROM vitrine_pedidos WHERE user_id = ? AND origem = 'vitrine' AND criado_em >= ?");
    $ordersStmt->execute([$userId, $effectiveStart]);
    $orders = $ordersStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $comparisonStmt = $pdo->prepare("SELECT
        COUNT(DISTINCT CASE WHEN created_at >= ? AND event_type = 'store_view' THEN session_hash END) AS current_views,
        COUNT(DISTINCT CASE WHEN created_at < ? AND event_type = 'store_view' THEN session_hash END) AS previous_views,
        COUNT(DISTINCT CASE WHEN created_at >= ? AND event_type = 'store_view' THEN visitor_hash END) AS current_visitors,
        COUNT(DISTINCT CASE WHEN created_at < ? AND event_type = 'store_view' THEN visitor_hash END) AS previous_visitors
        FROM vitrine_analytics_events
        WHERE owner_user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL {$previousStart} DAY) AND device_type <> 'bot'");
    $comparisonStmt->execute([$effectiveStart, $periodStart, $effectiveStart, $periodStart, $userId]);
    $comparison = $comparisonStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $previousOrdersStmt = $pdo->prepare("SELECT COUNT(*) FROM vitrine_pedidos
        WHERE user_id = ?
          AND origem = 'vitrine'
          AND criado_em >= DATE_SUB(CURDATE(), INTERVAL {$previousStart} DAY)
          AND criado_em < ?");
    $previousOrdersStmt->execute([$userId, $periodStart]);
    $previousOrders = (int)$previousOrdersStmt->fetchColumn();

    $dailyStmt = $pdo->prepare("SELECT DATE(created_at) AS day,
        COUNT(DISTINCT CASE WHEN event_type = 'store_view' THEN session_hash END) AS views,
        COUNT(DISTINCT CASE WHEN event_type = 'store_view' THEN visitor_hash END) AS visitors,
        SUM(event_type = 'product_view') AS product_views,
        SUM(event_type = 'add_to_cart') AS cart_adds
        FROM vitrine_analytics_events
        WHERE owner_user_id = ? AND created_at >= ? AND device_type <> 'bot'
        GROUP BY DATE(created_at) ORDER BY day");
    $dailyStmt->execute([$userId, $effectiveStart]);

    $topStmt = $pdo->prepare("SELECT p.id, p.nome, p.imagem,
        SUM(e.event_type = 'product_view') AS views,
        COUNT(DISTINCT CASE WHEN e.event_type = 'product_view' THEN e.visitor_hash END) AS visitors,
        SUM(e.event_type = 'add_to_cart') AS cart_adds
        FROM vitrine_analytics_events e
        JOIN produtos p ON p.id = e.product_id AND p.user_id = e.owner_user_id
        WHERE e.owner_user_id = ? AND e.created_at >= ? AND e.device_type <> 'bot'
          AND e.event_type IN ('product_view', 'add_to_cart')
        GROUP BY p.id, p.nome, p.imagem ORDER BY views DESC, cart_adds DESC LIMIT 10");
    $topStmt->execute([$userId, $effectiveStart]);

    $sourcesStmt = $pdo->prepare("SELECT COALESCE(NULLIF(referrer_host, ''), 'Acesso direto') AS source,
        COUNT(DISTINCT session_hash) AS total
        FROM vitrine_analytics_events
        WHERE owner_user_id = ? AND event_type = 'store_view'
          AND created_at >= ? AND device_type <> 'bot'
        GROUP BY source ORDER BY total DESC LIMIT 8");
    $sourcesStmt->execute([$userId, $effectiveStart]);

    $devicesStmt = $pdo->prepare("SELECT device_type, COUNT(DISTINCT session_hash) AS total
        FROM vitrine_analytics_events
        WHERE owner_user_id = ? AND event_type = 'store_view'
          AND created_at >= ? AND device_type <> 'bot'
        GROUP BY device_type ORDER BY total DESC");
    $devicesStmt->execute([$userId, $effectiveStart]);

    $hoursStmt = $pdo->prepare("SELECT HOUR(created_at) AS hour, COUNT(DISTINCT session_hash) AS total
        FROM vitrine_analytics_events
        WHERE owner_user_id = ? AND event_type = 'store_view'
          AND created_at >= ? AND device_type <> 'bot'
        GROUP BY HOUR(created_at) ORDER BY hour");
    $hoursStmt->execute([$userId, $effectiveStart]);

    $views = (int)($summary['store_views'] ?? 0);
    $productViews = (int)($summary['product_views'] ?? 0);
    $cartAdds = (int)($summary['add_to_cart'] ?? 0);
    $checkouts = (int)($summary['checkout_starts'] ?? 0);
    $productSessions = (int)($summary['product_sessions'] ?? 0);
    $cartSessions = (int)($summary['cart_sessions'] ?? 0);
    $checkoutSessions = (int)($summary['checkout_sessions'] ?? 0);
    $orderCount = (int)($orders['orders'] ?? 0);
    $sessionCount = (int)($sessions['sessions'] ?? 0);

    vitrine_metrics_out(['ok' => true, 'data' => [
        'period_days' => $days,
        'tracking_start' => $trackingStart,
        'comparison_available' => $trackingStart && $trackingStart <= date('Y-m-d 00:00:00', strtotime("-{$days} days")),
        'summary' => [
            'store_views' => $views,
            'page_views' => (int)($summary['page_views'] ?? 0),
            'unique_visitors' => (int)($summary['unique_visitors'] ?? 0),
            'product_views' => $productViews,
            'searches' => (int)($summary['searches'] ?? 0),
            'add_to_cart' => $cartAdds,
            'cart_views' => (int)($summary['cart_views'] ?? 0),
            'checkout_starts' => $checkouts,
            'whatsapp_clicks' => (int)($summary['whatsapp_clicks'] ?? 0),
            'orders' => $orderCount,
            'revenue' => (float)($orders['revenue'] ?? 0),
            'sessions' => $sessionCount,
            'engaged_sessions' => (int)($sessions['engaged_sessions'] ?? 0),
            'bounce_rate' => $sessionCount > 0 ? round(((int)($sessions['bounced_sessions'] ?? 0) / $sessionCount) * 100, 1) : 0,
            'avg_load_ms' => (int)($summary['avg_load_ms'] ?? 0),
            'avg_session_ms' => (int)($summary['avg_session_ms'] ?? 0),
            'avg_scroll_depth' => (int)($summary['avg_scroll_depth'] ?? 0),
            'product_view_rate' => $views > 0 ? round(($productSessions / $views) * 100, 1) : 0,
            'cart_rate' => $productSessions > 0 ? round(($cartSessions / $productSessions) * 100, 1) : 0,
            'checkout_rate' => $cartSessions > 0 ? round(($checkoutSessions / $cartSessions) * 100, 1) : 0,
            'conversion_rate' => $views > 0 ? round(($orderCount / $views) * 100, 1) : 0,
        ],
        'comparison' => [
            'views' => [(int)($comparison['current_views'] ?? 0), (int)($comparison['previous_views'] ?? 0)],
            'visitors' => [(int)($comparison['current_visitors'] ?? 0), (int)($comparison['previous_visitors'] ?? 0)],
            'orders' => [$orderCount, $previousOrders],
        ],
        'funnel' => [
            ['label' => 'Visitas', 'value' => $views],
            ['label' => 'Viram produtos', 'value' => $productSessions],
            ['label' => 'Adicionaram ao carrinho', 'value' => $cartSessions],
            ['label' => 'Iniciaram o pedido', 'value' => $checkoutSessions],
            ['label' => 'Pedidos', 'value' => $orderCount],
        ],
        'daily' => $dailyStmt->fetchAll(PDO::FETCH_ASSOC),
        'top_products' => $topStmt->fetchAll(PDO::FETCH_ASSOC),
        'sources' => $sourcesStmt->fetchAll(PDO::FETCH_ASSOC),
        'devices' => $devicesStmt->fetchAll(PDO::FETCH_ASSOC),
        'peak_hours' => $hoursStmt->fetchAll(PDO::FETCH_ASSOC),
    ]]);
} catch (Throwable $e) {
    error_log('[vitrine_metricas_api] ' . $e->getMessage());
    vitrine_metrics_out(['ok' => false, 'error' => 'Falha ao carregar métricas'], 500);
}
