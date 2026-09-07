<?php

/*
|--------------------------------------------------------------------------
| FTC GRADING & TABULATION MANAGEMENT SYSTEM
| VERIFICATION OFFICER - MARKS REVIEW (READ-ONLY)
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/csrf.php";
require_permission('verification.access');
require_once __DIR__ . "/../config/database.php";

$pageTitle = "Marks Review";
$errorMessage = "";

$scrutinyUser = current_user();
$navbarDisplayName = '';
if ($scrutinyUser) {
    $stmtName = getDbConnection()->prepare('SELECT full_name FROM profiles WHERE user_id = :uid LIMIT 1');
    $stmtName->execute([':uid' => $scrutinyUser['user_id']]);
    $profName = trim((string)$stmtName->fetchColumn());
    $navbarDisplayName = !empty($profName) ? $profName : (!empty($scrutinyUser['full_name']) ? $scrutinyUser['full_name'] : ($scrutinyUser['email'] ?? ''));
}

$batches = [];
$students = [];
$paperGroups = [];
$studentSubjectMarks = [];
$studentMarkEntries = [];
$paperResults = [];

$selectedBatchId = isset($_GET["batch_id"]) ? (int)$_GET["batch_id"] : 0;
$isAjaxRequest = isset($_POST['ajax']) && $_POST['ajax'] === '1';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['verification_action'])) {
    enforceCsrf();

    $studentId = (int)($_POST['student_id'] ?? 0);
    $entryId = (int)($_POST['entry_id'] ?? 0);
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $paperId = (int)($_POST['paper_id'] ?? 0);
    $status = $_POST['verification_status'] ?? '';
    $action = $_POST['verification_action'];
    $returnBatchId = (int)($_POST['batch_id'] ?? 0);
    $scrutinyReason = trim($_POST['scrutiny_reason'] ?? '');
    $currentUser = current_user();

    if ($action === 'unlock_review' && $studentId > 0 && $returnBatchId > 0) {
        try {
            $pdo->beginTransaction();
            $studentStmt = $pdo->prepare("SELECT verification_status FROM students WHERE student_id = :student_id AND batch_id = :batch_id FOR UPDATE");
            $studentStmt->execute([':student_id' => $studentId, ':batch_id' => $returnBatchId]);
            if ($studentStmt->fetchColumn() !== 'correct') {
                throw new Exception('This student is not currently confirmed.');
            }
            $pdo->prepare("UPDATE scrutiny_cases SET status = 'verified' WHERE student_id = :student_id AND status IN ('awaiting_admin', 'awaiting_recheck')")->execute([':student_id' => $studentId]);
            $pdo->prepare("UPDATE scrutiny_notifications SET status = 'verified', is_read = 1 WHERE student_id = :student_id AND status = 'awaiting_recheck'")->execute([':student_id' => $studentId]);
            $entryStmt = $pdo->prepare("SELECT DISTINCT fme.entry_id FROM faculty_marks_entry fme INNER JOIN students st ON st.student_id = fme.student_id INNER JOIN paper_subject_mapping psm ON psm.batch_id = st.batch_id AND psm.paper_id = fme.paper_id AND psm.subject_id = fme.subject_id INNER JOIN subjects sub ON sub.subject_id = fme.subject_id LEFT JOIN elective_subject_mapping esm ON esm.subject_id = fme.subject_id AND LOWER(TRIM(esm.group_code)) = LOWER(TRIM(st.group_code)) WHERE fme.student_id = :student_id AND st.batch_id = :batch_id AND fme.assessment_type_id IN (1, 2, 3) AND (fme.marks_obtained IS NOT NULL OR fme.is_absent = 1) AND (COALESCE(sub.is_elective, 0) = 0 OR esm.subject_id IS NOT NULL OR NOT EXISTS (SELECT 1 FROM elective_subject_mapping esm_any WHERE esm_any.subject_id = fme.subject_id))");
            $entryStmt->execute([':student_id' => $studentId, ':batch_id' => $returnBatchId]);
            $applicableEntryIds = array_map('intval', $entryStmt->fetchAll(PDO::FETCH_COLUMN));
            if ($applicableEntryIds) {
                $placeholders = implode(',', array_fill(0, count($applicableEntryIds), '?'));
                $resetStmt = $pdo->prepare("UPDATE faculty_marks_entry SET verification_status = NULL, verification_notified_at = NULL WHERE entry_id IN ($placeholders)");
                $resetStmt->execute($applicableEntryIds);
                error_log('Scrutiny unlock reset entries: ' . json_encode(['student_id' => $studentId, 'batch_id' => $returnBatchId, 'entry_ids' => $applicableEntryIds, 'rows_affected' => $resetStmt->rowCount()], JSON_UNESCAPED_SLASHES));
            }
            $studentResetStmt = $pdo->prepare('UPDATE students SET verification_status = NULL, verification_notified_at = NULL WHERE student_id = :student_id AND batch_id = :batch_id AND verification_status = \'correct\'');
            $studentResetStmt->execute([':student_id' => $studentId, ':batch_id' => $returnBatchId]);
            if ($studentResetStmt->rowCount() !== 1) {
                throw new Exception('The confirmed student state could not be unlocked.');
            }
            $pdo->commit();
            header('Location: marks.php?batch_id=' . $returnBatchId);
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Scrutiny unlock database error: ' . $e->getMessage());
            $errorMessage = 'Unable to unlock student review.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Scrutiny unlock error: ' . $e->getMessage());
            $errorMessage = $e->getMessage();
        }
    }

    if ($action === 'confirm_student' && $studentId > 0 && $returnBatchId > 0) {
        try {
            $pdo->beginTransaction();
            $reviewStmt = $pdo->prepare("SELECT fme.entry_id, fme.student_id, fme.paper_id, fme.subject_id, fme.assessment_type_id, fme.marks_obtained, fme.is_absent, fme.verification_status, fme.verification_notified_at, COALESCE((SELECT sc.case_id FROM scrutiny_cases sc WHERE sc.student_id = fme.student_id AND sc.subject_id = fme.subject_id AND COALESCE(sc.paper_id, 0) = COALESCE(fme.paper_id, 0) ORDER BY sc.case_id DESC LIMIT 1), 0) AS scrutiny_case_id, COALESCE((SELECT sc.status FROM scrutiny_cases sc WHERE sc.student_id = fme.student_id AND sc.subject_id = fme.subject_id AND COALESCE(sc.paper_id, 0) = COALESCE(fme.paper_id, 0) ORDER BY sc.case_id DESC LIMIT 1), '') AS scrutiny_case_status FROM faculty_marks_entry fme INNER JOIN students st ON st.student_id = fme.student_id INNER JOIN paper_subject_mapping psm ON psm.batch_id = st.batch_id AND psm.paper_id = fme.paper_id AND psm.subject_id = fme.subject_id INNER JOIN subjects sub ON sub.subject_id = fme.subject_id LEFT JOIN elective_subject_mapping esm ON esm.subject_id = fme.subject_id AND LOWER(TRIM(esm.group_code)) = LOWER(TRIM(st.group_code)) WHERE fme.student_id = :student_id AND st.batch_id = :batch_id AND fme.assessment_type_id IN (1, 2, 3) AND (fme.marks_obtained IS NOT NULL OR fme.is_absent = 1) AND (COALESCE(sub.is_elective, 0) = 0 OR esm.subject_id IS NOT NULL OR NOT EXISTS (SELECT 1 FROM elective_subject_mapping esm_any WHERE esm_any.subject_id = fme.subject_id)) ORDER BY fme.subject_id ASC, fme.paper_id ASC, fme.assessment_type_id ASC");
            $reviewStmt->execute([':student_id' => $studentId, ':batch_id' => $returnBatchId]);
            $reviewRows = $reviewStmt->fetchAll(PDO::FETCH_ASSOC);
            $subjectStates = [];
            foreach ($reviewRows as $row) {
                $paperIdForRow = (int)($row['paper_id'] ?? 0);
                $subjectIdForRow = (int)($row['subject_id'] ?? 0);
                $subjectKey = $paperIdForRow . ':' . $subjectIdForRow;
                if (!isset($subjectStates[$subjectKey])) {
                    $subjectStates[$subjectKey] = [];
                }
                $subjectStates[$subjectKey][] = $row;
            }

            $subjectReviewSummary = [];
            foreach ($subjectStates as $key => $entries) {
                $subjectReviewSummary[$key] = summarizeSubjectReviewState($entries);
            }

            $blockingSubjects = [];
            $unreviewedSubjects = 0;
            $wrongSubjects = 0;
            foreach ($subjectReviewSummary as $key => $summary) {
                if ($summary['state'] === 'unreviewed') {
                    $unreviewedSubjects++;
                    $blockingSubjects[] = $key;
                } elseif ($summary['state'] === 'wrong' || $summary['state'] === 'awaiting_admin' || $summary['state'] === 'awaiting_recheck') {
                    $wrongSubjects++;
                    $blockingSubjects[] = $key;
                }
            }

            $activeCaseStmt = $pdo->prepare("SELECT COUNT(*) FROM scrutiny_cases sc INNER JOIN students s ON s.student_id = sc.student_id WHERE sc.student_id = :student_id AND s.batch_id = :batch_id AND sc.status IN ('awaiting_admin', 'awaiting_recheck')");
            $activeCaseStmt->execute([':student_id' => $studentId, ':batch_id' => $returnBatchId]);
            $activeCaseCount = (int)$activeCaseStmt->fetchColumn();

            $subjectDiagnostic = [];
            foreach ($reviewRows as $row) {
                $subjectDiagnostic[] = [
                    'student_id' => (int)($row['student_id'] ?? 0),
                    'entry_id' => (int)($row['entry_id'] ?? 0),
                    'paper_id' => (int)($row['paper_id'] ?? 0),
                    'subject_id' => (int)($row['subject_id'] ?? 0),
                    'assessment_type_id' => (int)($row['assessment_type_id'] ?? 0),
                    'verification_status' => $row['verification_status'] ?? null,
                    'scrutiny_case_id' => (int)($row['scrutiny_case_id'] ?? 0),
                    'scrutiny_case_status' => $row['scrutiny_case_status'] ?? null,
                    'verification_notified_at' => $row['verification_notified_at'] ?? null,
                ];
            }
            error_log('Scrutiny final confirm audit: ' . json_encode([
                'student_id' => $studentId,
                'batch_id' => $returnBatchId,
                'applicable_rows' => count($reviewRows),
                'applicable_subjects' => count($subjectStates),
                'unreviewed_subjects' => $unreviewedSubjects,
                'wrong_subjects' => $wrongSubjects,
                'active_scrutiny_case_count' => $activeCaseCount,
                'subject_states' => $subjectReviewSummary,
                'diagnostic_rows' => $subjectDiagnostic,
            ], JSON_UNESCAPED_SLASHES));

            if ($activeCaseCount > 0) {
                $caseLogStmt = $pdo->prepare('SELECT case_id, status, subject_id, paper_id, entry_id FROM scrutiny_cases WHERE student_id = :student_id ORDER BY case_id ASC');
                $caseLogStmt->execute([':student_id' => $studentId]);
                error_log('Scrutiny confirmation blocked by active cases: ' . json_encode(['student_id' => $studentId, 'batch_id' => $returnBatchId, 'ACTIVE_SCRUTINY_CASE_COUNT' => $activeCaseCount, 'cases' => $caseLogStmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_SLASHES));
                throw new Exception('This student has an active scrutiny case awaiting Admin correction or recheck.');
            }

            if (count($subjectStates) === 0) {
                throw new Exception('No applicable marks were found for this student.');
            }

            if ($unreviewedSubjects > 0) {
                throw new Exception($unreviewedSubjects . ' subject' . ($unreviewedSubjects === 1 ? '' : 's') . ' still need review before this student can be confirmed.');
            }

            if ($wrongSubjects > 0) {
                throw new Exception($wrongSubjects . ' subject' . ($wrongSubjects === 1 ? '' : 's') . ' are marked Wrong. Notify Admin before final confirmation.');
            }

            $stmt = $pdo->prepare('UPDATE students SET verification_status = :status, scrutiny_remarks = :scrutiny_remarks WHERE student_id = :student_id AND batch_id = :batch_id');
            $stmt->execute([
                ':status' => 'correct',
                ':scrutiny_remarks' => $scrutinyReason !== '' ? $scrutinyReason : null,
                ':student_id' => $studentId,
                ':batch_id' => $returnBatchId
            ]);
            $pdo->commit();
            if ($isAjaxRequest) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['success' => true, 'action' => $action, 'confirmed' => true]);
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('Scrutiny student confirmation database error: ' . $e->getMessage());
            $errorMessage = 'Unable to confirm student verification.';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errorMessage = $e->getMessage();
        }
    }

    if ($studentId > 0 && $entryId > 0 && $subjectId > 0 && $paperId > 0 && in_array($action, ['set_entry_status', 'notify_entry', 'verify_recheck'], true) && ($action === 'verify_recheck' || in_array($status, ['correct', 'wrong'], true))) {
        try {
            $stmt = $pdo->prepare('SELECT verification_status FROM students WHERE student_id = :student_id AND batch_id = :batch_id');
            $stmt->execute([
                ':student_id' => $studentId,
                ':batch_id' => $returnBatchId
            ]);
            if ($stmt->fetchColumn() === 'correct') {
                throw new Exception('This student has already been confirmed and cannot be changed.');
            }

            $stmt = $pdo->prepare('SELECT fme.entry_id FROM faculty_marks_entry fme INNER JOIN students s ON s.student_id = fme.student_id WHERE fme.student_id = :student_id AND fme.subject_id = :subject_id AND COALESCE(fme.paper_id, 0) = :paper_id AND s.batch_id = :batch_id ORDER BY fme.assessment_type_id ASC');
            $stmt->execute([
                ':student_id' => $studentId,
                ':subject_id' => $subjectId,
                ':paper_id' => $paperId,
                ':batch_id' => $returnBatchId
            ]);
            $subjectEntryIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            if (!in_array($entryId, $subjectEntryIds, true)) {
                throw new Exception('Selected subject mark was not found.');
            }

            if ($action === 'verify_recheck') {
                $pdo->beginTransaction();
                $caseStmt = $pdo->prepare("SELECT case_id FROM scrutiny_cases WHERE student_id = :student_id AND subject_id = :subject_id AND COALESCE(paper_id, 0) = :paper_id AND status = 'awaiting_recheck' ORDER BY case_id DESC LIMIT 1 FOR UPDATE");
                $caseStmt->execute([':student_id' => $studentId, ':subject_id' => $subjectId, ':paper_id' => $paperId]);
                $caseId = (int)$caseStmt->fetchColumn();
                if ($caseId <= 0) throw new Exception('This scrutiny case is no longer awaiting re-check.');
                $changedStmt = $pdo->prepare('SELECT DISTINCT entry_id FROM scrutiny_mark_change_log WHERE case_id = ?');
                $changedStmt->execute([$caseId]);
                $changedEntryIds = array_map('intval', $changedStmt->fetchAll(PDO::FETCH_COLUMN));
                if (!$changedEntryIds) throw new Exception('No corrected assessment entries were found for this re-check.');
                $entryPlaceholders = implode(',', array_fill(0, count($subjectEntryIds), '?'));
                $stmt = $pdo->prepare("UPDATE faculty_marks_entry SET verification_status = 'correct', verification_notified_at = NULL WHERE entry_id IN ($entryPlaceholders)");
                $stmt->execute($subjectEntryIds);
                $pdo->prepare('UPDATE scrutiny_cases SET status = \'verified\' WHERE case_id = ?')->execute([$caseId]);
                $pdo->prepare("UPDATE scrutiny_notifications SET status = 'verified', is_read = 1 WHERE case_id = ? AND status = 'awaiting_recheck'")->execute([$caseId]);
                $pdo->commit();
            } elseif ($action === 'set_entry_status') {
                $entryPlaceholders = implode(',', array_fill(0, count($subjectEntryIds), '?'));
                $stmt = $pdo->prepare("UPDATE faculty_marks_entry SET verification_status = ?, verification_notified_at = NULL WHERE entry_id IN ($entryPlaceholders)");
                $stmt->execute(array_merge([$status], $subjectEntryIds));
            } else {
                $pdo->beginTransaction();
                $activeCaseStmt = $pdo->prepare("SELECT case_id FROM scrutiny_cases WHERE student_id = :student_id AND subject_id = :subject_id AND COALESCE(paper_id, 0) = :paper_id AND status IN ('awaiting_admin', 'awaiting_recheck') ORDER BY case_id DESC LIMIT 1 FOR UPDATE");
                $activeCaseStmt->execute([':student_id' => $studentId, ':subject_id' => $subjectId, ':paper_id' => $paperId]);
                if ((int)$activeCaseStmt->fetchColumn() > 0) throw new Exception('This subject already has an active scrutiny case.');
                $entryPlaceholders = implode(',', array_fill(0, count($subjectEntryIds), '?'));
                $stmt = $pdo->prepare("UPDATE faculty_marks_entry SET verification_notified_at = NOW() WHERE entry_id IN ($entryPlaceholders) AND verification_status = ?");
                $stmt->execute(array_merge($subjectEntryIds, [$status]));
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Select Correct or Wrong before notifying Admin.');
                }
                $stmt = $pdo->prepare("INSERT INTO admin_notifications (student_id, batch_id, entry_id, verification_status, message) VALUES (:student_id, :batch_id, :entry_id, :status, :message)");
                $stmt->execute([
                    ':student_id' => $studentId,
                    ':batch_id' => $returnBatchId,
                    ':entry_id' => $subjectEntryIds[0],
                    ':status' => $status,
                    ':message' => 'Verification completed for subject ' . $subjectId . ': ' . ucfirst($status) . '.'
                ]);
                $stmt = $pdo->prepare("INSERT INTO scrutiny_cases (student_id, subject_id, paper_id, entry_id, flagged_by_user_id, reason) VALUES (:student_id, :subject_id, :paper_id, :entry_id, :user_id, :reason)");
                $stmt->execute([
                    ':student_id' => $studentId,
                    ':subject_id' => $subjectId,
                    ':paper_id' => $paperId ?: null,
                    ':entry_id' => $subjectEntryIds[0],
                    ':user_id' => (int)($currentUser['user_id'] ?? 0),
                    ':reason' => $scrutinyReason !== '' ? $scrutinyReason : 'Incorrect marks identified during scrutiny.'
                ]);
                $pdo->commit();
            }
            if ($isAjaxRequest) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success' => true,
                    'action' => $action,
                    'status' => $status,
                    'notified' => $action === 'notify_entry'
                ]);
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Scrutiny verification database error: ' . $e->getMessage());
            $errorMessage = 'Unable to save verification status.';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessage = $e->getMessage();
        }
    }

    if ($studentId > 0 && $entryId > 0 && $subjectId > 0 && $paperId > 0 && $action === 'reopen_scrutiny') {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('SELECT fme.entry_id FROM faculty_marks_entry fme INNER JOIN students s ON s.student_id = fme.student_id WHERE fme.student_id = :student_id AND fme.subject_id = :subject_id AND COALESCE(fme.paper_id, 0) = :paper_id AND s.batch_id = :batch_id ORDER BY fme.assessment_type_id ASC');
            $stmt->execute([
                ':student_id' => $studentId,
                ':subject_id' => $subjectId,
                ':paper_id' => $paperId,
                ':batch_id' => $returnBatchId
            ]);
            $subjectEntryIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
            if (!in_array($entryId, $subjectEntryIds, true) || !$subjectEntryIds) {
                throw new Exception('Selected subject mark was not found.');
            }

            $placeholders = implode(',', array_fill(0, count($subjectEntryIds), '?'));
            $stmt = $pdo->prepare("UPDATE faculty_marks_entry SET verification_status = 'wrong', verification_notified_at = NOW() WHERE entry_id IN ($placeholders)");
            $stmt->execute($subjectEntryIds);

            $stmt = $pdo->prepare("INSERT INTO admin_notifications (student_id, batch_id, entry_id, verification_status, message) VALUES (:student_id, :batch_id, :entry_id, 'wrong', :message)");
            $stmt->execute([
                ':student_id' => $studentId,
                ':batch_id' => $returnBatchId,
                ':entry_id' => $subjectEntryIds[0],
                ':message' => 'Verification re-check found the subject still incorrect. Admin correction is required.'
            ]);
            $stmt = $pdo->prepare("UPDATE scrutiny_cases SET status = 'awaiting_admin', entry_id = :entry_id, flagged_by_user_id = :user_id, reason = :reason WHERE case_id = (SELECT case_id FROM (SELECT case_id FROM scrutiny_cases WHERE student_id = :student_id AND subject_id = :subject_id AND COALESCE(paper_id, 0) = :paper_id AND status IN ('verified', 'awaiting_recheck') ORDER BY case_id DESC LIMIT 1) AS latest_case)");
            $stmt->execute([
                ':entry_id' => $subjectEntryIds[0],
                ':user_id' => (int)($currentUser['user_id'] ?? 0),
                ':reason' => 'Verification re-check found the corrected subject marks are still not correct.',
                ':student_id' => $studentId,
                ':subject_id' => $subjectId,
                ':paper_id' => $paperId
            ]);
            if ($stmt->rowCount() !== 1) {
                throw new Exception('The existing scrutiny case could not be reopened.');
            }
            $pdo->commit();

            if ($isAjaxRequest) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['success' => true, 'action' => $action, 'reopened' => true]);
                exit;
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessage = 'Unable to notify Admin for another correction.';
        }
    }

    if ($isAjaxRequest) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => false, 'message' => $errorMessage ?: 'Invalid verification request.']);
        exit;
    }
}

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

function summarizeSubjectReviewState(array $entries): array
{
    $eligible = [];
    foreach ($entries as $entry) {
        if (($entry['marks_obtained'] ?? null) !== null || (int)($entry['is_absent'] ?? 0) === 1) {
            $eligible[] = $entry;
        }
    }

    if (empty($eligible)) {
        return ['state' => 'unreviewed', 'eligible_count' => 0];
    }

    $correctCount = 0;
    $wrongCount = 0;
    foreach ($eligible as $entry) {
        $status = (string)($entry['verification_status'] ?? '');
        if ($status === 'correct') {
            $correctCount++;
        }
        if ($status === 'wrong') {
            $wrongCount++;
        }
    }

    if ($wrongCount > 0) {
        return ['state' => 'wrong', 'eligible_count' => count($eligible), 'wrong_count' => $wrongCount, 'correct_count' => $correctCount];
    }

    if ($correctCount === count($eligible)) {
        return ['state' => 'correct', 'eligible_count' => count($eligible), 'wrong_count' => 0, 'correct_count' => $correctCount];
    }

    return ['state' => 'unreviewed', 'eligible_count' => count($eligible), 'wrong_count' => 0, 'correct_count' => $correctCount];
}

function subjectReviewControls(array $entries, int $studentId, int $batchId, int $paperId, int $subjectId, bool $locked = false): string
{
    if (empty($entries)) {
        return '';
    }

    foreach ($entries as $entry) {
        if ((int)($entry['active_case_count'] ?? 0) > 0) {
            if (($entry['active_case_status'] ?? '') !== 'awaiting_recheck') {
                return '<span class="no-print verification-status" style="display:block;color:#b02a37;font-size:10px;font-weight:bold;">Admin notified / Recheck required</span>';
            }

            $entryId = (int)($entry['entry_id'] ?? 0);
            return '<form method="POST" class="no-print subject-review-form" style="display:flex;gap:2px;justify-content:center;margin-top:2px;">'
                . csrfField()
                . '<input type="hidden" name="batch_id" value="' . $batchId . '">'
                . '<input type="hidden" name="student_id" value="' . $studentId . '">'
                . '<input type="hidden" name="entry_id" value="' . $entryId . '">'
                . '<input type="hidden" name="paper_id" value="' . $paperId . '">'
                . '<input type="hidden" name="subject_id" value="' . $subjectId . '">'
                . '<input type="hidden" name="verification_status" value="correct">'
                . '<input type="hidden" name="verification_action" value="verify_recheck">'
                . '<input type="hidden" name="ajax" value="1">'
                . '<input type="hidden" name="scrutiny_reason" value="">'
                . '<button type="submit" name="verification_action" value="verify_recheck" title="Confirm corrected mark">&#10003;</button>'
                . '<button type="submit" name="verification_action" value="reopen_scrutiny" title="Notify Admin that the mark is still wrong">&#10007;</button>'
                . '</form>'
                . '<span class="no-print verification-status" style="display:block;color:#1f4e79;font-size:10px;">Awaiting Re-check</span>';
        }
    }

    if ($locked) {
        return '<span class="no-print verification-status" style="display:block;color:#1f4b73;font-size:10px;">Confirmed</span>';
    }

    $summary = summarizeSubjectReviewState($entries);
    $status = $summary['state'];
    if ($status === 'correct') {
        return '<span class="no-print verification-status" style="display:block;color:#087f3e;font-size:10px;">Correct</span>';
    }

    if ($status === 'wrong') {
        $firstEntry = reset($entries);
        $entryId = (int)($firstEntry['entry_id'] ?? 0);
        $controls = '<form method="POST" class="no-print subject-review-form" style="display:flex;gap:2px;justify-content:center;margin-top:2px;">'
            . csrfField()
            . '<input type="hidden" name="batch_id" value="' . $batchId . '">'
            . '<input type="hidden" name="student_id" value="' . $studentId . '">'
            . '<input type="hidden" name="entry_id" value="' . $entryId . '">'
            . '<input type="hidden" name="paper_id" value="' . $paperId . '">'
            . '<input type="hidden" name="subject_id" value="' . $subjectId . '">'
            . '<input type="hidden" name="verification_status" value="wrong">'
            . '<input type="hidden" name="verification_action" value="notify_entry">'
            . '<input type="hidden" name="ajax" value="1">'
            . '<input type="hidden" name="scrutiny_reason" value="">'
            . '<button type="submit" title="Notify admin about this subject mark">Notify Admin</button>'
            . '</form>'
            . '<span class="no-print verification-status" style="display:block;color:#b02a37;font-size:10px;">Wrong</span>';
        return $controls;
    }

    if ($status === 'awaiting_admin') {
        return '<span class="no-print" style="display:block;color:#1f4e79;font-size:10px;">Admin notified</span>';
    }

    if ($status === 'unreviewed') {
        $firstEntry = reset($entries);
        $entryId = (int)($firstEntry['entry_id'] ?? 0);
        $controls = '<form method="POST" class="no-print subject-review-form" style="display:flex;gap:2px;justify-content:center;margin-top:2px;">'
            . csrfField()
            . '<input type="hidden" name="batch_id" value="' . $batchId . '">'
            . '<input type="hidden" name="student_id" value="' . $studentId . '">'
            . '<input type="hidden" name="entry_id" value="' . $entryId . '">'
            . '<input type="hidden" name="paper_id" value="' . $paperId . '">'
            . '<input type="hidden" name="subject_id" value="' . $subjectId . '">'
            . '<input type="hidden" name="verification_status" value="">'
            . '<input type="hidden" name="verification_action" value="set_entry_status">'
            . '<input type="hidden" name="ajax" value="1">'
            . '<input type="hidden" name="scrutiny_reason" value="">'
            . '<button type="submit" name="verification_action" value="set_entry_status" title="Mark this subject correct" onclick="this.form.verification_status.value=\'correct\';">&#10003;</button>'
            . '<button type="submit" name="verification_action" value="set_entry_status" title="Mark this subject wrong" onclick="this.form.verification_status.value=\'wrong\';">&#10007;</button>'
            . '</form>';
        return $controls;
    }

    return '';
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
            station,
            status
        FROM batches
        WHERE status != 'Completed'
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
                group_code,
                scrutiny_remarks,
                verification_status,
                verification_notified_at
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
                 MAX(fme.entry_id) AS entry_id,
                fme.student_id,
                fme.subject_id,
                COALESCE(fme.paper_id, 0) AS paper_id,
                fme.assessment_type_id,
                MAX(fme.marks_obtained) AS marks_obtained,
                MAX(fme.is_absent) AS is_absent,
                 MAX(fme.verification_status) AS verification_status,
                                 MAX(fme.verification_notified_at) AS verification_notified_at,
                                 (
                                        SELECT COUNT(*)
                                        FROM scrutiny_cases active_sc
                                        WHERE active_sc.student_id = fme.student_id
                                          AND active_sc.subject_id = fme.subject_id
                                          AND COALESCE(active_sc.paper_id, 0) = COALESCE(fme.paper_id, 0)
                                          AND active_sc.status IN ('awaiting_admin', 'awaiting_recheck')
                                                                 ) AS active_case_count,
                                COALESCE((
                                        SELECT sc.status
                                        FROM scrutiny_cases sc
                                        WHERE sc.student_id = fme.student_id
                                            AND sc.subject_id = fme.subject_id
                                            AND COALESCE(sc.paper_id, 0) = COALESCE(fme.paper_id, 0)
                                            AND sc.status IN ('awaiting_admin', 'awaiting_recheck')
                                        ORDER BY sc.case_id DESC
                                        LIMIT 1
                                ), '') AS active_case_status,
                COALESCE(
                    (
                        SELECT CEIL(scl.new_marks)
                        FROM scrutiny_mark_change_log scl
                        INNER JOIN faculty_marks_entry corrected_fme
                            ON corrected_fme.entry_id = scl.entry_id
                        WHERE scl.student_id = fme.student_id
                          AND scl.subject_id = fme.subject_id
                          AND COALESCE(corrected_fme.paper_id, 0) = COALESCE(MAX(fme.paper_id), 0)
                          AND corrected_fme.assessment_type_id = fme.assessment_type_id
                        ORDER BY scl.changed_at DESC, scl.change_id DESC
                        LIMIT 1
                    ),
                    CASE
                        WHEN COUNT(CASE WHEN fme.is_absent = 0 AND fme.marks_obtained IS NOT NULL THEN 1 END) > 0
                        THEN CEIL(AVG(CASE WHEN fme.is_absent = 0 THEN CEIL(fme.marks_obtained) ELSE NULL END))
                        ELSE 0
                    END
                ) AS score
            FROM faculty_marks_entry fme
            INNER JOIN students st
                ON st.student_id = fme.student_id
            INNER JOIN paper_subject_mapping psm
                ON psm.batch_id = st.batch_id
               AND psm.paper_id = fme.paper_id
               AND psm.subject_id = fme.subject_id
            INNER JOIN subjects sub
                ON sub.subject_id = fme.subject_id
            LEFT JOIN elective_subject_mapping esm
                ON esm.subject_id = fme.subject_id
               AND LOWER(TRIM(esm.group_code)) = LOWER(TRIM(st.group_code))
            WHERE st.batch_id = :batch_id
              AND (st.is_deleted = 0 OR st.is_deleted IS NULL)
              AND fme.assessment_type_id IN (1, 2, 3)
              AND (fme.marks_obtained IS NOT NULL OR fme.is_absent = 1)
              AND (COALESCE(sub.is_elective, 0) = 0 OR esm.subject_id IS NOT NULL OR NOT EXISTS (SELECT 1 FROM elective_subject_mapping esm_any WHERE esm_any.subject_id = fme.subject_id))
            GROUP BY fme.student_id, fme.subject_id, fme.paper_id, COALESCE(fme.paper_id, 0), fme.assessment_type_id
        ");
        $stmt->execute([":batch_id" => $selectedBatchId]);
        $scoreRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($scoreRows as $sr) {
            $stId = (int)$sr['student_id'];
            $subId = (int)$sr['subject_id'];
            $pId = (int)($sr['paper_id'] ?? 0);
            $aId = (int)$sr['assessment_type_id'];
            $studentSubjectMarks[$stId][$pId][$subId][$aId] = $sr['score'];
                $studentMarkEntries[$stId][$pId][$subId][$aId] = $sr;
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
        // v_result_register
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Marks Review - Scrutiny Officer</title>
    <style>
    .marks-page {
        background: #f4f6f8;
        min-height: 100vh;
        min-width: 0;
        overflow-x: hidden;
        padding: 30px;
    }

    .readonly-box {
        background: #eef5fa;
        border-left: 4px solid #1f4e79;
        padding: 14px 18px;
        margin-top: 15px;
        border-radius: 4px;
        font-size: 13px;
        color: #1f4e79;
    }

    .readonly-box strong {
        display: block;
        font-size: 14px;
        margin-bottom: 3px;
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

    .print-btn {
        background: #555555;
        color: #ffffff;
    }

    .print-btn:hover {
        background: #333333;
    }

    .table-wrapper {
        margin-top: 25px;
        background: #ffffff;
        border: 1px solid #777777;
        overflow: auto;
        max-height: calc(100vh - 300px);
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

    .student-tools {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        box-sizing: border-box;
        width: 100%;
        max-width: 100%;
        margin-top: 16px;
        padding: 12px 14px;
        background: #ffffff;
        border: 1px solid #d9d9d9;
        border-radius: 6px;
    }

    .student-search {
        flex: 1 1 260px;
        min-width: 0;
        height: 36px;
        box-sizing: border-box;
        padding: 0 11px;
        border: 1px solid #bfc8d0;
        border-radius: 4px;
        font-size: 13px;
    }

    .student-tool-btn {
        height: 36px;
        padding: 0 13px;
        border: 1px solid #1f4e79;
        border-radius: 4px;
        background: #ffffff;
        color: #1f4e79;
        font-size: 12px;
        font-weight: bold;
        cursor: pointer;
    }

    .student-tool-btn.active,
    .student-tool-btn:hover {
        background: #1f4e79;
        color: #ffffff;
    }

    .student-focus-label {
        color: #555555;
        font-size: 12px;
        font-weight: bold;
    }

    .tabulation-table tbody tr.student-selected td {
        background: #fff4cc !important;
        box-shadow: inset 0 2px 0 #d49b00, inset 0 -2px 0 #d49b00;
    }

    .tabulation-table tbody tr.student-hidden {
        display: none;
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
        min-width: 90px;
    }

    .action-btn {
        display: inline-block;
        padding: 5px 10px;
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

    .empty {
        text-align: center;
        padding: 50px 20px;
        background: #ffffff;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        margin-top: 25px;
    }

    @media print {
        .no-print {
            display: none !important;
        }
    }
    </style>
</head>
<body style="margin:0; font-family:Arial, sans-serif;">

<div style="display:flex; flex-direction:column; min-height:100vh;">

    <?php require_once __DIR__ . "/../includes/navbar.php"; ?>

    <div style="display:flex; flex:1;">

        <div class="no-print">
            <?php require_once __DIR__ . "/../includes/verification_sidebar.php"; ?>
        </div>

        <main class="marks-page" style="flex:1;">

            <div style="max-width:100%;">

                <div class="no-print" style="margin-bottom:10px;">
                    <h1 style="margin:0; font-size:26px; color:#1f4e79;">Marks Review (Scrutiny Panel)</h1>
                    <p style="margin:5px 0 0; color:#666666;">Comprehensive marks tabulation review for scrutiny officers.</p>
                </div>

                <!-- READ ONLY NOTICE -->
                <div class="readonly-box no-print">
                    <strong>🔒 Read-Only Scrutiny Mode</strong>
                    <span>Marks displayed in this table are for scrutiny and review only. Editing is not permitted from this panel.</span>
                </div>

                <?php if (!empty($errorMessage)): ?>
                    <div style="padding:15px; background:#f8d7da; color:#721c24; border-radius:4px; margin-top:20px;">
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

                            <!-- PRINT BUTTON -->
                            <button type="button" class="btn print-btn" onclick="window.print()">Print</button>

                        </div>
                    </form>
                </div>

                <!-- BATCH DETAILS BANNER -->
                <?php if ($currentBatch !== null): ?>
                    <div style="background:#ffffff; border:1px solid #dddddd; padding:15px 18px; margin-top:20px; border-radius:6px;">
                        <strong>FTC CLASS MARKS SCRUTINY REGISTER</strong>
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
                        <p>No paper mapping configured for this batch.</p>
                    </div>
                <?php else: ?>

                    <div class="student-tools no-print">
                        <input type="search" id="student-search" class="student-search" placeholder="Search student name or roll number..." aria-label="Search student name or roll number">
                        <button type="button" class="student-tool-btn" id="focus-student-btn">Focus Selected Student</button>
                        <button type="button" class="student-tool-btn" id="show-all-students-btn">Show All Students</button>
                        <span class="student-focus-label" id="student-focus-label">Select a student row to focus</span>
                    </div>

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
                                    <th rowspan="3" class="result-header no-print">Remark</th>
                                    <th rowspan="3" class="result-header no-print">Confirm</th>
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
                                        $isStudentVerified = ($student['verification_status'] ?? '') === 'correct';

                                        $hasPendingNotifiedChanges = false;
                                        $hasActiveScrutinyCase = false;
                                        $applicableMarkCount = 0;
                                        $correctMarkCount = 0;
                                        if (isset($studentMarkEntries[$studentId])) {
                                            foreach ($studentMarkEntries[$studentId] as $paperEntries) {
                                                foreach ($paperEntries as $subEntries) {
                                                    foreach ($subEntries as $entry) {
                                                        if (($entry['marks_obtained'] ?? null) !== null || (int)($entry['is_absent'] ?? 0) === 1) {
                                                            $applicableMarkCount++;
                                                            if (($entry['verification_status'] ?? '') === 'correct') {
                                                                $correctMarkCount++;
                                                            }
                                                        }
                                                        if (($entry['verification_status'] ?? '') === 'wrong' || (int)($entry['active_case_count'] ?? 0) > 0) {
                                                            $hasPendingNotifiedChanges = true;
                                                        }
                                                        if ((int)($entry['active_case_count'] ?? 0) > 0) {
                                                            $hasActiveScrutinyCase = true;
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                        $allApplicableMarksReviewed = $applicableMarkCount > 0 && $correctMarkCount === $applicableMarkCount && !$hasPendingNotifiedChanges;
                                    ?>
                                    <tr data-student-name="<?php echo h(strtolower((string)$student['student_name'])); ?>" data-student-roll="<?php echo h(strtolower((string)$student['roll_no'])); ?>">
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
                                                    <?php $subjectEntries = $studentMarkEntries[$studentId][$pId][$subId] ?? []; ?>
                                                    <td class="mark-cell"><?php echo displayMark($intMark ?? null); ?></td>
                                                    <td class="mark-cell"><?php echo displayMark($midMark ?? null); ?></td>
                                                    <td class="mark-cell"><?php echo displayMark($finMark ?? null); ?></td>
                                                    <td class="total-cell"><?php echo displayMark($subTotal !== null ? number_format($subTotal, 2) : null); ?><?php echo subjectReviewControls($subjectEntries, $studentId, $selectedBatchId, $pId, $subId, $isStudentVerified); ?></td>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>

                                        <?php
                                            // Results & Percentage — sourced from v_result_register (DB view)
                                            $resultInfo = $paperResults[$studentId] ?? [];
                                            $grandTotal = isset($resultInfo["grand_total"]) ? (float)$resultInfo["grand_total"] : null;
                                            $percentage  = isset($resultInfo["percentage"])  ? (float)$resultInfo["percentage"]  : null;
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

                                        <!-- REMARK COLUMN -->
                                        <td class="no-print" style="white-space:nowrap; text-align:center; padding:6px;">
                                            <input 
                                                type="text" 
                                                id="scrutiny-reason-<?php echo $studentId; ?>" 
                                                class="scrutiny-remark-input" 
                                                placeholder="Enter remark..." 
                                                value="<?php echo h($student['scrutiny_remarks'] ?? ''); ?>"
                                                <?php echo $isStudentVerified ? 'disabled' : ''; ?> 
                                                style="width:160px; padding:6px 8px; font-size:12px; border:1px solid #ccc; border-radius:4px;"
                                            >
                                        </td>

                                        <!-- CONFIRM COLUMN -->
                                        <td class="no-print" style="white-space:nowrap; text-align:center; padding:6px;">
                                            <?php if ($isStudentVerified): ?>
                                                <span class="student-confirmed" style="display:inline-block; padding:5px 12px; background:#e8f4ec; color:#087f3e; font-size:12px; font-weight:bold; border-radius:4px;">Confirmed</span>
                                                <form method="POST" class="unlock-review-form" style="margin-top:6px;" onsubmit="return confirm('Unlock this student for scrutiny review again?');">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                                                    <input type="hidden" name="batch_id" value="<?php echo $selectedBatchId; ?>">
                                                    <input type="hidden" name="verification_action" value="unlock_review">
                                                    <button type="submit" style="padding:4px 9px; font-size:11px; border:1px solid #b02a37; border-radius:4px; background:#fff; color:#b02a37; cursor:pointer;">Unlock Review</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="no-print confirm-student-form" style="margin:0;">
                                                    <?php echo csrfField(); ?>
                                                    <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                                                    <input type="hidden" name="batch_id" value="<?php echo $selectedBatchId; ?>">
                                                    <input type="hidden" name="verification_action" value="confirm_student">
                                                    <input type="hidden" name="ajax" value="1">
                                                    <button 
                                                        type="submit" 
                                                        class="confirm-student-btn"
                                                        <?php echo !$allApplicableMarksReviewed ? 'disabled title="Review every applicable mark as Correct first"' : ''; ?>
                                                        style="padding:6px 14px; font-size:12px; font-weight:bold; border-radius:4px; border:none; cursor:<?php echo !$allApplicableMarksReviewed ? 'not-allowed' : 'pointer'; ?>; background:<?php echo !$allApplicableMarksReviewed ? '#cccccc' : '#1f4e79'; ?>; color:<?php echo !$allApplicableMarksReviewed ? '#666666' : '#ffffff'; ?>;"
                                                    >
                                                        <?php echo $allApplicableMarksReviewed ? 'Confirm' : 'Review All Marks'; ?>
                                                    </button>
                                                </form>
                                                <?php if ($hasPendingNotifiedChanges): ?>
                                                    <small class="pending-admin-hint" style="display:block; color:#d9534f; font-size:10px; margin-top:3px; font-weight:bold;">Awaiting Admin Changes</small>
                                                <?php endif; ?>
                                            <?php endif; ?>
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

    <?php require_once __DIR__ . "/../includes/footer.php"; ?>

</div>

</body>
<script>
    function updateStudentConfirmation(row) {
        var confirmButton = row.querySelector('.confirm-student-btn');
        if (!confirmButton) {
            return;
        }

        var hasNotifiedOrWrong = Array.from(row.querySelectorAll('.verification-status, small')).some(function (el) {
            var txt = el.textContent.trim();
            return txt === 'Wrong' || txt === 'Admin notified' || txt === 'Awaiting Re-check';
        });

        if (hasNotifiedOrWrong) {
            confirmButton.disabled = true;
            confirmButton.style.cursor = 'not-allowed';
            confirmButton.style.background = '#cccccc';
            confirmButton.style.color = '#666666';
            confirmButton.title = 'Disabled until admin makes the notified changes from admin panel';
            var hint = row.querySelector('.pending-admin-hint');
            if (!hint) {
                hint = document.createElement('small');
                hint.className = 'pending-admin-hint';
                hint.style.cssText = 'display:block; color:#d9534f; font-size:10px; margin-top:3px; font-weight:bold;';
                hint.textContent = 'Awaiting Admin Changes';
                confirmButton.after(hint);
            }
        } else {
            confirmButton.disabled = false;
            confirmButton.style.cursor = 'pointer';
            confirmButton.style.background = '#1f4e79';
            confirmButton.style.color = '#ffffff';
            confirmButton.title = 'Confirm student verification';
            var hint = row.querySelector('.pending-admin-hint');
            if (hint) {
                hint.remove();
            }
        }
    }

    var studentRows = Array.from(document.querySelectorAll('.tabulation-table tbody tr'));
    var selectedStudentRow = null;
    var focusMode = false;
    var searchInput = document.getElementById('student-search') || {value: ''};
    var focusStudentButton = document.getElementById('focus-student-btn');
    var showAllStudentsButton = document.getElementById('show-all-students-btn');
    var focusLabel = document.getElementById('student-focus-label') || {textContent: ''};

    function updateFocusLabel() {
        if (!selectedStudentRow) {
            focusLabel.textContent = 'Select a student row to focus';
            return;
        }
        var name = selectedStudentRow.querySelector('.student-info strong');
        var roll = selectedStudentRow.dataset.studentRoll || '';
        focusLabel.textContent = focusMode
            ? 'Reviewing: ' + (name ? name.textContent.trim() : roll)
            : 'Selected: ' + (name ? name.textContent.trim() : roll);
    }

    function applyStudentView() {
        var query = (searchInput.value || '').trim().toLowerCase();
        studentRows.forEach(function (row) {
            var matchesSearch = !query
                || (row.dataset.studentName || '').indexOf(query) !== -1
                || (row.dataset.studentRoll || '').indexOf(query) !== -1;
            var matchesFocus = !focusMode || row === selectedStudentRow;
            row.classList.toggle('student-hidden', !matchesSearch || !matchesFocus);
        });
    }

    studentRows.forEach(function (row) {
        updateStudentConfirmation(row);
        row.addEventListener('click', function (event) {
            if (event.target.closest('button, form, input, a')) {
                return;
            }
            if (selectedStudentRow) {
                selectedStudentRow.classList.remove('student-selected');
            }
            selectedStudentRow = row;
            selectedStudentRow.classList.add('student-selected');
            updateFocusLabel();
        });
    });

    if (searchInput.addEventListener) {
        searchInput.addEventListener('input', function () {
            applyStudentView();
        });
    }

    if (focusStudentButton) {
        focusStudentButton.addEventListener('click', function () {
        if (!selectedStudentRow) {
            var firstVisible = studentRows.find(function (row) {
                return !row.classList.contains('student-hidden');
            });
            if (firstVisible) {
                selectedStudentRow = firstVisible;
                selectedStudentRow.classList.add('student-selected');
            }
        }
        if (selectedStudentRow) {
            focusMode = true;
            focusStudentButton.classList.add('active');
            applyStudentView();
            updateFocusLabel();
        }
        });
    }

    if (showAllStudentsButton) {
        showAllStudentsButton.addEventListener('click', function () {
        focusMode = false;
        focusStudentButton.classList.remove('active');
        applyStudentView();
        updateFocusLabel();
        });
    }

    updateFocusLabel();

    document.addEventListener('submit', function (event) {
        var form = event.target.closest('form');
        if (!form || form.classList.contains('unlock-review-form') || !form.querySelector('input[name="verification_action"]')) {
            return;
        }

        event.preventDefault();
        var submitButton = event.submitter;
        if (submitButton && submitButton.name === 'verification_action') {
            form.querySelector('input[name="verification_action"]').value = submitButton.value;
        }

        var studentIdInput = form.querySelector('input[name="student_id"]');
        var scrutinyReasonInput = form.querySelector('input[name="scrutiny_reason"]');
        var studentRemark = studentIdInput
            ? document.getElementById('scrutiny-reason-' + studentIdInput.value)
            : null;
        if (scrutinyReasonInput) {
            scrutinyReasonInput.value = studentRemark ? studentRemark.value : '';
        }

        fetch(form.action || window.location.href, {
            method: 'POST',
            body: new FormData(form),
            headers: {'X-Requested-With': 'XMLHttpRequest'}
        })
            .then(function (response) { return response.json(); })
            .then(function (result) {
                if (!result.success) {
                    window.alert(result.message || 'Unable to save verification status.');
                    return;
                }

                if (result.confirmed) {
                    var confirmedRow = form.closest('tr');
                    confirmedRow.classList.add('student-confirmed-row');
                    confirmedRow.querySelectorAll('form.subject-review-form, input.scrutiny-remark-input, .verification-status, .pending-admin-hint').forEach(function (control) {
                        control.disabled = true;
                        if (control.tagName === 'FORM' || control.classList.contains('verification-status') || control.classList.contains('pending-admin-hint')) {
                            control.remove();
                        }
                    });
                    var confirmCell = confirmedRow.cells[confirmedRow.cells.length - 1];
                    confirmCell.innerHTML = '<span class="student-confirmed" style="display:inline-block; padding:5px 12px; background:#e8f4ec; color:#087f3e; font-size:12px; font-weight:bold; border-radius:4px;">Confirmed</span>';
                    return;
                }

                if (result.reopened) {
                    form.outerHTML = '<span class="no-print" style="display:block;color:#1f4b73;font-size:10px;">Admin notified</span>';
                    return;
                }

                if (result.notified) {
                    var existingStatusLabel = form.nextElementSibling;
                    if (existingStatusLabel && existingStatusLabel.classList.contains('verification-status')) {
                        existingStatusLabel.remove();
                    }
                    form.outerHTML = '<span class="no-print" style="display:block;color:#1f4e79;font-size:10px;">Admin notified</span>';
                    return;
                }

                form.querySelector('input[name="verification_status"]').value = result.status;
                var statusLabel = form.nextElementSibling;
                if (statusLabel && statusLabel.classList.contains('verification-status')) {
                    statusLabel.remove();
                }
                statusLabel = document.createElement('span');
                statusLabel.className = 'no-print verification-status';
                statusLabel.style.cssText = 'display:block;font-size:10px;color:' + (result.status === 'correct' ? '#087f3e' : '#b02a37') + ';';
                statusLabel.textContent = result.status === 'correct' ? 'Correct' : 'Wrong';
                form.after(statusLabel);

                var notifyButton = form.querySelector('button[value="notify_entry"]');
                if (result.status === 'wrong' && !notifyButton) {
                    notifyButton = document.createElement('button');
                    notifyButton.type = 'submit';
                    notifyButton.name = 'verification_action';
                    notifyButton.value = 'notify_entry';
                    notifyButton.title = 'Notify admin about this subject mark';
                    notifyButton.textContent = 'Notify Admin';
                    form.appendChild(notifyButton);
                } else if (result.status !== 'wrong' && notifyButton) {
                    notifyButton.remove();
                }
                updateStudentConfirmation(form.closest('tr'));
            })
            .catch(function () {
                window.alert('Unable to save verification status.');
            });
    });
</script>
</html>
