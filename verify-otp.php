<?php
// verify-otp.php - Step 2: Email OTP Authentication

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/services/MailerService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect back to login if pending_2fa_user_id session variable is missing
if (empty($_SESSION['pending_2fa_user_id'])) {
    setFlash('warning', 'Session expired or invalid access. Please enter your credentials first.');
    header('Location: ' . baseUrl('login.php'));
    exit;
}

$userId = (int)$_SESSION['pending_2fa_user_id'];
$userEmail = $_SESSION['pending_2fa_email'] ?? '';
$userName = $_SESSION['pending_2fa_name'] ?? 'User';

$pdo = getDbConnection();
$error = null;

// Fetch active OTP record
$stmtOtp = $pdo->prepare("
    SELECT * FROM otp_verifications 
    WHERE user_id = :uid AND is_used = 0 
    ORDER BY created_at DESC LIMIT 1
");
$stmtOtp->execute([':uid' => $userId]);
$otpRecord = $stmtOtp->fetch();

// Handle Resend OTP POST action
if (isset($_POST['action']) && $_POST['action'] === 'resend_otp') {
    enforceCsrf();

    $lastSent = $otpRecord ? strtotime($otpRecord['otp_last_sent_at']) : 0;
    $timeSinceLast = time() - $lastSent;

    if ($timeSinceLast < 60) {
        $waitSecs = 60 - $timeSinceLast;
        $error = "Please wait {$waitSecs} seconds before requesting a new OTP.";
    } else {
        // Invalidate previous OTPs
        $pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE user_id = :uid AND is_used = 0")
            ->execute([':uid' => $userId]);

        // Generate new OTP
        $newOtpCode = sprintf('%06d', random_int(0, 999999));
        $newOtpHash = hash('sha256', $newOtpCode);
        $newExpiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));

        $insertStmt = $pdo->prepare("
            INSERT INTO otp_verifications (user_id, otp_code_hash, expires_at, attempts, is_used, otp_last_sent_at, created_at)
            VALUES (:uid, :hash, :expires, 0, 0, NOW(), NOW())
        ");
        $insertStmt->execute([
            ':uid'     => $userId,
            ':hash'    => $newOtpHash,
            ':expires' => $newExpiresAt
        ]);

        logAudit($userId, 'OTP_SENT');

        $sent = MailerService::sendOtpEmail($userEmail, $userName, $newOtpCode);
        if (!$sent && ($_ENV['APP_ENV'] ?? 'development') === 'development') {
            setFlash('warning', 'A new OTP was generated, but email failed. Please check app error logs or try again.');
        } else {
            setFlash('info', 'A new 6-digit OTP code has been sent to your email.');
        }

        header('Location: ' . baseUrl('verify-otp.php'));
        exit;
    }
}

// Handle OTP Verification Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['action']) || $_POST['action'] === 'verify')) {
    enforceCsrf();

    $otpInput = trim($_POST['otp_code'] ?? '');

    if (empty($otpInput) || strlen($otpInput) !== 6 || !ctype_digit($otpInput)) {
        $error = "Please enter a valid 6-digit numeric OTP code.";
    } elseif (!$otpRecord) {
        $error = "No active OTP found. Please request a new OTP code.";
    } else {
        $now = new DateTime();
        $expiresAt = new DateTime($otpRecord['expires_at']);

        if ($now > $expiresAt) {
            // Mark as expired/used
            $pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE otp_id = :oid")
                ->execute([':oid' => $otpRecord['otp_id']]);
            $error = "OTP has expired. Please request a new OTP code.";
        } else {
            $inputHash = hash('sha256', $otpInput);

            if (!hash_equals($otpRecord['otp_code_hash'], $inputHash)) {
                $attempts = (int)$otpRecord['attempts'] + 1;
                logAudit($userId, 'OTP_FAILED');

                if ($attempts >= 3) {
                    // Invalidate OTP and force restart at Step 1
                    $pdo->prepare("UPDATE otp_verifications SET attempts = :att, is_used = 1 WHERE otp_id = :oid")
                        ->execute([':att' => $attempts, ':oid' => $otpRecord['otp_id']]);

                    unset($_SESSION['pending_2fa_user_id']);
                    unset($_SESSION['pending_2fa_email']);
                    unset($_SESSION['pending_2fa_name']);

                    setFlash('danger', 'Maximum OTP attempts exceeded. Please log in again with your password.');
                    header('Location: ' . baseUrl('login.php'));
                    exit;
                } else {
                    $pdo->prepare("UPDATE otp_verifications SET attempts = :att WHERE otp_id = :oid")
                        ->execute([':att' => $attempts, ':oid' => $otpRecord['otp_id']]);
                    $remaining = 3 - $attempts;
                    $error = "Invalid OTP code. You have {$remaining} attempt(s) remaining.";
                }
            } else {
                // Fetch user & role details
                $stmtUser = $pdo->prepare("
                    SELECT u.*, r.role_name, p.full_name 
                    FROM users u 
                    JOIN roles r ON u.role_id = r.role_id 
                    LEFT JOIN profiles p ON u.user_id = p.user_id 
                    WHERE u.user_id = :uid AND u.account_status = 'active'
                ");
                $stmtUser->execute([':uid' => $userId]);
                $user = $stmtUser->fetch();

                if (!$user) {
                    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_name']);
                    setFlash('danger', 'This account is no longer active. Please contact an administrator.');
                    header('Location: ' . baseUrl('login.php'));
                    exit;
                }

                // OTP VERIFIED SUCCESS
                $pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE otp_id = :oid")
                    ->execute([':oid' => $otpRecord['otp_id']]);

                // Regenerate Session ID to prevent Session Fixation
                session_regenerate_id(true);

                // Set full authenticated session
                $_SESSION['user_id']    = $user['user_id'];
                $_SESSION['role_id']    = $user['role_id'];
                $_SESSION['role_name']  = $user['role_name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name']  = $user['full_name'] ?? 'User';

                // Clear pending 2FA state
                unset($_SESSION['pending_2fa_user_id']);
                unset($_SESSION['pending_2fa_email']);
                unset($_SESSION['pending_2fa_name']);

                logAudit($user['user_id'], 'OTP_VERIFIED');
                setFlash('success', 'Logged in successfully!');

                // Redirect based on role
                if ($user['role_name'] === 'guide') {
                                $facultyStmt = $pdo->prepare('SELECT faculty_id, faculty_name, email FROM faculties WHERE user_id = :uid LIMIT 1');
                                $facultyStmt->execute([':uid' => $user['user_id']]);
                                $faculty = $facultyStmt->fetch();
                                if ($faculty) {
                                    $_SESSION['faculty_id'] = (int)$faculty['faculty_id'];
                                    $_SESSION['faculty_name'] = $faculty['faculty_name'];
                                    $_SESSION['faculty_email'] = $faculty['email'];
                                }
                                header('Location: ' . baseUrl('teacher/dashboard.php'));
                } elseif ($user['role_name'] === 'verification') {
                                header('Location: ' . baseUrl('verification/dashboard.php'));
                } else {
                    header('Location: ' . baseUrl('admin/dashboard.php'));
                }
                exit;
            }
        }
    }
}

// Calculate remaining cooldown for resend button
$lastSentTimestamp = $otpRecord ? strtotime($otpRecord['otp_last_sent_at']) : 0;
$timePassed = time() - $lastSentTimestamp;
$cooldownRemaining = max(0, 60 - $timePassed);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2FA Verification - IMD Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= baseUrl('assets/css/imd-auth.css') ?>">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
    <header class="portal-header">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <a href="<?= baseUrl() ?>" class="portal-brand fs-4">IMD Training Division Portal</a>
                <div class="gov-emblem-text">India Meteorological Department | Govt. of India</div>
            </div>
        </div>
    </header>

    <main class="container my-auto py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card card-auth p-4">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h3 class="card-title text-imd-navy fw-bold">Two-Factor Auth</h3>
                            <p class="text-muted small">Step 2 of 2: Enter 6-Digit OTP Code</p>
                            <p class="small text-secondary">
                                Sent to <strong><?= e($userEmail) ?></strong>
                            </p>
                        </div>

                        <?= displayFlash() ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= e($error) ?>
                            </div>
                        <?php endif; ?>

                        <!-- OTP Verification Form -->
                        <form action="verify-otp.php" method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="verify">

                            <div class="mb-4">
                                <label for="otp_code" class="form-label text-center d-block fw-bold">Enter 6-Digit Code</label>
                                <input type="text" class="form-control form-control-lg text-center fs-2 letter-spacing-2" 
                                       id="otp_code" name="otp_code" maxlength="6" pattern="\d{6}" 
                                       placeholder="000000" autocomplete="off" required autofocus>
                                <div class="form-text text-center mt-2">Code expires in 5 minutes.</div>
                            </div>

                            <button type="submit" class="btn btn-imd w-100 py-2 fs-5">Verify & Login</button>
                        </form>

                        <hr class="my-4">

                        <!-- Resend OTP Form -->
                        <div class="text-center">
                            <form action="verify-otp.php" method="POST" class="d-inline">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="resend_otp">
                                <button type="submit" id="resend-otp-btn" class="btn btn-link text-imd-navy p-0 text-decoration-none fw-bold" data-cooldown="<?= $cooldownRemaining ?>">
                                    Resend OTP <?= $cooldownRemaining > 0 ? "({$cooldownRemaining}s)" : "" ?>
                                </button>
                            </form>
                            <span class="mx-2 text-muted">|</span>
                            <a href="<?= baseUrl('logout.php') ?>" class="text-secondary small text-decoration-none">Cancel Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <footer class="text-center py-3 mt-auto">
        <div class="container">
            <small>&copy; <?= date('Y') ?> India Meteorological Department. Training Division Portal.</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= baseUrl('assets/js/imd-auth.js') ?>"></script>
</body>
</html>
