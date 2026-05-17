<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $pdo->prepare("INSERT INTO expenses (title, amount, category, paid_to, payment_mode, expense_date, added_by) VALUES (?,?,?,?,?,?,?)")
        ->execute([
            trim($_POST['title']),
            (float)$_POST['amount'],
            trim($_POST['category']),
            trim($_POST['paid_to']),
            $_POST['payment_mode'],
            $_POST['expense_date'],
            $_SESSION['user_id']
        ]);
    $success = "Expense recorded!";
}

$expenses = $pdo->query("SELECT e.*, u.name as added_by_name FROM expenses e LEFT JOIN users u ON e.added_by = u.id ORDER BY e.expense_date DESC")->fetchAll();
$total_exp   = $pdo->query("SELECT SUM(amount) FROM expenses")->fetchColumn() ?: 0;
$this_month  = $pdo->query("SELECT SUM(amount) FROM expenses WHERE MONTH(expense_date)=MONTH(NOW()) AND YEAR(expense_date)=YEAR(NOW())")->fetchColumn() ?: 0;
?>

<div class="page-header">
    <div><h1>Expenses</h1><p>Track all business expenses and payments.</p></div>
    <button class="btn btn-primary" onclick="document.getElementById('exp-modal').classList.add('open')">+ Add Expense</button>
</div>

<?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr); margin-bottom:2rem;">
    <div class="stat-card glass-card"><div class="stat-label">Total Expenses</div><div class="stat-value">₹<?php echo number_format($total_exp,0); ?></div></div>
    <div class="stat-card glass-card"><div class="stat-label">This Month</div><div class="stat-value" style="color:var(--warning);">₹<?php echo number_format($this_month,0); ?></div></div>
    <div class="stat-card glass-card"><div class="stat-label">Total Records</div><div class="stat-value"><?php echo count($expenses); ?></div></div>
</div>

<div class="glass-card" style="padding:1.5rem;">
    <div class="data-table-container">
        <table>
            <thead><tr><th>Title</th><th>Category</th><th>Paid To</th><th>Mode</th><th>Amount</th><th>Date</th><th>Added By</th></tr></thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr><td colspan="7" style="text-align:center; padding:3rem; color:var(--text-muted);">No expenses recorded.</td></tr>
                <?php endif; ?>
                <?php foreach ($expenses as $e): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($e['title']); ?></strong></td>
                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($e['category']); ?></span></td>
                    <td><?php echo htmlspecialchars($e['paid_to']); ?></td>
                    <td style="text-transform:uppercase; font-size:0.8rem;"><?php echo $e['payment_mode']; ?></td>
                    <td><strong style="color:var(--danger);">₹<?php echo number_format($e['amount'],2); ?></strong></td>
                    <td style="font-size:0.8rem;"><?php echo date('d M Y', strtotime($e['expense_date'])); ?></td>
                    <td style="font-size:0.8rem; color:var(--text-muted);"><?php echo htmlspecialchars($e['added_by_name'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal-overlay" id="exp-modal">
    <div class="modal-box" style="width:500px;">
        <div class="modal-header">
            <h3>Add Expense</h3>
            <button class="modal-close" onclick="document.getElementById('exp-modal').classList.remove('open')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST">
            <div class="form-group"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required placeholder="e.g. Office Rent"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group"><label class="form-label">Amount (₹)</label><input type="number" name="amount" class="form-control" step="0.01" required></div>
                <div class="form-group"><label class="form-label">Category</label><input type="text" name="category" class="form-control" placeholder="Rent / Travel / Misc"></div>
            </div>
            <div class="form-group"><label class="form-label">Paid To</label><input type="text" name="paid_to" class="form-control" placeholder="Vendor / Person name"></div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Payment Mode</label>
                    <select name="payment_mode" class="form-control">
                        <option value="bank">Bank Transfer</option>
                        <option value="upi">UPI</option>
                        <option value="cash">Cash</option>
                        <option value="cheque">Cheque</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Date</label><input type="date" name="expense_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:1rem;">
                <button type="button" class="btn glass-card" onclick="document.getElementById('exp-modal').classList.remove('open')">Cancel</button>
                <button type="submit" name="add_expense" class="btn btn-primary">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
