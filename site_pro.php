<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/subscription.php';
require __DIR__ . '/site_pro_helpers.php';
require_once __DIR__ . '/includes/tracking_runtime.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$pdo = get_pdo();
$userId = (int)$_SESSION['user_id'];
$username = (string)($_SESSION['username'] ?? 'Usuário');
$sub = check_and_block($userId, $pdo);
$isPro = has_pro_access($userId, $pdo);
if (!$isPro) {
    header('Location: planos.php');
    exit;
}
$csrfToken = get_csrf_token();

$userStmt = $pdo->prepare('SELECT username, role, vitrine_slug FROM users WHERE id = ? LIMIT 1');
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$is_admin = ($user['role'] ?? '') === 'admin';
$slug = trim((string)($user['vitrine_slug'] ?? ''));

$storeStmt = $pdo->prepare('SELECT nome_loja, descricao FROM vitrine_config WHERE user_id = ? LIMIT 1');
$storeStmt->execute([$userId]);
$loja = $storeStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$loja['nome_loja'] = trim((string)($loja['nome_loja'] ?? '')) ?: $username;
$loja['slug_site'] = $slug;

$erro = '';
$sucesso = '';
if ($isPro && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $erro = 'Token de segurança inválido. Atualize a página e tente novamente.';
    } else {
        try {
            $savedConfig = site_pro_save_config($pdo, $userId, $_POST, $_FILES);
            if (!empty($_POST['abrir_apos_salvar']) && $slug !== '') {
                header('Location: /' . rawurlencode($slug));
                exit;
            }
            $sucesso = $savedConfig['canal_publico'] === 'site_pro'
                ? 'Escolha salva: o endereço da loja agora abre o Site PRO.'
                : 'Escolha salva: o endereço da loja agora abre a Vitrine.';
        } catch (Throwable $e) {
            $erro = $e instanceof InvalidArgumentException
                ? $e->getMessage()
                : 'Não foi possível salvar o site agora.';
        }
    }
}

$config = site_pro_config($pdo, $userId, $loja);
$pend_count = 0;
$productCount = 0;
$previewProducts = [];
try {
    $pending = $pdo->prepare("SELECT COUNT(*) FROM vitrine_pedidos WHERE user_id = ? AND status = 'pendente'");
    $pending->execute([$userId]);
    $pend_count = (int)$pending->fetchColumn();

    site_pro_ensure_schema($pdo);
    $products = $pdo->prepare('SELECT COUNT(*) FROM produtos WHERE user_id = ? AND quantidade > 0 AND site_pro_exposto = 1');
    $products->execute([$userId]);
    $productCount = (int)$products->fetchColumn();

    $previewProductsStmt = $pdo->prepare('
        SELECT id, nome, preco, quantidade, imagem
        FROM produtos
        WHERE user_id = ? AND quantidade > 0 AND site_pro_exposto = 1
        ORDER BY nome ASC
        LIMIT 3
    ');
    $previewProductsStmt->execute([$userId]);
    $previewProducts = $previewProductsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}

$siteSlug = $slug;
$siteUrl = $siteSlug !== '' ? '/' . rawurlencode($siteSlug) : '';
$previewBackgroundClass = $config['cor_fundo'] !== ''
    ? (site_pro_color_is_dark($config['cor_fundo']) ? ' custom-bg-dark' : ' custom-bg-light')
    : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Meu Site PRO — Uplar</title>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800;900&family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link rel="stylesheet" href="assets/vitrine-image-editor.css?v=20261015-banner1">
    <link rel="stylesheet" href="assets/app-theme.css?v=20260723">
    <script src="assets/app-theme.js?v=20260723"></script>
    <style>
        :root {
            --p:#3b6ff5;--p-dark:#2a55d9;--p-dim:rgba(59,111,245,.08);--p-glow:rgba(59,111,245,.20);
            --ink:#0f1c2e;--muted:#5a6a7a;--faint:#94a3b8;--bg:#f4f6fb;--card:#fff;--border:#e4eaf2;
            --surface-soft:#f8faff;--input-bg:#fff;--ok:#10b981;--ok-dim:rgba(16,185,129,.09);
            --err:#ef4444;--err-dim:rgba(239,68,68,.09);
            --sh:0 1px 4px rgba(15,28,46,.06),0 4px 16px rgba(15,28,46,.04);
            --sh-lift:0 4px 24px rgba(59,111,245,.14);--r:12px;
        }
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:Inter,sans-serif;background:var(--bg);color:var(--ink);line-height:1.55;-webkit-font-smoothing:antialiased}
        a{color:inherit;text-decoration:none}
        button,input,textarea{font:inherit}
        button{cursor:pointer}
        .spinner{width:16px;height:16px;display:inline-block;border:2px solid rgba(59,111,245,.24);border-top-color:var(--p);border-radius:50%;animation:spin .7s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}
        .app{display:flex;min-height:100vh}

        /* Estrutura compartilhada do sistema */
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
        .ham{display:none;width:40px;height:40px;flex:0 0 40px;background:var(--card);border:1px solid var(--border);border-radius:9px;align-items:center;justify-content:center;box-shadow:var(--sh)}
        .ham .material-symbols-outlined{font-size:1.3rem}
        .main{margin-left:248px;flex:1;min-width:0;padding:1.75rem 2rem}

        .top{display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1.3rem}
        .top-left{display:flex;align-items:center;gap:.8rem;min-width:0}
        .greeting{font-size:1.35rem;font-weight:800;letter-spacing:-.4px}
        .sub-txt{color:var(--muted);font-size:.8rem;margin-top:1px}
        .top-actions{display:flex;gap:.55rem;flex-wrap:wrap}
        .btn{min-height:40px;display:inline-flex;align-items:center;justify-content:center;gap:.4rem;border-radius:9px;padding:.58rem .9rem;border:1px solid var(--border);background:var(--card);color:var(--muted);font-size:.78rem;font-weight:700}
        .btn:hover{border-color:var(--p);background:var(--p-dim);color:var(--p)}
        .btn.primary{border-color:var(--p);background:var(--p);color:#fff;box-shadow:0 4px 14px var(--p-glow)}
        .btn.primary:hover{background:var(--p-dark);color:#fff;transform:translateY(-1px)}
        .btn .material-symbols-outlined{font-size:1rem}

        .status-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.75rem;margin-bottom:1rem}
        .status-card{display:flex;align-items:center;gap:.7rem;min-width:0;padding:.8rem .9rem;background:var(--card);border:1px solid var(--border);border-radius:10px}
        .status-icon{width:34px;height:34px;display:grid;place-items:center;flex:0 0 34px;border-radius:9px;background:var(--p-dim);color:var(--p)}
        .status-icon .material-symbols-outlined{font-size:1.05rem}
        .status-copy{min-width:0}
        .status-label{font-size:.66rem;color:var(--faint);font-weight:700;text-transform:uppercase;letter-spacing:.04em}
        .status-value{font-size:.78rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:.08rem}
        .status-value.on{color:var(--ok)}

        .notice{display:flex;align-items:center;gap:.5rem;padding:.75rem .9rem;border:1px solid transparent;border-radius:9px;font-size:.78rem;margin-bottom:1rem}
        .notice .material-symbols-outlined{font-size:1rem}
        .notice.ok{background:var(--ok-dim);border-color:rgba(16,185,129,.2);color:#087e53}
        .notice.err{background:var(--err-dim);border-color:rgba(239,68,68,.2);color:#b42318}
        .editor-toast{position:fixed;right:1rem;bottom:1rem;z-index:5000;max-width:340px;padding:.75rem 1rem;border-radius:9px;background:var(--ink);color:#fff;font-size:.75rem;font-weight:600;box-shadow:0 12px 30px rgba(0,0,0,.2)}
        .editor-toast.err{background:#b42318}
        .section-tabs{display:flex;margin-bottom:1rem;overflow:hidden;border:1px solid var(--border);border-radius:10px;background:var(--card)}
        .section-tab{flex:1;min-height:46px;display:flex;align-items:center;justify-content:center;gap:.4rem;border:0;border-bottom:2px solid transparent;background:transparent;color:var(--muted);font-size:.78rem;font-weight:700}
        .section-tab:hover{background:var(--p-dim);color:var(--p)}
        .section-tab.on{border-bottom-color:var(--p);background:var(--p-dim);color:var(--p)}
        .section-tab .material-symbols-outlined{font-size:1rem}
        .tab-panel[hidden]{display:none}

        .builder-form{display:block}
        .channel-panel{margin-bottom:0}
        .site-grid{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(330px,.84fr);gap:1.25rem;align-items:start}
        .editor-stack{display:grid;gap:1rem;min-width:0}
        .card{background:var(--card);border:1px solid var(--border);border-radius:var(--r);padding:1.2rem 1.3rem;box-shadow:var(--sh)}
        .builder-card{min-width:0}
        .section-heading{display:flex;align-items:center;gap:.75rem;padding-bottom:.9rem;margin-bottom:.9rem;border-bottom:1px solid var(--border)}
        .section-heading>div{min-width:0;flex:1}
        .section-heading h2{margin:0;font-size:.92rem;letter-spacing:-.01em}
        .section-heading p{margin:.12rem 0 0;color:var(--muted);font-size:.7rem}
        .section-heading.compact{align-items:flex-start}
        .section-number{width:30px;height:30px;display:grid;place-items:center;flex:0 0 30px;border-radius:8px;background:var(--p);color:#fff;font-size:.72rem;font-weight:800;box-shadow:0 3px 10px var(--p-glow)}
        .current-channel{padding:.3rem .55rem;border-radius:20px;background:var(--ok-dim);color:var(--ok);font-size:.64rem;font-weight:800;white-space:nowrap}
        .card-title{display:flex;align-items:center;gap:.45rem;padding-bottom:.75rem;margin-bottom:1rem;border-bottom:1px solid var(--border);font-size:.88rem;font-weight:700}
        .card-title .material-symbols-outlined{font-size:1rem;color:var(--p)}
        .card-sub{margin:-.45rem 0 1rem;color:var(--muted);font-size:.75rem}

        .field{display:flex;flex-direction:column;gap:.32rem;margin-top:.85rem}
        .field>label,.field-label{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}
        .field input,.field textarea,.field select{width:100%;padding:.65rem .9rem;border:1.5px solid var(--border);border-radius:9px;outline:0;color:var(--ink);background:var(--input-bg);font-size:.86rem}
        .field input:focus,.field textarea:focus,.field select:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
        .field textarea{resize:vertical;min-height:88px}
        .field-hint{font-size:.67rem;color:var(--faint)}
        .form-grid{display:grid;grid-template-columns:1fr 1fr;gap:.8rem}
        .form-grid .wide{grid-column:1/-1}
        .builder-subhead{display:flex;align-items:center;gap:.55rem;margin:1.15rem 0 .1rem;padding-top:1.05rem;border-top:1px solid var(--border)}
        .section-heading+.builder-subhead{margin-top:0;padding-top:0;border-top:0}
        .builder-subhead .material-symbols-outlined{color:var(--p);font-size:1rem}
        .builder-subhead strong{display:block;font-size:.77rem}
        .builder-subhead span:last-child{display:block;margin-top:.05rem;color:var(--muted);font-size:.66rem}
        .background-control{display:flex;align-items:center;gap:.65rem}
        .background-control .color-input{flex:0 0 44px}
        .check-line{display:flex;align-items:center;gap:.45rem;color:var(--muted);font-size:.72rem;font-weight:600}
        .check-line input{width:16px;height:16px;padding:0}
        .benefit-editor{display:grid;grid-template-columns:130px minmax(0,.8fr) minmax(0,1.2fr);gap:.65rem;align-items:end;margin-top:.75rem;padding:.75rem;border:1px solid var(--border);border-radius:9px;background:var(--surface-soft)}
        .benefit-editor .field{margin-top:0}
        .form-section{display:flex;align-items:center;gap:.65rem;margin-top:1.15rem;padding:.72rem .8rem;border:1px solid var(--border);border-radius:10px;background:var(--bg)}
        .form-section:first-of-type{margin-top:0}
        .form-section>.material-symbols-outlined{width:30px;height:30px;display:grid;place-items:center;flex:0 0 30px;border-radius:8px;background:var(--p-dim);color:var(--p);font-size:1rem}
        .form-section strong{display:block;font-size:.76rem}
        .form-section small{display:block;margin-top:.08rem;color:var(--muted);font-size:.66rem}
        .channel-options{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-top:.75rem}
        .channel-option{position:relative;display:block;cursor:pointer}
        .channel-option input{position:absolute;opacity:0;pointer-events:none}
        .channel-card{display:flex;align-items:flex-start;gap:.7rem;min-height:94px;padding:.85rem;border:1.5px solid var(--border);border-radius:10px;background:var(--surface-soft);transition:.18s}
        .channel-icon{width:36px;height:36px;display:grid;place-items:center;flex:0 0 36px;border-radius:9px;background:var(--card);color:var(--muted);border:1px solid var(--border)}
        .channel-icon .material-symbols-outlined{font-size:1.1rem}
        .channel-copy strong{display:block;font-size:.78rem}
        .channel-copy span{display:block;margin-top:.18rem;color:var(--muted);font-size:.66rem;line-height:1.4}
        .channel-option input:checked+.channel-card{border-color:var(--p);background:var(--p-dim);box-shadow:0 0 0 3px var(--p-dim)}
        .channel-option input:checked+.channel-card .channel-icon{border-color:var(--p);background:var(--p);color:#fff}
        .channel-option input:focus-visible+.channel-card{outline:2px solid var(--p);outline-offset:2px}
        .checkout-options{grid-template-columns:repeat(3,minmax(0,1fr))}
        .checkout-options .channel-card{min-height:112px}
        .payment-config{margin-top:.85rem;padding:.9rem;border:1px solid var(--border);border-radius:10px;background:var(--surface-soft)}
        .payment-config[hidden]{display:none}
        .infinite-tag-input{display:flex;align-items:center;overflow:hidden;border:1.5px solid var(--border);border-radius:9px;background:var(--input-bg)}
        .infinite-tag-input:focus-within{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
        .infinite-tag-input span{padding:0 .15rem 0 .8rem;color:var(--p);font-weight:800}
        .infinite-tag-input input{border:0!important;box-shadow:none!important;padding-left:.2rem}
        .payment-note{display:flex;gap:.45rem;margin-top:.65rem;color:var(--muted);font-size:.67rem;line-height:1.45}
        .payment-note .material-symbols-outlined{flex:0 0 auto;color:var(--p);font-size:.95rem}
        .infinite-help{margin-top:.65rem;border-top:1px solid var(--border);padding-top:.65rem}
        .infinite-help summary{display:inline-flex;align-items:center;gap:.35rem;color:var(--p);font-size:.69rem;font-weight:750;cursor:pointer;list-style:none}
        .infinite-help summary::-webkit-details-marker{display:none}
        .infinite-help summary .material-symbols-outlined{font-size:.95rem}
        .infinite-help-body{margin-top:.55rem;padding:.7rem .75rem;border-radius:8px;background:var(--card);color:var(--muted);font-size:.67rem;line-height:1.55}
        .infinite-help-body strong{color:var(--ink)}
        .infinite-help-body a{display:inline-block;margin-top:.35rem;color:var(--p);font-weight:750}
        .channel-footer{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-top:.75rem}
        .channel-note{display:flex;align-items:flex-start;gap:.4rem;padding:.6rem .7rem;border-radius:8px;background:var(--bg);color:var(--muted);font-size:.67rem}
        .channel-note .material-symbols-outlined{font-size:.9rem;color:var(--p)}
        .channel-actions{display:flex;align-items:center;justify-content:flex-end;margin-top:.65rem}
        .channel-save{min-width:190px}
        .color-row{display:flex;align-items:center;gap:.55rem;flex-wrap:wrap;margin-top:.4rem}
        .swatch{width:34px;height:34px;border:2px solid transparent;border-radius:8px;cursor:pointer;box-shadow:0 0 0 1px var(--border)}
        .swatch.on{border-color:var(--card);box-shadow:0 0 0 2px var(--ink)}
        .color-input{width:38px!important;height:38px;padding:2px!important;cursor:pointer}
        .media-grid{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-top:.45rem}
        .media-box{min-width:0;padding:.8rem;border:1px solid var(--border);border-radius:10px;background:var(--surface-soft)}
        .media-head{display:flex;align-items:center;justify-content:space-between;gap:.5rem;margin-bottom:.55rem}
        .media-title{display:flex;align-items:center;gap:.35rem;font-size:.72rem;font-weight:700}
        .media-title .material-symbols-outlined{font-size:.95rem;color:var(--p)}
        .media-ratio{font-size:.6rem;font-weight:700;color:var(--p);background:var(--p-dim);padding:.18rem .4rem;border-radius:20px}
        .media-thumb{width:100%;height:108px;display:flex;align-items:center;justify-content:center;overflow:hidden;border:1px dashed var(--border);border-radius:9px;background:var(--card);color:var(--faint)}
        .media-thumb.logo{height:108px}
        .media-thumb img{width:100%;height:100%;object-fit:cover}
        .media-thumb.logo img{object-fit:contain}
        .media-empty{display:flex;flex-direction:column;align-items:center;gap:.25rem;padding:.7rem;font-size:.65rem}
        .media-empty .material-symbols-outlined{font-size:1.35rem}
        .media-actions{display:flex;gap:.4rem;margin-top:.55rem}
        .media-action{flex:1;min-height:34px;display:inline-flex;align-items:center;justify-content:center;gap:.25rem;padding:.4rem;border:1px solid var(--border);border-radius:7px;background:var(--card);color:var(--muted);font-size:.65rem;font-weight:700}
        .media-action.primary{border-color:var(--p);background:var(--p);color:#fff}
        .media-action .material-symbols-outlined{font-size:.85rem}
        .media-file-native{display:none}
        .media-note{margin-top:.5rem;color:var(--faint);font-size:.61rem;line-height:1.4}
        .media-pending{display:flex;align-items:center;gap:.25rem;margin-top:.45rem;color:#b45309;font-size:.62rem;font-weight:700}
        .media-pending .material-symbols-outlined{font-size:.8rem}

        .templates{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-top:.45rem}
        .template{position:relative;display:block;cursor:pointer}
        .template input{position:absolute;opacity:0;pointer-events:none}
        .template-box{display:block;min-height:146px;padding:.75rem;border:1.5px solid var(--border);border-radius:10px;background:var(--surface-soft);transition:.18s}
        .template:hover .template-box{border-color:rgba(59,111,245,.45)}
        .template input:focus-visible+.template-box{outline:2px solid var(--p);outline-offset:2px}
        .template input:checked+.template-box{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim);background:var(--p-dim)}
        .template-preview{display:block;height:62px;margin-bottom:.65rem;border:1px solid rgba(15,28,46,.1);border-radius:6px;background:#edf1f5;position:relative;overflow:hidden}
        .template-preview::before{content:"";position:absolute;left:0;right:0;top:0;height:12px;border-bottom:1px solid rgba(15,28,46,.1);background:#fff}
        .template-preview::after{content:"";position:absolute;left:8px;right:30%;bottom:9px;height:5px;background:#fff;box-shadow:0 -11px 0 rgba(255,255,255,.62)}
        .template-preview.editorial{background:#ddd2c5}
        .template-preview.editorial::after{left:24%;right:24%;background:#fff;box-shadow:0 -11px 0 rgba(255,255,255,.68)}
        .template-preview.sport{background:#172018}
        .template-preview.sport::before{background:#0d120e;border-color:#314133}
        .template-preview.sport::after{right:18%;background:#d9ff45;box-shadow:0 -11px 0 rgba(255,255,255,.82);transform:skew(-9deg)}
        .template-preview.compact{background:repeating-linear-gradient(90deg,#e4e7e2 0,#e4e7e2 26px,#f6f7f4 26px,#f6f7f4 31px)}
        .template-preview.compact::after{left:7px;right:7px;bottom:7px;height:3px;background:#334155;box-shadow:0 -9px 0 #fff}
        .template-title,.template-text{display:block}
        .template-title{font-size:.78rem;font-weight:800}
        .template-text{font-size:.65rem;color:var(--muted);line-height:1.35;margin-top:.18rem}

        .publish-row{display:flex;align-items:center;justify-content:space-between;gap:.8rem;margin-top:1rem;padding:.75rem;background:var(--bg);border-radius:10px}
        .publish-title{font-size:.8rem;font-weight:700}
        .publish-sub{font-size:.68rem;color:var(--muted);margin-top:.08rem}
        .toggle{position:relative;width:42px;height:24px;flex:0 0 42px}
        .toggle input{position:absolute;opacity:0}
        .toggle-track{position:absolute;inset:0;background:var(--border);border-radius:12px;cursor:pointer;transition:background .25s}
        .toggle-track::before{content:"";position:absolute;width:18px;height:18px;left:3px;top:3px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.2);transition:transform .25s}
        .toggle input:checked+.toggle-track{background:var(--p)}
        .toggle input:checked+.toggle-track::before{transform:translateX(18px)}
        .save-bar{position:sticky;bottom:.75rem;z-index:30;display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-top:1rem;padding:.8rem 1rem;border:1px solid var(--border);border-radius:10px;background:color-mix(in srgb,var(--card) 94%,transparent);box-shadow:0 12px 35px rgba(15,28,46,.14);backdrop-filter:blur(10px)}
        .save-bar strong,.save-bar span{display:block}
        .save-bar strong{font-size:.76rem}.save-bar span{margin-top:.06rem;color:var(--muted);font-size:.65rem}
        .save-button{min-width:190px}

        .preview-card{padding:0;overflow:hidden;position:sticky;top:1rem;transition:border-color .25s,box-shadow .25s,transform .25s}
        .preview-card.is-editing{border-color:color-mix(in srgb,var(--store-color) 48%,var(--border));box-shadow:0 18px 44px color-mix(in srgb,var(--store-color) 16%,transparent)}
        .preview-head{display:flex;align-items:center;justify-content:space-between;gap:.7rem;padding:.8rem 1rem;border-bottom:1px solid var(--border);background:var(--card)}
        .preview-head strong{display:flex;align-items:center;gap:.4rem;font-size:.78rem}
        .preview-head .material-symbols-outlined{font-size:1rem;color:var(--p)}
        .preview-state{font-size:.65rem;font-weight:700;padding:.22rem .5rem;border-radius:20px;background:var(--ok-dim);color:var(--ok)}
        .preview-browser{padding:.55rem;background:var(--bg)}
        .browser-bar{height:30px;display:flex;align-items:center;gap:5px;padding:0 .65rem;border:1px solid var(--border);border-bottom:0;border-radius:9px 9px 0 0;background:var(--card)}
        .browser-dot{width:6px;height:6px;border-radius:50%;background:var(--border)}
        .browser-url{min-width:0;margin-left:.35rem;color:var(--faint);font-size:.61rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .preview-viewport{height:500px;overflow:hidden;border:1px solid var(--border);border-radius:0 0 9px 9px;background:var(--preview-stage-bg,#f6f6f3);isolation:isolate;transition:background .3s}
        .preview-site{min-height:640px;background:#f6f6f3;transform:translate3d(var(--preview-camera-x,0px),var(--preview-camera-y,0px),0) scale(var(--preview-camera-scale,1));transform-origin:left top;transition:transform .52s cubic-bezier(.22,1,.36,1),background .25s;will-change:transform}
        .pv-utility{min-height:22px;display:flex;align-items:center;justify-content:space-between;gap:.5rem;padding:.25rem .8rem;background:#17202b;color:#fff;font-size:.46rem;font-weight:700}
        .pv-utility span:last-child{color:#bdc6d0}
        .pv-nav{height:40px;display:flex;align-items:center;justify-content:space-between;gap:.6rem;padding:0 .8rem;border-bottom:1px solid rgba(15,28,46,.08);background:#fff}
        .pv-nav-brand{display:flex;align-items:center;gap:.38rem;min-width:0;font-size:.62rem;font-weight:800}
        .pv-nav-action{display:flex;align-items:center;gap:.25rem;color:#4e5968;font-size:.56rem;font-weight:700}
        .pv-nav-action .material-symbols-outlined{font-size:.8rem}
        .pv-hero{min-height:205px;padding:1.3rem;display:flex;flex-direction:column;justify-content:flex-end;color:#fff;background:#17202b;position:relative;overflow:hidden;transform-origin:center}
        .pv-hero>img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.62}
        .pv-hero::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(10,16,24,.88),rgba(10,16,24,.22))}
        .pv-content{position:relative;z-index:1;display:flex;flex-direction:column;align-items:flex-start}
        .pv-brand{display:flex;align-items:center;gap:.4rem;margin-bottom:.25rem}
        .pv-brand-logo{width:24px;height:24px;display:grid;place-items:center;overflow:hidden;border:1px solid rgba(255,255,255,.35);border-radius:6px;background:rgba(255,255,255,.18);font-size:.6rem;font-weight:800}
        .pv-brand-logo img{width:100%;height:100%;object-fit:cover}
        .pv-badge{font-size:.61rem;font-weight:800;text-transform:uppercase;letter-spacing:.08em;opacity:.82}
        .pv-title{font-size:1.28rem;font-weight:800;line-height:1.15;margin-top:.35rem}
        .pv-sub{font-size:.7rem;opacity:.84;margin-top:.4rem;max-width:90%}
        .pv-cta{margin-top:.85rem;padding:.42rem .65rem;border-radius:4px;background:var(--store-color);color:#fff;font-size:.66rem;font-weight:800}
        .pv-service{display:grid;grid-template-columns:repeat(3,1fr);border-bottom:1px solid rgba(15,28,46,.08);background:#fff}
        .pv-service-item{display:flex;align-items:center;justify-content:center;gap:.28rem;min-width:0;padding:.45rem .25rem;text-align:left;color:#667085}
        .pv-service-item+.pv-service-item{border-left:1px solid rgba(15,28,46,.08)}
        .pv-service-item>.material-symbols-outlined{flex:0 0 auto;color:var(--store-color);font-size:.72rem}
        .pv-service-copy{min-width:0}
        .pv-service-copy strong,.pv-service-copy small{display:block;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
        .pv-service-copy strong{font-size:.46rem}.pv-service-copy small{color:#8b95a2;font-size:.39rem;font-weight:500}
        .pv-body{padding:1rem;background:var(--store-bg,#f6f6f3)}
        .pv-body-head{display:flex;align-items:flex-end;justify-content:space-between;gap:.6rem}
        .pv-label{font-size:.65rem;font-weight:800;color:var(--muted);text-transform:uppercase;letter-spacing:.05em}
        .pv-description{display:block;max-width:62%;text-align:right;color:#7a8492;font-size:.52rem;line-height:1.35}
        .pv-products{display:grid;grid-template-columns:repeat(2,1fr);gap:.5rem;margin-top:.7rem}
        .pv-product{min-width:0;overflow:hidden;border:1px solid rgba(15,28,46,.09);border-radius:5px;background:#fff}
        .pv-product-image{height:88px;display:grid;place-items:center;overflow:hidden;background:#eceeeb;color:#a0a7b0}
        .pv-product-image img{width:100%;height:100%;object-fit:cover}
        .pv-product-image .material-symbols-outlined{font-size:1rem}
        .pv-product-copy{padding:.4rem}
        .pv-product-name{display:block;overflow:hidden;color:#202833;font-size:.54rem;font-weight:800;white-space:nowrap;text-overflow:ellipsis}
        .pv-product-bottom{display:flex;align-items:center;justify-content:space-between;gap:.2rem;margin-top:.25rem}
        .pv-product-price{color:var(--store-color);font-size:.56rem;font-weight:800}
        .pv-product-add{width:16px;height:16px;display:grid;place-items:center;border-radius:3px;background:var(--store-color);color:#fff}
        .pv-product-add .material-symbols-outlined{font-size:.68rem}
        .pv-product-empty{grid-column:1/-1;padding:1rem;border:1px dashed rgba(15,28,46,.16);text-align:center;color:#7a8492;font-size:.58rem}
        .pv-foot{padding:.7rem 1rem;border-top:1px solid var(--border);font-size:.67rem;color:var(--muted);background:var(--card)}
        .preview-site.custom-bg-dark .pv-label{color:#fff}
        .preview-site.custom-bg-dark .pv-description{color:#c2cad3}
        .preview-site.custom-bg-light.model-impacto-esportivo .pv-label{color:#202833}
        .preview-site.custom-bg-light.model-impacto-esportivo .pv-description{color:#667085}
        .preview-site.font-manrope{font-family:Manrope,Inter,sans-serif}
        .preview-site.font-inter{font-family:Inter,sans-serif}
        .preview-site.font-archivo{font-family:Archivo,Inter,sans-serif}
        .preview-site.font-cormorant{font-family:"Cormorant Garamond",Georgia,serif}
        .preview-site.font-manrope .pv-title{font-family:Manrope,Inter,sans-serif}
        .preview-site.font-inter .pv-title{font-family:Inter,sans-serif}
        .preview-site.font-archivo .pv-title{font-family:Archivo,Inter,sans-serif}
        .preview-site.font-cormorant .pv-title{font-family:"Cormorant Garamond",Georgia,serif}
        .preview-site.model-catalogo-editorial{background:var(--store-bg,#eee8e1)}
        .preview-site.model-catalogo-editorial .pv-hero{min-height:230px;background:#302a25}
        .preview-site.model-catalogo-editorial .pv-hero::after{background:rgba(30,25,21,.54)}
        .preview-site.model-catalogo-editorial .pv-content{align-items:center;text-align:center}
        .preview-site.model-catalogo-editorial .pv-title{max-width:92%;font-family:Georgia,serif;font-size:1.5rem;font-weight:600;letter-spacing:-.02em}
        .preview-site.model-catalogo-editorial .pv-body{background:var(--store-bg,#eee8e1)}
        .preview-site.model-catalogo-editorial .pv-product{border:0}
        .preview-site.model-impacto-esportivo{background:var(--store-bg,#111612)}
        .preview-site.model-impacto-esportivo .pv-utility{background:#070b08}
        .preview-site.model-impacto-esportivo .pv-nav{border-color:#2c392e;background:#0c110d;color:#fff}
        .preview-site.model-impacto-esportivo .pv-nav-action{color:#c8d1c9}
        .preview-site.model-impacto-esportivo .pv-hero{min-height:220px;background:#182019}
        .preview-site.model-impacto-esportivo .pv-hero::after{background:linear-gradient(90deg,rgba(5,9,6,.96),rgba(5,9,6,.35))}
        .preview-site.model-impacto-esportivo .pv-title{font-size:1.45rem;font-weight:900;letter-spacing:-.045em;text-transform:uppercase}
        .preview-site.model-impacto-esportivo .pv-cta{color:#fff}
        .preview-site.model-impacto-esportivo .pv-service{border-color:#2c392e;background:#141b15}
        .preview-site.model-impacto-esportivo .pv-service-item{color:#c8d1c9}
        .preview-site.model-impacto-esportivo .pv-service-item+.pv-service-item{border-color:#2c392e}
        .preview-site.model-impacto-esportivo .pv-service-copy small{color:#89958b}
        .preview-site.model-impacto-esportivo .pv-body{background:var(--store-bg,#111612)}
        .preview-site.model-impacto-esportivo .pv-label,.preview-site.model-impacto-esportivo .pv-product-name{color:#f5f7f4}
        .preview-site.model-impacto-esportivo .pv-description{color:#aab3aa}
        .preview-site.model-impacto-esportivo .pv-product{border-color:#2c392e;background:#182019}
        .preview-site.model-impacto-esportivo .pv-product-image{background:#222c24}
        .preview-site.model-impacto-esportivo .pv-foot{border-color:#2c392e;background:#0c110d;color:#aab3aa}
        .preview-site.model-catalogo-compacto .pv-hero{min-height:165px}
        .preview-site.model-catalogo-compacto .pv-body{padding:.75rem}
        .preview-site.model-catalogo-compacto .pv-products{grid-template-columns:repeat(3,1fr);gap:.35rem;margin-top:.5rem}
        .preview-site.model-catalogo-compacto .pv-product-image{height:62px}
        .preview-site.model-catalogo-compacto .pv-product-copy{padding:.3rem}
        .preview-site.model-catalogo-compacto .pv-product-name{font-size:.48rem}
        [data-preview-zone]{position:relative;transition:filter .25s,box-shadow .25s,opacity .25s}
        [data-preview-zone].preview-focus{z-index:5;filter:saturate(1.12);box-shadow:0 0 0 2px #fff,0 0 0 5px color-mix(in srgb,var(--store-color) 70%,transparent),0 12px 28px rgba(15,28,46,.22)}
        .pv-title.preview-focus,.pv-sub.preview-focus,.pv-brand.preview-focus,.pv-cta.preview-focus,.pv-description.preview-focus{border-radius:3px}
        @media(prefers-reduced-motion:reduce){.preview-card,.preview-site,[data-preview-zone]{transition:none!important}.preview-focus{transform:none!important}}

        .locked{max-width:720px;margin:2.5rem auto;text-align:center}
        .locked .lock-icon{width:52px;height:52px;display:grid;place-items:center;margin:0 auto 1rem;border-radius:12px;background:var(--p-dim);color:var(--p)}
        .locked h1{font-size:1.5rem}
        .locked>p{color:var(--muted);max-width:520px;margin:.65rem auto 1.1rem}
        .benefits{display:grid;grid-template-columns:repeat(3,1fr);gap:.55rem;margin-top:1.3rem;text-align:left}
        .benefit{display:flex;gap:.45rem;align-items:flex-start;padding:.7rem;border:1px solid var(--border);border-radius:9px;font-size:.72rem}
        .benefit .material-symbols-outlined{color:var(--ok);font-size:1rem}
        .products-toolbar{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem}
        .products-search{flex:1;min-width:220px;padding:.62rem .85rem;border:1.5px solid var(--border);border-radius:9px;outline:0;background:var(--input-bg);color:var(--ink);font-size:.8rem}
        .products-search:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
        .products-count{padding:.5rem .7rem;border:1px solid var(--border);border-radius:8px;background:var(--bg);color:var(--muted);font-size:.68rem;font-weight:700}
        .admin-products{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.7rem}
        .coupon-create{display:grid;grid-template-columns:minmax(180px,1fr) 160px auto;gap:.7rem;align-items:end;padding:1rem;border:1px solid var(--border);border-radius:10px;background:var(--surface-soft)}
        .coupon-create .field{margin:0}
        .coupon-list{display:grid;gap:.65rem;margin-top:1rem}
        .coupon-item{display:flex;align-items:center;gap:.8rem;padding:.85rem .95rem;border:1px solid var(--border);border-radius:10px;background:var(--card)}
        .coupon-item.off{opacity:.65}
        .coupon-badge{display:grid;place-items:center;min-width:54px;height:44px;padding:0 .55rem;border-radius:8px;background:var(--p-dim);color:var(--p);font-size:.82rem;font-weight:850}
        .coupon-copy{min-width:0;flex:1}
        .coupon-code{font-size:.8rem;font-weight:800;letter-spacing:.04em}
        .coupon-meta{margin-top:.15rem;color:var(--muted);font-size:.67rem}
        .coupon-actions{display:flex;gap:.45rem}
        .coupon-actions .btn{padding:.5rem .65rem}
        .coupon-empty{padding:1.4rem;border:1px dashed var(--border);border-radius:10px;text-align:center;color:var(--muted);font-size:.75rem}
        .admin-product{position:relative;overflow:hidden;border:1.5px solid var(--border);border-radius:10px;background:var(--card);cursor:pointer;transition:.18s}
        .admin-product:hover{box-shadow:var(--sh-lift)}
        .admin-product.exposed{border-color:var(--ok);background:var(--ok-dim)}
        .admin-product-image{height:105px;display:grid;place-items:center;overflow:hidden;background:var(--bg);color:var(--faint)}
        .admin-product-image img{width:100%;height:100%;object-fit:cover}
        .admin-product-body{display:block;padding:.65rem .7rem .75rem;text-align:left}
        .admin-product-name{font-size:.76rem;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .admin-product-meta{display:flex;justify-content:space-between;gap:.4rem;margin-top:.25rem;color:var(--muted);font-size:.65rem}
        .admin-product-check{position:absolute;right:.5rem;top:.5rem;width:27px;height:27px;display:grid;place-items:center;border:2px solid var(--border);border-radius:7px;background:var(--card);color:#fff}
        .admin-product.exposed .admin-product-check{border-color:var(--ok);background:var(--ok)}
        .admin-product-check .material-symbols-outlined{font-size:.9rem}
        .products-empty{grid-column:1/-1;padding:2rem;text-align:center;color:var(--muted);font-size:.8rem}

        .bnav{display:none;position:fixed;bottom:0;left:0;right:0;background:var(--card);border-top:1px solid var(--border);z-index:300;padding:.35rem 0 calc(.35rem + env(safe-area-inset-bottom));box-shadow:0 -2px 16px rgba(15,28,46,.07)}
        .bnav-inner{display:flex;justify-content:space-around}
        .bnav-a{display:flex;flex-direction:column;align-items:center;gap:2px;padding:.35rem .35rem;color:var(--faint);font-size:.6rem;font-weight:600;flex:1}
        .bnav-a .material-symbols-outlined{font-size:1.2rem}
        .bnav-a.on{color:var(--p)}

        @media(max-width:1050px){
            .site-grid{grid-template-columns:1fr}
            .preview-card{position:static}
        }
        @media(max-width:860px){
            .sb{transform:translateX(-100%)}
            .sb.open{transform:translateX(0)}
            .main{margin-left:0}
            .ham{display:flex}
        }
        @media(max-width:640px){
            .main{padding:.9rem .9rem calc(72px + env(safe-area-inset-bottom))}
            .top{align-items:stretch}
            .top-actions{display:grid;grid-template-columns:1fr 1fr;width:100%}
            .top-actions .btn{padding:.55rem .5rem}
            .status-row{grid-template-columns:1fr}
            .templates{grid-template-columns:1fr}
            .form-grid,.media-grid{grid-template-columns:1fr}
            .benefit-editor{grid-template-columns:1fr}
            .channel-options{grid-template-columns:1fr}
            .coupon-create{grid-template-columns:1fr}
            .coupon-item{align-items:flex-start;flex-wrap:wrap}
            .coupon-actions{width:100%}
            .form-grid .wide{grid-column:auto}
            .channel-footer,.save-bar{align-items:stretch;flex-direction:column}
            .save-bar{position:static}
            .channel-save,.save-button{width:100%;min-width:0}
            .current-channel{align-self:flex-start}
            .section-heading{align-items:flex-start;flex-wrap:wrap}
            .products-toolbar .btn{flex:1}
            .admin-products{grid-template-columns:repeat(2,minmax(0,1fr))}
            .card{padding:1rem;border-radius:10px}
            .preview-card{padding:0}
            .phone-device{width:min(100%,270px);height:548px}
            .benefits{grid-template-columns:1fr}
            .bnav{display:block}
        }
        @media(max-width:380px){
            .top-actions{grid-template-columns:1fr}
            .greeting{font-size:1.18rem}
        }

        /* Nova hierarquia do editor Site PRO */
        .site-overview{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-bottom:1rem;padding:.72rem .9rem;border:1px solid var(--border);border-radius:10px;background:var(--card)}
        .overview-main{display:flex;align-items:center;gap:.7rem 1rem;min-width:0;flex-wrap:wrap}
        .state-pill{display:inline-flex;align-items:center;gap:.4rem;padding:.32rem .55rem;border-radius:999px;font-size:.68rem;font-weight:800;white-space:nowrap}
        .state-pill.published{background:var(--ok-dim);color:#087e53}.state-pill.paused{background:var(--bg);color:var(--muted)}
        .state-dot{width:7px;height:7px;border-radius:50%;background:currentColor}
        .overview-item{display:inline-flex;align-items:center;gap:.3rem;min-width:0;color:var(--muted);font-size:.7rem}
        .overview-item strong{color:var(--ink);font-size:.72rem}.overview-item .material-symbols-outlined{font-size:.9rem;color:var(--faint)}
        .overview-address{max-width:360px}.overview-address strong{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

        .builder-nav{display:flex;align-items:stretch;margin-bottom:1rem;overflow-x:auto;border:1px solid var(--border);border-radius:10px;background:var(--card);box-shadow:none;scrollbar-width:none}
        .builder-nav::-webkit-scrollbar{display:none}
        .builder-nav-link{position:relative;min-height:44px;display:flex;align-items:center;justify-content:center;gap:.42rem;flex:1 0 auto;padding:.58rem .82rem;border:0;border-bottom:2px solid transparent;background:transparent;color:var(--muted);font-size:.7rem;font-weight:750;white-space:nowrap;transition:background .16s,color .16s,border-color .16s}
        .builder-nav-link:hover{background:var(--p-dim);color:var(--p)}
        .builder-nav-link.on{border-bottom-color:var(--p);background:var(--p-dim);color:var(--p)}
        .builder-nav-link .material-symbols-outlined{font-size:.95rem}
        .site-grid{display:grid;grid-template-columns:minmax(520px,1fr) minmax(320px,380px);gap:1rem;align-items:start}
        .editor-stack{display:grid;gap:.85rem;min-width:0}
        .editor-section{box-shadow:none}
        .editor-section[hidden]{display:none}
        .editor-section .section-heading{padding-bottom:.8rem;margin-bottom:.35rem}
        .editor-section .section-heading h2{font-size:.94rem}.editor-section .section-heading p{font-size:.68rem}
        .setting-group{padding-top:1rem;margin-top:1rem;border-top:1px solid var(--border)}.setting-group.first{padding-top:.35rem;margin-top:0;border-top:0}
        .setting-copy strong,.setting-copy span{display:block}.setting-copy strong{font-size:.78rem}.setting-copy span{margin-top:.1rem;color:var(--muted);font-size:.67rem}
        .channel-segmented{display:inline-grid;grid-template-columns:1fr 1fr;margin-top:.65rem;padding:3px;border:1px solid var(--border);border-radius:9px;background:var(--bg)}
        .channel-segmented label{position:relative;cursor:pointer}.channel-segmented input{position:absolute;opacity:0;pointer-events:none}
        .channel-segmented label>span{min-width:130px;display:flex;align-items:center;justify-content:center;gap:.35rem;padding:.52rem .75rem;border-radius:6px;color:var(--muted);font-size:.7rem;font-weight:800;transition:.16s}
        .channel-segmented .material-symbols-outlined{font-size:.95rem}.channel-segmented input:checked+span{background:var(--card);color:var(--p);box-shadow:0 1px 5px rgba(15,28,46,.08)}
        .channel-segmented input:focus-visible+span{outline:2px solid var(--p);outline-offset:1px}
        .inline-note{display:flex;align-items:center;gap:.35rem;margin-top:.55rem;color:var(--faint);font-size:.64rem}.inline-note .material-symbols-outlined{font-size:.85rem}
        .compact-row{margin-top:.65rem;background:var(--surface-soft);border:1px solid var(--border)}
        .builder-subhead.no-border{margin-top:.2rem;padding-top:0;border-top:0}
        .media-grid-featured{grid-template-columns:minmax(0,1.65fr) minmax(190px,.75fr)}
        .media-grid-featured .banner-box .media-thumb{height:132px}.media-grid-featured .logo-box .media-thumb{height:132px}
        .templates{gap:.6rem}.template-box{min-height:135px}.template input:checked+.template-box{box-shadow:0 0 0 2px var(--p-dim)}
        .benefits-editor-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:.6rem;margin-top:.75rem}
        .benefits-editor-grid .benefit-editor{display:block;margin-top:0;padding:.7rem;background:var(--surface-soft)}
        .benefits-editor-grid .benefit-editor .field{margin-top:.55rem}.benefits-editor-grid .benefit-editor .field:first-child{margin-top:0}
        .benefits-editor-grid .benefit-editor label{font-size:.62rem}.benefits-editor-grid .benefit-editor input,.benefits-editor-grid .benefit-editor select{padding:.55rem .65rem;font-size:.75rem}
        .checkout-options .channel-card{min-height:104px;padding:.72rem}.checkout-options .channel-icon{width:32px;height:32px;flex-basis:32px}
        .publication-section .publish-row{margin-top:.3rem;border:1px solid var(--border);background:var(--surface-soft)}
        .preview-card{top:1rem;box-shadow:none;overflow:visible}.preview-head{padding:.7rem .85rem}
        .preview-device-stage{display:grid;place-items:center;padding:1rem .75rem 1.15rem;background:linear-gradient(180deg,var(--surface-soft),var(--bg));border-radius:0 0 var(--r) var(--r)}
        .phone-device{position:relative;width:min(100%,264px);height:548px;padding:7px;border-radius:32px;background:#111a2a;box-shadow:0 18px 34px rgba(15,28,46,.18),0 3px 8px rgba(15,28,46,.12);transition:box-shadow .25s,transform .25s}
        .preview-card.is-editing .phone-device{box-shadow:0 20px 42px color-mix(in srgb,var(--store-color) 16%,rgba(15,28,46,.16)),0 0 0 2px color-mix(in srgb,var(--store-color) 34%,transparent)}
        .phone-screen{position:relative;height:100%;display:flex;flex-direction:column;overflow:hidden;border-radius:25px;background:#fff}
        .phone-notch{position:absolute;z-index:7;top:7px;left:50%;width:92px;height:20px;transform:translateX(-50%);border-radius:0 0 11px 11px;background:#111a2a;pointer-events:none}
        .phone-browser{position:relative;z-index:5;flex:0 0 40px;display:flex;align-items:flex-end;justify-content:center;padding:0 12px 7px;background:#fff;border-bottom:1px solid rgba(15,28,46,.08);color:#8390a3}
        .phone-browser-address{min-width:0;max-width:190px;display:flex;align-items:center;justify-content:center;gap:3px;font-size:.49rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .phone-browser-address .material-symbols-outlined{font-size:.58rem;color:#8190a6}
        .phone-browser-url{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .phone-screen .preview-viewport{flex:1;height:auto;min-height:0;overflow:hidden;border:0;border-radius:0;background:var(--preview-stage-bg,#f6f6f3);isolation:isolate;transition:background .3s}
        .phone-home{position:absolute;z-index:8;left:50%;bottom:5px;width:82px;height:3px;transform:translateX(-50%);border-radius:99px;background:rgba(255,255,255,.74);mix-blend-mode:difference;pointer-events:none}
        .phone-caption{display:flex;align-items:center;justify-content:center;gap:.32rem;margin-top:.72rem;color:var(--faint);font-size:.62rem;font-weight:650}
        .phone-caption .material-symbols-outlined{font-size:.78rem}
        .save-bar[hidden]{display:none}.save-bar{position:sticky;bottom:.75rem;margin-left:0;margin-right:396px;z-index:40}
        .save-actions{display:flex;align-items:center;gap:.5rem}.save-button{min-width:170px}

        @media(max-width:1380px){
            .site-grid{grid-template-columns:minmax(0,1fr) minmax(320px,350px)}
            .builder-nav-link{flex:0 0 auto;padding:.56rem .72rem}
            .save-bar{margin-right:366px}
        }
        @media(max-width:1050px){
            .site-grid{grid-template-columns:1fr}.preview-card{position:static}.save-bar{margin-right:0}.phone-device{width:min(100%,286px);height:570px}
        }
        @media(max-width:700px){
            .site-overview{align-items:flex-start}.overview-main{gap:.5rem .7rem}.overview-address{max-width:100%;width:100%}
            .builder-nav{margin-bottom:.75rem}.builder-nav-link{min-height:40px;padding:.52rem .68rem}.builder-nav-link .material-symbols-outlined{display:none}
            .media-grid-featured,.benefits-editor-grid{grid-template-columns:1fr}.channel-segmented{display:grid;width:100%}.channel-segmented label>span{min-width:0}
            .save-actions{width:100%}.save-actions .btn{flex:1}.save-bar{align-items:stretch}
        }
    </style>
</head>
<body>
<div class="app">
    <div class="overlay" id="ov"></div>
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="main">
        <header class="top">
            <div class="top-left">
                <button class="ham" id="ham" type="button" aria-label="Abrir menu">
                    <span class="material-symbols-outlined">menu</span>
                </button>
                <div>
                    <div class="greeting">Meu Site PRO</div>
                    <div class="sub-txt">Monte sua página comercial usando os produtos da vitrine.</div>
                </div>
            </div>
            <?php if ($isPro): ?>
                <div class="top-actions">
                    <?php if ($siteUrl): ?>
                        <button class="btn" id="openPublicAddress" type="button" data-url="<?= htmlspecialchars($siteUrl) ?>">
                            <span class="material-symbols-outlined">open_in_new</span>Abrir endereço
                        </button>
                    <?php endif; ?>
                    <a class="btn" href="vitrine_config.php">
                        <span class="material-symbols-outlined">storefront</span>Editar vitrine
                    </a>
                </div>
            <?php endif; ?>
        </header>

        <?php if (!$isPro): ?>
            <section class="locked card">
                <div class="lock-icon"><span class="material-symbols-outlined">web</span></div>
                <h1>Crie o site comercial da sua loja</h1>
                <p>No Plano PRO, seus produtos e estoque viram uma página de vendas pronta para receber clientes e levar o pedido ao WhatsApp.</p>
                <a class="btn primary" href="planos.php">
                    <span class="material-symbols-outlined">rocket_launch</span>Conhecer Plano PRO
                </a>
                <div class="benefits">
                    <div class="benefit"><span class="material-symbols-outlined">check_circle</span><span>Modelos prontos para comércio</span></div>
                    <div class="benefit"><span class="material-symbols-outlined">check_circle</span><span>Produtos ligados ao estoque</span></div>
                    <div class="benefit"><span class="material-symbols-outlined">check_circle</span><span>Pedido finalizado no WhatsApp</span></div>
                </div>
            </section>
        <?php else: ?>
            <section class="site-overview" aria-label="Resumo do Site PRO">
                <div class="overview-main">
                    <span class="state-pill <?= $config['ativo'] ? 'published' : 'paused' ?>" id="overviewPublishState">
                        <span class="state-dot"></span><?= $config['ativo'] ? 'Publicado' : 'Pausado' ?>
                    </span>
                    <span class="overview-item"><span class="material-symbols-outlined">public</span><span>Canal</span><strong id="overviewChannel"><?= $config['canal_publico'] === 'site_pro' ? 'Site PRO' : 'Vitrine' ?></strong></span>
                    <span class="overview-item"><span class="material-symbols-outlined">inventory_2</span><strong><?= $productCount ?></strong><span>produto<?= $productCount === 1 ? '' : 's' ?> publicado<?= $productCount === 1 ? '' : 's' ?></span></span>
                    <span class="overview-item overview-address"><span class="material-symbols-outlined">link</span><strong><?= $siteSlug !== '' ? htmlspecialchars('uplar.cloud/' . $siteSlug) : 'Endereço não configurado' ?></strong></span>
                </div>
            </section>

            <?php if ($erro): ?>
                <div class="notice err" role="alert"><span class="material-symbols-outlined">error</span><?= htmlspecialchars($erro) ?></div>
            <?php endif; ?>
            <?php if ($sucesso): ?>
                <div class="notice ok" role="status"><span class="material-symbols-outlined">check_circle</span><?= htmlspecialchars($sucesso) ?></div>
            <?php endif; ?>

            <div class="section-tabs" role="tablist" aria-label="Configuração do Site PRO">
                <button class="section-tab on" id="tabSite" type="button" role="tab" aria-selected="true" aria-controls="panelSite" data-panel="site"><span class="material-symbols-outlined">tune</span>Site</button>
                <button class="section-tab" id="tabProducts" type="button" role="tab" aria-selected="false" aria-controls="panelProducts" data-panel="products"><span class="material-symbols-outlined">inventory_2</span>Produtos</button>
                <button class="section-tab" id="tabCoupons" type="button" role="tab" aria-selected="false" aria-controls="panelCoupons" data-panel="coupons"><span class="material-symbols-outlined">sell</span>Cupons</button>
            </div>

            <section class="tab-panel" id="panelSite" role="tabpanel" aria-labelledby="tabSite">
                <form class="builder-form" id="siteProForm" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="slug_site" value="<?= htmlspecialchars($siteSlug) ?>">

                    <nav class="builder-nav" role="tablist" aria-label="Seções do Site PRO">
                        <button class="builder-nav-link on" id="editorTabGeneral" type="button" role="tab" aria-selected="true" aria-controls="site-general" data-editor-section="site-general"><span class="material-symbols-outlined">tune</span>Geral</button>
                        <button class="builder-nav-link" id="editorTabAppearance" type="button" role="tab" aria-selected="false" aria-controls="site-appearance" data-editor-section="site-appearance"><span class="material-symbols-outlined">palette</span>Aparência</button>
                        <button class="builder-nav-link" id="editorTabHome" type="button" role="tab" aria-selected="false" aria-controls="site-home" data-editor-section="site-home"><span class="material-symbols-outlined">web_asset</span>Página inicial</button>
                        <button class="builder-nav-link" id="editorTabDifferentials" type="button" role="tab" aria-selected="false" aria-controls="site-differentials" data-editor-section="site-differentials"><span class="material-symbols-outlined">workspace_premium</span>Diferenciais</button>
                        <button class="builder-nav-link" id="editorTabCheckout" type="button" role="tab" aria-selected="false" aria-controls="site-checkout" data-editor-section="site-checkout"><span class="material-symbols-outlined">payments</span>Checkout</button>
                        <button class="builder-nav-link" id="editorTabDelivery" type="button" role="tab" aria-selected="false" aria-controls="site-delivery" data-editor-section="site-delivery"><span class="material-symbols-outlined">local_shipping</span>Entrega</button>
                        <button class="builder-nav-link" id="editorTabPublication" type="button" role="tab" aria-selected="false" aria-controls="site-publication" data-editor-section="site-publication"><span class="material-symbols-outlined">publish</span>Publicação</button>
                    </nav>

                    <div class="site-grid">
                        <div class="editor-stack">
                            <section class="card builder-card editor-section" id="site-general" role="tabpanel" aria-labelledby="editorTabGeneral">
                                <div class="section-heading compact">
                                    <div><h2>Geral</h2><p>Defina qual experiência fica pública e como o catálogo aparece para o cliente.</p></div>
                                    <span class="current-channel"><?= $config['canal_publico'] === 'site_pro' ? 'Site PRO ativo' : 'Vitrine ativa' ?></span>
                                </div>

                                <div class="setting-group first">
                                    <div class="setting-copy">
                                        <strong>Canal público</strong>
                                        <span>Escolha o que seus clientes verão em uplar.cloud/<?= htmlspecialchars($siteSlug ?: 'sua-loja') ?>.</span>
                                    </div>
                                    <div class="channel-segmented" role="radiogroup" aria-label="Canal público">
                                        <label>
                                            <input type="radio" name="canal_publico" value="vitrine" <?= $config['canal_publico'] === 'vitrine' ? 'checked' : '' ?>>
                                            <span><span class="material-symbols-outlined">storefront</span>Vitrine</span>
                                        </label>
                                        <label>
                                            <input type="radio" name="canal_publico" value="site_pro" <?= $config['canal_publico'] === 'site_pro' ? 'checked' : '' ?>>
                                            <span><span class="material-symbols-outlined">web</span>Site PRO</span>
                                        </label>
                                    </div>
                                    <div class="inline-note"><span class="material-symbols-outlined">shield</span>Alternar o canal não apaga nenhuma configuração.</div>
                                </div>

                                <div class="setting-group">
                                    <div class="setting-copy">
                                        <strong>Exibição do catálogo</strong>
                                        <span>Controle se a quantidade disponível aparece para quem visita o site.</span>
                                    </div>
                                    <div class="publish-row compact-row">
                                        <div><div class="publish-title">Mostrar estoque</div><div class="publish-sub">Exibe a quantidade disponível dos produtos.</div></div>
                                        <label class="toggle" aria-label="Mostrar estoque">
                                            <input type="checkbox" name="mostrar_estoque" value="1" <?= $config['mostrar_estoque'] ? 'checked' : '' ?> data-preview-zone="catalog">
                                            <span class="toggle-track"></span>
                                        </label>
                                    </div>
                                </div>
                            </section>

                            <section class="card builder-card editor-section" id="site-appearance" role="tabpanel" aria-labelledby="editorTabAppearance" hidden>
                                <div class="section-heading compact">
                                    <div><h2>Aparência</h2><p>Configure marca, cores, tipografia, imagens e o modelo visual do Site PRO.</p></div>
                                </div>
<div class="builder-subhead">
                        <span class="material-symbols-outlined">brand_awareness</span>
                        <div><strong>Identidade da marca</strong><span>Nome, descrição, cores e tipografia.</span></div>
                    </div>
                    <div class="form-grid">
                        <div class="field wide">
                            <label for="nome_site">Nome da marca</label>
                            <input id="nome_site" name="nome_site" maxlength="120" required value="<?= htmlspecialchars($config['nome_site']) ?>" data-preview-target="pvName" data-preview-zone="brand">
                        </div>
                        <div class="field wide">
                            <label for="descricao_site">Descrição da loja</label>
                            <textarea id="descricao_site" name="descricao_site" maxlength="500" data-preview-target="pvDescription" data-preview-zone="description" data-preview-fallback="Produtos disponíveis para pedir agora."><?= htmlspecialchars($config['descricao_site']) ?></textarea>
                        </div>
                        <div class="field">
                            <span class="field-label">Cor principal</span>
                            <div class="color-row">
                                <?php foreach (['#3b6ff5', '#0877f9', '#7c3aed', '#10b981', '#e85d04'] as $color): ?>
                                    <button class="swatch <?= strtolower($config['cor_primaria']) === $color ? 'on' : '' ?>" type="button" data-color="<?= $color ?>" style="background:<?= $color ?>" aria-label="Usar cor <?= $color ?>"></button>
                                <?php endforeach; ?>
                                <input class="color-input" id="cor_primaria" name="cor_primaria" type="color" value="<?= htmlspecialchars($config['cor_primaria']) ?>" aria-label="Escolher outra cor" data-preview-zone="hero">
                            </div>
                        </div>
                        <div class="field">
                            <label for="fonte_site">Fonte do site</label>
                            <select id="fonte_site" name="fonte_site" data-preview-zone="hero">
                                <?php foreach (['tema' => 'Padrão do modelo', 'manrope' => 'Manrope — moderna', 'inter' => 'Inter — neutra', 'archivo' => 'Archivo — forte', 'cormorant' => 'Cormorant — elegante'] as $fontKey => $fontLabel): ?>
                                    <option value="<?= $fontKey ?>" <?= $config['fonte_site'] === $fontKey ? 'selected' : '' ?>><?= htmlspecialchars($fontLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="field wide">
                            <span class="field-label">Cor de fundo do catálogo</span>
                            <div class="background-control">
                                <input class="color-input" id="cor_fundo" name="cor_fundo" type="color" value="<?= htmlspecialchars($config['cor_fundo'] ?: '#f4f4f1') ?>" aria-label="Escolher cor de fundo" data-preview-zone="catalog">
                                <label class="check-line"><input id="fundo_personalizado" name="fundo_personalizado" type="checkbox" value="1" <?= $config['cor_fundo'] !== '' ? 'checked' : '' ?> data-preview-zone="catalog">Usar cor personalizada</label>
                            </div>
                            <span class="field-hint">Desmarcado, o fundo recomendado pelo modelo será usado.</span>
                        </div>
                    </div>

                    
                                <div class="builder-subhead">
                                    <span class="material-symbols-outlined">imagesmode</span>
                                    <div><strong>Imagens da marca</strong><span>Banner principal e logo usados exclusivamente no Site PRO.</span></div>
                                </div>
<div class="field">
                        <div class="media-grid media-grid-featured">
                            <div class="media-box banner-box">
                                <div class="media-head">
                                    <span class="media-title"><span class="material-symbols-outlined">landscape</span>Banner</span>
                                    <span class="media-ratio">3:1</span>
                                </div>
                                <button class="media-thumb" id="bannerPreviewBox" type="button" aria-label="Escolher ou arrastar banner">
                                    <span class="media-empty" id="bannerPreviewEmpty" <?= $config['banner_path'] ? 'hidden' : '' ?>><span class="material-symbols-outlined">add_photo_alternate</span>Clique ou arraste uma imagem</span>
                                    <img id="bannerPreviewImg" src="<?= htmlspecialchars($config['banner_path']) ?>" alt="Prévia do banner" <?= $config['banner_path'] ? '' : 'hidden' ?>>
                                </button>
                                <div class="media-actions">
                                    <button class="media-action primary" type="button" data-image-choose="banner"><span class="material-symbols-outlined">upload</span><span data-image-label="banner"><?= $config['banner_path'] ? 'Trocar' : 'Escolher' ?></span></button>
                                    <button class="media-action" type="button" data-image-edit="banner" <?= $config['banner_path'] ? '' : 'hidden' ?>><span class="material-symbols-outlined">crop</span>Ajustar</button>
                                </div>
                                <input id="bannerInp" class="media-file-native" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-preview-zone="hero">
                                <div class="media-pending" id="bannerPending" hidden><span class="material-symbols-outlined">pending</span>Alteração não salva</div>
                                <div class="media-note">Recomendado: 1500 × 500 px. JPG, PNG ou WebP até 5 MB.</div>
                            </div>
                            <div class="media-box logo-box">
                                <div class="media-head">
                                    <span class="media-title"><span class="material-symbols-outlined">account_circle</span>Logo</span>
                                    <span class="media-ratio">1:1</span>
                                </div>
                                <button class="media-thumb logo" id="logoPreviewBox" type="button" aria-label="Escolher ou arrastar logo">
                                    <span class="media-empty" id="logoPreviewEmpty" <?= $config['logo_path'] ? 'hidden' : '' ?>><span class="material-symbols-outlined">add_photo_alternate</span>Clique ou arraste uma imagem</span>
                                    <img id="logoPreviewImg" src="<?= htmlspecialchars($config['logo_path']) ?>" alt="Prévia da logo" <?= $config['logo_path'] ? '' : 'hidden' ?>>
                                </button>
                                <div class="media-actions">
                                    <button class="media-action primary" type="button" data-image-choose="profile"><span class="material-symbols-outlined">upload</span><span data-image-label="profile"><?= $config['logo_path'] ? 'Trocar' : 'Escolher' ?></span></button>
                                    <button class="media-action" type="button" data-image-edit="profile" <?= $config['logo_path'] ? '' : 'hidden' ?>><span class="material-symbols-outlined">crop</span>Ajustar</button>
                                </div>
                                <input id="logoInp" class="media-file-native" type="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" data-preview-zone="brand">
                                <div class="media-pending" id="logoPending" hidden><span class="material-symbols-outlined">pending</span>Alteração não salva</div>
                                <div class="media-note">Recomendado: 600 × 600 px. JPG, PNG ou WebP até 3 MB.</div>
                            </div>
                        </div>
                    </div>

                    
                                <div class="builder-subhead">
                                    <span class="material-symbols-outlined">dashboard_customize</span>
                                    <div><strong>Modelo visual</strong><span>Escolha a estrutura que melhor representa o tipo de loja.</span></div>
                                </div>
<div class="field">
                        <span class="field-label">Modelo visual</span>
                        <div class="templates">
                            <label class="template">
                                <input type="radio" name="modelo" value="venda_direta" <?= $config['modelo'] === 'venda_direta' ? 'checked' : '' ?> data-preview-zone="hero">
                                <span class="template-box">
                                    <span class="template-preview"></span>
                                    <span class="template-title">Loja Essencial</span>
                                    <span class="template-text">Comércio geral com catálogo equilibrado.</span>
                                </span>
                            </label>
                            <label class="template">
                                <input type="radio" name="modelo" value="catalogo_editorial" <?= $config['modelo'] === 'catalogo_editorial' ? 'checked' : '' ?> data-preview-zone="hero">
                                <span class="template-box">
                                    <span class="template-preview editorial"></span>
                                    <span class="template-title">Boutique Editorial</span>
                                    <span class="template-text">Moda, beleza, joias e marcas autorais.</span>
                                </span>
                            </label>
                            <label class="template">
                                <input type="radio" name="modelo" value="impacto_esportivo" <?= $config['modelo'] === 'impacto_esportivo' ? 'checked' : '' ?> data-preview-zone="hero">
                                <span class="template-box">
                                    <span class="template-preview sport"></span>
                                    <span class="template-title">Impacto Esportivo</span>
                                    <span class="template-text">Esportes, streetwear, suplementos e academia.</span>
                                </span>
                            </label>
                            <label class="template">
                                <input type="radio" name="modelo" value="catalogo_compacto" <?= $config['modelo'] === 'catalogo_compacto' ? 'checked' : '' ?> data-preview-zone="hero">
                                <span class="template-box">
                                    <span class="template-preview compact"></span>
                                    <span class="template-title">Catálogo Compacto</span>
                                    <span class="template-text">Alimentos, utilidades, variedades e muitos itens.</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    
                            </section>

                            <section class="card builder-card editor-section" id="site-home" role="tabpanel" aria-labelledby="editorTabHome" hidden>
                                <div class="section-heading compact">
                                    <div><h2>Página inicial</h2><p>Defina a mensagem principal e os avisos que aparecem antes do catálogo.</p></div>
                                </div>
                                <div class="builder-subhead no-border">
                                    <span class="material-symbols-outlined">title</span>
                                    <div><strong>Mensagem principal</strong><span>O conteúdo de maior destaque na abertura do site.</span></div>
                                </div>
<div class="field">
                        <label for="titulo">Título principal</label>
                        <input id="titulo" name="titulo" maxlength="100" required value="<?= htmlspecialchars($config['titulo']) ?>" data-preview-target="pvTitle" data-preview-zone="title">
                        <span class="field-hint">A primeira mensagem que seu cliente verá.</span>
                    </div>
                    <div class="field">
                        <label for="subtitulo">Texto de apoio</label>
                        <textarea id="subtitulo" name="subtitulo" maxlength="240" data-preview-target="pvSub" data-preview-zone="subtitle"><?= htmlspecialchars($config['subtitulo']) ?></textarea>
                    </div>
                    <div class="field">
                        <label for="texto_cta">Texto do botão principal</label>
                        <input id="texto_cta" name="texto_cta" maxlength="40" value="<?= htmlspecialchars($config['texto_cta']) ?>" data-preview-target="pvCta" data-preview-zone="cta">
                    </div>

                                <div class="builder-subhead">
                                    <span class="material-symbols-outlined">campaign</span>
                                    <div><strong>Faixa superior</strong><span>Use para promoções, condições especiais ou mensagens rápidas.</span></div>
                                </div>
                                <div class="form-grid">
                                    <div class="field">
                                        <label for="aviso_principal">Mensagem principal</label>
                                        <input id="aviso_principal" name="aviso_principal" maxlength="100" value="<?= htmlspecialchars($config['aviso_principal']) ?>" placeholder="Ex.: Frete grátis acima de R$ 150" data-preview-target="pvPrimaryNotice" data-preview-zone="utility">
                                    </div>
                                    <div class="field">
                                        <label for="aviso_secundario">Mensagem complementar</label>
                                        <input id="aviso_secundario" name="aviso_secundario" maxlength="140" value="<?= htmlspecialchars($config['aviso_secundario']) ?>" placeholder="Ex.: Use o cupom PRIMEIRACOMPRA" data-preview-target="pvSecondaryNotice" data-preview-zone="utility">
                                    </div>
                                </div>
                            </section>

                            <section class="card builder-card editor-section" id="site-differentials" role="tabpanel" aria-labelledby="editorTabDifferentials" hidden>
                                <div class="section-heading compact">
                                    <div><h2>Diferenciais</h2><p>Mostre três argumentos curtos que ajudam o cliente a confiar e comprar.</p></div>
                                </div>
                        <div class="builder-subhead">
                            <span class="material-symbols-outlined">workspace_premium</span>
                            <div><strong>Diferenciais da loja</strong><span>Escolha um ícone e edite os três argumentos exibidos após o banner.</span></div>
                        </div>
                        <div class="publish-row">
                            <div><div class="publish-title">Exibir diferenciais</div><div class="publish-sub">O conteúdo fica salvo mesmo quando esta seção estiver oculta.</div></div>
                            <label class="toggle" aria-label="Exibir diferenciais">
                                <input id="showBenefits" type="checkbox" name="mostrar_beneficios" value="1" <?= $config['mostrar_beneficios'] ? 'checked' : '' ?> data-preview-zone="commerce">
                                <span class="toggle-track"></span>
                            </label>
                        </div>
                        <div class="benefits-editor-grid">
                        <?php foreach ($config['beneficios'] as $benefitIndex => $benefit): ?>
                            <div class="benefit-editor">
                                <div class="field">
                                    <label for="beneficio_icone_<?= $benefitIndex ?>">Ícone <?= $benefitIndex + 1 ?></label>
                                    <select id="beneficio_icone_<?= $benefitIndex ?>" name="beneficio_icone[]" data-preview-icon-target="pvBenefitIcon<?= $benefitIndex ?>" data-preview-zone="commerce">
                                        <?php foreach (site_pro_benefit_icon_options() as $iconKey => $iconLabel): ?>
                                            <option value="<?= htmlspecialchars($iconKey) ?>" <?= $benefit['icone'] === $iconKey ? 'selected' : '' ?>><?= htmlspecialchars($iconLabel) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="field">
                                    <label for="beneficio_titulo_<?= $benefitIndex ?>">Título</label>
                                    <input id="beneficio_titulo_<?= $benefitIndex ?>" name="beneficio_titulo[]" maxlength="60" required value="<?= htmlspecialchars($benefit['titulo']) ?>" data-preview-target="pvBenefitTitle<?= $benefitIndex ?>" data-preview-zone="commerce">
                                </div>
                                <div class="field">
                                    <label for="beneficio_texto_<?= $benefitIndex ?>">Texto de apoio</label>
                                    <input id="beneficio_texto_<?= $benefitIndex ?>" name="beneficio_texto[]" maxlength="120" required value="<?= htmlspecialchars($benefit['texto']) ?>" data-preview-target="pvBenefitText<?= $benefitIndex ?>" data-preview-zone="commerce">
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                            </section>

                            <section class="card builder-card editor-section" id="site-checkout" role="tabpanel" aria-labelledby="editorTabCheckout" hidden>
                                <div class="section-heading compact">
                                    <div><h2>Checkout</h2><p>Escolha como o cliente conclui o pedido e configure o pagamento online.</p></div>
                                </div>
<div class="builder-subhead">
                        <span class="material-symbols-outlined">payments</span>
                        <div><strong>Como o cliente finaliza</strong><span>Escolha entre atendimento no WhatsApp, pagamento online ou as duas opções.</span></div>
                    </div>
                    <div class="channel-options checkout-options">
                        <label class="channel-option">
                            <input type="radio" name="finalizacao_pedido" value="whatsapp" <?= $config['finalizacao_pedido'] === 'whatsapp' ? 'checked' : '' ?> data-preview-zone="commerce">
                            <span class="channel-card">
                                <span class="channel-icon"><span class="material-symbols-outlined">chat</span></span>
                                <span class="channel-copy"><strong>WhatsApp</strong><span>O pedido chega pronto para a loja concluir o atendimento.</span></span>
                            </span>
                        </label>
                        <label class="channel-option">
                            <input type="radio" name="finalizacao_pedido" value="infinitepay" <?= $config['finalizacao_pedido'] === 'infinitepay' ? 'checked' : '' ?> data-preview-zone="commerce">
                            <span class="channel-card">
                                <span class="channel-icon"><span class="material-symbols-outlined">credit_card</span></span>
                                <span class="channel-copy"><strong>InfinitePay</strong><span>Checkout online com Pix ou cartão direto para sua conta.</span></span>
                            </span>
                        </label>
                        <label class="channel-option">
                            <input type="radio" name="finalizacao_pedido" value="ambos" <?= $config['finalizacao_pedido'] === 'ambos' ? 'checked' : '' ?> data-preview-zone="commerce">
                            <span class="channel-card">
                                <span class="channel-icon"><span class="material-symbols-outlined">conversion_path</span></span>
                                <span class="channel-copy"><strong>Oferecer os dois</strong><span>O cliente escolhe pagar online ou conversar com a loja.</span></span>
                            </span>
                        </label>
                    </div>
                    <div class="payment-config" id="infinitePayConfig" <?= $config['finalizacao_pedido'] === 'whatsapp' ? 'hidden' : '' ?>>
                        <div class="field" style="margin-top:0">
                            <label for="infinitepay_tag">Sua InfiniteTag</label>
                            <div class="infinite-tag-input">
                                <span>$</span>
                                <input id="infinitepay_tag" name="infinitepay_tag" maxlength="80" autocomplete="off" value="<?= htmlspecialchars($config['infinitepay_tag']) ?>" placeholder="nomedaloja" data-preview-zone="commerce">
                            </div>
                            <span class="field-hint">Use a tag da conta que receberá os pagamentos, sem o símbolo $.</span>
                        </div>
                        <details class="infinite-help">
                            <summary><span class="material-symbols-outlined">help</span>Onde encontro minha InfiniteTag?</summary>
                            <div class="infinite-help-body">
                                No app InfinitePay, deslize a tela para baixo e acesse <strong>Ajustes → Seus dados → InfiniteTag</strong>. Copie somente o nome, sem o símbolo $.
                                <br><a href="https://ajuda.infinitepay.io/pt-BR/articles/4957195-o-que-e-a-infinitetag" target="_blank" rel="noopener noreferrer">Ver instruções oficiais da InfinitePay</a>
                            </div>
                        </details>
                        <div class="payment-note"><span class="material-symbols-outlined">verified_user</span><span>O pagamento é processado pela InfinitePay e vai direto para a conta vinculada a esta tag. O Checkout Integrado precisa estar habilitado na sua conta InfinitePay.</span></div>
                    </div>
                    
                            </section>

                            <section class="card builder-card editor-section" id="site-delivery" role="tabpanel" aria-labelledby="editorTabDelivery" hidden>
                                <div class="section-heading compact">
                                    <div><h2>Atendimento e entrega</h2><p>Informe os dados usados no contato, retirada e cálculo padrão de frete.</p></div>
                                </div>
<div class="builder-subhead">
                        <span class="material-symbols-outlined">local_shipping</span>
                        <div><strong>Atendimento e entrega</strong><span>Dados exibidos e usados na preparação do pedido.</span></div>
                    </div>
                    <div class="form-grid">
                        <div class="field wide">
                            <label for="whatsapp">WhatsApp para pedidos</label>
                            <input id="whatsapp" name="whatsapp" maxlength="30" inputmode="tel" value="<?= htmlspecialchars($config['whatsapp']) ?>" placeholder="5592999999999" data-preview-zone="commerce">
                        </div>
                        <div class="field">
                            <label for="endereco">Endereço para retirada</label>
                            <input id="endereco" name="endereco" maxlength="255" value="<?= htmlspecialchars($config['endereco']) ?>" data-preview-zone="commerce">
                        </div>
                        <div class="field">
                            <label for="cidade_uf">Cidade / UF</label>
                            <input id="cidade_uf" name="cidade_uf" maxlength="80" value="<?= htmlspecialchars($config['cidade_uf']) ?>" data-preview-zone="commerce">
                        </div>
                        <div class="field">
                            <label for="valor_frete">Valor padrão do frete</label>
                            <input id="valor_frete" name="valor_frete" type="number" min="0" step="0.01" value="<?= htmlspecialchars(number_format($config['valor_frete'], 2, '.', '')) ?>" data-preview-zone="commerce">
                        </div>
                    </div>

                    
                            </section>

                            <section class="card builder-card editor-section publication-section" id="site-publication" role="tabpanel" aria-labelledby="editorTabPublication" hidden>
                                <div class="section-heading compact">
                                    <div><h2>Publicação</h2><p>Controle a disponibilidade do Site PRO sem perder suas configurações.</p></div>
                                </div>
<div class="publish-row">
                        <div>
                            <div class="publish-title">Site publicado</div>
                            <div class="publish-sub">Desative para ocultar temporariamente a página.</div>
                        </div>
                        <label class="toggle" aria-label="Publicar site">
                            <input id="siteAtivo" type="checkbox" name="ativo" value="1" <?= $config['ativo'] ? 'checked' : '' ?> data-preview-zone="commerce">
                            <span class="toggle-track"></span>
                        </label>
                    </div>

                            </section>
                        </div>

                        <aside class="card preview-card" id="previewCard" style="--store-color:<?= htmlspecialchars($config['cor_primaria']) ?><?= $config['cor_fundo'] !== '' ? ';--store-bg:' . htmlspecialchars($config['cor_fundo']) : '' ?>">
                    <div class="preview-head">
                        <strong><span class="material-symbols-outlined">smartphone</span>Pré-visualização</strong>
                        <span class="preview-state" id="previewState"><?= $config['ativo'] ? 'Publicado' : 'Pausado' ?></span>
                    </div>
                    <div class="preview-device-stage">
                        <div class="phone-device" aria-label="Simulação do Site PRO em um celular">
                            <span class="phone-notch" aria-hidden="true"></span>
                            <div class="phone-screen">
                                <div class="phone-browser" aria-hidden="true">
                                    <span class="phone-browser-address">
                                        <span class="material-symbols-outlined">lock</span>
                                        <span class="phone-browser-url"><?= $siteSlug !== '' ? htmlspecialchars('uplar.cloud/' . $siteSlug) : 'uplar.cloud/sua-loja' ?></span>
                                    </span>
                                </div>
                                <div class="preview-viewport" id="previewViewport">
                        <div class="preview-site model-<?= htmlspecialchars(str_replace('_', '-', $config['modelo'])) ?> font-<?= htmlspecialchars($config['fonte_site'] . $previewBackgroundClass) ?>" id="previewSite">
                            <div class="pv-utility" id="pvUtility" data-preview-zone="utility" data-preview-hide-when-empty <?= $config['aviso_principal'] === '' && $config['aviso_secundario'] === '' ? 'hidden' : '' ?>>
                                <span id="pvPrimaryNotice" data-preview-value><?= htmlspecialchars($config['aviso_principal']) ?></span>
                                <span id="pvSecondaryNotice" data-preview-value><?= htmlspecialchars($config['aviso_secundario']) ?></span>
                            </div>
                            <div class="pv-nav">
                                <div class="pv-brand pv-nav-brand" data-preview-zone="brand">
                                    <span class="pv-brand-logo">
                                        <img id="pvLogoImg" src="<?= htmlspecialchars($config['logo_path']) ?>" alt="" <?= $config['logo_path'] ? '' : 'hidden' ?>>
                                        <span id="pvLogoFallback" <?= $config['logo_path'] ? 'hidden' : '' ?>><?= htmlspecialchars(mb_strtoupper(mb_substr($config['nome_site'], 0, 2))) ?></span>
                                    </span>
                                    <span id="pvName"><?= htmlspecialchars($config['nome_site']) ?></span>
                                </div>
                                <span class="pv-nav-action"><span>Produtos</span><span class="material-symbols-outlined">shopping_bag</span></span>
                            </div>
                            <div class="pv-hero" data-preview-zone="hero">
                                <img id="pvBannerImg" src="<?= htmlspecialchars($config['banner_path']) ?>" alt="" <?= $config['banner_path'] ? '' : 'hidden' ?>>
                                <div class="pv-content">
                                    <span class="pv-badge">Catálogo online</span>
                                    <strong class="pv-title" id="pvTitle" data-preview-zone="title"><?= htmlspecialchars($config['titulo']) ?></strong>
                                    <span class="pv-sub" id="pvSub" data-preview-zone="subtitle"><?= htmlspecialchars($config['subtitulo']) ?></span>
                                    <span class="pv-cta" id="pvCta" data-preview-zone="cta"><?= htmlspecialchars($config['texto_cta']) ?></span>
                                </div>
                            </div>
                            <div class="pv-service" id="pvBenefits" data-preview-zone="commerce" <?= $config['mostrar_beneficios'] ? '' : 'hidden' ?>>
                                <?php foreach ($config['beneficios'] as $benefitIndex => $benefit): ?>
                                    <div class="pv-service-item">
                                        <span class="material-symbols-outlined" id="pvBenefitIcon<?= $benefitIndex ?>"><?= htmlspecialchars($benefit['icone']) ?></span>
                                        <span class="pv-service-copy">
                                            <strong id="pvBenefitTitle<?= $benefitIndex ?>"><?= htmlspecialchars($benefit['titulo']) ?></strong>
                                            <small id="pvBenefitText<?= $benefitIndex ?>"><?= htmlspecialchars($benefit['texto']) ?></small>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="pv-body" data-preview-zone="catalog">
                                <div class="pv-body-head">
                                    <div class="pv-label">Produtos em destaque</div>
                                    <span class="pv-description" id="pvDescription" data-preview-zone="description"><?= htmlspecialchars($config['descricao_site'] ?: 'Produtos disponíveis para pedir agora.') ?></span>
                                </div>
                                <div class="pv-products" id="pvProducts">
                                    <?php if ($previewProducts): ?>
                                        <?php foreach ($previewProducts as $produto): ?>
                                            <div class="pv-product">
                                                <div class="pv-product-image">
                                                    <?php if (!empty($produto['imagem'])): ?><img src="<?= htmlspecialchars($produto['imagem']) ?>" alt="">
                                                    <?php else: ?><span class="material-symbols-outlined">image</span><?php endif; ?>
                                                </div>
                                                <div class="pv-product-copy">
                                                    <span class="pv-product-name"><?= htmlspecialchars($produto['nome']) ?></span>
                                                    <span class="pv-product-bottom">
                                                        <span class="pv-product-price">R$ <?= number_format((float)$produto['preco'], 2, ',', '.') ?></span>
                                                        <span class="pv-product-add"><span class="material-symbols-outlined">add</span></span>
                                                    </span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="pv-product-empty">Selecione produtos na aba Produtos para vê-los aqui.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="pv-foot"><?= $siteUrl ? 'O catálogo e o estoque são atualizados automaticamente.' : 'Configure o endereço da vitrine para liberar o link público.' ?></div>
                                </div>
                                </div>
                                <span class="phone-home" aria-hidden="true"></span>
                            </div>
                        </div>
                        <div class="phone-caption"><span class="material-symbols-outlined">touch_app</span>Prévia móvel atualizada em tempo real</div>
                    </div>
                </aside>

                    </div>

                    <div class="save-bar" id="siteSaveBar" hidden>
                        <div><strong>Alterações não salvas</strong><span>Revise a pré-visualização e publique quando estiver pronto.</span></div>
                        <div class="save-actions">
                            <button class="btn" id="discardSiteChanges" type="button">Descartar</button>
                            <button class="btn primary save-button" type="submit"><span class="material-symbols-outlined">save</span>Salvar alterações</button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="tab-panel" id="panelProducts" role="tabpanel" aria-labelledby="tabProducts" hidden>
                <div class="card">
                    <div class="card-title"><span class="material-symbols-outlined">inventory_2</span>Produtos exibidos no Site PRO</div>
                    <p class="card-sub">Essa seleção é exclusiva do Site PRO e não altera os produtos publicados na vitrine básica.</p>
                    <div class="products-toolbar">
                        <input class="products-search" id="productsSearch" type="search" placeholder="Buscar produto pelo nome ou código">
                        <button class="btn" id="showAllProducts" type="button"><span class="material-symbols-outlined">visibility</span>Exibir todos</button>
                        <button class="btn" id="hideAllProducts" type="button"><span class="material-symbols-outlined">visibility_off</span>Ocultar todos</button>
                        <span class="products-count" id="productsCount">Carregando...</span>
                    </div>
                    <div class="admin-products" id="adminProducts">
                        <div class="products-empty"><span class="spinner"></span><br>Carregando produtos...</div>
                    </div>
                </div>
            </section>

            <section class="tab-panel" id="panelCoupons" role="tabpanel" aria-labelledby="tabCoupons" hidden>
                <div class="card">
                    <div class="card-title"><span class="material-symbols-outlined">sell</span>Cupons do Site PRO</div>
                    <p class="card-sub">O desconto é calculado no servidor sobre os produtos do carrinho. O frete não recebe desconto.</p>
                    <form class="coupon-create" id="couponForm">
                        <div class="field">
                            <label for="couponCode">Código do cupom</label>
                            <input id="couponCode" maxlength="32" required placeholder="PRIMEIRACOMPRA" autocomplete="off">
                            <span class="field-hint">Letras, números, hífen ou sublinhado.</span>
                        </div>
                        <div class="field">
                            <label for="couponPercentage">Desconto</label>
                            <input id="couponPercentage" type="number" min="0.01" max="100" step="0.01" required placeholder="10">
                            <span class="field-hint">Percentual de 0,01% a 100%.</span>
                        </div>
                        <button class="btn primary" type="submit"><span class="material-symbols-outlined">add</span>Criar cupom</button>
                    </form>
                    <div class="coupon-list" id="couponList">
                        <div class="coupon-empty"><span class="spinner"></span><br>Carregando cupons...</div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>

<div class="crop-editor-modal" id="cropEditorModal" role="dialog" aria-modal="true" aria-labelledby="cropEditorTitle" aria-hidden="true">
    <div class="crop-editor-dialog">
        <header class="crop-editor-head">
            <div>
                <div class="crop-editor-title" id="cropEditorTitle">Ajustar imagem</div>
                <div class="crop-editor-subtitle" id="cropEditorSubtitle">Arraste a imagem para definir o enquadramento.</div>
            </div>
            <button class="crop-editor-close" id="cropClose" type="button" aria-label="Fechar"><span class="material-symbols-outlined">close</span></button>
        </header>
        <div class="crop-editor-body">
            <div class="crop-editor-stage" id="cropEditorStage">
                <div class="crop-editor-loading" id="cropEditorLoading" role="status" aria-live="polite" hidden><span class="spinner"></span><span data-loading-label>Carregando…</span></div>
            </div>
        </div>
        <div class="crop-editor-controls">
            <div class="crop-editor-tools">
                <button class="crop-tool" id="cropRotateLeft" type="button" aria-label="Girar à esquerda"><span class="material-symbols-outlined">rotate_left</span></button>
                <button class="crop-tool" id="cropRotateRight" type="button" aria-label="Girar à direita"><span class="material-symbols-outlined">rotate_right</span></button>
            </div>
            <label class="crop-zoom" for="cropZoom"><span>Zoom</span><input id="cropZoom" type="range" min="1" max="3" step="0.01" value="1"><output class="crop-zoom-output" id="cropZoomOutput">100%</output></label>
        </div>
        <footer class="crop-editor-foot">
            <button class="crop-footer-btn" id="cropCancel" type="button">Cancelar</button>
            <button class="crop-footer-btn" id="cropReset" type="button"><span class="material-symbols-outlined">restart_alt</span>Redefinir</button>
            <button class="crop-footer-btn primary" id="cropApply" type="button"><span class="material-symbols-outlined">crop</span>Aplicar corte</button>
        </footer>
    </div>
</div>

<nav class="bnav" aria-label="Navegação rápida">
    <div class="bnav-inner">
        <a href="estoque.php" class="bnav-a"><span class="material-symbols-outlined">dashboard</span>Início</a>
        <a href="produtos.php" class="bnav-a"><span class="material-symbols-outlined">inventory</span>Produtos</a>
        <a href="vitrine_config.php" class="bnav-a"><span class="material-symbols-outlined">storefront</span>Vitrine</a>
        <a href="site_pro.php" class="bnav-a on"><span class="material-symbols-outlined">web</span>Site PRO</a>
        <a href="pedidos.php" class="bnav-a"><span class="material-symbols-outlined">receipt_long</span>Pedidos</a>
    </div>
</nav>

<script src="assets/cropper.min.js?v=2.1.1"></script>
<script src="assets/vitrine-image-editor.js?v=20261015-banner1"></script>
<script>
const sidebar = document.getElementById('sb');
const overlay = document.getElementById('ov');
const siteProCsrf = <?= json_encode($csrfToken) ?>;
const savedPublicChannel = <?= json_encode($config['canal_publico']) ?>;
const siteProFormElement = document.getElementById('siteProForm');
const siteSaveBar = document.getElementById('siteSaveBar');
let siteFormDirty = false;
let previewSystemReady = false;
function markSiteFormDirty() {
    if (!siteProFormElement || siteFormDirty) return;
    siteFormDirty = true;
    if (siteSaveBar) siteSaveBar.hidden = false;
}
function updateOverviewChannel() {
    const current = document.querySelector('input[name="canal_publico"]:checked')?.value || 'vitrine';
    const label = document.getElementById('overviewChannel');
    if (label) label.textContent = current === 'site_pro' ? 'Site PRO' : 'Vitrine';
}
function updateOverviewPublication(active) {
    const pill = document.getElementById('overviewPublishState');
    if (!pill) return;
    pill.classList.toggle('published', active);
    pill.classList.toggle('paused', !active);
    pill.innerHTML = `<span class="state-dot"></span>${active ? 'Publicado' : 'Pausado'}`;
}
siteProFormElement?.addEventListener('input', markSiteFormDirty);
siteProFormElement?.addEventListener('change', markSiteFormDirty);
document.getElementById('discardSiteChanges')?.addEventListener('click', () => window.location.reload());
['bannerPending','logoPending'].forEach(id => {
    const node = document.getElementById(id);
    if (node && window.MutationObserver) new MutationObserver(() => { if (!node.hidden) markSiteFormDirty(); }).observe(node, {attributes:true,attributeFilter:['hidden']});
});

const editorSections = [...document.querySelectorAll('.editor-section')];
const editorNavLinks = [...document.querySelectorAll('[data-editor-section]')];
const editorSectionStorageKey = 'uplar_site_pro_editor_section';
function setActiveEditorSection(id, {remember = true} = {}) {
    const target = editorSections.find(section => section.id === id) || editorSections[0];
    if (!target) return;
    editorSections.forEach(section => {
        const active = section === target;
        section.hidden = !active;
        section.setAttribute('aria-hidden', active ? 'false' : 'true');
    });
    editorNavLinks.forEach(link => {
        const active = link.dataset.editorSection === target.id;
        link.classList.toggle('on', active);
        link.setAttribute('aria-selected', active ? 'true' : 'false');
        link.tabIndex = active ? 0 : -1;
    });
    if (remember) {
        try { sessionStorage.setItem(editorSectionStorageKey, target.id); } catch (error) {}
    }
    if (previewSystemReady) resetPreviewCamera();
}
editorNavLinks.forEach((link, index) => {
    link.addEventListener('click', () => setActiveEditorSection(link.dataset.editorSection));
    link.addEventListener('keydown', event => {
        if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
        event.preventDefault();
        let nextIndex = index;
        if (event.key === 'ArrowRight') nextIndex = (index + 1) % editorNavLinks.length;
        if (event.key === 'ArrowLeft') nextIndex = (index - 1 + editorNavLinks.length) % editorNavLinks.length;
        if (event.key === 'Home') nextIndex = 0;
        if (event.key === 'End') nextIndex = editorNavLinks.length - 1;
        const next = editorNavLinks[nextIndex];
        next?.focus();
        if (next) setActiveEditorSection(next.dataset.editorSection);
    });
});
let initialEditorSection = 'site-general';
try {
    const storedEditorSection = sessionStorage.getItem(editorSectionStorageKey);
    if (editorSections.some(section => section.id === storedEditorSection)) initialEditorSection = storedEditorSection;
} catch (error) {}
setActiveEditorSection(initialEditorSection, {remember:false});

// Se um campo obrigatório de outra aba impedir o salvamento, abre a aba certa antes do navegador focá-lo.
siteProFormElement?.addEventListener('invalid', event => {
    const section = event.target.closest?.('.editor-section');
    if (section?.hidden) setActiveEditorSection(section.id);
}, true);

const escapeAdminHtml = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
function updateInfinitePayConfig() {
    const mode = document.querySelector('input[name="finalizacao_pedido"]:checked')?.value || 'whatsapp';
    const panel = document.getElementById('infinitePayConfig');
    const input = document.getElementById('infinitepay_tag');
    if (panel) panel.hidden = mode === 'whatsapp';
    if (input) input.required = mode !== 'whatsapp';
}
document.querySelectorAll('input[name="finalizacao_pedido"]').forEach(input => input.addEventListener('change', updateInfinitePayConfig));
updateInfinitePayConfig();
document.getElementById('ham')?.addEventListener('click', () => {
    sidebar?.classList.add('open');
    overlay?.classList.add('on');
    document.body.style.overflow = 'hidden';
});
overlay?.addEventListener('click', () => {
    sidebar?.classList.remove('open');
    overlay.classList.remove('on');
    document.body.style.overflow = '';
});
document.getElementById('openPublicAddress')?.addEventListener('click', event => {
    const selectedChannel = document.querySelector('input[name="canal_publico"]:checked')?.value;
    if (selectedChannel && selectedChannel !== savedPublicChannel) {
        const form = document.getElementById('siteProForm');
        let openAfterSave = form?.querySelector('input[name="abrir_apos_salvar"]');
        if (!openAfterSave && form) {
            openAfterSave = document.createElement('input');
            openAfterSave.type = 'hidden';
            openAfterSave.name = 'abrir_apos_salvar';
            form.appendChild(openAfterSave);
        }
        if (openAfterSave) openAfterSave.value = '1';
        form?.requestSubmit();
        return;
    }
    const url = event.currentTarget.dataset.url;
    if (url) window.open(url, '_blank', 'noopener');
});

let adminProducts = [];
let productsLoaded = false;
let adminCoupons = [];
let couponsLoaded = false;
document.querySelectorAll('.section-tab').forEach(tab => tab.addEventListener('click', () => {
    const selectedPanel = tab.dataset.panel || 'site';
    document.querySelectorAll('.section-tab').forEach(item => {
        const active = item === tab;
        item.classList.toggle('on', active);
        item.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.getElementById('panelSite').hidden = selectedPanel !== 'site';
    document.getElementById('panelProducts').hidden = selectedPanel !== 'products';
    document.getElementById('panelCoupons').hidden = selectedPanel !== 'coupons';
    if (selectedPanel === 'products' && !productsLoaded) loadAdminProducts();
    if (selectedPanel === 'coupons' && !couponsLoaded) loadAdminCoupons();
    if (selectedPanel === 'site' && productsLoaded) renderPreviewProducts();
}));

async function siteProAdminRequest(action, data = null) {
    const options = {credentials:'same-origin'};
    if (data) {
        const body = new FormData();
        body.append('csrf_token', siteProCsrf);
        Object.entries(data).forEach(([key, value]) => body.append(key, value));
        options.method = 'POST';
        options.body = body;
    }
    const response = await fetch(`site_pro_api.php?acao=${encodeURIComponent(action)}`, options);
    const result = await response.json();
    if (!result.ok) throw new Error(result.error || 'Não foi possível concluir a operação.');
    return result;
}

async function loadAdminProducts() {
    try {
        const result = await siteProAdminRequest('admin_listar_produtos');
        adminProducts = result.produtos || [];
        productsLoaded = true;
        renderAdminProducts();
    } catch (error) {
        document.getElementById('adminProducts').innerHTML = `<div class="products-empty">${escapeAdminHtml(error.message)}</div>`;
    }
}

function renderAdminProducts() {
    const query = (document.getElementById('productsSearch')?.value || '').toLowerCase().trim();
    const filtered = adminProducts.filter(product => !query || `${product.nome} ${product.codigo || ''}`.toLowerCase().includes(query));
    const exposed = adminProducts.filter(product => product.site_pro_exposto).length;
    document.getElementById('productsCount').textContent = `${exposed} de ${adminProducts.length} exibidos`;
    renderPreviewProducts();
    const grid = document.getElementById('adminProducts');
    if (!filtered.length) {
        grid.innerHTML = '<div class="products-empty">Nenhum produto encontrado.</div>';
        return;
    }
    grid.innerHTML = filtered.map(product => `
        <button class="admin-product ${product.site_pro_exposto ? 'exposed' : ''}" type="button" data-product-id="${product.id}" aria-pressed="${product.site_pro_exposto ? 'true' : 'false'}">
            <span class="admin-product-check"><span class="material-symbols-outlined">${product.site_pro_exposto ? 'check' : ''}</span></span>
            <span class="admin-product-image">${product.imagem ? `<img src="${escapeAdminHtml(product.imagem)}" alt="">` : '<span class="material-symbols-outlined">image</span>'}</span>
            <span class="admin-product-body">
                <span class="admin-product-name">${escapeAdminHtml(product.nome)}</span>
                <span class="admin-product-meta"><span>R$ ${Number(product.preco).toLocaleString('pt-BR',{minimumFractionDigits:2})}</span><span>${product.quantidade} em estoque</span></span>
            </span>
        </button>`).join('');
    grid.querySelectorAll('[data-product-id]').forEach(card => card.addEventListener('click', () => toggleAdminProduct(Number(card.dataset.productId), card)));
}

function renderPreviewProducts() {
    const grid = document.getElementById('pvProducts');
    if (!grid || !productsLoaded) return;
    const products = adminProducts.filter(product => product.site_pro_exposto && product.quantidade > 0).slice(0, 3);
    grid.innerHTML = products.length ? products.map(product => `
        <div class="pv-product">
            <div class="pv-product-image">${product.imagem ? `<img src="${escapeAdminHtml(product.imagem)}" alt="">` : '<span class="material-symbols-outlined">image</span>'}</div>
            <div class="pv-product-copy">
                <span class="pv-product-name">${escapeAdminHtml(product.nome)}</span>
                <span class="pv-product-bottom">
                    <span class="pv-product-price">R$ ${Number(product.preco).toLocaleString('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2})}</span>
                    <span class="pv-product-add"><span class="material-symbols-outlined">add</span></span>
                </span>
            </div>
        </div>`).join('') : '<div class="pv-product-empty">Selecione produtos na aba Produtos para vê-los aqui.</div>';
    focusPreview('catalog');
}

async function toggleAdminProduct(productId, card) {
    card.disabled = true;
    try {
        const result = await siteProAdminRequest('admin_toggle_produto', {produto_id:productId});
        const product = adminProducts.find(item => item.id === productId);
        if (product) product.site_pro_exposto = result.site_pro_exposto;
        renderAdminProducts();
    } catch (error) {
        editorFeedback(error.message, 'err');
        card.disabled = false;
    }
}

async function setAllAdminProducts(exposed) {
    if (!adminProducts.length) return;
    const button = exposed ? document.getElementById('showAllProducts') : document.getElementById('hideAllProducts');
    button.disabled = true;
    try {
        await siteProAdminRequest('admin_produtos_bulk', {
            ids:JSON.stringify(adminProducts.map(product => product.id)),
            site_pro_exposto:exposed ? '1' : ''
        });
        adminProducts.forEach(product => { product.site_pro_exposto = exposed; });
        renderAdminProducts();
    } catch (error) {
        editorFeedback(error.message, 'err');
    } finally {
        button.disabled = false;
    }
}
document.getElementById('productsSearch')?.addEventListener('input', renderAdminProducts);
document.getElementById('showAllProducts')?.addEventListener('click', () => setAllAdminProducts(true));
document.getElementById('hideAllProducts')?.addEventListener('click', () => setAllAdminProducts(false));

async function loadAdminCoupons() {
    try {
        const result = await siteProAdminRequest('admin_listar_cupons');
        adminCoupons = result.cupons || [];
        couponsLoaded = true;
        renderAdminCoupons();
    } catch (error) {
        document.getElementById('couponList').innerHTML = `<div class="coupon-empty">${escapeAdminHtml(error.message)}</div>`;
    }
}

function renderAdminCoupons() {
    const list = document.getElementById('couponList');
    if (!adminCoupons.length) {
        list.innerHTML = '<div class="coupon-empty">Nenhum cupom criado. Crie o primeiro acima para começar uma campanha.</div>';
        return;
    }
    list.innerHTML = adminCoupons.map(coupon => `
        <div class="coupon-item ${coupon.ativo ? '' : 'off'}">
            <span class="coupon-badge">${Number(coupon.desconto_percentual).toLocaleString('pt-BR',{maximumFractionDigits:2})}%</span>
            <span class="coupon-copy">
                <span class="coupon-code">${escapeAdminHtml(coupon.codigo)}</span>
                <span class="coupon-meta">${coupon.ativo ? 'Ativo no checkout' : 'Pausado e indisponível para novos pedidos'}</span>
            </span>
            <span class="coupon-actions">
                <button class="btn" type="button" data-coupon-toggle="${coupon.id}"><span class="material-symbols-outlined">${coupon.ativo ? 'pause' : 'play_arrow'}</span>${coupon.ativo ? 'Pausar' : 'Ativar'}</button>
                <button class="btn" type="button" data-coupon-delete="${coupon.id}" aria-label="Excluir ${escapeAdminHtml(coupon.codigo)}"><span class="material-symbols-outlined">delete</span></button>
            </span>
        </div>`).join('');
    list.querySelectorAll('[data-coupon-toggle]').forEach(button => button.addEventListener('click', () => toggleAdminCoupon(Number(button.dataset.couponToggle), button)));
    list.querySelectorAll('[data-coupon-delete]').forEach(button => button.addEventListener('click', () => deleteAdminCoupon(Number(button.dataset.couponDelete), button)));
}

document.getElementById('couponCode')?.addEventListener('input', event => {
    event.target.value = event.target.value.toUpperCase().replace(/[^A-Z0-9_-]/g, '');
});
document.getElementById('couponForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const button = event.currentTarget.querySelector('button[type="submit"]');
    button.disabled = true;
    try {
        await siteProAdminRequest('admin_salvar_cupom', {
            codigo:document.getElementById('couponCode').value,
            desconto_percentual:document.getElementById('couponPercentage').value
        });
        event.currentTarget.reset();
        await loadAdminCoupons();
        editorFeedback('Cupom criado e disponível no checkout.');
    } catch (error) {
        editorFeedback(error.message, 'err');
    } finally {
        button.disabled = false;
    }
});

async function toggleAdminCoupon(couponId, button) {
    button.disabled = true;
    try {
        const result = await siteProAdminRequest('admin_toggle_cupom', {cupom_id:couponId});
        const coupon = adminCoupons.find(item => item.id === couponId);
        if (coupon) coupon.ativo = result.ativo;
        renderAdminCoupons();
    } catch (error) {
        editorFeedback(error.message, 'err');
        button.disabled = false;
    }
}

async function deleteAdminCoupon(couponId, button) {
    const coupon = adminCoupons.find(item => item.id === couponId);
    if (!coupon || !window.confirm(`Excluir o cupom ${coupon.codigo}?`)) return;
    button.disabled = true;
    try {
        await siteProAdminRequest('admin_excluir_cupom', {cupom_id:couponId});
        adminCoupons = adminCoupons.filter(item => item.id !== couponId);
        renderAdminCoupons();
        editorFeedback('Cupom excluído.');
    } catch (error) {
        editorFeedback(error.message, 'err');
        button.disabled = false;
    }
}

function editorFeedback(message, type = 'ok') {
    const toast = document.createElement('div');
    toast.className = `editor-toast ${type === 'err' ? 'err' : ''}`;
    toast.textContent = message;
    document.body.appendChild(toast);
    window.setTimeout(() => toast.remove(), 3200);
}

let imageEditor = null;
if (document.getElementById('siteProForm') && window.VitrineImageEditor) {
    try {
        imageEditor = new VitrineImageEditor({
            modalId: 'cropEditorModal',
            feedback: editorFeedback,
            fields: {
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
                    defaultName: 'banner-site-pro.webp',
                    inputId: 'bannerInp',
                    dropzoneId: 'bannerPreviewBox',
                    previewIds: ['bannerPreviewImg', 'pvBannerImg'],
                    emptyId: 'bannerPreviewEmpty',
                    pendingId: 'bannerPending',
                    editorHint: 'Arraste a imagem. O recorte final será 1500 × 500 px.',
                    initialSrc: <?= json_encode($config['banner_path']) ?>
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
                    defaultName: 'logo-site-pro.webp',
                    inputId: 'logoInp',
                    dropzoneId: 'logoPreviewBox',
                    previewIds: ['logoPreviewImg', 'pvLogoImg'],
                    fallbackIds: ['pvLogoFallback'],
                    emptyId: 'logoPreviewEmpty',
                    pendingId: 'logoPending',
                    editorHint: 'Arraste a imagem. O recorte final será 600 × 600 px.',
                    initialSrc: <?= json_encode($config['logo_path']) ?>
                }
            }
        });
    } catch (error) {
        console.error(error);
        editorFeedback('Não foi possível iniciar o editor de imagens.', 'err');
    }
}

document.getElementById('siteProForm')?.addEventListener('submit', () => {
    if (!imageEditor) return;
    Object.entries(imageEditor.fields).forEach(([key, field]) => {
        const file = imageEditor.states[key]?.processedFile;
        const input = document.getElementById(field.inputId);
        if (!file || !input || typeof DataTransfer === 'undefined') return;
        const transfer = new DataTransfer();
        transfer.items.add(file);
        input.files = transfer.files;
        input.name = field.uploadField;
    });
});

const colorInput = document.getElementById('cor_primaria');
function applySiteColor(color) {
    if (!/^#[0-9a-f]{6}$/i.test(color)) return;
    document.getElementById('previewCard')?.style.setProperty('--store-color', color);
    document.querySelectorAll('.swatch').forEach(swatch => swatch.classList.toggle('on', swatch.dataset.color.toLowerCase() === color.toLowerCase()));
    focusPreview('hero', document.activeElement === colorInput);
}
document.querySelectorAll('.swatch').forEach(swatch => swatch.addEventListener('click', () => {
    if (colorInput) colorInput.value = swatch.dataset.color;
    applySiteColor(swatch.dataset.color);
    markSiteFormDirty();
}));
colorInput?.addEventListener('input', () => applySiteColor(colorInput.value));
const backgroundInput = document.getElementById('cor_fundo');
const customBackgroundToggle = document.getElementById('fundo_personalizado');
function applySiteBackground() {
    const card = document.getElementById('previewCard');
    const previewSite = document.getElementById('previewSite');
    if (!card) return;
    previewSite?.classList.remove('custom-bg-dark', 'custom-bg-light');
    if (customBackgroundToggle?.checked && /^#[0-9a-f]{6}$/i.test(backgroundInput?.value || '')) {
        card.style.setProperty('--store-bg', backgroundInput.value);
        const red = parseInt(backgroundInput.value.slice(1, 3), 16);
        const green = parseInt(backgroundInput.value.slice(3, 5), 16);
        const blue = parseInt(backgroundInput.value.slice(5, 7), 16);
        const isDark = ((red * 299) + (green * 587) + (blue * 114)) / 1000 < 145;
        previewSite?.classList.add(isDark ? 'custom-bg-dark' : 'custom-bg-light');
    } else {
        card.style.removeProperty('--store-bg');
    }
    focusPreview('catalog', document.activeElement === backgroundInput);
}
backgroundInput?.addEventListener('input', () => {
    if (customBackgroundToggle) customBackgroundToggle.checked = true;
    applySiteBackground();
});
customBackgroundToggle?.addEventListener('change', applySiteBackground);

const fontInput = document.getElementById('fonte_site');
fontInput?.addEventListener('change', () => {
    const previewSite = document.getElementById('previewSite');
    if (!previewSite) return;
    previewSite.classList.remove('font-tema', 'font-manrope', 'font-inter', 'font-archivo', 'font-cormorant');
    previewSite.classList.add(`font-${fontInput.value}`);
    focusPreview('hero');
});
let previewFocusTimer = null;
let previewFocusedTarget = null;
let previewFocusedZone = null;
let previewFocusPersistent = false;
let previewReframeFrame = null;
previewSystemReady = true;
const previewCamera = {
    utility: {scale:1.32, x:.50, y:.18, background:'#17202b'},
    brand: {scale:1.22, x:.50, y:.25, background:'#ffffff'},
    hero: {scale:1.14, x:.50, y:.50, background:'#17202b'},
    title: {scale:1.28, x:.50, y:.48, background:'#17202b'},
    subtitle: {scale:1.25, x:.50, y:.50, background:'#17202b'},
    cta: {scale:1.24, x:.50, y:.54, background:'#17202b'},
    commerce: {scale:1.20, x:.50, y:.50, background:'#ffffff'},
    catalog: {scale:1.18, x:.50, y:.50, background:'#f6f6f3'},
    description: {scale:1.25, x:.50, y:.48, background:'#f6f6f3'}
};
function resetPreviewCamera() {
    const card = document.getElementById('previewCard');
    const site = document.getElementById('previewSite');
    const viewport = document.getElementById('previewViewport');
    previewFocusedTarget?.classList.remove('preview-focus');
    previewFocusedTarget = null;
    previewFocusedZone = null;
    previewFocusPersistent = false;
    site?.style.setProperty('--preview-camera-scale', '1');
    site?.style.setProperty('--preview-camera-x', '0px');
    site?.style.setProperty('--preview-camera-y', '0px');
    viewport?.style.setProperty('--preview-stage-bg', '#f6f6f3');
    card?.classList.remove('is-editing');
}
function focusPreview(zone, persistent = false) {
    const card = document.getElementById('previewCard');
    const site = document.getElementById('previewSite');
    const viewport = document.getElementById('previewViewport');
    const target = site?.querySelector(`[data-preview-zone="${zone}"]`);
    if (!card || !site || !viewport || !target) return;
    if (target.hidden || target.getClientRects().length === 0) return;
    const camera = previewCamera[zone] || {scale:1.18, x:.50, y:.50, background:'#f6f6f3'};
    const siteBox = site.getBoundingClientRect();
    const targetBox = target.getBoundingClientRect();
    if (site.offsetWidth < 1 || targetBox.width < 1 || targetBox.height < 1) return;
    const renderedScale = site.offsetWidth > 0 ? siteBox.width / site.offsetWidth : 1;
    const targetCenterX = (targetBox.left + (targetBox.width / 2) - siteBox.left) / renderedScale;
    const targetCenterY = (targetBox.top + (targetBox.height / 2) - siteBox.top) / renderedScale;
    const cameraX = (viewport.clientWidth * camera.x) - (targetCenterX * camera.scale);
    const cameraY = (viewport.clientHeight * camera.y) - (targetCenterY * camera.scale);

    site.querySelectorAll('.preview-focus').forEach(item => item.classList.remove('preview-focus'));
    target.classList.add('preview-focus');
    previewFocusedTarget = target;
    previewFocusedZone = zone;
    previewFocusPersistent = persistent;
    const targetBackground = window.getComputedStyle(target).backgroundColor;
    viewport.style.setProperty(
        '--preview-stage-bg',
        targetBackground && targetBackground !== 'rgba(0, 0, 0, 0)' ? targetBackground : camera.background
    );
    site.style.setProperty('--preview-camera-scale', camera.scale);
    site.style.setProperty('--preview-camera-x', `${Math.round(cameraX)}px`);
    site.style.setProperty('--preview-camera-y', `${Math.round(cameraY)}px`);
    card.classList.add('is-editing');
    window.clearTimeout(previewFocusTimer);
    if (!persistent) {
        previewFocusTimer = window.setTimeout(resetPreviewCamera, 1400);
    }
}
function syncPreviewControl(field) {
    const zone = field.dataset.previewZone;
    const output = field.dataset.previewTarget ? document.getElementById(field.dataset.previewTarget) : null;
    const iconOutput = field.dataset.previewIconTarget ? document.getElementById(field.dataset.previewIconTarget) : null;
    if (output) {
        output.textContent = field.value || field.dataset.previewFallback || '';
        const emptyGroup = output.closest('[data-preview-hide-when-empty]');
        if (emptyGroup) {
            const hasContent = [...emptyGroup.querySelectorAll('[data-preview-value]')]
                .some(item => item.textContent.trim() !== '');
            emptyGroup.hidden = !hasContent;
            if (!hasContent && previewFocusedZone === zone) {
                resetPreviewCamera();
                return;
            }
        }
        if (field.id === 'nome_site') {
            const fallback = document.getElementById('pvLogoFallback');
            if (fallback) fallback.textContent = field.value.trim().slice(0, 2).toUpperCase();
        }
    }
    if (iconOutput) iconOutput.textContent = field.value;
    focusPreview(zone, document.activeElement === field);
}
function bindPreviewControl(field) {
    if (field.dataset.previewBound === '1') return;
    field.dataset.previewBound = '1';
    const zone = field.dataset.previewZone;
    field?.addEventListener('focus', () => focusPreview(zone, true));
    field?.addEventListener('input', () => syncPreviewControl(field));
    field?.addEventListener('change', () => syncPreviewControl(field));
    field?.addEventListener('blur', () => {
        window.clearTimeout(previewFocusTimer);
        previewFocusTimer = window.setTimeout(resetPreviewCamera, 120);
    });
}
// Novos controles entram na câmera declarando a zona e, quando houver conteúdo, o alvo da prévia no próprio HTML.
const previewControlSelector = 'input[data-preview-zone],select[data-preview-zone],textarea[data-preview-zone],button[data-preview-zone]';
function bindPreviewControls(root = document) {
    if (root.matches?.(previewControlSelector)) bindPreviewControl(root);
    root.querySelectorAll(previewControlSelector).forEach(bindPreviewControl);
}
function reframeFocusedPreview() {
    if (!previewFocusedZone) return;
    window.cancelAnimationFrame(previewReframeFrame);
    previewReframeFrame = window.requestAnimationFrame(() => focusPreview(previewFocusedZone, previewFocusPersistent));
}
bindPreviewControls();
const previewForm = document.getElementById('siteProForm');
if (previewForm && window.MutationObserver) {
    new MutationObserver(records => {
        records.forEach(record => record.addedNodes.forEach(node => {
            if (node.nodeType === Node.ELEMENT_NODE) bindPreviewControls(node);
        }));
    }).observe(previewForm, {childList:true, subtree:true});
}
window.addEventListener('resize', reframeFocusedPreview);
if (window.ResizeObserver) {
    const previewViewport = document.getElementById('previewViewport');
    if (previewViewport) new ResizeObserver(reframeFocusedPreview).observe(previewViewport);
}
document.fonts?.ready?.then(reframeFocusedPreview);

document.querySelectorAll('input[name="modelo"]').forEach(option => option.addEventListener('change', event => {
    if (!event.target.checked) return;
    const previewSite = document.getElementById('previewSite');
    if (previewSite) {
        previewSite.classList.remove('model-venda-direta', 'model-catalogo-editorial', 'model-impacto-esportivo', 'model-catalogo-compacto');
        previewSite.classList.add(`model-${event.target.value.replaceAll('_', '-')}`);
    }
    focusPreview('hero');
}));
document.getElementById('showBenefits')?.addEventListener('change', event => {
    const benefits = document.getElementById('pvBenefits');
    if (!benefits) return;
    benefits.hidden = !event.target.checked;
    if (benefits.hidden) {
        resetPreviewCamera();
    } else {
        focusPreview('commerce');
    }
});

document.getElementById('siteAtivo')?.addEventListener('change', event => {
    const state = document.getElementById('previewState');
    if (!state) return;
    state.textContent = event.target.checked ? 'Publicado' : 'Pausado';
    updateOverviewPublication(event.target.checked);
    state.style.color = event.target.checked ? 'var(--ok)' : 'var(--muted)';
    state.style.background = event.target.checked ? 'var(--ok-dim)' : 'var(--bg)';
    focusPreview('commerce');
});
document.querySelectorAll('input[name="canal_publico"]').forEach(option => option.addEventListener('change', event => {
    updateOverviewChannel();
    if (event.target.value !== 'site_pro' || !event.target.checked) return;
    const activeToggle = document.getElementById('siteAtivo');
    if (activeToggle && !activeToggle.checked) {
        activeToggle.checked = true;
        activeToggle.dispatchEvent(new Event('change'));
        editorFeedback('O Site PRO foi marcado para publicação. Salve para confirmar.');
    }
}));
</script>
</body>
</html>
