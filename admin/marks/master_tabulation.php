<?php

/*
|--------------------------------------------------------------------------
| FTC GRADING & TABULATION MANAGEMENT SYSTEM
| MASTER TABULATION SHEET
|--------------------------------------------------------------------------
|
| Samarth-style master tabulation.
|
| IMPORTANT:
| - This page is READ ONLY.
| - Existing calculation views are used.
| - No marks are recalculated differently in PHP.
| - Paper/subject mapping is dynamic.
| - Existing results/tabulation.php is NOT changed.
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once "../../config/database.php";

$pageTitle = "Master Tabulation";

$errorMessage = "";

$batches = [];
$papers = [];
$students = [];
$tabulation = [];
$results = [];

$selectedBatchId = isset($_GET["batch_id"])
    ? (int)$_GET["batch_id"]
    : 0;


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function h($value)
{
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}


function markValue($value)
{
    if ($value === null || $value === "") {
        return "—";
    }

    return number_format((float)$value, 1);
}


function dateIndian($value)
{
    if (!$value) {
        return "";
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date("d.m.Y", $timestamp);
}


/*
|--------------------------------------------------------------------------
| LOAD BATCHES
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT
            batch_id,
            batch_name,
            commenced_date,
            completed_date,
            station
        FROM batches
        ORDER BY batch_id DESC
    ");

    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    $errorMessage = "Unable to load batches.";

}


/*
|--------------------------------------------------------------------------
| DEFAULT BATCH
|--------------------------------------------------------------------------
*/

if (
    $selectedBatchId <= 0 &&
    !empty($batches)
) {

    $selectedBatchId =
        (int)$batches[0]["batch_id"];

}


/*
|--------------------------------------------------------------------------
| CURRENT BATCH
|--------------------------------------------------------------------------
*/

$currentBatch = null;

foreach ($batches as $batch) {

    if (
        (int)$batch["batch_id"]
        ===
        $selectedBatchId
    ) {

        $currentBatch = $batch;

        break;

    }

}


/*
|--------------------------------------------------------------------------
| LOAD PAPERS MAPPED TO THIS BATCH
|--------------------------------------------------------------------------
|
| This is important:
|
| We DO NOT hard-code paper IDs.
|
| The Samarth reference has fixed columns, but FTC uses
| paper_subject_mapping so our columns are generated from
| the actual selected batch.
|--------------------------------------------------------------------------
*/

if ($selectedBatchId > 0) {

    try {

        $stmt = $pdo->prepare("
            SELECT DISTINCT

                p.paper_id,
                p.paper_code,
                p.paper_name,
                p.paper_type,

                p.max_internal,
                p.max_midterm,
                p.max_final

            FROM papers p

            INNER JOIN paper_subject_mapping psm
                ON psm.paper_id = p.paper_id

            WHERE psm.batch_id = :batch_id

            ORDER BY p.paper_id ASC
        ");

        $stmt->execute([
            ":batch_id" => $selectedBatchId
        ]);

        $papers =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        $errorMessage =
            "Unable to load mapped papers.";

    }

}


/*
|--------------------------------------------------------------------------
| LOAD STUDENTS
|--------------------------------------------------------------------------
*/

if ($selectedBatchId > 0) {

    try {

        $stmt = $pdo->prepare("
            SELECT

                student_id,
                roll_no,
                student_name,
                designation,
                qualification,
                rmc,
                group_code

            FROM students

            WHERE batch_id = :batch_id

              AND is_deleted = 0

            ORDER BY
                roll_no ASC,
                student_name ASC
        ");

        $stmt->execute([
            ":batch_id" => $selectedBatchId
        ]);

        $students =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        $errorMessage =
            "Unable to load students.";

    }

}


/*
|--------------------------------------------------------------------------
| LOAD TABULATION VALUES
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| v_tabulation already performs:
|
| Internal + Mid-Term + Final
|
| and respects the existing database calculation pipeline.
|
| Therefore this PHP page only DISPLAYS those values.
|--------------------------------------------------------------------------
*/

if (
    $selectedBatchId > 0 &&
    !empty($students) &&
    !empty($papers)
) {

    try {

        $stmt = $pdo->prepare("
            SELECT

                vt.student_id,
                vt.paper_id,

                vt.internal_score,
                vt.midterm_score,
                vt.final_exam_score,
                vt.combined_total

            FROM v_tabulation vt

            INNER JOIN students s
                ON s.student_id = vt.student_id

            INNER JOIN paper_subject_mapping psm
                ON psm.paper_id = vt.paper_id
               AND psm.batch_id = s.batch_id

            WHERE s.batch_id = :batch_id

              AND s.is_deleted = 0

            ORDER BY
                vt.student_id ASC,
                vt.paper_id ASC
        ");

        $stmt->execute([
            ":batch_id" => $selectedBatchId
        ]);

        $rows =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        foreach ($rows as $row) {

            $studentId =
                (int)$row["student_id"];

            $paperId =
                (int)$row["paper_id"];


            if (
                !isset(
                    $tabulation[$studentId]
                )
            ) {

                $tabulation[$studentId] = [];

            }


            $tabulation[$studentId][$paperId] = [

                "internal" =>
                    $row["internal_score"],

                "midterm" =>
                    $row["midterm_score"],

                "final" =>
                    $row["final_exam_score"],

                "total" =>
                    $row["combined_total"]

            ];

        }

    } catch (PDOException $e) {

        $errorMessage =
            "Unable to load tabulation.";

    }

}


/*
|--------------------------------------------------------------------------
| LOAD RESULT REGISTER
|--------------------------------------------------------------------------
|
| We use the existing result calculation.
|
| NO percentage calculation is duplicated here.
|--------------------------------------------------------------------------
*/

if (
    $selectedBatchId > 0 &&
    !empty($students)
) {

    try {

        $stmt = $pdo->prepare("
            SELECT

                rr.student_id,
                rr.grand_total,
                rr.max_possible_marks,
                rr.percentage,
                rr.remarks,
                rr.custom_remarks

            FROM v_result_register rr

            INNER JOIN students s
                ON s.student_id = rr.student_id

            WHERE s.batch_id = :batch_id

              AND s.is_deleted = 0

            ORDER BY
                s.roll_no ASC
        ");

        $stmt->execute([
            ":batch_id" => $selectedBatchId
        ]);

        $resultRows =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        foreach ($resultRows as $row) {

            $results[
                (int)$row["student_id"]
            ] = $row;

        }

    } catch (PDOException $e) {

        $errorMessage =
            "Unable to load result calculations.";

    }

}


require_once "../../includes/header.php";

?>

<style>

/*
|--------------------------------------------------------------------------
| PAGE
|--------------------------------------------------------------------------
*/

.master-page {

    background:#f4f6f8;

    min-height:100vh;

    padding:22px;

}


/*
|--------------------------------------------------------------------------
| HEADER
|--------------------------------------------------------------------------
*/

.master-title {

    margin-bottom:18px;

}

.master-title h1 {

    margin:0;

    color:#1f4e79;

    font-size:29px;

}

.master-title p {

    margin:5px 0 0;

    color:#666;

}


/*
|--------------------------------------------------------------------------
| FILTER
|--------------------------------------------------------------------------
*/

.filter-card {

    background:#ffffff;

    border:1px solid #d8d8d8;

    border-radius:6px;

    padding:16px;

    margin-bottom:15px;

}

.filter-row {

    display:flex;

    flex-wrap:wrap;

    align-items:end;

    gap:12px;

}

.filter-field {

    display:flex;

    flex-direction:column;

    gap:5px;

}

.filter-field label {

    font-size:13px;

    font-weight:bold;

    color:#333;

}

.filter-field select,

.info-field {

    height:38px;

    min-width:180px;

    box-sizing:border-box;

    border:1px solid #aaa;

    border-radius:4px;

    padding:0 9px;

    background:#fff;

    font-size:13px;

}

.info-field {

    display:flex;

    align-items:center;

    background:#fafafa;

}


/*
|--------------------------------------------------------------------------
| BUTTON
|--------------------------------------------------------------------------
*/

.btn {

    height:38px;

    padding:0 16px;

    border:0;

    border-radius:4px;

    font-weight:bold;

    cursor:pointer;

}

.btn-view {

    background:#1f4e79;

    color:#fff;

}

.btn-print {

    background:#777;

    color:#fff;

}


/*
|--------------------------------------------------------------------------
| CHART TITLE
|--------------------------------------------------------------------------
*/

.chart-title {

    background:#ffffff;

    border:1px solid #555;

    padding:10px 14px;

    font-size:15px;

    font-weight:bold;

    color:#111;

}


/*
|--------------------------------------------------------------------------
| TABLE WRAPPER
|--------------------------------------------------------------------------
*/

.table-wrapper {

    width:100%;

    overflow-x:auto;

    background:#ffffff;

    border-left:1px solid #555;

    border-right:1px solid #555;

    border-bottom:1px solid #555;

}


/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.master-table {

    border-collapse:collapse;

    width:max-content;

    min-width:100%;

    font-family:Arial, Helvetica, sans-serif;

    font-size:10px;

}

.master-table th,
.master-table td {

    border:1px solid #555;

    padding:4px 5px;

    text-align:center;

    vertical-align:middle;

    white-space:nowrap;

}

.master-table thead th {

    background:#ffffff;

    color:#111;

    font-weight:bold;

}


/*
|--------------------------------------------------------------------------
| STUDENT INFORMATION
|--------------------------------------------------------------------------
*/

.sno {

    min-width:35px;

}

.student-name {

    min-width:155px;

    text-align:left !important;

}

.designation {

    min-width:75px;

}

.qualification {

    min-width:130px;

    text-align:left !important;

}

.roll {

    min-width:170px;

    text-align:left !important;

}

.final-exam {

    min-width:55px;

}


/*
|--------------------------------------------------------------------------
| PAPER GROUP
|--------------------------------------------------------------------------
*/

.paper-group {

    min-width:220px;

    height:45px;

    white-space:normal !important;

    line-height:1.2;

}

.paper-code {

    height:22px;

    font-size:9px;

}

.assessment {

    min-width:55px;

    height:25px;

    font-size:9px;

}

.maximum {

    min-width:55px;

    height:23px;

    font-size:9px;

    background:#fafafa !important;

}


/*
|--------------------------------------------------------------------------
| MARKS
|--------------------------------------------------------------------------
*/

.mark {

    min-width:55px;

}

.total {

    min-width:60px;

    font-weight:bold;

}


/*
|--------------------------------------------------------------------------
| TOTAL / RESULT
|--------------------------------------------------------------------------
*/

.grand {

    min-width:65px;

    font-weight:bold;

    background:#eef5fa !important;

}

.max-total {

    min-width:65px;

    font-weight:bold;

}

.percent {

    min-width:60px;

    font-weight:bold;

}

.result {

    min-width:75px;

    font-weight:bold;

}

.pass {

    background:#d9f2df !important;

    color:#146c2e;

}

.fail {

    background:#f8d7da !important;

    color:#a12622;

}

.pending {

    color:#777;

}


/*
|--------------------------------------------------------------------------
| REMARK
|--------------------------------------------------------------------------
*/

.remark {

    min-width:120px;

    white-space:normal !important;

}


/*
|--------------------------------------------------------------------------
| EMPTY / ERROR
|--------------------------------------------------------------------------
*/

.error {

    background:#fdecea;

    color:#a12622;

    border:1px solid #f5c6cb;

    padding:12px;

    border-radius:5px;

    margin-bottom:15px;

}

.empty {

    background:#fff;

    padding:50px;

    text-align:center;

    color:#666;

    border:1px solid #ddd;

}


/*
|--------------------------------------------------------------------------
| PRINT
|--------------------------------------------------------------------------
*/

@media print {

    .no-print {

        display:none !important;

    }

    .master-page {

        padding:0;

        background:#fff;

    }

    .table-wrapper {

        overflow:visible;

        border:0;

    }

    .master-table {

        font-size:7px;

    }

    .master-table th,
    .master-table td {

        padding:2px;

    }

}

</style>


<div style="
    display:flex;
    flex-direction:column;
    min-height:100vh;
">


    <?php require_once "../../includes/navbar.php"; ?>


    <div style="
        display:flex;
        flex:1;
        min-height:0;
    ">


        <?php require_once "../../includes/sidebar.php"; ?>


        <main style="
            flex:1;
            min-width:0;
        ">


            <div class="master-page">


                <!-- =================================================
                     TITLE
                ================================================== -->

                <div class="master-title">

                    <h1>
                        Marks Management
                        <span style="
                            font-size:18px;
                            font-weight:400;
                            color:#777;
                        ">
                            / Master Tabulation
                        </span>
                    </h1>

                    <p>
                        Master academic marks tabulation sheet.
                    </p>

                </div>


                <!-- =================================================
                     ERROR
                ================================================== -->

                <?php if ($errorMessage !== ""): ?>

                    <div class="error">
                        <?php echo h($errorMessage); ?>
                    </div>

                <?php endif; ?>


                <!-- =================================================
                     FILTER
                ================================================== -->

                <div class="filter-card no-print">

                    <form
                        method="GET"
                        action="master_tabulation.php"
                    >

                        <div class="filter-row">


                            <div class="filter-field">

                                <label>
                                    Batch No -
                                </label>

                                <select
                                    name="batch_id"
                                    onchange="
                                        this.form.submit()
                                    "
                                >

                                    <?php foreach (
                                        $batches
                                        as $batch
                                    ): ?>

                                        <option
                                            value="<?php
                                                echo (int)
                                                $batch[
                                                    "batch_id"
                                                ];
                                            ?>"
                                            <?php

                                            echo (
                                                (int)
                                                $batch[
                                                    "batch_id"
                                                ]
                                                ===
                                                $selectedBatchId
                                            )
                                            ? "selected"
                                            : "";

                                            ?>
                                        >

                                            <?php echo h(
                                                $batch[
                                                    "batch_name"
                                                ]
                                            ); ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="filter-field">

                                <label>
                                    Commenced on:
                                </label>

                                <div class="info-field">

                                    <?php echo h(
                                        dateIndian(
                                            $currentBatch[
                                                "commenced_date"
                                            ]
                                            ??
                                            ""
                                        )
                                    ); ?>

                                </div>

                            </div>


                            <div class="filter-field">

                                <label>
                                    Completed on:
                                </label>

                                <div class="info-field">

                                    <?php echo h(
                                        dateIndian(
                                            $currentBatch[
                                                "completed_date"
                                            ]
                                            ??
                                            ""
                                        )
                                    ); ?>

                                </div>

                            </div>


                            <div class="filter-field">

                                <label>
                                    Station:
                                </label>

                                <div class="info-field">

                                    <?php echo h(
                                        $currentBatch[
                                            "station"
                                        ]
                                        ??
                                        ""
                                    ); ?>

                                </div>

                            </div>


                            <button
                                type="submit"
                                class="btn btn-view"
                            >
                                View
                            </button>


                            <button
                                type="button"
                                class="btn btn-print"
                                onclick="
                                    window.print()
                                "
                            >
                                Print
                            </button>


                        </div>

                    </form>

                </div>


                <!-- =================================================
                     CHART TITLE
                ================================================== -->

                <?php if ($currentBatch): ?>

                    <div class="chart-title">

                        FTC CLASS INTERNAL / MID-TERM / FINAL
                        MASTER TABULATION

                        &nbsp; | &nbsp;

                        Batch No:
                        <?php echo h(
                            $currentBatch["batch_name"]
                        ); ?>

                        &nbsp; | &nbsp;

                        Station:
                        <?php echo h(
                            $currentBatch["station"]
                        ); ?>

                    </div>

                <?php endif; ?>


                <?php if (!$currentBatch): ?>

                    <div class="empty">

                        <h2>
                            Select a batch
                        </h2>

                        <p>
                            Select a batch to display the
                            Master Tabulation Chart.
                        </p>

                    </div>


                <?php elseif (empty($papers)): ?>

                    <div class="empty">

                        <h2>
                            No mapped papers
                        </h2>

                        <p>
                            Map papers and subjects to this
                            batch first.
                        </p>

                    </div>


                <?php elseif (empty($students)): ?>

                    <div class="empty">

                        <h2>
                            No students
                        </h2>

                        <p>
                            No active students are assigned
                            to this batch.
                        </p>

                    </div>


                <?php else: ?>


                    <!-- =================================================
                         MASTER CHART
                    ================================================== -->

                    <div class="table-wrapper">

                        <table class="master-table">


                            <thead>


                                <!-- =====================================
                                     ROW 1 - PAPER NAMES
                                ====================================== -->

                                <tr>

                                    <th rowspan="4" class="sno">
                                        S.<br>No.
                                    </th>

                                    <th rowspan="4" class="student-name">
                                        Name
                                    </th>

                                    <th rowspan="4" class="designation">
                                        DSG.
                                    </th>

                                    <th rowspan="4" class="qualification">
                                        Qualification
                                    </th>

                                    <th rowspan="4" class="roll">
                                        Roll Nos.
                                    </th>

                                    <th rowspan="4" class="final-exam">
                                        Final<br>Exam<br>Marks
                                    </th>


                                    <?php foreach (
                                        $papers
                                        as $paper
                                    ): ?>

                                        <th
                                            colspan="4"
                                            class="paper-group"
                                        >

                                            <?php echo h(
                                                $paper[
                                                    "paper_name"
                                                ]
                                            ); ?>

                                        </th>

                                    <?php endforeach; ?>


                                    <th rowspan="4" class="grand">
                                        Grand<br>Total
                                    </th>

                                    <th rowspan="4" class="max-total">
                                        Max<br>Marks
                                    </th>

                                    <th rowspan="4" class="percent">
                                        %
                                    </th>

                                    <th rowspan="4" class="result">
                                        Result
                                    </th>

                                    <th rowspan="4" class="remark">
                                        Remarks
                                    </th>

                                </tr>


                                <!-- =====================================
                                     ROW 2 - PAPER CODE
                                ====================================== -->

                                <tr>

                                    <?php foreach (
                                        $papers
                                        as $paper
                                    ): ?>

                                        <th
                                            colspan="4"
                                            class="paper-code"
                                        >

                                            <?php echo h(
                                                $paper[
                                                    "paper_code"
                                                ]
                                            ); ?>

                                        </th>

                                    <?php endforeach; ?>

                                </tr>


                                <!-- =====================================
                                     ROW 3 - ASSESSMENTS
                                ====================================== -->

                                <tr>

                                    <?php foreach (
                                        $papers
                                        as $paper
                                    ): ?>

                                        <th class="assessment">
                                            Internal
                                        </th>

                                        <th class="assessment">
                                            Mid-Term
                                        </th>

                                        <th class="assessment">
                                            Final
                                        </th>

                                        <th class="assessment">
                                            Total
                                        </th>

                                    <?php endforeach; ?>

                                </tr>


                                <!-- =====================================
                                     ROW 4 - MAXIMUM MARKS
                                ====================================== -->

                                <tr>

                                    <?php foreach (
                                        $papers
                                        as $paper
                                    ): ?>

                                        <th class="maximum">

                                            <?php echo h(
                                                number_format(
                                                    (float)
                                                    $paper[
                                                        "max_internal"
                                                    ],
                                                    0
                                                )
                                            ); ?>

                                        </th>

                                        <th class="maximum">

                                            <?php echo h(
                                                number_format(
                                                    (float)
                                                    $paper[
                                                        "max_midterm"
                                                    ],
                                                    0
                                                )
                                            ); ?>

                                        </th>

                                        <th class="maximum">

                                            <?php echo h(
                                                number_format(
                                                    (float)
                                                    $paper[
                                                        "max_final"
                                                    ],
                                                    0
                                                )
                                            ); ?>

                                        </th>

                                        <th class="maximum">

                                            <?php

                                            $paperMax =

                                                (float)
                                                $paper[
                                                    "max_internal"
                                                ]

                                                +

                                                (float)
                                                $paper[
                                                    "max_midterm"
                                                ]

                                                +

                                                (float)
                                                $paper[
                                                    "max_final"
                                                ];

                                            echo h(
                                                number_format(
                                                    $paperMax,
                                                    0
                                                )
                                            );

                                            ?>

                                        </th>

                                    <?php endforeach; ?>

                                </tr>

                            </thead>


                            <tbody>


                            <?php

                            $serial = 1;

                            ?>


                            <?php foreach (
                                $students
                                as $student
                            ): ?>


                                <?php

                                $studentId =
                                    (int)
                                    $student[
                                        "student_id"
                                    ];


                                $studentMarks =
                                    $tabulation[
                                        $studentId
                                    ]
                                    ??
                                    [];


                                $result =
                                    $results[
                                        $studentId
                                    ]
                                    ??
                                    null;

                                ?>


                                <tr>


                                    <td>
                                        <?php echo $serial; ?>
                                    </td>


                                    <td class="student-name">
                                        <?php echo h(
                                            $student[
                                                "student_name"
                                            ]
                                        ); ?>
                                    </td>


                                    <td>
                                        <?php echo h(
                                            $student[
                                                "designation"
                                            ]
                                        ); ?>
                                    </td>


                                    <td class="qualification">
                                        <?php echo h(
                                            $student[
                                                "qualification"
                                            ]
                                        ); ?>
                                    </td>


                                    <td class="roll">
                                        <?php echo h(
                                            $student[
                                                "roll_no"
                                            ]
                                        ); ?>
                                    </td>


                                    <td class="final-exam">
                                        Final
                                    </td>


                                    <!-- =================================
                                         PAPERS
                                    ================================== -->

                                    <?php foreach (
                                        $papers
                                        as $paper
                                    ): ?>


                                        <?php

                                        $paperId =
                                            (int)
                                            $paper[
                                                "paper_id"
                                            ];


                                        $marks =
                                            $studentMarks[
                                                $paperId
                                            ]
                                            ??
                                            [];


                                        $internal =
                                            $marks[
                                                "internal"
                                            ]
                                            ??
                                            null;


                                        $midterm =
                                            $marks[
                                                "midterm"
                                            ]
                                            ??
                                            null;


                                        $final =
                                            $marks[
                                                "final"
                                            ]
                                            ??
                                            null;


                                        $total =
                                            $marks[
                                                "total"
                                            ]
                                            ??
                                            null;

                                        ?>


                                        <td class="mark">

                                            <?php echo markValue(
                                                $internal
                                            ); ?>

                                        </td>


                                        <td class="mark">

                                            <?php echo markValue(
                                                $midterm
                                            ); ?>

                                        </td>


                                        <td class="mark">

                                            <?php echo markValue(
                                                $final
                                            ); ?>

                                        </td>


                                        <td class="total">

                                            <?php echo markValue(
                                                $total
                                            ); ?>

                                        </td>


                                    <?php endforeach; ?>


                                    <!-- =================================
                                         RESULT VALUES
                                    ================================== -->


                                    <td class="grand">

                                        <?php

                                        if (
                                            $result
                                            &&
                                            $result[
                                                "grand_total"
                                            ] !== null
                                        ) {

                                            echo h(
                                                number_format(
                                                    (float)
                                                    $result[
                                                        "grand_total"
                                                    ],
                                                    1
                                                )
                                            );

                                        } else {

                                            echo "—";

                                        }

                                        ?>

                                    </td>


                                    <td class="max-total">

                                        <?php

                                        if (
                                            $result
                                            &&
                                            $result[
                                                "max_possible_marks"
                                            ] !== null
                                        ) {

                                            echo h(
                                                number_format(
                                                    (float)
                                                    $result[
                                                        "max_possible_marks"
                                                    ],
                                                    1
                                                )
                                            );

                                        } else {

                                            echo "—";

                                        }

                                        ?>

                                    </td>


                                    <td class="percent">

                                        <?php

                                        if (
                                            $result
                                            &&
                                            $result[
                                                "percentage"
                                            ] !== null
                                        ) {

                                            echo h(
                                                number_format(
                                                    (float)
                                                    $result[
                                                        "percentage"
                                                    ],
                                                    2
                                                )
                                            );

                                            echo "%";

                                        } else {

                                            echo "—";

                                        }

                                        ?>

                                    </td>


                                    <td class="result">

                                        <?php

                                        if (
                                            $result
                                            &&
                                            $result[
                                                "percentage"
                                            ] !== null
                                        ) {

                                            $percentage =
                                                (float)
                                                $result[
                                                    "percentage"
                                                ];


                                            if (
                                                $percentage >= 50
                                            ) {

                                                echo '<span class="pass">PASS</span>';

                                            } else {

                                                echo '<span class="fail">FAIL</span>';

                                            }

                                        } else {

                                            echo '<span class="pending">Pending</span>';

                                        }

                                        ?>

                                    </td>


                                    <td class="remark">

                                        <?php

                                        if (
                                            $result
                                            &&
                                            !empty(
                                                $result[
                                                    "custom_remarks"
                                                ]
                                            )
                                        ) {

                                            echo h(
                                                $result[
                                                    "custom_remarks"
                                                ]
                                            );

                                        } elseif (
                                            $result
                                            &&
                                            !empty(
                                                $result[
                                                    "remarks"
                                                ]
                                            )
                                        ) {

                                            echo h(
                                                $result[
                                                    "remarks"
                                                ]
                                            );

                                        } else {

                                            echo "—";

                                        }

                                        ?>

                                    </td>


                                </tr>


                                <?php

                                $serial++;

                                ?>


                            <?php endforeach; ?>


                            </tbody>


                        </table>

                    </div>


                <?php endif; ?>


            </div>

        </main>

    </div>


    <?php require_once "../../includes/footer.php"; ?>


</div>