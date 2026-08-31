<?php
require_once __DIR__ . '/../config/database.php';

echo "=== MODIFYING subject_faculty_mapping max_marks DEFAULT ===\n";
try {
    $pdo->exec("ALTER TABLE subject_faculty_mapping MODIFY COLUMN max_marks DECIMAL(5,2) NOT NULL DEFAULT 100.00");
    echo "Successfully updated subject_faculty_mapping max_marks default to 100.00.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
