<?php

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

enforceCsrf();

$pdo->exec("UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0");

header('Content-Type: application/json');
echo json_encode(['success' => true]);
