<?php

/*
|--------------------------------------------------------------------------
| FTC Grading & Tabulation Management System
| Faculty Exams
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once __DIR__ . '/../../teacher/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';

$facultyId = (int)$_SESSION['faculty_id'];
$selectedBatchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0;



/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$faculty = null;

$assignments = [];

$errorMessage = "";


/*
|--------------------------------------------------------------------------
| LOAD FACULTY AND ASSIGNMENTS
|--------------------------------------------------------------------------
*/

try {


    /*
    |--------------------------------------------------------------------------
    | FACULTY
    |--------------------------------------------------------------------------
    */

    if ($facultyId > 0) {

        $stmt = $pdo->prepare("
            SELECT
                faculty_id,
                faculty_name,
                email
            FROM faculties
            WHERE faculty_id = :faculty_id
            LIMIT 1
        ");

        $stmt->execute([
            ":faculty_id" => $facultyId
        ]);

    } else {

        /*
        |--------------------------------------------------------------------------
        | TEMPORARY FALLBACK
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
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );


    if (!$faculty) {

        throw new Exception(
            "Faculty record not found."
        );

    }


    $facultyId =
        (int) $faculty["faculty_id"];


    /*
    |--------------------------------------------------------------------------
    | GET ONLY THIS FACULTY'S ASSIGNMENTS
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        SELECT

            sfm.batch_id,
            sfm.subject_id,
            sfm.faculty_id,
            sfm.max_marks,

            b.batch_name,
            b.station,

            s.subject_code,
            s.subject_name,
            s.is_elective

        FROM subject_faculty_mapping sfm

        INNER JOIN batches b
            ON sfm.batch_id = b.batch_id

        INNER JOIN subjects s
            ON sfm.subject_id = s.subject_id

        WHERE sfm.faculty_id = :faculty_id
        " . ($selectedBatchId > 0 ? "AND sfm.batch_id = :batch_id" : "") . "

        ORDER BY
            b.batch_name ASC,
            s.subject_name ASC
    ");


    $assignmentParams = [":faculty_id" => $facultyId];
    if ($selectedBatchId > 0) {
        $assignmentParams[":batch_id"] = $selectedBatchId;
    }
    $stmt->execute($assignmentParams);


    $assignments =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

    foreach ($assignments as &$asg) {
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
            INNER JOIN papers p ON psm.paper_id = p.paper_id
            WHERE psm.subject_id = ? AND psm.batch_id = ?
            ORDER BY p.paper_type ASC, p.paper_id ASC
        ");
        $stmtP->execute([$asg['subject_id'], $asg['batch_id']]);
        $asg['mapped_papers'] = $stmtP->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($asg);

} catch (Exception $e) {

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
        Faculty Exams
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


        .header {

            width: 100%;

            height: 60px;

            background: #1f4e79;

            color: #ffffff;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 25px;

        }


        .header-title {

            font-size: 18px;

            font-weight: bold;

        }


        .header-right {

            font-size: 14px;

        }


        .layout {

            display: flex;

            min-height:
                calc(100vh - 60px);

        }


        .sidebar {

            width: 255px;

            min-width: 255px;

            background: #ffffff;

            border-right:
                1px solid #dddddd;

            flex-shrink: 0;

        }


        .sidebar-title {

            padding: 22px 25px;

            color: #1f4e79;

            font-size: 23px;

            font-weight: bold;

            border-bottom:
                1px solid #eeeeee;

        }


        .sidebar a {

            display: block;

            padding: 13px 25px;

            color: #222222;

            text-decoration: none;

            border-bottom:
                1px solid #eeeeee;

            font-size: 15px;

        }


        .sidebar a:hover {

            background: #f1f5f8;

            color: #1f4e79;

        }


        .sidebar a.active {

            background: #eaf2f8;

            color: #1f4e79;

            font-weight: bold;

            border-left:
                4px solid #1f4e79;

            padding-left: 21px;

        }


        .content {

            flex: 1;

            padding: 30px;

            overflow-x: auto;

        }


        h1 {

            margin: 0 0 8px 0;

            color: #1f4e79;

            font-size: 30px;

        }


        .subtitle {

            margin: 0 0 25px 0;

            color: #666666;

        }


        .faculty-card {

            background: #ffffff;

            padding: 18px 22px;

            border-radius: 8px;

            box-shadow:
                0 2px 8px
                rgba(0,0,0,0.08);

            margin-bottom: 25px;

        }


        .faculty-card strong {

            color: #1f4e79;

        }


        .error {

            background: #fdecea;

            color: #a12622;

            border:
                1px solid #f5c6cb;

            padding: 15px;

            border-radius: 6px;

            margin-bottom: 20px;

        }


        .assignments {

            display: grid;

            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(300px, 1fr)
                );

            gap: 20px;

        }


        .assignment-card {

            background: #ffffff;

            padding: 25px;

            border-radius: 10px;

            box-shadow:
                0 3px 10px
                rgba(0,0,0,0.08);

        }


        .batch {

            color: #777777;

            font-size: 13px;

            font-weight: bold;

            text-transform: uppercase;

        }


        .subject {

            margin:
                10px 0 5px;

            color: #1f4e79;

            font-size: 21px;

        }


        .station {

            color: #777777;

            margin-bottom: 12px;

        }


        .badge {

            display: inline-block;

            padding: 5px 9px;

            border-radius: 5px;

            font-size: 12px;

            font-weight: bold;

        }


        .core {

            background: #eaf2f8;

            color: #1f4e79;

        }


        .elective {

            background: #fff3cd;

            color: #856404;

        }


        .max-marks {

            margin-top: 15px;

            padding: 12px;

            background: #f8fafb;

            border-radius: 6px;

        }


        .max-marks strong {

            color: #1f4e79;

            font-size: 18px;

        }


        .actions {

            margin-top: 20px;

        }


        .exam-button {

            display: inline-block;

            padding: 10px 12px;

            background: #1f4e79;

            color: #ffffff;

            text-decoration: none;

            border-radius: 5px;

            margin: 3px;

            font-size: 13px;

            font-weight: bold;

        }


        .exam-button:hover {

            background: #163a5c;

        }


        .view-button {

            background: #278a4b;

        }


        .view-button:hover {

            background: #1f713d;

        }


        .empty {

            background: #ffffff;

            padding: 50px 25px;

            text-align: center;

            border-radius: 10px;

            box-shadow:
                0 3px 10px
                rgba(0,0,0,0.08);

        }


        .empty h2 {

            color: #1f4e79;

        }


        .empty p {

            color: #666666;

        }


    </style>

</head>


<body>


<!-- =========================================================
     HEADER
========================================================== -->

<header class="header">


    <div class="header-title">

        FTC Grading & Tabulation Management System

    </div>


    <div class="header-right">

        <?php

        if ($faculty) {

            echo htmlspecialchars(
                $faculty["faculty_name"]
            );

        } else {

            echo "Faculty";

        }

        ?>

    </div>


</header>



<div class="layout">


<!-- =========================================================
     SIDEBAR
========================================================== -->

<?php require_once __DIR__ . '/../../includes/faculty_sidebar.php'; ?>



<!-- =========================================================
     CONTENT
========================================================== -->

<main class="content">


    <h1>

        Faculty Exams

    </h1>


    <p class="subtitle">

        Manage marks for your assigned subjects.

    </p>



    <!-- FACULTY INFORMATION -->

    <?php if ($faculty): ?>


        <div class="faculty-card">


            <strong>

                Faculty:

            </strong>


            <?php

            echo htmlspecialchars(
                $faculty["faculty_name"]
            );

            ?>


            <span style="
                margin:0 12px;
                color:#bbb;
            ">

                |

            </span>


            <strong>

                Email:

            </strong>


            <?php

            echo htmlspecialchars(
                $faculty["email"]
            );

            ?>


        </div>


    <?php endif; ?>



    <!-- ERROR -->

    <?php if ($errorMessage !== ""): ?>


        <div class="error">


            <strong>

                Error:

            </strong>


            <?php

            echo htmlspecialchars(
                $errorMessage
            );

            ?>


        </div>


    <?php endif; ?>



    <!-- ASSIGNMENTS -->

    <?php if (
        count($assignments) > 0
    ): ?>


        <div class="assignments">


            <?php foreach (
                $assignments
                as $assignmentIndex => $assignment
            ): ?>


                <div class="assignment-card">


                    <!-- BATCH -->

                    <div class="batch">

                        Batch:

                        <?php

                        echo htmlspecialchars(
                            $assignment["batch_name"]
                        );

                        ?>

                    </div>


                    <!-- SUBJECT -->

                    <h2 class="subject">

                        <?php

                        echo htmlspecialchars(
                            $assignment["subject_code"]
                        );

                        ?>

                        -

                        <?php

                        echo htmlspecialchars(
                            $assignment["subject_name"]
                        );

                        ?>

                    </h2>


                    <!-- STATION -->

                    <div class="station">

                        Station:

                        <?php

                        echo htmlspecialchars(
                            $assignment["station"]
                        );

                        ?>

                    </div>


                    <!-- TYPE -->

                    <?php if (
                        (int)
                        $assignment["is_elective"]
                        === 1
                    ): ?>


                        <span class="
                            badge
                            elective
                        ">

                            Elective

                        </span>


                    <?php else: ?>


                        <span class="
                            badge
                            core
                        ">

                            Core

                        </span>


                    <?php endif; ?>



                    <!-- MAX MARKS -->

                    <div class="max-marks">

                        Maximum Raw Marks:

                        <strong>

                            <?php

                            echo htmlspecialchars(
                                $assignment["max_marks"]
                            );

                            ?>

                        </strong>

                    </div>



                    <!-- ACTION BUTTONS -->
                    <?php if (!empty($assignment['mapped_papers'])): ?>
                        <?php foreach ($assignment['mapped_papers'] as $mp): ?>
                            <div style="margin-top:10px; padding:10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                                <div style="font-size:12px; font-weight:bold; color:#1f4e79; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center;">
                                    <span>[<?php echo htmlspecialchars($mp['paper_code']); ?>] <?php echo htmlspecialchars($mp['paper_name']); ?></span>
                                    <span style="font-size:10px; background:#1f4e79; color:#fff; padding:2px 6px; border-radius:4px;"><?php echo htmlspecialchars($mp['paper_type']); ?></span>
                                </div>
                                <div class="actions" style="display:flex; gap:6px;">
                                    <a href="internal.php?batch_id=<?php echo (int)$assignment["batch_id"]; ?>&subject_id=<?php echo (int)$assignment["subject_id"]; ?>&faculty_id=<?php echo (int)$facultyId; ?>&paper_id=<?php echo (int)$mp['paper_id']; ?>" class="exam-button" style="flex:1; text-align:center;">Internal</a>
                                    <a href="midterm.php?batch_id=<?php echo (int)$assignment["batch_id"]; ?>&subject_id=<?php echo (int)$assignment["subject_id"]; ?>&faculty_id=<?php echo (int)$facultyId; ?>&paper_id=<?php echo (int)$mp['paper_id']; ?>" class="exam-button" style="flex:1; text-align:center;">Mid-Term</a>
                                    <a href="final.php?batch_id=<?php echo (int)$assignment["batch_id"]; ?>&subject_id=<?php echo (int)$assignment["subject_id"]; ?>&faculty_id=<?php echo (int)$facultyId; ?>&paper_id=<?php echo (int)$mp['paper_id']; ?>" class="exam-button" style="flex:1; text-align:center;">Final</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div style="margin-top:10px;">
                            <a href="marks.php?id=<?php echo (int)$facultyId; ?>&tab=<?php echo (int)$assignmentIndex; ?>&paper_id=<?php echo (int)$mp['paper_id']; ?>" class="exam-button view-button" style="display:block; text-align:center;">View Entered Marks</a>
                        </div>
                    <?php else: ?>
                        <div class="actions">
                            <a href="internal.php?batch_id=<?php echo (int)$assignment["batch_id"]; ?>&subject_id=<?php echo (int)$assignment["subject_id"]; ?>&faculty_id=<?php echo (int)$facultyId; ?>" class="exam-button">Internal</a>
                            <a href="midterm.php?batch_id=<?php echo (int)$assignment["batch_id"]; ?>&subject_id=<?php echo (int)$assignment["subject_id"]; ?>&faculty_id=<?php echo (int)$facultyId; ?>" class="exam-button">Mid-Term</a>
                            <a href="final.php?batch_id=<?php echo (int)$assignment["batch_id"]; ?>&subject_id=<?php echo (int)$assignment["subject_id"]; ?>&faculty_id=<?php echo (int)$facultyId; ?>" class="exam-button">Final</a>
                            <a href="marks.php?id=<?php echo (int)$facultyId; ?>&tab=<?php echo (int)$assignmentIndex; ?>" class="exam-button view-button">View Entered Marks</a>
                        </div>
                    <?php endif; ?>


                </div>


            <?php endforeach; ?>


        </div>


    <?php else: ?>


        <!-- NO ASSIGNMENTS -->

        <div class="empty">


            <h2>

                No Subjects Assigned

            </h2>


            <p>

                You currently have no subjects assigned
                for marks entry.

            </p>


            <p>

                Please contact the administrator.

            </p>


        </div>


    <?php endif; ?>


</main>


</div>


</body>

</html>