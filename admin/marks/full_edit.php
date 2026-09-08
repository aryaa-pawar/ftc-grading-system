<?php

require_once __DIR__ . "/../../includes/auth.php";
$adminUser = require_permission('marks.view');
if (!in_array(strtolower((string)($adminUser['role_name'] ?? '')), ['admin', 'dept_admin', 'super_admin'], true)) {
    http_response_code(403);
    exit('403 Forbidden: Admin access is required for Full Marks Edit.');
}
require_once __DIR__ . "/../../config/database.php";

$pageTitle = "Admin Full Marks Edit";
$studentId = (int)($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
$successMessage = '';
$errorMessage = '';
$student = null;
$marks = [];

function fullEditHtml($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_full_marks'])) {
    $postedMarks = $_POST['marks'] ?? [];
    $reason = trim($_POST['reason'] ?? '');

    if ($studentId <= 0 || !is_array($postedMarks)) {
        $errorMessage = 'Invalid student or marks data.';
    } elseif (strlen($reason) > 500) {
        $errorMessage = 'Reason cannot exceed 500 characters.';
    } else {
        try {
            $pdo->beginTransaction();

            $studentStmt = $pdo->prepare('SELECT student_id, batch_id FROM students WHERE student_id = :student_id AND is_deleted = 0 FOR UPDATE');
            $studentStmt->execute([':student_id' => $studentId]);
            $studentRow = $studentStmt->fetch(PDO::FETCH_ASSOC);

            if (!$studentRow) {
                throw new Exception('Student not found.');
            }

            $entryIds = array_values(array_filter(array_map('intval', array_keys($postedMarks))));
            if (!$entryIds) {
                throw new Exception('No marks were submitted.');
            }

            $entryPlaceholderNames = [];
            foreach ($entryIds as $index => $entryId) {
                $entryPlaceholderNames[] = ':entry_' . $index;
            }
            $entryPlaceholders = implode(',', $entryPlaceholderNames);
            $entryStmt = $pdo->prepare("SELECT fme.entry_id, fme.student_id, fme.subject_id, fme.marks_obtained, fme.assessment_type_id, fme.is_absent,
                    sub.subject_name,
                    CASE fme.assessment_type_id
                        WHEN 1 THEN COALESCE(NULLIF(psm.subject_max_internal, 0), p.max_internal, 100)
                        WHEN 2 THEN COALESCE(NULLIF(psm.subject_max_midterm, 0), p.max_midterm, 100)
                        WHEN 3 THEN COALESCE(NULLIF(psm.subject_max_final, 0), p.max_final, 100)
                        ELSE 100
                    END AS max_marks
                FROM faculty_marks_entry fme
                INNER JOIN subjects sub ON sub.subject_id = fme.subject_id
                LEFT JOIN papers p ON p.paper_id = fme.paper_id
                LEFT JOIN paper_subject_mapping psm ON psm.paper_id = fme.paper_id AND psm.subject_id = fme.subject_id AND psm.batch_id = :batch_id
                WHERE fme.student_id = :student_id AND fme.entry_id IN ($entryPlaceholders)
                FOR UPDATE");
            $entryStmt->bindValue(':batch_id', (int)$studentRow['batch_id'], PDO::PARAM_INT);
            $entryStmt->bindValue(':student_id', $studentId, PDO::PARAM_INT);
            foreach ($entryIds as $index => $entryId) {
                $entryStmt->bindValue(':entry_' . $index, $entryId, PDO::PARAM_INT);
            }
            $entryStmt->execute();
            $entries = $entryStmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($entries) !== count($entryIds)) {
                throw new Exception('One or more marks entries are invalid for this student.');
            }

            $currentUser = current_user();
            $adminName = $currentUser['full_name'] ?? $currentUser['email'] ?? 'Admin';
            $adminUserId = !empty($currentUser['user_id']) ? (int)$currentUser['user_id'] : null;
            $changedCount = 0;

            foreach ($entries as $entry) {
                $rawValue = trim((string)($postedMarks[$entry['entry_id']] ?? ''));
                $newMarks = $rawValue === '' ? null : (is_numeric($rawValue) ? (float)$rawValue : false);
                $maxMarks = max(0, (float)$entry['max_marks']);

                if ($newMarks === false) {
                    throw new Exception('Marks for ' . $entry['subject_name'] . ' must be numeric or blank.');
                }
                if ($newMarks !== null && ($newMarks < 0 || $newMarks > $maxMarks)) {
                    throw new Exception('Marks for ' . $entry['subject_name'] . ' must be between 0 and ' . number_format($maxMarks, 2) . '.');
                }

                $oldMarks = $entry['marks_obtained'] === null ? null : (float)$entry['marks_obtained'];
                if (($oldMarks === null && $newMarks === null) || ($oldMarks !== null && $newMarks !== null && abs($oldMarks - $newMarks) < 0.001)) {
                    continue;
                }

                $updateStmt = $pdo->prepare('UPDATE faculty_marks_entry SET marks_obtained = :marks, graded_at = CURRENT_TIMESTAMP WHERE entry_id = :entry_id AND student_id = :student_id');
                $updateStmt->execute([
                    ':marks' => $newMarks,
                    ':entry_id' => (int)$entry['entry_id'],
                    ':student_id' => $studentId
                ]);

                $auditStmt = $pdo->prepare('INSERT INTO admin_marks_audit (entry_id, student_id, subject_id, old_marks, new_marks, admin_user_id, admin_name, reason) VALUES (:entry_id, :student_id, :subject_id, :old_marks, :new_marks, :admin_user_id, :admin_name, :reason)');
                $auditStmt->execute([
                    ':entry_id' => (int)$entry['entry_id'],
                    ':student_id' => $studentId,
                    ':subject_id' => (int)$entry['subject_id'],
                    ':old_marks' => $oldMarks,
                    ':new_marks' => $newMarks,
                    ':admin_user_id' => $adminUserId,
                    ':admin_name' => $adminName,
                    ':reason' => $reason !== '' ? $reason : 'Admin full marks edit'
                ]);
                $changedCount++;
            }

            $pdo->commit();
            $successMessage = $changedCount . ' mark entr' . ($changedCount === 1 ? 'y was' : 'ies were') . ' updated successfully.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessage = $e->getMessage();
        }
    }
}

if ($studentId > 0) {
    try {
        $studentStmt = $pdo->prepare('SELECT s.student_id, s.roll_no, s.student_name, b.batch_name FROM students s INNER JOIN batches b ON b.batch_id = s.batch_id WHERE s.student_id = :student_id AND s.is_deleted = 0');
        $studentStmt->execute([':student_id' => $studentId]);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

        if ($student) {
            $marksStmt = $pdo->prepare("SELECT fme.entry_id, fme.subject_id, fme.assessment_type_id, fme.marks_obtained, fme.is_absent,
                    sub.subject_code, sub.subject_name, p.paper_code,
                    CASE fme.assessment_type_id
                        WHEN 1 THEN COALESCE(NULLIF(psm.subject_max_internal, 0), p.max_internal, 100)
                        WHEN 2 THEN COALESCE(NULLIF(psm.subject_max_midterm, 0), p.max_midterm, 100)
                        WHEN 3 THEN COALESCE(NULLIF(psm.subject_max_final, 0), p.max_final, 100)
                        ELSE 100
                    END AS max_marks
                FROM faculty_marks_entry fme
                INNER JOIN students s ON s.student_id = fme.student_id
                INNER JOIN subjects sub ON sub.subject_id = fme.subject_id
                LEFT JOIN papers p ON p.paper_id = fme.paper_id
                LEFT JOIN paper_subject_mapping psm ON psm.paper_id = fme.paper_id AND psm.subject_id = fme.subject_id AND psm.batch_id = s.batch_id
                WHERE fme.student_id = :student_id
                ORDER BY p.paper_code, sub.subject_code, fme.assessment_type_id, fme.entry_id");
            $marksStmt->execute([':student_id' => $studentId]);
            $marks = $marksStmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $errorMessage = 'Unable to load marks.';
    }
}

require_once __DIR__ . "/../../includes/header.php";
require_once __DIR__ . "/../../includes/navbar.php";
?>

<div style="display:flex; flex:1; min-height:calc(100vh - 60px);">

<?php
require_once __DIR__ . "/../../includes/sidebar.php";
?>

<main style="flex:1; padding:30px; background:#f4f6f8; min-width:0; overflow-x:auto;">
    <style>
        .full-edit-page {
            max-width: 1400px;
            margin: 0 auto;
        }

        .full-edit-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 20px;
        }

        .full-edit-header h1 {
            margin: 0;
            color: #1f4e79;
            font-size: 28px;
        }

        .full-edit-header p {
            margin: 6px 0 0;
            color: #667085;
            font-size: 14px;
        }

        .full-edit-back {
            flex-shrink: 0;
            background: #6c757d;
            color: #fff;
            padding: 10px 16px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            font-size: 13px;
        }

        .full-edit-student,
        .full-edit-card {
            background: #fff;
            border: 1px solid #e1e6eb;
            border-radius: 6px;
            box-shadow: 0 2px 7px rgba(31, 78, 121, 0.06);
        }

        .full-edit-student {
            padding: 16px 20px;
            margin-bottom: 18px;
            color: #344054;
        }

        .full-edit-student strong {
            color: #1f4e79;
            font-size: 15px;
        }

        .full-edit-card {
            padding: 16px;
            overflow-x: auto;
        }

        .full-edit-table {
            width: 100%;
            min-width: 760px;
            border-collapse: collapse;
            color: #344054;
            font-size: 13px;
        }

        .full-edit-table th {
            padding: 12px 10px;
            background: #1f4e79;
            color: #fff;
            text-align: left;
            font-size: 12px;
            letter-spacing: .2px;
            white-space: nowrap;
        }

        .full-edit-table th:nth-child(3),
        .full-edit-table th:nth-child(4),
        .full-edit-table th:nth-child(5),
        .full-edit-table th:nth-child(6),
        .full-edit-table td:nth-child(3),
        .full-edit-table td:nth-child(4),
        .full-edit-table td:nth-child(5),
        .full-edit-table td:nth-child(6) {
            text-align: center;
        }

        .full-edit-table td {
            padding: 11px 10px;
            border-bottom: 1px solid #eaecf0;
            vertical-align: middle;
        }

        .full-edit-table tbody tr:hover {
            background: #f8fafc;
        }

        .full-edit-table input[type="number"] {
            width: 92px;
            padding: 8px 9px;
            border: 1px solid #b8c4d0;
            border-radius: 4px;
            text-align: center;
            color: #1f2937;
            background: #fff;
        }

        .full-edit-table input[type="number"]:focus,
        .full-edit-reason:focus {
            outline: none;
            border-color: #1f4e79;
            box-shadow: 0 0 0 3px rgba(31, 78, 121, .12);
        }

        .full-edit-status {
            color: #278a4b;
            font-weight: bold;
        }

        .full-edit-reason-label {
            display: block;
            margin-top: 20px;
            font-weight: bold;
            color: #344054;
        }

        .full-edit-reason {
            width: 100%;
            min-height: 76px;
            margin-top: 7px;
            padding: 10px;
            border: 1px solid #b8c4d0;
            border-radius: 4px;
            resize: vertical;
        }

        .full-edit-save {
            margin-top: 15px;
            background: #1f4e79;
            color: #fff;
            border: 0;
            padding: 11px 20px;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
        }

        .full-edit-save:hover,
        .full-edit-back:hover {
            filter: brightness(.92);
        }

        @media (max-width: 700px) {
            .full-edit-header {
                flex-direction: column;
            }

            .full-edit-back {
                align-self: flex-start;
            }
        }
    </style>

    <div class="full-edit-page">
    <div class="full-edit-header">
        <div>
            <h1>Admin Full Marks Edit</h1>
            <p>Edit Internal, Mid-Term, and Final marks within their configured maximums.</p>
        </div>
        <a href="index.php" class="full-edit-back">Back to Marks</a>
    </div>

    <?php if ($errorMessage !== ''): ?><div style="background:#f8d7da;color:#721c24;padding:14px;margin-bottom:18px;border-radius:4px;"><?php echo fullEditHtml($errorMessage); ?></div><?php endif; ?>
    <?php if ($successMessage !== ''): ?><div style="background:#d4edda;color:#155724;padding:14px;margin-bottom:18px;border-radius:4px;"><?php echo fullEditHtml($successMessage); ?></div><?php endif; ?>

    <?php if (!$student): ?>
        <div style="background:#fff;padding:20px;border-radius:6px;">Open this page from Marks Management for a student.</div>
    <?php else: ?>
        <div class="full-edit-student"><strong><?php echo fullEditHtml($student['student_name']); ?></strong> <span>|</span> <?php echo fullEditHtml($student['roll_no']); ?> <span>|</span> <?php echo fullEditHtml($student['batch_name']); ?></div>
        <form method="POST" class="full-edit-card">
            <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
            <table class="full-edit-table">
                <thead><tr><th>Paper</th><th>Subject</th><th>Assessment</th><th>Maximum</th><th>Marks</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($marks as $mark): ?>
                    <tr>
                        <td><?php echo fullEditHtml($mark['paper_code'] ?? ''); ?></td>
                        <td><?php echo fullEditHtml($mark['subject_code'] . ' - ' . $mark['subject_name']); ?></td>
                        <td><?php echo ['1' => 'Internal', '2' => 'Mid-Term', '3' => 'Final'][$mark['assessment_type_id']] ?? 'Other'; ?></td>
                        <td><?php echo number_format((float)$mark['max_marks'], 2); ?></td>
                        <td><input type="number" step="0.01" min="0" max="<?php echo fullEditHtml($mark['max_marks']); ?>" name="marks[<?php echo (int)$mark['entry_id']; ?>]" value="<?php echo fullEditHtml($mark['marks_obtained']); ?>"></td>
                        <td class="full-edit-status"><?php echo ((int)$mark['is_absent'] === 1) ? 'Absent flag retained' : 'Editable'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <label class="full-edit-reason-label">Reason for change <span style="font-weight:normal;color:#667085;">(optional)</span></label>
            <textarea name="reason" maxlength="500" class="full-edit-reason"></textarea>
            <button type="submit" name="save_full_marks" class="full-edit-save">Save All Marks</button>
        </form>
    <?php endif; ?>
    </div>
</main>

</div>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>