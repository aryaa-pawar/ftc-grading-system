<?php

require_once "../../config/database.php";

$pageTitle = "Add Batch";

$errorMessage = "";

$batchName = "";
$batchNumber = "";
$commencedDate = "";
$completedDate = "";
$station = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $batchName = trim(
        $_POST["batch_name"] ?? ""
    );

    $batchNumber = trim(
        $_POST["batch_number"] ?? ""
    );

    $commencedDate = trim(
        $_POST["commenced_date"] ?? ""
    );

    $completedDate = trim(
        $_POST["completed_date"] ?? ""
    );

    $station = trim(
        $_POST["station"] ?? ""
    );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($batchName === "") {

        $errorMessage = "Batch name is required.";

    } elseif ($batchNumber === "") {

        $errorMessage = "Batch number is required.";

    } elseif ($commencedDate === "") {

        $errorMessage = "Commenced date is required.";

    } elseif ($completedDate === "") {

        $errorMessage = "Completed date is required.";

    } elseif ($station === "") {

        $errorMessage = "Station is required.";

    } elseif ($completedDate < $commencedDate) {

        $errorMessage =
            "Completed date cannot be before commenced date.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | Insert into batches table
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                INSERT INTO batches
                (
                    batch_number,
                    batch_name,
                    commenced_date,
                    completed_date,
                    station
                )
                VALUES
                (
                    :batch_number,
                    :batch_name,
                    :commenced_date,
                    :completed_date,
                    :station
                )
            ");


            $stmt->execute([

                ":batch_number" =>
                    $batchNumber,

                ":batch_name" =>
                    $batchName,

                ":commenced_date" =>
                    $commencedDate,

                ":completed_date" =>
                    $completedDate,

                ":station" =>
                    $station

            ]);


            /*
            |--------------------------------------------------------------------------
            | Successfully added
            |--------------------------------------------------------------------------
            */

            header(
                "Location: index.php"
            );

            exit;


        } catch (PDOException $e) {

            $errorMessage =
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
                    Add Batch
                </h1>

                <p style="
                    margin:0;
                    color:#666;
                ">
                    Create a new FTC training batch.
                </p>

            </div>


            <!-- ERROR -->

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


            <!-- FORM CARD -->

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


                    <!-- BATCH NUMBER -->

                    <div style="margin-bottom:20px;">

                        <label for="batch_number" style="display:block;font-weight:bold;margin-bottom:8px;">
                            Batch Number
                        </label>

                        <input
                            type="text"
                            id="batch_number"
                            name="batch_number"
                            value="<?php echo htmlspecialchars($batchNumber); ?>"
                            placeholder="Enter batch number"
                            required
                            style="width:100%;padding:11px;border:1px solid #cccccc;border-radius:5px;"
                        >

                    </div>


                    <!-- BATCH NAME -->

                    <div style="
                        margin-bottom:20px;
                    ">

                        <label
                            for="batch_name"
                            style="
                                display:block;
                                font-weight:bold;
                                margin-bottom:8px;
                            "
                        >
                            Batch Name
                        </label>


                        <input
                            type="text"
                            id="batch_name"
                            name="batch_name"
                            value="<?php
                                echo htmlspecialchars(
                                    $batchName
                                );
                            ?>"
                            placeholder="Enter batch name"
                            required
                            style="
                                width:100%;
                                padding:11px;
                                border:1px solid #cccccc;
                                border-radius:5px;
                            "
                        >

                    </div>


                    <!-- COMMENCED DATE -->

                    <div style="
                        margin-bottom:20px;
                    ">

                        <label
                            for="commenced_date"
                            style="
                                display:block;
                                font-weight:bold;
                                margin-bottom:8px;
                            "
                        >
                            Commenced Date
                        </label>


                        <input
                            type="date"
                            id="commenced_date"
                            name="commenced_date"
                            value="<?php
                                echo htmlspecialchars(
                                    $commencedDate
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


                    <!-- COMPLETED DATE -->

                    <div style="
                        margin-bottom:20px;
                    ">

                        <label
                            for="completed_date"
                            style="
                                display:block;
                                font-weight:bold;
                                margin-bottom:8px;
                            "
                        >
                            Completed Date
                        </label>


                        <input
                            type="date"
                            id="completed_date"
                            name="completed_date"
                            value="<?php
                                echo htmlspecialchars(
                                    $completedDate
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


                    <!-- STATION -->

                    <div style="
                        margin-bottom:25px;
                    ">

                        <label
                            for="station"
                            style="
                                display:block;
                                font-weight:bold;
                                margin-bottom:8px;
                            "
                        >
                            Station
                        </label>


                        <input
                            type="text"
                            id="station"
                            name="station"
                            value="<?php
                                echo htmlspecialchars(
                                    $station
                                );
                            ?>"
                            placeholder="Enter station"
                            required
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
                        Save Batch
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