<div class="wn-overlay" id="whatsNewOverlay" aria-hidden="true" hidden>
    <section class="wn-dialog" id="whatsNewDialog" role="dialog" aria-modal="true" aria-labelledby="whatsNewTitle" tabindex="-1">
        <header class="wn-head">
            <button class="wn-close" id="whatsNewClose" type="button" aria-label="Fechar novidades">
                <span class="material-symbols-outlined" aria-hidden="true">close</span>
            </button>
            <span class="wn-badge" id="whatsNewBadge">ATUALIZAÇÃO</span>
            <h2 class="wn-title" id="whatsNewTitle">Novidades na Uplar</h2>
            <p class="wn-meta" id="whatsNewMeta"></p>
            <p class="wn-intro" id="whatsNewIntro"></p>
        </header>
        <div class="wn-scroll" id="whatsNewContent"></div>
        <footer class="wn-foot">
            <a class="wn-button" href="novidades.php" id="whatsNewAll">Ver todas as atualizações</a>
            <button class="wn-button wn-button--primary" id="whatsNewUnderstand" type="button">Entendi</button>
        </footer>
    </section>
</div>
<script>
window.UPLAR_CHANGELOG = {
    csrfToken: <?= json_encode((string)($_SESSION['csrf_token'] ?? '')) ?>,
    endpoint: 'changelog_api.php'
};
</script>
