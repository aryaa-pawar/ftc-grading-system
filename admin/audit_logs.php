<?php
// admin/audit_logs.php - System Audit Logs

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

// Only Super Admin can view audit logs
$auth = requireAuth(['admin', 'super_admin']);
$pdo = getDbConnection();

// Fetch Logs
$stmt = $pdo->query("
    SELECT al.audit_id, al.action, al.ip_address, al.created_at, 
           u.email, p.full_name
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.user_id
    LEFT JOIN profiles p ON u.user_id = p.user_id
    ORDER BY al.created_at DESC
    LIMIT 500
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$pageTitle = 'System Audit Logs';

?>
<?php require_once __DIR__ . '/../includes/header.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= baseUrl('assets/css/imd-auth.css') ?>">
<div style="display:flex; flex-direction:column; min-height:100vh;">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <div style="display:flex; flex:1;">
        <?php require_once __DIR__ . '/../includes/sidebar.php'; ?>
        <main class="container my-5" style="flex:1;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="text-imd-navy fw-bold mb-0">System Audit Logs</h2>
            <a href="<?= baseUrl('admin/dashboard.php') ?>" class="btn btn-outline-secondary btn-sm">Back to Dashboard</a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0 align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th>Log ID</th>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No audit logs found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>#<?= $log['audit_id'] ?></td>
                                        <td class="text-nowrap"><?= date('M d, Y H:i:s', strtotime($log['created_at'])) ?></td>
                                        <td>
                                            <?php if ($log['email']): ?>
                                                <strong><?= e($log['full_name']) ?></strong><br>
                                                <small class="text-muted"><?= e($log['email']) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted fst-italic">System / Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= e($log['action']) ?></span>
                                        </td>
                                        <td>
                                            <code><?= e($log['ip_address'] ?? 'N/A') ?></code>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="mt-3 text-muted small text-end">Showing last 500 records.</div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>
