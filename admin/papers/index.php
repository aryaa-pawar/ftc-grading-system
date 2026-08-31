<?php

require_once __DIR__ . "/../../config/database.php";

$pageTitle = "Paper & Grouping Management";

$papers = [];
$errorMessage = "";
$successMessage = "";

if (isset($_GET['success'])) {
    $successMessage = "Operation completed successfully.";
}


/*
|--------------------------------------------------------------------------
| GET PAPERS
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->query("
        SELECT 
            p.paper_id,
            p.paper_code,
            p.paper_name,
            p.is_elective,
            p.paper_type,
            p.max_internal,
            p.max_midterm,
            p.max_final,
            COUNT(DISTINCT psm.subject_id) AS mapped_subjects_count
        FROM papers p
        LEFT JOIN paper_subject_mapping psm 
            ON p.paper_id = psm.paper_id
        GROUP BY p.paper_id
        ORDER BY p.paper_id ASC
    ");

    $papers = $stmt->fetchAll(PDO::FETCH_ASSOC);

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


            <!-- =================================================
                 PAGE HEADER
            ================================================== -->

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

                        Paper & Grouping Management

                    </h1>


                    <p style="
                        margin:0;
                        color:#666;
                    ">

                        Define theory/practical/project papers and group subjects into paper sections for evaluation.

                    </p>

                </div>


                <!-- ACTION BUTTONS -->

                <div style="
                    display:flex;
                    gap:10px;
                    align-items:center;
                ">


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

                        + Add New Paper

                    </a>


                    <a
                        href="grouping.php"
                        style="
                            display:inline-block;
                            background:#28a745;
                            color:#ffffff;
                            padding:12px 20px;
                            border-radius:6px;
                            text-decoration:none;
                            cursor:pointer;
                            font-weight:bold;
                        "
                    >

                        Manage Subject Groupings

                    </a>


                </div>


            </div>



            <!-- =================================================
                 ERROR MESSAGE
            ================================================== -->

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
                        Unable to load papers:
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
                 SUCCESS MESSAGE
            ================================================== -->

            <?php if ($successMessage !== ""): ?>

                <div style="
                    background:#d4edda;
                    color:#155724;
                    border:1px solid #c3e6cb;
                    padding:15px;
                    border-radius:6px;
                    margin-bottom:20px;
                ">

                    <?php

                    echo htmlspecialchars(
                        $successMessage
                    );

                    ?>

                </div>

            <?php endif; ?>



            <!-- =================================================
                 PAPERS TABLE
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
                    min-width:1050px;
                ">


                    <thead>

                        <tr>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">

                                Code

                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">

                                Paper Name

                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">

                                Type

                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">

                                Internal Max

                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">

                                Mid-Term Max

                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">

                                Final Exam Max

                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">

                                Total Max Marks

                            </th>


                            <th style="
                                padding:12px;
                                background:#1f4e79;
                                color:white;
                                text-align:left;
                            ">

                                Mapped Subjects

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


                    <?php if (count($papers) > 0): ?>


                        <?php foreach ($papers as $paper): ?>


                            <?php

                            $totalPaperMax =
                                (float)$paper["max_internal"]
                                +
                                (float)$paper["max_midterm"]
                                +
                                (float)$paper["max_final"];

                            ?>


                            <tr>


                                <!-- CODE -->

                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eeeeee;
                                    font-weight:bold;
                                    color:#1f4e79;
                                ">

                                    <?php

                                    echo htmlspecialchars(
                                        $paper["paper_code"]
                                    );

                                    ?>


                                    <?php if ($paper["is_elective"]): ?>

                                        <span style="
                                            display:inline-block;
                                            margin-left:6px;
                                            background:#fff3cd;
                                            color:#856404;
                                            padding:3px 7px;
                                            border-radius:4px;
                                            font-size:11px;
                                            font-weight:bold;
                                        ">

                                            Elective

                                        </span>

                                    <?php endif; ?>


                                </td>



                                <!-- PAPER NAME -->

                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eeeeee;
                                    font-weight:bold;
                                ">

                                    <?php

                                    echo htmlspecialchars(
                                        $paper["paper_name"]
                                    );

                                    ?>

                                </td>



                                <!-- TYPE -->

                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eeeeee;
                                ">

                                    <span style="
                                        display:inline-block;
                                        background:#e3f2fd;
                                        color:#0d47a1;
                                        padding:4px 9px;
                                        border-radius:4px;
                                        font-size:12px;
                                        font-weight:bold;
                                    ">

                                        <?php

                                        echo htmlspecialchars(
                                            $paper["paper_type"]
                                        );

                                        ?>

                                    </span>

                                </td>



                                <!-- INTERNAL MAX -->

                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eeeeee;
                                ">

                                    <?php

                                    echo number_format(
                                        $paper["max_internal"],
                                        2
                                    );

                                    ?>

                                </td>



                                <!-- MID TERM MAX -->

                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eeeeee;
                                ">

                                    <?php

                                    echo number_format(
                                        $paper["max_midterm"],
                                        2
                                    );

                                    ?>

                                </td>



                                <!-- FINAL EXAM MAX -->

                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eeeeee;
                                ">

                                    <?php

                                    echo number_format(
                                        $paper["max_final"],
                                        2
                                    );

                                    ?>

                                </td>



                                <!-- TOTAL MAX -->

                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eeeeee;
                                    font-weight:bold;
                                    color:#278a4b;
                                ">

                                    <?php

                                    echo number_format(
                                        $totalPaperMax,
                                        2
                                    );

                                    ?>

                                </td>



                                <!-- MAPPED SUBJECTS -->

                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eeeeee;
                                ">

                                    <span style="
                                        display:inline-block;
                                        background:#f0f4f8;
                                        color:#333;
                                        padding:5px 10px;
                                        border-radius:12px;
                                        font-size:12px;
                                        font-weight:bold;
                                    ">

                                        <?php

                                        echo (int)
                                            $paper[
                                                "mapped_subjects_count"
                                            ];

                                        ?>

                                        subjects

                                    </span>

                                </td>



                                <!-- ACTIONS -->

                                <td style="
                                    padding:12px;
                                    border-bottom:1px solid #eeeeee;
                                    white-space:nowrap;
                                ">


                                    <a
                                        href="edit.php?id=<?php echo (int)$paper["paper_id"]; ?>"
                                        style="
                                            color:#1f4e79;
                                            text-decoration:none;
                                            margin-right:15px;
                                            font-weight:bold;
                                        "
                                    >

                                        Edit

                                    </a>


                                    <a
                                        href="delete.php?id=<?php echo (int)$paper["paper_id"]; ?>"
                                        onclick="return confirm('Are you sure you want to delete this paper?');"
                                        style="
                                            color:#d32f2f;
                                            text-decoration:none;
                                            font-weight:bold;
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
                                colspan="9"
                                style="
                                    padding:35px;
                                    text-align:center;
                                    color:#666;
                                "
                            >

                                No papers defined. Click
                                "+ Add New Paper" to create one.

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