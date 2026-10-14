<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('admin');

$pdo  = getDB();
$user = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $fee  = (float)($_POST['fee'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        if ($name) {
            try {
                $pdo->prepare("INSERT INTO document_types (name,fee,description) VALUES (?,?,?)")
                    ->execute([$name, $fee, $desc ?: null]);
                redirectWith('admin/doc_types.php', 'Document type added.');
            } catch (Exception $e) {
                redirectWith('admin/doc_types.php', 'Error: ' . $e->getMessage(), 'error');
            }
        }

    } elseif ($action === 'edit') {
        $id   = (int)($_POST['type_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $fee  = (float)($_POST['fee'] ?? 0);
        $desc = trim($_POST['description'] ?? '');
        $active = (int)($_POST['is_active'] ?? 1);
        if ($id && $name) {
            $pdo->prepare("UPDATE document_types SET name=?,fee=?,description=?,is_active=? WHERE id=?")
                ->execute([$name,$fee,$desc?:null,$active,$id]);
            redirectWith('admin/doc_types.php', 'Updated.');
        }

    } elseif ($action === 'toggle') {
        $id = (int)($_POST['type_id'] ?? 0);
        if ($id) {
            $pdo->prepare("UPDATE document_types SET is_active = NOT is_active WHERE id=?")->execute([$id]);
            redirectWith('admin/doc_types.php', 'Status toggled.');
        }
    }
}

$docTypes = $pdo->query(
    "SELECT dt.*, COUNT(dr.id) AS usage_count
     FROM document_types dt
     LEFT JOIN document_requests dr ON dr.document_type_id = dt.id
     GROUP BY dt.id ORDER BY dt.name"
)->fetchAll();

$navItems = getNavItems('admin', 'Doc Types');
$pageTitle  = 'Document Types — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Admin'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Document Types Configuration</div>

<div class="mgmt-controls">
  <button type="button" class="btn-add" onclick="document.getElementById('modal-add').style.display='flex'">+ Add Document Type</button>
</div>

<div class="table-wrap">
  <table class="data-table">
    <thead><tr><th>Name</th><th>Fee</th><th>Description</th><th>Status</th><th>Requests</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($docTypes as $dt): ?>
      <tr>
        <td><strong><?= htmlspecialchars($dt['name']) ?></strong></td>
        <td>₱<?= number_format($dt['fee'],2) ?></td>
        <td class="small text-muted"><?= htmlspecialchars($dt['description'] ?? '—') ?></td>
        <td><span class="badge <?= $dt['is_active']?'badge-green':'badge-neutral' ?>"><?= $dt['is_active']?'Active':'Inactive' ?></span></td>
        <td><?= $dt['usage_count'] ?></td>
        <td>
          <div class="action-stack">
            <button class="btn-edit" onclick="openEditModal(<?= $dt['id'] ?>,'<?= addslashes($dt['name']) ?>',<?= $dt['fee'] ?>,'<?= addslashes($dt['description']??'') ?>',<?= $dt['is_active'] ?>)">Edit</button>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="type_id" value="<?= $dt['id'] ?>">
              <button type="submit" class="btn-edit" style="background:<?= $dt['is_active']?'var(--red)':'var(--green)' ?>;">
                <?= $dt['is_active']?'Disable':'Enable' ?>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Add Modal -->
<div id="modal-add" class="modal-overlay" style="display:none;">
  <div class="modal">
    <h3>Add Document Type</h3>
    <form method="POST">
      <input type="hidden" name="action" value="add">
      <input class="modal-input" name="name" placeholder="Document Name" required>
      <input class="modal-input" name="fee" type="number" step="0.01" min="0" placeholder="Fee Amount (₱)" value="0">
      <textarea class="modal-input" name="description" rows="2" placeholder="Description (optional)"></textarea>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="document.getElementById('modal-add').style.display='none'">Cancel</button>
        <button type="submit" class="btn-confirm">Add</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="modal-edit" class="modal-overlay" style="display:none;">
  <div class="modal">
    <h3>Edit Document Type</h3>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="type_id" id="edit-tid">
      <input class="modal-input" name="name" id="edit-name" placeholder="Document Name" required>
      <input class="modal-input" name="fee" id="edit-fee" type="number" step="0.01" min="0" placeholder="Fee">
      <textarea class="modal-input" name="description" id="edit-desc" rows="2" placeholder="Description"></textarea>
      <select class="modal-input" name="is_active" id="edit-active">
        <option value="1">Active</option>
        <option value="0">Inactive</option>
      </select>
      <div class="modal-actions">
        <button type="button" class="btn-cancel" onclick="document.getElementById('modal-edit').style.display='none'">Cancel</button>
        <button type="submit" class="btn-confirm">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openEditModal(id, name, fee, desc, active) {
  document.getElementById('edit-tid').value    = id;
  document.getElementById('edit-name').value   = name;
  document.getElementById('edit-fee').value    = fee;
  document.getElementById('edit-desc').value   = desc;
  document.getElementById('edit-active').value = active;
  document.getElementById('modal-edit').style.display = 'flex';
}
document.querySelectorAll('.modal-overlay').forEach(m =>
  m.addEventListener('click', e => { if (e.target === m) m.style.display = 'none'; })
);
</script>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
