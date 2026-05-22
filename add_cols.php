<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/functions.php';

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN joining_date DATE DEFAULT NULL");
    echo "Added joining_date.\n";
} catch (Exception $e) {
    echo "joining_date: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN base_salary DECIMAL(10,2) DEFAULT 0");
    echo "Added base_salary.\n";
} catch (Exception $e) {
    echo "base_salary: " . $e->getMessage() . "\n";
}
?>
