<?php
require_once __DIR__ . '/../config/database.php';

echo "=== DROPPING CONSTRAINT chk_paper_weightages ===\n";
try {
    $pdo->exec("ALTER TABLE papers DROP CONSTRAINT chk_paper_weightages");
    echo "Successfully dropped chk_paper_weightages.\n";
} catch (PDOException $e) {
    echo "Drop error: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE papers ADD CONSTRAINT chk_paper_weightages CHECK (max_internal >= 0 AND max_midterm >= 0 AND max_final >= 0)");
    echo "Successfully added flexible chk_paper_weightages constraint.\n";
} catch (PDOException $e) {
    echo "Add check error: " . $e->getMessage() . "\n";
}

echo "\n=== NEW SHOW CREATE TABLE papers ===\n";
$res = $pdo->query("SHOW CREATE TABLE papers")->fetch(PDO::FETCH_ASSOC);
echo $res['Create Table'] . "\n";
