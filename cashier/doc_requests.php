<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('cashier');

$pdo  = getDB();
$user = currentUser();

// Handle payment recording
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId   = (int)($_POST['request_id'] ?? 0);
    $payStatus   = trim($_POST['payment_status'] ?? '');
    $paymentRef  = trim($_POST['payment_ref'] ?? '');

    if ($requestId && in_array($payStatus, ['paid','waived'], true)) {
        // Fetch fee and current status for validation
        $req = $pdo->prepare("SELECT fee_amount, status, payment_status FROM document_requests WHERE id=?");
        $req->execute([$requestId]);
        $reqRow = $req->fetch();

        if ($reqRow && $reqRow['payment_status'] === 'unpaid') {
            $pdo->prepare(
                "UPDATE document_requests SET payment_status=?, payment_ref=?, verified_by=? WHERE id=?"
            )->execute([$payStatus, $paymentRef ?: null, $user['id'], $requestId]);
            redirectWith('cashier/doc_requests.php', 'Payment recorded successfully.');
        } else {
            redirectWith('cashier/doc_requests.php', 'Request is already paid or not found.', 'error');
        }
    }
}

// Filter
$filter = trim($_GET['filter'] ?? 'all');
$search = trim($_GET['search'] ?? '');
$selectedId = (int)($_GET['id'] ?? 0);

$sql = "SELECT dr.id, dr.reference_no, dr.fee_amount, dr.payment_status, dr.payment_ref,
               dr.status, dr.purpose, dr.copies, dr.created_at, dr.updated_at,
               dt.name AS doc_type,
               u.full_name, s.student_number,
               vu.full_name AS verified_by_name
        FROM document_requests dr
        JOIN document_types dt ON dt.id = dr.document_type_id
        JOIN students s        ON s.id  = dr.student_id
        JOIN users u           ON u.id  = s.user_id
        LEFT JOIN users vu     ON vu.id = dr.verified_by
        WHERE 1=1";
$params = [];
if ($filter === 'unpaid') { $sql .= " AND dr.payment_status='unpaid' AND dr.status NOT IN ('rejected')"; }
if ($filter === 'paid')   { $sql .= " AND dr.payment_status='paid'"; }
if ($search) {
    $sql .= " AND (dr.reference_no LIKE ? OR u.full_name LIKE ? OR s.student_number LIKE ?)";
    $params = array_merge($params, ["%$search%","%$search%","%$search%"]);
}
$sql .= " ORDER BY dr.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// If specific request selected, highlight it
$selectedRequest = null;
if ($selectedId) {
    foreach ($requests as $r) {
        if ($r['id'] === $selectedId) { $selectedRequest = $r; break; }
    }
    if (!$selectedRequest) {
        // fetch it anyway
        $sr = $pdo->prepare("SELECT dr.*, dt.name AS doc_type, u.full_name, s.student_number FROM document_requests dr JOIN document_types dt ON dt.id=dr.document_type_id JOIN students s ON s.id=dr.student_id JOIN users u ON u.id=s.user_id WHERE dr.id=?");
        $sr->execute([$selectedId]);
        $selectedRequest = $sr->fetch() ?: null;
    }
}

$navItems = getNavItems('cashier', 'Doc Requests');
$pageTitle  = 'Payments — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Cashier'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Document Request Payments</div>

<?php if ($selectedRequest && $selectedRequest['payment_status'] === 'unpaid'): ?>
<!-- Payment form for selected request -->
<div class="panel mb-16">
  <div class="panel-header">Record Payment — <?= htmlspecialchars($selectedRequest['reference_no']) ?></div>
  <div style="padding:20px;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
      <div>
        <div class="stat-label">Student</div>
        <div style="font-weight:700;"><?= htmlspecialchars($selectedRequest['full_name']) ?> (<?= htmlspecialchars($selectedRequest['student_number']) ?>)</div>
      </div>
      <div>
        <div class="stat-label">Document</div>
        <div style="font-weight:700;"><?= htmlspecialchars($selectedRequest['doc_type']) ?> × <?= $selectedRequest['copies'] ?></div>
      </div>
      <div>
        <div class="stat-label">Fee Amount</div>
        <div style="font-family:'Caveat',cursive;font-size:1.8rem;font-weight:700;color:var(--accent-dark);">₱<?= number_format($selectedRequest['fee_amount'],2) ?></div>
      </div>
      <div>
        <div class="stat-label">Purpose</div>
        <div style="font-size:.85rem;color:var(--text-mid);"><?= htmlspecialchars($selectedRequest['purpose']) ?></div>
      </div>
    </div>
    <form method="POST">
      <input type="hidden" name="request_id" value="<?= $selectedRequest['id'] ?>">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
        <div>
          <label class="stat-label" style="display:block;margin-bottom:4px;">PAYMENT METHOD</label>
          <select name="payment_status" class="modal-input mb-0">
            <option value="paid">Paid (Cash/GCash/Bank)</option>
            <option value="waived">Waived</option>
          </select>
        </div>
        <div>
          <label class="stat-label" style="display:block;margin-bottom:4px;">REFERENCE / OR NUMBER</label>
          <input type="text" name="payment_ref" class="modal-input mb-0" placeholder="Optional OR or transaction ref">
        </div>
      </div>
      <button type="submit" class="btn-submit btn-green" style="width:auto;padding:11px 28px;">Confirm Payment</button>
      <a href="<?= url('cashier/doc_requests.php') ?>" class="btn-cancel" style="margin-left:10px;">Cancel</a>
    </form>
  </div>
</div>
<?php elseif ($selectedRequest): ?>
<div class="panel mb-16" style="border-left:4px solid var(--green);">
  <div class="panel-header" style="background:var(--green);">Already Paid — <?= htmlspecialchars($selectedRequest['reference_no']) ?></div>
  <div style="padding:16px;">Payment status: <strong><?= ucfirst($selectedRequest['payment_status']) ?></strong>
  <?php if ($selectedRequest['payment_ref']): ?> | Ref: <strong><?= htmlspecialchars($selectedRequest['payment_ref']) ?></strong><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Filter tabs -->
<div style="display:flex;gap:8px;margin-bottom:14px;">
  <?php foreach (['all'=>'All','unpaid'=>'Unpaid','paid'=>'Paid'] as $k=>$l): ?>
  <a href="?filter=<?= $k ?>" class="btn-add" style="text-decoration:none;background:<?= $filter===$k?'var(--accent-dark)':'var(--bg-secondary)' ?>;color:<?= $filter===$k?'white':'var(--text-main)' ?>;"><?= $l ?></a>
  <?php endforeach; ?>
</div>

<form method="GET" class="mgmt-controls">
  <input type="hidden" name="filter" value="<?= htmlspecialchars($filter) ?>">
  <div class="search-box">
    <span>🔍</span>
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Reference no., student name…">
  </div>
  <button type="submit" class="btn-add">Search</button>
</form>

<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr><th>Ref No.</th><th>Student</th><th>Document</th><th>Amount</th><th>Payment</th><th>Request Status</th><th>Date</th><th>Action</th></tr>
    </thead>
    <tbody>
      <?php foreach ($requests as $r): ?>
      <tr>
        <td><strong><?= htmlspecialchars($r['reference_no']) ?></strong></td>
        <td><?= htmlspecialchars($r['full_name']) ?><br><small class="text-muted"><?= htmlspecialchars($r['student_number']) ?></small></td>
        <td><?= htmlspecialchars($r['doc_type']) ?></td>
        <td style="font-weight:700;">₱<?= number_format($r['fee_amount'],2) ?></td>
        <td>
          <?php $pc=match($r['payment_status']){'paid'=>'badge-green','unpaid'=>'badge-red','waived'=>'badge-neutral',default=>'badge-neutral'}; ?>
          <span class="badge <?= $pc ?>"><?= ucfirst($r['payment_status']) ?></span>
          <?php if ($r['payment_ref']): ?><br><small class="text-muted"><?= htmlspecialchars($r['payment_ref']) ?></small><?php endif; ?>
        </td>
        <td>
          <?php $sc=match($r['status']){'submitted','pending'=>'badge-gold','processing'=>'badge-teal','ready_for_pickup'=>'badge-purple','completed'=>'badge-green','rejected'=>'badge-red',default=>'badge-neutral'}; ?>
          <span class="badge <?= $sc ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span>
        </td>
        <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
        <td>
          <?php if ($r['payment_status']==='unpaid'): ?>
          <a href="?id=<?= $r['id'] ?>&filter=<?= $filter ?>" class="btn-edit btn-green" style="text-decoration:none;">Pay</a>
          <?php else: ?>
          <span class="text-muted small">Done</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($requests)): ?>
      <tr><td colspan="8" class="text-center text-muted" style="padding:20px;">No requests found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
