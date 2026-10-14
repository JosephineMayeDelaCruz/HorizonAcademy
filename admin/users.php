<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('admin');

$pdo  = getDB();
$user = currentUser();

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $fullName = trim($_POST['full_name']  ?? '');
        $username = trim($_POST['username']   ?? '');
        $email    = trim($_POST['email']      ?? '');
        $role     = trim($_POST['role']       ?? '');
        $dept     = trim($_POST['department'] ?? '');
        $pass     = $_POST['password']        ?? '';
        $validRoles = ['admin','faculty','registrar','cashier','department'];

        if ($fullName && $username && $email && $pass && in_array($role, $validRoles, true)) {
            try {
                $hashed = password_hash($pass, PASSWORD_DEFAULT);
                $pdo->prepare(
                    "INSERT INTO users (username,email,password,role,full_name,department) VALUES (?,?,?,?,?,?)"
                )->execute([$username,$email,$hashed,$role,$fullName,$dept?:null]);
                redirectWith('admin/users.php', 'User created successfully.');
            } catch (Exception $e) {
                redirectWith('admin/users.php', 'Error: ' . $e->getMessage(), 'error');
            }
        } else {
            redirectWith('admin/users.php', 'Please fill all required fields.', 'error');
        }

    } elseif ($action === 'edit') {
        $uid      = (int)($_POST['user_id']   ?? 0);
        $fullName = trim($_POST['full_name']  ?? '');
        $email    = trim($_POST['email']      ?? '');
        $dept     = trim($_POST['department'] ?? '');
        if ($uid && $fullName && $email) {
            $pdo->prepare("UPDATE users SET full_name=?,email=?,department=? WHERE id=?")
                ->execute([$fullName,$email,$dept?:null,$uid]);
            redirectWith('admin/users.php', 'User updated.');
        }

    } elseif ($action === 'reset_pass') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $pass = trim($_POST['new_password'] ?? '');
        if ($uid && strlen($pass) >= 4) {
            $hashed = password_hash($pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hashed,$uid]);
            redirectWith('admin/users.php', 'Password reset successfully.');
        }

    } elseif ($action === 'remove') {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid && $uid !== (int)$user['id']) {
            $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$uid]);
            redirectWith('admin/users.php', 'User removed.');
        } else {
            redirectWith('admin/users.php', 'Cannot remove yourself.', 'error');
        }
    }
}

$roleFilter = trim($_GET['role'] ?? '');
$search     = trim($_GET['search'] ?? '');

$sql = "SELECT id, username, email, full_name, role, department, created_at FROM users WHERE 1=1";
$params = [];
if ($roleFilter) { $sql .= " AND role=?"; $params[] = $roleFilter; }
if ($search) {
    $sql .= " AND (full_name LIKE ? OR username LIKE ? OR email LIKE ?)";
    $params = array_merge($params,["%$search%","%$search%","%$search%"]);
}
$sql .= " ORDER BY role, full_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$roles = ['admin','faculty','registrar','cashier','department'];
$departments = $pdo->query("SELECT DISTINCT name FROM departments ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

$navItems = getNavItems('admin', 'Users');
$pageTitle  = 'User Management — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Admin'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">User Management</div>

<form method="GET" class="mgmt-controls">
  <div class="search-box">
    <span>🔍</span>
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, username, email…">
  </div>
  <select name="role" class="filter-select" onchange="this.form.submit()">
    <option value="">All Roles</option>
    <?php foreach ($roles as $r): ?>
      <option value="<?= $r ?>" <?= $roleFilter===$r?'selected':'' ?>><?= ucfirst($r) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn-add">Search</button>
  <button type="button" class="btn-add" onclick="document.getElementById('modal-add').style.display='flex'">+ Add User</button>
</form>

<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Full Name</th><th>Username</th><th>Email</th><th>Role</th><th>Department</th><th>Created</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <?php $roleColors = ['admin'=>'badge-red','faculty'=>'badge-teal','registrar'=>'badge-purple','cashier'=>'badge-gold','department'=>'badge-green','student'=>'badge-neutral']; ?>
      <tr>
        <td><?= htmlspecialchars($u['full_name']) ?></td>
        <td><?= htmlspecialchars($u['username']) ?></td>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><span class="badge <?= $roleColors[$u['role']] ?? 'badge-neutral' ?>"><?= ucfirst($u['role']) ?></span></td>
        <td><?= htmlspecialchars($u['department'] ?? '—') ?></td>
        <td class="small text-muted"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
        <td>
          <div class="action-stack">
            <button class="btn-edit" onclick="openEditModal(<?= $u['id'] ?>,'<?= addslashes($u['full_name']) ?>','<?= addslashes($u['username']) ?>','<?= addslashes($u['email']) ?>','<?= addslashes($u['department']??'') ?>')">Edit</button>
            <button class="btn-edit btn-teal" onclick="openPassModal(<?= $u['id'] ?>, '<?= addslashes($u['full_name']) ?>')">Reset PW</button>
            <?php if ($u['id'] != $user['id']): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Remove <?= addslashes($u['full_name']) ?>?')">
              <input type="hidden" name="action" value="remove">
              <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn-remove" style="padding:6px 10px;font-size:.72rem;">Remove</button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($users)): ?>
      <tr><td colspan="7" class="text-center text-muted" style="padding:20px;">No users found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Add User Modal -->
<div id="modal-add" class="modal-overlay" style="display:none;">
  <div class="modal">
    <h3>Add User Account</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <input class="modal-input" name="full_name"  placeholder="Full Name" required>
      <input class="modal-input" name="username"   placeholder="Username" required>
      <input class="modal-input" name="email"      placeholder="Email" type="email" required>
      <select class="modal-input" name="role" required>
        <option value="">— Select Role —</option>
        <?php foreach ($roles as $r): ?><option value="<?= $r ?>"><?= ucfirst($r) ?></option><?php endforeach; ?>
      </select>
      <select class="modal-input" name="department">
        <option value="">— No Department —</option>
        <?php foreach ($departments as $d): ?><option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option><?php endforeach; ?>
      </select>
      <input class="modal-input" name="password" type="password" placeholder="Password" required>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="document.getElementById('modal-add').style.display='none'">Cancel</button>
        <button type="submit" class="btn-confirm">Create User</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="modal-edit" class="modal-overlay" style="display:none;">
  <div class="modal">
    <h3>Edit User</h3>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="user_id" id="edit-uid">
      <input class="modal-input" name="full_name"  id="edit-name"  placeholder="Full Name" required>
      <input class="modal-input"                    id="edit-uname" placeholder="Username" readonly style="opacity:.6">
      <input class="modal-input" name="email"       id="edit-email" placeholder="Email" type="email" required>
      <select class="modal-input" name="department" id="edit-dept">
        <option value="">— No Department —</option>
        <?php foreach ($departments as $d): ?><option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option><?php endforeach; ?>
      </select>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="document.getElementById('modal-edit').style.display='none'">Cancel</button>
        <button type="submit" class="btn-confirm">Save</button>
      </div>
    </form>
  </div>
</div>

<!-- Reset Password Modal -->
<div id="modal-pass" class="modal-overlay" style="display:none;">
  <div class="modal">
    <h3>Reset Password</h3>
    <p id="pass-name" style="font-size:.88rem;color:var(--text-mid);margin-bottom:14px;"></p>
    <form method="POST">
      <input type="hidden" name="action" value="reset_pass">
      <input type="hidden" name="user_id" id="pass-uid">
      <input class="modal-input" name="new_password" type="password" placeholder="New Password (min 4 chars)" required minlength="4">
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="document.getElementById('modal-pass').style.display='none'">Cancel</button>
        <button type="submit" class="btn-confirm">Reset</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(id, name, uname, email, dept) {
  document.getElementById('edit-uid').value   = id;
  document.getElementById('edit-name').value  = name;
  document.getElementById('edit-uname').value = uname;
  document.getElementById('edit-email').value = email;
  document.getElementById('edit-dept').value  = dept;
  document.getElementById('modal-edit').style.display = 'flex';
}
function openPassModal(id, name) {
  document.getElementById('pass-uid').value = id;
  document.getElementById('pass-name').textContent = 'User: ' + name;
  document.getElementById('modal-pass').style.display = 'flex';
}
document.querySelectorAll('.modal-overlay').forEach(m =>
  m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; })
);
</script>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
