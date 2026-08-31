<?php

require_once "../../config/database.php";

$pageTitle = "Subject Management";

$subjects = [];
$errorMessage = "";

try {

    $stmt = $pdo->query("
        SELECT
            subject_id,
            subject_code,
            subject_name,
            is_elective,
            created_at
        FROM subjects
        ORDER BY subject_id ASC
    ");

    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $errorMessage = $e->getMessage();

}

require_once "../../includes/header.php";

?>

<div style="
    display:flex;
    flex-direction:column;
    min-height:100vh;
">

    <?php require_once "../../includes/navbar.php"; ?>

    <div style="display:flex; flex:1;">

        <?php require_once "../../includes/sidebar.php"; ?>

        <main style="
            flex:1;
            padding:30px;
            background:#f4f6f8;
        ">

            <div style="
                display:flex;
                justify-content:space-between;
                align-items:center;
                margin-bottom:25px;
            ">

                <div>

                    <h1 style="
                        margin:0 0 8px 0;
                        color:#1f4e79;
                    ">
                        Subject Management
                    </h1>

                    <p style="color:#666;">
                        Manage subjects registered in the system.
                    </p>

                </div>

                <a
                    href="add.php"
                    style="
                        background:#1f4e79;
                        color:white;
                        padding:12px 20px;
                        border-radius:6px;
                        text-decoration:none;
                        font-weight:bold;
                    "
                >
                    + Add Subject
                </a>

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
                background:white;
                padding:20px;
                border-radius:8px;
                box-shadow:0 2px 8px rgba(0,0,0,0.08);
                overflow-x:auto;
            ">

                <table style="
                    width:100%;
                    border-collapse:collapse;
                ">

                    <thead>

                        <tr>

                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">
                                S.No.
                            </th>

                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">
                                Subject Code
                            </th>

                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">
                                Subject Name
                            </th>

                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">
                                Elective
                            </th>

                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">
                                Created At
                            </th>

                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">
                                Actions
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (count($subjects) > 0): ?>

                        <?php foreach ($subjects as $index => $subject): ?>

                            <tr>

                                <td style="padding:12px;border-bottom:1px solid #eee;">
                                    <?php echo $index + 1; ?>
                                </td>

                                <td style="padding:12px;border-bottom:1px solid #eee;">
                                    <?php echo htmlspecialchars($subject["subject_code"]); ?>
                                </td>

                                <td style="padding:12px;border-bottom:1px solid #eee;">
                                    <?php echo htmlspecialchars($subject["subject_name"]); ?>
                                </td>

                                <td style="padding:12px;border-bottom:1px solid #eee;">

                                    <?php if ((int)$subject["is_elective"] === 1): ?>

                                        <span style="
                                            color:#278a4b;
                                            font-weight:bold;
                                        ">
                                            Yes
                                        </span>

                                    <?php else: ?>

                                        <span>
                                            No
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td style="padding:12px;border-bottom:1px solid #eee;">
                                    <?php echo htmlspecialchars($subject["created_at"]); ?>
                                </td>

                                <td style="padding:12px;border-bottom:1px solid #eee;">

                                    <a
                                        href="view.php?id=<?php echo $subject["subject_id"]; ?>"
                                        style="color:#1f4e79;margin-right:12px;"
                                    >
                                        View
                                    </a>

                                    <a
                                        href="edit.php?id=<?php echo $subject["subject_id"]; ?>"
                                        style="color:#278a4b;margin-right:12px;"
                                    >
                                        Edit
                                    </a>

                                    <a
                                        href="delete.php?id=<?php echo $subject["subject_id"]; ?>"
                                        style="color:#c0392b;"
                                    >
                                        Delete
                                    </a>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="6"
                                style="
                                    padding:30px;
                                    text-align:center;
                                    color:#666;
                                "
                            >
                                No subjects found.
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