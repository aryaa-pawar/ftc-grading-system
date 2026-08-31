<?php

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function auth_base_path(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $base = preg_replace('#/(admin|teacher|verification|includes)/.*$#i', '', $script);
    return rtrim($base === '/' ? '' : $base, '/');
}

function auth_redirect(string $path): never
{
    header('Location: ' . auth_base_path() . '/' . ltrim($path, '/'));
    exit;
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    return [
        'user_id' => (int)$_SESSION['user_id'],
        'role_id' => (int)($_SESSION['role_id'] ?? 0),
        'role_name' => $_SESSION['role_name'] ?? $_SESSION['role'] ?? '',
        'email' => $_SESSION['user_email'] ?? $_SESSION['faculty_email'] ?? '',
        'full_name' => $_SESSION['user_name'] ?? $_SESSION['faculty_name'] ?? '',
    ];
}

function has_permission(string $permissionName): bool
{
    $user = current_user();
    if (!$user) {
        return false;
    }

    static $permissions = [];
    if (!array_key_exists($permissionName, $permissions)) {
        $stmt = getDbConnection()->prepare(
            'SELECT 1 FROM role_permissions rp JOIN permissions p ON p.permission_id = rp.permission_id
             WHERE rp.role_id = :role_id AND p.permission_name = :permission LIMIT 1'
        );
        $stmt->execute([':role_id' => $user['role_id'], ':permission' => $permissionName]);
        $permissions[$permissionName] = (bool)$stmt->fetchColumn();
    }
    return $permissions[$permissionName];
}

function require_login(): array
{
    if (!current_user()) {
        auth_redirect('teacher/login.php');
    }
    return current_user();
}

function require_permission(string $permissionName): array
{
    $user = require_login();
    if (!has_permission($permissionName)) {
        http_response_code(403);
        exit('403 Forbidden');
    }
    return $user;
}

function establish_user_session(array $user, ?array $faculty = null): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['user_id'];
    $_SESSION['role_id'] = (int)$user['role_id'];
    $_SESSION['role_name'] = $user['role_name'];
    $_SESSION['role'] = $user['role_name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['full_name'] ?? '';
    if ($faculty) {
        $_SESSION['faculty_id'] = (int)$faculty['faculty_id'];
        $_SESSION['faculty_name'] = $faculty['faculty_name'];
        $_SESSION['faculty_email'] = $faculty['email'];
    }
}