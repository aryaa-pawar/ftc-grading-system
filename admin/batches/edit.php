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

    $directorName = trim($_POST["director_name"] ?? "") ?: "K. N. Mohan";
    $directorNameHindi = trim($_POST["director_name_hindi"] ?? "") ?: "के.एन.मोहन";

    $signaturePath = $batch["director_signature_path"] ?? null;
    if (isset($_FILES['director_signature']) && $_FILES['director_signature']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../uploads/signatures/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $ext = strtolower(pathinfo($_FILES['director_signature']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed, true)) {
            $filename = 'sig_' . time() . '_' . uniqid() . '.png';
            $destFile = $uploadDir . $filename;
            $tmpFile = $_FILES['director_signature']['tmp_name'];

            $processed = false;
            if (extension_loaded('gd')) {
                $info = @getimagesize($tmpFile);
                if ($info && isset($info['mime'])) {
                    $img = null;
                    switch ($info['mime']) {
                        case 'image/jpeg': $img = @imagecreatefromjpeg($tmpFile); break;
                        case 'image/png': $img = @imagecreatefrompng($tmpFile); break;
                        case 'image/webp': $img = @imagecreatefromwebp($tmpFile); break;
                    }
                    if ($img) {
                        if (function_exists('imagefilter')) {
                            imagefilter($img, IMG_FILTER_CONTRAST, -35);
                            imagefilter($img, IMG_FILTER_BRIGHTNESS, 25);
                        }
                        $processed = @imagepng($img, $destFile, 6);
                        imagedestroy($img);
                    }
                }
            }
            if (!$processed) {
                $filename = 'sig_' . time() . '_' . uniqid() . '.' . $ext;
                $destFile = $uploadDir . $filename;
                move_uploaded_file($tmpFile, $destFile);
            }
            $signaturePath = 'uploads/signatures/' . $filename;
        }
    }

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
                    is_locked = :is_locked,
                    director_name = :director_name,
                    director_name_hindi = :director_name_hindi,
                    director_signature_path = :director_signature_path
                WHERE batch_id = :batch_id
            ");

            $stmt->execute([
                ":batch_number" => $batchNumber,
                ":batch_name" => $batchName,
                ":commenced_date" => $startDate !== "" ? $startDate : null,
                ":completed_date" => $endDate !== "" ? $endDate : null,
                ":status" => $status,
                ":is_locked" => $isLocked,
                ":director_name" => $directorName,
                ":director_name_hindi" => $directorNameHindi,
                ":director_signature_path" => $signaturePath,
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

<form method="POST" enctype="multipart/form-data">

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


<!-- ASSIGNED DIRECTOR FOR BATCH -->
<div style="margin: 20px 0; padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
    <h4 style="margin:0 0 12px 0; color:#1f4e79;">Assigned Director for Batch</h4>
    <?php
        $dirEng = !empty($batch["director_name"]) ? $batch["director_name"] : "K. N. Mohan";
        $dirHin = !empty($batch["director_name_hindi"]) ? $batch["director_name_hindi"] : "के.एन.मोहन";
    ?>
    <div style="margin-bottom:15px;">
        <label style="display:block;font-weight:bold;margin-bottom:6px;">Director Name (English) *</label>
        <div style="display:flex; gap:10px;">
            <input type="text" id="director_name" name="director_name" value="<?= htmlspecialchars($dirEng) ?>" required placeholder="e.g. K. N. Mohan" style="flex:1; padding:10px; border:1px solid #ccc; border-radius:5px;">
            <button type="button" id="btn_convert_dir" onclick="convertDirectorNameToHindi()" style="background:#1f4e79; color:#fff; border:none; padding:10px 16px; border-radius:5px; font-weight:bold; cursor:pointer;">Convert</button>
        </div>
    </div>

    <div style="margin-top:12px;">
        <label style="display:block;font-weight:bold;margin-bottom:6px;">Director Name (Hindi / Devanagari) *</label>
        <select id="director_suggestions" onchange="document.getElementById('director_name_hindi').value = this.value" style="display:none; width:100%; padding:8px; margin-bottom:8px; border:1px solid #ccc; border-radius:5px;">
            <option value="">Choose spelling</option>
        </select>
        <input type="text" id="director_name_hindi" name="director_name_hindi" value="<?= htmlspecialchars($dirHin) ?>" required placeholder="e.g. के.एन.मोहन" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:5px;">
    </div>

    <div style="margin-top:15px;">
        <label style="display:block;font-weight:bold;margin-bottom:6px;">Director Digital Signature Photo (Optional)</label>
        <?php if (!empty($batch["director_signature_path"]) && file_exists(__DIR__ . '/../../' . $batch["director_signature_path"])): ?>
            <div style="margin-bottom:8px;">
                <span style="font-size:12px; color:#475569; display:block; margin-bottom:4px;">Current Signature:</span>
                <img src="../../<?= htmlspecialchars($batch["director_signature_path"]) ?>" alt="Signature" style="max-height:60px; max-width:180px; border:1px solid #cbd5e1; padding:3px; background:#fff; border-radius:4px; mix-blend-mode: multiply; filter: contrast(180%) brightness(108%);">
            </div>
        <?php endif; ?>
        <input type="file" id="director_signature" name="director_signature" accept="image/*" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:5px; background:#fff;">
        <small style="color:#64748b;">Allowed formats: PNG, JPG, JPEG, WEBP. Upload a photo of the signature to be displayed above the Director's name on certificates and results.</small>
    </div>
</div>

<script>
async function convertDirectorNameToHindi() {
    var engInput = document.getElementById('director_name');
    var hinInput = document.getElementById('director_name_hindi');
    var sugSelect = document.getElementById('director_suggestions');
    var btn = document.getElementById('btn_convert_dir');
    var engName = engInput ? engInput.value.trim() : '';

    if (!engName || !hinInput || !btn) return;
    btn.disabled = true;
    btn.textContent = 'Converting...';

    try {
        var endpoint = 'https://inputtools.google.com/request?text=' + encodeURIComponent(engName) + '&itc=hi-t-i0-und&num=5&ie=utf-8&oe=utf-8';
        var res = await fetch(endpoint);
        var data = await res.json();
        var suggestions = data[1] && data[1][0] && data[1][0][1] ? data[1][0][1] : [];
        if (suggestions.length > 0) {
            hinInput.value = suggestions[0];
            if (sugSelect) {
                sugSelect.innerHTML = '<option value="">Choose spelling</option>';
                suggestions.forEach(function(s) {
                    var opt = document.createElement('option');
                    opt.value = s;
                    opt.textContent = s;
                    sugSelect.appendChild(opt);
                });
                sugSelect.style.display = suggestions.length > 1 ? 'block' : 'none';
            }
        }
    } catch(e) {} finally {
        btn.disabled = false;
        btn.textContent = 'Convert';
    }
}
</script>


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
