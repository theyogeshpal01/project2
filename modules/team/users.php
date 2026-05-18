<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

$current_user_id = $_SESSION['user_id'];

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name       = trim($_POST['name']);
    $email      = trim($_POST['email']);
    $password   = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role_id    = (int)$_POST['role_id'];
    $team_id    = $_POST['team_id'] ?: null;
    $manager_id = $_POST['manager_id'] ?: null;

    try {
        $pdo->prepare("INSERT INTO users (name, email, password, role_id, team_id, manager_id) VALUES (?,?,?,?,?,?)")
            ->execute([$name, $email, $password, $role_id, $team_id, $manager_id]);
        $success = "User '$name' added successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $uid        = (int)$_POST['user_id'];
    $name       = trim($_POST['name']);
    $email      = trim($_POST['email']);
    $role_id    = (int)$_POST['role_id'];
    $team_id    = $_POST['team_id'] ?: null;
    $manager_id = $_POST['manager_id'] ?: null;
    $status     = $_POST['status'];

    try {
        if (!empty($_POST['password'])) {
            $pdo->prepare("UPDATE users SET name=?, email=?, password=?, role_id=?, team_id=?, manager_id=?, status=? WHERE id=?")
                ->execute([$name, $email, password_hash($_POST['password'], PASSWORD_DEFAULT), $role_id, $team_id, $manager_id, $status, $uid]);
        } else {
            $pdo->prepare("UPDATE users SET name=?, email=?, role_id=?, team_id=?, manager_id=?, status=? WHERE id=?")
                ->execute([$name, $email, $role_id, $team_id, $manager_id, $status, $uid]);
        }
        $success = "User updated successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $uid = (int)$_POST['user_id'];
    if ($uid === (int)$current_user_id) {
        $error = "You cannot delete your own account.";
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

$users    = $pdo->query("SELECT u.*, r.role_name, t.team_name, m.name as manager_name
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN teams t ON u.team_id = t.id
    LEFT JOIN users m ON u.manager_id = m.id
    ORDER BY u.created_at DESC")->fetchAll();

$roles    = getRoles($pdo);
$teams    = getTeams($pdo);
$managers = $pdo->query("SELECT id, name FROM users WHERE role_id IN (1,2,3) ORDER BY name")->fetchAll();

// Stats
$total   = count($users);
$active  = count(array_filter($users, fn($u) => $u['status'] === 'active'));
$inactive= $total - $active;
?>

<div class="page-header">
    <div>
        <h1>User Management</h1>
        <p>Add, edit, delete and manage all system users.</p>
    </div>
    <button class="btn btn-primary" onclick="openAddModal()">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>
        Add New User
    </button>
</div>

<?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
<?php if (isset($error)):   ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr); margin-bottom:2rem;">
    <div class="stat-card glass-card"><div class="stat-label">Total Users</div><div class="stat-value"><?php echo $total; ?></div></div>
    <div class="stat-card glass-card"><div class="stat-label">Active</div><div class="stat-value" style="color:var(--success);"><?php echo $active; ?></div></div>
    <div class="stat-card glass-card"><div class="stat-label">Inactive</div><div class="stat-value" style="color:var(--danger);"><?php echo $inactive; ?></div></div>
</div>

<div class="glass-card" style="padding:1.5rem;">
    <!-- Search -->
    <div style="margin-bottom:1.25rem;">
        <input type="text" id="user-search" onkeyup="filterUsers()" placeholder="Search by name, email or role..." class="form-control" style="max-width:360px;">
    </div>

    <div class="data-table-container">
        <table id="users-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Team</th>
                    <th>Reports To</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">No users found.</td></tr>
                <?php endif; ?>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=4f46e5&color=fff&size=64" style="width:34px; height:34px; border-radius:50%;">
                            <div>
                                <div style="font-weight:600;"><?php echo htmlspecialchars($user['name']); ?></div>
                                <div style="font-size:0.75rem; color:var(--text-muted);"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($user['role_name'] ?? '—'); ?></span></td>
                    <td style="font-size:0.875rem;"><?php echo htmlspecialchars($user['team_name'] ?? '—'); ?></td>
                    <td style="font-size:0.875rem;"><?php echo htmlspecialchars($user['manager_name'] ?? '—'); ?></td>
                    <td>
                        <?php
                        $sc = ['active'=>'success','inactive'=>'danger','suspended'=>'warning'];
                        echo '<span class="badge badge-'.($sc[$user['status']]??'muted').'">'.strtoupper($user['status']).'</span>';
                        ?>
                    </td>
                    <td style="font-size:0.8rem; color:var(--text-muted);"><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
                    <td>
                        <div style="display:flex; gap:6px; align-items:center;">
                            <!-- Edit -->
                            <button onclick='openEditModal(<?php echo htmlspecialchars(json_encode($user), ENT_QUOTES); ?>)'
                                title="Edit User"
                                style="background:none; border:1px solid var(--border); border-radius:7px; padding:5px 8px; cursor:pointer; color:var(--primary); transition:all 0.2s;"
                                onmouseover="this.style.background='rgba(79,70,229,0.1)'"
                                onmouseout="this.style.background='none'">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                            </button>

                            <?php if ($user['id'] != $current_user_id): ?>
                            <!-- Delete -->
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete user \'<?php echo htmlspecialchars(addslashes($user['name'])); ?>\'? This cannot be undone.')">
                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                <button type="submit" name="delete_user" value="1"
                                    title="Delete User"
                                    style="background:none; border:1px solid var(--border); border-radius:7px; padding:5px 8px; cursor:pointer; color:var(--danger); transition:all 0.2s;"
                                    onmouseover="this.style.background='rgba(239,68,68,0.1)'"
                                    onmouseout="this.style.background='none'">
                                    <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path><path d="M10 11v6M14 11v6"></path><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path></svg>
                                </button>
                            </form>
                            <?php else: ?>
                            <!-- Can't delete self -->
                            <button disabled title="Cannot delete your own account"
                                style="background:none; border:1px solid var(--border); border-radius:7px; padding:5px 8px; cursor:not-allowed; color:var(--text-muted); opacity:0.4;">
                                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"></path></svg>
                            </button>
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
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required></div>
            </div>
            <div class="form-group"><label class="form-label">Password *</label><input type="password" name="password" class="form-control" required placeholder="Min. 6 characters"></div>
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
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['team_name']); ?></option>
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
                <button type="button" class="btn glass-card" onclick="document.getElementById('add-user-modal').classList.remove('open')">Cancel</button>
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
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="user_id" id="edit-user-id">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="name" id="edit-name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Email *</label><input type="email" name="email" id="edit-email" class="form-control" required></div>
            </div>
            <div class="form-group"><label class="form-label">New Password <span style="color:var(--text-muted); font-weight:400;">(leave blank to keep current)</span></label><input type="password" name="password" class="form-control" placeholder="Leave blank to keep current"></div>
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
                            <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['team_name']); ?></option>
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
                <button type="button" class="btn glass-card" onclick="document.getElementById('edit-user-modal').classList.remove('open')">Cancel</button>
                <button type="submit" name="edit_user" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('add-user-modal').classList.add('open');
}

function openEditModal(user) {
    document.getElementById('edit-user-id').value  = user.id;
    document.getElementById('edit-name').value     = user.name;
    document.getElementById('edit-email').value    = user.email;
    document.getElementById('edit-role').value     = user.role_id;
    document.getElementById('edit-status').value   = user.status;
    document.getElementById('edit-team').value     = user.team_id || '';
    document.getElementById('edit-manager').value  = user.manager_id || '';
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
