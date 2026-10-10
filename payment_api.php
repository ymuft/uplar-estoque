<?php
// payment_api.php — Backend de integração com InfinityPay
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/subscription.php';
require __DIR__ . '/planos_config.php';
require __DIR__ . '/payment_verification.php';

header('Content-Type: application/json; charset=utf-8');
send_security_headers();
error_reporting(E_ALL);
ini_set('display_errors', '0');

register_shutdown_function(function () {
    $e = error_get_last();
    if (!$e) return;
    if (!in_array($e['type'] ?? 0, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) return;
    if (headers_sent()) return;
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erro interno no servidor.']);
});

function ok(array $d = []): void { echo json_encode(['ok' => true] + $d); exit; }
function apierr(string $m, int $c = 400): void { http_response_code($c); echo json_encode(['ok' => false, 'error' => $m]); exit; }

const IP_HANDLE = 'ymuft';
const IP_API = 'https://api.checkout.infinitepay.io';
const SITE_BASE = 'https://estoque.uplar.cloud';

function ip_handle(): string { return getenv('INFINITYPAY_HANDLE') ?: IP_HANDLE; }
function ip_api_base(): string { return getenv('INFINITYPAY_API') ?: IP_API; }
function site_base_url(): string { return rtrim(getenv('UPLAR_SITE_BASE') ?: SITE_BASE, '/'); }
function ensurePedidosPagamentoTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS pedidos_pagamento (
        id              INT AUTO_INCREMENT PRIMARY KEY,
        order_nsu       VARCHAR(64)   NOT NULL UNIQUE,
        plano_key       VARCHAR(20)   NOT NULL,
        plano_dias      INT           NOT NULL,
        valor_centavos  INT           NOT NULL,
        status          ENUM('pendente','pago','expirado','cancelado') DEFAULT 'pendente',
        user_id         INT           DEFAULT NULL,
        checkout_url    TEXT          DEFAULT NULL,
        invoice_slug    VARCHAR(128)  DEFAULT NULL,
        transaction_nsu VARCHAR(128)  DEFAULT NULL,
        gateway_amount_centavos INT   DEFAULT NULL,
        paid_amount_centavos INT      DEFAULT NULL,
        installments    SMALLINT UNSIGNED DEFAULT NULL,
        capture_method  VARCHAR(32)   DEFAULT NULL,
        receipt_url     VARCHAR(512)  DEFAULT NULL,
        items_json      TEXT          DEFAULT NULL,
        admin_oculto    TINYINT(1)    NOT NULL DEFAULT 0,
        admin_oculto_em DATETIME      DEFAULT NULL,
        admin_oculto_por INT          DEFAULT NULL,
        criado_em       TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
        pago_em         TIMESTAMP     NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $column = $pdo->query("SHOW COLUMNS FROM pedidos_pagamento LIKE 'categoria_plano'")->fetch(PDO::FETCH_ASSOC);
    if (!$column) {
        $pdo->exec("ALTER TABLE pedidos_pagamento ADD COLUMN categoria_plano VARCHAR(20) NOT NULL DEFAULT 'basico' AFTER plano_key");
    }
}

function getCurrentUserForCheckout(PDO $pdo): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $stmt = $pdo->prepare("SELECT id, username, role, plano FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([(int)$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if (!$user) return null;
    $user['id'] = (int)$user['id'];
    return $user;
}

function activatePaidOrder(PDO $pdo, array $pedido, string $origem, string $transactionNsu = '', string $captureMethod = ''): void {
    $userId = (int)($pedido['user_id'] ?? 0);
    if ($userId < 1) return;
    $orderNsu = trim($pedido['order_nsu'] ?? '');
    if ($orderNsu === '') return;
    if (pedido_pagamento_ja_ativado($orderNsu, $userId, $pdo)) return;

    $obs = "InfinityPay — order_nsu: {$orderNsu}";
    if ($captureMethod !== '') $obs .= " — método: {$captureMethod}";
    if ($transactionNsu !== '') $obs .= " — NSU: {$transactionNsu}";
    if ($origem !== '') $obs .= " — origem: {$origem}";

    renovar_inscricao($userId, (int)$pedido['plano_dias'], 0, $pdo, $obs, (string)($pedido['categoria_plano'] ?? 'basico'));
}

$acao = $_GET['acao'] ?? '';
$pdo = get_pdo();
ensurePedidosPagamentoTable($pdo);

if ($acao === 'criar_link') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') apierr('Método inválido', 405);
    if (!verify_csrf_token()) apierr('Token inválido', 403);

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rl_key = 'rl_checkout_' . md5($ip);
    if (!isset($_SESSION[$rl_key])) $_SESSION[$rl_key] = ['count' => 0, 'since' => time()];
    if (time() - $_SESSION[$rl_key]['since'] > 600) $_SESSION[$rl_key] = ['count' => 0, 'since' => time()];
    $_SESSION[$rl_key]['count']++;
    if ($_SESSION[$rl_key]['count'] > 10) apierr('Muitas requisições. Tente novamente em alguns minutos.', 429);

    $plano = resolver_plano_categoria($pdo, trim($_POST['plano'] ?? ''));
    if (!$plano || !$plano['ativo'] || (int)$plano['preco'] <= 0) apierr('Plano indisponível.');
    $plano_key = (string)$plano['codigo'];

    $user = getCurrentUserForCheckout($pdo);
    if (!$user) {
        apierr('Crie sua conta ou faça login antes de pagar.', 401);
    }
    if (($user['role'] ?? '') === 'admin') {
        apierr('Administradores não precisam de assinatura.', 403);
    }

    $order_nsu = 'UPL-' . strtoupper(bin2hex(random_bytes(8)));
    $pdo->prepare("INSERT INTO pedidos_pagamento
        (order_nsu, plano_key, categoria_plano, plano_dias, valor_centavos, user_id)
        VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$order_nsu, $plano_key, $plano['categoria'], $plano['dias'], $plano['preco'], $user['id']]);

    $_SESSION['pending_order_nsu'] = $order_nsu;
    $_SESSION['pending_plano_key'] = $plano_key;
    $_SESSION['pending_plano_categoria'] = $plano['categoria'];
    $_SESSION['pending_plano_dias'] = $plano['dias'];

    $payload = [
        'handle'       => ip_handle(),
        'order_nsu'    => $order_nsu,
        'redirect_url' => site_base_url() . '/pagamento_retorno.php',
        'webhook_url'  => site_base_url() . '/payment_webhook.php',
        'items'        => [[
            'quantity'    => 1,
            'price'       => $plano['preco'],
            'description' => $plano['label'],
        ]],
    ];

    $ch = curl_init(ip_api_base() . '/links');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($curlErr) {
        error_log("[payment_api] cURL: $curlErr");
        apierr('Erro de conexão com o gateway.', 503);
    }

    $data = json_decode($resp, true);
    if ($httpCode < 200 || $httpCode >= 300) {
        error_log("[payment_api] HTTP $httpCode: $resp");
        apierr('Erro ao gerar link de pagamento.', 502);
    }

    $checkout_url = $data['url'] ?? $data['checkout_url'] ?? $data['link'] ?? null;
    $slug_salvo = $data['slug'] ?? $data['invoice_slug'] ?? null;
    if ((!is_string($slug_salvo) || trim($slug_salvo) === '') && is_string($checkout_url)) {
        $query = parse_url($checkout_url, PHP_URL_QUERY);
        if (is_string($query)) {
            parse_str($query, $checkout_params);
            $slug_salvo = $checkout_params['lenc'] ?? $checkout_params['slug'] ?? $checkout_params['invoice_slug'] ?? null;
        }
    }
    if (is_string($slug_salvo)) {
        $slug_salvo = trim($slug_salvo);
        if ($slug_salvo === '' || strlen($slug_salvo) > 128 || !preg_match('/^[A-Za-z0-9_-]+$/', $slug_salvo)) {
            $slug_salvo = null;
        }
    } else {
        $slug_salvo = null;
    }
    if (!$checkout_url && $slug_salvo) {
        $checkout_url = 'https://checkout.infinitepay.io/' . ip_handle() . '?lenc=' . $slug_salvo;
    }
    if (!$checkout_url) {
        error_log("[payment_api] Sem URL na resposta: $resp");
        apierr('Resposta inesperada do gateway.', 502);
    }

    $pdo->prepare("UPDATE pedidos_pagamento SET checkout_url = ?, invoice_slug = ? WHERE order_nsu = ?")
        ->execute([$checkout_url, $slug_salvo, $order_nsu]);

    ok(['checkout_url' => $checkout_url, 'order_nsu' => $order_nsu]);
}

if ($acao === 'verificar_pagamento') {
    $order_nsu = $_SESSION['pending_order_nsu'] ?? trim($_GET['order_nsu'] ?? '');
    if (!$order_nsu) apierr('Sessão expirada. Volte e tente novamente.', 404);
    if (!preg_match('/^UPL-[A-F0-9]{16}$/i', $order_nsu)) apierr('Pedido inválido.', 400);

    $stmt = $pdo->prepare("SELECT * FROM pedidos_pagamento WHERE order_nsu = ? LIMIT 1");
    $stmt->execute([$order_nsu]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) apierr('Pedido não encontrado.', 404);

    $sessionUserId = (int)($_SESSION['user_id'] ?? 0);
    if ($sessionUserId > 0 && (int)($pedido['user_id'] ?? 0) > 0 && (int)$pedido['user_id'] !== $sessionUserId) {
        apierr('Pedido não pertence ao usuário autenticado.', 403);
    }

    if ($pedido['status'] === 'pago') {
        activatePaidOrder($pdo, $pedido, 'polling', (string)($pedido['transaction_nsu'] ?? ''));
        $_SESSION['pagamento_confirmado'] = true;
        $_SESSION['pagamento_order_nsu'] = $order_nsu;
        $_SESSION['pagamento_plano_key'] = $pedido['plano_key'];
        $_SESSION['pagamento_plano_dias'] = $pedido['plano_dias'];
        ok([
            'pago' => true,
            'plano_key' => $pedido['plano_key'],
            'plano_dias' => (int)$pedido['plano_dias'],
            'user_id' => (int)($pedido['user_id'] ?? 0),
        ]);
    }

    $slug = trim((string)($pedido['invoice_slug'] ?? ''));
    $saved_transaction_nsu = trim((string)($pedido['transaction_nsu'] ?? ''));
    if ($slug !== '' && $saved_transaction_nsu !== '') {
        $check_payload = json_encode([
            'handle'    => ip_handle(),
            'order_nsu' => $order_nsu,
            'transaction_nsu' => $saved_transaction_nsu,
            'slug'      => $slug,
        ]);

        $ch = curl_init(ip_api_base() . '/payment_check');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $check_payload,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $resp = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            error_log("[payment_api] payment_check cURL: $curlErr");
        } else {
            $result = json_decode($resp, true);
            if (infinitepay_payment_check_confirms(
                $result,
                $httpCode,
                '',
                (int)$pedido['valor_centavos']
            )) {
                $transaction_nsu = $saved_transaction_nsu;
                $capture_method = is_string($result['capture_method'] ?? null)
                    ? trim($result['capture_method'])
                    : '';
                if (strlen($capture_method) > 32) $capture_method = '';
                $installments = is_int($result['installments'] ?? null)
                    && $result['installments'] > 0
                    && $result['installments'] <= 99
                    ? $result['installments']
                    : null;
                $pdo->prepare("UPDATE pedidos_pagamento
                    SET status = 'pago', pago_em = COALESCE(pago_em, NOW()), transaction_nsu = ?, invoice_slug = ?,
                        gateway_amount_centavos = ?, paid_amount_centavos = ?, installments = ?, capture_method = ?
                    WHERE order_nsu = ?")
                    ->execute([
                        $transaction_nsu,
                        $slug,
                        (int)$result['amount'],
                        (int)$result['paid_amount'],
                        $installments,
                        $capture_method ?: null,
                        $order_nsu,
                    ]);

                $stmt = $pdo->prepare("SELECT * FROM pedidos_pagamento WHERE order_nsu = ? LIMIT 1");
                $stmt->execute([$order_nsu]);
                $pedido = $stmt->fetch(PDO::FETCH_ASSOC) ?: $pedido;
                activatePaidOrder($pdo, $pedido, 'polling', $transaction_nsu, $capture_method);

                $_SESSION['pagamento_confirmado'] = true;
                $_SESSION['pagamento_order_nsu'] = $order_nsu;
                $_SESSION['pagamento_plano_key'] = $pedido['plano_key'];
                $_SESSION['pagamento_plano_dias'] = $pedido['plano_dias'];

                ok([
                    'pago' => true,
                    'plano_key' => $pedido['plano_key'],
                    'plano_dias' => (int)$pedido['plano_dias'],
                    'user_id' => (int)($pedido['user_id'] ?? 0),
                ]);
            }
        }
    }

    ok(['pago' => false]);
}

apierr('Ação inválida', 400);
