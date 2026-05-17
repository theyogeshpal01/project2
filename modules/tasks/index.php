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
?>

<div class="page-header">
    <div><h1>Task Management</h1><p>Assign and track tasks across your team.</p></div>
    <?php if (in_array($role_id, [1,2,3])): ?>
        <button class="btn btn-primary" onclick="document.getElementById('task-modal').classList.add('open')">+ Assign Task</button>
    <?php endif; ?>
</div>

<?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

<!-- Filter tabs -->
<div style="display:flex; gap:8px; margin-bottom:1.5rem; flex-wrap:wrap;">
    <?php foreach (['all'=>'All','pending'=>'Pending','in_progress'=>'In Progress','completed'=>'Completed'] as $k=>$v): ?>
        <a href="?filter=<?php echo $k; ?>" class="btn <?php echo ($_GET['filter']??'all')===$k?'btn-primary':'glass-card'; ?>" style="padding:6px 16px; font-size:0.8rem;"><?php echo $v; ?></a>
    <?php endforeach; ?>
</div>

<div class="glass-card" style="padding:1.5rem;">
    <div class="data-table-container">
        <table>
            <thead><tr><th>Task</th><th>Assigned To</th><th>By</th><th>Due Date</th><th>Priority</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php
                $filter = $_GET['filter'] ?? 'all';
                $filtered = $filter === 'all' ? $tasks : array_filter($tasks, fn($t) => $t['status'] === $filter);
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
