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
$my_attendance = $stmt->fetch() ?: []; // empty array if no record today

$filter_user = $_GET['user_id'] ?? '';
$month       = $_GET['month'] ?? date('Y-m');

// ── My Attendance: ALWAYS only current logged-in user ──
$stmt_mine = $pdo->prepare("SELECT a.*, u.name as user_name, r.role_name
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    JOIN roles r ON u.role_id = r.id
    WHERE a.user_id = ?
    ORDER BY a.attendance_date DESC");
$stmt_mine->execute([$user_id]);
$my_own_attendance = $stmt_mine->fetchAll();

// ── All Attendance: Admin only — all users or filtered by user ──
$all_attendance = [];
if ($is_admin) {
    if ($filter_user) {
        $stmt_all = $pdo->prepare("SELECT a.*, u.name as user_name, r.role_name
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            JOIN roles r ON u.role_id = r.id
            WHERE a.user_id = ?
            ORDER BY a.attendance_date DESC");
        $stmt_all->execute([$filter_user]);
    } else {
        $stmt_all = $pdo->prepare("SELECT a.*, u.name as user_name, r.role_name
            FROM attendance a
            JOIN users u ON a.user_id = u.id
            JOIN roles r ON u.role_id = r.id
            ORDER BY a.attendance_date DESC");
        $stmt_all->execute();
    }
    $all_attendance = $stmt_all->fetchAll();
}

$all_users = $pdo->query("SELECT id, name FROM users WHERE status='active' ORDER BY name")->fetchAll();

// Monthly summary (always for current user)
$stmt_pres = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE user_id=? AND DATE_FORMAT(attendance_date,'%Y-%m')=?");
$stmt_pres->execute([$user_id, $month]);
$present = $stmt_pres->fetchColumn();

// Active tab
$tab = $_GET['tab'] ?? 'my_attendance';
?>

<div class="page-header" style="align-items:flex-start;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-dark);">Attendance Management</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Manage and track employee attendance</p>
    </div>
    <div style="display:flex;gap:10px;">
        <button class="btn btn-primary" style="background:#000000;color:var(--primary);border:1px solid #e2e8f0;">
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

<style>
/* ── Attendance Stat Cards ── */
.att-cards-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.25rem;
    margin-bottom: 1.75rem;
}
.att-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: var(--shadow-sm);
    transition: box-shadow 0.2s;
}
.att-card:hover { box-shadow: var(--shadow-md); }

/* Card Header */
.att-card-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 1.25rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}
.att-card-head-icon {
    width: 38px;
    height: 38px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.att-card-head-icon svg { width: 18px; height: 18px; }
.att-card-head-title {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--text-main);
    line-height: 1.2;
}
.att-card-head-sub {
    font-size: 0.72rem;
    color: var(--text-muted);
    margin-top: 2px;
}

/* Status Row */
.att-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.55rem 0;
}
.att-row + .att-row {
    border-top: 1px solid var(--border);
}
.att-row-label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.82rem;
    color: var(--text-muted);
}
.att-row-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.att-row-value {
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--text-main);
}

/* Punch buttons */
.punch-btn-wrap {
    display: flex;
    flex-direction: column;
    gap: 0.65rem;
}
.punch-in-btn {
    width: 100%;
    padding: 0.75rem 1rem;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s;
    box-shadow: 0 3px 10px rgba(34,197,94,0.25);
    letter-spacing: 0.01em;
}
.punch-in-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 5px 15px rgba(34,197,94,0.35);
}
.punch-in-btn:disabled {
    background: #d1fae5;
    color: #6ee7b7;
    box-shadow: none;
    cursor: not-allowed;
}
body.dark-mode .punch-in-btn:disabled { background: rgba(34,197,94,0.1); color: rgba(34,197,94,0.3); }

.punch-out-btn {
    width: 100%;
    padding: 0.75rem 1rem;
    background: rgba(239,68,68,0.08);
    color: var(--danger);
    border: 1.5px solid rgba(239,68,68,0.25);
    border-radius: 10px;
    font-size: 0.9rem;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    transition: all 0.2s;
}
.punch-out-btn:hover:not(:disabled) {
    background: rgba(239,68,68,0.14);
    border-color: rgba(239,68,68,0.4);
    transform: translateY(-1px);
}
.punch-out-btn:disabled {
    opacity: 0.35;
    cursor: not-allowed;
}

/* Month stat boxes */
.month-stat-boxes {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.65rem;
    margin-bottom: 0.75rem;
}
.msb {
    text-align: center;
    padding: 0.8rem 0.5rem;
    border-radius: 10px;
    border: 1px solid var(--border);
    background: var(--bg-main);
    transition: background 0.2s;
}
.msb:hover { background: var(--bg-card-hover); }
.msb-num {
    font-size: 1.5rem;
    font-weight: 800;
    line-height: 1;
}
.msb-lbl {
    font-size: 0.68rem;
    color: var(--text-muted);
    margin-top: 4px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

/* Progress bar */
.att-progress-wrap {
    margin-top: 0.75rem;
}
.att-progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    color: var(--text-muted);
    margin-bottom: 5px;
}
.att-progress-bar {
    height: 7px;
    background: var(--border);
    border-radius: 10px;
    overflow: hidden;
}
.att-progress-fill {
    height: 100%;
    border-radius: 10px;
    background: linear-gradient(90deg, #22c55e, #16a34a);
    transition: width 0.6s ease;
}
</style>

<?php
$ci = $my_attendance['check_in']  ?? null;
$co = $my_attendance['check_out'] ?? null;
$total_hrs = ($ci && $co) ? round((strtotime($co) - strtotime($ci)) / 3600, 2) : null;
$month_days = (int)date('d');
$att_pct = $month_days > 0 ? min(100, round(($present / $month_days) * 100)) : 0;
?>

<div class="att-cards-grid">

    <!-- ── Card 1: Today's Status ── -->
    <div class="att-card">
        <div class="att-card-head">
            <div class="att-card-head-icon" style="background:rgba(37,99,235,0.1); color:var(--primary);">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline>
                </svg>
            </div>
            <div>
                <div class="att-card-head-title">Today's Status</div>
                <div class="att-card-head-sub"><?php echo date('l, d M Y'); ?></div>
            </div>
            <!-- Live status badge -->
            <?php if ($ci && !$co): ?>
                <span style="margin-left:auto; background:rgba(34,197,94,0.12); color:#16a34a; font-size:0.68rem; font-weight:700; padding:3px 9px; border-radius:20px; border:1px solid rgba(34,197,94,0.2);">● WORKING</span>
            <?php elseif ($ci && $co): ?>
                <span style="margin-left:auto; background:rgba(37,99,235,0.08); color:var(--primary); font-size:0.68rem; font-weight:700; padding:3px 9px; border-radius:20px; border:1px solid rgba(37,99,235,0.15);">✓ DONE</span>
            <?php else: ?>
                <span style="margin-left:auto; background:rgba(100,116,139,0.08); color:var(--text-muted); font-size:0.68rem; font-weight:700; padding:3px 9px; border-radius:20px; border:1px solid var(--border);">ABSENT</span>
            <?php endif; ?>
        </div>

        <div class="att-row">
            <div class="att-row-label">
                <span class="att-row-dot" style="background:#22c55e;"></span>
                Punch In
            </div>
            <div class="att-row-value" style="color:#22c55e;">
                <?php echo $ci ? date('h:i A', strtotime($ci)) : '—'; ?>
            </div>
        </div>
        <div class="att-row">
            <div class="att-row-label">
                <span class="att-row-dot" style="background:var(--danger);"></span>
                Punch Out
            </div>
            <div class="att-row-value" style="color:var(--danger);">
                <?php echo $co ? date('h:i A', strtotime($co)) : '—'; ?>
            </div>
        </div>
        <div class="att-row">
            <div class="att-row-label">
                <span class="att-row-dot" style="background:var(--primary);"></span>
                Total Hours
            </div>
            <div class="att-row-value" style="color:var(--primary);">
                <?php echo $total_hrs ? $total_hrs . ' hrs' : '—'; ?>
            </div>
        </div>
    </div>

    <!-- ── Card 2: Punch In / Out ── -->
    <div class="att-card">
        <div class="att-card-head">
            <div class="att-card-head-icon" style="background:rgba(34,197,94,0.1); color:#22c55e;">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
            </div>
            <div>
                <div class="att-card-head-title">Quick Actions</div>
                <div class="att-card-head-sub">Mark your attendance</div>
            </div>
        </div>

        <div class="punch-btn-wrap">
            <form method="POST">
                <input type="hidden" name="lat" id="lat1">
                <input type="hidden" name="lng" id="lng1">
                <button type="submit" name="check_in" class="punch-in-btn"
                    <?php echo (!empty($my_attendance['check_in'])) ? 'disabled' : ''; ?>>
                    <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                        <polyline points="10 17 15 12 10 7"></polyline>
                        <line x1="15" y1="12" x2="3" y2="12"></line>
                    </svg>
                    <?php echo (!empty($my_attendance['check_in'])) ? '✓ Already Punched In' : 'Punch In'; ?>
                </button>
            </form>
            <form method="POST">
                <input type="hidden" name="lat" id="lat2">
                <input type="hidden" name="lng" id="lng2">
                <button type="submit" name="check_out" class="punch-out-btn"
                    <?php echo (empty($my_attendance['check_in']) || !empty($my_attendance['check_out'])) ? 'disabled' : ''; ?>>
                    <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <?php echo (!empty($my_attendance['check_out'])) ? '✓ Already Punched Out' : 'Punch Out'; ?>
                </button>
            </form>
        </div>
    </div>

    <!-- ── Card 3: This Month Summary ── -->
    <div class="att-card">
        <div class="att-card-head">
            <div class="att-card-head-icon" style="background:rgba(245,158,11,0.1); color:var(--warning);">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
            </div>
            <div>
                <div class="att-card-head-title">This Month</div>
                <div class="att-card-head-sub"><?php echo date('F Y'); ?></div>
            </div>
        </div>

        <div class="month-stat-boxes">
            <div class="msb">
                <div class="msb-num" style="color:#22c55e;"><?php echo $present; ?></div>
                <div class="msb-lbl">Present</div>
            </div>
            <div class="msb">
                <div class="msb-num" style="color:var(--danger);">0</div>
                <div class="msb-lbl">Absent</div>
            </div>
            <div class="msb">
                <div class="msb-num" style="color:var(--warning);">0</div>
                <div class="msb-lbl">Leaves</div>
            </div>
        </div>

        <div class="att-progress-wrap">
            <div class="att-progress-label">
                <span>Attendance Rate</span>
                <span style="font-weight:700; color:#22c55e;"><?php echo $att_pct; ?>%</span>
            </div>
            <div class="att-progress-bar">
                <div class="att-progress-fill" style="width:<?php echo $att_pct; ?>%;"></div>
            </div>
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

        <?php
        // Pick correct dataset based on active tab
        $display_rows = ($tab === 'all_attendance' && $is_admin) ? $all_attendance : $my_own_attendance;
        ?>

        <?php if ($tab === 'all_attendance' && $is_admin): ?>
        <!-- Employee filter for All Attendance tab -->
        <form method="GET" style="margin-bottom:1rem; display:flex; gap:10px; align-items:flex-end;">
            <input type="hidden" name="tab" value="all_attendance">
            <div>
                <label style="font-size:0.75rem; color:var(--text-muted); display:block; margin-bottom:4px;">Filter by Employee</label>
                <select name="user_id" class="form-control" style="min-width:200px;">
                    <option value="">All Employees</option>
                    <?php foreach ($all_users as $u): ?>
                    <option value="<?php echo $u['id']; ?>" <?php echo $filter_user == $u['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($u['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="padding:8px 16px;">Filter</button>
            <?php if ($filter_user): ?>
            <a href="?tab=all_attendance" class="btn glass-card" style="padding:8px 16px;">Clear</a>
            <?php endif; ?>
        </form>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Employee</th>
                    <th>Punch In</th>
                    <th>Punch Out</th>
                    <th>Hours</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($display_rows)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">
                            <?php echo $tab === 'my_attendance' ? 'No attendance records found for you.' : 'No attendance records found.'; ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($display_rows as $att):
                    $hrs = '';
                    if (!empty($att['check_in']) && !empty($att['check_out'])) {
                        $hrs = round((strtotime($att['check_out']) - strtotime($att['check_in'])) / 3600, 1) . 'h';
                    } elseif (!empty($att['check_in'])) {
                        $elapsed = round((time() - strtotime($att['check_in'])) / 3600, 1);
                        $hrs = $elapsed . 'h (ongoing)';
                    }
                ?>
                <tr>
                    <td style="font-size:0.875rem; font-weight:500; white-space:nowrap;">
                        <?php echo date('d M Y', strtotime($att['attendance_date'])); ?>
                    </td>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($att['user_name']); ?>&background=4f46e5&color=fff&size=32"
                                 style="width:30px; height:30px; border-radius:50%;">
                            <strong style="font-size:0.875rem;"><?php echo htmlspecialchars($att['user_name']); ?></strong>
                        </div>
                    </td>
                    <td style="color:var(--success); font-weight:600; font-size:0.875rem;">
                        <?php echo !empty($att['check_in']) ? date('h:i A', strtotime($att['check_in'])) : '—'; ?>
                    </td>
                    <td style="color:var(--danger); font-weight:600; font-size:0.875rem;">
                        <?php echo !empty($att['check_out']) ? date('h:i A', strtotime($att['check_out'])) : '—'; ?>
                    </td>
                    <td style="font-size:0.875rem; color:var(--text-muted);">
                        <?php echo $hrs ?: '—'; ?>
                    </td>
                    <td>
                        <?php if (!empty($att['check_out'])): ?>
                            <span class="badge badge-success">Present</span>
                        <?php elseif (!empty($att['check_in'])): ?>
                            <span class="badge badge-warning">Working</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Absent</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button class="btn btn-primary" style="padding:4px 10px; font-size:0.75rem;">View</button>
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
