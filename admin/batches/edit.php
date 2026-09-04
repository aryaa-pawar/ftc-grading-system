<?php

require_once "../../config/database.php";

$pageTitle = "Edit Batch";

$batchId = isset($_GET["id"])
    ? (int)$_GET["id"]
    : (int)($_POST["batch_id"] ?? 0);

if ($batchId <= 0) {
    die("Invalid batch ID.");
}

$stmt = $pdo->prepare("
    SELECT *
    FROM batches
    WHERE batch_id = :batch_id
");

$stmt->execute([
    ":batch_id" => $batchId
]);

$batch = $stmt->fetch();

if (!$batch) {
    die("Batch not found.");
}

$errorMessage = "";


if (($_SERVER["REQUEST_METHOD"] ?? "") === "POST") {

    $batchName = trim($_POST["batch_name"] ?? "");
    $batchNumber = trim($_POST["batch_number"] ?? "");
    $startDate = $_POST["start_date"] ?? $_POST["commenced_date"] ?? "";
    $endDate = $_POST["end_date"] ?? $_POST["completed_date"] ?? "";
    $status = $_POST["status"] ?? "Running";
    $isLocked = ($status === "Completed" || $status === "Locked") ? 1 : 0;

    if ($batchName === "") {

        $errorMessage = "Batch name is required.";

    } elseif ($batchNumber === "") {

        $errorMessage = "Batch number is required.";

    } else {

        try {

            $stmt = $pdo->prepare("
                UPDATE batches
                SET
                    batch_number = :batch_number,
                    batch_name = :batch_name,
                    commenced_date = :commenced_date,
                    completed_date = :completed_date,
                    status = :status,
                    is_locked = :is_locked
                WHERE batch_id = :batch_id
            ");

            $stmt->execute([
                ":batch_number" => $batchNumber,
                ":batch_name" => $batchName,
                ":commenced_date" => $startDate !== "" ? $startDate : null,
                ":completed_date" => $endDate !== "" ? $endDate : null,
                ":status" => $status,
                ":is_locked" => $isLocked,
                ":batch_id" => $batchId
            ]);

            header("Location: view.php?id=" . $batchId);
            exit;

        } catch (PDOException $e) {

            $errorMessage = "Unable to update batch: " . $e->getMessage();

        }

    }

}

require_once "../../includes/header.php";

?>

<div style="display:flex;flex-direction:column;min-height:100vh;">

<?php require_once "../../includes/navbar.php"; ?>

<div style="display:flex;flex:1;">

<?php require_once "../../includes/sidebar.php"; ?>

<main style="flex:1;padding:30px;background:#f4f6f8;">

<h1 style="color:#1f4e79;">
Edit Batch
</h1>

<p style="color:#666;margin-bottom:25px;">
Update batch information.
</p>

<?php if ($errorMessage !== ""): ?>

<div style="
    background:#fdecea;
    color:#a12622;
    padding:15px;
    border-radius:6px;
    margin-bottom:20px;
">
<?php echo htmlspecialchars($errorMessage); ?>
</div>

<?php endif; ?>


<div style="
    background:#fff;
    padding:25px;
    border-radius:8px;
    max-width:700px;
">

<form method="POST">

<input
    type="hidden"
    name="batch_id"
    value="<?php echo $batchId; ?>"
>


<label>Batch Number *</label>

<input
    type="text"
    name="batch_number"
    value="<?php echo htmlspecialchars($batch["batch_number"] ?? ""); ?>"
    required
    style="width:100%;padding:10px;margin:8px 0 18px;"
>


<label>Batch Name *</label>

<input
    type="text"
    name="batch_name"
    value="<?php echo htmlspecialchars($batch["batch_name"] ?? ""); ?>"
    required
    style="width:100%;padding:10px;margin:8px 0 18px;"
>


<label>Start Date</label>

<input
    type="date"
    name="start_date"
    value="<?php echo htmlspecialchars($batch["start_date"] ?? $batch["commenced_date"] ?? ""); ?>"
    style="width:100%;padding:10px;margin:8px 0 18px;"
>


<label>End Date</label>

<input
    type="date"
    name="end_date"
    value="<?php echo htmlspecialchars($batch["end_date"] ?? $batch["completed_date"] ?? ""); ?>"
    style="width:100%;padding:10px;margin:8px 0 18px;"
>


<label>Status</label>

<select
    name="status"
    style="width:100%;padding:10px;margin:8px 0 22px;"
>

<?php
$currentStatus = $batch["status"] ?? (($batch["is_locked"] ?? 0) == 1 ? "Completed" : "Running");
?>

<option value="Running" <?php echo $currentStatus === "Running" ? "selected" : ""; ?>>Running</option>
<option value="Result Declared" <?php echo $currentStatus === "Result Declared" ? "selected" : ""; ?>>Result Declared</option>

<option
    value="Completed"
    <?php echo $currentStatus === "Completed" ? "selected" : ""; ?>
>
Completed
</option>

</select>


<button
    type="submit"
    style="
        background:#278a4b;
        color:white;
        border:0;
        padding:11px 20px;
        border-radius:5px;
        margin-right:10px;
    "
>
Update Batch
</button>


<a
    href="view.php?id=<?php echo $batchId; ?>"
    style="
        background:#777;
        color:white;
        padding:11px 20px;
        border-radius:5px;
    "
>
Cancel
</a>

</form>

</div>

</main>

</div>

<?php require_once "../../includes/footer.php"; ?>

</div>
