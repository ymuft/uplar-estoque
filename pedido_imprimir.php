<?php
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

function pedido_print_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pedido_print_money($value): string
{
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

$pedidoId = filter_var($_GET['id'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$pedidoId) {
    http_response_code(404);
    echo 'Pedido não encontrado.';
    exit;
}

$pdo = get_pdo();
$userId = (int)$_SESSION['user_id'];
$orderStmt = $pdo->prepare("SELECT * FROM vitrine_pedidos WHERE id = ? AND user_id = ? AND status = 'confirmado' LIMIT 1");
$orderStmt->execute([$pedidoId, $userId]);
$pedido = $orderStmt->fetch(PDO::FETCH_ASSOC);
if (!$pedido) {
    http_response_code(404);
    echo 'Pedido não encontrado.';
    exit;
}

$storeStmt = $pdo->prepare('SELECT u.username, vc.nome_loja, vc.endereco, vc.cidade_uf, vc.horario_ret FROM users u LEFT JOIN vitrine_config vc ON vc.user_id = u.id WHERE u.id = ? LIMIT 1');
$storeStmt->execute([$userId]);
$store = $storeStmt->fetch(PDO::FETCH_ASSOC) ?: [];
$storeName = trim((string)($store['nome_loja'] ?? ''));
if ($storeName === '') {
    $storeName = trim((string)($store['username'] ?? ''));
}

$items = json_decode((string)($pedido['itens_json'] ?? ''), true);
$items = is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
$isDelivery = ($pedido['tipo_entrega'] ?? '') === 'entrega';
$isPickup = ($pedido['tipo_entrega'] ?? '') === 'retirada';
$orderType = $isDelivery ? 'ENTREGA' : ($isPickup ? 'RETIRADA' : 'NÃO INFORMADO');
$discount = is_numeric($pedido['desconto'] ?? null) ? max(0, (float)$pedido['desconto']) : 0.0;
$subtotal = 0.0;
$itemsHaveOriginalSubtotal = false;
foreach ($items as $item) {
    if (is_numeric($item['subtotal_original'] ?? null)) {
        $subtotal += (float)$item['subtotal_original'];
        $itemsHaveOriginalSubtotal = true;
    } elseif (is_numeric($item['subtotal'] ?? null)) {
        $subtotal += (float)$item['subtotal'];
    } elseif (is_numeric($item['preco_original'] ?? $item['preco'] ?? null)) {
        $subtotal += (float)($item['preco_original'] ?? $item['preco']) * max(0, (int)($item['quantidade'] ?? 0));
    }
}
if ($discount > 0 && !$itemsHaveOriginalSubtotal) {
    $subtotal += $discount;
}
$shipping = $isDelivery && is_numeric($pedido['valor_frete'] ?? null) ? max(0, (float)$pedido['valor_frete']) : 0.0;
$customerName = trim((string)($pedido['cliente_nome'] ?? ''));
$customerPhone = trim((string)($pedido['cliente_tel'] ?? ''));
$address = trim((string)($pedido['endereco_ent'] ?? ''));
$notes = trim((string)($pedido['obs'] ?? ''));
$paymentMethod = trim((string)($pedido['pagamento_metodo'] ?? ''));
$displayDate = '';
try {
    $displayDate = (new DateTimeImmutable((string)$pedido['criado_em']))->format('d/m/Y - H:i');
} catch (Throwable $e) {
    $displayDate = '';
}
$autoPrint = ($_GET['auto'] ?? '') === '1';
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pedido #<?= (int)$pedido['id'] ?> — <?= pedido_print_escape($storeName) ?></title>
<style>
:root{color-scheme:light;font-family:Arial,Helvetica,sans-serif;color:#111;background:#f1f2f4}
*{box-sizing:border-box}
body{margin:0;padding:1.25rem;color:#111;background:#f1f2f4;font-size:12px;line-height:1.35}
.paper{width:min(100%,74mm);margin:0 auto;padding:4mm;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.12);overflow-wrap:anywhere}
.print-actions{width:min(100%,74mm);margin:0 auto 1rem;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
.print-actions button{padding:.6rem .8rem;border:1px solid #bbb;border-radius:6px;background:#fff;color:#111;font:inherit;font-weight:700;cursor:pointer}
.print-actions p{flex-basis:100%;margin:0;color:#444;font-size:11px}
header{text-align:center;margin-bottom:4mm}
.store-name{margin:0;font-size:16px;font-weight:800;text-transform:uppercase;overflow-wrap:anywhere}
.document-kind{margin-top:2px;font-size:11px;font-weight:700;letter-spacing:.04em}
.not-fiscal{margin-top:2px;font-size:9px;text-transform:uppercase}
.order-title{margin-top:4mm;font-size:14px;font-weight:800}
.order-date{margin-top:1mm}
.order-status{margin-top:1mm;font-weight:800}
.rule{border:0;border-top:1px dashed #777;margin:3mm 0}
section{margin:0 0 3mm}
h2{margin:0 0 1.5mm;font-size:10px;letter-spacing:.04em;text-transform:uppercase}
.field{margin:.5mm 0;white-space:pre-wrap;overflow-wrap:anywhere}
.warning{padding:2mm;border:1px solid #111;font-weight:800}
.item{padding:0 0 2mm;margin:0 0 2mm;border-bottom:1px dotted #aaa;break-inside:avoid;page-break-inside:avoid}
.item:last-child{border-bottom:0;margin-bottom:0}
.item-name{font-weight:700;overflow-wrap:anywhere}
.item-variation{margin-top:.5mm}
.item-custom{margin-top:1.5mm;font-size:9pt;line-height:1.25}
.item-custom-title{font-size:8pt;font-weight:800;text-transform:uppercase;letter-spacing:.03em}
.item-custom ul{margin:.4mm 0 .7mm;padding-left:4mm;list-style:none}
.item-custom li{margin:.3mm 0;overflow-wrap:anywhere}
.item-note{margin-top:1mm;font-weight:600;white-space:pre-wrap;overflow-wrap:anywhere}
.item-price{display:flex;justify-content:space-between;gap:2mm;margin-top:1mm}
.totals{margin-top:1mm}
.total-row{display:flex;justify-content:space-between;gap:3mm;margin:.7mm 0}
.total-row strong:last-child{text-align:right}
.grand-total{padding-top:1.5mm;border-top:1px solid #111;font-size:14px;font-weight:800}
.observations{white-space:pre-wrap;overflow-wrap:anywhere}
.end-message{text-align:center;font-weight:700;letter-spacing:.03em}
@page{margin:3mm}
@media print{
  :root,body{background:#fff!important;color:#000!important}
  body{padding:0;font-size:9pt;line-height:1.3}
  .paper{width:min(100%,74mm);margin:0 auto;padding:0;box-shadow:none}
  .print-actions{display:none!important}
  .rule{border-top-color:#555}
  a{color:#000;text-decoration:none}
}
</style>
</head>
<body>
<div class="print-actions" aria-label="Ações de impressão">
    <button type="button" onclick="window.print()">Imprimir pedido</button>
    <button type="button" onclick="window.close()">Fechar</button>
    <p>Se a impressão automática não abrir, selecione “Imprimir pedido”. O navegador não informa se a impressora concluiu o trabalho.</p>
</div>
<main class="paper">
    <header>
        <h1 class="store-name"><?= pedido_print_escape($storeName) ?></h1>
        <div class="document-kind">COMANDA DE PEDIDO</div>
        <div class="not-fiscal">Pedido não fiscal</div>
        <div class="order-title">PEDIDO #<?= (int)$pedido['id'] ?></div>
        <?php if ($displayDate !== ''): ?><div class="order-date">Data: <?= pedido_print_escape($displayDate) ?></div><?php endif; ?>
        <div class="order-status">Status: CONFIRMADO</div>
    </header>

    <hr class="rule">
    <section>
        <h2>Cliente</h2>
        <?php if ($customerName !== ''): ?><div class="field"><?= pedido_print_escape($customerName) ?></div><?php endif; ?>
        <?php if ($customerPhone !== ''): ?><div class="field">Telefone: <?= pedido_print_escape($customerPhone) ?></div><?php endif; ?>
    </section>

    <section>
        <h2>Tipo de pedido</h2>
        <div class="field"><?= pedido_print_escape($orderType) ?></div>
        <?php if ($isDelivery): ?>
            <h2>Endereço de entrega</h2>
            <?php if ($address !== ''): ?>
                <div class="field"><?= pedido_print_escape($address) ?></div>
            <?php else: ?>
                <div class="field warning">Endereço não informado — conferir antes da entrega</div>
            <?php endif; ?>
        <?php elseif ($isPickup): ?>
            <h2>Informações de retirada</h2>
            <?php if (trim((string)($store['endereco'] ?? '')) !== ''): ?><div class="field">Local: <?= pedido_print_escape($store['endereco']) ?></div><?php endif; ?>
            <?php if (trim((string)($store['cidade_uf'] ?? '')) !== ''): ?><div class="field"><?= pedido_print_escape($store['cidade_uf']) ?></div><?php endif; ?>
            <?php if (trim((string)($store['horario_ret'] ?? '')) !== ''): ?><div class="field">Horário: <?= pedido_print_escape($store['horario_ret']) ?></div><?php endif; ?>
        <?php endif; ?>
    </section>

    <hr class="rule">
    <section>
        <h2>Produtos</h2>
        <?php if ($items): ?>
            <?php foreach ($items as $item):
                $quantity = max(0, (int)($item['quantidade'] ?? 0));
                $unitValue = is_numeric($item['preco_original'] ?? null)
                    ? (float)$item['preco_original']
                    : (is_numeric($item['preco'] ?? null) ? (float)$item['preco'] : null);
                $lineValue = is_numeric($item['subtotal_original'] ?? null)
                    ? (float)$item['subtotal_original']
                    : (is_numeric($item['subtotal'] ?? null)
                        ? (float)$item['subtotal']
                        : ($unitValue !== null ? $unitValue * $quantity : null));
                $itemName = trim((string)($item['nome'] ?? ''));
                $size = trim((string)($item['tamanho'] ?? ''));
                $customization = is_array($item['personalizacao'] ?? null) ? $item['personalizacao'] : [];
                $customGroups = is_array($customization['grupos'] ?? null) ? $customization['grupos'] : [];
                $itemNotes = trim((string)($item['observacao_item'] ?? $customization['observacao'] ?? ''));
            ?>
                <div class="item">
                    <div class="item-name"><?= pedido_print_escape($itemName !== '' ? $itemName : 'Produto') ?></div>
                    <?php if ($size !== ''): ?><div class="item-variation">Tamanho/variação: <?= pedido_print_escape($size) ?></div><?php endif; ?>
                    <?php foreach ($customGroups as $customGroup):
                        if (!is_array($customGroup)) continue;
                        $groupName = trim((string)($customGroup['nome'] ?? 'Personalização'));
                        $options = is_array($customGroup['opcoes'] ?? null) ? $customGroup['opcoes'] : [];
                        if (!$options) continue;
                    ?>
                        <div class="item-custom">
                            <div class="item-custom-title"><?= pedido_print_escape($groupName) ?></div>
                            <ul><?php foreach ($options as $option): if (!is_array($option)) continue;
                                $optionName = trim((string)($option['nome'] ?? ''));
                                if ($optionName === '') continue;
                                $optionQuantity = max(1, (int)($option['quantidade'] ?? 1));
                                $operator = preg_match('/\b(remover|retirar|sem)\b/iu', $groupName)
                                    || preg_match('/^(sem |remover |retirar )/iu', $optionName) ? '− '
                                    : (preg_match('/\b(adicional|adicionais|extra)\b/iu', $groupName) ? '+ ' : '');
                            ?><li><?= pedido_print_escape($operator . $optionName) ?><?= $optionQuantity > 1 ? ' × ' . $optionQuantity : '' ?></li><?php endforeach; ?></ul>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($itemNotes !== ''): ?><div class="item-note">OBS: <?= pedido_print_escape($itemNotes) ?></div><?php endif; ?>
                    <div class="item-price">
                        <span><?= $quantity ?> x <?= $unitValue !== null ? pedido_print_money($unitValue) : '—' ?></span>
                        <strong><?= $lineValue !== null ? pedido_print_money($lineValue) : '—' ?></strong>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="field">Itens do pedido indisponíveis.</div>
        <?php endif; ?>
    </section>

    <hr class="rule">
    <section class="totals">
        <div class="total-row"><span>Subtotal</span><strong><?= pedido_print_money($subtotal) ?></strong></div>
        <?php if ($discount > 0): ?><div class="total-row"><span>Desconto</span><strong>- <?= pedido_print_money($discount) ?></strong></div><?php endif; ?>
        <?php if ($isDelivery && $shipping > 0): ?><div class="total-row"><span>Entrega</span><strong><?= pedido_print_money($shipping) ?></strong></div><?php endif; ?>
        <div class="total-row grand-total"><span>TOTAL</span><strong><?= pedido_print_money($pedido['total'] ?? 0) ?></strong></div>
        <?php if ($paymentMethod !== ''): ?><div class="field">Pagamento: <?= pedido_print_escape($paymentMethod) ?></div><?php endif; ?>
    </section>

    <?php if ($notes !== ''): ?>
        <hr class="rule">
        <section>
            <h2>Observações</h2>
            <div class="observations"><?= pedido_print_escape($notes) ?></div>
        </section>
    <?php endif; ?>
    <hr class="rule">
    <div class="end-message">FIM DO PEDIDO · NÃO FISCAL</div>
</main>
<?php if ($autoPrint): ?>
<script>
window.addEventListener('load', () => {
    requestAnimationFrame(() => requestAnimationFrame(() => {
        if (document.readyState === 'complete') window.print();
    }));
});
</script>
<?php endif; ?>
</body>
</html>
