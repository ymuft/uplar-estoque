<?php

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    http_response_code(404);
    exit;
}

function vitrine_analytics_ensure_schema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS vitrine_analytics_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        owner_user_id INT NOT NULL,
        product_id INT NULL,
        event_type VARCHAR(40) NOT NULL,
        visitor_hash CHAR(64) NOT NULL,
        session_hash CHAR(64) NOT NULL,
        ip_hash CHAR(64) NOT NULL,
        device_type VARCHAR(20) NOT NULL DEFAULT 'desktop',
        browser_family VARCHAR(30) NOT NULL DEFAULT 'other',
        referrer_host VARCHAR(190) NULL,
        metric_value INT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_vitrine_analytics_owner_event (owner_user_id, event_type, created_at),
        INDEX idx_vitrine_analytics_owner_product (owner_user_id, product_id, event_type, created_at),
        INDEX idx_vitrine_analytics_owner_visitor (owner_user_id, visitor_hash, created_at),
        INDEX idx_vitrine_analytics_owner_session (owner_user_id, session_hash, created_at),
        CONSTRAINT fk_vitrine_analytics_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
        CONSTRAINT fk_vitrine_analytics_product FOREIGN KEY (product_id) REFERENCES produtos(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $ready = true;
}

function vitrine_analytics_hash_token($value): string
{
    $token = is_string($value) ? trim($value) : '';
    if ($token === '' || strlen($token) > 100 || !preg_match('/^[a-zA-Z0-9_-]+$/', $token)) {
        $token = bin2hex(random_bytes(16));
    }
    return hash('sha256', $token);
}

function vitrine_analytics_client_ip(): string
{
    return function_exists('get_client_ip')
        ? (string)get_client_ip()
        : (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function vitrine_analytics_client_profile(): array
{
    $ua = strtolower(substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512));
    $device = preg_match('/bot|crawler|spider|slurp/', $ua)
        ? 'bot'
        : (preg_match('/ipad|tablet/', $ua) ? 'tablet' : (preg_match('/mobile|android|iphone/', $ua) ? 'mobile' : 'desktop'));

    $browser = 'other';
    foreach (['edg' => 'edge', 'opr' => 'opera', 'chrome' => 'chrome', 'firefox' => 'firefox', 'safari' => 'safari'] as $needle => $name) {
        if (str_contains($ua, $needle)) {
            $browser = $name;
            break;
        }
    }

    return [$device, $browser];
}

function vitrine_analytics_referrer_host($value): ?string
{
    $url = is_string($value) ? substr(trim($value), 0, 1024) : '';
    if ($url === '') {
        return null;
    }
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    return $host !== '' && strlen($host) <= 190 ? $host : null;
}

function vitrine_analytics_record(PDO $pdo, int $ownerId, string $eventType, array $data = []): void
{
    $allowed = [
        'store_view', 'product_view', 'product_share', 'search', 'category_filter', 'add_to_cart',
        'cart_view', 'checkout_start', 'order_created', 'whatsapp_click',
        'page_load', 'scroll_depth', 'session_end',
    ];
    if ($ownerId < 1 || !in_array($eventType, $allowed, true)) {
        throw new InvalidArgumentException('Evento de vitrine inválido.');
    }

    vitrine_analytics_ensure_schema($pdo);
    [$device, $browser] = vitrine_analytics_client_profile();
    $productId = isset($data['product_id']) && (int)$data['product_id'] > 0 ? (int)$data['product_id'] : null;
    $value = isset($data['value']) ? max(0, min(86400000, (int)$data['value'])) : null;
    $salt = (string)(getenv('UPLAR_ANALYTICS_SALT') ?: 'uplar-vitrine-analytics-v1');
    $ipHash = hash_hmac('sha256', $ownerId . '|' . vitrine_analytics_client_ip() . '|' . date('Y-m'), $salt);

    $stmt = $pdo->prepare("INSERT INTO vitrine_analytics_events
        (owner_user_id, product_id, event_type, visitor_hash, session_hash, ip_hash,
         device_type, browser_family, referrer_host, metric_value, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        $ownerId,
        $productId,
        $eventType,
        vitrine_analytics_hash_token($data['visitor_id'] ?? ''),
        vitrine_analytics_hash_token($data['session_id'] ?? session_id()),
        $ipHash,
        $device,
        $browser,
        vitrine_analytics_referrer_host($data['referrer'] ?? ''),
        $value,
    ]);
}

function vitrine_analytics_rate_limit(string $bucket, int $limit = 120): bool
{
    $key = hash('sha256', $bucket . '|' . vitrine_analytics_client_ip());
    $path = sys_get_temp_dir() . '/uplar-va-' . $key;
    $handle = fopen($path, 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) {
        return false;
    }

    $now = time();
    $state = json_decode((string)stream_get_contents($handle), true);
    if (!is_array($state) || ($state['start'] ?? 0) <= $now - 60) {
        $state = ['start' => $now, 'count' => 0];
    }
    $state['count']++;
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($state));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);

    return $state['count'] <= $limit;
}
