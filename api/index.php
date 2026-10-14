<?php
/**
 * Vercel entry point (EXPERIMENTAL).
 * Vercel's PHP runtime only runs files inside /api, so this tiny router
 * forwards every request to the matching PHP file in the project.
 */
$root = dirname(__DIR__);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (substr($path, -1) === '/') {
    $path .= 'index.php';
}

$file = realpath($root . $path);
$blocked = ['/config/', '/partials/', '/api/', '/database/', '/migrations/'];

$ok = $file !== false
    && strpos($file, $root) === 0
    && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'php';
foreach ($blocked as $b) {
    if (strpos($path, $b) === 0) { $ok = false; }
}

if (!$ok) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

chdir(dirname($file));
$_SERVER['SCRIPT_NAME'] = $path;
require $file;
