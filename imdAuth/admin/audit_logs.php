<?php
// admin/audit_logs.php - System Audit Logs

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

// Only Super Admin can view audit logs
$auth = requireAuth(['super_admin']);
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Audit Logs - IMD Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= baseUrl('assets/css/style.css') ?>">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-imd-navy shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= baseUrl('admin/dashboard.php') ?>">IMD Admin Control Panel</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= baseUrl('admin/dashboard.php') ?>">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= baseUrl('admin/pending_approvals.php') ?>">Pending Approvals</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= baseUrl('admin/manage_users.php') ?>">Manage Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?= baseUrl('admin/audit_logs.php') ?>">Audit Logs</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center text-white gap-3">
                    <span class="small">Logged in: <strong><?= e($auth['full_name']) ?></strong> (<?= e($auth['role_name']) ?>)</span>
                    <a href="<?= baseUrl('logout.php') ?>" class="btn btn-outline-light btn-sm">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container my-5">
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

    <footer class="text-center py-3 mt-auto">
        <div class="container">
            <small>&copy; <?= date('Y') ?> India Meteorological Department. Training Division Portal.</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
