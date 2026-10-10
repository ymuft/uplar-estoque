<?php
// pedidos.php — Gestão de pedidos da Vitrine
session_start();
require 'db.php';
require 'helpers.php';
require_once __DIR__ . '/includes/tracking_runtime.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$user_id  = (int)$_SESSION['user_id'];
$username = $_SESSION['username'];
$pdo      = get_pdo();

get_csrf_token();

$passcodeStmt = $pdo->prepare('SELECT passcode_hash FROM users WHERE id = ? LIMIT 1');
$passcodeStmt->execute([$user_id]);
$hasOrderPasscode = !empty($passcodeStmt->fetchColumn());

// Pedidos recentes
$pedidos = [];
try {
    $ps = $pdo->prepare("
        SELECT id, cliente_nome, cliente_tel, total, valor_frete, status,
               tipo_entrega, endereco_ent,
               DATE_FORMAT(criado_em,'%d/%m %H:%i') AS data_fmt,
               itens_json
        FROM vitrine_pedidos WHERE user_id = ?
        ORDER BY criado_em DESC LIMIT 50
    ");
    $ps->execute([$user_id]);
    $pedidos = $ps->fetchAll(PDO::FETCH_ASSOC);
    foreach ($pedidos as &$p) {
        $p['itens_json'] = json_decode($p['itens_json'], true) ?? [];
    }
    unset($p);
} catch (Throwable $e) {}

$pend_count = count(array_filter($pedidos, fn($p) => $p['status']==='pendente'));
$confirm_count = count(array_filter($pedidos, fn($p) => $p['status']==='confirmado'));
$cancel_count = count(array_filter($pedidos, fn($p) => $p['status']==='cancelado'));
$orders_value = array_sum(array_map(static fn($p) => (float)($p['total'] ?? 0), $pedidos));

// Coleta ids de produto usados nos itens para buscar as fotos
$produto_ids = [];
foreach ($pedidos as $p) {
    foreach ($p['itens_json'] as $it) {
        $pid_item = $it['produto_id'] ?? $it['id'] ?? null;
        if ($pid_item) $produto_ids[] = (int)$pid_item;
    }
}
$produto_ids = array_unique($produto_ids);

$imagens_map = [];
if ($produto_ids) {
    $in = implode(',', array_fill(0, count($produto_ids), '?'));
    try {
        $qi = $pdo->prepare("SELECT id, imagem FROM produtos WHERE id IN ($in)");
        $qi->execute(array_values($produto_ids));
        foreach ($qi->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $imagens_map[$row['id']] = $row['imagem'];
        }
    } catch (Throwable $e) {}
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pedidos — Uplar</title>
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

/* ── Card ── */
.card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:1.2rem 1.3rem;margin-bottom:1rem}
.card-title{font-size:.88rem;font-weight:700;margin-bottom:1rem;padding-bottom:.7rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:.4rem}
.card-title .material-symbols-outlined{font-size:1rem;color:var(--p)}

/* ── Pedidos ── */
.pedidos-filters{display:flex;gap:.5rem;margin-bottom:.9rem;flex-wrap:wrap}
.filter-btn{padding:.45rem .85rem;border-radius:20px;border:1.5px solid var(--border);font-size:.75rem;font-weight:600;background:var(--card);color:var(--muted);cursor:pointer;transition:all .18s}
.filter-btn:hover{border-color:var(--p);color:var(--p)}
.filter-btn.on{background:var(--p-dim);border-color:var(--p);color:var(--p)}
.pedido-card{background:var(--card);border:1px solid var(--border);border-radius:12px;overflow:hidden;margin-bottom:.75rem;transition:box-shadow .18s}
.pedido-card:hover{box-shadow:var(--sh-lift)}
.pedido-head{display:flex;align-items:center;gap:.75rem;padding:.8rem 1rem;border-bottom:1px solid var(--border);flex-wrap:wrap}
.pedido-id{font-size:.85rem;font-weight:800;flex-shrink:0}
.pedido-meta{font-size:.72rem;color:var(--muted);flex:1}
.pedido-tipo{font-size:.72rem;padding:.2rem .55rem;border-radius:20px;font-weight:700}
.pedido-tipo.retirada{background:var(--p-dim);color:var(--p)}
.pedido-tipo.entrega{background:var(--violet-dim);color:var(--violet)}
.pedido-status-sel{padding:.3rem .65rem;border:1.5px solid var(--border);border-radius:8px;font-size:.72rem;font-family:inherit;outline:none;cursor:pointer;transition:border-color .18s;background:var(--card)}
.pedido-status-sel:focus{border-color:var(--p)}
.pedido-print-btn{display:inline-flex;align-items:center;gap:.3rem;margin-top:.65rem;padding:.38rem .62rem;border:1px solid var(--border);border-radius:8px;background:var(--card);color:var(--p);font-size:.7rem;font-weight:700;transition:background .18s,border-color .18s}.pedido-print-btn:hover{background:var(--p-dim);border-color:var(--p)}.pedido-print-btn .material-symbols-outlined{font-size:.95rem}
.pedido-body{padding:.8rem 1rem}
.pedido-cliente-row{display:flex;align-items:center;gap:.55rem;flex-wrap:wrap;margin-bottom:.35rem}
.pedido-cliente{font-size:.82rem;font-weight:600}
.cliente-add-btn{display:inline-flex;align-items:center;gap:.25rem;padding:.25rem .5rem;border:1px solid rgba(59,111,245,.28);border-radius:7px;background:var(--p-dim);color:var(--p);font-size:.68rem;font-weight:700;transition:background .18s,border-color .18s}
.cliente-add-btn:hover{background:rgba(59,111,245,.14);border-color:var(--p)}
.cliente-add-btn .material-symbols-outlined{font-size:.9rem}
.pedido-endereco{font-size:.73rem;color:var(--muted);margin-bottom:.5rem;display:flex;align-items:flex-start;gap:.3rem}
.pedido-endereco .material-symbols-outlined{font-size:.85rem;flex-shrink:0;color:var(--violet);margin-top:.1rem}
.pedido-itens-summary{display:flex;align-items:center;justify-content:space-between;font-size:.75rem;color:var(--muted);margin-bottom:.4rem}
.pedido-toggle-btn{background:none;border:none;color:var(--p);font-size:.72rem;font-weight:700;display:inline-flex;align-items:center;gap:.15rem;cursor:pointer;padding:.2rem .3rem}
.pedido-toggle-btn:hover{text-decoration:underline}
.pedido-toggle-btn .material-symbols-outlined{font-size:1rem;transition:transform .2s}
.pedido-toggle-btn.open .material-symbols-outlined{transform:rotate(180deg)}
.pedido-itens-full{display:none;flex-direction:column;gap:.5rem;margin-bottom:.5rem;padding-top:.2rem}
.pedido-item-row{display:flex;align-items:center;gap:.6rem}
.pedido-item-img{width:36px;height:36px;border-radius:8px;background:var(--bg);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0}
.pedido-item-img.clicavel{cursor:pointer;transition:transform .15s,box-shadow .15s}
.pedido-item-img.clicavel:hover{transform:scale(1.08);box-shadow:0 2px 8px rgba(15,28,46,.18)}
.pedido-item-img img{width:100%;height:100%;object-fit:cover}
.pedido-item-img .material-symbols-outlined{font-size:1.1rem;color:var(--faint)}
.img-modal{display:none;position:fixed;inset:0;background:rgba(15,28,46,.75);z-index:9998;align-items:center;justify-content:center;padding:1.5rem;backdrop-filter:blur(2px)}
.img-modal.on{display:flex}
.img-modal-box{position:relative;max-width:min(90vw,480px);max-height:85vh;display:flex;flex-direction:column;align-items:center}
.img-modal-box img{max-width:100%;max-height:75vh;border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.4);background:#fff;object-fit:contain}
.img-modal-nome{color:#fff;font-size:.85rem;font-weight:600;margin-top:.7rem;text-align:center}
.img-modal-close{position:absolute;top:-14px;right:-14px;width:32px;height:32px;border-radius:50%;background:#fff;border:none;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 3px 10px rgba(0,0,0,.25)}
.img-modal-close .material-symbols-outlined{font-size:1.15rem;color:var(--ink)}
@media(max-width:640px){.img-modal-box{max-width:92vw}.img-modal-close{top:-12px;right:0}}
.pedido-item-info{flex:1;min-width:0;display:flex;flex-direction:column}
.pedido-item-nome{font-size:.78rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.pedido-item-qtd{font-size:.68rem;color:var(--faint)}
.pedido-item-custom{font-size:.68rem;color:var(--muted);overflow-wrap:anywhere}
.pedido-item-subtotal{font-size:.78rem;font-weight:700;color:var(--ink);flex-shrink:0}
.pedido-total{font-size:.83rem;font-weight:700;display:flex;justify-content:space-between;align-items:flex-start;padding-top:.5rem;border-top:1px solid var(--border);margin-top:.4rem;gap:.5rem}
.pedido-frete{font-size:.75rem;color:var(--violet);font-weight:600;display:flex;align-items:center;gap:.3rem;flex-wrap:wrap}
.pedido-frete .material-symbols-outlined{font-size:.85rem}
.frete-edit-btn{background:none;border:none;padding:.1rem;cursor:pointer;color:var(--faint);display:inline-flex;align-items:center;transition:color .15s;line-height:1}
.frete-edit-btn:hover{color:var(--p)}
.frete-edit-btn .material-symbols-outlined{font-size:.85rem}
.frete-add{color:var(--p) !important;opacity:.7}.frete-add:hover{opacity:1}
.frete-inp{width:80px;padding:.32rem .55rem;border:1.5px solid var(--p);border-radius:7px;font-size:.8rem;font-family:inherit;outline:none;text-align:right;-moz-appearance:textfield}
.frete-inp::-webkit-outer-spin-button,.frete-inp::-webkit-inner-spin-button{-webkit-appearance:none}
.frete-ok{background:var(--ok);color:#fff;border:none;border-radius:7px;padding:.32rem .5rem;cursor:pointer;display:inline-flex;align-items:center;transition:background .15s}
.frete-ok:hover{background:#059669}
.frete-ok .material-symbols-outlined{font-size:.9rem}
.frete-cancel{background:var(--bg);color:var(--muted);border:1px solid var(--border);border-radius:7px;padding:.32rem .5rem;cursor:pointer;display:inline-flex;align-items:center;transition:all .15s}
.frete-cancel:hover{color:var(--err);border-color:var(--err)}
.frete-cancel .material-symbols-outlined{font-size:.9rem}
.pedidos-empty{text-align:center;padding:3rem 1rem;color:var(--faint)}
.pedidos-empty .material-symbols-outlined{font-size:2.5rem;display:block;margin-bottom:.6rem;opacity:.2}

/* ── Workspace operacional ── */
.orders-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem;margin-bottom:1rem}.order-kpi{display:flex;align-items:center;gap:.7rem;min-width:0;padding:.9rem 1rem;background:var(--card);border:1px solid var(--border);border-radius:12px;box-shadow:var(--sh)}.order-kpi-icon{width:34px;height:34px;display:grid;place-items:center;flex:0 0 34px;border-radius:9px;background:var(--p-dim);color:var(--p)}.order-kpi-icon .material-symbols-outlined{font-size:1.05rem}.order-kpi.warn .order-kpi-icon{background:var(--warn-dim);color:var(--warn)}.order-kpi.ok .order-kpi-icon{background:var(--ok-dim);color:var(--ok)}.order-kpi.muted .order-kpi-icon{background:var(--bg);color:var(--faint)}.order-kpi-copy{min-width:0}.order-kpi-label{display:block;color:var(--muted);font-size:.65rem;font-weight:650;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.order-kpi-value{display:block;margin-top:.1rem;font-size:1.05rem;font-weight:800;letter-spacing:-.02em}.orders-workspace{padding:1rem 1.1rem}.orders-toolbar{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;margin-bottom:1rem;padding-bottom:1rem;border-bottom:1px solid var(--border)}.orders-toolbar-title{display:flex;align-items:flex-start;gap:.55rem}.orders-toolbar-title>.material-symbols-outlined{width:32px;height:32px;display:grid;place-items:center;border-radius:8px;background:var(--p-dim);color:var(--p);font-size:1rem}.orders-toolbar-title strong{display:block;font-size:.9rem}.orders-toolbar-title span{display:block;margin-top:.1rem;color:var(--muted);font-size:.69rem}.orders-toolbar-actions{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;justify-content:flex-end}.orders-search{width:min(260px,100%);padding:.55rem .75rem .55rem 2.1rem;border:1.5px solid var(--border);border-radius:9px;background:var(--card);color:var(--ink);font:inherit;font-size:.74rem;outline:none;transition:border-color .18s,box-shadow .18s}.orders-search:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}.orders-search-wrap{position:relative}.orders-search-wrap>.material-symbols-outlined{position:absolute;left:.68rem;top:50%;transform:translateY(-50%);color:var(--faint);font-size:1rem;pointer-events:none}.pedidos-filters{margin:0;display:flex;gap:.35rem;padding:.25rem;background:var(--bg);border:1px solid var(--border);border-radius:9px}.filter-btn{padding:.42rem .68rem;border:0;border-radius:7px;font-size:.68rem;font-weight:700;background:transparent;color:var(--muted)}.filter-btn.on{background:var(--card);border-color:transparent;color:var(--p);box-shadow:0 1px 4px rgba(15,28,46,.08)}.pedido-card{border-radius:12px;margin-bottom:.7rem;border-left:3px solid var(--border)}.pedido-card[data-status="pendente"]{border-left-color:var(--warn)}.pedido-card[data-status="confirmado"]{border-left-color:var(--ok)}.pedido-card[data-status="cancelado"]{border-left-color:var(--faint);opacity:.86}.pedido-head{padding:.85rem 1rem;background:linear-gradient(180deg,var(--card),rgba(244,246,251,.45))}.pedido-head-main{display:flex;align-items:center;gap:.65rem;flex:1;min-width:180px}.pedido-id{font-size:.78rem}.pedido-meta{font-size:.68rem}.pedido-head-actions{display:flex;align-items:center;gap:.45rem;margin-left:auto}.pedido-status-sel{min-height:32px;padding:.28rem .55rem}.pedido-body{padding:1rem}.pedido-cliente-row{margin-bottom:.45rem}.pedido-cliente{font-size:.84rem}.pedido-itens-summary{padding:.5rem .6rem;border-radius:8px;background:var(--bg)}.pedido-total{margin-top:.7rem;padding-top:.7rem}.pedido-item-row{padding:.35rem 0}.pedido-item-img{width:40px;height:40px}.pedido-empty-filter{display:none;padding:2.2rem 1rem;text-align:center;color:var(--faint)}.pedido-empty-filter .material-symbols-outlined{display:block;margin-bottom:.35rem;font-size:2rem;opacity:.25}

/* ── Polimento dos elementos de pedido ── */
.pedido-tipo{display:inline-flex;align-items:center;gap:.25rem}.pedido-tipo .material-symbols-outlined{font-size:.85rem}
.pedido-card,.pedido-card[data-status="pendente"],.pedido-card[data-status="confirmado"],.pedido-card[data-status="cancelado"]{border-left:1px solid var(--border);opacity:1}

/* ── Toast ── */
.tw{position:fixed;bottom:1.2rem;right:1.2rem;z-index:9999;display:flex;flex-direction:column;gap:.5rem;pointer-events:none}
.toast{background:var(--ink);color:#fff;padding:.7rem 1.1rem;border-radius:10px;font-size:.82rem;font-weight:500;display:flex;align-items:center;gap:.5rem;box-shadow:0 8px 24px rgba(0,0,0,.2);pointer-events:auto;animation:fadeUp .35s cubic-bezier(.22,1,.36,1) both}
.toast .material-symbols-outlined{font-size:1rem}
.toast.ok{background:var(--ok)}.toast.err{background:var(--err)}

/* ── Confirmação por Pass Code ── */
.order-passcode-modal{display:none;position:fixed;inset:0;z-index:10020;align-items:center;justify-content:center;padding:1rem;background:rgba(6,26,70,.48);backdrop-filter:blur(4px)}.order-passcode-modal.on{display:flex}.order-passcode-box{width:min(100%,400px);padding:1.5rem;border:1px solid var(--border);border-radius:16px;background:var(--card);box-shadow:0 24px 64px rgba(12,38,79,.22);text-align:center;animation:fadeUp .2s ease-out}.order-passcode-icon{display:grid;place-items:center;width:42px;height:42px;margin:0 auto .75rem;border-radius:12px;background:var(--p-dim);color:var(--p)}.order-passcode-icon .material-symbols-outlined{font-size:1.35rem}.order-passcode-box h2{font-size:1.08rem;letter-spacing:-.03em}.order-passcode-box p{margin:.35rem 0 1rem;color:var(--muted);font-size:.78rem}.order-passcode-pin{position:relative;max-width:320px;margin:0 auto}.order-passcode-input{position:absolute;inset:0;width:100%;height:54px;opacity:.01;cursor:text;z-index:2}.order-passcode-slots{display:grid;grid-template-columns:repeat(6,1fr);gap:7px}.order-passcode-slots span{height:54px;display:grid;place-items:center;border:1px solid var(--border);border-radius:9px;background:#fff;transition:.16s}.order-passcode-slots span.filled:after{content:'•';font-size:1.45rem;color:var(--ink)}.order-passcode-pin.focused .order-passcode-slots span.active{border-color:var(--p);background:var(--p-dim);box-shadow:0 0 0 3px var(--p-glow)}.order-passcode-pin.error .order-passcode-slots{animation:pcShake .25s ease-in-out}.order-passcode-pin.error .order-passcode-slots span{border-color:var(--err);background:#fffafa}.order-passcode-pin.verifying .order-passcode-slots span{border-color:var(--p);background:var(--p-dim)}.order-passcode-error{min-height:18px;margin-top:.7rem;color:var(--err);font-size:.72rem;font-weight:700}.order-passcode-actions{display:flex;gap:.6rem;margin-top:1rem}.order-passcode-actions button{flex:1;min-height:40px;border-radius:9px;font:700 .78rem Inter,sans-serif}.order-passcode-cancel{border:1px solid var(--border);background:var(--card);color:var(--muted)}.order-passcode-confirm{border:0;background:var(--p);color:#fff}.order-passcode-confirm:disabled{opacity:.6;cursor:wait}@keyframes pcShake{25%{transform:translateX(-4px)}75%{transform:translateX(4px)}}

/* ── Bottom nav mobile ── */
.bnav{display:none;position:fixed;bottom:0;left:0;right:0;background:var(--card);border-top:1px solid var(--border);z-index:300;padding:.35rem 0 calc(.35rem + env(safe-area-inset-bottom));box-shadow:0 -2px 16px rgba(15,28,46,.07)}
.bnav-inner{display:flex;justify-content:space-around}
.bnav-a{display:flex;flex-direction:column;align-items:center;gap:2px;padding:.35rem .6rem;color:var(--faint);font-size:.62rem;font-weight:600;transition:all .18s;flex:1}
.bnav-a .material-symbols-outlined{font-size:1.25rem}
.bnav-a.on{color:var(--p)}

/* ── Responsive ── */
html[data-theme="dark"] .orders-summary .order-kpi,
html[data-theme="dark"] .orders-workspace,
html[data-theme="dark"] .pedido-card{box-shadow:0 1px 3px rgba(0,0,0,.24),0 8px 24px rgba(0,0,0,.14)}
html[data-theme="dark"] .pedido-card:hover{box-shadow:0 10px 28px rgba(0,0,0,.22)}
.pedido-head{background:linear-gradient(180deg,var(--card),var(--surface-soft))}
html[data-theme="dark"] .pedido-head{background:var(--card)}
@media(max-width:860px){.sb{transform:translateX(-100%)}.sb.open{transform:translateX(0)}.main{margin-left:0}.ham{display:flex}}
@media(max-width:980px){.orders-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.orders-toolbar{flex-direction:column}.orders-toolbar-actions{width:100%;justify-content:stretch}.orders-search-wrap,.orders-search{width:100%}.pedidos-filters{width:100%}.filter-btn{flex:1}}
@media(max-width:640px){.bnav{display:block}.main{padding:.9rem .9rem calc(68px + env(safe-area-inset-bottom))}.orders-summary{gap:.55rem}.order-kpi{padding:.75rem .7rem}.order-kpi-icon{width:30px;height:30px;flex-basis:30px}.order-kpi-label{font-size:.59rem}.order-kpi-value{font-size:.92rem}.orders-workspace{padding:.8rem}.pedido-head{align-items:flex-start}.pedido-head-main{flex-wrap:wrap}.pedido-head-actions{width:100%;margin-left:0;justify-content:space-between}.pedido-status-sel{flex:1}.pedido-tipo{font-size:.65rem}.pedido-body{padding:.85rem}.pedido-total{align-items:flex-start}.pedido-total>strong{font-size:.82rem!important}.pedidos-filters{overflow:auto}.filter-btn{white-space:nowrap;padding:.45rem .55rem}}
</style>
<link rel="stylesheet" href="assets/app-theme.css?v=20260723">
<script src="assets/app-theme.js?v=20260723"></script>
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
                <div class="greeting"> Pedidos</div>
                <div class="sub-txt">Acompanhe e gerencie os pedidos da sua vitrine</div>
            </div>
        </div>
    </div>

    <div class="orders-summary anim d2">
        <div class="order-kpi"><div class="order-kpi-icon"><span class="material-symbols-outlined">receipt_long</span></div><div class="order-kpi-copy"><span class="order-kpi-label">Pedidos recentes</span><strong class="order-kpi-value" id="orderStatTotal"><?= count($pedidos) ?></strong></div></div>
        <div class="order-kpi warn"><div class="order-kpi-icon"><span class="material-symbols-outlined">schedule</span></div><div class="order-kpi-copy"><span class="order-kpi-label">Aguardando ação</span><strong class="order-kpi-value" id="orderStatPending"><?= $pend_count ?></strong></div></div>
        <div class="order-kpi ok"><div class="order-kpi-icon"><span class="material-symbols-outlined">task_alt</span></div><div class="order-kpi-copy"><span class="order-kpi-label">Confirmados</span><strong class="order-kpi-value" id="orderStatConfirmed"><?= $confirm_count ?></strong></div></div>
        <div class="order-kpi muted"><div class="order-kpi-icon"><span class="material-symbols-outlined">payments</span></div><div class="order-kpi-copy"><span class="order-kpi-label">Valor dos pedidos</span><strong class="order-kpi-value" id="orderStatValue">R$ <?= number_format($orders_value, 2, ',', '.') ?></strong></div></div>
    </div>

    <div class="card orders-workspace anim d3">
        <div class="orders-toolbar">
            <div class="orders-toolbar-title">
                <span class="material-symbols-outlined">inbox</span>
                <div><strong>Central de pedidos</strong><span>Priorize o que precisa da sua atenção e acompanhe cada etapa.</span></div>
            </div>
            <div class="orders-toolbar-actions">
                <label class="orders-search-wrap" aria-label="Buscar pedidos">
                    <span class="material-symbols-outlined">search</span>
                    <input class="orders-search" id="pedidoSearch" type="search" placeholder="Buscar cliente ou pedido…" oninput="filtrarBusca(this.value)">
                </label>
                <div class="pedidos-filters" role="group" aria-label="Filtrar pedidos">
                    <button class="filter-btn on" data-filter="todos" onclick="filtrarPedidos('todos',this)">Todos</button>
                    <button class="filter-btn" data-filter="pendente" onclick="filtrarPedidos('pendente',this)">Pendentes</button>
                    <button class="filter-btn" data-filter="confirmado" onclick="filtrarPedidos('confirmado',this)">Confirmados</button>
                    <button class="filter-btn" data-filter="cancelado" onclick="filtrarPedidos('cancelado',this)">Cancelados</button>
                </div>
            </div>
        </div>

        <div id="pedidosList">
            <?php if (empty($pedidos)): ?>
            <div class="pedidos-empty">
                <span class="material-symbols-outlined">receipt_long</span>
                Nenhum pedido ainda. Compartilhe o link da vitrine!
            </div>
            <?php else: ?>
            <?php foreach ($pedidos as $p):
                $cliente_novo_url = 'cliente.php?' . http_build_query([
                    'novo'     => '1',
                    'nome'     => (string)($p['cliente_nome'] ?? ''),
                    'telefone' => (string)($p['cliente_tel'] ?? ''),
                    'endereco' => (string)($p['endereco_ent'] ?? ''),
                ], '', '&', PHP_QUERY_RFC3986);
            ?>
            <div class="pedido-card" data-status="<?= $p['status'] ?>" data-search="<?= htmlspecialchars(mb_strtolower('#'.$p['id'].' '.($p['cliente_nome'] ?? '').' '.($p['cliente_tel'] ?? ''))) ?>">
                <div class="pedido-head">
                    <div class="pedido-head-main"><span class="pedido-id">Pedido #<?= $p['id'] ?></span><div class="pedido-meta">
                        <?= $p['data_fmt'] ?>
                        <?php if ($p['cliente_tel']): ?>
                        · <?= htmlspecialchars($p['cliente_tel']) ?>
                        <?php endif; ?>
                    </div></div>
                    <div class="pedido-head-actions"><span class="pedido-tipo <?= $p['tipo_entrega'] ?>"><span class="material-symbols-outlined"><?= $p['tipo_entrega'] === 'retirada' ? 'storefront' : 'local_shipping' ?></span><?= $p['tipo_entrega'] === 'retirada' ? 'Retirada' : 'Entrega' ?></span><select class="pedido-status-sel" onchange="atualizarStatus(<?= $p['id'] ?>,this.value,this)">
                        <option value="pendente"   <?= $p['status']==='pendente'  ?'selected':'' ?>>Pendente</option>
                        <option value="confirmado" <?= $p['status']==='confirmado'?'selected':'' ?>> Confirmado</option>
                        <option value="cancelado"  <?= $p['status']==='cancelado' ?'selected':'' ?>> Cancelado</option>
                    </select></div>
                </div>
                <div class="pedido-body">
                    <?php if ($p['cliente_nome']): ?>
                    <div class="pedido-cliente-row">
                        <div class="pedido-cliente"> <?= htmlspecialchars($p['cliente_nome']) ?></div>
                        <a class="cliente-add-btn" href="<?= htmlspecialchars($cliente_novo_url, ENT_QUOTES, 'UTF-8') ?>">
                            <span class="material-symbols-outlined">person_add</span>
                            Cadastrar cliente
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php if ($p['tipo_entrega']==='entrega' && $p['endereco_ent']): ?>
                    <div class="pedido-endereco">
                        <span class="material-symbols-outlined">location_on</span>
                        <?= htmlspecialchars($p['endereco_ent']) ?>
                    </div>
                    <?php endif; ?>
                    <?php $qtd_itens = count($p['itens_json']); ?>
                    <div class="pedido-itens-summary">
                        <span><?= $qtd_itens ?> <?= $qtd_itens===1?'item':'itens' ?></span>
                        <button class="pedido-toggle-btn" onclick="toggleDetalhes(<?= $p['id'] ?>,this)">
                            <span class="toggle-label">Mostrar mais</span>
                            <span class="material-symbols-outlined">expand_more</span>
                        </button>
                    </div>
                    <div class="pedido-itens-full" id="itensFull-<?= $p['id'] ?>">
                        <?php foreach ($p['itens_json'] as $it):
                            $item_pid = $it['produto_id'] ?? $it['id'] ?? null;
                            $img = $it['imagem'] ?? (($item_pid && isset($imagens_map[$item_pid])) ? $imagens_map[$item_pid] : null);
                        ?>
                        <div class="pedido-item-row">
                            <div class="pedido-item-img <?= $img?'clicavel':'' ?>"
                                 <?php if ($img): ?>data-expand-image="1" data-image-src="<?= htmlspecialchars($img, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" data-image-name="<?= htmlspecialchars((string)$it['nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"<?php endif; ?>>
                                <?php if ($img): ?>
                                <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($it['nome']) ?>" loading="lazy"
                                     onerror="this.parentElement.classList.remove('clicavel');this.parentElement.removeAttribute('data-expand-image');this.parentElement.removeAttribute('data-image-src');this.parentElement.removeAttribute('data-image-name');this.parentElement.innerHTML='<span class=\'material-symbols-outlined\'>inventory_2</span>'">
                                <?php else: ?>
                                <span class="material-symbols-outlined">inventory_2</span>
                                <?php endif; ?>
                            </div>
                            <div class="pedido-item-info">
                                <span class="pedido-item-nome"><?= htmlspecialchars($it['nome']) ?></span>
                                <span class="pedido-item-qtd">×<?= $it['quantidade'] ?></span>
                                <?php if (!empty($it['tamanho'])): ?><span class="pedido-item-custom">Tamanho: <?= htmlspecialchars((string)$it['tamanho'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                                <?php
                                    $personalizacao = is_array($it['personalizacao'] ?? null) ? $it['personalizacao'] : [];
                                    $grupos_personalizacao = is_array($personalizacao['grupos'] ?? null) ? $personalizacao['grupos'] : [];
                                    foreach ($grupos_personalizacao as $grupo_personalizacao):
                                        if (!is_array($grupo_personalizacao)) continue;
                                        $opcoes_personalizacao = is_array($grupo_personalizacao['opcoes'] ?? null) ? $grupo_personalizacao['opcoes'] : [];
                                        if (!$opcoes_personalizacao) continue;
                                ?>
                                    <span class="pedido-item-custom"><strong><?= htmlspecialchars((string)($grupo_personalizacao['nome'] ?? 'Personalização'), ENT_QUOTES, 'UTF-8') ?>:</strong>
                                        <?= htmlspecialchars(implode(', ', array_map(static function ($option) {
                                            $name = trim((string)($option['nome'] ?? ''));
                                            $quantity = max(1, (int)($option['quantidade'] ?? 1));
                                            return $name . ($quantity > 1 ? ' × ' . $quantity : '');
                                        }, array_filter($opcoes_personalizacao, 'is_array'))), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <?php endforeach; ?>
                                <?php $observacao_item = trim((string)($it['observacao_item'] ?? $personalizacao['observacao'] ?? '')); ?>
                                <?php if ($observacao_item !== ''): ?><span class="pedido-item-custom"><strong>Observação:</strong> <?= htmlspecialchars($observacao_item, ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                            </div>
                            <span class="pedido-item-subtotal">R$ <?= number_format($it['subtotal'], 2, ',', '.') ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="pedido-total">
                        <div>
                            <?php if ($p['tipo_entrega']==='entrega'): ?>
                            <?php $vf = (float)($p['valor_frete'] ?? 0); ?>
                            <div class="pedido-frete">
                                <span class="material-symbols-outlined">local_shipping</span>
                                Frete:
                                <?php if ($vf > 0): ?>
                                    <strong>R$ <?= number_format($vf, 2, ',', '.') ?></strong>
                                    <button class="frete-edit-btn" title="Editar frete"
                                            onclick="editarFrete(<?= $p['id'] ?>, <?= $vf ?>, this)">
                                        <span class="material-symbols-outlined">edit</span>
                                    </button>
                                <?php else: ?>
                                    <span style="color:var(--faint);font-style:italic">não informado</span>
                                    <button class="frete-edit-btn frete-add" title="Informar frete"
                                            onclick="editarFrete(<?= $p['id'] ?>, 0, this)">
                                        <span class="material-symbols-outlined">add_circle</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                            <!-- Inline frete editor (hidden) -->
                            <div class="frete-form" id="freteForm-<?= $p['id'] ?>" style="display:none">
                                <div style="display:flex;align-items:center;gap:.4rem;margin-top:.35rem">
                                    <span style="font-size:.75rem;color:var(--muted);font-weight:600">R$</span>
                                    <input type="number" id="freteVal-<?= $p['id'] ?>"
                                           value="<?= $vf > 0 ? number_format($vf,2,'.',''): '' ?>"
                                           min="0" step="0.50" placeholder="0,00"
                                           class="frete-inp"
                                           onkeydown="if(event.key==='Enter')salvarFrete(<?= $p['id'] ?>,this)">
                                    <button class="frete-ok" onclick="salvarFrete(<?= $p['id'] ?>,document.getElementById('freteVal-<?= $p['id'] ?>'))">
                                        <span class="material-symbols-outlined">check</span>
                                    </button>
                                    <button class="frete-cancel" onclick="cancelarFrete(<?= $p['id'] ?>)">
                                        <span class="material-symbols-outlined">close</span>
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <strong style="font-size:.9rem" id="pedTotal-<?= $p['id'] ?>">Total: R$ <?= number_format($p['total'], 2, ',', '.') ?></strong>
                    </div>
                    <?php if ($p['status'] === 'confirmado'): ?>
                    <a class="pedido-print-btn" href="pedido_imprimir.php?id=<?= (int)$p['id'] ?>&amp;auto=1" target="_blank" rel="noopener">
                        <span class="material-symbols-outlined" aria-hidden="true">print</span>Imprimir pedido
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="pedido-empty-filter" id="pedidoEmptyFilter"><span class="material-symbols-outlined">search_off</span>Nenhum pedido corresponde à busca ou filtro selecionado.</div>
    </div>

</main>
</div><!-- .app -->

<nav class="bnav"><div class="bnav-inner">
    <a href="estoque.php"        class="bnav-a"><span class="material-symbols-outlined">dashboard</span>Início</a>
    <a href="produtos.php"       class="bnav-a"><span class="material-symbols-outlined">inventory</span>Produtos</a>
    <a href="vitrine_config.php" class="bnav-a"><span class="material-symbols-outlined">storefront</span>Vitrine</a>
    <a href="pedidos.php"        class="bnav-a on"><span class="material-symbols-outlined">receipt_long</span>Pedidos</a>
    <a href="config.php"         class="bnav-a"><span class="material-symbols-outlined">settings</span>Config</a>
</div></nav>

<div class="tw" id="tw"></div>

<div class="img-modal" id="imgModal" onclick="if(event.target===this)fecharImg()">
    <div class="img-modal-box">
        <button class="img-modal-close" onclick="fecharImg()"><span class="material-symbols-outlined">close</span></button>
        <img id="imgModalImg" src="" alt="">
        <div class="img-modal-nome" id="imgModalNome"></div>
    </div>
</div>

<?php if ($hasOrderPasscode): ?>
<div class="order-passcode-modal" id="orderPasscodeModal" role="dialog" aria-modal="true" aria-labelledby="orderPasscodeTitle">
    <div class="order-passcode-box">
        <div class="order-passcode-icon"><span class="material-symbols-outlined">shield_lock</span></div>
        <h2 id="orderPasscodeTitle">Confirme sua ação</h2>
        <p id="orderPasscodeDescription">Digite seu Pass Code para continuar.</p>
        <div class="order-passcode-pin" id="orderPasscodePin">
            <input class="order-passcode-input" id="orderPasscodeInput" type="password" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" aria-label="Pass Code de 6 dígitos">
            <div class="order-passcode-slots" id="orderPasscodeSlots" aria-hidden="true"></div>
        </div>
        <div class="order-passcode-error" id="orderPasscodeError" role="alert"></div>
        <div class="order-passcode-actions"><button class="order-passcode-cancel" type="button" onclick="fecharPasscodePedido()">Voltar</button><button class="order-passcode-confirm" id="orderPasscodeConfirm" type="button" onclick="confirmarPasscodePedido()">Confirmar</button></div>
    </div>
</div>
<?php endif; ?>

<script>
const CSRF = <?= json_encode(get_csrf_token()) ?>;
const HAS_ORDER_PASSCODE = <?= $hasOrderPasscode ? 'true' : 'false' ?>;
const $ = id => document.getElementById(id);

// ── Toast ──────────────────────────────────────────────────
function toast(msg, type='ok') {
    const w=$('tw'), el=document.createElement('div');
    el.className='toast '+(type==='err'?'err':'ok');
    const icon=document.createElement('span');
    icon.className='material-symbols-outlined';
    icon.textContent=type==='err'?'error':'check_circle';
    el.append(icon,document.createTextNode(String(msg??'')));
    w.appendChild(el);
    setTimeout(()=>{el.style.animation='toastOut .3s forwards';setTimeout(()=>el.remove(),300)},3200);
}

// ── Sidebar ────────────────────────────────────────────────
const sb=$('sb'), ov=$('ov');
$('ham')?.addEventListener('click',()=>{sb.classList.add('open');ov.classList.add('on');document.body.style.overflow='hidden'});
ov.addEventListener('click',()=>{sb.classList.remove('open');ov.classList.remove('on');document.body.style.overflow=''});

// ══════════════════════════════════════════════════════════
// PEDIDOS
// ══════════════════════════════════════════════════════════
function expandirImg(src, nome) {
    $('imgModalImg').src = src;
    $('imgModalImg').alt = nome;
    $('imgModalNome').textContent = nome;
    $('imgModal').classList.add('on');
    document.body.style.overflow = 'hidden';
}
document.addEventListener('click', event => {
    const image = event.target.closest('[data-expand-image]');
    if (image) expandirImg(image.dataset.imageSrc || '', image.dataset.imageName || '');
});
function fecharImg() {
    $('imgModal').classList.remove('on');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') fecharImg(); });

function toggleDetalhes(pid, btn) {
    const box = $(`itensFull-${pid}`);
    const abrir = box.style.display !== 'flex';
    box.style.display = abrir ? 'flex' : 'none';
    btn.classList.toggle('open', abrir);
    btn.querySelector('.toggle-label').textContent = abrir ? 'Mostrar menos' : 'Mostrar mais';
}

let pedidoStatusFilter = 'todos';
let pedidoSearchQuery = '';

function aplicarFiltrosPedidos() {
    let visible = 0;
    document.querySelectorAll('.pedido-card').forEach(card => {
        const matchesStatus = pedidoStatusFilter === 'todos' || card.dataset.status === pedidoStatusFilter;
        const matchesSearch = !pedidoSearchQuery || (card.dataset.search || '').includes(pedidoSearchQuery);
        const show = matchesStatus && matchesSearch;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    const empty = $('pedidoEmptyFilter');
    if (empty) empty.style.display = visible ? 'none' : 'block';
}

function filtrarPedidos(status, btn) {
    pedidoStatusFilter = status;
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('on'));
    btn.classList.add('on');
    aplicarFiltrosPedidos();
}

function filtrarBusca(value) {
    pedidoSearchQuery = String(value || '').trim().toLowerCase();
    aplicarFiltrosPedidos();
}

function atualizarIndicadoresPedidos() {
    const cards = [...document.querySelectorAll('.pedido-card')];
    const count = status => cards.filter(card => card.dataset.status === status).length;
    $('orderStatTotal').textContent = cards.length;
    $('orderStatPending').textContent = count('pendente');
    $('orderStatConfirmed').textContent = count('confirmado');
}

let orderPasscodeAction = null;

function abrirJanelaImpressaoPedido() {
    const printWindow = window.open('about:blank', '_blank');
    if (!printWindow) return null;
    try {
        printWindow.document.open();
        printWindow.document.write('<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Preparando impressão</title><body style="font:16px sans-serif;padding:24px;color:#222">Aguardando a confirmação do pedido…</body></html>');
        printWindow.document.close();
        return printWindow;
    } catch {
        try { printWindow.close(); } catch {}
        return null;
    }
}

function fecharJanelaImpressaoPedido(printWindow) {
    try {
        if (printWindow && !printWindow.closed) printWindow.close();
    } catch {}
}

function carregarImpressaoPedido(printWindow, pid) {
    try {
        if (!printWindow || printWindow.closed) return false;
        printWindow.location.replace(`pedido_imprimir.php?id=${encodeURIComponent(pid)}&auto=1`);
        return true;
    } catch {
        return false;
    }
}

function atualizarAcaoImpressaoPedido(card, pid, status) {
    let action = card.querySelector('.pedido-print-btn');
    if (status !== 'confirmado') {
        action?.remove();
        return;
    }
    if (action) return;

    action = document.createElement('a');
    action.className = 'pedido-print-btn';
    action.href = `pedido_imprimir.php?id=${encodeURIComponent(pid)}&auto=1`;
    action.target = '_blank';
    action.rel = 'noopener';
    const icon = document.createElement('span');
    icon.className = 'material-symbols-outlined';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = 'print';
    action.append(icon, document.createTextNode('Imprimir pedido'));
    card.querySelector('.pedido-body')?.append(action);
}

function atualizarStatus(pid, status, sel) {
    if (sel.disabled || orderPasscodeAction) {
        sel.value = sel.dataset.prev || sel.value;
        return;
    }
    const previousStatus = sel.dataset.prev || 'pendente';
    if (status === previousStatus) return;
    if (HAS_ORDER_PASSCODE && ['confirmado', 'cancelado'].includes(status)) {
        sel.value = previousStatus;
        abrirPasscodePedido(pid, status, sel, previousStatus);
        return;
    }
    const printWindow = status === 'confirmado' ? abrirJanelaImpressaoPedido() : null;
    enviarStatusPedido(pid, status, sel, '', false, printWindow);
}

function abrirPasscodePedido(pid, status, sel, previousStatus) {
    orderPasscodeAction = {pid, status, sel, previousStatus};
    const modal = $('orderPasscodeModal');
    const input = $('orderPasscodeInput');
    $('orderPasscodeTitle').textContent = status === 'confirmado' ? 'Confirmar pedido' : 'Cancelar pedido';
    $('orderPasscodeDescription').textContent = status === 'confirmado'
        ? 'Digite seu Pass Code para confirmar este pedido.'
        : 'Digite seu Pass Code para cancelar este pedido.';
    $('orderPasscodeError').textContent = '';
    input.value = '';
    delete input.dataset.submitting;
    renderPasscodePedido();
    modal.classList.add('on');
    document.body.style.overflow = 'hidden';
    setTimeout(() => input.focus(), 50);
}

function fecharPasscodePedido() {
    const modal = $('orderPasscodeModal');
    if (!modal) return;
    modal.classList.remove('on');
    document.body.style.overflow = '';
    orderPasscodeAction = null;
}

function renderPasscodePedido() {
    const input = $('orderPasscodeInput');
    const slots = $('orderPasscodeSlots');
    if (!input || !slots) return;
    const value = input.value.replace(/\D/g, '').slice(0, 6);
    if (input.value !== value) input.value = value;
    slots.querySelectorAll('span').forEach((slot, index) => {
        slot.className = (index < value.length ? 'filled' : '') + (index === value.length ? ' active' : '');
    });
    if (value) $('orderPasscodePin').classList.remove('error');
}

async function confirmarPasscodePedido() {
    if (!orderPasscodeAction) return;
    const input = $('orderPasscodeInput');
    if (input.dataset.submitting) return;
    if (!/^\d{6}$/.test(input.value)) {
        $('orderPasscodePin').classList.add('error');
        $('orderPasscodeError').textContent = 'Digite os 6 números do seu Pass Code.';
        return;
    }
    const button = $('orderPasscodeConfirm');
    input.dataset.submitting = '1';
    button.disabled = true;
    button.textContent = 'Verificando…';
    $('orderPasscodePin').classList.add('verifying');
    const action = orderPasscodeAction;
    const printWindow = action.status === 'confirmado' && action.previousStatus !== 'confirmado'
        ? abrirJanelaImpressaoPedido()
        : null;
    const ok = await enviarStatusPedido(action.pid, action.status, action.sel, input.value, true, printWindow);
    button.disabled = false;
    button.textContent = 'Confirmar';
    $('orderPasscodePin').classList.remove('verifying');
    if (ok) fecharPasscodePedido();
    else delete input.dataset.submitting;
}

async function enviarStatusPedido(pid, status, sel, passcode = '', fromModal = false, printWindow = null) {
    if (sel.dataset.submitting) {
        fecharJanelaImpressaoPedido(printWindow);
        return false;
    }
    const previousStatus = sel.dataset.prev || sel.value;
    sel.dataset.submitting = '1';
    sel.disabled = true;
    const fd = new FormData();
    fd.append('acao','pedido_status');
    fd.append('csrf_token',CSRF);
    fd.append('pedido_id',pid);
    fd.append('status',status);
    if (passcode) fd.append('passcode', passcode);

    try {
        const r = await fetch('vitrine_api.php',{method:'POST',body:fd,credentials:'same-origin'});
        const j = await r.json();
        if (j.ok) {
            const card = sel.closest('.pedido-card');
            card.dataset.status = status;
            sel.value = status;
            sel.dataset.prev = status;
            atualizarAcaoImpressaoPedido(card, pid, status);
            atualizarIndicadoresPedidos();
            aplicarFiltrosPedidos();
            const pendente = document.querySelectorAll('.pedido-card[data-status="pendente"]').length;
            const badge = $('pendBadge');
            if (badge) { badge.textContent = pendente; badge.style.display = pendente ? '' : 'none'; }

            const confirmouAgora = status === 'confirmado' && j.pedido_confirmado_agora === true;
            const impressaoAberta = confirmouAgora && carregarImpressaoPedido(printWindow, pid);
            if (!impressaoAberta) fecharJanelaImpressaoPedido(printWindow);
            toast(status === 'confirmado' ? 'Pedido confirmado! Venda declarada.' : 'Status atualizado!');
            if (confirmouAgora && !impressaoAberta) {
                toast('Pedido confirmado. Se a impressão não abriu, use “Imprimir pedido” no card.');
            }
            if (j.aviso) toast(j.aviso,'err');
            sel.disabled = false;
            delete sel.dataset.submitting;
            return true;
        }
        if (fromModal) {
            $('orderPasscodePin').classList.add('error');
            $('orderPasscodeError').textContent = j.error || 'Não foi possível confirmar o Pass Code.';
            $('orderPasscodeInput').value = '';
            renderPasscodePedido();
            setTimeout(() => $('orderPasscodeInput').focus(), 30);
        } else toast(j.error || 'Erro','err');
    } catch {
        if (fromModal) $('orderPasscodeError').textContent = 'Erro de conexão. Tente novamente.';
        else toast('Erro de conexão','err');
    }

    sel.value = previousStatus;
    fecharJanelaImpressaoPedido(printWindow);
    sel.disabled = false;
    delete sel.dataset.submitting;
    return false;
}
// Salva valor atual dos selects para rollback
document.querySelectorAll('.pedido-status-sel').forEach(sel => {
    sel.dataset.prev = sel.value;
});

if (HAS_ORDER_PASSCODE) {
    const passcodeInput = $('orderPasscodeInput');
    const passcodeSlots = $('orderPasscodeSlots');
    for (let index = 0; index < 6; index++) passcodeSlots.appendChild(document.createElement('span'));
    passcodeInput.addEventListener('input', () => {
        renderPasscodePedido();
        if (passcodeInput.value.length === 6 && !passcodeInput.dataset.submitting) {
            setTimeout(confirmarPasscodePedido, 180);
        }
    });
    passcodeInput.addEventListener('focus', () => $('orderPasscodePin').classList.add('focused'));
    passcodeInput.addEventListener('blur', () => $('orderPasscodePin').classList.remove('focused'));
    $('orderPasscodePin').addEventListener('click', () => passcodeInput.focus());
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && $('orderPasscodeModal').classList.contains('on')) fecharPasscodePedido(); });
}

// ── Utilitários ────────────────────────────────────────────
function fmtBrl(v) { return Number(v).toFixed(2).replace('.',','); }

// ══════════════════════════════════════════════════════════
// FRETE POR PEDIDO
// ══════════════════════════════════════════════════════════
function editarFrete(pid, valorAtual, btnEl) {
    const form = $(`freteForm-${pid}`);
    const inp  = $(`freteVal-${pid}`);
    form.style.display = 'block';
    inp.value = valorAtual > 0 ? valorAtual.toFixed(2) : '';
    inp.focus();
    inp.select();
    btnEl.style.display = 'none';
}

function cancelarFrete(pid) {
    $(`freteForm-${pid}`).style.display = 'none';
    // Reexibe o botão de editar
    const card = document.querySelector(`.pedido-card [onclick*="editarFrete(${pid},"]`);
    if (card) card.style.display = '';
}

async function salvarFrete(pid, inpEl) {
    const valor = parseFloat(inpEl.value) || 0;
    if (valor < 0) { toast('Valor inválido','err'); return; }

    inpEl.disabled = true;
    const fd = new FormData();
    fd.append('acao',        'salvar_frete');
    fd.append('csrf_token',  CSRF);
    fd.append('pedido_id',   pid);
    fd.append('valor_frete', valor.toFixed(2));

    try {
        const r = await fetch('vitrine_api.php', {method:'POST', body:fd, credentials:'same-origin'});
        const j = await r.json();
        if (!j.ok) { toast(j.error||'Erro ao salvar frete','err'); inpEl.disabled=false; return; }

        // Atualiza visual do card
        const form       = $(`freteForm-${pid}`);
        const totalEl    = $(`pedTotal-${pid}`);

        if (totalEl) totalEl.textContent = `Total: R$ ${fmtBrl(j.total_novo)}`;

        // Reconstrói linha de frete
        const freteWrap = form.previousElementSibling; // a .pedido-frete div
        if (freteWrap) {
            freteWrap.innerHTML = `
                <span class="material-symbols-outlined">local_shipping</span>
                Frete: <strong>R$ ${fmtBrl(valor)}</strong>
                <button class="frete-edit-btn" title="Editar frete"
                        onclick="editarFrete(${pid}, ${valor}, this)">
                    <span class="material-symbols-outlined">edit</span>
                </button>`;
        }
        form.style.display = 'none';

        const tipo = j.despesa_criada ? ' Frete lançado em Despesas!' : ' Frete salvo!';
        toast(tipo);
    } catch { toast('Erro de conexão','err'); }
    inpEl.disabled = false;
}
</script>
</body>
</html>
