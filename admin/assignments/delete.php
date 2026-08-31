<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../../config/database.php";


$batchId =
    isset($_GET['batch_id'])
        ? (int)$_GET['batch_id']
        : 0;

$subjectId =
    isset($_GET['subject_id'])
        ? (int)$_GET['subject_id']
        : 0;

$facultyId =
    isset($_GET['faculty_id'])
        ? (int)$_GET['faculty_id']
        : 0;


if (
    $batchId <= 0 ||
    $subjectId <= 0 ||
    $facultyId <= 0
) {

    header(
        "Location: index.php?error="
        . urlencode(
            "Invalid assignment."
        )
    );

    exit;
}


try {

    $stmt = $pdo->prepare("
        DELETE FROM subject_faculty_mapping

        WHERE
            batch_id = ?
            AND subject_id = ?
            AND faculty_id = ?
    ");

    $stmt->execute([
        $batchId,
        $subjectId,
        $facultyId
    ]);


    header(
        "Location: index.php?success="
        . urlencode(
            "Faculty assignment removed successfully."
        )
    );

    exit;


} catch (PDOException $e) {

    header(
        "Location: index.php?error="
        . urlencode(
            $e->getMessage()
        )
    );

    exit;
}