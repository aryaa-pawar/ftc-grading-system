<?php

require_once "../../config/database.php";

$pageTitle = "Add Teacher";

$errorMessage = "";

$facultyName = "";
$email = "";
$contactNumber = "";
$officeLocation = "";


/*
|--------------------------------------------------------------------------
| FORM SUBMISSION
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $facultyName = trim(
        $_POST["faculty_name"] ?? ""
    );

    $email = trim(
        $_POST["email"] ?? ""
    );

    $contactNumber = trim(
        $_POST["contact_number"] ?? ""
    );

    $officeLocation = trim(
        $_POST["office_location"] ?? ""
    );


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if ($facultyName === "") {

        $errorMessage = "Faculty name is required.";

    } elseif ($email === "") {

        $errorMessage = "Email is required.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $errorMessage = "Please enter a valid email address.";

    } elseif (!preg_match('/^[0-9]{10}$/', $contactNumber)) {

        $errorMessage = "Contact number must contain exactly 10 digits.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | INSERT FACULTY
            |--------------------------------------------------------------------------
            */

            $defaultPasswordHash = password_hash('123', PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO faculties
                (
                    faculty_name,
                    email,
                    password,
                    contact_number,
                    office_location,
                    is_deleted
                )
                VALUES
                (
                    :faculty_name,
                    :email,
                    :password,
                    :contact_number,
                    :office_location,
                    0
                )
            ");


            $stmt->execute([

                ":faculty_name" => $facultyName,

                ":email" => $email,

                ":password" => $defaultPasswordHash,

                ":contact_number" => $contactNumber,

                ":office_location" => $officeLocation

            ]);


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            header("Location: index.php");

            exit;


        } catch (PDOException $e) {

            $errorMessage = $e->getMessage();

        }

    }

}


require_once "../../includes/header.php";

?>


<div style="
    display:flex;
    flex-direction:column;
    min-height:100vh;
">


    <?php require_once "../../includes/navbar.php"; ?>


    <div style="
        display:flex;
        flex:1;
    ">


        <?php require_once "../../includes/sidebar.php"; ?>


        <main style="
            flex:1;
            padding:30px;
            background:#f4f6f8;
        ">


            <!-- PAGE TITLE -->

            <div style="
                margin-bottom:25px;
            ">

                <h1 style="
                    margin:0 0 8px 0;
                    color:#1f4e79;
                    font-size:28px;
                ">
                    Add Teacher
                </h1>


                <p style="
                    margin:0;
                    color:#666;
                ">
                    Add a new faculty member.
                </p>

            </div>


            <!-- ERROR MESSAGE -->

            <?php if ($errorMessage !== ""): ?>

                <div style="
                    background:#fdecea;
                    color:#a12622;
                    border:1px solid #f5c6cb;
                    padding:15px;
                    border-radius:6px;
                    margin-bottom:20px;
                ">

                    <?php
                    echo htmlspecialchars(
                        $errorMessage
                    );
                    ?>

                </div>

            <?php endif; ?>


            <!-- FORM -->

            <div style="
                background:#ffffff;
                padding:25px;
                border-radius:8px;
                max-width:700px;
                box-shadow:0 2px 8px rgba(0,0,0,0.08);
            ">


                <form
                    method="POST"
                    action="add.php"
                >


                    <!-- FACULTY NAME -->

                    <div style="
                        margin-bottom:20px;
                    ">

                        <label
                            for="faculty_name"
                            style="
                                display:block;
                                font-weight:bold;
                                margin-bottom:8px;
                            "
                        >
                            Faculty Name *
                        </label>


                        <input
                            type="text"
                            id="faculty_name"
                            name="faculty_name"
                            value="<?php
                                echo htmlspecialchars(
                                    $facultyName
                                );
                            ?>"
                            required
                            style="
                                width:100%;
                                padding:11px;
                                border:1px solid #cccccc;
                                border-radius:5px;
                            "
                        >

                    </div>


                    <!-- EMAIL -->

                    <div style="
                        margin-bottom:20px;
                    ">

                        <label
                            for="email"
                            style="
                                display:block;
                                font-weight:bold;
                                margin-bottom:8px;
                            "
                        >
                            Email *
                        </label>


                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?php
                                echo htmlspecialchars(
                                    $email
                                );
                            ?>"
                            required
                            style="
                                width:100%;
                                padding:11px;
                                border:1px solid #cccccc;
                                border-radius:5px;
                            "
                        >

                    </div>


                    <!-- CONTACT NUMBER -->

                    <div style="
                        margin-bottom:20px;
                    ">

                        <label
                            for="contact_number"
                            style="
                                display:block;
                                font-weight:bold;
                                margin-bottom:8px;
                            "
                        >
                            Contact Number *
                        </label>


                        <input
                            type="text"
                            id="contact_number"
                            name="contact_number"
                            value="<?php
                                echo htmlspecialchars(
                                    $contactNumber
                                );
                            ?>"
                            maxlength="10"
                            minlength="10"
                            pattern="[0-9]{10}"
                            inputmode="numeric"
                            placeholder="Enter 10-digit contact number"
                            title="Contact number must contain exactly 10 digits"
                            required
                            style="
                                width:100%;
                                padding:11px;
                                border:1px solid #cccccc;
                                border-radius:5px;
                            "
                        >

                    </div>


                    <!-- OFFICE LOCATION -->

                    <div style="
                        margin-bottom:25px;
                    ">

                        <label
                            for="office_location"
                            style="
                                display:block;
                                font-weight:bold;
                                margin-bottom:8px;
                            "
                        >
                            Office Location
                        </label>


                        <input
                            type="text"
                            id="office_location"
                            name="office_location"
                            value="<?php
                                echo htmlspecialchars(
                                    $officeLocation
                                );
                            ?>"
                            maxlength="150"
                            placeholder="Example: Room 204, IT Department"
                            style="
                                width:100%;
                                padding:11px;
                                border:1px solid #cccccc;
                                border-radius:5px;
                            "
                        >

                    </div>


                    <!-- BUTTONS -->

                    <button
                        type="submit"
                        style="
                            background:#1f4e79;
                            color:white;
                            border:none;
                            padding:12px 22px;
                            border-radius:6px;
                            cursor:pointer;
                            font-weight:bold;
                            margin-right:10px;
                        "
                    >
                        Save Teacher
                    </button>


                    <a
                        href="index.php"
                        style="
                            display:inline-block;
                            background:#777777;
                            color:white;
                            padding:12px 22px;
                            border-radius:6px;
                            text-decoration:none;
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