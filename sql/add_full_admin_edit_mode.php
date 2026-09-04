<?php
require_once __DIR__ . '/../config/database.php';
$columns = $pdo->query('SHOW COLUMNS FROM admin_marks_audit')->fetchAll(PDO::FETCH_COLUMN);
$additions = ['batch_id'=>'INT NULL AFTER student_id','paper_id'=>'INT NULL AFTER batch_id','assessment_type_id'=>'INT NULL AFTER subject_id','faculty_id'=>'INT NULL AFTER assessment_type_id','old_is_absent'=>'TINYINT(1) NULL AFTER old_marks','new_is_absent'=>'TINYINT(1) NULL AFTER new_marks','edit_mode'=>'VARCHAR(30) NULL AFTER reason'];
foreach ($additions as $name => $definition) if (!in_array($name, $columns, true)) $pdo->exec("ALTER TABLE admin_marks_audit ADD COLUMN {$name} {$definition}");
echo "Full Admin audit columns verified.\n";
