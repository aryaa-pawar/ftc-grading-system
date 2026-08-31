<?php

require_once "../../config/database.php";

$pageTitle = "Delete Subject";

$subjectId = isset($_GET["id"])
    ? (int)$_GET["id"]
    : 0;

if ($subjectId <= 0) {
    die("Invalid subject ID.");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM paper_subject_mapping WHERE subject_id = :subject_id");
        $stmt->execute([":subject_id" => $subjectId]);

        $stmt = $pdo->prepare("DELETE FROM subject_faculty_mapping WHERE subject_id = :subject_id");
        $stmt->execute([":subject_id" => $subjectId]);

        $stmt = $pdo->prepare("DELETE FROM elective_subject_mapping WHERE subject_id = :subject_id");
        $stmt->execute([":subject_id" => $subjectId]);

        $stmt = $pdo->prepare("DELETE FROM subjects WHERE subject_id = :subject_id");
        $stmt->execute([":subject_id" => $subjectId]);

        $pdo->commit();
        header("Location: index.php");
        exit;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die("Unable to delete subject. It may still be used in a paper or faculty assignment.");
    }
}


$stmt = $pdo->prepare("
    SELECT
        subject_id,
        subject_code,
        subject_name,
        is_elective,
        created_at
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
                Delete Subject
            </h1>

            <div style="
                background:white;
                padding:25px;
                border-radius:8px;
                max-width:750px;
                margin-top:25px;
            ">

                <p>
                    <strong>Subject ID:</strong>
                    <?php echo htmlspecialchars($subject["subject_id"]); ?>
                </p>

                <p>
                    <strong>Subject Code:</strong>
                    <?php echo htmlspecialchars($subject["subject_code"]); ?>
                </p>

                <p>
                    <strong>Subject Name:</strong>
                    <?php echo htmlspecialchars($subject["subject_name"]); ?>
                </p>

                <p>
                    <strong>Elective:</strong>

                    <?php
                    echo (int)$subject["is_elective"] === 1
                        ? "Yes"
                        : "No";
                    ?>

                </p>

                <p>
                    <strong>Created At:</strong>
                    <?php echo htmlspecialchars($subject["created_at"]); ?>
                </p>


                <div style="margin-top:25px;">

                    <form method="POST" style="display:inline;">
                        <button
                            type="submit"
                            style="
                                background:#c0392b;
                                color:white;
                                padding:11px 20px;
                                border:none;
                                border-radius:6px;
                                cursor:pointer;
                            "
                            onclick="return confirm('Delete this subject permanently?');"
                        >
                            Confirm Delete
                        </button>
                    </form>

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
                        Cancel
                    </a>

                </div>

            </div>

        </main>

    </div>

    <?php require_once "../../includes/footer.php"; ?>

</div>