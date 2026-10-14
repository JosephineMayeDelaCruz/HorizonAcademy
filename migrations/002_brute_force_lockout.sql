-- ═════════════════════════════════════════════════════════════════════════════
--  HORIZON ACADEMY — MIGRATION 002: Brute-Force Lockout Columns
--  Adds failed-login tracking and account-lockout support to the users table.
--  Run ONCE against an already-migrated (migration.sql applied) database.
-- ═════════════════════════════════════════════════════════════════════════════

USE horizon_academy;

-- ── 1. Brute-force lockout columns on users ───────────────────────────────
--
--  failed_login_attempts  — rolling counter, reset to 0 on successful login
--  last_failed_at         — timestamp of the most recent failed attempt;
--                           used to age-out the counter after the window expires
--  locked_until           — when non-NULL and in the future, the account is
--                           locked and all login attempts are rejected
--
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS failed_login_attempts TINYINT   UNSIGNED NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS last_failed_at        DATETIME           DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS locked_until          DATETIME           DEFAULT NULL;

-- Index on locked_until so the lockout check (WHERE locked_until > NOW()) is fast.
CREATE INDEX IF NOT EXISTS idx_users_locked_until ON users (locked_until);
