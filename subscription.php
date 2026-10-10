<?php
// subscription.php — Helper central de assinaturas
// Include este arquivo onde precisar checar acesso

/**
 * Retorna os dados da assinatura ativa do usuário.
 * Admins sempre recebem status "admin" (sem expiração).
 *
 * @return array [
 *   'ativa'          => bool,
 *   'status'         => 'admin'|'ativa'|'expirada'|'suspensa'|'sem_inscricao',
 *   'data_fim'       => 'YYYY-MM-DD' | null,
 *   'dias_restantes' => int,
 *   'expirando'      => bool,
 *   'inscricao'      => array|null
 * ]
 */
function get_subscription(int $user_id, PDO $pdo): array {
    ensure_subscription_category_schema($pdo);
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        return _sub_result(false, 'sem_inscricao', null, null);
    }

    if ($user['role'] === 'admin') {
        return _sub_result(true, 'admin', null, null);
    }

    $stmt2 = $pdo->prepare("\n        SELECT * FROM inscricoes\n        WHERE user_id = ?\n        ORDER BY data_fim DESC\n        LIMIT 1\n    ");
    $stmt2->execute([$user_id]);
    $inscricao = $stmt2->fetch(PDO::FETCH_ASSOC);

    if (!$inscricao) {
        return _sub_result(false, 'sem_inscricao', null, null);
    }

    $hoje      = new DateTime('today');
    $fim       = new DateTime($inscricao['data_fim']);
    $diff      = (int)$hoje->diff($fim)->format('%r%a');
    $dias_rest = max(0, $diff);

    if ($inscricao['status'] === 'ativa' && $diff < 0) {
        $pdo->prepare("UPDATE inscricoes SET status = 'expirada' WHERE id = ?")
            ->execute([$inscricao['id']]);
        $inscricao['status'] = 'expirada';
    }

    $ativa = ($inscricao['status'] === 'ativa');

    return _sub_result(
        $ativa,
        $inscricao['status'],
        $inscricao['data_fim'],
        $inscricao,
        $dias_rest
    );
}

function _sub_result(bool $ativa, string $status, ?string $data_fim, ?array $inscricao, int $dias = 0): array {
    return [
        'ativa'          => $ativa,
        'status'         => $status,
        'data_fim'       => $data_fim,
        'dias_restantes' => $dias,
        'expirando'      => ($ativa && $dias <= 7 && $status !== 'admin'),
        'inscricao'      => $inscricao,
    ];
}

function ensure_subscription_category_schema(PDO $pdo): void {
    $column = $pdo->query("SHOW COLUMNS FROM inscricoes LIKE 'categoria_plano'")->fetch(PDO::FETCH_ASSOC);
    if (!$column) {
        $pdo->exec("ALTER TABLE inscricoes ADD COLUMN categoria_plano VARCHAR(20) NOT NULL DEFAULT 'basico' AFTER plano_dias");
    }
}

function subscription_category(array $subscription): string {
    if (($subscription['status'] ?? '') === 'admin') return 'pro';
    $category = (string)($subscription['inscricao']['categoria_plano'] ?? 'basico');
    return in_array($category, ['basico', 'pro'], true) ? $category : 'basico';
}

function has_pro_access(int $user_id, PDO $pdo): bool {
    $subscription = get_subscription($user_id, $pdo);
    return $subscription['ativa'] && subscription_category($subscription) === 'pro';
}

function check_and_block(int $user_id, PDO $pdo): array {
    $sub = get_subscription($user_id, $pdo);
    if (!$sub['ativa']) {
        $_SESSION['sub_status'] = $sub['status'];
        header('Location: bloqueio.php');
        exit;
    }
    return $sub;
}

function fmt_date_br(?string $date): string {
    if (!$date) return '—';
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format('d/m/Y') : $date;
}

/**
 * Renova mantendo os dias restantes quando a assinatura atual ainda está ativa.
 */
function renovar_inscricao(int $user_id, int $plano_dias, int $admin_id, PDO $pdo, string $obs = '', string $categoria = 'basico'): int {
    if (!in_array($plano_dias, [3, 30, 90, 180, 365], true)) {
        throw new InvalidArgumentException('Plano inválido.');
    }
    if (!in_array($categoria, ['basico', 'pro'], true)) {
        throw new InvalidArgumentException('Categoria de plano inválida.');
    }
    ensure_subscription_category_schema($pdo);

    $pdo->beginTransaction();
    try {
        $hoje = new DateTime('today');
        $inicio = $hoje->format('Y-m-d');

        $stmt = $pdo->prepare("SELECT * FROM inscricoes WHERE user_id = ? ORDER BY data_fim DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $anterior = $stmt->fetch(PDO::FETCH_ASSOC);
        $data_fim_anterior = $anterior['data_fim'] ?? null;

        $baseFim = clone $hoje;
        if ($anterior && ($anterior['status'] ?? '') === 'ativa' && !empty($anterior['data_fim'])) {
            $fimAnterior = DateTime::createFromFormat('Y-m-d', $anterior['data_fim']);
            if ($fimAnterior && $fimAnterior >= $hoje) {
                $baseFim = $fimAnterior;
            }
        }
        $baseFim->modify("+{$plano_dias} days");
        $fim = $baseFim->format('Y-m-d');

        $pdo->prepare("UPDATE inscricoes SET status = 'expirada' WHERE user_id = ? AND status = 'ativa'")
            ->execute([$user_id]);

        $ins = $pdo->prepare("\n            INSERT INTO inscricoes (user_id, data_inicio, data_fim, plano_dias, categoria_plano, status, observacao, criado_por)\n            VALUES (?, ?, ?, ?, ?, 'ativa', ?, ?)\n        ");
        $ins->execute([$user_id, $inicio, $fim, $plano_dias, $categoria, $obs ?: null, $admin_id]);
        $inscricao_id = (int)$pdo->lastInsertId();

        $acao = $anterior ? 'renovada' : 'criada';
        $hist = $pdo->prepare("\n            INSERT INTO inscricoes_historico\n                (user_id, inscricao_id, acao, plano_dias, data_fim_anterior, data_fim_nova, admin_id, observacao)\n            VALUES (?, ?, ?, ?, ?, ?, ?, ?)\n        ");
        $hist->execute([$user_id, $inscricao_id, $acao, $plano_dias, $data_fim_anterior, $fim, $admin_id, $obs ?: null]);

        $pdo->prepare("UPDATE users SET plano = 'pago', free_desde = NULL WHERE id = ?")
            ->execute([$user_id]);

        $pdo->commit();
        return $inscricao_id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function pedido_pagamento_ja_ativado(string $order_nsu, int $user_id, PDO $pdo): bool {
    $stmt = $pdo->prepare("\n        SELECT 1\n        FROM inscricoes_historico\n        WHERE user_id = ? AND observacao LIKE ?\n        LIMIT 1\n    ");
    $stmt->execute([$user_id, '%' . $order_nsu . '%']);
    return (bool)$stmt->fetchColumn();
}
