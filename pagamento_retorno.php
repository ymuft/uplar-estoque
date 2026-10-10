<?php
// pagamento_retorno.php — Retorno após checkout InfinityPay
session_start();
require 'db.php';
require 'helpers.php';
require 'subscription.php';
require 'planos_config.php';

$pdo = get_pdo();
$planos_config = get_planos_config($pdo);
$logged_in = !empty($_SESSION['user_id']);
$user_sub = null;
if ($logged_in) {
    $user_sub = get_subscription((int)$_SESSION['user_id'], $pdo);
}

$order_nsu  = $_SESSION['pending_order_nsu']  ?? '';
$plano_key  = $_SESSION['pending_plano_key']  ?? '';
$plano_dias = (int)($_SESSION['pending_plano_dias'] ?? 0);

$return_order_nsu = is_string($_GET['order_nsu'] ?? null) ? strtoupper(trim($_GET['order_nsu'])) : '';
$return_slug = is_string($_GET['slug'] ?? null) ? trim($_GET['slug']) : '';
$return_transaction_nsu = is_string($_GET['transaction_nsu'] ?? null) ? trim($_GET['transaction_nsu']) : '';
$return_receipt_url = is_string($_GET['receipt_url'] ?? null) ? trim($_GET['receipt_url']) : '';

if (!preg_match('/^UPL-[A-F0-9]{16}$/', $return_order_nsu)) $return_order_nsu = '';
if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/', $return_slug)) $return_slug = '';
if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/', $return_transaction_nsu)) $return_transaction_nsu = '';
$receipt_parts = $return_receipt_url !== '' ? parse_url($return_receipt_url) : false;
if (!is_array($receipt_parts)
    || strtolower((string)($receipt_parts['scheme'] ?? '')) !== 'https'
    || strtolower((string)($receipt_parts['host'] ?? '')) !== 'recibo.infinitepay.io'
    || strlen($return_receipt_url) > 512
) {
    $return_receipt_url = '';
}

if ($order_nsu === '' && $logged_in && $return_order_nsu !== '') {
    $order_nsu = $return_order_nsu;
}

$pedido = null;
if ($order_nsu) {
    $st = $pdo->prepare("SELECT * FROM pedidos_pagamento WHERE order_nsu = ? LIMIT 1");
    $st->execute([$order_nsu]);
    $pedido = $st->fetch(PDO::FETCH_ASSOC);
    if ($pedido) {
        $pedido_user_id = (int)($pedido['user_id'] ?? 0);
        $session_user_id = (int)($_SESSION['user_id'] ?? 0);
        $return_matches_order = $return_order_nsu !== '' && hash_equals(strtoupper($order_nsu), $return_order_nsu);
        $user_owns_order = $session_user_id > 0 && $pedido_user_id === $session_user_id;

        if ($return_matches_order && $user_owns_order && $pedido['status'] !== 'pago') {
            $saved_slug = trim((string)($pedido['invoice_slug'] ?? ''));
            $saved_transaction_nsu = trim((string)($pedido['transaction_nsu'] ?? ''));
            $new_slug = $saved_slug === '' ? $return_slug : $saved_slug;
            $new_transaction_nsu = $saved_transaction_nsu === '' ? $return_transaction_nsu : $saved_transaction_nsu;

            if (($saved_slug === '' && $new_slug !== '') || ($saved_transaction_nsu === '' && $new_transaction_nsu !== '') || $return_receipt_url !== '') {
                $pdo->prepare("UPDATE pedidos_pagamento
                    SET invoice_slug = ?, transaction_nsu = ?, receipt_url = COALESCE(NULLIF(?, ''), receipt_url)
                    WHERE id = ?")
                    ->execute([$new_slug ?: null, $new_transaction_nsu ?: null, $return_receipt_url, (int)$pedido['id']]);
                $pedido['invoice_slug'] = $new_slug ?: null;
                $pedido['transaction_nsu'] = $new_transaction_nsu ?: null;
                if ($return_receipt_url !== '') $pedido['receipt_url'] = $return_receipt_url;
            }
        }

        $plano_key  = $pedido['plano_key'];
        $plano_dias = (int)$pedido['plano_dias'];
    }
}

$pago = ($pedido && $pedido['status'] === 'pago');

if ($pago) {
    $_SESSION['pagamento_confirmado'] = true;
    $_SESSION['pagamento_order_nsu']  = $order_nsu;
    $_SESSION['pagamento_plano_key']  = $plano_key;
    $_SESSION['pagamento_plano_dias'] = $plano_dias;
}

$planos_info = [
    'mensal' => ['label' => 'Mensal', 'preco' => formatar_preco_plano($planos_config['mensal']['preco']), 'cor' => '#3b6ff5'],
    'trimestral' => ['label' => 'Trimestral', 'preco' => formatar_preco_plano($planos_config['trimestral']['preco']), 'cor' => '#7c3aed'],
    'anual' => ['label' => 'Anual', 'preco' => formatar_preco_plano($planos_config['anual']['preco']), 'cor' => '#f59e0b'],
    'semestral' => ['label' => 'Semestral', 'preco' => '', 'cor' => '#10b981'],
    'teste' => ['label' => 'Teste', 'preco' => 'R$ 0,01', 'cor' => '#64748b'],
];
$pi = $planos_info[$plano_key] ?? ['label' => 'Plano', 'preco' => '', 'cor' => '#3b6ff5'];
if ($pedido && isset($pedido['valor_centavos'])) {
    $pi['preco'] = formatar_preco_plano((int)$pedido['valor_centavos']);
}
$pedido_user_id = (int)($pedido['user_id'] ?? 0);
$cta_href = 'login.php';
$cta_label = 'Fazer login';
$cta_icon = 'login';
$cta_sub = 'Pagamento confirmado. Faça login para usar o sistema.';
if ($pago && !$logged_in && $pedido_user_id < 1) {
    $cta_href = 'registro.php';
    $cta_label = 'Criar minha conta';
    $cta_icon = 'person_add';
    $cta_sub = 'Crie sua conta agora para acessar o sistema com o plano já ativo.';
} elseif ($pago && $logged_in) {
    $cta_href = ($user_sub && $user_sub['ativa']) ? 'estoque.php' : 'config.php#assinatura';
    $cta_label = ($user_sub && $user_sub['ativa']) ? 'Voltar ao sistema' : 'Ver minha assinatura';
    $cta_icon = ($user_sub && $user_sub['ativa']) ? 'dashboard' : 'card_membership';
    $cta_sub = 'Pagamento confirmado e assinatura atualizada.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $pago ? 'Pagamento confirmado' : 'Verificando pagamento' ?> — Uplar</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
<style>
:root{
    --p:#3b6ff5;--p-dark:#2a55d9;--p-dim:rgba(59,111,245,.08);--p-glow:rgba(59,111,245,.20);
    --ok:#10b981;--ok-dim:rgba(16,185,129,.09);
    --err:#ef4444;--warn:#f59e0b;
    --ink:#0f1c2e;--muted:#5a6a7a;--faint:#94a3b8;
    --bg:#f4f6fb;--card:#ffffff;--border:#e4eaf2;
    --sh:0 1px 4px rgba(15,28,46,.06),0 8px 32px rgba(15,28,46,.05);
}
*{margin:0;padding:0;box-sizing:border-box}
body{
    font-family:'Inter',sans-serif;background:var(--bg);color:var(--ink);
    min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;
    padding:1.5rem;-webkit-font-smoothing:antialiased;
    background-image:radial-gradient(ellipse 55% 45% at 50% 0%,rgba(16,185,129,.09) 0%,transparent 70%);
}

@keyframes fadeUp{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:translateY(0)}}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes popIn{0%{transform:scale(0)}60%{transform:scale(1.12)}100%{transform:scale(1)}}
@keyframes checkDraw{from{stroke-dashoffset:100}to{stroke-dashoffset:0}}

/* Logo topo */
.page-logo{
    display:flex;align-items:center;gap:.5rem;
    font-size:1.1rem;font-weight:800;letter-spacing:-.3px;
    color:var(--p);margin-bottom:1.5rem;
    animation:fadeUp .45s cubic-bezier(.22,1,.36,1) both;
}
.page-logo .material-symbols-outlined{font-size:1.45rem}

/* Card central */
.box{
    background:var(--card);border:1px solid var(--border);border-radius:18px;
    padding:2.5rem 2rem;max-width:400px;width:100%;text-align:center;
    box-shadow:var(--sh);
    animation:fadeUp .5s cubic-bezier(.22,1,.36,1) .05s both;
}

/* Ícone de sucesso */
.ok-ring{
    width:84px;height:84px;border-radius:50%;
    background:var(--ok-dim);border:2px solid rgba(16,185,129,.25);
    display:flex;align-items:center;justify-content:center;
    margin:0 auto 1.4rem;
    animation:popIn .45s cubic-bezier(.22,1,.36,1) .1s both;
}
.ok-ring svg{width:44px;height:44px}
.check-path{
    fill:none;stroke:var(--ok);stroke-width:3;stroke-linecap:round;stroke-linejoin:round;
    stroke-dasharray:100;stroke-dashoffset:100;
    animation:checkDraw .5s ease .5s forwards;
}

/* Spinner aguardando */
.spin-ring{
    width:72px;height:72px;border-radius:50%;
    border:4px solid var(--border);border-top-color:var(--p);
    animation:spin .85s linear infinite;margin:0 auto 1.4rem;
}

/* Badge plano */
.plan-badge{
    display:inline-flex;align-items:center;gap:.35rem;
    border-radius:50px;padding:.28rem .8rem;
    font-size:.74rem;font-weight:700;margin-bottom:.9rem;
}
.plan-badge .material-symbols-outlined{font-size:.82rem}

h2{font-size:1.3rem;font-weight:800;letter-spacing:-.03em;margin-bottom:.45rem}
.sub{font-size:.83rem;color:var(--muted);line-height:1.6;margin-bottom:1.3rem}

/* Grid de info */
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:1.3rem}
.ig{background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:.6rem .8rem;text-align:left}
.ig-l{font-size:.63rem;color:var(--faint);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.1rem}
.ig-v{font-size:.85rem;font-weight:700;color:var(--ink)}

/* Botões */
.btn-main{
    display:flex;align-items:center;justify-content:center;gap:.4rem;
    width:100%;padding:.82rem;border-radius:11px;
    background:var(--ok);color:#fff;border:none;
    font-family:'Inter',sans-serif;font-size:.9rem;font-weight:700;
    text-decoration:none;box-shadow:0 4px 16px rgba(16,185,129,.28);
    transition:all .2s;cursor:pointer;
}
.btn-main:hover{filter:brightness(1.08);transform:translateY(-2px)}
.btn-main.blue{background:var(--p);box-shadow:0 4px 16px var(--p-glow)}
.btn-main .material-symbols-outlined{font-size:.95rem}
.btn-back{
    display:flex;align-items:center;justify-content:center;gap:.3rem;
    margin-top:.55rem;font-size:.76rem;color:var(--muted);
    background:none;border:none;cursor:pointer;width:100%;padding:.38rem;
    font-family:'Inter',sans-serif;transition:color .18s;
}
.btn-back:hover{color:var(--p)}
.btn-back .material-symbols-outlined{font-size:.88rem}

.divider{height:1px;background:var(--border);margin:1.1rem 0}

/* Aviso info */
.aviso{
    background:rgba(245,158,11,.07);border:1px solid rgba(245,158,11,.22);
    border-radius:10px;padding:.7rem .85rem;
    font-size:.78rem;color:#92400e;
    display:flex;gap:.4rem;align-items:flex-start;
    margin-top:.85rem;text-align:left;
}
.aviso .material-symbols-outlined{font-size:.92rem;flex-shrink:0;margin-top:1px}

@media(max-width:440px){.box{padding:1.8rem 1.2rem}}
</style>
</head>
<body>

<div class="page-logo">
    <span class="material-symbols-outlined">inventory_2</span>Uplar
</div>

<div class="box">
    <?php if ($pago): ?>

    <div class="ok-ring">
        <svg viewBox="0 0 48 48"><path d="M12 24l10 10L38 16" class="check-path"/></svg>
    </div>

    <div class="plan-badge" style="background:color-mix(in srgb,<?= htmlspecialchars($pi['cor']) ?> 11%,transparent);color:<?= htmlspecialchars($pi['cor']) ?>">
        <span class="material-symbols-outlined">workspace_premium</span>
        Plano <?= htmlspecialchars($pi['label']) ?>
    </div>

    <h2>Pagamento confirmado! </h2>
    <p class="sub"><?= htmlspecialchars($cta_sub) ?></p>

    <div class="info-grid">
        <div class="ig"><div class="ig-l">Plano</div><div class="ig-v"><?= htmlspecialchars($pi['label']) ?></div></div>
        <div class="ig"><div class="ig-l">Valor</div><div class="ig-v"><?= htmlspecialchars($pi['preco']) ?></div></div>
        <div class="ig"><div class="ig-l">Acesso</div><div class="ig-v"><?= $plano_dias ?> dias</div></div>
        <div class="ig"><div class="ig-l">Válido até</div><div class="ig-v"><?= date('d/m/Y', strtotime("+{$plano_dias} days")) ?></div></div>
    </div>

    <a href="<?= htmlspecialchars($cta_href) ?>" class="btn-main">
        <span class="material-symbols-outlined"><?= htmlspecialchars($cta_icon) ?></span><?= htmlspecialchars($cta_label) ?>
    </a>

    <?php elseif ($order_nsu): ?>

    <div class="spin-ring" id="spinRing"></div>
    <h2>Verificando pagamento…</h2>
    <p class="sub">Aguardando confirmação da InfinityPay. Isso leva alguns segundos após o pagamento.</p>

    <div class="aviso">
        <span class="material-symbols-outlined">info</span>
        Se você pagou via Pix, aguarde alguns instantes. A confirmação é automática.
    </div>

    <div class="divider"></div>

    <button class="btn-main blue" id="btnVerificar" style="display:none" onclick="reiniciar()">
        <span class="material-symbols-outlined">refresh</span>Verificar novamente
    </button>
    <button class="btn-back" onclick="window.location.href='planos.php'">
        <span class="material-symbols-outlined">arrow_back</span>Voltar aos planos
    </button>

    <script>
    let tentativas = 0;
    const MAX = 60;

    async function verificar() {
        tentativas++;
        try {
            const r = await fetch('payment_api.php?acao=verificar_pagamento', {credentials:'same-origin'});
            const j = await r.json();
            if (j.ok && j.pago) { location.reload(); return; }
        } catch(e) {}

        if (tentativas < MAX) {
            setTimeout(verificar, 3000);
        } else {
            document.getElementById('spinRing').style.display = 'none';
            document.querySelector('h2').textContent = 'Ainda aguardando…';
            document.querySelector('.sub').textContent = 'Seu pagamento pode estar sendo processado. Clique abaixo para verificar novamente.';
            document.getElementById('btnVerificar').style.display = 'flex';
        }
    }
    function reiniciar() {
        tentativas = 0;
        document.getElementById('btnVerificar').style.display = 'none';
        document.getElementById('spinRing').style.display = 'block';
        verificar();
    }
    setTimeout(verificar, 2500);
    </script>

    <?php else: ?>

    <div style="font-size:2.8rem;margin:0 auto .9rem;line-height:1"></div>
    <h2>Sessão não encontrada</h2>
    <p class="sub">Não foi possível identificar seu pedido. Se você realizou o pagamento, entre em contato via WhatsApp.</p>
    <a href="planos.php" class="btn-main blue">
        <span class="material-symbols-outlined">arrow_back</span>Voltar aos planos
    </a>

    <?php endif; ?>
</div>

</body>
</html>
