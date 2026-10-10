<?php
/** M3 — API privada de categorias; nunca executa migrations em requisição. */
declare(strict_types=1);
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/includes/catalog_categories.php';
require_once __DIR__ . '/includes/business_profile_runtime.php';
header('Content-Type: application/json; charset=utf-8');
send_security_headers();
function cat_reply(array $value,int $status=200): void {
    http_response_code($status);echo json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id']<1)cat_reply(['ok'=>false,'error'=>'Não autenticado.'],401);
$userId=(int)$_SESSION['user_id'];
$action=(string)($_GET['acao']??$_POST['acao']??'');
$allowed=['listar','criar','renomear','ordenar','atribuir','excluir','auditoria','importar_legado','aplicar_sugestoes','ativar_canonico'];
if (!in_array($action,$allowed,true))cat_reply(['ok'=>false,'error'=>'Ação inválida.'],404);
if ($action!=='listar'&&$action!=='auditoria') {
    if ($_SERVER['REQUEST_METHOD']!=='POST')cat_reply(['ok'=>false,'error'=>'Método inválido.'],405);
    if (!verify_csrf_token())cat_reply(['ok'=>false,'error'=>'Token CSRF inválido.'],403);
}
$pdo=get_pdo();
if (!uplar_catalogo_schema_ready($pdo))cat_reply(['ok'=>false,'error'=>'Catálogo de categorias ainda não foi habilitado neste ambiente.'],409);
try {
    if($action==='listar')cat_reply(['ok'=>true,'categorias'=>uplar_catalogo_list($pdo,$userId)]);
    $id=uplar_catalogo_parse_input($_POST['categoria_id']??null);
    if($action==='criar'){
        $categoryId=uplar_catalogo_ensure($pdo,$userId,(string)($_POST['nome']??''));
        cat_reply(['ok'=>true,'categoria_id'=>$categoryId,'categorias'=>uplar_catalogo_list($pdo,$userId)]);
    }
    if($action==='renomear'){
        if(!$id||!uplar_catalogo_category($pdo,$userId,$id))throw new InvalidArgumentException('Categoria não encontrada.');
        $name=uplar_catalogo_validate_name($_POST['nome']??null);
        $s=$pdo->prepare('UPDATE catalogo_categorias SET nome=? WHERE id=? AND user_id=?');$s->execute([$name,$id,$userId]);
        cat_reply(['ok'=>true,'categorias'=>uplar_catalogo_list($pdo,$userId)]);
    }
    if($action==='ordenar'){
        $ids=json_decode((string)($_POST['ids']??''),true,512,JSON_THROW_ON_ERROR);
        if (!is_array($ids)||!array_is_list($ids)||count($ids)>40)throw new InvalidArgumentException('Ordem inválida.');
        $existing=array_column(uplar_catalogo_list($pdo,$userId),'id');
        $ordered=[];
        foreach($ids as $cid){if(!is_int($cid)||$cid<=0||in_array($cid,$ordered,true))throw new InvalidArgumentException('Ordem inválida.');$ordered[]=$cid;}
        sort($existing);$compare=$ordered;sort($compare);
        if($compare!==$existing)throw new InvalidArgumentException('Informe todas as categorias da empresa para reordenar.');
        $pdo->beginTransaction();$stmt=$pdo->prepare('UPDATE catalogo_categorias SET ordem=? WHERE user_id=? AND id=?');
        foreach($ordered as $i=>$cid)$stmt->execute([$i+1,$userId,$cid]);
        $pdo->commit();cat_reply(['ok'=>true,'categorias'=>uplar_catalogo_list($pdo,$userId)]);
    }
    if($action==='atribuir'){
        $product=uplar_catalogo_parse_input($_POST['produto_id']??null);
        if(!$product)throw new InvalidArgumentException('Produto inválido.');
        uplar_catalogo_assign($pdo,$userId,$product,$id);
        cat_reply(['ok'=>true]);
    }
    if($action==='excluir'){
        if(!$id||!uplar_catalogo_category($pdo,$userId,$id))throw new InvalidArgumentException('Categoria não encontrada.');
        if(($_POST['confirmar']??'')!=='1')throw new InvalidArgumentException('Confirme que deseja excluir a categoria sem excluir produtos.');
        $target=uplar_catalogo_parse_input($_POST['transferir_para']??null);
        if($target===$id)throw new InvalidArgumentException('Selecione outra categoria ou deixe os produtos sem categoria.');
        if($target!==null&&!uplar_catalogo_category($pdo,$userId,$target))throw new InvalidArgumentException('Categoria de destino inválida.');
        $pdo->beginTransaction();
        $s=$pdo->prepare('UPDATE produtos SET categoria_id=? WHERE user_id=? AND categoria_id=?');$s->execute([$target,$userId,$id]);
        $pdo->prepare('DELETE FROM catalogo_categorias WHERE user_id=? AND id=?')->execute([$userId,$id]);
        $pdo->commit();cat_reply(['ok'=>true,'categorias'=>uplar_catalogo_list($pdo,$userId)]);
    }
    $legacy = [];
    if (uplar_vitrine_experience_schema_disponivel($pdo)) {
        $s=$pdo->prepare('SELECT experiencia_json FROM vitrine_config WHERE user_id=? LIMIT 1');$s->execute([$userId]);
        $legacy=uplar_vitrine_experience_preferences($s->fetchColumn()?:null)['categorias'];
    }
    if($action==='auditoria')cat_reply(['ok'=>true,'relatorio'=>uplar_catalogo_audit($pdo,$userId,$legacy)]);
    if($action==='aplicar_sugestoes'){
        if (!uplar_business_profile_schema_available($pdo))throw new RuntimeException('Perfil do negócio indisponível.');
        $pdo->beginTransaction();
        $s=$pdo->prepare('SELECT perfil_negocio_json FROM empresa_config WHERE user_id=? FOR UPDATE');$s->execute([$userId]);
        $profile=uplar_business_profile_decode($s->fetchColumn());
        if (($profile['status_configuracao']??'')!=='concluida')
            throw new InvalidArgumentException('Conclua o perfil do negócio antes de aplicar as sugestões.');
        if(empty($profile['categorias_definidas']))
            throw new InvalidArgumentException('Confirme as categorias no perfil do negócio.');
        $created=[];
        foreach(($profile['categorias_pendentes']??[]) as $name)$created[]=uplar_catalogo_ensure($pdo,$userId,$name);
        $profile['categorias_aplicadas_em']=$profile['categorias_aplicadas_em']??date('c');
        $pdo->prepare('UPDATE empresa_config SET perfil_negocio_json=? WHERE user_id=?')->execute([
            json_encode($profile,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$userId]);
        $pdo->commit();cat_reply(['ok'=>true,'categorias'=>uplar_catalogo_list($pdo,$userId),'aplicadas'=>count($created)]);
    }
    if($action==='importar_legado'){
        $report=uplar_catalogo_audit($pdo,$userId,$legacy);
        if($report['conflitos']>0 && ($_POST['confirmar_conflitos']??'')!=='1')
            cat_reply(['ok'=>false,'error'=>'Existem divergências entre cadastro e vitrine. Revise o relatório e confirme explicitamente.','relatorio'=>$report],409);
        $pdo->beginTransaction();
        $products=$pdo->prepare('SELECT id,categoria,categoria_id FROM produtos WHERE user_id=? ORDER BY id FOR UPDATE');
        $products->execute([$userId]);$rows=$products->fetchAll(PDO::FETCH_ASSOC);
        $names=[];$manual=[];
        foreach($legacy as $c){$name=trim((string)($c['nome']??''));if($name==='')continue;
            $names[]=$name;foreach(($c['produto_ids']??[]) as $pid)$manual[(int)$pid]=$name;
        }
        $indexed=[];
        foreach($rows as $p){
            $text=trim((string)($p['categoria']??''));
            $choice=$manual[(int)$p['id']]??$text;
            if($choice!=='')$names[]=$choice;
            if($choice!==''&&empty($p['categoria_id']))$indexed[(int)$p['id']]=$choice;
        }
        foreach(array_unique($names) as $name)uplar_catalogo_ensure($pdo,$userId,$name);
        $updated=0;
        foreach($indexed as $pid=>$name){$cid=uplar_catalogo_ensure($pdo,$userId,$name);uplar_catalogo_assign($pdo,$userId,$pid,$cid);$updated++;}
        $pdo->commit();cat_reply(['ok'=>true,'associados'=>$updated,'categorias'=>uplar_catalogo_list($pdo,$userId),
            'aviso'=>'Vitrine legada permanece publicada. Ative o catálogo canônico separadamente.']);
    }
    if($action==='ativar_canonico'){
        if (!uplar_vitrine_experience_schema_disponivel($pdo))throw new RuntimeException('Vitrine ainda não suporta configurações de experiência.');
        if(($_POST['confirmar']??'')!=='1')throw new InvalidArgumentException('Confirme a mudança de origem das categorias.');
        $report=uplar_catalogo_audit($pdo,$userId,$legacy);
        if($report['conflitos']>0 && ($_POST['confirmar_conflitos']??'')!=='1')
            cat_reply(['ok'=>false,'error'=>'Conflitos detectados. Confirme após revisar o relatório.','relatorio'=>$report],409);
        $pdo->beginTransaction();
        $q=$pdo->prepare('SELECT experiencia_json FROM vitrine_config WHERE user_id=? FOR UPDATE');$q->execute([$userId]);
        $raw=$q->fetchColumn();
        if($raw===false)throw new InvalidArgumentException('Salve primeiro os dados da vitrine antes de mudar a origem das categorias.');
        $merged=uplar_vitrine_experience_merge($raw,['fonte_categorias'=>'canonico']);
        $pdo->prepare('UPDATE vitrine_config SET experiencia_json=? WHERE user_id=?')->execute([
            json_encode($merged,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$userId]);
        $pdo->commit();cat_reply(['ok'=>true,'origem'=>'canonico']);
    }
} catch(InvalidArgumentException|JsonException $e) {
    if($pdo->inTransaction())$pdo->rollBack();cat_reply(['ok'=>false,'error'=>$e->getMessage()],422);
} catch(PDOException $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    error_log('[categorias] '.$e->getMessage());
    cat_reply(['ok'=>false,'error'=>$e->getCode()==='23000'?'Já existe uma categoria com este nome.':'Não foi possível atualizar as categorias.'],409);
} catch(Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();error_log('[categorias] '.$e->getMessage());
    cat_reply(['ok'=>false,'error'=>'Não foi possível concluir a operação.'],500);
}
