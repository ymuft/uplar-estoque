<?php
session_start();
require 'db.php';
require 'helpers.php';
require 'subscription.php';
require_once __DIR__ . '/includes/tracking_runtime.php';

if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

get_csrf_token();
$user_id = (int)$_SESSION['user_id'];
$username = (string)$_SESSION['username'];
$pdo = get_pdo();
$sub = check_and_block($user_id, $pdo);
$roleStmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
$roleStmt->execute([$user_id]);
$user = ['role' => (string)$roleStmt->fetchColumn()];
$is_admin = $user['role'] === 'admin';

$stmt = $pdo->prepare("SELECT c.id, c.title, c.version, c.intro, c.importance, c.published_at,
        CASE WHEN v.changelog_id IS NULL THEN 0 ELSE 1 END AS is_read
    FROM platform_changelogs c
    LEFT JOIN platform_changelog_views v ON v.changelog_id = c.id AND v.user_id = ?
    WHERE c.status = 'published' AND c.published_at <= NOW()
    ORDER BY c.published_at DESC, c.id DESC");
$stmt->execute([$user_id]);
$changelogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$itemsByChangelog = [];
if ($changelogs) {
    $ids = array_column($changelogs, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $itemStmt = $pdo->prepare("SELECT changelog_id, category, title, description, image_path, link_url, button_text, is_featured
        FROM platform_changelog_items WHERE changelog_id IN ($placeholders) ORDER BY sort_order, id");
    $itemStmt->execute($ids);
    foreach ($itemStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $itemsByChangelog[(int)$item['changelog_id']][] = $item;
    }
}
$unreadCount = count(array_filter($changelogs, static fn(array $row): bool => !(bool)$row['is_read']));

function novidades_date(string $date): string {
    $months = [1=>'jan',2=>'fev',3=>'mar',4=>'abr',5=>'mai',6=>'jun',7=>'jul',8=>'ago',9=>'set',10=>'out',11=>'nov',12=>'dez'];
    $time = strtotime($date);
    return $time ? date('d', $time) . ' ' . $months[(int)date('n', $time)] . ' ' . date('Y', $time) : '';
}
function novidades_category(string $category): array {
    return [
        'new' => ['Novo', 'add_circle'],
        'improvement' => ['Melhoria', 'auto_awesome'],
        'fix' => ['Correção', 'build_circle'],
        'security' => ['Segurança', 'verified_user'],
        'performance' => ['Desempenho', 'speed'],
    ][$category] ?? ['Melhoria', 'auto_awesome'];
}
function novidades_link(?string $url): ?string {
    $url = trim((string)$url);
    if ($url === '' || str_starts_with($url, '//') || preg_match('/[\x00-\x1F\x7F]/', $url)) return null;
    if (str_starts_with($url, '/') || preg_match('#^[a-zA-Z0-9][a-zA-Z0-9._/?=&%:@+-]*$#', $url)) return $url;
    return filter_var($url, FILTER_VALIDATE_URL) && str_starts_with(strtolower($url), 'https://') ? $url : null;
}
function novidades_image(?string $path): ?string {
    return preg_match('#^imagens/changelog/[a-f0-9]{32}\.webp$#', (string)$path) ? $path : null;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Novidades — Uplar</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
    <link rel="stylesheet" href="assets/app-theme.css?v=20260723">
    <link rel="stylesheet" href="assets/changelog.css?v=20260714">
    <style>
      *{box-sizing:border-box}body{margin:0;font-family:Inter,sans-serif}.changelog-ham{display:none;width:42px;height:42px;place-items:center;border:1px solid var(--wn-border);border-radius:9px;background:var(--wn-card);color:var(--wn-text)}
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
                    <h1>O que há de novo</h1>
                    <p>Acompanhe as melhorias, correções e novos recursos da Uplar.</p>
                </div>
                <?php if ($unreadCount > 0): ?>
                    <button class="wn-button" id="markAll" type="button">Marcar todas como lidas</button>
                <?php endif; ?>
            </header>

            <?php if (!$changelogs): ?>
                <section class="admin-panel wn-empty">
                    <span class="material-symbols-outlined" aria-hidden="true">campaign</span>
                    Nenhuma atualização foi publicada até o momento.
                </section>
            <?php else: ?>
                <div class="changelog-timeline" id="changelogTimeline">
                    <?php foreach ($changelogs as $changelog):
                        $isRead = (bool)$changelog['is_read'];
                        $items = $itemsByChangelog[(int)$changelog['id']] ?? [];
                    ?>
                    <article class="changelog-entry<?= $isRead ? '' : ' is-unread' ?>" data-changelog-id="<?= (int)$changelog['id'] ?>">
                        <header class="changelog-entry-head">
                            <div>
                                <span class="wn-badge"><?= $changelog['importance'] === 'critical' ? 'IMPORTANTE' : 'ATUALIZAÇÃO' ?></span>
                                <h2 class="changelog-entry-title"><?= htmlspecialchars($changelog['title']) ?></h2>
                                <p class="changelog-entry-meta"><?= $changelog['version'] ? 'Versão ' . htmlspecialchars($changelog['version']) . ' · ' : '' ?><?= htmlspecialchars(novidades_date($changelog['published_at'])) ?></p>
                                <p class="changelog-entry-intro"><?= nl2br(htmlspecialchars($changelog['intro'])) ?></p>
                            </div>
                            <span class="changelog-status<?= $isRead ? '' : ' is-unread' ?>"><span class="material-symbols-outlined" aria-hidden="true"><?= $isRead ? 'done' : 'fiber_manual_record' ?></span><?= $isRead ? 'Lida' : 'Não lida' ?></span>
                        </header>
                        <div class="changelog-entry-items">
                            <?php foreach ($items as $item):
                                [$categoryLabel, $categoryIcon] = novidades_category($item['category']);
                                $media = novidades_image($item['image_path']);
                                $link = novidades_link($item['link_url']);
                            ?>
                            <section class="changelog-entry-item<?= $item['is_featured'] ? ' is-featured' : '' ?>">
                                <?php if ($media): ?><img class="changelog-entry-media" src="<?= htmlspecialchars($media) ?>" alt="" loading="lazy"><?php endif; ?>
                                <span class="wn-category wn-category--<?= htmlspecialchars($item['category']) ?>"><span class="material-symbols-outlined" aria-hidden="true"><?= htmlspecialchars($categoryIcon) ?></span><?= htmlspecialchars($categoryLabel) ?></span>
                                <h3 class="wn-item-title"><?= htmlspecialchars($item['title']) ?></h3>
                                <p class="wn-item-description"><?= htmlspecialchars($item['description']) ?></p>
                                <?php if ($link): ?><a class="wn-link" href="<?= htmlspecialchars($link) ?>"<?= str_starts_with($link, 'https://') ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= htmlspecialchars($item['button_text'] ?: 'Conhecer funcionalidade') ?><span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a><?php endif; ?>
                            </section>
                            <?php endforeach; ?>
                        </div>
                        <?php if (!$isRead): ?><div class="changelog-entry-actions"><button class="wn-button mark-one" type="button">Marcar como lida</button></div><?php endif; ?>
                    </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
<script>
const csrfToken = <?= json_encode((string)$_SESSION['csrf_token']) ?>;
async function markViewed(action, id = '') {
  const data = new FormData(); data.append('action', action); data.append('csrf_token', csrfToken);
  if (id) { data.append('changelog_id', id); data.append('source', 'center'); }
  const response = await fetch('changelog_api.php', {method:'POST', body:data, credentials:'same-origin'});
  if (!response.ok) throw new Error('Não foi possível atualizar o status.');
}
document.querySelectorAll('.mark-one').forEach(button => button.addEventListener('click', async () => {
  const card = button.closest('[data-changelog-id]'); button.disabled = true;
  try { await markViewed('mark_viewed', card.dataset.changelogId); card.classList.remove('is-unread'); card.querySelector('.changelog-status').className='changelog-status'; card.querySelector('.changelog-status').innerHTML='<span class="material-symbols-outlined" aria-hidden="true">done</span>Lida'; button.parentElement.remove(); }
  catch (_) { button.disabled = false; button.textContent = 'Tentar novamente'; }
}));
document.getElementById('markAll')?.addEventListener('click', async event => {
  event.currentTarget.disabled = true;
  try { await markViewed('mark_all_viewed'); location.reload(); }
  catch (_) { event.currentTarget.disabled = false; event.currentTarget.textContent = 'Tentar novamente'; }
});
const sb=document.getElementById('sb'),ov=document.getElementById('ov');
document.getElementById('ham')?.addEventListener('click',()=>{sb?.classList.add('open');ov?.classList.add('show')});
ov?.addEventListener('click',()=>{sb?.classList.remove('open');ov.classList.remove('show')});
document.getElementById('logoutButton')?.addEventListener('click',()=>document.getElementById('lf')?.submit());
</script>
</body>
</html>
