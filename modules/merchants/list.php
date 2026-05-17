<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_merchant'])) {
    $pdo->prepare("INSERT INTO merchants (business_name, owner_name, mobile, email, address, city, pincode, category, gst_number, onboarded_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([
            trim($_POST['business_name']),
            trim($_POST['owner_name']),
            trim($_POST['mobile']),
            trim($_POST['email']),
            trim($_POST['address']),
            trim($_POST['city']),
            trim($_POST['pincode']),
            trim($_POST['category']),
            trim($_POST['gst_number']),
            $_SESSION['user_id']
        ]);
    $success = "Merchant added successfully!";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_merchant_status'])) {
    $pdo->prepare("UPDATE merchants SET status=? WHERE id=?")->execute([$_POST['status'], (int)$_POST['merchant_id']]);
    $success = "Merchant status updated!";
}

$merchants = $pdo->query("SELECT m.*, u.name as agent_name FROM merchants m LEFT JOIN users u ON m.onboarded_by = u.id ORDER BY m.created_at DESC")->fetchAll();
$total_active  = $pdo->query("SELECT COUNT(*) FROM merchants WHERE status='active'")->fetchColumn();
$total_pending = $pdo->query("SELECT COUNT(*) FROM merchants WHERE status='pending'")->fetchColumn();
?>

<div class="page-header">
    <div><h1>Merchant Management</h1><p>Track all onboarded merchants and their status.</p></div>
    <button class="btn btn-primary" onclick="document.getElementById('merchant-modal').classList.add('open')">+ Add Merchant</button>
</div>

<?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:2rem;">
    <div class="stat-card glass-card"><div class="stat-label">Total Merchants</div><div class="stat-value"><?php echo count($merchants); ?></div></div>
    <div class="stat-card glass-card"><div class="stat-label">Active</div><div class="stat-value" style="color:var(--success);"><?php echo $total_active; ?></div></div>
    <div class="stat-card glass-card"><div class="stat-label">Pending</div><div class="stat-value" style="color:var(--warning);"><?php echo $total_pending; ?></div></div>
    <div class="stat-card glass-card"><div class="stat-label">Rejected</div><div class="stat-value" style="color:var(--danger);"><?php echo $pdo->query("SELECT COUNT(*) FROM merchants WHERE status='rejected'")->fetchColumn(); ?></div></div>
</div>

<div class="glass-card" style="padding:1.5rem;">
    <div class="data-table-container">
        <table>
            <thead><tr><th>Business</th><th>Owner</th><th>Mobile</th><th>City</th><th>Category</th><th>Agent</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                <?php if (empty($merchants)): ?>
                    <tr><td colspan="8" style="text-align:center; padding:3rem; color:var(--text-muted);">No merchants found.</td></tr>
                <?php endif; ?>
                <?php foreach ($merchants as $m): ?>
                <tr>
                    <td><strong style="color:var(--primary);"><?php echo htmlspecialchars($m['business_name']); ?></strong><br><span style="font-size:0.75rem; color:var(--text-muted);"><?php echo $m['gst_number'] ?: 'No GST'; ?></span></td>
                    <td><?php echo htmlspecialchars($m['owner_name']); ?></td>
                    <td><?php echo $m['mobile']; ?></td>
                    <td><?php echo htmlspecialchars($m['city']); ?></td>
                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($m['category']); ?></span></td>
                    <td style="font-size:0.8rem;"><?php echo htmlspecialchars($m['agent_name'] ?? '—'); ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="merchant_id" value="<?php echo $m['id']; ?>">
                            <select name="status" onchange="this.form.submit()" class="form-control" style="padding:4px 8px; font-size:0.75rem; width:auto;">
                                <?php foreach (['pending','active','inactive','rejected'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $m['status']===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="update_merchant_status" value="1">
                        </form>
                    </td>
                    <td style="font-size:0.8rem; color:var(--text-muted);"><?php echo date('d M Y', strtotime($m['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="merchant-modal">
    <div class="modal-box" style="width:650px;">
        <div class="modal-header">
            <h3>Add New Merchant</h3>
            <button class="modal-close" onclick="document.getElementById('merchant-modal').classList.remove('open')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group"><label class="form-label">Business Name</label><input type="text" name="business_name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Owner Name</label><input type="text" name="owner_name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Mobile</label><input type="text" name="mobile" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                <div class="form-group"><label class="form-label">City</label><input type="text" name="city" class="form-control"></div>
                <div class="form-group"><label class="form-label">Pincode</label><input type="text" name="pincode" class="form-control"></div>
                <div class="form-group"><label class="form-label">Category</label>
                    <select name="category" class="form-control">
                        <option>Retail</option><option>Grocery</option><option>Medical</option><option>Restaurant</option><option>Electronics</option><option>Others</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">GST Number</label><input type="text" name="gst_number" class="form-control" placeholder="Optional"></div>
            </div>
            <div class="form-group"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2"></textarea></div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:1rem;">
                <button type="button" class="btn glass-card" onclick="document.getElementById('merchant-modal').classList.remove('open')">Cancel</button>
                <button type="submit" name="add_merchant" class="btn btn-primary">Add Merchant</button>
            </div>
        </form>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
