<?php

require_once __DIR__ . "/../../includes/auth.php";
require_permission('results.download');

error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
|--------------------------------------------------------------------------
| FTC GRADING & TABULATION MANAGEMENT SYSTEM
| A4 RESULT DOWNLOAD / PRINT PAGE
|--------------------------------------------------------------------------
|
| File:
| admin/results/download_result.php
|
| This page contains ONLY the official result sheet.
| No admin sidebar.
| No navbar.
| No page heading.
| No buttons.
|
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/../../config/database.php";


/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
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


function getPerformance($percentage)
{
    if ($percentage === null) {
        return "";
    }

    $percentage = (float)$percentage;

    if ($percentage >= 90) {
        return "Outstanding";
    }

    if ($percentage >= 80) {
        return "Excellent";
    }

    if ($percentage >= 70) {
        return "Very Good";
    }

    if ($percentage >= 60) {
        return "Good";
    }

    if ($percentage >= 50) {
        return "Passed";
    }

    return "Failed";
}


/*
|--------------------------------------------------------------------------
| STUDENT ID
|--------------------------------------------------------------------------
*/

$studentId =
    isset($_GET["student_id"])
        ? (int)$_GET["student_id"]
        : 0;


if ($studentId <= 0) {

    die("Invalid student.");

}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$student = null;

$marks = [];

$result = null;


/*
|--------------------------------------------------------------------------
| LOAD STUDENT
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT

            s.student_id,
            s.batch_id,
            s.roll_no,
            s.student_name,
            s.designation,
            s.qualification,
            s.rmc,
            s.group_code,
            s.custom_remarks,

            b.batch_name,
            b.commenced_date,
            b.completed_date,
            b.station

        FROM students s

        LEFT JOIN batches b
            ON b.batch_id = s.batch_id

        WHERE
            s.student_id = :student_id
            AND s.is_deleted = 0

        LIMIT 1
    ");

    $stmt->execute([
        ":student_id" => $studentId
    ]);

    $student =
        $stmt->fetch(PDO::FETCH_ASSOC);


} catch (PDOException $e) {

    die("Unable to load student information.");

}


if (!$student) {

    die("Student not found.");

}


/*
|--------------------------------------------------------------------------
| LOAD MARKS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT

            vt.student_id,
            vt.paper_id,

            vt.internal_score,
            vt.midterm_score,
            vt.final_exam_score,
            vt.combined_total,

            p.paper_code,
            p.paper_name,

            p.max_internal,
            p.max_midterm,
            p.max_final

        FROM v_tabulation vt

        INNER JOIN papers p
            ON p.paper_id = vt.paper_id

        WHERE
            vt.student_id = :student_id

        ORDER BY
            vt.paper_id ASC
    ");

    $stmt->execute([
        ":student_id" => $studentId
    ]);

    $marks =
        $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {

    die("Unable to load student marks.");

}


/*
|--------------------------------------------------------------------------
| LOAD RESULT
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT

            student_id,

            grand_total,
            max_possible_marks,

            percentage,

            remarks,
            custom_remarks

        FROM v_result_register

        WHERE
            student_id = :student_id

        LIMIT 1
    ");

    $stmt->execute([
        ":student_id" => $studentId
    ]);

    $result =
        $stmt->fetch(PDO::FETCH_ASSOC);


} catch (PDOException $e) {

    die("Unable to load result.");

}


/*
|--------------------------------------------------------------------------
| RESULT VALUES
|--------------------------------------------------------------------------
*/

$grandTotal =
    $result["grand_total"]
    ?? 0;


$maxPossibleMarks =
    $result["max_possible_marks"]
    ?? 0;


$percentage =
    $result["percentage"]
    ?? null;


$remarks =
    $result["remarks"]
    ?? "";


/*
|--------------------------------------------------------------------------
| CUSTOM REMARK
|--------------------------------------------------------------------------
*/

$customRemarks =
    $result["custom_remarks"]
    ??
    $student["custom_remarks"]
    ??
    "";


$performance =
    getPerformance(
        $percentage
    );


$isPassed =
    (
        $percentage !== null
        &&
        (float)$percentage >= 50
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>

Course Result -
<?= h($student["student_name"]) ?>

</title>


<style>

/* =========================================================
   A4 PAGE SETTINGS
========================================================= */

@page {

    size: A4 portrait;

    margin: 10mm;

}


/* =========================================================
   GLOBAL
========================================================= */

html,
body {

    width: 210mm;

    min-height: 297mm;

    margin: 0;

    padding: 0;

    background: #ffffff;

}


body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    color: #111111;

}


/* =========================================================
   ONLY RESULT SHEET
========================================================= */

.result-sheet {

    width: 190mm;

    min-height: 277mm;

    margin: 0 auto;

    background: #ffffff;

    box-sizing: border-box;

}


/* =========================================================
   INSTITUTE HEADER
========================================================= */

.institute-header {

    text-align: center;

    border-bottom: 2px solid #1f4e79;

    padding-bottom: 12px;

}


.department {

    color: #1f4e79;

    font-size: 24px;

    font-weight: bold;

    line-height: 1.2;

}


.institute {

    color: #111111;

    font-size: 19px;

    font-weight: bold;

    margin-top: 4px;

}


.institute-subtitle {

    color: #555555;

    font-size: 13px;

    line-height: 1.45;

    margin-top: 4px;

}


/* =========================================================
   COURSE TITLE
========================================================= */

.course-heading {

    text-align: center;

    padding: 13px 0 12px;

}


.course-heading h2 {

    margin: 0;

    font-size: 21px;

    font-weight: bold;

}


.course-heading p {

    margin: 4px 0 0;

    color: #555555;

    font-size: 13px;

}


/* =========================================================
   BATCH DETAILS
========================================================= */

.batch-table {

    width: 100%;

    border-collapse: collapse;

    margin-bottom: 18px;

}


.batch-table td {

    width: 25%;

    border: 1px solid #777777;

    padding: 7px;

    text-align: center;

    vertical-align: middle;

}


.batch-label {

    font-size: 11px;

    font-weight: bold;

}


.batch-value {

    margin-top: 3px;

    font-size: 12px;

}


/* =========================================================
   TRAINEE DETAILS
========================================================= */

.section-heading {

    background: #eef1f3;

    border: 1px solid #777777;

    padding: 8px 10px;

    font-size: 14px;

    font-weight: bold;

}


.trainee-table {

    width: 100%;

    border-collapse: collapse;

}


.trainee-table td {

    border: 1px solid #777777;

    padding: 8px;

    vertical-align: middle;

}


.trainee-label {

    width: 16%;

    font-size: 11px;

    font-weight: bold;

}


.trainee-value {

    width: 34%;

    font-size: 12px;

}


/* =========================================================
   SUBJECT-WISE MARKS
========================================================= */

.subject-heading {

    color: #1f4e79;

    font-size: 18px;

    font-weight: bold;

    margin-top: 19px;

    margin-bottom: 7px;

}


.subject-table {

    width: 100%;

    border-collapse: collapse;

}


.subject-table th {

    background: #f0f2f3;

    border: 1px solid #777777;

    padding: 7px;

    text-align: center;

    font-size: 11px;

}


.subject-table td {

    border: 1px solid #777777;

    padding: 7px;

    text-align: center;

    font-size: 11px;

}


.subject-cell {

    text-align: left !important;

}


.subject-name {

    display: block;

    font-weight: bold;

}


.paper-code {

    display: block;

    color: #555555;

    font-size: 9px;

    margin-top: 2px;

}


/* =========================================================
   SUMMARY
========================================================= */

.summary-table {

    width: 100%;

    border-collapse: collapse;

    margin-top: 18px;

}


.summary-table td {

    border: 1px solid #777777;

    padding: 9px;

}


.summary-label {

    width: 35%;

    background: #eef1f3;

    font-size: 15px;

    font-weight: bold;

}


.summary-value {

    font-size: 14px;

    font-weight: bold;

}


.result-pass {

    display: inline-block;

    background: #d9f2df;

    color: #146c2e;

    padding: 4px 12px;

    border-radius: 15px;

    font-size: 11px;

}


.result-fail {

    display: inline-block;

    background: #f8d7da;

    color: #a12622;

    padding: 4px 12px;

    border-radius: 15px;

    font-size: 11px;

}


.result-pending {

    display: inline-block;

    background: #eeeeee;

    color: #777777;

    padding: 4px 12px;

    border-radius: 15px;

    font-size: 11px;

}


/* =========================================================
   REMARKS
========================================================= */

.remarks-table {

    width: 100%;

    border-collapse: collapse;

    margin-top: 18px;

}


.remarks-table td {

    border: 1px solid #777777;

    padding: 9px;

}


.remarks-title {

    background: #eef1f3;

    font-weight: bold;

    font-size: 13px;

}


.remarks-value {

    min-height: 38px;

    font-size: 12px;

}


/* =========================================================
   SIGNATURES
========================================================= */

.signature-table {

    width: 100%;

    border-collapse: collapse;

    margin-top: 45px;

}


.signature-table td {

    width: 33.33%;

    text-align: center;

    font-size: 11px;

}


.signature-line {

    width: 80%;

    border-top: 1px solid #555555;

    margin: 0 auto 32px;

}


/* =========================================================
   SYSTEM NOTE
========================================================= */

.system-note {

    margin-top: 18px;

    padding: 8px 10px;

    background: #eaf3fa;

    border-left: 4px solid #1f4e79;

    color: #1f4e79;

    font-size: 10px;

}


/* =========================================================
   PRINT
========================================================= */

@media print {

    @page {

        size: A4 portrait;

        margin: 10mm;

    }


    html,
    body {

        width: 210mm;

        min-height: 297mm;

        margin: 0;

        padding: 0;

        background: #ffffff !important;

    }


    .result-sheet {

        width: 190mm;

        min-height: 277mm;

        margin: 0 auto;

        padding: 0;

        box-shadow: none;

        box-sizing: border-box;

    }


    * {

        -webkit-print-color-adjust: exact !important;

        print-color-adjust: exact !important;

    }

}

</style>

</head>


<body>


<div class="result-sheet">


<!-- =====================================================
     INSTITUTE HEADER
====================================================== -->

<div class="institute-header">


<div class="department">

INDIA METEOROLOGICAL DEPARTMENT

</div>


<div class="institute">

METEOROLOGICAL TRAINING INSTITUTE

</div>


<div class="institute-subtitle">

Regional Training Centre, World Meteorological Organization

<br>

India Meteorological Department

</div>


</div>


<!-- =====================================================
     COURSE RESULT
====================================================== -->

<div class="course-heading">


<h2>

COURSE RESULT / पाठ्यक्रम परिणाम

</h2>


<p>

FTC Grading &amp; Tabulation Management System

</p>


</div>


<!-- =====================================================
     BATCH
====================================================== -->

<table class="batch-table">


<tr>


<td>

<div class="batch-label">

Batch / बैच

</div>


<div class="batch-value">

<?= h(
    $student["batch_name"]
    ?? ""
) ?>

</div>

</td>


<td>

<div class="batch-label">

Station / स्टेशन

</div>


<div class="batch-value">

<?= h(
    $student["station"]
    ?? ""
) ?>

</div>

</td>


<td>

<div class="batch-label">

Commenced / प्रारंभ

</div>


<div class="batch-value">

<?= h(
    formatDateIndian(
        $student["commenced_date"]
        ?? ""
    )
) ?>

</div>

</td>


<td>

<div class="batch-label">

Completed / समाप्ति

</div>


<div class="batch-value">

<?= h(
    formatDateIndian(
        $student["completed_date"]
        ?? ""
    )
) ?>

</div>

</td>


</tr>


</table>


<!-- =====================================================
     TRAINEE DETAILS
====================================================== -->

<div class="section-heading">

Trainee Details / प्रशिक्षु विवरण

</div>


<table class="trainee-table">


<tr>


<td class="trainee-label">

Roll Number / अनुक्रमांक

</td>


<td class="trainee-value">

<?= h(
    $student["roll_no"]
) ?>

</td>


<td class="trainee-label">

Name / नाम

</td>


<td class="trainee-value">

<?= h(
    $student["student_name"]
) ?>

</td>


</tr>


<tr>


<td class="trainee-label">

Designation / पद

</td>


<td class="trainee-value">

<?= h(
    $student["designation"]
) ?>

</td>


<td class="trainee-label">

Qualification / शैक्षणिक योग्यता

</td>


<td class="trainee-value">

<?= h(
    $student["qualification"]
) ?>

</td>


</tr>


<tr>


<td class="trainee-label">

Office / कार्यालय

</td>


<td class="trainee-value">

<?= h(
    $student["rmc"]
) ?>

</td>


<td class="trainee-label">

Group / समूह

</td>


<td class="trainee-value">

<?= h(
    $student["group_code"]
) ?>

</td>


</tr>


</table>


<!-- =====================================================
     SUBJECT-WISE MARKS
====================================================== -->

<div class="subject-heading">

Subject-wise Marks / विषयवार अंक

</div>


<table class="subject-table">


<thead>


<tr>

<th>
Paper No.
</th>

<th>
Subject / Paper
</th>

<th>
Max. Marks
</th>

<th>
Pass Marks
</th>

<th>
Marks Obtained
</th>

</tr>


</thead>


<tbody>


<?php

$paperNumber = 1;


foreach (
    $marks as $mark
):


$maxInternal =
    (float)(
        $mark["max_internal"]
        ?? 0
    );


$maxMidterm =
    (float)(
        $mark["max_midterm"]
        ?? 0
    );


$maxFinal =
    (float)(
        $mark["max_final"]
        ?? 0
    );


$maxMarks =
    $maxInternal
    +
    $maxMidterm
    +
    $maxFinal;


$obtained =
    $mark["combined_total"];


$passMarks =
    $maxMarks > 0
        ? $maxMarks / 2
        : 50;

?>


<tr>


<td>

<?= $paperNumber ?>

</td>


<td class="subject-cell">


<span class="subject-name">

<?= h(
    $mark["paper_name"]
) ?>

</span>


<span class="paper-code">

<?= h(
    $mark["paper_code"]
) ?>

</span>


</td>


<td>

<?= number_format(
    $maxMarks,
    0
) ?>

</td>


<td>

<?= number_format(
    $passMarks,
    0
) ?>

</td>


<td>


<?php

if (
    $obtained === null
) {

    echo "—";

} else {

    echo number_format(
        (float)$obtained,
        1
    );

}

?>


</td>


</tr>


<?php

$paperNumber++;

endforeach;

?>


<?php if (
    empty($marks)
): ?>


<tr>


<td colspan="5">

No subject-wise marks available.

</td>


</tr>


<?php endif; ?>


</tbody>


</table>


<!-- =====================================================
     RESULT SUMMARY
====================================================== -->

<table class="summary-table">


<tr>


<td class="summary-label">

Total / कुल

</td>


<td class="summary-value">

<?= number_format(
    (float)$grandTotal,
    1
) ?>

&nbsp; / &nbsp;

<?= number_format(
    (float)$maxPossibleMarks,
    1
) ?>

</td>


</tr>


<tr>


<td class="summary-label">

Percentage / प्रतिशत

</td>


<td class="summary-value">


<?php

if (
    $percentage !== null
) {

    echo number_format(
        (float)$percentage,
        2
    );

    echo "%";

} else {

    echo "—";

}

?>


</td>


</tr>


<tr>


<td class="summary-label">

Result / परिणाम

</td>


<td class="summary-value">


<?php if (
    $percentage === null
): ?>


<span class="result-pending">

PENDING

</span>


<?php elseif (
    $isPassed
): ?>


<span class="result-pass">

PASS

</span>


<?php else: ?>


<span class="result-fail">

FAIL

</span>


<?php endif; ?>


</td>


</tr>


<tr>


<td class="summary-label">

Performance / प्रदर्शन

</td>


<td class="summary-value">

<?= h(
    $performance
) ?>

</td>


</tr>


</table>


<!-- =====================================================
     REMARKS
====================================================== -->

<table class="remarks-table">


<tr>


<td class="remarks-title">

Remarks / टिप्पणी

</td>


</tr>


<tr>


<td class="remarks-value">


<?php

if (
    trim($customRemarks) !== ""
) {

    echo nl2br(
        h($customRemarks)
    );

} elseif (
    trim($remarks) !== ""
) {

    echo nl2br(
        h($remarks)
    );

} else {

    echo "&nbsp;";

}

?>


</td>


</tr>


</table>


<!-- =====================================================
     SIGNATURES
====================================================== -->

<table class="signature-table">


<tr>


<td>

<div class="signature-line"></div>

Course Coordinator / पाठ्यक्रम समन्वयक

</td>


<td>

<div class="signature-line"></div>

Head of Institute / संस्थान प्रमुख

</td>


<td>

<div class="signature-line"></div>

Authorized Officer / अधिकृत अधिकारी

</td>


</tr>


</table>


<!-- =====================================================
     SYSTEM NOTE
====================================================== -->

<div class="system-note">

This result is generated from the marks stored in the FTC Grading &amp; Tabulation Management System. This page does not permit marks modification.

</div>


</div>


</body>

</html>