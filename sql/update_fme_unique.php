<?php
require_once __DIR__ . '/../config/database.php';

echo "Updating unique index on faculty_marks_entry...\n";
try {
    $pdo->exec("ALTER TABLE faculty_marks_entry DROP INDEX student_id");
} catch (PDOException $e) {
    echo "Drop index info: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE faculty_marks_entry ADD UNIQUE KEY uq_fme_entry (student_id, subject_id, faculty_id, assessment_type_id, paper_id)");
    echo "Added uq_fme_entry successfully!\n";
} catch (PDOException $e) {
    echo "Add unique error: " . $e->getMessage() . "\n";
}
