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
$pdo      = get_pdo();
$stmt     = $pdo->prepare("SELECT role, whatsapp_number, passcode_hash, passcode_configured_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user            = $stmt->fetch();
$is_admin        = ($user && $user['role'] === 'admin');
$whatsapp_number = $user['whatsapp_number'] ?? '';
$passcode_enabled = !empty($user['passcode_hash']);
$passcode_configured_at = !empty($user['passcode_configured_at']) ? date('d/m/Y', strtotime($user['passcode_configured_at'])) : '';

// Garante tabela empresa_config
$pdo->exec("CREATE TABLE IF NOT EXISTS empresa_config (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL UNIQUE,
    razao_social    VARCHAR(255) DEFAULT '',
    nome_fantasia   VARCHAR(255) DEFAULT '',
    cnpj            VARCHAR(20)  DEFAULT '',
    endereco        VARCHAR(255) DEFAULT '',
    cidade          VARCHAR(100) DEFAULT '',
    estado          VARCHAR(2)   DEFAULT '',
    cep             VARCHAR(10)  DEFAULT '',
    telefone        VARCHAR(20)  DEFAULT '',
    email           VARCHAR(150) DEFAULT '',
    logo_path       VARCHAR(255) DEFAULT '',
    logo_size       INT          DEFAULT 120,
    atualizado_em   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$stmt2 = $pdo->prepare("SELECT * FROM empresa_config WHERE user_id = ?");
$stmt2->execute([$user_id]);
$empresa = $stmt2->fetch() ?: [
    'razao_social'=>'','nome_fantasia'=>'','cnpj'=>'','endereco'=>'',
    'cidade'=>'','estado'=>'','cep'=>'','telefone'=>'','email'=>'',
    'logo_path'=>'','logo_size'=>120
];

header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

// ── Assinatura (NOVO) ────────────────────────────────────────────────────────
require 'subscription.php';
$sub = get_subscription($user_id, $pdo);

$sub_historico = [];
try {
    $sh = $pdo->prepare("
        SELECT ih.acao, ih.plano_dias, ih.data_fim_nova,
               DATE_FORMAT(ih.criado_em, '%d/%m/%Y às %H:%i') AS data_fmt,
               u.username AS admin_nome
        FROM inscricoes_historico ih
        LEFT JOIN users u ON u.id = ih.admin_id
        WHERE ih.user_id = ?
        ORDER BY ih.criado_em DESC LIMIT 8
    ");
    $sh->execute([$user_id]);
    $sub_historico = $sh->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Configurações — Uplar</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"/>
<style>
/* ═══ TOKENS ═══════════════════════════════════════════════ */
:root{
    --p:#3b6ff5;--p-dark:#2a55d9;--p-dim:rgba(59,111,245,.08);--p-glow:rgba(59,111,245,.20);
    --ink:#0f1c2e;--muted:#5a6a7a;--faint:#94a3b8;--bg:#f4f6fb;--card:#fff;--border:#e4eaf2;
    --ok:#10b981;--ok-dim:rgba(16,185,129,.09);
    --warn:#f59e0b;--warn-dim:rgba(245,158,11,.09);
    --err:#ef4444;--err-dim:rgba(239,68,68,.09);
    --violet:#7c3aed;--violet-dim:rgba(124,58,237,.09);
    --sh:0 1px 4px rgba(15,28,46,.06),0 4px 16px rgba(15,28,46,.04);
    --sh-lift:0 4px 24px rgba(59,111,245,.14);--r:12px;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--ink);line-height:1.55;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none} button{font-family:inherit;cursor:pointer}
@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes popIn{from{opacity:0;transform:scale(.94)}to{opacity:1;transform:scale(1)}}
@keyframes spin{to{transform:rotate(360deg)}}
.anim{opacity:0;animation:fadeUp .45s cubic-bezier(.22,1,.36,1) forwards}
.d1{animation-delay:.04s}.d2{animation-delay:.09s}.d3{animation-delay:.14s}
.d4{animation-delay:.19s}.d5{animation-delay:.24s}.d6{animation-delay:.30s}

/* ═══ LAYOUT ════════════════════════════════════════════════ */
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
.sb-lbl{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--faint);padding:0 .7rem;margin:.9rem 0 .4rem}
.sb-lbl:first-child{margin-top:0}
.sb-link{display:flex;align-items:center;gap:.7rem;padding:.65rem .85rem;border-radius:9px;color:var(--muted);font-weight:500;font-size:.845rem;transition:all .18s;margin-bottom:2px}
.sb-link:hover{background:var(--p-dim);color:var(--p)}
.sb-link.on{background:var(--p-dim);color:var(--p);font-weight:600}
.sb-link .ms{font-size:1.15rem;width:20px;text-align:center;flex-shrink:0}
.overlay{display:none;position:fixed;inset:0;background:rgba(15,28,46,.4);z-index:199;backdrop-filter:blur(3px)}
.overlay.on{display:block}
.ham{display:none;background:var(--card);border:1px solid var(--border);border-radius:9px;padding:.42rem;align-items:center;justify-content:center;box-shadow:var(--sh);color:var(--ink)}
.ham .ms{font-size:1.3rem}
.main{margin-left:248px;flex:1;padding:2rem 2.4rem;min-width:0;max-width:100%}

/* ═══ TOP BAR ════════════════════════════════════════════════ */
.top{display:flex;align-items:center;gap:.8rem;margin-bottom:2rem}
.ptitle{font-size:1.35rem;font-weight:800;letter-spacing:-.4px}
.psub{color:var(--muted);font-size:.8rem;margin-top:2px}

/* ═══ GRID / NAV ═════════════════════════════════════════════ */
.cfg-grid{display:grid;grid-template-columns:280px 1fr;gap:1.5rem;align-items:start}
.cfg-nav{background:var(--card);border:1px solid var(--border);border-radius:var(--r);overflow:hidden;position:sticky;top:2rem}
.cfg-nav-item{display:flex;align-items:center;gap:.75rem;padding:.75rem 1.1rem;cursor:pointer;font-size:.84rem;font-weight:500;color:var(--muted);transition:all .18s;border-left:3px solid transparent;position:relative}
.cfg-nav-item:hover{background:var(--p-dim);color:var(--p)}
.cfg-nav-item.on{background:var(--p-dim);color:var(--p);font-weight:700;border-left-color:var(--p)}
.cfg-nav-item .ms{font-size:1.1rem;flex-shrink:0}
.cfg-nav-divider{height:1px;background:var(--border);margin:.3rem 0}
.nav-dot{width:7px;height:7px;border-radius:50%;background:var(--warn);margin-left:auto;flex-shrink:0}
.nav-dot.err{background:var(--err)}

/* ═══ SEÇÕES ═════════════════════════════════════════════════ */
.section{display:none}
.section.on{display:block}

/* ═══ CARD ═══════════════════════════════════════════════════ */
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:1.5rem;margin-bottom:1rem}
.card-head{display:flex;align-items:center;gap:.75rem;margin-bottom:1.5rem;padding-bottom:1rem;border-bottom:1px solid var(--border)}
.card-head-ico{width:38px;height:38px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.card-head-ico.p{background:var(--p-dim);color:var(--p)}
.card-head-ico.ok{background:var(--ok-dim);color:var(--ok)}
.card-head-ico.warn{background:var(--warn-dim);color:var(--warn)}
.card-head-ico.violet{background:var(--violet-dim);color:var(--violet)}
.card-head-ico .ms{font-size:1.25rem}
.card-title{font-size:.95rem;font-weight:700}
.card-sub{font-size:.75rem;color:var(--muted);margin-top:2px}

/* ═══ LOGO UPLOAD ════════════════════════════════════════════ */
.logo-section{display:flex;gap:1.5rem;align-items:flex-start;flex-wrap:wrap;margin-bottom:1.5rem}
.logo-preview-wrap{flex-shrink:0}
.logo-dropzone{width:160px;height:160px;border-radius:14px;border:2px dashed var(--border);background:var(--bg);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.5rem;cursor:pointer;transition:all .22s;position:relative;overflow:hidden}
.logo-dropzone:hover{border-color:var(--p);background:var(--p-dim)}
.logo-dropzone.has-img{border-style:solid;border-color:var(--border);padding:0}
.logo-dropzone img{width:100%;height:100%;object-fit:contain;border-radius:12px}
.logo-dropzone-lbl{font-size:.72rem;color:var(--faint);text-align:center;line-height:1.4;padding:0 .5rem}
.logo-dropzone .ms{font-size:2rem;color:var(--faint)}
.logo-dropzone:hover .ms,.logo-dropzone:hover .logo-dropzone-lbl{color:var(--p)}
.logo-overlay{position:absolute;inset:0;background:rgba(15,28,46,.55);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.3rem;opacity:0;transition:opacity .2s;border-radius:12px}
.logo-dropzone.has-img:hover .logo-overlay{opacity:1}
.logo-overlay .ms{font-size:1.5rem;color:#fff}
.logo-overlay span{font-size:.72rem;color:rgba(255,255,255,.85);font-weight:600}
.logo-controls{flex:1;min-width:200px;display:flex;flex-direction:column;gap:1rem;padding-top:.25rem}
.logo-size-wrap{display:flex;flex-direction:column;gap:.5rem}
.logo-size-lbl{font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;display:flex;justify-content:space-between}
.logo-size-lbl span{color:var(--p);font-weight:700}
.logo-slider{-webkit-appearance:none;appearance:none;width:100%;height:4px;border-radius:20px;background:var(--border);outline:none;cursor:pointer;background-image:linear-gradient(var(--p),var(--p));background-size:50% 100%;background-repeat:no-repeat}
.logo-slider::-webkit-slider-thumb{-webkit-appearance:none;width:18px;height:18px;border-radius:50%;background:var(--p);box-shadow:0 2px 8px var(--p-glow);cursor:pointer;border:2px solid #fff;transition:transform .15s}
.logo-slider::-webkit-slider-thumb:hover{transform:scale(1.15)}
.logo-slider::-moz-range-thumb{width:16px;height:16px;border-radius:50%;background:var(--p);border:2px solid #fff;cursor:pointer}
.logo-note{font-size:.75rem;color:var(--faint);line-height:1.5}

/* ═══ FORM ═══════════════════════════════════════════════════ */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:.9rem 1.1rem}
.form-grid.full{grid-template-columns:1fr}
.form-group{display:flex;flex-direction:column;gap:.3rem}
.form-group.span2{grid-column:1/-1}
.form-group label{font-size:.69rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;display:flex;align-items:center;gap:.3rem}
.form-group label .req{color:var(--err);font-size:.75rem}
.form-group input,.form-group select,.form-group textarea{padding:.65rem .9rem;border:1.5px solid var(--border);border-radius:9px;font-size:.86rem;font-family:inherit;outline:none;color:var(--ink);transition:border-color .18s,box-shadow .18s;background:var(--card)}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.form-group .hint{font-size:.7rem;color:var(--faint);margin-top:2px}
.inp-wrap{position:relative}
.inp-wrap .inp-ico{position:absolute;left:.8rem;top:50%;transform:translateY(-50%);color:var(--faint);font-size:1rem;pointer-events:none}
.inp-wrap input{padding-left:2.4rem}

/* ═══ SENHA ══════════════════════════════════════════════════ */
.pw-strength{height:4px;border-radius:20px;background:var(--border);margin-top:.5rem;overflow:hidden}
.pw-strength-bar{height:100%;border-radius:20px;transition:width .4s,background .4s;width:0}
.pw-strength-txt{font-size:.68rem;margin-top:.3rem;font-weight:600}
.pw-toggle{position:absolute;right:.8rem;top:50%;transform:translateY(-50%);color:var(--faint);cursor:pointer;font-size:1rem;background:none;border:none;padding:.2rem;transition:color .15s}
.pw-toggle:hover{color:var(--p)}

/* ═══ BOTÕES ═════════════════════════════════════════════════ */
.btn{display:inline-flex;align-items:center;gap:.45rem;padding:.6rem 1.2rem;border-radius:50px;font-size:.82rem;font-weight:700;border:none;cursor:pointer;transition:all .22s;white-space:nowrap}
.btn .ms{font-size:.95rem}
.btn-p{background:var(--p);color:#fff;box-shadow:0 3px 12px var(--p-glow)}
.btn-p:hover{background:var(--p-dark);transform:translateY(-2px);box-shadow:0 6px 20px var(--p-glow)}
.btn-p:disabled{background:var(--faint);box-shadow:none;transform:none;cursor:not-allowed}
.btn-o{background:var(--card);color:var(--muted);border:1.5px solid var(--border)}
.btn-o:hover{background:var(--p-dim);border-color:var(--p);color:var(--p);transform:translateY(-2px)}
.btn-ok{background:var(--ok);color:#fff;box-shadow:0 3px 12px rgba(16,185,129,.25)}
.btn-ok:hover{background:#059669;transform:translateY(-2px)}
.btn-err{background:var(--err);color:#fff;box-shadow:0 3px 8px rgba(239,68,68,.2)}
.btn-err:hover{background:#dc2626;transform:translateY(-2px)}
.btn-row{display:flex;align-items:center;gap:.6rem;margin-top:1.5rem;padding-top:1.2rem;border-top:1px solid var(--border);flex-wrap:wrap}

/* ═══ BADGES / ALERTS ════════════════════════════════════════ */
.bx{display:inline-flex;align-items:center;gap:.25rem;padding:.2rem .6rem;border-radius:20px;font-size:.72rem;font-weight:700;white-space:nowrap}
.bx.ok{background:var(--ok-dim);color:var(--ok)}
.bx.er{background:var(--err-dim);color:var(--err)}
.bx.wa{background:var(--warn-dim);color:#92400e}
.alert{display:flex;align-items:flex-start;gap:.7rem;padding:.85rem 1rem;border-radius:10px;font-size:.82rem;margin-bottom:1rem}
.alert .ms{font-size:1.1rem;flex-shrink:0;margin-top:1px}
.alert.ok{background:var(--ok-dim);color:#065f46;border:1px solid rgba(16,185,129,.2)}
.alert.er{background:var(--err-dim);color:#991b1b;border:1px solid rgba(239,68,68,.2)}

/* ═══ PROFILE HEADER ═════════════════════════════════════════ */
.profile-header{background:linear-gradient(135deg,var(--p) 0%,#7ca0ff 100%);border-radius:14px;padding:1.6rem;display:flex;align-items:center;gap:1.2rem;margin-bottom:1rem;position:relative;overflow:hidden}
.profile-header::after{content:'';position:absolute;right:-40px;top:-40px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.07);pointer-events:none}
.profile-av{width:72px;height:72px;border-radius:16px;background:rgba(255,255,255,.2);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:800;flex-shrink:0;border:2px solid rgba(255,255,255,.3);overflow:hidden}
.profile-av img{display:block;width:100%;height:100%;object-fit:cover;object-position:center}
.profile-info{flex:1}
.profile-name{font-size:1.1rem;font-weight:700;color:#fff;letter-spacing:-.2px}
.profile-role{font-size:.75rem;color:rgba(255,255,255,.75);margin-top:2px}
.profile-tag{background:rgba(255,255,255,.2);color:#fff;font-size:.7rem;font-weight:700;padding:.2rem .6rem;border-radius:20px;border:1px solid rgba(255,255,255,.25);display:inline-flex;align-items:center;gap:.3rem;margin-top:.5rem}

/* ═══ TOAST ══════════════════════════════════════════════════ */
.toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(20px);background:var(--ink);color:#fff;padding:.7rem 1.4rem;border-radius:50px;font-size:.82rem;font-weight:700;z-index:9999;box-shadow:0 8px 32px rgba(0,0,0,.25);opacity:0;transition:all .35s cubic-bezier(.22,1,.36,1);pointer-events:none;display:flex;align-items:center;gap:.5rem;white-space:nowrap}
.toast .ms{font-size:1rem}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.toast.ok-t{background:var(--ok)}
.toast.er-t{background:var(--err)}
.spinner{width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite}
.divider{height:1px;background:var(--border);margin:1.2rem 0}

/* ═══ BOTTOM NAV ═════════════════════════════════════════════ */
.bnav{display:none;position:fixed;bottom:0;left:0;right:0;background:var(--card);border-top:1px solid var(--border);z-index:300;padding:.35rem 0 calc(.35rem + env(safe-area-inset-bottom));box-shadow:0 -2px 16px rgba(15,28,46,.07)}
.bnav-inner{display:flex;justify-content:space-around}
.bnav-a{display:flex;flex-direction:column;align-items:center;gap:2px;padding:.35rem .6rem;color:var(--faint);font-size:.6rem;font-weight:600;transition:all .18s;flex:1}
.bnav-a .ms{font-size:1.2rem}
.bnav-a.on{color:var(--p)}

/* ═══ ASSINATURA (NOVO) ══════════════════════════════════════ */
/* Banner topo */
.sub-banner{position:fixed;top:0;left:0;right:0;z-index:400;padding:.55rem 1.4rem;font-size:.81rem;font-weight:600;display:flex;align-items:center;justify-content:center;gap:.55rem;animation:fadeUp .4s cubic-bezier(.22,1,.36,1) both}
.sub-banner.expirando{background:var(--warn);color:#1c1a00}
.sub-banner.expirada{background:var(--err);color:#fff}
.sub-banner .ms{font-size:.95rem}
.sub-banner-close{background:none;border:none;cursor:pointer;color:inherit;font-size:1.15rem;line-height:1;padding:0 0 0 .4rem;display:flex;align-items:center;margin-left:.4rem}
body.has-banner .main{padding-top:calc(2rem + 40px)}
body.has-banner .sb{top:40px}
/* Status box */
.sub-status-box{border-radius:12px;padding:1.05rem 1.15rem;display:flex;align-items:center;gap:.85rem;margin-bottom:.9rem;border:1.5px solid transparent}
.sub-status-box.ok{background:rgba(16,185,129,.06);border-color:rgba(16,185,129,.25)}
.sub-status-box.warn{background:rgba(245,158,11,.07);border-color:rgba(245,158,11,.3)}
.sub-status-box.err{background:rgba(239,68,68,.07);border-color:rgba(239,68,68,.25)}
.sub-status-box.adm{background:var(--violet-dim);border-color:rgba(124,58,237,.2)}
.ssb-ico{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ssb-ico .ms{font-size:1.25rem}
.ssb-ico.ok{background:var(--ok-dim);color:var(--ok)}
.ssb-ico.warn{background:var(--warn-dim);color:var(--warn)}
.ssb-ico.err{background:var(--err-dim);color:var(--err)}
.ssb-ico.violet{background:var(--violet-dim);color:var(--violet)}
.ssb-title{font-size:.88rem;font-weight:700}
.ssb-sub{font-size:.73rem;color:var(--muted);margin-top:.1rem;line-height:1.45}
/* Barra de progresso */
.sub-progress-wrap{margin-bottom:.9rem}
.sub-progress-lbl{display:flex;justify-content:space-between;font-size:.69rem;font-weight:600;color:var(--faint);margin-bottom:.35rem}
.sub-progress{height:6px;background:var(--border);border-radius:20px;overflow:hidden}
.sub-progress-bar{height:100%;border-radius:20px;transition:width .7s cubic-bezier(.22,1,.36,1)}
.sub-progress-bar.ok{background:var(--ok)}
.sub-progress-bar.warn{background:var(--warn)}
.sub-progress-bar.err{background:var(--err)}
/* Info grid */
.sub-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:.9rem}
.si{background:var(--bg);border:1px solid var(--border);border-radius:9px;padding:.6rem .85rem}
.si-l{font-size:.66rem;color:var(--faint);margin-bottom:.15rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em}
.si-v{font-size:.84rem;font-weight:700}
/* Aviso vencimento */
.sub-warn-box{background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.3);border-radius:10px;padding:.8rem 1rem;font-size:.79rem;color:#92400e;display:flex;align-items:flex-start;gap:.45rem;line-height:1.5;margin-bottom:.9rem}
.sub-warn-box .ms{font-size:.95rem;flex-shrink:0;margin-top:1px}
/* Histórico */
.sub-hist-item{background:var(--bg);border-radius:9px;padding:.6rem .85rem;margin-bottom:.4rem;display:flex;align-items:flex-start;gap:.65rem;border-left:3px solid var(--ok)}
.sub-hist-item.renovada{border-left-color:var(--p)}
.sub-hist-item.criada{border-left-color:var(--violet)}
.sub-hist-item.suspensa,.sub-hist-item.expirada{border-left-color:var(--err)}
.shi-ico{width:27px;height:27px;border-radius:7px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:var(--ok-dim);color:var(--ok)}
.sub-hist-item.renovada .shi-ico{background:var(--p-dim);color:var(--p)}
.sub-hist-item.criada .shi-ico{background:var(--violet-dim);color:var(--violet)}
.sub-hist-item.suspensa .shi-ico,.sub-hist-item.expirada .shi-ico{background:var(--err-dim);color:var(--err)}
.shi-ico .ms{font-size:.88rem}
.shi-title{font-size:.78rem;font-weight:700}
.shi-sub{font-size:.7rem;color:var(--muted);margin-top:.05rem}
.shi-admin{font-size:.64rem;color:var(--faint);margin-top:.04rem}
.sub-empty{text-align:center;padding:1.4rem;color:var(--faint);font-size:.78rem}
.sub-empty .ms{font-size:1.7rem;display:block;margin-bottom:.3rem;opacity:.25}
.sub-renew-wrap{margin:1rem 0 1.1rem}
.sub-renew-head{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--faint);margin-bottom:.7rem;display:flex;align-items:center;gap:.35rem}
.sub-renew-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.65rem}
.sub-plan{border:1.5px solid var(--border);border-radius:12px;padding:.9rem .95rem;background:var(--card);transition:all .18s}
.sub-plan:hover{border-color:var(--p);box-shadow:var(--sh)}
.sub-plan-name{font-size:.84rem;font-weight:800}
.sub-plan-price{font-size:.92rem;font-weight:800;color:var(--p);margin:.15rem 0}
.sub-plan-meta{font-size:.7rem;color:var(--muted);line-height:1.45;margin-bottom:.8rem}
.sub-plan-btn{width:100%;padding:.62rem .75rem;border:none;border-radius:10px;background:var(--p);color:#fff;font-size:.76rem;font-weight:700;font-family:inherit;display:flex;align-items:center;justify-content:center;gap:.32rem;cursor:pointer;transition:all .18s}
.sub-plan-btn:hover{background:var(--p-dark)}
.sub-plan-btn:disabled{background:var(--faint);cursor:not-allowed}
.sub-plan-btn .ms{font-size:.92rem}

/* ═══ RESPONSIVE ═════════════════════════════════════════════ */
@media(max-width:1024px){.cfg-grid{grid-template-columns:1fr}.cfg-nav{position:static;display:flex;overflow-x:auto;border-radius:var(--r)}.cfg-nav-item{flex-direction:column;gap:.2rem;padding:.6rem .9rem;font-size:.72rem;border-left:none;border-bottom:2.5px solid transparent;flex-shrink:0}.cfg-nav-item.on{border-bottom-color:var(--p);border-left:none}.nav-dot{position:absolute;top:.4rem;right:.4rem;margin:0}}
@media(max-width:860px){.sb{transform:translateX(-100%)}.sb.open{transform:translateX(0)}.main{margin-left:0}.ham{display:flex}.main{padding:1rem 1rem calc(72px + env(safe-area-inset-bottom))}}
@media(max-width:640px){.bnav{display:block}.form-grid{grid-template-columns:1fr}.form-grid .span2{grid-column:auto}.logo-section{flex-direction:column}.logo-dropzone{width:140px;height:140px}.sub-info-grid{grid-template-columns:1fr}.sub-renew-grid{grid-template-columns:1fr}}
.passcode-card{max-width:760px}.passcode-summary{display:flex;align-items:center;gap:.8rem;padding:1rem;border:1px solid var(--border);border-radius:11px;background:linear-gradient(110deg,#fbfdff,var(--bg))}.passcode-summary-icon{display:grid;place-items:center;width:38px;height:38px;border-radius:10px;background:var(--p-dim);color:var(--p)}.passcode-summary-copy{display:grid;gap:2px;min-width:0;flex:1}.passcode-summary-copy strong{font-size:.86rem}.passcode-summary-copy span,.passcode-summary-copy small{font-size:.74rem;color:var(--muted)}.passcode-summary-copy small{color:var(--faint)}.passcode-status{display:inline-flex;align-items:center;gap:5px;color:var(--muted);font-size:.72rem;font-weight:700;white-space:nowrap}.passcode-status i{width:7px;height:7px;border-radius:50%;background:var(--faint)}.passcode-status.is-on{color:#047857}.passcode-status.is-on i{background:var(--ok);box-shadow:0 0 0 4px var(--ok-dim)}.passcode-actions{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1rem}.passcode-danger{color:var(--err)!important}.pc-modal{display:none;position:fixed;inset:0;z-index:1000;align-items:center;justify-content:center;padding:1rem;background:rgba(15,28,46,.46);backdrop-filter:blur(3px)}.pc-modal.open{display:flex}.pc-modal-box{position:relative;width:min(100%,420px);padding:1.6rem;border:1px solid var(--border);border-radius:15px;background:var(--card);box-shadow:0 24px 64px rgba(15,28,46,.22);animation:popIn .2s ease-out}.pc-modal-box h2{margin:0;font-size:1.12rem;letter-spacing:-.03em}.pc-modal-box p{margin:.4rem 0 1.2rem;color:var(--muted);font-size:.8rem}.pc-modal-close{position:absolute;top:.85rem;right:.85rem;border:0;background:transparent;color:var(--faint);padding:.25rem}.pc-flow-kicker{margin-bottom:.55rem;color:var(--p);font-size:.63rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.pc-flow-step{display:none}.pc-flow-step.on{display:block}.pc-pin{position:relative;max-width:340px;margin:1rem auto}.pc-pin input{position:absolute;inset:0;z-index:2;width:100%;height:54px;opacity:.01;cursor:text}.pc-pin>div{display:grid;grid-template-columns:repeat(6,1fr);gap:7px}.pc-pin>div span{height:54px;display:grid;place-items:center;border:1px solid var(--border);border-radius:9px;background:#fff}.pc-pin>div span.filled:after{content:'•';font-size:1.45rem;color:var(--ink);animation:popIn .15s ease-out}.pc-pin.focused>div span.active{border-color:var(--p);background:var(--p-dim);box-shadow:0 0 0 3px var(--p-glow)}.pc-pin.error>div{animation:pcShake .25s ease-in-out}.pc-pin.error>div span{border-color:var(--err)}.pc-match{min-height:18px;color:var(--ok);font-size:.74rem;font-weight:700;text-align:center}.pc-password-label{display:block;margin:.9rem 0 .35rem;color:var(--muted);font-size:.72rem;font-weight:700}.pc-password{width:100%;height:43px;padding:0 .75rem;border:1px solid var(--border);border-radius:9px;font:inherit;outline:0}.pc-password:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}.pc-modal-foot{display:flex;justify-content:flex-end;gap:.55rem;margin-top:1.35rem}.pc-modal-foot .btn{margin:0}.pc-success{display:grid;place-items:center;width:48px;height:48px;margin:0 auto .9rem;border-radius:50%;background:var(--ok-dim);color:var(--ok);animation:popIn .22s ease-out}.pc-success span{font-size:1.6rem}.pc-flow-step[data-step="success"]{text-align:center}.pc-delete{background:var(--err)!important}@keyframes pcShake{25%{transform:translateX(-4px)}75%{transform:translateX(4px)}}@media(max-width:480px){.passcode-summary{align-items:flex-start}.passcode-status{margin-top:3px}.pc-modal{padding:.7rem}.pc-modal-box{padding:1.35rem 1.1rem}.pc-pin>div{gap:5px}.pc-pin>div span,.pc-pin input{height:48px}.pc-modal-foot .btn{flex:1}.passcode-actions .btn{flex:1;justify-content:center}}
</style>
<link rel="stylesheet" href="assets/app-theme.css?v=20260723">
<script src="assets/app-theme.js?v=20260723"></script>
</head>
<body>

<?php
// ── Banner topo (NOVO) ──────────────────────────────────────────────────────
if (!$is_admin && $sub['ativa'] && $sub['expirando']): ?>
<div class="sub-banner expirando" id="subBanner">
    <span class="ms material-symbols-outlined">warning</span>
    Sua assinatura expira em <strong><?= $sub['dias_restantes'] ?> dia<?= $sub['dias_restantes'] === 1 ? '' : 's' ?></strong>
    (<?= fmt_date_br($sub['data_fim']) ?>). Renove sua assinatura.
    <button class="sub-banner-close" onclick="this.parentElement.style.display='none';document.body.classList.remove('has-banner')" title="Fechar">×</button>
</div>
<script>document.body.classList.add('has-banner');</script>
<?php elseif (!$is_admin && !$sub['ativa']): ?>
<div class="sub-banner expirada">
    <span class="ms material-symbols-outlined">event_busy</span>
    Sua assinatura está inativa. Redirecionando…
</div>
<script>document.body.classList.add('has-banner');setTimeout(()=>location.href='bloqueio.php',2500);</script>
<?php endif; ?>

<div class="app">
<div class="overlay" id="ov"></div>
<div class="toast" id="toast"><span class="ms" id="toast-ico">check_circle</span><span id="toast-txt"></span></div>

<!-- ═══ SIDEBAR ════════════════════════════════════════════════ -->
<?php require __DIR__ . '/includes/sidebar.php'; ?>

<!-- ═══ MAIN ════════════════════════════════════════════════════ -->
<main class="main">

    <div class="top anim d1">
        <button class="ham" id="ham"><span class="ms material-symbols-outlined">menu</span></button>
        <div>
            <div class="ptitle">️ Configurações</div>
            <div class="psub">Gerencie seus dados e preferências</div>
        </div>
    </div>

    <div class="cfg-grid anim d2">

        <!-- ── Navegação lateral ── -->
        <div class="cfg-nav">
            <div class="cfg-nav-item on" data-sec="perfil">
                <span class="ms material-symbols-outlined">person</span>Meu Perfil
            </div>
            <div class="cfg-nav-item" data-sec="whatsapp">
                <span class="ms material-symbols-outlined">phone_iphone</span>WhatsApp / Vendas
            </div>
            <div class="cfg-nav-item" data-sec="empresa">
                <span class="ms material-symbols-outlined">business</span>Dados da Empresa
            </div>
            <div class="cfg-nav-item" data-sec="logo">
                <span class="ms material-symbols-outlined">image</span>Logo
            </div>
            <!-- NOVO: aba assinatura -->
            <div class="cfg-nav-item" data-sec="assinatura">
                <span class="ms material-symbols-outlined">card_membership</span>Assinatura
                <?php if (!$is_admin && (!$sub['ativa'] || $sub['expirando'])): ?>
                <span class="nav-dot <?= !$sub['ativa'] ? 'err' : '' ?>"></span>
                <?php endif; ?>
            </div>
            <div class="cfg-nav-divider"></div>
            <div class="cfg-nav-item" data-sec="senha">
                <span class="ms material-symbols-outlined">lock</span>Alterar Senha
            </div>
            <div class="cfg-nav-item" data-sec="passcode">
                <span class="ms material-symbols-outlined">shield_lock</span>Pass Code
            </div>
        </div>

        <!-- ── Conteúdo ── -->
        <div>

            <!-- ════ SEÇÃO: PERFIL ════ -->
            <div class="section on" id="sec-perfil">
                <div class="profile-header anim d3">
                    <div class="profile-av">
                        <?php if ($sidebarAvatar): ?>
                            <img src="<?= htmlspecialchars($sidebarAvatar) ?>" alt="Foto de perfil de <?= htmlspecialchars($username) ?>">
                        <?php else: ?>
                            <?= htmlspecialchars(mb_strtoupper(mb_substr($username, 0, 2))) ?>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <div class="profile-name"><?= htmlspecialchars($username) ?></div>
                        <div class="profile-role">Conta Uplar</div>
                        <div class="profile-tag">
                            <span class="ms material-symbols-outlined" style="font-size:.8rem"><?= $is_admin ? 'admin_panel_settings' : 'person' ?></span>
                            <?= $is_admin ? 'Administrador' : 'Usuário padrão' ?>
                        </div>
                    </div>
                </div>

                <div class="card anim d4">
                    <div class="card-head">
                        <div class="card-head-ico p"><span class="ms material-symbols-outlined">badge</span></div>
                        <div><div class="card-title">Informações da Conta</div><div class="card-sub">Seus dados de acesso ao sistema</div></div>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label><span class="ms material-symbols-outlined" style="font-size:.85rem">person</span> Usuário</label>
                            <input type="text" value="<?= htmlspecialchars($username) ?>" disabled style="background:var(--bg);color:var(--muted)">
                            <span class="hint">Nome de usuário não pode ser alterado</span>
                        </div>
                        <div class="form-group">
                            <label><span class="ms material-symbols-outlined" style="font-size:.85rem">admin_panel_settings</span> Nível de acesso</label>
                            <input type="text" value="<?= $is_admin ? 'Administrador' : 'Usuário' ?>" disabled style="background:var(--bg);color:var(--muted)">
                        </div>
                    </div>
                    <div class="divider"></div>
                    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.6rem">
                        <span style="font-size:.82rem;color:var(--muted)">Para alterar sua senha, acesse a seção específica.</span>
                        <button class="btn btn-o" onclick="goSec('senha')">
                            <span class="ms material-symbols-outlined">lock</span>Alterar Senha
                        </button>
                    </div>
                </div>
            </div>

            <!-- ════ SEÇÃO: WHATSAPP ════ -->
            <div class="section" id="sec-whatsapp">
                <div class="card anim d3">
                    <div class="card-head">
                        <div class="card-head-ico ok"><span class="ms material-symbols-outlined">phone_iphone</span></div>
                        <div>
                            <div class="card-title">WhatsApp para Vendas</div>
                            <div class="card-sub">Número usado para receber pedidos da vitrine e notificações</div>
                        </div>
                    </div>

                    <div id="wa-alert"></div>

                    <div class="form-grid">
                        <div class="form-group span2">
                            <label><span class="ms material-symbols-outlined" style="font-size:.85rem">phone_iphone</span> Número do WhatsApp <span class="req">*</span></label>
                            <div class="inp-wrap">
                                <span class="ms material-symbols-outlined inp-ico">tag</span>
                                <input type="tel" id="wa_number"
                                       value="<?= htmlspecialchars($whatsapp_number) ?>"
                                       placeholder="5592999999999"
                                       maxlength="15"
                                       oninput="this.value=this.value.replace(/\D/g,'')">
                            </div>
                            <span class="hint">DDI + DDD + número, somente dígitos. Ex: <strong>5592999999999</strong></span>
                        </div>
                    </div>

                    <div style="background:var(--p-dim);border:1px solid rgba(59,111,245,.18);border-radius:10px;padding:.85rem 1rem;margin-top:.8rem;display:flex;align-items:flex-start;gap:.55rem">
                        <span class="ms material-symbols-outlined" style="font-size:1rem;color:var(--p);flex-shrink:0;margin-top:.05rem">info</span>
                        <div style="font-size:.78rem;color:var(--p);line-height:1.55">
                            Este número é usado para receber os pedidos feitos na sua vitrine online via WhatsApp. Certifique-se de que ele está com o WhatsApp ativo.
                        </div>
                    </div>

                    <div class="btn-row">
                        <button class="btn btn-p" id="btn-wa" onclick="saveWhatsApp()">
                            <span class="ms material-symbols-outlined">save</span>Salvar número
                        </button>
                        <button class="btn btn-o" onclick="$('wa_number').value='<?= addslashes($whatsapp_number) ?>'">
                            <span class="ms material-symbols-outlined">restart_alt</span>Desfazer
                        </button>
                    </div>
                </div>
            </div>

            <!-- ════ SEÇÃO: EMPRESA ════ -->
            <div class="section" id="sec-empresa">
                <div class="card anim d3">
                    <div class="card-head">
                        <div class="card-head-ico p"><span class="ms material-symbols-outlined">business</span></div>
                        <div><div class="card-title">Dados da Empresa</div><div class="card-sub">Informações utilizadas em documentos e relatórios</div></div>
                    </div>

                    <div id="empresa-alert"></div>

                    <form id="form-empresa" onsubmit="saveEmpresa(event)">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Razão Social <span class="req">*</span></label>
                                <input type="text" id="razao_social" name="razao_social" value="<?= htmlspecialchars($empresa['razao_social']) ?>" placeholder="Ex: Uplar Comércio LTDA" maxlength="255">
                            </div>
                            <div class="form-group">
                                <label>Nome Fantasia</label>
                                <input type="text" id="nome_fantasia" name="nome_fantasia" value="<?= htmlspecialchars($empresa['nome_fantasia']) ?>" placeholder="Ex: Uplar" maxlength="255">
                            </div>
                        </div>

                        <div class="form-grid" style="margin-top:.9rem">
                            <div class="form-group">
                                <label>CNPJ</label>
                                <div class="inp-wrap">
                                    <input type="text" id="cnpj" name="cnpj" value="<?= htmlspecialchars($empresa['cnpj']) ?>" placeholder="00.000.000/0000-00" maxlength="18" oninput="maskCNPJ(this)">
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Telefone</label>
                                <div class="inp-wrap">
                                    <input type="text" id="telefone" name="telefone" value="<?= htmlspecialchars($empresa['telefone']) ?>" placeholder="(00) 00000-0000" maxlength="20" oninput="maskPhone(this)">
                                </div>
                            </div>
                        </div>

                        <div class="form-grid" style="margin-top:.9rem">
                            <div class="form-group span2">
                                <label>E-mail da empresa</label>
                                <div class="inp-wrap">
                                    <span class="ms material-symbols-outlined inp-ico">mail</span>
                                    <input type="email" id="email_empresa" name="email_empresa" value="<?= htmlspecialchars($empresa['email']) ?>" placeholder="contato@empresa.com.br" maxlength="150">
                                </div>
                            </div>
                        </div>

                        <div class="divider"></div>
                        <div style="font-size:.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.8rem">
                            <span class="ms material-symbols-outlined" style="font-size:.9rem;vertical-align:middle">location_on</span> Endereço
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label>CEP</label>
                                <input type="text" id="cep" name="cep" value="<?= htmlspecialchars($empresa['cep']) ?>" placeholder="00000-000" maxlength="9" oninput="maskCEP(this)" onblur="buscaCEP(this.value)">
                                <span class="hint" id="cep-hint"></span>
                            </div>
                            <div class="form-group span2" style="grid-column:auto">
                                <label>Endereço (rua, número, complemento)</label>
                                <input type="text" id="endereco" name="endereco" value="<?= htmlspecialchars($empresa['endereco']) ?>" placeholder="Ex: Av. Paulista, 1000, Sala 42" maxlength="255">
                            </div>
                        </div>

                        <div class="form-grid" style="margin-top:.9rem">
                            <div class="form-group">
                                <label>Cidade</label>
                                <input type="text" id="cidade" name="cidade" value="<?= htmlspecialchars($empresa['cidade']) ?>" placeholder="São Paulo" maxlength="100">
                            </div>
                            <div class="form-group">
                                <label>Estado (UF)</label>
                                <select id="estado" name="estado">
                                    <?php
                                    $ufs=['','AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                                    foreach($ufs as $uf){$sel=($empresa['estado']===$uf)?'selected':'';echo "<option value='$uf' $sel>".($uf?$uf:'Selecione')."</option>";}?>
                                </select>
                            </div>
                        </div>

                        <div class="btn-row">
                            <button type="submit" class="btn btn-p" id="btn-empresa">
                                <span class="ms material-symbols-outlined">save</span>Salvar Dados
                            </button>
                            <button type="button" class="btn btn-o" onclick="resetEmpresa()">
                                <span class="ms material-symbols-outlined">restart_alt</span>Desfazer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ════ SEÇÃO: LOGO ════ -->
            <div class="section" id="sec-logo">
                <div class="card anim d3">
                    <div class="card-head">
                        <div class="card-head-ico ok"><span class="ms material-symbols-outlined">image</span></div>
                        <div><div class="card-title">Logo da Empresa</div><div class="card-sub">Exibida em relatórios, notas e documentos exportados</div></div>
                    </div>

                    <div id="logo-alert"></div>

                    <div class="logo-section">
                        <div class="logo-preview-wrap">
                            <div class="logo-dropzone <?= $empresa['logo_path'] ? 'has-img' : '' ?>"
                                 id="logo-dropzone"
                                 onclick="document.getElementById('logo-file').click()"
                                 ondragover="event.preventDefault();this.style.borderColor='var(--p)'"
                                 ondragleave="this.style.borderColor=''"
                                 ondrop="handleLogoDrop(event)">
                                <?php if($empresa['logo_path'] && file_exists($empresa['logo_path'])): ?>
                                    <img id="logo-img" src="<?= htmlspecialchars($empresa['logo_path']) ?>?t=<?=time()?>" alt="Logo">
                                    <div class="logo-overlay"><span class="ms material-symbols-outlined">upload</span><span>Trocar logo</span></div>
                                <?php else: ?>
                                    <span class="ms material-symbols-outlined">add_photo_alternate</span>
                                    <div class="logo-dropzone-lbl">Clique ou arraste<br>sua logo aqui</div>
                                <?php endif; ?>
                            </div>
                            <input type="file" id="logo-file" accept="image/png,image/jpeg,image/webp,image/svg+xml" style="display:none" onchange="handleLogoFile(this)">
                        </div>

                        <div class="logo-controls">
                            <div>
                                <div style="font-size:.78rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.05em;margin-bottom:.6rem">Tamanho de exibição</div>
                                <div class="logo-size-wrap">
                                    <div class="logo-size-lbl"><span>Tamanho</span><span id="size-display"><?= $empresa['logo_size'] ?>px</span></div>
                                    <input type="range" class="logo-slider" id="logo-size" min="60" max="300" value="<?= $empresa['logo_size'] ?>" oninput="updateLogoSize(this.value)">
                                    <div style="display:flex;justify-content:space-between;font-size:.65rem;color:var(--faint);margin-top:.15rem"><span>Pequeno</span><span>Médio</span><span>Grande</span></div>
                                </div>
                            </div>
                            <div style="background:var(--bg);border-radius:10px;border:1px solid var(--border);padding:1rem;min-height:100px;display:flex;align-items:center;justify-content:center">
                                <div style="text-align:center">
                                    <?php if($empresa['logo_path'] && file_exists($empresa['logo_path'])): ?>
                                    <img id="logo-preview-size" src="<?= htmlspecialchars($empresa['logo_path']) ?>?t=<?=time()?>" alt="Preview" style="width:<?= $empresa['logo_size'] ?>px;max-width:260px;height:auto;display:block;margin:0 auto">
                                    <?php else: ?>
                                    <div id="logo-preview-size" style="width:<?= $empresa['logo_size'] ?>px;height:60px;background:linear-gradient(135deg,var(--p-dim),var(--p-glow));border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--p);font-size:.75rem;font-weight:700">SUA LOGO</div>
                                    <?php endif; ?>
                                    <div style="font-size:.65rem;color:var(--faint);margin-top:.4rem">Preview do tamanho</div>
                                </div>
                            </div>
                            <div class="logo-note">
                                <span class="ms material-symbols-outlined" style="font-size:.85rem;vertical-align:middle;color:var(--warn)">info</span>
                                PNG ou SVG com fundo transparente funciona melhor. Tamanho máximo: 3MB.
                            </div>
                        </div>
                    </div>

                    <div class="btn-row">
                        <button class="btn btn-ok" id="btn-logo" onclick="saveLogo()">
                            <span class="ms material-symbols-outlined">cloud_upload</span>Salvar Logo e Tamanho
                        </button>
                        <?php if($empresa['logo_path']): ?>
                        <button class="btn btn-err" onclick="deleteLogo()">
                            <span class="ms material-symbols-outlined">delete</span>Remover Logo
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ════ SEÇÃO: ASSINATURA (NOVO) ════ -->
            <div class="section" id="sec-assinatura">
                <div class="card anim d3">
                    <div class="card-head">
                        <div class="card-head-ico ok"><span class="ms material-symbols-outlined">card_membership</span></div>
                        <div>
                            <div class="card-title">Minha Assinatura</div>
                            <div class="card-sub">Status e histórico do seu acesso ao sistema</div>
                        </div>
                    </div>

                    <?php if ($is_admin): ?>
                    <div class="sub-status-box adm">
                        <div class="ssb-ico violet"><span class="ms material-symbols-outlined">shield</span></div>
                        <div>
                            <div class="ssb-title">Administrador — Acesso Permanente</div>
                            <div class="ssb-sub">Administradores têm acesso irrestrito ao sistema, sem necessidade de assinatura.</div>
                        </div>
                    </div>

                    <?php elseif ($sub['ativa']): ?>
                    <?php
                        $box_cls  = $sub['expirando'] ? 'warn' : 'ok';
                        $ico_cls  = $sub['expirando'] ? 'warn' : 'ok';
                        $ico_name = $sub['expirando'] ? 'warning' : 'check_circle';
                        $titulo   = $sub['expirando']
                            ? "Expira em {$sub['dias_restantes']} dia" . ($sub['dias_restantes'] === 1 ? '!' : 's!')
                            : 'Assinatura ativa';
                        $ins      = $sub['inscricao'];
                        $plano    = (int)($ins['plano_dias'] ?? 30);
                        $dias_pas = max(0, $plano - $sub['dias_restantes']);
                        $pct      = max(4, min(100, round(($dias_pas / $plano) * 100)));
                    ?>
                    <div class="sub-status-box <?= $box_cls ?>">
                        <div class="ssb-ico <?= $ico_cls ?>"><span class="ms material-symbols-outlined"><?= $ico_name ?></span></div>
                        <div>
                            <div class="ssb-title"><?= $titulo ?></div>
                            <div class="ssb-sub">Válida até <?= fmt_date_br($sub['data_fim']) ?></div>
                        </div>
                    </div>

                    <div class="sub-progress-wrap">
                        <div class="sub-progress-lbl">
                            <span>Início: <?= fmt_date_br($ins['data_inicio'] ?? null) ?></span>
                            <span>Fim: <?= fmt_date_br($sub['data_fim']) ?></span>
                        </div>
                        <div class="sub-progress">
                            <div class="sub-progress-bar <?= $box_cls ?>" style="width:<?= $pct ?>%"></div>
                        </div>
                    </div>

                    <div class="sub-info-grid">
                        <div class="si"><div class="si-l">Dias restantes</div><div class="si-v"><?= $sub['dias_restantes'] ?> dia<?= $sub['dias_restantes'] !== 1 ? 's' : '' ?></div></div>
                        <div class="si"><div class="si-l">Plano contratado</div><div class="si-v"><?= $plano ?> dias</div></div>
                        <div class="si"><div class="si-l">Data de início</div><div class="si-v"><?= fmt_date_br($ins['data_inicio'] ?? null) ?></div></div>
                        <div class="si"><div class="si-l">Vencimento</div><div class="si-v"><?= fmt_date_br($sub['data_fim']) ?></div></div>
                    </div>

                    <?php if ($sub['expirando']): ?>
                    <div class="sub-warn-box">
                        <span class="ms material-symbols-outlined">warning</span>
                        Sua assinatura vence em breve. Escolha um dos planos abaixo para renovar sem perder os dias restantes.
                    </div>
                    <?php endif; ?>

                    <?php else: ?>
                    <?php
                        $ico_name = $sub['status'] === 'suspensa' ? 'pause_circle' : 'event_busy';
                        $titulo   = match($sub['status']) {
                            'sem_inscricao' => 'Sem assinatura',
                            'suspensa'      => 'Assinatura suspensa',
                            default         => 'Assinatura expirada',
                        };
                    ?>
                    <div class="sub-status-box err">
                        <div class="ssb-ico err"><span class="ms material-symbols-outlined"><?= $ico_name ?></span></div>
                        <div>
                            <div class="ssb-title"><?= $titulo ?></div>
                            <div class="ssb-sub">Escolha um dos planos abaixo para liberar ou renovar seu acesso automaticamente.</div>
                        </div>
                    </div>
                    <?php if ($sub['data_fim']): ?>
                    <div class="sub-info-grid">
                        <div class="si"><div class="si-l">Último vencimento</div><div class="si-v"><?= fmt_date_br($sub['data_fim']) ?></div></div>
                        <div class="si"><div class="si-l">Status</div><div class="si-v" style="color:var(--err)"><?= ucfirst($sub['status']) ?></div></div>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!$is_admin): ?>
                    <div class="sub-renew-wrap">
                        <div class="sub-renew-head"><span class="ms material-symbols-outlined" style="font-size:.85rem">sell</span>Atualizar inscrição</div>
                        <div class="sub-renew-grid">
                            <div class="sub-plan">
                                <div class="sub-plan-name">Mensal</div>
                                <div class="sub-plan-price">R$ 19,90</div>
                                <div class="sub-plan-meta">30 dias de acesso para liberar ou renovar sua conta.</div>
                                <button type="button" class="sub-plan-btn" onclick="iniciarRenovacaoPlano('mensal', this)"><span class="ms material-symbols-outlined">credit_card</span>Pagar mensal</button>
                            </div>
                            <div class="sub-plan">
                                <div class="sub-plan-name">Trimestral</div>
                                <div class="sub-plan-price">R$ 54,90</div>
                                <div class="sub-plan-meta">90 dias com melhor custo para manter a operação estável.</div>
                                <button type="button" class="sub-plan-btn" onclick="iniciarRenovacaoPlano('trimestral', this)"><span class="ms material-symbols-outlined">workspace_premium</span>Pagar trimestral</button>
                            </div>
                            <div class="sub-plan">
                                <div class="sub-plan-name">Anual</div>
                                <div class="sub-plan-price">R$ 199,00</div>
                                <div class="sub-plan-meta">365 dias com maior economia e renovação antecipada sem perder dias.</div>
                                <button type="button" class="sub-plan-btn" onclick="iniciarRenovacaoPlano('anual', this)"><span class="ms material-symbols-outlined">diamond</span>Pagar anual</button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Histórico -->
                    <?php if (!empty($sub_historico)): ?>
                    <div class="divider"></div>
                    <div style="font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--faint);margin-bottom:.65rem;display:flex;align-items:center;gap:.35rem">
                        <span class="ms material-symbols-outlined" style="font-size:.85rem">history</span>Histórico de renovações
                    </div>
                    <?php
                    $acaoIcos=['criada'=>'add_circle','renovada'=>'autorenew','suspensa'=>'pause_circle','expirada'=>'event_busy','reativada'=>'play_circle'];
                    foreach ($sub_historico as $h): ?>
                    <div class="sub-hist-item <?= htmlspecialchars($h['acao']) ?>">
                        <div class="shi-ico"><span class="ms material-symbols-outlined"><?= $acaoIcos[$h['acao']] ?? 'history' ?></span></div>
                        <div>
                            <div class="shi-title"><?= ucfirst($h['acao']) ?><?= $h['plano_dias'] ? ' — '.(int)$h['plano_dias'].' dias' : '' ?></div>
                            <div class="shi-sub"><?= $h['data_fim_nova'] ? 'Válida até '.fmt_date_br($h['data_fim_nova']) : '' ?></div>
                            <div class="shi-admin">Por: <?= htmlspecialchars($h['admin_nome']??'—') ?> · <?= htmlspecialchars($h['data_fmt']??'') ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <div class="divider"></div>
                    <div class="sub-empty"><span class="ms material-symbols-outlined">history</span>Nenhum histórico encontrado.</div>
                    <?php endif; ?>

                </div>
            </div>
            <!-- ════ FIM SEÇÃO ASSINATURA ════ -->

            <div class="section" id="sec-passcode">
                <div class="card anim d3 passcode-card">
                    <div class="card-head"><div class="card-head-ico violet"><span class="ms material-symbols-outlined">shield_lock</span></div><div><div class="card-title">Segurança da conta</div><div class="card-sub">Gerencie a proteção adicional do seu acesso.</div></div></div>
                    <div id="passcode-alert"></div>
                    <div class="passcode-summary"><div class="passcode-summary-icon"><span class="ms material-symbols-outlined">password</span></div><div class="passcode-summary-copy"><strong>Pass Code</strong><span><?= $passcode_enabled ? 'Proteção adicional ativada.' : 'Adicione uma camada extra de proteção ao seu acesso.' ?></span><?php if ($passcode_enabled): ?><small>Configurado em <?= e($passcode_configured_at) ?></small><?php endif; ?></div><div class="passcode-status <?= $passcode_enabled ? 'is-on' : '' ?>"><i></i><?= $passcode_enabled ? 'Ativado' : 'Não configurado' ?></div></div>
                    <div class="passcode-actions"><button type="button" class="btn btn-p" onclick="openPasscodeFlow()"><span class="ms material-symbols-outlined"><?= $passcode_enabled ? 'edit' : 'shield_lock' ?></span><?= $passcode_enabled ? 'Alterar Pass Code' : 'Configurar Pass Code' ?></button><?php if ($passcode_enabled): ?><button type="button" class="btn btn-o passcode-danger" onclick="openPasscodeDisable()">Desativar</button><?php endif; ?></div>
                </div>
            </div>

            <!-- ════ SEÇÃO: ALTERAR SENHA ════ -->
            <div class="section" id="sec-senha">
                <div class="card anim d3">
                    <div class="card-head">
                        <div class="card-head-ico warn"><span class="ms material-symbols-outlined">lock</span></div>
                        <div><div class="card-title">Alterar Senha</div><div class="card-sub">Use uma senha forte com letras, números e símbolos</div></div>
                    </div>

                    <div id="senha-alert"></div>

                    <form id="form-senha" onsubmit="saveSenha(event)" autocomplete="off">
                        <div class="form-group" style="margin-bottom:.9rem">
                            <label>Senha atual <span class="req">*</span></label>
                            <div class="inp-wrap" style="position:relative">
                                <input type="password" id="senha_atual" name="senha_atual" placeholder="Digite sua senha atual" autocomplete="current-password">
                                <button type="button" class="pw-toggle" onclick="togglePw('senha_atual',this)"><span class="ms material-symbols-outlined">visibility</span></button>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom:.4rem">
                            <label>Nova senha <span class="req">*</span></label>
                            <div class="inp-wrap" style="position:relative">
                                <input type="password" id="senha_nova" name="senha_nova" placeholder="Mínimo 8 caracteres" autocomplete="new-password" oninput="checkStrength(this.value)">
                                <button type="button" class="pw-toggle" onclick="togglePw('senha_nova',this)"><span class="ms material-symbols-outlined">visibility</span></button>
                            </div>
                            <div class="pw-strength"><div class="pw-strength-bar" id="pw-bar"></div></div>
                            <div class="pw-strength-txt" id="pw-txt" style="color:var(--faint)">Digite a nova senha</div>
                        </div>

                        <div class="form-group" style="margin-top:.9rem">
                            <label>Confirmar nova senha <span class="req">*</span></label>
                            <div class="inp-wrap" style="position:relative">
                                <input type="password" id="senha_conf" name="senha_conf" placeholder="Repita a nova senha" autocomplete="new-password" oninput="checkMatch()">
                                <button type="button" class="pw-toggle" onclick="togglePw('senha_conf',this)"><span class="ms material-symbols-outlined">visibility</span></button>
                            </div>
                            <div class="hint" id="match-hint"></div>
                        </div>

                        <div style="background:var(--bg);border-radius:10px;padding:.85rem 1rem;margin-top:1rem;display:grid;grid-template-columns:1fr 1fr;gap:.3rem">
                            <div class="req-check" id="req-len"><span class="ms material-symbols-outlined" style="font-size:.9rem;vertical-align:middle">radio_button_unchecked</span> <span style="font-size:.74rem"> Mínimo 8 caracteres</span></div>
                            <div class="req-check" id="req-upper"><span class="ms material-symbols-outlined" style="font-size:.9rem;vertical-align:middle">radio_button_unchecked</span> <span style="font-size:.74rem"> Letra maiúscula</span></div>
                            <div class="req-check" id="req-num"><span class="ms material-symbols-outlined" style="font-size:.9rem;vertical-align:middle">radio_button_unchecked</span> <span style="font-size:.74rem"> Número</span></div>
                            <div class="req-check" id="req-sym"><span class="ms material-symbols-outlined" style="font-size:.9rem;vertical-align:middle">radio_button_unchecked</span> <span style="font-size:.74rem"> Símbolo (!@#...)</span></div>
                        </div>

                        <div class="btn-row">
                            <button type="submit" class="btn btn-p" id="btn-senha" disabled>
                                <span class="ms material-symbols-outlined">lock_reset</span>Alterar Senha
                            </button>
                            <button type="button" class="btn btn-o" onclick="document.getElementById('form-senha').reset();resetPwUI()">
                                <span class="ms material-symbols-outlined">close</span>Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div><!-- fim conteúdo -->
    </div><!-- fim cfg-grid -->
</main>
</div><!-- .app -->

<div class="pc-modal" id="passcode-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="pc-modal-title">
    <div class="pc-modal-box"><button type="button" class="pc-modal-close" onclick="closePasscodeModal()" aria-label="Fechar"><span class="ms material-symbols-outlined">close</span></button>
        <div class="pc-flow-step on" data-step="create"><div class="pc-flow-kicker">1 de 3</div><h2 id="pc-modal-title"><?= $passcode_enabled ? 'Novo Pass Code' : 'Crie seu Pass Code' ?></h2><p>Escolha 6 números fáceis de lembrar e difíceis de adivinhar.</p><div class="pc-pin" data-config-pin><input id="passcode_novo" type="password" inputmode="numeric" autocomplete="new-password" maxlength="6" pattern="[0-9]{6}" aria-label="Novo Pass Code"><div aria-hidden="true"></div></div></div>
        <div class="pc-flow-step" data-step="confirm"><div class="pc-flow-kicker">2 de 3</div><h2>Confirme seu Pass Code</h2><p>Digite novamente para confirmar.</p><div class="pc-pin" data-config-pin><input id="passcode_confirmacao" type="password" inputmode="numeric" autocomplete="new-password" maxlength="6" pattern="[0-9]{6}" aria-label="Confirmar Pass Code"><div aria-hidden="true"></div></div><div class="pc-match" id="pc-match" aria-live="polite"></div></div>
        <div class="pc-flow-step" data-step="identity"><div class="pc-flow-kicker">3 de 3</div><h2>Confirme sua identidade</h2><p>Informe sua senha atual para <?= $passcode_enabled ? 'atualizar' : 'ativar' ?> o Pass Code.</p><label class="pc-password-label" for="passcode_senha">Senha atual</label><input class="pc-password" type="password" id="passcode_senha" autocomplete="current-password"></div>
        <div class="pc-flow-step" data-step="success"><div class="pc-success"><span class="ms material-symbols-outlined">check</span></div><h2 id="pc-success-title">Pass Code ativado</h2><p>Sua conta agora possui uma camada adicional de proteção.</p></div>
        <div class="pc-modal-foot"><button type="button" class="btn btn-o" id="pc-back" onclick="passcodeFlowBack()">Voltar</button><button type="button" class="btn btn-p" id="pc-next" onclick="passcodeFlowNext()">Continuar</button></div>
    </div>
</div>
<div class="pc-modal" id="passcode-disable-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="pc-disable-title"><div class="pc-modal-box pc-disable-box"><button type="button" class="pc-modal-close" onclick="closePasscodeDisable()" aria-label="Fechar"><span class="ms material-symbols-outlined">close</span></button><div class="pc-flow-kicker">Proteção adicional</div><h2 id="pc-disable-title">Desativar Pass Code?</h2><p>Sua conta voltará a utilizar apenas sua senha no login.</p><label class="pc-password-label" for="passcode_desativar_senha">Senha atual</label><input class="pc-password" type="password" id="passcode_desativar_senha" autocomplete="current-password"><div class="pc-modal-foot"><button type="button" class="btn btn-o" onclick="closePasscodeDisable()">Cancelar</button><button type="button" class="btn btn-p pc-delete" onclick="disablePasscode()">Desativar</button></div></div></div>

<nav class="bnav"><div class="bnav-inner">
    <a href="estoque.php"   class="bnav-a"><span class="ms material-symbols-outlined">dashboard</span>Início</a>
    <a href="financeiro.php"class="bnav-a"><span class="ms material-symbols-outlined">account_balance_wallet</span>Financeiro</a>
    <a href="produtos.php"  class="bnav-a"><span class="ms material-symbols-outlined">inventory</span>Produtos</a>
    <a href="config.php"    class="bnav-a on"><span class="ms material-symbols-outlined">settings</span>Config</a>
</div></nav>

<script>
const CSRF='<?= get_csrf_token() ?>';
const $=id=>document.getElementById(id);

/* ── sidebar mobile ── */
const sb_=$('sb'),ov_=$('ov');
$('ham').addEventListener('click',()=>{sb_.classList.add('open');ov_.classList.add('on');document.body.style.overflow='hidden'});
ov_.addEventListener('click',()=>{sb_.classList.remove('open');ov_.classList.remove('on');document.body.style.overflow=''});

/* ── nav seções ── */
function goSec(sec){
    document.querySelectorAll('.cfg-nav-item').forEach(el=>el.classList.remove('on'));
    document.querySelectorAll('.section').forEach(el=>el.classList.remove('on'));
    document.querySelector(`.cfg-nav-item[data-sec="${sec}"]`)?.classList.add('on');
    $('sec-'+sec)?.classList.add('on');
    if(window.innerWidth<=860){sb_.classList.remove('open');ov_.classList.remove('on');document.body.style.overflow='';}
}
document.querySelectorAll('.cfg-nav-item').forEach(el=>el.addEventListener('click',()=>goSec(el.dataset.sec)));

/* ── toast ── */
let toastTimer;
function showToast(msg,type='ok'){
    const t=$('toast'),ico=$('toast-ico'),txt=$('toast-txt');
    t.className='toast '+(type==='ok'?'ok-t':'er-t');
    ico.textContent=type==='ok'?'check_circle':'error';
    txt.textContent=msg;
    t.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer=setTimeout(()=>t.classList.remove('show'),3200);
}

/* ── api post ── */
async function apiPost(url,data){
    const fd=new FormData(); fd.append('csrf_token',CSRF);
    Object.entries(data).forEach(([k,v])=>{if(v!==null&&v!==undefined)fd.append(k,v);});
    const r=await fetch(url,{method:'POST',body:fd,credentials:'same-origin'});
    return r.json();
}

/* ── masks ── */
function maskCNPJ(el){let v=el.value.replace(/\D/g,'').substring(0,14);v=v.replace(/^(\d{2})(\d)/,'$1.$2');v=v.replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3');v=v.replace(/\.(\d{3})(\d)/,'.$1/$2');v=v.replace(/(\d{4})(\d)/,'$1-$2');el.value=v;}
function maskPhone(el){let v=el.value.replace(/\D/g,'').substring(0,11);if(v.length<=10)v=v.replace(/^(\d{2})(\d{4})(\d*)/,'($1) $2-$3');else v=v.replace(/^(\d{2})(\d{5})(\d*)/,'($1) $2-$3');el.value=v;}
function maskCEP(el){let v=el.value.replace(/\D/g,'').substring(0,8);if(v.length>5)v=v.replace(/^(\d{5})(\d*)/,'$1-$2');el.value=v;}

/* ── ViaCEP ── */
async function buscaCEP(cep){
    const c=cep.replace(/\D/g,'');
    if(c.length!==8)return;
    const hint=$('cep-hint');
    hint.textContent='Buscando endereço…';
    try{
        const r=await fetch(`https://viacep.com.br/ws/${c}/json/`);
        const d=await r.json();
        if(d.erro){hint.textContent='CEP não encontrado.';return;}
        $('endereco').value=`${d.logradouro}${d.bairro?', '+d.bairro:''}`;
        $('cidade').value=d.localidade;
        $('estado').value=d.uf;
        hint.textContent=' Endereço preenchido automaticamente';
        hint.style.color='var(--ok)';
        setTimeout(()=>{hint.textContent='';hint.style.color='';},3000);
    }catch(e){hint.textContent='Erro ao buscar CEP.';}
}

/* ── empresa ── */
const empresaOriginal={
    razao_social:'<?= addslashes($empresa['razao_social']) ?>',
    nome_fantasia:'<?= addslashes($empresa['nome_fantasia']) ?>',
    cnpj:'<?= addslashes($empresa['cnpj']) ?>',
    telefone:'<?= addslashes($empresa['telefone']) ?>',
    email_empresa:'<?= addslashes($empresa['email']) ?>',
    cep:'<?= addslashes($empresa['cep']) ?>',
    endereco:'<?= addslashes($empresa['endereco']) ?>',
    cidade:'<?= addslashes($empresa['cidade']) ?>',
    estado:'<?= addslashes($empresa['estado']) ?>'
};
function resetEmpresa(){Object.entries(empresaOriginal).forEach(([k,v])=>{const el=$(k);if(el){if(el.tagName==='SELECT')el.value=v;else el.value=v;}});}
async function saveEmpresa(e){
    e.preventDefault();
    const razao=$('razao_social').value.trim();
    if(!razao){showAlert('empresa-alert','Razão Social é obrigatória.','er');return;}
    const btn=$('btn-empresa');btn.disabled=true;btn.innerHTML='<div class="spinner"></div>Salvando…';
    const data={acao:'salvar_empresa',razao_social:razao,nome_fantasia:$('nome_fantasia').value.trim(),cnpj:$('cnpj').value.trim(),telefone:$('telefone').value.trim(),email:$('email_empresa').value.trim(),cep:$('cep').value.trim(),endereco:$('endereco').value.trim(),cidade:$('cidade').value.trim(),estado:$('estado').value};
    const j=await apiPost('config_api.php',data);
    btn.disabled=false;btn.innerHTML='<span class="ms material-symbols-outlined">save</span>Salvar Dados';
    if(j.ok){showToast('Dados da empresa salvos!','ok');clearAlert('empresa-alert');}
    else showAlert('empresa-alert',j.error||'Erro ao salvar.','er');
}

/* ── logo ── */
let logoFile=null;
function handleLogoFile(input){if(!input.files||!input.files[0])return;const f=input.files[0];if(f.size>3*1024*1024){showToast('Arquivo muito grande (máx. 3MB)','er');return;}logoFile=f;const reader=new FileReader();reader.onload=ev=>applyLogoPreview(ev.target.result);reader.readAsDataURL(f);}
function handleLogoDrop(e){e.preventDefault();const f=e.dataTransfer.files[0];if(!f||!f.type.startsWith('image/'))return;logoFile=f;const reader=new FileReader();reader.onload=ev=>applyLogoPreview(ev.target.result);reader.readAsDataURL(f);e.currentTarget.style.borderColor='';}
function applyLogoPreview(src){const dz=$('logo-dropzone');dz.innerHTML=`<img id="logo-img" src="${src}" alt="Logo"><div class="logo-overlay"><span class="ms material-symbols-outlined">upload</span><span>Trocar logo</span></div>`;dz.className='logo-dropzone has-img';dz.onclick=()=>$('logo-file').click();const ps=$('logo-preview-size');if(ps.tagName==='IMG'){ps.src=src;}else{const img=document.createElement('img');img.id='logo-preview-size';img.src=src;img.alt='Preview';img.style.cssText=`width:${$('logo-size').value}px;max-width:260px;height:auto;display:block;margin:0 auto`;ps.replaceWith(img);}}
function updateLogoSize(v){$('size-display').textContent=v+'px';const s=$('logo-size');const pct=((v-60)/(300-60))*100;s.style.backgroundSize=pct+'% 100%';const ps=$('logo-preview-size');if(ps)ps.style.width=v+'px';}
async function saveLogo(){const btn=$('btn-logo');btn.disabled=true;btn.innerHTML='<div class="spinner"></div>Enviando…';const fd=new FormData();fd.append('csrf_token',CSRF);fd.append('acao','salvar_logo');fd.append('logo_size',$('logo-size').value);if(logoFile)fd.append('logo',logoFile);const r=await fetch('config_api.php',{method:'POST',body:fd,credentials:'same-origin'});const j=await r.json();btn.disabled=false;btn.innerHTML='<span class="ms material-symbols-outlined">cloud_upload</span>Salvar Logo e Tamanho';if(j.ok){showToast('Logo salva com sucesso!','ok');logoFile=null;clearAlert('logo-alert');}else showAlert('logo-alert',j.error||'Erro ao salvar logo.','er');}
async function deleteLogo(){if(!confirm('Remover a logo da empresa?'))return;const j=await apiPost('config_api.php',{acao:'deletar_logo'});if(j.ok){showToast('Logo removida.','ok');setTimeout(()=>location.reload(),1200);}else showToast('Erro ao remover logo.','er');}
(function(){const s=$('logo-size');if(s){const pct=((s.value-60)/(300-60))*100;s.style.backgroundSize=pct+'% 100%';}})();

/* ── senha ── */
function togglePw(id,btn){const inp=$(id);const isHidden=inp.type==='password';inp.type=isHidden?'text':'password';btn.querySelector('.ms').textContent=isHidden?'visibility_off':'visibility';}
const reqs={len:{el:$('req-len'),test:v=>v.length>=8},upper:{el:$('req-upper'),test:v=>/[A-Z]/.test(v)},num:{el:$('req-num'),test:v=>/[0-9]/.test(v)},sym:{el:$('req-sym'),test:v=>/[^A-Za-z0-9]/.test(v)}};
function checkStrength(v){let score=0;Object.values(reqs).forEach(r=>{const ok=r.test(v);if(ok)score++;r.el.style.color=ok?'var(--ok)':'var(--muted)';r.el.querySelector('.ms').textContent=ok?'check_circle':'radio_button_unchecked';});const bar=$('pw-bar'),txt=$('pw-txt');const widths=['0%','25%','50%','75%','100%'];const cols=['','var(--err)','var(--warn)','var(--warn)','var(--ok)'];const lbls=['','Muito fraca','Fraca','Boa','Forte '];bar.style.width=widths[score];bar.style.background=cols[score];txt.textContent=lbls[score];txt.style.color=cols[score];checkMatch();updateSenhaBtn();}
function checkMatch(){const nova=$('senha_nova').value,conf=$('senha_conf').value,hint=$('match-hint');if(!conf){hint.textContent='';return;}if(nova===conf){hint.style.color='var(--ok)';hint.textContent=' Senhas conferem';}else{hint.style.color='var(--err)';hint.textContent=' Senhas não conferem';}updateSenhaBtn();}
function updateSenhaBtn(){const nova=$('senha_nova').value,conf=$('senha_conf').value,atual=$('senha_atual').value;const allReqs=Object.values(reqs).every(r=>r.test(nova));$('btn-senha').disabled=!(nova===conf&&allReqs&&atual);}
function resetPwUI(){$('pw-bar').style.cssText='width:0';$('pw-txt').textContent='Digite a nova senha';$('pw-txt').style.color='var(--faint)';$('match-hint').textContent='';$('btn-senha').disabled=true;Object.values(reqs).forEach(r=>{r.el.style.color='var(--muted)';r.el.querySelector('.ms').textContent='radio_button_unchecked';});}
['senha_atual','senha_nova','senha_conf'].forEach(id=>$(id)?.addEventListener('input',updateSenhaBtn));
async function saveSenha(e){
    e.preventDefault();
    const nova=$('senha_nova').value,conf=$('senha_conf').value,atual=$('senha_atual').value;
    if(nova!==conf){showAlert('senha-alert','As senhas não conferem.','er');return;}
    const allReqs=Object.values(reqs).every(r=>r.test(nova));
    if(!allReqs){showAlert('senha-alert','A nova senha não atende todos os requisitos.','er');return;}
    const btn=$('btn-senha');btn.disabled=true;btn.innerHTML='<div class="spinner"></div>Alterando…';
    const j=await apiPost('config_api.php',{acao:'alterar_senha',senha_atual:atual,senha_nova:nova});
    btn.disabled=false;btn.innerHTML='<span class="ms material-symbols-outlined">lock_reset</span>Alterar Senha';
    if(j.ok){showAlert('senha-alert','Senha alterada com sucesso!','ok');showToast('Senha alterada!','ok');document.getElementById('form-senha').reset();resetPwUI();}
    else showAlert('senha-alert',j.error||'Erro ao alterar senha.','er');
}

let passcodeStep='create';
function setPasscodeStep(step){passcodeStep=step;document.querySelectorAll('.pc-flow-step').forEach(el=>el.classList.toggle('on',el.dataset.step===step));$('pc-back').style.display=step==='create'||step==='success'?'none':'';$('pc-next').style.display=step==='success'?'none':'';$('pc-next').textContent=step==='identity'?'Salvar Pass Code':'Continuar';if(step==='create')setTimeout(()=>$('passcode_novo').focus(),30);if(step==='confirm')setTimeout(()=>$('passcode_confirmacao').focus(),30);if(step==='identity')setTimeout(()=>$('passcode_senha').focus(),30);}
function openPasscodeFlow(){['passcode_novo','passcode_confirmacao','passcode_senha'].forEach(id=>$(id).value='');document.querySelectorAll('.pc-pin').forEach(renderConfigPin);$('passcode-modal').classList.add('open');$('passcode-modal').setAttribute('aria-hidden','false');setPasscodeStep('create');}
function closePasscodeModal(){$('passcode-modal').classList.remove('open');$('passcode-modal').setAttribute('aria-hidden','true');}
function passcodeFlowBack(){if(passcodeStep==='confirm')setPasscodeStep('create');else if(passcodeStep==='identity')setPasscodeStep('confirm');}
function passcodeFlowNext(){const code=$('passcode_novo').value,confirmacao=$('passcode_confirmacao').value;if(passcodeStep==='create'){if(!/^\d{6}$/.test(code)){markConfigPin('passcode_novo');return;}setPasscodeStep('confirm');return;}if(passcodeStep==='confirm'){if(confirmacao!==code){markConfigPin('passcode_confirmacao');$('pc-match').textContent='Os códigos não conferem.';return;}$('pc-match').textContent='✓ Pass Code confirmado';setPasscodeStep('identity');return;}if(passcodeStep==='identity')savePasscode();}
function markConfigPin(id){const pin=$(id).closest('.pc-pin');pin.classList.add('error');setTimeout(()=>pin.classList.remove('error'),320);$(id).focus();}
async function savePasscode(){const btn=$('pc-next');btn.disabled=true;btn.innerHTML='<span class="ms material-symbols-outlined">progress_activity</span>Salvando…';const j=await apiPost('config_api.php',{acao:'salvar_passcode',senha_atual:$('passcode_senha').value,passcode:$('passcode_novo').value,passcode_confirmacao:$('passcode_confirmacao').value});btn.disabled=false;btn.textContent='Salvar Pass Code';if(j.ok){$('pc-success-title').textContent='<?= $passcode_enabled ? 'Pass Code atualizado' : 'Pass Code ativado' ?>';setPasscodeStep('success');setTimeout(()=>location.reload(),1100);}else{showAlert('passcode-alert',j.error||'Não foi possível salvar o Pass Code.','er');setPasscodeStep('identity');}}
function openPasscodeDisable(){$('passcode_desativar_senha').value='';$('passcode-disable-modal').classList.add('open');$('passcode-disable-modal').setAttribute('aria-hidden','false');setTimeout(()=>$('passcode_desativar_senha').focus(),30);}
function closePasscodeDisable(){$('passcode-disable-modal').classList.remove('open');$('passcode-disable-modal').setAttribute('aria-hidden','true');}
async function disablePasscode(){const btn=document.querySelector('#passcode-disable-modal .pc-delete');btn.disabled=true;btn.textContent='Desativando…';const j=await apiPost('config_api.php',{acao:'desativar_passcode',senha_atual:$('passcode_desativar_senha').value});if(j.ok){showToast('Pass Code desativado.','ok');setTimeout(()=>location.reload(),700);}else{btn.disabled=false;btn.textContent='Desativar';showAlert('passcode-alert',j.error||'Não foi possível desativar o Pass Code.','er');}}
function renderConfigPin(pin){const input=pin.querySelector('input'),slots=pin.querySelector('div');if(!slots.children.length)for(let i=0;i<6;i++){const slot=document.createElement('span');slots.appendChild(slot);}const value=input.value.replace(/\D/g,'').slice(0,6);input.value=value;[...slots.children].forEach((slot,i)=>slot.className=(i<value.length?'filled ':'')+(i===value.length?'active':''));}
document.querySelectorAll('[data-config-pin]').forEach(pin=>{const input=pin.querySelector('input');renderConfigPin(pin);input.addEventListener('input',()=>{renderConfigPin(pin);if(input.id==='passcode_confirmacao'&&input.value.length===6){$('pc-match').textContent=input.value===$('passcode_novo').value?'✓ Pass Code confirmado':'';}});input.addEventListener('focus',()=>pin.classList.add('focused'));input.addEventListener('blur',()=>pin.classList.remove('focused'));pin.addEventListener('click',()=>input.focus());});

/* ── whatsapp ── */
async function saveWhatsApp() {
    const n = $('wa_number').value.replace(/\D/g,'');
    if (!n || n.length < 10) { showAlert('wa-alert','Número inválido. Informe DDI + DDD + número.','er'); return; }
    const btn = $('btn-wa');
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner"></div>Salvando…';
    const j = await apiPost('api.php?acao=save_whatsapp', { whatsapp_number: n });
    btn.disabled = false;
    btn.innerHTML = '<span class="ms material-symbols-outlined">save</span>Salvar número';
    if (j.ok) { showToast('WhatsApp salvo com sucesso!', 'ok'); clearAlert('wa-alert'); }
    else showAlert('wa-alert', j.error || 'Erro ao salvar.', 'er');
}

/* ── alert inline ── */
function showAlert(id,msg,type){$(id).innerHTML=`<div class="alert ${type}"><span class="ms material-symbols-outlined">${type==='ok'?'check_circle':'error'}</span><span>${msg}</span></div>`;setTimeout(()=>clearAlert(id),5000);}
function clearAlert(id){$(id).innerHTML='';}

async function iniciarRenovacaoPlano(plano, btn){
    if(!plano) return;
    const original=btn.innerHTML;
    btn.disabled=true;
    btn.innerHTML='<div class="spinner"></div>Gerando…';
    try{
        const resp=await fetch('payment_api.php?acao=criar_link',{
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body:new URLSearchParams({csrf_token:CSRF,plano}),
            credentials:'same-origin'
        });
        const data=await resp.json();
        if(!resp.ok||!data.checkout_url) throw new Error(data.error||'Não foi possível gerar o pagamento.');
        window.location.href=data.checkout_url;
    }catch(err){
        showToast(err.message||'Erro ao gerar o pagamento.','er');
        btn.disabled=false;
        btn.innerHTML=original;
    }
}

/* ── hash URL ── */
(function(){const h=location.hash.replace('#','');if(h&&document.querySelector(`.cfg-nav-item[data-sec="${h}"]`))goSec(h);})();
</script>
</body>
</html>
