<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('cashier');

$pdo  = getDB();
$user = currentUser();

// Summary
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(fee_amount),0) FROM document_requests WHERE payment_status='paid'")->fetchColumn();
$todayRevenue = $pdo->query("SELECT COALESCE(SUM(fee_amount),0) FROM document_requests WHERE payment_status='paid' AND DATE(updated_at)=CURDATE()")->fetchColumn();

// By document type
$byType = $pdo->query(
    "SELECT dt.name, COUNT(dr.id) AS total,
            COALESCE(SUM(CASE WHEN dr.payment_status='paid' THEN dr.fee_amount ELSE 0 END),0) AS revenue
     FROM document_types dt
     LEFT JOIN document_requests dr ON dr.document_type_id = dt.id
     GROUP BY dt.id ORDER BY revenue DESC"
)->fetchAll();

// Recent paid transactions
$recentPaid = $pdo->query(
    "SELECT dr.reference_no, dr.fee_amount, dr.payment_ref, dr.updated_at,
            dt.name AS doc_type,
            u.full_name, s.student_number,
            vu.full_name AS verified_by
     FROM document_requests dr
     JOIN document_types dt ON dt.id = dr.document_type_id
     JOIN students s        ON s.id  = dr.student_id
     JOIN users u           ON u.id  = s.user_id
     LEFT JOIN users vu     ON vu.id = dr.verified_by
     WHERE dr.payment_status='paid'
     ORDER BY dr.updated_at DESC LIMIT 30"
)->fetchAll();

$navItems = getNavItems('cashier', 'Payments');
$pageTitle  = 'Payment History — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Cashier'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Payment Summary</div>

<div class="stats-row">
  <div class="stat-card">
    <div><div class="stat-label">Total Revenue</div><div class="stat-num">₱<?= number_format($totalRevenue,0) ?></div></div>
    <span style="font-size:2rem;opacity:.5;">💰</span>
  </div>
  <div class="stat-card">
    <div><div class="stat-label">Today's Revenue</div><div class="stat-num" style="color:var(--green);">₱<?= number_format($todayRevenue,0) ?></div></div>
    <span style="font-size:2rem;opacity:.5;">📅</span>
  </div>
</div>

<div class="two-col">
  <div class="panel">
    <div class="panel-header">Recent Transactions</div>
    <table class="notif-table">
      <thead><tr><td>Ref No.</td><td>Student</td><td>Doc Type</td><td>Amount</td><td>Date</td></tr></thead>
      <tbody>
        <?php foreach ($recentPaid as $p): ?>
        <tr>
          <td><strong><?= htmlspecialchars($p['reference_no']) ?></strong></td>
          <td><?= htmlspecialchars($p['full_name']) ?></td>
          <td><?= htmlspecialchars($p['doc_type']) ?></td>
          <td style="font-weight:700;color:var(--green);">₱<?= number_format($p['fee_amount'],2) ?></td>
          <td><?= date('M d, Y', strtotime($p['updated_at'])) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recentPaid)): ?>
        <tr><td colspan="5" class="text-center text-muted" style="padding:20px;">No transactions yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="panel">
    <div class="panel-header">Revenue by Document Type</div>
    <table class="notif-table">
      <thead><tr><td>Document</td><td>Count</td><td>Revenue</td></tr></thead>
      <tbody>
        <?php foreach ($byType as $t): ?>
        <tr>
          <td><?= htmlspecialchars($t['name']) ?></td>
          <td><?= $t['total'] ?></td>
          <td style="font-weight:700;">₱<?= number_format($t['revenue'],2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
