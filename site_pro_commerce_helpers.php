<?php

require_once __DIR__ . '/payment_verification.php';
require_once __DIR__ . '/includes/commercial_origin.php';
require_once __DIR__ . '/includes/product_sizes.php';
require_once __DIR__ . '/includes/product_complements.php';

function site_pro_commerce_ensure_schema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS site_pro_cupons (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id INT NOT NULL,
        codigo VARCHAR(32) NOT NULL,
        desconto_percentual DECIMAL(5,2) NOT NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_site_pro_cupom (user_id, codigo),
        KEY idx_site_pro_cupom_ativo (user_id, ativo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS site_pro_pagamentos (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        pedido_id INT NOT NULL,
        user_id INT NOT NULL,
        order_nsu VARCHAR(64) NOT NULL,
        infinitepay_tag VARCHAR(80) NOT NULL,
        valor_centavos INT UNSIGNED NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pendente',
        checkout_url TEXT NULL,
        invoice_slug VARCHAR(128) NULL,
        transaction_nsu VARCHAR(128) NULL,
        gateway_amount_centavos INT UNSIGNED NULL,
        paid_amount_centavos INT UNSIGNED NULL,
        installments SMALLINT UNSIGNED NULL,
        capture_method VARCHAR(32) NULL,
        receipt_url VARCHAR(512) NULL,
        criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        pago_em TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_site_pro_pagamento_pedido (pedido_id),
        UNIQUE KEY uq_site_pro_pagamento_nsu (order_nsu),
        KEY idx_site_pro_pagamento_user (user_id, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $orderColumns = [
        'cupom_codigo' => "VARCHAR(32) NULL AFTER valor_frete",
        'desconto' => "DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER cupom_codigo",
        'pagamento_status' => "VARCHAR(20) NOT NULL DEFAULT 'nao_aplicavel' AFTER desconto",
        'pagamento_metodo' => "VARCHAR(32) NULL AFTER pagamento_status",
    ];
    foreach ($orderColumns as $name => $definition) {
        $exists = $pdo->query("SHOW COLUMNS FROM vitrine_pedidos LIKE " . $pdo->quote($name))->fetchColumn();
        if (!$exists) {
            $pdo->exec("ALTER TABLE vitrine_pedidos ADD COLUMN {$name} {$definition}");
        }
    }

    $ready = true;
}

function site_pro_normalize_coupon_code(string $code): string
{
    return strtoupper(trim($code));
}

function site_pro_find_coupon(PDO $pdo, int $userId, string $code, bool $onlyActive = true): ?array
{
    site_pro_commerce_ensure_schema($pdo);
    $normalized = site_pro_normalize_coupon_code($code);
    if (!preg_match('/^[A-Z0-9_-]{3,32}$/', $normalized)) {
        return null;
    }
    $sql = 'SELECT id, codigo, desconto_percentual, ativo FROM site_pro_cupons WHERE user_id = ? AND codigo = ?';
    if ($onlyActive) {
        $sql .= ' AND ativo = 1';
    }
    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId, $normalized]);
    $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$coupon) {
        return null;
    }
    $coupon['id'] = (int)$coupon['id'];
    $coupon['desconto_percentual'] = (float)$coupon['desconto_percentual'];
    $coupon['ativo'] = (int)$coupon['ativo'] === 1;
    return $coupon;
}

function site_pro_apply_coupon(float $subtotal, ?array $coupon): array
{
    $subtotal = max(0, round($subtotal, 2));
    $percentage = $coupon ? max(0, min(100, (float)$coupon['desconto_percentual'])) : 0;
    $discount = round($subtotal * ($percentage / 100), 2);
    return [
        'subtotal' => $subtotal,
        'desconto' => min($subtotal, $discount),
        'percentual' => $percentage,
        'codigo' => $coupon['codigo'] ?? '',
        'total_produtos' => max(0, round($subtotal - $discount, 2)),
    ];
}

function site_pro_infinitepay_api_base(): string
{
    return rtrim(getenv('INFINITYPAY_API') ?: 'https://api.checkout.infinitepay.io', '/');
}

function site_pro_commerce_base_url(): string
{
    return rtrim(getenv('UPLAR_SITE_BASE') ?: 'https://estoque.uplar.cloud', '/');
}

function site_pro_http_post_json(string $url, array $payload, int $timeout = 15): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $body = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'status' => $status,
        'error' => $error,
        'body' => is_string($body) ? $body : '',
        'data' => is_string($body) ? json_decode($body, true) : null,
    ];
}

function site_pro_valid_checkout_url(string $url): bool
{
    if (strlen($url) > 2048) {
        return false;
    }
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
        return false;
    }
    $host = strtolower((string)($parts['host'] ?? ''));
    return $host === 'checkout.infinitepay.io'
        || str_ends_with($host, '.infinitepay.io')
        || str_ends_with($host, '.infinitepay.com.br');
}

function site_pro_valid_receipt_url(string $url): string
{
    if ($url === '' || strlen($url) > 512) {
        return '';
    }
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
        return '';
    }
    $host = strtolower((string)($parts['host'] ?? ''));
    $valid = $host === 'recibo.infinitepay.io'
        || str_ends_with($host, '.infinitepay.io')
        || str_ends_with($host, '.infinitepay.com.br');
    return $valid ? $url : '';
}

function site_pro_create_infinitepay_checkout(
    PDO $pdo,
    int $orderId,
    int $userId,
    string $handle,
    int $amountCents,
    string $storeName
): array {
    site_pro_commerce_ensure_schema($pdo);
    if (!preg_match('/^[a-z0-9._-]{2,80}$/', $handle) || $amountCents < 1) {
        throw new InvalidArgumentException('Configuração de pagamento inválida.');
    }

    $orderNsu = 'SP-' . $userId . '-' . strtoupper(bin2hex(random_bytes(8)));
    $pdo->prepare('INSERT INTO site_pro_pagamentos
        (pedido_id, user_id, order_nsu, infinitepay_tag, valor_centavos)
        VALUES (?, ?, ?, ?, ?)')
        ->execute([$orderId, $userId, $orderNsu, $handle, $amountCents]);

    $payload = [
        'handle' => $handle,
        'order_nsu' => $orderNsu,
        'redirect_url' => site_pro_commerce_base_url() . '/site_pro_pagamento_retorno.php',
        'webhook_url' => site_pro_commerce_base_url() . '/site_pro_payment_webhook.php',
        'items' => [[
            'quantity' => 1,
            'price' => $amountCents,
            'description' => mb_substr('Pedido #' . $orderId . ' — ' . $storeName, 0, 180),
        ]],
    ];
    try {
        $response = site_pro_http_post_json(site_pro_infinitepay_api_base() . '/links', $payload);
        if ($response['error'] !== '' || $response['status'] < 200 || $response['status'] >= 300 || !is_array($response['data'])) {
            error_log('[site_pro checkout] Falha InfinitePay HTTP ' . $response['status'] . ': ' . $response['error']);
            throw new RuntimeException('Não foi possível abrir o pagamento agora.');
        }

        $data = $response['data'];
        $checkoutUrl = trim((string)($data['url'] ?? $data['checkout_url'] ?? $data['link'] ?? ''));
        $invoiceSlug = trim((string)($data['slug'] ?? $data['invoice_slug'] ?? ''));
        if (!site_pro_valid_checkout_url($checkoutUrl)) {
            error_log('[site_pro checkout] URL inesperada da InfinitePay.');
            throw new RuntimeException('A InfinitePay retornou um endereço de pagamento inválido.');
        }
        if ($invoiceSlug !== '' && (strlen($invoiceSlug) > 128 || !preg_match('/^[A-Za-z0-9_-]+$/', $invoiceSlug))) {
            $invoiceSlug = '';
        }

        $pdo->prepare('UPDATE site_pro_pagamentos SET checkout_url = ?, invoice_slug = ? WHERE order_nsu = ?')
            ->execute([$checkoutUrl, $invoiceSlug ?: null, $orderNsu]);

        return ['checkout_url' => $checkoutUrl, 'order_nsu' => $orderNsu];
    } catch (Throwable $e) {
        $pdo->prepare("DELETE FROM site_pro_pagamentos WHERE order_nsu = ? AND status = 'pendente'")
            ->execute([$orderNsu]);
        throw $e;
    }
}

function site_pro_confirm_paid_order(
    PDO $pdo,
    array $payment,
    array $checkResult,
    string $transactionNsu,
    string $invoiceSlug,
    string $receiptUrl = ''
): void {
    commercial_origin_ensure_schema($pdo);
    product_sizes_ensure_schema($pdo);
    $complementsReady = uplar_complementos_schema_disponivel($pdo);
    $paymentId = (int)$payment['id'];
    $orderId = (int)$payment['pedido_id'];
    $userId = (int)$payment['user_id'];
    $captureMethod = is_string($checkResult['capture_method'] ?? null)
        ? mb_substr(trim((string)$checkResult['capture_method']), 0, 32)
        : '';
    $installments = is_int($checkResult['installments'] ?? null)
        && $checkResult['installments'] > 0
        && $checkResult['installments'] <= 99
        ? $checkResult['installments']
        : null;

    $pdo->beginTransaction();
    try {
        $lockPayment = $pdo->prepare('SELECT status FROM site_pro_pagamentos WHERE id = ? FOR UPDATE');
        $lockPayment->execute([$paymentId]);
        $currentStatus = (string)$lockPayment->fetchColumn();

        $pdo->prepare("UPDATE site_pro_pagamentos
            SET status = 'pago', pago_em = COALESCE(pago_em, NOW()), transaction_nsu = ?, invoice_slug = ?,
                gateway_amount_centavos = ?, paid_amount_centavos = ?, installments = ?, capture_method = ?,
                receipt_url = COALESCE(NULLIF(?, ''), receipt_url)
            WHERE id = ?")
            ->execute([
                $transactionNsu,
                $invoiceSlug,
                (int)$checkResult['amount'],
                (int)$checkResult['paid_amount'],
                $installments,
                $captureMethod ?: null,
                site_pro_valid_receipt_url($receiptUrl),
                $paymentId,
            ]);

        $orderStmt = $pdo->prepare('SELECT * FROM vitrine_pedidos WHERE id = ? AND user_id = ? FOR UPDATE');
        $orderStmt->execute([$orderId, $userId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            throw new RuntimeException('Pedido do pagamento não encontrado.');
        }

        $pdo->prepare("UPDATE vitrine_pedidos
            SET status = 'confirmado', pagamento_status = 'pago', pagamento_metodo = ?
            WHERE id = ? AND user_id = ?")
            ->execute([$captureMethod ?: null, $orderId, $userId]);

        $saleCheck = $pdo->prepare('SELECT COUNT(*) FROM vendas WHERE pedido_vitrine_id = ? AND user_id = ?');
        $saleCheck->execute([$orderId, $userId]);
        if ((int)$saleCheck->fetchColumn() === 0) {
            $items = json_decode((string)$order['itens_json'], true);
            foreach (is_array($items) ? $items : [] as $item) {
                $productId = (int)($item['produto_id'] ?? 0);
                $quantity = (int)($item['quantidade'] ?? 0);
                $unitPrice = (float)($item['preco'] ?? 0);
                $size = isset($item['tamanho']) && $item['tamanho'] !== null && $item['tamanho'] !== '' ? product_sizes_normalize($item['tamanho']) : null;
                if ($productId < 1 || $quantity < 1 || $unitPrice < 0) {
                    continue;
                }
                $productStmt = $pdo->prepare('SELECT quantidade, preco_custo, usa_tamanhos FROM produtos WHERE id = ? AND user_id = ? FOR UPDATE');
                $productStmt->execute([$productId, $userId]);
                $product = $productStmt->fetch(PDO::FETCH_ASSOC);
                if (!$product) {
                    continue;
                }
                if ((int)$product['usa_tamanhos'] && $size === null) continue;
                if (!(int)$product['usa_tamanhos'] && $size !== null) continue;
                if ($size !== null) {
                    $deduct = $pdo->prepare('UPDATE produto_tamanhos SET quantidade = quantidade - ? WHERE produto_id = ? AND tamanho = ? AND ativo = 1 AND quantidade >= ?');
                    $deduct->execute([$quantity, $productId, $size, $quantity]);
                    if ($deduct->rowCount() !== 1) continue;
                    product_sizes_sync_total($pdo, $productId, $userId);
                } elseif ((int)$product['quantidade'] < $quantity) {
                    continue;
                }

                $personalization = is_array($item['personalizacao'] ?? null) ? $item['personalizacao'] : null;
                $baseCostCents = is_numeric($personalization['custo_base_centavos'] ?? null)
                    ? (int)$personalization['custo_base_centavos']
                    : (int)round((float)$product['preco_custo'] * 100);
                $additionalCostCents = is_numeric($personalization['custo_adicional_centavos'] ?? null)
                    ? (int)$personalization['custo_adicional_centavos']
                    : 0;
                $costComplete = $baseCostCents > 0 && (!$personalization || !empty($personalization['custos_completos']));
                $unitCost = ($baseCostCents + $additionalCostCents) / 100;
                $saleValues = [
                        $userId,
                        $productId,
                        $size,
                        commercial_origin_normalize($order['origem'] ?? 'site_pro'),
                        $quantity,
                        $unitPrice,
                        $unitCost,
                        "Pagamento InfinitePay — Pedido Site PRO #{$orderId}",
                        $orderId,
                    ];
                if ($complementsReady) {
                    $pdo->prepare('INSERT INTO vendas
                        (user_id, produto_id, tamanho, vendedor_id, origem, quantidade, preco_unitario, custo_unitario, observacao, pedido_vitrine_id, personalizacao_json, custo_completo)
                        VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?)')
                        ->execute([...$saleValues, $personalization ? json_encode($personalization, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null, $costComplete ? 1 : 0]);
                } else {
                    $pdo->prepare('INSERT INTO vendas
                        (user_id, produto_id, tamanho, vendedor_id, origem, quantidade, preco_unitario, custo_unitario, observacao, pedido_vitrine_id)
                        VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?)')->execute($saleValues);
                }
                if ($size === null) {
                    $pdo->prepare('UPDATE produtos SET quantidade = GREATEST(0, quantidade - ?) WHERE id = ? AND user_id = ?')
                        ->execute([$quantity, $productId, $userId]);
                }
            }
        }

        $shipping = (float)($order['valor_frete'] ?? 0);
        if ($shipping > 0) {
            $expenseObservation = "Pagamento InfinitePay — Frete do Pedido Site PRO #{$orderId}";
            $pdo->prepare("INSERT INTO despesas
                (pedido_vitrine_id, user_id, descricao, categoria, tipo, valor, data_despesa, status, observacao)
                VALUES (?, ?, ?, 'Frete', 'variavel', ?, ?, 'pago', ?)
                ON DUPLICATE KEY UPDATE descricao = VALUES(descricao), valor = VALUES(valor), data_despesa = VALUES(data_despesa), status = 'pago', observacao = VALUES(observacao)")
                ->execute([
                    $orderId,
                    $userId,
                    "Frete — Pedido Site PRO #{$orderId}",
                    $shipping,
                    date('Y-m-d'),
                    $expenseObservation,
                ]);
        }

        $pdo->commit();
        if ($currentStatus !== 'pago') {
            error_log("[site_pro payment] Pedido #{$orderId} confirmado.");
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function site_pro_verify_infinitepay_payment(
    PDO $pdo,
    array $payment,
    string $transactionNsu,
    string $invoiceSlug,
    string $receiptUrl = '',
    ?int $reportedPaidAmount = null
): bool {
    if ($transactionNsu === '' || strlen($transactionNsu) > 128
        || $invoiceSlug === '' || strlen($invoiceSlug) > 128
        || !preg_match('/^[A-Za-z0-9_-]+$/', $invoiceSlug)
    ) {
        return false;
    }
    $savedSlug = trim((string)($payment['invoice_slug'] ?? ''));
    if ($savedSlug !== '' && !hash_equals($savedSlug, $invoiceSlug)) {
        return false;
    }

    $payload = [
        'handle' => (string)$payment['infinitepay_tag'],
        'order_nsu' => (string)$payment['order_nsu'],
        'transaction_nsu' => $transactionNsu,
        'slug' => $invoiceSlug,
    ];
    $response = site_pro_http_post_json(site_pro_infinitepay_api_base() . '/payment_check', $payload, 8);
    $result = $response['data'];
    $confirmed = infinitepay_payment_check_confirms(
        $result,
        $response['status'],
        $response['error'],
        (int)$payment['valor_centavos'],
        $reportedPaidAmount
    );
    if (!$confirmed) {
        return false;
    }
    site_pro_confirm_paid_order($pdo, $payment, $result, $transactionNsu, $invoiceSlug, $receiptUrl);
    return true;
}
