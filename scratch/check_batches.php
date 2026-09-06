<?php
require_once "config/database.php";
$stmt = $pdo->query("SHOW COLUMNS FROM batches");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    echo $r['Field'] . " | " . $r['Type'] . " | " . $r['Null'] . " | " . $r['Default'] . "\n";
}
