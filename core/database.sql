-- CMS ERP Full Database Schema v2.0

CREATE DATABASE IF NOT EXISTS cms_erp_db;
USE cms_erp_db;

-- Roles Table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL UNIQUE,
    permissions JSON
);

-- Teams Table
CREATE TABLE IF NOT EXISTS teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    team_name VARCHAR(100) NOT NULL,
    location VARCHAR(100),
    project_name VARCHAR(100),
    manager_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role_id INT,
    team_id INT,
    manager_id INT,
    department_id INT,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    kyc_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    phone VARCHAR(20) NULL,
    base_salary DECIMAL(15, 2) DEFAULT 0.00,
    wallet_balance DECIMAL(15, 2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id),
    FOREIGN KEY (team_id) REFERENCES teams(id),
    FOREIGN KEY (department_id) REFERENCES departments(id)
);

-- Forms (Dynamic TypeForm Builder)
CREATE TABLE IF NOT EXISTS forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    created_by INT,
    status ENUM('draft', 'active', 'archived') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Form Fields
CREATE TABLE IF NOT EXISTS form_fields (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT,
    field_label VARCHAR(255) NOT NULL,
    field_type ENUM('text', 'number', 'dropdown', 'checkbox', 'radio', 'file', 'photo', 'signature', 'location') NOT NULL,
    field_options JSON, -- For dropdowns/checkboxes
    is_required BOOLEAN DEFAULT FALSE,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (form_id) REFERENCES forms(id) ON DELETE CASCADE
);

-- Form Responses / Data Collection
CREATE TABLE IF NOT EXISTS form_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT,
    agent_id INT,
    response_data JSON,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    status ENUM('pending', 'approved', 'rejected', 'rework') DEFAULT 'pending',
    qc_remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES forms(id),
    FOREIGN KEY (agent_id) REFERENCES users(id)
);

-- Leads Table
CREATE TABLE IF NOT EXISTS leads (
    id VARCHAR(50) PRIMARY KEY,
    agent_id INT,
    customer_name VARCHAR(100),
    mobile VARCHAR(20),
    business_name VARCHAR(100),
    address TEXT,
    gps_location VARCHAR(100),
    category VARCHAR(50),
    status ENUM('new', 'assigned', 'qualified', 'in_process', 'submitted', 'qc_approved', 'rejected', 'active') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES users(id)
);

-- KYC Documents
CREATE TABLE IF NOT EXISTS kyc_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    doc_type ENUM('aadhar', 'pan', 'gst', 'bank_proof', 'address_proof', 'selfie') NOT NULL,
    doc_path VARCHAR(255) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Inventory Table
CREATE TABLE IF NOT EXISTS inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100),
    sku VARCHAR(50) UNIQUE,
    total_qty INT DEFAULT 0,
    available_qty INT DEFAULT 0,
    cost_price DECIMAL(10, 2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Inventory Movement (Tracking Assignment)
CREATE TABLE IF NOT EXISTS inventory_movement (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT,
    from_user_id INT, -- NULL if from warehouse
    to_user_id INT,
    qty INT,
    status ENUM('dispatched', 'in_transit', 'delivered', 'returned') DEFAULT 'dispatched',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES inventory(id),
    FOREIGN KEY (from_user_id) REFERENCES users(id),
    FOREIGN KEY (to_user_id) REFERENCES users(id)
);

-- Attendance Table
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    check_in TIMESTAMP NULL,
    check_out TIMESTAMP NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    status ENUM('full', 'half', 'absent') DEFAULT 'full',
    attendance_date DATE NOT NULL,
    UNIQUE KEY user_date (user_id, attendance_date),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Payroll Table
CREATE TABLE IF NOT EXISTS payroll (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    month TINYINT,
    year SMALLINT,
    base_salary DECIMAL(15, 2),
    incentives DECIMAL(15, 2) DEFAULT 0.00,
    deductions DECIMAL(15, 2) DEFAULT 0.00,
    net_payable DECIMAL(15, 2),
    status ENUM('pending', 'approved', 'paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Agreements
CREATE TABLE IF NOT EXISTS agreements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    content TEXT,
    version VARCHAR(10) DEFAULT '1.0',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User Agreement Acceptances
CREATE TABLE IF NOT EXISTS user_agreements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    agreement_id INT,
    accepted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (agreement_id) REFERENCES agreements(id)
);

-- Transactions Table
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    amount DECIMAL(15, 2),
    type ENUM('credit', 'debit'),
    description TEXT,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Campaigns Table
CREATE TABLE IF NOT EXISTS campaigns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    cpl_amount DECIMAL(10, 2) DEFAULT 0.00,
    start_date DATE,
    end_date DATE,
    status ENUM('active', 'paused', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Departments Table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    dept_name VARCHAR(100) NOT NULL UNIQUE
);

-- Distributor Business Details
CREATE TABLE IF NOT EXISTS distributor_details (
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
    pincode VARCHAR(6),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Seed Departments
INSERT IGNORE INTO departments (dept_name) VALUES ('Sales'), ('Operations'), ('Finance'), ('Technology'), ('HR');

-- Seed Roles
INSERT IGNORE INTO roles (id, role_name) VALUES 
(1, 'Admin'), 
(2, 'Manager'), 
(3, 'Team Leader'), 
(4, 'Executive'), 
(5, 'Distributor'),
(6, 'QC Agent'),
(7, 'HR'),
(8, 'Accounts');

-- Notifications Table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    link VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Wallet Transactions Table
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    type ENUM('credit','debit') NOT NULL,
    source ENUM('commission','payroll','referral','manual','withdrawal') DEFAULT 'manual',
    description TEXT,
    reference_id VARCHAR(100),
    status ENUM('pending','completed','failed') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Withdrawal Requests
CREATE TABLE IF NOT EXISTS withdrawal_requests (
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
    processed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- QC Reviews Table (detailed QC tracking)
CREATE TABLE IF NOT EXISTS qc_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    response_id INT NOT NULL,
    qc_agent_id INT NOT NULL,
    status ENUM('approved','rejected','rework') NOT NULL,
    remarks TEXT,
    reviewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (response_id) REFERENCES form_responses(id),
    FOREIGN KEY (qc_agent_id) REFERENCES users(id)
);

-- Tasks Table
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    assigned_to INT,
    assigned_by INT,
    due_date DATE,
    priority ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status ENUM('pending','in_progress','completed','cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id)
);

-- Merchant Table
CREATE TABLE IF NOT EXISTS merchants (
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (onboarded_by) REFERENCES users(id)
);

-- Merchant KYC
CREATE TABLE IF NOT EXISTS merchant_kyc (
    id INT AUTO_INCREMENT PRIMARY KEY,
    merchant_id INT NOT NULL,
    doc_type VARCHAR(50),
    doc_path VARCHAR(255),
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (merchant_id) REFERENCES merchants(id)
);

-- Invoices Table
CREATE TABLE IF NOT EXISTS invoices (
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Expenses Table
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    amount DECIMAL(15,2),
    category VARCHAR(100),
    paid_to VARCHAR(255),
    payment_mode ENUM('cash','bank','upi','cheque') DEFAULT 'bank',
    receipt_path VARCHAR(255),
    added_by INT,
    expense_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES users(id)
);

-- Announcements
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT,
    target_role INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Seed Default Admin (Password: admin123)
INSERT IGNORE INTO users (name, email, password, role_id, status) VALUES 
('System Admin', 'admin@contractum.com', '$2y$10$8.uJ8qF3F3F3F3F3F3F3F.uJ8qF3F3F3F3F3F3F3F3F3F3F3F3', 1, 'active');
