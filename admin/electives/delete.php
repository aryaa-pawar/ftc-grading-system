<?php

require_once "../../config/database.php";

$groupCode = $_GET["id"] ?? "";

if ($groupCode === "") {

    header("Location: index.php");
    exit;

}


try {

    $stmt = $pdo->prepare("
        DELETE FROM elective_groups
        WHERE group_code = :group_code
    ");

    $stmt->execute([
        ":group_code" => $groupCode
    ]);


    header("Location: index.php");

    exit;


} catch (PDOException $e) {

    ?>

    <!DOCTYPE html>

    <html>

    <head>
        <title>Delete Elective Group</title>
    </head>

    <body style="
        font-family:Arial,sans-serif;
        background:#f4f6f8;
        padding:40px;
    ">

        <div style="
            max-width:650px;
            margin:auto;
            background:white;
            padding:30px;
            border-radius:8px;
        ">

            <h2 style="color:#c0392b;">
                Elective Group Cannot Be Deleted
            </h2>

            <p>
                <?php
                echo htmlspecialchars(
                    $e->getMessage()
                );
                ?>
            </p>

            <a
                href="index.php"
                style="
                    background:#1f4e79;
                    color:white;
                    padding:10px 18px;
                    text-decoration:none;
                    border-radius:5px;
                "
            >
                Back
            </a>

        </div>

    </body>

    </html>

    <?php

}