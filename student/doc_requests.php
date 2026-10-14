<?php
/**
 * ═══════════════════════════════════════════════════════════
 * STUDENT DOCUMENT REQUESTS — student/doc_requests.php
 * ═══════════════════════════════════════════════════════════
 * Rate limiting: max 5 document-request submissions per student
 * per 60-minute rolling window (session-backed).
 */
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('student');

// ── Rate-limiting constants ────────────────────────────────
const DOC_REQ_RATE_MAX    = 5;    // max submissions in the window
const DOC_REQ_RATE_WINDOW = 3600; // 60 minutes in seconds

$pdo  = getDB();
$user = currentUser();
$sid  = (int)($user['student_db_id'] ?? 0);

if (!$sid) {
    redirectWith('student/dashboard.php', 'Student profile not found.', 'error');
}

// Handle new request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ── Rate-limit check ──────────────────────────────────
    // Use a per-student session bucket keyed by their DB id so
    // students sharing a browser can't reset each other's counters.
    $bucketKey   = 'rl_docreq_' . $sid;
    $bucketTsKey = 'rl_docreq_ts_' . $sid;

    // Reset the bucket if it doesn't exist or the window has expired.
    if (
        !isset($_SESSION[$bucketKey]) ||
        (time() - ($_SESSION[$bucketTsKey] ?? 0)) > DOC_REQ_RATE_WINDOW
    ) {
        $_SESSION[$bucketKey]   = 0;
        $_SESSION[$bucketTsKey] = time();
    }

    $_SESSION[$bucketKey]++;

    if ($_SESSION[$bucketKey] > DOC_REQ_RATE_MAX) {
        $remaining = DOC_REQ_RATE_WINDOW - (time() - $_SESSION[$bucketTsKey]);
        $minutes   = (int)ceil($remaining / 60);
        redirectWith(
            'student/doc_requests.php',
            "Too many requests submitted. Please wait {$minutes} minute(s) before submitting again.",
            'error'
        );
    }
    $docTypeId = (int)($_POST['document_type_id'] ?? 0);
    $purpose   = trim($_POST['purpose'] ?? '');
    $copies    = max(1, (int)($_POST['copies'] ?? 1));

    if ($docTypeId && $purpose) {
        // Get fee from document_types
        $docType = $pdo->prepare("SELECT id, name, fee FROM document_types WHERE id=? AND is_active=1");
        $docType->execute([$docTypeId]);
        $docTypeRow = $docType->fetch();

        if ($docTypeRow) {
            // Generate reference number
            $refNo = 'REQ-' . strtoupper(substr(md5(uniqid()), 0, 8));

            $feeAmount = $docTypeRow['fee'] * $copies;

            $pdo->prepare(
                "INSERT INTO document_requests
                 (reference_no, student_id, document_type_id, purpose, copies, fee_amount, status, payment_status)
                 VALUES (?, ?, ?, ?, ?, ?, 'submitted', 'unpaid')"
            )->execute([$refNo, $sid, $docTypeId, $purpose, $copies, $feeAmount]);

            redirectWith('student/doc_requests.php', "Request submitted! Reference: $refNo");
        } else {
            redirectWith('student/doc_requests.php', 'Invalid document type.', 'error');
        }
    } else {
        redirectWith('student/doc_requests.php', 'Please fill in all required fields.', 'error');
    }
}

// Load active document types
$docTypes = $pdo->query("SELECT id, name, fee, description FROM document_types WHERE is_active=1 ORDER BY name")->fetchAll();

// Load student's requests
$myRequests = $pdo->prepare(
    "SELECT dr.id, dr.reference_no, dr.purpose, dr.copies, dr.fee_amount,
            dr.status, dr.payment_status, dr.staff_remarks, dr.created_at, dr.updated_at,
            dt.name AS doc_type
     FROM document_requests dr
     JOIN document_types dt ON dt.id = dr.document_type_id
     WHERE dr.student_id = ?
     ORDER BY dr.created_at DESC"
);
$myRequests->execute([$sid]);
$requests = $myRequests->fetchAll();

$navItems = getNavItems('student', 'Doc Requests');
$pageTitle  = 'Document Requests — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Student'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Document Requests</div>

<div class="two-col">
  <!-- Request form -->
  <div>
    <div class="panel mb-16">
      <div class="panel-header">Submit New Request</div>
      <form method="POST" style="padding:18px 20px;">
        <div style="margin-bottom:10px;">
          <label class="stat-label" style="display:block;margin-bottom:5px;">DOCUMENT TYPE</label>
          <select name="document_type_id" class="modal-input mb-0" required id="doc-type-select" onchange="updateFee(this)">
            <option value="">— Select Document —</option>
            <?php foreach ($docTypes as $dt): ?>
              <option value="<?= $dt['id'] ?>" data-fee="<?= $dt['fee'] ?>">
                <?= htmlspecialchars($dt['name']) ?> — ₱<?= number_format($dt['fee'],2) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="margin-bottom:10px;">
          <label class="stat-label" style="display:block;margin-bottom:5px;">NUMBER OF COPIES</label>
          <input type="number" name="copies" min="1" max="10" value="1" class="modal-input mb-0" id="copies-input" oninput="updateFee(document.getElementById('doc-type-select'))">
        </div>
        <div style="margin-bottom:10px;">
          <div class="stat-label" style="margin-bottom:3px;">ESTIMATED FEE</div>
          <div id="fee-display" style="font-family:'Caveat',cursive;font-size:1.6rem;font-weight:700;color:var(--accent-dark);">₱0.00</div>
          <div class="small text-muted">Payment will be processed at the cashier's office.</div>
        </div>
        <div style="margin-bottom:12px;">
          <label class="stat-label" style="display:block;margin-bottom:5px;">PURPOSE</label>
          <textarea name="purpose" class="modal-input mb-0" rows="3" required placeholder="State the purpose of this request…"></textarea>
        </div>
        <button type="submit" class="btn-submit">Submit Request</button>
      </form>
    </div>
  </div>

  <!-- Status legend -->
  <div class="panel" style="height:fit-content;">
    <div class="panel-header">Status Guide</div>
    <div style="padding:16px;">
      <?php
      $statusGuide = [
        'submitted'       => ['badge-gold',   'Submitted',       'Your request has been received.'],
        'pending'         => ['badge-gold',   'Pending',         'Being reviewed by the registrar.'],
        'processing'      => ['badge-teal',   'Processing',      'Your document is being prepared.'],
        'ready_for_pickup'=> ['badge-purple', 'Ready for Pickup','Go to the registrar to claim.'],
        'completed'       => ['badge-green',  'Completed',       'Request fulfilled and claimed.'],
        'rejected'        => ['badge-red',    'Rejected',        'Request was rejected. See remarks.'],
      ];
      foreach ($statusGuide as $s => [$cls, $label, $desc]):
      ?>
      <div style="display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);">
        <span class="badge <?= $cls ?>" style="flex-shrink:0;"><?= $label ?></span>
        <span class="small text-muted"><?= $desc ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- My Requests -->
<div class="panel">
  <div class="panel-header">My Request History (<?= count($requests) ?>)</div>
  <?php if (!empty($requests)): ?>
  <table class="data-table">
    <thead>
      <tr><th>Ref No.</th><th>Document</th><th>Copies</th><th>Fee</th><th>Payment</th><th>Status</th><th>Remarks</th><th>Date</th></tr>
    </thead>
    <tbody>
      <?php foreach ($requests as $r): ?>
      <tr>
        <td>
          <strong style="font-family:'Caveat',cursive;font-size:1.1rem;color:var(--accent-dark);">
            <?= htmlspecialchars($r['reference_no']) ?>
          </strong>
        </td>
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
        <td class="small text-muted"><?= htmlspecialchars($r['staff_remarks'] ?? '—') ?></td>
        <td class="small"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
  <div style="padding:32px;text-align:center;color:var(--text-light);">
    No requests yet. Use the form above to submit your first request.
  </div>
  <?php endif; ?>
</div>

<script>
function updateFee(sel) {
  const fee    = parseFloat(sel.options[sel.selectedIndex]?.dataset.fee || 0);
  const copies = parseInt(document.getElementById('copies-input')?.value || 1);
  const total  = fee * copies;
  document.getElementById('fee-display').textContent = '₱' + total.toFixed(2);
}
</script>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
