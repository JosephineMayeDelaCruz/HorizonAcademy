-- ═════════════════════════════════════════════════════════════════════════════
--  HORIZON ACADEMY — TOTP MIGRATION
--  Adds TOTP secret storage to the students table for physical presence
--  verification in the enrollment queue.
--  Run ONCE against a database that already has migration.sql applied.
-- ═════════════════════════════════════════════════════════════════════════════

USE horizon_academy;

ALTER TABLE students
    ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(32) DEFAULT NULL
        COMMENT 'Base32-encoded TOTP shared secret; NULL until student enrolls in TOTP verification';

-- ── Brute-force lockout columns ───────────────────────────────────────────
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS last_failed_at        DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS locked_until          DATETIME DEFAULT NULL;
