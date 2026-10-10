<?php

final class SiteProImageException extends RuntimeException {}

function site_pro_ensure_schema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS site_pro_config (
        user_id INT NOT NULL PRIMARY KEY,
        modelo VARCHAR(20) NOT NULL DEFAULT 'venda_direta',
        titulo VARCHAR(100) NOT NULL DEFAULT '',
        subtitulo VARCHAR(240) NOT NULL DEFAULT '',
        texto_cta VARCHAR(40) NOT NULL DEFAULT 'Ver catálogo',
        ativo TINYINT(1) NOT NULL DEFAULT 1,
        criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_site_pro_config_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $columns = [
        'slug_site' => "VARCHAR(80) NULL AFTER user_id",
        'nome_site' => "VARCHAR(120) NULL AFTER modelo",
        'descricao_site' => "VARCHAR(500) NULL AFTER nome_site",
        'logo_path' => "VARCHAR(255) NULL AFTER descricao_site",
        'banner_path' => "VARCHAR(255) NULL AFTER logo_path",
        'cor_primaria' => "CHAR(7) NOT NULL DEFAULT '#3b6ff5' AFTER banner_path",
        'cor_fundo' => "CHAR(7) NULL AFTER cor_primaria",
        'fonte_site' => "VARCHAR(20) NOT NULL DEFAULT 'tema' AFTER cor_fundo",
        'aviso_principal' => "VARCHAR(100) NULL AFTER fonte_site",
        'aviso_secundario' => "VARCHAR(140) NULL AFTER aviso_principal",
        'beneficios_json' => "TEXT NULL AFTER aviso_secundario",
        'mostrar_beneficios' => "TINYINT(1) NOT NULL DEFAULT 1 AFTER beneficios_json",
        'whatsapp' => "VARCHAR(30) NULL AFTER mostrar_beneficios",
        'finalizacao_pedido' => "VARCHAR(20) NOT NULL DEFAULT 'whatsapp' AFTER whatsapp",
        'infinitepay_tag' => "VARCHAR(80) NULL AFTER finalizacao_pedido",
        'endereco' => "VARCHAR(255) NULL AFTER infinitepay_tag",
        'cidade_uf' => "VARCHAR(80) NULL AFTER endereco",
        'valor_frete' => "DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER cidade_uf",
        'mostrar_estoque' => "TINYINT(1) NOT NULL DEFAULT 0 AFTER valor_frete",
        'canal_publico' => "VARCHAR(20) NOT NULL DEFAULT 'vitrine' AFTER mostrar_estoque",
    ];

    foreach ($columns as $name => $definition) {
        $exists = $pdo->query("SHOW COLUMNS FROM site_pro_config LIKE " . $pdo->quote($name))->fetchColumn();
        if (!$exists) {
            $pdo->exec("ALTER TABLE site_pro_config ADD COLUMN {$name} {$definition}");
        }
    }
    $slugIndex = $pdo->query("SHOW INDEX FROM site_pro_config WHERE Key_name = 'uq_site_pro_slug'")->fetchColumn();
    if (!$slugIndex) {
        $pdo->exec('ALTER TABLE site_pro_config ADD UNIQUE KEY uq_site_pro_slug (slug_site)');
    }

    $siteProductColumn = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'site_pro_exposto'")->fetchColumn();
    if (!$siteProductColumn) {
        $pdo->exec('ALTER TABLE produtos ADD COLUMN site_pro_exposto TINYINT(1) NOT NULL DEFAULT 0 AFTER vitrine_exposto');
        $pdo->exec('UPDATE produtos SET site_pro_exposto = COALESCE(vitrine_exposto, 0)');
    }

    $ready = true;
}

function site_pro_config(PDO $pdo, int $userId, array $fallback = []): array
{
    site_pro_ensure_schema($pdo);
    $stmt = $pdo->prepare('SELECT * FROM site_pro_config WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $fallbackName = trim((string)($fallback['nome_loja'] ?? ''));
    $name = trim((string)($config['nome_site'] ?? '')) ?: $fallbackName;
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($config['cor_primaria'] ?? ''))
        ? (string)$config['cor_primaria']
        : '#3b6ff5';
    $backgroundColor = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($config['cor_fundo'] ?? ''))
        ? (string)$config['cor_fundo']
        : '';
    $font = in_array(($config['fonte_site'] ?? ''), ['tema', 'manrope', 'inter', 'archivo', 'cormorant'], true)
        ? (string)$config['fonte_site']
        : 'tema';
    $benefits = json_decode((string)($config['beneficios_json'] ?? ''), true);
    if (!is_array($benefits) || count($benefits) !== 3) {
        $benefits = site_pro_default_benefits();
    }
    $allowedIcons = array_keys(site_pro_benefit_icon_options());
    foreach ($benefits as $index => $benefit) {
        if (!is_array($benefit)) {
            $benefits[$index] = site_pro_default_benefits()[$index];
            continue;
        }
        $benefits[$index] = [
            'icone' => in_array(($benefit['icone'] ?? ''), $allowedIcons, true) ? $benefit['icone'] : 'verified',
            'titulo' => mb_substr(trim((string)($benefit['titulo'] ?? '')), 0, 60),
            'texto' => mb_substr(trim((string)($benefit['texto'] ?? '')), 0, 120),
        ];
    }

    return [
        'slug_site' => trim((string)($config['slug_site'] ?? '')) ?: trim((string)($fallback['slug_site'] ?? '')),
        'modelo' => in_array(($config['modelo'] ?? ''), ['venda_direta', 'catalogo_editorial', 'impacto_esportivo', 'catalogo_compacto'], true)
            ? $config['modelo']
            : 'venda_direta',
        'nome_site' => $name,
        'descricao_site' => trim((string)($config['descricao_site'] ?? '')),
        'logo_path' => trim((string)($config['logo_path'] ?? '')),
        'banner_path' => trim((string)($config['banner_path'] ?? '')),
        'cor_primaria' => $color,
        'cor_fundo' => $backgroundColor,
        'fonte_site' => $font,
        'aviso_principal' => array_key_exists('aviso_principal', $config) && $config['aviso_principal'] !== null
            ? trim((string)$config['aviso_principal'])
            : 'Catálogo e estoque atualizados',
        'aviso_secundario' => array_key_exists('aviso_secundario', $config) && $config['aviso_secundario'] !== null
            ? trim((string)$config['aviso_secundario'])
            : 'Pedido enviado diretamente para a loja',
        'beneficios' => $benefits,
        'mostrar_beneficios' => !array_key_exists('mostrar_beneficios', $config)
            || (int)$config['mostrar_beneficios'] === 1,
        'whatsapp' => trim((string)($config['whatsapp'] ?? '')),
        'finalizacao_pedido' => in_array(($config['finalizacao_pedido'] ?? ''), ['whatsapp', 'infinitepay', 'ambos'], true)
            ? (string)$config['finalizacao_pedido']
            : 'whatsapp',
        'infinitepay_tag' => trim((string)($config['infinitepay_tag'] ?? '')),
        'endereco' => trim((string)($config['endereco'] ?? '')),
        'cidade_uf' => trim((string)($config['cidade_uf'] ?? '')),
        'valor_frete' => max(0, (float)($config['valor_frete'] ?? 0)),
        'mostrar_estoque' => (int)($config['mostrar_estoque'] ?? 0) === 1,
        'canal_publico' => ($config['canal_publico'] ?? '') === 'site_pro' ? 'site_pro' : 'vitrine',
        'titulo' => trim((string)($config['titulo'] ?? ''))
            ?: ($name !== '' ? 'Produtos de ' . $name : 'Conheça nossos produtos'),
        'subtitulo' => trim((string)($config['subtitulo'] ?? '')),
        'texto_cta' => trim((string)($config['texto_cta'] ?? '')) ?: 'Ver produtos',
        'ativo' => array_key_exists('ativo', $config) && (int)$config['ativo'] === 1,
    ];
}

function site_pro_benefit_icon_options(): array
{
    return [
        'inventory_2' => 'Estoque',
        'local_shipping' => 'Entrega',
        'shopping_bag' => 'Pedido',
        'chat' => 'Atendimento',
        'sell' => 'Oferta',
        'percent' => 'Desconto',
        'verified' => 'Garantia',
        'schedule' => 'Horário',
        'payments' => 'Pagamento',
        'storefront' => 'Loja',
    ];
}

function site_pro_default_benefits(): array
{
    return [
        ['icone' => 'inventory_2', 'titulo' => 'Estoque atualizado', 'texto' => 'Você escolhe apenas itens disponíveis.'],
        ['icone' => 'shopping_bag', 'titulo' => 'Pedido organizado', 'texto' => 'Produtos e quantidades seguem prontos para a loja.'],
        ['icone' => 'chat', 'titulo' => 'Atendimento da própria loja', 'texto' => 'A conversa continua diretamente pelo WhatsApp.'],
    ];
}

function site_pro_color_is_dark(string $color): bool
{
    if (!preg_match('/^#([0-9a-fA-F]{6})$/', $color, $matches)) {
        return false;
    }
    $red = hexdec(substr($matches[1], 0, 2));
    $green = hexdec(substr($matches[1], 2, 2));
    $blue = hexdec(substr($matches[1], 4, 2));

    return (($red * 299) + ($green * 587) + ($blue * 114)) / 1000 < 145;
}

function site_pro_media_dir(): string
{
    $directory = __DIR__ . '/imagens/site-pro';
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new SiteProImageException('Não foi possível preparar a pasta de imagens.');
    }
    return $directory;
}

function site_pro_upload_image(
    array $file,
    string $prefix,
    int $maxBytes,
    int $targetWidth,
    int $targetHeight,
    int $minWidth,
    int $minHeight
): string {
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        throw new SiteProImageException('Falha no upload da imagem.');
    }

    $size = (int)($file['size'] ?? 0);
    if ($size < 1 || $size > $maxBytes) {
        throw new SiteProImageException('O arquivo ultrapassa o tamanho permitido.');
    }

    $temporaryName = (string)($file['tmp_name'] ?? '');
    if ($temporaryName === '' || !is_uploaded_file($temporaryName)) {
        throw new SiteProImageException('Upload de imagem inválido.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)$finfo->file($temporaryName);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        throw new SiteProImageException('Formato não suportado. Utilize JPG, PNG ou WebP.');
    }

    $bytes = file_get_contents($temporaryName);
    if ($bytes === false) {
        throw new SiteProImageException('Não foi possível processar esta imagem.');
    }
    $imageInfo = getimagesizefromstring($bytes);
    if ($imageInfo === false || ($imageInfo['mime'] ?? '') !== $mime) {
        throw new SiteProImageException('O arquivo enviado não é uma imagem válida.');
    }

    $width = (int)$imageInfo[0];
    $height = (int)$imageInfo[1];
    if ($width < $minWidth || $height < $minHeight) {
        throw new SiteProImageException("A imagem é pequena demais. Dimensão mínima: {$minWidth} × {$minHeight} px.");
    }
    if ($width > ($targetWidth * 2) || $height > ($targetHeight * 2)) {
        throw new SiteProImageException('A imagem possui dimensões grandes demais.');
    }
    if (abs(($width / $height) - ($targetWidth / $targetHeight)) > 0.02) {
        throw new SiteProImageException('O enquadramento enviado possui proporção inválida.');
    }

    $source = imagecreatefromstring($bytes);
    unset($bytes);
    if ($source === false) {
        throw new SiteProImageException('Não foi possível decodificar esta imagem.');
    }

    $destination = imagecreatetruecolor($targetWidth, $targetHeight);
    if ($destination === false) {
        imagedestroy($source);
        throw new SiteProImageException('Não foi possível normalizar esta imagem.');
    }

    $useWebP = function_exists('imagewebp');
    if ($useWebP) {
        imagealphablending($destination, false);
        imagesavealpha($destination, true);
        $transparent = imagecolorallocatealpha($destination, 0, 0, 0, 127);
        imagefill($destination, 0, 0, $transparent);
        imagealphablending($destination, true);
    } else {
        $white = imagecolorallocate($destination, 255, 255, 255);
        imagefill($destination, 0, 0, $white);
    }

    $resampled = imagecopyresampled(
        $destination,
        $source,
        0,
        0,
        0,
        0,
        $targetWidth,
        $targetHeight,
        $width,
        $height
    );
    imagedestroy($source);
    if (!$resampled) {
        imagedestroy($destination);
        throw new SiteProImageException('Não foi possível redimensionar esta imagem.');
    }

    $random = bin2hex(random_bytes(16));
    $extension = $useWebP ? 'webp' : 'jpg';
    $filename = "{$prefix}_{$random}.{$extension}";
    $temporaryPath = site_pro_media_dir() . "/.{$random}.tmp";
    $saved = $useWebP
        ? imagewebp($destination, $temporaryPath, 85)
        : imagejpeg($destination, $temporaryPath, 88);
    imagedestroy($destination);

    if (!$saved || !is_file($temporaryPath)) {
        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
        throw new SiteProImageException('Não foi possível salvar a imagem processada.');
    }

    $destinationPath = site_pro_media_dir() . '/' . $filename;
    if (!rename($temporaryPath, $destinationPath)) {
        unlink($temporaryPath);
        throw new SiteProImageException('Não foi possível concluir o upload da imagem.');
    }
    chmod($destinationPath, 0644);

    return 'imagens/site-pro/' . $filename;
}

function site_pro_delete_image(?string $path): void
{
    if (!$path || !preg_match('#^imagens/site-pro/[a-zA-Z0-9._-]+\.(?:jpe?g|png|webp)$#i', $path)) {
        return;
    }
    $fullPath = __DIR__ . '/' . $path;
    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}

function site_pro_save_config(PDO $pdo, int $userId, array $input, array $files = []): array
{
    site_pro_ensure_schema($pdo);
    $current = site_pro_config($pdo, $userId);

    $model = trim((string)($input['modelo'] ?? 'venda_direta'));
    $slug = strtolower(trim((string)($input['slug_site'] ?? '')));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', trim($slug, '-'));
    $siteName = mb_substr(trim((string)($input['nome_site'] ?? '')), 0, 120);
    $siteDescription = mb_substr(trim((string)($input['descricao_site'] ?? '')), 0, 500);
    $title = trim((string)($input['titulo'] ?? ''));
    $subtitle = trim((string)($input['subtitulo'] ?? ''));
    $ctaText = trim((string)($input['texto_cta'] ?? ''));
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($input['cor_primaria'] ?? ''))
        ? (string)$input['cor_primaria']
        : '#3b6ff5';
    $backgroundColor = !empty($input['fundo_personalizado'])
        && preg_match('/^#[0-9a-fA-F]{6}$/', (string)($input['cor_fundo'] ?? ''))
        ? (string)$input['cor_fundo']
        : null;
    $font = trim((string)($input['fonte_site'] ?? 'tema'));
    $primaryNotice = mb_substr(trim((string)($input['aviso_principal'] ?? '')), 0, 100);
    $secondaryNotice = mb_substr(trim((string)($input['aviso_secundario'] ?? '')), 0, 140);
    $benefitIcons = is_array($input['beneficio_icone'] ?? null) ? $input['beneficio_icone'] : [];
    $benefitTitles = is_array($input['beneficio_titulo'] ?? null) ? $input['beneficio_titulo'] : [];
    $benefitTexts = is_array($input['beneficio_texto'] ?? null) ? $input['beneficio_texto'] : [];
    $allowedIcons = array_keys(site_pro_benefit_icon_options());
    $benefits = [];
    for ($index = 0; $index < 3; $index++) {
        $icon = trim((string)($benefitIcons[$index] ?? ''));
        $benefits[] = [
            'icone' => in_array($icon, $allowedIcons, true) ? $icon : 'verified',
            'titulo' => mb_substr(trim((string)($benefitTitles[$index] ?? '')), 0, 60),
            'texto' => mb_substr(trim((string)($benefitTexts[$index] ?? '')), 0, 120),
        ];
    }
    $benefitsJson = json_encode($benefits, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $showBenefits = !empty($input['mostrar_beneficios']) ? 1 : 0;
    $whatsapp = preg_replace('/\D/', '', (string)($input['whatsapp'] ?? ''));
    $checkoutMode = trim((string)($input['finalizacao_pedido'] ?? 'whatsapp'));
    $infinitePayTag = strtolower(ltrim(trim((string)($input['infinitepay_tag'] ?? '')), '$'));
    $address = mb_substr(trim((string)($input['endereco'] ?? '')), 0, 255);
    $city = mb_substr(trim((string)($input['cidade_uf'] ?? '')), 0, 80);
    $shipping = max(0, (float)($input['valor_frete'] ?? 0));
    $showStock = !empty($input['mostrar_estoque']) ? 1 : 0;
    $active = !empty($input['ativo']) ? 1 : 0;
    $publicChannel = trim((string)($input['canal_publico'] ?? 'vitrine'));

    if (!in_array($model, ['venda_direta', 'catalogo_editorial', 'impacto_esportivo', 'catalogo_compacto'], true)) {
        throw new InvalidArgumentException('Modelo de site inválido.');
    }
    if (!in_array($font, ['tema', 'manrope', 'inter', 'archivo', 'cormorant'], true)) {
        throw new InvalidArgumentException('Fonte do site inválida.');
    }
    if (!in_array($checkoutMode, ['whatsapp', 'infinitepay', 'ambos'], true)) {
        throw new InvalidArgumentException('Forma de finalização inválida.');
    }
    if ($checkoutMode !== 'whatsapp' && !preg_match('/^[a-z0-9._-]{2,80}$/', $infinitePayTag)) {
        throw new InvalidArgumentException('Informe uma InfiniteTag válida para ativar o pagamento online.');
    }
    foreach ($benefits as $benefit) {
        if ($benefit['titulo'] === '' || $benefit['texto'] === '') {
            throw new InvalidArgumentException('Preencha o título e o texto dos três diferenciais.');
        }
    }
    if (!in_array($publicChannel, ['vitrine', 'site_pro'], true)) {
        throw new InvalidArgumentException('Canal público inválido.');
    }
    if (strlen($slug) < 3 || strlen($slug) > 80) {
        throw new InvalidArgumentException('Informe um endereço do site com 3 a 80 caracteres.');
    }
    $slugStmt = $pdo->prepare('SELECT user_id FROM site_pro_config WHERE slug_site = ? AND user_id <> ? LIMIT 1');
    $slugStmt->execute([$slug, $userId]);
    if ($slugStmt->fetchColumn()) {
        throw new InvalidArgumentException('Este endereço do Site PRO já está em uso.');
    }
    if ($siteName === '') {
        throw new InvalidArgumentException('Informe o nome que aparecerá no site.');
    }
    if ($title === '' || mb_strlen($title) > 100) {
        throw new InvalidArgumentException('Informe um título com até 100 caracteres.');
    }
    if (mb_strlen($subtitle) > 240 || mb_strlen($ctaText) > 40) {
        throw new InvalidArgumentException('Um dos textos ultrapassou o limite permitido.');
    }
    if ($active && strlen($whatsapp) < 10) {
        throw new InvalidArgumentException('Informe um WhatsApp válido antes de publicar o site.');
    }
    if ($publicChannel === 'site_pro' && !$active) {
        throw new InvalidArgumentException('Publique o Site PRO antes de escolhê-lo como canal principal.');
    }

    $logoPath = $current['logo_path'] ?: null;
    $bannerPath = $current['banner_path'] ?: null;
    $oldLogoPath = $logoPath;
    $oldBannerPath = $bannerPath;
    $newPaths = [];

    try {
        if (!empty($files['banner']['name'])) {
            $bannerPath = site_pro_upload_image($files['banner'], 'banner', 5 * 1024 * 1024, 1500, 500, 1200, 400);
            $newPaths[] = $bannerPath;
        } elseif (!empty($input['remover_banner'])) {
            $bannerPath = null;
        }

        if (!empty($files['logo']['name'])) {
            $logoPath = site_pro_upload_image($files['logo'], 'logo', 3 * 1024 * 1024, 600, 600, 500, 500);
            $newPaths[] = $logoPath;
        } elseif (!empty($input['remover_logo'])) {
            $logoPath = null;
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO site_pro_config
            (user_id, slug_site, modelo, nome_site, descricao_site, logo_path, banner_path, cor_primaria, cor_fundo,
             fonte_site, aviso_principal, aviso_secundario, beneficios_json, mostrar_beneficios, whatsapp,
             finalizacao_pedido, infinitepay_tag, endereco, cidade_uf, valor_frete, mostrar_estoque, canal_publico,
             titulo, subtitulo, texto_cta, ativo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                slug_site = VALUES(slug_site),
                modelo = VALUES(modelo),
                nome_site = VALUES(nome_site),
                descricao_site = VALUES(descricao_site),
                logo_path = VALUES(logo_path),
                banner_path = VALUES(banner_path),
                cor_primaria = VALUES(cor_primaria),
                cor_fundo = VALUES(cor_fundo),
                fonte_site = VALUES(fonte_site),
                aviso_principal = VALUES(aviso_principal),
                aviso_secundario = VALUES(aviso_secundario),
                beneficios_json = VALUES(beneficios_json),
                mostrar_beneficios = VALUES(mostrar_beneficios),
                whatsapp = VALUES(whatsapp),
                finalizacao_pedido = VALUES(finalizacao_pedido),
                infinitepay_tag = VALUES(infinitepay_tag),
                endereco = VALUES(endereco),
                cidade_uf = VALUES(cidade_uf),
                valor_frete = VALUES(valor_frete),
                mostrar_estoque = VALUES(mostrar_estoque),
                canal_publico = VALUES(canal_publico),
                titulo = VALUES(titulo),
                subtitulo = VALUES(subtitulo),
                texto_cta = VALUES(texto_cta),
                ativo = VALUES(ativo)");
        $stmt->execute([
            $userId, $slug, $model, $siteName, $siteDescription ?: null, $logoPath, $bannerPath, $color,
            $backgroundColor, $font, $primaryNotice, $secondaryNotice, $benefitsJson, $showBenefits,
            $whatsapp ?: null, $checkoutMode, $infinitePayTag ?: null, $address ?: null, $city ?: null,
            $shipping, $showStock, $publicChannel,
            $title, $subtitle, $ctaText ?: 'Ver produtos', $active,
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($newPaths as $newPath) {
            site_pro_delete_image($newPath);
        }
        throw $e;
    }

    if ($logoPath !== $oldLogoPath) {
        site_pro_delete_image($oldLogoPath);
    }
    if ($bannerPath !== $oldBannerPath) {
        site_pro_delete_image($oldBannerPath);
    }

    return site_pro_config($pdo, $userId);
}
