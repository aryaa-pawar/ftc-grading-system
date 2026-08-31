-- ============================================================================
-- FTC GRADING SYSTEM - COMPLETE TRIGGERS & PROCEDURES SUITE
-- ============================================================================

USE ftc_grading;

-- ----------------------------------------------------------------------------
-- 1. SUPPORTING AUDIT & HISTORY TABLES
-- ----------------------------------------------------------------------------

-- Table to track student single-subject edit allowance
CREATE TABLE IF NOT EXISTS student_edit_history (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    edited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY (student_id, subject_id),
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Table to record complete change history for grade edits
CREATE TABLE IF NOT EXISTS marks_audit_log (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    assessment_type_id INT NOT NULL,
    old_marks DECIMAL(5,2),
    new_marks DECIMAL(5,2),
    modified_by VARCHAR(100) DEFAULT CURRENT_USER(),
    modified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- ----------------------------------------------------------------------------
-- 2. DROP EXISTING TRIGGERS & PROCEDURES FOR CLEAN RE-INITIALIZATION
-- ----------------------------------------------------------------------------

DROP TRIGGER IF EXISTS trg_faculty_marks_before_insert;
DROP TRIGGER IF EXISTS trg_faculty_marks_before_update;
DROP TRIGGER IF EXISTS trg_log_subject_edit_history;
DROP TRIGGER IF EXISTS trg_audit_faculty_marks_update;
DROP TRIGGER IF EXISTS trg_component_marks_before_insert;
DROP TRIGGER IF EXISTS trg_component_marks_before_update;
DROP TRIGGER IF EXISTS trg_check_elective_assignment;
DROP TRIGGER IF EXISTS trg_prevent_grading_deleted_student;
DROP TRIGGER IF EXISTS trg_check_passing_threshold;

DROP PROCEDURE IF EXISTS sp_upsert_faculty_mark;
DROP PROCEDURE IF EXISTS sp_edit_internal_mark;
DROP PROCEDURE IF EXISTS sp_generate_student_tabulation;
DROP PROCEDURE IF EXISTS sp_check_edit_eligibility;


-- ----------------------------------------------------------------------------
-- 3. ALL DATABASE TRIGGERS
-- ----------------------------------------------------------------------------

DELIMITER //

-- TRIGGER 1: Faculty Marks BEFORE INSERT Validation
-- Checks upper boundary limits and handles absent student flags
CREATE TRIGGER trg_faculty_marks_before_insert
BEFORE INSERT ON faculty_marks_entry
FOR EACH ROW
BEGIN
    DECLARE v_max_marks DECIMAL(5,2);
    DECLARE v_batch_id INT;

    SELECT batch_id INTO v_batch_id 
    FROM students 
    WHERE student_id = NEW.student_id;

    SELECT max_marks INTO v_max_marks 
    FROM subject_faculty_mapping 
    WHERE batch_id = v_batch_id 
      AND subject_id = NEW.subject_id 
      AND faculty_id = NEW.faculty_id;

    IF NEW.is_absent = 1 THEN
        SET NEW.marks_obtained = NULL;
    ELSE
        IF NEW.marks_obtained IS NOT NULL AND v_max_marks IS NOT NULL THEN
            IF NEW.marks_obtained < 0 OR NEW.marks_obtained > v_max_marks THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Error: Entered marks exceed maximum allowed marks for this faculty subject allocation.';
            END IF;
        END IF;
    END IF;
END//


-- TRIGGER 3: Log Subject Edit History AFTER UPDATE
-- Registers the student's 1-subject allowance usage
CREATE TRIGGER trg_log_subject_edit_history
AFTER UPDATE ON faculty_marks_entry
FOR EACH ROW
BEGIN
    IF OLD.assessment_type_id = 1 AND !(OLD.marks_obtained <=> NEW.marks_obtained) THEN
        INSERT INTO student_edit_history (student_id, subject_id)
        VALUES (NEW.student_id, NEW.subject_id)
        ON DUPLICATE KEY UPDATE edited_at = CURRENT_TIMESTAMP;
    END IF;
END//


-- TRIGGER 4: General Audit Log AFTER UPDATE
-- Logs old vs new marks into marks_audit_log for audit tracking
CREATE TRIGGER trg_audit_faculty_marks_update
AFTER UPDATE ON faculty_marks_entry
FOR EACH ROW
BEGIN
    IF !(OLD.marks_obtained <=> NEW.marks_obtained) THEN
        INSERT INTO marks_audit_log (
            student_id, 
            subject_id, 
            assessment_type_id, 
            old_marks, 
            new_marks
        )
        VALUES (
            OLD.student_id, 
            OLD.subject_id, 
            OLD.assessment_type_id, 
            OLD.marks_obtained, 
            NEW.marks_obtained
        );
    END IF;
END//


-- TRIGGER 5: Component Marks BEFORE INSERT
CREATE TRIGGER trg_component_marks_before_insert
BEFORE INSERT ON component_marks
FOR EACH ROW
BEGIN
    DECLARE v_max_marks DECIMAL(5,2);

    SELECT max_marks INTO v_max_marks 
    FROM assessment_components 
    WHERE component_id = NEW.component_id;

    IF NEW.is_absent = 1 THEN
        SET NEW.marks_obtained = NULL;
    ELSE
        IF NEW.marks_obtained IS NOT NULL AND v_max_marks IS NOT NULL THEN
            IF NEW.marks_obtained < 0 OR NEW.marks_obtained > v_max_marks THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Error: Component marks exceed the configured maximum allowed score.';
            END IF;
        END IF;
    END IF;
END//


-- TRIGGER 6: Component Marks BEFORE UPDATE
CREATE TRIGGER trg_component_marks_before_update
BEFORE UPDATE ON component_marks
FOR EACH ROW
BEGIN
    DECLARE v_max_marks DECIMAL(5,2);

    SELECT max_marks INTO v_max_marks 
    FROM assessment_components 
    WHERE component_id = NEW.component_id;

    IF NEW.is_absent = 1 THEN
        SET NEW.marks_obtained = NULL;
    ELSE
        IF NEW.marks_obtained IS NOT NULL AND v_max_marks IS NOT NULL THEN
            IF NEW.marks_obtained < 0 OR NEW.marks_obtained > v_max_marks THEN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Error: Component marks exceed the configured maximum allowed score.';
            END IF;
        END IF;
    END IF;
END//


-- TRIGGER 7: Check Elective Subject Stream Alignment
CREATE TRIGGER trg_check_elective_assignment
BEFORE INSERT ON faculty_marks_entry
FOR EACH ROW
BEGIN
    DECLARE v_student_group CHAR(1);
    DECLARE v_subject_group CHAR(1);
    DECLARE v_is_elective BOOLEAN;

    SELECT is_elective INTO v_is_elective 
    FROM subjects WHERE subject_id = NEW.subject_id;

    IF v_is_elective = TRUE THEN
        SELECT group_code INTO v_student_group 
        FROM students WHERE student_id = NEW.student_id;

        SELECT group_code INTO v_subject_group 
        FROM elective_subject_mapping WHERE subject_id = NEW.subject_id;

        IF v_student_group IS NULL OR v_student_group <> v_subject_group THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Error: Cannot enter marks for an elective subject outside the student assigned elective group.';
        END IF;
    END IF;
END//


-- TRIGGER 8: Prevent Grading Deleted/Inactive Trainees
CREATE TRIGGER trg_prevent_grading_deleted_student
BEFORE INSERT ON faculty_marks_entry
FOR EACH ROW
BEGIN
    DECLARE v_is_deleted BOOLEAN;

    SELECT is_deleted INTO v_is_deleted 
    FROM students WHERE student_id = NEW.student_id;

    IF v_is_deleted = TRUE THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Error: Cannot enter or update marks for a student who is marked as deleted/inactive.';
    END IF;
END//


-- TRIGGER 9: Auto-Flag Below 50% Threshold for Re-Exam
CREATE TRIGGER trg_check_passing_threshold
BEFORE INSERT ON faculty_marks_entry
FOR EACH ROW
BEGIN
    DECLARE v_max_marks DECIMAL(5,2);
    DECLARE v_batch_id INT;

    SELECT batch_id INTO v_batch_id FROM students WHERE student_id = NEW.student_id;
    
    SELECT max_marks INTO v_max_marks 
    FROM subject_faculty_mapping 
    WHERE batch_id = v_batch_id 
      AND subject_id = NEW.subject_id 
      AND faculty_id = NEW.faculty_id;

    IF NEW.assessment_type_id = 3 AND NEW.marks_obtained IS NOT NULL AND v_max_marks IS NOT NULL THEN
        IF (NEW.marks_obtained / v_max_marks) < 0.50 THEN
            IF INSTR(COALESCE((SELECT custom_remarks FROM students WHERE student_id = NEW.student_id), ''), CONCAT('[Needs Re-exam in Subject ID ', NEW.subject_id, ']')) = 0
               AND CHAR_LENGTH(COALESCE((SELECT custom_remarks FROM students WHERE student_id = NEW.student_id), '')) + CHAR_LENGTH(CONCAT(' [Needs Re-exam in Subject ID ', NEW.subject_id, ']')) <= 500 THEN
                UPDATE students
                SET custom_remarks = CONCAT(COALESCE(custom_remarks, ''), ' [Needs Re-exam in Subject ID ', NEW.subject_id, ']')
                WHERE student_id = NEW.student_id;
            END IF;
        END IF;
    END IF;
END//


-- ----------------------------------------------------------------------------
-- 4. ALL STORED PROCEDURES
-- ----------------------------------------------------------------------------

-- PROCEDURE 1: Upsert Faculty Mark (Insert if new, Update if exists)
CREATE PROCEDURE sp_upsert_faculty_mark(
    IN p_student_id INT,
    IN p_subject_id INT,
    IN p_faculty_id INT,
    IN p_assessment_type_id INT,
    IN p_marks_obtained DECIMAL(5,2),
    IN p_is_absent BOOLEAN
)
BEGIN
    INSERT INTO faculty_marks_entry (
        student_id, 
        subject_id, 
        faculty_id, 
        assessment_type_id, 
        marks_obtained, 
        is_absent
    )
    VALUES (
        p_student_id, 
        p_subject_id, 
        p_faculty_id, 
        p_assessment_type_id, 
        p_marks_obtained, 
        p_is_absent
    )
    ON DUPLICATE KEY UPDATE
        marks_obtained = VALUES(marks_obtained),
        is_absent = VALUES(is_absent),
        graded_at = CURRENT_TIMESTAMP;
END//


-- PROCEDURE 2: Edit Internal Marks (With Policy Safety Safeguards)
CREATE PROCEDURE sp_edit_internal_mark(
    IN p_student_id INT,
    IN p_subject_id INT,
    IN p_faculty_id INT,
    IN p_new_marks DECIMAL(5,2)
)
BEGIN
    UPDATE faculty_marks_entry
    SET marks_obtained = p_new_marks,
        graded_at = CURRENT_TIMESTAMP
    WHERE student_id = p_student_id
      AND subject_id = p_subject_id
      AND faculty_id = p_faculty_id
      AND assessment_type_id = 1;
END//


-- PROCEDURE 3: Generate Student Tabulation Scorecard
CREATE PROCEDURE sp_generate_student_tabulation(
    IN p_student_id INT
)
BEGIN
    SELECT 
        std.student_id,
        std.roll_no,
        std.student_name,
        sub.subject_code,
        sub.subject_name,
        MAX(CASE WHEN fme.assessment_type_id = 1 THEN fme.marks_obtained END) AS internal_marks,
        MAX(CASE WHEN fme.assessment_type_id = 2 THEN fme.marks_obtained END) AS midterm_marks,
        MAX(CASE WHEN fme.assessment_type_id = 3 THEN fme.marks_obtained END) AS final_marks
    FROM students std
    JOIN faculty_marks_entry fme ON std.student_id = fme.student_id
    JOIN subjects sub ON fme.subject_id = sub.subject_id
    WHERE std.student_id = p_student_id
    GROUP BY std.student_id, std.roll_no, std.student_name, sub.subject_code, sub.subject_name;
END//


-- PROCEDURE 4: UI Edit Allowance Checker
CREATE PROCEDURE sp_check_edit_eligibility(
    IN p_student_id INT,
    IN p_subject_id INT
)
BEGIN
    DECLARE v_edited_count INT;
    DECLARE v_is_this_subject_edited INT;

    SELECT COUNT(DISTINCT subject_id) INTO v_edited_count
    FROM student_edit_history WHERE student_id = p_student_id;

    SELECT COUNT(*) INTO v_is_this_subject_edited
    FROM student_edit_history WHERE student_id = p_student_id AND subject_id = p_subject_id;

    IF v_edited_count = 0 OR v_is_this_subject_edited > 0 THEN
        SELECT TRUE AS can_edit, 'Student is eligible for internal mark modification in this subject.' AS status_message;
    ELSE
        SELECT FALSE AS can_edit, 'Student has already used their 1-subject edit allowance on another subject.' AS status_message;
    END IF;
END//

DELIMITER ;