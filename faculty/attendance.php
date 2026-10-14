<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('faculty');

$pdo  = getDB();
$user = currentUser();

// My courses (teacher_id = user id directly since we merged tables)
$myCoursesStmt = $pdo->prepare(
    "SELECT id, course_code, title, section, schedule, max_lessons
     FROM courses WHERE teacher_id = ? ORDER BY course_code"
);
$myCoursesStmt->execute([$user['id']]);
$myCourses = $myCoursesStmt->fetchAll();
$myCourseIds = array_map(static fn($c) => (int)$c['id'], $myCourses);

$selectedCourseId = (int)($_GET['course_id'] ?? ($myCourses[0]['id'] ?? 0));
if ($selectedCourseId && !in_array($selectedCourseId, $myCourseIds, true)) {
    $selectedCourseId = (int)($myCourses[0]['id'] ?? 0);
}
$selectedDate     = trim($_GET['date'] ?? date('Y-m-d'));

// Save attendance
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postCourse = (int)($_POST['course_id'] ?? 0);
    $postDate   = trim($_POST['date'] ?? '');
    $statuses   = $_POST['status'] ?? [];

    // Only allow attendance updates for courses owned by the current faculty account.
    $isOwnedCourse = in_array($postCourse, $myCourseIds, true);
    $isValidDate = (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $postDate);

    if ($isOwnedCourse && $isValidDate && !empty($statuses)) {
        $upsert = $pdo->prepare(
            "INSERT INTO attendance (student_id, course_id, date, status, recorded_by)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), recorded_by = VALUES(recorded_by)"
        );
        foreach ($statuses as $sid => $status) {
            if (in_array($status, ['present','absent','late'], true)) {
                $upsert->execute([(int)$sid, $postCourse, $postDate, $status, $user['id']]);
            }
        }
        redirectWith('/faculty/attendance.php?course_id=' . $postCourse . '&date=' . urlencode($postDate), 'Attendance saved for ' . $postDate . '!');
    }

    if (!$isOwnedCourse) {
        redirectWith('/faculty/attendance.php', 'Invalid course selection.', 'error');
    }
    if (!$isValidDate) {
        redirectWith('/faculty/attendance.php?course_id=' . $postCourse, 'Invalid date format.', 'error');
    }

    $selectedCourseId = $isOwnedCourse ? $postCourse : $selectedCourseId;
    $selectedDate     = $isValidDate ? $postDate : $selectedDate;
}

// Load students enrolled in this course (via grades table as enrollment bridge)
$students = [];
if ($selectedCourseId) {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT s.id, u.full_name,
                COALESCE(a.status, 'present') AS status
         FROM students s
         JOIN users u    ON u.id = s.user_id
         JOIN courses c  ON c.id = ?
         LEFT JOIN attendance a ON a.student_id = s.id
                                AND a.course_id  = ?
                                AND a.date        = ?
         WHERE c.teacher_id = ?
           AND (
               EXISTS (
                   SELECT 1
                   FROM grades g
                   WHERE g.student_id = s.id
                     AND g.course_id = c.id
               )
               OR EXISTS (
                   SELECT 1
                   FROM attendance a2
                   WHERE a2.student_id = s.id
                     AND a2.course_id = c.id
               )
           )
         ORDER BY u.full_name"
    );
    $stmt->execute([$selectedCourseId, $selectedCourseId, $selectedDate, $user['id']]);
    $students = $stmt->fetchAll();
}

// Attendance summary for selected course
$summary = ['present'=>0,'absent'=>0,'late'=>0,'total'=>0];
if ($selectedCourseId) {
    $s = $pdo->prepare(
        "SELECT a.status, COUNT(*) AS cnt
         FROM attendance a
         JOIN courses c ON c.id = a.course_id
         WHERE a.course_id = ?
           AND a.date = ?
           AND c.teacher_id = ?
         GROUP BY a.status"
    );
    $s->execute([$selectedCourseId, $selectedDate, $user['id']]);
    foreach ($s->fetchAll() as $r) {
        $summary[$r['status']] = (int)$r['cnt'];
        $summary['total'] += (int)$r['cnt'];
    }
}

$currentCourse = array_values(array_filter($myCourses, fn($c) => $c['id'] === $selectedCourseId));
$currentCourse = $currentCourse[0] ?? null;

$navItems = getNavItems('faculty', 'Attendance');
$pageTitle  = 'Attendance — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Faculty'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Take Attendance</div>

<!-- Course + date selector -->
<form method="GET" class="courses-row mb-16" style="gap:12px;flex-wrap:wrap;">
  <select name="course_id" class="filter-select" style="padding:10px 32px 10px 12px;" onchange="this.form.submit()">
    <?php foreach ($myCourses as $c): ?>
      <option value="<?= $c['id'] ?>" <?= $c['id'] === $selectedCourseId ? 'selected' : '' ?>>
        <?= htmlspecialchars($c['course_code'] . ' — ' . $c['title'] . ' [' . $c['section'] . ']') ?>
      </option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>"
         class="filter-select" style="padding:10px 12px;" onchange="this.form.submit()">
</form>

<?php if ($currentCourse): ?>
<div style="display:flex;gap:12px;margin-bottom:14px;flex-wrap:wrap;">
  <div class="attend-stat-card" style="flex:0 0 auto;padding:12px 20px;flex-direction:row;gap:10px;">
    <span class="status-dot status-present">P</span>
    <span><span class="asnum" style="font-size:1.4rem;"><?= $summary['present'] ?></span><span class="aslabel"> Present</span></span>
  </div>
  <div class="attend-stat-card" style="flex:0 0 auto;padding:12px 20px;flex-direction:row;gap:10px;">
    <span class="status-dot status-absent">A</span>
    <span><span class="asnum" style="font-size:1.4rem;"><?= $summary['absent'] ?></span><span class="aslabel"> Absent</span></span>
  </div>
  <div class="attend-stat-card" style="flex:0 0 auto;padding:12px 20px;flex-direction:row;gap:10px;">
    <span class="status-dot status-late">L</span>
    <span><span class="asnum" style="font-size:1.4rem;"><?= $summary['late'] ?></span><span class="aslabel"> Late</span></span>
  </div>
  <div style="flex:1;display:flex;align-items:center;justify-content:flex-end;gap:16px;">
    <span class="attend-info"><?= htmlspecialchars($currentCourse['course_code']) ?> | <?= htmlspecialchars($currentCourse['section']) ?> | <?= htmlspecialchars($selectedDate) ?></span>
    <button type="button" class="btn-add" onclick="markAll('present')">All Present</button>
    <button type="button" class="btn-add" style="background:var(--teal);" onclick="markAll('absent')">All Absent</button>
  </div>
</div>
<?php endif; ?>

<form method="POST" id="attend-form">
  <input type="hidden" name="course_id" value="<?= $selectedCourseId ?>">
  <input type="hidden" name="date"      value="<?= htmlspecialchars($selectedDate) ?>">

  <div class="attend-wrap">
    <table class="attend-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Student Name</th>
          <th class="date-col">Present</th>
          <th class="date-col">Absent</th>
          <th class="date-col">Late</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($students as $i => $s): ?>
        <tr>
          <td style="color:var(--text-light);font-size:0.8rem;"><?= $i+1 ?></td>
          <td><?= htmlspecialchars($s['full_name']) ?></td>
          <?php foreach (['present','absent','late'] as $st): ?>
          <td class="attend-cell">
            <label style="cursor:pointer;">
              <input type="radio" name="status[<?= $s['id'] ?>]" value="<?= $st ?>"
                     class="radio-<?= $st ?>"
                     <?= $s['status'] === $st ? 'checked' : '' ?>>
              <span class="status-dot status-<?= $st ?>"><?= $st === 'present' ? 'P' : ($st === 'absent' ? 'A' : 'L') ?></span>
            </label>
          </td>
          <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($students)): ?>
        <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--text-light);">No students enrolled. Use Admin -> Courses -> Enroll Section first.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if (!empty($students)): ?>
  <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;">
    <div class="legend">
      <div class="legend-item"><span class="status-dot status-present">P</span> Present</div>
      <div class="legend-item"><span class="status-dot status-absent">A</span> Absent</div>
      <div class="legend-item"><span class="status-dot status-late">L</span> Late</div>
    </div>
    <button type="submit" class="btn-submit" style="width:auto;padding:11px 32px;">Save Attendance</button>
  </div>
  <?php endif; ?>
</form>

<script>
function markAll(status) {
  document.querySelectorAll('.radio-' + status).forEach(r => r.checked = true);
}
</script>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
