<?php

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../config/database.php';

$user = require_permission('verification.access');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}
enforceCsrf();

$notificationId = (int)($_POST['notification_id'] ?? 0);
$action = $_POST['action'] ?? 'read';
$redirectBatchId = 0;
try {
    if ($action === 'verified') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT sn.case_id, sn.student_id, s.batch_id, sc.subject_id, sc.paper_id, sc.entry_id FROM scrutiny_notifications sn INNER JOIN students s ON s.student_id = sn.student_id INNER JOIN scrutiny_cases sc ON sc.case_id = sn.case_id WHERE sn.notification_id = :id AND sn.recipient_user_id = :user_id AND sn.status = 'awaiting_recheck' AND sc.status = 'awaiting_recheck' FOR UPDATE");
        $stmt->execute([':id' => $notificationId, ':user_id' => $user['user_id']]);
        $notificationStudent = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$notificationStudent) throw new Exception('Notification is no longer awaiting re-check.');

        $stmt = $pdo->prepare("UPDATE scrutiny_notifications SET status = 'verified', is_read = 1, read_at = COALESCE(read_at, NOW()), verified_at = NOW() WHERE notification_id = :id AND recipient_user_id = :user_id AND status = 'awaiting_recheck'");
        $stmt->execute([':id' => $notificationId, ':user_id' => $user['user_id']]);
        if ($stmt->rowCount() !== 1) throw new Exception('Notification is no longer awaiting re-check.');
        $stmt = $pdo->prepare("UPDATE scrutiny_cases sc INNER JOIN scrutiny_notifications sn ON sn.case_id = sc.case_id SET sc.status = 'verified' WHERE sn.notification_id = :id AND sn.recipient_user_id = :user_id");
        $stmt->execute([':id' => $notificationId, ':user_id' => $user['user_id']]);
        $stmt = $pdo->prepare("UPDATE students SET verification_status = NULL WHERE student_id = :student_id AND batch_id = :batch_id");
        $stmt->execute([':student_id' => $notificationStudent['student_id'], ':batch_id' => $notificationStudent['batch_id']]);
        $stmt = $pdo->prepare("UPDATE faculty_marks_entry SET verification_status = 'correct', verification_notified_at = NULL WHERE student_id = :student_id AND subject_id = :subject_id AND COALESCE(paper_id, 0) = COALESCE(:paper_id_match, 0)");
        $stmt->execute([
            ':student_id' => $notificationStudent['student_id'],
            ':subject_id' => $notificationStudent['subject_id'],
            ':paper_id_match' => $notificationStudent['paper_id']
        ]);
        $stmt = $pdo->prepare("INSERT INTO admin_notifications (student_id, batch_id, entry_id, verification_status, message) VALUES (:student_id, :batch_id, :entry_id, 'correct', :message)");
        $stmt->execute([
            ':student_id' => $notificationStudent['student_id'],
            ':batch_id' => $notificationStudent['batch_id'],
            ':entry_id' => $notificationStudent['entry_id'],
            ':message' => 'Verification re-check completed for scrutiny case #' . $notificationStudent['case_id'] . '. Please review the updated marks.'
        ]);
        $pdo->commit();
        $redirectBatchId = (int)$notificationStudent['batch_id'];
    } elseif ($action === 'not_correct') {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT sn.case_id, sn.student_id, s.batch_id, sc.subject_id, sc.paper_id, sc.entry_id, sc.reason FROM scrutiny_notifications sn INNER JOIN students s ON s.student_id = sn.student_id INNER JOIN scrutiny_cases sc ON sc.case_id = sn.case_id WHERE sn.notification_id = :id AND sn.recipient_user_id = :user_id AND sn.status = 'awaiting_recheck' AND sc.status = 'awaiting_recheck' FOR UPDATE");
        $stmt->execute([':id' => $notificationId, ':user_id' => $user['user_id']]);
        $notificationStudent = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$notificationStudent) throw new Exception('This re-check is no longer awaiting a response.');

        $stmt = $pdo->prepare("UPDATE scrutiny_notifications SET status = 'verified', is_read = 1, read_at = COALESCE(read_at, NOW()), verified_at = COALESCE(verified_at, NOW()) WHERE notification_id = :id AND recipient_user_id = :user_id AND status = 'awaiting_recheck'");
        $stmt->execute([':id' => $notificationId, ':user_id' => $user['user_id']]);

        $stmt = $pdo->prepare('SELECT entry_id FROM faculty_marks_entry WHERE student_id = :student_id AND subject_id = :subject_id AND COALESCE(paper_id, 0) = COALESCE(:paper_id_match, 0) ORDER BY assessment_type_id ASC');
        $stmt->execute([
            ':student_id' => $notificationStudent['student_id'],
            ':subject_id' => $notificationStudent['subject_id'],
            ':paper_id_match' => $notificationStudent['paper_id']
        ]);
        $subjectEntryIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        if (!$subjectEntryIds) throw new Exception('No assessment marks were found for this subject.');

        $placeholders = implode(',', array_fill(0, count($subjectEntryIds), '?'));
        $stmt = $pdo->prepare("UPDATE faculty_marks_entry SET verification_status = 'wrong', verification_notified_at = NOW() WHERE entry_id IN ($placeholders)");
        $stmt->execute($subjectEntryIds);

        $stmt = $pdo->prepare("INSERT INTO admin_notifications (student_id, batch_id, entry_id, verification_status, message) VALUES (:student_id, :batch_id, :entry_id, 'wrong', :message)");
        $stmt->execute([
            ':student_id' => $notificationStudent['student_id'],
            ':batch_id' => $notificationStudent['batch_id'],
            ':entry_id' => $subjectEntryIds[0],
            ':message' => 'Verification re-check marked this subject as not correct. Admin correction is required again.'
        ]);
        $stmt = $pdo->prepare("UPDATE scrutiny_cases SET status = 'awaiting_admin', reason = :reason WHERE case_id = :case_id AND status = 'awaiting_recheck'");
        $stmt->execute([
            ':case_id' => $notificationStudent['case_id'],
            ':reason' => 'Verification re-check found the corrected subject marks are still not correct.'
        ]);
        $pdo->commit();
        $redirectBatchId = (int)$notificationStudent['batch_id'];
    } else {
        $stmt = $pdo->prepare('UPDATE scrutiny_notifications SET is_read = 1, read_at = COALESCE(read_at, NOW()) WHERE notification_id = :id AND recipient_user_id = :user_id');
        $stmt->execute([':id' => $notificationId, ':user_id' => $user['user_id']]);
    }
    if (in_array($action, ['verified', 'not_correct'], true)) {
        header('Location: ' . auth_base_path() . '/verification/marks.php?batch_id=' . $redirectBatchId);
    } else {
        header('Location: edit_scrutiny.php?case_id=' . (int)($_POST['case_id'] ?? 0));
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Scrutiny notification action error: ' . $e->getMessage());
    http_response_code(400);
    exit('Unable to update the scrutiny notification.');
}
