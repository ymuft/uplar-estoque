CREATE TABLE IF NOT EXISTS platform_changelogs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(160) NOT NULL,
    version VARCHAR(40) NULL,
    intro VARCHAR(500) NOT NULL,
    status ENUM('draft','published') NOT NULL DEFAULT 'draft',
    importance ENUM('normal','important','critical') NOT NULL DEFAULT 'normal',
    show_modal TINYINT(1) NOT NULL DEFAULT 0,
    published_at DATETIME NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_platform_changelog_publication (status, published_at),
    KEY idx_platform_changelog_creator (created_by),
    CONSTRAINT fk_platform_changelog_creator
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_changelog_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    changelog_id BIGINT UNSIGNED NOT NULL,
    category ENUM('new','improvement','fix','security','performance') NOT NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT NOT NULL,
    image_path VARCHAR(255) NULL,
    link_url VARCHAR(500) NULL,
    button_text VARCHAR(80) NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_platform_changelog_items (changelog_id, sort_order),
    CONSTRAINT fk_platform_changelog_item_parent
        FOREIGN KEY (changelog_id) REFERENCES platform_changelogs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS platform_changelog_views (
    changelog_id BIGINT UNSIGNED NOT NULL,
    user_id INT NOT NULL,
    viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    source ENUM('modal','center','admin') NOT NULL DEFAULT 'modal',
    PRIMARY KEY (changelog_id, user_id),
    KEY idx_platform_changelog_views_user (user_id, viewed_at),
    CONSTRAINT fk_platform_changelog_view_changelog
        FOREIGN KEY (changelog_id) REFERENCES platform_changelogs(id) ON DELETE CASCADE,
    CONSTRAINT fk_platform_changelog_view_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
