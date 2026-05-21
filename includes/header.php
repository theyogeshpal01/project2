<?php
require_once __DIR__ . '/../core/config.php';

if (!isset($_SESSION['user_id']) && strpos($_SERVER['PHP_SELF'], 'login.php') === false) {
    header("Location: " . BASE_URL . "modules/auth/login.php");
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'Guest';
$role_name = $_SESSION['role_name'] ?? 'Visitor';
$role_id = $_SESSION['role_id'] ?? 1;

// Unread notifications count
$notif_count = 0;
try {
    $ns = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $ns->execute([$_SESSION['user_id'] ?? 0]);
    $notif_count = $ns->fetchColumn();
} catch (Exception $e) {
}

// Derive page title from PHP_SELF
$page_map = [
    'index.php' => 'Dashboard',
    'team/users' => 'Employees',
    'team/manage' => 'Team Management',
    'crm/leads' => 'CRM & Leads',
    'crm/create_lead' => 'Add New Lead',
    'forms/index' => 'Form Builder',
    'qc/review' => 'QC Panel',
    'inventory/stock' => 'Asset Management',
    'merchants/list' => 'Merchants',
    'referral/campaigns' => 'Campaigns & CPL',
    'accounts/invoices' => 'Invoices',
    'accounts/expenses' => 'Expenses',
    'hr/employees' => 'Employees',
    'payroll/salaries' => 'Payroll',
    'payroll/attendance' => 'Attendance',
    'leaves/index' => 'Manage Leaves',
    'announcements/index' => 'Manage Announcement',
    'wallet/index' => 'Wallet & Payouts',
    'analytics/dashboard' => 'Analytics',
    'reports/onboarding' => 'Reports',
    'notifications/index' => 'Notifications',
    'agreements/manage' => 'Agreements',
    'settings/index' => 'Settings',
    'kyc/verify' => 'KYC Status',
    'kyc/admin_verify' => 'KYC Verification',
    'tasks/index' => 'Task Management',
];
$page_title = SITE_NAME;
$self = $_SERVER['PHP_SELF'];
foreach ($page_map as $pattern => $title) {
    if (strpos($self, $pattern) !== false) {
        $page_title = $title;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> — <?php echo SITE_NAME; ?></title>
    <meta name="description" content="<?php echo SITE_NAME; ?> — ERP management panel">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <script>
        // Apply saved dark-mode preference before paint to avoid flash
        (function () {
            if (localStorage.getItem('darkMode') === '1') {
                document.documentElement.classList.add('dark-mode-pending');
            }
        })();
    </script>
</head>

<body>

    <button class="sidebar-toggle" id="sidebarToggle"
        onclick="document.getElementById('sidebar').classList.toggle('open')" aria-label="Toggle sidebar">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </svg>
    </button>

    <div class="dashboard-container">
        <?php include_once __DIR__ . '/sidebar.php'; ?>
        <main class="main-content">

            <!-- ── Top Header Bar ── -->
            <header class="header-top">
                <div class="header-page-title">
                    <?php echo htmlspecialchars($page_title); ?>
                </div>

                <div class="header-actions">
                    <!-- Dark-mode toggle -->
                    <button class="header-icon-btn" id="darkModeToggle" title="Toggle dark mode"
                        onclick="toggleDarkMode()">
                        <svg id="moonIcon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                        </svg>
                        <svg id="sunIcon" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24" style="display:none">
                            <circle cx="12" cy="12" r="5"></circle>
                            <line x1="12" y1="1" x2="12" y2="3"></line>
                            <line x1="12" y1="21" x2="12" y2="23"></line>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                            <line x1="1" y1="12" x2="3" y2="12"></line>
                            <line x1="21" y1="12" x2="23" y2="12"></line>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                        </svg>
                    </button>

                    <!-- Notifications bell -->
                    <a href="<?php echo BASE_URL; ?>modules/notifications/index.php" class="header-icon-btn notif-btn"
                        title="Notifications">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <?php if ($notif_count > 0): ?>
                            <span class="notif-dot"></span>
                        <?php endif; ?>
                    </a>

                    <!-- Settings shortcut -->
                    <?php if ($role_id == 1): ?>
                        <a href="<?php echo BASE_URL; ?>modules/settings/index.php" class="header-icon-btn"
                            title="Settings">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="3"></circle>
                                <path
                                    d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z">
                                </path>
                            </svg>
                        </a>
                    <?php endif; ?>

                    <!-- User pill -->
                    <div class="header-user-pill">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&background=2563eb&color=fff&size=64"
                            alt="<?php echo htmlspecialchars($user_name); ?>">
                        <div>
                            <div class="u-name"><?php echo htmlspecialchars($user_name); ?></div>
                            <div class="u-role"><?php echo htmlspecialchars($role_name); ?></div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- ── Page Content Wrapper ── -->
            <div class="main-inner">