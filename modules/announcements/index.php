<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

$user_id = $_SESSION['user_id'] ?? 1;
$role_id = $_SESSION['role_id'] ?? 1;

// ── Auto-add missing columns — each in its own try-catch ──
// Add category column
try { $pdo->exec("ALTER TABLE announcements ADD COLUMN category VARCHAR(100) NOT NULL DEFAULT 'General'"); }
catch (Exception $e) { /* already exists - ok */ }

// Add status column
try { $pdo->exec("ALTER TABLE announcements ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'active'"); }
catch (Exception $e) { /* already exists - ok */ }

// Add or fix target_role column (old schema has INT, we need VARCHAR)
try {
    $col = $pdo->query("SHOW COLUMNS FROM announcements LIKE 'target_role'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE announcements ADD COLUMN target_role VARCHAR(50) NOT NULL DEFAULT 'all'");
    } elseif (stripos($col['Type'], 'int') !== false) {
        // Convert INT to VARCHAR safely
        $pdo->exec("ALTER TABLE announcements MODIFY COLUMN target_role VARCHAR(50) NOT NULL DEFAULT 'all'");
    }
} catch (Exception $e) { /* silent */ }

// ── Handle Create ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title       = trim($_POST['title'] ?? '');
    $content     = trim($_POST['content'] ?? '');
    $category    = trim($_POST['category'] ?? 'General');
    $target_role = trim($_POST['target_role'] ?? 'all');
    $status      = trim($_POST['status'] ?? 'active');

    if ($title && $content) {
        try {
            $pdo->prepare("INSERT INTO announcements (title, content, category, target_role, status, created_by, created_at)
                           VALUES (?,?,?,?,?,?,NOW())")
                ->execute([$title, $content, $category, $target_role, $status, $user_id]);
            $success = "Announcement \"$title\" published successfully!";
        } catch (Exception $e) {
            $error = "Create failed: " . $e->getMessage();
        }
    } else {
        $error = "Title and content are required.";
    }
}

// ── Handle Edit ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_announcement'])) {
    $aid         = (int)($_POST['announcement_id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $content     = trim($_POST['content'] ?? '');
    $category    = trim($_POST['category'] ?? 'General');
    $target_role = trim($_POST['target_role'] ?? 'all');
    $status      = trim($_POST['status'] ?? 'active');

    if ($aid && $title && $content) {
        try {
            $pdo->prepare("UPDATE announcements SET title=?, content=?, category=?, target_role=?, status=? WHERE id=? AND created_by=?")
                ->execute([$title, $content, $category, $target_role, $status, $aid, $user_id]);
            $success = "Announcement updated successfully!";
        } catch (Exception $e) {
            $error = "Update failed: " . $e->getMessage();
        }
    } else {
        $error = "Title and content are required.";
    }
}

// ── Handle Delete ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    $aid = (int)($_POST['announcement_id'] ?? 0);
    if ($aid) {
        try {
            $pdo->prepare("DELETE FROM announcements WHERE id=? AND created_by=?")->execute([$aid, $user_id]);
            $success = "Announcement deleted successfully.";
        } catch (Exception $e) {
            $error = "Delete failed: " . $e->getMessage();
        }
    }
}

// ── Fetch all ──
try {
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE created_by=? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $my_announcements = $stmt->fetchAll();
} catch (Exception $e) {
    $my_announcements = [];
    $error = "Fetch error: " . $e->getMessage();
}

$total    = count($my_announcements);
$active   = count(array_filter($my_announcements, fn($a) => ($a['status'] ?? 'active') === 'active'));
$inactive = $total - $active;
$cats     = count(array_unique(array_filter(array_column($my_announcements, 'category'))));
?>

<style>
.ann-modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(15,23,42,0.55); backdrop-filter: blur(4px);
    z-index: 1000; justify-content: center; align-items: center;
}
.ann-modal-overlay.open { display: flex; }
.ann-modal-box {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: 16px; padding: 2rem; width: 620px;
    max-width: 95vw; max-height: 90vh; overflow-y: auto;
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
    animation: slideIn 0.2s ease;
}
@keyframes slideIn { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.ann-modal-head {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border);
}
.ann-modal-head h3 { font-size: 1.05rem; font-weight: 700; }
.ann-close-btn {
    background: none; border: none; cursor: pointer; color: var(--text-muted);
    padding: 4px; border-radius: 6px; transition: all 0.2s;
    display: flex; align-items: center; justify-content: center;
}
.ann-close-btn:hover { background: rgba(239,68,68,0.08); color: var(--danger); }
</style>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- ── Page Header ── -->
<div class="page-header">
    <div>
        <h1 style="font-size:1.5rem; font-weight:800;">Announcement Management</h1>
        <p style="color:var(--text-muted); font-size:0.85rem; margin-top:4px;">Create and manage company-wide announcements.</p>
    </div>
    <div style="display:flex; gap:10px;">
        <button class="btn glass-card" onclick="clearFilters()">Clear Filters</button>
        <button class="btn btn-primary" onclick="openCreateModal()">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            New Announcement
        </button>
    </div>
</div>

<!-- ── Stats ── -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:1.75rem;">
    <div class="stat-card glass-card" style="padding:1.5rem;">
        <div class="stat-card-text">
            <div class="stat-label">Total</div>
            <div class="stat-value"><?php echo $total; ?></div>
            <div class="stat-sub">All announcements</div>
        </div>
        <div class="stat-card-icon" style="background:rgba(37,99,235,0.08); color:var(--primary);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M11 5L6 9H2v6h4l5 4V5z"></path><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
        </div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;">
        <div class="stat-card-text">
            <div class="stat-label">Active</div>
            <div class="stat-value" style="color:var(--success);"><?php echo $active; ?></div>
            <div class="stat-sub">Currently live</div>
        </div>
        <div class="stat-card-icon" style="background:rgba(16,185,129,0.08); color:var(--success);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path></svg>
        </div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;">
        <div class="stat-card-text">
            <div class="stat-label">Inactive</div>
            <div class="stat-value" style="color:var(--danger);"><?php echo $inactive; ?></div>
            <div class="stat-sub">Paused / Archived</div>
        </div>
        <div class="stat-card-icon" style="background:rgba(239,68,68,0.08); color:var(--danger);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
        </div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;">
        <div class="stat-card-text">
            <div class="stat-label">Categories</div>
            <div class="stat-value" style="color:#0ea5e9;"><?php echo $cats; ?></div>
            <div class="stat-sub">Unique categories</div>
        </div>
        <div class="stat-card-icon" style="background:rgba(14,165,233,0.08); color:#0ea5e9;">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
        </div>
    </div>
</div>

<!-- ── Table Card ── -->
<div class="glass-card" style="padding:1.5rem;">
    <!-- Filters -->
    <div style="margin-bottom:1.5rem;">
        <h3 style="font-size:0.95rem; font-weight:700; margin-bottom:0.875rem;">Filters &amp; Search</h3>
        <div style="display:grid; grid-template-columns:2fr 1fr 1fr; gap:1rem;">
            <div>
                <label style="font-size:0.75rem;color:var(--text-muted);display:block;margin-bottom:5px;">Search</label>
                <input type="text" id="ann-search" oninput="filterTable()" placeholder="Search by title or content..." class="form-control">
            </div>
            <div>
                <label style="font-size:0.75rem;color:var(--text-muted);display:block;margin-bottom:5px;">Category</label>
                <select id="cat-filter" onchange="filterTable()" class="form-control">
                    <option value="">All Categories</option>
                    <option>General</option><option>HR</option><option>IT</option>
                    <option>Finance</option><option>Operations</option>
                </select>
            </div>
            <div>
                <label style="font-size:0.75rem;color:var(--text-muted);display:block;margin-bottom:5px;">Status</label>
                <select id="status-filter" onchange="filterTable()" class="form-control">
                    <option value="">All Status</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
        </div>
    </div>

    <div style="display:flex; justify-content:space-between; align-items:center; border-top:1px solid var(--border); padding-top:1rem; margin-bottom:1rem;">
        <h3 style="font-size:0.95rem; font-weight:700;">Announcements List</h3>
        <span id="showing-count" style="font-size:0.82rem; color:var(--text-muted);">Showing <?php echo $total; ?> announcements</span>
    </div>

    <div class="data-table-container">
        <table id="ann-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title &amp; Content</th>
                    <th>Category</th>
                    <th>Target</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($my_announcements)): ?>
                <tr id="empty-row">
                    <td colspan="7" style="text-align:center; padding:3rem;">
                        <svg width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="opacity:0.3; display:block; margin:0 auto 1rem;"><path d="M11 5L6 9H2v6h4l5 4V5z"></path><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
                        <div style="font-weight:600; margin-bottom:6px; color:var(--text-main);">No announcements yet</div>
                        <div style="font-size:0.82rem; color:var(--text-muted);">Click "+ New Announcement" to create your first one.</div>
                    </td>
                </tr>
                <?php endif; ?>

                <?php foreach ($my_announcements as $i => $ann):
                    $ann_status  = $ann['status'] ?? 'active';
                    $ann_cat     = $ann['category'] ?? 'General';
                    $ann_target  = $ann['target_role'] ?? 'all';
                    $sc = ['active'=>'success','inactive'=>'danger'];
                    $badge_cls = $sc[$ann_status] ?? 'muted';
                ?>
                <tr>
                    <td style="color:var(--text-muted); font-size:0.8rem; font-weight:500;"><?php echo $i+1; ?></td>
                    <td>
                        <div style="font-weight:600; font-size:0.875rem; margin-bottom:3px;"><?php echo htmlspecialchars($ann['title']); ?></div>
                        <div style="font-size:0.75rem; color:var(--text-muted);"><?php echo htmlspecialchars(mb_substr($ann['content'] ?? '', 0, 70)); ?><?php echo strlen($ann['content'] ?? '') > 70 ? '...' : ''; ?></div>
                    </td>
                    <td><span class="badge badge-accent"><?php echo htmlspecialchars($ann_cat); ?></span></td>
                    <td style="font-size:0.82rem;"><?php echo ucfirst($ann_target); ?></td>
                    <td><span class="badge badge-<?php echo $badge_cls; ?>"><?php echo ucfirst($ann_status); ?></span></td>
                    <td style="font-size:0.8rem; color:var(--text-muted); white-space:nowrap;"><?php echo date('d M Y', strtotime($ann['created_at'])); ?></td>
                    <td>
                        <div style="display:flex; gap:6px; align-items:center;">
                            <!-- Edit -->
                            <button
                                class="btn btn-primary"
                                style="padding:4px 12px; font-size:0.75rem;"
                                data-ann="<?php echo htmlspecialchars(json_encode([
                                    'id'          => $ann['id'],
                                    'title'       => $ann['title'],
                                    'content'     => $ann['content'] ?? '',
                                    'category'    => $ann_cat,
                                    'target_role' => $ann_target,
                                    'status'      => $ann_status,
                                ]), ENT_QUOTES); ?>"
                                onclick="openEditModal(this)">Edit</button>

                            <!-- Delete -->
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this announcement? This cannot be undone.')">
                                <input type="hidden" name="announcement_id" value="<?php echo $ann['id']; ?>">
                                <button type="submit" name="delete_announcement" value="1"
                                    class="btn btn-danger" style="padding:4px 12px; font-size:0.75rem;">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ────────────────── CREATE MODAL ────────────────── -->
<div class="ann-modal-overlay" id="create-modal">
    <div class="ann-modal-box">
        <div class="ann-modal-head">
            <h3>
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:6px; color:var(--primary);"><path d="M11 5L6 9H2v6h4l5 4V5z"></path><path d="M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg>
                Create New Announcement
            </h3>
            <button class="ann-close-btn" onclick="closeModal('create-modal')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Title *</label>
                <input type="text" name="title" class="form-control" placeholder="Enter announcement title..." required maxlength="255">
            </div>
            <div class="form-group">
                <label class="form-label">Content *</label>
                <textarea name="content" class="form-control" rows="5" placeholder="Write the announcement content here..." required style="resize:vertical;"></textarea>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-control">
                        <option value="General">General</option>
                        <option value="HR">HR</option>
                        <option value="IT">IT</option>
                        <option value="Finance">Finance</option>
                        <option value="Operations">Operations</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Target Audience</label>
                    <select name="target_role" class="form-control">
                        <option value="all">All Employees</option>
                        <option value="admin">Admin Only</option>
                        <option value="manager">Managers</option>
                        <option value="agent">Field Agents</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="active">Active (Live)</option>
                        <option value="inactive">Inactive (Draft)</option>
                    </select>
                </div>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:0.5rem;">
                <button type="button" class="btn glass-card" onclick="closeModal('create-modal')">Cancel</button>
                <button type="submit" name="create_announcement" class="btn btn-primary">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    Publish Announcement
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ────────────────── EDIT MODAL ────────────────── -->
<div class="ann-modal-overlay" id="edit-modal">
    <div class="ann-modal-box">
        <div class="ann-modal-head">
            <h3>
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:6px; color:var(--primary);"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                Edit Announcement
            </h3>
            <button class="ann-close-btn" onclick="closeModal('edit-modal')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="announcement_id" id="edit-ann-id">
            <div class="form-group">
                <label class="form-label">Title *</label>
                <input type="text" name="title" id="edit-title" class="form-control" required maxlength="255">
            </div>
            <div class="form-group">
                <label class="form-label">Content *</label>
                <textarea name="content" id="edit-content" class="form-control" rows="5" required style="resize:vertical;"></textarea>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" id="edit-category" class="form-control">
                        <option value="General">General</option>
                        <option value="HR">HR</option>
                        <option value="IT">IT</option>
                        <option value="Finance">Finance</option>
                        <option value="Operations">Operations</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Target Audience</label>
                    <select name="target_role" id="edit-target" class="form-control">
                        <option value="all">All Employees</option>
                        <option value="admin">Admin Only</option>
                        <option value="manager">Managers</option>
                        <option value="agent">Field Agents</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="edit-status" class="form-control">
                        <option value="active">Active (Live)</option>
                        <option value="inactive">Inactive (Draft)</option>
                    </select>
                </div>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:0.5rem;">
                <button type="button" class="btn glass-card" onclick="closeModal('edit-modal')">Cancel</button>
                <button type="submit" name="edit_announcement" class="btn btn-primary">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Modal controls ──
function openCreateModal() {
    document.getElementById('create-modal').classList.add('open');
}
function openEditModal(btn) {
    var ann = JSON.parse(btn.getAttribute('data-ann'));
    document.getElementById('edit-ann-id').value    = ann.id;
    document.getElementById('edit-title').value     = ann.title;
    document.getElementById('edit-content').value   = ann.content;
    document.getElementById('edit-category').value  = ann.category;
    document.getElementById('edit-target').value    = ann.target_role;
    document.getElementById('edit-status').value    = ann.status;
    document.getElementById('edit-modal').classList.add('open');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}
// Close modal on backdrop click
document.querySelectorAll('.ann-modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) overlay.classList.remove('open');
    });
});

// ── Filter table ──
function filterTable() {
    var q      = document.getElementById('ann-search').value.toLowerCase();
    var cat    = document.getElementById('cat-filter').value.toLowerCase();
    var status = document.getElementById('status-filter').value.toLowerCase();
    var rows   = document.querySelectorAll('#ann-table tbody tr:not(#empty-row)');
    var visible = 0;
    rows.forEach(function(row) {
        var text    = row.textContent.toLowerCase();
        var matchQ  = !q      || text.includes(q);
        var matchC  = !cat    || text.includes(cat);
        var matchS  = !status || text.includes(status);
        var show    = matchQ && matchC && matchS;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    document.getElementById('showing-count').textContent = 'Showing ' + visible + ' announcements';
}

function clearFilters() {
    document.getElementById('ann-search').value   = '';
    document.getElementById('cat-filter').value   = '';
    document.getElementById('status-filter').value= '';
    filterTable();
}
</script>

<?php include_once '../../includes/footer.php'; ?>
