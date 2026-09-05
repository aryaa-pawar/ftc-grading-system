<?php
// admin/marks/export_excel.php

error_reporting(E_ALL);
ini_set('display_errors', 0);

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

$selectedBatchId = isset($_GET["batch_id"]) ? (int)$_GET["batch_id"] : 0;
if ($selectedBatchId <= 0) {
    die("Invalid Batch Selected.");
}

$pdo = getDbConnection();

// Load current batch
$stmt = $pdo->prepare("SELECT * FROM batches WHERE batch_id = :batch_id LIMIT 1");
$stmt->execute([":batch_id" => $selectedBatchId]);
$currentBatch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentBatch) {
    die("Batch not found.");
}

// Load students
$stmt = $pdo->prepare("
    SELECT student_id, roll_no, student_name, designation, qualification, rmc, group_code
    FROM students
    WHERE batch_id = :batch_id AND (is_deleted = 0 OR is_deleted IS NULL)
    ORDER BY roll_no ASC, student_name ASC
");
$stmt->execute([":batch_id" => $selectedBatchId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Load paper subject mapping
$stmt = $pdo->prepare("
    SELECT DISTINCT p.paper_id, p.paper_code, p.paper_name, p.is_elective, p.paper_type, p.max_internal, p.max_midterm, p.max_final
    FROM papers p
    INNER JOIN paper_subject_mapping psm ON psm.paper_id = p.paper_id
    WHERE psm.batch_id = :batch_id
    ORDER BY p.paper_id ASC
");
$stmt->execute([":batch_id" => $selectedBatchId]);
$papers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT psm.paper_id, psm.subject_id, psm.subject_weightage_pct, psm.subject_max_internal, psm.subject_max_midterm, psm.subject_max_final,
           s.subject_code, s.subject_name, s.is_elective
    FROM paper_subject_mapping psm
    INNER JOIN subjects s ON s.subject_id = psm.subject_id
    WHERE psm.batch_id = :batch_id
    ORDER BY psm.paper_id ASC, s.subject_code ASC
");
$stmt->execute([":batch_id" => $selectedBatchId]);
$mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);

$paperGroups = [];
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
        $paperGroups[$pId]['subjects'][] = [
            'subject_id' => (int)$m['subject_id'],
            'subject_code' => $m['subject_code'],
            'subject_name' => $m['subject_name'],
            'max_internal' => (float)($m['subject_max_internal'] ?? $paperGroups[$pId]['max_internal']),
            'max_midterm' => (float)($m['subject_max_midterm'] ?? $paperGroups[$pId]['max_midterm']),
            'max_final' => (float)($m['subject_max_final'] ?? $paperGroups[$pId]['max_final']),
            'max_total' => (float)($m['subject_max_internal'] ?? $paperGroups[$pId]['max_internal']) +
                          (float)($m['subject_max_midterm'] ?? $paperGroups[$pId]['max_midterm']) +
                          (float)($m['subject_max_final'] ?? $paperGroups[$pId]['max_final'])
        ];
    }
}

// Load student marks
$stmt = $pdo->prepare("
    SELECT student_id, paper_id, subject_id, assessment_type_id, marks_obtained, is_absent
    FROM faculty_marks_entry
    WHERE student_id IN (SELECT student_id FROM students WHERE batch_id = :batch_id)
");
$stmt->execute([":batch_id" => $selectedBatchId]);
$marksRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$studentSubjectMarks = [];
foreach ($marksRows as $r) {
    $sId = (int)$r['student_id'];
    $pId = (int)($r['paper_id'] ?? 0);
    $subId = (int)$r['subject_id'];
    $aTypeId = (int)$r['assessment_type_id'];
    $val = ($r['is_absent'] == 1) ? 'ABS' : $r['marks_obtained'];
    $studentSubjectMarks[$sId][$pId][$subId][$aTypeId] = $val;
}

// Load result register rows
$paperResults = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.student_id, r.grand_total, r.percentage, r.remarks
        FROM students s
        LEFT JOIN v_result_register r ON r.student_id = s.student_id
        WHERE s.batch_id = :batch_id
    ");
    $stmt->execute([":batch_id" => $selectedBatchId]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $paperResults[(int)$row['student_id']] = $row;
    }
} catch (PDOException $e) {}

// Elective mappings
$stmt = $pdo->prepare("
    SELECT subject_id, group_code FROM elective_subject_mapping
");
$stmt->execute();
$electiveSubjectGroups = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $electiveSubjectGroups[(int)$r['subject_id']][] = strtolower(trim($r['group_code']));
}

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

$filename = "marks-register-batch-" . $selectedBatchId . ".xlsx";

// Clear any output buffer
if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Pragma: public');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
