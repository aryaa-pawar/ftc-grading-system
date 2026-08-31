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
?><!DOCTYPE html><html><body><h2>Forgot Password</h2><?php if ($message): ?><p><?= e($message) ?></p><?php endif; ?><form method="post"><?= csrfField() ?><input type="email" name="email" required><button type="submit">Send OTP</button></form><a href="<?= baseUrl('login.php') ?>">Back to Login</a></body></html>
