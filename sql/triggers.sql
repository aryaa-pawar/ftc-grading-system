<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>FTC Grading System</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f6f8;
            display: flex;
            flex-direction: column;
        }

        .header {
            background: #1f4e79;
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .header h1 {
            margin: 0;
            font-size: 32px;
        }

        .header p {
            margin: 8px 0 0;
            opacity: 0.9;
        }

        .main {
            flex: 1;

            display: flex;
            justify-content: center;
            align-items: center;

            padding: 50px 20px;
        }

        .panels {
            width: 100%;
            max-width: 850px;

            display: grid;
            grid-template-columns: repeat(2, 1fr);

            gap: 30px;
        }

        .panel {
            background: white;

            padding: 50px 30px;

            border-radius: 12px;

            text-align: center;

            box-shadow: 0 4px 15px rgba(0,0,0,0.10);
        }

        .panel h2 {
            color: #1f4e79;
            margin: 15px 0;
        }

        .panel p {
            color: #666;
            line-height: 1.6;
        }

        .icon {
            font-size: 55px;
        }

        .button {
            display: inline-block;

            margin-top: 20px;

            padding: 13px 28px;

            background: #1f4e79;
            color: white;

            text-decoration: none;

            border-radius: 6px;

            font-weight: bold;
        }

        .button:hover {
            background: #163a5c;
        }

        .footer {
            text-align: center;
            padding: 20px;
            color: #777;
        }

        @media (max-width: 700px) {

            .panels {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>


<body>


<header class="header">

    <h1>
        FTC Grading & Tabulation Management System
    </h1>

    <p>
        Welcome to the Management System
    </p>

</header>


<main class="main">

    <div class="panels">


        <!-- ADMIN PANEL -->

        <div class="panel">

            <div class="icon">
                👨‍💼
            </div>

            <h2>
                Admin Panel
            </h2>

            <p>
                Manage students, teachers, batches,
                subjects, electives and marks.
            </p>

            <a
                href="admin/dashboard.php"
                class="button"
            >
                Enter Admin Panel
            </a>

        </div>


        <!-- FACULTY PANEL -->

        <div class="panel">

            <div class="icon">
                👨‍🏫
            </div>

            <h2>
                Faculty Panel
            </h2>

            <p>
                View assigned batches and subjects
                and manage student marks.
            </p>

            <a
                href="teacher/index.php"
                class="button"
            >
                Enter Faculty Panel
            </a>

        </div>


    </div>

</main>


<footer class="footer">

    FTC Grading & Tabulation Management System

</footer>


</body>

</html>