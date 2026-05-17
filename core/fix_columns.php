<?php
$host = 'localhost';
$user = 'root';
$pass = '12345';
$db   = 'cms_erp_db';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Fix DB</title>";
echo "<style>body{font-family:monospace;background:#0f172a;color:#94a3b8;padding:2rem;line-height:2;}
.ok{color:#10b981;}.err{color:#ef4444;}.warn{color:#f59e0b;}
a.btn{display:inline-block;margin-top:1.5rem;padding:10px 24px;background:#4f46e5;color:white;border-radius:8px;text-decoration:none;font-weight:700;}
</style></head><body><h2 style='color:white;'>🔧 Column Migration Fix</h2>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "<p class='ok'>✓ Connected</p>";
} catch(PDOException $e) {
    // try blank password
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "<p class='warn'>⚠ Connected with blank password</p>";
    } catch(PDOException $e2) {
        die("<p class='err'>✗ ".$e2->getMessage()."</p></body></html>");
    }
}

// Get existing columns in form_responses
$existing = $pdo->query("SHOW COLUMNS FROM form_responses")->fetchAll(PDO::FETCH_COLUMN);
echo "<p class='warn'>Existing columns: ".implode(', ', $existing)."</p>";

// Columns to add
$migrations = [
    "customer_name" => "ALTER TABLE form_responses ADD COLUMN customer_name VARCHAR(100) AFTER agent_id",
    "mobile"        => "ALTER TABLE form_responses ADD COLUMN mobile VARCHAR(20) AFTER customer_name",
    "business_name" => "ALTER TABLE form_responses ADD COLUMN business_name VARCHAR(100) AFTER mobile",
    "category"      => "ALTER TABLE form_responses ADD COLUMN category VARCHAR(50) AFTER business_name",
];

foreach ($migrations as $col => $sql) {
    if (in_array($col, $existing)) {
        echo "<p class='warn'>⚠ Column '$col' already exists — skipped</p>";
    } else {
        try {
            $pdo->exec($sql);
            echo "<p class='ok'>✓ Added column: $col</p>";
        } catch(PDOException $e) {
            echo "<p class='err'>✗ $col — ".$e->getMessage()."</p>";
        }
    }
}

// Also fix campaigns table
try {
    $campCols = $pdo->query("SHOW COLUMNS FROM campaigns")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('created_by', $campCols)) {
        $pdo->exec("ALTER TABLE campaigns ADD COLUMN created_by INT AFTER status");
        echo "<p class='ok'>✓ Added column: campaigns.created_by</p>";
    }
} catch(Exception $e) {}

// Verify
$updated = $pdo->query("SHOW COLUMNS FROM form_responses")->fetchAll(PDO::FETCH_COLUMN);
echo "<p class='ok'>✓ form_responses columns now: ".implode(', ', $updated)."</p>";

echo "<br><p class='ok' style='font-size:1.1rem; font-weight:bold;'>✅ Migration done!</p>";
echo "<a href='../modules/crm/create_lead.php' class='btn'>→ Test Lead Form</a>";
echo "</body></html>";
?>
