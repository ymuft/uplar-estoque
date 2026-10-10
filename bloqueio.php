<?php
session_start();
require 'db.php';
require 'helpers.php';
require 'subscription.php';
require 'planos_config.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id  = (int)$_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Usuário';
$pdo      = get_pdo();
$planos_config = get_planos_config($pdo);
get_csrf_token();

$sub = get_subscription($user_id, $pdo);
if ($sub['ativa']) { header('Location: estoque.php'); exit; }

$status = $sub['status'] ?? ($_SESSION['sub_status'] ?? 'sem_inscricao');
$selected_plan = trim($_GET['plano'] ?? '');
$selected_plan = in_array($selected_plan, ['mensal','trimestral','anual'], true) ? $selected_plan : 'mensal';

$msgs = [
    'expirada'      => ['icon'=>'event_busy',  'titulo'=>'Sua assinatura expirou', 'cor'=>'#ef4444'],
    'suspensa'      => ['icon'=>'pause_circle', 'titulo'=>'Assinatura suspensa', 'cor'=>'#f59e0b'],
    'sem_inscricao' => ['icon'=>'lock', 'titulo'=>'Acesso não liberado', 'cor'=>'#6366f1'],
];
$info = $msgs[$status] ?? $msgs['sem_inscricao'];

$historico = [];
try {
    $stmt = $pdo->prepare("SELECT data_inicio, data_fim, plano_dias, status, criado_em FROM inscricoes WHERE user_id = ? ORDER BY criado_em DESC LIMIT 5");
    $stmt->execute([$user_id]);
    $historico = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}
$ultima = $historico[0] ?? null;

$planos = [
    'mensal' => ['dias' => 30, 'preco' => formatar_preco_plano($planos_config['mensal']['preco']), 'titulo' => 'Mensal', 'desc' => 'Ideal para começar rápido.'],
    'trimestral' => ['dias' => 90, 'preco' => formatar_preco_plano($planos_config['trimestral']['preco']), 'titulo' => 'Trimestral', 'desc' => 'Melhor equilíbrio entre custo e prazo.'],
    'anual' => ['dias' => 365, 'preco' => formatar_preco_plano($planos_config['anual']['preco']), 'titulo' => 'Anual', 'desc' => 'Maior economia para operação contínua.'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Assinatura — Uplar</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200"/>
<style>
:root{--p:#3b6ff5;--p-dark:#2a55d9;--p-glow:rgba(59,111,245,.2);--ink:#0f1c2e;--muted:#5a6a7a;--faint:#94a3b8;--bg:#f4f6fb;--card:#fff;--border:#e4eaf2;--ok:#10b981;--ok-dim:rgba(16,185,129,.09);--err:#ef4444;--err-dim:rgba(239,68,68,.09);--warn:#f59e0b;--warn-dim:rgba(245,158,11,.1)}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--ink);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;-webkit-font-smoothing:antialiased}
body::before{content:'';position:fixed;inset:0;pointer-events:none;background:radial-gradient(ellipse 70% 50% at 20% 10%,rgba(59,111,245,.06),transparent 60%)}
.wrap{width:100%;max-width:940px;z-index:1}.logo{display:flex;align-items:center;justify-content:center;gap:.5rem;margin-bottom:1.4rem}.logo-ico{width:38px;height:38px;background:linear-gradient(135deg,var(--p),#5c8aff);border-radius:10px;display:flex;align-items:center;justify-content:center;box-shadow:0 3px 12px var(--p-glow)}.logo-ico .material-symbols-outlined{font-size:1.25rem;color:#fff}.logo-txt{font-size:1.3rem;font-weight:800;letter-spacing:-.3px}
.card{background:var(--card);border-radius:20px;overflow:hidden;box-shadow:0 2px 12px rgba(15,28,46,.07),0 8px 32px rgba(15,28,46,.05);animation:up .5s cubic-bezier(.22,1,.36,1) both}@keyframes up{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.ch{padding:2.3rem 2rem 1.7rem;text-align:center}.ch-ico{width:72px;height:72px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:rgba(255,255,255,.2);border:2px solid rgba(255,255,255,.3);margin-bottom:1rem}.ch-ico .material-symbols-outlined{font-size:2.2rem;color:#fff}.ch-title{font-size:1.25rem;font-weight:800;color:#fff;letter-spacing:-.3px;margin-bottom:.3rem}.ch-sub{font-size:.82rem;color:rgba(255,255,255,.82);line-height:1.5}
.cb{padding:1.5rem 1.6rem 1.7rem}.grid{display:grid;grid-template-columns:1.1fr .9fr;gap:1rem}.panel{background:var(--bg);border:1px solid var(--border);border-radius:14px;padding:1rem 1.05rem}.panel-title{font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--faint);margin-bottom:.8rem;display:flex;align-items:center;gap:.35rem}.panel-title .material-symbols-outlined{font-size:.9rem}
.info-box{background:#fff;border:1px solid var(--border);border-radius:12px;padding:1rem 1.05rem;margin-bottom:.9rem;display:flex;gap:.8rem;align-items:flex-start}.info-box .material-symbols-outlined{font-size:1.1rem;color:var(--faint);flex-shrink:0;margin-top:2px}.ib-label{font-size:.68rem;color:var(--faint);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.15rem}.ib-val{font-size:.88rem;font-weight:700}
.hi{display:flex;align-items:center;justify-content:space-between;padding:.55rem .75rem;border-radius:9px;background:#fff;border:1px solid var(--border);margin-bottom:.35rem;font-size:.78rem}.hi-name{font-weight:700}.hi-d{color:var(--muted);font-size:.72rem;margin-top:.1rem}.hb{font-size:.64rem;font-weight:700;padding:.12rem .42rem;border-radius:6px}.hb.ativa{background:var(--ok-dim);color:var(--ok)}.hb.expirada{background:var(--err-dim);color:var(--err)}.hb.suspensa{background:var(--warn-dim);color:#92400e}
.plan-list{display:grid;gap:.7rem}.plan-card{background:#fff;border:2px solid var(--border);border-radius:14px;padding:.95rem 1rem;cursor:pointer;transition:all .18s}.plan-card:hover{border-color:var(--p);transform:translateY(-1px)}.plan-card.on{border-color:var(--p);box-shadow:0 6px 20px rgba(59,111,245,.12);background:rgba(59,111,245,.04)}.plan-top{display:flex;align-items:center;justify-content:space-between;gap:.8rem;margin-bottom:.2rem}.plan-name{font-size:.92rem;font-weight:800}.plan-price{font-size:.9rem;font-weight:800;color:var(--p)}.plan-desc{font-size:.75rem;color:var(--muted);margin-bottom:.45rem}.plan-meta{font-size:.7rem;color:var(--faint);font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.note{background:rgba(59,111,245,.06);border:1px solid var(--border);border-radius:10px;padding:.85rem 1rem;font-size:.8rem;color:var(--muted);line-height:1.55;margin-top:.85rem}
.actions{display:flex;flex-direction:column;gap:.55rem;margin-top:1rem}.btn-main,.btn-sec{width:100%;padding:.84rem;border-radius:11px;font-size:.86rem;font-weight:700;font-family:inherit;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.4rem;transition:all .2s}.btn-main{background:var(--p);color:#fff;border:none;box-shadow:0 4px 16px var(--p-glow)}.btn-main:hover{background:var(--p-dark);transform:translateY(-2px)}.btn-main:disabled{background:var(--faint);box-shadow:none;cursor:not-allowed;transform:none}.btn-sec{border:1.5px solid var(--border);background:#fff;color:var(--muted)}.btn-sec:hover{background:var(--bg);border-color:var(--p);color:var(--p)}.spinner{width:16px;height:16px;border:2px solid rgba(255,255,255,.35);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}
.toast{position:fixed;bottom:22px;left:50%;transform:translateX(-50%) translateY(20px);background:var(--ink);color:#fff;padding:.7rem 1.2rem;border-radius:999px;font-size:.82rem;font-weight:700;opacity:0;transition:.25s;display:flex;align-items:center;gap:.4rem;pointer-events:none}.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}.toast.err{background:var(--err)}
@media(max-width:840px){.grid{grid-template-columns:1fr}}@media(max-width:520px){.cb{padding:1.2rem}.ch{padding:2rem 1.2rem 1.4rem}}
</style>
</head>
<body>
<div class="wrap">
    <div class="logo"><div class="logo-ico"><span class="material-symbols-outlined">inventory_2</span></div><div class="logo-txt">Uplar</div></div>
    <div class="card">
        <div class="ch" style="background:<?= $info['cor'] ?>">
            <div class="ch-ico"><span class="material-symbols-outlined"><?= $info['icon'] ?></span></div>
            <div class="ch-title"><?= $info['titulo'] ?></div>
            <div class="ch-sub">Olá, <strong><?= htmlspecialchars($username) ?></strong>. Escolha um plano para liberar ou renovar seu acesso automaticamente.</div>
        </div>
        <div class="cb">
            <div class="grid">
                <div class="panel">
                    <div class="panel-title"><span class="material-symbols-outlined">sell</span>Escolha seu plano</div>
                    <div class="plan-list">
                        <?php foreach ($planos as $key => $plano): ?>
                        <div class="plan-card <?= $selected_plan === $key ? 'on' : '' ?>" data-plan="<?= htmlspecialchars($key) ?>" onclick="selecionarPlano('<?= htmlspecialchars($key) ?>', this)">
                            <div class="plan-top">
                                <div class="plan-name"><?= htmlspecialchars($plano['titulo']) ?></div>
                                <div class="plan-price"><?= htmlspecialchars($plano['preco']) ?></div>
                            </div>
                            <div class="plan-desc"><?= htmlspecialchars($plano['desc']) ?></div>
                            <div class="plan-meta"><?= (int)$plano['dias'] ?> dias de acesso</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="note">Se você renovar antes do vencimento, os dias restantes são preservados no novo período.</div>
                    <div class="actions">
                        <button type="button" class="btn-main" id="btnPagar" onclick="iniciarCheckout()"><span class="material-symbols-outlined">credit_card</span>Pagar e liberar acesso</button>
                        <form action="logout.php" method="POST">
                            <?= csrf_input() ?>
                            <button type="submit" class="btn-sec"><span class="material-symbols-outlined">logout</span>Sair da conta</button>
                        </form>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-title"><span class="material-symbols-outlined">history</span>Seu status</div>
                    <?php if ($ultima): ?>
                    <div class="info-box">
                        <span class="material-symbols-outlined">calendar_today</span>
                        <div>
                            <div class="ib-label">Última assinatura</div>
                            <div class="ib-val"><?= fmt_date_br($ultima['data_inicio']) ?> até <?= fmt_date_br($ultima['data_fim']) ?> — <?= (int)$ultima['plano_dias'] ?> dias</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="info-box">
                        <span class="material-symbols-outlined">lock</span>
                        <div>
                            <div class="ib-label">Conta criada</div>
                            <div class="ib-val">Seu acesso está pronto. Falta apenas concluir o pagamento do plano.</div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($historico)): foreach ($historico as $h): ?>
                    <div class="hi">
                        <div><div class="hi-name"><?= (int)$h['plano_dias'] ?> dias</div><div class="hi-d"><?= fmt_date_br($h['data_inicio']) ?> → <?= fmt_date_br($h['data_fim']) ?></div></div>
                        <span class="hb <?= htmlspecialchars($h['status']) ?>"><?= ucfirst($h['status']) ?></span>
                    </div>
                    <?php endforeach; else: ?>
                    <div class="note">Nenhuma assinatura anterior encontrada. Depois do pagamento, seu acesso será liberado automaticamente.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="toast" id="toast"><span class="material-symbols-outlined" id="toastIco">check_circle</span><span id="toastTxt"></span></div>
<script>
const CSRF = <?= json_encode(get_csrf_token()) ?>;
let planoSelecionado = <?= json_encode($selected_plan) ?>;
const toast = (msg, err=false) => {
    const el = document.getElementById('toast');
    document.getElementById('toastTxt').textContent = msg;
    document.getElementById('toastIco').textContent = err ? 'error' : 'check_circle';
    el.className = 'toast show' + (err ? ' err' : '');
    clearTimeout(window.__toastTimer);
    window.__toastTimer = setTimeout(() => el.className = 'toast', 3200);
};
function selecionarPlano(plano, el) {
    planoSelecionado = plano;
    document.querySelectorAll('.plan-card').forEach(card => card.classList.remove('on'));
    el.classList.add('on');
}
async function iniciarCheckout() {
    if (!planoSelecionado) {
        toast('Selecione um plano primeiro.', true);
        return;
    }
    const btn = document.getElementById('btnPagar');
    btn.disabled = true;
    btn.innerHTML = '<div class="spinner"></div>Gerando pagamento…';
    try {
        const resp = await fetch('payment_api.php?acao=criar_link', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ csrf_token: CSRF, plano: planoSelecionado }),
            credentials: 'same-origin'
        });
        const data = await resp.json();
        if (!resp.ok || !data.checkout_url) throw new Error(data.error || 'Não foi possível gerar o pagamento.');
        window.location.href = data.checkout_url;
    } catch (err) {
        toast(err.message || 'Erro ao iniciar pagamento.', true);
        btn.disabled = false;
        btn.innerHTML = '<span class="material-symbols-outlined">credit_card</span>Pagar e liberar acesso';
    }
}
</script>
</body>
</html>
