<?php

require_once "../../config/database.php";

$pageTitle = "Delete Batch";
$batchId = isset($_GET["id"]) ? (int)$_GET["id"] : (int)($_POST["batch_id"] ?? 0);

if ($batchId <= 0) {
    header("Location: index.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE fme FROM faculty_marks_entry fme INNER JOIN students s ON s.student_id = fme.student_id WHERE s.batch_id = :batch_id");
        $stmt->execute([":batch_id" => $batchId]);

        $stmt = $pdo->prepare("DELETE FROM subject_faculty_mapping WHERE batch_id = :batch_id");
        $stmt->execute([":batch_id" => $batchId]);

        $stmt = $pdo->prepare("DELETE FROM paper_subject_mapping WHERE batch_id = :batch_id");
        $stmt->execute([":batch_id" => $batchId]);

        $stmt = $pdo->prepare("DELETE FROM students WHERE batch_id = :batch_id");
        $stmt->execute([":batch_id" => $batchId]);

        $stmt = $pdo->prepare("DELETE FROM batches WHERE batch_id = :batch_id");
        $stmt->execute([":batch_id" => $batchId]);

        $pdo->commit();
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Unable to delete batch. It may still be used by another record.");
    }
}

$stmt = $pdo->prepare("SELECT batch_id, batch_number, batch_name, station FROM batches WHERE batch_id = :batch_id");
$stmt->execute([":batch_id" => $batchId]);
$batch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$batch) {
    die("Batch not found.");
}

require_once "../../includes/header.php";
?>

<div style="display:flex;flex-direction:column;min-height:100vh;">
    <?php require_once "../../includes/navbar.php"; ?>
    <div style="display:flex;flex:1;">
        <?php require_once "../../includes/sidebar.php"; ?>
        <main style="flex:1;padding:30px;background:#f4f6f8;">
            <h1 style="color:#c0392b;">Delete Batch</h1>
            <div style="background:#fff;padding:25px;border-radius:8px;max-width:700px;margin-top:25px;">
                <p>This will permanently delete the batch and its students, marks, assignments, and paper mappings.</p>
                <p><strong>Batch:</strong> <?php echo htmlspecialchars($batch["batch_name"]); ?></p>
                <p><strong>Batch Number:</strong> <?php echo htmlspecialchars($batch["batch_number"] ?? ""); ?></p>
                <p><strong>Station:</strong> <?php echo htmlspecialchars($batch["station"] ?? ""); ?></p>
                <form method="POST" style="margin-top:25px;">
                    <input type="hidden" name="batch_id" value="<?php echo $batchId; ?>">
                    <button type="submit" onclick="return confirm('Delete this batch and all related records permanently?');" style="background:#c0392b;color:white;border:0;padding:11px 20px;border-radius:6px;cursor:pointer;">Confirm Delete</button>
                    <a href="index.php" style="background:#777;color:white;padding:11px 20px;border-radius:6px;text-decoration:none;margin-left:8px;">Cancel</a>
                </form>
            </div>
        </main>
    </div>
    <?php require_once "../../includes/footer.php"; ?>
</div>