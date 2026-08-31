<?php
require_once __DIR__ . '/../config/database.php';

echo "Checking columns in paper_subject_mapping table...\n";

$existingCols = $pdo->query("DESCRIBE paper_subject_mapping")->fetchAll(PDO::FETCH_COLUMN);

$columnsToAdd = [
    'subject_max_internal' => 'DECIMAL(5,2) NULL DEFAULT 0.00',
    'subject_max_midterm'  => 'DECIMAL(5,2) NULL DEFAULT 0.00',
    'subject_max_final'    => 'DECIMAL(5,2) NULL DEFAULT 0.00',
];

foreach ($columnsToAdd as $colName => $colDef) {
    if (!in_array($colName, $existingCols)) {
        echo "Adding column $colName to paper_subject_mapping...\n";
        $pdo->exec("ALTER TABLE paper_subject_mapping ADD COLUMN $colName $colDef");
        echo "Added $colName.\n";
    } else {
        echo "Column $colName already exists.\n";
    }
}

echo "\nUpdated paper_subject_mapping schema:\n";
$allCols = $pdo->query("DESCRIBE paper_subject_mapping")->fetchAll(PDO::FETCH_ASSOC);
foreach ($allCols as $col) {
    echo "{$col['Field']} | {$col['Type']} | Null: {$col['Null']} | Default: {$col['Default']}\n";
}
