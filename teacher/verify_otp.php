<?php

require_once __DIR__ . '/../includes/auth.php';

if (empty($_SESSION['pending_2fa_user_id'])) {
    auth_redirect('teacher/login.php?error=Session+expired');
}

$pdo = getDbConnection();
$userId = (int)$_SESSION['pending_2fa_user_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['otp_code'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM otp_verifications WHERE user_id = ? AND purpose = 'login' AND is_used = 0 ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    $otp = $stmt->fetch();

    if (!preg_match('/^\d{6}$/', $code) || !$otp || strtotime($otp['expires_at']) < time()) {
        $error = 'The OTP is invalid or expired.';
    } elseif (!hash_equals($otp['otp_code_hash'], hash('sha256', $code))) {
        $attempts = (int)$otp['attempts'] + 1;
        $pdo->prepare("UPDATE otp_verifications SET attempts = ?, is_used = ? WHERE otp_id = ? AND purpose = 'login'")
            ->execute([$attempts, $attempts >= 3 ? 1 : 0, $otp['otp_id']]);
        $error = $attempts >= 3 ? 'Too many attempts. Please log in again.' : 'Invalid OTP.';
    } else {
        $pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE otp_id = ? AND purpose = 'login'")->execute([$otp['otp_id']]);
        $stmt = $pdo->prepare('SELECT u.*, r.role_name, p.full_name, f.faculty_id, f.faculty_name FROM users u JOIN roles r ON r.role_id = u.role_id LEFT JOIN profiles p ON p.user_id = u.user_id LEFT JOIN faculties f ON f.user_id = u.user_id WHERE u.user_id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user || $user['account_status'] !== 'active') {
            $error = 'This account is no longer active.';
        } else {
            establish_user_session($user, $user['faculty_id'] ? $user : null);
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_email'], $_SESSION['pending_2fa_name']);
            auth_redirect($user['role_name'] === 'guide' ? 'teacher/dashboard.php' : 'admin/dashboard.php');
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Verify OTP - IMD Portal</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="<?= auth_base_path() ?>/assets/css/imd-auth.css"></head>
<body class="bg-light d-flex flex-column min-vh-100">
<header class="portal-header"><div class="container"><a href="<?= auth_base_path() ?>" class="portal-brand fs-4">IMD Training Division Portal</a><div class="gov-emblem-text">India Meteorological Department | Govt. of India</div></div></header>
<main class="container my-auto py-5"><div class="row justify-content-center"><div class="col-md-6 col-lg-5"><div class="card card-auth p-4"><div class="card-body"><div class="text-center mb-4"><h3 class="card-title text-imd-navy fw-bold">Verify OTP</h3><p class="text-muted small">Enter the OTP sent to your registered email address.</p></div><?php if ($error): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><form method="post"><div class="mb-4"><label for="otp_code" class="form-label text-center d-block fw-bold">6-digit OTP</label><input id="otp_code" name="otp_code" class="form-control form-control-lg text-center fs-2" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus></div><button type="submit" class="btn btn-imd w-100 py-2 fs-5">Verify OTP</button></form><div class="text-center mt-4"><a href="<?= auth_base_path() ?>/login.php" class="text-imd-navy fw-bold text-decoration-none">Back to Login</a></div></div></div></div></div></main><footer class="text-center py-3 mt-auto"><small>&copy; <?= date('Y') ?> India Meteorological Department. Training Division Portal.</small></footer>
</body>
</html>
