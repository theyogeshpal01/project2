<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

// ---- Create Team ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_team'])) {
    $team_name   = trim($_POST['team_name']);
    $location    = trim($_POST['location']);
    $manager_id  = $_POST['manager_id'] ?: null;
    $project     = trim($_POST['project_name']);

    if (empty($team_name)) {
        $error = "Team name is required.";
    } else {
        try {
            $pdo->prepare("INSERT INTO teams (team_name, location, manager_id, project_name) VALUES (?,?,?,?)")
                ->execute([$team_name, $location, $manager_id, $project]);
            $success = "Team '$team_name' created successfully!";
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// ---- Delete Team ----
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $pdo->prepare("DELETE FROM teams WHERE id = ?")->execute([(int)$_GET['delete']]);
    $success = "Team deleted.";
}

$teams        = $pdo->query("SELECT t.*, u.name as manager_name FROM teams t LEFT JOIN users u ON t.manager_id = u.id ORDER BY t.created_at DESC")->fetchAll();
$all_managers = $pdo->query("SELECT id, name FROM users WHERE role_id IN (1,2) ORDER BY name ASC")->fetchAll();

$managers_count = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 2")->fetchColumn();
$leaders        = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 3")->fetchColumn();
$executives     = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 4")->fetchColumn();
$distributors   = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 5")->fetchColumn();
?>

<div class="page-header">
    <div>
        <h1>Team Management</h1>
        <p>Create teams, assign managers, and organize your field force.</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('add-team-modal').classList.add('open')">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg>
        Create New Team
    </button>
</div>

<?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
<?php if (isset($error)):   ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:2rem;">
    <div class="stat-card glass-card" style="text-align:center;">
        <div class="stat-label">Managers</div>
        <div class="stat-value"><?php echo $managers_count; ?></div>
    </div>
    <div class="stat-card glass-card" style="text-align:center;">
        <div class="stat-label">Team Leaders</div>
        <div class="stat-value"><?php echo $leaders; ?></div>
    </div>
    <div class="stat-card glass-card" style="text-align:center;">
        <div class="stat-label">Executives</div>
        <div class="stat-value"><?php echo $executives; ?></div>
    </div>
    <div class="stat-card glass-card" style="text-align:center;">
        <div class="stat-label">Total Teams</div>
        <div class="stat-value" style="color:var(--primary);"><?php echo count($teams); ?></div>
    </div>
</div>

<!-- Teams Table -->
<div class="glass-card" style="padding:1.5rem;">
    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>Team ID</th>
                    <th>Team Name</th>
                    <th>Location</th>
                    <th>Project / Campaign</th>
                    <th>Manager</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($teams)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">No teams yet. Create your first team!</td></tr>
                <?php endif; ?>
                <?php foreach ($teams as $team): ?>
                <tr>
                    <td><span style="font-family:monospace; color:var(--text-muted);">#TM-<?php echo str_pad($team['id'],4,'0',STR_PAD_LEFT); ?></span></td>
                    <td><strong style="color:var(--primary);"><?php echo htmlspecialchars($team['team_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($team['location'] ?: '—'); ?></td>
                    <td>
                        <?php if ($team['project_name']): ?>
                            <span class="badge badge-primary"><?php echo htmlspecialchars($team['project_name']); ?></span>
                        <?php else: ?>
                            <span style="color:var(--text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($team['manager_name']): ?>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($team['manager_name']); ?>&background=4f46e5&color=fff&size=32" style="width:26px; height:26px; border-radius:50%;">
                            <span style="font-size:0.875rem;"><?php echo htmlspecialchars($team['manager_name']); ?></span>
                        </div>
                        <?php else: ?>
                            <span style="color:var(--text-muted); font-size:0.875rem;">No manager</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.8rem; color:var(--text-muted);"><?php echo date('d M Y', strtotime($team['created_at'])); ?></td>
                    <td>
                        <div style="display:flex; gap:8px;">
                            <button onclick="editTeam(<?php echo htmlspecialchars(json_encode($team)); ?>)" class="btn glass-card" style="padding:5px 10px; font-size:0.75rem;">Edit</button>
                            <a href="?delete=<?php echo $team['id']; ?>" onclick="return confirm('Delete this team?')" class="btn" style="padding:5px 10px; font-size:0.75rem; background:var(--danger); color:white;">Delete</a>
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
    <div class="modal-box" style="width:560px;">
        <div class="modal-header">
            <h3 id="modal-title">Create New Team</h3>
            <button class="modal-close" onclick="document.getElementById('add-team-modal').classList.remove('open')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="team_id" id="team-id">
            <div class="form-group">
                <label class="form-label">Team Name *</label>
                <input type="text" name="team_name" id="f-team-name" class="form-control" required placeholder="e.g. Delhi North Team">
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Operating Location</label>
                    <input type="text" name="location" id="f-location" class="form-control" placeholder="City / State">
                </div>
                <div class="form-group">
                    <label class="form-label">Reporting Manager</label>
                    <select name="manager_id" id="f-manager" class="form-control">
                        <option value="">Select Manager</option>
                        <?php foreach ($all_managers as $m): ?>
                            <option value="<?php echo $m['id']; ?>"><?php echo htmlspecialchars($m['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Linked Project / Campaign</label>
                <input type="text" name="project_name" id="f-project" class="form-control" placeholder="e.g. QR Kit Deployment Phase 2">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:1rem;">
                <button type="button" class="btn glass-card" onclick="document.getElementById('add-team-modal').classList.remove('open')">Cancel</button>
                <button type="submit" name="add_team" class="btn btn-primary">Create Team</button>
            </div>
        </form>
    </div>
</div>

<script>
function editTeam(team) {
    document.getElementById('modal-title').innerText = 'Edit Team';
    document.getElementById('f-team-name').value = team.team_name || '';
    document.getElementById('f-location').value  = team.location || '';
    document.getElementById('f-project').value   = team.project_name || '';
    document.getElementById('f-manager').value   = team.manager_id || '';
    document.getElementById('add-team-modal').classList.add('open');
}
</script>

<?php include_once '../../includes/footer.php'; ?>
