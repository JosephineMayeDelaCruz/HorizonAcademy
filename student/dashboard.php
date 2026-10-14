<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('student');

$pdo  = getDB();
$user = currentUser();

$stmt = $pdo->prepare(
    "SELECT s.id, s.student_number, s.program, s.section, s.year_level, u.full_name, u.email
     FROM students s
     INNER JOIN users u ON u.id = s.user_id
     WHERE s.user_id = ?"
);
$stmt->execute([$user['id']]);
$student = $stmt->fetch() ?: [];
$student_id = $student['id'] ?? 0;

$stmt = $pdo->prepare(
    "SELECT c.id, c.course_code, c.title, c.schedule, u2.full_name AS teacher_name,
            g.final_grade, g.remarks,
            COUNT(DISTINCT a.date) AS total_sessions,
            SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN a.status='absent'  THEN 1 ELSE 0 END) AS absent_count,
            SUM(CASE WHEN a.status='late'    THEN 1 ELSE 0 END) AS late_count
     FROM grades g
     INNER JOIN courses c  ON c.id = g.course_id
     INNER JOIN users u2   ON u2.id = c.teacher_id
     LEFT JOIN attendance a ON a.course_id = c.id AND a.student_id = g.student_id
     WHERE g.student_id = ?
     GROUP BY c.id, g.id
     ORDER BY c.course_code"
);
$stmt->execute([$student_id]);
$courses = $stmt->fetchAll();

$warnings = [];
foreach ($courses as $course) {
    if ($course['total_sessions'] > 0) {
        $attendance_rate = ($course['present_count'] / $course['total_sessions']) * 100;
        if ($attendance_rate < 80) {
            $warnings[] = [
                'course_code' => $course['course_code'],
                'rate' => round($attendance_rate, 1),
                'present' => $course['present_count'],
                'total' => $course['total_sessions']
            ];
        }
    }
}

$navItems = getNavItems('student', 'Dashboard');
$pageTitle  = 'Student Dashboard — Horizon Academy';
$topbarUser = ['name' => $student['full_name'] ?? 'Student', 'role' => 'Student'];

include __DIR__ . '/../partials/layout_start.php';
?>
<div class="content-title">My Dashboard</div>

<?php foreach ($warnings as $w): ?>
<div class="alert-warn">
  <span class="warn-icon">⚠️</span>
  <div>
    <div class="warn-title">Low Attendance Warning — <?= htmlspecialchars($w['course_code']) ?></div>
    <div class="warn-body">
      Your attendance is <?= $w['total'] ? round($w['present']/$w['total']*100,1) : 0 ?>%
      (<?= $w['present'] ?>/<?= $w['total'] ?> sessions).
      A minimum of 80% is required. Please consult your instructor.
    </div>
  </div>
</div>
<?php endforeach; ?>

<div class="student-profile-card">
  <div class="student-avatar">
    🎓
  </div>
  <div>
    <div class="student-name"><?= htmlspecialchars($student['full_name'] ?? '') ?></div>
    <div class="student-meta">
      <span><?= htmlspecialchars($student['student_number'] ?? '') ?></span>
      <span><?= htmlspecialchars($student['program'] ?? '') ?> — <?= htmlspecialchars($student['section'] ?? '') ?></span>
      <span>Year <?= $student['year_level'] ?? '' ?></span>
      <span><?= htmlspecialchars($student['email'] ?? '') ?></span>
    </div>
  </div>
</div>

<div class="overview-panel">
  <div class="overview-header">
    <span class="ov-title">My Courses — Attendance &amp; Grade Overview</span>
    <span class="ov-count"><?= count($courses) ?> Courses</span>
  </div>
  <table class="overview-table">
    <thead>
      <tr><th>Course</th><th>Teacher</th><th>Schedule</th><th>Sessions</th><th>Present</th><th>Absent</th><th>Late</th><th>Final Grade</th><th>Remarks</th></tr>
    </thead>
    <tbody>
      <?php foreach ($courses as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['course_code']) ?></td>
        <td><?= htmlspecialchars($c['teacher_name'] ?? '—') ?></td>
        <td><?= htmlspecialchars($c['schedule'] ?? '—') ?></td>
        <td><?= $c['total_sessions'] ?></td>
        <td><?= $c['present_count'] ?></td>
        <td style="color:var(--red);font-weight:600;"><?= $c['absent_count'] ?></td>
        <td style="color:var(--teal);font-weight:600;"><?= $c['late_count'] ?></td>
        <td style="font-weight:800;"><?= number_format($c['final_grade'],2) ?></td>
        <td style="font-weight:700;color:<?= $c['remarks']==='PASSED' ? '#2a9d5c' : ($c['remarks']==='FAILED' ? 'var(--red)' : 'var(--text-light)') ?>;"><?= htmlspecialchars($c['remarks']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($courses)): ?>
      <tr><td colspan="9" style="text-align:center;color:var(--text-light);padding:20px;">No courses enrolled yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <div style="padding:12px 16px;" class="clearfix">
    <a href="<?= url('student/grades.php') ?>" class="btn-view-grades">View Detailed Grades</a>
  </div>
</div>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
