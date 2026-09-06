<?php
require_once "config/database.php";

$studentId = 1; // Assuming 1 exists
$stmt = $pdo->prepare("SELECT batch_id FROM students WHERE student_id = ?");
$stmt->execute([$studentId]);
$batch_id = $stmt->fetchColumn();

$marksStmt = $pdo->prepare("
    SELECT
        fme.entry_id,
        fme.subject_id,
        fme.faculty_id,
        fme.assessment_type_id,
        fme.marks_obtained,
        f.faculty_name
    FROM faculty_marks_entry fme
    INNER JOIN students st ON st.student_id = fme.student_id AND st.batch_id = ?
    INNER JOIN subjects sub ON sub.subject_id = fme.subject_id
    INNER JOIN faculties f ON f.faculty_id = fme.faculty_id
    INNER JOIN assessment_types at ON at.assessment_type_id = fme.assessment_type_id
    INNER JOIN papers p ON p.paper_id = fme.paper_id
    INNER JOIN paper_subject_mapping psm ON psm.paper_id = fme.paper_id AND psm.subject_id = fme.subject_id AND psm.batch_id = ?
    LEFT JOIN subject_faculty_mapping sfm ON sfm.batch_id = ? AND sfm.subject_id = fme.subject_id AND sfm.faculty_id = fme.faculty_id
    WHERE fme.student_id = ?
");

$marksStmt->execute([$batch_id, $batch_id, $batch_id, $studentId]);
$marks = $marksStmt->fetchAll(PDO::FETCH_ASSOC);

print_r($marks);
