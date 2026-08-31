<?php

require_once __DIR__ . "/../../includes/auth.php";
require_permission('results.view');

/*
|--------------------------------------------------------------------------
| FTC GRADING & TABULATION MANAGEMENT SYSTEM
| RESULT TABULATION
|--------------------------------------------------------------------------
|
| READ ONLY
|
| This page is for viewing the overall result of a batch.
|
| Filters:
|   - Station
|   - Batch
|
| Displays:
|   - Roll No.
|   - Student Name
|   - Designation
|   - Subject/Paper totals
|   - Grand Total
|   - Percentage
|   - Result
|
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";

$pageTitle = "Result Tabulation";

function h($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function displayMark($value)
{
    if ($value === null || $value === '') {
        return '—';
    }

    return number_format((float)$value, 1);
}

function resultStatus($percentage)
{
    if ($percentage === null || $percentage === '') {
        return 'Pending';
    }

    return ((float)$percentage >= 50) ? 'PASS' : 'FAIL';
}

function resultClass($percentage)
{
    if ($percentage === null || $percentage === '') {
        return 'status-pending';
    }

    return ((float)$percentage >= 50) ? 'status-pass' : 'status-fail';
}

$selectedStation = isset($_GET['station']) ? trim($_GET['station']) : '';
$selectedBatch = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;

$stations = [];
$batches = [];
$papers = [];
$students = [];
$tabulation = [];
$paperResults = [];
$currentBatch = null;
$errorMessage = '';

try {
    $stmt = $pdo->query("
        SELECT DISTINCT station
        FROM batches
        WHERE station IS NOT NULL
          AND station <> ''
        ORDER BY station ASC
    ");

    $stations = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $errorMessage = 'Unable to load stations.';
}

try {
    $sql = "
        SELECT
            batch_id,
            batch_name,
            station,
            commenced_date,
            completed_date
        FROM batches
    ";

    $params = [];

    if ($selectedStation !== '') {
        $sql .= ' WHERE station = :station';
        $params[':station'] = $selectedStation;
    }

    $sql .= ' ORDER BY batch_id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = 'Unable to load batches.';
}

foreach ($batches as $batch) {
    if ((int)$batch['batch_id'] === $selectedBatch) {
        $currentBatch = $batch;
        break;
    }
}

if ($selectedBatch === 0 && !empty($batches)) {
    $selectedBatch = (int)$batches[0]['batch_id'];
    $currentBatch = $batches[0];
}

if ($currentBatch && $selectedStation === '') {
    $selectedStation = (string)($currentBatch['station'] ?? '');
}

if ($selectedBatch > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                p.paper_id,
                p.paper_code,
                p.paper_name,
                p.max_internal,
                p.max_midterm,
                p.max_final
            FROM papers p
            INNER JOIN paper_subject_mapping psm
                ON psm.paper_id = p.paper_id
            WHERE psm.batch_id = :batch_id
            ORDER BY p.paper_id ASC
        ");

        $stmt->execute([':batch_id' => $selectedBatch]);
        $papers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errorMessage = 'Unable to load papers.';
    }
}

if ($selectedBatch > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                student_id,
                roll_no,
                student_name,
                designation,
                qualification,
                rmc
            FROM students
            WHERE batch_id = :batch_id
              AND is_deleted = 0
            ORDER BY roll_no ASC, student_name ASC
        ");

        $stmt->execute([':batch_id' => $selectedBatch]);
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $errorMessage = 'Unable to load students.';
    }
}

if ($selectedBatch > 0 && count($students) > 0 && count($papers) > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                vt.student_id,
                vt.paper_id,
                vt.internal_score,
                vt.midterm_score,
                vt.final_exam_score,
                vt.combined_total
            FROM v_tabulation vt
            INNER JOIN students st
                ON st.student_id = vt.student_id
            INNER JOIN paper_subject_mapping psm
                ON psm.paper_id = vt.paper_id
               AND psm.batch_id = st.batch_id
            WHERE st.batch_id = :batch_id
              AND st.is_deleted = 0
            ORDER BY vt.student_id ASC, vt.paper_id ASC
        ");

        $stmt->execute([':batch_id' => $selectedBatch]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            $studentId = (int)$row['student_id'];
            $paperId = (int)$row['paper_id'];

            if (!isset($tabulation[$studentId])) {
                $tabulation[$studentId] = [];
            }

            $tabulation[$studentId][$paperId] = [
                'internal' => $row['internal_score'],
                'midterm' => $row['midterm_score'],
                'final' => $row['final_exam_score'],
                'total' => $row['combined_total'],
            ];
        }
    } catch (PDOException $e) {
        $errorMessage = 'Unable to load tabulation.';
    }
}

if ($selectedBatch > 0 && count($students) > 0) {
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
              AND s.is_deleted = 0
            ORDER BY s.roll_no ASC, s.student_name ASC
        ");

        $stmt->execute([':batch_id' => $selectedBatch]);
        $resultRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($resultRows as $row) {
            $paperResults[(int)$row['student_id']] = $row;
        }
    } catch (PDOException $e) {
        $errorMessage = 'Unable to load final results.';
    }
}

require_once "../includes/header.php";
?>

<style>
    body {
        background: #f4f6f8;
        color: #1f2937;
    }

    .result-page {
        min-height: calc(100vh - 72px);
        padding: 25px;
        background: #f4f6f8;
    }

    .page-heading {
        margin-bottom: 20px;
    }

    .page-heading h1 {
        margin: 0 0 5px;
        color: #1f4e79;
        font-size: 30px;
    }

    .page-heading p {
        margin: 0;
        color: #666;
        font-size: 15px;
    }

    .error-box {
        background: #fdecea;
        color: #a12622;
        border: 1px solid #f5c6c6;
        padding: 12px 14px;
        border-radius: 6px;
        margin-bottom: 20px;
    }

    .filter-card {
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,.08);
    }

    .filter-row {
        display: flex;
        flex-wrap: wrap;
        align-items: end;
        gap: 16px;
    }

    .filter-field {
        display: flex;
        flex-direction: column;
        gap: 6px;
        min-width: 180px;
    }

    .filter-field label {
        font-weight: 600;
        color: #374151;
        font-size: 13px;
    }

    .filter-field select,
    .info-field {
        min-height: 42px;
        padding: 9px 12px;
        border: 1px solid #d8dee6;
        border-radius: 6px;
        background: #fff;
        color: #1f2937;
        font-size: 14px;
    }

    .btn {
        padding: 10px 18px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
    }

    .btn-view {
        background: #1f4e79;
        color: #fff;
    }

    .btn-print {
        background: #0b7a75;
        color: #fff;
    }

    .tabulation-heading {
        background: #e9f1fb;
        color: #1f4e79;
        font-weight: 700;
        padding: 12px 16px;
        border-radius: 6px;
        margin-bottom: 18px;
        border: 1px solid #cfe0f7;
    }

    .table-wrapper {
        overflow-x: auto;
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,.08);
    }

    table.tabulation-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1100px;
        font-size: 12px;
    }

    .tabulation-table th,
    .tabulation-table td {
        border: 1px solid #e5e7eb;
        padding: 8px 6px;
        text-align: center;
        vertical-align: middle;
    }

    .tabulation-table thead th {
        background: #f3f7fb;
        color: #1f2937;
        font-weight: 700;
    }

    .tabulation-table tbody td {
        background: #fff;
    }

    .tabulation-table tbody tr:nth-child(even) td {
        background: #fafcff;
    }

    .name-column {
        text-align: left;
        min-width: 180px;
    }

    .roll-column,
    .designation-column,
    .qualification-column {
        min-width: 100px;
    }

    .paper-header {
        background: #eaf3ff;
        color: #1f4e79;
    }

    .paper-code {
        font-size: 11px;
        font-weight: 700;
        background: #f8fbff;
    }

    .assessment-header {
        background: #f5f9ff;
        font-size: 11px;
    }

    .maximum-mark {
        font-size: 10px;
        background: #f9fafb;
    }

    .status-pass {
        display: inline-block;
        background: #e8f7ee;
        color: #1e7f4a;
        border: 1px solid #bfe5cd;
        border-radius: 999px;
        padding: 4px 10px;
        font-weight: 700;
    }

    .status-fail {
        display: inline-block;
        background: #fdebea;
        color: #b42318;
        border: 1px solid #f5c6c6;
        border-radius: 999px;
        padding: 4px 10px;
        font-weight: 700;
    }

    .status-pending {
        display: inline-block;
        background: #f3f4f6;
        color: #5f6b7a;
        border: 1px solid #dfe3ea;
        border-radius: 999px;
        padding: 4px 10px;
        font-weight: 700;
    }

    .empty {
        background: #fff;
        padding: 55px 20px;
        border: 1px solid #ddd;
        border-radius: 7px;
        text-align: center;
        color: #666;
    }

    .empty h2 {
        margin: 0 0 8px;
        color: #666;
    }

    @media (max-width: 900px) {
        .result-page {
            padding: 15px;
        }

        .page-heading h1 {
            font-size: 25px;
        }
    }
</style>

<div class="result-page">
    <div class="page-heading">
        <h1>Result Tabulation</h1>
        <p>View the complete batch-wise academic tabulation and result summary.</p>
    </div>

    <?php if ($errorMessage !== ''): ?>
        <div class="error-box"><?php echo h($errorMessage); ?></div>
    <?php endif; ?>

    <div class="filter-card">
        <form method="GET" action="tabulation.php">
            <div class="filter-row">
                <div class="filter-field">
                    <label for="station">Station</label>
                    <select id="station" name="station" onchange="this.form.submit()">
                        <option value="">All stations</option>
                        <?php foreach ($stations as $station): ?>
                            <option value="<?php echo h($station); ?>" <?php echo ($selectedStation === $station) ? 'selected' : ''; ?>>
                                <?php echo h($station); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-field">
                    <label for="batch_id">Batch</label>
                    <select id="batch_id" name="batch_id" onchange="this.form.submit()">
                        <?php foreach ($batches as $batch): ?>
                            <option value="<?php echo (int)$batch['batch_id']; ?>" <?php echo ((int)$batch['batch_id'] === $selectedBatch) ? 'selected' : ''; ?>>
                                <?php echo h($batch['batch_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <?php if ($currentBatch): ?>
        <div class="tabulation-heading">
            FTC CLASS INTERNAL / MID-TERM / FINAL TABULATION | Batch No: <?php echo h($currentBatch['batch_name']); ?> | Station: <?php echo h($currentBatch['station'] ?? ''); ?>
        </div>
    <?php endif; ?>

    <?php if (!$currentBatch): ?>
        <div class="empty">
            <h2>Select a batch</h2>
            <p>Select a batch to display the tabulation sheet.</p>
        </div>
    <?php elseif (count($papers) === 0): ?>
        <div class="empty">
            <h2>No papers found</h2>
            <p>No papers have been mapped to this batch yet.</p>
        </div>
    <?php elseif (count($students) === 0): ?>
        <div class="empty">
            <h2>No students found</h2>
            <p>No active students are assigned to this batch.</p>
        </div>
    <?php else: ?>
        <div class="table-wrapper">
            <table class="tabulation-table">
                <thead>
                    <tr>
                        <th rowspan="4">S.<br>No.</th>
                        <th rowspan="4" class="name-column">Name</th>
                        <th rowspan="4">DSG.</th>
                        <th rowspan="4">Qualification</th>
                        <th rowspan="4">Roll<br>Nos.</th>
                        <?php foreach ($papers as $paper): ?>
                            <th colspan="4" class="paper-header"><?php echo h($paper['paper_name']); ?></th>
                        <?php endforeach; ?>
                        <th rowspan="4">Grand<br>Total</th>
                        <th rowspan="4">%</th>
                        <th rowspan="4">Result</th>
                    </tr>

                    <tr>
                        <?php foreach ($papers as $paper): ?>
                            <th colspan="4" class="paper-code"><?php echo h($paper['paper_code'] ?? ''); ?></th>
                        <?php endforeach; ?>
                    </tr>

                    <tr>
                        <?php foreach ($papers as $paper): ?>
                            <th class="assessment-header">Internal</th>
                            <th class="assessment-header">Mid-Term</th>
                            <th class="assessment-header">Final</th>
                            <th class="assessment-header">Total</th>
                        <?php endforeach; ?>
                    </tr>

                    <tr>
                        <?php foreach ($papers as $paper): ?>
                            <th class="maximum-mark"><?php echo h(number_format((float)($paper['max_internal'] ?? 0), 2)); ?></th>
                            <th class="maximum-mark"><?php echo h(number_format((float)($paper['max_midterm'] ?? 0), 2)); ?></th>
                            <th class="maximum-mark"><?php echo h(number_format((float)($paper['max_final'] ?? 0), 2)); ?></th>
                            <th class="maximum-mark"><?php
                                $paperMaximum = (float)($paper['max_internal'] ?? 0) + (float)($paper['max_midterm'] ?? 0) + (float)($paper['max_final'] ?? 0);
                                echo h(number_format($paperMaximum, 0));
                            ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>

                <tbody>
                    <?php $serialNo = 1; ?>
                    <?php foreach ($students as $student): ?>
                        <?php
                            $studentId = (int)$student['student_id'];
                            $studentTabulation = $tabulation[$studentId] ?? [];
                            $result = $paperResults[$studentId] ?? null;
                            $grandTotal = 0;
                            $hasMarks = false;
                        ?>

                        <tr>
                            <td><?php echo $serialNo; ?></td>
                            <td class="name-column"><?php echo h($student['student_name'] ?? ''); ?></td>
                            <td><?php echo h($student['designation'] ?? ''); ?></td>
                            <td><?php echo h($student['qualification'] ?? ''); ?></td>
                            <td><?php echo h($student['roll_no'] ?? ''); ?></td>

                            <?php foreach ($papers as $paper): ?>
                                <?php
                                    $paperId = (int)$paper['paper_id'];
                                    $paperMarks = $studentTabulation[$paperId] ?? null;
                                    $internal = $paperMarks['internal'] ?? null;
                                    $midterm = $paperMarks['midterm'] ?? null;
                                    $final = $paperMarks['final'] ?? null;
                                    $total = $paperMarks['total'] ?? null;

                                    if ($total !== null && $total !== '') {
                                        $grandTotal += (float)$total;
                                        $hasMarks = true;
                                    }
                                ?>
                                <td><?php echo displayMark($internal); ?></td>
                                <td><?php echo displayMark($midterm); ?></td>
                                <td><?php echo displayMark($final); ?></td>
                                <td><?php echo displayMark($total); ?></td>
                            <?php endforeach; ?>

                            <td><?php echo $hasMarks ? number_format($grandTotal, 1) : '—'; ?></td>
                            <td>
                                <?php
                                    if ($result && $result['percentage'] !== null && $result['percentage'] !== '') {
                                        echo number_format((float)$result['percentage'], 2) . '%';
                                    } else {
                                        echo '—';
                                    }
                                ?>
                            </td>
                            <td>
                                <?php
                                    if ($result && $result['percentage'] !== null && $result['percentage'] !== '') {
                                        $status = resultStatus($result['percentage']);
                                        echo '<span class="' . resultClass($result['percentage']) . '">' . h($status) . '</span>';
                                    } else {
                                        echo '<span class="status-pending">Pending</span>';
                                    }
                                ?>
                            </td>
                        </tr>
                        <?php $serialNo++; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once "../includes/footer.php"; ?>