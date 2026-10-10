ALTER TABLE users
    ADD COLUMN auth_version INT UNSIGNED NOT NULL DEFAULT 1 AFTER password;

ALTER TABLE despesas
    ADD COLUMN pedido_vitrine_id INT UNSIGNED NULL AFTER id,
    ADD UNIQUE KEY uq_despesas_user_pedido_vitrine (user_id, pedido_vitrine_id);
