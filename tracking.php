<?php

if (!function_exists('uplar_tracking_uuid')) {
    function uplar_tracking_uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}

if (!function_exists('uplar_tracking_is_uuid')) {
    function uplar_tracking_is_uuid(?string $value): bool
    {
        return is_string($value) && preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $value
        ) === 1;
    }
}

if (!function_exists('uplar_tracking_clean_string')) {
    function uplar_tracking_clean_string($value, int $maxLength = 255): string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '') {
            return '';
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }

        return substr($value, 0, $maxLength);
    }
}

if (!function_exists('uplar_tracking_now')) {
    function uplar_tracking_now(int $offsetSeconds = 0): string
    {
        static $timezone = null;
        if (!$timezone instanceof DateTimeZone) {
            $timezone = new DateTimeZone(getenv('UPLAR_TIMEZONE') ?: 'America/Manaus');
        }
        $now = new DateTimeImmutable('now', $timezone);
        if ($offsetSeconds !== 0) {
            $now = $now->modify(($offsetSeconds > 0 ? '+' : '') . $offsetSeconds . ' seconds');
        }
        return $now->format('Y-m-d H:i:s');
    }
}

if (!function_exists('uplar_tracking_clean_url')) {
    function uplar_tracking_clean_url($value, int $maxLength = 1024): string
    {
        $url = uplar_tracking_clean_string($value, $maxLength);
        if ($url === '') {
            return '';
        }

        if (preg_match('#^(https?:)?/#i', $url)) {
            return $url;
        }

        return '';
    }
}

if (!function_exists('uplar_tracking_clean_array')) {
    function uplar_tracking_clean_array($value, int $depth = 0)
    {
        if ($depth > 4) {
            return null;
        }

        if (is_array($value)) {
            $clean = [];
            $count = 0;
            foreach ($value as $key => $item) {
                if ($count >= 50) {
                    break;
                }
                $cleanKey = is_int($key) ? $key : uplar_tracking_clean_string((string)$key, 80);
                $clean[$cleanKey] = uplar_tracking_clean_array($item, $depth + 1);
                $count++;
            }
            return $clean;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return uplar_tracking_clean_string((string)$value, 500);
    }
}

if (!function_exists('uplar_tracking_json')) {
    function uplar_tracking_json($value): string
    {
        $json = json_encode(
            uplar_tracking_clean_array($value),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return $json === false ? '{}' : $json;
    }
}

if (!function_exists('uplar_tracking_client_ip')) {
    function uplar_tracking_client_ip(): string
    {
        if (function_exists('get_client_ip')) {
            return get_client_ip();
        }

        return uplar_tracking_clean_string($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 45);
    }
}

if (!function_exists('uplar_tracking_geolocate_ip')) {
    function uplar_tracking_geolocate_ip(string $ip): array
    {
        $fallback = ['country' => null, 'city' => null, 'provider' => 'local'];
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return $fallback;
        }

        if (
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
        ) {
            return ['country' => 'Rede privada', 'city' => null, 'provider' => 'private'];
        }

        $template = trim((string)(getenv('UPLAR_GEO_ENDPOINT') ?: ''));
        if ($template === '') {
            return $fallback;
        }

        $url = sprintf($template, rawurlencode($ip));
        $context = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => 1.5,
                'header'  => "Accept: application/json\r\nUser-Agent: UplarTracking/1.0\r\n",
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        if (!is_string($raw) || $raw === '') {
            return $fallback;
        }

        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return $fallback;
        }

        return [
            'country'  => uplar_tracking_clean_string($payload['country_name'] ?? $payload['country'] ?? '', 120) ?: null,
            'city'     => uplar_tracking_clean_string($payload['city'] ?? '', 120) ?: null,
            'provider' => 'remote',
        ];
    }
}

if (!function_exists('uplar_tracking_rate_limit_ok')) {
    function uplar_tracking_rate_limit_ok(string $bucket, int $limit, int $windowSeconds): bool
    {
        $ip = uplar_tracking_client_ip();
        $file = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR .
            'uplar-track-' . hash('sha256', $bucket . '|' . $ip) . '.json';

        $handle = @fopen($file, 'c+');
        if (!$handle) {
            return true;
        }

        $allowed = true;
        try {
            if (!flock($handle, LOCK_EX)) {
                return true;
            }

            $raw = stream_get_contents($handle);
            $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
            if (!is_array($data)) {
                $data = [];
            }

            $now = time();
            $startedAt = (int)($data['started_at'] ?? 0);
            $hits = (int)($data['hits'] ?? 0);

            if ($startedAt <= 0 || ($now - $startedAt) >= $windowSeconds) {
                $startedAt = $now;
                $hits = 0;
            }

            $hits++;
            if ($hits > $limit) {
                $allowed = false;
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode(['started_at' => $startedAt, 'hits' => $hits]));
            fflush($handle);
            @chmod($file, 0600);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        return $allowed;
    }
}

if (!function_exists('uplar_tracking_ensure_schema')) {
    function uplar_tracking_ensure_schema(PDO $pdo): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }

        $cacheKey = 'uplar_tracking_schema_v2';
        if (function_exists('apcu_fetch') && apcu_fetch($cacheKey) === true) {
            $ready = true;
            return;
        }

        $requiredTables = [
            'tracking_devices',
            'tracking_sessions',
            'tracking_events',
            'tracking_logs',
            'tracking_errors',
            'tracking_webhook_queue',
        ];
        $placeholders = implode(',', array_fill(0, count($requiredTables), '?'));
        $check = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ($placeholders)");
        $check->execute($requiredTables);
        if ((int)$check->fetchColumn() === count($requiredTables)) {
            if (function_exists('apcu_store')) {
                apcu_store($cacheKey, true, 3600);
            }
            $ready = true;
            return;
        }

        $queries = [
            "CREATE TABLE IF NOT EXISTS tracking_devices (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                device_hash CHAR(64) NOT NULL UNIQUE,
                user_agent VARCHAR(512) NOT NULL DEFAULT '',
                screen_resolution VARCHAR(32) NOT NULL DEFAULT '',
                timezone VARCHAR(64) NOT NULL DEFAULT '',
                language VARCHAR(32) NOT NULL DEFAULT '',
                platform VARCHAR(64) NOT NULL DEFAULT '',
                fingerprint_json LONGTEXT NULL,
                first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tracking_devices_user (user_id),
                CONSTRAINT fk_tracking_devices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS tracking_sessions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                device_id BIGINT UNSIGNED NULL,
                session_uuid CHAR(36) NOT NULL UNIQUE,
                ip_address VARCHAR(45) NOT NULL DEFAULT '',
                ip_hash CHAR(64) NOT NULL DEFAULT '',
                country VARCHAR(120) NULL,
                city VARCHAR(120) NULL,
                user_agent VARCHAR(512) NOT NULL DEFAULT '',
                entry_page VARCHAR(1024) NULL,
                last_page VARCHAR(1024) NULL,
                referrer VARCHAR(1024) NULL,
                started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_activity_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                ended_at DATETIME NULL,
                duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                metadata_json LONGTEXT NULL,
                INDEX idx_tracking_sessions_user (user_id),
                INDEX idx_tracking_sessions_device (device_id),
                INDEX idx_tracking_sessions_active (is_active, last_activity_at),
                CONSTRAINT fk_tracking_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT fk_tracking_sessions_device FOREIGN KEY (device_id) REFERENCES tracking_devices(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS tracking_events (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                session_id BIGINT UNSIGNED NULL,
                device_id BIGINT UNSIGNED NULL,
                event_name VARCHAR(120) NOT NULL,
                event_category VARCHAR(64) NOT NULL DEFAULT 'custom',
                request_uuid CHAR(36) NOT NULL,
                page_url VARCHAR(1024) NULL,
                metadata_json LONGTEXT NULL,
                engagement_score SMALLINT NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tracking_events_name (event_name, created_at),
                INDEX idx_tracking_events_user (user_id, created_at),
                INDEX idx_tracking_events_session (session_id),
                CONSTRAINT fk_tracking_events_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT fk_tracking_events_session FOREIGN KEY (session_id) REFERENCES tracking_sessions(id) ON DELETE SET NULL,
                CONSTRAINT fk_tracking_events_device FOREIGN KEY (device_id) REFERENCES tracking_devices(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS tracking_logs (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                session_id BIGINT UNSIGNED NULL,
                device_id BIGINT UNSIGNED NULL,
                level VARCHAR(16) NOT NULL,
                log_type VARCHAR(32) NOT NULL,
                action VARCHAR(120) NOT NULL,
                context_json LONGTEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tracking_logs_level (level, created_at),
                INDEX idx_tracking_logs_user (user_id, created_at),
                CONSTRAINT fk_tracking_logs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT fk_tracking_logs_session FOREIGN KEY (session_id) REFERENCES tracking_sessions(id) ON DELETE SET NULL,
                CONSTRAINT fk_tracking_logs_device FOREIGN KEY (device_id) REFERENCES tracking_devices(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS tracking_errors (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                session_id BIGINT UNSIGNED NULL,
                device_id BIGINT UNSIGNED NULL,
                source VARCHAR(16) NOT NULL,
                severity VARCHAR(16) NOT NULL DEFAULT 'error',
                fingerprint CHAR(64) NOT NULL,
                message TEXT NOT NULL,
                file_url VARCHAR(1024) NULL,
                line_number INT NULL,
                column_number INT NULL,
                stack_trace LONGTEXT NULL,
                context_json LONGTEXT NULL,
                occurrences INT UNSIGNED NOT NULL DEFAULT 1,
                first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_tracking_errors_fingerprint (fingerprint),
                INDEX idx_tracking_errors_source (source, last_seen_at),
                CONSTRAINT fk_tracking_errors_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT fk_tracking_errors_session FOREIGN KEY (session_id) REFERENCES tracking_sessions(id) ON DELETE SET NULL,
                CONSTRAINT fk_tracking_errors_device FOREIGN KEY (device_id) REFERENCES tracking_devices(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS tracking_webhook_queue (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                event_name VARCHAR(120) NOT NULL,
                payload_json LONGTEXT NULL,
                status VARCHAR(24) NOT NULL DEFAULT 'pending',
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                next_attempt_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                processed_at DATETIME NULL,
                INDEX idx_tracking_webhooks_status (status, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($queries as $sql) {
            $pdo->exec($sql);
        }

        if (function_exists('apcu_store')) {
            apcu_store($cacheKey, true, 3600);
        }
        $ready = true;
    }
}

if (!function_exists('uplar_tracking_device_payload')) {
    function uplar_tracking_device_payload(array $input): array
    {
        $fingerprint = is_array($input['fingerprint'] ?? null) ? $input['fingerprint'] : [];
        $device = [
            'user_agent'        => uplar_tracking_clean_string($fingerprint['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? ''), 512),
            'screen_resolution' => uplar_tracking_clean_string($fingerprint['screen_resolution'] ?? '', 32),
            'timezone'          => uplar_tracking_clean_string($fingerprint['timezone'] ?? '', 64),
            'language'          => uplar_tracking_clean_string($fingerprint['language'] ?? '', 32),
            'platform'          => uplar_tracking_clean_string($fingerprint['platform'] ?? '', 64),
        ];
        $device['hash'] = hash('sha256', implode('|', $device));
        $device['json'] = uplar_tracking_json($device);
        return $device;
    }
}

if (!function_exists('uplar_tracking_upsert_device')) {
    function uplar_tracking_upsert_device(PDO $pdo, ?int $userId, array $input): array
    {
        $device = uplar_tracking_device_payload($input);
        $now = uplar_tracking_now();

        $find = $pdo->prepare("SELECT id FROM tracking_devices WHERE device_hash = ?");
        $find->execute([$device['hash']]);
        $existingId = $find->fetchColumn();

        if ($existingId) {
            $update = $pdo->prepare(
                "UPDATE tracking_devices
                 SET user_id = COALESCE(?, user_id),
                     user_agent = ?,
                     screen_resolution = ?,
                     timezone = ?,
                     language = ?,
                     platform = ?,
                     fingerprint_json = ?,
                     last_seen_at = ?
                 WHERE id = ?"
            );
            $update->execute([
                $userId ?: null,
                $device['user_agent'],
                $device['screen_resolution'],
                $device['timezone'],
                $device['language'],
                $device['platform'],
                $device['json'],
                $now,
                $existingId,
            ]);
            return ['id' => (int)$existingId, 'hash' => $device['hash'], 'payload' => $device];
        }

        $insert = $pdo->prepare(
            "INSERT INTO tracking_devices
             (user_id, device_hash, user_agent, screen_resolution, timezone, language, platform, fingerprint_json, first_seen_at, last_seen_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $insert->execute([
            $userId ?: null,
            $device['hash'],
            $device['user_agent'],
            $device['screen_resolution'],
            $device['timezone'],
            $device['language'],
            $device['platform'],
            $device['json'],
            $now,
            $now,
        ]);

        return ['id' => (int)$pdo->lastInsertId(), 'hash' => $device['hash'], 'payload' => $device];
    }
}

if (!function_exists('uplar_tracking_touch_session')) {
    function uplar_tracking_touch_session(PDO $pdo, array $input, ?int $forcedUserId = null): array
    {
        uplar_tracking_ensure_schema($pdo);

        $userId = $forcedUserId ?? (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
        $device = uplar_tracking_upsert_device($pdo, $userId, $input);
        $sessionUuid = uplar_tracking_is_uuid($input['session_id'] ?? null)
            ? (string)$input['session_id']
            : uplar_tracking_uuid();
        $pageUrl = uplar_tracking_clean_url($input['page_url'] ?? '');
        $referrer = uplar_tracking_clean_url($input['referrer'] ?? '');
        $ip = uplar_tracking_client_ip();
        $geo = uplar_tracking_geolocate_ip($ip);
        $now = uplar_tracking_now();
        $metadata = uplar_tracking_json([
            'page_title' => $input['page_title'] ?? '',
            'page_type'  => $input['page_type'] ?? '',
            'extra'      => $input['metadata'] ?? [],
        ]);

        $find = $pdo->prepare("SELECT id, started_at FROM tracking_sessions WHERE session_uuid = ?");
        $find->execute([$sessionUuid]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $update = $pdo->prepare(
                "UPDATE tracking_sessions
                 SET user_id = COALESCE(?, user_id),
                     device_id = ?,
                     ip_address = ?,
                     ip_hash = ?,
                     country = ?,
                     city = ?,
                     user_agent = ?,
                     last_page = COALESCE(NULLIF(?, ''), last_page),
                     referrer = COALESCE(NULLIF(?, ''), referrer),
                     last_activity_at = ?,
                     ended_at = NULL,
                     is_active = 1,
                     duration_seconds = TIMESTAMPDIFF(SECOND, started_at, ?),
                     metadata_json = ?
                 WHERE id = ?"
            );
            $update->execute([
                $userId ?: null,
                $device['id'],
                $ip,
                hash('sha256', $ip),
                $geo['country'],
                $geo['city'],
                $device['payload']['user_agent'],
                $pageUrl,
                $referrer,
                $now,
                $now,
                $metadata,
                $existing['id'],
            ]);

            return [
                'id'           => (int)$existing['id'],
                'session_uuid' => $sessionUuid,
                'device_id'    => $device['id'],
                'device_hash'  => $device['hash'],
            ];
        }

        $insert = $pdo->prepare(
            "INSERT INTO tracking_sessions
             (user_id, device_id, session_uuid, ip_address, ip_hash, country, city, user_agent, entry_page, last_page, referrer, started_at, last_activity_at, duration_seconds, metadata_json)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?)"
        );
        $insert->execute([
            $userId ?: null,
            $device['id'],
            $sessionUuid,
            $ip,
            hash('sha256', $ip),
            $geo['country'],
            $geo['city'],
            $device['payload']['user_agent'],
            $pageUrl,
            $pageUrl,
            $referrer,
            $now,
            $now,
            $metadata,
        ]);

        return [
            'id'           => (int)$pdo->lastInsertId(),
            'session_uuid' => $sessionUuid,
            'device_id'    => $device['id'],
            'device_hash'  => $device['hash'],
        ];
    }
}

if (!function_exists('uplar_tracking_engagement_score')) {
    function uplar_tracking_engagement_score(string $eventName): int
    {
        $weights = [
            'login'            => 15,
            'view_dashboard'   => 5,
            'view_admin_users' => 6,
            'create_order'     => 25,
            'update_stock'     => 8,
            'create_product'   => 12,
            'page_view'        => 2,
            'api_timing'       => 1,
            'api_timing_summary' => 1,
            'page_load'        => 1,
        ];

        return $weights[$eventName] ?? 3;
    }
}

if (!function_exists('uplar_tracking_enqueue_webhook')) {
    function uplar_tracking_enqueue_webhook(PDO $pdo, string $eventName, array $payload): void
    {
        $insert = $pdo->prepare(
            "INSERT INTO tracking_webhook_queue (event_name, payload_json, status, attempts, next_attempt_at, created_at)
             VALUES (?, ?, 'pending', 0, NULL, ?)"
        );
        $insert->execute([$eventName, uplar_tracking_json($payload), uplar_tracking_now()]);
    }
}

if (!function_exists('uplar_tracking_should_enqueue_webhook')) {
    function uplar_tracking_should_enqueue_webhook(string $eventName): bool
    {
        return in_array($eventName, [
            'login',
            'create_order',
            'create_product',
            'update_stock',
        ], true);
    }
}

if (!function_exists('uplar_tracking_track_events')) {
    function uplar_tracking_track_events(PDO $pdo, array $input, ?int $forcedUserId = null): array
    {
        uplar_tracking_ensure_schema($pdo);
        $events = $input['events'] ?? null;
        if (!is_array($events) || array_is_list($events) === false || count($events) < 1 || count($events) > 20) {
            throw new InvalidArgumentException('Envie de 1 a 20 eventos por lote.');
        }

        $session = uplar_tracking_touch_session($pdo, $input, $forcedUserId);
        $userId = $forcedUserId ?? (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
        $defaultPageUrl = uplar_tracking_clean_url($input['page_url'] ?? '');
        $insert = $pdo->prepare(
            "INSERT INTO tracking_events
             (user_id, session_id, device_id, event_name, event_category, request_uuid, page_url, metadata_json, engagement_score, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $results = [];
        $startedTransaction = !$pdo->inTransaction();
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        try {
            foreach ($events as $event) {
                if (!is_array($event)) {
                    throw new InvalidArgumentException('Evento inválido no lote.');
                }
                $eventName = strtolower(uplar_tracking_clean_string($event['event_name'] ?? '', 120));
                if ($eventName === '' || preg_match('/^[a-z0-9:_-]+$/', $eventName) !== 1) {
                    throw new InvalidArgumentException('Nome de evento inválido.');
                }

                $requestUuid = uplar_tracking_is_uuid($event['request_id'] ?? null)
                    ? (string)$event['request_id']
                    : uplar_tracking_uuid();
                $pageUrl = uplar_tracking_clean_url($event['page_url'] ?? '') ?: $defaultPageUrl;
                $category = uplar_tracking_clean_string($event['event_category'] ?? 'custom', 64) ?: 'custom';
                $engagement = uplar_tracking_engagement_score($eventName);
                $metadata = uplar_tracking_clean_array($event['metadata'] ?? []);
                if (!is_array($metadata)) {
                    $metadata = [];
                }
                $metadata['server_time'] = gmdate('c');
                $insert->execute([
                    $userId ?: null,
                    $session['id'],
                    $session['device_id'],
                    $eventName,
                    $category,
                    $requestUuid,
                    $pageUrl ?: null,
                    uplar_tracking_json($metadata),
                    $engagement,
                    uplar_tracking_now(),
                ]);
                $eventId = (int)$pdo->lastInsertId();
                $results[] = [
                    'event_id' => $eventId,
                    'request_id' => $requestUuid,
                    'engagement' => $engagement,
                ];

                if (uplar_tracking_should_enqueue_webhook($eventName)) {
                    uplar_tracking_enqueue_webhook($pdo, $eventName, [
                        'user_id' => $userId,
                        'session_uuid' => $session['session_uuid'],
                        'device_hash' => $session['device_hash'],
                        'metadata' => $metadata,
                    ]);
                }
            }
            if ($startedTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $e) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'event_count' => count($results),
            'events' => $results,
            'session_id' => $session['session_uuid'],
            'device_id' => $session['device_hash'],
        ];
    }
}

if (!function_exists('uplar_tracking_track_event')) {
    function uplar_tracking_track_event(PDO $pdo, array $input, ?int $forcedUserId = null): array
    {
        $batchInput = $input;
        $batchInput['events'] = [[
            'event_name' => $input['event_name'] ?? '',
            'event_category' => $input['event_category'] ?? 'custom',
            'request_id' => $input['request_id'] ?? null,
            'page_url' => $input['page_url'] ?? '',
            'metadata' => $input['metadata'] ?? [],
        ]];
        $result = uplar_tracking_track_events($pdo, $batchInput, $forcedUserId);
        $event = $result['events'][0];
        return $event + [
            'session_id' => $result['session_id'],
            'device_id' => $result['device_id'],
        ];
    }
}

if (!function_exists('uplar_tracking_track_log')) {
    function uplar_tracking_track_log(PDO $pdo, array $input, ?int $forcedUserId = null): int
    {
        uplar_tracking_ensure_schema($pdo);
        $session = uplar_tracking_touch_session($pdo, $input, $forcedUserId);
        $userId = $forcedUserId ?? (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
        $level = strtolower(uplar_tracking_clean_string($input['level'] ?? 'info', 16));
        if (!in_array($level, ['info', 'warning', 'error'], true)) {
            $level = 'info';
        }

        $type = uplar_tracking_clean_string($input['log_type'] ?? 'system', 32) ?: 'system';
        $action = uplar_tracking_clean_string($input['action'] ?? 'unknown', 120) ?: 'unknown';

        $insert = $pdo->prepare(
            "INSERT INTO tracking_logs
             (user_id, session_id, device_id, level, log_type, action, context_json, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $insert->execute([
            $userId ?: null,
            $session['id'],
            $session['device_id'],
            $level,
            $type,
            $action,
            uplar_tracking_json($input['context'] ?? []),
            uplar_tracking_now(),
        ]);

        return (int)$pdo->lastInsertId();
    }
}

if (!function_exists('uplar_tracking_track_error')) {
    function uplar_tracking_track_error(PDO $pdo, array $input, ?int $forcedUserId = null): array
    {
        uplar_tracking_ensure_schema($pdo);

        $session = uplar_tracking_touch_session($pdo, $input, $forcedUserId);
        $userId = $forcedUserId ?? (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null);
        $source = strtolower(uplar_tracking_clean_string($input['source'] ?? 'frontend', 16));
        $severity = strtolower(uplar_tracking_clean_string($input['severity'] ?? 'error', 16));
        $message = uplar_tracking_clean_string($input['message'] ?? 'Erro não informado', 2000);
        $fileUrl = uplar_tracking_clean_url($input['file_url'] ?? '');
        $line = isset($input['line_number']) ? (int)$input['line_number'] : null;
        $column = isset($input['column_number']) ? (int)$input['column_number'] : null;
        $stack = uplar_tracking_clean_string($input['stack_trace'] ?? '', 16000);
        $fingerprint = hash('sha256', implode('|', [$source, $message, $fileUrl, (string)$line, (string)$column]));
        $contextJson = uplar_tracking_json($input['context'] ?? []);
        $now = uplar_tracking_now();

        $find = $pdo->prepare("SELECT id, occurrences FROM tracking_errors WHERE fingerprint = ?");
        $find->execute([$fingerprint]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $update = $pdo->prepare(
                "UPDATE tracking_errors
                 SET user_id = COALESCE(?, user_id),
                     session_id = ?,
                     device_id = ?,
                     severity = ?,
                     message = ?,
                     file_url = ?,
                     line_number = ?,
                     column_number = ?,
                     stack_trace = ?,
                     context_json = ?,
                     occurrences = occurrences + 1,
                     last_seen_at = ?
                 WHERE id = ?"
            );
            $update->execute([
                $userId ?: null,
                $session['id'],
                $session['device_id'],
                $severity,
                $message,
                $fileUrl ?: null,
                $line,
                $column,
                $stack ?: null,
                $contextJson,
                $now,
                $existing['id'],
            ]);

            return ['error_id' => (int)$existing['id'], 'fingerprint' => $fingerprint, 'occurrences' => ((int)$existing['occurrences']) + 1];
        }

        $insert = $pdo->prepare(
            "INSERT INTO tracking_errors
             (user_id, session_id, device_id, source, severity, fingerprint, message, file_url, line_number, column_number, stack_trace, context_json, occurrences, first_seen_at, last_seen_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)"
        );
        $insert->execute([
            $userId ?: null,
            $session['id'],
            $session['device_id'],
            $source,
            $severity,
            $fingerprint,
            $message,
            $fileUrl ?: null,
            $line,
            $column,
            $stack ?: null,
            $contextJson,
            $now,
            $now,
        ]);

        return ['error_id' => (int)$pdo->lastInsertId(), 'fingerprint' => $fingerprint, 'occurrences' => 1];
    }
}

if (!function_exists('uplar_tracking_backend_monitor')) {
    function uplar_tracking_backend_monitor(PDO $pdo, ?int $userId = null): void
    {
        static $registered = false;
        if ($registered) {
            return;
        }

        set_exception_handler(function (Throwable $exception) use ($pdo, $userId) {
            try {
                uplar_tracking_track_error($pdo, [
                    'source'      => 'backend',
                    'severity'    => 'critical',
                    'message'     => $exception->getMessage(),
                    'file_url'    => $exception->getFile(),
                    'line_number' => $exception->getLine(),
                    'stack_trace' => $exception->getTraceAsString(),
                    'context'     => ['type' => get_class($exception)],
                ], $userId);
            } catch (Throwable $trackingError) {
                error_log('Falha ao registrar exceção de tracking: ' . $trackingError->getMessage());
            }

            throw $exception;
        });

        set_error_handler(function ($severity, $message, $file, $line) use ($pdo, $userId) {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            try {
                uplar_tracking_track_error($pdo, [
                    'source'      => 'backend',
                    'severity'    => 'error',
                    'message'     => $message,
                    'file_url'    => $file,
                    'line_number' => $line,
                    'context'     => ['severity' => $severity],
                ], $userId);
            } catch (Throwable $trackingError) {
                error_log('Falha ao registrar erro de tracking: ' . $trackingError->getMessage());
            }

            return false;
        });

        register_shutdown_function(function () use ($pdo, $userId) {
            $fatal = error_get_last();
            if (!$fatal) {
                return;
            }

            $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
            if (!in_array($fatal['type'], $fatalTypes, true)) {
                return;
            }

            try {
                uplar_tracking_track_error($pdo, [
                    'source'      => 'backend',
                    'severity'    => 'critical',
                    'message'     => $fatal['message'] ?? 'Fatal error',
                    'file_url'    => $fatal['file'] ?? '',
                    'line_number' => isset($fatal['line']) ? (int)$fatal['line'] : null,
                    'context'     => ['shutdown' => true],
                ], $userId);
            } catch (Throwable $trackingError) {
                error_log('Falha ao registrar fatal de tracking: ' . $trackingError->getMessage());
            }
        });

        $registered = true;
    }
}

if (!function_exists('uplar_tracking_dashboard_summary')) {
    function uplar_tracking_dashboard_summary(PDO $pdo, int $days = 7): array
    {
        uplar_tracking_ensure_schema($pdo);
        $days = max(1, min($days, 90));

        $summary = [
            'period_days'       => $days,
            'total_events'      => 0,
            'active_users'      => 0,
            'active_sessions'   => 0,
            'top_events'        => [],
            'top_pages'         => [],
            'error_summary'     => [],
            'engagement_rank'   => [],
            'suspicious_users'  => [],
        ];

        $since = uplar_tracking_now(-$days * 86400);

        $summary['total_events'] = (int)$pdo->query(
            "SELECT COUNT(*) FROM tracking_events WHERE created_at >= '{$since}'"
        )->fetchColumn();

        $summary['active_sessions'] = (int)$pdo->query(
            "SELECT COUNT(*) FROM tracking_sessions
             WHERE is_active = 1 AND last_activity_at >= (NOW() - INTERVAL 15 MINUTE)"
        )->fetchColumn();

        $summary['active_users'] = (int)$pdo->query(
            "SELECT COUNT(DISTINCT user_id) FROM tracking_sessions
             WHERE user_id IS NOT NULL AND last_activity_at >= (NOW() - INTERVAL 15 MINUTE)"
        )->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT event_name, COUNT(*) AS total
             FROM tracking_events
             WHERE created_at >= ?
             GROUP BY event_name
             ORDER BY total DESC
             LIMIT 6"
        );
        $stmt->execute([$since]);
        $summary['top_events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare(
            "SELECT last_page AS page_url, COUNT(*) AS total
             FROM tracking_sessions
             WHERE started_at >= ? AND last_page IS NOT NULL AND last_page <> ''
             GROUP BY last_page
             ORDER BY total DESC
             LIMIT 6"
        );
        $stmt->execute([$since]);
        $summary['top_pages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare(
            "SELECT source, severity, SUM(occurrences) AS total
             FROM tracking_errors
             WHERE last_seen_at >= ?
             GROUP BY source, severity
             ORDER BY total DESC"
        );
        $stmt->execute([$since]);
        $summary['error_summary'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare(
            "SELECT u.username, e.user_id, SUM(e.engagement_score) AS score, COUNT(*) AS total_eventos
             FROM tracking_events e
             INNER JOIN users u ON u.id = e.user_id
             WHERE e.user_id IS NOT NULL AND e.created_at >= ?
             GROUP BY e.user_id, u.username
             ORDER BY score DESC, total_eventos DESC
             LIMIT 8"
        );
        $stmt->execute([$since]);
        $summary['engagement_rank'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare(
            "SELECT u.username, s.user_id, COUNT(DISTINCT s.ip_hash) AS total_ips, COUNT(*) AS total_sessoes
             FROM tracking_sessions s
             INNER JOIN users u ON u.id = s.user_id
             WHERE s.user_id IS NOT NULL AND s.started_at >= ?
             GROUP BY s.user_id, u.username
             HAVING COUNT(DISTINCT s.ip_hash) > 1
             ORDER BY total_ips DESC, total_sessoes DESC
             LIMIT 8"
        );
        $stmt->execute([$since]);
        $summary['suspicious_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $summary;
    }
}
