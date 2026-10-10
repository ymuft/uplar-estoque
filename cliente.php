<?php
// cliente.php — Cadastro e gestão de clientes
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
$sub = check_and_block($user_id, $pdo);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Clientes — Uplar</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"/>
<style>
:root {
    --p:       #3b6ff5;
    --p-dark:  #2a55d9;
    --p-dim:   rgba(59,111,245,.08);
    --p-glow:  rgba(59,111,245,.20);
    --ink:     #0f1c2e;
    --muted:   #5a6a7a;
    --faint:   #94a3b8;
    --bg:      #f4f6fb;
    --card:    #ffffff;
    --border:  #e4eaf2;
    --ok:      #10b981;
    --ok-dim:  rgba(16,185,129,.09);
    --warn:    #f59e0b;
    --warn-dim:rgba(245,158,11,.09);
    --err:     #ef4444;
    --err-dim: rgba(239,68,68,.09);
    --sh:      0 1px 4px rgba(15,28,46,.06), 0 4px 16px rgba(15,28,46,.04);
    --sh-lift: 0 8px 32px rgba(59,111,245,.16);
    --r:       14px;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--ink);line-height:1.55;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
button{font-family:inherit;cursor:pointer}

@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes popIn{from{opacity:0;transform:scale(.94)}to{opacity:1;transform:scale(1)}}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
@keyframes pulse-ring{0%{box-shadow:0 0 0 0 var(--p-glow)}70%{box-shadow:0 0 0 8px transparent}100%{box-shadow:0 0 0 0 transparent}}
.anim{opacity:0;animation:fadeUp .5s cubic-bezier(.22,1,.36,1) forwards}
.d1{animation-delay:.04s}.d2{animation-delay:.10s}.d3{animation-delay:.16s}.d4{animation-delay:.22s}

.app{display:flex;min-height:100vh}

/* ── SIDEBAR (inalterada) ── */
.sb{width:248px;background:var(--card);border-right:1px solid var(--border);position:fixed;left:0;top:0;height:100vh;overflow-y:auto;z-index:200;display:flex;flex-direction:column;transition:transform .3s cubic-bezier(.22,1,.36,1)}
.sb-head{padding:1.4rem 1.3rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.sb-logo-icon{font-size:2rem;color:var(--p)}
.sb-logo-txt{font-size:1.25rem;font-weight:700;letter-spacing:-.3px}
.sb-user{padding:1rem 1.3rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.8rem;cursor:pointer;transition:background .2s}
.sb-user:hover{background:var(--p-dim)}
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

.main{margin-left:248px;flex:1;padding:1.75rem 2rem;min-width:0}
.overlay{display:none;position:fixed;inset:0;background:rgba(15,28,46,.4);z-index:199;backdrop-filter:blur(3px)}
.overlay.on{display:block}
.ham{display:none;background:var(--card);border:1px solid var(--border);border-radius:9px;padding:.42rem;align-items:center;justify-content:center;box-shadow:var(--sh);color:var(--ink)}
.ham .material-symbols-outlined{font-size:1.3rem}

/* ── TOPBAR ── */
.top{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.6rem;gap:1rem;flex-wrap:wrap}
.top-l{display:flex;align-items:center;gap:.8rem;min-width:0}
.greeting{font-size:1.45rem;font-weight:700;letter-spacing:-.4px}
.sub{color:var(--muted);font-size:.82rem;margin-top:1px}
.searchbox{display:flex;align-items:center;gap:.55rem;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:.52rem .95rem;transition:border-color .2s,box-shadow .2s;min-width:220px}
.searchbox:focus-within{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.searchbox .material-symbols-outlined{font-size:1.05rem;color:var(--faint)}
.searchbox input{border:none;outline:none;font-size:.855rem;width:100%;background:transparent;color:var(--ink)}
.searchbox input::placeholder{color:var(--faint)}

/* ── PILLS ── */
.pills{display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap}
.pill{display:inline-flex;align-items:center;gap:.4rem;padding:.52rem 1rem;border-radius:50px;font-size:.8rem;font-weight:600;border:none;transition:all .22s;white-space:nowrap}
.pill .material-symbols-outlined{font-size:1rem}
.pill-solid{background:var(--p);color:#fff;box-shadow:0 3px 12px var(--p-glow)}
.pill-solid:hover{background:var(--p-dark);transform:translateY(-2px);box-shadow:0 6px 20px var(--p-glow)}
.pill-outline{background:var(--card);color:var(--muted);border:1px solid var(--border)}
.pill-outline:hover{background:var(--p-dim);border-color:var(--p);color:var(--p);transform:translateY(-2px)}

/* ── KPI STRIP ── */
.kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:.85rem;margin-bottom:1.8rem}
.kpi{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:1.1rem 1.25rem;transition:transform .22s,box-shadow .22s;position:relative;overflow:hidden}
.kpi::after{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--p);border-radius:var(--r) var(--r) 0 0}
.kpi.g::after{background:var(--ok)}.kpi.o::after{background:var(--warn)}
.kpi:hover{transform:translateY(-3px);box-shadow:var(--sh-lift)}
.kpi-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:.65rem}
.kpi-ico{width:32px;height:32px;border-radius:9px;display:flex;align-items:center;justify-content:center;background:var(--p-dim);color:var(--p)}
.kpi.g .kpi-ico{background:var(--ok-dim);color:var(--ok)}.kpi.o .kpi-ico{background:var(--warn-dim);color:var(--warn)}
.kpi-ico .material-symbols-outlined{font-size:1.1rem}
.kpi-tag{font-size:.67rem;font-weight:700;padding:.15rem .5rem;border-radius:20px;background:var(--p-dim);color:var(--p)}
.kpi.g .kpi-tag{background:var(--ok-dim);color:var(--ok)}.kpi.o .kpi-tag{background:var(--warn-dim);color:var(--warn)}
.kpi-val{font-size:1.7rem;font-weight:700;letter-spacing:-.5px;line-height:1;margin-bottom:.2rem}
.kpi-lbl{font-size:.73rem;color:var(--muted);font-weight:500}

/* ── SECTION HEADER ── */
.section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.1rem}
.section-title{font-size:.92rem;font-weight:700;color:var(--ink);display:flex;align-items:center;gap:.5rem}
.count-badge{display:inline-flex;align-items:center;background:var(--p);color:#fff;border-radius:20px;font-size:.65rem;font-weight:700;padding:.12rem .52rem}
.filter-tabs{display:flex;gap:.3rem}
.ftab{background:none;border:1px solid var(--border);border-radius:8px;padding:.28rem .7rem;font-size:.75rem;font-weight:600;color:var(--muted);transition:all .18s;cursor:pointer}
.ftab.on,.ftab:hover{background:var(--p-dim);border-color:var(--p);color:var(--p)}

/* ── CARDS GRID ── */
.cards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.1rem}

/* ── CLIENT CARD (reformulado) ── */
.client-card{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:16px;
    overflow:hidden;
    cursor:pointer;
    transition:transform .25s cubic-bezier(.22,1,.36,1), box-shadow .25s, border-color .25s;
    position:relative;
    display:flex;
    flex-direction:column;
}
.client-card:hover{
    transform:translateY(-4px);
    box-shadow:0 12px 40px rgba(59,111,245,.13);
    border-color:rgba(59,111,245,.28);
}
.client-card:hover .cc-arrow{opacity:1;transform:translateX(0)}
.client-card.inactive{opacity:.75}
.client-card.inactive .cc-banner{background:linear-gradient(135deg,#94a3b8,#b0bec5)}

/* banner colorido no topo */
.cc-banner{
    height:68px;
    background:linear-gradient(135deg,var(--p),#5a8dff);
    position:relative;
    flex-shrink:0;
}
.cc-banner.av-blue{background:linear-gradient(135deg,#3b6ff5,#7ca0ff)}
.cc-banner.av-green{background:linear-gradient(135deg,#10b981,#34d399)}
.cc-banner.av-purple{background:linear-gradient(135deg,#8b5cf6,#a78bfa)}
.cc-banner.av-orange{background:linear-gradient(135deg,#f59e0b,#fbbf24)}
.cc-banner.av-red{background:linear-gradient(135deg,#ef4444,#f87171)}
.cc-banner.av-teal{background:linear-gradient(135deg,#0ea5e9,#38bdf8)}
/* decorativo */
.cc-banner::before{content:'';position:absolute;right:-18px;top:-18px;width:90px;height:90px;border-radius:50%;background:rgba(255,255,255,.1)}
.cc-banner::after{content:'';position:absolute;right:28px;bottom:-22px;width:52px;height:52px;border-radius:50%;background:rgba(255,255,255,.07)}

/* badge status no banner */
.cc-badge{position:absolute;top:.6rem;right:.7rem;display:inline-flex;align-items:center;gap:3px;font-size:.65rem;font-weight:700;padding:.22rem .55rem;border-radius:20px;backdrop-filter:blur(6px)}
.cc-badge.on{background:rgba(16,185,129,.2);color:#d1fae5;border:1px solid rgba(16,185,129,.3)}
.cc-badge.off{background:rgba(239,68,68,.2);color:#fee2e2;border:1px solid rgba(239,68,68,.3)}
.cc-badge .dot{width:5px;height:5px;border-radius:50%;background:currentColor;display:inline-block}

/* avatar flutuante */
.cc-av-wrap{padding:0 1.15rem;position:relative;height:0;margin-bottom:0}
.cc-av{
    width:52px;height:52px;border-radius:13px;
    display:flex;align-items:center;justify-content:center;
    font-weight:700;font-size:1rem;color:#fff;text-transform:uppercase;
    border:3px solid var(--card);
    position:relative;top:-26px;
    box-shadow:0 4px 16px rgba(0,0,0,.12);
    flex-shrink:0;
}
.cc-av.av-blue{background:linear-gradient(135deg,#3b6ff5,#7ca0ff)}
.cc-av.av-green{background:linear-gradient(135deg,#10b981,#34d399)}
.cc-av.av-purple{background:linear-gradient(135deg,#8b5cf6,#a78bfa)}
.cc-av.av-orange{background:linear-gradient(135deg,#f59e0b,#fbbf24)}
.cc-av.av-red{background:linear-gradient(135deg,#ef4444,#f87171)}
.cc-av.av-teal{background:linear-gradient(135deg,#0ea5e9,#38bdf8)}

/* corpo do card */
.cc-body{padding:0 1.15rem 1.1rem;margin-top:-14px;flex:1;display:flex;flex-direction:column;gap:.55rem}
.cc-title-row{display:flex;align-items:flex-start;justify-content:space-between;gap:.5rem}
.cc-name{font-weight:700;font-size:.95rem;letter-spacing:-.2px;line-height:1.25;flex:1}
.cc-arrow{width:22px;height:22px;border-radius:6px;background:var(--p-dim);color:var(--p);display:flex;align-items:center;justify-content:center;flex-shrink:0;opacity:0;transform:translateX(-4px);transition:opacity .2s,transform .2s}
.cc-arrow .material-symbols-outlined{font-size:.85rem}
.cc-cnpj{font-size:.71rem;color:var(--faint);margin-top:-2px}

/* meta rows */
.cc-meta{display:flex;flex-direction:column;gap:.32rem}
.cc-row{display:flex;align-items:center;gap:.45rem;font-size:.76rem;color:var(--muted)}
.cc-row .material-symbols-outlined{font-size:.95rem;color:var(--faint);flex-shrink:0;width:16px}
.cc-row span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}

/* visita */
.cc-visit-row{display:flex;align-items:center;gap:.4rem;font-size:.72rem;padding:.32rem .55rem;border-radius:8px;background:var(--bg);color:var(--muted)}
.cc-visit-row .material-symbols-outlined{font-size:.9rem}
.cc-visit-row.warn{background:var(--warn-dim);color:var(--warn)}
.cc-visit-row.ok{background:var(--ok-dim);color:var(--ok)}

/* produtos */
.cc-prods{display:flex;flex-wrap:wrap;gap:.28rem}
.cc-prod-tag{background:var(--p-dim);color:var(--p);border-radius:20px;padding:.13rem .5rem;font-size:.67rem;font-weight:600}
.cc-prod-more{background:var(--bg);color:var(--muted);border:1px solid var(--border);border-radius:20px;padding:.13rem .5rem;font-size:.67rem;font-weight:600}

/* footer ações */
.cc-footer{display:flex;gap:.4rem;padding:.75rem 1.1rem;border-top:1px solid var(--border);background:var(--bg);margin-top:auto}
.cc-act{flex:1;display:flex;align-items:center;justify-content:center;gap:.3rem;padding:.4rem .5rem;border-radius:8px;border:none;font-size:.72rem;font-weight:600;transition:all .16s;cursor:pointer}
.cc-act .material-symbols-outlined{font-size:.9rem}
.cc-act-e{background:transparent;color:var(--muted);border:1px solid var(--border)}.cc-act-e:hover{background:var(--p-dim);border-color:var(--p);color:var(--p)}
.cc-act-d{background:transparent;color:var(--faint);border:1px solid var(--border)}.cc-act-d:hover{background:var(--err-dim);border-color:var(--err);color:var(--err)}

/* skeleton */
.sk{border-radius:7px;background:linear-gradient(90deg,var(--bg) 25%,#e8ecf2 50%,var(--bg) 75%);background-size:200% 100%;animation:shimmer 1.3s infinite}
.sk-card{background:var(--card);border:1px solid var(--border);border-radius:16px;overflow:hidden}

/* empty */
.empty{text-align:center;padding:3.5rem 1rem;color:var(--faint);grid-column:1/-1}
.empty .material-symbols-outlined{font-size:2.8rem;display:block;margin-bottom:.8rem;opacity:.25}
.empty-t{font-size:.88rem;font-weight:600;color:var(--muted);margin-bottom:.4rem}
.empty p{font-size:.8rem}

/* ── MODAL FORM ── */
.modal{display:none;position:fixed;inset:0;background:rgba(15,28,46,.55);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(5px)}
.mbox{background:var(--card);border-radius:18px;width:90%;max-width:700px;padding:1.7rem;max-height:93vh;overflow-y:auto;box-shadow:0 24px 64px rgba(0,0,0,.22);animation:popIn .28s cubic-bezier(.22,1,.36,1) both}
.mh{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.4rem}
.mh h2{font-size:1.05rem;font-weight:700}
.mcl{font-size:1.75rem;cursor:pointer;color:var(--faint);line-height:1;transition:color .15s}
.mcl:hover{color:var(--ink)}
.msec{margin-bottom:1.2rem}
.msec-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--p);border-bottom:1px solid var(--border);padding-bottom:.4rem;margin-bottom:.9rem;display:flex;align-items:center;gap:.35rem}
.msec-title .material-symbols-outlined{font-size:1rem}
.fg{display:grid;grid-template-columns:1fr 1fr;gap:.85rem}
.fw{grid-column:1/-1}
.f3{grid-column:span 1}
.fl{display:flex;flex-direction:column;gap:.3rem}
.fl label{font-size:.72rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
.fl input,.fl textarea,.fl select{padding:.65rem .9rem;border:1px solid var(--border);border-radius:9px;font-size:.88rem;font-family:inherit;transition:border-color .18s,box-shadow .18s;outline:none;background:var(--card);color:var(--ink)}
.fl input:focus,.fl textarea:focus,.fl select:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.fl input::placeholder,.fl textarea::placeholder{color:var(--faint)}

/* prod selector */
.prod-selector{border:1px solid var(--border);border-radius:10px;overflow:hidden}
.prod-search-bar{display:flex;align-items:center;gap:.5rem;padding:.6rem .8rem;border-bottom:1px solid var(--border);background:var(--bg)}
.prod-search-bar .material-symbols-outlined{font-size:1rem;color:var(--faint)}
.prod-search-bar input{border:none;outline:none;font-size:.83rem;background:transparent;color:var(--ink);flex:1}
.prod-search-bar input::placeholder{color:var(--faint)}
.prod-search-bar .prod-count{font-size:.7rem;font-weight:600;color:var(--p);background:var(--p-dim);border-radius:20px;padding:.1rem .45rem;white-space:nowrap}
.prod-list{max-height:200px;overflow-y:auto;padding:.3rem}
.prod-item{display:flex;align-items:center;gap:.65rem;padding:.5rem .6rem;border-radius:8px;cursor:pointer;transition:background .15s;user-select:none}
.prod-item:hover{background:var(--p-dim)}
.prod-item.selected{background:var(--p-dim)}
.prod-item input[type=checkbox]{accent-color:var(--p);width:15px;height:15px;flex-shrink:0;cursor:pointer}
.prod-item-info{flex:1;min-width:0}
.prod-item-name{font-size:.82rem;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.prod-item-meta{font-size:.68rem;color:var(--faint);margin-top:1px}
.prod-item-price{font-size:.78rem;font-weight:600;color:var(--ok);white-space:nowrap}
.prod-empty{text-align:center;padding:1.4rem;color:var(--faint);font-size:.8rem}
.prod-empty .material-symbols-outlined{font-size:1.4rem;display:block;margin-bottom:.35rem;opacity:.35}
.prod-selected-preview{display:flex;flex-wrap:wrap;gap:.3rem;padding:.55rem .7rem;border-top:1px solid var(--border);background:var(--bg);min-height:38px}
.prod-selected-preview .ps-empty{font-size:.72rem;color:var(--faint);align-self:center}
.ps-tag{display:inline-flex;align-items:center;gap:.25rem;background:var(--p-dim);color:var(--p);border-radius:20px;padding:.15rem .5rem .15rem .6rem;font-size:.7rem;font-weight:600}
.ps-tag button{background:none;border:none;cursor:pointer;color:var(--p);display:flex;align-items:center;padding:0;opacity:.7;transition:opacity .15s}
.ps-tag button:hover{opacity:1}
.ps-tag button .material-symbols-outlined{font-size:.8rem}
.btn-save{background:var(--p);color:#fff;border:none;padding:.8rem;border-radius:10px;font-weight:700;width:100%;font-size:.88rem;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:.45rem;transition:all .22s;cursor:pointer;box-shadow:0 4px 14px var(--p-glow);margin-top:.5rem}
.btn-save:hover{background:var(--p-dark);transform:translateY(-2px)}
.btn-save:disabled{background:var(--faint);box-shadow:none;transform:none;cursor:not-allowed}

/* ── MODAL DETALHE (reformulado) ── */
.det-banner{height:90px;border-radius:14px 14px 0 0;position:relative;overflow:hidden;margin:-1.7rem -1.7rem 0;flex-shrink:0}
.det-banner::before{content:'';position:absolute;right:-24px;top:-24px;width:130px;height:130px;border-radius:50%;background:rgba(255,255,255,.1)}
.det-banner::after{content:'';position:absolute;left:30px;bottom:-32px;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.07)}
.det-header{display:flex;align-items:flex-end;gap:1rem;padding:0 1.7rem;position:relative;height:0;top:-30px;margin-bottom:-10px}
.det-av{width:60px;height:60px;border-radius:15px;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:1.15rem;border:3px solid var(--card);box-shadow:0 4px 20px rgba(0,0,0,.14);flex-shrink:0}
.det-name-block{margin-bottom:4px}
.det-name-block h2{font-size:1.1rem;font-weight:700;letter-spacing:-.3px;line-height:1.2}
.det-name-block p{font-size:.75rem;color:var(--muted);margin-top:2px}
.det-status-badge{display:inline-flex;align-items:center;gap:3px;font-size:.65rem;font-weight:700;padding:.2rem .55rem;border-radius:20px;margin-top:4px}
.det-status-badge.on{background:var(--ok-dim);color:var(--ok)}
.det-status-badge.off{background:var(--err-dim);color:var(--err)}

.det-section{margin-bottom:1.2rem}
.det-section-title{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--faint);margin-bottom:.7rem;display:flex;align-items:center;gap:.35rem}
.det-section-title .material-symbols-outlined{font-size:.9rem}
.det-grid{display:grid;grid-template-columns:1fr 1fr;gap:.6rem 1.2rem}
.det-item .dl{font-size:.68rem;color:var(--faint);margin-bottom:2px;font-weight:500}
.det-item .dv{font-size:.84rem;font-weight:500;color:var(--ink)}
.det-item.full{grid-column:1/-1}
.prod-tag-list{display:flex;flex-wrap:wrap;gap:.4rem;margin-top:.2rem}
.prod-tag{background:var(--p-dim);color:var(--p);border-radius:20px;padding:.2rem .65rem;font-size:.72rem;font-weight:600}
.det-actions{display:flex;gap:.5rem;margin-top:1.4rem}
.det-act{flex:1;display:flex;align-items:center;justify-content:center;gap:.4rem;padding:.62rem;border-radius:10px;border:none;font-size:.82rem;font-weight:600;cursor:pointer;transition:all .18s}
.det-act-edit{background:var(--p);color:#fff;box-shadow:0 3px 12px var(--p-glow)}.det-act-edit:hover{background:var(--p-dark);transform:translateY(-1px)}
.det-act-del{background:var(--err-dim);color:var(--err)}.det-act-del:hover{background:rgba(239,68,68,.18)}

/* divider */
.det-div{height:1px;background:var(--border);margin:.6rem 0}

/* ── BOTTOM NAV ── */
.bnav{display:none;position:fixed;bottom:0;left:0;right:0;background:var(--card);border-top:1px solid var(--border);z-index:300;padding:.35rem 0 calc(.35rem + env(safe-area-inset-bottom));box-shadow:0 -2px 16px rgba(15,28,46,.07)}
.bnav-inner{display:flex;justify-content:space-around}
.bnav-a{display:flex;flex-direction:column;align-items:center;gap:2px;padding:.35rem .6rem;border-radius:9px;color:var(--faint);font-size:.62rem;font-weight:600;transition:all .18s;flex:1}
.bnav-a .material-symbols-outlined{font-size:1.25rem}
.bnav-a.on{color:var(--p)}
.bnav-a:hover{background:var(--p-dim)}

/* ── RESPONSIVE ── */
@media(max-width:860px){
    .sb{transform:translateX(-100%)}.sb.open{transform:translateX(0)}.main{margin-left:0}.ham{display:flex}
    .kpis{grid-template-columns:1fr 1fr}
}
@media(max-width:600px){
    .bnav{display:block}.main{padding:.9rem .9rem calc(68px + env(safe-area-inset-bottom))}
    .top{flex-direction:column;align-items:flex-start;margin-bottom:1rem}
    .searchbox{min-width:0;width:100%}.greeting{font-size:1.2rem}
    .kpis{grid-template-columns:1fr 1fr;gap:.6rem}.kpi-val{font-size:1.4rem}
    .cards-grid{grid-template-columns:1fr}
    .fg{grid-template-columns:1fr}
    .det-grid{grid-template-columns:1fr}
    .mbox{padding:1.2rem;width:96%;max-height:96vh}
    .det-banner{margin:-1.2rem -1.2rem 0}
    .det-header{padding:0 1.2rem}
}
@media(max-width:380px){.kpis{grid-template-columns:1fr}}
</style>
<link rel="stylesheet" href="assets/app-theme.css?v=20260723">
<script src="assets/app-theme.js?v=20260723"></script>
</head>
<body>
<div class="app">

<div class="overlay" id="ov"></div>

<!-- ══ SIDEBAR (inalterada) ══ -->
<?php require __DIR__ . '/includes/sidebar.php'; ?>

<!-- ══ MAIN ══ -->
<main class="main">

    <!-- topbar -->
    <div class="top anim d1">
        <div class="top-l">
            <button class="ham" id="ham"><span class="material-symbols-outlined">menu</span></button>
            <div>
                <div class="greeting">Clientes</div>
                <div class="sub">Gerencie seus clientes e produtos vendidos</div>
            </div>
        </div>
        <div class="searchbox">
            <span class="material-symbols-outlined">search</span>
            <input id="busca" placeholder="Buscar empresa, CNPJ, cidade…" type="text">
        </div>
    </div>

    <!-- pills -->
    <div class="pills anim d2">
        <button class="pill pill-solid" id="btnNovo">
            <span class="material-symbols-outlined">add</span>Novo Cliente
        </button>
        <a href="estoque.php" class="pill pill-outline">
            <span class="material-symbols-outlined">dashboard</span>Dashboard
        </a>
        <a href="vendedores.php" class="pill pill-outline">
            <span class="material-symbols-outlined">group</span>Vendedores
        </a>
    </div>

    <!-- KPIs -->
    <div class="kpis anim d3" id="kpis">
        <div class="kpi sk-card"><div class="sk" style="height:68px;border-radius:0"></div><div style="padding:1rem"><div class="sk" style="height:1.7rem;width:50%;margin-bottom:.4rem"></div><div class="sk" style="height:.7rem;width:65%"></div></div></div>
        <div class="kpi sk-card"><div class="sk" style="height:68px;border-radius:0"></div><div style="padding:1rem"><div class="sk" style="height:1.7rem;width:50%;margin-bottom:.4rem"></div><div class="sk" style="height:.7rem;width:65%"></div></div></div>
        <div class="kpi sk-card"><div class="sk" style="height:68px;border-radius:0"></div><div style="padding:1rem"><div class="sk" style="height:1.7rem;width:50%;margin-bottom:.4rem"></div><div class="sk" style="height:.7rem;width:65%"></div></div></div>
    </div>

    <!-- cards section -->
    <div class="section-header anim d4">
        <div class="section-title">
            Todos os clientes
            <span class="count-badge" id="totalBadge">0</span>
        </div>
        <div class="filter-tabs">
            <button class="ftab on" data-filter="todos">Todos</button>
            <button class="ftab" data-filter="ativos">Ativos</button>
            <button class="ftab" data-filter="inativos">Inativos</button>
        </div>
    </div>
    <div class="cards-grid anim d4" id="cardsGrid">
        <?php for($i=0;$i<6;$i++): ?>
        <div class="sk-card">
            <div class="sk" style="height:68px;border-radius:0"></div>
            <div style="padding:1.1rem">
                <div class="sk" style="height:.9rem;width:70%;margin-bottom:.6rem"></div>
                <div class="sk" style="height:.7rem;width:50%;margin-bottom:.9rem"></div>
                <div class="sk" style="height:.7rem;width:80%;margin-bottom:.4rem"></div>
                <div class="sk" style="height:.7rem;width:60%"></div>
            </div>
        </div>
        <?php endfor; ?>
    </div>

</main>
</div>

<!-- ══ BOTTOM NAV ══ -->
<nav class="bnav">
    <div class="bnav-inner">
        <a href="estoque.php"    class="bnav-a"><span class="material-symbols-outlined">dashboard</span>Início</a>
        <a href="relatorio.php"  class="bnav-a"><span class="material-symbols-outlined">bar_chart</span>Relatórios</a>
        <a href="produtos.php"   class="bnav-a"><span class="material-symbols-outlined">inventory</span>Produtos</a>
        <a href="cliente.php"    class="bnav-a on"><span class="material-symbols-outlined">handshake</span>Clientes</a>
    </div>
</nav>

<!-- ══ MODAL CADASTRO / EDIÇÃO ══ -->
<div class="modal" id="modal">
    <div class="mbox">
        <div class="mh">
            <h2 id="mtitle">Novo Cliente</h2>
            <span class="mcl" id="mclose">close</span>
        </div>
        <input type="hidden" id="cId">

        <div class="msec">
            <div class="msec-title"><span class="material-symbols-outlined">business</span>Dados da empresa</div>
            <div class="fg">
                <div class="fl fw"><label>Nome da empresa *</label><input id="cNome" placeholder="Ex: Tech Solutions Ltda"></div>
                <div class="fl"><label>CNPJ</label><input id="cCnpj" placeholder="00.000.000/0001-00" maxlength="18"></div>
                <div class="fl"><label>Nome do contato</label><input id="cContato" placeholder="Ex: João Silva"></div>
                <div class="fl"><label>Telefone</label><input id="cTelefone" placeholder="(92) 99999-9999"></div>
                <div class="fl fw"><label>E-mail</label><input id="cEmail" type="email" placeholder="contato@empresa.com.br"></div>
            </div>
        </div>

        <div class="msec">
            <div class="msec-title"><span class="material-symbols-outlined">location_on</span>Endereço</div>
            <div class="fg">
                <div class="fl"><label>CEP</label><input id="cCep" placeholder="00000-000" maxlength="9"></div>
                <div class="fl"><label>Bairro</label><input id="cBairro" placeholder="Bairro"></div>
                <div class="fl fw"><label>Logradouro</label><input id="cLogradouro" placeholder="Rua, Avenida…"></div>
                <div class="fl f3"><label>Número</label><input id="cNumero" placeholder="123"></div>
                <div class="fl f3"><label>Complemento</label><input id="cComplemento" placeholder="Sala, Bloco…"></div>
                <div class="fl"><label>Cidade</label><input id="cCidade" placeholder="Manaus"></div>
                <div class="fl"><label>Estado (UF)</label>
                    <select id="cEstado">
                        <option value="">Selecione</option>
                        <?php foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
                        <option value="<?=$uf?>"><?=$uf?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="msec">
            <div class="msec-title"><span class="material-symbols-outlined">inventory_2</span>Produtos associados</div>
            <div class="prod-selector">
                <div class="prod-search-bar">
                    <span class="material-symbols-outlined">search</span>
                    <input id="prodSearch" placeholder="Buscar produto por nome ou código…" type="text">
                    <span class="prod-count" id="prodSelCount">0 selecionados</span>
                </div>
                <div class="prod-list" id="prodList"></div>
                <div class="prod-selected-preview" id="prodPreview">
                    <span class="ps-empty">Nenhum produto selecionado</span>
                </div>
            </div>
        </div>

        <div class="msec">
            <div class="msec-title"><span class="material-symbols-outlined">edit_note</span>Mais informações</div>
            <div class="fg">
                <div class="fl"><label>Última visita</label><input id="cVisita" type="date"></div>
                <div class="fl"><label>Status</label>
                    <select id="cAtivo">
                        <option value="1">Ativo</option>
                        <option value="0">Inativo</option>
                    </select>
                </div>
                <div class="fl fw"><label>Observações</label><textarea id="cObs" rows="3" placeholder="Notas, informações relevantes…"></textarea></div>
            </div>
        </div>

        <button class="btn-save" id="btnSave">
            <span class="material-symbols-outlined">save</span>Salvar Cliente
        </button>
    </div>
</div>

<!-- ══ MODAL DETALHE (reformulado) ══ -->
<div class="modal" id="modalDet">
    <div class="mbox" style="max-width:560px;padding-top:0">
        <div id="detBody"></div>
    </div>
</div>

<script>
const CSRF = <?= json_encode(get_csrf_token()) ?>;
const $ = id => document.getElementById(id);
const esc = v => String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const fmt = d => { if(!d) return '—'; const [y,m,dd]=d.split('-'); return `${dd}/${m}/${y}` };
const fmtR = v => 'R$ '+Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});

const AV_COLORS = ['av-blue','av-green','av-purple','av-orange','av-red','av-teal'];
function avColor(name){ let h=0; for(let c of name) h=(h*31+c.charCodeAt(0))&0xff; return AV_COLORS[h%AV_COLORS.length]; }
function initials(name){ return name.replace(/[^a-zA-ZÀ-ÿ\s]/g,'').trim().split(/\s+/).slice(0,2).map(w=>w[0]||'').join('').toUpperCase()||'??'; }

/* sidebar */
const sb=$('sb'),ov=$('ov');
$('ham').addEventListener('click',()=>{sb.classList.add('open');ov.classList.add('on');document.body.style.overflow='hidden'});
ov.addEventListener('click',()=>{sb.classList.remove('open');ov.classList.remove('on');document.body.style.overflow=''});

let clientes=[], produtos=[], selectedProdIds=new Set(), currentFilter='todos';

/* ── CEP auto-fill ── */
$('cCep').addEventListener('blur', async()=>{
    const cep=$('cCep').value.replace(/\D/g,'');
    if(cep.length!==8)return;
    try{
        const r=await fetch(`https://viacep.com.br/ws/${cep}/json/`);
        const d=await r.json();
        if(d.erro)return;
        $('cLogradouro').value=d.logradouro||'';
        $('cBairro').value=d.bairro||'';
        $('cCidade').value=d.localidade||'';
        $('cEstado').value=d.uf||'';
    }catch(e){}
});

/* masks */
$('cCnpj').addEventListener('input',e=>{
    let v=e.target.value.replace(/\D/g,'').slice(0,14);
    if(v.length>12)v=v.slice(0,2)+'.'+v.slice(2,5)+'.'+v.slice(5,8)+'/'+v.slice(8,12)+'-'+v.slice(12);
    else if(v.length>8)v=v.slice(0,2)+'.'+v.slice(2,5)+'.'+v.slice(5,8)+'/'+v.slice(8);
    else if(v.length>5)v=v.slice(0,2)+'.'+v.slice(2,5)+'.'+v.slice(5);
    else if(v.length>2)v=v.slice(0,2)+'.'+v.slice(2);
    e.target.value=v;
});
$('cCep').addEventListener('input',e=>{
    let v=e.target.value.replace(/\D/g,'').slice(0,8);
    if(v.length>5)v=v.slice(0,5)+'-'+v.slice(5);
    e.target.value=v;
});

/* ── FILTER TABS ── */
document.querySelectorAll('.ftab').forEach(btn=>{
    btn.addEventListener('click',()=>{
        document.querySelectorAll('.ftab').forEach(b=>b.classList.remove('on'));
        btn.classList.add('on');
        currentFilter=btn.dataset.filter;
        renderCards();
    });
});

/* ── SELETOR DE PRODUTOS ── */
function renderProdSelector(){
    const q=$('prodSearch').value.toLowerCase().trim();
    const filtered=produtos.filter(p=>
        (p.nome||'').toLowerCase().includes(q)||
        (p.codigo||'').toLowerCase().includes(q)
    );
    const list=$('prodList');
    if(!produtos.length){
        list.innerHTML='<div class="prod-empty"><span class="material-symbols-outlined">inventory_2</span>Nenhum produto cadastrado</div>';
    } else if(!filtered.length){
        list.innerHTML='<div class="prod-empty"><span class="material-symbols-outlined">search_off</span>Nenhum produto encontrado</div>';
    } else {
        list.innerHTML=filtered.map(p=>{
            const sel=selectedProdIds.has(p.id)?'selected':'';
            const chk=selectedProdIds.has(p.id)?'checked':'';
            return`<div class="prod-item ${sel}" data-id="${p.id}">
                <input type="checkbox" id="pc_${p.id}" value="${p.id}" ${chk}>
                <div class="prod-item-info">
                    <div class="prod-item-name">${esc(p.nome)}</div>
                    <div class="prod-item-meta">${esc(p.codigo||'—')}</div>
                </div>
                <span class="prod-item-price">${fmtR(p.preco)}</span>
            </div>`;
        }).join('');
        list.querySelectorAll('.prod-item').forEach(el=>{
            el.addEventListener('click',e=>{
                if(e.target.tagName==='INPUT')return;
                toggleProd(+el.dataset.id);
            });
            el.querySelector('input[type=checkbox]').addEventListener('change',e=>{
                toggleProd(+e.target.value);
                e.stopPropagation();
            });
        });
    }
    renderProdPreview();
}

function toggleProd(id){
    if(selectedProdIds.has(id)) selectedProdIds.delete(id);
    else selectedProdIds.add(id);
    renderProdSelector();
}

function renderProdPreview(){
    const preview=$('prodPreview');
    const sel=[...selectedProdIds];
    $('prodSelCount').textContent=sel.length+' selecionado'+(sel.length!==1?'s':'');
    if(!sel.length){
        preview.innerHTML='<span class="ps-empty">Nenhum produto selecionado</span>';
        return;
    }
    preview.innerHTML=sel.map(id=>{
        const p=produtos.find(x=>x.id===id);
        if(!p)return'';
        return`<span class="ps-tag">${esc(p.nome)}<button type="button" data-id="${id}"><span class="material-symbols-outlined">close</span></button></span>`;
    }).join('');
    preview.querySelectorAll('button[data-id]').forEach(btn=>{
        btn.addEventListener('click',()=>{ selectedProdIds.delete(+btn.dataset.id); renderProdSelector(); });
    });
}

$('prodSearch').addEventListener('input',renderProdSelector);

/* ── abrir modal form ── */
function openM(c=null){
    $('mtitle').textContent=c?'Editar Cliente':'Novo Cliente';
    $('cId').value=c?.id??'';
    $('cNome').value=c?.nome_empresa??'';
    $('cCnpj').value=c?.cnpj??'';
    $('cContato').value=c?.contato_nome??'';
    $('cTelefone').value=c?.telefone??'';
    $('cEmail').value=c?.email??'';
    $('cCep').value=c?.cep??'';
    $('cLogradouro').value=c?.logradouro??'';
    $('cNumero').value=c?.numero??'';
    $('cComplemento').value=c?.complemento??'';
    $('cBairro').value=c?.bairro??'';
    $('cCidade').value=c?.cidade??'';
    $('cEstado').value=c?.estado??'';
    $('cVisita').value=c?.ultima_visita??'';
    $('cAtivo').value=c?.ativo??1;
    $('cObs').value=c?.observacao??'';
    $('prodSearch').value='';
    selectedProdIds=new Set((c?.produtos||[]).map(p=>p.produto_id));
    renderProdSelector();
    $('modal').style.display='flex';
}

$('btnNovo').addEventListener('click',()=>openM());
$('mclose').addEventListener('click',()=>$('modal').style.display='none');
$('modal').addEventListener('click',e=>{if(e.target===$('modal'))$('modal').style.display='none'});
$('modalDet').addEventListener('click',e=>{if(e.target===$('modalDet'))$('modalDet').style.display='none'});

/* ── salvar ── */
$('btnSave').addEventListener('click',async()=>{
    const id=$('cId').value;
    const nome=$('cNome').value.trim();
    if(!nome){alert('Nome da empresa é obrigatório');$('cNome').focus();return}
    const prodIds=[...selectedProdIds];
    const body={
        csrf_token:CSRF,id,nome_empresa:nome,
        cnpj:$('cCnpj').value.trim(),contato_nome:$('cContato').value.trim(),
        telefone:$('cTelefone').value.trim(),email:$('cEmail').value.trim(),
        cep:$('cCep').value.trim(),logradouro:$('cLogradouro').value.trim(),
        numero:$('cNumero').value.trim(),complemento:$('cComplemento').value.trim(),
        bairro:$('cBairro').value.trim(),cidade:$('cCidade').value.trim(),
        estado:$('cEstado').value,ultima_visita:$('cVisita').value,
        ativo:$('cAtivo').value,observacao:$('cObs').value.trim(),
        produto_ids:JSON.stringify(prodIds),
    };
    const fd=new FormData();
    Object.entries(body).forEach(([k,v])=>fd.append(k,v));
    const acao=id?'editar_cliente':'adicionar_cliente';
    const btn=$('btnSave');
    btn.disabled=true;btn.innerHTML='<span class="material-symbols-outlined">hourglass_empty</span>Salvando…';
    try{
        const r=await fetch(`api.php?acao=${acao}`,{method:'POST',body:fd,credentials:'same-origin'});
        const j=await r.json();
        if(j.ok){$('modal').style.display='none';await loadAll();alert(id?'Cliente atualizado!':'Cliente cadastrado!')}
        else alert('Erro: '+(j.error||'Falha'));
    }catch(e){alert('Erro: '+e.message)}
    btn.disabled=false;btn.innerHTML='<span class="material-symbols-outlined">save</span>Salvar Cliente';
});

/* ── deletar ── */
async function delCliente(id,nome){
    if(!confirm(`Excluir "${nome}"?`))return;
    const fd=new FormData();fd.append('id',id);fd.append('csrf_token',CSRF);
    const r=await fetch('api.php?acao=deletar_cliente',{method:'POST',body:fd,credentials:'same-origin'});
    const j=await r.json();
    j.ok?(await loadAll(),alert('Cliente excluído!')):alert('Erro: '+(j.error||'Falha'));
}

/* ── modal detalhe (reformulado) ── */
function verDetalhe(c){
    const col=avColor(c.nome_empresa||'');
    const ini=initials(c.nome_empresa||'?');
    const prods=c.produtos||[];
    const loc=[c.logradouro,c.numero?'Nº'+c.numero:'',c.complemento,c.bairro,c.cidade+(c.estado?'/'+c.estado:''),c.cep?'CEP '+c.cep:''].filter(Boolean).join(' · ')||'—';

    $('detBody').innerHTML=`
        <!-- banner -->
        <div class="det-banner ${col}" style="margin-bottom:0"></div>
        <!-- fechar -->
        <div style="position:absolute;top:.9rem;right:1rem;z-index:10">
            <span class="mcl" id="detClose2" style="color:rgba(255,255,255,.85);text-shadow:0 1px 4px rgba(0,0,0,.3)">close</span>
        </div>
        <!-- avatar flutuante -->
        <div style="padding:0 1.7rem;position:relative;top:-30px;margin-bottom:-4px;display:flex;align-items:flex-end;gap:1rem">
            <div class="det-av ${col}">${esc(ini)}</div>
            <div style="margin-bottom:6px">
                <div style="font-size:1.05rem;font-weight:700;letter-spacing:-.3px;line-height:1.25">${esc(c.nome_empresa)}</div>
                <div style="font-size:.75rem;color:var(--muted);margin-top:2px">${esc(c.cnpj||'Sem CNPJ')}</div>
                <span class="det-status-badge ${+c.ativo?'on':'off'}" style="margin-top:4px">
                    <span style="width:5px;height:5px;border-radius:50%;background:currentColor;display:inline-block"></span>
                    ${+c.ativo?'Ativo':'Inativo'}
                </span>
            </div>
        </div>

        <div style="padding:0 1.7rem 1.5rem">
            <!-- contato -->
            <div class="det-section">
                <div class="det-section-title"><span class="material-symbols-outlined">person</span>Contato</div>
                <div class="det-grid">
                    <div class="det-item"><div class="dl">Responsável</div><div class="dv">${esc(c.contato_nome||'—')}</div></div>
                    <div class="det-item"><div class="dl">Telefone</div><div class="dv">${esc(c.telefone||'—')}</div></div>
                    <div class="det-item full"><div class="dl">E-mail</div><div class="dv">${esc(c.email||'—')}</div></div>
                </div>
            </div>
            <div class="det-div"></div>
            <!-- localização -->
            <div class="det-section">
                <div class="det-section-title"><span class="material-symbols-outlined">location_on</span>Localização</div>
                <div class="det-item full"><div class="dl">Endereço completo</div><div class="dv">${esc(loc)}</div></div>
            </div>
            <div class="det-div"></div>
            <!-- produtos -->
            <div class="det-section">
                <div class="det-section-title"><span class="material-symbols-outlined">inventory_2</span>Produtos associados</div>
                ${prods.length
                    ? `<div class="prod-tag-list">${prods.map(p=>`<span class="prod-tag">${esc(p.nome||'#'+p.produto_id)}</span>`).join('')}</div>`
                    : '<p style="font-size:.8rem;color:var(--faint)">Nenhum produto associado</p>'
                }
            </div>
            <div class="det-div"></div>
            <!-- visita + obs -->
            <div class="det-grid" style="margin-bottom:${c.observacao?'1rem':'0'}">
                <div class="det-item"><div class="dl">Última visita</div><div class="dv">${fmt(c.ultima_visita)}</div></div>
                <div class="det-item"><div class="dl">Cadastrado</div><div class="dv">${fmt(c.created_at?.slice(0,10))}</div></div>
            </div>
            ${c.observacao?`<div class="det-item full" style="margin-bottom:1rem"><div class="dl">Observações</div><div class="dv" style="font-size:.82rem;line-height:1.55;color:var(--muted)">${esc(c.observacao)}</div></div>`:''}
            <!-- ações -->
            <div class="det-actions">
                <button class="det-act det-act-edit" onclick="$('modalDet').style.display='none';openM(clientes.find(x=>x.id==${c.id}))">
                    <span class="material-symbols-outlined">edit</span>Editar
                </button>
                <button class="det-act det-act-del" onclick="$('modalDet').style.display='none';delCliente(${c.id},'${esc(c.nome_empresa.replace(/'/g,"\\'"))}')">
                    <span class="material-symbols-outlined">delete</span>Excluir
                </button>
            </div>
        </div>
    `;
    // fechar via botão interno
    $('detClose2').addEventListener('click',()=>$('modalDet').style.display='none');
    $('modalDet').style.display='flex';
}

/* ── render KPIs ── */
function renderKPIs(){
    const total=clientes.length;
    const ativos=clientes.filter(c=>+c.ativo).length;
    const hoje=new Date().toISOString().slice(0,10);
    const comVisitaHoje=clientes.filter(c=>c.ultima_visita===hoje).length;
    $('kpis').innerHTML=`
    <div class="kpi anim d3">
        <div class="kpi-top">
            <div class="kpi-ico"><span class="material-symbols-outlined">handshake</span></div>
            <span class="kpi-tag">${total} total</span>
        </div>
        <div class="kpi-val">${total}</div>
        <div class="kpi-lbl">Clientes cadastrados</div>
    </div>
    <div class="kpi g anim d3">
        <div class="kpi-top">
            <div class="kpi-ico"><span class="material-symbols-outlined">check_circle</span></div>
            <span class="kpi-tag">ativos</span>
        </div>
        <div class="kpi-val">${ativos}</div>
        <div class="kpi-lbl">Clientes ativos</div>
    </div>
    <div class="kpi o anim d3">
        <div class="kpi-top">
            <div class="kpi-ico"><span class="material-symbols-outlined">today</span></div>
            <span class="kpi-tag">hoje</span>
        </div>
        <div class="kpi-val">${comVisitaHoje}</div>
        <div class="kpi-lbl">Visitas hoje</div>
    </div>`;
}

/* ── render CARDS (reformulado) ── */
function renderCards(){
    const q=$('busca').value.toLowerCase().trim();
    let f=clientes.filter(c=>
        (c.nome_empresa||'').toLowerCase().includes(q)||
        (c.cnpj||'').toLowerCase().includes(q)||
        (c.contato_nome||'').toLowerCase().includes(q)||
        (c.cidade||'').toLowerCase().includes(q)
    );
    if(currentFilter==='ativos') f=f.filter(c=>+c.ativo);
    if(currentFilter==='inativos') f=f.filter(c=>!+c.ativo);

    $('totalBadge').textContent=f.length;
    const grid=$('cardsGrid');
    if(!f.length){
        grid.innerHTML=`<div class="empty">
            <span class="material-symbols-outlined">people_outline</span>
            <div class="empty-t">${q||currentFilter!=='todos'?'Nenhum resultado encontrado':'Nenhum cliente cadastrado'}</div>
            <p>${q?'Tente outro termo de busca.':currentFilter!=='todos'?'Mude o filtro para ver mais clientes.':'Clique em "Novo Cliente" para começar.'}</p>
        </div>`;
        return;
    }

    grid.innerHTML=f.map(c=>{
        const prods=c.produtos||[];
        const ini=initials(c.nome_empresa||'?');
        const col=avColor(c.nome_empresa||'');
        const hoje=new Date().toISOString().slice(0,10);
        const semVisita=!c.ultima_visita;
        const visLabel=semVisita?'Sem visita registrada':'Últ. visita: '+fmt(c.ultima_visita);
        const visIcon=semVisita?'event_busy':'event_available';
        const visClass=semVisita?'warn':'ok';
        const loc=[c.cidade,c.estado].filter(Boolean).join(' / ')||null;

        return`<div class="client-card${+c.ativo?'':' inactive'}" onclick="verDetalhe(clientes.find(x=>x.id==${c.id}))">
            <!-- banner -->
            <div class="cc-banner ${col}">
                <span class="cc-badge ${+c.ativo?'on':'off'}">
                    <span class="dot"></span>${+c.ativo?'Ativo':'Inativo'}
                </span>
            </div>
            <!-- avatar flutuante -->
            <div class="cc-av-wrap">
                <div class="cc-av ${col}">${esc(ini)}</div>
            </div>
            <!-- body -->
            <div class="cc-body">
                <div class="cc-title-row">
                    <div>
                        <div class="cc-name">${esc(c.nome_empresa)}</div>
                        <div class="cc-cnpj">${esc(c.cnpj||'Sem CNPJ')}</div>
                    </div>
                    <div class="cc-arrow"><span class="material-symbols-outlined">arrow_forward</span></div>
                </div>

                <div class="cc-meta">
                    ${c.contato_nome?`<div class="cc-row"><span class="material-symbols-outlined">person</span><span>${esc(c.contato_nome)}</span></div>`:''}
                    ${c.telefone?`<div class="cc-row"><span class="material-symbols-outlined">call</span><span>${esc(c.telefone)}</span></div>`:''}
                    ${c.email?`<div class="cc-row"><span class="material-symbols-outlined">mail</span><span>${esc(c.email)}</span></div>`:''}
                    ${loc?`<div class="cc-row"><span class="material-symbols-outlined">location_on</span><span>${esc(loc)}</span></div>`:''}
                </div>

                <div class="cc-visit-row ${visClass}">
                    <span class="material-symbols-outlined">${visIcon}</span>
                    ${visLabel}
                </div>

                ${prods.length?`<div class="cc-prods">${prods.slice(0,3).map(p=>`<span class="cc-prod-tag">${esc(p.nome)}</span>`).join('')}${prods.length>3?`<span class="cc-prod-more">+${prods.length-3}</span>`:''}</div>`:''}
            </div>

            <!-- footer actions -->
            <div class="cc-footer" onclick="event.stopPropagation()">
                <button class="cc-act cc-act-e" onclick="openM(clientes.find(x=>x.id==${c.id}))">
                    <span class="material-symbols-outlined">edit</span>Editar
                </button>
                <button class="cc-act cc-act-d" onclick="delCliente(${c.id},'${esc(c.nome_empresa.replace(/'/g,"\\'"))}')">
                    <span class="material-symbols-outlined">delete</span>
                </button>
            </div>
        </div>`;
    }).join('');
}

$('busca').addEventListener('input',renderCards);

/* ── carregar tudo ── */
async function loadAll(){
    try{
        const[rC,rP]=await Promise.all([
            fetch('api.php?acao=listar_clientes',{credentials:'same-origin'}),
            fetch('api.php?acao=listar',{credentials:'same-origin'}),
        ]);
        clientes=(rC.ok?await rC.json():[]);
        produtos=(rP.ok?await rP.json():[]);
        if(!Array.isArray(clientes))clientes=[];
        if(!Array.isArray(produtos))produtos=[];
        renderKPIs();
        renderCards();
    }catch(e){console.error(e)}
}

window.onload=async()=>{
    await loadAll();

    const params=new URLSearchParams(window.location.search);
    if(params.get('novo')!=='1')return;

    const param=(key,max)=>String(params.get(key)||'').trim().slice(0,max);
    const nome=param('nome',150);
    const telefone=param('telefone',30);
    const endereco=param('endereco',500);

    openM({
        nome_empresa:nome,
        contato_nome:nome,
        telefone,
        observacao:endereco?`Endereço informado no pedido: ${endereco}`:'',
        ativo:1,
        produtos:[],
    });

    window.history.replaceState({},document.title,window.location.pathname);
    $('cNome').focus();
};
</script>
</body>
</html>
