<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('admin');

$pdo  = getDB();
$user = currentUser();

$period = '2025-2026-2';

$queueData = $pdo->prepare(
    "SELECT eq.id, eq.queue_number, eq.category, eq.status, eq.enrollment_period,
            eq.created_at, eq.called_at, eq.served_at,
            u.full_name, s.student_number,
            cu.full_name AS called_by_name
     FROM enrollment_queue eq
     JOIN students s ON s.id = eq.student_id
     JOIN users u    ON u.id = s.user_id
     LEFT JOIN users cu ON cu.id = eq.called_by
     WHERE eq.enrollment_period=?
     ORDER BY
       FIELD(eq.status,'serving','waiting','completed','skipped'),
       eq.created_at ASC"
);
$queueData->execute([$period]);
$queue = $queueData->fetchAll();

$stats = [];
foreach (['waiting','serving','completed','skipped'] as $s) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM enrollment_queue WHERE status=? AND enrollment_period=?");
    $c->execute([$s,$period]);
    $stats[$s] = (int)$c->fetchColumn();
}

$navItems = getNavItems('admin', 'Queue');
$pageTitle  = 'Queue Monitor — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Admin'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Enrollment Queue Monitor</div>

<div class="stats-row">
  <?php
  $sd = ['waiting'=>['badge-gold','⏳'],'serving'=>['badge-green','🔔'],'completed'=>['badge-neutral','✅'],'skipped'=>['badge-red','⏭️']];
  foreach ($sd as $s=>[$cls,$icon]):
  ?>
  <div class="stat-card">
    <div><div class="stat-label"><?= ucfirst($s) ?></div><div class="stat-num"><?= $stats[$s] ?></div></div>
    <span style="font-size:2rem;"><?= $icon ?></span>
  </div>
  <?php endforeach; ?>
</div>

<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr><th>Queue No.</th><th>Student</th><th>Category</th><th>Status</th><th>Called By</th><th>Queued At</th><th>Served At</th></tr>
    </thead>
    <tbody>
      <?php foreach ($queue as $q): ?>
      <?php $qc=match($q['status']){'waiting'=>'badge-gold','serving'=>'badge-green','completed'=>'badge-neutral','skipped'=>'badge-red',default=>'badge-neutral'}; ?>
      <tr>
        <td><strong style="font-family:'Caveat',cursive;font-size:1.2rem;color:var(--accent-dark);"><?= htmlspecialchars($q['queue_number']) ?></strong></td>
        <td><?= htmlspecialchars($q['full_name']) ?><br><small class="text-muted"><?= htmlspecialchars($q['student_number']) ?></small></td>
        <td><?= htmlspecialchars($q['category']) ?></td>
        <td><span class="badge <?= $qc ?>"><?= ucfirst($q['status']) ?></span></td>
        <td class="small"><?= htmlspecialchars($q['called_by_name'] ?? '—') ?></td>
        <td class="small"><?= date('h:i A', strtotime($q['created_at'])) ?></td>
        <td class="small"><?= $q['served_at'] ? date('h:i A', strtotime($q['served_at'])) : '—' ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($queue)): ?>
      <tr><td colspan="7" class="text-center text-muted" style="padding:20px;">No queue entries today.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
