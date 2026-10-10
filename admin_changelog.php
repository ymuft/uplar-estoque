<?php
session_start();
require 'db.php';
require 'helpers.php';
require_once __DIR__ . '/includes/tracking_runtime.php';

if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
    header('Location: login.php');
    exit;
}
get_csrf_token();
$user_id = (int)$_SESSION['user_id'];
$username = (string)$_SESSION['username'];
$pdo = get_pdo();
$roleStmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
$roleStmt->execute([$user_id]);
$user = ['role' => (string)$roleStmt->fetchColumn()];
$is_admin = $user['role'] === 'admin';
if (!$is_admin) {
    header('Location: estoque.php');
    exit;
}

$changelogs = $pdo->query("SELECT c.id, c.title, c.version, c.status, c.importance, c.show_modal, c.published_at, c.created_at,
        COUNT(DISTINCT i.id) AS item_count, COUNT(DISTINCT v.user_id) AS view_count
    FROM platform_changelogs c
    LEFT JOIN platform_changelog_items i ON i.changelog_id = c.id
    LEFT JOIN platform_changelog_views v ON v.changelog_id = c.id
    GROUP BY c.id ORDER BY c.created_at DESC LIMIT 40")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Changelog — Administração Uplar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link rel="stylesheet" href="assets/app-theme.css?v=20260723">
    <link rel="stylesheet" href="assets/changelog.css?v=20260728.2">
    <style>
      *{box-sizing:border-box}body{margin:0;font-family:Inter,sans-serif}.changelog-ham{display:none;width:42px;height:42px;place-items:center;border:1px solid var(--wn-border);border-radius:9px;background:var(--wn-card);color:var(--wn-text)}
      .admin-section-title{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:25px 0 10px}.admin-section-title h3{margin:0;font-size:15px}.admin-section-title span{color:var(--wn-muted);font-size:12px}
      @media(max-width:850px){.changelog-ham{display:grid}.changelog-page-head{padding-left:52px}.changelog-ham{position:absolute;margin-left:-52px}}
    </style>
    <script src="assets/app-theme.js?v=20260723"></script>
</head>
<body class="changelog-page">
<div class="app">
    <div class="overlay" id="ov"></div>
    <?php require __DIR__ . '/includes/sidebar.php'; ?>
    <main class="changelog-main">
        <div class="changelog-wrap">
            <header class="changelog-page-head">
                <div>
                    <button class="changelog-ham" id="ham" type="button" aria-label="Abrir menu"><span class="material-symbols-outlined">menu</span></button>
                    <h1>Novidades da plataforma</h1>
                    <p>Cadastre e programe as atualizações exibidas aos usuários.</p>
                </div>
                <a class="wn-button" href="novidades.php"><span class="material-symbols-outlined" aria-hidden="true">visibility</span>Ver central</a>
            </header>

            <div class="admin-feedback" id="adminFeedback" role="status" aria-live="polite"></div>
            <div class="admin-grid">
                <section class="admin-panel">
                    <h2>Nova atualização</h2>
                    <form id="changelogForm" enctype="multipart/form-data" novalidate>
                        <div class="admin-form-grid">
                            <div class="admin-field admin-field--full">
                                <label for="changelogTitle">Título da atualização</label>
                                <input id="changelogTitle" name="title" maxlength="160" required placeholder="Ex.: Novidades na Uplar">
                            </div>
                            <div class="admin-field">
                                <label for="changelogVersion">Versão <span class="admin-help">(opcional)</span></label>
                                <input id="changelogVersion" name="version" maxlength="40" placeholder="2.4.0">
                            </div>
                            <div class="admin-field">
                                <label for="changelogDate">Publicação</label>
                                <input id="changelogDate" name="published_at" type="datetime-local" required value="<?= date('Y-m-d\TH:i') ?>">
                            </div>
                            <div class="admin-field admin-field--full">
                                <label for="changelogIntro">Descrição introdutória</label>
                                <textarea id="changelogIntro" name="intro" maxlength="500" required placeholder="Confira as melhorias que acabaram de chegar à plataforma."></textarea>
                            </div>
                            <div class="admin-field">
                                <label for="changelogStatus">Status</label>
                                <select id="changelogStatus" name="status"><option value="draft">Rascunho</option><option value="published">Publicado</option></select>
                            </div>
                            <div class="admin-field">
                                <label for="changelogImportance">Importância</label>
                                <select id="changelogImportance" name="importance"><option value="normal">Normal</option><option value="important">Importante</option><option value="critical">Crítica</option></select>
                            </div>
                            <label class="admin-check admin-field--full">
                                <input type="checkbox" name="show_modal" value="1" checked>
                                <span>Exibir modal após o login. Atualizações importantes e críticas sempre usam o modal.</span>
                            </label>
                        </div>

                        <div class="admin-section-title"><h3>Alterações desta versão</h3><span>Até 20 itens</span></div>
                        <div class="admin-items" id="adminItems"></div>
                        <button class="wn-button" id="addItem" type="button"><span class="material-symbols-outlined" aria-hidden="true">add</span>Adicionar alteração</button>

                        <div class="admin-actions">
                            <button class="wn-button" id="previewChangelog" type="button"><span class="material-symbols-outlined" aria-hidden="true">preview</span>Pré-visualizar</button>
                            <button class="wn-button wn-button--primary" id="saveChangelog" type="submit"><span class="material-symbols-outlined" aria-hidden="true">publish</span>Salvar atualização</button>
                        </div>
                    </form>
                </section>

                <aside class="admin-panel">
                    <h2>Atualizações recentes</h2>
                    <div class="admin-list" id="adminChangelogList">
                        <?php if (!$changelogs): ?><p class="admin-help">Nenhuma atualização cadastrada.</p><?php endif; ?>
                        <?php foreach ($changelogs as $entry): ?>
                            <article class="admin-list-item" data-admin-id="<?= (int)$entry['id'] ?>">
                                <div class="admin-list-row">
                                    <div>
                                        <h3><?= htmlspecialchars($entry['title']) ?></h3>
                                        <p><?= $entry['version'] ? 'v' . htmlspecialchars($entry['version']) . ' · ' : '' ?><?= date('d/m/Y H:i', strtotime($entry['published_at'])) ?><br><?= (int)$entry['item_count'] ?> alterações · <?= (int)$entry['view_count'] ?> visualizações</p>
                                    </div>
                                    <span class="admin-state<?= $entry['status'] === 'draft' ? ' admin-state--draft' : '' ?>"><?= $entry['status'] === 'draft' ? 'Rascunho' : 'Publicado' ?></span>
                                </div>
                                <div class="changelog-entry-actions">
                                    <button class="wn-button story-create-button" type="button" data-story-id="<?= (int)$entry['id'] ?>"><span class="material-symbols-outlined" aria-hidden="true">photo_camera</span>Criar Story</button>
                                    <?php if ($entry['status'] === 'draft'): ?><button class="admin-icon-button delete-draft" type="button" title="Excluir rascunho" aria-label="Excluir rascunho"><span class="material-symbols-outlined" aria-hidden="true">delete</span></button><?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </aside>
            </div>
        </div>
    </main>
</div>

<div class="wn-overlay" id="adminPreviewOverlay" aria-hidden="true" hidden>
    <section class="wn-dialog" id="adminPreviewDialog" role="dialog" aria-modal="true" aria-labelledby="adminPreviewTitle" tabindex="-1">
        <header class="wn-head">
            <button class="wn-close" id="closeAdminPreview" type="button" aria-label="Fechar pré-visualização"><span class="material-symbols-outlined" aria-hidden="true">close</span></button>
            <span class="wn-badge" id="adminPreviewBadge">NOVIDADE</span>
            <h2 class="wn-title" id="adminPreviewTitle"></h2>
            <p class="wn-meta" id="adminPreviewMeta"></p>
            <p class="wn-intro" id="adminPreviewIntro"></p>
        </header>
        <div class="wn-scroll" id="adminPreviewContent"></div>
        <footer class="wn-foot"><button class="wn-button wn-button--primary" id="adminPreviewDone" type="button">Entendi</button></footer>
    </section>
</div>

<div class="wn-overlay story-overlay" id="storyOverlay" aria-hidden="true" hidden>
    <section class="story-dialog" id="storyDialog" role="dialog" aria-modal="true" aria-labelledby="storyDialogTitle" tabindex="-1">
        <header class="story-dialog-head">
            <div>
                <span class="story-format">STORY · 1080 × 1920</span>
                <h2 id="storyDialogTitle">Imagem para compartilhar</h2>
                <p>Confira o enquadramento antes de baixar.</p>
            </div>
            <button class="wn-close" id="closeStory" type="button" aria-label="Fechar gerador de Story"><span class="material-symbols-outlined" aria-hidden="true">close</span></button>
        </header>
        <div class="story-workspace">
            <aside class="story-editor" aria-label="Editar conteúdo do Story">
                <div class="story-editor-section">
                    <span class="story-editor-label">Aparência</span>
                    <div class="story-theme-options" role="group" aria-label="Tema do Story">
                        <button class="story-theme-button is-active" type="button" data-story-theme="dark" aria-pressed="true"><span class="material-symbols-outlined">dark_mode</span>Escuro</button>
                        <button class="story-theme-button" type="button" data-story-theme="light" aria-pressed="false"><span class="material-symbols-outlined">light_mode</span>Claro</button>
                    </div>
                </div>
                <div class="story-editor-section">
                    <label class="story-editor-label" for="storyTitleEdit">Título</label>
                    <textarea id="storyTitleEdit" maxlength="160" rows="3"></textarea>
                    <label class="story-editor-label" for="storyIntroEdit">Resumo</label>
                    <textarea id="storyIntroEdit" maxlength="500" rows="4"></textarea>
                </div>
                <div class="story-editor-section">
                    <div class="story-editor-row"><span class="story-editor-label">Adições exibidas</span><span id="storySelectedCount">0 de 3</span></div>
                    <p class="story-editor-help" id="storyEditorHelp">Escolha até três adições. Título e detalhes podem ser ajustados somente para esta imagem.</p>
                    <div class="story-items-editor" id="storyItemsEditor"></div>
                </div>
            </aside>
            <div class="story-preview">
                <div class="story-loading" id="storyLoading"><span class="material-symbols-outlined">progress_activity</span>Preparando imagem…</div>
                <canvas id="storyCanvas" width="1080" height="1920" aria-label="Pré-visualização do Story"></canvas>
            </div>
        </div>
        <footer class="story-dialog-foot">
            <span>PNG em alta resolução, pronto para Stories.</span>
            <div class="story-dialog-actions">
                <button class="wn-button" id="shareStory" type="button" hidden><span class="material-symbols-outlined" aria-hidden="true">ios_share</span>Compartilhar</button>
                <button class="wn-button wn-button--primary" id="downloadStory" type="button" disabled><span class="material-symbols-outlined" aria-hidden="true">download</span>Baixar imagem</button>
            </div>
        </footer>
    </section>
</div>

<template id="adminItemTemplate">
    <article class="admin-item">
        <div class="admin-item-head"><strong>Alteração <span class="item-number"></span></strong><button class="admin-icon-button remove-item" type="button" aria-label="Remover alteração"><span class="material-symbols-outlined" aria-hidden="true">delete</span></button></div>
        <div class="admin-form-grid">
            <div class="admin-field"><label>Categoria</label><select data-field="category"><option value="new">Novo</option><option value="improvement" selected>Melhoria</option><option value="fix">Correção</option><option value="security">Segurança</option><option value="performance">Desempenho</option></select></div>
            <div class="admin-field"><label>Imagem <span class="admin-help">(opcional)</span></label><input data-field="image" type="file" accept="image/jpeg,image/png,image/webp"><span class="admin-help">JPG, PNG ou WebP, até 4 MB.</span></div>
            <div class="admin-field admin-field--full"><label>Título</label><input data-field="title" maxlength="160" required placeholder="Ex.: Relatórios mais completos"></div>
            <div class="admin-field admin-field--full"><label>Descrição</label><textarea data-field="description" maxlength="2000" required placeholder="Descreva o benefício para o usuário."></textarea></div>
            <div class="admin-field"><label>Link ou rota <span class="admin-help">(opcional)</span></label><input data-field="link_url" maxlength="500" placeholder="relatorio.php"></div>
            <div class="admin-field"><label>Texto do botão <span class="admin-help">(opcional)</span></label><input data-field="button_text" maxlength="80" placeholder="Conhecer funcionalidade"></div>
            <label class="admin-check admin-field--full"><input data-field="is_featured" type="checkbox"><span>Usar como destaque principal. Somente um item pode ser destacado.</span></label>
        </div>
    </article>
</template>
<script>window.UPLAR_CHANGELOG_ADMIN={csrfToken:<?= json_encode((string)$_SESSION['csrf_token']) ?>,endpoint:'changelog_api.php'};</script>
<script src="assets/changelog-admin.js?v=20260728.2"></script>
</body>
</html>
