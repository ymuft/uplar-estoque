<?php
/** Assistente de perfil: não altera estoque, pedidos nem dados de pagamento. */
session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/subscription.php';
require_once __DIR__ . '/includes/business_profile_runtime.php';
send_security_headers();
if (!is_fully_authenticated()) { header('Location: login.php'); exit; }
$userId = (int)$_SESSION['user_id'];
$pdo = get_pdo();
check_and_block($userId, $pdo);
get_csrf_token();
$profile = uplar_business_profile_read($pdo, $userId);
$profiles = uplar_business_profiles_catalog();
$profileData = json_encode($profile, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$catalogData = json_encode($profiles, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Preparar meu negócio — Uplar</title>
<link rel="stylesheet" href="assets/business-setup.css">
</head>
<body>
<header class="bs-top"><a class="bs-brand" href="estoque.php">Uplar<span> Estoque</span></a><a href="estoque.php" class="bs-exit">Voltar ao painel</a></header>
<main class="bs-wrap">
    <div class="bs-progress" aria-label="Progresso da configuração">
        <span class="active" data-progress="1">1. Seu negócio</span>
        <span data-progress="2">2. Configuração</span>
        <span data-progress="3">3. Pronto</span>
    </div>
    <?php if (!$profile['ready']): ?>
        <section class="bs-panel"><h1>Configuração em preparação</h1><p>Este recurso será liberado após a atualização autorizada do banco de dados. Sua loja e seus produtos continuam funcionando normalmente.</p><a href="estoque.php" class="bs-primary">Voltar ao painel</a></section>
    <?php else: ?>
    <section class="bs-panel" id="bs-step-1">
        <p class="bs-eyebrow">Seu primeiro passo</p>
        <h1>Vamos preparar a Uplar para o seu negócio.</h1>
        <p class="bs-lead">Escolha o que melhor representa sua operação. O cadastro e a vitrine poderão ser ajustados depois.</p>
        <fieldset class="bs-choices" id="bs-choices"><legend>Tipo de negócio</legend>
            <?php foreach ($profiles as $key=>$item): ?>
                <label class="bs-choice"><input type="radio" name="segmento" value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"><span><?= htmlspecialchars($item['nome'], ENT_QUOTES, 'UTF-8') ?></span><span class="bs-choice-check" aria-hidden="true">✓</span></label>
            <?php endforeach; ?>
        </fieldset>
        <div class="bs-actions"><button type="button" class="bs-text" id="bs-skip">Configurar depois</button><button type="button" class="bs-primary" id="bs-next" disabled>Continuar</button></div>
    </section>

    <section class="bs-panel" id="bs-step-2" hidden>
        <p class="bs-eyebrow">Configuração inicial</p>
        <h1 id="bs-profile-heading">Seu negócio na Uplar</h1>
        <p class="bs-lead">Estas são sugestões, não limitações. Nenhum produto será criado ou excluído.</p>
        <div class="bs-block"><h2>Experiência da vitrine</h2><p id="bs-mode-label"></p>
            <label class="bs-check"><input type="checkbox" id="bs-apply-mode" checked><span>Preparar esse modo para minha vitrine</span></label>
            <p class="bs-help">Se a vitrine ainda não foi configurada, a escolha será aplicada quando você salvar as configurações dela. Uma vitrine já publicada só muda se você confirmar.</p>
        </div>
        <div class="bs-block"><h2>Categorias sugeridas</h2><p class="bs-help">Adicione, altere ou remova sugestões. Elas serão mantidas como pendentes até a integração definitiva das categorias com o cadastro.</p>
            <div id="bs-category-list"></div>
            <button type="button" class="bs-subtle" id="bs-add-category">+ Adicionar categoria</button>
        </div>
        <div class="bs-block" id="bs-service-block" hidden><h2>Atendimento</h2>
            <p class="bs-help">Informe o que pretende oferecer. Esta escolha não abre a loja nem ativa pagamentos.</p>
            <label class="bs-check"><input type="checkbox" id="bs-delivery"><span>Entrega</span></label>
            <label class="bs-check"><input type="checkbox" id="bs-pickup"><span>Retirada</span></label>
        </div>
        <p id="bs-error" class="bs-error" role="alert" hidden></p>
        <div class="bs-actions"><button type="button" class="bs-secondary" id="bs-back">Voltar</button><button type="button" class="bs-secondary" id="bs-save-later">Salvar e continuar depois</button><button type="button" class="bs-primary" id="bs-confirm">Preparar ambiente</button></div>
    </section>

    <section class="bs-panel" id="bs-step-3" hidden>
        <p class="bs-eyebrow">Tudo certo</p>
        <h1>Seu ambiente está preparado.</h1>
        <p class="bs-lead" id="bs-done-subtitle">Agora você pode começar a organizar seus produtos.</p>
        <div id="bs-done-tasks" class="bs-next-steps"></div>
        <p class="bs-help">As categorias sugeridas ainda não foram publicadas automaticamente. Você pode organizar sua vitrine antes de disponibilizá-la para clientes.</p>
        <div class="bs-actions"><a class="bs-secondary" href="estoque.php">Ir para o painel</a><a class="bs-primary" href="produtos.php">Cadastrar primeiro produto</a></div>
    </section>
    <?php endif; ?>
</main>
<script>window.UPLAR_BUSINESS_SETUP = {csrf: <?= json_encode(get_csrf_token(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>, profile: <?= $profileData ?: '{}' ?>, catalog: <?= $catalogData ?: '{}' ?>};</script>
<script defer src="assets/business-setup.js"></script>
</body>
</html>
