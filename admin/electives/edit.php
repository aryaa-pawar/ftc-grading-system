<?php

require_once "../../config/database.php";

$pageTitle = "Edit Elective Group";

$groupCode = $_GET["id"]
    ?? $_POST["group_code"]
    ?? "";

if ($groupCode === "") {
    die("Invalid group code.");
}

$errorMessage = "";


$stmt = $pdo->prepare("
    SELECT
        group_code,
        group_description
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
        subject_id,
        subject_code,
        subject_name
    FROM subjects
    WHERE is_elective = 1
    ORDER BY subject_name
");

$stmt->execute();

$electiveSubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);


$stmt = $pdo->prepare("
    SELECT subject_id
    FROM elective_subject_mapping
    WHERE group_code = :group_code
");

$stmt->execute([
    ":group_code" => $groupCode
]);

$currentSubjects = array_map(
    "intval",
    $stmt->fetchAll(PDO::FETCH_COLUMN)
);


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $groupDescription = trim(
        $_POST["group_description"] ?? ""
    );

    $selectedSubjects =
        $_POST["subjects"] ?? [];


    if ($groupDescription === "") {

        $errorMessage =
            "Group description is required.";

    } else {

        try {

            $pdo->beginTransaction();


            $stmt = $pdo->prepare("
                UPDATE elective_groups
                SET group_description = :group_description
                WHERE group_code = :group_code
            ");

            $stmt->execute([
                ":group_description" => $groupDescription,
                ":group_code" => $groupCode
            ]);


            /*
            |--------------------------------------------------------------------------
            | Replace subject mappings
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                DELETE FROM elective_subject_mapping
                WHERE group_code = :group_code
            ");

            $stmt->execute([
                ":group_code" => $groupCode
            ]);


            $stmt = $pdo->prepare("
                INSERT INTO elective_subject_mapping
                (
                    group_code,
                    subject_id
                )
                VALUES
                (
                    :group_code,
                    :subject_id
                )
            ");


            foreach ($selectedSubjects as $subjectId) {

                $stmt->execute([
                    ":group_code" => $groupCode,
                    ":subject_id" => (int)$subjectId
                ]);

            }


            $pdo->commit();


            header(
                "Location: view.php?id=" .
                urlencode($groupCode)
            );

            exit;


        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errorMessage =
                $e->getMessage();

        }

    }

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

            <h1 style="color:#1f4e79;">
                Edit Elective Group
            </h1>


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
                padding:25px;
                border-radius:8px;
                max-width:750px;
            ">

                <form method="POST">

                    <input
                        type="hidden"
                        name="group_code"
                        value="<?php echo htmlspecialchars($groupCode); ?>"
                    >


                    <p>
                        <strong>Group Code:</strong>

                        <?php
                        echo htmlspecialchars($groupCode);
                        ?>
                    </p>


                    <label style="font-weight:bold;">
                        Group Description
                    </label>

                    <input
                        type="text"
                        name="group_description"
                        value="<?php
                            echo htmlspecialchars(
                                $group["group_description"]
                            );
                        ?>"
                        required
                        style="
                            width:100%;
                            padding:11px;
                            margin:8px 0 20px;
                        "
                    >


                    <label style="
                        font-weight:bold;
                        display:block;
                        margin-bottom:10px;
                    ">
                        Elective Subjects
                    </label>


                    <?php foreach ($electiveSubjects as $subject): ?>

                        <label style="
                            display:block;
                            margin-bottom:10px;
                        ">

                            <input
                                type="checkbox"
                                name="subjects[]"
                                value="<?php
                                    echo $subject["subject_id"];
                                ?>"
                                <?php
                                echo in_array(
                                    (int)$subject["subject_id"],
                                    $currentSubjects,
                                    true
                                )
                                ? "checked"
                                : "";
                                ?>
                            >

                            <?php
                            echo htmlspecialchars(
                                $subject["subject_code"]
                            );
                            ?>

                            -

                            <?php
                            echo htmlspecialchars(
                                $subject["subject_name"]
                            );
                            ?>

                        </label>

                    <?php endforeach; ?>


                    <div style="margin-top:25px;">

                        <button
                            type="submit"
                            style="
                                background:#278a4b;
                                color:white;
                                border:0;
                                padding:12px 22px;
                                border-radius:6px;
                            "
                        >
                            Update Elective Group
                        </button>


                        <a
                            href="view.php?id=<?php echo urlencode($groupCode); ?>"
                            style="
                                background:#777;
                                color:white;
                                padding:12px 22px;
                                border-radius:6px;
                                text-decoration:none;
                                margin-left:8px;
                            "
                        >
                            Cancel
                        </a>

                    </div>

                </form>

            </div>

        </main>

    </div>

    <?php require_once "../../includes/footer.php"; ?>

</div>