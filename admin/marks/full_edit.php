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
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
        <div>
            <h1 style="margin:0;color:#1f4e79;">Admin Full Marks Edit</h1>
            <p style="margin:6px 0 0;color:#666;">Edit Internal, Mid-Term, and Final marks within their configured maximums.</p>
        </div>
        <a href="index.php" style="background:#6c757d;color:#fff;padding:10px 16px;text-decoration:none;border-radius:4px;">Back to Marks</a>
    </div>

    <?php if ($errorMessage !== ''): ?><div style="background:#f8d7da;color:#721c24;padding:14px;margin-bottom:18px;border-radius:4px;"><?php echo fullEditHtml($errorMessage); ?></div><?php endif; ?>
    <?php if ($successMessage !== ''): ?><div style="background:#d4edda;color:#155724;padding:14px;margin-bottom:18px;border-radius:4px;"><?php echo fullEditHtml($successMessage); ?></div><?php endif; ?>

    <?php if (!$student): ?>
        <div style="background:#fff;padding:20px;border-radius:6px;">Open this page from Marks Management for a student.</div>
    <?php else: ?>
        <div style="background:#fff;padding:18px 20px;border-radius:6px;margin-bottom:18px;"><strong><?php echo fullEditHtml($student['student_name']); ?></strong> | <?php echo fullEditHtml($student['roll_no']); ?> | <?php echo fullEditHtml($student['batch_name']); ?></div>
        <form method="POST" style="background:#fff;padding:20px;border-radius:6px;">
            <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
            <table style="width:100%;border-collapse:collapse;">
                <thead><tr style="background:#1f4e79;color:#fff;"><th style="padding:10px;text-align:left;">Paper</th><th style="padding:10px;text-align:left;">Subject</th><th style="padding:10px;">Assessment</th><th style="padding:10px;">Maximum</th><th style="padding:10px;">Marks</th><th style="padding:10px;">Status</th></tr></thead>
                <tbody>
                <?php foreach ($marks as $mark): ?>
                    <tr>
                        <td style="padding:10px;border-bottom:1px solid #eee;"><?php echo fullEditHtml($mark['paper_code'] ?? ''); ?></td>
                        <td style="padding:10px;border-bottom:1px solid #eee;"><?php echo fullEditHtml($mark['subject_code'] . ' - ' . $mark['subject_name']); ?></td>
                        <td style="padding:10px;text-align:center;border-bottom:1px solid #eee;"><?php echo ['1' => 'Internal', '2' => 'Mid-Term', '3' => 'Final'][$mark['assessment_type_id']] ?? 'Other'; ?></td>
                        <td style="padding:10px;text-align:center;border-bottom:1px solid #eee;"><?php echo number_format((float)$mark['max_marks'], 2); ?></td>
                        <td style="padding:10px;text-align:center;border-bottom:1px solid #eee;"><input type="number" step="0.01" min="0" max="<?php echo fullEditHtml($mark['max_marks']); ?>" name="marks[<?php echo (int)$mark['entry_id']; ?>]" value="<?php echo fullEditHtml($mark['marks_obtained']); ?>" style="width:100px;padding:7px;"></td>
                        <td style="padding:10px;text-align:center;border-bottom:1px solid #eee;"><?php echo ((int)$mark['is_absent'] === 1) ? 'Absent flag retained' : 'Editable'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <label style="display:block;margin-top:18px;font-weight:bold;">Reason for change <span style="font-weight:normal;color:#777;">(optional)</span></label>
            <textarea name="reason" maxlength="500" style="width:100%;min-height:70px;padding:8px;margin-top:6px;"></textarea>
            <button type="submit" name="save_full_marks" style="margin-top:15px;background:#1f4e79;color:#fff;border:0;padding:11px 18px;border-radius:4px;font-weight:bold;">Save All Marks</button>
        </form>
    <?php endif; ?>
</main>

</div>

<?php require_once __DIR__ . "/../../includes/footer.php"; ?>