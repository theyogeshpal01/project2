<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

$active_cat = $_GET['cat'] ?? '';

$total_users = 0;
$db_size = '0';
$roles = [];
try {
    $roles       = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
    $total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $db_size     = $pdo->query("SELECT ROUND(SUM(data_length+index_length)/1024/1024,2) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchColumn();
} catch(Exception $e) {}
?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-left">
        <div class="page-header-icon" style="background:rgba(37,99,235,0.1);color:var(--primary);">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="3"></circle>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>
        </div>
        <div>
            <h1>System Settings</h1>
            <p>Manage your organization's settings and configurations</p>
        </div>
    </div>
</div>

<!-- Settings Categories -->
<div class="glass-card" style="padding:1.5rem; margin-bottom:1.5rem;">
    <h3 style="font-size:0.95rem; font-weight:700; margin-bottom:1.25rem; color:var(--text-muted);">Settings Categories</h3>
    <div class="settings-category-grid">

        <a href="?cat=roles" class="settings-cat-card <?php echo $active_cat==='roles' ? 'active-cat' : ''; ?>">
            <div class="cat-icon">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
            </div>
            <div>
                <div class="cat-title">Employment Status</div>
                <div class="cat-desc">Manage employment types and status categories</div>
            </div>
        </a>

        <a href="?cat=departments" class="settings-cat-card <?php echo $active_cat==='departments' ? 'active-cat' : ''; ?>">
            <div class="cat-icon">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="2" y="7" width="20" height="14" rx="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
                </svg>
            </div>
            <div>
                <div class="cat-title">Departments</div>
                <div class="cat-desc">Manage company departments and organizational structure</div>
            </div>
        </a>

        <a href="?cat=designations" class="settings-cat-card <?php echo $active_cat==='designations' ? 'active-cat' : ''; ?>">
            <div class="cat-icon">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="2" y="3" width="20" height="14" rx="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
            </div>
            <div>
                <div class="cat-title">Designations</div>
                <div class="cat-desc">Manage employee job titles and roles</div>
            </div>
        </a>

        <a href="?cat=shifts" class="settings-cat-card <?php echo $active_cat==='shifts' ? 'active-cat' : ''; ?>">
            <div class="cat-icon">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
            <div>
                <div class="cat-title">Work Shifts</div>
                <div class="cat-desc">Manage work schedules and shift timings</div>
            </div>
        </a>

        <a href="?cat=locations" class="settings-cat-card <?php echo $active_cat==='locations' ? 'active-cat' : ''; ?>">
            <div class="cat-icon">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle>
                </svg>
            </div>
            <div>
                <div class="cat-title">Office Locations</div>
                <div class="cat-desc">Manage office locations</div>
            </div>
        </a>

        <a href="?cat=leaves" class="settings-cat-card <?php echo $active_cat==='leaves' ? 'active-cat' : ''; ?>">
            <div class="cat-icon">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </div>
            <div>
                <div class="cat-title">Leave Policies</div>
                <div class="cat-desc">Manage leave policies</div>
            </div>
        </a>

        <a href="?cat=general" class="settings-cat-card <?php echo $active_cat==='general' ? 'active-cat' : ''; ?>">
            <div class="cat-icon">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
            </div>
            <div>
                <div class="cat-title">General Settings</div>
                <div class="cat-desc">System name, currency, and core preferences</div>
            </div>
        </a>

    </div>

    <!-- Coming Soon -->
    <h3 style="font-size:0.85rem; font-weight:600; margin:1.5rem 0 1rem; color:var(--text-muted);">Coming Soon</h3>
    <div class="settings-category-grid">
        <?php
        $soon = [
            ['icon'=>'<circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>',           'title'=>'Attendance Rules',    'desc'=>'Configure attendance policies'],
            ['icon'=>'<line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>', 'title'=>'Payroll Settings',    'desc'=>'Configure payroll calculations'],
            ['icon'=>'<circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"></path>', 'title'=>'System Preferences',  'desc'=>'Global system configuration'],
            ['icon'=>'<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path>', 'title'=>'Notification Settings','desc'=>'Manage alerts and notifications'],
        ];
        foreach ($soon as $s): ?>
        <div class="settings-cat-card coming-soon">
            <div class="cat-icon">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><?php echo $s['icon']; ?></svg>
            </div>
            <div>
                <div class="cat-title"><?php echo $s['title']; ?></div>
                <div class="cat-desc"><?php echo $s['desc']; ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Dynamic content per category -->
<?php if ($active_cat === 'general'): ?>
<div class="glass-card" style="padding:1.5rem; margin-bottom:1.5rem;">
    <h3 style="font-size:1rem; font-weight:700; margin-bottom:1.25rem;">General Settings</h3>
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
        <div>
            <form method="POST">
                <div class="form-group"><label class="form-label">System Name</label><input type="text" class="form-control" value="<?php echo SITE_NAME; ?>"></div>
                <div class="form-group"><label class="form-label">Base URL</label><input type="text" class="form-control" value="<?php echo BASE_URL; ?>"></div>
                <div class="form-group">
                    <label class="form-label">Default Currency</label>
                    <select class="form-control"><option selected>INR (₹)</option><option>USD ($)</option></select>
                </div>
                <div class="form-group"><label class="form-label">Default GST %</label><input type="number" class="form-control" value="18" step="0.01"></div>
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </form>
        </div>
        <div>
            <h4 style="font-size:0.875rem; font-weight:700; margin-bottom:1rem; color:var(--text-muted);">System Information</h4>
            <?php
            $info = [
                ['PHP Version', phpversion()],
                ['Database',    'MySQL / MariaDB'],
                ['DB Size',     $db_size . ' MB'],
                ['Total Users', $total_users],
                ['Server Time', date('d M Y, H:i:s')],
            ];
            foreach ($info as [$label, $value]): ?>
            <div style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid var(--border); font-size:0.875rem;">
                <span style="color:var(--text-muted);"><?php echo $label; ?></span>
                <span style="font-weight:600;"><?php echo htmlspecialchars($value); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php elseif ($active_cat === 'roles' || $active_cat === 'designations'): ?>
<div class="glass-card table-card" style="margin-bottom:1.5rem;">
    <div class="table-header">
        <div>
            <h3 style="font-size:1rem; font-weight:700;"><?php echo $active_cat==='roles' ? 'Employment Status / Roles' : 'Designations'; ?></h3>
            <p style="color:var(--text-muted); font-size:0.8rem; margin-top:2px;">Manage system roles and access levels</p>
        </div>
    </div>
    <div class="data-table-container">
        <table>
            <thead><tr><th>ID</th><th>Role Name</th><th>Type</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($roles as $role): ?>
                <tr>
                    <td style="font-family:monospace; color:var(--text-muted); font-size:0.8rem;">#<?php echo str_pad($role['id'],3,'0',STR_PAD_LEFT); ?></td>
                    <td style="font-weight:600;"><?php echo htmlspecialchars($role['role_name']); ?></td>
                    <td><span class="badge badge-primary">System Role</span></td>
                    <td><span class="badge badge-success">Active</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($active_cat === 'departments'): ?>
<div class="glass-card table-card" style="margin-bottom:1.5rem;">
    <div class="table-header">
        <div>
            <h3 style="font-size:1rem; font-weight:700;">Departments</h3>
            <p style="color:var(--text-muted); font-size:0.8rem; margin-top:2px;">Manage company departments</p>
        </div>
        <a href="<?php echo BASE_URL; ?>modules/team/manage.php" class="btn btn-primary">+ Manage Teams</a>
    </div>
    <div style="padding:2rem; text-align:center; color:var(--text-muted); font-size:0.875rem;">
        Department management is handled in <a href="<?php echo BASE_URL; ?>modules/team/manage.php" style="color:var(--primary); font-weight:600;">Team Management</a>.
    </div>
</div>
<?php endif; ?>

<!-- System Quick Actions -->
<div class="glass-card" style="padding:1.5rem;">
    <h3 style="font-size:0.95rem; font-weight:700; margin-bottom:1rem;">Quick Actions</h3>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <a href="<?php echo BASE_URL; ?>modules/analytics/dashboard.php" class="btn">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
            View Analytics
        </a>
        <a href="<?php echo BASE_URL; ?>modules/notifications/index.php" class="btn">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
            Send Announcement
        </a>
        <a href="<?php echo BASE_URL; ?>modules/reports/onboarding.php" class="btn">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path></svg>
            View Reports
        </a>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
