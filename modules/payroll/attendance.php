<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

$user_id  = $_SESSION['user_id'] ?? 1;
$role_id  = $_SESSION['role_id'] ?? 1;
$today    = date('Y-m-d');
$is_admin = in_array($role_id, [1, 2, 7]);

// Handle Check-in / Check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lat = !empty($_POST['lat']) ? $_POST['lat'] : null;
    $lng = !empty($_POST['lng']) ? $_POST['lng'] : null;

    if (isset($_POST['check_in'])) {
        try {
            $pdo->prepare("INSERT INTO attendance (user_id, check_in, latitude, longitude, attendance_date, status) VALUES (?, NOW(), ?, ?, ?, 'full')")
                ->execute([$user_id, $lat, $lng, $today]);
            $success = "Checked in successfully!";
        } catch (Exception $e) {
            $error = "Check-in failed: " . $e->getMessage();
        }
    } elseif (isset($_POST['check_out'])) {
        try {
            $pdo->prepare("UPDATE attendance SET check_out = NOW() WHERE user_id = ? AND attendance_date = ?")
                ->execute([$user_id, $today]);
            $success = "Checked out successfully!";
        } catch (Exception $e) {
            $error = "Check-out failed: " . $e->getMessage();
        }
    }
}

// Fetch today's record for current user
$stmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? AND attendance_date = ?");
$stmt->execute([$user_id, $today]);
$my_attendance = $stmt->fetch();

// Admin data
$filter_date = $_GET['date'] ?? $today;
$filter_user = $_GET['user_id'] ?? '';
$month       = $_GET['month'] ?? date('Y-m');

$sql    = "SELECT a.*, u.name as user_name, r.role_name FROM attendance a JOIN users u ON a.user_id = u.id JOIN roles r ON u.role_id = r.id";
$params = [];
if ($is_admin) {
    if ($filter_user) { $sql .= " WHERE a.user_id = ?"; $params[] = $filter_user; }
} else {
    $sql .= " WHERE a.user_id = ?"; $params[] = $user_id;
}
$sql .= " ORDER BY a.check_in DESC";

$stmt2 = $pdo->prepare($sql);
$stmt2->execute($params);
$all_attendance  = $stmt2->fetchAll();

$all_users = $pdo->query("SELECT id, name FROM users WHERE status='active' ORDER BY name")->fetchAll();

// Monthly summary logic
$my_days_present = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id=? AND DATE_FORMAT(attendance_date,'%Y-%m')=?");
$my_days_present->execute([$user_id, $month]);
$present = $my_days_present->fetchColumn();

// Setup current active tab based on query param
$tab = $_GET['tab'] ?? 'my_attendance';
?>

<div class="page-header" style="align-items:flex-start;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-dark);">Attendance Management</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Manage and track employee attendance</p>
    </div>
    <div style="display:flex;gap:10px;">
        <button class="btn btn-primary" style="background:#f8fafc;color:var(--primary);border:1px solid #e2e8f0;">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"></path><path d="M21 3v5h-5"></path></svg> Refresh
        </button>
        <button class="btn btn-primary" onclick="alert('Location tracked.')">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> Location
        </button>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger" style="background:rgba(239,68,68,0.1);color:var(--danger);border:1px solid rgba(239,68,68,0.2);"><?php echo $error; ?></div><?php endif; ?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr); margin-bottom:1.5rem;">
    <!-- Today's Status -->
    <div class="stat-card glass-card" style="padding:1.5rem;">
        <h3 style="font-size:1rem;margin-bottom:1rem;font-weight:600;display:flex;align-items:center;gap:8px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--primary);"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> Today's Status
        </h3>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
            <span style="color:var(--text-muted);font-size:0.875rem;">Punch In at</span>
            <span style="font-weight:600;"><?php echo $my_attendance['check_in'] ? date('h:i A', strtotime($my_attendance['check_in'])) : '--'; ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
            <span style="color:var(--text-muted);font-size:0.875rem;">Punch Out at</span>
            <span style="font-weight:600;"><?php echo $my_attendance['check_out'] ? date('h:i A', strtotime($my_attendance['check_out'])) : '--'; ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border);">
            <span style="color:var(--text-muted);font-size:0.875rem;">Total Hours</span>
            <span style="font-weight:600;color:var(--primary);">
                <?php 
                if ($my_attendance['check_in'] && $my_attendance['check_out']) {
                    echo round((strtotime($my_attendance['check_out']) - strtotime($my_attendance['check_in'])) / 3600, 2) . ' hrs';
                } else {
                    echo '--';
                }
                ?>
            </span>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="stat-card glass-card" style="padding:1.5rem;">
        <h3 style="font-size:1rem;margin-bottom:1rem;font-weight:600;display:flex;align-items:center;gap:8px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--primary);"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg> Quick Actions
        </h3>
        <div style="display:flex;gap:10px;">
            <form method="POST" style="flex:1;">
                <input type="hidden" name="lat" id="lat1">
                <input type="hidden" name="lng" id="lng1">
                <button type="submit" name="check_in" class="btn btn-success" style="width:100%;padding:0.75rem;display:flex;justify-content:center;align-items:center;gap:8px;" <?php echo ($my_attendance['check_in']) ? 'disabled' : ''; ?>>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M14 18l-6-6 6-6"></path></svg> Punch In
                </button>
            </form>
            <form method="POST" style="flex:1;">
                <input type="hidden" name="lat" id="lat2">
                <input type="hidden" name="lng" id="lng2">
                <button type="submit" name="check_out" class="btn btn-danger" style="width:100%;padding:0.75rem;display:flex;justify-content:center;align-items:center;gap:8px;" <?php echo (!$my_attendance['check_in'] || $my_attendance['check_out']) ? 'disabled' : ''; ?>>
                    Punch Out <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 6l6 6-6 6"></path></svg>
                </button>
            </form>
        </div>
    </div>

    <!-- This Month Summary -->
    <div class="stat-card glass-card" style="padding:1.5rem;">
        <h3 style="font-size:1rem;margin-bottom:1rem;font-weight:600;display:flex;align-items:center;gap:8px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="color:var(--primary);"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> This Month Summary
        </h3>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
            <span style="color:var(--text-muted);font-size:0.875rem;">Present</span>
            <span style="font-weight:600;color:var(--success);"><?php echo $present; ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
            <span style="color:var(--text-muted);font-size:0.875rem;">Absent</span>
            <span style="font-weight:600;color:var(--danger);">0</span>
        </div>
        <div style="display:flex;justify-content:space-between;border-top:1px solid var(--border);padding-top:8px;">
            <span style="color:var(--text-muted);font-size:0.875rem;">Leaves</span>
            <span style="font-weight:600;color:var(--warning);">0</span>
        </div>
    </div>
</div>

<div class="glass-card" style="padding:1.5rem;">
    <!-- Tabs -->
    <div style="display:flex;gap:10px;margin-bottom:1.5rem;border-bottom:1px solid var(--border);padding-bottom:10px;">
        <a href="?tab=my_attendance" class="btn <?php echo $tab === 'my_attendance' ? 'btn-primary' : ''; ?>" style="<?php echo $tab === 'my_attendance' ? '' : 'background:transparent;color:var(--text-muted);border:none;'; ?>">My Attendance</a>
        <?php if ($is_admin): ?>
        <a href="?tab=all_attendance" class="btn <?php echo $tab === 'all_attendance' ? 'btn-primary' : ''; ?>" style="<?php echo $tab === 'all_attendance' ? '' : 'background:transparent;color:var(--text-muted);border:none;'; ?>">All Attendance</a>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div style="margin-bottom:1.5rem;">
        <div class="filter-bar" style="padding:0; margin-bottom:0;">
            <div>
                <label style="font-size:0.75rem;color:var(--text-muted);display:block;margin-bottom:5px;">Search</label>
                <input type="text" placeholder="Search by name or ID..." class="form-control">
            </div>
            <div>
                <label style="font-size:0.75rem;color:var(--text-muted);display:block;margin-bottom:5px;">Location</label>
                <select class="form-control"><option>All Locations</option></select>
            </div>
            <div>
                <label style="font-size:0.75rem;color:var(--text-muted);display:block;margin-bottom:5px;">Status</label>
                <select class="form-control"><option>All Status</option></select>
            </div>
            <div>
                <label style="font-size:0.75rem;color:var(--text-muted);display:block;margin-bottom:5px;">Date</label>
                <input type="date" class="form-control" value="<?php echo $today; ?>">
            </div>
        </div>
    </div>

    <div class="data-table-container">
        <table>
            <thead><tr><th>Date</th><th>Employee</th><th>Punch In</th><th>Punch Out</th><th>Hours</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (empty($all_attendance)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">No attendance records found.</td></tr>
                <?php endif; ?>
                <?php foreach ($all_attendance as $att):
                    $hrs = '';
                    if ($att['check_in'] && $att['check_out']) {
                        $hrs = round((strtotime($att['check_out']) - strtotime($att['check_in'])) / 3600, 1) . 'h';
                    } elseif ($att['check_in']) {
                        $hrs = round((time() - strtotime($att['check_in'])) / 3600, 1) . 'h+';
                    }
                ?>
                <tr>
                    <td style="font-size:0.875rem;font-weight:500;"><?php echo date('d M Y', strtotime($att['attendance_date'])); ?></td>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($att['user_name']); ?>&background=4f46e5&color=fff&size=32" style="width:30px;height:30px;border-radius:50%;">
                            <strong><?php echo htmlspecialchars($att['user_name']); ?></strong>
                        </div>
                    </td>
                    <td style="color:var(--success); font-weight:600;font-size:0.875rem;"><?php echo $att['check_in'] ? date('h:i A', strtotime($att['check_in'])) : '—'; ?></td>
                    <td style="color:var(--danger); font-weight:600;font-size:0.875rem;"><?php echo $att['check_out'] ? date('h:i A', strtotime($att['check_out'])) : '—'; ?></td>
                    <td style="font-size:0.875rem;"><?php echo $hrs ?: '—'; ?></td>
                    <td><?php echo $att['check_out'] ? '<span class="badge badge-success">Present</span>' : '<span class="badge badge-warning">Working</span>'; ?></td>
                    <td>
                        <button class="btn btn-primary" style="padding:4px 8px;font-size:0.75rem;">View</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(pos) {
        if(document.getElementById('lat1')) document.getElementById('lat1').value = pos.coords.latitude;
        if(document.getElementById('lng1')) document.getElementById('lng1').value = pos.coords.longitude;
        if(document.getElementById('lat2')) document.getElementById('lat2').value = pos.coords.latitude;
        if(document.getElementById('lng2')) document.getElementById('lng2').value = pos.coords.longitude;
    }, function() {}, { timeout: 4000 });
}
</script>

<?php include_once '../../includes/footer.php'; ?>
