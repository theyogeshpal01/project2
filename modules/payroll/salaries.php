<?php 
include_once '../../includes/header.php'; 
include_once '../../core/functions.php';

$salaries = $pdo->query("SELECT p.*, u.name, u.wallet_balance 
                        FROM payroll p 
                        JOIN users u ON p.user_id = u.id 
                        ORDER BY p.year DESC, p.month DESC")->fetchAll();

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
?>

<div class="page-header" style="align-items:flex-start;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-dark);">Payroll Management</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Manage employee salaries and generate payroll</p>
    </div>
    <div style="display:flex;gap:10px;">
        <button class="btn glass-card">Generate All</button>
        <button class="btn btn-primary">+ Add Payroll</button>
    </div>
</div>

<div class="glass-card" style="padding:1.5rem;">
    <!-- Filters -->
    <div style="margin-bottom:1.5rem; display:flex; gap:10px; align-items:center;">
        <select class="form-control" style="width:auto;"><option>All Months</option></select>
        <select class="form-control" style="width:auto;"><option>2026</option></select>
        <select class="form-control" style="width:auto;"><option>All Status</option></select>
        <button class="btn glass-card" style="padding:6px 12px;font-size:0.875rem;">Clear Filters</button>
    </div>

    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Period</th>
                    <th>Basic Salary</th>
                    <th>Net Salary</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($salaries)): ?>
                    <tr><td colspan="6" style="text-align:center; padding:3rem; color:var(--text-muted);">No payroll records found.</td></tr>
                <?php endif; ?>
                <?php foreach($salaries as $s): 
                    $emp_id = 'EMP' . str_pad($s['user_id'], 4, '0', STR_PAD_LEFT);
                ?>
                <tr>
                    <td>
                        <div style="font-weight:600;"><?php echo htmlspecialchars($s['name']); ?></div>
                        <div style="font-size:0.75rem; color:var(--text-muted);"><?php echo $emp_id; ?></div>
                    </td>
                    <td><?php echo $months[$s['month']] . ' ' . $s['year']; ?></td>
                    <td>₹<?php echo number_format($s['base_salary']); ?></td>
                    <td><strong style="color:var(--text-dark);">₹<?php echo number_format($s['net_payable']); ?></strong></td>
                    <td>
                        <?php
                        $sc = ['pending'=>'warning', 'approved'=>'primary', 'paid'=>'success'];
                        echo '<span class="badge badge-' . ($sc[$s['status']] ?? 'muted') . '">' . ucfirst($s['status']) . '</span>';
                        ?>
                    </td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <button class="btn btn-primary" style="padding:4px 8px;font-size:0.75rem;">Edit</button>
                            <button class="btn btn-danger" style="padding:4px 8px;font-size:0.75rem;">Delete</button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
