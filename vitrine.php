<?php
/**
 * vitrine.php — Vitrine pública da loja
 * URL: vitrine.php?loja=SLUG
 */
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/vitrine_experience.php';
require_once __DIR__ . '/includes/catalog_categories.php';

$pdo  = get_pdo();
$slug = trim($_GET['loja'] ?? '');

if (!$slug) { http_response_code(404); die('Vitrine não encontrada.'); }

$stmt = $pdo->prepare("SELECT id, username, vitrine_ativa, whatsapp_number FROM users WHERE vitrine_slug = ? LIMIT 1");
$stmt->execute([$slug]);
$owner = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$owner || !(int)$owner['vitrine_ativa']) { http_response_code(404); die('Esta vitrine não está disponível.'); }

$cs = $pdo->prepare("SELECT * FROM vitrine_config WHERE user_id = ? LIMIT 1");
$cs->execute([$owner['id']]);
$cfg = $cs->fetch() ?: [];
$experienceMode = uplar_vitrine_experience_mode($cfg['experiencia_json'] ?? null);
$experience = function_exists('uplar_vitrine_experience_preferences')
    ? uplar_vitrine_experience_preferences($cfg['experiencia_json'] ?? null)
    : array_merge(['modo' => $experienceMode, 'categorias' => [], 'mostrar_destaques' => true, 'indisponiveis' => 'mostrar', 'entrega_habilitada' => true, 'retirada_habilitada' => true, 'pedidos_habilitados' => true], uplar_vitrine_experience_data($cfg['experiencia_json'] ?? null));

$experience = uplar_catalogo_public_prefs($pdo, (int)$owner['id'], $experience);
$nome_loja = $cfg['nome_loja']   ?? $owner['username'];
$descricao = $cfg['descricao']   ?? '';
$cor       = preg_match('/^#[0-9a-fA-F]{6}$/', $cfg['cor_primaria'] ?? '') ? $cfg['cor_primaria'] : '#3b6ff5';
$whatsapp  = $cfg['whatsapp']    ?? $owner['whatsapp_number'] ?? '';
$hor_ret   = $cfg['horario_ret'] ?? '';
$hor_ent   = $cfg['horario_ent'] ?? '';
$endereco  = $cfg['endereco']    ?? '';
$cidade_uf = $cfg['cidade_uf']   ?? '';
$banner    = $cfg['banner_path'] ?? null;
$logo      = $cfg['logo_path']   ?? null;
$ini       = mb_strtoupper(mb_substr($nome_loja, 0, 2));

function hexToRgb($hex) {
    $hex = ltrim($hex, '#');
    return implode(',', [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))]);
}
$cor_rgb = hexToRgb($cor);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="<?= htmlspecialchars($cor) ?>">
<title><?= htmlspecialchars($nome_loja) ?></title>
<meta name="description" content="<?= htmlspecialchars($descricao ?: "Catálogo online de $nome_loja") ?>">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
:root {
    --p:      <?= $cor ?>;
    --p-rgb:  <?= $cor_rgb ?>;
    --p-dark: color-mix(in srgb, <?= $cor ?> 85%, #000);
    --p-dim:  rgba(<?= $cor_rgb ?>,.09);
    --p-glow: rgba(<?= $cor_rgb ?>,.25);
    --ink:    #0f1c2e;
    --muted:  #5a6a7a;
    --faint:  #94a3b8;
    --bg:     #f4f6fb;
    --card:   #ffffff;
    --border: #e4eaf2;
    --ok:     #10b981;
    --ok-dim: rgba(16,185,129,.09);
    --err:    #ef4444;
    --err-dim:rgba(239,68,68,.09);
    --warn:   #f59e0b;
    --warn-dim: rgba(245,158,11,.12);
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--ink);-webkit-font-smoothing:antialiased;overscroll-behavior-y:none}
a{color:inherit;text-decoration:none}
button{font-family:inherit;cursor:pointer}
img{display:block;max-width:100%}

@keyframes fadeUp{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
@keyframes popIn {from{opacity:0;transform:scale(.94)}to{opacity:1;transform:scale(1)}}
@keyframes spin  {to{transform:rotate(360deg)}}

@keyframes slideUp {
    from { transform: translateX(-50%) translateY(100%); }
    to   { transform: translateX(-50%) translateY(0);    }
}

/* ══════════════════════════════════════
   APP SHELL
══════════════════════════════════════ */
.app{max-width:480px;margin:0 auto;min-height:100vh;background:var(--card);position:relative;box-shadow:0 0 40px rgba(0,0,0,.12)}

/* ── Top bar fixa ── */
.topbar{
    position:sticky;top:0;z-index:100;
    background:var(--card);border-bottom:1px solid var(--border);
    display:flex;align-items:center;
    padding:.65rem 1rem;gap:.6rem;
    backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);
}
.topbar-logo{
    width:34px;height:34px;border-radius:9px;flex-shrink:0;
    background:linear-gradient(135deg,var(--p),rgba(var(--p-rgb),.7));
    display:flex;align-items:center;justify-content:center;
    font-weight:800;font-size:.85rem;color:#fff;
    box-shadow:0 3px 10px var(--p-glow);overflow:hidden;
}
.topbar-logo img{width:100%;height:100%;object-fit:cover}
.topbar-name{font-size:.95rem;font-weight:700;letter-spacing:-.2px;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cart-btn{
    position:relative;background:var(--p-dim);border:none;
    width:38px;height:38px;border-radius:10px;
    display:flex;align-items:center;justify-content:center;
    color:var(--p);transition:all .18s;flex-shrink:0;
}
.cart-btn:hover,.cart-btn:active{background:rgba(var(--p-rgb),.18)}
.cart-btn svg{width:20px;height:20px}
.cart-badge{
    position:absolute;top:-4px;right:-4px;
    background:var(--err);color:#fff;
    font-size:.6rem;font-weight:800;
    min-width:18px;height:18px;border-radius:9px;
    display:none;align-items:center;justify-content:center;
    padding:0 4px;border:2px solid var(--card);
}
.cart-badge.on{display:flex}

/* ── Banner e identidade ── */
.hero{height:168px;background:linear-gradient(135deg,var(--p),rgba(var(--p-rgb),.6));overflow:hidden;flex-shrink:0}
.hero img{width:100%;height:100%;object-fit:cover;display:block}
.store-identity{padding:1rem 1rem .75rem;background:var(--card);border-bottom:1px solid var(--border)}
.store-title{font-size:1.18rem;font-weight:800;letter-spacing:-.02em;line-height:1.25;color:var(--ink)}
.store-description{font-size:.79rem;line-height:1.5;color:var(--muted);margin:.35rem 0 0;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}

/* ── Info strips ── */
.info-strips{background:var(--card);border-bottom:1px solid var(--border);padding:.7rem 1rem .8rem}
.delivery-info{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.5rem}
.info-strip{display:flex;align-items:flex-start;gap:.45rem;min-width:0;padding:.45rem .35rem}
.info-strip svg{width:17px;height:17px;flex-shrink:0;color:var(--p);margin-top:.08rem}
.info-strip span{min-width:0;font-size:.72rem;line-height:1.25;color:var(--muted)}
.info-strip strong{display:block;color:var(--ink);font-size:.78rem;margin-top:.12rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.info-link{
    display:flex;align-items:center;justify-content:center;gap:.4rem;
    width:100%;padding:.55rem;border-radius:9px;margin-top:.6rem;
    font-size:.78rem;font-weight:700;color:var(--p);background:var(--p-dim);border:none;transition:all .18s;
}
.info-link:active{background:rgba(var(--p-rgb),.18)}
.info-link svg{width:14px;height:14px}

/* ── Busca ── */
.search-wrap{padding:.75rem 1rem;background:var(--card);border-bottom:1px solid var(--border)}
.search-box{
    display:flex;align-items:center;gap:.5rem;
    background:var(--bg);border:1.5px solid var(--border);
    border-radius:10px;padding:.55rem .8rem;transition:border-color .2s,box-shadow .2s;
}
.search-box:focus-within{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.search-box svg{width:16px;height:16px;color:var(--faint);flex-shrink:0}
.search-box input{border:none;outline:none;font-size:.88rem;width:100%;background:transparent;color:var(--ink);font-family:inherit}

/* ── Categorias ── */
.cats{
    display:flex;gap:.3rem;overflow-x:auto;padding:.6rem 1rem;
    background:var(--card);border-bottom:1px solid var(--border);
    scrollbar-width:none;-webkit-overflow-scrolling:touch;
}
.cats::-webkit-scrollbar{display:none}
.cat-btn{
    white-space:nowrap;padding:.38rem .85rem;border-radius:50px;
    font-size:.75rem;font-weight:600;border:1.5px solid var(--border);
    background:var(--card);color:var(--muted);transition:all .18s;flex-shrink:0;
}
.cat-btn.on{background:var(--p);border-color:var(--p);color:#fff}

/* ── Seções ── */
.section-head{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:1rem 1rem .55rem}
.sec-title{padding:0;font-size:.92rem;font-weight:800;color:var(--ink);letter-spacing:-.01em}
.featured-link{border:0;background:none;color:var(--p);font:inherit;font-size:.73rem;font-weight:700;padding:.15rem 0}
.featured-section{padding:0 0 .35rem}
.featured-row{display:flex;gap:.7rem;overflow-x:auto;padding:0 1rem .35rem;scroll-snap-type:x proximity;scrollbar-width:none;-webkit-overflow-scrolling:touch}
.featured-row::-webkit-scrollbar{display:none}

/* ── Produtos ── */
.prod-list{padding:0 1rem 1rem;display:flex;flex-direction:column;gap:.65rem}
.prod-card{
    display:flex;align-items:flex-start;gap:.85rem;
    background:var(--card);border:1px solid var(--border);border-radius:15px;
    padding:.75rem .9rem .75rem .75rem;
    transition:transform .18s,box-shadow .18s;
    animation:fadeUp .35s cubic-bezier(.22,1,.36,1) both;cursor:pointer;
}
.prod-card:active{transform:scale(.98);box-shadow:none}
.prod-card.promotion{border-color:#fb923c;background:linear-gradient(135deg,#fff,#fff9f5);box-shadow:0 0 0 1px rgba(239,68,68,.14),0 5px 16px rgba(249,115,22,.11)}
.prod-card.featured{border-color:#c4b5fd;background:#fdfcff}
.prod-img{width:88px;height:88px;border-radius:11px;flex-shrink:0;background:var(--bg);overflow:hidden;position:relative;display:flex;align-items:center;justify-content:center}
.prod-img img{width:100%;height:100%;object-fit:cover}
.prod-img svg{width:28px;height:28px;fill:var(--border)}
.prod-body{flex:1;min-width:0}
.prod-name{font-size:.9rem;font-weight:700;line-height:1.3;margin-bottom:.2rem;color:var(--ink)}
.prod-desc{font-size:.73rem;color:var(--muted);line-height:1.4;margin-bottom:.4rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.size-chips{display:flex;gap:.25rem;flex-wrap:wrap;margin:.35rem 0 .42rem}.size-chip{padding:.24rem .5rem;border-radius:6px;background:var(--bg);border:1px solid var(--border);font-size:.68rem;font-weight:700;color:var(--muted)}button.size-chip.on{background:var(--p);border-color:var(--p);color:#fff}button.size-chip:disabled{opacity:.42;text-decoration:line-through;cursor:not-allowed}
.prod-price{font-size:1.05rem;font-weight:800;color:var(--p)}
.prod-card.promotion .prod-price{color:#dc2626}.prod-badge{display:inline-flex;align-items:center;gap:.22rem;margin-bottom:.3rem;padding:.15rem .4rem;border-radius:5px;font-size:.59rem;font-weight:800;letter-spacing:.02em}.prod-badge.promotion{background:linear-gradient(90deg,#fee2e2,#ffedd5);color:#c2410c}.prod-badge.featured{background:#f3e8ff;color:#6d28d9}
.prod-add{
    width:32px;height:32px;border-radius:9px;border:none;
    background:var(--p);color:#fff;
    display:flex;align-items:center;justify-content:center;
    flex-shrink:0;align-self:center;
    transition:all .18s;box-shadow:0 3px 10px var(--p-glow);
}
.prod-add:active{transform:scale(.9)}
.prod-add svg{width:18px;height:18px}
.prod-add.added{background:var(--ok);box-shadow:0 3px 10px rgba(16,185,129,.3)}
.featured-card{width:212px;min-width:212px;display:flex;flex-direction:column;gap:0;padding:.58rem;scroll-snap-align:start;cursor:pointer}
.featured-card .prod-img{width:100%;height:132px;border-radius:10px}.featured-card .prod-body{width:100%;padding:.12rem .12rem 0}.featured-card .prod-name{font-size:.86rem}.featured-card .prod-desc{-webkit-line-clamp:1;margin-bottom:.3rem}.featured-card .featured-bottom{display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-top:.2rem}.featured-card .prod-add{align-self:auto;width:31px;height:31px}

/* ── Empty / skeleton ── */
.empty-state{text-align:center;padding:3.5rem 1.5rem;color:var(--faint)}
.empty-state svg{width:48px;height:48px;fill:var(--border);margin:0 auto 1rem;display:block}
.empty-state p{font-size:.88rem}
@keyframes shimmer{0%{background-position:200% 0}100%{background-position:-200% 0}}
.sk{border-radius:8px;background:linear-gradient(90deg,var(--bg) 25%,#e8ecf2 50%,var(--bg) 75%);background-size:200% 100%;animation:shimmer 1.3s infinite}
.sk-card{display:flex;gap:.85rem;background:var(--card);border:1px solid var(--border);border-radius:14px;padding:.75rem}

/* ── Bottom nav ── */
.bnav{
    position:fixed;bottom:0;left:50%;transform:translateX(-50%);
    width:100%;max-width:480px;
    background:var(--card);border-top:1px solid var(--border);
    z-index:100;padding:.35rem 0 calc(.35rem + env(safe-area-inset-bottom));
    box-shadow:0 -4px 20px rgba(0,0,0,.06);
}
.bnav-inner{display:flex;justify-content:space-around}
.bnav-a{
    display:flex;flex-direction:column;align-items:center;gap:2px;
    padding:.35rem .8rem;color:var(--faint);font-size:.62rem;font-weight:600;
    transition:color .18s;flex:1;border:none;background:none;
}
.bnav-a svg{width:22px;height:22px}
.bnav-a.on,.bnav-a:active{color:var(--p)}
.bnav-spacer{height:calc(64px + env(safe-area-inset-bottom))}

/* ── Overlay ── */
.sheet-bg{
    display:none;
    position:fixed;top:0;left:0;right:0;bottom:0;
    width:100%;height:100%;
    background:rgba(15,28,46,.5);
    z-index:200;
    backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);
}
.sheet-bg.on{display:block}

/* ── Sheet ── */
.sheet{
    position:fixed;
    bottom:0;
    left:50%;
    transform:translateX(-50%);
    width:100vw;
    max-width:480px;
    background:var(--card);
    border-radius:20px 20px 0 0;
    z-index:201;
    max-height:92vh;
    display:flex;flex-direction:column;
    animation:slideUp .3s cubic-bezier(.22,1,.36,1) both;
    padding-bottom:env(safe-area-inset-bottom);
    overflow:hidden;
}
.sheet-handle{width:36px;height:4px;background:var(--border);border-radius:2px;margin:.75rem auto .3rem}
.sheet-head{
    display:flex;align-items:center;justify-content:space-between;
    padding:.6rem 1.1rem;border-bottom:1px solid var(--border);flex-shrink:0;
}
.sheet-title{font-size:1rem;font-weight:700}
.sheet-cl{width:30px;height:30px;border-radius:8px;background:var(--bg);border:none;display:flex;align-items:center;justify-content:center;color:var(--muted)}
.sheet-cl svg{width:16px;height:16px}
.sheet-body{overflow-y:auto;-webkit-overflow-scrolling:touch;flex:1;padding:.9rem 1rem;min-height:0}
.sheet-foot{padding:.9rem 1rem;border-top:1px solid var(--border);flex-shrink:0}

/* ── Tipo entrega ── */
.del-tabs{display:flex;gap:4px;background:var(--bg);border-radius:10px;padding:3px;margin-bottom:.9rem}
.del-tab{flex:1;padding:.52rem;border-radius:8px;border:none;background:transparent;font-size:.78rem;font-weight:600;color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.35rem;transition:all .18s}
.del-tab svg{width:15px;height:15px}
.del-tab.on{background:var(--card);color:var(--p);box-shadow:0 1px 6px rgba(0,0,0,.08)}

/* ── Address / eta / contact ── */
.address-info{display:flex;align-items:flex-start;gap:.65rem;background:var(--bg);border-radius:10px;padding:.75rem;margin-bottom:.9rem}
.address-info svg{width:32px;height:32px;flex-shrink:0}
.address-text{font-size:.8rem;line-height:1.4}
.address-text strong{display:block;font-size:.85rem;margin-bottom:.1rem}
.eta-box{margin-bottom:.9rem}
.eta-label{font-size:.72rem;color:var(--faint);margin-bottom:.2rem;text-transform:uppercase;letter-spacing:.04em;font-weight:600}
.eta-val{font-size:1rem;font-weight:700;display:flex;align-items:center;gap:.4rem}
.eta-dot{width:8px;height:8px;border-radius:50%;background:var(--ok);flex-shrink:0}

/* ── Itens do carrinho ── */
.cart-section-title{font-size:.78rem;font-weight:700;color:var(--muted);margin-bottom:.55rem;text-transform:uppercase;letter-spacing:.04em}
.cart-item{display:flex;align-items:center;gap:.75rem;padding:.65rem 0;border-bottom:1px dashed var(--border)}
.cart-item:last-child{border-bottom:none}
.ci-img{width:48px;height:48px;border-radius:9px;background:var(--bg);overflow:hidden;flex-shrink:0;position:relative;display:flex;align-items:center;justify-content:center}
.ci-img img{width:100%;height:100%;object-fit:cover}
.ci-img svg{width:20px;height:20px;fill:var(--border)}
.ci-badge{position:absolute;top:-5px;left:-5px;background:var(--p);color:#fff;font-size:.58rem;font-weight:800;width:18px;height:18px;border-radius:9px;display:flex;align-items:center;justify-content:center;border:2px solid var(--card)}
.ci-body{flex:1;min-width:0}
.ci-name{font-size:.82rem;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ci-price{font-size:.85rem;font-weight:700;color:var(--p);margin-top:.1rem}
.ci-qty{display:inline-flex;align-items:center;gap:.15rem;margin-top:.35rem;padding:.12rem;background:var(--bg);border-radius:7px}.ci-qty button{width:22px;height:22px;border:0;border-radius:5px;background:var(--card);color:var(--ink);font-size:1rem;font-weight:700;line-height:1;box-shadow:0 1px 3px rgba(0,0,0,.08)}.ci-qty span{min-width:18px;text-align:center;font-size:.72rem;font-weight:800;color:var(--ink)}
.ci-del{width:28px;height:28px;border-radius:7px;background:var(--err-dim);color:var(--err);border:none;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ci-del svg{width:14px;height:14px}

/* ── Add mais / totais ── */
.add-more{text-align:center;padding:.65rem;font-size:.78rem;font-weight:700;color:var(--p);border:1.5px dashed rgba(var(--p-rgb),.3);border-radius:10px;margin:.75rem 0;cursor:pointer;background:none;width:100%;transition:all .18s}
.add-more:hover,.add-more:active{background:var(--p-dim)}
.totals{margin:.75rem 0}
.total-row{display:flex;justify-content:space-between;align-items:center;padding:.3rem 0;font-size:.83rem;color:var(--muted)}
.total-row.big{font-size:1rem;font-weight:800;color:var(--ink);padding:.5rem 0;border-top:2px solid var(--border);margin-top:.3rem}
.total-row.big span:last-child{color:var(--p)}

/* ── Botão principal ── */
.btn-main{
    width:100%;background:var(--p);color:#fff;border:none;padding:1rem;
    border-radius:13px;font-size:.98rem;font-weight:700;font-family:inherit;
    display:flex;align-items:center;justify-content:center;gap:.6rem;
    box-shadow:0 4px 16px var(--p-glow);transition:all .22s;cursor:pointer;
}
.btn-main:hover,.btn-main:active{background:var(--p-dark);transform:translateY(-1px)}
.btn-main:disabled{background:var(--faint);box-shadow:none;cursor:not-allowed;transform:none}
.btn-main svg{width:18px;height:18px}
.spinner{width:18px;height:18px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .65s linear infinite;flex-shrink:0}

/* ══════════════════════════════════════
   DETALHE PRODUTO — IMAGEM COM ZOOM
══════════════════════════════════════ */
.prod-sheet{max-height:88vh}
.prod-sheet-content{display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden}
.prod-sheet-scroll{overflow-y:auto;-webkit-overflow-scrolling:touch;flex:1;min-height:0}
.custom-groups{margin-top:1rem}
.custom-group{padding:.9rem 0;border-top:1px solid var(--border)}
.custom-group-head{margin-bottom:.55rem}
.custom-group-title{font-size:.84rem;font-weight:800;color:var(--ink)}
.custom-group-help{font-size:.69rem;color:var(--muted);margin-top:.12rem}
.custom-option{display:flex;align-items:center;gap:.65rem;min-height:46px;padding:.45rem .25rem;border-bottom:1px solid rgba(148,163,184,.18);font-size:.8rem}
.custom-option:last-child{border-bottom:0}
.custom-option input{width:20px;height:20px;accent-color:var(--p);flex-shrink:0}
.custom-option-name{flex:1;min-width:0}
.custom-option-price{font-size:.75rem;font-weight:700;color:var(--p);white-space:nowrap}
.custom-step{display:inline-flex;align-items:center;gap:.55rem}
.custom-step button{width:34px;height:34px;border:1px solid var(--border);border-radius:9px;background:var(--card);font-size:1rem;font-weight:700;color:var(--ink)}
.custom-step button:disabled{opacity:.35}
.custom-step span{min-width:14px;text-align:center;font-size:.78rem;font-weight:800}
.custom-note{width:100%;min-height:74px;margin-top:.4rem;padding:.7rem;border:1px solid var(--border);border-radius:10px;background:var(--card);font:inherit;font-size:.8rem;resize:vertical}
.custom-footer{padding:.7rem 1rem calc(.7rem + env(safe-area-inset-bottom));border-top:1px solid var(--border);background:var(--card);flex-shrink:0}
.custom-footer .qty-row{margin:0 0 .65rem}
.cart-custom{margin-top:.25rem;color:var(--muted);font-size:.68rem;line-height:1.4;overflow-wrap:anywhere}
.cart-review{margin:.25rem 0;padding:.5rem .6rem;border-radius:8px;background:var(--warn-dim);color:#8a5100;font-size:.7rem;line-height:1.4}

/* Container da imagem: altura auto, mostra tudo sem cortar */
.prod-sheet-img{
    width:100%;
    background:var(--bg);
    display:flex;
    align-items:center;
    justify-content:center;
    flex-shrink:0;
    overflow:hidden;
    cursor:zoom-in;
    position:relative;
    max-height:65vh;
}

/* Imagem completa, sem crop */
.prod-sheet-img img{
    width:100%;
    height:auto;
    max-height:65vh;
    object-fit:contain;
    display:block;
    transition:transform .25s ease;
    touch-action:none;
    user-select:none;
    -webkit-user-select:none;
    transform-origin:center center;
}

/* Quando em zoom ativo */
.prod-sheet-img.zoomed{
    cursor:zoom-out;
    max-height:none;
    overflow:visible;
}
.prod-sheet-img.zoomed img{
    max-height:none;
}

.prod-sheet-img svg{width:56px;height:56px;fill:var(--border)}

/* Hint de zoom — aparece brevemente na primeira abertura */
.zoom-hint{
    position:absolute;bottom:.6rem;right:.6rem;
    background:rgba(0,0,0,.45);color:#fff;
    font-size:.65rem;font-weight:600;
    padding:.25rem .5rem;border-radius:6px;
    display:flex;align-items:center;gap:.3rem;
    pointer-events:none;
    opacity:1;
    transition:opacity .4s;
}
.zoom-hint svg{width:12px;height:12px;fill:#fff}
.zoom-hint.hide{opacity:0}

.prod-sheet-body{padding:1rem 1.1rem}
.prod-sheet-top{display:flex;align-items:flex-start;justify-content:space-between;gap:.75rem}.prod-share{width:36px;height:36px;border:1px solid var(--border);border-radius:10px;background:var(--card);color:var(--p);display:flex;align-items:center;justify-content:center;flex-shrink:0}.prod-share:active{background:var(--p-dim)}.prod-share svg{width:18px;height:18px}
.prod-sheet-name{font-size:1.1rem;font-weight:800;margin-bottom:.35rem}
.prod-sheet-price{font-size:1.35rem;font-weight:800;color:var(--p);margin-bottom:.7rem}
.prod-sheet-desc{font-size:.83rem;color:var(--muted);line-height:1.55}
.prod-availability{font-size:.73rem;font-weight:700;color:var(--ok);margin-top:.65rem}
.qty-row{display:flex;align-items:center;justify-content:space-between;margin-top:1rem}
.qty-label{font-size:.8rem;font-weight:600;color:var(--muted)}
.qty-step{display:flex;align-items:center;gap:.5rem}
.qs-btn{width:34px;height:34px;border-radius:9px;border:1.5px solid var(--border);background:var(--card);color:var(--ink);font-size:1.1rem;font-weight:700;display:flex;align-items:center;justify-content:center;transition:all .18s}
.qs-btn:active{background:var(--p-dim);border-color:var(--p)}
.qs-val{font-size:1rem;font-weight:700;min-width:26px;text-align:center}

/* ── Checkout ── */
.form-group{margin-bottom:.9rem}
.form-group label{display:block;font-size:.72rem;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;margin-bottom:.35rem}
.form-group input,.form-group textarea{width:100%;padding:.75rem .9rem;border:1.5px solid var(--border);border-radius:10px;font-size:.9rem;font-family:inherit;color:var(--ink);background:var(--card);outline:none;transition:border-color .2s,box-shadow .2s}
.form-group input:focus,.form-group textarea:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.form-group textarea{resize:none;min-height:70px}
.success-state{text-align:center;padding:1.5rem 1rem}
.success-ico{width:64px;height:64px;border-radius:50%;background:var(--ok-dim);display:flex;align-items:center;justify-content:center;margin:0 auto 1rem}
.success-ico svg{width:32px;height:32px;fill:var(--ok)}
.success-title{font-size:1.1rem;font-weight:800;margin-bottom:.4rem}
.success-sub{font-size:.83rem;color:var(--muted);line-height:1.5;margin-bottom:1.2rem}

/* ── Toast ── */
.toast-wrap{position:fixed;bottom:calc(70px + env(safe-area-inset-bottom));left:50%;transform:translateX(-50%);z-index:999;display:flex;flex-direction:column;gap:.5rem;align-items:center;pointer-events:none;width:90%;max-width:380px}
@keyframes toastIn{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
@keyframes toastOut{to{opacity:0;transform:translateY(8px)}}
.toast{background:#1e293b;color:#fff;padding:.65rem 1.1rem;border-radius:10px;font-size:.82rem;font-weight:500;display:flex;align-items:center;gap:.5rem;animation:toastIn .3s ease both;box-shadow:0 8px 24px rgba(0,0,0,.2);pointer-events:auto;width:100%}
.toast svg{width:16px;height:16px;flex-shrink:0}
.toast.ok{background:var(--ok)}.toast.err{background:var(--err)}

@media(min-width:480px){
    .app{border-radius:0;box-shadow:0 0 60px rgba(0,0,0,.15)}
    .bnav{border-radius:0}
}
@media(max-width:360px){
    .sheet{border-radius:16px 16px 0 0}
    .prod-sheet-img{max-height:50vh}
    .sheet-body{padding:.75rem .85rem}
}
</style>
<style><?php readfile(__DIR__ . '/assets/vitrine-experience.css'); ?></style>
<script src="assets/storefront-menu.js?v=20261010.1"></script>
</head>
<body data-experience="<?= htmlspecialchars($experienceMode, ENT_QUOTES, 'UTF-8') ?>">
<div class="app" id="app">

    <!-- Top Bar -->
    <div class="topbar">
        <?php if($logo && file_exists($logo)): ?>
        <div class="topbar-logo"><img src="<?= htmlspecialchars($logo) ?>" alt="Logo"></div>
        <?php else: ?>
        <div class="topbar-logo" style="background:linear-gradient(135deg,<?= $cor ?>,<?= $cor ?>99)"><?= $ini ?></div>
        <?php endif; ?>
        <span class="topbar-name"><?= htmlspecialchars($nome_loja) ?></span>
        <button class="cart-btn" id="cartBtn" onclick="openCart()" title="Carrinho">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96C5 16.1 6.1 17 7 17h11v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63H18c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1 1 0 0022.46 4H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
            <span class="cart-badge" id="cartBadge">0</span>
        </button>
    </div>

    <!-- Hero -->
    <div class="hero<?= ($banner && file_exists($banner)) ? '' : ' hero-empty' ?>">
        <?php if($banner && file_exists($banner)): ?>
        <img src="<?= htmlspecialchars($banner) ?>" alt="Banner">
        <?php endif; ?>
    </div>

    <section class="store-identity" aria-label="Sobre a loja">
        <div class="store-title"><?= htmlspecialchars($nome_loja) ?></div>
        <?php if($descricao): ?>
        <p class="store-description"><?= htmlspecialchars($descricao) ?></p>
        <?php endif; ?>
    </section>

    <!-- Info Strips -->
    <?php if(($experience['retirada_habilitada'] && $hor_ret) || ($experience['entrega_habilitada'] && $hor_ent) || $whatsapp): ?>
    <div class="info-strips">
        <?php if(($experience['retirada_habilitada'] && $hor_ret) || ($experience['entrega_habilitada'] && $hor_ent)): ?><div class="delivery-info"><?php endif; ?>
        <?php if($experience['retirada_habilitada'] && $hor_ret): ?>
        <div class="info-strip">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17 12h-5v5h5v-5zM16 1v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2h-1V1h-2zm3 18H5V8h14v11z"/></svg>
            <span>Retirada: <strong><?= htmlspecialchars($hor_ret) ?></strong></span>
        </div>
        <?php endif; ?>
        <?php if($experience['entrega_habilitada'] && $hor_ent): ?>
        <div class="info-strip">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4zM6 18.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
            <span>Entrega: <strong><?= htmlspecialchars($hor_ent) ?></strong></span>
        </div>
        <?php endif; ?>
        <?php if(($experience['retirada_habilitada'] && $hor_ret) || ($experience['entrega_habilitada'] && $hor_ent)): ?></div><?php endif; ?>
        <?php if($whatsapp): ?>
        <a href="https://wa.me/<?= preg_replace('/\D/','',$whatsapp) ?>" target="_blank" rel="noopener noreferrer" class="info-link">
            <svg viewBox="0 0 24 24" fill="#25D366" width="16" height="16"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            Ver se entrega no meu endereço
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Busca -->
    <div class="search-wrap">
        <div class="search-box">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            <input id="busca" aria-label="Buscar produtos" placeholder="Buscar produto…" type="search" autocomplete="off" oninput="filtrar()">
        </div>
    </div>

    <div class="catalog-tools" id="catalogTools" hidden>
        <label class="filter-control"><span>Ordenar</span><select id="catalogSort" aria-label="Ordenar produtos" onchange="renderProdutos()"><option value="default">Recomendados</option><option value="price-asc">Menor preço</option><option value="price-desc">Maior preço</option><option value="name">Nome</option><option id="sortFeatured" value="featured">Destaques</option></select></label>
        <details class="catalog-filter-details"><summary>Filtros</summary><div class="catalog-filter-pop"><label class="filter-check" id="availabilityFilter"><input id="onlyAvailable" type="checkbox" onchange="renderProdutos()"> Disponíveis</label><label class="filter-check" id="hasSizeFilter"><input id="hasSize" type="checkbox" onchange="renderProdutos()"> Com tamanhos</label></div></details>
    </div>

    <div class="store-status" id="storeStatus" aria-live="polite" hidden></div>

    <!-- Categorias -->
    <div class="cats" id="cats">
        <button class="cat-btn on" data-cat="todos" onclick="setCat('todos',this)">Todos</button>
    </div>

    <!-- Destaques -->
    <section class="featured-section" id="featuredSection" hidden>
        <div class="section-head">
            <div class="sec-title">★ Destaques</div>
            <button class="featured-link" type="button" onclick="mostrarDestaques()">Ver todos</button>
        </div>
        <div class="featured-row" id="featuredList"></div>
    </section>

    <!-- Catálogo -->
    <div class="section-head" id="catalogHead"><div class="sec-title" id="secTitle">Todos os produtos</div></div>

    <!-- Lista -->
    <div class="prod-list" id="prodList">
        <?php for($i=0;$i<4;$i++):?>
        <div class="sk-card">
            <div class="sk" style="width:76px;height:76px;border-radius:10px;flex-shrink:0"></div>
            <div style="flex:1;display:flex;flex-direction:column;gap:.4rem;padding:.2rem 0">
                <div class="sk" style="height:.9rem;width:75%"></div>
                <div class="sk" style="height:.75rem;width:55%"></div>
                <div class="sk" style="height:.9rem;width:40%"></div>
            </div>
        </div>
        <?php endfor;?>
    </div>

    <div class="bnav-spacer"></div>
</div>

<!-- Bottom Nav -->
<nav class="bnav">
    <div class="bnav-inner">
        <button class="bnav-a on" onclick="closeCart()">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M11 9h2V6h3V4h-3V1h-2v3H8v2h3v3zm-4 9c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zm10 0c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2zm-9.83-3.25l.03-.12.9-1.63H17c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1 1 0 0021.46 4H5.21l-.94-2H1v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96C5 16.1 6.1 17 7 17h11v-2H7.42c-.14 0-.25-.11-.25-.25z"/></svg>
            Catálogo
        </button>
        <button class="bnav-a" id="btnNavPedidos" onclick="openPedidos()">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4zm2.5 2.1h-15V5h15v14.1zm0-16.1h-15C3.1 3 2 4.1 2 5.25V20c0 1.1.9 2 2 2h15c1.1 0 2-.9 2-2V5.25C21 4.1 19.9 3 18.5 3z"/></svg>
            Pedidos
        </button>
        <button class="bnav-a cart-nav" id="btnNavCart" onclick="openCart()" aria-label="Abrir carrinho">
            <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6h-2c0-2.76-2.24-5-5-5S7 3.24 7 6H5c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-7-3c1.66 0 3 1.34 3 3H9c0-1.66 1.34-3 3-3zm0 10c-2.21 0-4-1.79-4-4h2c0 1.1.9 2 2 2s2-.9 2-2h2c0 2.21-1.79 4-4 4z"/></svg>
            <span class="cart-nav-copy"><span id="cartNavLabel">Meu carrinho</span><small id="cartNavMeta" hidden></small></span>
        </button>
    </div>
</nav>

<!-- SHEET CARRINHO -->
<div class="sheet-bg" id="cartBg" onclick="closeCart()"></div>
<div class="sheet" id="cartSheet" role="dialog" aria-modal="true" aria-labelledby="cartTitle" tabindex="-1" style="display:none">
    <div class="sheet-handle"></div>
    <div class="sheet-head">
        <span class="sheet-title" id="cartTitle">Carrinho</span>
        <div style="display:flex;align-items:center;gap:.5rem">
            <button onclick="limparCarrinho()" style="font-size:.72rem;font-weight:600;color:var(--err);background:none;border:none;cursor:pointer" id="btnLimpar">Limpar</button>
            <button class="sheet-cl" onclick="closeCart()"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button>
        </div>
    </div>
    <div class="sheet-body" id="cartBody">
        <div style="text-align:center;padding:2rem;color:var(--faint);font-size:.83rem">
            <div class="spinner" style="margin:0 auto .8rem;border-color:var(--border);border-top-color:var(--p)"></div>Carregando…
        </div>
    </div>
    <div class="sheet-foot" id="cartFoot" style="display:none">
        <div class="cart-review" id="cartReviewNotice" role="status" hidden></div>
        <button class="btn-main" id="cartReviewButton" onclick="openCheckout()">
            <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M20 4H4c-1.11 0-2 .89-2 2v12c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
            Fazer Pedido
        </button>
    </div>
</div>

<!-- SHEET PRODUTO -->
<div class="sheet-bg" id="prodBg" onclick="closeProdSheet()"></div>
<div class="sheet prod-sheet" id="prodSheet" role="dialog" aria-modal="true" aria-label="Configurar produto" tabindex="-1" style="display:none">
    <div class="sheet-handle"></div>
    <div id="prodSheetContent" style="display:flex;overflow:hidden;flex:1;min-height:0"></div>
</div>

<!-- SHEET CHECKOUT -->
<div class="sheet-bg" id="checkBg" onclick="closeCheckout()"></div>
<div class="sheet" id="checkSheet" role="dialog" aria-modal="true" aria-labelledby="checkoutTitle" tabindex="-1" style="display:none">
    <div class="sheet-handle"></div>
    <div class="sheet-head">
        <span class="sheet-title" id="checkoutTitle">Finalizar Pedido</span>
        <button class="sheet-cl" onclick="closeCheckout()"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg></button>
    </div>
    <div class="sheet-body" id="checkBody"></div>
    <div class="sheet-foot" id="checkFoot"></div>
</div>

<!-- TOAST -->
<div class="toast-wrap" id="toastWrap" aria-live="polite" aria-atomic="false"></div>

<script>
const SLUG     = <?= json_encode($slug, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const SLUG_WA  = <?= json_encode($whatsapp, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const ENDERECO = <?= json_encode($endereco, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const CIDADE_UF= <?= json_encode($cidade_uf, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const HOR_RET  = <?= json_encode($hor_ret, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const HOR_ENT  = <?= json_encode($hor_ent, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const EXPERIENCE = <?= json_encode($experience, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const $   = id => document.getElementById(id);
const esc = v  => String(v??'').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
const R   = v  => 'R$\u00a0'+Number(v||0).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});

const STORE_VISITOR_KEY='uplar_store_visitor';
const STORE_SESSION_KEY='uplar_store_session';
const STORE_TRACK_ENDPOINT='/vitrine_track.php';
const storeRuntime={
    visitorId:localStorage.getItem(STORE_VISITOR_KEY)||cryptoId(),
    sessionId:sessionStorage.getItem(STORE_SESSION_KEY)||cryptoId(),
    startedAt:Date.now(),maxScroll:0,ended:false,searchTimer:null
};
localStorage.setItem(STORE_VISITOR_KEY,storeRuntime.visitorId);
sessionStorage.setItem(STORE_SESSION_KEY,storeRuntime.sessionId);

function cryptoId(){
    if(window.crypto?.randomUUID)return window.crypto.randomUUID();
    if(!window.crypto?.getRandomValues)return String(Date.now())+Math.random().toString(16).slice(2);
    const bytes=new Uint8Array(16);window.crypto?.getRandomValues?.(bytes);
    return Array.from(bytes,b=>b.toString(16).padStart(2,'0')).join('');
}
function storeTrack(eventType,productId=null,value=null,beacon=false){
    const payload={slug:SLUG,event_type:eventType,visitor_id:storeRuntime.visitorId,session_id:storeRuntime.sessionId,referrer:document.referrer||''};
    if(productId)payload.product_id=Number(productId);
    if(value!==null)payload.value=Math.max(0,Math.round(Number(value)||0));
    const body=JSON.stringify(payload);
    if(beacon&&navigator.sendBeacon)return navigator.sendBeacon(STORE_TRACK_ENDPOINT,new Blob([body],{type:'application/json'}));
    return fetch(STORE_TRACK_ENDPOINT,{method:'POST',headers:{'Content-Type':'application/json'},body,keepalive:beacon}).catch(()=>null);
}

/* ── Toast ── */
function toast(msg,type='ok',dur=2800){
    const w=$('toastWrap'),el=document.createElement('div');
    el.className='toast '+(type==='err'?'err':type==='ok'?'ok':'');
    el.innerHTML=(type==='err'
        ?'<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>'
        :'<svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>'
    )+esc(msg);
    w.appendChild(el);
    setTimeout(()=>{el.style.animation='toastOut .3s forwards';setTimeout(()=>el.remove(),300)},dur);
}

/* ══════════════════════════════════════
   PRODUTOS
══════════════════════════════════════ */
let produtos=[],catAtual='todos',buscaTxt='';

async function carregarProdutos(){
    try{
        const r=await fetch(`vitrine_api.php?acao=listar_produtos&slug=${encodeURIComponent(SLUG)}`);
        const j=await r.json();
        if(!j.ok) throw new Error(j.error);
        produtos=j.produtos||[];
        gerarCats();
        renderProdutos();
        openLinkedProduct();
    }catch(e){
        $('prodList').innerHTML='<div class="empty-state"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg><p>Erro ao carregar produtos.</p></div>';
    }
}

function productStock(p){
    const base=Number(p.estoque_disponivel??p.quantidade??p.estoque);
    if(Array.isArray(p.tamanhos)&&p.tamanhos.length){const variants=p.tamanhos.reduce((n,size)=>n+Math.max(0,Number(size.quantidade)||0),0);return Number.isFinite(base)&&base>=0?Math.min(base,variants):variants;}
    if(Number.isFinite(base)&&base>=0)return base;
    if(typeof p.disponivel==='boolean')return p.disponivel?1:0;
    return 0;
}
function productSelectedStock(){
    const selected=prodConfig?.usa_tamanhos?(prodConfig.tamanhos||[]).find(s=>s.tamanho===prodTamanho):null;
    const base=Number(prodAtual?.estoque_disponivel??prodAtual?.quantidade??prodAtual?.estoque);
    if(!selected)return productStock(prodAtual||{});
    const variant=Math.max(0,Number(selected.quantidade)||0);
    return Number.isFinite(base)&&base>=0?Math.min(base,variant):variant;
}
function gerarCats(){
    const cats=$('cats');cats.innerHTML='';cats.hidden=false;
    const configured=(EXPERIENCE.categorias||[]).filter(c=>Array.isArray(c.produto_ids)&&c.produto_ids.length);
    const visible=EXPERIENCE.indisponiveis==='ocultar'?produtos.filter(p=>productStock(p)>0):produtos;
    const grouped=UplarStorefrontMenu.groupProducts(visible,configured);
    let choices=grouped.groups.map(c=>({id:String(c.id),nome:String(c.nome),produto_ids:c.produtos.map(p=>Number(p.id))}));
    if(document.body.dataset.experience==='cardapio'&&choices.length&&grouped.uncategorized.length){
        choices.push({id:'outros',nome:'Sem categoria',produto_ids:grouped.uncategorized.map(p=>Number(p.id))});
    }
    if(!choices.length&&document.body.dataset.experience==='padrao'){
        const legacy=[...new Set(produtos.map(p=>String(p.nome||'').split(' ')[0].toUpperCase()).filter(Boolean))].slice(0,8);
        choices=legacy.map(name=>({id:name,nome:name,legacy:true}));
    }
    if(!choices.length){cats.hidden=true;}else{
        const all=document.createElement('button');all.className='cat-btn on';all.type='button';all.dataset.cat='todos';all.setAttribute('aria-pressed','true');all.textContent='Todos';all.onclick=()=>setCat('todos',all);cats.appendChild(all);
        choices.forEach(c=>{const button=document.createElement('button');button.className='cat-btn';button.type='button';button.dataset.cat=c.id;button.setAttribute('aria-pressed','false');button.textContent=c.nome;button.onclick=()=>setCat(c.id,button);cats.appendChild(button);});
    }
    $('catalogTools').hidden=document.body.dataset.experience!=='loja';
    $('availabilityFilter').hidden=!produtos.some(p=>productStock(p)<1);
    $('hasSizeFilter').hidden=!produtos.some(p=>p.usa_tamanhos===true||(Array.isArray(p.tamanhos)&&p.tamanhos.length>0));
    $('sortFeatured').hidden=EXPERIENCE.mostrar_destaques===false||!produtos.some(p=>p.vitrine_destaque==='featured'||p.vitrine_destaque==='promotion');
    const status=$('storeStatus');
    const unavailable=EXPERIENCE.pedidos_habilitados===false;
    const disabledModes=[EXPERIENCE.entrega_habilitada===false?'Entrega temporariamente indisponível.':'',EXPERIENCE.retirada_habilitada===false?'Retirada temporariamente indisponível.':''].filter(Boolean);
    status.hidden=!unavailable&&!disabledModes.length;
    status.textContent=unavailable?'A loja está temporariamente sem receber pedidos.':disabledModes.join(' ');
}

let menuScrollFrame=0;
function setMenuActiveCategory(category,ensureVisible=false){
    const nav=$('cats');
    for(const button of nav.querySelectorAll('.cat-btn')){
        const on=button.dataset.cat===String(category);
        button.classList.toggle('on',on);
        button.setAttribute('aria-pressed',String(on));
        if(on&&ensureVisible){
            const rect=button.getBoundingClientRect(),bounds=nav.getBoundingClientRect();
            if(rect.left<bounds.left+12||rect.right>bounds.right-12){
                button.scrollIntoView({block:'nearest',inline:'center',behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});
            }
        }
    }
}
function syncMenuCategoryOnScroll(){
    menuScrollFrame=0;
    if(document.body.dataset.experience!=='cardapio'||$('cats').hidden||buscaTxt)return;
    const topbar=document.querySelector('.topbar');
    const offset=(topbar?.getBoundingClientRect().height||0)+$('cats').getBoundingClientRect().height+14;
    const sections=[...$('prodList').querySelectorAll('.menu-category')].map(section=>({
        id:section.dataset.category,
        top:section.getBoundingClientRect().top
    }));
    const active=UplarStorefrontMenu.categoryAtOffset(sections,offset);
    setMenuActiveCategory(active,true);
}
function scheduleMenuCategorySync(){
    if(menuScrollFrame)return;
    menuScrollFrame=requestAnimationFrame(syncMenuCategoryOnScroll);
}
function setCat(cat,btn){
    if(document.body.dataset.experience==='cardapio'&&$('prodList').querySelector('.menu-category')){
        // Cardápio com seções: navega até a categoria sem esconder o restante.
        catAtual='todos';
        if(buscaTxt){buscaTxt='';$('busca').value='';renderProdutos();}
        setMenuActiveCategory(cat,true);
        const target=cat==='todos' ? $('prodList') : [...$('prodList').querySelectorAll('.menu-category')].find(s=>s.dataset.category===String(cat));
        (target||$('prodList')).scrollIntoView({behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth',block:'start'});
    }else{
        catAtual=cat;
        setMenuActiveCategory(cat);
        renderProdutos();
    }
    storeTrack('category_filter');
}

function filtrar(){
    buscaTxt=$('busca').value.toLowerCase().trim();renderProdutos();
    clearTimeout(storeRuntime.searchTimer);
    if(buscaTxt)storeRuntime.searchTimer=setTimeout(()=>storeTrack('search',null,buscaTxt.length),700);
}

function estadoDestaque(p){
    if(EXPERIENCE.mostrar_destaques===false)return '';
    return p.vitrine_destaque==='promotion' ? 'promotion' : p.vitrine_destaque==='featured' ? 'featured' : '';
}

function badgeDestaque(p){
    if(EXPERIENCE.mostrar_destaques===false)return '';
    return p.vitrine_destaque==='promotion'
        ? '<span class="prod-badge promotion">🔥 Promoção</span>'
        : p.vitrine_destaque==='featured' ? '<span class="prod-badge featured">★ Destaque</span>' : '';
}

function chipsTamanho(descricao){
    const match=String(descricao||'').match(/dispon[ií]vel\s+(?:nos?\s+)?tamanhos?\s*:\s*([^.!]+)/i);
    if(!match)return '';
    const tamanhos=match[1].split(/,|\s+e\s+|\//i).map(v=>v.trim()).filter(v=>/^[a-z0-9]{1,5}$/i.test(v)).slice(0,5);
    return tamanhos.length ? `<div class="size-chips">${tamanhos.map(t=>`<span class="size-chip">${esc(t.toUpperCase())}</span>`).join('')}</div>` : '';
}

function productAction(p){
    if(productStock(p)<1)return 'Indisponível';
    if(p.tem_complementos)return 'Personalizar';
    if(p.usa_tamanhos)return 'Escolher tamanho';
    return 'Adicionar';
}
function cardProduto(p,featured=false){
    const destaque=estadoDestaque(p),descricao=p.descricao?`<div class="prod-desc">${esc(p.descricao)}</div>`:'';
    const stock=productStock(p),sold=stock<1;
    const sizes=Array.isArray(p.tamanhos)&&p.tamanhos.length?`<div class="size-chips">${p.tamanhos.slice(0,5).map(size=>`<span class="size-chip ${Number(size.quantidade)<1?'out':''}">${esc(size.tamanho)}${Number(size.quantidade)<1?' · esgotado':''}</span>`).join('')}</div>`:'';
    const imagem=p.imagem?`<img src="${esc(p.imagem)}" alt="${esc(p.nome)}" loading="lazy">`:'<svg viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>';
    const action=productAction(p),configure=Boolean(p.usa_tamanhos||p.tem_complementos);
    const noFulfillment=EXPERIENCE.retirada_habilitada===false&&EXPERIENCE.entrega_habilitada===false;const add=`<button class="prod-action" type="button" ${sold||noFulfillment?'disabled':''} aria-label="${esc(action)} ${esc(p.nome)}" onclick="event.stopPropagation();${configure?'openProdById('+p.id+')':'addToCart('+p.id+',1)'}" id="add-${p.id}">${esc(action)}</button>`;
    return `<article class="prod-card ${featured?'featured-card ':''}${destaque} ${sold?'sold-out':''}" tabindex="0" onclick="if(!event.target.closest('button,a,input'))openProdById(${p.id})" onkeydown="if(event.target===this&&(event.key==='Enter'||event.key===' ')){event.preventDefault();openProdById(${p.id})}">
        <div class="prod-img">${imagem}</div><div class="prod-body">${badgeDestaque(p)}<div class="prod-name">${esc(p.nome)}</div>${descricao}${sizes}<div class="prod-card-bottom"><div><div class="prod-price">${R(p.preco)}</div>${sold?'<span class="stock-label">Esgotado</span>':''}</div>${add}</div></div>
    </article>`;
}

function openProdById(id){
    const produto=produtos.find(p=>Number(p.id)===Number(id));
    if(produto)openProd(produto);
}

function produtoUrl(p){
    const url=new URL(window.location.href);
    url.searchParams.set('produto',String(p.id));
    return url.toString();
}

function openLinkedProduct(){
    if(storeRuntime.productLinkOpened)return;
    const id=Number(new URLSearchParams(window.location.search).get('produto'));
    if(!Number.isInteger(id)||id<1)return;
    const produto=produtos.find(p=>Number(p.id)===id);
    if(!produto)return;
    storeRuntime.productLinkOpened=true;
    openProd(produto);
}

function mostrarDestaques(){
    catAtual='todos';
    buscaTxt='';
    $('busca').value='';
    document.querySelectorAll('.cat-btn').forEach(b=>b.classList.toggle('on',b.dataset.cat==='todos'));
    renderProdutos();
    const section=$('featuredSection');
    if(section)section.scrollIntoView({behavior:'smooth',block:'start'});
}

function renderProdutos(){
    const list=$('prodList'),featuredList=$('featuredList'),featuredSection=$('featuredSection'),catalogHead=$('catalogHead');
    let f=[...produtos];
    const configuredCategories=(EXPERIENCE.categorias||[]).filter(c=>Array.isArray(c.produto_ids)&&c.produto_ids.length);
    const selectedCategory=configuredCategories.find(c=>String(c.id)===String(catAtual));
    const categoryProductIds=new Map(configuredCategories.map(category=>[String(category.id),new Set(category.produto_ids.map(Number))]));
    const categoryByProduct=new Map();
    for(const category of configuredCategories){
        const ids=categoryProductIds.get(String(category.id));
        for(const id of ids)if(!categoryByProduct.has(id))categoryByProduct.set(id,category);
    }
    const legacyCategory=catAtual!=='todos'&&!selectedCategory&&document.body.dataset.experience==='padrao';
    if(selectedCategory){const ids=categoryProductIds.get(String(selectedCategory.id))||new Set();f=f.filter(p=>ids.has(Number(p.id)));}
    else if(legacyCategory)f=f.filter(p=>String(p.nome||'').toUpperCase().startsWith(String(catAtual).toUpperCase()));
    if(buscaTxt)f=f.filter(p=>`${p.nome||''} ${p.descricao||''} ${categoryByProduct.get(Number(p.id))?.nome||''}`.toLowerCase().includes(buscaTxt));
    if(EXPERIENCE.indisponiveis==='ocultar')f=f.filter(p=>productStock(p)>0);
    if($('onlyAvailable')?.checked)f=f.filter(p=>productStock(p)>0);
    if($('hasSize')?.checked)f=f.filter(p=>p.usa_tamanhos===true||(Array.isArray(p.tamanhos)&&p.tamanhos.length>0));
    const sort=$('catalogSort')?.value||'default';
    if(sort==='price-asc')f.sort((a,b)=>Number(a.preco)-Number(b.preco));
    if(sort==='price-desc')f.sort((a,b)=>Number(b.preco)-Number(a.preco));
    if(sort==='name')f.sort((a,b)=>String(a.nome).localeCompare(String(b.nome),'pt-BR'));
    if(sort==='featured')f.sort((a,b)=>Number(Boolean(estadoDestaque(b)))-Number(Boolean(estadoDestaque(a))));
    const destaques=EXPERIENCE.mostrar_destaques===false?[]:f.filter(p=>p.vitrine_destaque==='promotion'||p.vitrine_destaque==='featured');
    const comuns=f.filter(p=>!destaques.includes(p));
    $('secTitle').textContent=catAtual==='todos'?(configuredCategories.length||document.body.dataset.experience==='padrao'&&produtos.length?'Todos os produtos':'Produtos'):(selectedCategory?.nome||catAtual.charAt(0)+catAtual.slice(1).toLowerCase());
    if(!f.length){featuredSection.hidden=true;catalogHead.hidden=false;list.innerHTML='<div class="empty-state"><p>Nenhum produto encontrado.</p></div>';return;}
    if(document.body.dataset.experience==='cardapio'&&configuredCategories.length){
        // Destaques aparecem com selo dentro da própria categoria,
        // evitando duplicar cards e categorias vazias na navegação.
        featuredSection.hidden=true;
        featuredList.replaceChildren();
        const grouped=UplarStorefrontMenu.groupProducts(f,configuredCategories);
        const groups=grouped.groups;
        if(grouped.uncategorized.length)groups.push({id:'outros',nome:'Sem categoria',produtos:grouped.uncategorized});
        catalogHead.hidden=true;
        list.innerHTML=groups.map(group=>`<section class="menu-category" id="menu-category-${esc(group.id)}" data-category="${esc(group.id)}"><h2>${esc(group.nome)}</h2><div class="menu-category-products">${group.produtos.map(p=>cardProduto(p)).join('')}</div></section>`).join('');
        if(buscaTxt)setMenuActiveCategory('todos');
        else scheduleMenuCategorySync();
        return;
    }
    featuredSection.hidden=!destaques.length;catalogHead.hidden=!comuns.length;
    featuredList.innerHTML=destaques.map(p=>cardProduto(p,true)).join('');
    list.innerHTML=comuns.length?comuns.map(p=>cardProduto(p)).join(''):'';
}

/* ══════════════════════════════════════
   DETALHE PRODUTO
══════════════════════════════════════ */
let prodQtd=1,prodTamanho=null,prodAtual=null,prodConfig=null,prodSelections={},prodItemNote='',editingCartItem=null,editingRequestToken='',editingDraftSignature='',editingLegacyConfig=false,sheetSaving=false,productBusyReturnFocus=null;

async function openProd(p,itemToEdit=null){
    prodQtd=itemToEdit?Number(itemToEdit.qtd_carrinho)||1:1;prodTamanho=itemToEdit?.tamanho||null;prodAtual=p;prodConfig=null;prodSelections={};prodItemNote=String(itemToEdit?.personalizacao?.observacao||'').slice(0,500);editingCartItem=itemToEdit;editingRequestToken='';editingDraftSignature='';editingLegacyConfig=Boolean(itemToEdit?.legacy_configuration);
    storeTrack('product_view',p.id);
    $('prodBg').classList.add('on');$('prodSheet').style.display='flex';document.body.style.overflow='hidden';activateSheet('prodSheet');
    $('prodSheetContent').innerHTML='<div style="padding:2rem;text-align:center;color:var(--muted)"><div class="spinner" style="margin:0 auto .8rem;border-color:var(--border);border-top-color:var(--p)"></div>Carregando opções…</div>';
    try{
        const url=`vitrine_api.php?acao=produto_complementos&slug=${encodeURIComponent(SLUG)}&produto_id=${encodeURIComponent(p.id)}`;
        const response=await fetch(url,{credentials:'same-origin'});const data=await response.json();
        if(!response.ok||!data.ok)throw new Error(data.error||'Não foi possível carregar as opções deste produto.');
        prodConfig=data;if(itemToEdit){for(const group of (itemToEdit.personalizacao?.grupos||[])){const id=Number(group.grupo_id);prodSelections[id]={};for(const option of (group.opcoes||[]))prodSelections[id][Number(option.opcao_id)]=Number(option.quantidade)||0;}if(itemToEdit.personalizacao?.indisponivel||itemToEdit.revisao_requerida)toast('As opções deste item mudaram. Confira a configuração antes de salvar.','err');}renderProductSheet();requestAnimationFrame(initImgZoom);
    }catch(error){
        $('prodSheetContent').innerHTML=`<div style="padding:2rem;text-align:center;color:var(--err)">${esc(error.message||'Não foi possível carregar as opções.')}<button class="btn-main" style="margin-top:1rem" onclick="closeProdSheet()">Fechar</button></div>`;
    }
}

function productSelectionsPayload(){
    const groups=[];
    for(const group of (prodConfig?.grupos||[])){
        const selected=prodSelections[group.id]||{},validOptionIds=new Set((group.opcoes||[]).map(option=>Number(option.id)));
        const options=Object.entries(selected).filter(([id,quantity])=>validOptionIds.has(Number(id))&&Number(quantity)>0).map(([opcao_id,quantidade])=>({opcao_id:Number(opcao_id),quantidade:Number(quantidade)}));
        if(options.length)groups.push({grupo_id:Number(group.id),opcoes:options});
    }
    return groups;
}
function productSelectionUnits(group){const valid=new Set((group.opcoes||[]).map(option=>Number(option.id)));return Object.entries(prodSelections[group.id]||{}).reduce((sum,[id,value])=>sum+(valid.has(Number(id))?Number(value||0):0),0);}
function unavailableProductSelections(){const original=editingCartItem?.personalizacao?.grupos||[],result=[];for(const [groupKey,selected] of Object.entries(prodSelections||{})){const groupId=Number(groupKey),activeGroup=(prodConfig?.grupos||[]).find(group=>Number(group.id)===groupId),originalGroup=original.find(group=>Number(group.grupo_id)===groupId);if(!activeGroup){for(const [optionId,quantity] of Object.entries(selected||{}))if(Number(quantity)>0)result.push({groupId,optionId:Number(optionId),name:originalGroup?.nome||'Grupo de opções'});continue;}const activeIds=new Set((activeGroup.opcoes||[]).map(option=>Number(option.id)));for(const [optionId,quantity] of Object.entries(selected||{})){if(Number(quantity)<=0||activeIds.has(Number(optionId)))continue;const oldOption=(originalGroup?.opcoes||[]).find(option=>Number(option.opcao_id)===Number(optionId));result.push({groupId,optionId:Number(optionId),name:oldOption?.nome||'Opção indisponível'});}}return result;}
function clearUnavailableProductSelections(){const nextFocus=document.querySelector('#sheetSizes button:not(:disabled),.custom-groups input:not(:disabled),.custom-step button:not(:disabled)');for(const item of unavailableProductSelections()){if(prodSelections[item.groupId])delete prodSelections[item.groupId][item.optionId];if(!Object.keys(prodSelections[item.groupId]||{}).length)delete prodSelections[item.groupId];}refreshProductControls();nextFocus?.focus();}
function productChoicesValid(){
    if(!prodConfig||productStock(prodAtual)<1||unavailableProductSelections().length)return false;
    const selectedSize=prodConfig.usa_tamanhos?(prodConfig.tamanhos||[]).find(s=>s.tamanho===prodTamanho):null;
    if(prodConfig.usa_tamanhos&&!selectedSize)return false;
    if(prodQtd>productSelectedStock())return false;
    if(prodConfig.usa_tamanhos&&!prodTamanho)return false;
    return (prodConfig.grupos||[]).every(group=>{const count=productSelectionUnits(group);return count>=Number(group.minimo||0)&&count<=Number(group.maximo||0);});
}
function productPriceCents(){
    let cents=Math.round(Number(prodAtual?.preco||0)*100);
    for(const group of (prodConfig?.grupos||[]))for(const option of (group.opcoes||[]))cents+=Math.round(Number(option.preco_adicional||0)*100)*Number((prodSelections[group.id]||{})[option.id]||0);
    return cents;
}
function setProductSheetBusy(busy){
    const root=$('prodSheetContent');
    if(busy){productBusyReturnFocus=document.activeElement;sheetSaving=true;root?.querySelectorAll('button,input,textarea').forEach(el=>{el.dataset.busyWasDisabled=el.disabled?'1':'0';el.disabled=true;});}
    else{sheetSaving=false;root?.querySelectorAll('[data-busy-was-disabled]').forEach(el=>{el.disabled=el.dataset.busyWasDisabled==='1';delete el.dataset.busyWasDisabled;});}
    updateProductSheetPrice();
    if(!busy){if(productBusyReturnFocus?.isConnected&&$('prodSheet').style.display==='flex')productBusyReturnFocus.focus();productBusyReturnFocus=null;}
}
function updateProductSheetPrice(){
    const button=$('addConfiguredProduct');
    if(button){button.textContent=`${editingCartItem?'Salvar alterações':'Adicionar'} — ${R(productPriceCents()*prodQtd/100)}`;button.disabled=!prodConfig||productSelectedStock()<1||prodQtd<1||prodQtd>productSelectedStock()||editingLegacyConfig||sheetSaving;}
    const base=$('customUnitPrice');if(base)base.textContent=R(productPriceCents()/100);
    const sizeValidation=$('sizeValidation');if(sizeValidation)sizeValidation.textContent=prodConfig?.usa_tamanhos&&!prodTamanho?'Escolha um tamanho para continuar.':'';
    for(const group of (prodConfig?.grupos||[])){const validation=$(`groupValidation-${group.id}`);if(!validation)continue;const selected=productSelectionUnits(group),minimum=Number(group.minimo||0),maximum=Number(group.maximo||0);validation.textContent=selected<minimum?`Selecione pelo menos ${minimum} opção(ões).`:selected>maximum?`Escolha no máximo ${maximum} opção(ões).`:'';}
    updateUnavailableSelectionNotice();
}
function renderProductGroups(){
    return (prodConfig?.grupos||[]).map(group=>{
        const single=Number(group.maximo)===1;
        const total=productSelectionUnits(group);const required=Number(group.minimo)>0;
        const descriptor=required?`Obrigatório · Escolha ${Number(group.minimo)===Number(group.maximo)?group.minimo:`de ${group.minimo} a ${group.maximo}`}`:`Opcional · Até ${group.maximo} unidade(s)`;
        const options=(group.opcoes||[]).map(option=>{
            const quantity=Number((prodSelections[group.id]||{})[option.id]||0);const price=Number(option.preco_adicional||0);
            if(single)return `<label class="custom-option"><input type="radio" name="custom-group-${group.id}" value="${option.id}" ${quantity?'checked':''} onchange="setProductRadio(${group.id},${option.id})"><span class="custom-option-name">${esc(option.nome)}</span><span class="custom-option-price">${price>0?'+ '+R(price):R(0)}</span></label>`;
            const atGroupLimit=quantity===0&&total>=Number(group.maximo);const atOptionLimit=quantity>=Number(option.max_quantidade);
            return `<div class="custom-option" data-option-row="${group.id}-${option.id}"><span class="custom-option-name">${esc(option.nome)}</span><span class="custom-option-price">${price>0?'+ '+R(price):R(0)}</span><span class="custom-step"><button data-option-minus type="button" aria-label="Remover ${esc(option.nome)}" onclick="changeProductOption(${group.id},${option.id},-1)" ${quantity<1?'disabled':''}>−</button><span data-option-qty>${quantity}</span><button data-option-plus type="button" aria-label="Adicionar ${esc(option.nome)}" onclick="changeProductOption(${group.id},${option.id},1)" ${atGroupLimit||atOptionLimit?'disabled':''}>+</button></span></div>`;
        }).join('');
        const noneOption=single&&!required?`<label class="custom-option"><input type="radio" name="custom-group-${group.id}" value="0" ${total===0?'checked':''} onchange="setProductRadio(${group.id},0)"><span class="custom-option-name">Sem escolha</span><span class="custom-option-price">${R(0)}</span></label>`:'';
        return `<section class="custom-group" data-group-id="${group.id}"><div class="custom-group-head"><div class="custom-group-title">${esc(group.nome)}${required?' *':''}</div><div class="custom-group-help">${esc(descriptor)}${group.descricao?` · ${esc(group.descricao)}`:''}</div><div class="group-validation" id="groupValidation-${group.id}" role="status" aria-live="polite"></div></div>${noneOption}${options}</section>`;
    }).join('');
}
function renderProductSheet(){
    const p=prodAtual;
    $('prodSheetContent').innerHTML=`<div class="prod-sheet-content"><div class="prod-sheet-scroll"><div class="prod-sheet-img" id="prodImgBox">${p.imagem?`<img src="${esc(p.imagem)}" alt="${esc(p.nome)}" id="prodImgEl">`:'<span aria-hidden="true">◻</span>'}</div><div class="prod-sheet-body"><div class="prod-sheet-top"><div>${badgeDestaque(p)}<div class="prod-sheet-name">${esc(p.nome)}</div></div><button class="prod-share" type="button" onclick="compartilharProduto(${p.id})" aria-label="Compartilhar ${esc(p.nome)}">↗</button></div><div class="prod-sheet-price" id="customUnitPrice">${R(p.preco)}</div>${p.descricao?`<div class="prod-sheet-desc">${esc(p.descricao)}</div>`:''}${prodConfig.usa_tamanhos?`<section class="custom-group" id="sizeGroup"><div class="custom-group-title">Escolha o tamanho *</div><div class="group-validation" id="sizeValidation" role="status" aria-live="polite"></div><div class="size-chips" id="sheetSizes">${(prodConfig.tamanhos||[]).map(size=>`<button class="size-chip ${prodTamanho===size.tamanho?'on':''}" type="button" ${Number(size.quantidade)<1?'disabled':''} data-size="${esc(size.tamanho)}" data-stock="${Number(size.quantidade)}" onclick="selecionarTamanho(this.dataset.size,Number(this.dataset.stock))">${esc(size.tamanho)}</button>`).join('')}</div></section>`:''}<div id="selectionWarnings"></div><div class="custom-groups">${editingLegacyConfig?'<div class=\"cart-review\" role=\"status\">Este item tem uma configuração antiga que não pode ser alterada sem perder opções. Para atualizar, remova-o e adicione o produto novamente.</div>':''}${renderProductGroups()}</div><div class="custom-group"><label class="custom-group-title" for="customItemNote">Observações do item</label><textarea class="custom-note" id="customItemNote" maxlength="500" aria-label="Observações do item" placeholder="Ex.: pouco sal, molho à parte" oninput="prodItemNote=this.value">${esc(prodItemNote)}</textarea></div></div></div><div class="custom-footer"><div class="qty-row"><span class="qty-label">Quantidade</span><div class="qty-step"><button class="qs-btn" aria-label="Diminuir quantidade" type="button" onclick="changeQtd(-1)">−</button><span class="qs-val" id="qdVal">${prodQtd}</span><button class="qs-btn" aria-label="Aumentar quantidade" type="button" onclick="changeQtd(1)">+</button></div></div><button class="btn-main" id="addConfiguredProduct" type="button" onclick="addFromSheet(${p.id})">Adicionar — ${R(p.preco)}</button></div></div>`;
    updateProductSheetPrice();
    requestAnimationFrame(()=>{if(document.activeElement===$('prodSheet'))$('prodSheet').querySelector('button:not(:disabled),input:not(:disabled),textarea:not(:disabled)')?.focus();});
}

function updateUnavailableSelectionNotice(){const notice=$('selectionWarnings');if(!notice)return;const unavailable=unavailableProductSelections();notice.innerHTML=unavailable.length?`<div class=\"cart-review\" role=\"status\">Uma ou mais opções selecionadas deixaram de estar disponíveis. Remova-as ou escolha opções atuais antes de salvar.<ul>${unavailable.map(item=>`<li>${esc(item.name)} — opção indisponível</li>`).join('')}</ul><button class=\"clear-invalid-options\" type=\"button\" onclick=\"clearUnavailableProductSelections()\">Remover opções indisponíveis</button></div>`:'';}
function refreshProductControls(){
    const focused=document.activeElement,focusedRow=focused?.closest('[data-option-row]');
    const focusWasPlus=focused?.hasAttribute('data-option-plus'),focusWasMinus=focused?.hasAttribute('data-option-minus');
    for(const group of (prodConfig?.grupos||[])){
        const selected=prodSelections[group.id]||{};
        document.querySelectorAll(`[name="custom-group-${CSS.escape(String(group.id))}"]`).forEach(input=>input.checked=Number(input.value)===0?productSelectionUnits(group)===0:Number(selected[input.value]||0)>0);
        (group.opcoes||[]).forEach(option=>{
            const row=document.querySelector(`[data-option-row="${group.id}-${option.id}"]`);if(!row)return;
            const qty=Number(selected[option.id]||0);row.querySelector('[data-option-qty]').textContent=qty;
            row.querySelector('[data-option-minus]').disabled=qty<1;
            row.querySelector('[data-option-plus]').disabled=qty>=Number(option.max_quantidade)||productSelectionUnits(group)>=Number(group.maximo);
        });
    }
    updateProductSheetPrice();
    if(focusedRow&&((focusWasPlus&&focused.disabled)||(focusWasMinus&&focused.disabled))){
        const fallback=focusedRow.querySelector(focusWasPlus?'[data-option-minus]:not(:disabled)':'[data-option-plus]:not(:disabled)');fallback?.focus();
    }
}

function setProductRadio(groupId,optionId){prodSelections[groupId]=optionId?{[optionId]:1}:{};refreshProductControls();}
function changeProductOption(groupId,optionId,delta){
    const group=prodConfig?.grupos?.find(item=>Number(item.id)===Number(groupId));const option=group?.opcoes?.find(item=>Number(item.id)===Number(optionId));if(!group||!option)return;
    const selected=prodSelections[groupId]||{};const current=Number(selected[optionId]||0);const total=productSelectionUnits(group);const next=Math.max(0,Math.min(Number(option.max_quantidade),current+delta));
    if(delta>0&&total>=Number(group.maximo))return;
    prodSelections[groupId]={...selected,[optionId]:next};if(!next)delete prodSelections[groupId][optionId];refreshProductControls();
}

async function compartilharProduto(id){
    const produto=produtos.find(p=>Number(p.id)===Number(id));
    if(!produto)return;
    const url=produtoUrl(produto);
    const payload={title:produto.nome,text:`Confira ${produto.nome} na ${document.title}.`,url};
    try{
        if(navigator.share){
            await navigator.share(payload);
        }else if(navigator.clipboard?.writeText){
            await navigator.clipboard.writeText(url);
            toast('Link do produto copiado.');
        }else{
            const copy=document.createElement('textarea');
            copy.value=url;copy.setAttribute('readonly','');copy.style.position='fixed';copy.style.opacity='0';
            document.body.appendChild(copy);copy.select();document.execCommand('copy');copy.remove();
            toast('Link do produto copiado.');
        }
        storeTrack('product_share',produto.id);
    }catch(error){
        if(error?.name!=='AbortError')toast('Não foi possível compartilhar o produto.','err');
    }
}

function changeQtd(d){const max=productSelectedStock();prodQtd=Math.min(max,Math.max(1,prodQtd+d));$('qdVal').textContent=prodQtd;updateProductSheetPrice();}

/* ── Zoom imagem ── */
function initImgZoom(){
    const box=document.getElementById('prodImgBox');
    const img=document.getElementById('prodImgEl');
    const hint=document.getElementById('zoomHint');
    if(!img||!box)return;

    let scale=1, startDist=0, lastScale=1;

    // Esconde hint após 2.5s
    if(hint) setTimeout(()=>hint.classList.add('hide'),2500);

    // Duplo tap
    let lastTap=0;
    img.addEventListener('click',()=>{
        const now=Date.now();
        if(now-lastTap<300){
            scale = scale>1 ? 1 : 2.2;
            img.style.transform=`scale(${scale})`;
            box.classList.toggle('zoomed', scale>1);
            if(hint) hint.classList.add('hide');
        }
        lastTap=now;
    });

    // Pinch zoom (dois dedos)
    box.addEventListener('touchstart', e=>{
        if(e.touches.length===2){
            startDist=Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY
            );
            lastScale=scale;
        }
    },{passive:true});

    box.addEventListener('touchmove', e=>{
        if(e.touches.length===2){
            e.preventDefault();
            const d=Math.hypot(
                e.touches[0].clientX - e.touches[1].clientX,
                e.touches[0].clientY - e.touches[1].clientY
            );
            scale = Math.min(4, Math.max(1, lastScale*(d/startDist)));
            img.style.transform=`scale(${scale})`;
            box.classList.toggle('zoomed', scale>1);
        }
    },{passive:false});

    box.addEventListener('touchend', e=>{
        if(e.touches.length < 2 && scale < 1.15){
            scale=1;
            img.style.transform='scale(1)';
            box.classList.remove('zoomed');
        }
    });
}

function closeProdSheet(force=false){
    if(sheetSaving&&!force)return;
    $('prodBg').classList.remove('on');
    $('prodSheet').style.display='none';
    document.body.style.overflow='';restoreSheetFocus();editingCartItem=null;editingLegacyConfig=false;
    const url=new URL(window.location.href);
    if(url.searchParams.has('produto')){
        url.searchParams.delete('produto');
        history.replaceState({},'',url);
    }
}
function selecionarTamanho(tamanho,estoque){prodTamanho=tamanho;prodQtd=Math.min(prodQtd,productSelectedStock());$('qdVal').textContent=prodQtd;document.querySelectorAll('#sheetSizes .size-chip').forEach(b=>b.classList.toggle('on',b.textContent===tamanho));updateProductSheetPrice();}
async function addFromSheet(pid){
    if(sheetSaving)return;
    if(!productChoicesValid()){
        if(unavailableProductSelections().length){toast('Revise as opções indisponíveis e remova as que não podem mais ser selecionadas.','err');$('selectionWarnings')?.scrollIntoView({behavior:'smooth',block:'center'});$('selectionWarnings')?.querySelector('button')?.focus();return;}
        if(prodQtd>productSelectedStock()){toast('A quantidade excede o estoque disponível. Ajuste a quantidade.','err');return;}
        const missingSize=prodConfig?.usa_tamanhos&&!prodTamanho;
        const missing=(prodConfig?.grupos||[]).find(g=>productSelectionUnits(g)<Number(g.minimo||0));
        const target=missingSize?$('sizeGroup'):missing?document.querySelector(`[data-group-id=\"${missing.id}\"]`):null;
        toast(missingSize?'Escolha um tamanho.':`Complete o grupo obrigatório: ${missing?.nome||'opções'}.`,'err');target?.scrollIntoView({behavior:'smooth',block:'center'});target?.querySelector('input,button')?.focus();return;
    }
    if(editingCartItem){
        setProductSheetBusy(true);
        const fd=new FormData();fd.append('slug',SLUG);fd.append('carrinho_id',editingCartItem.id);fd.append('produto_id',pid);fd.append('quantidade',prodQtd);fd.append('tamanho',prodTamanho||'');fd.append('personalizacao',JSON.stringify(productSelectionsPayload()));fd.append('observacao_item',prodItemNote.slice(0,500));const draft=JSON.stringify([editingCartItem.id,pid,prodQtd,prodTamanho,productSelectionsPayload(),prodItemNote.slice(0,500)]);if(draft!==editingDraftSignature){editingDraftSignature=draft;editingRequestToken=cryptoId();}fd.append('request_token',editingRequestToken);
        try{const response=await fetch('vitrine_api.php?acao=carrinho_editar',{method:'POST',body:fd});const result=await response.json();if(!result.ok)throw new Error(result.error||'Não foi possível atualizar o item.');editingCartItem=null;editingRequestToken='';editingDraftSignature='';editingLegacyConfig=false;closeProdSheet(true);await updateCartCount();openCart();toast('Item atualizado no carrinho.');}catch(error){toast(error.message||'Erro ao atualizar item.','err');}finally{setProductSheetBusy(false);}
        return;
    }
    setProductSheetBusy(true);try{const added=await addToCart(pid,prodQtd,prodTamanho,productSelectionsPayload(),prodItemNote);if(added)closeProdSheet(true);}finally{setProductSheetBusy(false);}
}

/* ══════════════════════════════════════
   CARRINHO
══════════════════════════════════════ */
let cartCount=0,cartItemsSnapshot=[],cartSnapshotTotal=0,cartSnapshotFee=0;
const CART_STORAGE_KEY=`uplar:vitrine:${SLUG}:cart:v1`;

function updateCartNavigation(){
    const cardapio=document.body.dataset.experience==='cardapio';
    const label=cardapio?'Meu pedido':document.body.dataset.experience==='loja'?'Meu carrinho':'Carrinho';
    const title=$('cartTitle'),navLabel=$('cartNavLabel'),navMeta=$('cartNavMeta'),navButton=$('btnNavCart');
    if(title)title.textContent=label;
    if(navLabel)navLabel.textContent=label;
    if(navMeta){
        const hasItems=Number(cartCount)>0;
        const itemLabel=Number(cartCount)===1?'item':'itens';
        const total=Number(cartSnapshotTotal)+(typeof tipoEnt!=='undefined'&&tipoEnt==='entrega'?Number(cartSnapshotFee):0);
        navMeta.textContent=hasItems?`${cartCount} ${itemLabel} · ${R(total)}`:'';
        navMeta.hidden=!hasItems;
    }
    if(navButton){
        const count=Number(cartCount)||0;
        navButton.setAttribute('aria-label',count?`${label}, ${count} ${count===1?'item':'itens'}`:label);
    }
}

function readPersistentCart(){
    try{
        const raw=localStorage.getItem(CART_STORAGE_KEY);
        if(raw===null)return null;
        const items=JSON.parse(raw);
        if(!Array.isArray(items))return null;
        return items
            .map(item=>({produto_id:Number(item?.produto_id),tamanho:item?.tamanho?String(item.tamanho):null,quantidade:Number(item?.quantidade),personalizacao:Array.isArray(item?.personalizacao)?item.personalizacao:[],observacao_item:String(item?.observacao_item||'')}))
            .filter(item=>Number.isInteger(item.produto_id)&&item.produto_id>0&&Number.isInteger(item.quantidade)&&item.quantidade>0)
            .slice(0,100);
    }catch{return null;}
}

function savePersistentCart(itens){
    try{
        const items=(Array.isArray(itens)?itens:[]).map(item=>({
            produto_id:Number(item.produto_id),
            tamanho:item.tamanho||null,
            quantidade:Number(item.qtd_carrinho),
            personalizacao:(item.personalizacao?.grupos||[]).map(group=>({grupo_id:Number(group.grupo_id),opcoes:(group.opcoes||[]).map(option=>({opcao_id:Number(option.opcao_id),quantidade:Number(option.quantidade)}))})),
            observacao_item:String(item.personalizacao?.observacao||'')
        })).filter(item=>Number.isInteger(item.produto_id)&&item.produto_id>0&&Number.isInteger(item.quantidade)&&item.quantidade>0);
        localStorage.setItem(CART_STORAGE_KEY,JSON.stringify(items));
    }catch{}
}

async function restorePersistentCart(){
    const saved=readPersistentCart();
    if(saved===null)return;
    const fd=new FormData();
    fd.append('slug',SLUG);
    fd.append('itens',JSON.stringify(saved));
    try{
        const r=await fetch('vitrine_api.php?acao=carrinho_sincronizar',{method:'POST',body:fd});
        const j=await r.json();
        if(!j.ok)throw new Error(j.error||'sync');
    }catch(error){
        // Mantém a seleção local para que a pessoa possa revisar e tentar novamente.
        if(error?.message)toast(error.message,'err');
    }
}

async function addToCart(pid,qtd=1,tamanho=null,personalizacao=[],observacao=''){
    const btn=$('add-'+pid);
    if(btn)btn.disabled=true;
    const fd=new FormData();
    fd.append('slug',SLUG);fd.append('produto_id',pid);fd.append('quantidade',qtd);if(tamanho)fd.append('tamanho',tamanho);
    fd.append('personalizacao',JSON.stringify(Array.isArray(personalizacao)?personalizacao:[]));fd.append('observacao_item',observacao||'');
    try{
        const r=await fetch('vitrine_api.php?acao=carrinho_add',{method:'POST',body:fd});
        const j=await r.json();
        if(j.ok){
            storeTrack('add_to_cart',pid,qtd);
            toast('Adicionado ao carrinho!');
            if(btn){
                btn.classList.add('added');
                btn.innerHTML='<svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';
                setTimeout(()=>{btn.classList.remove('added');btn.innerHTML='<svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>';btn.disabled=false;},1500);
            }
            await updateCartCount();
            return true;
        } else {toast(j.error||'Erro','err');if(btn)btn.disabled=false;}
    }catch{toast('Erro de conexão','err');if(btn)btn.disabled=false;}
    return false;
}

async function updateCartCount(){
    try{
        const r=await fetch(`vitrine_api.php?acao=carrinho_get&slug=${encodeURIComponent(SLUG)}`);
        const j=await r.json();
        if(j.ok){
            savePersistentCart(j.itens);
            cartCount=j.count||0;
            cartSnapshotTotal=Number(j.total)||0;
            cartSnapshotFee=Number(j.valor_frete)||0;
            const b=$('cartBadge');b.textContent=cartCount;b.classList.toggle('on',cartCount>0);
            updateCartNavigation();
        }
    }catch{}
}

function openCart(){
    updateCartNavigation();
    storeTrack('cart_view');
    $('cartBg').classList.add('on');
    $('cartSheet').style.display='flex';
    document.body.style.overflow='hidden';activateSheet('cartSheet');
    loadCartSheet();
}
function closeCart(){$('cartBg').classList.remove('on');$('cartSheet').style.display='none';document.body.style.overflow='';restoreSheetFocus();}

async function loadCartSheet(){
    $('cartBody').innerHTML='<div style="text-align:center;padding:2rem;color:var(--faint);font-size:.83rem"><div class="spinner" style="margin:0 auto .8rem;border-color:var(--border);border-top-color:var(--p)"></div>Carregando…</div>';
    $('cartFoot').style.display='none';
    try{
        const r=await fetch(`vitrine_api.php?acao=carrinho_get&slug=${encodeURIComponent(SLUG)}`);
        const j=await r.json();
        if(!j.ok) throw new Error();
        const itens=j.itens||[];cartItemsSnapshot=itens.map(item=>({...item}));cartSnapshotTotal=Number(j.total)||0;cartSnapshotFee=Number(j.valor_frete)||0;cartCount=Number(j.count)||itens.reduce((sum,item)=>sum+Number(item.qtd_carrinho||0),0);updateCartNavigation();
        savePersistentCart(itens);
        if(!itens.length){
            $('cartBody').innerHTML='<div class="empty-state"><svg viewBox="0 0 24 24"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96C5 16.1 6.1 17 7 17h11v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63H18c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1 1 0 0022.46 4H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg><p>Seu carrinho está vazio.</p></div>';
            $('btnLimpar').style.display='none';
            return;
        }
        $('btnLimpar').style.display='';
        let html='';
        html+=`<div class="del-tabs" ${EXPERIENCE.retirada_habilitada===false||EXPERIENCE.entrega_habilitada===false?'hidden':''}>
            ${EXPERIENCE.retirada_habilitada!==false?`<button class="del-tab ${tipoEnt==='retirada'?'on':''}" id="tabRet" onclick="setTipo('retirada')">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M19 6h-2c0-2.76-2.24-5-5-5S7 3.24 7 6H5c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-7-3c1.66 0 3 1.34 3 3H9c0-1.66 1.34-3 3-3zm0 10c-2.21 0-4-1.79-4-4h2c0 1.1.9 2 2 2s2-.9 2-2h2c0 2.21-1.79 4-4 4z"/></svg>Retirada
            </button>`:''}
            ${EXPERIENCE.entrega_habilitada!==false?`<button class="del-tab ${tipoEnt==='entrega'?'on':''}" id="tabEnt" onclick="setTipo('entrega')">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.66 1.34 3 3 3s3-1.34 3-3h6c0 1.66 1.34 3 3 3s3-1.34 3-3h2v-5l-3-4z"/></svg>Entrega
            </button>`:''}
        </div><div id="tipoInfo">`;
        if(ENDERECO) html+=`<div class="address-info"><svg viewBox="0 0 24 24" width="32" height="32" fill="#ea4335"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg><div class="address-text"><strong>${esc(ENDERECO)}</strong>${CIDADE_UF?'<br>'+esc(CIDADE_UF):''}</div></div>`;
        html+=`<div class="eta-box"><div class="eta-label">Horário para retirada</div><div class="eta-val"><span class="eta-dot"></span>${esc(HOR_RET||'Consulte a loja')}</div></div></div>`;
        html+=`<div class="cart-section-title">Produtos selecionados</div><div id="cartItems">`;
        itens.forEach(i=>{
            const customLines=(i.personalizacao?.grupos||[]).map(group=>`${esc(group.nome)}: ${(group.opcoes||[]).map(option=>`${esc(option.nome)}${Number(option.quantidade)>1?` × ${Number(option.quantidade)}`:''}`).join(', ')}`).filter(Boolean);
            const itemNote=String(i.personalizacao?.observacao||'').trim();
            html+=`<div class="cart-item">
                <div class="ci-img">
                    ${i.imagem?`<img src="${esc(i.imagem)}" alt="">`:'<svg viewBox="0 0 24 24"><path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2z"/></svg>'}
                    <span class="ci-badge">${i.qtd_carrinho}</span>
                </div>
                <div class="ci-body">
                    <div class="ci-name">${esc(i.nome)}</div>
                    ${i.tamanho?`<div style="font-size:.7rem;color:var(--muted);margin-top:.12rem">Tamanho: ${esc(i.tamanho)}</div>`:''}
                    ${customLines.length?`<div class="cart-custom">${customLines.join('<br>')}</div>`:''}
                    ${itemNote?`<div class="cart-custom">Obs.: ${esc(itemNote)}</div>`:''}
                    ${i.revisao_requerida?`<div class="cart-review">${esc(i.aviso||'Revise as opções deste produto antes de finalizar.')}</div>`:''}
                    <div class="ci-price">${R(i.preco)}</div>
                    <button class="ci-edit" type="button" onclick="editarItemCarrinho(${Number(i.id)})">Editar opções</button>
                    <div class="ci-qty" aria-label="Quantidade de ${esc(i.nome)}">
                        <button type="button" aria-label="Diminuir quantidade" onclick="alterarQuantidade(${i.id},${i.qtd_carrinho},-1)">−</button>
                        <span>${i.qtd_carrinho}</span>
                        <button type="button" aria-label="Aumentar quantidade" onclick="alterarQuantidade(${i.id},${i.qtd_carrinho},1)" ${i.qtd_carrinho>=i.estoque?'disabled':''}>+</button>
                    </div>
                </div>
                <button class="ci-del" onclick="removerItem(${i.id})">
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                </button>
            </div>`;
        });
        html+=`</div><button class="add-more" onclick="closeCart()">+ Adicionar mais itens</button>`;
        html+=`<div class="totals"><div class="total-row"><span>Subtotal</span><span>${R(j.total)}</span></div>${tipoEnt==='entrega'&&cartSnapshotFee>0?`<div class="total-row"><span>Entrega</span><span>${R(cartSnapshotFee)}</span></div>`:''}<div class="total-row big"><span>Total</span><span>${R(Number(j.total)+(tipoEnt==='entrega'?cartSnapshotFee:0))}</span></div></div>`;
        $('cartBody').innerHTML=html;if(tipoEnt==='entrega')setTipo('entrega');
        $('cartReviewNotice').hidden=!j.revisao_requerida;
        $('cartReviewNotice').textContent=j.revisao_requerida?'Uma opção ou preço mudou. Remova o item e configure-o novamente para continuar.':'';
        $('cartReviewButton').disabled=!!j.revisao_requerida||EXPERIENCE.pedidos_habilitados===false||(EXPERIENCE.retirada_habilitada===false&&EXPERIENCE.entrega_habilitada===false); if(EXPERIENCE.pedidos_habilitados===false){$('cartReviewNotice').hidden=false;$('cartReviewNotice').textContent='A loja está temporariamente sem receber pedidos.';}
        $('cartFoot').style.display='';
    }catch{$('cartBody').innerHTML='<div class="empty-state"><p>Erro ao carregar carrinho.</p></div>';}
}

let tipoEnt=EXPERIENCE.retirada_habilitada!==false?'retirada':'entrega',deliveryAddress='';
function setTipo(tipo){
    if(tipo==='entrega'&&EXPERIENCE.entrega_habilitada===false)return;if(tipo==='retirada'&&EXPERIENCE.retirada_habilitada===false)return;
    tipoEnt=tipo;
    updateCartNavigation();
    const retTab=document.getElementById('tabRet'),entTab=document.getElementById('tabEnt');if(retTab)retTab.classList.toggle('on',tipo==='retirada');if(entTab)entTab.classList.toggle('on',tipo==='entrega');
    const info=document.getElementById('tipoInfo');
    if(!info)return;
    if(tipo==='retirada'){
        info.innerHTML=(ENDERECO?`<div class="address-info"><svg viewBox="0 0 24 24" width="32" height="32" fill="#ea4335"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5S10.62 6.5 12 6.5s2.5 1.12 2.5 2.5S13.38 11.5 12 11.5z"/></svg><div class="address-text"><strong>${esc(ENDERECO)}</strong>${CIDADE_UF?'<br>'+esc(CIDADE_UF):''}</div></div>`:'')+
        `<div class="eta-box"><div class="eta-label">Horário para retirada</div><div class="eta-val"><span class="eta-dot"></span>${esc(HOR_RET||'Consulte a loja')}</div></div>`;
    } else {
        info.innerHTML=`<div style="margin-bottom:.9rem"><div class="form-group"><label for="endEnt">Seu endereço de entrega</label><input id="endEnt" value="${esc(deliveryAddress)}" oninput="deliveryAddress=this.value" placeholder="Rua, número, bairro, cidade" type="text"></div></div>
        <div class="eta-box"><div class="eta-label">Horário para entrega</div><div class="eta-val"><span class="eta-dot" style="background:var(--warn)"></span>${esc(HOR_ENT||'Consulte disponibilidade')}</div></div>`;
    }
    updateCartTotals();
}
function updateCartTotals(){
    const totals=document.querySelector('#cartBody .totals');if(!totals)return;
    const fee=tipoEnt==='entrega'?cartSnapshotFee:0;
    totals.innerHTML=`<div class="total-row"><span>Subtotal</span><span>${R(cartSnapshotTotal)}</span></div>${fee>0?`<div class="total-row"><span>Entrega</span><span>${R(fee)}</span></div>`:''}<div class="total-row big"><span>Total</span><span>${R(cartSnapshotTotal+fee)}</span></div>`;
}

function editarItemCarrinho(cartId){
    const item=cartItemsSnapshot.find(line=>Number(line.id)===Number(cartId));
    if(!item)return toast('Atualize o carrinho antes de editar este item.','err');
    const product=produtos.find(p=>Number(p.id)===Number(item.produto_id));
    if(!product)return toast('Este produto não está disponível para edição.','err');
    const hasLegacyConfig=(item.personalizacao?.grupos||[]).some(g=>!Number(g.grupo_id)||(g.opcoes||[]).some(o=>!Number(o.opcao_id)));
    if(hasLegacyConfig){item.legacy_configuration=true;toast('Este item contém configuração antiga e será mantido para evitar perder opções.','err');}
    closeCart();$('cartBtn').focus();openProd(product,item);
}

async function removerItem(cid){
    const fd=new FormData();fd.append('slug',SLUG);fd.append('carrinho_id',cid);
    await fetch('vitrine_api.php?acao=carrinho_remover',{method:'POST',body:fd});
    await updateCartCount();loadCartSheet();
}

async function alterarQuantidade(cid,atual,variacao){
    const novaQuantidade=Number(atual)+Number(variacao);
    if(novaQuantidade<1){await removerItem(cid);return;}
    const fd=new FormData();fd.append('slug',SLUG);fd.append('carrinho_id',cid);fd.append('quantidade',novaQuantidade);
    try{
        const r=await fetch('vitrine_api.php?acao=carrinho_qtd',{method:'POST',body:fd});
        const j=await r.json();
        if(!j.ok){toast(j.error||'Não foi possível atualizar a quantidade','err');return;}
        if(Number(j.quantidade)<novaQuantidade)toast('Quantidade ajustada ao estoque disponível.','err');
        await updateCartCount();
        loadCartSheet();
    }catch{toast('Erro de conexão','err');}
}

async function limparCarrinho(){
    if(!confirm('Limpar carrinho?'))return;
    const fd=new FormData();fd.append('slug',SLUG);
    await fetch('vitrine_api.php?acao=carrinho_limpar',{method:'POST',body:fd});
    await updateCartCount();loadCartSheet();
}

/* ══════════════════════════════════════
   CHECKOUT
══════════════════════════════════════ */
function openCheckout(){
    if(EXPERIENCE.pedidos_habilitados===false){toast('A loja está temporariamente sem receber pedidos.','err');return;}
    closeCart();
    let endVal='';
    if(tipoEnt==='entrega'){
        const ei=document.getElementById('endEnt');
        endVal=ei?ei.value:deliveryAddress;deliveryAddress=endVal;
        if(!endVal){toast('Informe seu endereço de entrega','err');openCart();return;}
    }
    storeTrack('checkout_start');
    $('checkBg').classList.add('on');
    $('checkSheet').style.display='flex';
    document.body.style.overflow='hidden';activateSheet('checkSheet');
    $('checkBody').innerHTML=`
        <div class="checkout-review"><strong>Revise seu pedido</strong>${cartItemsSnapshot.map(i=>{const details=(i.personalizacao?.grupos||[]).map(g=>`${esc(g.nome)}: ${(g.opcoes||[]).map(o=>`${esc(o.nome)}${Number(o.quantidade)>1?' × '+Number(o.quantidade):''}`).join(', ')}`).join(' · ');const note=String(i.personalizacao?.observacao||'').trim();return `<div>${Number(i.qtd_carrinho)} × ${esc(i.nome)}${i.tamanho?' · Tamanho '+esc(i.tamanho):''}${details?' · '+details:''}${note?' · Obs.: '+esc(note):''}</div>`;}).join('')}<div class="checkout-total">Produtos ${R(cartSnapshotTotal)}${tipoEnt==='entrega'&&cartSnapshotFee>0?' · Entrega '+R(cartSnapshotFee):''}<br>Total: ${R(cartSnapshotTotal+(tipoEnt==='entrega'?cartSnapshotFee:0))}</div></div><div class="checkout-method">${tipoEnt==='retirada'?`Retirada · ${esc(ENDERECO||'Endereço da loja')} · ${esc(HOR_RET||'Consulte a loja')}`:`Entrega · ${esc(HOR_ENT||'Consulte a loja')}`}</div><div class="form-group"><label for="chkNome">Seu nome (opcional)</label><input id="chkNome" placeholder="Como devemos te chamar?" type="text"></div>
        <div class="form-group"><label for="chkTel">WhatsApp / Telefone (opcional)</label><input id="chkTel" placeholder="(99) 99999-9999" type="tel"></div>
        ${tipoEnt==='entrega'?`<div class="form-group"><label for="chkEnd">Endereço de entrega</label><input id="chkEnd" value="${esc(endVal)}" oninput="deliveryAddress=this.value" placeholder="Rua, número, bairro, cidade" type="text"></div>`:''}
        <div class="form-group"><label for="chkObs">Observações (opcional)</label><textarea id="chkObs" placeholder="Instruções para o pedido…"></textarea></div>`;
    if(cartItemsSnapshot.some(i=>i.revisao_requerida)){toast('Há itens que precisam ser revisados antes de finalizar.','err');closeCheckout();openCart();return;}
    $('checkFoot').innerHTML=`<button class="btn-main" id="btnConfirmar" onclick="confirmarPedido()">
        <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        Confirmar pedido via WhatsApp
    </button>`;
}
function closeCheckout(){$('checkBg').classList.remove('on');$('checkSheet').style.display='none';document.body.style.overflow='';restoreSheetFocus();}

async function confirmarPedido(){
    const btn=$('btnConfirmar');
    if(!btn||btn.disabled)return;
    btn.disabled=true;btn.innerHTML='<div class="spinner"></div> Processando…';
    const fd=new FormData();
    fd.append('slug',SLUG);fd.append('tipo',tipoEnt);
    fd.append('nome',$('chkNome')?.value||'');fd.append('tel',$('chkTel')?.value||'');
    fd.append('endereco',$('chkEnd')?.value||'');fd.append('obs',$('chkObs')?.value||'');
    try{
        const r=await fetch('vitrine_api.php?acao=fazer_pedido',{method:'POST',body:fd});
        const j=await r.json();
        if(!j.ok){toast(j.error||'Erro','err');btn.disabled=false;btn.innerHTML='Confirmar pedido via WhatsApp';return;}
        $('checkBody').innerHTML=`
            <div class="success-state">
                <div class="success-ico"><svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg></div>
                <div class="success-title">Pedido #${j.pedido_id} criado!</div>
                <div class="success-sub">Pedido registrado. Clique abaixo para enviar ao WhatsApp da loja.</div>
                ${j.whatsapp?`<a href="https://wa.me/${j.whatsapp}?text=${j.wa_msg}" target="_blank" rel="noopener noreferrer" class="btn-main" style="text-decoration:none;margin-bottom:.75rem">
                    <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                    Enviar pedido no WhatsApp
                </a>`:''}
                <button onclick="closeCheckout()" style="width:100%;padding:.7rem;border:1.5px solid var(--border);border-radius:11px;background:var(--card);color:var(--muted);font-size:.88rem;font-weight:600;cursor:pointer">Continuar comprando</button>
            </div>`;
        $('checkFoot').innerHTML='';
        savePersistentCart([]);deliveryAddress='';
        await updateCartCount();
    }catch{toast('Erro de conexão','err');btn.disabled=false;btn.innerHTML='Confirmar pedido via WhatsApp';}
}

function openPedidos(){
    if(EXPERIENCE.pedidos_habilitados===false){toast('A loja está temporariamente sem receber pedidos.','err');return;}
    document.querySelectorAll('.bnav-a').forEach(b=>b.classList.remove('on'));
    $('btnNavPedidos').classList.add('on');
    toast('Acompanhe seus pedidos pelo WhatsApp da loja ');
}

let focusReturnElement=null;
function activateSheet(sheetId){focusReturnElement=document.activeElement;requestAnimationFrame(()=>{$(sheetId)?.focus();const first=$(sheetId)?.querySelector('button:not(:disabled),input:not(:disabled),select:not(:disabled),textarea:not(:disabled),a[href]');first?.focus();});}
function closeOpenSheet(){if($('prodSheet').style.display==='flex')return closeProdSheet();if($('checkSheet').style.display==='flex')return closeCheckout();if($('cartSheet').style.display==='flex')return closeCart();}
document.addEventListener('keydown',event=>{
    const active=document.querySelector('.sheet[style*="display: flex"]');if(!active)return;
    if(event.key==='Escape'){event.preventDefault();closeOpenSheet();return;}
    if(event.key==='Tab'){
        const focusable=[...active.querySelectorAll('button:not(:disabled),input:not(:disabled),select:not(:disabled),textarea:not(:disabled),a[href],[tabindex="0"]')].filter(el=>el.offsetParent!==null);
        if(!focusable.length){event.preventDefault();active.focus();return;}
        const first=focusable[0],last=focusable[focusable.length-1];
        if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus();}
        else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus();}
    }
});
function restoreSheetFocus(){if(focusReturnElement?.isConnected)focusReturnElement.focus();focusReturnElement=null;}
window.visualViewport?.addEventListener('resize',()=>{const sheet=document.querySelector('.sheet[style*=\"display: flex\"]');if(!sheet)return;const height=Math.max(240,window.visualViewport.height);sheet.style.maxHeight=`${height}px`;const focused=sheet.contains(document.activeElement)?document.activeElement:null;if(focused&&['INPUT','TEXTAREA','SELECT'].includes(focused.tagName))setTimeout(()=>focused.scrollIntoView({block:'nearest',behavior:'smooth'}),50);});

document.addEventListener('DOMContentLoaded',()=>{
    carregarProdutos();restorePersistentCart().finally(updateCartCount);storeTrack('store_view');
    if(document.body.dataset.experience==='cardapio'){
        window.addEventListener('scroll',scheduleMenuCategorySync,{passive:true});
        window.addEventListener('resize',scheduleMenuCategorySync,{passive:true});
    }
    const header=document.querySelector('.topbar');if(header&&'ResizeObserver' in window)new ResizeObserver(()=>document.documentElement.style.setProperty('--store-header-height',header.getBoundingClientRect().height+'px')).observe(header);
    const nav=performance.getEntriesByType?.('navigation')?.[0];
    window.addEventListener('load',()=>storeTrack('page_load',null,Math.round(nav?.loadEventEnd||performance.now())),{once:true});
    document.addEventListener('click',e=>{if(e.target.closest('a[href*="wa.me"]'))storeTrack('whatsapp_click')},{passive:true});
    window.addEventListener('scroll',()=>{
        const max=Math.max(1,document.documentElement.scrollHeight-window.innerHeight);
        storeRuntime.maxScroll=Math.max(storeRuntime.maxScroll,Math.min(100,Math.round((window.scrollY/max)*100)));
    },{passive:true});
});
window.addEventListener('pagehide',()=>{
    if(storeRuntime.ended)return;storeRuntime.ended=true;
    storeTrack('scroll_depth',null,storeRuntime.maxScroll,true);
    storeTrack('session_end',null,Date.now()-storeRuntime.startedAt,true);
});
</script>
</body>
</html>

