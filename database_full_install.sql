-- Self-contained FTC Grading database installation script.
-- ==========================================
-- FTC GRADING & TABULATION SYSTEM
-- COMPLETE DATABASE SCHEMA
-- ==========================================
-- Database: ftc_grading
-- Engine: MySQL / MariaDB
-- Tested with MariaDB 10.4.x
--
-- IMPORTANT:
-- This is a REBUILD schema.
-- It drops existing tables/views before recreating them.
-- DO NOT run this on a database containing data you want to keep.
-- ==========================================



-- ==========================================
-- 1. CLEAN REINITIALIZATION
-- ==========================================

SET FOREIGN_KEY_CHECKS = 0;


-- Views

DROP VIEW IF EXISTS v_result_register;
DROP VIEW IF EXISTS v_tabulation;
DROP VIEW IF EXISTS v_internal_marks_converter;
DROP VIEW IF EXISTS v_paper_assessment_scores;
DROP VIEW IF EXISTS v_subject_assessment_scores;


-- Tables

DROP TABLE IF EXISTS admin_marks_audit;
DROP TABLE IF EXISTS component_marks;
DROP TABLE IF EXISTS assessment_components;
DROP TABLE IF EXISTS faculty_marks_entry;
DROP TABLE IF EXISTS assessment_types;
DROP TABLE IF EXISTS subject_faculty_mapping;
DROP TABLE IF EXISTS faculties;
DROP TABLE IF EXISTS paper_subject_mapping;
DROP TABLE IF EXISTS elective_subject_mapping;
DROP TABLE IF EXISTS subjects;
DROP TABLE IF EXISTS papers;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS elective_groups;
DROP TABLE IF EXISTS batches;


SET FOREIGN_KEY_CHECKS = 1;


-- ==========================================
-- 2. CORE ENTITY TABLES
-- ==========================================


-- ==========================================
-- A. BATCHES
-- ==========================================

CREATE TABLE batches (

    batch_id INT AUTO_INCREMENT PRIMARY KEY,

    batch_name VARCHAR(100) NOT NULL UNIQUE,

    commenced_date DATE NOT NULL,

    completed_date DATE NOT NULL,

    station VARCHAR(50) NOT NULL DEFAULT 'DELHI',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    updated_at TIMESTAMP
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT chk_dates
        CHECK (completed_date >= commenced_date)

) ENGINE=InnoDB;


-- ==========================================
-- B. ELECTIVE GROUPS
-- ==========================================

CREATE TABLE elective_groups (

    group_code CHAR(1) PRIMARY KEY,

    group_description VARCHAR(255) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB;


-- ==========================================
-- C. STUDENTS
-- ==========================================
-- Includes:
-- email
-- contact_number
-- soft delete
-- ==========================================

CREATE TABLE students (

    student_id INT AUTO_INCREMENT PRIMARY KEY,

    roll_no VARCHAR(50) NOT NULL UNIQUE,

    student_name VARCHAR(150) NOT NULL,

    designation VARCHAR(100),

    qualification VARCHAR(150),

    rmc VARCHAR(100),

    batch_id INT NOT NULL,

    group_code CHAR(1) NULL,

    custom_remarks VARCHAR(500) NULL,

    is_deleted BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    email VARCHAR(100) NULL,

    contact_number VARCHAR(20) NULL,

    FOREIGN KEY (batch_id)
        REFERENCES batches(batch_id)
        ON DELETE RESTRICT,

    FOREIGN KEY (group_code)
        REFERENCES elective_groups(group_code)
        ON DELETE RESTRICT

) ENGINE=InnoDB;


-- ==========================================
-- D. PAPERS
-- ==========================================

CREATE TABLE papers (

    paper_id INT AUTO_INCREMENT PRIMARY KEY,

    paper_code VARCHAR(10) NOT NULL UNIQUE,

    paper_name VARCHAR(150) NOT NULL,

    is_elective BOOLEAN DEFAULT FALSE,

    paper_type VARCHAR(50) NOT NULL DEFAULT 'Theory',

    max_internal DECIMAL(5,2)
        NOT NULL DEFAULT 30.00,

    max_midterm DECIMAL(5,2)
        NOT NULL DEFAULT 25.00,

    max_final DECIMAL(5,2)
        NOT NULL DEFAULT 45.00,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_paper_weightages
        CHECK (
            max_internal >= 0
            AND max_midterm >= 0
            AND max_final >= 0
        )

) ENGINE=InnoDB;


-- ==========================================
-- E. SUBJECTS
-- ==========================================

CREATE TABLE subjects (

    subject_id INT AUTO_INCREMENT PRIMARY KEY,

    subject_code VARCHAR(15) NOT NULL UNIQUE,

    subject_name VARCHAR(150) NOT NULL,

    is_elective BOOLEAN DEFAULT FALSE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP

) ENGINE=InnoDB;


-- ==========================================
-- F. FACULTIES
-- ==========================================
-- Includes today's additions:
-- contact_number
-- office_location
-- is_deleted
-- ==========================================

CREATE TABLE faculties (

    faculty_id INT AUTO_INCREMENT PRIMARY KEY,

    faculty_name VARCHAR(150) NOT NULL,

    email VARCHAR(100) UNIQUE,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    contact_number VARCHAR(20) NULL,

    office_location VARCHAR(150) NULL,

    is_deleted BOOLEAN NOT NULL DEFAULT FALSE

) ENGINE=InnoDB;


-- ==========================================
-- G. ASSESSMENT TYPES
-- ==========================================

CREATE TABLE assessment_types (

    assessment_type_id INT PRIMARY KEY,

    assessment_type_name VARCHAR(50)
        NOT NULL UNIQUE

) ENGINE=InnoDB;


-- ==========================================
-- H. ASSESSMENT COMPONENTS
-- ==========================================

CREATE TABLE assessment_components (

    component_id INT AUTO_INCREMENT PRIMARY KEY,

    paper_id INT NOT NULL,

    component_name VARCHAR(100) NOT NULL,

    max_marks DECIMAL(5,2) NOT NULL,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (paper_id)
        REFERENCES papers(paper_id)
        ON DELETE CASCADE,

    UNIQUE (
        paper_id,
        component_name
    )

) ENGINE=InnoDB;


-- ==========================================
-- 3. RELATIONSHIP / MAPPING TABLES
-- ==========================================


-- ==========================================
-- I. ELECTIVE SUBJECT MAPPING
-- ==========================================

CREATE TABLE elective_subject_mapping (

    group_code CHAR(1) NOT NULL,

    subject_id INT NOT NULL,

    PRIMARY KEY (
        group_code,
        subject_id
    ),

    FOREIGN KEY (group_code)
        REFERENCES elective_groups(group_code)
        ON DELETE CASCADE,

    FOREIGN KEY (subject_id)
        REFERENCES subjects(subject_id)
        ON DELETE CASCADE

) ENGINE=InnoDB;


-- ==========================================
-- J. PAPER SUBJECT MAPPING
-- ==========================================

CREATE TABLE paper_subject_mapping (

    paper_id INT NOT NULL,

    subject_id INT NOT NULL,

    batch_id INT NOT NULL,

    subject_weightage_pct
        DECIMAL(5,2)
        NOT NULL DEFAULT 100.00,

    subject_max_internal
        DECIMAL(5,2)
        NULL DEFAULT 0.00,

    subject_max_midterm
        DECIMAL(5,2)
        NULL DEFAULT 0.00,

    subject_max_final
        DECIMAL(5,2)
        NULL DEFAULT 0.00,

    PRIMARY KEY (
        paper_id,
        subject_id,
        batch_id
    ),

    FOREIGN KEY (paper_id)
        REFERENCES papers(paper_id)
        ON DELETE RESTRICT,

    FOREIGN KEY (subject_id)
        REFERENCES subjects(subject_id)
        ON DELETE RESTRICT,

    FOREIGN KEY (batch_id)
        REFERENCES batches(batch_id)
        ON DELETE RESTRICT,

    CONSTRAINT chk_weightage
        CHECK (
            subject_weightage_pct > 0
            AND subject_weightage_pct <= 100
        )

) ENGINE=InnoDB;


-- ==========================================
-- K. SUBJECT FACULTY MAPPING
-- ==========================================
-- Defines which faculty evaluates which
-- subject for which batch.
-- ==========================================

CREATE TABLE subject_faculty_mapping (

    batch_id INT NOT NULL,

    subject_id INT NOT NULL,

    faculty_id INT NOT NULL,

    max_marks DECIMAL(5,2) NOT NULL DEFAULT 100.00,

    PRIMARY KEY (
        batch_id,
        subject_id,
        faculty_id
    ),

    FOREIGN KEY (batch_id)
        REFERENCES batches(batch_id)
        ON DELETE RESTRICT,

    FOREIGN KEY (subject_id)
        REFERENCES subjects(subject_id)
        ON DELETE RESTRICT,

    FOREIGN KEY (faculty_id)
        REFERENCES faculties(faculty_id)
        ON DELETE RESTRICT

) ENGINE=InnoDB;


-- ==========================================
-- 4. MARKS TABLES
-- ==========================================


-- ==========================================
-- L. FACULTY MARKS ENTRY
-- ==========================================

CREATE TABLE faculty_marks_entry (

    entry_id INT AUTO_INCREMENT PRIMARY KEY,

    student_id INT NOT NULL,

    subject_id INT NOT NULL,

    faculty_id INT NOT NULL,

    paper_id INT NULL,

    assessment_type_id INT NOT NULL,

    marks_obtained DECIMAL(5,2) NULL,

    is_absent BOOLEAN DEFAULT FALSE,

    graded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (student_id)
        REFERENCES students(student_id)
        ON DELETE CASCADE,

    FOREIGN KEY (subject_id)
        REFERENCES subjects(subject_id)
        ON DELETE CASCADE,

    FOREIGN KEY (faculty_id)
        REFERENCES faculties(faculty_id)
        ON DELETE CASCADE,

    FOREIGN KEY (paper_id)
        REFERENCES papers(paper_id)
        ON DELETE SET NULL,

    FOREIGN KEY (assessment_type_id)
        REFERENCES assessment_types(assessment_type_id)
        ON DELETE RESTRICT,

    UNIQUE KEY (
        student_id,
        subject_id,
        faculty_id,
        assessment_type_id,
        paper_id
    )

) ENGINE=InnoDB;


-- ==========================================
-- M. ADMIN MARKS AUDIT
-- ==========================================
-- Added during today's work.
-- Tracks Admin changes to Internal marks.
-- ==========================================

CREATE TABLE admin_marks_audit (

    audit_id INT AUTO_INCREMENT PRIMARY KEY,

    entry_id INT NOT NULL,

    student_id INT NOT NULL,

    subject_id INT NOT NULL,

    old_marks DECIMAL(5,2) NULL,

    new_marks DECIMAL(5,2) NULL,

    admin_name VARCHAR(150) NULL,

    reason VARCHAR(500) NULL,

    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (entry_id)
        REFERENCES faculty_marks_entry(entry_id)
        ON DELETE CASCADE,

    FOREIGN KEY (student_id)
        REFERENCES students(student_id)
        ON DELETE CASCADE,

    FOREIGN KEY (subject_id)
        REFERENCES subjects(subject_id)
        ON DELETE CASCADE

) ENGINE=InnoDB;


-- ==========================================
-- N. COMPONENT MARKS
-- ==========================================

CREATE TABLE component_marks (

    student_id INT NOT NULL,

    component_id INT NOT NULL,

    marks_obtained DECIMAL(5,2) NULL,

    is_absent BOOLEAN DEFAULT FALSE,

    graded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (
        student_id,
        component_id
    ),

    FOREIGN KEY (student_id)
        REFERENCES students(student_id)
        ON DELETE CASCADE,

    FOREIGN KEY (component_id)
        REFERENCES assessment_components(component_id)
        ON DELETE CASCADE

) ENGINE=InnoDB;


-- ==========================================
-- 5. INDEXES
-- ==========================================

CREATE INDEX idx_fme_student
    ON faculty_marks_entry(student_id);

CREATE INDEX idx_fme_subject
    ON faculty_marks_entry(subject_id);

CREATE INDEX idx_fme_assessment
    ON faculty_marks_entry(assessment_type_id);

CREATE INDEX idx_students_batch
    ON students(batch_id);

CREATE INDEX idx_students_deleted
    ON students(is_deleted);

CREATE INDEX idx_faculties_deleted
    ON faculties(is_deleted);

CREATE INDEX idx_batches_station
    ON batches(station);

CREATE INDEX idx_sfm_faculty
    ON subject_faculty_mapping(faculty_id);

CREATE INDEX idx_sfm_batch
    ON subject_faculty_mapping(batch_id);


-- ==========================================
-- 6. SEED LOOKUPS
-- ==========================================


INSERT INTO assessment_types
(
    assessment_type_id,
    assessment_type_name
)
VALUES

(1, 'Internal'),

(2, 'Mid-Term'),

(3, 'Final Exam');


INSERT INTO elective_groups
(
    group_code,
    group_description
)
VALUES

(
    'a',
    'Group A - Physical Oceanography'
),

(
    'b',
    'Group B - Darmet & Radar Meteorology'
),

(
    'c',
    'Group C - Statistics & Computers'
);



-- ==========================================
-- IMD RESEARCH & GUIDE PORTAL - DATABASE SCHEMA (DDL)
-- ==========================================
-- Dialect: MySQL / MariaDB (Standard SQL Compatible)

-- 1. DISABLE FOREIGN KEY CHECKS FOR SAFE INITIALIZATION
SET FOREIGN_KEY_CHECKS = 0;

-- Drop Stored Procedures (To safely allow re-creation/updates without dropping user data)
DROP PROCEDURE IF EXISTS sp_register_user;
DROP PROCEDURE IF EXISTS sp_approve_user_registration;
DROP PROCEDURE IF EXISTS sp_reject_user_registration;
DROP PROCEDURE IF EXISTS sp_update_user_profile;
DROP PROCEDURE IF EXISTS sp_create_dept_admin;

SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================
-- 2. CREATE CORE ENTITY TABLES (PRESERVE EXISTING USER DATA)
-- ==========================================

-- A. ROLES TABLE
CREATE TABLE IF NOT EXISTS roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed Roles (1: guide, 2: dept_admin, 3: super_admin)
INSERT IGNORE INTO roles (role_id, role_name, description) VALUES
(1, 'guide', 'IMD Scientist / Faculty Supervisor'),
(2, 'dept_admin', 'Departmental Administrator'),
(3, 'super_admin', 'Super Administrator / System Director');

-- B. USERS TABLE
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    account_status ENUM('pending_approval', 'active', 'rejected', 'suspended') NOT NULL DEFAULT 'pending_approval',
    rejection_reason TEXT NULL,
    failed_login_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    locked_until DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_roles FOREIGN KEY (role_id) REFERENCES roles (role_id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- C. PROFILES TABLE
CREATE TABLE IF NOT EXISTS profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NULL,
    address TEXT NULL,
    dob DATE NULL,
    department VARCHAR(100) NULL,
    photo_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_profiles_users FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- D. OTP VERIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS otp_verifications (
    otp_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    otp_code_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    is_used TINYINT(1) NOT NULL DEFAULT 0,
    otp_last_sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_otp_users FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- E. AUDIT LOGS TABLE
CREATE TABLE IF NOT EXISTS audit_logs (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_users FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES
('director_name', 'Arya Pawar'),
('director_name_hindi', 'αñåαñ░αÑìαñ»αñ╛ αñ¬αñ╡αñ╛αñ░');

CREATE TABLE IF NOT EXISTS permissions (
    permission_id INT AUTO_INCREMENT PRIMARY KEY,
    permission_name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO permissions (permission_name, description) VALUES
('marks.enter', 'Enter and submit faculty marks'),
('marks.view', 'View marks and audit data'),
('marks.edit', 'Edit or override marks'),
('results.view', 'View and generate results'),
('results.download', 'Download result documents'),
('admin.access', 'Access administrative pages'),
('verification.access', 'Access verification pages'),
('users.manage', 'Approve and manage user accounts');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r CROSS JOIN permissions p
WHERE r.role_name IN ('dept_admin','super_admin','admin');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r CROSS JOIN permissions p
WHERE r.role_name = 'guide' AND p.permission_name IN ('marks.enter','marks.view','results.view');
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id FROM roles r CROSS JOIN permissions p
WHERE r.role_name = 'verification' AND p.permission_name IN ('marks.view','verification.access');

-- ==========================================
-- 3. CREATE INDEXES FOR OPTIMIZATION
-- ==========================================
-- Ignore errors if indexes already exist
SET @exist := (SELECT COUNT(*) FROM information_schema.statistics WHERE table_name = 'users' AND table_schema = DATABASE() AND index_name = 'idx_users_email');
SET @sqlstmt := IF(@exist = 0, 'CREATE INDEX idx_users_email ON users(email)', 'SELECT 1');
PREPARE stmt FROM @sqlstmt;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ==========================================
-- 4. SEED INITIAL SUPER ADMIN USER (SAFE INSERT)
-- ==========================================
-- Default Super Admin (email: aryaaa.pawar@gmail.com, pass: 12345678)
INSERT IGNORE INTO users (user_id, email, password_hash, role_id, account_status, created_at) VALUES
(1, 'aryaaa.pawar@gmail.com', '$2y$10$0YrhcvTP6bcXMymn49UqvOCI.dI/XRNRUIAzOrGu.NlIaGwCs.NM6', 3, 'active', NOW());

INSERT IGNORE INTO profiles (user_id, full_name, phone, department) VALUES
(1, 'Director IMD', '011-24612345', 'Executive Division');

-- ==========================================
-- 5. STORED PROCEDURES
-- ==========================================

DELIMITER //

-- Procedure 1: Register User (Atomic insertion into users, profiles, and audit_logs)
CREATE PROCEDURE sp_register_user(
    IN p_email VARCHAR(150),
    IN p_password_hash VARCHAR(255),
    IN p_role_name VARCHAR(50),
    IN p_full_name VARCHAR(150),
    IN p_phone VARCHAR(20),
    IN p_department VARCHAR(100),
    IN p_ip_address VARCHAR(45),
    OUT p_new_user_id INT
)
BEGIN
    DECLARE v_role_id INT;
    
    -- Resolve Role ID
    SELECT role_id INTO v_role_id FROM roles WHERE role_name = p_role_name LIMIT 1;
    IF v_role_id IS NULL THEN
        SET v_role_id = 1; -- Default to 'guide' if role not found
    END IF;

    -- Insert User
    INSERT INTO users (email, password_hash, role_id, account_status, created_at)
    VALUES (p_email, p_password_hash, v_role_id, 'pending_approval', NOW());
    
    SET p_new_user_id = LAST_INSERT_ID();

    -- Insert Profile
    INSERT INTO profiles (user_id, full_name, phone, department)
    VALUES (p_new_user_id, p_full_name, p_phone, p_department);

    -- Log Audit Event
    INSERT INTO audit_logs (user_id, action, ip_address, created_at)
    VALUES (p_new_user_id, 'REGISTRATION_SUBMITTED', p_ip_address, NOW());
END //

-- Procedure 2: Approve User Registration
CREATE PROCEDURE sp_approve_user_registration(
    IN p_target_user_id INT,
    IN p_admin_user_id INT,
    IN p_ip_address VARCHAR(45)
)
BEGIN
    UPDATE users 
    SET account_status = 'active', rejection_reason = NULL 
    WHERE user_id = p_target_user_id;

    INSERT INTO audit_logs (user_id, action, ip_address, created_at)
    VALUES (p_admin_user_id, CONCAT('ACCOUNT_APPROVED (User ID: ', p_target_user_id, ')'), p_ip_address, NOW());
END //

-- Procedure 3: Reject User Registration
CREATE PROCEDURE sp_reject_user_registration(
    IN p_target_user_id INT,
    IN p_rejection_reason TEXT,
    IN p_admin_user_id INT,
    IN p_ip_address VARCHAR(45)
)
BEGIN
    UPDATE users 
    SET account_status = 'rejected', rejection_reason = p_rejection_reason 
    WHERE user_id = p_target_user_id;

    INSERT INTO audit_logs (user_id, action, ip_address, created_at)
    VALUES (p_admin_user_id, CONCAT('ACCOUNT_REJECTED (User ID: ', p_target_user_id, ')'), p_ip_address, NOW());
END //

-- Procedure 4: Update User Profile
CREATE PROCEDURE sp_update_user_profile(
    IN p_user_id INT,
    IN p_full_name VARCHAR(150),
    IN p_phone VARCHAR(20),
    IN p_address TEXT,
    IN p_dob DATE,
    IN p_department VARCHAR(100),
    IN p_photo_path VARCHAR(255),
    IN p_ip_address VARCHAR(45)
)
BEGIN
    IF p_photo_path IS NOT NULL AND p_photo_path != '' THEN
        UPDATE profiles 
        SET full_name = p_full_name,
            phone = p_phone,
            address = p_address,
            dob = p_dob,
            department = p_department,
            photo_path = p_photo_path
        WHERE user_id = p_user_id;
    ELSE
        UPDATE profiles 
        SET full_name = p_full_name,
            phone = p_phone,
            address = p_address,
            dob = p_dob,
            department = p_department
        WHERE user_id = p_user_id;
    END IF;

    INSERT INTO audit_logs (user_id, action, ip_address, created_at)
    VALUES (p_user_id, 'PROFILE_UPDATED', p_ip_address, NOW());
END //

-- Procedure 5: Create Department Admin Account
CREATE PROCEDURE sp_create_dept_admin(
    IN p_email VARCHAR(150),
    IN p_password_hash VARCHAR(255),
    IN p_full_name VARCHAR(150),
    IN p_admin_user_id INT,
    IN p_ip_address VARCHAR(45),
    OUT p_new_user_id INT
)
BEGIN
    DECLARE v_dept_admin_role_id INT;

    SELECT role_id INTO v_dept_admin_role_id FROM roles WHERE role_name = 'dept_admin' LIMIT 1;
    IF v_dept_admin_role_id IS NULL THEN
        SET v_dept_admin_role_id = 2;
    END IF;

    INSERT INTO users (email, password_hash, role_id, account_status, created_at)
    VALUES (p_email, p_password_hash, v_dept_admin_role_id, 'active', NOW());

    SET p_new_user_id = LAST_INSERT_ID();

    INSERT INTO profiles (user_id, full_name, department)
    VALUES (p_new_user_id, p_full_name, 'Department Admin');

    INSERT INTO audit_logs (user_id, action, ip_address, created_at)
    VALUES (p_admin_user_id, CONCAT('DEPT_ADMIN_CREATED (New User ID: ', p_new_user_id, ')'), p_ip_address, NOW());
END //

DELIMITER ;

SELECT * FROM users;
SELECT * FROM profiles;


-- Add a business-facing batch number while preserving existing batch IDs.
SET @has_batch_number := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'batches'
      AND column_name = 'batch_number'
);
SET @sql := IF(
    @has_batch_number = 0,
    'ALTER TABLE batches ADD COLUMN batch_number VARCHAR(50) NULL AFTER batch_id',
    'SELECT 1'
);
PREPARE batch_number_stmt FROM @sql;
EXECUTE batch_number_stmt;
DEALLOCATE PREPARE batch_number_stmt;

UPDATE batches
SET batch_number = CAST(batch_id AS CHAR)
WHERE batch_id IS NOT NULL
    AND (batch_number IS NULL OR TRIM(batch_number) = '');

SET @has_batch_number_index := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'batches'
      AND index_name = 'uq_batches_batch_number'
);
SET @sql := IF(
    @has_batch_number_index = 0,
    'ALTER TABLE batches ADD UNIQUE KEY uq_batches_batch_number (batch_number)',
    'SELECT 1'
);
PREPARE batch_number_index_stmt FROM @sql;
EXECUTE batch_number_index_stmt;
DEALLOCATE PREPARE batch_number_index_stmt;

-- Additive schema for the independent Edit Scrutiny Marks workflow.
CREATE TABLE IF NOT EXISTS scrutiny_cases (
    case_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    paper_id INT NULL,
    entry_id INT NOT NULL,
    flagged_by_user_id INT NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    status ENUM('awaiting_admin','awaiting_recheck','verified') NOT NULL DEFAULT 'awaiting_admin',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (entry_id) REFERENCES faculty_marks_entry(entry_id) ON DELETE CASCADE,
    FOREIGN KEY (flagged_by_user_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_scrutiny_cases_status (status, updated_at),
    INDEX idx_scrutiny_cases_target (student_id, subject_id, entry_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS scrutiny_mark_change_log (
    change_id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    entry_id INT NOT NULL,
    old_marks DECIMAL(5,2) NULL,
    new_marks DECIMAL(5,2) NOT NULL,
    changed_by_user_id INT NOT NULL,
    reason VARCHAR(1000) NOT NULL,
    action VARCHAR(50) NOT NULL DEFAULT 'scrutiny_correction',
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES scrutiny_cases(case_id) ON DELETE RESTRICT,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (entry_id) REFERENCES faculty_marks_entry(entry_id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by_user_id) REFERENCES users(user_id) ON DELETE RESTRICT,
    INDEX idx_scrutiny_log_case (case_id, changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS scrutiny_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    change_id INT NOT NULL,
    recipient_user_id INT NOT NULL,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    old_marks DECIMAL(5,2) NULL,
    new_marks DECIMAL(5,2) NOT NULL,
    status ENUM('awaiting_recheck','verified') NOT NULL DEFAULT 'awaiting_recheck',
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    verified_at DATETIME NULL,
    FOREIGN KEY (case_id) REFERENCES scrutiny_cases(case_id) ON DELETE CASCADE,
    FOREIGN KEY (change_id) REFERENCES scrutiny_mark_change_log(change_id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    INDEX idx_scrutiny_notifications_user (recipient_user_id, is_read, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Add a separate remarks field for scrutiny/verification users.
SET @has_scrutiny_remarks := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'students'
      AND column_name = 'scrutiny_remarks'
);
SET @sql := IF(
    @has_scrutiny_remarks = 0,
    'ALTER TABLE students ADD COLUMN scrutiny_remarks VARCHAR(500) NULL AFTER custom_remarks',
    'SELECT 1'
);
PREPARE scrutiny_remarks_stmt FROM @sql;
EXECUTE scrutiny_remarks_stmt;
DEALLOCATE PREPARE scrutiny_remarks_stmt;


-- Current verification notification schema migration.
ALTER TABLE students ADD COLUMN verification_status ENUM('correct','wrong') NULL, ADD COLUMN verification_notified_at DATETIME NULL;
CREATE TABLE admin_notifications (notification_id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, batch_id INT NOT NULL, entry_id INT NULL, verification_status ENUM('correct','wrong') NOT NULL, message VARCHAR(500) NOT NULL, is_read TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, CONSTRAINT fk_admin_notifications_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE, CONSTRAINT fk_admin_notifications_batch FOREIGN KEY (batch_id) REFERENCES batches(batch_id) ON DELETE CASCADE, INDEX idx_admin_notifications_read (is_read, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE faculty_marks_entry ADD COLUMN verification_status ENUM('correct','wrong') NULL, ADD COLUMN verification_notified_at DATETIME NULL;
CREATE TABLE correction_notifications (correction_notification_id INT AUTO_INCREMENT PRIMARY KEY, notification_id INT NOT NULL, entry_id INT NOT NULL, original_mark DECIMAL(5,2) NULL, corrected_mark DECIMAL(5,2) NULL, corrected_by_faculty_id INT NULL, sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, completed_at DATETIME NULL, UNIQUE KEY uq_correction_notification (notification_id), UNIQUE KEY uq_correction_entry (entry_id), CONSTRAINT fk_correction_notification_admin FOREIGN KEY (notification_id) REFERENCES admin_notifications(notification_id) ON DELETE CASCADE, CONSTRAINT fk_correction_notification_entry FOREIGN KEY (entry_id) REFERENCES faculty_marks_entry(entry_id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- FTC GRADING SYSTEM - COMPLETE TRIGGERS & PROCEDURES SUITE
-- ============================================================================

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
    modified_by VARCHAR(100) DEFAULT NULL,
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

-- ==========================================
-- FTC GRADING SYSTEM - DATABASE VIEWS
-- ==========================================
-- Dialect: MySQL / MariaDB
-- ==========================================

-- ==========================================
-- VIEW 1: SUBJECT ASSESSMENT SCORES
-- ==========================================
-- Averages marks from multiple faculties for
-- a student, subject and assessment type.
-- The averaged mark is rounded to the nearest whole mark.
-- ==========================================

CREATE OR REPLACE VIEW v_subject_assessment_scores AS
SELECT 
    fme.student_id,
    COALESCE(fme.paper_id, 0) AS paper_id,
    fme.subject_id,
    fme.assessment_type_id,

    CASE 
        WHEN COUNT(CASE WHEN fme.is_absent = 0 AND fme.marks_obtained IS NOT NULL THEN 1 END) > 0 
        THEN AVG(CASE WHEN fme.is_absent = 0 THEN fme.marks_obtained ELSE NULL END)
        ELSE 0
    END AS subject_score,

    MAX(
        CASE fme.assessment_type_id
            WHEN 1 THEN COALESCE(NULLIF(psm.subject_max_internal, 0), sfm.max_marks)
            WHEN 2 THEN COALESCE(NULLIF(psm.subject_max_midterm, 0), sfm.max_marks)
            WHEN 3 THEN COALESCE(NULLIF(psm.subject_max_final, 0), sfm.max_marks)
            ELSE sfm.max_marks
        END
    ) AS subject_max_marks

FROM faculty_marks_entry fme

JOIN subject_faculty_mapping sfm 
    ON fme.subject_id = sfm.subject_id 
   AND fme.faculty_id = sfm.faculty_id

JOIN students std 
    ON fme.student_id = std.student_id 
   AND std.batch_id = sfm.batch_id

LEFT JOIN paper_subject_mapping psm
    ON psm.paper_id = COALESCE(fme.paper_id, 0)
   AND psm.subject_id = fme.subject_id
   AND psm.batch_id = std.batch_id

GROUP BY 
    fme.student_id,
    COALESCE(fme.paper_id, 0),
    fme.subject_id,
    fme.assessment_type_id;


-- ==========================================
-- VIEW 2: PAPER ASSESSMENT SCORES
-- ==========================================
-- Converts subject-level scores to paper-level
-- scores using the paper weightage.
--
-- Internal  = 30
-- Mid-Term  = 25
-- Final     = 45
-- ==========================================

CREATE OR REPLACE VIEW v_paper_assessment_scores AS
SELECT 
    sas.student_id,
    psm.paper_id,
    sas.assessment_type_id,

    ROUND(
        SUM(sas.subject_score)
        /
        NULLIF(
            SUM(
                CASE sas.assessment_type_id
                    WHEN 1 THEN COALESCE(NULLIF(psm.subject_max_internal, 0), sas.subject_max_marks)
                    WHEN 2 THEN COALESCE(NULLIF(psm.subject_max_midterm, 0), sas.subject_max_marks)
                    WHEN 3 THEN COALESCE(NULLIF(psm.subject_max_final, 0), sas.subject_max_marks)
                    ELSE sas.subject_max_marks
                END
            ),
            0
        )
        *
        CASE
            WHEN sas.assessment_type_id = 1 THEN p.max_internal
            WHEN sas.assessment_type_id = 2 THEN p.max_midterm
            WHEN sas.assessment_type_id = 3 THEN p.max_final
        END,
        0
    ) AS paper_score,

    MAX(
        CASE 
            WHEN sas.subject_score IS NULL THEN 1
            ELSE 0
        END
    ) AS was_absent

FROM v_subject_assessment_scores sas

JOIN paper_subject_mapping psm 
    ON sas.subject_id = psm.subject_id
   AND sas.paper_id = psm.paper_id

JOIN papers p 
    ON psm.paper_id = p.paper_id

JOIN students std 
    ON sas.student_id = std.student_id 
   AND psm.batch_id = std.batch_id

GROUP BY 
    sas.student_id,
    sas.paper_id,
    psm.paper_id,
    sas.assessment_type_id;


-- ==========================================
-- VIEW 3: INTERNAL MARKS CONVERTER
-- ==========================================
-- Converts raw internal subject marks into
-- their weighted internal score.
-- ==========================================

CREATE OR REPLACE VIEW v_internal_marks_converter AS
SELECT 
    student_id,
    paper_id,
    subject_id,

    CASE 
        WHEN was_absent = 1 THEN NULL
        ELSE subject_score
    END AS converted_score

FROM
(
    SELECT 
        sas.student_id,
        psm.paper_id,
        sas.subject_id,

        ROUND(
            (sas.subject_score / sas.subject_max_marks)
            *
            (
                p.max_internal
                *
                psm.subject_weightage_pct
                / 100.0
            ),
            0
        ) AS subject_score,

        MAX(
            CASE 
                WHEN sas.subject_score IS NULL THEN 1
                ELSE 0
            END
        ) AS was_absent

    FROM v_subject_assessment_scores sas

    JOIN paper_subject_mapping psm 
        ON sas.subject_id = psm.subject_id
       AND sas.paper_id = psm.paper_id

    JOIN papers p 
        ON psm.paper_id = p.paper_id

    JOIN students std 
        ON sas.student_id = std.student_id 
       AND std.batch_id = psm.batch_id

    WHERE sas.assessment_type_id = 1

    GROUP BY
        sas.student_id,
        sas.paper_id,
        psm.paper_id,
        sas.subject_id,
        sas.subject_score,
        sas.subject_max_marks,
        p.max_internal,
        psm.subject_weightage_pct

) sub;


-- ==========================================
-- VIEW 4: TABULATION
-- ==========================================
-- Combines:
--
-- Internal Score
-- Mid-Term Score
-- Final Exam Score
--
-- into the final combined paper total.
-- ==========================================

CREATE OR REPLACE VIEW v_tabulation AS
    WITH ElectivePaperTotals AS
    (
        SELECT
            st.student_id,
            psm.paper_id,
            SUM(COALESCE(sas.subject_score, 0)) AS raw_obtained,
            SUM(
                CASE sas.assessment_type_id
                    WHEN 1 THEN COALESCE(NULLIF(psm.subject_max_internal, 0), p.max_internal * psm.subject_weightage_pct / 100.0)
                    WHEN 2 THEN COALESCE(NULLIF(psm.subject_max_midterm, 0), p.max_midterm * psm.subject_weightage_pct / 100.0)
                    WHEN 3 THEN COALESCE(NULLIF(psm.subject_max_final, 0), p.max_final * psm.subject_weightage_pct / 100.0)
                    ELSE 0
                END
            ) AS raw_maximum,
            MAX(CASE WHEN sas.subject_score IS NULL THEN 1 ELSE 0 END) AS was_absent
        FROM students st
        INNER JOIN paper_subject_mapping psm
            ON psm.batch_id = st.batch_id
        INNER JOIN papers p
            ON p.paper_id = psm.paper_id
           AND (
               p.is_elective = 1
               OR UPPER(p.paper_code) IN ('P5', 'P-V')
               OR UPPER(p.paper_name) LIKE '%ELECTIVE%'
           )
        INNER JOIN elective_subject_mapping esm
            ON esm.subject_id = psm.subject_id
           AND LOWER(TRIM(esm.group_code)) = LOWER(TRIM(st.group_code))
        LEFT JOIN v_subject_assessment_scores sas
            ON sas.student_id = st.student_id
           AND sas.subject_id = psm.subject_id
           AND sas.paper_id = psm.paper_id
           AND sas.assessment_type_id IS NOT NULL
        GROUP BY
            st.student_id,
            psm.paper_id
    ),
    PaperScores AS
    (
        SELECT
            vt.*,
            CASE
                WHEN (
                    p.is_elective = 1
                    OR UPPER(p.paper_code) IN ('P5', 'P-V')
                    OR UPPER(p.paper_name) LIKE '%ELECTIVE%'
                )
                     AND ept.raw_maximum > 0
                THEN ROUND(
                    (ept.raw_obtained / ept.raw_maximum)
                    * (p.max_internal + p.max_midterm + p.max_final),
                    0
                )
                ELSE vt.combined_total
            END AS normalized_combined_total
        FROM
        (
            SELECT
                s.student_id,
                s.roll_no,
                s.student_name,
                p.paper_id,
                p.paper_name,
                CASE WHEN v_int.was_absent = 1 THEN NULL ELSE COALESCE(v_int.paper_score, 0) END AS internal_score,
                CASE WHEN v_mid.was_absent = 1 THEN NULL ELSE COALESCE(v_mid.paper_score, 0) END AS midterm_score,
                CASE WHEN v_fin.was_absent = 1 THEN NULL ELSE COALESCE(v_fin.paper_score, 0) END AS final_exam_score,
                CASE
                    WHEN v_int.was_absent = 1
                      OR v_mid.was_absent = 1
                      OR v_fin.was_absent = 1
                    THEN NULL
                    ELSE COALESCE(v_int.paper_score, 0)
                       + COALESCE(v_mid.paper_score, 0)
                       + COALESCE(v_fin.paper_score, 0)
                END AS combined_total
            FROM students s
            JOIN (
                SELECT DISTINCT paper_id, batch_id
                FROM paper_subject_mapping
            ) batch_papers
                ON batch_papers.batch_id = s.batch_id
            JOIN papers p
                ON p.paper_id = batch_papers.paper_id
            LEFT JOIN v_paper_assessment_scores v_int
                ON s.student_id = v_int.student_id
               AND p.paper_id = v_int.paper_id
               AND v_int.assessment_type_id = 1
            LEFT JOIN v_paper_assessment_scores v_mid
                ON s.student_id = v_mid.student_id
               AND p.paper_id = v_mid.paper_id
               AND v_mid.assessment_type_id = 2
            LEFT JOIN v_paper_assessment_scores v_fin
                ON s.student_id = v_fin.student_id
               AND p.paper_id = v_fin.paper_id
               AND v_fin.assessment_type_id = 3
        ) vt
        JOIN papers p
            ON p.paper_id = vt.paper_id
        LEFT JOIN ElectivePaperTotals ept
            ON ept.student_id = vt.student_id
           AND ept.paper_id = vt.paper_id
    )
SELECT
    student_id,
        roll_no,
        student_name,
        paper_id,
        paper_name,
        internal_score,
        midterm_score,
        final_exam_score,
        normalized_combined_total AS combined_total
FROM PaperScores;


-- ==========================================
-- VIEW 5: RESULT REGISTER
-- ==========================================

CREATE OR REPLACE VIEW v_result_register AS
WITH StudentPaperTotals AS
(
    SELECT
        student_id,
        SUM(combined_total) AS grand_total,
        SUM(
            p.max_internal
            + p.max_midterm
            + p.max_final
        ) AS max_possible_marks
    FROM v_tabulation vt
    JOIN papers p
        ON vt.paper_id = p.paper_id
    GROUP BY student_id
)
SELECT
    s.student_id,
    s.roll_no,
    s.student_name,
    s.designation,
    s.rmc,
    spt.grand_total,
    spt.max_possible_marks,
    ROUND(
        (spt.grand_total / spt.max_possible_marks) * 100,
        2
    ) AS percentage,
    CASE
        WHEN ROUND((spt.grand_total / spt.max_possible_marks) * 100, 2) >= 90.00 THEN 'Outstanding'
        WHEN ROUND((spt.grand_total / spt.max_possible_marks) * 100, 2) >= 80.00 THEN 'Excellent'
        WHEN ROUND((spt.grand_total / spt.max_possible_marks) * 100, 2) >= 70.00 THEN 'Very good'
        WHEN ROUND((spt.grand_total / spt.max_possible_marks) * 100, 2) >= 60.00 THEN 'Good'
        WHEN ROUND((spt.grand_total / spt.max_possible_marks) * 100, 2) >= 50.00 THEN 'Passed'
        ELSE 'Failed'
    END AS remarks,
    s.custom_remarks
FROM students s
JOIN StudentPaperTotals spt
    ON s.student_id = spt.student_id;
