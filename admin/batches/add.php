<?php

require_once "../../config/database.php";

$pageTitle = "Add Batch";

$errorMessage = "";

$batchName = "";
$batchNumber = "";
$commencedDate = "";
$completedDate = "";
$station = "";
$directorName = "K. N. Mohan";
$directorNameHindi = "के.एन.मोहन";


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
    $status = $_POST["status"] ?? "Running";

    $directorName = trim($_POST["director_name"] ?? "") ?: "K. N. Mohan";
    $directorNameHindi = trim($_POST["director_name_hindi"] ?? "") ?: "के.एन.मोहन";


    $signaturePath = null;
    if (isset($_FILES['director_signature']) && $_FILES['director_signature']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../uploads/signatures/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $ext = strtolower(pathinfo($_FILES['director_signature']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($ext, $allowed, true)) {
            $filename = 'sig_' . time() . '_' . uniqid() . '.png';
            $destFile = $uploadDir . $filename;
            $tmpFile = $_FILES['director_signature']['tmp_name'];
            
            $processed = false;
            if (extension_loaded('gd')) {
                $info = @getimagesize($tmpFile);
                if ($info && isset($info['mime'])) {
                    $img = null;
                    switch ($info['mime']) {
                        case 'image/jpeg': $img = @imagecreatefromjpeg($tmpFile); break;
                        case 'image/png': $img = @imagecreatefrompng($tmpFile); break;
                        case 'image/webp': $img = @imagecreatefromwebp($tmpFile); break;
                    }
                    if ($img) {
                        if (function_exists('imagefilter')) {
                            imagefilter($img, IMG_FILTER_CONTRAST, -35);
                            imagefilter($img, IMG_FILTER_BRIGHTNESS, 25);
                        }
                        $processed = @imagepng($img, $destFile, 6);
                        imagedestroy($img);
                    }
                }
            }
            if (!$processed) {
                $filename = 'sig_' . time() . '_' . uniqid() . '.' . $ext;
                $destFile = $uploadDir . $filename;
                move_uploaded_file($tmpFile, $destFile);
            }
            $signaturePath = 'uploads/signatures/' . $filename;
        }
    }


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
                    station,
                    status,
                    director_name,
                    director_name_hindi,
                    director_signature_path
                )
                VALUES
                (
                    :batch_number,
                    :batch_name,
                    :commenced_date,
                    :completed_date,
                    :station,
                    :status,
                    :director_name,
                    :director_name_hindi,
                    :director_signature_path
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
                    $station,
                ":status" => $status,
                ":director_name" => $directorName,
                ":director_name_hindi" => $directorNameHindi,
                ":director_signature_path" => $signaturePath

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
                    enctype="multipart/form-data"
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


                    <div style="margin-bottom:25px;">
                        <label for="status" style="display:block;font-weight:bold;margin-bottom:8px;">Status</label>
                        <select id="status" name="status" required style="width:100%;padding:11px;border:1px solid #cccccc;border-radius:5px;">
                            <option value="Running" <?= $status === 'Running' ? 'selected' : '' ?>>Running</option>
                            <option value="Result Declared" <?= $status === 'Result Declared' ? 'selected' : '' ?>>Result Declared</option>
                            <option value="Completed" <?= $status === 'Completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </div>

                    <!-- ASSIGNED DIRECTOR FOR BATCH -->
                    <div style="margin-bottom:20px; padding:16px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:6px;">
                        <h4 style="margin:0 0 12px 0; color:#1f4e79;">Assigned Director for Batch</h4>
                        
                        <div style="margin-bottom:15px;">
                            <label for="director_name" style="display:block;font-weight:bold;margin-bottom:6px;">Director Name (English) *</label>
                            <div style="display:flex; gap:10px;">
                                <input type="text" id="director_name" name="director_name" value="<?= htmlspecialchars($directorName) ?>" required placeholder="e.g. K. N. Mohan" style="flex:1; padding:10px; border:1px solid #ccc; border-radius:5px;">
                                <button type="button" id="btn_convert_dir" onclick="convertDirectorNameToHindi()" style="background:#1f4e79; color:#fff; border:none; padding:10px 16px; border-radius:5px; font-weight:bold; cursor:pointer;">Convert</button>
                            </div>
                        </div>

                        <div style="margin-top:12px;">
                            <label for="director_name_hindi" style="display:block;font-weight:bold;margin-bottom:6px;">Director Name (Hindi / Devanagari) *</label>
                            <select id="director_suggestions" onchange="document.getElementById('director_name_hindi').value = this.value" style="display:none; width:100%; padding:8px; margin-bottom:8px; border:1px solid #ccc; border-radius:5px;">
                                <option value="">Choose spelling</option>
                            </select>
                            <input type="text" id="director_name_hindi" name="director_name_hindi" value="<?= htmlspecialchars($directorNameHindi) ?>" required placeholder="e.g. के.एन.मोहन" style="width:100%; padding:10px; border:1px solid #ccc; border-radius:5px;">
                        </div>

                        <div style="margin-top:15px;">
                            <label for="director_signature" style="display:block;font-weight:bold;margin-bottom:6px;">Director Digital Signature Photo (Optional)</label>
                            <input type="file" id="director_signature" name="director_signature" accept="image/*" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:5px; background:#fff;">
                            <small style="color:#64748b;">Allowed formats: PNG, JPG, JPEG, WEBP. Will be placed above Director Name on certificates and result sheets.</small>
                        </div>
                    </div>

                    <script>
                    async function convertDirectorNameToHindi() {
                        var engInput = document.getElementById('director_name');
                        var hinInput = document.getElementById('director_name_hindi');
                        var sugSelect = document.getElementById('director_suggestions');
                        var btn = document.getElementById('btn_convert_dir');
                        var engName = engInput ? engInput.value.trim() : '';

                        if (!engName || !hinInput || !btn) return;
                        btn.disabled = true;
                        btn.textContent = 'Converting...';

                        try {
                            var endpoint = 'https://inputtools.google.com/request?text=' + encodeURIComponent(engName) + '&itc=hi-t-i0-und&num=5&ie=utf-8&oe=utf-8';
                            var res = await fetch(endpoint);
                            var data = await res.json();
                            var suggestions = data[1] && data[1][0] && data[1][0][1] ? data[1][0][1] : [];
                            if (suggestions.length > 0) {
                                hinInput.value = suggestions[0];
                                if (sugSelect) {
                                    sugSelect.innerHTML = '<option value="">Choose spelling</option>';
                                    suggestions.forEach(function(s) {
                                        var opt = document.createElement('option');
                                        opt.value = s;
                                        opt.textContent = s;
                                        sugSelect.appendChild(opt);
                                    });
                                    sugSelect.style.display = suggestions.length > 1 ? 'block' : 'none';
                                }
                            }
                        } catch(e) {} finally {
                            btn.disabled = false;
                            btn.textContent = 'Convert';
                        }
                    }
                    </script>

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
