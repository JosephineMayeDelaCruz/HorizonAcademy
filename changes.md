# Horizon Academy — Change Log

## [2026-10-14] Fix: Login redirect loop for registrar, cashier, faculty, and department roles

### Problem
Users logging in as **registrar**, **cashier**, **faculty**, and **department** were immediately redirected back to the login page instead of reaching their dashboards.

### Root Cause
The original `schema.sql` defines the `users.role` column as:
```sql
ENUM('admin','faculty','student')
```
The newer roles (`registrar`, `cashier`, `department`) were only added in `migration.sql`, which is a separate file that must be run manually. If that migration was never applied to the database:

1. The `role` ENUM rejects `registrar`, `cashier`, and `department` values — those user accounts either can't be inserted or silently fail.
2. The `failed_login_attempts`, `last_failed_at`, and `locked_until` columns (added in `migrations/002_brute_force_lockout.sql`) may be missing — causing the login SQL query to throw a `PDOException`, which results in a PHP error or blank redirect.
3. Accounts that absorbed repeated failed login attempts could be locked via `locked_until`, preventing any login.

**Faculty** was affected for the same column-missing reason: if `migrations/002_brute_force_lockout.sql` was never run, every login query for *any* role would crash because those columns are SELECTed in `auth/login.php`.

### Fix Added
**`fix_db.php`** — A one-time diagnostic and migration runner script.

Run it by visiting:
```
http://localhost/horizon_academy-web/fix_db.php
```

It safely applies all of the following in sequence (each step is idempotent — safe to run multiple times):

| # | Action |
|---|--------|
| 1 | Extend `users.role` ENUM to include `registrar`, `cashier`, `department` |
| 2 | Add `failed_login_attempts` column to `users` (if missing) |
| 3 | Add `last_failed_at` column to `users` (if missing) |
| 4 | Add `locked_until` column to `users` (if missing) |
| 5 | Add index on `locked_until` (if missing) |
| 6 | Create `departments` table (if missing) |
| 7 | Seed department reference rows (if missing) |
| 8 | Seed `registrar`, `cashier`, `dept_ccs` user accounts (if missing) |
| 9 | Create `enrollment_queue`, `student_clearance`, `clearance_requirements`, `document_types`, `document_requests`, `document_request_logs`, `activity_log` tables (if missing) |
| 10 | Seed document type rows (if missing) |
| 11 | Add `totp_secret` column to `students` (if missing) |
| 12 | Unlock all locked accounts and reset `failed_login_attempts` counters |
| 13 | Hash any remaining plaintext passwords via `password_hash()` |

After running, the page displays:
- A pass/fail table for every migration step
- The full user accounts list with lock status
- Default login credentials for all roles

### Default Login Credentials (after fix)

| Role       | Username     | Password   |
|------------|--------------|------------|
| Admin      | `admin`      | `admin123` |
| Faculty    | `rfernandez` | `fac123`   |
| Faculty    | `jmendoza`   | `fac123`   |
| Faculty    | `asantos`    | `fac123`   |
| Faculty    | `mreyes`     | `fac123`   |
| Student    | `2403938`    | `stu123`   |
| Registrar  | `registrar`  | `reg123`   |
| Cashier    | `cashier`    | `cash123`  |
| Department | `dept_ccs`   | `dept123`  |

> **Security:** Delete `fix_db.php` after running it. It should never be left accessible in a production environment.

### Files Changed
- `fix_db.php` — **Added** (new diagnostic/migration runner script)
- `changes.md` — **Added** (this file)

### Files NOT Changed
All existing PHP logic files (`auth/login.php`, `config/auth.php`, `config/db.php`, `config/paths.php`, dashboards, nav, partials) were **not modified** — the login logic is correct. The issue was purely a missing database migration.
