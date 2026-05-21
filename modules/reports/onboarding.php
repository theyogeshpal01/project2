<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

$tab = $_GET['tab'] ?? 'employees';

// Stats
function sc($pdo, $sql, $p=[]) { try { $s=$pdo->prepare($sql); $s->execute($p); return $s->fetchColumn()?:0; } catch(Exception $e){return 0;} }
function sq($pdo, $sql, $p=[]) { try { $s=$pdo->prepare($sql); $s->execute($p); return $s->fetchAll(); } catch(Exception $e){return [];} }

$total_emp    = sc($pdo,"SELECT COUNT(*) FROM users");
$active_emp   = sc($pdo,"SELECT COUNT(*) FROM users WHERE status='active'");
$inactive_emp = sc($pdo,"SELECT COUNT(*) FROM users WHERE status='inactive'");
$dept_dist    = sq($pdo,"SELECT t.team_name, COUNT(u.id) as cnt FROM users u LEFT JOIN teams t ON u.team_id=t.id WHERE t.team_name IS NOT NULL GROUP BY t.team_name ORDER BY cnt DESC LIMIT 6");
$role_dist    = sq($pdo,"SELECT r.role_name, COUNT(u.id) as cnt FROM users u JOIN roles r ON u.role_id=r.id GROUP BY r.role_name ORDER BY cnt DESC LIMIT 6");
$max_dist     = max(1, ...array_merge(array_column($dept_dist,'cnt'), array_column($role_dist,'cnt'), [1]));
?>

<div class="page-header" style="align-items:flex-start;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-dark);">Reports & Analytics</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Comprehensive insights and analytics dashboard</p>
    </div>
    <div style="display:flex;gap:10px;">
        <button onclick="window.print()" class="btn glass-card">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:5px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Export
        </button>
    </div>
</div>

<div class="glass-card" style="padding:1.5rem;margin-bottom:1.5rem;">
    <!-- Tabs -->
    <div style="display:flex;gap:10px;border-bottom:1px solid var(--border);padding-bottom:10px;flex-wrap:wrap;">
        <?php
        $tabs = [
            'employees' => 'Employee Reports',
            'payroll' => 'Payroll Reports',
            'asset' => 'Asset Reports',
            'attendance' => 'Attendance Reports',
            'leave' => 'Leave Reports',
            'department' => 'Department Reports'
        ];
        foreach ($tabs as $key => $label): ?>
            <a href="?tab=<?php echo $key; ?>" class="btn <?php echo $tab === $key ? 'btn-primary' : ''; ?>" style="<?php echo $tab === $key ? '' : 'background:transparent;color:var(--text-muted);border:none;'; ?>"><?php echo $label; ?></a>
        <?php endforeach; ?>
    </div>
</div>

<?php if ($tab === 'employees'): ?>
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr); margin-bottom:1.5rem;">
    <div class="stat-card glass-card" style="padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:var(--primary);"><?php echo $total_emp; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Total Employees</div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:var(--success);"><?php echo $active_emp; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Active Employees</div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:var(--danger);"><?php echo $inactive_emp; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Inactive Employees</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="font-size:1rem;font-weight:600;margin-bottom:1rem;">Department Distribution</h3>
        <div>
            <?php if (empty($dept_dist)): ?>
                <p style="color:var(--text-muted);font-size:0.875rem;">No department data yet.</p>
            <?php else: foreach ($dept_dist as $d): $pct = round(($d['cnt']/$max_dist)*100); ?>
            <div style="margin-bottom:15px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                    <span style="font-size:0.875rem;font-weight:500;"><?php echo htmlspecialchars($d['team_name']); ?></span>
                    <span style="font-size:0.875rem;color:var(--text-muted);"><?php echo $d['cnt']; ?></span>
                </div>
                <div style="width:100%;height:8px;background:var(--border);border-radius:4px;">
                    <div style="width:<?php echo $pct; ?>%;height:8px;background:var(--primary);border-radius:4px;"></div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="font-size:1rem;font-weight:600;margin-bottom:1rem;">Role Distribution</h3>
        <div>
            <?php if (empty($role_dist)): ?>
                <p style="color:var(--text-muted);font-size:0.875rem;">No role data yet.</p>
            <?php else: foreach ($role_dist as $r): $pct = round(($r['cnt']/$max_dist)*100); ?>
            <div style="margin-bottom:15px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                    <span style="font-size:0.875rem;font-weight:500;"><?php echo htmlspecialchars($r['role_name']); ?></span>
                    <span style="font-size:0.875rem;color:var(--text-muted);"><?php echo $r['cnt']; ?></span>
                </div>
                <div style="width:100%;height:8px;background:var(--border);border-radius:4px;">
                    <div style="width:<?php echo $pct; ?>%;height:8px;background:var(--success);border-radius:4px;"></div>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
<?php else: ?>
<!-- Placeholder for other reports -->
<div class="glass-card" style="padding:3rem;text-align:center;color:var(--text-muted);">
    <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="opacity:0.5;margin-bottom:1rem;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
    <h3 style="font-size:1.1rem;font-weight:600;color:var(--text-dark);margin-bottom:0.5rem;"><?php echo $tabs[$tab]; ?></h3>
    <p>This report section is under development.</p>
</div>
<?php endif; ?>

<?php include_once '../../includes/footer.php'; ?>
