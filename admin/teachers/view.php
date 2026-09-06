<?php

require_once "../../config/database.php";

$pageTitle = "View Faculty";

$facultyId = isset($_GET["id"])
    ? (int) $_GET["id"]
    : 0;


/*
|--------------------------------------------------------------------------
| VALIDATE FACULTY ID
|--------------------------------------------------------------------------
*/

if ($facultyId <= 0) {

    die("Invalid faculty ID.");

}


/*
|--------------------------------------------------------------------------
| GET FACULTY
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        faculty_id,
        faculty_name,
        email,
        contact_number,
        office_location,
        is_deleted,
        created_at
    FROM faculties
    WHERE faculty_id = :faculty_id
");


$stmt->execute([
    ":faculty_id" => $facultyId
]);


$teacher = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$teacher) {

    die("Faculty not found.");

}


/*
|--------------------------------------------------------------------------
| FACULTY STATUS
|--------------------------------------------------------------------------
*/

$isDeleted = (int) ($teacher["is_deleted"] ?? 0);

?>


<?php require_once "../../includes/header.php"; ?>


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
            background:#f4f6f8;
        ">


            <!-- PAGE TITLE -->

            <h1 style="
                color:#1f4e79;
                margin:0 0 8px 0;
            ">
                Faculty Details
            </h1>


            <p style="
                color:#666;
                margin:0 0 25px 0;
            ">
                View faculty information.
            </p>


            <!-- DETAILS CARD -->

            <div style="
                background:#ffffff;
                padding:25px;
                border-radius:8px;
                max-width:800px;
                box-shadow:0 2px 8px rgba(0,0,0,0.08);
            ">


                <div style="
                    display:grid;
                    grid-template-columns:180px 1fr;
                    gap:18px;
                    font-size:16px;
                ">


                    <!-- FACULTY ID -->

                    <strong>
                        Faculty ID
                    </strong>

                    <span>
                        <?php
                        echo htmlspecialchars(
                            $teacher["faculty_id"]
                        );
                        ?>
                    </span>


                    <!-- FACULTY NAME -->

                    <strong>
                        Faculty Name
                    </strong>

                    <span>
                        <?php
                        echo htmlspecialchars(
                            $teacher["faculty_name"]
                        );
                        ?>
                    </span>


                    <!-- EMAIL -->

                    <strong>
                        Email
                    </strong>

                    <span>
                        <?php
                        echo htmlspecialchars(
                            $teacher["email"]
                        );
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
                                $teacher["contact_number"]
                            )
                        ) {

                            echo htmlspecialchars(
                                $teacher["contact_number"]
                            );

                        } else {

                            echo "Not provided";

                        }

                        ?>
                    </span>


                    <!-- OFFICE LOCATION -->

                    <strong>
                        Office Location
                    </strong>

                    <span>
                        <?php

                        if (
                            !empty(
                                $teacher["office_location"]
                            )
                        ) {

                            echo htmlspecialchars(
                                $teacher["office_location"]
                            );

                        } else {

                            echo "Not provided";

                        }

                        ?>
                    </span>


                    <!-- STATUS -->

                    <strong>
                        Status
                    </strong>

                    <span>

                        <?php if ($isDeleted === 1): ?>

                            <span style="
                                color:#a12622;
                                background:#fdecea;
                                padding:5px 10px;
                                border-radius:5px;
                                font-weight:bold;
                            ">
                                Deleted
                            </span>

                        <?php else: ?>

                            <span style="
                                color:#278a4b;
                                background:#e8f5e9;
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
                            $teacher["created_at"]
                        );
                        ?>
                    </span>


                </div>


                <!-- BUTTONS -->

                <div style="
                    margin-top:30px;
                ">


                    <?php if ($isDeleted === 0): ?>

                        <a
                            href="edit.php?id=<?php echo $facultyId; ?>"
                            style="
                                display:inline-block;
                                background:#278a4b;
                                color:white;
                                padding:10px 18px;
                                border-radius:5px;
                                margin-right:10px;
                                text-decoration:none;
                            "
                        >
                            Edit
                        </a>


                    <?php endif; ?>


                    <a
                        href="index.php"
                        style="
                            display:inline-block;
                            background:#777;
                            color:white;
                            padding:10px 18px;
                            border-radius:5px;
                            text-decoration:none;
                        "
                    >
                        Back
                    </a>


                </div>


            </div>


        </main>


    </div>


    <?php require_once "../../includes/footer.php"; ?>


</div>
