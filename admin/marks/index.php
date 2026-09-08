<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../../includes/auth.php";
require_permission('marks.view');
require_once __DIR__ . "/../../config/database.php";
if (file_exists(__DIR__ . "/../../vendor/autoload.php")) {
    require_once __DIR__ . "/../../vendor/autoload.php";
} elseif (file_exists(__DIR__ . "/../../imdAuth/vendor/autoload.php")) {
    require_once __DIR__ . "/../../imdAuth/vendor/autoload.php";
}

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$pageTitle = "Marks Management";
$errorMessage = "";

$batches = [];
$students = [];
$paperGroups = [];
$studentSubjectMarks = [];
$paperResults = [];

$selectedBatchId = isset($_GET["batch_id"]) ? (int)$_GET["batch_id"] : 0;

/* =========================================================
   HELPER FUNCTIONS
========================================================= */

function h($value)
{
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
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

function displayMark($value)
{
    if ($value === null || $value === "") {
        return "—";
    }
    return h($value);
}

/* =========================================================
   LOAD BATCHES
========================================================= */

try {
    $stmt = $pdo->query("
        SELECT
            batch_id,
            batch_name,
            commenced_date,
            completed_date,
            station
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

/* =========================================================
   LOAD STUDENTS FOR BATCH
========================================================= */

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
            WHERE batch_id = :batch_id
              AND (is_deleted = 0 OR is_deleted IS NULL)
            ORDER BY
                roll_no ASC,
                student_name ASC
        ");
        $stmt->execute([":batch_id" => $selectedBatchId]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errorMessage = "Unable to load students: " . $e->getMessage();
    }
}

/* =========================================================
   LOAD PAPERS & MAPPED SUBJECTS (HIERARCHY)
========================================================= */

if ($selectedBatchId > 0) {
    try {
        // Load distinct papers mapped to this batch
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                p.paper_id,
                p.paper_code,
                p.paper_name,
                p.is_elective,
                p.paper_type,
                p.max_internal,
                p.max_midterm,
                p.max_final
            FROM papers p
            INNER JOIN paper_subject_mapping psm
                ON psm.paper_id = p.paper_id
            WHERE psm.batch_id = :batch_id
            ORDER BY p.paper_id ASC
        ");
        $stmt->execute([":batch_id" => $selectedBatchId]);
        $papers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Load mappings with subject details
        $stmt = $pdo->prepare("
            SELECT
                psm.paper_id,
                psm.subject_id,
                psm.subject_weightage_pct,
                psm.subject_max_internal,
                psm.subject_max_midterm,
                psm.subject_max_final,
                s.subject_code,
                s.subject_name,
                s.is_elective
            FROM paper_subject_mapping psm
            INNER JOIN subjects s
                ON s.subject_id = psm.subject_id
            WHERE psm.batch_id = :batch_id
            ORDER BY psm.paper_id ASC, s.subject_code ASC
        ");
        $stmt->execute([":batch_id" => $selectedBatchId]);
        $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($papers as $p) {
            $pId = (int)$p['paper_id'];
            $paperGroups[$pId] = [
                'paper_id' => $pId,
                'paper_code' => $p['paper_code'],
                'paper_name' => $p['paper_name'],
                'paper_type' => $p['paper_type'] ?? 'Theory',
                'max_internal' => (float)$p['max_internal'],
                'max_midterm' => (float)$p['max_midterm'],
                'max_final' => (float)$p['max_final'],
                'subjects' => []
            ];
        }

        foreach ($mappings as $m) {
            $pId = (int)$m['paper_id'];
            if (isset($paperGroups[$pId])) {
                $intMax = (float)($m['subject_max_internal'] > 0 ? $m['subject_max_internal'] : $paperGroups[$pId]['max_internal']);
                $midMax = (float)($m['subject_max_midterm'] > 0 ? $m['subject_max_midterm'] : $paperGroups[$pId]['max_midterm']);
                $finMax = (float)($m['subject_max_final'] > 0 ? $m['subject_max_final'] : $paperGroups[$pId]['max_final']);

                $paperGroups[$pId]['subjects'][] = [
                    'subject_id' => (int)$m['subject_id'],
                    'subject_code' => $m['subject_code'],
                    'subject_name' => $m['subject_name'],
                    'is_elective' => $m['is_elective'],
                    'max_internal' => $intMax,
                    'max_midterm' => $midMax,
                    'max_final' => $finMax,
                    'max_total' => $intMax + $midMax + $finMax
                ];
            }
        }

        // Only keep papers with mapped subjects
        $paperGroups = array_filter($paperGroups, function($pg) {
            return !empty($pg['subjects']);
        });

        // Load elective subject group mappings
        $electiveSubjectGroups = [];
        try {
            $esmRows = $pdo->query("SELECT group_code, subject_id FROM elective_subject_mapping")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($esmRows as $esm) {
                $electiveSubjectGroups[(int)$esm['subject_id']][] = strtolower(trim($esm['group_code']));
            }
        } catch (PDOException $e) {}

    } catch (PDOException $e) {
        $errorMessage = "Unable to load papers & subject mappings: " . $e->getMessage();
    }
}

/* =========================================================
   LOAD STUDENT SUBJECT MARKS (PAPER-SCOPED)
========================================================= */

if ($selectedBatchId > 0 && !empty($students)) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                fme.student_id,
                fme.subject_id,
                fme.paper_id AS paper_id,
                fme.assessment_type_id,
                CASE
                    WHEN COUNT(CASE WHEN fme.is_absent = 0 AND fme.marks_obtained IS NOT NULL THEN 1 END) > 0
                    THEN ROUND(AVG(CASE WHEN fme.is_absent = 0 THEN fme.marks_obtained ELSE NULL END), 0)
                    ELSE 0
                END AS score
            FROM faculty_marks_entry fme
            INNER JOIN students st
                ON st.student_id = fme.student_id
            INNER JOIN paper_subject_mapping psm
                ON psm.batch_id = st.batch_id
               AND psm.paper_id = fme.paper_id
               AND psm.subject_id = fme.subject_id
            WHERE st.batch_id = :batch_id
              AND (st.is_deleted = 0 OR st.is_deleted IS NULL)
              AND (fme.marks_obtained IS NOT NULL OR fme.is_absent = 1)
            GROUP BY fme.student_id, fme.subject_id, fme.paper_id, fme.assessment_type_id
        ");
        $stmt->execute([":batch_id" => $selectedBatchId]);
        $scoreRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($scoreRows as $sr) {
            $stId = (int)$sr['student_id'];
            $subId = (int)$sr['subject_id'];
            $pId = (int)($sr['paper_id'] ?? 0);
            $aId = (int)$sr['assessment_type_id'];
            $studentSubjectMarks[$stId][$pId][$subId][$aId] = $sr['score'];
        }
    } catch (PDOException $e) {
        $errorMessage = "Unable to load student marks: " . $e->getMessage();
    }
}

/* =========================================================
   LOAD RESULT REGISTER (GRAND TOTAL / PERCENTAGE / REMARKS)
========================================================= */

if ($selectedBatchId > 0 && !empty($students)) {
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
                ON s.student_id = rr.student_id
            WHERE s.batch_id = :batch_id
              AND (s.is_deleted = 0 OR s.is_deleted IS NULL)
            ORDER BY s.roll_no ASC
        ");
        $stmt->execute([":batch_id" => $selectedBatchId]);
        $resultRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($resultRows as $row) {
            $paperResults[(int)$row["student_id"]] = $row;
        }
    } catch (PDOException $e) {
        // v_result_register might be optional or calculated on the fly
    }
}

if (isset($_GET['download']) && $_GET['download'] === 'excel' && $currentBatch !== null) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Marks Register');

    $identityHeaders = ['S. No.', 'Student Name', 'Designation', 'Qualification', 'Roll No.', 'RMC / Station'];
    $paperHeader = [];
    $subjectHeader = [];
    $componentHeader = [];
    $maxHeader = [];
    $headers = $identityHeaders;
    foreach ($paperGroups as $paperGroup) {
        $paperStart = count($headers) + 1;
        foreach ($paperGroup['subjects'] as $subject) {
            $paperLabel = '[' . $paperGroup['paper_code'] . '] ' . $paperGroup['paper_name'];
            $subjectLabel = '[' . $subject['subject_code'] . '] ' . $subject['subject_name'];
            $subjectStart = count($headers) + 1;
            foreach ([['Internal', $subject['max_internal']], ['Mid-Term', $subject['max_midterm']], ['Final', $subject['max_final']], ['Total', $subject['max_total']]] as $component) {
                $headers[] = $component[0];
                $componentHeader[] = $component[0];
                $maxHeader[] = number_format((float)$component[1], 2);
            }
            $subjectHeader[] = ['label' => $subjectLabel, 'start' => $subjectStart, 'end' => count($headers)];
        }
        $paperHeader[] = ['label' => $paperLabel, 'start' => $paperStart, 'end' => count($headers)];
    }
    $headers[] = 'Grand Total';
    $headers[] = 'Percentage';
    $headers[] = 'Result';

    $paperRow = array_fill(0, count($headers), '');
    $subjectRow = array_fill(0, count($headers), '');
    foreach ($identityHeaders as $index => $label) $paperRow[$index] = $label;
    $summaryStart = count($headers) - 3;
    foreach (['Grand Total', 'Percentage', 'Result'] as $offset => $label) {
        $paperRow[$summaryStart + $offset] = $label;
    }
    foreach ($paperHeader as $group) $paperRow[$group['start'] - 1] = $group['label'];
    foreach ($subjectHeader as $group) $subjectRow[$group['start'] - 1] = $group['label'];
    $sheet->fromArray([['FTC Marks Tabulation - ' . $currentBatch['batch_name']]], null, 'A1');
    $sheet->fromArray([$paperRow], null, 'A2');
    $sheet->fromArray([$subjectRow], null, 'A3');
    $sheet->fromArray([array_merge(array_fill(0, count($identityHeaders), ''), $componentHeader)], null, 'A4');
    $sheet->fromArray([array_merge(array_fill(0, count($identityHeaders), ''), $maxHeader)], null, 'A5');
    $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
    $sheet->mergeCells('A1:' . $lastColumn . '1');
    foreach ($identityHeaders as $index => $label) {
        $col = Coordinate::stringFromColumnIndex($index + 1);
        $sheet->mergeCells($col . '2:' . $col . '5');
    }
    foreach ($paperHeader as $group) {
        if ($group['start'] < $group['end']) $sheet->mergeCells(Coordinate::stringFromColumnIndex($group['start']) . '2:' . Coordinate::stringFromColumnIndex($group['end']) . '2');
    }
    foreach ($subjectHeader as $group) {
        if ($group['start'] < $group['end']) $sheet->mergeCells(Coordinate::stringFromColumnIndex($group['start']) . '3:' . Coordinate::stringFromColumnIndex($group['end']) . '3');
    }
    for ($column = $summaryStart + 1; $column <= $summaryStart + 3; $column++) {
        $summaryColumn = Coordinate::stringFromColumnIndex($column);
        $sheet->mergeCells($summaryColumn . '2:' . $summaryColumn . '5');
    }

    $excelRow = 6;
    $serialNo = 1;
    foreach ($students as $student) {
        $studentId = (int)$student['student_id'];
        $hasAnyMark = false;
        $rowValues = [$serialNo++, $student['student_name'], $student['designation'], $student['qualification'], $student['roll_no'], $student['rmc'] ?? ''];

        foreach ($paperGroups as $paperGroup) {
            foreach ($paperGroup['subjects'] as $subject) {
                $paperId = (int)$paperGroup['paper_id'];
                $subjectId = (int)$subject['subject_id'];
                $isElective = strtoupper(trim($paperGroup['paper_code'] ?? '')) === 'P-V';
                $studentGroup = strtolower(trim($student['group_code'] ?? ''));
                $allowedGroups = $electiveSubjectGroups[$subjectId] ?? [];
                $isApplicable = !$isElective || empty($allowedGroups) || in_array($studentGroup, $allowedGroups, true);
                $internal = $studentSubjectMarks[$studentId][$paperId][$subjectId][1] ?? null;
                $midterm = $studentSubjectMarks[$studentId][$paperId][$subjectId][2] ?? null;
                $final = $studentSubjectMarks[$studentId][$paperId][$subjectId][3] ?? null;
                $total = null;

                if ($isApplicable && ($internal !== null || $midterm !== null || $final !== null)) {
                    $hasAnyMark = true;
                    $total = (is_numeric($internal) ? (float)$internal : 0) + (is_numeric($midterm) ? (float)$midterm : 0) + (is_numeric($final) ? (float)$final : 0);
                }

                if (!$isApplicable) {
                    array_push($rowValues, 'N/A', 'N/A', 'N/A', 'N/A');
                } else {
                    array_push($rowValues, $internal ?? '-', $midterm ?? '-', $final ?? '-', $total !== null ? round($total, 2) : '-');
                }
            }
        }

        $resultInfo = $paperResults[$studentId] ?? [];
        $grandTotal = isset($resultInfo['grand_total']) ? (float)$resultInfo['grand_total'] : null;
        $percentage = isset($resultInfo['percentage']) ? (float)$resultInfo['percentage'] : null;
        if (!$hasAnyMark) {
            $grandTotal = null;
            $percentage = null;
        }
        $rowValues[] = $grandTotal !== null ? round($grandTotal, 2) : '-';
        $rowValues[] = $percentage !== null ? round($percentage, 2) . '%' : '-';
        $rowValues[] = $percentage === null ? '-' : ((float)$percentage >= 50 ? 'PASS' : 'FAIL');
        $sheet->fromArray([$rowValues], null, 'A' . $excelRow++);
    }

    $sheet->getStyle('A1:' . $lastColumn . '5')->getFont()->setBold(true);
    $sheet->getStyle('A1:' . $lastColumn . '5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    $sheet->getStyle('A1:' . $lastColumn . '1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1B4B73');
    $sheet->getStyle('A1:' . $lastColumn . '1')->getFont()->getColor()->setARGB('FFFFFFFF');
    $sheet->getStyle('A2:' . $lastColumn . '5')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9EAF7');
    $sheet->getStyle('A2:' . $lastColumn . '5')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    $sheet->getStyle('A6:' . $lastColumn . ($excelRow - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getStyle('A6:' . $lastColumn . ($excelRow - 1))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    $sheet->getStyle('A2:' . $lastColumn . '3')->getAlignment()->setTextRotation(90);
    $sheet->getStyle('A4:' . $lastColumn . '5')->getAlignment()->setTextRotation(90);
    $sheet->getStyle('A2:F5')->getAlignment()->setTextRotation(0);
    $sheet->getStyle(Coordinate::stringFromColumnIndex($summaryStart + 1) . '2:' . $lastColumn . '5')->getAlignment()->setTextRotation(0);
    $sheet->getStyle('A3:' . $lastColumn . $excelRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->freezePane('G6');
    $sheet->setAutoFilter('A1:' . $lastColumn . ($excelRow - 1));
    $identityWidths = [5, 24, 13, 18, 22, 16];
    foreach ($identityWidths as $index => $width) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index + 1))->setWidth($width);
    for ($column = count($identityHeaders) + 1; $column <= count($headers); $column++) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(5);
    for ($column = $summaryStart + 1; $column <= $summaryStart + 3; $column++) $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setWidth(12);
    $sheet->getRowDimension(1)->setRowHeight(24);
    $sheet->getRowDimension(2)->setRowHeight(90);
    $sheet->getRowDimension(3)->setRowHeight(105);
    $sheet->getRowDimension(4)->setRowHeight(72);
    $sheet->getRowDimension(5)->setRowHeight(42);
    $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)->setFitToWidth(1)->setFitToHeight(0);
    $sheet->getPageMargins()->setTop(0.25)->setRight(0.25)->setBottom(0.25)->setLeft(0.25)->setHeader(0.1)->setFooter(0.1);
    $sheet->getPageSetup()->setHorizontalCentered(true)->setRowsToRepeatAtTopByStartAndEnd(1, 5)->setPrintArea('A1:' . $lastColumn . ($excelRow - 1));

    if (ob_get_length()) {
        ob_end_clean();
    }
    $filename = "marks-register-" . $selectedBatchId . ".xlsx";
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Cache-Control: max-age=0, no-cache, must-revalidate');
    header('Pragma: public');
    (new Xlsx($spreadsheet))->save('php://output');
    exit;
}

require_once __DIR__ . "/../../includes/header.php";

?>

<style>
.marks-page {
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

.select-box,
.date-box {
    height: 38px;
    border: 1px solid #cccccc;
    border-radius: 4px;
    padding: 0 12px;
    font-size: 13px;
    background: #ffffff;
    box-sizing: border-box;
    display: flex;
    align-items: center;
}

.select-box {
    min-width: 220px;
}

.date-box {
    min-width: 130px;
    background: #f9f9f9;
    color: #555555;
}

.btn {
    height: 38px;
    border: none;
    border-radius: 4px;
    padding: 0 18px;
    font-size: 13px;
    font-weight: bold;
    cursor: pointer;
}

.view-btn {
    background: #1f4e79;
    color: #ffffff;
}

.view-btn:hover {
    background: #173b5c;
}

.table-wrapper {
    margin-top: 25px;
    background: #ffffff;
    border: 1px solid #777777;
    overflow: auto;
    width: 100%;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
}

.tabulation-table {
    width: max-content;
    min-width: 100%;
    border-collapse: collapse;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
}

.tabulation-table th,
.tabulation-table td {
    border: 1px solid #555555;
    padding: 7px 8px;
    text-align: center;
    white-space: nowrap;
    vertical-align: middle;
}

.tabulation-table thead th {
    background: #1b4b73;
    color: #ffffff;
    font-weight: bold;
}

/* 1. TOP HEADER: PAPERS */
.paper-header {
    background: #1b4b73 !important;
    color: #ffffff !important;
    font-size: 13px;
    font-weight: bold;
    padding: 8px 10px !important;
    letter-spacing: 0.3px;
}

.paper-badge {
    display: inline-block;
    font-size: 11px;
    background: rgba(255, 255, 255, 0.2);
    padding: 2px 8px;
    border-radius: 10px;
    margin-left: 6px;
}

/* 2. SUB-HEADER: SUBJECTS */
.subject-header {
    background: #255b86 !important;
    color: #ffffff !important;
    font-size: 12px;
    font-weight: bold;
    padding: 7px 8px !important;
}

/* 3. LOWEST HEADER: ASSESSMENTS */
.assessment-header {
    background: #376d96 !important;
    color: #ffffff !important;
    font-size: 11px;
    padding: 6px 8px !important;
}

.assessment-header.total-col {
    background: #2e5c80 !important;
    font-weight: bold;
}

.maximum {
    display: block;
    font-size: 9px;
    margin-top: 2px;
    color: #ffffff;
    opacity: 0.95;
    font-weight: normal;
}

.student-header {
    background: #1b4b73 !important;
    color: #ffffff !important;
}

.student-info {
    min-width: 180px;
    text-align: left !important;
}

.roll-column {
    min-width: 120px;
}

.grand-total-header,
.percentage-header,
.result-header,
.actions-header {
    background: #1b4b73 !important;
    color: #ffffff !important;
}

.tabulation-table tbody td {
    background: #ffffff;
    color: #111111;
}

.tabulation-table tbody tr:hover td {
    background: #f4f8fb;
}

.mark-cell {
    min-width: 55px;
}

.total-cell {
    min-width: 60px;
    font-weight: bold;
    background: #eef5fa !important;
    color: #1f4e79 !important;
}

.grand-total {
    min-width: 75px;
    font-weight: bold;
    background: #e6f0fa !important;
    color: #1b4b73 !important;
    font-size: 13px;
}

.percentage {
    min-width: 70px;
    font-weight: bold;
    background: #e6f0fa !important;
    font-size: 13px;
}

.pass {
    display: inline-block;
    background: #d9f2df;
    color: #146c2e;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: bold;
}

.fail {
    display: inline-block;
    background: #f8d7da;
    color: #a12622;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: bold;
}

.action-cell {
    min-width: 140px;
}

.action-btn {
    display: inline-block;
    padding: 5px 8px;
    margin: 2px;
    border-radius: 4px;
    text-decoration: none;
    font-size: 11px;
    font-weight: bold;
}

.view-action {
    background: #1f4e79;
    color: #ffffff;
}

.edit-action {
    background: #f0f0f0;
    color: #333333;
    border: 1px solid #ccc;
}

.empty {
    text-align: center;
    padding: 50px 20px;
    background: #ffffff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    margin-top: 25px;
}

</style>

<div style="display:flex; flex-direction:column; min-height:100vh;">

    <?php require_once __DIR__ . "/../../includes/navbar.php"; ?>

    <div style="display:flex; flex:1;">

        <div class="no-print">
            <?php require_once __DIR__ . "/../../includes/sidebar.php"; ?>
        </div>

        <main class="marks-page" style="flex:1;">

            <div style="max-width:100%;">

                <div class="no-print" style="margin-bottom:20px;">
                    <h1 style="margin:0; font-size:26px; color:#1f4e79;">Marks Tabulation Register</h1>
                    <p style="margin:5px 0 0; color:#666666;">Comprehensive marks tabulation grouped by Paper and Subjects.</p>
                </div>

                <?php if (!empty($errorMessage)): ?>
                    <div style="padding:15px; background:#f8d7da; color:#721c24; border-radius:4px; margin-bottom:20px;">
                        <?php echo h($errorMessage); ?>
                    </div>
                <?php endif; ?>

                <!-- FILTER FORM -->
                <div class="filter-box no-print">
                    <form method="GET" action="">
                        <div class="filter-row">

                            <!-- BATCH SELECTOR -->
                            <div class="filter-field">
                                <label>Select Batch:</label>
                                <select name="batch_id" class="select-box" onchange="this.form.submit()">
                                    <?php foreach ($batches as $b): ?>
                                        <option value="<?php echo (int)$b["batch_id"]; ?>" <?php echo ((int)$b["batch_id"] === $selectedBatchId) ? "selected" : ""; ?>>
                                            <?php echo h($b["batch_name"]); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- COMMENCED DATE -->
                            <div class="filter-field">
                                <label>Commenced:</label>
                                <div class="date-box">
                                    <?php echo formatDateIndian($currentBatch["commenced_date"] ?? ""); ?>
                                </div>
                            </div>

                            <!-- COMPLETED DATE -->
                            <div class="filter-field">
                                <label>Completed:</label>
                                <div class="date-box">
                                    <?php echo formatDateIndian($currentBatch["completed_date"] ?? ""); ?>
                                </div>
                            </div>

                            <!-- STATION -->
                            <div class="filter-field">
                                <label>Station:</label>
                                <div class="date-box">
                                    <?php echo h($currentBatch["station"] ?? ""); ?>
                                </div>
                            </div>

                            <!-- VIEW BUTTON -->
                            <button type="submit" class="btn view-btn">View</button>

                            <!-- EXCEL DOWNLOAD -->
                            <a href="export_excel.php?batch_id=<?php echo $selectedBatchId; ?>" class="btn view-btn" style="display:inline-flex;align-items:center;text-decoration:none;">Download Excel</a>

                        </div>
                    </form>
                </div>

                <!-- BATCH DETAILS BANNER -->
                <?php if ($currentBatch !== null): ?>
                    <div style="background:#ffffff; border:1px solid #dddddd; padding:15px 18px; margin-top:20px; border-radius:6px;">
                        <strong>FTC CLASS MARKS TABULATION REGISTER</strong>
                        &nbsp; | &nbsp;
                        Batch: <strong><?php echo h($currentBatch["batch_name"]); ?></strong>
                        &nbsp; | &nbsp;
                        Station: <strong><?php echo h($currentBatch["station"]); ?></strong>
                    </div>
                <?php endif; ?>

                <!-- EMPTY STATES -->
                <?php if ($currentBatch === null): ?>
                    <div class="empty">
                        <h2>Select a batch</h2>
                    </div>
                <?php elseif (empty($students)): ?>
                    <div class="empty">
                        <h2>No students found</h2>
                        <p>This batch does not contain active students.</p>
                    </div>
                <?php elseif (empty($paperGroups)): ?>
                    <div class="empty">
                        <h2>No papers or subject mappings found</h2>
                        <p>Please map subjects to papers under Paper & Grouping Management.</p>
                    </div>
                <?php else: ?>

                    <!-- TABULATION TABLE -->
                    <div class="table-wrapper">
                        <table class="tabulation-table">
                            <thead>

                                <!-- =========================================================
                                     HEADER ROW 1: S.NO, STUDENT INFO, PAPER NAMES (TOP-LEVEL)
                                ========================================================= -->
                                <tr>
                                    <th rowspan="3" class="student-header">S. No.</th>
                                    <th rowspan="3" class="student-header student-info">Name<br>Designation</th>
                                    <th rowspan="3" class="student-header student-info">Qualification</th>
                                    <th rowspan="3" class="student-header roll-column">Roll No.</th>

                                    <?php foreach ($paperGroups as $pg): ?>
                                        <?php 
                                            // 4 columns per subject (Internal, Mid-Term, Final, Total)
                                            $paperColspan = count($pg['subjects']) * 4; 
                                        ?>
                                        <th colspan="<?php echo $paperColspan; ?>" class="paper-header">
                                            [<?php echo h($pg['paper_code']); ?>] <?php echo h($pg['paper_name']); ?>
                                            <span class="paper-badge"><?php echo h($pg['paper_type']); ?></span>
                                        </th>
                                    <?php endforeach; ?>

                                    <th rowspan="3" class="grand-total-header">Grand Total</th>
                                    <th rowspan="3" class="percentage-header">Percentage</th>
                                    <th rowspan="3" class="result-header">Result</th>
                                    <th rowspan="3" class="actions-header no-print">Actions</th>
                                </tr>

                                <!-- =========================================================
                                     HEADER ROW 2: SUBJECT NAMES (UNDER EACH PAPER)
                                ========================================================= -->
                                <tr>
                                    <?php foreach ($paperGroups as $pg): ?>
                                        <?php foreach ($pg['subjects'] as $sub): ?>
                                            <th colspan="4" class="subject-header">
                                                [<?php echo h($sub['subject_code']); ?>] <?php echo h($sub['subject_name']); ?>
                                            </th>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tr>

                                <!-- =========================================================
                                     HEADER ROW 3: INTERNAL, MID-TERM, FINAL, TOTAL WITH MAX MARKS
                                ========================================================= -->
                                <tr>
                                    <?php foreach ($paperGroups as $pg): ?>
                                        <?php foreach ($pg['subjects'] as $sub): ?>
                                            <th class="assessment-header">
                                                Internal
                                                <span class="maximum"><?php echo number_format($sub['max_internal'], 2); ?></span>
                                            </th>
                                            <th class="assessment-header">
                                                Mid-Term
                                                <span class="maximum"><?php echo number_format($sub['max_midterm'], 2); ?></span>
                                            </th>
                                            <th class="assessment-header">
                                                Final
                                                <span class="maximum"><?php echo number_format($sub['max_final'], 2); ?></span>
                                            </th>
                                            <th class="assessment-header total-col">
                                                Total
                                                <span class="maximum"><?php echo number_format($sub['max_total'], 2); ?></span>
                                            </th>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tr>

                            </thead>

                            <!-- =========================================================
                                 TABLE BODY: STUDENT MARKS DATA
                            ========================================================= -->
                            <tbody>
                                <?php $serialNo = 1; ?>
                                <?php foreach ($students as $student): ?>
                                    <?php
                                        $studentId = (int)$student["student_id"];
                                        $hasAnyMark = false;
                                    ?>
                                    <tr>
                                        <!-- S.NO -->
                                        <td><?php echo $serialNo++; ?></td>

                                        <!-- NAME + DESIGNATION -->
                                        <td class="student-info">
                                            <strong><?php echo h($student["student_name"]); ?></strong>
                                            <br>
                                            <small style="color:#666;"><?php echo h($student["designation"]); ?></small>
                                        </td>

                                        <!-- QUALIFICATION -->
                                        <td class="student-info">
                                            <?php echo h($student["qualification"]); ?>
                                        </td>

                                        <!-- ROLL NO -->
                                        <td style="font-weight:bold; color:#1f4e79;">
                                            <?php echo h($student["roll_no"]); ?>
                                        </td>

                                        <!-- MARKS PER PAPER & SUBJECT -->
                                        <?php foreach ($paperGroups as $pg): ?>
                                            <?php foreach ($pg['subjects'] as $sub): ?>
                                                <?php
                                                    $pId = (int)$pg['paper_id'];
                                                    $subId = (int)$sub['subject_id'];
                                                    $isElectiveSub = (int)($sub['is_elective'] ?? 0) === 1;
                                                    $studentGroup = strtolower(trim($student['group_code'] ?? ''));
                                                    $allowedSubGroups = $electiveSubjectGroups[$subId] ?? [];

                                                    $isApplicable = true;
                                                    if ($isElectiveSub && !empty($allowedSubGroups)) {
                                                        if (!in_array($studentGroup, $allowedSubGroups, true)) {
                                                            $isApplicable = false;
                                                        }
                                                    }

                                                    $subTotal = null;
                                                    if ($isApplicable) {
                                                        $intMark = $studentSubjectMarks[$studentId][$pId][$subId][1] ?? null;
                                                        $midMark = $studentSubjectMarks[$studentId][$pId][$subId][2] ?? null;
                                                        $finMark = $studentSubjectMarks[$studentId][$pId][$subId][3] ?? null;

                                                        if ($intMark !== null || $midMark !== null || $finMark !== null) {
                                                            $hasAnyMark = true;
                                                            $intVal = (is_numeric($intMark)) ? (float)$intMark : 0;
                                                            $midVal = (is_numeric($midMark)) ? (float)$midMark : 0;
                                                            $finVal = (is_numeric($finMark)) ? (float)$finMark : 0;
                                                            $subTotal = $intVal + $midVal + $finVal;
                                                        }
                                                    }
                                                ?>
                                                <?php if (!$isApplicable): ?>
                                                    <td class="mark-cell" style="color:#bbb; background:#fafafa; font-size:11px;" title="Not in student's elective group">N/A</td>
                                                    <td class="mark-cell" style="color:#bbb; background:#fafafa; font-size:11px;" title="Not in student's elective group">N/A</td>
                                                    <td class="mark-cell" style="color:#bbb; background:#fafafa; font-size:11px;" title="Not in student's elective group">N/A</td>
                                                    <td class="total-cell" style="color:#bbb; background:#fafafa; font-size:11px;">N/A</td>
                                                <?php else: ?>
                                                    <td class="mark-cell"><?php echo displayMark($intMark ?? null); ?></td>
                                                    <td class="mark-cell"><?php echo displayMark($midMark ?? null); ?></td>
                                                    <td class="mark-cell"><?php echo displayMark($finMark ?? null); ?></td>
                                                    <td class="total-cell"><?php echo displayMark($subTotal !== null ? number_format($subTotal, 2) : null); ?></td>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>

                                        <?php
                                            // Results & Percentage — sourced from v_result_register (DB view)
                                            $resultInfo = $paperResults[$studentId] ?? [];
                                            $grandTotal = isset($resultInfo["grand_total"]) ? (float)$resultInfo["grand_total"] : null;
                                            $percentage  = isset($resultInfo["percentage"])  ? (float)$resultInfo["percentage"]  : null;
                                            // Only show if there's something actually entered
                                            if (!$hasAnyMark) {
                                                $grandTotal = null;
                                                $percentage = null;
                                            }
                                        ?>

                                        <!-- GRAND TOTAL -->
                                        <td class="grand-total">
                                            <?php echo displayMark($grandTotal !== null ? number_format((float)$grandTotal, 2) : null); ?>
                                        </td>

                                        <!-- PERCENTAGE -->
                                        <td class="percentage">
                                            <?php echo ($percentage !== null) ? number_format((float)$percentage, 2) . "%" : "—"; ?>
                                        </td>

                                        <!-- RESULT -->
                                        <td>
                                            <?php if ($percentage === null): ?>
                                                —
                                            <?php elseif ((float)$percentage >= 50): ?>
                                                <span class="pass">PASS</span>
                                            <?php else: ?>
                                                <span class="fail">FAIL</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- ACTIONS -->
                                        <td class="action-cell no-print">
                                            <a href="view.php?student_id=<?php echo $studentId; ?>" class="action-btn view-action">
                                                View
                                            </a>
                                            <a href="full_edit.php?student_id=<?php echo $studentId; ?>" class="action-btn edit-action">
                                                Full Edit
                                            </a>
                                            <a href="edit_internal.php?student_id=<?php echo $studentId; ?>" class="action-btn edit-action">
                                                Edit
                                            </a>
                                        </td>

                                    </tr>
                                <?php endforeach; ?>
                            </tbody>

                        </table>
                    </div>

                <?php endif; ?>

            </div>

        </main>

    </div>

    <?php require_once __DIR__ . "/../../includes/footer.php"; ?>

</div>
