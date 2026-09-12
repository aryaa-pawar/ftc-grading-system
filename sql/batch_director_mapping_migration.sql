-- FTC Grading System: Batch Director Mapping Migration
-- Adds director_name and director_name_hindi to batches table

SET @db_name := DATABASE();

SET @sql := IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'batches' AND COLUMN_NAME = 'director_name'), 'ALTER TABLE batches ADD COLUMN director_name VARCHAR(255) NULL AFTER station', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'batches' AND COLUMN_NAME = 'director_name_hindi'), 'ALTER TABLE batches ADD COLUMN director_name_hindi VARCHAR(255) NULL AFTER director_name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'batches' AND COLUMN_NAME = 'director_signature_path'), 'ALTER TABLE batches ADD COLUMN director_signature_path VARCHAR(255) NULL AFTER director_name_hindi', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE batches SET director_name = 'K. N. Mohan' WHERE director_name IS NULL OR TRIM(director_name) = '';
UPDATE batches SET director_name_hindi = 'के.एन.मोहन' WHERE director_name_hindi IS NULL OR TRIM(director_name_hindi) = '';
