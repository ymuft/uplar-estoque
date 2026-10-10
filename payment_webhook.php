<?php
// payment_webhook.php — Webhook InfinityPay
require __DIR__ . '/db.php';
require __DIR__ . '/subscription.php';
require __DIR__ . '/payment_verification.php';

header('Content-Type: application/json');
const INFINITEPAY_HANDLE = 'ymuft';
const INFINITEPAY_API = 'https://api.checkout.infinitepay.io';

function infinitepay_handle(): string { return getenv('INFINITYPAY_HANDLE') ?: INFINITEPAY_HANDLE; }
function infinitepay_api_base(): string { return rtrim(getenv('INFINITYPAY_API') ?: INFINITEPAY_API, '/'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método inválido']);
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);

if (empty($data) || !is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload inválido']);
    exit;
}

$order_nsu = is_string($data['order_nsu'] ?? null) ? strtoupper(trim($data['order_nsu'])) : '';
$transaction_nsu = is_string($data['transaction_nsu'] ?? null) ? trim($data['transaction_nsu']) : '';
$invoice_slug = is_string($data['invoice_slug'] ?? null) ? trim($data['invoice_slug']) : '';
$paid_amount = $data['paid_amount'] ?? null;

if (!$order_nsu || !preg_match('/^UPL-[A-F0-9]{16}$/', $order_nsu)) {
    error_log('[webhook] order_nsu inválido ou ausente');
    echo json_encode(['ok' => true, 'info' => 'sem order_nsu válido']);
    exit;
}

if ($transaction_nsu === '' || strlen($transaction_nsu) > 128 || $invoice_slug === '' || strlen($invoice_slug) > 128) {
    error_log("[webhook] Identificadores do gateway inválidos ou ausentes, order_nsu=$order_nsu");
    http_response_code(400);
    echo json_encode(['error' => 'Identificadores do pagamento inválidos']);
    exit;
}

if (!is_int($paid_amount) || $paid_amount <= 0) {
    error_log("[webhook] paid_amount inválido ou ausente, order_nsu=$order_nsu");
    http_response_code(400);
    echo json_encode(['error' => 'Valor pago inválido']);
    exit;
}

try {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT * FROM pedidos_pagamento WHERE order_nsu = ? LIMIT 1');
    $stmt->execute([$order_nsu]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        error_log("[webhook] Pedido não encontrado: $order_nsu");
        echo json_encode(['ok' => true, 'info' => 'pedido não encontrado']);
        exit;
    }

    $invoice_slug_esperado = trim((string)($pedido['invoice_slug'] ?? ''));
    if ($invoice_slug_esperado !== '' && !hash_equals($invoice_slug_esperado, $invoice_slug)) {
        error_log("[webhook] invoice_slug divergente, order_nsu=$order_nsu");
        http_response_code(400);
        echo json_encode(['error' => 'Pagamento não confirmado']);
        exit;
    }
    if ($invoice_slug_esperado === '') {
        $invoice_slug_esperado = $invoice_slug;
    }

    $valor_esperado = (int)$pedido['valor_centavos'];
    if ($valor_esperado <= 0 || $paid_amount < $valor_esperado) {
        error_log("[webhook] ALERTA valor insuficiente — esperado {$pedido['valor_centavos']}, recebido $paid_amount, order_nsu=$order_nsu");
        http_response_code(400);
        echo json_encode(['error' => 'Valor insuficiente']);
        exit;
    }

    $check_payload = json_encode([
        'handle' => infinitepay_handle(),
        'order_nsu' => $order_nsu,
        'transaction_nsu' => $transaction_nsu,
        'slug' => $invoice_slug_esperado,
    ]);

    $ch = curl_init(infinitepay_api_base() . '/payment_check');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $check_payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $check_response = curl_exec($ch);
    $check_http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $check_error = curl_error($ch);
    curl_close($ch);

    $check_result = is_string($check_response) ? json_decode($check_response, true) : null;
    $pagamento_confirmado = infinitepay_payment_check_confirms(
        $check_result,
        $check_http_code,
        $check_error,
        $valor_esperado,
        $paid_amount
    );

    if (!$pagamento_confirmado) {
        error_log("[webhook] payment_check não confirmou o pagamento, order_nsu=$order_nsu, HTTP=$check_http_code");
        http_response_code(400);
        echo json_encode(['error' => 'Pagamento não confirmado']);
        exit;
    }

    $capture_method = is_string($check_result['capture_method'] ?? null)
        ? trim($check_result['capture_method'])
        : '';

    $installments = is_int($check_result['installments'] ?? null) && $check_result['installments'] > 0
        ? $check_result['installments']
        : null;
    if ($installments !== null && $installments > 99) $installments = null;
    if (strlen($capture_method) > 32) $capture_method = '';
    $receipt_url = is_string($data['receipt_url'] ?? null) ? trim($data['receipt_url']) : '';
    $receipt_parts = $receipt_url !== '' ? parse_url($receipt_url) : false;
    $receipt_host = is_array($receipt_parts) ? strtolower((string)($receipt_parts['host'] ?? '')) : '';
    $valid_receipt_host = $receipt_host === 'recibo.infinitepay.io'
        || str_ends_with($receipt_host, '.infinitepay.io')
        || str_ends_with($receipt_host, '.infinitepay.com.br');
    if (strlen($receipt_url) > 512
        || !is_array($receipt_parts)
        || strtolower((string)($receipt_parts['scheme'] ?? '')) !== 'https'
        || !$valid_receipt_host
    ) {
        $receipt_url = '';
    }

    $items = [];
    $items_total = 0;
    if (is_array($data['items'] ?? null) && count($data['items']) <= 50) {
        foreach ($data['items'] as $item) {
            if (!is_array($item)) continue;
            $quantity = $item['quantity'] ?? null;
            $price = $item['price'] ?? null;
            $description = is_string($item['description'] ?? null) ? trim($item['description']) : '';
            if (!is_int($quantity) || $quantity < 1 || $quantity > 1000 || !is_int($price) || $price < 0) continue;
            $items_total += $quantity * $price;
            $items[] = [
                'quantity' => $quantity,
                'price' => $price,
                'description' => function_exists('mb_substr') ? mb_substr($description, 0, 200) : substr($description, 0, 200),
            ];
        }
    }
    $items_json = $items && $items_total === $valor_esperado
        ? json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : null;

    $pdo->prepare("UPDATE pedidos_pagamento
        SET status = 'pago', pago_em = COALESCE(pago_em, NOW()), transaction_nsu = ?, invoice_slug = ?,
            gateway_amount_centavos = ?, paid_amount_centavos = ?, installments = ?, capture_method = ?,
            receipt_url = COALESCE(NULLIF(?, ''), receipt_url), items_json = COALESCE(?, items_json)
        WHERE order_nsu = ?")
        ->execute([
            $transaction_nsu,
            $invoice_slug_esperado,
            (int)$check_result['amount'],
            (int)$check_result['paid_amount'],
            $installments,
            $capture_method ?: null,
            $receipt_url,
            $items_json,
            $order_nsu,
        ]);

    if (!empty($pedido['user_id'])) {
        $user_id = (int)$pedido['user_id'];
        if (!pedido_pagamento_ja_ativado($order_nsu, $user_id, $pdo)) {
            $obs = "InfinityPay — order_nsu: {$order_nsu}";
            if ($capture_method !== '') $obs .= " — método: {$capture_method}";
            if ($transaction_nsu !== '') $obs .= " — NSU: {$transaction_nsu}";
            if ($invoice_slug !== '') $obs .= " — invoice: {$invoice_slug}";
            renovar_inscricao($user_id, (int)$pedido['plano_dias'], 0, $pdo, $obs, (string)($pedido['categoria_plano'] ?? 'basico'));
        }
    }

    error_log("[webhook] Pedido $order_nsu processado como pago.");
    http_response_code(200);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    error_log('[webhook] ERRO: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno']);
}
