<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('student');

$pdo  = getDB();
$user = currentUser();
$sid  = (int)($user['student_db_id'] ?? 0);

if (!$sid) {
    redirectWith('student/dashboard.php', 'Student profile not found.', 'error');
}

// Ensure clearance rows exist for this student
$requirements = $pdo->query(
    "SELECT cr.id FROM clearance_requirements cr WHERE cr.school_year='2025-2026' AND cr.semester=2"
)->fetchAll(PDO::FETCH_COLUMN);

foreach ($requirements as $reqId) {
    $pdo->prepare(
        "INSERT IGNORE INTO student_clearance (student_id, requirement_id, status)
         VALUES (?, ?, 'pending')"
    )->execute([$sid, $reqId]);
}

// Load clearance data
$clearanceItems = $pdo->query(
    "SELECT cr.id AS req_id, cr.requirement,
            d.name AS dept_name, d.code AS dept_code,
            COALESCE(sc.status,'pending') AS status,
            sc.remarks, sc.reviewed_at,
            ru.full_name AS reviewed_by
     FROM clearance_requirements cr
     JOIN departments d ON d.id = cr.department_id
     LEFT JOIN student_clearance sc ON sc.requirement_id = cr.id AND sc.student_id=$sid
     LEFT JOIN users ru ON ru.id = sc.reviewed_by
     WHERE cr.school_year='2025-2026' AND cr.semester=2
     ORDER BY d.name, cr.requirement"
)->fetchAll();

$total    = count($clearanceItems);
$approved = count(array_filter($clearanceItems, fn($c) => $c['status'] === 'approved'));
$rejected = count(array_filter($clearanceItems, fn($c) => $c['status'] === 'rejected'));
$pending  = $total - $approved - $rejected;
$pct      = $total ? round($approved / $total * 100) : 0;
$fullyCleared = ($approved === $total && $total > 0);

// Group by department
$grouped = [];
foreach ($clearanceItems as $item) {
    $grouped[$item['dept_name']][] = $item;
}

$navItems = getNavItems('student', 'Clearance');
$pageTitle  = 'My Clearance — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Student'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">My Clearance Status</div>

<!-- Overall progress card -->
<div class="student-profile-card mb-16">
  <div style="font-size:2.4rem;"><?= $fullyCleared ? '🎉' : '📋' ?></div>
  <div>
    <div class="student-name"><?= $fullyCleared ? 'Clearance Complete!' : 'Clearance In Progress' ?></div>
    <div class="student-meta">
      <span style="color:var(--green);">✅ <?= $approved ?> Approved</span>
      <span style="color:var(--gold);">⏳ <?= $pending ?> Pending</span>
      <?php if ($rejected): ?><span style="color:var(--red);">❌ <?= $rejected ?> Rejected</span><?php endif; ?>
    </div>
  </div>
  <div style="margin-left:auto;text-align:right;">
    <div style="font-family:'Caveat',cursive;font-size:2rem;font-weight:700;color:<?= $fullyCleared?'var(--green)':'var(--gold)' ?>;"><?= $pct ?>%</div>
    <div class="stat-label">Overall Progress</div>
    <div class="progress-bar-wrap" style="width:120px;margin-top:6px;">
      <div class="progress-bar-fill" style="width:<?= $pct ?>%;background:<?= $fullyCleared?'var(--green)':'var(--accent-dark)' ?>;"></div>
    </div>
  </div>
</div>

<?php if ($fullyCleared): ?>
<div class="alert-warn" style="background:var(--green-light);border-color:var(--green);">
  <span class="warn-icon">🎓</span>
  <div>
    <div class="warn-title" style="color:var(--green);">All Clearance Requirements Approved!</div>
    <div class="warn-body" style="color:var(--green);">Your clearance is complete for A.Y. 2025-2026, 2nd Semester.</div>
  </div>
</div>
<?php endif; ?>

<?php foreach ($grouped as $dept => $items):
  $deptApproved = count(array_filter($items, fn($i) => $i['status']==='approved'));
  $deptTotal    = count($items);
  $deptCleared  = $deptApproved === $deptTotal;
?>
<div class="clearance-dept mb-16">
  <div class="clearance-dept-name">
    <span><?= htmlspecialchars($dept) ?></span>
    <span class="badge <?= $deptCleared?'badge-green':($deptApproved>0?'badge-teal':'badge-gold') ?>">
      <?= $deptApproved ?>/<?= $deptTotal ?> Cleared
    </span>
  </div>
  <?php foreach ($items as $item): ?>
  <div class="clearance-item">
    <span class="clearance-dot clearance-dot-<?= $item['status']==='approved'?'approved':($item['status']==='rejected'?'rejected':'pending') ?>"></span>
    <span style="flex:1;"><?= htmlspecialchars($item['requirement']) ?></span>
    <span class="badge <?= match($item['status']){'approved'=>'badge-green','rejected'=>'badge-red',default=>'badge-gold'} ?>">
      <?= ucfirst($item['status']) ?>
    </span>
    <?php if ($item['remarks']): ?>
    <span class="small text-muted" style="margin-left:8px;">— <?= htmlspecialchars($item['remarks']) ?></span>
    <?php endif; ?>
    <?php if ($item['reviewed_at']): ?>
    <span class="small text-muted" style="margin-left:8px;"><?= date('M d', strtotime($item['reviewed_at'])) ?></span>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php if (empty($clearanceItems)): ?>
<div style="background:var(--card-bg);border-radius:var(--radius-sm);padding:32px;text-align:center;color:var(--text-light);border:1px solid var(--border);">
  No clearance requirements configured yet. Please contact the registrar.
</div>
<?php endif; ?>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
