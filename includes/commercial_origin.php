<?php

/** Origem comercial compartilhada por pedidos, vendas e relatórios. */
function commercial_origin_normalize($origin): string
{
    $origin = strtolower(trim((string)$origin));
    return in_array($origin, ['vitrine', 'venda_direta', 'whatsapp', 'site_pro', 'legacy'], true)
        ? $origin
        : 'legacy';
}

function commercial_origin_label(string $origin): string
{
    return [
        'vitrine' => 'Vitrine',
        'venda_direta' => 'Venda direta',
        'whatsapp' => 'WhatsApp',
        'site_pro' => 'Site PRO',
        'legacy' => 'Desconhecida',
    ][commercial_origin_normalize($origin)] ?? 'Desconhecida';
}

function commercial_origin_ensure_schema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;

    $saleColumn = $pdo->query("SHOW COLUMNS FROM vendas LIKE 'origem'")->fetchColumn();
    $salesColumnAdded = false;
    if (!$saleColumn) {
        $pdo->exec("ALTER TABLE vendas ADD COLUMN origem VARCHAR(20) NULL DEFAULT NULL AFTER vendedor_id");
        $salesColumnAdded = true;
    }

    $orderColumn = $pdo->query("SHOW COLUMNS FROM vitrine_pedidos LIKE 'origem'")->fetchColumn();
    if (!$orderColumn) {
        $pdo->exec("ALTER TABLE vitrine_pedidos ADD COLUMN origem VARCHAR(20) NULL DEFAULT NULL AFTER user_id");
    }

    $saleIndex = $pdo->query("SHOW INDEX FROM vendas WHERE Key_name = 'idx_vendas_user_origem_data'")->fetchColumn();
    if (!$saleIndex) {
        $pdo->exec("ALTER TABLE vendas ADD INDEX idx_vendas_user_origem_data (user_id, origem, criado_em)");
    }

    if ($salesColumnAdded) {
        // Backfill conservador: só herda origem de pedidos cuja origem já é conhecida.
        $pdo->exec("UPDATE vendas v
            INNER JOIN vitrine_pedidos p ON p.id = v.pedido_vitrine_id AND p.user_id = v.user_id
            SET v.origem = p.origem
            WHERE (v.origem IS NULL OR v.origem = '')
              AND p.origem IN ('vitrine', 'whatsapp', 'site_pro')");
        $pdo->exec("UPDATE vendas SET origem = 'legacy' WHERE origem IS NULL OR origem = ''");
    }

    $ready = true;
}
