<?php

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/site_pro_commerce_helpers.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');

$orderNsu = trim((string)($_GET['order_nsu'] ?? ''));
$transactionNsu = trim((string)($_GET['transaction_nsu'] ?? ''));
$invoiceSlug = trim((string)($_GET['invoice_slug'] ?? $_GET['slug'] ?? ''));
$receiptUrl = trim((string)($_GET['receipt_url'] ?? ''));
$state = 'processing';
$title = 'Confirmando seu pagamento';
$message = 'Estamos consultando a InfinitePay. Isso costuma levar apenas alguns segundos.';
$storeUrl = '/';
$safeReceiptUrl = '';

try {
    if (!preg_match('/^SP-\d+-[A-F0-9]{16}$/', $orderNsu)) {
        throw new InvalidArgumentException('Pagamento não identificado.');
    }
    $pdo = get_pdo();
    site_pro_commerce_ensure_schema($pdo);
    $stmt = $pdo->prepare('
        SELECT sp.*, u.vitrine_slug
        FROM site_pro_pagamentos sp
        JOIN users u ON u.id = sp.user_id
        WHERE sp.order_nsu = ?
        LIMIT 1
    ');
    $stmt->execute([$orderNsu]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$payment) {
        throw new RuntimeException('Pagamento não encontrado.');
    }
    if (!empty($payment['vitrine_slug'])) {
        $storeUrl = '/' . rawurlencode((string)$payment['vitrine_slug']);
    }

    if ((string)$payment['status'] !== 'pago' && $transactionNsu !== '' && $invoiceSlug !== '') {
        site_pro_verify_infinitepay_payment(
            $pdo,
            $payment,
            $transactionNsu,
            $invoiceSlug,
            $receiptUrl
        );
        $stmt = $pdo->prepare('SELECT * FROM site_pro_pagamentos WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$payment['id']]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC) ?: $payment;
    }

    if ((string)$payment['status'] === 'pago') {
        $state = 'success';
        $title = 'Pagamento confirmado';
        $message = 'Seu pedido foi aprovado e já entrou na fila de preparação da loja.';
        $safeReceiptUrl = site_pro_valid_receipt_url((string)($payment['receipt_url'] ?: $receiptUrl));
    } else {
        $title = 'Pagamento em processamento';
        $message = 'A confirmação ainda não chegou. Você pode voltar à loja; o pedido será atualizado automaticamente quando a InfinitePay confirmar.';
    }
} catch (Throwable $e) {
    $state = 'error';
    $title = 'Não conseguimos confirmar agora';
    $message = 'Volte à loja e confira seu pedido. Se o valor foi cobrado, a confirmação ainda pode acontecer automaticamente.';
    error_log('[site pro payment return] ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= htmlspecialchars($title) ?> · Uplar</title>
    <style>
        *{box-sizing:border-box}
        body{min-height:100vh;display:grid;place-items:center;margin:0;padding:24px;background:#f3f5f7;color:#18202a;font-family:Inter,system-ui,sans-serif}
        .card{width:min(480px,100%);padding:32px;border:1px solid #e0e5ea;border-radius:14px;background:#fff;box-shadow:0 18px 48px rgba(19,32,47,.1);text-align:center}
        .mark{width:58px;height:58px;display:grid;place-items:center;margin:0 auto 20px;border-radius:50%;background:#fff4db;color:#9b6700;font-size:27px;font-weight:800}
        .success .mark{background:#e4f7ed;color:#137348}.error .mark{background:#fde9e7;color:#ad2f29}
        h1{margin:0;font-size:24px;letter-spacing:-.03em}p{margin:12px 0 24px;color:#657080;font-size:14px;line-height:1.6}
        .actions{display:grid;gap:9px}.button{display:block;padding:12px 16px;border:1px solid #18202a;border-radius:8px;background:#18202a;color:#fff;text-decoration:none;font-size:14px;font-weight:750}
        .button.secondary{background:#fff;color:#18202a}
        .note{margin-top:18px;color:#8a94a1;font-size:11px}
    </style>
</head>
<body>
    <main class="card <?= htmlspecialchars($state) ?>">
        <div class="mark"><?= $state === 'success' ? '✓' : ($state === 'error' ? '!' : '…') ?></div>
        <h1><?= htmlspecialchars($title) ?></h1>
        <p><?= htmlspecialchars($message) ?></p>
        <div class="actions">
            <a class="button" href="<?= htmlspecialchars($storeUrl) ?>">Voltar para a loja</a>
            <?php if ($safeReceiptUrl !== ''): ?>
                <a class="button secondary" href="<?= htmlspecialchars($safeReceiptUrl) ?>" target="_blank" rel="noopener noreferrer">Ver comprovante</a>
            <?php endif; ?>
        </div>
        <div class="note">Pagamento processado com segurança pela InfinitePay.</div>
    </main>
</body>
</html>
