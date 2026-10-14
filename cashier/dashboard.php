<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('cashier');

$pdo  = getDB();
$user = currentUser();

$totalRequests = $pdo->query("SELECT COUNT(*) FROM document_requests")->fetchColumn();
$unpaidReady   = $pdo->query("SELECT COUNT(*) FROM document_requests WHERE payment_status='unpaid' AND status NOT IN ('rejected')")->fetchColumn();
$paidToday     = $pdo->query("SELECT COUNT(*) FROM document_requests WHERE payment_status='paid' AND DATE(updated_at)=CURDATE()")->fetchColumn();
$revenueToday  = $pdo->query("SELECT COALESCE(SUM(fee_amount),0) FROM document_requests WHERE payment_status='paid' AND DATE(updated_at)=CURDATE()")->fetchColumn();

// Recent unpaid requests
$unpaidList = $pdo->query(
    "SELECT dr.id, dr.reference_no, dr.fee_amount, dr.status, dr.created_at,
            dt.name AS doc_type, u.full_name, s.student_number
     FROM document_requests dr
     JOIN document_types dt ON dt.id = dr.document_type_id
     JOIN students s        ON s.id  = dr.student_id
     JOIN users u           ON u.id  = s.user_id
     WHERE dr.payment_status='unpaid' AND dr.status NOT IN ('rejected')
     ORDER BY dr.created_at ASC LIMIT 10"
)->fetchAll();

$navItems = getNavItems('cashier', 'Dashboard');
$pageTitle  = 'Cashier Dashboard — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Cashier'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Cashier Dashboard</div>

<div class="stats-row">
  <div class="stat-card">
    <div><div class="stat-label">Total Requests</div><div class="stat-num"><?= number_format($totalRequests) ?></div></div>
    <span style="font-size:2rem;opacity:.5;">📄</span>
  </div>
  <div class="stat-card">
    <div><div class="stat-label">Unpaid</div><div class="stat-num" style="color:var(--red);"><?= number_format($unpaidReady) ?></div></div>
    <span style="font-size:2rem;opacity:.5;">💸</span>
  </div>
  <div class="stat-card">
    <div><div class="stat-label">Paid Today</div><div class="stat-num" style="color:var(--green);"><?= number_format($paidToday) ?></div></div>
    <span style="font-size:2rem;opacity:.5;">✅</span>
  </div>
  <div class="stat-card">
    <div><div class="stat-label">Revenue Today</div><div class="stat-num">₱<?= number_format($revenueToday,0) ?></div></div>
    <span style="font-size:2rem;opacity:.5;">💰</span>
  </div>
</div>

<div class="panel">
  <div class="panel-header">Pending Payments</div>
  <table class="data-table">
    <thead>
      <tr><th>Ref No.</th><th>Student</th><th>Document</th><th>Amount</th><th>Request Status</th><th>Date</th><th>Action</th></tr>
    </thead>
    <tbody>
      <?php foreach ($unpaidList as $r): ?>
      <tr>
        <td><strong><?= htmlspecialchars($r['reference_no']) ?></strong></td>
        <td><?= htmlspecialchars($r['full_name']) ?><br><small class="text-muted"><?= htmlspecialchars($r['student_number']) ?></small></td>
        <td><?= htmlspecialchars($r['doc_type']) ?></td>
        <td style="font-weight:700;">₱<?= number_format($r['fee_amount'],2) ?></td>
        <td>
          <?php $sc=match($r['status']){'submitted','pending'=>'badge-gold','processing'=>'badge-teal','ready_for_pickup'=>'badge-purple','completed'=>'badge-green','rejected'=>'badge-red',default=>'badge-neutral'}; ?>
          <span class="badge <?= $sc ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span>
        </td>
        <td><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
        <td>
          <a href="<?= url('cashier/doc_requests.php') ?>?id=<?= $r['id'] ?>" class="btn-edit btn-green" style="text-decoration:none;">Record Payment</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($unpaidList)): ?>
      <tr><td colspan="7" class="text-center text-muted" style="padding:20px;">No pending payments.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
