<?php

require_once "../../config/database.php";

$pageTitle = "Elective Management";

$groups = [];
$errorMessage = "";

try {

    $stmt = $pdo->query("
        SELECT
            eg.group_code,
            eg.group_description,
            eg.created_at,
            COUNT(esm.subject_id) AS subject_count
        FROM elective_groups eg
        LEFT JOIN elective_subject_mapping esm
            ON eg.group_code = esm.group_code
        GROUP BY
            eg.group_code,
            eg.group_description,
            eg.created_at
        ORDER BY eg.group_code
    ");

    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $errorMessage = $e->getMessage();

}

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

            <div style="
                display:flex;
                justify-content:space-between;
                align-items:center;
                margin-bottom:25px;
            ">

                <div>

                    <h1 style="
                        color:#1f4e79;
                        margin:0 0 8px 0;
                    ">
                        Elective Management
                    </h1>

                    <p style="color:#666;">
                        Manage elective groups and their subjects.
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
                    + Add Elective Group
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
                    <?php echo htmlspecialchars($errorMessage); ?>
                </div>

            <?php endif; ?>


            <div style="
                background:white;
                padding:20px;
                border-radius:8px;
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
                                Group Code
                            </th>

                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">
                                Description
                            </th>

                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">
                                Subjects
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

                    <?php foreach ($groups as $group): ?>

                        <tr>

                            <td style="padding:12px;border-bottom:1px solid #eee;">
                                <?php echo htmlspecialchars($group["group_code"]); ?>
                            </td>

                            <td style="padding:12px;border-bottom:1px solid #eee;">
                                <?php echo htmlspecialchars($group["group_description"]); ?>
                            </td>

                            <td style="padding:12px;border-bottom:1px solid #eee;">
                                <?php echo htmlspecialchars($group["subject_count"]); ?>
                            </td>

                            <td style="padding:12px;border-bottom:1px solid #eee;">

                                <a
                                    href="view.php?id=<?php echo urlencode($group["group_code"]); ?>"
                                    style="color:#1f4e79;margin-right:12px;"
                                >
                                    View
                                </a>

                                <a
                                    href="edit.php?id=<?php echo urlencode($group["group_code"]); ?>"
                                    style="color:#278a4b;margin-right:12px;"
                                >
                                    Edit
                                </a>

                                <a
                                    href="delete.php?id=<?php echo urlencode($group["group_code"]); ?>"
                                    style="color:#c0392b;"
                                >
                                    Delete
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </main>

    </div>

    <?php require_once "../../includes/footer.php"; ?>

</div>