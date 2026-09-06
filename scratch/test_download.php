<?php
// scratch/test_download.php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role_id'] = 3;
$_SESSION['role_name'] = 'super_admin';

$_GET['batch_id'] = 2;
$_GET['download'] = 'excel';

// Capture output headers and buffer
ob_start();
require __DIR__ . '/../admin/marks/index.php';
$content = ob_get_clean();

echo "Headers Sent:\n";
foreach (headers_list() as $h) {
    echo "  $h\n";
}
echo "Content Length: " . strlen($content) . "\n";
echo "First 20 bytes (hex): " . bin2hex(substr($content, 0, 20)) . "\n";
