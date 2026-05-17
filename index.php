<?php
include_once 'includes/header.php';
include_once 'core/functions.php';

$role_id   = $_SESSION['role_id'] ?? 1;
$user_id   = $_SESSION['user_id'] ?? 0;
$user_name = $_SESSION['user_name'] ?? 'User';
$role_name = $_SESSION['role_name'] ?? 'Admin';

// ---- Stats based on role ----
function safeCount($pdo, $sql, $params = []) {
    try {
        $s = $pdo->prepare($sql);
        $s->execute($params);
        return $s->fetchColumn() ?: 0;
    } catch (Exception $e) { return 0; }
}
function safeQuery($pdo, $sql, $params = []) {
    try {
        $s = $pdo->prepare($sql);
        $s->execute($params);
        return $s->fetchAll();
    } catch (Exception $e) { return []; }
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
    $wallet_pool     = 0; $total_merchants = 0; $pending_kyc = 0;
    $recent_leads    = safeQuery($pdo, "SELECT fr.*, u.name as agent_name FROM form_responses fr LEFT JOIN users u ON fr.agent_id = u.id ORDER BY fr.created_at DESC LIMIT 6");

} else {
    $total_leads  = safeCount($pdo, "SELECT COUNT(*) FROM form_responses WHERE agent_id = ?", [$user_id]);
    $approved     = safeCount($pdo, "SELECT COUNT(*) FROM form_responses WHERE agent_id = ? AND status='approved'", [$user_id]);
    $wallet_pool  = safeCount($pdo, "SELECT wallet_balance FROM users WHERE id = ?", [$user_id]);
    $pending_tasks= safeCount($pdo, "SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status='pending'", [$user_id]);
    $active_agents = null; $total_merchants = null; $pending_qc = null; $pending_kyc = null;
    $recent_leads  = safeQuery($pdo, "SELECT fr.*, u.name as agent_name FROM form_responses fr LEFT JOIN users u ON fr.agent_id = u.id WHERE fr.agent_id = ? ORDER BY fr.created_at DESC LIMIT 6", [$user_id]);
}

// Unread notifications
$notif_stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 4");
try { $notif_stmt->execute([$user_id]); $unread_notifs = $notif_stmt->fetchAll(); } catch(Exception $e) { $unread_notifs = []; }
?>

<div style="margin-bottom:2rem;">
    <h1 style="font-size:1.6rem; font-weight:700; margin-bottom:0.25rem;">
        <?php
        $hour = (int)date('H');
        $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
        echo $greeting . ', ' . htmlspecialchars(explode(' ', $user_name)[0]) . '! 👋';
        ?>
    </h1>
    <p style="color:var(--text-muted); font-size:0.875rem;">
        Logged in as <strong><?php echo $role_name; ?></strong> · <?php echo date('l, d F Y'); ?>
    </p>
</div>

<!-- KPI Cards -->
<div class="stats-grid" style="margin-bottom:2rem;">
    <?php if ($role_id <= 2): ?>
    <div class="stat-card glass-card" style="border-left:4px solid var(--primary);">
        <div class="stat-label">Total Leads</div>
        <div class="stat-value"><?php echo number_format($total_leads); ?></div>
        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">All submissions</div>
    </div>
    <div class="stat-card glass-card" style="border-left:4px solid var(--success);">
        <div class="stat-label">Active Agents</div>
        <div class="stat-value" style="color:var(--success);"><?php echo number_format($active_agents); ?></div>
        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;">Field & TL</div>
    </div>
    <div class="stat-card glass-card" style="border-left:4px solid var(--warning);">
        <div class="stat-label">Pending QC</div>
        <div class="stat-value" style="color:var(--warning);"><?php echo number_format($pending_qc); ?></div>
        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;"><a href="<?php echo BASE_URL; ?>modules/qc/review.php" style="color:var(--warning);">Review now →</a></div>
    </div>
    <?php if ($role_id == 1): ?>
    <div class="stat-card glass-card" style="border-left:4px solid var(--accent);">
        <div class="stat-label">Merchants</div>
        <div class="stat-value" style="color:var(--accent);"><?php echo number_format($total_merchants); ?></div>
    </div>
    <div class="stat-card glass-card" style="border-left:4px solid var(--success);">
        <div class="stat-label">Wallet Pool</div>
        <div class="stat-value" style="color:var(--success);">₹<?php echo number_format($wallet_pool, 0); ?></div>
    </div>
    <div class="stat-card glass-card" style="border-left:4px solid var(--danger);">
        <div class="stat-label">KYC Pending</div>
        <div class="stat-value" style="color:var(--danger);"><?php echo number_format($pending_kyc); ?></div>
        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;"><a href="<?php echo BASE_URL; ?>modules/kyc/admin_verify.php" style="color:var(--danger);">Verify →</a></div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- Non-admin personal stats -->
    <div class="stat-card glass-card" style="border-left:4px solid var(--primary);">
        <div class="stat-label">My Leads</div>
        <div class="stat-value"><?php echo number_format($total_leads); ?></div>
    </div>
    <div class="stat-card glass-card" style="border-left:4px solid var(--success);">
        <div class="stat-label">Approved</div>
        <div class="stat-value" style="color:var(--success);"><?php echo number_format($approved ?? 0); ?></div>
    </div>
    <div class="stat-card glass-card" style="border-left:4px solid var(--warning);">
        <div class="stat-label">Pending Tasks</div>
        <div class="stat-value" style="color:var(--warning);"><?php echo number_format($pending_tasks ?? 0); ?></div>
    </div>
    <div class="stat-card glass-card" style="border-left:4px solid var(--accent);">
        <div class="stat-label">Wallet Balance</div>
        <div class="stat-value" style="color:var(--accent);">₹<?php echo number_format($wallet_pool, 2); ?></div>
    </div>
    <?php endif; ?>
</div>

<!-- Main Grid -->
<div style="display:grid; grid-template-columns:2fr 1fr; gap:2rem;">
    <!-- Recent Leads -->
    <div class="glass-card" style="padding:1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
            <h3 style="font-size:1.1rem; font-weight:700;">Recent Submissions</h3>
            <a href="<?php echo BASE_URL; ?>modules/crm/leads.php" class="btn btn-primary" style="padding:6px 14px; font-size:0.8rem;">View All</a>
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
                        <td style="font-family:monospace; color:var(--text-muted); font-size:0.8rem;">#FR-<?php echo str_pad($lead['id'],4,'0',STR_PAD_LEFT); ?></td>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($lead['agent_name']??'?'); ?>&background=4f46e5&color=fff&size=32" style="width:26px; height:26px; border-radius:50%;">
                                <span style="font-size:0.875rem;"><?php echo htmlspecialchars($lead['agent_name'] ?? 'N/A'); ?></span>
                            </div>
                        </td>
                        <td><span class="badge badge-<?php echo $cls; ?>"><?php echo str_replace('_',' ',$lead['status']); ?></span></td>
                        <td style="font-size:0.8rem; color:var(--text-muted);"><?php echo date('d M, H:i', strtotime($lead['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Panel -->
    <div style="display:flex; flex-direction:column; gap:1.5rem;">
        <!-- Quick Actions -->
        <div class="glass-card" style="padding:1.5rem;">
            <h3 style="font-size:1rem; font-weight:700; margin-bottom:1rem;">Quick Actions</h3>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <?php if ($role_id == 1 || $role_id == 2): ?>
                <a href="<?php echo BASE_URL; ?>modules/qc/review.php" class="btn glass-card" style="justify-content:flex-start; font-size:0.875rem;">🔍 Review QC Queue</a>
                <a href="<?php echo BASE_URL; ?>modules/team/users.php" class="btn glass-card" style="justify-content:flex-start; font-size:0.875rem;">👤 Add New User</a>
                <a href="<?php echo BASE_URL; ?>modules/analytics/dashboard.php" class="btn glass-card" style="justify-content:flex-start; font-size:0.875rem;">📊 View Analytics</a>
                <?php if ($role_id == 1): ?>
                <a href="<?php echo BASE_URL; ?>modules/accounts/invoices.php" class="btn glass-card" style="justify-content:flex-start; font-size:0.875rem;">🧾 Create Invoice</a>
                <a href="<?php echo BASE_URL; ?>modules/notifications/index.php" class="btn glass-card" style="justify-content:flex-start; font-size:0.875rem;">📢 Send Announcement</a>
                <?php endif; ?>
                <?php else: ?>
                <a href="<?php echo BASE_URL; ?>modules/crm/create_lead.php" class="btn btn-primary" style="justify-content:flex-start; font-size:0.875rem;">➕ Add New Lead</a>
                <a href="<?php echo BASE_URL; ?>modules/payroll/attendance.php" class="btn glass-card" style="justify-content:flex-start; font-size:0.875rem;">📍 Mark Attendance</a>
                <a href="<?php echo BASE_URL; ?>modules/wallet/index.php" class="btn glass-card" style="justify-content:flex-start; font-size:0.875rem;">💰 My Wallet</a>
                <a href="<?php echo BASE_URL; ?>modules/kyc/verify.php" class="btn glass-card" style="justify-content:flex-start; font-size:0.875rem;">📋 KYC Status</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Unread Notifications -->
        <?php if (!empty($unread_notifs)): ?>
        <div class="glass-card" style="padding:1.5rem;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <h3 style="font-size:1rem; font-weight:700;">🔔 Notifications</h3>
                <a href="<?php echo BASE_URL; ?>modules/notifications/index.php" style="font-size:0.75rem; color:var(--primary);">View all</a>
            </div>
            <div style="display:flex; flex-direction:column; gap:8px;">
                <?php foreach ($unread_notifs as $n):
                    $colors = ['info'=>'var(--accent)','success'=>'var(--success)','warning'=>'var(--warning)','danger'=>'var(--danger)'];
                    $color  = $colors[$n['type']] ?? 'var(--primary)';
                ?>
                <div style="padding:10px 12px; background:var(--bg-main); border-radius:10px; border-left:3px solid <?php echo $color; ?>;">
                    <div style="font-size:0.8rem; font-weight:600; color:<?php echo $color; ?>;"><?php echo htmlspecialchars($n['title']); ?></div>
                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:2px;"><?php echo htmlspecialchars(substr($n['message'],0,60)); ?>...</div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
