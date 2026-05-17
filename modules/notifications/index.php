<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

$user_id  = $_SESSION['user_id'];
$role_id  = $_SESSION['role_id'];
$is_admin = ($role_id == 1);

// Mark all as read
if (isset($_GET['mark_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user_id]);
    header("Location: index.php");
    exit();
}

// Mark single as read
if (isset($_GET['read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([(int)$_GET['read'], $user_id]);
}

// Admin: send announcement
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notif'])) {
    $title      = trim($_POST['title']);
    $message    = trim($_POST['message']);
    $target_role = (int)$_POST['target_role'];

    if ($target_role === 0) {
        // Send to all
        $all_users = $pdo->query("SELECT id FROM users WHERE status='active'")->fetchAll();
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role_id = ? AND status='active'");
        $stmt->execute([$target_role]);
        $all_users = $stmt->fetchAll();
    }

    $ins = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,'info')");
    foreach ($all_users as $u) {
        $ins->execute([$u['id'], $title, $message]);
    }
    $success = "Notification sent to " . count($all_users) . " users!";
}

// Fetch notifications
$notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$notifs->execute([$user_id]);
$notifs = $notifs->fetchAll();

$unread = array_filter($notifs, fn($n) => !$n['is_read']);
$roles  = $pdo->query("SELECT * FROM roles ORDER BY id")->fetchAll();
?>

<div class="page-header">
    <div>
        <h1>Notifications</h1>
        <p><?php echo count($unread); ?> unread notifications</p>
    </div>
    <div style="display:flex; gap:10px;">
        <?php if (count($unread) > 0): ?>
            <a href="?mark_read=1" class="btn glass-card">Mark All Read</a>
        <?php endif; ?>
        <?php if ($is_admin): ?>
            <button class="btn btn-primary" onclick="document.getElementById('send-notif-modal').classList.add('open')">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                Send Announcement
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

<div class="glass-card" style="padding:1.5rem;">
    <?php if (empty($notifs)): ?>
        <div style="text-align:center; padding:4rem; color:var(--text-muted);">
            <div style="font-size:3rem; margin-bottom:1rem;">🔔</div>
            <p>No notifications yet.</p>
        </div>
    <?php endif; ?>

    <div style="display:flex; flex-direction:column; gap:8px;">
        <?php foreach ($notifs as $n):
            $colors = ['info'=>'var(--accent)','success'=>'var(--success)','warning'=>'var(--warning)','danger'=>'var(--danger)'];
            $color  = $colors[$n['type']] ?? 'var(--primary)';
            $icons  = ['info'=>'ℹ️','success'=>'✅','warning'=>'⚠️','danger'=>'❌'];
            $icon   = $icons[$n['type']] ?? '🔔';
        ?>
        <div style="display:flex; gap:14px; padding:14px 16px; background:<?php echo !$n['is_read'] ? 'rgba(79,70,229,0.05)' : 'var(--bg-main)'; ?>; border-radius:12px; border:1px solid <?php echo !$n['is_read'] ? 'rgba(79,70,229,0.2)' : 'var(--border)'; ?>; transition:all 0.2s;">
            <div style="font-size:1.25rem; flex-shrink:0; margin-top:2px;"><?php echo $icon; ?></div>
            <div style="flex:1;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <div style="font-weight:<?php echo !$n['is_read'] ? '700' : '500'; ?>; font-size:0.9rem; color:<?php echo $color; ?>;">
                        <?php echo htmlspecialchars($n['title']); ?>
                        <?php if (!$n['is_read']): ?><span style="display:inline-block; width:7px; height:7px; background:var(--primary); border-radius:50%; margin-left:6px; vertical-align:middle;"></span><?php endif; ?>
                    </div>
                    <span style="font-size:0.72rem; color:var(--text-muted); white-space:nowrap; margin-left:10px;"><?php echo date('d M, H:i', strtotime($n['created_at'])); ?></span>
                </div>
                <div style="font-size:0.8rem; color:var(--text-muted); margin-top:3px;"><?php echo htmlspecialchars($n['message']); ?></div>
            </div>
            <?php if (!$n['is_read']): ?>
                <a href="?read=<?php echo $n['id']; ?>" style="color:var(--text-muted); font-size:0.75rem; white-space:nowrap; align-self:center; padding:3px 8px; border-radius:6px; border:1px solid var(--border);">Mark read</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Send Notification Modal (Admin only) -->
<?php if ($is_admin): ?>
<div class="modal-overlay" id="send-notif-modal">
    <div class="modal-box" style="width:500px;">
        <div class="modal-header">
            <h3>Send Announcement</h3>
            <button class="modal-close" onclick="document.getElementById('send-notif-modal').classList.remove('open')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Target Audience</label>
                <select name="target_role" class="form-control">
                    <option value="0">All Users</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?php echo $r['id']; ?>"><?php echo $r['role_name']; ?>s Only</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" required placeholder="Notification title">
            </div>
            <div class="form-group">
                <label class="form-label">Message</label>
                <textarea name="message" class="form-control" rows="3" required placeholder="Write your message..."></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn glass-card" onclick="document.getElementById('send-notif-modal').classList.remove('open')">Cancel</button>
                <button type="submit" name="send_notif" class="btn btn-primary">Send Now</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include_once '../../includes/footer.php'; ?>
