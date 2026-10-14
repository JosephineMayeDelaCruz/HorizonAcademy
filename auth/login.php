<?php
/**
 * ═══════════════════════════════════════════════════════════
 * LOGIN PROCESSOR — auth/login.php
 * ═══════════════════════════════════════════════════════════
 * Handles form submission: validates credentials and sets session.
 *
 * Security controls:
 *   • Rate limiting  — max 10 POST attempts per IP per 15-minute window
 *                      (session-based; resets automatically after the window)
 *   • Brute-force lockout — max 5 consecutive failed password attempts per
 *                      account; locks the account for 15 minutes
 */

require_once __DIR__ . '/../config/auth.php';

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// CONSTANTS
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
const RATE_LIMIT_MAX      = 10;   // max login attempts per IP in the window
const RATE_LIMIT_WINDOW   = 900;  // 15 minutes in seconds
const LOCKOUT_MAX_ATTEMPTS = 5;   // consecutive failures before account lock
const LOCKOUT_DURATION     = 900; // 15 minutes in seconds

function passwordMatches(string $input, string $stored): bool {
    // Support both bcrypt-hashed passwords and legacy plaintext (dev seed data).
    return password_verify($input, $stored) || hash_equals($stored, $input);
}

// Only process login requests from forms; redirect everything else.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('index.php'));
    exit;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// RATE LIMITING (per IP, session-backed)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Initialise the rate-limit bucket in session if it doesn't exist or has expired.
if (
    empty($_SESSION['rl_login_ip'])                                      ||
    $_SESSION['rl_login_ip'] !== $ip                                     ||
    (time() - ($_SESSION['rl_login_ts'] ?? 0)) > RATE_LIMIT_WINDOW
) {
    $_SESSION['rl_login_ip']    = $ip;
    $_SESSION['rl_login_ts']    = time();
    $_SESSION['rl_login_count'] = 0;
}

$_SESSION['rl_login_count']++;

if ($_SESSION['rl_login_count'] > RATE_LIMIT_MAX) {
    $remaining = RATE_LIMIT_WINDOW - (time() - $_SESSION['rl_login_ts']);
    $minutes   = (int)ceil($remaining / 60);
    redirectWith(
        'index.php',
        "Too many login attempts. Please wait {$minutes} minute(s) and try again.",
        'error'
    );
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// VALIDATE INPUT
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$role     = trim($_POST['role']     ?? '');
$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

$validRoles = ['admin', 'faculty', 'student', 'registrar', 'cashier', 'department'];
if (!in_array($role, $validRoles, true) || !$username || !$password) {
    redirectWith('index.php', 'Please fill in all fields.', 'error');
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FETCH USER — INNER JOIN to ensure role matches
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$pdo  = getDB();
$stmt = $pdo->prepare(
    "SELECT
        u.id,
        u.username,
        u.email,
        u.password,
        u.role,
        u.full_name,
        u.department,
        u.failed_login_attempts,
        u.last_failed_at,
        u.locked_until
     FROM users u
     WHERE u.username = ? AND u.role = ?
     LIMIT 1"
);
$stmt->execute([$username, $role]);
$user = $stmt->fetch();

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// ACCOUNT LOCKOUT CHECK
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Only run if we found the account — avoids leaking "account exists" info
// through timing differences when the account doesn't exist at all.
if ($user && !empty($user['locked_until'])) {
    $lockedUntil = strtotime($user['locked_until']);
    if ($lockedUntil > time()) {
        $remaining = (int)ceil(($lockedUntil - time()) / 60);
        redirectWith(
            'index.php',
            "Account is temporarily locked due to too many failed attempts. "
            . "Try again in {$remaining} minute(s).",
            'error'
        );
    }
    // Lock has expired — clear it so counters start fresh.
    $pdo->prepare(
        "UPDATE users SET failed_login_attempts=0, last_failed_at=NULL, locked_until=NULL WHERE id=?"
    )->execute([$user['id']]);
    $user['failed_login_attempts'] = 0;
    $user['locked_until']          = null;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// VERIFY PASSWORD
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if (!$user || !passwordMatches($password, $user['password'])) {
    // Record failed attempt only when the account exists (avoid enumeration
    // by still showing the generic error regardless).
    if ($user) {
        $newCount    = (int)$user['failed_login_attempts'] + 1;
        $lockUntilSql = null;

        if ($newCount >= LOCKOUT_MAX_ATTEMPTS) {
            // Lock the account for LOCKOUT_DURATION seconds.
            $lockUntilSql = date('Y-m-d H:i:s', time() + LOCKOUT_DURATION);
        }

        $pdo->prepare(
            "UPDATE users
             SET failed_login_attempts = ?,
                 last_failed_at        = NOW(),
                 locked_until          = ?
             WHERE id = ?"
        )->execute([$newCount, $lockUntilSql, $user['id']]);
    }

    redirectWith('index.php', 'Invalid username or password.', 'error');
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// SUCCESSFUL LOGIN — reset lockout counters
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$pdo->prepare(
    "UPDATE users
     SET failed_login_attempts = 0,
         last_failed_at        = NULL,
         locked_until          = NULL
     WHERE id = ?"
)->execute([$user['id']]);

// Also clear the rate-limit counter for this session on successful login.
$_SESSION['rl_login_count'] = 0;

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// BUILD SESSION DATA
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$_SESSION['user_id'] = $user['id'];
$_SESSION['role']    = $user['role'];

$userData = [
    'id'         => $user['id'],
    'username'   => $user['username'],
    'email'      => $user['email'],
    'role'       => $user['role'],
    'full_name'  => $user['full_name'],
    'department' => $user['department'],
];

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// FETCH STUDENT PROFILE (if student role)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if ($role === 'student') {
    $stmt = $pdo->prepare(
        "SELECT id, student_number, program, section, year_level
         FROM students
         WHERE user_id = ?
         LIMIT 1"
    );
    $stmt->execute([$user['id']]);
    $student = $stmt->fetch() ?: [];

    $userData                    = array_merge($userData, $student);
    $userData['student_db_id']   = $student['id'] ?? 0;
}

$_SESSION['user'] = $userData;

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// REDIRECT TO DASHBOARD
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$dashboards = [
    'admin'      => url('admin/dashboard.php'),
    'faculty'    => url('faculty/dashboard.php'),
    'student'    => url('student/dashboard.php'),
    'registrar'  => url('registrar/dashboard.php'),
    'cashier'    => url('cashier/dashboard.php'),
    'department' => url('department/dashboard.php'),
];

header('Location: ' . $dashboards[$role]);
exit;
