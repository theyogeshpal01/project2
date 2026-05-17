<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $pdo->prepare("UPDATE users SET status = ? WHERE id = ?")
        ->execute([$_POST['status'], (int)$_POST['user_id']]);
    $success = "Employee status updated!";
}

$employees = $pdo->query("
    SELECT u.*, r.role_name, t.team_name, m.name as manager_name,
           (SELECT COUNT(*) FROM form_responses WHERE agent_id = u.id) as lead_count,
           (SELECT COUNT(*) FROM attendance WHERE user_id = u.id AND MONTH(attendance_date)=MONTH(NOW())) as att_count
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN teams t ON u.team_id = t.id
    LEFT JOIN users m ON u.manager_id = m.id
    ORDER BY u.created_at DESC
")->fetchAll();

$total_active   = $pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
$total_inactive = $pdo->query("SELECT COUNT(*) FROM users WHERE status='inactive'")->fetchColumn();
$total_payroll  = $pdo->query("SELECT SUM(net_payable) FROM payroll WHERE month=MONTH(NOW()) AND year=YEAR(NOW())")->fetchColumn() ?: 0;
?>

<div class="page-header">
    <div>
        <h1>HR — Employee Management</h1>
        <p>Manage employees, track attendance, and oversee payroll.</p>
    </div>
    <a href="<?php echo BASE_URL; ?>modules/team/users.php" class="btn btn-primary">+ Add Employee</a>
</div>

<?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:2rem;">
    <div class="stat-card glass-card"><div class="stat-label">Total Employees</div><div class="stat-value"><?php echo count($employees); ?></div></div>
    <div class="stat-card glass-card"><div class="stat-label">Active</div><div class="stat-value" style="color:var(--success);"><?php echo $total_active; ?></div></div>
    <div class="stat-card glass-card"><div class="stat-label">Inactive</div><div class="stat-value" style="color:var(--danger);"><?php echo $total_inactive; ?></div></div>
    <div class="stat-card glass-card"><div class="stat-label">This Month Payroll</div><div class="stat-value">₹<?php echo number_format($total_payroll,0); ?></div></div>
</div>

<div class="glass-card" style="padding:1.5rem;">
    <div class="data-table-container">
        <table>
            <thead>
                <tr><th>Employee</th><th>Role</th><th>Team</th><th>Reports To</th><th>Leads</th><th>Att. (Month)</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $emp): ?>
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($emp['name']); ?>&background=4f46e5&color=fff&size=32" style="width:34px; height:34px; border-radius:50%;">
                            <div>
                                <div style="font-weight:600;"><?php echo htmlspecialchars($emp['name']); ?></div>
                                <div style="font-size:0.75rem; color:var(--text-muted);"><?php echo $emp['email']; ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-primary"><?php echo $emp['role_name']; ?></span></td>
                    <td style="font-size:0.875rem;"><?php echo $emp['team_name'] ?: '—'; ?></td>
                    <td style="font-size:0.875rem;"><?php echo $emp['manager_name'] ?: '—'; ?></td>
                    <td style="font-weight:600; color:var(--primary);"><?php echo $emp['lead_count']; ?></td>
                    <td><?php echo $emp['att_count']; ?> days</td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="user_id" value="<?php echo $emp['id']; ?>">
                            <select name="status" onchange="this.form.submit()" class="form-control" style="padding:4px 8px; font-size:0.75rem; width:auto;">
                                <option value="active"   <?php echo $emp['status']==='active'   ?'selected':''; ?>>Active</option>
                                <option value="inactive" <?php echo $emp['status']==='inactive' ?'selected':''; ?>>Inactive</option>
                                <option value="suspended"<?php echo $emp['status']==='suspended'?'selected':''; ?>>Suspended</option>
                            </select>
                            <input type="hidden" name="update_status" value="1">
                        </form>
                    </td>
                    <td>
                        <a href="<?php echo BASE_URL; ?>modules/payroll/attendance.php" class="btn glass-card" style="padding:4px 10px; font-size:0.75rem;">Attendance</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
