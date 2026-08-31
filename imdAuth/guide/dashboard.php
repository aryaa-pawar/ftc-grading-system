<?php
// guide/dashboard.php - Guide / Supervisor Portal Dashboard

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = requireAuth(['guide']);

$pdo = getDbConnection();
$stmt = $pdo->prepare("
    SELECT u.*, p.* 
    FROM users u 
    JOIN profiles p ON u.user_id = p.user_id 
    WHERE u.user_id = :uid
");
$stmt->execute([':uid' => $auth['user_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guide Dashboard - IMD Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= baseUrl('assets/css/style.css') ?>">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-imd-navy shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= baseUrl('guide/dashboard.php') ?>">IMD Faculty & Guide Portal</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link active" href="<?= baseUrl('guide/dashboard.php') ?>">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= baseUrl('guide/profile.php') ?>">My Profile</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center text-white gap-3">
                    <span class="small">Welcome, <strong><?= e($user['full_name']) ?></strong> (Guide)</span>
                    <a href="<?= baseUrl('logout.php') ?>" class="btn btn-outline-light btn-sm">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container my-5">
        <?= displayFlash() ?>

        <div class="row g-4">
            <!-- Welcome Card -->
            <div class="col-md-8">
                <div class="card card-dashboard p-4 mb-4">
                    <div class="d-flex align-items-center gap-3">
                        <?php if (!empty($user['photo_path'])): ?>
                            <img src="<?= baseUrl('serve_photo.php?file=' . urlencode($user['photo_path'])) ?>" alt="Profile Photo" class="rounded-circle border" width="80" height="80" style="object-fit: cover;">
                        <?php else: ?>
                            <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold fs-3" style="width: 80px; height: 80px;">
                                <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div>
                            <h3 class="mb-1 text-imd-navy fw-bold"><?= e($user['full_name']) ?></h3>
                            <p class="text-muted mb-1"><?= e($user['department'] ?? 'IMD Division') ?></p>
                            <div>
                                <span class="badge badge-active">Account Status: Active</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card bg-white border-info shadow-sm p-4">
                    <h5 class="card-title text-imd-navy">Research & Guidance Overview</h5>
                    <p class="text-muted">Welcome to the IMD Research Portal. Further features (dissertation tracking and project evaluation) will be unlocked in Phase 2.</p>
                </div>
            </div>

            <!-- Quick Profile Overview -->
            <div class="col-md-4">
                <div class="card card-dashboard p-3">
                    <h5 class="card-title text-imd-navy border-bottom pb-2">Guide Profile Details</h5>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Email:</span>
                            <strong class="text-break"><?= e($user['email']) ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Phone:</span>
                            <strong><?= e($user['phone'] ?? 'Not set') ?></strong>
                        </li>
                        <li class="list-group-item d-flex justify-content-between px-0">
                            <span class="text-muted">Department:</span>
                            <strong><?= e($user['department'] ?? 'N/A') ?></strong>
                        </li>
                    </ul>
                    <a href="<?= baseUrl('guide/profile.php') ?>" class="btn btn-imd btn-sm mt-3 w-100">Edit Profile</a>
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
