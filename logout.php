<?php
// logout.php - Complete Session Destruction

require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;

// Log audit action before destroying session
if ($userId) {
    logAudit($userId, 'LOGOUT');
}

// Unset all session variables
$_SESSION = [];

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to the main portal with flash message
session_start();
setFlash('info', 'You have been logged out safely.');
header('Location: ' . baseUrl());
exit;
