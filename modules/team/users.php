<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

$current_user_id = $_SESSION['user_id'];

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role_id = (int) $_POST['role_id'];
    $team_id = $_POST['team_id'] ?: null;
    $manager_id = $_POST['manager_id'] ?: null;
    $phone = trim($_POST['phone'] ?? '');

    try {
        $pdo->prepare("INSERT INTO users (name, email, password, role_id, team_id, manager_id, phone) VALUES (?,?,?,?,?,?,?)")
            ->execute([$name, $email, $password, $role_id, $team_id, $manager_id, $phone ?: null]);
        $success = "User '$name' added successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $uid = (int) $_POST['user_id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $role_id = (int) $_POST['role_id'];
    $team_id = $_POST['team_id'] ?: null;
    $manager_id = $_POST['manager_id'] ?: null;
    $status = $_POST['status'];
    $phone = trim($_POST['phone'] ?? '');

    try {
        if (!empty($_POST['password'])) {
            $pdo->prepare("UPDATE users SET name=?, email=?, password=?, role_id=?, team_id=?, manager_id=?, status=?, phone=? WHERE id=?")
                ->execute([$name, $email, password_hash($_POST['password'], PASSWORD_DEFAULT), $role_id, $team_id, $manager_id, $status, $phone ?: null, $uid]);
        } else {
            $pdo->prepare("UPDATE users SET name=?, email=?, role_id=?, team_id=?, manager_id=?, status=?, phone=? WHERE id=?")
                ->execute([$name, $email, $role_id, $team_id, $manager_id, $status, $phone ?: null, $uid]);
        }
        $success = "User updated successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $uid = (int) $_POST['user_id'];
    if ($uid === (int) $current_user_id) {
        $error = "You cannot delete your own account.";
    } elseif ($uid === 1) {
        $error = "The system admin account cannot be deleted.";
    } else {
        try {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            $success = "User deleted successfully.";
        } catch (Exception $e) {
            // If foreign key constraint, just deactivate instead
            $pdo->prepare("UPDATE users SET status='inactive' WHERE id=?")->execute([$uid]);
            $success = "User deactivated (has linked records).";
        }
    }
}

$users = $pdo->query("SELECT u.*, r.role_name, t.team_name, m.name as manager_name
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN teams t ON u.team_id = t.id
    LEFT JOIN users m ON u.manager_id = m.id
    ORDER BY u.created_at DESC")->fetchAll();

$roles = getRoles($pdo);
$teams = getTeams($pdo);
$managers = $pdo->query("SELECT id, name FROM users WHERE role_id IN (1,2,3) ORDER BY name")->fetchAll();

// Stats
$total = count($users);
$active = count(array_filter($users, fn($u) => $u['status'] === 'active'));
$inactive = $total - $active;
$designations = $pdo->query("SELECT COUNT(DISTINCT role_id) FROM users")->fetchColumn();
?>

<div class="page-header" style="align-items:flex-start;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-dark);">Employees Management</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Manage employees added by you</p>
        <div style="display:flex;gap:10px;margin-top:1rem;">
            <button class="btn btn-primary" style="background:#000000;color:var(--primary);border:1px solid #e2e8f0;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg> Employees
            </button>
            <button class="btn" style="background:transparent;color:var(--text-muted);border:none;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg> Form Links
            </button>
        </div>
    </div>
    <div style="display:flex;gap:10px;">
        <button class="btn glass-card">Clear Filters</button>
        <button class="btn btn-primary" onclick="openAddModal()">+ Add Employee</button>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:1.5rem;">
    <div class="stat-card glass-card" style="text-align:center;padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:var(--primary);"><?php echo $total; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Total Employees</div>
    </div>
    <div class="stat-card glass-card" style="text-align:center;padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:var(--success);"><?php echo $active; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Active Employees</div>
    </div>
    <div class="stat-card glass-card" style="text-align:center;padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:var(--danger);"><?php echo $inactive; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Inactive Employees</div>
    </div>
    <div class="stat-card glass-card" style="text-align:center;padding:1.5rem;">
        <div style="font-size:2rem;font-weight:700;color:#0ea5e9;"><?php echo $designations; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Designations</div>
    </div>
</div>

<div class="glass-card" style="padding:1.5rem;">
    <!-- Filters -->
    <div style="margin-bottom:1.5rem;">
        <h3 style="font-size:1rem;margin-bottom:1rem;font-weight:600;">Filters</h3>
        <div class="filter-bar" style="padding:0; margin-bottom:0;">
            <div>
                <label style="font-size:0.75rem;color:var(--text-muted);display:block;margin-bottom:5px;">Search</label>
                <input type="text" id="user-search" onkeyup="filterUsers()" placeholder="Search by name, email, designation..." class="form-control">
            </div>
            <div>
                <label style="font-size:0.75rem;color:var(--text-muted);display:block;margin-bottom:5px;">Role</label>
                <select class="form-control"><option>All Roles</option></select>
            </div>
            <div>
                <label style="font-size:0.75rem;color:var(--text-muted);display:block;margin-bottom:5px;">Designation</label>
                <input type="text" placeholder="Filter by designation..." class="form-control">
            </div>
            <div>
                <label style="font-size:0.75rem;color:var(--text-muted);display:block;margin-bottom:5px;">Status</label>
                <select class="form-control"><option>All Status</option></select>
            </div>
        </div>
    </div>

    <div style="border-top:1px solid var(--border);margin-bottom:1rem;padding-top:1rem;display:flex;justify-content:space-between;align-items:center;">
        <h3 style="font-size:1rem;font-weight:600;">Employees List</h3>
        <span style="font-size:0.875rem;color:var(--text-muted);">Showing <?php echo $total; ?> employees</span>
    </div>

    <div class="data-table-container">
        <table id="users-table">
            <thead>
                <tr>
                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Role</th>
                    <th>Manager</th>
                    <th>Designation</th>
                    <th>Department</th>
                    <th>Salary</th>
                    <th>Joined On</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="12" style="text-align:center; padding:3rem; color:var(--text-muted);">No employees found.</td></tr>
                <?php endif; ?>
                <?php foreach ($users as $user): 
                    $emp_id = 'EMP' . str_pad($user['id'], 4, '0', STR_PAD_LEFT);
                ?>
                    <tr>
                        <td style="font-weight:500;"><?php echo $emp_id; ?></td>
                        <td>
                            <div style="font-weight:600;"><?php echo htmlspecialchars($user['name']); ?></div>
                        </td>
                        <td style="font-size:0.875rem;"><?php echo htmlspecialchars($user['email']); ?></td>
                        <td style="font-size:0.875rem;"><?php echo htmlspecialchars($user['phone'] ?? '—'); ?></td>
                        <td>
                            <span class="badge" style="background:rgba(168,85,247,0.1);color:#9333ea;">
                                <?php echo htmlspecialchars($user['role_name'] ?? '—'); ?>
                            </span>
                        </td>
                        <td style="font-size:0.875rem;"><?php echo htmlspecialchars($user['manager_name'] ?? 'N/A'); ?></td>
                        <td style="font-size:0.875rem;"><?php echo htmlspecialchars($user['role_name'] ?? '—'); ?></td>
                        <td style="font-size:0.875rem;"><?php echo htmlspecialchars($user['dept_name'] ?? 'Administration'); ?></td>
                        <td style="font-size:0.875rem;">₹<?php echo number_format($user['base_salary'] ?? 0); ?></td>
                        <td style="font-size:0.875rem;"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <?php
                            $sc = ['active' => 'success', 'inactive' => 'danger', 'suspended' => 'warning'];
                            echo '<span class="badge badge-' . ($sc[$user['status']] ?? 'muted') . '">' . ucfirst($user['status']) . '</span>';
                            ?>
                        </td>
                        <td>
                            <div style="display:flex; gap:6px; align-items:center;">
                                <button
                                    class="btn btn-primary"
                                    style="padding:4px 8px;font-size:0.75rem;"
                                    data-user="<?php echo htmlspecialchars(json_encode(array_intersect_key($user, array_flip(['id','name','email','phone','role_id','team_id','manager_id','status']))), ENT_QUOTES); ?>"
                                    onclick="openEditModal(this)">Edit</button>

                                <?php if ($user['id'] != $current_user_id && $user['id'] != 1): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this user? This cannot be undone.')">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" name="delete_user" value="1" class="btn btn-danger" style="padding:4px 8px;font-size:0.75rem;">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <button disabled title="This account cannot be deleted" class="btn glass-card" style="padding:4px 8px;font-size:0.75rem;opacity:0.5;cursor:not-allowed;">Delete</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal-overlay" id="add-user-modal">
    <div class="modal-box" style="width:620px;">
        <div class="modal-header">
            <h3>Add New User</h3>
            <button class="modal-close" onclick="document.getElementById('add-user-modal').classList.remove('open')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="name"
                        class="form-control" required></div>
                <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email"
                        class="form-control" required></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group"><label class="form-label">Password *</label><input type="password" name="password"
                        class="form-control" required placeholder="Min. 6 characters"></div>
                <div class="form-group"><label class="form-label">Phone Number</label><input type="tel" name="phone"
                        class="form-control" placeholder="e.g. +91 98765 43210" maxlength="20"></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select name="role_id" class="form-control" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo $r['role_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Team</label>
                    <select name="team_id" class="form-control">
                        <option value="">No Team</option>
                        <?php foreach ($teams as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['team_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Reporting Manager</label>
                <select name="manager_id" class="form-control">
                    <option value="">None</option>
                    <?php foreach ($managers as $m): ?>
                        <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:1rem;">
                <button type="button" class="btn glass-card"
                    onclick="document.getElementById('add-user-modal').classList.remove('open')">Cancel</button>
                <button type="submit" name="add_user" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal-overlay" id="edit-user-modal">
    <div class="modal-box" style="width:620px;">
        <div class="modal-header">
            <h3>Edit User</h3>
            <button class="modal-close" onclick="document.getElementById('edit-user-modal').classList.remove('open')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="user_id" id="edit-user-id">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="name"
                        id="edit-name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email"
                        id="edit-email" class="form-control" required></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group"><label class="form-label">New Password <span
                            style="color:var(--text-muted); font-weight:400;">(leave blank to keep
                            current)</span></label><input type="password" name="password" class="form-control"
                        placeholder="Leave blank to keep current"></div>
                <div class="form-group"><label class="form-label">Phone Number</label><input type="tel" name="phone"
                        id="edit-phone" class="form-control" placeholder="e.g. +91 98765 43210" maxlength="20"></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select name="role_id" id="edit-role" class="form-control" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo $r['id']; ?>"><?php echo $r['role_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="edit-status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Team</label>
                    <select name="team_id" id="edit-team" class="form-control">
                        <option value="">No Team</option>
                        <?php foreach ($teams as $t): ?>
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['team_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Reporting Manager</label>
                <select name="manager_id" id="edit-manager" class="form-control">
                    <option value="">None</option>
                    <?php foreach ($managers as $m): ?>
                        <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:1rem;">
                <button type="button" class="btn glass-card"
                    onclick="document.getElementById('edit-user-modal').classList.remove('open')">Cancel</button>
                <button type="submit" name="edit_user" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('add-user-modal').classList.add('open');
    }

    function openEditModal(btn) {
        var user = JSON.parse(btn.getAttribute('data-user'));
        document.getElementById('edit-user-id').value = user.id;
        document.getElementById('edit-name').value = user.name;
        document.getElementById('edit-email').value = user.email || '';
        document.getElementById('edit-phone').value = user.phone || '';
        document.getElementById('edit-role').value = user.role_id;
        document.getElementById('edit-status').value = user.status;
        document.getElementById('edit-team').value = user.team_id || '';
        document.getElementById('edit-manager').value = user.manager_id || '';
        document.getElementById('edit-user-modal').classList.add('open');
    }

    function filterUsers() {
        const q = document.getElementById('user-search').value.toLowerCase();
        document.querySelectorAll('#users-table tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    }
</script>

<?php include_once '../../includes/footer.php'; ?>