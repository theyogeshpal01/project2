<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

// Since no leaves table exists yet in schema, using a blank array
$leaves = [];
$total_requests = 0;
$pending_requests = 0;
$approved_requests = 0;
$rejected_requests = 0;

$total_days = 0;
$pending_days = 0;
$approved_days = 0;
$rejected_days = 0;
?>

<div class="page-header" style="align-items:flex-start;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-dark);">Leave Management</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Manage and track employee leave requests.</p>
    </div>
    <div style="display:flex;gap:10px;">
        <button class="btn glass-card">Export CSV</button>
        <button class="btn glass-card">Clear Filters</button>
    </div>
</div>

<h3 style="font-size:1rem;font-weight:600;margin-bottom:1rem;">Statistics by Requests</h3>
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:2rem;">
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

<h3 style="font-size:1rem;font-weight:600;margin-bottom:1rem;">Statistics by Days</h3>
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:2rem;">
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

<div class="glass-card" style="padding:1.5rem;">
    <!-- Filters -->
    <div style="margin-bottom:1.5rem;">
        <h3 style="font-size:1rem;margin-bottom:1rem;font-weight:600;">Filters & Search</h3>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;">
            <div>
                <label style="font-size:0.75rem;color:var(--text-muted);display:block;margin-bottom:5px;">Search</label>
                <input type="text" placeholder="Search by name or ID..." class="form-control">
            </div>
            <div>
                <label style="font-size:0.75rem;color:var(--text-muted);display:block;margin-bottom:5px;">Leave Type</label>
                <select class="form-control"><option>All Types</option></select>
            </div>
            <div>
                <label style="font-size:0.75rem;color:var(--text-muted);display:block;margin-bottom:5px;">Status</label>
                <select class="form-control"><option>All Status</option></select>
            </div>
            <div>
                <label style="font-size:0.75rem;color:var(--text-muted);display:block;margin-bottom:5px;">Date Range</label>
                <input type="text" placeholder="Select date range" class="form-control">
            </div>
        </div>
    </div>

    <div style="border-top:1px solid var(--border);margin-bottom:1rem;padding-top:1rem;">
        <h3 style="font-size:1rem;font-weight:600;">All Leave Requests</h3>
    </div>

    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Leave Type</th>
                    <th>Duration</th>
                    <th>Applied On</th>
                    <th>Reason</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($leaves)): ?>
                    <tr>
                        <td colspan="7" style="text-align:center; padding:3rem;">
                            <div style="color:var(--text-muted);margin-bottom:10px;">
                                <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="opacity:0.5;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                            </div>
                            <div style="font-weight:500;color:var(--text-dark);">No leave requests found</div>
                            <div style="font-size:0.875rem;color:var(--text-muted);">There are no leave requests matching your criteria.</div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
