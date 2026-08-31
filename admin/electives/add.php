<?php

require_once "../../config/database.php";

$pageTitle = "Add Elective Group";

$errorMessage = "";

$groupCode = "";
$groupDescription = "";
$selectedSubjects = [];

try {

    $stmt = $pdo->query("
        SELECT
            subject_id,
            subject_code,
            subject_name
        FROM subjects
        WHERE is_elective = 1
        ORDER BY subject_name
    ");

    $electiveSubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die($e->getMessage());

}


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $groupCode = trim($_POST["group_code"] ?? "");
    $groupDescription = trim(
        $_POST["group_description"] ?? ""
    );

    $selectedSubjects =
        $_POST["subjects"] ?? [];


    if ($groupCode === "") {

        $errorMessage = "Group code is required.";

    } elseif (strlen($groupCode) !== 1) {

        $errorMessage =
            "Group code must contain exactly one character.";

    } elseif ($groupDescription === "") {

        $errorMessage =
            "Group description is required.";

    } else {

        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | Create group
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO elective_groups
                (
                    group_code,
                    group_description
                )
                VALUES
                (
                    :group_code,
                    :group_description
                )
            ");

            $stmt->execute([
                ":group_code" => $groupCode,
                ":group_description" => $groupDescription
            ]);


            /*
            |--------------------------------------------------------------------------
            | Map selected elective subjects
            |--------------------------------------------------------------------------
            */

            $stmtMapping = $pdo->prepare("
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

                $stmtMapping->execute([
                    ":group_code" => $groupCode,
                    ":subject_id" => (int)$subjectId
                ]);

            }


            $pdo->commit();


            header("Location: index.php");

            exit;


        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errorMessage = $e->getMessage();

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
                Add Elective Group
            </h1>

            <p style="color:#666;margin-bottom:25px;">
                Create an elective group and assign elective subjects.
            </p>


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

                    <label style="font-weight:bold;">
                        Group Code
                    </label>

                    <input
                        type="text"
                        name="group_code"
                        maxlength="1"
                        value="<?php echo htmlspecialchars($groupCode); ?>"
                        required
                        style="
                            width:100%;
                            padding:11px;
                            margin:8px 0 20px;
                        "
                    >


                    <label style="font-weight:bold;">
                        Group Description
                    </label>

                    <input
                        type="text"
                        name="group_description"
                        value="<?php echo htmlspecialchars($groupDescription); ?>"
                        required
                        style="
                            width:100%;
                            padding:11px;
                            margin:8px 0 20px;
                        "
                    >


                    <label style="
                        display:block;
                        font-weight:bold;
                        margin-bottom:10px;
                    ">
                        Elective Subjects
                    </label>


                    <div style="
                        border:1px solid #ddd;
                        padding:15px;
                        border-radius:6px;
                        margin-bottom:25px;
                    ">

                    <?php if (count($electiveSubjects) > 0): ?>

                        <?php foreach ($electiveSubjects as $subject): ?>

                            <label style="
                                display:block;
                                margin-bottom:10px;
                            ">

                                <input
                                    type="checkbox"
                                    name="subjects[]"
                                    value="<?php echo $subject["subject_id"]; ?>"
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

                    <?php else: ?>

                        <p style="color:#666;">
                            No elective subjects are currently registered.
                        </p>

                    <?php endif; ?>

                    </div>


                    <button
                        type="submit"
                        style="
                            background:#1f4e79;
                            color:white;
                            border:0;
                            padding:12px 22px;
                            border-radius:6px;
                        "
                    >
                        Save Elective Group
                    </button>


                    <a
                        href="index.php"
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

                </form>

            </div>

        </main>

    </div>

    <?php require_once "../../includes/footer.php"; ?>

</div>