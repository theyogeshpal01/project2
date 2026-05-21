<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

$user_id = $_SESSION['user_id'] ?? 1;

$announcements = $pdo->prepare("SELECT * FROM announcements WHERE created_by = ? ORDER BY created_at DESC");
$announcements->execute([$user_id]);
$my_announcements = $announcements->fetchAll();

$total = count($my_announcements);
$active = 0; // if status existed
$inactive = 0;
$categories = 0; // if category existed

?>

<div class="page-header" style="align-items:flex-start;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-dark);">Announcement Management</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Manage company-wide announcements and updates.</p>
    </div>
    <div style="display:flex;gap:10px;">
        <button class="btn glass-card">Export CSV</button>
        <button class="btn glass-card">Clear Filters</button>
        <button class="btn btn-primary">+ New Announcement</button>
    </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:2rem;">
    <div class="stat-card glass-card" style="padding:1.5rem;text-align:center;">
        <div style="font-size:2rem;font-weight:700;color:var(--primary);"><?php echo $total; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">My Total Announcements</div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;text-align:center;">
        <div style="font-size:2rem;font-weight:700;color:var(--success);"><?php echo $active; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">My Active</div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;text-align:center;">
        <div style="font-size:2rem;font-weight:700;color:var(--danger);"><?php echo $inactive; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">My Inactive</div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;text-align:center;">
        <div style="font-size:2rem;font-weight:700;color:#0ea5e9;"><?php echo $categories; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">My Categories</div>
    </div>
</div>

<div class="glass-card" style="padding:1.5rem;">
    <!-- Filters -->
    <div style="margin-bottom:1.5rem;">
        <h3 style="font-size:1rem;margin-bottom:1rem;font-weight:600;">Filters & Search</h3>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;">
            <div style="grid-column: span 2;">
                <label style="font-size:0.75rem;color:var(--text-muted);display:block;margin-bottom:5px;">Search</label>
                <input type="text" placeholder="Search by title or content..." class="form-control">
            </div>
            <div>
                <label style="font-size:0.75rem;color:var(--text-muted);display:block;margin-bottom:5px;">Category</label>
                <select class="form-control"><option>All Categories</option></select>
            </div>
            <div>
                <label style="font-size:0.75rem;color:var(--text-muted);display:block;margin-bottom:5px;">Status</label>
                <select class="form-control"><option>All Status</option></select>
            </div>
        </div>
    </div>

    <div style="border-top:1px solid var(--border);margin-bottom:1rem;padding-top:1rem;display:flex;justify-content:space-between;align-items:center;">
        <h3 style="font-size:1rem;font-weight:600;">My Announcements</h3>
        <span style="font-size:0.875rem;color:var(--text-muted);">Showing <?php echo $total; ?> announcements</span>
    </div>

    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Target Role</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($my_announcements)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; padding:3rem;">
                            <div style="color:var(--text-muted);margin-bottom:10px;">
                                <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="opacity:0.5;"><path d="M11 5L6 9H2v6h4l5 4V5z"></path><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
                            </div>
                            <div style="font-weight:500;color:var(--text-dark);">No announcements found</div>
                            <div style="font-size:0.875rem;color:var(--text-muted);">You haven't created any announcements matching these criteria.</div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
