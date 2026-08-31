<?php

/*
|--------------------------------------------------------------------------
| FTC Grading & Tabulation Management System
| Admin - Edit Student
|--------------------------------------------------------------------------
*/

require_once "../../config/database.php";

$pageTitle = "Edit Student";

$errorMessage = "";

$studentId = isset($_GET["id"])
    ? (int) $_GET["id"]
    : (int) ($_POST["student_id"] ?? 0);


/*
|--------------------------------------------------------------------------
| Validate Student ID
|--------------------------------------------------------------------------
*/

if ($studentId <= 0) {

    die("Invalid student ID.");

}


/*
|--------------------------------------------------------------------------
| Fetch Batches
|--------------------------------------------------------------------------
*/

$batches = [];

try {

    $stmt = $pdo->query("
        SELECT
            batch_id,
            batch_name
        FROM batches
        ORDER BY batch_id ASC
    ");

    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $errorMessage = "Unable to load batches.";

}


/*
|--------------------------------------------------------------------------
| Fetch Elective Groups
|--------------------------------------------------------------------------
*/

$groups = [];

try {

    $stmt = $pdo->query("
        SELECT
            group_code,
            group_description
        FROM elective_groups
        ORDER BY group_code ASC
    ");

    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $errorMessage = "Unable to load elective groups.";

}


/*
|--------------------------------------------------------------------------
| Fetch Student
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            student_id,
            roll_no,
            student_name,
            email,
            contact_number,
            designation,
            qualification,
            rmc,
            batch_id,
            group_code
        FROM students
        WHERE student_id = :student_id
    ");

    $stmt->execute([
        ":student_id" => $studentId
    ]);

    $student = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$student) {

        die("Student record not found.");

    }

} catch (PDOException $e) {

    die(
        "Unable to load student record: " .
        htmlspecialchars($e->getMessage())
    );

}


/*
|--------------------------------------------------------------------------
| Handle Update
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $rollNo = trim(
        $_POST["roll_no"] ?? ""
    );

    $studentName = trim(
        $_POST["student_name"] ?? ""
    );

    $email = trim(
        $_POST["email"] ?? ""
    );

    $contactNumber = trim(
        $_POST["contact_number"] ?? ""
    );

    $designation = trim(
        $_POST["designation"] ?? ""
    );

    $qualification = trim(
        $_POST["qualification"] ?? ""
    );

    $rmc = trim(
        $_POST["rmc"] ?? ""
    );

    $batchId = $_POST["batch_id"] ?? "";

    $groupCode = $_POST["group_code"] ?? "";


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $rollNo === "" ||
        $studentName === "" ||
        $batchId === ""
    ) {

        $errorMessage =
            "Roll No, Student Name and Batch are required.";

    } elseif ($email !== "" && !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )) {

        $errorMessage =
            "Please enter a valid email address.";

    } elseif ($contactNumber !== "" && !preg_match(
        '/^[0-9+\-\s]{7,15}$/',
        $contactNumber
    )) {

        $errorMessage =
            "Contact number must contain between 7 and 15 digits.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Update Student
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE students

                SET
                    roll_no = :roll_no,
                    student_name = :student_name,
                    email = :email,
                    contact_number = :contact_number,
                    designation = :designation,
                    qualification = :qualification,
                    rmc = :rmc,
                    batch_id = :batch_id,
                    group_code = :group_code

                WHERE student_id = :student_id
            ");


            $stmt->execute([

                ":roll_no" =>
                    $rollNo,

                ":student_name" =>
                    $studentName,

                ":email" =>
                    $email !== ""
                    ? $email
                    : null,

                ":contact_number" =>
                    $contactNumber !== ""
                    ? $contactNumber
                    : null,

                ":designation" =>
                    $designation !== ""
                    ? $designation
                    : null,

                ":qualification" =>
                    $qualification !== ""
                    ? $qualification
                    : null,

                ":rmc" =>
                    $rmc !== ""
                    ? $rmc
                    : null,

                ":batch_id" =>
                    $batchId,

                ":group_code" =>
                    $groupCode !== ""
                    ? $groupCode
                    : null,

                ":student_id" =>
                    $studentId

            ]);


            /*
            |--------------------------------------------------------------------------
            | Success
            |--------------------------------------------------------------------------
            */

            header(
                "Location: view.php?id=" .
                urlencode($studentId)
            );

            exit;


        } catch (PDOException $e) {

            if ($e->getCode() === "23000") {

                $errorMessage =
                    "The Roll No already exists or the selected Batch/Group is invalid.";

            } else {

                $errorMessage =
                    "Unable to update student: " .
                    $e->getMessage();

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Keep Entered Values After Validation Error
    |--------------------------------------------------------------------------
    */

    $student["roll_no"] =
        $rollNo;

    $student["student_name"] =
        $studentName;

    $student["email"] =
        $email;

    $student["contact_number"] =
        $contactNumber;

    $student["designation"] =
        $designation;

    $student["qualification"] =
        $qualification;

    $student["rmc"] =
        $rmc;

    $student["batch_id"] =
        $batchId;

    $student["group_code"] =
        $groupCode;

}


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


            <!-- PAGE TITLE -->

            <div style="
                margin-bottom:25px;
            ">

                <h1 style="
                    font-size:28px;
                    color:#1f4e79;
                    margin-bottom:8px;
                ">
                    Edit Student
                </h1>


                <p style="
                    color:#666;
                ">
                    Update the student record.
                </p>

            </div>


            <!-- ERROR -->

            <?php if ($errorMessage !== ""): ?>

                <div style="
                    background-color:#fdecea;
                    color:#a12622;
                    border:1px solid #f5c6cb;
                    padding:15px;
                    border-radius:6px;
                    margin-bottom:20px;
                ">

                    <?php

                    echo htmlspecialchars(
                        $errorMessage
                    );

                    ?>

                </div>

            <?php endif; ?>


            <!-- FORM CARD -->

            <div style="
                background-color:#ffffff;
                border-radius:8px;
                padding:25px;
                box-shadow:0 2px 8px rgba(0,0,0,0.08);
                max-width:900px;
            ">


                <form method="POST">


                    <!-- STUDENT ID -->

                    <input
                        type="hidden"
                        name="student_id"
                        value="<?php
                            echo htmlspecialchars(
                                $student["student_id"]
                            );
                        ?>"
                    >


                    <!-- ROLL NO -->

                    <div style="
                        margin-bottom:18px;
                    ">

                        <label>

                            Roll No
                            <strong>*</strong>

                        </label>


                        <input
                            type="text"
                            name="roll_no"
                            value="<?php
                                echo htmlspecialchars(
                                    $student["roll_no"]
                                );
                            ?>"
                            required
                            style="
                                width:100%;
                                padding:10px;
                                margin-top:6px;
                                border:1px solid #ccc;
                                border-radius:5px;
                            "
                        >

                    </div>


                    <!-- STUDENT NAME -->

                    <div style="
                        margin-bottom:18px;
                    ">

                        <label>

                            Student Name
                            <strong>*</strong>

                        </label>


                        <input
                            type="text"
                            name="student_name"
                            value="<?php
                                echo htmlspecialchars(
                                    $student["student_name"]
                                );
                            ?>"
                            required
                            style="
                                width:100%;
                                padding:10px;
                                margin-top:6px;
                                border:1px solid #ccc;
                                border-radius:5px;
                            "
                        >

                    </div>


                    <!-- EMAIL -->

                    <div style="
                        margin-bottom:18px;
                    ">

                        <label>
                            Email
                        </label>


                        <input
                            type="email"
                            name="email"
                            value="<?php
                                echo htmlspecialchars(
                                    $student["email"] ?? ""
                                );
                            ?>"
                            placeholder="example@email.com"
                            style="
                                width:100%;
                                padding:10px;
                                margin-top:6px;
                                border:1px solid #ccc;
                                border-radius:5px;
                            "
                        >

                    </div>


                    <!-- CONTACT NUMBER -->

                    <div style="
                        margin-bottom:18px;
                    ">

                        <label>
                            Contact Number
                        </label>


                        <input
                            type="text"
                            name="contact_number"
                            value="<?php
                                echo htmlspecialchars(
                                    $student["contact_number"] ?? ""
                                );
                            ?>"
                            maxlength="20"
                            placeholder="e.g. 9876543210"
                            style="
                                width:100%;
                                padding:10px;
                                margin-top:6px;
                                border:1px solid #ccc;
                                border-radius:5px;
                            "
                        >

                    </div>


                    <!-- DESIGNATION -->

                    <div style="
                        margin-bottom:18px;
                    ">

                        <label>
                            Designation
                        </label>


                        <input
                            type="text"
                            name="designation"
                            value="<?php
                                echo htmlspecialchars(
                                    $student["designation"] ?? ""
                                );
                            ?>"
                            style="
                                width:100%;
                                padding:10px;
                                margin-top:6px;
                                border:1px solid #ccc;
                                border-radius:5px;
                            "
                        >

                    </div>


                    <!-- QUALIFICATION -->

                    <div style="
                        margin-bottom:18px;
                    ">

                        <label>
                            Qualification
                        </label>


                        <input
                            type="text"
                            name="qualification"
                            value="<?php
                                echo htmlspecialchars(
                                    $student["qualification"] ?? ""
                                );
                            ?>"
                            style="
                                width:100%;
                                padding:10px;
                                margin-top:6px;
                                border:1px solid #ccc;
                                border-radius:5px;
                            "
                        >

                    </div>


                    <!-- RMC -->

                    <div style="
                        margin-bottom:18px;
                    ">

                        <label>
                            RMC
                        </label>


                        <input
                            type="text"
                            name="rmc"
                            value="<?php
                                echo htmlspecialchars(
                                    $student["rmc"] ?? ""
                                );
                            ?>"
                            placeholder="Enter RMC"
                            style="
                                width:100%;
                                padding:10px;
                                margin-top:6px;
                                border:1px solid #ccc;
                                border-radius:5px;
                            "
                        >

                    </div>


                    <!-- BATCH -->

                    <div style="
                        margin-bottom:18px;
                    ">

                        <label>

                            Batch
                            <strong>*</strong>

                        </label>


                        <select
                            name="batch_id"
                            required
                            style="
                                width:100%;
                                padding:10px;
                                margin-top:6px;
                                border:1px solid #ccc;
                                border-radius:5px;
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
                                    value="<?php
                                        echo $batch["batch_id"];
                                    ?>"
                                    <?php

                                    echo (
                                        $student["batch_id"]
                                        == $batch["batch_id"]
                                    )
                                    ? "selected"
                                    : "";

                                    ?>
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $batch["batch_name"]
                                    );

                                    ?>

                                </option>

                            <?php endforeach; ?>


                        </select>

                    </div>


                    <!-- ELECTIVE GROUP -->

                    <div style="
                        margin-bottom:22px;
                    ">

                        <label>
                            Elective Group
                        </label>


                        <select
                            name="group_code"
                            style="
                                width:100%;
                                padding:10px;
                                margin-top:6px;
                                border:1px solid #ccc;
                                border-radius:5px;
                            "
                        >

                            <option value="">
                                No Group
                            </option>


                            <?php foreach (
                                $groups
                                as $group
                            ): ?>

                                <option
                                    value="<?php
                                        echo htmlspecialchars(
                                            $group["group_code"]
                                        );
                                    ?>"
                                    <?php

                                    echo (
                                        $student["group_code"]
                                        === $group["group_code"]
                                    )
                                    ? "selected"
                                    : "";

                                    ?>
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $group["group_code"] .
                                        " - " .
                                        $group["group_description"]
                                    );

                                    ?>

                                </option>

                            <?php endforeach; ?>


                        </select>

                    </div>


                    <!-- BUTTONS -->

                    <button
                        type="submit"
                        style="
                            background-color:#278a4b;
                            color:#ffffff;
                            border:none;
                            padding:11px 20px;
                            border-radius:5px;
                            cursor:pointer;
                            margin-right:10px;
                        "
                    >
                        Update Student
                    </button>


                    <a
                        href="view.php?id=<?php echo $studentId; ?>"
                        style="
                            display:inline-block;
                            background-color:#777;
                            color:#ffffff;
                            padding:11px 20px;
                            border-radius:5px;
                            text-decoration:none;
                        "
                    >
                        Cancel
                    </a>


                </form>


            </div>


        </main>


    </div>


    <?php require_once "../../includes/footer.php"; ?>


</div>