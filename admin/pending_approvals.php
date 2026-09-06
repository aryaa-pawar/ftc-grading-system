<?php
// admin/pending_approvals.php - Pending Registration Approvals Queue

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/MailerService.php';

$auth = requireAuth(['admin', 'super_admin']);
$pdo = getDbConnection();

// Handle Approval POST Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    enforceCsrf();

    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $actionType = $_POST['action']; // 'approve' or 'reject'

    // Fetch user details
    $stmtFetch = $pdo->prepare("
        SELECT u.*, r.role_name, p.* 
        FROM users u 
        JOIN roles r ON u.role_id = r.role_id 
        JOIN profiles p ON u.user_id = p.user_id 
        WHERE u.user_id = :uid AND u.account_status = 'pending_approval'
    ");
    $stmtFetch->execute([':uid' => $targetUserId]);
    $targetUser = $stmtFetch->fetch();

    if (!$targetUser) {
        setFlash('danger', 'User not found or is no longer in pending approval state.');
        header('Location: ' . baseUrl('admin/pending_approvals.php'));
        exit;
    }

    if ($actionType === 'approve') {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $pdo->beginTransaction();

            $stmtApprove = $pdo->prepare("UPDATE users SET account_status = 'active', rejection_reason = NULL WHERE user_id = :uid AND account_status = 'pending_approval'");
            $stmtApprove->execute([':uid' => $targetUserId]);

            // Synchronize the legacy faculty record only after approval.
            $stmtFaculty = $pdo->prepare('SELECT * FROM faculties WHERE email = :email LIMIT 1');
            $stmtFaculty->execute([':email' => $targetUser['email']]);
            $existingFaculty = $stmtFaculty->fetch(PDO::FETCH_ASSOC);

            if ($existingFaculty && $existingFaculty['user_id'] === null && (int)$existingFaculty['is_deleted'] === 1) {
                // Reactivate the preserved row so its faculty_id and mark history remain intact.
                $stmtFacultyUpdate = $pdo->prepare('UPDATE faculties SET user_id = :new_user_id, faculty_name = :full_name, contact_number = :phone, is_deleted = 0, password = NULL WHERE faculty_id = :existing_faculty_id');
                $stmtFacultyUpdate->execute([
                    ':new_user_id' => $targetUserId,
                    ':full_name' => $targetUser['full_name'],
                    ':phone' => $targetUser['phone'] ?? null,
                    ':existing_faculty_id' => $existingFaculty['faculty_id'],
                ]);
            } elseif (!$existingFaculty) {
                $stmtFacultyInsert = $pdo->prepare("INSERT INTO faculties (faculty_name, email, password, contact_number, user_id, is_deleted) VALUES (:name, :email, :password, :phone, :uid, 0)");
                $stmtFacultyInsert->execute([
                    ':name' => $targetUser['full_name'],
                    ':email' => $targetUser['email'],
                    ':password' => $targetUser['password_hash'],
                    ':phone' => $targetUser['phone'] ?? null,
                    ':uid' => $targetUserId
                ]);
            } else {
                throw new RuntimeException('An active faculty record already exists for this email. Approval was not completed.');
            }

            $pdo->commit();
            logAudit($auth['user_id'], "ACCOUNT_APPROVED (User ID: {$targetUserId})");

            // Attempt to send email without rolling back database approval if SMTP fails
            try {
                $mailSent = MailerService::sendApprovalEmail($targetUser['email'], $targetUser['full_name']);
            } catch (Exception $me) {
                $mailSent = false;
            }

            if ($mailSent) {
                setFlash('success', "Account approved successfully for {$targetUser['full_name']}. Notification email sent.");
            } else {
                setFlash('success', "Account approved successfully for {$targetUser['full_name']}. (Note: Notification email delivery pending SMTP config).");
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Approval Error: " . $e->getMessage(), 3, __DIR__ . '/../storage/logs/app_error.log');
            setFlash('danger', 'Failed to approve account due to a database error.');
        }

        header('Location: ' . baseUrl('admin/pending_approvals.php'));
        exit;

    } elseif ($actionType === 'reject') {
        $rejectionReason = trim($_POST['rejection_reason'] ?? '');

        if (empty($rejectionReason)) {
            setFlash('danger', 'A rejection reason must be provided.');
            header('Location: ' . baseUrl('admin/pending_approvals.php'));
            exit;
        }

        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $stmtReject = $pdo->prepare("UPDATE users SET account_status = 'rejected', rejection_reason = :reason WHERE user_id = :uid AND account_status = 'pending_approval'");
            $stmtReject->execute([
                ':uid' => $targetUserId,
                ':reason' => $rejectionReason
            ]);
            logAudit($auth['user_id'], "ACCOUNT_REJECTED (User ID: {$targetUserId})");

            try {
                MailerService::sendRejectionEmail($targetUser['email'], $targetUser['full_name'], $rejectionReason);
            } catch (Exception $me) {
                // Email failure non-blocking
            }

            setFlash('info', "Registration for {$targetUser['full_name']} rejected.");

        } catch (Exception $e) {
            error_log("Rejection Error: " . $e->getMessage(), 3, __DIR__ . '/../storage/logs/app_error.log');
            setFlash('danger', 'Failed to update user rejection status.');
        }

        header('Location: ' . baseUrl('admin/pending_approvals.php'));
        exit;
    }
}

// Fetch Pending Approvals Queue
$stmtPending = $pdo->prepare("
    SELECT u.user_id, u.email, u.created_at, r.role_name, p.* 
    FROM users u 
    JOIN roles r ON u.role_id = r.role_id 
    JOIN profiles p ON u.user_id = p.user_id 
    WHERE u.account_status = 'pending_approval' 
    ORDER BY u.created_at ASC
");
$stmtPending->execute();
$pendingUsers = $stmtPending->fetchAll();
$pageTitle = 'Pending Approvals';
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
            <h3 class="text-imd-navy fw-bold mb-0">Pending Registration Queue</h3>
            <span class="badge bg-primary fs-6"><?= count($pendingUsers) ?> Applications Pending</span>
        </div>

        <?= displayFlash() ?>

        <?php if (empty($pendingUsers)): ?>
            <div class="card card-dashboard p-5 text-center">
                <div class="card-body">
                    <h4 class="text-muted">No Pending Approvals</h4>
                    <p class="text-secondary">All registration requests have been reviewed.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach ($pendingUsers as $user): ?>
                    <div class="col-md-6">
                        <div class="card card-dashboard p-4 h-100 border-start border-4 border-warning">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="fw-bold text-imd-navy mb-1"><?= e($user['full_name']) ?></h5>
                                    <span class="badge bg-secondary"><?= e(roleDisplayLabel($user['role_name'])) ?> Application</span>
                                </div>
                                <small class="text-muted">Applied: <?= date('d M Y, H:i', strtotime($user['created_at'])) ?></small>
                            </div>

                            <div class="bg-light p-3 rounded mb-3 small">
                                <div class="row mb-1">
                                    <div class="col-4 text-muted">Email:</div>
                                    <div class="col-8 fw-semibold text-break"><?= e($user['email']) ?></div>
                                </div>
                                <div class="row mb-1">
                                    <div class="col-4 text-muted">Phone:</div>
                                    <div class="col-8 fw-semibold"><?= e($user['phone'] ?? 'N/A') ?></div>
                                </div>
                                <div class="row">
                                    <div class="col-4 text-muted">Department:</div>
                                    <div class="col-8 fw-semibold"><?= e($user['department'] ?? 'N/A') ?></div>
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-auto">
                                <!-- Approve Form -->
                                <form action="pending_approvals.php" method="POST" class="flex-grow-1">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-success btn-sm w-100 py-2" onclick="return confirm('Approve registration for <?= e($user['full_name']) ?>?');">
                                        Approve
                                    </button>
                                </form>

                                <!-- Reject Trigger Modal Button -->
                                <button type="button" class="btn btn-outline-danger btn-sm flex-grow-1 py-2" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $user['user_id'] ?>">
                                    Reject
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Reject Modal -->
                    <div class="modal fade" id="rejectModal<?= $user['user_id'] ?>" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <form action="pending_approvals.php" method="POST">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                    <input type="hidden" name="action" value="reject">

                                    <div class="modal-header bg-danger text-white">
                                        <h5 class="modal-title">Reject Registration</h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>You are rejecting the registration application for <strong><?= e($user['full_name']) ?></strong>.</p>
                                        <div class="mb-3">
                                            <label for="reason<?= $user['user_id'] ?>" class="form-label fw-bold">Reason for Rejection <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="reason<?= $user['user_id'] ?>" name="rejection_reason" rows="3" placeholder="Provide a short explanation to be included in the email notification." required></textarea>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>
