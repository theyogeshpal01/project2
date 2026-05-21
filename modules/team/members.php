<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

if (!isset($_GET['team_id']) || empty($_GET['team_id'])) {
    die("Invalid Team ID.");
}

$team_id = (int)$_GET['team_id'];
$current_user_id = $_SESSION['user_id'];
$current_role_id = $_SESSION['role_id'];

// Fetch team info
$team = $pdo->prepare("SELECT t.*, u.name as manager_name FROM teams t LEFT JOIN users u ON t.manager_id = u.id WHERE t.id = ?");
$team->execute([$team_id]);
$team = $team->fetch();

if (!$team) {
    die("Team not found.");
}

// Access Control: Only Admin or the Team's Manager can manage members
if ($current_role_id != 1 && $team['manager_id'] != $current_user_id) {
    die("Unauthorized Access: You can only manage your own teams.");
}

// Handle Add Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_member'])) {
    $user_id = (int)$_POST['user_id'];
    try {
        $pdo->prepare("UPDATE users SET team_id = ? WHERE id = ?")->execute([$team_id, $user_id]);
        $success = "Employee successfully added to the team.";
    } catch (Exception $e) {
        $error = "Error adding member: " . $e->getMessage();
    }
}

// Handle Remove Member
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_member'])) {
    $user_id = (int)$_POST['user_id'];
    try {
        $pdo->prepare("UPDATE users SET team_id = NULL WHERE id = ?")->execute([$user_id]);
        $success = "Employee successfully removed from the team.";
    } catch (Exception $e) {
        $error = "Error removing member: " . $e->getMessage();
    }
}

// Fetch current members
$members = $pdo->prepare("SELECT id, name, email, phone, role_id, status FROM users WHERE team_id = ? ORDER BY name ASC");
$members->execute([$team_id]);
$members = $members->fetchAll();

// Fetch employees not in this team to show in dropdown
$available_users = $pdo->prepare("SELECT id, name FROM users WHERE status='active' AND role_id NOT IN (1) AND (team_id IS NULL OR team_id != ?) ORDER BY name ASC");
$available_users->execute([$team_id]);
$available_users = $available_users->fetchAll();
?>

<div class="page-header" style="align-items:flex-start;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-dark);">Manage Team: <?php echo htmlspecialchars($team['team_name']); ?></h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Manager: <?php echo htmlspecialchars($team['manager_name'] ?: 'Unassigned'); ?> | Location: <?php echo htmlspecialchars($team['location'] ?: '—'); ?></p>
    </div>
    <div style="display:flex;gap:10px;">
        <a href="<?php echo ($current_role_id == 1) ? 'teams.php' : 'manage.php'; ?>" class="btn glass-card">
            &larr; Back to Teams
        </a>
    </div>
</div>

<?php if (isset($success)): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if (isset($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div style="display:grid; grid-template-columns:1fr 2fr; gap:2rem; margin-top:2rem;">
    <!-- Add Member Form -->
    <div class="glass-card" style="padding:1.5rem; height:fit-content;">
        <h3 style="margin-bottom:1rem; font-size:1.1rem;">Add Employee to Team</h3>
        <form method="POST">
            <input type="hidden" name="add_member" value="1">
            <div class="form-group">
                <label class="form-label">Select Employee</label>
                <select name="user_id" class="form-control" required>
                    <option value="">-- Choose Employee --</option>
                    <?php foreach($available_users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center;">Add to Team</button>
        </form>
    </div>

    <!-- Current Members List -->
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="margin-bottom:1rem; font-size:1.1rem;">Current Team Members (<?php echo count($members); ?>)</h3>
        <div class="data-table-container">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($members)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:2rem; color:var(--text-muted);">No members assigned to this team yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach($members as $m): ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($m['name']); ?>&background=random&color=fff&size=32" style="width:28px; height:28px; border-radius:50%;">
                                <div style="font-weight:600;"><?php echo htmlspecialchars($m['name']); ?></div>
                            </div>
                        </td>
                        <td style="font-size:0.875rem;"><?php echo htmlspecialchars($m['email']); ?></td>
                        <td style="font-size:0.875rem;"><?php echo htmlspecialchars($m['phone'] ?: '—'); ?></td>
                        <td>
                            <?php
                            $sc = ['active'=>'success', 'inactive'=>'danger', 'suspended'=>'warning'];
                            $cls = $sc[$m['status']] ?? 'muted';
                            ?>
                            <span class="badge badge-<?php echo $cls; ?>"><?php echo ucfirst($m['status']); ?></span>
                        </td>
                        <td>
                            <form method="POST" onsubmit="return confirm('Remove this employee from the team?')">
                                <input type="hidden" name="remove_member" value="1">
                                <input type="hidden" name="user_id" value="<?php echo $m['id']; ?>">
                                <button type="submit" class="btn btn-danger" style="padding:4px 8px; font-size:0.75rem;">Remove</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
