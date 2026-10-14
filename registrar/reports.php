<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('registrar');

$pdo  = getDB();
$user = currentUser();

$report = trim($_GET['report'] ?? 'requests');

// Doc request summary by type
$requestByType = $pdo->query(
    "SELECT dt.name, COUNT(dr.id) AS total,
            SUM(CASE WHEN dr.status='completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN dr.status='rejected'  THEN 1 ELSE 0 END) AS rejected,
            SUM(dr.fee_amount) AS total_fees
     FROM document_types dt
     LEFT JOIN document_requests dr ON dr.document_type_id = dt.id
     GROUP BY dt.id ORDER BY total DESC"
)->fetchAll();

// Queue history
$queueHistory = $pdo->query(
    "SELECT eq.queue_number, eq.category, eq.status, eq.enrollment_period,
            u.full_name AS student_name, s.student_number,
            cu.full_name AS called_by_name,
            eq.created_at, eq.served_at
     FROM enrollment_queue eq
     JOIN students s ON s.id = eq.student_id
     JOIN users u    ON u.id = s.user_id
     LEFT JOIN users cu ON cu.id = eq.called_by
     ORDER BY eq.created_at DESC LIMIT 50"
)->fetchAll();

$navItems = getNavItems('registrar', 'Reports');
$pageTitle  = 'Registrar Reports — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Registrar'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Reports</div>

<div style="display:flex;gap:8px;margin-bottom:16px;">
  <a href="?report=requests" class="btn-add" style="text-decoration:none;background:<?= $report==='requests'?'var(--accent-dark)':'var(--bg-secondary)' ?>;color:<?= $report==='requests'?'white':'var(--text-main)' ?>;">Doc Request Summary</a>
  <a href="?report=queue"    class="btn-add" style="text-decoration:none;background:<?= $report==='queue'   ?'var(--accent-dark)':'var(--bg-secondary)' ?>;color:<?= $report==='queue'   ?'white':'var(--text-main)' ?>;">Queue History</a>
</div>

<?php if ($report === 'requests'): ?>
<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Document Type</th><th>Total</th><th>Completed</th><th>Rejected</th><th>Total Fees</th></tr></thead>
    <tbody>
      <?php foreach ($requestByType as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><?= $r['total'] ?></td>
        <td style="color:var(--green);font-weight:700;"><?= $r['completed'] ?></td>
        <td style="color:var(--red);font-weight:700;"><?= $r['rejected'] ?></td>
        <td>₱<?= number_format($r['total_fees'],2) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php elseif ($report === 'queue'): ?>
<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Queue No.</th><th>Student</th><th>Category</th><th>Status</th><th>Period</th><th>Called By</th><th>Date</th></tr></thead>
    <tbody>
      <?php foreach ($queueHistory as $q): ?>
      <?php $qc = match($q['status']){'waiting'=>'badge-gold','serving'=>'badge-green','completed'=>'badge-neutral','skipped'=>'badge-red',default=>'badge-neutral'}; ?>
      <tr>
        <td><strong><?= htmlspecialchars($q['queue_number']) ?></strong></td>
        <td><?= htmlspecialchars($q['student_name']) ?><br><small class="text-muted"><?= htmlspecialchars($q['student_number']) ?></small></td>
        <td><?= htmlspecialchars($q['category']) ?></td>
        <td><span class="badge <?= $qc ?>"><?= ucfirst($q['status']) ?></span></td>
        <td><?= htmlspecialchars($q['enrollment_period']) ?></td>
        <td><?= htmlspecialchars($q['called_by_name'] ?? '—') ?></td>
        <td><?= date('M d, Y h:i A', strtotime($q['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($queueHistory)): ?>
      <tr><td colspan="7" class="text-center text-muted" style="padding:20px;">No queue history.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
