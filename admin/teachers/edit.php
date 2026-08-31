<?php

require_once "../../config/database.php";

$pageTitle = "Edit Teacher";

$errorMessage = "";

$facultyId = isset($_GET["id"])
    ? (int) $_GET["id"]
    : (int) ($_POST["faculty_id"] ?? 0);


/*
|--------------------------------------------------------------------------
| VALIDATE FACULTY ID
|--------------------------------------------------------------------------
*/

if ($facultyId <= 0) {

    die("Invalid faculty ID.");

}


/*
|--------------------------------------------------------------------------
| GET FACULTY
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        faculty_id,
        faculty_name,
        email,
        contact_number,
        office_location,
        is_deleted
    FROM faculties
    WHERE faculty_id = :faculty_id
");


$stmt->execute([
    ":faculty_id" => $facultyId
]);


$teacher = $stmt->fetch(PDO::FETCH_ASSOC);


if (!$teacher) {

    die("Faculty not found.");

}


/*
|--------------------------------------------------------------------------
| FORM VALUES
|--------------------------------------------------------------------------
*/

$facultyName = $teacher["faculty_name"] ?? "";

$email = $teacher["email"] ?? "";

$contactNumber = $teacher["contact_number"] ?? "";

$officeLocation = $teacher["office_location"] ?? "";


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

        $errorMessage =
            "Faculty name is required.";

    } elseif ($email === "") {

        $errorMessage =
            "Email is required.";

    } elseif (!filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )) {

        $errorMessage =
            "Please enter a valid email address.";

    } elseif (!preg_match(
        '/^[0-9]{10}$/',
        $contactNumber
    )) {

        $errorMessage =
            "Contact number must contain exactly 10 digits.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | UPDATE FACULTY
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            | is_deleted is NOT changed here.
            |
            */

            $stmt = $pdo->prepare("
                UPDATE faculties

                SET
                    faculty_name = :faculty_name,
                    email = :email,
                    contact_number = :contact_number,
                    office_location = :office_location

                WHERE faculty_id = :faculty_id
            ");


            $stmt->execute([

                ":faculty_name" =>
                    $facultyName,

                ":email" =>
                    $email,

                ":contact_number" =>
                    $contactNumber,

                ":office_location" =>
                    $officeLocation,

                ":faculty_id" =>
                    $facultyId

            ]);


            /*
            |--------------------------------------------------------------------------
            | SUCCESS
            |--------------------------------------------------------------------------
            */

            header(
                "Location: view.php?id=" .
                $facultyId
            );

            exit;


        } catch (PDOException $e) {

            $errorMessage =
                "Unable to update faculty: " .
                $e->getMessage();

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
                    Edit Faculty
                </h1>


                <p style="
                    margin:0;
                    color:#666;
                ">
                    Update faculty information.
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
                    action="edit.php?id=<?php echo $facultyId; ?>"
                >


                    <!-- FACULTY ID -->

                    <input
                        type="hidden"
                        name="faculty_id"
                        value="<?php
                            echo $facultyId;
                        ?>"
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
                            background:#278a4b;
                            color:white;
                            border:none;
                            padding:12px 22px;
                            border-radius:6px;
                            cursor:pointer;
                            font-weight:bold;
                            margin-right:10px;
                        "
                    >
                        Update Faculty
                    </button>


                    <a
                        href="view.php?id=<?php echo $facultyId; ?>"
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