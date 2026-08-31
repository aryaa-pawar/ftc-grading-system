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


CREATE DATABASE IF NOT EXISTS ftc_grading;

USE ftc_grading;


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
-- 7. SQL VIEWS
-- ==========================================


-- ==========================================
-- VIEW 1:
-- SUBJECT ASSESSMENT SCORES
-- ==========================================

CREATE OR REPLACE VIEW v_subject_assessment_scores AS

SELECT

    fme.student_id,

    fme.subject_id,

    fme.assessment_type_id,

    CASE

        WHEN MAX(fme.is_absent) = 1
            THEN NULL

        ELSE CEIL(
            AVG(fme.marks_obtained)
        )

    END AS subject_score,

    MAX(sfm.max_marks)
        AS subject_max_marks

FROM faculty_marks_entry fme

JOIN subject_faculty_mapping sfm

    ON fme.subject_id =
       sfm.subject_id

    AND fme.faculty_id =
        sfm.faculty_id

JOIN students std

    ON fme.student_id =
       std.student_id

    AND std.batch_id =
        sfm.batch_id

GROUP BY

    fme.student_id,

    fme.subject_id,

    fme.assessment_type_id;


-- ==========================================
-- VIEW 2:
-- PAPER ASSESSMENT SCORES
-- ==========================================

CREATE OR REPLACE VIEW v_paper_assessment_scores AS

SELECT

    sas.student_id,

    psm.paper_id,

    sas.assessment_type_id,

    ROUND(
        SUM(

            (
                sas.subject_score /
                sas.subject_max_marks
            )

            *

            (

                CASE

                    WHEN sas.assessment_type_id = 1
                        THEN p.max_internal

                    WHEN sas.assessment_type_id = 2
                        THEN p.max_midterm

                    WHEN sas.assessment_type_id = 3
                        THEN p.max_final

                END

                *

                (
                    psm.subject_weightage_pct
                    / 100.0
                )

            )

        ),
        0
    ) AS paper_score,

    MAX(
        CASE

            WHEN sas.subject_score IS NULL
                THEN 1

            ELSE 0

        END
    ) AS was_absent

FROM v_subject_assessment_scores sas

JOIN paper_subject_mapping psm

    ON sas.subject_id =
       psm.subject_id

JOIN papers p

    ON psm.paper_id =
       p.paper_id

JOIN students std

    ON sas.student_id =
       std.student_id

    AND psm.batch_id =
        std.batch_id

GROUP BY

    sas.student_id,

    psm.paper_id,

    sas.assessment_type_id;


-- ==========================================
-- VIEW 3:
-- INTERNAL MARKS CONVERTER
-- ==========================================

CREATE OR REPLACE VIEW v_internal_marks_converter AS

SELECT

    student_id,

    paper_id,

    subject_id,

    CASE

        WHEN was_absent = 1
            THEN NULL

        ELSE subject_score

    END AS converted_score

FROM

(

    SELECT

        sas.student_id,

        psm.paper_id,

        sas.subject_id,

        ROUND(

            (
                sas.subject_score /
                sas.subject_max_marks
            )

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

                WHEN sas.subject_score IS NULL
                    THEN 1

                ELSE 0

            END

        ) AS was_absent

    FROM v_subject_assessment_scores sas

    JOIN paper_subject_mapping psm

        ON sas.subject_id =
           psm.subject_id

    JOIN papers p

        ON psm.paper_id =
           p.paper_id

    JOIN students std

        ON sas.student_id =
           std.student_id

        AND std.batch_id =
            psm.batch_id

    WHERE sas.assessment_type_id = 1

    GROUP BY

        sas.student_id,

        psm.paper_id,

        sas.subject_id,

        sas.subject_score,

        sas.subject_max_marks,

        p.max_internal,

        psm.subject_weightage_pct

) sub;


-- ==========================================
-- VIEW 4:
-- TABULATION
-- ==========================================

CREATE OR REPLACE VIEW v_tabulation AS

SELECT

    s.student_id,

    s.roll_no,

    s.student_name,

    p.paper_id,

    p.paper_name,


    CASE

        WHEN v_int.was_absent = 1
            THEN NULL

        ELSE COALESCE(
            v_int.paper_score,
            0
        )

    END AS internal_score,


    CASE

        WHEN v_mid.was_absent = 1
            THEN NULL

        ELSE COALESCE(
            v_mid.paper_score,
            0
        )

    END AS midterm_score,


    CASE

        WHEN v_fin.was_absent = 1
            THEN NULL

        ELSE COALESCE(
            v_fin.paper_score,
            0
        )

    END AS final_exam_score,


    CASE

        WHEN
            v_int.was_absent = 1

            OR v_mid.was_absent = 1

            OR v_fin.was_absent = 1

        THEN NULL

        ELSE

            (
                COALESCE(
                    v_int.paper_score,
                    0
                )

                +

                COALESCE(
                    v_mid.paper_score,
                    0
                )

                +

                COALESCE(
                    v_fin.paper_score,
                    0
                )
            )

    END AS combined_total


FROM students s

CROSS JOIN papers p


LEFT JOIN v_paper_assessment_scores v_int

    ON s.student_id =
       v_int.student_id

    AND p.paper_id =
        v_int.paper_id

    AND v_int.assessment_type_id = 1


LEFT JOIN v_paper_assessment_scores v_mid

    ON s.student_id =
       v_mid.student_id

    AND p.paper_id =
        v_mid.paper_id

    AND v_mid.assessment_type_id = 2


LEFT JOIN v_paper_assessment_scores v_fin

    ON s.student_id =
       v_fin.student_id

    AND p.paper_id =
        v_fin.paper_id

    AND v_fin.assessment_type_id = 3;


-- ==========================================
-- VIEW 5:
-- RESULT REGISTER
-- ==========================================

CREATE OR REPLACE VIEW v_result_register AS

WITH StudentPaperTotals AS

(

    SELECT

        student_id,

        SUM(combined_total)
            AS grand_total,

        SUM(
            p.max_internal
            +
            p.max_midterm
            +
            p.max_final
        )
            AS max_possible_marks

    FROM v_tabulation vt

    JOIN papers p

        ON vt.paper_id =
           p.paper_id

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

        (
            spt.grand_total /
            spt.max_possible_marks
        )

        * 100,

        2

    ) AS percentage,


    CASE

        WHEN

            ROUND(
                (
                    spt.grand_total /
                    spt.max_possible_marks
                ) * 100,

                2

            ) >= 90.00

        THEN 'Outstanding'


        WHEN

            ROUND(
                (
                    spt.grand_total /
                    spt.max_possible_marks
                ) * 100,

                2

            ) >= 80.00

        THEN 'Excellent'


        WHEN

            ROUND(
                (
                    spt.grand_total /
                    spt.max_possible_marks
                ) * 100,

                2

            ) >= 70.00

        THEN 'Very good'


        WHEN

            ROUND(
                (
                    spt.grand_total /
                    spt.max_possible_marks
                ) * 100,

                2

            ) >= 60.00

        THEN 'Good'


        WHEN

            ROUND(
                (
                    spt.grand_total /
                    spt.max_possible_marks
                ) * 100,

                2

            ) >= 50.00

        THEN 'Passed'


        ELSE 'Failed'

    END AS remarks,


    s.custom_remarks


FROM students s

JOIN StudentPaperTotals spt

    ON s.student_id =
       spt.student_id;


-- ==========================================
-- END OF SCHEMA
-- ==========================================