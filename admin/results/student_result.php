<?php

/*
|--------------------------------------------------------------------------
| FTC GRADING & TABULATION MANAGEMENT SYSTEM
| OFFICIAL STUDENT RESULT CERTIFICATE (PRINT VIEW)
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../../includes/auth.php";
require_permission('results.view');
require_once __DIR__ . "/../../config/database.php";

if (!function_exists('h')) {
    function h($value)
    {
        return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
    }
}

if (!function_exists('formatDateIndian')) {
    function formatDateIndian($date)
    {
        if (empty($date)) {
            return "";
        }
        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return $date;
        }
        return date("d.m.Y", $timestamp);
    }
}

if (!function_exists('getPerformanceGrade')) {
    function getPerformanceGrade($percentage)
    {
        if ($percentage === null) {
            return "";
        }
        $pct = (float)$percentage;
        if ($pct >= 90) return "Outstanding";
        if ($pct >= 80) return "Excellent";
        if ($pct >= 70) return "Very good";
        if ($pct >= 60) return "Good";
        if ($pct >= 50) return "Passed";
        return "Failed";
    }
}

$studentId = isset($_GET["student_id"]) ? (int)$_GET["student_id"] : 0;
$directorName = isset($_GET["director_name"]) && trim($_GET["director_name"]) !== ""
    ? trim($_GET["director_name"])
    : null;
$directorNameHindi = isset($_GET["director_name_hindi"]) && trim($_GET["director_name_hindi"]) !== ""
    ? trim($_GET["director_name_hindi"])
    : null;

if (empty($directorName) || empty($directorNameHindi)) {
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('director_name', 'director_name_hindi')");
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $setting) {
            if (empty($directorName) && $setting['setting_key'] === 'director_name') {
                $directorName = trim($setting['setting_value'] ?? '');
            } elseif (empty($directorNameHindi) && $setting['setting_key'] === 'director_name_hindi') {
                $directorNameHindi = trim($setting['setting_value'] ?? '');
            }
        }
        $directorName = $directorName ?: "K. N. Mohan";
        $directorNameHindi = $directorNameHindi ?: $directorName;
    } catch (PDOException $e) {
        $directorName = "K. N. Mohan";
    }
}

$student = null;
$tabulationRows = [];
$resultRegister = null;
$errorMessage = "";

if ($studentId <= 0) {
    $errorMessage = "Invalid student ID.";
} else {
    try {
        // Load student info
        $stmt = $pdo->prepare("
            SELECT
                s.student_id,
                s.batch_id,
                s.roll_no,
                s.student_name,
                s.student_name_hindi,
                s.name_prefix,
                s.designation,
                s.qualification,
                s.rmc,
                s.group_code,
                s.custom_remarks,
                b.batch_name,
                b.commenced_date,
                b.completed_date,
                b.station
            FROM students s
            LEFT JOIN batches b ON b.batch_id = s.batch_id
            WHERE s.student_id = :student_id
              AND (s.is_deleted = 0 OR s.is_deleted IS NULL)
            LIMIT 1
        ");
        $stmt->execute([":student_id" => $studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            $errorMessage = "Student not found.";
        } else {
            // Load paper scores from v_tabulation
            $stmt = $pdo->prepare("
                SELECT
                    vt.paper_id,
                    vt.combined_total,
                    p.paper_code,
                    p.paper_name,
                    p.paper_type,
                    p.is_elective,
                    p.max_internal,
                    p.max_midterm,
                    p.max_final
                FROM v_tabulation vt
                INNER JOIN papers p ON p.paper_id = vt.paper_id
                WHERE vt.student_id = :student_id
                ORDER BY vt.paper_id ASC
            ");
            $stmt->execute([":student_id" => $studentId]);
            $tabulationRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Load overall result register summary
            $stmt = $pdo->prepare("
                SELECT
                    grand_total,
                    max_possible_marks,
                    percentage,
                    remarks,
                    custom_remarks
                FROM v_result_register
                WHERE student_id = :student_id
                LIMIT 1
            ");
            $stmt->execute([":student_id" => $studentId]);
            $resultRegister = $stmt->fetch(PDO::FETCH_ASSOC);
        }

    } catch (PDOException $e) {
        $errorMessage = "Unable to load student result: " . $e->getMessage();
    }
}

// Default standard papers mapping definition (bilingual Hindi + English as shown in official IMD certificate)
$standardPapersMap = [
    1 => [
        'code' => 'P-1',
        'no' => 'I',
        'hindi' => 'गति मौसम विज्ञान + संख्यात्मक मौसम पूर्वानुमान',
        'english' => 'Dynamic Meteorology & NWP',
        'max' => 100,
        'pass' => 50
    ],
    2 => [
        'code' => 'P-2',
        'no' => 'II',
        'hindi' => 'भौतिक मौसम विज्ञान तथा सामुद्रीय विज्ञान',
        'english' => 'Physical Meteorology',
        'max' => 100,
        'pass' => 50
    ],
    3 => [
        'code' => 'P-3',
        'no' => 'III',
        'hindi' => 'सिनोप्टिक मौसम विज्ञान तथा विमानन मौसम विज्ञान',
        'english' => 'Synoptic Meteorology and Aviation Meteorology',
        'max' => 100,
        'pass' => 50
    ],
    4 => [
        'code' => 'P-4',
        'no' => 'IV',
        'hindi' => 'जलवायु विज्ञान, जलमौसम विज्ञान तथा सांख्यिकी',
        'english' => 'Climate Science & Hydrometeorology',
        'max' => 100,
        'pass' => 50
    ],
    5 => [
        'code' => 'P-5',
        'no' => 'V',
        'hindi' => 'भौतिक समुद्र विज्ञान और महासागरीय वातावरण परस्पर क्रिया',
        'english' => 'Physical Oceanography & Ocean - Atmosphere Interaction',
        'max' => 100,
        'pass' => 50
    ],
    6 => [
        'code' => 'Pr',
        'no' => 'VI',
        'hindi' => 'प्रयोगात्मक कार्य (I & II)',
        'english' => 'Practicals (I & II)',
        'max' => 200,
        'pass' => 100
    ],
    7 => [
        'code' => 'Viva',
        'no' => 'VII',
        'hindi' => 'मौखिक',
        'english' => 'Viva-Voce',
        'max' => 100,
        'pass' => 50
    ],
    8 => [
        'code' => 'Proj',
        'no' => 'VIII',
        'hindi' => 'प्रकल्प कार्य',
        'english' => 'Project Work Presentation',
        'max' => 50,
        'pass' => 25
    ],
    9 => [
        'code' => 'OJT',
        'no' => 'IX',
        'hindi' => 'कार्यरत प्रशिक्षण',
        'english' => 'On the Job Training (OJT)',
        'max' => 50,
        'pass' => 25
    ]
];

// Build actual dynamic paper rows
$displayRows = [];
$practicalSum = 0;
$hasPractical = false;

foreach ($tabulationRows as $tRow) {
    $code = strtoupper(trim($tRow['paper_code']));
    $type = strtoupper(trim($tRow['paper_type'] ?? ''));
    $score = $tRow['combined_total'] !== null ? (float)$tRow['combined_total'] : null;

    if (strpos($code, 'PR') !== false || $type === 'PRACTICAL') {
        $hasPractical = true;
        if ($score !== null) {
            $practicalSum += $score;
        }
    }
}

// Map tabulation rows into display table
$pIndex = 1;
foreach ($tabulationRows as $tRow) {
    $code = strtoupper(trim($tRow['paper_code']));
    $type = strtoupper(trim($tRow['paper_type'] ?? ''));
    $score = $tRow['combined_total'] !== null ? (float)$tRow['combined_total'] : null;

    // Group practicals into single row VI if multiple
    if (strpos($code, 'PR') !== false || $type === 'PRACTICAL') {
        continue;
    }

    $paperMeta = $standardPapersMap[$pIndex] ?? [
        'no' => strtoupper(dechex($pIndex)),
        'hindi' => $tRow['paper_name'],
        'english' => $tRow['paper_name'],
        'max' => 100,
        'pass' => 50
    ];

    $displayRows[] = [
        'no' => $paperMeta['no'],
        'hindi' => $paperMeta['hindi'],
        'english' => !empty($tRow['paper_name']) ? $tRow['paper_name'] : $paperMeta['english'],
        'max' => $paperMeta['max'],
        'pass' => $paperMeta['pass'],
        'score' => $score
    ];
    $pIndex++;
}

// Add combined practical row VI if present
if ($hasPractical) {
    $displayRows[] = [
        'no' => 'VI',
        'hindi' => 'प्रयोगात्मक कार्य (I & II)',
        'english' => 'Practicals (I & II)',
        'max' => 200,
        'pass' => 100,
        'score' => $practicalSum
    ];
}

// Sort display rows by standard paper order (I, II, III, IV, V, VI, VII, VIII, IX)
$orderMap = ['I'=>1, 'II'=>2, 'III'=>3, 'IV'=>4, 'V'=>5, 'VI'=>6, 'VII'=>7, 'VIII'=>8, 'IX'=>9];
usort($displayRows, function($a, $b) use ($orderMap) {
    $oa = $orderMap[$a['no']] ?? 99;
    $ob = $orderMap[$b['no']] ?? 99;
    return $oa <=> $ob;
});

// Summary numbers
$grandTotal = isset($resultRegister['grand_total']) ? (float)$resultRegister['grand_total'] : 0;
$maxPossibleMarks = isset($resultRegister['max_possible_marks']) ? (float)$resultRegister['max_possible_marks'] : 900;
$percentage = isset($resultRegister['percentage']) ? (float)$resultRegister['percentage'] : 0.0;
$statusRemark = ($percentage >= 50) ? "Passed" : "Failed";
$performanceGrade = getPerformanceGrade($percentage);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Result - <?php echo h($student['student_name'] ?? 'Student'); ?></title>
    <style>
    body {
        margin: 0;
        padding: 0;
        background-color: #f0f0f0;
        font-family: "Times New Roman", Times, serif;
        color: #000;
    }

    .no-print-bar {
        background: #1f4e79;
        color: #fff;
        padding: 12px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 6px rgba(0,0,0,0.2);
    }

    .no-print-bar button {
        background: #27ae60;
        color: #fff;
        border: none;
        padding: 8px 18px;
        font-size: 14px;
        font-weight: bold;
        border-radius: 4px;
        cursor: pointer;
    }

    .no-print-bar button:hover {
        background: #219150;
    }

    .no-print-bar a {
        color: #fff;
        text-decoration: none;
        font-size: 14px;
    }

    /* A4 Certificate Sheet Container */
    .certificate-container {
        width: 205mm;
        min-height: 285mm;
        max-width: 100%;
        margin: 15px auto;
        background: #ffffff;
        padding: 10mm 12mm;
        box-sizing: border-box;
        border: 2px solid #a82424;
        box-shadow: 0 0 10px rgba(0,0,0,0.15);
        position: relative;
        display: flex;
        flex-direction: column;
    }

    /* Header styling */
    .header-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 6px;
    }

    .header-table td {
        vertical-align: middle;
    }

    .logo-left, .logo-right {
        width: 200px;
        text-align: center;
    }

    .logo-left img, .logo-right img {
        display: block;
        width: 150px;
        height: 150px;
        object-fit: contain;
        margin: 0 auto;
    }

    .header-center {
        text-align: center;
        line-height: 1.2;
    }

    .header-center .h-hindi-1 { font-size: 12px; font-weight: bold; }
    .header-center .h-eng-1 { font-size: 14px; font-weight: bold; }
    .header-center .h-hindi-2 { font-size: 11px; font-weight: bold; }
    .header-center .h-eng-2 { font-size: 12px; font-weight: bold; }
    .header-center .h-sub { font-size: 11px; font-weight: bold; }
    .header-center .h-loc { font-size: 11px; font-weight: bold; }

    .main-title {
        text-align: center;
        font-size: 13px;
        font-weight: bold;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
        text-decoration: underline;
    }

    /* Batch / Dates Table */
    .info-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
        font-size: 11px;
    }

    .info-table td {
        border: 1px solid #000;
        padding: 3px 6px;
        vertical-align: middle;
    }

    /* Student details section */
    .student-details-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
        font-size: 11px;
    }

    .student-details-table td {
        padding: 2px 4px;
        vertical-align: top;
    }

    .student-details-table td.label-col {
        width: 160px;
        font-weight: bold;
    }

    .student-details-table td.colon-col {
        width: 15px;
        text-align: center;
        font-weight: bold;
    }

    /* Marks Table */
    .marks-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 10px;
        font-size: 10.5px;
    }

    .marks-table th, .marks-table td {
        border: 1px solid #000;
        padding: 3px 5px;
        vertical-align: middle;
    }

    .marks-table th {
        text-align: center;
        font-weight: bold;
        background-color: #f7f7f7;
    }

    .col-center { text-align: center; }
    .col-right { text-align: right; }
    .col-left { text-align: left; }

    /* Footer & Signature Section */
    .footer-section {
        margin-top: 10px;
        width: 100%;
        font-size: 11px;
    }

    .remarks-block {
        margin-bottom: 12px;
        font-size: 11px;
    }

    .remarks-block strong {
        font-weight: bold;
    }

    .signature-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: auto;
        margin-bottom: 5px;
    }

    .signature-table td {
        vertical-align: bottom;
        font-size: 11px;
    }

    .sig-left {
        text-align: left;
        line-height: 1.3;
    }

    .sig-right {
        text-align: right;
        line-height: 1.3;
    }

    .sig-right strong {
        font-size: 12px;
        display: block;
    }

    @page {
        size: A4 portrait;
        margin: 4mm;
    }

    @media print {
        html, body {
            width: 210mm;
            height: 297mm;
            margin: 0 !important;
            padding: 0 !important;
            background-color: #fff !important;
            overflow: hidden;
        }

        .no-print-bar {
            display: none !important;
        }

        .certificate-container {
            margin: 0 auto !important;
            border: 2px solid #a82424 !important;
            box-shadow: none !important;
            width: 198mm !important;
            height: 285mm !important;
            max-height: 285mm !important;
            padding: 6mm 8mm !important;
            box-sizing: border-box !important;
            page-break-inside: avoid !important;
            page-break-after: avoid !important;
            overflow: hidden !important;
        }
    }
    </style>
</head>
<body>

<!-- TOP CONTROL BAR FOR ADMIN SCREEN -->
<div class="no-print-bar">
    <div>
        <strong>Official Course Result Certificate</strong>
    </div>
    <div>
        <a href="result_register.php" style="margin-right: 15px;">← Back to Result Register</a>
        <button onclick="window.print()">🖨️ Print Certificate</button>
    </div>
</div>

<?php if (!empty($errorMessage)): ?>
    <div style="max-width:800px; margin:40px auto; padding:20px; background:#f8d7da; color:#721c24; border-radius:6px; text-align:center;">
        <h2><?php echo h($errorMessage); ?></h2>
    </div>
<?php else: ?>

<div class="certificate-container">

    <!-- HEADER WITH LOGOS -->
    <table class="header-table">
        <tr>
            <!-- WMO LOGO -->
            <td class="logo-left">
                <img src="../../wmo.png" alt="WMO logo">
            </td>

            <!-- CENTER BILINGUAL DEPARTMENT HEADER -->
            <td class="header-center">
                <div class="h-hindi-1">भारत सरकार मौसम विज्ञान विभाग</div>
                <div class="h-eng-1">India Meteorological Department</div>
                <div class="h-hindi-2">मौसम विज्ञान प्रशिक्षण संस्थान</div>
                <div class="h-eng-2">Meteorological Training Institute</div>
                <div class="h-sub">Regional Training Centre, World Meteorological Organization</div>
                <div class="h-loc">जलवायु अनुसंधान एवम् सेवाएँ का कार्यालय, पुणे – 411008</div>
                <div class="h-loc">O/o Climate Research and Services, Pune – 411008</div>
            </td>

            <!-- IMD LOGO -->
            <td class="logo-right">
                <img src="../../IMD%20Logo.png" alt="India Meteorological Department logo">
            </td>
        </tr>
    </table>

    <!-- COURSE TITLE -->
    <div class="main-title">
        FORECASTERS' TRAINING COURSE RESULT
    </div>

    <!-- BATCH & DATE TABLE -->
    <table class="info-table">
        <tr>
            <td style="width:33%;">
                <strong>दल क्रमांक / BATCH NO.</strong><br>
                <?php echo h($student['batch_name']); ?>
            </td>
            <td style="width:33%;">
                <strong>प्रारंभ दिन / COMMENCED ON</strong><br>
                <?php echo formatDateIndian($student['commenced_date']); ?>
            </td>
            <td style="width:34%;">
                <strong>समापन दिन / COMPLETED ON</strong><br>
                <?php echo formatDateIndian($student['completed_date']); ?>
            </td>
        </tr>
    </table>

    <!-- STUDENT PERSONAL INFORMATION BLOCK -->
    <table class="student-details-table">
        <tr>
            <td class="label-col">अनुक्रमांक / Roll Number</td>
            <td class="colon-col">:</td>
            <td><strong><?php echo h($student['roll_no']); ?></strong></td>
        </tr>
        <tr>
            <td class="label-col">नाम / Name</td>
            <td class="colon-col">:</td>
            <td><strong><?php echo h($student['student_name']); ?></strong></td>
        </tr>
        <tr>
            <td class="label-col">पदनाम / DESIGNATION</td>
            <td class="colon-col">:</td>
            <td><?php echo h($student['designation']); ?></td>
        </tr>
        <tr>
            <td class="label-col">योग्यता / QUALIFICATION</td>
            <td class="colon-col">:</td>
            <td><?php echo h($student['qualification']); ?></td>
        </tr>
        <tr>
            <td class="label-col">कार्यालय / OFFICE &amp; STATION</td>
            <td class="colon-col">:</td>
            <td><?php echo h($student['station'] ?? $student['rmc'] ?? 'C. R. & S. Pune'); ?></td>
        </tr>
    </table>

    <!-- MARKS & SUBJECTS TABLE -->
    <table class="marks-table">
        <thead>
            <tr>
                <th style="width: 8%;">अ.क्र. / Paper No.</th>
                <th style="width: 52%;">विषय / SUBJECT</th>
                <th style="width: 13%;">अधिकतम अंक / MAX. MARKS</th>
                <th style="width: 13%;">उत्तीर्णांक / PASS MARKS</th>
                <th style="width: 14%;">प्राप्तांक / MARKS OBTAINED</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($displayRows as $row): ?>
                <tr>
                    <td class="col-center"><strong><?php echo h($row['no']); ?></strong></td>
                    <td class="col-left">
                        <div style="font-size:11px; font-weight:bold; color:#000;"><?php echo h($row['hindi']); ?> / <?php echo h($row['english']); ?></div>
                    </td>
                    <td class="col-center"><?php echo $row['max']; ?></td>
                    <td class="col-center"><?php echo $row['pass']; ?></td>
                    <td class="col-center">
                        <strong>
                            <?php echo $row['score'] !== null ? number_format($row['score'], 0) : '—'; ?>
                        </strong>
                    </td>
                </tr>
            <?php endforeach; ?>

            <!-- TOTAL ROW -->
            <tr style="font-weight: bold; background-color: #fafafa;">
                <td colspan="2" class="col-right" style="padding-right: 15px;">
                    कुल / TOTAL
                </td>
                <td class="col-center"><?php echo number_format($maxPossibleMarks, 0); ?></td>
                <td class="col-center">450</td>
                <td class="col-center" style="font-size: 13px;">
                    <?php echo number_format($grandTotal, 0); ?>
                </td>
            </tr>

            <!-- PERCENTAGE ROW -->
            <tr style="font-weight: bold; background-color: #fafafa;">
                <td colspan="2" class="col-right" style="padding-right: 15px;">
                    प्रतिशत / PERCENTAGE
                </td>
                <td class="col-center">100</td>
                <td class="col-center">50</td>
                <td class="col-center" style="font-size: 13px;">
                    <?php echo number_format($percentage, 1); ?>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- REMARKS SECTION -->
    <div class="remarks-block">
        <strong>टिप्पणी / REMARKS :</strong>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
        <strong><?php echo h($statusRemark); ?></strong>
        <?php if ($performanceGrade !== "" && strtolower($performanceGrade) !== strtolower($statusRemark)): ?>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            <strong><?php echo h($performanceGrade); ?></strong>
        <?php endif; ?>
    </div>

    <!-- FOOTER SIGNATURE & DIRECTOR NAME SECTION -->
    <table class="signature-table">
        <tr>
            <td class="sig-left" style="width: 55%;">
                सं. टी.- 0852(पू) / पुणे - 8<br>
                प्रशिक्षण संस्थान<br>
                No. T.- 0852(FTC) / PUNE - 8<br>
                <strong>HEAD - METEOROLOGICAL TRAINING INSTITUTE</strong>
            </td>
            <td class="sig-right" style="width: 45%;">
                <div style="margin-bottom: 25px; min-height: 40px;">
                    <!-- Signature space -->
                </div>
                <strong>( <?php echo h($directorNameHindi); ?> )</strong><br>
                <span style="font-size: 12px; font-weight: bold;">(<?php echo h($directorName); ?>)</span><br>
                <span>प्रमुख - मौसम विज्ञान / Head - Meteorological Training Institute</span>
            </td>
        </tr>
    </table>

</div>

<?php endif; ?>

</body>
</html>
