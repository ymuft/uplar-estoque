<?php
session_start();
require 'db.php';
require 'helpers.php';
if (!isset($_SESSION['user_id'], $_SESSION['username'])) { header('Location: login.php'); exit; }
get_csrf_token();
$userId = (int)$_SESSION['user_id'];
$productId = (int)($_GET['produto_id'] ?? 0);
$pdo = get_pdo();
$check = $pdo->prepare('SELECT id, nome, preco FROM produtos WHERE id = ? AND user_id = ?');
$check->execute([$productId, $userId]);
$product = $check->fetch(PDO::FETCH_ASSOC);
if (!$product) { header('Location: produtos.php'); exit; }
header('X-Content-Type-Options: nosniff');
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Adicionais do produto — Uplar</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app-theme.css?v=20260819.1">
<script src="assets/app-theme.js?v=20260819.1"></script>
<link rel="stylesheet" href="assets/product-complements.css?v=20261009.5">
<style>
:root{--p:#3b6ff5;--ink:#102033;--muted:#66758a;--bg:#f4f6fb;--card:#fff;--border:#e3e9f2;--err:#dc3b49}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px Inter,Arial,sans-serif}.page{max-width:720px;margin:0 auto;padding:28px 20px 110px}.back{display:inline-block;margin-bottom:20px;color:var(--muted);font-size:13px;text-decoration:none}.back:hover{color:var(--p)}h1{margin:0;font-size:23px;letter-spacing:-.4px}.product-name{margin:6px 0 24px;color:var(--muted);overflow-wrap:anywhere}.editor-wrap{padding:22px;background:var(--card);border:1px solid var(--border);border-radius:18px}.save-bar{position:fixed;bottom:0;left:0;right:0;border-top:1px solid var(--border);background:var(--card);padding:14px 20px calc(14px + env(safe-area-inset-bottom))}.save-inner{max-width:680px;margin:auto;display:flex;gap:12px;align-items:center;justify-content:space-between}.save-hint{color:var(--muted);font-size:12px}.save-bar button{min-height:44px;padding:11px 18px;border:0;border-radius:10px;background:var(--p);color:#fff;font:600 13px Inter;cursor:pointer}.save-bar button:disabled{opacity:.5;cursor:wait}.feedback{font-size:13px;margin-top:12px;color:var(--muted)}.feedback.error{color:var(--err)}@media(max-width:520px){.page{padding:20px 14px 110px}.editor-wrap{padding:14px}h1{font-size:20px}.save-hint{max-width:145px;font-size:11px}}
</style>
</head>
<body>
<main class="page">
<a class="back" href="produtos.php">← Voltar aos produtos</a>
<h1>Opcionais do produto</h1>
<p class="product-name"><?= htmlspecialchars($product['nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
<section class="editor-wrap"><div id="editor" class="ce"></div><div id="feedback" class="feedback" role="status" aria-live="polite"></div></section>
</main>
<footer class="save-bar"><div class="save-inner"><span class="save-hint">Revise os grupos e salve para atualizar a vitrine.</span><button id="save" type="button" disabled>Salvar opcionais</button></div></footer>
<script src="assets/product-complements.js?v=20261009.5"></script>
<script>
const editor=new ProductComplementsEditor(document.getElementById('editor'),<?= json_encode(get_csrf_token()) ?>,<?= (int)$productId ?>);
editor.root.dataset.productName=<?= json_encode($product['nome'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
editor.root.dataset.basePrice=<?= json_encode($product['preco']) ?>;
const save=document.getElementById('save'),feedback=document.getElementById('feedback');
editor.load().then(()=>save.disabled=!editor.ready);
editor.root.addEventListener('complementsready',()=>save.disabled=!editor.ready||editor.saving);
save.addEventListener('click',async()=>{
    save.disabled=true;save.textContent='Salvando…';feedback.textContent='';feedback.classList.remove('error');
    try{await editor.save(<?= (int)$productId ?>);feedback.textContent='Opcionais salvos. As escolhas já estão disponíveis na vitrine.';}
    catch(error){feedback.textContent=error.message;feedback.classList.add('error');}
    finally{save.disabled=!editor.ready;save.textContent='Salvar opcionais';}
});
window.addEventListener('beforeunload',event=>{if(editor.hasUnsavedChanges()){event.preventDefault();event.returnValue='';}});
</script>
</body></html>
