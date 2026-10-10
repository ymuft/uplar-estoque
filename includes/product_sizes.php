<?php

function product_sizes_normalize($size): string {
    $size = mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string)$size)));
    if ($size === '' || mb_strlen($size) > 40) throw new InvalidArgumentException('Tamanho inválido.');
    return $size;
}

function product_sizes_ensure_schema(PDO $pdo): void {
    static $ready = false;
    if ($ready) return;
    if (!$pdo->query("SHOW COLUMNS FROM produtos LIKE 'usa_tamanhos'")->fetchColumn()) {
        $pdo->exec("ALTER TABLE produtos ADD COLUMN usa_tamanhos TINYINT(1) NOT NULL DEFAULT 0 AFTER quantidade");
    }
    $pdo->exec("CREATE TABLE IF NOT EXISTS produto_tamanhos (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        produto_id INT NOT NULL,
        tamanho VARCHAR(40) NOT NULL,
        quantidade INT NOT NULL DEFAULT 0,
        ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id), UNIQUE KEY uq_produto_tamanho (produto_id, tamanho),
        KEY idx_produto_tamanho_ativo (produto_id, ativo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    foreach ([
        'vitrine_carrinho' => "tamanho VARCHAR(40) NULL AFTER produto_id",
        'vendas' => "tamanho VARCHAR(40) NULL AFTER produto_id",
    ] as $table => $definition) {
        if (!$pdo->query("SHOW COLUMNS FROM {$table} LIKE 'tamanho'")->fetchColumn()) {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$definition}");
        }
    }
    $ready = true;
}

function product_sizes_list(PDO $pdo, int $productId, bool $onlyActive = false): array {
    $sql = 'SELECT id, tamanho, quantidade, ordem, ativo FROM produto_tamanhos WHERE produto_id = ?';
    if ($onlyActive) $sql .= ' AND ativo = 1';
    $sql .= ' ORDER BY ordem ASC, id ASC';
    $s=$pdo->prepare($sql); $s->execute([$productId]);
    return $s->fetchAll(PDO::FETCH_ASSOC);
}

function product_sizes_sync_total(PDO $pdo, int $productId, int $userId): int {
    $s=$pdo->prepare('SELECT COALESCE(SUM(quantidade),0) FROM produto_tamanhos WHERE produto_id = ? AND ativo = 1');
    $s->execute([$productId]); $total=max(0,(int)$s->fetchColumn());
    $pdo->prepare('UPDATE produtos SET quantidade = ?, usa_tamanhos = 1 WHERE id = ? AND user_id = ?')->execute([$total,$productId,$userId]);
    return $total;
}

function product_sizes_save(PDO $pdo, int $productId, int $userId, array $sizes): int {
    if (!$sizes || count($sizes)>30) throw new InvalidArgumentException('Informe entre 1 e 30 tamanhos.');
    $seen=[]; $normalized=[];
    foreach ($sizes as $order=>$item) {
        $name=product_sizes_normalize(is_array($item)?($item['tamanho']??''):$item);
        $qty=max(0,(int)(is_array($item)?($item['quantidade']??0):0));
        if(isset($seen[$name])) throw new InvalidArgumentException('Tamanhos repetidos.');
        $seen[$name]=true; $normalized[]=[$name,$qty,$order+1];
    }
    $pdo->prepare('UPDATE produto_tamanhos SET ativo = 0 WHERE produto_id = ?')->execute([$productId]);
    $up=$pdo->prepare('INSERT INTO produto_tamanhos (produto_id,tamanho,quantidade,ordem,ativo) VALUES (?,?,?,?,1) ON DUPLICATE KEY UPDATE quantidade=VALUES(quantidade),ordem=VALUES(ordem),ativo=1');
    foreach($normalized as [$name,$qty,$order]) $up->execute([$productId,$name,$qty,$order]);
    return product_sizes_sync_total($pdo,$productId,$userId);
}
