ALTER TABLE users
    ADD COLUMN passcode_hash VARCHAR(255) NULL AFTER password,
    ADD COLUMN passcode_configured_at DATETIME NULL AFTER passcode_hash,
    ADD COLUMN passcode_prompt_dismissed_at DATETIME NULL AFTER passcode_configured_at;

CREATE TABLE passcode_tentativas (
    user_id INT NOT NULL,
    ip VARCHAR(45) NOT NULL,
    tentativas TINYINT UNSIGNED NOT NULL DEFAULT 0,
    ultima_tentativa DATETIME NOT NULL,
    PRIMARY KEY (user_id, ip),
    KEY idx_passcode_tentativas_ultima (ultima_tentativa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
