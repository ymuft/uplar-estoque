<?php
session_start();
require 'db.php';
require 'helpers.php';
require 'subscription.php';

$pdo = get_pdo();
get_csrf_token();

if (isset($_SESSION['user_id'])) {
    $subAtual = get_subscription((int)$_SESSION['user_id'], $pdo);
    header('Location: ' . ($subAtual['ativa'] ? 'estoque.php' : 'bloqueio.php'));
    exit;
}

$selected_plan = trim($_GET['plano'] ?? $_POST['plano'] ?? '');
$selected_plan = in_array($selected_plan, ['mensal', 'trimestral', 'anual'], true) ? $selected_plan : '';

$order_nsu  = $_SESSION['pagamento_order_nsu'] ?? '';
$plano_key  = $_SESSION['pagamento_plano_key'] ?? $selected_plan;
$plano_dias = (int)($_SESSION['pagamento_plano_dias'] ?? 30);

$pedido = null;
$legacy_paid_flow = false;
if (!empty($_SESSION['pagamento_confirmado']) && $order_nsu !== '') {
    $st = $pdo->prepare("SELECT * FROM pedidos_pagamento WHERE order_nsu = ? AND status = 'pago' LIMIT 1");
    $st->execute([$order_nsu]);
    $pedido = $st->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($pedido) {
        $plano_key = $pedido['plano_key'];
        $plano_dias = (int)$pedido['plano_dias'];
        $legacy_paid_flow = empty($pedido['user_id']);
        if (!$legacy_paid_flow) {
            header('Location: login.php?registered=1');
            exit;
        }
    }
}

const PLANOS_INFO = [
    'mensal'     => ['label'=>'Mensal',     'dias'=>30,  'preco'=>'R$ 15,00',   'cor'=>'#f97316', 'icone'=>'bolt'],
    'trimestral' => ['label'=>'Trimestral', 'dias'=>90,  'preco'=>'R$ 129,90',  'cor'=>'#7c3aed', 'icone'=>'workspace_premium'],
    'anual'      => ['label'=>'Anual',      'dias'=>365, 'preco'=>'R$ 399,90',  'cor'=>'#f59e0b', 'icone'=>'diamond'],
    'semestral'  => ['label'=>'Semestral',  'dias'=>180, 'preco'=>'R$ 239,90',  'cor'=>'#10b981', 'icone'=>'auto_awesome'],
];

$pi = PLANOS_INFO[$plano_key] ?? [
    'label' => 'Conta Free',
    'dias'  => $plano_dias,
    'preco' => 'Escolha depois',
    'cor'   => '#3b6ff5',
    'icone' => 'person_add',
];

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $erro = 'Token de segurança inválido. Recarregue a página.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $senha    = $_POST['senha']  ?? '';
        $senha2   = $_POST['senha2'] ?? '';

        if (strlen($username) < 3)                            $erro = 'O nome de usuário deve ter ao menos 3 caracteres.';
        elseif (!preg_match('/^[a-zA-Z0-9_.\-]+$/', $username)) $erro = 'Use apenas letras, números, ponto, hífen ou sublinhado.';
        elseif (strlen($senha) < 8)                           $erro = 'A senha deve ter ao menos 8 caracteres.';
        elseif (!preg_match('/[A-Z]/', $senha))               $erro = 'A senha precisa ter ao menos uma letra maiúscula.';
        elseif (!preg_match('/[0-9]/', $senha))               $erro = 'A senha precisa ter ao menos um número.';
        elseif (!preg_match('/[^A-Za-z0-9]/', $senha))        $erro = 'A senha precisa ter ao menos um símbolo.';
        elseif ($senha !== $senha2)                           $erro = 'As senhas não conferem.';
        else {
            $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $chk->execute([$username]);
            if ($chk->fetch()) {
                $erro = 'Este nome de usuário já está em uso. Escolha outro.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $hash = password_hash($senha, PASSWORD_BCRYPT, ['cost' => 12]);
                    if ($legacy_paid_flow) {
                        $pdo->prepare("INSERT INTO users (username, password, role, plano) VALUES (?, ?, 'user', 'pago')")
                            ->execute([$username, $hash]);
                    } else {
                        $pdo->prepare("INSERT INTO users (username, password, role, plano, free_desde) VALUES (?, ?, 'user', 'free', NOW())")
                            ->execute([$username, $hash]);
                    }
                    $user_id = (int)$pdo->lastInsertId();

                    if ($legacy_paid_flow && $pedido) {
                        $pdo->prepare("UPDATE pedidos_pagamento SET user_id = ? WHERE order_nsu = ?")
                            ->execute([$user_id, $order_nsu]);

                        $inicio = date('Y-m-d');
                        $fim = date('Y-m-d', strtotime("+{$plano_dias} days"));
                        $obs = "InfinityPay — order_nsu: {$order_nsu}";

                        $pdo->prepare("INSERT INTO inscricoes (user_id,data_inicio,data_fim,plano_dias,status,observacao,criado_por) VALUES (?,?,?,?,'ativa',?,0)")
                            ->execute([$user_id, $inicio, $fim, $plano_dias, $obs]);
                        $inscricao_id = (int)$pdo->lastInsertId();

                        $pdo->prepare("INSERT INTO inscricoes_historico (user_id,inscricao_id,acao,plano_dias,data_fim_nova,admin_id,observacao) VALUES (?,?,'criada',?,?,0,?)")
                            ->execute([$user_id, $inscricao_id, $plano_dias, $fim, 'Conta criada via pagamento confirmado']);
                    }

                    $pdo->commit();
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['role'] = 'user';
                    $_SESSION['welcome_new'] = true;
                    unset(
                        $_SESSION['pagamento_confirmado'],
                        $_SESSION['pagamento_order_nsu'],
                        $_SESSION['pagamento_plano_key'],
                        $_SESSION['pagamento_plano_dias'],
                        $_SESSION['pending_order_nsu'],
                        $_SESSION['pending_plano_key'],
                        $_SESSION['pending_plano_dias']
                    );

                    if ($legacy_paid_flow) {
                        header('Location: estoque.php');
                    } else {
                        $destino = 'bloqueio.php';
                        if ($selected_plan !== '') {
                            $destino .= '?plano=' . urlencode($selected_plan);
                        }
                        header('Location: ' . $destino);
                    }
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log('[registro] ' . $e->getMessage());
                    $erro = 'Erro ao criar conta. Tente novamente.';
                }
            }
        }
    }
}

$modo_titulo = $legacy_paid_flow ? 'Pagamento confirmado! ' : 'Crie sua conta primeiro';
$modo_sub = $legacy_paid_flow
    ? 'Só falta criar sua conta para começar a usar o sistema.'
    : ($selected_plan !== ''
        ? 'Cadastre seu usuário para continuar com o plano selecionado e liberar o acesso depois do pagamento.'
        : 'Seu acesso começa com uma conta free. Depois você escolhe um dos planos para liberar a plataforma.');
$fp_title = $legacy_paid_flow ? 'Criar minha conta' : 'Criar conta gratuita';
$fp_sub = $legacy_paid_flow
    ? 'Plano já pago. Preencha os dados para acessar o sistema.'
    : 'Crie seu acesso agora. Se você escolher um plano em seguida, o sistema libera automaticamente após o pagamento.';
$btn_label = $legacy_paid_flow ? 'Criar conta e acessar' : ($selected_plan !== '' ? 'Criar conta e continuar' : 'Criar conta gratuita');
$data_fim_fmt = date('d/m/Y', strtotime("+{$plano_dias} days"));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Criar conta — Uplar</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200">
<style>
:root{
    --p:#3b6ff5;--p-dark:#2a55d9;--p-dim:rgba(59,111,245,.08);
    --ok:#10b981;--ok-dim:rgba(16,185,129,.09);
    --err:#ef4444;--err-dim:rgba(239,68,68,.08);
    --ink:#0f1c2e;--muted:#5a6a7a;--faint:#94a3b8;
    --bg:#f4f6fb;--card:#ffffff;--border:#e4eaf2;
    --plan-cor:<?= htmlspecialchars($pi['cor']) ?>;
}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{
    font-family:'Inter',sans-serif;background:var(--bg);color:var(--ink);
    min-height:100vh;display:flex;align-items:center;justify-content:center;
    padding:1.5rem;-webkit-font-smoothing:antialiased;
}
button,input{font-family:inherit}

@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes shake{0%,100%{transform:translateX(0)}25%,75%{transform:translateX(-5px)}50%{transform:translateX(5px)}}

/* Layout */
.wrap{
    display:grid;
    grid-template-columns:300px 420px;
    gap:1.2rem;max-width:760px;width:100%;
    animation:fadeUp .5s cubic-bezier(.22,1,.36,1) both;
}

/* ── Painel esquerdo ── */
.info-panel{
    border-radius:18px;padding:1.9rem 1.6rem;
    color:#fff;display:flex;flex-direction:column;
    position:relative;overflow:hidden;min-height:460px;
    background:var(--plan-cor);
}
/* círculos decorativos */
.info-panel::before{content:'';position:absolute;top:-60px;right:-60px;width:200px;height:200px;border-radius:50%;background:rgba(255,255,255,.08);pointer-events:none}
.info-panel::after{content:'';position:absolute;bottom:-80px;left:-40px;width:230px;height:230px;border-radius:50%;background:rgba(255,255,255,.05);pointer-events:none}

.ip-logo{display:flex;align-items:center;gap:.5rem;font-size:.95rem;font-weight:800;letter-spacing:-.3px;opacity:.9;position:relative;z-index:1}
.ip-logo .material-symbols-outlined{font-size:1.2rem}
.ip-body{position:relative;z-index:1;margin-top:auto;padding-top:1.8rem}
.ip-badge{display:inline-flex;align-items:center;gap:.3rem;background:rgba(255,255,255,.18);border:1px solid rgba(255,255,255,.22);border-radius:50px;padding:.24rem .68rem;font-size:.69rem;font-weight:700;margin-bottom:.8rem}
.ip-badge .material-symbols-outlined{font-size:.8rem}
.ip-title{font-size:1.45rem;font-weight:800;letter-spacing:-.03em;line-height:1.15;margin-bottom:.38rem}
.ip-sub{font-size:.79rem;opacity:.78;line-height:1.6;margin-bottom:1.1rem}
.ip-grid{display:grid;grid-template-columns:1fr 1fr;gap:.45rem;margin-bottom:1rem}
.ip-stat{background:rgba(255,255,255,.14);border-radius:9px;padding:.55rem .7rem}
.ip-stat-l{font-size:.59rem;opacity:.68;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:.08rem}
.ip-stat-v{font-size:.88rem;font-weight:800;line-height:1.2}
.ip-feats{display:flex;flex-direction:column;gap:.32rem}
.ip-feat{display:flex;align-items:center;gap:.38rem;font-size:.75rem;opacity:.88}
.ip-feat .material-symbols-outlined{font-size:.88rem;opacity:.7}

/* ── Painel direito (formulário) ── */
.form-panel{
    background:var(--card);border-radius:18px;padding:2rem 1.8rem;
    box-shadow:0 1px 4px rgba(15,28,46,.06),0 4px 16px rgba(15,28,46,.04);
    border:1px solid var(--border);
}
.fp-title{font-size:1.2rem;font-weight:800;letter-spacing:-.03em;margin-bottom:.22rem}
.fp-sub{font-size:.79rem;color:var(--muted);margin-bottom:1.35rem;line-height:1.55}

/* Alert erro */
.alert-err{
    background:var(--err-dim);border:1px solid rgba(239,68,68,.18);
    border-radius:9px;padding:.65rem .85rem;
    font-size:.79rem;color:#b91c1c;
    display:flex;align-items:flex-start;gap:.42rem;
    animation:shake .32s ease both;margin-bottom:.8rem;
}
.alert-err .material-symbols-outlined{font-size:.92rem;flex-shrink:0;margin-top:1px}

/* Campos */
.fg{display:flex;flex-direction:column;gap:.7rem}
.fl{display:flex;flex-direction:column;gap:.26rem}

.fl label{
    font-size:.66rem;font-weight:700;color:var(--muted);
    text-transform:uppercase;letter-spacing:.05em;
    display:flex;align-items:center;gap:.22rem;
}
.req{color:var(--err)}

/* ── INPUT COM ÍCONE ──
   O ícone fica à esquerda com position:absolute.
   O input tem padding-left grande o suficiente para nunca sobrepor o texto.
   Medida segura: ícone a left:10px, input padding-left:38px
*/
.inp-wrap{position:relative;display:flex;align-items:center}

.inp-ico{
    position:absolute;
    left:10px;
    top:50%;
    transform:translateY(-50%);
    color:var(--faint);
    font-size:18px;       /* tamanho fixo em px, não em rem */
    line-height:1;
    pointer-events:none;
    display:flex;
    align-items:center;
    z-index:1;
}

/* Botão "ver senha" fica à direita */
.inp-wrap input[type="password"],
.inp-wrap input[type="text"]{
    padding-right:40px;   /* espaço para o botão do olho */
}

.fl input{
    width:100%;
    padding:.68rem .9rem .68rem 38px;   /* ← left padding fixo 38px garante espaço após ícone */
    border:1.5px solid var(--border);
    border-radius:9px;
    font-size:.86rem;
    outline:none;
    color:var(--ink);
    background:var(--card);
    transition:border-color .18s,box-shadow .18s;
}
.fl input:focus{border-color:var(--p);box-shadow:0 0 0 3px var(--p-dim)}
.fl input.err{border-color:var(--err);box-shadow:0 0 0 3px var(--err-dim)}
.fl .hint{font-size:.66rem;color:var(--faint);margin-top:.06rem;line-height:1.45}

/* Força de senha */
.pw-strength{height:3px;border-radius:20px;background:var(--border);margin-top:.32rem;overflow:hidden}
.pw-bar{height:100%;border-radius:20px;transition:width .3s,background .3s;width:0}
.pw-txt{font-size:.64rem;margin-top:.2rem;font-weight:600;color:var(--faint)}

/* Olho (toggle senha) */
.pw-eye{
    position:absolute;right:10px;top:50%;transform:translateY(-50%);
    background:none;border:none;cursor:pointer;
    color:var(--faint);padding:.15rem;transition:color .15s;
    font-size:18px;display:flex;align-items:center;z-index:1;
}
.pw-eye:hover{color:var(--p)}

/* Checklist requisitos */
.req-list{
    display:grid;grid-template-columns:1fr 1fr;gap:.16rem .45rem;
    padding:.58rem .72rem;background:var(--bg);border-radius:8px;margin-top:.22rem;
}
.req-item{font-size:.65rem;color:var(--muted);display:flex;align-items:center;gap:.2rem;transition:color .18s}
.req-item.ok{color:var(--ok)}
.req-item .material-symbols-outlined{font-size:.78rem;flex-shrink:0}

.divider{height:1px;background:var(--border);margin:.75rem 0}

/* Submit */
.btn-submit{
    width:100%;padding:.8rem;border-radius:10px;border:none;
    background:var(--plan-cor);color:#fff;
    font-family:'Inter',sans-serif;font-size:.89rem;font-weight:700;
    display:flex;align-items:center;justify-content:center;gap:.42rem;
    transition:all .2s;cursor:pointer;margin-top:.32rem;
}
.btn-submit:hover{filter:brightness(1.08);transform:translateY(-2px)}
.btn-submit:disabled{background:#94a3b8;box-shadow:none;transform:none;cursor:not-allowed}
.btn-submit .material-symbols-outlined{font-size:.93rem}
.spinner{width:16px;height:16px;border:2.5px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite}

.login-link{text-align:center;font-size:.74rem;color:var(--muted);margin-top:.82rem}
.login-link a{color:var(--p);font-weight:600}
.login-link a:hover{text-decoration:underline}

@media(max-width:780px){.wrap{grid-template-columns:1fr;max-width:420px}}
@media(max-width:460px){.info-panel{padding:1.3rem;min-height:auto}.form-panel{padding:1.4rem 1.1rem}}
</style>
</head>
<body>
<div class="wrap">

    <!-- Painel esquerdo — info do plano -->
    <div class="info-panel">
        <div class="ip-logo">
            <span class="material-symbols-outlined">inventory_2</span>Uplar
        </div>
        <div class="ip-body">
            <div class="ip-badge">
                <span class="material-symbols-outlined"><?= htmlspecialchars($pi['icone']) ?></span>
                Plano <?= htmlspecialchars($pi['label']) ?>
            </div>
            <div class="ip-title"><?= htmlspecialchars($modo_titulo) ?></div>
            <div class="ip-sub"><?= htmlspecialchars($modo_sub) ?></div>
            <div class="ip-grid">
                <div class="ip-stat"><div class="ip-stat-l">Plano</div><div class="ip-stat-v"><?= htmlspecialchars($pi['label']) ?></div></div>
                <div class="ip-stat"><div class="ip-stat-l">Valor</div><div class="ip-stat-v"><?= htmlspecialchars($pi['preco']) ?></div></div>
                <div class="ip-stat"><div class="ip-stat-l">Acesso</div><div class="ip-stat-v"><?= $plano_dias ?> dias</div></div>
                <div class="ip-stat"><div class="ip-stat-l">Válido até</div><div class="ip-stat-v"><?= $data_fim_fmt ?></div></div>
            </div>
            <div class="ip-feats">
                <?php foreach(['Estoque ilimitado','Vitrine online','Relatórios completos','Financeiro integrado','Múltiplos vendedores','Suporte WhatsApp'] as $f): ?>
                <div class="ip-feat"><span class="material-symbols-outlined">check_circle</span><?= htmlspecialchars($f) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Painel direito — formulário -->
    <div class="form-panel">
        <div class="fp-title"><?= htmlspecialchars($fp_title) ?></div>
        <div class="fp-sub"><?= htmlspecialchars($fp_sub) ?></div>

        <?php if ($erro): ?>
        <div class="alert-err">
            <span class="material-symbols-outlined">error</span><?= htmlspecialchars($erro) ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="formReg" onsubmit="return validar()">
            <?= csrf_input() ?>
            <div class="fg">

                <!-- Nome de usuário -->
                <div class="fl">
                    <label>
                        <span class="material-symbols-outlined" style="font-size:.8rem">alternate_email</span>
                        Nome de usuário <span class="req">*</span>
                    </label>
                    <div class="inp-wrap">
                        <span class="material-symbols-outlined inp-ico">person</span>
                        <input type="text" name="username" id="username"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               placeholder="meu.usuario" maxlength="40"
                               autocomplete="username"
                               oninput="validarUsuario(this)">
                    </div>
                    <div class="hint" id="usernameHint">Letras, números, ponto, hífen ou sublinhado. Mín. 3 chars.</div>
                </div>

                <div class="divider"></div>

                <!-- Senha -->
                <div class="fl">
                    <label>
                        <span class="material-symbols-outlined" style="font-size:.8rem">lock</span>
                        Senha <span class="req">*</span>
                    </label>
                    <div class="inp-wrap">
                        <span class="material-symbols-outlined inp-ico">key</span>
                        <input type="password" name="senha" id="senha"
                               placeholder="Mínimo 8 caracteres"
                               autocomplete="new-password"
                               oninput="checkStrength(this.value)">
                        <button type="button" class="pw-eye" onclick="togglePw('senha',this)" tabindex="-1">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                    <div class="pw-strength"><div class="pw-bar" id="pwBar"></div></div>
                    <div class="pw-txt" id="pwTxt">Digite uma senha</div>
                    <div class="req-list">
                        <div class="req-item" id="req-len"><span class="material-symbols-outlined">radio_button_unchecked</span>8 caracteres</div>
                        <div class="req-item" id="req-up"><span class="material-symbols-outlined">radio_button_unchecked</span>Maiúscula</div>
                        <div class="req-item" id="req-num"><span class="material-symbols-outlined">radio_button_unchecked</span>Número</div>
                        <div class="req-item" id="req-sym"><span class="material-symbols-outlined">radio_button_unchecked</span>Símbolo</div>
                    </div>
                </div>

                <!-- Confirmar senha -->
                <div class="fl">
                    <label>
                        <span class="material-symbols-outlined" style="font-size:.8rem">lock_reset</span>
                        Confirmar senha <span class="req">*</span>
                    </label>
                    <div class="inp-wrap">
                        <span class="material-symbols-outlined inp-ico">key</span>
                        <input type="password" name="senha2" id="senha2"
                               placeholder="Repita a senha"
                               autocomplete="new-password"
                               oninput="checkMatch()">
                        <button type="button" class="pw-eye" onclick="togglePw('senha2',this)" tabindex="-1">
                            <span class="material-symbols-outlined">visibility</span>
                        </button>
                    </div>
                    <div class="hint" id="matchHint"></div>
                </div>

                <button type="submit" class="btn-submit" id="btnSubmit">
                    <span class="material-symbols-outlined">rocket_launch</span><?= htmlspecialchars($btn_label) ?>
                </button>
            </div>
        </form>

        <div class="login-link">Já tem conta? <a href="login.php">Entrar</a></div>
    </div>

</div>
<script>
const $ = id => document.getElementById(id);
const reqs = {
    len:{el:$('req-len'),test:v=>v.length>=8},
    up: {el:$('req-up'), test:v=>/[A-Z]/.test(v)},
    num:{el:$('req-num'),test:v=>/[0-9]/.test(v)},
    sym:{el:$('req-sym'),test:v=>/[^A-Za-z0-9]/.test(v)},
};
function checkStrength(v){
    let s=0;
    Object.values(reqs).forEach(r=>{
        const ok=r.test(v); if(ok)s++;
        r.el.className='req-item'+(ok?' ok':'');
        r.el.querySelector('.material-symbols-outlined').textContent=ok?'check_circle':'radio_button_unchecked';
    });
    const bar=$('pwBar'),txt=$('pwTxt');
    const ws=['0%','25%','50%','75%','100%'],cs=['','#ef4444','#f59e0b','#f59e0b','#10b981'],ls=['','Muito fraca','Fraca','Boa','Forte '];
    bar.style.width=ws[s];bar.style.background=cs[s];
    txt.textContent=ls[s];txt.style.color=cs[s]||'var(--faint)';
    checkMatch();
}
function checkMatch(){
    const s1=$('senha').value,s2=$('senha2').value,h=$('matchHint');
    if(!s2){h.textContent='';return}
    h.style.color=s1===s2?'var(--ok)':'var(--err)';
    h.textContent=s1===s2?' Senhas conferem':' Senhas não conferem';
}
function validarUsuario(inp){
    const v=inp.value,h=$('usernameHint');
    const ok=v.length>=3&&/^[a-zA-Z0-9_.\-]+$/.test(v);
    inp.className=v?(ok?'':'err'):'';
    if(v&&!ok){h.style.color='var(--err)';h.textContent=v.length<3?'Mínimo 3 caracteres.':'Caractere inválido.';}
    else{h.style.color='';h.textContent='Letras, números, ponto, hífen ou sublinhado. Mín. 3 chars.';}
}
function togglePw(id,btn){
    const inp=$(id);
    inp.type=inp.type==='password'?'text':'password';
    btn.querySelector('.material-symbols-outlined').textContent=inp.type==='password'?'visibility':'visibility_off';
}
function validar(){
    const u=$('username').value.trim(),s=$('senha').value,c=$('senha2').value;
    if(u.length<3||!/^[a-zA-Z0-9_.\-]+$/.test(u)){$('username').classList.add('err');$('username').focus();return false;}
    if(!Object.values(reqs).every(r=>r.test(s))||s!==c)return false;
    const btn=$('btnSubmit');btn.disabled=true;
    btn.innerHTML='<div class="spinner"></div>Criando conta…';
    return true;
}
</script>
</body>
</html>