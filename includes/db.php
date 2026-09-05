<?php
/*
|--------------------------------------------------------------------------
| FTC Grading & Tabulation Management System
| Centralized Database Connection (PDO)
|--------------------------------------------------------------------------
| Works locally (XAMPP) and on Vercel / Cloud MySQL Databases
|--------------------------------------------------------------------------
*/

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

if (class_exists('Dotenv\\Dotenv')) {
    if (file_exists(__DIR__ . '/../.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->safeLoad();
    } elseif (file_exists(__DIR__ . '/../imdAuth/.env')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../imdAuth');
        $dotenv->safeLoad();
    }
}

// Never expose application/database errors in production.
if (($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?? 'development') !== 'development') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

function getEnvHelper(string $key): ?string {
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }
    return null;
}

function getDbConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $host     = getEnvHelper('DB_HOST')     ?? getEnvHelper('MYSQLHOST')     ?? '127.0.0.1';
        $port     = getEnvHelper('DB_PORT')     ?? getEnvHelper('MYSQLPORT')     ?? '3306';
        $dbname   = getEnvHelper('DB_NAME')     ?? getEnvHelper('MYSQLDATABASE') ?? 'ftc_grading';
        $user     = getEnvHelper('DB_USER')     ?? getEnvHelper('MYSQLUSER')     ?? 'root';
        $pass     = getEnvHelper('DB_PASS')     ?? getEnvHelper('MYSQLPASSWORD') ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, $user, $pass, $options);
        } catch (PDOException $e) {
            die("Database Connection Failed: " . $e->getMessage());
        }
    }

    return $pdo;
}

// Global $pdo variable for scripts using $pdo directly
$pdo = getDbConnection();
