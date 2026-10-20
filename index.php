<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Controle estoque, vendas, vendedores e financeiro em um único sistema. Acesse pelo celular ou computador e acompanhe sua operação em tempo real.">
  <meta name="theme-color" content="#ffffff">
  <title>Uplar Estoque | Controle de estoque, vendas e financeiro</title>
  <link rel="icon" href="/favicon.ico" sizes="any">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=JetBrains+Mono:wght@500&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">

  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            ink: "#0F1C2E",
            muted: "#5A6A7A",
            navy: "#0F1C2E",
            "navy-soft": "#172B47",
            brand: "#3B6FF5",
            "brand-dark": "#2A55D9",
            cyan: "#10B981",
            "cyan-soft": "#E8F8F2",
            canvas: "#F4F6FB",
            line: "#E4EAF2"
          },
          fontFamily: {
            sans: ["Inter", "sans-serif"],
            display: ["Inter", "sans-serif"],
            mono: ["JetBrains Mono", "monospace"]
          },
          boxShadow: {
            card: "0 1px 4px rgba(15,28,46,.06), 0 4px 16px rgba(15,28,46,.04)",
            product: "0 22px 60px rgba(15,28,46,.16)",
            button: "0 8px 22px rgba(59,111,245,.22)"
          }
        }
      }
    };
  </script>

  <style>
    :root {
      --navy: #0F1C2E;
      --brand: #3B6FF5;
      --cyan: #10B981;
      --line: #E4EAF2;
      --app-bg: #F4F6FB;
      --app-card: #FFFFFF;
      --app-muted: #5A6A7A;
    }

    html { scroll-behavior: smooth; scroll-padding-top: 88px; }
    body { overflow-x: hidden; }
    a, button { -webkit-tap-highlight-color: transparent; }

    .material-symbols-outlined {
      font-variation-settings: "FILL" 0, "wght" 500, "GRAD" 0, "opsz" 24;
      line-height: 1;
    }

    .hero-grid {
      background-image:
        linear-gradient(rgba(59, 111, 245, .045) 1px, transparent 1px),
        linear-gradient(90deg, rgba(59, 111, 245, .045) 1px, transparent 1px);
      background-size: 34px 34px;
      -webkit-mask-image: linear-gradient(to bottom, black 54%, transparent 100%);
      mask-image: linear-gradient(to bottom, black 54%, transparent 100%);
    }

    .dark-grid {
      background-image:
        linear-gradient(rgba(255,255,255,.045) 1px, transparent 1px),
        linear-gradient(90deg, rgba(255,255,255,.045) 1px, transparent 1px);
      background-size: 38px 38px;
    }

    .dot-pattern {
      background-image: radial-gradient(rgba(59,111,245,.16) 1px, transparent 1px);
      background-size: 18px 18px;
    }

    .btn-primary,
    .btn-secondary,
    .btn-dark,
    .btn-whatsapp {
      display: inline-flex;
      min-height: 48px;
      align-items: center;
      justify-content: center;
      gap: 9px;
      border-radius: 8px;
      padding: 12px 20px;
      font-family: "Inter", sans-serif;
      font-size: 14px;
      font-weight: 600;
      transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease, border-color .18s ease;
    }

    .btn-primary { background: var(--brand); color: white; box-shadow: 0 8px 22px rgba(59,111,245,.22); }
    .btn-primary:hover { background: #2A55D9; transform: translateY(-1px); box-shadow: 0 12px 28px rgba(59,111,245,.28); }
    .btn-secondary { border: 1px solid #C9D6E6; background: white; color: #12213A; }
    .btn-secondary:hover { border-color: #92A8C3; background: #F8FAFD; transform: translateY(-1px); }
    .btn-dark { border: 1px solid rgba(255,255,255,.2); background: rgba(255,255,255,.08); color: white; }
    .btn-dark:hover { background: rgba(255,255,255,.14); transform: translateY(-1px); }
    .btn-whatsapp { background: #1FBF68; color: white; }
    .btn-whatsapp:hover { background: #19A85A; transform: translateY(-1px); }

    .focusable:focus-visible,
    a:focus-visible,
    button:focus-visible {
      outline: 3px solid rgba(18,184,166,.42);
      outline-offset: 3px;
    }

    .section-label {
      display: inline-block;
      color: #3B6FF5;
      font-size: 12px;
      font-weight: 700;
      letter-spacing: .12em;
      text-transform: uppercase;
    }

    .comparison-intro {
      display: grid;
      gap: 2rem;
      align-items: start;
    }

    .comparison-intro__title {
      max-width: 720px;
    }

    .comparison-intro__copy {
      max-width: 560px;
    }

    @media (min-width: 1024px) {
      .comparison-intro {
        grid-template-columns: minmax(0, 1.02fr) minmax(380px, .78fr);
        gap: 5.5rem;
      }

      .comparison-intro__copy {
        padding-top: 2.55rem;
      }
    }

    .comparison-wrapper {
      overflow: hidden;
      border: 1px solid var(--line);
      border-radius: 18px;
      background: linear-gradient(180deg, #FFFFFF 0%, #FAFCFF 100%);
      box-shadow: 0 18px 48px rgba(15,28,46,.06);
    }

    .comparison-card {
      height: 100%;
      padding: 2rem;
    }

    .comparison-card--before {
      background: #F8FAFD;
    }

    .comparison-card--after {
      background: #0F1C2E;
      color: #FFFFFF;
    }

    .comparison-list {
      margin-top: 1.5rem;
      display: grid;
      gap: 1rem;
    }

    .comparison-item {
      display: grid;
      grid-template-columns: auto 1fr;
      gap: .85rem;
      align-items: start;
    }

    .comparison-item__bullet {
      display: inline-flex;
      height: 24px;
      width: 24px;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      flex: 0 0 auto;
      margin-top: 1px;
    }

    .comparison-item__title {
      display: block;
      font-size: 14px;
      font-weight: 700;
      line-height: 1.4;
    }

    .comparison-item__copy {
      display: block;
      margin-top: 4px;
      font-size: 13px;
      line-height: 1.65;
    }

    @media (min-width: 640px) {
      .comparison-card {
        padding: 2.5rem;
      }
      .comparison-list {
        gap: 1.1rem;
      }
    }

    .reveal {
      opacity: 1;
      transform: none;
      transition:
        opacity .62s cubic-bezier(.22,1,.36,1),
        transform .62s cubic-bezier(.22,1,.36,1);
      transition-delay: var(--reveal-delay, 0ms);
    }

    .reveal-ready .reveal { opacity: 0; transform: translateY(18px); }
    .reveal-ready .reveal.is-visible { opacity: 1; transform: none; }

    .dashboard-shell {
      transform: perspective(1400px) rotateY(-3deg) rotateX(1deg);
      transform-origin: center;
    }

    .module-tab[aria-selected="true"] {
      background: white;
      color: #061A46;
      border-color: white;
    }

    .module-stage { min-height: 580px; }
    .module-panel { min-height: 580px; }
    .module-panel > div { min-width: 0; }
    .module-panel[hidden] { display: none; }
    .module-panel.panel-enter { animation: panel-enter .52s cubic-bezier(.22,1,.36,1) both; }

    .system-preview {
      --preview-primary: #3B6FF5;
      --preview-ink: #0F1C2E;
      --preview-muted: #5A6A7A;
      --preview-faint: #94A3B8;
      --preview-bg: #F4F6FB;
      --preview-card: #FFFFFF;
      --preview-line: #E4EAF2;
      color: var(--preview-ink);
      font-family: "Inter", sans-serif;
    }
    .system-preview-sidebar {
      background: var(--preview-card);
      border-right: 1px solid var(--preview-line);
    }
    .system-preview-nav {
      display: flex;
      min-height: 34px;
      align-items: center;
      gap: 8px;
      border-radius: 7px;
      padding: 7px 9px;
      color: var(--preview-muted);
    }
    .system-preview-nav.is-active {
      background: rgba(59,111,245,.08);
      color: var(--preview-primary);
      font-weight: 600;
    }
    .system-preview-card {
      border: 1px solid var(--preview-line);
      border-radius: 10px;
      background: var(--preview-card);
      box-shadow: 0 1px 4px rgba(15,28,46,.05);
    }
    .system-preview-kpi {
      position: relative;
      overflow: hidden;
      border: 1px solid var(--preview-line);
      border-radius: 10px;
      background: var(--preview-card);
    }
    .system-preview-kpi::before {
      content: "";
      position: absolute;
      inset: 0 0 auto;
      height: 3px;
      background: var(--kpi-color, var(--preview-primary));
    }
    .system-preview-table-row {
      display: grid;
      align-items: center;
      gap: 10px;
      border-top: 1px solid var(--preview-line);
      padding: 10px 0;
    }
    .feature-proof {
      border: 1px solid var(--preview-line);
      border-radius: 10px;
      background: var(--preview-bg);
      padding: 12px;
    }
    .resource-grid { align-items: stretch; }
    .resource-card {
      --resource-accent: #3B6FF5;
      --resource-soft: rgba(59,111,245,.09);
      position: relative;
      isolation: isolate;
      overflow: hidden;
      display: flex;
      min-height: 370px;
      height: 100%;
      flex-direction: column;
      border-color: rgba(15,28,46,.1);
      background: linear-gradient(145deg, #FFFFFF 0%, #FFFFFF 68%, var(--resource-soft) 150%);
      box-shadow: 0 1px 2px rgba(15,28,46,.04), 0 14px 36px rgba(15,28,46,.07);
      transition: transform .3s cubic-bezier(.22,1,.36,1), border-color .3s ease, box-shadow .3s ease;
    }
    .resource-card::before {
      content: "";
      position: absolute;
      inset: 0 0 auto;
      z-index: 2;
      height: 3px;
      background: linear-gradient(90deg, var(--resource-accent), transparent 78%);
    }
    .resource-card::after {
      content: "";
      position: absolute;
      z-index: -1;
      width: 190px;
      height: 190px;
      right: -85px;
      top: -90px;
      border-radius: 999px;
      background: var(--resource-soft);
      filter: blur(2px);
    }
    .resource-card--featured { min-height: 420px; }
    .resource-card--green { --resource-accent: #10B981; --resource-soft: rgba(16,185,129,.1); }
    .resource-card--amber { --resource-accent: #D58A17; --resource-soft: rgba(213,138,23,.1); }
    .resource-card--olive { --resource-accent: #587A31; --resource-soft: rgba(88,122,49,.1); }
    .resource-card__header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 20px;
    }
    .resource-card__icon {
      display: inline-flex;
      width: 48px;
      height: 48px;
      flex: 0 0 auto;
      align-items: center;
      justify-content: center;
      border: 1px solid color-mix(in srgb, var(--resource-accent) 22%, transparent);
      border-radius: 12px;
      background: var(--resource-soft);
      color: var(--resource-accent);
    }
    .resource-card__index {
      color: #8795A7;
      font-family: "JetBrains Mono", monospace;
      font-size: 9px;
      font-weight: 500;
      letter-spacing: .14em;
      text-transform: uppercase;
    }
    .resource-card__title { max-width: 15ch; }
    .resource-card__copy { max-width: 52ch; }
    .resource-card__proof {
      border: 1px solid rgba(15,28,46,.08);
      background: rgba(244,246,251,.82);
      box-shadow: inset 0 1px 0 rgba(255,255,255,.85);
    }
    @media (hover: hover) and (pointer: fine) {
      .resource-card:hover {
        transform: translateY(-4px);
        border-color: color-mix(in srgb, var(--resource-accent) 30%, #E4EAF2);
        box-shadow: 0 2px 4px rgba(15,28,46,.04), 0 22px 48px rgba(15,28,46,.11);
      }
    }
    @media (prefers-reduced-motion: reduce) {
      .resource-card { transition: none; }
      .resource-card:hover { transform: none; }
    }
    .resource-card > .feature-proof,
    .resource-card > .resource-proof {
      margin-top: auto;
    }
    .stock-proof {
      position: relative !important;
      inset: auto !important;
      display: block !important;
      width: 100% !important;
      margin-top: auto;
      align-self: stretch;
    }

    .demo-progress-track { background: rgba(255,255,255,.1); }
    .demo-progress-bar { width: 0; }
    .demo-progress-bar.is-running { animation: demo-progress var(--demo-duration, 6200ms) linear forwards; }

    .hero-demo-modal {
      opacity: 0;
      transform: translateY(10px) scale(.985);
      visibility: hidden;
      transition: opacity .28s ease, transform .38s cubic-bezier(.22,1,.36,1), visibility .28s;
    }
    .hero-demo-modal.is-open { opacity: 1; transform: none; visibility: visible; }
    .demo-field { transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease; }
    .demo-field.is-active { border-color: #3B6FF5; background: #fff; box-shadow: 0 0 0 3px rgba(59,111,245,.12); }
    .demo-save.is-pressing { transform: scale(.96); background: #2A55D9; }
    .demo-new-row { min-height: 54px; opacity: 0; transform: translateY(-8px); overflow: hidden; transition: opacity .35s ease, transform .35s ease; }
    .demo-new-row.is-visible { opacity: 1; transform: none; }
    .demo-toast { opacity: 0; transform: translateY(12px); pointer-events: none; transition: opacity .3s ease, transform .3s ease; }
    .demo-toast.is-visible { opacity: 1; transform: none; }
    .demo-pulse { animation: demo-pulse 1s ease-in-out infinite; }

    .hero-app-scene {
      position: absolute;
      inset: 0;
      overflow: hidden;
      padding: 1rem;
      opacity: 0;
      transform: translateX(18px) scale(.985);
      visibility: hidden;
      pointer-events: none;
      transition: opacity .38s ease, transform .48s cubic-bezier(.22,1,.36,1), visibility .38s;
    }
    .hero-app-scene.is-active {
      opacity: 1;
      transform: none;
      visibility: visible;
      pointer-events: auto;
    }
    .hero-chart-line {
      stroke-dasharray: 620;
      stroke-dashoffset: 620;
    }
    .hero-app-scene.is-active .hero-chart-line {
      animation: hero-chart-draw 1.35s cubic-bezier(.22,1,.36,1) .18s forwards;
    }
    .hero-chart-dot {
      opacity: 0;
      transform-box: fill-box;
      transform-origin: center;
    }
    .hero-app-scene.is-active .hero-chart-dot {
      animation: hero-dot-pop .35s ease forwards;
    }
    .hero-app-scene.is-active .hero-chart-dot:nth-of-type(2) { animation-delay: .55s; }
    .hero-app-scene.is-active .hero-chart-dot:nth-of-type(3) { animation-delay: .68s; }
    .hero-app-scene.is-active .hero-chart-dot:nth-of-type(4) { animation-delay: .81s; }
    .hero-app-scene.is-active .hero-chart-dot:nth-of-type(5) { animation-delay: .94s; }
    .hero-app-scene.is-active .hero-chart-dot:nth-of-type(6) { animation-delay: 1.07s; }
    .hero-app-scene.is-active .hero-chart-dot:nth-of-type(7) { animation-delay: 1.2s; }
    .hero-tour-progress { transform-origin: left; }
    .hero-tour-progress.is-running { animation: hero-tour-progress 3600ms linear forwards; }

    .store-phone { box-shadow: 0 35px 90px rgba(6,26,70,.22); }
    .store-screen { scrollbar-width: none; }
    .store-screen::-webkit-scrollbar { display: none; }
    .store-search-caret::after { content: ""; display: inline-block; width: 1px; height: 14px; margin-left: 2px; vertical-align: -2px; background: #48602A; animation: caret-blink .8s steps(1) infinite; }
    .store-chip { transition: background-color .3s ease, color .3s ease, transform .3s ease; }
    .store-chip.is-active { background: #48602A; color: white; transform: translateY(-1px); }
    .store-product { position: relative; transition: opacity .35s ease, transform .35s ease, box-shadow .35s ease; }
    .store-product.is-filtered { opacity: .18; transform: scale(.965); }
    .store-product.is-focused { transform: translateY(-4px); box-shadow: 0 16px 30px rgba(50,72,30,.18); }
    .store-add { position: absolute; right: .75rem; top: .75rem; z-index: 2; box-shadow: 0 5px 12px rgba(31,44,23,.14); }
    .store-add.is-pressing { transform: scale(.82); background: #48602A; color: white; }
    .store-cart-count { transform: scale(0); transition: transform .28s cubic-bezier(.34,1.56,.64,1); }
    .store-cart-count.has-item { transform: scale(1); }
    .store-demo-cursor {
      position: absolute;
      z-index: 30;
      width: 22px;
      height: 26px;
      color: #061A46;
      filter: drop-shadow(0 2px 3px rgba(0,0,0,.22));
      transition: left .7s cubic-bezier(.22,1,.36,1), top .7s cubic-bezier(.22,1,.36,1), transform .16s ease, opacity .25s ease;
      pointer-events: none;
    }
    .store-demo-cursor.is-clicking { transform: scale(.78); }
    .store-demo-cursor.is-hidden { opacity: 0; }
    .store-demo-status { transition: color .25s ease; }
    .store-catalog-screen {
      height: 100%;
      transition: opacity .35s ease, transform .45s cubic-bezier(.22,1,.36,1);
    }
    .store-catalog-screen.is-leaving { opacity: 0; transform: translateX(-22px); }
    .store-checkout {
      position: absolute;
      inset: 0;
      z-index: 15;
      display: flex;
      align-items: flex-end;
      background: rgba(15, 23, 18, .46);
      opacity: 0;
      visibility: hidden;
      transition: opacity .3s ease, visibility .3s;
    }
    .store-checkout.is-open { opacity: 1; visibility: visible; }
    .store-checkout-sheet {
      width: 100%;
      border-radius: 18px 18px 0 0;
      background: white;
      padding: 1.1rem;
      transform: translateY(100%);
      transition: transform .45s cubic-bezier(.22,1,.36,1);
    }
    .store-checkout.is-open .store-checkout-sheet { transform: none; }
    .store-whatsapp {
      position: absolute;
      inset: 0;
      z-index: 20;
      display: flex;
      flex-direction: column;
      background: #EFEAE2;
      opacity: 0;
      visibility: hidden;
      transform: translateX(24px);
      transition: opacity .35s ease, transform .45s cubic-bezier(.22,1,.36,1), visibility .35s;
    }
    .store-whatsapp.is-open { opacity: 1; visibility: visible; transform: none; }
    .store-whatsapp-message {
      opacity: 0;
      transform: translateY(10px);
      transition: opacity .35s ease, transform .35s ease;
    }
    .store-whatsapp-message.is-visible { opacity: 1; transform: none; }
    .store-whatsapp-typing { display: none; }
    .store-whatsapp-typing.is-visible { display: flex; }
    .store-whatsapp-typing span { animation: whatsapp-dot 1s infinite ease-in-out; }
    .store-whatsapp-typing span:nth-child(2) { animation-delay: .14s; }
    .store-whatsapp-typing span:nth-child(3) { animation-delay: .28s; }
    .store-confirmation { opacity: 0; transform: scale(.84); transition: opacity .3s ease, transform .4s cubic-bezier(.34,1.56,.64,1); }
    .store-confirmation.is-visible { opacity: 1; transform: none; }

    @keyframes panel-enter {
      from { opacity: 0; transform: translateX(18px); }
      to { opacity: 1; transform: none; }
    }
    @keyframes demo-progress { from { width: 0; } to { width: 100%; } }
    @keyframes hero-chart-draw { to { stroke-dashoffset: 0; } }
    @keyframes hero-dot-pop { from { opacity: 0; transform: scale(.4); } to { opacity: 1; transform: scale(1); } }
    @keyframes hero-tour-progress { from { transform: scaleX(0); } to { transform: scaleX(1); } }
    @keyframes demo-pulse {
      0%,100% { box-shadow: 0 0 0 0 rgba(59,111,245,0); }
      50% { box-shadow: 0 0 0 5px rgba(59,111,245,.16); }
    }
    @keyframes caret-blink { 50% { opacity: 0; } }
    @keyframes whatsapp-dot {
      0%, 60%, 100% { opacity: .35; transform: translateY(0); }
      30% { opacity: 1; transform: translateY(-2px); }
    }

    .faq-answer {
      display: grid;
      grid-template-rows: 0fr;
      transition: grid-template-rows .25s ease;
    }

    .faq-answer > div { overflow: hidden; }
    .faq-item.is-open .faq-answer { grid-template-rows: 1fr; }
    .faq-item.is-open .faq-icon { transform: rotate(45deg); }
    .faq-icon { transition: transform .2s ease; }

    @media (max-width: 1023px) {
      .dashboard-shell { transform: none; }
      .module-stage, .module-panel { min-height: 900px; }
    }

    @media (max-width: 639px) {
      .resource-card { min-height: 0; }
      .module-stage, .module-panel { min-height: 940px; }
      [data-hero-scene="dashboard"] .mt-3.grid > .system-preview-card:last-child { display: none; }
      [data-hero-scene="vendedores"] .mt-3.grid > .system-preview-card:nth-child(n+2) { display: none; }
    }

    @media (prefers-reduced-motion: reduce) {
      html { scroll-behavior: auto; }
      *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
      .reveal-ready .reveal { opacity: 1; transform: none; }
      .dashboard-shell { transform: none; }
      .module-panel.panel-enter { animation: none; }
      .demo-progress-bar.is-running, .demo-pulse, .hero-chart-line, .hero-chart-dot, .hero-tour-progress, .store-search-caret::after { animation: none; }
      .hero-chart-line { stroke-dashoffset: 0; }
      .hero-chart-dot { opacity: 1; }
      .store-demo-cursor { display: none; }
    }
  </style>
<link rel="stylesheet" href="assets/brand/uplar-brand.css?v=20261020.1">
</head>

<body class="bg-white font-sans text-ink antialiased selection:bg-cyan-soft selection:text-navy">
  <a href="#conteudo" class="fixed left-4 top-4 z-[100] -translate-y-24 rounded-md bg-navy px-4 py-3 text-sm font-semibold text-white transition focus:translate-y-0">Pular para o conteúdo</a>

  <!-- Navegação -->
  <header class="fixed top-0 z-50 w-full border-b border-line bg-white/80 shadow-sm backdrop-blur-md">
    <nav class="mx-auto flex h-20 max-w-7xl items-center justify-between px-5 sm:px-8 lg:px-10" aria-label="Navegação principal">
      <div class="flex items-center gap-5">
        <a href="https://estoque.uplar.cloud/" class="flex items-center gap-2.5" aria-label="Uplar Estoque — início">
<span class="uplar-brand-mark" aria-hidden="true"><img class="uplar-brand-image" src="assets/brand/uplar-mark-64.png" srcset="assets/brand/uplar-mark-128.png 2x, assets/brand/uplar-mark-256.png 4x" width="64" height="39" alt="" decoding="async" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><span class="uplar-brand-fallback" hidden>U</span></span>
          <span class="font-display text-xl font-extrabold tracking-[-.03em] text-brand">UPLAR</span>
          <span class="h-5 w-px bg-line"></span>
          <span class="font-mono text-[10px] font-medium uppercase tracking-[.18em] text-muted">Estoque</span>
        </a>
        <a href="https://uplar.cloud/" class="hidden border-l border-line pl-5 text-xs font-medium text-muted transition hover:text-brand xl:inline">Conheça a Uplar</a>
      </div>

      <div class="hidden items-center gap-8 lg:flex">
        <a href="#vitrine" class="text-sm font-semibold text-muted transition-colors hover:text-brand">Vitrine online</a>
        <a href="#demonstracao" class="text-sm font-semibold text-muted transition-colors hover:text-brand">Funcionalidades</a>
        <a href="#demonstracao" class="text-sm font-semibold text-muted transition-colors hover:text-brand">O sistema</a>
        <a href="#duvidas" class="text-sm font-semibold text-muted transition-colors hover:text-brand">Dúvidas</a>
      </div>

      <div class="hidden items-center gap-4 md:flex">
        <a href="https://estoque.uplar.cloud/login" class="text-sm font-semibold text-navy transition-colors hover:text-brand">Acessar sistema</a>
        <a href="https://estoque.uplar.cloud/planos" class="inline-flex min-h-[40px] items-center gap-2 rounded bg-brand px-6 py-2 text-sm font-semibold text-white shadow-[0_4px_14px_0_rgba(59,111,245,.32)] transition hover:bg-brand-dark">
          Ver planos
          <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
        </a>
      </div>

      <button id="menuButton" class="flex h-11 w-11 items-center justify-center rounded-md border border-line text-ink md:hidden" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="mobileMenu">
        <span class="material-symbols-outlined">menu</span>
      </button>
    </nav>

    <div id="mobileMenu" class="hidden border-t border-line bg-white md:hidden">
      <div class="mx-auto flex max-w-7xl flex-col gap-1 px-5 py-4">
        <a href="#vitrine" class="rounded-md px-3 py-3 text-sm font-medium text-muted">Vitrine online</a>
        <a href="#demonstracao" class="rounded-md px-3 py-3 text-sm font-medium text-muted">Funcionalidades</a>
        <a href="#demonstracao" class="rounded-md px-3 py-3 text-sm font-medium text-muted">O sistema</a>
        <a href="#duvidas" class="rounded-md px-3 py-3 text-sm font-medium text-muted">Dúvidas</a>
        <a href="https://estoque.uplar.cloud/login" class="btn-secondary mt-3 w-full">Acessar sistema</a>
        <a href="https://estoque.uplar.cloud/planos" class="btn-primary mt-2 w-full">Ver planos</a>
      </div>
    </div>
  </header>

  <main id="conteudo" class="pt-20">
    <!-- Hero -->
    <section class="relative overflow-hidden bg-canvas">
      <div class="hero-grid pointer-events-none absolute inset-0"></div>
      <div class="pointer-events-none absolute -right-44 top-6 h-[560px] w-[560px] rounded-full bg-brand/10 blur-3xl"></div>
      <div class="pointer-events-none absolute right-[18%] top-[36%] h-56 w-56 rounded-full bg-cyan/10 blur-3xl"></div>

      <div class="relative mx-auto grid min-h-[calc(100vh-80px)] max-w-7xl items-center gap-12 px-5 py-12 sm:px-8 sm:py-14 lg:grid-cols-[.88fr_1.12fr] lg:px-10 lg:py-12">
        <div>
<h1 class="max-w-2xl font-display text-[42px] font-extrabold leading-[1.08] tracking-[-.045em] text-navy sm:text-5xl lg:text-[62px]">
            Estoque, vendas e financeiro <span class="text-brand">sob controle.</span>
          </h1>

          <p class="mt-7 max-w-xl text-lg leading-8 text-muted">
            Gerencie produtos, acompanhe sua equipe, registre vendas e visualize o financeiro em um único sistema — sem depender de planilhas.
          </p>

          <div class="mt-9 flex flex-col gap-3 sm:flex-row">
            <a href="https://estoque.uplar.cloud/planos" class="btn-primary px-6 py-3.5 text-[15px]">
              Ver planos e começar
              <span class="material-symbols-outlined text-[19px]">arrow_forward</span>
            </a>
            <a href="https://estoque.uplar.cloud/login" class="btn-secondary px-6 py-3.5 text-[15px]">Já sou cliente</a>
          </div>

          <div class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-xs font-medium text-muted">
            <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[17px] text-cyan">check_circle</span>Sem instalação</span>
            <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[17px] text-cyan">check_circle</span>Acesso web e mobile</span>
            <span class="flex items-center gap-1.5"><span class="material-symbols-outlined text-[17px] text-cyan">check_circle</span>Suporte direto</span>
          </div>
        </div>

        <!-- Mockup do produto -->
        <div class="dashboard-shell relative lg:translate-x-2">
          <div class="absolute -left-8 -top-8 hidden rounded-xl border border-line bg-white p-4 shadow-card sm:block">
            <div class="flex items-center gap-3">
              <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-cyan-soft text-cyan"><span class="material-symbols-outlined">trending_up</span></span>
              <div><p class="text-[11px] font-medium text-muted">Operação atualizada</p><p class="mt-0.5 font-display text-sm font-bold text-ink">Em tempo real</p></div>
            </div>
          </div>

          <div class="system-preview overflow-hidden rounded-[14px] border border-[#CDD7E5] bg-white shadow-product">
            <div class="flex h-12 items-center justify-between border-b border-line bg-white px-4">
              <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 rounded-full bg-[#FF6B6B]"></span><span class="h-2.5 w-2.5 rounded-full bg-[#FFCC66]"></span><span class="h-2.5 w-2.5 rounded-full bg-[#4DD4AC]"></span>
              </div>
              <div id="heroBrowserPath" class="rounded bg-canvas px-4 py-1.5 font-mono text-[9px] text-muted">estoque.uplar.cloud / relatorios</div>
              <span class="material-symbols-outlined text-lg text-muted">more_horiz</span>
            </div>

            <div class="grid min-h-[440px] grid-cols-[66px_1fr] sm:grid-cols-[164px_1fr]">
              <aside class="system-preview-sidebar p-3 sm:p-4">
                <div class="mb-4 flex items-center gap-2 border-b border-line pb-4 text-ink">
                  <span class="uplar-brand-mark" aria-hidden="true"><img class="uplar-brand-image" src="assets/brand/uplar-mark-64.png" srcset="assets/brand/uplar-mark-128.png 2x, assets/brand/uplar-mark-256.png 4x" width="64" height="39" alt="" decoding="async" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><span class="uplar-brand-fallback" hidden>U</span></span><span class="hidden text-sm font-bold sm:inline">UPLAR</span>
                </div>
                <p class="mb-2 hidden px-2 text-[8px] font-bold uppercase tracking-wider text-[#94A3B8] sm:block">Gestão</p>
                <div class="space-y-1">
                  <button class="system-preview-nav w-full" type="button" data-hero-target="dashboard" aria-label="Mostrar dashboard"><span class="material-symbols-outlined text-[18px]">dashboard</span><span class="hidden text-[11px] sm:inline">Início</span></button>
                  <button class="system-preview-nav w-full" type="button" data-hero-target="produtos" aria-label="Mostrar produtos"><span class="material-symbols-outlined text-[18px]">inventory_2</span><span class="hidden text-[11px] sm:inline">Produtos</span></button>
                  <button class="system-preview-nav w-full" type="button" data-hero-target="pedidos" aria-label="Mostrar pedidos"><span class="material-symbols-outlined text-[18px]">receipt_long</span><span class="hidden text-[11px] sm:inline">Pedidos</span></button>
                  <button class="system-preview-nav is-active w-full" type="button" data-hero-target="relatorios" aria-label="Mostrar relatórios"><span class="material-symbols-outlined text-[18px]">bar_chart</span><span class="hidden text-[11px] sm:inline">Relatórios</span></button>
                  <button class="system-preview-nav w-full" type="button" data-hero-target="vendedores" aria-label="Mostrar vendedores"><span class="material-symbols-outlined text-[18px]">groups</span><span class="hidden text-[11px] sm:inline">Vendedores</span></button>
                  <button class="system-preview-nav w-full" type="button" data-hero-target="vitrine" aria-label="Mostrar vitrine online"><span class="material-symbols-outlined text-[18px]">storefront</span><span class="hidden text-[11px] sm:inline">Vitrine</span></button>
                </div>
              </aside>

              <div id="heroDemo" class="relative min-h-[440px] overflow-hidden bg-[#F4F6FB]" aria-label="Demonstração navegável dos principais módulos do Uplar Estoque">
                <div class="hero-app-scene is-active" data-hero-scene="relatorios" aria-hidden="false">
                  <div class="flex items-start justify-between">
                    <div><p class="text-lg font-bold text-ink sm:text-xl">Relatórios</p><p class="mt-1 text-[10px] text-muted sm:text-xs">Decisões baseadas no que está acontecendo agora</p></div>
                    <span class="hidden rounded-md border border-line bg-white px-3 py-2 text-[9px] font-semibold text-muted sm:inline">Últimos 30 dias</span>
                  </div>
                  <div class="mt-4 grid grid-cols-3 gap-2.5">
                    <div class="system-preview-kpi p-3"><p class="text-[8px] font-semibold text-muted">Receita</p><p class="mt-2 text-sm font-bold sm:text-base">R$ 42.680</p><p class="mt-1 text-[8px] font-semibold text-[#10B981]">↑ 18,4%</p></div>
                    <div class="system-preview-kpi p-3" style="--kpi-color:#10B981"><p class="text-[8px] font-semibold text-muted">Vendas</p><p class="mt-2 text-sm font-bold sm:text-base">318</p><p class="mt-1 text-[8px] font-semibold text-[#10B981]">↑ 34 este mês</p></div>
                    <div class="system-preview-kpi p-3" style="--kpi-color:#7C3AED"><p class="text-[8px] font-semibold text-muted">Ticket médio</p><p class="mt-2 text-sm font-bold sm:text-base">R$ 134</p><p class="mt-1 text-[8px] font-semibold text-[#7C3AED]">Melhor: R$ 189</p></div>
                  </div>
                  <div class="system-preview-card mt-3 p-3 sm:p-4">
                    <div class="flex items-center justify-between"><div><p class="text-[10px] font-bold">Desempenho de vendas</p><p class="mt-1 text-[8px] text-muted">Receita diária · evolução do período</p></div><span class="rounded bg-cyan-soft px-2 py-1 text-[8px] font-bold text-[#087F75]">+18,4%</span></div>
                    <svg class="mt-3 h-[150px] w-full" viewBox="0 0 520 180" role="img" aria-label="Gráfico de receita crescente nos últimos 30 dias">
                      <defs>
                        <linearGradient id="heroChartFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#3B6FF5" stop-opacity=".24"/><stop offset="1" stop-color="#3B6FF5" stop-opacity="0"/></linearGradient>
                      </defs>
                      <g stroke="#E4EAF2" stroke-width="1"><path d="M18 25H502"/><path d="M18 68H502"/><path d="M18 111H502"/><path d="M18 154H502"/></g>
                      <path d="M18 145 C55 136 68 125 100 130 S153 103 185 112 S235 78 269 88 S324 47 357 63 S416 31 449 42 S480 18 502 24 V164 H18Z" fill="url(#heroChartFill)"/>
                      <path class="hero-chart-line" d="M18 145 C55 136 68 125 100 130 S153 103 185 112 S235 78 269 88 S324 47 357 63 S416 31 449 42 S480 18 502 24" fill="none" stroke="#3B6FF5" stroke-linecap="round" stroke-width="4"/>
                      <g fill="#fff" stroke="#3B6FF5" stroke-width="3"><circle class="hero-chart-dot" cx="18" cy="145" r="5"/><circle class="hero-chart-dot" cx="100" cy="130" r="5"/><circle class="hero-chart-dot" cx="185" cy="112" r="5"/><circle class="hero-chart-dot" cx="269" cy="88" r="5"/><circle class="hero-chart-dot" cx="357" cy="63" r="5"/><circle class="hero-chart-dot" cx="449" cy="42" r="5"/><circle class="hero-chart-dot" cx="502" cy="24" r="5"/></g>
                    </svg>
                    <div class="grid grid-cols-3 gap-2 border-t border-line pt-2 text-center"><div><p class="text-[9px] font-bold">Camisa DryFit</p><p class="text-[7px] text-muted">Mais vendido</p></div><div><p class="text-[9px] font-bold">Quinta-feira</p><p class="text-[7px] text-muted">Melhor dia</p></div><div><p class="text-[9px] font-bold">R$ 2.940</p><p class="text-[7px] text-muted">Pico diário</p></div></div>
                  </div>
                </div>

                <div class="hero-app-scene" data-hero-scene="vitrine" aria-hidden="true">
                  <div class="flex items-start justify-between">
                    <div><p class="text-lg font-bold text-ink sm:text-xl">Vitrine online</p><p class="mt-1 text-[10px] text-muted sm:text-xs">Sua loja pronta para vender no link</p></div>
                    <span class="flex items-center gap-1.5 rounded-full bg-cyan-soft px-3 py-1.5 text-[8px] font-bold text-[#087F75]"><span class="h-1.5 w-1.5 rounded-full bg-[#10B981]"></span>PUBLICADA</span>
                  </div>
                  <div class="mt-4 grid h-[346px] gap-3 sm:grid-cols-[.78fr_1.22fr]">
                    <div class="hidden space-y-2.5 sm:block">
                      <div class="system-preview-card p-3"><p class="text-[8px] font-semibold text-muted">Visitas neste mês</p><div class="mt-2 flex items-end justify-between"><strong class="text-xl">1.284</strong><span class="text-[8px] font-bold text-[#10B981]">↑ 24%</span></div></div>
                      <div class="system-preview-card p-3"><p class="text-[8px] font-semibold text-muted">Pedidos recebidos</p><div class="mt-2 flex items-end justify-between"><strong class="text-xl">76</strong><span class="material-symbols-outlined text-lg text-brand">shopping_bag</span></div></div>
                      <div class="rounded-lg border border-brand/15 bg-brand/5 p-3"><span class="material-symbols-outlined text-lg text-brand">link</span><p class="mt-2 text-[9px] font-bold">uplar.cloud/sualoja</p><p class="mt-1 text-[8px] leading-4 text-muted">Compartilhe no Instagram e WhatsApp.</p></div>
                    </div>
                    <div class="overflow-hidden rounded-xl border border-line bg-white shadow-card">
                      <div class="flex h-11 items-center justify-between border-b border-line px-3"><div class="flex items-center gap-2"><img class="h-6 w-6 rounded-md object-cover" src="imagens/vitrine/logo_9_1783613263_071f51fa.png" alt=""><span class="text-[9px] font-bold text-[#243019]">GearUP Sports</span></div><span class="material-symbols-outlined text-base text-[#48602A]">shopping_cart</span></div>
                      <div class="h-[72px] overflow-hidden bg-black"><img class="h-full w-full object-cover" src="imagens/vitrine/banner_9_1783382746_81fc814f.png" alt="Banner GearUP Sports: Seja sua melhor versão"></div>
                      <div class="p-3">
                        <div class="flex h-8 items-center gap-2 rounded-md border border-[#DCE3D6] bg-[#F8F9F6] px-2 text-[8px] text-[#7A8374]"><span class="material-symbols-outlined text-[13px]">search</span>Buscar produto...</div>
                        <div class="mt-2 flex gap-1.5"><span class="rounded-full bg-[#48602A] px-2 py-1 text-[7px] font-bold text-white">Todos</span><span class="rounded-full bg-[#EFF3E9] px-2 py-1 text-[7px] font-bold text-[#48602A]">Camisas</span><span class="rounded-full bg-[#EFF3E9] px-2 py-1 text-[7px] font-bold text-[#48602A]">Bermudas</span></div>
                        <div class="mt-3 grid grid-cols-2 gap-2"><div class="rounded-md border border-[#E2E7DD] p-2"><img class="h-14 w-full rounded object-cover object-top" src="imagens/img_6a49a2e3ab2b96.54449542.jpeg" alt="Camisa Nike branca"><p class="mt-2 text-[8px] font-semibold">Camisa NIKE</p><p class="mt-1 text-[9px] font-bold text-[#48602A]">R$ 50,00</p></div><div class="rounded-md border border-[#E2E7DD] p-2"><img class="h-14 w-full rounded object-cover" src="imagens/img_6a499df719ddf5.20480161.jpeg" alt="Bermuda Nike preta"><p class="mt-2 text-[8px] font-semibold">Bermuda NIKE</p><p class="mt-1 text-[9px] font-bold text-[#48602A]">R$ 75,00</p></div></div>
                        <div class="mt-3 flex items-center justify-center gap-1 rounded-md bg-[#1FBF68] py-2 text-[8px] font-bold text-white"><span class="material-symbols-outlined text-[13px]">chat</span>Finalizar pedido no WhatsApp</div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="hero-app-scene" data-hero-scene="produtos" aria-hidden="true">
                  <div class="flex items-start justify-between">
                    <div><p class="text-lg font-bold text-ink sm:text-xl">Produtos</p><p class="mt-1 text-[10px] text-muted sm:text-xs">Catálogo, estoque e margem em uma visão</p></div>
                    <span class="hidden rounded-full bg-brand px-3 py-2 text-[9px] font-semibold text-white sm:inline">+ Novo produto</span>
                  </div>
                  <div class="mt-4 grid grid-cols-3 gap-2.5">
                    <div class="system-preview-kpi p-3"><p class="text-[8px] text-muted">Cadastrados</p><p class="mt-2 text-lg font-bold">247</p><p class="mt-1 text-[7px] text-brand">228 ativos</p></div>
                    <div class="system-preview-kpi p-3" style="--kpi-color:#10B981"><p class="text-[8px] text-muted">Valor em estoque</p><p class="mt-2 text-sm font-bold sm:text-base">R$ 31.840</p><p class="mt-1 text-[7px] text-[#10B981]">Margem 42%</p></div>
                    <div class="system-preview-kpi p-3" style="--kpi-color:#F59E0B"><p class="text-[8px] text-muted">Precisam de atenção</p><p class="mt-2 text-lg font-bold">12</p><p class="mt-1 text-[7px] text-[#B87500]">3 zerados</p></div>
                  </div>
                  <div class="system-preview-card mt-3 p-3 sm:p-4">
                    <div class="flex items-center justify-between gap-2"><div><p class="text-[10px] font-bold">Catálogo de produtos</p><p class="mt-1 text-[8px] text-muted">Estoque atualizado em tempo real</p></div><span class="flex items-center gap-1 rounded-md border border-line bg-canvas px-2 py-1.5 text-[8px] text-muted"><span class="material-symbols-outlined text-[12px]">search</span>Buscar</span></div>
                    <div class="mt-3 flex gap-1.5 text-[7px] font-bold"><span class="rounded-full bg-brand/10 px-2 py-1 text-brand">Todos</span><span class="rounded-full border border-line px-2 py-1 text-muted">Estoque baixo</span><span class="rounded-full border border-line px-2 py-1 text-muted">Mais vendidos</span></div>
                    <div class="system-preview-table-row grid-cols-[auto_1fr_auto_auto] text-[9px]"><img class="h-8 w-8 rounded-md object-cover object-top" src="imagens/img_6a49a2e3ab2b96.54449542.jpeg" alt=""><span><strong class="block">Camisa NIKE</strong><small class="text-muted">GUP23 · R$ 50,00</small></span><span class="font-bold">84 un.</span><span class="rounded bg-cyan-soft px-2 py-1 text-[7px] font-bold text-[#087F75]">ATIVO</span></div>
                    <div class="system-preview-table-row grid-cols-[auto_1fr_auto_auto] text-[9px]"><img class="h-8 w-8 rounded-md object-cover" src="imagens/img_6a499df719ddf5.20480161.jpeg" alt=""><span><strong class="block">Bermuda NIKE</strong><small class="text-muted">GUP09 · R$ 75,00</small></span><span class="font-bold">18 un.</span><span class="rounded bg-cyan-soft px-2 py-1 text-[7px] font-bold text-[#087F75]">ATIVO</span></div>
                    <div class="system-preview-table-row grid-cols-[auto_1fr_auto_auto] text-[9px]"><img class="h-8 w-8 rounded-md object-cover" src="imagens/img_6a49a6753eba16.54892915.jpeg" alt=""><span><strong class="block">Meia NIKE</strong><small class="text-muted">GUP31 · R$ 25,00</small></span><span class="font-bold text-[#B87500]">4 un.</span><span class="rounded bg-amber-50 px-2 py-1 text-[7px] font-bold text-amber-700">BAIXO</span></div>
                  </div>
                </div>

                <div class="hero-app-scene" data-hero-scene="pedidos" aria-hidden="true">
                  <div class="flex items-start justify-between">
                    <div><p class="text-lg font-bold text-ink sm:text-xl">Pedidos</p><p class="mt-1 text-[10px] text-muted sm:text-xs">Da vitrine à confirmação, sem perder vendas</p></div>
                    <span class="flex items-center gap-1 rounded-md border border-line bg-white px-2 py-1.5 text-[8px] text-muted"><span class="material-symbols-outlined text-[13px]">search</span>Buscar pedido</span>
                  </div>
                  <div class="mt-4 grid grid-cols-3 gap-2.5">
                    <div class="system-preview-kpi p-3"><p class="text-[8px] text-muted">Recentes</p><p class="mt-2 text-lg font-bold">76</p><p class="mt-1 text-[7px] text-brand">R$ 9.480</p></div>
                    <div class="system-preview-kpi p-3" style="--kpi-color:#F59E0B"><p class="text-[8px] text-muted">Aguardando ação</p><p class="mt-2 text-lg font-bold">4</p><p class="mt-1 text-[7px] text-[#B87500]">Confirmar agora</p></div>
                    <div class="system-preview-kpi p-3" style="--kpi-color:#10B981"><p class="text-[8px] text-muted">Confirmados</p><p class="mt-2 text-lg font-bold">68</p><p class="mt-1 text-[7px] text-[#10B981]">89% do total</p></div>
                  </div>
                  <div class="system-preview-card mt-3 p-3 sm:p-4">
                    <div class="flex items-center justify-between"><div><p class="text-[10px] font-bold">Central de pedidos</p><p class="mt-1 text-[8px] text-muted">Priorize o que precisa de atenção</p></div><div class="flex gap-1 text-[7px] font-bold"><span class="rounded-full bg-brand/10 px-2 py-1 text-brand">Todos</span><span class="rounded-full border border-line px-2 py-1 text-muted">Pendentes</span></div></div>
                    <div class="mt-3 rounded-lg border border-line bg-canvas/60 p-3"><div class="flex items-center justify-between"><div><span class="text-[9px] font-bold text-brand">Pedido #1048</span><p class="mt-1 text-[8px] text-muted">Hoje, 10:42 · WhatsApp</p></div><span class="rounded bg-amber-50 px-2 py-1 text-[7px] font-bold text-amber-700">PENDENTE</span></div><div class="mt-3 flex items-end justify-between border-t border-line pt-2"><span><strong class="block text-[9px]">Marina Costa</strong><small class="text-[8px] text-muted">2 itens · Entrega</small></span><strong class="text-xs">R$ 180,00</strong></div></div>
                    <div class="mt-2 rounded-lg border border-line bg-white p-3"><div class="flex items-center justify-between"><div><span class="text-[9px] font-bold text-brand">Pedido #1047</span><p class="mt-1 text-[8px] text-muted">Hoje, 09:18 · Vitrine</p></div><span class="rounded bg-cyan-soft px-2 py-1 text-[7px] font-bold text-[#087F75]">CONFIRMADO</span></div><div class="mt-3 flex items-end justify-between border-t border-line pt-2"><span><strong class="block text-[9px]">Carlos Lima</strong><small class="text-[8px] text-muted">1 item · Retirada</small></span><strong class="text-xs">R$ 70,00</strong></div></div>
                  </div>
                </div>

                <div class="hero-app-scene" data-hero-scene="vendedores" aria-hidden="true">
                  <div class="flex items-start justify-between">
                    <div><p class="text-lg font-bold text-ink sm:text-xl">Vendedores</p><p class="mt-1 text-[10px] text-muted sm:text-xs">Equipe, produtos e desempenho individual</p></div>
                    <span class="hidden rounded-full bg-brand px-3 py-2 text-[9px] font-semibold text-white sm:inline">+ Novo vendedor</span>
                  </div>
                  <div class="mt-4 grid grid-cols-3 gap-2.5">
                    <div class="system-preview-kpi p-3"><p class="text-[8px] text-muted">Equipe ativa</p><p class="mt-2 text-lg font-bold">5</p><p class="mt-1 text-[7px] text-brand">100% ativos</p></div>
                    <div class="system-preview-kpi p-3" style="--kpi-color:#10B981"><p class="text-[8px] text-muted">Vendas da equipe</p><p class="mt-2 text-lg font-bold">184</p><p class="mt-1 text-[7px] text-[#10B981]">↑ 21% no mês</p></div>
                    <div class="system-preview-kpi p-3" style="--kpi-color:#7C3AED"><p class="text-[8px] text-muted">Produtos alocados</p><p class="mt-2 text-lg font-bold">83</p><p class="mt-1 text-[7px] text-[#7C3AED]">Sem divergências</p></div>
                  </div>
                  <div class="mt-3 grid gap-2.5 sm:grid-cols-2">
                    <div class="system-preview-card p-3"><div class="flex items-center justify-between"><div class="flex items-center gap-2.5"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand/10 text-[10px] font-bold text-brand">MA</span><span><strong class="block text-[10px]">Maria Andrade</strong><small class="text-[8px] text-muted">22 produtos alocados</small></span></div><span class="rounded bg-cyan-soft px-2 py-1 text-[7px] font-bold text-[#087F75]">ATIVA</span></div><div class="mt-3 grid grid-cols-2 gap-2 border-t border-line pt-3"><div><p class="text-[8px] text-muted">Vendas</p><strong class="text-sm">64</strong></div><div><p class="text-[8px] text-muted">Receita</p><strong class="text-sm">R$ 8.940</strong></div></div><div class="mt-3 h-1.5 rounded bg-line"><div class="h-full w-[88%] rounded bg-brand"></div></div></div>
                    <div class="system-preview-card p-3"><div class="flex items-center justify-between"><div class="flex items-center gap-2.5"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand/10 text-[10px] font-bold text-brand">JS</span><span><strong class="block text-[10px]">João Silva</strong><small class="text-[8px] text-muted">18 produtos alocados</small></span></div><span class="rounded bg-cyan-soft px-2 py-1 text-[7px] font-bold text-[#087F75]">ATIVO</span></div><div class="mt-3 grid grid-cols-2 gap-2 border-t border-line pt-3"><div><p class="text-[8px] text-muted">Vendas</p><strong class="text-sm">52</strong></div><div><p class="text-[8px] text-muted">Receita</p><strong class="text-sm">R$ 7.280</strong></div></div><div class="mt-3 h-1.5 rounded bg-line"><div class="h-full w-[74%] rounded bg-brand"></div></div></div>
                    <div class="system-preview-card p-3 sm:col-span-2"><div class="flex items-center justify-between"><div><p class="text-[10px] font-bold">Ranking do mês</p><p class="mt-1 text-[8px] text-muted">Desempenho por receita</p></div><div class="flex items-center gap-2 text-[8px]"><span class="font-bold text-[#F59E0B]">1º Maria</span><span class="text-muted">2º João</span><span class="text-muted">3º Ana</span></div></div></div>
                  </div>
                </div>

                <div class="hero-app-scene" data-hero-scene="dashboard" aria-hidden="true">
                  <div class="flex items-start justify-between"><div><p class="text-lg font-bold text-ink sm:text-xl">Dashboard</p><p class="mt-1 text-[10px] text-muted sm:text-xs">Tudo que precisa da sua atenção em uma tela</p></div><span class="hidden rounded-full bg-brand px-3 py-2 text-[9px] font-semibold text-white sm:inline">+ Cadastrar produto</span></div>
                  <div class="mt-4 grid grid-cols-2 gap-2.5 xl:grid-cols-4"><div class="system-preview-kpi p-3"><p class="text-[8px] text-muted">Produtos</p><p class="mt-2 text-lg font-bold">247</p></div><div class="system-preview-kpi p-3" style="--kpi-color:#10B981"><p class="text-[8px] text-muted">Receita 30d</p><p class="mt-2 text-base font-bold">R$ 42,6 mil</p></div><div class="system-preview-kpi p-3" style="--kpi-color:#F59E0B"><p class="text-[8px] text-muted">Estoque baixo</p><p class="mt-2 text-lg font-bold">12</p></div><div class="system-preview-kpi p-3" style="--kpi-color:#7C3AED"><p class="text-[8px] text-muted">Pedidos</p><p class="mt-2 text-lg font-bold">76</p></div></div>
                  <div class="mt-3 grid gap-3 sm:grid-cols-[1.15fr_.85fr]">
                    <div class="system-preview-card p-4"><div class="border-b border-line pb-3"><p class="text-[10px] font-bold">Central de atenção</p><p class="mt-1 text-[8px] text-muted">Prioridades da operação</p></div><div class="system-preview-table-row grid-cols-[auto_1fr_auto] text-[9px]"><span class="material-symbols-outlined text-base text-[#EF4444]">error</span><span><strong class="block">3 produtos zerados</strong><small class="text-muted">Reposição necessária</small></span><span class="text-brand">Ver</span></div><div class="system-preview-table-row grid-cols-[auto_1fr_auto] text-[9px]"><span class="material-symbols-outlined text-base text-[#F59E0B]">schedule</span><span><strong class="block">4 pedidos pendentes</strong><small class="text-muted">Aguardando confirmação</small></span><span class="text-brand">Ver</span></div><div class="system-preview-table-row grid-cols-[auto_1fr_auto] text-[9px]"><span class="material-symbols-outlined text-base text-[#10B981]">trending_up</span><span><strong class="block">Meta em 82%</strong><small class="text-muted">R$ 7.320 para concluir</small></span><span class="text-brand">Abrir</span></div></div>
                    <div class="system-preview-card p-4"><p class="text-[10px] font-bold">Últimas vendas</p><div class="mt-3 space-y-3"><div class="flex justify-between border-b border-line pb-2 text-[9px]"><span><strong class="block">Camisa DryFit</strong><small class="text-muted">Hoje, 10:42</small></span><strong>R$ 120</strong></div><div class="flex justify-between border-b border-line pb-2 text-[9px]"><span><strong class="block">Bermuda Sport</strong><small class="text-muted">Hoje, 09:18</small></span><strong>R$ 70</strong></div><div class="flex justify-between text-[9px]"><span><strong class="block">Kit Performance</strong><small class="text-muted">Ontem, 18:36</small></span><strong>R$ 189</strong></div></div></div>
                  </div>
                </div>

                <div class="pointer-events-none absolute inset-x-0 bottom-0 z-30 h-1 bg-line/70"><div id="heroTourProgress" class="hero-tour-progress h-full bg-brand"></div></div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>

    <!-- Problema / solução -->
    <section class="bg-white py-24 sm:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
        <div class="comparison-intro">
          <div class="comparison-intro__title reveal" style="--reveal-delay: 0ms;">
            <span class="section-label">Controle visível</span>
            <h2 class="mt-4 font-display text-3xl font-bold leading-[1.1] tracking-[-.038em] text-navy sm:text-[44px]">
              Sua operação não precisa depender de memória, conversa solta e planilhas espalhadas.
            </h2>
          </div>

          <div class="comparison-intro__copy reveal" style="--reveal-delay: 100ms;">
            <p class="text-base leading-8 text-muted sm:text-[17px]">
              Quando estoque, vendas, equipe e financeiro ficam separados, o trabalho perde ritmo.
              O Uplar Estoque concentra essas informações em um único fluxo para facilitar acompanhamento,
              resposta e decisão.
            </p>
          </div>
        </div>

        <div class="comparison-wrapper mt-14">
          <div class="grid lg:grid-cols-2">
            <article class="comparison-card comparison-card--before reveal border-b border-line lg:border-b-0 lg:border-r" style="--reveal-delay: 160ms;">
              <div class="flex items-center gap-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-white text-[#E35454] shadow-sm">
                  <span class="material-symbols-outlined">close</span>
                </span>
                <div>
                  <p class="text-[11px] font-semibold uppercase tracking-[.12em] text-[#C96A6A]">Antes</p>
                  <h3 class="font-display text-2xl font-bold text-ink">Operação fragmentada</h3>
                </div>
              </div>

              <div class="comparison-list">
                <div class="comparison-item">
                  <span class="comparison-item__bullet bg-red-50 text-[#E35454]"><span class="material-symbols-outlined text-[16px]">remove</span></span>
                  <div>
                    <span class="comparison-item__title text-ink">Venda registrada em um lugar, estoque ajustado em outro</span>
                    <span class="comparison-item__copy text-muted">A atualização depende de alguém lembrar de conferir e corrigir depois.</span>
                  </div>
                </div>

                <div class="comparison-item">
                  <span class="comparison-item__bullet bg-red-50 text-[#E35454]"><span class="material-symbols-outlined text-[16px]">remove</span></span>
                  <div>
                    <span class="comparison-item__title text-ink">Histórico espalhado entre planilhas, conversas e anotações</span>
                    <span class="comparison-item__copy text-muted">Cada informação fica em um ponto diferente, o que dificulta retorno rápido.</span>
                  </div>
                </div>

                <div class="comparison-item">
                  <span class="comparison-item__bullet bg-red-50 text-[#E35454]"><span class="material-symbols-outlined text-[16px]">remove</span></span>
                  <div>
                    <span class="comparison-item__title text-ink">Equipe e produtos sem acompanhamento claro</span>
                    <span class="comparison-item__copy text-muted">Fica mais difícil entender desempenho, disponibilidade e pendências do dia.</span>
                  </div>
                </div>

                <div class="comparison-item">
                  <span class="comparison-item__bullet bg-red-50 text-[#E35454]"><span class="material-symbols-outlined text-[16px]">remove</span></span>
                  <div>
                    <span class="comparison-item__title text-ink">Financeiro fechado sem contexto da operação</span>
                    <span class="comparison-item__copy text-muted">Entradas, saídas e vendas perdem conexão e abrem espaço para retrabalho.</span>
                  </div>
                </div>
              </div>
            </article>

            <article class="comparison-card comparison-card--after reveal" style="--reveal-delay: 280ms;">
              <div class="flex items-center gap-3">
                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-cyan/15 text-[#54D9C9]">
                  <span class="material-symbols-outlined">check</span>
                </span>
                <div>
                  <p class="text-[11px] font-semibold uppercase tracking-[.12em] text-[#7BE0D5]">Com o sistema</p>
                  <h3 class="font-display text-2xl font-bold">Com o Uplar Estoque</h3>
                </div>
              </div>

              <div class="comparison-list">
                <div class="comparison-item">
                  <span class="comparison-item__bullet bg-cyan/12 text-[#54D9C9]"><span class="material-symbols-outlined text-[16px]">check</span></span>
                  <div>
                    <span class="comparison-item__title text-white">Movimentações registradas no mesmo fluxo da operação</span>
                    <span class="comparison-item__copy text-blue-100/72">Produtos, vendas e atualizações ficam conectados sem depender de conferência paralela.</span>
                  </div>
                </div>

                <div class="comparison-item">
                  <span class="comparison-item__bullet bg-cyan/12 text-[#54D9C9]"><span class="material-symbols-outlined text-[16px]">check</span></span>
                  <div>
                    <span class="comparison-item__title text-white">Histórico centralizado e acessível</span>
                    <span class="comparison-item__copy text-blue-100/72">Pedidos, estoque, equipe e informações importantes podem ser consultados em um único ambiente.</span>
                  </div>
                </div>

                <div class="comparison-item">
                  <span class="comparison-item__bullet bg-cyan/12 text-[#54D9C9]"><span class="material-symbols-outlined text-[16px]">check</span></span>
                  <div>
                    <span class="comparison-item__title text-white">Indicadores que ajudam a agir mais rápido</span>
                    <span class="comparison-item__copy text-blue-100/72">O sistema destaca o que precisa de atenção para reduzir atraso, ruído e decisão no escuro.</span>
                  </div>
                </div>

                <div class="comparison-item">
                  <span class="comparison-item__bullet bg-cyan/12 text-[#54D9C9]"><span class="material-symbols-outlined text-[16px]">check</span></span>
                  <div>
                    <span class="comparison-item__title text-white">Visão integrada de estoque, vendas e financeiro</span>
                    <span class="comparison-item__copy text-blue-100/72">A rotina fica mais organizada e a leitura da operação acontece com mais segurança.</span>
                  </div>
                </div>
              </div>
            </article>
          </div>
        </div>
      </div>
    </section>

    <!-- Vitrine online em destaque -->
    <section id="vitrine" class="relative overflow-hidden border-y border-line bg-[#F4F7F2] py-24 sm:py-28">
      <div class="pointer-events-none absolute -left-32 top-20 h-96 w-96 rounded-full bg-[#A7C879]/20 blur-3xl"></div>
      <div class="pointer-events-none absolute -right-24 bottom-0 h-96 w-96 rounded-full bg-brand/10 blur-3xl"></div>

      <div class="relative mx-auto grid max-w-7xl items-center gap-14 px-5 sm:px-8 lg:grid-cols-[.82fr_1.18fr] lg:px-10">
        <div class="reveal" style="--reveal-delay: 0ms;">
          <span class="section-label text-[#48602A]">Seu canal de vendas</span>
          <h2 class="mt-5 max-w-xl font-display text-3xl font-bold leading-tight tracking-[-.04em] text-navy sm:text-[46px]">Seu estoque também pode ser uma <span class="text-[#587A31]">vitrine online.</span></h2>
          <p class="mt-6 max-w-xl text-base leading-7 text-muted sm:text-lg">Publique seus produtos em um catálogo com a identidade da sua loja, facilite a descoberta e leve o pedido direto para o WhatsApp — sem cadastrar tudo duas vezes.</p>

          <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <div class="flex gap-3 rounded-lg border border-[#DDE6D4] bg-white/75 p-4"><span class="material-symbols-outlined text-[#587A31]">sync</span><div><p class="text-sm font-semibold text-ink">Ligada ao estoque</p><p class="mt-1 text-xs leading-5 text-muted">Produtos e disponibilidade no mesmo fluxo.</p></div></div>
            <div class="flex gap-3 rounded-lg border border-[#DDE6D4] bg-white/75 p-4"><span class="material-symbols-outlined text-[#587A31]">palette</span><div><p class="text-sm font-semibold text-ink">Com a sua identidade</p><p class="mt-1 text-xs leading-5 text-muted">Logo, cores, banner e informações da loja.</p></div></div>
            <div class="flex gap-3 rounded-lg border border-[#DDE6D4] bg-white/75 p-4"><span class="material-symbols-outlined text-[#587A31]">search</span><div><p class="text-sm font-semibold text-ink">Busca e categorias</p><p class="mt-1 text-xs leading-5 text-muted">O cliente encontra o produto mais rápido.</p></div></div>
            <div class="flex gap-3 rounded-lg border border-[#DDE6D4] bg-white/75 p-4"><span class="material-symbols-outlined text-[#587A31]">chat</span><div><p class="text-sm font-semibold text-ink">Pedido no WhatsApp</p><p class="mt-1 text-xs leading-5 text-muted">Conversa de venda direto com sua equipe.</p></div></div>
          </div>

          <div class="mt-9 flex flex-col gap-3 sm:flex-row">
            <a href="https://estoque.uplar.cloud/planos" class="btn-primary px-6">Criar minha vitrine<span class="material-symbols-outlined text-[18px]">arrow_forward</span></a>
            <a href="https://uplar.cloud/gearup" target="_blank" rel="noopener" class="btn-secondary px-6">Ver GearUP em produção<span class="material-symbols-outlined text-[18px]">open_in_new</span></a>
          </div>
          <p class="mt-3 text-[11px] leading-5 text-muted">A GearUP Sports é um exemplo real de vitrine publicada na plataforma.</p>
        </div>

        <div id="storeDemo" class="reveal relative mx-auto w-full max-w-[610px]" style="--reveal-delay: 160ms;" aria-label="Demonstração automática da compra na vitrine até a confirmação pelo vendedor no WhatsApp">
          <div class="mb-4 flex items-center justify-between rounded-lg border border-[#DDE6D4] bg-white/80 px-4 py-3 backdrop-blur">
            <div class="flex items-center gap-2"><span class="relative flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#6B8E3E] opacity-40"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-[#587A31]"></span></span><span id="storeDemoStatus" class="store-demo-status font-mono text-[9px] uppercase tracking-[.14em] text-[#587A31]">Demonstração automática</span></div>
            <button id="storeDemoToggle" class="flex items-center gap-1.5 text-xs font-semibold text-muted transition hover:text-ink" type="button" aria-pressed="false"><span class="material-symbols-outlined text-[18px]">pause</span><span>Pausar</span></button>
          </div>

          <div class="store-phone relative mx-auto overflow-hidden rounded-[24px] border-[7px] border-[#142035] bg-white lg:max-w-[430px]">
            <div class="flex h-7 items-center justify-between bg-[#142035] px-5 text-[8px] text-white/70"><span>09:41</span><span class="flex items-center gap-1">● ● ▰</span></div>
            <div class="store-screen relative h-[610px] overflow-hidden bg-[#F6F7F3]">
              <div id="storeCatalogScreen" class="store-catalog-screen">
              <div class="flex h-14 items-center justify-between border-b border-[#E3E8DE] bg-white px-4">
                <div class="flex items-center gap-2"><img class="h-8 w-8 rounded-lg border border-[#E3E8DE] bg-white object-contain p-0.5" src="imagens/vitrine/logo_9_1783613263_071f51fa.png" alt="Logo da GearUP Sports"><span class="text-xs font-bold text-[#1F2C17]">GearUP Sports</span></div>
                <div id="storeCartButton" class="relative flex h-9 w-9 items-center justify-center rounded-lg bg-[#EFF3E9] text-[#48602A]"><span class="material-symbols-outlined text-[19px]">shopping_cart</span><span id="storeCartCount" class="store-cart-count absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-[#48602A] text-[8px] font-bold text-white">1</span></div>
              </div>

              <div class="h-28 overflow-hidden bg-[#101510]">
                <img class="h-full w-full object-cover" src="imagens/vitrine/banner_9_1783382746_81fc814f.png" alt="Banner da GearUP Sports: seja sua melhor versão">
              </div>

              <div class="border-b border-[#E3E8DE] bg-white px-4 py-4">
                <p class="text-sm font-bold text-[#1F2C17]">GearUP Sports</p>
                <p class="mt-1 text-[9px] leading-4 text-[#66705F]">Especialista em roupas esportivas e DRYFIT. Qualidade e preço acessível.</p>
                <div class="mt-3 grid grid-cols-2 gap-2 text-[8px] text-[#465041]"><span class="flex items-center gap-1"><span class="material-symbols-outlined text-[13px] text-[#587A31]">store</span>Retirada 10h–18h</span><span class="flex items-center gap-1"><span class="material-symbols-outlined text-[13px] text-[#587A31]">local_shipping</span>Entrega 12h–18h</span></div>
                <div class="mt-3 flex items-center justify-center gap-1.5 rounded-md bg-[#EFF3E9] py-2 text-[9px] font-semibold text-[#48602A]"><span class="material-symbols-outlined text-[14px]">chat</span>Entrega no meu endereço?</div>
              </div>

              <div class="bg-white px-4 pb-3 pt-3">
                <div id="storeSearchField" class="flex h-10 items-center gap-2 rounded-lg border border-[#DCE3D6] bg-[#F8F9F6] px-3 text-[10px] text-[#7A8374]"><span class="material-symbols-outlined text-[16px]">search</span><span id="storeSearchText" class="store-search-caret"></span></div>
                <div class="mt-3 flex gap-2 overflow-hidden"><span class="store-chip is-active rounded-full px-3 py-1.5 text-[9px] font-semibold" data-store-chip="todos">Todos</span><span class="store-chip rounded-full bg-[#EFF3E9] px-3 py-1.5 text-[9px] font-semibold text-[#65705E]" data-store-chip="camisas">Camisas</span><span class="store-chip rounded-full bg-[#EFF3E9] px-3 py-1.5 text-[9px] font-semibold text-[#65705E]" data-store-chip="bermudas">Bermudas</span><span class="store-chip rounded-full bg-[#EFF3E9] px-3 py-1.5 text-[9px] font-semibold text-[#65705E]" data-store-chip="meias">Meias</span></div>
              </div>

              <div class="bg-[#F6F7F3] px-4 pb-20 pt-2">
                <div class="mb-2 flex items-center justify-between"><p class="text-[9px] font-bold uppercase tracking-wider text-[#5C6655]">Produtos</p><span id="storeProductCount" class="text-[8px] text-[#818A7B]">3 disponíveis</span></div>
                <div class="grid grid-cols-2 gap-2.5">
                  <article class="store-product rounded-lg border border-[#E2E7DD] bg-white p-2.5" data-category="camisas"><img class="h-16 w-full rounded-md bg-[#EEF1EA] object-cover" src="imagens/img_6a49a2e3ab2b96.54449542.jpeg" alt="Camisa NIKE da GearUP"><p class="mt-2 text-[9px] font-semibold text-[#283021]">Camisa NIKE</p><p class="mt-1 text-[8px] text-[#7A8374]">Tamanhos M, G e GG</p><div class="mt-2 flex items-center justify-between"><span class="text-[11px] font-bold text-[#48602A]">R$ 50,00</span><span id="storeAddProduct" class="store-add flex h-7 w-7 items-center justify-center rounded-full bg-[#EFF3E9] text-[#48602A] transition"><span class="material-symbols-outlined text-[16px]">add</span></span></div></article>
                  <article class="store-product rounded-lg border border-[#E2E7DD] bg-white p-2.5" data-category="bermudas"><img class="h-16 w-full rounded-md bg-[#EEF1EA] object-cover" src="imagens/img_6a499df719ddf5.20480161.jpeg" alt="Bermuda NIKE da GearUP"><p class="mt-2 text-[9px] font-semibold text-[#283021]">Bermuda NIKE</p><p class="mt-1 text-[8px] text-[#7A8374]">Com bolso e zíper</p><div class="mt-2 flex items-center justify-between"><span class="text-[11px] font-bold text-[#48602A]">R$ 75,00</span><span class="store-add flex h-7 w-7 items-center justify-center rounded-full bg-[#EFF3E9] text-[#48602A] transition"><span class="material-symbols-outlined text-[16px]">add</span></span></div></article>
                  <article class="store-product rounded-lg border border-[#E2E7DD] bg-white p-2.5" data-category="meias"><img class="h-16 w-full rounded-md bg-[#EEF1EA] object-cover" src="imagens/img_6a49a6753eba16.54892915.jpeg" alt="Meia NIKE da GearUP"><p class="mt-2 text-[9px] font-semibold text-[#283021]">Meia NIKE</p><p class="mt-1 text-[8px] text-[#7A8374]">Preta ou branca</p><div class="mt-2 flex items-center justify-between"><span class="text-[11px] font-bold text-[#48602A]">R$ 25,00</span><span class="store-add flex h-7 w-7 items-center justify-center rounded-full bg-[#EFF3E9] text-[#48602A] transition"><span class="material-symbols-outlined text-[16px]">add</span></span></div></article>
                </div>
              </div>

              <div class="absolute inset-x-0 bottom-0 grid h-14 grid-cols-3 border-t border-[#E3E8DE] bg-white text-center text-[8px] text-[#7A8374]"><span class="flex flex-col items-center justify-center gap-1 font-semibold text-[#48602A]"><span class="material-symbols-outlined text-[17px]">storefront</span>Catálogo</span><span class="flex flex-col items-center justify-center gap-1"><span class="material-symbols-outlined text-[17px]">shopping_bag</span>Pedidos</span><span class="flex flex-col items-center justify-center gap-1"><span class="material-symbols-outlined text-[17px]">store</span>Loja</span></div>
              </div>

              <div id="storeCheckout" class="store-checkout" aria-hidden="true">
                <div class="store-checkout-sheet">
                  <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-[#D9DED4]"></div>
                  <div class="flex items-center justify-between"><div><p class="text-sm font-bold text-[#1F2C17]">Seu pedido</p><p class="mt-1 text-[9px] text-[#7A8374]">1 item adicionado</p></div><span class="flex h-8 w-8 items-center justify-center rounded-full bg-[#EFF3E9] text-[#48602A]"><span class="material-symbols-outlined text-[17px]">shopping_bag</span></span></div>
                  <div class="mt-4 flex gap-3 rounded-lg border border-[#E2E7DD] p-3"><img class="h-14 w-14 rounded-md object-cover object-top" src="imagens/img_6a49a2e3ab2b96.54449542.jpeg" alt=""><div class="min-w-0 flex-1"><p class="text-[10px] font-semibold text-[#283021]">Camisa NIKE</p><p class="mt-1 text-[8px] text-[#7A8374]">Tamanho G · Quantidade 1</p><p class="mt-2 text-xs font-bold text-[#48602A]">R$ 50,00</p></div><span class="text-[9px] font-semibold text-[#66705F]">1x</span></div>
                  <div class="mt-4 flex items-center justify-between border-t border-[#E2E7DD] pt-4"><span class="text-[10px] text-[#66705F]">Total do pedido</span><strong class="text-base text-[#1F2C17]">R$ 50,00</strong></div>
                  <button id="storeWhatsappButton" class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-[#20B65A] py-3 text-[10px] font-bold text-white shadow-[0_8px_20px_rgba(32,182,90,.24)]" type="button"><span class="material-symbols-outlined text-[17px]">chat</span>Finalizar pedido no WhatsApp</button>
                  <p class="mt-2 text-center text-[8px] text-[#899184]">Você será atendido diretamente pela GearUP.</p>
                </div>
              </div>

              <div id="storeWhatsapp" class="store-whatsapp" aria-hidden="true">
                <div class="flex h-16 items-center gap-2 bg-[#075E54] px-3 text-white"><span class="material-symbols-outlined text-[18px]">arrow_back</span><img class="h-9 w-9 rounded-full border border-white/20 bg-white object-contain p-0.5" src="imagens/vitrine/logo_9_1783613263_071f51fa.png" alt=""><div class="min-w-0 flex-1"><p class="text-[11px] font-semibold">GearUP Sports</p><p class="mt-0.5 text-[8px] text-white/70">online</p></div><span class="material-symbols-outlined text-[18px]">videocam</span><span class="material-symbols-outlined text-[18px]">call</span></div>
                <div class="relative flex-1 overflow-hidden px-3 py-4" style="background-color:#EFEAE2;background-image:radial-gradient(rgba(88,72,52,.07) 1px,transparent 1px);background-size:16px 16px">
                  <div class="mx-auto mb-4 w-fit rounded-md bg-white/80 px-3 py-1 text-[7px] font-semibold uppercase tracking-wider text-[#66705F] shadow-sm">Hoje</div>
                  <div id="storeCustomerMessage" class="store-whatsapp-message ml-auto max-w-[84%] rounded-lg rounded-tr-sm bg-[#D9FDD3] p-3 text-[9px] leading-4 text-[#202C27] shadow-sm"><p class="font-semibold">Olá! Quero confirmar este pedido:</p><p class="mt-1">1x Camisa NIKE · Tamanho G</p><p>Retirada na loja · <strong>R$ 50,00</strong></p><p class="mt-1 text-right text-[7px] text-[#66705F]">09:42 <span class="font-bold text-[#53BDEB]">✓✓</span></p></div>
                  <div id="storeSellerTyping" class="store-whatsapp-typing mt-3 w-fit items-center gap-1 rounded-lg rounded-tl-sm bg-white px-3 py-2.5 shadow-sm"><span class="h-1.5 w-1.5 rounded-full bg-[#7A8374]"></span><span class="h-1.5 w-1.5 rounded-full bg-[#7A8374]"></span><span class="h-1.5 w-1.5 rounded-full bg-[#7A8374]"></span></div>
                  <div id="storeSellerMessage" class="store-whatsapp-message mt-3 max-w-[84%] rounded-lg rounded-tl-sm bg-white p-3 text-[9px] leading-4 text-[#202C27] shadow-sm"><p>Pedido confirmado, João! ✅</p><p class="mt-1">Sua camisa já está separada para retirada hoje até às 18h.</p><p class="mt-1 text-right text-[7px] text-[#66705F]">09:43</p></div>
                  <div id="storeConfirmation" class="store-confirmation mx-auto mt-5 flex w-fit items-center gap-2 rounded-full bg-[#075E54] px-4 py-2 text-[8px] font-bold text-white shadow-lg"><span class="material-symbols-outlined text-[15px]">check_circle</span>Venda confirmada</div>
                </div>
                <div class="flex h-14 items-center gap-2 bg-[#F0F2F5] px-2"><div class="flex h-10 flex-1 items-center gap-2 rounded-full bg-white px-3 text-[#667781]"><span class="material-symbols-outlined text-[18px]">mood</span><span class="text-[9px]">Mensagem</span><span class="material-symbols-outlined ml-auto text-[18px]">attach_file</span></div><span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#00A884] text-white"><span class="material-symbols-outlined text-[19px]">mic</span></span></div>
              </div>

              <svg id="storeDemoCursor" class="store-demo-cursor" style="left:78%;top:58%" viewBox="0 0 24 28" fill="none" aria-hidden="true"><path d="M3 2.5V21.5L8.4 16.2L12.1 25.5L16.2 23.8L12.5 15H20L3 2.5Z" fill="white" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
            </div>
          </div>
          <div class="mx-auto mt-5 max-w-[430px] rounded-lg border border-[#DDE6D4] bg-white px-4 py-3 text-center"><p class="text-[11px] font-semibold text-ink">Da descoberta do produto à venda confirmada.</p><p class="mt-1 text-[10px] text-muted">Compra simulada na vitrine real da GearUP com atendimento no WhatsApp.</p></div>
        </div>
      </div>
    </section>

    <!-- Demonstração por abas -->
    <section id="demonstracao" class="dark-grid overflow-hidden bg-navy py-24 text-white sm:py-28">
      <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
        <div class="grid gap-8 lg:grid-cols-[1fr_.7fr] lg:items-end">
          <div class="reveal" style="--reveal-delay: 0ms;">
            <span class="font-mono text-[11px] uppercase tracking-[.17em] text-[#67DCCE]">O sistema na prática</span>
            <h2 class="mt-5 max-w-3xl font-display text-3xl font-bold tracking-[-.035em] sm:text-[44px]">Da entrada do produto à vitrine, veja como os módulos se conectam.</h2>
          </div>
          <p class="reveal text-base leading-7 text-blue-100/65" style="--reveal-delay: 120ms;">A demonstração percorre os módulos automaticamente para mostrar o funcionamento do sistema. Se preferir, você também pode navegar pelas abas.</p>
        </div>

        <div class="mt-12 flex gap-2 overflow-x-auto pb-3" role="tablist" aria-label="Módulos do sistema">
          <button class="module-tab whitespace-nowrap rounded-md border border-white/15 bg-white/5 px-4 py-3 text-sm font-medium text-blue-100/70 transition" type="button" role="tab" aria-selected="true" aria-controls="panel-vitrine" id="tab-vitrine" data-panel="vitrine">Vitrine online</button>
          <button class="module-tab whitespace-nowrap rounded-md border border-white/15 bg-white/5 px-4 py-3 text-sm font-medium text-blue-100/70 transition" type="button" role="tab" aria-selected="false" aria-controls="panel-estoque" id="tab-estoque" data-panel="estoque">Estoque</button>
          <button class="module-tab whitespace-nowrap rounded-md border border-white/15 bg-white/5 px-4 py-3 text-sm font-medium text-blue-100/70 transition" type="button" role="tab" aria-selected="false" aria-controls="panel-pedidos" id="tab-pedidos" data-panel="pedidos">Pedidos</button>
          <button class="module-tab whitespace-nowrap rounded-md border border-white/15 bg-white/5 px-4 py-3 text-sm font-medium text-blue-100/70 transition" type="button" role="tab" aria-selected="false" aria-controls="panel-relatorios" id="tab-relatorios" data-panel="relatorios">Relatórios</button>
          <button class="module-tab whitespace-nowrap rounded-md border border-white/15 bg-white/5 px-4 py-3 text-sm font-medium text-blue-100/70 transition" type="button" role="tab" aria-selected="false" aria-controls="panel-equipe" id="tab-equipe" data-panel="equipe">Equipe</button>
          <button class="module-tab whitespace-nowrap rounded-md border border-white/15 bg-white/5 px-4 py-3 text-sm font-medium text-blue-100/70 transition" type="button" role="tab" aria-selected="false" aria-controls="panel-financeiro" id="tab-financeiro" data-panel="financeiro">Financeiro</button>
        </div>

        <div class="mt-2 flex items-center gap-4">
          <div class="demo-progress-track h-1 flex-1 overflow-hidden rounded-full"><div id="moduleDemoProgress" class="demo-progress-bar h-full rounded-full bg-[#67DCCE]"></div></div>
          <button id="moduleDemoToggle" class="flex shrink-0 items-center gap-1.5 text-[11px] font-semibold text-blue-100/60 transition hover:text-white" type="button" aria-pressed="false"><span class="material-symbols-outlined text-[17px]">pause</span><span>Pausar demo</span></button>
        </div>

        <div class="module-stage mt-5 overflow-hidden rounded-xl border border-white/10 bg-[#09245A]/90 shadow-2xl">
          <div class="module-panel grid gap-0 lg:grid-cols-[.72fr_1.28fr]" id="panel-estoque" role="tabpanel" aria-labelledby="tab-estoque" hidden>
            <div class="flex flex-col justify-center p-7 sm:p-10 lg:p-12"><span class="font-mono text-[10px] uppercase tracking-[.16em] text-[#67DCCE]">Módulo 01</span><h3 class="mt-4 font-display text-2xl font-bold">Estoque sempre atualizado</h3><p class="mt-4 text-sm leading-7 text-blue-100/65">Acompanhe quantidades, produtos críticos e valor armazenado em uma visão objetiva.</p><ul class="mt-7 space-y-3 text-sm text-blue-100/75"><li class="flex gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Alertas de estoque mínimo</li><li class="flex gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Cadastro com foto e código</li><li class="flex gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Entradas e saídas registradas</li></ul></div>
            <div class="system-preview bg-[#F4F6FB] p-5 text-ink sm:p-8"><div class="mb-4 flex items-center justify-between"><div><p class="text-lg font-bold">Produtos</p><p class="mt-1 text-xs text-muted">Gerencie os itens do seu catálogo</p></div><span class="rounded-full bg-brand px-3 py-2 text-[10px] font-semibold text-white">+ Novo produto</span></div><div class="grid grid-cols-3 gap-2"><div class="system-preview-kpi p-3"><p class="text-[8px] text-muted">Cadastrados</p><p class="mt-2 text-lg font-bold">247</p></div><div class="system-preview-kpi p-3" style="--kpi-color:#F59E0B"><p class="text-[8px] text-muted">Estoque baixo</p><p class="mt-2 text-lg font-bold">12</p></div><div class="system-preview-kpi p-3" style="--kpi-color:#EF4444"><p class="text-[8px] text-muted">Zerados</p><p class="mt-2 text-lg font-bold">3</p></div></div><div class="system-preview-card mt-3 p-4"><div class="flex items-center justify-between pb-2"><span class="text-[10px] font-bold">Catálogo de produtos</span><span class="text-[8px] text-brand">Todos · Baixo · Zerado</span></div><div class="system-preview-table-row grid-cols-[1fr_auto_auto] text-[10px]"><span class="font-semibold">Whey Protein 900g</span><span class="text-muted">142 un.</span><span class="rounded bg-cyan-soft px-2 py-1 text-[8px] font-semibold text-[#087F75]">OK</span></div><div class="system-preview-table-row grid-cols-[1fr_auto_auto] text-[10px]"><span class="font-semibold">Barra de Proteína</span><span class="text-muted">0 un.</span><span class="rounded bg-red-50 px-2 py-1 text-[8px] font-semibold text-red-600">ZERADO</span></div><div class="system-preview-table-row grid-cols-[1fr_auto_auto] text-[10px]"><span class="font-semibold">Vitamina C 1000mg</span><span class="text-muted">8 un.</span><span class="rounded bg-amber-50 px-2 py-1 text-[8px] font-semibold text-amber-700">BAIXO</span></div></div></div>
          </div>

          <div class="module-panel grid gap-0 lg:grid-cols-[.72fr_1.28fr]" id="panel-pedidos" role="tabpanel" aria-labelledby="tab-pedidos" hidden>
            <div class="flex flex-col justify-center p-7 sm:p-10 lg:p-12"><span class="font-mono text-[10px] uppercase tracking-[.16em] text-[#67DCCE]">Módulo 02</span><h3 class="mt-4 font-display text-2xl font-bold">Pedidos do início à entrega</h3><p class="mt-4 text-sm leading-7 text-blue-100/65">Visualize os pedidos, atualize cada etapa e mantenha cliente, itens e total no mesmo histórico.</p><ul class="mt-7 space-y-3 text-sm text-blue-100/75"><li class="flex gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Filtros rápidos por status</li><li class="flex gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Itens e total do pedido</li><li class="flex gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Atualização do andamento</li></ul></div>
            <div class="system-preview bg-[#F4F6FB] p-5 text-ink sm:p-8"><div class="mb-4 flex items-center justify-between"><div><p class="text-lg font-bold">Pedidos</p><p class="mt-1 text-xs text-muted">Acompanhe vendas e entregas</p></div><span class="rounded-full bg-brand px-3 py-2 text-[10px] font-semibold text-white">+ Novo pedido</span></div><div class="grid grid-cols-3 gap-2"><div class="system-preview-kpi p-3"><p class="text-[8px] text-muted">Hoje</p><p class="mt-2 text-lg font-bold">8</p></div><div class="system-preview-kpi p-3" style="--kpi-color:#F59E0B"><p class="text-[8px] text-muted">Em preparo</p><p class="mt-2 text-lg font-bold">3</p></div><div class="system-preview-kpi p-3" style="--kpi-color:#10B981"><p class="text-[8px] text-muted">Concluídos</p><p class="mt-2 text-lg font-bold">5</p></div></div><div class="system-preview-card mt-3 p-4"><div class="mb-2 flex gap-2 text-[8px] font-semibold"><span class="rounded-full bg-brand/10 px-2 py-1 text-brand">Todos</span><span class="rounded-full border border-line px-2 py-1 text-muted">Novos</span><span class="rounded-full border border-line px-2 py-1 text-muted">Em preparo</span></div><div class="system-preview-table-row grid-cols-[auto_1fr_auto] text-[10px]"><span class="font-bold text-brand">#1048</span><span><strong class="block">Marina Costa</strong><small class="text-muted">2 itens · WhatsApp</small></span><span class="rounded bg-amber-50 px-2 py-1 text-[8px] font-semibold text-amber-700">EM PREPARO</span></div><div class="system-preview-table-row grid-cols-[auto_1fr_auto] text-[10px]"><span class="font-bold text-brand">#1047</span><span><strong class="block">Carlos Lima</strong><small class="text-muted">1 item · Retirada</small></span><span class="rounded bg-cyan-soft px-2 py-1 text-[8px] font-semibold text-[#087F75]">CONCLUÍDO</span></div></div></div>
          </div>

          <div class="module-panel grid gap-0 lg:grid-cols-[.72fr_1.28fr]" id="panel-relatorios" role="tabpanel" aria-labelledby="tab-relatorios" hidden>
            <div class="flex flex-col justify-center p-7 sm:p-10 lg:p-12"><span class="font-mono text-[10px] uppercase tracking-[.16em] text-[#67DCCE]">Módulo 02</span><h3 class="mt-4 font-display text-2xl font-bold">Relatórios para decidir melhor</h3><p class="mt-4 text-sm leading-7 text-blue-100/65">Consulte períodos, produtos mais vendidos e o desempenho da operação sem montar fórmulas.</p><ul class="mt-7 space-y-3 text-sm text-blue-100/75"><li class="flex gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Filtros por período</li><li class="flex gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Produtos mais vendidos</li><li class="flex gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Exportação de dados</li></ul></div>
            <div class="system-preview bg-[#F4F6FB] p-5 text-ink sm:p-8"><div class="mb-4 flex items-center justify-between"><div><p class="text-lg font-bold">Relatórios</p><p class="mt-1 text-xs text-muted">Analise o desempenho do negócio</p></div><div class="rounded-md border border-line bg-white px-3 py-2 text-[9px] text-muted">Últimos 7 dias</div></div><div class="grid grid-cols-3 gap-2"><div class="system-preview-kpi p-3"><p class="text-[8px] text-muted">Receita</p><p class="mt-2 text-sm font-bold">R$ 8.420</p></div><div class="system-preview-kpi p-3" style="--kpi-color:#10B981"><p class="text-[8px] text-muted">Vendas</p><p class="mt-2 text-sm font-bold">64</p></div><div class="system-preview-kpi p-3" style="--kpi-color:#7C3AED"><p class="text-[8px] text-muted">Ticket médio</p><p class="mt-2 text-sm font-bold">R$ 131</p></div></div><div class="system-preview-card mt-3 p-5"><div class="flex items-center justify-between"><p class="text-xs font-semibold">Desempenho de vendas</p><span class="text-[8px] text-muted">Receita diária</span></div><div class="mt-7 flex h-32 items-end gap-3"><span class="h-[38%] flex-1 rounded-t bg-brand/25"></span><span class="h-[58%] flex-1 rounded-t bg-brand/40"></span><span class="h-[46%] flex-1 rounded-t bg-brand/30"></span><span class="h-[78%] flex-1 rounded-t bg-brand/65"></span><span class="h-[66%] flex-1 rounded-t bg-brand/50"></span><span class="h-[90%] flex-1 rounded-t bg-brand"></span><span class="h-[72%] flex-1 rounded-t bg-cyan"></span></div></div></div>
          </div>

          <div class="module-panel grid gap-0 lg:grid-cols-[.72fr_1.28fr]" id="panel-equipe" role="tabpanel" aria-labelledby="tab-equipe" hidden>
            <div class="flex flex-col justify-center p-7 sm:p-10 lg:p-12"><span class="font-mono text-[10px] uppercase tracking-[.16em] text-[#67DCCE]">Módulo 03</span><h3 class="mt-4 font-display text-2xl font-bold">Equipe organizada</h3><p class="mt-4 text-sm leading-7 text-blue-100/65">Cadastre vendedores, distribua produtos e acompanhe o que cada pessoa movimenta.</p><ul class="mt-7 space-y-3 text-sm text-blue-100/75"><li class="flex gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Produtos por vendedor</li><li class="flex gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Desempenho individual</li><li class="flex gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Comissões organizadas</li></ul></div>
            <div class="system-preview bg-[#F4F6FB] p-5 text-ink sm:p-8"><div class="mb-4 flex items-center justify-between"><div><p class="text-lg font-bold">Vendedores</p><p class="mt-1 text-xs text-muted">Equipe e produtos alocados</p></div><span class="rounded-full bg-brand px-3 py-2 text-[10px] font-semibold text-white">+ Vendedor</span></div><div class="grid grid-cols-3 gap-2"><div class="system-preview-kpi p-3"><p class="text-[8px] text-muted">Ativos</p><p class="mt-2 text-lg font-bold">5</p></div><div class="system-preview-kpi p-3" style="--kpi-color:#10B981"><p class="text-[8px] text-muted">Vendas</p><p class="mt-2 text-lg font-bold">38</p></div><div class="system-preview-kpi p-3" style="--kpi-color:#7C3AED"><p class="text-[8px] text-muted">Alocados</p><p class="mt-2 text-lg font-bold">83</p></div></div><div class="mt-3 space-y-2"><div class="system-preview-card flex items-center justify-between p-4"><div class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand/10 text-xs font-bold text-brand">JS</span><div><p class="text-xs font-semibold">João Silva</p><p class="mt-1 text-[9px] text-muted">18 produtos · 12 vendas</p></div></div><span class="rounded bg-cyan-soft px-2 py-1 text-[8px] font-bold text-[#087F75]">ATIVO</span></div><div class="system-preview-card flex items-center justify-between p-4"><div class="flex items-center gap-3"><span class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand/10 text-xs font-bold text-brand">MA</span><div><p class="text-xs font-semibold">Maria Andrade</p><p class="mt-1 text-[9px] text-muted">22 produtos · 16 vendas</p></div></div><span class="rounded bg-cyan-soft px-2 py-1 text-[8px] font-bold text-[#087F75]">ATIVO</span></div></div></div>
          </div>

          <div class="module-panel grid gap-0 lg:grid-cols-[.72fr_1.28fr]" id="panel-financeiro" role="tabpanel" aria-labelledby="tab-financeiro" hidden>
            <div class="flex flex-col justify-center p-7 sm:p-10 lg:p-12"><span class="font-mono text-[10px] uppercase tracking-[.16em] text-[#67DCCE]">Módulo 04</span><h3 class="mt-4 font-display text-2xl font-bold">Financeiro no contexto da operação</h3><p class="mt-4 text-sm leading-7 text-blue-100/65">Acompanhe entradas, despesas e resultado do período junto aos dados das vendas.</p><ul class="mt-7 space-y-3 text-sm text-blue-100/75"><li class="flex gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Fluxo de caixa</li><li class="flex gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Contas a pagar e receber</li><li class="flex gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Visão do resultado mensal</li></ul></div>
            <div class="system-preview bg-[#F4F6FB] p-5 text-ink sm:p-8"><div class="mb-4 flex items-center justify-between"><div><p class="text-lg font-bold">Financeiro</p><p class="mt-1 text-xs text-muted">Fluxo de caixa da operação</p></div><span class="rounded-full bg-brand px-3 py-2 text-[10px] font-semibold text-white">+ Lançamento</span></div><div class="grid grid-cols-2 gap-3"><div class="system-preview-kpi p-5" style="--kpi-color:#10B981"><p class="text-[10px] text-muted">Entradas</p><p class="mt-2 text-xl font-bold text-[#10B981]">R$ 7.840</p></div><div class="system-preview-kpi p-5" style="--kpi-color:#EF4444"><p class="text-[10px] text-muted">Saídas</p><p class="mt-2 text-xl font-bold text-[#EF4444]">R$ 3.610</p></div><div class="system-preview-card col-span-2 p-5"><div class="flex items-center justify-between"><div><p class="text-xs font-semibold">Saldo do período</p><p class="mt-1 text-[9px] text-muted">Resultado entre entradas e saídas</p></div><span class="material-symbols-outlined text-cyan">trending_up</span></div><p class="mt-5 text-3xl font-bold">R$ 4.230</p><div class="mt-4 h-1.5 overflow-hidden rounded bg-line"><div class="h-full w-[68%] rounded bg-cyan"></div></div></div></div></div>
          </div>

          <div class="module-panel grid gap-0 lg:grid-cols-[.72fr_1.28fr]" id="panel-vitrine" role="tabpanel" aria-labelledby="tab-vitrine">
            <div class="flex flex-col justify-center p-7 sm:p-10 lg:p-12"><span class="font-mono text-[10px] uppercase tracking-[.16em] text-[#67DCCE]">Diferencial Uplar</span><h3 class="mt-4 font-display text-2xl font-bold">Vitrine conectada ao estoque</h3><p class="mt-4 text-sm leading-7 text-blue-100/65">O produto cadastrado fica pronto para ser apresentado em uma loja online com busca, categorias e pedido pelo WhatsApp.</p><ul class="mt-7 space-y-3 text-sm text-blue-100/75"><li class="flex gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Catálogo com identidade própria</li><li class="flex gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Disponibilidade ligada ao estoque</li><li class="flex gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Pedido direto no WhatsApp</li></ul><a href="https://uplar.cloud/gearup" target="_blank" rel="noopener" class="mt-7 inline-flex items-center gap-2 text-xs font-semibold text-[#67DCCE]">Ver exemplo real da GearUP <span class="material-symbols-outlined text-[17px]">open_in_new</span></a></div>
            <div class="system-preview bg-[#F4F6FB] p-5 text-ink sm:p-8"><div class="mx-auto max-w-md overflow-hidden rounded-xl border border-line bg-white shadow-card"><div class="flex h-12 items-center justify-between border-b border-line px-4"><div class="flex items-center gap-2"><img class="h-7 w-7 rounded-md border border-line bg-white object-contain p-0.5" src="imagens/vitrine/logo_9_1783613263_071f51fa.png" alt="Logo da GearUP Sports"><p class="text-xs font-bold">GearUP Sports</p></div><span class="material-symbols-outlined text-lg text-[#48602A]">shopping_cart</span></div><img class="h-20 w-full bg-[#101510] object-cover" src="imagens/vitrine/banner_9_1783382746_81fc814f.png" alt="Banner da GearUP Sports"><div class="p-4"><div class="flex h-9 items-center gap-2 rounded-md border border-line bg-canvas px-3 text-[9px] text-muted"><span class="material-symbols-outlined text-[15px]">search</span>Buscar produto...</div><div class="mt-3 flex gap-2"><span class="rounded-full bg-[#48602A] px-3 py-1 text-[8px] font-semibold text-white">Todos</span><span class="rounded-full bg-[#EFF3E9] px-3 py-1 text-[8px] font-semibold text-[#48602A]">Camisas</span><span class="rounded-full bg-[#EFF3E9] px-3 py-1 text-[8px] font-semibold text-[#48602A]">Bermudas</span></div><div class="mt-4 grid grid-cols-2 gap-3"><div class="rounded border border-line p-3"><img class="h-16 w-full rounded bg-[#EEF1EA] object-cover" src="imagens/img_6a49a2e3ab2b96.54449542.jpeg" alt="Camisa NIKE da GearUP"><p class="mt-3 text-[10px] font-semibold">Camisa NIKE</p><p class="mt-1 text-xs font-bold text-[#48602A]">R$ 50,00</p></div><div class="rounded border border-line p-3"><img class="h-16 w-full rounded bg-[#EEF1EA] object-cover" src="imagens/img_6a499df719ddf5.20480161.jpeg" alt="Bermuda NIKE da GearUP"><p class="mt-3 text-[10px] font-semibold">Bermuda NIKE</p><p class="mt-1 text-xs font-bold text-[#48602A]">R$ 75,00</p></div></div></div></div></div>
          </div>
        </div>

        <div class="mt-10 text-center"><a href="https://estoque.uplar.cloud/planos" class="btn-primary px-7">Quero controlar minha operação<span class="material-symbols-outlined text-[19px]">arrow_forward</span></a><p class="mt-3 text-xs text-blue-100/45">Escolha entre as opções disponíveis e comece pela web.</p></div>
      </div>
    </section>

    <!-- Segurança -->
    <section class="border-y border-line bg-canvas py-20">
      <div class="mx-auto grid max-w-7xl items-center gap-10 px-5 sm:px-8 lg:grid-cols-[.85fr_1.15fr] lg:px-10">
        <div class="reveal rounded-xl border border-line bg-white p-7 shadow-card sm:p-9" style="--reveal-delay: 0ms;">
          <div class="flex items-center gap-4 border-b border-line pb-6"><span class="flex h-12 w-12 items-center justify-center rounded-lg bg-cyan-soft text-cyan"><span class="material-symbols-outlined">shield_lock</span></span><div><p class="font-display text-lg font-bold text-navy">Acesso protegido</p><p class="mt-1 font-mono text-[9px] uppercase tracking-[.13em] text-[#087F75]">Segurança ativa</p></div></div>
          <div class="mt-6 grid gap-4 sm:grid-cols-2"><div class="flex gap-3"><span class="material-symbols-outlined text-xl text-brand">password</span><div><p class="text-xs font-semibold text-ink">Senhas protegidas</p><p class="mt-1 text-[11px] leading-5 text-muted">Armazenamento seguro de credenciais.</p></div></div><div class="flex gap-3"><span class="material-symbols-outlined text-xl text-brand">verified_user</span><div><p class="text-xs font-semibold text-ink">Sessões verificadas</p><p class="mt-1 text-[11px] leading-5 text-muted">Validação das solicitações de acesso.</p></div></div><div class="flex gap-3"><span class="material-symbols-outlined text-xl text-brand">history</span><div><p class="text-xs font-semibold text-ink">Registro de atividades</p><p class="mt-1 text-[11px] leading-5 text-muted">Ações importantes ficam registradas.</p></div></div><div class="flex gap-3"><span class="material-symbols-outlined text-xl text-brand">lock</span><div><p class="text-xs font-semibold text-ink">Conexão HTTPS</p><p class="mt-1 text-[11px] leading-5 text-muted">Comunicação protegida durante o uso.</p></div></div></div>
        </div>
        <div class="reveal" style="--reveal-delay: 140ms;">
          <span class="section-label">Confiança operacional</span>
          <h2 class="mt-5 font-display text-3xl font-bold tracking-[-.035em] text-navy sm:text-[40px]">Seus dados operacionais protegidos desde o acesso.</h2>
          <p class="mt-5 max-w-2xl text-base leading-7 text-muted">Segurança não precisa parecer complicada. A Uplar aplica controles importantes nos bastidores para que você possa focar na operação.</p>
        </div>
      </div>
    </section>

    <!-- CTA de planos -->
    <section id="planos" class="bg-white py-24 sm:py-28">
      <div class="mx-auto max-w-6xl px-5 sm:px-8 lg:px-10">
        <div class="reveal relative overflow-hidden rounded-2xl bg-navy px-7 py-10 text-white shadow-product sm:px-12 sm:py-14 lg:px-16">
          <div class="dark-grid pointer-events-none absolute inset-0 opacity-70"></div>
          <div class="pointer-events-none absolute -right-32 -top-32 h-96 w-96 rounded-full bg-brand/30 blur-3xl"></div>
          <div class="relative grid items-center gap-10 lg:grid-cols-[1fr_auto]">
            <div>
              <span class="font-mono text-[10px] uppercase tracking-[.17em] text-[#67DCCE]">Planos flexíveis</span>
              <h2 class="mt-5 max-w-2xl font-display text-3xl font-bold tracking-[-.035em] sm:text-[42px]">Comece com o período que faz sentido para o seu negócio.</h2>
              <p class="mt-5 max-w-xl text-base leading-7 text-blue-100/65">Compare as opções disponíveis, escolha seu plano e tenha acesso à plataforma pela web.</p>
              <div class="mt-7 flex flex-wrap gap-x-6 gap-y-3 text-xs text-blue-100/65"><span class="flex items-center gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Sem instalação</span><span class="flex items-center gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Renovação acessível</span><span class="flex items-center gap-2"><span class="material-symbols-outlined text-lg text-[#67DCCE]">check_circle</span>Suporte direto</span></div>
            </div>
            <div class="flex min-w-[230px] flex-col gap-3"><a href="https://estoque.uplar.cloud/planos" class="btn-primary px-7 py-4 text-[15px]">Ver planos e preços<span class="material-symbols-outlined text-[19px]">arrow_forward</span></a><a href="https://wa.me/5592994384972?text=Ol%C3%A1!%20Tenho%20d%C3%BAvidas%20sobre%20os%20planos%20do%20Uplar%20Estoque." target="_blank" rel="noopener" class="btn-dark px-7 py-4 text-[15px]">Tirar dúvidas</a><p class="text-center text-[10px] text-blue-100/45">Você verá os valores antes de contratar.</p></div>
          </div>
        </div>
      </div>
    </section>

    <!-- FAQ -->
    <section id="duvidas" class="border-t border-line bg-canvas py-24 sm:py-28">
      <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[.7fr_1.3fr] lg:px-10">
        <div class="reveal">
          <span class="section-label">Dúvidas frequentes</span>
          <h2 class="mt-5 font-display text-3xl font-bold tracking-[-.035em] text-navy sm:text-[40px]">Informação clara antes de começar.</h2>
          <p class="mt-5 text-base leading-7 text-muted">Ainda precisa confirmar algum detalhe? Fale diretamente com a equipe pelo WhatsApp.</p>
          <a href="https://wa.me/5592994384972?text=Ol%C3%A1!%20Quero%20tirar%20uma%20d%C3%BAvida%20sobre%20o%20Uplar%20Estoque." target="_blank" rel="noopener" class="mt-7 inline-flex items-center gap-2 text-sm font-semibold text-[#139D55]">Falar com atendimento <span class="material-symbols-outlined text-lg">open_in_new</span></a>
        </div>

        <div class="divide-y divide-line border-y border-line">
          <article class="faq-item is-open reveal">
            <button class="faq-button flex w-full items-center justify-between gap-5 py-6 text-left" type="button" aria-expanded="true"><span class="font-display text-base font-bold text-navy sm:text-lg">Preciso instalar alguma coisa?</span><span class="faq-icon material-symbols-outlined text-brand">add</span></button>
            <div class="faq-answer"><div><p class="pb-6 pr-10 text-sm leading-7 text-muted">Não. O Uplar Estoque funciona pela web e pode ser acessado pelo computador ou celular.</p></div></div>
          </article>
          <article class="faq-item reveal">
            <button class="faq-button flex w-full items-center justify-between gap-5 py-6 text-left" type="button" aria-expanded="false"><span class="font-display text-base font-bold text-navy sm:text-lg">Consigo cadastrar vendedores?</span><span class="faq-icon material-symbols-outlined text-brand">add</span></button>
            <div class="faq-answer"><div><p class="pb-6 pr-10 text-sm leading-7 text-muted">Sim. Você pode organizar a equipe, alocar produtos e acompanhar as movimentações de cada vendedor.</p></div></div>
          </article>
          <article class="faq-item reveal">
            <button class="faq-button flex w-full items-center justify-between gap-5 py-6 text-left" type="button" aria-expanded="false"><span class="font-display text-base font-bold text-navy sm:text-lg">Como recebo pedidos da vitrine?</span><span class="faq-icon material-symbols-outlined text-brand">add</span></button>
            <div class="faq-answer"><div><p class="pb-6 pr-10 text-sm leading-7 text-muted">Os produtos são apresentados na vitrine online e o cliente pode direcionar o pedido para o seu WhatsApp.</p></div></div>
          </article>
          <article class="faq-item reveal">
            <button class="faq-button flex w-full items-center justify-between gap-5 py-6 text-left" type="button" aria-expanded="false"><span class="font-display text-base font-bold text-navy sm:text-lg">Como funciona a renovação?</span><span class="faq-icon material-symbols-outlined text-brand">add</span></button>
            <div class="faq-answer"><div><p class="pb-6 pr-10 text-sm leading-7 text-muted">Clientes ativos podem acessar as opções de renovação pelo próprio fluxo da conta.</p></div></div>
          </article>
          <article class="faq-item reveal">
            <button class="faq-button flex w-full items-center justify-between gap-5 py-6 text-left" type="button" aria-expanded="false"><span class="font-display text-base font-bold text-navy sm:text-lg">Existe suporte para começar?</span><span class="faq-icon material-symbols-outlined text-brand">add</span></button>
            <div class="faq-answer"><div><p class="pb-6 pr-10 text-sm leading-7 text-muted">Sim. Você pode falar diretamente com a equipe da Uplar pelo WhatsApp para esclarecer dúvidas sobre os planos e o início do uso.</p></div></div>
          </article>
        </div>
      </div>
    </section>

    <!-- CTA final -->
    <section class="bg-white py-24">
      <div class="mx-auto max-w-4xl px-5 text-center sm:px-8">
        <div class="reveal">
          <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-brand/10 text-brand"><span class="material-symbols-outlined text-3xl">rocket_launch</span></span>
          <h2 class="mt-7 font-display text-3xl font-bold tracking-[-.035em] text-navy sm:text-[44px]">Pronto para organizar sua operação?</h2>
          <p class="mx-auto mt-5 max-w-2xl text-base leading-7 text-muted">Escolha seu plano e comece a controlar estoque, vendas e financeiro em um único sistema.</p>
          <div class="mt-9 flex flex-col justify-center gap-3 sm:flex-row"><a href="https://estoque.uplar.cloud/planos" class="btn-primary px-7 py-4 text-[15px]">Ver planos<span class="material-symbols-outlined text-[19px]">arrow_forward</span></a><a href="https://wa.me/5592994384972?text=Ol%C3%A1!%20Quero%20come%C3%A7ar%20a%20usar%20o%20Uplar%20Estoque." target="_blank" rel="noopener" class="btn-whatsapp px-7 py-4 text-[15px]">Falar no WhatsApp</a></div>
        </div>
      </div>
    </section>
  </main>

  <!-- Rodapé -->
  <footer class="bg-navy py-12 text-blue-100/55">
    <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
      <div class="grid gap-10 border-b border-white/10 pb-10 md:grid-cols-[1fr_auto_auto]">
        <div>
          <a href="https://estoque.uplar.cloud/" class="inline-flex items-center gap-2.5 text-white"><span class="uplar-brand-mark" aria-hidden="true"><img class="uplar-brand-image" src="assets/brand/uplar-mark-64.png" srcset="assets/brand/uplar-mark-128.png 2x, assets/brand/uplar-mark-256.png 4x" width="64" height="39" alt="" decoding="async" onerror="this.hidden=true;this.nextElementSibling.hidden=false"><span class="uplar-brand-fallback" hidden>U</span></span><span class="font-display text-xl font-extrabold">UPLAR</span><span class="h-5 w-px bg-white/20"></span><span class="font-mono text-[10px] uppercase tracking-[.17em] text-blue-100/55">Estoque</span></a>
          <p class="mt-4 max-w-sm text-sm leading-6">Controle de estoque, vendas, equipe e financeiro em uma única plataforma.</p>
        </div>
        <div><p class="mb-4 text-xs font-semibold uppercase tracking-wider text-white">Produto</p><div class="flex flex-col gap-3 text-sm"><a href="#demonstracao" class="transition hover:text-white">O sistema</a><a href="https://estoque.uplar.cloud/planos" class="transition hover:text-white">Planos</a></div></div>
        <div><p class="mb-4 text-xs font-semibold uppercase tracking-wider text-white">Acesso</p><div class="flex flex-col gap-3 text-sm"><a href="https://estoque.uplar.cloud/login" class="transition hover:text-white">Entrar no sistema</a><a href="https://uplar.cloud/" class="transition hover:text-white">Site institucional</a><a href="https://wa.me/5592994384972" target="_blank" rel="noopener" class="transition hover:text-white">WhatsApp</a></div></div>
      </div>
      <div class="flex flex-col gap-3 pt-7 text-xs sm:flex-row sm:items-center sm:justify-between"><p>© 2026 Uplar Estoque. Todos os direitos reservados.</p><p class="font-mono text-[9px] uppercase tracking-[.14em]">Gestão clara. Decisão segura.</p></div>
    </div>
  </footer>

  <!-- CTA fixo mobile -->
  <div class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-white/95 p-3 backdrop-blur md:hidden">
    <div class="grid grid-cols-[.82fr_1.18fr] gap-2"><a href="https://estoque.uplar.cloud/login" class="btn-secondary min-h-[46px] px-3">Entrar</a><a href="https://estoque.uplar.cloud/planos" class="btn-primary min-h-[46px] px-3">Ver planos</a></div>
  </div>

  <script>
    // Menu mobile
    const menuButton = document.getElementById("menuButton");
    const mobileMenu = document.getElementById("mobileMenu");

    if (menuButton && mobileMenu) {
      menuButton.addEventListener("click", function () {
        const isOpen = !mobileMenu.classList.contains("hidden");
        mobileMenu.classList.toggle("hidden");
        menuButton.setAttribute("aria-expanded", String(!isOpen));
        menuButton.setAttribute("aria-label", isOpen ? "Abrir menu" : "Fechar menu");
        menuButton.querySelector(".material-symbols-outlined").textContent = isOpen ? "menu" : "close";
      });

      mobileMenu.querySelectorAll("a").forEach(function (link) {
        link.addEventListener("click", function () {
          mobileMenu.classList.add("hidden");
          menuButton.setAttribute("aria-expanded", "false");
          menuButton.setAttribute("aria-label", "Abrir menu");
          menuButton.querySelector(".material-symbols-outlined").textContent = "menu";
        });
      });
    }

    // Animações discretas de entrada
    const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    const revealElements = document.querySelectorAll(".reveal");

    if (reducedMotion || !("IntersectionObserver" in window)) {
      revealElements.forEach(function (element) { element.classList.add("is-visible"); });
    } else {
      const revealObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            entry.target.classList.add("is-visible");
            revealObserver.unobserve(entry.target);
          }
        });
      }, { threshold: 0.12, rootMargin: "0px 0px -30px 0px" });

      document.documentElement.classList.add("reveal-ready");
      revealElements.forEach(function (element) { revealObserver.observe(element); });
    }

    const sleep = function (milliseconds) {
      return new Promise(function (resolve) { window.setTimeout(resolve, milliseconds); });
    };

    async function typeDemoText(element, text, speed, canContinue) {
      element.textContent = "";
      for (let index = 0; index < text.length; index += 1) {
        if (canContinue && !canContinue()) return false;
        element.textContent += text[index];
        await sleep(speed);
      }
      return true;
    }

    // Tour rápido pelo aplicativo no hero
    const heroDemo = document.getElementById("heroDemo");
    const heroScenes = Array.from(document.querySelectorAll("[data-hero-scene]"));
    const heroNavItems = Array.from(document.querySelectorAll("[data-hero-target]"));
    const heroBrowserPath = document.getElementById("heroBrowserPath");
    const heroTourProgress = document.getElementById("heroTourProgress");
    const heroTourOrder = ["relatorios", "vitrine", "produtos", "pedidos", "vendedores", "dashboard"];
    const heroTourDuration = 3600;
    let heroDemoVisible = false;
    let heroDemoLoopRunning = false;
    let heroTourIndex = 0;
    let heroManualHoldUntil = 0;

    function restartHeroTourProgress() {
      if (!heroTourProgress) return;
      heroTourProgress.classList.remove("is-running");
      void heroTourProgress.offsetWidth;
      if (heroDemoVisible && !reducedMotion) heroTourProgress.classList.add("is-running");
    }

    function activateHeroScene(name) {
      heroScenes.forEach(function (scene) {
        const active = scene.dataset.heroScene === name;
        scene.classList.toggle("is-active", active);
        scene.setAttribute("aria-hidden", String(!active));
      });
      heroNavItems.forEach(function (item) {
        const active = item.dataset.heroTarget === name;
        item.classList.toggle("is-active", active);
        item.setAttribute("aria-pressed", String(active));
      });
      if (heroBrowserPath) heroBrowserPath.textContent = "estoque.uplar.cloud / " + name;
      restartHeroTourProgress();
    }

    async function runHeroDemoLoop() {
      if (heroDemoLoopRunning || reducedMotion || !heroDemo) return;
      heroDemoLoopRunning = true;

      while (heroDemoVisible && !document.hidden) {
        const sceneName = heroTourOrder[heroTourIndex];
        activateHeroScene(sceneName);
        await sleep(heroTourDuration);
        if (!heroDemoVisible) break;
        if (Date.now() < heroManualHoldUntil) {
          await sleep(heroManualHoldUntil - Date.now());
          if (!heroDemoVisible) break;
        }
        heroTourIndex = (heroTourIndex + 1) % heroTourOrder.length;
      }

      heroDemoLoopRunning = false;
    }

    heroNavItems.forEach(function (item) {
      item.addEventListener("click", function () {
        const target = item.dataset.heroTarget;
        const targetIndex = heroTourOrder.indexOf(target);
        if (targetIndex >= 0) heroTourIndex = targetIndex;
        heroManualHoldUntil = Date.now() + heroTourDuration;
        activateHeroScene(target);
      });
    });

    if (heroDemo) {
      if (reducedMotion || !("IntersectionObserver" in window)) {
        activateHeroScene("relatorios");
      } else {
        const heroDemoObserver = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            heroDemoVisible = entry.isIntersecting && entry.intersectionRatio >= 0.38;

            if (heroDemoVisible) {
              activateHeroScene(heroTourOrder[heroTourIndex]);
              runHeroDemoLoop();
            } else if (heroTourProgress) {
              heroTourProgress.classList.remove("is-running");
            }
          });
        }, { threshold: [0, 0.38, 0.6], rootMargin: "0px 0px -4% 0px" });
        heroDemoObserver.observe(heroDemo);
      }
    }

    // Demonstração da vitrine online
    const storeDemo = document.getElementById("storeDemo");
    const storeDemoToggle = document.getElementById("storeDemoToggle");
    const storeDemoStatus = document.getElementById("storeDemoStatus");
    const storeSearchText = document.getElementById("storeSearchText");
    const storeCursor = document.getElementById("storeDemoCursor");
    const storeScreen = storeDemo ? storeDemo.querySelector(".store-screen") : null;
    const storeCatalogScreen = document.getElementById("storeCatalogScreen");
    const storeSearchField = document.getElementById("storeSearchField");
    const storeCartButton = document.getElementById("storeCartButton");
    const storeCartCount = document.getElementById("storeCartCount");
    const storeProductCount = document.getElementById("storeProductCount");
    const storeAddProduct = document.getElementById("storeAddProduct");
    const storeCheckout = document.getElementById("storeCheckout");
    const storeWhatsappButton = document.getElementById("storeWhatsappButton");
    const storeWhatsapp = document.getElementById("storeWhatsapp");
    const storeCustomerMessage = document.getElementById("storeCustomerMessage");
    const storeSellerTyping = document.getElementById("storeSellerTyping");
    const storeSellerMessage = document.getElementById("storeSellerMessage");
    const storeConfirmation = document.getElementById("storeConfirmation");
    const storeChips = Array.from(document.querySelectorAll("[data-store-chip]"));
    const storeProducts = Array.from(document.querySelectorAll(".store-product"));
    const storeAddButtons = Array.from(document.querySelectorAll(".store-add"));
    let storeDemoVisible = false;
    let storeDemoPaused = false;
    let storeDemoLoopRunning = false;
    let storeDemoRunId = 0;

    function moveStoreCursorTo(target, xRatio, yRatio) {
      if (!storeCursor || !storeScreen || !target) return;
      const screenRect = storeScreen.getBoundingClientRect();
      const targetRect = target.getBoundingClientRect();
      const x = typeof xRatio === "number" ? xRatio : .5;
      const y = typeof yRatio === "number" ? yRatio : .5;
      storeCursor.classList.remove("is-hidden");
      storeCursor.style.left = (targetRect.left - screenRect.left + targetRect.width * x - 3) + "px";
      storeCursor.style.top = (targetRect.top - screenRect.top + targetRect.height * y - 3) + "px";
    }

    async function clickStoreCursor() {
      if (!storeCursor) return;
      storeCursor.classList.add("is-clicking");
      await sleep(180);
      storeCursor.classList.remove("is-clicking");
    }

    function resetStoreDemo() {
      if (!storeDemo) return;
      storeSearchText.textContent = "";
      storeCartCount.classList.remove("has-item");
      storeProductCount.textContent = "3 disponíveis";
      storeChips.forEach(function (chip) { chip.classList.toggle("is-active", chip.dataset.storeChip === "todos"); });
      storeProducts.forEach(function (product) { product.classList.remove("is-filtered", "is-focused"); });
      storeAddButtons.forEach(function (button) { button.classList.remove("is-pressing"); });
      storeCatalogScreen.classList.remove("is-leaving");
      storeCheckout.classList.remove("is-open");
      storeCheckout.setAttribute("aria-hidden", "true");
      storeWhatsapp.classList.remove("is-open");
      storeWhatsapp.setAttribute("aria-hidden", "true");
      storeCustomerMessage.classList.remove("is-visible");
      storeSellerTyping.classList.remove("is-visible");
      storeSellerMessage.classList.remove("is-visible");
      storeConfirmation.classList.remove("is-visible");
      storeCursor.classList.remove("is-clicking", "is-hidden");
      moveStoreCursorTo(storeSearchField, .78, .52);
      storeDemoStatus.textContent = "Demonstração automática";
    }

    function storeCanContinue(runId) {
      return runId === storeDemoRunId && storeDemoVisible && !storeDemoPaused && !document.hidden;
    }

    async function runStoreDemoLoop() {
      if (storeDemoLoopRunning || reducedMotion || !storeDemo) return;
      storeDemoLoopRunning = true;
      const runId = ++storeDemoRunId;

      while (storeCanContinue(runId)) {
        resetStoreDemo();
        await sleep(750);
        if (!storeCanContinue(runId)) break;

        storeDemoStatus.textContent = "Buscando um produto";
        moveStoreCursorTo(storeSearchField, .7, .52);
        await sleep(700);
        if (!storeCanContinue(runId)) break;
        await clickStoreCursor();
        if (!await typeDemoText(storeSearchText, "camisa", 110, function () { return storeCanContinue(runId); })) break;
        storeProducts.forEach(function (product) { product.classList.toggle("is-filtered", product.dataset.category !== "camisas"); });
        storeProductCount.textContent = "1 resultado";
        await sleep(650);
        if (!storeCanContinue(runId)) break;

        storeDemoStatus.textContent = "Filtrando a categoria";
        const shirtsChip = storeChips.find(function (chip) { return chip.dataset.storeChip === "camisas"; });
        moveStoreCursorTo(shirtsChip);
        await sleep(650);
        if (!storeCanContinue(runId)) break;
        await clickStoreCursor();
        storeChips.forEach(function (chip) { chip.classList.toggle("is-active", chip.dataset.storeChip === "camisas"); });
        await sleep(650);
        if (!storeCanContinue(runId)) break;

        storeDemoStatus.textContent = "Adicionando ao pedido";
        storeProducts[0].classList.add("is-focused");
        moveStoreCursorTo(storeAddProduct);
        await sleep(650);
        if (!storeCanContinue(runId)) break;
        await clickStoreCursor();
        storeAddButtons[0].classList.add("is-pressing");
        await sleep(220);
        storeAddButtons[0].classList.remove("is-pressing");
        storeCartCount.classList.add("has-item");
        await sleep(600);
        if (!storeCanContinue(runId)) break;

        storeDemoStatus.textContent = "Abrindo o carrinho";
        moveStoreCursorTo(storeCartButton);
        await sleep(650);
        if (!storeCanContinue(runId)) break;
        await clickStoreCursor();
        storeCheckout.classList.add("is-open");
        storeCheckout.setAttribute("aria-hidden", "false");
        await sleep(650);
        if (!storeCanContinue(runId)) break;

        storeDemoStatus.textContent = "Finalizando no WhatsApp";
        moveStoreCursorTo(storeWhatsappButton, .55, .5);
        await sleep(750);
        if (!storeCanContinue(runId)) break;
        await clickStoreCursor();
        storeCatalogScreen.classList.add("is-leaving");
        storeCheckout.classList.remove("is-open");
        storeCheckout.setAttribute("aria-hidden", "true");
        storeWhatsapp.classList.add("is-open");
        storeWhatsapp.setAttribute("aria-hidden", "false");
        storeCursor.classList.add("is-hidden");
        await sleep(650);
        if (!storeCanContinue(runId)) break;

        storeDemoStatus.textContent = "Pedido enviado para a GearUP";
        storeCustomerMessage.classList.add("is-visible");
        await sleep(950);
        if (!storeCanContinue(runId)) break;
        storeSellerTyping.classList.add("is-visible");
        storeDemoStatus.textContent = "Vendedor respondendo";
        await sleep(1450);
        if (!storeCanContinue(runId)) break;
        storeSellerTyping.classList.remove("is-visible");
        storeSellerMessage.classList.add("is-visible");
        await sleep(650);
        if (!storeCanContinue(runId)) break;
        storeConfirmation.classList.add("is-visible");
        storeDemoStatus.textContent = "Venda confirmada no WhatsApp";
        await sleep(2600);
      }

      if (runId === storeDemoRunId) storeDemoLoopRunning = false;
    }

    function updateStoreToggle() {
      if (!storeDemoToggle) return;
      const icon = storeDemoToggle.querySelector(".material-symbols-outlined");
      const label = storeDemoToggle.querySelector("span:last-child");
      storeDemoToggle.setAttribute("aria-pressed", String(storeDemoPaused));
      icon.textContent = storeDemoPaused ? "play_arrow" : "pause";
      label.textContent = storeDemoPaused ? "Continuar" : "Pausar";
      if (storeDemoPaused) storeDemoStatus.textContent = "Demonstração pausada";
    }

    if (storeDemo) {
      if (!reducedMotion && "IntersectionObserver" in window) {
        const storeDemoObserver = new IntersectionObserver(function (entries) {
          entries.forEach(function (entry) {
            storeDemoVisible = entry.isIntersecting && entry.intersectionRatio >= 0.32;

            if (storeDemoVisible && !storeDemoPaused) {
              resetStoreDemo();
              runStoreDemoLoop();
            } else {
              storeDemoRunId += 1;
              storeDemoLoopRunning = false;
            }
          });
        }, { threshold: [0, 0.32, 0.55], rootMargin: "0px 0px -6% 0px" });
        storeDemoObserver.observe(storeDemo);
      }

      storeDemoToggle.addEventListener("click", function () {
        storeDemoPaused = !storeDemoPaused;
        if (storeDemoPaused) {
          storeDemoRunId += 1;
          storeDemoLoopRunning = false;
        }
        updateStoreToggle();
        if (!storeDemoPaused) {
          resetStoreDemo();
          runStoreDemoLoop();
        }
      });
    }

    // Abas dos módulos com navegação automática
    const moduleTabs = Array.from(document.querySelectorAll(".module-tab"));
    const modulePanels = Array.from(document.querySelectorAll(".module-panel"));
    const moduleLabels = {
      "panel-estoque": "Módulo 01",
      "panel-pedidos": "Módulo 02",
      "panel-relatorios": "Módulo 03",
      "panel-equipe": "Módulo 04",
      "panel-financeiro": "Módulo 05"
    };
    Object.entries(moduleLabels).forEach(function (entry) {
      const label = document.querySelector("#" + entry[0] + " .font-mono");
      if (label) label.textContent = entry[1];
    });
    const orderPreview = document.querySelector("#panel-pedidos .system-preview");
    if (orderPreview) {
      const subtitle = orderPreview.querySelector(".mb-4 p.text-xs");
      if (subtitle) subtitle.textContent = "Acompanhe e gerencie os pedidos da sua vitrine";
      const action = orderPreview.querySelector(".mb-4 > span");
      if (action) {
        action.className = "flex items-center gap-1 rounded-md border border-line bg-white px-3 py-2 text-[9px] text-muted";
        action.innerHTML = '<span class="material-symbols-outlined text-[14px]">search</span>Buscar pedido';
      }
      const kpiLabels = orderPreview.querySelectorAll(".system-preview-kpi p:first-child");
      ["Recentes", "Aguardando ação", "Confirmados"].forEach(function (text, index) {
        if (kpiLabels[index]) kpiLabels[index].textContent = text;
      });
      const statuses = orderPreview.querySelectorAll(".system-preview-table-row > span:last-child");
      if (statuses[0]) statuses[0].textContent = "PENDENTE";
      if (statuses[1]) statuses[1].textContent = "CONFIRMADO";
    }
    const moduleDemoSection = document.getElementById("demonstracao");
    const moduleDemoProgress = document.getElementById("moduleDemoProgress");
    const moduleDemoToggle = document.getElementById("moduleDemoToggle");
    const moduleDemoDuration = 6200;
    let moduleDemoVisible = false;
    let moduleDemoPaused = false;
    let moduleDemoStarted = false;
    let moduleDemoTimer = 0;

    function runModulePanelScene(panel) {
      panel.classList.remove("panel-enter");
      window.requestAnimationFrame(function () {
        panel.classList.add("panel-enter");
      });

      if (panel.id === "panel-relatorios") {
        const bars = panel.querySelectorAll(".flex.h-32 > span, .flex.h-40 > span");
        bars.forEach(function (bar, index) {
          bar.style.transformOrigin = "bottom";
          bar.style.transform = "scaleY(0)";
          bar.style.transition = "transform .65s cubic-bezier(.22,1,.36,1) " + (index * 70) + "ms";
        });
        window.requestAnimationFrame(function () {
          window.requestAnimationFrame(function () {
            bars.forEach(function (bar) { bar.style.transform = "scaleY(1)"; });
          });
        });
      }
    }

    function restartModuleProgress() {
      if (!moduleDemoProgress) return;
      moduleDemoProgress.classList.remove("is-running");
      moduleDemoProgress.style.setProperty("--demo-duration", moduleDemoDuration + "ms");
      void moduleDemoProgress.offsetWidth;
      if (!moduleDemoPaused && moduleDemoVisible && !reducedMotion) moduleDemoProgress.classList.add("is-running");
    }

    function scheduleNextModule() {
      window.clearTimeout(moduleDemoTimer);
      restartModuleProgress();
      if (moduleDemoPaused || !moduleDemoVisible || reducedMotion) return;
      moduleDemoTimer = window.setTimeout(function () {
        const currentIndex = moduleTabs.findIndex(function (tab) { return tab.getAttribute("aria-selected") === "true"; });
        const nextIndex = (currentIndex + 1) % moduleTabs.length;
        activateModule(moduleTabs[nextIndex], false, true);
      }, moduleDemoDuration);
    }

    function activateModule(tab, fromUser, animatePanel) {
      const panelName = tab.dataset.panel;
      let activePanel = null;
      moduleTabs.forEach(function (item) {
        const active = item === tab;
        item.setAttribute("aria-selected", String(active));
        item.tabIndex = active ? 0 : -1;
      });
      modulePanels.forEach(function (panel) {
        panel.hidden = panel.id !== "panel-" + panelName;
        if (!panel.hidden) activePanel = panel;
      });
      if (activePanel && (animatePanel !== false) && (moduleDemoStarted || fromUser)) {
        runModulePanelScene(activePanel);
      }
      if (fromUser) {
        moduleDemoPaused = true;
        updateModuleToggle();
      }
      scheduleNextModule();
    }

    function updateModuleToggle() {
      if (!moduleDemoToggle) return;
      const icon = moduleDemoToggle.querySelector(".material-symbols-outlined");
      const label = moduleDemoToggle.querySelector("span:last-child");
      moduleDemoToggle.setAttribute("aria-pressed", String(moduleDemoPaused));
      icon.textContent = moduleDemoPaused ? "play_arrow" : "pause";
      label.textContent = moduleDemoPaused ? "Retomar demo" : "Pausar demo";
    }

    moduleTabs.forEach(function (tab, index) {
      tab.addEventListener("click", function () { activateModule(tab, true, true); });
      tab.addEventListener("keydown", function (event) {
        if (event.key !== "ArrowLeft" && event.key !== "ArrowRight") return;
        event.preventDefault();
        const direction = event.key === "ArrowRight" ? 1 : -1;
        const nextIndex = (index + direction + moduleTabs.length) % moduleTabs.length;
        moduleTabs[nextIndex].focus();
        activateModule(moduleTabs[nextIndex], true, true);
      });
    });

    if (moduleDemoToggle) {
      moduleDemoToggle.addEventListener("click", function () {
        moduleDemoPaused = !moduleDemoPaused;
        updateModuleToggle();
        scheduleNextModule();
      });
    }

    if (moduleDemoSection && !reducedMotion && "IntersectionObserver" in window) {
      const moduleDemoObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          const nowVisible = entry.isIntersecting && entry.intersectionRatio >= 0.28;
          moduleDemoVisible = nowVisible;

          if (nowVisible) {
            if (!moduleDemoStarted) {
              moduleDemoStarted = true;
              const activeTab = moduleTabs.find(function (tab) {
                return tab.getAttribute("aria-selected") === "true";
              }) || moduleTabs[0];

              if (activeTab) activateModule(activeTab, false, true);
            } else {
              scheduleNextModule();
            }
          } else {
            window.clearTimeout(moduleDemoTimer);
            if (moduleDemoProgress) moduleDemoProgress.classList.remove("is-running");
          }
        });
      }, { threshold: [0, 0.28, 0.55], rootMargin: "0px 0px -8% 0px" });

      moduleDemoObserver.observe(moduleDemoSection);
    }

    if (moduleTabs.length) {
      // Prepara o primeiro painel sem disparar animação ou autoplay fora da viewport.
      activateModule(moduleTabs[0], false, false);
    }

    document.addEventListener("visibilitychange", function () {
      if (document.hidden) {
        window.clearTimeout(moduleDemoTimer);
      } else {
        if (heroDemoVisible) runHeroDemoLoop();
        if (storeDemoVisible && !storeDemoPaused) runStoreDemoLoop();
        if (moduleDemoStarted && moduleDemoVisible) scheduleNextModule();
      }
    });

    // FAQ
    const faqItems = document.querySelectorAll(".faq-item");
    faqItems.forEach(function (item) {
      const button = item.querySelector(".faq-button");
      button.addEventListener("click", function () {
        const wasOpen = item.classList.contains("is-open");
        faqItems.forEach(function (otherItem) {
          otherItem.classList.remove("is-open");
          otherItem.querySelector(".faq-button").setAttribute("aria-expanded", "false");
        });
        if (!wasOpen) {
          item.classList.add("is-open");
          button.setAttribute("aria-expanded", "true");
        }
      });
    });
  </script>
</body>
</html>
