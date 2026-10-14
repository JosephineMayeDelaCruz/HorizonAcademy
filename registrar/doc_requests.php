<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('registrar');

$pdo  = getDB();
$user = currentUser();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action']     ?? '';
    $requestId = (int)($_POST['request_id'] ?? 0);
    $remarks   = trim($_POST['remarks'] ?? '');

    if ($requestId && $action === 'update_status') {
        $newStatus = trim($_POST['new_status'] ?? '');
        $allowed   = ['pending','processing','ready_for_pickup','completed','rejected'];

        if (in_array($newStatus, $allowed, true)) {
            // Get old status for log
            $old = $pdo->prepare("SELECT status FROM document_requests WHERE id=?");
            $old->execute([$requestId]);
            $oldStatus = $old->fetchColumn();

            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    "UPDATE document_requests SET status=?, staff_remarks=?, processed_by=? WHERE id=?"
                )->execute([$newStatus, $remarks, $user['id'], $requestId]);

                $pdo->prepare(
                    "INSERT INTO document_request_logs (request_id, old_status, new_status, changed_by, remarks)
                     VALUES (?, ?, ?, ?, ?)"
                )->execute([$requestId, $oldStatus, $newStatus, $user['id'], $remarks]);

                $pdo->commit();
                redirectWith('registrar/doc_requests.php', 'Request updated successfully.');
            } catch (Exception $e) {
                $pdo->rollBack();
                redirectWith('registrar/doc_requests.php', 'Error: ' . $e->getMessage(), 'error');
            }
        }
    }
}

// Filters
$statusFilter  = trim($_GET['status'] ?? '');
$studentFilter = (int)($_GET['student_id'] ?? 0);
$search        = trim($_GET['search'] ?? '');

$sql = "SELECT dr.id, dr.reference_no, dr.status, dr.payment_status,
               dr.fee_amount, dr.copies, dr.purpose, dr.created_at, dr.staff_remarks,
               dt.name AS doc_type,
               u.full_name AS student_name,
               s.student_number
        FROM document_requests dr
        JOIN document_types dt ON dt.id = dr.document_type_id
        JOIN students s        ON s.id  = dr.student_id
        JOIN users u           ON u.id  = s.user_id
        WHERE 1=1";
$params = [];
if ($statusFilter)  { $sql .= " AND dr.status=?";         $params[] = $statusFilter; }
if ($studentFilter) { $sql .= " AND dr.student_id=?";     $params[] = $studentFilter; }
if ($search) {
    $sql .= " AND (dr.reference_no LIKE ? OR u.full_name LIKE ? OR s.student_number LIKE ?)";
    $params = array_merge($params, ["%$search%","%$search%","%$search%"]);
}
$sql .= " ORDER BY dr.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$navItems = getNavItems('registrar', 'Doc Requests');
$pageTitle  = 'Document Requests — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Registrar'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Document Requests</div>

<form method="GET" class="mgmt-controls">
  <div class="search-box">
    <span>🔍</span>
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Reference no., student name…">
  </div>
  <select name="status" class="filter-select" onchange="this.form.submit()">
    <option value="">All Statuses</option>
    <?php foreach (['submitted','pending','processing','ready_for_pickup','completed','rejected'] as $st): ?>
      <option value="<?= $st ?>" <?= $statusFilter===$st?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$st)) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn-add">Search</button>
</form>

<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr><th>Ref No.</th><th>Student</th><th>Document</th><th>Copies</th><th>Fee</th><th>Payment</th><th>Status</th><th>Date</th><th>Action</th></tr>
    </thead>
    <tbody>
      <?php foreach ($requests as $r): ?>
      <tr>
        <td><strong><?= htmlspecialchars($r['reference_no']) ?></strong></td>
        <td><?= htmlspecialchars($r['student_name']) ?><br><small class="text-muted"><?= htmlspecialchars($r['student_number']) ?></small></td>
        <td><?= htmlspecialchars($r['doc_type']) ?></td>
        <td><?= $r['copies'] ?></td>
        <td>₱<?= number_format($r['fee_amount'],2) ?></td>
        <td>
          <?php $pc = match($r['payment_status']) { 'paid'=>'badge-green','unpaid'=>'badge-red','waived'=>'badge-neutral', default=>'badge-neutral' }; ?>
          <span class="badge <?= $pc ?>"><?= ucfirst($r['payment_status']) ?></span>
        </td>
        <td>
          <?php $sc = match($r['status']) {
            'submitted','pending'=>'badge-gold','processing'=>'badge-teal',
            'ready_for_pickup'=>'badge-purple','completed'=>'badge-green','rejected'=>'badge-red',default=>'badge-neutral'
          }; ?>
          <span class="badge <?= $sc ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span>
        </td>
        <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
        <td>
          <button class="btn-edit" onclick="openUpdateModal(
            <?= $r['id'] ?>,
            '<?= addslashes($r['reference_no']) ?>',
            '<?= addslashes($r['student_name']) ?>',
            '<?= addslashes($r['doc_type']) ?>',
            '<?= $r['status'] ?>',
            '<?= addslashes($r['staff_remarks'] ?? '') ?>'
          )">Update</button>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($requests)): ?>
      <tr><td colspan="9" class="text-center text-muted" style="padding:20px;">No document requests found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Update Status Modal -->
<div id="modal-update" class="modal-overlay" style="display:none;">
  <div class="modal" style="max-width:480px;">
    <h3>Update Request Status</h3>
    <div id="modal-ref" style="font-family:'Caveat',cursive;font-size:1.3rem;color:var(--accent-dark);margin-bottom:4px;"></div>
    <div id="modal-info" style="font-size:.82rem;color:var(--text-muted);margin-bottom:16px;"></div>
    <form method="POST">
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="request_id" id="modal-rid">
      <div style="margin-bottom:10px;">
        <label style="font-size:.72rem;font-weight:800;letter-spacing:1px;color:var(--text-mid);display:block;margin-bottom:5px;">NEW STATUS</label>
        <select name="new_status" id="modal-status" class="modal-input mb-0">
          <option value="pending">Pending</option>
          <option value="processing">Processing</option>
          <option value="ready_for_pickup">Ready for Pickup</option>
          <option value="completed">Completed</option>
          <option value="rejected">Rejected</option>
        </select>
      </div>
      <div style="margin-top:10px;">
        <label style="font-size:.72rem;font-weight:800;letter-spacing:1px;color:var(--text-mid);display:block;margin-bottom:5px;">REMARKS (OPTIONAL)</label>
        <textarea name="remarks" id="modal-remarks" class="modal-input mb-0" rows="3" placeholder="Add remarks or instructions…"></textarea>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="document.getElementById('modal-update').style.display='none'">Cancel</button>
        <button type="submit" class="btn-confirm">Update Status</button>
      </div>
    </form>
  </div>
</div>

<script>
function openUpdateModal(id, ref, student, doc, status, remarks) {
  document.getElementById('modal-rid').value     = id;
  document.getElementById('modal-ref').textContent = ref;
  document.getElementById('modal-info').textContent = student + ' — ' + doc;
  document.getElementById('modal-status').value  = status;
  document.getElementById('modal-remarks').value = remarks;
  document.getElementById('modal-update').style.display = 'flex';
}
document.querySelectorAll('.modal-overlay').forEach(m =>
  m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; })
);
</script>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
