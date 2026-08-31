<?php

/*
|--------------------------------------------------------------------------
| FTC Grading & Tabulation Management System
| Admin - Assign Faculty
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../../config/database.php";

$pageTitle = "Assign Faculty";

$error = "";


/*
|--------------------------------------------------------------------------
| SAVE ASSIGNMENT
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $batchId = isset($_POST["batch_id"])
        ? (int) $_POST["batch_id"]
        : 0;

    $subjectId = isset($_POST["subject_id"])
        ? (int) $_POST["subject_id"]
        : 0;

    $facultyId = isset($_POST["faculty_id"])
        ? (int) $_POST["faculty_id"]
        : 0;

    $maxMarks = 100.00;


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (
        $batchId <= 0 ||
        $subjectId <= 0 ||
        $facultyId <= 0
    ) {

        $error =
            "Please select batch, subject, and faculty.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | CHECK BATCH
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT batch_id
                FROM batches
                WHERE batch_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $batchId
            ]);

            if (!$stmt->fetch()) {

                throw new Exception(
                    "Selected batch does not exist."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | CHECK SUBJECT
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                SELECT subject_id
                FROM subjects
                WHERE subject_id = ?
                LIMIT 1
            ");

            $stmt->execute([
                $subjectId
            ]);

            if (!$stmt->fetch()) {

                throw new Exception(
                    "Selected subject does not exist."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | CHECK FACULTY
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            | Only ACTIVE faculty can be assigned.
            |
            */

            $stmt = $pdo->prepare("
                SELECT
                    faculty_id,
                    faculty_name,
                    email
                FROM faculties
                WHERE faculty_id = ?
                  AND is_deleted = FALSE
                LIMIT 1
            ");

            $stmt->execute([
                $facultyId
            ]);

            $faculty = $stmt->fetch(
                PDO::FETCH_ASSOC
            );


            if (!$faculty) {

                throw new Exception(
                    "Selected faculty does not exist or has been deleted."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | SAVE ASSIGNMENT
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO subject_faculty_mapping
                (
                    batch_id,
                    subject_id,
                    faculty_id,
                    max_marks
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?
                )
                ON DUPLICATE KEY UPDATE
                    max_marks = VALUES(max_marks)
            ");

            $stmt->execute([
                $batchId,
                $subjectId,
                $facultyId,
                $maxMarks
            ]);


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            header(
                "Location: index.php?success="
                . urlencode(
                    "Faculty assigned successfully."
                )
            );

            exit;


        } catch (Exception $e) {

            $error =
                $e->getMessage();

        }

    }

}


/*
|--------------------------------------------------------------------------
| GET BATCHES
|--------------------------------------------------------------------------
*/

$batches = [];

try {

    $stmt = $pdo->query("
        SELECT
            batch_id,
            batch_name,
            station
        FROM batches
        ORDER BY batch_name ASC
    ");

    $batches =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (PDOException $e) {

    $error =
        $e->getMessage();

}


/*
|--------------------------------------------------------------------------
| GET SUBJECTS
|--------------------------------------------------------------------------
*/

$subjects = [];

try {

    $stmt = $pdo->query("
        SELECT
            subject_id,
            subject_code,
            subject_name,
            is_elective
        FROM subjects
        ORDER BY subject_code ASC
    ");

    $subjects =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (PDOException $e) {

    $error =
        $e->getMessage();

}


/*
|--------------------------------------------------------------------------
| GET ACTIVE FACULTIES ONLY
|--------------------------------------------------------------------------
|
| Soft-deleted faculty are excluded.
|
*/

$faculties = [];

try {

    $stmt = $pdo->query("
        SELECT
            faculty_id,
            faculty_name,
            email
        FROM faculties
        WHERE is_deleted = FALSE
        ORDER BY faculty_name ASC
    ");

    $faculties =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

} catch (PDOException $e) {

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


    <!-- =====================================================
         COMMON NAVBAR
    ====================================================== -->

    <?php require_once "../../includes/navbar.php"; ?>


    <!-- =====================================================
         PAGE LAYOUT
    ====================================================== -->

    <div style="
        display:flex;
        flex:1;
        min-height:0;
    ">


        <!-- =================================================
             COMMON SIDEBAR
        ================================================== -->

        <?php require_once "../../includes/sidebar.php"; ?>


        <!-- =================================================
             MAIN CONTENT
        ================================================== -->

        <main style="
            flex:1;
            padding:30px;
            background:#f4f6f8;
            overflow-x:auto;
        ">


            <!-- =================================================
                 BACK
            ================================================== -->

            <div style="
                margin-bottom:18px;
            ">

                <a
                    href="index.php"
                    style="
                        color:#1f4e79;
                        text-decoration:none;
                        font-weight:bold;
                    "
                >

                    ← Back to Faculty Assignments

                </a>

            </div>



            <!-- =================================================
                 PAGE HEADER
            ================================================== -->

            <div style="
                margin-bottom:25px;
            ">

                <h1 style="
                    margin:0 0 8px 0;
                    color:#1f4e79;
                    font-size:32px;
                ">

                    Assign Faculty

                </h1>


                <p style="
                    margin:0;
                    color:#666;
                    font-size:16px;
                ">

                    Assign a faculty member to a batch and subject.

                </p>

            </div>



            <!-- =================================================
                 ERROR
            ================================================== -->

            <?php if ($error !== ""): ?>

                <div style="
                    background:#fdecea;
                    color:#a12622;
                    border:1px solid #f5c6cb;
                    padding:14px;
                    border-radius:6px;
                    margin-bottom:20px;
                ">

                    <strong>
                        Error:
                    </strong>

                    <br><br>

                    <?php

                    echo htmlspecialchars(
                        $error
                    );

                    ?>

                </div>

            <?php endif; ?>



            <!-- =================================================
                 FORM CARD
            ================================================== -->

            <div style="
                background:#ffffff;
                max-width:930px;
                padding:30px;
                border-radius:10px;
                box-shadow:0 3px 12px rgba(0,0,0,0.08);
            ">


                <form
                    method="POST"
                    action=""
                >


                    <!-- =========================================
                         BATCH
                    ========================================== -->

                    <div style="
                        margin-bottom:22px;
                    ">


                        <label
                            for="batch_id"
                            style="
                                display:block;
                                margin-bottom:8px;
                                font-weight:bold;
                            "
                        >

                            Batch

                        </label>


                        <select
                            id="batch_id"
                            name="batch_id"
                            required
                            style="
                                width:100%;
                                padding:12px;
                                border:1px solid #ccc;
                                border-radius:6px;
                                font-size:15px;
                                background:#ffffff;
                            "
                        >


                            <option value="">

                                Select Batch

                            </option>


                            <?php foreach (
                                $batches
                                as $batch
                            ): ?>


                                <option
                                    value="<?php echo (int) $batch["batch_id"]; ?>"
                                    <?php

                                    if (
                                        isset($_POST["batch_id"]) &&
                                        (int) $_POST["batch_id"]
                                            === (int) $batch["batch_id"]
                                    ) {

                                        echo "selected";

                                    }

                                    ?>
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $batch["batch_name"]
                                    );


                                    if (
                                        !empty(
                                            $batch["station"]
                                        )
                                    ) {

                                        echo " - "
                                            . htmlspecialchars(
                                                $batch["station"]
                                            );

                                    }

                                    ?>

                                </option>


                            <?php endforeach; ?>


                        </select>


                    </div>



                    <!-- =========================================
                         SUBJECT
                    ========================================== -->

                    <div style="
                        margin-bottom:22px;
                    ">


                        <label
                            for="subject_id"
                            style="
                                display:block;
                                margin-bottom:8px;
                                font-weight:bold;
                            "
                        >

                            Subject

                        </label>


                        <select
                            id="subject_id"
                            name="subject_id"
                            required
                            style="
                                width:100%;
                                padding:12px;
                                border:1px solid #ccc;
                                border-radius:6px;
                                font-size:15px;
                                background:#ffffff;
                            "
                        >


                            <option value="">

                                Select Subject

                            </option>


                            <?php foreach (
                                $subjects
                                as $subject
                            ): ?>


                                <option
                                    value="<?php echo (int) $subject["subject_id"]; ?>"
                                    <?php

                                    if (
                                        isset($_POST["subject_id"]) &&
                                        (int) $_POST["subject_id"]
                                            === (int) $subject["subject_id"]
                                    ) {

                                        echo "selected";

                                    }

                                    ?>
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $subject["subject_code"]
                                    );

                                    echo " - ";

                                    echo htmlspecialchars(
                                        $subject["subject_name"]
                                    );


                                    if (
                                        (int) $subject["is_elective"] === 1
                                    ) {

                                        echo " (Elective)";

                                    }

                                    ?>

                                </option>


                            <?php endforeach; ?>


                        </select>


                    </div>



                    <!-- =========================================
                         FACULTY
                    ========================================== -->

                    <div style="
                        margin-bottom:22px;
                    ">


                        <label
                            for="faculty_id"
                            style="
                                display:block;
                                margin-bottom:8px;
                                font-weight:bold;
                            "
                        >

                            Faculty

                        </label>


                        <select
                            id="faculty_id"
                            name="faculty_id"
                            required
                            style="
                                width:100%;
                                padding:12px;
                                border:1px solid #ccc;
                                border-radius:6px;
                                font-size:15px;
                                background:#ffffff;
                            "
                        >


                            <option value="">

                                Select Faculty

                            </option>


                            <?php foreach (
                                $faculties
                                as $faculty
                            ): ?>


                                <option
                                    value="<?php echo (int) $faculty["faculty_id"]; ?>"
                                    <?php

                                    if (
                                        isset($_POST["faculty_id"]) &&
                                        (int) $_POST["faculty_id"]
                                            === (int) $faculty["faculty_id"]
                                    ) {

                                        echo "selected";

                                    }

                                    ?>
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $faculty["faculty_name"]
                                    );

                                    echo " - ";

                                    echo htmlspecialchars(
                                        $faculty["email"]
                                    );

                                    ?>

                                </option>


                            <?php endforeach; ?>


                        </select>


                        <div style="
                            margin-top:7px;
                            color:#777;
                            font-size:14px;
                        ">

                            Only active faculty members are shown.
                            Deleted faculty cannot be assigned.

                        </div>


                    </div>



                    <!-- =========================================
                         BUTTONS
                    ========================================== -->

                    <div style="
                        display:flex;
                        gap:12px;
                        align-items:center;
                    ">


                        <button
                            type="submit"
                            style="
                                background:#1f4e79;
                                color:#ffffff;
                                border:none;
                                padding:12px 28px;
                                border-radius:6px;
                                font-size:15px;
                                font-weight:bold;
                                cursor:pointer;
                            "
                        >

                            Assign Faculty

                        </button>


                        <a
                            href="index.php"
                            style="
                                background:#eeeeee;
                                color:#333333;
                                padding:12px 22px;
                                border-radius:6px;
                                text-decoration:none;
                                font-weight:bold;
                            "
                        >

                            Cancel

                        </a>


                    </div>


                </form>


            </div>


        </main>


    </div>


    <!-- =====================================================
         COMMON FOOTER
    ====================================================== -->

    <?php require_once "../../includes/footer.php"; ?>


</div>