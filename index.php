<?php

require_once __DIR__ . '/imdAuth/index.php';
exit;

error_reporting(E_ALL);
ini_set('display_errors', 1);

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
        FTC Grading & Tabulation Management System
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            min-height: 100vh;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f3f5f7;

            color: #222;

            display: flex;

            flex-direction: column;

        }


        /* =====================================================
           HEADER
        ====================================================== */

        .header {

            background: #1f4e79;

            color: #ffffff;

            padding: 30px 40px;

            border-bottom: 4px solid #163a5c;

        }


        .header-inner {

            max-width: 1200px;

            margin: 0 auto;

        }


        .header h1 {

            margin: 0;

            font-size: 28px;

            font-weight: 600;

            letter-spacing: 0.2px;

        }


        .header p {

            margin: 8px 0 0;

            font-size: 15px;

            opacity: 0.92;

        }



        /* =====================================================
           MAIN
        ====================================================== */

        .main {

            flex: 1;

            width: 100%;

            padding: 55px 30px 65px;

        }


        .main-inner {

            max-width: 1200px;

            margin: 0 auto;

        }


        .intro {

            margin-bottom: 30px;

        }


        .intro h2 {

            margin: 0 0 8px;

            font-size: 24px;

            font-weight: 600;

            color: #1f4e79;

        }


        .intro p {

            margin: 0;

            color: #666;

            font-size: 15px;

            line-height: 1.6;

        }



        /* =====================================================
           PANELS
        ====================================================== */

        .panels {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 24px;

        }


        .panel {

            background: #ffffff;

            border: 1px solid #dfe3e7;

            border-radius: 8px;

            padding: 32px;

            min-height: 330px;

            display: flex;

            flex-direction: column;

            box-shadow:
                0 2px 8px
                rgba(0, 0, 0, 0.06);

            transition:
                box-shadow 0.2s ease,
                transform 0.2s ease;

        }


        .panel:hover {

            transform: translateY(-2px);

            box-shadow:
                0 5px 16px
                rgba(0, 0, 0, 0.10);

        }



        /* =====================================================
           PANEL TOP
        ====================================================== */

        .panel-number {

            width: 42px;

            height: 42px;

            border-radius: 5px;

            background: #eaf1f7;

            color: #1f4e79;

            display: flex;

            align-items: center;

            justify-content: center;

            font-size: 16px;

            font-weight: 700;

            margin-bottom: 22px;

        }


        .panel h2 {

            margin: 0 0 12px;

            color: #1f4e79;

            font-size: 23px;

            font-weight: 600;

        }


        .panel p {

            margin: 0;

            color: #666;

            line-height: 1.65;

            font-size: 14px;

            flex: 1;

        }



        /* =====================================================
           ROLE INFORMATION
        ====================================================== */

        .role-label {

            margin-top: 20px;

            padding-top: 15px;

            border-top: 1px solid #e5e5e5;

            color: #555;

            font-size: 13px;

            font-weight: 600;

            text-transform: uppercase;

            letter-spacing: 0.4px;

        }



        /* =====================================================
           BUTTON
        ====================================================== */

        .button {

            display: block;

            margin-top: 22px;

            padding: 12px 18px;

            background: #1f4e79;

            color: #ffffff;

            text-decoration: none;

            border-radius: 5px;

            font-weight: 600;

            font-size: 14px;

            text-align: center;

            border: 1px solid #1f4e79;

        }


        .button:hover {

            background: #163a5c;

            border-color: #163a5c;

        }



        /* =====================================================
           VERIFICATION BUTTON
        ====================================================== */

        .verification .panel-number {

            background: #eef4ef;

            color: #28623b;

        }


        .verification h2 {

            color: #28623b;

        }


        .verification .button {

            background: #28623b;

            border-color: #28623b;

        }


        .verification .button:hover {

            background: #1f4d2f;

            border-color: #1f4d2f;

        }



        /* =====================================================
           FACULTY
        ====================================================== */

        .faculty .panel-number {

            background: #f3f0e9;

            color: #72521d;

        }


        .faculty h2 {

            color: #1f4e79;

        }



        /* =====================================================
           FOOTER
        ====================================================== */

        .footer {

            border-top: 1px solid #dfe3e7;

            background: #ffffff;

            padding: 18px 30px;

            text-align: center;

            color: #777;

            font-size: 13px;

        }



        /* =====================================================
           RESPONSIVE
        ====================================================== */

        @media (max-width: 950px) {

            .panels {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }


        @media (max-width: 650px) {

            .header {

                padding: 25px 20px;

            }


            .header h1 {

                font-size: 22px;

            }


            .main {

                padding: 35px 18px 45px;

            }


            .panels {

                grid-template-columns: 1fr;

            }


            .panel {

                min-height: auto;

            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     HEADER
====================================================== -->

<header class="header">

    <div class="header-inner">

        <h1>
            FTC Grading & Tabulation Management System
        </h1>

        <p>
            Academic Marks Management and Verification Portal
        </p>

    </div>

</header>



<!-- =====================================================
     MAIN
====================================================== -->

<main class="main">

    <div class="main-inner">


        <div class="intro">

            <h2>
                System Access
            </h2>

            <p>
                Select the appropriate role to access the
                corresponding management and verification
                functions.
            </p>

        </div>



        <div class="panels">


            <!-- =================================================
                 ADMIN
            ================================================== -->

            <div class="panel">

                <div class="panel-number">
                    01
                </div>


                <h2>
                    Admin
                </h2>


                <p>

                    Manage students, faculty, batches,
                    subjects, electives, faculty assignments,
                    marks and other academic records.

                </p>


                <div class="role-label">
                    System Administration
                </div>


                <a
                    href="admin/dashboard.php"
                    class="button"
                >

                    Enter Admin Panel

                </a>

            </div>



            <!-- =================================================
                 FACULTY
            ================================================== -->

            <div class="panel faculty">

                <div class="panel-number">
                    02
                </div>


                <h2>
                    Faculty
                </h2>


                <p>

                    Access assigned batches and subjects,
                    conduct examinations and manage
                    student marks for assigned academic work.

                </p>


                <div class="role-label">
                    Academic Faculty
                </div>


                <a
                    href="teacher/dashboard.php"
                    class="button"
                >

                    Enter Faculty Panel

                </a>

            </div>



            <!-- =================================================
                 MARKS VERIFICATION OFFICER
            ================================================== -->

            <div class="panel verification">

                <div class="panel-number">
                    03
                </div>


                <h2>
                    Marks Verification Officer
                </h2>


                <p>

                    Review and verify marks entered by faculty.
                    Access marksheets and filtered academic
                    records in read-only mode.

                </p>


                <div class="role-label">
                    Marks Verification
                </div>


                <a
                    href="verification/dashboard.php"
                    class="button"
                >

                    Enter Verification Panel

                </a>

            </div>


        </div>


    </div>

</main>



<!-- =====================================================
     FOOTER
====================================================== -->

<footer class="footer">

    <p style="margin: 0 0 8px 0; font-weight: bold; font-size: 14px; color: #1f4e79;">
        FTC Grading &amp; Tabulation Management System
    </p>

    <div style="margin: 10px 0;">
        <span style="font-weight: bold; color: #444; display: block; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; font-size: 12px;">Built by</span>
        <div style="display: flex; justify-content: center; align-items: center; gap: 15px; flex-wrap: wrap;">
            <a href="https://www.linkedin.com/in/arya-pawar-34237a326/" target="_blank" rel="noopener noreferrer" style="color: #1f4e79; text-decoration: underline;">arya-pawar-34237a326</a>
            <span style="color: #ccc;">|</span>
            <a href="https://www.linkedin.com/in/anushka-patil-42585832a/" target="_blank" rel="noopener noreferrer" style="color: #1f4e79; text-decoration: underline;">anushka-patil-42585832a</a>
            <span style="color: #ccc;">|</span>
            <a href="https://www.linkedin.com/in/samarth-bidkar-24nov2005/" target="_blank" rel="noopener noreferrer" style="color: #1f4e79; text-decoration: underline;">samarth-bidkar-24nov2005</a>
            <span style="color: #ccc;">|</span>
            <a href="https://www.linkedin.com/in/nidhish-chincholkar-881821343/" target="_blank" rel="noopener noreferrer" style="color: #1f4e79; text-decoration: underline;">nidhish-chincholkar-881821343</a>
        </div>
    </div>

    <p style="margin-top: 10px; color: #777;">
        &copy; <?php echo date("Y"); ?> All Rights Reserved.
    </p>

</footer>


</body>

</html>