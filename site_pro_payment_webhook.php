<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/site_pro_commerce_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

$rawBody = file_get_contents('php://input', false, null, 0, 65537);
if (!is_string($rawBody) || strlen($rawBody) > 65536) {
    http_response_code(413);
    echo json_encode(['ok' => false]);
    exit;
}
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

$orderNsu = trim((string)($payload['order_nsu'] ?? ''));
$transactionNsu = trim((string)($payload['transaction_nsu'] ?? ''));
$invoiceSlug = trim((string)($payload['invoice_slug'] ?? $payload['slug'] ?? ''));
$receiptUrl = trim((string)($payload['receipt_url'] ?? ''));
$reportedPaidAmount = filter_var($payload['paid_amount'] ?? null, FILTER_VALIDATE_INT);
if (!preg_match('/^SP-\d+-[A-F0-9]{16}$/', $orderNsu)
    || $transactionNsu === ''
    || $invoiceSlug === ''
    || $reportedPaidAmount === false
) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

try {
    $pdo = get_pdo();
    site_pro_commerce_ensure_schema($pdo);
    $stmt = $pdo->prepare('SELECT * FROM site_pro_pagamentos WHERE order_nsu = ? LIMIT 1');
    $stmt->execute([$orderNsu]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$payment) {
        http_response_code(404);
        echo json_encode(['ok' => false]);
        exit;
    }
    if ((string)$payment['status'] === 'pago') {
        echo json_encode(['ok' => true]);
        exit;
    }

    $confirmed = site_pro_verify_infinitepay_payment(
        $pdo,
        $payment,
        $transactionNsu,
        $invoiceSlug,
        $receiptUrl,
        (int)$reportedPaidAmount
    );
    http_response_code($confirmed ? 200 : 400);
    echo json_encode(['ok' => $confirmed]);
} catch (Throwable $e) {
    error_log('[site pro webhook] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
