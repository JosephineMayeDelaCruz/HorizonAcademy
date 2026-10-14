<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('student');

$pdo  = getDB();
$user = currentUser();
$sid  = (int)($user['student_db_id'] ?? 0);

// All enrolled courses for this student (via grades)
// This block fetches courses where the student currently has enrollment-grade records.
$enrolledCourses = $pdo->prepare(
    "SELECT c.id, c.course_code, c.title, c.schedule, c.max_lessons,
            u2.full_name AS teacher
     FROM grades g
     JOIN courses c ON c.id = g.course_id
     JOIN users u2  ON u2.id = c.teacher_id
     WHERE g.student_id = ? ORDER BY c.course_code"
);
$enrolledCourses->execute([$sid]);
$courses = $enrolledCourses->fetchAll();

$selectedId = (int)($_GET['course_id'] ?? ($courses[0]['id'] ?? 0));
$current    = array_values(array_filter($courses, fn($c) => $c['id'] === $selectedId));
$current    = $current[0] ?? null;

// Stats for selected course
// This block calculates attendance counts and allowed absence limits for the selected course.
$stats = ['present' => 0, 'absent' => 0, 'late' => 0, 'total' => 0];
$maxAbsences = 0;
if ($selectedId && $sid) {
    $s = $pdo->prepare(
        "SELECT status, COUNT(*) AS cnt FROM attendance
         WHERE student_id=? AND course_id=? GROUP BY status"
    );
    $s->execute([$sid, $selectedId]);
    foreach ($s->fetchAll() as $row) {
        $stats[$row['status']] = (int)$row['cnt'];
        $stats['total'] += (int)$row['cnt'];
    }
    if ($current) {
        $maxAbsences = (int)floor($current['max_lessons'] * 0.20);
    }
}

$remaining = max(0, $maxAbsences - $stats['absent']);
$pct = $stats['total'] > 0 ? round($stats['present'] / $stats['total'] * 100, 1) : 0;

// Attendance log (last 30 entries, most recent first)
// This block loads recent attendance entries so the student can review their latest statuses.
$log = [];
if ($sid && $selectedId) {
    $logStmt = $pdo->prepare(
        "SELECT date, status FROM attendance
         WHERE student_id=? AND course_id=?
         ORDER BY date DESC LIMIT 30"
    );
    $logStmt->execute([$sid, $selectedId]);
    $log = $logStmt->fetchAll();
}

$navItems = getNavItems('student', 'My Attendance');
$pageTitle  = 'My Attendance — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Student'];

include __DIR__ . '/../partials/layout_start.php';
?>

<!-- This block renders course selection, attendance summary cards, and the recent attendance log table. -->
<div class="content-title">My Attendance Record</div>

<form method="GET">
  <div class="select-course-box">
    <label>Select Course</label>
    <select name="course_id" onchange="this.form.submit()">
      <?php foreach ($courses as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $c['id'] === $selectedId ? 'selected' : '' ?>>
          <?= htmlspecialchars($c['course_code'] . ' — ' . $c['title']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</form>

<?php if ($current): ?>
<div class="course-detail-banner">
  <div class="cdn">
    <?= htmlspecialchars($current['course_code'] . ' — ' . $current['title']) ?>
    <span class="course-badge"><?= number_format($pct, 1) ?>% Attendance</span>
    <?php if ($pct < 80): ?>
    <span class="course-badge" style="background:var(--red);color:white;">! LOW</span>
    <?php endif; ?>
  </div>
  <div class="cds"><?= htmlspecialchars($current['teacher'] ?? '—') ?> &nbsp; <?= htmlspecialchars($current['schedule'] ?? '') ?></div>
</div>
<?php endif; ?>

<div class="attend-stats-row" style="margin-bottom:16px;">
  <div class="attend-stat-card">
    <span style="font-size:1.8rem;">📅</span>
    <div class="asnum"><?= $stats['total'] ?></div>
    <div class="aslabel">Total Sessions</div>
  </div>
  <div class="attend-stat-card">
    <span style="font-size:1.8rem;">✅</span>
    <div class="asnum" style="color:#2a9d5c;"><?= $stats['present'] ?></div>
    <div class="aslabel">Present</div>
  </div>
  <div class="attend-stat-card">
    <span style="font-size:1.8rem;">❌</span>
    <div class="asnum" style="color:var(--red);"><?= $stats['absent'] ?></div>
    <div class="aslabel">Absent</div>
  </div>
  <div class="attend-stat-card">
    <span style="font-size:1.8rem;">⏰</span>
    <div class="asnum" style="color:var(--teal);"><?= $stats['late'] ?></div>
    <div class="aslabel">Late</div>
  </div>
  <div class="remain-card">
    <div>
      <div class="rlabel">Remaining<br>Allowed<br>Absences</div>
    <div class="rnum" style="color:<?= $remaining <= 2 ? 'var(--red)' : 'var(--text-dark)' ?>;">
      <?= $remaining ?>
    </div>
  </div>
</div>

<?php if (!empty($log)): ?>
<div class="attend-wrap" style="margin-top:4px;">
  <table class="attend-table">
    <thead><tr><th>Date</th><th class="date-col">Day</th><th class="date-col">Status</th></tr></thead>
    <tbody>
      <?php foreach ($log as $l): ?>
      <tr>
        <td><?= date('M j, Y', strtotime($l['date'])) ?></td>
        <td style="color:var(--text-light);font-size:0.82rem;"><?= date('l', strtotime($l['date'])) ?></td>
        <td class="attend-cell">
          <?php if ($l['status'] === 'present'): ?>
            <span class="status-dot status-present">P</span>
          <?php elseif ($l['status'] === 'absent'): ?>
            <span class="status-dot status-absent">A</span>
          <?php else: ?>
            <span class="status-dot status-late">L</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php elseif ($selectedId): ?>
<div style="background:var(--cream);border-radius:var(--radius-sm);padding:24px;text-align:center;color:var(--text-light);">
  No attendance records found for this course yet.
</div>
<?php endif; ?>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
