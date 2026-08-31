<?php

require_once __DIR__ . '/includes/auth_guard.php';

$filename = basename($_GET['file'] ?? '');
$filePath = __DIR__ . '/storage/uploads/profile_photos/' . $filename;

if ($filename === '' || !is_file($filePath)) {
    http_response_code(404);
    exit('File not found');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

if (!in_array($mimeType, ['image/jpeg', 'image/png'], true)) {
    http_response_code(403);
    exit('Forbidden file type');
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=86400');
readfile($filePath);