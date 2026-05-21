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

// ── Core Stats ──
$team_size       = safeCount($pdo, "SELECT COUNT(*) FROM users WHERE status='active'");
$total_leads     = safeCount($pdo, "SELECT COUNT(*) FROM form_responses");
$pending_qc      = safeCount($pdo, "SELECT COUNT(*) FROM form_responses WHERE status='pending'");
$pending_kyc     = safeCount($pdo, "SELECT COUNT(*) FROM kyc_documents WHERE status='pending'");
$total_merchants = safeCount($pdo, "SELECT COUNT(*) FROM merchants");
$wallet_pool     = safeCount($pdo, "SELECT SUM(wallet_balance) FROM users");
$total_assets    = safeCount($pdo, "SELECT COUNT(*) FROM inventory");
$salary_budget   = safeCount($pdo, "SELECT SUM(base_salary) FROM users WHERE status='active'");
$avg_salary      = $team_size > 0 ? round($salary_budget / $team_size) : 0;

// ── Attendance Today ──
$today_present   = safeCount($pdo, "SELECT COUNT(*) FROM attendance WHERE DATE(date)=CURDATE() AND status='present'");
$today_att_pct   = $team_size > 0 ? round(($today_present / $team_size) * 100, 1) : 0;

// ── Leaves ──
$pending_leaves  = safeCount($pdo, "SELECT COUNT(*) FROM leaves WHERE status='pending'");
$approved_leaves = safeCount($pdo, "SELECT COUNT(*) FROM leaves WHERE status='approved'");
$rejected_leaves = safeCount($pdo, "SELECT COUNT(*) FROM leaves WHERE status='rejected'");

// ── Monthly Attendance ──
$month_days      = (int)date('d');
$month_present   = safeCount($pdo, "SELECT COUNT(DISTINCT user_id) FROM attendance WHERE MONTH(date)=MONTH(CURDATE()) AND YEAR(date)=YEAR(CURDATE()) AND status='present'");
$monthly_pct     = $team_size > 0 ? round(($month_present / max($team_size,1)) * 100, 1) : 0;

// ── Department Distribution ──
$dept_dist = safeQuery($pdo, "SELECT t.team_name, COUNT(u.id) as emp_count FROM users u LEFT JOIN teams t ON u.team_id = t.id WHERE u.status='active' GROUP BY t.id, t.team_name ORDER BY emp_count DESC LIMIT 8");

// ── Asset Distribution by Category ──
$asset_dist = safeQuery($pdo, "SELECT category, COUNT(*) as cnt FROM inventory GROUP BY category ORDER BY cnt DESC LIMIT 6");

// ── Recent Activities (new employees) ──
$recent_users = safeQuery($pdo, "SELECT u.name, u.created_at, r.role_name, t.team_name FROM users u LEFT JOIN roles r ON u.role_id = r.id LEFT JOIN teams t ON u.team_id = t.id ORDER BY u.created_at DESC LIMIT 5");

// ── Department Statistics ──
$dept_stats = safeQuery($pdo, "SELECT t.team_name as dept_name, COUNT(u.id) as emp_count, COALESCE(AVG(u.base_salary),0) as avg_salary, COALESCE(SUM(u.base_salary),0) as total_salary FROM users u LEFT JOIN teams t ON u.team_id = t.id WHERE u.status='active' GROUP BY t.id, t.team_name ORDER BY emp_count DESC");

// ── Attendance Trend (last 30 days) ──
$att_trend = safeQuery($pdo, "SELECT date, COUNT(*) as cnt FROM attendance WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND status='present' GROUP BY date ORDER BY date ASC");

// ── Leave Trend (last 6 months) ──
$leave_trend = safeQuery($pdo, "SELECT DATE_FORMAT(created_at,'%b %Y') as mon, COUNT(*) as cnt FROM leaves WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY MIN(created_at) ASC");

// ── System Alerts ──
$alerts = [];
if ($today_att_pct < 70 && $team_size > 0) {
    $alerts[] = ['type'=>'danger', 'title'=>'Low Attendance Rate', 'msg'=>'Current rate: '.$today_att_pct.'%', 'date'=>date('d/m/Y')];
}
if ($pending_leaves > 5) {
    $alerts[] = ['type'=>'warning', 'title'=>'High Pending Leaves', 'msg'=>$pending_leaves.' leave requests awaiting approval', 'date'=>date('d/m/Y')];
}
if ($pending_kyc > 0) {
    $alerts[] = ['type'=>'warning', 'title'=>'KYC Verification Pending', 'msg'=>$pending_kyc.' documents need verification', 'date'=>date('d/m/Y')];
}
if ($pending_qc > 10) {
    $alerts[] = ['type'=>'danger', 'title'=>'QC Backlog Alert', 'msg'=>$pending_qc.' leads in QC queue', 'date'=>date('d/m/Y')];
}
?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
/* ── Dashboard Header ── */
.emp-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1.5rem;
}
.emp-header h1 {
    font-size: 1.4rem;
    font-weight: 800;
    color: var(--text-main);
    margin-bottom: 0.2rem;
}
.emp-header .sub-date {
    font-size: 0.82rem;
    color: var(--text-muted);
}
.live-clock {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--primary);
    font-variant-numeric: tabular-nums;
}

/* ── Top 3-col Cards ── */
.top-cards-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 1.25rem;
    margin-bottom: 1.5rem;
}
.top-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.4rem 1.5rem;
}
.top-card-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 1.1rem;
}
.tc-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: rgba(37,99,235,0.08);
    color: var(--primary);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.tc-icon svg { width: 17px; height: 17px; }
.tc-title { font-size: 0.88rem; font-weight: 700; color: var(--text-main); line-height: 1.2; }
.tc-sub   { font-size: 0.73rem; color: var(--text-muted); }

/* ── Stats Row ── */
.stats-row-5 {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1rem;
    margin-bottom: 1.1rem;
}
.stats-row-4 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.emp-stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.1rem 1.25rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    transition: box-shadow 0.2s;
}
.emp-stat-card:hover { box-shadow: var(--shadow-md); }
.esi-label {
    font-size: 0.74rem;
    color: var(--text-muted);
    font-weight: 500;
    margin-bottom: 4px;
}
.esi-value {
    font-size: 1.6rem;
    font-weight: 800;
    line-height: 1;
    color: var(--text-main);
}
.esi-sub {
    font-size: 0.69rem;
    color: var(--text-muted);
    margin-top: 3px;
}
.esi-link {
    font-size: 0.69rem;
    margin-top: 3px;
    font-weight: 600;
}
.emp-stat-icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.emp-stat-icon svg { width: 22px; height: 22px; }

/* small stat cards (2nd row) */
.small-stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1rem 1.1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
}
.ssc-left .ssc-val {
    font-size: 1.3rem;
    font-weight: 800;
    line-height: 1.1;
}
.ssc-left .ssc-label {
    font-size: 0.71rem;
    color: var(--text-muted);
    margin-top: 3px;
}
.ssc-icon {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.ssc-icon svg { width: 18px; height: 18px; }

/* ── Charts Grid ── */
.charts-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}
.chart-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.4rem;
}
.chart-card h3 {
    font-size: 0.9rem;
    font-weight: 700;
    margin-bottom: 1.2rem;
    color: var(--text-main);
}
.no-data-box {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 2.5rem 1rem;
    color: var(--text-muted);
    gap: 0.6rem;
    text-align: center;
}
.no-data-box svg { opacity: 0.3; }
.no-data-box p { font-size: 0.84rem; font-weight: 600; }
.no-data-box span { font-size: 0.73rem; }

/* ── Activity list ── */
.activity-item {
    display: flex;
    align-items: flex-start;
    gap: 0.875rem;
    padding: 0.8rem;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
    margin-bottom: 0.6rem;
    transition: background 0.15s;
}
.activity-item:last-child { margin-bottom: 0; }
.activity-item:hover { background: var(--bg-main); }
.act-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: rgba(16,185,129,0.1);
    color: var(--success);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.act-avatar svg { width: 17px; height: 17px; }
.act-title { font-size: 0.84rem; font-weight: 600; color: var(--text-main); margin-bottom: 2px; }
.act-sub   { font-size: 0.73rem; color: var(--primary); margin-bottom: 2px; }
.act-date  { font-size: 0.7rem; color: var(--text-muted); }

/* ── Alert item ── */
.sys-alert {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    padding: 0.8rem;
    border-radius: var(--radius-md);
    margin-bottom: 0.6rem;
}
.sys-alert.danger  { background: rgba(239,68,68,0.05);  border: 1px solid rgba(239,68,68,0.15); }
.sys-alert.warning { background: rgba(245,158,11,0.05); border: 1px solid rgba(245,158,11,0.15); }
.sys-alert:last-child { margin-bottom: 0; }
.alert-ico {
    width: 30px; height: 30px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.alert-ico svg { width: 15px; height: 15px; }
.alert-ico.danger  { background: rgba(239,68,68,0.12); color: var(--danger); }
.alert-ico.warning { background: rgba(245,158,11,0.12); color: var(--warning); }
.sal-title { font-size: 0.84rem; font-weight: 600; color: var(--text-main); }
.sal-sub   { font-size: 0.73rem; color: var(--danger); margin-top: 2px; }
.sal-sub.warning { color: var(--warning); }
.sal-date  { font-size: 0.69rem; color: var(--text-muted); margin-top: 2px; }

/* Leave stat boxes */
.leave-boxes {
    display: grid;
    grid-template-columns: repeat(3,1fr);
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}
.leave-box {
    text-align: center;
    padding: 1rem 0.5rem;
    border: 1px solid var(--border);
    border-radius: var(--radius-md);
}
.leave-box .lb-num { font-size: 1.6rem; font-weight: 800; }
.leave-box .lb-lbl { font-size: 0.72rem; color: var(--text-muted); margin-top: 4px; }

/* Table inside card */
.inner-table th { font-size: 0.75rem; padding: 0.6rem 0.75rem; }
.inner-table td { font-size: 0.82rem; padding: 0.6rem 0.75rem; }
</style>

<!-- ── Header ── -->
<div class="emp-header">
    <div>
        <h1>Dashboard Overview</h1>
        <div class="sub-date"><?php echo date('l, F d, Y'); ?></div>
    </div>
    <div class="live-clock" id="liveClock">--:-- --</div>
</div>

<!-- ── Top 3 Cards ── -->
<div class="top-cards-grid">

    <!-- Today's Status -->
    <div class="top-card">
        <div class="top-card-head">
            <div class="tc-icon">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="3" y1="10" x2="21" y2="10"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line><line x1="16" y1="2" x2="16" y2="6"></line>
                </svg>
            </div>
            <div>
                <div class="tc-title">Today's Status</div>
                <div class="tc-sub"><?php echo date('l, F d, Y'); ?></div>
            </div>
        </div>
        <div style="background:var(--bg-main); border:1px solid var(--border); border-radius:var(--radius-md); padding:1rem; text-align:center;">
            <?php if ($today_present > 0): ?>
                <div style="font-size:1.5rem; font-weight:800; color:var(--success);"><?php echo $today_present; ?></div>
                <div style="font-size:0.78rem; color:var(--text-muted); margin-top:4px;">employees present today</div>
                <div style="font-size:0.78rem; color:var(--primary); font-weight:600; margin-top:4px;"><?php echo $today_att_pct; ?>% attendance rate</div>
            <?php else: ?>
                <div style="color:var(--text-muted); font-size:0.84rem;">No attendance recorded for today</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="top-card">
        <div class="top-card-head">
            <div class="tc-icon" style="background:rgba(34,197,94,0.1); color:#22c55e;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <polyline points="13 2 13 9 20 9"></polyline>
                    <path d="M20 14v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h7"></path>
                </svg>
            </div>
            <div>
                <div class="tc-title">Quick Actions</div>
                <div class="tc-sub">Manage your team</div>
            </div>
        </div>
        <div style="display:flex; flex-direction:column; gap:0.5rem;">
            <a href="<?php echo BASE_URL; ?>modules/qc/review.php" class="btn btn-primary" style="justify-content:center; font-size:0.82rem;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                Review QC Queue <?php if($pending_qc>0): ?><span style="background:rgba(255,255,255,0.3);border-radius:10px;padding:1px 7px;font-size:0.72rem;"><?php echo $pending_qc; ?></span><?php endif; ?>
            </a>
            <a href="<?php echo BASE_URL; ?>modules/leaves/index.php" class="btn" style="justify-content:center; font-size:0.82rem; border-color:var(--warning); color:var(--warning);">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
                Approve Leaves <?php if($pending_leaves>0): ?><span style="background:rgba(245,158,11,0.15);border-radius:10px;padding:1px 7px;font-size:0.72rem;color:var(--warning);"><?php echo $pending_leaves; ?></span><?php endif; ?>
            </a>
        </div>
    </div>

    <!-- System Summary -->
    <div class="top-card">
        <div class="top-card-head">
            <div class="tc-icon" style="background:rgba(139,92,246,0.1); color:#8b5cf6;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="2" y1="12" x2="22" y2="12"></line>
                    <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                </svg>
            </div>
            <div>
                <div class="tc-title">System Summary</div>
                <div class="tc-sub">Overall performance</div>
            </div>
        </div>
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
            <div style="background:var(--bg-main); border-radius:var(--radius-sm); padding:0.6rem; text-align:center;">
                <div style="font-size:1.1rem; font-weight:800; color:var(--primary);"><?php echo $total_leads; ?></div>
                <div style="font-size:0.68rem; color:var(--text-muted);">Total Leads</div>
            </div>
            <div style="background:var(--bg-main); border-radius:var(--radius-sm); padding:0.6rem; text-align:center;">
                <div style="font-size:1.1rem; font-weight:800; color:var(--success);"><?php echo $total_merchants; ?></div>
                <div style="font-size:0.68rem; color:var(--text-muted);">Merchants</div>
            </div>
            <div style="background:var(--bg-main); border-radius:var(--radius-sm); padding:0.6rem; text-align:center;">
                <div style="font-size:1.1rem; font-weight:800; color:var(--warning);"><?php echo $pending_kyc; ?></div>
                <div style="font-size:0.68rem; color:var(--text-muted);">KYC Pending</div>
            </div>
            <div style="background:var(--bg-main); border-radius:var(--radius-sm); padding:0.6rem; text-align:center;">
                <div style="font-size:1.1rem; font-weight:800; color:#8b5cf6;">₹<?php echo number_format($wallet_pool); ?></div>
                <div style="font-size:0.68rem; color:var(--text-muted);">Wallet Pool</div>
            </div>
        </div>
    </div>
</div>

<!-- ── Stats Row 1 (5 cards) ── -->
<div class="stats-row-5">

    <div class="emp-stat-card">
        <div>
            <div class="esi-label">Team Size</div>
            <div class="esi-value"><?php echo $team_size; ?></div>
            <div class="esi-sub"><?php echo $team_size; ?> active members</div>
        </div>
        <div class="emp-stat-icon" style="background:rgba(37,99,235,0.08); color:var(--primary);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 20h5v-2a3 3 0 0 0-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 0 1 5.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 0 1 9.288 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0z"></path></svg>
        </div>
    </div>

    <div class="emp-stat-card">
        <div>
            <div class="esi-label">Today's Attendance</div>
            <div class="esi-value" style="color:var(--success);"><?php echo $today_att_pct; ?>%</div>
            <div class="esi-sub"><?php echo $today_present; ?> of <?php echo $team_size; ?> present</div>
        </div>
        <div class="emp-stat-icon" style="background:rgba(16,185,129,0.08); color:var(--success);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="3" y1="10" x2="21" y2="10"></line></svg>
        </div>
    </div>

    <div class="emp-stat-card">
        <div>
            <div class="esi-label">Pending Leaves</div>
            <div class="esi-value" style="color:var(--warning);"><?php echo $pending_leaves; ?></div>
            <div class="esi-sub"><?php echo $approved_leaves; ?> approved, <?php echo $rejected_leaves; ?> rejected</div>
        </div>
        <div class="emp-stat-icon" style="background:rgba(245,158,11,0.08); color:var(--warning);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline></svg>
        </div>
    </div>

    <div class="emp-stat-card">
        <div>
            <div class="esi-label">Assets</div>
            <div class="esi-value" style="color:var(--accent);"><?php echo $total_assets; ?></div>
            <a href="<?php echo BASE_URL; ?>modules/inventory/stock.php" class="esi-link" style="color:var(--accent);">View all company assets</a>
        </div>
        <div class="emp-stat-icon" style="background:rgba(8,145,178,0.08); color:var(--accent);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"></path></svg>
        </div>
    </div>

    <div class="emp-stat-card">
        <div>
            <div class="esi-label">Team Salary Budget</div>
            <div class="esi-value" style="color:var(--success); font-size:1.3rem;">₹<?php echo number_format($salary_budget); ?></div>
            <div class="esi-sub">₹<?php echo number_format($avg_salary); ?> avg</div>
        </div>
        <div class="emp-stat-icon" style="background:rgba(16,185,129,0.08); color:var(--success);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
        </div>
    </div>
</div>

<!-- ── Stats Row 2 (4 small cards) ── -->
<div class="stats-row-4">

    <div class="small-stat-card">
        <div class="ssc-left">
            <div class="ssc-val" style="color:var(--primary);"><?php echo $monthly_pct; ?>%</div>
            <div class="ssc-label" style="color:var(--primary); font-weight:600;">Monthly Attendance</div>
        </div>
        <div class="ssc-icon" style="background:rgba(37,99,235,0.08); color:var(--primary);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>
        </div>
    </div>

    <div class="small-stat-card">
        <div class="ssc-left">
            <div class="ssc-val">0h</div>
            <div class="ssc-label" style="color:var(--accent); font-weight:600;">Avg Work Hours</div>
        </div>
        <div class="ssc-icon" style="background:rgba(8,145,178,0.08); color:var(--accent);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </div>
    </div>

    <div class="small-stat-card">
        <div class="ssc-left">
            <div class="ssc-val">0h</div>
            <div class="ssc-label" style="color:var(--warning); font-weight:600;">Overtime Hours</div>
        </div>
        <div class="ssc-icon" style="background:rgba(245,158,11,0.08); color:var(--warning);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
        </div>
    </div>

    <div class="small-stat-card">
        <div class="ssc-left">
            <div class="ssc-val" style="color:var(--success);">
                <?php
                $pqc = safeCount($pdo, "SELECT COUNT(*) FROM form_responses WHERE status='approved'");
                echo ($total_leads > 0 ? round(($pqc/$total_leads)*100) : 0).'%';
                ?>
            </div>
            <div class="ssc-label" style="color:var(--success); font-weight:600;">Productivity Score</div>
        </div>
        <div class="ssc-icon" style="background:rgba(16,185,129,0.08); color:var(--success);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
        </div>
    </div>
</div>

<!-- ── Charts Row 1 ── -->
<div class="charts-grid-2">

    <!-- Attendance Trend -->
    <div class="chart-card">
        <h3>Attendance Trend (Last 30 Days)</h3>
        <?php if (empty($att_trend)): ?>
        <div class="no-data-box">
            <svg width="46" height="46" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <rect x="3" y="4" width="18" height="18" rx="2"></rect><line x1="3" y1="10" x2="21" y2="10"></line>
            </svg>
            <p>No Attendance Data</p>
            <span>Attendance trends will appear here once attendance is recorded.</span>
        </div>
        <?php else: ?>
        <canvas id="attChart" height="180"></canvas>
        <script>
        new Chart(document.getElementById('attChart'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($att_trend,'date')); ?>,
                datasets: [{
                    label: 'Present',
                    data: <?php echo json_encode(array_column($att_trend,'cnt')); ?>,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37,99,235,0.07)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointBackgroundColor: '#2563eb'
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1 } },
                    x: { ticks: { maxTicksLimit: 8 } }
                }
            }
        });
        </script>
        <?php endif; ?>
    </div>

    <!-- Department Distribution -->
    <div class="chart-card">
        <h3>Department Distribution</h3>
        <?php if (empty($dept_dist) || array_sum(array_column($dept_dist,'emp_count')) == 0): ?>
        <div class="no-data-box">
            <svg width="46" height="46" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path>
            </svg>
            <p>No Department Data</p>
            <span>Add employees to departments to see distribution.</span>
        </div>
        <?php else: ?>
        <div style="display:flex; gap:1rem; align-items:center;">
            <div style="flex:0 0 200px;">
                <canvas id="deptChart" width="200" height="200"></canvas>
            </div>
            <div style="flex:1; font-size:0.78rem;">
                <?php
                $dcolors = ['#2563eb','#22c55e','#f59e0b','#ef4444','#8b5cf6','#0891b2','#f97316','#64748b'];
                foreach ($dept_dist as $i => $d): ?>
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:8px;">
                    <span style="width:10px;height:10px;border-radius:50%;background:<?php echo $dcolors[$i%count($dcolors)]; ?>;flex-shrink:0;"></span>
                    <span style="color:var(--text-main); font-weight:500;"><?php echo htmlspecialchars($d['team_name'] ?? 'No Team'); ?></span>
                    <span style="margin-left:auto; color:var(--text-muted);"><?php echo $d['emp_count']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <script>
        new Chart(document.getElementById('deptChart'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_map(fn($d) => $d['team_name'] ?? 'No Team', $dept_dist)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($dept_dist,'emp_count')); ?>,
                    backgroundColor: <?php echo json_encode(array_slice($dcolors, 0, count($dept_dist))); ?>,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: ctx => ctx.label + ': ' + ctx.raw + ' emp' } }
                }
            }
        });
        </script>
        <?php endif; ?>
    </div>
</div>

<!-- ── Charts Row 2 ── -->
<div class="charts-grid-2">

    <!-- Leave Trends -->
    <div class="chart-card">
        <h3>Leave Trends (Last 6 Months)</h3>
        <?php if (empty($leave_trend)): ?>
        <div class="no-data-box">
            <svg width="46" height="46" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                <polyline points="14 2 14 8 20 8"></polyline>
            </svg>
            <p>No leave trend data available</p>
        </div>
        <?php else: ?>
        <canvas id="leaveChart" height="180"></canvas>
        <script>
        new Chart(document.getElementById('leaveChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($leave_trend,'mon')); ?>,
                datasets: [{
                    label: 'Leaves',
                    data: <?php echo json_encode(array_column($leave_trend,'cnt')); ?>,
                    backgroundColor: 'rgba(245,158,11,0.7)',
                    borderRadius: 6
                }]
            },
            options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
        </script>
        <?php endif; ?>
    </div>

    <!-- Asset Distribution -->
    <div class="chart-card">
        <h3>Asset Distribution by Category</h3>
        <?php if (empty($asset_dist)): ?>
        <div class="no-data-box">
            <svg width="46" height="46" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path>
            </svg>
            <p>No asset data available</p>
            <span>Add assets to see distribution by category.</span>
        </div>
        <?php else: ?>
        <div style="max-width:200px; margin:0 auto 1rem;">
            <canvas id="assetChart" height="200"></canvas>
        </div>
        <?php
        $acolors = ['#2563eb','#22c55e','#f59e0b','#8b5cf6','#ef4444','#0891b2'];
        ?>
        <div style="display:flex; flex-wrap:wrap; gap:0.5rem; justify-content:center;">
            <?php foreach ($asset_dist as $i => $a): ?>
            <span style="font-size:0.73rem; display:flex; align-items:center; gap:5px;">
                <span style="width:9px;height:9px;border-radius:50%;background:<?php echo $acolors[$i%count($acolors)]; ?>;display:inline-block;"></span>
                <?php echo htmlspecialchars($a['category'] ?? 'Other'); ?> (<?php echo $a['cnt']; ?>)
            </span>
            <?php endforeach; ?>
        </div>
        <script>
        new Chart(document.getElementById('assetChart'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_map(fn($a) => $a['category'] ?? 'Other', $asset_dist)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($asset_dist,'cnt')); ?>,
                    backgroundColor: <?php echo json_encode(array_slice($acolors,0,count($asset_dist))); ?>,
                    borderWidth: 0
                }]
            },
            options: { plugins: { legend: { display: false } }, cutout: '60%' }
        });
        </script>
        <?php endif; ?>
    </div>
</div>

<!-- ── Recent Activities + System Alerts ── -->
<div class="charts-grid-2">

    <!-- Recent Activities -->
    <div class="chart-card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <h3 style="margin:0;">Recent Activities</h3>
            <a href="<?php echo BASE_URL; ?>modules/team/users.php" style="font-size:0.78rem; color:var(--primary); font-weight:600;">View all →</a>
        </div>
        <?php if (empty($recent_users)): ?>
        <div class="no-data-box" style="padding:1.5rem;"><p>No recent activities</p></div>
        <?php else: ?>
        <?php foreach ($recent_users as $u): ?>
        <div class="activity-item">
            <div class="act-avatar">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </div>
            <div>
                <div class="act-title">New Employee: <?php echo htmlspecialchars($u['name']); ?></div>
                <div class="act-sub"><?php echo htmlspecialchars($u['team_name'] ?? 'No Team'); ?> — <?php echo htmlspecialchars($u['role_name'] ?? 'Employee'); ?></div>
                <div class="act-date"><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- System Alerts -->
    <div class="chart-card">
        <h3>System Alerts</h3>
        <?php if (empty($alerts)): ?>
        <div class="no-data-box" style="padding:1.5rem;">
            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path></svg>
            <p style="color:var(--success);">All Good!</p>
            <span>No system alerts at this time.</span>
        </div>
        <?php else: ?>
        <?php foreach ($alerts as $al): ?>
        <div class="sys-alert <?php echo $al['type']; ?>">
            <div class="alert-ico <?php echo $al['type']; ?>">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>
            </div>
            <div>
                <div class="sal-title"><?php echo htmlspecialchars($al['title']); ?></div>
                <div class="sal-sub <?php echo $al['type']==='warning'?'warning':''; ?>"><?php echo htmlspecialchars($al['msg']); ?></div>
                <div class="sal-date"><?php echo $al['date']; ?></div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ── Department Statistics + Leave Statistics ── -->
<div class="charts-grid-2" style="margin-bottom:0;">

    <!-- Department Statistics Table -->
    <div class="chart-card">
        <h3>Department Statistics</h3>
        <?php if (empty($dept_stats)): ?>
        <div class="no-data-box" style="padding:1.5rem;"><p>No department data</p></div>
        <?php else: ?>
        <div class="data-table-container">
            <table class="inner-table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th style="text-align:right;">Employees</th>
                        <th style="text-align:right;">Avg Salary</th>
                        <th style="text-align:right;">Total Salary</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dept_stats as $d): ?>
                    <tr>
                        <td style="color:var(--primary); font-weight:500;"><?php echo htmlspecialchars($d['dept_name'] ?? 'No Department'); ?></td>
                        <td style="text-align:right; font-weight:600;"><?php echo $d['emp_count']; ?></td>
                        <td style="text-align:right;">₹<?php echo number_format($d['avg_salary']); ?></td>
                        <td style="text-align:right; font-weight:600;">₹<?php echo number_format($d['total_salary']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <!-- Leave Statistics -->
    <div class="chart-card">
        <h3>Leave Statistics</h3>
        <div class="leave-boxes">
            <div class="leave-box">
                <div class="lb-num" style="color:var(--warning);"><?php echo $pending_leaves; ?></div>
                <div class="lb-lbl">Pending</div>
            </div>
            <div class="leave-box">
                <div class="lb-num" style="color:var(--success);"><?php echo $approved_leaves; ?></div>
                <div class="lb-lbl">Approved</div>
            </div>
            <div class="leave-box">
                <div class="lb-num" style="color:var(--danger);"><?php echo $rejected_leaves; ?></div>
                <div class="lb-lbl">Rejected</div>
            </div>
        </div>
        <div style="border-top:1px solid var(--border); padding-top:1rem;">
            <div style="font-size:0.82rem; font-weight:700; margin-bottom:0.75rem; color:var(--text-main);">Leave Type Breakdown:</div>
            <?php
            $leave_types = safeQuery($pdo, "SELECT leave_type, COUNT(*) as cnt FROM leaves GROUP BY leave_type ORDER BY cnt DESC");
            ?>
            <?php if (empty($leave_types)): ?>
            <div style="text-align:center; color:var(--text-muted); font-size:0.8rem; padding:1rem 0;">No leave type data available</div>
            <?php else: ?>
            <div style="display:flex; justify-content:space-between; font-size:0.78rem; color:var(--text-muted); font-weight:600; margin-bottom:0.5rem; padding:0 0.5rem;">
                <span>Leave Type</span><span>Count</span>
            </div>
            <?php foreach ($leave_types as $lt): ?>
            <div style="display:flex; justify-content:space-between; font-size:0.82rem; padding:0.4rem 0.5rem; border-radius:6px; margin-bottom:4px; background:var(--bg-main);">
                <span style="font-weight:500;"><?php echo htmlspecialchars($lt['leave_type'] ?? 'Other'); ?></span>
                <span style="font-weight:700; color:var(--primary);"><?php echo $lt['cnt']; ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Live Clock
function updateClock() {
    const now = new Date();
    let h = now.getHours(), m = now.getMinutes(), s = now.getSeconds();
    const ampm = h >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    document.getElementById('liveClock').textContent =
        String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0') + ' ' + ampm;
}
updateClock();
setInterval(updateClock, 1000);
</script>

<?php include_once 'includes/footer.php'; ?>
