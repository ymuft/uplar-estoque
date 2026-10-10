<?php
// config_api.php — Backend de todas as ações da página de configurações
session_start();
require 'db.php';
require 'helpers.php';

header('Content-Type: application/json; charset=utf-8');
send_security_headers();

function ok($data = [])  { echo json_encode(array_merge(['ok' => true], $data)); exit; }
function err($msg, $code = 400) { http_response_code($code); echo json_encode(['ok' => false, 'error' => $msg]); exit; }

// ── Autenticação ──────────────────────────────────────────────
if (!is_fully_authenticated()) err('Não autenticado', 401);
$user_id = (int)$_SESSION['user_id'];
$pdo     = get_pdo();

// ── CSRF ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') err('Método inválido', 405);
$token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    err('Token de segurança inválido', 403);
}

$acao = trim($_POST['acao'] ?? '');

if ($acao === 'salvar_passcode' || $acao === 'desativar_passcode') {
    $senhaAtual = (string)($_POST['senha_atual'] ?? '');
    if ($senhaAtual === '') err('Informe sua senha atual.');

    $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    $usuario = $stmt->fetch();
    if (!$usuario || !password_verify($senhaAtual, $usuario['password'])) err('Senha atual incorreta.');

    if ($acao === 'desativar_passcode') {
        $pdo->prepare('UPDATE users SET passcode_hash = NULL, passcode_configured_at = NULL, passcode_required = 0, auth_version = auth_version + 1 WHERE id = ?')->execute([$user_id]);
        $pdo->prepare('DELETE FROM passcode_tentativas WHERE user_id = ?')->execute([$user_id]);
        $_SESSION['auth_version'] = (int)($_SESSION['auth_version'] ?? 1) + 1;
        ok(['message' => 'Pass Code desativado.']);
    }

    $passcode = (string)($_POST['passcode'] ?? '');
    $confirmacao = (string)($_POST['passcode_confirmacao'] ?? '');
    if (!preg_match('/^\d{6}$/D', $passcode)) err('O Pass Code deve ter exatamente 6 números.');
    if (!hash_equals($passcode, $confirmacao)) err('A confirmação do Pass Code não confere.');

    $hash = password_hash($passcode, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET passcode_hash = ?, passcode_configured_at = NOW(), passcode_required = 0, passcode_prompt_dismissed_at = NULL, auth_version = auth_version + 1 WHERE id = ?')->execute([$hash, $user_id]);
    $pdo->prepare('DELETE FROM passcode_tentativas WHERE user_id = ?')->execute([$user_id]);
    $_SESSION['auth_version'] = (int)($_SESSION['auth_version'] ?? 1) + 1;
    ok(['message' => 'Pass Code ativado. Sua conta agora possui uma camada adicional de proteção durante o login.']);
}

if ($acao === 'dispensar_incentivo_passcode') {
    $pdo->prepare('UPDATE users SET passcode_prompt_dismissed_at = NOW() WHERE id = ?')->execute([$user_id]);
    ok();
}

/* ════════════════════════════════════════════════════════════
   SALVAR DADOS DA EMPRESA
════════════════════════════════════════════════════════════ */
if ($acao === 'salvar_empresa') {

    $razao        = trim($_POST['razao_social']  ?? '');
    $fantasia     = trim($_POST['nome_fantasia'] ?? '');
    $cnpj         = trim($_POST['cnpj']          ?? '');
    $telefone     = trim($_POST['telefone']       ?? '');
    $email        = trim($_POST['email']          ?? '');
    $cep          = trim($_POST['cep']            ?? '');
    $endereco     = trim($_POST['endereco']       ?? '');
    $cidade       = trim($_POST['cidade']         ?? '');
    $estado       = trim($_POST['estado']         ?? '');

    if (!$razao) err('Razão Social é obrigatória.');

    if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) err('E-mail inválido.');

    $ufs = ['','AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG',
            'PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
    if ($estado && !in_array($estado, $ufs)) $estado = '';

    // UPSERT (INSERT ou UPDATE)
    $stmt = $pdo->prepare("
        INSERT INTO empresa_config
            (user_id, razao_social, nome_fantasia, cnpj, telefone, email, cep, endereco, cidade, estado)
        VALUES
            (:uid, :rs, :nf, :cnpj, :tel, :email, :cep, :end, :cid, :est)
        ON DUPLICATE KEY UPDATE
            razao_social  = VALUES(razao_social),
            nome_fantasia = VALUES(nome_fantasia),
            cnpj          = VALUES(cnpj),
            telefone      = VALUES(telefone),
            email         = VALUES(email),
            cep           = VALUES(cep),
            endereco      = VALUES(endereco),
            cidade        = VALUES(cidade),
            estado        = VALUES(estado),
            atualizado_em = CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        ':uid'   => $user_id,
        ':rs'    => $razao,
        ':nf'    => $fantasia,
        ':cnpj'  => $cnpj,
        ':tel'   => $telefone,
        ':email' => $email,
        ':cep'   => $cep,
        ':end'   => $endereco,
        ':cid'   => $cidade,
        ':est'   => $estado,
    ]);

    ok(['message' => 'Dados da empresa salvos com sucesso']);
}

/* ════════════════════════════════════════════════════════════
   SALVAR LOGO (upload + tamanho)
════════════════════════════════════════════════════════════ */
if ($acao === 'salvar_logo') {

    $logo_size = max(60, min(300, (int)($_POST['logo_size'] ?? 120)));
    $logo_path = null; // só atualiza se enviou arquivo

    // Garante que a config existe antes de tentar atualizar
    $check = $pdo->prepare("SELECT id, logo_path FROM empresa_config WHERE user_id = ?");
    $check->execute([$user_id]);
    $cfg = $check->fetch();

    if (!$cfg) {
        // Cria registro base vazio para poder atualizar depois
        $pdo->prepare("INSERT IGNORE INTO empresa_config (user_id) VALUES (?)")->execute([$user_id]);
        $cfg = ['id' => $pdo->lastInsertId(), 'logo_path' => ''];
    }

    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $file    = $_FILES['logo'];
        try {
            $logo_path = save_uploaded_file($file, 'logos', 'logo_' . $user_id . '_', 'logo');
            delete_managed_file($cfg['logo_path'], ['logos']);
        } catch (RuntimeException $e) {
            err($e->getMessage());
        }
    }

    // Monta UPDATE dinâmico
    if ($logo_path !== null) {
        $stmt = $pdo->prepare("
            UPDATE empresa_config
            SET logo_path = :lp, logo_size = :ls, atualizado_em = CURRENT_TIMESTAMP
            WHERE user_id = :uid
        ");
        $stmt->execute([':lp' => $logo_path, ':ls' => $logo_size, ':uid' => $user_id]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE empresa_config
            SET logo_size = :ls, atualizado_em = CURRENT_TIMESTAMP
            WHERE user_id = :uid
        ");
        $stmt->execute([':ls' => $logo_size, ':uid' => $user_id]);
    }

    ok([
        'message'   => 'Logo salva com sucesso',
        'logo_path' => $logo_path ?? ($cfg['logo_path'] ?? ''),
        'logo_size' => $logo_size,
    ]);
}

/* ════════════════════════════════════════════════════════════
   DELETAR LOGO
════════════════════════════════════════════════════════════ */
if ($acao === 'deletar_logo') {

    $stmt = $pdo->prepare("SELECT logo_path FROM empresa_config WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cfg = $stmt->fetch();

    delete_managed_file($cfg['logo_path'] ?? '', ['logos']);

    $pdo->prepare("UPDATE empresa_config SET logo_path = '', atualizado_em = CURRENT_TIMESTAMP WHERE user_id = ?")
        ->execute([$user_id]);

    ok(['message' => 'Logo removida com sucesso']);
}

/* ════════════════════════════════════════════════════════════
   ALTERAR SENHA
════════════════════════════════════════════════════════════ */
if ($acao === 'alterar_senha') {

    $senha_atual = $_POST['senha_atual'] ?? '';
    $senha_nova  = $_POST['senha_nova']  ?? '';

    if (!$senha_atual) err('Informe a senha atual.');
    if (strlen($senha_nova) < 8)  err('A nova senha deve ter ao menos 8 caracteres.');
    if (!preg_match('/[A-Z]/', $senha_nova)) err('A nova senha precisa ter ao menos uma letra maiúscula.');
    if (!preg_match('/[0-9]/', $senha_nova)) err('A nova senha precisa ter ao menos um número.');
    if (!preg_match('/[^A-Za-z0-9]/', $senha_nova)) err('A nova senha precisa ter ao menos um símbolo (!@#...).');

    // Busca hash atual
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($senha_atual, $row['password'])) {
        err('Senha atual incorreta.');
    }

    $hash = password_hash($senha_nova, PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = ?, auth_version = auth_version + 1 WHERE id = ?")->execute([$hash, $user_id]);
    $_SESSION['auth_version'] = (int)($_SESSION['auth_version'] ?? 1) + 1;

    ok(['message' => 'Senha alterada com sucesso']);
}

err('Ação inválida ou não reconhecida.');
