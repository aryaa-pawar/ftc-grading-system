-- Add a business-facing batch number while preserving existing batch IDs.
USE ftc_grading;

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