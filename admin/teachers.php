<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('admin');

$pdo  = getDB();
$user = currentUser();

// This block handles form submissions for adding, editing, and removing faculty accounts.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $fullName = trim($_POST['full_name']  ?? '');
        $username = trim($_POST['username']   ?? '');
        $email    = trim($_POST['email']      ?? '');
        $dept     = trim($_POST['department'] ?? '');
        $pass     = $_POST['password']        ?? '';

        if ($fullName && $username && $email && $pass) {
            $pdo->beginTransaction();
            try {
                // Store newly created passwords securely.
                $hashedPass = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare(
                    "INSERT INTO users (username, email, password, role, full_name, department) VALUES (?, ?, ?, 'faculty', ?, ?)"
                )->execute([$username, $email, $hashedPass, $fullName, $dept]);
                $pdo->commit();
                redirectWith('/admin/teachers.php', 'Faculty registered successfully!');
            } catch (Exception $e) {
                $pdo->rollBack();
                redirectWith('/admin/teachers.php', 'Error: ' . $e->getMessage(), 'error');
            }
        } else {
            redirectWith('/admin/teachers.php', 'Please fill all required fields.', 'error');
        }
    } elseif ($action === 'edit') {
        $tid      = (int)($_POST['teacher_id'] ?? 0);
        $fullName = trim($_POST['full_name']   ?? '');
        $email    = trim($_POST['email']       ?? '');
        $dept     = trim($_POST['department']  ?? '');
        if ($tid && $fullName && $email) {
            $pdo->prepare("UPDATE users SET full_name=?, email=?, department=? WHERE id=? AND role='faculty'")
                ->execute([$fullName, $email, $dept, $tid]);
            redirectWith('/admin/teachers.php', 'Faculty updated!');
        }
    } elseif ($action === 'remove') {
        $tid = (int)($_POST['teacher_id'] ?? 0);
        if ($tid) {
            $cnt = $pdo->prepare("SELECT COUNT(*) FROM courses WHERE teacher_id=?");
            $cnt->execute([$tid]);
            if ((int)$cnt->fetchColumn() > 0) {
                redirectWith('/admin/teachers.php', 'Cannot remove: faculty has assigned courses.', 'error');
            } else {
                $pdo->prepare("DELETE FROM users WHERE id=? AND role='faculty'")->execute([$tid]);
                redirectWith('/admin/teachers.php', 'Faculty removed.');
            }
        }
    }
}

// This block reads filter values from the URL so the list can be narrowed by search and department.
$search = trim($_GET['search'] ?? '');
$dept   = trim($_GET['dept']   ?? '');

// This block builds and executes the faculty list query with optional filters and course counts.
$sql    = "SELECT u.id, u.username, u.email, u.full_name, u.department, COUNT(c.id) AS course_count
           FROM users u LEFT JOIN courses c ON c.teacher_id = u.id
           WHERE u.role = 'faculty'";
$params = [];
if ($search) {
    $sql    .= " AND (u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ?)";
    $params  = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}
if ($dept) {
    $sql .= " AND u.department LIKE ?";
    $params[] = "%$dept%";
}
$sql .= " GROUP BY u.id ORDER BY u.full_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$teachers = $stmt->fetchAll();

// This block loads distinct department names for the department filter dropdown.
$deptList = $pdo->query("SELECT DISTINCT department FROM users WHERE role='faculty' AND department IS NOT NULL ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

$navItems = getNavItems('admin', 'Faculty');
$pageTitle  = 'Faculty Management — Horizon Academy';
$topbarUser = ['name' => $user['full_name'] ?? 'Admin', 'role' => 'Admin'];
include __DIR__ . '/../partials/layout_start.php';
?>
<!-- This block renders the filter controls and action button for faculty management. -->
<div class="content-title">Faculty Management</div>
<form method="GET" class="mgmt-controls">
  <div class="search-box">
    <span style="font-size:1.2rem;">🔍</span>
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, username, email…">
  </div>
  <select name="dept" class="filter-select" onchange="this.form.submit()">
    <option value="">All Departments</option>
    <?php foreach ($deptList as $d): ?>
      <option value="<?= htmlspecialchars($d) ?>" <?= $dept === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn-add" style="background:var(--green-dark)">Search</button>
  <button type="button" class="btn-add" onclick="document.getElementById('modal-add').style.display='flex'">+ Add Faculty</button>
</form>
<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Full Name</th><th>Username</th><th>Email</th><th>Department</th><th>Courses</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($teachers as $t): ?>
      <tr>
        <td><?= htmlspecialchars($t['full_name']) ?></td>
        <td><?= htmlspecialchars($t['username']) ?></td>
        <td><?= htmlspecialchars($t['email']) ?></td>
        <td><?= htmlspecialchars($t['department'] ?? '—') ?></td>
        <td><?= $t['course_count'] ?></td>
        <td>
          <button class="btn-edit" onclick="openEditModal(<?= $t['id'] ?>,'<?= addslashes($t['full_name']) ?>','<?= addslashes($t['username']) ?>','<?= addslashes($t['email']) ?>','<?= addslashes($t['department'] ?? '') ?>')">Edit</button>
          <form method="POST" style="display:inline" onsubmit="return confirm('Remove <?= addslashes($t['full_name']) ?>?')">
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="teacher_id" value="<?= $t['id'] ?>">
            <button type="submit" class="btn-remove" style="padding:8px 14px;border-radius:8px;">Remove</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($teachers)): ?>
      <tr><td colspan="6" style="text-align:center;color:var(--text-light);padding:20px;">No faculty found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<!-- This block renders the modal form used to create a new faculty record. -->
<div id="modal-add" class="modal-overlay" style="display:none">
  <div class="modal" style="max-width:460px;">
    <h3>Add Faculty Member</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <input class="modal-input" name="full_name"  placeholder="Full Name" required>
      <input class="modal-input" name="username"   placeholder="Username" required>
      <input class="modal-input" name="email"      placeholder="Email" type="email" required>
      <input class="modal-input" name="department" placeholder="Department">
      <input class="modal-input" name="password"   placeholder="Temporary Password" type="password" required>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="document.getElementById('modal-add').style.display='none'">Cancel</button>
        <button type="submit" class="btn-confirm">Register</button>
      </div>
    </form>
  </div>
</div>
<!-- This block renders the modal form used to edit an existing faculty record. -->
<div id="modal-edit" class="modal-overlay" style="display:none">
  <div class="modal" style="max-width:460px;">
    <h3>Edit Faculty Details</h3>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="teacher_id" id="edit-tid">
      <input class="modal-input" name="full_name"  id="edit-name"  placeholder="Full Name" required>
      <input class="modal-input"                   id="edit-user"  placeholder="Username" readonly style="opacity:0.6">
      <input class="modal-input" name="email"      id="edit-email" placeholder="Email" type="email" required>
      <input class="modal-input" name="department" id="edit-dept"  placeholder="Department">
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="document.getElementById('modal-edit').style.display='none'">Cancel</button>
        <button type="submit" class="btn-confirm">Save Changes</button>
      </div>
    </form>
  </div>
</div>
<script>
// This function fills the edit modal fields with the selected faculty data and opens the modal.
function openEditModal(id, name, username, email, dept) {
  document.getElementById('edit-tid').value   = id;
  document.getElementById('edit-name').value  = name;
  document.getElementById('edit-user').value  = username;
  document.getElementById('edit-email').value = email;
  document.getElementById('edit-dept').value  = dept;
  document.getElementById('modal-edit').style.display = 'flex';
}
// This block closes open modals when the backdrop area is clicked.
document.querySelectorAll('.modal-overlay').forEach(m =>
  m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; })
);
</script>
<?php include __DIR__ . '/../partials/layout_end.php'; ?>
