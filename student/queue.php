<?php
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/totp.php';
require_once __DIR__ . '/../partials/nav.php';
requireLogin('student');

$pdo  = getDB();
$user = currentUser();
$sid  = (int)($user['student_db_id'] ?? 0);

$period = '2025-2026-2';

// Student's own queue entry (join students to get totp_secret)
$myQueue = $pdo->prepare(
    "SELECT eq.*, s.totp_secret
     FROM enrollment_queue eq
     JOIN students s ON s.id = eq.student_id
     WHERE eq.student_id=? AND eq.enrollment_period=?
     ORDER BY eq.created_at DESC LIMIT 1"
);
$myQueue->execute([$sid, $period]);
$myEntry = $myQueue->fetch();

// If the student has no TOTP secret yet, generate and persist one now.
$totpSecret = $myEntry['totp_secret'] ?? null;
if ($totpSecret === null) {
    $totpSecret = generate_totp_secret();
    $pdo->prepare("UPDATE students SET totp_secret=? WHERE id=?")
        ->execute([$totpSecret, $sid]);
}

// Currently serving
$serving = $pdo->prepare(
    "SELECT eq.queue_number, u.full_name AS student_name
     FROM enrollment_queue eq
     JOIN students s ON s.id = eq.student_id
     JOIN users u    ON u.id = s.user_id
     WHERE eq.status='serving' AND eq.enrollment_period=?
     ORDER BY eq.called_at DESC LIMIT 1"
);
$serving->execute([$period]);
$servingNow = $serving->fetch();

// How many ahead
$queuePosition = null;
if ($myEntry && $myEntry['status'] === 'waiting') {
    $pos = $pdo->prepare(
        "SELECT COUNT(*) FROM enrollment_queue
         WHERE status='waiting' AND enrollment_period=?
         AND created_at < ?"
    );
    $pos->execute([$period, $myEntry['created_at']]);
    $queuePosition = (int)$pos->fetchColumn() + 1;
}

// Total waiting
$wStmt = $pdo->prepare("SELECT COUNT(*) FROM enrollment_queue WHERE status='waiting' AND enrollment_period=?");
$wStmt->execute([$period]);
$totalWaiting = (int)$wStmt->fetchColumn();

// Is this student currently being served? Pre-compute for the view.
$isServing = $myEntry && (
    $myEntry['status'] === 'serving' ||
    ($myEntry['status'] === 'waiting' && !empty($myEntry['called_at']))
);

$navItems   = getNavItems('student', 'Queue');
$pageTitle  = 'Enrollment Queue — Horizon Academy';
$topbarUser = ['name' => $user['full_name'], 'role' => 'Student'];
include __DIR__ . '/../partials/layout_start.php';
?>

<?php if ($isServing): ?>
<!-- Auto-reload every 30 seconds while being served so the OTP code stays current. -->
<script>setTimeout(function(){ location.reload(); }, 30000);</script>
<?php endif; ?>

<div class="content-title">Enrollment Queue</div>

<!-- Now Serving Display -->
<div class="queue-display mb-16">
  <div class="queue-label">Now Serving</div>
  <div class="queue-number-large queue-status-serving">
    <?= $servingNow ? htmlspecialchars($servingNow['queue_number']) : '—' ?>
  </div>
  <?php if ($servingNow): ?>
  <div style="font-size:.88rem;color:var(--text-light);margin-top:6px;">
    <?= htmlspecialchars($servingNow['student_name']) ?>
  </div>
  <?php else: ?>
  <div style="font-size:.88rem;color:var(--text-light);margin-top:6px;">No student currently being served</div>
  <?php endif; ?>
  <div style="margin-top:12px;font-size:.82rem;color:var(--text-light);">
    <strong><?= $totalWaiting ?></strong> student<?= $totalWaiting!==1?'s':'' ?> in the queue
  </div>
</div>

<!-- Student's Own Queue Status -->
<div class="panel mb-16">
  <div class="panel-header">Your Queue Status</div>
  <div style="padding:24px;text-align:center;">
    <?php if ($myEntry): ?>
      <?php if ($myEntry['status'] === 'waiting'): ?>
        <div style="font-family:'Caveat',cursive;font-size:4rem;font-weight:700;color:var(--gold);">
          <?= htmlspecialchars($myEntry['queue_number']) ?>
        </div>
        <div class="badge badge-gold" style="font-size:.82rem;margin-top:8px;">Waiting</div>
        <div style="margin-top:14px;font-size:.92rem;color:var(--text-mid);">
          You are <strong>#<?= $queuePosition ?></strong> in the queue
        </div>
        <div style="font-size:.8rem;color:var(--text-light);margin-top:4px;"><?= htmlspecialchars($myEntry['category']) ?></div>
        <div style="font-size:.78rem;color:var(--text-light);margin-top:4px;">
          Added <?= date('h:i A', strtotime($myEntry['created_at'])) ?>
        </div>

        <?php if (!empty($myEntry['called_at'])): ?>
        <!-- ── TOTP Presence Verification (called, awaiting OTP) ── -->
        <div style="margin-top:24px;padding:20px;background:var(--bg-secondary);border-radius:var(--radius);border:2px solid var(--gold);">
          <div class="stat-label" style="margin-bottom:8px;">YOUR VERIFICATION CODE</div>
          <div style="font-family:'Caveat',cursive;font-size:4.5rem;font-weight:700;color:var(--gold);letter-spacing:0.15em;line-height:1;">
            <?= htmlspecialchars(get_totp_code($totpSecret)) ?>
          </div>
          <div style="font-size:.82rem;color:var(--text-light);margin-top:8px;">
            🔄 This code refreshes every 30 seconds
          </div>
          <div style="margin-top:12px;padding:8px 14px;background:#fff3cd;border:1px solid #ffc107;border-radius:var(--radius-sm);font-size:.82rem;color:#856404;font-weight:600;">
            ⚠️ Do not share this code with anyone
          </div>
        </div>
        <!-- ── end TOTP ──────────────────────────────────────── -->
        <?php endif; ?>

      <?php elseif ($myEntry['status'] === 'serving'): ?>
        <div style="font-family:'Caveat',cursive;font-size:4rem;font-weight:700;color:var(--green);">
          <?= htmlspecialchars($myEntry['queue_number']) ?>
        </div>
        <div class="badge badge-green" style="font-size:.82rem;margin-top:8px;">Now Being Served!</div>
        <div style="margin-top:14px;font-size:.92rem;color:var(--green);font-weight:700;">
          🔔 Please proceed to the enrollment window.
        </div>

        <!-- ── TOTP Presence Verification ─────────────────────── -->
        <div style="margin-top:24px;padding:20px;background:var(--bg-secondary);border-radius:var(--radius);border:2px solid var(--green);">
          <div class="stat-label" style="margin-bottom:8px;">YOUR VERIFICATION CODE</div>
          <div style="font-family:'Caveat',cursive;font-size:4.5rem;font-weight:700;color:var(--green);letter-spacing:0.15em;line-height:1;">
            <?= htmlspecialchars(get_totp_code($totpSecret)) ?>
          </div>
          <div style="font-size:.82rem;color:var(--text-light);margin-top:8px;">
            🔄 This code refreshes every 30 seconds
          </div>
          <div style="margin-top:12px;padding:8px 14px;background:#fff3cd;border:1px solid #ffc107;border-radius:var(--radius-sm);font-size:.82rem;color:#856404;font-weight:600;">
            ⚠️ Do not share this code with anyone
          </div>
        </div>
        <!-- ── end TOTP ──────────────────────────────────────── -->

      <?php elseif ($myEntry['status'] === 'completed'): ?>
        <div style="font-size:3rem;margin-bottom:8px;">✅</div>
        <div class="badge badge-neutral" style="font-size:.82rem;">Served</div>
        <div style="margin-top:14px;font-size:.88rem;color:var(--text-mid);">
          Your enrollment session (<?= htmlspecialchars($myEntry['queue_number']) ?>) has been completed.
        </div>

      <?php elseif ($myEntry['status'] === 'skipped'): ?>
        <div style="font-size:3rem;margin-bottom:8px;">⏭️</div>
        <div class="badge badge-red" style="font-size:.82rem;">Skipped</div>
        <div style="margin-top:14px;font-size:.88rem;color:var(--text-mid);">
          Your queue entry was skipped. Please approach the registrar.
        </div>
      <?php endif; ?>

    <?php else: ?>
      <div style="font-size:2.5rem;margin-bottom:12px;">🔢</div>
      <div style="font-size:.92rem;color:var(--text-mid);">You are not currently in the enrollment queue.</div>
      <div style="font-size:.82rem;color:var(--text-light);margin-top:8px;">
        Please visit the Registrar's office to get a queue number.
      </div>
    <?php endif; ?>
  </div>
</div>

<div class="panel">
  <div class="panel-header">Queue Information</div>
  <div style="padding:16px;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <div style="padding:12px;background:var(--bg-secondary);border-radius:var(--radius-sm);text-align:center;">
        <div class="stat-label">Enrollment Period</div>
        <div style="font-weight:700;margin-top:4px;">A.Y. 2025-2026, 2nd Semester</div>
      </div>
      <div style="padding:12px;background:var(--bg-secondary);border-radius:var(--radius-sm);text-align:center;">
        <div class="stat-label">Currently Waiting</div>
        <div style="font-family:'Caveat',cursive;font-size:1.8rem;font-weight:700;color:var(--gold);"><?= $totalWaiting ?></div>
      </div>
    </div>
    <div class="divider"></div>
    <div class="small text-muted">
      To join the enrollment queue, please visit the Registrar's office with your student ID and required documents.
    </div>
  </div>
</div>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
