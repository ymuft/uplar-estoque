<?php
header('Content-Type: application/json; charset=utf-8');
session_start();
require 'db.php';
require 'helpers.php';
send_security_headers();

function lote_json(array $data, int $status = 200): void {
    if (isset($data['error']) && (!is_string($data['error']) || trim($data['error']) === '' || strtolower(trim($data['error'])) === 'null')) {
        $data['error'] = 'Não foi possível concluir a operação.';
    }
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SESSION['user_id'])) lote_json(['ok' => false, 'error' => 'Você precisa estar logado.'], 401);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') lote_json(['ok' => false, 'error' => 'Método inválido.'], 405);
if (!verify_csrf_token()) lote_json(['ok' => false, 'error' => 'Token CSRF inválido ou ausente.'], 403);

$pdo = get_pdo();
$userId = (int)$_SESSION['user_id'];
$pdo->exec("CREATE TABLE IF NOT EXISTS produto_lote_drafts (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    codigo VARCHAR(50) NOT NULL,
    preco DECIMAL(10,2) NULL,
    quantidade INT NOT NULL DEFAULT 0,
    categoria VARCHAR(80) NULL,
    validade DATE NULL,
    imagem VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id), KEY idx_lote_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$acao = $_GET['acao'] ?? '';
if ($acao === 'upload') {
    $files = $_FILES['fotos'] ?? null;
    if (!$files || !isset($files['name']) || !is_array($files['name'])) lote_json(['ok' => false, 'error' => 'Selecione pelo menos uma foto.'], 422);
    $total = count($files['name']);
    if ($total < 1 || $total > 100) lote_json(['ok' => false, 'error' => 'Envie entre 1 e 100 fotos por lote.'], 422);

    $salvos = [];
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO produto_lote_drafts (user_id,nome,codigo,imagem) VALUES (?,?,?,?)');
        foreach ($files['name'] as $i => $original) {
            $file = ['name'=>$files['name'][$i], 'type'=>$files['type'][$i], 'tmp_name'=>$files['tmp_name'][$i], 'error'=>$files['error'][$i], 'size'=>$files['size'][$i]];
            $imagem = save_uploaded_file($file, 'imagens', 'lote_', 'imagem');
            $nome = trim(pathinfo((string)$original, PATHINFO_FILENAME));
            $nome = preg_replace('/[^\p{L}\p{N} ._-]+/u', '', $nome) ?: 'Novo produto';
            $nome = mb_substr($nome, 0, 150);
            $codigo = 'LOTE-' . strtoupper(substr(bin2hex(random_bytes(5)), 0, 8));
            $stmt->execute([$userId, $nome, $codigo, $imagem]);
            $salvos[] = ['id'=>(int)$pdo->lastInsertId(), 'nome'=>$nome, 'codigo'=>$codigo, 'preco'=>'', 'quantidade'=>0, 'categoria'=>'', 'validade'=>'', 'imagem'=>$imagem];
        }
        $pdo->commit();
        lote_json(['ok'=>true, 'rascunhos'=>$salvos]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($salvos as $draft) delete_managed_file($draft['imagem'], ['imagens']);
        error_log('Erro upload em lote: '.$e->getMessage());
        lote_json(['ok'=>false, 'error'=>'Não foi possível preparar o lote. Verifique o formato e o tamanho das fotos.'], 500);
    }
}

if ($acao === 'publicar') {
    $items = json_decode((string)($_POST['itens'] ?? ''), true);
    if (!is_array($items) || !$items) lote_json(['ok'=>false, 'error'=>'Nenhum produto para publicar.'], 422);
    if (count($items) > 100) lote_json(['ok'=>false, 'error'=>'O lote pode ter no máximo 100 produtos.'], 422);
    $ids = array_values(array_filter(array_map('intval', array_column($items, 'id'))));
    if (count($ids) !== count($items)) lote_json(['ok'=>false, 'error'=>'Rascunho inválido.'], 422);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $check = $pdo->prepare("SELECT * FROM produto_lote_drafts WHERE user_id=? AND id IN ($in) ORDER BY id");
    $check->execute(array_merge([$userId], $ids));
    $drafts = $check->fetchAll();
    if (count($drafts) !== count($items)) lote_json(['ok'=>false, 'error'=>'Um ou mais rascunhos não foram encontrados.'], 404);
    $byId = []; foreach ($items as $item) $byId[(int)$item['id']] = $item;
    $insert = $pdo->prepare('INSERT INTO produtos (user_id,nome,codigo,preco,preco_custo,quantidade,descricao,categoria,validade,imagem) VALUES (?,?,?,?,?,?,?,?,?,?)');
    try {
        $pdo->beginTransaction();
        foreach ($drafts as $draft) {
            $item = $byId[(int)$draft['id']] ?? [];
            $nome = trim((string)($item['nome'] ?? ''));
            $codigo = trim((string)($item['codigo'] ?? ''));
            $preco = (float)($item['preco'] ?? 0);
            $precoCusto = max(0, (float)($item['preco_custo'] ?? 0));
            $qtd = max(0, (int)($item['quantidade'] ?? 0));
            $validade = trim((string)($item['validade'] ?? ''));
            if ($validade !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validade)) throw new RuntimeException('A validade deve ser uma data válida ou ficar em branco.');
            if ($nome === '' || $codigo === '' || $preco <= 0) throw new RuntimeException('Preencha nome, código e preço de todos os produtos.');
            $insert->execute([$userId, mb_substr($nome,0,150), mb_substr($codigo,0,50), $preco, $precoCusto, $qtd, '', mb_substr(trim((string)($item['categoria'] ?? '')),0,80), $validade !== '' ? $validade : null, $draft['imagem']]);
        }
        $delete = $pdo->prepare("DELETE FROM produto_lote_drafts WHERE user_id=? AND id IN ($in)");
        $delete->execute(array_merge([$userId], $ids));
        $pdo->commit();
        lote_json(['ok'=>true, 'publicados'=>count($drafts)]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Não foi possível publicar o lote. Verifique códigos duplicados.';
        lote_json(['ok'=>false, 'error'=>$msg], 422);
    }
}

if ($acao === 'cancelar') {
    $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
    if (!$ids || count($ids) > 100) lote_json(['ok'=>true]);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $q = $pdo->prepare("SELECT imagem FROM produto_lote_drafts WHERE user_id=? AND id IN ($in)");
    $q->execute(array_merge([$userId], $ids));
    foreach ($q->fetchAll() as $row) delete_managed_file($row['imagem'], ['imagens']);
    $d = $pdo->prepare("DELETE FROM produto_lote_drafts WHERE user_id=? AND id IN ($in)");
    $d->execute(array_merge([$userId], $ids));
    lote_json(['ok'=>true]);
}

lote_json(['ok'=>false, 'error'=>'Ação inválida.'], 400);
