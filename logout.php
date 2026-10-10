<?php
/**
 * logout.php - Logout ultra-seguro e blindado
 * Proteções:
 * - Aceita somente POST com CSRF válido
 * - Limpeza total + regeneração de ID + cookie kill
 * - Headers anti-cache + segurança
 * - Log de auditoria
 */
session_start();
require 'helpers.php';

// Headers anti-cache + segurança (impede redirecionamento errado ou uso de página cacheada)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");
send_security_headers();

// Logout é uma ação de estado: nunca aceite GET.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    security_log("Tentativa de logout inválida: método {$_SERVER['REQUEST_METHOD']}", "warning");
    header("Location: login.php?error=acesso_invalido");
    exit;
}

if (!verify_csrf_token()) {
    security_log("Tentativa de logout com CSRF inválido", "danger");
    header("Location: login.php?error=requisicao_invalida");
    exit;
}

// Auditoria antes de destruir
$username = $_SESSION['username'] ?? 'desconhecido';
$ip = get_client_ip();
security_log("Logout realizado - usuário: $username | IP: $ip | Método: {$_SERVER['REQUEST_METHOD']}");

// Limpeza total
$_SESSION = [];

// Mata cookie de sessão
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Regenera ID (defesa profunda)
session_regenerate_id(true);

// Destrói sessão
session_destroy();

// Redireciona pro login sem cache
header("Location: login.php?logout=success");
exit;
