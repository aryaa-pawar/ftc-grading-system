<?php

// Faculty/account deletion is centralized in admin/manage_users.php.
require_once __DIR__ . '/../../includes/auth_guard.php';

http_response_code(410);
header('Location: index.php?error=central-delete-only');
exit;
