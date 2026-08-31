<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/services/MailerService.php';

$pdo = getDbConnection();
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrf();
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare("SELECT u.user_id, u.email, p.full_name FROM users u LEFT JOIN profiles p ON p.user_id = u.user_id WHERE u.email = :email AND u.account_status = 'active' LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if ($user) {
            $pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE user_id = :uid AND purpose = 'password_reset' AND is_used = 0")->execute([':uid' => $user['user_id']]);
            $otp = sprintf('%06d', random_int(0, 999999));
            $stmt = $pdo->prepare("INSERT INTO otp_verifications (user_id, otp_code_hash, purpose, expires_at, attempts, is_used, otp_last_sent_at, created_at) VALUES (:uid, :hash, 'password_reset', :expires, 0, 0, NOW(), NOW())");
            $stmt->execute([':uid' => $user['user_id'], ':hash' => hash('sha256', $otp), ':expires' => date('Y-m-d H:i:s', strtotime('+5 minutes'))]);
            if (MailerService::sendPasswordResetOtp($user['email'], $user['full_name'] ?: 'User', $otp)) {
                $_SESSION['password_reset_user_id'] = (int)$user['user_id'];
                $_SESSION['password_reset_otp_id'] = (int)$pdo->lastInsertId();
                $_SESSION['password_reset_expires'] = time() + 300;
                header('Location: ' . baseUrl('verify-password-reset.php')); exit;
            }
        }
    }
    $message = 'If an account exists for this email, a password reset OTP has been sent.';
}
?><!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Forgot Password - IMD Portal</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="<?= baseUrl('assets/css/imd-auth.css') ?>"></head>
<body class="bg-light d-flex flex-column min-vh-100"><header class="portal-header"><div class="container"><a href="<?= baseUrl() ?>" class="portal-brand fs-4">IMD Training Division Portal</a><div class="gov-emblem-text">India Meteorological Department | Govt. of India</div></div></header><main class="container my-auto py-5"><div class="row justify-content-center"><div class="col-md-6 col-lg-5"><div class="card card-auth p-4"><div class="card-body"><div class="text-center mb-4"><h3 class="card-title text-imd-navy fw-bold">Forgot Password</h3><p class="text-muted small">Enter your registered email address to receive a password reset OTP.</p></div><?= displayFlash() ?><?php if ($message): ?><div class="alert alert-info" role="alert"><?= e($message) ?></div><?php endif; ?><form method="post"><?= csrfField() ?><div class="mb-4"><label for="email" class="form-label">Email Address</label><input type="email" class="form-control" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autofocus></div><button type="submit" class="btn btn-imd w-100 py-2 fs-5">Send OTP</button></form><div class="text-center mt-4"><a href="<?= baseUrl('login.php') ?>" class="text-imd-navy fw-bold text-decoration-none">Back to Login</a></div></div></div></div></div></main><footer class="text-center py-3 mt-auto"><small>&copy; <?= date('Y') ?> India Meteorological Department. Training Division Portal.</small></footer></body></html>
