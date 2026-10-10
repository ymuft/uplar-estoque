<?php
// login_logger.php — Inclua no login.php após autenticação bem-sucedida
// Uso: log_login($pdo, $user_id, 'ok');
//      log_login($pdo, $user_id, 'falha');

function parse_device(string $ua): string {
    if (!$ua) return 'Desconhecido';

    // Sistema operacional
    $os = 'Desconhecido';
    if (preg_match('/Windows NT ([\d.]+)/', $ua, $m)) {
        $map = ['10.0'=>'Windows 10/11','6.3'=>'Windows 8.1','6.2'=>'Windows 8','6.1'=>'Windows 7'];
        $os = $map[$m[1]] ?? 'Windows';
    } elseif (preg_match('/Android ([\d.]+)/', $ua, $m)) {
        $os = 'Android '.$m[1];
    } elseif (preg_match('/iPhone OS ([\d_]+)/', $ua, $m)) {
        $os = 'iOS '.str_replace('_','.',$m[1]);
    } elseif (preg_match('/Mac OS X/', $ua)) {
        $os = 'macOS';
    } elseif (preg_match('/Linux/', $ua)) {
        $os = 'Linux';
    }

    // Navegador
    $browser = 'Desconhecido';
    if (preg_match('/Edg\//', $ua))                            $browser = 'Edge';
    elseif (preg_match('/OPR\/|Opera/', $ua))                  $browser = 'Opera';
    elseif (preg_match('/Chrome\/([\d]+)/', $ua, $m))          $browser = 'Chrome '.$m[1];
    elseif (preg_match('/Firefox\/([\d]+)/', $ua, $m))         $browser = 'Firefox '.$m[1];
    elseif (preg_match('/Safari\/([\d]+)/', $ua) &&
            preg_match('/Version\/([\d]+)/', $ua, $m))         $browser = 'Safari '.$m[1];

    // Tipo de dispositivo
    $tipo = 'Desktop';
    if (preg_match('/Mobile|Android|iPhone|iPad/', $ua)) $tipo = 'Mobile';
    if (preg_match('/iPad/', $ua))                        $tipo = 'Tablet';

    return "$tipo · $browser · $os";
}

function log_login(PDO $pdo, int $user_id, string $status = 'ok'): void {
    try {
        // Garante que a tabela existe (failsafe, normalmente já foi criada via migration)
        $pdo->exec("CREATE TABLE IF NOT EXISTS login_logs (
            id          INT NOT NULL AUTO_INCREMENT,
            user_id     INT NOT NULL,
            ip          VARCHAR(45) NOT NULL DEFAULT '',
            user_agent  TEXT,
            dispositivo VARCHAR(255) DEFAULT '',
            status      ENUM('ok','falha') DEFAULT 'ok',
            criado_em   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_ll_user (user_id),
            KEY idx_ll_data (criado_em)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $ip = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? '');

        $ua  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $dev = parse_device($ua);

        $pdo->prepare("
            INSERT INTO login_logs (user_id, ip, user_agent, dispositivo, status)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$user_id, $ip, $ua, $dev, $status]);

    } catch (Throwable $e) {
        // Não quebra o login se o log falhar
        error_log('login_logger erro: '.$e->getMessage());
    }
}
