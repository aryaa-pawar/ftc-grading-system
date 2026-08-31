<?php
require_once __DIR__ . '/config/database.php'; require_once __DIR__ . '/includes/csrf.php'; require_once __DIR__ . '/includes/functions.php';
$pdo = getDbConnection(); $error = null;
if (empty($_SESSION['password_reset_user_id']) || empty($_SESSION['password_reset_otp_id']) || ($_SESSION['password_reset_expires'] ?? 0) < time()) { header('Location: ' . baseUrl('forgot-password.php')); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrf(); $input = trim($_POST['otp_code'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM otp_verifications WHERE otp_id = :oid AND user_id = :uid AND purpose = 'password_reset' AND is_used = 0 LIMIT 1");
    $stmt->execute([':oid' => $_SESSION['password_reset_otp_id'], ':uid' => $_SESSION['password_reset_user_id']]); $row = $stmt->fetch();
    if (!$row || strtotime($row['expires_at']) < time()) $error = 'This OTP is invalid or expired.';
    elseif ((int)$row['attempts'] >= 3 || !preg_match('/^\d{6}$/', $input) || !hash_equals($row['otp_code_hash'], hash('sha256', $input))) {
        if ($row) $pdo->prepare('UPDATE otp_verifications SET attempts = attempts + 1, is_used = IF(attempts + 1 >= 3, 1, is_used) WHERE otp_id = :oid AND purpose = \'password_reset\'')->execute([':oid' => $row['otp_id']]);
        $error = 'This OTP is invalid or expired.';
    } else { $pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE otp_id = :oid AND purpose = 'password_reset'")->execute([':oid' => $row['otp_id']]); $_SESSION['password_reset_verified'] = true; header('Location: ' . baseUrl('reset-password.php')); exit; }
}
?><!DOCTYPE html><html><body><h2>Verify Password Reset</h2><?php if ($error): ?><p><?= e($error) ?></p><?php endif; ?><form method="post"><?= csrfField() ?><input name="otp_code" maxlength="6" required><button type="submit">Verify OTP</button></form></body></html>
