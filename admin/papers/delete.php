<?php

require_once __DIR__ . "/../../config/database.php";

$paperId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
$batchId = isset($_GET["batch_id"]) ? (int)$_GET["batch_id"] : 0;

if ($paperId <= 0) {
    header("Location: index.php");
    exit;
}

try {
    // Remove mappings first
    $stmt = $pdo->prepare("DELETE FROM paper_subject_mapping WHERE paper_id = :paper_id AND batch_id = :batch_id");
    $stmt->execute([":paper_id" => $paperId, ":batch_id" => $batchId]);

    // Delete paper
    $stmt = $pdo->prepare("DELETE FROM papers WHERE paper_id = :paper_id AND batch_id = :batch_id");
    $stmt->execute([":paper_id" => $paperId, ":batch_id" => $batchId]);

    header("Location: index.php?batch_id=" . $batchId . "&success=1");
    exit;
} catch (PDOException $e) {
    die("Unable to delete paper: " . $e->getMessage());
}
