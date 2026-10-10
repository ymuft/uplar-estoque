<?php
// helpers.php
// Funções auxiliares de segurança (CSRF + escaping XSS)
// Todas as chaves { } estão corretamente fechadas
// Código organizado e comentado

/**
 * Escapa conteúdo para saída HTML (substitui htmlspecialchars de forma segura)
 * @param mixed $string Valor a ser escapado
 * @return string Conteúdo seguro para HTML
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Escapa conteúdo para uso dentro de strings JavaScript (JSON safe)
 * @param mixed $string Valor a ser escapado
 * @return string String segura para JavaScript
 */
function js_escape($string) {
    return json_encode($string, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
}

/**
 * Escapa conteúdo para atributos HTML (value="", title="", alt="", etc)
 * @param mixed $string Valor a ser escapado
 * @return string Conteúdo seguro para atributos
 */
function attr_escape($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Gera ou retorna o token CSRF da sessão atual
 * @return string Token CSRF gerado
 */
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Gera o campo hidden com o token CSRF para formulários
 * @return string HTML do input hidden
 */
function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . e(get_csrf_token()) . '">';
}

/**
 * Verifica se o token CSRF enviado na requisição é válido
 * Usa comparação timing-safe (hash_equals)
 * @return bool True se válido, false caso contrário
 */
function verify_csrf_token() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    $received = $_POST['csrf_token'];
    $expected = $_SESSION['csrf_token'];
    return hash_equals($expected, $received);
}

function get_client_ip(): string {
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
    $trustedProxy = $remoteAddr !== '' && (
        filter_var($remoteAddr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
    );

    $headers = $trustedProxy
        ? ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR']
        : ['REMOTE_ADDR'];

    foreach ($headers as $header) {
        if (empty($_SERVER[$header])) {
            continue;
        }

        $parts = explode(',', (string)$_SERVER[$header]);
        $ip = trim($parts[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }

    return '0.0.0.0';
}

function get_safe_host(): string {
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '' && preg_match('/^[a-z0-9.-]+(?::\d+)?$/i', $host)) {
        return $host;
    }
    return 'uplar.cloud';
}

function validate_uploaded_image(array $file, string $fieldLabel, int $maxSizeBytes = 3145728): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException($fieldLabel . ' inválido.');
    }
    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new RuntimeException($fieldLabel . ' inválido.');
    }
    if (($file['size'] ?? 0) < 1 || (int)$file['size'] > $maxSizeBytes) {
        throw new RuntimeException($fieldLabel . ' excede o limite permitido.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Formato de ' . $fieldLabel . ' inválido.');
    }

    return [
        'mime' => $mime,
        'ext'  => $allowed[$mime],
    ];
}

function save_uploaded_file(array $file, string $targetDir, string $prefix, string $fieldLabel): string {
    $meta = validate_uploaded_image($file, $fieldLabel);
    $baseDir = rtrim(__DIR__ . '/' . trim($targetDir, '/'), '/') . '/';
    if (!is_dir($baseDir) && !mkdir($baseDir, 0755, true)) {
        throw new RuntimeException('Não foi possível preparar o diretório de upload.');
    }

    $filename = uniqid($prefix, true) . '.' . $meta['ext'];
    $relativePath = trim($targetDir, '/') . '/' . $filename;
    $absolutePath = $baseDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $absolutePath)) {
        throw new RuntimeException('Erro ao salvar ' . $fieldLabel . '.');
    }

    @chmod($absolutePath, 0644);
    return $relativePath;
}

function delete_managed_file(?string $relativePath, array $allowedRoots = ['imagens', 'fotos_vendedores', 'logos']): void {
    $relativePath = trim((string)$relativePath);
    if ($relativePath === '') {
        return;
    }

    foreach ($allowedRoots as $root) {
        $prefix = trim($root, '/') . '/';
        if (str_starts_with($relativePath, $prefix)) {
            $fullPath = __DIR__ . '/' . $relativePath;
            $realPath = realpath($fullPath);
            $realRoot = realpath(__DIR__ . '/' . trim($root, '/'));

            if ($realPath && $realRoot && str_starts_with($realPath, $realRoot . DIRECTORY_SEPARATOR) && is_file($realPath)) {
                @unlink($realPath);
            }
            return;
        }
    }
}

/**
 * Envia resposta de erro CSRF para APIs (JSON) e encerra execução
 */
function csrf_error_response() {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => false,
        'error' => 'Token CSRF inválido ou ausente'
    ]);
    exit;
}

// =============================================================================
// ADIÇÕES DE SEGURANÇA - NOVAS FUNÇÕES
// =============================================================================

/**
 * Envia cabeçalhos de segurança importantes em todas as páginas
 * Deve ser chamado no início de cada script PHP (de preferência após session_start)
 */
function send_security_headers() {
    // Proteção contra clickjacking
    header('X-Frame-Options: SAMEORIGIN');
    
    // Bloqueia MIME-type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // Ativa proteção XSS no navegador (modo block)
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com data:; img-src 'self' data: https:; connect-src 'self' https://api.checkout.infinitepay.io; frame-src https://challenges.cloudflare.com; frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'");
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function get_turnstile_config(): array {
    $configFile = getenv('UPLAR_TURNSTILE_CONFIG') ?: __DIR__ . '/.env.turnstile.php';
    $config = [];

    if (is_readable($configFile)) {
        $loaded = require $configFile;
        if (is_array($loaded)) {
            $config = $loaded;
        }
    }

    return [
        'site_key'   => (string)(getenv('UPLAR_TURNSTILE_SITE_KEY') ?: ($config['site_key'] ?? '')),
        'secret_key' => (string)(getenv('UPLAR_TURNSTILE_SECRET_KEY') ?: ($config['secret_key'] ?? '')),
    ];
}

function verify_turnstile_token(string $token, string $remoteIp): bool {
    $token = trim($token);
    $config = get_turnstile_config();

    if ($token === '' || strlen($token) > 2048 || $config['secret_key'] === '') {
        return false;
    }

    $curl = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    if ($curl === false) {
        return false;
    }

    curl_setopt_array($curl, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => $config['secret_key'],
            'response' => $token,
            'remoteip' => $remoteIp,
        ]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT        => 4,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);

    $response = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if (!is_string($response) || $status !== 200) {
        return false;
    }

    $result = json_decode($response, true);
    if (!is_array($result) || ($result['success'] ?? false) !== true) {
        return false;
    }

    $expectedHost = strtolower(preg_replace('/:\\d+$/', '', get_safe_host()));
    $verifiedHost = strtolower((string)($result['hostname'] ?? ''));
    $verifiedAction = (string)($result['action'] ?? '');

    return $verifiedHost === $expectedHost && $verifiedAction === 'login';
}

/**
 * Registra eventos de segurança em arquivo (login falho, CSRF, etc)
 * Cria arquivo security.log com permissões restritas
 * @param string $message Mensagem do evento
 * @param string $level (opcional) info | warning | danger
 */
function security_log($message, $level = 'info') {
    $log_file = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'uplar-security.log';

    $timestamp = date('Y-m-d H:i:s');
    $sanitize = static function ($value, int $maxLength): string {
        $value = preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string)$value);
        return substr(trim($value), 0, $maxLength);
    };

    $level = $sanitize($level, 16);
    $ip = $sanitize($_SERVER['REMOTE_ADDR'] ?? 'unknown', 64);
    $user_agent = $sanitize($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 500);
    $message = $sanitize($message, 1000);

    $line = "[$timestamp] [$level] IP: $ip | UA: $user_agent | $message\n";

    $handle = fopen($log_file, 'c+');
    if ($handle === false) {
        return;
    }

    @chmod($log_file, 0600);
    if (flock($handle, LOCK_EX)) {
        $stats = fstat($handle);
        if (($stats['size'] ?? 0) >= 5 * 1024 * 1024) {
            ftruncate($handle, 0);
            rewind($handle);
        } else {
            fseek($handle, 0, SEEK_END);
        }
        fwrite($handle, $line);
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);
}

/**
 * Verifica se a sessão ainda é válida (timeout de inatividade)
 * Recomendado chamar no início de páginas protegidas
 * @param int $max_inactive_time Tempo máximo em segundos (padrão: 30 minutos)
 * @return bool True se sessão ainda válida
 */
function is_session_valid($max_inactive_time = 1800) {
    if (!isset($_SESSION['last_activity'])) {
        return false;
    }
    
    $time_since_last = time() - $_SESSION['last_activity'];
    
    if ($time_since_last > $max_inactive_time) {
        // Sessão expirada por inatividade
        session_destroy();
        security_log("Sessão expirada por inatividade", "warning");
        return false;
    }
    
    // Atualiza o timestamp da última atividade
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Força logout seguro (destrói sessão e limpa cookies)
 */
function force_logout() {
    // Limpa dados da sessão
    $_SESSION = [];
    
    // Destroi a sessão
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
    
    security_log("Logout forçado / sessão destruída");
}

/**
 * Estados de autenticação. Sessões legadas, criadas antes do Pass Code,
 * continuam plenamente autenticadas para não interromper usuários ativos.
 */
function passcode_is_pending(): bool {
    return ($_SESSION['auth_state'] ?? '') === 'passcode_pending';
}

function passcode_setup_is_pending(): bool {
    return ($_SESSION['auth_state'] ?? '') === 'passcode_setup_pending';
}

function is_fully_authenticated(): bool {
    return !empty($_SESSION['user_id'])
        && !empty($_SESSION['username'])
        && isset($_SESSION['auth_version'])
        && !passcode_is_pending()
        && !passcode_setup_is_pending();
}

function reject_invalid_session(): void {
    force_logout();
    $script = basename((string)parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH));
    if ($script === 'api.php' || str_ends_with($script, '_api.php') || str_ends_with($script, '_action.php')) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Sessão inválida. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Location: login.php?error=' . urlencode('Sessão inválida. Faça login novamente.'));
    exit;
}

function enforce_session_binding(): void {
    if (empty($_SESSION['user_id']) || empty($_SESSION['_ip']) || empty($_SESSION['_ua_hash'])) {
        return;
    }

    $sameIp = hash_equals((string)$_SESSION['_ip'], get_client_ip());
    $uaHash = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $sameUa = hash_equals((string)$_SESSION['_ua_hash'], $uaHash);
    if ($sameIp && $sameUa) {
        return;
    }

    reject_invalid_session();
}

function enforce_auth_version(): void {
    if (empty($_SESSION['user_id'])) {
        return;
    }

    $sessionVersion = filter_var($_SESSION['auth_version'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($sessionVersion === false || !function_exists('get_pdo')) {
        reject_invalid_session();
    }

    try {
        $stmt = get_pdo()->prepare('SELECT auth_version FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$_SESSION['user_id']]);
        $currentVersion = (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('Falha ao validar versão de autenticação: ' . $e->getMessage());
        reject_invalid_session();
    }

    if ($currentVersion < 1 || !hash_equals((string)$sessionVersion, (string)$currentVersion)) {
        reject_invalid_session();
    }
}

/** Bloqueia centralmente qualquer rota privada durante a segunda etapa. */
function enforce_passcode_pending_access(): void {
    enforce_session_binding();
    enforce_auth_version();
    if (!empty($_SESSION['user_id'])) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    if (!passcode_is_pending() && !passcode_setup_is_pending()) {
        return;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');

    $script = basename((string)parse_url($_SERVER['SCRIPT_NAME'] ?? '', PHP_URL_PATH));
    $allowed = ['passcode.php', 'passcode_action.php', 'logout.php'];
    if (in_array($script, $allowed, true)) {
        return;
    }

    $isApi = $script === 'api.php' || str_ends_with($script, '_api.php') || str_ends_with($script, '_action.php');
    if ($isApi) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Confirme seu Pass Code para continuar.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: passcode.php');
    exit;
}

// helpers.php é carregado no início dos endpoints privados. A checagem aqui
// evita depender de dezenas de guards duplicados em páginas e APIs.
enforce_passcode_pending_access();
