<?php
// register.php - Public Guide / Supervisor Registration

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect logged in users
if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['role_name'] ?? '';
    if ($role === 'guide') header('Location: ' . baseUrl('teacher/dashboard.php'));
    else header('Location: ' . baseUrl('admin/dashboard.php'));
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $phone = trim($_POST['phone'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    // Validation
    if (empty($fullName)) {
        $errors[] = "Full Name is required.";
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "A valid Email address is required.";
    }
    if (empty($department)) {
        $errors[] = "Department / Division is required.";
    }
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    if ($password !== $passwordConfirm) {
        $errors[] = "Password confirmation does not match.";
    }

    // Check duplicate email
    if (empty($errors)) {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = "An account with this email address already exists.";
        }
    }

    // Insert the pending account atomically.
    if (empty($errors)) {
        try {
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->beginTransaction();
            $roleId = $pdo->query("SELECT role_id FROM roles WHERE role_name = 'guide' LIMIT 1")->fetchColumn();
            $stmtUser = $pdo->prepare("INSERT INTO users (email, password_hash, role_id, account_status) VALUES (:email, :pass, :role_id, 'pending_approval')");
            $stmtUser->execute([':email' => $email, ':pass' => $passwordHash, ':role_id' => $roleId]);
            $stmtProfile = $pdo->prepare('INSERT INTO profiles (user_id, full_name, phone, department) VALUES (:uid, :name, :phone, :dept)');
            $userId = (int)$pdo->lastInsertId();
            $stmtProfile->execute([':uid' => $userId, ':name' => $fullName, ':phone' => $phone, ':dept' => $department]);
            $pdo->commit();

            $success = true;

        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            error_log("Registration error: " . $e->getMessage(), 3, __DIR__ . '/storage/logs/app_error.log');
            $errors[] = "Registration failed due to a system error. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty / Lecturer Registration - IMD Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= baseUrl('assets/css/imd-auth.css') ?>">
</head>
<body class="bg-light">
    <!-- Header -->
    <header class="portal-header">
        <div class="container d-flex justify-content-between align-items-center">
            <div>
                <a href="<?= baseUrl() ?>" class="portal-brand fs-4">IMD Training Division Portal</a>
                <div class="gov-emblem-text">India Meteorological Department | Govt. of India</div>
            </div>
            <a href="<?= baseUrl('login.php') ?>" class="btn btn-outline-light btn-sm">Log In</a>
        </div>
    </header>

    <main class="container my-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">

                <?php if ($success): ?>
                    <div class="card card-auth p-4 text-center">
                        <div class="card-body">
                            <div class="mb-3">
                                <span class="badge bg-warning text-dark fs-6 px-3 py-2">Registration Pending Approval</span>
                            </div>
                            <h3 class="card-title text-imd-navy">Thank You for Registering!</h3>
                            <p class="card-text text-muted my-3">
                                Your Faculty / Lecturer registration account has been successfully submitted and is currently <strong>pending approval</strong> by an IMD Administrator.
                            </p>
                            <p class="text-muted small">
                                Once approved, you will receive an email confirmation and can log in with your credentials.
                            </p>
                            <a href="<?= baseUrl('login.php') ?>" class="btn btn-imd mt-3 px-4">Return to Login</a>
                        </div>
                    </div>
                <?php else: ?>

                    <div class="card card-auth p-4">
                        <div class="card-body">
                            <h3 class="card-title text-imd-navy mb-1 fw-bold">Faculty / Lecturer Registration</h3>
                            <p class="text-muted small mb-4">IMD Scientist & Faculty / Lecturer Portal Registration</p>

                            <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0 ps-3">
                                        <?php foreach ($errors as $err): ?>
                                            <li><?= e($err) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <form action="register.php" method="POST">
                                <?= csrfField() ?>

                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" required>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" id="phone" name="phone" value="<?= e($_POST['phone'] ?? '') ?>">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="department" class="form-label">Department / Division <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="department" name="department" placeholder="e.g. Satellite Meteorology / NWP Division" value="<?= e($_POST['department'] ?? '') ?>" required>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="password" name="password" minlength="8" required>
                                        <div class="form-text">Minimum 8 characters.</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="password_confirm" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                        <input type="password" class="form-control" id="password_confirm" name="password_confirm" minlength="8" required>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-imd w-100 py-2 fs-5">Submit Registration</button>
                                </div>
                            </form>

                            <div class="text-center mt-4">
                                <span class="text-muted">Already registered?</span> <a href="<?= baseUrl('login.php') ?>" class="text-imd-navy fw-bold text-decoration-none">Log In here</a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </main>

    <footer class="text-center py-3">
        <div class="container">
            <small>&copy; <?= date('Y') ?> India Meteorological Department. Training Division Portal.</small>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= baseUrl('assets/js/imd-auth.js') ?>"></script>
</body>
</html>
