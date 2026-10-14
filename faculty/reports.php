<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('faculty');

$pdo  = getDB();
$user = currentUser();

// This block fetches courses handled by the current faculty member for report selection.
$myCoursesStmt = $pdo->prepare(
    "SELECT id, course_code, title, section FROM courses WHERE teacher_id = ? ORDER BY course_code"
);
$myCoursesStmt->execute([$user['id']]);
$myCourses = $myCoursesStmt->fetchAll();

$selectedCourseId = (int)($_GET['course_id'] ?? ($myCourses[0]['id'] ?? 0));
$reportType = trim($_GET['report'] ?? 'grades');

// Grade report
// This block builds the grade report rows for the selected course.
$gradeReport = [];
if ($selectedCourseId) {
    $stmt = $pdo->prepare(
        "SELECT u.full_name, s.student_number, s.section,
                COALESCE(g.prelim, 0) AS prelim,
                COALESCE(g.midterm, 0) AS midterm,
                COALESCE(g.finals, 0) AS finals,
                COALESCE(g.final_grade, 0) AS final_grade,
                COALESCE(g.remarks, 'NO GRADE') AS remarks
         FROM grades g
         JOIN students s ON s.id = g.student_id
         JOIN users u    ON u.id = s.user_id
         WHERE g.course_id = ?
         ORDER BY g.final_grade DESC"
    );
    $stmt->execute([$selectedCourseId]);
    $gradeReport = $stmt->fetchAll();
}

// Attendance report
// This block builds attendance report rows with attendance-rate percentages for the selected course.
$attendReport = [];
if ($selectedCourseId) {
    $stmt = $pdo->prepare(
        "SELECT u.full_name, s.student_number,
                SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) AS present_count,
                SUM(CASE WHEN a.status='absent'  THEN 1 ELSE 0 END) AS absent_count,
                SUM(CASE WHEN a.status='late'    THEN 1 ELSE 0 END) AS late_count,
                COUNT(a.id) AS total_sessions,
                ROUND(SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) / NULLIF(COUNT(a.id),0) * 100, 1) AS attendance_rate
         FROM grades g
         JOIN students s ON s.id = g.student_id
         JOIN users u    ON u.id = s.user_id
         LEFT JOIN attendance a ON a.student_id = g.student_id AND a.course_id = g.course_id
         WHERE g.course_id = ?
         GROUP BY s.id
         ORDER BY attendance_rate ASC"
    );
    $stmt->execute([$selectedCourseId]);
    $attendReport = $stmt->fetchAll();
}

$currentCourse = array_values(array_filter($myCourses, fn($c) => $c['id'] === $selectedCourseId));
$currentCourse = $currentCourse[0] ?? null;

// This block prepares layout navigation and user metadata for the faculty reports page.
$navItems = getNavItems('faculty', 'Reports');
$pageTitle  = 'Reports — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Faculty'];
include __DIR__ . '/../partials/layout_start.php';
?>

<!-- This block renders report controls and conditionally shows either grade or attendance tables. -->
<div class="content-title">Performance Reports</div>

<form method="GET" class="mgmt-controls" style="margin-bottom:16px;">
  <select name="course_id" class="filter-select" style="padding:10px 32px 10px 12px;" onchange="this.form.submit()">
    <?php foreach ($myCourses as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $c['id'] === $selectedCourseId ? 'selected' : '' ?>>
        <?= htmlspecialchars($c['course_code'] . ' — ' . $c['title'] . ' [' . $c['section'] . ']') ?>
      </option>
    <?php endforeach; ?>
  </select>
  <select name="report" class="filter-select" onchange="this.form.submit()">
    <option value="grades"     <?= $reportType==='grades'     ? 'selected' : '' ?>>Grade Report</option>
    <option value="attendance" <?= $reportType==='attendance' ? 'selected' : '' ?>>Attendance Report</option>
  </select>
  <button type="button" class="btn-add" onclick="window.print()">Print Report</button>
</form>

<?php if ($reportType === 'grades'): ?>
<!-- GRADE REPORT -->
<div class="panel" id="printable">
  <div class="panel-header">
    Grade Report — <?= $currentCourse ? htmlspecialchars($currentCourse['course_code'] . ' ' . $currentCourse['title'] . ' [' . $currentCourse['section'] . ']') : '' ?>
  </div>
  <table class="grades-table" style="background:var(--white);">
    <thead>
      <tr>
        <th style="text-align:left;">#</th>
        <th style="text-align:left;">Student Name</th>
        <th style="text-align:left;">Student No.</th>
        <th>Prelim (30%)</th>
        <th>Midterm (30%)</th>
        <th>Finals (40%)</th>
        <th>Final Grade</th>
        <th>Remarks</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($gradeReport as $index => $student): ?>
      <tr>
        <td><?= $index + 1 ?></td>
        <td><?= htmlspecialchars($student['full_name']) ?></td>
        <td><?= htmlspecialchars($student['student_number']) ?></td>
        <td><?= $student['prelim'] ?></td>
        <td><?= $student['midterm'] ?></td>
        <td><?= $student['finals'] ?></td>
        <td><?= $student['final_grade'] ?></td>
        <td><?= htmlspecialchars($student['remarks']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<!-- ATTENDANCE REPORT -->
<div class="panel" id="printable">
  <div class="panel-header">
    Attendance Report — <?= $currentCourse ? htmlspecialchars($currentCourse['course_code'] . ' ' . $currentCourse['title'] . ' [' . $currentCourse['section'] . ']') : '' ?>
  </div>
  <table class="grades-table" style="background:var(--white);">
    <thead>
      <tr>
        <th style="text-align:left;">#</th>
        <th style="text-align:left;">Student Name</th>
        <th style="text-align:left;">Student No.</th>
        <th>Present</th>
        <th>Absent</th>
        <th>Late</th>
        <th>Attendance Rate</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($attendReport as $index => $student): ?>
      <tr>
        <td><?= $index + 1 ?></td>
        <td><?= htmlspecialchars($student['full_name']) ?></td>
        <td><?= htmlspecialchars($student['student_number']) ?></td>
        <td><?= $student['present_count'] ?></td>
        <td><?= $student['absent_count'] ?></td>
        <td><?= $student['late_count'] ?></td>
        <td><?= $student['attendance_rate'] ?>%</td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
