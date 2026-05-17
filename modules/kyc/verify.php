<?php 
include_once '../../includes/header.php'; 
include_once '../../core/functions.php';

$user_id = $_SESSION['user_id'] ?? 1;

// Handle File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $type = $_POST['doc_type'];
    $file = $_FILES['document'];
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = "KYC_" . $user_id . "_" . $type . "_" . time() . "." . $ext;
    $target = "../../uploads/kyc/" . $filename;
    
    if (!is_dir("../../uploads/kyc/")) mkdir("../../uploads/kyc/", 0777, true);
    
    if (move_uploaded_file($file['tmp_name'], $target)) {
        $stmt = $pdo->prepare("INSERT INTO kyc_documents (user_id, doc_type, doc_path, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$user_id, $type, $filename]);
        $success = "Document uploaded successfully! Verification pending.";
    }
}

$my_docs = $pdo->prepare("SELECT * FROM kyc_documents WHERE user_id = ?");
$my_docs->execute([$user_id]);
$docs = $my_docs->fetchAll();
?>

<div class="header-actions" style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem;">KYC & Identity Verification</h1>
    <p style="color: var(--text-muted); font-size: 0.875rem;">Upload your identity documents to unlock payouts and withdrawals.</p>
</div>

<div style="display: grid; grid-template-columns: 350px 1fr; gap: 2rem;">
    <!-- Upload Section -->
    <div class="glass-card" style="padding: 1.5rem; height: fit-content;">
        <h3 style="font-size: 1.125rem; margin-bottom: 1.5rem;">Upload Document</h3>
        <form method="POST" enctype="multipart/form-data">
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Document Type</label>
                <select name="doc_type" required style="width: 100%; padding: 0.75rem; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: white;">
                    <option value="aadhar">Aadhar Card</option>
                    <option value="pan">PAN Card</option>
                    <option value="bank_proof">Bank Proof (Cheque/Passbook)</option>
                    <option value="gst">GST Certificate (Optional)</option>
                    <option value="selfie">Live Selfie</option>
                </select>
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Select File</label>
                <input type="file" name="document" required style="width: 100%; color: var(--text-muted);">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%;">Upload for Verification</button>
        </form>

        <div style="margin-top: 2rem; padding: 1rem; background: rgba(34, 211, 238, 0.05); border-radius: 8px; border: 1px solid rgba(34, 211, 238, 0.2);">
            <div style="font-size: 0.8125rem; color: var(--accent); font-weight: 600; margin-bottom: 5px;">KYC Status: <?php echo $_SESSION['kyc_status'] ?? 'Pending'; ?></div>
            <div style="font-size: 0.75rem; color: var(--text-muted);">Verify all documents to reach "Active" status.</div>
        </div>
    </div>

    <!-- Documents List -->
    <div class="glass-card" style="padding: 1.5rem;">
        <h3 style="font-size: 1.125rem; margin-bottom: 1.5rem;">My Verification Documents</h3>
        <div class="data-table-container">
            <table>
                <thead>
                    <tr>
                        <th>Document Type</th>
                        <th>File</th>
                        <th>Status</th>
                        <th>Uploaded On</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($docs)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 2rem; color: var(--text-muted);">No documents uploaded yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach($docs as $doc): ?>
                    <tr>
                        <td style="text-transform: uppercase; font-weight: 600; font-size: 0.875rem;"><?php echo $doc['doc_type']; ?></td>
                        <td><a href="../../uploads/kyc/<?php echo $doc['doc_path']; ?>" target="_blank" style="color: var(--primary);">View Doc</a></td>
                        <td><?php echo getStatusBadge($doc['status']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($doc['uploaded_at'])); ?></td>
                        <td style="font-size: 0.75rem; color: var(--danger);"><?php echo $doc['rejection_reason'] ?: '-'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
