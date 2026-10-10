<?php
/**
 * Complementos de produto.
 *
 * Este arquivo nunca cria ou altera tabelas em tempo de execução. A migration
 * correspondente deve ser aplicada antes de habilitar a administração.
 */

function uplar_complementos_schema_instalado(PDO $pdo): bool
{
    static $installed = null;
    if ($installed !== null) return $installed;

    foreach (['produto_complemento_grupos', 'produto_complemento_opcoes', 'produto_complemento_vinculos'] as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
        if (!$stmt->fetchColumn()) return $installed = false;
    }
    foreach ([
        'vitrine_carrinho' => ['personalizacao_hash', 'personalizacao_json'],
        'vendas' => ['personalizacao_json', 'custo_completo'],
    ] as $table => $columns) {
        foreach ($columns as $column) {
            $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE " . $pdo->quote($column));
            if (!$stmt->fetchColumn()) return $installed = false;
        }
    }
    return $installed = true;
}

function uplar_complementos_schema_disponivel(PDO $pdo): bool
{
    return uplar_complementos_schema_instalado($pdo);
}

function uplar_complementos_exigir_schema(PDO $pdo): void
{
    if (!uplar_complementos_schema_instalado($pdo)) {
        throw new RuntimeException('A funcionalidade de complementos ainda não foi instalada. Aplique a migration antes de usá-la.');
    }
}

function uplar_complementos_texto($value, string $field, int $max, bool $required = false): string
{
    $value = trim(preg_replace('/\s+/u', ' ', (string)$value));
    if ($required && $value === '') throw new InvalidArgumentException("Informe {$field}.");
    if (mb_strlen($value) > $max) throw new InvalidArgumentException("{$field} excede o limite de {$max} caracteres.");
    return $value;
}

function uplar_complementos_cents($value): int
{
    $raw = trim(str_replace(',', '.', (string)$value));
    if ($raw === '') return 0;
    if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/D', $raw, $m)) {
        throw new InvalidArgumentException('Preço adicional inválido.');
    }
    $whole = (int)$m[1];
    $decimal = isset($m[2]) ? str_pad($m[2], 2, '0') : '00';
    if ($whole > 99999999) throw new InvalidArgumentException('Preço adicional muito alto.');
    return $whole * 100 + (int)$decimal;
}

function uplar_complementos_money(int $cents): string
{
    return number_format($cents / 100, 2, '.', '');
}

/** Identidade estável da personalização no carrinho, sem depender de preço ou nome. */
function uplar_complementos_hash(array $snapshot): string
{
    $groups = $snapshot['grupos'] ?? [];
    $normalized = [];
    if (is_array($groups)) {
        foreach ($groups as $group) {
            $groupId = (int)($group['grupo_id'] ?? 0);
            if ($groupId < 1) continue;
            $options = [];
            foreach (($group['opcoes'] ?? []) as $option) {
                $optionId = (int)($option['opcao_id'] ?? 0);
                $quantity = (int)($option['quantidade'] ?? 0);
                if ($optionId > 0 && $quantity > 0) $options[$optionId] = $quantity;
            }
            ksort($options, SORT_NUMERIC);
            $normalized[$groupId] = $options;
        }
    }
    ksort($normalized, SORT_NUMERIC);
    $observation = trim(preg_replace('/\s+/u', ' ', (string)($snapshot['observacao'] ?? '')));
    return hash('sha256', json_encode(['grupos' => $normalized, 'observacao' => $observation], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function uplar_complementos_produto_do_dono(PDO $pdo, int $ownerId, int $productId): array
{
    if ($ownerId < 1 || $productId < 1) throw new InvalidArgumentException('Produto inválido.');
    $stmt = $pdo->prepare('SELECT id, preco, preco_custo FROM produtos WHERE id = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$productId, $ownerId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$product) throw new RuntimeException('Produto não encontrado.');
    return $product;
}

/** Lista somente a configuração ativa que pode ser exibida ao cliente. */
function uplar_complementos_listar(PDO $pdo, int $ownerId, int $productId): array
{
    if (!uplar_complementos_schema_instalado($pdo)) return [];
    uplar_complementos_produto_do_dono($pdo, $ownerId, $productId);

    $groupsStmt = $pdo->prepare(
        'SELECT g.id, g.nome, g.descricao, g.minimo, g.maximo, v.ordem
         FROM produto_complemento_vinculos v
         INNER JOIN produto_complemento_grupos g ON g.id = v.grupo_id AND g.user_id = v.user_id
         WHERE v.user_id = ? AND v.produto_id = ? AND v.ativo = 1 AND g.ativo = 1
         ORDER BY v.ordem ASC, v.id ASC'
    );
    $groupsStmt->execute([$ownerId, $productId]);
    $groups = $groupsStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$groups) return [];

    $optionStmt = $pdo->prepare(
        'SELECT id, nome, preco_adicional, custo_adicional, max_quantidade, ordem
         FROM produto_complemento_opcoes
         WHERE user_id = ? AND grupo_id = ? AND ativo = 1
         ORDER BY ordem ASC, id ASC'
    );
    foreach ($groups as &$group) {
        $optionStmt->execute([$ownerId, (int)$group['id']]);
        $group['id'] = (int)$group['id'];
        $group['minimo'] = (int)$group['minimo'];
        $group['maximo'] = (int)$group['maximo'];
        $group['ordem'] = (int)$group['ordem'];
        $group['opcoes'] = [];
        foreach ($optionStmt->fetchAll(PDO::FETCH_ASSOC) as $option) {
            $option['id'] = (int)$option['id'];
            $option['max_quantidade'] = (int)$option['max_quantidade'];
            $option['ordem'] = (int)$option['ordem'];
            $option['preco_adicional'] = number_format((float)$option['preco_adicional'], 2, '.', '');
            $group['opcoes'][] = $option;
        }
    }
    unset($group);
    return $groups;
}

/** Forma pública da configuração: nunca entrega preço de custo ao comprador. */
function uplar_complementos_publicar_grupos(array $groups): array
{
    foreach ($groups as &$group) {
        unset($group['user_id']);
        if (!is_array($group['opcoes'] ?? null)) continue;
        foreach ($group['opcoes'] as &$option) {
            unset($option['custo_adicional']);
        }
        unset($option);
    }
    unset($group);
    return $groups;
}

/** Remove os custos internos antes de devolver snapshots do carrinho ao navegador. */
function uplar_complementos_publicar_snapshot(array $snapshot): array
{
    unset($snapshot['custo_adicional_centavos'], $snapshot['custo_base_centavos'], $snapshot['custo_unitario_centavos'], $snapshot['custos_completos']);
    if (!is_array($snapshot['grupos'] ?? null)) return $snapshot;
    foreach ($snapshot['grupos'] as &$group) {
        if (!is_array($group['opcoes'] ?? null)) continue;
        foreach ($group['opcoes'] as &$option) {
            unset($option['custo_unitario_centavos']);
        }
        unset($option);
    }
    unset($group);
    return $snapshot;
}

/**
 * Confere associação, disponibilidade, limites e preços no servidor.
 * Nenhum preço vindo do navegador integra o retorno.
 */
function uplar_complementos_validar(PDO $pdo, int $ownerId, int $productId, array $submitted): array
{
    uplar_complementos_exigir_schema($pdo);
    $product = uplar_complementos_produto_do_dono($pdo, $ownerId, $productId);
    $config = uplar_complementos_listar($pdo, $ownerId, $productId);
    return uplar_complementos_calcular_configuracao($product, $config, $submitted);
}

/** Calculadora compartilhada pela vitrine e prévia administrativa; sem gravação. */
function uplar_complementos_calcular_configuracao(array $product, array $config, array $submitted): array
{
    $submittedGroups = $submitted['grupos'] ?? null;
    if (!is_array($submittedGroups) || count($submittedGroups) > 30) throw new InvalidArgumentException('Quantidade de grupos inválida.');
    $observacao = uplar_complementos_texto($submitted['observacao'] ?? '', 'Observação', 500);

    $byGroup = [];
    foreach ($config as $group) $byGroup[$group['id']] = $group;
    $selectedByGroup = [];

    foreach ($submittedGroups as $entry) {
        if (!is_array($entry)) throw new InvalidArgumentException('Dados de complementos inválidos.');
        $groupId = (int)($entry['grupo_id'] ?? 0);
        if (!$groupId || !isset($byGroup[$groupId]) || isset($selectedByGroup[$groupId])) {
            throw new InvalidArgumentException('Grupo de complemento inválido.');
        }
        $rawOptions = $entry['opcoes'] ?? [];
        if (!is_array($rawOptions) || count($rawOptions) > 100) throw new InvalidArgumentException('Opções de complemento inválidas.');
        $byOption = [];
        foreach ($byGroup[$groupId]['opcoes'] as $option) $byOption[$option['id']] = $option;
        $chosen = [];
        foreach ($rawOptions as $rawOption) {
            if (!is_array($rawOption)) throw new InvalidArgumentException('Opção de complemento inválida.');
            $optionId = (int)($rawOption['opcao_id'] ?? 0);
            $quantity = (int)($rawOption['quantidade'] ?? 0);
            if (!$optionId || !isset($byOption[$optionId]) || isset($chosen[$optionId]) || $quantity < 1) {
                throw new InvalidArgumentException('Opção de complemento inválida.');
            }
            if ($quantity > $byOption[$optionId]['max_quantidade']) {
                throw new InvalidArgumentException('A quantidade escolhida para "' . $byOption[$optionId]['nome'] . '" ultrapassa o limite permitido.');
            }
            $chosen[$optionId] = $quantity;
        }
        $selectedByGroup[$groupId] = $chosen;
    }

    $snapshotGroups = [];
    $extraCents = 0;
    $extraCostCents = 0;
    $extraCostsComplete = true;
    $hasKnownExtraCost = false;
    foreach ($config as $group) {
        $chosen = $selectedByGroup[$group['id']] ?? [];
        $units = array_sum($chosen);
        if ($units < $group['minimo'] || $units > $group['maximo']) {
            throw new InvalidArgumentException('Escolha entre ' . $group['minimo'] . ' e ' . $group['maximo'] . ' opção(ões) em "' . $group['nome'] . '".');
        }
        if (!$chosen) continue;
        $options = [];
        foreach ($group['opcoes'] as $option) {
            $quantity = $chosen[$option['id']] ?? 0;
            if (!$quantity) continue;
            $unitCents = uplar_complementos_cents($option['preco_adicional']);
            $subtotalCents = $unitCents * $quantity;
            $unitCostCents = null;
            if ($option['custo_adicional'] !== null && $option['custo_adicional'] !== '') {
                $unitCostCents = uplar_complementos_cents($option['custo_adicional']);
                $extraCostCents += $unitCostCents * $quantity;
                $hasKnownExtraCost = true;
            } else {
                $extraCostsComplete = false;
            }
            $extraCents += $subtotalCents;
            $options[] = [
                'opcao_id' => $option['id'], 'nome' => $option['nome'], 'quantidade' => $quantity,
                'preco_unitario' => uplar_complementos_money($unitCents), 'preco_unitario_centavos' => $unitCents,
                'subtotal' => uplar_complementos_money($subtotalCents), 'subtotal_centavos' => $subtotalCents,
                'custo_unitario_centavos' => $unitCostCents,
            ];
        }
        $snapshotGroups[] = ['grupo_id' => $group['id'], 'nome' => $group['nome'], 'opcoes' => $options];
    }

    $baseCents = uplar_complementos_cents($product['preco']);
    $unitCents = $baseCents + $extraCents;
    $baseCostCents = null;
    if (isset($product['preco_custo']) && (float)$product['preco_custo'] > 0) {
        $baseCostCents = uplar_complementos_cents($product['preco_custo']);
    }
    $costsComplete = $baseCostCents !== null && $extraCostsComplete;
    return [
        'snapshot' => [
            'grupos' => $snapshotGroups,
            'observacao' => $observacao,
            'preco_base_centavos' => $baseCents,
            'total_adicional_centavos' => $extraCents,
            'preco_unitario_centavos' => $unitCents,
            'custo_adicional_centavos' => $hasKnownExtraCost ? $extraCostCents : null,
            'custos_completos' => $extraCostsComplete,
        ],
        'total_adicional' => uplar_complementos_money($extraCents), 'total_adicional_centavos' => $extraCents,
        'preco_base' => uplar_complementos_money($baseCents), 'preco_base_centavos' => $baseCents,
        'preco_unitario' => uplar_complementos_money($unitCents), 'preco_unitario_centavos' => $unitCents,
        'custo_adicional_centavos' => $extraCostsComplete ? $extraCostCents : null,
        'custo_unitario_centavos' => $costsComplete ? $baseCostCents + $extraCostCents : null,
        'custos_completos' => $costsComplete,
    ];
}
