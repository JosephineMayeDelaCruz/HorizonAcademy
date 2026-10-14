<?php
/**
 * ═══════════════════════════════════════════════════════════
 * FIX DB — Diagnostic & Migration Runner
 * ═══════════════════════════════════════════════════════════
 * Run this ONCE to diagnose and fix login issues for
 * registrar, cashier, faculty, and department roles.
 *
 * Access: http://localhost/horizon_academy-web/fix_db.php
 * DELETE or restrict this file after running.
 */

require_once __DIR__ . '/config/db.php';

$pdo  = getDB();
$logs = [];
$ok   = true;

function step(string $label, callable $fn, array &$logs, bool &$ok): void {
    try {
        $result = $fn();
        $logs[] = ['ok', $label, $result ?? ''];
    } catch (Throwable $e) {
        $logs[] = ['fail', $label, $e->getMessage()];
        $ok = false;
    }
}

// ── STEP 0: Drop the old CHECK constraint that blocks new role values ───────
step('Drop chk_users_role CHECK constraint (if exists)', function () use ($pdo) {
    // Check if the constraint exists before trying to drop it.
    $exists = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE()
           AND TABLE_NAME        = 'users'
           AND CONSTRAINT_NAME   = 'chk_users_role'
           AND CONSTRAINT_TYPE   = 'CHECK'"
    )->fetchColumn();

    if ($exists) {
        $pdo->exec("ALTER TABLE users DROP CONSTRAINT chk_users_role");
        return 'Constraint chk_users_role dropped';
    }
    return 'Constraint did not exist — nothing to drop';
}, $logs, $ok);

// ── STEP 1: Extend the role ENUM to include new roles ──────────────────────
step('Extend users.role ENUM (add registrar, cashier, department)', function () use ($pdo) {
    $pdo->exec(
        "ALTER TABLE users
         MODIFY COLUMN role ENUM('admin','faculty','student','registrar','cashier','department')
         NOT NULL DEFAULT 'student'"
    );
    return 'ENUM updated to include registrar, cashier, department';
}, $logs, $ok);

// ── STEP 2: Add brute-force lockout columns if missing ─────────────────────
step('Add failed_login_attempts column (if missing)', function () use ($pdo) {
    $pdo->exec(
        "ALTER TABLE users
         ADD COLUMN IF NOT EXISTS failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0"
    );
    return 'Column added or already exists';
}, $logs, $ok);

step('Add last_failed_at column (if missing)', function () use ($pdo) {
    $pdo->exec(
        "ALTER TABLE users
         ADD COLUMN IF NOT EXISTS last_failed_at DATETIME DEFAULT NULL"
    );
    return 'Column added or already exists';
}, $logs, $ok);

step('Add locked_until column (if missing)', function () use ($pdo) {
    $pdo->exec(
        "ALTER TABLE users
         ADD COLUMN IF NOT EXISTS locked_until DATETIME DEFAULT NULL"
    );
    return 'Column added or already exists';
}, $logs, $ok);

step('Add index on locked_until (if missing)', function () use ($pdo) {
    // Check if index exists first to avoid duplicate key error.
    $idx = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.statistics
         WHERE table_schema = DATABASE()
           AND table_name = 'users'
           AND index_name = 'idx_users_locked_until'"
    )->fetchColumn();
    if (!$idx) {
        $pdo->exec("CREATE INDEX idx_users_locked_until ON users (locked_until)");
        return 'Index created';
    }
    return 'Index already exists';
}, $logs, $ok);

// ── STEP 3: Departments table ──────────────────────────────────────────────
step('Create departments table (if missing)', function () use ($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS departments (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(150) NOT NULL UNIQUE,
            code        VARCHAR(30)  NOT NULL UNIQUE,
            description TEXT         DEFAULT NULL,
            created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
        )"
    );
    return 'Table exists or created';
}, $logs, $ok);

step('Seed department rows (if missing)', function () use ($pdo) {
    $pdo->exec(
        "INSERT IGNORE INTO departments (name, code, description) VALUES
          ('College of Computing Studies',   'CCS',      'Bachelor programs in Computing'),
          ('College of Engineering',         'CE',       'Bachelor programs in Engineering'),
          ('Library',                        'LIB',      'Library clearance'),
          ('Guidance Office',                'GUIDANCE', 'Student guidance clearance'),
          ('Finance / Cashier',              'FINANCE',  'Financial clearance'),
          ('Registrar',                      'REGISTRAR','Registrar office')"
    );
    return 'Departments seeded (existing rows skipped)';
}, $logs, $ok);

// ── STEP 4: Seed new-role staff users (if missing) ─────────────────────────
step('Seed registrar user (if missing)', function () use ($pdo) {
    $pdo->exec(
        "INSERT IGNORE INTO users (username, email, password, role, full_name, department) VALUES
          ('registrar', 'registrar@horizon.edu', 'reg123', 'registrar', 'Maria Santos', 'Registrar')"
    );
    return 'registrar user seeded (skipped if already exists)';
}, $logs, $ok);

step('Seed cashier user (if missing)', function () use ($pdo) {
    $pdo->exec(
        "INSERT IGNORE INTO users (username, email, password, role, full_name, department) VALUES
          ('cashier', 'cashier@horizon.edu', 'cash123', 'cashier', 'Pedro Cruz', 'Finance / Cashier')"
    );
    return 'cashier user seeded (skipped if already exists)';
}, $logs, $ok);

step('Seed department user (if missing)', function () use ($pdo) {
    $pdo->exec(
        "INSERT IGNORE INTO users (username, email, password, role, full_name, department) VALUES
          ('dept_ccs', 'dept.ccs@horizon.edu', 'dept123', 'department', 'CCS Department Head', 'College of Computing Studies')"
    );
    return 'dept_ccs user seeded (skipped if already exists)';
}, $logs, $ok);

// ── STEP 5: Create other migration tables (if missing) ─────────────────────
step('Create enrollment_queue table (if missing)', function () use ($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS enrollment_queue (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            queue_number     VARCHAR(20)  NOT NULL,
            student_id       INT          NOT NULL,
            category         VARCHAR(60)  NOT NULL DEFAULT 'General Enrollment',
            status           ENUM('waiting','serving','completed','skipped') NOT NULL DEFAULT 'waiting',
            enrollment_period VARCHAR(30) NOT NULL DEFAULT '2025-2026-2',
            called_by        INT          DEFAULT NULL,
            called_at        DATETIME     DEFAULT NULL,
            served_at        DATETIME     DEFAULT NULL,
            created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_active_queue (student_id, enrollment_period, status),
            CONSTRAINT fk_eq_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            CONSTRAINT fk_eq_caller  FOREIGN KEY (called_by)  REFERENCES users(id)    ON DELETE SET NULL
        )"
    );
    return 'Table exists or created';
}, $logs, $ok);

step('Create clearance_requirements table (if missing)', function () use ($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS clearance_requirements (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            department_id INT NOT NULL,
            requirement   VARCHAR(200) NOT NULL,
            school_year   VARCHAR(20)  NOT NULL DEFAULT '2025-2026',
            semester      TINYINT      NOT NULL DEFAULT 2,
            created_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_cr_dept FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE
        )"
    );
    return 'Table exists or created';
}, $logs, $ok);

step('Create student_clearance table (if missing)', function () use ($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS student_clearance (
            id             INT AUTO_INCREMENT PRIMARY KEY,
            student_id     INT NOT NULL,
            requirement_id INT NOT NULL,
            status         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            remarks        TEXT DEFAULT NULL,
            reviewed_by    INT  DEFAULT NULL,
            reviewed_at    DATETIME DEFAULT NULL,
            created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_student_req (student_id, requirement_id),
            CONSTRAINT fk_sc_student   FOREIGN KEY (student_id)     REFERENCES students(id)              ON DELETE CASCADE,
            CONSTRAINT fk_sc_req       FOREIGN KEY (requirement_id) REFERENCES clearance_requirements(id) ON DELETE CASCADE,
            CONSTRAINT fk_sc_reviewer  FOREIGN KEY (reviewed_by)    REFERENCES users(id)                 ON DELETE SET NULL
        )"
    );
    return 'Table exists or created';
}, $logs, $ok);

step('Create document_types table (if missing)', function () use ($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS document_types (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            name        VARCHAR(150) NOT NULL UNIQUE,
            fee         DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            description TEXT         DEFAULT NULL,
            is_active   TINYINT(1)   NOT NULL DEFAULT 1,
            created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
        )"
    );
    return 'Table exists or created';
}, $logs, $ok);

step('Create document_requests table (if missing)', function () use ($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS document_requests (
            id               INT AUTO_INCREMENT PRIMARY KEY,
            reference_no     VARCHAR(30)  NOT NULL UNIQUE,
            student_id       INT          NOT NULL,
            document_type_id INT          NOT NULL,
            purpose          TEXT         NOT NULL,
            copies           TINYINT      NOT NULL DEFAULT 1,
            status           ENUM('submitted','pending','processing','ready_for_pickup','completed','rejected') NOT NULL DEFAULT 'submitted',
            payment_status   ENUM('unpaid','paid','waived') NOT NULL DEFAULT 'unpaid',
            fee_amount       DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            payment_ref      VARCHAR(80)  DEFAULT NULL,
            staff_remarks    TEXT         DEFAULT NULL,
            processed_by     INT          DEFAULT NULL,
            verified_by      INT          DEFAULT NULL,
            created_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            updated_at       TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_dr_student   FOREIGN KEY (student_id)       REFERENCES students(id)       ON DELETE CASCADE,
            CONSTRAINT fk_dr_doctype   FOREIGN KEY (document_type_id) REFERENCES document_types(id) ON DELETE RESTRICT,
            CONSTRAINT fk_dr_processor FOREIGN KEY (processed_by)     REFERENCES users(id)          ON DELETE SET NULL,
            CONSTRAINT fk_dr_verifier  FOREIGN KEY (verified_by)      REFERENCES users(id)          ON DELETE SET NULL
        )"
    );
    return 'Table exists or created';
}, $logs, $ok);

step('Create document_request_logs table (if missing)', function () use ($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS document_request_logs (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            old_status VARCHAR(30) DEFAULT NULL,
            new_status VARCHAR(30) NOT NULL,
            changed_by INT NOT NULL,
            remarks    TEXT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_drl_request FOREIGN KEY (request_id) REFERENCES document_requests(id) ON DELETE CASCADE,
            CONSTRAINT fk_drl_user    FOREIGN KEY (changed_by)  REFERENCES users(id)             ON DELETE CASCADE
        )"
    );
    return 'Table exists or created';
}, $logs, $ok);

step('Create activity_log table (if missing)', function () use ($pdo) {
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS activity_log (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            user_id    INT          NOT NULL,
            action     VARCHAR(200) NOT NULL,
            module     VARCHAR(60)  DEFAULT NULL,
            ip_address VARCHAR(45)  DEFAULT NULL,
            created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
            CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )"
    );
    return 'Table exists or created';
}, $logs, $ok);

step('Seed document types (if missing)', function () use ($pdo) {
    $pdo->exec(
        "INSERT IGNORE INTO document_types (name, fee, description) VALUES
          ('Certificate of Enrollment',   50.00,  'Official certificate confirming student enrollment'),
          ('Transcript of Records (TOR)', 150.00, 'Official academic transcript'),
          ('Certificate of Grades',       50.00,  'Certified copy of grades per semester'),
          ('Good Moral Certificate',      50.00,  'Certificate of good moral character'),
          ('Certificate of Graduation',   100.00, 'Official graduation certificate'),
          ('Honorable Dismissal',         100.00, 'Honorable dismissal form'),
          ('Authentication',              30.00,  'Authentication of school documents')"
    );
    return 'Document types seeded (existing rows skipped)';
}, $logs, $ok);

// ── STEP 6: TOTP column on students (if missing) ───────────────────────────
step('Add totp_secret column to students (if missing)', function () use ($pdo) {
    $pdo->exec(
        "ALTER TABLE students
         ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(32) DEFAULT NULL"
    );
    return 'Column added or already exists';
}, $logs, $ok);

// ── STEP 7: Unlock any locked accounts ─────────────────────────────────────
step('Unlock all locked accounts and reset failed_login_attempts', function () use ($pdo) {
    $affected = $pdo->exec(
        "UPDATE users
         SET failed_login_attempts = 0,
             last_failed_at        = NULL,
             locked_until          = NULL
         WHERE locked_until IS NOT NULL OR failed_login_attempts > 0"
    );
    return "Unlocked / reset {$affected} account(s)";
}, $logs, $ok);

// ── STEP 8: Re-hash any plaintext passwords ────────────────────────────────
step('Hash any remaining plaintext passwords', function () use ($pdo) {
    $users = $pdo->query("SELECT id, password FROM users")->fetchAll();
    $count = 0;
    foreach ($users as $u) {
        if (str_starts_with($u['password'], '$2y$')) continue;
        $hashed = password_hash($u['password'], PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashed, $u['id']]);
        $count++;
    }
    return "Hashed {$count} plaintext password(s)";
}, $logs, $ok);

// ── STEP 9: Show current users table ───────────────────────────────────────
$users = $pdo->query(
    "SELECT id, username, role, full_name,
            failed_login_attempts,
            IF(locked_until IS NOT NULL AND locked_until > NOW(), 'LOCKED', 'OK') AS account_status
     FROM users ORDER BY role, username"
)->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Fix DB — Horizon Academy</title>
  <style>
    body { font-family: 'Segoe UI', sans-serif; background: #F5F0E8; padding: 32px; color: #2C2416; }
    h1   { color: #806B52; }
    h2   { margin-top: 2rem; color: #4A3728; }
    table { border-collapse: collapse; width: 100%; margin-top: .5rem; }
    th, td { padding: 8px 12px; border: 1px solid #D4C5A9; text-align: left; }
    th { background: #E8DFD0; }
    .ok   { color: #2E7D52; font-weight: 700; }
    .fail { color: #C0392B; font-weight: 700; }
    .banner { padding: 14px 20px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 1.1rem; font-weight: 600; }
    .banner.success { background: #D4EDDA; color: #155724; border: 1px solid #C3E6CB; }
    .banner.error   { background: #F8D7DA; color: #721C24; border: 1px solid #F5C6CB; }
    .cred { background: #FFF8E1; border: 1px solid #FFE082; padding: 12px 16px; border-radius: 8px; margin-top: .5rem; }
    .cred code { font-size: .95rem; }
    .delete-note { background: #F8D7DA; border: 1px solid #F5C6CB; padding: 12px 16px; border-radius: 8px; margin-top: 2rem; font-weight: 600; }
  </style>
</head>
<body>

<h1>🔧 Horizon Academy — DB Fix &amp; Diagnostic</h1>

<div class="banner <?= $ok ? 'success' : 'error' ?>">
  <?= $ok ? '✅ All steps completed successfully.' : '⚠️ One or more steps failed — see details below.' ?>
</div>

<h2>Migration Steps</h2>
<table>
  <thead><tr><th>Status</th><th>Step</th><th>Detail</th></tr></thead>
  <tbody>
    <?php foreach ($logs as [$status, $label, $detail]): ?>
    <tr>
      <td class="<?= $status ?>">
        <?= $status === 'ok' ? '✅ OK' : '❌ FAIL' ?>
      </td>
      <td><?= htmlspecialchars($label) ?></td>
      <td style="color:#555;"><?= htmlspecialchars($detail) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h2>Current User Accounts</h2>
<table>
  <thead>
    <tr><th>ID</th><th>Username</th><th>Role</th><th>Full Name</th><th>Failed Attempts</th><th>Account Status</th></tr>
  </thead>
  <tbody>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><?= $u['id'] ?></td>
      <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
      <td><?= htmlspecialchars($u['role']) ?></td>
      <td><?= htmlspecialchars($u['full_name']) ?></td>
      <td><?= $u['failed_login_attempts'] ?></td>
      <td style="color:<?= $u['account_status'] === 'LOCKED' ? '#C0392B' : '#2E7D52' ?>;font-weight:700;">
        <?= $u['account_status'] ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h2>Default Login Credentials</h2>
<div class="cred">
  <table style="border:none;">
    <thead><tr><th>Role</th><th>Username</th><th>Password</th></tr></thead>
    <tbody>
      <tr><td>Admin</td>      <td><code>admin</code></td>      <td><code>admin123</code></td></tr>
      <tr><td>Faculty</td>    <td><code>rfernandez</code></td> <td><code>fac123</code></td></tr>
      <tr><td>Faculty</td>    <td><code>jmendoza</code></td>   <td><code>fac123</code></td></tr>
      <tr><td>Student</td>    <td><code>2403938</code></td>    <td><code>stu123</code></td></tr>
      <tr><td>Registrar</td>  <td><code>registrar</code></td>  <td><code>reg123</code></td></tr>
      <tr><td>Cashier</td>    <td><code>cashier</code></td>    <td><code>cash123</code></td></tr>
      <tr><td>Department</td> <td><code>dept_ccs</code></td>   <td><code>dept123</code></td></tr>
    </tbody>
  </table>
</div>

<div class="delete-note">
  ⚠️ <strong>Security Note:</strong> Delete or restrict access to this file after running it.
  It exposes database operations and default credentials.
</div>

</body>
</html>
