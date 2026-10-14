<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('student');

$pdo  = getDB();
$user = currentUser();
$sid  = (int)($user['student_db_id'] ?? 0);

$student = $pdo->prepare(
    "SELECT s.student_number, s.program, s.section, s.year_level, u.full_name, u.email
     FROM students s JOIN users u ON u.id = s.user_id WHERE s.user_id = ? LIMIT 1"
);
$student->execute([$user['id']]);
$student = $student->fetch() ?: [];

$grades = $pdo->prepare(
    "SELECT c.course_code, c.title, c.schedule,
            u2.full_name AS teacher,
            g.prelim, g.midterm, g.finals, g.final_grade, g.remarks, g.updated_at
     FROM grades g
     JOIN courses c ON c.id = g.course_id
     JOIN users u2  ON u2.id = c.teacher_id
     WHERE g.student_id = ?
     ORDER BY c.course_code"
);
$grades->execute([$sid]);
$gradeRows = $grades->fetchAll();

$gradedCourses = array_filter($gradeRows, fn($g) => $g['final_grade'] !== null && $g['final_grade'] > 0);
$overallAvg = count($gradedCourses)
    ? round(array_sum(array_column($gradedCourses, 'final_grade')) / count($gradedCourses), 2)
    : 0;
$passedCount = count(array_filter($gradeRows, fn($g) => $g['remarks'] === 'PASSED'));

$navItems = getNavItems('student', 'My Grades');
$pageTitle  = 'My Grades — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Student'];

include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">My Grades</div>

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
    </div>
  </div>
  <div style="margin-left:auto;text-align:right;">
    <div style="font-family:'Caveat',cursive;font-size:2rem;font-weight:700;color:var(--green-dark);"><?= number_format($overallAvg,2) ?></div>
    <div style="font-size:0.72rem;font-weight:800;letter-spacing:1.5px;text-transform:uppercase;color:var(--text-light);">Overall Average</div>
    <div style="font-size:0.8rem;margin-top:4px;">
      <span style="color:#2a9d5c;font-weight:700;"><?= $passedCount ?> Passed</span> /
      <span style="color:var(--red);font-weight:700;"><?= count($gradeRows)-$passedCount ?> Failed</span>
    </div>
  </div>
</div>

<div class="overview-panel">
  <div class="overview-header">
    <span class="ov-title">Grade Sheet — <?= date('Y') ?></span>
    <span class="ov-count"><?= count($gradeRows) ?> Courses</span>
  </div>
  <table class="grades-student-table">
    <thead>
      <tr>
        <th>Course Code</th><th>Title</th><th>Teacher</th>
        <th>Prelim (30%)</th><th>Midterm (30%)</th><th>Finals (40%)</th>
        <th>Final Grade</th><th>Remarks</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($gradeRows as $g): ?>
      <tr>
        <td><?= htmlspecialchars($g['course_code']) ?></td>
        <td><?= htmlspecialchars($g['title']) ?></td>
        <td><?= htmlspecialchars($g['teacher'] ?? '—') ?></td>
        <td><?= $g['prelim']  !== null ? number_format($g['prelim'],  2) : '—' ?></td>
        <td><?= $g['midterm'] !== null ? number_format($g['midterm'], 2) : '—' ?></td>
        <td><?= $g['finals']  !== null ? number_format($g['finals'],  2) : '—' ?></td>
        <td style="font-weight:800;color:var(--green-dark);"><?= $g['final_grade'] !== null ? number_format($g['final_grade'], 2) : '—' ?></td>
        <td style="font-weight:700;color:<?= $g['remarks']==='PASSED' ? '#2a9d5c' : ($g['remarks']==='FAILED' ? 'var(--red)' : 'var(--text-light)') ?>;"><?= htmlspecialchars($g['remarks']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($gradeRows)): ?>
      <tr><td colspan="8" style="text-align:center;color:var(--text-light);padding:20px;">No grades available yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <div style="padding:14px 16px;background:var(--cream-dark);font-size:0.78rem;color:var(--text-mid);text-align:right;">
    Formula: (Prelim × 30%) + (Midterm × 30%) + (Finals × 40%) &nbsp;|&nbsp; Passing mark: 75.00
  </div>
  <div style="padding:12px 16px;" class="clearfix">
    <a href="<?= url('student/dashboard.php') ?>" class="btn-go-back"><- Back to Dashboard</a>
  </div>
</div>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
