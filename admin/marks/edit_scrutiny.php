<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/csrf.php';
require_once __DIR__ . '/../../config/database.php';

$user = require_login();
$canEditScrutiny = has_permission('marks.edit');
if (!$canEditScrutiny && !has_permission('verification.access')) {
    http_response_code(403);
    exit('403 Forbidden');
}

$pageTitle = 'Edit Scrutiny Marks';
$caseId = (int)($_GET['case_id'] ?? $_POST['case_id'] ?? 0);
$studentId = (int)($_GET['student_id'] ?? $_POST['student_id'] ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? $_POST['subject_id'] ?? 0);
$entryId = (int)($_GET['entry_id'] ?? $_POST['entry_id'] ?? 0);
$successMessage = '';
$errorMessage = '';
$ownNotification = null;
$savedCase = false;
$pendingCases = [];

function h($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function loadScrutinyCase(PDO $pdo, int $caseId, int $studentId, int $subjectId, int $entryId): ?array
{
    global $canEditScrutiny;
    $allowedStatuses = $canEditScrutiny ? "'awaiting_admin'" : "'awaiting_recheck', 'verified'";
    
    $sql = "SELECT sc.*, s.student_name, s.roll_no, s.batch_id, sub.subject_code, sub.subject_name,
                   fme.marks_obtained, fme.is_absent, fme.assessment_type_id,
                   at.assessment_type_name,
                   CASE fme.assessment_type_id
                       WHEN 1 THEN COALESCE(NULLIF(psm.subject_max_internal, 0), p.max_internal, sfm.max_marks, 100)
                       WHEN 2 THEN COALESCE(NULLIF(psm.subject_max_midterm, 0), p.max_midterm, sfm.max_marks, 100)
                       WHEN 3 THEN COALESCE(NULLIF(psm.subject_max_final, 0), p.max_final, sfm.max_marks, 100)
                       ELSE COALESCE(sfm.max_marks, 100)
                   END AS max_marks
            FROM scrutiny_cases sc
            INNER JOIN students s ON s.student_id = sc.student_id
            INNER JOIN subjects sub ON sub.subject_id = sc.subject_id
            INNER JOIN faculty_marks_entry fme ON fme.entry_id = sc.entry_id
            INNER JOIN assessment_types at ON at.assessment_type_id = fme.assessment_type_id
            LEFT JOIN papers p ON p.paper_id = sc.paper_id
            LEFT JOIN paper_subject_mapping psm ON psm.paper_id = sc.paper_id AND psm.subject_id = sc.subject_id AND psm.batch_id = s.batch_id
            LEFT JOIN subject_faculty_mapping sfm ON sfm.batch_id = s.batch_id AND sfm.subject_id = sc.subject_id AND sfm.faculty_id = fme.faculty_id
            WHERE sc.status IN ({$allowedStatuses})";

    $params = [];
    if ($caseId > 0) {
        $sql .= " AND sc.case_id = :case_id";
        $params[':case_id'] = $caseId;
    } elseif ($entryId > 0) {
        $sql .= " AND sc.entry_id = :entry_id";
        $params[':entry_id'] = $entryId;
    } elseif ($studentId > 0 && $subjectId > 0) {
        $sql .= " AND sc.student_id = :student_id AND sc.subject_id = :subject_id";
        $params[':student_id'] = $studentId;
        $params[':subject_id'] = $subjectId;
    } elseif ($studentId > 0) {
        $sql .= " AND sc.student_id = :student_id";
        $params[':student_id'] = $studentId;
    }

    $sql .= " ORDER BY sc.case_id ASC LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function loadSubjectAssessmentMarks(PDO $pdo, array $case): array
{
    $sql = "SELECT fme.entry_id, fme.assessment_type_id, fme.marks_obtained, fme.is_absent,
                   at.assessment_type_name,
                   CASE fme.assessment_type_id
                       WHEN 1 THEN COALESCE(NULLIF(psm.subject_max_internal, 0), p.max_internal, sfm.max_marks, 100)
                       WHEN 2 THEN COALESCE(NULLIF(psm.subject_max_midterm, 0), p.max_midterm, sfm.max_marks, 100)
                       WHEN 3 THEN COALESCE(NULLIF(psm.subject_max_final, 0), p.max_final, sfm.max_marks, 100)
                       ELSE COALESCE(sfm.max_marks, 100)
                   END AS max_marks
            FROM faculty_marks_entry fme
            INNER JOIN assessment_types at ON at.assessment_type_id = fme.assessment_type_id
            LEFT JOIN papers p ON p.paper_id = fme.paper_id
            LEFT JOIN paper_subject_mapping psm ON psm.paper_id = fme.paper_id AND psm.subject_id = fme.subject_id AND psm.batch_id = :batch_id
            LEFT JOIN subject_faculty_mapping sfm ON sfm.batch_id = :sfm_batch_id AND sfm.subject_id = fme.subject_id AND sfm.faculty_id = fme.faculty_id
            WHERE fme.student_id = :student_id
              AND fme.subject_id = :subject_id
              AND COALESCE(fme.paper_id, 0) = COALESCE(:paper_id, 0)
            ORDER BY fme.assessment_type_id ASC, fme.entry_id ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':batch_id' => $case['batch_id'],
        ':sfm_batch_id' => $case['batch_id'],
        ':student_id' => $case['student_id'],
        ':subject_id' => $case['subject_id'],
        ':paper_id' => $case['paper_id']
    ]);

    $marks = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $mark) {
        $typeId = (int)$mark['assessment_type_id'];
        if (!isset($marks[$typeId]) || (int)$mark['entry_id'] === (int)$case['entry_id']) {
            $marks[$typeId] = $mark;
        }
    }
    return $marks;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['save_scrutiny_marks']) && $canEditScrutiny) {
    enforceCsrf();
    $submittedMarks = $_POST['marks'] ?? [];
    if (!is_array($submittedMarks) || empty($submittedMarks)) {
        $errorMessage = 'Enter marks for at least one assessment.';
    } else {
        try {
            $pdo->beginTransaction();
            $case = loadScrutinyCase($pdo, $caseId, $studentId, $subjectId, $entryId);
            if (!$case) {
                throw new Exception('This scrutiny case is no longer awaiting an edit.');
            }
            $assessmentMarks = loadSubjectAssessmentMarks($pdo, $case);
            $reason = trim($case['reason']);
            $changedCount = 0;
            $changedEntryIds = [];
            foreach ($assessmentMarks as $assessmentMark) {
                $assessmentEntryId = (int)$assessmentMark['entry_id'];
                if (!array_key_exists((string)$assessmentEntryId, $submittedMarks)) {
                    continue;
                }
                $newMarksText = trim((string)$submittedMarks[$assessmentEntryId]);
                if ($newMarksText === '' || !is_numeric($newMarksText)) {
                    throw new Exception('Enter a numeric mark for ' . $assessmentMark['assessment_type_name'] . '.');
                }
                $newMarks = (float)$newMarksText;
                $maxMarks = (float)$assessmentMark['max_marks'];
                if ($newMarks < 0 || $newMarks > $maxMarks) {
                    throw new Exception($assessmentMark['assessment_type_name'] . ' marks must be between 0 and ' . number_format($maxMarks, 2) . '.');
                }
                $oldMarks = $assessmentMark['marks_obtained'] === null ? null : (float)$assessmentMark['marks_obtained'];
                if ($oldMarks !== null && abs($oldMarks - $newMarks) < 0.001) {
                    continue;
                }

                $stmt = $pdo->prepare('UPDATE faculty_marks_entry SET marks_obtained = :marks, graded_at = CURRENT_TIMESTAMP WHERE entry_id = :entry_id AND student_id = :student_id AND subject_id = :subject_id');
                $stmt->execute([':marks' => $newMarks, ':entry_id' => $assessmentEntryId, ':student_id' => $case['student_id'], ':subject_id' => $case['subject_id']]);
                $stmt = $pdo->prepare("INSERT INTO scrutiny_mark_change_log (case_id, student_id, subject_id, entry_id, old_marks, new_marks, changed_by_user_id, reason, action) VALUES (:case_id, :student_id, :subject_id, :entry_id, :old_marks, :new_marks, :user_id, :reason, 'scrutiny_correction')");
                $stmt->execute([':case_id' => $case['case_id'], ':student_id' => $case['student_id'], ':subject_id' => $case['subject_id'], ':entry_id' => $assessmentEntryId, ':old_marks' => $oldMarks, ':new_marks' => $newMarks, ':user_id' => $user['user_id'], ':reason' => $reason]);
                $changeId = (int)$pdo->lastInsertId();
                $stmt = $pdo->prepare("INSERT INTO scrutiny_notifications (case_id, change_id, recipient_user_id, student_id, subject_id, old_marks, new_marks) VALUES (:case_id, :change_id, :recipient, :student_id, :subject_id, :old_marks, :new_marks)");
                $stmt->execute([':case_id' => $case['case_id'], ':change_id' => $changeId, ':recipient' => $case['flagged_by_user_id'], ':student_id' => $case['student_id'], ':subject_id' => $case['subject_id'], ':old_marks' => $oldMarks, ':new_marks' => $newMarks]);
                $changedCount++;
                $changedEntryIds[] = $assessmentEntryId;
            }
            if ($changedCount === 0) {
                throw new Exception('No marks were changed.');
            }
            error_log('Scrutiny correction changed entry_ids: ' . implode(',', $changedEntryIds));
            $pdo->prepare("UPDATE scrutiny_cases SET status = 'awaiting_recheck' WHERE case_id = :case_id")->execute([':case_id' => $case['case_id']]);
            $pdo->prepare("UPDATE admin_notifications SET is_read = 1 WHERE student_id = :student_id AND (entry_id = :entry_id OR entry_id IS NULL)")->execute([':student_id' => $case['student_id'], ':entry_id' => $case['entry_id']]);
            $changedPlaceholders = implode(',', array_fill(0, count($changedEntryIds), '?'));
            $pdo->prepare("UPDATE faculty_marks_entry SET verification_notified_at = NULL WHERE entry_id IN ($changedPlaceholders)")->execute($changedEntryIds);
            $pdo->commit();
            $successMessage = 'All changed scrutiny marks were saved and sent for verification re-check.';
            $savedCase = true;
            $assessmentMarks = loadSubjectAssessmentMarks($pdo, $case);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errorMessage = $e->getMessage();
        }
    }
}

try {
    $case = $savedCase ? $case : loadScrutinyCase($pdo, $caseId, $studentId, $subjectId, $entryId);
    if (!$case && $errorMessage === '') $errorMessage = 'No flagged scrutiny subject was found.';
    $assessmentMarks = $case ? loadSubjectAssessmentMarks($pdo, $case) : [];
    $history = [];
    $newMarksByEntry = [];
    if ($case) {
        $stmt = $pdo->prepare("SELECT l.*, COALESCE(p.full_name, u.email, 'Deleted user') AS changed_by FROM scrutiny_mark_change_log l LEFT JOIN users u ON u.user_id = l.changed_by_user_id LEFT JOIN profiles p ON p.user_id = u.user_id WHERE l.case_id = :case_id ORDER BY l.changed_at DESC");
        $stmt->execute([':case_id' => $case['case_id']]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($history as $log) {
            $logEntryId = (int)$log['entry_id'];
            if (!array_key_exists($logEntryId, $newMarksByEntry)) {
                $newMarksByEntry[$logEntryId] = $log['new_marks'];
            }
        }
        $stmt = $pdo->prepare("SELECT notification_id, status FROM scrutiny_notifications WHERE case_id = :case_id AND recipient_user_id = :user_id ORDER BY notification_id DESC LIMIT 1");
        $stmt->execute([':case_id' => $case['case_id'], ':user_id' => $user['user_id']]);
        $ownNotification = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    if ($canEditScrutiny) {
        $stmt = $pdo->query("SELECT sc.case_id, sc.student_id, sc.subject_id, sc.entry_id, sc.reason, sc.created_at,
                                    s.student_name, s.roll_no, sub.subject_code, sub.subject_name
                             FROM scrutiny_cases sc
                             INNER JOIN students s ON s.student_id = sc.student_id
                             INNER JOIN subjects sub ON sub.subject_id = sc.subject_id
                             WHERE sc.status = 'awaiting_admin'
                             ORDER BY sc.case_id ASC");
        $pendingCases = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $errorMessage = 'Scrutiny tracking tables are not installed. Run sql/add_scrutiny_edit_tracking.php first.';
    $case = null;
    $assessmentMarks = [];
    $newMarksByEntry = [];
    $history = [];
    $ownNotification = null;
    $pendingCases = [];
}

require_once __DIR__ . '/../../includes/header.php';
?>
<div style="display:flex;flex-direction:column;min-height:100vh;">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>
    <div style="display:flex;flex:1;min-height:0;">
        <?php if (($user['role_name'] ?? '') === 'verification'): ?>
            <?php require_once __DIR__ . '/../../includes/verification_sidebar.php'; ?>
        <?php else: ?>
            <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        <?php endif; ?>
        <main style="flex:1;min-width:0;padding:30px;background:#f4f6f8;overflow-x:auto;">
<div style="max-width:1100px;margin:0 auto;">
    <h1 style="color:#1f4e79;margin-bottom:18px;">Edit Scrutiny Marks</h1>
    <?php if ($canEditScrutiny && !empty($pendingCases)): ?>
        <section style="background:#fff;padding:18px 20px;border:1px solid #ddd;margin-bottom:22px;border-radius:6px;box-shadow:0 1px 4px rgba(0,0,0,0.05);">
            <h2 style="color:#1f4e79;font-size:20px;margin:0 0 8px;">Pending Scrutiny Queue (<?php echo count($pendingCases); ?> unedited)</h2>
            <p style="margin:0 0 14px;color:#666;font-size:13px;">All unedited change requests remain in this queue until edited and saved by an administrator.</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php foreach ($pendingCases as $pendingCase): ?>
                    <?php $pendingIsCurrent = (int)$pendingCase['case_id'] === (int)($case['case_id'] ?? 0); ?>
                    <a href="edit_scrutiny.php?case_id=<?php echo (int)$pendingCase['case_id']; ?>&amp;student_id=<?php echo (int)$pendingCase['student_id']; ?>&amp;subject_id=<?php echo (int)$pendingCase['subject_id']; ?>&amp;entry_id=<?php echo (int)$pendingCase['entry_id']; ?>" style="display:inline-block;padding:9px 14px;border:1px solid <?php echo $pendingIsCurrent ? '#1f4e79' : '#ccc'; ?>;background:<?php echo $pendingIsCurrent ? '#1f4e79' : '#fff'; ?>;color:<?php echo $pendingIsCurrent ? '#fff' : '#1f4e79'; ?>;text-decoration:none;font-weight:bold;border-radius:4px;font-size:13px;">
                        [Roll #<?php echo h($pendingCase['roll_no']); ?>] <?php echo h($pendingCase['student_name']); ?> &mdash; <?php echo h($pendingCase['subject_code'] . ' ' . $pendingCase['subject_name']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
    <?php if ($successMessage): ?><div style="background:#d9f2df;color:#146c2e;padding:12px;margin-bottom:16px;"><?php echo h($successMessage); ?></div><?php endif; ?>
    <?php if ($errorMessage): ?><div style="background:#fdecea;color:#a12622;padding:12px;margin-bottom:16px;"><?php echo h($errorMessage); ?></div><?php endif; ?>
    <?php if ($case): ?>
        <section style="background:#fff;padding:20px;border:1px solid #ddd;margin-bottom:22px;">
            <h2 style="margin-bottom:10px;">Student Information</h2>
            <p><strong>Student:</strong> <?php echo h($case['student_name']); ?> &nbsp; <strong>Roll No:</strong> <?php echo h($case['roll_no']); ?></p>
            <p style="margin-top:8px;"><strong>Scrutiny Reason:</strong> <?php echo h($case['reason']); ?></p>
        </section>
        <form method="post" style="background:#fff;padding:20px;border:1px solid #ddd;">
            <?php echo csrfField(); ?><input type="hidden" name="case_id" value="<?php echo (int)$case['case_id']; ?>"><input type="hidden" name="student_id" value="<?php echo (int)$case['student_id']; ?>"><input type="hidden" name="subject_id" value="<?php echo (int)$case['subject_id']; ?>"><input type="hidden" name="entry_id" value="<?php echo (int)$case['entry_id']; ?>">
            <table style="width:100%;border-collapse:collapse;margin-bottom:18px;"><thead><tr><th style="text-align:left;padding:10px;background:#1f4e79;color:#fff;">Assessment</th><th style="text-align:left;padding:10px;background:#1f4e79;color:#fff;">Subject</th><th style="padding:10px;background:#1f4e79;color:#fff;">Maximum</th><th style="padding:10px;background:#1f4e79;color:#fff;">New Marks</th></tr></thead><tbody>
                <?php foreach ($assessmentMarks as $assessmentMark): ?>
                    <tr>
                        <td style="padding:10px;border-bottom:1px solid #ddd;"><?php echo h($assessmentMark['assessment_type_name']); ?></td>
                        <td style="padding:10px;border-bottom:1px solid #ddd;"><?php echo h($case['subject_code'] . ' - ' . $case['subject_name']); ?></td>
                        <td style="padding:10px;text-align:center;border-bottom:1px solid #ddd;"><?php echo number_format((float)$assessmentMark['max_marks'], 2); ?></td>
                        <td style="padding:10px;text-align:center;border-bottom:1px solid #ddd;">
                            <?php if ($canEditScrutiny && !$savedCase): ?>
                                <input required type="number" step="0.01" min="0" max="<?php echo h($assessmentMark['max_marks']); ?>" name="marks[<?php echo (int)$assessmentMark['entry_id']; ?>]" value="<?php echo h($assessmentMark['marks_obtained']); ?>">
                            <?php else: ?>
                                <?php
                                    $assessmentEntryId = (int)$assessmentMark['entry_id'];
                                    $displayNewMarks = $newMarksByEntry[$assessmentEntryId] ?? $assessmentMark['marks_obtained'];
                                ?>
                                <span style="color:#666;"><?php echo $displayNewMarks === null ? 'N/A' : h($displayNewMarks); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody></table>
            <?php if ($canEditScrutiny && !$savedCase): ?><button type="submit" name="save_scrutiny_marks" style="background:#1f4e79;color:#fff;border:0;padding:10px 16px;">Save Scrutiny Changes</button><?php endif; ?> <a href="<?php echo $canEditScrutiny ? '../dashboard.php' : '../../verification/dashboard.php'; ?>" style="display:inline-block;padding:9px 16px;">Back</a>
        </form>
        <section style="background:#fff;padding:20px;border:1px solid #ddd;margin-top:22px;"><h2 style="margin-bottom:14px;">Edited Logs / Change History</h2><?php if (!$history): ?><p>No changes recorded yet.</p><?php else: ?><?php foreach ($history as $log): ?><div style="border-bottom:1px solid #ddd;padding:12px 0;"><strong><?php echo h($case['subject_name']); ?></strong><br><?php echo h($log['old_marks']); ?> &rarr; <?php echo h($log['new_marks']); ?><br>Changed by: <?php echo h($log['changed_by']); ?><br><?php echo h(date('d M Y, g:i A', strtotime($log['changed_at']))); ?><br>Reason: <?php echo h($log['reason']); ?><br>Status: <strong>Changed - Awaiting Re-check</strong></div><?php endforeach; ?><?php endif; ?></section>
        <?php if ($ownNotification && ($user['role_name'] ?? '') === 'verification'): ?><form method="post" action="notification_action.php" style="display:flex;gap:8px;flex-wrap:wrap;margin-top:18px;"><?php echo csrfField(); ?><input type="hidden" name="notification_id" value="<?php echo (int)$ownNotification['notification_id']; ?>"><input type="hidden" name="case_id" value="<?php echo (int)$case['case_id']; ?>"><button type="submit" name="action" value="verified" <?php echo $ownNotification['status'] === 'verified' ? 'disabled' : ''; ?> style="background:#087f3e;color:#fff;border:0;padding:10px 16px;">&#10003; Re-checked / Verified</button><button type="submit" name="action" value="not_correct" style="background:#b02a37;color:#fff;border:0;padding:10px 16px;">&#10007; Not Correct</button></form><?php endif; ?>
    <?php endif; ?>
</div>
</main>
    </div>
    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
