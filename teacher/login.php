<?php

require_once __DIR__ . '/../login.php';

$errorMsg   = '';
$successMsg = '';

if (isset($_GET['error']))  $errorMsg   = htmlspecialchars($_GET['error']);
if (isset($_GET['msg']))    $successMsg = htmlspecialchars($_GET['msg']);

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $errorMsg = 'Please enter both email and password.';
    } else {
        try {
            $stmt = $pdo->prepare("\n                SELECT u.*, r.role_name, p.full_name, f.faculty_id, f.faculty_name\n                FROM users u\n                JOIN roles r ON r.role_id = u.role_id\n                LEFT JOIN profiles p ON p.user_id = u.user_id\n                LEFT JOIN faculties f ON f.user_id = u.user_id\n                WHERE u.email = :email\n                  AND (f.is_deleted = 0 OR f.is_deleted IS NULL OR f.faculty_id IS NULL)\n                LIMIT 1\n            ");
            $stmt->execute([':email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $errorMsg = 'Invalid email or password. Please try again.';
            } elseif ($user['account_status'] !== 'active') {
                $errorMsg = 'This account is not active. Please contact an administrator.';
            } else {
                $_SESSION['pending_2fa_user_id'] = (int)$user['user_id'];
                $_SESSION['pending_2fa_email'] = $user['email'];
                $_SESSION['pending_2fa_name'] = $user['full_name'] ?? $user['faculty_name'] ?? 'User';
                $pdo->prepare("UPDATE otp_verifications SET is_used = 1 WHERE user_id = ? AND purpose = 'login' AND is_used = 0")
                    ->execute([(int)$user['user_id']]);
                $otpCode = sprintf('%06d', random_int(0, 999999));
                $pdo->prepare("INSERT INTO otp_verifications (user_id, otp_code_hash, purpose, expires_at, otp_last_sent_at, created_at) VALUES (?, ?, 'login', DATE_ADD(NOW(), INTERVAL 5 MINUTE), NOW(), NOW())")
                    ->execute([(int)$user['user_id'], hash('sha256', $otpCode)]);
                require_once __DIR__ . '/../imdAuth/services/MailerService.php';
                $sent = MailerService::sendOtpEmail($user['email'], $user['full_name'] ?? 'User', $otpCode);
                if (!$sent && (($_ENV['APP_ENV'] ?? 'development') === 'development')) {
                    $successMsg = 'OTP email failed. Please check app error logs or try again.';
                }
                auth_redirect('teacher/verify_otp.php');
            }
        } catch (PDOException $e) {
            $errorMsg = 'Database error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Login — FTC Grading System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f2942 0%, #1a4a7a 50%, #0d3460 100%);
            position: relative;
            overflow: hidden;
        }

        /* Subtle animated background circles */
        body::before, body::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            opacity: 0.08;
            background: #ffffff;
            animation: float 8s ease-in-out infinite;
        }
        body::before { width: 500px; height: 500px; top: -150px; right: -100px; animation-delay: 0s; }
        body::after  { width: 350px; height: 350px; bottom: -100px; left: -80px; animation-delay: 4s; }

        @keyframes float {
            0%, 100% { transform: translateY(0px) scale(1); }
            50%       { transform: translateY(-20px) scale(1.02); }
        }

        .login-wrapper {
            width: 100%;
            max-width: 440px;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        /* Logo / top emblem */
        .login-brand {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-brand .emblem {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #3b9af8, #1565c0);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 24px rgba(59,154,248,0.35);
            margin-bottom: 14px;
        }
        .login-brand .emblem svg { width: 32px; height: 32px; fill: #fff; }
        .login-brand h1 {
            color: #ffffff;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .login-brand p {
            color: rgba(255,255,255,0.6);
            font-size: 13px;
            margin-top: 4px;
        }

        /* Card */
        .login-card {
            background: rgba(255,255,255,0.97);
            border-radius: 20px;
            padding: 36px 36px 32px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.3), 0 1px 0 rgba(255,255,255,0.15) inset;
            backdrop-filter: blur(10px);
        }

        .card-title {
            font-size: 22px;
            font-weight: 700;
            color: #0f2942;
            margin-bottom: 4px;
        }
        .card-subtitle {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 28px;
        }

        /* Alert messages */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13.5px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .alert-error {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .alert-success {
            background: #f0fdf4;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        /* Form */
        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 7px;
        }
        .input-wrap {
            position: relative;
        }
        .input-wrap .icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            pointer-events: none;
            display: flex;
        }
        .input-wrap .icon svg { width: 18px; height: 18px; }
        .form-control {
            width: 100%;
            padding: 12px 14px 12px 44px;
            border: 1.5px solid #e5e7eb;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            color: #111827;
            background: #f9fafb;
            transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
            outline: none;
        }
        .form-control:focus {
            border-color: #3b9af8;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(59,154,248,0.15);
        }

        /* Show/hide password toggle */
        .pwd-toggle {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            display: flex;
            padding: 0;
            transition: color 0.2s;
        }
        .pwd-toggle:hover { color: #4b5563; }
        .pwd-toggle svg { width: 18px; height: 18px; }

        /* Submit button */
        .btn-login {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #1a5fa8, #1976d2);
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            font-family: inherit;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            letter-spacing: 0.2px;
            transition: opacity 0.2s, transform 0.15s, box-shadow 0.2s;
            box-shadow: 0 4px 16px rgba(25,118,210,0.35);
            margin-top: 6px;
        }
        .btn-login:hover {
            opacity: 0.92;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(25,118,210,0.45);
        }
        .btn-login:active { transform: translateY(0); }

        /* Footer note */
        .login-note {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: rgba(255,255,255,0.5);
        }

        /* Match the main portal login shell. */
        body { background: #f4f6f9 !important; align-items: stretch !important; overflow: auto !important; }
        body::before, body::after { display: none !important; }
        .login-wrapper { max-width: none; padding: 0; }
        .login-brand { text-align: left; margin: 0; padding: 1rem max(20px, calc((100% - 1140px) / 2)); background: linear-gradient(135deg, #003366 0%, #001f3f 100%); border-bottom: 4px solid #D4AF37; }
        .login-brand .emblem { display: none; }
        .login-brand h1 { color: #fff; font-size: 1.25rem; }
        .login-brand p { color: #D4AF37; text-transform: uppercase; letter-spacing: 1px; }
        .login-card { width: min(100% - 30px, 540px); margin: 3rem auto; padding: 1.5rem; border-radius: 8px; border-top: 4px solid #003366; box-shadow: 0 4px 15px rgba(0,0,0,.08); backdrop-filter: none; }
        .card-title { font-size: 1.5rem; color: #003366; }
        .card-subtitle { margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1rem; }
        .form-control { padding: .375rem .75rem; border: 1px solid #ced4da; border-radius: .375rem; background: #fff; }
        .form-control:focus { border-color: #86b7fe; box-shadow: 0 0 0 .25rem rgba(13,110,253,.25); }
        .input-wrap .icon { display: none; }
        .btn-login { margin-top: 1rem; padding: .5rem 1rem; background: #003366; border-radius: .375rem; box-shadow: none; }
        .login-note { margin: 0; padding: 1rem; color: #777; }
    </style>
</head>
<body>

<div class="login-wrapper">

    <!-- Brand -->
    <div class="login-brand">
        <div class="emblem">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zm0 12.5L4.5 11.2 3 12l9 5 9-5-1.5-.8L12 15.5zM3 17l9 5 9-5-1.5-.8L12 19.5 4.5 15.2 3 16v1z"/>
            </svg>
        </div>
        <h1>FTC Grading System</h1>
        <p>Faculty Training Centre — IMD</p>
    </div>

    <!-- Card -->
    <div class="login-card">
        <div class="card-title">Faculty Login</div>
        <div class="card-subtitle">Enter your credentials to access the faculty panel</div>

        <?php if ($errorMsg): ?>
            <div class="alert alert-error">
                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                <?php echo $errorMsg; ?>
            </div>
        <?php endif; ?>

        <?php if ($successMsg): ?>
            <div class="alert alert-success">
                <svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z"/></svg>
                <?php echo $successMsg; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="loginForm" novalidate>

            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-wrap">
                    <span class="icon">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"/></svg>
                    </span>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control"
                        placeholder="yourname@imd.gov.in"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required
                        autocomplete="email"
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrap">
                    <span class="icon">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                    </span>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        class="form-control"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                    <button type="button" class="pwd-toggle" id="togglePwd" title="Show/hide password">
                        <svg id="eyeIcon" viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login" id="loginBtn">
                Sign In to Faculty Panel
            </button>

        </form>
    </div>

    <div class="login-note">
        &copy; <?php echo date('Y'); ?> Faculty Training Centre, IMD &nbsp;|&nbsp; Admin contact for credential issues
    </div>

</div>

<script>
    // Toggle password visibility
    document.getElementById('togglePwd').addEventListener('click', function () {
        const pwd = document.getElementById('password');
        const icon = document.getElementById('eyeIcon');
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.innerHTML = '<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>';
        } else {
            pwd.type = 'password';
            icon.innerHTML = '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>';
        }
    });

    // Subtle loading state on submit
    document.getElementById('loginForm').addEventListener('submit', function () {
        const btn = document.getElementById('loginBtn');
        btn.textContent = 'Signing in…';
        btn.disabled = true;
    });
</script>

</body>
</html>
