<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
|--------------------------------------------------------------------------
| FTC GRADING & TABULATION MANAGEMENT SYSTEM
| ADMIN - RESULT REGISTER
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/../../includes/auth.php";
require_permission('results.view');
require_once __DIR__ . "/../../config/database.php";
require_once __DIR__ . "/../../includes/csrf.php";


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function h($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}


/*
|--------------------------------------------------------------------------
| DATE FORMAT
|--------------------------------------------------------------------------
*/

function formatDateIndian($date)
{
    if (empty($date)) {
        return "";
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return $date;
    }

    return date("d.m.Y", $timestamp);
}


/*
|--------------------------------------------------------------------------
| SELECTED BATCH
|--------------------------------------------------------------------------
*/

$selectedBatchId =
    isset($_GET["batch_id"])
        ? (int)$_GET["batch_id"]
        : 0;


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$batches = [];

$students = [];

$results = [];
$successMessage = "";
$directorName = "";
$directorNameHindi = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['lock_batch']) || isset($_POST['unlock_batch']))) {
    enforceCsrf();
    $lockBatchId = (int)($_POST['batch_id'] ?? 0);
    if ($lockBatchId > 0) {
        try {
            $lockValue = isset($_POST['unlock_batch']) ? 0 : 1;
            $stmt = $pdo->prepare('UPDATE batches SET is_locked = :is_locked WHERE batch_id = :batch_id');
            $stmt->execute([':is_locked' => $lockValue, ':batch_id' => $lockBatchId]);
            $selectedBatchId = $lockBatchId;
            $successMessage = $lockValue === 1
                ? 'Marks locked for the selected batch.'
                : 'Marks unlocked for the selected batch.';
        } catch (PDOException $e) {
            $errorMessage = 'Unable to lock marks for the selected batch.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_director'])) {
    $newDir = trim($_POST['director_name'] ?? '');
    $newDirHindi = trim($_POST['director_name_hindi'] ?? '');
    if ($newDir !== '' || $newDirHindi !== '') {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value)
                VALUES ('director_name', :insert_value)
                ON DUPLICATE KEY UPDATE setting_value = :update_value
            ");
            $stmt->execute([
                ':insert_value' => $newDir,
                ':update_value' => $newDir
            ]);
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value)
                VALUES ('director_name_hindi', :insert_value)
                ON DUPLICATE KEY UPDATE setting_value = :update_value
            ");
            $stmt->execute([
                ':insert_value' => $newDirHindi,
                ':update_value' => $newDirHindi
            ]);
            $directorName = $newDir;
            $directorNameHindi = $newDirHindi;
            $successMessage = "Director Name saved successfully!";
        } catch (PDOException $e) {
            $errorMessage = "Unable to save Director Name: " . $e->getMessage();
        }
    }
}

if (empty($directorName) || empty($directorNameHindi)) {
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('director_name', 'director_name_hindi')");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $setting) {
            if ($setting['setting_key'] === 'director_name') {
                $directorName = trim($setting['setting_value'] ?? '');
            } elseif ($setting['setting_key'] === 'director_name_hindi') {
                $directorNameHindi = trim($setting['setting_value'] ?? '');
            }
        }
        if ($directorName === '') {
            $directorName = "K. N. Mohan";
        }
    } catch (PDOException $e) {
        $directorName = "K. N. Mohan";
    }
}

$errorMessage = isset($errorMessage) ? $errorMessage : "";


/*
|--------------------------------------------------------------------------
| LOAD BATCHES
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT

            batch_id,
            batch_name,
            commenced_date,
            completed_date,
            station,
            is_locked

        FROM batches

        ORDER BY
            batch_id ASC
    ");

    $batches =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (PDOException $e) {

    $errorMessage =
        "Unable to load batches.";

}


/*
|--------------------------------------------------------------------------
| DEFAULT BATCH
|--------------------------------------------------------------------------
*/

if (
    $selectedBatchId <= 0
    &&
    !empty($batches)
) {

    $selectedBatchId =
        (int)$batches[0]["batch_id"];

}


/*
|--------------------------------------------------------------------------
| CURRENT BATCH
|--------------------------------------------------------------------------
*/

$currentBatch = null;


foreach (
    $batches as $batch
) {

    if (
        (int)$batch["batch_id"]
        ===
        $selectedBatchId
    ) {

        $currentBatch =
            $batch;

        break;

    }

}


/*
|--------------------------------------------------------------------------
| LOAD STUDENTS
|--------------------------------------------------------------------------
*/

if ($selectedBatchId > 0) {

    try {

        $stmt = $pdo->prepare("
            SELECT

                student_id,
                roll_no,
                student_name,
                designation,
                qualification,
                rmc,
                group_code

            FROM students

            WHERE

                batch_id = :batch_id

                AND is_deleted = 0

            ORDER BY

                roll_no ASC,
                student_name ASC
        ");

        $stmt->execute([
            ":batch_id" =>
                $selectedBatchId
        ]);

        $students =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


    } catch (PDOException $e) {

        $errorMessage =
            "Unable to load students.";

    }

}


/*
|--------------------------------------------------------------------------
| LOAD RESULTS
|--------------------------------------------------------------------------
*/

if (
    $selectedBatchId > 0
    &&
    !empty($students)
) {

    try {

        $stmt = $pdo->prepare("
            SELECT

                rr.student_id,

                rr.grand_total,

                rr.max_possible_marks,

                rr.percentage,

                rr.remarks,

                rr.custom_remarks

            FROM v_result_register rr

            INNER JOIN students s

                ON s.student_id =
                   rr.student_id

            WHERE

                s.batch_id = :batch_id

                AND s.is_deleted = 0

            ORDER BY

                s.roll_no ASC
        ");

        $stmt->execute([
            ":batch_id" =>
                $selectedBatchId
        ]);

        $rows =
            $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );


        foreach (
            $rows as $row
        ) {

            $results[
                (int)$row["student_id"]
            ] = $row;

        }


    } catch (PDOException $e) {

        $errorMessage =
            "Unable to load results.";

    }

}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/../../includes/header.php";

?>

<style>

/* =========================================================
   PAGE
========================================================= */

.result-page {

    background:#f4f6f8;

    min-height:100vh;

    padding:25px;

}


/* =========================================================
   PAGE HEADING
========================================================= */

.page-heading {

    margin-bottom:20px;

}


.page-heading h1 {

    margin:0;

    color:#1f4e79;

    font-size:28px;

    font-weight:bold;

}


.page-heading p {

    margin:6px 0 0;

    color:#666;

    font-size:14px;

}


/* =========================================================
   FILTER CARD
========================================================= */

.filter-card {

    background:#ffffff;

    border:1px solid #dddddd;

    border-radius:7px;

    padding:18px;

    margin-bottom:20px;

}


.filter-row {

    display:flex;

    flex-wrap:wrap;

    align-items:flex-end;

    gap:15px;

}


.filter-field {

    display:flex;

    flex-direction:column;

    gap:6px;

}


.filter-field label {

    color:#555;

    font-size:12px;

    font-weight:bold;

}


.filter-field select {

    min-width:250px;

    height:39px;

    padding:0 10px;

    border:1px solid #bbbbbb;

    border-radius:5px;

    background:#ffffff;

    font-size:14px;

}


.btn {

    height:39px;

    padding:0 18px;

    border:0;

    border-radius:5px;

    cursor:pointer;

    font-size:14px;

    font-weight:bold;

}


.btn-view {

    background:#1f4e79;

    color:#ffffff;

}


.btn-print {

    background:#777777;

    color:#ffffff;

}


/* =========================================================
   BATCH INFO
========================================================= */

.batch-info {

    background:#ffffff;

    border:1px solid #dddddd;

    border-radius:7px;

    padding:14px 17px;

    margin-bottom:20px;

    color:#444;

    font-size:14px;

}


.batch-info strong {

    color:#222;

}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper {

    background:#ffffff;

    border:1px solid #dddddd;

    border-radius:7px;

    overflow:auto;

}


.result-table {

    width:100%;

    min-width:1100px;

    border-collapse:collapse;

}


.result-table th {

    background:#1f4e79;

    color:#ffffff;

    border:1px solid #ffffff;

    padding:11px 9px;

    text-align:center;

    font-size:13px;

}


.result-table td {

    border:1px solid #cccccc;

    padding:10px 9px;

    text-align:center;

    font-size:13px;

}


.result-table tbody tr:hover {

    background:#f7f9fa;

}


.name-cell {

    text-align:left !important;

    font-weight:bold;

}


/* =========================================================
   RESULT STATUS
========================================================= */

.pass {

    display:inline-block;

    color:#146c2e;

    background:#d9f2df;

    padding:5px 10px;

    border-radius:4px;

    font-size:11px;

    font-weight:bold;

}


.fail {

    display:inline-block;

    color:#a12622;

    background:#f8d7da;

    padding:5px 10px;

    border-radius:4px;

    font-size:11px;

    font-weight:bold;

}


.pending {

    color:#777777;

    font-size:12px;

}


/* =========================================================
   VIEW RESULT BUTTON
========================================================= */

.action {

    display:inline-block;

    padding:8px 13px;

    border-radius:5px;

    text-decoration:none;

    font-size:12px;

    font-weight:bold;

}


.view-action {

    background:#1f4e79;

    color:#ffffff;

}


.view-action:hover {

    background:#163a5b;

    color:#ffffff;

}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    padding:60px 30px;

    text-align:center;

    color:#777777;

}


.empty h2 {

    margin:0 0 8px;

    color:#555555;

}


.empty p {

    margin:0;

}


/* =========================================================
   ERROR
========================================================= */

.error-box {

    background:#fdecea;

    border:1px solid #e2b8b5;

    color:#a12622;

    padding:14px 16px;

    border-radius:6px;

    margin-bottom:20px;

}


/* =========================================================
   MOBILE
========================================================= */

@media(max-width:800px) {

    .result-page {

        padding:15px;

    }


    .filter-row {

        align-items:stretch;

        flex-direction:column;

    }


    .filter-field select {

        width:100%;

    }


    .btn {

        width:100%;

    }

}


/* =========================================================
   PRINT
========================================================= */

@media print {

    .no-print {

        display:none !important;

    }


    .result-page {

        padding:0;

        background:#ffffff;

    }


    .table-wrapper {

        border:0;

    }

}

</style>


<div style="
    display:flex;
    flex-direction:column;
    min-height:100vh;
">


<?php

require_once __DIR__ . "/../../includes/navbar.php";

?>


<div style="
    display:flex;
    flex:1;
    min-height:0;
">


<?php

require_once __DIR__ . "/../../includes/sidebar.php";

?>


<main style="
    flex:1;
    min-width:0;
">


<div class="result-page">


<!-- =====================================================
     PAGE HEADING
====================================================== -->

<div class="page-heading">

<h1>

Result Register

</h1>


<p>

View student results for the selected batch.

</p>

</div>


<!-- =====================================================
     ERROR
====================================================== -->

<?php if ($successMessage !== ""): ?>
<div class="success-box" style="background:#d4edda; color:#155724; border:1px solid #c3e6cb; padding:12px 18px; border-radius:6px; margin-bottom:15px; font-weight:bold;">
    <?= h($successMessage) ?>
</div>
<?php endif; ?>

<?php if ($errorMessage !== ""): ?>
<div class="error-box">
<?= h($errorMessage) ?>
</div>
<?php endif; ?>


<!-- =====================================================
     FILTER & SETTINGS
====================================================== -->

<div class="filter-card no-print">

<form method="POST" action="result_register.php?batch_id=<?= $selectedBatchId ?>">
    <?php echo csrfField(); ?>
    <input type="hidden" name="batch_id" value="<?= $selectedBatchId ?>">

    <div class="filter-row" style="display:flex; flex-wrap:wrap; align-items:flex-end; gap:15px;">

        <!-- BATCH SELECTOR -->
        <div class="filter-field">
            <label style="font-weight:bold; font-size:13px; margin-bottom:6px; display:block;">Batch No -</label>
            <select name="batch_id" style="height:38px; border:1px solid #cccccc; border-radius:4px; padding:0 12px; font-size:13px; min-width:200px;" onchange="window.location.href='result_register.php?batch_id=' + this.value">
                <?php foreach ($batches as $batch): ?>
                    <option value="<?= (int)$batch["batch_id"] ?>" <?= ((int)$batch["batch_id"] === $selectedBatchId) ? "selected" : "" ?>>
                        <?= h($batch["batch_name"]) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- DIRECTOR NAME & SAVE BUTTON -->
        <div class="filter-field">
            <label style="font-weight:bold; font-size:13px; margin-bottom:6px; display:block;">Director Name (Hindi / English):</label>
            <div style="display:flex; gap:8px;">
                <input
                    type="text"
                    id="director_name_input"
                    name="director_name"
                    value="<?= h($directorName) ?>"
                    placeholder="Enter Director Name"
                    style="height:38px; border:1px solid #cccccc; border-radius:4px; padding:0 12px; font-size:13px; min-width:220px;"
                >
                <button
                    type="button"
                    id="convert_director_name_button"
                    onclick="convertDirectorNameToHindi()"
                    style="height:38px; background:#1f4e79; color:#ffffff; border:none; border-radius:4px; padding:0 12px; font-weight:bold; cursor:pointer; font-size:13px;"
                >
                    Convert
                </button>
                <select
                    id="director_name_hindi_suggestions"
                    onchange="document.getElementById('director_name_hindi_input').value = this.value"
                    style="display:none; height:38px; border:1px solid #cccccc; border-radius:4px; padding:0 8px; font-size:13px; min-width:180px;"
                >
                    <option value="">Choose spelling</option>
                </select>
                <input
                    type="text"
                    id="director_name_hindi_input"
                    name="director_name_hindi"
                    value="<?= h($directorNameHindi) ?>"
                    placeholder="नाम हिंदी में"
                    style="height:38px; border:1px solid #cccccc; border-radius:4px; padding:0 12px; font-size:13px; min-width:220px;"
                >
                <button
                    type="submit"
                    name="save_director"
                    value="1"
                    style="height:38px; background:#27ae60; color:#ffffff; border:none; border-radius:4px; padding:0 18px; font-weight:bold; cursor:pointer; font-size:13px;"
                >
                    Save
                </button>
            </div>
        </div>

        <div class="filter-field">
            <label style="font-weight:bold; font-size:13px; margin-bottom:6px; display:block;">Marks Control:</label>
            <?php if (!empty($currentBatch['is_locked'])): ?>
                <button type="submit" name="unlock_batch" value="1" style="height:38px; background:#198754; color:#ffffff; border:none; border-radius:4px; padding:0 14px; font-weight:bold; cursor:pointer; font-size:13px;">Unlock Marks</button>
            <?php else: ?>
                <button type="submit" name="lock_batch" value="1" style="height:38px; background:#c0392b; color:#ffffff; border:none; border-radius:4px; padding:0 14px; font-weight:bold; cursor:pointer; font-size:13px;">Lock Marks</button>
            <?php endif; ?>
        </div>

        <div class="filter-field">
            <label style="font-weight:bold; font-size:13px; margin-bottom:6px; display:block;">Certificate Panel:</label>
            <a href="certificates.php?batch_id=<?= $selectedBatchId ?>" style="height:38px; background:#0d6efd; color:#ffffff; border:none; border-radius:4px; padding:0 14px; font-weight:bold; text-decoration:none; display:inline-flex; align-items:center; font-size:13px;">📜 Certificate Panel</a>
        </div>

    </div>
</form>

</div>


<!-- =====================================================
     BATCH INFORMATION
====================================================== -->

<?php if (
    $currentBatch
): ?>


<div class="batch-info">


<strong>
Batch No:
</strong>

<?= h(
    $currentBatch["batch_name"]
) ?>


&nbsp;&nbsp; | &nbsp;&nbsp;


<strong>
Station:
</strong>

<?= h(
    $currentBatch["station"]
    ?? ""
) ?>


&nbsp;&nbsp; | &nbsp;&nbsp;


<strong>
Commenced:
</strong>

<?= h(
    formatDateIndian(
        $currentBatch["commenced_date"]
        ?? ""
    )
) ?>


&nbsp;&nbsp; | &nbsp;&nbsp;


<strong>
Completed:
</strong>

<?= h(
    formatDateIndian(
        $currentBatch["completed_date"]
        ?? ""
    )
) ?>


</div>


<?php endif; ?>


<!-- =====================================================
     RESULT TABLE
====================================================== -->

<div class="table-wrapper">


<?php if (
    empty($students)
): ?>


<div class="empty">


<h2>

No students found

</h2>


<p>

No active students are assigned to this batch.

</p>


</div>


<?php else: ?>


<table class="result-table">


<thead>


<tr>

<th>
S.No.
</th>

<th>
Name
</th>

<th>
Designation
</th>

<th>
Qualification
</th>

<th>
Roll No.
</th>

<th>
Grand Total
</th>

<th>
Max Marks
</th>

<th>
%
</th>

<th>
Result
</th>

<th class="no-print">
Actions
</th>

</tr>


</thead>


<tbody>


<?php

$serial = 1;


foreach (
    $students as $student
):


$studentId =
    (int)$student["student_id"];


$result =
    $results[$studentId]
    ??
    null;


$percentage =
    $result["percentage"]
    ??
    null;


?>


<tr>


<!-- =====================================================
     SERIAL
====================================================== -->

<td>

<?= $serial ?>

</td>


<!-- =====================================================
     NAME
====================================================== -->

<td class="name-cell">

<?= h(
    $student["student_name"]
) ?>

</td>


<!-- =====================================================
     DESIGNATION
====================================================== -->

<td>

<?= h(
    $student["designation"]
) ?>

</td>


<!-- =====================================================
     QUALIFICATION
====================================================== -->

<td>

<?= h(
    $student["qualification"]
) ?>

</td>


<!-- =====================================================
     ROLL NUMBER
====================================================== -->

<td>

<?= h(
    $student["roll_no"]
) ?>

</td>


<!-- =====================================================
     GRAND TOTAL
====================================================== -->

<td>

<?= h(
    $result["grand_total"]
    ?? "—"
) ?>

</td>


<!-- =====================================================
     MAX MARKS
====================================================== -->

<td>

<?= h(
    $result["max_possible_marks"]
    ?? "—"
) ?>

</td>


<!-- =====================================================
     PERCENTAGE
====================================================== -->

<td>


<?php if (
    $percentage !== null
): ?>


<?= number_format(
    (float)$percentage,
    2
) ?>%


<?php else: ?>


—

<?php endif; ?>


</td>


<!-- =====================================================
     RESULT
====================================================== -->

<td>


<?php if (
    $percentage === null
): ?>


<span class="pending">

Pending

</span>


<?php elseif (
    (float)$percentage >= 50
): ?>


<span class="pass">

PASS

</span>


<?php else: ?>


<span class="fail">

FAIL

</span>


<?php endif; ?>


</td>


<!-- =====================================================
     ONLY ACTION = VIEW RESULT
====================================================== -->

<td class="no-print">

<button
    type="button"
    class="action print-action"
    style="background:#1f4e79; color:#ffffff; border:none; padding:7px 14px; border-radius:4px; font-weight:bold; cursor:pointer;"
    onclick="openPrintResult(<?= $studentId ?>)"
>
    Print Result
</button>

</td>


</tr>


<?php

$serial++;

endforeach;

?>


</tbody>


</table>


<?php endif; ?>


</div>


</div>


</main>


</div>

<script>
function openPrintResult(studentId) {
    var dirInput = document.getElementById('director_name_input');
    var dirHindiInput = document.getElementById('director_name_hindi_input');
    var dirName = dirInput ? dirInput.value.trim() : 'K. N. Mohan';
    var dirNameHindi = dirHindiInput ? dirHindiInput.value.trim() : '';
    var url = 'student_result.php?student_id=' + studentId + '&director_name=' + encodeURIComponent(dirName) + '&director_name_hindi=' + encodeURIComponent(dirNameHindi);
    window.open(url, '_blank');
}

async function convertDirectorNameToHindi() {
    var englishInput = document.getElementById('director_name_input');
    var hindiInput = document.getElementById('director_name_hindi_input');
    var suggestions = document.getElementById('director_name_hindi_suggestions');
    var convertButton = document.getElementById('convert_director_name_button');
    var englishName = englishInput ? englishInput.value.trim() : '';

    if (!englishName || !hindiInput || !convertButton) {
        return;
    }

    convertButton.disabled = true;
    convertButton.textContent = 'Converting...';

    try {
        var endpoint = 'https://inputtools.google.com/request?text=' + encodeURIComponent(englishName) + '&itc=hi-t-i0-und&num=5&ie=utf-8&oe=utf-8';
        var response = await fetch(endpoint);
        var data = await response.json();
        var hindiSuggestions = data[1] && data[1][0] && data[1][0][1] ? data[1][0][1] : [];

        if (data[0] !== 'SUCCESS' || !hindiSuggestions.length) {
            throw new Error('No Hindi suggestion found');
        }

        hindiInput.value = hindiSuggestions[0];
        suggestions.innerHTML = '<option value="">Choose spelling</option>';
        hindiSuggestions.forEach(function (suggestion) {
            var option = document.createElement('option');
            option.value = suggestion;
            option.textContent = suggestion;
            suggestions.appendChild(option);
        });
        suggestions.style.display = hindiSuggestions.length > 1 ? 'block' : 'none';
    } catch (error) {
        alert('Hindi conversion could not be completed. Please enter the Hindi name manually.');
    } finally {
        convertButton.disabled = false;
        convertButton.textContent = 'Convert';
    }
}
</script>


<?php

require_once __DIR__ . "/../../includes/footer.php";

?>


</div>