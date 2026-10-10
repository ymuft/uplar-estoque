<?php
session_start();
require 'helpers.php';
if (!isset($_SESSION['user_id'], $_SESSION['username'])) { header('Location: login.php'); exit; }
get_csrf_token();
header('X-Content-Type-Options: nosniff');
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Grupos de complementos — Uplar</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/app-theme.css?v=20260819.1">
<script src="assets/app-theme.js?v=20260819.1"></script>
<link rel="stylesheet" href="assets/product-complements.css?v=20261009.5">
<style>
:root{--p:#3b6ff5;--ink:#102033;--muted:#66758a;--bg:#f4f6fb;--card:#fff;--border:#e3e9f2;--err:#dc3b49}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px Inter,Arial,sans-serif}.page{max-width:900px;margin:0 auto;padding:28px 20px 56px}.back{display:inline-block;margin-bottom:20px;color:var(--muted);font-size:13px;text-decoration:none}.back:hover{color:var(--p)}h1{margin:0;font-size:23px;letter-spacing:-.4px}.intro{margin:6px 0 24px;color:var(--muted)}.editor-wrap{padding:22px;background:var(--card);border:1px solid var(--border);border-radius:18px}@media(max-width:520px){.page{padding:20px 14px 40px}.editor-wrap{padding:14px}h1{font-size:20px}}
</style>
</head>
<body>
<main class="page">
<a class="back" href="produtos.php">← Voltar aos produtos</a>
<h1>Grupos de complementos</h1>
<p class="intro">Crie e organize os grupos reutilizáveis da sua loja.</p>
<section class="editor-wrap"><div id="editor" class="ce"></div></section>
</main>
<script src="assets/product-complements.js?v=20261009.5"></script>
<script>
const editor=new ProductComplementsEditor(document.getElementById('editor'),<?= json_encode(get_csrf_token()) ?>,0,{libraryOnly:true});
editor.load();
window.addEventListener('beforeunload',event=>{if(editor.hasUnsavedChanges()){event.preventDefault();event.returnValue='';}});
</script>
</body>
</html>
