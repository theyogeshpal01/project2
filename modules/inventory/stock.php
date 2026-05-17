<?php 
include_once '../../includes/header.php'; 
include_once '../../core/functions.php';

$inventory = $pdo->query("SELECT * FROM inventory ORDER BY created_at DESC")->fetchAll();
?>

<div class="header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem;">Inventory Management</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">Track company devices, QR kits, and marketing materials.</p>
    </div>
    <button class="btn btn-primary">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg>
        Add Stock
    </button>
</div>

<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 2rem;">
    <div class="stat-card glass-card">
        <div class="stat-label">Total QR Kits</div>
        <div class="stat-value">2,500</div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-label">POS Devices</div>
        <div class="stat-value">120</div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-label">In Transit</div>
        <div class="stat-value">450</div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-label">Low Stock Alerts</div>
        <div class="stat-value" style="color: var(--danger);">3</div>
    </div>
</div>

<div class="glass-card" style="padding: 1.5rem;">
    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>SKU</th>
                    <th>Available Qty</th>
                    <th>Total Qty</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($inventory)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-muted);">No inventory items found.</td></tr>
                <?php endif; ?>
                <?php foreach($inventory as $item): ?>
                <tr>
                    <td><strong><?php echo $item['item_name']; ?></strong></td>
                    <td><span style="font-family: monospace;"><?php echo $item['sku']; ?></span></td>
                    <td><?php echo $item['available_qty']; ?></td>
                    <td><?php echo $item['total_qty']; ?></td>
                    <td><?php echo ($item['available_qty'] < 10) ? getStatusBadge('low_stock') : getStatusBadge('active'); ?></td>
                    <td>
                        <button class="btn glass-card" style="padding: 5px 10px; font-size: 0.75rem;">Assign</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
