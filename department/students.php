<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('department');

$pdo  = getDB();
$user = currentUser();

$search = trim($_GET['search'] ?? '');

$sql = "SELECT s.id, s.student_number, s.program, s.section, s.year_level,
               u.full_name, u.email
        FROM students s JOIN users u ON u.id=s.user_id WHERE 1=1";
$params = [];
if ($search) {
    $sql .= " AND (u.full_name LIKE ? OR s.student_number LIKE ?)";
    $params = ["%$search%","%$search%"];
}
$sql .= " ORDER BY u.full_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$navItems = getNavItems('department', 'Students');
$pageTitle  = 'Students — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Department'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Students</div>

<form method="GET" class="mgmt-controls">
  <div class="search-box">
    <span>🔍</span>
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search student…">
  </div>
  <button type="submit" class="btn-add">Search</button>
</form>

<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Full Name</th><th>Student No.</th><th>Program</th><th>Section</th><th>Year</th></tr></thead>
    <tbody>
      <?php foreach ($students as $s): ?>
      <tr>
        <td><?= htmlspecialchars($s['full_name']) ?></td>
        <td><?= htmlspecialchars($s['student_number']) ?></td>
        <td><?= htmlspecialchars($s['program']) ?></td>
        <td><?= htmlspecialchars($s['section']) ?></td>
        <td><?= $s['year_level'] ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($students)): ?>
      <tr><td colspan="5" class="text-center text-muted" style="padding:20px;">No students found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
