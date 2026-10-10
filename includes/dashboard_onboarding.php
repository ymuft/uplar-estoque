<?php if (!empty($onboarding['visible'])): ?>
<?php
$obCollapsed = !empty($onboarding['collapsed']);
$obCelebration = !empty($onboarding['show_celebration']);
$obStore = $onboarding['store'];
?>
<section class="onboarding-card <?= $obCollapsed ? 'is-collapsed' : '' ?> <?= $obCelebration ? 'is-complete' : '' ?>" id="onboardingCard" data-csrf="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>" aria-labelledby="onboardingTitle">
    <div class="onboarding-main">
        <div class="onboarding-heading">
            <div class="onboarding-heading-copy">
                <span class="onboarding-eyebrow"><?= $obCelebration ? 'Loja pronta para crescer' : 'Primeiros passos' ?></span>
                <h2 id="onboardingTitle"><?= $obCelebration ? 'Sua loja está pronta ' : 'Bem-vindo à Uplar ' ?></h2>
                <p><?= $obCelebration ? 'Você concluiu a configuração inicial. A dashboard agora acompanha sua operação.' : 'Vamos colocar sua loja para funcionar.' ?></p>
            </div>
            <?php if (!$obCelebration): ?>
            <button class="onboarding-collapse" id="onboardingCollapse" type="button" aria-expanded="<?= $obCollapsed ? 'false' : 'true' ?>" title="<?= $obCollapsed ? 'Expandir' : 'Recolher' ?> primeiros passos">
                <span class="material-symbols-outlined"><?= $obCollapsed ? 'expand_more' : 'expand_less' ?></span>
            </button>
            <?php endif; ?>
        </div>

        <div class="onboarding-progress-wrap">
            <div class="onboarding-progress-copy"><strong><?= (int)$onboarding['completed_steps'] ?> de <?= (int)$onboarding['total_steps'] ?> etapas concluídas</strong><span><?= (int)$onboarding['progress'] ?>%</span></div>
            <div class="onboarding-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int)$onboarding['progress'] ?>"><span style="--progress:<?= (int)$onboarding['progress'] ?>%"></span></div>
        </div>

        <?php if (!$obCelebration): ?>
        <div class="onboarding-tour-invite">
            <span class="material-symbols-outlined" aria-hidden="true">explore</span>
            <div><strong><?= !empty($onboarding['tour_seen']) ? 'Rever a plataforma?' : 'Conheça a plataforma' ?></strong><span>Veja rapidamente o que você encontra em cada área.</span></div>
            <button class="onboarding-button secondary" type="button" data-onboarding-tour><?= !empty($onboarding['tour_seen']) ? 'Repetir tour' : 'Ver tour' ?></button>
        </div>
        <?php endif; ?>

        <div class="onboarding-expanded">
            <?php if (!$obCelebration): ?>
            <div class="onboarding-steps">
                <?php foreach ($onboarding['steps'] as $step): ?>
                <div class="onboarding-step <?= $step['done'] ? 'done' : '' ?>" data-step="<?= htmlspecialchars($step['key']) ?>">
                    <span class="onboarding-check material-symbols-outlined" aria-hidden="true"><?= $step['done'] ? 'check' : 'radio_button_unchecked' ?></span>
                    <div class="onboarding-step-copy"><div class="onboarding-step-title"><?= htmlspecialchars($step['title']) ?></div><div class="onboarding-step-description"><?= htmlspecialchars($step['description']) ?></div></div>
                    <?php if (!$step['done'] && $step['action']): ?>
                        <?php if ($step['key'] === 'products'): ?>
                        <button class="onboarding-step-action" type="button" data-onboarding-add-product><?= htmlspecialchars($step['action']) ?></button>
                        <?php elseif ($step['key'] === 'share' && !empty($obStore['slug'])): ?>
                        <button class="onboarding-step-action" type="button" data-onboarding-share data-share-url="<?= htmlspecialchars($obStore['url']) ?>"><?= htmlspecialchars($step['action']) ?></button>
                        <?php else: ?>
                        <a class="onboarding-step-action" href="<?= htmlspecialchars($step['href']) ?>"><?= htmlspecialchars($step['action']) ?></a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="onboarding-store-preview">
                <div class="onboarding-store-visual <?= $obStore['banner'] ? 'has-banner' : '' ?>" <?= $obStore['banner'] ? 'style="background-image:url(\'' . htmlspecialchars($obStore['banner']) . '\')"' : '' ?>>
                    <div class="onboarding-store-logo">
                        <?php if ($obStore['logo']): ?><img src="<?= htmlspecialchars($obStore['logo']) ?>" alt="Logo atual da loja"><?php else: ?><span class="material-symbols-outlined">storefront</span><?php endif; ?>
                    </div>
                    <div><div class="onboarding-store-name"><?= htmlspecialchars($obStore['name']) ?></div><div class="onboarding-store-meta"><?= (int)$onboarding['counts']['products'] ?> produto<?= (int)$onboarding['counts']['products'] === 1 ? '' : 's' ?> no catálogo</div></div>
                </div>
                <div class="onboarding-store-copy"><strong>Sua loja está tomando forma</strong><span><?= (int)$onboarding['counts']['products'] > 0 ? 'Veja como seus produtos aparecem para os clientes.' : 'Adicione seu primeiro produto para começar a preencher a vitrine.' ?></span></div>
                <div class="onboarding-store-actions">
                    <?php if ((int)$onboarding['counts']['products'] === 0): ?><button class="onboarding-button primary" type="button" data-onboarding-add-product><span class="material-symbols-outlined">add</span>Adicionar produto</button><?php endif; ?>
                    <a class="onboarding-button secondary" href="<?= htmlspecialchars($obStore['url']) ?>" <?= $obStore['slug'] ? 'target="_blank" rel="noopener"' : '' ?>><span class="material-symbols-outlined"><?= $obStore['slug'] ? 'visibility' : 'tune' ?></span><?= $obStore['slug'] ? 'Ver minha vitrine' : 'Personalizar loja' ?></a>
                </div>
            </div>
        </div>

        <?php if (!$obCelebration): ?><button class="onboarding-dismiss onboarding-expanded" id="onboardingDismiss" type="button">Ocultar este guia</button><?php endif; ?>
    </div>
</section>
<?php endif; ?>
