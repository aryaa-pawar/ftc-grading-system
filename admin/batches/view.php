<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
| Current file:
| admin/batches/view.php
|
| Database:
| config/database.php
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/../../config/database.php";


/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function h($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


function formatDateIndian($date)
{
    if (empty($date)) {
        return '—';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return h($date);
    }

    return date('d.m.Y', $timestamp);
}


/*
|--------------------------------------------------------------------------
| GET BATCH IDENTIFIER
|--------------------------------------------------------------------------
|
| Accepts all of these:
|
| view.php?batch_id=1
| view.php?id=1
| view.php?batch=1
| view.php?batch_no=1
|
|--------------------------------------------------------------------------
*/

$batchId = 0;

if (isset($_GET['batch_id']) && $_GET['batch_id'] !== '') {

    $batchId = (int)$_GET['batch_id'];

} elseif (isset($_GET['id']) && $_GET['id'] !== '') {

    $batchId = (int)$_GET['id'];

} elseif (isset($_GET['batch']) && $_GET['batch'] !== '') {

    $batchId = (int)$_GET['batch'];

} elseif (isset($_GET['batch_no']) && $_GET['batch_no'] !== '') {

    $batchId = (int)$_GET['batch_no'];

}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$batch = null;

$students = [];

$errorMessage = '';


/*
|--------------------------------------------------------------------------
| LOAD BATCH
|--------------------------------------------------------------------------
*/

if ($batchId <= 0) {

    $errorMessage = 'Invalid batch ID.';

} else {

    try {

        /*
        |--------------------------------------------------------------------------
        | BATCH DETAILS
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                batch_id,
                batch_number,
                batch_name,
                station,
                commenced_date,
                completed_date
            FROM batches
            WHERE batch_id = :batch_id
            LIMIT 1
        ");

        $stmt->execute([
            ':batch_id' => $batchId
        ]);

        $batch = $stmt->fetch(
            PDO::FETCH_ASSOC
        );


        /*
        |--------------------------------------------------------------------------
        | BATCH NOT FOUND
        |--------------------------------------------------------------------------
        */

        if (!$batch) {

            $errorMessage =
                'Batch not found.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | STUDENTS
            |--------------------------------------------------------------------------
            */

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
                WHERE batch_id = :batch_id
                  AND is_deleted = 0
                ORDER BY
                    roll_no ASC,
                    student_name ASC
            ");

            $stmt->execute([
                ':batch_id' => $batchId
            ]);

            $students = $stmt->fetchAll(
                PDO::FETCH_ASSOC
            );

        }


    } catch (PDOException $e) {

        $errorMessage =
            $e->getMessage();

    }

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
    View Batch
</title>


<style>

/* =========================================================
   GLOBAL
========================================================= */

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


/* =========================================================
   TOP BAR
========================================================= */

.topbar {

    height: 72px;

    background: #1f4e79;

    color: white;

    display: flex;

    align-items: center;

    justify-content: space-between;

    padding: 0 30px;

}


.topbar-title {

    font-size: 21px;

    font-weight: bold;

}


.topbar-right {

    font-size: 15px;

}


/* =========================================================
   LAYOUT
========================================================= */

.layout {

    display: flex;

    min-height:
        calc(100vh - 72px);

}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    width: 255px;

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

    padding: 15px 25px;

    color: #222;

    text-decoration: none;

    border-bottom:
        1px solid #eee;

}


.sidebar a:hover {

    background: #eaf2f8;

    color: #1f4e79;

}


.sidebar a.active {

    background: #eaf2f8;

    color: #1f4e79;

    font-weight: bold;

}


/* =========================================================
   CONTENT
========================================================= */

.content {

    flex: 1;

    padding: 35px;

    overflow-x: auto;

}


.page-title {

    margin: 0;

    color: #1f4e79;

    font-size: 32px;

}


.page-subtitle {

    margin-top: 8px;

    margin-bottom: 25px;

    color: #666;

    font-size: 16px;

}


/* =========================================================
   ERROR
========================================================= */

.error {

    background: #fdecea;

    color: #a12622;

    border: 1px solid #f1b8b5;

    padding: 16px;

    border-radius: 7px;

    margin-bottom: 20px;

}


/* =========================================================
   CARD
========================================================= */

.card {

    background: white;

    padding: 25px;

    border-radius: 10px;

    box-shadow:
        0 3px 12px
        rgba(0, 0, 0, 0.08);

    margin-bottom: 25px;

}


.card-title {

    margin: 0;

    color: #1f4e79;

    font-size: 23px;

}


/* =========================================================
   BATCH DETAILS
========================================================= */

.batch-details {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-top: 22px;

}


.detail-box {

    background: #f4f6f8;

    padding: 18px;

    border-radius: 8px;

    border:
        1px solid #e1e1e1;

}


.detail-label {

    color: #777;

    font-size: 13px;

    font-weight: bold;

    margin-bottom: 8px;

}


.detail-value {

    color: #1f4e79;

    font-size: 18px;

    font-weight: bold;

}


/* =========================================================
   BUTTONS
========================================================= */

.button {

    display: inline-block;

    padding: 10px 17px;

    border-radius: 6px;

    text-decoration: none;

    font-weight: bold;

}


.back-button {

    background: #777;

    color: white;

}


.back-button:hover {

    background: #555;

}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper {

    overflow-x: auto;

    margin-top: 20px;

}


.student-table {

    width: 100%;

    min-width: 950px;

    border-collapse: collapse;

}


.student-table th {

    background: #1f4e79;

    color: white;

    padding: 13px;

    border:
        1px solid #174064;

    text-align: left;

}


.student-table td {

    padding: 12px;

    border:
        1px solid #e5e5e5;

}


.student-table tr:hover td {

    background: #f7fafc;

}


.student-name {

    color: #1f4e79;

    font-weight: bold;

}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    text-align: center;

    padding: 45px;

    color: #777;

}


.empty h3 {

    margin-top: 0;

    color: #555;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width: 950px) {

    .batch-details {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media(max-width: 700px) {

    .layout {

        display: block;

    }


    .sidebar {

        width: 100%;

    }


    .content {

        padding: 20px;

    }


    .batch-details {

        grid-template-columns: 1fr;

    }


    .topbar-title {

        font-size: 16px;

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

        FTC Grading & Tabulation Management System

    </div>

    <div class="topbar-right">

        Admin

    </div>

</header>



<div class="layout">


<!-- =====================================================
     SIDEBAR
====================================================== -->

<?php require_once __DIR__ . '/../../includes/sidebar.php'; ?>



<!-- =====================================================
     CONTENT
====================================================== -->

<main class="content">


    <h1 class="page-title">

        View Batch

    </h1>


    <p class="page-subtitle">

        View batch details and students assigned to this batch.

    </p>



    <!-- =================================================
         ERROR
    ================================================== -->

    <?php if ($errorMessage !== ''): ?>

        <div class="error">

            <strong>
                Error:
            </strong>

            <br><br>

            <?php echo h(
                $errorMessage
            ); ?>

        </div>


        <a
            href="index.php"
            class="button back-button"
        >

            ← Back to Batch Management

        </a>


    <?php elseif ($batch !== null): ?>


        <!-- =============================================
             BATCH DETAILS
        ============================================== -->

        <div class="card">


            <h2 class="card-title">

                Batch Details

            </h2>


            <div class="batch-details">


                <!-- BATCH -->

                <div class="detail-box">

                    <div class="detail-label">

                        Batch Number

                    </div>

                    <div class="detail-value">

                        <?php echo h(
                            $batch['batch_number']
                        ); ?>

                    </div>

                </div>


                <!-- STATION -->

                <div class="detail-box">

                    <div class="detail-label">

                        Station

                    </div>

                    <div class="detail-value">

                        <?php echo h(
                            $batch['station']
                        ); ?>

                    </div>

                </div>


                <!-- COMMENCED -->

                <div class="detail-box">

                    <div class="detail-label">

                        Commenced On

                    </div>

                    <div class="detail-value">

                        <?php echo formatDateIndian(
                            $batch['commenced_date']
                        ); ?>

                    </div>

                </div>


                <!-- COMPLETED -->

                <div class="detail-box">

                    <div class="detail-label">

                        Completed On

                    </div>

                    <div class="detail-value">

                        <?php echo formatDateIndian(
                            $batch['completed_date']
                        ); ?>

                    </div>

                </div>


            </div>


            <div style="margin-top:22px;">

                <a
                    href="index.php"
                    class="button back-button"
                >

                    ← Back to Batches

                </a>

            </div>


        </div>



        <!-- =============================================
             STUDENTS
        ============================================== -->

        <div class="card">


            <h2 class="card-title">

                Students in This Batch

            </h2>


            <?php if (
                count($students) === 0
            ): ?>


                <div class="empty">

                    <h3>

                        No Students Found

                    </h3>

                    <p>

                        There are currently no active students
                        assigned to this batch.

                    </p>

                </div>


            <?php else: ?>


                <div class="table-wrapper">


                    <table class="student-table">


                        <thead>

                            <tr>

                                <th>
                                    S.No.
                                </th>

                                <th>
                                    Roll No.
                                </th>

                                <th>
                                    Student Name
                                </th>

                                <th>
                                    Designation
                                </th>

                                <th>
                                    Qualification
                                </th>

                                <th>
                                    RMC
                                </th>

                                <th>
                                    Group
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php

                        $serialNo = 1;

                        ?>


                        <?php foreach (
                            $students
                            as $student
                        ): ?>


                            <tr>

                                <td>

                                    <?php echo $serialNo; ?>

                                </td>


                                <td>

                                    <?php echo h(
                                        $student['roll_no']
                                    ); ?>

                                </td>


                                <td>

                                    <span class="student-name">

                                        <?php echo h(
                                            $student['student_name']
                                        ); ?>

                                    </span>

                                </td>


                                <td>

                                    <?php echo h(
                                        $student['designation']
                                    ); ?>

                                </td>


                                <td>

                                    <?php echo h(
                                        $student['qualification']
                                    ); ?>

                                </td>


                                <td>

                                    <?php echo h(
                                        $student['rmc']
                                    ); ?>

                                </td>


                                <td>

                                    <?php echo h(
                                        $student['group_code']
                                    ); ?>

                                </td>

                            </tr>


                            <?php

                            $serialNo++;

                            ?>


                        <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php endif; ?>


        </div>


    <?php endif; ?>


</main>


</div>


</body>

</html>