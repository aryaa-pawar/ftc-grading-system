<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../../config/database.php";

$errorMessage = "";
$successMessage = "";


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

            /*
            |--------------------------------------------------------------------------
            | Check duplicate subject code
            |--------------------------------------------------------------------------
            */

            $check = $pdo->prepare("
                SELECT subject_id
                FROM subjects
                WHERE subject_code = ?
                LIMIT 1
            ");

            $check->execute([
                $subjectCode
            ]);


            if ($check->fetch()) {

                $errorMessage =
                    "This subject code already exists.";

            } else {

                /*
                |--------------------------------------------------------------------------
                | Insert Subject
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare("
                    INSERT INTO subjects
                    (
                        subject_code,
                        subject_name,
                        is_elective
                    )
                    VALUES
                    (
                        ?,
                        ?,
                        ?
                    )
                ");

                $stmt->execute([
                    $subjectCode,
                    $subjectName,
                    $isElective
                ]);


                /*
                |--------------------------------------------------------------------------
                | Return to Subject Registration
                |--------------------------------------------------------------------------
                */

                header(
                    "Location: index.php?success=1"
                );

                exit;

            }


        } catch (PDOException $e) {

            $errorMessage =
                $e->getMessage();

        }

    }

}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Add Subject
    </title>


    <style>

        * {
            box-sizing: border-box;
        }

        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f6f8;

            color: #333;

        }

        .header {

            height: 80px;

            background: #1f4e79;

            color: white;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding: 0 30px;

        }

        .header-title {

            font-size: 23px;

            font-weight: bold;

        }

        .header-right {

            font-size: 16px;

        }

        .layout {

            display: flex;

            min-height:
                calc(100vh - 80px);

        }

        .sidebar {

            width: 260px;

            background: white;

            border-right:
                1px solid #ddd;

            flex-shrink: 0;

        }

        .sidebar-title {

            padding: 25px;

            font-size: 22px;

            font-weight: bold;

            color: #1f4e79;

            border-bottom:
                1px solid #eee;

        }

        .sidebar a {

            display: block;

            padding: 16px 25px;

            color: #333;

            text-decoration: none;

            border-bottom:
                1px solid #eee;

        }

        .sidebar a:hover {

            background: #f1f5f8;

            color: #1f4e79;

        }

        .content {

            flex: 1;

            padding: 40px;

        }

        .page-title {

            margin: 0;

            color: #1f4e79;

            font-size: 32px;

        }

        .page-subtitle {

            color: #666;

            margin-top: 8px;

            margin-bottom: 25px;

        }

        .form-card {

            max-width: 700px;

            background: white;

            padding: 30px;

            border-radius: 10px;

            box-shadow:
                0 3px 10px
                rgba(0,0,0,0.08);

        }

        .error {

            background: #fdecea;

            color: #a12622;

            padding: 15px;

            border-radius: 7px;

            margin-bottom: 20px;

        }

        .form-group {

            margin-bottom: 20px;

        }

        label {

            display: block;

            margin-bottom: 8px;

            font-weight: bold;

            color: #444;

        }

        input[type="text"] {

            width: 100%;

            padding: 12px;

            border: 1px solid #ccc;

            border-radius: 6px;

            font-size: 15px;

        }

        input[type="text"]:focus {

            outline: none;

            border-color: #1f4e79;

        }

        .checkbox-row {

            display: flex;

            align-items: center;

            gap: 10px;

            margin-top: 10px;

        }

        .checkbox-row label {

            margin: 0;

            font-weight: normal;

        }

        .buttons {

            margin-top: 25px;

            display: flex;

            gap: 10px;

        }

        .button {

            padding: 12px 20px;

            border: none;

            border-radius: 6px;

            text-decoration: none;

            cursor: pointer;

            font-weight: bold;

            font-size: 14px;

        }

        .save {

            background: #1f4e79;

            color: white;

        }

        .cancel {

            background: #777;

            color: white;

        }

        .button:hover {

            opacity: 0.9;

        }

    </style>

</head>


<body>


<?php require_once "../../includes/navbar.php"; ?>


<div class="layout">


    <?php require_once "../../includes/sidebar.php"; ?>


    <main class="content">


        <h1 class="page-title">

            Subject Registration

        </h1>


        <p class="page-subtitle">

            Register a new subject.

        </p>


        <?php if ($errorMessage !== ""): ?>

            <div class="error">

                <?php

                echo htmlspecialchars(
                    $errorMessage
                );

                ?>

            </div>

        <?php endif; ?>


        <div class="form-card">


            <form
                method="POST"
                action=""
            >


                <div class="form-group">

                    <label for="subject_code">

                        Subject Code *

                    </label>


                    <input
                        type="text"
                        id="subject_code"
                        name="subject_code"
                        placeholder="Example: NWP"
                        required
                        maxlength="15"
                        value="<?php
                            echo htmlspecialchars(
                                $_POST["subject_code"] ?? ""
                            );
                        ?>"
                    >

                </div>


                <div class="form-group">

                    <label for="subject_name">

                        Subject Name *

                    </label>


                    <input
                        type="text"
                        id="subject_name"
                        name="subject_name"
                        placeholder="Example: Numerical Weather Prediction"
                        required
                        maxlength="150"
                        value="<?php
                            echo htmlspecialchars(
                                $_POST["subject_name"] ?? ""
                            );
                        ?>"
                    >

                </div>


                <div class="form-group">


                    <div class="checkbox-row">

                        <input
                            type="checkbox"
                            id="is_elective"
                            name="is_elective"
                            value="1"
                            <?php

                            if (
                                isset($_POST["is_elective"])
                            ) {

                                echo "checked";

                            }

                            ?>
                        >


                        <label for="is_elective">

                            This is an elective subject

                        </label>

                    </div>


                </div>


                <div class="buttons">


                    <button
                        type="submit"
                        class="button save"
                    >

                        Save Subject

                    </button>


                    <a
                        href="index.php"
                        class="button cancel"
                    >

                        Cancel

                    </a>


                </div>


            </form>


        </div>


    </main>


</div>


</body>

</html>
