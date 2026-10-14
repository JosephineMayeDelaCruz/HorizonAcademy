<?php
/**
 * ═══════════════════════════════════════════════════════════
 * CENTRALIZED NAVIGATION — All roles
 * ═══════════════════════════════════════════════════════════
 */

require_once __DIR__ . '/../config/paths.php';

$navDefinitions = [
    'admin' => [
        ['label' => 'Dashboard',  'path' => 'admin/dashboard.php',   'icon' => '🏠'],
        ['label' => 'Students',   'path' => 'admin/students.php',    'icon' => '👨‍🎓'],
        ['label' => 'Faculty',    'path' => 'admin/teachers.php',    'icon' => '👨‍🏫'],
        ['label' => 'Courses',    'path' => 'admin/courses.php',     'icon' => '📚'],
        ['label' => 'Users',      'path' => 'admin/users.php',       'icon' => '👥'],
        ['label' => 'Doc Types',  'path' => 'admin/doc_types.php',   'icon' => '📄'],
        ['label' => 'Requests',   'path' => 'admin/doc_requests.php','icon' => '📋'],
        ['label' => 'Queue',      'path' => 'admin/queue.php',       'icon' => '🔢'],
        ['label' => 'Reports',    'path' => 'admin/reports.php',     'icon' => '📈'],
    ],
    'faculty' => [
        ['label' => 'Dashboard',  'path' => 'faculty/dashboard.php',  'icon' => '🏠'],
        ['label' => 'Courses',    'path' => 'faculty/courses.php',    'icon' => '📚'],
        ['label' => 'Attendance', 'path' => 'faculty/attendance.php', 'icon' => '📅'],
        ['label' => 'Grades',     'path' => 'faculty/grades.php',     'icon' => '📊'],
        ['label' => 'Reports',    'path' => 'faculty/reports.php',    'icon' => '📈'],
    ],
    'student' => [
        ['label' => 'Dashboard',     'path' => 'student/dashboard.php',  'icon' => '🏠'],
        ['label' => 'My Attendance', 'path' => 'student/attendance.php', 'icon' => '📅'],
        ['label' => 'My Grades',     'path' => 'student/grades.php',     'icon' => '📊'],
        ['label' => 'Clearance',     'path' => 'student/clearance.php',  'icon' => '✅'],
        ['label' => 'Doc Requests',  'path' => 'student/doc_requests.php','icon'=> '📄'],
        ['label' => 'Queue',         'path' => 'student/queue.php',      'icon' => '🔢'],
    ],
    'registrar' => [
        ['label' => 'Dashboard',     'path' => 'registrar/dashboard.php',     'icon' => '🏠'],
        ['label' => 'Students',      'path' => 'registrar/students.php',      'icon' => '👨‍🎓'],
        ['label' => 'Queue',         'path' => 'registrar/queue.php',         'icon' => '🔢'],
        ['label' => 'Clearance',     'path' => 'registrar/clearance.php',     'icon' => '✅'],
        ['label' => 'Doc Requests',  'path' => 'registrar/doc_requests.php',  'icon' => '📄'],
        ['label' => 'Reports',       'path' => 'registrar/reports.php',       'icon' => '📈'],
    ],
    'cashier' => [
        ['label' => 'Dashboard',    'path' => 'cashier/dashboard.php',    'icon' => '🏠'],
        ['label' => 'Doc Requests', 'path' => 'cashier/doc_requests.php', 'icon' => '📄'],
        ['label' => 'Payments',     'path' => 'cashier/payments.php',     'icon' => '💰'],
    ],
    'department' => [
        ['label' => 'Dashboard',  'path' => 'department/dashboard.php',  'icon' => '🏠'],
        ['label' => 'Clearance',  'path' => 'department/clearance.php',  'icon' => '✅'],
        ['label' => 'Students',   'path' => 'department/students.php',   'icon' => '👨‍🎓'],
    ],
];

/**
 * Generate navigation items for the given role, marking the active page.
 */
function getNavItems(string $role, string $activeLabel = ''): array {
    global $navDefinitions;
    $items    = $navDefinitions[$role] ?? [];
    $navItems = [];
    foreach ($items as $item) {
        $navItems[] = [
            'label'  => $item['label'],
            'href'   => url($item['path']),
            'active' => $item['label'] === $activeLabel,
            'icon'   => $item['icon'],
        ];
    }
    return $navItems;
}
