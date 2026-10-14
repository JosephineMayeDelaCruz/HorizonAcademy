<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('registrar');

$pdo  = getDB();
$user = currentUser();

$search    = trim($_GET['search'] ?? '');
$studentId = (int)($_GET['student_id'] ?? 0);

// If specific student, show their clearance detail
if ($studentId) {
    $studentInfo = $pdo->prepare(
        "SELECT s.id, s.student_number, s.program, s.section, u.full_name, u.email
         FROM students s JOIN users u ON u.id=s.user_id WHERE s.id=?"
    );
    $studentInfo->execute([$studentId]);
    $studentInfo = $studentInfo->fetch();

    $clearanceItems = $pdo->prepare(
        "SELECT cr.id AS req_id, cr.requirement, d.name AS dept_name, d.code AS dept_code,
                COALESCE(sc.status,'pending') AS status,
                sc.remarks, sc.reviewed_at,
                ru.full_name AS reviewed_by
         FROM clearance_requirements cr
         JOIN departments d ON d.id = cr.department_id
         LEFT JOIN student_clearance sc ON sc.requirement_id = cr.id AND sc.student_id=?
         LEFT JOIN users ru ON ru.id = sc.reviewed_by
         WHERE cr.school_year='2025-2026' AND cr.semester=2
         ORDER BY d.name, cr.requirement"
    );
    $clearanceItems->execute([$studentId]);
    $clearance = $clearanceItems->fetchAll();

    $total    = count($clearance);
    $approved = count(array_filter($clearance, fn($c) => $c['status'] === 'approved'));

    $navItems = getNavItems('registrar', 'Clearance');
    $pageTitle  = 'Clearance Detail — Horizon Academy';
    $topbarUser = ['name' => $user['full_name'], 'role' => 'Registrar'];
    include __DIR__ . '/../partials/layout_start.php';
    ?>
    <div style="margin-bottom:14px;">
      <a href="<?= url('registrar/clearance.php') ?>" class="btn-go-back">← Back to Clearance List</a>
    </div>

    <div class="student-profile-card mb-16">
      <div class="student-avatar">🎓</div>
      <div>
        <div class="student-name"><?= htmlspecialchars($studentInfo['full_name']) ?></div>
        <div class="student-meta">
          <span><?= htmlspecialchars($studentInfo['student_number']) ?></span>
          <span><?= htmlspecialchars($studentInfo['program']) ?> — <?= htmlspecialchars($studentInfo['section']) ?></span>
        </div>
      </div>
      <div style="margin-left:auto;text-align:right;">
        <div style="font-family:'Caveat',cursive;font-size:1.8rem;font-weight:700;color:<?= $total && $approved === $total ? 'var(--green)' : 'var(--gold)' ?>;">
          <?= $approved ?>/<?= $total ?>
        </div>
        <div class="stat-label">Requirements Cleared</div>
        <div class="progress-bar-wrap" style="width:140px;margin-top:6px;">
          <div class="progress-bar-fill" style="width:<?= $total ? round($approved/$total*100) : 0 ?>%;"></div>
        </div>
      </div>
    </div>

    <?php
    $grouped = [];
    foreach ($clearance as $item) {
        $grouped[$item['dept_name']][] = $item;
    }
    foreach ($grouped as $dept => $items):
    $deptApproved = count(array_filter($items, fn($i) => $i['status']==='approved'));
    $deptTotal    = count($items);
    ?>
    <div class="clearance-dept mb-16">
      <div class="clearance-dept-name">
        <span><?= htmlspecialchars($dept) ?></span>
        <span class="badge <?= $deptApproved===$deptTotal ? 'badge-green' : 'badge-gold' ?>">
          <?= $deptApproved ?>/<?= $deptTotal ?> Cleared
        </span>
      </div>
      <?php foreach ($items as $item): ?>
      <div class="clearance-item">
        <span class="clearance-dot clearance-dot-<?= $item['status'] ?>"></span>
        <span style="flex:1;"><?= htmlspecialchars($item['requirement']) ?></span>
        <span class="badge <?= match($item['status']){'approved'=>'badge-green','rejected'=>'badge-red',default=>'badge-gold'} ?>">
          <?= ucfirst($item['status']) ?>
        </span>
        <?php if ($item['remarks']): ?>
        <span class="small text-muted" style="margin-left:8px;"><?= htmlspecialchars($item['remarks']) ?></span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <?php include __DIR__ . '/../partials/layout_end.php'; ?>
    <?php
    exit;
}

// Clearance list view — students with completion summary
$sql = "SELECT s.id, s.student_number, s.program, s.section, u.full_name,
               COUNT(cr.id) AS total_reqs,
               SUM(CASE WHEN sc.status='approved' THEN 1 ELSE 0 END) AS approved
        FROM students s
        JOIN users u ON u.id = s.user_id
        CROSS JOIN clearance_requirements cr
          ON cr.school_year='2025-2026' AND cr.semester=2
        LEFT JOIN student_clearance sc
          ON sc.student_id = s.id AND sc.requirement_id = cr.id
        WHERE 1=1";
$params = [];
if ($search) {
    $sql .= " AND (u.full_name LIKE ? OR s.student_number LIKE ?)";
    $params = ["%$search%","%$search%"];
}
$sql .= " GROUP BY s.id ORDER BY u.full_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$navItems = getNavItems('registrar', 'Clearance');
$pageTitle  = 'Clearance Monitor — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Registrar'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Clearance Monitor</div>

<form method="GET" class="mgmt-controls">
  <div class="search-box">
    <span>🔍</span>
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search student…">
  </div>
  <button type="submit" class="btn-add">Search</button>
</form>

<div class="table-wrap">
  <table class="data-table">
    <thead>
      <tr><th>Student</th><th>Student No.</th><th>Program</th><th>Section</th><th>Progress</th><th>Status</th><th>Action</th></tr>
    </thead>
    <tbody>
      <?php foreach ($students as $s):
        $pct = $s['total_reqs'] ? round($s['approved']/$s['total_reqs']*100) : 0;
        $cleared = $s['approved'] == $s['total_reqs'];
      ?>
      <tr>
        <td><?= htmlspecialchars($s['full_name']) ?></td>
        <td><?= htmlspecialchars($s['student_number']) ?></td>
        <td><?= htmlspecialchars($s['program']) ?></td>
        <td><?= htmlspecialchars($s['section']) ?></td>
        <td>
          <div style="display:flex;align-items:center;gap:8px;">
            <div class="progress-bar-wrap" style="width:80px;"><div class="progress-bar-fill" style="width:<?= $pct ?>%;"></div></div>
            <span class="small"><?= $s['approved'] ?>/<?= $s['total_reqs'] ?></span>
          </div>
        </td>
        <td>
          <span class="badge <?= $cleared ? 'badge-green' : ($pct>0?'badge-teal':'badge-neutral') ?>">
            <?= $cleared ? 'Cleared' : ($pct>0?'In Progress':'Not Started') ?>
          </span>
        </td>
        <td>
          <a href="?student_id=<?= $s['id'] ?>" class="btn-edit" style="text-decoration:none;">View</a>
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
