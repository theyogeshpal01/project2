<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/functions.php';

try {
    $pdo->exec("ALTER TABLE companies ADD COLUMN subscription_plan_id INT DEFAULT NULL");
    echo "Added subscription_plan_id to companies.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "subscription_plan_id already exists.\n";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
