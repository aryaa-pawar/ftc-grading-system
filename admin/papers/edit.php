<?php

require_once __DIR__ . "/../../config/database.php";

$pageTitle = "Edit Paper";
$errorMessage = "";
$paperId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;

if ($paperId <= 0) {
    header("Location: index.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM papers WHERE paper_id = :paper_id");
    $stmt->execute([":paper_id" => $paperId]);
    $paper = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$paper) {
        die("Paper not found.");
    }
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $paperCode = trim($_POST["paper_code"] ?? "");
    $paperName = trim($_POST["paper_name"] ?? "");
    $paperType = trim($_POST["paper_type"] ?? "Theory");
    $isElective = isset($_POST["is_elective"]) ? 1 : 0;
    $maxInternal = (float)($_POST["max_internal"] ?? 30.00);
    $maxMidterm = (float)($_POST["max_midterm"] ?? 25.00);
    $maxFinal = (float)($_POST["max_final"] ?? 45.00);

    if (empty($paperCode) || empty($paperName)) {
        $errorMessage = "Paper Code and Paper Name are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE papers
                SET paper_code = :paper_code,
                    paper_name = :paper_name,
                    is_elective = :is_elective,
                    paper_type = :paper_type,
                    max_internal = :max_internal,
                    max_midterm = :max_midterm,
                    max_final = :max_final
                WHERE paper_id = :paper_id
            ");
            $stmt->execute([
                ":paper_code" => $paperCode,
                ":paper_name" => $paperName,
                ":is_elective" => $isElective,
                ":paper_type" => $paperType,
                ":max_internal" => $maxInternal,
                ":max_midterm" => $maxMidterm,
                ":max_final" => $maxFinal,
                ":paper_id" => $paperId
            ]);

            header("Location: index.php?success=1");
            exit;
        } catch (PDOException $e) {
            $errorMessage = "Failed to update paper: " . $e->getMessage();
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
                <h1 style="margin:0; font-size:26px; color:#1f4e79;">Edit Paper</h1>
                <p style="margin:5px 0 0; color:#666666;">Modify paper settings and score weightage allocations.</p>
            </div>

            <?php if (!empty($errorMessage)): ?>
                <div style="padding:15px; background:#f8d7da; color:#721c24; border-radius:4px; margin-bottom:20px;">
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>
            <?php endif; ?>

            <div style="background:#ffffff; border-radius:8px; border:1px solid #e0e0e0; padding:25px; max-width:600px; box-shadow:0 2px 4px rgba(0,0,0,0.05);">
                <form method="POST" action="">

                    <div style="margin-bottom:18px;">
                        <label style="display:block; font-weight:bold; margin-bottom:6px; color:#333;">Paper Code *</label>
                        <input type="text" name="paper_code" value="<?php echo htmlspecialchars($paper['paper_code']); ?>" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-size:14px;">
                    </div>

                    <div style="margin-bottom:18px;">
                        <label style="display:block; font-weight:bold; margin-bottom:6px; color:#333;">Paper Name *</label>
                        <input type="text" name="paper_name" value="<?php echo htmlspecialchars($paper['paper_name']); ?>" required style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-size:14px;">
                    </div>

                    <div style="margin-bottom:18px;">
                        <label style="display:block; font-weight:bold; margin-bottom:6px; color:#333;">Paper Type *</label>
                        <select name="paper_type" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-size:14px;">
                            <option value="Theory" <?php echo ($paper['paper_type'] === 'Theory') ? 'selected' : ''; ?>>Theory Paper</option>
                            <option value="Practical" <?php echo ($paper['paper_type'] === 'Practical') ? 'selected' : ''; ?>>Practical Paper</option>
                            <option value="Viva" <?php echo ($paper['paper_type'] === 'Viva') ? 'selected' : ''; ?>>Viva-Voce</option>
                            <option value="Project" <?php echo ($paper['paper_type'] === 'Project') ? 'selected' : ''; ?>>Project Work</option>
                            <option value="OJT" <?php echo ($paper['paper_type'] === 'OJT') ? 'selected' : ''; ?>>On Job Training (OJT)</option>
                        </select>
                    </div>

                    <div style="margin-bottom:18px;">
                        <label style="display:flex; align-items:center; gap:8px; font-weight:bold; cursor:pointer;">
                            <input type="checkbox" name="is_elective" value="1" <?php echo ($paper['is_elective'] == 1) ? 'checked' : ''; ?>>
                            This is an Elective Paper (Choice-based per group)
                        </label>
                    </div>

                    <div style="display:flex; gap:15px; margin-bottom:20px;">
                        <div style="flex:1;">
                            <label style="display:block; font-weight:bold; margin-bottom:6px; color:#333;">Max Internal</label>
                            <input type="number" step="0.01" name="max_internal" value="<?php echo htmlspecialchars($paper['max_internal']); ?>" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-size:14px;">
                        </div>

                        <div style="flex:1;">
                            <label style="display:block; font-weight:bold; margin-bottom:6px; color:#333;">Max Mid-Term</label>
                            <input type="number" step="0.01" name="max_midterm" value="<?php echo htmlspecialchars($paper['max_midterm']); ?>" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-size:14px;">
                        </div>

                        <div style="flex:1;">
                            <label style="display:block; font-weight:bold; margin-bottom:6px; color:#333;">Max Final Exam</label>
                            <input type="number" step="0.01" name="max_final" value="<?php echo htmlspecialchars($paper['max_final']); ?>" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:4px; font-size:14px;">
                        </div>
                    </div>

                    <div style="display:flex; gap:10px; justify-content:flex-end;">
                        <a href="index.php" style="padding:10px 18px; border:1px solid #ccc; background:#fff; color:#333; text-decoration:none; border-radius:4px; font-weight:bold;">Cancel</a>
                        <button type="submit" style="padding:10px 22px; background:#1f4e79; color:#fff; border:none; border-radius:4px; font-weight:bold; cursor:pointer;">Update Paper</button>
                    </div>

                </form>
            </div>

        </main>
    </div>

</div>

<?php require_once "../../includes/footer.php"; ?>
