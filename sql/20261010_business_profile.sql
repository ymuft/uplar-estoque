-- M1: migration preparada; executar manualmente APÓS autorização e backup.
-- MySQL 8.x; idempotente e sem backfill de registros antigos.
SET @uplar_profile_column_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'empresa_config'
      AND COLUMN_NAME = 'perfil_negocio_json'
);
SET @uplar_profile_ddl := IF(
    @uplar_profile_column_exists = 0,
    'ALTER TABLE empresa_config ADD COLUMN perfil_negocio_json JSON NULL',
    'DO 0'
);
PREPARE uplar_profile_stmt FROM @uplar_profile_ddl;
EXECUTE uplar_profile_stmt;
DEALLOCATE PREPARE uplar_profile_stmt;
