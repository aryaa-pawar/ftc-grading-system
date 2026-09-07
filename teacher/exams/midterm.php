<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../teacher/auth_guard.php';
require_once __DIR__ . '/../../config/database.php';

$batchId   = isset($_GET['batch_id'])   ? (int)$_GET['batch_id']   : 0;
$subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$paperId   = isset($_GET['paper_id'])   ? (int)$_GET['paper_id']   : (isset($_POST['paper_id']) ? (int)$_POST['paper_id'] : 0);
$facultyId = (int)($_SESSION['faculty_id'] ?? 0);


$error = "";
$success = "";

$faculty = null;
$assignment = null;
$mappedPapers = [];
$currentPaper = null;
$students = [];
$existingMarks = [];
$maxMarks = 25.00;

try {

    if ($facultyId <= 0 || $batchId <= 0 || $subjectId <= 0) {
        throw new Exception("Invalid faculty, batch or subject.");
    }

    /* Faculty */
    $stmt = $pdo->prepare("
        SELECT faculty_id, faculty_name, email
        FROM faculties
        WHERE faculty_id = ?
    ");
    $stmt->execute([$facultyId]);
    $faculty = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$faculty) {
        throw new Exception("Faculty not found.");
    }

    /* Assessment type - Mid-Term */
    $stmt = $pdo->prepare("
        SELECT assessment_type_id, assessment_type_name
        FROM assessment_types
        WHERE assessment_type_name = 'Mid-Term'
        LIMIT 1
    ");
    $stmt->execute();
    $assessment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assessment) {
        throw new Exception("Mid-Term assessment type not found.");
    }
    $assessmentTypeId = (int)$assessment['assessment_type_id'];

    /* Verify faculty assignment */
    $stmt = $pdo->prepare("
        SELECT
            sfm.batch_id,
            sfm.subject_id,
            sfm.faculty_id,
            b.batch_name,
            b.station,
            b.status AS batch_status,
            s.subject_code,
            s.subject_name,
            s.is_elective
        FROM subject_faculty_mapping sfm
        INNER JOIN batches b
            ON b.batch_id = sfm.batch_id
        INNER JOIN subjects s
            ON s.subject_id = sfm.subject_id
        WHERE sfm.batch_id = ?
          AND sfm.subject_id = ?
          AND sfm.faculty_id = ?
        LIMIT 1
    ");
    $stmt->execute([$batchId, $subjectId, $facultyId]);
    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$assignment) {
        throw new Exception("This subject is not assigned to this faculty for this batch.");
    }

    if (in_array(($assignment['batch_status'] ?? ''), ['Result Declared', 'Completed'], true)) {
        throw new Exception("Results for this batch have been declared. Marks entry is no longer available.");
    }

    /* Get Mapped Papers for this Subject (Theory / Practical) */
    $stmt = $pdo->prepare("
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
        INNER JOIN papers p
            ON psm.paper_id = p.paper_id
        WHERE psm.subject_id = ?
          AND psm.batch_id = ?
        ORDER BY p.paper_type ASC, p.paper_id ASC
    ");
    $stmt->execute([$subjectId, $batchId]);
    $mappedPapers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($paperId <= 0 && !empty($mappedPapers)) {
        $paperId = (int)$mappedPapers[0]['paper_id'];
    }

    foreach ($mappedPapers as $mp) {
        if ((int)$mp['paper_id'] === $paperId) {
            $currentPaper = $mp;
            break;
        }
    }
    if (!$currentPaper && !empty($mappedPapers)) {
        $currentPaper = $mappedPapers[0];
        $paperId = (int)$currentPaper['paper_id'];
    }

    // Determine max marks for Mid-Term
    if ($currentPaper) {
        $maxMarks = (float)(!empty($currentPaper['subject_max_midterm']) && $currentPaper['subject_max_midterm'] > 0
            ? $currentPaper['subject_max_midterm']
            : $currentPaper['max_midterm']);
    }

    /* Apply elective eligibility only in the P-V paper context. */
    $isElective = strtoupper(trim($currentPaper['paper_code'] ?? '')) === 'P-V';

    $allowedGroups = [];
    if ($isElective) {
        $stmt = $pdo->prepare("SELECT group_code FROM elective_subject_mapping WHERE subject_id = ?");
        $stmt->execute([$subjectId]);
        $allowedGroups = array_map(function($g) { return strtolower(trim($g)); }, $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /* Students */
    $stmt = $pdo->prepare("
        SELECT student_id, roll_no, student_name, designation, group_code
        FROM students
        WHERE batch_id = ?
          AND (is_deleted = 0 OR is_deleted IS NULL)
        ORDER BY roll_no ASC, student_name ASC
    ");
    $stmt->execute([$batchId]);
    $allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($isElective && !empty($allowedGroups)) {
        $students = array_filter($allStudents, function($st) use ($allowedGroups) {
            $stGroup = strtolower(trim($st['group_code'] ?? ''));
            return in_array($stGroup, $allowedGroups, true);
        });
        $students = array_values($students);
    } else {
        $students = $allStudents;
    }

    /* Existing marks */
    $stmt = $pdo->prepare("
        SELECT student_id, marks_obtained, is_absent
        FROM faculty_marks_entry
        WHERE subject_id = ?
          AND faculty_id = ?
          AND assessment_type_id = ?
          AND (paper_id = ? OR (paper_id IS NULL AND ? = 0))
    ");
    $stmt->execute([$subjectId, $facultyId, $assessmentTypeId, $paperId, $paperId]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existingMarks[(int)$row['student_id']] = $row;
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}

/* Save Marks */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === "") {
    try {
        $pdo->beginTransaction();

        $lockCheck = $pdo->prepare('SELECT is_locked FROM batches WHERE batch_id = ? LIMIT 1 FOR UPDATE');
        $lockCheck->execute([$batchId]);
        if ((int)$lockCheck->fetchColumn() === 1) {
            throw new Exception('Marks are locked for this batch. No changes are allowed.');
        }

        $stmt = $pdo->prepare("
            SELECT student_id
            FROM students
            WHERE batch_id = ?
              AND (is_deleted = 0 OR is_deleted IS NULL)
        ");
        $stmt->execute([$batchId]);
        $studentIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if ($isElective && !empty($allowedGroups)) {
            $studentIds = array_values(array_filter($studentIds, function ($studentId) use ($pdo, $allowedGroups) {
                $check = $pdo->prepare('SELECT group_code FROM students WHERE student_id = ?');
                $check->execute([(int)$studentId]);
                return in_array(strtolower(trim((string)$check->fetchColumn())), $allowedGroups, true);
            }));
        }

        $save = $pdo->prepare("
            INSERT INTO faculty_marks_entry
            (
                student_id,
                subject_id,
                faculty_id,
                paper_id,
                assessment_type_id,
                marks_obtained,
                is_absent
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                marks_obtained = VALUES(marks_obtained),
                is_absent = VALUES(is_absent),
                graded_at = CURRENT_TIMESTAMP
        ");

        foreach ($studentIds as $studentId) {
            $studentId = (int)$studentId;
            $absent = isset($_POST['absent'][$studentId]);

            if ($absent) {
                $marks = null;
                $isAbsent = 1;
            } else {
                if (!isset($_POST['marks'][$studentId]) || $_POST['marks'][$studentId] === "") {
                    continue;
                }
                $marks = (float)$_POST['marks'][$studentId];
                if ($marks < 0 || $marks > $maxMarks) {
                    throw new Exception("Marks for student ID " . $studentId . " must be between 0 and " . $maxMarks . ".");
                }
                $isAbsent = 0;
            }

            $save->execute([
                $studentId,
                $subjectId,
                $facultyId,
                $paperId > 0 ? $paperId : null,
                $assessmentTypeId,
                $marks,
                $isAbsent
            ]);
        }

        $pdo->commit();
        $success = "Mid-Term marks saved successfully.";

        // Reload existing marks
        $stmt = $pdo->prepare("
            SELECT student_id, marks_obtained, is_absent
            FROM faculty_marks_entry
            WHERE subject_id = ?
              AND faculty_id = ?
              AND assessment_type_id = ?
              AND (paper_id = ? OR (paper_id IS NULL AND ? = 0))
        ");
        $stmt->execute([$subjectId, $facultyId, $assessmentTypeId, $paperId, $paperId]);
        $existingMarks = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $existingMarks[(int)$row['student_id']] = $row;
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mid-Term Marks Entry</title>
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
.back {
    display: inline-block;
    margin-bottom: 20px;
    text-decoration: none;
    color: #1f4e79;
    font-weight: bold;
}
h1 { color: #1f4e79; margin: 0 0 10px; }
.info {
    background: white;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 3px 10px rgba(0,0,0,.08);
}
.info-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
}
.info-box {
    background: #f8fafb;
    padding: 15px;
    border-radius: 6px;
}
.info-box small { color: #777; }
.info-box strong {
    display: block;
    margin-top: 5px;
    color: #1f4e79;
}
.success {
    background: #eaf7ed;
    color: #216e39;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
}
.error {
    background: #fdecea;
    color: #a12622;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
}
.card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 3px 10px rgba(0,0,0,.08);
}
.component-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}
.comp-tab {
    padding: 12px 22px;
    border-radius: 6px;
    font-weight: bold;
    font-size: 14px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}
.comp-tab.active {
    background: #1f4e79;
    color: #fff;
    box-shadow: 0 2px 6px rgba(31,78,121,0.3);
}
.comp-tab.inactive {
    background: #e2e8f0;
    color: #333;
}
.comp-tab.inactive:hover {
    background: #cbd5e1;
}
table {
    width: 100%;
    border-collapse: collapse;
    min-width: 800px;
}
th {
    background: #1f4e79;
    color: white;
    padding: 12px;
    text-align: left;
}
td {
    padding: 12px;
    border-bottom: 1px solid #eee;
}
input[type="number"] {
    width: 100px;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 5px;
    font-size: 14px;
}
.absent { color: #a12622; }
button {
    margin-top: 20px;
    padding: 12px 24px;
    border: 0;
    border-radius: 6px;
    background: #1f4e79;
    color: white;
    font-weight: bold;
    font-size: 14px;
    cursor: pointer;
}
button:hover { background: #173b5c; }
@media(max-width:900px) {
    .info-grid { grid-template-columns: repeat(2, 1fr); }
}
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

<a class="back" href="../batch.php?batch_id=<?php echo $batchId; ?>&id=<?php echo $facultyId; ?>#subject-<?php echo $subjectId; ?>">
    ← Back to Batch Subjects
</a>

<h1>Mid-Term Marks Entry</h1>
<p style="color:#666; margin-bottom:20px;">
    Enter Mid-Term assessment marks for this subject component.
</p>

<?php if ($success): ?>
<div class="success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<?php if ($error): ?>
<div class="error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($assignment): ?>

    <!-- COMPONENT TABS (THEORY / PRACTICAL) -->
    <?php if (count($mappedPapers) > 1): ?>
        <div class="component-tabs">
            <?php foreach ($mappedPapers as $mp): ?>
                <?php $isActive = ((int)$mp['paper_id'] === $paperId); ?>
                <a href="?batch_id=<?php echo $batchId; ?>&subject_id=<?php echo $subjectId; ?>&faculty_id=<?php echo $facultyId; ?>&paper_id=<?php echo $mp['paper_id']; ?>"
                   class="comp-tab <?php echo $isActive ? 'active' : 'inactive'; ?>">
                    <span><?php echo htmlspecialchars($mp['paper_type']); ?>: [<?php echo htmlspecialchars($mp['paper_code']); ?>] <?php echo htmlspecialchars($mp['paper_name']); ?></span>
                    <span style="font-size:11px; opacity:0.85;">(Max: <?php echo number_format((float)(!empty($mp['subject_max_midterm']) ? $mp['subject_max_midterm'] : $mp['max_midterm']), 2); ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="info">
        <div class="info-grid">
            <div class="info-box">
                <small>Faculty</small>
                <strong><?php echo htmlspecialchars($faculty['faculty_name']); ?></strong>
            </div>
            <div class="info-box">
                <small>Batch</small>
                <strong><?php echo htmlspecialchars($assignment['batch_name']); ?></strong>
            </div>
            <div class="info-box">
                <small>Subject</small>
                <strong><?php echo htmlspecialchars($assignment['subject_code']); ?> - <?php echo htmlspecialchars($assignment['subject_name']); ?></strong>
            </div>
            <div class="info-box">
                <small>Component / Max Marks</small>
                <strong>
                    <?php if ($currentPaper): ?>
                        <?php echo htmlspecialchars($currentPaper['paper_type']); ?> (Max: <?php echo number_format($maxMarks, 2); ?>)
                    <?php else: ?>
                        Max: <?php echo number_format($maxMarks, 2); ?>
                    <?php endif; ?>
                </strong>
            </div>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="paper_id" value="<?php echo (int)$paperId; ?>">
        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Roll No</th>
                        <th>Student Name</th>
                        <th>Group</th>
                        <th>Marks Obtained (Max: <?php echo number_format($maxMarks, 2); ?>)</th>
                        <th>Absent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach ($students as $student): ?>
                        <?php
                            $sId = (int)$student['student_id'];
                            $existing = $existingMarks[$sId] ?? null;
                            $val = ($existing && $existing['marks_obtained'] !== null) ? $existing['marks_obtained'] : '';
                            $isAbsent = ($existing && (int)$existing['is_absent'] === 1);
                        ?>
                        <tr>
                            <td><?php echo $i++; ?></td>
                            <td style="font-weight:bold; color:#1f4e79;"><?php echo htmlspecialchars($student['roll_no']); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($student['student_name']); ?></strong>
                                <?php if (!empty($student['designation'])): ?>
                                    <br><small style="color:#777;"><?php echo htmlspecialchars($student['designation']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($student['group_code'] ?? '—'); ?></td>
                            <td>
                                <input type="number" step="0.01" min="0" max="<?php echo $maxMarks; ?>"
                                       name="marks[<?php echo $sId; ?>]"
                                       value="<?php echo htmlspecialchars($val); ?>"
                                       placeholder="0.00">
                            </td>
                            <td>
                                <label style="display:flex; align-items:center; gap:6px; cursor:pointer;" class="absent">
                                    <input type="checkbox" name="absent[<?php echo $sId; ?>]" value="1" <?php echo $isAbsent ? 'checked' : ''; ?>>
                                    Absent
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="display:flex; gap:12px; align-items:center;">
                <button type="submit">Save Mid-Term Marks</button>
                <a href="../batch.php?batch_id=<?php echo $batchId; ?>&id=<?php echo $facultyId; ?>#subject-<?php echo $subjectId; ?>" style="margin-top:20px; padding:12px 18px; border:1px solid #ccc; background:#fff; color:#333; text-decoration:none; border-radius:6px; font-weight:bold; display:inline-block;">Cancel</a>
            </div>
        </div>
    </form>

<?php endif; ?>

</main>
</div>
</body>
</html>
