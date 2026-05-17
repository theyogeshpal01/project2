<?php
/**
 * CMS ERP Setup Wizard
 */
$config_file = 'core/config.php';
$init_file = 'core/init_db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_pass = $_POST['db_pass'];
    
    // 1. Update core/config.php
    if (file_exists($config_file)) {
        $config_content = file_get_contents($config_file);
        $config_content = preg_replace("/define\('DB_PASS',\s*.*?\);/", "define('DB_PASS', '$db_pass');", $config_content);
        file_put_contents($config_file, $config_content);
    }

    // 2. Update core/init_db.php
    if (file_exists($init_file)) {
        $init_content = file_get_contents($init_file);
        $init_content = preg_replace("/\\\$pass\s*=\s*.*?;/", "\$pass = '$db_pass';", $init_content);
        file_put_contents($init_file, $init_content);
    }

    // 3. Redirect to the initializer
    header("Location: core/init_db.php");
    exit();
}
?>
<!-- Setup UI -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CMS ERP Setup Wizard</title>
    <style>
        body { background: #0f172a; color: white; font-family: sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .card { background: #1e293b; padding: 2.5rem; border-radius: 16px; border: 1px solid #334155; width: 400px; text-align: center; }
        input { width: 100%; padding: 12px; margin: 20px 0; background: #0f172a; border: 1px solid #334155; border-radius: 8px; color: white; box-sizing: border-box; }
        button { background: #6366f1; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; width: 100%; }
        p { color: #94a3b8; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="card">
        <h2 style="margin-top:0">Setup Wizard</h2>
        <p>Enter your MySQL 'root' password to initialize the ERP system.</p>
        <form method="POST">
            <input type="password" name="db_pass" placeholder="MySQL Password (Leave empty if none)" autofocus>
            <button type="submit">Initialize System</button>
        </form>
    </div>
</body>
</html>
