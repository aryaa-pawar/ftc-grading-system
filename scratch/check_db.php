<?php
require_once "config/database.php";
$stmt = $pdo->query("SHOW CREATE TABLE faculty_marks_entry");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo $row['Create Table'];
