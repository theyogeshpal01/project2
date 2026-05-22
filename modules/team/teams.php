<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

// Only Admin can access
if ($_SESSION['role_id'] != 1) {
    die("Unauthorized Access");
}
$company_id = $_SESSION['company_id'] ?? 1;

// Handle Add Team
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_team'])) {
    $team_name = trim($_POST['team_name']);
    $location = trim($_POST['location']);
    $project_name = trim($_POST['project_name']);
    $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;

    try {
        $pdo->prepare("INSERT INTO teams (company_id, team_name, location, project_name, manager_id) VALUES (?,?,?,?,?)")
            ->execute([$company_id, $team_name, $location, $project_name, $manager_id]);
        $success = "Team '$team_name' added successfully!";
    } catch (Exception $e) {
        $error = "Error adding team: " . $e->getMessage();
    }
}

// Handle Edit Team
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_team'])) {
    $tid = (int) $_POST['team_id'];
    $team_name = trim($_POST['team_name']);
    $location = trim($_POST['location']);
    $project_name = trim($_POST['project_name']);
    $manager_id = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;

    try {
        $pdo->prepare("UPDATE teams SET team_name=?, location=?, project_name=?, manager_id=? WHERE id=? AND company_id=?")
            ->execute([$team_name, $location, $project_name, $manager_id, $tid, $company_id]);
        $success = "Team updated successfully!";
    } catch (Exception $e) {
        $error = "Error updating team: " . $e->getMessage();
    }
}

// Handle Delete Team
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_team'])) {
    $tid = (int) $_POST['team_id'];
    try {
        // Unassign employees from this team
        $pdo->prepare("UPDATE users SET team_id = NULL WHERE team_id = ? AND company_id = ?")->execute([$tid, $company_id]);
        $pdo->prepare("DELETE FROM teams WHERE id = ? AND company_id = ?")->execute([$tid, $company_id]);
        $success = "Team deleted successfully.";
    } catch (Exception $e) {
        $error = "Error deleting team: " . $e->getMessage();
    }
}

// Fetch all managers to assign to teams (Admins, Supervisors, HR)
$managers = $pdo->query("SELECT id, name FROM users WHERE status='active' AND role_id IN (1,2,7) AND company_id = $company_id")->fetchAll();

// Fetch Teams
$teams = $pdo->query("
    SELECT t.*, u.name as manager_name, (SELECT COUNT(*) FROM users WHERE team_id = t.id AND company_id = $company_id) as emp_count 
    FROM teams t 
    LEFT JOIN users u ON t.manager_id = u.id 
    WHERE t.company_id = $company_id
    ORDER BY t.created_at DESC
")->fetchAll();
?>

<div class="page-header" style="align-items:flex-start;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-dark);">Teams Management</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Create and manage teams, assign managers, and organize employees.</p>
    </div>
    <div style="display:flex;gap:10px;">
        <button onclick="document.getElementById('add-team-modal').classList.add('open')" class="btn btn-primary">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg>
            Add Team
        </button>
    </div>
</div>

<?php if (isset($success)): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if (isset($error)): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="glass-card" style="padding:1.5rem;">
    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>Team ID</th>
                    <th>Team Name</th>
                    <th>Project</th>
                    <th>Location</th>
                    <th>Manager</th>
                    <th>Employees</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($teams)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:2rem; color:var(--text-muted);">No teams found.</td></tr>
                <?php endif; ?>
                <?php foreach($teams as $team): ?>
                <tr>
                    <td style="font-family:monospace; color:var(--text-muted);">TM-<?php echo str_pad($team['id'], 3, '0', STR_PAD_LEFT); ?></td>
                    <td style="font-weight:600;"><?php echo htmlspecialchars($team['team_name']); ?></td>
                    <td><?php echo htmlspecialchars($team['project_name'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($team['location'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($team['manager_name'] ?: 'Not Assigned'); ?></td>
                    <td><span class="badge badge-primary"><?php echo $team['emp_count']; ?></span></td>
                    <td>
                        <div style="display:flex; gap:6px; align-items:center;">
                            <a href="members.php?team_id=<?php echo $team['id']; ?>" class="btn glass-card" style="padding:4px 8px;font-size:0.75rem;color:var(--primary);font-weight:600;">Members</a>
                            <button class="btn btn-primary" style="padding:4px 8px;font-size:0.75rem;" 
                                data-team="<?php echo htmlspecialchars(json_encode($team), ENT_QUOTES); ?>" 
                                onclick="openEditTeamModal(this)">Edit</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this team? Employees in this team will be unassigned.')">
                                <input type="hidden" name="team_id" value="<?php echo $team['id']; ?>">
                                <button type="submit" name="delete_team" value="1" class="btn btn-danger" style="padding:4px 8px;font-size:0.75rem;">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Team Modal -->
<div class="modal-overlay" id="add-team-modal">
    <div class="modal-box" style="width:500px;">
        <div class="modal-header">
            <h3>Add New Team</h3>
            <button type="button" class="modal-close" onclick="document.getElementById('add-team-modal').classList.remove('open')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="add_team" value="1">
            <div class="form-group">
                <label class="form-label">Team Name *</label>
                <input type="text" name="team_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Project Name</label>
                <input type="text" name="project_name" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Location</label>
                <input type="text" name="location" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Assign Manager</label>
                <select name="manager_id" class="form-control">
                    <option value="">-- No Manager --</option>
                    <?php foreach($managers as $m): ?>
                        <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:1.5rem;">
                <button type="button" class="btn glass-card" onclick="document.getElementById('add-team-modal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Team</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Team Modal -->
<div class="modal-overlay" id="edit-team-modal">
    <div class="modal-box" style="width:500px;">
        <div class="modal-header">
            <h3>Edit Team</h3>
            <button type="button" class="modal-close" onclick="document.getElementById('edit-team-modal').classList.remove('open')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="edit_team" value="1">
            <input type="hidden" name="team_id" id="edit_team_id">
            <div class="form-group">
                <label class="form-label">Team Name *</label>
                <input type="text" name="team_name" id="edit_team_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Project Name</label>
                <input type="text" name="project_name" id="edit_project_name" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Location</label>
                <input type="text" name="location" id="edit_location" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Assign Manager</label>
                <select name="manager_id" id="edit_manager_id" class="form-control">
                    <option value="">-- No Manager --</option>
                    <?php foreach($managers as $m): ?>
                        <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:1.5rem;">
                <button type="button" class="btn glass-card" onclick="document.getElementById('edit-team-modal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Team</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditTeamModal(btn) {
    const team = JSON.parse(btn.getAttribute('data-team'));
    document.getElementById('edit_team_id').value = team.id;
    document.getElementById('edit_team_name').value = team.team_name;
    document.getElementById('edit_project_name').value = team.project_name || '';
    document.getElementById('edit_location').value = team.location || '';
    document.getElementById('edit_manager_id').value = team.manager_id || '';
    document.getElementById('edit-team-modal').classList.add('open');
}
</script>

<?php include_once '../../includes/footer.php'; ?>
