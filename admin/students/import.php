<?php

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../imdAuth/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$pageTitle = 'Import Students';
$headers = ['roll_no', 'student_name', 'email', 'contact_number', 'designation', 'qualification', 'rmc', 'batch_number', 'group_code'];
$preview = $_SESSION['student_import_preview'] ?? null;
$errors = [];
$successMessage = '';

if (!class_exists('ZipArchive')) {
    $errors[] = 'Excel support is not active in the web server. Enable the PHP ZIP extension and restart Apache from the XAMPP Control Panel.';
}

function importText($value): string
{
    return trim((string)($value ?? ''));
}

function importHeaderKey(string $value): string
{
    return preg_replace('/[^a-z0-9]/', '', strtolower(trim($value)));
}

function importHeaderMap(): array
{
    return [
        'rollno' => 'roll_no',
        'rollnumber' => 'roll_no',
        'studentname' => 'student_name',
        'fullname' => 'student_name',
        'email' => 'email',
        'emailaddress' => 'email',
        'contactnumber' => 'contact_number',
        'phone' => 'contact_number',
        'designation' => 'designation',
        'qualification' => 'qualification',
        'rmc' => 'rmc',
        'batchnumber' => 'batch_number',
        'groupcode' => 'group_code',
        'group' => 'group_code'
    ];
}

function importErrorReport(array $rows): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="student-import-errors.csv"');
    $output = fopen('php://output', 'wb');
    fputcsv($output, ['Excel Row', 'Roll No', 'Student Name', 'Batch Number', 'Status', 'Errors']);
    foreach ($rows as $row) {
        if (empty($row['errors']) && empty($row['duplicate'])) {
            continue;
        }
        $status = $row['duplicate'] ? 'Duplicate' : 'Invalid';
        fputcsv($output, [$row['excel_row'], $row['data']['roll_no'] ?? '', $row['data']['student_name'] ?? '', $row['data']['batch_number'] ?? '', $status, implode(' | ', $row['errors'])]);
    }
    fclose($output);
    exit;
}

if (isset($_GET['download']) && $_GET['download'] === 'errors') {
    $token = $_GET['token'] ?? '';
    if (!hash_equals((string)($_SESSION['student_import_token'] ?? ''), (string)$token) || !$preview) {
        http_response_code(404);
        exit('Import report not found.');
    }
    importErrorReport($preview['rows']);
}

if (isset($_GET['download']) && $_GET['download'] === 'template') {
    if (!class_exists('ZipArchive')) {
        http_response_code(503);
        exit('Excel support is not active. Restart Apache after enabling the PHP ZIP extension.');
    }
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([$headers], null, 'A1');
    $sheet->getStyle('A1:I1')->getFont()->setBold(true);
    foreach (range('A', 'I') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="student-import-template.xlsx"');
    (new Xlsx($spreadsheet))->save('php://output');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload' && class_exists('ZipArchive')) {
        $preview = null;
        unset($_SESSION['student_import_preview'], $_SESSION['student_import_token']);
        $file = $_FILES['student_file'] ?? null;
        $allowed = ['xlsx', 'xls'];
        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));

        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Please select a valid Excel file.';
        } elseif (!in_array($extension, $allowed, true)) {
            $errors[] = 'Only .xlsx and .xls files are supported.';
        } elseif ((int)$file['size'] > 10 * 1024 * 1024) {
            $errors[] = 'The Excel file must be 10 MB or smaller.';
        } else {
            try {
                $reader = IOFactory::createReaderForFile($file['tmp_name']);
                $reader->setReadDataOnly(true);
                $sheet = $reader->load($file['tmp_name'])->getActiveSheet();
                $rawRows = $sheet->toArray(null, true, true, false);
                $uploadedHeaders = array_map('importText', $rawRows[0] ?? []);
                $map = [];
                $headerAliases = importHeaderMap();
                foreach ($uploadedHeaders as $index => $header) {
                    $key = importHeaderKey($header);
                    if (isset($headerAliases[$key])) {
                        $map[$headerAliases[$key]] = $index;
                    }
                }
                $missingHeaders = array_values(array_diff(['roll_no', 'student_name', 'batch_number'], array_keys($map)));
                if ($missingHeaders) {
                    throw new RuntimeException('Missing required headers: ' . implode(', ', $missingHeaders));
                }

                $rows = [];
                $batches = $pdo->query('SELECT batch_id, batch_number FROM batches')->fetchAll(PDO::FETCH_ASSOC);
                $groups = $pdo->query('SELECT group_code FROM elective_groups')->fetchAll(PDO::FETCH_COLUMN);
                $batchSet = [];
                foreach ($batches as $batch) {
                    $batchSet[strtolower(importText($batch['batch_number']))] = (int)$batch['batch_id'];
                }
                $groupSet = array_fill_keys(array_map('strval', $groups), true);
                $rolls = [];
                foreach ($rawRows as $index => $rawRow) {
                    if ($index === 0 || count(array_filter($rawRow, static fn($value): bool => importText($value) !== '')) === 0) {
                        continue;
                    }
                    $data = [];
                    foreach ($headers as $header) {
                        $data[$header] = isset($map[$header]) ? importText($rawRow[$map[$header]] ?? '') : '';
                    }
                    $rowErrors = [];
                    if ($data['roll_no'] === '') $rowErrors[] = 'Roll No is required.';
                    if ($data['student_name'] === '') $rowErrors[] = 'Student Name is required.';
                    if ($data['batch_number'] === '') $rowErrors[] = 'Batch Number is required.';
                    if (strlen($data['roll_no']) > 50) $rowErrors[] = 'Roll No exceeds 50 characters.';
                    if (strlen($data['student_name']) > 150) $rowErrors[] = 'Student Name exceeds 150 characters.';
                    if ($data['email'] !== '' && (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 100)) $rowErrors[] = 'Email is invalid or exceeds 100 characters.';
                    if ($data['contact_number'] !== '' && (!preg_match('/^[0-9+\-\s]{7,15}$/', $data['contact_number']) || strlen($data['contact_number']) > 20)) $rowErrors[] = 'Contact number is invalid.';
                    foreach (['designation' => 100, 'qualification' => 150, 'rmc' => 100] as $field => $max) {
                        if (strlen($data[$field]) > $max) $rowErrors[] = ucfirst($field) . " exceeds {$max} characters.";
                    }
                    $batchKey = strtolower($data['batch_number']);
                    if (!isset($batchSet[$batchKey])) $rowErrors[] = 'Batch Number does not exist.';
                    $data['batch_id'] = $batchSet[$batchKey] ?? 0;
                    if ($data['group_code'] !== '' && (!preg_match('/^[A-Za-z0-9]$/', $data['group_code']) || !isset($groupSet[$data['group_code']])) ) $rowErrors[] = 'Group code does not exist.';
                    $rollKey = strtolower($data['roll_no']);
                    $duplicate = isset($rolls[$rollKey]);
                    if ($duplicate) $rowErrors[] = 'Duplicate Roll No in Excel (also row ' . $rolls[$rollKey] . ').';
                    $rolls[$rollKey] = $index + 1;
                    $rows[] = ['excel_row' => $index + 1, 'data' => $data, 'errors' => $rowErrors, 'duplicate' => $duplicate];
                }
                $rollValues = array_values(array_filter(array_column(array_column($rows, 'data'), 'roll_no')));
                if ($rollValues) {
                    $placeholders = implode(',', array_fill(0, count($rollValues), '?'));
                    $existingStmt = $pdo->prepare("SELECT roll_no FROM students WHERE roll_no IN ({$placeholders})");
                    $existingStmt->execute($rollValues);
                    $existingRolls = array_fill_keys(array_map('strtolower', $existingStmt->fetchAll(PDO::FETCH_COLUMN)), true);
                    foreach ($rows as &$row) {
                        if (isset($existingRolls[strtolower($row['data']['roll_no'])])) {
                            $row['duplicate'] = true;
                            $row['errors'][] = 'Roll No already exists in the database.';
                        }
                    }
                    unset($row);
                }
                $preview = ['rows' => $rows, 'valid' => 0, 'invalid' => 0, 'duplicates' => 0];
                $preview['ignored'] = 0;
                foreach ($preview['rows'] as &$row) {
                    $duplicateErrors = array_filter($row['errors'], static function (string $error): bool {
                        return strpos($error, 'Duplicate Roll No in Excel') === 0 || $error === 'Roll No already exists in the database.';
                    });
                    $row['duplicate_only'] = $row['duplicate'] && count($duplicateErrors) === count($row['errors']);
                    if ($row['duplicate']) $preview['duplicates']++;
                    if ($row['duplicate_only']) {
                        $preview['ignored']++;
                    } elseif ($row['errors']) {
                        $preview['invalid']++;
                    } else {
                        $preview['valid']++;
                    }
                }
                unset($row);
                $_SESSION['student_import_preview'] = $preview;
                $_SESSION['student_import_token'] = bin2hex(random_bytes(16));
            } catch (Throwable $e) {
                $errors[] = 'Unable to read this Excel file: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'confirm') {
        $preview = $_SESSION['student_import_preview'] ?? null;
        $importableRows = [];
        $blockingRows = 0;
        if ($preview) {
            foreach ($preview['rows'] as $row) {
                $duplicateErrors = array_filter($row['errors'] ?? [], static function (string $error): bool {
                    return strpos($error, 'Duplicate Roll No in Excel') === 0 || $error === 'Roll No already exists in the database.';
                });
                $duplicateOnly = !empty($row['duplicate']) && count($duplicateErrors) === count($row['errors'] ?? []);
                if ($duplicateOnly) {
                    continue;
                }
                if (!empty($row['errors'])) {
                    $blockingRows++;
                } else {
                    $importableRows[] = $row;
                }
            }
        }
        if (!$preview || $blockingRows > 0 || !$importableRows) {
            $errors[] = 'Import cannot continue until all non-duplicate rows are valid.';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare('INSERT INTO students (roll_no, student_name, email, contact_number, designation, qualification, rmc, batch_id, group_code) VALUES (:roll_no, :student_name, :email, :contact_number, :designation, :qualification, :rmc, :batch_id, :group_code)');
                foreach ($importableRows as $row) {
                    $data = $row['data'];
                    $stmt->execute([
                        ':roll_no' => $data['roll_no'], ':student_name' => $data['student_name'],
                        ':email' => $data['email'] !== '' ? $data['email'] : null,
                        ':contact_number' => $data['contact_number'] !== '' ? $data['contact_number'] : null,
                        ':designation' => $data['designation'] !== '' ? $data['designation'] : null,
                        ':qualification' => $data['qualification'] !== '' ? $data['qualification'] : null,
                        ':rmc' => $data['rmc'] !== '' ? $data['rmc'] : null,
                        ':batch_id' => (int)$data['batch_id'], ':group_code' => $data['group_code'] !== '' ? $data['group_code'] : null
                    ]);
                }
                $pdo->commit();
                $successMessage = count($importableRows) . ' student record(s) imported successfully.';
                unset($_SESSION['student_import_preview'], $_SESSION['student_import_token']);
                $preview = null;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Import failed. No records were inserted: ' . $e->getMessage();
            }
        }
    }
}

$preview = $_SESSION['student_import_preview'] ?? $preview;
$reportUrl = $preview ? 'import.php?download=errors&token=' . urlencode($_SESSION['student_import_token'] ?? '') : '';
require_once __DIR__ . '/../../includes/header.php';
?>
<style>
    .student-import-page {
        --import-ink: #18324b;
        --import-blue: #1f5f8b;
        --import-blue-dark: #16466a;
        --import-sky: #eaf4fb;
        --import-line: #dce6ed;
        --import-muted: #647486;
        --import-bg: #f5f8fa;
        min-height: calc(100vh - 60px);
        padding: 38px clamp(22px, 4vw, 56px) 56px;
        background: radial-gradient(circle at 90% 0%, #e5f2f8 0, transparent 32%), var(--import-bg);
        color: var(--import-ink);
    }

    .import-heading-row,
    .import-card-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }

    .import-heading-row { margin: 0 auto 28px; max-width: 1180px; }
    .import-heading { margin: 0 0 7px; color: var(--import-ink); font-size: clamp(30px, 4vw, 42px); line-height: 1; }
    .import-subtitle { margin: 0; color: var(--import-muted); font-size: 15px; }
    .import-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .import-button { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 0 17px; border: 1px solid transparent; border-radius: 7px; font-weight: 600; font-size: 14px; text-decoration: none; cursor: pointer; transition: transform .2s, box-shadow .2s, background .2s; }
    .import-button:hover { transform: translateY(-1px); box-shadow: 0 5px 12px rgba(24, 50, 75, .13); }
    .import-button-secondary { background: #fff; border-color: var(--import-line); color: var(--import-ink); }
    .import-button-primary { background: var(--import-blue); color: #fff; }
    .import-button-primary:hover { background: var(--import-blue-dark); }

    .import-card { max-width: 1180px; margin: 0 auto 22px; padding: clamp(22px, 3vw, 32px); border: 1px solid rgba(220, 230, 237, .9); border-radius: 12px; background: rgba(255, 255, 255, .94); box-shadow: 0 10px 30px rgba(28, 58, 80, .07); }
    .import-card h2 { margin: 0; color: var(--import-ink); font-size: 25px; }
    .import-card-copy { margin: 9px 0 22px; color: var(--import-muted); font-size: 14px; line-height: 1.55; }
    .import-card-copy strong { color: var(--import-blue); }
    .import-upload-form { display: flex; align-items: end; gap: 14px; flex-wrap: wrap; }
    .import-file-label { display: flex; flex: 1 1 330px; flex-direction: column; gap: 7px; color: var(--import-ink); font-size: 13px; font-weight: 700; }
    .import-file-label input[type=file] { width: 100%; padding: 9px; border: 1px solid var(--import-line); border-radius: 7px; background: #fbfdfe; color: var(--import-muted); font-size: 14px; }
    .import-alert { max-width: 1180px; margin: 0 auto 20px; padding: 14px 17px; border-radius: 8px; font-size: 14px; line-height: 1.55; }
    .import-alert-error { border: 1px solid #f1c4c1; background: #fff5f4; color: #a12622; }
    .import-alert-success { border: 1px solid #b9dfc4; background: #f1fbf3; color: #176b2c; }
    .import-card-heading { margin-bottom: 19px; }
    .import-report { color: #a12622; font-size: 13px; font-weight: 700; }
    .import-stats { display: flex; gap: 9px; flex-wrap: wrap; margin: 0 0 20px; }
    .import-stat { padding: 8px 12px; border-radius: 99px; font-size: 13px; font-weight: 700; }
    .import-stat-valid { background: #e8f6ed; color: #176b2c; }
    .import-stat-invalid { background: #fff0ef; color: #a12622; }
    .import-stat-ignored { background: #fff7df; color: #856404; }
    .import-table-wrap { overflow-x: auto; border: 1px solid var(--import-line); border-radius: 8px; }
    .import-table { min-width: 900px; border-collapse: collapse; font-size: 13px; }
    .import-table th { padding: 12px 13px; background: var(--import-ink); color: #fff; font-size: 12px; letter-spacing: .03em; text-transform: uppercase; }
    .import-table td { padding: 12px 13px; border-bottom: 1px solid #edf1f4; color: #34495b; vertical-align: top; }
    .import-table tbody tr:last-child td { border-bottom: 0; }
    .import-table tbody tr:hover { background: #f7fbfd; }
    .import-status { font-weight: 700; white-space: nowrap; }
    .import-confirm { margin-top: 21px; }
    .import-confirm-button { border: 0; background: #218653; color: #fff; }
    .import-progress, .import-note { margin: 13px 0 0; font-size: 13px; font-weight: 700; }
    .import-progress { color: var(--import-blue); }
    .import-note-error { color: #a12622; }
    .import-note-warning { color: #856404; }
    @media (max-width: 640px) {
        .student-import-page { padding: 28px 16px 42px; }
        .import-button { flex: 1 1 auto; }
        .import-heading { font-size: 32px; }
    }
</style>
<div style="display:flex;flex-direction:column;min-height:100vh;">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>
    <div style="display:flex;flex:1;">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="student-import-page">
            <div class="import-heading-row">
                <div><h1 class="import-heading">Import Students</h1><p class="import-subtitle">Upload, validate, preview, and import student records.</p></div>
                <div class="import-actions"><a href="index.php" class="import-button import-button-secondary">Student List</a><a href="?download=template" class="import-button import-button-primary">Download Excel Template</a></div>
            </div>
            <?php if ($errors): ?><div class="import-alert import-alert-error"><?php foreach ($errors as $error): ?><div><?= e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
            <?php if ($successMessage): ?><div class="import-alert import-alert-success"><?= e($successMessage) ?></div><?php endif; ?>
            <section class="import-card">
                <h2>1. Upload Excel File</h2>
                <p class="import-card-copy">Required headers: <strong>roll_no</strong>, <strong>student_name</strong>, and <strong>batch_number</strong>. Optional fields are included in the template.</p>
                <form method="post" enctype="multipart/form-data" class="import-upload-form">
                    <?= csrfField() ?><input type="hidden" name="action" value="upload"><label class="import-file-label">Excel file<input type="file" name="student_file" accept=".xlsx,.xls" required></label><button type="submit" class="import-button import-button-primary">Validate &amp; Preview</button>
                </form>
            </section>
            <?php if ($preview): ?>
                <section class="import-card">
                    <div class="import-card-heading"><h2>2. Preview Before Import</h2><?php if ($reportUrl): ?><a href="<?= e($reportUrl) ?>" class="import-report">Download Error Report</a><?php endif; ?></div>
                    <div class="import-stats"><strong class="import-stat import-stat-valid">Valid: <?= (int)$preview['valid'] ?></strong><strong class="import-stat import-stat-invalid">Invalid: <?= (int)$preview['invalid'] ?></strong><strong class="import-stat import-stat-ignored">Ignored Duplicates: <?= (int)($preview['ignored'] ?? $preview['duplicates'] ?? 0) ?></strong></div>
                    <div class="import-table-wrap"><table class="import-table"><thead><tr><?php foreach (['Row','Roll No','Student Name','Batch Number','Group','Status','Errors'] as $heading): ?><th><?= e($heading) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($preview['rows'] as $row): ?><tr><?php $bad = !empty($row['errors']); $ignored = !empty($row['duplicate_only']); ?><td><?= (int)$row['excel_row'] ?></td><td><?= e($row['data']['roll_no']) ?></td><td><?= e($row['data']['student_name']) ?></td><td><?= e($row['data']['batch_number']) ?></td><td><?= e($row['data']['group_code']) ?></td><td class="import-status" style="color:<?= $ignored ? '#856404' : ($bad ? '#a12622' : '#176b2c') ?>;"><?= $ignored ? 'Ignored Duplicate' : ($bad ? 'Invalid' : 'Valid') ?></td><td style="color:#a12622;"><?= e(implode(' ', $row['errors'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
                    <?php if ($preview['invalid'] === 0 && $preview['valid'] > 0): ?><form method="post" id="student-import-confirm" class="import-confirm"><?= csrfField() ?><input type="hidden" name="action" value="confirm"><button type="submit" id="student-import-button" class="import-button import-confirm-button">Confirm Import <?= (int)$preview['valid'] ?> New Students</button><div id="student-import-progress" class="import-progress" hidden>Importing <?= (int)$preview['valid'] ?> students, please wait...</div></form><?php elseif ($preview['invalid'] > 0): ?><p class="import-note import-note-error">Fix the invalid rows, then upload the corrected file. Duplicate rows will be ignored automatically.</p><?php else: ?><p class="import-note import-note-warning">All rows are duplicates, so there are no new students to import.</p><?php endif; ?>
                </section>
            <?php endif; ?>
        </main>
    </div>
</div>
<script>
    document.getElementById('student-import-confirm')?.addEventListener('submit', function () {
        document.getElementById('student-import-button').disabled = true;
        document.getElementById('student-import-progress').hidden = false;
    });
</script>