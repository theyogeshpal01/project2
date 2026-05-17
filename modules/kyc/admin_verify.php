<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kyc_action'])) {
    $doc_id  = (int)$_POST['doc_id'];
    $status  = $_POST['status'];
    $reason  = trim($_POST['rejection_reason'] ?? '');

    $doc = $pdo->prepare("SELECT * FROM kyc_documents WHERE id = ?");
    $doc->execute([$doc_id]);
    $doc = $doc->fetch();

    if ($doc) {
        $pdo->prepare("UPDATE kyc_documents SET status=?, rejection_reason=? WHERE id=?")
            ->execute([$status, $reason, $doc_id]);

        // Notify user (safe)
        try {
            $msg = $status === 'approved' ? "Your {$doc['doc_type']} document has been approved!" : "Your {$doc['doc_type']} document was rejected. Reason: {$reason}";
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)")
                ->execute([$doc['user_id'], 'KYC Update', $msg, $status === 'approved' ? 'success' : 'danger']);
        } catch (Exception $e) {}

        // If all docs approved, update user kyc_status
        $pending = $pdo->prepare("SELECT COUNT(*) FROM kyc_documents WHERE user_id = ? AND status != 'approved'");
        $pending->execute([$doc['user_id']]);
        if ($pending->fetchColumn() == 0) {
            $pdo->prepare("UPDATE users SET kyc_status='verified' WHERE id=?")->execute([$doc['user_id']]);
        }

        $success = "KYC document " . ucfirst($status) . "!";
    }
}

$filter = $_GET['status'] ?? 'pending';
$docs = $pdo->prepare("SELECT kd.*, u.name as user_name, u.email, r.role_name FROM kyc_documents kd JOIN users u ON kd.user_id = u.id JOIN roles r ON u.role_id = r.id WHERE kd.status = ? ORDER BY kd.uploaded_at DESC");
$docs->execute([$filter]);
$docs = $docs->fetchAll();

$counts = $pdo->query("SELECT status, COUNT(*) as cnt FROM kyc_documents GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="page-header">
    <div><h1>KYC Verification Panel</h1><p>Review and approve identity documents submitted by users.</p></div>
</div>

<?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr); margin-bottom:2rem;">
    <div class="stat-card glass-card"><div class="stat-label">Pending</div><div class="stat-value" style="color:var(--warning);"><?php echo $counts['pending'] ?? 0; ?></div></div>
    <div class="stat-card glass-card"><div class="stat-label">Approved</div><div class="stat-value" style="color:var(--success);"><?php echo $counts['approved'] ?? 0; ?></div></div>
    <div class="stat-card glass-card"><div class="stat-label">Rejected</div><div class="stat-value" style="color:var(--danger);"><?php echo $counts['rejected'] ?? 0; ?></div></div>
</div>

<div style="display:flex; gap:8px; margin-bottom:1.5rem;">
    <?php foreach (['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected'] as $k=>$v): ?>
        <a href="?status=<?php echo $k; ?>" class="btn <?php echo $filter===$k?'btn-primary':'glass-card'; ?>" style="padding:6px 16px; font-size:0.8rem;"><?php echo $v; ?></a>
    <?php endforeach; ?>
</div>

<div class="glass-card" style="padding:1.5rem;">
    <div class="data-table-container">
        <table>
            <thead><tr><th>User</th><th>Role</th><th>Doc Type</th><th>Document</th><th>Uploaded</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (empty($docs)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">No documents found.</td></tr>
                <?php endif; ?>
                <?php foreach ($docs as $d): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($d['user_name']); ?></strong><br><span style="font-size:0.75rem; color:var(--text-muted);"><?php echo $d['email']; ?></span></td>
                    <td><span class="badge badge-primary"><?php echo $d['role_name']; ?></span></td>
                    <td style="text-transform:uppercase; font-weight:600;"><?php echo $d['doc_type']; ?></td>
                    <td><a href="<?php echo BASE_URL; ?>uploads/kyc/<?php echo $d['doc_path']; ?>" target="_blank" class="btn glass-card" style="padding:4px 10px; font-size:0.75rem;">View Doc</a></td>
                    <td style="font-size:0.8rem; color:var(--text-muted);"><?php echo date('d M Y', strtotime($d['uploaded_at'])); ?></td>
                    <td><?php $sc=['pending'=>'warning','approved'=>'success','rejected'=>'danger']; echo '<span class="badge badge-'.($sc[$d['status']]??'muted').'">'.strtoupper($d['status']).'</span>'; ?></td>
                    <td>
                        <?php if ($d['status'] === 'pending'): ?>
                        <form method="POST" style="display:inline-flex; gap:6px; align-items:center;">
                            <input type="hidden" name="doc_id" value="<?php echo $d['id']; ?>">
                            <input type="hidden" name="rejection_reason" value="">
                            <button type="submit" name="kyc_action" onclick="this.form.status.value='approved'" class="btn" style="background:var(--success); color:white; padding:4px 10px; font-size:0.75rem;">Approve</button>
                            <button type="button" onclick="rejectDoc(<?php echo $d['id']; ?>)" class="btn" style="background:var(--danger); color:white; padding:4px 10px; font-size:0.75rem;">Reject</button>
                            <input type="hidden" name="status" id="status-<?php echo $d['id']; ?>">
                        </form>
                        <?php else: ?>
                            <span style="font-size:0.8rem; color:var(--text-muted);">Done</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal-overlay" id="reject-modal">
    <div class="modal-box" style="width:450px;">
        <div class="modal-header">
            <h3>Reject Document</h3>
            <button class="modal-close" onclick="document.getElementById('reject-modal').classList.remove('open')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="doc_id" id="reject-doc-id">
            <input type="hidden" name="status" value="rejected">
            <div class="form-group">
                <label class="form-label">Rejection Reason</label>
                <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Explain why this document is rejected..."></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn glass-card" onclick="document.getElementById('reject-modal').classList.remove('open')">Cancel</button>
                <button type="submit" name="kyc_action" class="btn" style="background:var(--danger); color:white;">Confirm Reject</button>
            </div>
        </form>
    </div>
</div>

<script>
function rejectDoc(id) {
    document.getElementById('reject-doc-id').value = id;
    document.getElementById('reject-modal').classList.add('open');
}
</script>

<?php include_once '../../includes/footer.php'; ?>
