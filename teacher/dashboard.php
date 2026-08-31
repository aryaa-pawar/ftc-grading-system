<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/auth_guard.php';  // Redirects to login if not authenticated
require_once __DIR__ . '/../config/database.php';

/*
|--------------------------------------------------------------------------
| FACULTY IDENTIFICATION — from session (set by login.php)
|--------------------------------------------------------------------------
*/

$facultyId = (int) $_SESSION['faculty_id'];


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$faculty = null;
$assignments = [];

$totalBatches = 0;
$totalSubjects = 0;
$totalAssignments = 0;

$errorMessage = "";


try {


    /*
    |--------------------------------------------------------------------------
    | GET FACULTY
    |--------------------------------------------------------------------------
    */

    if ($facultyId > 0) {

        $stmt = $pdo->prepare("
            SELECT
                faculty_id,
                faculty_name,
                email,
                created_at
            FROM faculties
            WHERE faculty_id = ?
            LIMIT 1
        ");

        $stmt->execute([
            $facultyId
        ]);

    } else {

        /*
        |--------------------------------------------------------------------------
        | TEMPORARY TESTING FALLBACK
        |--------------------------------------------------------------------------
        |
        | Until faculty login/session is connected,
        | show the most recently added faculty.
        |
        */

        $stmt = $pdo->query("
            SELECT
                faculty_id,
                faculty_name,
                email,
                created_at
            FROM faculties
            ORDER BY faculty_id DESC
            LIMIT 1
        ");

    }


    $faculty =
        $stmt->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | GET ASSIGNED BATCHES + SUBJECTS
    |--------------------------------------------------------------------------
    */

    if ($faculty) {


        $facultyId =
            (int) $faculty['faculty_id'];


        $stmt = $pdo->prepare("
            SELECT
                sfm.batch_id,
                sfm.subject_id,
                COALESCE(
                    (
                        SELECT MAX(
                            COALESCE(NULLIF(psm.subject_max_internal, 0), p.max_internal, 0)
                            + COALESCE(NULLIF(psm.subject_max_midterm, 0), p.max_midterm, 0)
                            + COALESCE(NULLIF(psm.subject_max_final, 0), p.max_final, 0)
                        )
                        FROM paper_subject_mapping psm
                        INNER JOIN papers p ON p.paper_id = psm.paper_id
                        WHERE psm.batch_id = sfm.batch_id
                          AND psm.subject_id = sfm.subject_id
                    ),
                    sfm.max_marks
                ) AS max_marks,

                b.batch_name,
                b.station,
                b.commenced_date,
                b.completed_date,

                s.subject_code,
                s.subject_name,
                s.is_elective

            FROM subject_faculty_mapping sfm

            INNER JOIN batches b
                ON sfm.batch_id = b.batch_id

            INNER JOIN subjects s
                ON sfm.subject_id = s.subject_id

            WHERE sfm.faculty_id = ?

            ORDER BY
                b.batch_name ASC,
                s.subject_name ASC
        ");


        $stmt->execute([
            $facultyId
        ]);


        $assignments =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        /*
        |--------------------------------------------------------------------------
        | COUNT UNIQUE BATCHES
        |--------------------------------------------------------------------------
        */

        $batchIds = [];


        /*
        |--------------------------------------------------------------------------
        | COUNT UNIQUE SUBJECTS
        |--------------------------------------------------------------------------
        */

        $subjectIds = [];


        foreach ($assignments as $assignment) {

            $batchIds[
                $assignment['batch_id']
            ] = true;


            $subjectIds[
                $assignment['subject_id']
            ] = true;

        }


        $totalBatches =
            count($batchIds);


        $totalSubjects =
            count($subjectIds);


        $totalAssignments =
            count($assignments);

    }


} catch (PDOException $e) {

    $errorMessage =
        $e->getMessage();

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
        Faculty Dashboard
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f6f8;

            color: #333;

        }


        /* =========================
           HEADER
        ========================= */

        .header {

            height: 80px;

            background: #1f4e79;

            color: white;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 30px;

        }


        .header-title {

            font-size: 23px;

            font-weight: bold;

        }


        .header-right {

            font-size: 16px;

        }


        /* =========================
           LAYOUT
        ========================= */

        .layout {

            display: flex;

            min-height:
                calc(100vh - 80px);

        }


        /* =========================
           SIDEBAR
        ========================= */

        .sidebar {

            width: 260px;

            background: white;

            border-right:
                1px solid #ddd;

            flex-shrink: 0;

        }


        .sidebar-title {

            padding: 25px;

            font-size: 22px;

            font-weight: bold;

            color: #1f4e79;

            border-bottom:
                1px solid #eee;

        }


        .sidebar a {

            display: block;

            padding: 16px 25px;

            color: #333;

            text-decoration: none;

            border-bottom:
                1px solid #eee;

        }


        .sidebar a:hover {

            background: #f1f5f8;

            color: #1f4e79;

        }


        .sidebar a.active {

            background: #eaf2f8;

            color: #1f4e79;

            font-weight: bold;

        }


        /* =========================
           CONTENT
        ========================= */

        .content {

            flex: 1;

            padding: 40px;

            overflow-x: auto;

        }


        .page-title {

            margin: 0;

            color: #1f4e79;

            font-size: 36px;

        }


        .page-subtitle {

            margin-top: 8px;

            margin-bottom: 30px;

            color: #666;

        }


        /* =========================
           ERROR
        ========================= */

        .error {

            background: #fdecea;

            color: #a12622;

            padding: 18px;

            border-radius: 8px;

            margin-bottom: 25px;

            word-break: break-word;

        }


        /* =========================
           PROFILE
        ========================= */

        .profile {

            background: white;

            padding: 30px;

            border-radius: 10px;

            box-shadow:
                0 3px 10px
                rgba(0,0,0,0.08);

            margin-bottom: 25px;

        }


        .faculty-name {

            font-size: 30px;

            font-weight: bold;

            color: #1f4e79;

        }


        .faculty-email {

            margin-top: 10px;

            color: #666;

            font-size: 17px;

        }


        .faculty-id {

            margin-top: 15px;

            color: #777;

        }


        .faculty-id strong {

            color: #1f4e79;

        }


        /* =========================
           SUMMARY CARDS
        ========================= */

        .summary {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;

            margin-bottom: 30px;

        }


        .summary-card {

            background: white;

            padding: 25px;

            border-radius: 10px;

            box-shadow:
                0 3px 10px
                rgba(0,0,0,0.08);

        }


        .summary-link {

            display: block;

            color: inherit;

            text-decoration: none;

            transition: transform 0.2s, box-shadow 0.2s;

        }


        .summary-link:hover,
        .summary-link:focus-visible {

            transform: translateY(-3px);

            box-shadow:
                0 6px 16px
                rgba(31,78,121,0.18);

            outline: none;

        }


        .summary-label {

            color: #777;

            font-size: 15px;

            margin-bottom: 10px;

        }


        .summary-number {

            color: #1f4e79;

            font-size: 32px;

            font-weight: bold;

        }


        /* =========================
           SECTION
        ========================= */

        .section {

            background: white;

            padding: 30px;

            border-radius: 10px;

            box-shadow:
                0 3px 10px
                rgba(0,0,0,0.08);

        }


        .section-title {

            margin: 0 0 20px;

            color: #1f4e79;

            font-size: 26px;

        }


        /* =========================
           TABLE
        ========================= */

        .table-container {

            overflow-x: auto;

        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 850px;

        }


        th {

            background: #1f4e79;

            color: white;

            padding: 14px;

            text-align: left;

        }


        td {

            padding: 14px;

            border-bottom:
                1px solid #eee;

        }


        tr:hover td {

            background: #f8fafb;

        }


        /* =========================
           BADGES
        ========================= */

        .regular {

            display: inline-block;

            padding: 5px 9px;

            border-radius: 5px;

            background: #eaf2f8;

            color: #1f4e79;

            font-size: 12px;

            font-weight: bold;

        }


        .elective {

            display: inline-block;

            padding: 5px 9px;

            border-radius: 5px;

            background: #fff3cd;

            color: #856404;

            font-size: 12px;

            font-weight: bold;

        }


        /* =========================
           OPEN BUTTON
        ========================= */

        .open-button {

            display: inline-block;

            padding: 8px 13px;

            background: #1f4e79;

            color: white;

            text-decoration: none;

            border-radius: 5px;

            font-size: 13px;

            font-weight: bold;

        }


        .open-button:hover {

            background: #163a5c;

        }


        /* =========================
           EMPTY
        ========================= */

        .empty {

            text-align: center;

            padding: 40px;

            background: #f8f9fa;

            border-radius: 8px;

            color: #666;

        }


        .empty h3 {

            color: #1f4e79;

            margin-top: 0;

        }


        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 900px) {

            .summary {

                grid-template-columns: 1fr;

            }

        }


        @media (max-width: 700px) {

            .sidebar {

                width: 210px;

            }


            .content {

                padding: 20px;

            }


            .header-title {

                font-size: 17px;

            }

        }

    </style>

</head>


<body>


<!-- =========================
     HEADER
========================= -->

<header class="header">


    <div class="header-title">

        FTC Grading & Tabulation Management System

    </div>


    <div class="header-right">

        Faculty Panel &nbsp;|&nbsp; <strong><?php echo htmlspecialchars($_SESSION['faculty_name'] ?? 'Faculty'); ?></strong>

    </div>


</header>



<div class="layout">


    <!-- =========================
         SIDEBAR
    ========================= -->

    <?php require_once __DIR__ . '/../includes/faculty_sidebar.php'; ?>



    <!-- =========================
         MAIN CONTENT
    ========================= -->

    <main class="content">


        <h1 class="page-title">

            Faculty Dashboard

        </h1>


        <p class="page-subtitle">

            View your assigned batches and subjects.

        </p>



        <!-- =========================
             DATABASE ERROR
        ========================= -->

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



        <?php if ($faculty): ?>


            <!-- =========================
                 FACULTY PROFILE
            ========================= -->

            <section class="profile">


                <div class="faculty-name">

                    <?php

                    echo htmlspecialchars(
                        $faculty['faculty_name']
                    );

                    ?>

                </div>


                <div class="faculty-email">

                    <?php

                    echo htmlspecialchars(
                        $faculty['email']
                    );

                    ?>

                </div>


                <div class="faculty-id">

                    Faculty ID:

                    <strong>

                        <?php

                        echo htmlspecialchars(
                            $faculty['faculty_id']
                        );

                        ?>

                    </strong>

                </div>


            </section>



            <!-- =========================
                 SUMMARY
            ========================= -->

            <div class="summary">


                <a class="summary-card summary-link" href="batches.php">

                    <div class="summary-label">

                        Assigned Batches

                    </div>


                    <div class="summary-number">

                        <?php

                        echo $totalBatches;

                        ?>

                    </div>

                </a>



                <a class="summary-card summary-link" href="exams/index.php">

                    <div class="summary-label">

                        Assigned Subjects

                    </div>


                    <div class="summary-number">

                        <?php

                        echo $totalSubjects;

                        ?>

                    </div>

                </a>



                <a class="summary-card summary-link" href="exams/marks.php">

                    <div class="summary-label">

                        View Entered Marks

                    </div>


                    <div class="summary-number">

                        <?php

                        echo $totalAssignments;

                        ?>

                    </div>

                </a>


            </div>



            <!-- =========================
                 ASSIGNED SUBJECTS
            ========================= -->

            <section class="section">


                <h2 class="section-title">

                    My Assigned Subjects

                </h2>



                <?php if ($totalAssignments > 0): ?>


                    <div class="table-container">


                        <table>


                            <thead>

                                <tr>

                                    <th>
                                        Batch
                                    </th>

                                    <th>
                                        Station
                                    </th>

                                    <th>
                                        Subject Code
                                    </th>

                                    <th>
                                        Subject
                                    </th>

                                    <th>
                                        Type
                                    </th>

                                    <th>
                                        Max Marks
                                    </th>

                                    <th>
                                        Action
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach (
                                    $assignments
                                    as $assignment
                                ): ?>


                                    <tr>


                                        <td>

                                            <?php

                                            echo htmlspecialchars(
                                                $assignment['batch_name']
                                            );

                                            ?>

                                        </td>


                                        <td>

                                            <?php

                                            echo htmlspecialchars(
                                                $assignment['station']
                                            );

                                            ?>

                                        </td>


                                        <td>

                                            <?php

                                            echo htmlspecialchars(
                                                $assignment['subject_code']
                                            );

                                            ?>

                                        </td>


                                        <td>

                                            <?php

                                            echo htmlspecialchars(
                                                $assignment['subject_name']
                                            );

                                            ?>

                                        </td>


                                        <td>


                                            <?php

                                            if (
                                                (int)
                                                $assignment['is_elective']
                                                === 1
                                            ) {

                                                echo
                                                    '<span class="elective">Elective</span>';

                                            } else {

                                                echo
                                                    '<span class="regular">Regular</span>';

                                            }

                                            ?>

                                        </td>


                                        <td>

                                            <?php

                                            echo htmlspecialchars(
                                                $assignment['max_marks']
                                            );

                                            ?>

                                        </td>


                                        <td>


                                            <a
                                                href="batch.php?batch_id=<?php echo (int)$assignment['batch_id']; ?>"
                                                class="open-button"
                                            >

                                                Open Batch

                                            </a>


                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            </tbody>


                        </table>


                    </div>


                <?php else: ?>


                    <div class="empty">


                        <h3>

                            No Assignments Yet

                        </h3>


                        <p>

                            No batch and subject have
                            been assigned to this faculty.

                        </p>


                        <p>

                            Admin needs to create the
                            faculty-batch-subject assignment.

                        </p>


                    </div>


                <?php endif; ?>


            </section>


        <?php else: ?>


            <!-- =========================
                 NO FACULTY
            ========================= -->

            <div class="empty">


                <h3>

                    No Faculty Found

                </h3>


                <p>

                    Please add a faculty member from
                    Admin → Teacher Management.

                </p>


            </div>


        <?php endif; ?>


    </main>


</div>


</body>

</html>