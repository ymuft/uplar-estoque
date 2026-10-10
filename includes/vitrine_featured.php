<?php
/**
 * Garante a extensão editorial da vitrine sem alterar os produtos já existentes.
 * `none`, `featured` e `promotion` são mutuamente exclusivos.
 */
function vitrine_ensure_featured_schema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) return;

    if (!$pdo->query("SHOW COLUMNS FROM produtos LIKE 'vitrine_destaque'")->fetchColumn()) {
        $pdo->exec("ALTER TABLE produtos ADD COLUMN vitrine_destaque VARCHAR(12) NOT NULL DEFAULT 'none'");
    }
    if (!$pdo->query("SHOW COLUMNS FROM produtos LIKE 'vitrine_destaque_ordem'")->fetchColumn()) {
        $pdo->exec("ALTER TABLE produtos ADD COLUMN vitrine_destaque_ordem INT UNSIGNED NOT NULL DEFAULT 0");
    }
    if (!$pdo->query("SHOW INDEX FROM produtos WHERE Key_name = 'idx_vitrine_destaque'")->fetchColumn()) {
        $pdo->exec("ALTER TABLE produtos ADD INDEX idx_vitrine_destaque (user_id, vitrine_exposto, vitrine_destaque, vitrine_destaque_ordem)");
    }
    $ready = true;
}
