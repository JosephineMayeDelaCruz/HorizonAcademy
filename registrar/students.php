<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('registrar');

$pdo  = getDB();
$user = currentUser();

$search  = trim($_GET['search']  ?? '');
$program = trim($_GET['program'] ?? '');
$section = trim($_GET['section'] ?? '');

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
if ($section) { $sql .= " AND s.section = ?"; $params[] = $section; }
if ($program)  { $sql .= " AND s.program = ?"; $params[] = $program; }
$sql .= " ORDER BY u.full_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$sections = $pdo->query("SELECT DISTINCT section FROM students ORDER BY section")->fetchAll(PDO::FETCH_COLUMN);
$programs = ['BSCS','BSIT','BSIS','BSCE','BSEE'];

$navItems = getNavItems('registrar', 'Students');
$pageTitle  = 'Student Records — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Registrar'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Student Records</div>

<form method="GET" class="mgmt-controls">
  <div class="search-box">
    <span>🔍</span>
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, student no., email…">
  </div>
  <select name="section" class="filter-select" onchange="this.form.submit()">
    <option value="">All Sections</option>
    <?php foreach ($sections as $s): ?>
      <option value="<?= htmlspecialchars($s) ?>" <?= $section===$s?'selected':'' ?>><?= htmlspecialchars($s) ?></option>
    <?php endforeach; ?>
  </select>
  <select name="program" class="filter-select" onchange="this.form.submit()">
    <option value="">All Programs</option>
    <?php foreach ($programs as $p): ?>
      <option value="<?= $p ?>" <?= $program===$p?'selected':'' ?>><?= $p ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn-add">Search</button>
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
          <div class="action-stack">
            <a href="<?= url('registrar/doc_requests.php') ?>?student_id=<?= $s['id'] ?>" class="btn-edit btn-teal" style="text-decoration:none;">Doc Requests</a>
            <a href="<?= url('registrar/clearance.php') ?>?student_id=<?= $s['id'] ?>" class="btn-edit" style="text-decoration:none;">Clearance</a>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($students)): ?>
      <tr><td colspan="7" class="text-center text-muted" style="padding:20px;">No students found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
