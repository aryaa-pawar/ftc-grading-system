-- Add a separate remarks field for scrutiny/verification users.
USE ftc_grading;

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