<?php 
include_once '../../includes/header.php'; 
include_once '../../core/functions.php';

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role_id = $_POST['role_id'];
    $team_id = $_POST['team_id'] ?: null;
    $manager_id = $_POST['manager_id'] ?: null;

    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role_id, team_id, manager_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $password, $role_id, $team_id, $manager_id]);
        $success = "User added successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
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
$managers = $pdo->query("SELECT id, name FROM users WHERE role_id IN (1, 2, 3)")->fetchAll();
?>

<div class="header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem;">User Management</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">Manage your team members, roles, and reporting hierarchy.</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('add-user-modal').style.display='flex'">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>
        Add New User
    </button>
</div>

<?php if (isset($success)): ?>
    <div class="glass-card" style="padding: 1rem; background: rgba(16, 185, 129, 0.2); color: var(--success); margin-bottom: 1.5rem; border-color: var(--success);">
        <?php echo $success; ?>
    </div>
<?php endif; ?>

<div class="glass-card" style="padding: 1.5rem;">
    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Team</th>
                    <th>Reports To</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $user): ?>
                <tr>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['name']); ?>&background=random" style="width: 32px; height: 32px; border-radius: 50%;">
                            <div>
                                <div style="font-weight: 600;"><?php echo $user['name']; ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $user['email']; ?></div>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge" style="background: rgba(99, 102, 241, 0.1); color: var(--primary);"><?php echo $user['role_name']; ?></span></td>
                    <td><?php echo $user['team_name'] ?: '<span style="color:var(--text-muted)">N/A</span>'; ?></td>
                    <td><?php echo $user['manager_name'] ?: '<span style="color:var(--text-muted)">N/A</span>'; ?></td>
                    <td><?php echo getStatusBadge($user['status']); ?></td>
                    <td>
                        <div style="display: flex; gap: 10px;">
                            <button title="Edit" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></button>
                            <button title="View Performance" style="background:none; border:none; color:var(--primary); cursor:pointer;"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div id="add-user-modal" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center;">
    <div class="glass-card" style="width: 600px; padding: 2rem;">
        <h2 style="margin-bottom: 1.5rem;">Add New User</h2>
        <form method="POST">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Full Name</label>
                    <input type="text" name="name" required style="width: 100%; padding: 0.75rem; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: var(--text-main);">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Email Address</label>
                    <input type="email" name="email" required style="width: 100%; padding: 0.75rem; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: white;">
                </div>
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Password</label>
                <input type="password" name="password" required style="width: 100%; padding: 0.75rem; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: white;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Role</label>
                    <select name="role_id" required style="width: 100%; padding: 0.75rem; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: white;">
                        <?php foreach($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>"><?php echo $role['role_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Team (Optional)</label>
                    <select name="team_id" style="width: 100%; padding: 0.75rem; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: white;">
                        <option value="">Select Team</option>
                        <?php foreach($teams as $team): ?>
                            <option value="<?php echo $team['id']; ?>"><?php echo $team['team_name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 2rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Reporting Manager</label>
                <select name="manager_id" style="width: 100%; padding: 0.75rem; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: white;">
                    <option value="">Select Manager</option>
                    <?php foreach($managers as $m): ?>
                        <option value="<?php echo $m['id']; ?>"><?php echo $m['name']; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" class="btn" style="background: var(--bg-card);" onclick="document.getElementById('add-user-modal').style.display='none'">Cancel</button>
                <button type="submit" name="add_user" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
