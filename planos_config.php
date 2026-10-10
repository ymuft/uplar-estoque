<?php

const PLANOS_PADRAO = [
    'mensal' => ['dias' => 30, 'preco_centavos' => 1990, 'label' => 'Plano Mensal — Uplar'],
    'trimestral' => ['dias' => 90, 'preco_centavos' => 5490, 'label' => 'Plano Trimestral — Uplar'],
    'anual' => ['dias' => 365, 'preco_centavos' => 19900, 'label' => 'Plano Anual — Uplar'],
];

const CATEGORIAS_PLANO = ['basico', 'pro'];

const PLANOS_PRO_PADRAO = [
    'mensal' => ['preco_centavos' => 2985, 'label' => 'Plano PRO Mensal — Uplar'],
    'trimestral' => ['preco_centavos' => 8235, 'label' => 'Plano PRO Trimestral — Uplar'],
    'anual' => ['preco_centavos' => 29850, 'label' => 'Plano PRO Anual — Uplar'],
];

function ensure_planos_categoria_config_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS planos_categoria_config (
        categoria VARCHAR(20) NOT NULL,
        plano_key VARCHAR(20) NOT NULL,
        preco_centavos INT UNSIGNED NOT NULL DEFAULT 0,
        label VARCHAR(100) NOT NULL,
        ativo TINYINT(1) NOT NULL DEFAULT 0,
        atualizado_por INT NULL DEFAULT NULL,
        atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (categoria, plano_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmt = $pdo->prepare("INSERT IGNORE INTO planos_categoria_config
        (categoria, plano_key, preco_centavos, label, ativo)
        VALUES ('pro', ?, ?, ?, 1)");
    foreach (PLANOS_PRO_PADRAO as $key => $plano) {
        $stmt->execute([$key, $plano['preco_centavos'], $plano['label']]);
    }

    $repair = $pdo->prepare("UPDATE planos_categoria_config
        SET preco_centavos = ?, label = ?, ativo = 1
        WHERE categoria = 'pro' AND plano_key = ? AND preco_centavos = 0 AND ativo = 0");
    foreach (PLANOS_PRO_PADRAO as $key => $plano) {
        $repair->execute([$plano['preco_centavos'], $plano['label'], $key]);
    }
}

function get_planos_por_categoria(PDO $pdo): array
{
    $basicos = get_planos_config($pdo);
    ensure_planos_categoria_config_table($pdo);
    $planos = [];
    foreach ($basicos as $key => $plano) {
        $planos['basico_' . $key] = [
            'codigo' => 'basico_' . $key, 'categoria' => 'basico', 'plano_key' => $key,
            'dias' => (int)$plano['dias'], 'preco' => (int)$plano['preco'],
            'label' => (string)$plano['label'], 'ativo' => true,
        ];
    }
    $stmt = $pdo->query("SELECT categoria, plano_key, preco_centavos, label, ativo
        FROM planos_categoria_config WHERE categoria = 'pro'");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $plano) {
        $key = (string)$plano['plano_key'];
        if (!isset($basicos[$key])) continue;
        $planos['pro_' . $key] = [
            'codigo' => 'pro_' . $key, 'categoria' => 'pro', 'plano_key' => $key,
            'dias' => (int)$basicos[$key]['dias'], 'preco' => (int)$plano['preco_centavos'],
            'label' => (string)$plano['label'], 'ativo' => (bool)$plano['ativo'],
        ];
    }
    return $planos;
}

function resolver_plano_categoria(PDO $pdo, string $codigo): ?array
{
    $codigo = trim($codigo);
    if (isset(PLANOS_PADRAO[$codigo])) $codigo = 'basico_' . $codigo;
    $planos = get_planos_por_categoria($pdo);
    return $planos[$codigo] ?? null;
}

function ensure_planos_config_table(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS planos_config (
        plano_key VARCHAR(20) NOT NULL PRIMARY KEY,
        dias INT NOT NULL,
        preco_centavos INT UNSIGNED NOT NULL,
        label VARCHAR(100) NOT NULL,
        atualizado_por INT NULL DEFAULT NULL,
        atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $stmt = $pdo->prepare("INSERT IGNORE INTO planos_config (plano_key, dias, preco_centavos, label) VALUES (?, ?, ?, ?)");
    foreach (PLANOS_PADRAO as $key => $plano) {
        $stmt->execute([$key, $plano['dias'], $plano['preco_centavos'], $plano['label']]);
    }
}

function get_planos_config(PDO $pdo): array
{
    ensure_planos_config_table($pdo);
    $rows = $pdo->query("SELECT plano_key, dias, preco_centavos, label, atualizado_em FROM planos_config ORDER BY FIELD(plano_key, 'mensal', 'trimestral', 'anual')")->fetchAll(PDO::FETCH_ASSOC);
    $planos = [];
    foreach ($rows as $row) {
        $key = (string)$row['plano_key'];
        if (!isset(PLANOS_PADRAO[$key])) continue;
        $planos[$key] = [
            'dias' => (int)$row['dias'],
            'preco' => (int)$row['preco_centavos'],
            'preco_centavos' => (int)$row['preco_centavos'],
            'label' => (string)$row['label'],
            'atualizado_em' => $row['atualizado_em'] ?? null,
        ];
    }
    return $planos;
}

function formatar_preco_plano(int $centavos): string
{
    return 'R$ ' . number_format($centavos / 100, 2, ',', '.');
}

function preco_plano_para_centavos(string $valor): int
{
    $normalizado = str_replace(',', '.', trim($valor));
    if (!preg_match('/^\\d{1,7}(?:\\.\\d{1,2})?$/', $normalizado)) return 0;
    return (int)round((float)$normalizado * 100);
}

function atualizar_precos_planos(PDO $pdo, array $precos, int $adminId): void
{
    $stmt = $pdo->prepare("UPDATE planos_config SET preco_centavos = ?, atualizado_por = ? WHERE plano_key = ?");
    foreach (PLANOS_PADRAO as $key => $plano) {
        $centavos = (int)($precos[$key] ?? 0);
        if ($centavos < 100 || $centavos > 10000000) {
            throw new InvalidArgumentException('Valor inválido para o plano ' . $key . '.');
        }
        $stmt->execute([$centavos, $adminId, $key]);
    }
}
