<?php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/functions.php';

try {
    $pdo->exec("
        ALTER TABLE users 
        ADD COLUMN alt_mobile VARCHAR(20) DEFAULT NULL,
        ADD COLUMN whatsapp VARCHAR(20) DEFAULT NULL,
        ADD COLUMN gender ENUM('male', 'female', 'other') DEFAULT NULL,
        ADD COLUMN dob DATE DEFAULT NULL,
        ADD COLUMN street VARCHAR(255) DEFAULT NULL,
        ADD COLUMN city VARCHAR(100) DEFAULT NULL,
        ADD COLUMN state VARCHAR(100) DEFAULT NULL,
        ADD COLUMN country VARCHAR(100) DEFAULT 'India',
        ADD COLUMN pincode VARCHAR(20) DEFAULT NULL,
        ADD COLUMN designation VARCHAR(100) DEFAULT NULL,
        ADD COLUMN office_location VARCHAR(100) DEFAULT NULL,
        ADD COLUMN work_shift VARCHAR(50) DEFAULT NULL,
        ADD COLUMN punch_in_range INT DEFAULT NULL
    ");
    echo "Added new columns to users table.\n";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Columns already exist.\n";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
