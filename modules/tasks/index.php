<?php
include_once '../../includes/header.php';

$user_id = $_SESSION['user_id'];
$role_id  = $_SESSION['role_id'];
$is_admin = in_array($role_id, [1, 2]);

/* ──────────────────────────────────────────────
   POST HANDLERS
────────────────────────────────────────────── */

// ADD TASK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_task') {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO tasks (title, description, assigned_to, assigned_by, due_date, priority, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())"
        );
        $stmt->execute([
            trim($_POST['title']),
            trim($_POST['description']),
            (int)$_POST['assigned_to'],
            $user_id,
            $_POST['due_date'] ?: null,
            $_POST['priority'],
        ]);
        // Notify assigned user
        try {
            $pdo->prepare(
                "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'info')"
            )->execute([
                (int)$_POST['assigned_to'],
                'New Task Assigned',
                'You have been assigned a new task: ' . trim($_POST['title']),
            ]);
        } catch (Exception $e) { /* notifications table may not exist */ }
        $success = 'Task created successfully!';
    } catch (Exception $e) {
        $error = 'Error creating task: ' . $e->getMessage();
    }
}

// EDIT TASK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_task') {
    try {
        $task_id = (int)$_POST['task_id'];
        // Admins can edit any task; others only tasks they created
        $where   = $is_admin ? 'id = ?' : 'id = ? AND assigned_by = ?';
        $params  = $is_admin ? [$_POST['title'], trim($_POST['description']), (int)$_POST['assigned_to'],
                                $_POST['due_date'] ?: null, $_POST['priority'], $_POST['status'], $task_id]
                             : [$_POST['title'], trim($_POST['description']), (int)$_POST['assigned_to'],
                                $_POST['due_date'] ?: null, $_POST['priority'], $_POST['status'], $task_id, $user_id];
        $pdo->prepare(
            "UPDATE tasks SET title=?, description=?, assigned_to=?, due_date=?, priority=?, status=? WHERE $where"
        )->execute($params);
        $success = 'Task updated successfully!';
    } catch (Exception $e) {
        $error = 'Error updating task: ' . $e->getMessage();
    }
}

// DELETE TASK
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_task') {
    try {
        $task_id = (int)$_POST['task_id'];
        $where   = $is_admin ? 'id = ?' : 'id = ? AND assigned_by = ?';
        $params  = $is_admin ? [$task_id] : [$task_id, $user_id];
        $pdo->prepare("DELETE FROM tasks WHERE $where")->execute($params);
        $success = 'Task deleted successfully!';
    } catch (Exception $e) {
        $error = 'Error deleting task: ' . $e->getMessage();
    }
}

// QUICK STATUS UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    try {
        $allowed_statuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        $new_status = in_array($_POST['status'], $allowed_statuses) ? $_POST['status'] : 'pending';
        $task_id    = (int)$_POST['task_id'];
        $pdo->prepare(
            "UPDATE tasks SET status=? WHERE id=? AND (assigned_to=? OR assigned_by=?)"
        )->execute([$new_status, $task_id, $user_id, $user_id]);
        $success = 'Task status updated!';
    } catch (Exception $e) {
        $error = 'Error updating status: ' . $e->getMessage();
    }
}

/* ──────────────────────────────────────────────
   FETCH DATA
────────────────────────────────────────────── */
$tasks = [];
try {
    $base_sql = "SELECT t.*, u.name AS assigned_to_name, ab.name AS assigned_by_name
                 FROM tasks t
                 LEFT JOIN users u  ON t.assigned_to = u.id
                 LEFT JOIN users ab ON t.assigned_by  = ab.id";
    if ($is_admin) {
        $tasks = $pdo->query($base_sql . " ORDER BY t.created_at DESC")->fetchAll();
    } else {
        $stmt = $pdo->prepare($base_sql . " WHERE t.assigned_to = ? OR t.assigned_by = ? ORDER BY t.created_at DESC");
        $stmt->execute([$user_id, $user_id]);
        $tasks = $stmt->fetchAll();
    }
} catch (Exception $e) {
    $error = ($error ?? '') . ' Tasks table not ready.';
}

// Users list for dropdowns
$assignable_users = [];
try {
    $assignable_users = $pdo->query("SELECT id, name FROM users WHERE status='active' ORDER BY name")->fetchAll();
} catch (Exception $e) {}

/* ──────────────────────────────────────────────
   STATS (from $tasks array for efficiency)
────────────────────────────────────────────── */
$stat_total     = count($tasks);
$stat_pending   = count(array_filter($tasks, fn($t) => $t['status'] === 'pending'));
$stat_progress  = count(array_filter($tasks, fn($t) => $t['status'] === 'in_progress'));
$stat_completed = count(array_filter($tasks, fn($t) => $t['status'] === 'completed'));

/* ──────────────────────────────────────────────
   FILTERS  (GET params)
────────────────────────────────────────────── */
$filter_status   = $_GET['status']   ?? 'all';
$filter_priority = $_GET['priority'] ?? 'all';
?>

<!-- ── Page Header ── -->
<div class="page-header">
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-dark);">Task Management</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Manage and track team tasks and progress.</p>
    </div>
    <?php if ($is_admin || $role_id == 3): ?>
    <button class="btn btn-primary" onclick="openAddModal()">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:6px;vertical-align:middle;"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Add Task
    </button>
    <?php endif; ?>
</div>

<!-- ── Alerts ── -->
<?php if (isset($success)): ?>
<div class="alert alert-success" id="alert-msg"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
<div class="alert alert-danger" id="alert-msg"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- ── Stats ── -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.5rem;">
    <div class="stat-card glass-card" style="padding:1.5rem;text-align:center;">
        <div style="font-size:2rem;font-weight:700;color:var(--primary);"><?php echo $stat_total; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Total Tasks</div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;text-align:center;">
        <div style="font-size:2rem;font-weight:700;color:var(--warning);"><?php echo $stat_pending; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Pending</div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;text-align:center;">
        <div style="font-size:2rem;font-weight:700;color:#0284c7;"><?php echo $stat_progress; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">In Progress</div>
    </div>
    <div class="stat-card glass-card" style="padding:1.5rem;text-align:center;">
        <div style="font-size:2rem;font-weight:700;color:var(--success);"><?php echo $stat_completed; ?></div>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:5px;">Completed</div>
    </div>
</div>

<!-- ── Filter Bar ── -->
<div class="glass-card" style="padding:1.25rem 1.5rem;margin-bottom:1.5rem;">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
        <div class="form-group" style="margin:0;min-width:160px;">
            <label class="form-label" style="margin-bottom:4px;">Status</label>
            <select name="status" class="form-control" style="padding:8px 12px;">
                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                <option value="pending"     <?php echo $filter_status === 'pending'     ? 'selected' : ''; ?>>Pending</option>
                <option value="in_progress" <?php echo $filter_status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="completed"   <?php echo $filter_status === 'completed'   ? 'selected' : ''; ?>>Completed</option>
                <option value="cancelled"   <?php echo $filter_status === 'cancelled'   ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
        <div class="form-group" style="margin:0;min-width:160px;">
            <label class="form-label" style="margin-bottom:4px;">Priority</label>
            <select name="priority" class="form-control" style="padding:8px 12px;">
                <option value="all" <?php echo $filter_priority === 'all' ? 'selected' : ''; ?>>All Priorities</option>
                <option value="low"    <?php echo $filter_priority === 'low'    ? 'selected' : ''; ?>>Low</option>
                <option value="medium" <?php echo $filter_priority === 'medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="high"   <?php echo $filter_priority === 'high'   ? 'selected' : ''; ?>>High</option>
                <option value="urgent" <?php echo $filter_priority === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary" style="padding:8px 20px;">Filter</button>
        <a href="?" class="btn glass-card" style="padding:8px 20px;color:var(--text-muted);">Reset</a>
    </form>
</div>

<!-- ── Tasks Table ── -->
<div class="glass-card" style="padding:1.5rem;">
    <div class="data-table-container">
        <table id="tasksTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Task</th>
                    <th>Assigned To</th>
                    <th>Assigned By</th>
                    <th>Due Date</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $priority_colors = ['low' => 'badge-accent', 'medium' => 'badge-warning', 'high' => 'badge-danger', 'urgent' => 'badge-danger'];
            $status_colors   = ['pending' => 'badge-warning', 'in_progress' => 'badge-accent', 'completed' => 'badge-success', 'cancelled' => 'badge-danger'];

            // Apply filters
            $filtered_tasks = array_filter($tasks, function($t) use ($filter_status, $filter_priority) {
                if ($filter_status   !== 'all' && $t['status']   !== $filter_status)   return false;
                if ($filter_priority !== 'all' && $t['priority'] !== $filter_priority) return false;
                return true;
            });

            if (empty($filtered_tasks)):
            ?>
                <tr><td colspan="8" style="text-align:center;padding:3rem;color:var(--text-muted);">No tasks found.</td></tr>
            <?php
            else:
                $i = 1;
                foreach ($filtered_tasks as $task):
                    $is_overdue = $task['due_date'] && strtotime($task['due_date']) < time() && $task['status'] !== 'completed' && $task['status'] !== 'cancelled';
                    // Encode task data for edit modal
                    $task_json = htmlspecialchars(json_encode([
                        'id'          => $task['id'],
                        'title'       => $task['title'],
                        'description' => $task['description'],
                        'assigned_to' => $task['assigned_to'],
                        'due_date'    => $task['due_date'],
                        'priority'    => $task['priority'],
                        'status'      => $task['status'],
                    ]), ENT_QUOTES);
                    $can_edit   = $is_admin || $task['assigned_by'] == $user_id;
                    $can_status = $task['assigned_to'] == $user_id || $task['assigned_by'] == $user_id || $is_admin;
            ?>
                <tr>
                    <td style="color:var(--text-muted);font-size:0.85rem;"><?php echo $i++; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($task['title']); ?></strong>
                        <?php if ($task['description']): ?>
                        <br><span style="font-size:0.75rem;color:var(--text-muted);"><?php echo htmlspecialchars(mb_substr($task['description'], 0, 70)) . (mb_strlen($task['description']) > 70 ? '…' : ''); ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.875rem;"><?php echo htmlspecialchars($task['assigned_to_name'] ?? '—'); ?></td>
                    <td style="font-size:0.8rem;color:var(--text-muted);"><?php echo htmlspecialchars($task['assigned_by_name'] ?? '—'); ?></td>
                    <td style="font-size:0.8rem;<?php echo $is_overdue ? 'color:var(--danger);font-weight:600;' : ''; ?>">
                        <?php echo $task['due_date'] ? date('d M Y', strtotime($task['due_date'])) : '—'; ?>
                        <?php if ($is_overdue): ?><br><span style="font-size:0.7rem;">⚠ Overdue</span><?php endif; ?>
                    </td>
                    <td><span class="badge <?php echo $priority_colors[$task['priority']] ?? 'badge-accent'; ?>"><?php echo strtoupper($task['priority']); ?></span></td>
                    <td><span class="badge <?php echo $status_colors[$task['status']] ?? 'badge-warning'; ?>"><?php echo str_replace('_', ' ', strtoupper($task['status'])); ?></span></td>
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                            <!-- Inline status quick-actions -->
                            <?php if ($can_status && $task['status'] === 'pending'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action"  value="update_status">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <input type="hidden" name="status"  value="in_progress">
                                <button type="submit" class="btn btn-warning" style="padding:4px 10px;font-size:0.75rem;">▶ Start</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($can_status && in_array($task['status'], ['pending','in_progress'])): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action"  value="update_status">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <input type="hidden" name="status"  value="completed">
                                <button type="submit" class="btn btn-success" style="padding:4px 10px;font-size:0.75rem;">✓ Complete</button>
                            </form>
                            <?php endif; ?>
                            <!-- Edit -->
                            <?php if ($can_edit): ?>
                            <button class="btn btn-primary" style="padding:4px 10px;font-size:0.75rem;"
                                    onclick="openEditModal(<?php echo $task_json; ?>)">Edit</button>
                            <?php endif; ?>
                            <!-- Delete -->
                            <?php if ($can_edit): ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this task? This action cannot be undone.');">
                                <input type="hidden" name="action"  value="delete_task">
                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                <button type="submit" class="btn btn-danger" style="padding:4px 10px;font-size:0.75rem;">Delete</button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php
                endforeach;
            endif;
            ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ════════════════════════════════════════════
     ADD TASK MODAL
════════════════════════════════════════════ -->
<div class="modal-overlay" id="add-task-modal">
    <div class="modal-box" style="width:580px;max-width:95vw;">
        <div class="modal-header">
            <h3>Add New Task</h3>
            <button class="modal-close" onclick="closeModal('add-task-modal')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" id="add-task-form">
            <input type="hidden" name="action" value="add_task">
            <div class="form-group">
                <label class="form-label">Task Title <span style="color:var(--danger);">*</span></label>
                <input type="text" name="title" class="form-control" placeholder="Enter task title" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3" placeholder="Describe the task…"></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Assign To <span style="color:var(--danger);">*</span></label>
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
            <div class="form-group">
                <label class="form-label">Due Date</label>
                <input type="date" name="due_date" class="form-control">
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:1.25rem;">
                <button type="button" class="btn glass-card" onclick="closeModal('add-task-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Task</button>
            </div>
        </form>
    </div>
</div>

<!-- ════════════════════════════════════════════
     EDIT TASK MODAL
════════════════════════════════════════════ -->
<div class="modal-overlay" id="edit-task-modal">
    <div class="modal-box" style="width:580px;max-width:95vw;">
        <div class="modal-header">
            <h3>Edit Task</h3>
            <button class="modal-close" onclick="closeModal('edit-task-modal')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" id="edit-task-form">
            <input type="hidden" name="action"  value="edit_task">
            <input type="hidden" name="task_id" id="edit_task_id">
            <div class="form-group">
                <label class="form-label">Task Title <span style="color:var(--danger);">*</span></label>
                <input type="text" name="title" id="edit_title" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Assign To <span style="color:var(--danger);">*</span></label>
                    <select name="assigned_to" id="edit_assigned_to" class="form-control" required>
                        <option value="">Select User</option>
                        <?php foreach ($assignable_users as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select name="priority" id="edit_priority" class="form-control">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Due Date</label>
                    <input type="date" name="due_date" id="edit_due_date" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:1.25rem;">
                <button type="button" class="btn glass-card" onclick="closeModal('edit-task-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════ -->
<script>
/* Modal helpers */
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

/* Close modals when clicking the overlay backdrop */
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) overlay.classList.remove('open');
    });
});

/* Open Add Task modal */
function openAddModal() {
    document.getElementById('add-task-form').reset();
    openModal('add-task-modal');
}

/* Open Edit Task modal and pre-fill fields */
function openEditModal(taskData) {
    var t = (typeof taskData === 'string') ? JSON.parse(taskData) : taskData;

    document.getElementById('edit_task_id').value      = t.id;
    document.getElementById('edit_title').value        = t.title;
    document.getElementById('edit_description').value  = t.description || '';
    document.getElementById('edit_due_date').value     = t.due_date    || '';

    // Set select values safely
    setSelectValue('edit_assigned_to', t.assigned_to);
    setSelectValue('edit_priority',    t.priority);
    setSelectValue('edit_status',      t.status);

    openModal('edit-task-modal');
}

function setSelectValue(selectId, value) {
    var sel = document.getElementById(selectId);
    for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value == value) {
            sel.selectedIndex = i;
            break;
        }
    }
}

/* Auto-dismiss alerts after 4 seconds */
(function() {
    var alert = document.getElementById('alert-msg');
    if (alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() { alert.remove(); }, 500);
        }, 4000);
    }
})();
</script>

<?php include_once '../../includes/footer.php'; ?>
