<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('department');

$pdo  = getDB();
$user = currentUser();

$deptName = $user['department'] ?? '';
$dept = $pdo->prepare("SELECT id, name, code FROM departments WHERE name=? LIMIT 1");
$dept->execute([$deptName]);
$dept = $dept->fetch();
$deptId = $dept['id'] ?? 0;

if (!$deptId) {
    redirectWith('department/dashboard.php', 'Department not configured.', 'error');
}

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $scId      = (int)($_POST['clearance_id'] ?? 0);
    $remarks   = trim($_POST['remarks'] ?? '');

    if ($scId && in_array($action, ['approve','reject'], true)) {
        // Verify the clearance item belongs to this department
        $verify = $pdo->prepare(
            "SELECT sc.id FROM student_clearance sc
             JOIN clearance_requirements cr ON cr.id = sc.requirement_id
             WHERE sc.id=? AND cr.department_id=?"
        );
        $verify->execute([$scId, $deptId]);
        if ($verify->fetch()) {
            $newStatus = $action === 'approve' ? 'approved' : 'rejected';
            $pdo->prepare(
                "UPDATE student_clearance SET status=?, remarks=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?"
            )->execute([$newStatus, $remarks, $user['id'], $scId]);
            redirectWith('department/clearance.php', 'Clearance item ' . $newStatus . '.');
        } else {
            redirectWith('department/clearance.php', 'Unauthorized action.', 'error');
        }
    }
}

// Get requirements for this department
$requirements = $pdo->prepare(
    "SELECT cr.id, cr.requirement FROM clearance_requirements cr
     WHERE cr.department_id=? AND cr.school_year='2025-2026' AND cr.semester=2
     ORDER BY cr.requirement"
);
$requirements->execute([$deptId]);
$requirements = $requirements->fetchAll();

$reqFilter = (int)($_GET['req_id'] ?? ($requirements[0]['id'] ?? 0));
$statusFilter = trim($_GET['status'] ?? '');

// Load student clearance items for selected requirement
$pendingItems = [];
if ($reqFilter) {
    $sql = "SELECT sc.id AS clearance_id, sc.status, sc.remarks, sc.reviewed_at,
                   s.id AS student_id, s.student_number, s.program, s.section,
                   u.full_name
            FROM student_clearance sc
            JOIN students s ON s.id = sc.student_id
            JOIN users u    ON u.id = s.user_id
            WHERE sc.requirement_id=?";
    $params = [$reqFilter];
    if ($statusFilter) { $sql .= " AND sc.status=?"; $params[] = $statusFilter; }
    $sql .= " ORDER BY sc.status='pending' DESC, u.full_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pendingItems = $stmt->fetchAll();
}

$navItems = getNavItems('department', 'Clearance');
$pageTitle  = 'Clearance Management — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Department'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Clearance — <?= htmlspecialchars($dept['name']) ?></div>

<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;align-items:center;">
  <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap;flex:1;">
    <select name="req_id" class="filter-select" onchange="this.form.submit()">
      <?php foreach ($requirements as $r): ?>
        <option value="<?= $r['id'] ?>" <?= $reqFilter==$r['id']?'selected':'' ?>><?= htmlspecialchars($r['requirement']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="filter-select" onchange="this.form.submit()">
      <option value="">All Statuses</option>
      <option value="pending"  <?= $statusFilter==='pending'  ?'selected':'' ?>>Pending</option>
      <option value="approved" <?= $statusFilter==='approved' ?'selected':'' ?>>Approved</option>
      <option value="rejected" <?= $statusFilter==='rejected' ?'selected':'' ?>>Rejected</option>
    </select>
  </form>
</div>

<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr><th>Student</th><th>Student No.</th><th>Program</th><th>Section</th><th>Status</th><th>Remarks</th><th>Action</th></tr>
    </thead>
    <tbody>
      <?php foreach ($pendingItems as $item): ?>
      <tr>
        <td><?= htmlspecialchars($item['full_name']) ?></td>
        <td><?= htmlspecialchars($item['student_number']) ?></td>
        <td><?= htmlspecialchars($item['program']) ?></td>
        <td><?= htmlspecialchars($item['section']) ?></td>
        <td>
          <span class="badge <?= match($item['status']){'approved'=>'badge-green','rejected'=>'badge-red',default=>'badge-gold'} ?>">
            <?= ucfirst($item['status']) ?>
          </span>
        </td>
        <td class="small text-muted"><?= htmlspecialchars($item['remarks'] ?? '—') ?></td>
        <td>
          <?php if ($item['status'] === 'pending'): ?>
          <button class="btn-add btn-green" style="padding:5px 10px;font-size:.72rem;"
            onclick="openActionModal(<?= $item['clearance_id'] ?>, '<?= addslashes($item['full_name']) ?>', 'approve')">Approve</button>
          <button class="btn-remove" style="padding:5px 10px;font-size:.72rem;"
            onclick="openActionModal(<?= $item['clearance_id'] ?>, '<?= addslashes($item['full_name']) ?>', 'reject')">Reject</button>
          <?php else: ?>
          <span class="text-muted small">Done</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($pendingItems)): ?>
      <tr><td colspan="7" class="text-center text-muted" style="padding:20px;">No clearance items found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Action Modal -->
<div id="modal-action" class="modal-overlay" style="display:none;">
  <div class="modal">
    <h3 id="modal-action-title">Clearance Action</h3>
    <p id="modal-action-student" style="font-size:.88rem;color:var(--text-mid);margin-bottom:16px;"></p>
    <form method="POST">
      <input type="hidden" name="action" id="modal-action-val">
      <input type="hidden" name="clearance_id" id="modal-clearance-id">
      <label style="font-size:.72rem;font-weight:800;letter-spacing:1px;color:var(--text-mid);display:block;margin-bottom:5px;">REMARKS (OPTIONAL)</label>
      <textarea name="remarks" class="modal-input" rows="3" placeholder="Add reason or instructions…"></textarea>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="document.getElementById('modal-action').style.display='none'">Cancel</button>
        <button type="submit" class="btn-confirm" id="modal-confirm-btn">Confirm</button>
      </div>
    </form>
  </div>
</div>

<script>
function openActionModal(id, name, action) {
  document.getElementById('modal-action-val').value  = action;
  document.getElementById('modal-clearance-id').value = id;
  document.getElementById('modal-action-student').textContent = 'Student: ' + name;
  document.getElementById('modal-action-title').textContent  = action === 'approve' ? 'Approve Clearance' : 'Reject Clearance';
  document.getElementById('modal-confirm-btn').style.background = action === 'approve' ? 'var(--green)' : 'var(--red)';
  document.getElementById('modal-confirm-btn').textContent = action === 'approve' ? 'Approve' : 'Reject';
  document.getElementById('modal-action').style.display = 'flex';
}
document.querySelectorAll('.modal-overlay').forEach(m =>
  m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; })
);
</script>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
