<?php

/*
|--------------------------------------------------------------------------
| FTC Grading & Tabulation Management System
| Faculty - Entered Marks (Theory & Practical Support)
|--------------------------------------------------------------------------
*/

error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once __DIR__ . '/../../teacher/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';

$facultyId = (int)$_SESSION['faculty_id'];


$faculty = null;
$assignments = [];
$errorMessage = "";
$selectedBatchId = isset($_GET["batch_id"]) ? (int)$_GET["batch_id"] : 0;
$selectedTab = isset($_GET["tab"]) ? (int)$_GET["tab"] : 0;
$selectedPaperId = isset($_GET["paper_id"]) ? (int)$_GET["paper_id"] : 0;
$remarkSaved = isset($_GET["remark_saved"]) && $_GET["remark_saved"] === "1";

try {

    if ($facultyId > 0) {
        $stmt = $pdo->prepare("
            SELECT faculty_id, faculty_name, email
            FROM faculties
            WHERE faculty_id = ?
            LIMIT 1
        ");
        $stmt->execute([$facultyId]);
    } else {
        $stmt = $pdo->query("
            SELECT faculty_id, faculty_name, email
            FROM faculties
            ORDER BY faculty_id DESC
            LIMIT 1
        ");
    }

    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$faculty) {
        throw new Exception("Faculty record not found.");
    }
    $facultyId = (int)$faculty["faculty_id"];

    /* Get Faculty Assignments */
    $stmt = $pdo->prepare("
        SELECT
            sfm.batch_id,
            sfm.subject_id,
            sfm.faculty_id,
            b.batch_name,
            b.station,
            s.subject_code,
            s.subject_name,
            s.is_elective
        FROM subject_faculty_mapping sfm
        INNER JOIN batches b ON sfm.batch_id = b.batch_id
        INNER JOIN subjects s ON sfm.subject_id = s.subject_id
        WHERE sfm.faculty_id = ?
          AND b.status NOT IN ('Result Declared', 'Completed')
        " . ($selectedBatchId > 0 ? "AND sfm.batch_id = ?" : "") . "
        ORDER BY s.subject_name ASC, b.batch_name ASC
    ");
    $assignmentParams = [$facultyId];
    if ($selectedBatchId > 0) {
        $assignmentParams[] = $selectedBatchId;
    }
    $stmt->execute($assignmentParams);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch mapped papers for each assignment
    foreach ($assignments as &$asg) {
        $stmtP = $pdo->prepare("
            SELECT
                p.paper_id,
                p.paper_code,
                p.paper_name,
                p.paper_type,
                p.max_internal,
                p.max_midterm,
                p.max_final,
                psm.subject_max_internal,
                psm.subject_max_midterm,
                psm.subject_max_final
            FROM paper_subject_mapping psm
            INNER JOIN papers p ON psm.paper_id = p.paper_id
            WHERE psm.subject_id = ? AND psm.batch_id = ?
            ORDER BY p.paper_type ASC, p.paper_id ASC
        ");
        $stmtP->execute([$asg['subject_id'], $asg['batch_id']]);
        $asg['mapped_papers'] = $stmtP->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($asg);

} catch (Exception $e) {
    $errorMessage = $e->getMessage();
}

if (!empty($assignments) && !isset($assignments[$selectedTab])) {
    $selectedTab = 0;
}

$currentAssignment = !empty($assignments) ? $assignments[$selectedTab] : null;
$currentPaper = null;
$currentMarks = [];

if ($currentAssignment) {
    $mappedPapers = $currentAssignment['mapped_papers'];
    if ($selectedPaperId <= 0 && !empty($mappedPapers)) {
        $selectedPaperId = (int)$mappedPapers[0]['paper_id'];
    }
    foreach ($mappedPapers as $mp) {
        if ((int)$mp['paper_id'] === $selectedPaperId) {
            $currentPaper = $mp;
            break;
        }
    }
    if (!$currentPaper && !empty($mappedPapers)) {
        $currentPaper = $mappedPapers[0];
        $selectedPaperId = (int)$currentPaper['paper_id'];
    }

    // Load student marks for this paper & subject
    $bId = (int)$currentAssignment['batch_id'];
    $sId = (int)$currentAssignment['subject_id'];

    $marksStmt = $pdo->prepare("
        SELECT
            st.student_id,
            st.roll_no,
            st.student_name,
            st.rmc,
            st.group_code,
            st.custom_remarks,
            MAX(CASE WHEN fme.assessment_type_id = 1 THEN fme.marks_obtained END) AS internal_marks,
            MAX(CASE WHEN fme.assessment_type_id = 2 THEN fme.marks_obtained END) AS midterm_marks,
            MAX(CASE WHEN fme.assessment_type_id = 3 THEN fme.marks_obtained END) AS final_marks,
            MAX(CASE WHEN fme.assessment_type_id = 1 THEN fme.is_absent END) AS internal_absent,
            MAX(CASE WHEN fme.assessment_type_id = 2 THEN fme.is_absent END) AS midterm_absent,
            MAX(CASE WHEN fme.assessment_type_id = 3 THEN fme.is_absent END) AS final_absent
        FROM students st
        LEFT JOIN faculty_marks_entry fme
            ON fme.student_id = st.student_id
            AND fme.subject_id = :subject_id
            AND fme.faculty_id = :faculty_id
            AND (fme.paper_id = :paper_id OR (fme.paper_id IS NULL AND :paper_id_zero = 0))
        WHERE st.batch_id = :batch_id
          AND (st.is_deleted = 0 OR st.is_deleted IS NULL)
        GROUP BY st.student_id, st.roll_no, st.student_name, st.rmc, st.group_code, st.custom_remarks
        ORDER BY st.roll_no ASC, st.student_name ASC
    ");
    $marksStmt->execute([
        ":subject_id" => $sId,
        ":faculty_id" => $facultyId,
        ":paper_id"   => $selectedPaperId,
        ":paper_id_zero" => $selectedPaperId,
        ":batch_id"   => $bId
    ]);
    $currentMarks = $marksStmt->fetchAll(PDO::FETCH_ASSOC);

    // Apply elective eligibility only in the P-V paper context.
    $isElective = strtoupper(trim($currentPaper['paper_code'] ?? '')) === 'P-V';

    if ($isElective) {
        $egStmt = $pdo->prepare("SELECT group_code FROM elective_subject_mapping WHERE subject_id = ?");
        $egStmt->execute([$sId]);
        $allowedGroups = array_map(function($g) { return strtolower(trim($g)); }, $egStmt->fetchAll(PDO::FETCH_COLUMN));
        if (!empty($allowedGroups)) {
            $currentMarks = array_values(array_filter($currentMarks, function($m) use ($allowedGroups) {
                $stGroup = strtolower(trim($m['group_code'] ?? ''));
                return in_array($stGroup, $allowedGroups, true);
            }));
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Faculty - Entered Marks</title>
<style>
* { box-sizing: border-box; }
body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f4f6f8;
    color: #333;
}
.header {
    background: #1f4e79;
    color: white;
    padding: 22px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.header h2 { margin: 0; }
.layout {
    display: flex;
    min-height: calc(100vh - 70px);
}
.sidebar {
    width: 240px;
    background: white;
    border-right: 1px solid #ddd;
}
.sidebar h3 {
    padding: 20px;
    margin: 0;
    color: #1f4e79;
}
.sidebar a {
    display: block;
    padding: 15px 20px;
    text-decoration: none;
    color: #333;
    border-top: 1px solid #eee;
}
.sidebar a:hover,
.sidebar .active {
    background: #eaf2f8;
    color: #1f4e79;
    font-weight: bold;
}
.content {
    flex: 1;
    padding: 35px;
    overflow-x: auto;
}
.error-box {
    background: #fdecea;
    color: #a12622;
    padding: 15px 20px;
    border-radius: 6px;
    margin-bottom: 20px;
}
.success-box {
    background: #eaf7ed;
    color: #216e39;
    padding: 15px 20px;
    border-radius: 6px;
    margin-bottom: 20px;
}
.subject-tabs {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 25px;
}
.subject-tab {
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: bold;
    font-size: 14px;
    text-decoration: none;
    transition: all 0.2s ease;
}
.subject-tab.active {
    background: #1f4e79;
    color: #ffffff;
    box-shadow: 0 4px 10px rgba(31,78,121,0.3);
}
.subject-tab.inactive {
    background: #ffffff;
    color: #1f4e79;
    border: 1px solid #d5d5d5;
}
.subject-tab.inactive:hover {
    background: #eaf2f8;
}
.paper-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    background: #f8fafc;
    padding: 8px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}
.paper-tab {
    padding: 10px 18px;
    border-radius: 6px;
    font-weight: bold;
    font-size: 13px;
    text-decoration: none;
    transition: all 0.2s ease;
}
.paper-tab.active {
    background: #1f4e79;
    color: #fff;
}
.paper-tab.inactive {
    background: #fff;
    color: #333;
    border: 1px solid #cbd5e1;
}
.card {
    background: #ffffff;
    border-radius: 10px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    overflow: hidden;
}
.card-header {
    padding: 22px 25px;
    border-bottom: 1px solid #eee;
}
table {
    width: 100%;
    min-width: 1100px;
    border-collapse: collapse;
}
th {
    padding: 13px;
    background: #1f4e79;
    color: #ffffff;
    font-size: 13px;
}
td {
    padding: 13px;
    border-bottom: 1px solid #eee;
    font-size: 14px;
}
.absent-badge {
    display: inline-block;
    padding: 3px 8px;
    background: #fdecea;
    color: #a12622;
    border-radius: 4px;
    font-weight: bold;
    font-size: 12px;
}
.mark-val {
    font-weight: bold;
    color: #1f4e79;
}
.action-btn {
    padding: 7px 14px;
    background: #1f4e79;
    color: #fff;
    border: 0;
    border-radius: 4px;
    cursor: pointer;
    font-weight: bold;
    font-size: 12px;
}
.action-btn:hover { background: #173b5c; }
</style>
</head>
<body>

<header class="header">
    <h2>FTC Grading & Tabulation System</h2>
    <div>Faculty Panel</div>
</header>

<div class="layout">

<?php require_once __DIR__ . '/../../includes/faculty_sidebar.php'; ?>

<main class="content">

<h1 style="color:#1f4e79; margin: 0 0 10px;">Entered Marks Overview</h1>
<p style="color:#666; margin-bottom:25px;">
    View and verify entered marks for each assigned subject and component.
</p>

<?php if ($errorMessage): ?>
    <div class="error-box"><?php echo htmlspecialchars($errorMessage); ?></div>
<?php endif; ?>

<?php if (empty($assignments)): ?>
    <div class="card" style="padding:40px; text-align:center; color:#777;">
        <h3>No Assigned Subjects</h3>
        <p>You do not currently have any subjects assigned to you.</p>
    </div>
<?php else: ?>

    <!-- SUBJECT TABS -->
    <div class="subject-tabs">
        <?php foreach ($assignments as $index => $asg): ?>
            <?php $isActive = ($index === $selectedTab); ?>
            <a href="?tab=<?php echo $index; ?>&paper_id=0" class="subject-tab <?php echo $isActive ? 'active' : 'inactive'; ?>">
                [<?php echo htmlspecialchars($asg['subject_code']); ?>] <?php echo htmlspecialchars($asg['subject_name']); ?>
                <small style="opacity:0.85; font-weight:normal; display:block; font-size:11px;">
                    <?php echo htmlspecialchars($asg['batch_name']); ?>
                </small>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- CURRENT SUBJECT CARD -->
    <div class="card">
        <div class="card-header">
            <div style="color:#777; font-size:13px; font-weight:bold; text-transform:uppercase;">
                <?php echo htmlspecialchars($currentAssignment['batch_name']); ?> - <?php echo htmlspecialchars($currentAssignment['station']); ?>
            </div>
            <h2 style="margin:8px 0 5px; color:#1f4e79;">
                [<?php echo htmlspecialchars($currentAssignment['subject_code']); ?>] <?php echo htmlspecialchars($currentAssignment['subject_name']); ?>
            </h2>

            <?php
                $entryBase = 'batch_id=' . (int)$currentAssignment['batch_id']
                    . '&subject_id=' . (int)$currentAssignment['subject_id']
                    . '&faculty_id=' . (int)$facultyId
                    . '&paper_id=' . (int)$selectedPaperId;
            ?>
            <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-top:14px;">
                <strong style="font-size:13px; color:#555;">Go to Marks Entry:</strong>
                <a class="action-btn" href="internal.php?<?php echo $entryBase; ?>">Internal</a>
                <a class="action-btn" href="midterm.php?<?php echo $entryBase; ?>">Mid-Term</a>
                <a class="action-btn" href="final.php?<?php echo $entryBase; ?>">Final</a>
            </div>

            <!-- THEORY / PRACTICAL SUB-TABS -->
            <?php if (!empty($currentAssignment['mapped_papers'])): ?>
                <div class="paper-tabs" style="margin-top:15px; margin-bottom:0;">
                    <?php foreach ($currentAssignment['mapped_papers'] as $mp): ?>
                        <?php $isPaperActive = ((int)$mp['paper_id'] === $selectedPaperId); ?>
                        <a href="?tab=<?php echo $selectedTab; ?>&paper_id=<?php echo $mp['paper_id']; ?>"
                           class="paper-tab <?php echo $isPaperActive ? 'active' : 'inactive'; ?>">
                            <span><?php echo htmlspecialchars($mp['paper_type']); ?>: [<?php echo htmlspecialchars($mp['paper_code']); ?>] <?php echo htmlspecialchars($mp['paper_name']); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th style="text-align:left;">Roll No</th>
                        <th style="text-align:left;">Student</th>
                        <th style="text-align:center;">
                            Internal
                            <?php if ($currentPaper): ?>
                                <span style="display:block; font-size:10px; font-weight:normal; opacity:0.9;">
                                    Max: <?php echo number_format((float)(!empty($currentPaper['subject_max_internal']) ? $currentPaper['subject_max_internal'] : $currentPaper['max_internal']), 2); ?>
                                </span>
                            <?php endif; ?>
                        </th>
                        <th style="text-align:center;">
                            Mid-Term
                            <?php if ($currentPaper): ?>
                                <span style="display:block; font-size:10px; font-weight:normal; opacity:0.9;">
                                    Max: <?php echo number_format((float)(!empty($currentPaper['subject_max_midterm']) ? $currentPaper['subject_max_midterm'] : $currentPaper['max_midterm']), 2); ?>
                                </span>
                            <?php endif; ?>
                        </th>
                        <th style="text-align:center;">
                            Final
                            <?php if ($currentPaper): ?>
                                <span style="display:block; font-size:10px; font-weight:normal; opacity:0.9;">
                                    Max: <?php echo number_format((float)(!empty($currentPaper['subject_max_final']) ? $currentPaper['subject_max_final'] : $currentPaper['max_final']), 2); ?>
                                </span>
                            <?php endif; ?>
                        </th>
                        <th style="text-align:center;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($currentMarks)): ?>
                        <tr>
                            <td colspan="6" style="padding:35px; text-align:center; color:#777;">
                                No student records found for this batch.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($currentMarks as $st): ?>
                            <?php
                                $intVal = null;
                                $midVal = null;
                                $finVal = null;
                                $hasAny = false;

                                if ((int)$st['internal_absent'] === 1) {
                                    $intDisplay = '<span class="absent-badge">Absent</span>';
                                } elseif ($st['internal_marks'] !== null) {
                                    $intVal = (float)$st['internal_marks'];
                                    $intDisplay = '<span class="mark-val">' . number_format($intVal, 2) . '</span>';
                                    $hasAny = true;
                                } else {
                                    $intDisplay = '<span style="color:#aaa;">—</span>';
                                }

                                if ((int)$st['midterm_absent'] === 1) {
                                    $midDisplay = '<span class="absent-badge">Absent</span>';
                                } elseif ($st['midterm_marks'] !== null) {
                                    $midVal = (float)$st['midterm_marks'];
                                    $midDisplay = '<span class="mark-val">' . number_format($midVal, 2) . '</span>';
                                    $hasAny = true;
                                } else {
                                    $midDisplay = '<span style="color:#aaa;">—</span>';
                                }

                                if ((int)$st['final_absent'] === 1) {
                                    $finDisplay = '<span class="absent-badge">Absent</span>';
                                } elseif ($st['final_marks'] !== null) {
                                    $finVal = (float)$st['final_marks'];
                                    $finDisplay = '<span class="mark-val">' . number_format($finVal, 2) . '</span>';
                                    $hasAny = true;
                                } else {
                                    $finDisplay = '<span style="color:#aaa;">—</span>';
                                }

                                $totalMarks = ($intVal ?? 0) + ($midVal ?? 0) + ($finVal ?? 0);
                            ?>
                            <tr>
                                <td style="font-weight:bold; color:#1f4e79;">
                                    <?php echo htmlspecialchars($st['roll_no']); ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($st['student_name']); ?></strong>
                                    <?php if (!empty($st['rmc'])): ?>
                                        <br><small style="color:#777;"><?php echo htmlspecialchars($st['rmc']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;"><?php echo $intDisplay; ?></td>
                                <td style="text-align:center;"><?php echo $midDisplay; ?></td>
                                <td style="text-align:center;"><?php echo $finDisplay; ?></td>
                                <td style="text-align:center; font-weight:bold; color:#1f4e79;">
                                    <?php echo $hasAny ? number_format($totalMarks, 2) : '<span style="color:#aaa;">—</span>'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php endif; ?>

</main>
</div>
</body>
</html>
