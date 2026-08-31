<?php

/*
|--------------------------------------------------------------------------
| FTC Grading & Tabulation Management System
| Admin - Student Management
|--------------------------------------------------------------------------
*/

require_once "../../config/database.php";

$pageTitle = "Student Management";

$students = [];
$rmcList = [];
$batchList = [];

$selectedRmc = trim(
    $_GET["rmc"] ?? ""
);
$selectedBatch = trim(
    $_GET["batch_id"] ?? ""
);

$databaseError = "";


/*
|--------------------------------------------------------------------------
| FETCH RMC VALUES
|--------------------------------------------------------------------------
|
| Gets all unique RMC values from active students.
|
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT DISTINCT rmc
        FROM students
        WHERE (is_deleted = 0 OR is_deleted IS NULL)
          AND rmc IS NOT NULL
          AND TRIM(rmc) <> ''
        ORDER BY rmc ASC
    ");

    $rmcList = $stmt->fetchAll(
        PDO::FETCH_COLUMN
    );

} catch (PDOException $e) {

    $databaseError =
        $e->getMessage();

}

try {

    $stmt = $pdo->query("\n        SELECT batch_id, batch_number, batch_name\n        FROM batches\n        ORDER BY batch_number ASC, batch_id ASC\n    ");

    $batchList = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $databaseError =
        $e->getMessage();

}


/*
|--------------------------------------------------------------------------
| FETCH STUDENTS
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | Base Query
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT
            s.student_id,
            s.roll_no,
            s.student_name,
            s.email,
            s.contact_number,
            s.designation,
            s.qualification,
            s.rmc,
            s.batch_id,
            b.batch_name,
            s.group_code,
            eg.group_description

        FROM students s

        LEFT JOIN batches b
            ON s.batch_id = b.batch_id

        LEFT JOIN elective_groups eg
            ON s.group_code = eg.group_code

        WHERE (s.is_deleted = 0 OR s.is_deleted IS NULL)
    ";


    /*
    |--------------------------------------------------------------------------
    | RMC FILTER
    |--------------------------------------------------------------------------
    */

    $params = [];


    if ($selectedRmc !== "") {

        $sql .= "
            AND s.rmc = :rmc
        ";

        $params[
            ":rmc"
        ] = $selectedRmc;

    }

    if ($selectedBatch !== "") {

        $sql .= "
            AND s.batch_id = :batch_id
        ";

        $params[
            ":batch_id"
        ] = (int)$selectedBatch;

    }


    /*
    |--------------------------------------------------------------------------
    | ORDER
    |--------------------------------------------------------------------------
    */

    $sql .= "
        ORDER BY s.student_id ASC
    ";


    /*
    |--------------------------------------------------------------------------
    | Execute
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $students = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


} catch (PDOException $e) {

    $databaseError =
        $e->getMessage();

}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

require_once "../../includes/header.php";

?>


<div style="
    display:flex;
    flex-direction:column;
    min-height:100vh;
">


    <?php require_once "../../includes/navbar.php"; ?>


    <div style="
        display:flex;
        flex:1;
    ">


        <?php require_once "../../includes/sidebar.php"; ?>


        <main style="
            flex:1;
            padding:30px;
            background-color:#f4f6f8;
        ">


            <!-- =====================================================
                 PAGE HEADER
            ====================================================== -->

            <div style="
                display:flex;
                justify-content:space-between;
                align-items:center;
                margin-bottom:25px;
            ">


                <div>

                    <h1 style="
                        font-size:28px;
                        color:#1f4e79;
                        margin-bottom:8px;
                    ">
                        Student Management
                    </h1>


                    <p style="
                        color:#666;
                        font-size:14px;
                    ">
                        Manage FTC trainee student records.
                    </p>

                </div>


                <a
                    href="add.php"
                    style="
                        display:inline-block;
                        background-color:#1f4e79;
                        color:#ffffff;
                        padding:10px 18px;
                        border-radius:5px;
                        font-size:14px;
                        font-weight:bold;
                        text-decoration:none;
                    "
                >
                    + Add Student
                </a>

                <a
                    href="import.php"
                    style="
                        display:inline-block;
                        background-color:#198754;
                        color:#ffffff;
                        padding:10px 18px;
                        border-radius:5px;
                        font-size:14px;
                        font-weight:bold;
                        text-decoration:none;
                        margin-left:8px;
                    "
                >
                    Import Excel
                </a>


            </div>


            <!-- =====================================================
                 DATABASE ERROR
            ====================================================== -->

            <?php if ($databaseError !== ""): ?>

                <div style="
                    background-color:#fdecea;
                    color:#a12622;
                    border:1px solid #f5c6cb;
                    padding:15px;
                    border-radius:6px;
                    margin-bottom:20px;
                ">

                    Unable to load student records
                    from the database.

                </div>

            <?php endif; ?>


            <!-- =====================================================
                 RMC FILTER CARD
            ====================================================== -->

            <div style="
                background:#ffffff;
                padding:18px 20px;
                border-radius:8px;
                margin-bottom:20px;
                box-shadow:0 2px 8px rgba(0,0,0,0.08);
            ">


                <form
                    method="GET"
                    action="index.php"
                    style="
                        display:flex;
                        align-items:end;
                        gap:12px;
                        flex-wrap:wrap;
                    "
                >


                    <div>

                        <label
                            for="batch_id"
                            style="
                                display:block;
                                font-weight:bold;
                                color:#333;
                                margin-bottom:7px;
                            "
                        >
                            Filter by Batch Number
                        </label>

                        <select
                            id="batch_id"
                            name="batch_id"
                            style="
                                min-width:220px;
                                padding:10px;
                                border:1px solid #ccc;
                                border-radius:5px;
                                background:#fff;
                            "
                        >
                            <option value="">All Batches</option>
                            <?php foreach ($batchList as $batch): ?>
                                <option
                                    value="<?php echo (int)$batch["batch_id"]; ?>"
                                    <?php echo $selectedBatch === (string)$batch["batch_id"] ? "selected" : ""; ?>
                                >
                                    <?php echo htmlspecialchars(($batch["batch_number"] ?? "") . " - " . $batch["batch_name"]); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                    </div>


                    <div>

                        <label
                            for="rmc"
                            style="
                                display:block;
                                font-weight:bold;
                                color:#333;
                                margin-bottom:7px;
                            "
                        >
                            Filter by RMC
                        </label>


                        <select
                            id="rmc"
                            name="rmc"
                            style="
                                min-width:220px;
                                padding:10px;
                                border:1px solid #ccc;
                                border-radius:5px;
                                background:#fff;
                            "
                        >

                            <option value="">
                                All RMC
                            </option>


                            <?php foreach (
                                $rmcList
                                as $rmc
                            ): ?>

                                <option
                                    value="<?php
                                        echo htmlspecialchars(
                                            $rmc
                                        );
                                    ?>"
                                    <?php

                                    echo (
                                        $selectedRmc ===
                                        $rmc
                                    )
                                    ? "selected"
                                    : "";

                                    ?>
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $rmc
                                    );

                                    ?>

                                </option>

                            <?php endforeach; ?>


                        </select>

                    </div>


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


                    <?php if ($selectedRmc !== "" || $selectedBatch !== ""): ?>

                        <a
                            href="index.php"
                            style="
                                display:inline-block;
                                background:#777;
                                color:#ffffff;
                                padding:10px 18px;
                                border-radius:5px;
                                text-decoration:none;
                            "
                        >
                            Clear Filter
                        </a>

                    <?php endif; ?>


                </form>


            </div>


            <!-- =====================================================
                 STUDENT TABLE
            ====================================================== -->

            <div style="
                background-color:#ffffff;
                border-radius:8px;
                padding:20px;
                box-shadow:0 2px 8px rgba(0,0,0,0.08);
                overflow-x:auto;
            ">


                <table style="
                    width:100%;
                    border-collapse:collapse;
                    min-width:1200px;
                ">


                    <thead>

                        <tr>

                            <th style="
                                padding:12px;
                                text-align:center;
                                background-color:#1f4e79;
                                color:#ffffff;
                            ">
                                S.No
                            </th>

                            <th style="
                                padding:12px;
                                text-align:left;
                                background-color:#1f4e79;
                                color:#ffffff;
                            ">
                                Roll No
                            </th>


                            <th style="
                                padding:12px;
                                text-align:left;
                                background-color:#1f4e79;
                                color:#ffffff;
                            ">
                                Student Name
                            </th>


                            <th style="
                                padding:12px;
                                text-align:left;
                                background-color:#1f4e79;
                                color:#ffffff;
                            ">
                                Email
                            </th>


                            <th style="
                                padding:12px;
                                text-align:left;
                                background-color:#1f4e79;
                                color:#ffffff;
                            ">
                                Contact Number
                            </th>


                            <th style="
                                padding:12px;
                                text-align:left;
                                background-color:#1f4e79;
                                color:#ffffff;
                            ">
                                Designation
                            </th>


                            <th style="
                                padding:12px;
                                text-align:left;
                                background-color:#1f4e79;
                                color:#ffffff;
                            ">
                                Qualification
                            </th>


                            <th style="
                                padding:12px;
                                text-align:left;
                                background-color:#1f4e79;
                                color:#ffffff;
                            ">
                                RMC
                            </th>


                            <th style="
                                padding:12px;
                                text-align:left;
                                background-color:#1f4e79;
                                color:#ffffff;
                            ">
                                Batch
                            </th>


                            <th style="
                                padding:12px;
                                text-align:left;
                                background-color:#1f4e79;
                                color:#ffffff;
                            ">
                                Group
                            </th>


                            <th style="
                                padding:12px;
                                text-align:center;
                                background-color:#1f4e79;
                                color:#ffffff;
                            ">
                                Actions
                            </th>


                        </tr>

                    </thead>


                    <tbody>


                        <?php if (
                            count($students) > 0
                        ): ?>


                            <?php $serialNo = 1; ?>

                            <?php foreach (
                                $students
                                as $student
                            ): ?>


                                <tr>

                                    <!-- SERIAL NUMBER -->

                                    <td style="
                                        padding:12px;
                                        border-bottom:1px solid #eeeeee;
                                        text-align:center;
                                    ">

                                        <?php echo $serialNo++; ?>

                                    </td>


                                    <!-- ROLL NO -->

                                    <td style="
                                        padding:12px;
                                        border-bottom:1px solid #eeeeee;
                                    ">

                                        <?php

                                        echo htmlspecialchars(
                                            $student["roll_no"]
                                        );

                                        ?>

                                    </td>


                                    <!-- NAME -->

                                    <td style="
                                        padding:12px;
                                        border-bottom:1px solid #eeeeee;
                                    ">

                                        <strong>

                                            <?php

                                            echo htmlspecialchars(
                                                $student["student_name"]
                                            );

                                            ?>

                                        </strong>

                                    </td>


                                    <!-- EMAIL -->

                                    <td style="
                                        padding:12px;
                                        border-bottom:1px solid #eeeeee;
                                    ">

                                        <?php

                                        echo htmlspecialchars(
                                            $student["email"] ?? ""
                                        );

                                        ?>

                                    </td>


                                    <!-- CONTACT -->

                                    <td style="
                                        padding:12px;
                                        border-bottom:1px solid #eeeeee;
                                    ">

                                        <?php

                                        echo htmlspecialchars(
                                            $student["contact_number"] ?? ""
                                        );

                                        ?>

                                    </td>


                                    <!-- DESIGNATION -->

                                    <td style="
                                        padding:12px;
                                        border-bottom:1px solid #eeeeee;
                                    ">

                                        <?php

                                        echo htmlspecialchars(
                                            $student["designation"] ?? ""
                                        );

                                        ?>

                                    </td>


                                    <!-- QUALIFICATION -->

                                    <td style="
                                        padding:12px;
                                        border-bottom:1px solid #eeeeee;
                                    ">

                                        <?php

                                        echo htmlspecialchars(
                                            $student["qualification"] ?? ""
                                        );

                                        ?>

                                    </td>


                                    <!-- RMC -->

                                    <td style="
                                        padding:12px;
                                        border-bottom:1px solid #eeeeee;
                                    ">

                                        <?php

                                        echo htmlspecialchars(
                                            $student["rmc"] ?? ""
                                        );

                                        ?>

                                    </td>


                                    <!-- BATCH -->

                                    <td style="
                                        padding:12px;
                                        border-bottom:1px solid #eeeeee;
                                    ">

                                        <?php

                                        echo htmlspecialchars(
                                            $student["batch_name"] ?? ""
                                        );

                                        ?>

                                    </td>


                                    <!-- GROUP -->

                                    <td style="
                                        padding:12px;
                                        border-bottom:1px solid #eeeeee;
                                    ">

                                        <?php

                                        echo htmlspecialchars(
                                            $student["group_code"] ?? ""
                                        );

                                        ?>

                                    </td>


                                    <!-- ACTIONS -->

                                    <td style="
                                        padding:12px;
                                        border-bottom:1px solid #eeeeee;
                                        text-align:center;
                                        white-space:nowrap;
                                    ">


                                        <a
                                            href="view.php?id=<?php
                                                echo $student[
                                                    "student_id"
                                                ];
                                            ?>"
                                            style="
                                                color:#1f4e79;
                                                margin-right:10px;
                                                text-decoration:none;
                                                font-weight:bold;
                                            "
                                        >
                                            View
                                        </a>


                                        <a
                                            href="edit.php?id=<?php
                                                echo $student[
                                                    "student_id"
                                                ];
                                            ?>"
                                            style="
                                                color:#278a4b;
                                                margin-right:10px;
                                                text-decoration:none;
                                                font-weight:bold;
                                            "
                                        >
                                            Edit
                                        </a>


                                        <a
                                            href="delete.php?id=<?php
                                                echo $student[
                                                    "student_id"
                                                ];
                                            ?>"
                                            onclick="
                                                return confirm(
                                                    'Are you sure you want to delete this student?'
                                                );
                                            "
                                            style="
                                                color:#c0392b;
                                                text-decoration:none;
                                                font-weight:bold;
                                            "
                                        >
                                            Delete
                                        </a>


                                    </td>


                                </tr>


                            <?php endforeach; ?>


                        <?php else: ?>


                            <tr>

                                <td
                                    colspan="10"
                                    style="
                                        padding:30px;
                                        text-align:center;
                                        color:#666;
                                    "
                                >

                                    <?php if (
                                        $selectedRmc !== ""
                                    ): ?>

                                        No students found
                                        for RMC
                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $selectedRmc
                                            );
                                            ?>
                                        </strong>.

                                    <?php else: ?>

                                        No student records found.

                                    <?php endif; ?>

                                </td>

                            </tr>


                        <?php endif; ?>


                    </tbody>


                </table>


            </div>


        </main>


    </div>


    <?php require_once "../../includes/footer.php"; ?>


</div>