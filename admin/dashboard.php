<?php

/*
|--------------------------------------------------------------------------
| FTC Grading & Tabulation Management System
| ADMIN DASHBOARD
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';
require_once "../config/database.php";

$studentCount = 0;
$teacherCount = 0;
$batchCount = 0;
$subjectCount = 0;
$electiveCount = 0;
$assignmentCount = 0;
$adminUnreadNotificationCount = 0;
$adminNotifications = [];
$errorMessage = "";

try {

    /*
    |--------------------------------------------------------------------------
    | TOTAL ACTIVE STUDENTS
    |--------------------------------------------------------------------------
    */
    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM students
        WHERE is_deleted = FALSE
    ");

    $studentCount = (int) $stmt->fetchColumn();

    /*
    |--------------------------------------------------------------------------
        SELECT COUNT(*)
        FROM students
        WHERE is_deleted = FALSE
    ");

    $studentCount = (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL ACTIVE TEACHERS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM faculties
        WHERE is_deleted = FALSE
    ");

    $teacherCount = (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL BATCHES
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM batches
    ");

    $batchCount = (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL SUBJECTS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM subjects
    ");

    $subjectCount = (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL ELECTIVE SUBJECTS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM subjects
        WHERE is_elective = TRUE
    ");

    $electiveCount = (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | TOTAL FACULTY ASSIGNMENTS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM subject_faculty_mapping
    ");

    $assignmentCount = (int) $stmt->fetchColumn();

    $adminUnreadNotificationCount = (int)$pdo->query("SELECT COUNT(*) FROM admin_notifications WHERE is_read = 0")->fetchColumn();
    $adminNotifications = $pdo->query("
        SELECT
            an.verification_status,
            an.notification_id,
            an.created_at,
            an.entry_id,
            an.student_id,
            s.student_name,
            s.roll_no,
            b.batch_name,
            fme.subject_id,
            fme.assessment_type_id,
            sub.subject_name,
            at.assessment_type_name,
            fme.marks_obtained
        FROM admin_notifications an
        INNER JOIN students s ON s.student_id = an.student_id
        INNER JOIN batches b ON b.batch_id = an.batch_id
        LEFT JOIN faculty_marks_entry fme ON fme.entry_id = an.entry_id
        LEFT JOIN subjects sub ON sub.subject_id = fme.subject_id
        LEFT JOIN assessment_types at ON at.assessment_type_id = fme.assessment_type_id
        ORDER BY an.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $errorMessage = $e->getMessage();

}

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Admin Dashboard
    </title>

    <link rel="stylesheet" href="../assets/css/admin-shell.css">


    <style>

        /* =====================================================
           GLOBAL
        ====================================================== */

        * {
            box-sizing: border-box;
        }


        html,
        body {

            margin: 0;

            padding: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f6f8;

            color: #222222;

        }


        body {

            min-height: 100vh;

        }


        /* =====================================================
           TOP HEADER
        ====================================================== */

        .topbar {

            width: 100%;

            height: 60px;

            background: #1f4e79;

            color: #ffffff;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 28px;

        }


        .topbar-title {

            font-size: 23px;

            font-weight: bold;

            line-height: 1;

        }


        .topbar-admin {

            font-size: 16px;

            font-weight: normal;

        }


        .topbar-notifications {

            position: relative;

            display: inline-block;

            margin-right: 18px;

        }


        .topbar-notifications summary {

            list-style: none;

            cursor: pointer;

            font-size: 21px;

            line-height: 1;

        }


        .topbar-notifications summary::-webkit-details-marker {

            display: none;

        }


        .notification-badge {

            position: absolute;

            top: -9px;

            right: -10px;

            min-width: 18px;

            height: 18px;

            padding: 1px 5px;

            border-radius: 9px;

            background: #dc3545;

            color: #ffffff;

            font-size: 11px;

            font-weight: bold;

            text-align: center;

            line-height: 16px;

        }


        .notification-menu {

            position: absolute;

            right: 0;

            top: 31px;

            z-index: 1000;

            width: 360px;

            max-height: 430px;

            overflow-y: auto;

            background: #ffffff;

            color: #222222;

            border: 1px solid #d5d5d5;

            border-radius: 6px;

            box-shadow: 0 4px 14px rgba(0,0,0,0.2);

            text-align: left;

        }


        .notification-title,
        .notification-empty,
        .notification-item {

            padding: 11px 14px;

        }


        .notification-title {

            border-bottom: 1px solid #eeeeee;

            color: #1f4e79;

            font-weight: bold;

        }


        .notification-empty {

            color: #777777;

            font-size: 13px;

        }


        .notification-item {

            border-bottom: 1px solid #eeeeee;

            font-size: 12px;

        }


        .notification-item small {

            color: #777777;

        }


        .notification-correct {

            color: #087f3e;

        }


        .notification-wrong {

            color: #b02a37;

        }


        /* =====================================================
           MAIN LAYOUT
        ====================================================== */

        .layout {

            display: flex;

            width: 100%;

            min-height:
                calc(100vh - 60px);

        }


        /* =====================================================
           SIDEBAR
        ====================================================== */

        .admin-sidebar {

            width: 255px;

            min-width: 255px;

            height:
                calc(100vh - 60px);

            background: #ffffff;

            border-right:
                1px solid #dddddd;

            flex-shrink: 0;

            overflow-y: auto;

            overflow-x: hidden;

        }


        /* =====================================================
           SIDEBAR TITLE
        ====================================================== */

        .admin-sidebar-title {

            padding:
                25px 30px;

            color: #1f4e79;

            font-size: 28px;

            font-weight: bold;

            border-bottom:
                1px solid #eeeeee;

        }


        /* =====================================================
           SIDEBAR SECTION
        ====================================================== */

        .admin-sidebar-section {

            padding:
                18px 30px 8px;

            color: #888888;

            font-size: 12px;

            font-weight: bold;

            text-transform: uppercase;

            letter-spacing: 0.4px;

        }


        /* =====================================================
           SIDEBAR LINKS
           REDUCED TO 15PX
        ====================================================== */

        .admin-sidebar a {

            display: block;

            width: 100%;

            padding:
                12px 30px;

            box-sizing: border-box;

            color: #222222;

            text-decoration: none;

            border-bottom:
                1px solid #eeeeee;

            font-size: 15px;

            line-height: 20px;

            transition:
                background 0.2s,
                color 0.2s;

        }


        .admin-sidebar a:hover {

            background: #f1f5f8;

            color: #1f4e79;

        }


        /* =====================================================
           ACTIVE SIDEBAR LINK
        ====================================================== */

        .admin-sidebar a.active {

            background: #eaf2f8;

            color: #1f4e79;

            font-weight: bold;

            border-left:
                4px solid #1f4e79;

            padding-left: 26px;

        }


        /* =====================================================
           SIDEBAR SCROLLBAR
        ====================================================== */

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


        /* =====================================================
           MAIN CONTENT
        ====================================================== */

        .content {

            flex: 1;

            min-width: 0;

            padding:
                40px 36px;

            background: #f4f6f8;

            overflow-x: auto;

        }


        /* =====================================================
           PAGE HEADER
        ====================================================== */

        .page-header {

            margin-bottom: 28px;

        }


        .page-title {

            margin: 0;

            color: #1f4e79;

            font-size: 36px;

            font-weight: bold;

            line-height: 1.2;

        }


        .page-subtitle {

            margin:
                8px 0 0;

            color: #555555;

            font-size: 18px;

        }


        /* =====================================================
           DATABASE ERROR
        ====================================================== */

        .error {

            background: #fdecea;

            color: #a12622;

            border:
                1px solid #f2b8b5;

            padding: 15px;

            border-radius: 7px;

            margin-bottom: 25px;

        }


        /* =====================================================
           STATISTICS
        ====================================================== */

        .stats {

            display: grid;

            grid-template-columns:
                repeat(4, minmax(0, 1fr));

            gap: 24px;

            margin-bottom: 28px;

        }


        /* =====================================================
           STAT CARD
        ====================================================== */

        .stat-card {

            display: block;

            background: #ffffff;

            min-height: 160px;

            padding:
                30px;

            border-radius: 10px;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, 0.08);

            border-left:
                5px solid #1f4e79;

            text-decoration: none;

            color: inherit;

            transition:
                transform 0.2s,
                box-shadow 0.2s;

        }


        .stat-card:hover {

            transform:
                translateY(-2px);

            box-shadow:
                0 6px 18px
                rgba(0, 0, 0, 0.12);

        }


        .stat-card.green {

            border-left-color:
                #278a4b;

        }


        .stat-card.orange {

            border-left-color:
                #a66a21;

        }


        .stat-card.purple {

            border-left-color:
                #7057a5;

        }


        .stat-title {

            color: #555555;

            font-size: 17px;

            margin-bottom: 10px;

        }


        .stat-number {

            color: #1f4e79;

            font-size: 34px;

            font-weight: bold;

        }


        /* =====================================================
           SECOND STAT ROW
        ====================================================== */

        .stats.second-row {

            grid-template-columns:
                repeat(4, minmax(0, 1fr));

        }


        /* =====================================================
           QUICK ACCESS
        ====================================================== */

        .quick-access {

            background: #ffffff;

            padding:
                30px 35px;

            border-radius: 10px;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, 0.08);

            margin-top: 5px;

        }


        .quick-access h2 {

            margin:
                0 0 24px;

            color: #1f4e79;

            font-size: 27px;

            font-weight: bold;

        }


        .quick-grid {

            display: grid;

            grid-template-columns:
                repeat(4, minmax(0, 1fr));

            gap: 20px;

        }


        .quick-card {

            display: block;

            min-height: 125px;

            background: #f4f6f8;

            padding:
                22px;

            border-radius: 7px;

            text-decoration: none;

            transition:
                background 0.2s,
                transform 0.2s;

        }


        .quick-card:hover {

            background: #eaf2f8;

            transform:
                translateY(-2px);

        }


        .quick-card strong {

            display: block;

            margin-bottom: 8px;

            color: #1f4e79;

            font-size: 17px;

        }


        .quick-card span {

            display: block;

            color: #666666;

            font-size: 14px;

            line-height: 1.5;

        }


        /* =====================================================
           RESPONSIVE
        ====================================================== */

        @media (max-width: 1250px) {

            .stats {

                grid-template-columns:
                    repeat(2, minmax(0, 1fr));

            }


            .stats.second-row {

                grid-template-columns:
                    repeat(2, minmax(0, 1fr));

            }


            .quick-grid {

                grid-template-columns:
                    repeat(2, minmax(0, 1fr));

            }

        }


        @media (max-width: 800px) {

            .topbar {

                height: 60px;

                padding:
                    0 20px;

            }


            .topbar-title {

                font-size: 18px;

            }


            .layout {

                min-height:
                    calc(100vh - 60px);

            }


            .admin-sidebar {

                width: 220px;

                min-width: 220px;

                height:
                    calc(100vh - 65px);

            }


            .admin-sidebar-title {

                padding:
                    20px;

                font-size: 22px;

            }


            .admin-sidebar-section {

                padding-left: 20px;

            }


            .admin-sidebar a {

                padding-left: 20px;

                font-size: 15px;

            }


            .admin-sidebar a.active {

                padding-left: 16px;

            }


            .content {

                padding:
                    25px 20px;

            }

        }


        @media (max-width: 600px) {

            .layout {

                display: block;

            }


            .admin-sidebar {

                width: 100%;

                min-width: 100%;

                height: auto;

                max-height: none;

            }


            .stats,
            .stats.second-row {

                grid-template-columns: 1fr;

            }


            .quick-grid {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     TOP BAR
====================================================== -->

<header class="topbar">


    <div class="topbar-title">

        FTC Grading &amp; Tabulation Management System

    </div>


    <div class="topbar-admin">

        <details class="topbar-notifications admin-notification-menu">
            <summary title="Notifications">
                &#128276;
                <?php if ($adminUnreadNotificationCount > 0): ?>
                    <span class="notification-badge"><?php echo $adminUnreadNotificationCount; ?></span>
                <?php endif; ?>
            </summary>
            <div class="notification-menu">
                <div class="notification-title">Verification Notifications</div>
                <?php if (empty($adminNotifications)): ?>
                    <div class="notification-empty">No notifications yet.</div>
                <?php else: ?>
                    <?php foreach ($adminNotifications as $notification): ?>
                        <div class="notification-item">
                            <strong><?php echo htmlspecialchars($notification['student_name']); ?></strong>
                            <span>(<?php echo htmlspecialchars($notification['roll_no']); ?>)</span><br>
                            <?php if ($notification['verification_status'] === 'correct'): ?>
                                <b class="notification-correct">&#10003; Correct</b>
                            <?php else: ?>
                                <b class="notification-wrong">&#10007; Wrong</b>
                            <?php endif; ?>
                            <span> | <?php echo htmlspecialchars($notification['batch_name']); ?></span><br>
                            <?php if (!empty($notification['subject_name'])): ?>
                                <small><?php echo htmlspecialchars($notification['subject_name']); ?> | <?php echo htmlspecialchars($notification['assessment_type_name'] ?? ''); ?> | Mark: <?php echo htmlspecialchars((string)($notification['marks_obtained'] ?? '')); ?></small><br>
                            <?php endif; ?>
                            <small><?php echo htmlspecialchars($notification['created_at']); ?></small>
                            <?php if ($notification['verification_status'] === 'wrong' && (int)($notification['entry_id'] ?? 0) > 0): ?>
                                <a href="marks/edit_scrutiny.php?student_id=<?php echo (int)$notification['student_id']; ?>&amp;subject_id=<?php echo (int)$notification['subject_id']; ?>&amp;entry_id=<?php echo (int)$notification['entry_id']; ?>&amp;notification_id=<?php echo (int)$notification['notification_id']; ?>" style="display:inline-block;margin-top:6px;color:#1f4e79;font-weight:bold;">Edit Scrutiny Mark</a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </details>

        Admin
        <a href="../logout.php" style="color: #ffffff; margin-left: 18px;">Logout</a>

    </div>


</header>



<!-- =====================================================
     MAIN LAYOUT
====================================================== -->

<div class="layout">


<!-- =====================================================
     SIDEBAR
====================================================== -->

<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>



<!-- =====================================================
     DASHBOARD CONTENT
====================================================== -->

<main class="content">


    <!-- PAGE HEADER -->

    <div class="page-header">


        <h1 class="page-title">

            Admin Dashboard

        </h1>


        <p class="page-subtitle">

            FTC Grading &amp; Tabulation Management System

        </p>


    </div>



    <!-- =================================================
         DATABASE ERROR
    ================================================== -->

        <?php echo displayFlash(); ?>

    <?php if ($errorMessage !== ""): ?>

        <div class="error">

            <strong>
                Database Error:
            </strong>

            <br><br>

            <?php

            echo htmlspecialchars(
                $errorMessage
            );

            ?>

        </div>

    <?php endif; ?>



    <!-- =================================================
         FIRST ROW
    ================================================== -->

    <div class="stats">


        <!-- TOTAL STUDENTS -->

        <a
            href="students/index.php"
            class="stat-card"
        >

            <div class="stat-title">

                Total Students

            </div>


            <div class="stat-number">

                <?= $studentCount ?>

            </div>

        </a>



        <!-- TOTAL TEACHERS -->

        <a
            href="teachers/index.php"
            class="stat-card green"
        >

            <div class="stat-title">

                Total Teachers

            </div>


            <div class="stat-number">

                <?= $teacherCount ?>

            </div>

        </a>



        <!-- TOTAL BATCHES -->

        <a
            href="batches/index.php"
            class="stat-card orange"
        >

            <div class="stat-title">

                Total Batches

            </div>


            <div class="stat-number">

                <?= $batchCount ?>

            </div>

        </a>



        <!-- TOTAL SUBJECTS -->

        <a
            href="subjects/index.php"
            class="stat-card purple"
        >

            <div class="stat-title">

                Total Subjects

            </div>


            <div class="stat-number">

                <?= $subjectCount ?>

            </div>

        </a>


    </div>



    <!-- =================================================
         SECOND ROW
    ================================================== -->

    <div class="stats second-row">


        <!-- ELECTIVE SUBJECTS -->

        <a
            href="electives/index.php"
            class="stat-card purple"
        >

            <div class="stat-title">

                Total Elective Subjects

            </div>


            <div class="stat-number">

                <?= $electiveCount ?>

            </div>

        </a>



        <!-- FACULTY ASSIGNMENTS -->

        <a
            href="assignments/index.php"
            class="stat-card"
        >

            <div class="stat-title">

                Faculty Assignments

            </div>


            <div class="stat-number">

                <?= $assignmentCount ?>

            </div>

        </a>


    </div>



    <!-- =================================================
         QUICK ACCESS
    ================================================== -->

    <section class="quick-access">


        <h2>

            Quick Access

        </h2>


        <div class="quick-grid">


            <!-- ASSIGN FACULTY -->

            <a
                href="assignments/add.php"
                class="quick-card"
            >

                <strong>

                    + Assign Faculty

                </strong>


                <span>

                    Create a new faculty assignment.

                </span>

            </a>



            <!-- MANAGE BATCHES -->

            <a
                href="batches/index.php"
                class="quick-card"
            >

                <strong>

                    Manage Batches

                </strong>


                <span>

                    Open, lock and manage batches.

                </span>

            </a>



            <!-- SUBJECT REGISTRATION -->

            <a
                href="subjects/index.php"
                class="quick-card"
            >

                <strong>

                    Subject Registration

                </strong>


                <span>

                    Add and manage subjects.

                </span>

            </a>



            <!-- ELECTIVE MANAGEMENT -->

            <a
                href="electives/index.php"
                class="quick-card"
            >

                <strong>

                    Elective Management

                </strong>


                <span>

                    Manage elective groups and mappings.

                </span>

            </a>


        </div>


    </section>


</main>


</div>


<?php if ($adminUnreadNotificationCount > 0): ?>
<script>
    document.querySelectorAll('.admin-notification-menu').forEach(function (notificationMenu) {
        notificationMenu.addEventListener('toggle', function () {
            if (!notificationMenu.open) {
                return;
            }

            fetch('../admin/read_notifications.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'csrf_token=<?php echo rawurlencode(generateCsrfToken()); ?>'
            }).then(function () {
                var badge = notificationMenu.querySelector('.notification-badge');
                if (badge) {
                    badge.remove();
                }
            });
        });
    });
</script>
<?php endif; ?>

</body>

</html>
