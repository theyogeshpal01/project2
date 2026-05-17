<?php
require_once __DIR__ . '/../core/config.php';

if (!isset($_SESSION['user_id']) && strpos($_SERVER['PHP_SELF'], 'login.php') === false) {
    header("Location: " . BASE_URL . "modules/auth/login.php");
    exit();
}

$user_name = $_SESSION['user_name'] ?? 'Guest';
$role_name = $_SESSION['role_name'] ?? 'Visitor';
$role_id   = $_SESSION['role_id'] ?? 1;

// Unread notifications
$notif_count = 0;
try {
    $ns = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $ns->execute([$_SESSION['user_id'] ?? 0]);
    $notif_count = $ns->fetchColumn();
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
</head>
<body>
<button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
    </svg>
</button>

<div class="dashboard-container">
<?php include_once __DIR__ . '/sidebar.php'; ?>
    <main class="main-content">
        <header class="header-top">
            <div class="search-bar">
                <h2 id="page-title" style="font-size:1.1rem; font-weight:600; color:var(--text-muted);">
                    <?php echo SITE_NAME; ?>
                </h2>
            </div>
            <div class="user-profile">
                <!-- Notifications Bell -->
                <a href="<?php echo BASE_URL; ?>modules/notifications/index.php" style="position:relative; color:var(--text-muted); padding:8px; border-radius:10px; transition:all 0.2s;" title="Notifications">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <?php if ($notif_count > 0): ?>
                        <span style="position:absolute; top:4px; right:4px; background:var(--danger); color:white; font-size:0.6rem; font-weight:700; width:16px; height:16px; border-radius:50%; display:flex; align-items:center; justify-content:center;">
                            <?php echo $notif_count > 9 ? '9+' : $notif_count; ?>
                        </span>
                    <?php endif; ?>
                </a>

                <!-- User Info -->
                <div style="display:flex; align-items:center; gap:10px; padding:6px 12px; background:var(--bg-main); border-radius:12px; border:1px solid var(--border);">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user_name); ?>&background=4f46e5&color=fff&size=64" style="width:32px; height:32px; border-radius:50%;" alt="User">
                    <div>
                        <div style="font-size:0.875rem; font-weight:600; line-height:1.2;"><?php echo htmlspecialchars($user_name); ?></div>
                        <div style="font-size:0.7rem; color:var(--text-muted);"><?php echo htmlspecialchars($role_name); ?></div>
                    </div>
                </div>
            </div>
        </header>
