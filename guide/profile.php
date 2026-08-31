<?php
// guide/profile.php - View & Edit Guide Profile

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_guard.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

$auth = requireAuth(['guide']);
$userId = $auth['user_id'];
$pdo = getDbConnection();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrf();

    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $department = trim($_POST['department'] ?? '');

    if (empty($fullName)) {
        $errors[] = "Full Name is required.";
    }

    // Photo Upload Handling
    $photoFilename = null;
    if (isset($_FILES['photo_upload']) && $_FILES['photo_upload']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['photo_upload'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading photo. Please try again.";
        } elseif ($file['size'] > 2 * 1024 * 1024) { // 2MB Limit
            $errors[] = "Profile photo size must not exceed 2MB.";
        } else {
            // MIME Type Validation via finfo
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png'
            ];

            if (!isset($allowedMimes[$mimeType])) {
                $errors[] = "Invalid image format. Only JPG and PNG images are allowed.";
            } else {
                $ext = $allowedMimes[$mimeType];
                $randomName = bin2hex(random_bytes(16)) . '.' . $ext;
                $targetDir = __DIR__ . '/../storage/uploads/profile_photos/';
                $targetFile = $targetDir . $randomName;

                if (!move_uploaded_file($file['tmp_name'], $targetFile)) {
                    $errors[] = "Failed to save uploaded photo.";
                } else {
                    $photoFilename = $randomName;
                }
            }
        }
    }

    if (empty($errors)) {
        try {
            if ($photoFilename) {
                $stmt = $pdo->prepare("
                    UPDATE profiles 
                    SET full_name = :name, phone = :phone, address = :addr, dob = :dob, department = :dept, photo_path = :photo
                    WHERE user_id = :uid
                ");
                $stmt->execute([
                    ':name'  => $fullName,
                    ':phone' => $phone,
                    ':addr'  => $address ?: null,
                    ':dob'   => $dob ?: null,
                    ':dept'  => $department ?: null,
                    ':photo' => $photoFilename,
                    ':uid'   => $userId
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE profiles 
                    SET full_name = :name, phone = :phone, address = :addr, dob = :dob, department = :dept
                    WHERE user_id = :uid
                ");
                $stmt->execute([
                    ':name'  => $fullName,
                    ':phone' => $phone,
                    ':addr'  => $address ?: null,
                    ':dob'   => $dob ?: null,
                    ':dept'  => $department ?: null,
                    ':uid'   => $userId
                ]);
            }

            $_SESSION['user_name'] = $fullName;
            logAudit($userId, 'PROFILE_UPDATED');
            setFlash('success', 'Profile updated successfully.');
            header('Location: ' . baseUrl('guide/profile.php'));
            exit;

        } catch (Exception $e) {
            error_log("Guide Profile Update Error: " . $e->getMessage(), 3, __DIR__ . '/../storage/logs/app_error.log');
            $errors[] = "Database update failed.";
        }
    }
}

// Fetch Profile Data
$stmt = $pdo->prepare("
    SELECT u.email, u.account_status, p.* 
    FROM users u 
    JOIN profiles p ON u.user_id = p.user_id 
    WHERE u.user_id = :uid
");
$stmt->execute([':uid' => $userId]);
$profile = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - IMD Guide Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= baseUrl('assets/css/imd-auth.css') ?>">
</head>
<body class="bg-light d-flex flex-column min-vh-100">
    <nav class="navbar navbar-expand-lg navbar-dark bg-imd-navy shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="<?= baseUrl('teacher/dashboard.php') ?>">IMD Faculty & Guide Portal</a>
            <div class="collapse navbar-collapse">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?= baseUrl('teacher/dashboard.php') ?>">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="<?= baseUrl('guide/profile.php') ?>">My Profile</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center text-white gap-3">
                    <a href="<?= baseUrl('logout.php') ?>" class="btn btn-outline-light btn-sm">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container my-5">
        <?= displayFlash() ?>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card card-dashboard p-4">
                    <h3 class="card-title text-imd-navy mb-4 border-bottom pb-2">Guide Profile Management</h3>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= e($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form action="profile.php" method="POST" enctype="multipart/form-data">
                        <?= csrfField() ?>

                        <div class="text-center mb-4">
                            <?php if (!empty($profile['photo_path'])): ?>
                                <img src="<?= baseUrl('serve_photo.php?file=' . urlencode($profile['photo_path'])) ?>" alt="Profile Photo" class="rounded-circle border mb-2" width="120" height="120" style="object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center mx-auto mb-2 fw-bold fs-1" style="width: 120px; height: 120px;">
                                    <?= strtoupper(substr($profile['full_name'], 0, 1)) ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <label for="photo_upload" class="form-label text-imd-navy small fw-bold">Change Profile Photo (JPG/PNG, Max 2MB)</label>
                                <input type="file" class="form-control form-control-sm mx-auto" id="photo_upload" name="photo_upload" accept="image/jpeg,image/png" style="max-width: 300px;">
                            </div>
                        </div>

                        <!-- Read Only System Fields -->
                        <div class="row bg-light p-3 rounded mb-4">
                            <div class="col-md-6 mb-2">
                                <label class="form-label text-muted small mb-0">Email Address (Read-only)</label>
                                <div class="fw-bold"><?= e($profile['email']) ?></div>
                            </div>
                            <div class="col-md-6 mb-2">
                                <label class="form-label text-muted small mb-0">Account Role (Read-only)</label>
                                <div class="fw-bold text-imd-navy">Research Guide / Supervisor</div>
                            </div>
                        </div>

                        <!-- Editable Fields -->
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?= e($profile['full_name']) ?>" required>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?= e($profile['phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="department" class="form-label">Department / Division</label>
                                <input type="text" class="form-control" id="department" name="department" value="<?= e($profile['department'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="dob" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="dob" name="dob" value="<?= e($profile['dob'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?= e($profile['address'] ?? '') ?></textarea>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="<?= baseUrl('teacher/dashboard.php') ?>" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-imd px-4">Save Changes</button>
                        </div>
                    </form>
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

