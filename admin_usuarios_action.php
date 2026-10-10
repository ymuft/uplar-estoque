<?php
// admin_usuarios_action.php — COM GESTÃO DE ASSINATURAS
session_start();
require 'db.php';
require 'helpers.php';
require 'subscription.php';
require 'planos_config.php';

header('Content-Type: application/json; charset=utf-8');

function jok($data = []) { echo json_encode(array_merge(['ok' => true], $data)); exit; }
function jerr($msg, $code = 400) { http_response_code($code); echo json_encode(['ok' => false, 'error' => $msg]); exit; }

if (!isset($_SESSION['user_id'])) jerr('Não autenticado', 401);
$me_id = (int)$_SESSION['user_id'];
$pdo   = get_pdo();

$stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$stmt->execute([$me_id]);
$me = $stmt->fetch();
if (!$me || $me['role'] !== 'admin') jerr('Acesso negado', 403);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jerr('Método inválido', 405);
$token = $_POST['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) jerr('CSRF inválido', 403);

$action = trim($_POST['action'] ?? '');

/* ── VISIBILIDADE DE PAGAMENTOS NO PAINEL ── */
if (in_array($action, ['hide_payment', 'restore_payment'], true)) {
    $payment_id = (int)($_POST['payment_id'] ?? 0);
    if ($payment_id <= 0) jerr('Pagamento inválido.');

    $stmt_payment = $pdo->prepare('SELECT id, status FROM pedidos_pagamento WHERE id = ? LIMIT 1');
    $stmt_payment->execute([$payment_id]);
    $payment = $stmt_payment->fetch(PDO::FETCH_ASSOC);
    if (!$payment || $payment['status'] !== 'pago') jerr('Pagamento confirmado não encontrado.', 404);

    if ($action === 'hide_payment') {
        $pdo->prepare('UPDATE pedidos_pagamento
            SET admin_oculto = 1, admin_oculto_em = NOW(), admin_oculto_por = ?
            WHERE id = ?')
            ->execute([$me_id, $payment_id]);
        jok(['hidden' => true]);
    }

    $pdo->prepare('UPDATE pedidos_pagamento
        SET admin_oculto = 0, admin_oculto_em = NULL, admin_oculto_por = NULL
        WHERE id = ?')
        ->execute([$payment_id]);
    jok(['hidden' => false]);
}

/* ── PREÇOS DOS PLANOS ── */
if ($action === 'save_plan_prices') {
    $precos = [
        'mensal' => preco_plano_para_centavos((string)($_POST['mensal'] ?? '')),
        'trimestral' => preco_plano_para_centavos((string)($_POST['trimestral'] ?? '')),
        'anual' => preco_plano_para_centavos((string)($_POST['anual'] ?? '')),
    ];
    foreach ($precos as $centavos) {
        if ($centavos < 100 || $centavos > 10000000) jerr('Informe valores válidos para todos os planos.');
    }
    try {
        ensure_planos_config_table($pdo);
        $pdo->beginTransaction();
        atualizar_precos_planos($pdo, $precos, $me_id);
        $pdo->commit();
        jok([
            'planos' => array_map(fn($centavos) => formatar_preco_plano($centavos), $precos),
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jerr('Não foi possível atualizar os preços.', 500);
    }
}

/* ── CRIAR ── */
if ($action === 'create') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'user';
    if (strlen($username) < 3)                    jerr('Nome deve ter ao menos 3 caracteres');
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $username)) jerr('Nome inválido');
    if (strlen($password) < 6)                    jerr('Senha deve ter ao menos 6 caracteres');
    if (!in_array($role, ['user','admin']))         $role = 'user';
    $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $chk->execute([$username]);
    if ($chk->fetch()) jerr('Nome de usuário já está em uso');
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)")->execute([$username, $hash, $role]);
    jok(['id' => (int)$pdo->lastInsertId()]);
}

/* ── ALTERAR SENHA ── */
if ($action === 'change_password') {
    $target_id = (int)($_POST['user_id'] ?? 0);
    $password  = $_POST['password'] ?? '';
    if ($target_id <= 0)          jerr('ID inválido');
    if (strlen($password) < 6)    jerr('Senha deve ter ao menos 6 caracteres');
    $chk = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $chk->execute([$target_id]);
    if (!$chk->fetch()) jerr('Usuário não encontrado');
    $pdo->prepare("UPDATE users SET password = ?, auth_version = auth_version + 1 WHERE id = ?")->execute([password_hash($password, PASSWORD_DEFAULT), $target_id]);
    if ($target_id === $me_id) $_SESSION['auth_version'] = (int)($_SESSION['auth_version'] ?? 1) + 1;
    jok();
}

/* ── RESETAR PASS CODE E EXIGIR NOVA CONFIGURAÇÃO ── */
if ($action === 'reset_passcode') {
    $target_id = (int)($_POST['user_id'] ?? 0);
    if ($target_id <= 0) jerr('ID inválido');
    $chk = $pdo->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $chk->execute([$target_id]);
    if (!$chk->fetch()) jerr('Usuário não encontrado');

    $pdo->prepare('UPDATE users SET passcode_hash = NULL, passcode_configured_at = NULL, passcode_required = 1, auth_version = auth_version + 1 WHERE id = ?')
        ->execute([$target_id]);
    $pdo->prepare('DELETE FROM passcode_tentativas WHERE user_id = ?')->execute([$target_id]);
    if ($target_id === $me_id) $_SESSION['auth_version'] = (int)($_SESSION['auth_version'] ?? 1) + 1;
    jok(['message' => 'Pass Code resetado. O usuário deverá configurar um novo código no próximo acesso.']);
}

/* ── ALTERAR ROLE ── */
if ($action === 'change_role') {
    $target_id = (int)($_POST['user_id'] ?? 0);
    $role      = $_POST['role'] ?? 'user';
    if ($target_id <= 0)                       jerr('ID inválido');
    if ($target_id === $me_id)                 jerr('Não pode alterar seu próprio nível');
    if (!in_array($role, ['user','admin']))     jerr('Role inválido');
    $chk = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $chk->execute([$target_id]);
    if (!$chk->fetch()) jerr('Usuário não encontrado');
    $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$role, $target_id]);
    jok();
}

/* ── EXCLUIR ── */
if ($action === 'delete') {
    $target_id = (int)($_POST['user_id'] ?? 0);
    if ($target_id <= 0)       jerr('ID inválido');
    if ($target_id === $me_id) jerr('Não pode excluir sua própria conta');
    $chk = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $chk->execute([$target_id]);
    if (!$chk->fetch()) jerr('Usuário não encontrado');
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM inscricoes_historico WHERE user_id = ?")->execute([$target_id]);
        $pdo->prepare("DELETE FROM inscricoes WHERE user_id = ?")->execute([$target_id]);
        $pdo->prepare("DELETE FROM vendas WHERE user_id = ?")->execute([$target_id]);
        $pdo->prepare("DELETE FROM vendedor_produto WHERE vendedor_id IN (SELECT id FROM vendedores WHERE user_id = ?)")->execute([$target_id]);
        $pdo->prepare("DELETE FROM vendedores WHERE user_id = ?")->execute([$target_id]);
        $pdo->prepare("DELETE FROM produtos WHERE user_id = ?")->execute([$target_id]);
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$target_id]);
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        jerr('Erro interno ao excluir', 500);
    }
    jok();
}

/* ══════════════════════════════════════════════════════
   ── RENOVAR ASSINATURA ──
══════════════════════════════════════════════════════ */
if ($action === 'renovar_inscricao') {
    $target_id  = (int)($_POST['user_id']    ?? 0);
    $plano_dias = (int)($_POST['plano_dias'] ?? 0);
    $obs        = trim($_POST['observacao']  ?? '');

    if ($target_id <= 0)                       jerr('ID de usuário inválido');
    if (!in_array($plano_dias, [3, 30, 90, 365], true)) jerr('Plano inválido.');

    $chk = $pdo->prepare("SELECT id, role, username FROM users WHERE id = ?");
    $chk->execute([$target_id]);
    $target = $chk->fetch(PDO::FETCH_ASSOC);
    if (!$target)                  jerr('Usuário não encontrado');
    if ($target['role'] === 'admin') jerr('Admins não precisam de assinatura.');

    try {
        $inscricao_id = renovar_inscricao($target_id, $plano_dias, $me_id, $pdo, $obs);
        $sub          = get_subscription($target_id, $pdo);
        jok([
            'inscricao_id'   => $inscricao_id,
            'data_fim'       => $sub['data_fim'],
            'data_fim_br'    => fmt_date_br($sub['data_fim']),
            'dias_restantes' => $sub['dias_restantes'],
            'plano_dias'     => $plano_dias,
            'username'       => $target['username'],
        ]);
    } catch (Throwable $e) {
        jerr('Erro ao renovar: ' . $e->getMessage(), 500);
    }
}

/* ══════════════════════════════════════════════════════
   ── SUSPENDER ASSINATURA ──
══════════════════════════════════════════════════════ */
if ($action === 'suspender_inscricao') {
    $target_id = (int)($_POST['user_id'] ?? 0);
    if ($target_id <= 0)       jerr('ID inválido');
    if ($target_id === $me_id) jerr('Não pode suspender sua própria conta');

    $chk = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $chk->execute([$target_id]);
    $t = $chk->fetch();
    if (!$t || $t['role'] === 'admin') jerr('Operação inválida');

    $res = $pdo->prepare("UPDATE inscricoes SET status = 'suspensa' WHERE user_id = ? AND status = 'ativa'");
    $res->execute([$target_id]);

    if ($res->rowCount() > 0) {
        $ins_row = $pdo->prepare("SELECT id FROM inscricoes WHERE user_id = ? ORDER BY criado_em DESC LIMIT 1");
        $ins_row->execute([$target_id]);
        $ins = $ins_row->fetch();
        if ($ins) {
            $pdo->prepare("INSERT INTO inscricoes_historico (user_id, inscricao_id, acao, admin_id) VALUES (?, ?, 'suspensa', ?)")
                ->execute([$target_id, $ins['id'], $me_id]);
        }
    }
    jok(['suspended' => $res->rowCount() > 0]);
}

/* ══════════════════════════════════════════════════════
   ── DETALHES DO USUÁRIO (com assinatura) ──
══════════════════════════════════════════════════════ */
if ($action === 'get_user_details') {
    $target_id = (int)($_POST['user_id'] ?? 0);
    if ($target_id <= 0) jerr('ID inválido');

    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.role, u.whatsapp_number,
               DATE_FORMAT(u.created_at, '%d/%m/%Y às %H:%i') AS data_criacao,
               COUNT(DISTINCT p.id)     AS total_produtos,
               COUNT(DISTINCT v.id)     AS total_vendas,
               COUNT(DISTINCT vend.id)  AS total_vendedores,
               COALESCE(SUM(v.total),0) AS receita_total
        FROM users u
        LEFT JOIN produtos   p    ON p.user_id    = u.id
        LEFT JOIN vendas     v    ON v.user_id    = u.id
        LEFT JOIN vendedores vend ON vend.user_id = u.id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$target_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) jerr('Usuário não encontrado');

    $stmt2 = $pdo->prepare("SELECT * FROM empresa_config WHERE user_id = ?");
    $stmt2->execute([$target_id]);
    $empresa = $stmt2->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($empresa && !empty($empresa['logo_path']) && !file_exists($empresa['logo_path'])) {
        $empresa['logo_path'] = '';
    }

    $logins = [];
    try {
        $stmt3 = $pdo->prepare("
            SELECT ip, dispositivo, status,
                   DATE_FORMAT(criado_em, '%d/%m/%Y') AS data_fmt,
                   DATE_FORMAT(criado_em, '%H:%i')    AS hora_fmt
            FROM login_logs WHERE user_id = ? ORDER BY criado_em DESC LIMIT 10
        ");
        $stmt3->execute([$target_id]);
        $logins = $stmt3->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {}

    $fin = ['despesas_mes' => 0, 'receitas_mes' => 0];
    try {
        $stmt4 = $pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN tipo='despesa' THEN valor ELSE 0 END),0) AS despesas_mes,
                COALESCE(SUM(CASE WHEN tipo='receita' THEN valor ELSE 0 END),0) AS receitas_mes
            FROM (
                SELECT valor,'despesa' AS tipo FROM despesas
                WHERE user_id=? AND MONTH(data_despesa)=MONTH(NOW()) AND YEAR(data_despesa)=YEAR(NOW())
                UNION ALL
                SELECT valor,'receita' AS tipo FROM receitas_extras
                WHERE user_id=? AND MONTH(data_receita)=MONTH(NOW()) AND YEAR(data_receita)=YEAR(NOW())
            ) t
        ");
        $stmt4->execute([$target_id, $target_id]);
        $fin = $stmt4->fetch(PDO::FETCH_ASSOC) ?: $fin;
    } catch (PDOException $e) {}

    // Assinatura atual
    $sub = get_subscription($target_id, $pdo);

    // Histórico de assinaturas
    $sub_historico = [];
    try {
        $stmt5 = $pdo->prepare("
            SELECT ih.acao, ih.plano_dias, ih.data_fim_anterior, ih.data_fim_nova, ih.observacao,
                   DATE_FORMAT(ih.criado_em, '%d/%m/%Y às %H:%i') AS data_fmt,
                   u.username AS admin_nome,
                   pp.order_nsu, pp.plano_key, pp.valor_centavos, pp.gateway_amount_centavos,
                   pp.paid_amount_centavos, pp.installments, pp.capture_method, pp.receipt_url,
                   pp.invoice_slug, pp.transaction_nsu, pp.items_json,
                   DATE_FORMAT(pp.pago_em, '%d/%m/%Y às %H:%i') AS pago_em_fmt,
                   payer.username AS payer_nome, payer.email AS payer_email, payer.telefone AS payer_telefone
            FROM inscricoes_historico ih
            LEFT JOIN users u ON u.id = ih.admin_id
            LEFT JOIN pedidos_pagamento pp
                ON pp.user_id = ih.user_id
               AND LOCATE(BINARY pp.order_nsu, BINARY ih.observacao) > 0
            LEFT JOIN users payer ON payer.id = pp.user_id
            WHERE ih.user_id = ?
            ORDER BY ih.criado_em DESC LIMIT 10
        ");
        $stmt5->execute([$target_id]);
        $sub_historico = $stmt5->fetchAll(PDO::FETCH_ASSOC);
        foreach ($sub_historico as &$historico) {
            $items = json_decode((string)($historico['items_json'] ?? ''), true);
            $historico['payment_items'] = is_array($items) ? $items : [];
            unset($historico['items_json']);
        }
        unset($historico);
    } catch (Throwable $e) {}

    jok([
        'user'          => $user,
        'empresa'       => $empresa,
        'logins'        => $logins,
        'ultimo_login'  => count($logins) > 0 ? $logins[0] : null,
        'financeiro'    => $fin,
        'subscription'  => $sub,
        'sub_historico' => $sub_historico,
    ]);
}

jerr('Ação inválida');
