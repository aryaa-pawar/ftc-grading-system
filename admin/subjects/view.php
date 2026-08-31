<?php

require_once "../../config/database.php";

$pageTitle = "View Subject";

$subjectId = isset($_GET["id"])
    ? (int)$_GET["id"]
    : (int)($_POST["subject_id"] ?? 0);

if ($subjectId <= 0) {
    die("Invalid subject ID.");
}

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
                Subject Details
            </h1>


            <div style="
                background:white;
                padding:25px;
                border-radius:8px;
                max-width:700px;
            ">

                <p><strong>Subject ID:</strong> <?php echo htmlspecialchars($subject["subject_id"]); ?></p>
                <p><strong>Subject Code:</strong> <?php echo htmlspecialchars($subject["subject_code"]); ?></p>
                <p><strong>Subject Name:</strong> <?php echo htmlspecialchars($subject["subject_name"]); ?></p>
                <p><strong>Elective:</strong> <?php echo (int)$subject["is_elective"] === 1 ? "Yes" : "No"; ?></p>

                <div style="margin-top:25px;">
                    <a
                        href="edit.php?id=<?php echo $subjectId; ?>"
                        style="background:#278a4b;color:white;padding:11px 20px;border-radius:6px;text-decoration:none;"
                    >
                        Edit
                    </a>
                    <a
                        href="index.php"
                        style="background:#777;color:white;padding:11px 20px;border-radius:6px;text-decoration:none;margin-left:8px;"
                    >
                        Back
                    </a>
                </div>

            </div>

        </main>

    </div>

    <?php require_once "../../includes/footer.php"; ?>

</div>