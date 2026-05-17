<?php 
include_once '../../includes/header.php'; 
include_once '../../core/functions.php';

$type = $_GET['type'] ?? 'all';
$where = $type == 'distributor' ? "WHERE u.role_id = 5" : ($type == 'employee' ? "WHERE u.role_id != 5" : "");

$sql = "SELECT u.*, r.role_name, d.dept_name, dd.business_name 
        FROM users u 
        LEFT JOIN roles r ON u.role_id = r.id 
        LEFT JOIN departments d ON u.department_id = d.id 
        LEFT JOIN distributor_details dd ON u.id = dd.user_id 
        $where 
        ORDER BY u.created_at DESC";

$users = $pdo->query($sql)->fetchAll();
?>

<div class="header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem;">Onboarding Reports & Analytics</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">Monitor status, KYC completion, and activity across the network.</p>
    </div>
    <button class="btn btn-primary" onclick="window.print()">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><path d="M6 14h12v8H6z"></path></svg>
        Export PDF
    </button>
</div>

<div class="glass-card" style="padding: 1rem; margin-bottom: 2rem; display: flex; gap: 1rem;">
    <a href="?type=all" class="btn <?php echo $type=='all'?'btn-primary':'glass-card'; ?>">All Users</a>
    <a href="?type=employee" class="btn <?php echo $type=='employee'?'btn-primary':'glass-card'; ?>">Employees</a>
    <a href="?type=distributor" class="btn <?php echo $type=='distributor'?'btn-primary':'glass-card'; ?>">Distributors</a>
</div>

<div class="glass-card" style="padding: 1.5rem;">
    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>Name / Entity</th>
                    <th>Role / Dept</th>
                    <th>KYC Status</th>
                    <th>Account Status</th>
                    <th>Joined On</th>
                    <th>Progress</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): ?>
                <tr>
                    <td>
                        <div style="font-weight: 600;"><?php echo $u['name']; ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $u['business_name'] ?: $u['email']; ?></div>
                    </td>
                    <td>
                        <div style="font-size: 0.875rem;"><?php echo $u['role_name']; ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $u['dept_name'] ?: '-'; ?></div>
                    </td>
                    <td><?php echo getStatusBadge($u['kyc_status']); ?></td>
                    <td><?php echo getStatusBadge($u['status']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                    <td>
                        <?php 
                        $pct = ($u['kyc_status'] == 'verified' ? 100 : ($u['status'] == 'active' ? 50 : 25));
                        $color = $pct == 100 ? 'var(--success)' : ($pct == 50 ? 'var(--primary)' : 'var(--warning)');
                        ?>
                        <div style="width: 100px; height: 6px; background: var(--bg-main); border-radius: 3px; position: relative; overflow: hidden;">
                            <div style="width: <?php echo $pct; ?>%; height: 100%; background: <?php echo $color; ?>; position: absolute; left: 0; top: 0;"></div>
                        </div>
                        <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 4px;"><?php echo $pct; ?>% Complete</div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
