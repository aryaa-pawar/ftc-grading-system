<?php

/*
|--------------------------------------------------------------------------
| FTC Grading & Tabulation Management System
| Admin - Faculty Assignment
|--------------------------------------------------------------------------
*/

require_once "../../config/database.php";

$pageTitle = "Faculty Assignment";

$message = "";
$error = "";

$assignments = [];
$faculties = [];

$selectedFaculty = isset($_GET["faculty_id"])
    ? (int) $_GET["faculty_id"]
    : 0;


/*
|--------------------------------------------------------------------------
| SUCCESS / ERROR MESSAGE
|--------------------------------------------------------------------------
*/

if (isset($_GET["success"])) {

    $message = $_GET["success"];

}

if (isset($_GET["error"])) {

    $error = $_GET["error"];

}


/*
|--------------------------------------------------------------------------
| LOAD ACTIVE FACULTIES FOR FILTER
|--------------------------------------------------------------------------
*/

try {

    $facultyStmt = $pdo->query("
        SELECT
            faculty_id,
            faculty_name
        FROM faculties
        WHERE is_deleted = FALSE
        ORDER BY faculty_name ASC
    ");

    $faculties =
        $facultyStmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (PDOException $e) {

    $error =
        $e->getMessage();

}


/*
|--------------------------------------------------------------------------
| GET ALL ASSIGNMENTS
|--------------------------------------------------------------------------
*/

try {

    $sql = "
        SELECT

            sfm.batch_id,
            sfm.subject_id,
            sfm.faculty_id,
            sfm.max_marks,

            b.batch_name,
            b.station,

            s.subject_code,
            s.subject_name,
            s.is_elective,

            f.faculty_name,
            f.email

        FROM subject_faculty_mapping sfm

        INNER JOIN batches b
            ON b.batch_id = sfm.batch_id

        INNER JOIN subjects s
            ON s.subject_id = sfm.subject_id

        INNER JOIN faculties f
            ON f.faculty_id = sfm.faculty_id

        WHERE f.is_deleted = FALSE
    ";

    $params = [];


    /*
    |--------------------------------------------------------------------------
    | FACULTY FILTER
    |--------------------------------------------------------------------------
    */

    if ($selectedFaculty > 0) {

        $sql .= "
            AND sfm.faculty_id = :faculty_id
        ";

        $params[":faculty_id"] =
            $selectedFaculty;

    }


    $sql .= "
        ORDER BY
            sfm.batch_id,
            sfm.subject_id,
            sfm.faculty_id,
            b.batch_name,
            s.subject_code,
            f.faculty_name
    ";


    $stmt =
        $pdo->prepare($sql);


    $stmt->execute(
        $params
    );


    $assignments =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


} catch (PDOException $e) {

    $assignments = [];

    $error =
        $e->getMessage();

}


/*
|--------------------------------------------------------------------------
| COMMON HEADER
|--------------------------------------------------------------------------
*/

require_once "../../includes/header.php";

?>


<div style="
    display:flex;
    flex-direction:column;
    min-height:100vh;
">


    <!-- COMMON NAVBAR -->

    <?php require_once "../../includes/navbar.php"; ?>


    <div style="
        display:flex;
        flex:1;
        min-height:0;
    ">


        <!-- COMMON ADMIN SIDEBAR -->

        <?php require_once "../../includes/sidebar.php"; ?>


        <!-- MAIN CONTENT -->

        <main style="
            flex:1;
            padding:30px;
            background:#f4f6f8;
            overflow-x:auto;
        ">


            <!-- PAGE HEADER -->

            <div style="
                display:flex;
                justify-content:space-between;
                align-items:flex-start;
                gap:20px;
                margin-bottom:25px;
            ">


                <div>

                    <h1 style="
                        margin:0 0 8px 0;
                        color:#1f4e79;
                        font-size:32px;
                    ">

                        Faculty Assignment

                    </h1>


                    <p style="
                        margin:0;
                        color:#666;
                        font-size:16px;
                    ">

                        Assign faculty members to batches and subjects.

                    </p>

                </div>


                <a
                    href="add.php"
                    style="
                        display:inline-block;
                        background:#1f4e79;
                        color:#ffffff;
                        text-decoration:none;
                        padding:12px 20px;
                        border-radius:6px;
                        font-weight:bold;
                        white-space:nowrap;
                    "
                >

                    + Assign Faculty

                </a>


            </div>



            <!-- =================================================
                 FACULTY FILTER
            ================================================== -->

            <div style="
                background:#ffffff;
                padding:18px 20px;
                border-radius:8px;
                box-shadow:0 2px 8px rgba(0,0,0,0.08);
                margin-bottom:20px;
            ">


                <form
                    method="GET"
                    action="index.php"
                    style="
                        display:flex;
                        align-items:flex-end;
                        gap:15px;
                        flex-wrap:wrap;
                    "
                >


                    <div>

                        <label
                            for="faculty_id"
                            style="
                                display:block;
                                margin-bottom:7px;
                                font-weight:bold;
                                color:#444;
                            "
                        >

                            Filter by Faculty

                        </label>


                        <select
                            id="faculty_id"
                            name="faculty_id"
                            style="
                                min-width:250px;
                                padding:10px 12px;
                                border:1px solid #ccc;
                                border-radius:5px;
                                background:#ffffff;
                                font-size:14px;
                            "
                        >


                            <option value="0">

                                All Faculty

                            </option>


                            <?php foreach ($faculties as $faculty): ?>


                                <option
                                    value="<?php echo (int)$faculty["faculty_id"]; ?>"
                                    <?php

                                    echo $selectedFaculty ===
                                        (int)$faculty["faculty_id"]

                                        ? "selected"

                                        : "";

                                    ?>
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $faculty["faculty_name"]
                                    );

                                    ?>

                                </option>


                            <?php endforeach; ?>


                        </select>

                    </div>


                    <!-- FILTER -->

                    <button
                        type="submit"
                        style="
                            background:#1f4e79;
                            color:#ffffff;
                            border:none;
                            padding:10px 18px;
                            border-radius:5px;
                            cursor:pointer;
                            font-weight:bold;
                        "
                    >

                        Filter

                    </button>


                    <!-- CLEAR -->

                    <?php if ($selectedFaculty > 0): ?>


                        <a
                            href="index.php"
                            style="
                                background:#eeeeee;
                                color:#333333;
                                padding:10px 18px;
                                border-radius:5px;
                                text-decoration:none;
                                font-weight:bold;
                            "
                        >

                            Clear

                        </a>


                    <?php endif; ?>


                </form>


            </div>



            <!-- SUCCESS MESSAGE -->

            <?php if ($message !== ""): ?>


                <div style="
                    background:#eaf7ed;
                    color:#216e39;
                    padding:14px;
                    border-radius:6px;
                    margin-bottom:20px;
                    border:1px solid #c8e6ce;
                ">


                    <?php

                    echo htmlspecialchars(
                        $message
                    );

                    ?>


                </div>


            <?php endif; ?>



            <!-- ERROR MESSAGE -->

            <?php if ($error !== ""): ?>


                <div style="
                    background:#fdecea;
                    color:#a12622;
                    padding:14px;
                    border-radius:6px;
                    margin-bottom:20px;
                    border:1px solid #f5c6cb;
                ">


                    <?php

                    echo htmlspecialchars(
                        $error
                    );

                    ?>


                </div>


            <?php endif; ?>



            <!-- ASSIGNMENT CARD -->

            <div style="
                background:#ffffff;
                padding:25px;
                border-radius:10px;
                box-shadow:0 3px 10px rgba(0,0,0,0.08);
                overflow-x:auto;
            ">


                <?php if (
                    count($assignments) === 0
                ): ?>


                    <!-- EMPTY STATE -->

                    <div style="
                        text-align:center;
                        padding:40px;
                        color:#777;
                    ">


                        <h2>

                            No Faculty Assignments Yet

                        </h2>


                        <p>

                            No faculty members have been assigned
                            to a batch and subject.

                        </p>


                        <p>

                            Click

                            <strong>

                                + Assign Faculty

                            </strong>

                            to create the first assignment.

                        </p>


                    </div>


                <?php else: ?>


                    <!-- TABLE -->

                    <table style="
                        width:100%;
                        min-width:900px;
                        border-collapse:collapse;
                    ">


                        <thead>

                            <tr>

                                <th style="
                                    background:#1f4e79;
                                    color:#ffffff;
                                    padding:13px;
                                    text-align:center;
                                ">
                                    S.No
                                </th>


                                <th style="
                                    background:#1f4e79;
                                    color:#ffffff;
                                    padding:13px;
                                    text-align:left;
                                ">

                                    Batch

                                </th>


                                <th style="
                                    background:#1f4e79;
                                    color:#ffffff;
                                    padding:13px;
                                    text-align:left;
                                ">

                                    Subject

                                </th>


                                <th style="
                                    background:#1f4e79;
                                    color:#ffffff;
                                    padding:13px;
                                    text-align:left;
                                ">

                                    Faculty

                                </th>


                                <th style="
                                    background:#1f4e79;
                                    color:#ffffff;
                                    padding:13px;
                                    text-align:left;
                                ">

                                    Email

                                </th>


                                <th style="
                                    background:#1f4e79;
                                    color:#ffffff;
                                    padding:13px;
                                    text-align:left;
                                ">

                                    Type

                                </th>


                                <th style="
                                    background:#1f4e79;
                                    color:#ffffff;
                                    padding:13px;
                                    text-align:left;
                                ">

                                    Action

                                </th>


                            </tr>

                        </thead>


                        <tbody>


                        <?php $serialNo = 1; ?>

                        <?php foreach (
                            $assignments
                            as $row
                        ): ?>


                            <tr>

                                <!-- SERIAL NUMBER -->

                                <td style="
                                    padding:13px;
                                    border-bottom:1px solid #eeeeee;
                                    text-align:center;
                                ">
                                    <?php echo $serialNo++; ?>
                                </td>


                                <!-- BATCH -->

                                <td style="
                                    padding:13px;
                                    border-bottom:1px solid #eeeeee;
                                ">


                                    <strong>

                                        <?php

                                        echo htmlspecialchars(
                                            $row["batch_name"]
                                        );

                                        ?>

                                    </strong>


                                    <br>


                                    <small>

                                        <?php

                                        echo htmlspecialchars(
                                            $row["station"]
                                        );

                                        ?>

                                    </small>


                                </td>



                                <!-- SUBJECT -->

                                <td style="
                                    padding:13px;
                                    border-bottom:1px solid #eeeeee;
                                ">


                                    <strong>

                                        <?php

                                        echo htmlspecialchars(
                                            $row["subject_code"]
                                        );

                                        ?>

                                    </strong>


                                    <br>


                                    <?php

                                    echo htmlspecialchars(
                                        $row["subject_name"]
                                    );

                                    ?>


                                </td>



                                <!-- FACULTY -->

                                <td style="
                                    padding:13px;
                                    border-bottom:1px solid #eeeeee;
                                ">


                                    <?php

                                    echo htmlspecialchars(
                                        $row["faculty_name"]
                                    );

                                    ?>


                                </td>



                                <!-- EMAIL -->

                                <td style="
                                    padding:13px;
                                    border-bottom:1px solid #eeeeee;
                                ">


                                    <?php

                                    echo htmlspecialchars(
                                        $row["email"]
                                    );

                                    ?>


                                </td>



                                <!-- TYPE -->

                                <td style="
                                    padding:13px;
                                    border-bottom:1px solid #eeeeee;
                                ">


                                    <?php if (
                                        $row["is_elective"]
                                    ): ?>


                                        <span style="
                                            display:inline-block;
                                            padding:5px 9px;
                                            border-radius:12px;
                                            font-size:12px;
                                            background:#eaf2f8;
                                            color:#1f4e79;
                                        ">

                                            Elective

                                        </span>


                                    <?php else: ?>


                                        <span style="
                                            display:inline-block;
                                            padding:5px 9px;
                                            border-radius:12px;
                                            font-size:12px;
                                            background:#eaf2f8;
                                            color:#1f4e79;
                                        ">

                                            Core

                                        </span>


                                    <?php endif; ?>


                                </td>



                                <!-- DELETE -->

                                <td style="
                                    padding:13px;
                                    border-bottom:1px solid #eeeeee;
                                ">


                                    <a
                                        href="delete.php?batch_id=<?php
                                            echo (int)
                                                $row["batch_id"];
                                        ?>&subject_id=<?php
                                            echo (int)
                                                $row["subject_id"];
                                        ?>&faculty_id=<?php
                                            echo (int)
                                                $row["faculty_id"];
                                        ?>"
                                        onclick="
                                            return confirm(
                                                'Remove this faculty assignment?'
                                            );
                                        "
                                        style="
                                            color:#a12622;
                                            text-decoration:none;
                                            font-weight:bold;
                                        "
                                    >

                                        Delete

                                    </a>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                        </tbody>


                    </table>


                <?php endif; ?>


            </div>


        </main>


    </div>


    <!-- COMMON FOOTER -->

    <?php require_once "../../includes/footer.php"; ?>


</div>