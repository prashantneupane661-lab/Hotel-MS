<?php
/**
 * Secure Logout — Hotel Management System
 * Logs the activity, clears session data, destroys cookie, and redirects.
 */
require_once __DIR__ . '/config/db.php';
startSecureSession();

// Log the logout activity before destroying session
if (isset($_SESSION['user_id'])) {
    logActivity((int)$_SESSION['user_id'], 'logout', 'User logged out');
}

// Clear all session variables
$_SESSION = [];

// Destroy session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

header('Location: /hotel-management/index.php');
exit;
