<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

// Only Super Admin can access this page
$role_id = $_SESSION['role_id'] ?? 1;
if ($role_id != 7) { // Assuming 7 is Super Admin
    echo "<div class='alert alert-danger'>Access Denied. Super Admin only.</div>";
    include_once '../../includes/footer.php';
    exit;
}

try {
    // Basic SaaS Stats
    $total_companies = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
    $active_companies = $pdo->query("SELECT COUNT(*) FROM companies WHERE status='active'")->fetchColumn();
    
    // Revenue stats (mocked logic - sum of subscription payments assuming a table exists or just base it on active plans)
    // We'll just show active companies count and plan distribution
    
    $plan_stats = $pdo->query("
        SELECT sp.name, COUNT(c.id) as company_count 
        FROM subscription_plans sp 
        LEFT JOIN companies c ON c.subscription_plan_id = sp.id AND c.status = 'active'
        GROUP BY sp.id
    ")->fetchAll();
    
} catch (Exception $e) {
    // If column doesn't exist yet, we handle gracefully
    $total_companies = 0;
    $active_companies = 0;
    $plan_stats = [];
}

?>

<div class="page-header" style="align-items:flex-start;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-dark);">SaaS Dashboard</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Super Admin Overview</p>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr); margin-bottom:2rem;">
    <div class="stat-card glass-card">
        <div class="stat-label">Total Companies</div>
        <div class="stat-value"><?php echo $total_companies; ?></div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-label">Active Companies</div>
        <div class="stat-value" style="color:var(--success);"><?php echo $active_companies; ?></div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-label">Inactive Companies</div>
        <div class="stat-value" style="color:var(--danger);"><?php echo $total_companies - $active_companies; ?></div>
    </div>
</div>

<div class="glass-card table-card" style="padding:1.5rem;">
    <h3>Plan Distribution (Active Companies)</h3>
    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>Plan Name</th>
                    <th>Active Companies</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($plan_stats)): ?>
                    <tr><td colspan="2" style="text-align:center;padding:2rem;">No data available. Make sure subscription_plan_id is linked to companies.</td></tr>
                <?php endif; ?>
                <?php foreach ($plan_stats as $ps): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($ps['name']); ?></strong></td>
                        <td><?php echo $ps['company_count']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
