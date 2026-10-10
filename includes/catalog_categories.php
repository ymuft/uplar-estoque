<?php
/**
 * M3 — categorias canônicas por conta, sem DDL em tempo de requisição.
 * Nenhuma criação/associação acontece na leitura pública.
 */
declare(strict_types=1);

function uplar_catalogo_schema_ready(PDO $pdo): bool {
    try {
        $tbl = $pdo->query("SHOW TABLES LIKE 'catalogo_categorias'");
        if (!$tbl->fetch(PDO::FETCH_NUM)) return false;
        $tbl->closeCursor();
        $col = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'categoria_id'");
        $ready = (bool)$col->fetch(PDO::FETCH_ASSOC);
        $col->closeCursor();
        return $ready;
    } catch (Throwable $e) { return false; }
}

function uplar_catalogo_validate_name($name): string {
    if (!is_string($name)) throw new InvalidArgumentException('Nome da categoria inválido.');
    $value = trim(preg_replace('/\\s+/u', ' ', $name) ?? '');
    if ($value === '' || mb_strlen($value) > 80 || preg_match('/[\\x00-\\x1f\\x7f]/u', $value))
        throw new InvalidArgumentException('Informe um nome de categoria com até 80 caracteres.');
    return $value;
}

function uplar_catalogo_list(PDO $pdo, int $userId): array {
    if (!uplar_catalogo_schema_ready($pdo)) return [];
    $s = $pdo->prepare('SELECT c.id,c.nome,c.ordem,COUNT(p.id) AS total_produtos
        FROM catalogo_categorias c LEFT JOIN produtos p
          ON p.categoria_id=c.id AND p.user_id=c.user_id
        WHERE c.user_id=? GROUP BY c.id,c.nome,c.ordem ORDER BY c.ordem,c.id');
    $s->execute([$userId]);
    return array_map(static function($row){
        return ['id'=>(int)$row['id'],'nome'=>$row['nome'],'ordem'=>(int)$row['ordem'],'total_produtos'=>(int)$row['total_produtos']];
    }, $s->fetchAll(PDO::FETCH_ASSOC));
}

function uplar_catalogo_category(PDO $pdo, int $userId, int $categoryId): ?array {
    if ($categoryId <= 0 || !uplar_catalogo_schema_ready($pdo)) return null;
    $s=$pdo->prepare('SELECT id,nome,ordem FROM catalogo_categorias WHERE user_id=? AND id=? LIMIT 1');
    $s->execute([$userId,$categoryId]);
    return $s->fetch(PDO::FETCH_ASSOC) ?: null;
}

function uplar_catalogo_ensure(PDO $pdo,int $userId,string $name): int {
    if (!uplar_catalogo_schema_ready($pdo)) throw new RuntimeException('Categorias aguardam migration.');
    $name=uplar_catalogo_validate_name($name);
    $find=$pdo->prepare('SELECT id FROM catalogo_categorias WHERE user_id=? AND nome=? LIMIT 1');
    $find->execute([$userId,$name]);
    if ($old=(int)$find->fetchColumn()) return $old;
    $order=$pdo->prepare('SELECT COALESCE(MAX(ordem),0)+1 FROM catalogo_categorias WHERE user_id=?');
    $order->execute([$userId]);
    $next=(int)$order->fetchColumn();
    try{
        $s=$pdo->prepare('INSERT INTO catalogo_categorias (user_id,nome,ordem) VALUES (?,?,?)');
        $s->execute([$userId,$name,$next]);
        return (int)$pdo->lastInsertId();
    }catch(PDOException $e){
        // Corrida entre criações: reaproveita a categoria, não a duplica.
        if ($e->getCode()!=='23000') throw $e;
        $find->execute([$userId,$name]);
        if ($existing=(int)$find->fetchColumn()) return $existing;
        throw $e;
    }
}

/** Null permite remover classificação sem apagar produto. */
function uplar_catalogo_assign(PDO $pdo,int $userId,int $productId,?int $categoryId): void {
    if (!uplar_catalogo_schema_ready($pdo)) throw new RuntimeException('Categorias aguardam migration.');
    if ($categoryId!==null && !uplar_catalogo_category($pdo,$userId,$categoryId))
        throw new InvalidArgumentException('Categoria inexistente ou de outra empresa.');
    $s=$pdo->prepare('UPDATE produtos SET categoria_id=? WHERE id=? AND user_id=?');
    $s->execute([$categoryId,$productId,$userId]);
    if ($s->rowCount()===0) {
        $check=$pdo->prepare('SELECT id FROM produtos WHERE id=? AND user_id=?');
        $check->execute([$productId,$userId]);
        if (!$check->fetchColumn()) throw new InvalidArgumentException('Produto não encontrado nesta empresa.');
    }
}

function uplar_catalogo_parse_input($raw): ?int {
    if ($raw===null || $raw==='') return null;
    if (!is_string($raw) && !is_int($raw)) throw new InvalidArgumentException('Categoria inválida.');
    if (!preg_match('/^[1-9][0-9]*$/D',(string)$raw)) throw new InvalidArgumentException('Categoria inválida.');
    return (int)$raw;
}

/** Categorias para renderização pública, refletindo apenas produtos expostos. */
function uplar_catalogo_public_categories(PDO $pdo,int $userId): array {
    if (!uplar_catalogo_schema_ready($pdo)) return [];
    $items=uplar_catalogo_list($pdo,$userId);
    if (!$items) return [];
    $s=$pdo->prepare('SELECT p.id,p.categoria_id FROM produtos p
        WHERE p.user_id=? AND p.vitrine_exposto=1 AND p.categoria_id IS NOT NULL ORDER BY p.nome,p.id');
    $s->execute([$userId]);$ids=[];
    foreach($s->fetchAll(PDO::FETCH_ASSOC) as $item)$ids[(int)$item['categoria_id']][]=(int)$item['id'];
    return array_map(static fn(array $x)=>[
        'id'=>'cat-'.$x['id'],'nome'=>$x['nome'],'produto_ids'=>$ids[$x['id']]??[]
    ],$items);
}

/** Não altera vitrines antigas com categoria manual enquanto a origem for legada. */
function uplar_catalogo_use_public(PDO $pdo,int $userId,array $preferences): bool {
    if (!uplar_catalogo_schema_ready($pdo)) return false;
    if (($preferences['fonte_categorias']??'legado')==='canonico') return true;
    // Sem configuração manual antiga, uma categoria criada voluntariamente pode aparecer.
    if (!empty($preferences['categorias'])) return false;
    return count(uplar_catalogo_list($pdo,$userId))>0;
}

function uplar_catalogo_public_prefs(PDO $pdo,int $userId,array $preferences): array {
    if (uplar_catalogo_use_public($pdo,$userId,$preferences))
        $preferences['categorias']=uplar_catalogo_public_categories($pdo,$userId);
    return $preferences;
}

/**
 * Relatório somente leitura para decisão de migração; compara associações
 * explícitas da vitrine com o texto legado e a categoria canônica.
 */
function uplar_catalogo_audit(PDO $pdo,int $userId,array $legacyCategories): array {
    if (!uplar_catalogo_schema_ready($pdo))throw new RuntimeException('Categorias aguardam migration.');
    $stmt=$pdo->prepare('SELECT id,nome,categoria,categoria_id FROM produtos WHERE user_id=? ORDER BY id');
    $stmt->execute([$userId]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $manual=[];$legacyNames=[];
    foreach($legacyCategories as $cat){
        if (!is_array($cat)) continue;
        $name=trim((string)($cat['nome']??''));
        if (!$name)continue;
        $legacyNames[]=$name;
        foreach(($cat['produto_ids']??[]) as $id){if((int)$id>0)$manual[(int)$id]=$name;}
    }
    $assigned=0;$conflicts=[];$fromManual=0;$fromText=0;
    $canonical=array_column(uplar_catalogo_list($pdo,$userId),'nome','id');
    foreach($rows as $product){
        $id=(int)$product['id'];
        $text=trim((string)($product['categoria']??''));
        $chosen=$manual[$id]??$text;
        if(isset($manual[$id]))$fromManual++;
        elseif($text!=='')$fromText++;
        if($chosen==='')continue;
        $assigned++;
        $current=$canonical[(int)($product['categoria_id']??0)]??'';
        $mismatch=($text!==''&&isset($manual[$id])&&mb_strtolower($text)!==mb_strtolower($manual[$id]))
            ||($current!==''&&mb_strtolower($current)!==mb_strtolower($chosen));
        if($mismatch)$conflicts[]=['produto_id'=>$id,'produto'=>$product['nome'],
            'categoria_cadastro'=>$text,'categoria_vitrine'=>$manual[$id]??null,'categoria_canonica'=>$current?:null];
    }
    return [
        'produtos'=>count($rows),'classificaveis'=>$assigned,
        'origem_manual'=>$fromManual,'origem_textual'=>$fromText,
        'conflitos'=>count($conflicts),'amostra_conflitos'=>array_slice($conflicts,0,100),
        'categorias_vitrine'=>count($legacyNames),
    ];
}
