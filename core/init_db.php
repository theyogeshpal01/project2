<?php
/**
 * Database Initializer v2.0 — Full Migration
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

$host   = getenv('DB_HOST') ?: 'localhost';
$user   = getenv('DB_USER') ?: 'root';
$env_pass = getenv('DB_PASS');
$pass   = $env_pass !== false ? $env_pass : '12345';
$dbname = getenv('DB_NAME') ?: 'cms_erp_db';

echo "<style>body{font-family:monospace;background:#0f172a;color:#94a3b8;padding:2rem;} .ok{color:#10b981;} .warn{color:#f59e0b;} .err{color:#ef4444;} h2{color:white;}</style>";
echo "<h2>🔧 Contractum ERP — Database Migration</h2><hr style='border-color:#1e293b; margin-bottom:1rem;'>";

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");
    echo "<p class='ok'>✓ Database ready.</p>";
} catch (PDOException $e) {
    die("<p class='err'>✗ Connection failed: " . $e->getMessage() . "</p>");
}

// Helper
function runSQL($pdo, $sql, $label) {
    try {
        $pdo->exec($sql);
        echo "<p class='ok'>✓ $label</p>";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'already exists') !== false || strpos($msg, 'Duplicate') !== false) {
            echo "<p class='warn'>⚠ Skipped (already exists): $label</p>";
        } else {
            echo "<p class='err'>✗ $label — $msg</p>";
        }
    }
}

// ===================== TABLES =====================

runSQL($pdo, "CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dept_name VARCHAR(100) NOT NULL UNIQUE
)", "Table: departments");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    permissions JSON
)", "Table: roles");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(100) NOT NULL,
    location VARCHAR(100),
    project_name VARCHAR(100),
    manager_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: teams");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS users (
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
)", "Table: users");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    created_by INT,
    status ENUM('draft','active','archived') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: forms");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS form_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT,
    field_label VARCHAR(255) NOT NULL,
    field_type ENUM('text','number','dropdown','checkbox','radio','file','photo','signature','location') NOT NULL,
    field_options JSON,
    is_required BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0
)", "Table: form_fields");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS form_responses (
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
)", "Table: form_responses");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS leads (
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
)", "Table: leads");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS kyc_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    doc_type ENUM('aadhar','pan','gst','bank_proof','address_proof','selfie') NOT NULL,
    doc_path VARCHAR(255) NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: kyc_documents");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS merchants (
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
)", "Table: merchants");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS merchant_kyc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT NOT NULL,
    doc_type VARCHAR(50),
    doc_path VARCHAR(255),
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: merchant_kyc");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100),
    sku VARCHAR(50) UNIQUE,
    total_qty INT DEFAULT 0,
    available_qty INT DEFAULT 0,
    cost_price DECIMAL(10,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: inventory");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS inventory_movement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT,
    from_user_id INT,
    to_user_id INT,
    qty INT,
    status ENUM('dispatched','in_transit','delivered','returned') DEFAULT 'dispatched',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: inventory_movement");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    check_in TIMESTAMP NULL,
    check_out TIMESTAMP NULL,
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    status ENUM('full','half','absent') DEFAULT 'full',
    attendance_date DATE NOT NULL,
    UNIQUE KEY user_date (user_id, attendance_date)
)", "Table: attendance");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS payroll (
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
)", "Table: payroll");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    cpl_amount DECIMAL(10,2) DEFAULT 0.00,
    start_date DATE,
    end_date DATE,
    created_by INT,
    status ENUM('active','paused','completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: campaigns");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS agreements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    content TEXT,
    version VARCHAR(10) DEFAULT '1.0',
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: agreements");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS user_agreements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    agreement_id INT,
    accepted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45)
)", "Table: user_agreements");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    amount DECIMAL(15,2),
    type ENUM('credit','debit'),
    description TEXT,
    status ENUM('pending','completed','failed') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: transactions");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS distributor_details (
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
)", "Table: distributor_details");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    type ENUM('info','success','warning','danger') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    link VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: notifications");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    type ENUM('credit','debit') NOT NULL,
    source ENUM('commission','payroll','referral','manual','withdrawal') DEFAULT 'manual',
    description TEXT,
    reference_id VARCHAR(100),
    status ENUM('pending','completed','failed') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: wallet_transactions");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS withdrawal_requests (
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
)", "Table: withdrawal_requests");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS qc_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    response_id INT NOT NULL,
    qc_agent_id INT NOT NULL,
    status ENUM('approved','rejected','rework') NOT NULL,
    remarks TEXT,
    reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: qc_reviews");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    assigned_to INT,
    assigned_by INT,
    due_date DATE,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('pending','in_progress','completed','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: tasks");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS invoices (
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
)", "Table: invoices");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS expenses (
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
)", "Table: expenses");

runSQL($pdo, "CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    target_role INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Table: announcements");

// ===================== SEED DATA =====================
echo "<br><p style='color:#64748b;'>--- Seeding default data ---</p>";

runSQL($pdo, "INSERT IGNORE INTO departments (dept_name) VALUES ('Sales'),('Operations'),('Finance'),('Technology'),('HR')", "Seed: departments");

runSQL($pdo, "INSERT IGNORE INTO roles (id, role_name) VALUES
    (1,'Admin'),(2,'Manager'),(3,'Team Leader'),(4,'Executive'),
    (5,'Distributor'),(6,'QC Agent'),(7,'HR'),(8,'Accounts')", "Seed: roles");

// Admin user with proper bcrypt hash for 'admin123'
$hash = password_hash('admin123', PASSWORD_DEFAULT);
runSQL($pdo, "INSERT IGNORE INTO users (id, name, email, password, role_id, status, kyc_status) VALUES
    (1, 'System Admin', 'admin@contractum.com', '$hash', 1, 'active', 'verified')", "Seed: admin user");

echo "<br><hr style='border-color:#1e293b;'>";
echo "<p style='color:#10b981; font-size:1.1rem; font-weight:bold;'>✅ Migration complete! All tables created.</p>";
echo "<a href='../index.php' style='display:inline-block; margin-top:1rem; padding:10px 24px; background:#4f46e5; color:white; border-radius:8px; text-decoration:none; font-weight:600;'>→ Go to Dashboard</a>";
?>
