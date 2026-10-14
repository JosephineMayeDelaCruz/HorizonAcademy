<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('admin');

$pdo  = getDB();
$user = currentUser();

$statusFilter = trim($_GET['status'] ?? '');
$search       = trim($_GET['search'] ?? '');

$sql = "SELECT dr.id, dr.reference_no, dr.status, dr.payment_status,
               dr.fee_amount, dr.copies, dr.created_at,
               dt.name AS doc_type,
               u.full_name AS student_name, s.student_number
        FROM document_requests dr
        JOIN document_types dt ON dt.id = dr.document_type_id
        JOIN students s        ON s.id  = dr.student_id
        JOIN users u           ON u.id  = s.user_id
        WHERE 1=1";
$params = [];
if ($statusFilter) { $sql .= " AND dr.status=?"; $params[] = $statusFilter; }
if ($search) {
    $sql .= " AND (dr.reference_no LIKE ? OR u.full_name LIKE ? OR s.student_number LIKE ?)";
    $params = array_merge($params,["%$search%","%$search%","%$search%"]);
}
$sql .= " ORDER BY dr.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Summary counts
$counts = [];
foreach (['submitted','pending','processing','ready_for_pickup','completed','rejected'] as $s) {
    $c = $pdo->prepare("SELECT COUNT(*) FROM document_requests WHERE status=?");
    $c->execute([$s]);
    $counts[$s] = (int)$c->fetchColumn();
}

$navItems = getNavItems('admin', 'Requests');
$pageTitle  = 'Document Requests — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Admin'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Document Requests Monitor</div>

<!-- Summary stats -->
<div class="stats-row">
  <?php
  $statDef = [
    'submitted' => ['⏳','badge-gold'],
    'processing'=> ['⚙️','badge-teal'],
    'ready_for_pickup'=>['📦','badge-purple'],
    'completed' => ['✅','badge-green'],
    'rejected'  => ['❌','badge-red'],
  ];
  foreach ($statDef as $s=>[$icon,$cls]):
  ?>
  <div class="stat-card" style="cursor:pointer;" onclick="location='?status=<?= $s ?>'">
    <div><div class="stat-label"><?= ucfirst(str_replace('_',' ',$s)) ?></div><div class="stat-num"><?= $counts[$s] + ($counts['pending']??0 && $s==='submitted' ? $counts['pending'] : 0) ?></div></div>
    <span style="font-size:1.6rem;"><?= $icon ?></span>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filter + search -->
<form method="GET" class="mgmt-controls">
  <div class="search-box">
    <span>🔍</span>
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Reference no., student name…">
  </div>
  <select name="status" class="filter-select" onchange="this.form.submit()">
    <option value="">All Statuses</option>
    <?php foreach (array_keys($counts) as $s): ?>
      <option value="<?= $s ?>" <?= $statusFilter===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="btn-add">Search</button>
  <?php if ($statusFilter): ?><a href="?" class="btn-add" style="background:var(--bg-secondary);color:var(--text-main);text-decoration:none;">Clear Filter</a><?php endif; ?>
</form>

<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr><th>Ref No.</th><th>Student</th><th>Document</th><th>Copies</th><th>Fee</th><th>Payment</th><th>Status</th><th>Date</th></tr>
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
          <?php $pc=match($r['payment_status']){'paid'=>'badge-green','unpaid'=>'badge-red','waived'=>'badge-neutral',default=>'badge-neutral'}; ?>
          <span class="badge <?= $pc ?>"><?= ucfirst($r['payment_status']) ?></span>
        </td>
        <td>
          <?php $sc=match($r['status']){'submitted','pending'=>'badge-gold','processing'=>'badge-teal','ready_for_pickup'=>'badge-purple','completed'=>'badge-green','rejected'=>'badge-red',default=>'badge-neutral'}; ?>
          <span class="badge <?= $sc ?>"><?= ucfirst(str_replace('_',' ',$r['status'])) ?></span>
        </td>
        <td class="small"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($requests)): ?>
      <tr><td colspan="8" class="text-center text-muted" style="padding:20px;">No requests found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
