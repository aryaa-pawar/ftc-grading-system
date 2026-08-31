<?php

require_once "../../config/database.php";

$pageTitle = "Batch Management";

$batches = [];
$stations = [];
$errorMessage = "";

$selectedStation = isset($_GET["station"])
    ? trim($_GET["station"])
    : "";


/*
|--------------------------------------------------------------------------
| GET STATIONS
|--------------------------------------------------------------------------
*/

try {

    $stationStmt = $pdo->query("
        SELECT DISTINCT station
        FROM batches
        WHERE station IS NOT NULL
        AND station <> ''
        ORDER BY station ASC
    ");

    $stations = $stationStmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {

    $errorMessage = $e->getMessage();

}


/*
|--------------------------------------------------------------------------
| GET BATCHES
|--------------------------------------------------------------------------
*/

try {

    $sql = "
        SELECT
            batch_id,
            batch_number,
            batch_name,
            commenced_date,
            completed_date,
            station,
            created_at,
            updated_at
        FROM batches
    ";

    $params = [];


    if ($selectedStation !== "") {

        $sql .= "
            WHERE station = :station
        ";

        $params[":station"] = $selectedStation;

    }


    $sql .= "
        ORDER BY batch_id ASC
    ";


    $stmt = $pdo->prepare($sql);

    $stmt->execute($params);

    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {

    $errorMessage = $e->getMessage();

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


            <!-- PAGE HEADER -->

            <div style="
                display:flex;
                justify-content:space-between;
                align-items:center;
                margin-bottom:25px;
            ">


                <div>

                    <h1 style="
                        margin:0 0 8px 0;
                        color:#1f4e79;
                        font-size:28px;
                    ">

                        Batch Management

                    </h1>


                    <p style="
                        margin:0;
                        color:#666;
                    ">

                        Manage FTC training batches.

                    </p>

                </div>


                <!-- ADD BATCH -->

                <a
                    href="add.php"
                    style="
                        display:inline-block;
                        background:#1f4e79;
                        color:#ffffff;
                        padding:12px 20px;
                        border-radius:6px;
                        text-decoration:none;
                        cursor:pointer;
                        font-weight:bold;
                    "
                >

                    + Add Batch

                </a>

            </div>



            <!-- =================================================
                 STATION FILTER
            ================================================== -->

            <div style="
                background:#ffffff;
                padding:16px 20px;
                border-radius:8px;
                box-shadow:0 2px 8px rgba(0,0,0,0.08);
                margin-bottom:20px;
            ">


                <form
                    method="GET"
                    action="index.php"
                    style="
                        display:flex;
                        align-items:flex-end;
                        gap:12px;
                        flex-wrap:wrap;
                    "
                >


                    <div>

                        <label
                            for="station"
                            style="
                                display:block;
                                margin-bottom:6px;
                                font-weight:bold;
                                color:#444;
                            "
                        >

                            Filter by Station

                        </label>


                        <select
                            id="station"
                            name="station"
                            style="
                                min-width:220px;
                                padding:9px 12px;
                                border:1px solid #ccc;
                                border-radius:5px;
                                font-size:14px;
                            "
                        >


                            <option value="">

                                All Stations

                            </option>


                            <?php foreach ($stations as $station): ?>

                                <option
                                    value="<?php echo htmlspecialchars($station); ?>"
                                    <?php
                                    if ($selectedStation === $station) {
                                        echo "selected";
                                    }
                                    ?>
                                >

                                    <?php
                                    echo htmlspecialchars($station);
                                    ?>

                                </option>

                            <?php endforeach; ?>


                        </select>

                    </div>


                    <button
                        type="submit"
                        style="
                            background:#1f4e79;
                            color:#ffffff;
                            border:none;
                            padding:9px 17px;
                            border-radius:5px;
                            cursor:pointer;
                            font-weight:bold;
                        "
                    >

                        Filter

                    </button>


                    <?php if ($selectedStation !== ""): ?>

                        <a
                            href="index.php"
                            style="
                                background:#eeeeee;
                                color:#333333;
                                padding:9px 17px;
                                border-radius:5px;
                                text-decoration:none;
                                font-weight:bold;
                            "
                        >

                            Clear

                        </a>

                    <?php endif; ?>


                </form>


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

                    <strong>
                        Unable to load batches:
                    </strong>

                    <br><br>

                    <?php

                    echo htmlspecialchars(
                        $errorMessage
                    );

                    ?>

                </div>

            <?php endif; ?>



            <!-- =================================================
                 BATCH TABLE
            ================================================== -->

            <div style="
                background:#ffffff;
                padding:20px;
                border-radius:8px;
                box-shadow:0 2px 8px rgba(0,0,0,0.08);
                overflow-x:auto;
            ">


                <table style="
                    width:100%;
                    border-collapse:collapse;
                    min-width:850px;
                ">


                    <thead>

                        <tr>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">

                                S.No.

                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">

                                Batch Number

                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">

                                Batch Name

                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">

                                Commenced Date

                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">

                                Completed Date

                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">

                                Station

                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">

                                Actions

                            </th>


                        </tr>

                    </thead>



                    <tbody>


                    <?php if (count($batches) > 0): ?>


                        <?php foreach ($batches as $index => $batch): ?>


                            <tr>


                                <!-- SERIAL NUMBER -->

                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eeeeee;
                                ">

                                    <?php

                                    echo $index + 1;

                                    ?>

                                </td>


                                <!-- BATCH NUMBER -->

                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eeeeee;
                                ">

                                    <?php echo htmlspecialchars($batch["batch_number"] ?? ""); ?>

                                </td>



                                <!-- BATCH NAME -->

                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eeeeee;
                                    font-weight:bold;
                                ">

                                    <?php

                                    echo htmlspecialchars(
                                        $batch["batch_name"]
                                    );

                                    ?>

                                </td>



                                <!-- COMMENCED DATE -->

                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eeeeee;
                                ">

                                    <?php

                                    echo htmlspecialchars(
                                        $batch["commenced_date"]
                                    );

                                    ?>

                                </td>



                                <!-- COMPLETED DATE -->

                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eeeeee;
                                ">

                                    <?php

                                    echo htmlspecialchars(
                                        $batch["completed_date"]
                                    );

                                    ?>

                                </td>



                                <!-- STATION -->

                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eeeeee;
                                ">

                                    <?php

                                    echo htmlspecialchars(
                                        $batch["station"]
                                    );

                                    ?>

                                </td>



                                <!-- ACTIONS -->

                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eeeeee;
                                    white-space:nowrap;
                                ">


                                    <a
                                        href="view.php?id=<?php echo (int)$batch["batch_id"]; ?>"
                                        style="
                                            color:#1f4e79;
                                            text-decoration:none;
                                            margin-right:15px;
                                        "
                                    >

                                        View

                                    </a>


                                    <a
                                        href="edit.php?id=<?php echo (int)$batch["batch_id"]; ?>"
                                        style="
                                            color:#278a4b;
                                            text-decoration:none;
                                        "
                                    >

                                        Edit

                                    </a>

                                    <a
                                        href="delete.php?id=<?php echo (int)$batch["batch_id"]; ?>"
                                        style="
                                            color:#c0392b;
                                            text-decoration:none;
                                            margin-left:15px;
                                        "
                                    >

                                        Delete

                                    </a>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>


                            <td
                                colspan="7"
                                style="
                                    padding:35px;
                                    text-align:center;
                                    color:#666;
                                "
                            >

                                No batches found.

                            </td>


                        </tr>


                    <?php endif; ?>


                    </tbody>


                </table>


            </div>


        </main>


    </div>


    <?php require_once "../../includes/footer.php"; ?>


</div>