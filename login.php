<?php
// login.php - Step 1: Password Authentication

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/services/MailerService.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect logged-in users to dashboard
if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['role_name'] ?? '';
    if ($role === 'guide') header('Location: ' . baseUrl('teacher/dashboard.php'));
    elseif ($role === 'verification') header('Location: ' . baseUrl('verification/dashboard.php'));
    else header('Location: ' . baseUrl('admin/dashboard.php'));
    exit;
}

// Redirect if already passed Step 1 and awaiting 2FA
if (!empty($_SESSION['pending_2fa_user_id'])) {
    header('Location: ' . baseUrl('verify-otp.php'));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrf();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter both Email and Password.";
    } else {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT u.*, r.role_name, p.full_name 
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            LEFT JOIN profiles p ON u.user_id = p.user_id
            WHERE u.email = :email
        ");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            logAudit(null, 'LOGIN_FAILED');
            $error = "Invalid credentials.";
        } else {
            $now = new DateTime();
            $lockedUntil = $user['locked_until'] ? new DateTime($user['locked_until']) : null;

            // Check if locked out
            if ($lockedUntil && $lockedUntil > $now) {
                $interval = $now->diff($lockedUntil);
                $mins = $interval->i + 1;
                $error = "Account is locked due to too many failed attempts. Try again in {$mins} minute(s).";
            } else {
                // Verify password
                if (!password_verify($password, $user['password_hash'])) {
                    $failed = (int)$user['failed_login_attempts'] + 1;
                    $lockTimeSql = null;

                    if ($failed >= 5) {
                        $lockUntil = (new DateTime())->add(new DateInterval('PT15M'))->format('Y-m-d H:i:s');
                        $updateStmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = :locked WHERE user_id = :user_id");
                        $updateStmt->execute([':locked' => $lockUntil, ':user_id' => $user['user_id']]);
                        $error = "Too many failed attempts. Account locked for 15 minutes.";
                    } else {
                        $updateStmt = $pdo->prepare("UPDATE users SET failed_login_attempts = :failed WHERE user_id = :user_id");
                        $updateStmt->execute([':failed' => $failed, ':user_id' => $user['user_id']]);
                        $error = "Invalid credentials. Attempt {$failed} of 5.";
                    }

                    logAudit($user['user_id'], 'LOGIN_FAILED');
                } else {
                    // Check account status
                    if ($user['account_status'] === 'pending_approval') {
                        $error = "Your account is currently PENDING ADMIN APPROVAL. You will be notified by email once approved.";
                    } elseif ($user['account_status'] === 'rejected') {
                        $reason = $user['rejection_reason'] ? " Reason: " . $user['rejection_reason'] : "";
                        $error = "Your registration request was REJECTED by admin." . $reason;
                    } elseif ($user['account_status'] === 'suspended') {
                        $error = "Your account has been SUSPENDED. Please contact the IMD administrator.";
                    } else {
                        // Reset login attempts
                        $resetStmt = $pdo->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE user_id = :user_id");
                        $resetStmt->execute([':user_id' => $user['user_id']]);

                        logAudit($user['user_id'], 'LOGIN_PASSWORD_OK');

                        // Initiate Step 2 (OTP)
                        $_SESSION['pending_2fa_user_id'] = $user['user_id'];
                        $_SESSION['pending_2fa_email']   = $user['email'];
                        $_SESSION['pending_2fa_name']    = $user['full_name'] ?? 'User';

                        // Generate 6-digit OTP
                        $otpCode = sprintf('%06d', random_int(0, 999999));
                        $otpHash = hash('sha256', $otpCode);
                        $expiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));

                        // Invalidate existing unused OTPs
                        $pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE user_id = :uid AND purpose = 'login' AND is_used = 0")
                            ->execute([':uid' => $user['user_id']]);

                        // Insert new OTP
                        $otpStmt = $pdo->prepare("
                            INSERT INTO otp_verifications (user_id, otp_code_hash, purpose, expires_at, attempts, is_used, otp_last_sent_at, created_at)
                            VALUES (:uid, :hash, 'login', :expires, 0, 0, NOW(), NOW())
                        ");
                        $otpStmt->execute([
                            ':uid'     => $user['user_id'],
                            ':hash'    => $otpHash,
                            ':expires' => $expiresAt
                        ]);

                        logAudit($user['user_id'], 'OTP_SENT');

                        // Send OTP email
                        $mailSent = MailerService::sendOtpEmail($user['email'], $user['full_name'] ?? 'User', $otpCode);

                        if (!$mailSent) {
                            if (($_ENV['APP_ENV'] ?? 'development') === 'development') {
                                setFlash('warning', 'OTP generated, but email sending failed. Please check app error logs or click Resend.');
                            } else {
                                setFlash('warning', 'OTP generated, but email sending failed. Please check app error logs or click Resend.');
                            }
                        } else {
                            setFlash('info', 'A 6-digit OTP code has been sent to your email address.');
                        }

                        header('Location: ' . baseUrl('verify-otp.php'));
                        exit;
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - IMD Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= baseUrl('assets/css/imd-auth.css') ?>">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
    <!-- Header -->
    <header class="portal-header">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <a href="<?= baseUrl() ?>" class="portal-brand fs-4">IMD Training Division Portal</a>
                <div class="gov-emblem-text">India Meteorological Department | Govt. of India</div>
            </div>
            <a href="<?= baseUrl('register.php') ?>" class="btn btn-outline-light btn-sm">Register</a>
        </div>
    </header>

    <main class="container my-auto py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card card-auth p-4">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h3 class="card-title text-imd-navy fw-bold">Portal Login</h3>
                            <p class="text-muted small">Step 1 of 2: Password Authentication</p>
                        </div>

                        <?= displayFlash() ?>

                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= e($error) ?>
                            </div>
                        <?php endif; ?>

                        <form action="login.php" method="POST">
                            <?= csrfField() ?>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-imd w-100 py-2 fs-5">Continue to OTP</button>
                            </div>
                        </form>
                        <div class="text-center mt-3"><a href="<?= baseUrl('forgot-password.php') ?>">Forgot Password?</a></div>

                        <hr class="my-4">

                        <div class="text-center">
                            <span class="text-muted">Don't have an account?</span>
                            <a href="<?= baseUrl('register.php') ?>" class="text-imd-navy fw-bold text-decoration-none">Register here</a>
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
</body>
</html>
