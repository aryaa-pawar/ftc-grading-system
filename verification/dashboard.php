<?php

/*
|--------------------------------------------------------------------------
| FTC Grading & Tabulation Management System
| Marks Scrutiny Officer - Dashboard
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/auth.php';
require_permission('verification.access');
require_once __DIR__ . '/../config/database.php';

$pageTitle = "Scrutiny Dashboard";
$scrutinyOfficer = current_user();
$scrutinyContact = '';
if ($scrutinyOfficer) {
    $contactStmt = $pdo->prepare('SELECT phone FROM profiles WHERE user_id = :user_id LIMIT 1');
    $contactStmt->execute([':user_id' => $scrutinyOfficer['user_id']]);
    $scrutinyContact = (string)($contactStmt->fetchColumn() ?: '');
}


/*
|--------------------------------------------------------------------------
| COUNTS
|--------------------------------------------------------------------------
*/

$studentCount = 0;
$batchCount = 0;
$facultyCount = 0;
$marksCount = 0;

$errorMessage = "";


try {

    /*
    |--------------------------------------------------------------------------
    | ACTIVE STUDENTS
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
    | ACTIVE FACULTY
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM faculties
        WHERE is_deleted = FALSE
    ");

    $facultyCount = (int) $stmt->fetchColumn();


    /*
    |--------------------------------------------------------------------------
    | MARKS RECORDS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM faculty_marks_entry
    ");

    $marksCount = (int) $stmt->fetchColumn();


} catch (PDOException $e) {

    $errorMessage = $e->getMessage();

}


/*
|--------------------------------------------------------------------------
| COMMON HEADER
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../includes/header.php';

?>


<div style="
    display:flex;
    flex-direction:column;
    min-height:100vh;
">


    <!-- =====================================================
         COMMON NAVBAR
    ====================================================== -->

    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>


    <div style="
        display:flex;
        flex:1;
        min-height:0;
    ">


        <!-- =================================================
             SCRUTINY SIDEBAR
        ================================================== -->

        <?php require_once __DIR__ . '/../includes/verification_sidebar.php'; ?>


        <!-- =================================================
             MAIN CONTENT
        ================================================== -->

        <main style="
            flex:1;
            padding:30px;
            background:#f4f6f8;
            overflow-x:auto;
        ">

            <section style="background:#fff;padding:18px 22px;border-radius:8px;margin-bottom:24px;box-shadow:0 2px 8px rgba(0,0,0,.08);">
                <strong>Scrutiny Officer</strong><br>
                Name: <?= htmlspecialchars($scrutinyOfficer['full_name'] ?? 'Not available') ?><br>
                Email: <?= htmlspecialchars($scrutinyOfficer['email'] ?? 'Not available') ?><br>
                Contact Number: <?= htmlspecialchars($scrutinyContact !== '' ? $scrutinyContact : 'Not available') ?><br>
                Role: <?= htmlspecialchars(roleDisplayLabel($scrutinyOfficer['role_name'] ?? 'verification')) ?>
            </section>


            <!-- =================================================
                 PAGE TITLE
            ================================================== -->

            <div style="
                margin-bottom:30px;
            ">

                <h1 style="
                    margin:0 0 8px 0;
                    color:#1f4e79;
                    font-size:32px;
                ">

                    Scrutiny Dashboard

                </h1>


                <p style="
                    margin:0;
                    color:#666;
                    font-size:16px;
                ">

                    Review and verify marks entered in the
                    FTC Grading & Tabulation System.

                </p>

            </div>



            <!-- =================================================
                 ERROR
            ================================================== -->

            <?php if ($errorMessage !== ""): ?>

                <div style="
                    background:#fdecea;
                    color:#a12622;
                    border:1px solid #f5c6cb;
                    padding:15px;
                    border-radius:6px;
                    margin-bottom:25px;
                ">

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
                 STATISTICS
            ================================================== -->

            <div style="
                display:grid;
                grid-template-columns:
                    repeat(4, minmax(180px, 1fr));
                gap:20px;
                margin-bottom:30px;
            ">


                <!-- STUDENTS -->

                <a
                    href="marks.php"
                    style="
                        display:block;
                        background:#ffffff;
                        padding:25px;
                        border-radius:10px;
                        box-shadow:0 3px 12px rgba(0,0,0,0.08);
                        text-decoration:none;
                        color:inherit;
                        border-left:5px solid #1f4e79;
                    "
                >

                    <div style="
                        color:#666;
                        font-size:14px;
                        margin-bottom:8px;
                    ">

                        Active Students

                    </div>


                    <div style="
                        color:#1f4e79;
                        font-size:32px;
                        font-weight:bold;
                    ">

                        <?php

                        echo $studentCount;

                        ?>

                    </div>

                </a>



                <!-- BATCHES -->

                <a
                    href="marks.php"
                    style="
                        display:block;
                        background:#ffffff;
                        padding:25px;
                        border-radius:10px;
                        box-shadow:0 3px 12px rgba(0,0,0,0.08);
                        text-decoration:none;
                        color:inherit;
                        border-left:5px solid #278a4b;
                    "
                >

                    <div style="
                        color:#666;
                        font-size:14px;
                        margin-bottom:8px;
                    ">

                        Total Batches

                    </div>


                    <div style="
                        color:#278a4b;
                        font-size:32px;
                        font-weight:bold;
                    ">

                        <?php

                        echo $batchCount;

                        ?>

                    </div>

                </a>



                <!-- FACULTY -->

                <a
                    href="marks.php"
                    style="
                        display:block;
                        background:#ffffff;
                        padding:25px;
                        border-radius:10px;
                        box-shadow:0 3px 12px rgba(0,0,0,0.08);
                        text-decoration:none;
                        color:inherit;
                        border-left:5px solid #a66a21;
                    "
                >

                    <div style="
                        color:#666;
                        font-size:14px;
                        margin-bottom:8px;
                    ">

                        Active Faculty

                    </div>


                    <div style="
                        color:#a66a21;
                        font-size:32px;
                        font-weight:bold;
                    ">

                        <?php

                        echo $facultyCount;

                        ?>

                    </div>

                </a>



                <!-- MARKS -->

                <a
                    href="marks.php"
                    style="
                        display:block;
                        background:#ffffff;
                        padding:25px;
                        border-radius:10px;
                        box-shadow:0 3px 12px rgba(0,0,0,0.08);
                        text-decoration:none;
                        color:inherit;
                        border-left:5px solid #7057a5;
                    "
                >

                    <div style="
                        color:#666;
                        font-size:14px;
                        margin-bottom:8px;
                    ">

                        Marks Records

                    </div>


                    <div style="
                        color:#7057a5;
                        font-size:32px;
                        font-weight:bold;
                    ">

                        <?php

                        echo $marksCount;

                        ?>

                    </div>

                </a>


            </div>



            <!-- =================================================
                 QUICK ACCESS
            ================================================== -->

            <div style="
                background:#ffffff;
                padding:30px;
                border-radius:10px;
                box-shadow:0 3px 12px rgba(0,0,0,0.08);
            ">


                <h2 style="
                    margin:0 0 20px 0;
                    color:#1f4e79;
                    font-size:25px;
                ">

                    Quick Access

                </h2>


                <div style="
                    display:grid;
                    grid-template-columns:
                        repeat(2, minmax(220px, 1fr));
                    gap:18px;
                ">


                    <!-- MARKS REVIEW -->

                    <a
                        href="marks.php"
                        style="
                            display:block;
                            padding:22px;
                            background:#f4f6f8;
                            border-radius:8px;
                            text-decoration:none;
                            color:#1f4e79;
                        "
                    >

                        <strong style="
                            display:block;
                            margin-bottom:8px;
                            font-size:18px;
                        ">

                            Review Marks

                        </strong>


                        <span style="
                            color:#666;
                            font-size:14px;
                            line-height:1.5;
                        ">

                            Open the read-only marksheet
                            and verify entered marks.

                        </span>

                    </a>



                </div>


            </div>


        </main>


    </div>


    <!-- =====================================================
         COMMON FOOTER
    ====================================================== -->

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>


</div>



<style>

@media (max-width: 1000px) {

    main > div:nth-of-type(3) {

        grid-template-columns:
            repeat(2, minmax(180px, 1fr)) !important;

    }

}


@media (max-width: 650px) {

    main {

        padding:20px !important;

    }


    main > div:nth-of-type(3) {

        grid-template-columns:
            1fr !important;

    }

}

</style>
