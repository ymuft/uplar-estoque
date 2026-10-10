<?php
// vitrine_config.php — v5
// Abas: Loja | Produtos
session_start();
require 'db.php';
require 'helpers.php';
require_once __DIR__ . '/includes/vitrine_experience.php';
require_once __DIR__ . '/includes/business_profile_runtime.php';
require_once __DIR__ . '/includes/tracking_runtime.php';
require_once __DIR__ . '/includes/vitrine_featured.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id  = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];
$pdo      = get_pdo();
vitrine_ensure_featured_schema($pdo);

get_csrf_token();

$stmt = $pdo->prepare("SELECT vitrine_slug, vitrine_ativa, whatsapp_number, role FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$udata = $stmt->fetch(PDO::FETCH_ASSOC);

$slug_atual = $udata['vitrine_slug'] ?? '';
$ativa      = (int)($udata['vitrine_ativa'] ?? 1);

$cs = $pdo->prepare("SELECT * FROM vitrine_config WHERE user_id = ? LIMIT 1");
$cs->execute([$user_id]);
$cfg = $cs->fetch(PDO::FETCH_ASSOC) ?: [];
$businessProfileForVitrine = uplar_business_profile_read($pdo, $user_id);
$businessPresetForVitrine = uplar_business_profile_get($businessProfileForVitrine['segmento']);
$experienceMode = uplar_vitrine_experience_mode($cfg['experiencia_json'] ?? null);
$experiencePrefs = uplar_vitrine_experience_preferences($cfg['experiencia_json'] ?? null);
// Somente para formulário administrativo ainda não salvo: o modo sugerido
// não altera a vitrine pública nem publica uma configuração incompleta.
if (empty($cfg['experiencia_json'])) {
    $suggestedMode = $businessProfileForVitrine['modo_pendente'] ?? null;
    if (in_array($suggestedMode, ['padrao', 'loja', 'cardapio'], true)) {
        $experienceMode = $suggestedMode;
        $experiencePrefs['modo'] = $suggestedMode;
    }
    if (!empty($businessProfileForVitrine['atendimento_confirmado'])) {
        $pendingService = $businessProfileForVitrine['preferencias_atendimento_pendentes'] ?? [];
        if (is_array($pendingService)) {
            $experiencePrefs['entrega_habilitada'] = !empty($pendingService['entrega']);
            $experiencePrefs['retirada_habilitada'] = !empty($pendingService['retirada']);
        }
    }
}

$nome_loja   = ($cfg['nome_loja']   ?? '') ?: $username;
$descricao   = $cfg['descricao']   ?? '';
$cor         = preg_match('/^#[0-9a-fA-F]{6}$/', $cfg['cor_primaria'] ?? '') ? $cfg['cor_primaria'] : '#3b6ff5';
$whatsapp    = ($cfg['whatsapp']   ?? '') ?: ($udata['whatsapp_number'] ?? '');
$hor_ret     = $cfg['horario_ret'] ?? 'Aberto das 10:00 às 18:00';
$hor_ent     = $cfg['horario_ent'] ?? 'Aberto das 12:00 às 18:00';
$endereco    = $cfg['endereco']    ?? '';
$cidade_uf   = $cfg['cidade_uf']   ?? '';
$mostrar_est = (int)($cfg['mostrar_estoque'] ?? 0);
$valor_frete = (float)($cfg['valor_frete']   ?? 0);
$banner_path = $cfg['banner_path'] ?? null;
$logo_path   = $cfg['logo_path'] ?? null;
$iniciais_loja = mb_strtoupper(mb_substr($nome_loja, 0, 2));

$vitrineResumoStmt = $pdo->prepare("SELECT COUNT(*) AS total,
    SUM(CASE WHEN vitrine_exposto = 1 THEN 1 ELSE 0 END) AS expostos,
    SUM(CASE WHEN vitrine_destaque = 'featured' THEN 1 ELSE 0 END) AS destaques,
    SUM(CASE WHEN vitrine_destaque = 'promotion' THEN 1 ELSE 0 END) AS promocoes
    FROM produtos WHERE user_id = ?");
$vitrineResumoStmt->execute([$user_id]);
$vitrineResumo = $vitrineResumoStmt->fetch(PDO::FETCH_ASSOC) ?: [];

// Produtos usados somente na simulação visual da vitrine dentro do editor.
// Não altera exposição, estoque, pedido ou qualquer regra da vitrine.
$previewVitrineProducts = [];
try {
    $previewStmt = $pdo->prepare("SELECT id, nome, preco, quantidade, imagem, vitrine_destaque
        FROM produtos
        WHERE user_id = ? AND vitrine_exposto = 1
        ORDER BY
            CASE vitrine_destaque WHEN 'promotion' THEN 0 WHEN 'featured' THEN 1 ELSE 2 END,
            vitrine_destaque_ordem ASC,
            nome ASC
        LIMIT 4");
    $previewStmt->execute([$user_id]);
    $previewVitrineProducts = $previewStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

// URL pública limpa, independente do domínio usado no painel de estoque.
$base_url_publica = 'https://uplar.cloud/';
$url_publica = static fn(string $s): string => $base_url_publica . ($s ?: 'configure-o-endereco');

// Contador de pedidos pendentes (badge no menu)
$pend_count = 0;
try {
    $pc = $pdo->prepare("SELECT COUNT(*) FROM vitrine_pedidos WHERE user_id = ? AND status = 'pendente'");
    $pc->execute([$user_id]);
    $pend_count = (int)$pc->fetchColumn();
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Vitrine — Uplar</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
<style>
:root{
    --p:#3b6ff5;--p-dark:#2a55d9;--p-dim:rgba(59,111,245,.08);--p-glow:rgba(59,111,245,.20);
    --ink:#0f1c2e;--muted:#5a6a7a;--faint:#94a3b8;--bg:#f4f6fb;--card:#fff;--border:#e4eaf2;
    --ok:#10b981;--ok-dim:rgba(16,185,129,.09);--warn:#f59e0b;--warn-dim:rgba(245,158,11,.09);
    --err:#ef4444;--err-dim:rgba(239,68,68,.09);--violet:#7c3aed;--violet-dim:rgba(124,58,237,.08);
    --sh:0 1px 4px rgba(15,28,46,.06),0 4px 16px rgba(15,28,46,.04);
    --sh-lift:0 4px 24px rgba(59,111,245,.14);--r:12px;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--ink);line-height:1.55;-webkit-font-smoothing:antialiased}
a{color:inherit;text-decoration:none} button{font-family:inherit;cursor:pointer}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes toastOut{to{opacity:0;transform:translateX(20px)}}
.anim{opacity:0;animation:fadeUp .45s cubic-bezier(.22,1,.36,1) forwards}
.d1{animation-delay:.04s}.d2{animation-delay:.10s}.d3{animation-delay:.16s}

/* ── App layout ── */
.app{display:flex;min-height:100vh}

/* ── Sidebar ── */
.sb{width:248px;background:var(--card);border-right:1px solid var(--border);position:fixed;left:0;top:0;height:100vh;overflow-y:auto;z-index:200;display:flex;flex-direction:column;transition:transform .3s}
.sb-head{padding:1.4rem 1.3rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.sb-logo-icon{font-size:2rem;color:var(--p)}
.sb-logo-txt{font-size:1.25rem;font-weight:700;letter-spacing:-.3px}
.sb-user{padding:1rem 1.3rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.8rem}
.sb-av{width:36px;height:36px;border-radius:9px;flex-shrink:0;background:linear-gradient(135deg,var(--p),#7ca0ff);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;box-shadow:0 3px 10px var(--p-glow)}
.sb-uname{font-weight:600;font-size:.85rem}
.sb-urole{font-size:.7rem;color:var(--faint);margin-top:1px}
.sb-nav{padding:.9rem .7rem;flex:1}
.sb-lbl{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--faint);padding:0 .7rem;margin:.9rem 0 .4rem}
.sb-link{display:flex;align-items:center;gap:.7rem;padding:.65rem .85rem;border-radius:9px;color:var(--muted);font-weight:500;font-size:.845rem;transition:all .18s;margin-bottom:2px}
.sb-link:hover{background:var(--p-dim);color:var(--p)}
.sb-link.on{background:var(--p-dim);color:var(--p);font-weight:600}
.sb-link .material-symbols-outlined{font-size:1.15rem;width:20px;text-align:center}
.sb-badge{background:var(--err);color:#fff;font-size:.62rem;font-weight:700;padding:.1rem .38rem;border-radius:20px;margin-left:auto}
.overlay{display:none;position:fixed;inset:0;background:rgba(15,28,46,.4);z-index:199;backdrop-filter:blur(3px)}
.overlay.on{display:block}
.ham{display:none;background:var(--card);border:1px solid var(--border);border-radius:9px;padding:.42rem;align-items:center;justify-content:center;box-shadow:var(--sh)}
.ham .material-symbols-outlined{font-size:1.3rem}

/* ── Main ── */
.main{margin-left:248px;flex:1;padding:1.75rem 2rem;min-width:0}

/* ── Top ── */
.top{display:flex;align-items:center;justify-content:space-between;margin-bottom:1.4rem;gap:1rem;flex-wrap:wrap}
.top-l{display:flex;align-items:center;gap:.8rem}
.greeting{font-size:1.35rem;font-weight:800;letter-spacing:-.4px}
.sub-txt{color:var(--muted);font-size:.8rem;margin-top:1px}

/* ── Tabs ── */
.tabs{display:flex;gap:0;background:var(--card);border:1px solid var(--border);border-radius:var(--r);margin-bottom:1.3rem;overflow:hidden}
.tab{flex:1;padding:.7rem 1rem;font-size:.83rem;font-weight:600;color:var(--muted);background:none;border:none;cursor:pointer;transition:all .18s;display:flex;align-items:center;justify-content:center;gap:.4rem;border-bottom:2.5px solid transparent;position:relative}
.tab .material-symbols-outlined{font-size:1rem}
.tab:hover{color:var(--p);background:var(--p-dim)}
.tab.on{color:var(--p);border-bottom-color:var(--p);background:var(--p-dim)}

/* ── Tab panels ── */
.tab-panel{display:none}
.tab-panel.on{display:block}

/* ── Card ── */
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:1.2rem 1.3rem;margin-bottom:1rem}
.card-title{font-size:.88rem;font-weight:700;margin-bottom:1rem;padding-bottom:.7rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.4rem}
.card-title .material-symbols-outlined{font-size:1rem;color:var(--p)}

/* ── Preview card ── */
.vitrine-preview{border:1.5px solid var(--border);border-radius:14px;overflow:hidden;transition:box-shadow .2s}
.vitrine-preview:hover{box-shadow:var(--sh-lift)}
.vp-banner{height:80px;position:relative;overflow:hidden;transition:background .3s}
.vp-banner img{width:100%;height:100%;object-fit:cover;object-position:center center;transform-origin:center center}
.vp-logo{width:38px;height:38px;border-radius:9px;background:rgba(255,255,255,.2);border:2px solid rgba(255,255,255,.4);color:#fff;font-weight:800;font-size:.82rem;display:flex;align-items:center;justify-content:center;position:absolute;bottom:.6rem;left:.8rem}
.vp-logo img{width:100%;height:100%;object-fit:cover;object-position:center center;transform-origin:center center}
.media-grid{display:grid;grid-template-columns:1fr 1fr;gap:.9rem}
.media-box{padding:1rem;border:1px solid var(--border);border-radius:14px;background:linear-gradient(180deg,#fff, #f8fbff);box-shadow:0 1px 4px rgba(15,28,46,.04)}
.media-box-head{display:flex;align-items:center;justify-content:space-between;gap:.6rem;margin-bottom:.75rem}
.media-box-title{display:flex;align-items:center;gap:.45rem;font-size:.82rem;font-weight:700;color:var(--ink)}
.media-box-title .material-symbols-outlined{font-size:1rem;color:var(--p)}
.media-chip{padding:.22rem .5rem;border-radius:999px;background:var(--p-dim);color:var(--p);font-size:.67rem;font-weight:700}
.media-thumb{height:132px;border-radius:12px;border:1px solid var(--border);background:linear-gradient(180deg,#eef3fb,#f7f9fc);overflow:hidden;display:flex;align-items:center;justify-content:center;color:var(--faint);margin-bottom:.8rem;position:relative}
.media-thumb img{width:100%;height:100%;object-fit:cover;object-position:center center;transform-origin:center center}
.media-thumb.logo{height:132px}
.media-empty{display:flex;flex-direction:column;align-items:center;gap:.35rem;font-size:.74rem;text-align:center;padding:1rem}
.media-empty .material-symbols-outlined{font-size:1.5rem}
.media-file-native{display:none}
.media-note{font-size:.69rem;color:var(--faint);line-height:1.45}
.vp-body{padding:.8rem;border-top:1px solid var(--border)}
.vp-name{font-size:.88rem;font-weight:700;margin-bottom:.2rem}.vp-description{min-height:1.35rem;margin:.35rem 0 .45rem;color:var(--muted);font-size:.7rem;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.vp-url{font-size:.68rem;color:var(--p);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:flex;align-items:center;gap:.2rem}
.vp-url .material-symbols-outlined{font-size:.8rem;flex-shrink:0}
.vp-actions{display:flex;gap:.5rem;margin-top:.7rem}
.vp-btn{flex:1;padding:.45rem .5rem;border-radius:8px;border:none;font-size:.72rem;font-weight:600;display:flex;align-items:center;justify-content:center;gap:.25rem;transition:all .18s;cursor:pointer}
.vp-btn .material-symbols-outlined{font-size:.85rem}
.vp-open{background:var(--p);color:#fff;box-shadow:0 3px 10px var(--p-glow)}.vp-open:hover{background:var(--p-dark)}
.vp-copy{background:var(--p-dim);color:var(--p)}.vp-copy:hover{background:rgba(59,111,245,.16)}

/* ── Status pill ── */
.status-pill{display:inline-flex;align-items:center;gap:.35rem;padding:.28rem .65rem;border-radius:20px;font-size:.7rem;font-weight:700}
.status-pill.on{background:var(--ok-dim);color:var(--ok)}.status-pill.off{background:var(--err-dim);color:var(--err)}
.status-pill .material-symbols-outlined{font-size:.82rem}

/* ── Form ── */
.fg{display:grid;grid-template-columns:1fr 1fr;gap:.9rem}
.fw{grid-column:1/-1}
.settings-section-label{display:flex;align-items:center;gap:.65rem;margin-top:.35rem;padding:.72rem .8rem;border:1px solid var(--border);border-radius:10px;background:var(--bg)}.settings-section-label:first-child{margin-top:0}.settings-section-label>.material-symbols-outlined{width:30px;height:30px;display:grid;place-items:center;flex:0 0 30px;border-radius:8px;background:var(--p-dim);color:var(--p);font-size:1rem}.settings-section-label strong{display:block;font-size:.76rem}.settings-section-label small{display:block;margin-top:.08rem;color:var(--muted);font-size:.66rem;line-height:1.35}
.fl{display:flex;flex-direction:column;gap:.3rem}
.fl label{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
.fl input,.fl textarea,.fl select{padding:.65rem .9rem;border:1.5px solid var(--border);border-radius:9px;font-size:.88rem;font-family:inherit;outline:none;transition:border-color .2s,box-shadow .2s;color:var(--ink);background:var(--card)}
.fl input:focus,.fl textarea:focus,.fl select:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.fl textarea{min-height:70px;resize:vertical}
.fl .hint{font-size:.69rem;color:var(--faint)}
.fl input[type=number]{-moz-appearance:textfield}
.fl input[type=number]::-webkit-outer-spin-button,.fl input[type=number]::-webkit-inner-spin-button{-webkit-appearance:none}

/* ── Toggle ── */
.toggle-row{display:flex;align-items:center;justify-content:space-between;gap:.8rem;padding:.75rem;background:var(--bg);border-radius:10px;margin-bottom:.7rem}
.toggle-row-l{font-size:.83rem;font-weight:600}
.toggle-row-s{font-size:.72rem;color:var(--muted);margin-top:.1rem}
.toggle{position:relative;width:42px;height:24px;flex-shrink:0}
.toggle input{opacity:0;width:0;height:0}
.toggle-track{position:absolute;inset:0;background:var(--border);border-radius:12px;cursor:pointer;transition:background .25s}
.toggle input:checked+.toggle-track{background:var(--p)}
.toggle-track::before{content:'';position:absolute;width:18px;height:18px;border-radius:9px;background:#fff;top:3px;left:3px;transition:transform .25s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.toggle input:checked+.toggle-track::before{transform:translateX(18px)}

/* ── Color picker ── */
.color-section{margin-bottom:1.1rem}
.color-lbl{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);margin-bottom:.55rem;display:block}
.color-row{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap}
.color-inp{width:38px;height:38px;border:2px solid var(--border);border-radius:9px;cursor:pointer;padding:2px;background:var(--card);flex-shrink:0}
.swatch{width:38px;height:38px;border-radius:9px;cursor:pointer;border:2.5px solid transparent;transition:transform .18s,border-color .18s,box-shadow .18s;flex-shrink:0}
.swatch:hover{transform:scale(1.1);box-shadow:0 2px 8px rgba(0,0,0,.18)}
.swatch.on{transform:scale(1.1);border-color:#fff;box-shadow:0 0 0 2.5px var(--ink)}
.color-sep{width:1px;height:30px;background:var(--border);flex-shrink:0}

/* ── Produtos (aba) ── */
.prod-toolbar{display:flex;align-items:center;gap:.65rem;margin-bottom:.9rem;flex-wrap:wrap}
.prod-search{flex:1;min-width:200px;padding:.6rem .9rem;border:1.5px solid var(--border);border-radius:9px;font-size:.85rem;font-family:inherit;outline:none;transition:border-color .2s}
.prod-search:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.btn-sm{padding:.5rem .9rem;border-radius:8px;font-size:.75rem;font-weight:700;border:none;display:inline-flex;align-items:center;gap:.3rem;transition:all .18s;cursor:pointer}
.btn-sm .material-symbols-outlined{font-size:.9rem}
.btn-sm.p{background:var(--p);color:#fff;box-shadow:0 2px 8px var(--p-glow)}.btn-sm.p:hover{background:var(--p-dark)}
.btn-sm.o{background:var(--card);color:var(--muted);border:1.5px solid var(--border)}.btn-sm.o:hover{color:var(--p);border-color:var(--p);background:var(--p-dim)}
.btn-sm.ok{background:var(--ok-dim);color:var(--ok)}.btn-sm.ok:hover{background:rgba(16,185,129,.18)}
.btn-sm.err{background:var(--err-dim);color:var(--err)}.btn-sm.err:hover{background:rgba(239,68,68,.18)}
.prod-stats{font-size:.75rem;color:var(--muted);padding:.5rem .8rem;background:var(--bg);border-radius:8px;border:1px solid var(--border);white-space:nowrap}
.prod-stats strong{color:var(--p)}

/* Grid de produtos */
.prod-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.75rem}
.prod-card{background:var(--card);border:1.5px solid var(--border);border-radius:12px;overflow:hidden;transition:box-shadow .2s,border-color .2s;position:relative;cursor:pointer}
.prod-card:hover{box-shadow:var(--sh-lift)}
.prod-card.exposed{border-color:var(--ok);background:rgba(16,185,129,.03)}
.prod-card.exposed .prod-card-check{background:var(--ok);border-color:var(--ok)}
.prod-card.exposed .prod-card-check .material-symbols-outlined{opacity:1}
.prod-card-img{height:110px;background:var(--bg);display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden}
.prod-card-img img{width:100%;height:100%;object-fit:cover}
.prod-card-img .no-img{font-size:2rem;color:var(--faint)}
.prod-card-check{position:absolute;top:.55rem;right:2.65rem;width:26px;height:26px;border-radius:7px;background:var(--card);border:2px solid var(--border);display:flex;align-items:center;justify-content:center;transition:all .2s;box-shadow:0 1px 4px rgba(0,0,0,.1);cursor:pointer}
.prod-card-check .material-symbols-outlined{font-size:.95rem;color:#fff;opacity:0;transition:opacity .15s}
.prod-card-body{padding:.6rem .75rem .75rem}
.prod-card-name{font-size:.8rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:.18rem}
.prod-card-price{font-size:.78rem;color:var(--p);font-weight:700}
.prod-card-stock{font-size:.67rem;color:var(--faint);margin-top:.1rem}
.prod-card-badge{position:absolute;top:.55rem;left:.55rem;background:var(--ok);color:#fff;font-size:.6rem;font-weight:700;padding:.15rem .4rem;border-radius:6px;line-height:1.3}
.prod-card.out-stock{opacity:.6}
.prod-card.out-stock .prod-card-badge{background:var(--err)}

/* ── Curadoria de produtos ── */
.vitrine-summary{display:flex;align-items:center;gap:.45rem;flex-wrap:wrap;margin:-.65rem 0 1.25rem;padding:.5rem .65rem;background:var(--card);border:1px solid var(--border);border-radius:10px;font-size:.73rem;color:var(--muted)}
.vitrine-summary-item{display:inline-flex;align-items:center;gap:.3rem;padding:.22rem .48rem;border-radius:7px;font-weight:650}.vitrine-summary-item strong{color:var(--ink)}.vitrine-summary-item .material-symbols-outlined{font-size:.9rem}.vitrine-summary-item.online{color:var(--ok);background:var(--ok-dim)}.vitrine-summary-dot{width:3px;height:3px;background:var(--border);border-radius:50%}
.store-anchor-nav{position:sticky;top:.75rem;z-index:5;display:flex;gap:.25rem;overflow-x:auto;margin:-.15rem 0 1rem;padding:.38rem;background:var(--bg);border:1px solid var(--border);border-radius:10px}.store-anchor-nav a{white-space:nowrap;padding:.42rem .58rem;border-radius:7px;font-size:.71rem;font-weight:700;color:var(--muted)}.store-anchor-nav a:hover,.store-anchor-nav a:focus{background:var(--p-dim);color:var(--p);outline:none}
.product-header{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;padding-bottom:1rem;border-bottom:1px solid var(--border);margin-bottom:1rem}.product-header h2{font-size:1rem;letter-spacing:-.2px}.product-header p{font-size:.75rem;color:var(--muted);margin-top:.18rem}.product-header-meta{font-size:.73rem;color:var(--muted);margin-top:.35rem}.product-header-meta strong{color:var(--ink)}
.bulk-menu{position:relative}.bulk-menu summary{list-style:none}.bulk-menu summary::-webkit-details-marker{display:none}.bulk-menu[open] summary{border-color:var(--p);color:var(--p)}.bulk-menu-panel{position:absolute;right:0;top:calc(100% + .4rem);z-index:20;min-width:205px;padding:.35rem;background:var(--card);border:1px solid var(--border);border-radius:10px;box-shadow:var(--sh-lift)}.bulk-menu-panel button{display:flex;width:100%;align-items:center;gap:.45rem;padding:.55rem .6rem;border:0;border-radius:7px;background:transparent;text-align:left;font:inherit;font-size:.74rem;font-weight:650;color:var(--muted)}.bulk-menu-panel button:hover{background:var(--p-dim);color:var(--p)}.bulk-menu-panel .danger:hover{background:var(--err-dim);color:var(--err)}
.product-controls{display:grid;grid-template-columns:minmax(220px,1fr) auto;gap:.65rem;align-items:center;margin-bottom:.8rem}.filter-groups{display:flex;gap:.45rem;flex-wrap:wrap}.filter-group{display:flex;gap:.25rem;align-items:center;padding:.25rem;background:var(--bg);border:1px solid var(--border);border-radius:9px}.filter-chip{border:0;background:transparent;color:var(--muted);padding:.4rem .55rem;border-radius:7px;font:700 .71rem inherit;white-space:nowrap}.filter-chip:hover,.filter-chip:focus{color:var(--p);background:var(--p-dim);outline:none}.filter-chip.on{background:var(--card);box-shadow:0 1px 3px rgba(15,28,46,.09);color:var(--p)}.filter-chip .count{font-size:.64rem;opacity:.75;margin-left:.2rem}
.featured-strip{margin:1rem 0 1.2rem;padding:.85rem;border:1px solid var(--border);border-radius:12px;background:linear-gradient(135deg,#fff,#f8faff)}.featured-strip-head{display:flex;align-items:baseline;justify-content:space-between;margin-bottom:.65rem}.featured-strip-head h3,.catalog-heading{font-size:.8rem;font-weight:800}.featured-strip-head span{font-size:.68rem;color:var(--muted)}.featured-list{display:flex;gap:.55rem;overflow-x:auto;padding-bottom:.12rem}.featured-mini{display:flex;align-items:center;gap:.5rem;min-width:190px;padding:.45rem;border:1px solid var(--border);border-radius:9px;background:var(--card);cursor:grab}.featured-mini.dragging{opacity:.45}.featured-mini img,.featured-mini-placeholder{width:36px;height:36px;border-radius:7px;object-fit:cover;background:var(--bg);display:grid;place-items:center;color:var(--faint);font-size:1rem}.featured-mini-name{font-size:.72rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100px}.featured-mini-type{font-size:.62rem;color:var(--muted)}.featured-mini button{margin-left:auto;border:0;background:var(--bg);color:var(--muted);width:27px;height:27px;border-radius:7px}.featured-mini button:hover{color:var(--err);background:var(--err-dim)}
.selection-bar{position:sticky;bottom:1rem;z-index:15;display:none;align-items:center;justify-content:space-between;gap:.7rem;margin-top:1rem;padding:.65rem .75rem;background:var(--ink);color:#fff;border-radius:11px;box-shadow:0 8px 24px rgba(15,28,46,.22)}.selection-bar.on{display:flex}.selection-actions{display:flex;gap:.4rem;flex-wrap:wrap}.selection-actions button{border:1px solid rgba(255,255,255,.22);background:rgba(255,255,255,.1);color:#fff;padding:.38rem .55rem;border-radius:7px;font:700 .68rem inherit}.selection-actions button:hover{background:rgba(255,255,255,.2)}.selection-clear{background:none!important;border:0!important;color:#fff!important;text-decoration:underline}
.prod-card{cursor:default}.prod-card.selected{outline:3px solid var(--p-glow);border-color:var(--p)}.prod-card.promotion{border-color:#fb923c;box-shadow:0 0 0 1px rgba(239,68,68,.12),0 5px 18px rgba(249,115,22,.12);background:linear-gradient(135deg,#fff,#fff9f5)}.prod-card.featured{border-color:#a78bfa;background:#fcfbff}.prod-card-select,.prod-card-more{position:absolute;z-index:3;width:27px;height:27px;border-radius:7px;border:1px solid var(--border);background:var(--card);color:var(--muted);display:grid;place-items:center;box-shadow:0 1px 4px rgba(0,0,0,.08)}.prod-card-select{right:.55rem;bottom:.55rem}.prod-card-select .material-symbols-outlined{font-size:1rem}.prod-card.selected .prod-card-select{background:var(--p);border-color:var(--p);color:#fff}.prod-card-more{right:.55rem;top:.55rem}.prod-card-more .material-symbols-outlined{font-size:1rem}.prod-card-menu{display:none;position:absolute;z-index:10;right:.55rem;top:2.55rem;width:180px;padding:.3rem;background:var(--card);border:1px solid var(--border);border-radius:9px;box-shadow:var(--sh-lift)}.prod-card-menu.on{display:block}.prod-card-menu button{display:flex;width:100%;padding:.5rem;border:0;border-radius:6px;background:none;text-align:left;font:650 .7rem inherit;color:var(--muted)}.prod-card-menu button:hover{color:var(--p);background:var(--p-dim)}.prod-card-badge.badge-promo{background:linear-gradient(90deg,#ef4444,#f97316)}.prod-card-badge.badge-featured{background:#7c3aed}.prod-card-badge.badge-stock{background:var(--err)}

/* ── Botão salvar ── */
.btn-save{display:inline-flex;align-items:center;gap:.45rem;padding:.7rem 1.35rem;background:var(--p);color:#fff;border:none;border-radius:10px;font-size:.86rem;font-weight:700;font-family:inherit;transition:all .22s;box-shadow:0 4px 14px var(--p-glow);cursor:pointer;white-space:nowrap}
.btn-save:hover{background:var(--p-dark);transform:translateY(-2px)}
.btn-save:disabled{background:var(--faint);box-shadow:none;cursor:not-allowed;transform:none}
.btn-save .material-symbols-outlined{font-size:1rem}

/* ── Spinner ── */
.spinner{width:16px;height:16px;border:2.5px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite;display:inline-block;flex-shrink:0}

/* ── Toast ── */
.tw{position:fixed;bottom:1.2rem;right:1.2rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;pointer-events:none}
.toast{background:var(--ink);color:#fff;padding:.7rem 1.1rem;border-radius:10px;font-size:.82rem;font-weight:500;display:flex;align-items:center;gap:.5rem;box-shadow:0 8px 24px rgba(0,0,0,.2);pointer-events:auto;animation:fadeUp .35s cubic-bezier(.22,1,.36,1) both}
.toast .material-symbols-outlined{font-size:1rem}
.toast.ok{background:var(--ok)}.toast.err{background:var(--err)}

/* ── Composição da configuração da vitrine ── */
.store-config-layout{display:grid;grid-template-columns:minmax(0,1fr) 350px;gap:1rem;align-items:start}.store-preview-card,.store-settings-card{min-width:0}.store-preview-card{grid-column:2;grid-row:1;position:sticky;top:1rem}.store-settings-card{grid-column:1;grid-row:1}.store-preview-content{display:block}.store-preview-side{display:flex;flex-direction:column;min-width:0}.store-status{margin-top:.75rem}.vp-summary{display:none}.vp-banner{height:100px}.vp-catalog{display:flex;gap:.35rem;margin-top:.65rem}.vp-catalog span{height:30px;flex:1;background:var(--p-dim);border-radius:6px}

/* Hierarquia visual do editor da Vitrine. */
.site-overview{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem;padding:.72rem .9rem;border:1px solid var(--border);border-radius:10px;background:var(--card)}
.overview-main{display:flex;align-items:center;gap:.7rem 1rem;min-width:0;flex-wrap:wrap}.state-pill{display:inline-flex;align-items:center;gap:.4rem;padding:.32rem .55rem;border-radius:999px;font-size:.68rem;font-weight:800;white-space:nowrap}.state-pill.published{background:var(--ok-dim);color:#087e53}.state-pill.paused{background:var(--err-dim);color:#b42318}.state-dot{width:7px;height:7px;border-radius:50%;background:currentColor}.overview-item{display:inline-flex;align-items:center;gap:.3rem;min-width:0;color:var(--muted);font-size:.7rem}.overview-item strong{color:var(--ink);font-size:.72rem}.overview-item .material-symbols-outlined{font-size:.9rem;color:var(--faint)}.overview-address{max-width:360px}.overview-address strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tabs{margin-bottom:1rem;border-radius:10px}.tab{min-height:46px;padding:.58rem .82rem;font-size:.78rem;font-weight:700}.store-settings-card{box-shadow:none}.store-settings-card>.card-title{padding-bottom:.8rem;margin-bottom:.9rem}.store-anchor-nav{margin:0 0 1rem;padding:3px;border-radius:10px;background:var(--bg);box-shadow:none}.store-anchor-nav a{padding:.52rem .68rem;font-size:.7rem}.store-anchor-nav a:first-child{background:var(--card);color:var(--p);box-shadow:0 1px 5px rgba(15,28,46,.08)}.settings-section-label{margin-top:1.15rem;padding:.8rem .85rem;background:var(--surface-soft,#f8faff)}.settings-section-label:first-child{margin-top:0}.settings-section-label strong{font-size:.8rem}.settings-section-label small{font-size:.68rem}.store-settings-card .fg{gap:.8rem}.store-preview-card{box-shadow:none}.store-preview-card>.card-title{padding-bottom:.8rem;margin-bottom:.85rem}.store-status{padding:.7rem;border:1px solid var(--border);border-radius:10px;background:var(--surface-soft,#f8faff)}.store-status .toggle-row{background:transparent}.save-config-bar{position:sticky;bottom:.75rem;z-index:40;display:flex;justify-content:flex-end;margin-top:1.1rem;padding:.65rem .75rem;border:1px solid var(--border);border-radius:11px;background:rgba(255,255,255,.94);box-shadow:var(--sh);backdrop-filter:blur(8px)}

/* ── Bottom nav mobile ── */
.bnav{display:none;position:fixed;bottom:0;left:0;right:0;background:var(--card);border-top:1px solid var(--border);z-index:300;padding:.35rem 0 calc(.35rem + env(safe-area-inset-bottom));box-shadow:0 -2px 16px rgba(15,28,46,.07)}
.bnav-inner{display:flex;justify-content:space-around}
.bnav-a{display:flex;flex-direction:column;align-items:center;gap:2px;padding:.35rem .6rem;color:var(--faint);font-size:.62rem;font-weight:600;transition:all .18s;flex:1}
.bnav-a .material-symbols-outlined{font-size:1.25rem}
.bnav-a.on{color:var(--p)}

/* ── Responsive ── */
@media(max-width:860px){.sb{transform:translateX(-100%)}.sb.open{transform:translateX(0)}.main{margin-left:0}.ham{display:flex}}
@media(max-width:1100px){.store-config-layout{grid-template-columns:1fr}.store-preview-card,.store-settings-card{grid-column:auto;grid-row:auto}.store-preview-card{position:static}.store-preview-content{display:block}}
@media(max-width:640px){.bnav{display:block}.main{padding:.9rem .9rem calc(68px + env(safe-area-inset-bottom))}.top{align-items:stretch}.top-l{min-width:0}.top .btn-save{width:100%;justify-content:center}.fg,.media-grid{grid-template-columns:1fr}.tabs{margin-bottom:1rem}.tab{padding:.68rem .6rem;font-size:.76rem}.card{padding:1rem;border-radius:10px}.vp-banner{height:104px}.vp-actions{display:grid;grid-template-columns:1fr 1fr}.prod-grid{grid-template-columns:repeat(auto-fill,minmax(150px,1fr))}.product-header,.product-controls{grid-template-columns:1fr}.product-header{align-items:stretch}.bulk-menu{justify-self:stretch}.bulk-menu summary{justify-content:center;width:100%}.bulk-menu-panel{left:0;right:auto}.selection-bar{align-items:flex-start;flex-direction:column}.vitrine-summary{margin-top:-.3rem}}


/* ══════════════════════════════════════════════════════════
   Editor da Vitrine — navegação real + prévia móvel
   Mantém exclusivamente os recursos já existentes na Vitrine.
   ══════════════════════════════════════════════════════════ */
.store-config-layout{display:grid;grid-template-columns:minmax(0,1fr) 370px;gap:1.2rem;align-items:start}
.store-settings-card{grid-column:1;grid-row:1;min-width:0;padding:0;overflow:hidden;box-shadow:var(--sh)}
.store-preview-card{grid-column:2;grid-row:1;min-width:0;position:sticky;top:1rem;padding:0;overflow:hidden;box-shadow:var(--sh)}
.store-settings-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1.05rem 1.15rem .85rem}
.store-settings-head h2{font-size:.92rem;letter-spacing:-.01em}
.store-settings-head p{margin-top:.15rem;color:var(--muted);font-size:.7rem}
.store-subnav{display:flex;gap:.2rem;overflow-x:auto;padding:0 .75rem .75rem;border-bottom:1px solid var(--border);scrollbar-width:thin}
.store-subtab{flex:0 0 auto;display:inline-flex;align-items:center;justify-content:center;gap:.38rem;min-height:36px;padding:.45rem .65rem;border:0;border-radius:8px;background:transparent;color:var(--muted);font-size:.7rem;font-weight:750;white-space:nowrap;transition:.18s}
.store-subtab .material-symbols-outlined{font-size:.92rem}
.store-subtab:hover{background:var(--p-dim);color:var(--p)}
.store-subtab.on{background:var(--p-dim);color:var(--p);box-shadow:inset 0 0 0 1px rgba(59,111,245,.08)}
.store-subtab:focus-visible{outline:2px solid var(--p);outline-offset:2px}
.store-section{padding:1.15rem}
.store-section[hidden]{display:none!important}
.store-section-heading{display:flex;align-items:flex-start;gap:.7rem;padding-bottom:.85rem;margin-bottom:.9rem;border-bottom:1px solid var(--border)}
.store-section-icon{width:32px;height:32px;display:grid;place-items:center;flex:0 0 32px;border-radius:9px;background:var(--p-dim);color:var(--p)}
.store-section-icon .material-symbols-outlined{font-size:1rem}
.store-section-heading h3{font-size:.88rem;letter-spacing:-.01em}
.store-section-heading p{margin-top:.12rem;color:var(--muted);font-size:.68rem}
.store-section .fg{gap:.8rem}
.store-section .settings-section-label{margin-top:1rem;padding:.72rem .8rem;background:var(--bg)}
.store-section .settings-section-label:first-child{margin-top:0}
.store-section .color-section{margin:0}
.store-section .media-grid{margin-top:.15rem}
.store-section .toggle-row{margin-bottom:0}
.save-config-bar{position:sticky;bottom:0;z-index:20;display:flex;align-items:center;justify-content:space-between;gap:1rem;margin:0;padding:.78rem 1rem;border-top:1px solid var(--border);border-radius:0;background:rgba(255,255,255,.95);box-shadow:0 -8px 22px rgba(15,28,46,.05);backdrop-filter:blur(10px)}
.save-config-copy strong,.save-config-copy span{display:block}.save-config-copy strong{font-size:.73rem}.save-config-copy span{margin-top:.05rem;color:var(--muted);font-size:.64rem}

.store-preview-head{display:flex;align-items:center;justify-content:space-between;gap:.7rem;padding:.85rem 1rem;border-bottom:1px solid var(--border);background:var(--card)}
.store-preview-head strong{display:flex;align-items:center;gap:.4rem;font-size:.78rem}.store-preview-head strong .material-symbols-outlined{font-size:1rem;color:var(--p)}
.preview-live-state{font-size:.63rem;font-weight:800;padding:.22rem .5rem;border-radius:99px;background:var(--ok-dim);color:var(--ok)}
.preview-live-state.off{background:var(--err-dim);color:var(--err)}
.preview-device-stage{display:grid;place-items:center;padding:1rem .75rem 1.1rem;background:linear-gradient(180deg,#f8faff,var(--bg))}
.phone-device{--store-color:var(--p);position:relative;width:min(100%,270px);height:548px;padding:7px;border-radius:32px;background:#111a2a;box-shadow:0 18px 34px rgba(15,28,46,.18),0 3px 8px rgba(15,28,46,.12)}
.phone-screen{position:relative;height:100%;display:flex;flex-direction:column;overflow:hidden;border-radius:25px;background:#fff}
.phone-notch{position:absolute;z-index:8;top:7px;left:50%;width:92px;height:20px;transform:translateX(-50%);border-radius:0 0 11px 11px;background:#111a2a;pointer-events:none}
.phone-browser{position:relative;z-index:6;flex:0 0 40px;display:flex;align-items:flex-end;justify-content:center;padding:0 12px 7px;background:#fff;border-bottom:1px solid rgba(15,28,46,.08);color:#8390a3}
.phone-browser-address{min-width:0;max-width:194px;display:flex;align-items:center;justify-content:center;gap:3px;font-size:.49rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.phone-browser-address .material-symbols-outlined{font-size:.58rem;color:#8190a6}.phone-browser-url{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.phone-content{flex:1;min-height:0;overflow:hidden;background:#f7f8fa;color:#17202b}
.phone-store-banner{height:105px;position:relative;overflow:hidden;background:linear-gradient(135deg,var(--store-color),color-mix(in srgb,var(--store-color) 68%,#111827));border-bottom:3px solid var(--store-color)}
.phone-store-banner>img{width:100%;height:100%;object-fit:cover;display:block}
.phone-store-banner>img[hidden],.phone-logo img[hidden]{display:none!important}
.phone-banner-empty{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.22rem;color:rgba(255,255,255,.72);text-align:center;pointer-events:none}
.phone-banner-empty .material-symbols-outlined{font-size:1rem;opacity:.72}
.phone-banner-empty strong{font-size:.46rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase}
.phone-banner-empty small{max-width:145px;font-size:.40rem;line-height:1.35;color:rgba(255,255,255,.58)}
.phone-store-main{padding:.78rem .8rem 1rem;background:#151515;color:#fff;min-height:calc(100% - 105px)}
.phone-identity{display:flex;align-items:center;gap:.52rem;margin-bottom:.68rem}
.phone-logo{width:32px;height:32px;display:grid;place-items:center;flex:0 0 32px;overflow:hidden;border-radius:50%;background:color-mix(in srgb,var(--store-color) 24%,#1e293b);color:#fff;border:1px solid rgba(255,255,255,.18);font-size:.5rem;font-weight:800;box-shadow:0 1px 5px rgba(0,0,0,.18)}
.phone-logo img{width:100%;height:100%;object-fit:cover}
.phone-store-name{min-width:0;font-size:.72rem;font-weight:850;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.phone-store-description{margin:.16rem 0 .72rem;color:#b8c2cf;font-size:.52rem;line-height:1.42;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.phone-products-title{display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin:.2rem 0 .55rem;color:#aab7c7;font-size:.49rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em}
.phone-products-title span:last-child{font-size:.43rem;font-weight:600;text-transform:none;letter-spacing:0;color:#7f8b99}
.phone-products{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.48rem}
.phone-product{min-width:0;overflow:hidden;border:1px solid rgba(255,255,255,.08);border-radius:8px;background:#1e2939}
.phone-product-img{height:82px;display:grid;place-items:center;overflow:hidden;background:#222d3e;color:#8390a3}
.phone-product-img img{width:100%;height:100%;object-fit:cover}.phone-product-img .material-symbols-outlined{font-size:1rem}
.phone-product-copy{padding:.4rem}.phone-product-name{display:block;color:#f8fafc;font-size:.49rem;font-weight:750;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.phone-product-bottom{display:flex;align-items:center;justify-content:space-between;gap:.25rem;margin-top:.23rem}.phone-product-price{color:var(--store-color);font-size:.48rem;font-weight:800}.phone-product-stock{color:#8f9baa;font-size:.39rem;white-space:nowrap}
.phone-products-empty{grid-column:1/-1;padding:1rem .7rem;border:1px dashed rgba(255,255,255,.12);border-radius:8px;text-align:center;color:#8f9baa;font-size:.5rem;line-height:1.45}
.phone-home{position:absolute;z-index:9;left:50%;bottom:5px;width:82px;height:3px;transform:translateX(-50%);border-radius:99px;background:rgba(255,255,255,.74);mix-blend-mode:difference;pointer-events:none}
.phone-caption{display:flex;align-items:center;justify-content:center;gap:.32rem;margin-top:.7rem;color:var(--faint);font-size:.61rem;font-weight:650}.phone-caption .material-symbols-outlined{font-size:.76rem}
.preview-actions{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;padding:.75rem 1rem 1rem;border-top:1px solid var(--border);background:var(--card)}
.preview-actions .vp-btn{margin:0;min-height:36px}.preview-actions .vp-copy{border:1px solid var(--border)}

@media(max-width:1100px){.store-config-layout{grid-template-columns:1fr}.store-settings-card,.store-preview-card{grid-column:auto;grid-row:auto}.store-preview-card{position:static}.phone-device{width:min(100%,286px);height:570px}}
@media(max-width:640px){.store-settings-head{padding:.9rem .9rem .75rem}.store-subnav{padding:0 .55rem .6rem}.store-section{padding:.9rem}.store-section .fg,.store-section .media-grid{grid-template-columns:1fr}.store-section .fw{grid-column:auto}.save-config-bar{align-items:stretch;flex-direction:column}.save-config-bar .btn-save{width:100%;justify-content:center}.preview-device-stage{padding:.8rem .5rem 1rem}.phone-device{height:548px}}

</style>
<link rel="stylesheet" href="assets/vitrine-image-editor.css?v=20260714">
<link rel="stylesheet" href="assets/app-theme.css?v=20260723">
<script src="assets/app-theme.js?v=20260723"></script>
<link rel="stylesheet" href="assets/vitrine-experience.css?v=20261009.1">
<link rel="stylesheet" href="assets/vitrine-experience-admin.css?v=20261009.1">
<script src="assets/vitrine-experience-admin.js?v=20261009.1"></script>
</head>
<body>
<div class="app">
<div class="overlay" id="ov"></div>

<?php require __DIR__ . '/includes/sidebar.php'; ?>

<main class="main">

    <!-- Top -->
    <div class="top anim d1">
        <div class="top-l">
            <button class="ham" id="ham"><span class="material-symbols-outlined">menu</span></button>
            <div>
                <div class="greeting">️ Minha Vitrine</div>
                <div class="sub-txt">Gerencie sua loja online</div>
            </div>
        </div>
        <!-- Botão ver vitrine com URL limpa -->
        <a class="btn-save" id="btnVerVitrine"
           href="<?= htmlspecialchars($url_publica($slug_atual)) ?>"
           target="_blank" style="text-decoration:none">
            <span class="material-symbols-outlined">open_in_new</span>Ver vitrine
        </a>
    </div>

    <section class="site-overview anim d2" aria-label="Resumo da vitrine">
        <div class="overview-main">
            <span class="state-pill <?= $ativa ? 'published' : 'paused' ?>" id="overviewPublishState"><span class="state-dot"></span><?= $ativa ? 'Publicada' : 'Pausada' ?></span>
            <span class="overview-item"><span class="material-symbols-outlined">storefront</span><span>Canal</span><strong>Vitrine</strong></span>
            <span class="overview-item"><span class="material-symbols-outlined">inventory_2</span><strong><?= (int)($vitrineResumo['expostos'] ?? 0) ?></strong><span>de <?= (int)($vitrineResumo['total'] ?? 0) ?> produtos expostos</span></span>
            <span class="overview-item overview-address"><span class="material-symbols-outlined">link</span><strong><?= htmlspecialchars($url_publica($slug_atual)) ?></strong></span>
        </div>
    </section>

    <!-- Tabs -->
    <div class="tabs anim d2">
        <button class="tab on" data-tab="loja">
            <span class="material-symbols-outlined">tune</span>
            <span class="label">Loja</span>
        </button>
        <button class="tab" data-tab="produtos">
            <span class="material-symbols-outlined">inventory_2</span>
            <span class="label">Produtos</span>
        </button>
    </div>

    <!-- ════════════ ABA: LOJA ════════════ -->
    <div class="tab-panel on anim d3" id="tab-loja">
        <div class="store-config-layout">

            <!-- Configuração: uma seção por vez -->
            <section class="card store-settings-card" aria-label="Configurações da Vitrine">
                <header class="store-settings-head">
                    <div>
                        <h2>Configurações da Loja</h2>
                        <p>Altere somente a área que precisa. As demais ficam fora da tela para manter o editor mais limpo.</p>
                    </div>
                </header>

                <nav class="store-subnav" id="storeSubnav" role="tablist" aria-label="Áreas da configuração da vitrine">
                    <button class="store-subtab on" id="storeTabGeral" type="button" role="tab" aria-selected="true" aria-controls="storeSectionGeral" data-store-section="geral"><span class="material-symbols-outlined">storefront</span>Geral</button>
                    <button class="store-subtab" id="storeTabExperiencia" type="button" role="tab" aria-selected="false" aria-controls="storeSectionExperiencia" data-store-section="experiencia">Experiência</button>
                    <button class="store-subtab" id="storeTabAparencia" type="button" role="tab" aria-selected="false" aria-controls="storeSectionAparencia" data-store-section="aparencia"><span class="material-symbols-outlined">palette</span>Aparência</button>
                    <button class="store-subtab" id="storeTabAtendimento" type="button" role="tab" aria-selected="false" aria-controls="storeSectionAtendimento" data-store-section="atendimento"><span class="material-symbols-outlined">chat</span>Atendimento</button>
                    <button class="store-subtab" id="storeTabRetirada" type="button" role="tab" aria-selected="false" aria-controls="storeSectionRetirada" data-store-section="retirada"><span class="material-symbols-outlined">local_shipping</span>Retirada e entrega</button>
                    <button class="store-subtab" id="storeTabEstoque" type="button" role="tab" aria-selected="false" aria-controls="storeSectionEstoque" data-store-section="estoque"><span class="material-symbols-outlined">inventory_2</span>Estoque</button>
                </nav>

                <section class="store-section" id="storeSectionGeral" role="tabpanel" aria-labelledby="storeTabGeral" data-store-panel="geral">
                    <div class="store-section-heading">
                        <span class="store-section-icon"><span class="material-symbols-outlined">storefront</span></span>
                        <div><h3>Geral</h3><p>Nome, mensagem, endereço público e disponibilidade da sua vitrine.</p></div>
                    </div>
                    <div class="fg">
                        <div class="fl fw">
                            <label for="nomeLoja">Nome da loja *</label>
                            <input id="nomeLoja" value="<?= htmlspecialchars($nome_loja) ?>" placeholder="Minha Loja" oninput="atualizarNomeLoja(this.value)">
                        </div>
                        <div class="fl fw">
                            <label for="descInp">Descrição / slogan</label>
                            <textarea id="descInp" placeholder="Uma frase sobre sua loja…"><?= htmlspecialchars($descricao) ?></textarea>
                        </div>
                        <div class="settings-section-label fw"><span class="material-symbols-outlined">link</span><div><strong>Endereço público</strong><small>O link que você compartilha com os seus clientes.</small></div></div>
                        <div class="fl fw">
                            <label for="slugInp">Endereço da vitrine (slug) *</label>
                            <input id="slugInp" value="<?= htmlspecialchars($slug_atual) ?>" placeholder="minha-loja" oninput="atualizarSlugPreview(this.value)">
                            <span class="hint">Apenas letras, números e hífens. URL: <strong id="slugHint"><?= htmlspecialchars($url_publica($slug_atual)) ?></strong></span>
                        </div>
                        <div class="settings-section-label fw"><span class="material-symbols-outlined">public</span><div><strong>Disponibilidade</strong><small>Controle se a vitrine pode ser acessada pelo endereço público.</small></div></div>
                        <div class="fl fw">
                            <div class="toggle-row">
                                <div><div class="toggle-row-l">Vitrine ativa</div><div class="toggle-row-s">Quando desativada, ninguém acessa a loja</div></div>
                                <label class="toggle"><input type="checkbox" id="togAtiva" <?= $ativa?'checked':'' ?>><span class="toggle-track"></span></label>
                            </div>
                            <div id="statusMsg">
                                <?php if($ativa): ?><span class="status-pill on"><span class="material-symbols-outlined">check_circle</span>Vitrine online</span>
                                <?php else: ?><span class="status-pill off"><span class="material-symbols-outlined">cancel</span>Vitrine offline</span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="store-section" id="storeSectionExperiencia" role="tabpanel" aria-labelledby="storeTabExperiencia" data-store-panel="experiencia" hidden>
                    <div class="store-section-heading"><div><h3>Experiência da vitrine</h3><p>Como seus clientes costumam comprar?</p></div></div>
                    <div style="border:1px solid var(--border);padding:.8rem 1rem;border-radius:10px;margin-bottom:1rem;display:flex;align-items:center;justify-content:space-between;gap:.8rem;flex-wrap:wrap"><div><strong style="font-size:.82rem">Perfil do negócio: <?= htmlspecialchars($businessPresetForVitrine['nome'] ?? 'Não definido', ENT_QUOTES, 'UTF-8') ?></strong><p style="font-size:.72rem;color:var(--muted);margin:.2rem 0 0">O perfil sugere um modo de apresentação, mas não restringe seus produtos.</p></div><a href="perfil_negocio.php" style="font-size:.78rem;font-weight:700;color:var(--p)"> <?= $businessPresetForVitrine ? 'Alterar perfil' : 'Escolher meu segmento' ?> →</a></div>
                    <fieldset class="experience-choices"><legend class="hint">Selecione a apresentação. Tamanhos e complementos estão disponíveis em todos os modos.</legend>
                        <?php foreach ([
                            'loja' => ['Loja / E-commerce', 'Produtos, fotos, tamanhos e variações.'],
                            'cardapio' => ['Cardápio / Alimentação', 'Pratos, bebidas, adicionais e pedidos.'],
                            'padrao' => ['Padrão', 'Catálogo organizado com a apresentação atual.'],
                        ] as $mode => [$label, $hint]): ?>
                        <label class="experience-choice"><input type="radio" name="modo_experiencia" value="<?= $mode ?>" <?= $experienceMode === $mode ? 'checked' : '' ?> onchange="previewExperience(this.value)"><span><strong><?= $label ?></strong><small><?= $hint ?></small></span></label>
                        <?php endforeach; ?>
                    </fieldset>
                    <p class="experience-preview-caption" id="experiencePreviewFeedback" role="status">A prévia ao lado mostra a apresentação selecionada. A mudança será publicada somente ao salvar.</p>
                    <div class="xp-settings">
                        <label class="xp-setting"><input type="checkbox" id="xpFeatured" <?= $experiencePrefs['mostrar_destaques'] ? 'checked' : '' ?>><span><strong>Mostrar destaques e promoções</strong><small>Os produtos continuam no catálogo se esta seção estiver oculta.</small></span></label>
                        <div class="fl"><label for="xpUnavailable">Produtos esgotados</label><select id="xpUnavailable"><option value="mostrar" <?= $experiencePrefs['indisponiveis']==='mostrar'?'selected':'' ?>>Mostrar como esgotados</option><option value="ocultar" <?= $experiencePrefs['indisponiveis']==='ocultar'?'selected':'' ?>>Ocultar do catálogo</option></select></div>
                    </div>
                    <div id="experienceCategories" class="xp-categories"></div>
                </section>

                <section class="store-section" id="storeSectionAparencia" role="tabpanel" aria-labelledby="storeTabAparencia" data-store-panel="aparencia" hidden>
                    <div class="store-section-heading">
                        <span class="store-section-icon"><span class="material-symbols-outlined">palette</span></span>
                        <div><h3>Aparência</h3><p>Cor, banner e foto de perfil usados pela Vitrine comum.</p></div>
                    </div>
                    <div class="fg">
                        <div class="color-section fw">
                            <span class="color-lbl">Cor principal</span>
                            <div class="color-row">
                                <?php $cores3 = ['#3b6ff5','#7c3aed','#10b981']; ?>
                                <?php foreach ($cores3 as $c): ?>
                                <div class="swatch <?= $c===$cor?'on':'' ?>" style="background:<?= $c ?>" title="<?= $c ?>" onclick="pickSwatch('<?= $c ?>')"></div>
                                <?php endforeach; ?>
                                <div class="color-sep"></div>
                                <input type="color" id="corInput" class="color-inp" value="<?= htmlspecialchars($cor) ?>" oninput="updateCor(this.value)" onchange="updateCor(this.value)" title="Escolha qualquer cor">
                            </div>
                        </div>
                        <div class="fl fw">
                            <label>Imagens da vitrine</label>
                            <div class="media-grid">
                                <div class="media-box">
                                    <div class="media-box-head"><div class="media-box-title"><span class="material-symbols-outlined">landscape</span>Banner da vitrine</div><span class="media-chip">3:1</span></div>
                                    <button class="media-thumb media-dropzone" id="bannerPreviewBox" type="button" aria-label="Escolher ou soltar banner da vitrine">
                                        <span class="media-empty" id="bannerPreviewEmpty" <?= $banner_path ? 'hidden' : '' ?>><span class="material-symbols-outlined">image</span><span>Clique ou arraste uma imagem</span></span>
                                        <img id="bannerPreviewImg" src="<?= $banner_path ? htmlspecialchars($banner_path) : '' ?>" alt="Prévia do banner" <?= $banner_path ? '' : 'hidden' ?>>
                                    </button>
                                    <div class="media-actions">
                                        <button class="media-action primary" type="button" data-image-choose="banner"><span class="material-symbols-outlined">upload</span><span data-image-label="banner"><?= $banner_path ? 'Trocar imagem' : 'Escolher imagem' ?></span></button>
                                        <button class="media-action" type="button" data-image-edit="banner" <?= $banner_path ? '' : 'hidden' ?>><span class="material-symbols-outlined">crop</span>Editar enquadramento</button>
                                    </div>
                                    <input id="bannerInp" class="media-file-native" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                    <div class="media-pending" id="bannerPending" hidden><span class="material-symbols-outlined">pending</span>Alteração ainda não salva</div>
                                    <div class="media-note">Utilize uma imagem horizontal. Tamanho recomendado: 1500 × 500 px. JPG, PNG ou WebP até 5 MB.</div>
                                </div>
                                <div class="media-box">
                                    <div class="media-box-head"><div class="media-box-title"><span class="material-symbols-outlined">account_circle</span>Foto de perfil</div><span class="media-chip">1:1</span></div>
                                    <button class="media-thumb media-dropzone logo" id="logoPreviewBox" type="button" aria-label="Escolher ou soltar foto de perfil">
                                        <span class="media-empty" id="logoPreviewEmpty" <?= $logo_path ? 'hidden' : '' ?>><span class="material-symbols-outlined">account_circle</span><span>Clique ou arraste uma imagem</span></span>
                                        <img id="logoPreviewImg" src="<?= $logo_path ? htmlspecialchars($logo_path) : '' ?>" alt="Prévia da foto de perfil" <?= $logo_path ? '' : 'hidden' ?>>
                                    </button>
                                    <div class="profile-circle-row">
                                        <span>Prévia circular na vitrine</span>
                                        <div class="profile-circle-preview"><span class="material-symbols-outlined" id="logoCircleFallback" <?= $logo_path ? 'hidden' : '' ?>>account_circle</span><img id="logoCirclePreviewImg" src="<?= $logo_path ? htmlspecialchars($logo_path) : '' ?>" alt="Prévia circular da foto de perfil" <?= $logo_path ? '' : 'hidden' ?>></div>
                                    </div>
                                    <div class="media-actions">
                                        <button class="media-action primary" type="button" data-image-choose="profile"><span class="material-symbols-outlined">upload</span><span data-image-label="profile"><?= $logo_path ? 'Trocar imagem' : 'Escolher imagem' ?></span></button>
                                        <button class="media-action" type="button" data-image-edit="profile" <?= $logo_path ? '' : 'hidden' ?>><span class="material-symbols-outlined">crop</span>Editar enquadramento</button>
                                    </div>
                                    <input id="logoInp" class="media-file-native" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                    <div class="media-pending" id="logoPending" hidden><span class="material-symbols-outlined">pending</span>Alteração ainda não salva</div>
                                    <div class="media-note">Utilize uma imagem quadrada. Tamanho recomendado: 600 × 600 px. JPG, PNG ou WebP até 3 MB.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="store-section" id="storeSectionAtendimento" role="tabpanel" aria-labelledby="storeTabAtendimento" data-store-panel="atendimento" hidden>
                    <div class="store-section-heading">
                        <span class="store-section-icon"><span class="material-symbols-outlined">chat</span></span>
                        <div><h3>Atendimento</h3><p>Defina o WhatsApp usado para receber os pedidos da Vitrine.</p></div>
                    </div>
                    <div class="fg">
                        <div class="fl fw">
                            <label for="waInp">WhatsApp para pedidos</label>
                            <input id="waInp" value="<?= htmlspecialchars($whatsapp) ?>" placeholder="5592999999999" type="tel">
                            <span class="hint">Use o mesmo número que seus clientes já utilizam para falar com a loja.</span>
                        </div>
                    </div>
                    <label class="xp-setting"><input type="checkbox" id="xpOrders" <?= $experiencePrefs['pedidos_habilitados'] ? 'checked' : '' ?>><span><strong>Receber pedidos pela vitrine</strong><small>Desmarque para pausar pedidos. Os horários abaixo são informativos, sem cálculo automático de abertura.</small></span></label>
                </section>

                <section class="store-section" id="storeSectionRetirada" role="tabpanel" aria-labelledby="storeTabRetirada" data-store-panel="retirada" hidden>
                    <div class="store-section-heading">
                        <span class="store-section-icon"><span class="material-symbols-outlined">local_shipping</span></span>
                        <div><h3>Retirada e entrega</h3><p>Informe localização e horários já usados pela sua Vitrine.</p></div>
                    </div>
                    <div class="xp-settings" style="margin-bottom:18px">
                        <label class="xp-setting"><input type="checkbox" id="xpPickup" <?= $experiencePrefs['retirada_habilitada'] ? 'checked' : '' ?>><span><strong>Permitir retirada</strong><small>Usa o endereço e o horário de retirada cadastrados.</small></span></label>
                        <label class="xp-setting"><input type="checkbox" id="xpDelivery" <?= $experiencePrefs['entrega_habilitada'] ? 'checked' : '' ?>><span><strong>Permitir entrega</strong><small>Informe a taxa. Área de atendimento deve ser confirmada com a loja.</small></span></label>
                        <div class="fl"><label for="xpFreight">Taxa de entrega (R$)</label><input id="xpFreight" type="number" min="0" max="99999999.99" step="0.01" inputmode="decimal" value="<?= htmlspecialchars(number_format((float)$valor_frete,2,'.','')) ?>"><span class="hint">Zero é válido. A taxa será aplicada apenas aos pedidos de entrega.</span></div>
                    </div>
                    <div class="fg">
                        <div class="fl"><label for="endInp">Endereço (retirada)</label><input id="endInp" value="<?= htmlspecialchars($endereco) ?>" placeholder="Rua X, 123"></div>
                        <div class="fl"><label for="cidUfInp">Cidade / UF</label><input id="cidUfInp" value="<?= htmlspecialchars($cidade_uf) ?>" placeholder="Manaus/AM"></div>
                        <div class="fl"><label for="horRetInp">Horário — Retirada</label><input id="horRetInp" value="<?= htmlspecialchars($hor_ret) ?>" placeholder="Aberto das 10:00 às 18:00"></div>
                        <div class="fl"><label for="horEntInp">Horário — Entrega</label><input id="horEntInp" value="<?= htmlspecialchars($hor_ent) ?>" placeholder="Aberto das 12:00 às 18:00"></div>
                    </div>
                </section>

                <section class="store-section" id="storeSectionEstoque" role="tabpanel" aria-labelledby="storeTabEstoque" data-store-panel="estoque" hidden>
                    <div class="store-section-heading">
                        <span class="store-section-icon"><span class="material-symbols-outlined">inventory_2</span></span>
                        <div><h3>Estoque e catálogo</h3><p>Controle somente a informação de estoque exibida aos clientes. A exposição dos produtos continua na aba Produtos.</p></div>
                    </div>
                    <div class="fg">
                        <div class="fl fw">
                            <div class="toggle-row">
                                <div><div class="toggle-row-l">Mostrar quantidade em estoque</div><div class="toggle-row-s">Clientes verão a disponibilidade de cada produto exposto</div></div>
                                <label class="toggle"><input type="checkbox" id="togEst" <?= $mostrar_est?'checked':'' ?>><span class="toggle-track"></span></label>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="save-config-bar">
                    <div class="save-config-copy"><strong>Alterou alguma configuração?</strong><span>Salve para atualizar a Vitrine pública.</span></div>
                    <button class="btn-save" id="btnSalvar" onclick="salvar()"><span class="material-symbols-outlined">save</span>Salvar configurações</button>
                </div>
            </section>

            <!-- Simulação móvel da Vitrine comum -->
            <aside class="card store-preview-card" aria-label="Pré-visualização da Vitrine">
                <div class="store-preview-head">
                    <strong><span class="material-symbols-outlined">smartphone</span>Pré-visualização</strong>
                    <span class="preview-live-state <?= $ativa ? '' : 'off' ?>" id="previewLiveState"><?= $ativa ? 'Publicada' : 'Pausada' ?></span>
                </div>
                <div class="preview-device-stage">
                    <div class="phone-device" id="phonePreview" data-experience="<?= htmlspecialchars($experienceMode) ?>" style="--store-color:<?= htmlspecialchars($cor) ?>" aria-label="Simulação da Vitrine em um celular">
                        <span class="phone-notch" aria-hidden="true"></span>
                        <div class="phone-screen">
                            <div class="phone-browser" aria-hidden="true">
                                <span class="phone-browser-address"><span class="material-symbols-outlined">lock</span><span class="phone-browser-url" id="prevUrl"><?= htmlspecialchars(parse_url($url_publica($slug_atual), PHP_URL_HOST) . '/' . ($slug_atual ?: 'configure-o-endereco')) ?></span></span>
                            </div>
                            <div class="phone-content">
                                <div class="phone-store-banner" id="prevBanner">
                                    <img id="prevBannerImg" src="<?= $banner_path ? htmlspecialchars($banner_path) : '' ?>" alt="" <?= $banner_path ? '' : 'hidden' ?>>
                                    <span class="phone-banner-empty" id="phoneBannerEmpty" <?= $banner_path ? 'hidden' : '' ?>>
                                        <span class="material-symbols-outlined">landscape</span>
                                        <strong>Sem banner</strong>
                                        <small>A cor principal da vitrine ocupa este espaço.</small>
                                    </span>
                                </div>
                                <div class="phone-store-main">
                                    <div class="phone-identity">
                                        <div class="phone-logo" id="prevLogoBox">
                                            <img id="prevLogoImg" src="<?= $logo_path ? htmlspecialchars($logo_path) : '' ?>" alt="" <?= $logo_path ? '' : 'hidden' ?>>
                                            <span id="prevLogoTxt" <?= $logo_path ? 'hidden' : '' ?>><?= htmlspecialchars($iniciais_loja) ?></span>
                                        </div>
                                        <div class="phone-store-name" id="prevName"><?= htmlspecialchars($nome_loja) ?></div>
                                    </div>
                                    <div class="phone-store-description" id="prevDescription"><?= htmlspecialchars($descricao ?: 'A descrição da sua loja aparecerá aqui.') ?></div>
                                    <div class="phone-products-title"><span>Produtos</span><span><?= (int)($vitrineResumo['expostos'] ?? 0) ?> expostos</span></div>
                                    <div class="phone-products" id="phoneProductGrid">
                                        <?php if ($previewVitrineProducts): ?>
                                            <?php foreach ($previewVitrineProducts as $produto): ?>
                                                <div class="phone-product">
                                                    <div class="phone-product-img"><?php if (!empty($produto['imagem'])): ?><img src="<?= htmlspecialchars($produto['imagem']) ?>" alt=""><?php else: ?><span class="material-symbols-outlined">image</span><?php endif; ?></div>
                                                    <div class="phone-product-copy">
                                                        <span class="phone-product-name"><?= htmlspecialchars($produto['nome']) ?></span>
                                                        <span class="phone-product-bottom"><span class="phone-product-price">R$ <?= number_format((float)$produto['preco'], 2, ',', '.') ?></span><span class="phone-product-stock" data-phone-stock <?= $mostrar_est ? '' : 'hidden' ?>><?= (int)$produto['quantidade'] ?> un.</span></span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <div class="phone-products-empty">Os produtos expostos na aba Produtos aparecerão aqui.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <span class="phone-home" aria-hidden="true"></span>
                        </div>
                    </div>
                    <div class="phone-caption"><span class="material-symbols-outlined">touch_app</span>Prévia móvel atualizada em tempo real</div>
                </div>
                <div class="preview-actions">
                    <a class="vp-btn vp-open" href="<?= htmlspecialchars($url_publica($slug_atual)) ?>" target="_blank" id="linkOpen" style="text-decoration:none"><span class="material-symbols-outlined">open_in_new</span>Abrir vitrine</a>
                    <button class="vp-btn vp-copy" type="button" onclick="copiarLink()"><span class="material-symbols-outlined">content_copy</span>Copiar link</button>
                </div>
            </aside>
        </div>
    </div>

    <!-- ════════════ ABA: PRODUTOS ════════════ -->
    <div class="tab-panel" id="tab-produtos">
        <div class="card">
            <div class="product-header">
                <div>
                    <h2>Produtos na Vitrine</h2>
                    <p>Escolha o que seus clientes encontrarão na loja.</p>
                    <div class="product-header-meta" id="prodStats">Carregando produtos…</div>
                </div>
                <details class="bulk-menu" id="bulkMenu"><summary class="btn-sm o"><span class="material-symbols-outlined">expand_more</span>Ações em massa</summary><div class="bulk-menu-panel">
                    <button onclick="exposeAll(true)"><span class="material-symbols-outlined">visibility</span>Expor todos</button>
                    <button class="danger" onclick="exposeAll(false)"><span class="material-symbols-outlined">visibility_off</span>Ocultar todos</button>
                    <button class="danger" onclick="removerTodosDestaques()"><span class="material-symbols-outlined">star_outline</span>Remover todos os destaques</button>
                </div></details>
            </div>

            <div class="product-controls">
                <input class="prod-search" id="prodSearch" placeholder="Buscar produto..." oninput="filtrarProdutos()" aria-label="Buscar produto">
                <div class="filter-groups" aria-label="Filtros de produtos">
                    <div class="filter-group" id="visibilityFilters">
                        <button class="filter-chip on" data-filter="todos" onclick="setExposureFilter('todos',this)">Todos</button>
                        <button class="filter-chip" data-filter="expostos" onclick="setExposureFilter('expostos',this)">Expostos</button>
                        <button class="filter-chip" data-filter="ocultos" onclick="setExposureFilter('ocultos',this)">Ocultos</button>
                    </div>
                    <div class="filter-group" id="situationFilters">
                        <button class="filter-chip" data-filter="sem-estoque" onclick="toggleSituationFilter('sem-estoque',this)">Sem estoque <span class="count" id="countOutStock"></span></button>
                        <button class="filter-chip" data-filter="featured" onclick="toggleSituationFilter('featured',this)">★ Destaques <span class="count" id="countFeatured"></span></button>
                        <button class="filter-chip" data-filter="promotion" onclick="toggleSituationFilter('promotion',this)">🔥 Promoções <span class="count" id="countPromotions"></span></button>
                    </div>
                </div>
            </div>

            <section class="featured-strip" id="featuredStrip" hidden><div class="featured-strip-head"><h3>Destaques da vitrine</h3><span>Prioridade no catálogo público</span></div><div class="featured-list" id="featuredList"></div></section>

            <h3 class="catalog-heading">Todos os produtos</h3>

            <div class="prod-grid" id="prodGrid">
                <div style="grid-column:1/-1;text-align:center;padding:2rem;color:var(--faint)">
                    <div class="spinner" style="border-color:rgba(59,111,245,.3);border-top-color:var(--p);width:28px;height:28px;margin:0 auto .7rem"></div>
                    Carregando produtos…
                </div>
            </div>
            <div class="selection-bar" id="selectionBar"><strong id="selectionCount">0 produtos selecionados</strong><div class="selection-actions">
                <button onclick="applySelection('expose')">Expor</button><button onclick="applySelection('hide')">Ocultar</button><button onclick="applySelection('featured')">Destacar</button><button onclick="applySelection('promotion')">Marcar como promoção</button><button onclick="applySelection('none')">Remover destaque</button><button class="selection-clear" onclick="clearSelection()">Limpar</button>
            </div></div>
        </div>
    </div>

</main>
</div><!-- .app -->

<nav class="bnav"><div class="bnav-inner">
    <a href="estoque.php"        class="bnav-a"><span class="material-symbols-outlined">dashboard</span>Início</a>
    <a href="produtos.php"       class="bnav-a"><span class="material-symbols-outlined">inventory</span>Produtos</a>
    <a href="vitrine_config.php" class="bnav-a on"><span class="material-symbols-outlined">storefront</span>Vitrine</a>
    <a href="pedidos.php"        class="bnav-a"><span class="material-symbols-outlined">receipt_long</span>Pedidos</a>
    <a href="config.php"         class="bnav-a"><span class="material-symbols-outlined">settings</span>Config</a>
</div></nav>

<div class="crop-editor-modal" id="cropEditorModal" role="dialog" aria-modal="true" aria-labelledby="cropEditorTitle" aria-hidden="true">
    <div class="crop-editor-dialog">
        <header class="crop-editor-head">
            <div>
                <div class="crop-editor-title" id="cropEditorTitle">Ajustar imagem</div>
                <div class="crop-editor-subtitle" id="cropEditorSubtitle">Arraste a imagem para definir o enquadramento.</div>
            </div>
            <button class="crop-editor-close" id="cropClose" type="button" aria-label="Fechar editor de imagem"><span class="material-symbols-outlined">close</span></button>
        </header>
        <div class="crop-editor-body">
            <div class="crop-editor-stage" id="cropEditorStage">
                <div class="crop-editor-loading" id="cropEditorLoading" role="status" aria-live="polite" hidden><span class="spinner"></span><span data-loading-label>Carregando…</span></div>
            </div>
        </div>
        <div class="crop-editor-controls">
            <div class="crop-editor-tools">
                <button class="crop-tool" id="cropRotateLeft" type="button" aria-label="Girar 90 graus à esquerda" title="Girar à esquerda"><span class="material-symbols-outlined">rotate_left</span></button>
                <button class="crop-tool" id="cropRotateRight" type="button" aria-label="Girar 90 graus à direita" title="Girar à direita"><span class="material-symbols-outlined">rotate_right</span></button>
            </div>
            <label class="crop-zoom" for="cropZoom"><span>Zoom</span><input id="cropZoom" type="range" min="1" max="3" step="0.01" value="1"><output class="crop-zoom-output" id="cropZoomOutput" for="cropZoom">100%</output></label>
        </div>
        <footer class="crop-editor-foot">
            <button class="crop-footer-btn" id="cropCancel" type="button">Cancelar</button>
            <button class="crop-footer-btn" id="cropReset" type="button"><span class="material-symbols-outlined">restart_alt</span>Redefinir</button>
            <button class="crop-footer-btn primary" id="cropApply" type="button"><span class="material-symbols-outlined">crop</span>Aplicar corte</button>
        </footer>
    </div>
</div>

<div class="tw" id="tw"></div>

<script src="assets/cropper.min.js?v=2.1.1"></script>
<script src="assets/vitrine-image-editor.js?v=20260714"></script>
<script>
const CSRF = <?= json_encode(get_csrf_token()) ?>;
const PUBLIC_BASE = <?= json_encode($base_url_publica, JSON_UNESCAPED_SLASHES) ?>;
const $ = id => document.getElementById(id);

// ── URL limpa ──────────────────────────────────────────────
function buildUrl(slug) {
    const s = slug.trim().toLowerCase().replace(/[^a-z0-9-]/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'');
    return `${PUBLIC_BASE}${s||'configure-o-endereco'}`;
}

// ── Toast ──────────────────────────────────────────────────
function toast(msg, type='ok') {
    const w=$('tw'), el=document.createElement('div');
    el.className='toast '+(type==='err'?'err':'ok');
    el.innerHTML=`<span class="material-symbols-outlined">${type==='err'?'error':'check_circle'}</span>${msg}`;
    w.appendChild(el);
    setTimeout(()=>{el.style.animation='toastOut .3s forwards';setTimeout(()=>el.remove(),300)},3200);
}

// ── Sidebar ────────────────────────────────────────────────
const sb=$('sb'), ov=$('ov');
$('ham')?.addEventListener('click',()=>{sb.classList.add('open');ov.classList.add('on');document.body.style.overflow='hidden'});
ov.addEventListener('click',()=>{sb.classList.remove('open');ov.classList.remove('on');document.body.style.overflow=''});

// ── Tabs ───────────────────────────────────────────────────
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('on'));
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('on'));
        tab.classList.add('on');
        $('tab-'+tab.dataset.tab)?.classList.add('on');
        if (tab.dataset.tab === 'produtos' && !produtosCarregados) carregarProdutos();
    });
});

// ── Navegação interna real da Vitrine ──────────────────────
const storeSections = ['geral','experiencia','aparencia','atendimento','retirada','estoque'];
function activateStoreSection(section, persist = true) {
    const target = storeSections.includes(section) ? section : 'geral';
    document.querySelectorAll('[data-store-section]').forEach(button => {
        const active = button.dataset.storeSection === target;
        button.classList.toggle('on', active);
        button.setAttribute('aria-selected', active ? 'true' : 'false');
        button.tabIndex = active ? 0 : -1;
    });
    document.querySelectorAll('[data-store-panel]').forEach(panel => {
        panel.hidden = panel.dataset.storePanel !== target;
    });
    if (persist) {
        try { sessionStorage.setItem('uplar.vitrine.config.section', target); } catch (_) {}
    }
}
document.querySelectorAll('[data-store-section]').forEach(button => {
    button.addEventListener('click', () => activateStoreSection(button.dataset.storeSection));
    button.addEventListener('keydown', event => {
        if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
        event.preventDefault();
        const currentIndex = storeSections.indexOf(button.dataset.storeSection);
        let nextIndex = currentIndex;
        if (event.key === 'ArrowRight') nextIndex = (currentIndex + 1) % storeSections.length;
        if (event.key === 'ArrowLeft') nextIndex = (currentIndex - 1 + storeSections.length) % storeSections.length;
        if (event.key === 'Home') nextIndex = 0;
        if (event.key === 'End') nextIndex = storeSections.length - 1;
        const next = document.querySelector(`[data-store-section="${storeSections[nextIndex]}"]`);
        next?.focus();
        activateStoreSection(storeSections[nextIndex]);
    });
});
try { activateStoreSection(sessionStorage.getItem('uplar.vitrine.config.section') || 'geral', false); }
catch (_) { activateStoreSection('geral', false); }

function syncPhoneStockVisibility() {
    const show = $('togEst')?.checked;
    document.querySelectorAll('[data-phone-stock]').forEach(el => { el.hidden = !show; });
}
function renderPhoneProducts() {
    const grid = $('phoneProductGrid');
    if (!grid || !produtosCarregados) return;
    const products = todosOsProdutos
        .filter(product => product.vitrine_exposto)
        .sort((a,b) => {
            const rank = value => value === 'promotion' ? 0 : value === 'featured' ? 1 : 2;
            return rank(a.vitrine_destaque) - rank(b.vitrine_destaque)
                || Number(a.vitrine_destaque_ordem || 0) - Number(b.vitrine_destaque_ordem || 0)
                || String(a.nome).localeCompare(String(b.nome), 'pt-BR');
        })
        .slice(0,4);
    if (!products.length) {
        grid.innerHTML = '<div class="phone-products-empty">Os produtos expostos na aba Produtos aparecerão aqui.</div>';
        return;
    }
    grid.innerHTML = products.map(product => `
        <div class="phone-product">
            <div class="phone-product-img">${product.imagem ? `<img src="${esc(product.imagem)}" alt="">` : '<span class="material-symbols-outlined">image</span>'}</div>
            <div class="phone-product-copy">
                <span class="phone-product-name">${esc(product.nome)}</span>
                <span class="phone-product-bottom">
                    <span class="phone-product-price">R$ ${fmtBrl(product.preco)}</span>
                    <span class="phone-product-stock" data-phone-stock>${Number(product.quantidade || 0)} un.</span>
                </span>
            </div>
        </div>`).join('');
    syncPhoneStockVisibility();
}

// ── Color picker ──────────────────────────────────────────
function updateCor(c) {
    if (!/^#[0-9a-fA-F]{6}$/.test(c)) return;
    $('prevBanner')?.style.setProperty('background', `linear-gradient(135deg,${c},${c}99)`);
    $('phonePreview')?.style.setProperty('--store-color', c);
    document.querySelectorAll('.swatch').forEach(s => {
        s.classList.toggle('on', s.title?.toLowerCase() === c.toLowerCase());
    });
}
function pickSwatch(c) {
    $('corInput').value = c;
    updateCor(c);
}
updateCor(<?= json_encode($cor) ?>);

function atualizarNomeLoja(valor) {
    $('prevName').textContent = valor;
    const txt = $('prevLogoTxt');
    if (txt && !txt.hidden) txt.textContent = valor.slice(0,2).toUpperCase();
}

$('descInp')?.addEventListener('input', function() {
    const output = $('prevDescription');
    if (output) output.textContent = this.value.trim() || 'A descrição da sua loja aparecerá aqui.';
});

const imageFields = {
    banner: {
        aspectRatio: 3 / 1,
        outputWidth: 1500,
        outputHeight: 500,
        minWidth: 1200,
        minHeight: 400,
        quality: .85,
        maxBytes: 5 * 1024 * 1024,
        maxPixels: 40 * 1000 * 1000,
        uploadField: 'banner',
        defaultName: 'banner-vitrine.webp',
        inputId: 'bannerInp',
        dropzoneId: 'bannerPreviewBox',
        previewIds: ['bannerPreviewImg', 'prevBannerImg'],
        emptyId: 'bannerPreviewEmpty',
        pendingId: 'bannerPending',
        editorHint: 'Arraste a imagem. O recorte final será 1500 × 500 px.',
        initialSrc: <?= json_encode($banner_path ?: '') ?>
    },
    profile: {
        aspectRatio: 1,
        outputWidth: 600,
        outputHeight: 600,
        minWidth: 500,
        minHeight: 500,
        quality: .85,
        maxBytes: 3 * 1024 * 1024,
        maxPixels: 40 * 1000 * 1000,
        uploadField: 'logo',
        defaultName: 'perfil-vitrine.webp',
        inputId: 'logoInp',
        dropzoneId: 'logoPreviewBox',
        previewIds: ['logoPreviewImg', 'prevLogoImg', 'logoCirclePreviewImg'],
        fallbackIds: ['prevLogoTxt', 'logoCircleFallback'],
        emptyId: 'logoPreviewEmpty',
        pendingId: 'logoPending',
        editorHint: 'Arraste a imagem. O recorte final será 600 × 600 px.',
        initialSrc: <?= json_encode($logo_path ?: '') ?>
    }
};

let imageEditor;
try {
    imageEditor = new VitrineImageEditor({
        fields: imageFields,
        modalId: 'cropEditorModal',
        feedback: toast
    });
} catch (error) {
    console.error(error);
    toast('Não foi possível carregar o editor de imagens.', 'err');
}

// ── Slug preview ───────────────────────────────────────────
function atualizarSlugPreview(v) {
    const url = buildUrl(v);
    const shortUrl = url.replace(/^https?:\/\//i, '');
    if ($('prevUrl')) $('prevUrl').textContent = shortUrl;
    if ($('slugHint')) $('slugHint').textContent = url;
    const lo = $('linkOpen');
    if (lo) lo.href = url;
    if ($('btnVerVitrine')) $('btnVerVitrine').href = url;
}

// ── Toggle vitrine ativa ───────────────────────────────────
$('togAtiva').addEventListener('change', function() {
    $('statusMsg').innerHTML = this.checked
        ? `<span class="status-pill on"><span class="material-symbols-outlined">check_circle</span>Vitrine online</span>`
        : `<span class="status-pill off"><span class="material-symbols-outlined">cancel</span>Vitrine offline</span>`;
    const overviewState = $('overviewPublishState');
    if (overviewState) {
        overviewState.className = `state-pill ${this.checked ? 'published' : 'paused'}`;
        overviewState.innerHTML = `<span class="state-dot"></span>${this.checked ? 'Publicada' : 'Pausada'}`;
    }
    const previewState = $('previewLiveState');
    if (previewState) {
        previewState.className = `preview-live-state ${this.checked ? '' : 'off'}`.trim();
        previewState.textContent = this.checked ? 'Publicada' : 'Pausada';
    }
});

$('waInp')?.addEventListener('input', function() {
    const output = $('previewChannel');
    if (!output) return;
    const configured = this.value.trim() !== '';
    output.className = configured ? 'summary-ok' : 'summary-muted';
    output.textContent = configured ? 'WhatsApp configurado para receber pedidos.' : 'Configure o WhatsApp para receber pedidos.';
});

$('togEst')?.addEventListener('change', function() {
    const output = $('previewAvailability');
    if (output) {
        output.className = this.checked ? 'summary-ok' : 'summary-muted';
        output.textContent = this.checked ? 'A quantidade em estoque será mostrada aos clientes.' : 'A quantidade em estoque ficará oculta para os clientes.';
    }
    syncPhoneStockVisibility();
});
syncPhoneStockVisibility();

// ── Copiar link ────────────────────────────────────────────
function copiarLink() {
    const url = buildUrl($('slugInp').value);
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url).then(() => { toast('Link copiado!'); registrarCompartilhamentoOnboarding(); });
    } else {
        const i = document.createElement('input');
        i.value = url; document.body.appendChild(i); i.select();
        document.execCommand('copy'); i.remove();
        toast('Link copiado!');
        registrarCompartilhamentoOnboarding();
    }
}

function registrarCompartilhamentoOnboarding() {
    const fd = new FormData();
    fd.append('action', 'share');
    fd.append('csrf_token', CSRF);
    fetch('onboarding_api.php', {method:'POST', body:fd, credentials:'same-origin'}).catch(() => {});
}

function previewExperience(mode){
    if(!['padrao','loja','cardapio'].includes(mode))return;
    $('phonePreview').dataset.experience=mode;
    $('experiencePreviewFeedback').textContent='Prévia: '+({padrao:'Padrão',loja:'Loja / E-commerce',cardapio:'Cardápio / Alimentação'}[mode])+'. Salve para publicar esta escolha.';
}

// ── Salvar config da loja ──────────────────────────────────
async function salvar() {
    const btn = $('btnSalvar');
    if(btn.disabled)return;
    let experienceOptions;
    try{experienceOptions=experienceAdmin.getPreferences();if(experienceOptions.pedidos_habilitados&&!experienceOptions.entrega_habilitada&&!experienceOptions.retirada_habilitada)throw Error('Habilite entrega ou retirada para receber pedidos.');if(!$('xpFreight').checkValidity())throw Error('Informe uma taxa de entrega válida.');}
    catch(error){activateStoreSection(experienceAdmin.edit?'experiencia':'retirada');toast(error.message,'err');return;}
    const slug = $('slugInp').value.trim().toLowerCase()
        .replace(/[^a-z0-9-]/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'');
    if (!$('nomeLoja').value.trim()) { activateStoreSection('geral'); $('nomeLoja').focus(); toast('Informe o nome da loja','err'); return; }
    if (!slug) { activateStoreSection('geral'); $('slugInp').focus(); toast('Informe o endereço (slug)','err'); return; }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>Salvando…';

    const fd = new FormData();
    fd.append('acao','salvar_config');
    fd.append('experiencia_opcoes',JSON.stringify(experienceOptions));
    fd.append('valor_frete',$('xpFreight').value);
    fd.append('modo_experiencia', document.querySelector('[name="modo_experiencia"]:checked')?.value || 'padrao');
    fd.append('csrf_token', CSRF);
    fd.append('nome_loja', $('nomeLoja').value.trim());
    fd.append('slug', slug);
    fd.append('descricao', $('descInp').value.trim());
    fd.append('whatsapp', $('waInp').value.trim());
    fd.append('horario_ret', $('horRetInp').value.trim());
    fd.append('horario_ent', $('horEntInp').value.trim());
    fd.append('endereco', $('endInp').value.trim());
    fd.append('cidade_uf', $('cidUfInp').value.trim());
    fd.append('cor_primaria', $('corInput').value);
    fd.append('mostrar_estoque', $('togEst').checked ? '1' : '');
    fd.append('vitrine_ativa', $('togAtiva').checked ? '1' : '');
    imageEditor?.appendPendingFiles(fd);

    try {
        const r = await fetch('vitrine_api.php', {method:'POST', body:fd, credentials:'same-origin'});
        const j = await r.json();
        if (j.ok) {
            toast('Vitrine salva com sucesso! ');
            experienceSettingsDirty=false;experienceAdmin.dirty=false;
            atualizarSlugPreview(j.slug || slug);
            $('slugInp').value = j.slug || slug;
            $('btnVerVitrine').href = j.url || buildUrl(j.slug || slug);
            if (j.banner_path) imageEditor?.commit('banner', j.banner_path);
            if (j.logo_path) imageEditor?.commit('profile', j.logo_path);
        } else {
            toast(j.error || 'Erro ao salvar', 'err');
        }
    } catch (error) {
        console.error(error);
        toast('Não foi possível concluir o salvamento.', 'err');
    }

    btn.disabled = false;
    btn.innerHTML = '<span class="material-symbols-outlined">save</span>Salvar configurações';
}

// ══════════════════════════════════════════════════════════
// ABA PRODUTOS
// ══════════════════════════════════════════════════════════
let todosOsProdutos = [];
let produtosCarregados = false;
let exposureFilter = 'todos';
const situationFilters = new Set();
const selectedProducts = new Set();

async function carregarProdutos() {
    const grid = $('prodGrid');
    grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:2rem;color:var(--faint)">
        <div class="spinner" style="border-color:rgba(59,111,245,.3);border-top-color:var(--p);width:28px;height:28px;margin:0 auto .7rem"></div>
        Carregando produtos…</div>`;

    try {
        const r = await fetch(`vitrine_api.php?acao=listar_produtos_admin`, {credentials:'same-origin'});
        const j = await r.json();
        if (!j.ok) { toast('Erro ao carregar produtos','err'); return; }
        todosOsProdutos = j.produtos;
        produtosCarregados = true;
        renderProdutos();
        atualizarStats();
    } catch { toast('Erro de conexão','err'); }
}

function atualizarStats() {
    const total    = todosOsProdutos.length;
    const expostos = todosOsProdutos.filter(p => p.vitrine_exposto).length;
    const featured = todosOsProdutos.filter(p => p.vitrine_destaque === 'featured').length;
    const promotions = todosOsProdutos.filter(p => p.vitrine_destaque === 'promotion').length;
    const outStock = todosOsProdutos.filter(p => p.quantidade === 0).length;
    $('prodStats').innerHTML = `<strong>${expostos} de ${total}</strong> produtos expostos &nbsp;•&nbsp; <strong>${featured}</strong> destaques &nbsp;•&nbsp; <strong>${promotions}</strong> promoções`;
    $('countOutStock').textContent = outStock || '';
    $('countFeatured').textContent = featured || '';
    $('countPromotions').textContent = promotions || '';
    const summaryFeatured = $('summaryFeatured');
    const summaryPromotions = $('summaryPromotions');
    if (summaryFeatured) summaryFeatured.textContent = featured;
    if (summaryPromotions) summaryPromotions.textContent = promotions;
    renderDestaques();
    renderPhoneProducts();
}

function produtosFiltrados() {
    const termo = $('prodSearch').value.trim().toLowerCase();
    return todosOsProdutos.filter(p => {
        if (exposureFilter === 'expostos' && !p.vitrine_exposto) return false;
        if (exposureFilter === 'ocultos' && p.vitrine_exposto) return false;
        if (situationFilters.has('sem-estoque') && p.quantidade !== 0) return false;
        if (situationFilters.has('featured') && p.vitrine_destaque !== 'featured') return false;
        if (situationFilters.has('promotion') && p.vitrine_destaque !== 'promotion') return false;
        return !termo || p.nome.toLowerCase().includes(termo) || (p.codigo || '').toLowerCase().includes(termo);
    });
}

function renderProdutos() {
    const lista = produtosFiltrados();
    const grid = $('prodGrid');
    if (!lista.length) {
        grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:2rem;color:var(--faint)">
            <span class="material-symbols-outlined" style="font-size:2rem;display:block;margin-bottom:.4rem;opacity:.2">inventory_2</span>
            Nenhum produto encontrado.</div>`;
        return;
    }
    grid.innerHTML = lista.map(p => {
        const exp   = p.vitrine_exposto;
        const sem   = p.quantidade === 0;
        const tipo = p.vitrine_destaque || 'none';
        const badge = tipo === 'promotion'
            ? `<span class="prod-card-badge badge-promo">🔥 Promoção</span>`
            : tipo === 'featured'
                ? `<span class="prod-card-badge badge-featured">★ Destaque</span>`
                : sem ? `<span class="prod-card-badge badge-stock">Sem estoque</span>`
                : exp ? `<span class="prod-card-badge">Na vitrine</span>` : '';
        const img   = p.imagem
            ? `<img src="${p.imagem}" alt="${esc(p.nome)}" loading="lazy" onerror="this.parentElement.innerHTML='<span class=\\'material-symbols-outlined no-img\\'>image_not_supported</span>'">`
            : `<span class="material-symbols-outlined no-img">inventory_2</span>`;
        return `<div class="prod-card ${exp?'exposed':''} ${sem?'out-stock':''} ${tipo} ${selectedProducts.has(p.id)?'selected':''}"
                     data-id="${p.id}">
            <div class="prod-card-img">
                ${img}
                ${badge}
                <button class="prod-card-more" onclick="event.stopPropagation();toggleProductMenu(${p.id})" aria-label="Mais ações para ${esc(p.nome)}"><span class="material-symbols-outlined">more_horiz</span></button>
                <div class="prod-card-menu" id="productMenu-${p.id}">
                    <button onclick="setDestaque(${p.id},'featured')">★ Destacar produto</button>
                    <button onclick="setDestaque(${p.id},'promotion')">🔥 Destacar como promoção</button>
                    ${tipo !== 'none' ? `<button onclick="setDestaque(${p.id},'none')">Remover destaque</button>` : ''}
                </div>
            </div>
            <div class="prod-card-body">
                <div class="prod-card-name" title="${esc(p.nome)}">${esc(p.nome)}</div>
                <div class="prod-card-price">R$ ${fmtBrl(p.preco)}</div>
                <div class="prod-card-stock">${p.quantidade > 0 ? p.quantidade+' em estoque' : 'Sem estoque'}</div>
            </div>
            <button class="prod-card-select" onclick="event.stopPropagation();selectProduto(${p.id})" aria-label="Selecionar ${esc(p.nome)}"><span class="material-symbols-outlined">${selectedProducts.has(p.id)?'check':'check_box_outline_blank'}</span></button>
            <button class="prod-card-check" onclick="event.stopPropagation();toggleProduto(${p.id})" aria-label="${exp?'Ocultar':'Expor'} ${esc(p.nome)}"><span class="material-symbols-outlined">check</span></button>
        </div>`;
    }).join('');
}

async function toggleProduto(id) {
    const fd = new FormData();
    fd.append('acao','toggle_produto');
    fd.append('csrf_token',CSRF);
    fd.append('produto_id',id);

    try {
        const r = await fetch('vitrine_api.php',{method:'POST',body:fd,credentials:'same-origin'});
        const j = await r.json();
        if (!j.ok) { toast(j.error||'Erro','err'); return; }

        // Atualiza estado local
        const prod = todosOsProdutos.find(p=>p.id===id);
        if (prod) prod.vitrine_exposto = j.vitrine_exposto;
        renderProdutos();
        atualizarStats();
        toast(j.vitrine_exposto ? 'Produto exposto na vitrine.' : 'Produto ocultado da vitrine.');
    } catch { toast('Erro de conexão','err'); }
}

function filtrarProdutos() { renderProdutos(); }
function setExposureFilter(filter, button) {
    exposureFilter = filter;
    document.querySelectorAll('#visibilityFilters .filter-chip').forEach(el => el.classList.toggle('on', el === button));
    renderProdutos();
}
function toggleSituationFilter(filter, button) {
    situationFilters.has(filter) ? situationFilters.delete(filter) : situationFilters.add(filter);
    button.classList.toggle('on', situationFilters.has(filter));
    renderProdutos();
}
function toggleProductMenu(id) {
    document.querySelectorAll('.prod-card-menu').forEach(menu => menu.classList.toggle('on', menu.id === `productMenu-${id}` && !menu.classList.contains('on')));
}
function selectProduto(id) {
    selectedProducts.has(id) ? selectedProducts.delete(id) : selectedProducts.add(id);
    updateSelectionBar(); renderProdutos();
}
function clearSelection() { selectedProducts.clear(); updateSelectionBar(); renderProdutos(); }
function updateSelectionBar() {
    const count = selectedProducts.size;
    $('selectionBar').classList.toggle('on', count > 0);
    $('selectionCount').textContent = `${count} ${count === 1 ? 'produto selecionado' : 'produtos selecionados'}`;
}
let featuredDrag = null;
function renderDestaques() {
    const destaques = todosOsProdutos.filter(p => p.vitrine_destaque !== 'none').sort((a,b) => {
        const typeA = a.vitrine_destaque === 'promotion' ? 0 : 1, typeB = b.vitrine_destaque === 'promotion' ? 0 : 1;
        return typeA - typeB || a.vitrine_destaque_ordem - b.vitrine_destaque_ordem || a.id - b.id;
    });
    $('featuredStrip').hidden = !destaques.length;
    $('featuredList').innerHTML = destaques.map(p => `<div class="featured-mini" draggable="true" data-id="${p.id}" data-type="${p.vitrine_destaque}" ondragstart="startFeaturedDrag(${p.id},'${p.vitrine_destaque}',this)" ondragover="event.preventDefault()" ondrop="dropFeaturedDrag(${p.id},'${p.vitrine_destaque}')" ondragend="this.classList.remove('dragging')"><span class="material-symbols-outlined" aria-hidden="true">drag_handle</span>${p.imagem ? `<img src="${esc(p.imagem)}" alt="">` : '<span class="featured-mini-placeholder material-symbols-outlined">inventory_2</span>'}<div><div class="featured-mini-name">${esc(p.nome)}</div><div class="featured-mini-type">${p.vitrine_destaque === 'promotion' ? '🔥 Promoção' : '★ Destaque'}</div></div><button onclick="setDestaque(${p.id},'none')" aria-label="Remover destaque"><span class="material-symbols-outlined">close</span></button></div>`).join('');
}
function startFeaturedDrag(id, type, element) { featuredDrag = {id,type}; element.classList.add('dragging'); }
async function dropFeaturedDrag(targetId, targetType) {
    if (!featuredDrag || featuredDrag.id === targetId) return;
    if (featuredDrag.type !== targetType) { toast('Promoções e destaques têm prioridades separadas.','err'); return; }
    const ordered = todosOsProdutos.filter(p => p.vitrine_destaque === targetType).sort((a,b) => a.vitrine_destaque_ordem - b.vitrine_destaque_ordem || a.id-b.id);
    const from = ordered.findIndex(p => p.id === featuredDrag.id), to = ordered.findIndex(p => p.id === targetId);
    if (from < 0 || to < 0) return;
    const [moved] = ordered.splice(from,1); ordered.splice(to,0,moved);
    const ids = ordered.map(p=>p.id);
    try { const fd=new FormData(); fd.append('acao','ordenar_destaques'); fd.append('csrf_token',CSRF); fd.append('tipo',targetType); fd.append('ids',JSON.stringify(ids)); const r=await fetch('vitrine_api.php',{method:'POST',body:fd,credentials:'same-origin'}); const j=await r.json(); if(!j.ok)throw new Error(j.error); ordered.forEach((p,index)=>p.vitrine_destaque_ordem=index+1); renderDestaques(); toast('Prioridade atualizada.'); } catch(e) { toast(e.message||'Não foi possível salvar a ordem.','err'); }
}
async function setDestaque(id, tipo) {
    const fd = new FormData(); fd.append('acao','set_destaque_produto'); fd.append('csrf_token',CSRF); fd.append('produto_id',id); fd.append('tipo',tipo);
    try { const r = await fetch('vitrine_api.php',{method:'POST',body:fd,credentials:'same-origin'}); const j = await r.json(); if (!j.ok) throw new Error(j.error); const p=todosOsProdutos.find(p=>p.id===id); p.vitrine_destaque=j.vitrine_destaque; p.vitrine_destaque_ordem=j.vitrine_destaque_ordem; renderProdutos(); atualizarStats(); toast(tipo==='promotion'?'Produto marcado como promoção.':tipo==='featured'?'Produto destacado.':'Destaque removido.'); } catch(e) { toast(e.message||'Erro ao atualizar destaque.','err'); }
}

async function exposeAll(expor) {
    const ids = todosOsProdutos.map(p=>p.id);
    if (!ids.length) return;
    if (!confirm(expor ? 'Expor todos os produtos na vitrine?' : 'Ocultar todos os produtos da vitrine? Esta ação afeta todo o catálogo.')) return;

    const fd = new FormData();
    fd.append('acao','toggle_produto_bulk');
    fd.append('csrf_token',CSRF);
    fd.append('ids',JSON.stringify(ids));
    fd.append('vitrine_exposto', expor?'1':'0');

    try {
        const r = await fetch('vitrine_api.php',{method:'POST',body:fd,credentials:'same-origin'});
        const j = await r.json();
        if (!j.ok) { toast(j.error||'Erro','err'); return; }
        todosOsProdutos.forEach(p => p.vitrine_exposto = expor);
        renderProdutos();
        atualizarStats();
        toast(expor ? 'Todos os produtos foram expostos.' : 'Todos os produtos foram ocultados.');
    } catch { toast('Erro de conexão','err'); }
}
async function setDestaqueBulk(ids, tipo) {
    const fd = new FormData(); fd.append('acao','set_destaque_bulk'); fd.append('csrf_token',CSRF); fd.append('ids',JSON.stringify(ids)); fd.append('tipo',tipo);
    try { const r=await fetch('vitrine_api.php',{method:'POST',body:fd,credentials:'same-origin'}); const j=await r.json(); if(!j.ok) throw new Error(j.error); todosOsProdutos.forEach(p=>{if(ids.includes(p.id)){p.vitrine_destaque=tipo;p.vitrine_destaque_ordem=tipo==='none'?0:p.id;}}); clearSelection(); atualizarStats(); toast(tipo==='promotion'?`${j.atualizados} produtos marcados como promoção.`:tipo==='featured'?`${j.atualizados} produtos adicionados aos destaques.`:'Destaques removidos.'); } catch(e) { toast(e.message||'Erro de conexão','err'); }
}
function applySelection(action) {
    const ids=[...selectedProducts]; if(!ids.length)return;
    if(action==='expose'||action==='hide') return toggleVisibilityBulk(ids,action==='expose');
    setDestaqueBulk(ids,action);
}
async function toggleVisibilityBulk(ids, expor) {
    const fd=new FormData(); fd.append('acao','toggle_produto_bulk'); fd.append('csrf_token',CSRF); fd.append('ids',JSON.stringify(ids)); fd.append('vitrine_exposto',expor?'1':'0');
    try { const r=await fetch('vitrine_api.php',{method:'POST',body:fd,credentials:'same-origin'}); const j=await r.json(); if(!j.ok) throw new Error(j.error); todosOsProdutos.forEach(p=>{if(ids.includes(p.id))p.vitrine_exposto=expor;}); clearSelection(); atualizarStats(); renderProdutos(); toast(expor?'Produtos expostos.':'Produtos ocultados.'); } catch(e){toast(e.message||'Erro de conexão','err');}
}
function removerTodosDestaques() { const ids=todosOsProdutos.filter(p=>p.vitrine_destaque!=='none').map(p=>p.id); if(ids.length && confirm('Remover todos os destaques e promoções?')) setDestaqueBulk(ids,'none'); }

// Mantém o rótulo "Banner 3:1" somente quando não há imagem na prévia.
const phoneBannerImage = $('prevBannerImg');
const phoneBannerEmpty = $('phoneBannerEmpty');
function syncPhoneBannerEmpty() {
    if (!phoneBannerImage || !phoneBannerEmpty) return;
    phoneBannerEmpty.hidden = !phoneBannerImage.hidden && !!phoneBannerImage.getAttribute('src');
}
if (phoneBannerImage && window.MutationObserver) {
    new MutationObserver(syncPhoneBannerEmpty).observe(phoneBannerImage, {attributes:true, attributeFilter:['src','hidden']});
}
phoneBannerImage?.addEventListener('load', syncPhoneBannerEmpty);
phoneBannerImage?.addEventListener('error', () => {
    phoneBannerImage.hidden = true;
    phoneBannerImage.removeAttribute('src');
    syncPhoneBannerEmpty();
});
syncPhoneBannerEmpty();

const phoneLogoImage = $('prevLogoImg');
const phoneLogoFallback = $('prevLogoTxt');
function syncPhoneLogoFallback() {
    if (!phoneLogoImage || !phoneLogoFallback) return;
    const hasImage = !phoneLogoImage.hidden && !!phoneLogoImage.getAttribute('src');
    phoneLogoFallback.hidden = hasImage;
}
phoneLogoImage?.addEventListener('load', syncPhoneLogoFallback);
phoneLogoImage?.addEventListener('error', () => {
    phoneLogoImage.hidden = true;
    phoneLogoImage.removeAttribute('src');
    syncPhoneLogoFallback();
});
if (phoneLogoImage && window.MutationObserver) {
    new MutationObserver(syncPhoneLogoFallback).observe(phoneLogoImage, {attributes:true, attributeFilter:['src','hidden']});
}
syncPhoneLogoFallback();

// ── Utilitários ────────────────────────────────────────────
function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function fmtBrl(v) { return Number(v).toFixed(2).replace('.',','); }

const experienceAdmin=new VitrineExperienceAdmin($('experienceCategories'),<?= json_encode($experiencePrefs,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>,async()=>{
    if(!produtosCarregados)await carregarProdutos();
    if(!produtosCarregados)throw Error('Não foi possível carregar seus produtos. Tente novamente.');
    return todosOsProdutos;
});
let experienceSettingsDirty=false;
document.querySelector('.store-settings-card')?.addEventListener('input',()=>experienceSettingsDirty=true);
document.querySelector('.store-settings-card')?.addEventListener('change',()=>experienceSettingsDirty=true);
window.addEventListener('beforeunload',event=>{if(experienceSettingsDirty||experienceAdmin.dirty||experienceAdmin.edit){event.preventDefault();event.returnValue='';}});
</script>
</body>
</html>
