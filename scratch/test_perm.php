<?php
session_start();
$_SESSION['user_id'] = 3;
require __DIR__ . '/../includes/auth.php';
echo "User: " . print_r(current_user(), true) . "\n";
echo "admin.access: " . (has_permission('admin.access') ? 'GRANTED' : 'DENIED') . "\n";
