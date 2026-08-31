<?php
require_once __DIR__ . '/../config/database.php';

echo "=== CHECKING faculty_marks_entry COLUMNS ===\n";
$cols = $pdo->query("SHOW COLUMNS FROM faculty_marks_entry LIKE 'paper_id'")->fetchAll();

if (count($cols) === 0) {
    echo "Adding paper_id column to faculty_marks_entry...\n";
    $pdo->exec("ALTER TABLE faculty_marks_entry ADD COLUMN paper_id INT NULL AFTER faculty_id");
    $pdo->exec("ALTER TABLE faculty_marks_entry ADD CONSTRAINT fk_fme_paper FOREIGN KEY (paper_id) REFERENCES papers(paper_id) ON DELETE SET NULL");
    echo "Added paper_id and foreign key successfully!\n";
} else {
    echo "paper_id already exists in faculty_marks_entry.\n";
}

// Backfill any existing records with the mapped paper_id if possible
echo "Backfilling paper_id in faculty_marks_entry...\n";
$pdo->exec("
    UPDATE faculty_marks_entry fme
    JOIN students st ON st.student_id = fme.student_id
    JOIN paper_subject_mapping psm ON psm.subject_id = fme.subject_id AND psm.batch_id = st.batch_id
    SET fme.paper_id = psm.paper_id
    WHERE fme.paper_id IS NULL
");
echo "Backfilled existing records.\n";

echo "\nUpdated faculty_marks_entry schema:\n";
$allCols = $pdo->query("DESCRIBE faculty_marks_entry")->fetchAll(PDO::FETCH_ASSOC);
foreach ($allCols as $col) {
    echo "{$col['Field']} | {$col['Type']} | Null: {$col['Null']} | Default: {$col['Default']}\n";
}
