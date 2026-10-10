<?php
$sidebarPage = basename($_SERVER['PHP_SELF'] ?? '');
$sidebarUser = isset($username) ? (string)$username : (string)($_SESSION['username'] ?? 'Usuário');
$sidebarAdmin = isset($is_admin) ? (bool)$is_admin : (($user['role'] ?? $udata['role'] ?? '') === 'admin');
$sidebarSub = (!$sidebarAdmin && isset($sub) && is_array($sub)) ? $sub : null;
$sidebarPending = isset($pend_count) ? (int)$pend_count : 0;
$sidebarAvatar = null;
$sidebarUserId = (int)($_SESSION['user_id'] ?? 0);
$sidebarChangelogUnread = 0;
$sidebarWhatsapp = isset($whatsapp_number) ? trim((string)$whatsapp_number) : '';
$sidebarCreatedAt = null;
$sidebarPro = false;

if ($sidebarUserId > 0 && isset($pdo) && $pdo instanceof PDO) {
    try {
        $sidebarAccountStmt = $pdo->prepare('SELECT username, role, whatsapp_number, created_at FROM users WHERE id = ? LIMIT 1');
        $sidebarAccountStmt->execute([$sidebarUserId]);
        $sidebarAccount = $sidebarAccountStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        if (!isset($username) && !empty($sidebarAccount['username'])) $sidebarUser = (string)$sidebarAccount['username'];
        if (!$sidebarAdmin && ($sidebarAccount['role'] ?? '') === 'admin') $sidebarAdmin = true;
        if ($sidebarWhatsapp === '' && !empty($sidebarAccount['whatsapp_number'])) $sidebarWhatsapp = trim((string)$sidebarAccount['whatsapp_number']);
        $sidebarCreatedAt = $sidebarAccount['created_at'] ?? null;
    } catch (Throwable $e) {
        $sidebarAccount = [];
    }
    if (!$sidebarAdmin && $sidebarSub === null) {
        try {
            if (!function_exists('get_subscription')) {
                require_once dirname(__DIR__) . '/subscription.php';
            }
            $sidebarSub = get_subscription($sidebarUserId, $pdo);
        } catch (Throwable $e) {
            $sidebarSub = null;
        }
    }
    try {
        $sidebarAvatarStmt = $pdo->prepare('SELECT logo_path FROM vitrine_config WHERE user_id = ? LIMIT 1');
        $sidebarAvatarStmt->execute([$sidebarUserId]);
        $sidebarAvatarPath = trim((string)$sidebarAvatarStmt->fetchColumn());
        $sidebarAvatarFile = dirname(__DIR__) . '/' . $sidebarAvatarPath;
        if (preg_match('#^(?:imagens/vitrine|logos)/[a-zA-Z0-9._-]+\.(?:jpe?g|png|webp)$#i', $sidebarAvatarPath)
            && is_file($sidebarAvatarFile)) {
            $sidebarAvatar = $sidebarAvatarPath;
        }
    } catch (Throwable $e) {
        $sidebarAvatar = null;
    }
    try {
        $sidebarChangelogStmt = $pdo->prepare("SELECT COUNT(*) FROM platform_changelogs c
            LEFT JOIN platform_changelog_views v ON v.changelog_id = c.id AND v.user_id = ?
            WHERE c.status = 'published' AND c.published_at <= NOW() AND v.changelog_id IS NULL");
        $sidebarChangelogStmt->execute([$sidebarUserId]);
        $sidebarChangelogUnread = (int)$sidebarChangelogStmt->fetchColumn();
    } catch (Throwable $e) {
        $sidebarChangelogUnread = 0;
    }
}
$sidebarPro = $sidebarAdmin || (
    is_array($sidebarSub)
    && !empty($sidebarSub['ativa'])
    && function_exists('subscription_category')
    && subscription_category($sidebarSub) === 'pro'
);

function sidebar_link(string $href, string $icon, string $label, string $current, string $extra = ''): void {
    $active = $current === $href;
    $class = 'sb-link' . ($active ? ' on' : '');
    $aria = $active ? ' aria-current="page"' : '';
    echo '<a href="' . htmlspecialchars($href) . '" class="' . $class . '"' . $aria . '>';
    echo '<span class="material-symbols-outlined" aria-hidden="true">' . htmlspecialchars($icon) . '</span>';
    echo '<span class="sb-link-label">' . htmlspecialchars($label) . '</span>' . $extra . '</a>';
}
?>
<link rel="stylesheet" href="assets/vendor/driver.css?v=20260724">
<link rel="stylesheet" href="assets/product-tour.css?v=20260724">
<style>
.sb-av{overflow:hidden}.sb-av-img{display:block;width:100%;height:100%;object-fit:cover;object-position:center}
.sb-changelog-badge{min-width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;margin-left:auto;padding:0 5px;border-radius:999px;color:#fff;background:#2563eb;font-size:10px;font-weight:800}.sb-changelog-badge[hidden]{display:none}
.sb-sub-alert{display:block;margin-top:2px;color:#b45309;font-size:.64rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.profile-modal{display:none;position:fixed;inset:0;z-index:1200;background:rgba(15,28,46,.55);align-items:center;justify-content:center;padding:1rem;backdrop-filter:blur(4px)}
.profile-modal.open{display:flex}.profile-box{width:min(100%,430px);background:var(--card,#fff);border:1px solid var(--border,#e4eaf2);border-radius:16px;box-shadow:0 24px 64px rgba(0,0,0,.2);overflow:hidden;animation:profileIn .22s ease-out both}.profile-head{display:flex;align-items:center;gap:.8rem;padding:1.1rem 1.2rem;background:var(--bg,#f4f6fb);border-bottom:1px solid var(--border,#e4eaf2)}.profile-head .sb-av{width:44px;height:44px;flex-shrink:0}.profile-head-copy{min-width:0;flex:1}.profile-head-name{font-size:1rem;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.profile-head-role{font-size:.72rem;color:var(--muted,#5a6a7a);margin-top:.1rem}.profile-close{width:30px;height:30px;border:0;border-radius:8px;background:transparent;color:var(--muted,#5a6a7a);display:flex;align-items:center;justify-content:center}.profile-close:hover{background:var(--border,#e4eaf2)}.profile-body{padding:1rem 1.2rem}.profile-status{display:flex;align-items:center;gap:.65rem;padding:.75rem .8rem;border-radius:10px;background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.22);margin-bottom:.8rem}.profile-status.warn{background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.3);color:#92400e}.profile-status.err{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.25);color:#b91c1c}.profile-status .material-symbols-outlined{font-size:1.15rem}.profile-status-title{font-size:.8rem;font-weight:800}.profile-status-sub{font-size:.7rem;color:var(--muted,#5a6a7a);margin-top:.1rem}.profile-grid{display:grid;grid-template-columns:1fr 1fr;gap:.55rem}.profile-item{padding:.65rem .75rem;border:1px solid var(--border,#e4eaf2);border-radius:9px;background:var(--card,#fff)}.profile-label{font-size:.63rem;color:var(--faint,#94a3b8);font-weight:700;text-transform:uppercase;letter-spacing:.04em}.profile-value{font-size:.78rem;font-weight:700;margin-top:.15rem;overflow-wrap:anywhere}.profile-actions{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem}.profile-action{min-height:40px;flex:1 1 130px;border:1px solid transparent;display:inline-flex;align-items:center;justify-content:center;gap:.3rem;padding:.62rem .7rem;border-radius:9px;text-decoration:none;font:inherit;font-size:.74rem;font-weight:700;transition:background .18s,border-color .18s,color .18s,transform .18s}.profile-action:hover{transform:translateY(-1px)}.profile-action.primary{background:var(--p,#3b6ff5);border-color:var(--p,#3b6ff5);color:#fff}.profile-action.secondary{border-color:var(--border,#e4eaf2);color:var(--muted,#5a6a7a);background:var(--card,#fff)}.profile-action.secondary:hover{border-color:rgba(59,111,245,.4);background:var(--p-dim,rgba(59,111,245,.08));color:var(--p,#3b6ff5)}.profile-action .material-symbols-outlined{font-size:.95rem}@keyframes profileIn{from{opacity:0;transform:translateY(8px) scale(.98)}to{opacity:1;transform:none}}
@media(max-width:480px){.profile-grid{grid-template-columns:1fr}.profile-actions{flex-direction:column}}
</style>
<aside class="sb" id="sb" aria-label="Navegação principal">
    <div class="sb-head">
        <span class="sb-logo-icon material-symbols-outlined" aria-hidden="true">inventory_2</span>
        <span class="sb-logo-txt">Uplar</span>
        <button class="sb-theme" id="themeToggle" type="button" aria-label="Ativar modo escuro" title="Alternar tema">
            <span class="material-symbols-outlined" aria-hidden="true">dark_mode</span>
        </button>
    </div>
    <div class="sb-user" id="trigCfg" role="button" tabindex="0" aria-label="Abrir configurações da conta">
        <span class="sb-av">
            <?php if ($sidebarAvatar): ?>
                <img class="sb-av-img" src="<?= htmlspecialchars($sidebarAvatar) ?>" alt="Foto de perfil de <?= htmlspecialchars($sidebarUser) ?>">
            <?php else: ?>
                <?= htmlspecialchars(mb_strtoupper(mb_substr($sidebarUser, 0, 2))) ?>
            <?php endif; ?>
        </span>
        <span class="sb-user-copy">
            <span class="sb-uname"><?= htmlspecialchars($sidebarUser) ?></span>
            <span class="sb-urole"><?= $sidebarAdmin ? 'Administrador' : 'Usuário' ?></span>
            <?php if ($sidebarSub && !empty($sidebarSub['expirando'])): ?>
                <span class="sb-sub-alert"> Assinatura expira em <?= (int)$sidebarSub['dias_restantes'] ?> dia<?= (int)$sidebarSub['dias_restantes'] === 1 ? '' : 's' ?></span>
            <?php endif; ?>
        </span>
        <span class="material-symbols-outlined sb-user-arrow" aria-hidden="true">chevron_right</span>
    </div>
    <nav class="sb-nav">
        <div class="sb-label sb-lbl">Principal</div>
        <?php sidebar_link('estoque.php', 'dashboard', 'Dashboard', $sidebarPage); ?>
        <?php
        $changelogBadge = '<span class="sb-changelog-badge" id="changelogUnreadBadge" aria-label="' . $sidebarChangelogUnread . ' novidades não lidas"' . ($sidebarChangelogUnread < 1 ? ' hidden' : '') . '>' . min(99, $sidebarChangelogUnread) . ($sidebarChangelogUnread > 99 ? '+' : '') . '</span>';
        sidebar_link('novidades.php', 'campaign', 'Novidades', $sidebarPage, $changelogBadge);
        ?>
        <?php sidebar_link('relatorio.php', 'bar_chart', 'Relatórios', $sidebarPage); ?>
        <?php sidebar_link('produtos.php', 'inventory', 'Produtos', $sidebarPage); ?>
        <?php sidebar_link('vendedores.php', 'group', 'Vendedores', $sidebarPage); ?>
        <?php sidebar_link('cliente.php', 'handshake', 'Clientes', $sidebarPage); ?>
        <?php sidebar_link('financeiro.php', 'account_balance_wallet', 'Financeiro', $sidebarPage); ?>
        <?php if ($sidebarPage === 'financeiro.php'): ?>
        <div class="fin-subnav" aria-label="Seções financeiras">
            <button type="button" class="fin-sub on" data-view="dashboard"><span class="material-symbols-outlined">dashboard</span>Dashboard</button>
            <button type="button" class="fin-sub" data-view="fluxo"><span class="material-symbols-outlined">swap_vert</span>Fluxo de Caixa</button>
            <button type="button" class="fin-sub" data-view="contas"><span class="material-symbols-outlined">account_balance</span>Contas &amp; Despesas</button>
            <button type="button" class="fin-sub" data-view="receitas"><span class="material-symbols-outlined">trending_up</span>Receitas</button>
            <button type="button" class="fin-sub" data-view="comissoes"><span class="material-symbols-outlined">handshake</span>Comissões</button>
            <button type="button" class="fin-sub" data-view="dre"><span class="material-symbols-outlined">analytics</span>DRE</button>
            <button type="button" class="fin-sub" data-view="relatorios"><span class="material-symbols-outlined">leaderboard</span>Relatórios</button>
        </div>
        <?php endif; ?>
        <?php if ($sidebarAdmin): ?>
            <?php sidebar_link('admin_usuarios.php', 'manage_accounts', 'Usuários', $sidebarPage); ?>
            <?php sidebar_link('admin_changelog.php', 'new_releases', 'Changelog', $sidebarPage); ?>
            <?php sidebar_link('tracking_admin.php', 'monitoring', 'Tracking', $sidebarPage); ?>
        <?php endif; ?>

        <div class="sb-label sb-lbl">Loja online</div>
        <?php sidebar_link('vitrine_config.php', 'storefront', 'Minha Vitrine', $sidebarPage); ?>
        <?php sidebar_link('vitrine_metricas.php', 'query_stats', 'Métricas da Vitrine', $sidebarPage); ?>
        <?php
        $pendingBadge = $sidebarPending > 0
            ? '<span class="sb-badge" id="pendBadge" aria-label="' . $sidebarPending . ' pedidos pendentes">' . $sidebarPending . '</span>'
            : '';
        sidebar_link('pedidos.php', 'receipt_long', 'Pedidos', $sidebarPage, $pendingBadge);
        ?>

        <?php if ($sidebarPro): ?>
        <div class="sb-label sb-lbl">PRO</div>
        <?php sidebar_link('site_pro.php', 'web', 'Meu Site PRO', $sidebarPage); ?>
        <?php sidebar_link('site_pro_metricas.php', 'monitoring', 'Métricas do Site', $sidebarPage); ?>
        <?php endif; ?>

        <div class="sb-label sb-lbl">Conta</div>
        <?php sidebar_link('config.php', 'settings', 'Configurações', $sidebarPage); ?>
        <form action="logout.php" method="POST" id="lf" hidden><?= csrf_input() ?></form>
        <button type="button" class="sb-link sb-logout" id="logoutButton">
            <span class="material-symbols-outlined" aria-hidden="true">logout</span>
            <span class="sb-link-label">Sair</span>
        </button>
    </nav>
</aside>
<div class="profile-modal" id="sidebarProfileModal" role="dialog" aria-modal="true" aria-labelledby="sidebarProfileTitle">
    <div class="profile-box">
        <div class="profile-head">
            <span class="sb-av">
                <?php if ($sidebarAvatar): ?><img class="sb-av-img" src="<?= htmlspecialchars($sidebarAvatar) ?>" alt="">
                <?php else: ?><?= htmlspecialchars(mb_strtoupper(mb_substr($sidebarUser, 0, 2))) ?><?php endif; ?>
            </span>
            <div class="profile-head-copy"><div class="profile-head-name" id="sidebarProfileTitle"><?= htmlspecialchars($sidebarUser) ?></div><div class="profile-head-role"><?= $sidebarAdmin ? 'Administrador' : 'Usuário' ?></div></div>
            <button class="profile-close" id="sidebarProfileClose" type="button" aria-label="Fechar"><span class="material-symbols-outlined">close</span></button>
        </div>
        <div class="profile-body">
            <?php
            $profileStatus = $sidebarAdmin ? 'admin' : (string)($sidebarSub['status'] ?? 'sem_inscricao');
            $profileDays = (int)($sidebarSub['dias_restantes'] ?? 0);
            $profileIcon = $profileStatus === 'admin' ? 'shield' : ($profileStatus === 'ativa' ? ($sidebarSub['expirando'] ? 'warning' : 'check_circle') : 'event_busy');
            $profileTitle = $profileStatus === 'admin' ? 'Acesso administrativo' : ($profileStatus === 'ativa' ? ($profileDays === 0 ? 'Assinatura vence hoje' : 'Assinatura ativa') : 'Assinatura inativa');
            $profileSub = $profileStatus === 'admin' ? 'Acesso sem expiração' : ($profileStatus === 'ativa' ? ($profileDays === 0 ? 'Renove para manter o acesso' : ($profileDays . ' dia' . ($profileDays === 1 ? '' : 's') . ' restantes')) : 'Renove para continuar usando o sistema');
            ?>
            <div class="profile-status <?= $profileStatus === 'admin' ? '' : ($profileStatus === 'ativa' ? (!empty($sidebarSub['expirando']) ? 'warn' : '') : 'err') ?>">
                <span class="material-symbols-outlined" aria-hidden="true"><?= $profileIcon ?></span><div><div class="profile-status-title"><?= htmlspecialchars($profileTitle) ?></div><div class="profile-status-sub"><?= htmlspecialchars($profileSub) ?></div></div>
            </div>
            <div class="profile-grid">
                <div class="profile-item"><div class="profile-label">Usuário</div><div class="profile-value"><?= htmlspecialchars($sidebarUser) ?></div></div>
                <div class="profile-item"><div class="profile-label">Perfil</div><div class="profile-value"><?= $sidebarAdmin ? 'Administrador' : 'Usuário' ?></div></div>
                <div class="profile-item"><div class="profile-label">Validade</div><div class="profile-value"><?= $sidebarAdmin ? 'Sem expiração' : htmlspecialchars(fmt_date_br($sidebarSub['data_fim'] ?? null)) ?></div></div>
                <div class="profile-item"><div class="profile-label">WhatsApp</div><div class="profile-value"><?= htmlspecialchars($sidebarWhatsapp !== '' ? $sidebarWhatsapp : 'Não informado') ?></div></div>
                <?php if ($sidebarCreatedAt): ?><div class="profile-item"><div class="profile-label">Conta criada</div><div class="profile-value"><?= htmlspecialchars(date('d/m/Y', strtotime((string)$sidebarCreatedAt))) ?></div></div><?php endif; ?>
            </div>
            <div class="profile-actions"><a class="profile-action primary" href="config.php#assinatura"><span class="material-symbols-outlined">card_membership</span>Ver assinatura</a><a class="profile-action secondary" href="config.php"><span class="material-symbols-outlined">settings</span>Configurações</a><button class="profile-action secondary" id="sidebarTourButton" type="button"><span class="material-symbols-outlined">explore</span>Tour rápido</button></div>
        </div>
    </div>
</div>
<script>
window.UPLAR_TRACKING_CONFIG = {
    enabled: true,
    csrfToken: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>,
    userId: <?= isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 'null' ?>,
    pageName: <?= json_encode($sidebarPage) ?>,
    pageEvent: <?= json_encode(match ($sidebarPage) {
        'admin_usuarios.php' => 'view_admin_users',
        'admin_changelog.php' => 'view_admin_changelog',
        'novidades.php' => 'view_changelog',
        'tracking_admin.php' => 'view_tracking_dashboard',
        'vitrine_metricas.php' => 'view_storefront_metrics',
        'site_pro_metricas.php' => 'view_site_pro_metrics',
        default => 'page_view',
    }) ?>,
    endpoints: {
        session: 'track/session.php',
        event: 'track/event.php',
        error: 'track/error.php',
        log: 'track/log.php'
    }
};
window.UPLAR_TOUR_CONFIG = {
    csrfToken: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>,
    autoStart: <?= ($sidebarPage === 'estoque.php' && isset($onboarding) && is_array($onboarding) && !empty($onboarding['eligible']) && empty($onboarding['tour_seen'])) ? 'true' : 'false' ?>
};
</script>
<script>
(function(){
    const trigger=document.getElementById('trigCfg'), modal=document.getElementById('sidebarProfileModal'), close=document.getElementById('sidebarProfileClose');
    if(!trigger||!modal)return;
    window.openSidebarProfile=function(){modal.classList.add('open');document.body.style.overflow='hidden';close?.focus()};
    window.closeSidebarProfile=function(){modal.classList.remove('open');document.body.style.overflow=''};
    trigger.setAttribute('aria-label','Abrir informações do usuário');
    trigger.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();window.openSidebarProfile()}});
    close?.addEventListener('click',window.closeSidebarProfile);
    modal.addEventListener('click',e=>{if(e.target===modal)window.closeSidebarProfile()});
    document.addEventListener('keydown',e=>{if(e.key==='Escape'&&modal.classList.contains('open'))window.closeSidebarProfile()});
})();
</script>
<script src="assets/vendor/driver.js?v=20260724"></script>
<script src="assets/product-tour.js?v=20260724"></script>
<script src="assets/tracker.js?v=20260715"></script>
