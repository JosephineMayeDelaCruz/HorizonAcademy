<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('registrar');

$pdo  = getDB();
$user = currentUser();

// Stats
$totalStudents     = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$totalDocRequests  = $pdo->query("SELECT COUNT(*) FROM document_requests")->fetchColumn();
$pendingRequests   = $pdo->query("SELECT COUNT(*) FROM document_requests WHERE status IN ('submitted','pending','processing')")->fetchColumn();
$waitingQueue      = $pdo->query("SELECT COUNT(*) FROM enrollment_queue WHERE status='waiting'")->fetchColumn();

// Recent document requests
$recentRequests = $pdo->query(
    "SELECT dr.reference_no, dr.status, dr.payment_status, dr.created_at,
            dt.name AS doc_type,
            u.full_name AS student_name
     FROM document_requests dr
     JOIN document_types dt ON dt.id = dr.document_type_id
     JOIN students s         ON s.id  = dr.student_id
     JOIN users u            ON u.id  = s.user_id
     ORDER BY dr.created_at DESC LIMIT 8"
)->fetchAll();

// Queue summary
$queueSummary = $pdo->query(
    "SELECT status, COUNT(*) AS cnt FROM enrollment_queue
     WHERE enrollment_period = '2025-2026-2'
     GROUP BY status"
)->fetchAll(PDO::FETCH_KEY_PAIR);

$navItems = getNavItems('registrar', 'Dashboard');
$pageTitle  = 'Registrar Dashboard — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Registrar'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Registrar Dashboard</div>

<div class="stats-row">
  <div class="stat-card">
    <div><div class="stat-label">Total Students</div><div class="stat-num"><?= number_format($totalStudents) ?></div></div>
    <span style="font-size:2rem;opacity:.5;">👨‍🎓</span>
  </div>
  <div class="stat-card">
    <div><div class="stat-label">Total Doc Requests</div><div class="stat-num"><?= number_format($totalDocRequests) ?></div></div>
    <span style="font-size:2rem;opacity:.5;">📄</span>
  </div>
  <div class="stat-card">
    <div><div class="stat-label">Pending Requests</div><div class="stat-num" style="color:var(--gold);"><?= number_format($pendingRequests) ?></div></div>
    <span style="font-size:2rem;opacity:.5;">⏳</span>
  </div>
  <div class="stat-card">
    <div><div class="stat-label">Queue Waiting</div><div class="stat-num" style="color:var(--teal);"><?= number_format($waitingQueue) ?></div></div>
    <span style="font-size:2rem;opacity:.5;">🔢</span>
  </div>
</div>

<div class="two-col">
  <div class="panel">
    <div class="panel-header">Recent Document Requests</div>
    <table class="notif-table">
      <thead><tr>
        <td>Reference</td><td>Student</td><td>Type</td><td>Status</td>
      </tr></thead>
      <tbody>
        <?php foreach ($recentRequests as $r): ?>
        <tr>
          <td><strong><?= htmlspecialchars($r['reference_no']) ?></strong></td>
          <td><?= htmlspecialchars($r['student_name']) ?></td>
          <td><?= htmlspecialchars($r['doc_type']) ?></td>
          <td>
            <?php
            $sClass = match($r['status']) {
                'submitted','pending' => 'badge-gold',
                'processing'          => 'badge-teal',
                'ready_for_pickup'    => 'badge-purple',
                'completed'           => 'badge-green',
                'rejected'            => 'badge-red',
                default               => 'badge-neutral',
            };
            ?>
            <span class="badge <?= $sClass ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recentRequests)): ?>
        <tr><td colspan="4" class="text-center text-muted" style="padding:20px;">No requests yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <div style="padding:10px 16px;">
      <a href="<?= url('registrar/doc_requests.php') ?>" class="btn-submit" style="text-decoration:none;display:block;text-align:center;">Manage All Requests</a>
    </div>
  </div>

  <div class="panel">
    <div class="assign-form">
      <h3>Enrollment Queue Summary</h3>
      <div style="margin-bottom:12px;">
        <?php
        $labels = ['waiting'=>'Waiting','serving'=>'Currently Serving','completed'=>'Completed','skipped'=>'Skipped'];
        $classes = ['waiting'=>'badge-gold','serving'=>'badge-green','completed'=>'badge-neutral','skipped'=>'badge-red'];
        foreach ($labels as $key => $label):
        ?>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border);">
          <span style="font-size:.88rem;color:var(--text-mid);font-weight:600;"><?= $label ?></span>
          <span class="badge <?= $classes[$key] ?>"><?= $queueSummary[$key] ?? 0 ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <a href="<?= url('registrar/queue.php') ?>" class="btn-submit" style="text-decoration:none;display:block;text-align:center;background:var(--teal);">Manage Queue</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
