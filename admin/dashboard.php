<?php
/**
 * ═══════════════════════════════════════════════════════════
 * ADMIN DASHBOARD
 * ═══════════════════════════════════════════════════════════
 * Overview of system statistics and recent academic activities
 * Uses INNER JOINs and LEFT JOINs to aggregate data
 */

// Import authentication functions and ensure only admins can access this page.
require_once __DIR__ . '/../config/auth.php';
// Import navigation helper to display the correct sidebar menu.
require_once __DIR__ . '/../partials/nav.php';
// Check if the current user is logged in and has admin role.
requireLogin('admin');

// Connect to the database and get the currently logged-in admin's information.
$pdo  = getDB();
$user = currentUser();

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// STATISTICS - Simple COUNT queries
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

// Query the database to count total number of students registered in the system.
$totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

// Query the database to count total number of faculty members.
$totalFaculty  = $pdo->query("SELECT COUNT(*) FROM users WHERE role='faculty'")->fetchColumn();

// Query the database to count total number of courses available.
$totalCourses  = $pdo->query("SELECT COUNT(*) FROM courses")->fetchColumn();

// Query the database to count all attendance records ever recorded.
$totalAttendance = $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();

// New module stats
$pendingDocRequests = $pdo->query("SELECT COUNT(*) FROM document_requests WHERE status IN ('submitted','pending','processing')")->fetchColumn();
$queueWaiting       = $pdo->query("SELECT COUNT(*) FROM enrollment_queue WHERE status='waiting'")->fetchColumn();

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// RECENT GRADES - Using INNER JOINs (4 tables)
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Query the 8 most recently graded courses by joining student, grade, and course tables.
$recentGrades = $pdo->query(
    "SELECT 
        u.full_name AS student_name,      -- Student name from users table
        c.course_code,                    -- Course code from courses table
        g.final_grade,                    -- Computed final grade (auto-calculated)
        g.remarks,                        -- Pass/Fail status (auto-calculated)
        g.updated_at                      -- When the grade was last updated
     FROM grades g
     INNER JOIN students s   ON s.id = g.student_id    -- Link grade to student
     INNER JOIN users u      ON u.id = s.user_id        -- Get student's full name
     INNER JOIN courses c    ON c.id = g.course_id      -- Get course details
     ORDER BY g.updated_at DESC LIMIT 8"
)->fetchAll();

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// SECTION PERFORMANCE - Using LEFT OUTER JOIN
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// Query performance statistics for each class section including pass/fail rates.
$sectionReport = $pdo->query(
    "SELECT 
        s.section,                                       -- Section name (e.g., 1-A, 2-B)
        COUNT(DISTINCT s.id) AS total_students,          -- Number of students in section
        ROUND(AVG(g.final_grade), 2) AS avg_grade,       -- Average grade for section
        SUM(CASE WHEN g.remarks='PASSED' THEN 1 ELSE 0 END) AS passed,  -- Count passed
        SUM(CASE WHEN g.remarks='FAILED' THEN 1 ELSE 0 END) AS failed   -- Count failed
     FROM students s
     LEFT JOIN grades g ON g.student_id = s.id          -- Include students with no grades
     GROUP BY s.section
     ORDER BY s.section"
)->fetchAll();

// 
// Load the admin navigation menu with Dashboard marked as active.
$navItems = getNavItems('admin', 'Dashboard');

// Set the browser tab title and prepare the top-right user information.
$pageTitle  = 'Admin Dashboard — Horizon Academy';
$topbarUser = ['name' => $user['full_name'] ?? 'Admin', 'role' => 'Administrator'];

include __DIR__ . '/../partials/layout_start.php';
?>

<div class="content-title">Admin Dashboard</div>

<div class="stats-row">
  <div class="stat-card">
    <div class="stat-left">
      <div class="stat-label">Total Students</div>
      <div class="stat-num"><?= number_format($totalStudents) ?></div>
    </div>
    <span style="font-size:2rem;opacity:0.6;">👨‍🎓</span>
  </div>
  <div class="stat-card">
    <div class="stat-left">
      <div class="stat-label">Total Faculty</div>
      <div class="stat-num"><?= number_format($totalFaculty) ?></div>
    </div>
    <span style="font-size:2rem;opacity:0.6;">👨‍🏫</span>
  </div>
  <div class="stat-card">
    <div class="stat-left">
      <div class="stat-label">Total Courses</div>
      <div class="stat-num"><?= number_format($totalCourses) ?></div>
    </div>
    <span style="font-size:2rem;opacity:0.6;">📚</span>
  </div>
  <div class="stat-card">
    <div class="stat-left">
      <div class="stat-label">Attendance Records</div>
      <div class="stat-num"><?= number_format($totalAttendance) ?></div>
    </div>
    <span style="font-size:2rem;opacity:0.6;">📅</span>
  </div>
  <div class="stat-card">
    <div class="stat-left">
      <div class="stat-label">Pending Requests</div>
      <div class="stat-num" style="color:var(--gold);"><?= number_format($pendingDocRequests) ?></div>
    </div>
    <span style="font-size:2rem;opacity:0.6;">📄</span>
  </div>
  <div class="stat-card">
    <div class="stat-left">
      <div class="stat-label">Queue Waiting</div>
      <div class="stat-num" style="color:var(--teal);"><?= number_format($queueWaiting) ?></div>
    </div>
    <span style="font-size:2rem;opacity:0.6;">🔢</span>
  </div>
</div>

<div class="two-col">
  <!-- Section Performance -->
  <div class="panel">
    <div class="panel-header">Section Performance Overview</div>
    <table class="notif-table">
      <thead><tr><td>Section</td><td>Students</td><td>Avg Grade</td><td>Passed</td><td>Failed</td></tr></thead>
      <tbody>
        <?php foreach ($sectionReport as $r): ?>
        <tr>
          <td><?= htmlspecialchars($r['section']) ?></td>
          <td><?= $r['total_students'] ?></td>
          <td><?= $r['avg_grade'] !== null ? number_format($r['avg_grade'],2) : '—' ?></td>
          <td style="color:#2a9d5c;font-weight:700;"><?= $r['passed'] ?></td>
          <td style="color:var(--red);font-weight:700;"><?= $r['failed'] ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($sectionReport)): ?>
        <tr><td colspan="5" style="text-align:center;color:var(--text-light);">No data yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Recent Grades -->
  <div class="panel">
    <div class="panel-header">Recently Submitted Grades</div>
    <table class="notif-table">
      <thead><tr><td>Student</td><td>Course</td><td>Grade</td></tr></thead>
      <tbody>
        <?php foreach ($recentGrades as $g): ?>
        <tr>
          <td><?= htmlspecialchars($g['student_name']) ?></td>
          <td><?= htmlspecialchars($g['course_code']) ?></td>
          <td style="font-weight:700;color:<?= $g['remarks']==='PASSED' ? 'var(--green)' : 'var(--red)' ?>">
            <?= number_format($g['final_grade'],2) ?>
            <small>(<?= $g['remarks'] ?>)</small>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($recentGrades)): ?>
        <tr><td colspan="3" style="text-align:center;color:var(--text-light);">No grades yet.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <div style="padding:10px 16px;">
      <a href="<?= url('admin/reports.php') ?>" class="btn-submit" style="text-decoration:none;display:block;text-align:center;">View Full Reports</a>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../partials/layout_end.php'; ?>
