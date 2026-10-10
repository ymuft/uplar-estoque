<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/subscription.php';
require_once __DIR__ . '/site_pro_helpers.php';
require_once __DIR__ . '/includes/product_sizes.php';
require_once __DIR__ . '/includes/product_complements.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['site_pro_public_csrf'])) {
    $_SESSION['site_pro_public_csrf'] = bin2hex(random_bytes(32));
}

$slug = trim((string)($_GET['loja'] ?? ''));
if ($slug === '' || !preg_match('/^[a-zA-Z0-9_-]{3,80}$/', $slug)) {
    http_response_code(404);
    exit('Site não encontrado.');
}
$requestPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?: '';
if (preg_match('#^/site(?:\.php)?/?$#', $requestPath)) {
    header('Location: /' . rawurlencode($slug), true, 301);
    exit;
}

$pdo = get_pdo();
site_pro_ensure_schema($pdo);
product_sizes_ensure_schema($pdo);
$complementsReady = uplar_complementos_schema_disponivel($pdo);
$ownerStmt = $pdo->prepare('SELECT id, username, whatsapp_number FROM users WHERE vitrine_slug = ? LIMIT 1');
$ownerStmt->execute([$slug]);
$owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);
if (!$owner || !has_pro_access((int)$owner['id'], $pdo)) {
    http_response_code(404);
    exit('Site não disponível.');
}

$config = site_pro_config($pdo, (int)$owner['id'], ['nome_loja' => $owner['username'], 'slug_site' => $slug]);
if (!$config['ativo'] || $config['canal_publico'] !== 'site_pro') {
    http_response_code(404);
    exit('Site não disponível.');
}

$productsStmt = $pdo->prepare('
    SELECT id, nome, descricao, preco, quantidade, imagem, usa_tamanhos
    FROM produtos
    WHERE user_id = ? AND quantidade > 0 AND site_pro_exposto = 1
    ORDER BY nome ASC
    LIMIT 100
');
$productsStmt->execute([(int)$owner['id']]);
$produtos = $productsStmt->fetchAll(PDO::FETCH_ASSOC);
$customProductIds = [];
if ($complementsReady && $produtos) {
    $ids = array_map('intval', array_column($produtos, 'id'));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $customStmt = $pdo->prepare("SELECT v.produto_id FROM produto_complemento_vinculos v INNER JOIN produto_complemento_grupos g ON g.id=v.grupo_id AND g.user_id=v.user_id WHERE v.user_id=? AND v.ativo=1 AND g.ativo=1 AND v.produto_id IN ({$placeholders}) GROUP BY v.produto_id");
    $customStmt->execute(array_merge([(int)$owner['id']], $ids));
    $customProductIds = array_fill_keys(array_map('intval', $customStmt->fetchAll(PDO::FETCH_COLUMN)), true);
}
foreach ($produtos as &$product) {
    $product['id'] = (int)$product['id'];
    $product['preco'] = (float)$product['preco'];
    $product['usa_tamanhos'] = (bool)$product['usa_tamanhos'];
    $product['tem_complementos'] = isset($customProductIds[$product['id']]);
}
unset($product);

$cor = $config['cor_primaria'];
$nomeSite = $config['nome_site'] ?: (string)$owner['username'];
$modelo = $config['modelo'];
$modeloClass = 'model-' . str_replace('_', '-', $modelo);
$modelCopy = [
    'venda_direta' => ['eyebrow' => 'Catálogo online', 'catalog' => 'Escolha o que combina com você'],
    'catalogo_editorial' => ['eyebrow' => 'Seleção da marca', 'catalog' => 'Peças escolhidas para você'],
    'impacto_esportivo' => ['eyebrow' => 'Performance e atitude', 'catalog' => 'Equipe-se para o próximo desafio'],
    'catalogo_compacto' => ['eyebrow' => 'Compra rápida', 'catalog' => 'Tudo o que você precisa'],
][$modelo];
$fontClass = 'font-' . $config['fonte_site'];
$primaryNotice = $config['aviso_principal'] !== '' ? $config['aviso_principal'] : $config['aviso_secundario'];
$secondaryNotice = $config['aviso_principal'] !== '' ? $config['aviso_secundario'] : '';
$backgroundToneClass = $config['cor_fundo'] !== ''
    ? (site_pro_color_is_dark($config['cor_fundo']) ? 'custom-bg-dark' : 'custom-bg-light')
    : '';
$location = trim($config['endereco'] . ($config['cidade_uf'] !== '' ? ' — ' . $config['cidade_uf'] : ''));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="<?= htmlspecialchars($cor) ?>">
    <meta name="description" content="<?= htmlspecialchars($config['descricao_site'] ?: $config['subtitulo']) ?>">
    <title><?= htmlspecialchars($nomeSite) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,600;0,700;0,800;0,900;1,800;1,900&family=Cormorant+Garamond:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <style>
        :root{--p:<?= htmlspecialchars($cor) ?>;--ink:#171b20;--muted:#626a73;--line:#dfe2e1;--canvas:#f4f4f1;--navy:#111820;--ok:#18794e}
        *{box-sizing:border-box}
        html{scroll-behavior:smooth}
        body{margin:0;background:var(--canvas);color:var(--ink);font-family:Manrope,sans-serif;line-height:1.5;-webkit-font-smoothing:antialiased}
        body.locked{overflow:hidden}
        button,input,textarea{font:inherit}
        button{cursor:pointer}
        a{text-decoration:none;color:inherit}
        svg{display:block}
        .wrap{width:min(1180px,calc(100% - 48px));margin:auto}
        .utility{background:var(--navy);color:#d9dee5;font-size:11px;font-weight:600;letter-spacing:.025em}
        .utility-inner{min-height:32px;display:flex;align-items:center;justify-content:space-between;gap:20px}
        .utility-note{color:#929ca8}
        .site-header{position:sticky;top:0;z-index:80;border-bottom:1px solid rgba(23,27,32,.08);background:rgba(255,255,255,.96);backdrop-filter:blur(14px)}
        .nav{height:74px;display:flex;align-items:center;justify-content:space-between;gap:20px}
        .brand{display:flex;align-items:center;gap:10px;min-width:0;font-weight:800}
        .brand-logo{width:38px;height:38px;display:grid;place-items:center;flex:0 0 38px;overflow:hidden;border:1px solid var(--line);border-radius:6px;background:var(--p);color:#fff;font-size:12px;letter-spacing:.04em}
        .brand-logo img{width:100%;height:100%;object-fit:cover}
        .brand-name{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:15px;letter-spacing:-.02em}
        .nav-actions{display:flex;align-items:center;gap:18px}
        .nav-link{position:relative;font-size:12px;font-weight:700;color:var(--muted)}
        .nav-link::after{content:"";position:absolute;left:0;right:100%;bottom:-7px;height:2px;background:var(--p);transition:right .2s}
        .nav-link:hover{color:var(--ink)}.nav-link:hover::after{right:0}
        .cart-button{min-height:42px;display:inline-flex;align-items:center;gap:8px;padding:9px 12px 9px 15px;border:1px solid var(--ink);border-radius:5px;background:var(--ink);color:#fff;font-size:12px;font-weight:800;transition:background .2s,border-color .2s,transform .2s}
        .cart-button:hover{border-color:var(--p);background:var(--p);transform:translateY(-1px)}
        .cart-button svg{width:17px;height:17px}
        .cart-count{min-width:20px;height:20px;display:grid;place-items:center;padding:0 5px;border-radius:20px;background:var(--p);color:#fff;font-size:10px}
        .cart-button:hover .cart-count{background:#fff;color:var(--p)}
        .hero{position:relative;min-height:570px;display:flex;align-items:center;overflow:hidden;background:var(--navy)}
        .hero-media{position:absolute;inset:0}
        .hero-media img{width:100%;height:100%;object-fit:cover;opacity:.68}
        .hero-media::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(11,16,22,.94) 0%,rgba(11,16,22,.72) 50%,rgba(11,16,22,.18) 100%)}
        .hero-media.fallback{background:var(--navy)}
        .hero-media.fallback::before{content:"";position:absolute;right:8%;top:18%;width:260px;height:260px;border:1px solid rgba(255,255,255,.1);transform:rotate(12deg)}
        .hero-content{position:relative;z-index:1;max-width:760px;padding:104px 0;color:#fff}
        .eyebrow{display:inline-flex;align-items:center;gap:9px;color:var(--p);font-size:10px;font-weight:800;letter-spacing:.16em;text-transform:uppercase}
        .eyebrow::before{content:"";width:26px;height:2px;background:currentColor}
        h1{max-width:760px;margin:18px 0;font:800 clamp(42px,6vw,72px)/1.02 Manrope,sans-serif;letter-spacing:-.055em}
        .hero-copy{max-width:600px;margin:0;color:#d8dde3;font-size:16px;line-height:1.7}
        .hero-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-top:28px}
        .primary-button,.secondary-button{min-height:50px;display:inline-flex;align-items:center;justify-content:center;padding:13px 22px;border-radius:4px;font-size:13px;font-weight:800;transition:transform .2s,background .2s}
        .primary-button{border:1px solid var(--p);background:var(--p);color:#fff}
        .primary-button:hover{transform:translateY(-2px);filter:brightness(.94)}
        .secondary-button{border:1px solid rgba(255,255,255,.34);background:rgba(10,16,22,.28);color:#fff}
        .hero-note{display:flex;align-items:center;gap:9px;margin-top:22px;color:#b9c0c9;font-size:11px;font-weight:600}
        .hero-note::before{content:"";width:7px;height:7px;border-radius:50%;background:var(--p);box-shadow:0 0 0 4px rgba(255,255,255,.1)}
        .trust{border-bottom:1px solid var(--line);background:#fff}
        .trust-grid{display:grid;grid-template-columns:repeat(3,1fr);padding:0}
        .trust-item{display:flex;align-items:center;gap:13px;min-height:94px;padding:20px 26px}
        .trust-item+.trust-item{border-left:1px solid var(--line)}
        .trust-icon{width:34px;height:34px;display:grid;place-items:center;flex:0 0 34px;color:var(--p)}
        .trust-icon svg{width:25px;height:25px;stroke:currentColor}
        .trust-icon.material-symbols-outlined{font-size:25px}
        .trust-item strong{display:block;font-size:12px;letter-spacing:-.01em}
        .trust-item span{display:block;margin-top:3px;color:var(--muted);font-size:11px}
        .trust-item .trust-icon{margin-top:0;color:var(--p);font-size:25px}
        .catalog-shell{background:var(--canvas)}
        .catalog{padding:86px 0 96px}
        .section-head{display:flex;align-items:end;justify-content:space-between;gap:24px;margin-bottom:28px}
        .section-head h2{margin:9px 0 0;font:800 clamp(30px,4vw,44px)/1.1 Manrope,sans-serif;letter-spacing:-.045em}
        .section-copy{max-width:460px;margin:0;color:var(--muted);font-size:13px;line-height:1.7}
        .catalog-count{display:block;margin-bottom:8px;color:var(--muted);font-size:10px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
        .products{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}
        .product{min-width:0;overflow:hidden;border:1px solid transparent;background:#fff;transition:transform .2s,border-color .2s,box-shadow .2s}
        .product:hover{transform:translateY(-4px);border-color:var(--line);box-shadow:0 20px 44px rgba(17,24,32,.09)}
        .product-image{height:230px;display:grid;place-items:center;overflow:hidden;background:#e9ebe8;color:var(--muted);font-size:11px}
        .product-image img{width:100%;height:100%;object-fit:cover;transition:transform .45s cubic-bezier(.22,1,.36,1)}
        .product-placeholder{display:grid;place-items:center;gap:8px;color:#8b9299;font-size:10px;font-weight:700}
        .product-placeholder svg{width:28px;height:28px}
        .product:hover .product-image img{transform:scale(1.035)}
        .product-body{padding:16px 16px 17px}
        .product-name{font-size:13px;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .product-desc{height:38px;display:-webkit-box;margin-top:6px;overflow:hidden;-webkit-line-clamp:2;-webkit-box-orient:vertical;color:var(--muted);font-size:11px;line-height:1.7}
        .stock{margin-top:7px;color:var(--muted);font-size:11px}
        .product-foot{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:14px}
        .price{font-size:15px;font-weight:800;color:var(--ink)}
        .add-button{min-height:36px;display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 10px;border:1px solid var(--ink);border-radius:4px;background:var(--ink);color:#fff;font-size:10px;font-weight:800}
        .add-button svg{width:14px;height:14px}
        .add-button:hover{border-color:var(--p);background:var(--p)}
        .add-button:disabled{opacity:.55;cursor:wait}
        .empty{grid-column:1/-1;padding:48px;border:1px solid var(--line);text-align:center;color:var(--muted);background:#fff}
        .footer{padding:38px 0;background:var(--navy);color:#aeb7c2}
        .footer-inner{display:flex;align-items:center;justify-content:space-between;gap:20px;font-size:13px}
        .footer strong{color:#fff}

        body.model-catalogo-editorial{--ink:#29241f;--muted:#716960;--line:#d9d0c6;--canvas:#f0ebe4;--navy:#28231f}
        .model-catalogo-editorial .utility{background:#e9e2d9;color:#514a43}
        .model-catalogo-editorial .utility-note{color:#81786f}
        .model-catalogo-editorial .hero{min-height:650px;background:#28231f}
        .model-catalogo-editorial .hero-media img{opacity:.8}
        .model-catalogo-editorial .hero-media::after{background:rgba(31,26,22,.54)}
        .model-catalogo-editorial .hero-content{max-width:880px;margin:auto;text-align:center}
        .model-catalogo-editorial .hero-content .eyebrow,.model-catalogo-editorial .hero-actions,.model-catalogo-editorial .hero-note{justify-content:center}
        .model-catalogo-editorial h1,.model-catalogo-editorial .section-head h2{font-family:"Cormorant Garamond",serif;font-weight:600;letter-spacing:-.025em}
        .model-catalogo-editorial h1{max-width:880px;font-size:clamp(52px,7vw,86px);line-height:.98}
        .model-catalogo-editorial .hero-copy{margin:auto;color:#eee8e1}
        .model-catalogo-editorial .products{grid-template-columns:repeat(3,minmax(0,1fr));gap:28px}
        .model-catalogo-editorial .product{border:0;background:transparent}
        .model-catalogo-editorial .product-image{height:340px;background:#e2dbd2}
        .model-catalogo-editorial .product-body{padding:15px 2px 20px}
        .model-catalogo-editorial .product:hover{box-shadow:none}

        body.model-impacto-esportivo{--ink:#f5f7f4;--muted:#a9b2aa;--line:#2c372e;--canvas:#111612;--navy:#090d0a}
        .model-impacto-esportivo{background:var(--canvas);font-family:Archivo,Manrope,sans-serif}
        .model-impacto-esportivo .utility{border-bottom:1px solid #29342b;background:#090d0a;color:#f5f7f4;text-transform:uppercase}
        .model-impacto-esportivo .utility-note{color:#9daa9f}
        .model-impacto-esportivo .site-header{border-color:#2c372e;background:rgba(9,13,10,.96)}
        .model-impacto-esportivo .brand,.model-impacto-esportivo .nav-link{color:#f5f7f4}
        .model-impacto-esportivo .cart-button{border-color:var(--p);background:var(--p);color:#fff}
        .model-impacto-esportivo .hero{min-height:620px}
        .model-impacto-esportivo .hero-media img{opacity:.72}
        .model-impacto-esportivo .hero-media::after{background:linear-gradient(90deg,rgba(4,8,5,.97),rgba(4,8,5,.7) 52%,rgba(4,8,5,.22))}
        .model-impacto-esportivo h1{font-family:Archivo,sans-serif;font-weight:900;letter-spacing:-.055em;text-transform:uppercase}
        .model-impacto-esportivo .eyebrow{font-family:Archivo,sans-serif}
        .model-impacto-esportivo .primary-button{color:#fff;text-transform:uppercase}
        .model-impacto-esportivo .trust{border-color:#2c372e;background:#151c16}
        .model-impacto-esportivo .trust-item+.trust-item{border-color:#2c372e}
        .model-impacto-esportivo .catalog-shell{background:#111612}
        .model-impacto-esportivo .products{grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
        .model-impacto-esportivo .product{border-color:#2c372e;background:#182019}
        .model-impacto-esportivo .product-image{height:270px;background:#222c24}
        .model-impacto-esportivo .product:hover{border-color:var(--p);box-shadow:0 20px 44px rgba(0,0,0,.3)}
        .model-impacto-esportivo .add-button{border-color:var(--p);background:var(--p);color:#fff}
        .model-impacto-esportivo .footer{border-top:1px solid #2c372e}
        .model-impacto-esportivo .drawer,.model-impacto-esportivo .drawer-foot{background:#111612}
        .model-impacto-esportivo .drawer-head,.model-impacto-esportivo .drawer-foot,.model-impacto-esportivo .checkout,.model-impacto-esportivo .cart-item{border-color:#2c372e}
        .model-impacto-esportivo .icon-button,.model-impacto-esportivo .delivery-tab,.model-impacto-esportivo .quantity button,.model-impacto-esportivo .form-field input,.model-impacto-esportivo .form-field textarea{border-color:#39453b;background:#182019;color:#f5f7f4}
        .model-impacto-esportivo .delivery-tab.active{border-color:var(--p);background:color-mix(in srgb,var(--p) 14%,#182019)}
        .model-impacto-esportivo .summary{background:#182019}

        body.model-catalogo-compacto{--ink:#252922;--muted:#697065;--line:#d9ddd5;--canvas:#f3f2ed;--navy:#202720}
        .model-catalogo-compacto .hero{min-height:410px}
        .model-catalogo-compacto .hero-content{padding:68px 0}
        .model-catalogo-compacto h1{font-size:clamp(38px,5vw,58px)}
        .model-catalogo-compacto .trust-item{min-height:78px}
        .model-catalogo-compacto .catalog{padding:60px 0 76px}
        .model-catalogo-compacto .products{grid-template-columns:repeat(5,minmax(0,1fr));gap:10px}
        .model-catalogo-compacto .product{border-color:var(--line)}
        .model-catalogo-compacto .product-image{height:165px}
        .model-catalogo-compacto .product-body{padding:12px}
        .model-catalogo-compacto .product-desc{display:none}
        .model-catalogo-compacto .product-foot{margin-top:10px}
        body.font-manrope,.font-manrope h1,.font-manrope .section-head h2{font-family:Manrope,sans-serif}
        body.font-inter,.font-inter h1,.font-inter .section-head h2{font-family:Inter,sans-serif}
        body.font-archivo,.font-archivo h1,.font-archivo .section-head h2{font-family:Archivo,sans-serif}
        body.font-cormorant,.font-cormorant h1,.font-cormorant .section-head h2{font-family:"Cormorant Garamond",Georgia,serif}
        .custom-bg-dark .catalog .section-head h2{color:#fff}
        .custom-bg-dark .catalog .catalog-count,.custom-bg-dark .catalog .section-copy{color:#c5cdd5}
        .custom-bg-light.model-impacto-esportivo .catalog .section-head h2{color:#171b20}
        .custom-bg-light.model-impacto-esportivo .catalog .catalog-count,.custom-bg-light.model-impacto-esportivo .catalog .section-copy{color:#626a73}

        .drawer-overlay{position:fixed;inset:0;z-index:900;background:rgba(6,18,40,.52);opacity:0;visibility:hidden;transition:.22s}
        .drawer-overlay.open{opacity:1;visibility:visible}
        .drawer{position:fixed;right:0;top:0;bottom:0;z-index:901;width:min(440px,100%);display:flex;flex-direction:column;background:#fff;box-shadow:-20px 0 60px rgba(6,26,70,.2);transform:translateX(100%);transition:transform .28s cubic-bezier(.22,1,.36,1)}
        .drawer.open{transform:none}
        .drawer-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:18px 20px;border-bottom:1px solid var(--line)}
        .drawer-title{font:800 18px Manrope,sans-serif}
        .icon-button{width:36px;height:36px;display:grid;place-items:center;border:1px solid var(--line);border-radius:5px;background:#fff;color:var(--ink);font-size:20px}
        .drawer-body{flex:1;overflow-y:auto;padding:18px 20px}
        .cart-empty{padding:52px 12px;text-align:center;color:var(--muted)}
        .cart-item{display:grid;grid-template-columns:58px minmax(0,1fr) auto;gap:11px;padding:12px 0;border-bottom:1px solid var(--line)}
        .cart-image{width:58px;height:58px;overflow:hidden;border-radius:8px;background:var(--canvas)}
        .cart-image img{width:100%;height:100%;object-fit:cover}
        .cart-name{font-size:13px;font-weight:800}
        .cart-price{margin-top:3px;color:var(--p);font-size:12px;font-weight:700}
        .quantity{display:flex;align-items:center;gap:8px;margin-top:8px}
        .quantity button{width:25px;height:25px;border:1px solid var(--line);border-radius:6px;background:#fff}
        .quantity span{min-width:16px;text-align:center;font-size:12px;font-weight:700}
        .remove{border:0;background:transparent;color:#a34a4a;font-size:12px}
        .summary{margin-top:18px;padding:14px;border-radius:9px;background:var(--canvas)}
        .summary-row{display:flex;justify-content:space-between;gap:12px;color:var(--muted);font-size:13px}
        .summary-row+.summary-row{margin-top:7px}
        .summary-row.discount{color:var(--ok);font-weight:700}
        .summary-row.total{padding-top:9px;border-top:1px solid var(--line);color:var(--ink);font-size:15px;font-weight:800}
        .coupon-box{margin-top:12px;padding:12px;border:1px solid var(--line);border-radius:9px}
        .coupon-label{display:block;margin-bottom:6px;color:var(--muted);font-size:11px;font-weight:700}
        .coupon-entry{display:flex;gap:7px}
        .coupon-entry input{min-width:0;flex:1;padding:10px 11px;border:1px solid var(--line);border-radius:7px;outline:0;text-transform:uppercase}
        .coupon-entry input:focus{border-color:var(--p);box-shadow:0 0 0 3px color-mix(in srgb,var(--p) 10%,transparent)}
        .coupon-entry button{padding:0 13px;border:1px solid var(--ink);border-radius:7px;background:var(--ink);color:#fff;font-size:12px;font-weight:800}
        .coupon-applied{display:flex;align-items:center;justify-content:space-between;gap:8px;color:var(--ok);font-size:12px;font-weight:800}
        .coupon-applied button{border:0;background:transparent;color:var(--muted);font-size:11px;text-decoration:underline}
        .checkout{margin-top:18px;padding-top:18px;border-top:1px solid var(--line)}
        .checkout h3{margin:0 0 12px;font-size:14px}
        .delivery-tabs{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px}
        .delivery-tab{padding:10px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--muted);font-size:12px;font-weight:800}
        .delivery-tab.active{border-color:var(--p);background:color-mix(in srgb,var(--p) 8%,white);color:var(--p)}
        .form-field{margin-top:10px}
        .form-field label{display:block;margin-bottom:4px;color:var(--muted);font-size:11px;font-weight:700}
        .form-field input,.form-field textarea{width:100%;padding:11px 12px;border:1px solid var(--line);border-radius:8px;outline:0;color:var(--ink)}
        .form-field input:focus,.form-field textarea:focus{border-color:var(--p);box-shadow:0 0 0 3px color-mix(in srgb,var(--p) 10%,transparent)}
        .form-field textarea{min-height:76px;resize:vertical}
        .drawer-foot{padding:14px 20px;border-top:1px solid var(--line);background:#fff}
        .checkout-actions{display:grid;gap:8px}
        .checkout-button{width:100%;min-height:48px;border:0;border-radius:8px;background:var(--ok);color:#fff;font-size:14px;font-weight:800}
        .checkout-button.payment{background:var(--ink)}
        .checkout-button.secondary{border:1px solid var(--line);background:#fff;color:var(--ink)}
        .checkout-button:disabled{opacity:.6;cursor:wait}
        .custom-product-overlay{position:fixed;inset:0;z-index:1000;display:flex;align-items:center;justify-content:center;padding:18px;background:rgba(8,17,28,.52)}
        .custom-product-overlay[hidden]{display:none}
        .custom-product-modal{width:min(560px,100%);max-height:min(90dvh,820px);display:flex;flex-direction:column;overflow:hidden;border-radius:10px;background:#fff;box-shadow:0 24px 70px rgba(0,0,0,.25)}
        .custom-product-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;border-bottom:1px solid var(--line)}
        .custom-product-head strong{font:800 18px Manrope,sans-serif}
        .custom-product-close{width:36px;height:36px;border:1px solid var(--line);border-radius:7px;background:#fff;font-size:21px}
        .custom-product-body{flex:1;overflow-y:auto;padding:16px 18px;-webkit-overflow-scrolling:touch}
        .custom-product-title{font-size:20px;font-weight:800}.custom-product-price{margin-top:5px;color:var(--p);font-weight:800;font-size:17px}
        .custom-product-description{margin-top:7px;color:var(--muted);font-size:13px;line-height:1.5}
        .custom-product-group{padding:14px 0;border-top:1px solid var(--line)}
        .custom-product-group-title{font-size:13px;font-weight:800}.custom-product-group-help{margin-top:3px;color:var(--muted);font-size:11px}
        .custom-product-option{min-height:48px;display:flex;align-items:center;gap:10px;padding:7px 2px;border-bottom:1px solid #eef0f2;font-size:13px}
        .custom-product-option input{width:20px;height:20px;accent-color:var(--p)}
        .custom-product-option-name{flex:1;min-width:0}.custom-product-option-price{color:var(--p);font-size:12px;font-weight:800;white-space:nowrap}
        .custom-product-step{display:inline-flex;align-items:center;gap:8px}.custom-product-step button{width:34px;height:34px;border:1px solid var(--line);border-radius:8px;background:#fff;font-weight:800}.custom-product-step button:disabled{opacity:.35}.custom-product-step span{min-width:14px;text-align:center;font-weight:800}
        .custom-product-note{width:100%;min-height:72px;padding:10px;border:1px solid var(--line);border-radius:8px;font:inherit;font-size:13px;resize:vertical}
        .custom-product-footer{padding:12px 18px calc(12px + env(safe-area-inset-bottom));border-top:1px solid var(--line);background:#fff}
        .custom-product-quantity{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;font-size:13px;font-weight:700}
        .custom-product-quantity button{width:36px;height:36px;border:1px solid var(--line);border-radius:8px;background:#fff;font-size:18px}
        .custom-product-submit{width:100%;min-height:48px;border:0;border-radius:8px;background:var(--p);color:#fff;font-size:14px;font-weight:800}
        .custom-product-submit:disabled{opacity:.45}
        .cart-custom{margin-top:4px;color:var(--muted);font-size:11px;line-height:1.45;overflow-wrap:anywhere}
        .cart-review{margin:7px 0;padding:9px;border-radius:7px;background:#fff1d7;color:#85500b;font-size:12px;line-height:1.4}
        .cart-custom-line{display:block;margin-top:2px}
        .cart-review-banner{margin:12px 0;padding:11px;border:1px solid #efcf95;border-radius:8px;background:#fff7e8;color:#764b10;font-size:12px;line-height:1.45}
        .toast{position:fixed;left:50%;bottom:24px;z-index:1200;max-width:calc(100% - 32px);padding:10px 15px;border-radius:8px;background:var(--navy);color:#fff;font-size:13px;font-weight:700;box-shadow:0 12px 30px rgba(0,0,0,.2);transform:translateX(-50%)}

        @media(max-width:900px){
            .products,.model-catalogo-editorial .products,.model-impacto-esportivo .products,.model-catalogo-compacto .products{grid-template-columns:repeat(3,1fr)}
        }
        @media(max-width:700px){
            .wrap{width:min(100% - 30px,1180px)}
            .utility-note{display:none}.utility-inner{justify-content:center}
            .nav{height:68px}.nav-link{display:none}.cart-button>span:first-child{display:none}
            .hero,.model-catalogo-editorial .hero,.model-impacto-esportivo .hero{min-height:500px}.hero-content{padding:76px 0}.hero-media::after{background:rgba(11,16,22,.76)}
            .model-catalogo-editorial .hero-media::after{background:rgba(31,26,22,.62)}
            .model-impacto-esportivo .hero-media::after{background:rgba(4,8,5,.78)}
            .trust-grid{grid-template-columns:1fr}
            .trust-item{min-height:82px;padding:16px 0}.trust-item+.trust-item{border-left:0;border-top:1px solid var(--line)}
            .section-head{display:block}.section-copy{margin-top:10px}
            .products,.model-catalogo-editorial .products,.model-impacto-esportivo .products,.model-catalogo-compacto .products{grid-template-columns:repeat(2,1fr);gap:11px}
            .product-image{height:170px}.catalog{padding:62px 0 74px}
            .model-catalogo-editorial .product-image,.model-impacto-esportivo .product-image{height:210px}
            .model-catalogo-compacto .product-image{height:145px}
            .add-button span{display:none}
            .custom-product-overlay{align-items:flex-end;padding:0}
            .custom-product-modal{width:100%;max-height:94dvh;border-radius:16px 16px 0 0}
            .custom-product-head{padding:13px 16px}.custom-product-body{padding:13px 16px}.custom-product-footer{padding:10px 16px calc(10px + env(safe-area-inset-bottom))}
            .footer-inner{display:block}.footer-inner>*+*{display:block;margin-top:8px}
        }
        @media(max-width:420px){h1{font-size:39px}.hero-copy{font-size:14px}.product-image{height:145px}.product-body{padding:12px}.product-desc{display:none}.price{font-size:13px}.add-button{width:34px;padding:8px}}
    </style>
</head>
<body class="<?= htmlspecialchars(trim($modeloClass . ' ' . $fontClass . ' ' . $backgroundToneClass)) ?>"<?= $config['cor_fundo'] !== '' ? ' style="--canvas:' . htmlspecialchars($config['cor_fundo']) . '"' : '' ?>>
<?php if ($primaryNotice !== ''): ?>
    <div class="utility">
        <div class="wrap utility-inner"><span><?= htmlspecialchars($primaryNotice) ?></span><?php if ($secondaryNotice !== ''): ?><span class="utility-note"><?= htmlspecialchars($secondaryNotice) ?></span><?php endif; ?></div>
    </div>
<?php endif; ?>
<header class="site-header">
    <div class="wrap nav">
        <a class="brand" href="#inicio">
            <span class="brand-logo">
                <?php if ($config['logo_path']): ?><img src="<?= htmlspecialchars($config['logo_path']) ?>" alt="Logo de <?= htmlspecialchars($nomeSite) ?>">
                <?php else: ?><?= htmlspecialchars(mb_strtoupper(mb_substr($nomeSite, 0, 2))) ?><?php endif; ?>
            </span>
            <span class="brand-name"><?= htmlspecialchars($nomeSite) ?></span>
        </a>
        <nav class="nav-actions" aria-label="Ações principais">
            <a class="nav-link" href="#produtos">Ver produtos</a>
            <button class="cart-button" id="openCart" type="button">
                <span>Carrinho</span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2l2.2 10.1a2 2 0 0 0 2 1.6h7.7a2 2 0 0 0 1.9-1.4L21 7H6.1M9.5 20a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1Zm8 0a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <span class="cart-count" id="cartCount">0</span>
            </button>
        </nav>
    </div>
</header>

<main>
    <section class="hero" id="inicio">
        <div class="hero-media <?= $config['banner_path'] ? '' : 'fallback' ?>">
            <?php if ($config['banner_path']): ?><img src="<?= htmlspecialchars($config['banner_path']) ?>" alt=""><?php endif; ?>
        </div>
        <div class="wrap hero-content">
            <span class="eyebrow"><?= htmlspecialchars($modelCopy['eyebrow']) ?></span>
            <h1><?= htmlspecialchars($config['titulo']) ?></h1>
            <?php if ($config['subtitulo']): ?><p class="hero-copy"><?= htmlspecialchars($config['subtitulo']) ?></p><?php endif; ?>
            <div class="hero-actions">
                <a class="primary-button" href="#produtos"><?= htmlspecialchars($config['texto_cta']) ?></a>
                <?php if ($location): ?><span class="secondary-button"><?= htmlspecialchars($location) ?></span><?php endif; ?>
            </div>
            <div class="hero-note">Escolha os produtos, monte o pedido e fale direto com a loja.</div>
        </div>
    </section>

    <?php if ($config['mostrar_beneficios']): ?>
        <section class="trust">
            <div class="wrap trust-grid">
                <?php foreach ($config['beneficios'] as $benefit): ?>
                    <div class="trust-item">
                        <span class="trust-icon material-symbols-outlined" aria-hidden="true"><?= htmlspecialchars($benefit['icone']) ?></span>
                        <div><strong><?= htmlspecialchars($benefit['titulo']) ?></strong><span><?= htmlspecialchars($benefit['texto']) ?></span></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <div class="catalog-shell"><section class="wrap catalog" id="produtos">
        <div class="section-head">
            <div><span class="catalog-count"><?= count($produtos) ?> produto<?= count($produtos) === 1 ? '' : 's' ?> <?= count($produtos) === 1 ? 'disponível' : 'disponíveis' ?></span><span class="eyebrow">Catálogo</span><h2><?= htmlspecialchars($modelCopy['catalog']) ?></h2></div>
            <p class="section-copy"><?= htmlspecialchars($config['descricao_site'] ?: 'Adicione os produtos ao carrinho e envie o pedido pronto para nossa equipe.') ?></p>
        </div>
        <div class="products">
            <?php if ($produtos): ?>
                <?php foreach ($produtos as $produto): ?>
                    <article class="product" data-product-card="<?= (int)$produto['id'] ?>">
                        <div class="product-image">
                            <?php if ($produto['imagem']): ?><img src="<?= htmlspecialchars($produto['imagem']) ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" loading="lazy">
                            <?php else: ?><span class="product-placeholder"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4V5Zm3 11 3.2-3.5 2.4 2.4 1.8-2 2.6 3.1M8.5 9.5h.01" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Produto sem imagem</span></span><?php endif; ?>
                        </div>
                        <div class="product-body">
                            <div class="product-name"><?= htmlspecialchars($produto['nome']) ?></div>
                            <div class="product-desc"><?= htmlspecialchars((string)$produto['descricao']) ?></div>
                            <?php if ($config['mostrar_estoque']): ?><div class="stock"><?= (int)$produto['quantidade'] ?> unidade<?= (int)$produto['quantidade'] === 1 ? ' disponível' : 's disponíveis' ?></div><?php endif; ?>
                            <div class="product-foot">
                                <span class="price">R$ <?= number_format((float)$produto['preco'], 2, ',', '.') ?></span>
                                <button class="add-button" type="button" data-product="<?= (int)$produto['id'] ?>" data-configurable="<?= $produto['tem_complementos'] || $produto['usa_tamanhos'] ? '1' : '0' ?>" aria-label="Adicionar <?= htmlspecialchars($produto['nome']) ?> ao carrinho">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 7h12l1 13H5L6 7Zm3 1V6a3 3 0 0 1 6 0v2m3 5h-6m3-3v6" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    <span>Adicionar</span>
                                </button>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty">Os produtos aparecerão aqui assim que forem publicados no catálogo.</div>
            <?php endif; ?>
        </div>
    </section></div>
</main>

<footer class="footer">
    <div class="wrap footer-inner"><strong><?= htmlspecialchars($nomeSite) ?></strong><span><?= htmlspecialchars($location) ?></span><span>Pedidos preparados com o Uplar Site PRO</span></div>
</footer>

<div class="custom-product-overlay" id="customProductOverlay" hidden onclick="if(event.target===this)closeCustomProduct()">
    <section class="custom-product-modal" role="dialog" aria-modal="true" aria-labelledby="customProductHeading">
        <header class="custom-product-head"><strong id="customProductHeading">Personalize seu produto</strong><button class="custom-product-close" type="button" onclick="closeCustomProduct()" aria-label="Fechar">×</button></header>
        <div class="custom-product-body" id="customProductBody"></div>
        <footer class="custom-product-footer" id="customProductFooter"></footer>
    </section>
</div>

<div class="drawer-overlay" id="drawerOverlay"></div>
<aside class="drawer" id="cartDrawer" aria-label="Carrinho de compras" aria-hidden="true">
    <header class="drawer-head"><div class="drawer-title">Seu pedido</div><button class="icon-button" id="closeCart" type="button" aria-label="Fechar carrinho">×</button></header>
    <div class="drawer-body">
        <div id="cartItems"><div class="cart-empty">Seu carrinho está vazio.</div></div>
        <div id="cartCheckout" hidden>
            <div class="summary" id="cartSummary"></div>
            <div class="coupon-box">
                <label class="coupon-label" for="couponCodePublic">Cupom de desconto</label>
                <div id="couponControl">
                    <div class="coupon-entry"><input id="couponCodePublic" maxlength="32" placeholder="Digite seu cupom"><button id="applyCoupon" type="button">Aplicar</button></div>
                </div>
            </div>
            <div class="checkout">
                <h3>Como você quer receber?</h3>
                <div class="delivery-tabs">
                    <button class="delivery-tab active" type="button" data-delivery="retirada">Retirada</button>
                    <button class="delivery-tab" type="button" data-delivery="entrega">Entrega</button>
                </div>
                <div class="form-field" id="deliveryAddressField" hidden><label for="deliveryAddress">Endereço de entrega *</label><input id="deliveryAddress" maxlength="255" placeholder="Rua, número, bairro e cidade"></div>
                <div class="form-field"><label for="customerName">Seu nome</label><input id="customerName" maxlength="120" placeholder="Como devemos te chamar?"></div>
                <div class="form-field"><label for="customerPhone">Seu WhatsApp / telefone</label><input id="customerPhone" maxlength="30" inputmode="tel" placeholder="(99) 99999-9999"></div>
                <div class="form-field"><label for="orderNotes">Observações</label><textarea id="orderNotes" maxlength="500" placeholder="Cor, tamanho ou outra informação para a loja"></textarea></div>
            </div>
        </div>
    </div>
    <footer class="drawer-foot" id="drawerFoot" hidden>
        <div class="checkout-actions">
            <?php if (in_array($config['finalizacao_pedido'], ['infinitepay', 'ambos'], true)): ?>
                <button class="checkout-button payment" data-finish-order="infinitepay" type="button">Pagar com Pix ou cartão</button>
            <?php endif; ?>
            <?php if (in_array($config['finalizacao_pedido'], ['whatsapp', 'ambos'], true)): ?>
                <button class="checkout-button <?= $config['finalizacao_pedido'] === 'ambos' ? 'secondary' : '' ?>" data-finish-order="whatsapp" type="button">Enviar pedido pelo WhatsApp</button>
            <?php endif; ?>
        </div>
    </footer>
</aside>

<script>
const SITE_SLUG = <?= json_encode($slug) ?>;
const SITE_PRODUCTS = <?= json_encode($produtos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const API_URL = '/site_pro_api.php';
const TRACK_URL = '/site_pro_track.php';
const SHIPPING = <?= json_encode((float)$config['valor_frete']) ?>;
const PUBLIC_CSRF = <?= json_encode($_SESSION['site_pro_public_csrf']) ?>;
const formatMoney = value => Number(value || 0).toLocaleString('pt-BR', {style:'currency', currency:'BRL'});
const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[char]));
let deliveryType = 'retirada';
let currentCart = null;
let customProduct = null;
let customProductConfig = null;
let customProductSelections = {};
let customProductNote = '';
let customProductSize = '';
let customProductQuantity = 1;
let customProductBusy = false;
const SITE_PRO_VISITOR_KEY = 'uplar_site_pro_visitor';
const SITE_PRO_SESSION_KEY = 'uplar_site_pro_session';
const siteProRuntime = {
    visitorId:localStorage.getItem(SITE_PRO_VISITOR_KEY) || trackingId(),
    sessionId:sessionStorage.getItem(SITE_PRO_SESSION_KEY) || trackingId(),
    startedAt:Date.now(),
    maxScroll:0,
    ended:false
};
localStorage.setItem(SITE_PRO_VISITOR_KEY, siteProRuntime.visitorId);
sessionStorage.setItem(SITE_PRO_SESSION_KEY, siteProRuntime.sessionId);

function trackingId() {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();
    if (!window.crypto?.getRandomValues) return String(Date.now()) + Math.random().toString(16).slice(2);
    const bytes = new Uint8Array(16);
    window.crypto.getRandomValues(bytes);
    return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
}

function siteProTrack(eventType, productId = null, value = null, beacon = false) {
    const payload = {
        slug:SITE_SLUG,
        event_type:eventType,
        visitor_id:siteProRuntime.visitorId,
        session_id:siteProRuntime.sessionId,
        referrer:document.referrer || ''
    };
    if (productId) payload.product_id = Number(productId);
    if (value !== null) payload.value = Math.max(0, Math.round(Number(value) || 0));
    const body = JSON.stringify(payload);
    if (beacon && navigator.sendBeacon) {
        return navigator.sendBeacon(TRACK_URL, new Blob([body], {type:'application/json'}));
    }
    return fetch(TRACK_URL, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body,
        keepalive:beacon
    }).catch(() => null);
}

function showToast(message) {
    document.querySelector('.toast')?.remove();
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = message;
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 2600);
}

async function api(action, data = null) {
    const options = {credentials:'same-origin'};
    let url = `${API_URL}?acao=${encodeURIComponent(action)}&slug=${encodeURIComponent(SITE_SLUG)}`;
    if (data) {
        const body = new FormData();
        body.append('slug', SITE_SLUG);
        body.append('csrf_token', PUBLIC_CSRF);
        Object.entries(data).forEach(([key, value]) => body.append(key, value));
        options.method = 'POST';
        options.body = body;
    }
    const response = await fetch(url, options);
    const result = await response.json();
    if (!result.ok) throw new Error(result.error || 'Não foi possível concluir esta ação.');
    return result;
}

function setDrawer(open) {
    document.getElementById('drawerOverlay').classList.toggle('open', open);
    document.getElementById('cartDrawer').classList.toggle('open', open);
    document.getElementById('cartDrawer').setAttribute('aria-hidden', open ? 'false' : 'true');
    document.body.classList.toggle('locked', open);
    if (open) loadCart();
}
document.getElementById('openCart').addEventListener('click', () => {
    siteProTrack('cart_view');
    setDrawer(true);
});
document.getElementById('closeCart').addEventListener('click', () => setDrawer(false));
document.getElementById('drawerOverlay').addEventListener('click', () => setDrawer(false));
document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    if (!document.getElementById('customProductOverlay').hidden) closeCustomProduct();
    else setDrawer(false);
});

document.querySelectorAll('.add-button').forEach(button => button.addEventListener('click', async () => {
    const product = SITE_PRODUCTS.find(item => Number(item.id) === Number(button.dataset.product));
    if (!product) return;
    if (button.dataset.configurable === '1') {
        await openCustomProduct(product);
        return;
    }
    button.disabled = true;
    try {
        await api('carrinho_add', {produto_id:product.id, quantidade:1});
        siteProTrack('add_to_cart', product.id);
        await loadCart(false);
        showToast('Produto adicionado ao carrinho.');
    } catch (error) {
        showToast(error.message);
    } finally {
        button.disabled = false;
    }
}));

function closeCustomProduct() {
    document.getElementById('customProductOverlay').hidden = true;
    document.body.classList.remove('locked');
    customProduct = null;
    customProductConfig = null;
}

async function openCustomProduct(product) {
    customProduct = product;
    customProductConfig = null;
    customProductSelections = {};
    customProductNote = '';
    customProductSize = '';
    customProductQuantity = 1;
    const overlay = document.getElementById('customProductOverlay');
    const body = document.getElementById('customProductBody');
    document.getElementById('customProductHeading').textContent = 'Personalize seu produto';
    body.innerHTML = '<p>Carregando opções…</p>';
    document.getElementById('customProductFooter').innerHTML = '';
    overlay.hidden = false;
    document.body.classList.add('locked');
    try {
        const url = `${API_URL}?acao=produto_complementos&slug=${encodeURIComponent(SITE_SLUG)}&produto_id=${encodeURIComponent(product.id)}`;
        const response = await fetch(url, {credentials:'same-origin'});
        const result = await response.json();
        if (!response.ok || !result.ok) throw new Error(result.error || 'Não foi possível carregar as opções.');
        customProductConfig = result;
        renderCustomProduct();
    } catch (error) {
        body.innerHTML = `<p>${escapeHtml(error.message)}</p>`;
        document.getElementById('customProductFooter').innerHTML = '<button class="custom-product-submit" type="button" onclick="closeCustomProduct()">Fechar</button>';
    }
}

function customSelectionUnits(group) {
    return Object.values(customProductSelections[group.id] || {}).reduce((sum, quantity) => sum + Number(quantity || 0), 0);
}

function customSelectionsPayload() {
    return (customProductConfig?.grupos || []).map(group => ({
        grupo_id:Number(group.id),
        opcoes:Object.entries(customProductSelections[group.id] || {})
            .map(([opcao_id, quantidade]) => ({opcao_id:Number(opcao_id), quantidade:Number(quantidade)}))
            .filter(option => option.quantidade > 0)
    })).filter(group => group.opcoes.length > 0);
}

function customProductValid() {
    if (!customProduct || !customProductConfig) return false;
    if (customProductConfig.usa_tamanhos && !customProductSize) return false;
    return (customProductConfig.grupos || []).every(group => {
        const count = customSelectionUnits(group);
        return count >= Number(group.minimo || 0) && count <= Number(group.maximo || 0);
    });
}

function customProductPriceCents() {
    let cents = Math.round(Number(customProduct?.preco || 0) * 100);
    for (const group of customProductConfig?.grupos || []) {
        for (const option of group.opcoes || []) {
            cents += Math.round(Number(option.preco_adicional || 0) * 100) * Number((customProductSelections[group.id] || {})[option.id] || 0);
        }
    }
    return cents;
}

function setCustomRadio(groupId, optionId) {
    customProductSelections[groupId] = optionId ? {[optionId]:1} : {};
    renderCustomProduct();
}

function changeCustomOption(groupId, optionId, delta) {
    const group = customProductConfig?.grupos?.find(item => Number(item.id) === Number(groupId));
    const option = group?.opcoes?.find(item => Number(item.id) === Number(optionId));
    if (!group || !option) return;
    const selected = {...(customProductSelections[groupId] || {})};
    const current = Number(selected[optionId] || 0);
    const total = customSelectionUnits(group);
    const next = Math.max(0, Math.min(Number(option.max_quantidade), current + delta));
    if (delta > 0 && total >= Number(group.maximo)) return;
    if (next) selected[optionId] = next; else delete selected[optionId];
    customProductSelections[groupId] = selected;
    renderCustomProduct();
}

function renderCustomProduct() {
    if (!customProduct || !customProductConfig) return;
    document.getElementById('customProductHeading').textContent = customProduct.nome;
    const sizeChoices = customProductConfig.usa_tamanhos ? `
        <section class="custom-product-group">
            <div class="custom-product-group-title">Escolha o tamanho *</div>
            <div class="custom-product-group-help">Selecione uma variação disponível</div>
            ${(customProductConfig.tamanhos || []).map(size => `<label class="custom-product-option"><input type="radio" name="site-product-size" value="${escapeHtml(size.tamanho)}" ${customProductSize === size.tamanho ? 'checked' : ''} ${Number(size.quantidade) < 1 ? 'disabled' : ''} onchange="customProductSize=this.value;customProductQuantity=1;renderCustomProduct()"><span class="custom-product-option-name">${escapeHtml(size.tamanho)}</span><span class="custom-product-option-price">${Number(size.quantidade) > 0 ? `${Number(size.quantidade)} disponível(is)` : 'Indisponível'}</span></label>`).join('')}
        </section>` : '';
    const groups = (customProductConfig.grupos || []).map(group => {
        const single = Number(group.maximo) === 1;
        const total = customSelectionUnits(group);
        const required = Number(group.minimo) > 0;
        const limit = Number(group.minimo) === Number(group.maximo) ? `${group.minimo}` : `${group.minimo} a ${group.maximo}`;
        const help = `${required ? `Obrigatório · Escolha ${limit}` : `Opcional · Até ${group.maximo} unidade(s)`}${group.descricao ? ` · ${escapeHtml(group.descricao)}` : ''}`;
        const none = single && !required ? `<label class="custom-product-option"><input type="radio" name="custom-group-${group.id}" ${total === 0 ? 'checked' : ''} onchange="setCustomRadio(${Number(group.id)},0)"><span class="custom-product-option-name">Sem escolha</span><span class="custom-product-option-price">${formatMoney(0)}</span></label>` : '';
        const options = (group.opcoes || []).map(option => {
            const quantity = Number((customProductSelections[group.id] || {})[option.id] || 0);
            const price = Number(option.preco_adicional || 0);
            if (single) return `<label class="custom-product-option"><input type="radio" name="custom-group-${group.id}" ${quantity ? 'checked' : ''} onchange="setCustomRadio(${Number(group.id)},${Number(option.id)})"><span class="custom-product-option-name">${escapeHtml(option.nome)}</span><span class="custom-product-option-price">${price > 0 ? `+ ${formatMoney(price)}` : formatMoney(0)}</span></label>`;
            const atGroupLimit = quantity === 0 && total >= Number(group.maximo);
            const atOptionLimit = quantity >= Number(option.max_quantidade);
            return `<div class="custom-product-option"><span class="custom-product-option-name">${escapeHtml(option.nome)}</span><span class="custom-product-option-price">${price > 0 ? `+ ${formatMoney(price)}` : formatMoney(0)}</span><span class="custom-product-step"><button type="button" aria-label="Remover ${escapeHtml(option.nome)}" onclick="changeCustomOption(${Number(group.id)},${Number(option.id)},-1)" ${quantity < 1 ? 'disabled' : ''}>−</button><span>${quantity}</span><button type="button" aria-label="Adicionar ${escapeHtml(option.nome)}" onclick="changeCustomOption(${Number(group.id)},${Number(option.id)},1)" ${atGroupLimit || atOptionLimit ? 'disabled' : ''}>+</button></span></div>`;
        }).join('');
        return `<section class="custom-product-group"><div class="custom-product-group-title">${escapeHtml(group.nome)}${required ? ' *' : ''}</div><div class="custom-product-group-help">${help}</div>${none}${options}</section>`;
    }).join('');
    const max = customProductConfig.usa_tamanhos
        ? Number((customProductConfig.tamanhos || []).find(size => size.tamanho === customProductSize)?.quantidade || 0)
        : Number(customProduct.quantidade || 999);
    customProductQuantity = Math.min(Math.max(1, customProductQuantity), Math.max(1, max || 1));
    const body = document.getElementById('customProductBody');
    const previousNote = customProductNote;
    body.innerHTML = `<div class="custom-product-title">${escapeHtml(customProduct.nome)}</div><div class="custom-product-price" id="customProductUnitPrice">${formatMoney(customProductPriceCents() / 100)}</div>${customProduct.descricao ? `<div class="custom-product-description">${escapeHtml(customProduct.descricao)}</div>` : ''}${sizeChoices}${groups}<section class="custom-product-group"><label class="custom-product-group-title" for="customProductNote">Observações do pedido</label><textarea class="custom-product-note" id="customProductNote" maxlength="500" placeholder="Ex.: embalagem para presente, molho à parte">${escapeHtml(previousNote)}</textarea></section>`;
    document.getElementById('customProductNote').addEventListener('input', event => { customProductNote = event.target.value; });
    document.getElementById('customProductFooter').innerHTML = `<div class="custom-product-quantity"><span>Quantidade</span><span class="custom-product-step"><button type="button" aria-label="Diminuir quantidade" onclick="changeCustomProductQuantity(-1)" ${customProductQuantity <= 1 ? 'disabled' : ''}>−</button><span>${customProductQuantity}</span><button type="button" aria-label="Aumentar quantidade" onclick="changeCustomProductQuantity(1)" ${customProductQuantity >= max ? 'disabled' : ''}>+</button></span></div><button class="custom-product-submit" type="button" onclick="addCustomProduct()" ${!customProductValid() || customProductBusy ? 'disabled' : ''}>Adicionar — ${formatMoney(customProductPriceCents() * customProductQuantity / 100)}</button>`;
}

function changeCustomProductQuantity(delta) {
    const max = customProductConfig?.usa_tamanhos
        ? Number((customProductConfig.tamanhos || []).find(size => size.tamanho === customProductSize)?.quantidade || 0)
        : Number(customProduct?.quantidade || 999);
    customProductQuantity = Math.min(max, Math.max(1, customProductQuantity + delta));
    renderCustomProduct();
}

async function addCustomProduct() {
    if (!customProductValid() || customProductBusy) return;
    customProductBusy = true;
    renderCustomProduct();
    try {
        await api('carrinho_add', {
            produto_id:customProduct.id,
            quantidade:customProductQuantity,
            tamanho:customProductSize,
            personalizacao:JSON.stringify(customSelectionsPayload()),
            observacao_item:customProductNote.trim()
        });
        siteProTrack('add_to_cart', customProduct.id);
        await loadCart(false);
        closeCustomProduct();
        showToast('Produto adicionado ao carrinho.');
    } catch (error) {
        showToast(error.message);
    } finally {
        customProductBusy = false;
        if (customProduct) renderCustomProduct();
    }
}

async function loadCart(render = true) {
    try {
        currentCart = await api('carrinho_get');
        document.getElementById('cartCount').textContent = currentCart.count || 0;
        if (render) renderCart();
    } catch (error) {
        if (render) document.getElementById('cartItems').innerHTML = `<div class="cart-empty">${escapeHtml(error.message)}</div>`;
    }
}

function renderCart() {
    const container = document.getElementById('cartItems');
    const checkout = document.getElementById('cartCheckout');
    const footer = document.getElementById('drawerFoot');
    const items = currentCart?.itens || [];
    if (!items.length) {
        container.innerHTML = '<div class="cart-empty">Seu carrinho está vazio.<br>Adicione um produto para começar.</div>';
        checkout.hidden = true;
        footer.hidden = true;
        return;
    }
    const reviewBanner = currentCart?.revisao_requerida
        ? '<div class="cart-review-banner">Uma personalização ou preço mudou. Remova o item indicado e configure-o novamente antes de finalizar.</div>'
        : '';
    container.innerHTML = reviewBanner + items.map(item => `
        <div class="cart-item">
            <div class="cart-image">${item.imagem ? `<img src="${escapeHtml(item.imagem)}" alt="">` : ''}</div>
            <div><div class="cart-name">${escapeHtml(item.nome)}</div><div class="cart-price">${formatMoney(item.preco)}</div>
                ${item.tamanho ? `<div class="cart-custom">Tamanho: ${escapeHtml(item.tamanho)}</div>` : ''}
                ${renderCartCustomizations(item.personalizacao)}
                ${item.revisao_requerida ? `<div class="cart-review">${escapeHtml(item.aviso || 'Este item precisa ser revisado.')}</div>` : ''}
                <div class="quantity"><button type="button" data-qty="${item.id}" data-value="${item.qtd_carrinho - 1}" aria-label="Diminuir">−</button><span>${item.qtd_carrinho}</span><button type="button" data-qty="${item.id}" data-value="${item.qtd_carrinho + 1}" aria-label="Aumentar">+</button></div>
            </div>
            <button class="remove" type="button" data-remove="${item.id}">Remover</button>
        </div>`).join('');
    checkout.hidden = false;
    footer.hidden = false;
    updateSummary();
    renderCoupon();
    container.querySelectorAll('[data-qty]').forEach(button => button.addEventListener('click', () => changeQuantity(button.dataset.qty, Number(button.dataset.value))));
    container.querySelectorAll('[data-remove]').forEach(button => button.addEventListener('click', () => removeItem(button.dataset.remove)));
    document.querySelectorAll('[data-finish-order]').forEach(button => { button.disabled = Boolean(currentCart?.revisao_requerida); });
}

function renderCartCustomizations(personalization) {
    if (!personalization || !Array.isArray(personalization.grupos)) return '';
    const groups = personalization.grupos.map(group => {
        const options = (group.opcoes || []).map(option => `${escapeHtml(option.nome)}${Number(option.quantidade) > 1 ? ` × ${Number(option.quantidade)}` : ''}`).join(', ');
        return options ? `<span class="cart-custom-line">${escapeHtml(group.nome)}: ${options}</span>` : '';
    }).join('');
    const note = personalization.observacao ? `<span class="cart-custom-line">Observação: ${escapeHtml(personalization.observacao)}</span>` : '';
    return groups || note ? `<div class="cart-custom">${groups}${note}</div>` : '';
}

function updateSummary() {
    const freight = deliveryType === 'entrega' ? SHIPPING : 0;
    const total = Number(currentCart?.total_produtos ?? currentCart?.total ?? 0) + freight;
    document.getElementById('cartSummary').innerHTML = `
        <div class="summary-row"><span>Produtos</span><span>${formatMoney(currentCart?.subtotal ?? currentCart?.total)}</span></div>
        ${Number(currentCart?.desconto || 0) > 0 ? `<div class="summary-row discount"><span>Desconto (${escapeHtml(currentCart.cupom?.codigo || '')})</span><span>− ${formatMoney(currentCart.desconto)}</span></div>` : ''}
        ${deliveryType === 'entrega' ? `<div class="summary-row"><span>Frete</span><span>${formatMoney(freight)}</span></div>` : ''}
        <div class="summary-row total"><span>Total</span><span>${formatMoney(total)}</span></div>`;
}

function renderCoupon() {
    const control = document.getElementById('couponControl');
    if (!control) return;
    if (currentCart?.cupom) {
        control.innerHTML = `<div class="coupon-applied"><span>${escapeHtml(currentCart.cupom.codigo)} · ${Number(currentCart.cupom.percentual).toLocaleString('pt-BR',{maximumFractionDigits:2})}% aplicado</span><button id="removeCoupon" type="button">Remover</button></div>`;
        document.getElementById('removeCoupon').addEventListener('click', removeCoupon);
        return;
    }
    control.innerHTML = '<div class="coupon-entry"><input id="couponCodePublic" maxlength="32" placeholder="Digite seu cupom"><button id="applyCoupon" type="button">Aplicar</button></div>';
    const input = document.getElementById('couponCodePublic');
    input.addEventListener('input', () => { input.value = input.value.toUpperCase().replace(/[^A-Z0-9_-]/g, ''); });
    input.addEventListener('keydown', event => { if (event.key === 'Enter') applyCoupon(); });
    document.getElementById('applyCoupon').addEventListener('click', applyCoupon);
}

async function applyCoupon() {
    const input = document.getElementById('couponCodePublic');
    const code = input?.value.trim() || '';
    if (!code) {
        showToast('Digite o código do cupom.');
        input?.focus();
        return;
    }
    try {
        await api('cupom_aplicar', {codigo:code});
        await loadCart();
        showToast('Cupom aplicado ao pedido.');
    } catch (error) {
        showToast(error.message);
    }
}

async function removeCoupon() {
    try {
        await api('cupom_remover', {});
        await loadCart();
    } catch (error) {
        showToast(error.message);
    }
}

async function changeQuantity(cartId, quantity) {
    if (quantity < 1) return removeItem(cartId);
    try { await api('carrinho_qtd', {carrinho_id:cartId, quantidade:quantity}); await loadCart(); }
    catch (error) { showToast(error.message); }
}
async function removeItem(cartId) {
    try { await api('carrinho_remover', {carrinho_id:cartId}); await loadCart(); }
    catch (error) { showToast(error.message); }
}

document.querySelectorAll('[data-delivery]').forEach(button => button.addEventListener('click', () => {
    deliveryType = button.dataset.delivery;
    document.querySelectorAll('[data-delivery]').forEach(item => item.classList.toggle('active', item === button));
    document.getElementById('deliveryAddressField').hidden = deliveryType !== 'entrega';
    updateSummary();
}));

document.querySelectorAll('[data-finish-order]').forEach(finishButton => finishButton.addEventListener('click', async event => {
    const button = event.currentTarget;
    const finishType = button.dataset.finishOrder;
    const originalLabel = button.textContent;
    const address = document.getElementById('deliveryAddress').value.trim();
    if (deliveryType === 'entrega' && !address) {
        showToast('Informe o endereço de entrega.');
        document.getElementById('deliveryAddress').focus();
        return;
    }
    button.disabled = true;
    button.textContent = finishType === 'infinitepay' ? 'Abrindo pagamento...' : 'Preparando pedido...';
    siteProTrack('checkout_start');
    try {
        const result = await api('fazer_pedido', {
            finalizacao:finishType,
            tipo:deliveryType,
            nome:document.getElementById('customerName').value.trim(),
            tel:document.getElementById('customerPhone').value.trim(),
            endereco:address,
            obs:document.getElementById('orderNotes').value.trim()
        });
        if (finishType === 'infinitepay') {
            if (!result.checkout_url) throw new Error('Não foi possível abrir o pagamento.');
            window.location.assign(result.checkout_url);
            return;
        }
        await loadCart();
        if (!result.whatsapp) throw new Error('A loja ainda não configurou o WhatsApp.');
        siteProTrack('whatsapp_click');
        window.location.href = `https://wa.me/${result.whatsapp}?text=${result.wa_msg}`;
    } catch (error) {
        showToast(error.message);
    } finally {
        button.disabled = false;
        button.textContent = originalLabel;
    }
}));

loadCart(false);
siteProTrack('store_view');
const navigation = performance.getEntriesByType?.('navigation')?.[0];
window.addEventListener('load', () => {
    siteProTrack('page_load', null, Math.round(navigation?.loadEventEnd || performance.now()));
}, {once:true});
window.addEventListener('scroll', () => {
    const maximum = Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
    siteProRuntime.maxScroll = Math.max(siteProRuntime.maxScroll, Math.min(100, Math.round((window.scrollY / maximum) * 100)));
}, {passive:true});
if ('IntersectionObserver' in window) {
    const viewedProducts = new Set();
    const productObserver = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            const productId = Number(entry.target.dataset.productCard || 0);
            if (!entry.isIntersecting || productId < 1 || viewedProducts.has(productId)) return;
            viewedProducts.add(productId);
            siteProTrack('product_view', productId);
            productObserver.unobserve(entry.target);
        });
    }, {threshold:.4});
    document.querySelectorAll('[data-product-card]').forEach(card => productObserver.observe(card));
}
window.addEventListener('pagehide', () => {
    if (siteProRuntime.ended) return;
    siteProRuntime.ended = true;
    siteProTrack('scroll_depth', null, siteProRuntime.maxScroll, true);
    siteProTrack('session_end', null, Date.now() - siteProRuntime.startedAt, true);
});
</script>
</body>
</html>
