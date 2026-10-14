<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('admin');

$pdo  = getDB();
$user = currentUser();

// ── Handle POST actions ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $fullName  = trim($_POST['full_name']      ?? '');
        $studNum   = trim($_POST['student_number'] ?? '');
        $email     = trim($_POST['email']          ?? '');
        $section   = trim($_POST['section']        ?? '1-A');
        $program   = trim($_POST['program']        ?? 'BSCS');
        $yearLevel = (int)($_POST['year_level']    ?? 1);
        $pass      = $_POST['password']            ?? '';

        if ($fullName && $studNum && $email && $pass) {
            $pdo->beginTransaction();
            try {
                // Insert into users (TABLE 1)
                // Store newly created passwords securely.
                $hashedPass = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare(
                    "INSERT INTO users (username, email, password, role, full_name)
                     VALUES (?, ?, ?, 'student', ?)"
                )->execute([$studNum, $email, $hashedPass, $fullName]);
                $uid = (int)$pdo->lastInsertId();

                // Insert into students (TABLE 2)
                $pdo->prepare(
                    "INSERT INTO students (user_id, student_number, program, section, year_level)
                     VALUES (?, ?, ?, ?, ?)"
                )->execute([$uid, $studNum, $program, $section, $yearLevel]);

                $pdo->commit();
                redirectWith('/admin/students.php', 'Student registered successfully!');
            } catch (Exception $e) {
                $pdo->rollBack();
                redirectWith('/admin/students.php', 'Error: ' . $e->getMessage(), 'error');
            }
        } else {
            redirectWith('/admin/students.php', 'Please fill in all required fields.', 'error');
        }

    } elseif ($action === 'edit') {
        $sid       = (int)($_POST['student_id'] ?? 0);
        $fullName  = trim($_POST['full_name']   ?? '');
        $email     = trim($_POST['email']       ?? '');
        $section   = trim($_POST['section']     ?? '');
        $program   = trim($_POST['program']     ?? '');
        $yearLevel = (int)($_POST['year_level'] ?? 1);

        if ($sid && $fullName && $email) {
            $pdo->prepare(
                "UPDATE students SET section=?, program=?, year_level=? WHERE id=?"
            )->execute([$section, $program, $yearLevel, $sid]);

            $pdo->prepare(
                "UPDATE users SET full_name=?, email=?
                 WHERE id = (SELECT user_id FROM students WHERE id=?)"
            )->execute([$fullName, $email, $sid]);

            redirectWith('/admin/students.php', 'Student updated successfully!');
        }

    } elseif ($action === 'remove') {
        $sid = (int)($_POST['student_id'] ?? 0);
        if ($sid) {
            // CASCADE on FK: deleting user deletes student, attendance, grades
            $pdo->prepare(
                "DELETE u FROM users u
                 JOIN students s ON s.user_id = u.id
                 WHERE s.id = ?"
            )->execute([$sid]);
            redirectWith('/admin/students.php', 'Student removed.');
        }
    }
}

// ── Fetch students ──────────────────────────────────────────
$search  = trim($_GET['search']  ?? '');
$section = trim($_GET['section'] ?? '');
$program = trim($_GET['program'] ?? '');

$sql    = "SELECT s.id, s.student_number, s.section, s.program, s.year_level,
                  u.full_name, u.email
           FROM students s
           JOIN users u ON u.id = s.user_id
           WHERE 1=1";
$params = [];
if ($search) {
    $sql    .= " AND (u.full_name LIKE ? OR s.student_number LIKE ? OR u.email LIKE ?)";
    $params  = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($section) {
    $sql    .= " AND s.section = ?";
    $params[] = $section;
}
if ($program) {
    $sql    .= " AND s.program = ?";
    $params[] = $program;
}
$sql .= " ORDER BY u.full_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Distinct sections and programs for filter dropdowns
$sections = $pdo->query("SELECT DISTINCT section FROM students ORDER BY section")->fetchAll(PDO::FETCH_COLUMN);
$programs = ['BSCS','BSIT','BSIS','BSCE','BSEE'];

$navItems = getNavItems('admin', 'Students');
$pageTitle  = 'Student Management — Horizon Academy';
$topbarUser = ['name' => $user['full_name'] ?? 'Admin', 'role' => 'Admin'];

include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Student Management</div>

<form method="GET" class="mgmt-controls">
  <div class="search-box">
    <span style="font-size:1.2rem;">🔍</span>
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, student no., email…">
  </div>
  <select name="section" class="filter-select" onchange="this.form.submit()">
    <option value="">All Sections</option>
    <?php foreach ($sections as $s): ?>
      <option value="<?= htmlspecialchars($s) ?>" <?= $section === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="program" class="filter-select" onchange="this.form.submit()">
    <option value="">All Programs</option>
    <?php foreach ($programs as $p): ?>
      <option value="<?= $p ?>" <?= $program === $p ? 'selected' : '' ?>><?= $p ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn-add" style="background:var(--green-dark)">Search</button>
  <button type="button" class="btn-add" onclick="document.getElementById('modal-add').style.display='flex'">+ Register Student</button>
</form>

<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr><th>Full Name</th><th>Student No.</th><th>Email</th><th>Program</th><th>Section</th><th>Year</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($students as $s): ?>
      <tr>
        <td><?= htmlspecialchars($s['full_name']) ?></td>
        <td><?= htmlspecialchars($s['student_number']) ?></td>
        <td><?= htmlspecialchars($s['email']) ?></td>
        <td><?= htmlspecialchars($s['program']) ?></td>
        <td><?= htmlspecialchars($s['section']) ?></td>
        <td><?= $s['year_level'] ?></td>
        <td>
          <button class="btn-edit"
            onclick="openEditModal(
              <?= $s['id'] ?>,
              '<?= addslashes($s['full_name']) ?>',
              '<?= addslashes($s['student_number']) ?>',
              '<?= addslashes($s['email']) ?>',
              '<?= addslashes($s['section']) ?>',
              '<?= addslashes($s['program']) ?>',
              <?= $s['year_level'] ?>
            )">Edit</button>
          <form method="POST" style="display:inline"
                onsubmit="return confirm('Remove <?= addslashes($s['full_name']) ?>? This also removes their attendance and grade records.')">
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="student_id" value="<?= $s['id'] ?>">
            <button type="submit" class="btn-remove" style="padding:8px 14px;border-radius:8px;">Remove</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($students)): ?>
      <tr><td colspan="7" style="text-align:center;color:var(--text-light);padding:20px;">No students found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Add Modal -->
<div id="modal-add" class="modal-overlay" style="display:none">
  <div class="modal" style="max-width:480px;">
    <h3>Register New Student</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <input class="modal-input" name="full_name"      placeholder="Full Name" required>
      <input class="modal-input" name="student_number" placeholder="Student Number (used as username)" required>
      <input class="modal-input" name="email"          placeholder="Email Address" type="email" required>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <select class="modal-input" name="program">
          <?php foreach ($programs as $p): ?><option value="<?= $p ?>"><?= $p ?></option><?php endforeach; ?>
        </select>
        <input class="modal-input" name="section" placeholder="Section (e.g. 2-A)" style="margin-bottom:0;" required>
      </div>
      <input class="modal-input" name="year_level" type="number" min="1" max="4" placeholder="Year Level (1-4)" value="1" required>
      <input class="modal-input" name="password" type="password" placeholder="Temporary Password" required>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="document.getElementById('modal-add').style.display='none'">Cancel</button>
        <button type="submit" class="btn-confirm">Register</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="modal-edit" class="modal-overlay" style="display:none">
  <div class="modal" style="max-width:480px;">
    <h3>Edit Student Details</h3>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="student_id" id="edit-sid">
      <input class="modal-input" name="full_name"      id="edit-name"    placeholder="Full Name" required>
      <input class="modal-input"                       id="edit-num"     placeholder="Student Number" readonly style="opacity:0.6">
      <input class="modal-input" name="email"          id="edit-email"   placeholder="Email" type="email" required>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <select class="modal-input" name="program" id="edit-program">
          <?php foreach ($programs as $p): ?><option value="<?= $p ?>"><?= $p ?></option><?php endforeach; ?>
        </select>
        <input class="modal-input" name="section" id="edit-section" placeholder="Section" style="margin-bottom:0;">
      </div>
      <input class="modal-input" name="year_level" id="edit-year" type="number" min="1" max="4" placeholder="Year Level" required>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="document.getElementById('modal-edit').style.display='none'">Cancel</button>
        <button type="submit" class="btn-confirm">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(id, name, num, email, section, program, year) {
  document.getElementById('edit-sid').value     = id;
  document.getElementById('edit-name').value    = name;
  document.getElementById('edit-num').value     = num;
  document.getElementById('edit-email').value   = email;
  document.getElementById('edit-section').value = section;
  document.getElementById('edit-program').value = program;
  document.getElementById('edit-year').value    = year;
  document.getElementById('modal-edit').style.display = 'flex';
}
document.querySelectorAll('.modal-overlay').forEach(m =>
  m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; })
);
</script>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
