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
if ($is_admin) {
    $filter_date = $_GET['date'] ?? $today;
    $filter_user = $_GET['user_id'] ?? '';
    $month       = $_GET['month'] ?? date('Y-m');

    $sql    = "SELECT a.*, u.name as user_name, r.role_name FROM attendance a JOIN users u ON a.user_id = u.id JOIN roles r ON u.role_id = r.id WHERE a.attendance_date = ?";
    $params = [$filter_date];
    if ($filter_user) { $sql .= " AND a.user_id = ?"; $params[] = $filter_user; }
    $sql .= " ORDER BY a.check_in DESC";

    $stmt2 = $pdo->prepare($sql);
    $stmt2->execute($params);
    $all_attendance  = $stmt2->fetchAll();
    $total_present   = count($all_attendance);
    $total_users     = $pdo->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn();
    $checked_out     = count(array_filter($all_attendance, fn($a) => $a['check_out']));
    $all_users       = $pdo->query("SELECT id, name FROM users WHERE status='active' ORDER BY name")->fetchAll();

    $mstmt = $pdo->prepare("SELECT u.name, u.id,
        COUNT(a.id) as days_present,
        SUM(CASE WHEN a.check_out IS NOT NULL THEN 1 ELSE 0 END) as full_days,
        MIN(a.check_in) as earliest_in
        FROM users u
        LEFT JOIN attendance a ON u.id = a.user_id AND DATE_FORMAT(a.attendance_date,'%Y-%m') = ?
        WHERE u.status = 'active'
        GROUP BY u.id ORDER BY days_present DESC");
    $mstmt->execute([$month]);
    $monthly_data = $mstmt->fetchAll();
}
?>

<div class="page-header">
    <div>
        <h1>Attendance <?php echo $is_admin ? '— All Employees' : '& Check-In'; ?></h1>
        <p><?php echo $is_admin ? 'Monitor daily attendance and monthly summaries.' : 'Mark your daily attendance.'; ?></p>
    </div>
    <?php if ($is_admin): ?>
    <form method="GET" style="display:flex; gap:8px; align-items:center;">
        <input type="date" name="date" value="<?php echo $filter_date; ?>" class="form-control" style="width:auto;">
        <select name="user_id" class="form-control" style="width:auto;">
            <option value="">All Employees</option>
            <?php foreach ($all_users as $u): ?>
                <option value="<?php echo $u['id']; ?>" <?php echo $filter_user == $u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['name']); ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
    </form>
    <?php endif; ?>
</div>

<?php if (isset($success)): ?><div class="alert alert-success"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg><?php echo $success; ?></div><?php endif; ?>
<?php if (isset($error)):   ?><div class="alert alert-danger"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg><?php echo $error; ?></div><?php endif; ?>

<?php if ($is_admin): ?>
<!-- ADMIN VIEW -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:2rem;">
    <div class="stat-card glass-card" style="border-left:4px solid var(--success);">
        <div class="stat-label">Present</div>
        <div class="stat-value" style="color:var(--success);"><?php echo $total_present; ?></div>
    </div>
    <div class="stat-card glass-card" style="border-left:4px solid var(--danger);">
        <div class="stat-label">Absent</div>
        <div class="stat-value" style="color:var(--danger);"><?php echo max(0, $total_users - $total_present); ?></div>
    </div>
    <div class="stat-card glass-card" style="border-left:4px solid var(--primary);">
        <div class="stat-label">Checked Out</div>
        <div class="stat-value" style="color:var(--primary);"><?php echo $checked_out; ?></div>
    </div>
    <div class="stat-card glass-card" style="border-left:4px solid var(--warning);">
        <div class="stat-label">Still Working</div>
        <div class="stat-value" style="color:var(--warning);"><?php echo $total_present - $checked_out; ?></div>
    </div>
</div>

<div class="glass-card" style="padding:1.5rem; margin-bottom:2rem;">
    <h3 style="margin-bottom:1.5rem; display:flex; align-items:center; gap:8px;"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg> <?php echo date('d F Y', strtotime($filter_date)); ?> <?php echo $filter_date===$today ? '<span class="badge badge-success">Today</span>' : ''; ?></h3>
    <div class="data-table-container">
        <table>
            <thead><tr><th>Employee</th><th>Role</th><th>Check In</th><th>Check Out</th><th>Hours</th><th>GPS</th><th>Status</th></tr></thead>
            <tbody>
                <?php if (empty($all_attendance)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">No records for this date.</td></tr>
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
                    <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($att['user_name']); ?>&background=4f46e5&color=fff&size=32" style="width:30px;height:30px;border-radius:50%;">
                            <strong><?php echo htmlspecialchars($att['user_name']); ?></strong>
                        </div>
                    </td>
                    <td><span class="badge badge-primary"><?php echo $att['role_name']; ?></span></td>
                    <td style="color:var(--success); font-weight:600;"><?php echo $att['check_in'] ? date('h:i A', strtotime($att['check_in'])) : '—'; ?></td>
                    <td style="color:var(--danger); font-weight:600;"><?php echo $att['check_out'] ? date('h:i A', strtotime($att['check_out'])) : '<span style="color:var(--warning);">Still In</span>'; ?></td>
                    <td><?php echo $hrs ?: '—'; ?></td>
                    <td><?php echo $att['latitude'] ? '<a href="https://maps.google.com/?q='.$att['latitude'].','.$att['longitude'].'" target="_blank" style="color:var(--accent);display:flex;align-items:center;gap:4px;"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>Map</a>' : '<span style="color:var(--text-muted);">—</span>'; ?></td>
                    <td><?php echo $att['check_out'] ? '<span class="badge badge-success">DONE</span>' : '<span class="badge badge-warning">ACTIVE</span>'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="glass-card" style="padding:1.5rem;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
        <h3 style="display:flex;align-items:center;gap:8px;"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg> Monthly Summary</h3>
        <form method="GET" style="display:flex; gap:8px;">
            <input type="month" name="month" value="<?php echo $month; ?>" class="form-control" style="width:auto;">
            <button type="submit" class="btn btn-primary" style="padding:6px 14px;">Go</button>
        </form>
    </div>
    <div class="data-table-container">
        <table>
            <thead><tr><th>Employee</th><th>Days Present</th><th>Full Days</th><th>Attendance %</th></tr></thead>
            <tbody>
                <?php foreach ($monthly_data as $m):
                    $pct   = min(100, round(($m['days_present'] / 26) * 100));
                    $color = $pct >= 90 ? 'var(--success)' : ($pct >= 70 ? 'var(--warning)' : 'var(--danger)');
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($m['name']); ?></strong></td>
                    <td><strong style="color:var(--primary);"><?php echo $m['days_present']; ?></strong> / 26</td>
                    <td><?php echo $m['full_days']; ?></td>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <div style="flex:1; height:6px; background:var(--border); border-radius:4px;">
                                <div style="width:<?php echo $pct; ?>%; height:6px; background:<?php echo $color; ?>; border-radius:4px;"></div>
                            </div>
                            <span style="font-weight:700; color:<?php echo $color; ?>; min-width:38px;"><?php echo $pct; ?>%</span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- EMPLOYEE VIEW -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:2rem;">
    <div class="glass-card" style="padding:2rem; text-align:center;">
        <div id="clock" style="font-size:3rem; font-weight:700; color:var(--primary);">00:00:00</div>
        <div style="color:var(--text-muted); margin-bottom:2rem;"><?php echo date('l, d F Y'); ?></div>

        <?php if (!$my_attendance): ?>
            <!-- NOT CHECKED IN -->
            <form method="POST">
                <input type="hidden" name="lat" id="lat" value="">
                <input type="hidden" name="lng" id="lng" value="">
                <input type="hidden" name="check_in" value="1">
                <button type="submit" class="btn btn-primary" style="padding:1.25rem 3rem; border-radius:50px; font-size:1.1rem; width:100%; display:flex; align-items:center; justify-content:center; gap:10px;">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    Mark Check-In
                </button>
            </form>
            <div id="gps-status" style="margin-top:1rem; font-size:0.8rem; color:var(--text-muted); display:flex; align-items:center; justify-content:center; gap:4px;"><svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> GPS will auto-capture if available</div>

        <?php elseif (!$my_attendance['check_out']): ?>
            <!-- CHECKED IN, NOT OUT -->
            <div style="background:rgba(16,185,129,0.1); padding:1rem; border-radius:12px; margin-bottom:1.5rem; border:1px solid var(--success);">
                <div style="color:var(--success); font-weight:700; font-size:1.1rem; display:flex; align-items:center; justify-content:center; gap:8px;"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Checked In</div>
                <div style="color:var(--text-muted); font-size:0.875rem;">at <?php echo date('h:i A', strtotime($my_attendance['check_in'])); ?></div>
            </div>
            <form method="POST">
                <input type="hidden" name="lat" id="lat" value="">
                <input type="hidden" name="lng" id="lng" value="">
                <input type="hidden" name="check_out" value="1">
                <button type="submit" class="btn" style="background:var(--danger); color:white; padding:1.25rem 3rem; border-radius:50px; font-size:1.1rem; width:100%; display:flex; align-items:center; justify-content:center; gap:10px;">
                    <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
                    Mark Check-Out
                </button>
            </form>

        <?php else: ?>
            <!-- SHIFT COMPLETE -->
            <div style="background:rgba(99,102,241,0.1); padding:2rem; border-radius:12px; border:1px solid var(--primary);">
                <div style="color:var(--primary); font-weight:700; font-size:1.2rem; margin-bottom:1rem; display:flex; align-items:center; justify-content:center; gap:8px;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Shift Complete!</div>
                <div style="font-size:0.875rem; color:var(--text-muted);">In: <strong><?php echo date('h:i A', strtotime($my_attendance['check_in'])); ?></strong></div>
                <div style="font-size:0.875rem; color:var(--text-muted);">Out: <strong><?php echo date('h:i A', strtotime($my_attendance['check_out'])); ?></strong></div>
                <?php $hrs = round((strtotime($my_attendance['check_out']) - strtotime($my_attendance['check_in'])) / 3600, 1); ?>
                <div style="margin-top:0.75rem; font-weight:700; color:var(--success); display:flex; align-items:center; justify-content:center; gap:6px;"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg> <?php echo $hrs; ?> hours worked</div>
            </div>
        <?php endif; ?>
    </div>

    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="margin-bottom:1.5rem;">Recent Attendance</h3>
        <?php
        $hist = $pdo->prepare("SELECT * FROM attendance WHERE user_id = ? ORDER BY attendance_date DESC LIMIT 7");
        $hist->execute([$user_id]);
        $rows = $hist->fetchAll();
        ?>
        <?php if (empty($rows)): ?>
            <p style="color:var(--text-muted); text-align:center; padding:2rem;">No records yet.</p>
        <?php endif; ?>
        <div style="display:flex; flex-direction:column; gap:8px;">
        <?php foreach ($rows as $row):
            $h = ($row['check_in'] && $row['check_out']) ? round((strtotime($row['check_out'])-strtotime($row['check_in']))/3600,1).'h' : '';
        ?>
            <div style="display:flex; justify-content:space-between; align-items:center; padding:10px 14px; background:var(--bg-main); border-radius:10px; border:1px solid var(--border);">
                <div>
                    <div style="font-weight:600; font-size:0.875rem;"><?php echo date('D, d M', strtotime($row['attendance_date'])); ?></div>
                    <div style="font-size:0.75rem; color:var(--text-muted);">
                        <?php echo $row['check_in'] ? date('h:i A', strtotime($row['check_in'])) : '--'; ?>
                        → <?php echo $row['check_out'] ? date('h:i A', strtotime($row['check_out'])) : 'Active'; ?>
                        <?php echo $h ? " ($h)" : ''; ?>
                    </div>
                </div>
                <?php echo $row['check_out'] ? '<span class="badge badge-success">DONE</span>' : '<span class="badge badge-warning">ACTIVE</span>'; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
setInterval(function() {
    const el = document.getElementById('clock');
    if (el) el.innerText = new Date().toLocaleTimeString();
}, 1000);

// Try GPS silently — does NOT block form submit
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(pos) {
        const lat = document.getElementById('lat');
        const lng = document.getElementById('lng');
        if (lat) lat.value = pos.coords.latitude;
        if (lng) lng.value = pos.coords.longitude;
        const s = document.getElementById('gps-status');
        if (s) s.innerHTML = '<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg> GPS: ' + pos.coords.latitude.toFixed(4) + ', ' + pos.coords.longitude.toFixed(4);
    }, function() {}, { timeout: 4000 });
}
</script>

<?php include_once '../../includes/footer.php'; ?>
