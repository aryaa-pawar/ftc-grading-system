<?php
// admin/results/certificates.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
|--------------------------------------------------------------------------
| FTC GRADING & TABULATION MANAGEMENT SYSTEM
| ADMIN - CERTIFICATE PANEL
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/../../includes/auth.php";
require_permission('results.view');
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/csrf.php";

function h($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$selectedBatchId = isset($_GET["batch_id"]) ? (int)$_GET["batch_id"] : 0;

$batches = [];
$students = [];
$results = [];
$successMessage = "";
$errorMessage = "";
$directorName = "";
$directorNameHindi = "";

$pdo = getDbConnection();

// Save per-student certificate prefix and Devanagari name.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_students_hindi'])) {
    enforceCsrf();
    $prefixes = $_POST['name_prefix'] ?? [];
    $hindiNames = $_POST['student_name_hindi'] ?? [];
    try {
        $stmtUpdate = $pdo->prepare('UPDATE students SET name_prefix = :pfx, student_name_hindi = :hin WHERE student_id = :sid');
        foreach ($prefixes as $sid => $pfx) {
            $sid = (int)$sid;
            $stmtUpdate->execute([
                ':pfx' => trim($pfx) !== '' ? trim($pfx) : 'Mr.',
                ':hin' => trim($hindiNames[$sid] ?? '') ?: null,
                ':sid' => $sid,
            ]);
        }
        $successMessage = 'Student prefixes and Devanagari names saved successfully.';
    } catch (PDOException $e) {
        $errorMessage = 'Unable to save student Devanagari details: ' . $e->getMessage();
    }
}

// Load Batches
try {
    $stmt = $pdo->query("
        SELECT batch_id, batch_name, batch_number, commenced_date, completed_date, station, director_name, director_name_hindi
        FROM batches
        ORDER BY batch_id ASC
    ");
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = "Unable to load batches: " . $e->getMessage();
}

if ($selectedBatchId <= 0 && !empty($batches)) {
    $selectedBatchId = (int)$batches[0]["batch_id"];
}

$currentBatch = null;
foreach ($batches as $batch) {
    if ((int)$batch["batch_id"] === $selectedBatchId) {
        $currentBatch = $batch;
        break;
    }
}

if ($currentBatch) {
    $directorName = !empty($currentBatch['director_name']) ? $currentBatch['director_name'] : "K. N. Mohan";
    $directorNameHindi = !empty($currentBatch['director_name_hindi']) ? $currentBatch['director_name_hindi'] : "के.एन.मोहन";
} else {
    $directorName = "K. N. Mohan";
    $directorNameHindi = "के.एन.मोहन";
}

// Load Students & Performance for Selected Batch
if ($selectedBatchId > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT s.student_id, s.roll_no, s.student_name, s.name_prefix, s.student_name_hindi, s.designation, s.qualification, s.rmc, s.group_code,
                   s.generated_director_name, s.generated_director_name_hindi, s.certificate_generated_at,
                   r.grand_total, r.percentage, r.remarks
            FROM students s
            LEFT JOIN v_result_register r ON r.student_id = s.student_id
            WHERE s.batch_id = :batch_id AND (s.is_deleted = 0 OR s.is_deleted IS NULL)
            ORDER BY s.roll_no ASC, s.student_name ASC
        ");
        $stmt->execute([":batch_id" => $selectedBatchId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errorMessage = "Unable to load student data: " . $e->getMessage();
    }
}

require_once __DIR__ . "/../../includes/header.php";

?>

<div style="display:flex; flex-direction:column; min-height:100vh;">
<?php require_once __DIR__ . "/../../includes/navbar.php"; ?>
<div style="display:flex; flex:1; min-height:0;">
<?php require_once __DIR__ . "/../../includes/sidebar.php"; ?>
<main style="flex:1; min-width:0;">

<style>
.cert-page {
    background: #f4f6f8;
    min-height: 100vh;
    padding: 30px;
}
.filter-box {
    background: #ffffff;
    border: 1px solid #d9d9d9;
    padding: 20px 24px;
    margin-top: 20px;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
.filter-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 15px;
}
.filter-field {
    display: flex;
    flex-direction: column;
}
.filter-field label {
    font-size: 13px;
    font-weight: bold;
    color: #444444;
    margin-bottom: 6px;
}
.select-box, .input-box {
    height: 38px;
    border: 1px solid #cccccc;
    border-radius: 4px;
    padding: 0 12px;
    font-size: 13px;
    background: #ffffff;
}
.select-box { min-width: 260px; }
.input-box { min-width: 200px; }
.btn-action {
    height: 38px;
    background: #1f4e79;
    color: #ffffff;
    border: none;
    padding: 0 18px;
    border-radius: 4px;
    font-size: 13px;
    font-weight: bold;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.btn-action:hover { background: #163a5c; }
.btn-success { background: #16a34a; }
.btn-success:hover { background: #15803d; }
.table-wrapper {
    background: #ffffff;
    border: 1px solid #d9d9d9;
    border-radius: 6px;
    margin-top: 20px;
    overflow-x: auto;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.cert-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.cert-table th {
    background: #1f4e79;
    color: #ffffff;
    padding: 12px 14px;
    text-align: left;
    font-weight: bold;
    border: 1px solid #163a5c;
    white-space: nowrap;
}
.cert-table td {
    padding: 10px 14px;
    border: 1px solid #e5e5e5;
    color: #222222;
}
.cert-table tr:nth-child(even) { background: #f9fbfd; }
.cert-table tr:hover { background: #f1f5f9; }
.badge-status {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
    display: inline-block;
}
.badge-gen { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.badge-none { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
</style>

<div class="cert-page">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <h1 style="margin:0; color:#1f4e79; font-size:26px;">Certificate Panel</h1>
            <p style="margin:4px 0 0 0; color:#666; font-size:14px;">Generate and manage official WMO/IMD Forecaster Training Certificates.</p>
        </div>
        <div>
            <a href="result_register.php?batch_id=<?= $selectedBatchId ?>" class="btn-action" style="background:#4b5563;">← Result Register</a>
        </div>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div style="background:#d1e7dd; color:#0f5132; padding:12px 16px; border-radius:4px; margin-top:15px; border:1px solid #badbcc;">
            <?= h($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div style="background:#f8d7da; color:#842029; padding:12px 16px; border-radius:4px; margin-top:15px; border:1px solid #f5c2c7;">
            <?= h($errorMessage) ?>
        </div>
    <?php endif; ?>

    <!-- BATCH FILTER & ASSIGNED DIRECTOR INFO -->
    <div class="filter-box">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px;">
            <form method="GET" action="certificates.php" style="margin:0;">
                <div class="filter-row">
                    <div class="filter-field">
                        <label>Select Batch:</label>
                        <select name="batch_id" class="select-box" onchange="this.form.submit()">
                            <?php foreach ($batches as $batch): ?>
                                <option value="<?= $batch['batch_id'] ?>" <?= (int)$batch['batch_id'] === $selectedBatchId ? 'selected' : '' ?>>
                                    [Batch <?= h($batch['batch_number'] ?? $batch['batch_id']) ?>] <?= h($batch['batch_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-field">
                        <button type="submit" class="btn-action">Load Batch</button>
                    </div>
                </div>
            </form>

            <?php if ($currentBatch): ?>
                <div style="padding:10px 16px; background:#eaf2f8; border:1px solid #c8d9e6; border-radius:6px; font-size:13px; color:#1f4e79;">
                    <strong>Assigned Director for this Batch:</strong> 
                    <?= h($directorName) ?> (<?= h($directorNameHindi) ?>)
                    <a href="../batches/edit.php?id=<?= $selectedBatchId ?>" style="margin-left:10px; color:#0284c7; text-decoration:underline; font-weight:bold;">⚙️ Edit in Batch Management</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- STUDENT CERTIFICATE TABLE FORM -->
    <?php if (!empty($currentBatch)): ?>
        <form method="POST" action="certificates.php?batch_id=<?= $selectedBatchId ?>" style="margin-top:20px;">
            <?= csrfField() ?>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; flex-wrap:wrap; gap:10px;">
                <div>
                    <h3 style="margin:0; color:#1f4e79; font-size:18px;">Student Certificates for [Batch <?= h($currentBatch['batch_number'] ?? $currentBatch['batch_id']) ?>] <?= h($currentBatch['batch_name']) ?></h3>
                    <p style="margin:2px 0 0 0; color:#666; font-size:13px;">Select a prefix and verify each Devanagari name before generating certificates.</p>
                </div>
                <div style="display:flex; gap:10px;">
                    <button type="button" id="btn_convert_all" onclick="autoConvertAllStudentsToHindi()" class="btn-action" style="background:#0284c7;">⚡ Auto-Convert All Names to Devanagari</button>
                    <button type="submit" name="save_students_hindi" class="btn-action btn-success">💾 Save Student Prefixes &amp; Hindi Names</button>
                </div>
            </div>

            <div class="table-wrapper">
                <table class="cert-table">
                    <thead>
                        <tr>
                            <th style="width:40px;">S.No.</th>
                            <th style="width:140px;">Roll No.</th>
                            <th style="width:100px;">Prefix</th>
                            <th>Student Name (English)</th>
                            <th style="min-width:320px;">Student Name (Devanagari / Hindi)</th>
                            <th>Performance Level</th>
                            <th>Certificate Status</th>
                            <th style="text-align:center; width:170px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:25px; color:#777;">No active students found in this batch.</td>
                            </tr>
                        <?php else: ?>
                            <?php $sn = 1; foreach ($students as $student): ?>
                                <?php
                                    $sid = (int)$student['student_id'];
                                    $pfxVal = !empty($student['name_prefix']) ? $student['name_prefix'] : 'Mr.';
                                    $isGen = !empty($student['generated_director_name']);
                                    $pct = isset($student['percentage']) ? (float)$student['percentage'] : null;
                                    $perfLabel = 'Satisfactory';
                                    if ($pct !== null) {
                                        if ($pct >= 90) $perfLabel = 'Outstanding';
                                        elseif ($pct >= 80) $perfLabel = 'Very Good';
                                        elseif ($pct >= 70) $perfLabel = 'Very Good';
                                        elseif ($pct >= 60) $perfLabel = 'Good';
                                    }
                                ?>
                                <tr>
                                    <td><?= $sn++ ?></td>
                                    <td><strong><?= h($student['roll_no']) ?></strong></td>
                                    <td><select name="name_prefix[<?= $sid ?>]" class="select-box" style="min-width:85px; height:32px; padding:0 4px; font-size:12px;">
                                        <?php foreach (['Mr.' => 'Mr. / श्री', 'Ms.' => 'Ms. / कुमारी', 'Mrs.' => 'Mrs. / सौ.', 'Dr.' => 'Dr. / डॉ.', 'Shri' => 'Shri / श्री', 'Smt.' => 'Smt. / श्रीमती'] as $prefix => $label): ?>
                                            <option value="<?= h($prefix) ?>" <?= $pfxVal === $prefix ? 'selected' : '' ?>><?= h($label) ?></option>
                                        <?php endforeach; ?>
                                    </select></td>
                                    <td><strong><?= h($student['student_name']) ?></strong></td>
                                    <td><div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                                        <input type="text" id="st_hindi_<?= $sid ?>" name="student_name_hindi[<?= $sid ?>]" value="<?= h($student['student_name_hindi'] ?? '') ?>" placeholder="नाम हिंदी में" class="input-box st-hindi-input" data-english="<?= h($student['student_name']) ?>" style="height:32px; min-width:140px; font-size:12px;">
                                        <button type="button" onclick="convertSingleStudentHindi(<?= $sid ?>)" class="btn-action" style="height:32px; padding:0 8px; font-size:11px; background:#1f4e79;">Convert</button>
                                        <select id="st_sug_<?= $sid ?>" onchange="document.getElementById('st_hindi_<?= $sid ?>').value = this.value" class="select-box" style="display:none; height:32px; min-width:110px; font-size:11px; padding:0 4px;"><option value="">Choose</option></select>
                                    </div></td>
                                    <td><?= h($perfLabel) ?> (<?= $pct !== null ? number_format($pct, 2) . '%' : '-' ?>)</td>
                                    <td>
                                        <?php if ($isGen): ?>
                                            <?php
                                                $genTs = !empty($student['certificate_generated_at']) ? strtotime($student['certificate_generated_at']) : 0;
                                                $formattedGenDate = ($genTs && $genTs > 0) ? date('d.m.Y', $genTs) : date('d.m.Y');
                                            ?>
                                            <span class="badge-status badge-gen">Generated on <?= $formattedGenDate ?></span>
                                        <?php else: ?>
                                            <span class="badge-status badge-none">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <a href="view_certificate.php?student_id=<?= $sid ?>&director_name=<?= urlencode($directorName) ?>&director_name_hindi=<?= urlencode($directorNameHindi) ?>"
                                           target="_blank"
                                           class="btn-action"
                                           style="padding:6px 12px; font-size:12px;">
                                            <?= $isGen ? '📄 View / Print' : '📜 Generate' ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>
    <?php endif; ?>

</div>

<script>

async function convertSingleStudentHindi(studentId) {
    var hindiInput = document.getElementById('st_hindi_' + studentId);
    var sugSelect = document.getElementById('st_sug_' + studentId);
    if (!hindiInput) return;
    
    var rawName = hindiInput.getAttribute('data-english') || '';
    var cleanName = rawName.replace(/^(Mr\.|Ms\.|Mrs\.|Shri|Smt\.|Dr\.)\s*/i, '').trim();
    if (!cleanName) return;

    try {
        var endpoint = 'https://inputtools.google.com/request?text=' + encodeURIComponent(cleanName) + '&itc=hi-t-i0-und&num=5&ie=utf-8&oe=utf-8';
        var res = await fetch(endpoint);
        var data = await res.json();
        var suggestions = data[1] && data[1][0] && data[1][0][1] ? data[1][0][1] : [];
        if (suggestions.length > 0) {
            hindiInput.value = suggestions[0];
            if (sugSelect) {
                sugSelect.innerHTML = '<option value="">Choose spelling</option>';
                suggestions.forEach(function(s) {
                    var opt = document.createElement('option');
                    opt.value = s;
                    opt.textContent = s;
                    sugSelect.appendChild(opt);
                });
                sugSelect.style.display = suggestions.length > 1 ? 'inline-block' : 'none';
            }
        }
    } catch(e) {}
}

async function autoConvertAllStudentsToHindi() {
    var inputs = document.querySelectorAll('.st-hindi-input');
    var btn = document.getElementById('btn_convert_all');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Converting All...';
    }
    for (var i = 0; i < inputs.length; i++) {
        var inp = inputs[i];
        var sid = inp.id.replace('st_hindi_', '');
        await convertSingleStudentHindi(sid);
    }
    if (btn) {
        btn.disabled = false;
        btn.textContent = '⚡ Auto-Convert All Names to Devanagari';
    }
}
</script>

</main>
</div>
</div>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>
