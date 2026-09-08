<?php

require_once __DIR__ . "/../../config/database.php";

$pageTitle = "Add Paper";
$errorMessage = "";
$batchId = isset($_GET["batch_id"]) ? (int)$_GET["batch_id"] : (int)($_POST["batch_id"] ?? 0);
$batches = [];

$batchStmt = $pdo->query("SELECT batch_id, batch_name FROM batches ORDER BY batch_id ASC");
$batches = $batchStmt->fetchAll(PDO::FETCH_ASSOC);

if ($batchId <= 0 && !empty($batches)) {
    $batchId = (int)$batches[0]["batch_id"];
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $paperCode = trim($_POST["paper_code"] ?? "");
    $paperName = trim($_POST["paper_name"] ?? "");
    $paperType = trim($_POST["paper_type"] ?? "Theory");
    $isElective = isset($_POST["is_elective"]) ? 1 : 0;
    $maxInternal = (float)($_POST["max_internal"] ?? 30.00);
    $maxMidterm = (float)($_POST["max_midterm"] ?? 25.00);
    $maxFinal = (float)($_POST["max_final"] ?? 45.00);

    if ($batchId <= 0) {
        $errorMessage = "Please select a training batch.";
    } elseif (empty($paperCode) || empty($paperName)) {
        $errorMessage = "Paper Code and Paper Name are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO papers (batch_id, paper_code, paper_name, is_elective, paper_type, max_internal, max_midterm, max_final)
                VALUES (:batch_id, :paper_code, :paper_name, :is_elective, :paper_type, :max_internal, :max_midterm, :max_final)
            ");
            $stmt->execute([
                ":batch_id" => $batchId,
                ":paper_code" => $paperCode,
                ":paper_name" => $paperName,
                ":is_elective" => $isElective,
                ":paper_type" => $paperType,
                ":max_internal" => $maxInternal,
                ":max_midterm" => $maxMidterm,
                ":max_final" => $maxFinal
            ]);

            header("Location: index.php?batch_id=" . $batchId . "&success=1");
            exit;
        } catch (PDOException $e) {
            $errorMessage = "Failed to add paper: " . $e->getMessage();
        }
    }
}

require_once "../../includes/header.php";

?>

<div style="display:flex; flex-direction:column; min-height:100vh;">

    <?php require_once "../../includes/navbar.php"; ?>

    <div style="display:flex; flex:1;">

        <?php require_once "../../includes/sidebar.php"; ?>

        <main style="flex:1; padding:30px; background:#f4f6f8;">

            <div style="margin-bottom:25px;">
                <h1 style="margin:0; font-size:26px; color:#1f4e79;">Add New Paper</h1>
                <p style="margin:5px 0 0; color:#666666;">Define a new paper definition for evaluation and subject grouping.</p>
            </div>

            <?php if (!empty($errorMessage)): ?>
                <div style="padding:15px; background:#f8d7da; color:#721c24; border-radius:4px; margin-bottom:20px;">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>

            <div style="background:#ffffff; border-radius:8px; border:1px solid #e0e0e0; padding:25px; max-width:600px; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
                <form method="POST" action="">

                    <div style="margin-bottom:18px;">
                        <label style="display:block; font-weight:bold; margin-bottom:6px; color:#333;">Training Batch *</label>
                        <select name="batch_id" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-size:14px;">
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?php echo (int)$batch['batch_id']; ?>" <?php echo ((int)$batch['batch_id'] === $batchId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($batch['batch_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div style="margin-bottom:18px;">
                        <label style="display:block; font-weight:bold; margin-bottom:6px; color:#333;">Paper Code *</label>
                        <input type="text" name="paper_code" placeholder="e.g. P-I, PR-I, VIVA, PROJ, OJT" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-size:14px;">
                    </div>

                    <div style="margin-bottom:18px;">
                        <label style="display:block; font-weight:bold; margin-bottom:6px; color:#333;">Paper Name *</label>
                        <input type="text" name="paper_name" placeholder="e.g. Paper I (Dynamic Meteorology & NWP)" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-size:14px;">
                    </div>

                    <div style="margin-bottom:18px;">
                        <label style="display:block; font-weight:bold; margin-bottom:6px; color:#333;">Paper Type *</label>
                        <select name="paper_type" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-size:14px;">
                            <option value="Theory">Theory Paper</option>
                            <option value="Practical">Practical Paper</option>
                            <option value="Viva">Viva-Voce</option>
                            <option value="Project">Project Work</option>
                            <option value="OJT">On Job Training (OJT)</option>
                        </select>
                    </div>

                    <div style="margin-bottom:18px;">
                        <label style="display:flex; align-items:center; gap:8px; font-weight:bold; cursor:pointer;">
                            <input type="checkbox" name="is_elective" value="1">
                            This is an Elective Paper (Choice-based per group)
                        </label>
                    </div>

                    <div style="display:flex; gap:15px; margin-bottom:20px;">
                        <div style="flex:1;">
                            <label style="display:block; font-weight:bold; margin-bottom:6px; color:#333;">Max Internal</label>
                            <input type="number" step="0.01" name="max_internal" value="30.00" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-size:14px;">
                        </div>

                        <div style="flex:1;">
                            <label style="display:block; font-weight:bold; margin-bottom:6px; color:#333;">Max Mid-Term</label>
                            <input type="number" step="0.01" name="max_midterm" value="25.00" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-size:14px;">
                        </div>

                        <div style="flex:1;">
                            <label style="display:block; font-weight:bold; margin-bottom:6px; color:#333;">Max Final Exam</label>
                            <input type="number" step="0.01" name="max_final" value="45.00" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-size:14px;">
                        </div>
                    </div>

                    <div style="display:flex; gap:10px; justify-content:flex-end;">
                        <a href="index.php?batch_id=<?php echo $batchId; ?>" style="padding:10px 18px; border:1px solid #ccc; background:#fff; color:#333; text-decoration:none; border-radius:4px; font-weight:bold;">Cancel</a>
                        <button type="submit" style="padding:10px 22px; background:#1f4e79; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Save Paper</button>
                    </div>

                </form>
            </div>

        </main>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
