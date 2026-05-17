<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

// Create Invoice
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invoice'])) {
    $inv_no  = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(),0,4));
    $client  = trim($_POST['client_name']);
    $email   = trim($_POST['client_email']);
    $items   = json_encode(array_map(null, $_POST['item_name'], $_POST['item_qty'], $_POST['item_rate']));
    $sub     = array_sum(array_map(fn($q,$r) => $q*$r, $_POST['item_qty'], $_POST['item_rate']));
    $gst_pct = (float)$_POST['gst_percent'];
    $gst_amt = $sub * $gst_pct / 100;
    $total   = $sub + $gst_amt;
    $due     = $_POST['due_date'];

    $pdo->prepare("INSERT INTO invoices (invoice_number,client_name,client_email,items,subtotal,gst_percent,gst_amount,total_amount,due_date,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
        ->execute([$inv_no,$client,$email,$items,$sub,$gst_pct,$gst_amt,$total,$due,$_SESSION['user_id']]);
    $success = "Invoice $inv_no created!";
}

// Update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_inv_status'])) {
    $pdo->prepare("UPDATE invoices SET status=? WHERE id=?")->execute([$_POST['status'], (int)$_POST['inv_id']]);
    $success = "Invoice status updated!";
}

$invoices = $pdo->query("SELECT i.*, u.name as created_by_name FROM invoices i LEFT JOIN users u ON i.created_by = u.id ORDER BY i.created_at DESC")->fetchAll();
$total_paid    = $pdo->query("SELECT SUM(total_amount) FROM invoices WHERE status='paid'")->fetchColumn() ?: 0;
$total_pending = $pdo->query("SELECT SUM(total_amount) FROM invoices WHERE status='sent'")->fetchColumn() ?: 0;
$total_overdue = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status='overdue'")->fetchColumn();
?>

<div class="page-header">
    <div><h1>Invoices</h1><p>Create and manage client invoices with GST.</p></div>
    <button class="btn btn-primary" onclick="document.getElementById('inv-modal').classList.add('open')">+ New Invoice</button>
</div>

<?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:2rem;">
    <div class="stat-card glass-card"><div class="stat-label">Total Invoices</div><div class="stat-value"><?php echo count($invoices); ?></div></div>
    <div class="stat-card glass-card"><div class="stat-label">Paid</div><div class="stat-value" style="color:var(--success);">₹<?php echo number_format($total_paid,0); ?></div></div>
    <div class="stat-card glass-card"><div class="stat-label">Pending</div><div class="stat-value" style="color:var(--warning);">₹<?php echo number_format($total_pending,0); ?></div></div>
    <div class="stat-card glass-card"><div class="stat-label">Overdue</div><div class="stat-value" style="color:var(--danger);"><?php echo $total_overdue; ?></div></div>
</div>

<div class="glass-card" style="padding:1.5rem;">
    <div class="data-table-container">
        <table>
            <thead><tr><th>Invoice #</th><th>Client</th><th>Amount</th><th>GST</th><th>Total</th><th>Due Date</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr><td colspan="8" style="text-align:center; padding:3rem; color:var(--text-muted);">No invoices yet.</td></tr>
                <?php endif; ?>
                <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td><strong style="color:var(--primary); font-family:monospace;"><?php echo $inv['invoice_number']; ?></strong></td>
                    <td><div style="font-weight:600;"><?php echo htmlspecialchars($inv['client_name']); ?></div><div style="font-size:0.75rem; color:var(--text-muted);"><?php echo $inv['client_email']; ?></div></td>
                    <td>₹<?php echo number_format($inv['subtotal'],2); ?></td>
                    <td>₹<?php echo number_format($inv['gst_amount'],2); ?> (<?php echo $inv['gst_percent']; ?>%)</td>
                    <td><strong>₹<?php echo number_format($inv['total_amount'],2); ?></strong></td>
                    <td style="font-size:0.8rem;"><?php echo date('d M Y', strtotime($inv['due_date'])); ?></td>
                    <td>
                        <?php $sc=['draft'=>'muted','sent'=>'warning','paid'=>'success','overdue'=>'danger','cancelled'=>'danger'];
                        echo '<span class="badge badge-'.($sc[$inv['status']]??'muted').'">'.strtoupper($inv['status']).'</span>'; ?>
                    </td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="inv_id" value="<?php echo $inv['id']; ?>">
                            <select name="status" onchange="this.form.submit()" class="form-control" style="padding:4px 8px; font-size:0.75rem; width:auto;">
                                <?php foreach (['draft','sent','paid','overdue','cancelled'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $inv['status']===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="update_inv_status" value="1">
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Invoice Modal -->
<div class="modal-overlay" id="inv-modal">
    <div class="modal-box" style="width:700px;">
        <div class="modal-header">
            <h3>Create New Invoice</h3>
            <button class="modal-close" onclick="document.getElementById('inv-modal').classList.remove('open')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group"><label class="form-label">Client Name</label><input type="text" name="client_name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Client Email</label><input type="email" name="client_email" class="form-control"></div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group"><label class="form-label">GST %</label><input type="number" name="gst_percent" class="form-control" value="18" step="0.01"></div>
                <div class="form-group"><label class="form-label">Due Date</label><input type="date" name="due_date" class="form-control" required></div>
            </div>

            <h4 style="margin:1rem 0 0.75rem; font-size:0.9rem;">Line Items</h4>
            <div id="items-container">
                <div class="item-row" style="display:grid; grid-template-columns:2fr 1fr 1fr auto; gap:8px; margin-bottom:8px; align-items:center;">
                    <input type="text" name="item_name[]" class="form-control" placeholder="Item description" required>
                    <input type="number" name="item_qty[]" class="form-control" placeholder="Qty" min="1" value="1" required>
                    <input type="number" name="item_rate[]" class="form-control" placeholder="Rate ₹" step="0.01" required>
                    <button type="button" onclick="this.closest('.item-row').remove()" style="background:none; border:none; color:var(--danger); cursor:pointer; font-size:1.2rem;">×</button>
                </div>
            </div>
            <button type="button" onclick="addItem()" class="btn glass-card" style="font-size:0.8rem; padding:6px 14px; margin-bottom:1rem;">+ Add Item</button>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:1rem;">
                <button type="button" class="btn glass-card" onclick="document.getElementById('inv-modal').classList.remove('open')">Cancel</button>
                <button type="submit" name="create_invoice" class="btn btn-primary">Create Invoice</button>
            </div>
        </form>
    </div>
</div>

<script>
function addItem() {
    const row = document.createElement('div');
    row.className = 'item-row';
    row.style = 'display:grid; grid-template-columns:2fr 1fr 1fr auto; gap:8px; margin-bottom:8px; align-items:center;';
    row.innerHTML = `<input type="text" name="item_name[]" class="form-control" placeholder="Item description" required>
        <input type="number" name="item_qty[]" class="form-control" placeholder="Qty" min="1" value="1" required>
        <input type="number" name="item_rate[]" class="form-control" placeholder="Rate ₹" step="0.01" required>
        <button type="button" onclick="this.closest('.item-row').remove()" style="background:none; border:none; color:var(--danger); cursor:pointer; font-size:1.2rem;">×</button>`;
    document.getElementById('items-container').appendChild(row);
}
</script>

<?php include_once '../../includes/footer.php'; ?>
