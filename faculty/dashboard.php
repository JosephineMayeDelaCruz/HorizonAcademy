<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('faculty');

$pdo  = getDB();
$user = currentUser();

// Summary stats for this faculty
$totalCourses = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE teacher_id=?");
$totalCourses->execute([$user['id']]);
$totalCourses = (int)$totalCourses->fetchColumn();

$totalStudents = $pdo->prepare(
    "SELECT COUNT(DISTINCT g.student_id) FROM grades g
     JOIN courses c ON c.id = g.course_id WHERE c.teacher_id=?"
);
$totalStudents->execute([$user['id']]);
$totalStudents = (int)$totalStudents->fetchColumn();

$totalAttendance = $pdo->prepare(
    "SELECT COUNT(*) FROM attendance a
     JOIN courses c ON c.id = a.course_id WHERE c.teacher_id=?"
);
$totalAttendance->execute([$user['id']]);
$totalAttendance = (int)$totalAttendance->fetchColumn();

// My courses with enrolled count
$courses = $pdo->prepare(
    "SELECT c.id, c.course_code, c.title, c.section, c.schedule,
            COUNT(DISTINCT g.student_id) AS enrolled,
            SUM(CASE WHEN g.remarks='PASSED' THEN 1 ELSE 0 END) AS passed
     FROM courses c
     LEFT JOIN grades g ON g.course_id = c.id
     WHERE c.teacher_id = ?
     GROUP BY c.id ORDER BY c.course_code"
);
$courses->execute([$user['id']]);
$courseRows = $courses->fetchAll();

$navItems = getNavItems('faculty', 'Dashboard');
$pageTitle  = 'Faculty Dashboard — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Faculty'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Faculty Portal — <?= htmlspecialchars($user['full_name']) ?></div>

<div class="stats-row">
  <div class="stat-card">
    <div class="stat-left"><div class="stat-label">My Courses</div><div class="stat-num"><?= $totalCourses ?></div>
    </div>
    <span style="font-size:2rem;opacity:0.6;">📚</span>
  </div>
  <div class="stat-card">
    <div class="stat-left"><div class="stat-label">Total Students</div><div class="stat-num"><?= $totalStudents ?></div>
    </div>
    <span style="font-size:2rem;opacity:0.6;">👨‍🎓</span>
  </div>
  <div class="stat-card">
    <div class="stat-left"><div class="stat-label">Attendance Records</div><div class="stat-num"><?= number_format($totalAttendance) ?></div>
    </div>
    <span style="font-size:2rem;opacity:0.6;">📅</span>
  </div>
</div>

<div class="two-col">
  <div class="panel">
    <div class="panel-header">My Assigned Courses</div>
    <table class="notif-table">
      <thead><tr><td>Course</td><td>Section</td><td>Schedule</td><td>Enrolled</td></tr></thead>
      <tbody>
        <?php foreach ($courseRows as $c): ?>
        <tr>
          <td><strong><?= htmlspecialchars($c['course_code']) ?></strong> — <?= htmlspecialchars($c['title']) ?></td>
          <td><?= htmlspecialchars($c['section']) ?></td>
          <td><?= htmlspecialchars($c['schedule']) ?></td>
          <td><?= $c['enrolled'] ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($courseRows)): ?>
        <tr><td colspan="4" style="text-align:center;color:var(--text-light);">No courses assigned yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="panel">
    <div class="assign-form">
      <h3>Quick Actions</h3>
      <a href="<?= url('faculty/attendance.php') ?>" class="btn-submit" style="text-decoration:none;display:block;text-align:center;margin-bottom:10px;">Take Attendance</a>
      <a href="<?= url('faculty/grades.php') ?>"     class="btn-submit" style="text-decoration:none;display:block;text-align:center;margin-bottom:10px;background:var(--teal);">Manage Grades</a>
      <a href="<?= url('faculty/reports.php') ?>"    class="btn-submit" style="text-decoration:none;display:block;text-align:center;background:#8a6a00;">View Reports</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
