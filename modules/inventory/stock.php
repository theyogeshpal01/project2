<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

// Handle Add Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    try {
        $pdo->prepare("INSERT INTO inventory (item_name, sku, available_qty, total_qty, category, unit) VALUES (?,?,?,?,?,?)")
            ->execute([$_POST['item_name'], $_POST['sku'], $_POST['qty'], $_POST['qty'], $_POST['category']??'General', $_POST['unit']??'Piece']);
        $success = "Item added successfully!";
    } catch(Exception $e) { $error = "Error: " . $e->getMessage(); }
}

try {
    $inventory   = $pdo->query("SELECT * FROM inventory ORDER BY created_at DESC")->fetchAll();
    $total_items = count($inventory);
    $avail_total = array_sum(array_column($inventory, 'available_qty'));
    $low_stock   = count(array_filter($inventory, fn($i) => ($i['available_qty'] ?? 0) < 10));
    $assigned    = array_sum(array_column($inventory, 'total_qty')) - $avail_total;
} catch(Exception $e) {
    $inventory = []; $total_items = $avail_total = $low_stock = $assigned = 0;
}
?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-left">
        <div class="page-header-icon" style="background:rgba(8,145,178,0.1);color:var(--accent);">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"></path>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>
        </div>
        <div>
            <h1>Asset Management</h1>
            <p>Manage company assets, devices, and inventory</p>
        </div>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-success" onclick="alert('Sample Excel download coming soon')">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Sample Excel
        </button>
        <button class="btn btn-warning" onclick="alert('Import coming soon')">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
            Import Excel
        </button>
        <button class="btn" onclick="alert('Export coming soon')">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
            Export Excel
        </button>
        <button class="btn btn-primary" onclick="document.getElementById('addModal').classList.add('open')">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
            + Add Asset
        </button>
    </div>
</div>

<?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
<?php if (isset($error)):   ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

<!-- Stat Cards -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="stat-card glass-card">
        <div class="stat-card-text"><div class="stat-label">Total Assets</div><div class="stat-value"><?php echo $total_items; ?></div></div>
        <div class="stat-card-icon" style="background:rgba(37,99,235,0.08);color:var(--primary);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"></path><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path></svg>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-card-text"><div class="stat-label">Available</div><div class="stat-value" style="color:var(--success);"><?php echo $avail_total; ?></div></div>
        <div class="stat-card-icon" style="background:rgba(16,185,129,0.08);color:var(--success);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path></svg>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-card-text"><div class="stat-label">Assigned</div><div class="stat-value" style="color:var(--accent);"><?php echo max(0,$assigned); ?></div></div>
        <div class="stat-card-icon" style="background:rgba(8,145,178,0.08);color:var(--accent);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle></svg>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-card-text"><div class="stat-label">Low Stock</div><div class="stat-value" style="color:var(--danger);"><?php echo $low_stock; ?></div></div>
        <div class="stat-card-icon" style="background:rgba(239,68,68,0.08);color:var(--danger);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
        </div>
    </div>
</div>

<!-- Assets Table -->
<div class="glass-card table-card">
    <div class="table-header">
        <div>
            <h3 style="font-size:0.95rem;font-weight:700;">Asset List</h3>
            <p style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;">Showing <?php echo $total_items; ?> asset<?php echo $total_items!==1?'s':''; ?></p>
        </div>
    </div>
    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>Asset ID</th>
                    <th>Item Name</th>
                    <th>SKU / Category</th>
                    <th>Unit</th>
                    <th>Available Qty</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inventory)): ?>
                    <tr><td colspan="7" style="text-align:center;padding:3rem;color:var(--text-muted);">No assets found. Add your first asset.</td></tr>
                <?php endif; ?>
                <?php foreach($inventory as $i => $item):
                    $qty = $item['available_qty'] ?? 0;
                    $status_badge = $qty < 1 ? 'danger' : ($qty < 10 ? 'warning' : 'success');
                    $status_label = $qty < 1 ? 'Out of Stock' : ($qty < 10 ? 'Low Stock' : 'Available');
                    $asset_id = 'AST' . str_pad($item['id'], 4, '0', STR_PAD_LEFT);
                ?>
                <tr>
                    <td style="font-family:monospace;font-weight:700;color:var(--primary);font-size:0.82rem;"><?php echo $asset_id; ?></td>
                    <td style="font-weight:600;"><?php echo htmlspecialchars($item['item_name']); ?></td>
                    <td>
                        <div style="font-size:0.82rem;"><?php echo htmlspecialchars($item['sku']??'—'); ?></div>
                        <div style="font-size:0.72rem;color:var(--text-muted);"><?php echo htmlspecialchars($item['category']??'General'); ?></div>
                    </td>
                    <td style="color:var(--text-muted);"><?php echo htmlspecialchars($item['unit']??'Piece'); ?></td>
                    <td style="font-weight:700;"><?php echo number_format($qty); ?></td>
                    <td><span class="badge badge-<?php echo $status_badge; ?>"><?php echo $status_label; ?></span></td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <button class="btn btn-sm" title="Edit" style="padding:0.35rem 0.6rem;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                            </button>
                            <button class="btn btn-sm" title="View" style="padding:0.35rem 0.6rem;color:var(--accent);border-color:rgba(8,145,178,0.2);">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                            </button>
                            <button class="btn btn-sm" title="Assign" style="padding:0.35rem 0.6rem;color:var(--success);border-color:rgba(16,185,129,0.2);">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg>
                            </button>
                            <button class="btn btn-sm btn-danger" title="Delete" data-confirm="Delete this asset?" style="padding:0.35rem 0.6rem;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Asset Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box" style="width:480px;">
        <div class="modal-header">
            <h3 style="font-size:1rem;font-weight:700;">Add New Asset</h3>
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        <form method="POST">
            <div class="form-group"><label class="form-label">Item Name *</label><input type="text" name="item_name" class="form-control" required placeholder="e.g. Dell Laptop"></div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group"><label class="form-label">SKU</label><input type="text" name="sku" class="form-control" placeholder="e.g. LAP-001"></div>
                <div class="form-group"><label class="form-label">Category</label><input type="text" name="category" class="form-control" placeholder="e.g. Laptop"></div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group"><label class="form-label">Quantity *</label><input type="number" name="qty" class="form-control" required min="0" value="1"></div>
                <div class="form-group"><label class="form-label">Unit</label><input type="text" name="unit" class="form-control" placeholder="Piece / Set / Box"></div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:0.5rem;">
                <button type="button" class="btn" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
                <button type="submit" name="add_item" class="btn btn-primary">Add Asset</button>
            </div>
        </form>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
