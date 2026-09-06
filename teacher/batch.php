<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../config/database.php';

$facultyId = (int) $_SESSION['faculty_id'];
$batchId   = 0;


/*
|--------------------------------------------------------------------------
| BATCH ID
|--------------------------------------------------------------------------
*/

if (
    isset($_GET['batch_id']) &&
    is_numeric($_GET['batch_id'])
) {

    $batchId = (int) $_GET['batch_id'];

}


$faculty = null;
$batch = null;
$subjects = [];
$students = [];

$errorMessage = "";
$accessDenied = false;


/*
|--------------------------------------------------------------------------
| BASIC VALIDATION
|--------------------------------------------------------------------------
*/

if ($batchId <= 0) {

    $errorMessage = "Invalid batch selected.";

}


try {


    /*
    |--------------------------------------------------------------------------
    | GET FACULTY
    |--------------------------------------------------------------------------
    */

    if ($errorMessage === "") {


        if ($facultyId > 0) {

            $stmt = $pdo->prepare("
                SELECT
                    faculty_id,
                    faculty_name,
                    email
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
            */

            $stmt = $pdo->query("
                SELECT
                    faculty_id,
                    faculty_name,
                    email
                FROM faculties
                ORDER BY faculty_id DESC
                LIMIT 1
            ");

        }


        $faculty =
            $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$faculty) {

            $errorMessage =
                "Faculty record could not be found.";

        } else {

            $facultyId =
                (int) $faculty['faculty_id'];

        }

    }


    /*
    |--------------------------------------------------------------------------
    | CHECK FACULTY ACCESS TO THIS BATCH
    |--------------------------------------------------------------------------
    |
    | A faculty member can open a batch only if that
    | batch exists in subject_faculty_mapping for them.
    |
    */

    if (
        $errorMessage === "" &&
        $faculty
    ) {


        $stmt = $pdo->prepare("
            SELECT DISTINCT

                b.batch_id,
                b.batch_name,
                b.commenced_date,
                b.completed_date,
                b.station,
                b.status

            FROM batches b

            INNER JOIN subject_faculty_mapping sfm
                ON sfm.batch_id = b.batch_id

            WHERE
                b.batch_id = ?
                AND sfm.faculty_id = ?
                AND b.status != 'Completed'

            LIMIT 1
        ");


        $stmt->execute([
            $batchId,
            $facultyId
        ]);


        $batch =
            $stmt->fetch(PDO::FETCH_ASSOC);


        if (!$batch) {

            $accessDenied = true;

            // Check if this batch exists but is simply Completed
            $chkStmt = $pdo->prepare("
                SELECT status FROM batches WHERE batch_id = ? LIMIT 1
            ");
            $chkStmt->execute([$batchId]);
            $batchStatus = $chkStmt->fetchColumn();

            if ($batchStatus === 'Completed') {
                $errorMessage =
                    "This batch has been marked as Completed. Marks entry is no longer available for completed batches.";
            } else {
                $errorMessage =
                    "This batch is not assigned to you.";
            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | GET SUBJECTS ASSIGNED TO FACULTY IN THIS BATCH
    |--------------------------------------------------------------------------
    */

    if (
        !$accessDenied &&
        $batch
    ) {


        $stmt = $pdo->prepare("
            SELECT

                sfm.batch_id,
                sfm.subject_id,
                sfm.faculty_id,
                sfm.max_marks,

                s.subject_code,
                s.subject_name,
                s.is_elective

            FROM subject_faculty_mapping sfm

            INNER JOIN subjects s
                ON sfm.subject_id = s.subject_id

            WHERE
                sfm.batch_id = ?
                AND sfm.faculty_id = ?

            ORDER BY
                s.subject_name ASC
        ");


        $stmt->execute([
            $batchId,
            $facultyId
        ]);


        $subjects =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($subjects as &$sub) {
            $stmtP = $pdo->prepare("
                SELECT
                    p.paper_id,
                    p.paper_code,
                    p.paper_name,
                    p.paper_type,
                    psm.subject_max_internal,
                    psm.subject_max_midterm,
                    psm.subject_max_final
                FROM paper_subject_mapping psm
                INNER JOIN papers p
                    ON psm.paper_id = p.paper_id
                WHERE psm.subject_id = ?
                  AND psm.batch_id = ?
                ORDER BY p.paper_type ASC, p.paper_id ASC
            ");
            $stmtP->execute([$sub['subject_id'], $batchId]);
            $sub['mapped_papers'] = $stmtP->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($sub);

    }


    /*
    |--------------------------------------------------------------------------
    | GET STUDENTS OF THIS BATCH
    |--------------------------------------------------------------------------
    */

    if (
        !$accessDenied &&
        $batch
    ) {


        $stmt = $pdo->prepare("
            SELECT

                student_id,
                roll_no,
                student_name,
                designation,
                qualification,
                rmc,
                group_code

            FROM students

            WHERE
                batch_id = ?
                AND is_deleted = FALSE

            ORDER BY
                roll_no ASC,
                student_name ASC
        ");


        $stmt->execute([
            $batchId
        ]);


        $students =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

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

        <?php

        echo $batch
            ? htmlspecialchars(
                $batch['batch_name']
            )
            : 'Batch';

        ?>

        - Faculty Panel

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
           BATCH INFO
        ========================= */

        .batch-info {

            background: white;

            padding: 30px;

            border-radius: 10px;

            box-shadow:
                0 3px 10px
                rgba(0,0,0,0.08);

            margin-bottom: 30px;

        }


        .batch-name {

            margin: 0;

            color: #1f4e79;

            font-size: 30px;

        }


        .batch-meta {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;

            margin-top: 25px;

        }


        .meta-box {

            background: #f8fafb;

            padding: 18px;

            border-radius: 7px;

        }


        .meta-label {

            color: #777;

            font-size: 14px;

            margin-bottom: 7px;

        }


        .meta-value {

            font-weight: bold;

            color: #333;

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

            margin-bottom: 30px;

        }


        .section-title {

            margin: 0 0 20px;

            color: #1f4e79;

            font-size: 26px;

        }


        /* =========================
           SUBJECT CARDS
        ========================= */

        .subject-grid {

            display: grid;

            grid-template-columns:
                repeat(2, minmax(300px, 1fr));

            gap: 20px;

        }


        .subject-card {

            border:
                1px solid #e2e6ea;

            border-radius: 9px;

            padding: 22px;

            background: #fff;

        }


        .subject-code {

            color: #777;

            font-size: 14px;

            font-weight: bold;

        }


        .subject-name {

            color: #1f4e79;

            font-size: 21px;

            font-weight: bold;

            margin-top: 7px;

        }


        .subject-type {

            display: inline-block;

            margin-top: 12px;

            padding: 5px 9px;

            border-radius: 5px;

            font-size: 12px;

            font-weight: bold;

        }


        .regular {

            background: #eaf2f8;

            color: #1f4e79;

        }


        .elective {

            background: #fff3cd;

            color: #856404;

        }


        .max-marks {

            margin-top: 15px;

            font-size: 14px;

            color: #555;

        }


        .max-marks strong {

            color: #1f4e79;

        }


        /* =========================
           EXAM BUTTONS
        ========================= */

        .exam-buttons {

            display: flex;

            flex-wrap: wrap;

            gap: 8px;

            margin-top: 18px;

        }


        .exam-button {

            padding: 9px 12px;

            border-radius: 5px;

            text-decoration: none;

            background: #1f4e79;

            color: white;

            font-size: 13px;

            font-weight: bold;

        }


        .exam-button:hover {

            background: #163a5c;

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

            min-width: 800px;

        }


        th {

            background: #1f4e79;

            color: white;

            padding: 13px;

            text-align: left;

        }


        td {

            padding: 13px;

            border-bottom:
                1px solid #eee;

        }


        tr:hover td {

            background: #f8fafb;

        }


        /* =========================
           GROUP BADGE
        ========================= */

        .group-badge {

            display: inline-block;

            padding: 5px 9px;

            background: #f0f0f0;

            border-radius: 5px;

            font-size: 12px;

            font-weight: bold;

        }


        .no-group {

            color: #999;

        }


        /* =========================
           EMPTY
        ========================= */

        .empty {

            text-align: center;

            padding: 35px;

            background: #f8f9fa;

            border-radius: 8px;

            color: #666;

        }


        .empty h3 {

            color: #1f4e79;

            margin-top: 0;

        }


        /* =========================
           BACK BUTTON
        ========================= */

        .back-button {

            display: inline-block;

            margin-bottom: 20px;

            padding: 10px 15px;

            background: #777;

            color: white;

            text-decoration: none;

            border-radius: 6px;

            font-weight: bold;

        }


        .back-button:hover {

            background: #555;

        }


        /* =========================
           RESPONSIVE
        ========================= */

        @media (max-width: 900px) {

            .batch-meta {

                grid-template-columns: 1fr;

            }


            .subject-grid {

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

        Faculty Panel

    </div>


</header>



<div class="layout">


    <!-- =========================
         SIDEBAR
    ========================= -->

    <?php require_once __DIR__ . '/../includes/faculty_sidebar.php'; ?>



    <!-- =========================
         MAIN
    ========================= -->

    <main class="content">


        <a
            href="batches.php"
            class="back-button"
        >

            ← Back to My Batches

        </a>



        <?php if ($errorMessage !== ""): ?>


            <div class="error">

                <strong>

                    <?php

                    echo $accessDenied
                        ? "Access Denied"
                        : "Error";

                    ?>

                </strong>

                <br><br>

                <?php

                echo htmlspecialchars(
                    $errorMessage
                );

                ?>

            </div>


        <?php endif; ?>



        <?php if (
            $batch &&
            !$accessDenied
        ): ?>


            <!-- =========================
                 BATCH INFORMATION
            ========================= -->

            <section class="batch-info">


                <h1 class="batch-name">

                    <?php

                    echo htmlspecialchars(
                        $batch['batch_name']
                    );

                    ?>

                </h1>


                <div class="batch-meta">


                    <div class="meta-box">


                        <div class="meta-label">

                            Station

                        </div>


                        <div class="meta-value">

                            <?php

                            echo htmlspecialchars(
                                $batch['station']
                            );

                            ?>

                        </div>


                    </div>



                    <div class="meta-box">


                        <div class="meta-label">

                            Commenced Date

                        </div>


                        <div class="meta-value">

                            <?php

                            echo htmlspecialchars(
                                $batch['commenced_date']
                            );

                            ?>

                        </div>


                    </div>



                    <div class="meta-box">


                        <div class="meta-label">

                            Completed Date

                        </div>


                        <div class="meta-value">

                            <?php

                            echo htmlspecialchars(
                                $batch['completed_date']
                            );

                            ?>

                        </div>


                    </div>


                </div>


            </section>



            <!-- =========================
                 ASSIGNED SUBJECTS
            ========================= -->

            <section class="section">


                <h2 class="section-title">

                    My Subjects for this Batch

                </h2>



                <?php if (count($subjects) > 0): ?>


                    <div class="subject-grid">


                        <?php foreach (
                            $subjects
                            as $subject
                        ): ?>


                            <div class="subject-card" id="subject-<?php echo (int)$subject['subject_id']; ?>">


                                <div class="subject-code">

                                    <?php

                                    echo htmlspecialchars(
                                        $subject['subject_code']
                                    );

                                    ?>

                                </div>


                                <div class="subject-name">

                                    <?php

                                    echo htmlspecialchars(
                                        $subject['subject_name']
                                    );

                                    ?>

                                </div>



                                <?php

                                if (
                                    (int)
                                    $subject['is_elective']
                                    === 1
                                ):

                                ?>

                                    <span
                                        class="subject-type elective"
                                    >

                                        Elective

                                    </span>

                                <?php else: ?>

                                    <span
                                        class="subject-type regular"
                                    >

                                        Regular

                                    </span>

                                <?php endif; ?>



                                <div class="max-marks">

                                    Faculty Max Marks:

                                    <strong>

                                        <?php

                                        echo htmlspecialchars(
                                            $subject['max_marks']
                                        );

                                        ?>

                                    </strong>

                                </div>



                                <?php if (!empty($subject['mapped_papers'])): ?>
                                    <?php foreach ($subject['mapped_papers'] as $mp): ?>
                                        <div style="margin-top:12px; padding:10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                                            <div style="font-size:12px; font-weight:bold; color:#1f4e79; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                                                <span>[<?php echo htmlspecialchars($mp['paper_code']); ?>] <?php echo htmlspecialchars($mp['paper_name']); ?></span>
                                                <span style="font-size:10px; background:#1f4e79; color:#fff; padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($mp['paper_type']); ?></span>
                                            </div>
                                            <div class="exam-buttons" style="display:flex; gap:6px;">
                                                <a href="exams/internal.php?batch_id=<?php echo (int)$batchId; ?>&subject_id=<?php echo (int)$subject['subject_id']; ?>&faculty_id=<?php echo (int)$facultyId; ?>&paper_id=<?php echo (int)$mp['paper_id']; ?>" class="exam-button" style="flex:1; text-align:center; font-size:11px; padding:6px 4px;">Internal</a>
                                                <a href="exams/midterm.php?batch_id=<?php echo (int)$batchId; ?>&subject_id=<?php echo (int)$subject['subject_id']; ?>&faculty_id=<?php echo (int)$facultyId; ?>&paper_id=<?php echo (int)$mp['paper_id']; ?>" class="exam-button" style="flex:1; text-align:center; font-size:11px; padding:6px 4px;">Mid-Term</a>
                                                <a href="exams/final.php?batch_id=<?php echo (int)$batchId; ?>&subject_id=<?php echo (int)$subject['subject_id']; ?>&faculty_id=<?php echo (int)$facultyId; ?>&paper_id=<?php echo (int)$mp['paper_id']; ?>" class="exam-button" style="flex:1; text-align:center; font-size:11px; padding:6px 4px;">Final</a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- DEFAULT EXAM BUTTONS -->
                                    <div class="exam-buttons" style="margin-top:12px;">
                                        <a href="exams/internal.php?batch_id=<?php echo (int)$batchId; ?>&subject_id=<?php echo (int)$subject['subject_id']; ?>&faculty_id=<?php echo (int)$facultyId; ?>" class="exam-button">Internal</a>
                                        <a href="exams/midterm.php?batch_id=<?php echo (int)$batchId; ?>&subject_id=<?php echo (int)$subject['subject_id']; ?>&faculty_id=<?php echo (int)$facultyId; ?>" class="exam-button">Mid-Term</a>
                                        <a href="exams/final.php?batch_id=<?php echo (int)$batchId; ?>&subject_id=<?php echo (int)$subject['subject_id']; ?>&faculty_id=<?php echo (int)$facultyId; ?>" class="exam-button">Final</a>
                                    </div>
                                <?php endif; ?>


                            </div>


                        <?php endforeach; ?>


                    </div>


                <?php else: ?>


                    <div class="empty">


                        <h3>

                            No Subjects Assigned

                        </h3>


                        <p>

                            No subjects have been assigned
                            to you for this batch.

                        </p>


                    </div>


                <?php endif; ?>


            </section>



            <!-- =========================
                 STUDENTS
            ========================= -->

            <section class="section">


                <h2 class="section-title">

                    Students in this Batch

                </h2>



                <?php if (count($students) > 0): ?>


                    <div class="table-container">


                        <table>


                            <thead>

                                <tr>

                                    <th>
                                        #
                                    </th>

                                    <th>
                                        Roll No
                                    </th>

                                    <th>
                                        Student Name
                                    </th>

                                    <th>
                                        Designation
                                    </th>

                                    <th>
                                        RMC
                                    </th>

                                    <th>
                                        Elective Group
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php

                                $counter = 1;

                                foreach (
                                    $students
                                    as $student
                                ):

                                ?>


                                    <tr>


                                        <td>

                                            <?php

                                            echo $counter++;

                                            ?>

                                        </td>


                                        <td>

                                            <?php

                                            echo htmlspecialchars(
                                                $student['roll_no']
                                            );

                                            ?>

                                        </td>


                                        <td>

                                            <strong>

                                                <?php

                                                echo htmlspecialchars(
                                                    $student['student_name']
                                                );

                                                ?>

                                            </strong>

                                        </td>


                                        <td>

                                            <?php

                                            echo htmlspecialchars(
                                                $student['designation']
                                            );

                                            ?>

                                        </td>


                                        <td>

                                            <?php

                                            echo htmlspecialchars(
                                                $student['rmc']
                                            );

                                            ?>

                                        </td>


                                        <td>


                                            <?php

                                            if (
                                                !empty(
                                                    $student['group_code']
                                                )
                                            ):

                                            ?>


                                                <span
                                                    class="group-badge"
                                                >

                                                    Group

                                                    <?php

                                                    echo htmlspecialchars(
                                                        strtoupper(
                                                            $student['group_code']
                                                        )
                                                    );

                                                    ?>

                                                </span>


                                            <?php else: ?>


                                                <span
                                                    class="no-group"
                                                >

                                                    Core / None

                                                </span>


                                            <?php endif; ?>


                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            </tbody>


                        </table>


                    </div>


                <?php else: ?>


                    <div class="empty">


                        <h3>

                            No Students Found

                        </h3>


                        <p>

                            There are currently no active
                            students registered in this batch.

                        </p>


                    </div>


                <?php endif; ?>


            </section>


        <?php endif; ?>


    </main>


</div>


</body>

</html>