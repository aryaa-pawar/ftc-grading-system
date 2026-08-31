<?php

error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once __DIR__ . '/../../teacher/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';

$facultyId = (int)$_SESSION['faculty_id'];

$studentId = isset($_POST["student_id"]) ? (int)$_POST["student_id"] : 0;
$tab = isset($_POST["tab"]) ? (int)$_POST["tab"] : 0;
$paperId = isset($_POST["paper_id"]) ? (int)$_POST["paper_id"] : 0;

$remark = isset($_POST["custom_remarks"])
    ? trim($_POST["custom_remarks"])
    : (isset($_POST["custom_remark"]) ? trim($_POST["custom_remark"]) : "");

if ($studentId <= 0) {
    die("Invalid request. Student ID is missing.");
}

if (strlen($remark) > 500) {
    die("Remark cannot be more than 500 characters.");
}

try {
    $stmt = $pdo->prepare("
        UPDATE students
        SET custom_remarks = ?
        WHERE student_id = ?
          AND (is_deleted = 0 OR is_deleted IS NULL)
    ");
    $stmt->execute([
        $remark === "" ? null : $remark,
        $studentId
    ]);

    header("Location: marks.php?id=" . $facultyId . "&tab=" . $tab . "&paper_id=" . $paperId . "&remark_saved=1");
    exit;

} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, "UTF-8"));
}