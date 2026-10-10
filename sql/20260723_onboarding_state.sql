CREATE TABLE IF NOT EXISTS onboarding_state (
    user_id INT NOT NULL PRIMARY KEY,
    status ENUM('in_progress','completed','dismissed') NOT NULL DEFAULT 'in_progress',
    collapsed TINYINT(1) NOT NULL DEFAULT 0,
    shared_at DATETIME NULL,
    completed_at DATETIME NULL,
    celebration_seen_at DATETIME NULL,
    tour_seen_at DATETIME NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_onboarding_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
