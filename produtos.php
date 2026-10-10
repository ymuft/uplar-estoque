<?php
session_start();
require 'db.php';
require 'helpers.php';
require_once __DIR__ . '/includes/tracking_runtime.php';
require_once __DIR__ . '/includes/business_profile_runtime.php';
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}
get_csrf_token();
$user_id  = $_SESSION['user_id'];
$username = $_SESSION['username'];
$pdo = get_pdo();
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
@keyframes slideRight{from{transform:translateX(-12px);opacity:0}to{transform:translateX(0);opacity:1}}
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

/* MODAL MULTISTEP */
.modal{display:none;position:fixed;inset:0;background:rgba(15,28,46,.55);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(5px)}
.mbox{background:var(--card);border-radius:18px;width:90%;max-width:560px;box-shadow:0 24px 64px rgba(0,0,0,.2);animation:popIn .28s cubic-bezier(.22,1,.36,1) both;overflow:hidden}
.size-stock-dialog{width:min(500px,calc(100vw - 2rem));max-height:min(680px,calc(100vh - 2rem));display:flex;flex-direction:column;background:var(--card);border-radius:18px;box-shadow:0 24px 64px rgba(0,0,0,.22);overflow:hidden;animation:popIn .24s cubic-bezier(.22,1,.36,1) both}.size-stock-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1.2rem 1.25rem 1rem;border-bottom:1px solid var(--border)}.size-stock-head h2{margin:0;font-size:1.02rem;font-weight:750}.size-stock-head p{margin:.28rem 0 0;font-size:.86rem;font-weight:650;color:var(--ink)}.size-stock-head span#sizeStockCurrent{display:block;margin-top:.2rem;color:var(--muted);font-size:.72rem}.size-stock-close{width:34px;height:34px;flex:0 0 34px;display:grid;place-items:center;border:1px solid var(--border);border-radius:9px;background:var(--card);color:var(--muted);cursor:pointer;transition:all .18s}.size-stock-close:hover,.size-stock-close:focus-visible{background:var(--p-dim);border-color:var(--p);color:var(--p);outline:none}.size-stock-close .material-symbols-outlined{font-size:1.2rem}.size-stock-body{min-height:0;overflow:auto;padding:1rem 1.25rem 1.15rem}.size-stock-section-title{font-size:.76rem;font-weight:750;color:var(--ink)}.size-stock-picker{padding:.9rem;margin-bottom:1rem;border:1px solid var(--border);border-radius:12px;background:var(--bg)}.size-stock-picker p{margin:.28rem 0 .75rem;font-size:.72rem;line-height:1.45;color:var(--muted)}.size-stock-chips{display:flex;flex-wrap:wrap;gap:.42rem}.size-stock-chip{min-width:42px;padding:.42rem .55rem;border:1px solid var(--border);border-radius:8px;background:var(--card);font:700 .73rem Inter;color:var(--muted);cursor:pointer;transition:all .16s}.size-stock-chip:hover,.size-stock-chip:focus-visible{border-color:var(--p);color:var(--p);outline:none}.size-stock-chip.on{border-color:var(--p);background:var(--p-dim);color:var(--p)}.size-stock-custom{display:flex;gap:.45rem;margin-top:.7rem}.size-stock-custom input{min-width:0;flex:1;padding:.52rem .65rem;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--ink);font:600 .75rem Inter;outline:none}.size-stock-custom input:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}.size-stock-custom button{padding:.5rem .7rem;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--p);font:700 .73rem Inter;cursor:pointer}.size-stock-list{margin-top:.2rem}.size-stock-list .size-stock-section-title{margin-bottom:.5rem}.size-stock-row{display:grid;grid-template-columns:minmax(0,1fr) auto auto;align-items:center;gap:.6rem;padding:.62rem .7rem;margin-bottom:.42rem;border:1px solid var(--border);border-radius:10px;background:var(--card)}.size-stock-label{font-size:.85rem;font-weight:750;color:var(--ink)}.size-stock-stepper{display:flex;align-items:center;border:1px solid var(--border);border-radius:8px;overflow:hidden}.size-stock-stepper button{width:30px;height:30px;border:0;background:var(--bg);color:var(--p);font:700 1rem Inter;cursor:pointer}.size-stock-stepper button:hover{background:var(--p-dim)}.size-stock-stepper input{width:42px;height:30px;border:0;border-left:1px solid var(--border);border-right:1px solid var(--border);outline:0;text-align:center;background:var(--card);color:var(--ink);font:700 .78rem Inter;-moz-appearance:textfield}.size-stock-stepper input::-webkit-outer-spin-button,.size-stock-stepper input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}.size-stock-remove{width:30px;height:30px;border:0;border-radius:7px;background:transparent;color:var(--faint);font-size:1.15rem;cursor:pointer}.size-stock-remove:hover,.size-stock-remove:focus-visible{background:var(--err-dim);color:var(--err);outline:none}.size-stock-empty{padding:1rem .2rem;color:var(--muted);font-size:.76rem}.size-stock-summary{margin-top:1rem;padding:.85rem .9rem;border:1px solid var(--border);border-radius:11px;background:var(--bg)}.size-stock-summary-line{display:flex;justify-content:space-between;gap:.75rem;font-size:.76rem;color:var(--muted)}.size-stock-summary-line strong{color:var(--ink);font-size:.8rem}.size-stock-progress{height:5px;margin:.6rem 0 .45rem;border-radius:999px;overflow:hidden;background:rgba(148,163,184,.24)}.size-stock-progress span{display:block;width:0;height:100%;border-radius:inherit;background:var(--p);transition:width .18s,background .18s}.size-stock-hint{font-size:.71rem;line-height:1.4;color:var(--muted)}.size-stock-hint.attention{color:var(--warn)}.size-stock-hint.error{color:var(--err)}.size-stock-hint.success{color:var(--ok)}.size-stock-change{display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;margin-top:.75rem;padding-top:.7rem;border-top:1px solid var(--border);font-size:.66rem;color:var(--muted)}.size-stock-change span:nth-child(2){text-align:center}.size-stock-change span:last-child{text-align:right}.size-stock-change strong{display:block;margin-top:.12rem;color:var(--ink);font-size:.75rem}.size-stock-footer{display:flex;justify-content:flex-end;gap:.55rem;padding:.85rem 1.25rem calc(.85rem + env(safe-area-inset-bottom));border-top:1px solid var(--border);background:var(--card)}.size-stock-footer .pill{justify-content:center}.size-stock-footer .pill:disabled{opacity:.52;box-shadow:none;cursor:not-allowed;transform:none}
.mstep-head{padding:1.5rem 1.5rem 1rem;background:linear-gradient(135deg,var(--p) 0%,#5c8aff 100%);color:#fff;position:relative}
.mstep-head h2{font-size:1.1rem;font-weight:700;margin-bottom:.3rem}
.mstep-head p{font-size:.78rem;opacity:.8}
.mstep-close{position:absolute;top:1rem;right:1rem;background:rgba(255,255,255,.2);border:none;color:#fff;width:28px;height:28px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .18s}
.mstep-close:hover{background:rgba(255,255,255,.3)}
.mstep-close .material-symbols-outlined{font-size:1rem}
.step-dots{display:flex;gap:.45rem;margin-top:.9rem;align-items:center}
.sdot{height:4px;border-radius:2px;background:rgba(255,255,255,.3);transition:all .35s cubic-bezier(.22,1,.36,1)}
.sdot.on{background:#fff;width:24px !important}
.sdot.done{background:rgba(255,255,255,.7)}
.mstep-body{padding:1.3rem 1.5rem;max-height:calc(93vh - 200px);overflow-y:auto}
.step-panel{display:none;animation:slideRight .3s cubic-bezier(.22,1,.36,1) both}
.step-panel.on{display:block}

/* form */
.fg{display:grid;grid-template-columns:1fr 1fr;gap:.9rem}
.fw{grid-column:1/-1}
.fl{display:flex;flex-direction:column;gap:.3rem}
.fl label{font-size:.72rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
.fl input,.fl textarea,.fl select{padding:.65rem .9rem;border:1px solid var(--border);border-radius:9px;font-size:.88rem;font-family:inherit;transition:border-color .18s,box-shadow .18s;outline:none;background:var(--card);color:var(--ink)}
.fl input:focus,.fl textarea:focus,.fl select:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.fl .hint{font-size:.69rem;color:var(--faint);margin-top:.15rem}
.fl input:disabled{background:var(--bg);color:var(--faint);cursor:not-allowed}

/* preço preview */
.price-preview{background:linear-gradient(135deg,var(--p-dim),rgba(124,58,237,.06));border:1px solid rgba(59,111,245,.15);border-radius:12px;padding:1rem;margin-top:.4rem;display:flex;align-items:center;justify-content:space-between}
.pp-val{font-size:1.4rem;font-weight:700;color:var(--p)}
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
.prev{position:relative;display:inline-block;margin-top:.9rem}
.prev img{max-height:140px;border-radius:9px;border:1px solid var(--border);display:block}
.prev-rm{position:absolute;top:-8px;right:-8px;background:var(--err);color:#fff;border:none;width:22px;height:22px;border-radius:50%;font-size:.95rem;line-height:22px;text-align:center;cursor:pointer;transition:transform .18s}
.prev-rm:hover{transform:scale(1.15)}

/* review */
.review-card{background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:1rem 1.1rem;margin-bottom:.6rem}
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
.btn-next{display:flex;align-items:center;gap:.4rem;padding:.65rem 1.3rem;border:none;border-radius:9px;background:var(--p);color:#fff;font-size:.83rem;font-weight:700;cursor:pointer;transition:all .22s;box-shadow:0 4px 14px var(--p-glow);margin-left:auto}
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
.toast .material-symbols-outlined{font-size:1rem}
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
@media(max-width:600px){.bnav{display:block}.main{padding:.9rem .9rem calc(68px + env(safe-area-inset-bottom))}.top{flex-direction:column;align-items:flex-start;margin-bottom:1rem}.searchbox{min-width:0;width:100%}.greeting{font-size:1.2rem}.pills{gap:.4rem}.pill{padding:.46rem .8rem;font-size:.76rem}.kpis{grid-template-columns:1fr 1fr;gap:.6rem}.kpi-val{font-size:1.35rem}.pg{grid-template-columns:1fr 1fr}.mbox{width:96%;max-height:96vh}.fg{grid-template-columns:1fr}.size-stock-dialog{width:calc(100vw - 1rem);max-height:calc(100vh - 1rem);border-radius:15px}.size-stock-head{padding:1rem}.size-stock-body{padding:.85rem 1rem}.size-stock-footer{padding:.75rem 1rem calc(.75rem + env(safe-area-inset-bottom))}.size-stock-change{grid-template-columns:1fr 1fr}.size-stock-change span:last-child{grid-column:1/-1;text-align:left}.product-action-popover{min-width:170px}}
@media(max-width:380px){.kpis{grid-template-columns:1fr}.pg{grid-template-columns:1fr}}
.customization-choice{min-height:44px;cursor:pointer;gap:.45rem}.customization-choice.on{background:var(--p-dim);border-color:var(--p);color:var(--p)}.customization-choice input{accent-color:var(--p)}
#pCategoriaControl[hidden],#pNovaCategoriaWrap[hidden],#categoryRetry[hidden],#categoryLoadStatus[hidden]{display:none!important}
.product-optional-control{margin-top:1rem;border:1px solid var(--border);border-radius:11px;background:var(--card)}
.product-optional-control>summary{display:flex;align-items:center;justify-content:space-between;gap:.7rem;min-height:48px;padding:.75rem .9rem;color:var(--ink);font-size:.78rem;font-weight:700;cursor:pointer;list-style:none}
.product-optional-control>summary::-webkit-details-marker{display:none}
.product-optional-control>summary::after{content:'+';font-size:1.05rem;color:var(--p)}
.product-optional-control[open]>summary::after{content:'−'}
.product-optional-content{padding:0 .85rem .85rem}
.category-load-status{margin-top:.5rem;color:var(--muted);font-size:.72rem;line-height:1.45}
.category-retry{margin-top:.45rem}
.val-toggle-wrap{width:100%;font:inherit;color:inherit;text-align:left}
.val-toggle-wrap:focus-visible{outline:3px solid var(--p);outline-offset:2px}
.wizard-inline-link{border:0;background:transparent;color:var(--p);font:inherit;font-weight:700;text-decoration:underline;cursor:pointer;min-height:30px}
.wizard-quick-save{margin:1.1rem 0 .25rem auto;min-height:46px}
.wizard-quick-save[hidden]{display:none!important}
.modal[data-business-family="alimentacao"] #productNameField{order:1}
.modal[data-business-family="alimentacao"] #pCategoriaControl{order:2}
.modal[data-business-family="alimentacao"] #productDescriptionField{order:3}
.modal[data-business-family="alimentacao"] #productSkuField{order:4}
</style>
<link rel="stylesheet" href="assets/app-theme.css?v=20260819.1">
<script src="assets/app-theme.js?v=20260819.1"></script>
<link rel="stylesheet" href="assets/product-complements.css?v=20261009.5">
<script src="assets/product-complements.js?v=20261009.5"></script>
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
        <div class="searchbox">
            <span class="material-symbols-outlined">search</span>
            <input id="busca" placeholder="Buscar por nome ou SKU..." type="text">
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

    <div class="card anim d4">
        <div class="ch">
            <span class="ct">Catálogo</span>
            <div style="display:flex;align-items:center;gap:.6rem">
                <div class="view-tog">
                    <button class="vt-btn on" id="vGrid" title="Grade"><span class="material-symbols-outlined">grid_view</span></button>
                    <button class="vt-btn" id="vList" title="Lista"><span class="material-symbols-outlined">view_list</span></button>
                </div>
            </div>
        </div>

        <div class="filters">
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
        </div>

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

<div class="modal" id="modal" data-business-family="<?= htmlspecialchars((string)($businessPreset['familia'] ?? 'generico'), ENT_QUOTES, 'UTF-8') ?>">
    <div class="mbox">
        <div class="mstep-head">
            <button class="mstep-close" id="mclose"><span class="material-symbols-outlined">close</span></button>
            <h2 id="stepTitle">Novo Produto</h2>
            <p id="stepSub">Vamos começar com as informações básicas</p>
            <div class="step-dots" id="stepDots"></div>
        </div>

        <div class="mstep-body">
            <div class="step-panel on" id="s1">
                <div class="fg">
                    <div class="fl fw" id="productNameField">
                        <label>Nome do produto *</label>
                        <input id="pNome" placeholder="<?= $businessFood ? 'Ex: X-Bacon Artesanal' : 'Ex: Camisa esportiva' ?>">
                        <span class="hint">Como o produto aparecerá no catálogo</span>
                    </div>
                    <div class="fl fw" id="productSkuField">
                        <label for="pCod"><?= $businessFood ? 'Código interno / SKU (gerado se ficar vazio)' : 'Código / SKU *' ?></label>
                        <input id="pCod" placeholder="<?= $businessFood ? 'Ex: LAN-001' : 'Ex: CAM-001' ?>">
                        <span class="hint">Código único de identificação. <button type="button" id="genSku" class="wizard-inline-link">Gerar código</button></span>
                    </div>
                    <div class="fl fw" id="productDescriptionField">
                        <label><?= $businessFood ? 'Descrição e ingredientes' : 'Descrição' ?></label>
                        <textarea id="pDesc" rows="3" placeholder="<?= $businessFood ? 'Descreva ingredientes, preparo e o que acompanha o produto…' : 'Detalhe material, modelo, cores ou outras características…' ?>"></textarea>
                    </div>
                    <div class="fl fw" id="pCategoriaControl" hidden>
                        <label for="pCategoria">Categoria</label>
                        <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap">
                            <select id="pCategoria" style="flex:1;min-width:150px"><option value="">Sem categoria</option></select>
                            <button class="pill pill-outline" type="button" id="btnCriarCategoria">+ Criar categoria</button>
                        </div>
                        <div id="pNovaCategoriaWrap" hidden style="display:flex;gap:.5rem;align-items:center;margin-top:.55rem;flex-wrap:wrap">
                            <input id="pNovaCategoria" maxlength="80" placeholder="Nome da nova categoria" style="flex:1;min-width:140px">
                            <button class="pill pill-solid" id="pSalvarCategoria" type="button">Criar</button>
                            <button class="pill pill-outline" id="pCancelarCategoria" type="button">Cancelar</button>
                        </div>
                        <span class="hint">A mesma categoria será usada na vitrine, sem classificar novamente.</span>
                        <div id="categoryLoadStatus" class="category-load-status" role="status" aria-live="polite" hidden></div>
                        <button class="pill pill-outline category-retry" type="button" id="categoryRetry" hidden>Tentar carregar categorias</button>
                    </div>
                </div>
                <details class="product-optional-control" id="productImageDetails">
                    <summary>Adicionar foto ao catálogo <span style="font-weight:500;color:var(--faint)">Opcional</span></summary>
                    <div class="product-optional-content" id="productImageContent"></div>
                </details>
            </div>

            <div class="step-panel" id="s2">
                <div class="fg">
                    <div class="fl fw">
                        <label>Preço de venda (R$) *</label>
                        <input id="pPreco" type="number" step="0.01" min="0" placeholder="0,00" oninput="updatePreview()">
                        <label style="margin-top:.8rem">Preço de custo (R$)</label>
                        <input id="pCusto" type="number" step="0.01" min="0" placeholder="0,00" oninput="updatePreview()">
                        <span class="hint">A margem é calculada automaticamente sobre o preço de venda</span>
                        <div class="price-preview" id="pricePreview" style="margin-top:.6rem">
                            <div>
                                <div class="pp-lbl">Preço de venda</div>
                                <div class="pp-val" id="ppVal">R$ —</div>
                            </div>
                            <div>
                                <div class="pp-lbl">Margem</div>
                                <div class="pp-val" id="ppMargin">—</div>
                            </div>
                            <span class="material-symbols-outlined" style="font-size:2rem;color:var(--p);opacity:.5">sell</span>
                        </div>
                    </div>
                    <div class="fl fw">
                        <label id="stockQuantityLabel">Quantidade inicial em estoque</label>
                        <div class="qty-stepper">
                            <button type="button" class="qs-btn" id="qsMinus">−</button>
                            <input class="qs-inp" id="pQtd" type="number" min="0" value="1">
                            <button type="button" class="qs-btn" id="qsPlus">+</button>
                        </div>
                        <span class="hint" id="stockQuantityHint">Pode ser alterado depois a qualquer momento</span>
                    </div>
                </div>
                <div class="fl fw" id="sizeControl" style="margin-top:1rem">
                    <label>Este produto possui tamanhos?</label>
                    <div style="display:flex;gap:.5rem;margin:.45rem 0"><button type="button" class="pill pill-outline" id="sizeNo">Não</button><button type="button" class="pill pill-outline" id="sizeYes">Sim</button></div>
                    <div id="sizeEditor" hidden><strong style="font-size:.82rem">Tamanhos do produto</strong><div id="sizeQuick" style="display:flex;gap:.35rem;flex-wrap:wrap;margin:.55rem 0"></div><button type="button" class="pill pill-outline" id="sizeCustom">+ Adicionar outro tamanho</button><div id="sizeRows" style="margin-top:.7rem"></div><div id="sizeTotal" style="font-weight:700;margin-top:.65rem">Estoque total: 0 unidades</div></div>
                    <div id="sizeSuggestion" hidden style="margin-top:.7rem;padding:.7rem;border:1px dashed var(--border);border-radius:10px;font-size:.78rem"></div>
                </div>
                <fieldset class="fl fw" style="margin-top:1.1rem;border:0;padding:0">
                    <legend style="font-size:.72rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.04em">Personalização</legend>
                    <p class="hint" style="margin:.25rem 0 .55rem">Permita que o cliente escolha complementos e adicionais para este produto.</p>
                    <div style="display:flex;gap:.5rem;flex-wrap:wrap" role="radiogroup" aria-label="Personalização do produto">
                        <label class="pill pill-outline customization-choice" for="customizationNo"><input id="customizationNo" name="customization" type="radio" value="no" checked> Não</label>
                        <label class="pill pill-outline customization-choice" for="customizationYes"><input id="customizationYes" name="customization" type="radio" value="yes"> Sim</label>
                    </div>
                </fieldset>
                <details class="product-optional-control" id="productValidityDetails">
                    <summary>Validade e alertas <span style="font-weight:500;color:var(--faint)">Opcional</span></summary>
                    <div class="product-optional-content" id="productValidityContent"></div>
                </details>
                <button type="button" id="btnQuickSave" class="btn-next wizard-quick-save" hidden>Salvar produto simples</button>
            </div>

            <div class="step-panel" id="s3">
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
                            <label>Data de validade *</label>
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
            </div>

            <div class="step-panel" id="s4">
                <div class="fl">
                    <label>Foto do produto</label>
                    <div class="upload" id="dz">
                        <span class="material-symbols-outlined upload-ico">cloud_upload</span>
                        <div class="upload-txt">Clique ou arraste a imagem aqui</div>
                        <div class="upload-hint">PNG · JPG · WEBP — máx 5 MB</div>
                        <input type="file" id="fImg" accept="image/*" hidden>
                        <div class="prev" id="prevW" style="display:none">
                            <img id="prevI" alt="">
                            <button type="button" class="prev-rm" id="rmPrev">×</button>
                        </div>
                    </div>
                    <span class="hint" style="margin-top:.4rem">A foto aparece no catálogo e no painel de vendas. Opcional.</span>
                </div>
            </div>

            <div class="step-panel" id="s5">
                <div id="productComplements" class="ce"></div>
            </div>

            <div class="step-panel" id="s6">
                <div id="reviewW"></div>
            </div>
        </div>

        <div class="mstep-foot">
            <span class="step-lbl" id="stepLbl">Etapa 1 de 6</span>
            <div style="display:flex;gap:.5rem">
                <button class="btn-back" id="btnBack" style="display:none">
                    <span class="material-symbols-outlined" style="font-size:1rem">arrow_back</span>Voltar
                </button>
                <button class="btn-next" id="btnNext">
                    Próximo<span class="material-symbols-outlined">arrow_forward</span>
                </button>
            </div>
        </div>
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
    categoriasDisponiveis.forEach(c=>select.add(new Option(c.nome,String(c.id))));
    select.value=categoriaSelecionada===null?'':String(categoriaSelecionada);
    select.disabled=false;
    categoriasProntas=true;
    document.getElementById('pCategoriaControl').hidden=false;
    document.getElementById('categoryLoadStatus').hidden=true;
    document.getElementById('categoryRetry').hidden=true;
}
async function carregarCategoriasProduto(){
    if(categoriasProntas)return true;
    if(categoriasRequest)return categoriasRequest;
    categoriasRequest=(async()=>{
        try{
            const r=await fetch('catalogo_categorias_api.php?acao=listar',{credentials:'same-origin'});
            const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.error||'Não foi possível carregar as categorias.');
            categoriasDisponiveis=Array.isArray(j.categorias)?j.categorias:[];renderCategoriasProduto();return true;
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
            const r=await fetch('catalogo_categorias_api.php?acao=criar',{method:'POST',body,credentials:'same-origin'});
            const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.error||'Erro ao criar categoria.');
            categoriasDisponiveis=j.categorias||[];categoriaSelecionada=Number(j.categoria_id);renderCategoriasProduto();
            document.getElementById('pNovaCategoriaWrap').hidden=true;document.getElementById('pNovaCategoria').value='';toast('Categoria disponível no cadastro.');
        }catch(error){toast(error.message,'err');}finally{button.disabled=false;}
    }
});
document.addEventListener('change',e=>{if(e.target.id==='pCategoria')categoriaSelecionada=e.target.value?Number(e.target.value):null;});
// Contrato de sugestão visual: não controla permissões, estoque nem persistência.
const BUSINESS_CONTEXT = <?= json_encode([
    'segmento' => $businessPreset['segmento'] ?? null,
    'familia' => $businessPreset['familia'] ?? null,
    'modo_sugerido' => $businessPreset['modo_sugerido'] ?? 'padrao',
    'categorias_sugeridas' => $businessPreset['categorias_sugeridas'] ?? [],
    'prioridades_cadastro' => $businessPreset['prioridades_cadastro'] ?? [],
    'perfil_concluido' => ($businessProfile['status_configuracao'] ?? '') === 'concluida',
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;
const $ = id => document.getElementById(id);
const esc = v => String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const R = v => 'R$\u00a0'+Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
function generateProductSku(){
    const bytes=new Uint8Array(6);
    if(window.crypto?.getRandomValues)window.crypto.getRandomValues(bytes);
    else for(let i=0;i<bytes.length;i++)bytes[i]=Math.floor(Math.random()*256);
    const suffix=Array.from(bytes,b=>b.toString(16).padStart(2,'0')).join('').toUpperCase();
    return (BUSINESS_CONTEXT.familia==='alimentacao'?'ALIM':'PROD')+'-'+suffix;
}
$('genSku').addEventListener('click',()=>{
    $('pCod').value=generateProductSku();
    wizardFieldsDirty=true;
});
function updateQuickSaveVisibility(){
    const button=$('btnQuickSave');if(!button)return;
    const simple=!editId&&!customizationEnabled&&!$('sizeYes').classList.contains('on');
    button.hidden=!(simple&&activeStepId===2);
}
$('btnQuickSave').addEventListener('click',()=>saveProduct());

function compactOptionalWizardPanels(){
    const moves=[['s3','productValidityContent'],['s4','productImageContent']];
    for(const [sourceId,targetId] of moves){
        const source=$(sourceId),target=$(targetId);
        if(!source||!target)continue;
        while(source.firstChild)target.appendChild(source.firstChild);
        source.remove();
    }
}
compactOptionalWizardPanels();

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
    wrap.appendChild(el);
    setTimeout(()=>{el.style.animation='toastOut .3s forwards';setTimeout(()=>el.remove(),300)},dur);
}

function cnt(el,to,fmt){
    const s=performance.now(),dur=550;
    const t=now=>{const p=Math.min((now-s)/dur,1),e=1-Math.pow(1-p,3);el.textContent=fmt(to*e);if(p<1)requestAnimationFrame(t);else el.textContent=fmt(to)};
    requestAnimationFrame(t);
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
        renderProds();if(viewMode==='list')renderTable();
    });
});
$('sortSel').addEventListener('change',()=>{renderProds();if(viewMode==='list')renderTable()});

function updatePreview(){
    const v=parseFloat($('pPreco').value)||0;
    $('ppVal').textContent=v>0?R(v):'R$ —';
    const c=parseFloat($('pCusto').value);
    $('ppMargin').textContent=v>0&&c>0?`Lucro ${R(v-c)} · ${((v-c)/v*100).toFixed(1).replace('.',',')}%`:'Custo não informado';
}

$('qsMinus').addEventListener('click',()=>{const i=$('pQtd'),v=Math.max(0,parseInt(i.value||0)-1);i.value=v});
$('qsPlus').addEventListener('click',()=>{const i=$('pQtd'),v=parseInt(i.value||0)+1;i.value=v});

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
        $('valAlertInfo').innerHTML = '';
    }
}

function updateValAlert(){
    const d = $('pValidade').value;
    const info = $('valAlertInfo');
    if (!d) { info.innerHTML=''; return; }
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
}

const dz=$('dz');
dz.addEventListener('click',e=>{if(e.target.closest('.prev-rm'))return;$('fImg').click()});
$('fImg').addEventListener('change',e=>{if(e.target.files[0])showPrev(e.target.files[0])});
['dragover','dragenter'].forEach(ev=>dz.addEventListener(ev,e=>{e.preventDefault();dz.classList.add('on')}));
['dragleave','drop'].forEach(ev=>dz.addEventListener(ev,e=>{e.preventDefault();dz.classList.remove('on')}));
dz.addEventListener('drop',e=>{e.preventDefault();const f=e.dataTransfer.files[0];if(f){$('fImg').files=e.dataTransfer.files;showPrev(f)}});
$('rmPrev').addEventListener('click',()=>{$('fImg').value='';$('prevW').style.display='none'});
function showPrev(f){
    if(!f||!f.type.startsWith('image/'))return;
    if(f.size>5*1024*1024)return toast('Imagem muito grande (máx 5 MB)','err');
    const r=new FileReader();r.onload=e=>{$('prevI').src=e.target.result;$('prevW').style.display='block'};r.readAsDataURL(f);
}

const STEPS=[
    {id:1,title:BUSINESS_CONTEXT.familia==='alimentacao'?'Produto e cardápio':'Informações do produto',sub:BUSINESS_CONTEXT.familia==='alimentacao'?'Informe nome, categoria e o que acompanha o produto.':'Informe nome, identificação e detalhes do produto.'},
    {id:2,title:'Preço e disponibilidade',sub:BUSINESS_CONTEXT.familia==='alimentacao'?'Defina o preço, os tamanhos e a quantidade disponível.':'Defina o preço, os tamanhos e o estoque.'},
    {id:5,title:'Personalização do produto',sub:'Organize e reutilize as escolhas do cliente'},
    {id:6,title:'Confirmar e salvar',sub:'Revise os dados antes de salvar'},
];
let curStep=1,activeStepId=1,editId=null;
let sizeDraft=[];
let complementsEditor=null;
let customizationEnabled=false,customizationChoiceExplicit=false,customizationDisableConfirmed=false;
let wizardBaseline='',skipWizardLeaveWarning=false,wizardFieldsDirty=false;

function wizardSteps(){return STEPS.filter(step=>step.id!==5||customizationEnabled);}
function wizardPositionForId(id){const position=wizardSteps().findIndex(step=>step.id===id);return position<0?1:position+1;}
function activeWizardStep(){return wizardSteps()[curStep-1]||wizardSteps()[0];}
function setCustomizationEnabled(enabled,{explicit=false}={}){
    customizationEnabled=Boolean(enabled);
    if(explicit)customizationChoiceExplicit=true;
    $('customizationYes').checked=customizationEnabled;
    $('customizationNo').checked=!customizationEnabled;
    document.querySelector('label[for="customizationYes"]').classList.toggle('on',customizationEnabled);
    document.querySelector('label[for="customizationNo"]').classList.toggle('on',!customizationEnabled);
    if($('modal').style.display==='flex'){
        buildDots();
        gotoStep(wizardPositionForId(activeStepId));
    }
    updateQuickSaveVisibility();
}
function removeProductComplementsAfterConfirmation(){
    if(!complementsEditor?.groups?.length)return true;
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
    const editing=activeStepId===5&&Boolean(complementsEditor?.edit);
    $('btnNext').style.display=editing?'none':'';
    $('btnBack').style.display=editing?'none':(curStep>1?'flex':'none');
    $('stepLbl').textContent=editing?'Conclua ou cancele a edição acima':`Etapa ${curStep} de ${wizardSteps().length}`;
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
    $('stockQuantityLabel').textContent=enabled?'Estoque total dos tamanhos':(editId?'Quantidade em estoque':'Quantidade inicial em estoque');
    $('stockQuantityHint').textContent=enabled
        ?'Total calculado pelas quantidades de cada tamanho. Edite os tamanhos abaixo para ajustar.'
        :'Informe a quantidade disponível deste produto.';

    $('sizeEditor').hidden=!enabled;

    $('sizeQuick').innerHTML=COMMON_SIZES.map(s=>`
        <button type="button"
                class="pill pill-outline ${sizeDraft.some(x=>x.tamanho===s)?'on':''}"
                onclick="addSize('${s}')">${s}</button>
    `).join('');

    $('sizeRows').innerHTML=sizeDraft.map((s,i)=>`
        <div style="display:flex;gap:.5rem;align-items:center;margin:.35rem 0">
            <strong style="width:45px">${esc(s.tamanho)}</strong>
            <input type="number"
                   min="0"
                   value="${Number(s.quantidade)||0}"
                   oninput="sizeDraft[${i}].quantidade=Math.max(0,Number(this.value)||0);renderSizeTotal()"
                   style="max-width:110px">
            <button type="button"
                    class="tbl-btn del"
                    onclick="sizeDraft.splice(${i},1);renderSizeEditor()">×</button>
        </div>
    `).join('');

    renderSizeTotal();
}

function renderSizeTotal(){
    const total=sizeDraft.reduce((n,s)=>n+Math.max(0,Number(s.quantidade)||0),0);
    $('sizeTotal').textContent=`Estoque total: ${total} unidades`;
    $('pQtd').value=total;
}


function addSize(name){
    name=String(name||'').trim().toUpperCase();
    if(!name||sizeDraft.some(s=>s.tamanho===name))return;
    sizeDraft.push({tamanho:name,quantidade:0});
    $('sizeYes').classList.add('on');
    $('sizeNo').classList.remove('on');
    renderSizeEditor();
}

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
    $('sizeYes').classList.add('on');
    $('sizeNo').classList.remove('on');
    renderSizeEditor();
    updateQuickSaveVisibility();
});

$('sizeNo').addEventListener('click',()=>{
    const original=editId?estoque.find(p=>Number(p.id)===Number(editId)):null;
    if(original&&productUsesSizes(original)){
        toast('O produto já utiliza tamanhos. Ajuste as quantidades por tamanho; remover o controle requer conversão específica.','warn',5500);
        return;
    }
    sizeDraft=[];
    $('sizeNo').classList.add('on');
    $('sizeYes').classList.remove('on');
    renderSizeEditor();
    updateQuickSaveVisibility();
});

$('sizeCustom').addEventListener('click',()=>{
    const value=prompt('Informe o tamanho (ex.: 36, Único ou 3G)');
    if(value)addSize(value);
});

function buildDots(){
    const c=$('stepDots');c.innerHTML='';
    wizardSteps().forEach((_,i)=>{
        const d=document.createElement('div');
        d.className='sdot'+(i===0?' on':'');
        d.style.width=(i===0?24:9)+'px';
        c.appendChild(d);
    });
}

function gotoStep(position){
    if(activeStepId===5 && complementsEditor?.edit)complementsEditor.collect();
    const steps=wizardSteps();
    const n=Math.max(1,Math.min(Number(position)||1,steps.length));
    document.querySelectorAll('.step-panel').forEach(x=>x.classList.remove('on'));
    curStep=n;
    activeStepId=steps[n-1].id;
    $('s'+activeStepId).classList.add('on');
    const s=steps[n-1];
    $('stepTitle').textContent=editId?'Editar Produto — '+s.title:s.title;
    $('stepSub').textContent=s.sub;
    $('stepLbl').textContent=`Etapa ${n} de ${steps.length}`;
    const dots=$('stepDots').querySelectorAll('.sdot');
    dots.forEach((d,i)=>{
        d.className='sdot'+(i<n-1?' done':i===n-1?' on':'');
        d.style.width=(i===n-1?24:9)+'px';
    });
    $('btnBack').style.display=n>1?'flex':'none';
    $('btnNext').innerHTML=n===steps.length
        ?'<span class="material-symbols-outlined">save</span>Salvar'
        :'Próximo<span class="material-symbols-outlined">arrow_forward</span>';
    if(activeStepId===5 && complementsEditor){
        complementsEditor.root.dataset.productName=$('pNome').value;
        complementsEditor.root.dataset.basePrice=$('pPreco').value;
        if(complementsEditor.ready)complementsEditor.render();
    }
    if(activeStepId===6)buildReview();
    updateComplementsNavigation();
    updateQuickSaveVisibility();
}

function validateStep(stepId){
    if(stepId===1){
        if(!$('pNome').value.trim()){toast('Informe o nome do produto','err');return false}
        if(!$('pCod').value.trim()){
            if(BUSINESS_CONTEXT.familia==='alimentacao'&&!editId){
                $('pCod').value=generateProductSku();
            }else{toast('Informe o código / SKU','err');return false;}
        }
    }
    if(stepId===2){
        if(!$('pPreco').value||parseFloat($('pPreco').value)<=0){toast('Informe o preço de venda','err');return false}
        if($('sizeYes').classList.contains('on')&&!sizeDraft.length){toast('Selecione pelo menos um tamanho','err');return false}
        if(temValidade&&!$('pValidade').value){toast('Informe a data de validade','err');return false}
    }
    if(stepId===5 && customizationEnabled&&(!complementsEditor || !complementsEditor.validate())){
        toast(complementsEditor?.ready?'Conclua ou cancele o opcional em edição para avançar.':'Aguarde o carregamento dos opcionais.','err');return false;
    }
    return true;
}

function buildReview(){
    const img=$('prevI').src&&$('prevW').style.display!=='none';
    const val=$('pValidade').value;
    const dias=diasParaVencer(val);
    const st=valStatus(dias);
    const lbl=valLabel(dias);
    const ico=valIcon(st);

    const validadeHtml = temValidade && val
        ? `<span class="val-chip ${st}" style="font-size:.75rem">
               <span class="material-symbols-outlined" style="font-size:.8rem">${ico}</span>
               ${fmtDate(val)} — ${lbl}
           </span>`
        : `<span style="color:var(--faint);font-size:.8rem">Sem validade</span>`;

    $('reviewW').innerHTML=`
        ${img?`<div style="text-align:center;margin-bottom:.8rem"><img src="${$('prevI').src}" class="review-img" alt=""></div>`:''}
        <div class="review-card">
            <div class="review-row"><span class="rr-l">Nome</span><span class="rr-v">${esc($('pNome').value||'—')}</span></div>
            <div class="review-row"><span class="rr-l">Código</span><span class="rr-v">${esc($('pCod').value||'—')}</span></div>
            <div class="review-row"><span class="rr-l">Descrição</span><span class="rr-v" style="max-width:60%;text-align:right;font-size:.78rem">${esc($('pDesc').value||'—')}</span></div>
             ${categoriasProntas?'<div class="review-row"><span class="rr-l">Categoria</span><span class="rr-v">'+esc($('pCategoria').selectedOptions[0]?.textContent||'Sem categoria')+'</span></div>':''}
        </div>
        <div class="review-card">
            <div class="review-row"><span class="rr-l">Preço de venda</span><span class="rr-v" style="color:var(--p)">${R($('pPreco').value)}</span></div>
            <div class="review-row"><span class="rr-l">Preço de custo</span><span class="rr-v">${R($('pCusto').value||0)}</span></div>
            <div class="review-row"><span class="rr-l">Margem de lucro</span><span class="rr-v" style="color:var(--ok)">${(parseFloat($('pPreco').value)>0?((parseFloat($('pPreco').value)-parseFloat($('pCusto').value||0))/parseFloat($('pPreco').value)*100):0).toFixed(1).replace('.',',')}%</span></div>
            <div class="review-row"><span class="rr-l">Quantidade inicial</span><span class="rr-v">${$('pQtd').value} un.</span></div>
        </div>
        <div class="review-card">
            <div class="review-row">
                <span class="rr-l">Validade</span>
                <span class="rr-v">${validadeHtml}</span>
            </div>
        </div>
        <div class="review-card ce"><strong style="display:block;margin-bottom:12px">Personalização</strong>${customizationEnabled?(complementsEditor?.summaryHTML()||'<p class="ce-intro">Carregando opcionais…</p>'):'<p class="ce-intro">Produto sem complementos.</p>'}</div>`;
}

$('btnNovo').addEventListener('click',()=>openM());
function wizardDraftSnapshot(){
    return JSON.stringify({
        nome:$('pNome').value,codigo:$('pCod').value,descricao:$('pDesc').value,categoria:categoriaSelecionada,preco:$('pPreco').value,custo:$('pCusto').value,
        quantidade:$('pQtd').value,tamanhos:sizeDraft,validade:temValidade?$('pValidade').value:'',temValidade,
        personalizacao:customizationEnabled,imagem:$('fImg').files.length?$('fImg').files[0].name:$('prevI').src
    });
}
function wizardHasUnsavedChanges(){
    return Boolean(complementsEditor?.dirty||complementsEditor?.edit||wizardDraftSnapshot()!==wizardBaseline);
}
function closeProductModal(){
    if($('btnNext').disabled)return false;
    if(!skipWizardLeaveWarning&&wizardHasUnsavedChanges()&&!confirm('Descartar as alterações deste produto?'))return false;
    $('modal').style.display='none';
    return true;
}
$('mclose').addEventListener('click',closeProductModal);
$('modal').addEventListener('click',e=>{if(e.target===$('modal'))closeProductModal();});
window.addEventListener('beforeunload',event=>{
    if($('modal').style.display==='flex'&&!skipWizardLeaveWarning&&wizardHasUnsavedChanges()){
        event.preventDefault();event.returnValue='';
    }
});

function openM(p=null){
    if($('btnNext').disabled){toast('Aguarde o produto terminar de salvar.','warn');return;}
    editId=p?.id??null;
    const hasStructuredSizes=productUsesSizes(p);
    $('stockQuantityLabel').textContent=editId?'Quantidade em estoque':'Quantidade inicial em estoque';
    $('stockQuantityHint').textContent=hasStructuredSizes?'O total é calculado pelos tamanhos. Edite cada quantidade individualmente.':'Informe a quantidade disponível deste produto.';
    $('pNome').value=p?.nome??'';
    $('pCod').value=p?.codigo??'';
    $('pPreco').value=p?Number(p.preco).toFixed(2):'';
    $('pCusto').value=p?Number(p.preco_custo||0).toFixed(2):'';
    $('pQtd').value=p?.quantidade??1;
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

    $('fImg').value='';

    temValidade = !!(p?.validade);
    $('productValidityDetails').open=temValidade;
    $('productImageDetails').open=Boolean(p?.imagem)||(BUSINESS_CONTEXT.prioridades_cadastro||[]).includes('imagens');
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
        $('prevW').style.display='block';
    }else{
        $('prevI').src='';
        $('prevW').style.display='none';
    }

    updatePreview();
    buildDots();
    curStep=1;
    activeStepId=1;
    gotoStep(1);
    $('modal').style.display='flex';
    wizardBaseline=wizardDraftSnapshot();
}

/* Handler dedicado para edição. Evita lógica de busca espalhada pelo HTML dinâmico. */
function editProduct(id){
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
    $('stepTitle').textContent='Duplicar produto';
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
function toggleStockSize(size){const index=stockSizeDraft.findIndex(item=>item.tamanho===size);if(index>=0)stockSizeDraft.splice(index,1);else stockSizeDraft.push({tamanho:size,quantidade:0});renderSizeStock();}
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
        const response=await fetch('api.php?acao=salvar_tamanhos',{method:'POST',body:fd,credentials:'same-origin'});
        const json=await response.json();
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

$('btnBack').addEventListener('click',()=>{if(!$('btnNext').disabled)gotoStep(curStep-1);});
$('btnNext').addEventListener('click',async()=>{
    if(curStep<wizardSteps().length){
        if(!validateStep(activeStepId))return;
        gotoStep(curStep+1);
    } else {
        await saveProduct();
    }
});

async function saveProduct(){
    const btn=$('btnNext'),quick=$('btnQuickSave');
    if(btn.disabled||quick?.disabled)return;
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
    fd.append('nome',$('pNome').value.trim());
    fd.append('codigo',$('pCod').value.trim());
    fd.append('preco',$('pPreco').value.trim());
    fd.append('preco_custo',$('pCusto').value.trim() || '0');
    fd.append('quantidade',sizeDraft.length?String(sizeDraft.reduce((n,s)=>n+(Number(s.quantidade)||0),0)):$('pQtd').value.trim());
    fd.append('descricao',$('pDesc').value.trim());
    if(categoriasProntas)fd.append('categoria_id',$('pCategoria').value);
    fd.append('validade', temValidade ? $('pValidade').value : '');
    fd.append('csrf_token',CSRF);
    if($('fImg').files[0])fd.append('imagem',$('fImg').files[0]);

    btn.disabled=true;
    if(quick)quick.disabled=true;
    btn.innerHTML='<span class="material-symbols-outlined">hourglass_empty</span>Salvando…';

    try{
        const r=await fetch(`api.php?acao=${acao}`,{method:'POST',body:fd,credentials:'same-origin'});
        const j=await r.json();

        if(j.ok){
            const productId=wasEditing?existingId:Number(j.id);
            if(!Number.isInteger(productId)||productId<=0)throw new Error('O produto foi salvo, mas o servidor não informou um ID válido. Reabra o produto pela lista antes de continuar.');
            editId=productId;productPersisted=true;

            if(sizeDraft.length){
                const sf=new FormData();
                sf.append('csrf_token',CSRF);
                sf.append('produto_id',productId);
                sf.append('tamanhos',JSON.stringify(sizeDraft));

                const sr=await fetch('api.php?acao=salvar_tamanhos',{method:'POST',body:sf,credentials:'same-origin'});
                const sj=await sr.json();

                if(!sj.ok)throw new Error(sj.error||'Erro ao salvar tamanhos');
            }

            if(customizationEnabled||complementsEditor?.dirty)await complementsEditor.save(productId);
            skipWizardLeaveWarning=true;
            $('modal').style.display='none';
            toast(wasEditing?'Produto atualizado!':'Produto cadastrado!');
            await loadAll();
        } else {
            toast(j.error||'Não foi possível salvar o produto. Revise os campos e tente novamente.','err');
        }
    }catch(e){
        console.error('[Produtos] Erro ao salvar produto:',e);
        if(e.status===409&&customizationEnabled)gotoStep(wizardPositionForId(5));
        const rawMessage=String(e?.message||'');
        const safeMessage=/failed to fetch|networkerror|load failed|unexpected token|json|http 5\d\d|500/i.test(rawMessage)
            ?'O servidor não conseguiu concluir o salvamento. Verifique a conexão e tente novamente.'
            :(rawMessage||'Não foi possível concluir o salvamento. Tente novamente.');
        toast(productPersisted?'Produto salvo. '+safeMessage+' Os dados permanecem; tente salvar novamente para concluir.':safeMessage,'err');
    }finally{
        btn.disabled=false;
        if(quick)quick.disabled=false;
        btn.innerHTML='<span class="material-symbols-outlined">save</span>Salvar';
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

function applyFilters(arr){
    let f=[...arr];
    if(activeFilter==='ok')  f=f.filter(p=>Number(p.quantidade)>5);
    if(activeFilter==='low') f=f.filter(p=>Number(p.quantidade)>0&&Number(p.quantidade)<=5);
    if(activeFilter==='zero')f=f.filter(p=>Number(p.quantidade)===0);
    if(activeFilter==='exp') f=f.filter(p=>{
        const d=diasParaVencer(p.validade);
        return d!==null && d<=30;
    });
    if(activeFilter==='no-cost') f=f.filter(p=>Number(p.preco_custo||0)<=0);
    if(activeFilter==='no-image') f=f.filter(p=>!p.imagem);
    if(activeFilter==='incomplete') f=f.filter(p=>!p.imagem||Number(p.preco_custo||0)<=0||!(p.codigo||'').trim());
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
    const valorEstoque=estoque.reduce((s,p)=>s+(Math.max(0,Number(p.quantidade)||0)*Math.max(0,Number(p.preco)||0)),0);
    const disponiveis=estoque.filter(p=>Number(p.quantidade)>0).length;
    const disponibilidade=total?Math.round((disponiveis/total)*100):0;
    const low=estoque.filter(p=>Number(p.quantidade)>0&&Number(p.quantidade)<=5).length;
    const zero=estoque.filter(p=>Number(p.quantidade)<=0).length;
    const reposicao=low+zero;
    const vencidos=estoque.filter(p=>{const d=diasParaVencer(p.validade);return d!==null&&d<0}).length;
    const aVencer=estoque.filter(p=>{const d=diasParaVencer(p.validade);return d!==null&&d>=0&&d<=30}).length;
    const validadeCritica=vencidos+aVencer;

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
        <div class="kpi-hint">Preço atual × unidades disponíveis</div>
    </div>
    <div class="kpi g anim d3">
        <div class="kpi-top"><div class="kpi-ico"><span class="material-symbols-outlined">inventory</span></div><span class="kpi-tag">volume</span></div>
        <div class="kpi-val" id="kv2">0</div><div class="kpi-lbl">Unidades armazenadas</div>
        <div class="kpi-hint">${total?`${(unidades/total).toLocaleString('pt-BR',{maximumFractionDigits:1})} por produto`:'Catálogo sem produtos'}</div>
    </div>
    <div class="kpi anim d3">
        <div class="kpi-top"><div class="kpi-ico"><span class="material-symbols-outlined">check_circle</span></div><span class="kpi-tag">cobertura</span></div>
        <div class="kpi-val" id="kv3">0%</div><div class="kpi-lbl">Disponibilidade do catálogo</div>
        <div class="kpi-hint">${disponiveis} de ${total} produto(s) com saldo</div>
    </div>
    <div class="kpi r anim d3">
        <div class="kpi-top"><div class="kpi-ico"><span class="material-symbols-outlined">production_quantity_limits</span></div><span class="kpi-tag">ação</span></div>
        <div class="kpi-val" id="kv4">0</div><div class="kpi-lbl">Reposição prioritária</div>
        <div class="kpi-hint">${zero} zerado(s) · ${low} com até 5 un.</div>
    </div>
    <div class="kpi v anim d3">
        <div class="kpi-top"><div class="kpi-ico"><span class="material-symbols-outlined">event_busy</span></div><span class="kpi-tag">validade</span></div>
        <div class="kpi-val" id="kv5">0</div><div class="kpi-lbl">Validade crítica</div>
        <div class="kpi-hint">${vencidos} vencido(s) · ${aVencer} vencem em 30 dias</div>
    </div>`;

    setTimeout(()=>{
        cnt($('kv1'),valorEstoque,v=>R(v));
        cnt($('kv2'),unidades,v=>Math.round(v).toLocaleString('pt-BR'));
        cnt($('kv3'),disponibilidade,v=>Math.round(v).toLocaleString('pt-BR')+'%');
        cnt($('kv4'),reposicao,v=>Math.round(v).toLocaleString('pt-BR'));
        cnt($('kv5'),validadeCritica,v=>Math.round(v).toLocaleString('pt-BR'));
    },60);
}

function renderProds(){
    const d=$('pg'), f=applyFilters(estoque);
    if(!f.length){d.innerHTML='<div class="empty" style="grid-column:1/-1"><span class="material-symbols-outlined">inventory_2</span><p>Nenhum produto encontrado</p></div>';return}
    d.innerHTML='';
    f.forEach((p,i)=>{
        const q=Number(p.quantidade);
        const sc=q===0?'zero':q<=5?'low':'';
        const sl=q===0?'Zerado':q<=5?`${q} restantes`:`${q} em estoque`;
        const bc=q===0?'err':q<=5?'warn':'ok';
        const bl=q===0?'Zerado':q<=5?'Baixo':'OK';

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
            </div>`:'<div class="pc-validade pc-validade-empty" aria-hidden="true">Sem validade</div>';

        const hasCost=Number(p.preco_custo||0)>0;
        const costHtml=hasCost?`Custo ${R(p.preco_custo)} · Margem ${(((Number(p.preco)-Number(p.preco_custo))/Number(p.preco))*100).toFixed(1).replace('.',',')}%`:'Custo não informado · Margem indisponível';
        const c=document.createElement('div');c.className='pc';c.style.animationDelay=(i*.04)+'s';
        c.innerHTML=`
            <div class="pc-img">
                ${p.imagem?`<img src="${esc(p.imagem)}" alt="${esc(p.nome)}">`:'<span class="material-symbols-outlined">image_not_supported</span>'}
                <span class="pc-badge ${bc}">${bl}</span>
                ${valBadgeHtml}
            </div>
            <div class="pc-body">
                <div class="pc-name">${esc(p.nome)}</div>
                <div class="pc-code">Cód: ${esc(p.codigo||'—')}</div>
                <div class="pc-price">Venda ${R(p.preco)}</div>
                <div class="pc-cost">${costHtml}</div>
                <div class="pc-stock ${sc}">${sl}</div>
                ${productUsesSizes(p)?`<div class="pc-code">${(p.tamanhos||[]).filter(s=>Number(s.ativo)!==0).length} tamanhos configurados</div>`:''}
                ${valFootHtml}
                <div class="pc-acts">
                    <button class="pc-btn pc-e" type="button" onclick="editProduct(${Number(p.id)})"><span class="material-symbols-outlined">edit</span>Editar</button>
                    <button class="pc-btn pc-e pc-more" type="button" onclick="openProductActions(event,${p.id})" aria-label="Mais ações para ${esc(p.nome)}" aria-haspopup="menu" aria-expanded="false"><span class="material-symbols-outlined">more_horiz</span></button>
                </div>
            </div>`;
        d.appendChild(c);
    });
}

function renderTable(){
    const f=applyFilters(estoque), tb=$('tblBody');
    tb.innerHTML='';
    if(!f.length){tb.innerHTML=`<tr><td colspan="7" style="text-align:center;padding:3rem;color:var(--faint)">Nenhum produto encontrado</td></tr>`;return}
    f.forEach(p=>{
        const q=Number(p.quantidade);
        const bc=q===0?'err':q<=5?'warn':'ok';
        const bl=q===0?'Zerado':q<=5?'Baixo':'Disponível';
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

        const tr=document.createElement('tr');
        tr.innerHTML=`
            <td>
                <div class="tbl-cell">
                    <div class="tbl-av">${p.imagem?`<img src="${esc(p.imagem)}" alt="">`:'<span class="material-symbols-outlined">image_not_supported</span>'}</div>
                    <div><div class="tbl-name">${esc(p.nome)}</div><div class="tbl-sku">${esc(p.descricao||'').substring(0,40)}${(p.descricao||'').length>40?'…':''}</div></div>
                </div>
            </td>
            <td><strong>${esc(p.codigo||'—')}</strong></td>
            <td><strong>${R(p.preco)}</strong><br><small style="color:var(--faint)">Custo ${R(p.preco_custo||0)} · ${Number(p.preco)>0?(((Number(p.preco)-Number(p.preco_custo||0))/Number(p.preco))*100).toFixed(1).replace('.',','):'0,0'}%</small></td>
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
                    <button class="tbl-btn" onclick="openSizeStock(${p.id})" title="Ajustar estoque"><span class="material-symbols-outlined">tune</span></button>
                    <button class="tbl-btn" type="button" onclick="editProduct(${Number(p.id)})" title="Editar"><span class="material-symbols-outlined">edit</span></button>
                    <button class="tbl-btn del" onclick="delProd(${p.id},'${esc((p.nome||'').replace(/'/g,"\\'"))}')" title="Excluir"><span class="material-symbols-outlined">delete</span></button>
                </div>
            </td>`;
        tb.appendChild(tr);
    });
}

$('busca').addEventListener('input',()=>{renderProds();if(viewMode==='list')renderTable()});

async function loadAll(){
    try{
        const r=await fetch('api.php?acao=listar',{credentials:'same-origin'});
        estoque=(r.ok?await r.json():[]) ||[];
        if(!Array.isArray(estoque))estoque=[];
        renderKPIs();
        renderProds();
        if(viewMode==='list')renderTable();
    }catch(e){console.error(e);toast('Erro ao carregar produtos','err')}
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

