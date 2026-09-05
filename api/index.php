<?php
/*
|--------------------------------------------------------------------------
| Vercel Serverless PHP Router & Front Controller
|--------------------------------------------------------------------------
| Entrypoint for Vercel Serverless Functions
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Load Composer Autoloader
$autoloader = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
} elseif (file_exists(__DIR__ . '/../imdAuth/vendor/autoload.php')) {
    require_once __DIR__ . '/../imdAuth/vendor/autoload.php';
}

// 2. Load Dotenv environment variables
if (class_exists('Dotenv\\Dotenv')) {
    if (file_exists(__DIR__ . '/../.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->safeLoad();
    } elseif (file_exists(__DIR__ . '/../imdAuth/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../imdAuth');
        $dotenv->safeLoad();
    }
}

// 3. Parse Request URI
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestUri = urldecode($requestUri);

// Root path requested
if ($requestUri === '/' || $requestUri === '' || $requestUri === '/index.php') {
    require_once __DIR__ . '/../index.php';
    exit;
}

// 4. Serve static public files fallback if requested through PHP
$publicFilePath = __DIR__ . '/../public' . $requestUri;
if (file_exists($publicFilePath) && !is_dir($publicFilePath)) {
    $ext = pathinfo($publicFilePath, PATHINFO_EXTENSION);
    $mimeTypes = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    readfile($publicFilePath);
    exit;
}

// 5. Route dynamic PHP page requests
$targetFile = __DIR__ . '/..' . $requestUri;

// Match exact php file (e.g. /login.php, /admin/index.php)
if (file_exists($targetFile) && !is_dir($targetFile) && pathinfo($targetFile, PATHINFO_EXTENSION) === 'php') {
    require_once $targetFile;
    exit;
}

// Match folder with index.php (e.g. /admin -> /admin/index.php)
if (is_dir($targetFile) && file_exists(rtrim($targetFile, '/') . '/index.php')) {
    require_once rtrim($targetFile, '/') . '/index.php';
    exit;
}

// Match path without .php extension (e.g. /login -> /login.php)
if (file_exists($targetFile . '.php') && !is_dir($targetFile . '.php')) {
    require_once $targetFile . '.php';
    exit;
}

// 6. Default Fallback -> index.php
if (file_exists(__DIR__ . '/../index.php')) {
    require_once __DIR__ . '/../index.php';
    exit;
}

http_response_code(404);
echo "404 - Page Not Found";
