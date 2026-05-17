<?php 
include_once '../../includes/header.php'; 
include_once '../../core/functions.php';

$stmt = $pdo->query("SELECT f.*, (SELECT COUNT(*) FROM form_fields WHERE form_id = f.id) as field_count 
                     FROM forms f ORDER BY f.created_at DESC");
$forms = $stmt->fetchAll();
?>

<div class="header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem;">Forms & Data Collection</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">Manage your dynamic forms and survey templates.</p>
    </div>
    <a href="create.php" class="btn btn-primary">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg>
        Create New Form
    </a>
</div>

<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 2rem;">
    <div class="stat-card glass-card">
        <div class="stat-label">Total Forms</div>
        <div class="stat-value"><?php echo count($forms); ?></div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-label">Active Surveys</div>
        <div class="stat-value">
            <?php 
            $activeCount = 0;
            foreach($forms as $f) if($f['status'] == 'active') $activeCount++;
            echo $activeCount;
            ?>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-label">Total Submissions</div>
        <div class="stat-value">0</div>
    </div>
</div>

<div class="glass-card" style="padding: 1.5rem;">
    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>Form Title</th>
                    <th>Status</th>
                    <th>Fields</th>
                    <th>Submissions</th>
                    <th>Created On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($forms)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                        No forms found. <a href="create.php" style="color: var(--primary);">Create your first form</a>
                    </td>
                </tr>
                <?php endif; ?>
                
                <?php foreach($forms as $form): ?>
                <tr>
                    <td>
                        <strong style="color: var(--primary);"><?php echo $form['title']; ?></strong>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo substr($form['description'], 0, 50); ?>...</div>
                    </td>
                    <td><?php echo getStatusBadge($form['status']); ?></td>
                    <td><?php echo $form['field_count']; ?> Fields</td>
                    <td>0</td>
                    <td><?php echo date('M d, Y', strtotime($form['created_at'])); ?></td>
                    <td>
                        <div style="display: flex; gap: 10px;">
                            <a href="view.php?id=<?php echo $form['id']; ?>" title="Preview" style="color: var(--accent);"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></a>
                            <a href="edit.php?id=<?php echo $form['id']; ?>" title="Edit" style="color: var(--text-muted);"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></a>
                            <a href="responses.php?id=<?php echo $form['id']; ?>" title="Submissions" style="color: var(--success);"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"></path></svg></a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
