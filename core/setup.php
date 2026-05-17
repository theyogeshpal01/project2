<?php
// Direct SQL Runner — runs in browser via XAMPP
$host = 'localhost';
$user = 'root';
$pass = '12345';
$db   = 'cms_erp_db';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>DB Setup</title>";
echo "<style>body{font-family:monospace;background:#0f172a;color:#94a3b8;padding:2rem;line-height:1.8;}
.ok{color:#10b981;}.err{color:#ef4444;}.warn{color:#f59e0b;}
h2{color:white;border-bottom:1px solid #1e293b;padding-bottom:1rem;}
.done{background:#10b981;color:white;padding:1rem 2rem;border-radius:8px;display:inline-block;margin-top:1rem;text-decoration:none;font-weight:700;font-size:1.1rem;}
</style></head><body>";
echo "<h2>🔧 Contractum ERP — Database Setup</h2>";

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$db`");
    echo "<p class='ok'>✓ Connected to database: $db</p>";
} catch(PDOException $e) {
    // Try with blank password
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db`");
        echo "<p class='warn'>⚠ Connected with blank password. Update config.php DB_PASS to empty string.</p>";
    } catch(PDOException $e2) {
        die("<p class='err'>✗ Cannot connect: ".$e2->getMessage()."</p></body></html>");
    }
}

$tables = [
"departments" => "CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dept_name VARCHAR(100) NOT NULL UNIQUE
)",
"roles" => "CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    permissions JSON
)",
"teams" => "CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(100) NOT NULL,
    location VARCHAR(100),
    project_name VARCHAR(100),
    manager_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"users" => "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password VARCHAR(255) NOT NULL,
    role_id INT,
    team_id INT,
    manager_id INT,
    department_id INT,
    profile_photo VARCHAR(255),
    joining_date DATE,
    base_salary DECIMAL(15,2) DEFAULT 0.00,
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    kyc_status ENUM('pending','verified','rejected') DEFAULT 'pending',
    wallet_balance DECIMAL(15,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"forms" => "CREATE TABLE IF NOT EXISTS forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    created_by INT,
    status ENUM('draft','active','archived') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"form_fields" => "CREATE TABLE IF NOT EXISTS form_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT,
    field_label VARCHAR(255) NOT NULL,
    field_type ENUM('text','number','dropdown','checkbox','radio','file','photo','signature','location') NOT NULL,
    field_options JSON,
    is_required BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0
)",
"form_responses" => "CREATE TABLE IF NOT EXISTS form_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT,
    agent_id INT,
    customer_name VARCHAR(100),
    mobile VARCHAR(20),
    business_name VARCHAR(100),
    category VARCHAR(50),
    response_data JSON,
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    status ENUM('pending','under_review','approved','rejected','rework') DEFAULT 'pending',
    qc_remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"leads" => "CREATE TABLE IF NOT EXISTS leads (
    id VARCHAR(50) PRIMARY KEY,
    agent_id INT,
    customer_name VARCHAR(100),
    mobile VARCHAR(20),
    business_name VARCHAR(100),
    address TEXT,
    gps_location VARCHAR(100),
    category VARCHAR(50),
    status ENUM('new','assigned','qualified','in_process','submitted','qc_approved','rejected','active') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"kyc_documents" => "CREATE TABLE IF NOT EXISTS kyc_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    doc_type ENUM('aadhar','pan','gst','bank_proof','address_proof','selfie') NOT NULL,
    doc_path VARCHAR(255) NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"merchants" => "CREATE TABLE IF NOT EXISTS merchants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_name VARCHAR(255) NOT NULL,
    owner_name VARCHAR(100),
    mobile VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    city VARCHAR(100),
    pincode VARCHAR(6),
    category VARCHAR(50),
    gst_number VARCHAR(15),
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    onboarded_by INT,
    status ENUM('pending','active','inactive','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"merchant_kyc" => "CREATE TABLE IF NOT EXISTS merchant_kyc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT NOT NULL,
    doc_type VARCHAR(50),
    doc_path VARCHAR(255),
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"inventory" => "CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100),
    sku VARCHAR(50) UNIQUE,
    total_qty INT DEFAULT 0,
    available_qty INT DEFAULT 0,
    cost_price DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"inventory_movement" => "CREATE TABLE IF NOT EXISTS inventory_movement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT,
    from_user_id INT,
    to_user_id INT,
    qty INT,
    status ENUM('dispatched','in_transit','delivered','returned') DEFAULT 'dispatched',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"attendance" => "CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    check_in TIMESTAMP NULL,
    check_out TIMESTAMP NULL,
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    status ENUM('full','half','absent') DEFAULT 'full',
    attendance_date DATE NOT NULL,
    UNIQUE KEY user_date (user_id, attendance_date)
)",
"payroll" => "CREATE TABLE IF NOT EXISTS payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    month TINYINT,
    year SMALLINT,
    base_salary DECIMAL(15,2),
    incentives DECIMAL(15,2) DEFAULT 0.00,
    deductions DECIMAL(15,2) DEFAULT 0.00,
    net_payable DECIMAL(15,2),
    status ENUM('pending','approved','paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"campaigns" => "CREATE TABLE IF NOT EXISTS campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    cpl_amount DECIMAL(10,2) DEFAULT 0.00,
    start_date DATE,
    end_date DATE,
    created_by INT,
    status ENUM('active','paused','completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"agreements" => "CREATE TABLE IF NOT EXISTS agreements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    content TEXT,
    version VARCHAR(10) DEFAULT '1.0',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"user_agreements" => "CREATE TABLE IF NOT EXISTS user_agreements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    agreement_id INT,
    accepted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45)
)",
"transactions" => "CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    amount DECIMAL(15,2),
    type ENUM('credit','debit'),
    description TEXT,
    status ENUM('pending','completed','failed') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"distributor_details" => "CREATE TABLE IF NOT EXISTS distributor_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    business_name VARCHAR(255),
    gst_number VARCHAR(15),
    pan_number VARCHAR(10),
    bank_name VARCHAR(100),
    account_number VARCHAR(20),
    ifsc_code VARCHAR(11),
    address_line1 TEXT,
    city VARCHAR(100),
    pincode VARCHAR(6)
)",
"notifications" => "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    type ENUM('info','success','warning','danger') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    link VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"wallet_transactions" => "CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    type ENUM('credit','debit') NOT NULL,
    source ENUM('commission','payroll','referral','manual','withdrawal') DEFAULT 'manual',
    description TEXT,
    reference_id VARCHAR(100),
    status ENUM('pending','completed','failed') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"withdrawal_requests" => "CREATE TABLE IF NOT EXISTS withdrawal_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    bank_name VARCHAR(100),
    account_number VARCHAR(20),
    ifsc_code VARCHAR(11),
    upi_id VARCHAR(100),
    status ENUM('pending','approved','rejected','processed') DEFAULT 'pending',
    admin_remarks TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL
)",
"qc_reviews" => "CREATE TABLE IF NOT EXISTS qc_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    response_id INT NOT NULL,
    qc_agent_id INT NOT NULL,
    status ENUM('approved','rejected','rework') NOT NULL,
    remarks TEXT,
    reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"tasks" => "CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    assigned_to INT,
    assigned_by INT,
    due_date DATE,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('pending','in_progress','completed','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"invoices" => "CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE,
    client_name VARCHAR(255),
    client_email VARCHAR(100),
    items JSON,
    subtotal DECIMAL(15,2),
    gst_percent DECIMAL(5,2) DEFAULT 18.00,
    gst_amount DECIMAL(15,2),
    total_amount DECIMAL(15,2),
    status ENUM('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
    due_date DATE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"expenses" => "CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    amount DECIMAL(15,2),
    category VARCHAR(100),
    paid_to VARCHAR(255),
    payment_mode ENUM('cash','bank','upi','cheque') DEFAULT 'bank',
    receipt_path VARCHAR(255),
    added_by INT,
    expense_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
"announcements" => "CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    target_role INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)",
];

$created = 0;
$skipped = 0;
foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        // Check if it actually exists now
        $check = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$db' AND table_name='$name'")->fetchColumn();
        if ($check) {
            echo "<p class='ok'>✓ $name</p>";
            $created++;
        }
    } catch(PDOException $e) {
        echo "<p class='err'>✗ $name — ".$e->getMessage()."</p>";
    }
}

// Seed data
echo "<br><p class='warn'>--- Seeding ---</p>";
try {
    $pdo->exec("INSERT IGNORE INTO departments (dept_name) VALUES ('Sales'),('Operations'),('Finance'),('Technology'),('HR')");
    echo "<p class='ok'>✓ Departments seeded</p>";
} catch(Exception $e){}

try {
    $pdo->exec("INSERT IGNORE INTO roles (id, role_name) VALUES (1,'Admin'),(2,'Manager'),(3,'Team Leader'),(4,'Executive'),(5,'Distributor'),(6,'QC Agent'),(7,'HR'),(8,'Accounts')");
    echo "<p class='ok'>✓ Roles seeded</p>";
} catch(Exception $e){}

try {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT IGNORE INTO users (id,name,email,password,role_id,status,kyc_status) VALUES (1,'System Admin','admin@contractum.com','$hash',1,'active','verified')");
    echo "<p class='ok'>✓ Admin user seeded (admin@contractum.com / admin123)</p>";
} catch(Exception $e){}

// Verify all tables exist
echo "<br><p class='warn'>--- Verification ---</p>";
$existing = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='$db' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
echo "<p class='ok'>✓ Total tables in database: <strong>".count($existing)."</strong></p>";
echo "<p style='color:#475569; font-size:0.85rem;'>".implode(', ', $existing)."</p>";

echo "<br><p class='ok' style='font-size:1.2rem; font-weight:bold;'>✅ Done! $created tables ready.</p>";
echo "<a href='../index.php' class='done'>→ Go to Dashboard</a>";
echo "</body></html>";
?>
