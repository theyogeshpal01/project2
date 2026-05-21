<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

// --- Stats ---
$total_leads     = $pdo->query("SELECT COUNT(*) FROM form_responses")->fetchColumn();
$approved_leads  = $pdo->query("SELECT COUNT(*) FROM form_responses WHERE status='approved'")->fetchColumn();
$pending_leads   = $pdo->query("SELECT COUNT(*) FROM form_responses WHERE status='pending'")->fetchColumn();
$total_agents    = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id IN (3,4) AND status='active'")->fetchColumn();
$total_wallet    = $pdo->query("SELECT SUM(wallet_balance) FROM users")->fetchColumn() ?: 0;
$total_merchants = $pdo->query("SELECT COUNT(*) FROM distributor_details")->fetchColumn();

// --- Chart: Leads last 7 days ---
$leads_chart = $pdo->query("
    SELECT DATE(created_at) as day, COUNT(*) as cnt
    FROM form_responses
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY day ASC
")->fetchAll();

$chart_labels = [];
$chart_data   = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('d M', strtotime($d));
    $found = 0;
    foreach ($leads_chart as $row) {
        if ($row['day'] === $d) { $found = $row['cnt']; break; }
    }
    $chart_data[] = $found;
}

// --- Chart: Status breakdown (Pie) ---
$status_data = $pdo->query("SELECT status, COUNT(*) as cnt FROM form_responses GROUP BY status")->fetchAll();
$pie_labels  = array_column($status_data, 'status');
$pie_data    = array_column($status_data, 'cnt');

// --- Top Agents ---
$top_agents = $pdo->query("
    SELECT u.name, COUNT(fr.id) as total, SUM(fr.status='approved') as approved
    FROM form_responses fr
    JOIN users u ON fr.agent_id = u.id
    GROUP BY fr.agent_id
    ORDER BY total DESC
    LIMIT 5
")->fetchAll();

// --- Monthly Payroll ---
$monthly_payroll = $pdo->query("
    SELECT month, SUM(net_payable) as total
    FROM payroll
    WHERE year = YEAR(NOW())
    GROUP BY month
    ORDER BY month ASC
")->fetchAll();
$payroll_months = [];
$payroll_vals   = [];
foreach ($monthly_payroll as $mp) {
    $payroll_months[] = date('M', mktime(0,0,0,$mp['month'],1));
    $payroll_vals[]   = (float)$mp['total'];
}

// --- Recent Activity ---
$recent = $pdo->query("
    SELECT fr.id, fr.status, fr.created_at, u.name as agent_name, f.title as form_title
    FROM form_responses fr
    LEFT JOIN users u ON fr.agent_id = u.id
    LEFT JOIN forms f ON fr.form_id = f.id
    ORDER BY fr.created_at DESC LIMIT 8
")->fetchAll();
?>

<div class="page-header">
    <div class="page-header-left">
        <div class="page-header-icon" style="background:rgba(37,99,235,0.1);color:var(--primary);">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line>
            </svg>
        </div>
        <div>
            <h1>Analytics Dashboard</h1>
            <p>Real-time overview of your operations, leads, and performance</p>
        </div>
    </div>
    <div style="font-size:0.8rem; color:var(--text-muted); background:var(--bg-card); padding:6px 14px; border-radius:8px; border:1px solid var(--border);">
        Last updated: <?php echo date('d M Y, H:i'); ?>
    </div>
</div>

<!-- KPI Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));">
    <div class="stat-card glass-card">
        <div class="stat-card-text"><div class="stat-label">Total Leads</div><div class="stat-value"><?php echo number_format($total_leads); ?></div></div>
        <div class="stat-card-icon" style="background:rgba(37,99,235,0.08);color:var(--primary);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"></path></svg>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-card-text"><div class="stat-label">Approved</div><div class="stat-value" style="color:var(--success);"><?php echo number_format($approved_leads); ?></div></div>
        <div class="stat-card-icon" style="background:rgba(16,185,129,0.08);color:var(--success);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path></svg>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-card-text"><div class="stat-label">Pending QC</div><div class="stat-value" style="color:var(--warning);"><?php echo number_format($pending_leads); ?></div></div>
        <div class="stat-card-icon" style="background:rgba(245,158,11,0.08);color:var(--warning);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-card-text"><div class="stat-label">Active Agents</div><div class="stat-value" style="color:var(--accent);"><?php echo number_format($total_agents); ?></div></div>
        <div class="stat-card-icon" style="background:rgba(8,145,178,0.08);color:var(--accent);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-card-text"><div class="stat-label">Wallet Pool</div><div class="stat-value" style="color:var(--success);">&#8377;<?php echo number_format($total_wallet,0); ?></div></div>
        <div class="stat-card-icon" style="background:rgba(16,185,129,0.08);color:var(--success);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="2" y="7" width="20" height="14" rx="2"></rect><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-card-text"><div class="stat-label">Merchants</div><div class="stat-value"><?php echo number_format($total_merchants); ?></div></div>
        <div class="stat-card-icon" style="background:rgba(37,99,235,0.08);color:var(--primary);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><path d="M9 22V12h6v10"></path></svg>
        </div>
    </div>
</div>

<!-- Charts Row 1 -->
<div style="display:grid; grid-template-columns:2fr 1fr; gap:2rem; margin-bottom:2rem;">
    <!-- Line Chart: Leads 7 days -->
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="margin-bottom:1.5rem;">Lead Submissions — Last 7 Days</h3>
        <canvas id="leadsChart" height="100"></canvas>
    </div>
    <!-- Pie Chart: Status -->
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="margin-bottom:1.5rem;">Submission Status</h3>
        <canvas id="statusChart" height="200"></canvas>
    </div>
</div>

<!-- Charts Row 2 -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem; margin-bottom:2rem;">
    <!-- Bar Chart: Payroll -->
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="margin-bottom:1.5rem;">Monthly Payroll (<?php echo date('Y'); ?>)</h3>
        <canvas id="payrollChart" height="120"></canvas>
    </div>
    <!-- Top Agents -->
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="margin-bottom:1.5rem;">🏆 Top Performing Agents</h3>
        <?php if (empty($top_agents)): ?>
            <p style="color:var(--text-muted); text-align:center; padding:2rem;">No data yet.</p>
        <?php endif; ?>
        <?php foreach ($top_agents as $i => $agent): ?>
        <div style="display:flex; align-items:center; gap:12px; margin-bottom:1rem;">
            <div style="width:28px; height:28px; border-radius:50%; background:var(--primary); color:white; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:0.8rem; flex-shrink:0;"><?php echo $i+1; ?></div>
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($agent['name']); ?>&background=4f46e5&color=fff&size=32" style="width:32px; height:32px; border-radius:50%;">
            <div style="flex:1;">
                <div style="font-weight:600; font-size:0.875rem;"><?php echo htmlspecialchars($agent['name']); ?></div>
                <div style="font-size:0.75rem; color:var(--text-muted);"><?php echo $agent['total']; ?> leads · <?php echo $agent['approved']; ?> approved</div>
                <div style="height:4px; background:var(--border); border-radius:4px; margin-top:4px;">
                    <div style="height:4px; background:var(--success); border-radius:4px; width:<?php echo $agent['total'] > 0 ? round(($agent['approved']/$agent['total'])*100) : 0; ?>%;"></div>
                </div>
            </div>
            <div style="font-weight:700; color:var(--success); font-size:0.875rem;"><?php echo $agent['total'] > 0 ? round(($agent['approved']/$agent['total'])*100) : 0; ?>%</div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Recent Activity -->
<div class="glass-card" style="padding:1.5rem;">
    <h3 style="margin-bottom:1.5rem;">Recent Activity Feed</h3>
    <div class="data-table-container">
        <table>
            <thead><tr><th>ID</th><th>Form</th><th>Agent</th><th>Status</th><th>Time</th></tr></thead>
            <tbody>
                <?php foreach ($recent as $r):
                    $sc = ['approved'=>'success','rejected'=>'danger','rework'=>'warning','pending'=>'primary'];
                    $cls = $sc[$r['status']] ?? 'muted';
                ?>
                <tr>
                    <td style="font-family:monospace; color:var(--text-muted);">#FR-<?php echo str_pad($r['id'],4,'0',STR_PAD_LEFT); ?></td>
                    <td style="font-size:0.875rem;"><?php echo htmlspecialchars($r['form_title'] ?? 'N/A'); ?></td>
                    <td style="font-size:0.875rem;"><?php echo htmlspecialchars($r['agent_name'] ?? 'N/A'); ?></td>
                    <td><span class="badge badge-<?php echo $cls; ?>"><?php echo str_replace('_',' ',$r['status']); ?></span></td>
                    <td style="font-size:0.8rem; color:var(--text-muted);"><?php echo date('d M, H:i', strtotime($r['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const chartDefaults = {
    responsive: true,
    plugins: { legend: { labels: { color: '#475569', font: { family: 'Inter' } } } }
};

// Leads Line Chart
new Chart(document.getElementById('leadsChart'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($chart_labels); ?>,
        datasets: [{
            label: 'Submissions',
            data: <?php echo json_encode($chart_data); ?>,
            borderColor: '#4f46e5',
            backgroundColor: 'rgba(79,70,229,0.1)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#2563eb',
            pointRadius: 5,
        }]
    },
    options: { ...chartDefaults, scales: { y: { beginAtZero: true, ticks: { color: getComputedStyle(document.body).getPropertyValue('--text-muted').trim() || '#64748b' } }, x: { ticks: { color: '#64748b' } } } }
});

// Status Pie Chart
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($pie_labels); ?>,
        datasets: [{
            data: <?php echo json_encode($pie_data); ?>,
            backgroundColor: ['#f59e0b','#10b981','#ef4444','#4f46e5','#0891b2'],
            borderWidth: 0,
        }]
    },
    options: { ...chartDefaults, cutout: '65%' }
});

// Payroll Bar Chart
new Chart(document.getElementById('payrollChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($payroll_months ?: ['No Data']); ?>,
        datasets: [{
            label: 'Net Payroll (₹)',
            data: <?php echo json_encode($payroll_vals ?: [0]); ?>,
            backgroundColor: 'rgba(79,70,229,0.7)',
            borderRadius: 8,
        }]
    },
    options: { ...chartDefaults, scales: { y: { beginAtZero: true, ticks: { color: '#475569' } }, x: { ticks: { color: '#475569' } } } }
});
</script>

<?php include_once '../../includes/footer.php'; ?>
