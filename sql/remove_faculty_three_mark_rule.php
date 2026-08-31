<?php

require_once __DIR__ . '/../config/database.php';

$pdo->exec('DROP TRIGGER IF EXISTS trg_faculty_marks_before_update');

echo "Faculty marks update restrictions removed; admin editor remains restricted.\n";