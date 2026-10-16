<?php
session_start();
require 'db.php';
require 'helpers.php';
require_once __DIR__ . '/includes/tracking_runtime.php';
require_once __DIR__ . '/includes/business_profile_runtime.php';
require_once __DIR__ . '/includes/commercial_availability.php';
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
get_csrf_token();
$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$pdo = get_pdo();
$commercialAvailabilityReady = uplar_commercial_availability_schema_ready($pdo);
$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user     = $stmt->fetch();
$is_admin = ($user && $user['role'] === 'admin');
$stmt = $pdo->prepare("SELECT whatsapp_number FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$whatsapp_number = ($stmt->fetch())['whatsapp_number'] ?? '';
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
require 'subscription.php';
$sub = check_and_block($user_id, $pdo);
$businessProfile = uplar_business_profile_read($pdo, (int)$user_id);
$businessPreset = uplar_business_profile_get($businessProfile['segmento']);
$businessFood = ($businessPreset['familia'] ?? '') === 'alimentacao';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Produtos — Uplar</title>
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
    --violet:  #7c3aed;
    --sh:      0 1px 4px rgba(15,28,46,.06), 0 4px 16px rgba(15,28,46,.04);
    --sh-lift: 0 4px 24px rgba(59,111,245,.14);
    --r:       12px;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--ink);line-height:1.55;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none}
button{font-family:inherit;cursor:pointer}

@keyframes fadeUp  {from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeIn  {from{opacity:0}to{opacity:1}}
@keyframes popIn   {from{opacity:0;transform:scale(.94)}to{opacity:1;transform:scale(1)}}
@keyframes shimmer {0%{background-position:200% 0}100%{background-position:-200% 0}}
@keyframes pulse   {0%,100%{opacity:1}50%{opacity:.5}}
@keyframes slideRight{from{transform:translateX(12px);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes panelOutLeft{to{transform:translateX(-12px);opacity:0}}
@keyframes panelOutRight{to{transform:translateX(12px);opacity:0}}
@keyframes panelInLeft{from{transform:translateX(-12px);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes panelInRight{from{transform:translateX(12px);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes fieldShake{0%,100%{transform:translateX(0)}25%{transform:translateX(-4px)}50%{transform:translateX(4px)}75%{transform:translateX(-2px)}}
@keyframes chipPop{0%{transform:scale(.92)}55%{transform:scale(1.05)}100%{transform:scale(1)}}
@keyframes iconBounce{0%{transform:scale(.5);opacity:0}65%{transform:scale(1.15);opacity:1}100%{transform:scale(1)}}
@keyframes reviewReveal{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}
@keyframes marginPulse{50%{color:var(--ok)}}
@keyframes marginLossPulse{50%{color:var(--err)}}
@keyframes savedCheck{0%{transform:scale(.55);opacity:0}65%{transform:scale(1.12);opacity:1}100%{transform:scale(1)}}
@keyframes dotCheck{from{clip-path:inset(0 100% 0 0);opacity:0;transform:scale(.6)}to{clip-path:inset(0);opacity:1;transform:scale(1)}}
.anim{opacity:0;animation:fadeUp .5s cubic-bezier(.22,1,.36,1) forwards}
.d1{animation-delay:.04s}.d2{animation-delay:.10s}
.d3{animation-delay:.16s}.d4{animation-delay:.22s}
.d5{animation-delay:.28s}.d6{animation-delay:.34s}

.app{display:flex;min-height:100vh}

/* sidebar */
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

.top{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.4rem;gap:1rem;flex-wrap:wrap}
.top-l{display:flex;align-items:center;gap:.8rem;min-width:0}
.greeting{font-size:1.45rem;font-weight:700;letter-spacing:-.4px}
.sub{color:var(--muted);font-size:.82rem;margin-top:1px}
.searchbox{display:flex;align-items:center;gap:.55rem;background:var(--card);border:1px solid var(--border);border-radius:10px;padding:.52rem .95rem;transition:border-color .2s,box-shadow .2s;min-width:210px}
.searchbox:focus-within{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.searchbox .material-symbols-outlined{font-size:1.05rem;color:var(--faint)}
.searchbox input{border:none;outline:none;font-size:.855rem;width:100%;background:transparent;color:var(--ink)}
.searchbox input::placeholder{color:var(--faint)}

.pills{display:flex;gap:.5rem;margin-bottom:1.4rem;flex-wrap:wrap;position:relative;z-index:60}
.pill{display:inline-flex;align-items:center;gap:.4rem;padding:.52rem 1rem;border-radius:50px;font-size:.8rem;font-weight:600;border:none;transition:all .22s;white-space:nowrap}
.pill .material-symbols-outlined{font-size:1rem}
.pill-solid{background:var(--p);color:#fff;box-shadow:0 3px 12px var(--p-glow)}
.pill-solid:hover{background:var(--p-dark);transform:translateY(-2px);box-shadow:0 6px 20px var(--p-glow)}
.pill-outline{background:var(--card);color:var(--muted);border:1px solid var(--border)}
.pill-outline:hover{background:var(--p-dim);border-color:var(--p);color:var(--p);transform:translateY(-2px)}
.action-menu{position:relative;z-index:1}.action-menu[open]{z-index:100}.action-menu summary{list-style:none;cursor:pointer}.action-menu summary::-webkit-details-marker{display:none}.action-menu>div{position:absolute;z-index:101;right:0;top:calc(100% + .35rem);min-width:165px;padding:.35rem;background:var(--card);border:1px solid var(--border);border-radius:10px;box-shadow:0 10px 28px rgba(15,28,46,.18)}.action-menu button{display:block;width:100%;padding:.52rem .65rem;border:0;border-radius:7px;background:none;text-align:left;color:var(--muted);font:600 .75rem Inter;cursor:pointer}.action-menu button:hover{background:var(--p-dim);color:var(--p)}.filters .action-menu>div{left:0;right:auto}.pc-more{width:32px;flex:0 0 32px}.pc-cost{font-size:.68rem;color:var(--faint);margin-bottom:.25rem}
.product-action-popover{position:fixed;z-index:1200;min-width:178px;padding:.35rem;background:var(--card);border:1px solid var(--border);border-radius:11px;box-shadow:0 14px 34px rgba(15,28,46,.19)}.product-action-popover[hidden]{display:none}.product-action-popover button{display:flex;align-items:center;width:100%;padding:.6rem .7rem;border:0;border-radius:7px;background:none;text-align:left;color:var(--muted);font:600 .76rem Inter;cursor:pointer}.product-action-popover button:hover,.product-action-popover button:focus-visible{background:var(--p-dim);color:var(--p);outline:none}.product-action-popover .danger{color:var(--err)}.product-action-popover .danger:hover,.product-action-popover .danger:focus-visible{background:var(--err-dim);color:var(--err)}.product-action-popover hr{border:0;border-top:1px solid var(--border);margin:.35rem 0}.pc-more{justify-content:center;padding:0}

.kpis{display:grid;grid-template-columns:repeat(5,1fr);gap:.85rem;margin-bottom:1.3rem;align-items:stretch;position:relative;z-index:1}
.kpi{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:1rem 1.1rem;transition:transform .22s,box-shadow .22s;position:relative;overflow:hidden;display:flex;flex-direction:column;min-height:142px}
.kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--p);border-radius:var(--r) var(--r) 0 0}
.kpi.g::before{background:var(--ok)}
.kpi.o::before{background:var(--warn)}
.kpi.r::before{background:var(--err)}
.kpi.v::before{background:var(--violet)}
.kpi:hover{transform:translateY(-3px);box-shadow:var(--sh-lift)}
.kpi-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:.55rem}
.kpi-ico{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:var(--p-dim);color:var(--p)}
.kpi.g .kpi-ico{background:var(--ok-dim);color:var(--ok)}
.kpi.o .kpi-ico{background:var(--warn-dim);color:var(--warn)}
.kpi.r .kpi-ico{background:var(--err-dim);color:var(--err)}
.kpi.v .kpi-ico{background:rgba(124,58,237,.09);color:var(--violet)}
.kpi-ico .material-symbols-outlined{font-size:1.05rem}
.kpi-tag{font-size:.67rem;font-weight:700;padding:.15rem .5rem;border-radius:20px;background:var(--p-dim);color:var(--p)}
.kpi.g .kpi-tag{background:var(--ok-dim);color:var(--ok)}
.kpi.o .kpi-tag{background:var(--warn-dim);color:var(--warn)}
.kpi.r .kpi-tag{background:var(--err-dim);color:var(--err)}
.kpi.v .kpi-tag{background:rgba(124,58,237,.09);color:var(--violet)}
.kpi-val{font-size:clamp(1.2rem,1.55vw,1.6rem);font-weight:700;letter-spacing:-.4px;line-height:1.08;margin-bottom:.22rem}
.kpi-lbl{font-size:.73rem;color:var(--muted);font-weight:600}
.kpi-hint{font-size:.67rem;color:var(--faint);line-height:1.35;margin-top:auto;padding-top:.7rem}

.card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:1.1rem 1.2rem}
.ch{display:flex;justify-content:space-between;align-items:center;margin-bottom:.9rem;padding-bottom:.7rem;border-bottom:1px solid var(--border)}
.ct{font-size:.88rem;font-weight:700}

.filters{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.9rem}
.filter-btn{padding:.38rem .8rem;border-radius:20px;border:1px solid var(--border);background:var(--card);color:var(--muted);font-size:.75rem;font-weight:600;cursor:pointer;transition:all .18s}
.filter-btn:hover,.filter-btn.on{background:var(--p-dim);border-color:var(--p);color:var(--p)}
.filter-btn.on{font-weight:700}
.filter-btn.exp-filter.on{background:rgba(124,58,237,.09);border-color:var(--violet);color:var(--violet)}
.sort-sel{padding:.38rem .8rem;border-radius:20px;border:1px solid var(--border);background:var(--card);color:var(--muted);font-size:.75rem;font-weight:600;cursor:pointer;outline:none;transition:all .18s}
.sort-sel:focus{border-color:var(--p);color:var(--p)}

.view-tog{display:flex;gap:3px;background:var(--bg);border-radius:8px;padding:3px}
.vt-btn{padding:.3rem .5rem;border-radius:6px;border:none;background:transparent;color:var(--faint);cursor:pointer;transition:all .18s;display:flex;align-items:center}
.vt-btn .material-symbols-outlined{font-size:1rem}
.vt-btn.on{background:var(--card);color:var(--p);box-shadow:var(--sh)}

/* PRODUCT GRID */
.pg{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.85rem;align-items:stretch}
.pc{background:var(--card);border:1px solid var(--border);border-radius:var(--r);overflow:visible;transition:transform .22s,box-shadow .22s;display:flex;flex-direction:column;height:100%;animation:fadeUp .4s cubic-bezier(.22,1,.36,1) both}.pc-img{border-radius:var(--r) var(--r) 0 0;overflow:hidden}.pc-acts{position:relative;z-index:2}
.pc:hover{transform:translateY(-4px);box-shadow:var(--sh-lift)}
.pc-img{height:130px;background:var(--bg);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;position:relative}
.pc-img img{width:100%;height:100%;object-fit:cover;transition:transform .4s}
.pc:hover .pc-img img{transform:scale(1.06)}
.pc-img .material-symbols-outlined{font-size:2.2rem;color:var(--border)}
.pc-badge{position:absolute;top:8px;right:8px;font-size:.62rem;font-weight:700;padding:.15rem .45rem;border-radius:6px;backdrop-filter:blur(8px)}
.pc-badge.ok{background:rgba(16,185,129,.85);color:#fff}
.pc-badge.warn{background:rgba(245,158,11,.85);color:#fff}
.pc-badge.err{background:rgba(239,68,68,.85);color:#fff}
/* validade badge no canto superior esquerdo */
.pc-val-badge{position:absolute;top:8px;left:8px;font-size:.6rem;font-weight:700;padding:.15rem .42rem;border-radius:6px;backdrop-filter:blur(8px);display:flex;align-items:center;gap:2px}
.pc-val-badge .material-symbols-outlined{font-size:.7rem}
.pc-val-badge.venc{background:rgba(239,68,68,.9);color:#fff}
.pc-val-badge.crit{background:rgba(239,68,68,.9);color:#fff}
.pc-val-badge.alrt{background:rgba(245,158,11,.88);color:#fff}
.pc-val-badge.aten{background:rgba(124,58,237,.85);color:#fff}
.pc-val-badge.ok{background:rgba(16,185,129,.75);color:#fff}
.pc-body{padding:.75rem .85rem;flex:1;display:flex;flex-direction:column}
.pc-name{font-size:.82rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:1px}
.pc-code{font-size:.68rem;color:var(--faint);margin-bottom:.4rem}
.pc-price{font-size:1rem;font-weight:700;color:var(--p);margin-bottom:.15rem}
.pc-stock{font-size:.68rem;color:var(--muted);margin-bottom:.25rem}
.pc-stock.low{color:var(--warn);font-weight:600}
.pc-stock.zero{color:var(--err);font-weight:600}
.pc-validade{font-size:.68rem;color:var(--muted);margin-bottom:.5rem;display:flex;align-items:center;gap:.25rem}
.pc-validade .material-symbols-outlined{font-size:.78rem}
.pc-validade.venc,.pc-validade.crit{color:var(--err);font-weight:600}
.pc-validade.alrt{color:var(--warn);font-weight:600}
.pc-validade.aten{color:var(--violet);font-weight:600}
.pc-validade-empty{visibility:hidden}
.pc-acts{display:flex;gap:.4rem;margin-top:auto}
.pc-btn{flex:1;padding:.4rem;border:none;border-radius:7px;font-size:.72rem;font-weight:600;display:flex;align-items:center;justify-content:center;gap:.28rem;transition:all .18s}
.pc-btn .material-symbols-outlined{font-size:.9rem}
.pc-e{background:var(--p-dim);color:var(--p)}.pc-e:hover{background:rgba(59,111,245,.16)}
.pc-d{background:var(--err-dim);color:var(--err)}.pc-d:hover{background:rgba(239,68,68,.16)}

/* TABLE */
.tbl-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
.tbl{width:100%;border-collapse:collapse;min-width:750px}
.tbl thead tr{background:var(--bg)}
.tbl th{padding:.7rem 1rem;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--faint);white-space:nowrap;border-bottom:1px solid var(--border)}
.tbl td{padding:.8rem 1rem;border-bottom:1px solid var(--border);vertical-align:middle;font-size:.83rem}
.tbl tbody tr{transition:background .15s}
.tbl tbody tr:hover{background:var(--p-dim)}
.tbl tbody tr:last-child td{border-bottom:none}
.tbl-av{width:40px;height:40px;border-radius:9px;background:var(--bg);overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.tbl-av img{width:100%;height:100%;object-fit:cover}
.tbl-av .material-symbols-outlined{font-size:1.1rem;color:var(--border)}
.tbl-cell{display:flex;align-items:center;gap:.7rem}
.tbl-name{font-weight:600;font-size:.83rem}
.tbl-sku{font-size:.7rem;color:var(--faint)}
.stock-bar-wrap{display:flex;align-items:center;gap:.5rem}
.stock-bar{width:70px;height:5px;background:var(--border);border-radius:3px;overflow:hidden;flex-shrink:0}
.stock-fill{height:100%;border-radius:3px;transition:width .4s}
.stock-fill.ok{background:var(--ok)}
.stock-fill.warn{background:var(--warn)}
.stock-fill.err{background:var(--err)}
.badge{font-size:.67rem;font-weight:700;padding:.15rem .5rem;border-radius:20px;white-space:nowrap;display:inline-flex;align-items:center;gap:.25rem}
.badge .material-symbols-outlined{font-size:.75rem}
.badge.ok{background:var(--ok-dim);color:var(--ok)}
.badge.warn{background:var(--warn-dim);color:#b45309}
.badge.err{background:var(--err-dim);color:var(--err)}
.badge.violet{background:rgba(124,58,237,.09);color:var(--violet)}
.tbl-acts{display:flex;gap:.35rem}
.tbl-btn{width:30px;height:30px;border:1px solid var(--border);border-radius:7px;background:transparent;color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .18s}
.tbl-btn .material-symbols-outlined{font-size:.95rem}
.tbl-btn:hover{background:var(--p-dim);color:var(--p);border-color:var(--p)}
.tbl-btn.del:hover{background:var(--err-dim);color:var(--err);border-color:var(--err)}
.lote-toolbar{display:flex;align-items:center;gap:.7rem;flex-wrap:wrap;padding:.8rem;background:var(--bg);border:1px solid var(--border);border-radius:10px;margin-bottom:.8rem}
.lote-check{font-size:.78rem;font-weight:600;display:flex;align-items:center;gap:.4rem;white-space:nowrap}
.lote-check input,.lote-row-check{accent-color:var(--p)}
.lote-bulk{display:flex;align-items:center;gap:.45rem;flex:1;justify-content:flex-end;flex-wrap:wrap}
.lote-bulk input{width:150px;padding:.45rem .6rem;border:1px solid var(--border);border-radius:8px;font:inherit;font-size:.75rem;outline:none}
.lote-bulk input:focus,.lote-field:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.lote-rows{display:flex;flex-direction:column;gap:.55rem}
.lote-row{display:grid;grid-template-columns:24px 58px minmax(180px,1.5fr) 120px 110px minmax(120px,1fr) 120px 140px;gap:.55rem;align-items:center;padding:.6rem;border:1px solid var(--border);border-radius:10px;background:var(--card)}
.lote-row img{width:58px;height:58px;border-radius:8px;object-fit:cover;background:var(--bg)}
.lote-field{width:100%;min-width:0;padding:.5rem .6rem;border:1px solid var(--border);border-radius:8px;font:inherit;font-size:.78rem;outline:none;background:var(--card)}
.lote-footer{display:flex;justify-content:space-between;align-items:center;gap:1rem;margin-top:1rem;flex-wrap:wrap}
@media(max-width:1100px){.lote-row{grid-template-columns:24px 58px minmax(160px,1.4fr) 110px 100px minmax(110px,1fr) 110px}.lote-row .lote-code{grid-column:3/-1}}
@media(max-width:760px){.lote-row{grid-template-columns:24px 58px 1fr 1fr}.lote-row .lote-name,.lote-row .lote-code,.lote-row .lote-category,.lote-row .lote-validade{grid-column:3/-1}.lote-bulk{justify-content:flex-start}.lote-bulk input{flex:1;min-width:130px}}

/* EMPTY & SKELETON */
.empty{text-align:center;padding:3rem 1rem;color:var(--faint)}
.empty .material-symbols-outlined{font-size:2.5rem;display:block;margin-bottom:.5rem;opacity:.25}
.empty p{font-size:.82rem}
.sk{border-radius:7px;background:linear-gradient(90deg,var(--bg) 25%,#e8ecf2 50%,var(--bg) 75%);background-size:200% 100%;animation:shimmer 1.3s infinite}

/* EDITOR CONTÍNUO DE PRODUTO */
.modal{display:none;position:fixed;inset:0;background:rgba(15,28,46,.55);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(5px)}
.mbox{background:var(--card);border-radius:18px;width:90%;max-width:560px;box-shadow:0 24px 64px rgba(0,0,0,.2);animation:popIn .28s cubic-bezier(.22,1,.36,1) both;overflow:hidden}
.product-editor-modal .mbox{width:min(1040px,calc(100vw - 2rem));max-width:1040px;max-height:calc(100vh - 2rem);display:flex;flex-direction:column}.product-editor-modal .mstep-body{min-height:0;max-height:none;flex:1;overflow-y:auto}.product-editor-modal .mstep-head,.product-editor-modal .mstep-foot{flex:0 0 auto}
.editor-context{max-width:760px;margin-top:.42rem;font-size:.76rem;line-height:1.45;opacity:.9}
.editor-section{display:block!important;margin:0 0 1rem;padding:1rem;border:1px solid var(--border);border-radius:13px;background:var(--card);animation:none!important}
.editor-section[hidden]{display:none!important}.editor-section:last-child{margin-bottom:0}
.editor-section-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:.9rem}
.editor-section-heading h3{font-size:.92rem;letter-spacing:-.01em}.editor-section-heading p{margin-top:.12rem;color:var(--muted);font-size:.74rem;line-height:1.45}
.editor-section-badge{flex:0 0 auto;padding:.25rem .55rem;border-radius:999px;background:var(--p-dim);color:var(--p);font-size:.65rem;font-weight:700}
.editor-preview{background:linear-gradient(135deg,var(--p-dim),rgba(124,58,237,.06))}.editor-preview #reviewW{min-height:78px}.editor-preview .review-card{background:rgba(255,255,255,.72)}
.product-optional-control{margin-top:1rem;border:1px solid var(--border);border-radius:11px;background:var(--card)}
.product-optional-control>summary{display:flex;align-items:center;justify-content:space-between;gap:.7rem;min-height:48px;padding:.75rem .9rem;color:var(--ink);font-size:.78rem;font-weight:700;cursor:pointer;list-style:none}
.product-optional-control>summary::-webkit-details-marker{display:none}.product-optional-control>summary::after{content:'+';font-size:1.05rem;color:var(--p)}.product-optional-control[open]>summary::after{content:'−'}
.product-optional-content{padding:0 .85rem .85rem}.editor-actions{display:flex;gap:.5rem;align-items:center}.btn-save-another{display:flex;align-items:center;justify-content:center;padding:.65rem 1rem;border:1px solid var(--border);border-radius:9px;background:var(--card);color:var(--p);font-size:.83rem;font-weight:700;cursor:pointer}.btn-save-another:hover{background:var(--p-dim);border-color:var(--p)}.btn-save-another:disabled{opacity:.55;cursor:not-allowed}
.field-error{display:block;margin-top:.25rem;color:var(--err);font-size:.72rem;font-weight:600;line-height:1.35}
.fl input[aria-invalid="true"],.fl textarea[aria-invalid="true"],.fl select[aria-invalid="true"]{border-color:var(--err);box-shadow:0 0 0 3px var(--err-dim)}
.size-stock-dialog{width:min(500px,calc(100vw - 2rem));max-height:min(680px,calc(100vh - 2rem));display:flex;flex-direction:column;background:var(--card);border-radius:18px;box-shadow:0 24px 64px rgba(0,0,0,.22);overflow:hidden;animation:popIn .24s cubic-bezier(.22,1,.36,1) both}.size-stock-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1.2rem 1.25rem 1rem;border-bottom:1px solid var(--border)}.size-stock-head h2{margin:0;font-size:1.02rem;font-weight:750}.size-stock-head p{margin:.28rem 0 0;font-size:.86rem;font-weight:650;color:var(--ink)}.size-stock-head span#sizeStockCurrent{display:block;margin-top:.2rem;color:var(--muted);font-size:.72rem}.size-stock-close{width:34px;height:34px;flex:0 0 34px;display:grid;place-items:center;border:1px solid var(--border);border-radius:9px;background:var(--card);color:var(--muted);cursor:pointer;transition:all .18s}.size-stock-close:hover,.size-stock-close:focus-visible{background:var(--p-dim);border-color:var(--p);color:var(--p);outline:none}.size-stock-close .material-symbols-outlined{font-size:1.2rem}.size-stock-body{min-height:0;overflow:auto;padding:1rem 1.25rem 1.15rem}.size-stock-section-title{font-size:.76rem;font-weight:750;color:var(--ink)}.size-stock-picker{padding:.9rem;margin-bottom:1rem;border:1px solid var(--border);border-radius:12px;background:var(--bg)}.size-stock-picker p{margin:.28rem 0 .75rem;font-size:.72rem;line-height:1.45;color:var(--muted)}.size-stock-chips{display:flex;flex-wrap:wrap;gap:.42rem}.size-stock-chip{min-width:42px;padding:.42rem .55rem;border:1px solid var(--border);border-radius:8px;background:var(--card);font:700 .73rem Inter;color:var(--muted);cursor:pointer;transition:background-color .18s,border-color .18s,color .18s}.size-stock-chip:hover,.size-stock-chip:focus-visible{border-color:var(--p);color:var(--p);outline:none}.size-stock-chip.on{border-color:var(--p);background:var(--p-dim);color:var(--p)}.size-stock-custom{display:flex;gap:.45rem;margin-top:.7rem}.size-stock-custom input{min-width:0;flex:1;padding:.52rem .65rem;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--ink);font:600 .75rem Inter;outline:none}.size-stock-custom input:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}.size-stock-custom button{padding:.5rem .7rem;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--p);font:700 .73rem Inter;cursor:pointer}.size-stock-list{margin-top:.2rem}.size-stock-list .size-stock-section-title{margin-bottom:.5rem}.size-stock-row{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:.6rem;padding:.62rem .7rem;margin-bottom:.42rem;border:1px solid var(--border);border-radius:10px;background:var(--card)}.size-stock-label{font-size:.85rem;font-weight:750;color:var(--ink)}.size-stock-stepper{display:flex;align-items:center;border:1px solid var(--border);border-radius:8px;overflow:hidden}.size-stock-stepper button{width:30px;height:30px;border:0;background:var(--bg);color:var(--p);font:700 1rem Inter;cursor:pointer}.size-stock-stepper button:hover{background:var(--p-dim)}.size-stock-stepper input{width:42px;height:30px;border:0;border-left:1px solid var(--border);border-right:1px solid var(--border);outline:0;text-align:center;background:var(--card);color:var(--ink);font:700 .78rem Inter;-moz-appearance:textfield}.size-stock-stepper input::-webkit-outer-spin-button,.size-stock-stepper input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}.size-stock-remove{width:30px;height:30px;border:0;border-radius:7px;background:transparent;color:var(--faint);font-size:1.15rem;cursor:pointer}.size-stock-remove:hover,.size-stock-remove:focus-visible{background:var(--err-dim);color:var(--err);outline:none}.size-stock-empty{padding:1rem .2rem;color:var(--muted);font-size:.76rem}.size-stock-summary{margin-top:1rem;padding:.85rem .9rem;border:1px solid var(--border);border-radius:11px;background:var(--bg)}.size-stock-summary-line{display:flex;justify-content:space-between;gap:.75rem;font-size:.76rem;color:var(--muted)}.size-stock-summary-line strong{color:var(--ink);font-size:.8rem}.size-stock-progress{height:5px;margin:.6rem 0 .45rem;border-radius:999px;overflow:hidden;background:rgba(148,163,184,.24)}.size-stock-progress span{display:block;width:0;height:100%;border-radius:inherit;background:var(--p);transition:width .18s,background .18s}.size-stock-hint{font-size:.71rem;line-height:1.4;color:var(--muted)}.size-stock-hint.attention{color:var(--warn)}.size-stock-hint.error{color:var(--err)}.size-stock-hint.success{color:var(--ok)}.size-stock-change{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-top:.75rem;padding-top:.7rem;border-top:1px solid var(--border);font-size:.66rem;color:var(--muted)}.size-stock-change span:nth-child(2){text-align:center}.size-stock-change span:last-child{text-align:right}.size-stock-change strong{display:block;margin-top:.12rem;color:var(--ink);font-size:.75rem}.size-stock-footer{display:flex;justify-content:flex-end;gap:.55rem;padding:.85rem 1.25rem calc(.85rem + env(safe-area-inset-bottom));border-top:1px solid var(--border);background:var(--card)}.size-stock-footer .pill{justify-content:center}.size-stock-footer .pill:disabled{opacity:.52;box-shadow:none;cursor:not-allowed;transform:none}
.mstep-head{padding:1.5rem 1.5rem 1rem;background:linear-gradient(135deg,var(--p) 0%,#5c8aff 100%);color:#fff;position:relative}
.mstep-head h2{font-size:1.1rem;font-weight:700;margin-bottom:.3rem;display:flex;align-items:center;gap:.45rem}.save-background{width:7px;height:7px;border-radius:50%;background:#fff;opacity:0;transform:scale(.5);transition:opacity .18s,transform .18s cubic-bezier(.22,1,.36,1)}.save-background.on{opacity:.9;transform:scale(1);animation:pulse .85s cubic-bezier(.22,1,.36,1) infinite}
.mstep-head p{font-size:.78rem;opacity:.8}
.mstep-close{position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.2);border:none;color:#fff;width:28px;height:28px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .18s}
.mstep-close:hover{background:rgba(255,255,255,.3)}
.mstep-close .material-symbols-outlined{font-size:1rem}
.step-dots{display:flex;gap:.45rem;margin-top:.9rem;align-items:center}
.sdot{height:4px;width:9px;min-width:9px;padding:0;border:0;border-radius:2px;background:rgba(255,255,255,.3);transition:width .35s cubic-bezier(.22,1,.36,1),background-color .2s;cursor:default;position:relative}.sdot.done{cursor:pointer}.sdot::after{content:"✓";position:absolute;inset:-7px 0 auto;text-align:center;font-size:9px;line-height:16px;color:#fff;opacity:0;transform:scale(.55);pointer-events:none}.sdot.done::after{opacity:1;transform:scale(1);animation:dotCheck .2s cubic-bezier(.22,1,.36,1) both}.sdot:focus-visible{outline:2px solid #fff;outline-offset:4px}
.sdot.on{background:#fff;width:24px !important}
.sdot.done{background:rgba(255,255,255,.7)}
.mstep-body{padding:1.3rem 1.5rem;max-height:calc(93vh - 200px);overflow-y:auto}
.step-panel{display:none}.step-panel.on{display:block}.step-panel.step-enter-next{animation:panelInRight .28s cubic-bezier(.22,1,.36,1) both}.step-panel.step-enter-prev{animation:panelInLeft .28s cubic-bezier(.22,1,.36,1) both}.step-panel.step-exit-next,.step-panel.step-exit-prev{display:block}.step-panel.step-exit-next{animation:panelOutLeft .2s cubic-bezier(.22,1,.36,1) both}.step-panel.step-exit-prev{animation:panelOutRight .2s cubic-bezier(.22,1,.36,1) both}

/* form */
.fg{display:grid;grid-template-columns:1fr 1fr;gap:.9rem}
.fw{grid-column:1/-1}
.fl{display:flex;flex-direction:column;gap:.3rem;position:relative}
.fl label{font-size:.72rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
.fl input,.fl textarea,.fl select{padding:.65rem .9rem;border:1px solid var(--border);border-radius:9px;font-size:.88rem;font-family:inherit;transition:border-color .18s,box-shadow .18s;outline:none;background:var(--card);color:var(--ink)}
.fl input:focus,.fl textarea:focus,.fl select:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.fl .hint{font-size:.69rem;color:var(--faint);margin-top:.15rem}
.fl input:disabled{background:var(--bg);color:var(--faint);cursor:not-allowed}.fl.has-error input,.fl.has-error textarea,.fl.has-error select{border-color:var(--err);animation:fieldShake .3s cubic-bezier(.22,1,.36,1)}.fl.has-valid input,.fl.has-valid textarea,.fl.has-valid select{border-color:var(--ok)}.field-confirm{position:absolute;right:.65rem;top:2.02rem;color:var(--ok);font-size:1rem;opacity:0;transform:scale(.7);transition:opacity .18s,transform .18s cubic-bezier(.22,1,.36,1);pointer-events:none}.fl.has-valid .field-confirm{opacity:1;transform:scale(1)}

/* preço preview */
.price-preview{background:linear-gradient(135deg,var(--p-dim),rgba(124,58,237,.06));border:1px solid rgba(59,111,245,.15);border-radius:12px;padding:1rem;margin-top:.4rem;display:flex;align-items:center;justify-content:space-between}
.pp-val{font-size:1.4rem;font-weight:700;color:var(--p);font-variant-numeric:tabular-nums;transition:color .2s}#ppMargin.is-profit{color:var(--ok)}#ppMargin.is-loss{color:var(--err)}#ppMargin.cross-profit{animation:marginPulse .22s cubic-bezier(.22,1,.36,1)}#ppMargin.cross-loss{animation:marginLossPulse .22s cubic-bezier(.22,1,.36,1)}
.pp-lbl{font-size:.72rem;color:var(--muted)}

/* qty stepper */
.qty-stepper{display:flex;align-items:center;gap:.5rem}
.qs-btn{width:34px;height:34px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--ink);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.1rem;transition:all .18s;flex-shrink:0}
.qs-btn:hover{background:var(--p-dim);border-color:var(--p);color:var(--p)}
.qs-inp{flex:1;text-align:center;padding:.55rem .4rem;border:1px solid var(--border);border-radius:9px;font-size:.95rem;font-weight:700;outline:none;transition:border-color .18s}
.qs-inp:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}

/* VALIDADE STEP */
.val-toggle-wrap{display:flex;align-items:center;gap:.75rem;padding:1rem 1.1rem;background:var(--bg);border:1px solid var(--border);border-radius:12px;cursor:pointer;transition:all .2s;margin-bottom:1rem}
.val-toggle-wrap:hover{border-color:var(--p);background:var(--p-dim)}
.val-toggle-wrap.active{border-color:var(--p);background:var(--p-dim)}
.val-check{width:20px;height:20px;border-radius:6px;border:2px solid var(--border);background:var(--card);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .2s}
.val-check.on{background:var(--p);border-color:var(--p)}
.val-check .material-symbols-outlined{font-size:.85rem;color:#fff;display:none}
.val-check.on .material-symbols-outlined{display:block}
.val-toggle-txt{flex:1}
.val-toggle-title{font-size:.88rem;font-weight:700;color:var(--ink)}
.val-toggle-sub{font-size:.73rem;color:var(--muted);margin-top:1px}
.val-toggle-ico{width:36px;height:36px;border-radius:9px;background:rgba(124,58,237,.09);color:var(--violet);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.val-toggle-ico .material-symbols-outlined{font-size:1.2rem}
.val-date-section{display:none;animation:fadeUp .3s cubic-bezier(.22,1,.36,1) both}
.val-date-section.on{display:block}
.val-alert-preview{display:flex;gap:.5rem;margin-top:.7rem;flex-wrap:wrap}
.val-chip{display:inline-flex;align-items:center;gap:.3rem;padding:.28rem .65rem;border-radius:20px;font-size:.69rem;font-weight:700}
.val-chip.venc{background:var(--err-dim);color:var(--err)}
.val-chip.crit{background:var(--err-dim);color:var(--err)}
.val-chip.alrt{background:var(--warn-dim);color:#b45309}
.val-chip.aten{background:rgba(124,58,237,.09);color:var(--violet)}
.val-chip.ok{background:var(--ok-dim);color:var(--ok)}
.val-chip .material-symbols-outlined{font-size:.78rem}
.val-dias-info{font-size:.78rem;color:var(--muted);margin-top:.5rem;min-height:1.1rem}

/* upload */
.upload{border:2px dashed var(--border);border-radius:12px;padding:1.8rem;text-align:center;cursor:pointer;transition:all .22s;background:var(--bg)}
.upload:hover,.upload.on{border-color:var(--p);background:var(--p-dim)}
.upload-ico{font-size:2.4rem;color:var(--p);margin-bottom:.4rem;display:block}
.upload-txt{font-size:.83rem;font-weight:600;color:var(--muted)}
.upload-hint{font-size:.7rem;color:var(--faint);margin-top:.2rem}
.prev{position:relative;display:inline-block;margin-top:.9rem;opacity:0;transform:scale(.98);transition:opacity .22s cubic-bezier(.22,1,.36,1),transform .22s cubic-bezier(.22,1,.36,1)}.prev.is-visible{opacity:1;transform:scale(1)}.prev.is-removing{opacity:0;transform:scale(.94)}.prev img{max-height:140px;border-radius:9px;border:1px solid var(--border);display:block}#dz .upload-ico,#dz .upload-txt,#dz .upload-hint{transition:opacity .18s cubic-bezier(.22,1,.36,1)}#dz.has-preview .upload-ico,#dz.has-preview .upload-txt,#dz.has-preview .upload-hint{opacity:0}.upload{transition:border-color .2s cubic-bezier(.22,1,.36,1),background-color .2s cubic-bezier(.22,1,.36,1)}#sizeQuick .just-selected,.size-stock-chip.just-selected{animation:chipPop .18s cubic-bezier(.22,1,.36,1)}
.prev-rm{position:absolute;top:-8px;right:-8px;background:var(--err);color:#fff;border:none;width:22px;height:22px;border-radius:50%;font-size:.95rem;line-height:22px;text-align:center;cursor:pointer;transition:transform .18s}
.prev-rm:hover{transform:scale(1.15)}

/* review */
.review-card{background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:1rem 1.1rem;margin-bottom:.6rem}.review-card.review-reveal{animation:reviewReveal .24s cubic-bezier(.22,1,.36,1) both}
.review-row{display:flex;justify-content:space-between;align-items:center;padding:.3rem 0;font-size:.83rem}
.review-row:not(:last-child){border-bottom:1px dashed var(--border)}
.rr-l{color:var(--muted);font-size:.75rem}
.rr-v{font-weight:600}
.review-img{width:60px;height:60px;border-radius:9px;object-fit:cover;border:1px solid var(--border);margin-bottom:.4rem}
.review-validade-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.22rem .6rem;border-radius:20px;font-size:.72rem;font-weight:700}

/* step footer */
.mstep-foot{padding:1rem 1.5rem;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:.7rem}
.btn-back{display:flex;align-items:center;gap:.3rem;padding:.65rem 1.1rem;border:1px solid var(--border);border-radius:9px;background:var(--card);color:var(--muted);font-size:.83rem;font-weight:600;cursor:pointer;transition:all .18s}
.btn-back:hover{background:var(--bg);color:var(--ink)}
.btn-next{display:flex;align-items:center;justify-content:center;gap:.4rem;padding:.65rem 1.3rem;border:none;border-radius:9px;background:var(--p);color:#fff;font-size:.83rem;font-weight:700;cursor:pointer;transition:background-color .22s,transform .22s,width .22s cubic-bezier(.22,1,.36,1);box-shadow:0 4px 14px var(--p-glow);margin-left:auto}.btn-next .save-check{animation:savedCheck .24s cubic-bezier(.22,1,.36,1) both}
.btn-next:hover{background:var(--p-dark);transform:translateY(-2px)}
.btn-next:disabled{background:var(--faint);box-shadow:none;transform:none;cursor:not-allowed}
.btn-next .material-symbols-outlined{font-size:1rem}
.step-lbl{font-size:.72rem;color:var(--faint);font-weight:500}

/* BANNER ALERTA VENCIMENTO */
.val-banner{
    background:linear-gradient(135deg,rgba(239,68,68,.07),rgba(245,158,11,.06));
    border:1px solid rgba(239,68,68,.2);border-radius:var(--r);
    padding:.85rem 1.1rem;margin-bottom:1rem;
    display:flex;align-items:center;gap:.7rem;
}
.val-banner .material-symbols-outlined{font-size:1.3rem;color:var(--err);flex-shrink:0}
.val-banner-txt{font-size:.8rem;font-weight:600;color:var(--ink)}
.val-banner-txt span{color:var(--err)}

/* CFG MODAL */
.cfg{display:none;position:fixed;inset:0;background:rgba(15,28,46,.55);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(5px)}
.cbox{background:var(--card);border-radius:16px;width:90%;max-width:420px;padding:1.7rem;box-shadow:0 24px 64px rgba(0,0,0,.2);animation:popIn .28s cubic-bezier(.22,1,.36,1) both}
.mh{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem}
.mh h2{font-size:1rem;font-weight:700}
.mcl{font-size:1.7rem;cursor:pointer;color:var(--faint);line-height:1;transition:color .15s}
.mcl:hover{color:var(--ink)}
.btn-save{background:var(--p);color:#fff;border:none;padding:.75rem;border-radius:10px;font-weight:700;width:100%;font-size:.88rem;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:.45rem;transition:all .22s;cursor:pointer;box-shadow:0 4px 14px var(--p-glow);margin-top:.9rem}
.btn-save:hover{background:var(--p-dark);transform:translateY(-2px)}
.btn-save:disabled{background:var(--faint);box-shadow:none;transform:none;cursor:not-allowed}

/* TOAST */
.toast-wrap{position:fixed;bottom:1.2rem;right:1.2rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;pointer-events:none}
.toast{background:var(--ink);color:#fff;padding:.7rem 1.1rem;border-radius:10px;font-size:.82rem;font-weight:500;display:flex;align-items:center;gap:.5rem;animation:fadeUp .35s cubic-bezier(.22,1,.36,1) both;box-shadow:0 8px 24px rgba(0,0,0,.2);pointer-events:auto}
.toast .material-symbols-outlined{font-size:1rem;animation:iconBounce .28s cubic-bezier(.22,1,.36,1) both}.toast.is-older{margin-bottom:.12rem;transform:scale(.985);opacity:.93;transition:margin .22s cubic-bezier(.22,1,.36,1),transform .22s cubic-bezier(.22,1,.36,1),opacity .22s}
.toast.ok{background:var(--ok)}
.toast.err{background:var(--err)}
@keyframes toastOut{to{opacity:0;transform:translateX(20px)}}

/* BOTTOM NAV */
.bnav{display:none;position:fixed;bottom:0;left:0;right:0;background:var(--card);border-top:1px solid var(--border);z-index:300;padding:.35rem 0 calc(.35rem + env(safe-area-inset-bottom));box-shadow:0 -2px 16px rgba(15,28,46,.07)}
.bnav-inner{display:flex;justify-content:space-around}
.bnav-a{display:flex;flex-direction:column;align-items:center;gap:2px;padding:.35rem .6rem;border-radius:9px;color:var(--faint);font-size:.62rem;font-weight:600;transition:all .18s;flex:1}
.bnav-a .material-symbols-outlined{font-size:1.25rem}
.bnav-a.on{color:var(--p)}
.bnav-a:hover{background:var(--p-dim)}

@media(max-width:1200px){.kpis{grid-template-columns:repeat(3,1fr)}}
@media(max-width:1050px){.kpis{grid-template-columns:repeat(2,1fr)}}
@media(max-width:860px){.sb{transform:translateX(-100%)}.sb.open{transform:translateX(0)}.main{margin-left:0}.ham{display:flex}}
@media(prefers-reduced-motion:reduce){.step-panel,.sdot,.sdot::after,.product-optional-content,.product-optional-control>summary::after,.toast,.toast .material-symbols-outlined,.toast.is-older,.btn-next,.prev,.upload,.field-confirm,.save-background,.review-card,#ppMargin,#sizeQuick .just-selected{animation:none!important;transition:none!important}}
@media(max-width:600px){.bnav{display:block}.main{padding:.9rem .9rem calc(68px + env(safe-area-inset-bottom))}.top{flex-direction:column;align-items:flex-start;margin-bottom:1rem}.searchbox{min-width:0;width:100%}.greeting{font-size:1.2rem}.pills{gap:.4rem}.pill{padding:.46rem .8rem;font-size:.76rem}.kpis{grid-template-columns:1fr 1fr;gap:.6rem}.kpi-val{font-size:1.35rem}.pg{grid-template-columns:repeat(2,minmax(0,1fr))}.pc{min-width:0}.mbox{width:96%;max-height:96vh}.fg{grid-template-columns:1fr}.size-stock-dialog{width:calc(100vw - 1rem);max-height:calc(100vh - 1rem);border-radius:15px}.size-stock-head{padding:1rem}.size-stock-body{padding:.85rem 1rem}.size-stock-footer{padding:.75rem 1rem calc(.75rem + env(safe-area-inset-bottom))}.size-stock-change{grid-template-columns:1fr 1fr}.size-stock-change span:last-child{grid-column:1/-1;text-align:left}.product-action-popover{min-width:170px}}
@media(max-width:380px){.kpis{grid-template-columns:1fr}.pg{grid-template-columns:1fr}}
@media(min-width:900px){.product-editor-modal .mstep-body{display:grid;grid-template-columns:minmax(0,1.18fr) minmax(290px,.82fr);gap:1rem;align-items:start}.product-editor-modal #s1,.product-editor-modal #s2,.product-editor-modal #s5{grid-column:1}.product-editor-modal #s6{grid-column:2;grid-row:1;position:sticky;top:0}.product-editor-modal #s1 .fg,.product-editor-modal #s2 .fg{grid-template-columns:repeat(2,minmax(0,1fr))}.product-editor-modal #s1 .fw,.product-editor-modal #s2 .fw{grid-column:1/-1}.product-editor-modal #s1 #productNameField,.product-editor-modal #s1 #productSkuField{grid-column:auto}.product-editor-modal[data-business-family="alimentacao"] #s1 #productNameField,.product-editor-modal[data-business-family="alimentacao"] #s1 #productSkuField{grid-column:1/-1}}
@media(max-width:600px){.product-editor-modal{align-items:stretch}.product-editor-modal .mbox{width:100%;height:100dvh;max-height:100dvh;border-radius:0}.product-editor-modal .mstep-head{padding:1.15rem 1rem .9rem}.product-editor-modal .mstep-body{min-height:0;overflow-y:auto;overscroll-behavior:contain;padding:.8rem}.product-editor-modal .mstep-head,.product-editor-modal .mstep-foot{flex:0 0 auto}.editor-section{padding:.85rem;margin-bottom:.7rem}.editor-section-heading{gap:.6rem}.editor-section-badge{display:none}.mstep-foot{padding:.75rem .8rem calc(.75rem + env(safe-area-inset-bottom))}.editor-actions{width:100%;display:grid;grid-template-columns:1fr 1fr}.editor-actions .btn-next{grid-column:1/-1;width:100%;justify-content:center;margin-left:0}.editor-actions .btn-save-another,.mstep-foot .btn-back{justify-content:center;width:100%}.product-editor-modal .fg{grid-template-columns:1fr}.product-editor-modal .step-dots,.product-editor-modal .step-lbl{display:none!important}}
.customization-choice{min-height:44px;cursor:pointer;gap:.45rem}.customization-choice.on{background:var(--p-dim);border-color:var(--p);color:var(--p)}.customization-choice input{accent-color:var(--p)}
#pCategoriaControl[hidden],#pNovaCategoriaWrap[hidden],#categoryRetry[hidden],#categoryLoadStatus[hidden]{display:none!important}
.product-optional-control{margin-top:1rem;border:1px solid var(--border);border-radius:11px;background:var(--card);overflow:hidden}
.product-optional-control>summary{display:flex;align-items:center;justify-content:space-between;gap:.7rem;min-height:48px;padding:.75rem .9rem;color:var(--ink);font-size:.78rem;font-weight:700;cursor:pointer;list-style:none}
.product-optional-control>summary::-webkit-details-marker{display:none}
.product-optional-control>summary::after{content:'+';font-size:1.05rem;color:var(--p);transition:transform .22s cubic-bezier(.22,1,.36,1)}.product-optional-control[open]>summary::after{transform:rotate(45deg)}.product-optional-control>.product-optional-content{display:grid;grid-template-rows:1fr;padding:0 .85rem .85rem;transition:grid-template-rows .28s cubic-bezier(.22,1,.36,1),padding .28s cubic-bezier(.22,1,.36,1);overflow:hidden}.product-optional-control:not([open])>.product-optional-content{grid-template-rows:0fr;padding-top:0;padding-bottom:0}.optional-inner{min-height:0;overflow:hidden}
.category-load-status{margin-top:.5rem;color:var(--muted);font-size:.72rem;line-height:1.45}
.category-retry{margin-top:.45rem}
.val-toggle-wrap{width:100%;font:inherit;color:inherit;text-align:left}
.val-toggle-wrap:focus-visible{outline:3px solid var(--p);outline-offset:2px}
.wizard-inline-link{border:0;background:transparent;color:var(--p);font:inherit;font-weight:700;text-decoration:underline;cursor:pointer;min-height:30px}
.wizard-quick-save{margin:1.1rem 0 .25rem auto;min-height:46px}
.wizard-quick-save[hidden]{display:none!important}
.product-editor-modal .step-dots{display:none}
.product-assist{display:grid;grid-template-columns:34px minmax(0,1fr);gap:.45rem .65rem;align-items:center;max-width:680px;margin-top:.8rem}
.product-assist-count{width:32px;height:32px;display:grid;place-items:center;border:1px solid rgba(255,255,255,.4);border-radius:50%;font-size:.78rem;font-weight:750}
.product-assist-copy{display:flex;flex-direction:column;gap:.15rem;min-width:0}
.product-assist-copy strong{font-size:.76rem;font-weight:750}
.product-assist-copy span{font-size:.7rem;line-height:1.4;opacity:.84}
.product-assist-track{grid-column:1/-1;height:4px;border-radius:99px;background:rgba(255,255,255,.25);overflow:hidden}
.product-assist-track span{display:block;width:8%;height:100%;border-radius:inherit;background:#fff;transition:width .24s ease}
.assist-continue{display:inline-flex;align-items:center;gap:.4rem;min-height:42px;margin:.85rem 0 0;padding:.55rem .85rem;border:1px solid var(--p);border-radius:9px;background:var(--p-dim);color:var(--p);font:700 .78rem Inter;cursor:pointer}
.assist-continue:hover,.assist-continue:focus-visible{background:var(--p);color:#fff;outline:none}
.assist-continue[hidden]{display:none!important}
.modal[data-business-family="alimentacao"] #productNameField{order:1}
.modal[data-business-family="alimentacao"] #pCategoriaControl{order:2}
.modal[data-business-family="alimentacao"] #productDescriptionField{order:3}
.modal[data-business-family="alimentacao"] #productSkuField{order:4}
</style>
<link rel="stylesheet" href="assets/app-theme.css?v=20260819.1">
<script src="assets/app-theme.js?v=20260819.1"></script>
<link rel="stylesheet" href="assets/product-complements.css?v=20261009.5">
<script src="assets/product-complements.js?v=20261010.1"></script>
<link rel="stylesheet" href="assets/product-editor.css?v=20261016.2">
<link rel="stylesheet" href="assets/product-catalog.css?v=20261016.2">
</head>
<body>
<div class="app">
<div class="overlay" id="ov"></div>

<!-- SIDEBAR -->
<?php require __DIR__ . '/includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="main">
    <div class="val-banner" id="valBanner" style="display:none">
        <span class="material-symbols-outlined">warning</span>
        <div class="val-banner-txt" id="valBannerTxt"></div>
    </div>

    <div class="top anim d1">
        <div class="top-l">
            <button class="ham" id="ham"><span class="material-symbols-outlined">menu</span></button>
            <div>
                <div class="greeting">Produtos</div>
                <div class="sub">Gerencie seu catálogo completo</div>
            </div>
        </div>
    </div>

    <div class="pills anim d2">
        <button class="pill pill-solid" id="btnNovo">
            <span class="material-symbols-outlined">add</span>Novo Produto
        </button>
        <a class="pill pill-outline" href="grupos_complementos.php"><span class="material-symbols-outlined">category</span>Grupos de complementos</a>
        <details class="action-menu"><summary class="pill pill-outline"><span class="material-symbols-outlined">file_upload</span>Importar</summary><div><button id="btnLote" type="button">Upload em massa</button></div></details>
        <details class="action-menu"><summary class="pill pill-outline"><span class="material-symbols-outlined">more_horiz</span>Ações</summary><div><button id="btnCustos" type="button">Atualizar custos</button></div></details>
    </div>

    <section class="card anim d3" id="loteCard" style="display:none;margin-bottom:1.3rem">
        <div class="ch">
            <div>
                <div class="ct">Cadastro em lote por fotos</div>
                <div class="sub">Envie até 100 fotos, revise tudo na mesma tela e publique quando estiver pronto.</div>
            </div>
            <button class="tbl-btn" id="btnFecharLote" type="button" title="Fechar"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div id="loteUploadState">
            <label class="upload" for="loteFotos" style="display:flex;flex-direction:column;min-height:150px;cursor:pointer">
                <span class="material-symbols-outlined upload-ico">cloud_upload</span>
                <div class="upload-txt">Selecione 20, 50 ou até 100 fotos</div>
                <div class="upload-hint">JPG · PNG · WEBP — até 5 MB por foto</div>
                <input id="loteFotos" type="file" accept="image/jpeg,image/png,image/webp" multiple hidden>
            </label>
        </div>
        <div id="loteEditor" style="display:none">
            <div class="lote-toolbar">
                <label class="lote-check"><input id="loteTodos" type="checkbox"> Selecionar todos</label>
                <span id="loteCount" class="hint"></span>
                <div class="lote-bulk">
                    <input id="lotePreco" type="number" min="0" step="0.01" placeholder="Preço para selecionados">
                    <input id="loteCusto" type="number" min="0" step="0.01" placeholder="Custo para selecionados">
                    <input id="loteQtd" type="number" min="0" step="1" placeholder="Estoque para selecionados">
                    <input id="loteCategoria" maxlength="80" placeholder="Categoria para selecionados">
                    <input id="loteValidade" type="date" title="Validade para selecionados">
                    <button class="pill pill-outline" id="btnAplicarLote" type="button">Aplicar</button>
                </div>
            </div>
            <div id="loteRows" class="lote-rows"></div>
            <div class="lote-footer">
                <span class="hint">Use Tab para navegar pelos campos. Enter aplica o valor e avança.</span>
                <div style="display:flex;gap:.5rem">
                    <button class="pill pill-outline" id="btnCancelarLote" type="button">Cancelar</button>
                    <button class="pill pill-solid" id="btnPublicarLote" type="button"><span class="material-symbols-outlined">publish</span>Publicar todos</button>
                </div>
            </div>
        </div>
    </section>

    <div class="kpis anim d3" id="kpis">
        <?php for($i=0;$i<5;$i++):?>
        <div class="kpi"><div class="sk" style="height:1.6rem;width:55%;margin-bottom:.4rem"></div><div class="sk" style="height:.7rem;width:70%"></div></div>
        <?php endfor;?>
    </div>

    <div class="catalog-layout" id="catalogLayout">
    <section class="card catalog-panel">
        <div class="ch">
            <span class="ct">Catálogo <small id="catalogBusinessContext" class="catalog-context"></small></span>
            <div style="display:flex;align-items:center;gap:.6rem">
                <div class="view-tog">
                    <button class="vt-btn on" id="vGrid" title="Grade"><span class="material-symbols-outlined">grid_view</span></button>
                    <button class="vt-btn" id="vList" title="Lista"><span class="material-symbols-outlined">view_list</span></button>
                </div>
            </div>
        </div>

        <div class="filters">
            <div class="searchbox catalog-search"><span class="material-symbols-outlined">search</span><input id="busca" placeholder="Buscar por nome ou SKU..." type="text"></div>
            <button class="filter-btn on" data-f="all">Todos</button>
            <button class="filter-btn" data-f="ok">Em estoque</button>
            <button class="filter-btn" data-f="low">Estoque baixo</button>
            <button class="filter-btn" data-f="zero">Zerados</button>
            <button class="filter-btn exp-filter" data-f="exp">A vencer</button>
            <details class="action-menu"><summary class="filter-btn">Mais filtros</summary><div><button class="filter-btn" data-f="no-cost">Sem custo</button><button class="filter-btn" data-f="no-image">Sem imagem</button><button class="filter-btn" data-f="incomplete">Cadastro incompleto</button></div></details>
            <select class="sort-sel" id="sortSel">
                <option value="name">Nome A–Z</option>
                <option value="name-d">Nome Z–A</option>
                <option value="price">Menor preço</option>
                <option value="price-d">Maior preço</option>
                <option value="stock">Menos estoque</option>
                <option value="stock-d">Mais estoque</option>
                <option value="val-asc">Validade mais próxima</option>
                <option value="val-desc">Validade mais distante</option>
            </select>
            <span class="catalog-result-count" id="catalogResultCount" aria-live="polite"></span>
        </div>

        <div class="catalog-load-state" id="catalogLoadState" role="status" hidden></div>

        <div class="pg" id="pg">
            <?php for($i=0;$i<6;$i++):?>
            <div class="pc"><div class="sk" style="height:130px;border-radius:0"></div><div style="padding:.75rem .85rem"><div class="sk" style="height:.8rem;margin-bottom:.35rem"></div><div class="sk" style="height:.7rem;width:55%"></div></div></div>
            <?php endfor;?>
        </div>

        <div class="tbl-wrap" id="tbl" style="display:none">
            <table class="tbl">
                <thead><tr>
                    <th>Produto</th><th>Código</th><th>Preço</th>
                    <th>Estoque</th><th>Validade</th><th>Status</th><th>Ações</th>
                </tr></thead>
                <tbody id="tblBody"></tbody>
            </table>
        </div>
        <div class="catalog-pagination" id="catalogPagination" hidden><button class="pill pill-outline" id="catalogMore" type="button">Mostrar mais produtos</button></div>
    </section>
    <div class="catalog-inspector-backdrop" id="catalogInspectorBackdrop" hidden></div>
    <aside class="card catalog-inspector" id="catalogInspector" aria-labelledby="catalogInspectorTitle">
        <header class="catalog-inspector-head">
            <button type="button" class="catalog-inspector-toggle" id="catalogInspectorToggle" aria-controls="catalogInspectorBody" aria-expanded="false"><span id="catalogInspectorTitle">Inspeção rápida</span><span class="material-symbols-outlined" aria-hidden="true">expand_less</span></button>
            <button type="button" class="catalog-inspector-close" id="catalogInspectorClose" aria-label="Fechar inspeção"><span class="material-symbols-outlined" aria-hidden="true">close</span></button>
        </header>
        <div class="catalog-inspector-body" id="catalogInspectorBody">
            <div class="catalog-inspector-empty" id="catalogInspectorEmpty"><span class="material-symbols-outlined" aria-hidden="true">touch_app</span><p>Selecione um produto para ver seus detalhes.</p></div>
            <div id="catalogInspectorContent" hidden></div>
        </div>
        <footer class="catalog-inspector-foot" id="catalogInspectorFoot" hidden><button class="pill pill-solid" id="catalogEdit" type="button"><span class="material-symbols-outlined">edit</span>Editar cadastro completo</button></footer>
    </aside>
    </div>
</main>
</div>

<nav class="bnav">
    <div class="bnav-inner">
        <a href="estoque.php"    class="bnav-a"><span class="material-symbols-outlined">dashboard</span>Início</a>
        <a href="relatorio.php"  class="bnav-a"><span class="material-symbols-outlined">bar_chart</span>Relatórios</a>
        <a href="produtos.php"   class="bnav-a on"><span class="material-symbols-outlined">inventory</span>Produtos</a>
        <a href="vendedores.php" class="bnav-a"><span class="material-symbols-outlined">group</span>Vendedores</a>
    </div>
</nav>

<div class="modal product-editor-modal" id="modal" data-business-family="<?= htmlspecialchars((string)($businessPreset['familia'] ?? 'generico'), ENT_QUOTES, 'UTF-8') ?>">
  <div class="mbox" role="dialog" aria-modal="true" aria-labelledby="stepTitle" aria-describedby="stepSub">
    <header class="mstep-head">
      <span class="editor-head-icon" aria-hidden="true"><svg class="" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7 12 3 4 7m16 0-8 4-8-4m16 0v10l-8 4-8-4V7m8 4v10"/></svg></span>
      <div class="editor-head-copy"><h2 id="stepTitle">Novo produto <span id="saveBackground" class="save-background" role="status" aria-label="Finalizando salvamento"></span></h2><p id="stepSub">Configure seu produto para o catálogo e a gestão do negócio.</p></div>
      <button type="button" class="mstep-close" id="mclose" aria-label="Fechar cadastro"><span class="editor-close-glyph" aria-hidden="true">×</span></button>
    </header>
    <div class="mstep-body">
      <div class="editor-form" id="productEditorFields">
        <div id="editorSaveNotice" class="editor-save-notice" role="alert" hidden></div>
        <div class="editor-operation">
          <div><span class="editor-eyebrow">Perfil de operação</span><p id="businessContextHint">Ajuste a apresentação deste produto.</p></div>
          <button type="button" class="preview-jump" id="previewJump">Ver prévia</button><div class="operation-options" role="group" aria-label="Perfil de operação deste cadastro"><button type="button" data-operation="varejo" aria-pressed="false">Varejo e vestuário</button><button type="button" data-operation="alimentacao" aria-pressed="false">Alimentação</button><button type="button" data-operation="generico" aria-pressed="false">Geral</button></div>
        </div>
        <section class="editor-section" id="s1" aria-labelledby="identificationTitle">
          <div class="editor-section-heading"><h3 id="identificationTitle"><span class="section-number">01</span>Identificação do produto</h3><span class="editor-required">* Obrigatórios</span></div>
          <div class="fg">
            <div class="fl fw" id="productNameField"><label for="pNome">Nome do produto <span aria-hidden="true">*</span></label><input id="pNome" required placeholder="Ex.: Camiseta esportiva" autocomplete="off"><span class="hint">Como o produto aparecerá no catálogo.</span></div>
            <div class="fl" id="productSkuField"><div class="field-label-row"><label for="pCod">Código / SKU</label><button type="button" id="genSku" class="wizard-inline-link">Gerar automático</button></div><input id="pCod" placeholder="Gerado ao salvar se ficar vazio" autocomplete="off"><span class="hint">Identificador único do produto.</span></div>
            <div class="fl" id="pCategoriaControl" hidden><label for="pCategoria">Categoria principal</label><div class="category-picker"><select id="pCategoria"><option value="">Sem categoria</option></select><button class="category-add" type="button" id="btnCriarCategoria" aria-label="Criar categoria" title="Criar categoria">+</button></div><input id="pCategoriaSearch" type="search" placeholder="Filtrar categorias" autocomplete="off" aria-label="Filtrar categorias"><div id="pNovaCategoriaWrap" hidden><input id="pNovaCategoria" maxlength="80" placeholder="Nome da categoria" aria-label="Nome da nova categoria"><div class="category-create-actions"><button class="pill pill-solid" id="pSalvarCategoria" type="button">Criar</button><button class="pill pill-outline" id="pCancelarCategoria" type="button">Cancelar</button></div></div><span class="hint">Compartilhada com a vitrine.</span><div id="categoryLoadStatus" class="category-load-status" role="status" hidden></div><button class="pill pill-outline category-retry" type="button" id="categoryRetry" hidden>Tentar novamente</button></div>
            <div class="fl fw" id="productDescriptionField"><label for="pDesc" id="productDescriptionLabel">Descrição comercial</label><textarea id="pDesc" rows="3" placeholder="Material, acabamento e detalhes que ajudam o cliente a escolher."></textarea></div>
          </div>
        </section>
        <section class="editor-section" id="s2" aria-labelledby="pricingTitle">
          <div class="editor-section-heading"><h3 id="pricingTitle"><span class="section-number">02</span>Preço e formação de margem</h3><span class="section-note">Em tempo real</span></div>
          <div class="fg"><div class="fl"><label for="pCusto">Preço de custo (R$)</label><input id="pCusto" type="number" step="0.01" min="0" inputmode="decimal" placeholder="0,00" oninput="updatePreview()"><span class="hint">Custo da mercadoria ou dos insumos.</span></div><div class="fl"><label for="pPreco">Preço de venda (R$) <span aria-hidden="true">*</span></label><input id="pPreco" type="number" step="0.01" min="0.01" required inputmode="decimal" placeholder="0,00" oninput="updatePreview()"><span class="hint">Valor exibido no catálogo.</span></div></div>
          <div class="price-preview" id="pricePreview"><div class="profit-main"><span class="profit-icon" aria-hidden="true"><svg class="" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 17 9 11l4 4 8-8m-7 0h7v7"/></svg></span><div><span class="pp-lbl">Lucro bruto unitário</span><strong class="pp-val" id="ppVal">—</strong><span id="ppMarkup" class="profit-markup">Informe o custo para calcular</span></div></div><div class="profit-margin"><span class="pp-lbl">Margem bruta</span><strong class="pp-val" id="ppMargin">—</strong></div></div>
        </section>
        <section class="editor-section" id="stockSection" aria-labelledby="stockTitle">
          <div class="editor-section-heading"><h3 id="stockTitle"><span class="section-number">03</span>Estoque e disponibilidade</h3></div>
          <div class="stock-modes" role="group" aria-label="Modo de disponibilidade"><button type="button" class="stock-mode-tile on" id="availabilityStock" aria-pressed="true" <?= $commercialAvailabilityReady ? '' : 'disabled' ?>><span class="stock-mode-symbol" aria-hidden="true"><svg class="" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7 12 3 4 7m16 0-8 4-8-4m16 0v10l-8 4-8-4V7m8 4v10"/></svg></span><span><strong>Saldo físico</strong><small>A disponibilidade acompanha o estoque.</small></span></button><button type="button" class="stock-mode-tile" id="availabilityDemand" aria-pressed="false" <?= $commercialAvailabilityReady ? '' : 'disabled' ?>><span class="stock-mode-symbol" aria-hidden="true"><svg class="" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 3v6a2 2 0 0 0 4 0V3M6 3v18M18 3c-4 2-5 9 0 9v9m0-18v9"/></svg></span><span><strong>Sob demanda</strong><small>Preparado ou disponibilizado após o pedido.</small></span></button></div>
          <p class="hint" id="availabilityModeHint">A venda depende do saldo físico informado.</p>
          <?php if (!$commercialAvailabilityReady): ?><p class="hint">O modo sob demanda ainda não está disponível nesta instalação.</p><?php endif; ?>
          <div class="stock-controls"><div class="fl" id="stockQuantityField"><label for="pQtd" id="stockQuantityLabel">Quantidade inicial em estoque</label><div class="qty-stepper"><button type="button" class="qs-btn" id="qsMinus" aria-label="Diminuir quantidade">−</button><input class="qs-inp" id="pQtd" type="number" min="0" step="1" value="1" inputmode="numeric"><button type="button" class="qs-btn" id="qsPlus" aria-label="Aumentar quantidade">+</button></div><span class="hint" id="stockQuantityHint">Informe a quantidade disponível.</span></div><label class="manual-availability" for="availabilityManual"><input type="checkbox" id="availabilityManual" <?= $commercialAvailabilityReady ? '' : 'disabled' ?> checked><span><strong>Disponível para venda</strong><small>Desative para pausar o produto sem alterar seu saldo.</small></span></label></div>
        </section>
        <section class="editor-section" id="variationsSection" aria-labelledby="variationsTitle">
          <div class="editor-section-heading"><h3 id="variationsTitle"><span class="section-number">04</span>Variações e adicionais</h3></div>
          <p class="section-intro">Tamanhos e complementos podem ser usados juntos no mesmo produto.</p>
          <details class="product-optional-control" id="productSizesDetails"><summary><span id="sizesSummaryLabel">Tamanhos e quantidades</span><span class="section-note">Opcional</span></summary><div class="product-optional-content"><div class="optional-inner"><div class="fl" id="sizeControl"><label id="sizesChoiceLabel">Este produto possui tamanhos?</label><div class="size-choice"><button type="button" class="pill pill-outline" id="sizeNo">Não</button><button type="button" class="pill pill-outline" id="sizeYes">Sim</button></div><div id="sizeEditor" hidden><strong class="size-editor-label">Tamanhos do produto</strong><div id="sizeQuick"></div><button type="button" class="pill pill-outline" id="sizeCustom">+ Adicionar tamanho</button><div id="sizeRows"></div><div id="sizeTotal">Estoque total: 0 unidades</div></div><div id="sizeSuggestion" hidden></div></div></div></div></details>
          <details class="product-optional-control" id="productCustomizationDetails"><summary>Complementos e adicionais<span class="section-note">Opcional</span></summary><div class="product-optional-content"><div class="optional-inner"><fieldset class="fl customization-fieldset"><legend>Oferecer personalização?</legend><p class="hint">Crie grupos ou reutilize as opções já cadastradas.</p><div class="size-choice" role="radiogroup" aria-label="Personalização"><label class="pill pill-outline customization-choice" for="customizationNo"><input id="customizationNo" name="customization" type="radio" value="no" checked> Não</label><label class="pill pill-outline customization-choice" for="customizationYes"><input id="customizationYes" name="customization" type="radio" value="yes"> Sim</label></div></fieldset><div id="s5" hidden><div id="productComplements" class="ce"></div></div></div></div></details>
        </section>
        <section class="editor-section" id="mediaSection" aria-labelledby="mediaTitle">
          <div class="editor-section-heading"><h3 id="mediaTitle"><span class="section-number">05</span>Fotografia e configurações</h3><span class="section-note">Opcional</span></div>
          <details class="product-optional-control" id="productImageDetails" open><summary>Foto do produto<span class="section-note">Até 5 MB</span></summary><div class="product-optional-content" id="productImageContent"><div class="optional-inner">
                <div class="fl">
                    <label>Foto do produto</label>
                    <div class="upload" id="dz" role="button" tabindex="0" aria-label="Selecionar ou substituir foto do produto">
                        <span class="material-symbols-outlined upload-ico">cloud_upload</span>
                        <div class="upload-txt">Clique ou arraste a imagem aqui</div>
                        <div class="upload-hint">PNG · JPG · WEBP — máx 5 MB</div>
                        <input type="file" id="fImg" accept="image/png,image/jpeg,image/webp" hidden>
                        <div class="prev" id="prevW" style="display:none">
                            <img id="prevI" alt="">
                            <button type="button" class="prev-rm" id="rmPrev" aria-label="Remover foto do produto" title="Remover foto">×</button>
                        </div>
                    </div>
                    <span class="hint" id="imageActionHint" style="margin-top:.4rem">A foto aparece no catálogo e no painel de vendas. Você pode remover uma foto existente antes de salvar.</span>
                </div>
            </div></div></details>
          <details class="product-optional-control" id="productValidityDetails"><summary>Validade e alertas<span class="section-note">Opcional</span></summary><div class="product-optional-content" id="productValidityContent"><div class="optional-inner">
                <div class="val-toggle-wrap" id="valToggleWrap" role="button" tabindex="0" aria-pressed="false" onclick="toggleValidade()" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();toggleValidade();}">
                    <div class="val-check" id="valCheck">
                        <span class="material-symbols-outlined">check</span>
                    </div>
                    <div class="val-toggle-txt">
                        <div class="val-toggle-title">Este produto tem validade?</div>
                        <div class="val-toggle-sub">Ative para monitorar a data de vencimento e receber alertas</div>
                    </div>
                    <div class="val-toggle-ico">
                        <span class="material-symbols-outlined">event_available</span>
                    </div>
                </div>

                <div class="val-date-section" id="valDateSection">
                    <div class="fg">
                        <div class="fl fw">
                            <label for="pValidade">Data de validade *</label>
                            <input type="date" id="pValidade" oninput="updateValAlert()">
                            <span class="hint">Data impressa na embalagem do produto</span>
                        </div>
                    </div>

                    <div id="valAlertInfo" style="margin-top:.6rem"></div>

                    <div style="margin-top:.8rem;padding:.75rem .9rem;background:var(--bg);border-radius:10px;border:1px solid var(--border)">
                        <div style="font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.5rem">Legenda de alertas</div>
                        <div style="display:flex;flex-direction:column;gap:.35rem">
                            <div style="display:flex;align-items:center;gap:.5rem;font-size:.77rem;color:var(--muted)">
                                <span class="val-chip venc"><span class="material-symbols-outlined">block</span>Vencido</span>
                                Data já passou
                            </div>
                            <div style="display:flex;align-items:center;gap:.5rem;font-size:.77rem;color:var(--muted)">
                                <span class="val-chip crit"><span class="material-symbols-outlined">warning</span>Crítico</span>
                                Vence em até 7 dias
                            </div>
                            <div style="display:flex;align-items:center;gap:.5rem;font-size:.77rem;color:var(--muted)">
                                <span class="val-chip alrt"><span class="material-symbols-outlined">schedule</span>Alerta</span>
                                Vence em até 30 dias
                            </div>
                            <div style="display:flex;align-items:center;gap:.5rem;font-size:.77rem;color:var(--muted)">
                                <span class="val-chip aten"><span class="material-symbols-outlined">info</span>Atenção</span>
                                Vence em até 90 dias
                            </div>
                            <div style="display:flex;align-items:center;gap:.5rem;font-size:.77rem;color:var(--muted)">
                                <span class="val-chip ok"><span class="material-symbols-outlined">check_circle</span>OK</span>
                                Mais de 90 dias
                            </div>
                        </div>
                    </div>
                </div>

                <div id="valNenhumaTxt" style="text-align:center;padding:1.5rem;color:var(--faint);font-size:.85rem">
                    <span class="material-symbols-outlined" style="font-size:2rem;display:block;margin-bottom:.4rem;opacity:.25">event_busy</span>
                    Produto sem data de validade.<br>
                    <span style="font-size:.75rem">Ex: eletrônicos, roupas, ferramentas…</span>
                </div>
            </div></div></details>
        </section>
      </div>
      <aside class="editor-preview" id="s6" aria-label="Prévia do produto"><div class="preview-sticky"><div class="preview-heading"><span><i aria-hidden="true"></i>Prévia do catálogo</span><small>Tempo real</small></div><div id="reviewW">
        <article class="live-product-card"><div class="live-product-photo"><div id="liveImagePlaceholder" class="live-image-placeholder"><span class="placeholder-image-icon" aria-hidden="true"><svg class="" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16v16H4zM4 16l5-5 4 4 3-3 4 4M8 8h.01"/></svg></span><span>Sua foto aparece aqui</span></div><img id="liveImage" alt="Foto do produto em cadastro" hidden><span id="liveCategory" class="live-category">Sem categoria</span></div><div class="live-product-body"><p id="liveSku" class="live-sku">SKU automático ao salvar</p><h3 id="liveName">Nome do produto</h3><p id="liveDescription" class="live-description">A descrição comercial aparecerá aqui.</p><div id="liveSizes" class="live-sizes" hidden></div><p id="liveComplements" class="live-complements" hidden></p><div class="live-product-bottom"><div><span class="preview-price-label">Preço de venda</span><strong id="livePrice">R$ —</strong></div><span id="liveAvailability" class="live-availability">Em estoque: 1</span></div><p id="liveValidity" class="live-validity" hidden></p></div></article>
        <section class="financial-summary" aria-labelledby="financialTitle"><h4 id="financialTitle">Demonstrativo unitário</h4><dl><div><dt>Preço de venda</dt><dd id="financeSale">—</dd></div><div><dt>Custo da mercadoria</dt><dd id="financeCost">Não informado</dd></div><div class="finance-total"><dt>Lucro bruto</dt><dd id="financeProfit">—</dd></div><div><dt>Margem bruta</dt><dd id="financeMargin">—</dd></div><div><dt>Markup sobre o custo</dt><dd id="financeMarkup">—</dd></div></dl><p>Valores calculados com o preço e o custo informados, sem deduzir impostos ou despesas adicionais.</p></section>
      </div></div></aside>
    </div>
    <footer class="mstep-foot"><div class="editor-validation" id="editorValidation" role="status" aria-live="polite"><span class="validation-dot" aria-hidden="true"></span><span id="stepLbl">Preencha nome e preço de venda.</span></div><div class="editor-actions"><button type="button" class="btn-back" id="btnBack">Cancelar</button><button type="button" class="btn-save-another" id="btnSaveAnother">Salvar e cadastrar outro</button><button type="button" class="btn-next" id="btnNext"><svg class="" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Salvar produto</button></div></footer>
  </div>
</div>

<div class="cfg" id="cfg">
    <div class="cbox">
        <div class="mh">
            <h2>Configurações da conta</h2>
            <span class="mcl" id="cfgClose">close</span>
        </div>
        <div class="fl">
            <label>WhatsApp para vendas</label>
            <input id="waNum" placeholder="5599999999999" value="<?= htmlspecialchars($whatsapp_number) ?>">
            <span class="hint">DDI + DDD + número (apenas números)</span>
        </div>
        <button class="btn-save" id="btnWa"><span class="material-symbols-outlined">save</span>Salvar número</button>
    </div>
</div>

<div class="modal" id="sizeStockModal">
    <section class="size-stock-dialog" role="dialog" aria-modal="true" aria-labelledby="sizeStockTitle">
        <header class="size-stock-head">
            <div><h2 id="sizeStockTitle">Atualizar tamanhos</h2><p id="sizeStockName"></p><span id="sizeStockCurrent"></span></div>
            <button class="size-stock-close" id="sizeStockClose" type="button" aria-label="Fechar"><span class="material-symbols-outlined">close</span></button>
        </header>
        <div class="size-stock-body">
            <section class="size-stock-picker" id="sizeStockPicker" hidden>
                <div class="size-stock-section-title">Distribua o estoque entre os tamanhos</div>
                <p>Selecione os tamanhos disponíveis e informe a quantidade de cada um.</p>
                <div class="size-stock-chips" id="sizeStockQuick"></div>
                <div class="size-stock-custom"><input id="sizeStockCustomInput" type="text" maxlength="30" placeholder="Ex.: 38, Único, 3G"><button id="sizeStockCustom" type="button">Adicionar</button></div>
            </section>
            <section class="size-stock-list"><div class="size-stock-section-title">Tamanhos e quantidades</div><div id="sizeStockRows"></div></section>
            <section class="size-stock-summary">
                <div class="size-stock-summary-line"><span id="sizeStockDistributedLabel">Estoque total</span><strong id="sizeStockTotal">0 unidades</strong></div>
                <div class="size-stock-progress"><span id="sizeStockProgress"></span></div>
                <div class="size-stock-hint" id="sizeStockHint"></div>
                <div class="size-stock-change" id="sizeStockChange" hidden><span>Estoque anterior <strong id="sizeStockOld">0</strong></span><span>Novo estoque <strong id="sizeStockNew">0</strong></span><span>Diferença <strong id="sizeStockDelta">0</strong></span></div>
            </section>
        </div>
        <footer class="size-stock-footer"><button class="pill pill-outline" id="sizeStockCancel" type="button">Cancelar</button><button class="pill pill-solid" id="sizeStockSave" type="button"><span class="material-symbols-outlined">save</span>Salvar tamanhos</button></footer>
    </section>
</div>

<div class="product-action-popover" id="productActionPopover" role="menu" hidden></div>
<div class="toast-wrap" id="toastWrap"></div>

<script>
const CSRF = <?= json_encode(get_csrf_token()) ?>;
let categoriasDisponiveis=[],categoriaSelecionada=null,categoriasProntas=false,categoriasRequest=null;
function renderCategoriasProduto(){
    const select=document.getElementById('pCategoria');if(!select)return;
    select.replaceChildren(new Option('Sem categoria',''));
    const query=(document.getElementById('pCategoriaSearch')?.value||'').trim().toLocaleLowerCase('pt-BR');
    const selecionada=categoriasDisponiveis.find(c=>Number(c.id)===Number(categoriaSelecionada));
    const filtradas=categoriasDisponiveis.filter(c=>String(c.nome||'').toLocaleLowerCase('pt-BR').includes(query));
    if(selecionada&&!filtradas.some(c=>Number(c.id)===Number(selecionada.id)))filtradas.unshift(selecionada);
    filtradas.forEach(c=>select.add(new Option(c.nome,String(c.id))));
    if(query&&!filtradas.length){const empty=new Option('Nenhuma categoria encontrada','');empty.disabled=true;select.add(empty)}
    select.value=categoriaSelecionada===null?'':String(categoriaSelecionada);
    select.disabled=false;
    categoriasProntas=true;
    document.getElementById('pCategoriaControl').hidden=false;
    document.getElementById('categoryLoadStatus').hidden=true;
    document.getElementById('categoryRetry').hidden=true;
    buildReview();
}
async function carregarCategoriasProduto(){
    if(categoriasProntas)return true;
    if(categoriasRequest)return categoriasRequest;
    categoriasRequest=(async()=>{
        try{
            const {response:r,data:j}=await ProductComplementsEditor.fetchJson('catalogo_categorias_api.php?acao=listar',{credentials:'same-origin'});
            if(!r.ok||!j.ok)throw new Error(j.error||'Não foi possível carregar as categorias.');
            categoriasDisponiveis=Array.isArray(j.categorias)?j.categorias:[];renderCategoriasProduto();if(estoque?.length){refreshCatalog({reset:false});const selected=estoque.find(item=>Number(item.id)===Number(selectedProductId));if(selected)renderInspector(selected);}return true;
        }catch(error){
            console.warn('[Categorias] Erro ao carregar:',error);
            const control=document.getElementById('pCategoriaControl');
            control.hidden=false;document.getElementById('pCategoria').disabled=true;
            const status=document.getElementById('categoryLoadStatus');
            status.textContent='As categorias não carregaram. Você pode tentar novamente; os demais dados do produto serão preservados.';status.hidden=false;
            document.getElementById('categoryRetry').hidden=false;
            return false;
        }finally{categoriasRequest=null;}
    })();
    return categoriasRequest;
}
document.addEventListener('DOMContentLoaded',carregarCategoriasProduto);
document.addEventListener('click',async event=>{
    if(event.target.closest('#categoryRetry')){await carregarCategoriasProduto();return;}
    if(event.target.closest('#btnCriarCategoria')){document.getElementById('pNovaCategoriaWrap').hidden=false;document.getElementById('pNovaCategoria').focus();}
    if(event.target.closest('#pCancelarCategoria')){document.getElementById('pNovaCategoriaWrap').hidden=true;document.getElementById('pNovaCategoria').value='';}
    if(event.target.closest('#pSalvarCategoria')){
        const button=document.getElementById('pSalvarCategoria'),name=document.getElementById('pNovaCategoria').value.trim();
        if(!name){toast('Informe o nome da categoria.','err');return;}
        button.disabled=true;
        try{
            const body=new FormData();body.append('csrf_token',CSRF);body.append('nome',name);
            const {response:r,data:j}=await ProductComplementsEditor.fetchJson('catalogo_categorias_api.php?acao=criar',{method:'POST',body,credentials:'same-origin'});
            if(!r.ok||!j.ok)throw new Error(j.error||'Erro ao criar categoria.');
            categoriasDisponiveis=j.categorias||[];categoriaSelecionada=Number(j.categoria_id);renderCategoriasProduto();
            document.getElementById('pNovaCategoriaWrap').hidden=true;document.getElementById('pNovaCategoria').value='';toast('Categoria disponível no cadastro.');
        }catch(error){toast(error.message,'err');}finally{button.disabled=false;}
    }
});
document.addEventListener('change',e=>{if(e.target.id==='pCategoria'){categoriaSelecionada=e.target.value?Number(e.target.value):null;buildReview()}});
document.addEventListener('input',e=>{if(e.target.id==='pCategoriaSearch')renderCategoriasProduto();});
// Contrato de sugestão visual: não controla permissões, estoque nem persistência.
const BUSINESS_CONTEXT = <?= json_encode([
    'segmento' => $businessPreset['segmento'] ?? null,
    'familia' => $businessPreset['familia'] ?? null,
    'modo_sugerido' => $businessPreset['modo_sugerido'] ?? 'padrao',
    'categorias_sugeridas' => $businessPreset['categorias_sugeridas'] ?? [],
    'prioridades_cadastro' => $businessPreset['prioridades_cadastro'] ?? [],
    'perfil_concluido' => ($businessProfile['status_configuracao'] ?? '') === 'concluida',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
const COMMERCIAL_AVAILABILITY_SCHEMA_READY = <?= $commercialAvailabilityReady ? 'true' : 'false' ?>;
const $ = id => document.getElementById(id);
function applyBusinessContext({expand=false}={}){
    const food=operationProfile==='alimentacao',retail=operationProfile==='varejo';
    $('modal').dataset.businessFamily=operationProfile;
    document.querySelectorAll('[data-operation]').forEach(button=>button.setAttribute('aria-pressed',String(button.dataset.operation===operationProfile)));
    $('businessContextHint').textContent=food?'Categoria, descrição e adicionais em destaque. Só altera a apresentação deste cadastro.':retail?'SKU, estoque e tamanhos em destaque. Só altera a apresentação deste cadastro.':'Organização geral. Só altera a apresentação deste cadastro.';
    $('productDescriptionLabel').textContent=food?'Descrição do cardápio e ingredientes':'Descrição comercial';
    $('pNome').placeholder=food?'Ex.: X-bacon artesanal':retail?'Ex.: Camiseta esportiva':'Ex.: Nome do produto';
    $('pDesc').placeholder=food?'Ingredientes, preparo e o que acompanha o produto.':'Material, acabamento e detalhes que ajudam o cliente a escolher.';
    $('sizesSummaryLabel').textContent=food?'Tamanhos, porções e quantidades':'Tamanhos e quantidades';
    $('sizesChoiceLabel').textContent=food?'Possui tamanhos ou porções?':'Este produto possui tamanhos?';
    if(expand){$('productSizesDetails').open=retail||sizeDraft.length>0;$('productCustomizationDetails').open=food||customizationEnabled;}
}
const esc = v => String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const R = v => 'R$\u00a0'+Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
function generateProductSku(){
    const bytes=new Uint8Array(6);
    if(window.crypto?.getRandomValues)window.crypto.getRandomValues(bytes);
    else for(let i=0;i<bytes.length;i++)bytes[i]=Math.floor(Math.random()*256);
    const suffix=Array.from(bytes,b=>b.toString(16).padStart(2,'0')).join('').toUpperCase();
    return (operationProfile==='alimentacao'?'ALIM':'PROD')+'-'+suffix;
}
$('genSku').addEventListener('click',()=>{
    $('pCod').value=generateProductSku();clearFieldValidation($('pCod'));
    wizardFieldsDirty=true;
    buildReview();
});
function diasParaVencer(dataStr) {
    if (!dataStr) return null;
    const hoje = new Date(); hoje.setHours(0,0,0,0);
    const val  = new Date(dataStr + 'T00:00:00');
    return Math.round((val - hoje) / 86400000);
}

function valStatus(dias) {
    if (dias === null)     return null;
    if (dias < 0)          return 'venc';
    if (dias <= 7)         return 'crit';
    if (dias <= 30)        return 'alrt';
    if (dias <= 90)        return 'aten';
    return 'ok';
}

function valLabel(dias) {
    if (dias === null)  return '';
    if (dias < 0)       return `Vencido há ${Math.abs(dias)} dias`;
    if (dias === 0)     return 'Vence HOJE!';
    if (dias === 1)     return 'Vence amanhã!';
    if (dias <= 7)      return `Vence em ${dias} dias`;
    if (dias <= 30)     return `Vence em ${dias} dias`;
    if (dias <= 90)     return `Vence em ${dias} dias`;
    return `Válido por ${dias} dias`;
}

function valIcon(st) {
    return {venc:'block',crit:'warning',alrt:'schedule',aten:'info',ok:'check_circle'}[st]||'event';
}

function fmtDate(dateStr) {
    if (!dateStr) return '—';
    const [y,m,d] = dateStr.split('-');
    return `${d}/${m}/${y}`;
}

function toast(msg, type='ok', dur=3200){
    const wrap=$('toastWrap'), el=document.createElement('div');
    msg = (msg === null || msg === undefined || String(msg).trim() === '' || String(msg).trim().toLowerCase() === 'null')
        ? (type === 'err' ? 'Não foi possível concluir a operação.' : 'Operação concluída.')
        : String(msg);
    el.className='toast '+(type==='err'?'err':type==='warn'?'':'ok');
    const ico=type==='err'?'error':type==='warn'?'warning':'check_circle';
    el.innerHTML=`<span class="material-symbols-outlined">${ico}</span>${esc(msg)}`;
    wrap.querySelectorAll('.toast').forEach(old=>old.classList.add('is-older'));wrap.appendChild(el);
    setTimeout(()=>{el.style.animation='toastOut .3s forwards';setTimeout(()=>{el.remove();const newest=wrap.querySelector('.toast:last-child');if(newest)newest.classList.remove('is-older')},300)},dur);
}

const countFrames=new WeakMap();
function cnt(el,to,fmt,duration=550){
    const previous=countFrames.get(el);if(previous)cancelAnimationFrame(previous);const from=Number(el.dataset.countValue)||0,start=performance.now();
    if(matchMedia('(prefers-reduced-motion: reduce)').matches){el.textContent=fmt(to);el.dataset.countValue=String(to);return}
    const tick=now=>{const p=Math.min((now-start)/duration,1),e=1-Math.pow(1-p,3),v=from+(to-from)*e;el.textContent=fmt(v);el.dataset.countValue=String(v);if(p<1)countFrames.set(el,requestAnimationFrame(tick));else{el.textContent=fmt(to);el.dataset.countValue=String(to);countFrames.delete(el)}};countFrames.set(el,requestAnimationFrame(tick));
}

const sb=$('sb'),ov=$('ov');
$('ham').addEventListener('click',()=>{sb.classList.add('open');ov.classList.add('on');document.body.style.overflow='hidden'});
ov.addEventListener('click',()=>{sb.classList.remove('open');ov.classList.remove('on');document.body.style.overflow=''});
sb.querySelectorAll('.sb-link').forEach(l=>l.addEventListener('click',()=>{if(window.innerWidth<=860){sb.classList.remove('open');ov.classList.remove('on');document.body.style.overflow=''}}));

const trigCfg=$('trigCfg'),cfgClose=$('cfgClose'),cfg=$('cfg'),btnWa=$('btnWa');
if(trigCfg)trigCfg.addEventListener('click',()=>openSidebarProfile());
if(cfgClose)cfgClose.addEventListener('click',()=>cfg.style.display='none');
if(cfg)cfg.addEventListener('click',e=>{if(e.target===cfg)cfg.style.display='none'});
if(btnWa)btnWa.addEventListener('click',async()=>{
    const btn=$('btnWa'),n=$('waNum').value.replace(/\D/g,'');
    if(!n||n.length<10)return toast('Número inválido','err');
    btn.disabled=true;btn.innerHTML='<span class="material-symbols-outlined">hourglass_empty</span>Salvando…';
    const fd=new FormData();fd.append('whatsapp_number',n);fd.append('csrf_token',CSRF);
    const r=await fetch('api.php?acao=save_whatsapp',{method:'POST',body:fd,credentials:'same-origin'});
    const j=await r.json();
    j.ok?(toast('Número salvo!'),$('cfg').style.display='none'):toast(j.error||'Erro ao salvar','err');
    btn.disabled=false;btn.innerHTML='<span class="material-symbols-outlined">save</span>Salvar número';
});

let viewMode='grid';
$('vGrid').addEventListener('click',()=>setView('grid'));
$('vList').addEventListener('click',()=>setView('list'));
function setView(v){
    viewMode=v;
    $('vGrid').classList.toggle('on',v==='grid');
    $('vList').classList.toggle('on',v==='list');
    $('pg').style.display=v==='grid'?'grid':'none';
    $('tbl').style.display=v==='list'?'block':'none';
    if(v==='list')renderTable();
}

let activeFilter='all';
document.querySelectorAll('.filter-btn').forEach(b=>{
    b.addEventListener('click',()=>{
        document.querySelectorAll('.filter-btn').forEach(x=>x.classList.remove('on'));
        b.classList.add('on');
        activeFilter=b.dataset.f;
        refreshCatalog();
    });
});
$('sortSel').addEventListener('change',()=>refreshCatalog());

let lastProfitState=null;
function updatePreview(){
    const f=productFinancials(),hasValues=f.hasCost&&f.sale>0,state=hasValues?(f.profit>=0?'profit':'loss'):null,m=$('ppMargin');
    if(hasValues){cnt($('ppVal'),f.profit,R,210);cnt(m,f.margin,value=>value.toFixed(2).replace('.',',')+'%',210);}
    else{[$('ppVal'),m].forEach(node=>{const frame=countFrames.get(node);if(frame)cancelAnimationFrame(frame);node.textContent='—';node.dataset.countValue='0';});}
    $('ppMarkup').textContent=!f.hasCost?'Informe o custo para calcular':f.markup===null?'Markup não se aplica ao custo zero':`Markup: ${f.markup.toFixed(2).replace('.',',')}% sobre o custo`;
    m.classList.toggle('is-profit',state==='profit');m.classList.toggle('is-loss',state==='loss');
    if(lastProfitState&&state&&state!==lastProfitState){m.classList.remove('cross-profit','cross-loss');void m.offsetWidth;m.classList.add(state==='profit'?'cross-profit':'cross-loss');}lastProfitState=state;buildReview();
}

$('qsMinus').addEventListener('click',()=>{const i=$('pQtd'),v=Math.max(0,parseInt(i.value||0)-1);i.value=v;buildReview()});
$('qsPlus').addEventListener('click',()=>{const i=$('pQtd'),v=parseInt(i.value||0)+1;i.value=v;buildReview()});

let temValidade = false;

function toggleValidade(){
    temValidade = !temValidade;
    const check = $('valCheck');
    const section = $('valDateSection');
    const noWrap = $('valNenhumaTxt');
    const wrap = $('valToggleWrap');

    check.classList.toggle('on', temValidade);
    wrap.classList.toggle('active', temValidade);
    wrap.setAttribute('aria-pressed',String(temValidade));
    section.classList.toggle('on', temValidade);
    noWrap.style.display = temValidade ? 'none' : 'block';

    if (!temValidade) {
        $('pValidade').value = '';
        $('valAlertInfo').innerHTML = '';clearFieldValidation($('pValidade'));
    }
    if($('modal')?.style?.display==='flex')buildReview();
}

function updateValAlert(){
    const d = $('pValidade').value;
    const info = $('valAlertInfo');
    if (!d) { info.innerHTML=''; if($('modal').style.display==='flex')buildReview(); return; }
    const dias = diasParaVencer(d);
    const st   = valStatus(dias);
    const lbl  = valLabel(dias);
    const ico  = valIcon(st);
    info.innerHTML = `
        <div style="display:flex;align-items:center;gap:.5rem;padding:.65rem .9rem;
            border-radius:10px;background:var(--bg);border:1px solid var(--border)">
            <span class="val-chip ${st}">
                <span class="material-symbols-outlined">${ico}</span>${lbl}
            </span>
            <span style="font-size:.73rem;color:var(--muted)">${fmtDate(d)}</span>
        </div>`;
    if($('modal').style.display==='flex')buildReview();
}

const dz=$('dz');
dz.addEventListener('click',event=>{if(event.target.closest('.prev-rm'))return;$('fImg').click();});
dz.addEventListener('keydown',event=>{if(event.target===dz&&(event.key==='Enter'||event.key===' ')){event.preventDefault();$('fImg').click();}});
$('fImg').addEventListener('change',event=>{if(event.target.files[0])showPrev(event.target.files[0]);});
['dragover','dragenter'].forEach(name=>dz.addEventListener(name,event=>{event.preventDefault();dz.classList.add('on');}));
['dragleave','drop'].forEach(name=>dz.addEventListener(name,event=>{event.preventDefault();dz.classList.remove('on');}));
dz.addEventListener('drop',event=>{event.preventDefault();const file=event.dataTransfer.files[0];if(file)showPrev(file);});
$('rmPrev').addEventListener('click',event=>{
    event.stopPropagation();const preview=$('prevW'),version=++imageReadVersion;draftImageFile=null;$('fImg').value='';$('prevI').removeAttribute('src');imageRemovalRequested=Boolean(productImageOriginal);
    preview.classList.remove('is-visible');preview.classList.add('is-removing');$('imageActionHint').textContent=imageRemovalRequested?'A foto atual será removida ao salvar.':'Nenhuma foto selecionada.';buildReview();
    setTimeout(()=>{if(version!==imageReadVersion)return;preview.style.display='none';preview.classList.remove('is-removing');$('dz').classList.remove('has-preview');},220);
});
function showPrev(file){
    const allowed=/\.(png|jpe?g|webp)$/i.test(file?.name||'')&&['image/png','image/jpeg','image/webp'].includes(file?.type);
    if(!allowed||file.size>5*1024*1024){if(draftImageFile){const previous=new DataTransfer();previous.items.add(draftImageFile);$('fImg').files=previous.files;}else $('fImg').value='';toast(allowed?'Imagem muito grande (máx. 5 MB).':'Selecione uma imagem PNG, JPG ou WEBP.','err');return;}
    const transfer=new DataTransfer();transfer.items.add(file);$('fImg').files=transfer.files;
    const version=++imageReadVersion,reader=new FileReader();
    reader.onload=event=>{if(version!==imageReadVersion)return;draftImageFile=file;imageRemovalRequested=false;$('prevI').src=event.target.result;const preview=$('prevW');preview.classList.remove('is-removing');preview.style.display='block';$('dz').classList.add('has-preview');requestAnimationFrame(()=>preview.classList.add('is-visible'));$('imageActionHint').textContent=productImageOriginal?'A nova foto substituirá a atual ao salvar.':'A foto será enviada ao salvar.';buildReview();};
    reader.onerror=()=>{if(version!==imageReadVersion)return;$('fImg').value='';toast('Não foi possível ler esta imagem. Tente novamente.','err');};reader.readAsDataURL(file);
}

let editId=null,productCreateKey=null;
let sizeDraft=[],stockBeforeSizeChoice=0,complementsEditor=null;
let customizationEnabled=false,customizationChoiceExplicit=false,customizationDisableConfirmed=false;
let availabilityMode='estoque',availabilityManual=true,productImageOriginal='',imageRemovalRequested=false;
let wizardBaseline='',skipWizardLeaveWarning=false,wizardFieldsDirty=false,productSaving=false;
let editorReturnFocus=null,previewSizesKey='',previewComplementsKey='',imageReadVersion=0,draftImageFile=null;
let operationProfile=['varejo','alimentacao'].includes(BUSINESS_CONTEXT.familia)?BUSINESS_CONTEXT.familia:'generico';
function setProductEditorTitle(text){
    const heading=$('stepTitle');
    if(!heading)return;
    const status=$('saveBackground');
    if(status&&status.parentNode===heading){
        const label=Array.from(heading.childNodes).find(node=>node.nodeType===3);
        if(label){label.nodeValue=`${text} `;return;}
        heading.insertBefore(document.createTextNode(`${text} `),status);
        return;
    }
    heading.textContent=text;
}
function setCustomizationEnabled(enabled,{explicit=false}={}){
    customizationEnabled=Boolean(enabled);
    if(explicit)customizationChoiceExplicit=true;
    $('customizationYes').checked=customizationEnabled;
    $('customizationNo').checked=!customizationEnabled;
    document.querySelector('label[for="customizationYes"]').classList.toggle('on',customizationEnabled);
    document.querySelector('label[for="customizationNo"]').classList.toggle('on',!customizationEnabled);
    $('s5').hidden=!customizationEnabled;
    if(customizationEnabled)$('productCustomizationDetails').open=true;
    if($('modal').style.display==='flex')buildReview();
    buildReview();
}
function removeProductComplementsAfterConfirmation(){
    if(!complementsEditor?.groups?.length&&!complementsEditor?.edit)return true;
    if(!confirm('Desativar a personalização desvincula estes grupos somente deste produto. Os grupos e os pedidos antigos serão preservados. Continuar?'))return false;
    complementsEditor.groups=[];
    complementsEditor.dirty=true;
    complementsEditor.edit=null;
    complementsEditor.render();
    customizationDisableConfirmed=true;
    return true;
}
function chooseCustomizationNo(){
    if(!removeProductComplementsAfterConfirmation()){
        setCustomizationEnabled(true);
        return;
    }
    setCustomizationEnabled(false,{explicit:true});
}
function updateComplementsNavigation(){
    const blocked=productSaving||Boolean(complementsEditor?.edit);
    $('btnNext').disabled=blocked;$('btnSaveAnother').disabled=blocked;
    updateEditorValidation();buildReview();
}
$('productComplements').addEventListener('complementsstate',updateComplementsNavigation);
$('productComplements').addEventListener('complementsready',()=>{
    if(!complementsEditor?.ready)return;
    if(editId&&complementsEditor.groups.length){
        if(!customizationChoiceExplicit){
            setCustomizationEnabled(true);
            if(!wizardFieldsDirty)wizardBaseline=wizardDraftSnapshot();
        }
        else if(!customizationEnabled&&!customizationDisableConfirmed){
            if(!removeProductComplementsAfterConfirmation())setCustomizationEnabled(true);
        }
    }
});
$('customizationYes').addEventListener('change',()=>{if($('customizationYes').checked)setCustomizationEnabled(true,{explicit:true});});
$('customizationNo').addEventListener('change',()=>{if($('customizationNo').checked)chooseCustomizationNo();});
$('modal').addEventListener('input',()=>{wizardFieldsDirty=true;});
$('modal').addEventListener('change',()=>{wizardFieldsDirty=true;});
$('modal').addEventListener('input',()=>buildReview());
$('modal').addEventListener('change',()=>buildReview());
const COMMON_SIZES=['PP','P','M','G','GG','XG','XXG'];

/* CORRIGIDO: "enabled" agora é definido localmente e o editor
   não derruba a execução do openM ao clicar em Editar. */
function renderSizeEditor(){
    const enabled = $('sizeYes').classList.contains('on');

    // Em produtos com tamanhos o agregado é apenas leitura: nenhuma unidade
    // deve ser atribuída automaticamente à primeira variação.
    $('pQtd').disabled=false;
    $('pQtd').readOnly=enabled;
    $('pQtd').setAttribute('aria-readonly',String(enabled));
    $('qsMinus').disabled=enabled;
    $('qsPlus').disabled=enabled;
    $('stockQuantityField').hidden=availabilityMode==='sob_demanda'&&!enabled;
    $('stockQuantityLabel').textContent=enabled?'Estoque total dos tamanhos':(editId?'Quantidade em estoque':'Quantidade inicial em estoque');
    $('stockQuantityHint').textContent=enabled
        ?(availabilityMode==='sob_demanda'?'Os saldos físicos por tamanho ficam preservados, mas não limitam as vendas sob demanda.':'Total calculado pelas quantidades de cada tamanho. Edite os tamanhos abaixo para ajustar.')
        :'Informe a quantidade disponível deste produto.';

    $('sizeEditor').hidden=!enabled;

    $('sizeQuick').innerHTML=COMMON_SIZES.map(s=>`
        <button type="button" class="pill pill-outline ${sizeDraft.some(x=>x.tamanho===s)?'on':''}" aria-pressed="${sizeDraft.some(x=>x.tamanho===s)}" onclick="toggleQuickSize('${s}')">${s}</button>
    `).join('');

    $('sizeRows').innerHTML=sizeDraft.map((s,i)=>`
        <div style="display:flex;gap:.5rem;align-items:center;margin:.35rem 0">
            <strong style="width:45px">${esc(s.tamanho)}</strong>
            <input type="number"
                   min="0"
                   value="${Number(s.quantidade)||0}"
                   oninput="sizeDraft[${i}].quantidade=Math.max(0,Number(this.value)||0);renderSizeTotal()"
                   aria-label="Quantidade do tamanho ${esc(s.tamanho)}" style="max-width:110px">
            <button type="button"
                    class="tbl-btn del" aria-label="Remover tamanho ${esc(s.tamanho)}"
                    onclick="sizeDraft.splice(${i},1);renderSizeEditor()">×</button>
        </div>
    `).join('');

    renderSizeTotal();
}

function renderSizeTotal(){
    const total=sizeDraft.reduce((n,s)=>n+Math.max(0,Number(s.quantidade)||0),0);
    $('sizeTotal').textContent=`Estoque total: ${total} unidades`;
    // Ao abrir/editar um produto comum, preservar a quantidade existente.
    // O agregado só deve refletir os tamanhos quando o controle estiver ativo.
    if($('sizeYes').classList.contains('on'))$('pQtd').value=total;clearFieldValidation($('sizeCustom'));clearFieldValidation($('pQtd'));
    if($('modal')?.style?.display==='flex')buildReview();
}


function addSize(name){
    name=String(name||'').trim().toUpperCase();if(!name||sizeDraft.some(s=>s.tamanho===name))return;sizeDraft.push({tamanho:name,quantidade:0});$('sizeYes').classList.add('on');$('sizeNo').classList.remove('on');renderSizeEditor();const chip=[...$('sizeQuick').querySelectorAll('button')].find(b=>b.textContent===name);if(chip){chip.classList.remove('just-selected');void chip.offsetWidth;chip.classList.add('just-selected')}
}
function toggleQuickSize(name){name=String(name||'').trim().toUpperCase();if(sizeDraft.some(s=>s.tamanho===name)){sizeDraft=sizeDraft.filter(s=>s.tamanho!==name);renderSizeEditor();return}addSize(name)}

function suggestedSizes(description){
    const m=String(description||'').match(/dispon[ií]ve(?:l|is)(?:\s+somente)?\s+(?:no\s+)?(?:tamanho\s*:?)?\s*([A-Z0-9]+(?:\s*(?:,|\/|e)\s*[A-Z0-9]+)*)/i);
    if(!m)return[];
    return [...new Set(m[1].toUpperCase().split(/\s*(?:,|\/|E)\s*/).map(s=>s.trim()).filter(s=>/^(PP|P|M|G|GG|XG|XXG|\d{2}|ÚNICO|UNICO|\dG)$/.test(s)))];
}

function showSizeSuggestion(p){
    const sizes=!productUsesSizes(p)?suggestedSizes(p?.descricao):[],box=$('sizeSuggestion');
    if(!sizes.length){box.hidden=true;return;}
    box.hidden=false;
    box.innerHTML=`Encontramos tamanhos na descrição: <strong>${sizes.map(esc).join(' · ')}</strong><br><button type="button" class="pill pill-outline" style="margin-top:.45rem" onclick="sizeDraft=[];sizesSuggestionUse(${JSON.stringify(sizes).replace(/&/g,'&amp;')})">Usar estes tamanhos</button>`;
}

function sizesSuggestionUse(sizes){
    sizes.forEach(addSize);
    $('sizeSuggestion').hidden=true;
    toast('Informe as quantidades de cada tamanho antes de salvar.','warn');
}

$('sizeYes').addEventListener('click',()=>{
    const original=editId?estoque.find(p=>Number(p.id)===Number(editId)):null;
    if(original&&!productUsesSizes(original)&&Number(original.quantidade)>0){
        toast('Este produto já tem estoque. Use a opção Configurar tamanhos no menu do produto para distribuir as unidades sem perda.','warn',5500);
        return;
    }
    if(!$('sizeYes').classList.contains('on'))stockBeforeSizeChoice=Math.max(0,Number($('pQtd').value)||0);
    $('sizeYes').classList.add('on');
    $('sizeNo').classList.remove('on');
    renderSizeEditor();
    buildReview();
});

$('sizeNo').addEventListener('click',()=>{
    const original=editId?estoque.find(p=>Number(p.id)===Number(editId)):null;
    if(original&&productUsesSizes(original)){
        toast('O produto já utiliza tamanhos. Ajuste as quantidades por tamanho; remover o controle requer conversão específica.','warn',5500);
        return;
    }
    const wasEnabled=$('sizeYes').classList.contains('on');
    sizeDraft=[];
    $('sizeNo').classList.add('on');
    $('sizeYes').classList.remove('on');
    renderSizeEditor();
    if(wasEnabled)$('pQtd').value=stockBeforeSizeChoice;
    buildReview();
});

$('sizeCustom').addEventListener('click',()=>{
    const value=prompt('Informe o tamanho (ex.: 36, Único ou 3G)');
    if(value)addSize(value);
});

function setWizardButton(html){$('btnNext').innerHTML=html;}
function gotoStep(position){
    const target=position===5?'productCustomizationDetails':position===2?'stockSection':'s1';
    if(target==='productCustomizationDetails')$(target).open=true;
    $(target).scrollIntoView({block:'start',behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});
}
function productValidationIssues(){
    const issues=[],price=Number($('pPreco').value),cost=$('pCusto').value;
    if(!$('pNome').value.trim())issues.push({id:'pNome',message:'Informe o nome do produto.'});
    if(!$('pPreco').value||!Number.isFinite(price)||price<=0||!$('pPreco').validity.valid)issues.push({id:'pPreco',message:'Informe um preço de venda válido maior que zero.'});
    if(cost!==''&&(!Number.isFinite(Number(cost))||Number(cost)<0||!$('pCusto').validity.valid))issues.push({id:'pCusto',message:'Informe um custo válido, igual ou maior que zero.'});
    if(availabilityMode==='estoque'&&!$('sizeYes').classList.contains('on')&&(!Number.isInteger(Number($('pQtd').value))||Number($('pQtd').value)<0||$('pQtd').value===''))issues.push({id:'pQtd',message:'Informe uma quantidade inteira igual ou maior que zero.'});
    if($('sizeYes').classList.contains('on')&&!sizeDraft.length)issues.push({id:'sizeCustom',message:'Selecione pelo menos um tamanho.'});
    if(sizeDraft.some(size=>!Number.isInteger(Number(size.quantidade))||Number(size.quantidade)<0))issues.push({id:'sizeCustom',message:'Informe quantidades inteiras iguais ou maiores que zero por tamanho.'});
    if(temValidade&&(!$('pValidade').value||!$('pValidade').validity.valid))issues.push({id:'pValidade',message:'Informe a data de validade.'});
    return issues;
}
function updateEditorValidation(){
    const status=$('editorValidation'),issues=productValidationIssues(),loading=Boolean(editId&&!complementsEditor?.ready),editing=Boolean(complementsEditor?.edit);
    const groupsInvalid=customizationEnabled&&complementsEditor?.ready&&complementsEditor.groups.some(group=>ProductComplementsEditor.problem(group));
    const ready=!issues.length&&!editing&&!groupsInvalid&&(!customizationEnabled||complementsEditor?.ready)&&!loading;
    status.classList.toggle('ready',ready&&!productSaving);status.classList.toggle('invalid',!ready&&Boolean(document.querySelector('#productEditorFields [aria-invalid="true"]')));
    $('stepLbl').textContent=productSaving?'Salvando produto e configurações…':loading?'Carregando configurações do produto…':editing?'Conclua ou cancele a edição do complemento.':groupsInvalid?'Revise as regras dos complementos.':ready?'Dados obrigatórios preenchidos.':issues.length?issues[0].message:'Aguarde o carregamento dos complementos.';
}
function markFieldInvalid(id,message,{focus=true}={}){
    const field=$(id),wrap=field?.closest('.fl');if(!field||!wrap)return;
    let parent=field.parentElement;while(parent&&parent!==$('modal')){if(parent.tagName==='DETAILS')parent.open=true;parent=parent.parentElement;}
    wrap.classList.remove('has-valid');wrap.classList.add('has-error');field.setAttribute('aria-invalid','true');
    let error=wrap.querySelector('.field-error');if(!error){error=document.createElement('span');error.className='field-error';error.id=`${id}Error`;error.setAttribute('role','alert');wrap.appendChild(error)}error.textContent=message;field.setAttribute('aria-describedby',error.id);
    if(focus){field.focus({preventScroll:true});field.scrollIntoView({block:'center'});}setTimeout(()=>wrap.classList.remove('has-error'),380);updateEditorValidation();
}
function clearFieldValidation(field){
    const wrap=field.closest('.fl');if(!wrap||field.getAttribute('aria-invalid')!=='true'||productValidationIssues().some(issue=>issue.id===field.id))return;
    wrap.classList.remove('has-error');field.removeAttribute('aria-invalid');field.removeAttribute('aria-describedby');wrap.querySelector('.field-error')?.remove();wrap.classList.add('has-valid');
    setTimeout(()=>wrap.classList.remove('has-valid'),600);
}
$('modal').addEventListener('input',event=>{if(event.target.matches('input,textarea,select'))clearFieldValidation(event.target);updateEditorValidation();});
function validateStep(stepId){
    if(stepId===1||stepId===2){
        const issues=productValidationIssues().filter(issue=>stepId===1?issue.id==='pNome':issue.id!=='pNome');
        if(issues.length){issues.forEach((issue,index)=>markFieldInvalid(issue.id,issue.message,{focus:index===0}));return false;}
        if(stepId===1&&!$('pCod').value.trim()){$('pCod').value=generateProductSku();buildReview();}
    }
    if(stepId===5&&customizationEnabled&&(!complementsEditor||!complementsEditor.validate())){$('productCustomizationDetails').open=true;showEditorNotice(complementsEditor?.ready?'Conclua a edição e revise as regras dos complementos.':'Aguarde o carregamento dos complementos.');return false;}
    return true;
}
function showEditorNotice(message){const notice=$('editorSaveNotice');notice.textContent=message;notice.hidden=false;}

function createProductCreationKey(){
    const bytes=new Uint8Array(16);
    if(window.crypto?.getRandomValues)window.crypto.getRandomValues(bytes);
    else for(let i=0;i<bytes.length;i++)bytes[i]=Math.floor(Math.random()*256);
    return Array.from(bytes,value=>value.toString(16).padStart(2,'0')).join('');
}

function setAvailabilityMode(mode){
    availabilityMode=mode==='sob_demanda'?'sob_demanda':'estoque';
    $('availabilityStock').classList.toggle('on',availabilityMode==='estoque');
    $('availabilityDemand').classList.toggle('on',availabilityMode==='sob_demanda');
    $('availabilityStock').setAttribute('aria-pressed',String(availabilityMode==='estoque'));$('availabilityDemand').setAttribute('aria-pressed',String(availabilityMode==='sob_demanda'));
    const demand=availabilityMode==='sob_demanda';
    $('stockQuantityField').hidden=demand&&!$('sizeYes').classList.contains('on');
    $('availabilityModeHint').textContent=demand
        ?'O produto é preparado ou disponibilizado após o pedido. Não reduz saldo físico; tamanhos continuam sendo escolhas válidas.'
        :'A venda depende do saldo físico informado.';
    renderSizeEditor();
    wizardFieldsDirty=true;
}

$('availabilityStock').addEventListener('click',()=>setAvailabilityMode('estoque'));
$('availabilityDemand').addEventListener('click',()=>setAvailabilityMode('sob_demanda'));
$('availabilityManual').addEventListener('change',()=>{availabilityManual=$('availabilityManual').checked;wizardFieldsDirty=true;});

function productFinancials(){
    const sale=Number($('pPreco').value)||0,cost=Number($('pCusto').value)||0,hasCost=$('pCusto').value.trim()!==''&&Number.isFinite(Number($('pCusto').value))&&cost>=0;
    return {sale,cost,hasCost,profit:sale-cost,margin:sale>0?(sale-cost)/sale*100:null,markup:cost>0?(sale-cost)/cost*100:null};
}
function buildReview(){
    const set=(id,value)=>{const node=$(id);if(node&&node.textContent!==value)node.textContent=value;},f=productFinancials();
    set('liveName',$('pNome').value.trim()||'Nome do produto');set('liveDescription',$('pDesc').value.trim()||'A descrição comercial aparecerá aqui.');
    set('liveSku',$('pCod').value.trim()?'SKU: '+$('pCod').value.trim():'SKU automático ao salvar');
    set('liveCategory',categoriasDisponiveis.find(category=>Number(category.id)===Number(categoriaSelecionada))?.nome||'Sem categoria');
    const image=$('prevW').style.display!=='none'&&$('prevI').getAttribute('src')&&!imageRemovalRequested?$('prevI').getAttribute('src'):'';
    $('liveImage').hidden=!image;$('liveImagePlaceholder').hidden=Boolean(image);if(image&&$('liveImage').getAttribute('src')!==image)$('liveImage').src=image;if(!image)$('liveImage').removeAttribute('src');
    set('livePrice',f.sale>0?R(f.sale):'R$ —');
    const sizesEnabled=$('sizeYes').classList.contains('on'),total=sizesEnabled?sizeDraft.reduce((sum,size)=>sum+(Number(size.quantidade)||0),0):Number($('pQtd').value)||0,demand=availabilityMode==='sob_demanda';
    const available=availabilityManual&&(demand||total>0);set('liveAvailability',!availabilityManual?'Venda pausada':demand?'Sob demanda':total>0?`Em estoque: ${total}`:'Sem estoque');$('liveAvailability').classList.toggle('unavailable',!available);
    const sizesKey=JSON.stringify([sizesEnabled,demand,sizeDraft]);$('liveSizes').hidden=!sizesEnabled||!sizeDraft.length;
    if(sizesKey!==previewSizesKey){previewSizesKey=sizesKey;$('liveSizes').replaceChildren(...(sizesEnabled?sizeDraft:[]).map(size=>{const chip=document.createElement('span'),empty=!demand&&Number(size.quantidade)<=0;chip.className='live-size'+(empty?' empty':'');chip.textContent=String(size.tamanho)+(demand?'':` · ${Number(size.quantidade)||0}`);chip.title=demand?'Disponível sob demanda':empty?'Sem estoque':`${size.quantidade} unidades`;return chip}));}
    const groups=customizationEnabled?(complementsEditor?.groups||[]).filter(group=>group.ativo):[];
    const groupsKey=JSON.stringify(groups.map(group=>[group.nome,group.opcoes?.length]));if(groupsKey!==previewComplementsKey){previewComplementsKey=groupsKey;set('liveComplements',groups.length?groups.map(group=>group.nome).join(' · '):'');}$('liveComplements').hidden=!groups.length;
    const valid=temValidade&&$('pValidade').value;set('liveValidity',valid?'Validade: '+fmtDate(valid):'');$('liveValidity').hidden=!valid;
    const percent=value=>value===null?'—':value.toFixed(2).replace('.',',')+'%';
    set('financeSale',f.sale>0?R(f.sale):'—');set('financeCost',f.hasCost?R(f.cost):'Não informado');set('financeProfit',f.hasCost&&f.sale>0?R(f.profit):'—');set('financeMargin',f.hasCost?percent(f.margin):'—');set('financeMarkup',f.hasCost?percent(f.markup):'—');
    $('financeProfit').classList.toggle('profit-positive',f.hasCost&&f.profit>=0);$('financeProfit').classList.toggle('profit-negative',f.hasCost&&f.profit<0);updateEditorValidation();
}

$('btnNovo').addEventListener('click',()=>openM());
function wizardDraftSnapshot(){
    return JSON.stringify({
        nome:$('pNome').value,codigo:$('pCod').value,descricao:$('pDesc').value,categoria:categoriaSelecionada,preco:$('pPreco').value,custo:$('pCusto').value,
        quantidade:$('pQtd').value,tamanhos:sizeDraft,validade:temValidade?$('pValidade').value:'',temValidade,
        personalizacao:customizationEnabled,disponibilidade:availabilityMode,disponivel:availabilityManual,imagem:$('fImg').files.length?$('fImg').files[0].name:$('prevI').src,removerImagem:imageRemovalRequested
    });
}
function wizardHasUnsavedChanges(){
    return Boolean(complementsEditor?.dirty||complementsEditor?.edit||wizardDraftSnapshot()!==wizardBaseline);
}
function closeProductModal(){
    if(productSaving)return false;
    if(!skipWizardLeaveWarning&&wizardHasUnsavedChanges()&&!confirm('Descartar as alterações deste produto?'))return false;
    $('modal').style.display='none';document.body.style.overflow=editorBodyOverflow;editorReturnFocus?.focus();return true;
}
function clearProductValidation(){
    ['pNome','pCod','pPreco','pCusto','pQtd','pValidade','sizeCustom'].forEach(id=>{
        const field=$(id),wrap=field?.closest('.fl');
        if(!field||!wrap)return;
        field.removeAttribute('aria-invalid');field.removeAttribute('aria-describedby');
        wrap.classList.remove('has-error','has-valid');
        wrap.querySelector('.field-error')?.remove();wrap.querySelector('.field-confirm')?.remove();
    });
}
$('mclose').addEventListener('click',closeProductModal);
$('modal').addEventListener('click',e=>{if(e.target===$('modal'))closeProductModal();});
window.addEventListener('beforeunload',event=>{
    if($('modal').style.display==='flex'&&!skipWizardLeaveWarning&&wizardHasUnsavedChanges()){
        event.preventDefault();event.returnValue='';
    }
});

function openM(p=null,{allowDuringSave=false}={}){
    if(productSaving&&!allowDuringSave){toast('Aguarde o produto terminar de salvar.','warn');return;}
    clearProductValidation();
    editId=p?.id??null;
    productCreateKey=editId===null?createProductCreationKey():null;
    const hasStructuredSizes=productUsesSizes(p);
    availabilityMode=(p?.disponibilidade_modo??p?.modo_disponibilidade)==='sob_demanda'?'sob_demanda':'estoque';
    availabilityManual=Number(p?.disponibilidade_manual??1)!==0;
    $('availabilityManual').checked=availabilityManual;$('availabilityStock').setAttribute('aria-pressed',String(availabilityMode==='estoque'));$('availabilityDemand').setAttribute('aria-pressed',String(availabilityMode==='sob_demanda'));
    $('availabilityStock').classList.toggle('on',availabilityMode==='estoque');
    $('availabilityDemand').classList.toggle('on',availabilityMode==='sob_demanda');
    $('availabilityModeHint').textContent=availabilityMode==='sob_demanda'
        ?'O produto é preparado ou disponibilizado após o pedido. Não reduz saldo físico; tamanhos continuam sendo escolhas válidas.'
        :'A venda depende do saldo físico informado.';
    $('stockQuantityLabel').textContent=editId?'Quantidade em estoque':'Quantidade inicial em estoque';
    $('stockQuantityHint').textContent=hasStructuredSizes?'O total é calculado pelos tamanhos. Edite cada quantidade individualmente.':'Informe a quantidade disponível deste produto.';
    $('pNome').value=p?.nome??'';
    $('pCod').value=p?.codigo??'';
    $('pPreco').value=p?Number(p.preco).toFixed(2):'';
    $('pCusto').value=p?Number(p.preco_custo||0).toFixed(2):'';
    $('pQtd').value=p?.quantidade??1;
    stockBeforeSizeChoice=Math.max(0,Number($('pQtd').value)||0);
    $('pDesc').value=p?.descricao??'';
    categoriaSelecionada=p?.categoria_id?Number(p.categoria_id):null;
    if(categoriasDisponiveis.length)renderCategoriasProduto();else carregarCategoriasProduto();
    customizationEnabled=false;
    customizationChoiceExplicit=false;
    customizationDisableConfirmed=false;
    skipWizardLeaveWarning=false;
    wizardFieldsDirty=false;
    setCustomizationEnabled(false);
    complementsEditor=new ProductComplementsEditor($('productComplements'),CSRF,editId||0);
    complementsEditor.load();

    sizeDraft=hasStructuredSizes
        ? (Array.isArray(p?.tamanhos)?p.tamanhos:[])
            .filter(s=>Number(s.ativo)!==0)
            .map(s=>({tamanho:s.tamanho,quantidade:Number(s.quantidade)||0}))
        : [];

    $('sizeYes').classList.toggle('on',hasStructuredSizes);
    $('sizeNo').classList.toggle('on',!hasStructuredSizes);

    renderSizeEditor();
    showSizeSuggestion(p);

    imageReadVersion++;draftImageFile=null;$('prevW').classList.remove('is-removing');$('fImg').value='';
    productImageOriginal=p?.imagem??'';
    imageRemovalRequested=false;
    $('imageActionHint').textContent=productImageOriginal?'A foto aparece no catálogo e no painel de vendas. Você pode remover a foto existente antes de salvar.':'A foto aparece no catálogo e no painel de vendas. Opcional.';
    $('btnSaveAnother').hidden=false;$('editorSaveNotice').hidden=true;

    temValidade = !!(p?.validade);
    $('productValidityDetails').open=temValidade;
    $('productImageDetails').open=!editId||Boolean(p?.imagem)||(BUSINESS_CONTEXT.prioridades_cadastro||[]).includes('imagens');
    $('productCustomizationDetails').open=BUSINESS_CONTEXT.familia==='alimentacao'||(BUSINESS_CONTEXT.prioridades_cadastro||[]).includes('complementos');
    $('productSizesDetails').open=hasStructuredSizes||(BUSINESS_CONTEXT.prioridades_cadastro||[]).includes('tamanhos');
    $('valCheck').classList.toggle('on', temValidade);
    $('valToggleWrap').classList.toggle('active', temValidade);
    $('valToggleWrap').setAttribute('aria-pressed',String(temValidade));
    $('valDateSection').classList.toggle('on', temValidade);
    $('valNenhumaTxt').style.display = temValidade ? 'none' : 'block';
    $('pValidade').value = p?.validade ?? '';
    $('valAlertInfo').innerHTML = '';
    if(p?.validade) updateValAlert();

    if(p?.imagem){
        $('prevI').src=p.imagem;
        $('prevW').style.display='block';$('prevW').classList.add('is-visible');$('dz').classList.add('has-preview');
    }else{
        $('prevI').src='';
        $('prevW').style.display='none';$('prevW').classList.remove('is-visible','is-removing');$('dz').classList.remove('has-preview');
    }

    updatePreview();
    operationProfile=['varejo','alimentacao'].includes(BUSINESS_CONTEXT.familia)?BUSINESS_CONTEXT.familia:'generico';applyBusinessContext();
    setProductEditorTitle(editId?'Editar produto':'Novo produto');
    if($('modal').style.display!=='flex'){editorReturnFocus=document.activeElement;editorBodyOverflow=document.body.style.overflow;}document.body.style.overflow='hidden';
    $('modal').style.display='flex';$('modal').querySelector('.mstep-body').scrollTop=0;
    requestAnimationFrame(()=>$('pNome').focus({preventScroll:true}));
    buildReview();
    wizardBaseline=wizardDraftSnapshot();
}

/* Handler dedicado para edição. Evita lógica de busca espalhada pelo HTML dinâmico. */
function editProduct(id){
    closeCatalogInspectorDrawer();
    const product=estoque.find(item=>Number(item.id)===Number(id));

    if(!product){
        console.error('[Produtos] Produto não encontrado:',id);
        toast('Não foi possível localizar este produto.','err');
        return;
    }

    try{
        openM(product);
    }catch(error){
        console.error('[Produtos] Erro ao abrir edição:',error);
        toast('Não foi possível abrir a edição do produto.','err');
    }
}

function duplicateProd(id){
    const source=estoque.find(p=>Number(p.id)===Number(id));if(!source)return;
    openM({...source,id:null,codigo:'',nome:`${source.nome} (cópia)`,quantidade:0});
    setProductEditorTitle('Duplicar produto');
    toast('Revise a cópia e informe um novo SKU antes de salvar.');
}

let productActionAnchor=null;
const productUsesSizes=product=>{
    const enabled=Number(product?.usa_tamanhos)===1||product?.usa_tamanhos===true;
    return enabled&&Array.isArray(product?.tamanhos)&&product.tamanhos.some(size=>Number(size?.ativo)!==0);
};

function closeProductActions(){
    const popover=$('productActionPopover');
    if(!popover.hidden)popover.hidden=true;
    if(productActionAnchor)productActionAnchor.setAttribute('aria-expanded','false');
    productActionAnchor=null;
}

function positionProductActions(){
    const popover=$('productActionPopover');if(!productActionAnchor||popover.hidden)return;
    const rect=productActionAnchor.getBoundingClientRect(),gap=6,margin=8;
    const width=popover.offsetWidth,height=popover.offsetHeight;
    let left=Math.min(Math.max(margin,rect.right-width),window.innerWidth-width-margin);
    let top=rect.bottom+gap;
    if(top+height>window.innerHeight-margin)top=Math.max(margin,rect.top-height-gap);
    popover.style.left=`${left}px`;popover.style.top=`${top}px`;
}

function openProductActions(event,id){
    event.preventDefault();event.stopPropagation();
    closeCatalogInspectorDrawer();
    const anchor=event.currentTarget,p=estoque.find(item=>Number(item.id)===Number(id)),popover=$('productActionPopover');if(!p)return;
    if(productActionAnchor===anchor&&!popover.hidden){closeProductActions();return}
    closeProductActions();productActionAnchor=anchor;anchor.setAttribute('aria-expanded','true');
    popover.innerHTML=`<button type="button" data-action="edit">Editar</button><button type="button" data-action="complements">Gerenciar complementos</button><button type="button" data-action="stock">${productUsesSizes(p)?'Atualizar tamanhos':'Ajustar estoque'}</button>${!productUsesSizes(p)?'<button type="button" data-action="setup-sizes">Configurar tamanhos</button>':''}<button type="button" data-action="duplicate">Duplicar</button><hr><button class="danger" type="button" data-action="delete">Excluir</button>`;
    popover.querySelector('[data-action="edit"]').onclick=()=>{closeProductActions();editProduct(id)};
    popover.querySelector('[data-action="complements"]').onclick=()=>{window.location.href=`complementos.php?produto_id=${encodeURIComponent(id)}`};
    popover.querySelector('[data-action="stock"]').onclick=()=>{closeProductActions();productUsesSizes(p)?openSizeStock(id):openAggregateStockEdit(id)};
    const setupSizes=popover.querySelector('[data-action="setup-sizes"]');
    if(setupSizes)setupSizes.onclick=()=>{closeProductActions();openSizeStock(id)};
    popover.querySelector('[data-action="duplicate"]').onclick=()=>{closeProductActions();duplicateProd(id)};
    popover.querySelector('[data-action="delete"]').onclick=()=>{closeProductActions();delProd(id,p.nome||'')};
    popover.hidden=false;requestAnimationFrame(positionProductActions);
}

document.addEventListener('click',event=>{const popover=$('productActionPopover');if(!popover.hidden&&!popover.contains(event.target)&&!productActionAnchor?.contains(event.target))closeProductActions()});
document.addEventListener('keydown',event=>{if(event.key==='Escape')closeProductActions()});
window.addEventListener('resize',closeProductActions);
window.addEventListener('scroll',closeProductActions,true);

window.openM=openM;
window.editProduct=editProduct;
window.openProductActions=openProductActions;

function openAggregateStockEdit(id){
    const product=estoque.find(item=>Number(item.id)===Number(id));if(!product)return;
    openM(product);gotoStep(2);
    requestAnimationFrame(()=>{$('pQtd').focus();$('pQtd').select()});
}

let stockSizeProduct=null, stockSizeDraft=[],stockSizeConverting=false,stockSizeBaseline=0;
function sizeStockTotal(){return stockSizeDraft.reduce((sum,size)=>sum+Math.max(0,Number(size.quantidade)||0),0)}
function openSizeStock(id){
    const product=estoque.find(p=>Number(p.id)===Number(id));
    if(!product)return;
    stockSizeProduct=product;stockSizeConverting=!productUsesSizes(product);stockSizeBaseline=Math.max(0,Number(product.quantidade)||0);
    stockSizeDraft=(product.tamanhos||[]).filter(s=>Number(s.ativo)!==0).map(s=>({tamanho:s.tamanho,quantidade:Math.max(0,Number(s.quantidade)||0)}));
    $('sizeStockTitle').textContent=stockSizeConverting?'Atualizar tamanhos':'Ajustar estoque por tamanho';
    $('sizeStockName').textContent=product.nome;$('sizeStockCurrent').textContent=`${stockSizeBaseline} ${stockSizeBaseline===1?'unidade em estoque':'unidades em estoque'}`;
    $('sizeStockPicker').hidden=!stockSizeConverting;$('sizeStockCustomInput').value='';renderSizeStock();
    $('sizeStockModal').style.display='flex';$('sizeStockClose').focus();
}

function renderSizeStock(){
    $('sizeStockQuick').innerHTML=COMMON_SIZES.map(size=>`<button type="button" class="size-stock-chip ${stockSizeDraft.some(item=>item.tamanho===size)?'on':''}" onclick="toggleStockSize('${size}')">${size}${stockSizeDraft.some(item=>item.tamanho===size)?' ✓':''}</button>`).join('');
    $('sizeStockRows').innerHTML=stockSizeDraft.length?stockSizeDraft.map((size,index)=>`<div class="size-stock-row"><strong class="size-stock-label">${esc(size.tamanho)}</strong><div class="size-stock-stepper"><button type="button" onclick="changeSizeStock(${index},-1)" aria-label="Diminuir ${esc(size.tamanho)}">−</button><input type="number" min="0" step="1" inputmode="numeric" value="${size.quantidade}" oninput="inputSizeStock(${index},this)" aria-label="Quantidade ${esc(size.tamanho)}"><button type="button" onclick="changeSizeStock(${index},1)" aria-label="Aumentar ${esc(size.tamanho)}">+</button></div><button class="size-stock-remove" type="button" onclick="removeStockSize(${index})" aria-label="Remover ${esc(size.tamanho)}">×</button></div>`).join(''):'<div class="size-stock-empty">Selecione os tamanhos acima para começar.</div>';
    updateSizeStockSummary();
}

function updateSizeStockSummary(){
    const total=sizeStockTotal(),baseline=stockSizeConverting?stockSizeBaseline:total,progress=$('sizeStockProgress'),hint=$('sizeStockHint'),save=$('sizeStockSave');
    $('sizeStockDistributedLabel').textContent=stockSizeConverting?'Distribuído':'Novo estoque';
    $('sizeStockTotal').textContent=stockSizeConverting?`${total} / ${baseline} unidades`:`${total} unidades`;
    progress.style.width=`${baseline?Math.min(100,total/baseline*100):100}%`;progress.style.background=stockSizeConverting&&total>baseline?'var(--err)':total===baseline?'var(--ok)':'var(--p)';
    hint.className='size-stock-hint';
    if(stockSizeConverting){
        const difference=baseline-total;
        hint.textContent=difference===0?'✓ Todo o estoque foi distribuído.':difference>0?`Faltam distribuir ${difference} unidade(s).`:`${Math.abs(difference)} unidade(s) acima do estoque atual.`;
        hint.classList.add(difference===0?'success':difference>0?'attention':'error');save.disabled=total!==baseline||!stockSizeDraft.length;save.title=save.disabled?`Distribua todas as ${baseline} unidades para continuar.`:'';
        $('sizeStockChange').hidden=true;
    }else{
        const delta=total-stockSizeBaseline;
        hint.textContent='O estoque total é calculado automaticamente pelos tamanhos.';
        $('sizeStockChange').hidden=false;$('sizeStockOld').textContent=stockSizeBaseline;$('sizeStockNew').textContent=total;$('sizeStockDelta').textContent=`${delta>0?'+':''}${delta} unidade${Math.abs(delta)===1?'':'s'}`;save.disabled=!stockSizeDraft.length;save.title='';
    }
}

function inputSizeStock(index,input){let quantity=parseInt(input.value,10);if(!Number.isFinite(quantity)||quantity<0)quantity=0;stockSizeDraft[index].quantidade=quantity;if(String(input.value)!==String(quantity))input.value=quantity;updateSizeStockSummary();}
function changeSizeStock(index,delta){stockSizeDraft[index].quantidade=Math.max(0,(Number(stockSizeDraft[index].quantidade)||0)+delta);renderSizeStock();}
function removeStockSize(index){stockSizeDraft.splice(index,1);renderSizeStock();}
function toggleStockSize(size){const index=stockSizeDraft.findIndex(item=>item.tamanho===size);if(index>=0)stockSizeDraft.splice(index,1);else stockSizeDraft.push({tamanho:size,quantidade:0});renderSizeStock();const chip=[...$('sizeStockQuick').querySelectorAll('.size-stock-chip')].find(button=>button.textContent.trim().startsWith(size));if(chip){chip.classList.remove('just-selected');void chip.offsetWidth;chip.classList.add('just-selected')}}
function addStockSize(size){size=String(size||'').trim().toUpperCase();if(!size)return;if(stockSizeDraft.some(item=>item.tamanho===size)){toast('Este tamanho já foi incluído.','err');return}stockSizeDraft.push({tamanho:size,quantidade:0});$('sizeStockCustomInput').value='';renderSizeStock();}
function closeSizeStock(){$('sizeStockModal').style.display='none';stockSizeProduct=null;stockSizeDraft=[];stockSizeConverting=false;}
$('sizeStockClose').addEventListener('click',closeSizeStock);
$('sizeStockCancel').addEventListener('click',closeSizeStock);
$('sizeStockModal').addEventListener('click',e=>{if(e.target===$('sizeStockModal'))closeSizeStock()});
$('sizeStockCustom').addEventListener('click',()=>addStockSize($('sizeStockCustomInput').value));
$('sizeStockCustomInput').addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();addStockSize(event.currentTarget.value)}});

$('sizeStockSave').addEventListener('click',async()=>{
    if(!stockSizeProduct||!stockSizeDraft.length)return;
    const distributed=stockSizeDraft.reduce((sum,size)=>sum+(Number(size.quantidade)||0),0);
    if(stockSizeConverting&&distributed!==stockSizeBaseline){toast(`Distribua exatamente ${stockSizeBaseline} unidade(s) antes de salvar.`,'err');return;}
    const button=$('sizeStockSave');button.disabled=true;button.innerHTML='<span class="material-symbols-outlined">hourglass_empty</span>Salvando…';
    try{
        const fd=new FormData();
        fd.append('csrf_token',CSRF);
        fd.append('produto_id',stockSizeProduct.id);
        fd.append('tamanhos',JSON.stringify(stockSizeDraft));
        const {response,data:json}=await ProductComplementsEditor.fetchJson('api.php?acao=salvar_tamanhos',{method:'POST',body:fd,credentials:'same-origin'});
        if(!json.ok)throw new Error(json.error||'Erro ao atualizar estoque');
        closeSizeStock();
        await loadAll();
        toast('Estoque atualizado.');
    }catch(error){
        console.error('[Produtos] Erro ao atualizar estoque por tamanho:',error);
        toast(error.message||'Erro ao atualizar estoque','err');
    }finally{
        button.disabled=false;
        button.innerHTML='<span class="material-symbols-outlined">save</span>Salvar estoque';
    }
});

let editorBodyOverflow='';
$('btnBack').addEventListener('click',closeProductModal);
$('btnSaveAnother').addEventListener('click',()=>saveProduct({addAnother:true}));
$('modal').addEventListener('keydown',event=>{
    if($('modal').style.display!=='flex'||event.isComposing)return;
    if(event.key==='Escape'){event.preventDefault();closeProductModal();return;}
    if(event.key==='Enter'&&event.target.id==='pNovaCategoria'){event.preventDefault();$('pSalvarCategoria').click();return;}
    if(event.key==='Enter'&&(event.ctrlKey||event.metaKey)){event.preventDefault();saveProduct();return;}
    if(event.key==='Tab'){
        const nodes=Array.from($('modal').querySelectorAll('button,input,textarea,select,summary,[tabindex="0"]')).filter(node=>!node.disabled&&!node.closest('[inert]')&&node.getClientRects().length),first=nodes[0],last=nodes[nodes.length-1];
        if(event.shiftKey&&document.activeElement===first){event.preventDefault();last?.focus();}else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first?.focus();}
    }
});
$('previewJump').addEventListener('click',()=>{$('s6').scrollIntoView({block:'start',behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'});});
document.querySelectorAll('[data-operation]').forEach(button=>button.addEventListener('click',()=>{operationProfile=button.dataset.operation;applyBusinessContext({expand:true});buildReview();}));
$('btnNext').addEventListener('click',()=>saveProduct());

function wait(ms){return new Promise(resolve=>setTimeout(resolve,ms))}
async function saveProduct({addAnother=false}={}){
    const btn=$('btnNext'),another=$('btnSaveAnother');
    if(productSaving)return;
    if(editId&&!complementsEditor?.ready){showEditorNotice('Aguarde carregar os complementos existentes antes de salvar este produto.');return;}
    $('editorSaveNotice').hidden=true;
    const requiredSteps=customizationEnabled?[1,2,5]:[1,2];
    if(!requiredSteps.every(validateStep))return;
    const existingId=Number(editId);
    const wasEditing=Number.isInteger(existingId)&&existingId>0;
    if(editId!==null&&!wasEditing){toast('Reabra o produto pela lista para continuar a edição.','err');return;}
    const acao=wasEditing?'editar':'adicionar';
    let productPersisted=false;
    // Quantidade consolidada é derivada exclusivamente dos tamanhos.
    const fd=new FormData();
    fd.append('id',editId??'');
    if(!wasEditing&&productCreateKey)fd.append('creation_key',productCreateKey);
    fd.append('nome',$('pNome').value.trim());
    fd.append('codigo',$('pCod').value.trim());
    fd.append('preco',$('pPreco').value.trim());
    fd.append('preco_custo',$('pCusto').value.trim() || '0');
    fd.append('quantidade',availabilityMode==='sob_demanda'&&!wasEditing&&!sizeDraft.length?'0':(sizeDraft.length?String(sizeDraft.reduce((n,s)=>n+(Number(s.quantidade)||0),0)):$('pQtd').value.trim()));
    if(COMMERCIAL_AVAILABILITY_SCHEMA_READY){
        fd.append('modo_disponibilidade',availabilityMode);
        fd.append('disponibilidade_manual',availabilityManual?'1':'0');
    }
    fd.append('descricao',$('pDesc').value.trim());
    if(categoriasProntas)fd.append('categoria_id',$('pCategoria').value);
    fd.append('validade', temValidade ? $('pValidade').value : '');
    fd.append('csrf_token',CSRF);
    if($('fImg').files[0])fd.append('imagem',$('fImg').files[0]);
    if(wasEditing&&imageRemovalRequested&&!$('fImg').files[0])fd.append('remover_imagem','1');

    productSaving=true;$('productEditorFields').inert=true;$('modal').setAttribute('aria-busy','true');btn.disabled=true;another.disabled=true;$('btnBack').disabled=true;$('mclose').disabled=true;updateEditorValidation();
    setWizardButton('<svg class="save-spinner" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="2" stroke-dasharray="40 18"/></svg>Salvando…');

    try{
        const hasImage=Boolean($('fImg').files[0]);
        const {response:r,data:j}=await ProductComplementsEditor.fetchJson(`api.php?acao=${acao}`,{method:'POST',body:fd,credentials:'same-origin'},hasImage?60000:25000);

        if(j.ok){
            const productId=wasEditing?existingId:Number(j.id);
            if(!Number.isInteger(productId)||productId<=0)throw new Error('O produto foi salvo, mas o servidor não informou um ID válido. Reabra o produto pela lista antes de continuar.');
            editId=productId;productCreateKey=null;productPersisted=true;

            const background=$('saveBackground');if(background&&(sizeDraft.length||customizationEnabled||complementsEditor?.dirty))background.classList.add('on');
            if(sizeDraft.length){
                const sf=new FormData();
                sf.append('csrf_token',CSRF);
                sf.append('produto_id',productId);
                sf.append('tamanhos',JSON.stringify(sizeDraft));

                const {response:sr,data:sj}=await ProductComplementsEditor.fetchJson('api.php?acao=salvar_tamanhos',{method:'POST',body:sf,credentials:'same-origin'});

                if(!sj.ok)throw new Error(sj.error||'Erro ao salvar tamanhos');
            }

            if(customizationEnabled||complementsEditor?.dirty)await complementsEditor.save(productId);
            $('saveBackground').classList.remove('on');setWizardButton('<svg class="save-check" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Salvo');await wait(matchMedia('(prefers-reduced-motion: reduce)').matches?0:520);
            skipWizardLeaveWarning=true;
            // O reset acontece somente depois de produto, tamanhos e complementos persistirem.
            if(addAnother){
                toast(wasEditing?'Produto atualizado. Você já pode cadastrar outro.':'Produto cadastrado. Você já pode cadastrar outro.');
                openM(null,{allowDuringSave:true});
            }else{
                $('modal').style.display='none';document.body.style.overflow=editorBodyOverflow;editorReturnFocus?.focus();
                toast(wasEditing?'Produto atualizado!':'Produto cadastrado!');
            }
            // A atualização da lista é secundária: não mantenha o formulário
            // nem os botões bloqueados depois de a API confirmar o salvamento.
            void loadAll();
        } else {
            if(j.idempotency_conflict&&Number.isInteger(Number(j.id))&&Number(j.id)>0){
                editId=Number(j.id);productCreateKey=null;showEditorNotice('O produto já foi criado. Salve novamente para concluir a atualização do mesmo cadastro.');
                toast(j.error||'Este produto já foi recebido. Salve novamente para atualizar o mesmo cadastro.','warn');
                return;
            }
            if(/já existe um produto com este código/i.test(String(j.error||'')))markFieldInvalid('pCod','Este SKU já está cadastrado. Gere outro código ou informe um SKU diferente.');
            showEditorNotice(j.error||'Não foi possível salvar. Seus dados permanecem no editor.');toast(j.error||'Não foi possível salvar o produto. Revise os campos e tente novamente.','err');
        }
    }catch(e){
        console.error('[Produtos] Erro ao salvar produto:',e);
        if(e.status===409&&customizationEnabled)gotoStep(5);
        const rawMessage=String(e?.message||'');
        const safeMessage=e?.code==='UPLAR_TIMEOUT'
            ?(productPersisted?'O produto principal foi salvo, mas uma etapa complementar não respondeu. Seus dados permanecem; tente salvar novamente para concluir.':'Sem resposta do servidor. O produto pode ter sido salvo; confira a lista antes de tentar novamente para evitar duplicação.')
            :(/failed to fetch|networkerror|load failed|unexpected token|json|http 5\d\d|500/i.test(rawMessage)
            ?'O servidor não conseguiu concluir o salvamento. Verifique a conexão e tente novamente.'
            :(rawMessage||'Não foi possível concluir o salvamento. Tente novamente.'));
        const message=productPersisted&&e?.code!=='UPLAR_TIMEOUT'?'Produto salvo. '+safeMessage+' Os dados permanecem; tente salvar novamente para concluir.':safeMessage;showEditorNotice(message);toast(message,'err');
        if(e?.code==='UPLAR_TIMEOUT')setTimeout(()=>{void loadAll();},1500);
    }finally{
        $('saveBackground')?.classList.remove('on');productSaving=false;$('productEditorFields').inert=false;$('modal').removeAttribute('aria-busy');$('btnBack').disabled=false;$('mclose').disabled=false;setWizardButton('<svg class="" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>Salvar produto');updateComplementsNavigation();
    }
}

async function delProd(id,nome){
    if(!confirm(`Excluir "${nome}"?`))return;
    const fd=new FormData();fd.append('id',id);fd.append('csrf_token',CSRF);
    const r=await fetch('api.php?acao=deletar',{method:'POST',body:fd,credentials:'same-origin'});
    const j=await r.json();
    j.ok?(toast('Produto excluído!'),loadAll()):toast(j.error||'Erro ao excluir','err');
}

let estoque=[];
let selectedProductId=null,inspectorRequest=0,catalogVisible=24,catalogDrawerBodyOverflow=null,catalogInspectorFrame=0;
const CATALOG_PAGE_SIZE=24;

function availabilityOf(product){
    const mode=(product?.disponibilidade_modo??product?.modo_disponibilidade)==='sob_demanda'?'sob_demanda':'estoque';
    const manual=Number(product?.disponibilidade_manual??1)!==0;
    const quantity=Math.max(0,Number(product?.quantidade)||0);
    const available=manual&&(mode==='sob_demanda'||quantity>0);
    if(!manual)return {mode,manual,quantity,available:false,label:'Venda pausada',tone:'err'};
    if(mode==='sob_demanda')return {mode,manual,quantity,available:true,label:'Sob demanda',tone:'ok'};
    if(quantity===0)return {mode,manual,quantity,available:false,label:'Esgotado',tone:'err'};
    if(quantity<=5)return {mode,manual,quantity,available:true,label:`${quantity} restante${quantity===1?'':'s'}`,tone:'warn'};
    return {mode,manual,quantity,available:true,label:`${quantity} em estoque`,tone:'ok'};
}
function productFinance(product){
    const sale=Math.max(0,Number(product?.preco)||0),raw=product?.preco_custo;
    // O contrato atual persiste custo vazio como 0; sem flag de preenchimento, 0 não permite margem confiável.
    const cost=Number(raw),hasCost=raw!==null&&raw!==''&&Number.isFinite(cost)&&cost>0;
    if(!hasCost)return {sale,cost:null,hasCost:false,profit:null,margin:null,markup:null};
    const profit=sale-cost;
    return {sale,cost,hasCost:true,profit,margin:sale>0?(profit/sale)*100:null,markup:(profit/cost)*100};
}
function productCategory(product){
    if(typeof product?.categoria_nome==='string'&&product.categoria_nome.trim())return product.categoria_nome.trim();
    const raw=product?.categoria_id??product?.categoria;
    const category=categoriasDisponiveis.find(item=>Number(item.id)===Number(raw));
    if(category?.nome)return String(category.nome);
    return typeof raw==='string'&&!/^\d+$/.test(raw.trim())?raw.trim():'';
}
function catalogStatus(product){return availabilityOf(product);}

function applyFilters(arr){
    let f=[...arr];
    if(activeFilter==='ok')  f=f.filter(p=>catalogStatus(p).available);
    if(activeFilter==='low') f=f.filter(p=>{const s=catalogStatus(p);return s.mode==='estoque'&&s.quantity>0&&s.quantity<=5;});
    if(activeFilter==='zero')f=f.filter(p=>{const s=catalogStatus(p);return s.mode==='estoque'&&s.quantity===0;});
    if(activeFilter==='exp') f=f.filter(p=>{
        const d=diasParaVencer(p.validade);
        return d!==null && d<=30;
    });
    if(activeFilter==='no-cost') f=f.filter(p=>!productFinance(p).hasCost);
    if(activeFilter==='no-image') f=f.filter(p=>!p.imagem);
    if(activeFilter==='incomplete') f=f.filter(p=>!p.imagem||!productFinance(p).hasCost||!(p.codigo||'').trim());
    const t=$('busca').value.toLowerCase().trim();
    if(t)f=f.filter(p=>(p.nome||'').toLowerCase().includes(t)||(p.codigo||'').toLowerCase().includes(t));
    const s=$('sortSel').value;
    f.sort((a,b)=>{
        if(s==='name')   return (a.nome||'').localeCompare(b.nome||'');
        if(s==='name-d') return (b.nome||'').localeCompare(a.nome||'');
        if(s==='price')  return Number(a.preco)-Number(b.preco);
        if(s==='price-d')return Number(b.preco)-Number(a.preco);
        if(s==='stock')  return Number(a.quantidade)-Number(b.quantidade);
        if(s==='stock-d')return Number(b.quantidade)-Number(a.quantidade);
        if(s==='val-asc'){
            const da=diasParaVencer(a.validade), db=diasParaVencer(b.validade);
            if(da===null&&db===null)return 0;
            if(da===null)return 1;if(db===null)return -1;
            return da-db;
        }
        if(s==='val-desc'){
            const da=diasParaVencer(a.validade), db=diasParaVencer(b.validade);
            if(da===null&&db===null)return 0;
            if(da===null)return 1;if(db===null)return -1;
            return db-da;
        }
        return 0;
    });
    return f;
}

function renderKPIs(){
    const total=estoque.length;
    const unidades=estoque.reduce((s,p)=>s+Math.max(0,Number(p.quantidade)||0),0);
    const comCusto=estoque.filter(p=>productFinance(p).hasCost);
    const valorEstoque=estoque.reduce((s,p)=>s+(Math.max(0,Number(p.quantidade)||0)*Math.max(0,Number(p.preco)||0)),0);
    const valorCustoConhecido=comCusto.reduce((s,p)=>s+(Math.max(0,Number(p.quantidade)||0)*productFinance(p).cost),0);
    const disponiveis=estoque.filter(p=>catalogStatus(p).available).length;
    const disponibilidade=total?Math.round((disponiveis/total)*100):0;
    const low=estoque.filter(p=>{const s=catalogStatus(p);return s.mode==='estoque'&&s.quantity>0&&s.quantity<=5;}).length;
    const zero=estoque.filter(p=>{const s=catalogStatus(p);return s.mode==='estoque'&&s.quantity===0;}).length;
    const reposicao=low+zero;
    const vencidos=estoque.filter(p=>{const d=diasParaVencer(p.validade);return d!==null&&d<0}).length;
    const aVencer=estoque.filter(p=>{const d=diasParaVencer(p.validade);return d!==null&&d>=0&&d<=30}).length;

    const criticos=estoque.filter(p=>{const d=diasParaVencer(p.validade);return d!==null&&d<0}).length;
    const expirando=estoque.filter(p=>{const d=diasParaVencer(p.validade);return d!==null&&d>=0&&d<=7}).length;
    const banner=$('valBanner');
    if(criticos>0||expirando>0){
        const partes=[];
        if(criticos>0)partes.push(`<span>${criticos} produto${criticos>1?'s':''} vencido${criticos>1?'s':''}</span>`);
        if(expirando>0)partes.push(`<span>${expirando} produto${expirando>1?'s':''} vencendo em até 7 dias</span>`);
        $('valBannerTxt').innerHTML='Atenção: '+partes.join(' e ')+'. Verifique seu estoque.';
        banner.style.display='flex';
    } else { banner.style.display='none'; }

    $('kpis').innerHTML=`
    <div class="kpi anim d3">
        <div class="kpi-top"><div class="kpi-ico"><span class="material-symbols-outlined">payments</span></div><span class="kpi-tag">potencial</span></div>
        <div class="kpi-val" id="kv1">R$ 0,00</div><div class="kpi-lbl">Valor potencial em estoque</div>
        <div class="kpi-hint">Venda × saldo · ${R(valorCustoConhecido)} a custo conhecido</div>
    </div>
    <div class="kpi g anim d3">
        <div class="kpi-top"><div class="kpi-ico"><span class="material-symbols-outlined">inventory</span></div><span class="kpi-tag">volume</span></div>
        <div class="kpi-val" id="kv2">0</div><div class="kpi-lbl">Unidades armazenadas</div>
        <div class="kpi-hint">${total?`${(unidades/total).toLocaleString('pt-BR',{maximumFractionDigits:1})} por produto`:'Catálogo sem produtos'}</div>
    </div>
    <div class="kpi anim d3">
        <div class="kpi-top"><div class="kpi-ico"><span class="material-symbols-outlined">check_circle</span></div><span class="kpi-tag">cobertura</span></div>
        <div class="kpi-val" id="kv3">0%</div><div class="kpi-lbl">Disponibilidade do catálogo</div>
        <div class="kpi-hint">${disponiveis} de ${total} disponível(is) para venda</div>
    </div>
    <div class="kpi o anim d3">
        <div class="kpi-top"><div class="kpi-ico"><span class="material-symbols-outlined">production_quantity_limits</span></div><span class="kpi-tag">ação</span></div>
        <div class="kpi-val" id="kv4">0</div><div class="kpi-lbl">Reposição prioritária</div>
        <div class="kpi-hint">${zero} zerado(s) · ${low} com até 5 un.</div>
    </div>
    <div class="kpi r anim d3">
        <div class="kpi-top"><div class="kpi-ico"><span class="material-symbols-outlined">remove_shopping_cart</span></div><span class="kpi-tag">crítico</span></div>
        <div class="kpi-val" id="kv5">0</div><div class="kpi-lbl">Produtos esgotados</div>
        <div class="kpi-hint">Não inclui itens disponíveis sob demanda</div>
    </div>`;

    setTimeout(()=>{
        cnt($('kv1'),valorEstoque,v=>R(v));
        cnt($('kv2'),unidades,v=>Math.round(v).toLocaleString('pt-BR'));
        cnt($('kv3'),disponibilidade,v=>Math.round(v).toLocaleString('pt-BR')+'%');
        cnt($('kv4'),reposicao,v=>Math.round(v).toLocaleString('pt-BR'));
        cnt($('kv5'),zero,v=>Math.round(v).toLocaleString('pt-BR'));
    },60);
}

function updateCatalogMeta(filtered){
    const counts={
        all:estoque.length,
        ok:estoque.filter(product=>catalogStatus(product).available).length,
        low:estoque.filter(product=>{const status=catalogStatus(product);return status.mode==='estoque'&&status.quantity>0&&status.quantity<=5;}).length,
        zero:estoque.filter(product=>{const status=catalogStatus(product);return status.mode==='estoque'&&status.quantity===0;}).length,
        exp:estoque.filter(product=>{const days=diasParaVencer(product.validade);return days!==null&&days<=30;}).length
    };
    document.querySelectorAll('.filter-btn[data-f]').forEach(button=>{
        const label={all:'Todos',ok:'Em estoque',low:'Estoque baixo',zero:'Zerados',exp:'A vencer'}[button.dataset.f];
        if(label)button.textContent=`${label} (${counts[button.dataset.f]})`;
    });
    $('catalogResultCount').textContent=filtered.length===estoque.length?`${estoque.length} produto(s)`:`${filtered.length} de ${estoque.length} produto(s)`;
    const profile=BUSINESS_CONTEXT.perfil_concluido&&BUSINESS_CONTEXT.segmento?`Perfil: ${String(BUSINESS_CONTEXT.segmento).replace(/_/g,' ').replace(/^./,letter=>letter.toLocaleUpperCase('pt-BR'))}`:'Visão geral';
    $('catalogBusinessContext').textContent=profile;
}

function renderProds(){
    const d=$('pg'), f=applyFilters(estoque),shown=f.slice(0,catalogVisible);
    updateCatalogMeta(f);
    if(selectedProductId!==null&&!f.some(p=>Number(p.id)===Number(selectedProductId)))clearCatalogSelection();
    $('catalogPagination').hidden=f.length<=shown.length;
    if(!f.length){d.innerHTML='<div class="empty" style="grid-column:1/-1"><span class="material-symbols-outlined">inventory_2</span><p>Nenhum produto encontrado</p></div>';return}
    d.innerHTML='';
    shown.forEach((p,i)=>{
        const status=catalogStatus(p),finance=productFinance(p),q=status.quantity;
        const sc=status.tone==='err'?'zero':status.tone==='warn'?'low':'';
        const bc=status.tone,bl=status.label;

        const dias=diasParaVencer(p.validade);
        const vSt=valStatus(dias);
        const vLbl=valLabel(dias);
        const vIco=valIcon(vSt);

        const valBadgeHtml=vSt?`
            <div class="pc-val-badge ${vSt}" title="${vLbl}">
                <span class="material-symbols-outlined">${vIco}</span>
                ${fmtDate(p.validade)}
            </div>`:'';

        const valFootHtml=vSt?`
            <div class="pc-validade ${vSt}">
                <span class="material-symbols-outlined">${vIco}</span>
                ${vLbl}
            </div>`:'';

        const costHtml=finance.hasCost?`Custo ${R(finance.cost)}`:'Custo —';
        const marginHtml=finance.margin===null?'Margem —':`${finance.margin.toFixed(1).replace('.',',')}% margem`;
        const c=document.createElement('article');c.className='pc'+(Number(p.id)===Number(selectedProductId)?' is-selected':'');c.style.animationDelay=(i*.04)+'s';c.tabIndex=0;c.dataset.productId=String(p.id);c.setAttribute('aria-label',`Inspecionar ${p.nome||'produto'}`);
        c.innerHTML=`
            <div class="pc-img">
                ${p.imagem?`<img loading="lazy" decoding="async" src="${esc(p.imagem)}" alt="${esc(p.nome)}">`:'<span class="material-symbols-outlined">image_not_supported</span>'}
                <span class="pc-badge ${bc}">${bl}</span>
                ${valBadgeHtml}
                ${productCategory(p)?`<span class="pc-image-category">${esc(productCategory(p))}</span>`:''}
            </div>
            <div class="pc-body">
                <div class="pc-meta"><div class="pc-code">${esc(p.codigo||'Sem SKU')}</div><span class="pc-margin ${finance.margin===null?'unknown':finance.profit<0?'loss':''}" title="${finance.margin===null?'Informe um custo para calcular a margem.':'Margem bruta sobre o preço de venda'}">${marginHtml}</span></div>
                <div class="pc-name" title="${esc(p.nome)}">${esc(p.nome)}</div>
                <div class="pc-finance"><div class="pc-price"><small>Venda</small> ${R(p.preco)}</div><div class="pc-cost">${costHtml}</div></div>
                ${valFootHtml}
                <div class="pc-acts">
                    <button class="pc-btn pc-e" type="button" onclick="event.stopPropagation();editProduct(${Number(p.id)})"><span class="material-symbols-outlined">edit</span>Editar</button>
                    <button class="pc-btn pc-e pc-more" type="button" onclick="openProductActions(event,${p.id})" aria-label="Mais ações para ${esc(p.nome)}" aria-haspopup="menu" aria-expanded="false"><span class="material-symbols-outlined">more_horiz</span></button>
                </div>
            </div>`;
        c.addEventListener('click',()=>selectCatalogProduct(p.id));
        c.addEventListener('keydown',event=>{if(event.target!==c)return;if(event.key==='Enter'||event.key===' '){event.preventDefault();selectCatalogProduct(p.id);}});
        d.appendChild(c);
    });
}

function clearCatalogSelection(){
    closeCatalogInspectorDrawer();
    selectedProductId=null;inspectorRequest++;
    $('catalogInspector').classList.remove('has-selection','is-open');
    $('catalogInspectorToggle').setAttribute('aria-expanded','false');
    $('catalogInspectorBackdrop').hidden=true;
    $('catalogInspectorEmpty').hidden=false;$('catalogInspectorContent').hidden=true;$('catalogInspectorContent').replaceChildren();
    $('catalogInspectorFoot').hidden=true;
}
function closeCatalogInspectorDrawer({restoreFocus=true}={}){
    const inspector=$('catalogInspector'),wasOpen=inspector.classList.contains('is-open');
    inspector.classList.remove('is-open');$('catalogInspectorToggle').setAttribute('aria-expanded','false');$('catalogInspectorBackdrop').hidden=true;
    inspector.removeAttribute('aria-modal');inspector.removeAttribute('role');
    if(catalogDrawerBodyOverflow!==null){document.body.style.overflow=catalogDrawerBodyOverflow;catalogDrawerBodyOverflow=null;}
    if(restoreFocus&&wasOpen&&matchMedia('(max-width:1080px)').matches){
        const root=viewMode==='list'?$('tblBody'):$('pg');
        root.querySelector(`[data-product-id="${Number(selectedProductId)}"]`)?.focus({preventScroll:true});
    }
}

function inspectorAvailability(status){return `<span class="badge ${status.tone}">${esc(status.label)}</span>`;}
function renderInspector(product){
    const content=$('catalogInspectorContent'),status=catalogStatus(product),finance=productFinance(product),sizes=(product.tamanhos||[]).filter(size=>Number(size?.ativo)!==0);
    const sizeHtml=sizes.length?`<section class="inspector-block"><h3>Tamanhos e saldos</h3><div class="inspector-sizes">${sizes.map(size=>`<div class="inspector-size"><span>${esc(size.tamanho||'—')}</span><strong>${Math.max(0,Number(size.quantidade)||0)} un.</strong></div>`).join('')}</div></section>`:'';
    const financeHtml=finance.hasCost?`
        <div><span>Preço de custo</span><strong>${R(finance.cost)}</strong></div>
        <div><span>Preço de venda</span><strong>${R(finance.sale)}</strong></div>
        <div><span>Lucro bruto unitário</span><strong class="${finance.profit<0?'negative':'positive'}">${R(finance.profit)}</strong></div>
        <div><span>Margem bruta</span><strong class="${finance.margin!==null&&finance.margin<0?'negative':finance.margin!==null?'positive':'unknown'}">${finance.margin===null?'Indisponível':finance.margin.toFixed(1).replace('.',',')+'%'}</strong></div>
        <div><span>Markup</span><strong class="${finance.markup<0?'negative':''}">${finance.markup.toFixed(1).replace('.',',')}%</strong></div>`:
        `<div><span>Preço de venda</span><strong>${R(finance.sale)}</strong></div><div class="unknown"><span>Margem bruta</span><strong class="unknown">Indisponível</strong></div><p class="inspector-loading" style="grid-column:1/-1">Informe um custo para calcular a margem.</p>`;
    content.innerHTML=`
        <div class="inspector-media">${product.imagem?`<img src="${esc(product.imagem)}" alt="${esc(product.nome)}">`:'<span class="material-symbols-outlined" aria-hidden="true">image_not_supported</span>'}</div>
        <div><h3 class="inspector-title">${esc(product.nome||'Produto sem nome')}</h3><div class="inspector-meta"><span>${esc(product.codigo||'Sem SKU')}</span>${productCategory(product)?`<span>${esc(productCategory(product))}</span>`:''}${inspectorAvailability(status)}</div></div>
        ${product.descricao?`<p class="inspector-desc">${esc(product.descricao)}</p>`:''}
        <section class="inspector-block"><h3>Financeiro bruto</h3><div class="inspector-values">${financeHtml}</div></section>
        <section class="inspector-block"><h3>Disponibilidade</h3><div class="inspector-values"><div><span>Modo</span><strong>${status.mode==='sob_demanda'?'Sob demanda':'Saldo físico'}</strong></div><div><span>Situação</span><strong>${esc(status.label)}</strong></div>${status.mode==='estoque'?`<div><span>Saldo</span><strong>${status.quantity} un.</strong></div>`:''}</div></section>
        ${BUSINESS_CONTEXT.familia==='alimentacao'?'<section class="inspector-block" id="inspectorComplements"><h3>Complementos</h3><p class="inspector-loading" role="status">Carregando complementos…</p></section>'+sizeHtml:sizeHtml+'<section class="inspector-block" id="inspectorComplements"><h3>Complementos</h3><p class="inspector-loading" role="status">Carregando complementos…</p></section>'}`;
    $('catalogInspectorEmpty').hidden=true;content.hidden=false;$('catalogInspectorFoot').hidden=false;
    $('catalogEdit').onclick=()=>{closeCatalogInspectorDrawer();editProduct(product.id);};
    loadInspectorComplements(product.id);
    sizeCatalogInspector();
}
async function loadInspectorComplements(productId){
    const token=++inspectorRequest,root=$('inspectorComplements');
    try{
        const {response,data}=await ProductComplementsEditor.fetchJson(`api.php?acao=complementos_grupos&produto_id=${encodeURIComponent(productId)}`,{credentials:'same-origin'},15000);
        if(token!==inspectorRequest||Number(selectedProductId)!==Number(productId)||!root.isConnected)return;
        if(!response.ok||!data?.ok)throw new Error(data?.error||'Não foi possível carregar os complementos.');
        const groups=(Array.isArray(data.grupos)?data.grupos:[]).filter(group=>group.associado&&group.ativo!==false&&Number(group.ativo)!==0);
        root.innerHTML='<h3>Complementos</h3>'+(groups.length?`<div class="inspector-complements">${groups.map(group=>{const options=(group.opcoes||[]).filter(option=>option.ativo!==false&&Number(option.ativo)!==0);return `<div class="inspector-complement"><strong>${esc(group.nome||'Grupo')}</strong><small>${options.length?options.map(option=>esc(option.nome||'Opção')).join(' · '):'Sem opções ativas'}</small></div>`}).join('')}</div>`:'<p class="inspector-loading">Nenhum complemento vinculado.</p>');
    }catch(error){
        if(token!==inspectorRequest||Number(selectedProductId)!==Number(productId)||!root.isConnected)return;
        root.innerHTML='<h3>Complementos</h3><p class="inspector-loading">Não foi possível carregar os complementos.</p>';
    }
}
function selectCatalogProduct(id){
    const product=estoque.find(item=>Number(item.id)===Number(id));if(!product)return;
    selectedProductId=Number(product.id);$('catalogInspector').classList.add('has-selection','is-open');$('catalogInspectorToggle').setAttribute('aria-expanded','true');
    $('catalogInspectorBackdrop').hidden=!matchMedia('(max-width: 1080px)').matches;
    renderInspector(product);renderProds();if(viewMode==='list')renderTable();
    if(matchMedia('(max-width:1080px)').matches){
        if(catalogDrawerBodyOverflow===null)catalogDrawerBodyOverflow=document.body.style.overflow;
        document.body.style.overflow='hidden';$('catalogInspector').setAttribute('role','dialog');$('catalogInspector').setAttribute('aria-modal','true');
        requestAnimationFrame(()=>$('catalogInspectorClose').focus({preventScroll:true}));
    }
}

function deleteCatalogProduct(id){
    const product=estoque.find(item=>Number(item.id)===Number(id));
    if(product)void delProd(Number(product.id),String(product.nome||''));
}

function renderTable(){
    const f=applyFilters(estoque),shown=f.slice(0,catalogVisible), tb=$('tblBody');
    tb.innerHTML='';
    if(!f.length){tb.innerHTML=`<tr><td colspan="7" style="text-align:center;padding:3rem;color:var(--faint)">Nenhum produto encontrado</td></tr>`;return}
    shown.forEach(p=>{
        const status=catalogStatus(p),q=status.quantity,bc=status.tone,bl=status.label,finance=productFinance(p);
        const pct=Math.min(100,(q/50)*100);

        const dias=diasParaVencer(p.validade);
        const vSt=valStatus(dias);
        const vLbl=valLabel(dias);
        const vIco=valIcon(vSt);

        const validadeCel = p.validade
            ? `<span class="badge ${vSt==='ok'?'ok':vSt==='aten'?'violet':vSt==='alrt'?'warn':'err'}">
                   <span class="material-symbols-outlined">${vIco}</span>
                   ${fmtDate(p.validade)}
               </span>
               <div style="font-size:.68rem;color:var(--faint);margin-top:2px">${vLbl}</div>`
            : `<span style="color:var(--faint);font-size:.75rem">—</span>`;

        const financeText=finance.hasCost?`Custo ${R(finance.cost)} · ${finance.margin===null?'Margem indisponível':`Margem ${finance.margin.toFixed(1).replace('.',',')}%`}`:'Custo não informado · Margem indisponível';
        const tr=document.createElement('tr');tr.tabIndex=0;tr.dataset.productId=String(p.id);tr.classList.toggle('is-selected',Number(p.id)===Number(selectedProductId));
        tr.innerHTML=`
            <td>
                <div class="tbl-cell">
                    <div class="tbl-av">${p.imagem?`<img loading="lazy" decoding="async" src="${esc(p.imagem)}" alt="">`:'<span class="material-symbols-outlined">image_not_supported</span>'}</div>
                    <div><div class="tbl-name">${esc(p.nome)}</div><div class="tbl-sku">${esc(p.descricao||'').substring(0,40)}${(p.descricao||'').length>40?'…':''}</div></div>
                </div>
            </td>
            <td><strong>${esc(p.codigo||'—')}</strong></td>
            <td><strong>${R(p.preco)}</strong><br><small style="color:var(--faint)">${financeText}</small></td>
            <td>
                <div class="stock-bar-wrap">
                    <div class="stock-bar"><div class="stock-fill ${bc}" style="width:${pct}%"></div></div>
                    <span style="font-size:.8rem;font-weight:600">${q} un.</span>
                </div>
            </td>
            <td>${validadeCel}</td>
            <td><span class="badge ${bc}">${bl}</span></td>
            <td>
                <div class="tbl-acts">
                    <button class="tbl-btn" onclick="event.stopPropagation();openSizeStock(${p.id})" title="Ajustar estoque"><span class="material-symbols-outlined">tune</span></button>
                    <button class="tbl-btn" type="button" onclick="event.stopPropagation();editProduct(${Number(p.id)})" title="Editar"><span class="material-symbols-outlined">edit</span></button>
                    <button class="tbl-btn del" onclick="event.stopPropagation();deleteCatalogProduct(${Number(p.id)})" title="Excluir"><span class="material-symbols-outlined">delete</span></button>
                </div>
            </td>`;
        tr.addEventListener('click',()=>selectCatalogProduct(p.id));
        tr.addEventListener('keydown',event=>{if(event.target!==tr)return;if(event.key==='Enter'||event.key===' '){event.preventDefault();selectCatalogProduct(p.id);}});
        tb.appendChild(tr);
    });
}

$('busca').addEventListener('input',()=>refreshCatalog());

function refreshCatalog({reset=true}={}){
    if(reset)catalogVisible=CATALOG_PAGE_SIZE;
    renderProds();if(viewMode==='list')renderTable();
}
$('catalogMore').addEventListener('click',()=>{catalogVisible+=CATALOG_PAGE_SIZE;renderProds();if(viewMode==='list')renderTable();});
function sizeCatalogInspector(){
    cancelAnimationFrame(catalogInspectorFrame);
    catalogInspectorFrame=requestAnimationFrame(()=>{
        const panel=$('catalogInspector');
        if(matchMedia('(max-width:1080px)').matches){panel.style.maxHeight='';return;}
        const top=Math.max(16,panel.getBoundingClientRect().top);
        panel.style.maxHeight=`${Math.max(260,innerHeight-top-16)}px`;
    });
}
window.addEventListener('resize',sizeCatalogInspector);
window.addEventListener('scroll',sizeCatalogInspector,{passive:true});
const catalogMobileQuery=matchMedia('(max-width:1080px)');
$('catalogInspectorToggle').disabled=!catalogMobileQuery.matches;
$('catalogInspectorToggle').addEventListener('click',()=>{
    if(!selectedProductId||!catalogMobileQuery.matches)return;
    if($('catalogInspector').classList.contains('is-open'))closeCatalogInspectorDrawer();
    else selectCatalogProduct(selectedProductId);
});
$('catalogInspectorClose').addEventListener('click',()=>closeCatalogInspectorDrawer());
$('catalogInspectorBackdrop').addEventListener('click',()=>closeCatalogInspectorDrawer());
catalogMobileQuery.addEventListener('change',()=>{
    closeCatalogInspectorDrawer({restoreFocus:false});$('catalogInspectorToggle').disabled=!catalogMobileQuery.matches;
});
document.addEventListener('keydown',event=>{
    if(!catalogMobileQuery.matches||!$('catalogInspector').classList.contains('is-open')||$('modal').style.display==='flex'||$('sizeStockModal').style.display==='flex')return;
    if(event.key==='Escape'){event.preventDefault();closeCatalogInspectorDrawer();return;}
    if(event.key!=='Tab')return;
    const focusable=[...$('catalogInspector').querySelectorAll('button:not([disabled]),a[href],[tabindex="0"]')].filter(element=>element.offsetParent!==null&&!element.hidden);
    if(!focusable.length)return;
    const first=focusable[0],last=focusable[focusable.length-1];
    if(event.shiftKey&&(document.activeElement===first||!$('catalogInspector').contains(document.activeElement))){event.preventDefault();last.focus();}
    else if(!event.shiftKey&&(document.activeElement===last||!$('catalogInspector').contains(document.activeElement))){event.preventDefault();first.focus();}
});

async function loadAll(){
    try{
        const {response:r,data}=await ProductComplementsEditor.fetchJson('api.php?acao=listar',{credentials:'same-origin'},20000);
        if(!r.ok||!Array.isArray(data))throw new Error(data?.error||'Não foi possível carregar os produtos.');
        estoque=data;
        catalogLoaded=true;$('catalogLoadState').hidden=true;
        renderKPIs();
        refreshCatalog();
        if(selectedProductId!==null){const selected=estoque.find(item=>Number(item.id)===Number(selectedProductId));if(selected)renderInspector(selected);}
    }catch(e){
        console.error(e);showCatalogLoadError('Não foi possível carregar os produtos.');
        if(!catalogLoaded){
            $('kpis').innerHTML='<div class="catalog-indicators-error" role="status">Indicadores indisponíveis até o catálogo carregar.</div>';
            $('pg').innerHTML='<div class="empty" style="grid-column:1/-1"><span class="material-symbols-outlined">cloud_off</span><p>Não foi possível carregar o catálogo.</p></div>';
            $('catalogPagination').hidden=true;clearCatalogSelection();
        }
        toast('Erro ao carregar produtos','err');
    }
}

let catalogLoaded=false;
function showCatalogLoadError(message){
    const state=$('catalogLoadState');state.replaceChildren(document.createTextNode(message+' '));
    const retry=document.createElement('button');retry.type='button';retry.textContent='Tentar novamente';retry.addEventListener('click',()=>void loadAll());state.appendChild(retry);state.hidden=false;
}

let loteDrafts=[];
const loteCard=$('loteCard');
function resetLote(){loteDrafts=[];$('loteFotos').value='';$('loteRows').innerHTML='';$('loteEditor').style.display='none';$('loteUploadState').style.display='block';$('loteTodos').checked=false}
function abrirLote(){loteCard.style.display='block';loteCard.scrollIntoView({behavior:'smooth',block:'start'})}

function renderLote(){
    $('loteEditor').style.display='block';$('loteUploadState').style.display='none';
    $('loteCount').textContent=`${loteDrafts.length} rascunho${loteDrafts.length===1?'':'s'} · campos obrigatórios: nome, código e preço`;
    $('loteRows').innerHTML=loteDrafts.map((p,i)=>`<div class="lote-row" data-id="${p.id}">
        <input class="lote-row-check" type="checkbox" aria-label="Selecionar ${esc(p.nome)}">
        <img src="${esc(p.imagem)}" alt="">
        <input class="lote-field lote-name" data-key="nome" value="${esc(p.nome)}" placeholder="Nome do produto">
        <input class="lote-field" data-key="preco" type="number" min="0" step="0.01" value="${esc(p.preco)}" placeholder="Preço (R$)">
        <input class="lote-field" data-key="preco_custo" type="number" min="0" step="0.01" value="${esc(p.preco_custo||'')}" placeholder="Custo (R$)">
        <input class="lote-field" data-key="quantidade" type="number" min="0" step="1" value="${esc(p.quantidade)}" placeholder="Estoque">
        <input class="lote-field lote-category" data-key="categoria" maxlength="80" value="${esc(p.categoria)}" placeholder="Categoria">
        <input class="lote-field lote-code" data-key="codigo" value="${esc(p.codigo)}" placeholder="Código / SKU">
        <input class="lote-field lote-validade" data-key="validade" type="date" value="${esc(p.validade||'')}" title="Validade opcional">
    </div>`).join('');

    $('loteRows').querySelectorAll('.lote-field').forEach(input=>input.addEventListener('input',()=>{
        const row=input.closest('.lote-row'), p=loteDrafts.find(x=>x.id===Number(row.dataset.id)); if(p)p[input.dataset.key]=input.value;
    }));

    $('loteRows').querySelectorAll('.lote-field').forEach(input=>input.addEventListener('keydown',e=>{
        if(e.key==='Enter'){
            e.preventDefault();
            e.target.blur();
            const fields=[...$('loteRows').querySelectorAll('.lote-field')],n=fields.indexOf(e.target);
            fields[n+1]?.focus()
        }
    }));
}

$('btnLote').addEventListener('click',abrirLote);
$('btnCustos').addEventListener('click', abrirAtualizacaoCustos);

function abrirAtualizacaoCustos(){
    const modal=document.createElement('div');
    modal.className='modal'; modal.style.display='flex';
    modal.innerHTML=`<div class="mbox" style="max-width:760px;max-height:90vh;overflow:auto">
        <div class="mh"><h2>Atualizar custos dos produtos</h2><span class="mcl">close</span></div>
        <p class="hint" style="margin-bottom:1rem">Informe o custo dos produtos existentes. Produtos sem custo ficam em R$ 0,00 e não alteram vendas antigas.</p>
        <div style="display:grid;gap:.45rem">${estoque.map(p=>`<label style="display:grid;grid-template-columns:1fr 140px;gap:.7rem;align-items:center;padding:.45rem .6rem;border-bottom:1px solid var(--border)">
            <span>${esc(p.nome)} <small style="color:var(--faint)">(${esc(p.codigo||'—')})</small></span>
            <input class="cost-bulk-input" data-id="${p.id}" type="number" min="0" step="0.01" value="${Number(p.preco_custo||0).toFixed(2)}">
        </label>`).join('')}</div>
        <div style="display:flex;justify-content:flex-end;gap:.5rem;margin-top:1rem"><button class="pill pill-outline cost-cancel">Cancelar</button><button class="pill pill-solid cost-save">Salvar custos</button></div>
    </div>`;
    document.body.appendChild(modal);
    const close=()=>modal.remove();
    modal.querySelector('.mcl').onclick=close;
    modal.querySelector('.cost-cancel').onclick=close;

    modal.querySelector('.cost-save').onclick=async()=>{
        const itens=[...modal.querySelectorAll('.cost-bulk-input')].map(input=>({id:Number(input.dataset.id),preco_custo:Number(input.value||0)}));
        const fd=new FormData();
        fd.append('csrf_token',CSRF);
        fd.append('itens',JSON.stringify(itens));
        const res=await fetch('api.php?acao=atualizar_custos',{method:'POST',body:fd,credentials:'same-origin'});
        const j=await res.json();
        if(!j.ok){toast(j.error||'Erro ao atualizar custos','err');return}
        close();
        toast(`${j.atualizados} produto(s) atualizado(s)!`);
        await loadAll();
    };
}

$('btnFecharLote').addEventListener('click',()=>{loteCard.style.display='none'});
$('loteTodos').addEventListener('change',e=>$('loteRows').querySelectorAll('.lote-row-check').forEach(x=>x.checked=e.target.checked));

$('loteFotos').addEventListener('change',async e=>{
    const files=[...e.target.files];
    if(!files.length)return;
    if(files.length>100){toast('Selecione no máximo 100 fotos','err');return}
    if(files.some(f=>f.size>5*1024*1024)){toast('Cada foto pode ter no máximo 5 MB','err');return}

    const fd=new FormData();
    files.forEach(f=>fd.append('fotos[]',f));
    fd.append('csrf_token',CSRF);

    $('loteUploadState').innerHTML='<div class="empty"><span class="material-symbols-outlined">hourglass_empty</span><p>Preparando os rascunhos…</p></div>';

    try{
        const r=await fetch('produto_lote_api.php?acao=upload',{method:'POST',body:fd,credentials:'same-origin'});
        const j=await r.json();
        if(!j.ok)throw new Error(typeof j.error==='string'&&j.error.trim()?j.error:'Não foi possível preparar o lote.');
        loteDrafts=Array.isArray(j.rascunhos)?j.rascunhos:[];
        renderLote()
    }catch(err){
        toast(err?.message||'Erro ao enviar as fotos','err');
        resetLote()
    }
});

$('btnAplicarLote').addEventListener('click',()=>{
    const selected=[...$('loteRows').querySelectorAll('.lote-row-check:checked')].map(x=>x.closest('.lote-row').dataset.id);
    if(!selected.length){toast('Selecione os produtos que deseja alterar','err');return}
    const values={preco:$('lotePreco').value,preco_custo:$('loteCusto').value,quantidade:$('loteQtd').value,categoria:$('loteCategoria').value.trim(),validade:$('loteValidade').value};
    loteDrafts.filter(p=>selected.includes(String(p.id))).forEach(p=>Object.keys(values).forEach(k=>{if(values[k]!=='' )p[k]=values[k]}));
    renderLote();
    toast('Alterações aplicadas aos selecionados!');
});

$('btnCancelarLote').addEventListener('click',async()=>{
    const ids=loteDrafts.map(p=>p.id),fd=new FormData();
    ids.forEach(id=>fd.append('ids[]',id));
    fd.append('csrf_token',CSRF);
    try{
        await fetch('produto_lote_api.php?acao=cancelar',{method:'POST',body:fd,credentials:'same-origin'})
    }finally{
        resetLote()
    }
});

$('btnPublicarLote').addEventListener('click',async()=>{
    if(!loteDrafts.length)return;
    const invalid=loteDrafts.find(p=>!String(p.nome).trim()||!String(p.codigo).trim()||Number(p.preco)<=0);
    if(invalid){toast('Preencha nome, código e preço de todos os produtos','err');return}

    const btn=$('btnPublicarLote');
    btn.disabled=true;
    btn.innerHTML='<span class="material-symbols-outlined">hourglass_empty</span>Publicando…';

    const fd=new FormData();
    fd.append('itens',JSON.stringify(loteDrafts));
    fd.append('csrf_token',CSRF);

    try{
        const r=await fetch('produto_lote_api.php?acao=publicar',{method:'POST',body:fd,credentials:'same-origin'});
        const j=await r.json();
        if(!j.ok)throw new Error(typeof j.error==='string'&&j.error.trim()?j.error:'Não foi possível publicar o lote.');
        const publicados=Number(j.publicados)||loteDrafts.length;
        toast(`${publicados} produtos publicados!`);
        resetLote();
        loteCard.style.display='none';
        await loadAll()
    }catch(e){
        toast(e?.message||'Erro ao publicar o lote','err')
    }finally{
        btn.disabled=false;
        btn.innerHTML='<span class="material-symbols-outlined">publish</span>Publicar todos'
    }
});

window.onload=loadAll;
</script>
</body>
</html>

