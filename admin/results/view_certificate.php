<?php
// admin/results/view_certificate.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . "/../../includes/auth.php";
require_permission('results.view');
require_once __DIR__ . "/../../config/database.php";

function h($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$studentId = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
if ($studentId <= 0) {
    die("Invalid Student Selected.");
}

$pdo = getDbConnection();

// Fetch student details
$stmt = $pdo->prepare("
    SELECT s.*, b.batch_name, b.batch_number, b.commenced_date, b.completed_date, b.station
    FROM students s
    LEFT JOIN batches b ON b.batch_id = s.batch_id
    WHERE s.student_id = :sid AND (s.is_deleted = 0 OR s.is_deleted IS NULL)
    LIMIT 1
");
$stmt->execute([':sid' => $studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    die("Student record not found.");
}

// Fetch student performance / result
$stmtRes = $pdo->prepare("
    SELECT * FROM v_result_register WHERE student_id = :sid LIMIT 1
");
$stmtRes->execute([':sid' => $studentId]);
$resultData = $stmtRes->fetch(PDO::FETCH_ASSOC);

// Handle director name updates if passed via GET/POST
$activeDirectorEng = trim($_REQUEST['director_name'] ?? '');
$activeDirectorHindi = trim($_REQUEST['director_name_hindi'] ?? '');

if (empty($activeDirectorEng) || empty($activeDirectorHindi)) {
    // Fetch global setting defaults if not passed
    $stmtSet = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('director_name', 'director_name_hindi')");
    $stmtSet->execute();
    foreach ($stmtSet->fetchAll(PDO::FETCH_ASSOC) as $setting) {
        if ($setting['setting_key'] === 'director_name' && empty($activeDirectorEng)) {
            $activeDirectorEng = trim($setting['setting_value'] ?? '');
        }
        if ($setting['setting_key'] === 'director_name_hindi' && empty($activeDirectorHindi)) {
            $activeDirectorHindi = trim($setting['setting_value'] ?? '');
        }
    }
}

if (empty($activeDirectorEng)) {
    $activeDirectorEng = "K. N. Mohan";
}
if (empty($activeDirectorHindi)) {
    $activeDirectorHindi = "के.एन.मोहन";
}

// Overwrite/save the director name in student table on generation
try {
    $updateStmt = $pdo->prepare("
        UPDATE students
        SET generated_director_name = :dir_eng,
            generated_director_name_hindi = :dir_hin,
            certificate_generated_at = NOW()
        WHERE student_id = :sid
    ");
    $updateStmt->execute([
        ':dir_eng' => $activeDirectorEng,
        ':dir_hin' => $activeDirectorHindi,
        ':sid'     => $studentId
    ]);
} catch (PDOException $e) {
    // Graceful fallback if column doesn't exist
}

// Calculate Completed Month & Year
$completedDate = $student['completed_date'] ?? null;
if (!empty($completedDate) && strtotime($completedDate) !== false) {
    $ts = strtotime($completedDate);
    $completionYear = date('Y', $ts);
    $completionMonthEnglish = date('F', $ts);
    $completionMonthYearEnglish = date('F Y', $ts);
} else {
    $completionYear = "2024";
    $completionMonthEnglish = "March";
    $completionMonthYearEnglish = "March 2024";
}

$monthHindiMap = [
    'January' => 'जनवरी', 'February' => 'फरवरी', 'March' => 'मार्च', 'April' => 'अप्रैल',
    'May' => 'मई', 'June' => 'जून', 'July' => 'जुलाई', 'August' => 'अगस्त',
    'September' => 'सितंबर', 'October' => 'अक्टूबर', 'November' => 'नवंबर', 'December' => 'दिसंबर'
];
$completionMonthHindi = $monthHindiMap[$completionMonthEnglish] ?? $completionMonthEnglish;

// Performance / Grade Determination
$percentage = isset($resultData['percentage']) ? (float)$resultData['percentage'] : 75.0;

if ($percentage >= 90.0) {
    $performanceTextEnglish = "Outstanding";
    $performanceTextHindi = "उत्कृष्ट";
} elseif ($percentage >= 80.0) {
    $performanceTextEnglish = "Very Good";
    $performanceTextHindi = "बहुत अच्छे";
} elseif ($percentage >= 70.0) {
    $performanceTextEnglish = "Very Good";
    $performanceTextHindi = "बहुत अच्छे";
} elseif ($percentage >= 60.0) {
    $performanceTextEnglish = "Good";
    $performanceTextHindi = "अच्छे";
} else {
    $performanceTextEnglish = "Satisfactory";
    $performanceTextHindi = "संतोषजनक";
}

// Format Student Name with Salutation and Devanagari Hindi support
$rawName = trim($student['student_name'] ?? '');
$cleanNameEng = preg_replace('/^(Mr\.|Ms\.|Mrs\.|Shri|Smt\.|Dr\.)\s*/i', '', $rawName);
$prefixKey = trim($student['name_prefix'] ?? 'Mr.');
if (empty($prefixKey)) $prefixKey = 'Mr.';

$prefixMap = [
    'Mr.'   => ['en' => 'Mr.',   'hi' => 'श्री.'],
    'Ms.'   => ['en' => 'Ms.',   'hi' => 'कुमारी.'],
    'Mrs.'  => ['en' => 'Mrs.',  'hi' => 'श्रीमती.'],
    'Dr.'   => ['en' => 'Dr.',   'hi' => 'डॉ.'],
    'Shri'  => ['en' => 'Shri',  'hi' => 'श्री.'],
    'Smt.'  => ['en' => 'Smt.',  'hi' => 'श्रीमती.'],
];
$pInfo = $prefixMap[$prefixKey] ?? ['en' => 'Mr.', 'hi' => 'श्री.'];

$studentNameHindi = trim($student['student_name_hindi'] ?? '');
if (empty($studentNameHindi)) {
    $studentNameHindi = $cleanNameEng;
}

$studentSalutationNameEnglish = $pInfo['en'] . ' ' . $cleanNameEng;
$studentSalutationNameHindi = $pInfo['hi'] . ' ' . $studentNameHindi;

$todayEnglish = date('d.m.Y');
$todayHindi = date('d.m.Y');
$appBase = preg_replace('#/(admin|teacher|verification|includes)/.*$#i', '', $_SERVER['SCRIPT_NAME'] ?? '');

?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FTC Training Certificate - <?= h($student['student_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Noto+Sans+Devanagari:wght@400;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 0;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 0;
            background: #e2e8f0;
            font-family: 'Times New Roman', 'Noto Sans Devanagari', serif;
            color: #111827;
        }

        .no-print-bar {
            background: #1f4e79;
            color: #ffffff;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
            margin-bottom: 20px;
        }

        .no-print-bar .btn {
            background: #f59e0b;
            color: #111827;
            font-weight: bold;
            padding: 8px 18px;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }

        .no-print-bar .btn-secondary {
            background: #ffffff;
            color: #1f4e79;
            margin-right: 10px;
        }

        .cert-container {
            width: 210mm;
            min-height: 297mm;
            margin: 0 auto 30px auto;
            background: #ffffff;
            padding: 10mm;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            position: relative;
            box-sizing: border-box;
        }

        .cert-border-outer {
            border: 3px double #d97706;
            padding: 6mm;
            height: 100%;
            position: relative;
            box-sizing: border-box;
        }

        .cert-border-inner {
            border: 1px solid #d97706;
            padding: 10mm 12mm;
            height: 100%;
            display: flex;
            flex-direction: column;
            box-sizing: border-box;
        }

        .header-logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .header-logos img.wmo-logo {
            height: 100px;
            width: auto;
            object-fit: contain;
        }

        .header-logos img.imd-logo {
            height: 105px;
            width: auto;
            object-fit: contain;
        }

        .header-title-hindi {
            text-align: center;
            flex: 1;
            padding: 0 10px;
        }

        .title-blue-hi {
            color: #1d4ed8;
            font-size: 21px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .title-orange-hi {
            color: #ea580c;
            font-size: 19px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .title-green-hi {
            color: #16a34a;
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .cert-heading-red-hi {
            color: #dc2626;
            font-size: 22px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 28px;
        }

        .body-para-hi {
            font-size: 16px;
            line-height: 1.85;
            text-align: justify;
            margin-bottom: 35px;
            color: #000000;
        }

        .header-title-eng {
            text-align: center;
            margin-top: 10px;
        }

        .title-orange-en {
            color: #ea580c;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .title-green-en {
            color: #16a34a;
            font-size: 17px;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .cert-heading-red-en {
            color: #dc2626;
            font-size: 23px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 28px;
        }

        .body-para-en {
            font-size: 16px;
            line-height: 1.85;
            text-align: justify;
            margin-bottom: 45px;
            color: #000000;
        }

        .footer-signatures {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: auto;
            padding-bottom: 5px;
            font-size: 14px;
            line-height: 1.5;
        }

        .footer-left {
            text-align: left;
        }

        .footer-right {
            text-align: center;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }
            html, body {
                width: 210mm;
                height: 297mm;
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
                overflow: hidden;
            }
            .no-print-bar { display: none !important; }
            .cert-container {
                box-shadow: none !important;
                margin: 0 !important;
                width: 210mm !important;
                height: 297mm !important;
                padding: 10mm !important;
                page-break-after: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    <div class="no-print-bar">
        <div>
            <strong>FTC Training Certificate</strong> &nbsp;|&nbsp; Student: <strong><?= h($student['student_name']) ?></strong> (Roll No: <?= h($student['roll_no']) ?>)
        </div>
        <div>
            <a href="<?= h($appBase . '/admin/results/certificates.php?batch_id=' . $student['batch_id']) ?>" class="btn btn-secondary">← Back to Certificate Panel</a>
            <button onclick="window.print()" class="btn">🖨️ Print / Download PDF</button>
        </div>
    </div>

    <div class="cert-container">
        <div class="cert-border-outer">
            <div class="cert-border-inner">

                <!-- TOP LOGOS & HINDI HEADER -->
                <div class="header-logos">
                    <img src="<?= h($appBase . '/wmo.png') ?>" alt="WMO Logo" class="wmo-logo">
                    <div class="header-title-hindi">
                        <div class="title-blue-hi">भारत मौसम विज्ञान विभाग</div>
                        <div class="title-blue-hi" style="font-family:'Times New Roman', serif; font-style:italic; font-size:22px;">India Meteorological Department</div>
                        <div class="title-orange-hi">मौसम विज्ञान प्रशिक्षण संस्थान</div>
                        <div class="title-green-hi">विश्व मौसम संगठन का क्षेत्रीय प्रशिक्षण केन्द्र</div>
                    </div>
                    <img src="<?= h($appBase . '/IMD Logo.png') ?>" alt="IMD Logo" class="imd-logo">
                </div>

                <div class="cert-heading-red-hi">प्रशिक्षण प्रमाणपत्र</div>

                <!-- HINDI BODY PARAGRAPH -->
                <div class="body-para-hi">
                    यह प्रमाणित किया जाता है कि <strong><?php echo h($studentSalutationNameHindi); ?></strong> ने वर्ष <strong><?php echo h($completionYear); ?></strong> के <strong><?php echo h($completionMonthHindi); ?></strong> महीने में मौसम विज्ञान प्रशिक्षण संस्थान, पुणे द्वारा संचालित सामान्य मौसम विज्ञान के प्रशिक्षण का <strong>पूर्वानुमान प्रशिक्षण पाठ्यक्रम</strong> सफलतापूर्वक पूर्ण कर लिया है तथा निर्धारित परीक्षाएं <strong><?php echo h($performanceTextHindi); ?> प्रदर्शन</strong> के साथ उत्तीर्ण कर ली हैं। यह पाठ्यक्रम <strong>विश्व मौसम संगठन बी. आई. पी.-एम.</strong> की आवश्यकताएं पूर्ण करता है।
                </div>

                <!-- ENGLISH HEADER -->
                <div class="header-title-eng">
                    <div class="title-orange-en">Meteorological Training Institute</div>
                    <div class="title-green-en">Regional Training Centre, World Meteorological Organization</div>
                    <div class="cert-heading-red-en">Certificate of Training</div>
                </div>

                <!-- ENGLISH BODY PARAGRAPH -->
                <div class="body-para-en">
                    This is to certify that <strong><?php echo h($studentSalutationNameEnglish); ?></strong> has successfully completed the <strong>Forecaster Training Course</strong> in General Meteorology and has passed the prescribed tests conducted by the Meteorological Training Institute, Pune in the month of <strong><?php echo h($completionMonthYearEnglish); ?></strong> with <strong><?php echo h($performanceTextEnglish); ?> performance</strong>. This course meets the requirements of <strong>WMO BIP-M</strong>.
                </div>

                <!-- FOOTER & SIGNATURES -->
                <div class="footer-signatures">
                    <div class="footer-left">
                        <strong>भारत मौसम विज्ञान विभाग, पुणे</strong><br>
                        दिनांक: <strong><?= h($todayHindi) ?></strong><br><br>
                        <strong>India Meteorological Department, Pune</strong><br>
                        Dated: <strong><?= h($todayEnglish) ?></strong>
                    </div>
                    <div class="footer-right">
                        <strong>( <?= h($activeDirectorHindi) ?> )</strong><br>
                        <strong>प्रमुख, मौसम विज्ञान प्रशिक्षण संस्थान</strong><br><br>
                        <strong>( <?= h($activeDirectorEng) ?> )</strong><br>
                        <strong>Head-Meteorological Training Institute</strong>
                    </div>
                </div>

            </div>
        </div>
    </div>

</body>
</html>
