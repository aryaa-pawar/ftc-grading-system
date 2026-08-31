<?php

require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once "../../config/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

enforceCsrf();

$batchId = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

if ($batchId <= 0) {
    header("Location: index.php");
    exit;
}

try {

    $stmt = $pdo->prepare("
        UPDATE batches
        SET
            is_locked = 1
        WHERE batch_id = :batch_id
    ");

    $stmt->execute([
        ":batch_id" => $batchId
    ]);

} catch (PDOException $e) {

    die("Unable to lock batch.");

}

header("Location: ../results/result_register.php?batch_id=" . $batchId);
exit;