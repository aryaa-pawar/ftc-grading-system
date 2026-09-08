<?php

require_once __DIR__ . "/../../config/database.php";

$pageTitle = "Paper-Subject Grouping Dashboard";

$errorMessage = "";
$successMessage = "";

/*
|--------------------------------------------------------------------------
| FETCH BATCHES
|--------------------------------------------------------------------------
*/

$batches = [];

try {

    $stmt = $pdo->query("
        SELECT batch_id, batch_name
        FROM batches
        ORDER BY batch_id DESC
    ");

    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $errorMessage = "Failed to load batches: " . $e->getMessage();

}


/*
|--------------------------------------------------------------------------
| SELECTED BATCH
|--------------------------------------------------------------------------
*/

$selectedBatchId = isset($_GET['batch_id'])
    ? (int)$_GET['batch_id']
    : ($batches[0]['batch_id'] ?? 0);

$paperSearch = trim($_GET['paper_name'] ?? '');


/*
|--------------------------------------------------------------------------
| HANDLE POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | ADD MAPPING
    |--------------------------------------------------------------------------
    */

    if ($action === 'add_mapping') {

        $paperId = (int)($_POST['paper_id'] ?? 0);
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $batchId = (int)($_POST['batch_id'] ?? 0);

        $weightage = (float)($_POST['subject_weightage_pct'] ?? 100.00);

        $maxInt = (float)($_POST['subject_max_internal'] ?? 0);
        $maxMid = (float)($_POST['subject_max_midterm'] ?? 0);
        $maxFin = (float)($_POST['subject_max_final'] ?? 0);


        if ($paperId > 0 && $subjectId > 0 && $batchId > 0) {

            try {

                $stmt = $pdo->prepare("
                    INSERT INTO paper_subject_mapping
                    (
                        paper_id,
                        subject_id,
                        batch_id,
                        subject_weightage_pct,
                        subject_max_internal,
                        subject_max_midterm,
                        subject_max_final
                    )

                    VALUES
                    (
                        :paper_id,
                        :subject_id,
                        :batch_id,
                        :weightage,
                        :max_int,
                        :max_mid,
                        :max_fin
                    )

                    ON DUPLICATE KEY UPDATE

                        subject_weightage_pct =
                            VALUES(subject_weightage_pct),

                        subject_max_internal =
                            VALUES(subject_max_internal),

                        subject_max_midterm =
                            VALUES(subject_max_midterm),

                        subject_max_final =
                            VALUES(subject_max_final)
                ");

                $stmt->execute([
                    ':paper_id' => $paperId,
                    ':subject_id' => $subjectId,
                    ':batch_id' => $batchId,
                    ':weightage' => $weightage,
                    ':max_int' => $maxInt,
                    ':max_mid' => $maxMid,
                    ':max_fin' => $maxFin
                ]);

                $successMessage =
                    "Subject mapped to paper successfully with max marks configuration.";

                $selectedBatchId = $batchId;

            } catch (PDOException $e) {

                $errorMessage =
                    "Mapping error: " . $e->getMessage();

            }

        } else {

            $errorMessage =
                "Please select a paper, subject and batch.";

        }

    }


    /*
    |--------------------------------------------------------------------------
    | REMOVE MAPPING
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'remove_mapping') {

        $paperId = (int)($_POST['paper_id'] ?? 0);
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $batchId = (int)($_POST['batch_id'] ?? 0);


        if ($paperId > 0 && $subjectId > 0 && $batchId > 0) {

            try {

                $stmt = $pdo->prepare("
                    DELETE FROM paper_subject_mapping

                    WHERE paper_id = :paper_id
                    AND subject_id = :subject_id
                    AND batch_id = :batch_id
                ");

                $stmt->execute([
                    ':paper_id' => $paperId,
                    ':subject_id' => $subjectId,
                    ':batch_id' => $batchId
                ]);

                $successMessage =
                    "Subject mapping removed successfully.";

                $selectedBatchId = $batchId;

            } catch (PDOException $e) {

                $errorMessage =
                    "Failed to remove mapping: " . $e->getMessage();

            }

        }

    }

}


/*
|--------------------------------------------------------------------------
| FETCH PAPERS
|--------------------------------------------------------------------------
*/

$papers = [];

try {

    $stmt = $pdo->prepare("
        SELECT *
        FROM papers
        WHERE batch_id = :batch_id
        AND (:paper_name = '' OR paper_name LIKE :paper_name_like)
        ORDER BY paper_id ASC
    ");

    $stmt->execute([
        ':batch_id' => $selectedBatchId,
        ':paper_name' => $paperSearch,
        ':paper_name_like' => '%' . $paperSearch . '%'
    ]);

    $papers = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $errorMessage =
        "Failed to load papers: " . $e->getMessage();

}


/*
|--------------------------------------------------------------------------
| FETCH SUBJECTS
|--------------------------------------------------------------------------
*/

$subjects = [];

try {

    $stmt = $pdo->query("
        SELECT *
        FROM subjects
        ORDER BY subject_code ASC
    ");

    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $errorMessage =
        "Failed to load subjects: " . $e->getMessage();

}


/*
|--------------------------------------------------------------------------
| FETCH EXISTING MAPPINGS
|--------------------------------------------------------------------------
*/

$mappings = [];

if ($selectedBatchId > 0) {

    try {

        $stmt = $pdo->prepare("
            SELECT

                psm.paper_id,
                psm.subject_id,
                psm.batch_id,

                psm.subject_weightage_pct,

                psm.subject_max_internal,
                psm.subject_max_midterm,
                psm.subject_max_final,

                s.subject_code,
                s.subject_name,
                s.is_elective

            FROM paper_subject_mapping psm

            INNER JOIN subjects s
                ON psm.subject_id = s.subject_id

            WHERE psm.batch_id = :batch_id

            ORDER BY
                psm.paper_id ASC,
                s.subject_code ASC
        ");

        $stmt->execute([
            ':batch_id' => $selectedBatchId
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


        foreach ($rows as $r) {

            $mappings[$r['paper_id']][] = $r;

        }

    } catch (PDOException $e) {

        $errorMessage =
            "Failed to load mappings: " . $e->getMessage();

    }

}


require_once "../../includes/header.php";

?>

<div style="display:flex; flex-direction:column; min-height:100vh;">

    <?php require_once "../../includes/navbar.php"; ?>


    <div style="display:flex; flex:1;">

        <?php require_once "../../includes/sidebar.php"; ?>


        <main style="flex:1; padding:30px; background:#f4f6f8;">

            <!-- PAGE HEADER -->

            <div style="
                display:flex;
                justify-content:space-between;
                align-items:center;
                margin-bottom:25px;
            ">

                <div>

                    <h1 style="
                        margin:0;
                        font-size:26px;
                        color:#1f4e79;
                    ">
                        Paper-Subject Grouping Dashboard
                    </h1>

                    <p style="
                        margin:5px 0 0;
                        color:#666666;
                    ">
                        Group subjects into theory papers (Paper I, II, etc.),
                        practicals, viva, project work, and OJT for evaluation.
                    </p>

                </div>


                <div>

                    <a
                        href="index.php"
                        style="
                            background:#6c757d;
                            color:#fff;
                            padding:10px 18px;
                            text-decoration:none;
                            border-radius:4px;
                            font-weight:bold;
                        "
                    >
                        ← Back to Papers List
                    </a>

                </div>

            </div>


            <!-- ERROR -->

            <?php if (!empty($errorMessage)): ?>

                <div style="
                    padding:15px;
                    background:#f8d7da;
                    color:#721c24;
                    border-radius:4px;
                    margin-bottom:20px;
                ">

                    <?php
                    echo htmlspecialchars($errorMessage);
                    ?>

                </div>

            <?php endif; ?>


            <!-- SUCCESS -->

            <?php if (!empty($successMessage)): ?>

                <div style="
                    padding:15px;
                    background:#d4edda;
                    color:#155724;
                    border-radius:4px;
                    margin-bottom:20px;
                ">

                    <?php
                    echo htmlspecialchars($successMessage);
                    ?>

                </div>

            <?php endif; ?>


            <!-- BATCH + MAPPING -->

            <div style="
                background:#ffffff;
                border-radius:8px;
                border:1px solid #e0e0e0;
                padding:20px;
                margin-bottom:30px;
                box-shadow:0 2px 4px rgba(0,0,0,0.05);
            ">


                <!-- BATCH SELECT -->

                <form
                    method="GET"
                    action=""
                    style="
                        display:flex;
                        align-items:center;
                        gap:15px;
                        flex-wrap:wrap;
                        margin-bottom:20px;
                        padding-bottom:15px;
                        border-bottom:1px solid #eee;
                    "
                >

                    <label style="
                        font-weight:bold;
                        color:#1f4e79;
                        font-size:16px;
                    ">
                        Select Training Batch:
                    </label>


                    <select
                        name="batch_id"
                        onchange="this.form.submit()"
                        style="
                            padding:10px 15px;
                            border:1px solid #1f4e79;
                            border-radius:4px;
                            font-size:15px;
                            font-weight:bold;
                            color:#1f4e79;
                            background:#f9fbfd;
                        "
                    >

                        <?php foreach ($batches as $b): ?>

                            <option
                                value="<?php echo (int)$b['batch_id']; ?>"
                                <?php
                                echo (
                                    $b['batch_id'] == $selectedBatchId
                                )
                                ? 'selected'
                                : '';
                                ?>
                            >

                                <?php
                                echo htmlspecialchars(
                                    $b['batch_name']
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </form>


                <form method="GET" action="" style="display:flex; align-items:flex-end; gap:10px; flex-wrap:wrap; margin-bottom:20px;">
                    <input type="hidden" name="batch_id" value="<?php echo (int)$selectedBatchId; ?>">

                    <div style="min-width:260px;">
                        <label for="paper_name" style="display:block; font-weight:bold; color:#1f4e79; font-size:13px; margin-bottom:5px;">
                            Search Paper by Name
                        </label>
                        <input
                            type="search"
                            id="paper_name"
                            name="paper_name"
                            value="<?php echo htmlspecialchars($paperSearch); ?>"
                            placeholder="Enter paper name"
                            style="width:100%; padding:10px 12px; border:1px solid #ccc; border-radius:4px; font-size:14px;"
                        >
                    </div>

                    <button type="submit" style="padding:10px 18px; background:#1f4e79; color:#fff; border:0; border-radius:4px; font-weight:bold; cursor:pointer;">
                        Search
                    </button>

                    <?php if ($paperSearch !== ''): ?>
                        <a href="?batch_id=<?php echo (int)$selectedBatchId; ?>" style="padding:10px 18px; background:#6c757d; color:#fff; border-radius:4px; text-decoration:none; font-weight:bold;">
                            Clear
                        </a>
                    <?php endif; ?>
                </form>


                <!-- ADD MAPPING FORM -->

                <form
                    method="POST"
                    action=""
                    style="
                        display:flex;
                        align-items:flex-end;
                        gap:15px;
                        flex-wrap:wrap;
                    "
                >

                    <input
                        type="hidden"
                        name="action"
                        value="add_mapping"
                    >

                    <input
                        type="hidden"
                        name="batch_id"
                        value="<?php echo (int)$selectedBatchId; ?>"
                    >


                    <!-- PAPER -->

                    <div style="flex:1; min-width:200px;">

                        <label style="
                            display:block;
                            font-weight:bold;
                            font-size:13px;
                            margin-bottom:5px;
                            color:#555;
                        ">
                            Select Target Paper
                        </label>


                        <select
                            name="paper_id"
                            required
                            style="
                                width:100%;
                                padding:10px;
                                border:1px solid #ccc;
                                border-radius:4px;
                                font-size:14px;
                            "
                        >

                            <option value="">
                                -- Choose Paper --
                            </option>


                            <?php foreach ($papers as $p): ?>

                                <option
                                    value="<?php echo (int)$p['paper_id']; ?>"
                                >

                                    [
                                    <?php
                                    echo htmlspecialchars(
                                        $p['paper_code']
                                    );
                                    ?>
                                    ]

                                    <?php
                                    echo htmlspecialchars(
                                        $p['paper_name']
                                    );
                                    ?>

                                    (
                                    <?php
                                    echo htmlspecialchars(
                                        $p['paper_type']
                                    );
                                    ?>
                                    )

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- SUBJECT -->

                    <div style="flex:1; min-width:220px;">

                        <label style="
                            display:block;
                            font-weight:bold;
                            font-size:13px;
                            margin-bottom:5px;
                            color:#555;
                        ">
                            Select Subject to Map
                        </label>


                        <select
                            name="subject_id"
                            required
                            style="
                                width:100%;
                                padding:10px;
                                border:1px solid #ccc;
                                border-radius:4px;
                                font-size:14px;
                            "
                        >

                            <option value="">
                                -- Choose Subject --
                            </option>


                            <?php foreach ($subjects as $s): ?>

                                <option
                                    value="<?php echo (int)$s['subject_id']; ?>"
                                >

                                    [
                                    <?php
                                    echo htmlspecialchars(
                                        $s['subject_code']
                                    );
                                    ?>
                                    ]

                                    <?php
                                    echo htmlspecialchars(
                                        $s['subject_name']
                                    );
                                    ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <!-- INTERNAL -->

                    <div style="width:110px;">

                        <label style="
                            display:block;
                            font-weight:bold;
                            font-size:13px;
                            margin-bottom:5px;
                            color:#555;
                        ">
                            Int Max
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="subject_max_internal"
                            value="18.00"
                            style="
                                width:100%;
                                padding:10px;
                                border:1px solid #ccc;
                                border-radius:4px;
                                font-size:14px;
                            "
                        >

                    </div>


                    <!-- MID -->

                    <div style="width:110px;">

                        <label style="
                            display:block;
                            font-weight:bold;
                            font-size:13px;
                            margin-bottom:5px;
                            color:#555;
                        ">
                            Mid Max
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="subject_max_midterm"
                            value="15.00"
                            style="
                                width:100%;
                                padding:10px;
                                border:1px solid #ccc;
                                border-radius:4px;
                                font-size:14px;
                            "
                        >

                    </div>


                    <!-- FINAL -->

                    <div style="width:110px;">

                        <label style="
                            display:block;
                            font-weight:bold;
                            font-size:13px;
                            margin-bottom:5px;
                            color:#555;
                        ">
                            Fin Max
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="subject_max_final"
                            value="27.00"
                            style="
                                width:100%;
                                padding:10px;
                                border:1px solid #ccc;
                                border-radius:4px;
                                font-size:14px;
                            "
                        >

                    </div>


                    <!-- BUTTON -->

                    <div>

                        <button
                            type="submit"
                            style="
                                padding:10px 20px;
                                background:#1f4e79;
                                color:#ffffff;
                                border:none;
                                border-radius:4px;
                                font-weight:bold;
                                cursor:pointer;
                            "
                        >
                            + Map Subject
                        </button>

                    </div>

                </form>

            </div>


            <!-- PAPER MAPPINGS -->

            <h2 style="
                font-size:20px;
                color:#1f4e79;
                margin-bottom:15px;
            ">

                Paper Section Mappings
                (Batch #<?php echo (int)$selectedBatchId; ?>)

            </h2>


            <div style="
                display:grid;
                grid-template-columns:
                    repeat(auto-fill, minmax(420px, 1fr));
                gap:20px;
            ">


                <?php foreach ($papers as $p): ?>

                    <?php

                    $pId = (int)$p['paper_id'];

                    $mappedList =
                        $mappings[$pId] ?? [];

                    ?>


                    <div style="
                        background:#ffffff;
                        border-radius:8px;
                        border:1px solid #e0e0e0;
                        box-shadow:
                            0 2px 4px rgba(0,0,0,0.05);
                        overflow:hidden;
                        display:flex;
                        flex-direction:column;
                    ">


                        <!-- PAPER HEADER -->

                        <div style="
                            background:#1f4e79;
                            color:#ffffff;
                            padding:15px;
                            display:flex;
                            justify-content:space-between;
                            align-items:center;
                        ">

                            <div>

                                <span style="
                                    background:rgba(255,255,255,0.2);
                                    padding:3px 8px;
                                    border-radius:3px;
                                    font-weight:bold;
                                    font-size:12px;
                                    margin-right:8px;
                                ">

                                    <?php
                                    echo htmlspecialchars(
                                        $p['paper_code']
                                    );
                                    ?>

                                </span>


                                <strong style="font-size:15px;">

                                    <?php
                                    echo htmlspecialchars(
                                        $p['paper_name']
                                    );
                                    ?>

                                </strong>

                            </div>


                            <span style="
                                font-size:12px;
                                background:#fff;
                                color:#1f4e79;
                                font-weight:bold;
                                padding:2px 8px;
                                border-radius:10px;
                            ">

                                <?php
                                echo htmlspecialchars(
                                    $p['paper_type']
                                );
                                ?>

                            </span>

                        </div>


                        <!-- PAPER CONTENT -->

                        <div style="
                            padding:15px;
                            flex:1;
                        ">


                            <!-- PAPER MARKS -->

                            <div style="
                                font-size:12px;
                                color:#666;
                                margin-bottom:12px;
                            ">

                                Paper Max Marks:

                                Internal:
                                <strong>
                                    <?php
                                    echo number_format(
                                        (float)$p['max_internal'],
                                        2
                                    );
                                    ?>
                                </strong>

                                |

                                Mid-Term:
                                <strong>
                                    <?php
                                    echo number_format(
                                        (float)$p['max_midterm'],
                                        2
                                    );
                                    ?>
                                </strong>

                                |

                                Final:
                                <strong>
                                    <?php
                                    echo number_format(
                                        (float)$p['max_final'],
                                        2
                                    );
                                    ?>
                                </strong>

                            </div>


                            <?php if (empty($mappedList)): ?>


                                <div style="
                                    padding:15px;
                                    background:#f9f9f9;
                                    border:1px dashed #ccc;
                                    border-radius:4px;
                                    text-align:center;
                                    color:#888;
                                    font-size:13px;
                                 ">

                                    No subjects grouped under
                                    this paper yet.

                                </div>


                            <?php else: ?>


                                <table style="
                                    width:100%;
                                    border-collapse:collapse;
                                    font-size:13px;
                                ">

                                    <thead>

                                        <tr style="
                                            background:#f0f4f8;
                                            color:#333;
                                            text-align:left;
                                        ">

                                            <th style="padding:8px;">
                                                Code
                                            </th>

                                            <th style="padding:8px;">
                                                Subject Name
                                            </th>

                                            <th style="padding:8px; text-align:center;">
                                                Int Max
                                            </th>

                                            <th style="padding:8px; text-align:center;">
                                                Mid Max
                                            </th>

                                            <th style="padding:8px; text-align:center;">
                                                Fin Max
                                            </th>

                                            <th style="
                                                padding:8px;
                                                text-align:right;
                                            ">
                                                Action
                                            </th>

                                        </tr>

                                    </thead>


                                    <tbody>


                                        <?php foreach ($mappedList as $m): ?>


                                            <tr style="
                                                border-bottom:
                                                    1px solid #eee;
                                            ">


                                                <td style="
                                                    padding:8px;
                                                    font-weight:bold;
                                                    color:#1f4e79;
                                                ">

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $m['subject_code']
                                                    );
                                                    ?>

                                                </td>


                                                <td style="padding:8px;">

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $m['subject_name']
                                                    );
                                                    ?>

                                                </td>


                                                <td style="padding:8px; text-align:center;">

                                                    <?php
                                                    echo number_format(
                                                        (float)($m['subject_max_internal'] ?? 0),
                                                        2
                                                    );
                                                    ?>

                                                </td>


                                                <td style="padding:8px; text-align:center;">

                                                    <?php
                                                    echo number_format(
                                                        (float)($m['subject_max_midterm'] ?? 0),
                                                        2
                                                    );
                                                    ?>

                                                </td>


                                                <td style="padding:8px; text-align:center;">

                                                    <?php
                                                    echo number_format(
                                                        (float)($m['subject_max_final'] ?? 0),
                                                        2
                                                    );
                                                    ?>

                                                </td>


                                                <td style="
                                                    padding:8px;
                                                    text-align:right;
                                                ">


                                                    <form
                                                        method="POST"
                                                        action=""
                                                        style="display:inline;"
                                                        onsubmit="
                                                            return confirm(
                                                                'Remove subject from this paper?'
                                                            );
                                                        "
                                                    >

                                                        <input
                                                            type="hidden"
                                                            name="action"
                                                            value="remove_mapping"
                                                        >


                                                        <input
                                                            type="hidden"
                                                            name="paper_id"
                                                            value="<?php echo $pId; ?>"
                                                        >


                                                        <!-- FIXED: SUBJECT ID -->

                                                        <input
                                                            type="hidden"
                                                            name="subject_id"
                                                            value="<?php echo (int)$m['subject_id']; ?>"
                                                        >


                                                        <input
                                                            type="hidden"
                                                            name="batch_id"
                                                            value="<?php echo (int)$selectedBatchId; ?>"
                                                        >


                                                        <button
                                                            type="submit"
                                                            style="
                                                                background:none;
                                                                border:none;
                                                                color:#d32f2f;
                                                                cursor:pointer;
                                                                font-weight:bold;
                                                                font-size:12px;
                                                            "
                                                        >
                                                            Remove
                                                        </button>

                                                    </form>

                                                </td>

                                            </tr>


                                        <?php endforeach; ?>


                                    </tbody>

                                </table>


                            <?php endif; ?>


                        </div>

                    </div>


                <?php endforeach; ?>


            </div>


        </main>

    </div>

</div>


<?php require_once "../../includes/footer.php"; ?>