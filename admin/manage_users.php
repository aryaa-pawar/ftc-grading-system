<?php
// admin/manage_users.php - User Management Directory & Controls

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/MailerService.php';

$auth = requireAuth(['admin', 'super_admin']);
$pdo = getDbConnection();

$isSuperAdmin = ($auth['role_name'] === 'super_admin');

// Handle creation of an active verification user.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_verification_user') {
    enforceCsrf();

    if (!$isSuperAdmin) {
        http_response_code(403);
        die('Only Super Admin can create verification users.');
    }

    $name = trim($_POST['verification_name'] ?? '');
    $email = strtolower(trim($_POST['verification_email'] ?? ''));
    $password = $_POST['verification_password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        setFlash('danger', 'Enter a name, a valid email, and a password of at least 8 characters.');
    } else {
        try {
            $pdo->beginTransaction();

            $stmtRole = $pdo->query("SELECT role_id FROM roles WHERE role_name = 'verification' LIMIT 1");
            $verificationRoleId = $stmtRole->fetchColumn();
            if ($verificationRoleId === false) {
                throw new RuntimeException('The verification role is missing. Run database_migration.sql first.');
            }

            $stmtUser = $pdo->prepare('SELECT user_id FROM users WHERE email = :email LIMIT 1');
            $stmtUser->execute([':email' => $email]);
            if ($stmtUser->fetchColumn()) {
                throw new RuntimeException('An account with this email already exists.');
            }

            $stmtUser = $pdo->prepare('INSERT INTO users (email, password_hash, role_id, account_status, created_at) VALUES (:email, :password_hash, :role_id, \'active\', NOW())');
            $stmtUser->execute([
                ':email' => $email,
                ':password_hash' => password_hash($password, PASSWORD_BCRYPT),
                ':role_id' => $verificationRoleId
            ]);
            $newUserId = (int)$pdo->lastInsertId();

            $stmtProfile = $pdo->prepare("INSERT INTO profiles (user_id, full_name, department) VALUES (:user_id, :full_name, 'Marks Verification')");
            $stmtProfile->execute([':user_id' => $newUserId, ':full_name' => $name]);

            $pdo->commit();
            logAudit($auth['user_id'], "VERIFICATION_USER_CREATED (Target User ID: {$newUserId})");
            if (MailerService::sendVerificationOfficerCredentials($email, $name, $password)) {
                setFlash('success', "Verification user created successfully for {$email}. Credentials were emailed.");
            } else {
                setFlash('warning', "Verification user created successfully, but the credentials email could not be sent.");
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            setFlash('danger', $e->getMessage());
        }
    }

    header('Location: ' . baseUrl('admin/manage_users.php'));
    exit;
}

// Handle Super Admin User Management POST Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    enforceCsrf();

    if (!$isSuperAdmin) {
        http_response_code(403);
        die("Only Super Admin can modify user roles or account status.");
    }

    $targetUserId = (int)($_POST['target_user_id'] ?? 0);
    $newStatus    = $_POST['account_status'] ?? 'active';
    $newRoleId    = (int)($_POST['role_id'] ?? 1);

    $stmtRole = $pdo->prepare('SELECT role_id FROM roles WHERE role_id = :role_id AND role_name IN (\'guide\', \'verification\', \'super_admin\') LIMIT 1');
    $stmtRole->execute([':role_id' => $newRoleId]);
    $validatedRoleId = $stmtRole->fetchColumn();

    if (!in_array($newStatus, ['active', 'pending_approval', 'rejected', 'suspended'], true)) {
        setFlash('danger', 'Invalid status value.');
    } elseif ($validatedRoleId === false) {
        setFlash('danger', 'Invalid role value.');
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE users 
                SET account_status = :status, role_id = :role_id 
                WHERE user_id = :uid
            ");
            $stmt->execute([
                ':status'  => $newStatus,
                ':role_id' => $validatedRoleId,
                ':uid'     => $targetUserId
            ]);

            logAudit($auth['user_id'], "USER_MANAGED (Target User ID: {$targetUserId}, Status: {$newStatus}, Role: {$newRoleId})");
            setFlash('success', "User account settings updated successfully.");

        } catch (Exception $e) {
            error_log("User Manage Error: " . $e->getMessage(), 3, __DIR__ . '/../storage/logs/app_error.log');
            setFlash('danger', 'Failed to update user account.');
        }
    }

    header('Location: ' . baseUrl('admin/manage_users.php'));
    exit;
}

// Search / Filter Params
$filterRole = $_GET['role'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$searchQuery = trim($_GET['q'] ?? '');

$sql = "
    SELECT u.*, r.role_name, p.full_name, p.phone, p.department, p.photo_path
    FROM users u 
    JOIN roles r ON u.role_id = r.role_id 
    LEFT JOIN profiles p ON u.user_id = p.user_id 
    WHERE 1=1
";
$params = [];

if (!empty($filterRole)) {
    $sql .= " AND r.role_name = :role";
    $params[':role'] = $filterRole;
}

if (!empty($filterStatus)) {
    $sql .= " AND u.account_status = :status";
    $params[':status'] = $filterStatus;
}

if (!empty($searchQuery)) {
    $sql .= " AND (u.email LIKE :q_email OR p.full_name LIKE :q_full_name OR p.department LIKE :q_department)";
    $searchPattern = "%{$searchQuery}%";
    $params[':q_email'] = $searchPattern;
    $params[':q_full_name'] = $searchPattern;
    $params[':q_department'] = $searchPattern;
}

$sql .= " ORDER BY u.user_id DESC";

$stmtUsers = $pdo->prepare($sql);
$stmtUsers->execute($params);
$usersList = $stmtUsers->fetchAll();

// Fetch roles list for select dropdown
$roles = $pdo->query("SELECT * FROM roles WHERE role_name IN ('guide', 'verification', 'super_admin') ORDER BY role_id ASC")->fetchAll();
$pageTitle = 'Manage Users';
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
            <h3 class="text-imd-navy fw-bold mb-0">User Directory & Management</h3>
        </div>

        <?= displayFlash() ?>

        <!-- Search & Filter Controls -->
        <div class="card card-dashboard p-3 mb-4">
            <form action="manage_users.php" method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="q" placeholder="Search by name, email, or department..." value="<?= e($searchQuery) ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="role">
                        <option value="">-- All Roles --</option>
                        <option value="guide" <?= $filterRole === 'guide' ? 'selected' : '' ?>>Faculty / Lecturer</option>
                        <option value="verification" <?= $filterRole === 'verification' ? 'selected' : '' ?>>Scrutiny Officer</option>
                        <option value="super_admin" <?= $filterRole === 'super_admin' ? 'selected' : '' ?>>Super Admin</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="">-- All Statuses --</option>
                        <option value="active" <?= $filterStatus === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="pending_approval" <?= $filterStatus === 'pending_approval' ? 'selected' : '' ?>>Pending Approval</option>
                        <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="suspended" <?= $filterStatus === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-imd w-100">Filter</button>
                    <a href="manage_users.php" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>

        <?php if ($isSuperAdmin): ?>
            <div class="card card-dashboard p-4 mb-4 border-primary">
                <h5 class="text-imd-navy fw-bold mb-3">Create Verification User</h5>
                <form action="manage_users.php" method="POST" class="row g-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="create_verification_user">
                    <div class="col-md-4">
                        <label for="verification_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="verification_name" name="verification_name" required>
                    </div>
                    <div class="col-md-4">
                        <label for="verification_email" class="form-label">Email Address</label>
                        <input type="email" class="form-control" id="verification_email" name="verification_email" required>
                    </div>
                    <div class="col-md-3">
                        <label for="verification_password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="verification_password" name="verification_password" minlength="8" required>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Add</button>
                    </div>
                </form>
                <div class="form-text mt-2">The account is created active and will use OTP verification at login.</div>
            </div>
        <?php endif; ?>

        <!-- Users Table -->
        <div class="card card-dashboard p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>User</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Registered</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usersList)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No users found matching the selected criteria.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usersList as $u): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if (!empty($u['photo_path'])): ?>
                                                <img src="<?= baseUrl('serve_photo.php?file=' . urlencode(basename($u['photo_path']))) ?>" class="rounded-circle" width="40" height="40" style="object-fit: cover;">
                                            <?php else: ?>
                                                <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px;">
                                                    <?= strtoupper(substr($u['full_name'] ?? 'U', 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-bold"><?= e($u['full_name'] ?? 'N/A') ?></div>
                                                <div class="text-muted small"><?= e($u['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary text-uppercase"><?= e($u['role_name']) ?></span>
                                    </td>
                                    <td>
                                        <div class="text-muted small"><?= e($u['department'] ?? 'Administrative') ?></div>
                                    </td>
                                    <td>
                                        <?php if ($u['account_status'] === 'active'): ?>
                                            <span class="badge badge-active">Active</span>
                                        <?php elseif ($u['account_status'] === 'pending_approval'): ?>
                                            <span class="badge badge-pending">Pending</span>
                                        <?php elseif ($u['account_status'] === 'rejected'): ?>
                                            <span class="badge badge-rejected">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge badge-suspended">Suspended</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?= date('d M Y', strtotime($u['created_at'])) ?>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#userModal<?= $u['user_id'] ?>">
                                            View Profile
                                        </button>
                                    </td>
                                </tr>

                                <!-- View / Edit User Modal -->
                                <div class="modal fade" id="userModal<?= $u['user_id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-lg">
                                        <div class="modal-content">
                                            <div class="modal-header bg-imd-navy text-white">
                                                <h5 class="modal-title">User Profile Details</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="row mb-4">
                                                    <div class="col-md-3 text-center">
                                                        <?php if (!empty($u['photo_path'])): ?>
                                                            <img src="<?= baseUrl('serve_photo.php?file=' . urlencode(basename($u['photo_path']))) ?>" class="rounded-circle border mb-2" width="100" height="100" style="object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2 fw-bold fs-2" style="width: 100px; height: 100px;">
                                                                <?= strtoupper(substr($u['full_name'] ?? 'U', 0, 1)) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="col-md-9">
                                                        <h4><?= e($u['full_name']) ?></h4>
                                                        <p class="text-muted mb-1">Email: <strong><?= e($u['email']) ?></strong></p>
                                                        <p class="text-muted mb-1">Phone: <strong><?= e($u['phone'] ?? 'N/A') ?></strong></p>
                                                        <p class="text-muted mb-1">Department: <strong><?= e($u['department'] ?? 'N/A') ?></strong></p>
                                                    </div>
                                                </div>

                                                <?php if ($isSuperAdmin): ?>
                                                    <hr>
                                                    <h5 class="text-imd-navy">Super Admin Management</h5>
                                                    <form action="manage_users.php" method="POST">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="action" value="update_user">
                                                        <input type="hidden" name="target_user_id" value="<?= $u['user_id'] ?>">

                                                        <div class="row g-3 my-2">
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-bold small">Role</label>
                                                                <select class="form-select form-select-sm" name="role_id">
                                                                    <?php foreach ($roles as $r): ?>
                                                                        <option value="<?= $r['role_id'] ?>" <?= $u['role_id'] == $r['role_id'] ? 'selected' : '' ?>>
                                                                            <?= e($r['role_name'] === 'verification' ? 'Verification' : ($r['role_name'] === 'guide' ? 'Guide / Supervisor' : 'Super Admin')) ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label fw-bold small">Account Status</label>
                                                                <select class="form-select form-select-sm" name="account_status">
                                                                    <option value="active" <?= $u['account_status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                                    <option value="pending_approval" <?= $u['account_status'] === 'pending_approval' ? 'selected' : '' ?>>Pending Approval</option>
                                                                    <option value="rejected" <?= $u['account_status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                                                    <option value="suspended" <?= $u['account_status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                                                </select>
                                                            </div>
                                                        </div>

                                                        <div class="mt-3 text-end">
                                                            <button type="submit" class="btn btn-primary btn-sm">Save Changes</button>
                                                        </div>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>
