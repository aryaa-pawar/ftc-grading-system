<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../../includes/auth.php";
require_permission('marks.edit');
require_once "../../config/database.php";

$pageTitle = "Edit Marks";


/* =========================================================
   HELPER
========================================================= */

function h($value)
{
    return htmlspecialchars(
        (string)($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


/* =========================================================
   GET STUDENT ID
========================================================= */

$studentId = 0;

if (isset($_GET['student_id'])) {
    $studentId = (int)$_GET['student_id'];
}

if (
    $studentId <= 0 &&
    isset($_POST['student_id'])
) {
    $studentId = (int)$_POST['student_id'];
}


/* =========================================================
   VARIABLES
========================================================= */

$student = null;

$marks = [];

$successMessage = '';

$errorMessage = '';

$studentLocked = false;


/* =========================================================
   SAVE CUSTOM REMARK
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_remark'])
) {

    $studentId =
        (int)($_POST['student_id'] ?? 0);

    $remark =
        trim(
            $_POST['custom_remark'] ?? ''
        );


    if ($studentId <= 0) {

        $errorMessage =
            "Invalid student.";

    } elseif (
        strlen($remark) > 500
    ) {

        $errorMessage =
            "Custom remark cannot exceed 500 characters.";

    } else {

        try {

            $stmt = $pdo->prepare("
                UPDATE students

                SET custom_remarks = :remark

                WHERE student_id = :student_id

                AND is_deleted = 0
            ");

            $stmt->execute([
                ':remark' =>
                    $remark !== ''
                        ? $remark
                        : null,

                ':student_id' =>
                    $studentId
            ]);


            $successMessage =
                "Custom remark saved successfully.";

        } catch (PDOException $e) {

            $errorMessage =
                "Unable to save custom remark.";

        }

    }
}


/* =========================================================
   SAVE INTERNAL MARK
========================================================= */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['save_internal'])
) {
    $studentId = (int)($_POST['student_id'] ?? 0);
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $entryId   = (int)($_POST['entry_id'] ?? 0);
    $newMarks  = trim($_POST['new_marks'] ?? '');
    $reason    = trim($_POST['reason'] ?? '');

    if ($studentId <= 0) {
        $errorMessage = "Invalid student.";
    } elseif ($subjectId <= 0) {
        $errorMessage = "Invalid subject.";
    } elseif ($newMarks === '') {
        $errorMessage = "Please enter Internal marks.";
    } elseif (!is_numeric($newMarks)) {
        $errorMessage = "Internal marks must be numeric.";
    } else {
        $newMarks = (float)$newMarks;

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT s.student_id, s.roll_no, s.student_name, s.batch_id, b.is_locked
                FROM students s
                INNER JOIN batches b ON b.batch_id = s.batch_id
                WHERE s.student_id = :student_id AND s.is_deleted = 0
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([':student_id' => $studentId]);
            $studentEdit = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$studentEdit) {
                throw new Exception("Student not found.");
            }

            if ((int)($studentEdit['is_locked'] ?? 0) === 1) {
                throw new Exception("Marks are locked for this batch. No changes are allowed.");
            }

            // Get target mark entry with correct assessment max marks
            $stmt = $pdo->prepare("
                SELECT
                    fme.entry_id,
                    fme.student_id,
                    fme.subject_id,
                    fme.faculty_id,
                    fme.paper_id,
                    fme.assessment_type_id,
                    fme.marks_obtained,
                    fme.is_absent,
                    sub.subject_code,
                    sub.subject_name,
                    p.paper_code,
                    COALESCE(NULLIF(psm.subject_max_internal, 0), p.max_internal, sfm.max_marks) AS max_marks
                FROM faculty_marks_entry fme
                INNER JOIN subjects sub ON sub.subject_id = fme.subject_id
                LEFT JOIN papers p ON p.paper_id = fme.paper_id
                LEFT JOIN paper_subject_mapping psm ON psm.paper_id = fme.paper_id AND psm.subject_id = fme.subject_id AND psm.batch_id = :batch_id
                LEFT JOIN subject_faculty_mapping sfm ON sfm.batch_id = :sfm_batch_id AND sfm.subject_id = fme.subject_id AND sfm.faculty_id = fme.faculty_id
                WHERE fme.student_id = :student_id
                  AND fme.subject_id = :subject_id
                  AND fme.assessment_type_id = 1
                  AND (fme.entry_id = :entry_id OR :entry_id_filter = 0)
                ORDER BY fme.entry_id ASC
                LIMIT 1
                FOR UPDATE
            ");
            $stmt->execute([
                ':batch_id' => $studentEdit['batch_id'],
                ':sfm_batch_id' => $studentEdit['batch_id'],
                ':student_id' => $studentId,
                ':subject_id' => $subjectId,
                ':entry_id' => $entryId,
                ':entry_id_filter' => $entryId
            ]);
            $existingMark = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existingMark) {
                throw new Exception("No Internal mark exists for this subject.");
            }

            if ((int)$existingMark['is_absent'] === 1) {
                throw new Exception("Absent Internal marks cannot be edited.");
            }

            if ($existingMark['marks_obtained'] === null) {
                throw new Exception("Current Internal mark is empty.");
            }

            $oldMarks = (float)$existingMark['marks_obtained'];
            $maxMarks = (float)$existingMark['max_marks'];

            if ($maxMarks <= 0) {
                $maxMarks = 100.00;
            }

            // Check range
            if ($newMarks < 0 || $newMarks > $maxMarks) {
                throw new Exception("Marks cannot exceed maximum marks of " . number_format($maxMarks, 2) . " (must be between 0 and " . number_format($maxMarks, 2) . ").");
            }

            $diff = $newMarks - $oldMarks;

            if (abs($diff) < 0.001) {
                throw new Exception("New marks are the same as current marks.");
            }

            if (abs($diff) > 3.00) {
                throw new Exception("Internal mark modifications cannot exceed 3 marks.");
            }

            // ENFORCE GRACE MARKS RULES (FOR MARK INCREASES)
            if ($diff > 0) {
                // Rule A: Grace marks can only be given in 1 subject for a student
                $stmt = $pdo->prepare("
                    SELECT DISTINCT subject_id
                    FROM admin_marks_audit
                    WHERE student_id = :student_id
                      AND new_marks > old_marks
                ");
                $stmt->execute([':student_id' => $studentId]);
                $previousGracedSubjects = $stmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($previousGracedSubjects as $gracedSubId) {
                    if ((int)$gracedSubId !== $subjectId) {
                        $subNameStmt = $pdo->prepare("SELECT subject_name FROM subjects WHERE subject_id = ?");
                        $subNameStmt->execute([$gracedSubId]);
                        $otherSubName = $subNameStmt->fetchColumn() ?: "another subject";
                        throw new Exception("Grace marks have already been awarded in '{$otherSubName}' for this student. Grace marks can only be given in 1 subject.");
                    }
                }

                // Rule B: Maximum 3 grace marks total across all edits for the student
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(new_marks - old_marks), 0)
                    FROM admin_marks_audit
                    WHERE student_id = :student_id
                      AND new_marks > old_marks
                ");
                $stmt->execute([':student_id' => $studentId]);
                $existingGraceSum = (float)$stmt->fetchColumn();

                $totalGraceWithNew = $existingGraceSum + $diff;
                if ($totalGraceWithNew > 3.00) {
                    $remainingGrace = max(0, 3.00 - $existingGraceSum);
                    throw new Exception("Grace marks limit exceeded. A student can receive a maximum of 3 grace marks in total (Already awarded: " . number_format($existingGraceSum, 2) . " marks, Remaining available: " . number_format($remainingGrace, 2) . " marks).");
                }
            }

            // UPDATE MARK (STRICTLY INTERNAL MARKS)
            $stmt = $pdo->prepare("
                UPDATE faculty_marks_entry
                SET marks_obtained = :marks,
                    graded_at = CURRENT_TIMESTAMP
                WHERE entry_id = :entry_id
                  AND assessment_type_id = 1
            ");
            $stmt->execute([
                ':marks' => $newMarks,
                ':entry_id' => $existingMark['entry_id']
            ]);

            // CREATE ADMIN AUDIT LOG
            $stmt = $pdo->prepare("
                INSERT INTO admin_marks_audit
                (entry_id, student_id, subject_id, old_marks, new_marks, admin_name, reason)
                VALUES
                (:entry_id, :student_id, :subject_id, :old_marks, :new_marks, :admin_name, :reason)
            ");
            $stmt->execute([
                ':entry_id' => $existingMark['entry_id'],
                ':student_id' => $studentId,
                ':subject_id' => $subjectId,
                ':old_marks' => $oldMarks,
                ':new_marks' => $newMarks,
                ':admin_name' => 'Admin',
                ':reason' => $reason !== '' ? $reason : 'Admin mark grace/correction'
            ]);

            $pdo->commit();
            $successMessage = "Internal mark updated successfully.";

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errorMessage = $e->getMessage();
        }
    }
}


/* =========================================================
   LOAD STUDENT INFORMATION
========================================================= */

if ($studentId > 0) {

    try {

        $stmt = $pdo->prepare("
            SELECT

                s.student_id,
                s.roll_no,
                s.student_name,
                s.designation,
                s.qualification,
                s.rmc,
                s.group_code,
                s.custom_remarks,
                s.batch_id,

                b.batch_name,
                b.commenced_date,
                b.completed_date,
                b.station

            FROM students s

            LEFT JOIN batches b
                ON b.batch_id = s.batch_id

            WHERE

                s.student_id = :student_id

                AND s.is_deleted = 0

            LIMIT 1
        ");

        $stmt->execute([
            ':student_id' =>
                $studentId
        ]);


        $student =
            $stmt->fetch(
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

}


/* =========================================================
   CHECK THIS STUDENT'S GRACE MARKS STATUS
========================================================= */

$gracedSubjectId = null;
$gracedSubjectName = null;
$totalGraceSum = 0.00;
$studentLocked = false;

if ($student) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT audit.subject_id, sub.subject_name
            FROM admin_marks_audit audit
            INNER JOIN subjects sub ON sub.subject_id = audit.subject_id
            WHERE audit.student_id = :student_id
              AND audit.new_marks > audit.old_marks
            LIMIT 1
        ");
        $stmt->execute([':student_id' => $studentId]);
        $gracedRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($gracedRow) {
            $gracedSubjectId = (int)$gracedRow['subject_id'];
            $gracedSubjectName = $gracedRow['subject_name'];
        }

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(new_marks - old_marks), 0)
            FROM admin_marks_audit
            WHERE student_id = :student_id
              AND new_marks > old_marks
        ");
        $stmt->execute([':student_id' => $studentId]);
        $totalGraceSum = (float)$stmt->fetchColumn();

    } catch (PDOException $e) {}
}


/* =========================================================
   LOAD INTERNAL MARKS

   ONLY SUBJECTS PRESENT IN TABULATION
========================================================= */

if ($student) {

    try {

        $stmt = $pdo->prepare("
            SELECT
                fme.entry_id,
                fme.student_id,
                fme.subject_id,
                fme.faculty_id,
                fme.marks_obtained,
                fme.is_absent,

                f.faculty_name,

                sub.subject_code,
                sub.subject_name,

                p.paper_id,
                p.paper_code,
                p.paper_name,
                p.paper_type,

                COALESCE(NULLIF(psm.subject_max_internal, 0), p.max_internal, sfm.max_marks) AS max_marks

            FROM faculty_marks_entry fme

            INNER JOIN subjects sub
                ON sub.subject_id = fme.subject_id

            INNER JOIN faculties f
                ON f.faculty_id = fme.faculty_id

            INNER JOIN paper_subject_mapping psm
                ON psm.subject_id = fme.subject_id
               AND psm.paper_id = fme.paper_id
               AND psm.batch_id = :psm_batch_id

            INNER JOIN papers p
                ON p.paper_id = psm.paper_id

            LEFT JOIN subject_faculty_mapping sfm
                ON sfm.batch_id = :batch_id
               AND sfm.subject_id = fme.subject_id
               AND sfm.faculty_id = fme.faculty_id

            WHERE
                fme.student_id = :student_id
                AND fme.assessment_type_id = 1

            ORDER BY
                p.paper_id ASC,
                sub.subject_code ASC,
                fme.entry_id ASC
        ");

        $stmt->execute([
            ':batch_id' => $student['batch_id'],
            ':psm_batch_id' => $student['batch_id'],
            ':student_id' => $studentId
        ]);

        $marks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $errorMessage = "Unable to load Internal marks.";
    }
}


/* =========================================================
   GROUP
========================================================= */

$group =
    !empty($student['group_code'])
        ? strtoupper(
            $student['group_code']
        )
        : '—';


/* =========================================================
   HEADER
========================================================= */

require_once "../../includes/header.php";

?>


<style>

/* =========================================================
   PAGE
========================================================= */

.edit-page {

    padding: 30px;

    background: #f4f6f8;

    min-height: 100%;

}


/* =========================================================
   HEADER
========================================================= */

.page-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;

}


.page-header h1 {

    margin: 0;

    color: #1f4e79;

}


.page-header p {

    margin-top: 6px;

    color: #666;

}


.back-btn {

    background: #777;

    color: white;

    text-decoration: none;

    padding: 10px 16px;

    border-radius: 5px;

}


/* =========================================================
   MESSAGE
========================================================= */

.success {

    background: #e8f5e9;

    color: #216b36;

    padding: 14px;

    border-radius: 6px;

    margin-bottom: 20px;

}


.error {

    background: #fdecea;

    color: #a12622;

    padding: 14px;

    border-radius: 6px;

    margin-bottom: 20px;

}


/* =========================================================
   CARD
========================================================= */

.card {

    background: white;

    border-radius: 8px;

    margin-bottom: 25px;

    box-shadow:
        0 2px 8px rgba(0,0,0,.08);

    overflow: hidden;

}


.card-title {

    background: #1f4e79;

    color: white;

    padding: 15px 20px;

    font-weight: bold;

    font-size: 17px;

}


/* =========================================================
   STUDENT INFORMATION
========================================================= */

.student-grid {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

}


.student-item {

    padding: 16px;

    border-right: 1px solid #eee;

    border-bottom: 1px solid #eee;

}


.student-item label {

    display: block;

    font-size: 11px;

    color: #777;

    text-transform: uppercase;

    font-weight: bold;

    margin-bottom: 5px;

}


.student-item strong {

    color: #1f4e79;

}


/* =========================================================
   RULE BOX
========================================================= */

.rule-box {

    margin: 20px;

    padding: 15px;

    background: #eef5fa;

    border-left: 4px solid #1f4e79;

    line-height: 1.7;

    font-size: 13px;

}


.rule-box.locked {

    background: #f1f1f1;

    border-left-color: #777;

}


/* =========================================================
   TABLE
========================================================= */

.table-container {

    overflow-x: auto;

}


.marks-table {

    width: 100%;

    min-width: 1000px;

    border-collapse: collapse;

}


.marks-table th {

    background: #315f87;

    color: white;

    padding: 12px;

    text-align: left;

    font-size: 12px;

}


.marks-table td {

    padding: 12px;

    border: 1px solid #ddd;

    font-size: 13px;

}


.marks-table tr:nth-child(even) {

    background: #fafafa;

}


.locked-row {

    background: #f1f1f1 !important;

    color: #777;

}


/* =========================================================
   INPUT
========================================================= */

.mark-input {

    width: 80px;

    padding: 8px;

    text-align: center;

    border: 1px solid #aaa;

    border-radius: 5px;

}


.reason-input {

    width: 140px;

    padding: 7px;

    border: 1px solid #aaa;

    border-radius: 5px;

    margin-bottom: 5px;

}


/* =========================================================
   BUTTON
========================================================= */

.save-btn {

    background: #278a4b;

    color: white;

    border: none;

    padding: 8px 14px;

    border-radius: 5px;

    cursor: pointer;

    font-weight: bold;

}


/* =========================================================
   BADGES
========================================================= */

.locked-badge {

    background: #e4e4e4;

    border: 1px solid #ccc;

    color: #666;

    padding: 6px 10px;

    border-radius: 5px;

    font-size: 11px;

    font-weight: bold;

}


.absent-badge {

    background: #fdecea;

    color: #a12622;

    padding: 6px 10px;

    border-radius: 5px;

    font-size: 11px;

    font-weight: bold;

}


/* =========================================================
   REMARK
========================================================= */

.remark-area {

    padding: 20px;

}


.remark-area textarea {

    width: 100%;

    min-height: 120px;

    resize: vertical;

    padding: 12px;

    border: 1px solid #aaa;

    border-radius: 6px;

    font-family: Arial, sans-serif;

}


.remark-save {

    margin-top: 10px;

    background: #1f4e79;

    color: white;

    border: none;

    padding: 10px 18px;

    border-radius: 5px;

    cursor: pointer;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width: 900px) {

    .student-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }

}


@media(max-width: 600px) {

    .edit-page {

        padding: 15px;

    }

    .student-grid {

        grid-template-columns: 1fr;

    }

    .page-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;

    }

}

</style>


<div
    style="
        display:flex;
        flex-direction:column;
        min-height:100vh;
    "
>


    <?php require_once "../../includes/navbar.php"; ?>


    <div
        style="
            display:flex;
            flex:1;
        "
    >


        <?php require_once "../../includes/sidebar.php"; ?>


        <main
            style="
                flex:1;
                min-width:0;
            "
        >


            <div class="edit-page">


                <!-- =================================================
                     HEADER
                ================================================== -->

                <div class="page-header">

                    <div>

                        <h1>
                            Edit Marks
                        </h1>

                        <p>
                            Edit Internal marks for
                            the selected student.
                        </p>

                    </div>


                    <?php if ($student): ?>

                        <a
                            href="view.php?student_id=<?php
                                echo $studentId;
                            ?>"
                            class="back-btn"
                        >
                            ← Back to View
                        </a>

                    <?php endif; ?>

                </div>



                <!-- =================================================
                     SUCCESS
                ================================================== -->

                <?php if (
                    $successMessage !== ''
                ): ?>

                    <div class="success">

                        <?php
                        echo h(
                            $successMessage
                        );
                        ?>

                    </div>

                <?php endif; ?>



                <!-- =================================================
                     ERROR
                ================================================== -->

                <?php if (
                    $errorMessage !== ''
                ): ?>

                    <div class="error">

                        <?php
                        echo h(
                            $errorMessage
                        );
                        ?>

                    </div>

                <?php endif; ?>



                <?php if ($student): ?>


                    <!-- =================================================
                         STUDENT INFORMATION
                    ================================================== -->

                    <div class="card">

                        <div class="card-title">

                            Student Information

                        </div>


                        <div class="student-grid">


                            <div class="student-item">

                                <label>
                                    Student Name
                                </label>

                                <strong>
                                    <?php
                                    echo h(
                                        $student[
                                            'student_name'
                                        ]
                                    );
                                    ?>
                                </strong>

                            </div>


                            <div class="student-item">

                                <label>
                                    Roll No.
                                </label>

                                <strong>
                                    <?php
                                    echo h(
                                        $student[
                                            'roll_no'
                                        ]
                                    );
                                    ?>
                                </strong>

                            </div>


                            <div class="student-item">

                                <label>
                                    Designation
                                </label>

                                <strong>
                                    <?php
                                    echo h(
                                        $student[
                                            'designation'
                                        ]
                                    );
                                    ?>
                                </strong>

                            </div>


                            <div class="student-item">

                                <label>
                                    Qualification
                                </label>

                                <strong>
                                    <?php
                                    echo h(
                                        $student[
                                            'qualification'
                                        ]
                                    );
                                    ?>
                                </strong>

                            </div>


                            <div class="student-item">

                                <label>
                                    RMC
                                </label>

                                <strong>
                                    <?php
                                    echo h(
                                        $student[
                                            'rmc'
                                        ]
                                    );
                                    ?>
                                </strong>

                            </div>


                            <div class="student-item">

                                <label>
                                    Group
                                </label>

                                <strong>
                                    <?php
                                    echo h($group);
                                    ?>
                                </strong>

                            </div>


                            <div class="student-item">

                                <label>
                                    Batch
                                </label>

                                <strong>
                                    <?php
                                    echo h(
                                        $student[
                                            'batch_name'
                                        ]
                                    );
                                    ?>
                                </strong>

                            </div>


                            <div class="student-item">

                                <label>
                                    Station
                                </label>

                                <strong>
                                    <?php
                                    echo h(
                                        $student[
                                            'station'
                                        ]
                                    );
                                    ?>
                                </strong>

                            </div>


                            <div class="student-item">

                                <label>
                                    Commenced Date
                                </label>

                                <strong>
                                    <?php
                                    echo h(
                                        $student[
                                            'commenced_date'
                                        ]
                                    );
                                    ?>
                                </strong>

                            </div>


                            <div class="student-item">

                                <label>
                                    Completed Date
                                </label>

                                <strong>
                                    <?php
                                    echo h(
                                        $student[
                                            'completed_date'
                                        ]
                                    );
                                    ?>
                                </strong>

                            </div>


                        </div>

                    </div>



                    <!-- =================================================
                         RULE
                    ================================================== -->

                    <div class="rule-box">
                        <strong>Admin Grace Marks & Editing Rules</strong>
                        <br>
                        • <strong>Internal Marks Only:</strong> Editing is allowed strictly for <strong>Internal marks</strong>. Mid-Term and Final Exam marks cannot be edited.
                        <br>
                        • <strong>Maximum 3 Grace Marks:</strong> A student can receive up to <strong>3 grace marks</strong> in total across all edits.
                        <br>
                        • <strong>Single Subject Grace:</strong> Grace marks (mark increases) can only be awarded in <strong>1 subject</strong> per student.
                        <br>
                        • <strong>Assessment Upper Limit:</strong> New marks cannot exceed the assessment maximum limit (defined per subject/paper).
                        <br>
                        <?php if ($totalGraceSum > 0): ?>
                            <div style="margin-top:8px; padding-top:8px; border-top:1px dashed #cbd5e1; color:#1f4e79;">
                                <strong>Current Status for <?php echo h($student['student_name']); ?>:</strong>
                                Awarded <strong><?php echo number_format($totalGraceSum, 2); ?> / 3.00</strong> grace marks
                                <?php if ($gracedSubjectName): ?>
                                    in <strong><?php echo h($gracedSubjectName); ?></strong>
                                <?php endif; ?>
                                (Remaining available: <strong><?php echo number_format(max(0, 3.00 - $totalGraceSum), 2); ?></strong> marks).
                            </div>
                        <?php endif; ?>
                    </div>



                    <!-- =================================================
                         INTERNAL MARKS
                    ================================================== -->

                    <div class="card">


                        <div class="card-title">

                            Internal Marks

                        </div>


                        <div class="table-container">


                            <?php if (
                                empty($marks)
                            ): ?>


                                <div
                                    style="
                                        padding:40px;
                                        text-align:center;
                                        color:#777;
                                    "
                                >

                                    No Internal marks
                                    found for this
                                    student's tabulation.

                                </div>


                            <?php else: ?>


                                <table
                                    class="marks-table"
                                >


                                    <thead>

                                        <tr>

                                            <th>
                                                Paper
                                            </th>

                                            <th>
                                                Subject
                                            </th>

                                            <th>
                                                Professor
                                            </th>

                                            <th>
                                                Current
                                            </th>

                                            <th>
                                                Maximum
                                            </th>

                                            <th>
                                                New Marks
                                            </th>

                                            <th>
                                                Status
                                            </th>

                                            <th>
                                                Action
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>


                                    <?php foreach (
                                        $marks
                                        as $mark
                                    ): ?>


                                        <?php

                                        $absent =
                                            (int)$mark[
                                                'is_absent'
                                            ] === 1;


                                        $current =
                                            $mark[
                                                'marks_obtained'
                                            ];


                                        $maximum = (float)$mark['max_marks'];

                                        ?>


                                        <tr
                                            class="<?php
                                                if ($absent) {
                                                    echo 'locked-row';
                                                }
                                            ?>"
                                        >


                                            <!-- PAPER -->

                                            <td>

                                                <strong>

                                                    <?php
                                                    echo h(
                                                        $mark[
                                                            'paper_name'
                                                        ]
                                                    );
                                                    ?>

                                                </strong>

                                                <br>

                                                <small>

                                                    <?php
                                                    echo h(
                                                        $mark[
                                                            'paper_code'
                                                        ]
                                                    );
                                                    ?>

                                                </small>

                                            </td>



                                            <!-- SUBJECT -->

                                            <td>

                                                <strong>

                                                    <?php
                                                    echo h(
                                                        $mark[
                                                            'subject_name'
                                                        ]
                                                    );
                                                    ?>

                                                </strong>

                                                <br>

                                                <small>

                                                    <?php
                                                    echo h(
                                                        $mark[
                                                            'subject_code'
                                                        ]
                                                    );
                                                    ?>

                                                </small>

                                            </td>



                                            <!-- PROFESSOR -->

                                            <td>

                                                <?php
                                                echo h(
                                                    $mark[
                                                        'faculty_name'
                                                    ]
                                                );
                                                ?>

                                            </td>



                                            <!-- CURRENT -->

                                            <td>

                                                <strong>

                                                    <?php

                                                    echo $current !== null
                                                        ? h(
                                                            number_format(
                                                                (float)$current,
                                                                2
                                                            )
                                                        )
                                                        : '—';

                                                    ?>

                                                </strong>

                                            </td>



                                            <!-- MAX -->

                                            <td>

                                                <?php
                                                echo h(
                                                    number_format(
                                                        $maximum,
                                                        2
                                                    )
                                                );
                                                ?>

                                            </td>



                                            <!-- NEW MARK -->

                                            <td>


                                                <?php if ($absent): ?>

                                                    —

                                                <?php else: ?>


                                                    <form
                                                        method="POST"
                                                        style="
                                                            margin:0;
                                                        "
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="student_id"
                                                            value="<?php
                                                                echo $studentId;
                                                            ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="subject_id"
                                                            value="<?php
                                                                echo (int)$mark[
                                                                    'subject_id'
                                                                ];
                                                            ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="entry_id"
                                                            value="<?php
                                                                echo (int)$mark[
                                                                    'entry_id'
                                                                ];
                                                            ?>"
                                                        >


                                                        <input
                                                            type="number"
                                                            name="new_marks"
                                                            class="mark-input"
                                                            min="0"
                                                            max="<?php
                                                                echo h(
                                                                    $maximum
                                                                );
                                                            ?>"
                                                            step="0.01"
                                                            value="<?php
                                                                echo h(
                                                                    $current
                                                                );
                                                            ?>"
                                                            required
                                                        >

                                                <?php endif; ?>


                                            </td>



                                            <!-- STATUS -->

                                            <td>

                                                <?php if ($absent): ?>

                                                    <span class="absent-badge">Absent</span>

                                                <?php elseif ($gracedSubjectId !== null && (int)$mark['subject_id'] !== $gracedSubjectId): ?>

                                                    <span class="editable-badge" style="background:#e2e8f0; color:#475569;" title="Correction allowed; Grace marks awarded in another subject">Editable</span>

                                                <?php else: ?>

                                                    <span class="editable-badge" style="color:#278a4b; font-weight:bold;">Editable</span>

                                                <?php endif; ?>

                                            </td>


                                            <!-- ACTION -->

                                            <td>

                                                <?php if ($absent): ?>

                                                    <span class="absent-badge">Not Editable</span>

                                                <?php else: ?>

                                                    <input
                                                        type="text"
                                                        name="reason"
                                                        class="reason-input"
                                                        placeholder="Reason"
                                                    >

                                                    <br>

                                                    <button
                                                        type="submit"
                                                        name="save_internal"
                                                        value="1"
                                                        class="save-btn"
                                                        onclick="return confirm('Save this mark edit for this student?');"
                                                    >
                                                        Save
                                                    </button>

                                                    </form>

                                                <?php endif; ?>

                                            </td>


                                        </tr>


                                    <?php endforeach; ?>


                                    </tbody>


                                </table>


                            <?php endif; ?>


                        </div>

                    </div>



                    <!-- =================================================
                         CUSTOM REMARK
                    ================================================== -->

                    <div class="card">


                        <div class="card-title">

                            Custom Remark

                        </div>


                        <div class="remark-area">


                            <p>

                                Admin can add or update a
                                custom remark for this
                                particular student.

                            </p>


                            <form
                                method="POST"
                            >

                                <input
                                    type="hidden"
                                    name="student_id"
                                    value="<?php
                                        echo $studentId;
                                    ?>"
                                >


                                <textarea
                                    name="custom_remark"
                                    maxlength="500"
                                    placeholder="Enter custom remark..."
                                ><?php
                                    echo h(
                                        $student[
                                            'custom_remarks'
                                        ]
                                    );
                                ?></textarea>


                                <button
                                    type="submit"
                                    name="save_remark"
                                    value="1"
                                    class="remark-save"
                                >

                                    Save Custom Remark

                                </button>


                            </form>


                        </div>


                    </div>


                <?php endif; ?>


            </div>


        </main>


    </div>


    <?php require_once "../../includes/footer.php"; ?>


</div>