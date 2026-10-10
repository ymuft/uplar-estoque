<?php
// vendedores.php — Gerenciamento de Vendedores (padrão visual Uplar)
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
require 'subscription.php';
$sub = check_and_block($user_id, $pdo); // redireciona para bloqueio.php se inativo
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vendedores — Uplar</title>
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

/* ─── ANIMAÇÕES ─────────────────────────── */
@keyframes fadeUp   {from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes popIn    {from{opacity:0;transform:scale(.94)}to{opacity:1;transform:scale(1)}}
@keyframes shimmer  {0%{background-position:200% 0}100%{background-position:-200% 0}}
@keyframes slideRight{from{transform:translateX(-12px);opacity:0}to{transform:translateX(0);opacity:1}}
@keyframes toastOut {to{opacity:0;transform:translateX(20px)}}
@keyframes spin     {to{transform:rotate(360deg)}}
.anim{opacity:0;animation:fadeUp .5s cubic-bezier(.22,1,.36,1) forwards}
.d1{animation-delay:.04s}.d2{animation-delay:.10s}
.d3{animation-delay:.16s}.d4{animation-delay:.22s}
.d5{animation-delay:.28s}.d6{animation-delay:.34s}

/* ─── LAYOUT ────────────────────────────── */
.app{display:flex;min-height:100vh}

/* ─── SIDEBAR ───────────────────────────── */
.sb{
    width:248px;background:var(--card);border-right:1px solid var(--border);
    position:fixed;left:0;top:0;height:100vh;overflow-y:auto;z-index:200;
    display:flex;flex-direction:column;
    transition:transform .3s cubic-bezier(.22,1,.36,1);
}
.sb-head{padding:1.4rem 1.3rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.sb-logo-icon{font-size:2rem;color:var(--p)}
.sb-logo-txt{font-size:1.25rem;font-weight:700;letter-spacing:-.3px}
.sb-user{
    padding:1rem 1.3rem;border-bottom:1px solid var(--border);
    display:flex;align-items:center;gap:.8rem;cursor:pointer;transition:background .2s;
}
.sb-user:hover{background:var(--p-dim)}
.sb-av{
    width:36px;height:36px;border-radius:9px;flex-shrink:0;
    background:linear-gradient(135deg,var(--p),#7ca0ff);
    color:#fff;display:flex;align-items:center;justify-content:center;
    font-weight:700;font-size:.85rem;box-shadow:0 3px 10px var(--p-glow);
}
.sb-uname{font-weight:600;font-size:.85rem}
.sb-urole{font-size:.7rem;color:var(--faint);margin-top:1px}
.sb-nav{padding:.9rem .7rem;flex:1}
.sb-label{
    font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
    color:var(--faint);padding:0 .7rem;margin:.9rem 0 .4rem;
}
.sb-label:first-child{margin-top:0}
.sb-link{
    display:flex;align-items:center;gap:.7rem;
    padding:.65rem .85rem;border-radius:9px;
    color:var(--muted);font-weight:500;font-size:.845rem;
    transition:all .18s;margin-bottom:2px;
}
.sb-link:hover{background:var(--p-dim);color:var(--p)}
.sb-link.on{background:var(--p-dim);color:var(--p);font-weight:600}
.sb-link .material-symbols-outlined{font-size:1.15rem;width:20px;text-align:center;flex-shrink:0}

/* ─── MAIN ──────────────────────────────── */
.main{margin-left:248px;flex:1;padding:1.75rem 2rem;min-width:0}

/* overlay + ham */
.overlay{display:none;position:fixed;inset:0;background:rgba(15,28,46,.4);z-index:199;backdrop-filter:blur(3px)}
.overlay.on{display:block}
.ham{
    display:none;background:var(--card);border:1px solid var(--border);
    border-radius:9px;padding:.42rem;align-items:center;justify-content:center;
    box-shadow:var(--sh);color:var(--ink);
}
.ham .material-symbols-outlined{font-size:1.3rem}

/* ─── TOP BAR ───────────────────────────── */
.top{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.4rem;gap:1rem;flex-wrap:wrap}
.top-l{display:flex;align-items:center;gap:.8rem;min-width:0}
.greeting{font-size:1.45rem;font-weight:700;letter-spacing:-.4px}
.sub{color:var(--muted);font-size:.82rem;margin-top:1px}
.searchbox{
    display:flex;align-items:center;gap:.55rem;
    background:var(--card);border:1px solid var(--border);
    border-radius:10px;padding:.52rem .95rem;
    transition:border-color .2s,box-shadow .2s;min-width:210px;
}
.searchbox:focus-within{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.searchbox .material-symbols-outlined{font-size:1.05rem;color:var(--faint)}
.searchbox input{border:none;outline:none;font-size:.855rem;width:100%;background:transparent;color:var(--ink)}
.searchbox input::placeholder{color:var(--faint)}

/* ─── PILLS ─────────────────────────────── */
.pills{display:flex;gap:.5rem;margin-bottom:1.4rem;flex-wrap:wrap}
.pill{
    display:inline-flex;align-items:center;gap:.4rem;
    padding:.52rem 1rem;border-radius:50px;font-size:.8rem;
    font-weight:600;border:none;transition:all .22s;white-space:nowrap;
}
.pill .material-symbols-outlined{font-size:1rem}
.pill-solid{background:var(--p);color:#fff;box-shadow:0 3px 12px var(--p-glow)}
.pill-solid:hover{background:var(--p-dark);transform:translateY(-2px);box-shadow:0 6px 20px var(--p-glow)}
.pill-outline{background:var(--card);color:var(--muted);border:1px solid var(--border)}
.pill-outline:hover{background:var(--p-dim);border-color:var(--p);color:var(--p);transform:translateY(-2px)}

/* ─── KPI ROW ───────────────────────────── */
.kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:.85rem;margin-bottom:1.3rem}
.kpi{
    background:var(--card);border:1px solid var(--border);
    border-radius:var(--r);padding:1rem 1.1rem;
    transition:transform .22s,box-shadow .22s;position:relative;overflow:hidden;
}
.kpi::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;background:var(--p);border-radius:var(--r) var(--r) 0 0}
.kpi.g::before{background:var(--ok)}
.kpi.o::before{background:var(--warn)}
.kpi.r::before{background:var(--err)}
.kpi:hover{transform:translateY(-3px);box-shadow:var(--sh-lift)}
.kpi-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:.55rem}
.kpi-ico{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:var(--p-dim);color:var(--p)}
.kpi.g .kpi-ico{background:var(--ok-dim);color:var(--ok)}
.kpi.o .kpi-ico{background:var(--warn-dim);color:var(--warn)}
.kpi.r .kpi-ico{background:var(--err-dim);color:var(--err)}
.kpi-ico .material-symbols-outlined{font-size:1.05rem}
.kpi-tag{font-size:.67rem;font-weight:700;padding:.15rem .5rem;border-radius:20px;background:var(--p-dim);color:var(--p)}
.kpi.g .kpi-tag{background:var(--ok-dim);color:var(--ok)}
.kpi.o .kpi-tag{background:var(--warn-dim);color:var(--warn)}
.kpi.r .kpi-tag{background:var(--err-dim);color:var(--err)}
.kpi-val{font-size:1.6rem;font-weight:700;letter-spacing:-.4px;line-height:1;margin-bottom:.18rem}
.kpi-lbl{font-size:.73rem;color:var(--muted);font-weight:500}

/* ─── CARD ──────────────────────────────── */
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:1.1rem 1.2rem}
.ch{display:flex;justify-content:space-between;align-items:center;margin-bottom:.9rem;padding-bottom:.7rem;border-bottom:1px solid var(--border)}
.ct{font-size:.88rem;font-weight:700}

/* ─── VENDEDOR GRID ─────────────────────── */
.vg{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:.85rem}
.vc{
    background:var(--card);border:1px solid var(--border);
    border-radius:var(--r);padding:1.1rem;
    transition:transform .22s,box-shadow .22s;
    display:flex;flex-direction:column;gap:.6rem;
    animation:fadeUp .4s cubic-bezier(.22,1,.36,1) both;
}
.vc:hover{transform:translateY(-3px);box-shadow:var(--sh-lift)}
.vc-top{display:flex;align-items:center;gap:.75rem}
.vc-av{
    width:44px;height:44px;border-radius:50%;flex-shrink:0;
    background:linear-gradient(135deg,var(--p),#7ca0ff);
    color:#fff;display:flex;align-items:center;justify-content:center;
    font-weight:700;font-size:.95rem;overflow:hidden;
    box-shadow:0 3px 10px var(--p-glow);
}
.vc-av img{width:100%;height:100%;object-fit:cover}
.vc-name{font-weight:700;font-size:.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.vc-sub{font-size:.7rem;color:var(--faint);margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.vc-badge{
    display:inline-flex;align-items:center;gap:.25rem;
    font-size:.65rem;font-weight:700;padding:.15rem .5rem;
    border-radius:20px;align-self:flex-start;
}
.vc-badge.ok{background:var(--ok-dim);color:var(--ok)}
.vc-badge.err{background:var(--err-dim);color:var(--err)}
.vc-stats{display:flex;gap:.6rem}
.vc-stat{
    flex:1;background:var(--bg);border-radius:8px;padding:.45rem .6rem;
    text-align:center;
}
.vc-stat-v{font-size:.9rem;font-weight:700;color:var(--p)}
.vc-stat-l{font-size:.62rem;color:var(--faint);margin-top:1px}
.vc-acts{display:flex;gap:.4rem;margin-top:.1rem}
.vc-btn{
    flex:1;padding:.42rem;border:none;border-radius:7px;
    font-size:.72rem;font-weight:600;
    display:flex;align-items:center;justify-content:center;gap:.28rem;
    transition:all .18s;
}
.vc-btn .material-symbols-outlined{font-size:.9rem}
.vc-e{background:var(--p-dim);color:var(--p)}.vc-e:hover{background:rgba(59,111,245,.16)}
.vc-g{background:var(--ok-dim);color:var(--ok)}.vc-g:hover{background:rgba(16,185,129,.16)}
.vc-d{background:var(--err-dim);color:var(--err)}.vc-d:hover{background:rgba(239,68,68,.16)}

/* ─── TABLE VIEW ────────────────────────── */
.tbl-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
.tbl{width:100%;border-collapse:collapse;min-width:700px}
.tbl thead tr{background:var(--bg)}
.tbl th{padding:.7rem 1rem;text-align:left;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--faint);white-space:nowrap;border-bottom:1px solid var(--border)}
.tbl td{padding:.8rem 1rem;border-bottom:1px solid var(--border);vertical-align:middle;font-size:.83rem}
.tbl tbody tr{transition:background .15s}
.tbl tbody tr:hover{background:var(--p-dim)}
.tbl tbody tr:last-child td{border-bottom:none}
.tbl-av{width:38px;height:38px;border-radius:50%;overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--p),#7ca0ff);color:#fff;font-weight:700;font-size:.8rem}
.tbl-av img{width:100%;height:100%;object-fit:cover}
.tbl-cell{display:flex;align-items:center;gap:.7rem}
.tbl-name{font-weight:600;font-size:.83rem}
.tbl-sub{font-size:.7rem;color:var(--faint)}
.badge{font-size:.67rem;font-weight:700;padding:.15rem .5rem;border-radius:20px;white-space:nowrap}
.badge.ok{background:var(--ok-dim);color:var(--ok)}
.badge.err{background:var(--err-dim);color:var(--err)}
.tbl-acts{display:flex;gap:.35rem}
.tbl-btn{width:30px;height:30px;border:1px solid var(--border);border-radius:7px;background:transparent;color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:all .18s}
.tbl-btn .material-symbols-outlined{font-size:.95rem}
.tbl-btn:hover{background:var(--p-dim);color:var(--p);border-color:var(--p)}
.tbl-btn.del:hover{background:var(--err-dim);color:var(--err);border-color:var(--err)}
.tbl-btn.grn:hover{background:var(--ok-dim);color:var(--ok);border-color:var(--ok)}

/* ─── VIEW TOGGLE ───────────────────────── */
.view-tog{display:flex;gap:3px;background:var(--bg);border-radius:8px;padding:3px}
.vt-btn{padding:.3rem .5rem;border-radius:6px;border:none;background:transparent;color:var(--faint);cursor:pointer;transition:all .18s;display:flex;align-items:center}
.vt-btn .material-symbols-outlined{font-size:1rem}
.vt-btn.on{background:var(--card);color:var(--p);box-shadow:var(--sh)}

/* ─── FILTROS ───────────────────────────── */
.filters{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.9rem}
.filter-btn{padding:.38rem .8rem;border-radius:20px;border:1px solid var(--border);background:var(--card);color:var(--muted);font-size:.75rem;font-weight:600;cursor:pointer;transition:all .18s}
.filter-btn:hover,.filter-btn.on{background:var(--p-dim);border-color:var(--p);color:var(--p)}

/* ─── EMPTY / SKEL ──────────────────────── */
.empty{text-align:center;padding:3rem 1rem;color:var(--faint)}
.empty .material-symbols-outlined{font-size:2.5rem;display:block;margin-bottom:.5rem;opacity:.25}
.empty p{font-size:.82rem}
.sk{border-radius:7px;background:linear-gradient(90deg,var(--bg) 25%,#e8ecf2 50%,var(--bg) 75%);background-size:200% 100%;animation:shimmer 1.3s infinite}

/* ════════════════════════════════════════
   MODAL MULTISTEP — VENDEDOR
════════════════════════════════════════ */
.modal{
    display:none;position:fixed;inset:0;
    background:rgba(15,28,46,.55);z-index:1000;
    align-items:center;justify-content:center;backdrop-filter:blur(5px);
}
.mbox{
    background:var(--card);border-radius:18px;
    width:90%;max-width:520px;
    box-shadow:0 24px 64px rgba(0,0,0,.2);
    animation:popIn .28s cubic-bezier(.22,1,.36,1) both;
    overflow:hidden;
}
/* step header */
.mstep-head{
    padding:1.5rem 1.5rem 1rem;
    background:linear-gradient(135deg,var(--p) 0%,#5c8aff 100%);
    color:#fff;position:relative;
}
.mstep-head h2{font-size:1.1rem;font-weight:700;margin-bottom:.3rem}
.mstep-head p{font-size:.78rem;opacity:.8}
.mstep-close{
    position:absolute;top:1rem;right:1rem;
    background:rgba(255,255,255,.2);border:none;color:#fff;
    width:28px;height:28px;border-radius:8px;cursor:pointer;
    display:flex;align-items:center;justify-content:center;transition:background .18s;
}
.mstep-close:hover{background:rgba(255,255,255,.3)}
.mstep-close .material-symbols-outlined{font-size:1rem}
.step-dots{display:flex;gap:.45rem;margin-top:.9rem;align-items:center}
.sdot{height:4px;border-radius:2px;background:rgba(255,255,255,.3);transition:all .35s cubic-bezier(.22,1,.36,1)}
.sdot.on{background:#fff;width:24px !important}
.sdot.done{background:rgba(255,255,255,.7)}
/* step body */
.mstep-body{padding:1.3rem 1.5rem;max-height:calc(93vh - 200px);overflow-y:auto}
.step-panel{display:none;animation:slideRight .3s cubic-bezier(.22,1,.36,1) both}
.step-panel.on{display:block}
/* form fields */
.fg{display:grid;grid-template-columns:1fr 1fr;gap:.9rem}
.fw{grid-column:1/-1}
.fl{display:flex;flex-direction:column;gap:.3rem}
.fl label{font-size:.72rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
.fl input,.fl textarea,.fl select{
    padding:.65rem .9rem;border:1px solid var(--border);
    border-radius:9px;font-size:.88rem;font-family:inherit;
    transition:border-color .18s,box-shadow .18s;outline:none;background:var(--card);color:var(--ink);
}
.fl input:focus,.fl textarea:focus,.fl select:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.fl .hint{font-size:.69rem;color:var(--faint);margin-top:.15rem}
/* avatar preview (step3) */
.av-upload{
    display:flex;flex-direction:column;align-items:center;gap:.8rem;
    border:2px dashed var(--border);border-radius:12px;padding:1.6rem;
    cursor:pointer;transition:all .22s;background:var(--bg);text-align:center;
}
.av-upload:hover,.av-upload.on{border-color:var(--p);background:var(--p-dim)}
.av-preview{
    width:80px;height:80px;border-radius:50%;overflow:hidden;
    background:linear-gradient(135deg,var(--p),#7ca0ff);
    display:flex;align-items:center;justify-content:center;
    color:#fff;font-size:1.8rem;font-weight:700;
    border:3px solid var(--border);transition:border-color .2s;
    box-shadow:0 4px 16px var(--p-glow);
}
.av-preview img{width:100%;height:100%;object-fit:cover}
.av-upload-txt{font-size:.82rem;font-weight:600;color:var(--muted)}
.av-upload-hint{font-size:.7rem;color:var(--faint)}
/* toggle ativo */
.toggle-wrap{display:flex;align-items:center;gap:.75rem;padding:.7rem .9rem;border:1px solid var(--border);border-radius:9px;cursor:pointer;transition:all .18s}
.toggle-wrap:hover{border-color:var(--p);background:var(--p-dim)}
.toggle{width:38px;height:22px;border-radius:11px;background:var(--border);position:relative;transition:background .22s;flex-shrink:0}
.toggle.on{background:var(--ok)}
.toggle::after{content:'';position:absolute;top:3px;left:3px;width:16px;height:16px;border-radius:50%;background:#fff;transition:transform .22s;box-shadow:0 1px 4px rgba(0,0,0,.2)}
.toggle.on::after{transform:translateX(16px)}
/* review */
.review-card{background:var(--bg);border:1px solid var(--border);border-radius:12px;padding:1rem 1.1rem;margin-bottom:.6rem}
.review-row{display:flex;justify-content:space-between;align-items:center;padding:.3rem 0;font-size:.83rem}
.review-row:not(:last-child){border-bottom:1px dashed var(--border)}
.rr-l{color:var(--muted);font-size:.75rem}
.rr-v{font-weight:600}
.review-av{width:52px;height:52px;border-radius:50%;overflow:hidden;border:2px solid var(--p-dim);margin:0 auto .6rem;display:block}
/* step footer */
.mstep-foot{padding:1rem 1.5rem;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;gap:.7rem}
.btn-back{display:flex;align-items:center;gap:.3rem;padding:.65rem 1.1rem;border:1px solid var(--border);border-radius:9px;background:var(--card);color:var(--muted);font-size:.83rem;font-weight:600;cursor:pointer;transition:all .18s}
.btn-back:hover{background:var(--bg);color:var(--ink)}
.btn-next{display:flex;align-items:center;gap:.4rem;padding:.65rem 1.3rem;border:none;border-radius:9px;background:var(--p);color:#fff;font-size:.83rem;font-weight:700;cursor:pointer;transition:all .22s;box-shadow:0 4px 14px var(--p-glow);margin-left:auto}
.btn-next:hover{background:var(--p-dark);transform:translateY(-2px)}
.btn-next:disabled{background:var(--faint);box-shadow:none;transform:none;cursor:not-allowed}
.btn-next .material-symbols-outlined{font-size:1rem}
.step-lbl{font-size:.72rem;color:var(--faint);font-weight:500}

/* ════════════════════════════════════════
   MODAL GERENCIAR PRODUTOS
════════════════════════════════════════ */
.gbox{
    background:var(--card);border-radius:18px;
    width:92%;max-width:680px;
    box-shadow:0 24px 64px rgba(0,0,0,.2);
    animation:popIn .28s cubic-bezier(.22,1,.36,1) both;
    overflow:hidden;display:flex;flex-direction:column;max-height:92vh;
}
/* header vendedor */
.ghead{
    padding:1.3rem 1.5rem;
    background:linear-gradient(135deg,var(--ok) 0%,#34d399 100%);
    color:#fff;display:flex;align-items:center;gap:.9rem;flex-shrink:0;
}
.ghead-av{
    width:46px;height:46px;border-radius:50%;overflow:hidden;
    background:rgba(255,255,255,.25);
    display:flex;align-items:center;justify-content:center;
    font-weight:700;font-size:1.1rem;border:2px solid rgba(255,255,255,.4);flex-shrink:0;
}
.ghead-av img{width:100%;height:100%;object-fit:cover}
.ghead-info{flex:1;min-width:0}
.ghead-name{font-size:1.05rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ghead-sub{font-size:.75rem;opacity:.8;margin-top:1px}
.ghead-close{background:rgba(255,255,255,.2);border:none;color:#fff;width:28px;height:28px;border-radius:8px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .18s;flex-shrink:0}
.ghead-close:hover{background:rgba(255,255,255,.3)}
.ghead-close .material-symbols-outlined{font-size:1rem}
/* tabs */
.gtabs{display:flex;border-bottom:1px solid var(--border);flex-shrink:0;background:var(--card)}
.gtab{
    flex:1;padding:.75rem;border:none;background:transparent;
    font-size:.8rem;font-weight:600;color:var(--muted);cursor:pointer;
    transition:all .18s;border-bottom:2px solid transparent;
    display:flex;align-items:center;justify-content:center;gap:.35rem;
}
.gtab .material-symbols-outlined{font-size:.95rem}
.gtab:hover{color:var(--ok)}
.gtab.on{color:var(--ok);border-bottom-color:var(--ok)}
/* tab panels */
.gpanels{flex:1;overflow-y:auto;min-height:0}
.gpanel{display:none;padding:1rem 1.2rem;animation:slideRight .28s cubic-bezier(.22,1,.36,1) both}
.gpanel.on{display:block}
/* produto associado card */
.pa-card{
    display:flex;align-items:center;gap:.75rem;
    padding:.75rem .85rem;border:1px solid var(--border);border-radius:10px;margin-bottom:.5rem;
    transition:all .18s;background:var(--card);
}
.pa-card:hover{border-color:var(--ok);background:var(--ok-dim)}
.pa-av{
    width:38px;height:38px;border-radius:9px;flex-shrink:0;
    background:var(--bg);display:flex;align-items:center;justify-content:center;overflow:hidden;
}
.pa-av img{width:100%;height:100%;object-fit:cover}
.pa-av .material-symbols-outlined{font-size:1rem;color:var(--border)}
.pa-info{flex:1;min-width:0}
.pa-name{font-size:.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pa-sku{font-size:.68rem;color:var(--faint)}
.pa-qtd{
    font-size:.8rem;font-weight:700;color:var(--ok);
    background:var(--ok-dim);padding:.2rem .55rem;border-radius:6px;
    white-space:nowrap;flex-shrink:0;
}
.pa-adj{background:transparent;border:1px solid var(--border);border-radius:7px;padding:.28rem .5rem;cursor:pointer;color:var(--muted);display:flex;align-items:center;transition:all .18s;flex-shrink:0}
.pa-adj .material-symbols-outlined{font-size:.9rem}
.pa-adj:hover{background:var(--p-dim);color:var(--p);border-color:var(--p)}
/* alocar item */
.alo-item{
    display:flex;align-items:center;gap:.7rem;
    padding:.7rem .85rem;border:1px solid var(--border);border-radius:10px;margin-bottom:.5rem;
    transition:all .18s;background:var(--card);cursor:pointer;
}
.alo-item:hover{border-color:var(--p);background:var(--p-dim)}
.alo-item.sel{border-color:var(--p);background:var(--p-dim)}
.alo-check{
    width:20px;height:20px;border-radius:6px;border:2px solid var(--border);
    flex-shrink:0;display:flex;align-items:center;justify-content:center;
    transition:all .18s;background:var(--card);
}
.alo-item.sel .alo-check{background:var(--p);border-color:var(--p)}
.alo-item.sel .alo-check::after{content:'';color:#fff;font-size:.65rem;font-weight:700}
.alo-av{width:36px;height:36px;border-radius:8px;background:var(--bg);overflow:hidden;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.alo-av img{width:100%;height:100%;object-fit:cover}
.alo-av .material-symbols-outlined{font-size:.95rem;color:var(--border)}
.alo-info{flex:1;min-width:0}
.alo-name{font-size:.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.alo-sub{font-size:.68rem;color:var(--faint)}
.alo-qty{display:flex;align-items:center;gap:.35rem;flex-shrink:0}
.alo-qty input{width:62px;padding:.38rem .4rem;border:1px solid var(--border);border-radius:7px;font-size:.82rem;text-align:center;outline:none;transition:border-color .18s;background:var(--card)}
.alo-qty input:focus{border-color:var(--p);box-shadow:0 0 0 2px var(--p-dim)}
.alo-qty input:disabled{background:var(--bg);color:var(--faint)}
/* g-footer */
.gfoot{padding:.85rem 1.2rem;border-top:1px solid var(--border);flex-shrink:0;display:flex;justify-content:space-between;align-items:center;gap:.6rem}
.gfoot-info{font-size:.75rem;color:var(--faint)}
.gbtn-save{display:flex;align-items:center;gap:.4rem;padding:.65rem 1.3rem;border:none;border-radius:9px;background:var(--ok);color:#fff;font-size:.82rem;font-weight:700;cursor:pointer;transition:all .22s;box-shadow:0 4px 14px rgba(16,185,129,.25)}
.gbtn-save:hover{background:#059669;transform:translateY(-2px)}
.gbtn-save:disabled{background:var(--faint);box-shadow:none;transform:none;cursor:not-allowed}
.gbtn-save .material-symbols-outlined{font-size:.95rem}

/* ─── AJUSTE QTD INLINE ─────────────────── */
.adj-popup{
    position:fixed;inset:0;background:rgba(15,28,46,.5);z-index:2000;
    display:none;align-items:center;justify-content:center;backdrop-filter:blur(4px);
}
.adj-box{
    background:var(--card);border-radius:16px;padding:1.5rem;width:90%;max-width:340px;
    box-shadow:0 20px 60px rgba(0,0,0,.2);animation:popIn .25s cubic-bezier(.22,1,.36,1) both;
}
.adj-title{font-size:.95rem;font-weight:700;margin-bottom:.25rem}
.adj-sub{font-size:.75rem;color:var(--muted);margin-bottom:1rem}
.adj-stepper{display:flex;align-items:center;gap:.5rem;margin-bottom:1rem}
.adj-btn{width:36px;height:36px;border-radius:8px;border:1px solid var(--border);background:var(--bg);color:var(--ink);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.1rem;transition:all .18s;flex-shrink:0}
.adj-btn:hover{background:var(--p-dim);border-color:var(--p);color:var(--p)}
.adj-inp{flex:1;text-align:center;padding:.55rem;border:1px solid var(--border);border-radius:9px;font-size:1rem;font-weight:700;outline:none;transition:border-color .18s}
.adj-inp:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.adj-hint{font-size:.7rem;color:var(--faint);margin-bottom:1rem}
.adj-foot{display:flex;gap:.5rem}
.adj-cancel{flex:1;padding:.62rem;border:1px solid var(--border);border-radius:9px;background:var(--card);color:var(--muted);font-size:.83rem;font-weight:600;cursor:pointer;transition:all .18s}
.adj-cancel:hover{background:var(--bg)}
.adj-confirm{flex:1;padding:.62rem;border:none;border-radius:9px;background:var(--p);color:#fff;font-size:.83rem;font-weight:700;cursor:pointer;transition:all .22s;box-shadow:0 3px 10px var(--p-glow)}
.adj-confirm:hover{background:var(--p-dark)}

/* ─── CONFIG ────────────────────────────── */
.cfg{display:none;position:fixed;inset:0;background:rgba(15,28,46,.55);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(5px)}
.cbox{background:var(--card);border-radius:16px;width:90%;max-width:420px;padding:1.7rem;box-shadow:0 24px 64px rgba(0,0,0,.2);animation:popIn .28s cubic-bezier(.22,1,.36,1) both}
.mh{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem}
.mh h2{font-size:1rem;font-weight:700}
.mcl{font-size:1.7rem;cursor:pointer;color:var(--faint);line-height:1;transition:color .15s}
.mcl:hover{color:var(--ink)}
.btn-save{background:var(--p);color:#fff;border:none;padding:.75rem;border-radius:10px;font-weight:700;width:100%;font-size:.88rem;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:.45rem;transition:all .22s;cursor:pointer;box-shadow:0 4px 14px var(--p-glow);margin-top:.9rem}
.btn-save:hover{background:var(--p-dark);transform:translateY(-2px)}
.btn-save:disabled{background:var(--faint);box-shadow:none;transform:none;cursor:not-allowed}

/* ─── TOAST ─────────────────────────────── */
.toast-wrap{position:fixed;bottom:1.2rem;right:1.2rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;pointer-events:none}
.toast{background:var(--ink);color:#fff;padding:.7rem 1.1rem;border-radius:10px;font-size:.82rem;font-weight:500;display:flex;align-items:center;gap:.5rem;animation:fadeUp .35s cubic-bezier(.22,1,.36,1) both;box-shadow:0 8px 24px rgba(0,0,0,.2);pointer-events:auto}
.toast .material-symbols-outlined{font-size:1rem}
.toast.ok{background:var(--ok)}
.toast.err{background:var(--err)}

/* ─── BOTTOM NAV ────────────────────────── */
.bnav{display:none;position:fixed;bottom:0;left:0;right:0;background:var(--card);border-top:1px solid var(--border);z-index:300;padding:.35rem 0 calc(.35rem + env(safe-area-inset-bottom));box-shadow:0 -2px 16px rgba(15,28,46,.07)}
.bnav-inner{display:flex;justify-content:space-around}
.bnav-a{display:flex;flex-direction:column;align-items:center;gap:2px;padding:.35rem .6rem;border-radius:9px;color:var(--faint);font-size:.62rem;font-weight:600;transition:all .18s;flex:1}
.bnav-a .material-symbols-outlined{font-size:1.25rem}
.bnav-a.on{color:var(--p)}
.bnav-a:hover{background:var(--p-dim)}

/* ─── RESPONSIVE ────────────────────────── */
@media(max-width:1050px){.kpis{grid-template-columns:repeat(2,1fr)}}
@media(max-width:860px){
    .sb{transform:translateX(-100%)}
    .sb.open{transform:translateX(0)}
    .main{margin-left:0}
    .ham{display:flex}
}
@media(max-width:600px){
    .bnav{display:block}
    .main{padding:.9rem .9rem calc(68px + env(safe-area-inset-bottom))}
    .top{flex-direction:column;align-items:flex-start;margin-bottom:1rem}
    .searchbox{min-width:0;width:100%}
    .greeting{font-size:1.2rem}
    .pills{gap:.4rem}
    .pill{padding:.46rem .8rem;font-size:.76rem}
    .kpis{grid-template-columns:1fr 1fr;gap:.6rem}
    .kpi-val{font-size:1.35rem}
    .vg{grid-template-columns:1fr}
    .mbox,.gbox{width:96%;max-height:96vh}
    .fg{grid-template-columns:1fr}
}
@media(max-width:380px){
    .kpis{grid-template-columns:1fr}
}
</style>
<link rel="stylesheet" href="assets/app-theme.css?v=20260723">
<script src="assets/app-theme.js?v=20260723"></script>
</head>
<body>
<div class="app">

<div class="overlay" id="ov"></div>

<!-- ═══ SIDEBAR ═════════════════════════ -->
<?php require __DIR__ . '/includes/sidebar.php'; ?>

<!-- ═══ MAIN ═════════════════════════════ -->
<main class="main">

    <div class="top anim d1">
        <div class="top-l">
            <button class="ham" id="ham"><span class="material-symbols-outlined">menu</span></button>
            <div>
                <div class="greeting">Vendedores</div>
                <div class="sub">Gerencie sua equipe de vendas</div>
            </div>
        </div>
        <div class="searchbox">
            <span class="material-symbols-outlined">search</span>
            <input id="busca" placeholder="Buscar vendedor…" type="text">
        </div>
    </div>

    <div class="pills anim d2">
        <button class="pill pill-solid" id="btnNovo">
            <span class="material-symbols-outlined">person_add</span>Novo Vendedor
        </button>
        <a href="estoque.php" class="pill pill-outline"><span class="material-symbols-outlined">dashboard</span>Dashboard</a>
        <a href="produtos.php" class="pill pill-outline"><span class="material-symbols-outlined">inventory</span>Produtos</a>
        <a href="relatorio.php" class="pill pill-outline"><span class="material-symbols-outlined">bar_chart</span>Relatórios</a>
    </div>

    <!-- KPIs -->
    <div class="kpis anim d3" id="kpis">
        <?php for($i=0;$i<4;$i++):?>
        <div class="kpi"><div class="sk" style="height:1.6rem;width:55%;margin-bottom:.4rem"></div><div class="sk" style="height:.7rem;width:70%"></div></div>
        <?php endfor;?>
    </div>

    <!-- Card principal -->
    <div class="card anim d4">
        <div class="ch">
            <span class="ct">Equipe de vendas</span>
            <div style="display:flex;align-items:center;gap:.6rem">
                <div class="view-tog">
                    <button class="vt-btn on" id="vGrid"><span class="material-symbols-outlined">grid_view</span></button>
                    <button class="vt-btn" id="vList"><span class="material-symbols-outlined">view_list</span></button>
                </div>
            </div>
        </div>

        <div class="filters">
            <button class="filter-btn on" data-f="all">Todos</button>
            <button class="filter-btn" data-f="active">Ativos</button>
            <button class="filter-btn" data-f="inactive">Inativos</button>
        </div>

        <!-- grid cards -->
        <div class="vg" id="vg">
            <?php for($i=0;$i<4;$i++):?>
            <div class="vc">
                <div class="vc-top">
                    <div class="vc-av" style="background:var(--bg)"></div>
                    <div style="flex:1"><div class="sk" style="height:.8rem;margin-bottom:.35rem"></div><div class="sk" style="height:.65rem;width:60%"></div></div>
                </div>
            </div>
            <?php endfor;?>
        </div>

        <!-- table view (hidden) -->
        <div class="tbl-wrap" id="tbl" style="display:none">
            <table class="tbl">
                <thead><tr>
                    <th>Vendedor</th><th>Telefone</th><th>Email</th>
                    <th>Produtos</th><th>Status</th><th>Ações</th>
                </tr></thead>
                <tbody id="tblBody"></tbody>
            </table>
        </div>
    </div>

</main>
</div>

<!-- bottom nav -->
<nav class="bnav">
    <div class="bnav-inner">
        <a href="estoque.php"    class="bnav-a"><span class="material-symbols-outlined">dashboard</span>Início</a>
        <a href="relatorio.php"  class="bnav-a"><span class="material-symbols-outlined">bar_chart</span>Relatórios</a>
        <a href="produtos.php"   class="bnav-a"><span class="material-symbols-outlined">inventory</span>Produtos</a>
        <a href="vendedores.php" class="bnav-a on"><span class="material-symbols-outlined">group</span>Vendedores</a>
    </div>
</nav>

<!-- ═══ MODAL NOVO/EDITAR VENDEDOR (multistep) ═══ -->
<div class="modal" id="modal">
    <div class="mbox">
        <div class="mstep-head">
            <button class="mstep-close" id="mclose"><span class="material-symbols-outlined">close</span></button>
            <h2 id="stepTitle">Novo Vendedor</h2>
            <p id="stepSub">Vamos começar com as informações básicas</p>
            <div class="step-dots" id="stepDots"></div>
        </div>
        <div class="mstep-body">

            <!-- S1 — dados básicos -->
            <div class="step-panel on" id="vs1">
                <div class="fg">
                    <div class="fl fw">
                        <label>Nome completo *</label>
                        <input id="vNome" placeholder="Ex: João da Silva">
                        <span class="hint">Como o vendedor será identificado no sistema</span>
                    </div>
                    <div class="fl">
                        <label>Telefone / WhatsApp</label>
                        <input id="vTel" placeholder="(99) 99999-9999">
                    </div>
                    <div class="fl">
                        <label>E-mail</label>
                        <input id="vEmail" type="email" placeholder="vendedor@email.com">
                    </div>
                    <div class="fl fw">
                        <label>Observações</label>
                        <textarea id="vObs" rows="2" placeholder="Informações adicionais…"></textarea>
                    </div>
                </div>
            </div>

            <!-- S2 — status -->
            <div class="step-panel" id="vs2">
                <p style="font-size:.82rem;color:var(--muted);margin-bottom:1rem">Defina o status inicial do vendedor no sistema.</p>
                <div class="toggle-wrap" id="toggleWrap" onclick="toggleAtivo()">
                    <div class="toggle on" id="toggleEl"></div>
                    <div>
                        <div style="font-size:.85rem;font-weight:600" id="toggleTxt">Vendedor Ativo</div>
                        <div style="font-size:.72rem;color:var(--faint)" id="toggleSub">Pode receber produtos e registrar vendas</div>
                    </div>
                </div>
                <input type="hidden" id="vAtivo" value="1">
            </div>

            <!-- S3 — foto -->
            <div class="step-panel" id="vs3">
                <div class="fl">
                    <label>Foto do vendedor</label>
                    <div class="av-upload" id="avDz">
                        <div class="av-preview" id="avPreview">
                            <span id="avInitials">?</span>
                        </div>
                        <div class="av-upload-txt">Clique ou arraste para adicionar foto</div>
                        <div class="av-upload-hint">PNG · JPG · WEBP — máx 5 MB · Opcional</div>
                        <input type="file" id="vFoto" accept="image/*" hidden>
                    </div>
                    <div id="avRemoveRow" style="text-align:center;margin-top:.5rem;display:none">
                        <button type="button" onclick="removeAvatar()" style="font-size:.72rem;color:var(--err);background:none;border:none;cursor:pointer;text-decoration:underline">Remover foto</button>
                    </div>
                </div>
            </div>

            <!-- S4 — revisão -->
            <div class="step-panel" id="vs4">
                <div id="vReviewW"></div>
            </div>

        </div>
        <div class="mstep-foot">
            <span class="step-lbl" id="stepLbl">Etapa 1 de 4</span>
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

<!-- ═══ MODAL GERENCIAR PRODUTOS ═══ -->
<div class="modal" id="modalG">
    <div class="gbox">
        <!-- header dinâmico -->
        <div class="ghead">
            <div class="ghead-av" id="gHeadAv">?</div>
            <div class="ghead-info">
                <div class="ghead-name" id="gHeadName">Vendedor</div>
                <div class="ghead-sub" id="gHeadSub">Gerenciar produtos</div>
            </div>
            <button class="ghead-close" id="gClose"><span class="material-symbols-outlined">close</span></button>
        </div>
        <!-- tabs -->
        <div class="gtabs">
            <button class="gtab on" data-tab="associados">
                <span class="material-symbols-outlined">inventory_2</span>Alocados
                <span id="gTabCount" style="background:var(--ok-dim);color:var(--ok);font-size:.62rem;font-weight:700;padding:.1rem .4rem;border-radius:10px;margin-left:.25rem">0</span>
            </button>
            <button class="gtab" data-tab="alocar">
                <span class="material-symbols-outlined">add_circle</span>Alocar Estoque
            </button>
        </div>
        <!-- panels -->
        <div class="gpanels">
            <!-- tab: associados -->
            <div class="gpanel on" id="gpAssociados">
                <div style="margin-bottom:.7rem">
                    <div class="searchbox" style="background:var(--bg)">
                        <span class="material-symbols-outlined">search</span>
                        <input id="gBuscaAssoc" placeholder="Filtrar produtos alocados…" type="text">
                    </div>
                </div>
                <div id="gListAssoc">
                    <div class="sk" style="height:3rem;border-radius:10px;margin-bottom:.5rem"></div>
                    <div class="sk" style="height:3rem;border-radius:10px;margin-bottom:.5rem"></div>
                </div>
            </div>
            <!-- tab: alocar -->
            <div class="gpanel" id="gpAlocar">
                <div style="margin-bottom:.7rem">
                    <div class="searchbox" style="background:var(--bg)">
                        <span class="material-symbols-outlined">search</span>
                        <input id="gBuscaAlocar" placeholder="Buscar produto do estoque…" type="text">
                    </div>
                </div>
                <div id="gListAlocar">
                    <div class="sk" style="height:3rem;border-radius:10px;margin-bottom:.5rem"></div>
                    <div class="sk" style="height:3rem;border-radius:10px;margin-bottom:.5rem"></div>
                </div>
            </div>
        </div>
        <!-- footer -->
        <div class="gfoot">
            <span class="gfoot-info" id="gFootInfo">Selecione produtos para alocar</span>
            <button class="gbtn-save" id="gBtnSave">
                <span class="material-symbols-outlined">save</span>Salvar alocações
            </button>
        </div>
    </div>
</div>

<!-- ═══ POPUP AJUSTE QTD ═══ -->
<div class="adj-popup" id="adjPopup">
    <div class="adj-box">
        <div class="adj-title" id="adjTitle">Ajustar quantidade</div>
        <div class="adj-sub" id="adjSub">Produto</div>
        <div class="adj-stepper">
            <button class="adj-btn" id="adjMinus">−</button>
            <input class="adj-inp" id="adjInp" type="number" min="0" value="0">
            <button class="adj-btn" id="adjPlus">+</button>
        </div>
        <div class="adj-hint" id="adjHint">0 = remove a alocação deste produto</div>
        <div class="adj-foot">
            <button class="adj-cancel" id="adjCancel">Cancelar</button>
            <button class="adj-confirm" id="adjConfirm">Confirmar</button>
        </div>
    </div>
</div>

<!-- CONFIG -->
<div class="cfg" id="cfg">
    <div class="cbox">
        <div class="mh"><h2>Configurações da conta</h2><span class="mcl" id="cfgClose">close</span></div>
        <div class="fl">
            <label>WhatsApp para vendas</label>
            <input id="waNum" placeholder="5599999999999" value="<?= htmlspecialchars($whatsapp_number) ?>">
            <span class="hint">DDI + DDD + número (apenas números)</span>
        </div>
        <button class="btn-save" id="btnWa"><span class="material-symbols-outlined">save</span>Salvar número</button>
    </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
const CSRF = <?= json_encode(get_csrf_token()) ?>;
const $  = id => document.getElementById(id);
const esc = v => String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const R  = v => 'R$\u00a0'+Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
const ini = s => (s||'??').trim().slice(0,2).toUpperCase();

/* ── toast ── */
function toast(msg, type='ok', dur=3200){
    const wrap=$('toastWrap'), el=document.createElement('div');
    el.className='toast '+(type==='err'?'err':'ok');
    el.innerHTML=`<span class="material-symbols-outlined">${type==='err'?'error':'check_circle'}</span>${esc(msg)}`;
    wrap.appendChild(el);
    setTimeout(()=>{el.style.animation='toastOut .3s forwards';setTimeout(()=>el.remove(),300)},dur);
}

/* ── counter ── */
function cnt(el,to,fmt){
    const s=performance.now(),dur=550;
    const t=now=>{const p=Math.min((now-s)/dur,1),e=1-Math.pow(1-p,3);el.textContent=fmt(to*e);if(p<1)requestAnimationFrame(t);else el.textContent=fmt(to)};
    requestAnimationFrame(t);
}

/* ── sidebar ── */
const sb=$('sb'),ov=$('ov');
$('ham').addEventListener('click',()=>{sb.classList.add('open');ov.classList.add('on');document.body.style.overflow='hidden'});
ov.addEventListener('click',()=>{sb.classList.remove('open');ov.classList.remove('on');document.body.style.overflow=''});
sb.querySelectorAll('.sb-link').forEach(l=>l.addEventListener('click',()=>{if(window.innerWidth<=860){sb.classList.remove('open');ov.classList.remove('on');document.body.style.overflow=''}}));

/* ── config ── */
$('trigCfg').addEventListener('click',()=>openSidebarProfile());
$('cfgClose').addEventListener('click',()=>$('cfg').style.display='none');
$('cfg').addEventListener('click',e=>{if(e.target===$('cfg'))$('cfg').style.display='none'});
$('btnWa').addEventListener('click',async()=>{
    const btn=$('btnWa'),n=$('waNum').value.replace(/\D/g,'');
    if(!n||n.length<10)return toast('Número inválido','err');
    btn.disabled=true;btn.innerHTML='<span class="material-symbols-outlined">hourglass_empty</span>Salvando…';
    const fd=new FormData();fd.append('whatsapp_number',n);fd.append('csrf_token',CSRF);
    const r=await fetch('api.php?acao=save_whatsapp',{method:'POST',body:fd,credentials:'same-origin'});
    const j=await r.json();
    j.ok?(toast('Número salvo!'),$('cfg').style.display='none'):toast(j.error||'Erro','err');
    btn.disabled=false;btn.innerHTML='<span class="material-symbols-outlined">save</span>Salvar número';
});

/* ── view toggle ── */
let viewMode='grid';
$('vGrid').addEventListener('click',()=>setView('grid'));
$('vList').addEventListener('click',()=>setView('list'));
function setView(v){
    viewMode=v;
    $('vGrid').classList.toggle('on',v==='grid');
    $('vList').classList.toggle('on',v==='list');
    $('vg').style.display=v==='grid'?'grid':'none';
    $('tbl').style.display=v==='list'?'block':'none';
    if(v==='list')renderTable();
}

/* ── filtros ── */
let activeFilter='all';
document.querySelectorAll('.filter-btn').forEach(b=>{
    b.addEventListener('click',()=>{
        document.querySelectorAll('.filter-btn').forEach(x=>x.classList.remove('on'));
        b.classList.add('on');activeFilter=b.dataset.f;
        renderVendedores();if(viewMode==='list')renderTable();
    });
});

function applyFilter(arr){
    let f=[...arr];
    if(activeFilter==='active')  f=f.filter(v=>Number(v.ativo)===1);
    if(activeFilter==='inactive')f=f.filter(v=>Number(v.ativo)!==1);
    const t=$('busca').value.toLowerCase().trim();
    if(t)f=f.filter(v=>(v.nome||'').toLowerCase().includes(t)||(v.telefone||'').toLowerCase().includes(t)||(v.email||'').toLowerCase().includes(t));
    return f;
}

/* ══════════════════════════════
   MODAL MULTISTEP — VENDEDOR
══════════════════════════════ */
const VSTEPS=[
    {title:'Dados do vendedor',  sub:'Nome, contato e observações'},
    {title:'Status inicial',     sub:'Defina se o vendedor está ativo'},
    {title:'Foto do vendedor',   sub:'Adicione uma foto de perfil (opcional)'},
    {title:'Confirmar e salvar', sub:'Revise as informações antes de salvar'},
];
let vCurStep=1, vEditId=null, vAtivoVal=1;

function buildVDots(){
    const c=$('stepDots');c.innerHTML='';
    VSTEPS.forEach((_,i)=>{
        const d=document.createElement('div');
        d.className='sdot'+(i===0?' on':'');
        d.style.width=(i===0?24:9)+'px';
        c.appendChild(d);
    });
}

function gotoVStep(n){
    document.querySelectorAll('.step-panel[id^="vs"]').forEach(x=>x.classList.remove('on'));
    vCurStep=n;
    $('vs'+n).classList.add('on');
    const s=VSTEPS[n-1];
    $('stepTitle').textContent=vEditId?'Editar Vendedor — '+s.title:s.title;
    $('stepSub').textContent=s.sub;
    $('stepLbl').textContent=`Etapa ${n} de ${VSTEPS.length}`;
    const dots=$('stepDots').querySelectorAll('.sdot');
    dots.forEach((d,i)=>{
        d.className='sdot'+(i<n-1?' done':i===n-1?' on':'');
        d.style.width=(i===n-1?24:9)+'px';
    });
    $('btnBack').style.display=n>1?'flex':'none';
    $('btnNext').innerHTML=n===VSTEPS.length
        ?'<span class="material-symbols-outlined">save</span>Salvar'
        :'Próximo<span class="material-symbols-outlined">arrow_forward</span>';
    // atualiza initials no avatar preview
    const nm=$('vNome').value.trim()||'?';
    $('avInitials').textContent=ini(nm);
    if(n===4)buildVReview();
}

function validateVStep(n){
    if(n===1){if(!$('vNome').value.trim()){toast('Informe o nome do vendedor','err');return false}}
    return true;
}

function buildVReview(){
    const hasFoto=$('vFoto').files[0]||($('avPreview').querySelector('img'));
    const fotoSrc=hasFoto instanceof File
        ? URL.createObjectURL(hasFoto)
        : ($('avPreview').querySelector('img')?.src||null);
    $('vReviewW').innerHTML=`
        <div style="text-align:center;margin-bottom:.8rem">
            ${fotoSrc
                ?`<img src="${esc(fotoSrc)}" class="review-av" style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:2px solid var(--p-dim);display:inline-block">`
                :`<div style="width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--p),#7ca0ff);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:1.1rem">${ini($('vNome').value)}</div>`
            }
        </div>
        <div class="review-card">
            <div class="review-row"><span class="rr-l">Nome</span><span class="rr-v">${esc($('vNome').value||'—')}</span></div>
            <div class="review-row"><span class="rr-l">Telefone</span><span class="rr-v">${esc($('vTel').value||'—')}</span></div>
            <div class="review-row"><span class="rr-l">E-mail</span><span class="rr-v">${esc($('vEmail').value||'—')}</span></div>
            <div class="review-row"><span class="rr-l">Observações</span><span class="rr-v" style="max-width:60%;text-align:right;font-size:.76rem">${esc($('vObs').value||'—')}</span></div>
        </div>
        <div class="review-card">
            <div class="review-row"><span class="rr-l">Status</span>
                <span class="rr-v" style="color:${vAtivoVal?'var(--ok)':'var(--err)'}">${vAtivoVal?' Ativo':' Inativo'}</span>
            </div>
        </div>`;
}

/* toggle ativo */
function toggleAtivo(){
    vAtivoVal=vAtivoVal?0:1;
    $('vAtivo').value=vAtivoVal;
    $('toggleEl').classList.toggle('on',!!vAtivoVal);
    $('toggleTxt').textContent=vAtivoVal?'Vendedor Ativo':'Vendedor Inativo';
    $('toggleSub').textContent=vAtivoVal?'Pode receber produtos e registrar vendas':'Não aparece nas listagens de venda';
}

/* avatar upload */
$('avDz').addEventListener('click',e=>{if(e.target.closest('button'))return;$('vFoto').click()});
$('vFoto').addEventListener('change',e=>{
    const f=e.target.files[0];if(!f)return;
    if(f.size>5*1024*1024)return toast('Foto muito grande (máx 5 MB)','err');
    const r=new FileReader();
    r.onload=ev=>{
        $('avPreview').innerHTML=`<img src="${ev.target.result}" alt="">`;
        $('avRemoveRow').style.display='block';
        $('avDz').classList.add('on');
    };
    r.readAsDataURL(f);
});
['dragover','dragenter'].forEach(ev=>$('avDz').addEventListener(ev,e=>{e.preventDefault();$('avDz').classList.add('on')}));
['dragleave','drop'].forEach(ev=>$('avDz').addEventListener(ev,e=>{e.preventDefault();$('avDz').classList.remove('on')}));
$('avDz').addEventListener('drop',e=>{e.preventDefault();const f=e.dataTransfer.files[0];if(f){$('vFoto').files=e.dataTransfer.files;$('vFoto').dispatchEvent(new Event('change'))}});

function removeAvatar(){
    $('vFoto').value='';
    const nm=$('vNome').value.trim()||'?';
    $('avPreview').innerHTML=`<span id="avInitials">${ini(nm)}</span>`;
    $('avRemoveRow').style.display='none';
    $('avDz').classList.remove('on');
}

/* abrir modal */
$('btnNovo').addEventListener('click',()=>openVM());
$('mclose').addEventListener('click',()=>$('modal').style.display='none');
$('modal').addEventListener('click',e=>{if(e.target===$('modal'))$('modal').style.display='none'});

function openVM(v=null){
    vEditId=v?.id??null;
    vAtivoVal=v?Number(v.ativo??1):1;
    $('vNome').value=v?.nome??'';
    $('vTel').value=v?.telefone??'';
    $('vEmail').value=v?.email??'';
    $('vObs').value=v?.observacao??'';
    $('vAtivo').value=vAtivoVal;
    $('toggleEl').classList.toggle('on',!!vAtivoVal);
    $('toggleTxt').textContent=vAtivoVal?'Vendedor Ativo':'Vendedor Inativo';
    $('toggleSub').textContent=vAtivoVal?'Pode receber produtos e registrar vendas':'Não aparece nas listagens de venda';
    $('vFoto').value='';
    const nm=v?.nome||'?';
    if(v?.foto){
        $('avPreview').innerHTML=`<img src="${esc(v.foto)}" alt="">`;
        $('avRemoveRow').style.display='block';
        $('avDz').classList.add('on');
    } else {
        $('avPreview').innerHTML=`<span id="avInitials">${ini(nm)}</span>`;
        $('avRemoveRow').style.display='none';
        $('avDz').classList.remove('on');
    }
    buildVDots();
    vCurStep=1;
    gotoVStep(1);
    $('modal').style.display='flex';
}

$('btnBack').addEventListener('click',()=>gotoVStep(vCurStep-1));
$('btnNext').addEventListener('click',async()=>{
    if(vCurStep<VSTEPS.length){if(!validateVStep(vCurStep))return;gotoVStep(vCurStep+1);}
    else await saveVendedor();
});

async function saveVendedor(){
    const btn=$('btnNext');
    const acao=vEditId?'editar_vendedor':'adicionar_vendedor';
    const fd=new FormData();
    fd.append('id',vEditId??'');
    fd.append('nome',$('vNome').value.trim());
    fd.append('telefone',$('vTel').value.trim());
    fd.append('email',$('vEmail').value.trim());
    fd.append('observacao',$('vObs').value.trim());
    fd.append('ativo',$('vAtivo').value);
    fd.append('csrf_token',CSRF);
    if($('vFoto').files[0])fd.append('foto',$('vFoto').files[0]);
    btn.disabled=true;btn.innerHTML='<span class="material-symbols-outlined">hourglass_empty</span>Salvando…';
    try{
        const r=await fetch(`api.php?acao=${acao}`,{method:'POST',body:fd,credentials:'same-origin'});
        const j=await r.json();
        if(j.ok){
            $('modal').style.display='none';
            toast(vEditId?'Vendedor atualizado!':'Vendedor cadastrado!');
            await loadAll();
        } else toast(j.error||'Erro ao salvar','err');
    }catch(e){toast('Erro de conexão','err')}
    btn.disabled=false;
    btn.innerHTML='<span class="material-symbols-outlined">save</span>Salvar';
}

async function delVendedor(id,nome){
    if(!confirm(`Excluir "${nome}"?`))return;
    const fd=new FormData();fd.append('id',id);fd.append('csrf_token',CSRF);
    const r=await fetch('api.php?acao=deletar_vendedor',{method:'POST',body:fd,credentials:'same-origin'});
    const j=await r.json();
    j.ok?(toast('Vendedor excluído!'),loadAll()):toast(j.error||'Erro','err');
}

/* ══════════════════════════════
   MODAL GERENCIAR PRODUTOS
══════════════════════════════ */
let gVendId=null, gVendNome='', gAssociados=[], gTodosProd=[], gSelecionados={};

// tabs
document.querySelectorAll('.gtab').forEach(t=>{
    t.addEventListener('click',()=>{
        document.querySelectorAll('.gtab').forEach(x=>x.classList.remove('on'));
        document.querySelectorAll('.gpanel').forEach(x=>x.classList.remove('on'));
        t.classList.add('on');
        $('gp'+t.dataset.tab.charAt(0).toUpperCase()+t.dataset.tab.slice(1)).classList.add('on');
    });
});

$('gClose').addEventListener('click',()=>$('modalG').style.display='none');
$('modalG').addEventListener('click',e=>{if(e.target===$('modalG'))$('modalG').style.display='none'});

async function openGerenciar(vendId,vendNome,vendFoto){
    gVendId=vendId; gVendNome=vendNome; gSelecionados={};
    // set header
    const av=$('gHeadAv');
    if(vendFoto){av.innerHTML=`<img src="${esc(vendFoto)}" alt="" style="width:100%;height:100%;object-fit:cover;border-radius:50%">`}
    else{av.textContent=ini(vendNome)}
    $('gHeadName').textContent=vendNome;
    $('gHeadSub').textContent='Gerencie os produtos alocados';
    // reset tabs
    document.querySelectorAll('.gtab').forEach((t,i)=>t.classList.toggle('on',i===0));
    document.querySelectorAll('.gpanel').forEach((p,i)=>p.classList.toggle('on',i===0));
    $('modalG').style.display='flex';
    // load
    await loadGData();
}

async function loadGData(){
    $('gListAssoc').innerHTML='<div class="sk" style="height:3rem;border-radius:10px;margin-bottom:.5rem"></div><div class="sk" style="height:3rem;border-radius:10px"></div>';
    $('gListAlocar').innerHTML='<div class="sk" style="height:3rem;border-radius:10px;margin-bottom:.5rem"></div><div class="sk" style="height:3rem;border-radius:10px"></div>';
    try{
        const [rAssoc,rProd]=await Promise.all([
            fetch(`api.php?acao=listar_produtos_vendedor&vendedor_id=${gVendId}`,{credentials:'same-origin'}),
            fetch('api.php?acao=listar',{credentials:'same-origin'}),
        ]);
        gAssociados=(rAssoc.ok?await rAssoc.json():[])||[];
        gTodosProd=(rProd.ok?await rProd.json():[])||[];
        renderGAssociados();
        renderGAlocar();
        $('gTabCount').textContent=gAssociados.length;
    }catch(e){toast('Erro ao carregar produtos','err')}
}

function renderGAssociados(termo=''){
    const d=$('gListAssoc');
    let list=gAssociados.filter(p=>(p.nome||'').toLowerCase().includes(termo.toLowerCase()));
    if(!list.length){
        d.innerHTML='<div class="empty"><span class="material-symbols-outlined">inventory_2</span><p>Nenhum produto alocado</p></div>';
        $('gFootInfo').textContent='Nenhum produto alocado ainda';
        return;
    }
    $('gFootInfo').textContent=`${list.length} produto(s) alocado(s)`;
    d.innerHTML='';
    list.forEach(p=>{
        const qtd=Number(p.quantidade_alocada||0);
        // find image from gTodosProd
        const full=gTodosProd.find(x=>x.id==p.produto_id);
        const img=full?.imagem||null;
        const el=document.createElement('div');el.className='pa-card';
        el.innerHTML=`
            <div class="pa-av">${img?`<img src="${esc(img)}" alt="">`:'<span class="material-symbols-outlined">inventory</span>'}</div>
            <div class="pa-info">
                <div class="pa-name">${esc(p.nome)}</div>
                <div class="pa-sku">Cód: ${esc(p.codigo||'—')} · ${R(p.preco)}</div>
            </div>
            <span class="pa-qtd">${qtd} un.</span>
            <button class="pa-adj" onclick="openAdj('${esc(p.nome)}',${p.produto_id},${qtd})" title="Ajustar quantidade">
                <span class="material-symbols-outlined">edit</span>
            </button>`;
        d.appendChild(el);
    });
}

function renderGAlocar(termo=''){
    const d=$('gListAlocar');
    const assocIds=new Set(gAssociados.map(a=>String(a.produto_id)));
    let list=gTodosProd.filter(p=>Number(p.quantidade)>0&&!assocIds.has(String(p.id)));
    if(termo)list=list.filter(p=>(p.nome||'').toLowerCase().includes(termo.toLowerCase())||(p.codigo||'').toLowerCase().includes(termo.toLowerCase()));
    if(!list.length){
        d.innerHTML='<div class="empty"><span class="material-symbols-outlined">check_circle</span><p>Todos os produtos já estão alocados ou sem estoque</p></div>';
        return;
    }
    d.innerHTML='';
    list.forEach((p,i)=>{
        const sel=!!gSelecionados[p.id];
        const qtd=gSelecionados[p.id]||1;
        const el=document.createElement('div');
        el.className='alo-item'+(sel?' sel':'');
        el.dataset.pid=p.id;
        el.innerHTML=`
            <div class="alo-check"></div>
            <div class="alo-av">${p.imagem?`<img src="${esc(p.imagem)}" alt="">`:'<span class="material-symbols-outlined">inventory</span>'}</div>
            <div class="alo-info">
                <div class="alo-name">${esc(p.nome)}</div>
                <div class="alo-sub">Cód: ${esc(p.codigo||'—')} · ${R(p.preco)} · ${p.quantidade} disp.</div>
            </div>
            <div class="alo-qty">
                <input type="number" min="1" max="${p.quantidade}" value="${qtd}" ${sel?'':'disabled'}
                    onclick="event.stopPropagation()"
                    oninput="gSelecionados[${p.id}]=Math.min(Math.max(parseInt(this.value)||1,1),${p.quantidade});this.value=gSelecionados[${p.id}]">
            </div>`;
        el.addEventListener('click',e=>{
            if(e.target.tagName==='INPUT')return;
            const inp=el.querySelector('input');
            if(sel){delete gSelecionados[p.id];el.classList.remove('sel');el.querySelector('.alo-check').innerHTML='';inp.disabled=true;}
            else{gSelecionados[p.id]=parseInt(inp.value)||1;el.classList.add('sel');el.querySelector('.alo-check').innerHTML='';el.querySelector('.alo-check').innerHTML='<span style="color:#fff;font-size:.65rem;font-weight:700"></span>';inp.disabled=false;}
            updateGFooter();
        });
        d.appendChild(el);
    });
}

function updateGFooter(){
    const n=Object.keys(gSelecionados).length;
    $('gFootInfo').textContent=n>0?`${n} produto(s) selecionado(s) para alocar`:'Selecione produtos para alocar';
}

$('gBuscaAssoc').addEventListener('input',e=>renderGAssociados(e.target.value));
$('gBuscaAlocar').addEventListener('input',e=>renderGAlocar(e.target.value));

$('gBtnSave').addEventListener('click',async()=>{
    const ids=Object.keys(gSelecionados);
    if(!ids.length)return toast('Selecione ao menos um produto','err');
    const btn=$('gBtnSave');
    btn.disabled=true;btn.innerHTML='<span class="material-symbols-outlined">hourglass_empty</span>Salvando…';
    let ok=0,err=0;
    for(const pid of ids){
        const fd=new FormData();
        fd.append('csrf_token',CSRF);fd.append('vendedor_id',gVendId);
        fd.append('produto_id',pid);fd.append('quantidade',gSelecionados[pid]);
        try{
            const r=await fetch('api.php?acao=alocar_produto_vendedor',{method:'POST',body:fd,credentials:'same-origin'});
            const j=await r.json();
            j.ok?ok++:err++;
        }catch{err++}
    }
    btn.disabled=false;btn.innerHTML='<span class="material-symbols-outlined">save</span>Salvar alocações';
    if(ok)toast(`${ok} produto(s) alocado(s)!`);
    if(err)toast(`${err} erro(s) ao alocar`,'err');
    gSelecionados={};
    await loadGData();
    // switch to associados tab
    document.querySelectorAll('.gtab').forEach((t,i)=>t.classList.toggle('on',i===0));
    document.querySelectorAll('.gpanel').forEach((p,i)=>p.classList.toggle('on',i===0));
    loadAll();
});

/* ─── POPUP AJUSTE ── */
let adjData=null;
function openAdj(nome,prodId,qtdAtual){
    adjData={prodId,qtdAtual};
    $('adjTitle').textContent='Ajustar quantidade alocada';
    $('adjSub').textContent=nome;
    $('adjInp').value=qtdAtual;
    $('adjHint').textContent='Digite 0 para remover a alocação deste produto';
    $('adjPopup').style.display='flex';
}
$('adjMinus').addEventListener('click',()=>{const v=Math.max(0,parseInt($('adjInp').value||0)-1);$('adjInp').value=v});
$('adjPlus').addEventListener('click',()=>{$('adjInp').value=parseInt($('adjInp').value||0)+1});
$('adjCancel').addEventListener('click',()=>$('adjPopup').style.display='none');
$('adjPopup').addEventListener('click',e=>{if(e.target===$('adjPopup'))$('adjPopup').style.display='none'});
$('adjConfirm').addEventListener('click',async()=>{
    if(!adjData)return;
    const nq=parseInt($('adjInp').value);
    if(isNaN(nq)||nq<0)return toast('Quantidade inválida','err');
    const fd=new FormData();
    fd.append('csrf_token',CSRF);fd.append('vendedor_id',gVendId);
    fd.append('produto_id',adjData.prodId);fd.append('nova_quantidade',nq);
    const btn=$('adjConfirm');
    btn.disabled=true;btn.textContent='Salvando…';
    try{
        const r=await fetch('api.php?acao=ajustar_quantidade_vendedor',{method:'POST',body:fd,credentials:'same-origin'});
        const j=await r.json();
        if(j.ok){
            toast(nq===0?'Produto removido da alocação':'Quantidade ajustada!');
            $('adjPopup').style.display='none';
            await loadGData();
            loadAll();
        } else toast(j.error||'Erro ao ajustar','err');
    }catch{toast('Erro de conexão','err')}
    btn.disabled=false;btn.textContent='Confirmar';
});

/* ══════════════════════════════
   RENDER VENDEDORES
══════════════════════════════ */
let vendedores=[];

function renderKPIs(){
    const total=vendedores.length;
    const ativos=vendedores.filter(v=>Number(v.ativo)===1).length;
    const inativos=total-ativos;
    const totalProd=vendedores.reduce((s,v)=>s+Number(v.num_produtos||0),0);
    $('kpis').innerHTML=`
    <div class="kpi anim d3">
        <div class="kpi-top"><div class="kpi-ico"><span class="material-symbols-outlined">group</span></div><span class="kpi-tag">${total}</span></div>
        <div class="kpi-val" id="kv1">0</div><div class="kpi-lbl">Total de vendedores</div>
    </div>
    <div class="kpi g anim d3">
        <div class="kpi-top"><div class="kpi-ico"><span class="material-symbols-outlined">check_circle</span></div><span class="kpi-tag">ativos</span></div>
        <div class="kpi-val" id="kv2">0</div><div class="kpi-lbl">Vendedores ativos</div>
    </div>
    <div class="kpi r anim d3">
        <div class="kpi-top"><div class="kpi-ico"><span class="material-symbols-outlined">block</span></div><span class="kpi-tag">inativos</span></div>
        <div class="kpi-val" id="kv3">0</div><div class="kpi-lbl">Vendedores inativos</div>
    </div>
    <div class="kpi o anim d3">
        <div class="kpi-top"><div class="kpi-ico"><span class="material-symbols-outlined">inventory_2</span></div><span class="kpi-tag">alocados</span></div>
        <div class="kpi-val" id="kv4">0</div><div class="kpi-lbl">Produtos associados</div>
    </div>`;
    setTimeout(()=>{
        cnt($('kv1'),total,v=>Math.round(v).toLocaleString('pt-BR'));
        cnt($('kv2'),ativos,v=>Math.round(v).toLocaleString('pt-BR'));
        cnt($('kv3'),inativos,v=>Math.round(v).toLocaleString('pt-BR'));
        cnt($('kv4'),totalProd,v=>Math.round(v).toLocaleString('pt-BR'));
    },60);
}

function renderVendedores(){
    const f=applyFilter(vendedores),d=$('vg');
    if(!f.length){d.innerHTML='<div class="empty" style="grid-column:1/-1"><span class="material-symbols-outlined">group</span><p>Nenhum vendedor encontrado</p></div>';return}
    d.innerHTML='';
    f.forEach((v,i)=>{
        const ativo=Number(v.ativo)===1;
        const el=document.createElement('div');el.className='vc';el.style.animationDelay=(i*.04)+'s';
        el.innerHTML=`
            <div class="vc-top">
                <div class="vc-av">${v.foto?`<img src="${esc(v.foto)}" alt="">`:'<span>'+ini(v.nome)+'</span>'}</div>
                <div style="flex:1;min-width:0">
                    <div class="vc-name">${esc(v.nome||'—')}</div>
                    <div class="vc-sub">${esc(v.email||v.telefone||'Sem contato')}</div>
                </div>
            </div>
            <span class="vc-badge ${ativo?'ok':'err'}">${ativo?'● Ativo':'○ Inativo'}</span>
            <div class="vc-stats">
                <div class="vc-stat"><div class="vc-stat-v">${v.num_produtos||0}</div><div class="vc-stat-l">Produtos</div></div>
                <div class="vc-stat"><div class="vc-stat-v">${esc(v.telefone||'—')}</div><div class="vc-stat-l">Telefone</div></div>
            </div>
            <div class="vc-acts">
                <button class="vc-btn vc-g" onclick="openGerenciar(${v.id},'${esc((v.nome||'').replace(/'/g,"\\'"))}','${esc(v.foto||'')}')">
                    <span class="material-symbols-outlined">inventory_2</span>Produtos
                </button>
                <button class="vc-btn vc-e" onclick="editVendedor(${v.id})">
                    <span class="material-symbols-outlined">edit</span>
                </button>
                <button class="vc-btn vc-d" onclick="delVendedor(${v.id},'${esc((v.nome||'').replace(/'/g,"\\'"))}')">
                    <span class="material-symbols-outlined">delete</span>
                </button>
            </div>`;
        d.appendChild(el);
    });
}

function renderTable(){
    const f=applyFilter(vendedores),tb=$('tblBody');
    tb.innerHTML='';
    if(!f.length){tb.innerHTML=`<tr><td colspan="6" style="text-align:center;padding:3rem;color:var(--faint)">Nenhum vendedor encontrado</td></tr>`;return}
    f.forEach(v=>{
        const ativo=Number(v.ativo)===1;
        const tr=document.createElement('tr');
        tr.innerHTML=`
            <td>
                <div class="tbl-cell">
                    <div class="tbl-av">${v.foto?`<img src="${esc(v.foto)}" alt="">`:ini(v.nome)}</div>
                    <div><div class="tbl-name">${esc(v.nome||'—')}</div><div class="tbl-sub">${esc(v.observacao||'').substring(0,40)}</div></div>
                </div>
            </td>
            <td>${esc(v.telefone||'—')}</td>
            <td>${esc(v.email||'—')}</td>
            <td><strong>${v.num_produtos||0}</strong></td>
            <td><span class="badge ${ativo?'ok':'err'}">${ativo?'Ativo':'Inativo'}</span></td>
            <td>
                <div class="tbl-acts">
                    <button class="tbl-btn grn" onclick="openGerenciar(${v.id},'${esc((v.nome||'').replace(/'/g,"\\'"))}','${esc(v.foto||'')}')" title="Produtos"><span class="material-symbols-outlined">inventory_2</span></button>
                    <button class="tbl-btn" onclick="editVendedor(${v.id})" title="Editar"><span class="material-symbols-outlined">edit</span></button>
                    <button class="tbl-btn del" onclick="delVendedor(${v.id},'${esc((v.nome||'').replace(/'/g,"\\'"))}')" title="Excluir"><span class="material-symbols-outlined">delete</span></button>
                </div>
            </td>`;
        tb.appendChild(tr);
    });
}

async function editVendedor(id){
    try{
        const r=await fetch(`api.php?acao=obter_vendedor&id=${id}`,{credentials:'same-origin'});
        const v=await r.json();
        if(!v||v.ok===false)return toast('Vendedor não encontrado','err');
        openVM(v);
    }catch{toast('Erro ao carregar vendedor','err')}
}

$('busca').addEventListener('input',()=>{renderVendedores();if(viewMode==='list')renderTable()});

async function loadAll(){
    try{
        const r=await fetch('api.php?acao=listar_vendedores',{credentials:'same-origin'});
        vendedores=(r.ok?await r.json():[])||[];
        if(!Array.isArray(vendedores))vendedores=[];
        renderKPIs();
        renderVendedores();
        if(viewMode==='list')renderTable();
    }catch(e){console.error(e);toast('Erro ao carregar vendedores','err')}
}

window.onload=loadAll;
</script>
</body>
</html>
