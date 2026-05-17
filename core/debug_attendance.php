<?php
require_once '../core/config.php';

echo "<style>body{font-family:monospace;background:#0f172a;color:#94a3b8;padding:2rem;line-height:2;}
.ok{color:#10b981;}.err{color:#ef4444;}.warn{color:#f59e0b;}
a{color:#4f46e5;}</style>";

echo "<h2 style='color:white;'>🔍 Attendance Debug</h2>";

// 1. Check table exists
try {
    $cols = $pdo->query("SHOW COLUMNS FROM attendance")->fetchAll(PDO::FETCH_COLUMN);
    echo "<p class='ok'>✓ attendance table exists. Columns: " . implode(', ', $cols) . "</p>";
} catch(Exception $e) {
    echo "<p class='err'>✗ attendance table MISSING: " . $e->getMessage() . "</p>";
    echo "<p class='warn'>→ Run setup first: <a href='setup.php'>setup.php</a></p>";

    // Create it now
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            check_in TIMESTAMP NULL,
            check_out TIMESTAMP NULL,
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            status ENUM('full','half','absent') DEFAULT 'full',
            attendance_date DATE NOT NULL,
            UNIQUE KEY user_date (user_id, attendance_date)
        )");
        echo "<p class='ok'>✓ attendance table CREATED now!</p>";
    } catch(Exception $e2) {
        echo "<p class='err'>✗ Could not create: " . $e2->getMessage() . "</p>";
    }
}

// 2. Test insert
try {
    $test_user = 1;
    $today = date('Y-m-d');

    // Delete test record first
    $pdo->prepare("DELETE FROM attendance WHERE user_id = ? AND attendance_date = ?")->execute([$test_user, $today]);

    // Insert
    $pdo->prepare("INSERT INTO attendance (user_id, check_in, attendance_date, status) VALUES (?, NOW(), ?, 'full')")
        ->execute([$test_user, $today]);

    echo "<p class='ok'>✓ Test check-in INSERT worked!</p>";

    // Verify
    $row = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND attendance_date = ?");
    $row->execute([$test_user, $today]);
    $row = $row->fetch();
    echo "<p class='ok'>✓ Record found: check_in = " . $row['check_in'] . "</p>";

    // Cleanup
    $pdo->prepare("DELETE FROM attendance WHERE user_id = ? AND attendance_date = ?")->execute([$test_user, $today]);
    echo "<p class='ok'>✓ Test record cleaned up</p>";

} catch(Exception $e) {
    echo "<p class='err'>✗ INSERT failed: " . $e->getMessage() . "</p>";
}

// 3. Check session
session_start();
echo "<p class='warn'>Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET') . "</p>";
echo "<p class='warn'>Session role_id: " . ($_SESSION['role_id'] ?? 'NOT SET') . "</p>";

echo "<br><p class='ok' style='font-size:1.1rem;'>Done! Check errors above.</p>";
echo "<br><a href='../modules/payroll/attendance.php' style='background:#4f46e5;color:white;padding:10px 20px;border-radius:8px;text-decoration:none;'>→ Go to Attendance</a>";
?>
