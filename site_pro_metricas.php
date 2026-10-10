<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/subscription.php';
require_once __DIR__ . '/includes/tracking_runtime.php';
send_security_headers();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = get_pdo();
$user_id = (int)$_SESSION['user_id'];
if (!has_pro_access($user_id, $pdo)) {
    header('Location: planos.php');
    exit;
}
$username = (string)($_SESSION['username'] ?? 'Usuário');
$stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
$is_admin = ($user['role'] ?? '') === 'admin';
$pend_count = 0;
try {
    $pending = $pdo->prepare("SELECT COUNT(*) FROM vitrine_pedidos WHERE user_id = ? AND status = 'pendente'");
    $pending->execute([$user_id]);
    $pend_count = (int)$pending->fetchColumn();
} catch (Throwable $e) {
}
get_csrf_token();
?>
<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Métricas do Site PRO — Uplar</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
<link rel="stylesheet" href="assets/analytics.css?v=20260713e"><link rel="stylesheet" href="assets/app-theme.css?v=20260723"><script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script><script src="assets/app-theme.js?v=20260723"></script></head>
<body><div class="app"><div class="overlay" id="ov"></div><?php require __DIR__ . '/includes/sidebar.php'; ?>
<main class="main"><div class="top anim"><div class="top-l"><button class="ham" id="ham" aria-label="Abrir menu"><span class="material-symbols-outlined">menu</span></button><div><h1 class="greeting">Métricas do Site PRO</h1><p class="sub">Entenda como os visitantes descobrem produtos e avançam até o pedido</p></div></div><div class="range" aria-label="Período"><button class="range-btn" data-days="7">7 dias</button><button class="range-btn on" data-days="30">30 dias</button><button class="range-btn" data-days="90">90 dias</button></div></div>

<div class="metrics-notice anim d2" id="dataNotice"><span class="material-symbols-outlined">info</span><span>Carregando informações do seu Site PRO…</span></div>

<section class="kpis kpis-simple anim d2">
<div class="kpi"><div class="kpi-head">Visitas ao Site PRO<span class="kpi-icon"><span class="material-symbols-outlined">storefront</span></span></div><div class="kpi-value" id="views">--</div><div class="kpi-meta" id="viewsDelta">uma visita por sessão</div></div>
<div class="kpi"><div class="kpi-head">Pessoas alcançadas<span class="kpi-icon"><span class="material-symbols-outlined">groups</span></span></div><div class="kpi-value" id="visitors">--</div><div class="kpi-meta" id="visitorsDelta">visitantes diferentes</div></div>
<div class="kpi"><div class="kpi-head">Pedidos recebidos<span class="kpi-icon"><span class="material-symbols-outlined">shopping_bag</span></span></div><div class="kpi-value" id="orders">--</div><div class="kpi-meta" id="ordersDelta">pelo Site PRO</div></div>
<div class="kpi kpi-revenue"><div class="kpi-head">Valor vendido<span class="kpi-icon"><span class="material-symbols-outlined">payments</span></span></div><div class="kpi-value" id="revenue">--</div><div class="kpi-meta">somente pedidos confirmados</div></div>
</section>

<section class="grid-main visual-grid anim d3"><article class="card line-card"><div class="card-head"><div><h2 class="card-title">Visitas ao longo do tempo</h2><p class="card-sub">Veja em quais dias seu Site PRO recebeu mais atenção</p></div><div class="chart-legend"><span><i class="legend-dot visits"></i>Visitas</span><span><i class="legend-dot products"></i>Produtos vistos</span></div></div><div class="line-chart" id="dailyChart"><div class="state">Carregando gráfico…</div></div></article><article class="card"><div class="card-head"><div><h2 class="card-title">Resumo fácil</h2><p class="card-sub">O que aconteceu a cada 100 visitas</p></div></div><div class="simple-rates" id="simpleRates"><div class="state">Carregando…</div></div></article></section>

<section class="card journey-card anim d3"><div class="card-head"><div><h2 class="card-title">Caminho até a compra</h2><p class="card-sub">Quantas pessoas avançaram em cada etapa</p></div></div><div class="journey" id="journey"><div class="state">Carregando jornada…</div></div></section>

<section class="card products-card anim d3"><div class="card-head"><div><h2 class="card-title">Produtos que mais chamaram atenção</h2><p class="card-sub">O ranking considera quantas vezes cada produto apareceu na tela</p></div></div><div class="product-ranking" id="topProducts"><div class="state">Carregando produtos…</div></div></section>

<section class="grid-equal anim d3"><article class="card"><div class="card-head"><div><h2 class="card-title">De onde vieram as visitas</h2><p class="card-sub">Acesso direto, redes sociais e outros sites</p></div></div><div class="small-echart" id="sources"><div class="state">Carregando…</div></div></article><article class="card"><div class="card-head"><div><h2 class="card-title">Como acessaram</h2><p class="card-sub">Celular, computador ou tablet</p></div></div><div class="small-echart" id="devices"><div class="state">Carregando…</div></div></article></section>

<details class="card more-metrics anim d3"><summary><span><strong>Ver mais informações</strong><small>WhatsApp, carrinho, tempo no Site PRO e horários</small></span><span class="material-symbols-outlined">expand_more</span></summary><div class="health-grid more-grid"><div class="health"><div class="health-v" id="whatsapp">--</div><div class="health-l">cliques no WhatsApp</div></div><div class="health"><div class="health-v" id="cartViews">--</div><div class="health-l">aberturas do carrinho</div></div><div class="health"><div class="health-v" id="duration">--</div><div class="health-l">tempo médio no Site PRO</div></div><div class="health"><div class="health-v" id="scroll">--</div><div class="health-l">página visualizada</div></div><div class="health"><div class="health-v" id="loadTime">--</div><div class="health-l">tempo de carregamento</div></div><div class="health"><div class="health-v" id="peakHour">--</div><div class="health-l">horário mais movimentado</div></div></div></details>
</main></div>
<script>
const q=s=>document.querySelector(s),nf=v=>Number(v||0).toLocaleString('pt-BR'),money=v=>Number(v||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'}),esc=v=>String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
function delta(id,pair,available){const el=q(id);if(!available){el.textContent='Coleta iniciada recentemente';return}const [now,prev]=pair||[0,0];if(!prev){el.textContent='Sem visitas no período anterior';return}const pct=((now-prev)/prev)*100;el.innerHTML=`<span class="delta ${pct>=0?'up':'down'}">${pct>=0?'↑':'↓'} ${Math.abs(pct).toFixed(1)}%</span> em relação ao período anterior`}
function filledDays(rows,days){const map=new Map(rows.map(x=>[x.day,x])),out=[];for(let i=days-1;i>=0;i--){const date=new Date();date.setHours(12,0,0,0);date.setDate(date.getDate()-i);const key=date.toISOString().slice(0,10);out.push(map.get(key)||{day:key,views:0,product_views:0})}return out}
function chartColors(){const styles=getComputedStyle(document.documentElement);return{ink:styles.getPropertyValue('--ink').trim()||'#0f1c2e',muted:styles.getPropertyValue('--muted').trim()||'#5a6a7a',faint:styles.getPropertyValue('--faint').trim()||'#94a3b8',border:styles.getPropertyValue('--border').trim()||'#e4eaf2',card:styles.getPropertyValue('--card').trim()||'#ffffff'}}
let storefrontChart=null,sourceChart=null,deviceChart=null,lastChartPayload=null,lastDashboardPayload=null;
function lineChart(rows,days){
    lastChartPayload={rows,days};
    const el=q('#dailyChart');
    if(typeof echarts==='undefined'){
        el.innerHTML='<div class="state error-state">Não foi possível carregar o gráfico.</div>';
        return;
    }
    const data=filledDays(rows,days);
    if(!storefrontChart){
        el.innerHTML='';
        storefrontChart=echarts.init(el,null,{renderer:'canvas'});
        if('ResizeObserver' in window){
            storefrontChartObserver=new ResizeObserver(()=>storefrontChart?.resize());
            storefrontChartObserver.observe(el);
        }
    }
    const {ink,faint,border,card}=chartColors();
    const labels=data.map(x=>new Date(x.day+'T12:00:00').toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit'}));
    storefrontChart.setOption({
        backgroundColor:'transparent',
        animationDuration:500,
        grid:{left:10,right:16,top:18,bottom:20,containLabel:true},
        tooltip:{trigger:'axis',backgroundColor:card,borderColor:border,borderWidth:1,padding:[9,11],textStyle:{color:ink,fontFamily:'Inter',fontSize:12},axisPointer:{type:'line',lineStyle:{color:'rgba(59,111,245,.45)',type:'dashed'}},formatter:params=>`<strong>${esc(params[0]?.axisValue||'')}</strong><br>${params.map(p=>`${p.marker}${esc(p.seriesName)}: <strong>${nf(p.value)}</strong>`).join('<br>')}`},
        xAxis:{type:'category',boundaryGap:false,data:labels,axisTick:{show:false},axisLine:{lineStyle:{color:border}},axisLabel:{color:faint,fontFamily:'Inter',fontSize:10,hideOverlap:true}},
        yAxis:{type:'value',min:0,minInterval:1,splitNumber:4,axisLabel:{color:faint,fontFamily:'Inter',fontSize:10},splitLine:{lineStyle:{color:border,type:'dashed',opacity:.7}}},
        series:[
            {name:'Visitas',type:'line',smooth:.35,symbol:'circle',symbolSize:6,showSymbol:days<=30,data:data.map(x=>+x.views||0),lineStyle:{width:3,color:'#3b6ff5'},itemStyle:{color:card,borderColor:'#3b6ff5',borderWidth:2},areaStyle:{color:new echarts.graphic.LinearGradient(0,0,0,1,[{offset:0,color:'rgba(59,111,245,.24)'},{offset:1,color:'rgba(59,111,245,0)'}])},emphasis:{focus:'series'}},
            {name:'Produtos vistos',type:'line',smooth:.35,symbol:'circle',symbolSize:5,showSymbol:days<=30,data:data.map(x=>+x.product_views||0),lineStyle:{width:2.5,color:'#7c3aed'},itemStyle:{color:card,borderColor:'#7c3aed',borderWidth:2},emphasis:{focus:'series'}}
        ]
    },true);
    requestAnimationFrame(()=>storefrontChart?.resize());
}
function renderJourney(rows){const icons=['storefront','visibility','add_shopping_cart','edit_note','shopping_bag'];q('#journey').innerHTML=rows.map((x,i)=>`<div class="journey-stage"><div class="journey-icon"><span class="material-symbols-outlined">${icons[i]}</span></div><strong>${nf(x.value)}</strong><span>${esc(x.label)}</span></div>${i<rows.length-1?'<span class="material-symbols-outlined journey-arrow">arrow_forward</span>':''}`).join('')}
function renderProducts(rows){const max=Math.max(1,...rows.map(x=>+x.views||0));q('#topProducts').innerHTML=rows.length?rows.slice(0,6).map((x,i)=>`<div class="rank-product"><div class="rank-number">${i+1}</div><div class="rank-image"><span class="material-symbols-outlined">inventory_2</span>${x.imagem?`<img src="${esc(x.imagem)}" alt="${esc(x.nome)}" loading="lazy">`:''}</div><div class="rank-main"><div class="rank-name">${esc(x.nome)}</div><div class="rank-track"><div style="width:${(+x.views/max)*100}%"></div></div><div class="rank-sub">${nf(x.visitors)} pessoas interessadas · ${nf(x.cart_adds)} adicionaram ao carrinho</div></div><strong>${nf(x.views)}<small> visualizações</small></strong></div>`).join(''):'<div class="state">Quando alguém abrir um produto, ele aparecerá aqui.</div>';q('#topProducts').querySelectorAll('.rank-image img').forEach(img=>img.addEventListener('error',()=>img.remove(),{once:true}))}
function renderSources(rows){const el=q('#sources');if(typeof echarts==='undefined'){el.innerHTML='<div class="state error-state">Gráfico indisponível.</div>';return}if(!rows.length){sourceChart?.dispose();sourceChart=null;el.innerHTML='<div class="state">Ainda não há visitas suficientes.</div>';return}if(!sourceChart){el.innerHTML='';sourceChart=echarts.init(el,null,{renderer:'canvas'});if('ResizeObserver' in window)new ResizeObserver(()=>sourceChart?.resize()).observe(el)}const {ink,faint,border,card}=chartColors();sourceChart.setOption({grid:{left:6,right:30,top:4,bottom:4,containLabel:true},tooltip:{trigger:'axis',axisPointer:{type:'shadow'},backgroundColor:card,borderColor:border,textStyle:{color:ink,fontFamily:'Inter'}},xAxis:{type:'value',minInterval:1,axisLabel:{color:faint},splitLine:{lineStyle:{color:border,type:'dashed'}}},yAxis:{type:'category',inverse:true,data:rows.map(x=>x.source),axisTick:{show:false},axisLine:{show:false},axisLabel:{color:faint,width:110,overflow:'truncate'}},series:[{name:'Visitas',type:'bar',data:rows.map(x=>+x.total||0),barMaxWidth:14,itemStyle:{color:'#3b6ff5',borderRadius:[0,5,5,0]},label:{show:true,position:'right',color:ink,fontWeight:700}}]},true);requestAnimationFrame(()=>sourceChart?.resize())}
function renderDevices(rows){const el=q('#devices'),names={mobile:'Celular',desktop:'Computador',tablet:'Tablet'};if(typeof echarts==='undefined'){el.innerHTML='<div class="state error-state">Gráfico indisponível.</div>';return}if(!rows.length){deviceChart?.dispose();deviceChart=null;el.innerHTML='<div class="state">Ainda não há visitas suficientes.</div>';return}if(!deviceChart){el.innerHTML='';deviceChart=echarts.init(el,null,{renderer:'canvas'});if('ResizeObserver' in window)new ResizeObserver(()=>deviceChart?.resize()).observe(el)}const {ink,muted,border,card}=chartColors();deviceChart.setOption({color:['#3b6ff5','#7c3aed','#10b981','#94a3b8'],tooltip:{trigger:'item',formatter:'{b}: <strong>{c}</strong> visitas ({d}%)',backgroundColor:card,borderColor:border,textStyle:{color:ink,fontFamily:'Inter'}},legend:{orient:'vertical',right:8,top:'middle',textStyle:{color:muted,fontFamily:'Inter'},icon:'circle'},series:[{name:'Dispositivos',type:'pie',radius:['48%','72%'],center:['34%','50%'],avoidLabelOverlap:true,label:{show:false},emphasis:{label:{show:true,position:'center',fontSize:16,fontWeight:700,color:ink}},data:rows.map(x=>({name:names[x.device_type]||x.device_type,value:+x.total||0}))}]},true);requestAnimationFrame(()=>deviceChart?.resize())}
function render(d){lastDashboardPayload=d;const s=d.summary||{},hasData=s.store_views>0;q('#dataNotice').classList.toggle('is-empty',!hasData);q('#dataNotice').innerHTML=hasData?'<span class="material-symbols-outlined">verified</span><span>Os números abaixo contam sessões únicas e ignoram robôs e recarregamentos.</span>':'<span class="material-symbols-outlined">hourglass_top</span><span>A coleta está ativa. As informações aparecerão assim que seu Site PRO receber novas visitas.</span>';q('#views').textContent=nf(s.store_views);q('#visitors').textContent=nf(s.unique_visitors);q('#orders').textContent=nf(s.orders);q('#revenue').textContent=money(s.revenue);delta('#viewsDelta',d.comparison?.views,d.comparison_available);delta('#visitorsDelta',d.comparison?.visitors,d.comparison_available);delta('#ordersDelta',d.comparison?.orders,d.comparison_available);q('#simpleRates').innerHTML=[['visibility','Viram algum produto',s.product_view_rate],['add_shopping_cart','Colocaram no carrinho',s.cart_rate],['shopping_bag','Finalizaram uma compra',s.conversion_rate]].map(x=>`<div class="simple-rate"><span class="material-symbols-outlined">${x[0]}</span><div><strong>${x[2]||0}%</strong><small>${x[1]}</small></div></div>`).join('');lineChart(d.daily||[],d.period_days||30);renderJourney(d.funnel||[]);renderProducts(d.top_products||[]);renderSources(d.sources||[]);renderDevices(d.devices||[]);q('#whatsapp').textContent=nf(s.whatsapp_clicks);q('#cartViews').textContent=nf(s.cart_views);q('#duration').textContent=s.avg_session_ms?`${Math.round(s.avg_session_ms/1000)}s`:'--';q('#scroll').textContent=s.avg_scroll_depth?`${s.avg_scroll_depth}%`:'--';q('#loadTime').textContent=s.avg_load_ms?`${(s.avg_load_ms/1000).toFixed(1)}s`:'--';const peak=(d.peak_hours||[]).reduce((best,x)=>(+x.total||0)>(+best.total||0)?x:best,{total:0});q('#peakHour').textContent=peak.total?`${String(peak.hour).padStart(2,'0')}h`:'--'}
async function load(days){document.querySelectorAll('.range-btn').forEach(b=>b.classList.toggle('on',+b.dataset.days===days));try{const r=await fetch(`site_pro_metricas_api.php?days=${days}`,{credentials:'same-origin'}),j=await r.json();if(!j.ok)throw new Error(j.error);render(j.data)}catch(e){document.querySelectorAll('.state').forEach(el=>{el.textContent='Falha ao carregar as métricas.';el.classList.add('error-state')})}}
document.querySelectorAll('.range-btn').forEach(b=>b.addEventListener('click',()=>load(+b.dataset.days)));const sb=q('#sb'),ov=q('#ov');q('#ham').addEventListener('click',()=>{sb.classList.add('open');ov.classList.add('on')});ov.addEventListener('click',()=>{sb.classList.remove('open');ov.classList.remove('on')});load(30);
window.addEventListener('resize',()=>{storefrontChart?.resize();sourceChart?.resize();deviceChart?.resize()});
window.addEventListener('uplar:themechange',()=>{if(lastDashboardPayload){lineChart(lastDashboardPayload.daily||[],lastDashboardPayload.period_days||30);renderSources(lastDashboardPayload.sources||[]);renderDevices(lastDashboardPayload.devices||[])}});
</script></body></html>
