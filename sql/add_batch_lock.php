<?php

require_once __DIR__ . '/../config/database.php';

$columns = $pdo->query('SHOW COLUMNS FROM batches')->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('is_locked', $columns, true)) {
    $pdo->exec('ALTER TABLE batches ADD COLUMN is_locked TINYINT(1) NOT NULL DEFAULT 0 AFTER station');
    echo "Batch lock column added.\n";
} else {
    echo "Batch lock column already exists.\n";
}