<?php

require_once __DIR__ . '/../config/database.php';

$column = $pdo->query("SHOW COLUMNS FROM students LIKE 'verification_status'")->fetch(PDO::FETCH_ASSOC);

if (!$column) {
    $pdo->exec("ALTER TABLE students ADD COLUMN verification_status ENUM('correct', 'wrong') NULL AFTER scrutiny_remarks");
    echo "verification_status column added.\n";
} else {
    echo "verification_status column already exists.\n";
}