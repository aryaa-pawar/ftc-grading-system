<?php

require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/database.php';

$user = require_login();
if (!in_array($user['role_name'] ?? '', ['admin', 'super_admin'], true)) {
    http_response_code(403);
    exit('403 Forbidden');
}

$pageTitle = 'Scrutiny Edit Logs';
$logs = [];
$errorMessage = '';

try {
        $stmt = $pdo->query("SELECT l.change_id, l.case_id, l.old_marks, l.new_marks, l.reason, l.action, l.changed_at,
                                s.student_name, s.roll_no, sub.subject_code, sub.subject_name,
                        at.assessment_type_name,
                                COALESCE(p.full_name, u.email) AS changed_by
                         FROM scrutiny_mark_change_log l
                         INNER JOIN students s ON s.student_id = l.student_id
                         INNER JOIN subjects sub ON sub.subject_id = l.subject_id
                         INNER JOIN users u ON u.user_id = l.changed_by_user_id
                    LEFT JOIN faculty_marks_entry fme ON fme.entry_id = l.entry_id
                    LEFT JOIN assessment_types at ON at.assessment_type_id = fme.assessment_type_id
                         LEFT JOIN profiles p ON p.user_id = u.user_id
                         ORDER BY l.changed_at ASC, l.change_id ASC");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = 'Unable to load scrutiny edit logs. Confirm the scrutiny tracking tables are installed.';
}

require_once __DIR__ . '/../../includes/header.php';
?>
<style>
    .scrutiny-logs-page { flex: 1; min-width: 0; padding: 30px; background: #f4f6f8; }
    .scrutiny-logs-content { max-width: 1400px; margin: 0 auto; }
    .scrutiny-logs-heading { display: flex; justify-content: space-between; align-items: center; gap: 15px; flex-wrap: wrap; margin-bottom: 22px; }
    .scrutiny-logs-heading h1 { margin: 0; color: #1f4e79; font-size: 28px; }
    .scrutiny-logs-heading p { margin: 6px 0 0; color: #666; }
    .scrutiny-logs-card { overflow: hidden; background: #fff; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
    .scrutiny-logs-table-wrap { overflow-x: auto; }
    .scrutiny-logs-table { width: 100%; min-width: 1050px; border-collapse: collapse; font-size: 13px; }
    .scrutiny-logs-table th { padding: 12px; background: #1f4e79; color: #fff; text-align: left; white-space: nowrap; }
    .scrutiny-logs-table td { padding: 12px; border-bottom: 1px solid #e7e7e7; vertical-align: top; }
    .scrutiny-logs-table tr:last-child td { border-bottom: 0; }
    .scrutiny-logs-table tbody tr:hover { background: #f7fafc; }
    .scrutiny-log-id { color: #1f4e79; font-weight: bold; }
    .scrutiny-log-marks { white-space: nowrap; font-weight: bold; }
    .scrutiny-log-reason { max-width: 300px; color: #555; }
    .scrutiny-log-empty, .scrutiny-log-error { padding: 25px; color: #666; text-align: center; }
    .scrutiny-log-error { color: #a12622; background: #fff5f4; }
</style>
<div style="display:flex;flex-direction:column;min-height:100vh;">
    <?php require_once __DIR__ . '/../../includes/navbar.php'; ?>
    <div style="display:flex;flex:1;min-height:0;">
        <?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>
        <main class="scrutiny-logs-page">
            <div class="scrutiny-logs-content">
                <div class="scrutiny-logs-heading">
                    <div>
                        <h1>Scrutiny Edit Logs</h1>
                        <p>Permanent history of every mark correction made by an administrator.</p>
                    </div>
                    <a href="../dashboard.php" class="btn" style="display:inline-flex;align-items:center;background:#fff;border:1px solid #ccc;color:#1f4e79;text-decoration:none;">Back to Dashboard</a>
                </div>
                <div class="scrutiny-logs-card">
                    <?php if ($errorMessage): ?>
                        <div class="scrutiny-log-error"><?php echo htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php elseif (!$logs): ?>
                        <div class="scrutiny-log-empty">No scrutiny edit logs found.</div>
                    <?php else: ?>
                        <div class="scrutiny-logs-table-wrap">
                            <table class="scrutiny-logs-table">
                                <thead>
                                    <tr>
                                        <th>Log ID</th>
                                        <th>Date &amp; Time</th>
                                        <th>Student</th>
                                        <th>Subject</th>
                                        <th>Assessment</th>
                                        <th>Marks Changed</th>
                                        <th>Edited By Admin</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td class="scrutiny-log-id">#<?php echo (int)$log['change_id']; ?><br><small>Case #<?php echo (int)$log['case_id']; ?></small></td>
                                            <td><?php echo htmlspecialchars(date('d M Y, g:i A', strtotime($log['changed_at'])), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><strong><?php echo htmlspecialchars($log['student_name'], ENT_QUOTES, 'UTF-8'); ?></strong><br><small><?php echo htmlspecialchars($log['roll_no'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                            <td><?php echo htmlspecialchars($log['subject_code'] . ' - ' . $log['subject_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($log['assessment_type_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="scrutiny-log-marks"><?php echo $log['old_marks'] === null ? 'N/A' : htmlspecialchars($log['old_marks'], ENT_QUOTES, 'UTF-8'); ?> &rarr; <?php echo htmlspecialchars($log['new_marks'], ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><strong>Admin</strong><br><small><?php echo htmlspecialchars($log['changed_by'], ENT_QUOTES, 'UTF-8'); ?></small></td>
                                            <td class="scrutiny-log-reason"><?php echo htmlspecialchars($log['reason'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
    <?php require_once __DIR__ . '/../../includes/footer.php'; ?>
</div>
