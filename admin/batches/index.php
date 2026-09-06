<?php

require_once "../../config/database.php";

$pageTitle = "Batch Management";

$batches = [];
$stations = [];
$errorMessage = "";

$statusCounts = [
    'Running' => 0,
    'Completed' => 0,
    'Result Declared' => 0,
    'Locked' => 0,
];

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
            status,
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

    foreach ($batches as $batch) {
        $batchStatus = (string)($batch['status'] ?? '');
        if (isset($statusCounts[$batchStatus])) {
            $statusCounts[$batchStatus]++;
        }
    }


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

            <style>
                .batch-summary-grid { display:grid; grid-template-columns:repeat(5, minmax(140px, 1fr)); gap:14px; margin:0 0 22px; }
                .batch-summary-card { background:#fff; border:1px solid #e1e8ef; border-radius:10px; padding:16px 18px; box-shadow:0 2px 8px rgba(31,78,121,.07); }
                .batch-summary-label { color:#64748b; font-size:12px; font-weight:700; letter-spacing:.04em; text-transform:uppercase; }
                .batch-summary-value { color:#1f4e79; font-size:27px; font-weight:800; line-height:1.1; margin-top:8px; }
                .batch-table-wrap { background:#fff; padding:8px; border:1px solid #e1e8ef; border-radius:10px; box-shadow:0 2px 8px rgba(31,78,121,.07); overflow-x:auto; }
                .batch-table { width:100%; min-width:980px; border-collapse:separate; border-spacing:0; }
                .batch-table th { padding:14px 16px; background:#1f4e79; color:#fff; text-align:left; font-size:12px; letter-spacing:.02em; white-space:nowrap; }
                .batch-table th:first-child { border-radius:7px 0 0 0; }
                .batch-table th:last-child { border-radius:0 7px 0 0; }
                .batch-table td { padding:14px 16px; border-bottom:1px solid #edf1f5; color:#334155; vertical-align:middle; }
                .batch-table tbody tr:hover { background:#f8fbfe; }
                .batch-table .serial-cell { width:56px; color:#64748b; text-align:center; }
                .batch-table .number-cell { width:135px; font-weight:700; color:#1f4e79; }
                .batch-table .date-cell { width:145px; white-space:nowrap; }
                .batch-table .station-cell { width:145px; }
                .batch-table .status-cell { width:155px; }
                .batch-table .actions-cell { width:205px; white-space:nowrap; }
                .batch-status-badge { display:inline-flex; align-items:center; justify-content:center; min-width:108px; padding:6px 11px; border-radius:999px; font-size:12px; font-weight:800; line-height:1; white-space:nowrap; }
                .batch-status-active { color:#166534; background:#dcfce7; border:1px solid #bbf7d0; }
                .batch-status-running { color:#075985; background:#e0f2fe; border:1px solid #bae6fd; }
                .batch-status-completed { color:#475569; background:#e2e8f0; border:1px solid #cbd5e1; }
                .batch-status-result { color:#6b21a8; background:#f3e8ff; border:1px solid #e9d5ff; }
                .batch-status-locked { color:#991b1b; background:#fee2e2; border:1px solid #fecaca; }
                .batch-status-unknown { color:#475569; background:#f1f5f9; border:1px solid #e2e8f0; }
                .batch-action { display:inline-block; padding:7px 10px; border-radius:5px; text-decoration:none; font-size:12px; font-weight:700; margin-right:5px; }
                .batch-action-view { color:#1f4e79; background:#e0f2fe; }
                .batch-action-edit { color:#166534; background:#dcfce7; }
                .batch-action-delete { color:#991b1b; background:#fee2e2; }
                @media (max-width:900px) { .batch-summary-grid { grid-template-columns:repeat(2, minmax(140px, 1fr)); } }
                @media (max-width:560px) { .batch-summary-grid { grid-template-columns:1fr; } }
            </style>


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

            <div class="batch-summary-grid" aria-label="Batch summary">
                <?php foreach ([
                    ['label' => 'Total Batches', 'value' => count($batches)],
                    ['label' => 'Running', 'value' => $statusCounts['Running']],
                    ['label' => 'Completed', 'value' => $statusCounts['Completed']],
                    ['label' => 'Result Declared', 'value' => $statusCounts['Result Declared']],
                    ['label' => 'Locked', 'value' => $statusCounts['Locked']],
                ] as $summary): ?>
                    <div class="batch-summary-card">
                        <div class="batch-summary-label"><?php echo htmlspecialchars($summary['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <div class="batch-summary-value"><?php echo (int)$summary['value']; ?></div>
                    </div>
                <?php endforeach; ?>
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

            <div class="batch-table-wrap">


                <table class="batch-table">


                    <thead>

                        <tr>


                            <th>

                                S.No.

                            </th>


                            <th>

                                Batch Number

                            </th>


                            <th>

                                Batch Name

                            </th>


                            <th>

                                Commenced Date

                            </th>


                            <th>

                                Completed Date

                            </th>


                            <th>

                                Station

                            </th>


                            <th>

                                Status

                            </th>


                            <th>

                                Actions

                            </th>


                        </tr>

                    </thead>



                    <tbody>


                    <?php if (count($batches) > 0): ?>


                        <?php foreach ($batches as $index => $batch): ?>


                            <tr>


                                <!-- SERIAL NUMBER -->

                                <td class="serial-cell">

                                    <?php

                                    echo $index + 1;

                                    ?>

                                </td>


                                <!-- BATCH NUMBER -->

                                <td class="number-cell">

                                    <?php echo htmlspecialchars($batch["batch_number"] ?? ""); ?>

                                </td>



                                <!-- BATCH NAME -->

                                <td>

                                    <?php

                                    echo htmlspecialchars(
                                        $batch["batch_name"]
                                    );

                                    ?>

                                </td>



                                <!-- COMMENCED DATE -->

                                <td class="date-cell">

                                    <?php

                                    echo htmlspecialchars(
                                        $batch["commenced_date"]
                                    );

                                    ?>

                                </td>



                                <!-- COMPLETED DATE -->

                                <td class="date-cell">

                                    <?php

                                    echo htmlspecialchars(
                                        $batch["completed_date"]
                                    );

                                    ?>

                                </td>



                                <!-- STATION -->

                                <td class="station-cell">

                                    <?php

                                    echo htmlspecialchars(
                                        $batch["station"]
                                    );

                                    ?>

                                </td>



                                <!-- STATUS -->
                                <?php
                                    $displayStatus = (string)($batch['status'] ?? '');
                                    $statusClass = [
                                        'Active' => 'batch-status-active',
                                        'Running' => 'batch-status-running',
                                        'Completed' => 'batch-status-completed',
                                        'Result Declared' => 'batch-status-result',
                                        'Locked' => 'batch-status-locked',
                                    ][$displayStatus] ?? 'batch-status-unknown';
                                ?>
                                <td class="status-cell">
                                    <span class="batch-status-badge <?php echo $statusClass; ?>">
                                        <?php echo $displayStatus !== '' ? htmlspecialchars($displayStatus, ENT_QUOTES, 'UTF-8') : '—'; ?>
                                    </span>
                                </td>

                                <!-- ACTIONS -->

                                <td class="actions-cell">


                                    <a
                                        href="view.php?id=<?php echo (int)$batch["batch_id"]; ?>"
                                        class="batch-action batch-action-view"
                                    >

                                        View

                                    </a>


                                    <a
                                        href="edit.php?id=<?php echo (int)$batch["batch_id"]; ?>"
                                        class="batch-action batch-action-edit"
                                    >

                                        Edit

                                    </a>

                                    <a
                                        href="delete.php?id=<?php echo (int)$batch["batch_id"]; ?>"
                                        class="batch-action batch-action-delete"
                                    >

                                        Delete

                                    </a>


                                </td>


                            </tr>


                        <?php endforeach; ?>


                    <?php else: ?>


                        <tr>


                            <td
                                colspan="8"
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
