<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('admin');

$pdo  = getDB();
$user = currentUser();

// ── Handle POST actions ─────────────────────────────────────
// This block handles create, update, delete, enroll, and unenroll actions for courses.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $code      = trim($_POST['course_code'] ?? '');
        $title     = trim($_POST['title']       ?? '');
        $section   = trim($_POST['section']     ?? '');
        $schedule  = trim($_POST['schedule']    ?? 'TBA');
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $maxLess   = (int)($_POST['max_lessons'] ?? 54);
        $sy        = trim($_POST['school_year'] ?? '2025-2026');
        $sem       = (int)($_POST['semester']   ?? 2);

        if ($code && $title && $section && $teacherId) {
            try {
                $pdo->prepare(
                    "INSERT INTO courses (course_code, title, section, schedule, teacher_id, max_lessons, school_year, semester)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                )->execute([$code, $title, $section, $schedule, $teacherId, $maxLess, $sy, $sem]);
                redirectWith('/admin/courses.php', 'Course added successfully!');
            } catch (Exception $e) {
                redirectWith('/admin/courses.php', 'Error: ' . $e->getMessage(), 'error');
            }
        } else {
            redirectWith('/admin/courses.php', 'Please fill in all required fields.', 'error');
        }

    } elseif ($action === 'edit') {
        $cid      = (int)($_POST['course_id']  ?? 0);
        $title    = trim($_POST['title']       ?? '');
        $section  = trim($_POST['section']     ?? '');
        $schedule = trim($_POST['schedule']    ?? '');
        $tid      = (int)($_POST['teacher_id'] ?? 0);
        $maxLess  = (int)($_POST['max_lessons'] ?? 54);

        if ($cid && $title && $tid) {
            $pdo->prepare(
                "UPDATE courses SET title=?, section=?, schedule=?, teacher_id=?, max_lessons=? WHERE id=?"
            )->execute([$title, $section, $schedule, $tid, $maxLess, $cid]);
            redirectWith('/admin/courses.php', 'Course updated!');
        }

    } elseif ($action === 'remove') {
        $cid = (int)($_POST['course_id'] ?? 0);
        if ($cid) {
            $pdo->prepare("DELETE FROM courses WHERE id=?")->execute([$cid]);
            redirectWith('/admin/courses.php', 'Course removed.');
        }

    } elseif ($action === 'enroll') {
        // Enroll a student into a course
        $cid = (int)($_POST['course_id']  ?? 0);
        $sid = (int)($_POST['student_id'] ?? 0);
        if ($cid && $sid) {
            // We implement enrollment via an attendance placeholder —
            // a student is "enrolled" when they appear in attendance records for a course.
            // For the 5-table design we track enrollment via a lightweight grades seed insert:
            try {
                $pdo->prepare(
                    "INSERT IGNORE INTO grades (student_id, course_id, prelim, midterm, finals, submitted_by)
                     VALUES (?, ?, 0, 0, 0, ?)"
                )->execute([$sid, $cid, $user['id']]);
                redirectWith('/admin/courses.php?view=' . $cid, 'Student enrolled!');
            } catch (Exception $e) {
                redirectWith('/admin/courses.php?view=' . $cid, 'Error: ' . $e->getMessage(), 'error');
            }
        }

    } elseif ($action === 'unenroll') {
        $cid = (int)($_POST['course_id']  ?? 0);
        $sid = (int)($_POST['student_id'] ?? 0);
        if ($cid && $sid) {
            $pdo->prepare("DELETE FROM grades     WHERE student_id=? AND course_id=?")->execute([$sid, $cid]);
            $pdo->prepare("DELETE FROM attendance WHERE student_id=? AND course_id=?")->execute([$sid, $cid]);
            redirectWith('/admin/courses.php?view=' . $cid, 'Student unenrolled.');
        }
    }
}

// ── Fetch courses ───────────────────────────────────────────
// This block loads the searchable course list together with faculty names and enrollment totals.
$search = trim($_GET['search'] ?? '');
$sql    = "SELECT c.id, c.course_code, c.title, c.section, c.schedule, c.teacher_id,
                  c.max_lessons, c.school_year, c.semester,
                  u.full_name AS teacher_name,
                  COUNT(DISTINCT g.student_id) AS enrolled
           FROM courses c
           JOIN users u ON u.id = c.teacher_id
           LEFT JOIN grades g ON g.course_id = c.id
           WHERE 1=1";
$params = [];
if ($search) {
    $sql    .= " AND (c.course_code LIKE ? OR c.title LIKE ? OR c.section LIKE ?)";
    $params  = ["%$search%", "%$search%", "%$search%"];
}
$sql .= " GROUP BY c.id ORDER BY c.course_code";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$courses = $stmt->fetchAll();

// All faculty for dropdowns
// This block fetches faculty members for the add/edit course assignment dropdowns.
$allFaculty = $pdo->query(
    "SELECT id, full_name FROM users WHERE role='faculty' ORDER BY full_name"
)->fetchAll();

// ── Enrollment view ─────────────────────────────────────────
// This block loads the selected course enrollment view with enrolled and available students.
$viewCourseId  = (int)($_GET['view'] ?? 0);
$viewCourse    = null;
$enrolledStuds = [];
$availableStuds = [];

if ($viewCourseId) {
    $vc = $pdo->prepare("SELECT c.*, u.full_name AS teacher FROM courses c JOIN users u ON u.id=c.teacher_id WHERE c.id=?");
    $vc->execute([$viewCourseId]);
    $viewCourse = $vc->fetch();

    $enrolledStuds = $pdo->prepare(
        "SELECT s.id, s.student_number, u.full_name, s.section, s.program
         FROM grades g
         JOIN students s ON s.id = g.student_id
         JOIN users u    ON u.id = s.user_id
         WHERE g.course_id = ?
         ORDER BY u.full_name"
    );
    $enrolledStuds->execute([$viewCourseId]);
    $enrolledStuds = $enrolledStuds->fetchAll();

    $enrolledIds = array_column($enrolledStuds, 'id');
    $availableStuds = $pdo->query(
        "SELECT s.id, s.student_number, u.full_name, s.section
         FROM students s JOIN users u ON u.id=s.user_id
         ORDER BY u.full_name"
    )->fetchAll();
    $availableStuds = array_filter($availableStuds, fn($s) => !in_array($s['id'], $enrolledIds));
}

$navItems = getNavItems('admin', 'Courses');
$pageTitle  = 'Course Management — Horizon Academy';
$topbarUser = ['name' => $user['full_name'] ?? 'Admin', 'role' => 'Admin'];

include __DIR__ . '/../partials/layout_start.php';
?>

<!-- This block switches between enrollment details and the general course list screen. -->
<div class="content-title">Course Management</div>

<?php if ($viewCourse): ?>
<!-- ── Enrollment View ── -->
<div style="margin-bottom:14px;">
  <a href="/admin/courses.php" class="btn-go-back" style="font-size:0.78rem;padding:8px 18px;border-radius:8px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;background:var(--green-dark);color:white;">
    <- Back to Course List
  </a>
</div>

<div class="panel" style="margin-bottom:16px;">
  <div class="panel-header">
    <?= htmlspecialchars($viewCourse['course_code']) ?> — <?= htmlspecialchars($viewCourse['title']) ?>
    &nbsp;<span style="font-weight:400;opacity:0.75;"><?= htmlspecialchars($viewCourse['section']) ?> | <?= htmlspecialchars($viewCourse['teacher']) ?></span>
  </div>
  <div style="padding:14px 20px;display:flex;gap:20px;align-items:center;flex-wrap:wrap;">
    <form method="POST" style="display:flex;gap:10px;align-items:center;flex:1;min-width:260px;">
      <input type="hidden" name="action" value="enroll">
      <input type="hidden" name="course_id" value="<?= $viewCourseId ?>">
      <select name="student_id" class="filter-select" style="flex:1;padding:10px 12px;" required>
        <option value="">— Select student to enroll —</option>
        <?php foreach ($availableStuds as $s): ?>
          <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name'] . ' (' . $s['student_number'] . ') — ' . $s['section']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn-add">Enroll</button>
    </form>
    <div style="font-size:0.82rem;color:var(--text-mid);font-weight:700;">
      <?= count($enrolledStuds) ?> student(s) enrolled
    </div>
  </div>
</div>

<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Full Name</th><th>Student No.</th><th>Section</th><th>Program</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach ($enrolledStuds as $s): ?>
      <tr>
        <td><?= htmlspecialchars($s['full_name']) ?></td>
        <td><?= htmlspecialchars($s['student_number']) ?></td>
        <td><?= htmlspecialchars($s['section']) ?></td>
        <td><?= htmlspecialchars($s['program']) ?></td>
        <td>
          <form method="POST" style="display:inline"
                onsubmit="return confirm('Unenroll <?= addslashes($s['full_name']) ?>? This removes their grades and attendance for this course.')">
            <input type="hidden" name="action" value="unenroll">
            <input type="hidden" name="course_id"  value="<?= $viewCourseId ?>">
            <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
            <button type="submit" class="btn-remove" style="padding:6px 12px;border-radius:7px;font-size:0.78rem;">Unenroll</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($enrolledStuds)): ?>
      <tr><td colspan="5" style="text-align:center;color:var(--text-light);padding:20px;">No students enrolled in this course yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php else: ?>
<!-- ── Course List ── -->
<!-- This block renders searchable course records and management actions. -->
<form method="GET" class="mgmt-controls">
  <div class="search-box">
    <span style="font-size:1.2rem;">🔍</span>
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by code, title, section…">
  </div>
  <button type="submit" class="btn-add" style="background:var(--green-dark)">Search</button>
  <button type="button" class="btn-add" onclick="document.getElementById('modal-add').style.display='flex'">+ Add Course</button>
</form>

<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr><th>Code</th><th>Title</th><th>Section</th><th>Schedule</th><th>Faculty</th><th>Enrolled</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($courses as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['course_code']) ?></td>
        <td><?= htmlspecialchars($c['title']) ?></td>
        <td><?= htmlspecialchars($c['section']) ?></td>
        <td><?= htmlspecialchars($c['schedule']) ?></td>
        <td><?= htmlspecialchars($c['teacher_name']) ?></td>
        <td><?= $c['enrolled'] ?></td>
        <td>
          <a href="/admin/courses.php?view=<?= $c['id'] ?>" class="btn-edit" style="text-decoration:none;">Enrollments</a>
          <button class="btn-edit"
            onclick="openEditModal(
              <?= $c['id'] ?>,
              '<?= addslashes($c['course_code']) ?>',
              '<?= addslashes($c['title']) ?>',
              '<?= addslashes($c['section']) ?>',
              '<?= addslashes($c['schedule']) ?>',
              <?= $c['teacher_id'] ?? 0 ?>,
              <?= $c['max_lessons'] ?>
            )">Edit</button>
          <form method="POST" style="display:inline"
                onsubmit="return confirm('Remove course <?= addslashes($c['course_code']) ?>? This removes all grades and attendance records for this course.')">
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="course_id" value="<?= $c['id'] ?>">
            <button type="submit" class="btn-remove" style="padding:8px 14px;border-radius:8px;">Remove</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($courses)): ?>
      <tr><td colspan="7" style="text-align:center;color:var(--text-light);padding:20px;">No courses found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Add Modal -->
<!-- This block renders the modal form for creating a new course. -->
<div id="modal-add" class="modal-overlay" style="display:none">
  <div class="modal" style="max-width:500px;">
    <h3>Add New Course</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <input class="modal-input" name="course_code" placeholder="Course Code (e.g. CCS110)" required style="margin-bottom:0;">
        <input class="modal-input" name="section"     placeholder="Section (e.g. 2-A)"         required style="margin-bottom:0;">
      </div>
      <input class="modal-input" name="title"    placeholder="Course Title" required>
      <input class="modal-input" name="schedule" placeholder="Schedule (e.g. MWF 7:00-8:30 AM)">
      <select class="modal-input" name="teacher_id" required>
        <option value="">— Assign Faculty —</option>
        <?php foreach ($allFaculty as $f): ?>
          <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
        <input class="modal-input" name="max_lessons"  type="number" value="54"        placeholder="Max Lessons"   style="margin-bottom:0;">
        <input class="modal-input" name="school_year"  value="2025-2026"               placeholder="School Year"   style="margin-bottom:0;">
        <select class="modal-input" name="semester" style="margin-bottom:0;">
          <option value="1">1st Semester</option>
          <option value="2" selected>2nd Semester</option>
        </select>
      </div>
      <div class="modal-actions" style="margin-top:14px;">
        <button type="button" class="btn-cancel" onclick="document.getElementById('modal-add').style.display='none'">Cancel</button>
        <button type="submit" class="btn-confirm">Add Course</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<!-- This block renders the modal form for editing a selected course. -->
<div id="modal-edit" class="modal-overlay" style="display:none">
  <div class="modal" style="max-width:500px;">
    <h3>Edit Course</h3>
    <form method="POST">
      <input type="hidden" name="action"    value="edit">
      <input type="hidden" name="course_id" id="edit-cid">
      <input class="modal-input" id="edit-code"     placeholder="Course Code" readonly style="opacity:0.6">
      <input class="modal-input" name="title"    id="edit-title"    placeholder="Course Title" required>
      <input class="modal-input" name="section"  id="edit-section"  placeholder="Section">
      <input class="modal-input" name="schedule" id="edit-schedule" placeholder="Schedule">
      <select class="modal-input" name="teacher_id" id="edit-teacher">
        <?php foreach ($allFaculty as $f): ?>
          <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['full_name']) ?></option>
        <?php endforeach; ?>
      </select>
      <input class="modal-input" name="max_lessons" id="edit-maxless" type="number" placeholder="Max Lessons">
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="document.getElementById('modal-edit').style.display='none'">Cancel</button>
        <button type="submit" class="btn-confirm">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<?php endif; ?>

<script>
// This function fills the edit course modal inputs and then opens the modal.
function openEditModal(id, code, title, section, schedule, teacherId, maxLess) {
  document.getElementById('edit-cid').value      = id;
  document.getElementById('edit-code').value     = code;
  document.getElementById('edit-title').value    = title;
  document.getElementById('edit-section').value  = section;
  document.getElementById('edit-schedule').value = schedule;
  document.getElementById('edit-teacher').value  = teacherId;
  document.getElementById('edit-maxless').value  = maxLess;
  document.getElementById('modal-edit').style.display = 'flex';
}
// This block closes any open modal when the user clicks outside its content panel.
document.querySelectorAll('.modal-overlay').forEach(m =>
  m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; })
);
</script>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
