<?php

/*
|--------------------------------------------------------------------------
| FTC Grading & Tabulation Management System
| Admin - Delete Student
|--------------------------------------------------------------------------
| Uses soft delete through students.is_deleted.
|--------------------------------------------------------------------------
*/

require_once "../../config/database.php";

$studentId = isset($_GET["id"])
    ? (int)$_GET["id"]
    : (int)($_POST["student_id"] ?? 0);


if ($studentId <= 0) {

    header("Location: index.php");
    exit;

}


/*
|--------------------------------------------------------------------------
| Handle Delete Confirmation
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    try {

        $stmt = $pdo->prepare("
            UPDATE students
            SET is_deleted = 1
            WHERE student_id = :student_id
        ");

        $stmt->execute([
            ":student_id" => $studentId
        ]);


        header("Location: index.php");
        exit;


    } catch (PDOException $e) {

        $errorMessage = "Unable to delete student.";

    }

}


/*
|--------------------------------------------------------------------------
| Fetch Student Information
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            student_id,
            roll_no,
            student_name
        FROM students
        WHERE student_id = :student_id
          AND (is_deleted = 0 OR is_deleted IS NULL)
    ");

    $stmt->execute([
        ":student_id" => $studentId
    ]);

    $student = $stmt->fetch();


    if (!$student) {

        die("Student record not found.");

    }

} catch (PDOException $e) {

    die("Unable to load student record.");

}


$pageTitle = "Delete Student";

require_once "../../includes/header.php";

?>

<div style="display:flex; flex-direction:column; min-height:100vh;">

    <?php require_once "../../includes/navbar.php"; ?>

    <div style="display:flex; flex:1;">

        <?php require_once "../../includes/sidebar.php"; ?>

        <main style="
            flex:1;
            padding:30px;
            background-color:#f4f6f8;
        ">


            <div style="margin-bottom:25px;">

                <h1 style="
                    font-size:28px;
                    color:#c0392b;
                    margin-bottom:8px;
                ">
                    Delete Student
                </h1>

                <p style="color:#666;">
                    Confirm removal of the student record.
                </p>

            </div>


            <div style="
                background-color:#ffffff;
                border-radius:8px;
                padding:25px;
                max-width:600px;
                box-shadow:0 2px 8px rgba(0,0,0,0.08);
            ">


                <div style="
                    background-color:#fff3cd;
                    color:#856404;
                    padding:15px;
                    border-radius:6px;
                    margin-bottom:20px;
                ">

                    The student will be marked as inactive.

                </div>


                <p style="margin-bottom:10px;">

                    <strong>Roll No:</strong>

                    <?php
                    echo htmlspecialchars($student["roll_no"]);
                    ?>

                </p>


                <p style="margin-bottom:25px;">

                    <strong>Student Name:</strong>

                    <?php
                    echo htmlspecialchars($student["student_name"]);
                    ?>

                </p>


                <form method="POST">

                    <input
                        type="hidden"
                        name="student_id"
                        value="<?php echo $student["student_id"]; ?>"
                    >


                    <button
                        type="submit"
                        style="
                            background-color:#c0392b;
                            color:#ffffff;
                            border:none;
                            padding:11px 20px;
                            border-radius:5px;
                            cursor:pointer;
                            margin-right:10px;
                        "
                    >
                        Confirm Delete
                    </button>


                    <a
                        href="index.php"
                        style="
                            display:inline-block;
                            background-color:#777;
                            color:#ffffff;
                            padding:11px 20px;
                            border-radius:5px;
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