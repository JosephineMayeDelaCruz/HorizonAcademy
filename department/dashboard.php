<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('department');

$pdo  = getDB();
$user = currentUser();

// Get department for this user
$deptName = $user['department'] ?? '';
$dept = $pdo->prepare("SELECT id, name, code FROM departments WHERE name=? LIMIT 1");
$dept->execute([$deptName]);
$dept = $dept->fetch();

$deptId = $dept['id'] ?? 0;

// Stats
$totalReqs  = 0;
$pendingReqs = 0;
$approvedReqs = 0;

if ($deptId) {
    $totalReqs = $pdo->prepare(
        "SELECT COUNT(*) FROM student_clearance sc
         JOIN clearance_requirements cr ON cr.id = sc.requirement_id
         WHERE cr.department_id=?"
    );
    $totalReqs->execute([$deptId]);
    $totalReqs = (int)$totalReqs->fetchColumn();

    $pendingReqs = $pdo->prepare(
        "SELECT COUNT(*) FROM student_clearance sc
         JOIN clearance_requirements cr ON cr.id=sc.requirement_id
         WHERE cr.department_id=? AND sc.status='pending'"
    );
    $pendingReqs->execute([$deptId]);
    $pendingReqs = (int)$pendingReqs->fetchColumn();

    $approvedReqs = $pdo->prepare(
        "SELECT COUNT(*) FROM student_clearance sc
         JOIN clearance_requirements cr ON cr.id=sc.requirement_id
         WHERE cr.department_id=? AND sc.status='approved'"
    );
    $approvedReqs->execute([$deptId]);
    $approvedReqs = (int)$approvedReqs->fetchColumn();
}

$navItems = getNavItems('department', 'Dashboard');
$pageTitle  = 'Department Dashboard — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Department'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Department Dashboard — <?= htmlspecialchars($deptName) ?></div>

<?php if (!$deptId): ?>
<div class="alert-warn">
  <span class="warn-icon">⚠️</span>
  <div>
    <div class="warn-title">Department Not Configured</div>
    <div class="warn-body">Your account is not linked to a department. Please contact the administrator.</div>
  </div>
</div>
<?php else: ?>

<div class="stats-row">
  <div class="stat-card">
    <div><div class="stat-label">Clearance Requests</div><div class="stat-num"><?= number_format($totalReqs) ?></div></div>
    <span style="font-size:2rem;opacity:.5;">📋</span>
  </div>
  <div class="stat-card">
    <div><div class="stat-label">Pending</div><div class="stat-num" style="color:var(--gold);"><?= number_format($pendingReqs) ?></div></div>
    <span style="font-size:2rem;opacity:.5;">⏳</span>
  </div>
  <div class="stat-card">
    <div><div class="stat-label">Approved</div><div class="stat-num" style="color:var(--green);"><?= number_format($approvedReqs) ?></div></div>
    <span style="font-size:2rem;opacity:.5;">✅</span>
  </div>
</div>

<div class="panel">
  <div class="assign-form">
    <h3>Quick Links</h3>
    <a href="<?= url('department/clearance.php') ?>" class="btn-submit" style="text-decoration:none;display:block;text-align:center;margin-bottom:10px;">Manage Clearance</a>
    <a href="<?= url('department/students.php') ?>"  class="btn-submit btn-teal" style="text-decoration:none;display:block;text-align:center;">View Students</a>
  </div>
</div>

<?php endif; ?>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
