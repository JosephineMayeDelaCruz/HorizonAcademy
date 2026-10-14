<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/totp.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('registrar');

$pdo  = getDB();
$user = currentUser();

$period = '2025-2026-2';

// ── POST actions ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── AJAX: verify OTP ──────────────────────────────────────────────────
    if ($action === 'verify_otp') {
        header('Content-Type: application/json');

        $studentId = (int)($_POST['student_id'] ?? 0);
        $otpCode   = trim($_POST['otp_code'] ?? '');

        if (!$studentId || !$otpCode) {
            echo json_encode(['success' => false, 'message' => 'Missing student ID or code.']);
            exit;
        }

        // Fetch the student's TOTP secret.
        $secretStmt = $pdo->prepare("SELECT totp_secret FROM students WHERE id=? LIMIT 1");
        $secretStmt->execute([$studentId]);
        $row = $secretStmt->fetch();

        if (!$row || $row['totp_secret'] === null) {
            echo json_encode(['success' => false, 'message' => 'No verification secret found for this student.']);
            exit;
        }

        if (verify_totp_code($row['totp_secret'], $otpCode)) {
            // Mark the student as serving (they were already called/waiting → serving transition
            // happened via call_next; this confirms physical presence).
            $pdo->prepare(
                "UPDATE enrollment_queue
                 SET status='serving', called_by=?, called_at=NOW()
                 WHERE student_id=? AND status='waiting' AND enrollment_period=?"
            )->execute([$user['id'], $studentId, $period]);

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid or expired code.']);
        }
        exit;
    }

    // ── add ───────────────────────────────────────────────────────────────
    if ($action === 'add') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $category  = trim($_POST['category'] ?? 'General Enrollment');

        if ($studentId) {
            // Check for existing active entry
            $exists = $pdo->prepare(
                "SELECT id FROM enrollment_queue
                 WHERE student_id=? AND enrollment_period=? AND status IN ('waiting','serving')"
            );
            $exists->execute([$studentId, $period]);
            if ($exists->fetch()) {
                redirectWith('registrar/queue.php', 'Student already has an active queue entry.', 'error');
            }

            // Generate queue number: Q + zero-padded count
            $cntStmt = $pdo->prepare("SELECT COUNT(*)+1 FROM enrollment_queue WHERE enrollment_period=?");
            $cntStmt->execute([$period]);
            $cnt  = (int)$cntStmt->fetchColumn();
            $qNum = 'Q' . str_pad($cnt, 4, '0', STR_PAD_LEFT);

            $pdo->prepare(
                "INSERT INTO enrollment_queue (queue_number, student_id, category, status, enrollment_period)
                 VALUES (?, ?, ?, 'waiting', ?)"
            )->execute([$qNum, $studentId, $category, $period]);
            redirectWith('registrar/queue.php', "Queue number $qNum assigned.");
        }

    // ── call_next ─────────────────────────────────────────────────────────
    } elseif ($action === 'call_next') {
        // Mark current serving as completed first
        $pdo->prepare(
            "UPDATE enrollment_queue SET status='completed', served_at=NOW()
             WHERE status='serving' AND enrollment_period=?"
        )->execute([$period]);

        // Call next waiting — leave status as 'waiting' so OTP verification
        // is required before the registrar can proceed. The verify_otp action
        // transitions them to 'serving' after the code is confirmed.
        $next = $pdo->prepare(
            "SELECT id, student_id FROM enrollment_queue
             WHERE status='waiting' AND enrollment_period=?
             ORDER BY created_at ASC LIMIT 1"
        );
        $next->execute([$period]);
        $nextRow = $next->fetch();

        if ($nextRow) {
            // Record who called them and when, but keep status='waiting' until OTP confirmed.
            $pdo->prepare(
                "UPDATE enrollment_queue SET called_by=?, called_at=NOW() WHERE id=?"
            )->execute([$user['id'], $nextRow['id']]);
            redirectWith('registrar/queue.php', 'Next student called — verify their OTP code to confirm presence.');
        } else {
            redirectWith('registrar/queue.php', 'No more students in the queue.', 'error');
        }

    // ── skip ─────────────────────────────────────────────────────────────
    } elseif ($action === 'skip') {
        $qid = (int)($_POST['queue_id'] ?? 0);
        if ($qid) {
            $pdo->prepare("UPDATE enrollment_queue SET status='skipped' WHERE id=?")->execute([$qid]);
            redirectWith('registrar/queue.php', 'Student skipped.');
        }

    // ── complete ──────────────────────────────────────────────────────────
    } elseif ($action === 'complete') {
        $qid = (int)($_POST['queue_id'] ?? 0);
        if ($qid) {
            $pdo->prepare("UPDATE enrollment_queue SET status='completed', served_at=NOW() WHERE id=?")->execute([$qid]);
            redirectWith('registrar/queue.php', 'Student marked as served.');
        }
    }
}

// ── Load queue ─────────────────────────────────────────────────────────────
$currentlyServing = $pdo->prepare(
    "SELECT eq.*, s.student_number, s.id AS student_db_id, u.full_name
     FROM enrollment_queue eq
     JOIN students s ON s.id = eq.student_id
     JOIN users u    ON u.id = s.user_id
     WHERE eq.status='serving' AND eq.enrollment_period=?
     ORDER BY eq.called_at DESC LIMIT 1"
);
$currentlyServing->execute([$period]);
$serving = $currentlyServing->fetch();

// Called but not yet OTP-verified (status still 'waiting' and called_at is set)
$calledStudent = $pdo->prepare(
    "SELECT eq.*, s.student_number, s.id AS student_db_id, u.full_name
     FROM enrollment_queue eq
     JOIN students s ON s.id = eq.student_id
     JOIN users u    ON u.id = s.user_id
     WHERE eq.status='waiting' AND eq.called_at IS NOT NULL AND eq.enrollment_period=?
     ORDER BY eq.called_at DESC LIMIT 1"
);
$calledStudent->execute([$period]);
$called = $calledStudent->fetch();

$waitingList = $pdo->prepare(
    "SELECT eq.*, s.student_number, u.full_name
     FROM enrollment_queue eq
     JOIN students s ON s.id = eq.student_id
     JOIN users u    ON u.id = s.user_id
     WHERE eq.status='waiting' AND eq.called_at IS NULL AND eq.enrollment_period=?
     ORDER BY eq.created_at ASC"
);
$waitingList->execute([$period]);
$waiting = $waitingList->fetchAll();

$completedToday = $pdo->prepare(
    "SELECT COUNT(*) FROM enrollment_queue
     WHERE status IN ('completed','skipped') AND enrollment_period=?
     AND DATE(created_at)=CURDATE()"
);
$completedToday->execute([$period]);
$doneToday = (int)$completedToday->fetchColumn();

// All students for dropdown
$allStudents = $pdo->query(
    "SELECT s.id, s.student_number, u.full_name
     FROM students s JOIN users u ON u.id = s.user_id
     ORDER BY u.full_name"
)->fetchAll();

$totalWaiting = count($waiting) + ($called ? 1 : 0);

$navItems   = getNavItems('registrar', 'Queue');
$pageTitle  = 'Enrollment Queue — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Registrar'];
include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Enrollment Queue Management</div>

<div class="stats-row">
  <div class="stat-card">
    <div><div class="stat-label">Now Serving</div>
    <div class="stat-num queue-status-serving"><?= $serving ? htmlspecialchars($serving['queue_number']) : '—' ?></div></div>
    <span style="font-size:2rem;opacity:.5;">🔔</span>
  </div>
  <div class="stat-card">
    <div><div class="stat-label">Waiting</div><div class="stat-num" style="color:var(--gold);"><?= $totalWaiting ?></div></div>
    <span style="font-size:2rem;opacity:.5;">⏳</span>
  </div>
  <div class="stat-card">
    <div><div class="stat-label">Served Today</div><div class="stat-num" style="color:var(--green);"><?= $doneToday ?></div></div>
    <span style="font-size:2rem;opacity:.5;">✅</span>
  </div>
</div>

<div class="two-col">
  <!-- Queue Control -->
  <div>
    <!-- Currently Serving -->
    <div class="queue-display mb-16">
      <div class="queue-label">Now Serving</div>
      <div class="queue-number-large queue-status-serving">
        <?= $serving ? htmlspecialchars($serving['queue_number']) : '—' ?>
      </div>
      <?php if ($serving): ?>
      <div style="font-size:.92rem;color:var(--text-mid);margin-top:8px;font-weight:700;">
        <?= htmlspecialchars($serving['full_name']) ?> (<?= htmlspecialchars($serving['student_number']) ?>)
      </div>
      <div style="font-size:.8rem;color:var(--text-light);margin-top:4px;"><?= htmlspecialchars($serving['category']) ?></div>
      <div class="divider"></div>
      <div style="display:flex;gap:10px;justify-content:center;">
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="complete">
          <input type="hidden" name="queue_id" value="<?= $serving['id'] ?>">
          <button type="submit" class="btn-add btn-green">Mark Served</button>
        </form>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action" value="skip">
          <input type="hidden" name="queue_id" value="<?= $serving['id'] ?>">
          <button type="submit" class="btn-add" style="background:var(--red);">Skip</button>
        </form>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── OTP Verification Panel (shown when a student has been called but not yet verified) -->
    <?php if ($called): ?>
    <div class="panel mb-16" style="border:2px solid var(--gold);">
      <div class="panel-header" style="background:var(--gold);color:#fff;">🔐 Verify Student Presence</div>
      <div style="padding:20px;text-align:center;">
        <div style="font-size:.92rem;color:var(--text-mid);margin-bottom:4px;">
          Called: <strong><?= htmlspecialchars($called['full_name']) ?></strong>
          (<?= htmlspecialchars($called['student_number']) ?>) — <?= htmlspecialchars($called['queue_number']) ?>
        </div>
        <div style="font-size:.82rem;color:var(--text-light);margin-bottom:16px;">
          Ask the student to show their 6-digit code from the student portal.
        </div>
        <div style="display:flex;gap:10px;justify-content:center;align-items:center;flex-wrap:wrap;">
          <input
            type="text"
            id="otp-input"
            inputmode="numeric"
            pattern="\d{6}"
            maxlength="6"
            placeholder="000000"
            autocomplete="one-time-code"
            style="font-family:'Caveat',cursive;font-size:2rem;font-weight:700;letter-spacing:0.2em;
                   width:160px;text-align:center;padding:10px 14px;border:2px solid var(--border);
                   border-radius:var(--radius-sm);background:var(--bg-secondary);color:var(--text-dark);"
          >
          <button
            type="button"
            id="otp-verify-btn"
            class="btn-submit"
            style="padding:10px 24px;font-size:.92rem;"
            onclick="verifyOtp(<?= (int)$called['student_db_id'] ?>)"
          >Verify</button>
        </div>
        <div id="otp-result" style="margin-top:14px;font-size:.88rem;font-weight:600;min-height:22px;"></div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Call Next -->
    <form method="POST" style="margin-bottom:16px;">
      <input type="hidden" name="action" value="call_next">
      <button type="submit" class="btn-submit btn-teal" style="font-size:1rem;padding:16px;"
        <?= $called ? 'disabled title="Verify the current student before calling the next one."' : '' ?>>
        🔔 Call Next Student
      </button>
    </form>
  </div>

  <!-- Right panel: Add to queue -->
  <div>
    <div class="panel" style="margin-bottom:16px;">
      <div class="panel-header">Add Student to Queue</div>
      <form method="POST" style="padding:16px;">
        <input type="hidden" name="action" value="add">
        <div style="margin-bottom:10px;">
          <label style="font-size:.72rem;font-weight:800;letter-spacing:1px;color:var(--text-mid);display:block;margin-bottom:5px;">STUDENT</label>
          <select name="student_id" class="modal-input mb-0" required>
            <option value="">— Select Student —</option>
            <?php foreach ($allStudents as $s): ?>
              <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name'] . ' (' . $s['student_number'] . ')') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="margin-bottom:12px;">
          <label style="font-size:.72rem;font-weight:800;letter-spacing:1px;color:var(--text-mid);display:block;margin-bottom:5px;">CATEGORY</label>
          <select name="category" class="modal-input mb-0">
            <option value="General Enrollment">General Enrollment</option>
            <option value="Transferee">Transferee</option>
            <option value="Returnee">Returnee</option>
            <option value="Cross-Enrollee">Cross-Enrollee</option>
          </select>
        </div>
        <button type="submit" class="btn-submit">Add to Queue</button>
      </form>
    </div>
  </div>
</div>

<!-- Waiting List -->
<div class="panel">
  <div class="panel-header">Waiting List (<?= $totalWaiting ?>)</div>
  <table class="data-table">
    <thead>
      <tr><th>#</th><th>Queue No.</th><th>Student</th><th>Category</th><th>Waiting Since</th><th>Action</th></tr>
    </thead>
    <tbody>
      <?php
      // Show the called-but-unverified student at the top of the list (highlighted)
      $displayWaiting = $called ? array_merge([$called], $waiting) : $waiting;
      foreach ($displayWaiting as $i => $q):
        $isCalled = $called && $q['id'] === $called['id'];
      ?>
      <tr <?= $isCalled ? 'style="background:rgba(200,160,0,.08);"' : '' ?>>
        <td><?= $i + 1 ?></td>
        <td>
          <strong style="color:var(--accent-dark);font-family:'Caveat',cursive;font-size:1.2rem;">
            <?= htmlspecialchars($q['queue_number']) ?>
          </strong>
          <?php if ($isCalled): ?>
            <span class="badge badge-gold" style="font-size:.68rem;margin-left:4px;">Called</span>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($q['full_name']) ?><br><small class="text-muted"><?= htmlspecialchars($q['student_number']) ?></small></td>
        <td><?= htmlspecialchars($q['category']) ?></td>
        <td><?= date('h:i A', strtotime($q['created_at'])) ?></td>
        <td>
          <form method="POST" style="display:inline">
            <input type="hidden" name="action" value="skip">
            <input type="hidden" name="queue_id" value="<?= $q['id'] ?>">
            <button type="submit" class="btn-remove" style="padding:5px 10px;font-size:.72rem;">Skip</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($displayWaiting)): ?>
      <tr><td colspan="6" class="text-center text-muted" style="padding:20px;">No students in the waiting queue.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
function verifyOtp(studentId) {
  const input  = document.getElementById('otp-input');
  const result = document.getElementById('otp-result');
  const btn    = document.getElementById('otp-verify-btn');
  const code   = input.value.trim();

  if (!/^\d{6}$/.test(code)) {
    result.textContent = '⚠️ Please enter a valid 6-digit code.';
    result.style.color = 'var(--red, #c0392b)';
    return;
  }

  btn.disabled       = true;
  result.textContent = 'Verifying…';
  result.style.color = 'var(--text-light)';

  const body = new URLSearchParams({
    action:     'verify_otp',
    student_id: studentId,
    otp_code:   code
  });

  fetch(window.location.href, { method: 'POST', body })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        result.textContent = '✅ Code verified — student confirmed present.';
        result.style.color = 'var(--green, #27ae60)';
        // Reload after a short delay so the serving panel updates.
        setTimeout(() => location.reload(), 1200);
      } else {
        result.textContent = '❌ ' + (data.message || 'Invalid or expired code.');
        result.style.color = 'var(--red, #c0392b)';
        btn.disabled = false;
        input.focus();
      }
    })
    .catch(() => {
      result.textContent = '❌ Network error — please try again.';
      result.style.color = 'var(--red, #c0392b)';
      btn.disabled = false;
    });
}

// Allow Enter key to trigger verification from the OTP input.
document.addEventListener('DOMContentLoaded', function () {
  const input = document.getElementById('otp-input');
  if (input) {
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') verifyOtp(<?= $called ? (int)$called['student_db_id'] : 'null' ?>);
    });
  }
});
</script>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
