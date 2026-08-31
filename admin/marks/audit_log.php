<?php

require_once __DIR__ . "/../../includes/auth.php";
require_permission('marks.view');

require_once __DIR__ . "/../../config/database.php";

$pageTitle = "Marks Audit Log";

$auditLogs = [];
$errorMessage = "";

try {

    $stmt = $pdo->query("
        SELECT
            mal.audit_id,
            mal.student_id,
            mal.subject_id,
            mal.assessment_type_id,
            mal.old_marks,
            mal.new_marks,
            mal.modified_by,
            mal.modified_at,

            s.roll_no,
            s.student_name,

            sub.subject_code,
            sub.subject_name,

            at.assessment_type_name

        FROM marks_audit_log mal

        INNER JOIN students s
            ON mal.student_id = s.student_id

        INNER JOIN subjects sub
            ON mal.subject_id = sub.subject_id

        INNER JOIN assessment_types at
            ON mal.assessment_type_id = at.assessment_type_id

        ORDER BY mal.modified_at DESC
    ");

    $auditLogs = $stmt->fetchAll();

} catch (PDOException $e) {

    $errorMessage =
        "Unable to load audit log.";

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
            background:#f4f6f8;
        ">


            <div style="margin-bottom:25px;">

                <h1 style="
                    color:#1f4e79;
                    font-size:28px;
                    margin-bottom:8px;
                ">
                    Marks Audit Log
                </h1>

                <p style="color:#666;">
                    History of changes made to marks.
                </p>

            </div>


            <?php if ($errorMessage !== ""): ?>

                <div style="
                    background:#fdecea;
                    color:#a12622;
                    padding:15px;
                    border-radius:6px;
                    margin-bottom:20px;
                ">

                    <?php
                    echo htmlspecialchars($errorMessage);
                    ?>

                </div>

            <?php endif; ?>


            <div style="
                background:#ffffff;
                padding:20px;
                border-radius:8px;
                box-shadow:0 2px 8px rgba(0,0,0,0.08);
                overflow-x:auto;
            ">


                <table style="
                    width:100%;
                    border-collapse:collapse;
                    min-width:1100px;
                ">


                    <thead>

                        <tr>

                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                            ">
                                Student
                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                            ">
                                Subject
                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                            ">
                                Assessment
                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                            ">
                                Old Marks
                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                            ">
                                New Marks
                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                            ">
                                Modified By
                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                            ">
                                Modified At
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php if (count($auditLogs) > 0): ?>


                        <?php foreach ($auditLogs as $log): ?>

                            <tr>


                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eee;
                                ">

                                    <strong>
                                        <?php
                                        echo htmlspecialchars(
                                            $log["roll_no"]
                                        );
                                        ?>
                                    </strong>

                                    <br>

                                    <small>
                                        <?php
                                        echo htmlspecialchars(
                                            $log["student_name"]
                                        );
                                        ?>
                                    </small>

                                </td>


                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eee;
                                ">

                                    <?php
                                    echo htmlspecialchars(
                                        $log["subject_code"]
                                    );
                                    ?>

                                    <br>

                                    <small>
                                        <?php
                                        echo htmlspecialchars(
                                            $log["subject_name"]
                                        );
                                        ?>
                                    </small>

                                </td>


                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eee;
                                ">

                                    <?php
                                    echo htmlspecialchars(
                                        $log["assessment_type_name"]
                                    );
                                    ?>

                                </td>


                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eee;
                                ">

                                    <?php
                                    echo htmlspecialchars(
                                        $log["old_marks"] ?? "NULL"
                                    );
                                    ?>

                                </td>


                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eee;
                                ">

                                    <?php
                                    echo htmlspecialchars(
                                        $log["new_marks"] ?? "NULL"
                                    );
                                    ?>

                                </td>


                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eee;
                                ">

                                    <?php
                                    echo htmlspecialchars(
                                        $log["modified_by"]
                                    );
                                    ?>

                                </td>


                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eee;
                                ">

                                    <?php
                                    echo htmlspecialchars(
                                        $log["modified_at"]
                                    );
                                    ?>

                                </td>


                            </tr>

                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>

                            <td
                                colspan="7"
                                style="
                                    padding:30px;
                                    text-align:center;
                                    color:#666;
                                "
                            >
                                No mark changes have been recorded yet.
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