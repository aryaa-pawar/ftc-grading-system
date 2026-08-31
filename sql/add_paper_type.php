<?php
require_once __DIR__ . '/../config/database.php';

echo "Checking if paper_type column exists in papers table...\n";
$cols = $pdo->query("SHOW COLUMNS FROM papers LIKE 'paper_type'")->fetchAll();

if (count($cols) === 0) {
    echo "Adding column paper_type to papers table...\n";
    $pdo->exec("ALTER TABLE papers ADD COLUMN paper_type VARCHAR(50) NOT NULL DEFAULT 'Theory' AFTER is_elective");
    echo "Column paper_type added successfully!\n";
} else {
    echo "Column paper_type already exists.\n";
}

echo "\nUpdated papers schema:\n";
$allCols = $pdo->query("DESCRIBE papers")->fetchAll(PDO::FETCH_ASSOC);
foreach ($allCols as $col) {
    echo "{$col['Field']} | {$col['Type']} | Null: {$col['Null']} | Default: {$col['Default']}\n";
}
