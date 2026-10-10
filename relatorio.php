<?php
session_start();
require 'db.php';
require 'helpers.php';
require_once __DIR__ . '/includes/commercial_origin.php';
require_once __DIR__ . '/includes/tracking_runtime.php';
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php"); exit;
}
get_csrf_token();
$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$pdo  = get_pdo();
commercial_origin_ensure_schema($pdo);
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user     = $stmt->fetch();
$is_admin = ($user && $user['role'] === 'admin');
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
require 'subscription.php';
$sub = check_and_block($user_id, $pdo);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Relatórios — Uplar</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"/>
<script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
<style>
/* ══ TOKENS E VARIÁVEIS ══════════════════════════════════════ */
:root {
    --p:        #3b6ff5;
    --p-dark:   #2a55d9;
    --p-dim:    rgba(59,111,245,.08);
    --p-glow:   rgba(59,111,245,.20);
    --ink:      #0f1c2e;
    --muted:    #5a6a7a;
    --faint:    #94a3b8;
    --bg:       #f4f6fb;
    --card:     #ffffff;
    --border:   #e4eaf2;
    --ok:       #10b981;
    --ok-dim:   rgba(16,185,129,.09);
    --warn:     #f59e0b;
    --warn-dim: rgba(245,158,11,.09);
    --err:      #ef4444;
    --err-dim:  rgba(239,68,68,.09);
    --violet:   #7c3aed;
    --violet-dim: rgba(124,58,237,.09);
    --cyan:     #0891b2;
    --cyan-dim: rgba(8,145,178,.09);
    --gold:     #b7791f;
    --gold-dim: rgba(183,121,31,.10);
    --sh:       0 1px 4px rgba(15,28,46,.06), 0 4px 16px rgba(15,28,46,.04);
    --sh-lift:  0 4px 24px rgba(59,111,245,.14);
    --r:        12px;
}

/* ══ RESET GERAL ═════════════════════════════════════════════ */
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Inter', sans-serif;
    background: var(--bg);
    color: var(--ink);
    line-height: 1.55;
    -webkit-font-smoothing: antialiased;
}
a { color: inherit; text-decoration: none; }
button { font-family: inherit; cursor: pointer; }

/* ══ ANIMAÇÕES ═══════════════════════════════════════════════ */
@keyframes fadeUp { from { opacity: 0; transform: translateY(14px); } to { opacity: 1; transform: translateY(0); } }
@keyframes popIn { from { opacity: 0; transform: scale(.94); } to { opacity: 1; transform: scale(1); } }
.anim { opacity: 0; animation: fadeUp .5s cubic-bezier(.22,1,.36,1) forwards; }
.d1 { animation-delay: .04s; } .d2 { animation-delay: .10s; } .d3 { animation-delay: .16s; }
.d4 { animation-delay: .22s; } .d5 { animation-delay: .28s; } .d6 { animation-delay: .34s; }

/* ══ ESTRUTURA PRINCIPAL ═════════════════════════════════════ */
.app { display: flex; min-height: 100vh; }

/* ── Sidebar ── */
.sb {
    width: 248px; background: var(--card); border-right: 1px solid var(--border);
    position: fixed; left: 0; top: 0; height: 100vh; overflow-y: auto; z-index: 200;
    display: flex; flex-direction: column;
    transition: transform .3s cubic-bezier(.22,1,.36,1);
}
.sb-head { padding: 1.4rem 1.3rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
.sb-logo-icon { font-size: 2rem; color: var(--p); }
.sb-logo-txt { font-size: 1.25rem; font-weight: 700; letter-spacing: -.3px; }
.sb-user { padding: 1rem 1.3rem; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: .8rem; }
.sb-av {
    width: 36px; height: 36px; border-radius: 9px; flex-shrink: 0;
    background: linear-gradient(135deg, var(--p), #7ca0ff);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-weight: 700; font-size: .85rem; box-shadow: 0 3px 10px var(--p-glow);
}
.sb-uname { font-weight: 600; font-size: .85rem; }
.sb-urole { font-size: .7rem; color: var(--faint); margin-top: 1px; }
.sb-nav { padding: .9rem .7rem; flex: 1; }
.sb-label {
    font-size: .65rem; font-weight: 700; text-transform: uppercase; letter-spacing: .07em;
    color: var(--faint); padding: 0 .7rem; margin: .9rem 0 .4rem;
}
.sb-label:first-child { margin-top: 0; }
.sb-link {
    display: flex; align-items: center; gap: .7rem;
    padding: .65rem .85rem; border-radius: 9px;
    color: var(--muted); font-weight: 500; font-size: .845rem;
    transition: all .18s; margin-bottom: 2px;
}
.sb-link:hover { background: var(--p-dim); color: var(--p); }
.sb-link.on { background: var(--p-dim); color: var(--p); font-weight: 600; }
.sb-link .material-symbols-outlined { font-size: 1.15rem; width: 20px; text-align: center; flex-shrink: 0; }

/* ── Main Area ── */
.main { margin-left: 248px; flex: 1; padding: 1.75rem 2rem; min-width: 0; }
/* ── Nova hierarquia comercial ── */
.report-top{justify-content:flex-start}.report-actions{margin-left:auto;display:flex;gap:.5rem;align-items:center}.action-menu{position:relative}.action-menu summary{list-style:none}.action-menu summary::-webkit-details-marker{display:none}.action-menu>div{position:absolute;right:0;top:calc(100% + .4rem);z-index:20;width:190px;padding:.35rem;background:var(--card);border:1px solid var(--border);border-radius:10px;box-shadow:var(--sh)}.action-menu button{display:block;width:100%;border:0;background:none;text-align:left;padding:.55rem .65rem;border-radius:7px;color:var(--muted);font:600 .76rem Inter}.action-menu button:hover{background:var(--p-dim);color:var(--p)}
.custom-period>div{display:flex;gap:.35rem}.custom-period input{min-width:0;width:100%;padding:.52rem;border:1px solid var(--border);border-radius:8px;font:inherit;color:var(--ink)}.overview-grid{display:grid;grid-template-columns:minmax(0,1.8fr) minmax(260px,.75fr);gap:1.2rem;margin-bottom:1.2rem}.analysis-grid{display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.2rem}.echart-wrap.compact{height:280px;min-height:220px}.segmented{display:flex;gap:2px;padding:3px;background:var(--bg);border-radius:8px}.segmented button{border:0;background:transparent;padding:.33rem .5rem;border-radius:6px;color:var(--muted);font:700 .65rem Inter}.segmented button.on{background:var(--card);color:var(--p);box-shadow:0 1px 3px rgba(15,28,46,.1)}.chart-controls{display:flex;gap:.45rem;align-items:center;flex-wrap:wrap}.result-card{padding:1.15rem 1.2rem;border:1px solid var(--border);border-radius:var(--r);background:var(--card);box-shadow:var(--sh)}.result-card h2{font-size:.88rem;margin-bottom:1rem}.result-row{display:flex;justify-content:space-between;align-items:baseline;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.77rem;color:var(--muted)}.result-row strong{font-size:1rem;color:var(--ink)}.result-row.profit strong{color:var(--ok)}.result-margin{margin-top:1rem;padding:.7rem .75rem;background:var(--ok-dim);border-radius:9px;color:var(--ok);font-size:.74rem;font-weight:700}.result-warning{margin-top:.8rem;font-size:.7rem;color:#b45309;background:var(--warn-dim);padding:.6rem;border-radius:8px}.product-insight{display:flex;gap:.5rem;align-items:center;padding:.48rem 0;border-bottom:1px solid var(--border);font-size:.72rem}.product-insight:last-child{border:0}.product-insight strong{margin-left:auto;color:var(--ink)}

/* ── Overlay & Mobile Menu ── */
.overlay { display: none; position: fixed; inset: 0; background: rgba(15,28,46,.4); z-index: 199; backdrop-filter: blur(3px); }
.overlay.on { display: block; }
.ham {
    display: none; background: var(--card); border: 1px solid var(--border);
    border-radius: 9px; padding: .42rem; align-items: center; justify-content: center;
    box-shadow: var(--sh); color: var(--ink);
}
.ham .material-symbols-outlined { font-size: 1.3rem; }

/* ══ HEADER DA PÁGINA ════════════════════════════════════════ */
.top { display: flex; align-items: center; gap: .8rem; margin-bottom: 1.4rem; flex-wrap: wrap; }
.ptitle { font-size: 1.45rem; font-weight: 700; letter-spacing: -.4px; }
.psub { color: var(--muted); font-size: .82rem; margin-top: 1px; }

/* ══ HERO EXECUTIVO ══════════════════════════════════════════ */
.report-hero {
    background:
        radial-gradient(circle at top right, rgba(59,111,245,.16), transparent 34%),
        linear-gradient(135deg, #0f1c2e 0%, #182943 54%, #23395d 100%);
    color: #fff;
    border-radius: 18px;
    padding: 1.35rem;
    margin-bottom: 1.2rem;
    box-shadow: 0 18px 46px rgba(15,28,46,.18);
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 1rem;
    overflow: hidden;
    position: relative;
}
.report-hero::after {
    content: '';
    position: absolute;
    right: -70px;
    bottom: -95px;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    background: rgba(255,255,255,.07);
}
.hero-eyebrow { color: #a9c2ff; font-size: .68rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; margin-bottom: .45rem; }
.hero-title { font-size: clamp(1.35rem, 2.2vw, 2rem); line-height: 1.08; font-weight: 800; letter-spacing: -.04em; max-width: 680px; }
.hero-copy { color: rgba(255,255,255,.72); font-size: .86rem; max-width: 660px; margin-top: .55rem; }
.hero-side { align-self: center; display: grid; grid-template-columns: repeat(2, minmax(110px, 1fr)); gap: .65rem; min-width: 280px; position: relative; z-index: 1; }
.hero-stat { background: rgba(255,255,255,.09); border: 1px solid rgba(255,255,255,.13); border-radius: 14px; padding: .8rem; backdrop-filter: blur(10px); }
.hero-stat-v { font-size: 1rem; font-weight: 800; }
.hero-stat-l { color: rgba(255,255,255,.65); font-size: .66rem; margin-top: 2px; }

/* ══ BOTÕES (PILLS) ══════════════════════════════════════════ */
.pills { display: flex; gap: .5rem; margin-bottom: 1.4rem; flex-wrap: wrap; }
.pill {
    display: inline-flex; align-items: center; gap: .4rem;
    padding: .52rem 1rem; border-radius: 50px; font-size: .8rem;
    font-weight: 600; border: none; transition: all .22s; white-space: nowrap;
}
.pill .material-symbols-outlined { font-size: 1rem; }
.pill-solid { background: var(--p); color: #fff; box-shadow: 0 3px 12px var(--p-glow); }
.pill-solid:hover { background: var(--p-dark); transform: translateY(-2px); box-shadow: 0 6px 20px var(--p-glow); }
.pill-green { background: var(--ok); color: #fff; box-shadow: 0 3px 12px rgba(16,185,129,.25); }
.pill-green:hover { background: #059669; transform: translateY(-2px); }
.pill-outline { background: var(--card); color: var(--muted); border: 1px solid var(--border); }
.pill-outline:hover { background: var(--p-dim); border-color: var(--p); color: var(--p); transform: translateY(-2px); }

/* ══ INDICADORES (KPIs) ══════════════════════════════════════ */
.kpis { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); gap: .65rem; margin-bottom: 1.2rem; }
.kpi {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: .78rem .82rem;
    position: relative;
    box-shadow: 0 1px 3px rgba(15,28,46,.04);
    transition: border-color .18s, box-shadow .18s, transform .18s;
}
.kpi:hover { border-color: rgba(59,111,245,.24); box-shadow: var(--sh); transform: translateY(-2px); }
.kpi-top { display: flex; justify-content: space-between; align-items: center; gap: .5rem; margin-bottom: .58rem; }
.kpi-head { display: flex; align-items: center; gap: .48rem; min-width: 0; }
.kpi-ico { width: 30px; height: 30px; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: var(--p-dim); color: var(--p); flex-shrink: 0; }
.kpi.g .kpi-ico { background: var(--ok-dim); color: var(--ok); }
.kpi.o .kpi-ico { background: var(--warn-dim); color: var(--warn); }
.kpi.r .kpi-ico { background: var(--err-dim); color: var(--err); }
.kpi.v .kpi-ico { background: var(--violet-dim); color: var(--violet); }
.kpi.c .kpi-ico { background: var(--cyan-dim); color: var(--cyan); }
.kpi-ico .material-symbols-outlined { font-size: 1rem; }
.kpi-lbl { font-size: .68rem; color: var(--muted); font-weight: 700; line-height: 1.15; }
.kpi-tag { font-size: .59rem; font-weight: 800; padding: .12rem .38rem; border-radius: 20px; background: var(--bg); color: var(--muted); border: 1px solid var(--border); white-space: nowrap; }
.kpi-val { font-size: clamp(1.08rem, 1.35vw, 1.45rem); font-weight: 800; letter-spacing: -.04em; line-height: 1.05; margin-bottom: .2rem; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.kpi-primary .kpi-val { font-size: clamp(1.08rem, 1.35vw, 1.45rem); }
.kpi-hint { font-size: .63rem; color: var(--faint); line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.kpi-delta { display: inline-flex; align-items: center; gap: .25rem; margin-top: .65rem; padding-top: .65rem; border-top: 1px solid var(--border); width: 100%; font-size: .68rem; color: var(--muted); font-weight: 600; }
.kpi-delta .material-symbols-outlined { font-size: .86rem; color: var(--p); }
.kpi.g .kpi-delta .material-symbols-outlined { color: var(--ok); }
.kpi.o .kpi-delta .material-symbols-outlined { color: var(--warn); }
.kpi.r .kpi-delta .material-symbols-outlined { color: var(--err); }
.kpi.v .kpi-delta .material-symbols-outlined { color: var(--violet); }
.kpi.c .kpi-delta .material-symbols-outlined { color: var(--cyan); }

/* ══ COMPONENTES GERAIS (CARDS E FILTROS) ════════════════════ */
.card { background: var(--card); border: 1px solid var(--border); border-radius: var(--r); padding: 1.1rem 1.2rem; margin-bottom: 1.2rem; }
.ch { display: flex; justify-content: space-between; align-items: center; margin-bottom: .9rem; padding-bottom: .7rem; border-bottom: 1px solid var(--border); flex-wrap: wrap; gap: .5rem; }
.ct { font-size: .88rem; font-weight: 700; display: inline-flex; align-items: center; gap: .4rem; }
.ct .material-symbols-outlined { font-size: 1rem; color: var(--p); }

.filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: .8rem; align-items: end; }
.fg { display: flex; flex-direction: column; gap: .3rem; }
.fg label { font-size: .68rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
.fg select {
    padding: .55rem .85rem; border: 1px solid var(--border); border-radius: 9px;
    font-size: .83rem; background: var(--card); outline: none;
    font-family: inherit; color: var(--ink);
    transition: border-color .18s, box-shadow .18s;
    width: 100%;
}
.fg select:focus { border-color: var(--p); box-shadow: 0 0 0 3px var(--p-dim); }

/* ══ DASHBOARD GRID (GRÁFICO + RANKING) ══════════════════════ */
/* Agora organizado lado a lado no desktop para aproveitar espaço */
.dash-layout { display: grid; grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr); gap: 1.2rem; margin-bottom: 1.2rem; }

/* ── Gráfico ── */
.chart-card { display: flex; flex-direction: column; background: var(--card); border: 1px solid var(--border); border-radius: var(--r); padding: 1.1rem 1.2rem; height: 100%; min-width: 0; }
.echart-wrap { width: 100%; height: clamp(260px, 34vw, 390px); min-height: 260px; position: relative; margin-top: auto; }
.echart { width: 100%; height: 100%; }
.chart-foot { display: flex; border-top: 1px solid var(--border); padding-top: .75rem; margin-top: .85rem; }
.cf { flex: 1; text-align: center; }
.cf + .cf { border-left: 1px solid var(--border); }
.cf-v { font-size: .95rem; font-weight: 700; }
.cf-l { font-size: .68rem; color: var(--faint); margin-top: 2px; }
.chart-empty { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 180px; color: var(--faint); gap: .4rem; }
.chart-empty .material-symbols-outlined { font-size: 2rem; opacity: .3; }
.chart-empty p { font-size: .78rem; }

/* ── Top Produtos ── */
.top-prods-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--r); padding: 1.1rem 1.2rem; height: 100%; display: flex; flex-direction: column; }
.ti-grid { display: flex; flex-direction: column; gap: .4rem; flex: 1; justify-content: flex-start; }
.ti { display: flex; align-items: center; gap: .65rem; padding: .6rem; border-radius: 9px; transition: background .15s; border: 1px solid transparent; }
.ti:hover { background: var(--bg); border-color: var(--border); }
.rk { width: 26px; height: 26px; border-radius: 7px; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 800; background: var(--p-dim); color: var(--p); }
.rk.au { background: rgba(245,158,11,.15); color: #b45309; }
.rk.ag { background: rgba(148,163,184,.18); color: #64748b; }
.rk.br { background: rgba(146,64,14,.12); color: #92400e; }
.ti-body { flex: 1; min-width: 0; }
.ti-name { font-size: .83rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.ti-sub { font-size: .68rem; color: var(--faint); margin-top: 1px; }
.ti-val { font-size: .85rem; font-weight: 700; color: var(--p); white-space: nowrap; }

/* ══ PAINÉIS ANALÍTICOS ══════════════════════════════════════ */
.insight-grid { display: grid; grid-template-columns: 1.15fr 1fr 1fr; gap: 1.2rem; margin-bottom: 1.2rem; }
.insight-card { background: var(--card); border: 1px solid var(--border); border-radius: var(--r); padding: 1.1rem 1.2rem; min-width: 0; display: flex; flex-direction: column; height: 100%; }
.mini-list { display: flex; flex-direction: column; gap: .65rem; flex: 1; }
.rank-row { display: grid; grid-template-columns: 28px minmax(0, 1fr) auto; gap: .65rem; align-items: center; }
.rank-num { width: 28px; height: 28px; border-radius: 8px; background: var(--p-dim); color: var(--p); display: grid; place-items: center; font-size: .72rem; font-weight: 800; }
.rank-name { font-size: .82rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.rank-meta { font-size: .68rem; color: var(--faint); margin-top: 1px; }
.rank-value { font-size: .82rem; font-weight: 800; color: var(--ink); white-space: nowrap; }
.bar-track { grid-column: 2 / 4; height: 7px; background: var(--bg); border-radius: 99px; overflow: hidden; }
.bar-track span { display: block; height: 100%; background: linear-gradient(90deg, var(--p), #7fa3ff); border-radius: inherit; min-width: 3px; }
.mix-row { display: grid; grid-template-columns: 42px minmax(0, 1fr) auto; grid-template-areas: "photo name value" "photo bar bar"; column-gap: .65rem; row-gap: .35rem; align-items: center; min-height: 42px; }
.mix-thumb { grid-area: photo; width: 42px; height: 42px; border-radius: 9px; overflow: hidden; background: var(--bg); border: 1px solid var(--border); display: grid; place-items: center; color: var(--faint); }
.mix-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.mix-thumb .material-symbols-outlined { font-size: 1.15rem; }
.mix-thumb-button { padding: 0; cursor: zoom-in; transition: border-color .18s, box-shadow .18s, transform .18s; }
.mix-thumb-button:hover { border-color: var(--p); box-shadow: 0 0 0 3px var(--p-dim); transform: scale(1.04); }
.mix-thumb-button:focus-visible { outline: 2px solid var(--p); outline-offset: 2px; }
.mix-name { grid-area: name; font-size: .78rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.mix-val { grid-area: value; font-size: .74rem; color: var(--muted); font-weight: 700; white-space: nowrap; }
.mix-bar { grid-area: bar; height: 8px; background: var(--bg); border-radius: 99px; overflow: hidden; }
.mix-bar span { display: block; height: 100%; background: linear-gradient(90deg, var(--ok), #8ee6c3); border-radius: inherit; min-width: 3px; }
.pulse-panel { display: grid; gap: .65rem; }
.pulse-item { display: flex; gap: .65rem; padding: .68rem; border: 1px solid var(--border); border-radius: 11px; background: var(--bg); }
.pulse-ico { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; flex-shrink: 0; background: var(--p-dim); color: var(--p); }
.pulse-ico .material-symbols-outlined { font-size: 1rem; }
.pulse-title { font-size: .78rem; font-weight: 800; }
.pulse-text { font-size: .7rem; color: var(--muted); margin-top: 1px; }
.report-note { font-size: .72rem; color: var(--faint); font-weight: 600; }

/* ══ TABELA ══════════════════════════════════════════════════ */
.tbl-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; margin: 0 -1.2rem; padding: 0 1.2rem; }
.tbl { width: 100%; min-width: 680px; border-collapse: collapse; font-size: .82rem; }
.tbl thead { background: var(--bg); }
.tbl th { text-align: left; padding: .65rem 1rem; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); white-space: nowrap; border-bottom: 1px solid var(--border); }
.tbl td { padding: .72rem 1rem; border-bottom: 1px solid var(--border); vertical-align: middle; }
.tbl tbody tr:hover { background: var(--bg); }
.tbl tbody tr:last-child td { border-bottom: none; }
.bx { display: inline-block; padding: .18rem .6rem; border-radius: 20px; font-size: .72rem; font-weight: 600; }
.bx.ok { background: var(--ok-dim); color: var(--ok); }
.bx.bl { background: var(--p-dim); color: var(--p); }
.del-btn { background: var(--err-dim); color: var(--err); border: none; padding: .32rem .6rem; border-radius: 7px; font-size: .78rem; font-weight: 600; cursor: pointer; transition: all .18s; display: inline-flex; align-items: center; gap: .2rem; }
.del-btn:hover { background: rgba(239,68,68,.18); }
.del-btn .material-symbols-outlined { font-size: .88rem; }

/* ══ PAGINAÇÃO ═══════════════════════════════════════════════ */
.pag { display: flex; justify-content: center; align-items: center; gap: .4rem; margin-top: 1rem; flex-wrap: wrap; }
.pg-btn { padding: .4rem .75rem; border: 1px solid var(--border); border-radius: 8px; background: var(--card); cursor: pointer; font-size: .82rem; transition: all .18s; font-family: inherit; color: var(--ink); }
.pg-btn:hover { background: var(--p-dim); border-color: var(--p); color: var(--p); }
.pg-btn.on { background: var(--p); color: #fff; border-color: var(--p); font-weight: 600; }
.pg-btn:disabled { opacity: .35; cursor: not-allowed; }

/* ══ EMPTY STATE E MODAIS ════════════════════════════════════ */
.empty { text-align: center; padding: 2rem 1rem; color: var(--faint); }
.empty .material-symbols-outlined { font-size: 2rem; display: block; margin-bottom: .35rem; opacity: .3; }
.empty p { font-size: .8rem; }
.modal { display: none; position: fixed; inset: 0; background: rgba(15,28,46,.55); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(5px); }
.modal.show { display: flex; }
.image-preview-box { position: relative; width: fit-content; max-width: 92vw; background: var(--card); border-radius: 14px; padding: .75rem; box-shadow: 0 24px 64px rgba(0,0,0,.3); animation: popIn .24s cubic-bezier(.22,1,.36,1) both; }
.image-preview-box img { display: block; max-width: calc(92vw - 1.5rem); max-height: calc(86vh - 3.5rem); object-fit: contain; border-radius: 9px; background: var(--bg); }
.image-preview-caption { padding: .65rem .25rem 0; font-size: .78rem; font-weight: 700; color: var(--ink); text-align: center; }
.image-preview-close { position: absolute; top: -12px; right: -12px; width: 34px; height: 34px; border: 1px solid var(--border); border-radius: 50%; background: var(--card); color: var(--ink); display: grid; place-items: center; box-shadow: var(--sh); z-index: 1; }
.image-preview-close:hover { background: var(--p); border-color: var(--p); color: #fff; }
.image-preview-close .material-symbols-outlined { font-size: 1.15rem; }
.mbox { background: var(--card); border-radius: 16px; width: 90%; max-width: 500px; padding: 1.7rem; max-height: 94vh; overflow-y: auto; box-shadow: 0 24px 64px rgba(0,0,0,.22); animation: popIn .28s cubic-bezier(.22,1,.36,1) both; }
.mbox.wide { max-width: 600px; }
.mh { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.3rem; }
.mh h2 { font-size: 1rem; font-weight: 700; }
.mcl { font-size: 1.7rem; cursor: pointer; color: var(--faint); line-height: 1; transition: color .15s; }
.mcl:hover { color: var(--ink); }
.steps { display: flex; align-items: center; gap: .4rem; margin-bottom: 1.2rem; }
.sdot { width: 26px; height: 26px; border-radius: 50%; flex-shrink: 0; display: flex; align-items: center; justify-content: center; font-size: .75rem; font-weight: 700; transition: all .3s; }
.sdot .material-symbols-outlined { font-size: 1rem; }
.sdot.done { background: var(--ok); color: #fff; }
.sdot.act { background: var(--p); color: #fff; }
.sdot.pend { background: var(--border); color: var(--muted); }
.sline { flex: 1; height: 2px; background: var(--border); border-radius: 2px; transition: background .3s; }
.sline.done { background: var(--ok); }
.slabels { display: flex; justify-content: space-between; margin-top: -.8rem; margin-bottom: 1.2rem; }
.slabels span { font-size: .67rem; color: var(--muted); font-weight: 600; }
.sp { display: none; }
.sp.on { display: block; }
.fl { display: flex; flex-direction: column; gap: .3rem; margin-bottom: .85rem; }
.fl label { font-size: .7rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
.fl input, .fl select, .fl textarea { padding: .68rem .9rem; border: 1px solid var(--border); border-radius: 9px; font-size: .87rem; font-family: inherit; transition: border-color .18s, box-shadow .18s; outline: none; color: var(--ink); }
.fl input:focus, .fl select:focus, .fl textarea:focus { border-color: var(--p); box-shadow: 0 0 0 3px var(--p-dim); }
.fgrid2 { display: grid; grid-template-columns: 1fr 1fr; gap: .8rem; }
.total-box { background: var(--ok-dim); border: 1px solid rgba(16,185,129,.2); border-radius: 10px; padding: .65rem 1rem; text-align: center; margin: .2rem 0 .85rem; }
.total-box .tv { font-size: 1.3rem; font-weight: 700; color: var(--ok); }
.total-box .tl { font-size: .7rem; color: var(--muted); margin-top: 1px; }
.info-box { background: var(--p-dim); border: 1px solid rgba(59,111,245,.18); border-radius: 10px; padding: .75rem 1rem; margin-bottom: .85rem; display: none; }
.info-box .ib-nome { font-weight: 700; font-size: .92rem; }
.info-box .ib-meta { font-size: .75rem; color: var(--muted); margin-top: 2px; }
.pac { display: flex; align-items: center; gap: .75rem; padding: .75rem .9rem; border: 1px solid var(--border); border-radius: 10px; margin-bottom: .55rem; background: var(--bg); transition: all .18s; }
.pac:hover { border-color: var(--ok); background: rgba(16,185,129,.04); }
.pac-body { flex: 1; min-width: 0; }
.pac-nome { font-weight: 600; font-size: .83rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.pac-sub { font-size: .7rem; color: var(--faint); margin-top: 1px; }
.pac-badge { background: var(--ok-dim); color: var(--ok); padding: .13rem .5rem; border-radius: 20px; font-size: .7rem; font-weight: 700; white-space: nowrap; flex-shrink: 0; }
.pac-input { width: 68px; padding: .42rem .5rem; border: 1.5px solid var(--border); border-radius: 8px; font-size: .88rem; text-align: center; outline: none; transition: border-color .18s; font-family: inherit; flex-shrink: 0; }
.pac-input:focus { border-color: var(--ok); box-shadow: 0 0 0 3px rgba(16,185,129,.1); }
.pac-list { max-height: 280px; overflow-y: auto; padding-right: 2px; }
.sw { text-align: center; padding: .5rem 0; }
.sw .sico { font-size: 3rem; margin-bottom: .6rem; }
.sw .sico .material-symbols-outlined { font-size: 3rem; color: var(--ok); }
.sw h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: .35rem; }
.sw p { font-size: .82rem; color: var(--muted); margin-bottom: .9rem; }
.resumo-lista { background: var(--bg); border-radius: 10px; padding: .85rem; text-align: left; max-height: 170px; overflow-y: auto; font-size: .8rem; margin-bottom: .9rem; }
.res-row { display: flex; justify-content: space-between; padding: .3rem 0; border-bottom: 1px solid var(--border); }
.res-row:last-child { border-bottom: none; }
.mbtn { background: var(--p); color: #fff; border: none; padding: .72rem; border-radius: 10px; font-weight: 700; width: 100%; font-size: .86rem; font-family: inherit; display: flex; align-items: center; justify-content: center; gap: .4rem; transition: all .22s; box-shadow: 0 4px 14px var(--p-glow); }
.mbtn:hover { background: var(--p-dark); transform: translateY(-2px); }
.mbtn:disabled { background: var(--faint); box-shadow: none; transform: none; cursor: not-allowed; }
.mbtn.ok { background: var(--ok); box-shadow: 0 4px 14px rgba(16,185,129,.25); }
.mbtn.ok:hover { background: #059669; }
.mbtn.ghost { background: var(--card); color: var(--muted); border: 1px solid var(--border); box-shadow: none; }
.mbtn.ghost:hover { background: var(--p-dim); border-color: var(--p); color: var(--p); transform: translateY(-2px); }
.brow { display: flex; gap: .6rem; margin-top: 1rem; }
.brow .mbtn { flex: 1; }
.bnav { display: none; position: fixed; bottom: 0; left: 0; right: 0; background: var(--card); border-top: 1px solid var(--border); z-index: 300; padding: .35rem 0 calc(.35rem + env(safe-area-inset-bottom)); box-shadow: 0 -2px 16px rgba(15,28,46,.07); }
.bnav-inner { display: flex; justify-content: space-around; }
.bnav-a { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: .35rem .6rem; border-radius: 9px; color: var(--faint); font-size: .62rem; font-weight: 600; transition: all .18s; flex: 1; }
.bnav-a .material-symbols-outlined { font-size: 1.25rem; }
.bnav-a.on { color: var(--p); }
.bnav-a:hover { background: var(--p-dim); }

/* ══ SKELETON ════════════════════════════════════════════════ */
.sk { border-radius: 7px; background: linear-gradient(90deg, var(--bg) 25%, #eaeef4 50%, var(--bg) 75%); background-size: 200% 100%; animation: shimmer 1.3s infinite; }
@keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

/* ══ RESPONSIVIDADE ══════════════════════════════════════════ */
@media (max-width: 1050px) {
    .kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .dash-layout { grid-template-columns: 1fr; } /* Empilha o gráfico e os tops em telas menores */
    .insight-grid { grid-template-columns: 1fr; }
    .report-hero { grid-template-columns: 1fr; }
    .hero-side { min-width: 0; }
}
@media (max-width: 860px) {
    .sb { transform: translateX(-100%); }
    .sb.open { transform: translateX(0); }
    .main { margin-left: 0; }
    .ham { display: flex; }
}
@media (max-width: 640px) {
    .bnav { display: block; }
    .main { padding: .9rem .9rem calc(70px + env(safe-area-inset-bottom)); }
    .top { margin-bottom: 1rem; }
    .pills { gap: .4rem; }
    .pill { padding: .46rem .8rem; font-size: .76rem; }
    .kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem; }
    .kpi-val { font-size: 1.3rem; }
    .report-hero { padding: 1rem; border-radius: 14px; }
    .hero-side { grid-template-columns: 1fr 1fr; }
    .echart-wrap { height: 290px; min-height: 290px; }
    .mbox { padding: 1.2rem; width: 96%; max-height: 96vh; }
    .fgrid2 { grid-template-columns: 1fr; }
    .pac { flex-wrap: wrap; }
    .pac-input { width: 62px; }
}
@media (max-width: 380px) {
    .kpis { grid-template-columns: 1fr; }
}
@media(max-width:980px){.overview-grid,.analysis-grid{grid-template-columns:1fr}.result-card{order:2}}
@media(max-width:640px){.report-top{align-items:flex-start}.report-actions{margin-left:0;width:100%;justify-content:space-between}.report-actions .pill{padding:.5rem .65rem}.kpis{grid-template-columns:repeat(2,minmax(0,1fr))}.filters-grid{grid-template-columns:1fr}.echart-wrap.compact{height:250px}.chart-controls{width:100%;justify-content:space-between}.tbl{min-width:900px}}
</style>
<link rel="stylesheet" href="assets/app-theme.css?v=20260723">
<script src="assets/app-theme.js?v=20260723"></script>
</head>
<body>
<div class="app">
<div class="overlay" id="ov"></div>

<?php require __DIR__ . '/includes/sidebar.php'; ?>

<main class="main">

    <header class="top report-top anim d1">
        <button class="ham" id="ham"><span class="material-symbols-outlined">menu</span></button>
        <div>
            <h1 class="ptitle">Relatórios de Vendas</h1>
            <p class="psub">Acompanhe receita, custos, lucro bruto e desempenho comercial.</p>
        </div>
        <div class="report-actions">
            <button class="pill pill-outline" id="btnCSV"><span class="material-symbols-outlined">download</span>Exportar</button>
            <details class="action-menu"><summary class="pill pill-solid"><span class="material-symbols-outlined">add</span>Registrar venda</summary><div><button id="btnDV">Declarar venda</button><button id="btnVV">Venda por vendedor</button></div></details>
        </div>
    </header>

    <section class="card anim d4">
        <header class="ch"><span class="ct">Filtros do relatório</span></header>
        <div class="filters-grid">
            <div class="fg">
                <label>Período</label>
                <select id="fPer">
                    <option value="1">Hoje</option>
                    <option value="7">Últimos 7 dias</option>
                    <option value="30" selected>Últimos 30 dias</option>
                    <option value="month">Este mês</option>
                    <option value="previous-month">Mês anterior</option>
                    <option value="90">Últimos 90 dias</option><option value="365">Último ano</option>
                    <option value="custom">Personalizado</option>
                    <option value="0">Todo período</option>
                </select>
            </div>
            <div class="fg custom-period" id="customPeriod" hidden><label>Intervalo</label><div><input type="date" id="fInicio"><input type="date" id="fFim"></div></div>
            <div class="fg">
                <label>Vendedor</label>
                <select id="fVend"><option value="">Todos</option></select>
            </div>
            <div class="fg">
                <label>Produto</label>
                <select id="fProd"><option value="">Todos</option></select>
            </div>
            <div class="fg">
                <label>Origem</label>
                <select id="fOrig"><option value="">Todas</option></select>
            </div>
            <button class="pill pill-outline" id="btnFilt" style="height: 38px;">
                <span class="material-symbols-outlined">filter_alt</span>Filtrar
            </button>
        </div>
    </section>

    <section class="kpis anim d3" id="kpis"></section>

    <section class="overview-grid anim d5">
        <div class="chart-card">
            <header class="ch">
                <span class="ct" id="chartTitle">Evolução das vendas</span><div class="chart-controls"><div class="segmented" id="chartType"><button data-view="bar" class="on">Barras</button><button data-view="line">Linha</button></div><div class="segmented" id="granularity"><button data-g="day" class="on">Diário</button><button data-g="week">Semanal</button><button data-g="month">Mensal</button></div></div>
            </header>
            <div class="echart-wrap compact">
                <div class="echart" id="salesChart" role="img" aria-label="Gráfico de receita por período"></div>
            </div>
        </div>
        <aside class="result-card"><h2>Resultado do período</h2><div id="resultCard"></div></aside>
    </section>

    <section class="card anim d5"><header class="ch"><span class="ct">Receita, custo e lucro</span><span class="report-note">Custo + lucro bruto = receita</span></header><div class="echart-wrap"><div class="echart" id="financialChart"></div></div></section>

    <section class="analysis-grid anim d6">
        <div class="insight-card"><header class="ch"><span class="ct">Desempenho por produto</span><div class="segmented" id="productMetric"><button data-m="revenue" class="on">Receita</button><button data-m="profit">Lucro</button><button data-m="units">Unidades</button></div></header><div class="echart-wrap compact"><div class="echart" id="productChart"></div></div><div class="mini-list" id="productHighlights"></div></div>
        <div class="insight-card"><header class="ch"><span class="ct">Insights do período</span></header><div class="pulse-panel" id="execPulse"></div></div>
    </section>

    <section class="analysis-grid anim d6"><div class="insight-card"><header class="ch"><span class="ct">Desempenho dos vendedores</span><div class="segmented" id="vendorMetric"><button data-m="revenue" class="on">Receita</button><button data-m="sales">Vendas</button><button data-m="ticket">Ticket</button></div></header><div id="vendRank" class="mini-list"></div></div><div class="insight-card"><header class="ch"><span class="ct">Mix de produtos</span><span class="report-note">Participação na receita</span></header><div class="echart-wrap compact"><div class="echart" id="mixChart"></div></div></div></section>

    <section class="card anim d6">
        <header class="ch">
            <span class="ct">Histórico de vendas</span>
            <span id="totalBx" class="bx bl">—</span>
        </header>
        <div class="tbl-scroll">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>#</th><th>Produto</th><th>Origem</th><th>Vendedor</th>
                        <th>Qtd</th><th>Preço</th><th>Custo / Lucro</th><th>Total</th>
                        <th>Obs.</th><th>Data</th><th></th>
                    </tr>
                </thead>
                <tbody id="tbody"></tbody>
            </table>
        </div>
        <nav class="pag" id="pag"></nav>
    </section>

</main>
</div>

<nav class="bnav">
    <div class="bnav-inner">
        <a href="estoque.php"    class="bnav-a"><span class="material-symbols-outlined">dashboard</span>Início</a>
        <a href="relatorio.php"  class="bnav-a on"><span class="material-symbols-outlined">bar_chart</span>Relatórios</a>
        <a href="produtos.php"   class="bnav-a"><span class="material-symbols-outlined">inventory</span>Produtos</a>
        <a href="vendedores.php" class="bnav-a"><span class="material-symbols-outlined">group</span>Vendedores</a>
    </div>
</nav>

<div class="modal" id="imagePreview" aria-hidden="true">
    <div class="image-preview-box" role="dialog" aria-modal="true" aria-labelledby="imagePreviewCaption">
        <button class="image-preview-close" id="imagePreviewClose" type="button" aria-label="Fechar imagem ampliada">
            <span class="material-symbols-outlined">close</span>
        </button>
        <img id="imagePreviewImg" src="" alt="">
        <div class="image-preview-caption" id="imagePreviewCaption"></div>
    </div>
</div>

<div class="modal" id="modDV">
    <div class="mbox">
        <div class="mh">
            <h2>Declarar Venda</h2>
            <span class="mcl" id="dvCl">close</span>
        </div>
        <div class="steps">
            <div class="sdot act"  id="dv_s1">1</div>
            <div class="sline"     id="dv_l1"></div>
            <div class="sdot pend" id="dv_s2">2</div>
            <div class="sline"     id="dv_l2"></div>
            <div class="sdot pend" id="dv_s3">3</div>
        </div>
        <div class="slabels">
            <span>Produto</span><span>Detalhes</span><span>Pronto</span>
        </div>
        <div class="sp on" id="dv_p1">
            <div class="fl">
                <label>Produto *</label>
                <select id="dv_prod"><option value="">Selecione um produto…</option></select>
            </div>
            <div class="info-box" id="dv_prodBox">
                <div class="ib-nome" id="dv_prodNome">—</div>
                <div class="ib-meta" id="dv_prodMeta">—</div>
            </div>
            <button class="mbtn" id="dv_next1" disabled>
                Próximo <span class="material-symbols-outlined">arrow_forward</span>
            </button>
        </div>
        <div class="sp" id="dv_p2">
            <div class="fgrid2">
                <div class="fl">
                    <label>Quantidade *</label>
                    <input type="number" id="dv_qtd" min="1" value="1" placeholder="1">
                </div>
                <div class="fl">
                    <label>Preço unit. (R$) *</label>
                    <input type="number" id="dv_preco" step="0.01" min="0" placeholder="0,00">
                </div>
            </div>
            <div class="info-box" id="dv_marginBox" style="display:none"><div class="ib-meta" id="dv_marginMeta">Margem estimada: —</div></div>
            <div class="total-box">
                <div class="tv" id="dv_total">R$ 0,00</div>
                <div class="tl">Total da venda</div>
            </div>
            <div class="fl">
                <label>Vendedor (opcional)</label>
                <select id="dv_vend"><option value="">Venda direta</option></select>
            </div>
            <div class="fl">
                <label>Observação (opcional)</label>
                <textarea id="dv_obs" rows="2" placeholder="Ex: promoção especial, cliente VIP…"></textarea>
            </div>
            <div class="brow">
                <button class="mbtn ghost" id="dv_back">
                    <span class="material-symbols-outlined">arrow_back</span>Voltar
                </button>
                <button class="mbtn" id="dv_save">
                    Confirmar <span class="material-symbols-outlined">check_circle</span>
                </button>
            </div>
        </div>
        <div class="sp" id="dv_p3">
            <div class="sw">
                <div class="sico"><span class="material-symbols-outlined">check_circle</span></div>
                <h3>Venda registrada!</h3>
                <p id="dv_succtxt">—</p>
                <button class="mbtn" id="dv_done">
                    Concluir <span class="material-symbols-outlined">done_all</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal" id="modVV">
    <div class="mbox wide">
        <div class="mh">
            <h2>Venda por Vendedor</h2>
            <span class="mcl" id="vvCl">close</span>
        </div>
        <div class="steps">
            <div class="sdot act"  id="vv_s1">1</div>
            <div class="sline"     id="vv_l1"></div>
            <div class="sdot pend" id="vv_s2">2</div>
            <div class="sline"     id="vv_l2"></div>
            <div class="sdot pend" id="vv_s3">3</div>
        </div>
        <div class="slabels">
            <span>Vendedor</span><span>Produtos</span><span>Resultado</span>
        </div>
        <div class="sp on" id="vv_p1">
            <div class="fl">
                <label>Vendedor *</label>
                <select id="vv_vend"><option value="">Selecione…</option></select>
            </div>
            <div class="info-box" id="vv_vendBox">
                <div class="ib-nome" id="vv_vendNome">—</div>
                <div class="ib-meta" id="vv_vendMeta">—</div>
            </div>
            <button class="mbtn" id="vv_next1" disabled>
                Próximo <span class="material-symbols-outlined">arrow_forward</span>
            </button>
        </div>
        <div class="sp" id="vv_p2">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.65rem">
                <span style="font-weight:700;font-size:.88rem" id="vv_p2title">Produtos</span>
                <span class="bx ok" id="vv_count">0</span>
            </div>
            <p style="font-size:.76rem;color:var(--faint);margin-bottom:.75rem">
                Informe a quantidade vendida. Deixe <strong>0</strong> para pular.
            </p>
            <div class="pac-list" id="vv_list">
                <p style="text-align:center;padding:2rem;color:var(--faint);font-size:.8rem">Carregando…</p>
            </div>
            <div class="fl" style="margin-top:.85rem">
                <label>Observação (opcional)</label>
                <textarea id="vv_obs" rows="2" placeholder="Ex: feira, lote, cliente especial…"></textarea>
            </div>
            <div class="brow">
                <button class="mbtn ghost" id="vv_back">
                    <span class="material-symbols-outlined">arrow_back</span>Voltar
                </button>
                <button class="mbtn ok" id="vv_conf">
                    <span class="material-symbols-outlined">check_circle</span>Confirmar vendas
                </button>
            </div>
        </div>
        <div class="sp" id="vv_p3">
            <div class="sw">
                <div class="sico"><span class="material-symbols-outlined">check_circle</span></div>
                <h3>Vendas registradas!</h3>
                <p id="vv_succtxt">—</p>
                <div class="resumo-lista" id="vv_resumo"></div>
                <button class="mbtn" id="vv_done">
                    Concluir <span class="material-symbols-outlined">done_all</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// O código Javascript manteve sua base operacional inalterada para garantir a compatibilidade
const CSRF = <?= json_encode(get_csrf_token()) ?>;
const $    = id => document.getElementById(id);
const esc  = v => String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const R    = v => 'R$ '+Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
const originLabel = value => ({vitrine:'Vitrine',venda_direta:'Venda direta',whatsapp:'WhatsApp',site_pro:'Site PRO',legacy:'Desconhecida'})[value] || 'Desconhecida';
const dFmt = s => { if(!s) return '—'; const d=new Date(s); return d.toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit',year:'2-digit'})+' '+d.toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'}) };
const pct  = (v,t) => !t ? 0 : Math.round((Number(v||0) / Number(t||0)) * 100);
const shortName = (v, max=18) => {
    const s = String(v || '—');
    return s.length > max ? s.slice(0, max - 1) + '…' : s;
};

function groupStats(vendas, keyFn) {
    const mp = {};
    vendas.forEach(v => {
        const key = keyFn(v);
        if (!mp[key]) mp[key] = {q:0, r:0, n:0};
        mp[key].q += Number(v.quantidade||0);
        mp[key].r += Number(v.total||0);
        mp[key].n += 1;
    });
    return Object.entries(mp).map(([nome,x]) => ({nome, ...x})).sort((a,b)=>b.r-a.r);
}

function dailyStats(vendas) {
    const mp = {};
    vendas.forEach(v => {
        const k = (v.criado_em || '').slice(0,10);
        if (!k) return;
        if (!mp[k]) mp[k] = {r:0, q:0, n:0};
        mp[k].r += Number(v.total||0);
        mp[k].q += Number(v.quantidade||0);
        mp[k].n += 1;
    });
    return Object.entries(mp).map(([dia,x]) => ({dia, ...x})).sort((a,b)=>a.dia.localeCompare(b.dia));
}

const sb=$('sb'), ov=$('ov');
$('ham').addEventListener('click',()=>{sb.classList.add('open');ov.classList.add('on');document.body.style.overflow='hidden'});
ov.addEventListener('click',()=>{sb.classList.remove('open');ov.classList.remove('on');document.body.style.overflow=''});
sb.querySelectorAll('.sb-link').forEach(l=>l.addEventListener('click',()=>{
    if(window.innerWidth<=860){sb.classList.remove('open');ov.classList.remove('on');document.body.style.overflow=''}
}));

let allVendas=[], vendsCache=[], prodsCache=[], vvProds=[];
let salesChart = null, salesChartObserver = null;
let pg=1; const PP=15;

function closeImagePreview() {
    const modal = $('imagePreview');
    modal.classList.remove('show');
    modal.setAttribute('aria-hidden', 'true');
    $('imagePreviewImg').src = '';
}

$('prodMix')?.addEventListener('click', e => {
    const trigger = e.target.closest('.mix-thumb-button');
    if (!trigger) return;
    const name = trigger.dataset.name || 'Produto';
    $('imagePreviewImg').src = trigger.dataset.image;
    $('imagePreviewImg').alt = name;
    $('imagePreviewCaption').textContent = name;
    $('imagePreview').classList.add('show');
    $('imagePreview').setAttribute('aria-hidden', 'false');
    $('imagePreviewClose').focus();
});
$('imagePreviewClose').addEventListener('click', closeImagePreview);
$('imagePreview').addEventListener('click', e => { if (e.target === $('imagePreview')) closeImagePreview(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && $('imagePreview').classList.contains('show')) closeImagePreview(); });

function goStep(prefix, n) {
    for (let i=1; i<=3; i++) {
        const dot = $(prefix+'_s'+i);
        if (!dot) continue;
        dot.className = 'sdot ' + (i < n ? 'done' : i === n ? 'act' : 'pend');
        dot.innerHTML = i < n ? '<span class="material-symbols-outlined">check</span>' : String(i);
        if (i < 3) {
            const line = $(prefix+'_l'+i);
            if (line) line.className = 'sline ' + (i < n ? 'done' : '');
        }
        const panel = $(prefix+'_p'+i);
        if (panel) panel.className = 'sp ' + (i === n ? 'on' : '');
    }
}

function openDV() {
    goStep('dv',1);
    $('dv_prod').value=''; $('dv_prodBox').style.display='none'; $('dv_next1').disabled=true;
    $('dv_qtd').value=1; $('dv_preco').value=''; $('dv_total').textContent='R$ 0,00';
    $('dv_marginBox').style.display='none'; $('dv_marginMeta').textContent='Margem estimada: —';
    $('dv_vend').value=''; $('dv_obs').value='';
    $('modDV').classList.add('show');
}
$('btnDV').addEventListener('click', openDV);
$('dvCl').addEventListener('click', ()=>$('modDV').classList.remove('show'));
$('modDV').addEventListener('click', e=>{ if(e.target===$('modDV')) $('modDV').classList.remove('show') });

$('dv_prod').addEventListener('change', ()=>{
    const id = $('dv_prod').value;
    if (!id) { $('dv_prodBox').style.display='none'; $('dv_next1').disabled=true; return; }
    const p = prodsCache.find(x => x.id == id);
    if (p) {
        $('dv_prodNome').textContent = p.nome;
        $('dv_prodMeta').textContent = `Cód: ${p.codigo||'—'}  ·  Estoque: ${p.quantidade} un.  ·  Venda ${R(p.preco)}  ·  Custo ${R(p.preco_custo||0)}`;
        $('dv_preco').value = Number(p.preco).toFixed(2);
        calcTotal();
        $('dv_prodBox').style.display = 'block';
    }
    $('dv_next1').disabled = false;
});
$('dv_next1').addEventListener('click', ()=> goStep('dv',2));
$('dv_back').addEventListener('click', ()=> goStep('dv',1));

function calcTotal() {
    const q=parseFloat($('dv_qtd').value)||0, p=parseFloat($('dv_preco').value)||0;
    $('dv_total').textContent = R(q*p);
    const produto=prodsCache.find(x=>x.id == $('dv_prod').value);
    if (produto) {
        const custo=Number(produto.preco_custo||0);
        $('dv_marginMeta').textContent = `Margem estimada: ${p>0?((p-custo)/p*100).toFixed(1).replace('.',','):'0,0'}% · Lucro bruto: ${R(q*(p-custo))}`;
        $('dv_marginBox').style.display='block';
    }
}
$('dv_qtd').addEventListener('input', calcTotal);
$('dv_preco').addEventListener('input', calcTotal);

$('dv_save').addEventListener('click', async ()=>{
    const pid   = $('dv_prod').value;
    const qtd   = parseInt($('dv_qtd').value);
    const preco = parseFloat($('dv_preco').value);
    if (!pid)            return alert('Selecione um produto');
    if (!qtd || qtd < 1) return alert('Quantidade mínima: 1');
    if (!preco || preco <= 0) return alert('Informe o preço');
    const btn = $('dv_save');
    btn.disabled = true;
    btn.innerHTML = '<span class="material-symbols-outlined">hourglass_empty</span>Salvando…';
    try {
        const fd = new FormData();
        fd.append('csrf_token', CSRF); fd.append('produto_id', pid);
        fd.append('quantidade', qtd);  fd.append('preco_unitario', preco);
        fd.append('vendedor_id', $('dv_vend').value||'');
        fd.append('observacao',  $('dv_obs').value.trim());
        const res = await fetch('api.php?acao=declarar_venda', {method:'POST', body:fd, credentials:'same-origin'});
        const j   = await res.json();
        if (j.ok) {
            const nome = $('dv_prod').options[$('dv_prod').selectedIndex].text.split(' (')[0];
            $('dv_succtxt').textContent = `${nome}  ·  ${qtd} un.  ·  ${R(qtd*preco)}`;
            goStep('dv',3);
            await loadVendas();
        } else alert('Erro: ' + (j.error||'Falha'));
    } catch(e) { alert('Erro: '+e.message); }
    btn.disabled = false;
    btn.innerHTML = 'Confirmar <span class="material-symbols-outlined">check_circle</span>';
});
$('dv_done').addEventListener('click', ()=>$('modDV').classList.remove('show'));

function openVV() {
    goStep('vv',1);
    $('vv_vend').value=''; $('vv_vendBox').style.display='none'; $('vv_next1').disabled=true; $('vv_obs').value='';
    $('modVV').classList.add('show');
}
$('btnVV').addEventListener('click', openVV);
$('vvCl').addEventListener('click', ()=>$('modVV').classList.remove('show'));
$('modVV').addEventListener('click', e=>{ if(e.target===$('modVV')) $('modVV').classList.remove('show') });

$('vv_vend').addEventListener('change', async ()=>{
    const id   = $('vv_vend').value;
    if (!id) { $('vv_vendBox').style.display='none'; $('vv_next1').disabled=true; return; }
    const nome = $('vv_vend').options[$('vv_vend').selectedIndex].textContent;
    $('vv_vendNome').textContent = nome;
    $('vv_vendMeta').textContent = 'Verificando produtos…';
    $('vv_vendBox').style.display = 'block';
    $('vv_next1').disabled = true;
    try {
        const res = await fetch(`api.php?acao=listar_produtos_vendedor&vendedor_id=${id}`, {credentials:'same-origin'});
        vvProds   = res.ok ? await res.json() : [];
        if (!Array.isArray(vvProds)) vvProds = [];
        const tot = vvProds.length;
        const uns = vvProds.reduce((s,p)=>s+(p.quantidade_alocada||0), 0);
        if (!tot) {
            $('vv_vendMeta').textContent = 'Nenhum produto alocado';
            $('vv_next1').disabled = true;
        } else {
            $('vv_vendMeta').textContent = `${tot} produto(s)  ·  ${uns} unidade(s) disponíveis`;
            $('vv_next1').disabled = false;
        }
    } catch(e) { $('vv_vendMeta').textContent='Erro ao carregar'; }
});

$('vv_next1').addEventListener('click', ()=>{
    const nome = $('vv_vend').options[$('vv_vend').selectedIndex].textContent;
    $('vv_p2title').textContent = `Produtos de ${nome}`;
    $('vv_count').textContent   = vvProds.length + ' produto(s)';
    renderVVList();
    goStep('vv',2);
});
$('vv_back').addEventListener('click', ()=> goStep('vv',1));

function renderVVList() {
    const d = $('vv_list');
    if (!vvProds.length) {
        d.innerHTML='<div class="empty"><span class="material-symbols-outlined">inventory</span><p>Nenhum produto alocado</p></div>';
        return;
    }
    d.innerHTML = vvProds.map(p=>`
        <div class="pac">
            <div class="pac-body">
                <div class="pac-nome">${esc(p.nome||'Produto')}</div>
                <div class="pac-sub">Cód: ${esc(p.codigo||'—')}  ·  ${R(p.preco)}</div>
            </div>
            <span class="pac-badge">${p.quantidade_alocada} un.</span>
            <input type="number" class="pac-input"
                data-id="${p.produto_id}"
                data-preco="${Number(p.preco||0).toFixed(2)}"
                data-max="${p.quantidade_alocada}"
                data-nome="${esc(p.nome||'Produto')}"
                min="0" max="${p.quantidade_alocada}" value="0">
        </div>`).join('');
    d.querySelectorAll('.pac-input').forEach(inp=>{
        inp.addEventListener('input', ()=>{
            let v = parseInt(inp.value)||0;
            if (v < 0) v=0;
            if (v > +inp.dataset.max) v=+inp.dataset.max;
            inp.value = v;
            inp.style.borderColor = v>0 ? 'var(--ok)' : '';
        });
    });
}

$('vv_conf').addEventListener('click', async ()=>{
    const vid = $('vv_vend').value;
    const obs = $('vv_obs').value.trim();
    const itens = [...$('vv_list').querySelectorAll('.pac-input')]
        .map(i=>({id:i.dataset.id, qtd:parseInt(i.value)||0, preco:i.dataset.preco, nome:i.dataset.nome, max:+i.dataset.max}))
        .filter(i=>i.qtd>0);
    if (!itens.length) return alert('Informe a quantidade de pelo menos 1 produto');
    const inv = itens.find(i=>i.qtd>i.max);
    if (inv) return alert(`"${inv.nome}" excede o alocado (${inv.max} un.)`);
    const btn = $('vv_conf');
    btn.disabled=true; btn.innerHTML='<span class="material-symbols-outlined">hourglass_empty</span>Registrando…';
    const ok=[], err=[];
    for (const item of itens) {
        try {
            const fd = new FormData();
            fd.append('csrf_token',CSRF); fd.append('produto_id',item.id);
            fd.append('quantidade',item.qtd); fd.append('preco_unitario',item.preco);
            fd.append('vendedor_id',vid); fd.append('observacao',obs);
            const res = await fetch('api.php?acao=declarar_venda',{method:'POST',body:fd,credentials:'same-origin'});
            const j   = await res.json();
            j.ok ? ok.push(item) : err.push({...item, e:j.error||'Falha'});
        } catch(e) { err.push({...item, e:e.message}); }
    }
    btn.disabled=false; btn.innerHTML='<span class="material-symbols-outlined">check_circle</span>Confirmar vendas';
    const rec = ok.reduce((s,i)=>s+i.qtd*parseFloat(i.preco),0);
    const uns = ok.reduce((s,i)=>s+i.qtd,0);
    $('vv_succtxt').textContent = `${ok.length} produto(s)  ·  ${uns} un.  ·  ${R(rec)}${err.length ? `  ·  ${err.length} erro(s)` : ''}`;
    $('vv_resumo').innerHTML = [
        ...ok.map(i=>`<div class="res-row"><span><span class="material-symbols-outlined" style="font-size:1rem;vertical-align:middle;color:var(--ok)">check_circle</span> <strong>${esc(i.nome)}</strong> × ${i.qtd}</span><span style="color:var(--ok);font-weight:700">${R(i.qtd*parseFloat(i.preco))}</span></div>`),
        ...err.map(i=>`<div class="res-row"><span style="color:var(--err)"><span class="material-symbols-outlined" style="font-size:1rem;vertical-align:middle">error</span> <strong>${esc(i.nome)}</strong>: ${esc(i.e)}</span></div>`)
    ].join('');
    goStep('vv',3);
    await loadVendas();
});
$('vv_done').addEventListener('click', ()=>$('modVV').classList.remove('show'));

$('btnCSV').addEventListener('click', ()=>{
    if (!allVendas.length) return alert('Nenhuma venda para exportar');
    const h = ['ID','Produto','Código','Origem','Vendedor','Qtd','Preço Unit.','Custo Unit.','Custo Total','Lucro Bruto','Total','Obs.','Data'];
    const rows = allVendas.map(v=>[
        v.id, `"${(v.produto_nome||'').replace(/"/g,'""')}"`, `"${(v.produto_codigo||'').replace(/"/g,'""')}"`,
        `"${originLabel(v.origem).replace(/"/g,'""')}"`, `"${(v.vendedor_nome||'').replace(/"/g,'""')}"`, v.quantidade, Number(v.preco_unitario||0).toFixed(2), Number(v.custo_unitario||0).toFixed(2),
        v.custo_informado ? Number(v.custo_total||0).toFixed(2) : '', v.custo_informado ? Number(v.lucro_bruto||0).toFixed(2) : '', Number(v.total||0).toFixed(2), `"${(v.observacao||'').replace(/"/g,'""')}"`, (v.criado_em||'').replace('T',' ').slice(0,19)
    ]);
    const csv = '\uFEFF' + [h,...rows].map(r=>r.join(';')).join('\n');
    const a = Object.assign(document.createElement('a'),{
        href: URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8;'})),
        download: `relatorio_${new Date().toISOString().slice(0,10)}.csv`
    });
    a.click();
});

function renderKPIs(v) {
    const rec  = v.reduce((s,x)=>s+Number(x.total||0),0);
    const un   = v.reduce((s,x)=>s+Number(x.quantidade||0),0);
    const tick = v.length ? rec/v.length : 0;
    const dias = dailyStats(v);
    const mediaDia = dias.length ? rec/dias.length : 0;
    const per  = $('fPer').options[$('fPer').selectedIndex].text.toLowerCase();
    const avgUnits = v.length ? (un/v.length).toFixed(1).replace('.',',') : '0';
    const custo = v.reduce((s,x)=>s+Number(x.custo_total||0),0);
    const lucro = v.reduce((s,x)=>s+Number(x.lucro_bruto||0),0);
    const margem = rec > 0 ? lucro/rec*100 : 0;
    $('kpis').innerHTML = `
    <div class="kpi kpi-primary">
        <div class="kpi-top">
            <div class="kpi-head"><div class="kpi-ico"><span class="material-symbols-outlined">receipt_long</span></div><div class="kpi-lbl">Vendas registradas</div></div>
            <span class="kpi-tag">${per}</span>
        </div>
        <div class="kpi-val">${v.length.toLocaleString('pt-BR')}</div>
        <div class="kpi-hint">${dias.length} dia(s) com movimento</div>
    </div>
    <div class="kpi kpi-primary g">
        <div class="kpi-top">
            <div class="kpi-head"><div class="kpi-ico"><span class="material-symbols-outlined">payments</span></div><div class="kpi-lbl">Receita total</div></div>
            <span class="kpi-tag">receita</span>
        </div>
        <div class="kpi-val">${R(rec)}</div>
        <div class="kpi-hint">Média ${R(mediaDia)}/dia ativo</div>
    </div>
    <div class="kpi v">
        <div class="kpi-top"><div class="kpi-head"><div class="kpi-ico"><span class="material-symbols-outlined">request_quote</span></div><div class="kpi-lbl">Custo dos produtos</div></div><span class="kpi-tag">CMV</span></div>
        <div class="kpi-val">${R(custo)}</div><div class="kpi-hint">Custo das unidades vendidas</div>
    </div>
    <div class="kpi c">
        <div class="kpi-top"><div class="kpi-head"><div class="kpi-ico"><span class="material-symbols-outlined">account_balance</span></div><div class="kpi-lbl">Lucro bruto</div></div><span class="kpi-tag">${margem.toFixed(1).replace('.',',')}%</span></div>
        <div class="kpi-val">${R(lucro)}</div><div class="kpi-hint">Faturamento menos custo dos produtos</div>
    </div>
    <div class="kpi o">
        <div class="kpi-top">
            <div class="kpi-head"><div class="kpi-ico"><span class="material-symbols-outlined">inventory_2</span></div><div class="kpi-lbl">Unidades vendidas</div></div>
            <span class="kpi-tag">giro</span>
        </div>
        <div class="kpi-val">${un.toLocaleString('pt-BR')}</div>
        <div class="kpi-hint">${avgUnits} un. por venda</div>
    </div>
    <div class="kpi r">
        <div class="kpi-top">
            <div class="kpi-head"><div class="kpi-ico"><span class="material-symbols-outlined">price_check</span></div><div class="kpi-lbl">Ticket médio</div></div>
            <span class="kpi-tag">média</span>
        </div>
        <div class="kpi-val">${R(tick)}</div>
        <div class="kpi-hint">Receita / número de vendas</div>
    </div>`;
}

function renderChart(vendas) {
    const perTxt = $('fPer').options[$('fPer').selectedIndex].text;
    const per = Number($('fPer').value || 30);
    const hoje = new Date();
    const parseDate = s => {
        const raw = String(s || '').replace(' ', 'T');
        const d = new Date(raw);
        return Number.isNaN(d.getTime()) ? null : d;
    };
    const dateKey = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
    const monthKey = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}`;
    const buckets = [];
    const byKey = {};
    const addBucket = (key, label, fullLabel) => {
        const bucket = {k:key, l:label, full:fullLabel || label, t:0, q:0, n:0};
        buckets.push(bucket);
        byKey[key] = bucket;
    };

    if (per === 365 || per === 0) {
        let start;
        let end = new Date(hoje.getFullYear(), hoje.getMonth(), 1);

        if (per === 365 || !vendas.length) {
            start = new Date(hoje.getFullYear(), hoje.getMonth() - 11, 1);
        } else {
            const dates = vendas.map(v => parseDate(v.criado_em)).filter(Boolean).sort((a,b)=>a-b);
            start = new Date(dates[0].getFullYear(), dates[0].getMonth(), 1);
            end = new Date(dates[dates.length-1].getFullYear(), dates[dates.length-1].getMonth(), 1);
        }

        for (let d = new Date(start); d <= end; d.setMonth(d.getMonth()+1)) {
            const snap = new Date(d);
            addBucket(
                monthKey(snap),
                snap.toLocaleDateString('pt-BR',{month:'short'}).replace('.', ''),
                snap.toLocaleDateString('pt-BR',{month:'long',year:'numeric'})
            );
        }
    } else {
        const totalDias = Math.max(per, 1);
        for (let i = totalDias - 1; i >= 0; i--) {
            const d = new Date(hoje);
            d.setHours(0,0,0,0);
            d.setDate(hoje.getDate() - i);
            const key = dateKey(d);
            addBucket(
                key,
                d.toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit'}),
                d.toLocaleDateString('pt-BR',{day:'2-digit',month:'long'})
            );
        }
    }

    vendas.forEach(v=>{
        const dt = parseDate(v.criado_em);
        if (!dt) return;
        const key = (per === 365 || per === 0) ? monthKey(dt) : dateKey(dt);
        const b = byKey[key];
        if (b) {
            b.t += Number(v.total||0);
            b.q += Number(v.quantidade||0);
            b.n += 1;
        }
    });

    const rec   = buckets.reduce((s,d)=>s+d.t, 0);

    $('cfR').textContent = R(rec);
    $('cfV').textContent = vendas.length + ' vendas';
    $('cfU').textContent = buckets.reduce((s,d)=>s+d.q, 0) + ' un.';
    $('chartTitle').textContent = `${per === 365 || per === 0 ? 'Receita mensal' : 'Receita diária'} — ${perTxt.toLowerCase()}`;
    $('chartNote').textContent = `${buckets.length} ponto(s) · ${vendas.length.toLocaleString('pt-BR')} registro(s)`;

    const el = $('salesChart');
    if (!el) return;

    if (typeof echarts === 'undefined') {
        el.innerHTML = `<div class="chart-empty"><span class="material-symbols-outlined">bar_chart</span><p>Não foi possível carregar o gráfico.</p></div>`;
        return;
    }

    if (!salesChart) {
        salesChart = echarts.init(el, null, {renderer: 'canvas'});

        if ('ResizeObserver' in window) {
            salesChartObserver = new ResizeObserver(() => salesChart && salesChart.resize());
            salesChartObserver.observe(el);
        }

        window.addEventListener('resize', () => {
            if (salesChart) salesChart.resize();
        });
    }

    const empty = buckets.every(d=>d.t===0);
    const labels = buckets.map(d=>d.l);
    const values = buckets.map(d=>Number(d.t.toFixed(2)));
    const units = buckets.map(d=>d.q);
    const sales = buckets.map(d=>d.n);
    const fullLabels = buckets.map(d=>d.full);
    const showEvery = labels.length > 70 ? 9 : labels.length > 45 ? 5 : labels.length > 24 ? 2 : 0;
    const chartStyles = getComputedStyle(document.documentElement);
    const chartInk = chartStyles.getPropertyValue('--ink').trim() || '#0f1c2e';
    const chartFaint = chartStyles.getPropertyValue('--faint').trim() || '#94a3b8';
    const chartBorder = chartStyles.getPropertyValue('--border').trim() || '#e4eaf2';
    const chartCard = chartStyles.getPropertyValue('--card').trim() || '#ffffff';

    salesChart.setOption({
        backgroundColor: 'transparent',
        color: ['#3b6ff5'],
        grid: {
            left: 12,
            right: 16,
            top: 26,
            bottom: labels.length > 16 ? 54 : 36,
            containLabel: true
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'shadow',
                shadowStyle: { color: 'rgba(59,111,245,.08)' }
            },
            borderWidth: 1,
            borderColor: chartBorder,
            backgroundColor: chartCard,
            textStyle: { color: chartInk, fontFamily: 'Inter, sans-serif', fontSize: 12 },
            padding: [8, 10],
            formatter: params => {
                const idx = params[0]?.dataIndex || 0;
                return `<strong>${esc(fullLabels[idx])}</strong><br>${R(values[idx])}<br>${units[idx]} un. · ${sales[idx]} venda(s)`;
            }
        },
        xAxis: {
            type: 'category',
            data: labels,
            axisTick: { show: false },
            axisLine: { lineStyle: { color: chartBorder } },
            axisLabel: {
                color: chartFaint,
                fontSize: 11,
                interval: showEvery,
                rotate: labels.length > 18 ? 35 : 0,
                hideOverlap: true
            }
        },
        yAxis: {
            type: 'value',
            axisLabel: {
                color: chartFaint,
                fontSize: 11,
                formatter: value => {
                    if (value >= 1000000) return 'R$ ' + (value / 1000000).toFixed(1).replace('.', ',') + ' mi';
                    if (value >= 1000) return 'R$ ' + (value / 1000).toFixed(0) + ' mil';
                    return 'R$ ' + Number(value || 0).toLocaleString('pt-BR');
                }
            },
            splitLine: { lineStyle: { color: chartBorder, type: 'dashed', opacity: .72 } }
        },
        series: [{
            name: 'Receita',
            type: 'bar',
            data: values,
            barMaxWidth: 34,
            barMinHeight: empty ? 0 : 3,
            itemStyle: {
                borderRadius: [7, 7, 2, 2],
                color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                    { offset: 0, color: '#7fa3ff' },
                    { offset: 1, color: '#3b6ff5' }
                ])
            },
            emphasis: {
                itemStyle: {
                    color: new echarts.graphic.LinearGradient(0, 0, 0, 1, [
                        { offset: 0, color: '#9bb7ff' },
                        { offset: 1, color: '#2a55d9' }
                    ])
                }
            }
        }],
        graphic: empty ? [{
            type: 'group',
            left: 'center',
            top: 'middle',
            children: [
                {
                    type: 'text',
                    style: {
                        text: 'Nenhuma venda no período',
                        fill: chartFaint,
                        font: '600 13px Inter, sans-serif'
                    }
                }
            ]
        }] : []
    }, true);

    requestAnimationFrame(() => {
        if (salesChart) salesChart.resize();
    });
}
window.addEventListener('uplar:themechange', () => {
    if (Array.isArray(allVendas)) renderChart(allVendas);
});

function renderTop(vendas) {
    const d = $('topProds'), mp = {};
    vendas.forEach(v=>{
        const n = v.produto_nome||'#'+v.produto_id;
        if (!mp[n]) mp[n]={q:0,r:0};
        mp[n].q += Number(v.quantidade||0);
        mp[n].r += Number(v.total||0);
    });
    const rank = Object.entries(mp).sort((a,b)=>b[1].r-a[1].r).slice(0,5);
    if (!rank.length) {
        d.innerHTML='<div class="empty"><span class="material-symbols-outlined">emoji_events</span><p>Nenhuma venda ainda</p></div>';
        return;
    }
    const cl = ['au','ag','br','',''];
    d.className = 'ti-grid';
    d.innerHTML = rank.map(([n,x],i)=>`
        <div class="ti">
            <div class="rk ${cl[i]}">${i+1}</div>
            <div class="ti-body">
                <div class="ti-name">${esc(n)}</div>
                <div class="ti-sub">${x.q} unidades</div>
            </div>
            <div class="ti-val">${R(x.r)}</div>
        </div>`).join('');
}

function renderHero(vendas) {
    const rec = vendas.reduce((s,x)=>s+Number(x.total||0),0);
    const prods = groupStats(vendas, v => v.produto_nome || '#'+v.produto_id);
    const vends = groupStats(vendas, v => v.vendedor_nome || 'Venda direta');
    const dias = dailyStats(vendas);
    const bestDay = dias.length ? [...dias].sort((a,b)=>b.r-a.r)[0] : null;

    $('heroRec').textContent = R(rec);
    $('heroBest').textContent = prods[0] ? shortName(prods[0].nome, 20) : '—';
    $('heroVend').textContent = vends[0] ? shortName(vends[0].nome, 20) : '—';
    $('heroPeak').textContent = bestDay
        ? new Date(bestDay.dia+'T00:00:00').toLocaleDateString('pt-BR',{day:'2-digit',month:'short'})
        : '—';
}

function renderVendorRank(vendas) {
    const d = $('vendRank');
    const rank = groupStats(vendas, v => v.vendedor_nome || 'Venda direta').slice(0,5);
    const max = rank.length ? rank[0].r : 0;
    if (!rank.length) {
        d.innerHTML = '<div class="empty"><span class="material-symbols-outlined">groups</span><p>Nenhuma venda no filtro</p></div>';
        return;
    }
    d.innerHTML = rank.map((x,i)=>`
        <div class="rank-row">
            <div class="rank-num">${i+1}</div>
            <div>
                <div class="rank-name">${esc(x.nome)}</div>
                <div class="rank-meta">${x.n} venda(s) · ${x.q} un. · ${pct(x.r, max)}% do líder</div>
            </div>
            <div class="rank-value">${R(x.r)}</div>
            <div class="bar-track"><span style="width:${Math.max(pct(x.r,max),4)}%"></span></div>
        </div>`).join('');
}

function renderProductMix(vendas) {
    const d = $('prodMix');
    const rec = vendas.reduce((s,x)=>s+Number(x.total||0),0);
    const grouped = new Map();
    vendas.forEach(v => {
        const id = String(v.produto_id || '');
        const key = id || (v.produto_nome || 'produto-sem-identificacao');
        if (!grouped.has(key)) grouped.set(key, {id, nome:v.produto_nome || '#'+v.produto_id, r:0, q:0});
        const item = grouped.get(key);
        item.r += Number(v.total || 0);
        item.q += Number(v.quantidade || 0);
    });
    const rank = [...grouped.values()].sort((a,b)=>b.r-a.r).slice(0,6);
    const productById = new Map(prodsCache.map(p => [String(p.id), p]));
    if (!rank.length) {
        d.innerHTML = '<div class="empty"><span class="material-symbols-outlined">inventory_2</span><p>Nenhum produto vendido no filtro</p></div>';
        return;
    }
    d.innerHTML = rank.map(x => {
        const image = productById.get(x.id)?.imagem;
        return `
            <div class="mix-row">
                ${image
                    ? `<button type="button" class="mix-thumb mix-thumb-button" data-image="${esc(image)}" data-name="${esc(x.nome)}" aria-label="Ampliar foto de ${esc(x.nome)}"><img src="${esc(image)}" alt="${esc(x.nome)}" loading="lazy" decoding="async"></button>`
                    : '<div class="mix-thumb"><span class="material-symbols-outlined">image_not_supported</span></div>'}
                <div class="mix-name">${esc(x.nome)}</div>
                <div class="mix-val">${pct(x.r, rec)}% · ${R(x.r)}</div>
                <div class="mix-bar"><span style="width:${Math.max(pct(x.r,rec),4)}%"></span></div>
            </div>`;
    }).join('');
}

function renderExecPulse(vendas) {
    const d = $('execPulse');
    const rec = vendas.reduce((s,x)=>s+Number(x.total||0),0);
    const un = vendas.reduce((s,x)=>s+Number(x.quantidade||0),0);
    const dias = dailyStats(vendas);
    const prods = groupStats(vendas, v => v.produto_nome || '#'+v.produto_id);
    const vends = groupStats(vendas, v => v.vendedor_nome || 'Venda direta');
    const bestDay = dias.length ? [...dias].sort((a,b)=>b.r-a.r)[0] : null;
    const direct = vendas.filter(x=>!x.vendedor_id).length;

    if (!vendas.length) {
        d.innerHTML = `
            <div class="pulse-item">
                <div class="pulse-ico"><span class="material-symbols-outlined">info</span></div>
                <div><div class="pulse-title">Sem movimento no filtro</div><div class="pulse-text">Troque o período, vendedor ou produto para analisar histórico disponível.</div></div>
            </div>`;
        return;
    }

    d.innerHTML = `
        <div class="pulse-item">
            <div class="pulse-ico"><span class="material-symbols-outlined">monitoring</span></div>
            <div><div class="pulse-title">${R(rec)} em ${vendas.length} venda(s)</div><div class="pulse-text">${un} unidade(s) vendidas; ticket médio de ${R(rec / vendas.length)}.</div></div>
        </div>
        <div class="pulse-item">
            <div class="pulse-ico"><span class="material-symbols-outlined">emoji_events</span></div>
            <div><div class="pulse-title">${esc(shortName(prods[0]?.nome || '—', 28))}</div><div class="pulse-text">Produto líder com ${prods[0]?.q || 0} unidade(s) e ${R(prods[0]?.r || 0)}.</div></div>
        </div>
        <div class="pulse-item">
            <div class="pulse-ico"><span class="material-symbols-outlined">person_check</span></div>
            <div><div class="pulse-title">${esc(shortName(vends[0]?.nome || '—', 28))}</div><div class="pulse-text">Melhor origem de venda. ${direct} venda(s) direta(s) no filtro.</div></div>
        </div>
        <div class="pulse-item">
            <div class="pulse-ico"><span class="material-symbols-outlined">calendar_month</span></div>
            <div><div class="pulse-title">${bestDay ? new Date(bestDay.dia+'T00:00:00').toLocaleDateString('pt-BR',{weekday:'short',day:'2-digit',month:'short'}) : '—'}</div><div class="pulse-text">Pico diário de ${R(bestDay?.r || 0)} em ${bestDay?.n || 0} venda(s).</div></div>
        </div>`;
}

function renderInsights(vendas) {
    renderHero(vendas);
    renderVendorRank(vendas);
    renderProductMix(vendas);
    renderExecPulse(vendas);
}

function renderTabela() {
    const body = $('tbody');
    $('totalBx').textContent = allVendas.length + ' registro' + (allVendas.length!==1?'s':'');
    const ini = (pg-1)*PP, pag = allVendas.slice(ini, ini+PP);
    if (!allVendas.length) {
        body.innerHTML = `<tr><td colspan="11"><div class="empty"><span class="material-symbols-outlined">receipt_long</span><p>Nenhuma venda encontrada neste período.</p></div></td></tr>`;
        $('pag').innerHTML = '';
        return;
    }
    body.innerHTML = pag.map(v=>`
        <tr>
            <td><span class="bx bl">#${esc(v.id)}</span></td>
            <td><strong>${esc(v.produto_nome||'—')}</strong><br><small style="color:var(--faint)">${esc(v.produto_codigo||'')}</small></td>
            <td><span class="bx bl">${esc(originLabel(v.origem))}</span></td>
            <td>${v.vendedor_nome ? `<span class="bx ok">${esc(v.vendedor_nome)}</span>` : '<span style="color:var(--faint)">—</span>'}</td>
            <td><strong>${esc(v.quantidade)}</strong></td>
            <td>${R(v.preco_unitario)}</td>
            <td>${v.custo_informado ? `<small style="color:var(--muted)">Custo ${R(v.custo_total)}</small><br><strong style="color:var(--ok)">Lucro ${R(v.lucro_bruto)}</strong>` : '<span class="bx" style="background:var(--warn-dim);color:#b45309">Custo não informado</span>'}</td>
            <td><strong style="color:var(--ok)">${R(v.total)}</strong></td>
            <td style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:var(--faint)">${esc(v.observacao||'—')}</td>
            <td style="white-space:nowrap;color:var(--muted);font-size:.76rem">${dFmt(v.criado_em)}</td>
            <td><button class="del-btn" onclick="delV(${v.id})"><span class="material-symbols-outlined">delete</span></button></td>
        </tr>`).join('');
    renderPag();
}

function renderPag() {
    const tot = Math.ceil(allVendas.length/PP);
    if (tot <= 1) { $('pag').innerHTML=''; return; }
    let h = `<button class="pg-btn" onclick="goP(${pg-1})" ${pg===1?'disabled':''}>‹</button>`;
    for (let i=1; i<=tot; i++) {
        if (tot>7 && i>2 && i<tot-1 && Math.abs(i-pg)>1) {
            if (i===3||i===tot-2) h+='<span style="padding:.3rem .25rem;color:var(--faint)">…</span>';
            continue;
        }
        h+=`<button class="pg-btn ${i===pg?'on':''}" onclick="goP(${i})">${i}</button>`;
    }
    h+=`<button class="pg-btn" onclick="goP(${pg+1})" ${pg===tot?'disabled':''}>›</button>`;
    $('pag').innerHTML = h;
}
function goP(n) { const t=Math.ceil(allVendas.length/PP); if(n<1||n>t)return; pg=n; renderTabela(); }

async function delV(id) {
    if (!confirm('Excluir esta venda? O estoque será restaurado.')) return;
    const fd = new FormData(); fd.append('csrf_token',CSRF); fd.append('id',id);
    const r  = await fetch('api.php?acao=excluir_venda',{method:'POST',body:fd,credentials:'same-origin'});
    const j  = await r.json();
    j.ok ? await loadVendas() : alert('Erro: '+(j.error||'Falha'));
}

async function loadSelects() {
    const [rv, rp, ro] = await Promise.all([
        fetch('api.php?acao=listar_vendedores',{credentials:'same-origin'}),
        fetch('api.php?acao=listar',{credentials:'same-origin'}),
        fetch('api.php?acao=listar_origens_vendas',{credentials:'same-origin'}),
    ]);
    vendsCache = rv.ok ? await rv.json() : [];
    prodsCache = rp.ok ? await rp.json() : [];
    if (!Array.isArray(vendsCache)) vendsCache=[];
    if (!Array.isArray(prodsCache)) prodsCache=[];
    const origins = ro.ok ? await ro.json() : {origens:{}};
    [$('fVend'), $('dv_vend'), $('vv_vend')].forEach(sel=>{
        const base = sel.options[0].outerHTML; sel.innerHTML = base;
        vendsCache.forEach(v=>{ const o=document.createElement('option'); o.value=v.id; o.textContent=v.nome; sel.appendChild(o); });
    });
    [$('fProd'), $('dv_prod')].forEach(sel=>{
        const base = sel.options[0].outerHTML; sel.innerHTML = base;
        prodsCache.forEach(p=>{ const o=document.createElement('option'); o.value=p.id; o.textContent=`${p.nome} (${p.quantidade} em estoque)`; sel.appendChild(o); });
    });
    const labels={vitrine:'Vitrine',venda_direta:'Venda direta',whatsapp:'WhatsApp',site_pro:'Site PRO',legacy:'Desconhecida'};
    const selected=$('fOrig').value;
    $('fOrig').innerHTML='<option value="">Todas</option>';
    Object.keys(origins.origens||{}).forEach(origin=>{
        if(!labels[origin])return;
        const option=document.createElement('option');option.value=origin;option.textContent=labels[origin];
        $('fOrig').appendChild(option);
    });
    $('fOrig').value=selected;
}

async function loadVendas() {
    const per = $('fPer').value;
    const vid = $('fVend').value;
    let url   = `api.php?acao=listar_vendas&periodo=${per}`;
    if (vid) url += `&vendedor_id=${vid}`;
    if (pid) url += `&produto_id=${pid}`;
    const r   = await fetch(url, {credentials:'same-origin'});
    allVendas = r.ok ? await r.json() : [];
    if (!Array.isArray(allVendas)) allVendas=[];
    pg = 1;
    renderKPIs(allVendas);
    renderChart(allVendas);
    renderTop(allVendas);
    renderInsights(allVendas);
    renderTabela();
}

/* ── Dashboard financeiro: usa snapshots preço/custo gravados na venda ── */
let financialChart, productChart, mixChart, granularity='day', productMetric='revenue', vendorMetric='revenue';
const moneyAxis=v=>v>=1000?'R$ '+(v/1000).toLocaleString('pt-BR',{maximumFractionDigits:1})+' mil':'R$ '+Number(v||0).toLocaleString('pt-BR');
function financial(v){const revenue=v.reduce((s,x)=>s+Number(x.total||0),0), known=v.filter(x=>x.custo_informado), cost=known.reduce((s,x)=>s+Number(x.custo_total||0),0), profit=known.reduce((s,x)=>s+Number(x.lucro_bruto||0),0);return {revenue,cost,profit,known,missing:v.length-known.length,coverage:revenue?known.reduce((s,x)=>s+Number(x.total||0),0)/revenue:0};}
function metricStyle(){const s=getComputedStyle(document.documentElement);return {ink:s.getPropertyValue('--ink').trim(),faint:s.getPropertyValue('--faint').trim(),border:s.getPropertyValue('--border').trim(),card:s.getPropertyValue('--card').trim()};}
function ensureChart(ref, id){const el=$(id);if(typeof echarts==='undefined'||!el)return null;if(!ref.chart){ref.chart=echarts.init(el,null,{renderer:'canvas'});new ResizeObserver(()=>ref.chart.resize()).observe(el);}return ref.chart;}
const chartRefs={sales:{chart:null},financial:{chart:null},product:{chart:null},mix:{chart:null}};
function makeBuckets(v){const per=$('fPer').value, wanted=granularity==='month'||per==='365'||per==='0'?'month':granularity==='week'?'week':'day', map=new Map();v.forEach(x=>{const d=new Date(String(x.criado_em).replace(' ','T'));if(Number.isNaN(+d))return;let key,label;if(wanted==='month'){key=`${d.getFullYear()}-${d.getMonth()}`;label=d.toLocaleDateString('pt-BR',{month:'short',year:'2-digit'});}else if(wanted==='week'){const monday=new Date(d);monday.setDate(d.getDate()-((d.getDay()+6)%7));key=monday.toISOString().slice(0,10);label='Sem. '+monday.toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit'});}else{key=d.toISOString().slice(0,10);label=d.toLocaleDateString('pt-BR',{day:'2-digit',month:'short'});}if(!map.has(key))map.set(key,{label,r:0,c:0,p:0,n:0,q:0});const b=map.get(key);b.r+=+x.total||0;b.q+=+x.quantidade||0;b.n++;if(x.custo_informado){b.c+=+x.custo_total||0;b.p+=+x.lucro_bruto||0;}});return [...map.values()];}
function renderKPIs(v){const f=financial(v),units=v.reduce((s,x)=>s+(+x.quantidade||0),0),ticket=v.length?f.revenue/v.length:0,margin=f.known.length&&f.known.reduce((s,x)=>s+(+x.total||0),0)?f.profit/f.known.reduce((s,x)=>s+(+x.total||0),0)*100:null;const card=(icon,label,value,hint,cls='')=>`<div class="kpi ${cls}"><div class="kpi-top"><div class="kpi-head"><div class="kpi-ico"><span class="material-symbols-outlined">${icon}</span></div><div class="kpi-lbl">${label}</div></div></div><div class="kpi-val">${value}</div><div class="kpi-hint">${hint}</div></div>`;$('kpis').innerHTML=card('payments','Receita',R(f.revenue),'Valor vendido')+card('account_balance','Lucro bruto',f.known.length?R(f.profit):'—',f.known.length?'Receita − custo informado':'Custo não informado','g')+card('percent','Margem bruta',margin===null?'—':margin.toFixed(1).replace('.',',')+'%',margin===null?'Aguardando custo':'Lucro sobre receita com custo','c')+card('receipt_long','Vendas',v.length.toLocaleString('pt-BR'),'Registros no período')+card('price_check','Ticket médio',R(ticket),'Receita / vendas','o')+card('inventory_2','Unidades',units.toLocaleString('pt-BR'),'Itens vendidos','v');$('resultCard').innerHTML=`<div class="result-row"><span>Receita</span><strong>${R(f.revenue)}</strong></div><div class="result-row"><span>− Custo dos produtos</span><strong>${f.known.length?R(f.cost):'—'}</strong></div><div class="result-row profit"><span>= Lucro bruto</span><strong>${f.known.length?R(f.profit):'—'}</strong></div><div class="result-margin">${margin===null?'Margem indisponível':margin.toFixed(1).replace('.',',')+'% de margem bruta'}</div>${f.missing?`<div class="result-warning">${f.missing} venda(s) sem custo cadastrado. Lucro calculado sobre ${(f.coverage*100).toFixed(0)}% da receita.</div>`:''}`;}
function renderChart(v){const b=makeBuckets(v),c=ensureChart(chartRefs.sales,'salesChart');if(!c)return;const st=metricStyle(),empty=!b.length;c.setOption({grid:{left:12,right:18,top:20,bottom:32,containLabel:true},tooltip:{trigger:'axis',borderColor:st.border,backgroundColor:st.card,textStyle:{color:st.ink},formatter:p=>{const x=b[p[0].dataIndex];return `<strong>${x.label}</strong><br>Receita&nbsp;&nbsp; ${R(x.r)}<br>Vendas&nbsp;&nbsp; ${x.n}<br>Unidades&nbsp;&nbsp; ${x.q}<br>Ticket&nbsp;&nbsp; ${R(x.n?x.r/x.n:0)}<br>Lucro bruto&nbsp;&nbsp; ${R(x.p)}`;}},xAxis:{type:'category',data:b.map(x=>x.label),axisTick:{show:false},axisLine:{lineStyle:{color:st.border}},axisLabel:{color:st.faint,hideOverlap:true}},yAxis:{type:'value',axisLabel:{color:st.faint,formatter:moneyAxis},splitLine:{lineStyle:{color:st.border,type:'dashed'}}},series:[{name:'Receita',type:'line',data:b.map(x=>x.r),showSymbol:b.length<16,symbolSize:6,smooth:.18,lineStyle:{width:2.5,color:'#3b6ff5'},itemStyle:{color:'#3b6ff5'},areaStyle:{color:'rgba(59,111,245,.12)'}}],graphic:empty?[{type:'text',left:'center',top:'middle',style:{text:'Nenhuma venda encontrada neste período.',fill:st.faint,font:'600 13px Inter'}}]:[]},true);}
function renderFinancialChart(v){const b=makeBuckets(v),c=ensureChart(chartRefs.financial,'financialChart');if(!c)return;const st=metricStyle();c.setOption({legend:{top:0,textStyle:{color:st.faint,fontSize:11}},grid:{left:12,right:28,top:35,bottom:32,containLabel:true},tooltip:{trigger:'axis',borderColor:st.border,backgroundColor:st.card,textStyle:{color:st.ink},formatter:p=>{const x=b[p[0].dataIndex],m=x.r?x.p/x.r*100:0;return `<strong>${x.label}</strong><br>Receita&nbsp; ${R(x.r)}<br>CMV&nbsp; ${R(x.c)}<br>Lucro bruto&nbsp; ${R(x.p)}<br>Margem&nbsp; ${m.toFixed(1).replace('.',',')}%`; }},xAxis:{type:'category',data:b.map(x=>x.label),axisTick:{show:false},axisLine:{lineStyle:{color:st.border}},axisLabel:{color:st.faint,hideOverlap:true}},yAxis:[{type:'value',axisLabel:{color:st.faint,formatter:moneyAxis},splitLine:{lineStyle:{color:st.border,type:'dashed'}}},{type:'value',min:0,max:100,axisLabel:{color:st.faint,formatter:'{value}%'},splitLine:{show:false}}],series:[{name:'Custo / CMV',type:'bar',stack:'resultado',data:b.map(x=>x.c),itemStyle:{color:'#94a3b8',borderRadius:[0,0,4,4]}},{name:'Lucro bruto',type:'bar',stack:'resultado',data:b.map(x=>x.p),itemStyle:{color:'#10b981',borderRadius:[5,5,0,0]}},{name:'Margem bruta',type:'line',yAxisIndex:1,data:b.map(x=>x.r?+(x.p/x.r*100).toFixed(1):0),symbolSize:5,lineStyle:{color:'#3b6ff5',width:2},itemStyle:{color:'#3b6ff5'}}]},true);}
function productStats(v){const m=new Map();v.forEach(x=>{const k=x.produto_id||x.produto_nome;if(!m.has(k))m.set(k,{name:x.produto_nome||'Produto',r:0,p:0,q:0,n:0,knownR:0});const z=m.get(k);z.r+=+x.total||0;z.q+=+x.quantidade||0;z.n++;if(x.custo_informado){z.p+=+x.lucro_bruto||0;z.knownR+=+x.total||0;}});return [...m.values()].map(x=>({...x,margin:x.knownR?x.p/x.knownR*100:null})).sort((a,b)=>b.r-a.r);}
function renderProducts(v){const a=productStats(v),key=productMetric==='revenue'?'r':productMetric==='profit'?'p':'q',c=ensureChart(chartRefs.product,'productChart');if(c){const r=[...a].sort((x,y)=>y[key]-x[key]).slice(0,8).reverse(),st=metricStyle();c.setOption({grid:{left:10,right:16,top:8,bottom:8,containLabel:true},tooltip:{trigger:'axis',axisPointer:{type:'shadow'},formatter:p=>`${esc(r[p[0].dataIndex].name)}<br>${productMetric==='units'?r[p[0].dataIndex].q+' unidades':R(r[p[0].dataIndex][key])}`},xAxis:{type:'value',show:false},yAxis:{type:'category',data:r.map(x=>shortName(x.name,22)),axisTick:{show:false},axisLine:{show:false},axisLabel:{color:st.muted||st.faint}},series:[{type:'bar',data:r.map(x=>x[key]),barMaxWidth:18,itemStyle:{color:productMetric==='profit'?'#10b981':'#3b6ff5',borderRadius:[0,5,5,0]}}]},true);}const largest=a[0],profit=[...a].sort((x,y)=>y.p-x.p)[0],margin=[...a].filter(x=>x.margin!==null).sort((x,y)=>y.margin-x.margin)[0];$('productHighlights').innerHTML=largest?`<div class="product-insight">Maior receita <strong>${esc(shortName(largest.name,18))} · ${R(largest.r)}</strong></div><div class="product-insight">Maior lucro <strong>${esc(shortName(profit.name,18))} · ${R(profit.p)}</strong></div>${margin?`<div class="product-insight">Maior margem <strong>${esc(shortName(margin.name,18))} · ${margin.margin.toFixed(1).replace('.',',')}%</strong></div>`:''}`:'<div class="empty"><p>Nenhum produto vendido.</p></div>'}
function renderVendorRank(v){const a=groupStats(v,x=>x.vendedor_nome||'Venda direta').map(x=>({...x,ticket:x.n?x.r/x.n:0})),key=vendorMetric==='revenue'?'r':vendorMetric==='sales'?'n':'ticket',rank=[...a].sort((x,y)=>y[key]-x[key]),max=rank[0]?.[key]||1;$('vendRank').innerHTML=rank.length?rank.slice(0,6).map((x,i)=>`<div class="rank-row"><div class="rank-num">${i+1}</div><div><div class="rank-name">${esc(x.nome)}</div><div class="rank-meta">${x.n} venda(s) · ${x.q} un.</div></div><div class="rank-value">${vendorMetric==='sales'?x.n: R(x[key])}</div><div class="bar-track"><span style="width:${Math.max(4,x[key]/max*100)}%"></span></div></div>`).join(''):'<div class="empty"><p>Somente venda direta no período.</p></div>';}
function renderMix(v){const a=productStats(v),top=a.slice(0,5),other=a.slice(5).reduce((s,x)=>s+x.r,0),data=top.map(x=>({name:shortName(x.name,18),value:x.r}));if(other)data.push({name:'Outros',value:other});const c=ensureChart(chartRefs.mix,'mixChart');if(c)c.setOption({tooltip:{trigger:'item',formatter:p=>`${p.name}<br>${R(p.value)} · ${p.percent}%`},legend:{bottom:0,type:'scroll',textStyle:{fontSize:10}},series:[{type:'pie',radius:['45%','70%'],center:['50%','43%'],label:{show:false},data,itemStyle:{borderColor:'#fff',borderWidth:3}}]},true);}
function renderExecPulse(v){const f=financial(v),a=productStats(v),margin=f.known.length?f.profit/f.known.reduce((s,x)=>s+(+x.total||0),0)*100:null;$('execPulse').innerHTML=!v.length?'<div class="pulse-item"><div><div class="pulse-title">Sem movimento no filtro</div><div class="pulse-text">Altere o período ou os filtros para analisar vendas.</div></div></div>':`<div class="pulse-item"><div class="pulse-ico"><span class="material-symbols-outlined">emoji_events</span></div><div><div class="pulse-title">Maior receita</div><div class="pulse-text">${esc(a[0]?.name||'—')} gerou ${R(a[0]?.r||0)}.</div></div></div>${margin!==null?`<div class="pulse-item"><div class="pulse-ico"><span class="material-symbols-outlined">percent</span></div><div><div class="pulse-title">Margem bruta</div><div class="pulse-text">${margin.toFixed(1).replace('.',',')}% sobre vendas com custo informado.</div></div></div>`:''}${f.missing?`<div class="pulse-item"><div class="pulse-ico"><span class="material-symbols-outlined">warning</span></div><div><div class="pulse-title">Cadastrar custo</div><div class="pulse-text">${f.missing} venda(s) não entram no lucro para evitar valor fictício.</div></div></div>`:''}`;}
function renderTop(){} function renderInsights(v){renderProducts(v);renderVendorRank(v);renderMix(v);renderExecPulse(v);} 
async function loadVendas(){const per=$('fPer').value,vid=$('fVend').value,pid=$('fProd').value,origem=$('fOrig').value;let url=`api.php?acao=listar_vendas&periodo=${['month','previous-month','custom'].includes(per)?0:per}`;if(per==='month'||per==='previous-month'||per==='custom'){const now=new Date(),pad=n=>String(n).padStart(2,'0');let a,b;if(per==='month'){a=new Date(now.getFullYear(),now.getMonth(),1);b=now;}else if(per==='previous-month'){a=new Date(now.getFullYear(),now.getMonth()-1,1);b=new Date(now.getFullYear(),now.getMonth(),0);}else{a=$('fInicio').value?new Date($('fInicio').value+'T00:00:00'):null;b=$('fFim').value?new Date($('fFim').value+'T00:00:00'):null;}if(a&&b){const fmt=d=>`${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;url+=`&inicio=${fmt(a)}&fim=${fmt(b)}`;}}if(vid)url+=`&vendedor_id=${vid}`;if(pid)url+=`&produto_id=${pid}`;if(origem)url+=`&origem=${encodeURIComponent(origem)}`;const r=await fetch(url,{credentials:'same-origin'});allVendas=r.ok?await r.json():[];if(!Array.isArray(allVendas))allVendas=[];pg=1;renderKPIs(allVendas);renderChart(allVendas);renderFinancialChart(allVendas);renderInsights(allVendas);renderTabela();}
document.querySelectorAll('#granularity button').forEach(b=>b.onclick=()=>{granularity=b.dataset.g;document.querySelectorAll('#granularity button').forEach(x=>x.classList.toggle('on',x===b));renderChart(allVendas);renderFinancialChart(allVendas);});document.querySelectorAll('#productMetric button').forEach(b=>b.onclick=()=>{productMetric=b.dataset.m;document.querySelectorAll('#productMetric button').forEach(x=>x.classList.toggle('on',x===b));renderProducts(allVendas);});document.querySelectorAll('#vendorMetric button').forEach(b=>b.onclick=()=>{vendorMetric=b.dataset.m;document.querySelectorAll('#vendorMetric button').forEach(x=>x.classList.toggle('on',x===b));renderVendorRank(allVendas);});$('fPer').addEventListener('change',()=>{$('customPeriod').hidden=$('fPer').value!=='custom';if($('fPer').value!=='custom')loadVendas();});$('fOrig').addEventListener('change',loadVendas);const loadCustom=()=>{if($('fInicio').value&&$('fFim').value)loadVendas();};$('fInicio').addEventListener('change',loadCustom);$('fFim').addEventListener('change',loadCustom);
/* Correção da série temporal: chave real -> ordenação -> label. */
let chartView = 'bar';
let granularityAuto = true;

function localDate(value) {
    const m = String(value || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}):(\d{2}))?/);
    return m ? new Date(+m[1], +m[2] - 1, +m[3], +(m[4] || 0), +(m[5] || 0), +(m[6] || 0)) : null;
}
function startOfDay(d) { return new Date(d.getFullYear(), d.getMonth(), d.getDate()); }
function startOfWeek(d) { const x=startOfDay(d); x.setDate(x.getDate()-((x.getDay()+6)%7)); return x; }
function startOfMonth(d) { return new Date(d.getFullYear(), d.getMonth(), 1); }
function addDays(d,n) { const x=new Date(d); x.setDate(x.getDate()+n); return x; }
function addMonths(d,n) { return new Date(d.getFullYear(), d.getMonth()+n, 1); }
function dayDiff(a,b) { return Math.round((startOfDay(b)-startOfDay(a))/86400000); }
function selectedRange(vendas) {
    const per=$('fPer').value, today=startOfDay(new Date());
    if (per === 'custom' && $('fInicio').value && $('fFim').value) return {start:localDate($('fInicio').value),end:localDate($('fFim').value)};
    if (per === 'month') return {start:new Date(today.getFullYear(),today.getMonth(),1),end:today};
    if (per === 'previous-month') return {start:new Date(today.getFullYear(),today.getMonth()-1,1),end:new Date(today.getFullYear(),today.getMonth(),0)};
    if (/^\d+$/.test(per) && +per > 0) return {start:addDays(today,-(+per-1)),end:today};
    const dates=vendas.map(x=>localDate(x.criado_em)).filter(Boolean).sort((a,b)=>a-b);
    return dates.length ? {start:startOfDay(dates[0]),end:startOfDay(dates[dates.length-1])} : {start:today,end:today};
}
function preferredGranularity(vendas) {
    const range=selectedRange(vendas), days=dayDiff(range.start,range.end)+1;
    return days<=45?'day':days<=180?'week':'month';
}
function labelFor(d, type, end) {
    if(type==='day') return d.toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit'});
    if(type==='week') return `${d.toLocaleDateString('pt-BR',{day:'2-digit',month:'short'}).replace('.','')}–${end.toLocaleDateString('pt-BR',{day:'2-digit',month:'short'}).replace('.','')}`;
    return d.toLocaleDateString('pt-BR',{month:'short',year:'2-digit'}).replace('.','') + '.';
}
function makeBuckets(vendas) {
    const type=granularity, range=selectedRange(vendas), map=new Map();
    const bucketStart=d=>type==='day'?startOfDay(d):type==='week'?startOfWeek(d):startOfMonth(d);
    const keyFor=d=>{const x=bucketStart(d);return x.getFullYear()*10000+(x.getMonth()+1)*100+x.getDate();};
    vendas.forEach(v=>{const date=localDate(v.criado_em);if(!date)return;const start=bucketStart(date),key=keyFor(date);if(!map.has(key))map.set(key,{key,start,r:0,c:0,p:0,n:0,q:0,knownRevenue:0});const b=map.get(key);b.r+=Number(v.total||0);b.q+=Number(v.quantidade||0);b.n++;if(v.custo_informado){b.c+=Number(v.custo_total||0);b.p+=Number(v.lucro_bruto||0);b.knownRevenue+=Number(v.total||0);}});
    const fillDays=type==='day' && dayDiff(range.start,range.end)<=180;
    const fillWeeks=type==='week' && dayDiff(range.start,range.end)<=730;
    const fillMonths=type==='month';
    if(fillDays||fillWeeks||fillMonths){let cursor=bucketStart(range.start),last=bucketStart(range.end);while(cursor<=last){const key=keyFor(cursor);if(!map.has(key))map.set(key,{key,start:new Date(cursor),r:0,c:0,p:0,n:0,q:0,knownRevenue:0});cursor=type==='day'?addDays(cursor,1):type==='week'?addDays(cursor,7):addMonths(cursor,1);}}
    return [...map.values()].sort((a,b)=>a.key-b.key).map(b=>{const end=type==='week'?addDays(b.start,6):b.start;return {...b,label:labelFor(b.start,type,end),full:type==='day'?b.start.toLocaleDateString('pt-BR',{day:'2-digit',month:'short',year:'numeric'}):type==='week'?`${b.start.toLocaleDateString('pt-BR',{day:'2-digit',month:'short',year:'numeric'})} a ${end.toLocaleDateString('pt-BR',{day:'2-digit',month:'short',year:'numeric'})}`:b.start.toLocaleDateString('pt-BR',{month:'long',year:'numeric'})};});
}
function renderChart(vendas) {
    const buckets=makeBuckets(vendas), chart=ensureChart(chartRefs.sales,'salesChart'); if(!chart)return;
    const st=metricStyle(), empty=!buckets.length, isLine=chartView==='line';
    const series={id:'revenue',name:'Receita',type:chartView,data:buckets.map(b=>b.r),animationDurationUpdate:180,universalTransition:true,
        itemStyle:isLine?{color:'#3b6ff5'}:{color:'#3b6ff5',borderRadius:[5,5,0,0]},
        ...(isLine?{showSymbol:buckets.length<=24,symbolSize:6,smooth:buckets.length>3?.12:false,lineStyle:{width:2.25,color:'#3b6ff5'},areaStyle:{color:'rgba(59,111,245,.12)'}}:{barMaxWidth:34,barMinHeight:0})};
    chart.setOption({animationDuration:220,grid:{left:12,right:18,top:20,bottom:42,containLabel:true},tooltip:{trigger:'axis',axisPointer:{type:isLine?'line':'shadow'},borderColor:st.border,backgroundColor:st.card,textStyle:{color:st.ink},formatter:p=>{const b=buckets[p[0]?.dataIndex||0],margin=b.knownRevenue?b.p/b.knownRevenue*100:null;return `<strong>${b.full}</strong><br>Receita&nbsp;&nbsp; ${R(b.r)}<br>Vendas&nbsp;&nbsp; ${b.n}<br>Unidades&nbsp;&nbsp; ${b.q}<br>Ticket médio&nbsp;&nbsp; ${R(b.n?b.r/b.n:0)}${margin===null?'':'<br>Lucro bruto&nbsp;&nbsp; '+R(b.p)+'<br>Margem&nbsp;&nbsp; '+margin.toFixed(1).replace('.',',')+'%'}`;}},xAxis:{type:'category',data:buckets.map(b=>b.label),axisTick:{show:false},axisLine:{lineStyle:{color:st.border}},axisLabel:{color:st.faint,hideOverlap:true,interval:buckets.length>36?Math.ceil(buckets.length/12):0}},yAxis:{type:'value',axisLabel:{color:st.faint,formatter:moneyAxis},splitLine:{lineStyle:{color:st.border,type:'dashed'}}},series:[series],graphic:empty?[{type:'text',left:'center',top:'middle',style:{text:'Nenhuma venda encontrada neste período.',fill:st.faint,font:'600 13px Inter'}}]:[]},{notMerge:true});
}
function renderFinancialChart(vendas) {
    const chart=ensureChart(chartRefs.financial,'financialChart');if(!chart)return;const f=financial(vendas),st=metricStyle();
    if(!f.known.length){chart.setOption({xAxis:[],yAxis:[],series:[],legend:{show:false},graphic:[{type:'group',left:'center',top:'middle',children:[{type:'text',style:{text:'Resultado financeiro indisponível',fill:st.ink,font:'700 15px Inter'}},{type:'text',top:27,style:{text:`Cadastre o preço de custo dos produtos vendidos para visualizar custo, lucro bruto e margem.${f.missing?' '+f.missing+' venda(s) ainda não possuem custo cadastrado.':''}`,fill:st.faint,font:'12px Inter',width:330,overflow:'break'}}]}]},{notMerge:true});return;}
    const b=makeBuckets(vendas);chart.setOption({legend:{top:0,textStyle:{color:st.faint,fontSize:11}},grid:{left:12,right:28,top:35,bottom:36,containLabel:true},tooltip:{trigger:'axis',borderColor:st.border,backgroundColor:st.card,textStyle:{color:st.ink},formatter:p=>{const x=b[p[0].dataIndex],m=x.knownRevenue?x.p/x.knownRevenue*100:null;return `<strong>${x.full}</strong><br>Receita&nbsp; ${R(x.r)}<br>CMV&nbsp; ${R(x.c)}<br>Lucro bruto&nbsp; ${R(x.p)}${m===null?'':'<br>Margem&nbsp; '+m.toFixed(1).replace('.',',')+'%'}${x.r>x.knownRevenue?'<br><small>Custo parcial neste período</small>':''}`;}},xAxis:{type:'category',data:b.map(x=>x.label),axisTick:{show:false},axisLine:{lineStyle:{color:st.border}},axisLabel:{color:st.faint,hideOverlap:true}},yAxis:[{type:'value',axisLabel:{color:st.faint,formatter:moneyAxis},splitLine:{lineStyle:{color:st.border,type:'dashed'}}},{type:'value',min:0,max:100,axisLabel:{color:st.faint,formatter:'{value}%'},splitLine:{show:false}}],series:[{name:'Custo / CMV',type:'bar',stack:'resultado',data:b.map(x=>x.c),itemStyle:{color:'#94a3b8',borderRadius:[0,0,4,4]}},{name:'Lucro bruto',type:'bar',stack:'resultado',data:b.map(x=>x.p),itemStyle:{color:'#10b981',borderRadius:[5,5,0,0]}},{name:'Margem bruta',type:'line',yAxisIndex:1,data:b.map(x=>x.knownRevenue?+(x.p/x.knownRevenue*100).toFixed(1):null),symbolSize:5,lineStyle:{color:'#3b6ff5',width:2},itemStyle:{color:'#3b6ff5'}}]},{notMerge:true});
}
function updateTemporalControlButtons(){document.querySelectorAll('#granularity button').forEach(x=>x.classList.toggle('on',x.dataset.g===granularity));document.querySelectorAll('#chartType button').forEach(x=>x.classList.toggle('on',x.dataset.view===chartView));}
function prepareTemporalDefault(vendas){if(granularityAuto){granularity=preferredGranularity(vendas);const points=makeBuckets(vendas).filter(x=>x.n>0).length;chartView=points<=3?'bar':'line';}updateTemporalControlButtons();}
document.querySelectorAll('#chartType button').forEach(button=>button.onclick=()=>{chartView=button.dataset.view;updateTemporalControlButtons();renderChart(allVendas);});
document.querySelectorAll('#granularity button').forEach(button=>button.onclick=()=>{granularityAuto=false;granularity=button.dataset.g;updateTemporalControlButtons();renderChart(allVendas);renderFinancialChart(allVendas);});
const loadVendasOriginal = loadVendas;
loadVendas = async function(){
    granularityAuto = true;
    await loadVendasOriginal();
    prepareTemporalDefault(allVendas);
    renderChart(allVendas);
    renderFinancialChart(allVendas);
};
async function init() { await loadSelects(); await loadVendas(); prepareTemporalDefault(allVendas); renderChart(allVendas); renderFinancialChart(allVendas); }

$('btnFilt').addEventListener('click', loadVendas);
window.onload = init;
</script>
</body>
</html>
