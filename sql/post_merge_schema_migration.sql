-- FTC Grading System: additive post-merge schema migration
-- Review before execution. This file is intentionally not executed by the application.
-- Existing rows, marks, IDs, and batch status values are preserved.

SET @db_name := DATABASE();

SET @sql := IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'students' AND COLUMN_NAME = 'name_prefix'), 'ALTER TABLE students ADD COLUMN name_prefix VARCHAR(20) NULL DEFAULT ''Mr.'' AFTER student_name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'students' AND COLUMN_NAME = 'student_name_hindi'), 'ALTER TABLE students ADD COLUMN student_name_hindi VARCHAR(255) NULL AFTER name_prefix', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'students' AND COLUMN_NAME = 'generated_director_name'), 'ALTER TABLE students ADD COLUMN generated_director_name VARCHAR(255) NULL AFTER student_name_hindi', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'students' AND COLUMN_NAME = 'generated_director_name_hindi'), 'ALTER TABLE students ADD COLUMN generated_director_name_hindi VARCHAR(255) NULL AFTER generated_director_name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'admin_marks_audit' AND COLUMN_NAME = 'admin_user_id'), 'ALTER TABLE admin_marks_audit ADD COLUMN admin_user_id INT NULL AFTER student_id', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'students' AND COLUMN_NAME = 'certificate_generated_at'), 'ALTER TABLE students ADD COLUMN certificate_generated_at DATETIME NULL AFTER generated_director_name_hindi', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Do not guess a mapping for legacy Locked. Existing values are retained.
-- is_locked remains the enforcement flag used by marks-entry code.
-- Running and Result Declared are added because the current batch UI writes them.
ALTER TABLE batches
    MODIFY COLUMN status ENUM('Active', 'Completed', 'Locked', 'Running', 'Result Declared')
    NOT NULL DEFAULT 'Running';
