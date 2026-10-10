<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require_once __DIR__ . '/includes/tracking_runtime.php';
send_security_headers();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = get_pdo();
$userId = (int)$_SESSION['user_id'];
$username = (string)($_SESSION['username'] ?? 'Administrador');
$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$is_admin = ($user['role'] ?? '') === 'admin';
if (!$is_admin) {
    http_response_code(403);
    header('Location: estoque.php');
    exit;
}
get_csrf_token();
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tracking — Uplar</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
<link rel="stylesheet" href="assets/analytics.css?v=20260713"><link rel="stylesheet" href="assets/app-theme.css?v=20260723"><script src="assets/app-theme.js?v=20260723"></script></head>
<body><div class="app"><div class="overlay" id="ov"></div><?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main"><div class="top anim"><div class="top-l"><button class="ham" id="ham" aria-label="Abrir menu"><span class="material-symbols-outlined">menu</span></button><div><h1 class="greeting">Tracking e observabilidade</h1><p class="sub">Saúde técnica, uso da plataforma e sinais de risco</p></div></div><div class="range" aria-label="Período"><button class="range-btn on" data-days="7">7 dias</button><button class="range-btn" data-days="30">30 dias</button></div></div>
<section class="kpis anim d2"><div class="kpi"><div class="kpi-head">Eventos<span class="kpi-icon"><span class="material-symbols-outlined">data_usage</span></span></div><div class="kpi-value" id="totalEvents">--</div><div class="kpi-meta">ações registradas no período</div></div><div class="kpi"><div class="kpi-head">Usuários ativos<span class="kpi-icon"><span class="material-symbols-outlined">person_check</span></span></div><div class="kpi-value" id="activeUsers">--</div><div class="kpi-meta">usuários com atividade</div></div><div class="kpi"><div class="kpi-head">Sessões ativas<span class="kpi-icon"><span class="material-symbols-outlined">devices</span></span></div><div class="kpi-value" id="activeSessions">--</div><div class="kpi-meta">atividade nos últimos minutos</div></div><div class="kpi"><div class="kpi-head">Erros<span class="kpi-icon"><span class="material-symbols-outlined">error</span></span></div><div class="kpi-value" id="errorTotal">--</div><div class="kpi-meta">ocorrências consolidadas</div></div></section>
<section class="grid-main anim d3"><article class="card"><div class="card-head"><div><h2 class="card-title">Eventos mais usados</h2><p class="card-sub">Ações com maior volume na plataforma</p></div></div><div class="list" id="topEvents"><div class="state">Carregando…</div></div></article><article class="card"><div class="card-head"><div><h2 class="card-title">Erros por origem</h2><p class="card-sub">Ocorrências agrupadas por severidade</p></div></div><div class="list" id="errors"><div class="state">Carregando…</div></div></article></section>
<section class="grid-equal anim d3"><article class="card"><div class="card-head"><div><h2 class="card-title">Engajamento por usuário</h2><p class="card-sub">Score calculado pelas ações realizadas</p></div></div><div class="list" id="engagement"><div class="state">Carregando…</div></div></article><article class="card"><div class="card-head"><div><h2 class="card-title">Comportamento suspeito</h2><p class="card-sub">Contas acessadas por múltiplos IPs</p></div></div><div class="list" id="suspicious"><div class="state">Carregando…</div></div></article></section></main></div>
<script>
const q=s=>document.querySelector(s),esc=v=>String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
function rows(id,data,render){q(id).innerHTML=data?.length?data.map(render).join(''):'<div class="state">Sem dados no período.</div>'}
async function load(days){document.querySelectorAll('.range-btn').forEach(b=>b.classList.toggle('on',+b.dataset.days===days));try{const r=await fetch(`track/dashboard.php?days=${days}`,{credentials:'same-origin'}),j=await r.json();if(!j.ok)throw new Error(j.error);const s=j.summary||{};q('#totalEvents').textContent=Number(s.total_events||0).toLocaleString('pt-BR');q('#activeUsers').textContent=Number(s.active_users||0).toLocaleString('pt-BR');q('#activeSessions').textContent=Number(s.active_sessions||0).toLocaleString('pt-BR');q('#errorTotal').textContent=(s.error_summary||[]).reduce((a,x)=>a+Number(x.total||0),0).toLocaleString('pt-BR');rows('#topEvents',s.top_events,x=>`<div class="list-row"><div class="list-main"><div class="list-title">${esc(x.event_name)}</div><div class="list-sub">Últimos ${s.period_days||days} dias</div></div><div class="list-value">${Number(x.total||0).toLocaleString('pt-BR')}</div></div>`);rows('#errors',s.error_summary,x=>`<div class="list-row"><div class="list-main"><div class="list-title">${esc(x.source)} · ${esc(x.severity)}</div><div class="list-sub">Ocorrências consolidadas</div></div><div class="list-value">${Number(x.total||0).toLocaleString('pt-BR')}</div></div>`);rows('#engagement',s.engagement_rank,x=>`<div class="list-row"><div class="list-main"><div class="list-title">${esc(x.username)}</div><div class="list-sub">${Number(x.total_eventos||0)} eventos</div></div><div class="list-value">${Number(x.score||0)} pts</div></div>`);rows('#suspicious',s.suspicious_users,x=>`<div class="list-row"><div class="list-main"><div class="list-title">${esc(x.username)}</div><div class="list-sub">${Number(x.total_sessoes||0)} sessões</div></div><div class="list-value">${Number(x.total_ips||0)} IPs</div></div>`)}catch(e){document.querySelectorAll('.list').forEach(el=>el.innerHTML='<div class="state error-state">Falha ao carregar os dados.</div>')}}
document.querySelectorAll('.range-btn').forEach(b=>b.addEventListener('click',()=>load(+b.dataset.days)));const sb=q('#sb'),ov=q('#ov');q('#ham').addEventListener('click',()=>{sb.classList.add('open');ov.classList.add('on')});ov.addEventListener('click',()=>{sb.classList.remove('open');ov.classList.remove('on')});load(7);
</script></body></html>
