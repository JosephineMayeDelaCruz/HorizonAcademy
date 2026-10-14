<?php
/**
 * ═══════════════════════════════════════════════════════════
 * PATHS & URL HELPER
 * ═══════════════════════════════════════════════════════════
 * Auto-detects the project base URL and provides url() helper.
 * This fixes "Not Found" when the app lives in a subdirectory.
 */

// Calculate the base URL path by comparing the web server root with this project's location.
$docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$thisDir = rtrim(str_replace('\\', '/', __DIR__), '/'); // .../config
$projectDir = dirname($thisDir); // parent of config = project root

// Extract the base path if the project is in a subdirectory of the web server.
$basePath = '';
if (strpos($projectDir, $docRoot) === 0) {
    $basePath = rtrim(substr($projectDir, strlen($docRoot)), '/');
}

// Ensure base path is properly formatted (empty string or starts with /).
$basePath = $basePath ? $basePath : '';

// Optional override for hosts where auto-detection fails (e.g. set APP_BASE_PATH='' or '/app').
if (getenv('APP_BASE_PATH') !== false) {
    $basePath = rtrim(getenv('APP_BASE_PATH'), '/');
}

/**
 * Generate a full URL relative to the project root - fixes links when app is in a subfolder.
 */
function url(string $path = ''): string {
    global $basePath;
    // Remove leading slash from the path, then combine with base path for the final URL.
    $path = ltrim($path, '/');
    return $basePath . ($path ? '/' . $path : '');
}

