<?php
// db.php - Conexão PDO centralizada (recomendado)

function get_pdo() {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $configFile = getenv('UPLAR_DB_CONFIG') ?: __DIR__ . '/.env.database.php';
            $config = [];
            if (is_readable($configFile)) {
                $loaded = require $configFile;
                if (is_array($loaded)) {
                    $config = $loaded;
                }
            }

            $dbHost = (string)(getenv('UPLAR_DB_HOST') ?: ($config['host'] ?? ''));
            $dbName = (string)(getenv('UPLAR_DB_NAME') ?: ($config['name'] ?? ''));
            $dbUser = (string)(getenv('UPLAR_DB_USER') ?: ($config['user'] ?? ''));
            $dbPass = (string)(getenv('UPLAR_DB_PASS') ?: ($config['password'] ?? ''));

            if ($dbHost === '' || $dbName === '' || $dbUser === '' || $dbPass === '') {
                throw new RuntimeException('Configuração de banco ausente.');
            }

            $pdo = new PDO(
                "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
                $dbUser,
                $dbPass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false, // Força true prepared statements
                ]
            );
        } catch (Throwable $e) {
            error_log("Erro de conexão PDO: " . $e->getMessage());
            die("Erro interno no servidor. Contate o administrador.");
        }
    }

    return $pdo;
}
