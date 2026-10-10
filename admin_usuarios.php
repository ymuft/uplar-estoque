<?php
// admin_usuarios.php — COM GESTÃO DE ASSINATURAS
session_start();
require 'db.php';
require 'helpers.php';
require 'subscription.php';
require_once __DIR__ . '/includes/tracking_runtime.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php"); exit;
}
get_csrf_token();

$user_id  = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];
$pdo      = get_pdo();

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$me = $stmt->fetch();
if (!$me || $me['role'] !== 'admin') { header("Location: estoque.php"); exit; }

$whatsapp_number = '';
$wq = $pdo->prepare("SELECT whatsapp_number FROM users WHERE id = ?");
$wq->execute([$user_id]);
$wr = $wq->fetch();
if ($wr) $whatsapp_number = $wr['whatsapp_number'] ?? '';

// Busca usuários com dados de assinatura
$usuarios = $pdo->query("
    SELECT u.id, u.username, u.role, u.whatsapp_number,
        DATE_FORMAT(u.created_at, '%d/%m/%Y %H:%i') AS data_criacao,
        COALESCE(p.total_produtos, 0) AS total_produtos,
        COALESCE(v.total_vendas, 0) AS total_vendas,
        COALESCE(v.receita_total, 0) AS receita_total,
        -- Assinatura mais recente
        (SELECT i.status   FROM inscricoes i WHERE i.user_id = u.id ORDER BY i.data_fim DESC LIMIT 1) AS sub_status,
        (SELECT i.data_fim FROM inscricoes i WHERE i.user_id = u.id ORDER BY i.data_fim DESC LIMIT 1) AS sub_data_fim
    FROM users u
    LEFT JOIN (
        SELECT user_id, COUNT(*) AS total_produtos
        FROM produtos
        GROUP BY user_id
    ) p ON p.user_id = u.id
    LEFT JOIN (
        SELECT user_id, COUNT(*) AS total_vendas, COALESCE(SUM(total), 0) AS receita_total
        FROM vendas
        GROUP BY user_id
    ) v ON v.user_id = u.id
    ORDER BY u.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Calcula sub_status atual (expiração por data)
$hoje = new DateTime('today');
foreach ($usuarios as &$u) {
    if ($u['role'] === 'admin') {
        $u['sub_status_calc'] = 'admin';
        $u['sub_dias']        = null;
    } elseif (!$u['sub_data_fim']) {
        $u['sub_status_calc'] = 'sem_inscricao';
        $u['sub_dias']        = null;
    } else {
        $fim  = new DateTime($u['sub_data_fim']);
        $diff = (int)$hoje->diff($fim)->format('%r%a');
        if ($u['sub_status'] === 'suspensa') {
            $u['sub_status_calc'] = 'suspensa';
            $u['sub_dias']        = 0;
        } elseif ($diff < 0 || $u['sub_status'] === 'expirada') {
            $u['sub_status_calc'] = 'expirada';
            $u['sub_dias']        = 0;
        } else {
            $u['sub_status_calc'] = 'ativa';
            $u['sub_dias']        = $diff;
        }
    }
}
unset($u);

$total    = count($usuarios);
$admins   = count(array_filter($usuarios, fn($u) => $u['role'] === 'admin'));
$regular  = $total - $admins;
$sub_ativas = count(array_filter($usuarios, fn($u) => $u['sub_status_calc'] === 'ativa'));
$sub_expirando = count(array_filter($usuarios, fn($u) => $u['sub_status_calc'] === 'ativa' && (int)$u['sub_dias'] <= 7));

// Receita da Uplar: considera somente pedidos de assinatura confirmados.
$financeiro_saas = [
    'total_centavos' => 0,
    'mes_centavos' => 0,
    'mes_anterior_centavos' => 0,
    'ultimos_30_centavos' => 0,
    'pagamentos_aprovados' => 0,
    'checkouts_total' => 0,
    'checkouts_pendentes' => 0,
    'ticket_medio_centavos' => 0,
];
$receita_mensal = [];
$receita_planos = [];
$receita_metodos = [];
$pagamentos_recentes = [];
$pagamentos_ocultos = [];
$pagamentos_ocultos_total = 0;

try {
    $financeiro_saas = $pdo->query("
        SELECT
            COALESCE(SUM(CASE WHEN status = 'pago' THEN COALESCE(gateway_amount_centavos, valor_centavos) ELSE 0 END), 0) AS total_centavos,
            COALESCE(SUM(CASE WHEN status = 'pago' AND pago_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN COALESCE(gateway_amount_centavos, valor_centavos) ELSE 0 END), 0) AS mes_centavos,
            COALESCE(SUM(CASE WHEN status = 'pago' AND pago_em >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01') AND pago_em < DATE_FORMAT(CURDATE(), '%Y-%m-01') THEN COALESCE(gateway_amount_centavos, valor_centavos) ELSE 0 END), 0) AS mes_anterior_centavos,
            COALESCE(SUM(CASE WHEN status = 'pago' AND pago_em >= CURDATE() - INTERVAL 29 DAY THEN COALESCE(gateway_amount_centavos, valor_centavos) ELSE 0 END), 0) AS ultimos_30_centavos,
            SUM(status = 'pago') AS pagamentos_aprovados,
            COUNT(*) AS checkouts_total,
            SUM(status = 'pendente') AS checkouts_pendentes,
            COALESCE(AVG(CASE WHEN status = 'pago' THEN COALESCE(gateway_amount_centavos, valor_centavos) END), 0) AS ticket_medio_centavos
        FROM pedidos_pagamento
        WHERE admin_oculto = 0
    ")->fetch(PDO::FETCH_ASSOC) ?: $financeiro_saas;

    $inicio_grafico = (new DateTimeImmutable('first day of this month'))->modify('-5 months')->format('Y-m-d');
    $stmt_receita_mensal = $pdo->prepare("
        SELECT DATE_FORMAT(pago_em, '%Y-%m') AS mes,
               COUNT(*) AS pagamentos,
               SUM(COALESCE(gateway_amount_centavos, valor_centavos)) AS total_centavos
        FROM pedidos_pagamento
        WHERE status = 'pago' AND admin_oculto = 0 AND pago_em >= ?
        GROUP BY DATE_FORMAT(pago_em, '%Y-%m')
    ");
    $stmt_receita_mensal->execute([$inicio_grafico]);
    $mensal_por_chave = [];
    foreach ($stmt_receita_mensal->fetchAll(PDO::FETCH_ASSOC) as $linha) {
        $mensal_por_chave[$linha['mes']] = $linha;
    }
    $nomes_meses = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];
    for ($i = 5; $i >= 0; $i--) {
        $data_mes = (new DateTimeImmutable('first day of this month'))->modify("-{$i} months");
        $chave = $data_mes->format('Y-m');
        $linha = $mensal_por_chave[$chave] ?? ['pagamentos' => 0, 'total_centavos' => 0];
        $receita_mensal[] = [
            'mes' => $nomes_meses[(int)$data_mes->format('n')],
            'pagamentos' => (int)$linha['pagamentos'],
            'total_centavos' => (int)$linha['total_centavos'],
        ];
    }

    $receita_planos = $pdo->query("
        SELECT plano_key, COUNT(*) AS pagamentos,
               SUM(COALESCE(gateway_amount_centavos, valor_centavos)) AS total_centavos
        FROM pedidos_pagamento
        WHERE status = 'pago' AND admin_oculto = 0
        GROUP BY plano_key
        ORDER BY total_centavos DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $receita_metodos = $pdo->query("
        SELECT COALESCE(NULLIF(capture_method, ''), 'nao_informado') AS metodo,
               COUNT(*) AS pagamentos,
               SUM(COALESCE(gateway_amount_centavos, valor_centavos)) AS total_centavos
        FROM pedidos_pagamento
        WHERE status = 'pago' AND admin_oculto = 0
        GROUP BY COALESCE(NULLIF(capture_method, ''), 'nao_informado')
        ORDER BY total_centavos DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $pagamentos_recentes = $pdo->query("
        SELECT pp.id, pp.order_nsu, pp.plano_key, pp.capture_method, pp.installments,
               pp.transaction_nsu, pp.receipt_url,
               COALESCE(pp.gateway_amount_centavos, pp.valor_centavos) AS valor_centavos,
               DATE_FORMAT(pp.pago_em, '%d/%m/%Y às %H:%i') AS pago_em_fmt,
               u.id AS user_id, u.username, u.email
        FROM pedidos_pagamento pp
        LEFT JOIN users u ON u.id = pp.user_id
        WHERE pp.status = 'pago' AND pp.admin_oculto = 0
        ORDER BY pp.pago_em DESC
        LIMIT 100
    ")->fetchAll(PDO::FETCH_ASSOC);

    $pagamentos_ocultos = $pdo->query("
        SELECT pp.id, pp.order_nsu, pp.plano_key, pp.capture_method, pp.installments,
               COALESCE(pp.gateway_amount_centavos, pp.valor_centavos) AS valor_centavos,
               DATE_FORMAT(pp.pago_em, '%d/%m/%Y às %H:%i') AS pago_em_fmt,
               DATE_FORMAT(pp.admin_oculto_em, '%d/%m/%Y às %H:%i') AS oculto_em_fmt,
               u.username, u.email, admin.username AS oculto_por_nome
        FROM pedidos_pagamento pp
        LEFT JOIN users u ON u.id = pp.user_id
        LEFT JOIN users admin ON admin.id = pp.admin_oculto_por
        WHERE pp.status = 'pago' AND pp.admin_oculto = 1
        ORDER BY pp.admin_oculto_em DESC, pp.pago_em DESC
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
    $pagamentos_ocultos_total = (int)$pdo->query("
        SELECT COUNT(*) FROM pedidos_pagamento WHERE status = 'pago' AND admin_oculto = 1
    ")->fetchColumn();
} catch (Throwable $e) {
    error_log('[admin_usuarios] Falha ao carregar indicadores financeiros: ' . $e->getMessage());
}

$financeiro_saas = array_map(static fn($valor) => (int)round((float)$valor), $financeiro_saas);
$crescimento_mes = null;
if ($financeiro_saas['mes_anterior_centavos'] > 0) {
    $crescimento_mes = (($financeiro_saas['mes_centavos'] - $financeiro_saas['mes_anterior_centavos']) / $financeiro_saas['mes_anterior_centavos']) * 100;
}
$taxa_conversao = $financeiro_saas['checkouts_total'] > 0
    ? ($financeiro_saas['pagamentos_aprovados'] / $financeiro_saas['checkouts_total']) * 100
    : 0;

// Conta assinaturas vencidas/sem assinatura (só users)
$sub_alerta = count(array_filter($usuarios, fn($u) => in_array($u['sub_status_calc'], ['expirada','sem_inscricao'])));

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

// Helper para badge de assinatura
function sub_badge(array $u): string {
    $s = $u['sub_status_calc'];
    if ($s === 'admin')         return '<span class="sub-badge admin"><span class="material-symbols-outlined">shield</span>Admin</span>';
    if ($s === 'ativa') {
        $d = (int)$u['sub_dias'];
        $cls = $d <= 7 ? 'expirando' : 'ativa';
        $lbl = $d <= 7 ? "Expira em {$d}d" : fmt_date_br($u['sub_data_fim']);
        return '<span class="sub-badge '.$cls.'"><span class="material-symbols-outlined">'.($d<=7?'warning':'check_circle').'</span>'.$lbl.'</span>';
    }
    if ($s === 'expirada')      return '<span class="sub-badge expirada"><span class="material-symbols-outlined">event_busy</span>Expirada</span>';
    if ($s === 'suspensa')      return '<span class="sub-badge suspensa"><span class="material-symbols-outlined">pause_circle</span>Suspensa</span>';
    return '<span class="sub-badge sem"><span class="material-symbols-outlined">lock</span>Sem assinatura</span>';
}

function money_from_cents(int $centavos): string {
    return 'R$ ' . number_format($centavos / 100, 2, ',', '.');
}

function payment_method_label(?string $method): string {
    return match ($method) {
        'pix' => 'Pix',
        'credit_card' => 'Cartão de crédito',
        'debit_card' => 'Cartão de débito',
        default => 'Não informado',
    };
}

function plan_label(?string $plan): string {
    return match ($plan) {
        'mensal' => 'Mensal',
        'trimestral' => 'Trimestral',
        'semestral' => 'Semestral',
        'anual' => 'Anual',
        'teste' => 'Teste',
        default => $plan ? ucfirst($plan) : 'Plano',
    };
}

function safe_infinitepay_receipt(?string $url): string {
    if (!$url || strlen($url) > 512 || !filter_var($url, FILTER_VALIDATE_URL)) return '';
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') return '';
    $host = strtolower((string)($parts['host'] ?? ''));
    $valid = $host === 'recibo.infinitepay.io'
        || str_ends_with($host, '.infinitepay.io')
        || str_ends_with($host, '.infinitepay.com.br');
    return $valid ? $url : '';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Usuários — Uplar Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"/>
<script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
<style>
:root{
    --p:#3b6ff5;--p-dark:#2a55d9;--p-dim:rgba(59,111,245,.08);--p-glow:rgba(59,111,245,.20);
    --ink:#0f1c2e;--muted:#5a6a7a;--faint:#94a3b8;--bg:#f4f6fb;--card:#fff;--border:#e4eaf2;
    --ok:#10b981;--ok-dim:rgba(16,185,129,.09);
    --warn:#f59e0b;--warn-dim:rgba(245,158,11,.09);
    --err:#ef4444;--err-dim:rgba(239,68,68,.09);
    --violet:#7c3aed;--violet-dim:rgba(124,58,237,.08);
    --sh:0 1px 4px rgba(15,28,46,.06),0 4px 16px rgba(15,28,46,.04);
    --sh-lift:0 4px 24px rgba(59,111,245,.14);--r:12px;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--ink);line-height:1.55;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none} button{font-family:inherit;cursor:pointer}

@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes popIn{from{opacity:0;transform:scale(.94)}to{opacity:1;transform:scale(1)}}
@keyframes toastOut{to{opacity:0;transform:translateX(20px)}}
@keyframes spin{to{transform:rotate(360deg)}}
.anim{opacity:0;animation:fadeUp .5s cubic-bezier(.22,1,.36,1) forwards}
.d1{animation-delay:.04s}.d2{animation-delay:.10s}.d3{animation-delay:.16s}.d4{animation-delay:.22s}

.app{display:flex;min-height:100vh}

/* ── Sidebar ── */
.sb{width:248px;background:var(--card);border-right:1px solid var(--border);position:fixed;left:0;top:0;height:100vh;overflow-y:auto;z-index:200;display:flex;flex-direction:column;transition:transform .3s}
.sb-head{padding:1.4rem 1.3rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.sb-logo-icon{font-size:2rem;color:var(--p)}
.sb-logo-txt{font-size:1.25rem;font-weight:700;letter-spacing:-.3px}
.sb-user{padding:1rem 1.3rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.8rem}
.sb-av{width:36px;height:36px;border-radius:9px;flex-shrink:0;background:linear-gradient(135deg,var(--p),#7ca0ff);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;box-shadow:0 3px 10px var(--p-glow)}
.sb-uname{font-weight:600;font-size:.85rem}.sb-urole{font-size:.7rem;color:var(--faint);margin-top:1px}
.sb-nav{padding:.9rem .7rem;flex:1}
.sb-lbl{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--faint);padding:0 .7rem;margin:.9rem 0 .4rem}
.sb-link{display:flex;align-items:center;gap:.7rem;padding:.65rem .85rem;border-radius:9px;color:var(--muted);font-weight:500;font-size:.845rem;transition:all .18s;margin-bottom:2px}
.sb-link:hover{background:var(--p-dim);color:var(--p)}
.sb-link.on{background:var(--p-dim);color:var(--p);font-weight:600}
.sb-link .material-symbols-outlined{font-size:1.15rem;width:20px;text-align:center}
.sb-badge{display:inline-flex;align-items:center;gap:.3rem;font-size:.62rem;font-weight:700;padding:.15rem .5rem;border-radius:6px;background:linear-gradient(135deg,var(--violet),#a855f7);color:#fff;margin-left:auto}
.overlay{display:none;position:fixed;inset:0;background:rgba(15,28,46,.4);z-index:199;backdrop-filter:blur(3px)}
.overlay.on{display:block}
.ham{display:none;background:var(--card);border:1px solid var(--border);border-radius:9px;padding:.42rem;align-items:center;justify-content:center;box-shadow:var(--sh)}
.ham .material-symbols-outlined{font-size:1.3rem}

.main{margin-left:248px;flex:1;padding:1.75rem 2rem;min-width:0}

.top{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.4rem;gap:1rem;flex-wrap:wrap}
.top-l{display:flex;align-items:center;gap:.8rem}
.greeting{font-size:1.45rem;font-weight:700;letter-spacing:-.4px}
.sub{color:var(--muted);font-size:.82rem;margin-top:1px}
.searchbox{display:flex;align-items:center;gap:.55rem;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:.52rem .95rem;transition:border-color .2s,box-shadow .2s;min-width:210px}
.searchbox:focus-within{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.searchbox .material-symbols-outlined{font-size:1.05rem;color:var(--faint)}
.searchbox input{border:none;outline:none;font-size:.855rem;width:100%;background:transparent;color:var(--ink)}

.pills{display:flex;gap:.5rem;margin-bottom:1.4rem;flex-wrap:wrap}
.pill{display:inline-flex;align-items:center;gap:.4rem;padding:.52rem 1rem;border-radius:50px;font-size:.8rem;font-weight:600;border:none;transition:all .22s;white-space:nowrap}
.pill .material-symbols-outlined{font-size:1rem}
.pill-solid{background:var(--p);color:#fff;box-shadow:0 3px 12px var(--p-glow)}
.pill-solid:hover{background:var(--p-dark);transform:translateY(-2px)}
.pill-outline{background:var(--card);color:var(--muted);border:1px solid var(--border)}
.pill-outline:hover{background:var(--p-dim);border-color:var(--p);color:var(--p);transform:translateY(-2px)}

/* KPIs */
.kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:.85rem;margin-bottom:1.3rem}
.kpi{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:1rem 1.1rem;position:relative;overflow:hidden;transition:transform .22s,box-shadow .22s}
.kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--p);border-radius:var(--r) var(--r) 0 0}
.kpi.g::before{background:var(--ok)}.kpi.o::before{background:var(--warn)}.kpi.v::before{background:var(--violet)}.kpi.r::before{background:var(--err)}
.kpi:hover{transform:translateY(-3px);box-shadow:var(--sh-lift)}
.kpi-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:.55rem}
.kpi-ico{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:var(--p-dim);color:var(--p)}
.kpi.g .kpi-ico{background:var(--ok-dim);color:var(--ok)}.kpi.o .kpi-ico{background:var(--warn-dim);color:var(--warn)}.kpi.v .kpi-ico{background:var(--violet-dim);color:var(--violet)}.kpi.r .kpi-ico{background:var(--err-dim);color:var(--err)}
.kpi-ico .material-symbols-outlined{font-size:1.05rem}
.kpi-tag{font-size:.67rem;font-weight:700;padding:.15rem .5rem;border-radius:20px;background:var(--p-dim);color:var(--p)}
.kpi.g .kpi-tag{background:var(--ok-dim);color:var(--ok)}.kpi.o .kpi-tag{background:var(--warn-dim);color:var(--warn)}.kpi.v .kpi-tag{background:var(--violet-dim);color:var(--violet)}.kpi.r .kpi-tag{background:var(--err-dim);color:var(--err)}
.kpi-val{font-size:1.6rem;font-weight:700;letter-spacing:-.4px;line-height:1;margin-bottom:.18rem}
.kpi-lbl{font-size:.73rem;color:var(--muted);font-weight:500}

/* Visão executiva financeira */
.exec-head{background:#101828;color:#fff;border:1px solid #1d2939;border-radius:10px;padding:1.2rem 1.35rem;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem}
.exec-eyebrow{font-size:.67rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#e0b84f;margin-bottom:.2rem}
.exec-title{font-size:1.25rem;font-weight:700;letter-spacing:-.02em}
.exec-sub{font-size:.76rem;color:#98a2b3;margin-top:.15rem}
.exec-date{text-align:right;font-size:.7rem;color:#98a2b3;white-space:nowrap}
.exec-date strong{display:block;color:#fff;font-size:.78rem;margin-bottom:.1rem}
.fin-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem;margin-bottom:.9rem}
.fin-kpi{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:1rem;min-height:118px;display:flex;flex-direction:column;justify-content:space-between;box-shadow:var(--sh)}
.fin-kpi.total{grid-column:span 2;background:#101828;border-color:#101828;color:#fff;position:relative;overflow:hidden}
.fin-kpi.total::after{content:'';position:absolute;width:110px;height:110px;border:22px solid rgba(224,184,79,.13);border-radius:50%;right:-42px;top:-45px}
.fin-kpi-head{display:flex;align-items:center;justify-content:space-between;gap:.5rem;font-size:.7rem;font-weight:600;color:var(--muted)}
.fin-kpi.total .fin-kpi-head{color:#98a2b3}
.fin-kpi-icon{width:30px;height:30px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--p);background:var(--bg);border-radius:7px}
.fin-kpi.total .fin-kpi-icon{border-color:rgba(224,184,79,.35);background:rgba(224,184,79,.1);color:#e0b84f}
.fin-kpi-icon .material-symbols-outlined{font-size:1rem}
.fin-kpi-value{font-size:1.45rem;font-weight:700;letter-spacing:-.04em;line-height:1;margin-top:.65rem;position:relative;z-index:1}
.fin-kpi.total .fin-kpi-value{font-size:2rem}
.fin-kpi-foot{font-size:.66rem;color:var(--faint);margin-top:.35rem}
.fin-kpi.total .fin-kpi-foot{color:#98a2b3}
.trend{font-weight:700}.trend.up{color:var(--ok)}.trend.down{color:var(--err)}
.ops-strip{display:flex;align-items:center;gap:1.2rem;flex-wrap:wrap;background:var(--card);border:1px solid var(--border);border-radius:9px;padding:.7rem .9rem;margin-bottom:.9rem}
.ops-item{display:flex;align-items:center;gap:.4rem;font-size:.72rem;color:var(--muted)}
.ops-item strong{color:var(--ink)}
.ops-item .material-symbols-outlined{font-size:.9rem;color:var(--faint)}
.fin-grid{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(280px,1fr);gap:.9rem;margin-bottom:.9rem}
.fin-card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:1rem 1.1rem;box-shadow:var(--sh)}
.fin-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem;margin-bottom:1rem}
.fin-card-title{font-size:.84rem;font-weight:700}.fin-card-sub{font-size:.68rem;color:var(--faint);margin-top:.1rem}
.fin-total-chip{font-size:.67rem;font-weight:700;color:var(--p);background:var(--p-dim);padding:.2rem .5rem;border-radius:5px;white-space:nowrap}
.revenue-chart{width:100%;height:250px;min-height:250px}
.chart-unavailable{height:100%;display:flex;align-items:center;justify-content:center;text-align:center;color:var(--faint);font-size:.75rem}
.breakdown-section+.breakdown-section{border-top:1px solid var(--border);margin-top:.85rem;padding-top:.85rem}
.breakdown-title{font-size:.68rem;color:var(--faint);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.55rem}
.breakdown-row{margin-bottom:.6rem}.breakdown-row:last-child{margin-bottom:0}
.breakdown-top{display:flex;align-items:center;justify-content:space-between;gap:.5rem;font-size:.72rem}
.breakdown-name{font-weight:600}.breakdown-amount{font-weight:700}
.breakdown-meta{font-size:.62rem;color:var(--faint);margin-top:.1rem}
.breakdown-track{height:4px;background:var(--bg);margin-top:.3rem;overflow:hidden;border-radius:2px}
.breakdown-fill{height:100%;background:var(--p);border-radius:2px}.breakdown-fill.gold{background:#c99a2e}
.payments-card{margin-bottom:1rem}
.payment-status{display:inline-flex;align-items:center;gap:.25rem;color:var(--ok);background:var(--ok-dim);font-size:.65rem;font-weight:700;padding:.16rem .45rem;border-radius:5px}
.payment-status .material-symbols-outlined{font-size:.75rem}
.payment-method{font-size:.72rem;color:var(--muted)}
.receipt-link{width:28px;height:28px;border:1px solid var(--border);display:inline-flex;align-items:center;justify-content:center;color:var(--p);border-radius:6px}
.receipt-link .material-symbols-outlined{font-size:.9rem}
.payment-actions{display:flex;align-items:center;justify-content:flex-end;gap:.35rem}
.payment-hide,.payment-restore{height:28px;border:1px solid var(--border);background:var(--card);border-radius:6px;padding:0 .5rem;display:inline-flex;align-items:center;justify-content:center;gap:.25rem;font-size:.65rem;font-weight:700}
.payment-hide{color:var(--err)}.payment-hide:hover{background:var(--err-dim);border-color:var(--err)}
.payment-restore{color:var(--ok)}.payment-restore:hover{background:var(--ok-dim);border-color:var(--ok)}
.payment-hide .material-symbols-outlined,.payment-restore .material-symbols-outlined{font-size:.82rem}
.payment-tabs{display:flex;align-items:center;gap:.35rem;flex-wrap:wrap}
.payment-tab{border:1px solid var(--border);background:var(--card);color:var(--muted);border-radius:6px;padding:.32rem .65rem;font-size:.68rem;font-weight:700}
.payment-tab.on{background:var(--p-dim);border-color:var(--p);color:var(--p)}
.payment-panel{display:none}.payment-panel.on{display:block}
.hidden-note{display:flex;align-items:flex-start;gap:.4rem;background:var(--warn-dim);color:var(--muted);border:1px solid rgba(245,158,11,.22);padding:.65rem .75rem;border-radius:7px;margin-bottom:.75rem;font-size:.69rem}
.hidden-note .material-symbols-outlined{font-size:.9rem;color:var(--warn);flex-shrink:0}
.section-heading{display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin:1.2rem 0 .7rem}
.section-heading h2{font-size:1rem;letter-spacing:-.02em}.section-heading p{font-size:.72rem;color:var(--muted);margin-top:.1rem}

.card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:1.1rem 1.2rem;margin-bottom:.9rem}
.ch{display:flex;justify-content:space-between;align-items:center;margin-bottom:.9rem;padding-bottom:.7rem;border-bottom:1px solid var(--border)}
.ct{font-size:.88rem;font-weight:700}

.filters{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.9rem}
.filter-btn{padding:.38rem .8rem;border-radius:20px;border:1px solid var(--border);background:var(--card);color:var(--muted);font-size:.75rem;font-weight:600;cursor:pointer;transition:all .18s}
.filter-btn:hover,.filter-btn.on{background:var(--p-dim);border-color:var(--p);color:var(--p)}

/* ── User grid ── */
.ug{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:.85rem}
.uc{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:1.1rem;transition:transform .22s,box-shadow .22s;display:flex;flex-direction:column;gap:.65rem;animation:fadeUp .4s cubic-bezier(.22,1,.36,1) both;position:relative;overflow:hidden}
.uc::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--p);border-radius:var(--r) var(--r) 0 0}
.uc.admin-card::before{background:linear-gradient(90deg,var(--violet),#a855f7)}
.uc.me-card::before{background:linear-gradient(90deg,var(--ok),#34d399)}
.uc:hover{transform:translateY(-3px);box-shadow:var(--sh-lift)}
.uc-top{display:flex;align-items:center;gap:.75rem}
.uc-av{width:44px;height:44px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,var(--p),#7ca0ff);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.95rem;box-shadow:0 3px 10px var(--p-glow)}
.uc-av.a{background:linear-gradient(135deg,var(--violet),#a855f7);box-shadow:0 3px 10px rgba(124,58,237,.3)}
.uc-av.m{background:linear-gradient(135deg,var(--ok),#34d399);box-shadow:0 3px 10px rgba(16,185,129,.3)}
.uc-name{font-weight:700;font-size:.9rem;display:flex;align-items:center;gap:.35rem;flex-wrap:wrap}
.uc-sub{font-size:.7rem;color:var(--faint);margin-top:1px}
.rbadge{font-size:.62rem;font-weight:700;padding:.12rem .45rem;border-radius:6px}
.rbadge.admin{background:var(--violet-dim);color:var(--violet)}
.rbadge.user{background:var(--p-dim);color:var(--p)}
.me-badge{font-size:.62rem;font-weight:700;padding:.12rem .45rem;border-radius:6px;background:var(--ok-dim);color:var(--ok)}
.uc-stats{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.4rem}
.uc-stat{background:var(--bg);border-radius:8px;padding:.42rem .5rem;text-align:center}
.uc-stat-v{font-size:.85rem;font-weight:700}
.uc-stat-l{font-size:.6rem;color:var(--faint);margin-top:1px}
.uc-meta{font-size:.7rem;color:var(--faint);display:flex;align-items:center;gap:.4rem}
.uc-meta .material-symbols-outlined{font-size:.85rem}
.uc-acts{display:flex;gap:.4rem;margin-top:.1rem;flex-wrap:wrap}
.uc-btn{flex:1;min-width:0;padding:.42rem .3rem;border:none;border-radius:7px;font-size:.7rem;font-weight:600;display:flex;align-items:center;justify-content:center;gap:.2rem;transition:all .18s;white-space:nowrap}
.uc-btn .material-symbols-outlined{font-size:.88rem}
.uc-info{background:var(--p-dim);color:var(--p)}.uc-info:hover{background:rgba(59,111,245,.16)}
.uc-pw{background:var(--warn-dim);color:#b45309}.uc-pw:hover{background:rgba(245,158,11,.18)}
.uc-role{background:var(--violet-dim);color:var(--violet)}.uc-role:hover{background:rgba(124,58,237,.15)}
.uc-del{background:var(--err-dim);color:var(--err);flex:0;padding:.42rem .55rem}.uc-del:hover{background:rgba(239,68,68,.16)}
.uc-sub-btn{background:var(--ok-dim);color:var(--ok)}.uc-sub-btn:hover{background:rgba(16,185,129,.18)}

/* ── Badge de assinatura ── */
.sub-badge{display:inline-flex;align-items:center;gap:.28rem;font-size:.64rem;font-weight:700;padding:.18rem .5rem;border-radius:6px;white-space:nowrap}
.sub-badge .material-symbols-outlined{font-size:.78rem}
.sub-badge.ativa{background:var(--ok-dim);color:var(--ok)}
.sub-badge.expirando{background:var(--warn-dim);color:#92400e}
.sub-badge.expirada{background:var(--err-dim);color:var(--err)}
.sub-badge.suspensa{background:var(--warn-dim);color:#92400e}
.sub-badge.sem{background:var(--err-dim);color:var(--err)}
.sub-badge.admin{background:var(--violet-dim);color:var(--violet)}

/* Table */
.tbl-wrap{overflow-x:auto}
.tbl{width:100%;border-collapse:collapse;min-width:700px}
.tbl thead tr{background:var(--bg)}
.tbl th{padding:.7rem 1rem;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--faint);border-bottom:1px solid var(--border)}
.tbl td{padding:.8rem 1rem;border-bottom:1px solid var(--border);vertical-align:middle;font-size:.83rem}
.tbl tbody tr:hover{background:var(--p-dim)}
.tbl tbody tr:last-child td{border-bottom:none}
.tbl-av{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.78rem;color:#fff;flex-shrink:0}
.tbl-cell{display:flex;align-items:center;gap:.65rem}
.tbl-name{font-weight:600;font-size:.83rem}.tbl-sub{font-size:.7rem;color:var(--faint)}
.badge{font-size:.67rem;font-weight:700;padding:.15rem .5rem;border-radius:20px}
.badge.admin{background:var(--violet-dim);color:var(--violet)}.badge.user{background:var(--p-dim);color:var(--p)}.badge.me{background:var(--ok-dim);color:var(--ok)}
.tbl-acts{display:flex;gap:.35rem}
.tbl-btn{width:30px;height:30px;border:1px solid var(--border);border-radius:7px;background:transparent;color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .18s}
.tbl-btn .material-symbols-outlined{font-size:.95rem}
.tbl-btn:hover{background:var(--p-dim);color:var(--p);border-color:var(--p)}
.tbl-btn.del:hover{background:var(--err-dim);color:var(--err);border-color:var(--err)}
.tbl-btn.pw:hover{background:var(--warn-dim);color:var(--warn);border-color:var(--warn)}
.tbl-btn.role:hover{background:var(--violet-dim);color:var(--violet);border-color:var(--violet)}
.tbl-btn.sub:hover{background:var(--ok-dim);color:var(--ok);border-color:var(--ok)}

.view-tog{display:flex;gap:3px;background:var(--bg);border-radius:8px;padding:3px}
.vt-btn{padding:.3rem .5rem;border-radius:6px;border:none;background:transparent;color:var(--faint);cursor:pointer;transition:all .18s;display:flex;align-items:center}
.vt-btn .material-symbols-outlined{font-size:1rem}
.vt-btn.on{background:var(--card);color:var(--p);box-shadow:var(--sh)}

/* ══ MODAIS BASE ══ */
.modal{display:none;position:fixed;inset:0;background:rgba(15,28,46,.55);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(5px)}

/* Modal criar user */
.mbox{background:var(--card);border-radius:18px;width:90%;max-width:500px;box-shadow:0 24px 64px rgba(0,0,0,.2);animation:popIn .28s cubic-bezier(.22,1,.36,1) both;overflow:hidden}
.mhead{padding:1.5rem 1.5rem 1rem;background:linear-gradient(135deg,var(--p),#5c8aff);color:#fff;position:relative}
.mhead h2{font-size:1.1rem;font-weight:700;margin-bottom:.3rem}
.mhead p{font-size:.78rem;opacity:.8}
.mhead-close{position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.2);border:none;color:#fff;width:28px;height:28px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.mhead-close .material-symbols-outlined{font-size:1rem}
.step-dots{display:flex;gap:.45rem;margin-top:.9rem;align-items:center}
.sdot{height:4px;border-radius:2px;background:rgba(255,255,255,.3);transition:all .35s}
.sdot.on{background:#fff;width:24px!important}.sdot.done{background:rgba(255,255,255,.7)}
.mbody{padding:1.3rem 1.5rem;max-height:calc(93vh - 200px);overflow-y:auto}
.step-p{display:none;animation:fadeUp .3s cubic-bezier(.22,1,.36,1) both}
.step-p.on{display:block}
.fg{display:grid;grid-template-columns:1fr 1fr;gap:.9rem}
.fw{grid-column:1/-1}
.fl{display:flex;flex-direction:column;gap:.3rem}
.fl label{font-size:.72rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
.fl input,.fl select{padding:.65rem .9rem;border:1px solid var(--border);border-radius:9px;font-size:.88rem;font-family:inherit;transition:border-color .18s,box-shadow .18s;outline:none;background:var(--card);color:var(--ink)}
.fl input:focus,.fl select:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.fl .hint{font-size:.69rem;color:var(--faint);margin-top:.15rem}
.fl .eh{font-size:.69rem;color:var(--err);margin-top:.15rem;display:none}
.fl input.invalid{border-color:var(--err);box-shadow:0 0 0 3px var(--err-dim)}
.sw{margin-top:.4rem}
.sbar{height:4px;border-radius:2px;background:var(--border);overflow:hidden;margin-bottom:.2rem}
.sfill{height:100%;border-radius:2px;transition:width .3s,background .3s;width:0}
.slbl{font-size:.67rem;color:var(--faint)}
.role-opts{display:grid;grid-template-columns:1fr 1fr;gap:.7rem;margin-top:.2rem}
.role-opt{border:2px solid var(--border);border-radius:10px;padding:.85rem;cursor:pointer;transition:all .2s;text-align:center}
.role-opt:hover{border-color:var(--p);background:var(--p-dim)}
.role-opt.on[data-role="user"]{border-color:var(--p);background:var(--p-dim)}
.role-opt.on[data-role="admin"]{border-color:var(--violet);background:var(--violet-dim)}
.role-opt .material-symbols-outlined{font-size:1.6rem;display:block;margin-bottom:.3rem}
.role-opt.on[data-role="user"] .material-symbols-outlined{color:var(--p)}
.role-opt.on[data-role="admin"] .material-symbols-outlined{color:var(--violet)}
.ro-name{font-size:.82rem;font-weight:700;margin-bottom:.1rem}
.ro-sub{font-size:.68rem;color:var(--faint)}
.rev-card{background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:1rem 1.1rem;margin-bottom:.6rem}
.rev-row{display:flex;justify-content:space-between;align-items:center;padding:.3rem 0;font-size:.83rem}
.rev-row:not(:last-child){border-bottom:1px dashed var(--border)}
.rrl{color:var(--muted);font-size:.75rem}.rrv{font-weight:600}
.mfoot{padding:1rem 1.5rem;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:.7rem}
.btn-back{display:flex;align-items:center;gap:.3rem;padding:.65rem 1.1rem;border:1px solid var(--border);border-radius:9px;background:var(--card);color:var(--muted);font-size:.83rem;font-weight:600;cursor:pointer;transition:all .18s}
.btn-back:hover{background:var(--bg)}
.btn-next{display:flex;align-items:center;gap:.4rem;padding:.65rem 1.3rem;border:none;border-radius:9px;background:var(--p);color:#fff;font-size:.83rem;font-weight:700;cursor:pointer;transition:all .22s;box-shadow:0 4px 14px var(--p-glow);margin-left:auto}
.btn-next:hover{background:var(--p-dark);transform:translateY(-2px)}
.btn-next:disabled{background:var(--faint);box-shadow:none;transform:none;cursor:not-allowed}
.btn-next .material-symbols-outlined{font-size:1rem}
.step-lbl{font-size:.72rem;color:var(--faint);font-weight:500}

/* Modal senha / role */
.sbox{background:var(--card);border-radius:16px;width:90%;max-width:400px;padding:1.6rem;box-shadow:0 24px 64px rgba(0,0,0,.2);animation:popIn .28s cubic-bezier(.22,1,.36,1) both}
.sbox-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.1rem}
.sbox-head h2{font-size:1rem;font-weight:700;display:flex;align-items:center;gap:.4rem}
.sbox-head h2 .material-symbols-outlined{font-size:1.1rem;color:var(--warn)}
.mcl{font-size:1.7rem;cursor:pointer;color:var(--faint);line-height:1;transition:color .15s}
.mcl:hover{color:var(--ink)}
.pw-wrap{position:relative}
.pw-wrap input{width:100%;padding-right:2.4rem}
.pw-eye{position:absolute;right:.7rem;top:50%;transform:translateY(-50%);cursor:pointer;color:var(--faint);background:none;border:none;display:flex;align-items:center;transition:color .15s}
.pw-eye .material-symbols-outlined{font-size:1.1rem}
.btn-save{background:var(--p);color:#fff;border:none;padding:.75rem;border-radius:10px;font-weight:700;width:100%;font-size:.88rem;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:.45rem;transition:all .22s;cursor:pointer;box-shadow:0 4px 14px var(--p-glow);margin-top:.9rem}
.btn-save:hover{background:var(--p-dark);transform:translateY(-2px)}
.btn-save:disabled{background:var(--faint);box-shadow:none;transform:none;cursor:not-allowed}
.btn-save.warn{background:var(--warn);box-shadow:0 4px 14px rgba(245,158,11,.3)}.btn-save.warn:hover{background:#d97706}
.btn-save.violet{background:var(--violet);box-shadow:0 4px 14px rgba(124,58,237,.3)}.btn-save.violet:hover{background:#6d28d9}
.btn-save.ok{background:var(--ok);box-shadow:0 4px 14px rgba(16,185,129,.3)}.btn-save.ok:hover{background:#059669}

/* ══ MODAL ASSINATURA ══ */
.sub-box{background:var(--card);border-radius:18px;width:90%;max-width:440px;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.2);animation:popIn .28s cubic-bezier(.22,1,.36,1) both}
.sub-head{padding:1.5rem 1.5rem 1.1rem;background:linear-gradient(135deg,var(--ok),#34d399);color:#fff;position:relative}
.sub-head h2{font-size:1.05rem;font-weight:700;margin-bottom:.2rem;display:flex;align-items:center;gap:.45rem}
.sub-head h2 .material-symbols-outlined{font-size:1.15rem}
.sub-head p{font-size:.78rem;opacity:.82}
.sub-head-close{position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.2);border:none;color:#fff;width:28px;height:28px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.sub-body{padding:1.3rem 1.5rem}
.sub-current{background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:.85rem 1rem;margin-bottom:1.1rem;font-size:.82rem;display:flex;align-items:center;gap:.6rem}
.sub-current .material-symbols-outlined{font-size:1rem;color:var(--faint)}
.sub-current strong{color:var(--ink)}
.plan-opts{display:flex;flex-direction:column;gap:.55rem;margin-bottom:1rem}
.plan-opt{border:2px solid var(--border);border-radius:11px;padding:.9rem 1.1rem;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:.85rem;position:relative}
.plan-opt:hover{border-color:var(--ok);background:var(--ok-dim)}
.plan-opt.on{border-color:var(--ok);background:var(--ok-dim)}
.plan-ico{width:38px;height:38px;border-radius:9px;background:var(--bg);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.2rem;border:1px solid var(--border)}
.plan-opt.on .plan-ico{background:rgba(16,185,129,.1);border-color:rgba(16,185,129,.3)}
.plan-name{font-size:.88rem;font-weight:700}
.plan-desc{font-size:.71rem;color:var(--muted);margin-top:.1rem}
.plan-check{margin-left:auto;width:20px;height:20px;border-radius:50%;border:2px solid var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .18s}
.plan-opt.on .plan-check{background:var(--ok);border-color:var(--ok)}
.plan-opt.on .plan-check::after{content:'';width:6px;height:6px;border-radius:50%;background:#fff}
.sub-obs{margin-bottom:.5rem}
.sub-foot{padding:1rem 1.5rem;border-top:1px solid var(--border);display:flex;gap:.5rem}
.sub-cancel{flex:1;padding:.65rem;border:1px solid var(--border);border-radius:9px;background:var(--card);color:var(--muted);font-size:.82rem;font-weight:600;cursor:pointer;transition:all .18s;font-family:inherit}
.sub-cancel:hover{background:var(--bg)}
.sub-confirm{flex:2;padding:.65rem;border:none;border-radius:9px;background:var(--ok);color:#fff;font-size:.83rem;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:.4rem;transition:all .22s;box-shadow:0 3px 12px rgba(16,185,129,.3)}
.sub-confirm:hover{background:#059669;transform:translateY(-1px)}
.sub-confirm:disabled{background:var(--faint);box-shadow:none;transform:none;cursor:not-allowed}
.sub-confirm .material-symbols-outlined{font-size:.95rem}

/* Confirm / Delete */
.cbox2{background:var(--card);border-radius:16px;width:90%;max-width:380px;padding:1.5rem;box-shadow:0 24px 64px rgba(0,0,0,.2);animation:popIn .25s cubic-bezier(.22,1,.36,1) both;text-align:center}
.cico{font-size:2.4rem;color:var(--err);margin-bottom:.5rem;display:block}
.ctitle{font-size:1rem;font-weight:700;margin-bottom:.3rem}
.csub{font-size:.8rem;color:var(--muted);margin-bottom:1.2rem}
.cacts{display:flex;gap:.5rem}
.ccancel{flex:1;padding:.65rem;border:1px solid var(--border);border-radius:9px;background:var(--card);color:var(--muted);font-size:.83rem;font-weight:600;cursor:pointer;transition:all .18s}
.ccancel:hover{background:var(--bg)}
.cdel{flex:1;padding:.65rem;border:none;border-radius:9px;background:var(--err);color:#fff;font-size:.83rem;font-weight:700;cursor:pointer;box-shadow:0 3px 10px var(--err-dim)}
.cdel:hover{background:#dc2626}

/* Modal detalhes */
.det-box{background:var(--card);border-radius:18px;width:96%;max-width:700px;max-height:92vh;overflow:hidden;box-shadow:0 24px 64px rgba(0,0,0,.22);animation:popIn .28s cubic-bezier(.22,1,.36,1) both;display:flex;flex-direction:column}
.dh{padding:1.4rem 1.5rem 1.1rem;color:#fff;position:relative;flex-shrink:0}
.dh.blue{background:linear-gradient(135deg,var(--p),#5c8aff)}
.dh.violet{background:linear-gradient(135deg,var(--violet),#a855f7)}
.dh.green{background:linear-gradient(135deg,var(--ok),#34d399)}
.dh-close{position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.2);border:none;color:#fff;width:30px;height:30px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.dh-close:hover{background:rgba(255,255,255,.35)}
.dh-close .material-symbols-outlined{font-size:1.1rem}
.dh-top{display:flex;align-items:center;gap:1rem}
.dh-av{width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,.2);border:2px solid rgba(255,255,255,.35);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.25rem;flex-shrink:0}
.dh-name{font-size:1.1rem;font-weight:700;line-height:1.2}
.dh-sub{font-size:.75rem;opacity:.8;margin-top:.2rem}
.dh-badges{display:flex;gap:.4rem;margin-top:.5rem;flex-wrap:wrap}
.dh-badge{background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.25);color:#fff;font-size:.68rem;font-weight:700;padding:.2rem .55rem;border-radius:20px;display:inline-flex;align-items:center;gap:.25rem}
.dh-badge .material-symbols-outlined{font-size:.78rem}
.dh-stats{display:flex;gap:.8rem;margin-top:1rem;flex-wrap:wrap}
.dh-stat{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);border-radius:9px;padding:.5rem .85rem;min-width:75px}
.dh-stat-v{font-size:1.05rem;font-weight:700;line-height:1}
.dh-stat-l{font-size:.6rem;opacity:.75;margin-top:.15rem}
.dbody{padding:1.1rem 1.4rem;overflow-y:auto;flex:1}
.dtabs{display:flex;gap:2px;background:var(--bg);border-radius:9px;padding:3px;margin-bottom:1rem}
.dtab{flex:1;padding:.45rem .3rem;border-radius:7px;border:none;background:transparent;font-size:.74rem;font-weight:600;color:var(--faint);cursor:pointer;transition:all .18s;display:flex;align-items:center;justify-content:center;gap:.3rem;white-space:nowrap}
.dtab .material-symbols-outlined{font-size:.9rem}
.dtab.on{background:var(--card);color:var(--p);box-shadow:var(--sh)}
.dtab-p{display:none}
.dtab-p.on{display:block;animation:fadeUp .3s cubic-bezier(.22,1,.36,1) both}
.ib{margin-bottom:1rem}
.ib-title{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--faint);margin-bottom:.55rem;display:flex;align-items:center;gap:.35rem}
.ib-title .material-symbols-outlined{font-size:.85rem}
.ir-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}
.ir{background:var(--bg);border-radius:9px;padding:.6rem .8rem}
.ir.s2{grid-column:1/-1}
.ir-l{font-size:.67rem;color:var(--faint);margin-bottom:.18rem;font-weight:600}
.ir-v{font-size:.83rem;font-weight:600;color:var(--ink)}
.ir-v.mt{color:var(--faint);font-weight:400;font-style:italic}
.logo-mini{width:52px;height:52px;border-radius:8px;object-fit:contain;border:1px solid var(--border);background:var(--bg)}
.logo-ph{width:52px;height:52px;border-radius:8px;background:var(--p-dim);border:1px dashed var(--border);display:flex;align-items:center;justify-content:center;color:var(--faint);flex-shrink:0}
.logo-ph .material-symbols-outlined{font-size:1.4rem}
.login-list{display:flex;flex-direction:column;gap:.45rem}
.login-item{background:var(--bg);border-radius:10px;padding:.65rem .9rem;display:flex;align-items:flex-start;gap:.75rem;border-left:3px solid var(--ok)}
.login-item.fail{border-left-color:var(--err)}
.login-item.first{border-left-color:var(--p);background:var(--p-dim)}
.login-ico{width:30px;height:30px;border-radius:8px;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:var(--ok-dim);color:var(--ok)}
.login-item.fail .login-ico{background:var(--err-dim);color:var(--err)}
.login-item.first .login-ico{background:var(--p-dim);color:var(--p)}
.login-ico .material-symbols-outlined{font-size:1rem}
.login-info{flex:1;min-width:0}
.login-date{font-size:.78rem;font-weight:700}
.login-device{font-size:.71rem;color:var(--muted);margin-top:.08rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.login-ip{font-size:.68rem;color:var(--faint);margin-top:.05rem;font-family:monospace}
.login-tag{font-size:.65rem;font-weight:700;padding:.1rem .4rem;border-radius:6px;flex-shrink:0;margin-top:.15rem}
.login-tag.ok{background:var(--ok-dim);color:var(--ok)}.login-tag.fail{background:var(--err-dim);color:var(--err)}
.empty-state{text-align:center;padding:2.5rem 1rem;color:var(--faint)}
.empty-state .material-symbols-outlined{font-size:2rem;display:block;margin-bottom:.5rem;opacity:.25}
.empty-state p{font-size:.8rem}
.loading-c{display:flex;justify-content:center;align-items:center;padding:3rem;flex-direction:column;gap:.8rem;color:var(--faint);font-size:.82rem}
.spinner{width:22px;height:22px;border:2.5px solid var(--border);border-top-color:var(--p);border-radius:50%;animation:spin .7s linear infinite}

/* Histórico sub no modal detalhes */
.sub-hist-item{background:var(--bg);border-radius:9px;padding:.6rem .8rem;margin-bottom:.4rem;display:flex;align-items:flex-start;gap:.6rem;border-left:3px solid var(--ok)}
.sub-hist-item.suspensa{border-left-color:var(--warn)}
.sub-hist-item.expirada{border-left-color:var(--err)}
.sub-hist-item.criada{border-left-color:var(--p)}
.shi-ico{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:var(--ok-dim);color:var(--ok)}
.sub-hist-item.suspensa .shi-ico{background:var(--warn-dim);color:var(--warn)}
.sub-hist-item.expirada .shi-ico{background:var(--err-dim);color:var(--err)}
.sub-hist-item.criada .shi-ico{background:var(--p-dim);color:var(--p)}
.shi-ico .material-symbols-outlined{font-size:.9rem}
.shi-info{flex:1;min-width:0}
.shi-title{font-size:.78rem;font-weight:700}
.shi-sub{font-size:.69rem;color:var(--muted);margin-top:.08rem}
.shi-admin{font-size:.65rem;color:var(--faint);margin-top:.04rem}
.shi-payment{margin-top:.5rem;padding:.55rem .65rem;background:var(--card);border:1px solid var(--border);border-radius:7px}
.shi-pay-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.35rem .7rem}
.shi-pay-field{min-width:0}
.shi-pay-label{font-size:.58rem;color:var(--faint);font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.shi-pay-value{font-size:.7rem;color:var(--ink);font-weight:600;overflow-wrap:anywhere;margin-top:.05rem}
.shi-pay-value.mono{font-family:monospace;font-size:.64rem}
.shi-items{margin-top:.45rem;padding-top:.4rem;border-top:1px solid var(--border);font-size:.67rem;color:var(--muted)}
.shi-receipt{display:inline-flex;align-items:center;gap:.2rem;margin-top:.45rem;color:var(--p);font-size:.68rem;font-weight:700;text-decoration:none}
.shi-receipt .material-symbols-outlined{font-size:.8rem}
@media(max-width:640px){.shi-pay-grid{grid-template-columns:1fr}}

/* Toast */
.tw{position:fixed;bottom:1.2rem;right:1.2rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;pointer-events:none}
.toast{background:var(--ink);color:#fff;padding:.7rem 1.1rem;border-radius:10px;font-size:.82rem;font-weight:500;display:flex;align-items:center;gap:.5rem;animation:fadeUp .35s cubic-bezier(.22,1,.36,1) both;box-shadow:0 8px 24px rgba(0,0,0,.2);pointer-events:auto}
.toast .material-symbols-outlined{font-size:1rem}
.toast.ok{background:var(--ok)}.toast.err{background:var(--err)}.toast.warn{background:var(--warn);color:var(--ink)}

/* Bottom nav */
.bnav{display:none;position:fixed;bottom:0;left:0;right:0;background:var(--card);border-top:1px solid var(--border);z-index:300;padding:.35rem 0 calc(.35rem + env(safe-area-inset-bottom));box-shadow:0 -2px 16px rgba(15,28,46,.07)}
.bnav-inner{display:flex;justify-content:space-around}
.bnav-a{display:flex;flex-direction:column;align-items:center;gap:2px;padding:.35rem .6rem;color:var(--faint);font-size:.62rem;font-weight:600;transition:all .18s;flex:1}
.bnav-a .material-symbols-outlined{font-size:1.25rem}
.bnav-a.on{color:var(--p)}

@media(max-width:1050px){.kpis{grid-template-columns:repeat(2,1fr)}.fin-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.fin-grid{grid-template-columns:1fr}}
@media(max-width:860px){.sb{transform:translateX(-100%)}.sb.open{transform:translateX(0)}.main{margin-left:0}.ham{display:flex}}
@media(max-width:640px){.bnav{display:block}.main{padding:.9rem .9rem calc(68px + env(safe-area-inset-bottom))}.top{flex-direction:column;align-items:flex-start}.searchbox{min-width:0;width:100%}.kpis{grid-template-columns:1fr 1fr;gap:.6rem}.kpi-val{font-size:1.35rem}.exec-head{align-items:flex-start}.exec-date{display:none}.fin-kpis{grid-template-columns:1fr 1fr;gap:.6rem}.fin-kpi.total{grid-column:1/-1}.fin-kpi{min-height:105px;padding:.85rem}.fin-kpi-value{font-size:1.2rem}.fin-kpi.total .fin-kpi-value{font-size:1.65rem}.revenue-chart{height:220px;min-height:220px}.ops-strip{gap:.7rem}.payments-card{padding:.85rem}.payment-tabs{width:100%}.payment-tab{flex:1}.ug{grid-template-columns:1fr}.mbox,.sbox,.cbox2,.sub-box{width:96%;max-height:96vh}.det-box{width:98%;max-height:98vh}.fg{grid-template-columns:1fr}.role-opts{grid-template-columns:1fr}.ir-grid{grid-template-columns:1fr}.dh-stats{gap:.5rem}}
@media(max-width:380px){.kpis{grid-template-columns:1fr}}

/* ═══════════════════════════════════════════════════════════
   ADMIN CONTROL CENTER — camada visual v2
   Mantém os componentes/ações existentes e reorganiza a hierarquia.
═══════════════════════════════════════════════════════════ */
.admin-shell{max-width:1500px;margin:0 auto}
.admin-page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1.25rem;margin-bottom:1rem}
.admin-title-wrap{display:flex;align-items:flex-start;gap:.8rem}
.admin-title-icon{width:42px;height:42px;border-radius:12px;background:var(--p-dim);color:var(--p);display:flex;align-items:center;justify-content:center;border:1px solid rgba(59,111,245,.12);flex-shrink:0}
.admin-title-icon .material-symbols-outlined{font-size:1.25rem}
.admin-title{font-size:1.65rem;font-weight:750;letter-spacing:-.045em;line-height:1.12}
.admin-subtitle{font-size:.8rem;color:var(--muted);margin-top:.25rem;max-width:680px}
.admin-head-actions{display:flex;align-items:center;justify-content:flex-end;gap:.5rem;flex-wrap:wrap}
.admin-action{height:38px;padding:0 .85rem;border-radius:9px;border:1px solid var(--border);background:var(--card);color:var(--muted);font-size:.75rem;font-weight:650;display:inline-flex;align-items:center;gap:.38rem;transition:all .18s}
.admin-action .material-symbols-outlined{font-size:1rem}
.admin-action:hover{border-color:rgba(59,111,245,.3);color:var(--p);background:var(--p-dim)}
.admin-action.primary{background:var(--p);border-color:var(--p);color:#fff;box-shadow:0 5px 14px var(--p-glow)}
.admin-action.primary:hover{background:var(--p-dark);color:#fff;transform:translateY(-1px)}

.admin-local-nav{position:sticky;top:.65rem;z-index:120;display:flex;align-items:center;gap:.3rem;width:max-content;max-width:100%;padding:4px;margin-bottom:1rem;background:color-mix(in srgb,var(--card) 88%,transparent);border:1px solid var(--border);border-radius:11px;box-shadow:0 5px 20px rgba(15,28,46,.06);backdrop-filter:blur(12px);overflow-x:auto}
.admin-nav-link{border:0;background:transparent;color:var(--muted);padding:.48rem .78rem;border-radius:8px;font-size:.72rem;font-weight:650;display:inline-flex;align-items:center;gap:.35rem;white-space:nowrap}
.admin-nav-link .material-symbols-outlined{font-size:.95rem}
.admin-nav-link:hover,.admin-nav-link.on{background:var(--p-dim);color:var(--p)}

.finance-hero{scroll-margin-top:76px;display:grid;grid-template-columns:minmax(0,1.55fr) minmax(300px,.75fr);gap:.85rem;margin-bottom:.85rem}
.finance-main{position:relative;overflow:hidden;min-height:292px;padding:1.45rem 1.55rem;background:linear-gradient(135deg,#0d1728 0%,#101d33 65%,#152746 100%);border:1px solid #1d2b42;border-radius:15px;color:#fff;box-shadow:0 12px 28px rgba(15,28,46,.12)}
.finance-main::before{content:'';position:absolute;width:260px;height:260px;border:38px solid rgba(217,169,52,.10);border-radius:50%;right:-105px;top:-110px}
.finance-main::after{content:'';position:absolute;width:130px;height:130px;border:1px solid rgba(255,255,255,.08);border-radius:50%;right:80px;bottom:-75px}
.finance-main-top{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;position:relative;z-index:1}
.finance-kicker{display:inline-flex;align-items:center;gap:.35rem;color:#e0b84f;font-size:.65rem;font-weight:750;text-transform:uppercase;letter-spacing:.105em}
.finance-kicker .material-symbols-outlined{font-size:.85rem}
.finance-main h2{font-size:1.05rem;margin-top:.3rem;letter-spacing:-.02em}
.finance-updated{font-size:.64rem;color:#98a2b3;text-align:right;white-space:nowrap}
.finance-updated strong{display:block;color:#fff;font-size:.72rem;margin-bottom:.05rem}
.finance-primary{position:relative;z-index:1;margin-top:1.4rem}
.finance-primary-label{font-size:.7rem;color:#aab5c5;font-weight:600}
.finance-primary-value{font-size:2.65rem;font-weight:760;letter-spacing:-.065em;line-height:1;margin-top:.25rem}
.finance-primary-foot{font-size:.7rem;color:#aab5c5;margin-top:.5rem}
.finance-primary-foot .trend{margin-right:.25rem}
.finance-mini-grid{position:relative;z-index:1;display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.55rem;margin-top:1.35rem}
.finance-mini{padding:.7rem .75rem;border:1px solid rgba(255,255,255,.09);background:rgba(255,255,255,.055);border-radius:10px}
.finance-mini-label{font-size:.59rem;color:#98a2b3;text-transform:uppercase;letter-spacing:.05em;font-weight:700}
.finance-mini-value{font-size:.84rem;font-weight:700;margin-top:.18rem;color:#fff}

.finance-health{background:var(--card);border:1px solid var(--border);border-radius:15px;padding:1.15rem 1.2rem;box-shadow:var(--sh);display:flex;flex-direction:column}
.finance-health-head{display:flex;align-items:flex-start;justify-content:space-between;gap:.7rem;margin-bottom:.85rem}
.finance-health-title{font-size:.86rem;font-weight:720}
.finance-health-sub{font-size:.66rem;color:var(--faint);margin-top:.08rem}
.health-icon{width:31px;height:31px;border-radius:8px;background:var(--ok-dim);color:var(--ok);display:flex;align-items:center;justify-content:center}
.health-icon .material-symbols-outlined{font-size:1rem}
.health-list{display:flex;flex-direction:column;gap:.2rem;flex:1}
.health-row{display:grid;grid-template-columns:auto 1fr auto;align-items:center;gap:.55rem;padding:.58rem 0;border-bottom:1px solid var(--border)}
.health-row:last-child{border-bottom:0}
.health-dot{width:8px;height:8px;border-radius:50%;background:var(--p)}
.health-dot.ok{background:var(--ok)}.health-dot.warn{background:var(--warn)}.health-dot.err{background:var(--err)}
.health-name{font-size:.7rem;color:var(--muted)}
.health-val{font-size:.78rem;font-weight:720;text-align:right}
.health-progress{grid-column:2/4;height:4px;background:var(--bg);border-radius:4px;overflow:hidden;margin-top:-.2rem}
.health-progress > span{display:block;height:100%;border-radius:4px;background:var(--p)}
.finance-note{margin-top:.7rem;padding:.58rem .65rem;background:var(--bg);border-radius:8px;font-size:.62rem;color:var(--faint);display:flex;gap:.35rem;align-items:flex-start}
.finance-note .material-symbols-outlined{font-size:.8rem;color:var(--faint);flex-shrink:0;margin-top:.02rem}

.admin-metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.7rem;margin-bottom:.9rem}
.admin-metric{background:var(--card);border:1px solid var(--border);border-radius:11px;padding:.88rem .95rem;display:flex;align-items:center;gap:.7rem;box-shadow:0 1px 4px rgba(15,28,46,.035)}
.admin-metric-ico{width:35px;height:35px;border-radius:9px;background:var(--p-dim);color:var(--p);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.admin-metric-ico .material-symbols-outlined{font-size:1rem}
.admin-metric.warn .admin-metric-ico{background:var(--warn-dim);color:var(--warn)}
.admin-metric.ok .admin-metric-ico{background:var(--ok-dim);color:var(--ok)}
.admin-metric.violet .admin-metric-ico{background:var(--violet-dim);color:var(--violet)}
.admin-metric-label{font-size:.62rem;color:var(--faint);font-weight:650}
.admin-metric-value{font-size:1rem;font-weight:740;letter-spacing:-.025em;margin-top:.05rem}
.admin-metric-meta{font-size:.58rem;color:var(--faint);margin-top:.06rem}

.fin-grid{scroll-margin-top:76px}
.fin-card{border-radius:13px;padding:1.1rem 1.15rem;box-shadow:0 2px 10px rgba(15,28,46,.045)}
.fin-card-title{font-size:.88rem}.fin-card-sub{font-size:.67rem}
.revenue-chart{height:270px;min-height:270px}

.section-band{scroll-margin-top:76px;display:flex;align-items:flex-end;justify-content:space-between;gap:1rem;margin:1.35rem 0 .65rem}
.section-band-title{font-size:1rem;font-weight:750;letter-spacing:-.025em}
.section-band-sub{font-size:.69rem;color:var(--muted);margin-top:.1rem}
.section-count{font-size:.65rem;color:var(--muted);background:var(--card);border:1px solid var(--border);padding:.27rem .55rem;border-radius:7px;font-weight:650}

.payments-card{border-radius:13px;padding:1.05rem 1.1rem}
.payments-card .fin-card-head{align-items:center}
.payment-tabs{background:var(--bg);padding:3px;border-radius:8px}
.payment-tab{border:0;background:transparent}
.payment-tab.on{background:var(--card);box-shadow:var(--sh);border:0}
.tbl th{background:var(--bg);font-size:.64rem;padding:.68rem .8rem}
.tbl td{padding:.75rem .8rem;font-size:.78rem}
.tbl tbody tr{transition:background .15s}

.users-card{scroll-margin-top:76px;background:transparent;border:0;border-radius:0;padding:0;box-shadow:none}
.users-toolbar{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:.55rem;padding:.78rem .85rem;background:var(--card);border:1px solid var(--border);border-radius:11px;box-shadow:0 1px 3px rgba(15,28,46,.035)}
.users-toolbar-left{display:flex;align-items:center;gap:.55rem;min-width:0}
.users-toolbar-title{font-size:.86rem;font-weight:720;letter-spacing:-.015em}
.users-toolbar-meta{font-size:.63rem;color:var(--faint);margin-top:.03rem}
.users-toolbar-right{display:flex;align-items:center;gap:.4rem}
.users-search{min-width:285px;height:36px;padding:.4rem .72rem;border-radius:8px;box-shadow:none}
.users-search:focus-within{box-shadow:0 0 0 2px var(--p-dim)}
.users-search input{font-size:.75rem}
.view-tog{border:1px solid var(--border);background:var(--bg);border-radius:8px;padding:2px}
.vt-btn{border-radius:6px;padding:.3rem .46rem}

/* Filtros em formato de abas: menos "pill", mais painel administrativo. */
.filters{display:flex;align-items:center;gap:1.15rem;margin:0 0 .72rem;padding:0 .05rem;border-bottom:1px solid var(--border);overflow-x:auto;scrollbar-width:none}
.filters::-webkit-scrollbar{display:none}
.filter-btn{position:relative;padding:.55rem 0 .52rem;border:0;border-radius:0;background:transparent;color:var(--muted);font-size:.68rem;font-weight:650;white-space:nowrap}
.filter-btn:hover{background:transparent;color:var(--ink)}
.filter-btn.on{background:transparent;color:var(--p)}
.filter-btn.on::after{content:'';position:absolute;left:0;right:0;bottom:-1px;height:2px;background:var(--p);border-radius:2px 2px 0 0}
.filter-count{display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;margin-left:.22rem;padding:0 .28rem;border-radius:9px;background:var(--bg);color:var(--faint);font-size:.56rem;font-weight:700;line-height:1}
.filter-btn.on .filter-count{background:var(--p-dim);color:var(--p)}

/* Cards de usuário — visual mais sóbrio, denso e próximo de um CRM/admin real. */
.ug{grid-template-columns:repeat(auto-fill,minmax(335px,1fr));gap:.68rem}
.uc{padding:0;border-radius:10px;gap:0;box-shadow:none;overflow:hidden;border:1px solid var(--border);background:var(--card);transition:border-color .16s,box-shadow .16s}
.uc::before{display:none}
.uc.admin-card::before,.uc.me-card::before{display:none}
.uc:hover{transform:none;border-color:color-mix(in srgb,var(--border) 55%,var(--ink));box-shadow:0 5px 14px rgba(15,28,46,.055)}
.uc-top{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:start;gap:.72rem;padding:.88rem .92rem .76rem}
.uc-av{width:38px;height:38px;border-radius:9px;background:var(--bg)!important;color:var(--ink)!important;border:1px solid var(--border);box-shadow:none!important;font-size:.78rem;font-weight:750}
.uc-av.a{background:var(--violet-dim)!important;color:var(--violet)!important;border-color:color-mix(in srgb,var(--violet) 18%,var(--border))}
.uc-av.m{background:var(--ok-dim)!important;color:var(--ok)!important;border-color:color-mix(in srgb,var(--ok) 20%,var(--border))}
.uc-ident{min-width:0;padding-top:.02rem}
.uc-name{font-size:.86rem;font-weight:720;line-height:1.25;gap:.28rem}
.uc-subline{display:flex;align-items:center;gap:.35rem;margin-top:.22rem;color:var(--faint);font-size:.59rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.uc-subline .material-symbols-outlined{font-size:.72rem}
.uc-status{display:flex;justify-content:flex-end;min-width:0;padding-top:.02rem}
.rbadge,.me-badge{font-size:.56rem;font-weight:700;padding:.1rem .34rem;border-radius:5px}
.rbadge.admin{background:var(--violet-dim);color:var(--violet)}
.rbadge.user{background:var(--p-dim);color:var(--p)}
.me-badge{background:var(--ok-dim);color:var(--ok)}
.sub-badge{font-size:.58rem;padding:.17rem .38rem;border-radius:5px;gap:.2rem}
.sub-badge .material-symbols-outlined{font-size:.7rem}

.uc-stats{display:grid;grid-template-columns:.78fr .78fr 1.28fr;gap:0;margin:0;padding:.68rem .92rem;border-top:1px solid var(--border);border-bottom:1px solid var(--border);background:color-mix(in srgb,var(--bg) 56%,var(--card))}
.uc-stat{background:transparent;border:0;border-radius:0;padding:0 .72rem;text-align:left;min-width:0}
.uc-stat:first-child{padding-left:0}
.uc-stat:last-child{padding-right:0}
.uc-stat+.uc-stat{border-left:1px solid var(--border)}
.uc-stat-v{font-size:.8rem;font-weight:720;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.uc-stat-l{font-size:.55rem;color:var(--faint);margin-top:.12rem}
.uc-meta{display:none}

.uc-acts{display:flex;align-items:center;flex-wrap:wrap;gap:.28rem;padding:.58rem .68rem .62rem;margin:0;background:var(--card)}
.uc-btn{flex:0 0 auto;width:auto;min-width:0;min-height:29px;padding:.34rem .48rem;border:1px solid transparent;border-radius:6px;background:transparent!important;color:var(--muted);font-size:.59rem;font-weight:650;box-shadow:none;transition:background .15s,border-color .15s,color .15s}
.uc-btn .material-symbols-outlined{font-size:.78rem}
.uc-btn:hover{transform:none;background:var(--bg)!important;border-color:var(--border);color:var(--ink)}
.uc-info{color:var(--p);font-weight:700}
.uc-info:hover{color:var(--p);background:var(--p-dim)!important;border-color:color-mix(in srgb,var(--p) 20%,var(--border))}
.uc-sub-btn{color:var(--ok)}
.uc-sub-btn:hover{color:var(--ok);background:var(--ok-dim)!important;border-color:color-mix(in srgb,var(--ok) 22%,var(--border))}
.uc-pw{color:var(--muted)}
.uc-pw:hover{color:#a85b08;background:var(--warn-dim)!important;border-color:color-mix(in srgb,var(--warn) 24%,var(--border))}
.uc-role{color:var(--muted)}
.uc-role:hover{color:var(--violet);background:var(--violet-dim)!important;border-color:color-mix(in srgb,var(--violet) 22%,var(--border))}
.uc-del{color:var(--faint);margin-left:auto;padding:.34rem .48rem}
.uc-del:hover{color:var(--err);background:var(--err-dim)!important;border-color:color-mix(in srgb,var(--err) 22%,var(--border))}
.user-empty-filter{display:none;text-align:center;padding:2rem;color:var(--faint);font-size:.75rem;border:1px dashed var(--border);border-radius:10px;background:var(--card)}
.user-empty-filter.on{display:block}

@media(max-width:1180px){
    .finance-hero{grid-template-columns:1fr}
    .finance-health{min-height:auto}
    .admin-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}
}
@media(max-width:760px){
    .admin-page-head{flex-direction:column}
    .admin-head-actions{width:100%;justify-content:flex-start}
    .admin-local-nav{top:.4rem;width:100%}
    .finance-main{padding:1.15rem;min-height:auto}
    .finance-primary-value{font-size:2.15rem}
    .finance-mini-grid{grid-template-columns:1fr}
    .admin-metrics{grid-template-columns:1fr 1fr}
    .section-band{align-items:flex-start;flex-direction:column}
    .users-toolbar{align-items:stretch;flex-direction:column}
    .users-toolbar-right{width:100%}
    .users-search{min-width:0;flex:1}
    .uc-top{grid-template-columns:auto minmax(0,1fr)}
    .uc-status{grid-column:2;justify-content:flex-start;margin-top:-.1rem}
}
@media(max-width:520px){
    .admin-title{font-size:1.35rem}
    .admin-head-actions .admin-action:not(.primary){flex:1;justify-content:center}
    .admin-action.primary{width:100%;justify-content:center;order:-1}
    .finance-main-top{flex-direction:column}
    .finance-updated{text-align:left}
    .admin-metrics{grid-template-columns:1fr}
    .ug{grid-template-columns:1fr}
    .uc-acts{gap:.25rem}
    .uc-del{margin-left:0}
}



/* ═══════════════════════════════════════════════════════════════
   GESTÃO DE USUÁRIOS — refinamento visual "calm admin"
   Princípios: menos cor decorativa, maior densidade útil,
   hierarquia tipográfica e ações neutras/consistentes.
   Nenhum fluxo, id, handler ou ação foi alterado.
═══════════════════════════════════════════════════════════════ */
#usuarios.section-band{
    margin-top:1.55rem;
    margin-bottom:.55rem;
    align-items:center;
}
#usuarios .section-band-title{
    font-size:1.02rem;
    font-weight:760;
    letter-spacing:-.028em;
}
#usuarios .section-band-sub{
    color:var(--faint);
    font-size:.67rem;
}
#usuarios .section-count{
    padding:0;
    border:0;
    border-radius:0;
    background:transparent;
    color:var(--faint);
    font-size:.64rem;
    font-weight:650;
}

.users-card{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:12px;
    overflow:hidden;
    box-shadow:0 1px 2px rgba(15,28,46,.025);
}
.users-toolbar{
    margin:0;
    padding:.95rem 1rem .85rem;
    border:0;
    border-bottom:1px solid var(--border);
    border-radius:0;
    background:transparent;
    box-shadow:none;
}
.users-toolbar-title{
    font-size:.84rem;
    font-weight:735;
    color:var(--ink);
}
.users-toolbar-meta{
    margin-top:.12rem;
    font-size:.6rem;
    color:var(--faint);
}
.users-toolbar-right{gap:.45rem}
.users-search{
    min-width:270px;
    height:34px;
    padding:.35rem .7rem;
    border-radius:7px;
    border-color:var(--border);
    background:color-mix(in srgb,var(--card) 88%,var(--bg));
}
.users-search:focus-within{
    border-color:color-mix(in srgb,var(--ink) 28%,var(--border));
    box-shadow:0 0 0 2px color-mix(in srgb,var(--p) 8%,transparent);
    background:var(--card);
}
.users-search .material-symbols-outlined{font-size:.95rem;color:var(--faint)}
.users-search input{font-size:.73rem;font-weight:500}

.view-tog{
    height:34px;
    gap:1px;
    padding:2px;
    border:1px solid var(--border);
    border-radius:7px;
    background:color-mix(in srgb,var(--card) 72%,var(--bg));
}
.vt-btn{
    width:28px;
    height:28px;
    padding:0;
    justify-content:center;
    border-radius:5px;
    color:var(--faint);
}
.vt-btn:hover{color:var(--ink);background:var(--card)}
.vt-btn.on{
    color:var(--ink);
    background:var(--card);
    box-shadow:0 1px 2px rgba(15,28,46,.07);
}

/* Tabs discretas: o estado ativo é hierarquia, não decoração. */
.filters{
    gap:1.25rem;
    margin:0;
    padding:0 1rem;
    min-height:43px;
    border-bottom:1px solid var(--border);
    background:color-mix(in srgb,var(--card) 96%,var(--bg));
}
.filter-btn{
    padding:.71rem 0 .65rem;
    color:var(--faint);
    font-size:.65rem;
    font-weight:620;
    letter-spacing:-.005em;
}
.filter-btn:hover{color:var(--ink)}
.filter-btn.on{color:var(--ink);font-weight:700}
.filter-btn.on::after{
    left:0;
    right:0;
    bottom:-1px;
    height:2px;
    background:var(--ink);
}
.filter-count{
    min-width:auto;
    height:auto;
    margin-left:.22rem;
    padding:0;
    border-radius:0;
    background:transparent!important;
    color:var(--faint)!important;
    font-size:.56rem;
    font-weight:650;
}
.filter-btn.on .filter-count{color:var(--muted)!important}
.user-empty-filter{
    margin:.8rem;
    padding:2.1rem 1rem;
    border-radius:8px;
    border:1px dashed var(--border);
    background:transparent;
}

/* Duas colunas deliberadamente: melhora leitura e evita a 3ª coluna apertada. */
.ug{
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:.7rem;
    padding:.8rem;
    background:var(--card);
}
.uc{
    min-width:0;
    border:1px solid var(--border);
    border-radius:9px;
    background:var(--card);
    box-shadow:none;
    transition:background .14s,border-color .14s;
}
.uc:hover{
    transform:none;
    background:color-mix(in srgb,var(--card) 97%,var(--bg));
    border-color:color-mix(in srgb,var(--border) 68%,var(--muted));
    box-shadow:none;
}
.uc::before,.uc.admin-card::before,.uc.me-card::before{display:none}

.uc-top{
    grid-template-columns:auto minmax(0,1fr) auto;
    align-items:center;
    gap:.68rem;
    padding:.88rem .92rem .74rem;
}
.uc-av,
.uc-av.a,
.uc-av.m{
    width:36px;
    height:36px;
    border-radius:8px;
    border:1px solid var(--border);
    background:color-mix(in srgb,var(--card) 75%,var(--bg))!important;
    color:var(--muted)!important;
    box-shadow:none!important;
    font-size:.72rem;
    font-weight:760;
    letter-spacing:-.02em;
}
.uc.me-card .uc-av{
    color:var(--ink)!important;
    border-color:color-mix(in srgb,var(--border) 65%,var(--muted));
}
.uc-ident{padding:0;min-width:0}
.uc-name{
    gap:.3rem;
    font-size:.85rem;
    font-weight:735;
    line-height:1.25;
    color:var(--ink);
}
.uc-subline{
    margin-top:.22rem;
    gap:.3rem;
    font-size:.57rem;
    color:var(--faint);
}
.uc-subline .material-symbols-outlined{font-size:.68rem}

/* Roles quase monocromáticas. Cor fica reservada ao estado de assinatura. */
.rbadge{
    padding:.08rem .32rem;
    border:1px solid var(--border);
    border-radius:4px;
    background:var(--bg)!important;
    color:var(--muted)!important;
    font-size:.53rem;
    font-weight:680;
}
.me-badge{
    padding:.08rem .3rem;
    border:0;
    border-radius:4px;
    background:transparent;
    color:var(--ok);
    font-size:.53rem;
    font-weight:700;
}
.uc-status{align-self:start;padding-top:.02rem}
.uc-status .sub-badge{
    min-height:23px;
    padding:.18rem .42rem;
    border:1px solid var(--border);
    border-radius:6px;
    background:transparent;
    color:var(--muted);
    font-size:.55rem;
    font-weight:680;
}
.uc-status .sub-badge .material-symbols-outlined{display:none}
.uc-status .sub-badge::before{
    content:'';
    width:6px;
    height:6px;
    flex:0 0 6px;
    border-radius:999px;
    background:var(--faint);
}
.uc-status .sub-badge.ativa::before{background:var(--ok)}
.uc-status .sub-badge.expirando::before,
.uc-status .sub-badge.suspensa::before{background:var(--warn)}
.uc-status .sub-badge.expirada::before,
.uc-status .sub-badge.sem::before{background:var(--err)}
.uc-status .sub-badge.admin{display:none}

/* Métricas deixam de parecer três mini-cards. */
.uc-stats{
    display:flex;
    align-items:center;
    gap:0;
    margin:0 .92rem;
    padding:.68rem 0;
    border-top:1px solid var(--border);
    border-bottom:0;
    background:transparent;
}
.uc-stat{
    flex:0 1 auto;
    min-width:82px;
    padding:0 .86rem;
    border:0;
    background:transparent;
    text-align:left;
}
.uc-stat:first-child{padding-left:0}
.uc-stat:last-child{min-width:0;flex:1;padding-right:0}
.uc-stat+.uc-stat{border-left:1px solid var(--border)}
.uc-stat-v{
    font-size:.77rem;
    font-weight:720;
    line-height:1.2;
    color:var(--ink);
}
.uc-stat-l{
    margin-top:.14rem;
    font-size:.53rem;
    color:var(--faint);
    letter-spacing:.005em;
}

/* Barra de ações neutra. Nada de botão verde/laranja/roxo permanente. */
.uc-acts{
    gap:.15rem;
    padding:.48rem .58rem .55rem;
    margin:0;
    border-top:1px solid var(--border);
    background:color-mix(in srgb,var(--card) 97%,var(--bg));
    flex-wrap:nowrap;
    overflow-x:auto;
    scrollbar-width:none;
}
.uc-acts::-webkit-scrollbar{display:none}
.uc-btn,
.uc-info,
.uc-sub-btn,
.uc-pw,
.uc-role,
.uc-del{
    flex:0 0 auto;
    min-height:30px;
    padding:.36rem .48rem;
    border:1px solid transparent;
    border-radius:6px;
    background:transparent!important;
    color:var(--muted)!important;
    box-shadow:none;
    font-size:.57rem;
    font-weight:620;
    transition:background .14s,color .14s,border-color .14s;
}
.uc-btn .material-symbols-outlined{font-size:.76rem;color:var(--faint)}
.uc-btn:hover,
.uc-info:hover,
.uc-sub-btn:hover,
.uc-pw:hover,
.uc-role:hover{
    background:var(--card)!important;
    border-color:var(--border)!important;
    color:var(--ink)!important;
}
.uc-btn:hover .material-symbols-outlined{color:var(--ink)}
.uc-info{
    font-weight:700;
    color:var(--ink)!important;
}
.uc-info .material-symbols-outlined{color:var(--muted)}
.uc-sub-btn{color:var(--ink)!important}
.uc-del{
    margin-left:auto;
    color:var(--faint)!important;
}
.uc-del:hover{
    background:var(--err-dim)!important;
    border-color:color-mix(in srgb,var(--err) 20%,var(--border))!important;
    color:var(--err)!important;
}
.uc-del:hover .material-symbols-outlined{color:var(--err)}

/* Lista usa a mesma lógica visual neutra. */
.users-card #tbl{
    padding:0 .8rem .8rem;
}
.users-card .tbl{
    border:1px solid var(--border);
    border-radius:8px;
    overflow:hidden;
    background:var(--card);
}
.users-card .tbl thead tr{background:color-mix(in srgb,var(--card) 92%,var(--bg))}
.users-card .tbl th{
    padding:.66rem .72rem;
    font-size:.58rem;
    color:var(--faint);
    letter-spacing:.045em;
}
.users-card .tbl td{
    padding:.69rem .72rem;
    font-size:.72rem;
}
.users-card .tbl tbody tr:hover{background:color-mix(in srgb,var(--card) 95%,var(--bg))}
.users-card .tbl-btn{
    width:28px;
    height:28px;
    border-color:transparent;
    border-radius:6px;
    color:var(--faint);
}
.users-card .tbl-btn:hover{
    background:var(--bg);
    border-color:var(--border);
    color:var(--ink);
}
.users-card .tbl-btn.del:hover{
    background:var(--err-dim);
    border-color:color-mix(in srgb,var(--err) 20%,var(--border));
    color:var(--err);
}

@media(max-width:1160px){
    .ug{grid-template-columns:1fr}
}
@media(max-width:760px){
    .users-toolbar{padding:.85rem}
    .filters{padding:0 .85rem}
    .ug{padding:.65rem}
    .uc-top{grid-template-columns:auto minmax(0,1fr)}
    .uc-status{grid-column:2;justify-content:flex-start;margin-top:.05rem}
    .uc-stats{margin:0 .82rem}
    .uc-acts{padding:.48rem .52rem .54rem}
}
@media(max-width:520px){
    .users-toolbar-right{gap:.35rem}
    .users-search{min-width:0}
    .filters{gap:1rem}
    .uc-stat{min-width:70px;padding:0 .62rem}
    .uc-btn,.uc-info,.uc-sub-btn,.uc-pw,.uc-role,.uc-del{font-size:.55rem}
    .uc-del{margin-left:0}
}

</style>
<link rel="stylesheet" href="assets/app-theme.css?v=20260723">
<script src="assets/app-theme.js?v=20260723"></script>
</head>
<body>
<div class="app">
<div class="overlay" id="ov"></div>

<?php require __DIR__ . '/includes/sidebar.php'; ?>

<main class="main">
<div class="admin-shell">
    <header class="admin-page-head anim d1">
        <div class="admin-title-wrap">
            <button class="ham" id="ham"><span class="material-symbols-outlined">menu</span></button>
            <div class="admin-title-icon"><span class="material-symbols-outlined">admin_panel_settings</span></div>
            <div>
                <div class="admin-title">Administração</div>
                <div class="admin-subtitle">Controle a receita de assinaturas da Uplar, acompanhe pagamentos e gerencie o acesso dos clientes em um único lugar.</div>
            </div>
        </div>
        <div class="admin-head-actions">
            <a href="estoque.php" class="admin-action"><span class="material-symbols-outlined">dashboard</span>Dashboard</a>
            <a href="produtos.php" class="admin-action"><span class="material-symbols-outlined">inventory_2</span>Produtos</a>
            <a href="vendedores.php" class="admin-action"><span class="material-symbols-outlined">groups</span>Vendedores</a>
            <button class="admin-action primary" id="btnNovoUser"><span class="material-symbols-outlined">person_add</span>Novo usuário</button>
        </div>
    </header>

    <nav class="admin-local-nav anim d2" aria-label="Seções da administração">
        <button class="admin-nav-link on" type="button" data-scroll-target="financeiro"><span class="material-symbols-outlined">monitoring</span>Visão financeira</button>
        <button class="admin-nav-link" type="button" data-scroll-target="pagamentos"><span class="material-symbols-outlined">payments</span>Pagamentos</button>
        <button class="admin-nav-link" type="button" data-scroll-target="usuarios"><span class="material-symbols-outlined">group</span>Usuários</button>
    </nav>

    <section class="finance-hero anim d3" id="financeiro">
        <article class="finance-main">
            <div class="finance-main-top">
                <div>
                    <div class="finance-kicker"><span class="material-symbols-outlined">query_stats</span>Receita Uplar</div>
                    <h2>Assinaturas com pagamento confirmado</h2>
                </div>
                <div class="finance-updated"><strong><?= date('d/m/Y') ?></strong>Atualizado nesta visualização</div>
            </div>

            <div class="finance-primary">
                <div class="finance-primary-label">Receita neste mês</div>
                <div class="finance-primary-value"><?= money_from_cents($financeiro_saas['mes_centavos']) ?></div>
                <div class="finance-primary-foot">
                    <?php if($crescimento_mes !== null): ?>
                        <span class="trend <?= $crescimento_mes >= 0 ? 'up' : 'down' ?>"><?= $crescimento_mes >= 0 ? '↑' : '↓' ?> <?= number_format(abs($crescimento_mes), 1, ',', '.') ?>%</span>
                        em relação ao mês anterior
                    <?php else: ?>
                        Ainda não há base no mês anterior para comparação.
                    <?php endif; ?>
                </div>
            </div>

            <div class="finance-mini-grid">
                <div class="finance-mini">
                    <div class="finance-mini-label">Total histórico</div>
                    <div class="finance-mini-value"><?= money_from_cents($financeiro_saas['total_centavos']) ?></div>
                </div>
                <div class="finance-mini">
                    <div class="finance-mini-label">Últimos 30 dias</div>
                    <div class="finance-mini-value"><?= money_from_cents($financeiro_saas['ultimos_30_centavos']) ?></div>
                </div>
                <div class="finance-mini">
                    <div class="finance-mini-label">Mês anterior</div>
                    <div class="finance-mini-value"><?= money_from_cents($financeiro_saas['mes_anterior_centavos']) ?></div>
                </div>
            </div>
        </article>

        <aside class="finance-health">
            <div class="finance-health-head">
                <div>
                    <div class="finance-health-title">Saúde da operação</div>
                    <div class="finance-health-sub">Assinaturas, checkout e base ativa</div>
                </div>
                <span class="health-icon"><span class="material-symbols-outlined">monitor_heart</span></span>
            </div>
            <div class="health-list">
                <div class="health-row">
                    <span class="health-dot ok"></span>
                    <span class="health-name">Assinaturas ativas</span>
                    <span class="health-val"><?= number_format($sub_ativas, 0, ',', '.') ?></span>
                </div>
                <div class="health-row">
                    <span class="health-dot <?= $sub_expirando > 0 ? 'warn' : 'ok' ?>"></span>
                    <span class="health-name">Vencem em até 7 dias</span>
                    <span class="health-val"><?= number_format($sub_expirando, 0, ',', '.') ?></span>
                </div>
                <div class="health-row">
                    <span class="health-dot <?= $taxa_conversao >= 50 ? 'ok' : ($taxa_conversao > 0 ? 'warn' : 'err') ?>"></span>
                    <span class="health-name">Conversão de checkouts</span>
                    <span class="health-val"><?= number_format($taxa_conversao, 1, ',', '.') ?>%</span>
                    <div class="health-progress"><span style="width:<?= min(100, max(0, $taxa_conversao)) ?>%"></span></div>
                </div>
                <div class="health-row">
                    <span class="health-dot <?= $financeiro_saas['checkouts_pendentes'] > 0 ? 'warn' : 'ok' ?>"></span>
                    <span class="health-name">Checkouts pendentes</span>
                    <span class="health-val"><?= number_format($financeiro_saas['checkouts_pendentes'], 0, ',', '.') ?></span>
                </div>
            </div>
            <div class="finance-note"><span class="material-symbols-outlined">info</span><span>Os valores desta área são exclusivamente da receita de assinaturas da Uplar. As vendas das lojas aparecem apenas na gestão de cada cliente.</span></div>
        </aside>
    </section>

    <section class="admin-metrics anim d3" aria-label="Indicadores operacionais">
        <article class="admin-metric">
            <span class="admin-metric-ico"><span class="material-symbols-outlined">sell</span></span>
            <div><div class="admin-metric-label">Ticket médio</div><div class="admin-metric-value"><?= money_from_cents($financeiro_saas['ticket_medio_centavos']) ?></div><div class="admin-metric-meta">por pagamento aprovado</div></div>
        </article>
        <article class="admin-metric ok">
            <span class="admin-metric-ico"><span class="material-symbols-outlined">task_alt</span></span>
            <div><div class="admin-metric-label">Pagamentos aprovados</div><div class="admin-metric-value"><?= number_format($financeiro_saas['pagamentos_aprovados'], 0, ',', '.') ?></div><div class="admin-metric-meta">desde o início da operação</div></div>
        </article>
        <article class="admin-metric violet">
            <span class="admin-metric-ico"><span class="material-symbols-outlined">group</span></span>
            <div><div class="admin-metric-label">Clientes cadastrados</div><div class="admin-metric-value"><?= number_format($regular, 0, ',', '.') ?></div><div class="admin-metric-meta"><?= number_format($total, 0, ',', '.') ?> contas incluindo admins</div></div>
        </article>
        <article class="admin-metric <?= $sub_alerta > 0 ? 'warn' : 'ok' ?>">
            <span class="admin-metric-ico"><span class="material-symbols-outlined">notification_important</span></span>
            <div><div class="admin-metric-label">Atenção de assinatura</div><div class="admin-metric-value"><?= number_format($sub_alerta, 0, ',', '.') ?></div><div class="admin-metric-meta">sem assinatura ou expirados</div></div>
        </article>
    </section>

    <section class="fin-grid anim d4" aria-label="Análise financeira">
        <div class="fin-card">
            <div class="fin-card-head"><div><div class="fin-card-title">Evolução da receita</div><div class="fin-card-sub">Pagamentos confirmados nos últimos seis meses</div></div><span class="fin-total-chip">6 meses</span></div>
            <div id="revenueChart" class="revenue-chart" role="img" aria-label="Gráfico da receita de assinaturas nos últimos seis meses"></div>
        </div>
        <div class="fin-card">
            <div class="fin-card-head"><div><div class="fin-card-title">Composição da receita</div><div class="fin-card-sub">Origem dos pagamentos aprovados</div></div></div>
            <div class="breakdown-section">
                <div class="breakdown-title">Por plano</div>
                <?php if($receita_planos): foreach($receita_planos as $plano):
                    $participacao = $financeiro_saas['total_centavos'] > 0 ? ((int)$plano['total_centavos'] / $financeiro_saas['total_centavos']) * 100 : 0;
                ?>
                <div class="breakdown-row"><div class="breakdown-top"><span class="breakdown-name"><?= htmlspecialchars(plan_label($plano['plano_key'])) ?></span><span class="breakdown-amount"><?= money_from_cents((int)$plano['total_centavos']) ?></span></div><div class="breakdown-meta"><?= (int)$plano['pagamentos'] ?> pagamento(s) · <?= number_format($participacao, 1, ',', '.') ?>%</div><div class="breakdown-track"><div class="breakdown-fill" style="width:<?= min(100, $participacao) ?>%"></div></div></div>
                <?php endforeach; else: ?><div class="fin-card-sub">Nenhum pagamento aprovado.</div><?php endif; ?>
            </div>
            <div class="breakdown-section">
                <div class="breakdown-title">Por método</div>
                <?php if($receita_metodos): foreach($receita_metodos as $metodo):
                    $participacao = $financeiro_saas['total_centavos'] > 0 ? ((int)$metodo['total_centavos'] / $financeiro_saas['total_centavos']) * 100 : 0;
                ?>
                <div class="breakdown-row"><div class="breakdown-top"><span class="breakdown-name"><?= htmlspecialchars(payment_method_label($metodo['metodo'])) ?></span><span class="breakdown-amount"><?= money_from_cents((int)$metodo['total_centavos']) ?></span></div><div class="breakdown-meta"><?= (int)$metodo['pagamentos'] ?> pagamento(s)</div><div class="breakdown-track"><div class="breakdown-fill gold" style="width:<?= min(100, $participacao) ?>%"></div></div></div>
                <?php endforeach; else: ?><div class="fin-card-sub">Nenhum método registrado.</div><?php endif; ?>
            </div>
        </div>
    </section>

    <div class="section-band anim d4" id="pagamentos">
        <div>
            <div class="section-band-title">Pagamentos</div>
            <div class="section-band-sub">Audite os pagamentos confirmados e controle o que compõe os indicadores financeiros.</div>
        </div>
        <span class="section-count"><?= number_format($financeiro_saas['pagamentos_aprovados'], 0, ',', '.') ?> aprovados</span>
    </div>

    <section class="fin-card payments-card anim d4">
        <div class="fin-card-head"><div><div class="fin-card-title">Pagamentos</div><div class="fin-card-sub">Controle quais assinaturas compõem a visão financeira</div></div><div class="payment-tabs"><button class="payment-tab on" type="button" data-payment-tab="visible">Visíveis (<?= $financeiro_saas['pagamentos_aprovados'] ?>)</button><button class="payment-tab" type="button" data-payment-tab="hidden">Ocultados (<?= $pagamentos_ocultos_total ?>)</button></div></div>
        <div class="payment-panel on" id="paymentPanelVisible">
        <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Cliente</th><th>Plano</th><th>Pagamento</th><th>Valor</th><th>Status</th><th>Confirmado em</th><th>Ações</th></tr></thead><tbody>
        <?php if($pagamentos_recentes): foreach($pagamentos_recentes as $pagamento): $receipt = safe_infinitepay_receipt($pagamento['receipt_url']); ?>
            <tr>
                <td><div class="tbl-name"><?= htmlspecialchars($pagamento['username'] ?: 'Usuário removido') ?></div><div class="tbl-sub"><?= htmlspecialchars($pagamento['email'] ?: $pagamento['order_nsu']) ?></div></td>
                <td><div class="tbl-name"><?= htmlspecialchars(plan_label($pagamento['plano_key'])) ?></div><div class="tbl-sub"><?= htmlspecialchars($pagamento['order_nsu']) ?></div></td>
                <td><div class="payment-method"><?= htmlspecialchars(payment_method_label($pagamento['capture_method'])) ?><?= $pagamento['installments'] ? ' · '.(int)$pagamento['installments'].'x' : '' ?></div></td>
                <td><strong><?= money_from_cents((int)$pagamento['valor_centavos']) ?></strong></td>
                <td><span class="payment-status"><span class="material-symbols-outlined">check_circle</span>Pago</span></td>
                <td><span class="payment-method"><?= htmlspecialchars($pagamento['pago_em_fmt'] ?: '—') ?></span></td>
                <td><div class="payment-actions"><?php if($receipt): ?><a class="receipt-link" href="<?= htmlspecialchars($receipt) ?>" target="_blank" rel="noopener noreferrer" title="Abrir comprovante"><span class="material-symbols-outlined">receipt_long</span></a><?php endif; ?><button class="payment-hide" type="button" onclick="setPaymentVisibility(<?= (int)$pagamento['id'] ?>,true)" title="Ocultar dos indicadores"><span class="material-symbols-outlined">visibility_off</span>Ocultar</button></div></td>
            </tr>
        <?php endforeach; else: ?><tr><td colspan="7"><div class="empty-state"><span class="material-symbols-outlined">payments</span><p>Nenhum pagamento aprovado.</p></div></td></tr><?php endif; ?>
        </tbody></table></div>
        </div>
        <div class="payment-panel" id="paymentPanelHidden">
            <div class="hidden-note"><span class="material-symbols-outlined">info</span><span>Pagamentos ocultos não entram na receita total, indicadores, gráficos ou composição por plano e método. O registro financeiro não é apagado e pode ser restaurado.</span></div>
            <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Cliente</th><th>Plano</th><th>Pagamento</th><th>Valor</th><th>Ocultado em</th><th>Ocultado por</th><th>Ações</th></tr></thead><tbody>
            <?php if($pagamentos_ocultos): foreach($pagamentos_ocultos as $pagamento): ?>
                <tr>
                    <td><div class="tbl-name"><?= htmlspecialchars($pagamento['username'] ?: 'Usuário removido') ?></div><div class="tbl-sub"><?= htmlspecialchars($pagamento['email'] ?: $pagamento['order_nsu']) ?></div></td>
                    <td><div class="tbl-name"><?= htmlspecialchars(plan_label($pagamento['plano_key'])) ?></div><div class="tbl-sub"><?= htmlspecialchars($pagamento['order_nsu']) ?></div></td>
                    <td><div class="payment-method"><?= htmlspecialchars(payment_method_label($pagamento['capture_method'])) ?><?= $pagamento['installments'] ? ' · '.(int)$pagamento['installments'].'x' : '' ?></div></td>
                    <td><strong><?= money_from_cents((int)$pagamento['valor_centavos']) ?></strong></td>
                    <td><span class="payment-method"><?= htmlspecialchars($pagamento['oculto_em_fmt'] ?: '—') ?></span></td>
                    <td><span class="payment-method"><?= htmlspecialchars($pagamento['oculto_por_nome'] ?: 'Administrador') ?></span></td>
                    <td><button class="payment-restore" type="button" onclick="setPaymentVisibility(<?= (int)$pagamento['id'] ?>,false)"><span class="material-symbols-outlined">restore</span>Restaurar</button></td>
                </tr>
            <?php endforeach; else: ?><tr><td colspan="7"><div class="empty-state"><span class="material-symbols-outlined">visibility</span><p>Nenhum pagamento ocultado.</p></div></td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </section>

    <div class="section-band anim d4" id="usuarios">
        <div>
            <div class="section-band-title">Gestão de usuários</div>
            <div class="section-band-sub">Contas, acessos, assinaturas e indicadores das lojas clientes.</div>
        </div>
        <span class="section-count"><?= number_format($total, 0, ',', '.') ?> contas</span>
    </div>

    <div class="card users-card anim d4">
        <div class="users-toolbar">
            <div class="users-toolbar-left">
                <div>
                    <div class="users-toolbar-title">Usuários do sistema</div>
                    <div class="users-toolbar-meta"><span id="userVisibleCount"><?= number_format($total, 0, ',', '.') ?></span> exibidos</div>
                </div>
            </div>
            <div class="users-toolbar-right">
                <div class="searchbox users-search">
                    <span class="material-symbols-outlined">search</span>
                    <input id="busca" placeholder="Buscar usuário…" type="text" autocomplete="off">
                </div>
                <div class="view-tog" aria-label="Modo de visualização">
                    <button class="vt-btn on" id="vGrid" title="Cards"><span class="material-symbols-outlined">grid_view</span></button>
                    <button class="vt-btn" id="vList" title="Lista"><span class="material-symbols-outlined">view_list</span></button>
                </div>
            </div>
        </div>
        <div class="filters">
            <button class="filter-btn on" data-f="all">Todos <span class="filter-count"><?= $total ?></span></button>
            <button class="filter-btn" data-f="admin">Admins <span class="filter-count"><?= $admins ?></span></button>
            <button class="filter-btn" data-f="user">Regulares <span class="filter-count"><?= $regular ?></span></button>
            <button class="filter-btn" data-f="ativa">Assinatura ativa <span class="filter-count"><?= $sub_ativas ?></span></button>
            <button class="filter-btn" data-f="alerta">Sem/Expirada <span class="filter-count"><?= $sub_alerta ?></span></button>
        </div>
        <div class="user-empty-filter" id="userEmptyFilter"><span class="material-symbols-outlined" style="display:block;font-size:1.6rem;margin-bottom:.35rem">person_search</span>Nenhum usuário corresponde aos filtros atuais.</div>

        <div class="ug" id="ug">
            <?php foreach($usuarios as $i=>$u):
                $isMe   = $u['id'] == $user_id;
                $isAdm  = $u['role'] === 'admin';
                $ini    = mb_strtoupper(mb_substr($u['username'],0,2));
                $avCls  = $isMe ? 'm' : ($isAdm ? 'a' : '');
                $cardCls= $isMe ? 'me-card' : ($isAdm ? 'admin-card' : '');
                $delay  = min($i * .04, .4);
                $sCalc  = $u['sub_status_calc'];
            ?>
            <div class="uc <?= $cardCls ?>"
                 data-role="<?= $u['role'] ?>"
                 data-name="<?= htmlspecialchars(strtolower($u['username'])) ?>"
                 data-sub="<?= $sCalc ?>"
                 style="animation-delay:<?= $delay ?>s">
                <div class="uc-top">
                    <div class="uc-av <?= $avCls ?>"><?= $ini ?></div>
                    <div class="uc-ident">
                        <div class="uc-name">
                            <?= htmlspecialchars($u['username']) ?>
                            <span class="rbadge <?= $u['role'] ?>"><?= $isAdm?'Admin':'User' ?></span>
                            <?php if($isMe):?><span class="me-badge">você</span><?php endif;?>
                        </div>
                        <div class="uc-subline"><span class="material-symbols-outlined">schedule</span>Conta criada em <?= $u['data_criacao']??'—' ?></div>
                    </div>
                    <div class="uc-status"><?= sub_badge($u) ?></div>
                </div>
                <div class="uc-stats">
                    <div class="uc-stat"><div class="uc-stat-v"><?= $u['total_produtos'] ?></div><div class="uc-stat-l">Produtos</div></div>
                    <div class="uc-stat"><div class="uc-stat-v"><?= $u['total_vendas'] ?></div><div class="uc-stat-l">Vendas</div></div>
                    <div class="uc-stat"><div class="uc-stat-v">R$ <?= number_format($u['receita_total'],0,'.','.') ?></div><div class="uc-stat-l">Receita da loja</div></div>
                </div>
                <div class="uc-acts">
                    <button class="uc-btn uc-info" onclick="openDetails(<?= $u['id'] ?>)"><span class="material-symbols-outlined">manage_search</span>Detalhes</button>
                    <?php if(!$isAdm):?>
                    <button class="uc-btn uc-sub-btn" onclick="openSub(<?= $u['id'] ?>,'<?= htmlspecialchars($u['username'],ENT_QUOTES) ?>','<?= htmlspecialchars($u['sub_data_fim'] ?? '',ENT_QUOTES) ?>','<?= $u['sub_status_calc'] ?>')"><span class="material-symbols-outlined">card_membership</span>Assinatura</button>
                    <?php endif;?>
                    <button class="uc-btn uc-pw" onclick="openPw(<?= $u['id'] ?>,'<?= htmlspecialchars($u['username'],ENT_QUOTES) ?>')"><span class="material-symbols-outlined">lock_reset</span>Senha</button>
                    <button class="uc-btn uc-pw" onclick="resetPasscode(<?= $u['id'] ?>,'<?= htmlspecialchars($u['username'],ENT_QUOTES) ?>')"><span class="material-symbols-outlined">shield_lock</span>Pass Code</button>
                    <?php if(!$isMe):?>
                    <button class="uc-btn uc-role" onclick="openRole(<?= $u['id'] ?>,'<?= htmlspecialchars($u['username'],ENT_QUOTES) ?>','<?= $u['role'] ?>')"><span class="material-symbols-outlined">manage_accounts</span>Acesso</button>
                    <button class="uc-btn uc-del"  onclick="openDel(<?= $u['id'] ?>,'<?= htmlspecialchars($u['username'],ENT_QUOTES) ?>')"><span class="material-symbols-outlined">delete</span>Excluir</button>
                    <?php endif;?>
                </div>
            </div>
            <?php endforeach;?>
        </div>

        <div class="tbl-wrap" id="tbl" style="display:none">
            <table class="tbl">
                <thead><tr><th>Usuário</th><th>Role</th><th>Assinatura</th><th>Produtos</th><th>Vendas</th><th>Receita loja</th><th>Ações</th></tr></thead>
                <tbody id="tblBody"></tbody>
            </table>
        </div>
    </div>

</div><!-- /.admin-shell -->
</main>
</div>

<nav class="bnav"><div class="bnav-inner">
    <a href="estoque.php"        class="bnav-a"><span class="material-symbols-outlined">dashboard</span>Início</a>
    <a href="produtos.php"       class="bnav-a"><span class="material-symbols-outlined">inventory</span>Produtos</a>
    <a href="vendedores.php"     class="bnav-a"><span class="material-symbols-outlined">group</span>Vendedores</a>
    <a href="admin_usuarios.php" class="bnav-a on"><span class="material-symbols-outlined">manage_accounts</span>Usuários</a>
</div></nav>

<!-- ═══ MODAL CRIAR USUÁRIO ═══ -->
<div class="modal" id="modal">
    <div class="mbox">
        <div class="mhead" id="stepHead">
            <button class="mhead-close" id="mclose"><span class="material-symbols-outlined">close</span></button>
            <h2 id="stepTitle">Novo Usuário</h2>
            <p id="stepSub">Defina as credenciais de acesso</p>
            <div class="step-dots" id="stepDots"></div>
        </div>
        <div class="mbody">
            <div class="step-p on" id="us1">
                <div class="fg">
                    <div class="fl fw"><label>Nome de usuário *</label>
                        <input id="uNome" placeholder="ex: joao.silva" autocomplete="off" oninput="validateUsername()">
                        <span class="hint">Mínimo 3 caracteres, sem espaços</span>
                        <span class="eh" id="uNomeErr">Usuário inválido</span>
                    </div>
                    <div class="fl fw"><label>Senha *</label>
                        <div class="pw-wrap">
                            <input id="uSenha" type="password" placeholder="Mínimo 6 caracteres" oninput="updateStrength()">
                            <button type="button" class="pw-eye" onclick="togglePw('uSenha',this)"><span class="material-symbols-outlined">visibility</span></button>
                        </div>
                        <div class="sw"><div class="sbar"><div class="sfill" id="sFill"></div></div><span class="slbl" id="sLbl">Digite uma senha</span></div>
                    </div>
                    <div class="fl fw"><label>Confirmar senha *</label>
                        <div class="pw-wrap">
                            <input id="uSenha2" type="password" placeholder="Repita a senha" oninput="checkMatch()">
                            <button type="button" class="pw-eye" onclick="togglePw('uSenha2',this)"><span class="material-symbols-outlined">visibility</span></button>
                        </div>
                        <span class="eh" id="matchErr">As senhas não coincidem</span>
                    </div>
                </div>
            </div>
            <div class="step-p" id="us2">
                <p style="font-size:.82rem;color:var(--muted);margin-bottom:.9rem">Escolha o nível de permissão.</p>
                <div class="role-opts">
                    <div class="role-opt on" data-role="user" onclick="selRole('user',this)">
                        <span class="material-symbols-outlined">person</span>
                        <div class="ro-name">Usuário Regular</div><div class="ro-sub">Gerencia seu próprio estoque</div>
                    </div>
                    <div class="role-opt" data-role="admin" onclick="selRole('admin',this)">
                        <span class="material-symbols-outlined">shield</span>
                        <div class="ro-name">Administrador</div><div class="ro-sub">Acesso total ao sistema</div>
                    </div>
                </div>
                <input type="hidden" id="uRole" value="user">
            </div>
            <div class="step-p" id="us3"><div id="uRevW"></div></div>
        </div>
        <div class="mfoot">
            <span class="step-lbl" id="stepLbl">Etapa 1 de 3</span>
            <div style="display:flex;gap:.5rem">
                <button class="btn-back" id="btnBack" style="display:none"><span class="material-symbols-outlined" style="font-size:1rem">arrow_back</span>Voltar</button>
                <button class="btn-next" id="btnNext">Próximo<span class="material-symbols-outlined">arrow_forward</span></button>
            </div>
        </div>
    </div>
</div>

<!-- ═══ MODAL ASSINATURA ═══ -->
<div class="modal" id="modalSub">
    <div class="sub-box">
        <div class="sub-head">
            <button class="sub-head-close" onclick="$('modalSub').style.display='none'"><span class="material-symbols-outlined">close</span></button>
            <h2><span class="material-symbols-outlined">card_membership</span>Gerenciar Assinatura</h2>
            <p id="subTarget">Selecione o plano para renovar o acesso</p>
        </div>
        <div class="sub-body">
            <div class="sub-current" id="subCurrent">
                <span class="material-symbols-outlined">info</span>
                <span id="subCurrentTxt">Carregando…</span>
            </div>

            <div class="plan-opts">
                <div class="plan-opt" data-dias="3" onclick="selPlan(3,this)">
                    <div class="plan-ico"></div>
                    <div>
                        <div class="plan-name">Teste — 3 dias</div>
                        <div class="plan-desc">Acesso completo para validar o sistema</div>
                    </div>
                    <div class="plan-check"></div>
                </div>
                <div class="plan-opt on" data-dias="30" onclick="selPlan(30,this)">
                    <div class="plan-ico"></div>
                    <div>
                        <div class="plan-name">Mensal — 30 dias</div>
                        <div class="plan-desc">Acesso completo por 1 mês</div>
                    </div>
                    <div class="plan-check"></div>
                </div>
                <div class="plan-opt" data-dias="90" onclick="selPlan(90,this)">
                    <div class="plan-ico"></div>
                    <div>
                        <div class="plan-name">Trimestral — 90 dias</div>
                        <div class="plan-desc">Acesso completo por 3 meses</div>
                    </div>
                    <div class="plan-check"></div>
                </div>
                <div class="plan-opt" data-dias="365" onclick="selPlan(365,this)">
                    <div class="plan-ico"></div>
                    <div>
                        <div class="plan-name">Anual — 365 dias</div>
                        <div class="plan-desc">Acesso completo por 1 ano</div>
                    </div>
                    <div class="plan-check"></div>
                </div>
            </div>

            <div class="fl sub-obs">
                <label>Observação (opcional)</label>
                <input type="text" id="subObs" placeholder="Ex: pago via pix em 01/03/2026" maxlength="200">
            </div>
        </div>
        <div class="sub-foot">
            <input type="hidden" id="subUid">
            <button class="sub-cancel" onclick="$('modalSub').style.display='none'">Cancelar</button>
            <button class="sub-confirm" id="btnSubConfirm" onclick="confirmarSub()">
                <span class="material-symbols-outlined">check_circle</span>Renovar Assinatura
            </button>
        </div>
    </div>
</div>

<!-- ═══ MODAL SENHA ═══ -->
<div class="modal" id="modalPw">
    <div class="sbox">
        <div class="sbox-head">
            <h2><span class="material-symbols-outlined">lock_reset</span>Alterar Senha</h2>
            <span class="mcl" id="pwClose">close</span>
        </div>
        <p style="font-size:.78rem;color:var(--muted);margin-bottom:.9rem" id="pwTarget"></p>
        <input type="hidden" id="pwUid">
        <div class="fl" style="margin-bottom:.8rem"><label>Nova senha *</label>
            <div class="pw-wrap">
                <input id="pwNew" type="password" placeholder="Mínimo 6 caracteres" oninput="updPwStr()">
                <button type="button" class="pw-eye" onclick="togglePw('pwNew',this)"><span class="material-symbols-outlined">visibility</span></button>
            </div>
            <div class="sw"><div class="sbar"><div class="sfill" id="pwSFill"></div></div><span class="slbl" id="pwSLbl">Digite uma senha</span></div>
        </div>
        <div class="fl"><label>Confirmar *</label>
            <div class="pw-wrap">
                <input id="pwNew2" type="password" placeholder="Repita a senha">
                <button type="button" class="pw-eye" onclick="togglePw('pwNew2',this)"><span class="material-symbols-outlined">visibility</span></button>
            </div>
            <span class="eh" id="pwMatchErr" style="display:none">As senhas não coincidem</span>
        </div>
        <button class="btn-save warn" id="btnPwSave"><span class="material-symbols-outlined">save</span>Alterar Senha</button>
    </div>
</div>

<!-- ═══ MODAL ROLE ═══ -->
<div class="modal" id="modalRole">
    <div class="sbox">
        <div class="sbox-head">
            <h2><span class="material-symbols-outlined" style="color:var(--violet)">manage_accounts</span>Alterar Permissão</h2>
            <span class="mcl" id="roleClose">close</span>
        </div>
        <p style="font-size:.78rem;color:var(--muted);margin-bottom:.9rem" id="roleTarget"></p>
        <input type="hidden" id="roleUid"><input type="hidden" id="roleNew" value="user">
        <div class="role-opts" style="margin-bottom:.9rem" id="roleOpts">
            <div class="role-opt on" data-role="user" onclick="selRoleM('user',this)">
                <span class="material-symbols-outlined">person</span>
                <div class="ro-name">Usuário Regular</div><div class="ro-sub">Gerencia seu próprio estoque</div>
            </div>
            <div class="role-opt" data-role="admin" onclick="selRoleM('admin',this)">
                <span class="material-symbols-outlined">shield</span>
                <div class="ro-name">Administrador</div><div class="ro-sub">Acesso total ao sistema</div>
            </div>
        </div>
        <button class="btn-save violet" id="btnRoleSave"><span class="material-symbols-outlined">save</span>Salvar Permissão</button>
    </div>
</div>

<!-- ═══ MODAL EXCLUIR ═══ -->
<div class="modal" id="modalDel">
    <div class="cbox2">
        <span class="cico material-symbols-outlined">warning</span>
        <div class="ctitle">Excluir usuário?</div>
        <div class="csub" id="delSub">Esta ação não pode ser desfeita.</div>
        <input type="hidden" id="delUid">
        <div class="cacts">
            <button class="ccancel" id="delCancel">Cancelar</button>
            <button class="cdel"    id="delConfirm">Excluir</button>
        </div>
    </div>
</div>

<!-- ═══ MODAL DETALHES ═══ -->
<div class="modal" id="modalDet">
    <div class="det-box">
        <div id="detHead"></div>
        <div class="dbody" id="detBody">
            <div class="loading-c"><div class="spinner"></div><span>Carregando…</span></div>
        </div>
    </div>
</div>

<div class="tw" id="tw"></div>

<script>
const CSRF  = <?= json_encode(get_csrf_token()) ?>;
const ME_ID = <?= (int)$user_id ?>;
const $     = id => document.getElementById(id);
const esc   = v => String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const R     = v => 'R$\u00a0'+Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
const mt    = v => (!v||String(v).trim()==='');
const fv    = v => mt(v) ? `<span class="ir-v mt">Não informado</span>` : `<span class="ir-v">${esc(v)}</span>`;
const REVENUE_MONTHLY = <?= json_encode($receita_mensal, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

/* Gráfico financeiro — padrão ECharts usado no projeto */
let revenueChart = null;
let revenueChartObserver = null;
function adminChartCss(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
}
function renderRevenueChart() {
    const el = $('revenueChart');
    if (!el) return;
    if (typeof echarts === 'undefined') {
        el.innerHTML = '<div class="chart-unavailable">Não foi possível carregar o gráfico financeiro.</div>';
        return;
    }
    if (!revenueChart) {
        revenueChart = echarts.init(el, null, {renderer:'canvas'});
        if ('ResizeObserver' in window) {
            revenueChartObserver = new ResizeObserver(() => revenueChart?.resize());
            revenueChartObserver.observe(el);
        }
    }
    const dark = document.documentElement.dataset.theme === 'dark';
    const ink = adminChartCss('--ink', dark ? '#edf3ff' : '#0f1c2e');
    const faint = adminChartCss('--faint', dark ? '#7f8da4' : '#94a3b8');
    const border = adminChartCss('--border', dark ? '#233149' : '#e4eaf2');
    const card = adminChartCss('--card', dark ? '#111b2b' : '#ffffff');
    const values = REVENUE_MONTHLY.map(item => Number(item.total_centavos || 0) / 100);
    const empty = values.every(value => value === 0);

    revenueChart.setOption({
        backgroundColor:'transparent',
        animationDuration:650,
        animationEasing:'cubicOut',
        grid:{left:10,right:12,top:22,bottom:8,containLabel:true},
        tooltip:{
            trigger:'axis',
            axisPointer:{type:'shadow',shadowStyle:{color:dark?'rgba(111,149,255,.10)':'rgba(59,111,245,.07)'}},
            backgroundColor:card,borderColor:border,borderWidth:1,
            textStyle:{color:ink,fontFamily:'Inter, sans-serif',fontSize:12},
            formatter:params=>{
                const index=params[0]?.dataIndex||0,item=REVENUE_MONTHLY[index]||{};
                return `<strong>${esc(item.mes||'')}</strong><br>${R(values[index]||0)}<br>${Number(item.pagamentos||0)} pagamento(s)`;
            }
        },
        xAxis:{
            type:'category',data:REVENUE_MONTHLY.map(item=>item.mes),
            axisTick:{show:false},axisLine:{lineStyle:{color:border}},
            axisLabel:{color:faint,fontSize:11,margin:11}
        },
        yAxis:{
            type:'value',min:0,splitNumber:4,
            axisLine:{show:false},axisTick:{show:false},
            axisLabel:{color:faint,fontSize:10,formatter:value=>value>=1000?'R$ '+(value/1000).toFixed(1).replace('.',',')+' mil':'R$ '+Number(value).toLocaleString('pt-BR')},
            splitLine:{lineStyle:{color:border,type:'dashed',opacity:.72}}
        },
        series:[{
            name:'Receita',type:'bar',data:values.map((value,index)=>({
                value,
                itemStyle:{color:index===values.length-1?'#c99a2e':'#3b6ff5',borderRadius:[5,5,1,1]}
            })),
            barMaxWidth:38,barMinHeight:empty?0:3,
            emphasis:{itemStyle:{opacity:.84}}
        }],
        graphic:empty?[{type:'text',left:'center',top:'middle',style:{text:'Nenhuma receita no período',fill:faint,font:'600 12px Inter, sans-serif'}}]:[]
    }, true);
    requestAnimationFrame(() => revenueChart?.resize());
}

window.addEventListener('load', renderRevenueChart);
window.addEventListener('resize', () => revenueChart?.resize());
window.addEventListener('uplar:themechange', renderRevenueChart);

document.querySelectorAll('[data-payment-tab]').forEach(button => {
    button.addEventListener('click', () => {
        document.querySelectorAll('[data-payment-tab]').forEach(item => item.classList.toggle('on', item === button));
        const hidden = button.dataset.paymentTab === 'hidden';
        $('paymentPanelVisible').classList.toggle('on', !hidden);
        $('paymentPanelHidden').classList.toggle('on', hidden);
    });
});

async function setPaymentVisibility(paymentId, hide) {
    const message = hide
        ? 'Ocultar este pagamento? Ele deixará de entrar em todos os totais e gráficos, mas poderá ser restaurado.'
        : 'Restaurar este pagamento nos indicadores financeiros?';
    if (!window.confirm(message)) return;
    const fd = new FormData();
    fd.append('action', hide ? 'hide_payment' : 'restore_payment');
    fd.append('payment_id', String(paymentId));
    fd.append('csrf_token', CSRF);
    try {
        const response = await fetch('admin_usuarios_action.php', {method:'POST',body:fd,credentials:'same-origin'});
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.error || 'Não foi possível atualizar o pagamento.');
        toast(hide ? 'Pagamento ocultado dos indicadores.' : 'Pagamento restaurado nos indicadores.', 'ok');
        setTimeout(() => location.reload(), 450);
    } catch (error) {
        toast(error.message || 'Erro ao atualizar pagamento.', 'err');
    }
}

/* toast */
function toast(msg,type='ok',dur=3200){
    const w=$('tw'),el=document.createElement('div');
    el.className='toast '+(type==='err'?'err':type==='warn'?'warn':'ok');
    el.innerHTML=`<span class="material-symbols-outlined">${type==='err'?'error':type==='warn'?'warning':'check_circle'}</span>${esc(msg)}`;
    w.appendChild(el);
    setTimeout(()=>{el.style.animation='toastOut .3s forwards';setTimeout(()=>el.remove(),300)},dur);
}

/* KPIs animados */
function cnt(el,to,fmt){
    const s=performance.now(),dur=600;
    const t=now=>{const p=Math.min((now-s)/dur,1),e=1-Math.pow(1-p,3);el.textContent=fmt(to*e);if(p<1)requestAnimationFrame(t);else el.textContent=fmt(to)};
    requestAnimationFrame(t);
}

const UJ = <?= json_encode($usuarios,JSON_UNESCAPED_UNICODE) ?>;
window.addEventListener('load',()=>{
    if(window.UplarTracker){
        window.UplarTracker.trackEvent('view_admin_users', { origem: 'admin_usuarios' }, 'navigation');
    }
});

/* navegação local da administração */
document.querySelectorAll('[data-scroll-target]').forEach(button=>{
    button.addEventListener('click',()=>{
        const target=$(button.dataset.scrollTarget);
        if(target) target.scrollIntoView({behavior:'smooth',block:'start'});
        document.querySelectorAll('.admin-nav-link').forEach(item=>item.classList.toggle('on',item===button));
    });
});

/* sidebar */
const sb=$('sb'),ov=$('ov');
$('ham').addEventListener('click',()=>{sb.classList.add('open');ov.classList.add('on');document.body.style.overflow='hidden'});
ov.addEventListener('click',()=>{sb.classList.remove('open');ov.classList.remove('on');document.body.style.overflow=''});

/* view toggle */
let viewMode='grid';
$('vGrid').addEventListener('click',()=>setView('grid'));
$('vList').addEventListener('click',()=>setView('list'));
function setView(v){
    viewMode=v;
    $('vGrid').classList.toggle('on',v==='grid');
    $('vList').classList.toggle('on',v==='list');
    $('ug').style.display=v==='grid'?'grid':'none';
    $('tbl').style.display=v==='list'?'block':'none';
    if($('userEmptyFilter')) $('userEmptyFilter').classList.remove('on');
    if(v==='list') renderTable();
    else applyF();
}

/* filtros */
let af='all';
document.querySelectorAll('.filter-btn').forEach(b=>{
    b.addEventListener('click',()=>{
        document.querySelectorAll('.filter-btn').forEach(x=>x.classList.remove('on'));
        b.classList.add('on'); af=b.dataset.f; applyF();
    });
});
$('busca').addEventListener('input',applyF);
function applyF(){
    const t=$('busca').value.toLowerCase().trim();
    let visible=0;
    document.querySelectorAll('.uc').forEach(el=>{
        const role=el.dataset.role, sub=el.dataset.sub;
        const mF=af==='all'
            ||(af==='admin'&&role==='admin')
            ||(af==='user'&&role==='user')
            ||(af==='ativa'&&sub==='ativa')
            ||(af==='alerta'&&['expirada','sem_inscricao'].includes(sub));
        const mS=!t||(el.dataset.name||'').includes(t);
        const show=mF&&mS;
        el.style.display=show?'':'none';
        if(show) visible++;
    });
    if($('userVisibleCount')) $('userVisibleCount').textContent=visible.toLocaleString('pt-BR');
    if($('userEmptyFilter')) $('userEmptyFilter').classList.toggle('on', visible===0 && viewMode==='grid');
    if(viewMode==='list') renderTable();
}

/* Tabela */
function subBadgeHTML(u){
    const s=u.sub_status_calc;
    if(s==='admin') return '<span class="sub-badge admin"><span class="material-symbols-outlined">shield</span>Admin</span>';
    if(s==='ativa'){
        const d=parseInt(u.sub_dias||0);
        const cls=d<=7?'expirando':'ativa';
        const lbl=d<=7?`Expira em ${d}d`:fmtDateBR(u.sub_data_fim);
        return `<span class="sub-badge ${cls}"><span class="material-symbols-outlined">${d<=7?'warning':'check_circle'}</span>${esc(lbl)}</span>`;
    }
    if(s==='expirada') return '<span class="sub-badge expirada"><span class="material-symbols-outlined">event_busy</span>Expirada</span>';
    if(s==='suspensa') return '<span class="sub-badge suspensa"><span class="material-symbols-outlined">pause_circle</span>Suspensa</span>';
    return '<span class="sub-badge sem"><span class="material-symbols-outlined">lock</span>Sem assinatura</span>';
}
function fmtDateBR(d){if(!d)return '—';const p=d.split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d;}

function renderTable(){
    const t=$('busca').value.toLowerCase().trim(), tb=$('tblBody');
    tb.innerHTML='';
    UJ.filter(u=>{
        const mF=af==='all'
            ||(af==='admin'&&u.role==='admin')
            ||(af==='user'&&u.role==='user')
            ||(af==='ativa'&&u.sub_status_calc==='ativa')
            ||(af==='alerta'&&['expirada','sem_inscricao'].includes(u.sub_status_calc));
        return mF&&(!t||u.username.toLowerCase().includes(t));
    }).forEach(u=>{
        const isMe=u.id==ME_ID,isAdm=u.role==='admin';
        const bg=isMe?'linear-gradient(135deg,var(--ok),#34d399)':isAdm?'linear-gradient(135deg,var(--violet),#a855f7)':'linear-gradient(135deg,var(--p),#7ca0ff)';
        const tr=document.createElement('tr');
        tr.innerHTML=`
            <td><div class="tbl-cell">
                <div class="tbl-av" style="background:${bg}">${u.username.slice(0,2).toUpperCase()}</div>
                <div><div class="tbl-name">${esc(u.username)}</div><div class="tbl-sub">${esc(u.data_criacao||'—')}</div></div>
            </div></td>
            <td><span class="badge ${u.role}">${isAdm?'Admin':'User'}</span>${isMe?'<span class="badge me" style="margin-left:.3rem">você</span>':''}</td>
            <td>${subBadgeHTML(u)}</td>
            <td>${u.total_produtos}</td><td>${u.total_vendas}</td><td>${R(u.receita_total)}</td>
            <td><div class="tbl-acts">
                <button class="tbl-btn" onclick="openDetails(${u.id})" title="Detalhes"><span class="material-symbols-outlined">manage_search</span></button>
                ${!isAdm?`<button class="tbl-btn sub" onclick="openSub(${u.id},'${esc(u.username)}','${u.sub_data_fim||''}','${u.sub_status_calc}')" title="Assinatura"><span class="material-symbols-outlined">card_membership</span></button>`:''}
                <button class="tbl-btn pw"  onclick="openPw(${u.id},'${esc(u.username)}')" title="Senha"><span class="material-symbols-outlined">lock_reset</span></button>
                <button class="tbl-btn pw"  onclick="resetPasscode(${u.id},'${esc(u.username)}')" title="Resetar Pass Code"><span class="material-symbols-outlined">shield_lock</span></button>
                ${!isMe?`
                <button class="tbl-btn role" onclick="openRole(${u.id},'${esc(u.username)}','${u.role}')" title="Role"><span class="material-symbols-outlined">manage_accounts</span></button>
                <button class="tbl-btn del"  onclick="openDel(${u.id},'${esc(u.username)}')"  title="Excluir"><span class="material-symbols-outlined">delete</span></button>
                `:'<span style="font-size:.72rem;color:var(--faint)">(você)</span>'}
            </div></td>`;
        tb.appendChild(tr);
    });
}

/* ══════════════════════════════════════════
   MODAL ASSINATURA
══════════════════════════════════════════ */
let subPlanSel = 30;

function selPlan(dias, el) {
    subPlanSel = dias;
    document.querySelectorAll('.plan-opt').forEach(x => x.classList.remove('on'));
    el.classList.add('on');
}

function openSub(id, nome, dataFim, statusCalc) {
    $('subUid').value = id;
    $('subObs').value = '';
    $('subTarget').textContent = `Usuário: ${nome}`;
    subPlanSel = 30;
    document.querySelectorAll('.plan-opt').forEach(x => x.classList.remove('on'));
    document.querySelector('.plan-opt[data-dias="30"]').classList.add('on');

    const sc = $('subCurrentTxt');
    if (statusCalc === 'ativa' && dataFim) {
        const dias = Math.round((new Date(dataFim) - new Date()) / 86400000);
        sc.innerHTML = `<strong>Assinatura ativa</strong> — expira em ${fmtDateBR(dataFim)} (${Math.max(0,dias)} dias restantes). Renovar substituirá a assinatura atual.`;
    } else if (statusCalc === 'expirada') {
        sc.innerHTML = `<strong>Assinatura expirada</strong>${dataFim?' em '+fmtDateBR(dataFim):''}. Renovar reativa o acesso.`;
    } else if (statusCalc === 'suspensa') {
        sc.innerHTML = `<strong>Assinatura suspensa.</strong> Renovar reativa o acesso.`;
    } else {
        sc.innerHTML = `<strong>Sem assinatura.</strong> Primeira ativação.`;
    }

    $('modalSub').style.display = 'flex';
}

async function confirmarSub() {
    const btn = $('btnSubConfirm');
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner" style="border-top-color:#fff;width:16px;height:16px;border-width:2px"></div>Processando…';
    const fd = new FormData();
    fd.append('action',     'renovar_inscricao');
    fd.append('user_id',    $('subUid').value);
    fd.append('plano_dias', subPlanSel);
    fd.append('observacao', $('subObs').value);
    fd.append('csrf_token', CSRF);
    try {
        const r = await fetch('admin_usuarios_action.php', {method:'POST',body:fd,credentials:'same-origin'});
        const j = await r.json();
        if (j.ok) {
            $('modalSub').style.display = 'none';
            toast(` Assinatura de ${j.username} renovada até ${j.data_fim_br}!`, 'ok', 4000);
            setTimeout(() => location.reload(), 2000);
        } else {
            toast(j.error || 'Erro ao renovar', 'err');
        }
    } catch { toast('Erro de conexão', 'err'); }
    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined">check_circle</span>Renovar Assinatura';
}

$('modalSub').addEventListener('click', e => { if (e.target === $('modalSub')) $('modalSub').style.display = 'none'; });

/* ══════════════════════════════════════════
   MODAL DETALHES
══════════════════════════════════════════ */
async function openDetails(uid){
    if(window.UplarTracker){
        window.UplarTracker.trackEvent('view_user_details', { alvo_user_id: uid }, 'admin');
    }
    $('detHead').innerHTML=`<div class="dh blue"><button class="dh-close" onclick="closeDet()"><span class="material-symbols-outlined">close</span></button><div class="dh-top"><div class="dh-av">…</div><div><div class="dh-name">Carregando…</div></div></div></div>`;
    $('detBody').innerHTML=`<div class="loading-c"><div class="spinner"></div><span>Buscando dados…</span></div>`;
    $('modalDet').style.display='flex';
    document.body.style.overflow='hidden';
    const fd=new FormData();
    fd.append('action','get_user_details');fd.append('user_id',uid);fd.append('csrf_token',CSRF);
    let j;
    try{const r=await fetch('admin_usuarios_action.php',{method:'POST',body:fd,credentials:'same-origin'});j=await r.json();}
    catch(e){$('detBody').innerHTML=`<div class="loading-c"><span style="color:var(--err)">Erro de conexão.</span></div>`;return;}
    if(!j.ok){$('detBody').innerHTML=`<div class="loading-c"><span style="color:var(--err)">${esc(j.error||'Erro')}</span></div>`;return;}

    const u=j.user,em=j.empresa,lg=j.logins,fin=j.financeiro,ul=j.ultimo_login,sub=j.subscription,subH=j.sub_historico||[];
    const isAdm=u.role==='admin',isMe=u.id==ME_ID;
    const hCls=isMe?'green':isAdm?'violet':'blue';
    const ini=u.username.slice(0,2).toUpperCase();

    $('detHead').innerHTML=`
        <div class="dh ${hCls}">
            <button class="dh-close" onclick="closeDet()"><span class="material-symbols-outlined">close</span></button>
            <div class="dh-top">
                <div class="dh-av">${ini}</div>
                <div>
                    <div class="dh-name">${esc(u.username)}</div>
                    <div class="dh-sub">Cadastrado em ${esc(u.data_criacao||'—')}</div>
                    <div class="dh-badges">
                        <span class="dh-badge"><span class="material-symbols-outlined">${isAdm?'shield':'person'}</span>${isAdm?'Administrador':'Usuário Regular'}</span>
                        ${isMe?'<span class="dh-badge"><span class="material-symbols-outlined">star</span>Você</span>':''}
                        ${buildSubBadgeDet(sub)}
                        <span class="dh-badge"><span class="material-symbols-outlined">login</span>${ul?'Último: '+esc(ul.data_fmt+' '+ul.hora_fmt):'Sem acessos'}</span>
                    </div>
                </div>
            </div>
            <div class="dh-stats">
                <div class="dh-stat"><div class="dh-stat-v">${u.total_produtos}</div><div class="dh-stat-l">Produtos</div></div>
                <div class="dh-stat"><div class="dh-stat-v">${u.total_vendas}</div><div class="dh-stat-l">Vendas</div></div>
                <div class="dh-stat"><div class="dh-stat-v">${u.total_vendedores}</div><div class="dh-stat-l">Vendedores</div></div>
                <div class="dh-stat"><div class="dh-stat-v">${R(u.receita_total)}</div><div class="dh-stat-l">Receita</div></div>
            </div>
        </div>`;

    $('detBody').innerHTML=`
        <div class="dtabs">
            <button class="dtab on" data-t="conta"><span class="material-symbols-outlined">person</span>Conta</button>
            <button class="dtab" data-t="assinatura"><span class="material-symbols-outlined">card_membership</span>Assinatura</button>
            <button class="dtab" data-t="empresa"><span class="material-symbols-outlined">business</span>Empresa</button>
            <button class="dtab" data-t="financeiro"><span class="material-symbols-outlined">account_balance_wallet</span>Financeiro</button>
            <button class="dtab" data-t="acessos"><span class="material-symbols-outlined">history</span>Acessos</button>
        </div>
        <div class="dtab-p on" id="tp-conta">${tabConta(u)}</div>
        <div class="dtab-p" id="tp-assinatura">${tabAssinatura(sub,subH,u)}</div>
        <div class="dtab-p" id="tp-empresa">${tabEmpresa(em)}</div>
        <div class="dtab-p" id="tp-financeiro">${tabFin(fin,u)}</div>
        <div class="dtab-p" id="tp-acessos">${tabAcessos(lg)}</div>
    `;
    document.querySelectorAll('.dtab').forEach(tab=>{
        tab.addEventListener('click',()=>{
            document.querySelectorAll('.dtab').forEach(t=>t.classList.remove('on'));
            document.querySelectorAll('.dtab-p').forEach(p=>p.classList.remove('on'));
            tab.classList.add('on');$('tp-'+tab.dataset.t).classList.add('on');
        });
    });
}
function closeDet(){$('modalDet').style.display='none';document.body.style.overflow='';}
$('modalDet').addEventListener('click',e=>{if(e.target===$('modalDet'))closeDet()});

function buildSubBadgeDet(sub){
    if(!sub||sub.status==='admin') return '<span class="dh-badge"><span class="material-symbols-outlined">shield</span>Admin — sem expiração</span>';
    if(sub.status==='ativa'){
        const d=sub.dias_restantes;
        return `<span class="dh-badge"><span class="material-symbols-outlined">${d<=7?'warning':'check_circle'}</span>${d<=7?`Expira em ${d}d`:'Assinatura ativa até '+fmtDateBR(sub.data_fim)}</span>`;
    }
    if(sub.status==='expirada') return `<span class="dh-badge" style="background:rgba(239,68,68,.25)"><span class="material-symbols-outlined">event_busy</span>Expirada em ${fmtDateBR(sub.data_fim)}</span>`;
    if(sub.status==='suspensa') return `<span class="dh-badge" style="background:rgba(245,158,11,.25)"><span class="material-symbols-outlined">pause_circle</span>Suspensa</span>`;
    return `<span class="dh-badge" style="background:rgba(239,68,68,.25)"><span class="material-symbols-outlined">lock</span>Sem assinatura</span>`;
}

function tabAssinatura(sub, hist, u) {
    const isAdm = u.role === 'admin';
    let statusHtml = '';
    if (isAdm) {
        statusHtml = `<div class="ir s2"><div class="ir-l">Status</div><div class="ir-v" style="color:var(--violet)">Admin — acesso permanente</div></div>`;
    } else if (!sub || sub.status === 'sem_inscricao') {
        statusHtml = `<div class="ir s2"><div class="ir-l">Status</div><div class="ir-v" style="color:var(--err)">Sem assinatura</div></div>`;
    } else {
        const cor = sub.status==='ativa'?(sub.expirando?'var(--warn)':'var(--ok)'):'var(--err)';
        statusHtml = `
            <div class="ir"><div class="ir-l">Status</div><div class="ir-v" style="color:${cor}">${sub.status.charAt(0).toUpperCase()+sub.status.slice(1)}</div></div>
            <div class="ir"><div class="ir-l">Válida até</div><div class="ir-v">${fmtDateBR(sub.data_fim)}</div></div>
            <div class="ir"><div class="ir-l">Dias restantes</div><div class="ir-v">${sub.status==='ativa'?sub.dias_restantes+' dias':'—'}</div></div>
            ${sub.inscricao?`<div class="ir"><div class="ir-l">Plano contratado</div><div class="ir-v">${sub.inscricao.plano_dias} dias</div></div>`:''}
        `;
    }

    const acoes = !isAdm ? `
        <div style="display:flex;gap:.5rem;margin-top:.8rem;flex-wrap:wrap">
            <button style="flex:2;padding:.6rem;background:var(--ok);color:#fff;border:none;border-radius:9px;font-size:.82rem;font-weight:700;font-family:inherit;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.35rem;transition:all .2s;box-shadow:0 3px 10px rgba(16,185,129,.25)"
                onclick="closeDet();openSub(${u.id},'${esc(u.username)}','${sub&&sub.data_fim?sub.data_fim:''}','${sub?sub.status:'sem_inscricao'}')">
                <span class="material-symbols-outlined" style="font-size:.95rem">autorenew</span>Renovar
            </button>
        </div>` : '';

    // Histórico
    const histHtml = hist && hist.length ? hist.map(h => {
        const acaoIcos = {criada:'add_circle',renovada:'autorenew',suspensa:'pause_circle',expirada:'event_busy',reativada:'play_circle'};
        const cls = ['suspensa','expirada'].includes(h.acao) ? h.acao : h.acao==='criada'?'criada':'';
        const methodLabels = {pix:'Pix',credit_card:'Cartão de crédito',debit_card:'Cartão de débito'};
        const method = methodLabels[h.capture_method] || h.capture_method || '—';
        const paidAmount = h.paid_amount_centavos !== null && h.paid_amount_centavos !== '' ? Number(h.paid_amount_centavos) : Number(h.valor_centavos);
        const chargedAmount = h.gateway_amount_centavos !== null && h.gateway_amount_centavos !== '' ? Number(h.gateway_amount_centavos) : Number(h.valor_centavos);
        const cents = value => Number.isFinite(value) ? (value / 100).toLocaleString('pt-BR',{style:'currency',currency:'BRL'}) : '—';
        const receiptUrl = (() => {
            try {
                const url = new URL(h.receipt_url || '');
                const validHost = url.hostname === 'recibo.infinitepay.io' || url.hostname.endsWith('.infinitepay.io') || url.hostname.endsWith('.infinitepay.com.br');
                return url.protocol === 'https:' && validHost ? url.href : '';
            } catch (_) { return ''; }
        })();
        const items = Array.isArray(h.payment_items) ? h.payment_items : [];
        const itemsHtml = items.length ? `<div class="shi-items"><strong>Itens:</strong> ${items.map(item =>
            `${esc(item.quantity)}× ${esc(item.description||'Item')} (${cents(Number(item.price))})`
        ).join(' · ')}</div>` : '';
        const paymentHtml = h.order_nsu ? `
            <div class="shi-payment">
                <div class="shi-pay-grid">
                    <div class="shi-pay-field"><div class="shi-pay-label">Forma de pagamento</div><div class="shi-pay-value">${esc(method)}</div></div>
                    <div class="shi-pay-field"><div class="shi-pay-label">Valor pago</div><div class="shi-pay-value">${cents(paidAmount)}</div></div>
                    <div class="shi-pay-field"><div class="shi-pay-label">Valor do pedido</div><div class="shi-pay-value">${cents(chargedAmount)}</div></div>
                    <div class="shi-pay-field"><div class="shi-pay-label">Parcelas</div><div class="shi-pay-value">${h.installments?esc(h.installments)+'x':'—'}</div></div>
                    <div class="shi-pay-field"><div class="shi-pay-label">Cliente da conta</div><div class="shi-pay-value">${esc(h.payer_nome||u.username||'—')}</div></div>
                    <div class="shi-pay-field"><div class="shi-pay-label">E-mail da conta</div><div class="shi-pay-value">${esc(h.payer_email||'—')}</div></div>
                    <div class="shi-pay-field"><div class="shi-pay-label">Telefone da conta</div><div class="shi-pay-value">${esc(h.payer_telefone||'—')}</div></div>
                    <div class="shi-pay-field"><div class="shi-pay-label">Pedido</div><div class="shi-pay-value mono">${esc(h.order_nsu)}</div></div>
                    <div class="shi-pay-field"><div class="shi-pay-label">Transação NSU</div><div class="shi-pay-value mono">${esc(h.transaction_nsu||'—')}</div></div>
                    <div class="shi-pay-field"><div class="shi-pay-label">Fatura</div><div class="shi-pay-value mono">${esc(h.invoice_slug||'—')}</div></div>
                    <div class="shi-pay-field"><div class="shi-pay-label">Confirmado em</div><div class="shi-pay-value">${esc(h.pago_em_fmt||h.data_fmt||'—')}</div></div>
                </div>
                ${itemsHtml}
                ${receiptUrl?`<a class="shi-receipt" href="${esc(receiptUrl)}" target="_blank" rel="noopener noreferrer"><span class="material-symbols-outlined">receipt_long</span>Abrir comprovante InfinitePay</a>`:''}
            </div>` : '';
        const responsible = h.order_nsu ? 'Pagamento automático · InfinitePay' : `Por: ${esc(h.admin_nome||'—')}`;
        return `<div class="sub-hist-item ${cls}">
            <div class="shi-ico"><span class="material-symbols-outlined">${acaoIcos[h.acao]||'history'}</span></div>
            <div class="shi-info">
                <div class="shi-title">${h.acao.charAt(0).toUpperCase()+h.acao.slice(1)}${h.plano_dias?' — '+h.plano_dias+' dias':''}</div>
                <div class="shi-sub">${h.data_fim_nova?'Válida até '+fmtDateBR(h.data_fim_nova):''}</div>
                <div class="shi-admin">${responsible} · ${esc(h.data_fmt||'')}</div>
                ${paymentHtml}
            </div>
        </div>`;
    }).join('') : `<div class="empty-state"><span class="material-symbols-outlined">history</span><p>Nenhum histórico encontrado</p></div>`;

    return `
        <div class="ib">
            <div class="ib-title"><span class="material-symbols-outlined">card_membership</span>Status Atual</div>
            <div class="ir-grid">${statusHtml}</div>
            ${acoes}
        </div>
        <div class="ib">
            <div class="ib-title"><span class="material-symbols-outlined">history</span>Histórico de Renovações</div>
            ${histHtml}
        </div>`;
}

function tabConta(u){return `
    <div class="ib">
        <div class="ib-title"><span class="material-symbols-outlined">badge</span>Dados de Acesso</div>
        <div class="ir-grid">
            <div class="ir"><div class="ir-l">Usuário</div><div class="ir-v">${esc(u.username)}</div></div>
            <div class="ir"><div class="ir-l">Nível de acesso</div><div class="ir-v">${u.role==='admin'?'Administrador':'Usuário Regular'}</div></div>
            <div class="ir"><div class="ir-l">Cadastrado em</div>${fv(u.data_criacao)}</div>
            <div class="ir"><div class="ir-l">WhatsApp vinculado</div>${fv(u.whatsapp_number)}</div>
            <div class="ir"><div class="ir-l">ID no sistema</div><div class="ir-v" style="font-family:monospace">#${u.id}</div></div>
        </div>
    </div>
    <div class="ib">
        <div class="ib-title"><span class="material-symbols-outlined">bar_chart</span>Atividade</div>
        <div class="ir-grid">
            <div class="ir"><div class="ir-l">Produtos</div><div class="ir-v">${u.total_produtos}</div></div>
            <div class="ir"><div class="ir-l">Vendas</div><div class="ir-v">${u.total_vendas}</div></div>
            <div class="ir"><div class="ir-l">Vendedores</div><div class="ir-v">${u.total_vendedores}</div></div>
            <div class="ir"><div class="ir-l">Receita total</div><div class="ir-v" style="color:var(--ok);font-weight:700">${R(u.receita_total)}</div></div>
        </div>
    </div>`;}

function tabEmpresa(em){
    if(!em||(!em.razao_social&&!em.cnpj&&!em.email)){return `<div class="empty-state"><span class="material-symbols-outlined">business</span><p>Nenhum dado de empresa configurado.</p></div>`;}
    const logoHtml=em.logo_path?`<img src="${esc(em.logo_path)}" class="logo-mini" alt="Logo">`:`<div class="logo-ph"><span class="material-symbols-outlined">image_not_supported</span></div>`;
    return `
    <div class="ib">
        <div class="ib-title"><span class="material-symbols-outlined">business</span>Identificação</div>
        <div style="display:flex;align-items:center;gap:.85rem;background:var(--bg);border-radius:10px;border:1px solid var(--border);padding:.75rem .9rem;margin-bottom:.7rem">
            ${logoHtml}<div><div style="font-weight:700;font-size:.88rem">${esc(em.razao_social||'—')}</div>${em.nome_fantasia?`<div style="font-size:.75rem;color:var(--muted)">${esc(em.nome_fantasia)}</div>`:''}</div>
        </div>
        <div class="ir-grid">
            <div class="ir"><div class="ir-l">Razão Social</div>${fv(em.razao_social)}</div>
            <div class="ir"><div class="ir-l">CNPJ</div>${fv(em.cnpj)}</div>
            <div class="ir"><div class="ir-l">Telefone</div>${fv(em.telefone)}</div>
            <div class="ir s2"><div class="ir-l">E-mail</div>${fv(em.email)}</div>
            <div class="ir"><div class="ir-l">Cidade</div>${fv(em.cidade)}</div>
            <div class="ir"><div class="ir-l">Estado</div>${fv(em.estado)}</div>
        </div>
    </div>`;}

function tabFin(fin,u){
    const d=parseFloat(fin.despesas_mes||0),rc=parseFloat(fin.receitas_mes||0),s=rc-d;
    return `
    <div class="ib">
        <div class="ib-title"><span class="material-symbols-outlined">calendar_month</span>Mês Atual</div>
        <div class="ir-grid">
            <div class="ir"><div class="ir-l">Receitas extras</div><div class="ir-v" style="color:var(--ok)">${R(rc)}</div></div>
            <div class="ir"><div class="ir-l">Despesas</div><div class="ir-v" style="color:var(--err)">${R(d)}</div></div>
            <div class="ir s2"><div class="ir-l">Saldo do mês</div><div class="ir-v" style="color:${s>=0?'var(--ok)':'var(--err)'};font-size:1rem">${R(s)}</div></div>
        </div>
    </div>`;}

function tabAcessos(lg){
    if(!lg||!lg.length) return `<div class="empty-state"><span class="material-symbols-outlined">history</span><p>Nenhum acesso registrado.</p></div>`;
    return `<div style="font-size:.72rem;color:var(--faint);margin-bottom:.65rem;font-weight:600">Últimos ${lg.length} acessos</div>
    <div class="login-list">${lg.map((l,i)=>`
        <div class="login-item ${l.status==='falha'?'fail':''} ${i===0?'first':''}">
            <div class="login-ico"><span class="material-symbols-outlined">${l.status==='falha'?'block':'login'}</span></div>
            <div class="login-info">
                <div class="login-date">${esc(l.data_fmt)} às ${esc(l.hora_fmt)}</div>
                <div class="login-device">${esc(l.dispositivo||'Desconhecido')}</div>
                <div class="login-ip">${esc(l.ip||'—')}</div>
            </div>
            <span class="login-tag ${l.status==='falha'?'fail':'ok'}">${l.status==='falha'?'Falha':'OK'}</span>
        </div>`).join('')}</div>`;}

/* ══════════════════════════════════════════
   CRIAR USUÁRIO (multistep)
══════════════════════════════════════════ */
const STEPS=[{title:'Credenciais',sub:'Defina usuário e senha'},{title:'Permissão',sub:'Escolha o nível de acesso'},{title:'Confirmar',sub:'Revise e crie o usuário'}];
let curStep=1, roleVal='user';
function buildDots(){const c=$('stepDots');c.innerHTML='';STEPS.forEach((_,i)=>{const d=document.createElement('div');d.className='sdot'+(i===0?' on':'');d.style.width=(i===0?24:9)+'px';c.appendChild(d);});}
function gotoStep(n){
    document.querySelectorAll('.step-p').forEach(x=>x.classList.remove('on'));
    curStep=n;$('us'+n).classList.add('on');
    const s=STEPS[n-1];$('stepTitle').textContent=s.title;$('stepSub').textContent=s.sub;$('stepLbl').textContent=`Etapa ${n} de ${STEPS.length}`;
    $('stepDots').querySelectorAll('.sdot').forEach((d,i)=>{d.className='sdot'+(i<n-1?' done':i===n-1?' on':'');d.style.width=(i===n-1?24:9)+'px';});
    $('btnBack').style.display=n>1?'flex':'none';
    $('btnNext').innerHTML=n===STEPS.length?'<span class="material-symbols-outlined">person_add</span>Criar':'Próximo<span class="material-symbols-outlined">arrow_forward</span>';
    if(n===3) buildReview();
}
function buildReview(){const isAdm=roleVal==='admin';$('uRevW').innerHTML=`<div style="text-align:center;margin-bottom:.9rem"><div style="width:56px;height:56px;border-radius:50%;background:${isAdm?'linear-gradient(135deg,var(--violet),#a855f7)':'linear-gradient(135deg,var(--p),#7ca0ff)'};color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:1.2rem">${($('uNome').value.slice(0,2).toUpperCase())||'?'}</div></div><div class="rev-card"><div class="rev-row"><span class="rrl">Usuário</span><span class="rrv">${esc($('uNome').value||'—')}</span></div><div class="rev-row"><span class="rrl">Senha</span><span class="rrv">••••••${$('uSenha').value.length>0?' ('+$('uSenha').value.length+' chars)':''}</span></div></div><div class="rev-card"><div class="rev-row"><span class="rrl">Nível</span><span class="rrv" style="color:${isAdm?'var(--violet)':'var(--p)'}">${isAdm?' Administrador':' Usuário Regular'}</span></div></div>`;}
function validateUsername(){const v=$('uNome').value.trim(),ok=v.length>=3&&/^[a-zA-Z0-9._-]+$/.test(v);$('uNomeErr').textContent=v.length<3?'Mínimo 3 caracteres':'Apenas letras, números, . _ -';$('uNomeErr').style.display=ok?'none':'block';$('uNome').classList.toggle('invalid',!ok&&v.length>0);return ok;}
function checkMatch(){const ok=$('uSenha').value===$('uSenha2').value;$('matchErr').style.display=ok||!$('uSenha2').value?'none':'block';$('uSenha2').classList.toggle('invalid',!ok&&!!$('uSenha2').value);return ok;}
function pwStrength(val){let s=0;if(val.length>=6)s++;if(val.length>=10)s++;if(/[A-Z]/.test(val))s++;if(/[0-9]/.test(val))s++;if(/[^a-zA-Z0-9]/.test(val))s++;return s;}
function applyStr(fill,lbl,val){const s=pwStrength(val),pct=[0,20,40,65,85,100][s];const cols=['#e2e8f0','var(--err)','var(--warn)','var(--warn)','var(--ok)','var(--ok)'];const lbls=['Digite uma senha','Muito fraca','Fraca','Razoável','Boa','Forte'];fill.style.width=pct+'%';fill.style.background=cols[s];lbl.textContent=lbls[s];lbl.style.color=cols[s];}
function updateStrength(){applyStr($('sFill'),$('sLbl'),$('uSenha').value)}
function updPwStr(){applyStr($('pwSFill'),$('pwSLbl'),$('pwNew').value)}
function togglePw(id,btn){const i=$(id),ico=btn.querySelector('.material-symbols-outlined');i.type=i.type==='password'?'text':'password';ico.textContent=i.type==='password'?'visibility':'visibility_off';}
function selRole(v,el){roleVal=v;$('uRole').value=v;document.querySelectorAll('#us2 .role-opt').forEach(x=>x.classList.remove('on'));el.classList.add('on');}
function selRoleM(v,el){$('roleNew').value=v;document.querySelectorAll('#roleOpts .role-opt').forEach(x=>x.classList.remove('on'));el.classList.add('on');}

$('btnNovoUser').addEventListener('click',()=>{
    $('uNome').value='';$('uSenha').value='';$('uSenha2').value='';
    $('uNomeErr').style.display='none';$('matchErr').style.display='none';$('uNome').classList.remove('invalid');$('uSenha2').classList.remove('invalid');
    $('sFill').style.width='0';$('sLbl').textContent='Digite uma senha';
    roleVal='user';$('uRole').value='user';
    document.querySelectorAll('#us2 .role-opt').forEach(x=>x.classList.remove('on'));
    document.querySelector('#us2 .role-opt[data-role="user"]').classList.add('on');
    buildDots();curStep=1;gotoStep(1);
    $('modal').style.display='flex';
});
$('mclose').addEventListener('click',()=>$('modal').style.display='none');
$('modal').addEventListener('click',e=>{if(e.target===$('modal'))$('modal').style.display='none'});
$('btnBack').addEventListener('click',()=>gotoStep(curStep-1));
$('btnNext').addEventListener('click',async()=>{
    if(curStep<STEPS.length){
        if(curStep===1){if(!validateUsername())return;if($('uSenha').value.length<6)return toast('Senha deve ter ao menos 6 caracteres','err');if(!checkMatch())return toast('As senhas não coincidem','err');}
        gotoStep(curStep+1);
    } else {
        const btn=$('btnNext');btn.disabled=true;btn.innerHTML='<span class="material-symbols-outlined">hourglass_empty</span>Criando…';
        const fd=new FormData();fd.append('action','create');fd.append('username',$('uNome').value.trim());fd.append('password',$('uSenha').value);fd.append('role',$('uRole').value);fd.append('csrf_token',CSRF);
        try{const r=await fetch('admin_usuarios_action.php',{method:'POST',body:fd,credentials:'same-origin'});const j=await r.json();if(j.ok){if(window.UplarTracker){window.UplarTracker.trackEvent('create_user',{target_user_id:j.id,role:$('uRole').value},'admin');}$('modal').style.display='none';toast('Usuário criado!');setTimeout(()=>location.reload(),1200);}else toast(j.error||'Erro','err');}catch{toast('Erro de conexão','err')}
        btn.disabled=false;btn.innerHTML='<span class="material-symbols-outlined">person_add</span>Criar';
    }
});

/* Senha */
function openPw(id,nome){$('pwUid').value=id;$('pwTarget').textContent='Alterando senha de: '+nome;$('pwNew').value='';$('pwNew2').value='';$('pwSFill').style.width='0';$('pwSLbl').textContent='Digite uma senha';$('pwMatchErr').style.display='none';$('modalPw').style.display='flex';}
$('pwClose').addEventListener('click',()=>$('modalPw').style.display='none');
$('modalPw').addEventListener('click',e=>{if(e.target===$('modalPw'))$('modalPw').style.display='none'});
$('btnPwSave').addEventListener('click',async()=>{
    const pw=$('pwNew').value,pw2=$('pwNew2').value;
    if(pw.length<6)return toast('Senha deve ter ao menos 6 caracteres','err');
    if(pw!==pw2){$('pwMatchErr').style.display='block';return toast('Senhas não coincidem','err')}
    $('pwMatchErr').style.display='none';
    const btn=$('btnPwSave');btn.disabled=true;btn.innerHTML='<span class="material-symbols-outlined">hourglass_empty</span>Salvando…';
    const fd=new FormData();fd.append('action','change_password');fd.append('user_id',$('pwUid').value);fd.append('password',pw);fd.append('csrf_token',CSRF);
    try{const r=await fetch('admin_usuarios_action.php',{method:'POST',body:fd,credentials:'same-origin'});const j=await r.json();if(j.ok){if(window.UplarTracker){window.UplarTracker.trackEvent('change_password',{target_user_id:Number($('pwUid').value)},'admin');}$('modalPw').style.display='none';toast('Senha alterada!')}else toast(j.error||'Erro','err');}catch{toast('Erro de conexão','err')}
    btn.disabled=false;btn.innerHTML='<span class="material-symbols-outlined">save</span>Alterar Senha';
});

async function resetPasscode(id,nome){
    if(!confirm(`Resetar o Pass Code de ${nome}? No próximo acesso, essa pessoa deverá configurar um novo código.`)) return;
    const fd=new FormData();fd.append('action','reset_passcode');fd.append('user_id',id);fd.append('csrf_token',CSRF);
    try{const r=await fetch('admin_usuarios_action.php',{method:'POST',body:fd,credentials:'same-origin'});const j=await r.json();if(j.ok){if(window.UplarTracker){window.UplarTracker.trackEvent('reset_passcode',{target_user_id:Number(id)},'admin');}toast(j.message||'Pass Code resetado.')}else toast(j.error||'Erro','err');}catch{toast('Erro de conexão','err')}
}

/* Role */
function openRole(id,nome,cur){$('roleUid').value=id;$('roleNew').value=cur;$('roleTarget').textContent='Alterando permissão de: '+nome;document.querySelectorAll('#roleOpts .role-opt').forEach(x=>x.classList.remove('on'));const t=document.querySelector(`#roleOpts .role-opt[data-role="${cur}"]`);if(t)t.classList.add('on');$('modalRole').style.display='flex';}
$('roleClose').addEventListener('click',()=>$('modalRole').style.display='none');
$('modalRole').addEventListener('click',e=>{if(e.target===$('modalRole'))$('modalRole').style.display='none'});
$('btnRoleSave').addEventListener('click',async()=>{
    const btn=$('btnRoleSave');btn.disabled=true;btn.innerHTML='<span class="material-symbols-outlined">hourglass_empty</span>Salvando…';
    const fd=new FormData();fd.append('action','change_role');fd.append('user_id',$('roleUid').value);fd.append('role',$('roleNew').value);fd.append('csrf_token',CSRF);
    try{const r=await fetch('admin_usuarios_action.php',{method:'POST',body:fd,credentials:'same-origin'});const j=await r.json();if(j.ok){if(window.UplarTracker){window.UplarTracker.trackEvent('change_role',{target_user_id:Number($('roleUid').value),new_role:$('roleNew').value},'admin');}$('modalRole').style.display='none';toast('Permissão alterada!');setTimeout(()=>location.reload(),1200)}else toast(j.error||'Erro','err');}catch{toast('Erro de conexão','err')}
    btn.disabled=false;btn.innerHTML='<span class="material-symbols-outlined">save</span>Salvar Permissão';
});

/* Excluir */
function openDel(id,nome){$('delUid').value=id;$('delSub').textContent=`O usuário "${nome}" e todos os seus dados serão excluídos permanentemente.`;$('modalDel').style.display='flex';}
$('delCancel').addEventListener('click',()=>$('modalDel').style.display='none');
$('modalDel').addEventListener('click',e=>{if(e.target===$('modalDel'))$('modalDel').style.display='none'});
$('delConfirm').addEventListener('click',async()=>{
    const btn=$('delConfirm');btn.disabled=true;btn.textContent='Excluindo…';
    const fd=new FormData();fd.append('action','delete');fd.append('user_id',$('delUid').value);fd.append('csrf_token',CSRF);
    try{const r=await fetch('admin_usuarios_action.php',{method:'POST',body:fd,credentials:'same-origin'});const j=await r.json();if(j.ok){if(window.UplarTracker){window.UplarTracker.trackEvent('delete_user',{target_user_id:Number($('delUid').value)},'admin');}$('modalDel').style.display='none';toast('Usuário excluído');setTimeout(()=>location.reload(),1200)}else toast(j.error||'Erro','err');}catch{toast('Erro de conexão','err')}
    btn.disabled=false;btn.textContent='Excluir';
});
</script>
</body>
</html>
