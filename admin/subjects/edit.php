<?php

require_once "../../config/database.php";

$pageTitle = "Edit Subject";

$subjectId = isset($_GET["id"])
    ? (int)$_GET["id"]
    : (int)($_POST["subject_id"] ?? 0);

if ($subjectId <= 0) {
    die("Invalid subject ID.");
}

$errorMessage = "";

try {

    $stmt = $pdo->prepare("
        SELECT
            subject_id,
            subject_code,
            subject_name,
            is_elective
        FROM subjects
        WHERE subject_id = :subject_id
    ");

    $stmt->execute([
        ":subject_id" => $subjectId
    ]);

    $subject = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$subject) {
        die("Subject not found.");
    }

} catch (PDOException $e) {

    die($e->getMessage());

}


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $subjectCode = trim($_POST["subject_code"] ?? "");
    $subjectName = trim($_POST["subject_name"] ?? "");
    $isElective = isset($_POST["is_elective"]) ? 1 : 0;


    if ($subjectCode === "") {

        $errorMessage = "Subject code is required.";

    } elseif ($subjectName === "") {

        $errorMessage = "Subject name is required.";

    } else {

        try {

            $stmt = $pdo->prepare("
                UPDATE subjects
                SET
                    subject_code = :subject_code,
                    subject_name = :subject_name,
                    is_elective = :is_elective
                WHERE subject_id = :subject_id
            ");

            $stmt->execute([
                ":subject_code" => $subjectCode,
                ":subject_name" => $subjectName,
                ":is_elective" => $isElective,
                ":subject_id" => $subjectId
            ]);

            header(
                "Location: view.php?id=" . $subjectId
            );

            exit;

        } catch (PDOException $e) {

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
                Edit Subject
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
                max-width:700px;
            ">

                <form method="POST">

                    <input
                        type="hidden"
                        name="subject_id"
                        value="<?php echo $subjectId; ?>"
                    >


                    <label style="font-weight:bold;">
                        Subject Code
                    </label>

                    <input
                        type="text"
                        name="subject_code"
                        value="<?php echo htmlspecialchars($subject["subject_code"]); ?>"
                        required
                        style="
                            width:100%;
                            padding:11px;
                            margin:8px 0 20px;
                        "
                    >


                    <label style="font-weight:bold;">
                        Subject Name
                    </label>

                    <input
                        type="text"
                        name="subject_name"
                        value="<?php echo htmlspecialchars($subject["subject_name"]); ?>"
                        required
                        style="
                            width:100%;
                            padding:11px;
                            margin:8px 0 20px;
                        "
                    >


                    <label style="
                        display:flex;
                        align-items:center;
                        gap:8px;
                        margin-bottom:25px;
                    ">

                        <input
                            type="checkbox"
                            name="is_elective"
                            value="1"
                            <?php
                            echo (int)$subject["is_elective"] === 1
                                ? "checked"
                                : "";
                            ?>
                        >

                        This is an elective subject

                    </label>


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
                        Update Subject
                    </button>


                    <button
                        type="button"
                        onclick="window.location.href='index.php';"
                        style="
                            background:#777;
                            color:white;
                            padding:12px 22px;
                            border:0;
                            border-radius:6px;
                            cursor:pointer;
                            margin-left:8px;
                        "
                    >
                        Cancel
                    </button>

                </form>

            </div>

        </main>

    </div>

    <?php require_once "../../includes/footer.php"; ?>

</div>