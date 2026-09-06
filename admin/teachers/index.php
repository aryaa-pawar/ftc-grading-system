<?php

require_once "../../config/database.php";

$pageTitle = "Faculty Management";

$teachers = [];

$errorMessage = "";
$successMessage = "";


/*
|--------------------------------------------------------------------------
| SUCCESS / ERROR MESSAGES
|--------------------------------------------------------------------------
*/

if (isset($_GET["success"])) {

    if ($_GET["success"] === "deactivated") {

        $successMessage =
            "Faculty deactivated successfully.";

    }

}


if (isset($_GET["error"])) {

    if ($_GET["error"] === "invalid") {

        $errorMessage =
            "Invalid faculty ID.";

    }

    elseif ($_GET["error"] === "notfound") {

        $errorMessage =
            "Faculty not found or already deactivated.";

    }

}


/*
|--------------------------------------------------------------------------
| GET ACTIVE FACULTY
|--------------------------------------------------------------------------
|
| is_deleted = 0 means the faculty is active.
|
| Soft-deleted faculty members are NOT shown here.
|
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT
            faculty_id,
            faculty_name,
            email,
            contact_number,
            office_location
        FROM faculties
        WHERE is_deleted = 0
        ORDER BY faculty_id ASC
    ");


    $teachers = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );


} catch (PDOException $e) {

    $errorMessage =
        "Unable to load faculty: " .
        $e->getMessage();

}


require_once "../../includes/header.php";

?>


<style>

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

.faculty-page {

    padding: 30px;

    background: #f4f6f8;

    min-height: 100%;

}


/*
|--------------------------------------------------------------------------
| PAGE HEADER
|--------------------------------------------------------------------------
*/

.faculty-page-header {

    display: flex;

    justify-content: space-between;

    align-items: flex-start;

    margin-bottom: 25px;

}


.faculty-page-header h1 {

    margin: 0 0 8px 0;

    color: #1f4e79;

    font-size: 30px;

}


.faculty-page-header p {

    margin: 0;

    color: #666;

}


/*
|--------------------------------------------------------------------------
| ADD BUTTON
|--------------------------------------------------------------------------
*/

.add-faculty-btn {

    display: inline-block;

    background: #1f4e79;

    color: white;

    text-decoration: none;

    padding: 11px 18px;

    border-radius: 6px;

    font-weight: bold;

}


.add-faculty-btn:hover {

    background: #163a5c;

}


/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

.success-message {

    background: #e8f5e9;

    color: #278a4b;

    border: 1px solid #c8e6c9;

    padding: 13px 15px;

    border-radius: 6px;

    margin-bottom: 20px;

}


.error-message {

    background: #fdecea;

    color: #a12622;

    border: 1px solid #f5c6cb;

    padding: 13px 15px;

    border-radius: 6px;

    margin-bottom: 20px;

}


/*
|--------------------------------------------------------------------------
| TABLE CARD
|--------------------------------------------------------------------------
*/

.faculty-table-card {

    background: white;

    border-radius: 8px;

    padding: 20px;

    box-shadow:
        0 2px 8px rgba(0,0,0,0.08);

    overflow-x: auto;

}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.faculty-table {

    width: 100%;

    border-collapse: collapse;

}


.faculty-table th {

    background: #1f4e79;

    color: white;

    padding: 13px 12px;

    text-align: left;

    font-size: 14px;

    white-space: nowrap;

}


.faculty-table td {

    padding: 13px 12px;

    border-bottom: 1px solid #eeeeee;

    font-size: 14px;

    vertical-align: middle;

}


.faculty-table tbody tr:hover {

    background: #f8fafb;

}


/*
|--------------------------------------------------------------------------
| ACTION BUTTONS
|--------------------------------------------------------------------------
*/

.faculty-action {

    display: inline-block;

    text-decoration: none;

    border-radius: 4px;

    font-size: 12px;

    font-weight: bold;

    padding: 6px 9px;

    margin-right: 4px;

}


/*
|--------------------------------------------------------------------------
| VIEW
|--------------------------------------------------------------------------
*/

.faculty-view {

    background: #eaf2f8;

    color: #1f4e79;

}


/*
|--------------------------------------------------------------------------
| EDIT
|--------------------------------------------------------------------------
*/

.faculty-edit {

    background: #fff3cd;

    color: #856404;

}


/*
|--------------------------------------------------------------------------
| DELETE
|--------------------------------------------------------------------------
*/

.faculty-delete {

    background: #fdecea;

    color: #a12622;

    border: 1px solid #f5c6cb;

}


/*
|--------------------------------------------------------------------------
| DELETE HOVER
|--------------------------------------------------------------------------
*/

.faculty-delete:hover {

    background: #f8d7da;

}


/*
|--------------------------------------------------------------------------
| EMPTY
|--------------------------------------------------------------------------
*/

.no-faculty {

    text-align: center;

    padding: 40px;

    color: #777;

}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 900px) {

    .faculty-page-header {

        flex-direction: column;

        gap: 15px;

    }

}

</style>


<div style="
    display:flex;
    flex-direction:column;
    min-height:100vh;
">


    <!-- =====================================================
         NAVBAR
    ====================================================== -->

    <?php require_once "../../includes/navbar.php"; ?>


    <!-- =====================================================
         SIDEBAR + CONTENT
    ====================================================== -->

    <div style="
        display:flex;
        flex:1;
    ">


        <!-- =================================================
             SIDEBAR
        ================================================== -->

        <?php require_once "../../includes/sidebar.php"; ?>


        <!-- =================================================
             MAIN CONTENT
        ================================================== -->

        <main style="
            flex:1;
            min-width:0;
        ">


            <div class="faculty-page">


                <!-- =========================================
                     PAGE HEADER
                ========================================== -->

                <div class="faculty-page-header">


                    <div>

                        <h1>
                            Faculty Management
                        </h1>

                        <p>
                            Manage faculty members.
                        </p>

                    </div>


                </div>


                <!-- =========================================
                     SUCCESS MESSAGE
                ========================================== -->

                <?php if ($successMessage !== ""): ?>

                    <div class="success-message">

                        <?php

                        echo htmlspecialchars(
                            $successMessage
                        );

                        ?>

                    </div>

                <?php endif; ?>


                <!-- =========================================
                     ERROR MESSAGE
                ========================================== -->

                <?php if ($errorMessage !== ""): ?>

                    <div class="error-message">

                        <?php

                        echo htmlspecialchars(
                            $errorMessage
                        );

                        ?>

                    </div>

                <?php endif; ?>


                <!-- =========================================
                     FACULTY TABLE
                ========================================== -->

                <div class="faculty-table-card">


                    <?php if (count($teachers) > 0): ?>


                        <table class="faculty-table">


                            <thead>

                                <tr>

                                    <th>
                                        S.No
                                    </th>

                                    <th>
                                        Faculty Name
                                    </th>

                                    <th>
                                        Email
                                    </th>

                                    <th>
                                        Contact Number
                                    </th>

                                    <th>
                                        Office Location
                                    </th>

                                    <th>
                                        Actions
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php $serialNo = 1; ?>

                                <?php foreach (
                                    $teachers
                                    as $teacher
                                ): ?>


                                    <tr>


                                        <!-- FACULTY ID -->

                                        <td>

                                            <?php

                                            echo $serialNo++;

                                            ?>

                                        </td>


                                        <!-- NAME -->

                                        <td>

                                            <strong>

                                                <?php

                                                echo htmlspecialchars(
                                                    $teacher["faculty_name"]
                                                );

                                                ?>

                                            </strong>

                                        </td>


                                        <!-- EMAIL -->

                                        <td>

                                            <?php

                                            echo htmlspecialchars(
                                                $teacher["email"]
                                            );

                                            ?>

                                        </td>


                                        <!-- CONTACT -->

                                        <td>

                                            <?php

                                            if (
                                                !empty(
                                                    $teacher[
                                                        "contact_number"
                                                    ]
                                                )
                                            ) {

                                                echo htmlspecialchars(
                                                    $teacher[
                                                        "contact_number"
                                                    ]
                                                );

                                            } else {

                                                echo "Not provided";

                                            }

                                            ?>

                                        </td>


                                        <!-- OFFICE -->

                                        <td>

                                            <?php

                                            if (
                                                !empty(
                                                    $teacher[
                                                        "office_location"
                                                    ]
                                                )
                                            ) {

                                                echo htmlspecialchars(
                                                    $teacher[
                                                        "office_location"
                                                    ]
                                                );

                                            } else {

                                                echo "Not provided";

                                            }

                                            ?>

                                        </td>


                                        <!-- ACTIONS -->

                                        <td
                                            style="
                                                white-space:nowrap;
                                            "
                                        >


                                            <!-- VIEW -->

                                            <a
                                                href="view.php?id=<?php
                                                    echo $teacher[
                                                        "faculty_id"
                                                    ];
                                                ?>"
                                                class="
                                                    faculty-action
                                                    faculty-view
                                                "
                                            >
                                                View
                                            </a>


                                            <!-- EDIT -->

                                            <a
                                                href="edit.php?id=<?php
                                                    echo $teacher[
                                                        "faculty_id"
                                                    ];
                                                ?>"
                                                class="
                                                    faculty-action
                                                    faculty-edit
                                                "
                                            >
                                                Edit
                                            </a>


                                        </td>


                                    </tr>


                                <?php endforeach; ?>


                            </tbody>


                        </table>


                    <?php else: ?>


                        <div class="no-faculty">

                            No active faculty members found.

                        </div>


                    <?php endif; ?>


                </div>


            </div>


        </main>


    </div>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <?php require_once "../../includes/footer.php"; ?>


</div>
