<?php 
include_once '../../includes/header.php'; 
include_once '../../core/functions.php';

$salaries = $pdo->query("SELECT p.*, u.name, u.wallet_balance 
                        FROM payroll p 
                        JOIN users u ON p.user_id = u.id 
                        ORDER BY p.year DESC, p.month DESC")->fetchAll();
?>

<div class="header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem;">Payroll & Earnings</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">Monitor employee salaries, incentives, and wallet balances.</p>
    </div>
    <div style="display: flex; gap: 10px;">
        <a href="attendance.php" class="btn glass-card">View Attendance</a>
        <button class="btn btn-primary">Run Payroll Engine</button>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 2rem;">
    <div class="stat-card glass-card">
        <div class="stat-label">Total Payable (Month)</div>
        <div class="stat-value">₹0.00</div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-label">Pending Approvals</div>
        <div class="stat-value">0</div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-label">Wallet Distributions</div>
        <div class="stat-value">₹0.00</div>
    </div>
</div>

<div class="glass-card" style="padding: 1.5rem;">
    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Period</th>
                    <th>Base Salary</th>
                    <th>Incentives</th>
                    <th>Net Payable</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($salaries)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-muted);">No payroll records found for the current period.</td></tr>
                <?php endif; ?>
                <?php foreach($salaries as $s): ?>
                <tr>
                    <td><strong><?php echo $s['name']; ?></strong></td>
                    <td><?php echo date('F', mktime(0, 0, 0, $s['month'], 10)) . ' ' . $s['year']; ?></td>
                    <td><?php echo formatCurrency($s['base_salary']); ?></td>
                    <td><span style="color: var(--success);">+<?php echo formatCurrency($s['incentives']); ?></span></td>
                    <td><strong><?php echo formatCurrency($s['net_payable']); ?></strong></td>
                    <td><?php echo getStatusBadge($s['status']); ?></td>
                    <td>
                        <button class="btn glass-card" style="padding: 5px 10px; font-size: 0.75rem;">Details</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
