-- M3: migration EXPAND sem backfill e sem alteração de vitrines.
-- Somente staging autorizado; produção requer aprovação específica.
CREATE TABLE IF NOT EXISTS catalogo_categorias (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT NOT NULL,
  nome VARCHAR(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  ordem INT NOT NULL DEFAULT 0,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY(id),
  UNIQUE KEY uq_catalogo_user_nome(user_id,nome),
  KEY idx_catalogo_user_ordem(user_id,ordem,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @uplar_m3_categoria_col := (
 SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='produtos' AND COLUMN_NAME='categoria_id'
);
SET @uplar_m3_categoria_sql := IF(
 @uplar_m3_categoria_col=0,
 'ALTER TABLE produtos ADD COLUMN categoria_id INT UNSIGNED NULL, ADD INDEX idx_produtos_categoria_user (user_id, categoria_id)',
 'DO 0'
);
PREPARE uplar_m3_categoria_stmt FROM @uplar_m3_categoria_sql;
EXECUTE uplar_m3_categoria_stmt;
DEALLOCATE PREPARE uplar_m3_categoria_stmt;
