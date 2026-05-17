<?php
/**
 * CMS ERP & App Configuration
 */

// Load Environment Variables from .env file if it exists
if (file_exists(__DIR__ . '/../.env')) {
    $lines = file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            $val = trim($val, '"\'');
            if (getenv($key) === false) {
                putenv("$key=$val");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
$env_pass = getenv('DB_PASS');
define('DB_PASS', $env_pass !== false ? $env_pass : '12345');
define('DB_NAME', getenv('DB_NAME') ?: 'cms_erp_db');

// System Constants
define('SITE_NAME', getenv('SITE_NAME') ?: 'Contractum ERP');
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost/New%20folder2/');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database Connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If DB doesn't exist yet, inform the user
    die("Database Connection Failed. Please make sure the database exists. <br> 
         You can initialize it here: <a href='" . BASE_URL . "core/init_db.php'>Initialize Database</a><br>
         Error: " . $e->getMessage());
}

// Session Start
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
