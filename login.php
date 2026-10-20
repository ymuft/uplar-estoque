<?php
// login.php
session_start();
require 'db.php';
require 'helpers.php';

send_security_headers();

if (isset($_SESSION['user_id'])) {
    header("Location: estoque.php");
    exit;
}

get_csrf_token();
$turnstile_config = get_turnstile_config();
$turnstile_enabled = $turnstile_config['enabled'];
$turnstile_site_key = $turnstile_config['site_key'];

$rate_limited = ($_GET['rate_limited'] ?? '') === '1';
$retry_after = filter_var($_GET['retry_after'] ?? 1, FILTER_VALIDATE_INT, [
    'options' => ['default' => 1, 'min_range' => 1, 'max_range' => 900],
]);
if ($rate_limited) {
    http_response_code(429);
    header('Retry-After: ' . $retry_after);
}

// ── Mensagens ────────────────────────────────────────────────
$raw_error = $_GET['error'] ?? '';
$error     = '';

$allowed_prefixes = [
    'Usuário ou senha','Preencha todos','Requisição inválida',
    'Dados inválidos','Sessão expirada','Acesso inválido','Conta bloqueada','Verificação de segurança','Aguarde',
];
if ($raw_error) {
    $decoded = htmlspecialchars(urldecode($raw_error), ENT_QUOTES, 'UTF-8');
    foreach ($allowed_prefixes as $p) {
        if (str_starts_with($decoded, $p)) { $error = $decoded; break; }
    }
}

$success = isset($_GET['registered']) ? 'Conta criada com sucesso! Faça login.' : '';
$logout  = isset($_GET['logout'])     ? 'Você saiu com segurança. Até breve!'  : '';

// O bloqueio é aplicado em auth.php. Manter o GET do login sem consulta ao banco
// reduz o custo de floods que apenas recarregam esta página.
$ip_blocked   = false;
$blocked_secs = 0;

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Uplar — Login</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<?php if ($turnstile_enabled): ?><script src="https://challenges.cloudflare.com/turnstile/v0/api.js" defer></script><?php endif; ?>
<style>
:root{
    --navy:#061a46;--navy-soft:#0a2862;--p:#0877f9;--p-dark:#065fc7;--p-dim:rgba(8,119,249,.09);--p-glow:rgba(8,119,249,.24);
    --cyan:#12b8a6;--cyan-soft:#ddf8f4;--ink:#12213a;--muted:#56647a;--faint:#8795aa;--bg:#f5f8fc;--card:#fff;--border:#dce5f0;
    --ok:#10b981;--ok-dim:rgba(16,185,129,.09);
    --err:#ef4444;--err-dim:rgba(239,68,68,.09);--warn:#f59e0b;
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
body{
    font-family:'Inter',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;-webkit-font-smoothing:antialiased;
}
.material-symbols-outlined{font-variation-settings:'FILL' 0,'wght' 500,'GRAD' 0,'opsz' 24;line-height:1}
.login-shell{min-height:100vh;display:grid;grid-template-columns:minmax(520px,1.25fr) minmax(420px,.75fr)}
.login-story{position:relative;isolation:isolate;overflow:hidden;display:flex;flex-direction:column;padding:clamp(2rem,4vw,4.5rem);color:#fff;background:linear-gradient(140deg,var(--navy) 0%,var(--navy-soft) 64%,#0b3d74 100%)}
.login-story::before{content:'';position:absolute;inset:0;z-index:-2;background-image:linear-gradient(rgba(255,255,255,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.045) 1px,transparent 1px);background-size:42px 42px;mask-image:linear-gradient(120deg,#000,transparent 90%)}
.login-story::after{content:'';position:absolute;z-index:-1;width:440px;height:440px;right:-180px;top:-190px;border-radius:50%;background:rgba(18,184,166,.18);filter:blur(12px)}
.brand{display:inline-flex;align-items:center;gap:.75rem;width:max-content;color:#fff;text-decoration:none}
.brand-mark{width:42px;height:42px;display:grid;place-items:center;border:1px solid rgba(255,255,255,.16);background:rgba(255,255,255,.08);border-radius:11px;color:#68a9ff}
.brand-mark .material-symbols-outlined{font-size:1.55rem}
.brand-name{display:block;font-family:'Plus Jakarta Sans',sans-serif;font-size:1.15rem;font-weight:800;letter-spacing:-.04em}
.brand-meta{display:block;font-family:'JetBrains Mono',monospace;font-size:.58rem;text-transform:uppercase;letter-spacing:.15em;color:rgba(255,255,255,.5);margin-top:.12rem}
.story-content{width:min(100%,900px);margin:auto 0;display:grid;grid-template-columns:minmax(235px,.76fr) minmax(350px,1.24fr);align-items:center;gap:clamp(2rem,4vw,4.5rem);padding:3.5rem 0}
.story-copy{min-width:0}.eyebrow{display:inline-flex;align-items:center;gap:.55rem;font-family:'JetBrains Mono',monospace;font-size:.62rem;text-transform:uppercase;letter-spacing:.14em;color:#8fe3d9;margin-bottom:1.25rem}.eyebrow::before{content:'';width:7px;height:7px;border-radius:50%;background:var(--cyan);box-shadow:0 0 0 6px rgba(18,184,166,.12)}
.story-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:clamp(2.55rem,5vw,4.8rem);line-height:1.02;letter-spacing:-.055em;max-width:9.5ch}.story-title span{color:#68a9ff}
.story-text{max-width:44ch;margin-top:1.4rem;color:rgba(230,239,255,.68);font-size:.95rem;line-height:1.8}
.uplar-film{position:relative;isolation:isolate;width:100%;min-height:350px;align-self:center;perspective:1100px;font-variant-numeric:tabular-nums;pointer-events:none}
.uplar-film::before{content:'';position:absolute;inset:8% -7%;z-index:-1;border-radius:50%;background:radial-gradient(ellipse,rgba(18,184,166,.15),transparent 67%);filter:blur(20px);animation:filmAura 22s ease-in-out infinite}
.film-app{position:absolute;inset:0;display:grid;grid-template-columns:96px 1fr;overflow:hidden;border:1px solid rgba(198,218,247,.48);border-radius:12px;background:#f7faff;color:#14233e;box-shadow:0 30px 70px rgba(0,5,21,.32),0 1px 0 rgba(255,255,255,.22) inset;transform:rotateY(-3deg) rotateX(1deg) scale(.96);transform-origin:center;animation:filmCamera 22s cubic-bezier(.45,0,.2,1) infinite}
.film-sidebar{position:relative;padding:15px 10px;background:#fff;border-right:1px solid #e5ecf5}.film-logo{display:flex;align-items:center;gap:6px;padding:2px 5px 16px;border-bottom:1px solid #edf1f6;color:#0877f9;font-size:10px;font-weight:800;letter-spacing:-.04em}.film-logo span{font-size:15px}.film-nav-label{margin:14px 5px 7px;color:#9aa9bc;font:500 7px 'JetBrains Mono',monospace;letter-spacing:.12em;text-transform:uppercase}.film-nav{position:relative;z-index:1;display:flex;align-items:center;gap:7px;height:29px;margin:2px 0;padding:0 7px;border-radius:5px;color:#718096;font-size:8px;font-weight:600}.film-nav .material-symbols-outlined{font-size:13px}.film-sidebar::after{content:'';position:absolute;left:7px;right:7px;top:53px;height:29px;border-radius:5px;background:rgba(8,119,249,.1);box-shadow:inset 2px 0 #0877f9;animation:filmNav 22s cubic-bezier(.45,0,.2,1) infinite}.film-nav--overview{color:#0877f9;animation:filmOverview 22s linear infinite}.film-nav--stock{animation:filmStockNav 22s linear infinite}.film-nav--sales{animation:filmSalesNav 22s linear infinite}.film-nav--finance{animation:filmFinanceNav 22s linear infinite}
.film-workspace{position:relative;overflow:hidden;padding:18px;background:#f6f9fd}.film-head{display:flex;align-items:flex-start;justify-content:space-between;position:relative;z-index:3}.film-title{font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:800;letter-spacing:-.04em}.film-subtitle{margin-top:3px;color:#7c8aa0;font-size:7px}.film-status{display:flex;align-items:center;gap:4px;padding:5px 7px;border:1px solid #dce8e9;border-radius:99px;background:#effbf8;color:#078577;font:500 6px 'JetBrains Mono',monospace;letter-spacing:.08em}.film-status i{width:5px;height:5px;border-radius:50%;background:#12b8a6;box-shadow:0 0 0 3px rgba(18,184,166,.12)}
.film-kpis{position:relative;z-index:2;display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin-top:13px}.film-kpi{position:relative;overflow:hidden;padding:9px 8px;border:1px solid #e3eaf4;border-radius:7px;background:#fff;box-shadow:0 2px 7px rgba(12,38,79,.035)}.film-kpi::before{content:'';position:absolute;inset:0 auto auto;width:100%;height:2px;background:var(--film-accent,#0877f9)}.film-kpi small{display:block;color:#8290a3;font-size:6px;font-weight:600}.film-kpi strong{display:block;margin-top:5px;font-size:13px;letter-spacing:-.04em}.film-kpi em{display:block;margin-top:3px;color:#0d9a7d;font-size:6px;font-style:normal;font-weight:700}.film-kpi--sales{--film-accent:#12b8a6}.film-kpi--health{--film-accent:#6d8ffb}.film-number{display:block;overflow:hidden;height:16px;line-height:16px}.film-number b{display:block;font:800 13px/16px 'Plus Jakarta Sans',sans-serif;animation:filmRoll 22s steps(1,end) infinite}.film-number--revenue b{animation-name:filmRevenueRoll}
.film-panel{position:absolute;inset:91px 18px 18px;overflow:hidden;border:1px solid #e1e9f3;border-radius:8px;background:#fff;box-shadow:0 4px 12px rgba(12,38,79,.05)}.film-panel-head{display:flex;align-items:center;justify-content:space-between;padding:10px 11px;border-bottom:1px solid #edf1f5;font-size:8px;font-weight:800}.film-panel-head span{color:#8090a4;font-size:6px;font-weight:600}.film-table{padding:0 11px}.film-row{display:grid;grid-template-columns:1fr 45px 55px;align-items:center;gap:5px;min-height:37px;border-bottom:1px solid #eef2f6;font-size:7px}.film-row strong{display:block;font-size:8px}.film-row small{display:block;margin-top:2px;color:#8996a8;font-size:6px}.film-row--low{position:relative;background:linear-gradient(90deg,rgba(245,158,11,.075),transparent);animation:filmLowFocus 22s ease-in-out infinite}.film-qty{font-weight:800;text-align:right}.film-qty b{display:inline-block;animation:filmQtyRoll 22s steps(1,end) infinite}.film-badge{justify-self:end;padding:4px 5px;border-radius:4px;background:#e5f8f2;color:#078577;font:700 5px 'JetBrains Mono',monospace;letter-spacing:.05em}.film-badge--low{background:#fff4dd;color:#b6780b;animation:filmLowBadge 22s ease-in-out infinite}
.film-sales,.film-finance,.film-overview{opacity:0;transform:translateX(20px) scale(.98);pointer-events:none}.film-sales{padding:0 11px;animation:filmSalesScene 22s cubic-bezier(.45,0,.2,1) infinite}.film-sale-row{display:grid;grid-template-columns:1fr auto;align-items:center;gap:7px;padding:10px 0;border-bottom:1px solid #eef2f6;font-size:8px}.film-sale-row strong{display:block}.film-sale-row small{display:block;margin-top:2px;color:#8492a5;font-size:6px}.film-sale-status{padding:4px 5px;border-radius:4px;background:#e8f2ff;color:#0877f9;font:700 5px 'JetBrains Mono',monospace;animation:filmProcess 22s steps(1,end) infinite}.film-sale-status::after{content:'NOVO'}.film-finance{padding:11px;animation:filmFinanceScene 22s cubic-bezier(.45,0,.2,1) infinite}.film-money{padding:10px;border-radius:7px;background:linear-gradient(135deg,#eef6ff,#f8fbff);border:1px solid #deebfb}.film-money small{color:#7c8ca3;font-size:6px}.film-money strong{display:block;margin-top:4px;font-size:16px;letter-spacing:-.05em}.film-money strong b{display:inline-block;animation:filmMoneyRoll 22s steps(1,end) infinite}.film-bars{display:flex;align-items:end;gap:8px;height:56px;margin-top:13px;padding:0 5px;border-bottom:1px solid #e8eef5}.film-bars i{display:block;flex:1;height:var(--h);border-radius:3px 3px 0 0;background:#b9cdf9;transform-origin:bottom}.film-bars i:last-child{background:#12b8a6;animation:filmBar 22s cubic-bezier(.45,0,.2,1) infinite}.film-overview{inset:91px 18px 18px;padding:10px;background:#fff;animation:filmOverviewScene 22s cubic-bezier(.45,0,.2,1) infinite}.film-overview-grid{display:grid;grid-template-columns:1.12fr .88fr;gap:8px;height:100%}.film-chart-card,.film-control-card{padding:9px;border:1px solid #e4ebf4;border-radius:7px}.film-chart-card p,.film-control-card p{font-size:7px;font-weight:800}.film-chart-card small,.film-control-card small{display:block;margin-top:3px;color:#8593a6;font-size:6px}.film-chart{width:100%;height:76px;margin-top:7px;overflow:visible}.film-chart path{fill:none;stroke:#0877f9;stroke-width:3;stroke-linecap:round;stroke-dasharray:180;stroke-dashoffset:180;animation:filmChart 22s cubic-bezier(.45,0,.2,1) infinite}.film-chart circle{fill:#fff;stroke:#12b8a6;stroke-width:3;opacity:0;animation:filmPoint 22s ease-out infinite}.film-control-card{background:#f8fbff}.film-control-card strong{display:block;margin-top:13px;font-size:18px;letter-spacing:-.06em}.film-meter{height:5px;margin-top:8px;overflow:hidden;border-radius:99px;background:#dfe8f4}.film-meter i{display:block;width:92%;height:100%;border-radius:inherit;background:linear-gradient(90deg,#0877f9,#12b8a6);transform-origin:left;animation:filmMeter 22s cubic-bezier(.45,0,.2,1) infinite}
.film-toast{position:absolute;z-index:6;right:14px;top:76px;display:flex;align-items:center;gap:6px;padding:8px 9px;border:1px solid #cce9e4;border-radius:7px;background:#fff;box-shadow:0 10px 22px rgba(12,38,79,.14);color:#31435d;font-size:7px;opacity:0;transform:translateY(-12px) scale(.96);animation:filmToast 22s cubic-bezier(.22,1,.36,1) infinite}.film-toast span{display:grid;place-items:center;width:17px;height:17px;border-radius:5px;background:#e7f8f4;color:#078577}.film-toast .material-symbols-outlined{font-size:11px}.film-toast strong{display:block;font-size:7px}.film-toast small{display:block;margin-top:2px;color:#7c8aa0;font-size:6px}.film-link{position:absolute;z-index:5;top:50%;left:27%;width:48%;height:1px;background:linear-gradient(90deg,transparent,#12b8a6,transparent);transform-origin:left;opacity:0;animation:filmLink 22s ease-in-out infinite}.film-link::after{content:'';position:absolute;right:0;top:-3px;width:7px;height:7px;border-radius:50%;background:#12b8a6;box-shadow:0 0 0 5px rgba(18,184,166,.12)}
.film-overview{position:absolute}
@keyframes filmCamera{0%,8%{transform:rotateY(-3deg) rotateX(1deg) scale(.96) translate(0)}18%,37%{transform:rotateY(-1deg) rotateX(0) scale(1.025) translate(-11px,5px)}42%,59%{transform:rotateY(-1deg) rotateX(0) scale(1.02) translate(-8px,4px)}64%,80%{transform:rotateY(-2deg) rotateX(1deg) scale(1.015) translate(3px,2px)}88%,100%{transform:rotateY(-3deg) rotateX(1deg) scale(.96) translate(0)}}@keyframes filmAura{0%,100%{opacity:.65;transform:scale(.9)}70%{opacity:1;transform:scale(1.05)}}@keyframes filmNav{0%,15%,85%,100%{top:53px}19%,39%{top:84px}43%,61%{top:115px}65%,80%{top:146px}}@keyframes filmOverview{0%,15%,85%,100%{color:#0877f9}16%,84%{color:#718096}}@keyframes filmStockNav{0%,18%,40%,100%{color:#718096}20%,38%{color:#0877f9}}@keyframes filmSalesNav{0%,42%,62%,100%{color:#718096}44%,60%{color:#0877f9}}@keyframes filmFinanceNav{0%,64%,82%,100%{color:#718096}66%,80%{color:#0877f9}}@keyframes filmLowFocus{0%,20%,100%{box-shadow:none}23%,33%{box-shadow:inset 3px 0 #f59e0b,0 0 0 1px rgba(245,158,11,.13)}}@keyframes filmLowBadge{0%,21%,100%{transform:none;opacity:1}23%,33%{transform:scale(1.06);box-shadow:0 0 0 4px rgba(245,158,11,.1)}}@keyframes filmQtyRoll{0%,28%,100%{transform:translateY(0)}29%,31%{transform:translateY(-16px)}32%,100%{transform:translateY(-16px)}}@keyframes filmRoll{0%,48%,100%{transform:translateY(0)}49%,51%{transform:translateY(-16px)}52%,100%{transform:translateY(-16px)}}@keyframes filmRevenueRoll{0%,68%,100%{transform:translateY(0)}69%,71%{transform:translateY(-16px)}72%,100%{transform:translateY(-16px)}}@keyframes filmSalesScene{0%,40%,62%,100%{opacity:0;transform:translateX(20px) scale(.98)}44%,59%{opacity:1;transform:none}}@keyframes filmFinanceScene{0%,62%,82%,100%{opacity:0;transform:translateX(20px) scale(.98)}66%,79%{opacity:1;transform:none}}@keyframes filmOverviewScene{0%,10%{opacity:1;transform:none}14%,82%{opacity:0;transform:translateX(-16px) scale(.98)}86%,100%{opacity:1;transform:none}}@keyframes filmToast{0%,27%,37%,100%{opacity:0;transform:translateY(-12px) scale(.96)}29%,35%{opacity:1;transform:none}}@keyframes filmLink{0%,28%,39%,100%{opacity:0;transform:scaleX(.2)}30%,35%{opacity:1;transform:scaleX(1)}}@keyframes filmProcess{0%,47%{background:#e8f2ff;color:#0877f9}48%,100%{background:#e5f8f2;color:#078577}}@keyframes filmMoneyRoll{0%,68%{transform:translateY(0)}69%,71%{transform:translateY(-20px)}72%,100%{transform:translateY(-20px)}}@keyframes filmBar{0%,68%{transform:scaleY(.58)}70%,100%{transform:scaleY(1)}}@keyframes filmChart{0%,84%{stroke-dashoffset:180}87%,100%{stroke-dashoffset:0}}@keyframes filmPoint{0%,91%{opacity:0;transform:scale(.5)}93%,100%{opacity:1;transform:scale(1)}}@keyframes filmMeter{0%,85%{transform:scaleX(.68)}89%,100%{transform:scaleX(1)}}
.story-foot{font-family:'JetBrains Mono',monospace;font-size:.55rem;text-transform:uppercase;letter-spacing:.14em;color:rgba(255,255,255,.35)}
.login-access{position:relative;display:flex;align-items:center;justify-content:center;padding:clamp(1.5rem,4vw,4.5rem);background:radial-gradient(circle at 100% 0,rgba(8,119,249,.08),transparent 28%),var(--bg)}
.login-access::before{content:'';position:absolute;inset:0;pointer-events:none;background-image:linear-gradient(rgba(8,119,249,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(8,119,249,.035) 1px,transparent 1px);background-size:34px 34px;mask-image:linear-gradient(to bottom,#000,transparent 80%)}
.login-panel{position:relative;z-index:1;width:100%;max-width:430px;animation:panelIn .55s cubic-bezier(.22,1,.36,1) both}
@keyframes panelIn{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}
.mobile-brand{display:none;margin-bottom:2.3rem}.mobile-brand .brand-mark{background:#fff;border-color:var(--border);box-shadow:0 10px 28px rgba(6,26,70,.08)}.panel-kicker{font-family:'JetBrains Mono',monospace;color:var(--p);font-size:.64rem;font-weight:500;letter-spacing:.14em;text-transform:uppercase;margin-bottom:.75rem}
.title{font-family:'Plus Jakarta Sans',sans-serif;font-size:clamp(2rem,3.5vw,2.75rem);font-weight:800;line-height:1.08;letter-spacing:-.05em;margin-bottom:.7rem}
.subtitle{color:var(--muted);font-size:.88rem;margin-bottom:2rem;line-height:1.65}

/* Alerts */
.alert{
    display:flex;align-items:flex-start;gap:.65rem;padding:.85rem 1rem;
    border-radius:8px;font-size:.82rem;margin-bottom:1.1rem;line-height:1.45;
    animation:alertIn .3s cubic-bezier(.22,1,.36,1) both;
}
@keyframes alertIn{from{opacity:0;transform:translateY(-5px)}to{opacity:1;transform:none}}
.alert svg{flex-shrink:0;margin-top:1px;width:18px;height:18px}
.alert.ok {background:var(--ok-dim); color:#065f46;border:1px solid rgba(16,185,129,.2)}
.alert.err{background:var(--err-dim);color:#991b1b;border:1px solid rgba(239,68,68,.2)}
.alert.warn{background:rgba(245,158,11,.08);color:#92400e;border:1px solid rgba(245,158,11,.2)}

/* Lockout */
.lockout-box{
    background:var(--err-dim);border:1px solid rgba(239,68,68,.2);
    border-radius:10px;padding:1.3rem;margin-bottom:1.4rem;text-align:center;
}
.lockout-title{font-size:.9rem;font-weight:700;color:#991b1b;margin:.5rem 0 .25rem}
.lockout-sub{font-size:.78rem;color:#b91c1c;line-height:1.45}
.countdown{
    display:inline-flex;align-items:center;gap:.4rem;
    background:rgba(239,68,68,.12);border-radius:8px;padding:.42rem .9rem;
    margin-top:.75rem;font-weight:700;font-size:.88rem;color:var(--err);
}
.countdown svg{width:15px;height:15px;flex-shrink:0}

/* Fields */
.form-group{margin-bottom:1.1rem}.form-label{display:block;margin-bottom:.48rem;color:var(--ink);font-size:.72rem;font-weight:600}.field-wrap{position:relative}
.field-ico{position:absolute;left:1rem;top:50%;transform:translateY(-50%);color:var(--faint);pointer-events:none;transition:color .2s;display:flex;align-items:center;width:20px;height:20px}
.field-ico svg{width:18px;height:18px}
.field-wrap:focus-within .field-ico{color:var(--p)}
.form-group input{width:100%;min-height:52px;padding:.85rem 2.9rem;border:1px solid var(--border);border-radius:8px;font-size:.88rem;font-family:inherit;color:var(--ink);background:rgba(255,255,255,.92);outline:none;transition:border-color .2s,box-shadow .2s,background .2s}
.form-group input::placeholder{color:#9aa7b8}.form-group input:hover{border-color:#c5d2e2}.form-group input:focus{border-color:var(--p);background:#fff;box-shadow:0 0 0 4px var(--p-dim)}
.pw-btn{
    position:absolute;right:.8rem;top:50%;transform:translateY(-50%);
    background:none;border:none;cursor:pointer;color:var(--faint);
    display:flex;align-items:center;padding:.2rem;transition:color .18s;
}
.pw-btn:hover{color:var(--p)}
.pw-btn svg{width:18px;height:18px}

/* CapsLock */
.caps-hint{display:none;font-size:.7rem;color:var(--warn);margin-top:.3rem;align-items:center;gap:.3rem}
.caps-hint svg{width:14px;height:14px;flex-shrink:0}
.caps-hint.on{display:flex}
.turnstile-wrap{display:flex;min-height:65px;align-items:center;justify-content:center;margin:.2rem 0 .8rem;overflow:hidden}

/* Button */
.btn-login{
    width:100%;background:var(--p);color:#fff;border:none;
    min-height:52px;padding:.9rem 1.5rem;border-radius:8px;font-size:.9rem;font-weight:700;
    font-family:inherit;cursor:pointer;margin-top:.4rem;
    display:flex;align-items:center;justify-content:center;gap:.6rem;
    transition:all .25s cubic-bezier(.22,1,.36,1);
    box-shadow:0 4px 16px var(--p-glow);position:relative;overflow:hidden;
}
.btn-login::after{content:'';position:absolute;inset:0;background:linear-gradient(rgba(255,255,255,.1),transparent);pointer-events:none}
.btn-login:hover:not(:disabled){background:var(--p-dark);transform:translateY(-2px);box-shadow:0 12px 28px var(--p-glow)}
.btn-login:active:not(:disabled){transform:translateY(0)}
.btn-login:disabled{background:var(--faint);box-shadow:none;cursor:not-allowed;transform:none}
.btn-login svg{width:18px;height:18px;flex-shrink:0}
.spinner{
    width:18px;height:18px;border:2px solid rgba(255,255,255,.35);
    border-top-color:#fff;border-radius:50%;animation:spin .65s linear infinite;flex-shrink:0;
}
@keyframes spin{to{transform:rotate(360deg)}}

/* Footer */
.account-link{margin-top:1.35rem;text-align:center;font-size:.78rem;color:var(--muted);line-height:1.6}.account-link a{color:var(--p);font-weight:700;text-decoration:none}.account-link a:hover{text-decoration:underline}
.sec-badge{display:flex;align-items:center;justify-content:center;gap:.45rem;margin-top:1.5rem;padding-top:1.2rem;border-top:1px solid var(--border);font-size:.67rem;color:var(--faint)}
.sec-badge svg{width:14px;height:14px;fill:var(--ok)}
/* O painel azul inteiro e o palco; a copy permanece em uma zona segura acima da interface. */
.story-content{display:block;width:100%;margin:auto 0;padding:3.5rem 0}.story-copy{position:relative;z-index:3;width:min(42%,310px)}
.uplar-film{position:absolute;inset:0;z-index:1;width:auto;height:auto;min-height:0;overflow:hidden;border-radius:0;mask-image:none;pointer-events:none}.uplar-film::before{right:-8%;top:18%;width:58%;height:58%;filter:blur(32px)}
.film-app{left:clamp(40%,44vw,48%);top:clamp(16%,20vh,22%);right:auto;bottom:auto;width:min(720px,72vw);height:clamp(380px,52vh,470px);grid-template-columns:126px 1fr;border-radius:14px;box-shadow:0 24px 58px rgba(2,19,54,.18);animation:filmHeroCamera 24s cubic-bezier(.45,0,.2,1) infinite}
@keyframes filmHeroCamera{0%,10%,100%{transform:translate3d(0,0,0) scale(.92)}15%,34%{transform:translate3d(-5%,-1%,0) scale(1)}39%,56%{transform:translate3d(-10%,-2%,0) scale(1.03)}61%,77%{transform:translate3d(-14%,-2%,0) scale(1.01)}82%,91%{transform:translate3d(-2%,0,0) scale(.94)}}
/* Viewport cinematografico: o sistema e maior que a area visivel e a camera navega pelo stage. */
.uplar-film{height:382px;min-height:382px;overflow:hidden;border-radius:14px;mask-image:linear-gradient(90deg,#000 93%,transparent 100%)}
.film-app{inset:0 auto auto 0;width:610px;height:426px;grid-template-columns:118px 1fr;border-color:rgba(170,205,255,.42);box-shadow:0 24px 58px rgba(0,5,21,.22);transform-origin:top left;animation:filmCameraV2 24s cubic-bezier(.45,0,.2,1) infinite}
.film-sidebar{padding:18px 12px}.film-logo{padding-bottom:19px;font-size:12px}.film-nav-label{margin-top:17px}.film-nav{height:34px;margin:3px 0;font-size:9px}.film-nav .material-symbols-outlined{font-size:15px}.film-sidebar::after{left:8px;right:8px;top:62px;height:34px;animation:filmNavV2 24s cubic-bezier(.45,0,.2,1) infinite}
.film-nav--overview{animation:filmOverviewV2 24s linear infinite}.film-nav--stock{animation:filmStockV2 24s linear infinite}.film-nav--sales{animation:filmSalesV2 24s linear infinite}.film-nav--finance{animation:filmFinanceV2 24s linear infinite}
.film-workspace{padding:22px}.film-title{font-size:16px}.film-subtitle{font-size:8px}.film-status{padding:6px 9px;font-size:7px}.film-kpis{gap:10px;margin-top:18px}.film-kpi{padding:11px 10px;border-radius:8px}.film-kpi small,.film-kpi em{font-size:7px}.film-kpi strong,.film-number b{font-size:16px;line-height:19px}.film-number{height:19px}.film-panel{inset:118px 22px 22px;border-radius:10px}.film-panel-head{padding:13px 14px;font-size:10px}.film-panel-head span{font-size:7px}.film-table{padding:0 14px}.film-row{grid-template-columns:1fr 52px 72px;min-height:46px;font-size:8px}.film-row strong{font-size:10px}.film-row small{font-size:7px}.film-badge{padding:5px 6px;font-size:6px}.film-sales{padding:0 14px}.film-sale-row{padding:13px 0;font-size:10px}.film-sale-row small{font-size:7px}.film-finance{padding:14px}.film-money{padding:14px}.film-money small{font-size:8px}.film-money strong{font-size:21px}.film-bars{height:76px;margin-top:18px}.film-overview{inset:118px 22px 22px;padding:14px}.film-chart-card,.film-control-card{padding:13px}.film-chart-card p,.film-control-card p{font-size:9px}.film-chart-card small,.film-control-card small{font-size:7px}.film-chart{height:116px;margin-top:10px}.film-control-card strong{margin-top:20px;font-size:24px}.film-meter{display:none}.film-control-card::after{content:'';display:block;width:34px;height:34px;margin-top:12px;border:5px solid #d9e9fb;border-top-color:#12b8a6;border-right-color:#0877f9;border-radius:50%;transform:rotate(36deg)}.film-toast{right:20px;top:96px;padding:10px 12px;font-size:8px}.film-toast strong{font-size:8px}.film-toast small{font-size:7px}.film-link{top:55%}
@keyframes filmCameraV2{0%,9%,100%{transform:translate(0,0) scale(.78)}14%,34%{transform:translate(-54px,-28px) scale(.93)}38%,55%{transform:translate(-96px,-40px) scale(.95)}59%,75%{transform:translate(-138px,-45px) scale(.96)}80%,91%{transform:translate(-10px,-4px) scale(.8)}}
@keyframes filmNavV2{0%,12%,87%,100%{top:62px}16%,35%{top:99px}39%,56%{top:136px}60%,77%{top:173px}81%,86%{top:62px}}
@keyframes filmOverviewV2{0%,12%,87%,100%{color:#0877f9}13%,86%{color:#718096}}@keyframes filmStockV2{0%,15%,36%,100%{color:#718096}17%,34%{color:#0877f9}}@keyframes filmSalesV2{0%,38%,57%,100%{color:#718096}40%,55%{color:#0877f9}}@keyframes filmFinanceV2{0%,59%,78%,100%{color:#718096}61%,76%{color:#0877f9}}
@keyframes filmSalesScene{0%,36%,58%,100%{opacity:0;transform:translateX(36px) scale(.98)}40%,55%{opacity:1;transform:none}}@keyframes filmFinanceScene{0%,57%,78%,100%{opacity:0;transform:translateX(36px) scale(.98)}61%,75%{opacity:1;transform:none}}@keyframes filmOverviewScene{0%,11%{opacity:1;transform:none}15%,79%{opacity:0;transform:translateX(-30px) scale(.98)}83%,100%{opacity:1;transform:none}}@keyframes filmToast{0%,27%,36%,100%{opacity:0;transform:translateY(-15px) scale(.96)}29%,34%{opacity:1;transform:none}}@keyframes filmLink{0%,28%,38%,100%{opacity:0;transform:scaleX(.2)}30%,35%{opacity:1;transform:scaleX(1)}}
/* O primeiro e o ultimo quadro compartilham o dashboard consolidado para o loop ser continuo. */
@keyframes filmChart{0%,10%,100%{stroke-dashoffset:0}14%,84%{stroke-dashoffset:180}87%,99%{stroke-dashoffset:0}}
@keyframes filmPoint{0%,10%,100%{opacity:1;transform:scale(1)}14%,91%{opacity:0;transform:scale(.5)}93%,99%{opacity:1;transform:scale(1)}}
@keyframes filmMeter{0%,10%,100%{transform:scaleX(1)}14%,85%{transform:scaleX(.68)}89%,99%{transform:scaleX(1)}}
.story-content{display:block;width:100%;margin:auto 0;padding:3.5rem 0}.story-copy{position:relative;z-index:3;width:min(42%,310px)}.uplar-film{position:absolute;inset:0;z-index:1;width:auto;height:auto;min-height:0;overflow:hidden;border-radius:0;mask-image:none}.film-app{left:clamp(40%,44vw,48%);top:clamp(16%,20vh,22%);width:min(720px,72vw);height:clamp(380px,52vh,470px);grid-template-columns:126px 1fr;border-radius:14px;box-shadow:0 24px 58px rgba(2,19,54,.18);animation:filmHeroCamera 24s cubic-bezier(.45,0,.2,1) infinite}@keyframes filmHeroCamera{0%,10%,100%{transform:translate3d(0,0,0) scale(.92)}15%,34%{transform:translate3d(-5%,-1%,0) scale(1)}39%,56%{transform:translate3d(-10%,-2%,0) scale(1.03)}61%,77%{transform:translate3d(-14%,-2%,0) scale(1.01)}82%,91%{transform:translate3d(-2%,0,0) scale(.94)}}
/* Camadas do hero: fundo, produto e copy segura. */
.login-story{isolation:isolate}.story-copy{z-index:4}
@media(max-width:900px){.login-shell{grid-template-columns:1fr}.login-story{display:none}.login-access{min-height:100vh}.mobile-brand{display:flex}.login-panel{max-width:440px}}
@media(max-width:560px){.login-access{align-items:flex-start;padding:1.4rem 1.25rem 2rem}.login-panel{padding-top:.5rem}.mobile-brand{margin-bottom:3rem}.title{font-size:2rem}.subtitle{margin-bottom:1.6rem}.form-group input{font-size:16px}}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}.film-app{transform:none}.film-stock-panel,.film-sales,.film-finance,.film-toast,.film-link{display:none}.film-overview{opacity:1;transform:none}.film-chart path{stroke-dashoffset:0}.film-chart circle{opacity:1;transform:none}.film-meter i{transform:none}}

/* Product film de producao: o stage nunca depende da timeline para existir. */
@media(min-width:901px){
  .login-story{min-height:100vh}
  .uplar-film{position:absolute;inset:0;display:block;min-height:720px;overflow:hidden;visibility:visible;opacity:1;z-index:1;perspective:1200px;pointer-events:none}
  .uplar-film::before{inset:auto -12% 8% 35%;height:48%;background:radial-gradient(ellipse,rgba(18,184,166,.16),transparent 70%);filter:blur(30px);animation:prodAmbient 24s ease-in-out infinite}
  .film-app{position:absolute;top:clamp(114px,16vh,156px);left:42%;right:auto;bottom:auto;width:59%;height:clamp(380px,51vh,470px);display:grid;grid-template-columns:108px 1fr;overflow:hidden;border:1px solid rgba(191,215,247,.62);border-radius:14px;background:#f7faff;color:#14233e;box-shadow:0 16px 40px rgba(2,19,54,.15),0 1px 0 rgba(255,255,255,.7) inset;transform-origin:center;animation:prodCamera 24s cubic-bezier(.45,0,.2,1) infinite}
  .film-sidebar{padding:16px 10px}.film-logo{padding-bottom:17px;font-size:11px}.film-nav-label{margin-top:15px}.film-nav{height:32px;margin:3px 0;padding:0 8px;font-size:8px}.film-nav .material-symbols-outlined{font-size:14px}.film-sidebar::after{z-index:0;left:7px;right:7px;top:58px;height:32px;box-shadow:inset 2px 0 #0877f9;animation:prodNav 24s cubic-bezier(.45,0,.2,1) infinite}
  .film-nav--overview{animation:prodOverviewNav 24s linear infinite}.film-nav--stock{animation:prodStockNav 24s linear infinite}.film-nav--sales{animation:prodSalesNav 24s linear infinite}.film-nav--finance{animation:prodFinanceNav 24s linear infinite}
  .film-workspace{padding:18px}.film-head{align-items:center}.film-title{font-size:15px}.film-subtitle{font-size:8px}.film-status{margin-right:1px;padding:6px 9px;font-size:7px}.film-kpis{grid-auto-rows:1fr;gap:8px;margin-top:14px}.film-kpi{min-height:70px;padding:10px 9px}.film-kpi small,.film-kpi em{font-size:7px}.film-kpi strong,.film-number b{font-size:15px;line-height:18px}.film-number{height:18px}.film-panel{inset:105px 18px 18px}.film-panel-head{padding:11px 12px;font-size:9px}.film-panel-head span{font-size:7px}.film-table{padding:0 12px}.film-row{grid-template-columns:1fr 48px 66px;min-height:43px;font-size:8px}.film-row strong{font-size:9px}.film-row small{font-size:7px}.film-badge{font-size:6px}.film-sales{padding:0 12px}.film-sale-row{min-height:48px;padding:11px 0;font-size:9px}.film-sale-row small{font-size:7px}.film-finance{padding:12px}.film-money{padding:12px}.film-money small{font-size:7px}.film-money strong{font-size:18px}.film-bars{height:66px;margin-top:15px}.film-overview{position:absolute;inset:105px 18px 18px;padding:11px;opacity:1;transform:none;animation:prodOverview 24s cubic-bezier(.45,0,.2,1) infinite}.film-chart-card,.film-control-card{padding:10px}.film-chart-card p,.film-control-card p{font-size:8px}.film-chart-card small,.film-control-card small{font-size:7px}.film-chart{height:90px;margin-top:7px}.film-control-card strong{font-size:20px;margin-top:14px}.film-control-card::after{width:30px;height:30px;margin-top:9px}.film-toast{right:18px;top:86px;padding:9px 10px;font-size:8px;animation:prodToast 24s cubic-bezier(.22,1,.36,1) infinite}.film-toast strong{font-size:8px}.film-toast small{font-size:7px}.film-link{top:54%;animation:prodLink 24s ease-in-out infinite}
  .film-stock-panel{opacity:0;transform:translate3d(34px,0,0);animation:prodStock 24s cubic-bezier(.45,0,.2,1) infinite}.film-sales{opacity:0;transform:translate3d(34px,0,0);animation:prodSales 24s cubic-bezier(.45,0,.2,1) infinite}.film-finance{opacity:0;transform:translate3d(34px,0,0);animation:prodFinance 24s cubic-bezier(.45,0,.2,1) infinite}.film-row--low{animation:prodRowFocus 24s ease-in-out infinite}.film-qty b{animation:prodQty 24s steps(1,end) infinite}.film-number b{animation:prodSalesCount 24s steps(1,end) infinite}.film-money strong b{animation:prodMoney 24s steps(1,end) infinite}.film-sale-status{animation:prodProcess 24s steps(1,end) infinite}.film-chart path{animation:prodChart 24s cubic-bezier(.45,0,.2,1) infinite}.film-chart circle{animation:prodPoint 24s ease-out infinite}
  @keyframes prodAmbient{0%,100%{opacity:.55;transform:translate3d(0,0,0)}50%{opacity:1;transform:translate3d(-12px,-5px,0)}}@keyframes prodCamera{0%,12%,100%{transform:translate3d(0,0,0) scale(.90)}16%,35%{transform:translate3d(-3%,-1%,0) scale(.96)}40%,57%{transform:translate3d(-6%,-2%,0) scale(.98)}62%,78%{transform:translate3d(-7%,-2%,0) scale(.97)}83%,92%{transform:translate3d(0,0,0) scale(.91)}}@keyframes prodNav{0%,13%,87%,100%{top:58px}17%,36%{top:93px}41%,58%{top:128px}63%,79%{top:163px}83%,86%{top:58px}}@keyframes prodOverviewNav{0%,13%,87%,100%{color:#0877f9}14%,86%{color:#718096}}@keyframes prodStockNav{0%,16%,37%,100%{color:#718096}18%,35%{color:#0877f9}}@keyframes prodSalesNav{0%,40%,59%,100%{color:#718096}42%,57%{color:#0877f9}}@keyframes prodFinanceNav{0%,62%,80%,100%{color:#718096}64%,78%{color:#0877f9}}
  @keyframes prodOverview{0%,13%,100%{opacity:1;transform:none}17%,81%{opacity:0;transform:translate3d(-22px,0,0)}85%,96%{opacity:1;transform:none}}@keyframes prodStock{0%,15%,38%,100%{opacity:0;transform:translate3d(34px,0,0)}19%,36%{opacity:1;transform:none}}@keyframes prodSales{0%,39%,60%,100%{opacity:0;transform:translate3d(34px,0,0)}43%,58%{opacity:1;transform:none}}@keyframes prodFinance{0%,61%,81%,100%{opacity:0;transform:translate3d(34px,0,0)}65%,79%{opacity:1;transform:none}}@keyframes prodToast{0%,28%,37%,100%{opacity:0;transform:translate3d(12px,-12px,0) scale(.97)}30%,35%{opacity:1;transform:none}}@keyframes prodLink{0%,29%,38%,100%{opacity:0;transform:scaleX(.2)}31%,36%{opacity:1;transform:scaleX(1)}}@keyframes prodRowFocus{0%,29%,100%{box-shadow:none}31%,36%{box-shadow:inset 3px 0 #f59e0b,0 0 0 1px rgba(245,158,11,.15)}}@keyframes prodQty{0%,31%{transform:translateY(0)}32%,100%{transform:translateY(-18px)}}@keyframes prodSalesCount{0%,55%{transform:translateY(0)}56%,100%{transform:translateY(-18px)}}@keyframes prodMoney{0%,75%{transform:translateY(0)}76%,100%{transform:translateY(-22px)}}@keyframes prodProcess{0%,51%{background:#e8f2ff;color:#0877f9}52%,100%{background:#e5f8f2;color:#078577}}@keyframes prodChart{0%,13%{stroke-dashoffset:0}17%,83%{stroke-dashoffset:180}88%,100%{stroke-dashoffset:0}}@keyframes prodPoint{0%,13%,100%{opacity:1;transform:scale(1)}17%,91%{opacity:0;transform:scale(.5)}94%,99%{opacity:1;transform:scale(1)}}
}
@media(prefers-reduced-motion:reduce){.uplar-film{min-height:720px}.film-app{animation:none;transform:translate3d(0,0,0) scale(.94)}.film-overview{display:block;opacity:1;transform:none;animation:none}.film-stock-panel,.film-sales,.film-finance,.film-toast,.film-link{display:none}.film-chart path{stroke-dashoffset:0;animation:none}.film-chart circle{opacity:1;transform:none;animation:none}.film-number b{transform:translateY(-18px);animation:none}.film-money strong b{transform:translateY(-22px);animation:none}.film-status i{box-shadow:0 0 0 3px rgba(18,184,166,.12)}}

/* Acabamento operacional GearUP: interface escura integrada ao hero, sem moldura de mockup. */
@media(min-width:901px){
  .film-app{border-color:rgba(169,200,190,.16);background:#152319;box-shadow:0 18px 42px rgba(0,11,8,.26),0 1px 0 rgba(255,255,255,.05) inset}
  .film-sidebar{background:#111b14;border-right-color:rgba(232,241,226,.08)}.film-logo{border-bottom-color:rgba(232,241,226,.08);color:#a7d66a}.film-nav-label{color:#6f7e71}.film-nav{color:#aab6aa}.film-nav .material-symbols-outlined{color:#829584}.film-nav-indicator{position:absolute;z-index:0;left:7px;right:7px;top:93px;height:32px;border:1px solid rgba(167,214,106,.17);border-radius:7px;background:rgba(167,214,106,.13);box-shadow:inset 2px 0 #a7d66a;transform:translate3d(0,0,0);animation:gearupNav 24s cubic-bezier(.45,0,.2,1) infinite}.film-sidebar::after{display:none}.film-nav{z-index:1}.film-nav--overview{animation:gearupOverview 24s linear infinite}.film-nav--stock{animation:gearupStock 24s linear infinite}.film-nav--sales{animation:gearupSales 24s linear infinite}.film-nav--finance{animation:gearupFinance 24s linear infinite}
  .film-workspace{background:#18251c}.film-title{color:#f0f5ed}.film-subtitle{color:#91a292}.film-status{border-color:rgba(167,214,106,.2);background:rgba(167,214,106,.1);color:#b7df87}.film-status i{background:#a7d66a;box-shadow:0 0 0 3px rgba(167,214,106,.12)}.film-kpi,.film-panel,.film-overview,.film-chart-card{border-color:rgba(232,241,226,.09);background:#1d2c21;box-shadow:none}.film-kpi small,.film-panel-head span,.film-row small,.film-sale-row small,.film-chart-card small,.film-control-card small{color:#91a292}.film-kpi strong,.film-number b,.film-panel-head,.film-row strong,.film-sale-row strong,.film-chart-card p,.film-control-card p{color:#f0f5ed}.film-kpi em{color:#a7d66a}.film-kpi::before{background:var(--film-accent,#a7d66a)}.film-kpi--sales{--film-accent:#a7d66a}.film-kpi--health{--film-accent:#7cc9a5}
  .film-panel-head,.film-row,.film-sale-row{border-color:rgba(232,241,226,.08)}.film-row--low{background:linear-gradient(90deg,rgba(213,138,23,.16),transparent)}.film-badge{background:rgba(167,214,106,.12);color:#b7df87}.film-badge--low{background:rgba(213,138,23,.16);color:#e8b865}.film-sale-status{background:rgba(167,214,106,.14);color:#b7df87}.film-money{border-color:rgba(167,214,106,.14);background:linear-gradient(135deg,rgba(167,214,106,.12),rgba(29,44,33,.88))}.film-money small{color:#a8b8a9}.film-money strong{color:#f0f5ed}.film-bars{border-bottom-color:rgba(232,241,226,.1)}.film-bars i{background:#49634e}.film-bars i:last-child{background:#a7d66a}.film-control-card{border-color:rgba(232,241,226,.09);background:#1a281e}.film-control-card::after{border-color:#35543b;border-top-color:#a7d66a;border-right-color:#7cc9a5}.film-chart path{stroke:#a7d66a}.film-chart circle{fill:#1d2c21;stroke:#a7d66a}.film-toast{border-color:rgba(167,214,106,.22);background:#203024;box-shadow:0 12px 24px rgba(0,11,8,.25);color:#eff7ea}.film-toast span{background:rgba(167,214,106,.15);color:#b7df87}.film-toast small{color:#a1b3a2}.film-link{background:linear-gradient(90deg,transparent,#a7d66a,transparent)}.film-link::after{background:#a7d66a;box-shadow:0 0 0 5px rgba(167,214,106,.12)}
  @keyframes gearupNav{0%,13%,87%,100%{transform:translate3d(0,-35px,0)}17%,36%{transform:translate3d(0,0,0)}41%,58%{transform:translate3d(0,35px,0)}63%,79%{transform:translate3d(0,70px,0)}83%,86%{transform:translate3d(0,-35px,0)}}@keyframes gearupOverview{0%,13%,87%,100%{color:#eaf5e5}14%,86%{color:#aab6aa}}@keyframes gearupStock{0%,16%,37%,100%{color:#aab6aa}18%,35%{color:#eaf5e5}}@keyframes gearupSales{0%,40%,59%,100%{color:#aab6aa}42%,57%{color:#eaf5e5}}@keyframes gearupFinance{0%,62%,80%,100%{color:#aab6aa}64%,78%{color:#eaf5e5}}
}

/* Acabamento final claro: o produto segue a identidade azul original do Uplar. */
@media(min-width:901px){
  .film-app{background:#f7faff;border-color:rgba(191,215,247,.62);box-shadow:0 16px 40px rgba(2,19,54,.15),0 1px 0 rgba(255,255,255,.7) inset}
  .film-sidebar{background:#fff;border-right-color:#e5ecf5}.film-logo{border-bottom-color:#edf1f6;color:#0877f9}.film-nav-label{color:#9aa9bc}.film-nav{color:#718096}.film-nav .material-symbols-outlined{color:inherit}.film-nav-indicator{left:7px;right:7px;top:58px;height:32px;border:0;border-radius:7px;background:rgba(8,119,249,.09);box-shadow:inset 2px 0 #0877f9;animation:uplarNavIndicator 24s cubic-bezier(.45,0,.2,1) infinite}.film-nav--overview{animation:uplarOverviewNav 24s linear infinite}.film-nav--stock{animation:uplarStockNav 24s linear infinite}.film-nav--sales{animation:uplarSalesNav 24s linear infinite}.film-nav--finance{animation:uplarFinanceNav 24s linear infinite}
  .film-workspace{background:#f6f9fd}.film-title,.film-kpi strong,.film-number b,.film-panel-head,.film-row strong,.film-sale-row strong,.film-chart-card p,.film-control-card p{color:#14233e}.film-subtitle,.film-kpi small,.film-panel-head span,.film-row small,.film-sale-row small,.film-chart-card small,.film-control-card small{color:#8190a4}.film-status{border-color:#dce8e9;background:#effbf8;color:#078577}.film-status i{background:#12b8a6;box-shadow:0 0 0 3px rgba(18,184,166,.12)}.film-kpi,.film-panel,.film-overview,.film-chart-card{border-color:#e3eaf4;background:#fff;box-shadow:0 2px 7px rgba(12,38,79,.035)}.film-kpi::before{background:var(--film-accent,#0877f9)}.film-kpi--sales{--film-accent:#12b8a6}.film-kpi--health{--film-accent:#6d8ffb}.film-kpi em{color:#0d9a7d}.film-panel-head,.film-row,.film-sale-row{border-color:#eef2f6}.film-row--low{background:linear-gradient(90deg,rgba(245,158,11,.075),transparent)}.film-badge{background:#e5f8f2;color:#078577}.film-badge--low{background:#fff4dd;color:#b6780b}.film-sale-status{background:#e8f2ff;color:#0877f9}.film-money{border-color:#deebfb;background:linear-gradient(135deg,#eef6ff,#f8fbff)}.film-money small{color:#7c8ca3}.film-money strong{color:#14233e}.film-bars{border-bottom-color:#e8eef5}.film-bars i{background:#b9cdf9}.film-bars i:last-child{background:#12b8a6}.film-control-card{border-color:#e4ebf4;background:#f8fbff}.film-control-card::after{border-color:#d9e9fb;border-top-color:#12b8a6;border-right-color:#0877f9}.film-chart path{stroke:#0877f9}.film-chart circle{fill:#fff;stroke:#12b8a6}.film-toast{border-color:#cce9e4;background:#fff;box-shadow:0 10px 22px rgba(12,38,79,.14);color:#31435d}.film-toast span{background:#e7f8f4;color:#078577}.film-toast small{color:#7c8aa0}.film-link{background:linear-gradient(90deg,transparent,#12b8a6,transparent)}.film-link::after{background:#12b8a6;box-shadow:0 0 0 5px rgba(18,184,166,.12)}
  .film-kpis{align-items:stretch;grid-auto-rows:74px}.film-kpi{height:74px;display:flex;flex-direction:column}.film-kpi strong,.film-number{margin-top:5px}.film-kpi em{margin-top:auto}.film-overview-grid{align-items:stretch}.film-chart-card,.film-control-card{min-height:0}.film-chart-card{display:flex;min-width:0;flex-direction:column}.film-chart{flex:1;min-height:0;height:auto;margin:8px 0 0}.film-control-card{display:flex;flex-direction:column}.film-control-card::after{margin-top:auto}
  @keyframes uplarNavIndicator{0%,13%,87%,100%{top:58px}17%,36%{top:93px}41%,58%{top:128px}63%,79%{top:163px}83%,86%{top:58px}}@keyframes uplarOverviewNav{0%,13%,87%,100%{color:#0877f9}14%,86%{color:#718096}}@keyframes uplarStockNav{0%,16%,37%,100%{color:#718096}18%,35%{color:#0877f9}}@keyframes uplarSalesNav{0%,40%,59%,100%{color:#718096}42%,57%{color:#0877f9}}@keyframes uplarFinanceNav{0%,62%,80%,100%{color:#718096}64%,78%{color:#0877f9}}
}

/* Correcoes pontuais: navegação discreta e grafico contido. */
@media(min-width:901px){
  .film-sidebar::after,.film-nav-indicator{display:none!important}
  .film-nav{background:transparent;transition:none}.film-nav--overview{animation:uplarOverviewNav 24s linear infinite}.film-nav--stock{animation:uplarStockNav 24s linear infinite}.film-nav--sales{animation:uplarSalesNav 24s linear infinite}.film-nav--finance{animation:uplarFinanceNav 24s linear infinite}
  .film-chart-card{padding:10px 12px 9px}.film-chart{margin:9px 2px 1px;width:calc(100% - 4px);height:82px}.film-chart path{stroke-width:2;stroke-linecap:round;stroke-linejoin:round}.film-chart circle{stroke-width:2;opacity:.9}.film-control-card{padding:10px 12px}
}
/* Graficos da Visao geral: contidos, proporcionais e legiveis. */
@media(min-width:901px){
  .film-overview-grid{gap:10px}.film-chart-card{padding:11px 12px 10px}.film-chart{display:block;width:100%;height:96px;margin:8px 0 0;overflow:visible}.film-chart-grid line{stroke:#e8eef6;stroke-width:1}.film-chart .film-chart-line{fill:none;stroke:#0877f9;stroke-width:2.15;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:210;stroke-dashoffset:0}.film-chart .film-chart-point{fill:#fff;stroke:#0877f9;stroke-width:1.8;opacity:1;transform-box:fill-box;transform-origin:center}.film-control-card{padding:11px 12px 10px}.film-control-card::after,.film-meter{display:none}.film-health{display:flex;align-items:center;justify-content:space-between;gap:9px;margin-top:auto;padding-top:9px}.film-health strong{margin:0;font-size:21px;line-height:1;letter-spacing:-.06em}.film-health-ring{width:38px;height:38px;flex:0 0 auto;transform:rotate(-90deg)}.film-health-ring circle{fill:none;stroke-width:4}.film-health-track{stroke:#e4edf8}.film-health-value{stroke:#12b8a6;stroke-linecap:round;stroke-dasharray:100.53;stroke-dashoffset:8.04}.film-chart path{animation:prodChart 24s cubic-bezier(.45,0,.2,1) infinite}.film-chart circle{animation:prodPoint 24s ease-out infinite}
}
/* Demonstração do hero institucional, isolada para o login. */
@media(min-width:901px){
  .uplar-film .film-app{display:none}.login-index-demo{position:absolute;top:clamp(132px,18vh,164px);right:clamp(18px,2vw,36px);left:clamp(330px,44%,400px);width:auto;height:clamp(382px,50vh,456px);overflow:hidden;border:1px solid rgba(205,215,229,.94);border-radius:14px;background:#fff;color:#0f1c2e;box-shadow:0 20px 50px rgba(0,18,54,.17),0 1px 0 rgba(255,255,255,.9) inset;transform:translate3d(0,6px,0);animation:indexDemoIn 1s cubic-bezier(.22,1,.36,1) both}
  .index-demo-browser{display:flex;height:42px;align-items:center;gap:6px;padding:0 13px;border-bottom:1px solid #e4eaf2;background:#fff}.index-demo-browser>span{width:8px;height:8px;border-radius:50%;background:#ff6b6b}.index-demo-browser>span:nth-child(2){background:#ffcc66}.index-demo-browser>span:nth-child(3){background:#4dd4ac}.index-demo-browser b{margin:auto;padding:5px 22px;border-radius:4px;background:#f4f6fb;color:#5a6a7a;font:500 8px 'JetBrains Mono',monospace}
  .index-demo-body{display:grid;height:calc(100% - 42px);grid-template-columns:112px minmax(0,1fr)}.index-demo-sidebar{padding:13px 10px;border-right:1px solid #e4eaf2;background:#fff}.index-demo-logo{display:flex;align-items:center;gap:5px;padding:1px 4px 13px;border-bottom:1px solid #e4eaf2;color:#3b6ff5;font-size:10px;font-weight:800}.index-demo-logo .material-symbols-outlined{font-size:15px}.index-demo-sidebar small{display:block;margin:13px 4px 6px;color:#94a3b8;font:700 6px 'JetBrains Mono',monospace;letter-spacing:.1em}.index-demo-nav{display:flex;align-items:center;gap:6px;height:28px;margin:2px 0;padding:0 7px;border-radius:6px;color:#65748a;font-size:8px;font-weight:600}.index-demo-nav .material-symbols-outlined{font-size:13px}.index-demo-nav.is-active{background:rgba(59,111,245,.09);color:#3b6ff5}
  .index-demo-workspace{min-width:0;padding:17px;background:#f4f6fb}.index-demo-head{display:flex;align-items:flex-start;justify-content:space-between}.index-demo-head strong{display:block;font-size:15px;letter-spacing:-.04em}.index-demo-head small{display:block;margin-top:3px;color:#5a6a7a;font-size:7px}.index-demo-head em{padding:6px 8px;border:1px solid #e4eaf2;border-radius:5px;background:#fff;color:#5a6a7a;font-size:7px;font-style:normal}.index-demo-kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:7px;margin-top:13px}.index-demo-kpis>div{position:relative;min-width:0;padding:9px 8px;border:1px solid #e4eaf2;border-radius:8px;background:#fff;overflow:hidden}.index-demo-kpis>div::before{content:'';position:absolute;top:0;right:0;left:0;height:2px;background:#3b6ff5}.index-demo-kpis>div:nth-child(2)::before{background:#10b981}.index-demo-kpis>div:nth-child(3)::before{background:#7c3aed}.index-demo-kpis small{display:block;color:#5a6a7a;font-size:6px}.index-demo-kpis b{display:block;margin-top:5px;font-size:13px;letter-spacing:-.04em}.index-demo-kpis i{display:block;margin-top:3px;color:#10a172;font-size:6px;font-style:normal;font-weight:700}
  .index-demo-chart-card{height:calc(100% - 103px);min-height:190px;margin-top:9px;padding:10px 11px;border:1px solid #e4eaf2;border-radius:8px;background:#fff}.index-demo-chart-head{display:flex;align-items:flex-start;justify-content:space-between}.index-demo-chart-head b{display:block;font-size:8px}.index-demo-chart-head small{display:block;margin-top:3px;color:#5a6a7a;font-size:6px}.index-demo-chart-head>i{padding:4px 5px;border-radius:3px;background:#e8f8f2;color:#087f75;font-size:6px;font-style:normal;font-weight:700}.index-demo-chart{display:block;width:100%;height:calc(100% - 47px);min-height:103px;margin-top:6px;overflow:visible}.index-demo-chart g line{stroke:#e4eaf2;stroke-width:1}.index-demo-fill{fill:rgba(59,111,245,.13);animation:indexFill 20s ease-in-out infinite}.index-demo-line{fill:none;stroke:#3b6ff5;stroke-width:3;stroke-linecap:round;stroke-linejoin:round;stroke-dasharray:610;stroke-dashoffset:0;animation:indexLine 20s cubic-bezier(.22,1,.36,1) infinite}.index-demo-chart circle{fill:#fff;stroke:#3b6ff5;stroke-width:2.5;animation:indexPoint 20s ease-in-out infinite}.index-demo-insights{display:grid;grid-template-columns:repeat(3,1fr);gap:5px;padding-top:6px;border-top:1px solid #e4eaf2;text-align:center}.index-demo-insights span{display:block;color:#5a6a7a;font-size:6px}.index-demo-insights b{display:block;margin-bottom:2px;color:#0f1c2e;font-size:7px}
  @keyframes indexDemoIn{from{opacity:0;transform:translate3d(18px,12px,0)}to{opacity:1;transform:translate3d(0,6px,0)}}@keyframes indexLine{0%,8%{stroke-dashoffset:610}14%,84%{stroke-dashoffset:0}100%{stroke-dashoffset:610}}@keyframes indexPoint{0%,13%,84%,100%{opacity:0;transform:scale(.6)}16%,80%{opacity:1;transform:scale(1)}}@keyframes indexFill{0%,10%,84%,100%{opacity:0}16%,80%{opacity:1}}
}
@media(prefers-reduced-motion:reduce){.uplar-film .film-app{display:none}.login-index-demo{display:block;animation:none;transform:translate3d(0,6px,0)}.index-demo-line{stroke-dashoffset:0;animation:none}.index-demo-chart circle,.index-demo-fill{opacity:1;animation:none}}
@media(min-width:1201px) and (max-width:1360px){.login-index-demo{right:18px;left:315px;transform:translate3d(0,8px,0) scale(.88);transform-origin:top left}}
@media(max-width:1200px){.login-shell{grid-template-columns:1fr}.login-story{display:none}.login-access{min-height:100vh}.mobile-brand{display:flex}.login-panel{max-width:440px}}
</style>
<link rel="stylesheet" href="assets/brand/uplar-brand.css?v=20261020.1">
</head>
<body>
<div class="login-shell">
    <section class="login-story" aria-label="Visão geral da plataforma Uplar">
        <a class="brand" href="/" aria-label="Voltar para a página inicial da Uplar">
            <span class="uplar-brand-mark" aria-hidden="true"><img class="uplar-brand-image" src="assets/brand/uplar-mark-64.png" srcset="assets/brand/uplar-mark-128.png 2x, assets/brand/uplar-mark-256.png 4x" width="64" height="39" alt="" decoding="async" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><span class="uplar-brand-fallback" hidden>U</span></span>
            <span><span class="brand-name">UPLAR</span><span class="brand-meta">Controle operacional</span></span>
        </a>

        <div class="story-content">
            <div class="story-copy">
                <span class="eyebrow">Estoque · vendas · financeiro</span>
                <h1 class="story-title">Sua operação, <span>clara</span> do estoque à venda.</h1>
                <p class="story-text">Produtos, movimentações, pedidos e resultados conectados em um fluxo simples para você decidir com segurança.</p>
            </div>

            <div class="uplar-film" aria-hidden="true">
                <div class="film-app">
                    <aside class="film-sidebar">
                        <div class="film-logo"><span class="material-symbols-outlined">cloud</span>UPLAR</div>
                        <p class="film-nav-label">Operação</p>
                        <div class="film-nav film-nav--overview"><span class="material-symbols-outlined">dashboard</span>Visão geral</div>
                        <div class="film-nav film-nav--stock"><span class="material-symbols-outlined">inventory_2</span>Estoque</div>
                        <div class="film-nav film-nav--sales"><span class="material-symbols-outlined">receipt_long</span>Vendas</div>
                        <div class="film-nav film-nav--finance"><span class="material-symbols-outlined">account_balance</span>Financeiro</div>
                    </aside>
                    <div class="film-workspace">
                        <div class="film-head"><div><p class="film-title">Visão da operação</p><p class="film-subtitle">Hoje, 08 de agosto</p></div><span class="film-status"><i></i>ATUALIZADO</span></div>
                        <div class="film-kpis">
                            <div class="film-kpi"><small>Produtos</small><strong>248</strong><em>em catálogo</em></div>
                            <div class="film-kpi film-kpi--sales"><small>Vendas</small><span class="film-number"><b>36<br>37</b></span><em>+ 1 registrada</em></div>
                            <div class="film-kpi film-kpi--health"><small>Saúde</small><strong>92%</strong><em>operação estável</em></div>
                        </div>
                        <div class="film-panel film-stock-panel">
                            <div class="film-panel-head">Estoque <span>248 produtos</span></div>
                            <div class="film-table">
                                <div class="film-row"><span><strong>Camisa esportiva</strong><small>SKU •••• · Catálogo</small></span><span class="film-qty"><b>8 un.<br>7 un.</b></span><span class="film-badge film-badge--low">ESTOQUE BAIXO</span></div>
                                <div class="film-row"><span><strong>Bermuda performance</strong><small>SKU •••• · Catálogo</small></span><span class="film-qty">24 un.</span><span class="film-badge">DISPONÍVEL</span></div>
                                <div class="film-row"><span><strong>Meia esportiva</strong><small>SKU •••• · Catálogo</small></span><span class="film-qty">31 un.</span><span class="film-badge">DISPONÍVEL</span></div>
                            </div>
                        </div>
                        <div class="film-panel film-sales">
                            <div class="film-panel-head">Vendas <span>Pedido mais recente</span></div>
                            <div class="film-sale-row"><span><strong>Pedido #••48</strong><small>Camisa esportiva · R$ ••,••</small></span><span class="film-sale-status"></span></div>
                            <div class="film-sale-row"><span><strong>Pedido #••47</strong><small>Bermuda performance · R$ ••,••</small></span><span class="film-badge">PROCESSADO</span></div>
                            <div class="film-sale-row"><span><strong>Pedido #••46</strong><small>Meia esportiva · R$ ••,••</small></span><span class="film-badge">PROCESSADO</span></div>
                        </div>
                        <div class="film-panel film-finance">
                            <div class="film-money"><small>Receita do período</small><strong><b>R$ 8.420<br>R$ 8.486</b></strong><em>+ R$ 66,00 da venda</em></div>
                            <div class="film-bars"><i style="--h:32%"></i><i style="--h:48%"></i><i style="--h:42%"></i><i style="--h:68%"></i><i style="--h:56%"></i><i style="--h:82%"></i></div>
                        </div>
                        <div class="film-overview">
                            <div class="film-overview-grid">
                                <div class="film-chart-card"><p>Desempenho da operação</p><small>Estoque, pedidos e receita conectados</small><svg class="film-chart" viewBox="0 0 180 92" preserveAspectRatio="none"><g class="film-chart-grid"><line x1="8" y1="20" x2="172" y2="20"></line><line x1="8" y1="46" x2="172" y2="46"></line><line x1="8" y1="72" x2="172" y2="72"></line></g><path class="film-chart-line" d="M10 73 C26 69,33 70,46 61 S67 63,80 51 S101 54,115 39 S139 42,154 29 S166 25,170 17"></path><circle class="film-chart-point" cx="170" cy="17" r="2.8"></circle></svg></div>
                                <div class="film-control-card"><p>Operação sob controle</p><small>Indicadores atualizados</small><div class="film-health"><strong>92%</strong><svg class="film-health-ring" viewBox="0 0 42 42" aria-hidden="true"><circle class="film-health-track" cx="21" cy="21" r="16"></circle><circle class="film-health-value" cx="21" cy="21" r="16"></circle></svg></div></div>
                            </div>
                        </div>
                        <div class="film-link"></div>
                        <div class="film-toast"><span><span class="material-symbols-outlined">shopping_bag</span></span><div><strong>Nova venda registrada</strong><small>Pedido #1048 · R$ 66,00</small></div></div>
                    </div>
                </div>
                <div class="login-index-demo">
                    <div class="index-demo-browser"><span></span><span></span><span></span><b>app.uplar.cloud / relatórios</b></div>
                    <div class="index-demo-body">
                        <aside class="index-demo-sidebar"><div class="index-demo-logo"><span class="uplar-brand-mark" aria-hidden="true"><img class="uplar-brand-image" src="assets/brand/uplar-mark-64.png" srcset="assets/brand/uplar-mark-128.png 2x, assets/brand/uplar-mark-256.png 4x" width="64" height="39" alt="" decoding="async" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><span class="uplar-brand-fallback" hidden>U</span></span>UPLAR</div><small>GESTÃO</small><div class="index-demo-nav is-active"><span class="material-symbols-outlined">bar_chart</span>Relatórios</div><div class="index-demo-nav"><span class="material-symbols-outlined">inventory_2</span>Produtos</div><div class="index-demo-nav"><span class="material-symbols-outlined">receipt_long</span>Pedidos</div><div class="index-demo-nav"><span class="material-symbols-outlined">groups</span>Vendedores</div></aside>
                        <div class="index-demo-workspace"><div class="index-demo-head"><div><strong>Relatórios</strong><small>Decisões baseadas no que está acontecendo agora</small></div><em>Últimos 30 dias</em></div><div class="index-demo-kpis"><div><small>Receita</small><b>R$ 42.680</b><i>↑ 18,4%</i></div><div><small>Vendas</small><b>318</b><i>↑ 34 este mês</i></div><div><small>Ticket médio</small><b>R$ 134</b><i>Melhor: R$ 189</i></div></div><div class="index-demo-chart-card"><div class="index-demo-chart-head"><div><b>Desempenho de vendas</b><small>Receita diária · evolução do período</small></div><i>+18,4%</i></div><svg class="index-demo-chart" viewBox="0 0 500 172" preserveAspectRatio="none"><g><line x1="8" y1="24" x2="492" y2="24"></line><line x1="8" y1="70" x2="492" y2="70"></line><line x1="8" y1="116" x2="492" y2="116"></line><line x1="8" y1="162" x2="492" y2="162"></line></g><path class="index-demo-fill" d="M8 148 C47 142 59 127 91 132 S142 107 174 116 S223 80 258 91 S311 54 344 69 S401 39 434 49 S469 24 492 29 V164 H8Z"></path><path class="index-demo-line" d="M8 148 C47 142 59 127 91 132 S142 107 174 116 S223 80 258 91 S311 54 344 69 S401 39 434 49 S469 24 492 29"></path><circle cx="492" cy="29" r="4"></circle></svg><div class="index-demo-insights"><span><b>Camisa esportiva</b>Mais vendido</span><span><b>Quinta-feira</b>Melhor dia</span><span><b>R$ •.•••</b>Pico diário</span></div></div></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="story-foot">Uplar Cloud · Sistemas para operações reais</div>
    </section>

    <main class="login-access">
    <div class="login-panel">
        <a class="brand mobile-brand" href="/" aria-label="Voltar para a página inicial da Uplar">
            <span class="uplar-brand-mark" aria-hidden="true"><img class="uplar-brand-image" src="assets/brand/uplar-mark-64.png" srcset="assets/brand/uplar-mark-128.png 2x, assets/brand/uplar-mark-256.png 4x" width="64" height="39" alt="" decoding="async" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><span class="uplar-brand-fallback" hidden>U</span></span>
            <span><span class="brand-name" style="color:var(--navy)">UPLAR</span><span class="brand-meta" style="color:var(--faint)">Controle operacional</span></span>
        </a>

        <div class="panel-kicker">Acesso ao sistema</div>
        <h2 class="title">Bem-vindo de volta</h2>
        <p class="subtitle">Entre para acompanhar seu estoque, suas vendas e os números da operação.</p>

    <!-- Bloqueio por IP -->
    <?php if ($ip_blocked): ?>
    <div class="lockout-box">
        <svg viewBox="0 0 24 24" width="38" height="38" fill="#ef4444" xmlns="http://www.w3.org/2000/svg">
            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
        </svg>
        <div class="lockout-title">Acesso temporariamente bloqueado</div>
        <div class="lockout-sub">Muitas tentativas falhas detectadas. Aguarde antes de tentar novamente.</div>
        <div class="countdown">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15 1H9v2h6V1zm-4 13h2V8h-2v6zm8.03-6.61l1.42-1.42c-.43-.51-.9-.99-1.41-1.41l-1.42 1.42C16.07 4.74 14.12 4 12 4c-4.97 0-9 4.03-9 9s4.02 9 9 9 9-4.03 9-9c0-2.12-.74-4.07-1.97-5.61zM12 20c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7z"/></svg>
            <span id="cdown">Calculando…</span>
        </div>
    </div>
    <?php endif; ?>

    <!-- Alertas -->
    <?php if ($logout): ?>
    <div class="alert ok">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        <?= htmlspecialchars($logout) ?>
    </div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="alert ok">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert err" id="errAlert">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Formulário -->
    <form action="auth.php" method="POST" id="loginForm" novalidate autocomplete="on">
        <?= csrf_input() ?>
        <!-- Honeypot anti-bot -->
        <input type="text" name="website" id="website" tabindex="-1" autocomplete="off"
            style="position:absolute;left:-9999px;opacity:0;pointer-events:none" aria-hidden="true">

        <!-- Usuário -->
        <div class="form-group">
            <label class="form-label" for="username">Usuário</label>
            <div class="field-wrap">
                <div class="field-ico">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/></svg>
                </div>
                <input type="text" name="username" id="username" placeholder="Digite seu usuário"
                    required autofocus autocomplete="username" maxlength="50"
                    spellcheck="false" autocorrect="off" autocapitalize="off"
                    <?= $ip_blocked ? 'disabled' : '' ?>>
            </div>
        </div>

        <!-- Senha -->
        <div class="form-group">
            <label class="form-label" for="password">Senha</label>
            <div class="field-wrap">
                <div class="field-ico">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                </div>
                <input type="password" name="password" id="password" placeholder="Digite sua senha"
                    required autocomplete="current-password" maxlength="200"
                    <?= $ip_blocked ? 'disabled' : '' ?>>
                <button type="button" class="pw-btn" id="pwBtn" tabindex="-1" aria-label="Mostrar/ocultar senha">
                    <svg id="icoVis" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                    <svg id="icoHide" viewBox="0 0 24 24" fill="currentColor" style="display:none"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>
                </button>
            </div>
        </div>

        <!-- CapsLock warning -->
        <div class="caps-hint" id="capsHint">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 6h2v2h-2V7zm0 4h2v6h-2v-6z"/></svg>
            CapsLock ativado — verifique sua senha
        </div>

        <?php if ($turnstile_enabled): ?>
            <div class="turnstile-wrap">
                <div class="cf-turnstile"
                    data-sitekey="<?= attr_escape($turnstile_site_key) ?>"
                    data-action="login"
                    data-theme="light"
                    data-language="pt-BR"></div>
            </div>
        <?php endif; ?>

        <!-- Submit -->
        <button type="submit" class="btn-login" id="btnLogin" <?= $ip_blocked ? 'disabled' : '' ?>>
            <svg id="btnIco" viewBox="0 0 24 24" fill="currentColor">
                <path d="M11 7L9.6 8.4l2.6 2.6H2v2h10.2l-2.6 2.6L11 17l5-5-5-5zm9 12h-8v2h8c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-8v2h8v14z"/>
            </svg>
            <span id="btnTxt">Entrar</span>
        </button>
    </form>

    <div class="account-link">
        Ainda não tem conta? <a href="registro.php">Crie sua conta</a> para escolher um plano.
    </div>

    <div class="sec-badge">
        <svg viewBox="0 0 24 24"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>
        Conexão protegida · Dados criptografados
    </div>

    </div>
    </main>
</div>

<script>
(function(){
    'use strict';
    const $=id=>document.getElementById(id);
    const pwIn=$('password'),pwBtn=$('pwBtn'),iV=$('icoVis'),iH=$('icoHide');
    const capsHint=$('capsHint'),form=$('loginForm');
    const btn=$('btnLogin'),btnTxt=$('btnTxt'),btnIco=$('btnIco');

    /* Toggle senha */
    if(pwBtn){pwBtn.addEventListener('click',()=>{
        const show=pwIn.type==='password';
        pwIn.type=show?'text':'password';
        iV.style.display=show?'none':'';iH.style.display=show?'':'none';
    });}

    /* CapsLock */
    document.addEventListener('keydown',e=>{
        if(capsHint&&document.activeElement===pwIn){
            capsHint.classList.toggle('on',!!(e.getModifierState&&e.getModifierState('CapsLock')));
        }
    });

    /* Submit */
    if(form){form.addEventListener('submit',function(e){
        const hp=$('website');if(hp&&hp.value!==''){e.preventDefault();return;}
        const u=$('username').value.trim(),p=pwIn.value;
        if(!u||!p){e.preventDefault();showErr('Preencha usuário e senha.');return;}
        <?php if ($turnstile_enabled): ?>
        const turnstile=form.querySelector('[name="cf-turnstile-response"]');
        if(!turnstile||!turnstile.value){e.preventDefault();showErr('Conclua a verificação de segurança.');return;}
        <?php endif; ?>
        btn.disabled=true;btnIco.style.display='none';btnTxt.textContent='Verificando…';
        const sp=document.createElement('div');sp.className='spinner';btn.insertBefore(sp,btn.firstChild);
        setTimeout(()=>{if(btn.disabled){btn.disabled=false;btnIco.style.display='';btnTxt.textContent='Entrar';sp.remove();}},12000);
    });}

    function showErr(msg){
        let el=$('jsErr');if(el)el.remove();
        const d=document.createElement('div');d.id='jsErr';d.className='alert err';d.setAttribute('role','alert');
        d.innerHTML=`<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>${String(msg).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]))}`;
        form.insertBefore(d,form.firstChild);setTimeout(()=>d.remove(),4000);
    }

    /* Auto-dismiss erro */
    const ea=$('errAlert');
    if(ea){setTimeout(()=>{ea.style.transition='opacity .4s';ea.style.opacity='0';setTimeout(()=>ea.remove(),400);},8000);}

    /* Limpa query string */
    if(window.history&&window.history.replaceState)window.history.replaceState({},document.title,window.location.pathname);

    /* Countdown bloqueio */
    <?php if($ip_blocked&&$blocked_secs>0): ?>
    let rem=<?= (int)$blocked_secs ?>;
    const cel=$('cdown');
    (function tick(){
        if(!cel)return;
        const m=Math.floor(rem/60),s=rem%60;
        cel.textContent='Libera em '+m+'m '+String(s).padStart(2,'0')+'s';
        if(rem<=0){cel.textContent='Recarregando…';setTimeout(()=>location.reload(),1000);return;}
        rem--;setTimeout(tick,1000);
    })();
    <?php endif; ?>
})();
</script>
</body>
</html>
