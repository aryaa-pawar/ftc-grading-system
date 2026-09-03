<?php

require_once __DIR__ . "/../../includes/auth.php";
require_once __DIR__ . "/../../includes/csrf.php";
require_permission('marks.view');
require_once "../../config/database.php";

$pageTitle = "View Student Marks";


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/*
|--------------------------------------------------------------------------
| GET STUDENT ID
|--------------------------------------------------------------------------
*/

$studentId = filter_input(
    INPUT_GET,
    "student_id",
    FILTER_VALIDATE_INT
);

$errorMessage = "";
$successMessage = "";

$student = null;

$marks = [];

$currentUser = current_user();
$canAddScrutinyRemark = ($currentUser['role_name'] ?? '') === 'verification';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_scrutiny_remark'])) {
    enforceCsrf();

    if (!$canAddScrutinyRemark) {
        $errorMessage = "Only scrutiny users can add scrutiny remarks.";
    } else {
        $postedStudentId = (int)($_POST['student_id'] ?? 0);
        $scrutinyRemark = trim($_POST['scrutiny_remarks'] ?? '');

        if ($postedStudentId <= 0 || $postedStudentId !== (int)$studentId) {
            $errorMessage = "Invalid student.";
        } elseif (strlen($scrutinyRemark) > 500) {
            $errorMessage = "Scrutiny remark cannot exceed 500 characters.";
        } else {
            try {
                $remarkStmt = $pdo->prepare("UPDATE students SET scrutiny_remarks = :remark WHERE student_id = :student_id AND is_deleted = 0");
                $remarkStmt->execute([
                    ':remark' => $scrutinyRemark !== '' ? $scrutinyRemark : null,
                    ':student_id' => $postedStudentId
                ]);
                $successMessage = "Scrutiny remark saved successfully.";
            } catch (PDOException $e) {
                $errorMessage = "Unable to save scrutiny remark.";
            }
        }
    }
}


if (!$studentId) {

    $errorMessage = "Invalid student ID.";

} else {


    /*
    |--------------------------------------------------------------------------
    | GET STUDENT INFORMATION
    |--------------------------------------------------------------------------
    */

    try {

        $studentStmt = $pdo->prepare("
            SELECT
                s.student_id,
                s.roll_no,
                s.student_name,
                s.designation,
                s.qualification,
                s.rmc,
                s.group_code,
                s.custom_remarks,
                s.scrutiny_remarks,

                b.batch_id,
                b.batch_name,
                b.commenced_date,
                b.completed_date,
                b.station,

                eg.group_description

            FROM students s

            INNER JOIN batches b
                ON b.batch_id = s.batch_id

            LEFT JOIN elective_groups eg
                ON eg.group_code = s.group_code

            WHERE
                s.student_id = ?

                AND s.is_deleted = 0

            LIMIT 1
        ");

        $studentStmt->execute([
            $studentId
        ]);

        $student =
            $studentStmt->fetch(
                PDO::FETCH_ASSOC
            );


        if (!$student) {

            $errorMessage =
                "Student not found.";

        }


    } catch (PDOException $e) {

        $errorMessage =
            "Unable to load student information.";

    }


    /*
    |--------------------------------------------------------------------------
    | GET STUDENT MARKS
    |--------------------------------------------------------------------------
    */

    if ($student) {

        try {

            $marksStmt = $pdo->prepare("
                SELECT
                    MIN(fme.entry_id) AS entry_id,
                    fme.student_id,
                    fme.subject_id,
                    MIN(fme.faculty_id) AS faculty_id,
                    COALESCE(fme.paper_id, (SELECT MIN(psm0.paper_id)
                        FROM paper_subject_mapping psm0
                        WHERE psm0.batch_id = st.batch_id
                          AND psm0.subject_id = fme.subject_id)) AS paper_id,
                    fme.assessment_type_id,
                    CASE WHEN MAX(fme.is_absent) = 1 AND COUNT(CASE WHEN fme.marks_obtained IS NOT NULL THEN 1 END) = 0
                         THEN NULL ELSE ROUND(AVG(CASE WHEN fme.is_absent = 0 THEN fme.marks_obtained END), 2) END AS marks_obtained,
                    MAX(fme.is_absent) AS is_absent,
                    MAX(fme.graded_at) AS graded_at,

                    sub.subject_code,
                    sub.subject_name,
                    MIN(f.faculty_name) AS faculty_name,
                    MIN(at.assessment_type_name) AS assessment_type_name,

                    p.paper_code,
                    p.paper_name,
                    p.paper_type,

                    CASE
                        WHEN fme.assessment_type_id = 1 THEN COALESCE(NULLIF(psm.subject_max_internal, 0), p.max_internal, sfm.max_marks)
                        WHEN fme.assessment_type_id = 2 THEN COALESCE(NULLIF(psm.subject_max_midterm, 0), p.max_midterm, sfm.max_marks)
                        WHEN fme.assessment_type_id = 3 THEN COALESCE(NULLIF(psm.subject_max_final, 0), p.max_final, sfm.max_marks)
                        ELSE sfm.max_marks
                    END AS max_marks

                FROM faculty_marks_entry fme
                INNER JOIN students st ON st.student_id = fme.student_id

                INNER JOIN subjects sub
                    ON sub.subject_id = fme.subject_id

                INNER JOIN faculties f
                    ON f.faculty_id = fme.faculty_id

                INNER JOIN assessment_types at
                    ON at.assessment_type_id = fme.assessment_type_id

                LEFT JOIN papers p
                    ON p.paper_id = fme.paper_id

                LEFT JOIN paper_subject_mapping psm
                    ON psm.paper_id = fme.paper_id
                   AND psm.subject_id = fme.subject_id
                   AND psm.batch_id = ?

                LEFT JOIN subject_faculty_mapping sfm
                    ON sfm.batch_id = ?
                   AND sfm.subject_id = fme.subject_id
                   AND sfm.faculty_id = fme.faculty_id

                WHERE fme.student_id = ?
                GROUP BY fme.student_id, fme.subject_id,
                         COALESCE(fme.paper_id, (SELECT MIN(psm1.paper_id)
                           FROM paper_subject_mapping psm1
                          WHERE psm1.batch_id = st.batch_id
                            AND psm1.subject_id = fme.subject_id)),
                         fme.assessment_type_id,
                         sub.subject_code, sub.subject_name,
                         p.paper_code, p.paper_name, p.paper_type,
                         p.max_internal, p.max_midterm, p.max_final,
                         psm.subject_max_internal, psm.subject_max_midterm,
                         psm.subject_max_final, sfm.max_marks

                ORDER BY
                    sub.subject_name ASC,
                    p.paper_type ASC,
                    fme.assessment_type_id ASC,
                    f.faculty_name ASC
            ");

            $marksStmt->execute([
                $student["batch_id"],
                $student["batch_id"],
                $studentId
            ]);

            $marks =
                $marksStmt->fetchAll(
                    PDO::FETCH_ASSOC
                );


        } catch (PDOException $e) {

            /*
             * If the marks query fails, keep the
             * student information visible.
             */

            $marks = [];

        }

    }

}


/*
|--------------------------------------------------------------------------
| GROUP MARKS BY SUBJECT
|--------------------------------------------------------------------------
*/

$marksBySubject = [];


foreach ($marks as $mark) {

    $subjectId =
        (int)$mark["subject_id"];


    if (
        !isset(
            $marksBySubject[$subjectId]
        )
    ) {

        $marksBySubject[$subjectId] = [

            "subject_code" =>
                $mark["subject_code"],

            "subject_name" =>
                $mark["subject_name"],

            "entries" => []

        ];

    }


    $marksBySubject[$subjectId][
        "entries"
    ][] = $mark;

}


/*
|--------------------------------------------------------------------------
| GROUP DISPLAY
|--------------------------------------------------------------------------
*/

$groupDisplay = "—";


if (
    $student
    &&
    !empty($student["group_code"])
) {

    $groupDisplay =
        strtoupper(
            $student["group_code"]
        );

}


/*
|--------------------------------------------------------------------------
| LOAD COMMON HEADER
|--------------------------------------------------------------------------
*/

require_once "../../includes/header.php";

?>


<style>

/* =========================================================
   MAIN PAGE
========================================================= */

.view-page {

    padding:30px;

    background:#f4f6f8;

    min-height:100%;

}

.paper-type-badge {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 6px;
    vertical-align: middle;
}
.paper-type-badge.theory {
    background: #eaf2f8;
    color: #1f4e79;
    border: 1px solid #d4e6f1;
}
.paper-type-badge.practical {
    background: #fef9e7;
    color: #9a7d0a;
    border: 1px solid #f9e79f;
}
.paper-type-badge.viva, .paper-type-badge.project, .paper-type-badge.ojt {
    background: #f2f4f4;
    color: #4a5568;
    border: 1px solid #e2e8f0;
}


/* =========================================================
   PAGE HEADER
========================================================= */

.page-heading {

    display:flex;

    justify-content:space-between;

    align-items:flex-start;

    gap:20px;

    margin-bottom:25px;

    flex-wrap:wrap;

}


.page-heading h1 {

    margin:0 0 7px 0;

    color:#1f4e79;

    font-size:28px;

}


.page-heading p {

    margin:0;

    color:#666;

    font-size:14px;

}


.header-actions {

    display:flex;

    gap:8px;

    flex-wrap:wrap;

}


/* =========================================================
   BUTTONS
========================================================= */

.action-btn {

    display:inline-block;

    padding:10px 16px;

    border-radius:5px;

    text-decoration:none;

    font-size:13px;

    font-weight:bold;

}


.back-btn {

    background:#ffffff;

    color:#333;

    border:1px solid #ccc;

}


.edit-btn {

    background:#278a4b;

    color:#ffffff;

}


.action-btn:hover {

    opacity:.9;

}


/* =========================================================
   ERROR
========================================================= */

.error-box {

    background:#fdecea;

    color:#a12622;

    border:1px solid #f5c6cb;

    padding:15px;

    border-radius:6px;

    margin-bottom:20px;

}


/* =========================================================
   CARD
========================================================= */

.card {

    background:#ffffff;

    border-radius:8px;

    box-shadow:
        0 2px 8px
        rgba(0,0,0,.08);

    margin-bottom:25px;

    overflow:hidden;

}


.card-title {

    background:#1f4e79;

    color:#ffffff;

    padding:15px 20px;

    font-size:17px;

    font-weight:bold;

}


/* =========================================================
   STUDENT INFORMATION
========================================================= */

.student-info-grid {

    display:grid;

    grid-template-columns:
        repeat(3, 1fr);

}


.info-box {

    padding:16px 18px;

    border-right:1px solid #eeeeee;

    border-bottom:1px solid #eeeeee;

}


.info-box small {

    display:block;

    color:#777;

    font-size:11px;

    text-transform:uppercase;

    font-weight:bold;

    margin-bottom:5px;

}


.info-box strong {

    display:block;

    color:#1f4e79;

    font-size:14px;

    word-break:break-word;

}


/* =========================================================
   MARKS HEADER
========================================================= */

.marks-header {

    padding:20px;

    border-bottom:1px solid #eeeeee;

}


.marks-header h2 {

    margin:0 0 5px 0;

    color:#1f4e79;

    font-size:20px;

}


.marks-header p {

    margin:0;

    color:#777;

    font-size:13px;

}


/* =========================================================
   SUBJECT SECTION
========================================================= */

.subject-section {

    border-bottom:1px solid #dddddd;

}


.subject-section:last-child {

    border-bottom:none;

}


.subject-heading {

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:15px;

    padding:13px 18px;

    background:#eef5fa;

}


.subject-name {

    font-size:15px;

    font-weight:bold;

    color:#1f4e79;

}


.subject-code {

    padding:4px 8px;

    background:#ffffff;

    border:1px solid #d5d5d5;

    border-radius:4px;

    color:#666;

    font-size:11px;

    font-weight:bold;

}


/* =========================================================
   MARKS TABLE
========================================================= */

.table-wrapper {

    width:100%;

    overflow-x:auto;

}


.marks-table {

    width:100%;

    min-width:750px;

    border-collapse:collapse;

}


.marks-table th {

    background:#315f87;

    color:#ffffff;

    padding:11px;

    text-align:left;

    border:1px solid #365f82;

    font-size:12px;

}


.marks-table td {

    padding:11px;

    border:1px solid #e2e2e2;

    font-size:13px;

}


.marks-table tbody tr:nth-child(even) {

    background:#fafafa;

}


.marks-value {

    font-weight:bold;

}


.faculty-name {

    font-weight:bold;

    color:#333;

}


.assessment-name {

    color:#555;

}


.absent {

    display:inline-block;

    padding:4px 8px;

    background:#fdecea;

    color:#a12622;

    border-radius:4px;

    font-size:11px;

    font-weight:bold;

}


/* =========================================================
   NO MARKS
========================================================= */

.no-marks {

    padding:45px 20px;

    text-align:center;

    color:#777;

}


/* =========================================================
   CUSTOM REMARK
========================================================= */

.remark-body {

    padding:20px;

}


.remark-box {

    background:#f8fafb;

    border:1px solid #dddddd;

    border-radius:6px;

    padding:15px;

    line-height:1.6;

    white-space:pre-wrap;

    color:#333;

}


.no-remark {

    color:#888;

    font-style:italic;

}


/* =========================================================
   VIEW NOTE
========================================================= */

.view-note {

    background:#eef5fa;

    border-left:4px solid #1f4e79;

    padding:13px 15px;

    margin-bottom:25px;

    color:#555;

    font-size:12px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:900px) {

    .view-page {

        padding:20px;

    }


    .student-info-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media(max-width:600px) {

    .view-page {

        padding:15px;

    }


    .student-info-grid {

        grid-template-columns:1fr;

    }


    .page-heading h1 {

        font-size:23px;

    }

}


/* =========================================================
   PRINT
========================================================= */

@media print {

    .no-print {

        display:none !important;

    }


    .view-page {

        padding:0;

        background:#ffffff;

    }


    .card {

        box-shadow:none;

        border:1px solid #aaa;

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


    <div style="
        display:flex;
        flex:1;
        min-height:0;
    ">


        <!-- =================================================
             SIDEBAR
        ================================================== -->

        <?php require_once "../../includes/sidebar.php"; ?>


        <main style="
            flex:1;
            min-width:0;
        ">


            <div class="view-page">


                <!-- =================================================
                     PAGE HEADER
                ================================================== -->

                <div class="page-heading">


                    <div>

                        <h1>
                            View Student Marks
                        </h1>

                        <p>
                            Complete marks and assessment
                            details for the selected student.
                        </p>

                    </div>



                    <?php if ($student): ?>


                        <div
                            class="
                                header-actions
                                no-print
                            "
                        >


                            <a
                                href="index.php?batch_id=<?php
                                    echo (int)
                                        $student[
                                            "batch_id"
                                        ];
                                ?>"
                                class="
                                    action-btn
                                    back-btn
                                "
                            >

                                ← Back to Tabulation

                            </a>


                            <a
                                href="edit_internal.php?student_id=<?php
                                    echo (int)
                                        $studentId;
                                ?>"
                                class="
                                    action-btn
                                    edit-btn
                                "
                            >

                                Edit Marks

                            </a>


                        </div>


                    <?php endif; ?>


                </div>



                <!-- =================================================
                     ERROR
                ================================================== -->

                <?php if (
                    $errorMessage !== ""
                ): ?>


                    <div class="error-box">

                        <strong>
                            Error:
                        </strong>

                        <?php
                        echo h(
                            $errorMessage
                        );
                        ?>

                    </div>


                <?php endif; ?>


                <?php if ($successMessage !== ""): ?>

                    <div class="success-box">

                        <?php echo h($successMessage); ?>

                    </div>

                <?php endif; ?>



                <?php if ($student): ?>


                    <!-- =============================================
                         STUDENT INFORMATION
                    ============================================== -->

                    <div class="card">


                        <div class="card-title">

                            Student Information

                        </div>


                        <div
                            class="
                                student-info-grid
                            "
                        >


                            <div class="info-box">

                                <small>
                                    Student Name
                                </small>

                                <strong>

                                    <?php
                                    echo h(
                                        $student[
                                            "student_name"
                                        ]
                                    );
                                    ?>

                                </strong>

                            </div>


                            <div class="info-box">

                                <small>
                                    Roll No.
                                </small>

                                <strong>

                                    <?php
                                    echo h(
                                        $student[
                                            "roll_no"
                                        ]
                                    );
                                    ?>

                                </strong>

                            </div>


                            <div class="info-box">

                                <small>
                                    Designation
                                </small>

                                <strong>

                                    <?php
                                    echo h(
                                        $student[
                                            "designation"
                                        ]
                                    );
                                    ?>

                                </strong>

                            </div>


                            <div class="info-box">

                                <small>
                                    Qualification
                                </small>

                                <strong>

                                    <?php
                                    echo h(
                                        $student[
                                            "qualification"
                                        ]
                                    );
                                    ?>

                                </strong>

                            </div>


                            <div class="info-box">

                                <small>
                                    RMC
                                </small>

                                <strong>

                                    <?php
                                    echo h(
                                        $student[
                                            "rmc"
                                        ]
                                    );
                                    ?>

                                </strong>

                            </div>


                            <div class="info-box">

                                <small>
                                    Group
                                </small>

                                <strong>

                                    <?php
                                    echo h(
                                        $groupDisplay
                                    );
                                    ?>

                                </strong>

                            </div>


                            <div class="info-box">

                                <small>
                                    Batch
                                </small>

                                <strong>

                                    <?php
                                    echo h(
                                        $student[
                                            "batch_name"
                                        ]
                                    );
                                    ?>

                                </strong>

                            </div>


                            <div class="info-box">

                                <small>
                                    Station
                                </small>

                                <strong>

                                    <?php
                                    echo h(
                                        $student[
                                            "station"
                                        ]
                                    );
                                    ?>

                                </strong>

                            </div>


                            <div class="info-box">

                                <small>
                                    Commenced Date
                                </small>

                                <strong>

                                    <?php
                                    echo h(
                                        $student[
                                            "commenced_date"
                                        ]
                                    );
                                    ?>

                                </strong>

                            </div>


                            <div class="info-box">

                                <small>
                                    Completed Date
                                </small>

                                <strong>

                                    <?php
                                    echo h(
                                        $student[
                                            "completed_date"
                                        ]
                                    );
                                    ?>

                                </strong>

                            </div>


                        </div>

                    </div>



                    <!-- =============================================
                         DETAILED MARKS
                    ============================================== -->

                    <div class="card">


                        <div class="marks-header">

                            <h2>
                                Detailed Marks
                            </h2>

                            <p>
                                Each mark is shown with the
                                assessment type and the professor
                                who entered it.
                            </p>

                        </div>



                        <?php if (
                            count(
                                $marksBySubject
                            ) === 0
                        ): ?>


                            <div class="no-marks">

                                No marks have been entered
                                for this student yet.

                            </div>


                        <?php else: ?>


                            <?php foreach (
                                $marksBySubject
                                as $subject
                            ): ?>


                                <div
                                    class="
                                        subject-section
                                    "
                                >


                                    <div
                                        class="
                                            subject-heading
                                        "
                                    >


                                        <div
                                            class="
                                                subject-name
                                            "
                                        >

                                            <?php
                                            echo h(
                                                $subject[
                                                    "subject_name"
                                                ]
                                            );
                                            ?>

                                        </div>


                                        <div
                                            class="
                                                subject-code
                                            "
                                        >

                                            <?php
                                            echo h(
                                                $subject[
                                                    "subject_code"
                                                ]
                                            );
                                            ?>

                                        </div>


                                    </div>



                                    <div
                                        class="
                                            table-wrapper
                                        "
                                    >


                                        <table
                                            class="
                                                marks-table
                                            "
                                        >


                                            <thead>

                                                <tr>

                                                    <th>
                                                        Assessment
                                                    </th>

                                                    <th>
                                                        Marks
                                                    </th>

                                                    <th>
                                                        Maximum
                                                    </th>

                                                    <th>
                                                        Given By
                                                    </th>

                                                    <th>
                                                        Entered On
                                                    </th>

                                                </tr>

                                            </thead>


                                            <tbody>


                                            <?php foreach (
                                                $subject[
                                                    "entries"
                                                ] as $mark
                                            ): ?>


                                                <tr>


                                                    <td>

                                                        <span
                                                            class="
                                                                assessment-name
                                                            "
                                                        >

                                                            <?php
                                                            echo h(
                                                                $mark[
                                                                    "assessment_type_name"
                                                                ]
                                                            );
                                                            ?>

                                                        </span>

                                                        <?php if (!empty($mark["paper_type"])): ?>
                                                            <?php
                                                                $pTypeClass = strtolower($mark["paper_type"]);
                                                                $pCodeText  = !empty($mark["paper_code"]) ? " (" . h($mark["paper_code"]) . ")" : "";
                                                            ?>
                                                            <span class="paper-type-badge <?php echo $pTypeClass; ?>">
                                                                <?php echo h($mark["paper_type"]) . $pCodeText; ?>
                                                            </span>
                                                        <?php endif; ?>

                                                    </td>


                                                    <td>


                                                        <?php if (
                                                            (int)
                                                            $mark[
                                                                "is_absent"
                                                            ] === 1
                                                        ): ?>


                                                            <span
                                                                class="
                                                                    absent
                                                                "
                                                            >

                                                                Absent

                                                            </span>


                                                        <?php elseif (
                                                            $mark[
                                                                "marks_obtained"
                                                            ]
                                                            !== null
                                                        ): ?>


                                                            <span
                                                                class="
                                                                    marks-value
                                                                "
                                                            >

                                                                <?php
                                                                echo h(
                                                                    $mark[
                                                                        "marks_obtained"
                                                                    ]
                                                                );
                                                                ?>

                                                            </span>


                                                        <?php else: ?>


                                                            <span
                                                                style="
                                                                    color:#999;
                                                                "
                                                            >

                                                                —

                                                            </span>


                                                        <?php endif; ?>


                                                    </td>


                                                    <td>

                                                        <?php

                                                        if (
                                                            $mark[
                                                                "max_marks"
                                                            ]
                                                            !== null
                                                        ) {

                                                            echo h(
                                                                $mark[
                                                                    "max_marks"
                                                                ]
                                                            );

                                                        } else {

                                                            echo "—";

                                                        }

                                                        ?>

                                                    </td>


                                                    <td>

                                                        <span
                                                            class="
                                                                faculty-name
                                                            "
                                                        >

                                                            <?php
                                                            echo h(
                                                                $mark[
                                                                    "faculty_name"
                                                                ]
                                                            );
                                                            ?>

                                                        </span>

                                                    </td>


                                                    <td>

                                                        <?php

                                                        if (
                                                            !empty(
                                                                $mark[
                                                                    "graded_at"
                                                                ]
                                                            )
                                                        ) {

                                                            echo h(
                                                                date(
                                                                    "d-m-Y H:i",
                                                                    strtotime(
                                                                        $mark[
                                                                            "graded_at"
                                                                        ]
                                                                    )
                                                                )
                                                            );

                                                        } else {

                                                            echo "—";

                                                        }

                                                        ?>

                                                    </td>


                                                </tr>


                                            <?php endforeach; ?>


                                            </tbody>


                                        </table>


                                    </div>


                                </div>


                            <?php endforeach; ?>


                        <?php endif; ?>


                    </div>



                    <!-- =============================================
                         SCRUTINY REMARK
                    ============================================== -->

                    <div class="card">

                        <div class="card-title">

                            Scrutiny Remark

                        </div>


                        <div class="remark-body">

                            <?php if ($canAddScrutinyRemark): ?>

                                <form method="POST">

                                    <?php echo csrfField(); ?>

                                    <input type="hidden" name="student_id" value="<?php echo (int)$student['student_id']; ?>">

                                    <textarea
                                        name="scrutiny_remarks"
                                        maxlength="500"
                                        rows="4"
                                        class="remark-box"
                                        style="width:100%;"
                                    ><?php echo h($student['scrutiny_remarks'] ?? ''); ?></textarea>

                                    <button type="submit" name="save_scrutiny_remark" class="btn btn-primary mt-2">
                                        Save Scrutiny Remark
                                    </button>

                                </form>

                            <?php elseif (!empty(trim((string)($student['scrutiny_remarks'] ?? '')))): ?>

                                <div class="remark-box">
                                    <?php echo nl2br(h($student['scrutiny_remarks'])); ?>
                                </div>

                            <?php else: ?>

                                <div class="no-remark">
                                    No scrutiny remark has been added for this student.
                                </div>

                            <?php endif; ?>

                        </div>

                    </div>



                    <!-- =============================================
                         CUSTOM REMARK
                    ============================================== -->

                    <div class="card">


                        <div class="card-title">

                            Custom Remark

                        </div>


                        <div class="remark-body">


                            <?php if (
                                !empty(
                                    trim(
                                        (string)
                                        $student[
                                            "custom_remarks"
                                        ]
                                    )
                                )
                            ): ?>


                                <div
                                    class="remark-box"
                                >

                                    <?php
                                    echo nl2br(
                                        h(
                                            $student[
                                                "custom_remarks"
                                            ]
                                        )
                                    );
                                    ?>

                                </div>


                            <?php else: ?>


                                <div
                                    class="no-remark"
                                >

                                    No custom remark has
                                    been added for this
                                    student.

                                </div>


                            <?php endif; ?>


                        </div>


                    </div>



                    <!-- =============================================
                         VIEW ONLY NOTE
                    ============================================== -->

                    <div class="view-note">

                        <strong>
                            View Only:
                        </strong>

                        This page displays the student's
                        information, detailed marks, the professor
                        associated with each mark, and the custom
                        remark.

                        <br><br>

                        Marks are edited separately through
                        the Edit Marks section.

                    </div>


                <?php endif; ?>


            </div>


        </main>


    </div>


    <?php require_once "../../includes/footer.php"; ?>


</div>
