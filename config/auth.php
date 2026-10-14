<?php
/**
 * ═══════════════════════════════════════════════════════════
 * AUTHENTICATION & SESSION MANAGEMENT
 * ═══════════════════════════════════════════════════════════
 * Handles user authentication, authorization, and session control
 */

// Load database and path helper functions.
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/paths.php';

// Start a new user session if one doesn't already exist so we can track login state.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in with optional role validation - returns to login page if not authorized.
 */
function requireLogin(string $role = ''): void {
    // If no user ID in session, redirect to login page.
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . url('index.php'));
        exit;
    }
    // If specific role is required and user doesn't have it, deny access.
    if ($role && $_SESSION['role'] !== $role) {
        header('Location: ' . url('index.php?err=forbidden'));
        exit;
    }
}

/**
 * Return the currently logged-in user's data stored in the session.
 */
function currentUser(): array {
    // Retrieve user data from session or return empty array if not logged in.
    return $_SESSION['user'] ?? [];
}

/**
 * Check whether a user is currently logged in.
 */
function isLoggedIn(): bool {
    // Return true if there's a user ID stored in the session.
    return !empty($_SESSION['user_id']);
}

/**
 * Log out the current user by clearing session data and redirecting to the homepage.
 */
function logout(): void {
    // Clear all session variables and destroy the session cookie.
    session_unset();
    session_destroy();
    // Send user back to login page.
    header('Location: ' . url('index.php'));
    exit;
}

/**
 * Convert plain text to HTML-safe format - prevents code injection attacks.
 */
function sanitize(string $val): string {
    // Convert special characters to HTML entities and remove extra whitespace.
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

/**
 * Store a message in session and redirect user to a new page - useful for showing success/error messages.
 */
function redirectWith(string $path, string $msg, string $type = 'success'): void {
    // Store the message and type in session so it can be shown on the next page.
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
    // Send user to the new page.
    header('Location: ' . url($path));
    exit;
}

/**
 * Get a temporary message from session and delete it so it only shows once.
 */
function getFlash(): array {
    // Retrieve the flash message from session.
    $flash = $_SESSION['flash'] ?? [];
    // Delete the message so it won't show again on the next page.
    unset($_SESSION['flash']);
    return $flash;
}
