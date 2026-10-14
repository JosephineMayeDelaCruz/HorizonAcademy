<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('admin');

$pdo  = getDB();
$user = currentUser();

// ── Filters ─────────────────────────────────────────────────
// This block reads report filters from the URL to control which dataset is shown.
$filterSection = trim($_GET['section']   ?? '');
$filterCourse  = trim($_GET['course_id'] ?? '');
$reportType    = trim($_GET['report']    ?? 'grades');

// Available sections and courses
// This block loads available sections and courses to populate filter dropdown options.
$sections = $pdo->query(
    "SELECT DISTINCT section FROM students ORDER BY section"
)->fetchAll(PDO::FETCH_COLUMN);

$courses = $pdo->query(
    "SELECT id, course_code, title, section FROM courses ORDER BY course_code"
)->fetchAll();

// ── REPORT: Grade Summary ───────────────────────────────────
// This block builds and runs the grade summary report query with optional section and course filters.
$gradeReport = [];
if ($reportType === 'grades') {
    $sql = "SELECT u.full_name AS student, s.student_number, s.section, s.program,
                   c.course_code, c.title AS course_title,
                   g.prelim, g.midterm, g.finals, g.final_grade, g.remarks
            FROM grades g
            JOIN students s ON s.id = g.student_id
            JOIN users u    ON u.id = s.user_id
            JOIN courses c  ON c.id = g.course_id
            WHERE 1=1";
    $params = [];
    if ($filterSection) {
        $sql    .= " AND s.section = ?";
        $params[] = $filterSection;
    }
    if ($filterCourse) {
        $sql    .= " AND c.id = ?";
        $params[] = $filterCourse;
    }
    $sql .= " ORDER BY s.section, u.full_name, c.course_code";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $gradeReport = $stmt->fetchAll();
}

// ── REPORT: Attendance Summary ──────────────────────────────
// This block builds and runs the attendance summary report query with computed attendance rates.
$attendReport = [];
if ($reportType === 'attendance') {
    $sql = "SELECT u.full_name AS student, s.student_number, s.section,
                   c.course_code, c.title AS course_title,
                   SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) AS present_count,
                   SUM(CASE WHEN a.status='absent'  THEN 1 ELSE 0 END) AS absent_count,
                   SUM(CASE WHEN a.status='late'    THEN 1 ELSE 0 END) AS late_count,
                   COUNT(*) AS total_classes,
                   ROUND(SUM(CASE WHEN a.status='present' THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) AS attendance_rate
            FROM attendance a
            JOIN students s ON s.id = a.student_id
            JOIN users u    ON u.id = s.user_id
            JOIN courses c  ON c.id = a.course_id
            WHERE 1=1";
    $params = [];
    if ($filterSection) {
        $sql    .= " AND s.section = ?";
        $params[] = $filterSection;
    }
    if ($filterCourse) {
        $sql    .= " AND c.id = ?";
        $params[] = $filterCourse;
    }
    $sql .= " GROUP BY s.id, a.course_id ORDER BY s.section, u.full_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $attendReport = $stmt->fetchAll();
}

// ── REPORT: Per-Course Statistics ──────────────────────────
// This block builds and runs per-course aggregate statistics for grade performance.
$courseStats = [];
if ($reportType === 'course_stats') {
    $sql = "SELECT c.course_code, c.title, c.section,
                   fu.full_name AS faculty,
                   COUNT(g.id) AS total_students,
                   ROUND(AVG(g.final_grade), 2) AS avg_grade,
                   MAX(g.final_grade) AS highest,
                   MIN(g.final_grade) AS lowest,
                   SUM(CASE WHEN g.remarks='PASSED' THEN 1 ELSE 0 END) AS passed,
                   SUM(CASE WHEN g.remarks='FAILED' THEN 1 ELSE 0 END) AS failed
            FROM courses c
            JOIN users fu ON fu.id = c.teacher_id
            LEFT JOIN grades g ON g.course_id = c.id
            WHERE 1=1";
    $params = [];
    if ($filterSection) {
        $sql    .= " AND c.section = ?";
        $params[] = $filterSection;
    }
    if ($filterCourse) {
        $sql    .= " AND c.id = ?";
        $params[] = $filterCourse;
    }
    $sql .= " GROUP BY c.id ORDER BY c.course_code";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $courseStats = $stmt->fetchAll();
}

$navItems = getNavItems('admin', 'Reports');
$pageTitle  = 'Reports — Horizon Academy';
$topbarUser = ['name' => $user['full_name'] ?? 'Admin', 'role' => 'Admin'];

include __DIR__ . '/../partials/layout_start.php';
?>

<!-- This block renders report type tabs and common filter controls for all report views. -->
<div class="content-title">Reports &amp; Analytics</div>

<!-- Report Type Tabs -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap;">
  <?php
  $tabs = ['grades' => 'Grade Summary', 'attendance' => 'Attendance Summary', 'course_stats' => 'Course Statistics'];
  foreach ($tabs as $key => $label):
  ?>
  <a href="?report=<?= $key ?>&section=<?= urlencode($filterSection) ?>&course_id=<?= urlencode($filterCourse) ?>"
     class="btn-add"
     style="text-decoration:none;background:<?= $reportType === $key ? 'var(--green-dark)' : 'var(--cream-dark)' ?>;
            color:<?= $reportType === $key ? 'white' : 'var(--text-dark)' ?>;">
    <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<form method="GET" class="mgmt-controls" style="margin-bottom:16px;">
  <input type="hidden" name="report" value="<?= htmlspecialchars($reportType) ?>">
  <select name="section" class="filter-select" onchange="this.form.submit()">
    <option value="">All Sections</option>
    <?php foreach ($sections as $s): ?>
      <option value="<?= htmlspecialchars($s) ?>" <?= $filterSection === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="course_id" class="filter-select" onchange="this.form.submit()">
    <option value="">All Courses</option>
    <?php foreach ($courses as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $filterCourse == $c['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($c['course_code'] . ' — ' . $c['title']) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn-add" style="background:var(--green-dark)">Apply Filter</button>
</form>

<?php if ($reportType === 'grades'): ?>
<!-- Grade Summary Table -->
<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <th>Student</th><th>Stud. No.</th><th>Section</th>
        <th>Course</th><th>Prelim</th><th>Midterm</th><th>Finals</th>
        <th>Final Grade</th><th>Remarks</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($gradeReport as $g): ?>
      <tr>
        <td><?= htmlspecialchars($g['student']) ?></td>
        <td><?= htmlspecialchars($g['student_number']) ?></td>
        <td><?= htmlspecialchars($g['section']) ?></td>
        <td><?= htmlspecialchars($g['course_code']) ?></td>
        <td><?= number_format($g['prelim'],   2) ?></td>
        <td><?= number_format($g['midterm'],  2) ?></td>
        <td><?= number_format($g['finals'],   2) ?></td>
        <td style="font-weight:700;"><?= number_format($g['final_grade'], 2) ?></td>
        <td>
          <span style="font-weight:700;color:<?= $g['remarks']==='PASSED' ? '#2a9d5c' : 'var(--red)' ?>">
            <?= $g['remarks'] ?>
          </span>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($gradeReport)): ?>
      <tr><td colspan="9" style="text-align:center;color:var(--text-light);padding:20px;">No grade records found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<div style="padding:12px 0;font-size:0.82rem;color:var(--cream);font-weight:700;">
  Total records: <?= count($gradeReport) ?>
</div>

<?php elseif ($reportType === 'attendance'): ?>
<!-- Attendance Summary Table -->
<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <th>Student</th><th>Stud. No.</th><th>Section</th><th>Course</th>
        <th>Total</th><th>Present</th><th>Absent</th><th>Late</th><th>Rate</th><th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($attendReport as $a): ?>
      <?php $warn = $a['attendance_rate'] < 80; ?>
      <tr>
        <td><?= htmlspecialchars($a['student']) ?></td>
        <td><?= htmlspecialchars($a['student_number']) ?></td>
        <td><?= htmlspecialchars($a['section']) ?></td>
        <td><?= htmlspecialchars($a['course_code']) ?></td>
        <td><?= $a['total_classes'] ?></td>
        <td style="color:#2a9d5c;font-weight:700;"><?= $a['present_count'] ?></td>
        <td style="color:var(--red);font-weight:700;"><?= $a['absent_count'] ?></td>
        <td style="color:var(--teal);font-weight:700;"><?= $a['late_count'] ?></td>
        <td style="font-weight:700;"><?= $a['attendance_rate'] ?>%</td>
        <td>
          <span style="font-weight:700;color:<?= $warn ? 'var(--red)' : '#2a9d5c' ?>">
            <?= $warn ? '! At Risk' : 'OK Good' ?>
          </span>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($attendReport)): ?>
      <tr><td colspan="10" style="text-align:center;color:var(--text-light);padding:20px;">No attendance records found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<div style="padding:12px 0;font-size:0.82rem;color:var(--cream);font-weight:700;">
  Total records: <?= count($attendReport) ?>
  &nbsp;|&nbsp;
  At-risk students: <?= count(array_filter($attendReport, fn($a) => $a['attendance_rate'] < 80)) ?>
</div>

<?php elseif ($reportType === 'course_stats'): ?>
<!-- Course Statistics Table -->
<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr>
        <th>Course Code</th><th>Title</th><th>Section</th><th>Faculty</th>
        <th>Enrolled</th><th>Average</th><th>Highest</th><th>Lowest</th><th>Passed</th><th>Failed</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($courseStats as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['course_code']) ?></td>
        <td><?= htmlspecialchars($c['title']) ?></td>
        <td><?= htmlspecialchars($c['section']) ?></td>
        <td><?= htmlspecialchars($c['faculty']) ?></td>
        <td><?= $c['total_students'] ?></td>
        <td style="font-weight:700;"><?= $c['avg_grade'] !== null ? number_format($c['avg_grade'],2) : '—' ?></td>
        <td style="color:#2a9d5c;font-weight:700;"><?= $c['highest'] !== null ? number_format($c['highest'],2) : '—' ?></td>
        <td style="color:var(--red);font-weight:700;"><?= $c['lowest']  !== null ? number_format($c['lowest'],2)  : '—' ?></td>
        <td style="color:#2a9d5c;font-weight:700;"><?= $c['passed'] ?></td>
        <td style="color:var(--red);font-weight:700;"><?= $c['failed'] ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($courseStats)): ?>
      <tr><td colspan="10" style="text-align:center;color:var(--text-light);padding:20px;">No course data found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
