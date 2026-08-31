<?php
$currentUri = $_SERVER['REQUEST_URI'] ?? '';
$currentPath = parse_url($currentUri, PHP_URL_PATH) ?? '';
$scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
$appBase = preg_replace('#/(admin|teacher|verification|includes)/.*$#i', '', $scriptPath);
if ($appBase === '/' || $appBase === '\\') {
    $appBase = '';
}
$teacherBase = $appBase . '/teacher';

$facultyMenuActive = static function (string $path) use ($currentPath): string {
    return strpos($currentPath, $path) !== false ? 'active' : '';
};
?>

<style>
    .layout > .sidebar,
    .page-layout > .sidebar {
        width: 260px !important;
        min-width: 260px !important;
        flex-shrink: 0;
    }

    .layout > .content,
    .page-layout > .content {
        flex: 1 1 auto;
        min-width: 0;
        padding: 40px !important;
        overflow-x: auto;
    }

    .sidebar .sidebar-title {
        font-size: 22px !important;
        padding: 25px !important;
    }

    .sidebar > a {
        font-size: 16px !important;
        line-height: 20px;
        padding: 16px 25px !important;
    }

    .sidebar a.active {
        background: #eaf2f8;
        color: #1f4e79;
        font-weight: bold;
        border-left: 4px solid #1f4e79;
        padding-left: 21px !important;
    }
</style>

<aside class="sidebar">
    <h3 class="sidebar-title" style="margin:0;">
        Faculty Panel
    </h3>

    <a href="<?= htmlspecialchars($teacherBase . '/dashboard.php') ?>" class="<?= $facultyMenuActive('/teacher/dashboard.php') ?>">
        Dashboard
    </a>

    <a href="<?= htmlspecialchars($teacherBase . '/batches.php') ?>" class="<?= $facultyMenuActive('/teacher/batches.php') ?>">
        My Batches
    </a>

    <a href="<?= htmlspecialchars($teacherBase . '/exams/index.php') ?>" class="<?= $facultyMenuActive('/teacher/exams/index.php') ?>">
        Marks Entry
    </a>

    <a href="<?= htmlspecialchars($appBase . '/guide/profile.php') ?>" class="<?= $facultyMenuActive('/guide/profile.php') ?>">
        My Profile
    </a>

    <a href="<?= htmlspecialchars($teacherBase . '/logout.php') ?>" style="color:#c0392b; margin-top:auto;">
        &#x2192; Logout
    </a>
</aside>
