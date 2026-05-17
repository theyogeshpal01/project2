<?php 
include_once '../../includes/header.php'; 
include_once '../../core/functions.php';

$agreements = $pdo->query("SELECT * FROM agreements ORDER BY created_at DESC")->fetchAll();
?>

<div class="header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem;">Legal & Agreements</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">Manage digital contracts, T&Cs, and compliance policies.</p>
    </div>
    <button class="btn btn-primary">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg>
        New Agreement
    </button>
</div>

<div class="glass-card" style="padding: 1.5rem;">
    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Version</th>
                    <th>Status</th>
                    <th>Created On</th>
                    <th>Acceptances</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($agreements)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-muted);">No agreements found.</td></tr>
                <?php endif; ?>
                <?php foreach($agreements as $ag): ?>
                <tr>
                    <td><strong><?php echo $ag['title']; ?></strong></td>
                    <td>v<?php echo $ag['version']; ?></td>
                    <td><?php echo getStatusBadge($ag['status']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($ag['created_at'])); ?></td>
                    <td>0</td>
                    <td>
                        <div style="display: flex; gap: 10px;">
                            <button title="View" style="background:none; border:none; color:var(--accent); cursor:pointer;"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg></button>
                            <button title="Edit" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
