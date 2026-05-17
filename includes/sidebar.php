<?php
$current_page = $_SERVER['PHP_SELF'];
$role_id = $_SESSION['role_id'] ?? 1;

function isActive($path) {
    global $current_page;
    return strpos($current_page, $path) !== false ? 'active' : '';
}

// Unread notifications count
$notif_count = 0;
if (isset($GLOBALS['pdo']) && isset($_SESSION['user_id'])) {
    try {
        $ns = $GLOBALS['pdo']->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $ns->execute([$_SESSION['user_id']]);
        $notif_count = $ns->fetchColumn();
    } catch(Exception $e) {}
}

$menus = [];

// ROLE 1 — Admin
if ($role_id == 1) {
    $menus = [
        ['label' => 'MAIN', 'type' => 'section'],
        ['href' => 'index.php', 'label' => 'Dashboard', 'icon' => 'home', 'match' => 'index.php'],
        ['label' => 'OPERATIONS', 'type' => 'section'],
        ['href' => 'modules/team/users.php', 'label' => 'User Management', 'icon' => 'users', 'match' => 'team/users'],
        ['href' => 'modules/team/manage.php', 'label' => 'Team Management', 'icon' => 'team', 'match' => 'team/manage'],
        ['href' => 'modules/crm/leads.php', 'label' => 'CRM & Leads', 'icon' => 'crm', 'match' => 'modules/crm'],
        ['href' => 'modules/forms/index.php', 'label' => 'Form Builder', 'icon' => 'form', 'match' => 'modules/forms'],
        ['href' => 'modules/qc/review.php', 'label' => 'QC Panel', 'icon' => 'qc', 'match' => 'modules/qc'],
        ['label' => 'BUSINESS', 'type' => 'section'],
        ['href' => 'modules/inventory/stock.php', 'label' => 'Inventory', 'icon' => 'inventory', 'match' => 'modules/inventory'],
        ['href' => 'modules/merchants/list.php', 'label' => 'Merchants', 'icon' => 'merchant', 'match' => 'modules/merchants'],
        ['href' => 'modules/referral/campaigns.php', 'label' => 'Campaigns & CPL', 'icon' => 'referral', 'match' => 'modules/referral'],
        ['href' => 'modules/accounts/invoices.php', 'label' => 'Invoices', 'icon' => 'invoice', 'match' => 'modules/accounts/invoices'],
        ['href' => 'modules/accounts/expenses.php', 'label' => 'Expenses', 'icon' => 'expense', 'match' => 'modules/accounts/expenses'],
        ['label' => 'HR & PAYROLL', 'type' => 'section'],
        ['href' => 'modules/hr/employees.php', 'label' => 'Employees', 'icon' => 'employee', 'match' => 'modules/hr/employees'],
        ['href' => 'modules/payroll/salaries.php', 'label' => 'Payroll', 'icon' => 'payroll', 'match' => 'modules/payroll/salaries'],
        ['href' => 'modules/payroll/attendance.php', 'label' => 'Attendance', 'icon' => 'attendance', 'match' => 'modules/payroll/attendance'],
        ['href' => 'modules/wallet/index.php', 'label' => 'Wallet & Payouts', 'icon' => 'wallet', 'match' => 'modules/wallet'],
        ['label' => 'ANALYTICS', 'type' => 'section'],
        ['href' => 'modules/analytics/dashboard.php', 'label' => 'Analytics', 'icon' => 'analytics', 'match' => 'modules/analytics'],
        ['href' => 'modules/reports/onboarding.php', 'label' => 'Reports', 'icon' => 'report', 'match' => 'modules/reports'],
        ['label' => 'SYSTEM', 'type' => 'section'],
        ['href' => 'modules/notifications/index.php', 'label' => 'Notifications', 'icon' => 'notif', 'match' => 'modules/notifications', 'badge' => $notif_count],
        ['href' => 'modules/agreements/manage.php', 'label' => 'Agreements', 'icon' => 'agreement', 'match' => 'modules/agreements'],
        ['href' => 'modules/settings/index.php', 'label' => 'Settings', 'icon' => 'settings', 'match' => 'modules/settings'],
    ];
}

// ROLE 2 — Manager
elseif ($role_id == 2) {
    $menus = [
        ['label' => 'MAIN', 'type' => 'section'],
        ['href' => 'index.php', 'label' => 'Dashboard', 'icon' => 'home', 'match' => 'index.php'],
        ['label' => 'MY TEAM', 'type' => 'section'],
        ['href' => 'modules/team/manage.php', 'label' => 'My Teams', 'icon' => 'team', 'match' => 'team/manage'],
        ['href' => 'modules/team/users.php', 'label' => 'Team Members', 'icon' => 'users', 'match' => 'team/users'],
        ['href' => 'modules/tasks/index.php', 'label' => 'Task Assignment', 'icon' => 'task', 'match' => 'modules/tasks'],
        ['label' => 'OPERATIONS', 'type' => 'section'],
        ['href' => 'modules/crm/leads.php', 'label' => 'Lead Reports', 'icon' => 'crm', 'match' => 'modules/crm'],
        ['href' => 'modules/qc/review.php', 'label' => 'QC Monitor', 'icon' => 'qc', 'match' => 'modules/qc'],
        ['href' => 'modules/payroll/attendance.php', 'label' => 'Attendance', 'icon' => 'attendance', 'match' => 'modules/payroll/attendance'],
        ['href' => 'modules/analytics/dashboard.php', 'label' => 'Analytics', 'icon' => 'analytics', 'match' => 'modules/analytics'],
        ['label' => 'SYSTEM', 'type' => 'section'],
        ['href' => 'modules/notifications/index.php', 'label' => 'Notifications', 'icon' => 'notif', 'match' => 'modules/notifications', 'badge' => $notif_count],
    ];
}

// ROLE 3 — Team Leader
elseif ($role_id == 3) {
    $menus = [
        ['label' => 'MAIN', 'type' => 'section'],
        ['href' => 'index.php', 'label' => 'Dashboard', 'icon' => 'home', 'match' => 'index.php'],
        ['label' => 'FIELD OPS', 'type' => 'section'],
        ['href' => 'modules/crm/leads.php', 'label' => 'My Leads', 'icon' => 'crm', 'match' => 'modules/crm'],
        ['href' => 'modules/tasks/index.php', 'label' => 'Tasks', 'icon' => 'task', 'match' => 'modules/tasks'],
        ['href' => 'modules/team/users.php', 'label' => 'My Agents', 'icon' => 'users', 'match' => 'team/users'],
        ['href' => 'modules/payroll/attendance.php', 'label' => 'Attendance', 'icon' => 'attendance', 'match' => 'modules/payroll/attendance'],
        ['label' => 'SYSTEM', 'type' => 'section'],
        ['href' => 'modules/notifications/index.php', 'label' => 'Notifications', 'icon' => 'notif', 'match' => 'modules/notifications', 'badge' => $notif_count],
        ['href' => 'modules/wallet/index.php', 'label' => 'My Wallet', 'icon' => 'wallet', 'match' => 'modules/wallet'],
    ];
}

// ROLE 4 — Field Agent / Executive
elseif ($role_id == 4) {
    $menus = [
        ['label' => 'MAIN', 'type' => 'section'],
        ['href' => 'index.php', 'label' => 'Dashboard', 'icon' => 'home', 'match' => 'index.php'],
        ['label' => 'MY WORK', 'type' => 'section'],
        ['href' => 'modules/tasks/index.php', 'label' => 'My Tasks', 'icon' => 'task', 'match' => 'modules/tasks'],
        ['href' => 'modules/crm/create_lead.php', 'label' => 'Add New Lead', 'icon' => 'crm', 'match' => 'create_lead'],
        ['href' => 'modules/crm/leads.php', 'label' => 'My Leads', 'icon' => 'report', 'match' => 'modules/crm/leads'],
        ['href' => 'modules/payroll/attendance.php', 'label' => 'Attendance', 'icon' => 'attendance', 'match' => 'modules/payroll/attendance'],
        ['label' => 'PROFILE', 'type' => 'section'],
        ['href' => 'modules/kyc/verify.php', 'label' => 'My KYC', 'icon' => 'qc', 'match' => 'modules/kyc'],
        ['href' => 'modules/wallet/index.php', 'label' => 'My Wallet', 'icon' => 'wallet', 'match' => 'modules/wallet'],
        ['href' => 'modules/notifications/index.php', 'label' => 'Notifications', 'icon' => 'notif', 'match' => 'modules/notifications', 'badge' => $notif_count],
    ];
}

// ROLE 5 — Distributor / Referral Partner
elseif ($role_id == 5) {
    $menus = [
        ['label' => 'MAIN', 'type' => 'section'],
        ['href' => 'index.php', 'label' => 'Dashboard', 'icon' => 'home', 'match' => 'index.php'],
        ['label' => 'MY BUSINESS', 'type' => 'section'],
        ['href' => 'modules/crm/leads.php', 'label' => 'My Leads', 'icon' => 'crm', 'match' => 'modules/crm'],
        ['href' => 'modules/referral/campaigns.php', 'label' => 'CPL Campaigns', 'icon' => 'referral', 'match' => 'modules/referral'],
        ['href' => 'modules/wallet/index.php', 'label' => 'My Wallet', 'icon' => 'wallet', 'match' => 'modules/wallet'],
        ['href' => 'modules/kyc/verify.php', 'label' => 'KYC Status', 'icon' => 'qc', 'match' => 'modules/kyc'],
        ['label' => 'SYSTEM', 'type' => 'section'],
        ['href' => 'modules/notifications/index.php', 'label' => 'Notifications', 'icon' => 'notif', 'match' => 'modules/notifications', 'badge' => $notif_count],
    ];
}

// ROLE 6 — QC Agent
elseif ($role_id == 6) {
    $menus = [
        ['label' => 'MAIN', 'type' => 'section'],
        ['href' => 'index.php', 'label' => 'Dashboard', 'icon' => 'home', 'match' => 'index.php'],
        ['label' => 'QC WORK', 'type' => 'section'],
        ['href' => 'modules/qc/review.php', 'label' => 'Pending Reviews', 'icon' => 'qc', 'match' => 'modules/qc/review'],
        ['href' => 'modules/kyc/admin_verify.php', 'label' => 'KYC Verification', 'icon' => 'kyc', 'match' => 'modules/kyc/admin'],
        ['href' => 'modules/payroll/attendance.php', 'label' => 'Attendance', 'icon' => 'attendance', 'match' => 'modules/payroll/attendance'],
        ['label' => 'SYSTEM', 'type' => 'section'],
        ['href' => 'modules/notifications/index.php', 'label' => 'Notifications', 'icon' => 'notif', 'match' => 'modules/notifications', 'badge' => $notif_count],
    ];
}

// ROLE 7 — HR
elseif ($role_id == 7) {
    $menus = [
        ['label' => 'MAIN', 'type' => 'section'],
        ['href' => 'index.php', 'label' => 'Dashboard', 'icon' => 'home', 'match' => 'index.php'],
        ['label' => 'HR PANEL', 'type' => 'section'],
        ['href' => 'modules/hr/employees.php', 'label' => 'Employees', 'icon' => 'employee', 'match' => 'modules/hr/employees'],
        ['href' => 'modules/payroll/attendance.php', 'label' => 'Attendance', 'icon' => 'attendance', 'match' => 'modules/payroll/attendance'],
        ['href' => 'modules/payroll/salaries.php', 'label' => 'Payroll', 'icon' => 'payroll', 'match' => 'modules/payroll/salaries'],
        ['href' => 'modules/kyc/admin_verify.php', 'label' => 'KYC Approvals', 'icon' => 'kyc', 'match' => 'modules/kyc/admin'],
        ['label' => 'SYSTEM', 'type' => 'section'],
        ['href' => 'modules/notifications/index.php', 'label' => 'Notifications', 'icon' => 'notif', 'match' => 'modules/notifications', 'badge' => $notif_count],
    ];
}

// ROLE 8 — Accounts
elseif ($role_id == 8) {
    $menus = [
        ['label' => 'MAIN', 'type' => 'section'],
        ['href' => 'index.php', 'label' => 'Dashboard', 'icon' => 'home', 'match' => 'index.php'],
        ['label' => 'FINANCE', 'type' => 'section'],
        ['href' => 'modules/accounts/invoices.php', 'label' => 'Invoices', 'icon' => 'invoice', 'match' => 'modules/accounts/invoices'],
        ['href' => 'modules/accounts/expenses.php', 'label' => 'Expenses', 'icon' => 'expense', 'match' => 'modules/accounts/expenses'],
        ['href' => 'modules/wallet/index.php', 'label' => 'Wallet & Payouts', 'icon' => 'wallet', 'match' => 'modules/wallet'],
        ['href' => 'modules/payroll/salaries.php', 'label' => 'Payroll', 'icon' => 'payroll', 'match' => 'modules/payroll/salaries'],
        ['href' => 'modules/analytics/dashboard.php', 'label' => 'Finance Reports', 'icon' => 'analytics', 'match' => 'modules/analytics'],
        ['label' => 'SYSTEM', 'type' => 'section'],
        ['href' => 'modules/notifications/index.php', 'label' => 'Notifications', 'icon' => 'notif', 'match' => 'modules/notifications', 'badge' => $notif_count],
    ];
}

// SVG Icons map
function getSidebarIcon($name) {
    $icons = [
        'home'       => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline>',
        'users'      => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>',
        'team'       => '<path d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>',
        'crm'        => '<path d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"></path>',
        'form'       => '<path d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"></path>',
        'qc'         => '<path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>',
        'kyc'        => '<rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M8 10h8M8 14h5"></path>',
        'inventory'  => '<path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"></path><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>',
        'merchant'   => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><path d="M9 22V12h6v10"></path>',
        'referral'   => '<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>',
        'invoice'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line>',
        'expense'    => '<rect x="2" y="5" width="20" height="14" rx="2"></rect><line x1="2" y1="10" x2="22" y2="10"></line>',
        'employee'   => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle>',
        'payroll'    => '<line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>',
        'attendance' => '<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line>',
        'wallet'     => '<rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>',
        'analytics'  => '<line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>',
        'report'     => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path>',
        'notif'      => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path>',
        'agreement'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline>',
        'settings'   => '<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>',
        'task'       => '<path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>',
    ];
    return $icons[$name] ?? '<circle cx="12" cy="12" r="10"></circle>';
}
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo-icon">C</div>
        <h2>Contractum</h2>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-links">
            <?php foreach ($menus as $item): ?>
                <?php if ($item['type'] ?? '' === 'section'): ?>
                    <li class="nav-section-label"><?php echo $item['label']; ?></li>
                <?php else: ?>
                    <?php
                        $href = BASE_URL . $item['href'];
                        $active = (strpos($current_page, $item['match']) !== false) ? 'active' : '';
                        $badge = $item['badge'] ?? 0;
                    ?>
                    <li class="nav-item">
                        <a href="<?php echo $href; ?>" class="nav-link <?php echo $active; ?>">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <?php echo getSidebarIcon($item['icon']); ?>
                            </svg>
                            <span><?php echo $item['label']; ?></span>
                            <?php if ($badge > 0): ?>
                                <span class="nav-badge"><?php echo $badge; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>

            <li style="margin: 1rem 0; height: 1px; background: rgba(255,255,255,0.05);"></li>
            <li class="nav-item">
                <a href="<?php echo BASE_URL; ?>modules/auth/logout.php" class="nav-link logout-link">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>
