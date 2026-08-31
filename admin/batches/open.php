<?php

require_once "../../config/database.php";

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
            status = 'Active',
            is_locked = 0
        WHERE batch_id = :batch_id
    ");

    $stmt->execute([
        ":batch_id" => $batchId
    ]);

} catch (PDOException $e) {

    die("Unable to open batch.");

}

header("Location: index.php");
exit;