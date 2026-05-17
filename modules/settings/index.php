<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    // In a real system, save to a settings table. For now, session-based.
    $success = "Settings saved successfully!";
}

$roles = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$db_size = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
?>

<div class="page-header">
    <div><h1>System Settings</h1><p>Configure system preferences and manage roles.</p></div>
</div>

<?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem;">
    <!-- General Settings -->
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="margin-bottom:1.5rem;">General Settings</h3>
        <form method="POST">
            <div class="form-group"><label class="form-label">System Name</label><input type="text" name="site_name" class="form-control" value="<?php echo SITE_NAME; ?>"></div>
            <div class="form-group"><label class="form-label">Base URL</label><input type="text" name="base_url" class="form-control" value="<?php echo BASE_URL; ?>"></div>
            <div class="form-group">
                <label class="form-label">Default Currency</label>
                <select name="currency" class="form-control">
                    <option value="INR" selected>INR (₹)</option>
                    <option value="USD">USD ($)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Default GST %</label>
                <input type="number" name="default_gst" class="form-control" value="18" step="0.01">
            </div>
            <button type="submit" name="save_settings" class="btn btn-primary">Save Settings</button>
        </form>
    </div>

    <!-- System Info -->
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="margin-bottom:1.5rem;">System Information</h3>
        <div style="display:flex; flex-direction:column; gap:12px;">
            <?php
            $info = [
                ['label'=>'PHP Version',    'value'=>phpversion()],
                ['label'=>'Database',       'value'=>'MySQL / MariaDB'],
                ['label'=>'Database Size',  'value'=>$db_size . ' MB'],
                ['label'=>'Total Users',    'value'=>$total_users],
                ['label'=>'Upload Dir',     'value'=>UPLOAD_DIR],
                ['label'=>'Server Time',    'value'=>date('d M Y, H:i:s')],
            ];
            foreach ($info as $item):
            ?>
            <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--border);">
                <span style="color:var(--text-muted); font-size:0.875rem;"><?php echo $item['label']; ?></span>
                <span style="font-weight:600; font-size:0.875rem;"><?php echo htmlspecialchars($item['value']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Roles Management -->
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="margin-bottom:1.5rem;">System Roles</h3>
        <div style="display:flex; flex-direction:column; gap:8px;">
            <?php foreach ($roles as $role): ?>
            <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 14px; background:var(--bg-main); border-radius:10px; border:1px solid var(--border);">
                <div>
                    <span style="font-weight:600;"><?php echo $role['role_name']; ?></span>
                    <span style="font-size:0.75rem; color:var(--text-muted); margin-left:8px;">ID: <?php echo $role['id']; ?></span>
                </div>
                <span class="badge badge-primary">Role</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="margin-bottom:1.5rem;">Quick Actions</h3>
        <div style="display:flex; flex-direction:column; gap:10px;">
            <a href="<?php echo BASE_URL; ?>core/init_db.php" target="_blank" class="btn glass-card" style="justify-content:flex-start;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4h16v16H4z"></path></svg>
                Re-initialize Database
            </a>
            <a href="<?php echo BASE_URL; ?>modules/analytics/dashboard.php" class="btn glass-card" style="justify-content:flex-start;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                View Analytics
            </a>
            <a href="<?php echo BASE_URL; ?>modules/notifications/index.php" class="btn glass-card" style="justify-content:flex-start;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                Send Announcement
            </a>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
