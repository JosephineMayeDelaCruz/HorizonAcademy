<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('faculty');

$pdo  = getDB();
$user = currentUser();

$myCoursesStmt = $pdo->prepare(
    "SELECT id, course_code, title, section FROM courses WHERE teacher_id = ? ORDER BY course_code"
);
$myCoursesStmt->execute([$user['id']]);
$myCourses = $myCoursesStmt->fetchAll();

$selectedCourseId = (int)($_GET['course_id'] ?? ($myCourses[0]['id'] ?? 0));

// Save grades
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postCourse  = (int)($_POST['course_id'] ?? 0);
    $gradesInput = $_POST['grades'] ?? [];

    if ($postCourse && !empty($gradesInput)) {
        $upsert = $pdo->prepare(
            "INSERT INTO grades (student_id, course_id, prelim, midterm, finals, submitted_by)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               prelim       = VALUES(prelim),
               midterm      = VALUES(midterm),
               finals       = VALUES(finals),
               submitted_by = VALUES(submitted_by)"
        );
        foreach ($gradesInput as $sid => $g) {
            $prelim  = min(100, max(0, (float)($g['prelim']  ?? 0)));
            $midterm = min(100, max(0, (float)($g['midterm'] ?? 0)));
            $finals  = min(100, max(0, (float)($g['finals']  ?? 0)));
            $upsert->execute([(int)$sid, $postCourse, $prelim, $midterm, $finals, $user['id']]);
        }
        redirectWith('/faculty/grades.php?course_id=' . $postCourse, 'Grades submitted successfully!');
    }
    $selectedCourseId = $postCourse;
}

// Load students and grades
$students = [];
if ($selectedCourseId) {
    $stmt = $pdo->prepare(
        "SELECT s.id, u.full_name, s.student_number,
                COALESCE(g.prelim,   0) AS prelim,
                COALESCE(g.midterm,  0) AS midterm,
                COALESCE(g.finals,   0) AS finals,
                COALESCE(g.final_grade, 0) AS final_grade,
                COALESCE(g.remarks, '—') AS remarks
         FROM grades g
         JOIN students s ON s.id = g.student_id
         JOIN users u    ON u.id = s.user_id
         WHERE g.course_id = ?
         ORDER BY u.full_name"
    );
    $stmt->execute([$selectedCourseId]);
    $students = $stmt->fetchAll();
}

$currentCourse = array_values(array_filter($myCourses, fn($c) => $c['id'] === $selectedCourseId));
$currentCourse = $currentCourse[0] ?? null;

// Class stats
$passed = count(array_filter($students, fn($s) => $s['remarks'] === 'PASSED'));
$failed = count($students) - $passed;
$avg    = count($students) ? round(array_sum(array_column($students,'final_grade')) / count($students), 2) : 0;

$navItems = getNavItems('faculty', 'Grades');
$pageTitle  = 'Grades — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Faculty'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="courses-row mb-16" style="align-items:center;">
  <div class="content-title" style="margin-bottom:0">Grades Management</div>
  <form method="GET">
    <select name="course_id" class="filter-select" style="padding:10px 32px 10px 12px;" onchange="this.form.submit()">
      <?php foreach ($myCourses as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $c['id'] === $selectedCourseId ? 'selected' : '' ?>>
          <?= htmlspecialchars($c['course_code'] . ' — ' . $c['title'] . ' [' . $c['section'] . ']') ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if (!empty($students)): ?>
<!-- Class stats row -->
<div class="stats-row" style="margin-bottom:16px;">
  <div class="stat-card">
    <div class="stat-left"><div class="stat-label">Total Students</div><div class="stat-num"><?= count($students) ?></div>
    </div>
    <span style="font-size:2rem;opacity:0.6;">👨‍🎓</span>
  </div>
  <div class="stat-card">
    <div class="stat-left"><div class="stat-label">Passed</div><div class="stat-num" style="color:#2a9d5c;"><?= $passed ?></div>
    </div>
    <span style="font-size:2rem;opacity:0.6;">✅</span>
  </div>
  <div class="stat-card">
    <div class="stat-left"><div class="stat-label">Failed</div><div class="stat-num" style="color:var(--red);"><?= $failed ?></div>
    </div>
    <span style="font-size:2rem;opacity:0.6;">❌</span>
  </div>
  <div class="stat-card">
    <div class="stat-left"><div class="stat-label">Class Average</div><div class="stat-num"><?= number_format($avg, 2) ?></div>
    </div>
    <span style="font-size:2rem;opacity:0.6;">📊</span>
  </div>
</div>
<?php endif; ?>

<form method="POST">
  <input type="hidden" name="course_id" value="<?= $selectedCourseId ?>">

  <div class="grades-wrap">
    <table class="grades-table">
      <thead>
        <tr>
          <th>#</th>
          <th style="text-align:left;">Student Name</th>
          <th>Prelim (30%)</th>
          <th>Midterm (30%)</th>
          <th>Finals (40%)</th>
          <th>Final Grade</th>
          <th>Remarks</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $i => $s): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td style="text-align:left;"><?= htmlspecialchars($s['full_name']) ?><br><small style="color:var(--text-light);"><?= htmlspecialchars($s['student_number']) ?></small></td>
          <td><input class="grade-input" type="number" name="grades[<?= $s['id'] ?>][prelim]"
               value="<?= $s['prelim'] ?>" min="0" max="100" step="0.25"></td>
          <td><input class="grade-input" type="number" name="grades[<?= $s['id'] ?>][midterm]"
               value="<?= $s['midterm'] ?>" min="0" max="100" step="0.25"></td>
          <td><input class="grade-input" type="number" name="grades[<?= $s['id'] ?>][finals]"
               value="<?= $s['finals'] ?>" min="0" max="100" step="0.25"></td>
          <td style="font-weight:800;color:var(--green-dark);"><?= number_format($s['final_grade'], 2) ?></td>
          <td>
            <span style="font-weight:700;color:<?= $s['remarks']==='PASSED' ? '#2a9d5c' : 'var(--red)' ?>;">
              <?= htmlspecialchars($s['remarks']) ?>
            </span>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($students)): ?>
        <tr><td colspan="7" style="text-align:center;color:var(--text-light);padding:24px;">No students enrolled in this course yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="grades-footer">
    <div class="course-info">
      <?= $currentCourse ? htmlspecialchars($currentCourse['course_code'] . ' — ' . $currentCourse['section']) : '' ?>
      &nbsp;|&nbsp; Formula: (Prelim×30%) + (Midterm×30%) + (Finals×40%) | Passing: 75.00
    </div>
    <?php if (!empty($students)): ?>
    <button type="submit" class="btn-submit" style="width:auto;padding:11px 32px;">Submit Grades</button>
    <?php endif; ?>
  </div>
</form>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
