<?php
// includes/functions.php

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Htmlspecialchars wrapper for safe output escaping
 */
function e(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

function roleDisplayLabel(?string $role): string {
    switch (strtoupper(trim((string)$role))) {
        case 'GUIDE': case 'TEACHER': case 'FACULTY': return 'Faculty / Lecturer';
        case 'VERIFICATION': return 'Scrutiny';
        case 'SUPER_ADMIN': return 'Super Admin';
        case 'ADMIN': case 'DEPT_ADMIN': return 'Admin';
        default: return ucwords(strtolower(str_replace('_', ' ', (string)$role)));
    }
}

/**
 * Set flash alert message
 */
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

/**
 * Display flash alert message HTML
 */
function displayFlash(): string {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return sprintf(
            '<div class="alert alert-%s alert-dismissible fade show" role="alert">
                %s
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>',
            e($flash['type']),
            e($flash['message'])
        );
    }
    return '';
}

/**
 * Log audit events to audit_logs table
 */
function logAudit(?int $userId, string $action): void {
    try {
        $pdo = getDbConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, ip_address, created_at) VALUES (:user_id, :action, :ip, NOW())");
        $stmt->execute([
            ':user_id' => $userId,
            ':action'  => $action,
            ':ip'      => $ip
        ]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage(), 3, __DIR__ . '/../storage/logs/app_error.log');
    }
}



/**
 * Base URL Helper
 */
function baseUrl(string $path = ''): string {
    $baseUrl = rtrim($_ENV['APP_URL'] ?? 'http://localhost/ftc_grading', '/');
    return $baseUrl . '/' . ltrim($path, '/');
}
