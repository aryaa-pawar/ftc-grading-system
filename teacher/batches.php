<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/../config/database.php';

$facultyId = (int) $_SESSION['faculty_id'];



/*
|--------------------------------------------------------------------------
| DATA
|--------------------------------------------------------------------------
*/

$faculty = null;
$batches = [];

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


    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);


    /*
    |--------------------------------------------------------------------------
    | GET ASSIGNED BATCHES
    |--------------------------------------------------------------------------
    */

    if ($faculty) {

        $facultyId =
            (int) $faculty['faculty_id'];


        $stmt = $pdo->prepare("
            SELECT

                b.batch_id,
                b.batch_name,
                b.commenced_date,
                b.completed_date,
                b.station,
                b.status,
                b.is_locked,

                COUNT(
                    DISTINCT sfm.subject_id
                ) AS subject_count

            FROM subject_faculty_mapping sfm

            INNER JOIN batches b
                ON sfm.batch_id = b.batch_id

            WHERE sfm.faculty_id = ?
            GROUP BY

                b.batch_id,
                b.batch_name,
                b.commenced_date,
                b.completed_date,
                b.station,
                b.status,
                b.is_locked

            ORDER BY
                b.batch_name ASC
        ");


        $stmt->execute([
            $facultyId
        ]);


        $batches =
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
        My Batches
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


        /* HEADER */

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


        /* LAYOUT */

        .layout {

            display: flex;

            min-height:
                calc(100vh - 80px);

        }


        /* SIDEBAR */

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


        /* CONTENT */

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


        /* ERROR */

        .error {

            background: #fdecea;

            color: #a12622;

            padding: 18px;

            border-radius: 8px;

            margin-bottom: 25px;

            word-break: break-word;

        }


        /* FACULTY INFO */

        .faculty-info {

            background: white;

            padding: 20px 25px;

            border-radius: 10px;

            box-shadow:
                0 3px 10px
                rgba(0,0,0,0.08);

            margin-bottom: 25px;

        }


        .faculty-info strong {

            color: #1f4e79;

        }


        /* BATCH GRID */

        .batch-grid {

            display: grid;

            grid-template-columns:
                repeat(2, minmax(300px, 1fr));

            gap: 25px;

        }


        /* BATCH CARD */

        .batch-card {

            background: white;

            padding: 28px;

            border-radius: 10px;

            box-shadow:
                0 3px 10px
                rgba(0,0,0,0.08);

        }


        .batch-name {

            margin: 0;

            color: #1f4e79;

            font-size: 25px;

        }


        .batch-details {

            margin-top: 20px;

        }


        .detail {

            display: flex;

            justify-content: space-between;

            padding: 10px 0;

            border-bottom:
                1px solid #eee;

        }


        .detail:last-child {

            border-bottom: none;

        }


        .detail-label {

            color: #777;

        }


        .detail-value {

            font-weight: bold;

            color: #333;

        }


        /* SUBJECT BADGE */

        .subject-badge {

            display: inline-block;

            margin-top: 15px;

            padding: 7px 11px;

            background: #eaf2f8;

            color: #1f4e79;

            border-radius: 5px;

            font-weight: bold;

            font-size: 13px;

        }


        /* BUTTON */

        .open-button {

            display: inline-block;

            margin-top: 20px;

            padding: 11px 18px;

            background: #1f4e79;

            color: white;

            text-decoration: none;

            border-radius: 6px;

            font-weight: bold;

        }


        .open-button:hover {

            background: #163a5c;

        }


        /* EMPTY */

        .empty {

            background: white;

            padding: 45px;

            text-align: center;

            border-radius: 10px;

            box-shadow:
                0 3px 10px
                rgba(0,0,0,0.08);

        }


        .empty h2 {

            color: #1f4e79;

            margin-top: 0;

        }


        /* RESPONSIVE */

        @media (max-width: 900px) {

            .batch-grid {

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


<!-- HEADER -->

<header class="header">

    <div class="header-title">

        FTC Grading & Tabulation Management System

    </div>


    <div class="header-right">

        Faculty Panel

    </div>

</header>



<div class="layout">


    <!-- SIDEBAR -->

    <?php require_once __DIR__ . '/../includes/faculty_sidebar.php'; ?>



    <!-- CONTENT -->

    <main class="content">


        <h1 class="page-title">

            My Batches

        </h1>


        <p class="page-subtitle">

            View the batches assigned to you.

        </p>



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


            <!-- FACULTY INFO -->

            <div class="faculty-info">

                Faculty:

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $faculty['faculty_name']
                    );

                    ?>

                </strong>


                &nbsp;&nbsp; | &nbsp;&nbsp;


                Email:

                <strong>

                    <?php

                    echo htmlspecialchars(
                        $faculty['email']
                    );

                    ?>

                </strong>

            </div>



            <?php if (count($batches) > 0): ?>


                <!-- BATCHES -->

                <div class="batch-grid">


                    <?php foreach (
                        $batches
                        as $batch
                    ): ?>


                        <div class="batch-card">


                            <h2 class="batch-name">

                                <?php

                                echo htmlspecialchars(
                                    $batch['batch_name']
                                );

                                ?>

                            </h2>

                            <?php if (in_array(($batch['status'] ?? ''), ['Result Declared', 'Completed'], true) || !empty($batch['is_locked'])): ?>
                                <div style="display:inline-block;margin:0 0 14px;padding:6px 10px;border-radius:4px;background:#e8f4ec;color:#216e39;font-size:12px;font-weight:bold;">
                                    Results Declared
                                </div>
                            <?php endif; ?>


                            <div class="batch-details">


                                <div class="detail">

                                    <span class="detail-label">

                                        Station

                                    </span>


                                    <span class="detail-value">

                                        <?php

                                        echo htmlspecialchars(
                                            $batch['station']
                                        );

                                        ?>

                                    </span>

                                </div>



                                <div class="detail">

                                    <span class="detail-label">

                                        Start Date

                                    </span>


                                    <span class="detail-value">

                                        <?php

                                        echo htmlspecialchars(
                                            $batch['commenced_date']
                                        );

                                        ?>

                                    </span>

                                </div>



                                <div class="detail">

                                    <span class="detail-label">

                                        End Date

                                    </span>


                                    <span class="detail-value">

                                        <?php

                                        echo htmlspecialchars(
                                            $batch['completed_date']
                                        );

                                        ?>

                                    </span>

                                </div>


                            </div>



                            <div class="subject-badge">

                                <?php

                                echo (int)
                                    $batch['subject_count'];

                                ?>

                                Assigned Subject(s)

                            </div>



                            <br>


                            <?php if (in_array(($batch['status'] ?? ''), ['Result Declared', 'Completed'], true) || !empty($batch['is_locked'])): ?>
                                <div style="margin-top:12px;color:#666;font-size:12px;">
                                    Marks are no longer available for faculty viewing.
                                </div>
                            <?php else: ?>
                                <a
                                    href="exams/index.php?batch_id=<?php echo (int)$batch['batch_id']; ?>"
                                    class="open-button"
                                >
                                    Open Batch
                                </a>
                            <?php endif; ?>


                        </div>


                    <?php endforeach; ?>


                </div>


            <?php else: ?>


                <!-- NO BATCHES -->

                <div class="empty">


                    <h2>

                        No Batches Assigned

                    </h2>


                    <p>

                        You currently do not have
                        any batches assigned to you.

                    </p>


                    <p>

                        Admin needs to assign a
                        batch and subject through
                        Subject-Faculty Mapping.

                    </p>


                </div>


            <?php endif; ?>


        <?php else: ?>


            <div class="empty">


                <h2>

                    Faculty Not Found

                </h2>


                <p>

                    Please add a faculty member
                    through Admin → Teacher Management.

                </p>


            </div>


        <?php endif; ?>


    </main>


</div>


</body>

</html>