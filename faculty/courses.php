<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('faculty');

$pdo  = getDB();
$user = currentUser();

// This block reads the optional search term and fetches this faculty member's course list with summary counts.
$search = trim($_GET['search'] ?? '');
$sql    = "SELECT c.id, c.course_code, c.title, c.section, c.schedule, c.max_lessons,
                  c.school_year, c.semester,
                  COUNT(DISTINCT g.student_id) AS enrolled,
                  SUM(CASE WHEN g.remarks='PASSED' THEN 1 ELSE 0 END) AS passed
           FROM courses c
           LEFT JOIN grades g ON g.course_id = c.id
           WHERE c.teacher_id = ?";
$params = [$user['id']];
if ($search) {
    $sql    .= " AND (c.title LIKE ? OR c.course_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " GROUP BY c.id ORDER BY c.course_code";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

$navItems = getNavItems('faculty', 'Courses');
$pageTitle  = 'My Courses — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Faculty'];
include __DIR__ . '/../partials/layout_start.php';
?>
<!-- This block renders the faculty course table and quick links to attendance and grades pages. -->
<div class="courses-row" style="align-items:center;margin-bottom:16px;">
  <div class="content-title" style="margin-bottom:0">My Courses</div>
  <form method="GET" style="flex:1;max-width:360px;">
    <div class="search-box" style="background:rgba(255,255,255,0.12);">
      <span style="font-size:1.2rem;">🔍</span>
      <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
             placeholder="Search course…" style="color:var(--cream);background:none;">
    </div>
  </form>
</div>
<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr><th>Code</th><th>Title</th><th>Section</th><th>Schedule</th><th>Enrolled</th><th>Passed</th><th>SY/Sem</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($courses as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['course_code']) ?></td>
        <td><?= htmlspecialchars($c['title']) ?></td>
        <td><?= htmlspecialchars($c['section']) ?></td>
        <td><?= htmlspecialchars($c['schedule']) ?></td>
        <td><?= $c['enrolled'] ?></td>
        <td style="color:#2a9d5c;font-weight:700;"><?= $c['passed'] ?></td>
        <td><?= htmlspecialchars($c['school_year']) ?>/<?= $c['semester'] ?></td>
        <td>
          <div class="action-stack">
            <a href="<?= url('faculty/attendance.php') ?>?course_id=<?= $c['id'] ?>" class="btn-edit action-btn" style="text-decoration:none;">Attendance</a>
            <a href="<?= url('faculty/grades.php') ?>?course_id=<?= $c['id'] ?>" class="btn-edit action-btn action-btn-teal" style="text-decoration:none;">Grades</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($courses)): ?>
      <tr><td colspan="8" style="text-align:center;color:var(--text-light);padding:20px;">No courses assigned yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php include __DIR__ . '/../partials/layout_end.php'; ?>
