<?php

class UplarComplementosAdminConflictException extends RuntimeException
{
}

function uplar_complementos_admin_contexto_grupo(PDO $pdo, int $ownerId, int $groupId, bool $lock = false): array
{
    $suffix = $lock ? ' FOR UPDATE' : '';
    $groupStmt = $pdo->prepare('SELECT id,nome,descricao,minimo,maximo,ordem,ativo FROM produto_complemento_grupos WHERE id=? AND user_id=?' . $suffix);
    $groupStmt->execute([$groupId, $ownerId]);
    $group = $groupStmt->fetch(PDO::FETCH_ASSOC);
    if (!$group) {
        throw new RuntimeException('Grupo não encontrado.');
    }

    $optionsStmt = $pdo->prepare('SELECT id,grupo_id,nome,preco_adicional,custo_adicional,ordem,max_quantidade,ativo FROM produto_complemento_opcoes WHERE grupo_id=? AND user_id=? ORDER BY id ASC' . $suffix);
    $optionsStmt->execute([$groupId, $ownerId]);
    $options = $optionsStmt->fetchAll(PDO::FETCH_ASSOC);
    $linksStmt = $pdo->prepare('SELECT v.id,v.produto_id,v.ativo,p.nome AS produto_nome FROM produto_complemento_vinculos v INNER JOIN produtos p ON p.id=v.produto_id AND p.user_id=v.user_id WHERE v.grupo_id=? AND v.user_id=? ORDER BY v.produto_id ASC, v.id ASC' . $suffix);
    $linksStmt->execute([$groupId, $ownerId]);
    $links = $linksStmt->fetchAll(PDO::FETCH_ASSOC);

    $products = [];
    foreach ($links as $link) {
        if (!(bool)$link['ativo']) continue;
        $products[] = [
            'id' => (int)$link['produto_id'],
            'nome' => (string)$link['produto_nome'],
            'ativo' => (bool)$link['ativo'],
        ];
    }
    $versionPayload = [
        'grupo' => [
            'id' => (int)$group['id'],
            'nome' => (string)$group['nome'],
            'descricao' => $group['descricao'],
            'minimo' => (int)$group['minimo'],
            'maximo' => (int)$group['maximo'],
            'ordem' => (int)$group['ordem'],
            'ativo' => (bool)$group['ativo'],
        ],
        'opcoes' => array_map(static function (array $option): array {
            return [
                'id' => (int)$option['id'], 'nome' => (string)$option['nome'],
                'preco_adicional' => (string)$option['preco_adicional'], 'custo_adicional' => $option['custo_adicional'],
                'ordem' => (int)$option['ordem'], 'max_quantidade' => (int)$option['max_quantidade'], 'ativo' => (bool)$option['ativo'],
            ];
        }, $options),
        'vinculos' => array_map(static function (array $link): array {
            return ['id' => (int)$link['id'], 'produto_id' => (int)$link['produto_id'], 'ativo' => (bool)$link['ativo']];
        }, $links),
    ];

    return [
        'grupo' => $group,
        'opcoes' => $options,
        'qtd_produtos' => count($products),
        'qtd_vinculos' => count($links),
        'produtos' => $products,
        'versao' => hash('sha256', json_encode($versionPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
    ];
}

function uplar_complementos_admin_exigir_confirmacao(PDO $pdo, int $ownerId, int $groupId, bool $confirm, $version, bool $anyLink): array
{
    $context = uplar_complementos_admin_contexto_grupo($pdo, $ownerId, $groupId, true);
    $threshold = $anyLink ? 0 : 1;
    if ($context['qtd_produtos'] > $threshold) {
        if (!$confirm || !is_string($version) || !hash_equals($context['versao'], $version)) {
            throw new UplarComplementosAdminConflictException('Este grupo compartilhado foi alterado. Revise os dados e confirme a edição novamente.');
        }
    }
    return $context;
}

function uplar_complementos_admin_normalizar_grupo(array $context): array
{
    $group = $context['grupo'];
    $group['id'] = (int)$group['id'];
    $group['minimo'] = (int)$group['minimo'];
    $group['maximo'] = (int)$group['maximo'];
    $group['ordem'] = (int)$group['ordem'];
    $group['ativo'] = (bool)$group['ativo'];
    $group['qtd_produtos'] = $context['qtd_produtos'];
    $group['produtos'] = $context['produtos'];
    $group['versao'] = $context['versao'];
    $group['opcoes'] = $context['opcoes'];
    usort($group['opcoes'], static fn(array $a, array $b): int => ((int)$b['ativo'] <=> (int)$a['ativo']) ?: ((int)$a['ordem'] <=> (int)$b['ordem']) ?: ((int)$a['id'] <=> (int)$b['id']));
    foreach ($group['opcoes'] as &$option) {
        $option['id'] = (int)$option['id'];
        $option['grupo_id'] = (int)$option['grupo_id'];
        $option['ordem'] = (int)$option['ordem'];
        $option['max_quantidade'] = (int)$option['max_quantidade'];
        $option['ativo'] = (bool)$option['ativo'];
        $option['preco_adicional'] = number_format((float)$option['preco_adicional'], 2, '.', '');
        $option['custo_adicional'] = $option['custo_adicional'] === null ? null : number_format((float)$option['custo_adicional'], 2, '.', '');
    }
    unset($option);
    return $group;
}

function uplar_complementos_admin_listar_grupos(PDO $pdo, int $ownerId): array
{
    $stmt = $pdo->prepare('SELECT id FROM produto_complemento_grupos WHERE user_id=? ORDER BY ativo DESC, ordem ASC, id ASC');
    $stmt->execute([$ownerId]);
    $groups = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $groupId) {
        $groups[] = uplar_complementos_admin_normalizar_grupo(uplar_complementos_admin_contexto_grupo($pdo, $ownerId, (int)$groupId));
    }
    return $groups;
}

function uplar_complementos_admin_snapshot_referencia_grupo(array $snapshot, int $groupId): bool
{
    foreach (($snapshot['grupos'] ?? []) as $group) {
        if (is_array($group) && (int)($group['grupo_id'] ?? 0) === $groupId) {
            return true;
        }
    }
    return false;
}

function uplar_complementos_admin_tabela_existe(PDO $pdo, string $table): bool
{
    $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
    return (bool)$stmt->fetchColumn();
}

function uplar_complementos_admin_coluna_existe(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->query("SHOW COLUMNS FROM {$table} LIKE " . $pdo->quote($column));
    return (bool)$stmt->fetchColumn();
}

function uplar_complementos_admin_historico_referencia_grupo(PDO $pdo, int $ownerId, int $groupId): bool
{
    $sources = [];
    foreach (['pedidos', 'vitrine_pedidos'] as $table) {
        if (uplar_complementos_admin_tabela_existe($pdo, $table)
            && uplar_complementos_admin_coluna_existe($pdo, $table, 'itens_json')) {
            $sources[] = [$table, 'itens_json', true];
        }
    }
    if (uplar_complementos_admin_tabela_existe($pdo, 'vendas')
        && uplar_complementos_admin_coluna_existe($pdo, 'vendas', 'personalizacao_json')) {
        $sources[] = ['vendas', 'personalizacao_json', false];
    }
    foreach ($sources as [$table, $column, $items]) {
        $lastId = 0;
        do {
            $stmt = $pdo->prepare("SELECT id, {$column} AS snapshot_json FROM {$table} WHERE user_id=? AND id>? ORDER BY id ASC LIMIT 200");
            $stmt->execute([$ownerId, $lastId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $lastId = (int)$row['id'];
                $raw = $row['snapshot_json'];
                if ($raw === null || trim((string)$raw) === '') {
                    continue;
                }
                $decoded = json_decode((string)$raw, true);
                if (!is_array($decoded)) {
                    throw new UplarComplementosAdminConflictException('A exclusão foi bloqueada porque existe histórico que não pode ser validado. Desative o grupo.');
                }
                if ($items) {
                    foreach ($decoded as $item) {
                        if (is_array($item) && is_array($item['personalizacao'] ?? null)
                            && uplar_complementos_admin_snapshot_referencia_grupo($item['personalizacao'], $groupId)) {
                            return true;
                        }
                    }
                } elseif (uplar_complementos_admin_snapshot_referencia_grupo($decoded, $groupId)) {
                    return true;
                }
            }
        } while (count($rows) === 200);
    }
    return false;
}
