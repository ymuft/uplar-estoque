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
$stmt = $pdo->prepare("SELECT role, passcode_hash, passcode_prompt_dismissed_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user     = $stmt->fetch();
$is_admin = ($user && $user['role'] === 'admin');
$passcodePromptDismissedAt = $user['passcode_prompt_dismissed_at'] ?? null;
$showPasscodePrompt = !$user || !empty($user['passcode_hash']) ? false : (!$passcodePromptDismissedAt || strtotime($passcodePromptDismissedAt) < (time() - 2592000));
if ($showPasscodePrompt && empty($_SESSION['passcode_prompt_shown'])) $_SESSION['passcode_prompt_shown'] = true;
else if (!empty($_SESSION['passcode_prompt_shown'])) $showPasscodePrompt = false;
$stmt = $pdo->prepare("SELECT whatsapp_number FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$whatsapp_number = ($stmt->fetch())['whatsapp_number'] ?? '';
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
require 'subscription.php';
$sub = check_and_block($user_id, $pdo); // redireciona para bloqueio.php se inativo
require 'onboarding.php';
$onboarding = get_onboarding_data((int)$user_id, $pdo, $is_admin);
if (uplar_business_profile_should_autostart($pdo, (int)$user_id, $is_admin)) {
    header('Location: perfil_negocio.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — Uplar</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"/>
<script src="https://cdn.jsdelivr.net/npm/echarts@5/dist/echarts.min.js"></script>
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
@keyframes fadeUp  {from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeIn  {from{opacity:0}to{opacity:1}}
@keyframes popIn   {from{opacity:0;transform:scale(.94)}to{opacity:1;transform:scale(1)}}
@keyframes barRise {from{height:0!important}to{}}
@keyframes lineGrow{from{width:0!important}to{}}
.anim{opacity:0;animation:fadeUp .5s cubic-bezier(.22,1,.36,1) forwards}
.d1{animation-delay:.04s}.d2{animation-delay:.10s}
.d3{animation-delay:.16s}.d4{animation-delay:.22s}
.d5{animation-delay:.28s}.d6{animation-delay:.34s}

/* ─── LAYOUT ────────────────────────────── */
.app{display:flex;min-height:100vh}

/* sidebar */
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

/* main */
.main{margin-left:248px;flex:1;padding:1.75rem 2rem;min-width:0}
.subscription-alert{display:flex;align-items:center;gap:.8rem;margin-bottom:1rem;padding:.8rem 1rem;border:1px solid rgba(245,158,11,.35);border-radius:12px;background:rgba(245,158,11,.1);color:#92400e}
.subscription-alert .material-symbols-outlined{font-size:1.35rem;color:#d97706;flex-shrink:0}
.subscription-alert-copy{font-size:.8rem;line-height:1.45;flex:1}.subscription-alert-copy strong{font-weight:800}
.subscription-alert-action{display:inline-flex;align-items:center;gap:.3rem;padding:.45rem .7rem;border-radius:8px;background:#d97706;color:#fff;font-size:.72rem;font-weight:700;white-space:nowrap}.subscription-alert-action:hover{background:#b45309}.subscription-alert-action .material-symbols-outlined{font-size:.9rem;color:inherit}
@media(max-width:600px){.subscription-alert{align-items:flex-start;flex-wrap:wrap}.subscription-alert-action{margin-left:2.15rem}}

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
.greeting{font-size:1.45rem;font-weight:700;letter-spacing:-.4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
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

/* ─── ACTION PILLS ──────────────────────── */
.pills{display:flex;gap:.5rem;margin-bottom:1.4rem;flex-wrap:wrap}
.pill{
    display:inline-flex;align-items:center;gap:.4rem;
    padding:.52rem 1rem;border-radius:50px;font-size:.8rem;
    font-weight:600;border:none;transition:all .22s;white-space:nowrap;
}
.pill .material-symbols-outlined{font-size:1rem}
.pill-solid{
    background:var(--p);color:#fff;
    box-shadow:0 3px 12px var(--p-glow);
}
.pill-solid:hover{background:var(--p-dark);transform:translateY(-2px);box-shadow:0 6px 20px var(--p-glow)}
.pill-outline{background:var(--card);color:var(--muted);border:1px solid var(--border)}
.pill-outline:hover{background:var(--p-dim);border-color:var(--p);color:var(--p);transform:translateY(-2px)}

/* ─── KPI ROW ───────────────────────────── */
.kpis{display:grid;grid-template-columns:repeat(4,1fr);gap:.85rem;margin-bottom:1.2rem}
.kpi{
    background:var(--card);border:1px solid var(--border);
    border-radius:var(--r);padding:1rem 1.1rem;
    transition:transform .22s,box-shadow .22s;
    position:relative;overflow:hidden;
}
.kpi::before{
    content:'';position:absolute;top:0;left:0;right:0;height:3px;
    background:var(--p);border-radius:var(--r) var(--r) 0 0;
}
.kpi.g::before{background:var(--ok)}
.kpi.o::before{background:var(--warn)}
.kpi.r::before{background:var(--err)}
.kpi:hover{transform:translateY(-3px);box-shadow:var(--sh-lift)}
.kpi-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:.55rem}
.kpi-ico{
    width:30px;height:30px;border-radius:8px;
    display:flex;align-items:center;justify-content:center;
    background:var(--p-dim);color:var(--p);
}
.kpi.g .kpi-ico{background:var(--ok-dim);color:var(--ok)}
.kpi.o .kpi-ico{background:var(--warn-dim);color:var(--warn)}
.kpi.r .kpi-ico{background:var(--err-dim);color:var(--err)}
.kpi-ico .material-symbols-outlined{font-size:1.05rem}
.kpi-tag{
    font-size:.67rem;font-weight:700;padding:.15rem .5rem;
    border-radius:20px;background:var(--p-dim);color:var(--p);
}
.kpi.g .kpi-tag{background:var(--ok-dim);color:var(--ok)}
.kpi.o .kpi-tag{background:var(--warn-dim);color:var(--warn)}
.kpi.r .kpi-tag{background:var(--err-dim);color:var(--err)}
.kpi-val{font-size:1.6rem;font-weight:700;letter-spacing:-.4px;line-height:1;margin-bottom:.18rem}
.kpi-lbl{font-size:.73rem;color:var(--muted);font-weight:500}
.kpi-hint{font-size:.68rem;color:var(--faint);margin-top:.12rem}

/* ─── CARD ──────────────────────────────── */
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:1.1rem 1.2rem}
.ch{display:flex;justify-content:space-between;align-items:center;margin-bottom:.9rem;padding-bottom:.7rem;border-bottom:1px solid var(--border)}
.ct{font-size:.88rem;font-weight:700}
.ca{
    font-size:.72rem;color:var(--p);font-weight:600;
    display:inline-flex;align-items:center;gap:2px;
}
.ca:hover{text-decoration:underline}
.ca .material-symbols-outlined{font-size:.8rem}

/* ─── DUO / SOLO ────────────────────────── */
.duo{display:grid;grid-template-columns:1.55fr 1fr;gap:.9rem;margin-bottom:.9rem}
.pair{display:grid;grid-template-columns:1fr 1fr;gap:.9rem;margin-bottom:.9rem}

/* ─── SPARKLINE ─────────────────────────── */
.spark{display:flex;align-items:flex-end;gap:5px;height:68px;margin-bottom:.8rem}
.sp-col{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px}
.sp-bar{
    width:100%;border-radius:4px 4px 0 0;min-height:2px;
    background:linear-gradient(to top,var(--p),#8ab0ff);
    position:relative;cursor:default;transition:opacity .18s;
}
.sp-bar:hover{opacity:.72}
.sp-bar::after{
    content:attr(data-tip);position:absolute;bottom:calc(100% + 5px);
    left:50%;transform:translateX(-50%);
    background:var(--ink);color:#fff;padding:3px 7px;
    border-radius:6px;font-size:.67rem;white-space:nowrap;
    opacity:0;pointer-events:none;transition:opacity .15s;z-index:10;
}
.sp-bar:hover::after{opacity:1}
.sp-lbl{font-size:.6rem;color:var(--faint)}
.spark-foot{display:flex;border-top:1px solid var(--border);padding-top:.7rem}
.sf{flex:1;text-align:center}
.sf+.sf{border-left:1px solid var(--border)}
.sf-v{font-size:.88rem;font-weight:700}
.sf-l{font-size:.67rem;color:var(--faint);margin-top:1px}

/* ─── LIST ROWS ─────────────────────────── */
.row{display:flex;align-items:center;gap:.7rem;padding:.55rem 0;border-bottom:1px solid var(--border)}
.row:last-child{border-bottom:none;padding-bottom:0}
.row:first-child{padding-top:0}
.dot{width:6px;height:6px;border-radius:50%;flex-shrink:0;background:var(--ok)}
.dot.w{background:var(--warn)}.dot.e{background:var(--err)}
.rb{flex:1;min-width:0}
.rn{font-size:.82rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rm{font-size:.7rem;color:var(--faint);margin-top:1px}
.rv{font-size:.84rem;font-weight:700;color:var(--ok);white-space:nowrap}
.rv.blue{color:var(--p)}
.rk{
    width:22px;height:22px;border-radius:7px;flex-shrink:0;
    display:flex;align-items:center;justify-content:center;
    font-size:.7rem;font-weight:800;
    background:var(--p-dim);color:var(--p);
}
.rk.au{background:rgba(245,158,11,.15);color:#b45309}
.rk.ag{background:rgba(148,163,184,.18);color:#64748b}
.rk.br{background:rgba(146,64,14,.12);color:#92400e}
.badge-s{
    font-size:.67rem;font-weight:700;padding:.13rem .5rem;
    border-radius:20px;white-space:nowrap;flex-shrink:0;
    background:var(--warn-dim);color:#b45309;
}
.badge-s.e{background:var(--err-dim);color:var(--err)}

/* ─── PRODUCT GRID ──────────────────────── */
.pg{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.85rem}
.pc{
    background:var(--card);border:1px solid var(--border);
    border-radius:var(--r);overflow:hidden;
    transition:transform .22s,box-shadow .22s;display:flex;flex-direction:column;
}
.pc:hover{transform:translateY(-4px);box-shadow:var(--sh-lift)}
.pc-img{height:130px;background:var(--bg);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0}
.pc-img img{width:100%;height:100%;object-fit:cover;transition:transform .4s}
.pc:hover .pc-img img{transform:scale(1.06)}
.pc-img .material-symbols-outlined{font-size:2.2rem;color:var(--border)}
.pc-body{padding:.75rem .85rem;flex:1;display:flex;flex-direction:column}
.pc-name{font-size:.82rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:1px}
.pc-code{font-size:.68rem;color:var(--faint);margin-bottom:.4rem}
.pc-price{font-size:1rem;font-weight:700;color:var(--p);margin-bottom:.15rem}
.pc-stock{font-size:.68rem;color:var(--muted);margin-bottom:.6rem}
.pc-stock.low{color:var(--warn);font-weight:600}
.pc-stock.zero{color:var(--err);font-weight:600}
.pc-acts{display:flex;gap:.4rem;margin-top:auto}
.pc-btn{
    flex:1;padding:.4rem;border:none;border-radius:7px;
    font-size:.72rem;font-weight:600;
    display:flex;align-items:center;justify-content:center;gap:.28rem;
    transition:all .18s;
}
.pc-btn .material-symbols-outlined{font-size:.9rem}
.pc-e{background:var(--p-dim);color:var(--p)}.pc-e:hover{background:rgba(59,111,245,.16)}
.pc-d{background:var(--err-dim);color:var(--err)}.pc-d:hover{background:rgba(239,68,68,.16)}

/* ─── MODAL ─────────────────────────────── */
.modal,.cfg{
    display:none;position:fixed;inset:0;
    background:rgba(15,28,46,.55);z-index:1000;
    align-items:center;justify-content:center;backdrop-filter:blur(5px);
}
.mbox,.cbox{
    background:var(--card);border-radius:16px;
    width:90%;max-width:640px;padding:1.7rem;
    max-height:93vh;overflow-y:auto;
    box-shadow:0 24px 64px rgba(0,0,0,.2);
    animation:popIn .28s cubic-bezier(.22,1,.36,1) both;
}
.mh{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.4rem}
.mh h2{font-size:1.05rem;font-weight:700}
.mcl{font-size:1.75rem;cursor:pointer;color:var(--faint);line-height:1;transition:color .15s}
.mcl:hover{color:var(--ink)}
.fg{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.fw{grid-column:1/-1}
.fl{display:flex;flex-direction:column;gap:.3rem}
.fl label{font-size:.72rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}
.fl input,.fl textarea{
    padding:.68rem .9rem;border:1px solid var(--border);
    border-radius:9px;font-size:.88rem;font-family:inherit;
    transition:border-color .18s,box-shadow .18s;outline:none;
}
.fl input:focus,.fl textarea:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.upload{
    border:2px dashed var(--border);border-radius:10px;
    padding:1.6rem;text-align:center;cursor:pointer;
    transition:all .2s;background:var(--bg);
}
.upload:hover,.upload.on{border-color:var(--p);background:var(--p-dim)}
.upload-ico{font-size:2.2rem;color:var(--p);margin-bottom:.4rem}
.upload-txt{font-size:.82rem;font-weight:500;color:var(--muted)}
.upload-hint{font-size:.72rem;color:var(--faint);margin-top:.2rem}
.prev{position:relative;display:inline-block;margin-top:.9rem}
.prev img{max-height:160px;border-radius:9px;border:1px solid var(--border)}
.prev-rm{
    position:absolute;top:-8px;right:-8px;
    background:var(--err);color:#fff;border:none;
    width:22px;height:22px;border-radius:50%;font-size:.95rem;
    line-height:22px;text-align:center;cursor:pointer;transition:transform .18s;
}
.prev-rm:hover{transform:scale(1.15)}
.btn-save{
    background:var(--p);color:#fff;border:none;
    padding:.8rem;border-radius:10px;font-weight:700;
    width:100%;font-size:.88rem;font-family:inherit;
    display:flex;align-items:center;justify-content:center;gap:.45rem;
    transition:all .22s;cursor:pointer;box-shadow:0 4px 14px var(--p-glow);
}
.btn-save:hover{background:var(--p-dark);transform:translateY(-2px)}
.btn-save:disabled{background:var(--faint);box-shadow:none;transform:none;cursor:not-allowed}

/* ─── EMPTY / SKEL ──────────────────────── */
.empty{text-align:center;padding:1.6rem 1rem;color:var(--faint)}
.empty .material-symbols-outlined{font-size:1.8rem;display:block;margin-bottom:.35rem;opacity:.3}
.empty p{font-size:.78rem}
.sk{
    border-radius:7px;
    background:linear-gradient(90deg,var(--bg) 25%,#e8ecf2 50%,var(--bg) 75%);
    background-size:200% 100%;animation:shimmer 1.3s infinite;
}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}

/* ─── BOTTOM NAV ────────────────────────── */
.bnav{
    display:none;position:fixed;bottom:0;left:0;right:0;
    background:var(--card);border-top:1px solid var(--border);z-index:300;
    padding:.35rem 0 calc(.35rem + env(safe-area-inset-bottom));
    box-shadow:0 -2px 16px rgba(15,28,46,.07);
}
.bnav-inner{display:flex;justify-content:space-around}
.bnav-a{
    display:flex;flex-direction:column;align-items:center;gap:2px;
    padding:.35rem .6rem;border-radius:9px;color:var(--faint);
    font-size:.62rem;font-weight:600;transition:all .18s;flex:1;
}
.bnav-a .material-symbols-outlined{font-size:1.25rem}
.bnav-a.on{color:var(--p)}
.bnav-a:hover{background:var(--p-dim)}

/* ─── RESPONSIVE ────────────────────────── */
@media(max-width:1050px){
    .kpis{grid-template-columns:repeat(2,1fr)}
    .duo{grid-template-columns:1fr}
}
@media(max-width:860px){
    .sb{transform:translateX(-100%)}
    .sb.open{transform:translateX(0)}
    .main{margin-left:0}
    .ham{display:flex}
    .pair{grid-template-columns:1fr}
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
    .pg{grid-template-columns:1fr 1fr}
    .mbox,.cbox{padding:1.2rem;width:96%;max-height:96vh}
    .fg{grid-template-columns:1fr}
}
@media(max-width:380px){
    .kpis{grid-template-columns:1fr}
    .pg{grid-template-columns:1fr}
}

/* Dashboard refinement */
:focus-visible{outline:3px solid rgba(59,111,245,.28);outline-offset:2px}
.main{max-width:1600px}
.top{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:.95rem 1rem;margin-bottom:1rem;box-shadow:var(--sh);display:grid;grid-template-columns:max-content minmax(280px,420px) 1fr;align-items:start;gap:.8rem;width:100%}
.top-l{align-items:flex-start;min-width:0}
.top-copy{min-width:0}
.top-actions{display:flex;align-items:flex-start;gap:.5rem;justify-content:flex-end;min-width:0}
.searchbox{width:100%;min-height:38px}
.pills{margin:0;justify-content:flex-end}
.pill{border-radius:9px;min-height:38px}
.top .pill{padding:.5rem .78rem}
.pill-solid:hover,.pill-outline:hover{transform:translateY(-1px)}
.sb-link.on{position:relative}
.sb-link.on::before{content:'';position:absolute;left:0;width:3px;height:20px;border-radius:0 3px 3px 0;background:var(--p)}
.overview,.duo,.pair{display:grid;grid-template-columns:minmax(0,1.45fr) minmax(330px,.75fr);gap:.9rem;margin-bottom:.9rem;align-items:stretch}
.attention-list{display:grid;gap:.55rem}
.attention-item{display:flex;align-items:center;gap:.75rem;padding:.7rem .75rem;background:var(--bg);border-radius:9px;min-height:58px}
.attention-icon{width:34px;height:34px;border-radius:8px;display:grid;place-items:center;flex:0 0 auto;background:var(--warn-dim);color:#b45309}
.attention-icon.error{background:var(--err-dim);color:var(--err)}
.attention-icon.ok{background:var(--ok-dim);color:var(--ok)}
.attention-icon .material-symbols-outlined{font-size:1.1rem}
.attention-copy{min-width:0;flex:1}
.attention-title{font-size:.82rem;font-weight:650}
.attention-meta{font-size:.72rem;color:var(--muted);margin-top:1px}
.attention-action{font-size:.72rem;font-weight:650;color:var(--p);padding:.35rem .55rem;border-radius:7px;white-space:nowrap}
.attention-action:hover{background:var(--p-dim)}
.summary-grid{display:grid;grid-template-columns:1fr 1fr;gap:0}
.summary-item{padding:.75rem .8rem;border-bottom:1px solid var(--border)}
.summary-item:nth-child(odd){border-right:1px solid var(--border)}
.summary-item:nth-last-child(-n+2){border-bottom:0}
.summary-label{font-size:.7rem;color:var(--muted);margin-bottom:.2rem}
.summary-value{font-size:1.15rem;font-weight:700;letter-spacing:-.025em;overflow-wrap:anywhere}
.summary-value.danger{color:var(--err)}
.summary-note{font-size:.66rem;color:var(--faint);margin-top:.12rem}
.chart-card{min-height:360px;display:flex;flex-direction:column;overflow:hidden}
.chart-head-meta{font-size:.7rem;color:var(--muted);margin-top:.12rem}
.echart-wrap{position:relative;min-height:230px;height:250px;margin:.1rem 0 .75rem;border-radius:10px;background:linear-gradient(180deg,rgba(59,111,245,.06),rgba(255,255,255,0));overflow:hidden}
.echart-wrap::before{content:'';position:absolute;inset:0;background:linear-gradient(rgba(59,111,245,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(59,111,245,.06) 1px,transparent 1px);background-size:36px 36px;mask-image:linear-gradient(to bottom,rgba(0,0,0,.85),transparent);pointer-events:none}
.echart-canvas{position:relative;z-index:1;width:100%;height:100%;min-height:230px}
.spark{height:170px;gap:9px;padding:.7rem .25rem .2rem;margin-bottom:.65rem}
.sp-bar{background:var(--p);border-radius:5px 5px 2px 2px;max-width:54px}
.sp-lbl{font-size:.68rem}
.row{padding:.67rem 0}
.row:hover{background:linear-gradient(90deg,transparent,var(--p-dim),transparent)}
.status-label{display:inline-flex;align-items:center;gap:.25rem;font-size:.66rem;font-weight:650;color:#047857;background:var(--ok-dim);padding:.15rem .42rem;border-radius:6px;margin-top:.25rem}
.card{box-shadow:0 1px 2px rgba(15,28,46,.025)}
.ct-wrap{min-width:0}
.ct-sub{font-size:.69rem;color:var(--muted);margin-top:.1rem}
.pg{grid-template-columns:repeat(4,minmax(0,1fr))}
.pc-img{height:112px}
.pc{position:relative}
.pc-more{position:absolute;right:.55rem;top:.55rem;z-index:2}
.pc-more summary{list-style:none;width:32px;height:32px;border:1px solid var(--border);border-radius:8px;background:rgba(255,255,255,.94);display:grid;place-items:center;cursor:pointer;color:var(--muted)}
.pc-more summary::-webkit-details-marker{display:none}
.pc-menu{position:absolute;right:0;top:36px;background:var(--card);border:1px solid var(--border);border-radius:8px;box-shadow:var(--sh);padding:.3rem;min-width:120px}
.pc-menu button{border:0;background:transparent;color:var(--err);font:600 .72rem Inter,sans-serif;padding:.5rem;width:100%;text-align:left;border-radius:6px}
.pc-menu button:hover{background:var(--err-dim)}
.pc-acts .pc-e{flex:1}
.dashboard-error{padding:.75rem;background:var(--err-dim);color:#b91c1c;border-radius:8px;font-size:.78rem}
@media(max-width:1150px){.pg{grid-template-columns:repeat(3,minmax(0,1fr))}}
@media(max-width:1180px){
    .top{grid-template-columns:max-content minmax(260px,1fr);align-items:start}
    .top-actions{grid-column:1/-1;justify-content:flex-end}
}
@media(max-width:900px){.overview,.duo,.pair{grid-template-columns:1fr}.top{grid-template-columns:1fr}.top-actions{width:100%;min-width:0;justify-content:flex-start}.searchbox{width:100%}}
@media(max-width:600px){
    .top{padding:.85rem}.top-actions{align-items:stretch}.pills{display:grid;grid-template-columns:1fr 1fr;width:100%}.pill{justify-content:center}.pill-solid{grid-column:1/-1}
    .overview{gap:.7rem}.summary-value{font-size:1.02rem}.attention-item{align-items:flex-start}.attention-action{align-self:center}
    .echart-wrap{height:220px;min-height:210px}.echart-canvas{min-height:210px}.spark{height:150px;gap:5px}.pg{grid-template-columns:1fr 1fr}.card{padding:1rem}.duo,.pair{gap:.7rem}
}
@media(max-width:420px){.pg{grid-template-columns:1fr}.summary-grid{grid-template-columns:1fr}.summary-item,.summary-item:nth-child(odd){border-right:0;border-bottom:1px solid var(--border)}.summary-item:last-child{border-bottom:0}}
</style>
<link rel="stylesheet" href="assets/app-theme.css?v=20260723">
<link rel="stylesheet" href="assets/changelog.css?v=20260714">
<link rel="stylesheet" href="assets/onboarding.css?v=20260724">
<script src="assets/app-theme.js?v=20260723"></script>
</head>
<body>
<div class="app">

<div class="overlay" id="ov"></div>

<!-- ═══ SIDEBAR ═════════════════════════ -->
<?php require __DIR__ . '/includes/sidebar.php'; ?>

<!-- ═══ MAIN ═════════════════════════════ -->
<main class="main">

    <?php if (!$is_admin && !empty($sub['expirando'])): ?>
    <?php $diasAssinatura = (int)$sub['dias_restantes']; ?>
    <div class="subscription-alert" role="status" aria-live="polite">
        <span class="material-symbols-outlined" aria-hidden="true">warning</span>
        <div class="subscription-alert-copy">
            <?php if ($diasAssinatura === 0): ?>
                <strong>Sua assinatura vence hoje.</strong> Renove agora para não perder o acesso.
            <?php else: ?>
                <strong>Sua assinatura vence em <?= $diasAssinatura ?> dia<?= $diasAssinatura === 1 ? '' : 's' ?>.</strong> Renove com antecedência para manter o acesso ativo.
            <?php endif; ?>
        </div>
        <a class="subscription-alert-action" href="config.php#assinatura">Renovar <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a>
    </div>
    <?php endif; ?>

    <?php if ($showPasscodePrompt): ?>
    <section class="card" aria-labelledby="passcodePromptTitle" style="margin-bottom:1rem;display:flex;align-items:center;gap:.8rem">
        <div class="attention-icon ok"><span class="material-symbols-outlined" aria-hidden="true">shield_lock</span></div>
        <div class="attention-copy"><div class="attention-title" id="passcodePromptTitle">Proteja ainda mais sua conta</div><div class="attention-meta">Configure um Pass Code para adicionar uma camada extra de segurança ao seu acesso.</div></div>
        <a class="attention-action" href="config.php#passcode">Configurar agora</a>
        <button class="attention-action" type="button" id="dismiss-passcode-prompt">Agora não</button>
    </section>
    <?php endif; ?>

    <!-- top bar -->
    <div class="top anim d1">
        <div class="top-l">
            <button class="ham" id="ham"><span class="material-symbols-outlined">menu</span></button>
            <div class="top-copy">
                <div class="greeting" id="greet">Dashboard</div>
                <div class="sub">Visão geral do seu negócio</div>
            </div>
        </div>
        <label class="searchbox" for="busca">
            <span class="material-symbols-outlined">search</span>
            <input id="busca" placeholder="Buscar produto por nome ou código…" type="search" autocomplete="off">
        </label>
        <div class="top-actions">
            <div class="pills">
                <a href="produtos.php" class="pill pill-solid" id="btnNovo"><span class="material-symbols-outlined">add</span>Cadastrar produto</a>
                <a href="produtos.php" class="pill pill-outline">
                    <span class="material-symbols-outlined">inventory_2</span>Produtos
                </a>
                <a href="relatorio.php" class="pill pill-outline">
                    <span class="material-symbols-outlined">bar_chart</span>Relatórios
                </a>
                <a href="vendedores.php" class="pill pill-outline">
                    <span class="material-symbols-outlined">group</span>Vendedores
                </a>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/includes/dashboard_onboarding.php'; ?>

    <!-- Attention center + compact summary -->
    <div class="overview anim d3">
        <section class="card" aria-labelledby="attentionTitle">
            <div class="ch"><div class="ct-wrap"><h2 class="ct" id="attentionTitle">Central de atenção</h2><div class="ct-sub">Prioridades que precisam do seu acompanhamento</div></div></div>
            <div class="attention-list" id="attention"><div class="sk" style="height:58px"></div></div>
        </section>
        <section class="card" aria-labelledby="summaryTitle">
            <div class="ch"><div class="ct-wrap"><h2 class="ct" id="summaryTitle">Resumo do período</h2><div class="ct-sub">Visão compacta dos últimos 30 dias</div></div></div>
            <div class="summary-grid" id="summary">
                <?php for($i=0;$i<4;$i++):?><div class="summary-item"><div class="sk" style="height:2.3rem"></div></div><?php endfor;?>
            </div>
        </section>
    </div>

    <!-- Receita 7d + Recentes -->
    <div class="duo anim d4">
        <div class="card chart-card">
            <div class="ch">
                <div class="ct-wrap"><h2 class="ct">Desempenho de vendas</h2><div class="chart-head-meta">Receita diária · últimos 7 dias</div></div>
                <a href="relatorio.php" class="ca">Completo <span class="material-symbols-outlined">arrow_forward</span></a>
            </div>
            <div class="echart-wrap">
                <div class="echart-canvas" id="salesChart" role="img" aria-label="Gráfico de linha com receita diária dos últimos 7 dias"></div>
            </div>
            <div class="spark" id="spark" style="display:none">
                <div class="empty" style="width:100%"><span class="material-symbols-outlined">bar_chart</span><p>Carregando…</p></div>
            </div>
            <div class="spark-foot" id="sfoot">
                <div class="sf"><div class="sf-v" id="s7r">—</div><div class="sf-l">Receita</div></div>
                <div class="sf"><div class="sf-v" id="s7v">—</div><div class="sf-l">Vendas</div></div>
                <div class="sf"><div class="sf-v" id="s7u">—</div><div class="sf-l">Unidades</div></div>
            </div>
        </div>
        <div class="card">
            <div class="ch">
                <div class="ct-wrap"><h2 class="ct">Últimas vendas</h2><div class="ct-sub">Transações registradas recentemente</div></div>
                <a href="relatorio.php" class="ca">Ver todas <span class="material-symbols-outlined">arrow_forward</span></a>
            </div>
            <div id="recent"><div class="sk" style="height:.8rem;margin-bottom:.5rem"></div><div class="sk" style="height:.8rem;width:75%"></div></div>
        </div>
    </div>

    <!-- Top produtos + Estoque baixo -->
    <div class="pair anim d5">
        <div class="card">
            <div class="ch">
                <div class="ct-wrap"><h2 class="ct">Produtos mais vendidos</h2><div class="ct-sub">Ranking por receita nos últimos 30 dias</div></div>
                <a href="relatorio.php" class="ca">Relatório <span class="material-symbols-outlined">arrow_forward</span></a>
            </div>
            <div id="topP"><div class="sk" style="height:.8rem;margin-bottom:.5rem"></div><div class="sk" style="height:.8rem;width:70%"></div></div>
        </div>
        <div class="card">
            <div class="ch">
                <div class="ct-wrap"><h2 class="ct">Estoque crítico</h2><div class="ct-sub">Produtos com 5 unidades ou menos</div></div>
                <span id="abadge" style="font-size:.68rem;font-weight:700;padding:.13rem .5rem;border-radius:20px;background:var(--warn-dim);color:#b45309">—</span>
            </div>
            <div id="lowS"><div class="sk" style="height:.8rem;margin-bottom:.5rem"></div><div class="sk" style="height:.8rem;width:80%"></div></div>
        </div>
    </div>

    <!-- Produtos -->
    <div class="card anim d6">
        <div class="ch">
            <div class="ct-wrap"><h2 class="ct">Produtos em destaque</h2><div class="ct-sub">Uma seleção compacta do seu catálogo</div></div>
            <a href="produtos.php" class="ca">Ver todos <span class="material-symbols-outlined">arrow_forward</span></a>
        </div>
        <div class="pg" id="pg">
            <?php for($i=0;$i<4;$i++):?>
            <div class="pc"><div class="sk" style="height:130px;border-radius:0"></div><div style="padding:.75rem .85rem"><div class="sk" style="height:.8rem;margin-bottom:.35rem"></div><div class="sk" style="height:.7rem;width:55%"></div></div></div>
            <?php endfor;?>
        </div>
    </div>

</main>
<?php require __DIR__ . '/includes/changelog_modal.php'; ?>
</div>

<!-- bottom nav -->
<nav class="bnav">
    <div class="bnav-inner">
        <a href="estoque.php"    class="bnav-a on"><span class="material-symbols-outlined">dashboard</span>Início</a>
        <a href="relatorio.php"  class="bnav-a"><span class="material-symbols-outlined">bar_chart</span>Relatórios</a>
        <a href="produtos.php"   class="bnav-a"><span class="material-symbols-outlined">inventory</span>Produtos</a>
        <a href="vendedores.php" class="bnav-a"><span class="material-symbols-outlined">group</span>Vendedores</a>
    </div>
</nav>

<!-- ═══ MODAL PRODUTO ════════════════════ -->
<div class="modal" id="modal">
    <div class="mbox">
        <div class="mh">
            <h2 id="mtitle">Novo Produto</h2>
            <span class="mcl" id="mclose">close</span>
        </div>
        <div class="fg">
            <input type="hidden" id="pId">
            <div class="fl fw"><label>Nome</label><input id="pNome" placeholder="Nome do produto"></div>
            <div class="fl"><label>Código</label><input id="pCod" placeholder="SKU-001"></div>
            <div class="fl"><label>Preço (R$)</label><input id="pPreco" type="number" step="0.01" min="0" placeholder="0,00"></div>
            <div class="fl"><label>Quantidade</label><input id="pQtd" type="number" min="0" value="1"></div>
            <div class="fl fw"><label>Descrição</label><textarea id="pDesc" rows="2" placeholder="Opcional…"></textarea></div>
            <div class="fl fw">
                <label>Foto</label>
                <div class="upload" id="dz">
                    <span class="material-symbols-outlined upload-ico">cloud_upload</span>
                    <div class="upload-txt">Clique ou arraste a imagem</div>
                    <div class="upload-hint">PNG · JPG · WEBP — máx 5MB</div>
                    <input type="file" id="fImg" accept="image/*" hidden>
                    <div class="prev" id="prevW" style="display:none">
                        <img id="prevI" alt="">
                        <button type="button" class="prev-rm" id="rmPrev">×</button>
                    </div>
                </div>
            </div>
            <button class="btn-save fw" id="btnSave">
                <span class="material-symbols-outlined">save</span>Salvar Produto
            </button>
        </div>
    </div>
</div>

<!-- ═══ CONFIG ═══════════════════════════ -->
<div class="cfg" id="cfg">
    <div class="cbox">
        <div class="mh">
            <h2>Configurações da conta</h2>
            <span class="mcl" id="cfgClose">close</span>
        </div>
        <div style="display:flex;flex-direction:column;gap:.9rem">
            <div class="fl">
                <label>WhatsApp para vendas</label>
                <input id="waNum" placeholder="5599999999999" value="<?= htmlspecialchars($whatsapp_number) ?>">
                <span style="font-size:.7rem;color:var(--faint)">DDI + DDD + número (apenas números)</span>
            </div>
            <button class="btn-save" id="btnWa"><span class="material-symbols-outlined">save</span>Salvar número</button>
        </div>
    </div>
</div>

<script>
const CSRF = <?= json_encode(get_csrf_token()) ?>;
const dismissPasscodePrompt=document.getElementById('dismiss-passcode-prompt');
dismissPasscodePrompt?.addEventListener('click',async()=>{
    dismissPasscodePrompt.disabled=true;
    try{
        const response=await fetch('config_api.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({acao:'dispensar_incentivo_passcode',csrf_token:CSRF})});
        if(response.ok) dismissPasscodePrompt.closest('section')?.remove();
    }finally{dismissPasscodePrompt.disabled=false;}
});
const DASHBOARD_CONTEXT = <?= json_encode([
    'onboardingStage' => $onboarding['stage'],
    'counts' => $onboarding['counts'],
    'storeUrl' => $onboarding['store']['url'],
    'hasStoreSlug' => $onboarding['store']['slug'] !== '',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const $ = id => document.getElementById(id);
const esc = v => String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const R   = v => 'R$ '+Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
const dt  = s => { if(!s) return '—'; const d=new Date(s); return d.toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit'}) };

/* greeting */
(()=>{
    const h=new Date().getHours();
    $('greet').textContent=(h<12?'Bom dia':h<18?'Boa tarde':'Boa noite')+', <?= htmlspecialchars($username) ?> ';
})();

/* counter */
function cnt(el,to,fmt){
    const s=performance.now(),dur=550;
    const t=now=>{const p=Math.min((now-s)/dur,1),e=1-Math.pow(1-p,3);el.textContent=fmt(to*e);if(p<1)requestAnimationFrame(t);else el.textContent=fmt(to)};
    requestAnimationFrame(t);
}

/* sidebar */
const sb=$('sb'),ov=$('ov');
$('ham').addEventListener('click',()=>{sb.classList.add('open');ov.classList.add('on');document.body.style.overflow='hidden'});
ov.addEventListener('click',()=>{sb.classList.remove('open');ov.classList.remove('on');document.body.style.overflow=''});
sb.querySelectorAll('.sb-link').forEach(l=>l.addEventListener('click',()=>{if(window.innerWidth<=860){sb.classList.remove('open');ov.classList.remove('on');document.body.style.overflow=''}}));

/* config */
$('trigCfg').addEventListener('click',()=>openSidebarProfile());
$('cfgClose').addEventListener('click',()=>$('cfg').style.display='none');
$('cfg').addEventListener('click',e=>{if(e.target===$('cfg'))$('cfg').style.display='none'});
$('btnWa').addEventListener('click',async()=>{
    const btn=$('btnWa'),n=$('waNum').value.replace(/\D/g,'');
    if(!n||n.length<10)return alert('Número inválido');
    btn.disabled=true;btn.innerHTML='<span class="material-symbols-outlined">hourglass_empty</span>Salvando…';
    const fd=new FormData();fd.append('whatsapp_number',n);fd.append('csrf_token',CSRF);
    const r=await fetch('api.php?acao=save_whatsapp',{method:'POST',body:fd,credentials:'same-origin'});
    const j=await r.json();
    if(j.ok){alert('Número salvo!');$('cfg').style.display='none'}else alert('Erro: '+(j.error||'Falha'));
    btn.disabled=false;btn.innerHTML='<span class="material-symbols-outlined">save</span>Salvar número';
});

/* modal produto */
let estoque=[];
$('btnNovo').addEventListener('click',e=>{e.preventDefault();window.location.href='produtos.php'});
$('mclose').addEventListener('click',()=>$('modal').style.display='none');
$('modal').addEventListener('click',e=>{if(e.target===$('modal'))$('modal').style.display='none'});
function openM(p=null){
    $('mtitle').textContent=p?'Editar Produto':'Novo Produto';
    $('pId').value=p?.id??'';
    $('pNome').value=p?.nome??'';
    $('pCod').value=p?.codigo??'';
    $('pPreco').value=p?Number(p.preco).toFixed(2):'';
    $('pQtd').value=p?.quantidade??1;
    $('pDesc').value=p?.descricao??'';
    $('fImg').value='';
    if(p?.imagem){$('prevI').src=p.imagem;$('prevW').style.display='block'}
    else $('prevW').style.display='none';
    $('modal').style.display='flex';
}
function showPrev(f){
    if(!f||!f.type.startsWith('image/'))return;
    if(f.size>5*1024*1024)return alert('Máx 5MB.');
    const r=new FileReader();r.onload=e=>{$('prevI').src=e.target.result;$('prevW').style.display='block'};r.readAsDataURL(f);
}
const dz=$('dz');
dz.addEventListener('click',()=>$('fImg').click());
$('fImg').addEventListener('change',e=>{if(e.target.files[0])showPrev(e.target.files[0])});
['dragover','dragenter'].forEach(ev=>dz.addEventListener(ev,e=>{e.preventDefault();dz.classList.add('on')}));
['dragleave','drop'].forEach(ev=>dz.addEventListener(ev,e=>{e.preventDefault();dz.classList.remove('on')}));
dz.addEventListener('drop',e=>{e.preventDefault();const f=e.dataTransfer.files[0];if(f){$('fImg').files=e.dataTransfer.files;showPrev(f)}});
$('rmPrev').addEventListener('click',()=>{$('fImg').value='';$('prevW').style.display='none'});
$('btnSave').addEventListener('click',async()=>{
    const id=$('pId').value,acao=id?'editar':'adicionar';
    const fd=new FormData();
    fd.append('id',id);fd.append('nome',$('pNome').value.trim());fd.append('codigo',$('pCod').value.trim());
    fd.append('preco',$('pPreco').value.trim());fd.append('quantidade',$('pQtd').value.trim());
    fd.append('descricao',$('pDesc').value.trim());fd.append('csrf_token',CSRF);
    if($('fImg').files[0])fd.append('imagem',$('fImg').files[0]);
    const btn=$('btnSave');btn.disabled=true;btn.innerHTML='<span class="material-symbols-outlined">hourglass_empty</span>Salvando…';
    try{
        const r=await fetch(`api.php?acao=${acao}`,{method:'POST',body:fd,credentials:'same-origin'});
        const j=await r.json();
        if(j.ok){$('modal').style.display='none';await loadAll();window.UplarOnboarding?.productAdded();alert('Produto salvo!')}
        else alert('Erro: '+(j.error||'Falha'));
    }catch(e){alert('Erro: '+e.message)}
    btn.disabled=false;btn.innerHTML='<span class="material-symbols-outlined">save</span>Salvar Produto';
});
async function delProd(id,nome){
    if(!confirm(`Excluir "${nome}"?`))return;
    const fd=new FormData();fd.append('id',id);fd.append('csrf_token',CSRF);
    const r=await fetch('api.php?acao=deletar',{method:'POST',body:fd,credentials:'same-origin'});
    const j=await r.json();
    j.ok?(await loadAll(),alert('Produto excluído!')):alert('Erro: '+(j.error||'Falha'));
}

/* ── attention center + compact summary ── */
function renderKPIs(prods,v30,vends){
    const total=prods.length;
    const valor=prods.reduce((s,p)=>s+Number(p.preco||0)*Number(p.quantidade||0),0);
    const rec=v30.reduce((s,v)=>s+Number(v.total||0),0);
    const un=v30.reduce((s,v)=>s+Number(v.quantidade||0),0);
    const sem=prods.filter(p=>Number(p.quantidade)===0).length;
    const baixos=prods.filter(p=>Number(p.quantidade)>0&&Number(p.quantidade)<=5).length;
    const initial=total===0&&v30.length===0&&Number(DASHBOARD_CONTEXT.counts.orders||0)===0;
    $('summary').innerHTML=initial?`
        <div class="summary-item"><div class="summary-label">Produtos</div><div class="summary-value">0</div><div class="summary-note">Comece montando seu catálogo</div></div>
        <div class="summary-item"><div class="summary-label">Pedidos</div><div class="summary-value">${Number(DASHBOARD_CONTEXT.counts.orders||0).toLocaleString('pt-BR')}</div><div class="summary-note">Chegarão pela sua vitrine</div></div>
        <div class="summary-item"><div class="summary-label">Clientes</div><div class="summary-value">${Number(DASHBOARD_CONTEXT.counts.clients||0).toLocaleString('pt-BR')}</div><div class="summary-note">Organize seus contatos</div></div>
        <div class="summary-item"><div class="summary-label">Vendas</div><div class="summary-value">0</div><div class="summary-note">Seus resultados aparecerão aqui</div></div>`:`
        <div class="summary-item"><div class="summary-label">Produtos cadastrados</div><div class="summary-value">${total.toLocaleString('pt-BR')}</div><div class="summary-note">${R(valor)} em estoque</div></div>
        <div class="summary-item"><div class="summary-label">Receita</div><div class="summary-value">${R(rec)}</div><div class="summary-note">${v30.length} vendas em 30 dias</div></div>
        <div class="summary-item"><div class="summary-label">Unidades vendidas</div><div class="summary-value">${un.toLocaleString('pt-BR')}</div><div class="summary-note">${vends.length} vendedor(es)</div></div>
        <div class="summary-item"><div class="summary-label">Produtos sem estoque</div><div class="summary-value ${sem?'danger':''}">${sem.toLocaleString('pt-BR')}</div><div class="summary-note">${baixos} com estoque baixo</div></div>`;
    const alerts=[];
    if(initial){
        alerts.push({icon:'inventory_2',tone:'',title:'Seu catálogo está vazio',meta:'Adicione os primeiros produtos para montar sua loja',href:'produtos.php',action:'Adicionar produtos'});
        alerts.push({icon:'storefront',tone:'ok',title:'Sua vitrine já pode ganhar forma',meta:'Personalize a loja e prepare o link para compartilhar',href:'vitrine_config.php',action:'Personalizar'});
    }
    if(sem) alerts.push({icon:'error',tone:'error',title:`${sem} ${sem===1?'produto está':'produtos estão'} sem estoque`,meta:'Prioridade alta · venda indisponível',action:'Ver produtos'});
    if(baixos) alerts.push({icon:'warning',tone:'',title:`${baixos} ${baixos===1?'produto atingiu':'produtos atingiram'} estoque baixo`,meta:'Reposição recomendada · até 5 unidades',action:'Revisar estoque'});
    if(v30.length) alerts.push({icon:'receipt_long',tone:'ok',title:`${v30.length} ${v30.length===1?'venda registrada':'vendas registradas'} nos últimos 30 dias`,meta:`${un} unidades · ${R(rec)} em receita`,href:'relatorio.php',action:'Ver relatório'});
    if(!alerts.length) alerts.push({icon:'check_circle',tone:'ok',title:'Nenhuma pendência crítica no momento',meta:'Seu estoque está operando normalmente'});
    $('attention').innerHTML=alerts.slice(0,3).map(a=>`<div class="attention-item">
        <div class="attention-icon ${a.tone}"><span class="material-symbols-outlined">${a.icon}</span></div>
        <div class="attention-copy"><div class="attention-title">${a.title}</div><div class="attention-meta">${a.meta}</div></div>
        ${a.action?`<a class="attention-action" href="${a.href||'produtos.php'}">${a.action}</a>`:''}
    </div>`).join('');
}

/* ── sales chart ── */
let salesChart = null;
function renderSalesChart(v7){
    window.__uplarSales7=v7;
    const hoje=new Date();
    const dias=Array.from({length:7},(_,i)=>{
        const d=new Date(hoje);d.setDate(hoje.getDate()-(6-i));
        return{l:d.toLocaleDateString('pt-BR',{weekday:'short'}).replace('.',''),k:d.toISOString().slice(0,10),t:0,q:0};
    });
    v7.forEach(v=>{const d=dias.find(x=>x.k===(v.criado_em||'').slice(0,10));if(d){d.t+=Number(v.total||0);d.q+=Number(v.quantidade||0)}});
    $('s7r').textContent=R(dias.reduce((s,d)=>s+d.t,0));
    $('s7v').textContent=v7.length+' vendas';
    $('s7u').textContent=v7.reduce((s,v)=>s+Number(v.quantidade||0),0)+' un.';
    const el=$('salesChart');
    if(typeof echarts==='undefined'){
        el.innerHTML='<div class="empty"><span class="material-symbols-outlined">cloud_off</span><p>Gráfico indisponível no momento</p></div>';
        return;
    }
    if(dias.every(d=>d.t===0)){
        if(salesChart){salesChart.dispose();salesChart=null}
        el.innerHTML=`<div class="chart-demo" aria-label="Demonstração visual do futuro gráfico de vendas">
            <div class="chart-demo-head"><span class="chart-demo-title">Veja o que você poderá acompanhar aqui</span><span class="chart-demo-badge">Demonstração</span></div>
            <div class="chart-demo-bars" aria-hidden="true"><span style="height:28%"></span><span style="height:43%"></span><span style="height:36%"></span><span style="height:62%"></span><span style="height:51%"></span><span style="height:74%"></span><span style="height:68%"></span></div>
            <div class="chart-demo-copy">Quando suas vendas começarem, a receita diária será exibida neste gráfico.</div>
        </div>`;
        return;
    }
    if(el.querySelector('.empty'))el.innerHTML='';
    if(!salesChart)salesChart=echarts.init(el,null,{renderer:'canvas'});
    const totals=dias.map(d=>Number(d.t.toFixed(2)));
    const chartStyles=getComputedStyle(document.documentElement);
    const chartMuted=chartStyles.getPropertyValue('--faint').trim()||'#94a3b8';
    const chartBorder=chartStyles.getPropertyValue('--border').trim()||'#e4eaf2';
    const chartCard=chartStyles.getPropertyValue('--card').trim()||'#ffffff';
    salesChart.setOption({
        color:['#3b6ff5'],
        grid:{left:8,right:12,top:24,bottom:16,containLabel:true},
        tooltip:{
            trigger:'axis',
            backgroundColor:chartCard,
            borderColor:chartBorder,
            borderWidth:1,
            padding:[9,11],
            textStyle:{color:getComputedStyle(document.documentElement).getPropertyValue('--ink').trim(),fontFamily:'Inter',fontSize:12},
            axisPointer:{type:'line',lineStyle:{color:'rgba(59,111,245,.55)',width:1,type:'dashed'}},
            formatter:params=>{
                const p=params[0],d=dias[p.dataIndex];
                return `<strong>${d.l}</strong><br>Receita: ${R(d.t)}<br>Unidades: ${d.q}`;
            }
        },
        xAxis:{
            type:'category',
            boundaryGap:false,
            data:dias.map(d=>d.l),
            axisTick:{show:false},
            axisLine:{lineStyle:{color:chartBorder}},
            axisLabel:{color:chartMuted,fontFamily:'Inter',fontSize:11}
        },
        yAxis:{
            type:'value',
            min:0,
            splitNumber:4,
            axisLabel:{color:chartMuted,fontFamily:'Inter',fontSize:11,formatter:v=>v>=1000?'R$ '+(v/1000).toFixed(1).replace('.',',')+'k':'R$ '+v},
            splitLine:{lineStyle:{color:chartBorder,type:'dashed',opacity:.7}}
        },
        series:[{
            name:'Receita',
            type:'line',
            smooth:true,
            symbol:'circle',
            symbolSize:7,
            showSymbol:true,
            data:totals,
            lineStyle:{
                width:3,
                color:{
                    type:'linear',x:0,y:0,x2:1,y2:0,
                    colorStops:[{offset:0,color:'#2a55d9'},{offset:.55,color:'#3b6ff5'},{offset:1,color:'#10b981'}]
                },
                shadowColor:'rgba(59,111,245,.28)',
                shadowBlur:10
            },
            itemStyle:{color:chartCard,borderColor:'#3b6ff5',borderWidth:2},
            areaStyle:{
                color:{
                    type:'linear',x:0,y:0,x2:0,y2:1,
                    colorStops:[{offset:0,color:'rgba(59,111,245,.22)'},{offset:.7,color:'rgba(59,111,245,.05)'},{offset:1,color:'rgba(59,111,245,0)'}]
                }
            },
            emphasis:{focus:'series',itemStyle:{borderColor:'#10b981',borderWidth:3}}
        }]
    },true);
    requestAnimationFrame(()=>salesChart.resize());
}
window.addEventListener('resize',()=>{if(salesChart)salesChart.resize()});
window.addEventListener('uplar:themechange',()=>{if(Array.isArray(window.__uplarSales7))renderSalesChart(window.__uplarSales7)});

/* ── recent ── */
function renderRecent(v30){
    const d=$('recent');
    if(!v30.length){d.innerHTML=`<div class="empty"><span class="material-symbols-outlined">receipt_long</span><p><strong>Nenhuma venda ainda</strong><br><span>Compartilhe sua vitrine para começar.</span></p><a class="empty-action secondary" href="${esc(DASHBOARD_CONTEXT.storeUrl)}"><span class="material-symbols-outlined">storefront</span>${DASHBOARD_CONTEXT.hasStoreSlug?'Ver minha vitrine':'Preparar vitrine'}</a></div>`;return}
    d.innerHTML=v30.slice(0,6).map(v=>`
        <div class="row">
            <div class="dot"></div>
            <div class="rb">
                <div class="rn">${esc(v.produto_nome||'#'+v.produto_id)}</div>
                <div class="rm">${v.vendedor_nome?esc(v.vendedor_nome)+' · ':''}${dt(v.criado_em)} · ${v.quantidade} un.</div>
                <span class="status-label"><span class="material-symbols-outlined" style="font-size:.75rem">check_circle</span>Registrada</span>
            </div>
            <div class="rv">${R(v.total)}</div>
        </div>`).join('');
}

/* ── top ── */
function renderTop(v30){
    const d=$('topP'),mp={};
    v30.forEach(v=>{const n=v.produto_nome||'#'+v.produto_id;if(!mp[n])mp[n]={q:0,r:0};mp[n].q+=Number(v.quantidade||0);mp[n].r+=Number(v.total||0)});
    const rank=Object.entries(mp).sort((a,b)=>b[1].r-a[1].r).slice(0,5);
    if(!rank.length){d.innerHTML='<div class="empty"><span class="material-symbols-outlined">emoji_events</span><p><strong>Seus destaques aparecerão aqui</strong><br><span>O ranking será criado conforme as vendas acontecerem.</span></p><a class="empty-action secondary" href="produtos.php"><span class="material-symbols-outlined">inventory_2</span>Ver catálogo</a></div>';return}
    const cl=['au','ag','br','',''];
    d.innerHTML=rank.map(([n,x],i)=>`
        <div class="row">
            <div class="rk ${cl[i]}">${i+1}</div>
            <div class="rb"><div class="rn">${esc(n)}</div><div class="rm">${x.q} unidades</div></div>
            <div class="rv">${R(x.r)}</div>
        </div>`).join('');
}

/* ── low stock ── */
function renderLow(prods){
    const d=$('lowS');
    const b=prods.filter(p=>Number(p.quantidade)<=5).sort((a,z)=>Number(a.quantidade)-Number(z.quantidade));
    $('abadge').textContent=b.length||'0';
    if(!b.length){d.innerHTML=prods.length?'<div class="empty"><span class="material-symbols-outlined">check_circle</span><p>Tudo em ordem!</p></div>':'<div class="empty"><span class="material-symbols-outlined">inventory_2</span><p><strong>Nenhum produto cadastrado</strong><br><span>Adicione produtos para acompanhar o estoque por aqui.</span></p><button class="empty-action" type="button" onclick="document.getElementById(\'btnNovo\').click()"><span class="material-symbols-outlined">add</span>Adicionar produto</button></div>';return}
    d.innerHTML=b.slice(0,5).map(p=>{
        const q=Number(p.quantidade),crit=q===0;
        return`<div class="row">
            <div class="dot ${crit?'e':'w'}"></div>
            <div class="rb"><div class="rn">${esc(p.nome)}</div><div class="rm">Cód: ${esc(p.codigo||'—')}</div></div>
            <span class="badge-s ${crit?'e':''}">${crit?'Zerado':q<=2?'Crítico · '+q+' un.':'Baixo · '+q+' un.'}</span>
            <button class="pc-btn pc-e" style="flex:0 0 auto;padding:.36rem .5rem" aria-label="Editar ${esc(p.nome)}" onclick="openM(estoque.find(x=>x.id==${Number(p.id)}))"><span class="material-symbols-outlined">edit</span></button>
        </div>`;
    }).join('');
}

/* ── products ── */
function renderProds(){
    const d=$('pg'),t=$('busca').value.toLowerCase().trim();
    const f=estoque.filter(p=>(p.nome||'').toLowerCase().includes(t)||(p.codigo||'').toLowerCase().includes(t));
    if(!f.length){d.innerHTML=estoque.length?'<p style="grid-column:1/-1;text-align:center;padding:2rem;color:var(--faint);font-size:.82rem">Nenhum produto encontrado para esta busca</p>':'<div class="empty" style="grid-column:1/-1"><span class="material-symbols-outlined">inventory_2</span><p><strong>Seu catálogo está vazio</strong><br><span>Adicione seus primeiros produtos e comece a montar sua loja.</span></p><button class="empty-action" type="button" onclick="document.getElementById(\'btnNovo\').click()"><span class="material-symbols-outlined">add</span>Adicionar produtos</button></div>';return}
    d.innerHTML='';
    f.slice(0,8).forEach(p=>{
        const q=Number(p.quantidade),sc=q===0?'zero':q<=3?'low':'';
        const sl=q===0?' Zerado':q<=3?`️ ${q} restantes`:`${q} em estoque`;
        const c=document.createElement('div');c.className='pc';
        c.innerHTML=`
            <details class="pc-more"><summary aria-label="Mais ações para ${esc(p.nome)}"><span class="material-symbols-outlined">more_horiz</span></summary><div class="pc-menu"><button type="button" onclick="delProd(${Number(p.id)},'${esc(p.nome.replace(/'/g,"\\'"))}')">Excluir produto</button></div></details>
            <div class="pc-img">${p.imagem?`<img src="${esc(p.imagem)}" alt="${esc(p.nome)}" loading="lazy" decoding="async" onerror="this.hidden=true;this.parentElement.innerHTML='<span class=&quot;material-symbols-outlined&quot;>image_not_supported</span>'">`:'<span class="material-symbols-outlined">image_not_supported</span>'}</div>
            <div class="pc-body">
                <div class="pc-name">${esc(p.nome)}</div>
                <div class="pc-code">Cód: ${esc(p.codigo||'—')}</div>
                <div class="pc-price">${R(p.preco)}</div>
                <div class="pc-stock ${sc}">${sl}</div>
                <div class="pc-acts">
                    <button class="pc-btn pc-e" onclick="openM(estoque.find(x=>x.id==${Number(p.id)}))"><span class="material-symbols-outlined">edit</span>Editar produto</button>
                </div>
            </div>`;
        d.appendChild(c);
    });
}
$('busca').addEventListener('input',renderProds);

/* ── load all ── */
async function loadAll(){
    try{
        const[rP,rV30,rV7,rVd]=await Promise.all([
            fetch('api.php?acao=listar',{credentials:'same-origin'}),
            fetch('api.php?acao=listar_vendas&periodo=30',{credentials:'same-origin'}),
            fetch('api.php?acao=listar_vendas&periodo=7',{credentials:'same-origin'}),
            fetch('api.php?acao=listar_vendedores',{credentials:'same-origin'}),
        ]);
        estoque=(rP.ok?await rP.json():[]) ||[];
        const v30=(rV30.ok?await rV30.json():[]) ||[];
        const v7 =(rV7.ok ?await rV7.json()  :[]) ||[];
        const vds=(rVd.ok ?await rVd.json()  :[]) ||[];
        if(!Array.isArray(estoque))estoque=[];
        renderKPIs(estoque,v30,vds);
        renderSalesChart(v7);
        renderRecent(v30);
        renderTop(v30);
        renderLow(estoque);
        renderProds();
    }catch(e){
        console.error(e);
        $('attention').innerHTML='<div class="dashboard-error">Não foi possível carregar os dados da dashboard. Atualize a página para tentar novamente.</div>';
        $('summary').innerHTML='<div class="empty" style="grid-column:1/-1"><span class="material-symbols-outlined">cloud_off</span><p>Resumo indisponível</p></div>';
    }
}
window.onload=loadAll;
</script>
<script src="assets/onboarding.js?v=20260724"></script>
<script src="assets/changelog-modal.js?v=20260714"></script>
</body>
</html>
