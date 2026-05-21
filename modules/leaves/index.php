<?php
include_once '../../includes/header.php';

// ── Auto-create leaves table if missing ──────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS leaves (
        id            INT AUTO_INCREMENT PRIMARY KEY,
        user_id       INT NOT NULL,
        leave_type    VARCHAR(50) NOT NULL,
        start_date    DATE NOT NULL,
        end_date      DATE NOT NULL,
        reason        TEXT,
        status        ENUM('pending','approved','rejected') DEFAULT 'pending',
        created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {
    // Table may already exist or driver limitation – continue silently
}

// ── Session helpers ───────────────────────────────────────────────────────────
$current_user_id = $_SESSION['user_id']  ?? 0;
$current_role_id = $_SESSION['role_id']  ?? 0;
$is_admin        = in_array($current_role_id, [1, 2, 7]);

// ── POST handlers (must run before any HTML output) ──────────────────────────
$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Apply Leave
    if (isset($_POST['action']) && $_POST['action'] === 'apply_leave') {
        $leave_type  = trim($_POST['leave_type']  ?? '');
        $start_date  = trim($_POST['start_date']  ?? '');
        $end_date    = trim($_POST['end_date']    ?? '');
        $reason      = trim($_POST['reason']      ?? '');

        if (!$leave_type || !$start_date || !$end_date) {
            $error_msg = 'Please fill in all required fields.';
        } elseif ($end_date < $start_date) {
            $error_msg = 'End date cannot be before start date.';
        } else {
            try {
                $stmt = $pdo->prepare("INSERT INTO leaves (user_id, leave_type, start_date, end_date, reason, status)
                                       VALUES (?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([$current_user_id, $leave_type, $start_date, $end_date, $reason]);
                $success_msg = 'Leave application submitted successfully!';
            } catch (Exception $e) {
                $error_msg = 'Error submitting leave: ' . $e->getMessage();
            }
        }
    }

    // Approve Leave (admin only)
    if (isset($_POST['action']) && $_POST['action'] === 'approve_leave' && $is_admin) {
        $leave_id = (int)($_POST['leave_id'] ?? 0);
        if ($leave_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE leaves SET status='approved' WHERE id=?");
                $stmt->execute([$leave_id]);
                $success_msg = 'Leave approved successfully.';
            } catch (Exception $e) {
                $error_msg = 'Error approving leave: ' . $e->getMessage();
            }
        }
    }

    // Reject Leave (admin only)
    if (isset($_POST['action']) && $_POST['action'] === 'reject_leave' && $is_admin) {
        $leave_id = (int)($_POST['leave_id'] ?? 0);
        if ($leave_id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE leaves SET status='rejected' WHERE id=?");
                $stmt->execute([$leave_id]);
                $success_msg = 'Leave rejected.';
            } catch (Exception $e) {
                $error_msg = 'Error rejecting leave: ' . $e->getMessage();
            }
        }
    }

    // Delete Leave (own or admin)
    if (isset($_POST['action']) && $_POST['action'] === 'delete_leave') {
        $leave_id = (int)($_POST['leave_id'] ?? 0);
        if ($leave_id > 0) {
            try {
                if ($is_admin) {
                    $stmt = $pdo->prepare("DELETE FROM leaves WHERE id=?");
                    $stmt->execute([$leave_id]);
                } else {
                    $stmt = $pdo->prepare("DELETE FROM leaves WHERE id=? AND user_id=? AND status='pending'");
                    $stmt->execute([$leave_id, $current_user_id]);
                }
                $success_msg = 'Leave request deleted.';
            } catch (Exception $e) {
                $error_msg = 'Error deleting leave: ' . $e->getMessage();
            }
        }
    }
}

// ── Fetch leaves from DB ──────────────────────────────────────────────────────
$leaves = [];
try {
    if ($is_admin) {
        $stmt = $pdo->query("SELECT l.*, u.name AS employee_name
                             FROM leaves l
                             LEFT JOIN users u ON u.id = l.user_id
                             ORDER BY l.created_at DESC");
    } else {
        $stmt = $pdo->prepare("SELECT l.*, u.name AS employee_name
                               FROM leaves l
                               LEFT JOIN users u ON u.id = l.user_id
                               WHERE l.user_id = ?
                               ORDER BY l.created_at DESC");
        $stmt->execute([$current_user_id]);
    }
    $leaves = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_msg = 'Could not fetch leave records: ' . $e->getMessage();
}

// ── Stats ─────────────────────────────────────────────────────────────────────
$total_requests   = count($leaves);
$pending_requests = 0;
$approved_requests= 0;
$rejected_requests= 0;
$total_days       = 0;
$pending_days     = 0;
$approved_days    = 0;
$rejected_days    = 0;

foreach ($leaves as $lv) {
    $days = (int)((strtotime($lv['end_date']) - strtotime($lv['start_date'])) / 86400) + 1;
    $total_days += $days;
    if ($lv['status'] === 'pending')  { $pending_requests++;  $pending_days  += $days; }
    if ($lv['status'] === 'approved') { $approved_requests++; $approved_days += $days; }
    if ($lv['status'] === 'rejected') { $rejected_requests++; $rejected_days += $days; }
}
?>

<!-- ── Page Header ──────────────────────────────────────────────────────────── -->
<div class="page-header" style="align-items:flex-start;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-dark);">Leave Management</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Manage and track employee leave requests.</p>
    </div>
    <div style="display:flex;gap:10px;">
        <button class="btn btn-primary" onclick="openModal('applyLeaveModal')">
            + Apply Leave
        </button>
    </div>
</div>

<!-- ── Alerts ───────────────────────────────────────────────────────────────── -->
<?php if ($success_msg): ?>
    <div class="alert alert-success" style="margin-bottom:1rem;"><?php echo htmlspecialchars($success_msg); ?></div>
<?php endif; ?>
<?php if ($error_msg): ?>
    <div class="alert alert-danger" style="margin-bottom:1rem;"><?php echo htmlspecialchars($error_msg); ?></div>
<?php endif; ?>

<!-- ── Stats by Requests ────────────────────────────────────────────────────── -->
<h3 style="font-size:1rem;font-weight:600;margin-bottom:1rem;">Statistics by Requests</h3>
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:2rem;">
    <div class="stat-card glass-card" style="padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:var(--primary);"><?php echo $total_requests; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Total Leaves</div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:var(--warning);"><?php echo $pending_requests; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Pending Approval</div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:var(--success);"><?php echo $approved_requests; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Approved</div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:var(--danger);"><?php echo $rejected_requests; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Rejected</div>
    </div>
</div>

<!-- ── Stats by Days ────────────────────────────────────────────────────────── -->
<h3 style="font-size:1rem;font-weight:600;margin-bottom:1rem;">Statistics by Days</h3>
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:2rem;">
    <div class="stat-card glass-card" style="padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:var(--primary);"><?php echo $total_days; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Total Days</div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:var(--warning);"><?php echo $pending_days; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Pending Days</div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:var(--success);"><?php echo $approved_days; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Approved Days</div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:var(--danger);"><?php echo $rejected_days; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Rejected Days</div>
    </div>
</div>

<!-- ── Leave Table ───────────────────────────────────────────────────────────── -->
<div class="glass-card" style="padding:1.5rem;">

    <!-- Filters -->
    <div style="margin-bottom:1.5rem;">
        <h3 style="font-size:1rem;margin-bottom:1rem;font-weight:600;">Filters &amp; Search</h3>
        <div class="filter-bar" style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;">
            <div>
                <label class="form-label" style="font-size:0.75rem;">Search</label>
                <input type="text" id="filterSearch" placeholder="Search name, type, reason…" class="form-control" oninput="applyFilters()">
            </div>
            <div>
                <label class="form-label" style="font-size:0.75rem;">Leave Type</label>
                <select id="filterType" class="form-control" onchange="applyFilters()">
                    <option value="">All Types</option>
                    <option value="Sick">Sick</option>
                    <option value="Casual">Casual</option>
                    <option value="Annual">Annual</option>
                    <option value="Emergency">Emergency</option>
                </select>
            </div>
            <div>
                <label class="form-label" style="font-size:0.75rem;">Status</label>
                <select id="filterStatus" class="form-control" onchange="applyFilters()">
                    <option value="">All Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
            </div>
            <div style="display:flex;align-items:flex-end;">
                <button class="btn glass-card" onclick="clearFilters()" style="width:100%;">Clear Filters</button>
            </div>
        </div>
    </div>

    <div style="border-top:1px solid var(--border);margin-bottom:1rem;padding-top:1rem;display:flex;justify-content:space-between;align-items:center;">
        <h3 style="font-size:1rem;font-weight:600;"><?php echo $is_admin ? 'All Leave Requests' : 'My Leave Requests'; ?></h3>
        <span id="rowCount" style="font-size:0.8rem;color:var(--text-muted);"></span>
    </div>

    <div class="data-table-container">
        <table id="leavesTable">
            <thead>
                <tr>
                    <th>#</th>
                    <?php if ($is_admin): ?>
                    <th>Employee</th>
                    <?php endif; ?>
                    <th>Leave Type</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Days</th>
                    <th>Reason</th>
                    <th>Applied On</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaves)): ?>
                <tr id="emptyRow">
                    <td colspan="<?php echo $is_admin ? 10 : 9; ?>" style="text-align:center;padding:3rem;">
                        <div style="color:var(--text-muted);margin-bottom:10px;">
                            <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="opacity:0.5;">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                                <line x1="16" y1="2" x2="16" y2="6"></line>
                                <line x1="8" y1="2" x2="8" y2="6"></line>
                                <line x1="3" y1="10" x2="21" y2="10"></line>
                            </svg>
                        </div>
                        <div style="font-weight:500;color:var(--text-dark);">No leave requests found</div>
                        <div style="font-size:0.875rem;color:var(--text-muted);">Click "Apply Leave" to submit a new request.</div>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($leaves as $i => $lv):
                    $days = (int)((strtotime($lv['end_date']) - strtotime($lv['start_date'])) / 86400) + 1;
                    $badge_class = match($lv['status']) {
                        'approved' => 'badge-success',
                        'rejected' => 'badge-danger',
                        default    => 'badge-warning',
                    };
                    $emp_name = htmlspecialchars($lv['employee_name'] ?? 'Unknown');
                ?>
                <tr class="leave-row"
                    data-search="<?php echo strtolower($emp_name . ' ' . $lv['leave_type'] . ' ' . $lv['reason']); ?>"
                    data-type="<?php echo htmlspecialchars($lv['leave_type']); ?>"
                    data-status="<?php echo htmlspecialchars($lv['status']); ?>">
                    <td><?php echo $i + 1; ?></td>
                    <?php if ($is_admin): ?>
                    <td><strong><?php echo $emp_name; ?></strong></td>
                    <?php endif; ?>
                    <td><?php echo htmlspecialchars($lv['leave_type']); ?></td>
                    <td><?php echo htmlspecialchars($lv['start_date']); ?></td>
                    <td><?php echo htmlspecialchars($lv['end_date']); ?></td>
                    <td><?php echo $days; ?></td>
                    <td style="max-width:200px;white-space:normal;font-size:0.85rem;">
                        <?php echo htmlspecialchars(mb_strimwidth($lv['reason'] ?? '', 0, 80, '…')); ?>
                    </td>
                    <td><?php echo date('d M Y', strtotime($lv['created_at'])); ?></td>
                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($lv['status']); ?></span></td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <?php if ($is_admin && $lv['status'] === 'pending'): ?>
                            <!-- Approve -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action"   value="approve_leave">
                                <input type="hidden" name="leave_id" value="<?php echo $lv['id']; ?>">
                                <button type="submit" class="btn btn-success" style="padding:4px 10px;font-size:0.78rem;">Approve</button>
                            </form>
                            <!-- Reject -->
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Reject this leave request?');">
                                <input type="hidden" name="action"   value="reject_leave">
                                <input type="hidden" name="leave_id" value="<?php echo $lv['id']; ?>">
                                <button type="submit" class="btn btn-danger" style="padding:4px 10px;font-size:0.78rem;">Reject</button>
                            </form>
                            <?php endif; ?>

                            <?php if ($is_admin || ($lv['user_id'] == $current_user_id && $lv['status'] === 'pending')): ?>
                            <!-- Delete -->
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this leave request? This cannot be undone.');">
                                <input type="hidden" name="action"   value="delete_leave">
                                <input type="hidden" name="leave_id" value="<?php echo $lv['id']; ?>">
                                <button type="submit" class="btn btn-danger" style="padding:4px 10px;font-size:0.78rem;background:transparent;border:1px solid var(--danger);color:var(--danger);">Delete</button>
                            </form>
                            <?php endif; ?>

                            <?php if (!$is_admin && $lv['status'] !== 'pending'): ?>
                                <span style="font-size:0.8rem;color:var(--text-muted);">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>

                <!-- No-match row (hidden by default) -->
                <tr id="noMatchRow" style="display:none;">
                    <td colspan="<?php echo $is_admin ? 10 : 9; ?>" style="text-align:center;padding:2rem;color:var(--text-muted);">
                        No records match your filters.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Apply Leave Modal ─────────────────────────────────────────────────────── -->
<div class="modal-overlay" id="applyLeaveModal">
    <div class="modal-box" style="max-width:480px;width:100%;">
        <div class="modal-header">
            <h3 style="font-size:1.1rem;font-weight:600;">Apply for Leave</h3>
            <button class="modal-close" onclick="closeModal('applyLeaveModal')">&times;</button>
        </div>
        <form method="POST" style="padding:1.5rem;">
            <input type="hidden" name="action" value="apply_leave">

            <div class="form-group">
                <label class="form-label">Leave Type <span style="color:var(--danger);">*</span></label>
                <select name="leave_type" class="form-control" required>
                    <option value="" disabled selected>Select leave type</option>
                    <option value="Sick">Sick Leave</option>
                    <option value="Casual">Casual Leave</option>
                    <option value="Annual">Annual Leave</option>
                    <option value="Emergency">Emergency Leave</option>
                </select>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Start Date <span style="color:var(--danger);">*</span></label>
                    <input type="date" name="start_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">End Date <span style="color:var(--danger);">*</span></label>
                    <input type="date" name="end_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Reason</label>
                <textarea name="reason" class="form-control" rows="4" placeholder="Brief reason for leave…" style="resize:vertical;"></textarea>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:1rem;">
                <button type="button" class="btn glass-card" onclick="closeModal('applyLeaveModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit Application</button>
            </div>
        </form>
    </div>
</div>

<!-- ── JS: modal helpers & client-side filter ───────────────────────────────── -->
<script>
/* Modal helpers */
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

/* Close modal on overlay click */
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeModal(overlay.id);
    });
});

/* Client-side filter */
function applyFilters() {
    var search   = document.getElementById('filterSearch').value.toLowerCase().trim();
    var type     = document.getElementById('filterType').value.toLowerCase();
    var status   = document.getElementById('filterStatus').value.toLowerCase();
    var rows     = document.querySelectorAll('.leave-row');
    var visible  = 0;

    rows.forEach(function(row) {
        var ds = row.dataset.search  || '';
        var dt = row.dataset.type    ? row.dataset.type.toLowerCase()   : '';
        var dstat = row.dataset.status ? row.dataset.status.toLowerCase() : '';

        var matchSearch = !search || ds.includes(search);
        var matchType   = !type   || dt === type;
        var matchStatus = !status || dstat === status;

        if (matchSearch && matchType && matchStatus) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    var noMatch = document.getElementById('noMatchRow');
    if (noMatch) noMatch.style.display = (visible === 0 && rows.length > 0) ? '' : 'none';

    var countEl = document.getElementById('rowCount');
    if (countEl) countEl.textContent = 'Showing ' + visible + ' of ' + rows.length + ' record(s)';
}

/* Initialise count on load */
window.addEventListener('DOMContentLoaded', function() {
    applyFilters();
});

function clearFilters() {
    document.getElementById('filterSearch').value = '';
    document.getElementById('filterType').value   = '';
    document.getElementById('filterStatus').value = '';
    applyFilters();
}

/* Auto-close alert after 4 s */
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(function(el) {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity = '0';
        setTimeout(function() { el.remove(); }, 500);
    });
}, 4000);
</script>

<?php include_once '../../includes/footer.php'; ?>
