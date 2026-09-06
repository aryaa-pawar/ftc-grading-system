-- Required before enabling central user deletion.
-- Preserves historical scrutiny rows while allowing the users row to be removed.

SET @db_name := DATABASE();

SELECT CONSTRAINT_NAME INTO @fk_name
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'scrutiny_cases'
  AND COLUMN_NAME = 'flagged_by_user_id' AND REFERENCED_TABLE_NAME = 'users'
LIMIT 1;
SET @sql := IF(@fk_name IS NULL, 'SELECT 1', CONCAT('ALTER TABLE scrutiny_cases MODIFY COLUMN flagged_by_user_id INT NULL, DROP FOREIGN KEY `', @fk_name, '`, ADD CONSTRAINT fk_scrutiny_cases_flagged_by FOREIGN KEY (flagged_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CONSTRAINT_NAME INTO @fk_name
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'scrutiny_mark_change_log'
  AND COLUMN_NAME = 'changed_by_user_id' AND REFERENCED_TABLE_NAME = 'users'
LIMIT 1;
SET @sql := IF(@fk_name IS NULL, 'SELECT 1', CONCAT('ALTER TABLE scrutiny_mark_change_log MODIFY COLUMN changed_by_user_id INT NULL, DROP FOREIGN KEY `', @fk_name, '`, ADD CONSTRAINT fk_scrutiny_log_changed_by FOREIGN KEY (changed_by_user_id) REFERENCES users(user_id) ON DELETE SET NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT CONSTRAINT_NAME INTO @fk_name
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'scrutiny_notifications'
  AND COLUMN_NAME = 'recipient_user_id' AND REFERENCED_TABLE_NAME = 'users'
LIMIT 1;
SET @sql := IF(@fk_name IS NULL, 'SELECT 1', CONCAT('ALTER TABLE scrutiny_notifications MODIFY COLUMN recipient_user_id INT NULL, DROP FOREIGN KEY `', @fk_name, '`, ADD CONSTRAINT fk_scrutiny_notifications_recipient FOREIGN KEY (recipient_user_id) REFERENCES users(user_id) ON DELETE SET NULL'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
