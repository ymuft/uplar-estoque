ALTER TABLE users
    ADD COLUMN passcode_required TINYINT(1) NOT NULL DEFAULT 0 AFTER passcode_configured_at;
