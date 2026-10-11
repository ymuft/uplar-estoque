-- M6 / 2026-10-10. Idempotente; aplicar primeiro em staging com backup.
-- Defaults preservam o fluxo existente: estoque físico e produto habilitado.
-- Horários e pausa são salvos em vitrine_config.experiencia_json.atendimento.
SET @uplar_schema := DATABASE();

SET @uplar_add_mode := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=@uplar_schema AND table_name='produtos' AND column_name='modo_disponibilidade'),
    'SELECT 1',
    'ALTER TABLE produtos ADD COLUMN modo_disponibilidade ENUM(''estoque'', ''sob_demanda'') NOT NULL DEFAULT ''estoque'' AFTER quantidade'
);
PREPARE uplar_m6_stmt FROM @uplar_add_mode;
EXECUTE uplar_m6_stmt;
DEALLOCATE PREPARE uplar_m6_stmt;

SET @uplar_add_manual := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema=@uplar_schema AND table_name='produtos' AND column_name='disponibilidade_manual'),
    'SELECT 1',
    'ALTER TABLE produtos ADD COLUMN disponibilidade_manual TINYINT(1) NOT NULL DEFAULT 1 AFTER modo_disponibilidade'
);
PREPARE uplar_m6_stmt FROM @uplar_add_manual;
EXECUTE uplar_m6_stmt;
DEALLOCATE PREPARE uplar_m6_stmt;

-- Rollback (somente antes de utilizar dados M6):
-- ALTER TABLE produtos DROP COLUMN disponibilidade_manual;
-- ALTER TABLE produtos DROP COLUMN modo_disponibilidade;
