<?php
session_start();
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

function changelog_json(array $data, int $status = 200): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function changelog_error(string $message, int $status = 400): never {
    changelog_json(['ok' => false, 'error' => $message], $status);
}

function changelog_require_user(): int {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    if ($userId <= 0) changelog_error('Não autenticado.', 401);
    return $userId;
}

function changelog_require_admin(PDO $pdo, int $userId): void {
    $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    if ($stmt->fetchColumn() !== 'admin') changelog_error('Acesso negado.', 403);
}

function changelog_require_csrf(): void {
    $token = (string)($_POST['csrf_token'] ?? '');
    $expected = (string)($_SESSION['csrf_token'] ?? '');
    if ($token === '' || $expected === '' || !hash_equals($expected, $token)) {
        changelog_error('Token CSRF inválido.', 403);
    }
}

function changelog_date_label(?string $date): string {
    if (!$date) return '';
    $months = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
    $timestamp = strtotime($date);
    if (!$timestamp) return '';
    return date('j', $timestamp) . ' de ' . $months[(int)date('n', $timestamp)] . ' de ' . date('Y', $timestamp);
}

function changelog_safe_link(?string $url): ?string {
    $url = trim((string)$url);
    if ($url === '') return null;
    if (strlen($url) > 500 || preg_match('/[\x00-\x1F\x7F]/', $url)) return null;
    if (str_starts_with($url, '//')) return null;
    if (str_starts_with($url, '/')) return $url;
    if (preg_match('#^[a-zA-Z0-9][a-zA-Z0-9._/-]*(?:\?[a-zA-Z0-9._~!$&\'()*+,;=:@%/?-]*)?$#', $url)) return $url;
    if (filter_var($url, FILTER_VALIDATE_URL) && str_starts_with(strtolower($url), 'https://')) return $url;
    return null;
}

final class ChangelogImageException extends RuntimeException {}

function changelog_media_dir(): string {
    $directory = __DIR__ . '/imagens/changelog';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new ChangelogImageException('Não foi possível preparar o diretório de imagens.');
    }
    return $directory;
}

function changelog_delete_image(?string $path): void {
    $path = trim((string)$path);
    if (!preg_match('#^imagens/changelog/[a-f0-9]{32}\.webp$#', $path)) return;
    $fullPath = __DIR__ . '/' . $path;
    $realDirectory = realpath(changelog_media_dir());
    $realFile = realpath($fullPath);
    if ($realDirectory && $realFile && str_starts_with($realFile, $realDirectory . DIRECTORY_SEPARATOR) && !unlink($realFile)) {
        error_log('Não foi possível excluir a imagem de changelog: ' . basename($realFile));
    }
}

function changelog_save_image(array $file): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new ChangelogImageException('Imagem inválida.');
    }
    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > 4 * 1024 * 1024) throw new ChangelogImageException('A imagem ultrapassa o limite de 4 MB.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) {
        throw new ChangelogImageException('Formato de imagem inválido. Utilize JPG, PNG ou WebP.');
    }
    $bytes = file_get_contents($file['tmp_name']);
    $info = $bytes !== false ? @getimagesizefromstring($bytes) : false;
    if (!$info || ($info['mime'] ?? '') !== $mime) throw new ChangelogImageException('O arquivo enviado não é uma imagem válida.');
    $width = (int)$info[0];
    $height = (int)$info[1];
    if ($width < 320 || $height < 180) throw new ChangelogImageException('A imagem deve possuir pelo menos 320 × 180 px.');
    if ($width > 7000 || $height > 7000 || $width * $height > 30000000) throw new ChangelogImageException('A imagem possui dimensões grandes demais.');
    $source = @imagecreatefromstring($bytes);
    unset($bytes);
    if (!$source || !function_exists('imagewebp')) throw new ChangelogImageException('Não foi possível processar a imagem.');
    $scale = min(1, 1400 / $width, 900 / $height);
    $targetWidth = max(1, (int)round($width * $scale));
    $targetHeight = max(1, (int)round($height * $scale));
    $destination = imagecreatetruecolor($targetWidth, $targetHeight);
    imagealphablending($destination, false);
    imagesavealpha($destination, true);
    imagefill($destination, 0, 0, imagecolorallocatealpha($destination, 0, 0, 0, 127));
    imagealphablending($destination, true);
    $copied = imagecopyresampled($destination, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
    imagedestroy($source);
    if (!$copied) {
        imagedestroy($destination);
        throw new ChangelogImageException('Não foi possível redimensionar a imagem.');
    }
    $name = bin2hex(random_bytes(16));
    $temporary = changelog_media_dir() . '/.' . $name . '.tmp';
    $saved = imagewebp($destination, $temporary, 84);
    imagedestroy($destination);
    if (!$saved) {
        if (is_file($temporary)) unlink($temporary);
        throw new ChangelogImageException('Não foi possível salvar a imagem.');
    }
    $destinationPath = changelog_media_dir() . '/' . $name . '.webp';
    if (!rename($temporary, $destinationPath)) {
        if (is_file($temporary)) unlink($temporary);
        throw new ChangelogImageException('Não foi possível concluir o upload.');
    }
    @chmod($destinationPath, 0644);
    return 'imagens/changelog/' . $name . '.webp';
}

function changelog_fetch_items(PDO $pdo, array $changelogIds): array {
    if (!$changelogIds) return [];
    $placeholders = implode(',', array_fill(0, count($changelogIds), '?'));
    $stmt = $pdo->prepare("SELECT id, changelog_id, category, title, description, image_path, link_url, button_text, is_featured, sort_order
        FROM platform_changelog_items WHERE changelog_id IN ($placeholders) ORDER BY sort_order, id");
    $stmt->execute($changelogIds);
    $grouped = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $item['id'] = (int)$item['id'];
        $item['changelog_id'] = (int)$item['changelog_id'];
        $item['is_featured'] = (bool)$item['is_featured'];
        $grouped[$item['changelog_id']][] = $item;
    }
    return $grouped;
}

$userId = changelog_require_user();
$pdo = get_pdo();
$action = trim((string)($_GET['action'] ?? $_POST['action'] ?? 'status'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') changelog_require_csrf();

if ($action === 'admin_detail') {
    changelog_require_admin($pdo, $userId);
    $changelogId = (int)($_GET['changelog_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT id, title, version, intro, status, importance, published_at
        FROM platform_changelogs WHERE id = ? LIMIT 1');
    $stmt->execute([$changelogId]);
    $changelog = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$changelog) changelog_error('Atualização não encontrada.', 404);
    $changelog['id'] = (int)$changelog['id'];
    $changelog['date_label'] = changelog_date_label($changelog['published_at']);
    $items = changelog_fetch_items($pdo, [$changelogId]);
    $changelog['items'] = $items[$changelogId] ?? [];
    changelog_json(['ok' => true, 'changelog' => $changelog]);
}

if ($action === 'status') {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM platform_changelogs c
        LEFT JOIN platform_changelog_views v ON v.changelog_id = c.id AND v.user_id = ?
        WHERE c.status = 'published' AND c.published_at <= NOW() AND v.changelog_id IS NULL");
    $countStmt->execute([$userId]);
    $unreadCount = (int)$countStmt->fetchColumn();

    $modalStmt = $pdo->prepare("SELECT c.id, c.title, c.version, c.intro, c.importance, c.show_modal, c.published_at
        FROM platform_changelogs c
        LEFT JOIN platform_changelog_views v ON v.changelog_id = c.id AND v.user_id = ?
        WHERE c.status = 'published' AND c.published_at <= NOW() AND v.changelog_id IS NULL
          AND (c.importance IN ('important','critical') OR c.show_modal = 1)
        ORDER BY c.published_at DESC, c.id DESC LIMIT 1");
    $modalStmt->execute([$userId]);
    $modal = $modalStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    if ($modal) {
        $modal['id'] = (int)$modal['id'];
        $modal['show_modal'] = (bool)$modal['show_modal'];
        $modal['date_label'] = changelog_date_label($modal['published_at']);
        $items = changelog_fetch_items($pdo, [$modal['id']]);
        $modal['items'] = $items[$modal['id']] ?? [];
    }
    changelog_json(['ok' => true, 'unread_count' => $unreadCount, 'modal' => $modal]);
}

if ($action === 'mark_viewed') {
    $changelogId = (int)($_POST['changelog_id'] ?? 0);
    $source = in_array($_POST['source'] ?? '', ['modal','center'], true) ? $_POST['source'] : 'modal';
    $check = $pdo->prepare("SELECT id FROM platform_changelogs WHERE id = ? AND status = 'published' AND published_at <= NOW()");
    $check->execute([$changelogId]);
    if (!$check->fetchColumn()) changelog_error('Atualização não encontrada.', 404);
    $stmt = $pdo->prepare('INSERT INTO platform_changelog_views (changelog_id, user_id, source) VALUES (?,?,?)
        ON DUPLICATE KEY UPDATE viewed_at = viewed_at');
    $stmt->execute([$changelogId, $userId, $source]);
    changelog_json(['ok' => true]);
}

if ($action === 'mark_all_viewed') {
    $stmt = $pdo->prepare("INSERT IGNORE INTO platform_changelog_views (changelog_id, user_id, source)
        SELECT id, ?, 'center' FROM platform_changelogs WHERE status = 'published' AND published_at <= NOW()");
    $stmt->execute([$userId]);
    changelog_json(['ok' => true, 'marked' => $stmt->rowCount()]);
}

if ($action === 'admin_create') {
    changelog_require_admin($pdo, $userId);
    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 160);
    $version = mb_substr(trim((string)($_POST['version'] ?? '')), 0, 40);
    $intro = mb_substr(trim((string)($_POST['intro'] ?? '')), 0, 500);
    $status = in_array($_POST['status'] ?? '', ['draft','published'], true) ? $_POST['status'] : 'draft';
    $importance = in_array($_POST['importance'] ?? '', ['normal','important','critical'], true) ? $_POST['importance'] : 'normal';
    $showModal = !empty($_POST['show_modal']) ? 1 : 0;
    if ($importance !== 'normal') $showModal = 1;
    $publishedInput = trim((string)($_POST['published_at'] ?? ''));
    $publishedAt = $publishedInput !== '' ? date('Y-m-d H:i:s', strtotime($publishedInput) ?: 0) : date('Y-m-d H:i:s');
    if ($title === '' || $intro === '') changelog_error('Informe título e descrição introdutória.');
    if ($publishedAt === '1970-01-01 00:00:00') changelog_error('Data de publicação inválida.');

    $items = json_decode((string)($_POST['items_json'] ?? ''), true);
    if (!is_array($items) || count($items) < 1 || count($items) > 20) changelog_error('Adicione de 1 a 20 alterações.');
    $categories = ['new','improvement','fix','security','performance'];
    $validatedItems = [];
    $featuredCount = 0;
    foreach ($items as $index => $item) {
        $category = in_array($item['category'] ?? '', $categories, true) ? $item['category'] : 'improvement';
        $itemTitle = mb_substr(trim((string)($item['title'] ?? '')), 0, 160);
        $description = mb_substr(trim((string)($item['description'] ?? '')), 0, 2000);
        $link = changelog_safe_link($item['link_url'] ?? null);
        $buttonText = mb_substr(trim((string)($item['button_text'] ?? '')), 0, 80);
        $featured = !empty($item['is_featured']);
        if ($itemTitle === '' || $description === '') changelog_error('Todos os itens precisam de título e descrição.');
        if (trim((string)($item['link_url'] ?? '')) !== '' && $link === null) changelog_error('Um dos links informados é inválido.');
        if ($featured) $featuredCount++;
        $validatedItems[] = compact('category','itemTitle','description','link','buttonText','featured') + ['index' => (int)$index];
    }
    if ($featuredCount > 1) changelog_error('Marque somente uma alteração como destaque.');

    $newImages = [];
    try {
        foreach ($validatedItems as &$item) {
            $fileKey = 'item_image_' . $item['index'];
            $item['imagePath'] = !empty($_FILES[$fileKey]['name']) ? changelog_save_image($_FILES[$fileKey]) : null;
            if ($item['imagePath']) $newImages[] = $item['imagePath'];
        }
        unset($item);
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('INSERT INTO platform_changelogs
            (title, version, intro, status, importance, show_modal, published_at, created_by)
            VALUES (?,?,?,?,?,?,?,?)');
        $stmt->execute([$title, $version ?: null, $intro, $status, $importance, $showModal, $publishedAt, $userId]);
        $changelogId = (int)$pdo->lastInsertId();
        $itemStmt = $pdo->prepare('INSERT INTO platform_changelog_items
            (changelog_id, category, title, description, image_path, link_url, button_text, is_featured, sort_order)
            VALUES (?,?,?,?,?,?,?,?,?)');
        foreach ($validatedItems as $order => $item) {
            $itemStmt->execute([$changelogId, $item['category'], $item['itemTitle'], $item['description'], $item['imagePath'], $item['link'], $item['buttonText'] ?: null, $item['featured'] ? 1 : 0, $order]);
        }
        $pdo->commit();
        changelog_json(['ok' => true, 'id' => $changelogId]);
    } catch (ChangelogImageException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($newImages as $path) changelog_delete_image($path);
        changelog_error($e->getMessage(), 422);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        foreach ($newImages as $path) changelog_delete_image($path);
        error_log('Erro ao criar changelog: ' . $e->getMessage());
        changelog_error('Não foi possível salvar a atualização.', 500);
    }
}

if ($action === 'admin_delete_draft') {
    changelog_require_admin($pdo, $userId);
    $changelogId = (int)($_POST['changelog_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT i.image_path FROM platform_changelogs c LEFT JOIN platform_changelog_items i ON i.changelog_id = c.id WHERE c.id = ? AND c.status = 'draft'");
    $stmt->execute([$changelogId]);
    $images = array_filter(array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'image_path'));
    $delete = $pdo->prepare("DELETE FROM platform_changelogs WHERE id = ? AND status = 'draft'");
    $delete->execute([$changelogId]);
    if (!$delete->rowCount()) changelog_error('Rascunho não encontrado.', 404);
    foreach ($images as $path) changelog_delete_image($path);
    changelog_json(['ok' => true]);
}

changelog_error('Ação inválida.', 404);
