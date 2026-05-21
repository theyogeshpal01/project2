<?php
include_once 'includes/header.php';
include_once 'core/functions.php';

$role_id   = $_SESSION['role_id']   ?? 1;
$user_id   = $_SESSION['user_id']   ?? 0;
$user_name = $_SESSION['user_name'] ?? 'User';
$role_name = $_SESSION['role_name'] ?? 'Admin';

function safeCount($pdo, $sql, $params = []) {
    try { $s = $pdo->prepare($sql); $s->execute($params); return $s->fetchColumn() ?: 0; }
    catch (Exception $e) { return 0; }
}
function safeQuery($pdo, $sql, $params = []) {
    try { $s = $pdo->prepare($sql); $s->execute($params); return $s->fetchAll(); }
    catch (Exception $e) { return []; }
}

if ($role_id == 1) {
    $total_leads     = safeCount($pdo, "SELECT COUNT(*) FROM form_responses");
    $active_agents   = safeCount($pdo, "SELECT COUNT(*) FROM users WHERE role_id IN (3,4) AND status='active'");
    $total_merchants = safeCount($pdo, "SELECT COUNT(*) FROM merchants");
    $wallet_pool     = safeCount($pdo, "SELECT SUM(wallet_balance) FROM users");
    $pending_qc      = safeCount($pdo, "SELECT COUNT(*) FROM form_responses WHERE status='pending'");
    $pending_kyc     = safeCount($pdo, "SELECT COUNT(*) FROM kyc_documents WHERE status='pending'");
    $recent_leads    = safeQuery($pdo, "SELECT fr.*, u.name as agent_name FROM form_responses fr LEFT JOIN users u ON fr.agent_id = u.id ORDER BY fr.created_at DESC LIMIT 6");
} elseif ($role_id == 2) {
    $total_leads     = safeCount($pdo, "SELECT COUNT(*) FROM form_responses fr JOIN users u ON fr.agent_id = u.id WHERE u.manager_id = ?", [$user_id]);
    $active_agents   = safeCount($pdo, "SELECT COUNT(*) FROM users WHERE manager_id = ? AND status='active'", [$user_id]);
    $pending_qc      = safeCount($pdo, "SELECT COUNT(*) FROM form_responses WHERE status='pending'");
    $wallet_pool = $total_merchants = $pending_kyc = 0;
    $recent_leads    = safeQuery($pdo, "SELECT fr.*, u.name as agent_name FROM form_responses fr LEFT JOIN users u ON fr.agent_id = u.id ORDER BY fr.created_at DESC LIMIT 6");
} else {
    $total_leads  = safeCount($pdo, "SELECT COUNT(*) FROM form_responses WHERE agent_id = ?", [$user_id]);
    $approved     = safeCount($pdo, "SELECT COUNT(*) FROM form_responses WHERE agent_id = ? AND status='approved'", [$user_id]);
    $wallet_pool  = safeCount($pdo, "SELECT wallet_balance FROM users WHERE id = ?", [$user_id]);
    $pending_tasks= safeCount($pdo, "SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status='pending'", [$user_id]);
    $active_agents = $total_merchants = $pending_qc = $pending_kyc = null;
    $recent_leads  = safeQuery($pdo, "SELECT fr.*, u.name as agent_name FROM form_responses fr LEFT JOIN users u ON fr.agent_id = u.id WHERE fr.agent_id = ? ORDER BY fr.created_at DESC LIMIT 6", [$user_id]);
}

$unread_notifs = safeQuery($pdo, "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 4", [$user_id]);

$hour = (int)date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
$first_name = explode(' ', $user_name)[0];
?>

<!-- Greeting -->
<div style="margin-bottom:1.75rem;">
    <h1 style="font-size:1.5rem; font-weight:800; margin-bottom:0.2rem;">
        <?php echo $greeting . ', ' . htmlspecialchars($first_name) . '! 👋'; ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.85rem;">
        Logged in as <strong><?php echo htmlspecialchars($role_name); ?></strong> &middot; <?php echo date('l, d F Y'); ?>
    </p>
</div>

<!-- KPI Stat Cards -->
<div class="stats-grid" style="margin-bottom:2rem;">
<?php if ($role_id <= 2): ?>

    <div class="stat-card glass-card">
        <div class="stat-card-text">
            <div class="stat-label">Total Leads</div>
            <div class="stat-value"><?php echo number_format($total_leads); ?></div>
            <div class="stat-sub">All submissions</div>
        </div>
        <div class="stat-card-icon" style="background:rgba(37,99,235,0.08);color:var(--primary);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"></path></svg>
        </div>
    </div>

    <div class="stat-card glass-card">
        <div class="stat-card-text">
            <div class="stat-label">Active Agents</div>
            <div class="stat-value" style="color:var(--success);"><?php echo number_format($active_agents); ?></div>
            <div class="stat-sub">Field & TL</div>
        </div>
        <div class="stat-card-icon" style="background:rgba(16,185,129,0.08);color:var(--success);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        </div>
    </div>

    <div class="stat-card glass-card">
        <div class="stat-card-text">
            <div class="stat-label">Pending QC</div>
            <div class="stat-value" style="color:var(--warning);"><?php echo number_format($pending_qc); ?></div>
            <div class="stat-sub"><a href="<?php echo BASE_URL; ?>modules/qc/review.php" style="color:var(--warning);">Review now →</a></div>
        </div>
        <div class="stat-card-icon" style="background:rgba(245,158,11,0.08);color:var(--warning);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
        </div>
    </div>

    <?php if ($role_id == 1): ?>
    <div class="stat-card glass-card">
        <div class="stat-card-text">
            <div class="stat-label">Merchants</div>
            <div class="stat-value" style="color:var(--accent);"><?php echo number_format($total_merchants); ?></div>
        </div>
        <div class="stat-card-icon" style="background:rgba(8,145,178,0.08);color:var(--accent);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><path d="M9 22V12h6v10"></path></svg>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-card-text">
            <div class="stat-label">Wallet Pool</div>
            <div class="stat-value" style="color:var(--success);">₹<?php echo number_format($wallet_pool, 0); ?></div>
        </div>
        <div class="stat-card-icon" style="background:rgba(16,185,129,0.08);color:var(--success);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-card-text">
            <div class="stat-label">KYC Pending</div>
            <div class="stat-value" style="color:var(--danger);"><?php echo number_format($pending_kyc); ?></div>
            <div class="stat-sub"><a href="<?php echo BASE_URL; ?>modules/kyc/admin_verify.php" style="color:var(--danger);">Verify →</a></div>
        </div>
        <div class="stat-card-icon" style="background:rgba(239,68,68,0.08);color:var(--danger);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M8 10h8M8 14h5"></path></svg>
        </div>
    </div>
    <?php endif; ?>

<?php else: ?>

    <div class="stat-card glass-card">
        <div class="stat-card-text">
            <div class="stat-label">My Leads</div>
            <div class="stat-value"><?php echo number_format($total_leads); ?></div>
        </div>
        <div class="stat-card-icon" style="background:rgba(37,99,235,0.08);color:var(--primary);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"></path></svg>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-card-text">
            <div class="stat-label">Approved</div>
            <div class="stat-value" style="color:var(--success);"><?php echo number_format($approved ?? 0); ?></div>
        </div>
        <div class="stat-card-icon" style="background:rgba(16,185,129,0.08);color:var(--success);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path></svg>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-card-text">
            <div class="stat-label">Pending Tasks</div>
            <div class="stat-value" style="color:var(--warning);"><?php echo number_format($pending_tasks ?? 0); ?></div>
        </div>
        <div class="stat-card-icon" style="background:rgba(245,158,11,0.08);color:var(--warning);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 11l3 3L22 4"></path><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path></svg>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-card-text">
            <div class="stat-label">Wallet Balance</div>
            <div class="stat-value" style="color:var(--accent);">₹<?php echo number_format($wallet_pool, 0); ?></div>
        </div>
        <div class="stat-card-icon" style="background:rgba(8,145,178,0.08);color:var(--accent);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
        </div>
    </div>

<?php endif; ?>
</div>

<!-- Main Grid -->
<div style="display:grid; grid-template-columns:2fr 1fr; gap:1.5rem;">

    <!-- Recent Submissions Table -->
    <div class="glass-card table-card">
        <div class="table-header">
            <div>
                <h3 style="font-size:0.95rem; font-weight:700;">Recent Submissions</h3>
                <p style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">Latest lead activity</p>
            </div>
            <a href="<?php echo BASE_URL; ?>modules/crm/leads.php" class="btn btn-primary btn-sm">View All</a>
        </div>
        <div class="data-table-container">
            <table>
                <thead>
                    <tr><th>ID</th><th>Agent</th><th>Status</th><th>Date</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_leads)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:2rem; color:var(--text-muted);">No submissions yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($recent_leads as $lead):
                        $sc = ['approved'=>'success','rejected'=>'danger','rework'=>'warning','pending'=>'primary'];
                        $cls = $sc[$lead['status']] ?? 'muted';
                    ?>
                    <tr>
                        <td style="font-family:monospace; color:var(--text-muted); font-size:0.78rem;">#FR-<?php echo str_pad($lead['id'],4,'0',STR_PAD_LEFT); ?></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($lead['agent_name']??'?'); ?>&background=2563eb&color=fff&size=32" style="width:26px; height:26px; border-radius:50%;">
                                <span style="font-size:0.875rem; font-weight:500;"><?php echo htmlspecialchars($lead['agent_name'] ?? 'N/A'); ?></span>
                            </div>
                        </td>
                        <td><span class="badge badge-<?php echo $cls; ?>"><?php echo str_replace('_',' ',$lead['status']); ?></span></td>
                        <td style="font-size:0.78rem; color:var(--text-muted);"><?php echo date('d M, H:i', strtotime($lead['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Panel -->
    <div style="display:flex; flex-direction:column; gap:1.25rem;">

        <!-- Quick Actions -->
        <div class="glass-card" style="padding:1.25rem;">
            <h3 style="font-size:0.9rem; font-weight:700; margin-bottom:0.875rem;">Quick Actions</h3>
            <div style="display:flex; flex-direction:column; gap:6px;">
                <?php if ($role_id == 1 || $role_id == 2): ?>
                <a href="<?php echo BASE_URL; ?>modules/qc/review.php" class="btn" style="justify-content:flex-start; font-size:0.82rem;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    Review QC Queue
                </a>
                <a href="<?php echo BASE_URL; ?>modules/team/users.php" class="btn" style="justify-content:flex-start; font-size:0.82rem;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>
                    Add New User
                </a>
                <a href="<?php echo BASE_URL; ?>modules/analytics/dashboard.php" class="btn" style="justify-content:flex-start; font-size:0.82rem;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                    View Analytics
                </a>
                <?php if ($role_id == 1): ?>
                <a href="<?php echo BASE_URL; ?>modules/accounts/invoices.php" class="btn" style="justify-content:flex-start; font-size:0.82rem;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                    Create Invoice
                </a>
                <a href="<?php echo BASE_URL; ?>modules/notifications/index.php" class="btn" style="justify-content:flex-start; font-size:0.82rem;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                    Send Announcement
                </a>
                <?php endif; ?>
                <?php else: ?>
                <a href="<?php echo BASE_URL; ?>modules/crm/create_lead.php" class="btn btn-primary" style="justify-content:flex-start; font-size:0.82rem;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Add New Lead
                </a>
                <a href="<?php echo BASE_URL; ?>modules/payroll/attendance.php" class="btn" style="justify-content:flex-start; font-size:0.82rem;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                    Mark Attendance
                </a>
                <a href="<?php echo BASE_URL; ?>modules/wallet/index.php" class="btn" style="justify-content:flex-start; font-size:0.82rem;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
                    My Wallet
                </a>
                <a href="<?php echo BASE_URL; ?>modules/kyc/verify.php" class="btn" style="justify-content:flex-start; font-size:0.82rem;">
                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"></rect><path d="M8 10h8M8 14h5"></path></svg>
                    KYC Status
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Unread Notifications -->
        <?php if (!empty($unread_notifs)): ?>
        <div class="glass-card" style="padding:1.25rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.875rem;">
                <h3 style="font-size:0.9rem; font-weight:700;">Notifications</h3>
                <a href="<?php echo BASE_URL; ?>modules/notifications/index.php" style="font-size:0.75rem; color:var(--primary);">View all</a>
            </div>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <?php foreach ($unread_notifs as $n):
                    $colors = ['info'=>'var(--accent)','success'=>'var(--success)','warning'=>'var(--warning)','danger'=>'var(--danger)'];
                    $color  = $colors[$n['type']] ?? 'var(--primary)';
                ?>
                <div style="padding:10px 12px; background:var(--bg-main); border-radius:8px; border-left:3px solid <?php echo $color; ?>;">
                    <div style="font-size:0.78rem; font-weight:600; color:<?php echo $color; ?>;"><?php echo htmlspecialchars($n['title']); ?></div>
                    <div style="font-size:0.72rem; color:var(--text-muted); margin-top:2px;"><?php echo htmlspecialchars(substr($n['message'],0,60)); ?>...</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
