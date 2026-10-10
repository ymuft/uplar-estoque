<?php
session_start();
require 'db.php';
require 'helpers.php';
require_once __DIR__ . '/includes/tracking_runtime.php';
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php"); exit;
}
get_csrf_token();
$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$pdo  = get_pdo();
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user     = $stmt->fetch();
$is_admin = ($user && $user['role'] === 'admin');
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
require 'subscription.php';
$sub = check_and_block($user_id, $pdo); // redireciona para bloqueio.php se inativo
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Financeiro — Uplar</title>
<link rel="icon" href="/favicon.ico" type="image/x-icon">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"/>
<style>
/* ═══ TOKENS ════════════════════════════════════════════ */
:root{
    --p:#3b6ff5;--p-dark:#2a55d9;--p-dim:rgba(59,111,245,.08);--p-glow:rgba(59,111,245,.20);
    --ink:#0f1c2e;--muted:#5a6a7a;--faint:#94a3b8;--bg:#f4f6fb;--card:#fff;--border:#e4eaf2;
    --ok:#10b981;--ok-dim:rgba(16,185,129,.09);--warn:#f59e0b;--warn-dim:rgba(245,158,11,.09);
    --err:#ef4444;--err-dim:rgba(239,68,68,.09);--violet:#7c3aed;--violet-dim:rgba(124,58,237,.09);
    --sh:0 1px 4px rgba(15,28,46,.06),0 4px 16px rgba(15,28,46,.04);
    --sh-lift:0 4px 24px rgba(59,111,245,.14);--r:12px;
    --ch-bg:var(--card);--ch-grid:rgba(59,111,245,.07);--ch-txt:var(--muted);
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--ink);line-height:1.55;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none} button{font-family:inherit;cursor:pointer}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes popIn{from{opacity:0;transform:scale(.94)}to{opacity:1;transform:scale(1)}}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
.anim{opacity:0;animation:fadeUp .45s cubic-bezier(.22,1,.36,1) forwards}
.d1{animation-delay:.04s}.d2{animation-delay:.09s}.d3{animation-delay:.14s}
.d4{animation-delay:.19s}.d5{animation-delay:.24s}
.sk{border-radius:7px;background:linear-gradient(90deg,var(--bg) 25%,#eaeef4 50%,var(--bg) 75%);background-size:200%;animation:shimmer 1.3s infinite}

/* ═══ LAYOUT ════════════════════════════════════════════ */
.app{display:flex;min-height:100vh}
.sb{width:248px;background:var(--card);border-right:1px solid var(--border);position:fixed;left:0;top:0;height:100vh;overflow-y:auto;z-index:200;display:flex;flex-direction:column;transition:transform .3s cubic-bezier(.22,1,.36,1)}
.sb-head{padding:1.4rem 1.3rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.sb-logo-icon{font-size:2rem;color:var(--p)}
.sb-logo-txt{font-size:1.25rem;font-weight:700;letter-spacing:-.3px}
.sb-user{padding:1rem 1.3rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.8rem}
.sb-av{width:36px;height:36px;border-radius:9px;flex-shrink:0;background:linear-gradient(135deg,var(--p),#7ca0ff);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;box-shadow:0 3px 10px var(--p-glow)}
.sb-uname{font-weight:600;font-size:.85rem}
.sb-urole{font-size:.7rem;color:var(--faint);margin-top:1px}
.sb-nav{padding:.9rem .7rem;flex:1}
.sb-label{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--faint);padding:0 .7rem;margin:.9rem 0 .4rem}
.sb-label:first-child{margin-top:0}
.sb-link{display:flex;align-items:center;gap:.7rem;padding:.65rem .85rem;border-radius:9px;color:var(--muted);font-weight:500;font-size:.845rem;transition:all .18s;margin-bottom:2px}
.sb-link:hover{background:var(--p-dim);color:var(--p)}
.sb-link.on{background:var(--p-dim);color:var(--p);font-weight:600}
.sb-link .material-symbols-outlined{font-size:1.15rem;width:20px;text-align:center;flex-shrink:0}
.fin-subnav{padding:.3rem .7rem .7rem 2.7rem}
.fin-sub{display:flex;align-items:center;gap:.5rem;padding:.48rem .7rem;border-radius:8px;color:var(--muted);font-size:.79rem;font-weight:500;cursor:pointer;transition:all .18s;margin-bottom:1px;white-space:nowrap}
.fin-sub:hover{background:var(--p-dim);color:var(--p)}
.fin-sub.on{background:rgba(124,58,237,.1);color:var(--violet);font-weight:700}
.fin-sub .material-symbols-outlined{font-size:.98rem;flex-shrink:0}
.main{margin-left:248px;flex:1;padding:1.75rem 2rem;min-width:0}
.overlay{display:none;position:fixed;inset:0;background:rgba(15,28,46,.4);z-index:199;backdrop-filter:blur(3px)}
.overlay.on{display:block}
.ham{display:none;background:var(--card);border:1px solid var(--border);border-radius:9px;padding:.42rem;align-items:center;justify-content:center;box-shadow:var(--sh);color:var(--ink)}
.ham .material-symbols-outlined{font-size:1.3rem}

/* ═══ TOP BAR ═══════════════════════════════════════════ */
.top{display:flex;align-items:center;gap:.8rem;margin-bottom:1.3rem;flex-wrap:wrap;justify-content:space-between}
.top-l{display:flex;align-items:center;gap:.8rem}
.ptitle{font-size:1.35rem;font-weight:700;letter-spacing:-.4px}
.psub{color:var(--muted);font-size:.8rem;margin-top:1px}

/* ═══ VIEWS ══════════════════════════════════════════════ */
.view{display:none}
.view.on{display:block}

/* ═══ KPI STRIP ═════════════════════════════════════════ */
.kpis{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:.6rem;margin-bottom:1.1rem}
.kpi{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:.72rem .8rem;position:relative;overflow:hidden;cursor:default}
.kpi::before{content:"";position:absolute;top:0;left:0;right:0;height:2px;background:var(--p)}
.kpi.g::before{background:var(--ok)}.kpi.o::before{background:var(--warn)}.kpi.r::before{background:var(--err)}.kpi.v::before{background:var(--violet)}
.kpi:hover{border-color:var(--p);box-shadow:var(--sh)}
.kpi-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:.42rem}
.kpi-ico{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;background:var(--p-dim);color:var(--p)}
.kpi.g .kpi-ico{background:var(--ok-dim);color:var(--ok)}.kpi.o .kpi-ico{background:var(--warn-dim);color:var(--warn)}.kpi.r .kpi-ico{background:var(--err-dim);color:var(--err)}.kpi.v .kpi-ico{background:var(--violet-dim);color:var(--violet)}
.kpi-ico .material-symbols-outlined{font-size:1rem}
.kpi-tag{font-size:.64rem;font-weight:700;padding:.12rem .45rem;border-radius:20px;background:var(--p-dim);color:var(--p)}
.kpi.g .kpi-tag{background:var(--ok-dim);color:var(--ok)}.kpi.o .kpi-tag{background:var(--warn-dim);color:var(--warn)}.kpi.r .kpi-tag{background:var(--err-dim);color:var(--err)}.kpi.v .kpi-tag{background:var(--violet-dim);color:var(--violet)}
.kpi-val{font-size:1.18rem;font-weight:750;letter-spacing:-.3px;line-height:1.1;margin-bottom:.12rem}
.kpi-lbl{font-size:.68rem;color:var(--muted);font-weight:650}
.kpi-hint{font-size:.61rem;color:var(--faint);margin-top:.08rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* ═══ CARD ═══════════════════════════════════════════════ */
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:1.1rem 1.2rem;margin-bottom:.85rem}
.ch{display:flex;justify-content:space-between;align-items:center;margin-bottom:.85rem;padding-bottom:.65rem;border-bottom:1px solid var(--border);flex-wrap:wrap;gap:.5rem}
.ct{font-size:.87rem;font-weight:700}
.duo{display:grid;grid-template-columns:1.6fr 1fr;gap:.85rem;margin-bottom:.85rem}
.trio{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.85rem;margin-bottom:.85rem}

/* ═══ AREA CHART (SVG dark) ══════════════════════════════ */
.chart-dark{position:relative;background:linear-gradient(180deg,rgba(59,111,245,.06),rgba(255,255,255,0));border-radius:10px;padding:.9rem .9rem .6rem;overflow:hidden;margin-bottom:.7rem}.chart-dark::before{content:"";position:absolute;inset:0;background:linear-gradient(rgba(59,111,245,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(59,111,245,.06) 1px,transparent 1px);background-size:36px 36px;mask-image:linear-gradient(to bottom,rgba(0,0,0,.85),transparent);pointer-events:none}.chart-dark>*{position:relative;z-index:1}
.chart-dark svg{display:block;width:100%;overflow:visible}
.finance-chart{width:100%;height:190px;min-height:190px}
.finance-chart[data-empty="true"]::after{content:'Sem dados no período';display:grid;place-items:center;height:100%;color:var(--faint);font-size:.78rem}
.chart-legend{display:flex;gap:1.1rem;padding:.45rem 0 0;flex-wrap:wrap}
.legend-item{display:flex;align-items:center;gap:.4rem;font-size:.72rem;color:var(--ch-txt)}
.legend-dot{width:10px;height:3px;border-radius:2px;flex-shrink:0}

/* tooltip */
.chart-tooltip{
    position:fixed;pointer-events:none;z-index:999;
    background:rgba(17,24,39,.92);color:#fff;
    padding:.45rem .75rem;border-radius:8px;font-size:.72rem;font-weight:600;
    border:1px solid rgba(255,255,255,.1);backdrop-filter:blur(4px);
    box-shadow:0 8px 32px rgba(0,0,0,.4);
    white-space:nowrap;transition:opacity .12s;opacity:0;
}
.chart-tooltip.show{opacity:1}

/* ═══ TABS (Contas & Despesas) ══════════════════════════ */
.tab-bar{display:flex;gap:.3rem;margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:0}
.tab-btn{
    padding:.55rem 1rem;border-radius:8px 8px 0 0;border:none;background:transparent;
    font-size:.83rem;font-weight:600;color:var(--muted);cursor:pointer;
    transition:all .18s;border-bottom:2.5px solid transparent;margin-bottom:-1px;
    display:inline-flex;align-items:center;gap:.35rem;
}
.tab-btn:hover{color:var(--p);background:var(--p-dim)}
.tab-btn.on{color:var(--p);border-bottom-color:var(--p);background:transparent}
.tab-btn .material-symbols-outlined{font-size:1rem}
.tab-pane{display:none}
.tab-pane.on{display:block}

/* ═══ TABELA ═════════════════════════════════════════════ */
.tbl-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;margin:0 -1.2rem;padding:0 1.2rem}
.tbl{width:100%;min-width:560px;border-collapse:collapse;font-size:.82rem}
.tbl thead{background:var(--bg)}
.tbl th{text-align:left;padding:.6rem 1rem;font-size:.67rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);white-space:nowrap;border-bottom:1px solid var(--border)}
.tbl td{padding:.68rem 1rem;border-bottom:1px solid var(--border);vertical-align:middle}
.tbl tbody tr:hover{background:var(--bg)}
.tbl tbody tr:last-child td{border-bottom:none}

/* ═══ BADGES ═════════════════════════════════════════════ */
.bx{display:inline-flex;align-items:center;gap:.2rem;padding:.16rem .55rem;border-radius:20px;font-size:.71rem;font-weight:700;white-space:nowrap}
.bx.ok{background:var(--ok-dim);color:var(--ok)}.bx.bl{background:var(--p-dim);color:var(--p)}
.bx.wa{background:var(--warn-dim);color:#92400e}.bx.er{background:var(--err-dim);color:var(--err)}
.bx.vl{background:var(--violet-dim);color:var(--violet)}.bx.gy{background:#f1f5f9;color:var(--muted)}

/* ═══ PILLS ══════════════════════════════════════════════ */
.pills{display:flex;gap:.5rem;margin-bottom:1.1rem;flex-wrap:wrap;align-items:center}
.pill{display:inline-flex;align-items:center;gap:.4rem;padding:.5rem .95rem;border-radius:50px;font-size:.79rem;font-weight:600;border:none;transition:all .22s;white-space:nowrap}
.pill .material-symbols-outlined{font-size:.95rem}
.pill-p{background:var(--p);color:#fff;box-shadow:0 3px 12px var(--p-glow)}
.pill-p:hover{background:var(--p-dark);transform:translateY(-2px)}
.pill-g{background:var(--ok);color:#fff;box-shadow:0 3px 12px rgba(16,185,129,.25)}
.pill-g:hover{background:#059669;transform:translateY(-2px)}
.pill-o{background:var(--card);color:var(--muted);border:1px solid var(--border)}
.pill-o:hover{background:var(--p-dim);border-color:var(--p);color:var(--p);transform:translateY(-2px)}
.pill-wa{background:var(--warn);color:#fff;box-shadow:0 3px 12px rgba(245,158,11,.25)}
.pill-wa:hover{background:#d97706;transform:translateY(-2px)}

/* ═══ FILTROS ════════════════════════════════════════════ */
.frow{display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end}
.fg{display:flex;flex-direction:column;gap:.28rem}
.fg label{font-size:.67rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
.fg select,.fg input{padding:.52rem .85rem;border:1px solid var(--border);border-radius:9px;font-size:.82rem;background:var(--card);outline:none;font-family:inherit;color:var(--ink);transition:border-color .18s,box-shadow .18s}
.fg select:focus,.fg input:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}

/* ═══ AÇÕES NA TABELA ════════════════════════════════════ */
.acts{display:flex;gap:.3rem;align-items:center}
.act-btn{background:transparent;border:1px solid var(--border);border-radius:7px;padding:.28rem .5rem;color:var(--muted);font-size:.78rem;display:inline-flex;align-items:center;gap:.2rem;transition:all .18s;cursor:pointer}
.act-btn:hover{background:var(--p-dim);border-color:var(--p);color:var(--p)}
.act-btn.ok:hover{background:var(--ok-dim);border-color:var(--ok);color:var(--ok)}
.act-btn.er{color:var(--err)}
.act-btn.er:hover{background:var(--err-dim);border-color:var(--err)}
.act-btn .material-symbols-outlined{font-size:.88rem}

/* ═══ FLUXO CAIXA ════════════════════════════════════════ */
.saldo-box{background:linear-gradient(135deg,var(--p) 0%,#5c8aff 100%);border-radius:14px;padding:1.3rem 1.5rem;color:#fff;margin-bottom:.85rem}
.saldo-box .sb-label-text{font-size:.75rem;opacity:.75;font-weight:600;text-transform:uppercase;letter-spacing:.05em}
.saldo-box .sb-value{font-size:2rem;font-weight:700;letter-spacing:-.5px;margin:.2rem 0}
.saldo-stats{display:flex;gap:1rem;margin-top:.9rem}
.saldo-stat{flex:1;background:rgba(255,255,255,.15);border-radius:9px;padding:.6rem .8rem}
.saldo-stat .ss-v{font-size:.95rem;font-weight:700}
.saldo-stat .ss-l{font-size:.68rem;opacity:.75;margin-top:1px}
.mov-row{display:flex;align-items:center;gap:.75rem;padding:.6rem 0;border-bottom:1px solid var(--border)}
.mov-row:last-child{border-bottom:none}
.mov-ico{width:28px;height:28px;border-radius:8px;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.mov-ico.in{background:var(--ok-dim);color:var(--ok)}.mov-ico.out{background:var(--err-dim);color:var(--err)}
.mov-ico .material-symbols-outlined{font-size:1rem}
.mov-body{flex:1;min-width:0}
.mov-desc{font-size:.83rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.mov-meta{font-size:.7rem;color:var(--faint);margin-top:1px}
.mov-val{font-size:.88rem;font-weight:700;white-space:nowrap}
.mov-val.in{color:var(--ok)}.mov-val.out{color:var(--err)}

/* ═══ DRE ════════════════════════════════════════════════ */
.dre-row{display:flex;justify-content:space-between;align-items:center;padding:.62rem 1rem;border-radius:8px;cursor:default}
.dre-row:hover{background:var(--bg)}
.dre-row.sub{padding-left:2rem;font-size:.82rem;color:var(--muted)}
.dre-row.total{background:var(--p-dim);font-weight:700;border-radius:9px}
.dre-row.total.g{background:var(--ok-dim)}.dre-row.total.r{background:var(--err-dim)}
.dre-row.sep{border-top:1px solid var(--border);margin-top:.3rem;padding-top:.9rem}
.dre-lbl{font-size:.84rem;display:flex;align-items:center;gap:.4rem}
.dre-val{font-size:.9rem;font-weight:700}
.dre-val.neg{color:var(--err)}.dre-val.pos{color:var(--ok)}

/* Fechar mês banner */
.mes-fechado-banner{
    display:flex;align-items:center;gap:.75rem;
    background:linear-gradient(135deg,rgba(124,58,237,.12),rgba(124,58,237,.06));
    border:1.5px solid rgba(124,58,237,.3);border-radius:12px;
    padding:.9rem 1.2rem;margin-bottom:1rem;
}
.mes-fechado-banner .material-symbols-outlined{font-size:1.4rem;color:var(--violet)}
.mes-fechado-banner p{font-size:.82rem;color:var(--violet);font-weight:600}
.dre-custo-warn{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.3);border-radius:10px;padding:.8rem 1rem;font-size:.79rem;color:#92400e;display:flex;align-items:flex-start;gap:.45rem;line-height:1.5;margin-bottom:.9rem}
.dre-custo-warn .material-symbols-outlined{font-size:.95rem;flex-shrink:0;margin-top:1px}

/* Simulação */
.sim-row{display:flex;align-items:center;gap:.75rem;margin-bottom:.7rem}
.sim-label{font-size:.82rem;font-weight:600;min-width:140px}
.sim-slider{flex:1;accent-color:var(--p);height:4px;cursor:pointer}
.sim-val{font-size:.82rem;font-weight:700;min-width:52px;text-align:right;color:var(--p)}
.sim-resultado{background:var(--ch-bg);border-radius:12px;padding:1rem 1.1rem;margin-top:1rem}
.sim-res-row{display:flex;justify-content:space-between;padding:.4rem 0;border-bottom:1px solid rgba(255,255,255,.07);font-size:.82rem}
.sim-res-row:last-child{border-bottom:none;padding-top:.65rem;margin-top:.35rem}
.sim-res-lbl{color:rgba(255,255,255,.65)}
.sim-res-val{font-weight:700}

/* Comissão config */
.vend-comis{display:flex;align-items:center;gap:.75rem;padding:.65rem 0;border-bottom:1px solid var(--border)}
.vend-comis:last-child{border-bottom:none}
.vend-av{width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--p),#7ca0ff);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.78rem;flex-shrink:0}
.vend-comis-body{flex:1}
.vend-comis-nome{font-weight:600;font-size:.85rem}
.vend-comis-sub{font-size:.7rem;color:var(--faint)}
.comis-pct-input{width:72px;padding:.38rem .5rem;border:1.5px solid var(--border);border-radius:8px;font-size:.88rem;text-align:center;outline:none;font-family:inherit;transition:border-color .18s}
.comis-pct-input:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}

/* ═══ EMPTY ══════════════════════════════════════════════ */
.empty{text-align:center;padding:2.5rem 1rem;color:var(--faint)}
.empty .material-symbols-outlined{font-size:2.2rem;display:block;margin-bottom:.4rem;opacity:.3}
.empty p{font-size:.8rem}

/* ═══ PAGINATION ═════════════════════════════════════════ */
.pag{display:flex;justify-content:center;align-items:center;gap:.35rem;margin-top:.85rem;flex-wrap:wrap}
.pg-btn{padding:.38rem .7rem;border:1px solid var(--border);border-radius:7px;background:var(--card);cursor:pointer;font-size:.8rem;transition:all .18s;font-family:inherit;color:var(--ink)}
.pg-btn:hover{background:var(--p-dim);border-color:var(--p);color:var(--p)}
.pg-btn.on{background:var(--p);color:#fff;border-color:var(--p);font-weight:600}
.pg-btn:disabled{opacity:.35;cursor:not-allowed}

/* ═══ MODAL ══════════════════════════════════════════════ */
.modal{display:none;position:fixed;inset:0;background:rgba(15,28,46,.55);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(5px)}
.modal.show{display:flex}
.mbox{background:var(--card);border-radius:16px;width:90%;max-width:480px;padding:1.6rem;max-height:94vh;overflow-y:auto;box-shadow:0 24px 64px rgba(0,0,0,.22);animation:popIn .28s cubic-bezier(.22,1,.36,1) both}
.mbox.wide{max-width:560px}
.mh{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem}
.mh h2{font-size:.97rem;font-weight:700}
.mcl{font-size:1.6rem;cursor:pointer;color:var(--faint);line-height:1;transition:color .15s}
.mcl:hover{color:var(--ink)}
.fl{display:flex;flex-direction:column;gap:.28rem;margin-bottom:.8rem}
.fl label{font-size:.69rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
.fl input,.fl select,.fl textarea{padding:.65rem .9rem;border:1px solid var(--border);border-radius:9px;font-size:.86rem;font-family:inherit;transition:border-color .18s,box-shadow .18s;outline:none;color:var(--ink)}
.fl input:focus,.fl select:focus,.fl textarea:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.fg2{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}
.mbtn{background:var(--p);color:#fff;border:none;padding:.7rem;border-radius:10px;font-weight:700;width:100%;font-size:.85rem;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:.4rem;transition:all .22s;box-shadow:0 4px 14px var(--p-glow);margin-top:1rem}
.mbtn:hover{background:var(--p-dark);transform:translateY(-2px)}
.mbtn:disabled{background:var(--faint);box-shadow:none;transform:none;cursor:not-allowed}
.mbtn.ok{background:var(--ok);box-shadow:0 4px 14px rgba(16,185,129,.25)}
.mbtn.ok:hover{background:#059669}
.mbtn.er{background:var(--err);box-shadow:none}
.mbtn.vl{background:var(--violet);box-shadow:0 4px 14px var(--violet-dim)}
.mbtn.vl:hover{background:#6d28d9}
.mbtn-row{display:flex;gap:.6rem;margin-top:1rem}
.mbtn-row .mbtn{margin-top:0}

/* ═══ BOTTOM NAV ═════════════════════════════════════════ */
.bnav{display:none;position:fixed;bottom:0;left:0;right:0;background:var(--card);border-top:1px solid var(--border);z-index:300;padding:.35rem 0 calc(.35rem + env(safe-area-inset-bottom));box-shadow:0 -2px 16px rgba(15,28,46,.07)}
.bnav-inner{display:flex;justify-content:space-around}
.bnav-a{display:flex;flex-direction:column;align-items:center;gap:2px;padding:.35rem .6rem;border-radius:9px;color:var(--faint);font-size:.6rem;font-weight:600;transition:all .18s;flex:1}
.bnav-a .material-symbols-outlined{font-size:1.2rem}
.bnav-a.on{color:var(--violet)}

/* ═══ RESPONSIVE ═════════════════════════════════════════ */
@media(max-width:1100px){.kpis{grid-template-columns:repeat(3,1fr)}.duo,.trio{grid-template-columns:1fr}}
@media(max-width:860px){.sb{transform:translateX(-100%)}.sb.open{transform:translateX(0)}.main{margin-left:0}.ham{display:flex}}
@media(max-width:640px){.bnav{display:block}.main{padding:.9rem .9rem calc(70px + env(safe-area-inset-bottom))}.kpis{grid-template-columns:1fr 1fr;gap:.6rem}.kpi-val{font-size:1.2rem}.mbox{padding:1.2rem;width:96%;max-height:96vh}.fg2{grid-template-columns:1fr}.frow{flex-direction:column}.fg select,.fg input{width:100%}.saldo-stats{flex-direction:column;gap:.5rem}}
@media(max-width:380px){.kpis{grid-template-columns:1fr}}
</style>
<link rel="stylesheet" href="assets/app-theme.css?v=20260723">
<script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
<script src="assets/app-theme.js?v=20260723"></script>
</head>
<body>
<div class="app">
<div class="overlay" id="ov"></div>
<div class="chart-tooltip" id="chartTip"></div>

<!-- ═══ SIDEBAR ════════════════════════════════════════════ -->
<?php require __DIR__ . '/includes/sidebar.php'; ?>

<!-- ═══ MAIN ═══════════════════════════════════════════════ -->
<main class="main">

    <div class="top anim d1">
        <div class="top-l">
            <button class="ham" id="ham"><span class="material-symbols-outlined">menu</span></button>
            <div>
                <div class="ptitle" id="viewTitle"> Dashboard Financeiro</div>
                <div class="psub"   id="viewSub">Visão geral das suas finanças</div>
            </div>
        </div>
    </div>

    <!-- ══ DASHBOARD ══════════════════════════════════════════ -->
    <div class="view on anim d2" id="view-dashboard">
        <div style="display:flex;gap:.6rem;align-items:flex-end;margin-bottom:1rem;flex-wrap:wrap">
            <div class="fg"><label>Mês</label>
                <select id="dash-mes"><?php for($m=1;$m<=12;$m++){$s=$m==date('n')?'selected':'';echo "<option value='$m' $s>".date('M',mktime(0,0,0,$m,1))."</option>";}?></select>
            </div>
            <div class="fg"><label>Ano</label>
                <select id="dash-ano"><?php for($y=date('Y');$y>=date('Y')-3;$y--){$s=$y==date('Y')?'selected':'';echo "<option value='$y' $s>$y</option>";}?></select>
            </div>
            <button class="pill pill-o" onclick="loadDashboard()"><span class="material-symbols-outlined">refresh</span>Atualizar</button>
        </div>
        <div class="kpis" id="dash-kpis">
            <?php for($i=0;$i<6;$i++):?><div class="kpi"><div class="sk" style="height:1.5rem;width:60%;margin-bottom:.4rem"></div><div class="sk" style="height:.65rem;width:75%"></div></div><?php endfor;?>
        </div>
        <div class="duo">
            <div class="card" style="margin-bottom:0">
                <div class="ch"><span class="ct">Últimos 6 meses — Receita vs Despesa</span></div>
                <div class="chart-dark" id="dash-chart-wrap">
                    <div class="finance-chart" id="dash-chart" role="img" aria-label="Gráfico de receita, despesa e lucro dos últimos seis meses"></div>
                    <div class="chart-legend">
                        <div class="legend-item"><div class="legend-dot" style="background:#10b981"></div>Receita</div>
                        <div class="legend-item"><div class="legend-dot" style="background:#f59e0b"></div>Despesas</div>
                        <div class="legend-item"><div class="legend-dot" style="background:#8b5cf6"></div>Custo dos produtos</div>
                        <div class="legend-item"><div class="legend-dot" style="background:#3b6ff5"></div>Lucro bruto</div>
                        <div class="legend-item"><div class="legend-dot" style="background:#7c3aed"></div>Lucro líquido</div>
                    </div>
                </div>
            </div>
            <div class="card" style="margin-bottom:0">
                <div class="ch"><span class="ct"> Top produtos do mês</span></div>
                <div id="dash-top"></div>
            </div>
        </div>
        <div class="trio">
            <div class="card" style="margin-bottom:0"><div class="ch"><span class="ct"> A Receber</span></div><div id="dash-cr-alert"></div></div>
            <div class="card" style="margin-bottom:0"><div class="ch"><span class="ct"> A Pagar</span></div><div id="dash-cp-alert"></div></div>
            <div class="card" style="margin-bottom:0"><div class="ch"><span class="ct"> Comissões</span></div><div id="dash-comis-alert"></div></div>
        </div>
    </div>

    <!-- ══ FLUXO DE CAIXA ══════════════════════════════════════ -->
    <div class="view" id="view-fluxo">
        <div class="saldo-box">
            <div class="sb-label-text">Saldo do período</div>
            <div class="sb-value" id="fluxo-saldo-val">R$ —</div>
            <div class="saldo-stats">
                <div class="saldo-stat"><div class="ss-v" id="fluxo-in">—</div><div class="ss-l">↑ Entradas</div></div>
                <div class="saldo-stat"><div class="ss-v" id="fluxo-out">—</div><div class="ss-l">↓ Saídas</div></div>
            </div>
        </div>
        <div class="card">
            <div class="ch">
                <span class="ct">Movimentações</span>
                <div class="frow">
                    <div class="fg"><label>Período</label>
                        <select id="fluxo-per" onchange="loadFluxo()">
                            <option value="7">7 dias</option><option value="30" selected>30 dias</option>
                            <option value="90">90 dias</option><option value="0">Tudo</option>
                        </select>
                    </div>
                    <button class="pill pill-o" onclick="exportFluxoCSV()"><span class="material-symbols-outlined">download</span>CSV</button>
                </div>
            </div>
            <div id="fluxo-list"></div>
        </div>
    </div>

    <!-- ══ CONTAS & DESPESAS (unificado com 3 abas) ════════════ -->
    <div class="view" id="view-contas">
        <div class="tab-bar">
            <button class="tab-btn on" data-tab="cr" onclick="switchTab('cr',this)"><span class="material-symbols-outlined">request_quote</span>A Receber</button>
            <button class="tab-btn" data-tab="cp" onclick="switchTab('cp',this)"><span class="material-symbols-outlined">receipt_long</span>A Pagar</button>
            <button class="tab-btn" data-tab="desp" onclick="switchTab('desp',this)"><span class="material-symbols-outlined">trending_down</span>Despesas</button>
        </div>

        <!-- ABA: A RECEBER -->
        <div class="tab-pane on" id="tab-cr">
            <div class="pills">
                <button class="pill pill-g" onclick="openModal('mod-cr')"><span class="material-symbols-outlined">add</span>Nova Conta</button>
                <button class="pill pill-o cr-f active-f" data-s="" onclick="setCrFilter(this)"><span class="material-symbols-outlined">list</span>Todas</button>
                <button class="pill pill-o cr-f" data-s="pendente" onclick="setCrFilter(this)">Pendentes</button>
                <button class="pill pill-o cr-f" data-s="atrasado" onclick="setCrFilter(this)">Atrasadas</button>
                <button class="pill pill-o cr-f" data-s="pago" onclick="setCrFilter(this)">Pagas</button>
                <span class="bx bl" id="cr-count" style="margin-left:auto">—</span>
            </div>
            <div class="card">
                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead><tr><th>#</th><th>Descrição</th><th>Cliente</th><th>Valor</th><th>Vencimento</th><th>Status</th><th></th></tr></thead>
                        <tbody id="cr-body"></tbody>
                    </table>
                </div>
                <div class="pag" id="cr-pag"></div>
            </div>
        </div>

        <!-- ABA: A PAGAR -->
        <div class="tab-pane" id="tab-cp">
            <div class="pills">
                <button class="pill pill-p" onclick="openModal('mod-cp')"><span class="material-symbols-outlined">add</span>Nova Conta</button>
                <button class="pill pill-o cp-f active-f" data-s="" onclick="setCpFilter(this)"><span class="material-symbols-outlined">list</span>Todas</button>
                <button class="pill pill-o cp-f" data-s="pendente" onclick="setCpFilter(this)">Pendentes</button>
                <button class="pill pill-o cp-f" data-s="atrasado" onclick="setCpFilter(this)">Atrasadas</button>
                <button class="pill pill-o cp-f" data-s="pago" onclick="setCpFilter(this)">Pagas</button>
                <span class="bx er" id="cp-count" style="margin-left:auto">—</span>
            </div>
            <div class="card">
                <div class="tbl-wrap">
                    <table class="tbl">
                        <thead><tr><th>#</th><th>Descrição</th><th>Fornecedor</th><th>Categoria</th><th>Valor</th><th>Vencimento</th><th>Status</th><th></th></tr></thead>
                        <tbody id="cp-body"></tbody>
                    </table>
                </div>
                <div class="pag" id="cp-pag"></div>
            </div>
        </div>

        <!-- ABA: DESPESAS -->
        <div class="tab-pane" id="tab-desp">
            <div class="pills">
                <button class="pill pill-p" onclick="openModal('mod-despesa')"><span class="material-symbols-outlined">add</span>Nova Despesa</button>
                <div class="fg" style="margin-left:auto"><label>Período</label>
                    <select id="desp-per" onchange="loadDespesas()"><option value="30">30 dias</option><option value="90">90 dias</option><option value="365">1 ano</option><option value="0">Tudo</option></select>
                </div>
                <div class="fg"><label>Tipo</label>
                    <select id="desp-tipo" onchange="loadDespesas()"><option value="">Todos</option><option value="fixa">Fixa</option><option value="variavel">Variável</option></select>
                </div>
                <span class="bx er" id="desp-count">—</span>
            </div>
            <div class="duo">
                <div class="card" style="margin-bottom:0">
                    <div class="tbl-wrap">
                        <table class="tbl">
                            <thead><tr><th>Descrição</th><th>Categoria</th><th>Tipo</th><th>Valor</th><th>Data</th><th>Status</th><th></th></tr></thead>
                            <tbody id="desp-body"></tbody>
                        </table>
                    </div>
                    <div class="pag" id="desp-pag"></div>
                </div>
                <div class="card" style="margin-bottom:0">
                    <div class="ch"><span class="ct">Por Categoria</span></div>
                    <div id="desp-cats"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ══ RECEITAS ════════════════════════════════════════════ -->
    <div class="view" id="view-receitas">
        <div class="pills">
            <button class="pill pill-g" onclick="openModal('mod-receita')"><span class="material-symbols-outlined">add</span>Nova Receita</button>
            <div class="fg" style="margin-left:auto"><label>Período</label>
                <select id="rec-per" onchange="loadReceitas()"><option value="30">30 dias</option><option value="90">90 dias</option><option value="365">1 ano</option><option value="0">Tudo</option></select>
            </div>
            <span class="bx ok" id="rec-count">—</span>
        </div>
        <div class="card">
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Descrição</th><th>Categoria</th><th>Valor</th><th>Data</th><th>Status</th><th></th></tr></thead>
                    <tbody id="rec-body"></tbody>
                </table>
            </div>
            <div class="pag" id="rec-pag"></div>
        </div>
    </div>

    <!-- ══ COMISSÕES ═══════════════════════════════════════════ -->
    <div class="view" id="view-comissoes">
        <div class="duo">
            <div class="card" style="margin-bottom:0">
                <div class="ch"><span class="ct">️ % por Vendedor</span></div>
                <div id="comis-config"></div>
            </div>
            <div class="card" style="margin-bottom:0">
                <div class="ch">
                    <span class="ct"> Vendas do período</span>
                    <div class="fg"><label>Período</label>
                        <select id="comis-per" onchange="loadComissoes()">
                            <option value="30">30 dias</option><option value="90">90 dias</option><option value="365">1 ano</option>
                        </select>
                    </div>
                </div>
                <div id="comis-vendas"></div>
            </div>
        </div>
        <div class="card">
            <div class="ch">
                <span class="ct">Histórico de Comissões</span>
                <button class="pill pill-p" onclick="openModal('mod-comissao')"><span class="material-symbols-outlined">add</span>Registrar</button>
            </div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Vendedor</th><th>Período</th><th>Vendas</th><th>%</th><th>Comissão</th><th>Status</th><th></th></tr></thead>
                    <tbody id="comis-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══ DRE ══════════════════════════════════════════════════ -->
    <div class="view" id="view-dre">
        <!-- controles no topo -->
        <div style="display:flex;gap:.6rem;align-items:flex-end;margin-bottom:1rem;flex-wrap:wrap">
            <div class="fg"><label>Mês</label>
                <select id="dre-mes"><?php for($m=1;$m<=12;$m++){$s=$m==date('n')?'selected':'';echo "<option value='$m' $s>".date('F',mktime(0,0,0,$m,1))."</option>";}?></select>
            </div>
            <div class="fg"><label>Ano</label>
                <select id="dre-ano"><?php for($y=date('Y');$y>=date('Y')-3;$y--){$s=$y==date('Y')?'selected':'';echo "<option value='$y' $s>$y</option>";}?></select>
            </div>
            <button class="pill pill-p" id="btn-gerar-dre" onclick="gerarDRE()">
                <span class="material-symbols-outlined">refresh</span>Gerar DRE
            </button>
            <button class="pill pill-wa" id="btn-fechar-mes" onclick="confirmarFecharMes()">
                <span class="material-symbols-outlined">lock</span>Fechar Mês
            </button>
            <button class="pill pill-o" onclick="openModal('mod-simular')">
                <span class="material-symbols-outlined">tune</span>Simular Cenário
            </button>
        </div>
        <!-- banner "mês fechado" (oculto por padrão) -->
        <div class="mes-fechado-banner" id="banner-fechado" style="display:none">
            <span class="material-symbols-outlined">lock</span>
            <div>
                <p id="banner-fechado-txt">Este mês está fechado.</p>
                <p style="font-size:.75rem;opacity:.7;margin-top:2px">Novos lançamentos precisam ser registrados como <strong>Ajuste</strong>.</p>
            </div>
        </div>
        <!-- conteúdo DRE -->
        <div class="duo">
            <div class="card" style="margin-bottom:0">
                <div class="ch"><span class="ct" id="dre-periodo-titulo"> DRE</span></div>
                <div id="dre-body"><div class="empty"><span class="material-symbols-outlined">analytics</span><p>Selecione mês/ano e clique em Gerar DRE</p></div></div>
            </div>
            <div class="card" style="margin-bottom:0">
                <div class="ch"><span class="ct">Despesas por Categoria</span></div>
                <div id="dre-cats"><div class="empty"><span class="material-symbols-outlined">pie_chart</span><p>Aguardando dados</p></div></div>
            </div>
        </div>
    </div>

    <!-- ══ RELATÓRIOS ══════════════════════════════════════════ -->
    <div class="view" id="view-relatorios">
        <div style="display:flex;gap:.6rem;align-items:flex-end;margin-bottom:1rem;flex-wrap:wrap">
            <div class="fg"><label>Período</label>
                <select id="rel-per" onchange="loadRelatorios()">
                    <option value="30">30 dias</option><option value="90" selected>90 dias</option>
                    <option value="180">6 meses</option><option value="365">1 ano</option>
                </select>
            </div>
        </div>
        <div class="card">
            <div class="ch"><span class="ct"> Receita por período</span></div>
            <div class="chart-dark">
                <div class="finance-chart" id="rel-chart" role="img" aria-label="Gráfico de receita por período"></div>
                <div class="chart-legend">
                    <div class="legend-item"><div class="legend-dot" style="background:#10b981"></div>Receita</div>
                </div>
            </div>
        </div>
        <div class="duo">
            <div class="card" style="margin-bottom:0">
                <div class="ch"><span class="ct"> Top Produtos</span></div>
                <div id="rel-top-prods"></div>
            </div>
            <div class="card" style="margin-bottom:0">
                <div class="ch"><span class="ct"> Despesas por Categoria</span></div>
                <div id="rel-desp-cat"></div>
            </div>
        </div>
        <div class="card">
            <div class="ch"><span class="ct"> Vendedores — Ticket Médio</span></div>
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead><tr><th>Vendedor</th><th>Vendas</th><th>Receita</th><th>Ticket Médio</th></tr></thead>
                    <tbody id="rel-vend-body"></tbody>
                </table>
            </div>
        </div>
    </div>

</main>
</div><!-- .app -->

<nav class="bnav"><div class="bnav-inner">
    <a href="estoque.php"    class="bnav-a"><span class="material-symbols-outlined">dashboard</span>Início</a>
    <a href="relatorio.php"  class="bnav-a"><span class="material-symbols-outlined">bar_chart</span>Vendas</a>
    <a href="financeiro.php" class="bnav-a on"><span class="material-symbols-outlined">account_balance_wallet</span>Financeiro</a>
    <a href="produtos.php"   class="bnav-a"><span class="material-symbols-outlined">inventory</span>Produtos</a>
</div></nav>

<!-- ═══ MODAIS ══════════════════════════════════════════════════ -->

<!-- Conta a Receber -->
<div class="modal" id="mod-cr">
    <div class="mbox">
        <div class="mh"><h2 id="cr-modal-title"> Nova Conta a Receber</h2><span class="mcl" onclick="closeModal('mod-cr')">close</span></div>
        <input type="hidden" id="cr-id">
        <div class="fl"><label>Descrição *</label><input type="text" id="cr-desc" placeholder="Ex: Venda parcelada"></div>
        <div class="fg2">
            <div class="fl"><label>Cliente</label><input type="text" id="cr-cli" placeholder="Nome"></div>
            <div class="fl"><label>Valor (R$) *</label><input type="number" id="cr-val" step="0.01" min="0"></div>
        </div>
        <div class="fg2">
            <div class="fl"><label>Vencimento *</label><input type="date" id="cr-venc"></div>
            <div class="fl"><label>Obs.</label><input type="text" id="cr-obs" placeholder="Opcional"></div>
        </div>
        <button class="mbtn ok" onclick="saveCR()">Salvar <span class="material-symbols-outlined">save</span></button>
    </div>
</div>

<!-- Baixa CR -->
<div class="modal" id="mod-cr-baixa">
    <div class="mbox">
        <div class="mh"><h2> Registrar Recebimento</h2><span class="mcl" onclick="closeModal('mod-cr-baixa')">close</span></div>
        <input type="hidden" id="crb-id">
        <div class="fl"><label>Data Recebimento *</label><input type="date" id="crb-data"></div>
        <button class="mbtn ok" onclick="pagarCR()">Confirmar <span class="material-symbols-outlined">check_circle</span></button>
    </div>
</div>

<!-- Conta a Pagar -->
<div class="modal" id="mod-cp">
    <div class="mbox">
        <div class="mh"><h2 id="cp-modal-title"> Nova Conta a Pagar</h2><span class="mcl" onclick="closeModal('mod-cp')">close</span></div>
        <input type="hidden" id="cp-id">
        <div class="fl"><label>Descrição *</label><input type="text" id="cp-desc" placeholder="Ex: Aluguel"></div>
        <div class="fg2">
            <div class="fl"><label>Fornecedor</label><input type="text" id="cp-forn" placeholder="Nome"></div>
            <div class="fl"><label>Categoria</label>
                <select id="cp-cat"><option>Aluguel</option><option>Energia</option><option>Internet</option><option>Fornecedor</option><option>Salário</option><option>Frete</option><option>Manutenção</option><option>Marketing</option><option>Outros</option></select>
            </div>
        </div>
        <div class="fg2">
            <div class="fl"><label>Valor (R$) *</label><input type="number" id="cp-val" step="0.01" min="0"></div>
            <div class="fl"><label>Vencimento *</label><input type="date" id="cp-venc"></div>
        </div>
        <div class="fl"><label>Obs.</label><textarea id="cp-obs" rows="2" placeholder="Opcional"></textarea></div>
        <button class="mbtn" onclick="saveCP()">Salvar <span class="material-symbols-outlined">save</span></button>
    </div>
</div>

<!-- Baixa CP -->
<div class="modal" id="mod-cp-baixa">
    <div class="mbox">
        <div class="mh"><h2> Registrar Pagamento</h2><span class="mcl" onclick="closeModal('mod-cp-baixa')">close</span></div>
        <input type="hidden" id="cpb-id">
        <div class="fl"><label>Data Pagamento *</label><input type="date" id="cpb-data"></div>
        <button class="mbtn ok" onclick="pagarCP()">Confirmar <span class="material-symbols-outlined">check_circle</span></button>
    </div>
</div>

<!-- Despesa -->
<div class="modal" id="mod-despesa">
    <div class="mbox">
        <div class="mh"><h2 id="desp-modal-title"> Nova Despesa</h2><span class="mcl" onclick="closeModal('mod-despesa')">close</span></div>
        <input type="hidden" id="desp-id">
        <div class="fl"><label>Descrição *</label><input type="text" id="desp-desc" placeholder="Ex: Conta de luz"></div>
        <div class="fg2">
            <div class="fl"><label>Categoria</label>
                <select id="desp-cat"><option>Aluguel</option><option>Energia</option><option>Internet</option><option>Salário</option><option>Frete</option><option>Manutenção</option><option>Marketing</option><option>Alimentação</option><option>Transporte</option><option>Outros</option></select>
            </div>
            <div class="fl"><label>Tipo</label>
                <select id="desp-tipo-modal"><option value="variavel">Variável</option><option value="fixa">Fixa</option></select>
            </div>
        </div>
        <div class="fg2">
            <div class="fl"><label>Valor (R$) *</label><input type="number" id="desp-val" step="0.01" min="0"></div>
            <div class="fl"><label>Data</label><input type="date" id="desp-data"></div>
        </div>
        <div class="fl"><label>Status</label><select id="desp-status"><option value="pago">Pago</option><option value="pendente">Pendente</option></select></div>
        <div class="fl"><label>Obs.</label><textarea id="desp-obs" rows="2" placeholder="Opcional"></textarea></div>
        <button class="mbtn er" onclick="saveDespesa()">Salvar Despesa <span class="material-symbols-outlined">save</span></button>
    </div>
</div>

<!-- Receita Extra -->
<div class="modal" id="mod-receita">
    <div class="mbox">
        <div class="mh"><h2 id="rec-modal-title"> Nova Receita</h2><span class="mcl" onclick="closeModal('mod-receita')">close</span></div>
        <input type="hidden" id="rec-id">
        <div class="fl"><label>Descrição *</label><input type="text" id="rec-desc" placeholder="Ex: Serviço de instalação"></div>
        <div class="fg2">
            <div class="fl"><label>Categoria</label><select id="rec-cat"><option>Serviço</option><option>Ajuste</option><option>Bonificação</option><option>Reembolso</option><option>Outros</option></select></div>
            <div class="fl"><label>Valor (R$) *</label><input type="number" id="rec-val" step="0.01" min="0"></div>
        </div>
        <div class="fg2">
            <div class="fl"><label>Data</label><input type="date" id="rec-data"></div>
            <div class="fl"><label>Status</label><select id="rec-status"><option value="recebido">Recebido</option><option value="pendente">Pendente</option></select></div>
        </div>
        <div class="fl"><label>Obs.</label><textarea id="rec-obs" rows="2" placeholder="Opcional"></textarea></div>
        <button class="mbtn ok" onclick="saveReceita()">Salvar <span class="material-symbols-outlined">save</span></button>
    </div>
</div>

<!-- Comissão -->
<div class="modal" id="mod-comissao">
    <div class="mbox">
        <div class="mh"><h2> Registrar Comissão</h2><span class="mcl" onclick="closeModal('mod-comissao')">close</span></div>
        <div class="fl"><label>Vendedor *</label><select id="com-vend"><option value="">Selecione</option></select></div>
        <div class="fg2">
            <div class="fl"><label>Período Início</label><input type="date" id="com-ini"></div>
            <div class="fl"><label>Período Fim</label><input type="date" id="com-fim"></div>
        </div>
        <div class="fg2">
            <div class="fl"><label>Total Vendas (R$)</label><input type="number" id="com-vendas" step="0.01" min="0"></div>
            <div class="fl"><label>% Comissão</label><input type="number" id="com-pct" step="0.5" min="0" max="100"></div>
        </div>
        <div class="fl" style="background:var(--ok-dim);border-radius:9px;padding:.7rem 1rem;margin-bottom:.5rem">
            <span style="font-size:.72rem;color:var(--muted);font-weight:700">COMISSÃO CALCULADA</span>
            <span style="font-size:1.2rem;font-weight:700;color:var(--ok)" id="com-preview">R$ 0,00</span>
        </div>
        <div class="fl"><label>Obs.</label><textarea id="com-obs" rows="2" placeholder="Opcional"></textarea></div>
        <button class="mbtn vl" onclick="saveComissao()">Registrar</button>
    </div>
</div>

<!-- Simular Cenário -->
<div class="modal" id="mod-simular">
    <div class="mbox wide">
        <div class="mh"><h2>Simular Cenário</h2><span class="mcl" onclick="closeModal('mod-simular')">close</span></div>
        <p style="font-size:.8rem;color:var(--muted);margin-bottom:1.1rem">Ajuste os sliders para ver como mudanças afetariam o resultado do mês selecionado no DRE.</p>
        <div class="sim-row">
            <span class="sim-label"> Receita de Vendas</span>
            <input type="range" class="sim-slider" id="sim-rec" min="-50" max="100" value="0" oninput="calcSimulacao()">
            <span class="sim-val" id="sim-rec-val">0%</span>
        </div>
        <div class="sim-row">
            <span class="sim-label"> Despesas Fixas</span>
            <input type="range" class="sim-slider" id="sim-desp-fix" min="-50" max="50" value="0" oninput="calcSimulacao()">
            <span class="sim-val" id="sim-fix-val">0%</span>
        </div>
        <div class="sim-row">
            <span class="sim-label"> Despesas Variáveis</span>
            <input type="range" class="sim-slider" id="sim-desp-var" min="-50" max="50" value="0" oninput="calcSimulacao()">
            <span class="sim-val" id="sim-var-val">0%</span>
        </div>
        <div class="sim-row">
            <span class="sim-label"> Receitas Extras</span>
            <input type="range" class="sim-slider" id="sim-rec-ext" min="-100" max="200" value="0" oninput="calcSimulacao()">
            <span class="sim-val" id="sim-ext-val">0%</span>
        </div>
        <div class="sim-resultado" id="sim-resultado">
            <div style="color:rgba(255,255,255,.5);font-size:.78rem;text-align:center;padding:.5rem">
                Carregando dados do DRE…
            </div>
        </div>
        <button class="mbtn" style="margin-top:1rem" onclick="resetSimulacao()"><span class="material-symbols-outlined">restart_alt</span>Resetar</button>
    </div>
</div>

<script>
const CSRF = <?= json_encode(get_csrf_token()) ?>;
const API  = 'financeiro_api.php';
const $    = id => document.getElementById(id);
const esc  = v => String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const R    = v => v===null||v===undefined||v==='' ? '—' : 'R$ '+Number(v).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
const Rn   = v => Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
const dF   = s => s ? new Date(s+'T00:00:00').toLocaleDateString('pt-BR') : '—';
const today= () => new Date().toISOString().slice(0,10);

let fpCache=[], vendsCache=[], dreData=null;

/* ── sidebar mobile ── */
const sb_=$('sb'), ov_=$('ov');
$('ham').addEventListener('click',()=>{sb_.classList.add('open');ov_.classList.add('on');document.body.style.overflow='hidden'});
ov_.addEventListener('click',()=>{sb_.classList.remove('open');ov_.classList.remove('on');document.body.style.overflow=''});

/* ── view navigation ── */
const viewMeta = {
    dashboard: {title:' Dashboard Financeiro',    sub:'Visão geral financeira',              fn:loadDashboard},
    fluxo:     {title:' Fluxo de Caixa',          sub:'Todas as entradas e saídas',           fn:loadFluxo},
    contas:    {title:' Contas & Despesas',        sub:'A receber, a pagar e despesas',        fn:loadContas},
    receitas:  {title:' Receitas',                 sub:'Receitas extras além das vendas',      fn:loadReceitas},
    comissoes: {title:' Comissões',                sub:'Gestão de comissões de vendedores',    fn:loadComissoes},
    dre:       {title:' DRE',                      sub:'Demonstrativo de Resultado',           fn:()=>{}},
    relatorios:{title:' Relatórios Financeiros',   sub:'Análises e indicadores',               fn:loadRelatorios},
};

function showView(v) {
    document.querySelectorAll('.view').forEach(el=>el.classList.remove('on'));
    document.querySelectorAll('.fin-sub').forEach(el=>el.classList.remove('on'));
    $('view-'+v).classList.add('on');
    document.querySelector(`.fin-sub[data-view="${v}"]`)?.classList.add('on');
    const m=viewMeta[v];
    $('viewTitle').textContent=m.title;
    $('viewSub').textContent=m.sub;
    if(window.innerWidth<=860){sb_.classList.remove('open');ov_.classList.remove('on');document.body.style.overflow='';}
    m.fn();
}
document.querySelectorAll('.fin-sub').forEach(el=>el.addEventListener('click',()=>showView(el.dataset.view)));

/* ── tab system (Contas & Despesas) ── */
const tabLoaded={cr:false,cp:false,desp:false};
function switchTab(tab, btn) {
    document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('on'));
    document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('on'));
    btn.classList.add('on');
    $('tab-'+tab).classList.add('on');
    if(!tabLoaded[tab]) {
        if(tab==='cr')   loadCR();
        if(tab==='cp')   loadCP();
        if(tab==='desp') loadDespesas();
        tabLoaded[tab]=true;
    }
}
function loadContas() { tabLoaded.cr=false; tabLoaded.cp=false; tabLoaded.desp=false; loadCR(); tabLoaded.cr=true; }

/* ── modal helpers ── */
function openModal(id){$(id).classList.add('show')}
function closeModal(id){$(id).classList.remove('show')}
document.querySelectorAll('.modal').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('show')}));

/* ── api helpers ── */
async function post(acao, data) {
    const fd=new FormData(); fd.append('csrf_token',CSRF);
    Object.entries(data).forEach(([k,v])=>{if(v!==null&&v!==undefined)fd.append(k,v);});
    const r=await fetch(`${API}?acao=${acao}`,{method:'POST',body:fd,credentials:'same-origin'});
    return r.json();
}
async function get(acao, params='') {
    const r=await fetch(`${API}?acao=${acao}${params}`,{credentials:'same-origin'});
    return r.json();
}

/* ── status badge ── */
function stBadge(s){const m={pendente:'<span class="bx wa">Pendente</span>',pago:'<span class="bx ok"> Pago</span>',atrasado:'<span class="bx er"> Atrasado</span>',cancelado:'<span class="bx gy">Cancelado</span>',recebido:'<span class="bx ok"> Recebido</span>'};return m[s]||`<span class="bx gy">${esc(s)}</span>`;}

/* ════════════════════════════════════════════════════
   SVG AREA CHART — estilo dark com curvas suaves
   datasets: [{label,color,fill,data:[numbers]}]
   labels: string[]
════════════════════════════════════════════════════ */
const financeCharts = new Map();
function chartCss(name, fallback) {
    return getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
}
function renderAreaChart(chartId, datasets, labels) {
    const el=$(chartId); if(!el || typeof echarts==='undefined') return;
    let entry=financeCharts.get(chartId);
    if(!entry){
        entry={chart:echarts.init(el,null,{renderer:'canvas'}),datasets:[],labels:[]};
        financeCharts.set(chartId,entry);
    }
    entry.datasets=datasets;
    entry.labels=labels;
    if(!labels.length){entry.chart.clear();el.dataset.empty='true';return;}
    delete el.dataset.empty;
    const dark=document.documentElement.dataset.theme==='dark';
    const text=chartCss('--muted',dark?'#aebbd0':'#5a6a7a');
    const faint=chartCss('--faint',dark?'#7f8da4':'#94a3b8');
    const border=chartCss('--border',dark?'#233149':'#e4eaf2');
    const card=chartCss('--card',dark?'#111b2b':'#ffffff');
    entry.chart.setOption({
        animationDuration:650,
        animationEasing:'cubicOut',
        color:datasets.map(ds=>ds.color),
        grid:{left:12,right:16,top:18,bottom:10,containLabel:true},
        tooltip:{
            trigger:'axis',backgroundColor:card,borderColor:border,borderWidth:1,
            textStyle:{color:text,fontFamily:'Inter',fontSize:12},
            axisPointer:{type:'line',lineStyle:{color:faint,type:'dashed'}},
            valueFormatter:value=>R(value)
        },
        xAxis:{
            type:'category',boundaryGap:false,data:labels,
            axisLine:{lineStyle:{color:border}},axisTick:{show:false},
            axisLabel:{color:faint,fontSize:10,hideOverlap:true,margin:12}
        },
        yAxis:{
            type:'value',scale:true,splitNumber:4,
            axisLine:{show:false},axisTick:{show:false},
            axisLabel:{color:faint,fontSize:10,formatter:value=>Number(value).toLocaleString('pt-BR')},
            splitLine:{lineStyle:{color:border,type:'dashed',opacity:.72}}
        },
        series:datasets.map((ds,index)=>({
            name:ds.label,type:'line',data:ds.data.map(Number),smooth:.34,showSymbol:datasets.length===1,
            symbol:'circle',symbolSize:7,lineStyle:{width:3,color:ds.color},
            itemStyle:{color:ds.color,borderColor:card,borderWidth:2},
            emphasis:{focus:'series',lineStyle:{width:3}},
            areaStyle:{
                opacity:index===0?.16:.06,
                color:new echarts.graphic.LinearGradient(0,0,0,1,[
                    {offset:0,color:ds.color},{offset:1,color:dark?'rgba(17,27,43,0)':'rgba(255,255,255,0)'}
                ])
            }
        }))
    },true);
}

window.addEventListener('resize',()=>financeCharts.forEach(entry=>entry.chart.resize()));
window.addEventListener('uplar:themechange',()=>{
    financeCharts.forEach((entry,id)=>renderAreaChart(id,entry.datasets,entry.labels));
});

/* ════════════════════════════════════════════════════
   DASHBOARD
════════════════════════════════════════════════════ */
async function loadDashboard() {
    const mes=$('dash-mes').value, ano=$('dash-ano').value;
    const d=await get('dashboard',`&mes=${mes}&ano=${ano}`);
    const pctR=d.receita_ant>0?(((d.receita-d.receita_ant)/d.receita_ant)*100).toFixed(1):0;
    const pctD=d.despesa_ant>0?(((d.despesa-d.despesa_ant)/d.despesa_ant)*100).toFixed(1):0;
    const lucro=parseFloat(d.lucro)||0;
    $('dash-kpis').innerHTML=`
    <div class="kpi g"><div class="kpi-top"><div class="kpi-ico"><span class="material-symbols-outlined">payments</span></div><span class="kpi-tag">${pctR>=0?"+":"-"}${Math.abs(pctR)}%</span></div><div class="kpi-val">${R(d.receita)}</div><div class="kpi-lbl">Receita</div><div class="kpi-hint">Mês anterior ${R(d.receita_ant)}</div></div>
    <div class="kpi v"><div class="kpi-top"><div class="kpi-head"><div class="kpi-ico"><span class="material-symbols-outlined">inventory</span></div><span class="kpi-tag">CMV</span></div></div><div class="kpi-val">${R(d.custo_vendas)}</div><div class="kpi-lbl">Custo dos produtos</div><div class="kpi-hint">Custo das vendas</div></div>
    <div class="kpi g"><div class="kpi-top"><div class="kpi-head"><div class="kpi-ico"><span class="material-symbols-outlined">trending_up</span></div><span class="kpi-tag">Bruto</span></div></div><div class="kpi-val">${R(d.lucro_bruto)}</div><div class="kpi-lbl">Lucro bruto</div><div class="kpi-hint">${d.custos_completos===false?`${Number(d.vendas_custo_parcial||0)} venda(s) sem custo completo`:'Receita menos custo'}</div></div>
    <div class="kpi r"><div class="kpi-top"><div class="kpi-head"><div class="kpi-ico"><span class="material-symbols-outlined">trending_down</span></div><span class="kpi-tag">${pctD<=0?"-":"+"}${Math.abs(pctD)}%</span></div></div><div class="kpi-val">${R(d.despesa)}</div><div class="kpi-lbl">Despesas</div><div class="kpi-hint">Mês anterior ${R(d.despesa_ant)}</div></div>
    <div class="kpi ${d.lucro===null?"o":(lucro>=0?"g":"r")}"><div class="kpi-top"><div class="kpi-head"><div class="kpi-ico"><span class="material-symbols-outlined">account_balance</span></div><span class="kpi-tag">Líquido</span></div></div><div class="kpi-val">${R(d.lucro)}</div><div class="kpi-lbl">Lucro líquido</div><div class="kpi-hint">${d.custos_completos===false?'Custos incompletos no período':'Após despesas'}</div></div>
    <div class="kpi o"><div class="kpi-top"><div class="kpi-head"><div class="kpi-ico"><span class="material-symbols-outlined">pending_actions</span></div><span class="kpi-tag">${Number(d.cr_count||0)+Number(d.cp_count||0)}</span></div></div><div class="kpi-val">${R(Number(d.cr_total||0)+Number(d.cp_total||0))}</div><div class="kpi-lbl">Contas pendentes</div><div class="kpi-hint">A receber + a pagar</div></div>`;
    // Area chart
    const h6=d.historico_6m||[];
    renderAreaChart("dash-chart",
        [{label:"Receita",color:"#10b981",data:h6.map(x=>x.receita)},
         {label:"Despesas",color:"#f59e0b",data:h6.map(x=>x.despesa)},
         {label:"Custo dos produtos",color:"#8b5cf6",data:h6.map(x=>x.custo_vendas)},
         {label:"Lucro bruto",color:"#3b6ff5",data:h6.map(x=>x.lucro_bruto)},
         {label:"Lucro líquido",color:"#7c3aed",data:h6.map(x=>x.lucro)}],
        h6.map(x=>x.label));
    // Top produtos
    const tp=d.top_produtos||[];
    $('dash-top').innerHTML=tp.length?tp.map((p,i)=>`<div style="display:flex;align-items:center;gap:.6rem;padding:.5rem 0;border-bottom:1px solid var(--border)"><div style="width:20px;height:20px;border-radius:6px;background:var(--ok-dim);color:var(--ok);display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:800;flex-shrink:0">${i+1}</div><div style="flex:1;min-width:0"><div style="font-size:.83rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(p.nome)}</div><div style="font-size:.68rem;color:var(--faint)">${p.unidades} un.</div></div><div style="font-weight:700;color:var(--ok);font-size:.86rem">${R(p.receita)}</div></div>`).join(''):'<div class="empty"><span class="material-symbols-outlined">inventory</span><p>Sem vendas no mês</p></div>';
    // Alertas
    $('dash-cr-alert').innerHTML=d.cr_count?`<div style="text-align:center;padding:1rem 0"><div style="font-size:1.5rem;font-weight:700;color:var(--warn)">${R(d.cr_total)}</div><div style="font-size:.78rem;color:var(--muted);margin:.3rem 0">${d.cr_count} conta(s)</div><button class="pill pill-o" style="width:100%;justify-content:center" onclick="showView('contas')">Ver</button></div>`:'<div class="empty"><span class="material-symbols-outlined">check_circle</span><p>Em dia</p></div>';
    $('dash-cp-alert').innerHTML=d.cp_count?`<div style="text-align:center;padding:1rem 0"><div style="font-size:1.5rem;font-weight:700;color:var(--err)">${R(d.cp_total)}</div><div style="font-size:.78rem;color:var(--muted);margin:.3rem 0">${d.cp_count} conta(s)</div><button class="pill pill-o" style="width:100%;justify-content:center" onclick="showView('contas')">Ver</button></div>`:'<div class="empty"><span class="material-symbols-outlined">check_circle</span><p>Em dia</p></div>';
    $('dash-comis-alert').innerHTML=d.comis_count?`<div style="text-align:center;padding:1rem 0"><div style="font-size:1.5rem;font-weight:700;color:var(--violet)">${R(d.comis_total)}</div><div style="font-size:.78rem;color:var(--muted);margin:.3rem 0">${d.comis_count} pend.</div><button class="pill pill-o" style="width:100%;justify-content:center" onclick="showView('comissoes')">Ver</button></div>`:'<div class="empty"><span class="material-symbols-outlined">check_circle</span><p>Em dia</p></div>';
}

/* ════════════════════════════════════════════════════

/* ════════════════════════════════════════════════════
  FLUXO DE CAIXA
════════════════════════════════════════════════════ */
let fluxoData=[];
async function loadFluxo() {
    const per=$('fluxo-per').value;
    const d=await get('fluxo_caixa',`&periodo=${per}`);
    fluxoData=d.movimentacoes||[];
    const saldo=parseFloat(d.saldo)||0;
    $('fluxo-saldo-val').textContent=R(saldo);
    $('fluxo-saldo-val').style.color=saldo>=0?'#fff':'#fca5a5';
    $('fluxo-in').textContent=R(d.total_entradas);
    $('fluxo-out').textContent=R(d.total_saidas);
    const div=$('fluxo-list');
    if(!fluxoData.length){div.innerHTML='<div class="empty"><span class="material-symbols-outlined">swap_vert</span><p>Nenhuma movimentação</p></div>';return;}
    const byDate={};
    fluxoData.forEach(m=>{const k=m.data||'';if(!byDate[k])byDate[k]=[];byDate[k].push(m);});
    div.innerHTML=Object.entries(byDate).map(([dt,movs])=>{
        const tot=movs.reduce((s,m)=>s+(m.tipo==='entrada'?parseFloat(m.valor):-parseFloat(m.valor)),0);
        return `<div style="margin-bottom:.85rem">
            <div style="display:flex;justify-content:space-between;font-size:.71rem;font-weight:700;color:var(--faint);text-transform:uppercase;padding:.4rem 0;border-bottom:1px solid var(--border);margin-bottom:.4rem">
                <span>${dF(dt)}</span><span style="color:${tot>=0?'var(--ok)':'var(--err)'}">Saldo: ${R(tot)}</span>
            </div>
            ${movs.map(m=>`<div class="mov-row">
                <div class="mov-ico ${m.tipo==='entrada'?'in':'out'}"><span class="material-symbols-outlined">${m.tipo==='entrada'?'south':'north'}</span></div>
                <div class="mov-body"><div class="mov-desc">${esc(m.descricao)}</div><div class="mov-meta">${esc(m.categoria)}</div></div>
                <div class="mov-val ${m.tipo}">${m.tipo==='entrada'?'+':'-'}${R(m.valor)}</div>
            </div>`).join('')}</div>`;
    }).join('');
}
function exportFluxoCSV(){if(!fluxoData.length)return alert('Nenhuma movimentação');const h=['Data','Tipo','Descrição','Categoria','Valor'];const rows=fluxoData.map(m=>[m.data,m.tipo,`"${(m.descricao||'').replace(/"/g,'""')}"`,`"${(m.categoria||'').replace(/"/g,'""')}"`,Number(m.valor||0).toFixed(2)]);const csv='\uFEFF'+[h,...rows].map(r=>r.join(';')).join('\n');const a=Object.assign(document.createElement('a'),{href:URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8;'})),download:`fluxo_${today()}.csv`});a.click();}

/* ════════════════════════════════════════════════════
   CONTAS A RECEBER
════════════════════════════════════════════════════ */
let crFilter='',crData=[],crPg=1;const crPP=20;
function setCrFilter(btn){document.querySelectorAll('.cr-f').forEach(b=>b.classList.remove('pill-p'));btn.classList.add('pill-p');crFilter=btn.dataset.s;loadCR();}
async function loadCR(){const d=await get('listar_cr',`&status=${crFilter}`);crData=Array.isArray(d)?d:[];$('cr-count').textContent=crData.length+' conta(s)';crPg=1;renderCR();}
function renderCR(){
    const body=$('cr-body'),pag=crData.slice((crPg-1)*crPP,crPg*crPP);
    if(!crData.length){body.innerHTML=`<tr><td colspan="7"><div class="empty"><span class="material-symbols-outlined">request_quote</span><p>Nenhuma conta</p></div></td></tr>`;$('cr-pag').innerHTML='';return;}
    body.innerHTML=pag.map(r=>{const late=new Date(r.data_vencimento+'T00:00:00')<new Date()&&r.status==='pendente';return`<tr>
        <td><span class="bx bl">#${esc(r.id)}</span></td>
        <td><strong>${esc(r.descricao)}</strong><br><small style="color:var(--faint)">${esc(r.cliente||'')}</small></td>
        <td><strong style="color:var(--ok)">${R(r.valor)}</strong></td>
        <td style="${late?'color:var(--err)':''}"><strong>${dF(r.data_vencimento)}</strong></td>
        <td>${stBadge(r.status)}</td>
        <td><div class="acts">${r.status!=='pago'?`<button class="act-btn ok" onclick="openCRBaixa(${r.id})"><span class="material-symbols-outlined">check_circle</span></button>`:''}<button class="act-btn" onclick="editCR(${r.id})"><span class="material-symbols-outlined">edit</span></button><button class="act-btn er" onclick="delCR(${r.id})"><span class="material-symbols-outlined">delete</span></button></div></td>
    </tr>`;}).join('');
    renderPag('cr-pag',crPg,Math.ceil(crData.length/crPP),p=>{crPg=p;renderCR();});
}
function openCRBaixa(id){$('crb-id').value=id;$('crb-data').value=today();openModal('mod-cr-baixa');}
async function pagarCR(){const j=await post('pagar_cr',{id:$('crb-id').value,data_recebimento:$('crb-data').value});if(j.ok){closeModal('mod-cr-baixa');loadCR();}else alert('Erro: '+j.error);}
function editCR(id){const r=crData.find(x=>x.id==id);if(!r)return;$('cr-id').value=id;$('cr-desc').value=r.descricao;$('cr-cli').value=r.cliente||'';$('cr-val').value=r.valor;$('cr-venc').value=r.data_vencimento;$('cr-obs').value=r.observacao||'';$('cr-modal-title').textContent='️ Editar Conta a Receber';openModal('mod-cr');}
async function saveCR(){const id=parseInt($('cr-id').value)||0;const data={descricao:$('cr-desc').value,cliente:$('cr-cli').value,valor:$('cr-val').value,data_vencimento:$('cr-venc').value,observacao:$('cr-obs').value};const j=await post(id?'edit_cr':'add_cr',id?{...data,id}:data);if(j.ok){closeModal('mod-cr');$('cr-id').value='';$('cr-modal-title').textContent=' Nova Conta a Receber';loadCR();}else alert('Erro: '+j.error);}
async function delCR(id){if(!confirm('Excluir?'))return;const j=await post('del_cr',{id});if(j.ok)loadCR();else alert('Erro: '+j.error);}

/* ════════════════════════════════════════════════════
   CONTAS A PAGAR
════════════════════════════════════════════════════ */
let cpFilter='',cpData=[],cpPg=1;const cpPP=20;
function setCpFilter(btn){document.querySelectorAll('.cp-f').forEach(b=>b.classList.remove('pill-p'));btn.classList.add('pill-p');cpFilter=btn.dataset.s;loadCP();}
async function loadCP(){const d=await get('listar_cp',`&status=${cpFilter}`);cpData=Array.isArray(d)?d:[];$('cp-count').textContent=cpData.length+' conta(s)';cpPg=1;renderCP();}
function renderCP(){
    const body=$('cp-body'),pag=cpData.slice((cpPg-1)*cpPP,cpPg*cpPP);
    if(!cpData.length){body.innerHTML=`<tr><td colspan="8"><div class="empty"><span class="material-symbols-outlined">receipt_long</span><p>Nenhuma conta</p></div></td></tr>`;$('cp-pag').innerHTML='';return;}
    body.innerHTML=pag.map(r=>`<tr>
        <td><span class="bx er">#${esc(r.id)}</span></td>
        <td><strong>${esc(r.descricao)}</strong></td>
        <td>${esc(r.fornecedor)||'—'}</td>
        <td><span class="bx gy">${esc(r.categoria)}</span></td>
        <td><strong style="color:var(--err)">${R(r.valor)}</strong></td>
        <td>${dF(r.data_vencimento)}</td>
        <td>${stBadge(r.status)}</td>
        <td><div class="acts">${r.status!=='pago'?`<button class="act-btn ok" onclick="openCPBaixa(${r.id})"><span class="material-symbols-outlined">check_circle</span></button>`:''}<button class="act-btn" onclick="editCP(${r.id})"><span class="material-symbols-outlined">edit</span></button><button class="act-btn er" onclick="delCP(${r.id})"><span class="material-symbols-outlined">delete</span></button></div></td>
    </tr>`).join('');
    renderPag('cp-pag',cpPg,Math.ceil(cpData.length/cpPP),p=>{cpPg=p;renderCP();});
}
function openCPBaixa(id){$('cpb-id').value=id;$('cpb-data').value=today();openModal('mod-cp-baixa');}
async function pagarCP(){const j=await post('pagar_cp',{id:$('cpb-id').value,data_pagamento:$('cpb-data').value});if(j.ok){closeModal('mod-cp-baixa');loadCP();}else alert('Erro: '+j.error);}
function editCP(id){const r=cpData.find(x=>x.id==id);if(!r)return;$('cp-id').value=id;$('cp-desc').value=r.descricao;$('cp-forn').value=r.fornecedor||'';$('cp-cat').value=r.categoria||'Outros';$('cp-val').value=r.valor;$('cp-venc').value=r.data_vencimento;$('cp-obs').value=r.observacao||'';$('cp-modal-title').textContent='️ Editar Conta a Pagar';openModal('mod-cp');}
async function saveCP(){const id=parseInt($('cp-id').value)||0;const data={descricao:$('cp-desc').value,fornecedor:$('cp-forn').value,categoria:$('cp-cat').value,valor:$('cp-val').value,data_vencimento:$('cp-venc').value,observacao:$('cp-obs').value};const j=await post(id?'edit_cp':'add_cp',id?{...data,id}:data);if(j.ok){closeModal('mod-cp');$('cp-id').value='';$('cp-modal-title').textContent=' Nova Conta a Pagar';loadCP();}else alert('Erro: '+j.error);}
async function delCP(id){if(!confirm('Excluir?'))return;const j=await post('del_cp',{id});if(j.ok)loadCP();else alert('Erro: '+j.error);}

/* ════════════════════════════════════════════════════
   DESPESAS
════════════════════════════════════════════════════ */
let despData=[],despPg=1;const despPP=20;
async function loadDespesas(){
    const per=$('desp-per').value,tipo=$('desp-tipo').value;
    const d=await get('listar_despesas',`&periodo=${per}&tipo=${tipo}`);
    despData=d.items||[];$('desp-count').textContent=despData.length+' item(ns)';despPg=1;renderDespesas();renderDespCats(despData);
}
function renderDespesas(){
    const body=$('desp-body'),pag=despData.slice((despPg-1)*despPP,despPg*despPP);
    if(!despData.length){body.innerHTML=`<tr><td colspan="7"><div class="empty"><span class="material-symbols-outlined">trending_down</span><p>Nenhuma despesa</p></div></td></tr>`;$('desp-pag').innerHTML='';return;}
    body.innerHTML=pag.map(r=>`<tr>
        <td><strong>${esc(r.descricao)}</strong></td>
        <td><span class="bx gy">${esc(r.categoria)}</span></td>
        <td><span class="bx ${r.tipo==='fixa'?'vl':'wa'}">${r.tipo==='fixa'?'Fixa':'Variável'}</span></td>
        <td><strong style="color:var(--err)">${R(r.valor)}</strong></td>
        <td>${dF(r.data_despesa)}</td>
        <td>${stBadge(r.status)}</td>
        <td><div class="acts"><button class="act-btn" onclick="editDespesa(${r.id})"><span class="material-symbols-outlined">edit</span></button><button class="act-btn er" onclick="delDespesa(${r.id})"><span class="material-symbols-outlined">delete</span></button></div></td>
    </tr>`).join('');
    renderPag('desp-pag',despPg,Math.ceil(despData.length/despPP),p=>{despPg=p;renderDespesas();});
}
function renderDespCats(data){const cats={};data.forEach(d=>{if(d.status==='pago'){cats[d.categoria]=(cats[d.categoria]||0)+parseFloat(d.valor);}});const sorted=Object.entries(cats).sort((a,b)=>b[1]-a[1]);const total=sorted.reduce((s,[,v])=>s+v,0);$('desp-cats').innerHTML=sorted.length?sorted.map(([cat,val])=>{const pct=total>0?(val/total*100).toFixed(0):0;return`<div style="margin-bottom:.6rem"><div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:.25rem"><span style="font-weight:600">${esc(cat)}</span><span style="color:var(--err);font-weight:700">${R(val)}</span></div><div style="height:6px;border-radius:20px;background:var(--border)"><div style="height:6px;border-radius:20px;background:var(--err);width:${pct}%;transition:width .7s"></div></div></div>`;}).join(''):'<div class="empty"><span class="material-symbols-outlined">pie_chart</span><p>Sem dados</p></div>';}
function editDespesa(id){const r=despData.find(x=>x.id==id);if(!r)return;$('desp-id').value=id;$('desp-desc').value=r.descricao;$('desp-cat').value=r.categoria||'Outros';$('desp-tipo-modal').value=r.tipo||'variavel';$('desp-val').value=r.valor;$('desp-data').value=r.data_despesa;$('desp-status').value=r.status||'pago';$('desp-obs').value=r.observacao||'';$('desp-modal-title').textContent='️ Editar Despesa';openModal('mod-despesa');}
async function saveDespesa(){const id=parseInt($('desp-id').value)||0;const data={descricao:$('desp-desc').value,categoria:$('desp-cat').value,tipo:$('desp-tipo-modal').value,valor:$('desp-val').value,data_despesa:$('desp-data').value,status:$('desp-status').value,observacao:$('desp-obs').value};const j=await post(id?'edit_despesa':'add_despesa',id?{...data,id}:data);if(j.ok){closeModal('mod-despesa');$('desp-id').value='';$('desp-modal-title').textContent=' Nova Despesa';loadDespesas();}else alert('Erro: '+j.error);}
async function delDespesa(id){if(!confirm('Excluir?'))return;const j=await post('del_despesa',{id});if(j.ok)loadDespesas();else alert('Erro: '+j.error);}

/* ════════════════════════════════════════════════════
   RECEITAS EXTRAS
════════════════════════════════════════════════════ */
let recData=[],recPg=1;const recPP=20;
async function loadReceitas(){const per=$('rec-per').value;const d=await get('listar_receitas',`&periodo=${per}`);recData=d.items||[];$('rec-count').textContent=recData.length+' item(ns)';recPg=1;renderReceitas();}
function renderReceitas(){const body=$('rec-body'),pag=recData.slice((recPg-1)*recPP,recPg*recPP);if(!recData.length){body.innerHTML=`<tr><td colspan="6"><div class="empty"><span class="material-symbols-outlined">trending_up</span><p>Nenhuma receita</p></div></td></tr>`;$('rec-pag').innerHTML='';return;}body.innerHTML=pag.map(r=>`<tr><td><strong>${esc(r.descricao)}</strong></td><td><span class="bx gy">${esc(r.categoria)}</span></td><td><strong style="color:var(--ok)">${R(r.valor)}</strong></td><td>${dF(r.data_receita)}</td><td>${stBadge(r.status)}</td><td><div class="acts"><button class="act-btn" onclick="editReceita(${r.id})"><span class="material-symbols-outlined">edit</span></button><button class="act-btn er" onclick="delReceita(${r.id})"><span class="material-symbols-outlined">delete</span></button></div></td></tr>`).join('');renderPag('rec-pag',recPg,Math.ceil(recData.length/recPP),p=>{recPg=p;renderReceitas();});}
function editReceita(id){const r=recData.find(x=>x.id==id);if(!r)return;$('rec-id').value=id;$('rec-desc').value=r.descricao;$('rec-cat').value=r.categoria||'Serviço';$('rec-val').value=r.valor;$('rec-data').value=r.data_receita;$('rec-status').value=r.status||'recebido';$('rec-obs').value=r.observacao||'';$('rec-modal-title').textContent='️ Editar Receita';openModal('mod-receita');}
async function saveReceita(){const id=parseInt($('rec-id').value)||0;const data={descricao:$('rec-desc').value,categoria:$('rec-cat').value,valor:$('rec-val').value,data_receita:$('rec-data').value,status:$('rec-status').value,observacao:$('rec-obs').value};const j=await post(id?'edit_receita':'add_receita',id?{...data,id}:data);if(j.ok){closeModal('mod-receita');$('rec-id').value='';$('rec-modal-title').textContent=' Nova Receita';loadReceitas();}else alert('Erro: '+j.error);}
async function delReceita(id){if(!confirm('Excluir?'))return;const j=await post('del_receita',{id});if(j.ok)loadReceitas();else alert('Erro: '+j.error);}

/* ════════════════════════════════════════════════════
   COMISSÕES
════════════════════════════════════════════════════ */
async function loadComissoes(){
    const per=$('comis-per').value;
    const d=await get('listar_comissoes',`&periodo=${per}`);
    const cc=$('comis-config');
    if(!vendsCache.length){cc.innerHTML='<div class="empty"><span class="material-symbols-outlined">group</span><p>Nenhum vendedor cadastrado</p></div>';}
    else{const configMap={};(d.configs||[]).forEach(c=>configMap[c.vendedor_id]=c.percentual);cc.innerHTML=vendsCache.map(v=>`<div class="vend-comis"><div class="vend-av">${esc(v.nome.substring(0,2).toUpperCase())}</div><div class="vend-comis-body"><div class="vend-comis-nome">${esc(v.nome)}</div><div class="vend-comis-sub">Comissão atual</div></div><input type="number" class="comis-pct-input" data-vend="${v.id}" value="${configMap[v.id]||0}" min="0" max="100" step="0.5"><button class="act-btn" onclick="saveConfigComis(${v.id},this)" style="margin-left:.4rem"><span class="material-symbols-outlined">save</span></button></div>`).join('');}
    const vp=$('comis-vendas');
    if(!(d.vendas_periodo&&d.vendas_periodo.length)){vp.innerHTML='<div class="empty"><span class="material-symbols-outlined">receipt_long</span><p>Sem vendas no período</p></div>';}
    else{vp.innerHTML=d.vendas_periodo.map(v=>`<div class="mov-row"><div class="mov-ico in"><span class="material-symbols-outlined">person</span></div><div class="mov-body"><div class="mov-desc">${esc(v.vendedor_nome)}</div><div class="mov-meta">${v.qtd_vendas} vendas · ${v.percentual}%</div></div><div style="text-align:right"><div class="mov-val in">${R(v.comissao_periodo)}</div><div style="font-size:.68rem;color:var(--faint)">${R(v.total_vendas)}</div></div></div>`).join('');}
    const pagas=d.pagas||[];
    $('comis-body').innerHTML=pagas.length?pagas.map(c=>`<tr><td><strong>${esc(c.vendedor_nome)}</strong></td><td style="font-size:.74rem;color:var(--faint)">${dF(c.periodo_inicio)}–${dF(c.periodo_fim)}</td><td>${R(c.total_vendas)}</td><td>${c.percentual}%</td><td><strong style="color:var(--violet)">${R(c.valor_comissao)}</strong></td><td>${stBadge(c.status)}</td><td><div class="acts">${c.status==='pendente'?`<button class="act-btn ok" onclick="pagarComis(${c.id})"><span class="material-symbols-outlined">check_circle</span></button>`:''}<button class="act-btn er" onclick="delComis(${c.id})"><span class="material-symbols-outlined">delete</span></button></div></td></tr>`).join(''):`<tr><td colspan="7"><div class="empty"><span class="material-symbols-outlined">handshake</span><p>Nenhuma comissão</p></div></td></tr>`;
}
async function saveConfigComis(vid,btn){const inp=document.querySelector(`.comis-pct-input[data-vend="${vid}"]`);const j=await post('set_config_comissao',{vendedor_id:vid,percentual:inp.value});if(j.ok){btn.innerHTML='<span class="material-symbols-outlined">check</span>';setTimeout(()=>btn.innerHTML='<span class="material-symbols-outlined">save</span>',1200);}else alert('Erro: '+j.error);}
['com-vendas','com-pct'].forEach(id=>{const el=$(id);if(el)el.addEventListener('input',()=>{const v=parseFloat($('com-vendas').value)||0,p=parseFloat($('com-pct').value)||0;$('com-preview').textContent=R(v*p/100);});});
async function saveComissao(){const j=await post('registrar_comissao',{vendedor_id:$('com-vend').value,periodo_inicio:$('com-ini').value,periodo_fim:$('com-fim').value,total_vendas:$('com-vendas').value,percentual:$('com-pct').value,observacao:$('com-obs').value});if(j.ok){closeModal('mod-comissao');loadComissoes();}else alert('Erro: '+j.error);}
async function pagarComis(id){const dt=prompt('Data do pagamento (AAAA-MM-DD):',today());if(!dt)return;const j=await post('pagar_comissao',{id,data_pagamento:dt});if(j.ok)loadComissoes();else alert('Erro: '+j.error);}
async function delComis(id){if(!confirm('Excluir?'))return;const j=await post('del_comissao',{id});if(j.ok)loadComissoes();else alert('Erro: '+j.error);}

/* ════════════════════════════════════════════════════
   DRE — Demonstrativo de Resultado do Exercício
   Inclui: Fechar Mês + Simular Cenário
════════════════════════════════════════════════════ */

/* --- Fechar Mês (state via localStorage) --- */
function dreKey(){return`dre_fechado_${$('dre-mes').value}_${$('dre-ano').value}_u<?= $user_id ?>`;}
function isMesFechado(){return localStorage.getItem(dreKey())==='1';}
function checkMesFechado(){
    const fechado=isMesFechado();
    $('banner-fechado').style.display=fechado?'flex':'none';
    const mesNome=new Date(+$('dre-ano').value,+$('dre-mes').value-1).toLocaleDateString('pt-BR',{month:'long',year:'numeric'});
    $('banner-fechado-txt').textContent=`${mesNome.charAt(0).toUpperCase()+mesNome.slice(1)} está fechado — apenas ajustes permitidos.`;
    $('btn-fechar-mes').style.display=fechado?'none':'inline-flex';
}

function confirmarFecharMes(){
    const mes=+$('dre-mes').value, ano=+$('dre-ano').value;
    const hoje=new Date(); const mesAtual=hoje.getMonth()+1; const anoAtual=hoje.getFullYear();
    const mesNome=new Date(ano,mes-1).toLocaleDateString('pt-BR',{month:'long',year:'numeric'});
    if(mes===mesAtual&&ano===anoAtual){if(!confirm(`Deseja fechar ${mesNome}?\n\nEste é o mês atual — tem certeza?`))return;}
    else{if(!confirm(`Fechar ${mesNome}?\n\nApós o fechamento, novos lançamentos devem ser registrados como Ajuste.`))return;}
    localStorage.setItem(dreKey(),'1');
    checkMesFechado();
    // Toast visual
    showToast(' Mês fechado com sucesso!','var(--violet)');
}

function showToast(msg,color='var(--ok)'){
    const t=document.createElement('div');
    t.style.cssText=`position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:${color};color:#fff;padding:.7rem 1.3rem;border-radius:50px;font-size:.84rem;font-weight:700;box-shadow:0 8px 32px rgba(0,0,0,.25);z-index:9999;animation:fadeUp .3s forwards`;
    t.textContent=msg; document.body.appendChild(t);
    setTimeout(()=>t.remove(),3000);
}

/* --- Gerar DRE --- */
async function gerarDRE(){
    const mes=$('dre-mes').value, ano=$('dre-ano').value;
    checkMesFechado();
    const btn=$('btn-gerar-dre');
    btn.disabled=true; btn.innerHTML='<span class="material-symbols-outlined">hourglass_empty</span>Carregando…';
    try {
        const d=await get('dre',`&mes=${mes}&ano=${ano}`);
        if(!d||d.ok===false) throw new Error(d?.error||'Resposta inválida da API');
        dreData=d;
        const mesNome=new Date(+ano,+mes-1).toLocaleDateString('pt-BR',{month:'long',year:'numeric'});
        $('dre-periodo-titulo').textContent=` DRE — ${mesNome.charAt(0).toUpperCase()+mesNome.slice(1)}`;
        const lucro=parseFloat(d.lucro_liquido)||0;
        const hasProfit=d.lucro_liquido!==null;
        const isPos=hasProfit&&lucro>=0;
        $('dre-body').innerHTML=`
        ${Number(d.vendas_custo_parcial||0)>0?`<div class="dre-custo-warn"><span class="material-symbols-outlined">warning</span><div>${d.vendas_custo_parcial} venda(s) têm custo ausente ou parcial. O relatório mantém o custo conhecido e oculta lucro e margem até a cobertura ser completa.</div></div>`:''}
        <div class="dre-row"><div class="dre-lbl"> Receita de Vendas</div><div class="dre-val pos">${R(d.receita_vendas)}</div></div>
        <div class="dre-row sub"><div class="dre-lbl">↳ Receitas Extras</div><div class="dre-val pos">${R(d.receita_extras)}</div></div>
        <div class="dre-row total"><div class="dre-lbl" style="font-weight:700">= Receita Bruta</div><div class="dre-val pos">${R(d.receita_bruta)}</div></div>
        <div class="dre-row sep"><div class="dre-lbl"> Custo dos produtos vendidos</div><div class="dre-val neg">– ${R(d.custo_vendas)}</div></div>
        <div class="dre-row total"><div class="dre-lbl" style="font-weight:700">= Lucro Bruto</div><div class="dre-val pos">${R(d.lucro_bruto)}</div></div>
        <div style="height:.6rem"></div>
        <div class="dre-row sep"><div class="dre-lbl"> Despesas Fixas</div><div class="dre-val neg">– ${R(d.desp_fixas)}</div></div>
        <div class="dre-row sub"><div class="dre-lbl">↳ Despesas Variáveis</div><div class="dre-val neg">– ${R(d.desp_variaveis)}</div></div>
        <div class="dre-row sub"><div class="dre-lbl">↳ Contas Pagas</div><div class="dre-val neg">– ${R(d.contas_pagas)}</div></div>
        <div class="dre-row sub"><div class="dre-lbl">↳ Comissões</div><div class="dre-val neg">– ${R(d.comissoes)}</div></div>
        <div class="dre-row total r"><div class="dre-lbl" style="font-weight:700">= Total Despesas</div><div class="dre-val neg">– ${R(d.total_despesas)}</div></div>
        <div style="height:.8rem"></div>
        <div class="dre-row total ${!hasProfit?'o':(isPos?'g':'r')}" style="padding:1rem;border-radius:12px">
            <div class="dre-lbl" style="font-size:1rem;font-weight:700">${isPos?'':''} Lucro Líquido</div>
            <div style="text-align:right">
                <div style="font-size:1.4rem;font-weight:700;color:${!hasProfit?'var(--warn)':(isPos?'var(--ok)':'var(--err)')}">${R(d.lucro_liquido)}</div>
                <div style="font-size:.72rem;color:var(--muted);margin-top:2px">Margem: ${d.margem_lucro===null?'—':d.margem_lucro+'%'}</div>
            </div>
        </div>`;
        const cats=d.desp_por_categoria||[];
        const totCat=cats.reduce((s,c)=>s+parseFloat(c.total),0);
        $('dre-cats').innerHTML=cats.length?cats.map(c=>{const pct=totCat>0?(parseFloat(c.total)/totCat*100).toFixed(0):0;return`<div style="margin-bottom:.65rem"><div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:.22rem"><span style="font-weight:600">${esc(c.categoria)}</span><span style="color:var(--err);font-weight:700">${R(c.total)}</span></div><div style="height:5px;border-radius:20px;background:var(--border)"><div style="height:5px;border-radius:20px;background:linear-gradient(to right,var(--err),#fb7185);width:${pct}%;transition:width .8s"></div></div></div>`;}).join(''):'<div class="empty"><span class="material-symbols-outlined">pie_chart</span><p>Sem despesas</p></div>';
    } catch(e) {
        $('dre-body').innerHTML=`<div class="empty"><span class="material-symbols-outlined">error</span><p>Erro ao carregar: ${esc(e.message)}</p></div>`;
    }
    btn.disabled=false; btn.innerHTML='<span class="material-symbols-outlined">refresh</span>Gerar DRE';
}
// mês/ano change → re-check fechado
['dre-mes','dre-ano'].forEach(id=>{const el=$(id);if(el)el.addEventListener('change',checkMesFechado);});

/* --- Simular Cenário --- */
function calcSimulacao(){
    const recPct=+$('sim-rec').value, fixPct=+$('sim-desp-fix').value;
    const varPct=+$('sim-desp-var').value, extPct=+$('sim-rec-ext').value;
    $('sim-rec-val').textContent=(recPct>=0?'+':'')+recPct+'%';
    $('sim-fix-val').textContent=(fixPct>=0?'+':'')+fixPct+'%';
    $('sim-var-val').textContent=(varPct>=0?'+':'')+varPct+'%';
    $('sim-ext-val').textContent=(extPct>=0?'+':'')+extPct+'%';
    const slider=document.getElementById('sim-rec');
    slider.style.setProperty('--thumb',recPct>=0?'var(--ok)':'var(--err)');
    if(!dreData){$('sim-resultado').innerHTML='<div style="color:rgba(255,255,255,.5);font-size:.78rem;text-align:center;padding:.5rem">Gere o DRE primeiro para simular.</div>';return;}
    const d=dreData;
    if(d.custos_completos===false){$('sim-resultado').innerHTML='<div style="color:#fcd34d;font-size:.78rem;text-align:center;padding:.5rem">A simulação está indisponível enquanto houver vendas com custo ausente ou parcial.</div>';return;}
    const simRec  =parseFloat(d.receita_vendas)*(1+recPct/100);
    const simExt  =parseFloat(d.receita_extras)*(1+extPct/100);
    const simBruta=simRec+simExt;
    const simFix  =parseFloat(d.desp_fixas)*(1+fixPct/100);
    const simVar  =parseFloat(d.desp_variaveis)*(1+varPct/100);
    const simDesp =simFix+simVar+parseFloat(d.contas_pagas)+parseFloat(d.comissoes);
    const simLucro=simBruta-simDesp-parseFloat(d.custo_vendas||0);
    const diffLucro=simLucro-parseFloat(d.lucro_liquido);
    const isPos=simLucro>=0;
    const col=v=>v>=0?'#10b981':'#ef4444';
    $('sim-resultado').innerHTML=`
    <div class="sim-res-row"><span class="sim-res-lbl">Receita de Vendas</span><span class="sim-res-val" style="color:#10b981">${R(simRec)}</span></div>
    <div class="sim-res-row"><span class="sim-res-lbl">Receitas Extras</span><span class="sim-res-val" style="color:#10b981">${R(simExt)}</span></div>
    <div class="sim-res-row"><span class="sim-res-lbl">Receita Bruta</span><span class="sim-res-val" style="color:#10b981;font-size:.9rem">${R(simBruta)}</span></div>
    <div class="sim-res-row"><span class="sim-res-lbl">Total Despesas</span><span class="sim-res-val" style="color:#ef4444">${R(simDesp)}</span></div>
    <div class="sim-res-row" style="border-top:1px solid rgba(255,255,255,.15);margin-top:.4rem;padding-top:.8rem">
        <span style="color:#fff;font-weight:700;font-size:.9rem">Lucro Simulado</span>
        <div style="text-align:right">
            <div style="font-size:1.1rem;font-weight:700;color:${col(simLucro)}">${R(simLucro)}</div>
            <div style="font-size:.7rem;color:${col(diffLucro)};margin-top:2px">${diffLucro>=0?'▲ +':'▼ '}${R(Math.abs(diffLucro))} vs atual</div>
        </div>
    </div>`;
}
function resetSimulacao(){['sim-rec','sim-desp-fix','sim-desp-var','sim-rec-ext'].forEach(id=>{const el=$(id);if(el)el.value=0;});calcSimulacao();}
$('mod-simular').addEventListener('show',calcSimulacao);
// Trigger calcSimulacao when modal opens
const origOpenModal=openModal;
window.openModal=function(id){$(id).classList.add('show');if(id==='mod-simular')calcSimulacao();};

/* ════════════════════════════════════════════════════
   RELATÓRIOS FINANCEIROS
════════════════════════════════════════════════════ */
async function loadRelatorios(){
    const per=$('rel-per').value;
    const d=await get('relatorio_financeiro',`&periodo=${per}`);
    // area chart receita
    const rm=d.receita_por_mes||[];
    renderAreaChart('rel-chart',[{label:'Receita',color:'#10b981',data:rm.map(x=>x.receita)}],rm.map(x=>x.label));
    // top produtos
    const tp=d.top_produtos||[];
    $('rel-top-prods').innerHTML=tp.length?tp.map((p,i)=>`<div style="display:flex;align-items:center;gap:.6rem;padding:.5rem 0;border-bottom:1px solid var(--border)"><div style="width:20px;height:20px;border-radius:6px;background:var(--p-dim);color:var(--p);display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:800;flex-shrink:0">${i+1}</div><div style="flex:1;min-width:0"><div style="font-size:.83rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(p.nome)}</div><div style="font-size:.68rem;color:var(--faint)">${p.unidades} un.</div></div><div style="font-weight:700;color:var(--ok);font-size:.86rem">${R(p.receita)}</div></div>`).join(''):'<div class="empty"><span class="material-symbols-outlined">inventory</span><p>Sem dados</p></div>';
    // despesas categoria
    const dc=d.despesas_categoria||[];
    const totD=dc.reduce((s,c)=>s+parseFloat(c.total),0);
    $('rel-desp-cat').innerHTML=dc.length?dc.map(c=>{const pct=totD>0?(parseFloat(c.total)/totD*100).toFixed(0):0;return`<div style="margin-bottom:.6rem"><div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:.22rem"><span style="font-weight:600">${esc(c.categoria)}</span><span style="color:var(--err);font-weight:700">${R(c.total)}</span></div><div style="height:5px;border-radius:20px;background:var(--border)"><div style="height:5px;border-radius:20px;background:var(--err);width:${pct}%;transition:width .7s"></div></div></div>`;}).join(''):'<div class="empty"><span class="material-symbols-outlined">pie_chart</span><p>Sem despesas</p></div>';
    // vendedores
    const vt=d.vendedores||[];
    $('rel-vend-body').innerHTML=vt.length?vt.map(v=>`<tr><td><strong>${esc(v.nome)}</strong></td><td>${v.vendas}</td><td style="color:var(--ok);font-weight:700">${R(v.receita)}</td><td><span class="bx bl">${R(v.ticket)}</span></td></tr>`).join(''):`<tr><td colspan="4"><div class="empty"><span class="material-symbols-outlined">group</span><p>Sem dados</p></div></td></tr>`;
}

/* ════════════════════════════════════════════════════
   PAGINAÇÃO HELPER
════════════════════════════════════════════════════ */
function renderPag(id,pg,total,fn){const el=$(id);if(total<=1){el.innerHTML='';return;}let h=`<button class="pg-btn" onclick="(${fn})(${pg-1})" ${pg===1?'disabled':''}>‹</button>`;for(let i=1;i<=total;i++){if(total>7&&i>2&&i<total-1&&Math.abs(i-pg)>1){if(i===3||i===total-2)h+='<span style="padding:.3rem .2rem;color:var(--faint)">…</span>';continue;}h+=`<button class="pg-btn ${i===pg?'on':''}" onclick="(${fn})(${i})">${i}</button>`;}h+=`<button class="pg-btn" onclick="(${fn})(${pg+1})" ${pg===total?'disabled':''}>›</button>`;el.innerHTML=h;}

/* ════════════════════════════════════════════════════
   INIT
════════════════════════════════════════════════════ */
async function init(){
    try{
        const vr=await fetch('api.php?acao=listar_vendedores',{credentials:'same-origin'}).then(r=>r.json());
        vendsCache=Array.isArray(vr)?vr:[];
        fpCache=[]; // FP cache not needed now (removed from sidebar)
    }catch(e){vendsCache=[];}
    // vendedores → modal comissão
    const cv=$('com-vend');
    vendsCache.forEach(v=>{const o=document.createElement('option');o.value=v.id;o.textContent=v.nome;cv.appendChild(o);});
    // defaults
    $('desp-data').value=today();$('rec-data').value=today();
    $('cr-venc').value=today();$('cp-venc').value=today();
    $('com-ini').value=new Date(new Date().setDate(1)).toISOString().slice(0,10);
    $('com-fim').value=today();
    // redraw charts on resize
    window.addEventListener('resize',()=>{
        if(document.getElementById('view-dashboard').classList.contains('on')) loadDashboard();
        if(document.getElementById('view-relatorios').classList.contains('on')) loadRelatorios();
    });
    checkMesFechado();
    loadDashboard();
}
window.onload=init;
</script>
</body>
</html>
