<?php
// financeiro_api.php — API do Módulo Financeiro Uplar
header('Content-Type: application/json; charset=utf-8');
session_start();
require 'db.php';
require 'helpers.php';

$acao = $_GET['acao'] ?? '';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok'=>false,'error'=>'Não autenticado']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
        http_response_code(403);
        echo json_encode(['ok'=>false,'error'=>'CSRF inválido']);
        exit;
    }
}

try {
    $pdo = get_pdo();
} catch(Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'Erro de conexão']);
    exit;
}

// ── helpers internos ──────────────────────────────────────────
function R($v){ return number_format((float)$v, 2, '.', ''); }

$custoCompletoColumn = (bool)$pdo->query("SHOW COLUMNS FROM vendas LIKE 'custo_completo'")->fetchColumn();
function financeIncompleteSales(PDO $pdo, int $userId, int $month, int $year, bool $hasCompletenessColumn): int {
    $condition = $hasCompletenessColumn
        ? '(custo_completo = 0 OR (custo_completo IS NULL AND custo_unitario = 0))'
        : 'custo_unitario = 0';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vendas WHERE user_id = ? AND MONTH(criado_em) = ? AND YEAR(criado_em) = ? AND {$condition}");
    $stmt->execute([$userId, $month, $year]);
    return (int)$stmt->fetchColumn();
}

function financeIncompleteSalesRange(PDO $pdo, int $userId, int $days, bool $hasCompletenessColumn): int {
    $condition = $hasCompletenessColumn
        ? '(custo_completo = 0 OR (custo_completo IS NULL AND custo_unitario = 0))'
        : 'custo_unitario = 0';
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM vendas WHERE user_id = ? AND criado_em >= DATE_SUB(CURDATE(), INTERVAL ? DAY) AND {$condition}");
    $stmt->execute([$userId, $days]);
    return (int)$stmt->fetchColumn();
}

// Atualiza status de contas vencidas
function updateStatus(PDO $pdo, string $table, int $uid) {
    $pdo->prepare("UPDATE $table SET status='atrasado' WHERE user_id=? AND status='pendente' AND data_vencimento < CURDATE()")->execute([$uid]);
}

// ════════════════════════════════════════════════════════════
// DASHBOARD FINANCEIRO
// ════════════════════════════════════════════════════════════
if ($acao === 'dashboard') {
    updateStatus($pdo,'contas_receber',$user_id);
    updateStatus($pdo,'contas_pagar',$user_id);
    $mes  = (int)($_GET['mes']  ?? date('n'));
    $ano  = (int)($_GET['ano']  ?? date('Y'));
    if ($mes < 1 || $mes > 12) $mes = (int)date('n');
    if ($ano < 2000 || $ano > 2100) $ano = (int)date('Y');
    $mesA = $mes===1 ? 12 : $mes-1;
    $anoA = $mes===1 ? $ano-1 : $ano;

    // Receita do mês (vendas)
    $s = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM vendas WHERE user_id=? AND MONTH(criado_em)=? AND YEAR(criado_em)=?");
    $s->execute([$user_id,$mes,$ano]); $recVendas = (float)$s->fetchColumn();
    $s = $pdo->prepare("SELECT COALESCE(SUM(quantidade * custo_unitario),0) FROM vendas WHERE user_id=? AND MONTH(criado_em)=? AND YEAR(criado_em)=?");
    $s->execute([$user_id,$mes,$ano]); $custoVendas = (float)$s->fetchColumn();
    $custosParciais = financeIncompleteSales($pdo, $user_id, $mes, $ano, $custoCompletoColumn);
    // Receitas extras do mês
    $s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM receitas_extras WHERE user_id=? AND MONTH(data_receita)=? AND YEAR(data_receita)=? AND status='recebido'");
    $s->execute([$user_id,$mes,$ano]); $recExtras = (float)$s->fetchColumn();
    $recTotal = $recVendas + $recExtras;

    // Receita mês anterior
    $s->execute([$user_id,$mesA,$anoA]); $recExtraAnt = (float)$s->fetchColumn();
    $s = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM vendas WHERE user_id=? AND MONTH(criado_em)=? AND YEAR(criado_em)=?");
    $s->execute([$user_id,$mesA,$anoA]); $recVendasAnt = (float)$s->fetchColumn();
    $s = $pdo->prepare("SELECT COALESCE(SUM(quantidade * custo_unitario),0) FROM vendas WHERE user_id=? AND MONTH(criado_em)=? AND YEAR(criado_em)=?");
    $s->execute([$user_id,$mesA,$anoA]); $custoVendasAnt = (float)$s->fetchColumn();
    $custosParciaisAnt = financeIncompleteSales($pdo, $user_id, $mesA, $anoA, $custoCompletoColumn);
    $recAnt = $recVendasAnt + $recExtraAnt;

    // Despesas do mês
    $s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM despesas WHERE user_id=? AND MONTH(data_despesa)=? AND YEAR(data_despesa)=? AND status='pago'");
    $s->execute([$user_id,$mes,$ano]); $despTotal = (float)$s->fetchColumn();
    // Contas a pagar pagas no mês
    $s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM contas_pagar WHERE user_id=? AND MONTH(data_pagamento)=? AND YEAR(data_pagamento)=? AND status='pago'");
    $s->execute([$user_id,$mes,$ano]); $despTotal += (float)$s->fetchColumn();

    // Despesas mês anterior
    $s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM despesas WHERE user_id=? AND MONTH(data_despesa)=? AND YEAR(data_despesa)=? AND status='pago'");
    $s->execute([$user_id,$mesA,$anoA]); $despAnt = (float)$s->fetchColumn();
    $s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM contas_pagar WHERE user_id=? AND MONTH(data_pagamento)=? AND YEAR(data_pagamento)=? AND status='pago'");
    $s->execute([$user_id,$mesA,$anoA]); $despAnt += (float)$s->fetchColumn();

    // Contas a receber pendentes
    $s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) AS tot, COUNT(*) AS cnt FROM contas_receber WHERE user_id=? AND status IN('pendente','atrasado')");
    $s->execute([$user_id]); $cr = $s->fetch();

    // Contas a pagar pendentes
    $s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) AS tot, COUNT(*) AS cnt FROM contas_pagar WHERE user_id=? AND status IN('pendente','atrasado')");
    $s->execute([$user_id]); $cp = $s->fetch();

    // Top produtos (mês)
    $s = $pdo->prepare("
        SELECT p.nome, SUM(v.quantidade) as unidades, SUM(v.total) as receita
        FROM vendas v JOIN produtos p ON v.produto_id=p.id
        WHERE v.user_id=? AND MONTH(v.criado_em)=? AND YEAR(v.criado_em)=?
        GROUP BY v.produto_id, p.nome ORDER BY receita DESC LIMIT 5");
    $s->execute([$user_id,$mes,$ano]); $topProds = $s->fetchAll();

    // Indicadores comerciais do mês para cruzamento financeiro
    $s = $pdo->prepare("SELECT COUNT(*) AS vendas, COALESCE(SUM(quantidade),0) AS unidades, COALESCE(AVG(total),0) AS ticket FROM vendas WHERE user_id=? AND MONTH(criado_em)=? AND YEAR(criado_em)=?");
    $s->execute([$user_id,$mes,$ano]); $comercial = $s->fetch();
    $s->execute([$user_id,$mesA,$anoA]); $comercialAnt = $s->fetch();

    $s = $pdo->prepare("
        SELECT v.produto_id, COALESCE(SUM(v.total),0) AS receita
        FROM vendas v
        WHERE v.user_id=? AND MONTH(v.criado_em)=? AND YEAR(v.criado_em)=?
        GROUP BY v.produto_id");
    $s->execute([$user_id,$mesA,$anoA]);
    $receitaAnteriorPorProduto = [];
    foreach ($s->fetchAll() as $produtoAnterior) {
        $receitaAnteriorPorProduto[(int)$produtoAnterior['produto_id']] = (float)$produtoAnterior['receita'];
    }

    $s = $pdo->prepare("
        SELECT p.id, p.nome, SUM(v.quantidade) AS unidades, SUM(v.total) AS receita
        FROM vendas v JOIN produtos p ON v.produto_id=p.id
        WHERE v.user_id=? AND MONTH(v.criado_em)=? AND YEAR(v.criado_em)=?
        GROUP BY p.id, p.nome ORDER BY receita DESC LIMIT 8");
    $s->execute([$user_id,$mes,$ano]); $produtosCruzamento = $s->fetchAll();
    foreach ($produtosCruzamento as &$produtoCruzamento) {
        $produtoId = (int)$produtoCruzamento['id'];
        $receitaProduto = (float)$produtoCruzamento['receita'];
        $produtoCruzamento['receita_anterior'] = R($receitaAnteriorPorProduto[$produtoId] ?? 0);
        $produtoCruzamento['participacao'] = $recVendas > 0 ? round($receitaProduto / $recVendas * 100, 1) : 0;
    }
    unset($produtoCruzamento);

    // Composição das despesas realizadas no mês
    $s = $pdo->prepare("
        SELECT categoria, SUM(total) AS total FROM (
            SELECT COALESCE(categoria,'Outros') AS categoria, valor AS total
            FROM despesas WHERE user_id=? AND MONTH(data_despesa)=? AND YEAR(data_despesa)=? AND status='pago'
            UNION ALL
            SELECT COALESCE(categoria,'Outros') AS categoria, valor AS total
            FROM contas_pagar WHERE user_id=? AND MONTH(data_pagamento)=? AND YEAR(data_pagamento)=? AND status='pago'
        ) despesas_periodo GROUP BY categoria ORDER BY total DESC LIMIT 8");
    $s->execute([$user_id,$mes,$ano,$user_id,$mes,$ano]); $despesasCategoria = $s->fetchAll();

    // Agenda prevista: somente títulos já cadastrados para os próximos 30 dias
    $s = $pdo->prepare("
        SELECT data_ref, SUM(entradas) AS entradas, SUM(saidas) AS saidas FROM (
            SELECT data_vencimento AS data_ref, valor AS entradas, 0 AS saidas
            FROM contas_receber WHERE user_id=? AND status IN('pendente','atrasado')
                AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            UNION ALL
            SELECT data_vencimento AS data_ref, 0 AS entradas, valor AS saidas
            FROM contas_pagar WHERE user_id=? AND status IN('pendente','atrasado')
                AND data_vencimento BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ) agenda GROUP BY data_ref ORDER BY data_ref");
    $s->execute([$user_id,$user_id]); $agenda30 = $s->fetchAll();

    $s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM contas_receber WHERE user_id=? AND status='atrasado'");
    $s->execute([$user_id]); $receberAtrasado = (float)$s->fetchColumn();
    $s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM contas_pagar WHERE user_id=? AND status='atrasado'");
    $s->execute([$user_id]); $pagarAtrasado = (float)$s->fetchColumn();

    // Últimos 6 meses (receita e despesa)
    $meses6 = [];
    $mesBase = new DateTime(sprintf('%04d-%02d-01', $ano, $mes));
    for($i=5;$i>=0;$i--){
        $d = (clone $mesBase)->modify("-$i month");
        $m = (int)$d->format('n'); $a = (int)$d->format('Y');
        $s = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM vendas WHERE user_id=? AND MONTH(criado_em)=? AND YEAR(criado_em)=?");
        $s->execute([$user_id,$m,$a]); $rv=(float)$s->fetchColumn();
        $s = $pdo->prepare("SELECT COALESCE(SUM(quantidade * custo_unitario),0) FROM vendas WHERE user_id=? AND MONTH(criado_em)=? AND YEAR(criado_em)=?");
        $s->execute([$user_id,$m,$a]); $cv=(float)$s->fetchColumn();
        $s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM receitas_extras WHERE user_id=? AND MONTH(data_receita)=? AND YEAR(data_receita)=? AND status='recebido'");
        $s->execute([$user_id,$m,$a]); $re=(float)$s->fetchColumn();
        $s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM despesas WHERE user_id=? AND MONTH(data_despesa)=? AND YEAR(data_despesa)=? AND status='pago'");
        $s->execute([$user_id,$m,$a]); $de=(float)$s->fetchColumn();
        $s = $pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM contas_pagar WHERE user_id=? AND MONTH(data_pagamento)=? AND YEAR(data_pagamento)=? AND status='pago'");
        $s->execute([$user_id,$m,$a]); $de+=(float)$s->fetchColumn();
        $partialMonth = financeIncompleteSales($pdo, $user_id, $m, $a, $custoCompletoColumn);
        $meses6[]=["label"=>$d->format("M/y"),"receita"=>R($rv+$re),"custo_vendas"=>R($cv),"lucro_bruto"=>$partialMonth ? null : R($rv-$cv),"despesa"=>R($de),"lucro"=>$partialMonth ? null : R($rv+$re-$cv-$de),"custos_completos"=>$partialMonth === 0,"vendas_custo_parcial"=>$partialMonth];
    }

    // Comissões pendentes
    $s = $pdo->prepare("SELECT COALESCE(SUM(valor_comissao),0) AS tot, COUNT(*) AS cnt FROM comissoes_pagas WHERE user_id=? AND status='pendente'");
    $s->execute([$user_id]); $comis = $s->fetch();

    echo json_encode([
        'receita'=>R($recTotal),'receita_ant'=>R($recAnt),
        'despesa'=>R($despTotal),'despesa_ant'=>R($despAnt),
        'custo_vendas'=>R($custoVendas),'lucro_bruto'=>$custosParciais ? null : R($recVendas-$custoVendas),
        'lucro'=>$custosParciais ? null : R($recTotal-$custoVendas-$despTotal),'lucro_ant'=>$custosParciaisAnt ? null : R($recAnt-$custoVendasAnt-$despAnt),
        'custos_completos'=>$custosParciais === 0,'vendas_custo_parcial'=>$custosParciais,
        'cr_total'=>R($cr['tot']),'cr_count'=>(int)$cr['cnt'],
        'cp_total'=>R($cp['tot']),'cp_count'=>(int)$cp['cnt'],
        'comis_total'=>R($comis['tot']),'comis_count'=>(int)$comis['cnt'],
        'top_produtos'=>$topProds,
        'receita_vendas'=>R($recVendas),'receita_extras'=>R($recExtras),
        'vendas_count'=>(int)$comercial['vendas'],'unidades_vendidas'=>(int)$comercial['unidades'],
        'ticket_medio'=>R($comercial['ticket']),'ticket_medio_ant'=>R($comercialAnt['ticket']),
        'margem'=>$custosParciais ? null : round($recTotal > 0 ? (($recTotal-$custoVendas-$despTotal)/$recTotal*100) : 0,1),
        'produtos_cruzamento'=>$produtosCruzamento,
        'despesas_categoria'=>$despesasCategoria,
        'agenda_30d'=>$agenda30,
        'receber_atrasado'=>R($receberAtrasado),'pagar_atrasado'=>R($pagarAtrasado),
        'historico_6m'=>$meses6,
        'mes'=>$mes,'ano'=>$ano
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ════════════════════════════════════════════════════════════
// FLUXO DE CAIXA
// ════════════════════════════════════════════════════════════
if ($acao === 'fluxo_caixa') {
    $periodo = (int)($_GET['periodo'] ?? 30);
    $cond = $periodo > 0 ? "AND data >= DATE_SUB(CURDATE(), INTERVAL $periodo DAY)" : "";

    // União de todas as movimentações
    $sql = "
    SELECT data, tipo, descricao, valor, categoria FROM (
        SELECT DATE(criado_em) as data, 'entrada' as tipo,
               CONCAT('Venda: ', COALESCE(p.nome,'Produto')) as descricao,
               total as valor, 'Venda' as categoria
        FROM vendas v LEFT JOIN produtos p ON v.produto_id=p.id
        WHERE v.user_id=? {$cond}
        UNION ALL
        SELECT data_receita, 'entrada', descricao, valor, CONCAT('Receita: ',categoria)
        FROM receitas_extras WHERE user_id=? AND status='recebido' {$cond}
        UNION ALL
        SELECT data_despesa, 'saida', descricao, valor, CONCAT('Despesa: ',categoria)
        FROM despesas WHERE user_id=? AND status='pago' {$cond}
        UNION ALL
        SELECT data_pagamento, 'saida', descricao, valor, CONCAT('Conta: ',COALESCE(categoria,'Outros'))
        FROM contas_pagar WHERE user_id=? AND status='pago' AND data_pagamento IS NOT NULL {$cond}
        UNION ALL
        SELECT data_recebimento, 'entrada', descricao, valor, 'Conta Recebida'
        FROM contas_receber WHERE user_id=? AND status='pago' AND data_recebimento IS NOT NULL {$cond}
    ) t ORDER BY data DESC, tipo";

    $cond2 = $periodo > 0 ? "AND data_recebimento >= DATE_SUB(CURDATE(), INTERVAL $periodo DAY)" : "";
    $s = $pdo->prepare($sql);
    $s->execute([$user_id,$user_id,$user_id,$user_id,$user_id]);
    $movs = $s->fetchAll();

    $totalIn=0; $totalOut=0;
    foreach($movs as $m){
        if($m['tipo']==='entrada') $totalIn+=(float)$m['valor'];
        else $totalOut+=(float)$m['valor'];
    }

    echo json_encode([
        'movimentacoes'=>$movs,
        'total_entradas'=>R($totalIn),
        'total_saidas'=>R($totalOut),
        'saldo'=>R($totalIn-$totalOut)
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ════════════════════════════════════════════════════════════
// CONTAS A RECEBER
// ════════════════════════════════════════════════════════════
if ($acao === 'listar_cr') {
    updateStatus($pdo,'contas_receber',$user_id);
    $status = $_GET['status'] ?? '';
    $periodo = (int)($_GET['periodo'] ?? 0);
    $sql = "SELECT cr.*, fp.nome as forma_nome FROM contas_receber cr
            LEFT JOIN formas_pagamento fp ON cr.forma_pagamento_id=fp.id
            WHERE cr.user_id=?";
    $params = [$user_id];
    if($status) { $sql.=" AND cr.status=?"; $params[]=$status; }
    if($periodo>0) { $sql.=" AND cr.data_vencimento >= DATE_SUB(CURDATE(), INTERVAL ? DAY)"; $params[]=$periodo; }
    $sql.=" ORDER BY cr.data_vencimento ASC";
    $s=$pdo->prepare($sql); $s->execute($params);
    echo json_encode($s->fetchAll(), JSON_UNESCAPED_UNICODE); exit;
}
if ($acao === 'add_cr') {
    $desc   = trim($_POST['descricao'] ?? '');
    $cli    = trim($_POST['cliente'] ?? '');
    $val    = (float)($_POST['valor'] ?? 0);
    $venc   = trim($_POST['data_vencimento'] ?? '');
    $fp     = (int)($_POST['forma_pagamento_id'] ?? 0) ?: null;
    $obs    = trim($_POST['observacao'] ?? '');
    if(!$desc||$val<=0||!$venc){echo json_encode(['ok'=>false,'error'=>'Campos obrigatórios']);exit;}
    $s=$pdo->prepare("INSERT INTO contas_receber (user_id,descricao,cliente,valor,data_vencimento,forma_pagamento_id,observacao) VALUES(?,?,?,?,?,?,?)");
    $s->execute([$user_id,$desc,$cli,$val,$venc,$fp,$obs]);
    echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]); exit;
}
if ($acao === 'edit_cr') {
    $id=(int)($_POST['id']??0); $desc=trim($_POST['descricao']??''); $cli=trim($_POST['cliente']??'');
    $val=(float)($_POST['valor']??0); $venc=trim($_POST['data_vencimento']??'');
    $fp=(int)($_POST['forma_pagamento_id']??0)?:null; $obs=trim($_POST['observacao']??'');
    if($id<=0||!$desc||$val<=0||!$venc){echo json_encode(['ok'=>false,'error'=>'Inválido']);exit;}
    $s=$pdo->prepare("UPDATE contas_receber SET descricao=?,cliente=?,valor=?,data_vencimento=?,forma_pagamento_id=?,observacao=? WHERE id=? AND user_id=?");
    $s->execute([$desc,$cli,$val,$venc,$fp,$obs,$id,$user_id]);
    echo json_encode(['ok'=>true]); exit;
}
if ($acao === 'pagar_cr') {
    $id=(int)($_POST['id']??0); $dt=trim($_POST['data_recebimento']??date('Y-m-d'));
    $fp=(int)($_POST['forma_pagamento_id']??0)?:null;
    if($id<=0){echo json_encode(['ok'=>false,'error'=>'ID inválido']);exit;}
    $s=$pdo->prepare("UPDATE contas_receber SET status='pago',data_recebimento=?,forma_pagamento_id=COALESCE(?,forma_pagamento_id) WHERE id=? AND user_id=?");
    $s->execute([$dt,$fp,$id,$user_id]);
    echo json_encode(['ok'=>true]); exit;
}
if ($acao === 'del_cr') {
    $id=(int)($_POST['id']??0);
    $pdo->prepare("DELETE FROM contas_receber WHERE id=? AND user_id=?")->execute([$id,$user_id]);
    echo json_encode(['ok'=>true]); exit;
}

// ════════════════════════════════════════════════════════════
// CONTAS A PAGAR
// ════════════════════════════════════════════════════════════
if ($acao === 'listar_cp') {
    updateStatus($pdo,'contas_pagar',$user_id);
    $status=$_GET['status']??''; $periodo=(int)($_GET['periodo']??0);
    $sql="SELECT cp.*,fp.nome as forma_nome FROM contas_pagar cp LEFT JOIN formas_pagamento fp ON cp.forma_pagamento_id=fp.id WHERE cp.user_id=?";
    $params=[$user_id];
    if($status){$sql.=" AND cp.status=?";$params[]=$status;}
    if($periodo>0){$sql.=" AND cp.data_vencimento >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";$params[]=$periodo;}
    $sql.=" ORDER BY cp.data_vencimento ASC";
    $s=$pdo->prepare($sql);$s->execute($params);
    echo json_encode($s->fetchAll(), JSON_UNESCAPED_UNICODE); exit;
}
if ($acao === 'add_cp') {
    $desc=trim($_POST['descricao']??''); $forn=trim($_POST['fornecedor']??'');
    $cat=trim($_POST['categoria']??'Outros'); $val=(float)($_POST['valor']??0);
    $venc=trim($_POST['data_vencimento']??''); $fp=(int)($_POST['forma_pagamento_id']??0)?:null;
    $rec=(int)($_POST['recorrente']??0); $obs=trim($_POST['observacao']??'');
    if(!$desc||$val<=0||!$venc){echo json_encode(['ok'=>false,'error'=>'Campos obrigatórios']);exit;}
    $s=$pdo->prepare("INSERT INTO contas_pagar (user_id,descricao,fornecedor,categoria,valor,data_vencimento,forma_pagamento_id,recorrente,observacao) VALUES(?,?,?,?,?,?,?,?,?)");
    $s->execute([$user_id,$desc,$forn,$cat,$val,$venc,$fp,$rec,$obs]);
    echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]); exit;
}
if ($acao === 'edit_cp') {
    $id=(int)($_POST['id']??0); $desc=trim($_POST['descricao']??''); $forn=trim($_POST['fornecedor']??'');
    $cat=trim($_POST['categoria']??'Outros'); $val=(float)($_POST['valor']??0);
    $venc=trim($_POST['data_vencimento']??''); $obs=trim($_POST['observacao']??'');
    if($id<=0||!$desc||$val<=0||!$venc){echo json_encode(['ok'=>false,'error'=>'Inválido']);exit;}
    $s=$pdo->prepare("UPDATE contas_pagar SET descricao=?,fornecedor=?,categoria=?,valor=?,data_vencimento=?,observacao=? WHERE id=? AND user_id=?");
    $s->execute([$desc,$forn,$cat,$val,$venc,$obs,$id,$user_id]);
    echo json_encode(['ok'=>true]); exit;
}
if ($acao === 'pagar_cp') {
    $id=(int)($_POST['id']??0); $dt=trim($_POST['data_pagamento']??date('Y-m-d'));
    $fp=(int)($_POST['forma_pagamento_id']??0)?:null;
    if($id<=0){echo json_encode(['ok'=>false,'error'=>'ID inválido']);exit;}
    $s=$pdo->prepare("UPDATE contas_pagar SET status='pago',data_pagamento=?,forma_pagamento_id=COALESCE(?,forma_pagamento_id) WHERE id=? AND user_id=?");
    $s->execute([$dt,$fp,$id,$user_id]);
    echo json_encode(['ok'=>true]); exit;
}
if ($acao === 'del_cp') {
    $id=(int)($_POST['id']??0);
    $pdo->prepare("DELETE FROM contas_pagar WHERE id=? AND user_id=?")->execute([$id,$user_id]);
    echo json_encode(['ok'=>true]); exit;
}

// ════════════════════════════════════════════════════════════
// DESPESAS
// ════════════════════════════════════════════════════════════
if ($acao === 'listar_despesas') {
    $periodo=(int)($_GET['periodo']??30); $tipo=$_GET['tipo']??''; $cat=$_GET['categoria']??'';
    $sql="SELECT d.*,fp.nome as forma_nome FROM despesas d LEFT JOIN formas_pagamento fp ON d.forma_pagamento_id=fp.id WHERE d.user_id=?";
    $params=[$user_id];
    if($periodo>0){$sql.=" AND d.data_despesa >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";$params[]=$periodo;}
    if($tipo){$sql.=" AND d.tipo=?";$params[]=$tipo;}
    if($cat){$sql.=" AND d.categoria=?";$params[]=$cat;}
    $sql.=" ORDER BY d.data_despesa DESC";
    $s=$pdo->prepare($sql);$s->execute($params);
    $rows=$s->fetchAll();
    // categorias disponíveis
    $sc=$pdo->prepare("SELECT DISTINCT categoria FROM despesas WHERE user_id=? ORDER BY categoria");
    $sc->execute([$user_id]); $cats=array_column($sc->fetchAll(),'categoria');
    echo json_encode(['items'=>$rows,'categorias'=>$cats], JSON_UNESCAPED_UNICODE); exit;
}
if ($acao === 'add_despesa') {
    $desc=trim($_POST['descricao']??''); $cat=trim($_POST['categoria']??'Outros');
    $tipo=$_POST['tipo']??'variavel'; $val=(float)($_POST['valor']??0);
    $data=trim($_POST['data_despesa']??date('Y-m-d')); $fp=(int)($_POST['forma_pagamento_id']??0)?:null;
    $st=$_POST['status']??'pago'; $obs=trim($_POST['observacao']??'');
    if(!$desc||$val<=0){echo json_encode(['ok'=>false,'error'=>'Campos obrigatórios']);exit;}
    $s=$pdo->prepare("INSERT INTO despesas (user_id,descricao,categoria,tipo,valor,data_despesa,forma_pagamento_id,status,observacao) VALUES(?,?,?,?,?,?,?,?,?)");
    $s->execute([$user_id,$desc,$cat,$tipo,$val,$data,$fp,$st,$obs]);
    echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]); exit;
}
if ($acao === 'edit_despesa') {
    $id=(int)($_POST['id']??0); $desc=trim($_POST['descricao']??''); $cat=trim($_POST['categoria']??'Outros');
    $tipo=$_POST['tipo']??'variavel'; $val=(float)($_POST['valor']??0);
    $data=trim($_POST['data_despesa']??''); $st=$_POST['status']??'pago'; $obs=trim($_POST['observacao']??'');
    if($id<=0||!$desc||$val<=0){echo json_encode(['ok'=>false,'error'=>'Inválido']);exit;}
    $s=$pdo->prepare("UPDATE despesas SET descricao=?,categoria=?,tipo=?,valor=?,data_despesa=?,status=?,observacao=? WHERE id=? AND user_id=?");
    $s->execute([$desc,$cat,$tipo,$val,$data,$st,$obs,$id,$user_id]);
    echo json_encode(['ok'=>true]); exit;
}
if ($acao === 'del_despesa') {
    $id=(int)($_POST['id']??0);
    $pdo->prepare("DELETE FROM despesas WHERE id=? AND user_id=?")->execute([$id,$user_id]);
    echo json_encode(['ok'=>true]); exit;
}

// ════════════════════════════════════════════════════════════
// RECEITAS EXTRAS
// ════════════════════════════════════════════════════════════
if ($acao === 'listar_receitas') {
    $periodo=(int)($_GET['periodo']??30);
    $sql="SELECT re.*,fp.nome as forma_nome FROM receitas_extras re LEFT JOIN formas_pagamento fp ON re.forma_pagamento_id=fp.id WHERE re.user_id=?";
    $params=[$user_id];
    if($periodo>0){$sql.=" AND re.data_receita >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";$params[]=$periodo;}
    $sql.=" ORDER BY re.data_receita DESC";
    $s=$pdo->prepare($sql);$s->execute($params);
    $rows=$s->fetchAll();
    $sc=$pdo->prepare("SELECT DISTINCT categoria FROM receitas_extras WHERE user_id=? ORDER BY categoria");
    $sc->execute([$user_id]); $cats=array_column($sc->fetchAll(),'categoria');
    echo json_encode(['items'=>$rows,'categorias'=>$cats], JSON_UNESCAPED_UNICODE); exit;
}
if ($acao === 'add_receita') {
    $desc=trim($_POST['descricao']??''); $cat=trim($_POST['categoria']??'Outros');
    $val=(float)($_POST['valor']??0); $data=trim($_POST['data_receita']??date('Y-m-d'));
    $fp=(int)($_POST['forma_pagamento_id']??0)?:null; $st=$_POST['status']??'recebido'; $obs=trim($_POST['observacao']??'');
    if(!$desc||$val<=0){echo json_encode(['ok'=>false,'error'=>'Campos obrigatórios']);exit;}
    $s=$pdo->prepare("INSERT INTO receitas_extras (user_id,descricao,categoria,valor,data_receita,forma_pagamento_id,status,observacao) VALUES(?,?,?,?,?,?,?,?)");
    $s->execute([$user_id,$desc,$cat,$val,$data,$fp,$st,$obs]);
    echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]); exit;
}
if ($acao === 'edit_receita') {
    $id=(int)($_POST['id']??0); $desc=trim($_POST['descricao']??''); $cat=trim($_POST['categoria']??'Outros');
    $val=(float)($_POST['valor']??0); $data=trim($_POST['data_receita']??''); $st=$_POST['status']??'recebido'; $obs=trim($_POST['observacao']??'');
    if($id<=0||!$desc||$val<=0){echo json_encode(['ok'=>false,'error'=>'Inválido']);exit;}
    $s=$pdo->prepare("UPDATE receitas_extras SET descricao=?,categoria=?,valor=?,data_receita=?,status=?,observacao=? WHERE id=? AND user_id=?");
    $s->execute([$desc,$cat,$val,$data,$st,$obs,$id,$user_id]);
    echo json_encode(['ok'=>true]); exit;
}
if ($acao === 'del_receita') {
    $id=(int)($_POST['id']??0);
    $pdo->prepare("DELETE FROM receitas_extras WHERE id=? AND user_id=?")->execute([$id,$user_id]);
    echo json_encode(['ok'=>true]); exit;
}

// ════════════════════════════════════════════════════════════
// COMISSÕES
// ════════════════════════════════════════════════════════════
if ($acao === 'listar_comissoes') {
    // Config de % por vendedor
    $sc=$pdo->prepare("SELECT cc.*,v.nome as vendedor_nome FROM config_comissoes cc JOIN vendedores v ON cc.vendedor_id=v.id AND v.user_id=cc.user_id WHERE cc.user_id=?");
    $sc->execute([$user_id]); $configs=$sc->fetchAll();
    // Comissões pagas/pendentes registradas
    $sp=$pdo->prepare("SELECT cp.*,v.nome as vendedor_nome FROM comissoes_pagas cp JOIN vendedores v ON cp.vendedor_id=v.id AND v.user_id=cp.user_id WHERE cp.user_id=? ORDER BY cp.criado_em DESC");
    $sp->execute([$user_id]); $pagas=$sp->fetchAll();
    // Vendas por vendedor (total não pago em comissão)
    $periodo=(int)($_GET['periodo']??30);
    $sv=$pdo->prepare("
        SELECT v.vendedor_id, vend.nome as vendedor_nome, SUM(v.total) as total_vendas, COUNT(*) as qtd_vendas
        FROM vendas v JOIN vendedores vend ON v.vendedor_id=vend.id AND vend.user_id=v.user_id
        WHERE v.user_id=? AND v.vendedor_id IS NOT NULL
          AND v.criado_em >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY v.vendedor_id, vend.nome ORDER BY total_vendas DESC");
    $sv->execute([$user_id,$periodo]); $vendas=$sv->fetchAll();
    // Agregar
    $configMap=[];
    foreach($configs as $c) $configMap[$c['vendedor_id']]=$c;
    foreach($vendas as &$v){
        $pct = $configMap[$v['vendedor_id']]['percentual'] ?? 0;
        $v['percentual']=R($pct);
        $v['comissao_periodo']=R((float)$v['total_vendas']*$pct/100);
    }
    echo json_encode(['configs'=>$configs,'pagas'=>$pagas,'vendas_periodo'=>$vendas], JSON_UNESCAPED_UNICODE); exit;
}
if ($acao === 'set_config_comissao') {
    $vid=(int)($_POST['vendedor_id']??0); $pct=(float)($_POST['percentual']??0);
    if($vid<=0||$pct<0||$pct>100){echo json_encode(['ok'=>false,'error'=>'Inválido']);exit;}
    $seller=$pdo->prepare('SELECT id FROM vendedores WHERE id=? AND user_id=? LIMIT 1');
    $seller->execute([$vid,$user_id]);
    if(!$seller->fetch()){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Operação negada']);exit;}
    $s=$pdo->prepare("INSERT INTO config_comissoes (user_id,vendedor_id,percentual) VALUES(?,?,?) ON DUPLICATE KEY UPDATE percentual=?");
    $s->execute([$user_id,$vid,$pct,$pct]);
    echo json_encode(['ok'=>true]); exit;
}
if ($acao === 'registrar_comissao') {
    $vid=(int)($_POST['vendedor_id']??0); $ini=trim($_POST['periodo_inicio']??''); $fim=trim($_POST['periodo_fim']??'');
    $val=(float)($_POST['total_vendas']??0); $pct=(float)($_POST['percentual']??0); $obs=trim($_POST['observacao']??'');
    if($vid<=0||!$ini||!$fim){echo json_encode(['ok'=>false,'error'=>'Inválido']);exit;}
    $seller=$pdo->prepare('SELECT id FROM vendedores WHERE id=? AND user_id=? LIMIT 1');
    $seller->execute([$vid,$user_id]);
    if(!$seller->fetch()){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Operação negada']);exit;}
    $comis=R($val*$pct/100);
    $s=$pdo->prepare("INSERT INTO comissoes_pagas (user_id,vendedor_id,periodo_inicio,periodo_fim,total_vendas,percentual,valor_comissao,observacao) VALUES(?,?,?,?,?,?,?,?)");
    $s->execute([$user_id,$vid,$ini,$fim,$val,$pct,$comis,$obs]);
    echo json_encode(['ok'=>true,'valor_comissao'=>$comis]); exit;
}
if ($acao === 'pagar_comissao') {
    $id=(int)($_POST['id']??0); $dt=trim($_POST['data_pagamento']??date('Y-m-d'));
    if($id<=0){echo json_encode(['ok'=>false,'error'=>'ID inválido']);exit;}
    $pdo->prepare("UPDATE comissoes_pagas SET status='pago',data_pagamento=? WHERE id=? AND user_id=?")->execute([$dt,$id,$user_id]);
    echo json_encode(['ok'=>true]); exit;
}
if ($acao === 'del_comissao') {
    $id=(int)($_POST['id']??0);
    $pdo->prepare("DELETE FROM comissoes_pagas WHERE id=? AND user_id=?")->execute([$id,$user_id]);
    echo json_encode(['ok'=>true]); exit;
}

// ════════════════════════════════════════════════════════════
// DRE — Demonstrativo de Resultado do Exercício
// ════════════════════════════════════════════════════════════
if ($acao === 'dre') {
    $mes=(int)($_GET['mes']??date('n')); $ano=(int)($_GET['ano']??date('Y'));
    // Receita bruta de vendas
    $s=$pdo->prepare("SELECT COALESCE(SUM(total),0) FROM vendas WHERE user_id=? AND MONTH(criado_em)=? AND YEAR(criado_em)=?");
    $s->execute([$user_id,$mes,$ano]); $recVendas=(float)$s->fetchColumn();
    $s=$pdo->prepare("SELECT COALESCE(SUM(quantidade * custo_unitario),0) FROM vendas WHERE user_id=? AND MONTH(criado_em)=? AND YEAR(criado_em)=?");
    $s->execute([$user_id,$mes,$ano]); $custoVendas=(float)$s->fetchColumn();
    $s=$pdo->prepare("SELECT COUNT(*) FROM vendas WHERE user_id=? AND MONTH(criado_em)=? AND YEAR(criado_em)=? AND custo_unitario=0");
    $s->execute([$user_id,$mes,$ano]); $vendasSemCusto=(int)$s->fetchColumn();
    $vendasCustoParcial=financeIncompleteSales($pdo,$user_id,$mes,$ano,$custoCompletoColumn);
    // Receitas extras
    $s=$pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM receitas_extras WHERE user_id=? AND MONTH(data_receita)=? AND YEAR(data_receita)=? AND status='recebido'");
    $s->execute([$user_id,$mes,$ano]); $recExtras=(float)$s->fetchColumn();
    // Despesas fixas pagas no mês
    $s=$pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM despesas WHERE user_id=? AND MONTH(data_despesa)=? AND YEAR(data_despesa)=? AND tipo='fixa' AND status='pago'");
    $s->execute([$user_id,$mes,$ano]); $despFixas=(float)$s->fetchColumn();
    // Despesas variáveis pagas no mês
    $s=$pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM despesas WHERE user_id=? AND MONTH(data_despesa)=? AND YEAR(data_despesa)=? AND tipo='variavel' AND status='pago'");
    $s->execute([$user_id,$mes,$ano]); $despVar=(float)$s->fetchColumn();
    // Contas a pagar pagas no mês
    $s=$pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM contas_pagar WHERE user_id=? AND MONTH(data_pagamento)=? AND YEAR(data_pagamento)=? AND status='pago'");
    $s->execute([$user_id,$mes,$ano]); $contasPagas=(float)$s->fetchColumn();
    // Comissões pagas no mês
    $s=$pdo->prepare("SELECT COALESCE(SUM(valor_comissao),0) FROM comissoes_pagas WHERE user_id=? AND MONTH(data_pagamento)=? AND YEAR(data_pagamento)=? AND status='pago'");
    $s->execute([$user_id,$mes,$ano]); $comissPagas=(float)$s->fetchColumn();
    // Despesas por categoria
    $s=$pdo->prepare("SELECT categoria, SUM(valor) as total FROM despesas WHERE user_id=? AND MONTH(data_despesa)=? AND YEAR(data_despesa)=? AND status='pago' GROUP BY categoria ORDER BY total DESC");
    $s->execute([$user_id,$mes,$ano]); $despCats=$s->fetchAll();

    $recBruta=$recVendas+$recExtras;
    $totalDesp=$despFixas+$despVar+$contasPagas+$comissPagas;
    $custosCompletos=$vendasCustoParcial===0;
    $lucroBruto=$custosCompletos?$recVendas-$custoVendas:null;
    $lucro=$custosCompletos?$recBruta-$custoVendas-$totalDesp:null;
    $margem=$custosCompletos&&$recBruta>0?($lucro/$recBruta*100):null;

    echo json_encode([
        'receita_vendas'=>R($recVendas),'receita_extras'=>R($recExtras),'receita_bruta'=>R($recBruta),
        'desp_fixas'=>R($despFixas),'desp_variaveis'=>R($despVar),
        'contas_pagas'=>R($contasPagas),'comissoes'=>R($comissPagas),
        'custo_vendas'=>R($custoVendas),'lucro_bruto'=>$lucroBruto===null?null:R($lucroBruto),
        'vendas_sem_custo'=>$vendasSemCusto,'vendas_custo_parcial'=>$vendasCustoParcial,'custos_completos'=>$custosCompletos,
        'total_despesas'=>R($totalDesp),
        'lucro_liquido'=>$lucro===null?null:R($lucro),'margem_lucro'=>$margem===null?null:round($margem,1),
        'desp_por_categoria'=>$despCats,
        'mes'=>$mes,'ano'=>$ano
    ], JSON_UNESCAPED_UNICODE); exit;
}

// ════════════════════════════════════════════════════════════
// FORMAS DE PAGAMENTO
// ════════════════════════════════════════════════════════════
if ($acao === 'listar_fp') {
    $s=$pdo->prepare("SELECT * FROM formas_pagamento WHERE user_id=? ORDER BY nome");
    $s->execute([$user_id]); $rows=$s->fetchAll();
    // Se vazio, semeia defaults
    if(empty($rows)){
        $defaults=[['Dinheiro','payments',0],['Pix','pix',0],['Cartão Crédito','credit_card',2.5],['Cartão Débito','credit_card',1.5],['Boleto','receipt',0],['Parcelado','calendar_month',0]];
        $si=$pdo->prepare("INSERT INTO formas_pagamento (user_id,nome,icone,taxa_percentual) VALUES(?,?,?,?)");
        foreach($defaults as $d){$si->execute([$user_id,$d[0],$d[1],$d[2]]);}
        $s->execute([$user_id]); $rows=$s->fetchAll();
    }
    echo json_encode($rows, JSON_UNESCAPED_UNICODE); exit;
}
if ($acao === 'add_fp') {
    $nome=trim($_POST['nome']??''); $icone=trim($_POST['icone']??'payments'); $taxa=(float)($_POST['taxa_percentual']??0);
    if(!$nome){echo json_encode(['ok'=>false,'error'=>'Nome obrigatório']);exit;}
    $s=$pdo->prepare("INSERT INTO formas_pagamento (user_id,nome,icone,taxa_percentual) VALUES(?,?,?,?)");
    $s->execute([$user_id,$nome,$icone,$taxa]);
    echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]); exit;
}
if ($acao === 'edit_fp') {
    $id=(int)($_POST['id']??0); $nome=trim($_POST['nome']??''); $icone=trim($_POST['icone']??'payments'); $taxa=(float)($_POST['taxa_percentual']??0);
    if($id<=0||!$nome){echo json_encode(['ok'=>false,'error'=>'Inválido']);exit;}
    $pdo->prepare("UPDATE formas_pagamento SET nome=?,icone=?,taxa_percentual=? WHERE id=? AND user_id=?")->execute([$nome,$icone,$taxa,$id,$user_id]);
    echo json_encode(['ok'=>true]); exit;
}
if ($acao === 'del_fp') {
    $id=(int)($_POST['id']??0);
    $pdo->prepare("DELETE FROM formas_pagamento WHERE id=? AND user_id=?")->execute([$id,$user_id]);
    echo json_encode(['ok'=>true]); exit;
}

// ════════════════════════════════════════════════════════════
// RELATÓRIOS FINANCEIROS
// ════════════════════════════════════════════════════════════
if ($acao === 'relatorio_financeiro') {
    $periodo=(int)($_GET['periodo']??90);
    $partialCostCondition = $custoCompletoColumn
        ? '(custo_completo = 0 OR (custo_completo IS NULL AND custo_unitario = 0))'
        : 'custo_unitario = 0';
    // Receita por mês (últimos N dias)
    $s=$pdo->prepare("
        SELECT DATE_FORMAT(criado_em,'%Y-%m') as mes_ano,
               DATE_FORMAT(criado_em,'%b/%y') as label,
               SUM(total) as receita, SUM(quantidade * custo_unitario) as custo, SUM((preco_unitario - custo_unitario) * quantidade) as lucro, COUNT(*) as qtd,
               SUM(CASE WHEN {$partialCostCondition} THEN 1 ELSE 0 END) as vendas_custo_parcial
        FROM vendas WHERE user_id=? AND criado_em >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY mes_ano, label ORDER BY mes_ano");
    $s->execute([$user_id,$periodo]); $recMes=$s->fetchAll();
    foreach ($recMes as &$monthRow) {
        $monthRow['custos_completos'] = (int)$monthRow['vendas_custo_parcial'] === 0;
        if (!$monthRow['custos_completos']) $monthRow['lucro'] = null;
    }
    unset($monthRow);
    // Lucro por produto
    $s=$pdo->prepare("
        SELECT p.nome, SUM(v.total) as receita, SUM(v.quantidade * v.custo_unitario) as custo, SUM((v.preco_unitario - v.custo_unitario) * v.quantidade) as lucro, SUM(v.quantidade) as unidades,
               SUM(CASE WHEN " . str_replace(['custo_completo', 'custo_unitario'], ['v.custo_completo', 'v.custo_unitario'], $partialCostCondition) . " THEN 1 ELSE 0 END) as vendas_custo_parcial
        FROM vendas v JOIN produtos p ON v.produto_id=p.id
        WHERE v.user_id=? AND v.criado_em >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY p.id, p.nome ORDER BY receita DESC LIMIT 10");
    $s->execute([$user_id,$periodo]); $lucProd=$s->fetchAll();
    foreach ($lucProd as &$productRow) {
        $productRow['custos_completos'] = (int)$productRow['vendas_custo_parcial'] === 0;
        if (!$productRow['custos_completos']) $productRow['lucro'] = null;
    }
    unset($productRow);
    // Despesas por categoria
    $s=$pdo->prepare("
        SELECT categoria, SUM(valor) as total FROM despesas
        WHERE user_id=? AND data_despesa >= DATE_SUB(CURDATE(), INTERVAL ? DAY) AND status='pago'
        GROUP BY categoria ORDER BY total DESC");
    $s->execute([$user_id,$periodo]); $despCat=$s->fetchAll();
    // Ticket médio por vendedor
    $s=$pdo->prepare("
        SELECT vend.nome, COUNT(*) as vendas, SUM(v.total) as receita, AVG(v.total) as ticket
        FROM vendas v JOIN vendedores vend ON v.vendedor_id=vend.id
        WHERE v.user_id=? AND v.criado_em >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY v.vendedor_id, vend.nome ORDER BY receita DESC");
    $s->execute([$user_id,$periodo]); $vendedores=$s->fetchAll();
    // Formas de pagamento mais usadas (vendas declaradas com observacao como proxy)
    echo json_encode([
        'receita_por_mes'=>$recMes,
        'top_produtos'=>$lucProd,
        'despesas_categoria'=>$despCat,
        'vendedores'=>$vendedores
    ], JSON_UNESCAPED_UNICODE); exit;
}

echo json_encode(['ok'=>false,'error'=>"Ação '$acao' não encontrada"]);
