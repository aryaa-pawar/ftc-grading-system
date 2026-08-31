<?php

require_once "../../config/database.php";

$pageTitle = "View Elective Group";

$groupCode = $_GET["id"] ?? "";

if ($groupCode === "") {
    die("Invalid group code.");
}


$stmt = $pdo->prepare("
    SELECT
        group_code,
        group_description,
        created_at
    FROM elective_groups
    WHERE group_code = :group_code
");

$stmt->execute([
    ":group_code" => $groupCode
]);

$group = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$group) {
    die("Elective group not found.");
}


$stmt = $pdo->prepare("
    SELECT
        s.subject_id,
        s.subject_code,
        s.subject_name
    FROM elective_subject_mapping esm
    INNER JOIN subjects s
        ON esm.subject_id = s.subject_id
    WHERE esm.group_code = :group_code
    ORDER BY s.subject_name
");

$stmt->execute([
    ":group_code" => $groupCode
]);

$subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);


require_once "../../includes/header.php";

?>

<div style="display:flex;flex-direction:column;min-height:100vh;">

    <?php require_once "../../includes/navbar.php"; ?>

    <div style="display:flex;flex:1;">

        <?php require_once "../../includes/sidebar.php"; ?>

        <main style="
            flex:1;
            padding:30px;
            background:#f4f6f8;
        ">

            <h1 style="color:#1f4e79;">
                Elective Group
            </h1>

            <div style="
                background:white;
                padding:25px;
                border-radius:8px;
                max-width:800px;
                margin-top:25px;
            ">

                <p>
                    <strong>Group Code:</strong>
                    <?php echo htmlspecialchars($group["group_code"]); ?>
                </p>

                <p>
                    <strong>Description:</strong>
                    <?php echo htmlspecialchars($group["group_description"]); ?>
                </p>


                <h3 style="color:#1f4e79;margin-top:30px;">
                    Assigned Elective Subjects
                </h3>


                <?php if (count($subjects) > 0): ?>

                    <table style="
                        width:100%;
                        border-collapse:collapse;
                    ">

                        <tr>

                            <th style="
                                padding:10px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">
                                Code
                            </th>

                            <th style="
                                padding:10px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">
                                Subject
                            </th>

                        </tr>

                        <?php foreach ($subjects as $subject): ?>

                            <tr>

                                <td style="
                                    padding:10px;
                                    border-bottom:1px solid #eee;
                                ">
                                    <?php
                                    echo htmlspecialchars(
                                        $subject["subject_code"]
                                    );
                                    ?>
                                </td>

                                <td style="
                                    padding:10px;
                                    border-bottom:1px solid #eee;
                                ">
                                    <?php
                                    echo htmlspecialchars(
                                        $subject["subject_name"]
                                    );
                                    ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </table>

                <?php else: ?>

                    <p style="color:#666;">
                        No subjects assigned to this group.
                    </p>

                <?php endif; ?>


                <div style="margin-top:25px;">

                    <a
                        href="edit.php?id=<?php echo urlencode($groupCode); ?>"
                        style="
                            background:#278a4b;
                            color:white;
                            padding:11px 20px;
                            border-radius:6px;
                            text-decoration:none;
                        "
                    >
                        Edit
                    </a>

                    <a
                        href="index.php"
                        style="
                            background:#777;
                            color:white;
                            padding:11px 20px;
                            border-radius:6px;
                            text-decoration:none;
                            margin-left:8px;
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