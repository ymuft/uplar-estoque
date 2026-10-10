<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/planos_config.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf = (string)$_SESSION['csrf_token'];
$loggedIn = !empty($_SESSION['user_id']);
$pdo = get_pdo();
$catalogo = get_planos_por_categoria($pdo);
$periodos = ['mensal' => 1, 'trimestral' => 3, 'anual' => 12];
$planosJs = ['basico' => [], 'pro' => []];

foreach (['basico', 'pro'] as $categoria) {
    foreach ($periodos as $periodo => $meses) {
        $plano = $catalogo[$categoria . '_' . $periodo] ?? null;
        if (!$plano || empty($plano['ativo'])) continue;
        $preco = (int)$plano['preco'];
        $referencia = (int)($catalogo[$categoria . '_mensal']['preco'] ?? $preco);
        $economia = max(0, ($referencia * $meses) - $preco);
        $planosJs[$categoria][$periodo] = [
            'codigo' => (string)$plano['codigo'],
            'preco' => formatar_preco_plano($preco),
            'mensal' => formatar_preco_plano((int)round($preco / $meses)),
            'economia' => formatar_preco_plano($economia),
            'economia_centavos' => $economia,
            'dias' => (int)$plano['dias'],
        ];
    }
}
?>
<!DOCTYPE html>

<html class="light" lang="pt-BR"><head>
<meta charset="utf-8"/>
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>UPLAR - Planos e Preços</title>
<script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&amp;family=Hanken+Grotesk:wght@600;700;800&amp;family=JetBrains+Mono:wght@500&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    "colors": {
                        "on-tertiary": "#ffffff",
                        "primary-container": "#0066ff",
                        "on-surface-variant": "#424656",
                        "surface-container-highest": "#e0e3e5",
                        "surface-dark": "#0A1B44",
                        "surface-container-low": "#f2f4f6",
                        "vibrant-blue": "#0066FF",
                        "primary-fixed": "#dae1ff",
                        "error": "#ba1a1a",
                        "inverse-primary": "#b3c5ff",
                        "inverse-surface": "#2d3133",
                        "primary-fixed-dim": "#b3c5ff",
                        "secondary": "#515d84",
                        "surface-container": "#eceef0",
                        "tertiary": "#a33200",
                        "error-container": "#ffdad6",
                        "text-muted": "#64748B",
                        "surface-container-lowest": "#ffffff",
                        "on-primary-fixed": "#001849",
                        "primary": "#0050cb",
                        "tertiary-container": "#cc4204",
                        "secondary-container": "#c4d0fd",
                        "secondary-fixed": "#dbe1ff",
                        "on-secondary-fixed-variant": "#39456a",
                        "deep-navy": "#061437",
                        "on-primary": "#ffffff",
                        "secondary-fixed-dim": "#b9c5f1",
                        "surface-tint": "#0054d6",
                        "on-surface": "#191c1e",
                        "on-background": "#191c1e",
                        "on-tertiary-fixed-variant": "#832600",
                        "background": "#f7f9fb",
                        "surface-dim": "#d8dadc",
                        "surface-container-high": "#e6e8ea",
                        "grid-line": "rgba(6, 20, 55, 0.05)",
                        "outline-variant": "#c2c6d8",
                        "on-error-container": "#93000a",
                        "tertiary-fixed": "#ffdbd0",
                        "on-secondary": "#ffffff",
                        "on-tertiary-fixed": "#390c00",
                        "on-primary-container": "#f8f7ff",
                        "inverse-on-surface": "#eff1f3",
                        "tertiary-fixed-dim": "#ffb59d",
                        "on-secondary-container": "#4c587f",
                        "surface-variant": "#e0e3e5",
                        "on-primary-fixed-variant": "#003fa4",
                        "on-secondary-fixed": "#0c1a3c",
                        "surface-bright": "#f7f9fb",
                        "surface": "#f7f9fb",
                        "outline": "#727687",
                        "on-error": "#ffffff",
                        "on-tertiary-container": "#fff6f4"
                    },
                    "borderRadius": {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                    "spacing": {
                        "margin-desktop": "64px",
                        "gutter": "24px",
                        "container-max": "1280px",
                        "grid-unit": "8px",
                        "margin-mobile": "16px"
                    },
                    "fontFamily": {
                        "body-md": ["Inter"],
                        "display-lg": ["Hanken Grotesk"],
                        "headline-md": ["Hanken Grotesk"],
                        "button": ["Inter"],
                        "label-caps": ["JetBrains Mono"],
                        "headline-sm": ["Hanken Grotesk"],
                        "body-lg": ["Inter"],
                        "display-lg-mobile": ["Hanken Grotesk"]
                    },
                    "fontSize": {
                        "body-md": ["16px", { "lineHeight": "1.5", "fontWeight": "400" }],
                        "display-lg": ["48px", { "lineHeight": "1.1", "letterSpacing": "-0.02em", "fontWeight": "700" }],
                        "headline-md": ["32px", { "lineHeight": "1.3", "fontWeight": "600" }],
                        "button": ["14px", { "lineHeight": "20px", "fontWeight": "600" }],
                        "label-caps": ["12px", { "lineHeight": "1", "letterSpacing": "0.05em", "fontWeight": "500" }],
                        "headline-sm": ["24px", { "lineHeight": "1.4", "fontWeight": "600" }],
                        "body-lg": ["18px", { "lineHeight": "1.6", "fontWeight": "400" }],
                        "display-lg-mobile": ["36px", { "lineHeight": "1.2", "fontWeight": "700" }]
                    },
                    backgroundImage: {
                        'grid-pattern': "url(\"data:image/svg+xml,%3Csvg width='32' height='32' viewBox='0 0 32 32' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M32 0v32H0V0h32zM1 1h30v30H1V1z' fill='%23061437' fill-opacity='0.03' fill-rule='evenodd'/%3E%3C/svg%3E\")"
                    }
                }
            }
        }
    </script>
<style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .icon-fill {
            font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body class="bg-background text-on-background font-body-md antialiased selection:bg-vibrant-blue selection:text-white">
<!-- TopNavBar -->
<nav class="bg-surface/80 dark:bg-deep-navy/80 backdrop-blur-md fixed top-0 w-full z-50 border-b border-grid-line dark:border-white/10 shadow-sm">
<div class="flex justify-between items-center h-20 px-margin-desktop max-w-container-max mx-auto md:px-margin-desktop px-margin-mobile">
<div class="flex items-center gap-2">
<span class="text-headline-sm font-display-lg font-bold text-deep-navy dark:text-surface-container-lowest">UPLAR</span>
</div>
<div class="hidden md:flex items-center gap-8">
<a class="text-on-surface-variant dark:text-surface-variant hover:text-vibrant-blue transition-colors font-button text-button" href="#">Soluções</a>
<a class="text-on-surface-variant dark:text-surface-variant hover:text-vibrant-blue transition-colors font-button text-button" href="#">Recursos</a>
<a class="text-vibrant-blue font-bold border-b-2 border-vibrant-blue pb-1 font-button text-button" href="#">Comparar planos</a>
<a class="text-on-surface-variant dark:text-surface-variant hover:text-vibrant-blue transition-colors font-button text-button" href="#">Dúvidas</a>
</div>
<div class="hidden md:flex items-center gap-4">
<a class="font-button text-button text-deep-navy hover:text-vibrant-blue transition-colors" href="<?= $loggedIn ? '/estoque' : '/login' ?>"><?= $loggedIn ? 'Voltar ao sistema' : 'Acessar sistema' ?></a>
<a class="bg-vibrant-blue text-white px-6 py-2 rounded font-button text-button hover:bg-surface-tint transition-colors shadow-[0_4px_14px_0_rgba(0,102,255,0.39)]" href="#planos">Começar Agora</a>
</div>
<button class="md:hidden text-deep-navy">
<span class="material-symbols-outlined">menu</span>
</button>
</div>
</nav>
<main class="pt-20">
<!-- Hero Section -->
<section id="hero-planos" class="hidden" aria-hidden="true">
<!-- STITCH_SHADER_START:ANIMATION_8 class="absolute inset-0 w-full h-full -z-10" -->
<div class="absolute inset-0 w-full h-full -z-10" style="display:block;">
<canvas id="shader-canvas-ANIMATION_8" style="display:block;width:100%;height:100%"></canvas>
<script>
(function() {
  return;
  const canvas = document.getElementById('shader-canvas-ANIMATION_8');

  // Sync the WebGL drawing-buffer size with the CSS-driven layout size.
  // This fires on initial layout and whenever the element is resized.
  function syncSize() {
    const w = canvas.clientWidth  || 1280;
    const h = canvas.clientHeight || 720;
    if (canvas.width !== w || canvas.height !== h) {
      canvas.width  = w;
      canvas.height = h;
    }
  }
  if (typeof ResizeObserver !== 'undefined') {
    new ResizeObserver(syncSize).observe(canvas);
  }
  syncSize();

  const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
  if (!gl) return;
  const vs = `attribute vec2 a_position;
varying vec2 v_texCoord;
void main() {
  v_texCoord = a_position * 0.5 + 0.5;
  gl_Position = vec4(a_position, 0.0, 1.0);
}`;
  const fs = `precision highp float;

uniform float u_time;
uniform vec2 u_resolution;
uniform vec2 u_mouse;

varying vec2 v_texCoord;

void main() {
    vec2 uv = v_texCoord;
    
    // Suave movimento orgânico baseado no tempo
    float noise = sin(uv.x * 3.0 + u_time * 0.5) * cos(uv.y * 2.0 - u_time * 0.3);
    
    // Cores baseadas na marca UPLAR (Azul vibrante e tons profundos)
    vec3 color1 = vec3(0.0, 0.4, 1.0); // Vibrant Blue
    vec3 color2 = vec3(0.02, 0.05, 0.15); // Deep Navy
    
    // Interação com o mouse
    vec2 mouseNorm = u_mouse / u_resolution;
    float dist = length(uv - mouseNorm);
    float glow = smoothstep(0.4, 0.0, dist) * 0.2;
    
    // Mistura de cores com padrão de grade sutil
    float grid = abs(sin(uv.x * 40.0) * sin(uv.y * 40.0));
    grid = pow(grid, 0.1);
    
    vec3 finalColor = mix(color2, color1, noise * 0.5 + 0.5);
    finalColor += color1 * glow; // Adiciona brilho no mouse
    
    // Aplica a grade sutilmente
    finalColor *= (0.95 + 0.05 * grid);
    
    gl_FragColor = vec4(finalColor, 1.0);
}`;
  function cs(type, src) {
    const s = gl.createShader(type);
    gl.shaderSource(s, src);
    gl.compileShader(s);
    return s;
  }
  const prog = gl.createProgram();
  gl.attachShader(prog, cs(gl.VERTEX_SHADER, vs));
  gl.attachShader(prog, cs(gl.FRAGMENT_SHADER, fs));
  gl.linkProgram(prog);
  gl.useProgram(prog);
  const buf = gl.createBuffer();
  gl.bindBuffer(gl.ARRAY_BUFFER, buf);
  gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1,-1, 1,-1, -1,1, 1,1]), gl.STATIC_DRAW);
  const pos = gl.getAttribLocation(prog, 'a_position');
  gl.enableVertexAttribArray(pos);
  gl.vertexAttribPointer(pos, 2, gl.FLOAT, false, 0, 0);
  const uTime = gl.getUniformLocation(prog, 'u_time');
  const uRes = gl.getUniformLocation(prog, 'u_resolution');
  const uMouse = gl.getUniformLocation(prog, 'u_mouse');

  // u_mouse is in pixel coordinates matching u_resolution (ShaderToy convention).
  // Shaders that need normalized coords should use: u_mouse / u_resolution.
  let mouse = { x: canvas.width / 2, y: canvas.height / 2 };
  window.addEventListener('mousemove', (event) => {
    const rect = canvas.getBoundingClientRect();
    if (rect.width && rect.height) {
      const nx = (event.clientX - rect.left) / rect.width;
      const ny = 1.0 - (event.clientY - rect.top) / rect.height;
      mouse.x = nx * canvas.width;
      mouse.y = ny * canvas.height;
    }
  });

  function render(t) {
    if (typeof ResizeObserver === 'undefined') syncSize();
    gl.viewport(0, 0, canvas.width, canvas.height);
    if (uTime) gl.uniform1f(uTime, t * 0.001);
    if (uRes) gl.uniform2f(uRes, canvas.width, canvas.height);
    if (uMouse) gl.uniform2f(uMouse, mouse.x, mouse.y);
    gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
    requestAnimationFrame(render);
  }
  render(0);
})();
</script>
</div>
<!-- STITCH_SHADER_END:ANIMATION_8 -->
<div class="absolute inset-0 bg-gradient-to-b from-transparent to-background/90 pointer-events-none"></div>
<div class="max-w-3xl mx-auto text-center relative z-10 bg-white/40 backdrop-blur-md p-8 rounded-2xl border border-white/20 shadow-xl">
<div class="inline-flex items-center gap-2 px-3 py-1 bg-white/80 rounded-full mb-6 border border-surface-dim">
<span class="material-symbols-outlined text-[16px] text-vibrant-blue">rocket_launch</span>
<span class="font-label-caps text-label-caps text-deep-navy">PLANOS UPLAR ESTOQUE</span>
</div>
<h1 class="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-deep-navy mb-6 tracking-tight">
                    Comece pelo controle. Avance para uma <span class="text-vibrant-blue">loja que vende.</span>
</h1>
<p class="font-body-lg text-body-lg text-deep-navy/80 mb-12 max-w-2xl mx-auto">
                    No Básico, estoque, vendas, vendedores e financeiro ficam no mesmo sistema. No PRO, os produtos que você já cadastrou também ganham um site comercial ligado aos pedidos pelo WhatsApp.
                </p>
<div class="flex flex-wrap justify-center gap-6 mb-12">
<div class="flex items-center gap-2 text-sm text-deep-navy">
<span class="material-symbols-outlined text-vibrant-blue icon-fill text-[18px]">check_circle</span>
                        Sem instalação
                    </div>
<div class="flex items-center gap-2 text-sm text-deep-navy">
<span class="material-symbols-outlined text-vibrant-blue icon-fill text-[18px]">check_circle</span>
                        Acesso web e mobile
                    </div>
<div class="flex items-center gap-2 text-sm text-deep-navy">
<span class="material-symbols-outlined text-vibrant-blue icon-fill text-[18px]">check_circle</span>
                        Suporte direto
                    </div>
</div>
<!-- Billing Toggle -->
<div class="flex flex-col items-center gap-4">
<span class="font-label-caps text-label-caps text-vibrant-blue">ESCOLHA O PERÍODO</span>
<div class="inline-flex bg-white/80 p-1 rounded-lg border border-surface-dim backdrop-blur-sm">
<button data-periodo="mensal" class="period-button px-6 py-2 bg-white text-deep-navy rounded shadow-sm font-button text-button transition-all" aria-pressed="true">Mensal</button>
<button data-periodo="trimestral" class="period-button px-6 py-2 text-deep-navy/70 rounded hover:text-deep-navy font-button text-button transition-all" aria-pressed="false">
                            Trimestral <span class="block text-[10px] text-text-muted mt-0.5 font-normal">EQUILÍBRIO</span>
</button>
<button data-periodo="anual" class="period-button px-6 py-2 text-deep-navy/70 rounded hover:text-deep-navy font-button text-button transition-all" aria-pressed="false">
                            Anual <span class="block text-[10px] text-vibrant-blue mt-0.5 font-normal">MENOR MENSAL</span>
</button>
</div>
<p class="text-sm text-deep-navy/60 max-w-md mx-auto mt-4">Mude o período abaixo para comparar os valores. Trimestral e anual reduzem o custo médio por mês.</p>
</div>
</div>
</section>
<!-- Pricing Cards -->
<section id="planos" class="px-margin-mobile pb-24 pt-14 md:px-margin-desktop md:pt-20">
<div class="max-w-3xl mx-auto text-center mb-10">
<span class="font-label-caps text-label-caps text-vibrant-blue mb-4 block">ESCOLHA SEU PLANO</span>
<h1 class="font-display-lg-mobile md:font-display-lg text-display-lg-mobile md:text-display-lg text-deep-navy tracking-tight">Planos e preços</h1>
<p class="text-sm text-on-surface-variant mt-4">Selecione o período que faz mais sentido para sua operação.</p>
<div class="flex flex-col items-center gap-4 mt-8">
<div class="inline-flex bg-surface-container-low p-1 rounded-lg border border-surface-dim">
<button data-periodo="mensal" class="period-button px-6 py-2 bg-white text-deep-navy rounded shadow-sm font-button text-button transition-all" aria-pressed="true">Mensal</button>
<button data-periodo="trimestral" class="period-button px-6 py-2 text-deep-navy/70 rounded hover:text-deep-navy font-button text-button transition-all" aria-pressed="false">Trimestral</button>
<button data-periodo="anual" class="period-button px-6 py-2 text-deep-navy/70 rounded hover:text-deep-navy font-button text-button transition-all" aria-pressed="false">Anual</button>
</div>
<p class="text-xs text-text-muted">Trimestral e anual reduzem o custo médio por mês.</p>
</div>
</div>
<div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-gutter items-start">
<!-- Basic Plan -->
<div class="bg-white/90 backdrop-blur-xl rounded-xl border-2 border-[#F97316] p-8 shadow-xl flex flex-col h-full relative">
<div id="basicMonthlyBadge" class="absolute top-0 right-0 bg-[#F97316] text-white px-4 py-1 rounded-bl-lg font-label-caps text-[10px] font-bold z-10">🔥 MAIS BARATO</div>
<div class="mb-8">
<div class="font-label-caps text-label-caps text-text-muted mb-2">— PLANO BÁSICO</div>
<h3 class="font-headline-sm text-headline-sm text-deep-navy mb-4">Sua operação sob controle.</h3>
<p class="text-sm text-on-surface-variant h-16">Estoque, produtos, vendas, vendedores, clientes, financeiro, relatórios e vitrine atual com WhatsApp.</p>
</div>
<div class="mb-8 pb-8 border-b border-surface-container-high">
<div class="flex items-baseline gap-2">
<span data-price="basico" class="font-headline-md text-headline-md text-deep-navy">R$ 19,90</span>
<span data-detail="basico" class="text-sm text-text-muted">/mês</span>
</div>
<p data-saving="basico" class="text-[12px] text-text-muted mt-2">Trimestral: R$ 54,90 | Anual: R$ 199,00</p>
</div>
<ul class="space-y-4 mb-8 flex-grow">
<li class="flex items-start gap-3">
<span class="material-symbols-outlined text-vibrant-blue text-[20px]">check</span>
<span class="text-sm text-on-surface-variant">Cadastro ilimitado de produtos</span>
</li>
<li class="flex items-start gap-3">
<span class="material-symbols-outlined text-vibrant-blue text-[20px]">check</span>
<span class="text-sm text-on-surface-variant">Controle de estoque, vendas e financeiro</span>
</li>
<li class="flex items-start gap-3">
<span class="material-symbols-outlined text-vibrant-blue text-[20px]">check</span>
<span class="text-sm text-on-surface-variant">Gestão de vendedores e clientes</span>
</li>
<li class="flex items-start gap-3">
<span class="material-symbols-outlined text-vibrant-blue text-[20px]">check</span>
<span class="text-sm text-on-surface-variant">Relatórios automáticos</span>
</li>
<li class="flex items-start gap-3">
<span class="material-symbols-outlined text-vibrant-blue text-[20px]">check</span>
<span class="text-sm text-on-surface-variant">Vitrine atual com catálogo e WhatsApp</span>
</li>
</ul>
<button data-checkout="basico" class="checkout-button w-full py-3 border border-deep-navy text-deep-navy rounded font-button text-button hover:bg-surface-container transition-colors mt-auto">Começar pelo Básico</button>
<p class="text-[11px] text-center text-text-muted mt-3">Para organizar a operação investindo menos.</p>
</div>
<!-- PRO Plan (Highlighted) -->
<div class="bg-deep-navy/95 backdrop-blur-xl rounded-xl p-8 shadow-2xl flex flex-col h-full relative border border-vibrant-blue/50 overflow-hidden">
<div class="absolute top-0 right-0 bg-vibrant-blue text-white px-4 py-1 rounded-bl-lg font-label-caps text-[10px] font-bold z-10">MAIS POPULAR</div>
<div class="mb-8 relative z-10">
<div class="font-label-caps text-label-caps text-surface-container-highest mb-2 opacity-80">— PLANO PRO</div>
<h3 class="font-headline-sm text-headline-sm text-white mb-4">Seu estoque também vira um site de vendas.</h3>
<p class="text-sm text-surface-variant h-16">Tudo do Básico + criação de site comercial simplificado. Leve o pedido pronto para o WhatsApp — sem cadastrar tudo duas vezes.</p>
</div>
<div class="mb-8 pb-8 border-b border-white/10 relative z-10">
<div class="flex items-baseline gap-2">
<span data-price="pro" class="font-headline-md text-headline-md text-white">R$ 29,85</span>
<span data-detail="pro" class="text-sm text-surface-variant">/mês</span>
</div>
<p data-saving="pro" class="text-[12px] text-surface-variant mt-2">Trimestral: R$ 82,35 | Anual: R$ 298,50</p>
</div>
<div class="mb-8 flex-grow relative z-10">
<h4 class="text-sm font-semibold text-white mb-4">Destaques do Site PRO:</h4>
<ul class="space-y-3">
<li class="flex items-start gap-3">
<span class="material-symbols-outlined text-vibrant-blue text-[20px]">check_circle</span>
<span class="text-sm text-surface-container-highest">Modelos visuais, logo, cores, capa e seções</span>
</li>
<li class="flex items-start gap-3">
<span class="material-symbols-outlined text-vibrant-blue text-[20px]">check_circle</span>
<span class="text-sm text-surface-container-highest">Produtos em destaque e categorias</span>
</li>
<li class="flex items-start gap-3">
<span class="material-symbols-outlined text-vibrant-blue text-[20px]">check_circle</span>
<span class="text-sm text-surface-container-highest">Catálogo sincronizado ao estoque</span>
</li>
<li class="flex items-start gap-3">
<span class="material-symbols-outlined text-vibrant-blue text-[20px]">check_circle</span>
<span class="text-sm text-surface-container-highest">Pedidos organizados no WhatsApp</span>
</li>
</ul>
</div>
<button data-checkout="pro" class="checkout-button w-full py-3 bg-vibrant-blue text-white rounded font-button text-button hover:bg-surface-tint transition-colors mt-auto relative z-10 shadow-[0_4px_14px_0_rgba(0,102,255,0.39)] flex justify-center items-center gap-2">
                        Quero o Plano PRO <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
</button>
<p class="text-[11px] text-center text-surface-variant mt-3 relative z-10 opacity-70">Gestão completa e um canal próprio para apresentar seus produtos.</p>
</div>
<!-- Enterprise fora do catálogo atual -->
<div class="hidden">
<div class="mb-8">
<div class="font-label-caps text-label-caps text-text-muted mb-2">— PLANO ENTERPRISE</div>
<h3 class="font-headline-sm text-headline-sm text-deep-navy mb-4">Para operações complexas.</h3>
<p class="text-sm text-on-surface-variant h-16">Soluções customizadas, integrações dedicadas e suporte prioritário para empresas que precisam escalar sem limites.</p>
</div>
<div class="mb-8 pb-8 border-b border-surface-container-high">
<div class="flex items-baseline gap-2">
<span class="font-headline-md text-headline-md text-deep-navy">Personalizado</span>
</div>
<p class="text-[12px] text-text-muted mt-2">Fale com nossos especialistas</p>
</div>
<ul class="space-y-4 mb-8 flex-grow">
<li class="flex items-start gap-3">
<span class="material-symbols-outlined text-vibrant-blue text-[20px]">check</span>
<span class="text-sm text-on-surface-variant">Tudo do Plano PRO</span>
</li>
<li class="flex items-start gap-3">
<span class="material-symbols-outlined text-vibrant-blue text-[20px]">check</span>
<span class="text-sm text-on-surface-variant">Múltiplos estoques / filiais</span>
</li>
<li class="flex items-start gap-3">
<span class="material-symbols-outlined text-vibrant-blue text-[20px]">check</span>
<span class="text-sm text-on-surface-variant">API Aberta para integrações</span>
</li>
<li class="flex items-start gap-3">
<span class="material-symbols-outlined text-vibrant-blue text-[20px]">check</span>
<span class="text-sm text-on-surface-variant">Gerente de conta dedicado (CSM)</span>
</li>
<li class="flex items-start gap-3">
<span class="material-symbols-outlined text-vibrant-blue text-[20px]">check</span>
<span class="text-sm text-on-surface-variant">SLA Garantido</span>
</li>
</ul>
<button class="w-full py-3 border border-outline-variant text-on-surface-variant rounded font-button text-button hover:bg-surface-container-low hover:text-deep-navy transition-colors mt-auto">Falar com Consultor</button>
</div>
</div>
</section>
<!-- Comparison Table -->
<section class="py-24 px-margin-mobile md:px-margin-desktop bg-surface-container-lowest">
<div class="max-w-5xl mx-auto">
<div class="mb-12 text-center">
<span class="font-label-caps text-label-caps text-vibrant-blue mb-4 block">— COMPARAÇÃO OBJETIVA</span>
<h2 class="font-headline-md text-headline-md text-deep-navy mb-4">O Básico organiza. O PRO organiza e apresenta sua loja.</h2>
<p class="text-sm text-on-surface-variant max-w-2xl mx-auto">A diferença está na experiência comercial. Os controles internos continuam completos nos dois planos.</p>
</div>
<div class="rounded-2xl overflow-hidden">
<table class="w-full text-left border-collapse">
<thead>
<tr class="text-sm font-button border-b-2 border-surface-dim">
<th class="py-6 px-6 font-semibold w-1/2 text-deep-navy">Recurso</th>
<th class="py-6 px-6 font-semibold text-center w-1/4 text-deep-navy">Básico</th>
<th class="py-6 px-6 font-semibold text-center w-1/4 bg-vibrant-blue/10 text-vibrant-blue rounded-t-xl">PRO</th>
</tr>
</thead>
<tbody class="text-sm text-on-surface-variant">
<tr class="border-b border-surface-container-high hover:bg-surface-container/50 transition-colors">
<td class="py-5 px-6 font-medium">Controle de estoque, vendas e financeiro</td>
<td class="py-5 px-6 text-center text-vibrant-blue"><span class="material-symbols-outlined text-[20px]">check_circle</span></td>
<td class="py-5 px-6 text-center text-vibrant-blue bg-vibrant-blue/5"><span class="material-symbols-outlined text-[20px]">check_circle</span></td>
</tr>
<tr class="border-b border-surface-container-high hover:bg-surface-container/50 transition-colors">
<td class="py-5 px-6 font-medium">Produtos, vendedores e clientes</td>
<td class="py-5 px-6 text-center text-vibrant-blue"><span class="material-symbols-outlined text-[20px]">check_circle</span></td>
<td class="py-5 px-6 text-center text-vibrant-blue bg-vibrant-blue/5"><span class="material-symbols-outlined text-[20px]">check_circle</span></td>
</tr>
<tr class="border-b border-surface-container-high hover:bg-surface-container/50 transition-colors">
<td class="py-5 px-6 font-medium">Relatórios e indicadores da operação</td>
<td class="py-5 px-6 text-center text-vibrant-blue"><span class="material-symbols-outlined text-[20px]">check_circle</span></td>
<td class="py-5 px-6 text-center text-vibrant-blue bg-vibrant-blue/5"><span class="material-symbols-outlined text-[20px]">check_circle</span></td>
</tr>
<tr class="border-b border-surface-container-high hover:bg-surface-container/50 transition-colors">
<td class="py-5 px-6 font-medium">Vitrine com catálogo e pedido no WhatsApp</td>
<td class="py-5 px-6 text-center text-vibrant-blue"><span class="material-symbols-outlined text-[20px]">check_circle</span></td>
<td class="py-5 px-6 text-center text-vibrant-blue bg-vibrant-blue/5"><span class="material-symbols-outlined text-[20px]">check_circle</span></td>
</tr>
<tr class="border-b border-surface-container-high hover:bg-surface-container/50 transition-colors">
<td class="py-5 px-6 font-medium">Site comercial com modelos visuais</td>
<td class="py-5 px-6 text-center text-outline/50"><span class="material-symbols-outlined text-[20px]">remove</span></td>
<td class="py-5 px-6 text-center text-vibrant-blue bg-vibrant-blue/5"><span class="material-symbols-outlined text-[20px]">check_circle</span></td>
</tr>
<tr class="border-b border-surface-container-high hover:bg-surface-container/50 transition-colors">
<td class="py-5 px-6 font-medium">Capa, seções e produtos em destaque</td>
<td class="py-5 px-6 text-center text-outline/50"><span class="material-symbols-outlined text-[20px]">remove</span></td>
<td class="py-5 px-6 text-center text-vibrant-blue bg-vibrant-blue/5"><span class="material-symbols-outlined text-[20px]">check_circle</span></td>
</tr>
<tr class="hover:bg-surface-container/50 transition-colors">
<td class="py-5 px-6 font-medium rounded-bl-xl">Experiência de edição simplificada</td>
<td class="py-5 px-6 text-center text-outline/50"><span class="material-symbols-outlined text-[20px]">remove</span></td>
<td class="py-5 px-6 text-center text-vibrant-blue bg-vibrant-blue/5 rounded-br-xl"><span class="material-symbols-outlined text-[20px]">check_circle</span></td>
</tr>
</tbody>
</table>
</div>
</div>
</section>
<!-- Decision Section (Bento Grid Style) -->
<section class="py-24 px-margin-mobile md:px-margin-desktop bg-surface-container-low">
<div class="max-w-4xl mx-auto text-center mb-16">
<span class="font-label-caps text-label-caps text-vibrant-blue mb-4 block">DECISÃO SIMPLES</span>
<h2 class="font-headline-md text-headline-md text-deep-navy">Escolha pelo que você precisa resolver agora.</h2>
</div>
<div class="max-w-4xl mx-auto grid grid-cols-1 md:grid-cols-2 gap-6">
<div class="bg-white border border-surface-dim p-8 rounded-xl flex flex-col justify-between hover:shadow-lg transition-shadow cursor-pointer">
<div>
<div class="flex items-center gap-3 mb-4">
<span class="material-symbols-outlined text-vibrant-blue">storefront</span>
<h3 class="font-button text-button text-deep-navy text-lg">Comece no Básico</h3>
</div>
<p class="text-sm text-on-surface-variant">Faz sentido para quem precisa deixar planilhas e memória para trás, organizar produtos, acompanhar vendedores e enxergar o resultado financeiro.</p>
</div>
</div>
<div class="bg-deep-navy text-white p-8 rounded-xl flex flex-col justify-between shadow-lg relative overflow-hidden group cursor-pointer">
<!-- Subtle glow effect -->
<div class="absolute -right-20 -top-20 w-48 h-48 bg-vibrant-blue rounded-full blur-3xl opacity-20 group-hover:opacity-40 transition-opacity"></div>
<div class="relative z-10">
<div class="flex items-center gap-3 mb-4">
<span class="material-symbols-outlined text-vibrant-blue">rocket</span>
<h3 class="font-button text-button text-white text-lg">Avance para o PRO</h3>
</div>
<p class="text-sm text-surface-variant">Faz sentido para quem, além de controlar a operação, quer apresentar produtos em um site próprio, destacar ofertas e facilitar pedidos sem manter catálogos e estoque separados.</p>
</div>
</div>
</div>
</section>
<!-- FAQ Section -->
<section class="py-24 px-margin-mobile md:px-margin-desktop bg-background border-t border-surface-dim">
<div class="max-w-4xl mx-auto flex flex-col md:flex-row gap-16">
<div class="md:w-1/3">
<span class="font-label-caps text-label-caps text-vibrant-blue mb-4 block">— DÚVIDAS FREQUENTES</span>
<h2 class="font-headline-md text-headline-md text-deep-navy mb-4">Informação clara antes de começar.</h2>
<p class="text-sm text-on-surface-variant mb-6">Se ainda precisar confirmar algum detalhe, fale diretamente com a equipe pelo WhatsApp.</p>
<a class="text-vibrant-blue font-button text-button hover:underline flex items-center gap-1" href="#">
                        Falar com atendimento <span class="material-symbols-outlined text-[16px]">open_in_new</span>
</a>
</div>
<div class="md:w-2/3">
<div class="divide-y divide-surface-dim">
<div class="py-5" x-data="{ open: true }">
<button class="flex justify-between items-center w-full text-left" onclick="this.parentElement.querySelector('div').classList.toggle('hidden'); this.querySelector('.icon').textContent = this.querySelector('.icon').textContent === 'add' ? 'close' : 'add';">
<h4 class="font-button text-button text-deep-navy">Preciso instalar alguma coisa?</h4>
<span class="material-symbols-outlined text-vibrant-blue icon">close</span>
</button>
<div class="mt-4 text-sm text-on-surface-variant">
                                Não. O Uplar Estoque funciona pela web e pode ser acessado pelo computador ou celular.
                            </div>
</div>
<div class="py-5">
<button class="flex justify-between items-center w-full text-left" onclick="this.parentElement.querySelector('div').classList.toggle('hidden'); this.querySelector('.icon').textContent = this.querySelector('.icon').textContent === 'add' ? 'close' : 'add';">
<h4 class="font-button text-button text-deep-navy">O Plano Básico continua com a vitrine atual?</h4>
<span class="material-symbols-outlined text-vibrant-blue icon">add</span>
</button>
<div class="mt-4 text-sm text-on-surface-variant hidden">
                                Sim. O Básico mantém a vitrine integrada com catálogo e pedido no WhatsApp. O PRO acrescenta o site comercial, os modelos visuais, as seções e mais controle de apresentação.
                            </div>
</div>
<div class="py-5">
<button class="flex justify-between items-center w-full text-left" onclick="this.parentElement.querySelector('div').classList.toggle('hidden'); this.querySelector('.icon').textContent = this.querySelector('.icon').textContent === 'add' ? 'close' : 'add';">
<h4 class="font-button text-button text-deep-navy">Preciso cadastrar os produtos novamente no PRO?</h4>
<span class="material-symbols-outlined text-vibrant-blue icon">add</span>
</button>
<div class="mt-4 text-sm text-on-surface-variant hidden">
                                Não. Todo o seu banco de dados, produtos e histórico são mantidos. O plano PRO apenas ativa as novas funcionalidades sobre a sua base existente.
                            </div>
</div>
<div class="py-5">
<button class="flex justify-between items-center w-full text-left" onclick="this.parentElement.querySelector('div').classList.toggle('hidden'); this.querySelector('.icon').textContent = this.querySelector('.icon').textContent === 'add' ? 'close' : 'add';">
<h4 class="font-button text-button text-deep-navy">Posso começar no Básico e mudar para o PRO?</h4>
<span class="material-symbols-outlined text-vibrant-blue icon">add</span>
</button>
<div class="mt-4 text-sm text-on-surface-variant hidden">
                                Sim. Seus produtos e dados permanecem na mesma conta; a mudança para o PRO é feita pela contratação do novo plano.
                            </div>
</div>
<div class="py-5">
<button class="flex justify-between items-center w-full text-left" onclick="this.parentElement.querySelector('div').classList.toggle('hidden'); this.querySelector('.icon').textContent = this.querySelector('.icon').textContent === 'add' ? 'close' : 'add';">
<h4 class="font-button text-button text-deep-navy">Como recebo os pedidos?</h4>
<span class="material-symbols-outlined text-vibrant-blue icon">add</span>
</button>
<div class="mt-4 text-sm text-on-surface-variant hidden">
                                No plano PRO, os clientes montam o carrinho na sua vitrine e o pedido chega formatado diretamente no número de WhatsApp que você configurar, facilitando o fechamento.
                            </div>
</div>
<div class="py-5">
<button class="flex justify-between items-center w-full text-left" onclick="this.parentElement.querySelector('div').classList.toggle('hidden'); this.querySelector('.icon').textContent = this.querySelector('.icon').textContent === 'add' ? 'close' : 'add';">
<h4 class="font-button text-button text-deep-navy">Existe suporte para começar?</h4>
<span class="material-symbols-outlined text-vibrant-blue icon">add</span>
</button>
<div class="mt-4 text-sm text-on-surface-variant hidden">
                                Sim, nossa equipe oferece suporte via WhatsApp em horário comercial para ajudar você a configurar seus primeiros produtos e entender o fluxo.
                            </div>
</div>
</div>
</div>
</div>
</section>
</main>
<!-- Footer -->
<footer class="bg-deep-navy dark:bg-deep-navy relative w-full py-16 border-t border-white/5 font-body-md text-body-md">
<!-- Sub CTA Section within Footer area -->
<div class="border-b border-white/10 pb-16 mb-16 px-margin-desktop max-w-container-max mx-auto">
<div class="flex flex-col md:flex-row justify-between items-center gap-8 bg-surface-dark p-8 md:p-12 rounded-2xl border border-white/5 relative overflow-hidden">
<div class="absolute inset-0 bg-grid-pattern opacity-10"></div>
<div class="relative z-10 max-w-lg">
<span class="font-label-caps text-label-caps text-vibrant-blue mb-2 block">COMECE NO SEU RITMO</span>
<h3 class="font-headline-sm text-headline-sm text-white mb-2">Controle sua operação agora. Ative o site PRO quando fizer sentido.</h3>
<p class="text-sm text-surface-variant opacity-80">Os dois planos usam a mesma base de produtos, vendas, equipe e financeiro.</p>
</div>
<div class="relative z-10 flex flex-col gap-3 min-w-[200px]">
<button data-checkout="pro" class="checkout-button w-full py-3 bg-vibrant-blue text-white rounded font-button text-button hover:bg-surface-tint transition-colors shadow-[0_4px_14px_0_rgba(0,102,255,0.39)] flex justify-center items-center gap-2">
                        Escolher Plano PRO <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
</button>
<a href="#planos" class="w-full py-3 border border-white/20 text-white rounded font-button text-button hover:bg-white/5 transition-colors text-center">
                        Comparar com o Básico
                    </a>
</div>
</div>
</div>
<div class="grid grid-cols-1 md:grid-cols-4 gap-gutter px-margin-desktop max-w-container-max mx-auto">
<!-- Brand Column -->
<div class="col-span-1 md:col-span-2">
<div class="flex items-center gap-2 mb-4">
<span class="text-headline-sm font-display-lg font-bold text-white">UPLAR</span>
<span class="text-[10px] bg-white/10 text-white px-2 py-0.5 rounded font-label-caps">ESTOQUE</span>
</div>
<p class="text-surface-variant/70 text-sm max-w-xs mb-8">
                    Controle de estoque, vendas, equipe e financeiro em uma única plataforma.
                </p>
</div>
<!-- Links Column 1 -->
<div>
<h5 class="font-label-caps text-label-caps text-white mb-6">PRODUTO</h5>
<ul class="space-y-4">
<li><a class="text-surface-variant/70 hover:text-white transition-colors duration-200 hover:translate-x-1 inline-block" href="#">Recursos</a></li>
<li><a class="text-surface-variant/70 hover:text-white transition-colors duration-200 hover:translate-x-1 inline-block" href="#">Vitrine online</a></li>
<li><a class="text-vibrant-blue font-semibold transition-colors duration-200 hover:translate-x-1 inline-block" href="#">Planos</a></li>
</ul>
</div>
<!-- Links Column 2 -->
<div>
<h5 class="font-label-caps text-label-caps text-white mb-6">ACESSO</h5>
<ul class="space-y-4">
<li><a class="text-surface-variant/70 hover:text-white transition-colors duration-200 hover:translate-x-1 inline-block" href="#">Entrar no sistema</a></li>
<li><a class="text-surface-variant/70 hover:text-white transition-colors duration-200 hover:translate-x-1 inline-block" href="#">Site institucional</a></li>
<li><a class="text-surface-variant/70 hover:text-white transition-colors duration-200 hover:translate-x-1 inline-block" href="#">WhatsApp</a></li>
</ul>
</div>
</div>
<!-- Copyright -->
<div class="mt-16 pt-8 border-t border-white/5 px-margin-desktop max-w-container-max mx-auto flex flex-col md:flex-row justify-between items-center gap-4">
<p class="text-xs text-surface-variant/50">© 2024 UPLAR Business Management. Todos os direitos reservados.</p>
<div class="flex gap-4">
<a class="text-xs text-surface-variant/50 hover:text-white transition-colors font-label-caps" href="#">Termos de Uso</a>
<a class="text-xs text-surface-variant/50 hover:text-white transition-colors font-label-caps" href="#">Privacidade</a>
<a class="text-xs text-surface-variant/50 hover:text-white transition-colors font-label-caps" href="#">Suporte</a>
</div>
</div>
</footer>
<div id="toast" class="fixed bottom-6 left-1/2 z-[100] max-w-[calc(100%-32px)] -translate-x-1/2 translate-y-4 rounded-lg bg-error px-5 py-3 text-sm font-semibold text-white opacity-0 shadow-xl transition-all" role="status" aria-live="polite"></div>
<script>
const PLANOS = <?= json_encode($planosJs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const LOGGED_IN = <?= $loggedIn ? 'true' : 'false' ?>;
const CSRF = <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let periodoAtual = 'mensal';

function atualizarPlanos(periodo) {
  periodoAtual = periodo;
  document.getElementById('basicMonthlyBadge')?.classList.toggle('hidden', periodo !== 'mensal');
  document.querySelectorAll('[data-periodo]').forEach((botao) => {
    const ativo = botao.dataset.periodo === periodo;
    botao.setAttribute('aria-pressed', String(ativo));
    botao.classList.toggle('bg-white', ativo);
    botao.classList.toggle('shadow-sm', ativo);
    botao.classList.toggle('text-deep-navy', ativo);
  });
  ['basico', 'pro'].forEach((categoria) => {
    const plano = PLANOS[categoria]?.[periodo];
    if (!plano) return;
    document.querySelectorAll(`[data-price="${categoria}"]`).forEach((el) => el.textContent = plano.preco);
    document.querySelectorAll(`[data-detail="${categoria}"]`).forEach((el) => el.textContent = periodo === 'mensal' ? '/mês' : `por ${plano.dias} dias`);
    document.querySelectorAll(`[data-saving="${categoria}"]`).forEach((el) => {
      el.textContent = plano.economia_centavos > 0 ? `Equivale a ${plano.mensal}/mês · economia de ${plano.economia}` : 'Pagamento referente a 30 dias de acesso';
    });
  });
}

function mostrarErro(mensagem) {
  const toast = document.getElementById('toast');
  toast.textContent = mensagem;
  toast.classList.remove('translate-y-4', 'opacity-0');
  window.setTimeout(() => toast.classList.add('translate-y-4', 'opacity-0'), 4500);
}

async function iniciarCheckout(botao, categoria) {
  const plano = PLANOS[categoria]?.[periodoAtual];
  if (!plano) { mostrarErro('Este plano não está disponível.'); return; }
  if (!LOGGED_IN) { window.location.href = `registro.php?plano=${encodeURIComponent(plano.codigo)}`; return; }
  if (botao.disabled) return;
  const conteudoOriginal = botao.innerHTML;
  botao.disabled = true;
  botao.textContent = 'Preparando pagamento...';
  try {
    const resposta = await fetch('payment_api.php?acao=criar_link', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({csrf_token: CSRF, plano: plano.codigo})
    });
    const dados = await resposta.json();
    if (!resposta.ok || !dados.checkout_url) throw new Error(dados.error || 'Não foi possível criar o pagamento.');
    window.location.href = dados.checkout_url;
  } catch (erro) {
    mostrarErro(erro.message || 'Não foi possível continuar.');
    botao.disabled = false;
    botao.innerHTML = conteudoOriginal;
  }
}

document.querySelectorAll('[data-periodo]').forEach((botao) => botao.addEventListener('click', () => atualizarPlanos(botao.dataset.periodo)));
document.querySelectorAll('[data-checkout]').forEach((botao) => botao.addEventListener('click', () => iniciarCheckout(botao, botao.dataset.checkout)));
atualizarPlanos(periodoAtual);
</script>
</body></html>
