<?php
// includes/auth_guard.php

require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Enforce authentication and role permissions
 * @param string|array $allowedRoles Array or string of allowed role names ('guide', 'dept_admin', 'super_admin')
 */
function requireAuth($allowedRoles = []): array {
    // Send headers to prevent caching of authenticated pages
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Cache-Control: post-check=0, pre-check=0", false);
    header("Pragma: no-cache");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

    // A half-completed 2FA session must NEVER grant access
    if (empty($_SESSION['user_id'])) {
        setFlash('warning', 'Please log in to access this page.');
        header('Location: ' . baseUrl('login.php'));
        exit;
    }

    $currentRole = $_SESSION['role_name'] ?? '';

    if (!empty($allowedRoles)) {
        $allowed = (array)$allowedRoles;
        if (!in_array($currentRole, $allowed, true)) {
            http_response_code(403);
            render403Page("Access Denied: You do not have permissions to access this page.");
            exit;
        }
    }

    return [
        'user_id'    => $_SESSION['user_id'],
        'email'      => $_SESSION['user_email'] ?? '',
        'role_id'    => $_SESSION['role_id'] ?? null,
        'role_name'  => $currentRole,
        'full_name'  => $_SESSION['user_name'] ?? 'User'
    ];
}

/**
 * Render 403 Forbidden page
 */
function render403Page(string $message): void {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>403 Forbidden - IMD Portal</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="<?= baseUrl('assets/css/style.css') ?>">
    </head>
    <body class="bg-light d-flex align-items-center justify-content-center vh-100">
        <div class="card shadow border-danger text-center p-4" style="max-width: 500px;">
            <div class="card-body">
                <h1 class="display-1 text-danger fw-bold">403</h1>
                <h3 class="card-title text-dark">Access Denied</h3>
                <p class="card-text text-muted"><?= e($message) ?></p>
                <a href="<?= baseUrl('login.php') ?>" class="btn btn-primary mt-3">Back to Portal Home</a>
            </div>
        </div>
    </body>
    </html>
    <?php
}
