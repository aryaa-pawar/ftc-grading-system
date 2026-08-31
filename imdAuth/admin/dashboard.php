<?php
// admin/dashboard.php - Admin Control Center

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = requireAuth(['dept_admin', 'super_admin']);
$pdo = getDbConnection();

$isSuperAdmin = ($auth['role_name'] === 'super_admin');

// Handle Creation of Dept Admin (Super Admin Only)
$adminCreateError = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_dept_admin') {
    enforceCsrf();
    if (!$isSuperAdmin) {
        http_response_code(403);
        die("Only Super Admin can create Department Admins.");
    }

    $name = trim($_POST['admin_name'] ?? '');
    $email = strtolower(trim($_POST['admin_email'] ?? ''));
    $password = $_POST['admin_password'] ?? '';

    if (empty($name) || empty($email) || strlen($password) < 8) {
        $adminCreateError = "Please fill all fields. Password must be at least 8 characters.";
    } else {
        $stmtCheck = $pdo->prepare("SELECT user_id FROM users WHERE email = :email");
        $stmtCheck->execute([':email' => $email]);
        if ($stmtCheck->fetch()) {
            $adminCreateError = "An account with this email already exists.";
        } else {
            try {
                $pdo->beginTransaction();
                $passHash = password_hash($password, PASSWORD_BCRYPT);
                $deptAdminRoleId = $pdo->query("SELECT role_id FROM roles WHERE role_name = 'dept_admin'")->fetchColumn() ?: 2;
                $stmt = $pdo->prepare("
                    INSERT INTO users (email, password_hash, role_id, account_status, created_at)
                    VALUES (:email, :pass, :role_id, 'active', NOW())
                ");
                $stmt->execute([':email' => $email, ':pass' => $passHash, ':role_id' => $deptAdminRoleId]);
                $newUid = $pdo->lastInsertId();

                $stmtP = $pdo->prepare("
                    INSERT INTO profiles (user_id, full_name, department)
                    VALUES (:uid, :name, 'Department Admin')
                ");
                $stmtP->execute([':uid' => $newUid, ':name' => $name]);

                $pdo->commit();
                logAudit($newUid, 'DEPT_ADMIN_CREATED');
                setFlash('success', "Department Admin account created successfully for {$email}.");
                header('Location: ' . baseUrl('admin/dashboard.php'));
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("Dept Admin Creation Error: " . $e->getMessage(), 3, __DIR__ . '/../storage/logs/app_error.log');
                $adminCreateError = "Failed to create admin account due to a database error.";
            }
        }
    }
}

// Fetch Statistics
$pendingCount = $pdo->query("SELECT COUNT(*) FROM users WHERE account_status = 'pending_approval'")->fetchColumn();
$activeCount = $pdo->query("SELECT COUNT(*) FROM users WHERE account_status = 'active'")->fetchColumn();
$guideCount = $pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'guide'")->fetchColumn();
$adminCount = $pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name IN ('dept_admin', 'super_admin')")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - IMD Portal</title>
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
                        <a class="nav-link active" href="<?= baseUrl('admin/dashboard.php') ?>">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link position-relative" href="<?= baseUrl('admin/pending_approvals.php') ?>">
                            Pending Approvals
                            <?php if ($pendingCount > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-1"><?= $pendingCount ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= baseUrl('admin/manage_users.php') ?>">Manage Users</a>
                    </li>
                    <?php if ($isSuperAdmin): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= baseUrl('admin/audit_logs.php') ?>">Audit Logs</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex align-items-center text-white gap-3">
                    <span class="small">Logged in: <strong><?= e($auth['full_name']) ?></strong> (<?= e($auth['role_name']) ?>)</span>
                    <a href="<?= baseUrl('logout.php') ?>" class="btn btn-outline-light btn-sm">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container my-5">
        <?= displayFlash() ?>

        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card card-dashboard text-center p-3 border-warning bg-white">
                    <div class="fs-1 fw-bold text-warning"><?= $pendingCount ?></div>
                    <div class="text-muted fw-semibold">Pending Approvals</div>
                    <a href="<?= baseUrl('admin/pending_approvals.php') ?>" class="btn btn-sm btn-outline-warning mt-2">Review Queue</a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-dashboard text-center p-3 border-success bg-white">
                    <div class="fs-1 fw-bold text-success"><?= $activeCount ?></div>
                    <div class="text-muted fw-semibold">Active Users</div>
                    <a href="<?= baseUrl('admin/manage_users.php?status=active') ?>" class="btn btn-sm btn-outline-success mt-2">View Active</a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-dashboard text-center p-3 border-info bg-white">
                    <div class="fs-1 fw-bold text-info"><?= $guideCount ?></div>
                    <div class="text-muted fw-semibold">Research Guides</div>
                    <a href="<?= baseUrl('admin/manage_users.php?role=guide') ?>" class="btn btn-sm btn-outline-info mt-2">View Guides</a>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-dashboard text-center p-3 border-primary bg-white">
                    <div class="fs-1 fw-bold text-imd-navy"><?= $adminCount ?></div>
                    <div class="text-muted fw-semibold">Administrators</div>
                    <a href="<?= baseUrl('admin/manage_users.php?role=dept_admin') ?>" class="btn btn-sm btn-outline-primary mt-2">View Admins</a>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Approvals Quick Link -->
            <div class="col-md-6">
                <div class="card card-dashboard p-4 h-100">
                    <h4 class="card-title text-imd-navy fw-bold mb-3">Registration Queue</h4>
                    <p class="text-muted">
                        Review pending faculty guide registration applications and approve or reject access.
                    </p>
                    <div class="mt-auto">
                        <a href="<?= baseUrl('admin/pending_approvals.php') ?>" class="btn btn-imd py-2 px-4">
                            Open Pending Approvals (<?= $pendingCount ?>)
                        </a>
                    </div>
                </div>
            </div>

            <!-- Super Admin Section: Manage Dept Admins -->
            <?php if ($isSuperAdmin): ?>
                <div class="col-md-6">
                    <div class="card card-dashboard p-4 border-primary">
                        <h4 class="card-title text-imd-navy fw-bold mb-2">Create Department Admin</h4>
                        <p class="text-muted small mb-3">Super Admin Privilege: Add new Departmental Administrators to assist with registration approvals.</p>

                        <?php if ($adminCreateError): ?>
                            <div class="alert alert-danger py-2 small"><?= e($adminCreateError) ?></div>
                        <?php endif; ?>

                        <form action="dashboard.php" method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="create_dept_admin">

                            <div class="mb-2">
                                <label for="admin_name" class="form-label small mb-1">Admin Full Name</label>
                                <input type="text" class="form-control form-control-sm" id="admin_name" name="admin_name" required>
                            </div>
                            <div class="mb-2">
                                <label for="admin_email" class="form-label small mb-1">Admin Email Address</label>
                                <input type="email" class="form-control form-control-sm" id="admin_email" name="admin_email" required>
                            </div>
                            <div class="mb-3">
                                <label for="admin_password" class="form-label small mb-1">Initial Password (min 8 chars)</label>
                                <input type="password" class="form-control form-control-sm" id="admin_password" name="admin_password" minlength="8" required>
                            </div>

                            <button type="submit" class="btn btn-success btn-sm w-100">Create Dept Admin Account</button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="col-md-6">
                    <div class="card card-dashboard p-4 h-100">
                        <h4 class="card-title text-imd-navy fw-bold mb-3">User Directory</h4>
                        <p class="text-muted">Browse and search the entire directory of registered scholars and research guides.</p>
                        <div class="mt-auto">
                            <a href="<?= baseUrl('admin/manage_users.php') ?>" class="btn btn-outline-primary py-2 px-4">Browse Directory</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($isSuperAdmin): ?>
        <div class="row g-4 mt-1">
            <div class="col-md-12">
                <div class="card card-dashboard p-4 border-dark h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h4 class="card-title text-dark fw-bold mb-2">System Audit Logs</h4>
                            <p class="text-muted small mb-0">Review comprehensive system activity, logins, and administrative actions.</p>
                        </div>
                        <a href="<?= baseUrl('admin/audit_logs.php') ?>" class="btn btn-dark py-2 px-4">View Audit Logs</a>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <footer class="text-center py-3 mt-auto">
        <div class="container">
            <small>&copy; <?= date('Y') ?> India Meteorological Department. Training Division Portal.</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
