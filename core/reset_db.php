<?php
/**
 * Database Reset Script — Clears all data, keeps only Admin user
 * ACCESS: http://localhost/New%20folder2/core/reset_db.php
 * DELETE THIS FILE after use for security!
 */

include_once __DIR__ . '/config.php';

// Security: Only allow local access or CLI
if (php_sapi_name() !== 'cli' && (!isset($_SERVER['REMOTE_ADDR']) || !in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1', 'localhost']))) {
    die('<h2 style="color:red;">Access Denied — localhost only</h2>');
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Database Reset</title>
<style>
body { font-family: monospace; background: #0f172a; color: #94a3b8; padding: 2rem; }
h2   { color: white; }
.ok  { color: #10b981; }
.err { color: #ef4444; }
.warn{ color: #f59e0b; }
.box { background: #1e293b; border-radius: 12px; padding: 2rem; margin-top: 1.5rem; }
.cred-box { background: #0f2d20; border: 1px solid #10b981; border-radius: 10px; padding: 1.5rem; margin-top: 1.5rem; }
.cred-row { display: flex; gap: 2rem; margin: 8px 0; font-size: 1.1rem; }
.label { color: #64748b; }
.value { color: #10b981; font-weight: bold; font-size: 1.2rem; }
a.btn { display: inline-block; margin-top: 1.5rem; padding: 12px 28px; background: #2563eb; color: white;
        border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 1rem; }
</style>
</head>
<body>
<h2>🗑️ Database Reset — Clearing All Data</h2>

<?php
// List of all tables to clear (in safe order)
$tables_to_truncate = [
    'attendance',
    'announcements',
    'campaigns',
    'agreements',
    'user_agreements',
    'transactions',
    'wallet_transactions',
    'withdrawal_requests',
    'qc_reviews',
    'tasks',
    'invoices',
    'expenses',
    'form_responses',
    'form_fields',
    'forms',
    'leads',
    'kyc_documents',
    'merchants',
    'merchant_kyc',
    'inventory',
    'inventory_movement',
    'payroll',
    'distributor_details',
    'notifications',
    'teams',
    'leaves',
];

// Tables to remove all non-admin rows
$partial_tables = ['users']; // keep id=1

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");

    foreach ($tables_to_truncate as $tbl) {
        try {
            $pdo->exec("TRUNCATE TABLE `$tbl`");
            echo "<p class='ok'>✓ Cleared: $tbl</p>";
        } catch (Exception $e) {
            echo "<p class='warn'>⚠ Skipped $tbl: " . $e->getMessage() . "</p>";
        }
    }

    // Keep only admin user (id=1)
    $pdo->exec("DELETE FROM users WHERE id != 1");
    echo "<p class='ok'>✓ Users: deleted all except admin (id=1)</p>";

    // Reset admin password to 'admin123' and make sure credentials are correct
    $new_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET
        name     = 'System Admin',
        email    = 'admin@contractum.com',
        password = ?,
        role_id  = 1,
        status   = 'active',
        kyc_status = 'verified',
        wallet_balance = 0
        WHERE id = 1")->execute([$new_pass]);
    echo "<p class='ok'>✓ Admin credentials reset</p>";

    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

    echo "<div class='cred-box'>";
    echo "<h3 style='color:#10b981; margin-top:0;'>✅ Reset Complete! Admin Credentials:</h3>";
    echo "<div class='cred-row'><span class='label'>URL:</span>     <span class='value'>http://localhost/New%20folder2/</span></div>";
    echo "<div class='cred-row'><span class='label'>Email:</span>   <span class='value'>admin@contractum.com</span></div>";
    echo "<div class='cred-row'><span class='label'>Password:</span><span class='value'>admin123</span></div>";
    echo "<div class='cred-row'><span class='label'>Role:</span>    <span class='value'>Admin (Full Access)</span></div>";
    echo "</div>";

} catch (Exception $e) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    echo "<p class='err'>✗ Fatal Error: " . $e->getMessage() . "</p>";
}
?>

<div class="box">
    <p class="warn">⚠️ <strong>Security Warning:</strong> Please delete this file after use!</p>
    <p style="color:#64748b; font-size:0.875rem;">File location: <code>c:\xampp\htdocs\New folder2\core\reset_db.php</code></p>
</div>

<a class="btn" href="../index.php">→ Go to Dashboard</a>

</body>
</html>
