<?php

require_once __DIR__ . '/../config/database.php';

$pdo->exec('DROP TRIGGER IF EXISTS trg_check_passing_threshold');

$pdo->exec(<<<'SQL'
UPDATE students
SET custom_remarks = NULLIF(
    TRIM(
        REGEXP_REPLACE(
            COALESCE(custom_remarks, ''),
            '[[:space:]]*\\[Needs Re-exam in Subject ID [0-9]+\\]',
            ''
        )
    ),
    ''
)
WHERE custom_remarks LIKE '%Needs Re-exam in Subject ID%'
SQL);

echo "Generated re-exam remarks removed and passing-threshold trigger disabled.\n";