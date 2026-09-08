<?php

/*
|--------------------------------------------------------------------------
| FTC Grading & Tabulation Management System
| Admin Sidebar
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| CURRENT URL & BASE PATH
|--------------------------------------------------------------------------
*/

$currentUri = $_SERVER["REQUEST_URI"] ?? "";
$scriptPath = $_SERVER["SCRIPT_NAME"] ?? "";
$appBase = preg_replace('#/(admin|teacher|verification|includes)/.*$#i', '', $scriptPath);
if ($appBase === "/" || $appBase === "\\") {
    $appBase = "";
}
$adminBase = $appBase . "/admin";


/*
|--------------------------------------------------------------------------
| ACTIVE MENU FUNCTION
|--------------------------------------------------------------------------
*/

function adminMenuActive($folder)
{
    global $currentUri;

    if (
        strpos(
            $currentUri,
            "/admin/" . $folder . "/"
        ) !== false
    ) {
        return "active";
    }

    return "";
}


/*
|--------------------------------------------------------------------------
| DASHBOARD ACTIVE
|--------------------------------------------------------------------------
*/

$dashboardActive = "";
$scrutinyMarksActive = strpos($currentUri, "/admin/marks/edit_scrutiny.php") !== false ? "active" : "";
$scrutinyLogsActive = strpos($currentUri, "/admin/marks/scrutiny_logs.php") !== false ? "active" : "";
$marksManagementActive = $scrutinyMarksActive ? "" : adminMenuActive("marks");
$pendingApprovalsActive = "";
$manageUsersActive = "";
$auditLogsActive = "";

if (
    strpos(
        $currentUri,
        "/admin/dashboard.php"
    ) !== false
) {
    $dashboardActive = "active";
}

if (strpos($currentUri, "/admin/pending_approvals.php") !== false) {
    $pendingApprovalsActive = "active";
}

if (strpos($currentUri, "/admin/manage_users.php") !== false) {
    $manageUsersActive = "active";
}

if (strpos($currentUri, "/admin/audit_logs.php") !== false) {
    $auditLogsActive = "active";
}

?>

<style>

/* =========================================================
   ADMIN SIDEBAR
========================================================= */

.admin-sidebar {

    width: 255px;

    min-width: 255px;

    height: calc(100vh - 60px);

    background: #ffffff;

    border-right: 1px solid #dddddd;

    flex-shrink: 0;

    overflow-y: auto;

    overflow-x: hidden;
}


/* =========================================================
   SIDEBAR TITLE
========================================================= */

.admin-sidebar-title {

    padding: 22px 25px;

    color: #1f4e79;

    font-size: 23px;

    font-weight: bold;

    border-bottom: 1px solid #eeeeee;
}


/* =========================================================
   SECTION TITLE
========================================================= */

.admin-sidebar-section {

    padding: 16px 25px 7px;

    color: #888888;

    font-size: 12px;

    font-weight: bold;

    text-transform: uppercase;

    letter-spacing: 0.4px;
}


/* =========================================================
   SIDEBAR LINKS
========================================================= */

.admin-sidebar a {

    display: block;

    width: 100%;

    padding: 11px 25px;

    box-sizing: border-box;

    color: #222222;

    text-decoration: none;

    border-bottom: 1px solid #eeeeee;

    font-size: 15px;

    line-height: 20px;

    transition:
        background 0.2s,
        color 0.2s;
}


/* =========================================================
   HOVER
========================================================= */

.admin-sidebar a:hover {

    background: #f1f5f8;

    color: #1f4e79;
}


/* =========================================================
   ACTIVE
========================================================= */

.admin-sidebar a.active {

    background: #eaf2f8;

    color: #1f4e79;

    font-weight: bold;

    border-left: 4px solid #1f4e79;

    padding-left: 21px;
}

.admin-sidebar a.admin-logout {
    color: #c0392b;
    font-weight: bold;
    border-top: 1px solid #dddddd;
}

.admin-sidebar a.admin-logout:hover {
    background: #fdecea;
    color: #a12622;
}


/* =========================================================
   SCROLLBAR
========================================================= */

.admin-sidebar::-webkit-scrollbar {

    width: 6px;
}


.admin-sidebar::-webkit-scrollbar-track {

    background: #f5f5f5;
}


.admin-sidebar::-webkit-scrollbar-thumb {

    background: #cccccc;

    border-radius: 10px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 800px) {

    .admin-sidebar {

        width: 220px;

        min-width: 220px;
    }


    .admin-sidebar-title {

        padding: 20px;

        font-size: 20px;
    }


    .admin-sidebar-section {

        padding-left: 20px;
    }


    .admin-sidebar a {

        padding-left: 20px;

        font-size: 14px;
    }


    .admin-sidebar a.active {

        padding-left: 16px;
    }

}

</style>


<aside class="admin-sidebar" id="adminSidebar">


    <!-- =====================================================
         TITLE
    ====================================================== -->

    <div class="admin-sidebar-title">

        Admin Panel

    </div>



    <!-- =====================================================
         ADMINISTRATION
    ====================================================== -->

    <div class="admin-sidebar-section">

        Administration

    </div>

    <a
        href="<?php echo $adminBase; ?>/dashboard.php"
        class="<?php echo $dashboardActive; ?>"
    >

        Dashboard

    </a>

    <!-- =====================================================
         BATCH MANAGEMENT
    ====================================================== -->

    <a
        href="<?php echo $adminBase; ?>/batches/index.php"
        class="<?php echo adminMenuActive("batches"); ?>"
    >

        Batch Management

    </a>



    <!-- =====================================================
         STUDENT MANAGEMENT
    ====================================================== -->

    <a
        href="<?php echo $adminBase; ?>/students/index.php"
        class="<?php echo adminMenuActive("students"); ?>"
    >

        Student Management

    </a>



    <!-- =====================================================
         TEACHER MANAGEMENT
    ====================================================== -->

    <a
        href="<?php echo $adminBase; ?>/teachers/index.php"
        class="<?php echo adminMenuActive("teachers"); ?>"
    >

        Teacher Management

    </a>



    <!-- =====================================================
         SUBJECT MANAGEMENT
    ====================================================== -->

    <a
        href="<?php echo $adminBase; ?>/subjects/index.php"
        class="<?php echo adminMenuActive("subjects"); ?>"
    >

        Subject Management

    </a>



    <!-- =====================================================
         PAPER & GROUPING MANAGEMENT
    ====================================================== -->

    <a
        href="<?php echo $adminBase; ?>/papers/index.php"
        class="<?php echo adminMenuActive("papers"); ?>"
    >

        Paper &amp; Grouping Management

    </a>



    <!-- =====================================================
         ELECTIVE MANAGEMENT
    ====================================================== -->

    <a
        href="<?php echo $adminBase; ?>/electives/index.php"
        class="<?php echo adminMenuActive("electives"); ?>"
    >

        Elective Management

    </a>



    <!-- =====================================================
         FACULTY ASSIGNMENT
    ====================================================== -->

    <a
        href="<?php echo $adminBase; ?>/assignments/index.php"
        class="<?php echo adminMenuActive("assignments"); ?>"
    >

        Faculty Subject Assignment

    </a>



    <!-- =====================================================
         MARKS MANAGEMENT
    ====================================================== -->

    <a
        href="<?php echo $adminBase; ?>/marks/index.php"
        class="<?php echo $marksManagementActive; ?>"
    >

        Marks Management

    </a>

    <a
        href="<?php echo $adminBase; ?>/marks/full_edit.php"
    >

        Admin Full Marks Edit

    </a>

    <a
        href="<?php echo $adminBase; ?>/marks/edit_scrutiny.php"
        class="<?php echo $scrutinyMarksActive; ?>"
    >

        Edit Scrutiny Marks

    </a>

    <a
        href="<?php echo $adminBase; ?>/marks/scrutiny_logs.php"
        class="<?php echo $scrutinyLogsActive; ?>"
    >

        Scrutiny Edit Logs

    </a>



    <!-- =====================================================
         RESULTS
    ====================================================== -->

    <a
        href="<?php echo $adminBase; ?>/results/result_register.php"
        class="<?php echo adminMenuActive("results"); ?>"
    >

        Results

    </a>

    <a
        href="<?php echo $adminBase; ?>/results/certificates.php"
        class="<?php echo (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'certificates.php') ? 'active' : ''; ?>"
    >

        Certificate Panel

    </a>

    <?php if (!function_exists('has_permission') || has_permission('admin.access')): ?>
    <div class="admin-sidebar-section">Identity & Access</div>
    <a href="<?php echo $adminBase; ?>/pending_approvals.php" class="<?php echo $pendingApprovalsActive; ?>">Pending Approvals</a>
    <a href="<?php echo $adminBase; ?>/manage_users.php" class="<?php echo $manageUsersActive; ?>">Manage Users</a>
    <?php if (!function_exists('has_permission') || has_permission('users.manage')): ?>
    <a href="<?php echo $adminBase; ?>/audit_logs.php" class="<?php echo $auditLogsActive; ?>">Identity Audit Logs</a>
    <?php endif; ?>
    <?php endif; ?>

    <a
        href="<?php echo htmlspecialchars($appBase . '/logout.php', ENT_QUOTES, 'UTF-8'); ?>"
        class="admin-logout"
    >

        Logout

    </a>


</aside>

<script>
    (function () {
        const sidebar = document.getElementById('adminSidebar');
        const storageKey = 'adminSidebarScrollTop';

        if (!sidebar) {
            return;
        }

        const savedScrollTop = sessionStorage.getItem(storageKey);
        if (savedScrollTop !== null) {
            sidebar.scrollTop = Number(savedScrollTop);
        }

        const saveScrollPosition = function () {
            sessionStorage.setItem(storageKey, String(sidebar.scrollTop));
        };

        sidebar.addEventListener('scroll', saveScrollPosition, { passive: true });
        sidebar.addEventListener('click', saveScrollPosition);
        window.addEventListener('beforeunload', saveScrollPosition);
    }());
</script>
