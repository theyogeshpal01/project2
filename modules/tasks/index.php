<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

$user_id = $_SESSION['user_id'];
$role_id = $_SESSION['role_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    $pdo->prepare("INSERT INTO tasks (title, description, assigned_to, assigned_by, due_date, priority) VALUES (?,?,?,?,?,?)")
        ->execute([
            trim($_POST['title']),
            trim($_POST['description']),
            (int)$_POST['assigned_to'],
            $user_id,
            $_POST['due_date'],
            $_POST['priority']
        ]);
    // Notify assigned user
    $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,'info')")
        ->execute([(int)$_POST['assigned_to'], 'New Task Assigned', "You have a new task: " . trim($_POST['title'])]);
    $success = "Task assigned!";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task_status'])) {
    $pdo->prepare("UPDATE tasks SET status=? WHERE id=? AND (assigned_to=? OR assigned_by=?)")
        ->execute([$_POST['status'], (int)$_POST['task_id'], $user_id, $user_id]);
    $success = "Task updated!";
}

// Show tasks relevant to user
try {
    if ($role_id == 1 || $role_id == 2) {
        $tasks = $pdo->query("SELECT t.*, u.name as assigned_to_name, ab.name as assigned_by_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id LEFT JOIN users ab ON t.assigned_by = ab.id ORDER BY t.created_at DESC")->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT t.*, u.name as assigned_to_name, ab.name as assigned_by_name FROM tasks t LEFT JOIN users u ON t.assigned_to = u.id LEFT JOIN users ab ON t.assigned_by = ab.id WHERE t.assigned_to = ? OR t.assigned_by = ? ORDER BY t.created_at DESC");
        $stmt->execute([$user_id, $user_id]);
        $tasks = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $tasks = [];
    $error = "Tasks table not ready. Please run: <a href='" . BASE_URL . "core/init_db.php'>Initialize Database</a>";
}

$assignable_users = $pdo->query("SELECT id, name, role_id FROM users WHERE status='active' ORDER BY name")->fetchAll();

$total_tasks = count($tasks);
$my_tasks = count(array_filter($tasks, fn($t) => $t['assigned_to'] == $user_id));
$overdue = count(array_filter($tasks, fn($t) => $t['due_date'] && strtotime($t['due_date']) < time() && $t['status'] != 'completed'));
$urgent = count(array_filter($tasks, fn($t) => $t['priority'] == 'urgent' && $t['status'] != 'completed'));

$tab = $_GET['tab'] ?? 'dashboard';
?>

<div class="page-header" style="align-items:flex-start;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-dark);">Task Management</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Manage and track team tasks and progress.</p>
    </div>
    <?php if (in_array($role_id, [1,2,3])): ?>
        <button class="btn btn-primary" onclick="document.getElementById('task-modal').classList.add('open')">+ Assign Task</button>
    <?php endif; ?>
</div>

<?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

<div class="glass-card" style="padding:1.5rem;margin-bottom:1.5rem;">
    <!-- Tabs -->
    <div style="display:flex;gap:10px;border-bottom:1px solid var(--border);padding-bottom:10px;">
        <a href="?tab=dashboard" class="btn <?php echo $tab === 'dashboard' ? 'btn-primary' : ''; ?>" style="<?php echo $tab === 'dashboard' ? '' : 'background:transparent;color:var(--text-muted);border:none;'; ?>">Dashboard</a>
        <a href="?tab=all_tasks" class="btn <?php echo $tab === 'all_tasks' ? 'btn-primary' : ''; ?>" style="<?php echo $tab === 'all_tasks' ? '' : 'background:transparent;color:var(--text-muted);border:none;'; ?>">All Tasks</a>
        <a href="?tab=my_tasks" class="btn <?php echo $tab === 'my_tasks' ? 'btn-primary' : ''; ?>" style="<?php echo $tab === 'my_tasks' ? '' : 'background:transparent;color:var(--text-muted);border:none;'; ?>">My Tasks</a>
    </div>
</div>

<?php if ($tab === 'dashboard'): ?>
<!-- Dashboard View -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:1.5rem;">
    <div class="stat-card glass-card" style="padding:1.5rem;text-align:center;">
        <div style="font-size:2rem;font-weight:700;color:var(--primary);"><?php echo $total_tasks; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Total Tasks</div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;text-align:center;">
        <div style="font-size:2rem;font-weight:700;color:var(--success);"><?php echo $my_tasks; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">My Tasks</div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;text-align:center;">
        <div style="font-size:2rem;font-weight:700;color:var(--warning);"><?php echo $overdue; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Overdue</div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;text-align:center;">
        <div style="font-size:2rem;font-weight:700;color:var(--danger);"><?php echo $urgent; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Urgent</div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem;">
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="font-size:1rem;font-weight:600;margin-bottom:1rem;">Status Distribution</h3>
        <div>
            <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                <span style="font-size:0.875rem;font-weight:500;">Assigned</span>
                <span style="font-size:0.875rem;color:var(--text-muted);">1/1</span>
            </div>
            <div style="width:100%;height:8px;background:var(--border);border-radius:4px;">
                <div style="width:100%;height:8px;background:var(--primary);border-radius:4px;"></div>
            </div>
        </div>
    </div>
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="font-size:1rem;font-weight:600;margin-bottom:1rem;">Priority Distribution</h3>
        <div>
            <div style="display:flex;justify-content:space-between;margin-bottom:5px;">
                <span style="font-size:0.875rem;font-weight:500;">Medium</span>
                <span style="font-size:0.875rem;color:var(--text-muted);">1/1</span>
            </div>
            <div style="width:100%;height:8px;background:var(--border);border-radius:4px;">
                <div style="width:100%;height:8px;background:var(--warning);border-radius:4px;"></div>
            </div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.5rem;">
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="font-size:1rem;font-weight:600;color:var(--danger);margin-bottom:1rem;display:flex;align-items:center;gap:8px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
            Overdue Tasks <?php echo $overdue; ?>
        </h3>
        <p style="font-size:0.875rem;color:var(--text-muted);">No overdue tasks found.</p>
    </div>
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="font-size:1rem;font-weight:600;color:#ea580c;margin-bottom:1rem;display:flex;align-items:center;gap:8px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            Urgent Tasks <?php echo $urgent; ?>
        </h3>
        <p style="font-size:0.875rem;color:var(--text-muted);">No urgent tasks found.</p>
    </div>
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="font-size:1rem;font-weight:600;color:#0284c7;margin-bottom:1rem;display:flex;align-items:center;gap:8px;">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
            Approaching 0
        </h3>
        <p style="font-size:0.875rem;color:var(--text-muted);">No approaching tasks found.</p>
    </div>
</div>

<?php else: ?>
<!-- All Tasks & My Tasks View -->
<div class="glass-card" style="padding:1.5rem;">
    <!-- Filter tabs inside view -->
    <div style="display:flex; gap:8px; margin-bottom:1.5rem; flex-wrap:wrap;">
        <?php foreach (['all'=>'All','pending'=>'Pending','in_progress'=>'In Progress','completed'=>'Completed'] as $k=>$v): ?>
            <a href="?tab=<?php echo $tab; ?>&filter=<?php echo $k; ?>" class="btn <?php echo ($_GET['filter']??'all')===$k?'btn-primary':'glass-card'; ?>" style="padding:6px 16px; font-size:0.8rem;"><?php echo $v; ?></a>
        <?php endforeach; ?>
    </div>

    <div class="data-table-container">
        <table>
            <thead><tr><th>Task</th><th>Assigned To</th><th>By</th><th>Due Date</th><th>Priority</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php
                $filter = $_GET['filter'] ?? 'all';
                $view_tasks = $tab === 'my_tasks' ? array_filter($tasks, fn($t) => $t['assigned_to'] == $user_id) : $tasks;
                $filtered = $filter === 'all' ? $view_tasks : array_filter($view_tasks, fn($t) => $t['status'] === $filter);
                if (empty($filtered)):
                ?>
                    <tr><td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">No tasks found.</td></tr>
                <?php endif; ?>
                <?php foreach ($filtered as $task):
                    $pc = ['low'=>'muted','medium'=>'primary','high'=>'warning','urgent'=>'danger'];
                    $sc = ['pending'=>'warning','in_progress'=>'primary','completed'=>'success','cancelled'=>'danger'];
                ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($task['title']); ?></strong><br><span style="font-size:0.75rem; color:var(--text-muted);"><?php echo htmlspecialchars(substr($task['description'],0,60)); ?></span></td>
                    <td style="font-size:0.875rem;"><?php echo htmlspecialchars($task['assigned_to_name'] ?? '—'); ?></td>
                    <td style="font-size:0.8rem; color:var(--text-muted);"><?php echo htmlspecialchars($task['assigned_by_name'] ?? '—'); ?></td>
                    <td style="font-size:0.8rem;"><?php echo $task['due_date'] ? date('d M Y', strtotime($task['due_date'])) : '—'; ?></td>
                    <td><span class="badge badge-<?php echo $pc[$task['priority']]??'muted'; ?>"><?php echo strtoupper($task['priority']); ?></span></td>
                    <td><span class="badge badge-<?php echo $sc[$task['status']]??'muted'; ?>"><?php echo str_replace('_',' ',strtoupper($task['status'])); ?></span></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                            <select name="status" onchange="this.form.submit()" class="form-control" style="padding:4px 8px; font-size:0.75rem; width:auto;">
                                <?php foreach (['pending','in_progress','completed','cancelled'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $task['status']===$s?'selected':''; ?>><?php echo ucfirst(str_replace('_',' ',$s)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="update_task_status" value="1">
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="modal-overlay" id="task-modal">
    <div class="modal-box" style="width:550px;">
        <div class="modal-header">
            <h3>Assign New Task</h3>
            <button class="modal-close" onclick="document.getElementById('task-modal').classList.remove('open')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST">
            <div class="form-group"><label class="form-label">Task Title</label><input type="text" name="title" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Assign To</label>
                    <select name="assigned_to" class="form-control" required>
                        <option value="">Select User</option>
                        <?php foreach ($assignable_users as $u): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-control">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>
            <div class="form-group"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control"></div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:1rem;">
                <button type="button" class="btn glass-card" onclick="document.getElementById('task-modal').classList.remove('open')">Cancel</button>
                <button type="submit" name="add_task" class="btn btn-primary">Assign Task</button>
            </div>
        </form>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
