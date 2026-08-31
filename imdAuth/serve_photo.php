<?php
// serve_photo.php - Secure Avatar Image Delivery Controller

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_guard.php';

$auth = requireAuth(); // Enforce authenticated user session

$filename = basename($_GET['file'] ?? '');

if (empty($filename)) {
    http_response_code(400);
    die("Bad Request");
}

$filepath = __DIR__ . '/storage/uploads/profile_photos/' . $filename;

if (!file_exists($filepath)) {
    http_response_code(404);
    die("File not found");
}

// Verify actual MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filepath);
finfo_close($finfo);

$allowedMimes = ['image/jpeg', 'image/png'];

if (!in_array($mimeType, $allowedMimes, true)) {
    http_response_code(403);
    die("Forbidden file type");
}

// Send Headers and File Contents
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=86400');
readfile($filepath);
exit;
