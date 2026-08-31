<?php

/*
|--------------------------------------------------------------------------
| FTC Grading & Tabulation Management System
| Admin - View Student
|--------------------------------------------------------------------------
*/

require_once "../../config/database.php";

$pageTitle = "View Student";

$student = null;
$errorMessage = "";

$studentId = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;


/*
|--------------------------------------------------------------------------
| FETCH STUDENT
|--------------------------------------------------------------------------
*/

if ($studentId <= 0) {

    $errorMessage = "Invalid student ID.";

} else {

    try {

        $stmt = $pdo->prepare("
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
                eg.group_description,
                s.is_deleted,
                s.created_at

            FROM students s

            LEFT JOIN batches b
                ON s.batch_id = b.batch_id

            LEFT JOIN elective_groups eg
                ON s.group_code = eg.group_code

            WHERE s.student_id = :student_id
        ");


        $stmt->execute([
            ":student_id" => $studentId
        ]);


        $student = $stmt->fetch(
            PDO::FETCH_ASSOC
        );


        if (!$student) {

            $errorMessage =
                "Student record not found.";

        }

    } catch (PDOException $e) {

        $errorMessage =
            "Unable to load student record: " .
            $e->getMessage();

    }

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
                        Student Details
                    </h1>


                    <p style="
                        color:#666;
                    ">
                        View student information.
                    </p>

                </div>


                <a
                    href="index.php"
                    style="
                        display:inline-block;
                        background-color:#777;
                        color:#ffffff;
                        padding:10px 18px;
                        border-radius:5px;
                        text-decoration:none;
                    "
                >
                    Back
                </a>


            </div>


            <!-- =====================================================
                 ERROR
            ====================================================== -->

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


            <?php elseif ($student): ?>


                <!-- =================================================
                     STUDENT CARD
                ================================================== -->

                <div style="
                    background-color:#ffffff;
                    border-radius:8px;
                    padding:25px;
                    box-shadow:0 2px 8px rgba(0,0,0,0.08);
                    max-width:900px;
                ">


                    <div style="
                        display:grid;
                        grid-template-columns:180px 1fr;
                        row-gap:18px;
                    ">


                        <!-- STUDENT ID -->

                        <strong>
                            Student ID
                        </strong>

                        <span>
                            <?php

                            echo htmlspecialchars(
                                $student["student_id"]
                            );

                            ?>
                        </span>


                        <!-- ROLL NO -->

                        <strong>
                            Roll No
                        </strong>

                        <span>
                            <?php

                            echo htmlspecialchars(
                                $student["roll_no"]
                            );

                            ?>
                        </span>


                        <!-- STUDENT NAME -->

                        <strong>
                            Student Name
                        </strong>

                        <span>
                            <?php

                            echo htmlspecialchars(
                                $student["student_name"]
                            );

                            ?>
                        </span>


                        <!-- EMAIL -->

                        <strong>
                            Email
                        </strong>

                        <span>
                            <?php

                            if (
                                !empty(
                                    $student["email"]
                                )
                            ) {

                                echo htmlspecialchars(
                                    $student["email"]
                                );

                            } else {

                                echo "Not provided";

                            }

                            ?>
                        </span>


                        <!-- CONTACT NUMBER -->

                        <strong>
                            Contact Number
                        </strong>

                        <span>
                            <?php

                            if (
                                !empty(
                                    $student["contact_number"]
                                )
                            ) {

                                echo htmlspecialchars(
                                    $student["contact_number"]
                                );

                            } else {

                                echo "Not provided";

                            }

                            ?>
                        </span>


                        <!-- DESIGNATION -->

                        <strong>
                            Designation
                        </strong>

                        <span>
                            <?php

                            echo htmlspecialchars(
                                $student["designation"] ?? ""
                            );

                            ?>
                        </span>


                        <!-- QUALIFICATION -->

                        <strong>
                            Qualification
                        </strong>

                        <span>
                            <?php

                            echo htmlspecialchars(
                                $student["qualification"] ?? ""
                            );

                            ?>
                        </span>


                        <!-- RMC -->

                        <strong>
                            RMC
                        </strong>

                        <span>
                            <?php

                            echo htmlspecialchars(
                                $student["rmc"] ?? ""
                            );

                            ?>
                        </span>


                        <!-- BATCH -->

                        <strong>
                            Batch
                        </strong>

                        <span>
                            <?php

                            echo htmlspecialchars(
                                $student["batch_name"] ?? ""
                            );

                            ?>
                        </span>


                        <!-- ELECTIVE GROUP -->

                        <strong>
                            Elective Group
                        </strong>

                        <span>

                            <?php

                            if (
                                !empty(
                                    $student["group_code"]
                                )
                            ) {

                                echo htmlspecialchars(
                                    $student["group_code"] .
                                    " - " .
                                    (
                                        $student[
                                            "group_description"
                                        ] ?? ""
                                    )
                                );

                            } else {

                                echo "No Group";

                            }

                            ?>

                        </span>


                        <!-- STATUS -->

                        <strong>
                            Status
                        </strong>

                        <span>

                            <?php

                            if (
                                (int)
                                $student["is_deleted"]
                                === 1
                            ):

                            ?>

                                <span style="
                                    display:inline-block;
                                    background:#fdecea;
                                    color:#a12622;
                                    padding:5px 10px;
                                    border-radius:5px;
                                    font-weight:bold;
                                ">
                                    Deleted / Inactive
                                </span>

                            <?php else: ?>

                                <span style="
                                    display:inline-block;
                                    background:#e8f5e9;
                                    color:#278a4b;
                                    padding:5px 10px;
                                    border-radius:5px;
                                    font-weight:bold;
                                ">
                                    Active
                                </span>

                            <?php endif; ?>

                        </span>


                        <!-- CREATED AT -->

                        <strong>
                            Created At
                        </strong>

                        <span>
                            <?php

                            echo htmlspecialchars(
                                $student["created_at"]
                            );

                            ?>
                        </span>


                    </div>


                    <!-- =================================================
                         BUTTONS
                    ================================================== -->

                    <div style="
                        margin-top:30px;
                    ">


                        <?php

                        if (
                            (int)
                            $student["is_deleted"]
                            === 0
                        ):

                        ?>

                            <a
                                href="edit.php?id=<?php
                                    echo $student["student_id"];
                                ?>"
                                style="
                                    display:inline-block;
                                    background-color:#278a4b;
                                    color:#ffffff;
                                    padding:10px 18px;
                                    border-radius:5px;
                                    margin-right:10px;
                                    text-decoration:none;
                                "
                            >
                                Edit Student
                            </a>

                        <?php endif; ?>


                        <a
                            href="index.php"
                            style="
                                display:inline-block;
                                background-color:#777;
                                color:#ffffff;
                                padding:10px 18px;
                                border-radius:5px;
                                text-decoration:none;
                            "
                        >
                            Back
                        </a>


                    </div>


                </div>


            <?php endif; ?>


        </main>


    </div>


    <?php require_once "../../includes/footer.php"; ?>


</div>