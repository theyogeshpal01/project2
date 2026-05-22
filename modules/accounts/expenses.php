<?php
include_once '../../includes/header.php';

$categories   = ['Travel', 'Food', 'Office', 'Marketing', 'IT', 'Other'];
$payment_modes = ['cash' => 'Cash', 'bank' => 'Bank Transfer', 'upi' => 'UPI', 'cheque' => 'Cheque'];
$company_id   = $_SESSION['company_id'] ?? 1;

$msg_success = '';
$msg_error   = '';

/* ──────────────────────────────────────────────────────────────
   POST: ADD EXPENSE
────────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO expenses (company_id, title, amount, category, paid_to, payment_mode, expense_date, added_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $company_id,
            trim($_POST['title']),
            (float) $_POST['amount'],
            $_POST['category'],
            trim($_POST['paid_to']),
            $_POST['payment_mode'],
            $_POST['expense_date'],
            $_SESSION['user_id']
        ]);
        $msg_success = 'Expense added successfully!';
    } catch (Exception $e) {
        $msg_error = 'Error adding expense: ' . $e->getMessage();
    }
}

/* ──────────────────────────────────────────────────────────────
   POST: EDIT EXPENSE
────────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit') {
    try {
        $stmt = $pdo->prepare(
            "UPDATE expenses SET title=?, amount=?, category=?, paid_to=?, payment_mode=?, expense_date=? WHERE id=? AND company_id=?"
        );
        $stmt->execute([
            trim($_POST['title']),
            (float) $_POST['amount'],
            $_POST['category'],
            trim($_POST['paid_to']),
            $_POST['payment_mode'],
            $_POST['expense_date'],
            (int) $_POST['expense_id'],
            $company_id
        ]);
        $msg_success = 'Expense updated successfully!';
    } catch (Exception $e) {
        $msg_error = 'Error updating expense: ' . $e->getMessage();
    }
}

/* ──────────────────────────────────────────────────────────────
   POST: DELETE EXPENSE
────────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    try {
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id=? AND company_id=?");
        $stmt->execute([(int) $_POST['expense_id'], $company_id]);
        $msg_success = 'Expense deleted successfully!';
    } catch (Exception $e) {
        $msg_error = 'Error deleting expense: ' . $e->getMessage();
    }
}

/* ──────────────────────────────────────────────────────────────
   FETCH FILTER PARAMS
────────────────────────────────────────────────────────────── */
$filter_cat   = $_GET['category']   ?? '';
$filter_from  = $_GET['date_from']  ?? '';
$filter_to    = $_GET['date_to']    ?? '';

/* ──────────────────────────────────────────────────────────────
   FETCH ALL EXPENSES (with optional filters)
────────────────────────────────────────────────────────────── */
try {
    $where  = ['e.company_id = ?'];
    $params = [$company_id];

    if ($filter_cat !== '') {
        $where[]  = 'e.category = ?';
        $params[] = $filter_cat;
    }
    if ($filter_from !== '') {
        $where[]  = 'e.expense_date >= ?';
        $params[] = $filter_from;
    }
    if ($filter_to !== '') {
        $where[]  = 'e.expense_date <= ?';
        $params[] = $filter_to;
    }

    $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt     = $pdo->prepare(
        "SELECT e.*, u.name AS added_by_name
         FROM expenses e
         LEFT JOIN users u ON e.added_by = u.id
         $whereSQL
         ORDER BY e.expense_date DESC, e.created_at DESC"
    );
    $stmt->execute($params);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $expenses  = [];
    $msg_error = 'Error fetching expenses: ' . $e->getMessage();
}

/* ──────────────────────────────────────────────────────────────
   STATS
────────────────────────────────────────────────────────────── */
try {
    $total_all   = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE company_id = $company_id")->fetchColumn();
    $total_month = $pdo->query(
        "SELECT COALESCE(SUM(amount),0) FROM expenses
         WHERE MONTH(expense_date)=MONTH(NOW()) AND YEAR(expense_date)=YEAR(NOW()) AND company_id = $company_id"
    )->fetchColumn();
    // Category counts
    $cat_rows = $pdo->query(
        "SELECT category, COUNT(*) as cnt FROM expenses WHERE company_id = $company_id GROUP BY category"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    $total_all   = 0;
    $total_month = 0;
    $cat_rows    = [];
}

// Filtered total
$filtered_total = array_sum(array_column($expenses, 'amount'));

/* ──────────────────────────────────────────────────────────────
   HELPER: category badge class
────────────────────────────────────────────────────────────── */
function catBadge(string $cat): string {
    return match($cat) {
        'Travel'    => 'badge-accent',
        'Food'      => 'badge-success',
        'Office'    => 'badge-warning',
        'Marketing' => 'badge-danger',
        'IT'        => 'badge-warning',
        default     => 'badge-accent',
    };
}
?>

<!-- ── Page Header ── -->
<div class="page-header">
    <div>
        <h1>Expenses</h1>
        <p>Track all business expenses and payments.</p>
    </div>
    <button class="btn btn-primary" onclick="openAddModal()">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:6px;vertical-align:middle;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        Add Expense
    </button>
</div>

<!-- ── Alerts ── -->
<?php if ($msg_success): ?>
<div class="alert alert-success" id="alertBox">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:6px;vertical-align:middle;"><polyline points="20 6 9 17 4 12"></polyline></svg>
    <?php echo htmlspecialchars($msg_success); ?>
</div>
<?php endif; ?>
<?php if ($msg_error): ?>
<div class="alert alert-danger" id="alertBox">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:6px;vertical-align:middle;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
    <?php echo htmlspecialchars($msg_error); ?>
</div>
<?php endif; ?>

<!-- ── Stats Grid ── -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); margin-bottom:2rem;">
    <div class="stat-card glass-card">
        <div class="stat-label">Total Expenses</div>
        <div class="stat-value" style="color:var(--danger);">₹<?php echo number_format($total_all, 0); ?></div>
        <div style="font-size:0.78rem; color:var(--text-muted); margin-top:4px;">All time</div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-label">This Month</div>
        <div class="stat-value" style="color:var(--warning);">₹<?php echo number_format($total_month, 0); ?></div>
        <div style="font-size:0.78rem; color:var(--text-muted); margin-top:4px;"><?php echo date('F Y'); ?></div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-label">By Category</div>
        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:10px;">
            <?php foreach ($categories as $c): ?>
                <span class="badge <?php echo catBadge($c); ?>" title="<?php echo $c; ?>">
                    <?php echo $c; ?>: <?php echo $cat_rows[$c] ?? 0; ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ── Filter Bar ── -->
<div class="glass-card filter-bar" style="padding:1.2rem 1.5rem; margin-bottom:1.5rem; display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
    <form method="GET" style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end; width:100%;">
        <div class="form-group" style="margin:0; flex:1; min-width:160px;">
            <label class="form-label">Category</label>
            <select name="category" class="form-control">
                <option value="">All Categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?php echo $c; ?>" <?php echo $filter_cat === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group" style="margin:0; flex:1; min-width:160px;">
            <label class="form-label">From Date</label>
            <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filter_from); ?>">
        </div>
        <div class="form-group" style="margin:0; flex:1; min-width:160px;">
            <label class="form-label">To Date</label>
            <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filter_to); ?>">
        </div>
        <div style="display:flex; gap:8px; align-items:flex-end;">
            <button type="submit" class="btn btn-primary">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:4px;vertical-align:middle;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                Filter
            </button>
            <?php if ($filter_cat || $filter_from || $filter_to): ?>
            <a href="expenses.php" class="btn glass-card" style="text-decoration:none;">Clear</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ── Expenses Table ── -->
<div class="glass-card" style="padding:1.5rem;">
    <!-- Table header with filtered total -->
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:0.5rem;">
        <h3 style="margin:0; font-size:1rem;">
            <?php if ($filter_cat || $filter_from || $filter_to): ?>
                Filtered Results <span style="color:var(--text-muted); font-weight:400; font-size:0.85rem;">(<?php echo count($expenses); ?> records)</span>
            <?php else: ?>
                All Expenses <span style="color:var(--text-muted); font-weight:400; font-size:0.85rem;">(<?php echo count($expenses); ?> records)</span>
            <?php endif; ?>
        </h3>
        <div style="font-size:0.95rem; font-weight:600;">
            Total: <span style="color:var(--danger); font-size:1.1rem;">₹<?php echo number_format($filtered_total, 0); ?></span>
        </div>
    </div>

    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Title</th>
                    <th>Category</th>
                    <th>Paid To</th>
                    <th>Mode</th>
                    <th>Amount</th>
                    <th>Date</th>
                    <th>Added By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="9" style="text-align:center; padding:3rem; color:var(--text-muted);">
                            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="display:block;margin:0 auto 1rem;opacity:0.4;"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                            No expenses found.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($expenses as $i => $e): ?>
                <tr>
                    <td style="color:var(--text-muted); font-size:0.8rem;"><?php echo $i + 1; ?></td>
                    <td><strong><?php echo htmlspecialchars($e['title']); ?></strong></td>
                    <td><span class="badge <?php echo catBadge($e['category']); ?>"><?php echo htmlspecialchars($e['category']); ?></span></td>
                    <td><?php echo htmlspecialchars($e['paid_to'] ?: '—'); ?></td>
                    <td>
                        <span class="badge badge-accent" style="text-transform:uppercase; font-size:0.7rem; letter-spacing:0.05em;">
                            <?php echo htmlspecialchars($payment_modes[$e['payment_mode']] ?? $e['payment_mode']); ?>
                        </span>
                    </td>
                    <td><strong style="color:var(--danger);">₹<?php echo number_format((float)$e['amount'], 0); ?></strong></td>
                    <td style="font-size:0.82rem;"><?php echo date('d M Y', strtotime($e['expense_date'])); ?></td>
                    <td style="font-size:0.82rem; color:var(--text-muted);"><?php echo htmlspecialchars($e['added_by_name'] ?? '—'); ?></td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <button class="btn btn-warning" style="padding:5px 10px; font-size:0.78rem;"
                                onclick='openEditModal(<?php echo htmlspecialchars(json_encode([
                                    "id"           => $e["id"],
                                    "title"        => $e["title"],
                                    "amount"       => $e["amount"],
                                    "category"     => $e["category"],
                                    "paid_to"      => $e["paid_to"],
                                    "payment_mode" => $e["payment_mode"],
                                    "expense_date" => $e["expense_date"],
                                ]), ENT_QUOTES); ?>)'>
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                Edit
                            </button>
                            <button class="btn btn-danger" style="padding:5px 10px; font-size:0.78rem;"
                                onclick="confirmDelete(<?php echo (int)$e['id']; ?>, '<?php echo addslashes(htmlspecialchars($e['title'])); ?>')">
                                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14H6L5 6"></path><path d="M10 11v6"></path><path d="M14 11v6"></path><path d="M9 6V4h6v2"></path></svg>
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     MODAL: ADD EXPENSE
══════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="add-expense-modal">
    <div class="modal-box" style="width:540px; max-width:95vw;">
        <div class="modal-header">
            <h3>
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:8px;vertical-align:middle;"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Add Expense
            </h3>
            <button class="modal-close" onclick="closeModal('add-expense-modal')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label class="form-label">Title <span style="color:var(--danger);">*</span></label>
                <input type="text" name="title" class="form-control" required placeholder="e.g. Office Rent, Flight Ticket">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Amount (₹) <span style="color:var(--danger);">*</span></label>
                    <input type="number" name="amount" class="form-control" step="0.01" min="0" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Category <span style="color:var(--danger);">*</span></label>
                    <select name="category" class="form-control" required>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?php echo $c; ?>"><?php echo $c; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Paid To</label>
                <input type="text" name="paid_to" class="form-control" placeholder="Vendor / Person name">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Payment Mode</label>
                    <select name="payment_mode" class="form-control">
                        <?php foreach ($payment_modes as $val => $lbl): ?>
                            <option value="<?php echo $val; ?>"><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Expense Date <span style="color:var(--danger);">*</span></label>
                    <input type="date" name="expense_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:1.5rem; padding-top:1rem; border-top:1px solid rgba(255,255,255,0.08);">
                <button type="button" class="btn glass-card" onclick="closeModal('add-expense-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:4px;vertical-align:middle;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    Save Expense
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     MODAL: EDIT EXPENSE
══════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="edit-expense-modal">
    <div class="modal-box" style="width:540px; max-width:95vw;">
        <div class="modal-header">
            <h3>
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:8px;vertical-align:middle;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                Edit Expense
            </h3>
            <button class="modal-close" onclick="closeModal('edit-expense-modal')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="expense_id" id="edit_id">

            <div class="form-group">
                <label class="form-label">Title <span style="color:var(--danger);">*</span></label>
                <input type="text" name="title" id="edit_title" class="form-control" required placeholder="e.g. Office Rent">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Amount (₹) <span style="color:var(--danger);">*</span></label>
                    <input type="number" name="amount" id="edit_amount" class="form-control" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <select name="category" id="edit_category" class="form-control">
                        <?php foreach ($categories as $c): ?>
                            <option value="<?php echo $c; ?>"><?php echo $c; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Paid To</label>
                <input type="text" name="paid_to" id="edit_paid_to" class="form-control" placeholder="Vendor / Person name">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Payment Mode</label>
                    <select name="payment_mode" id="edit_payment_mode" class="form-control">
                        <?php foreach ($payment_modes as $val => $lbl): ?>
                            <option value="<?php echo $val; ?>"><?php echo $lbl; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Expense Date <span style="color:var(--danger);">*</span></label>
                    <input type="date" name="expense_date" id="edit_expense_date" class="form-control" required>
                </div>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:1.5rem; padding-top:1rem; border-top:1px solid rgba(255,255,255,0.08);">
                <button type="button" class="btn glass-card" onclick="closeModal('edit-expense-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:4px;vertical-align:middle;"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v14a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                    Update Expense
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     MODAL: CONFIRM DELETE
══════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="delete-expense-modal">
    <div class="modal-box" style="width:420px; max-width:95vw;">
        <div class="modal-header">
            <h3 style="color:var(--danger);">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:8px;vertical-align:middle;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                Confirm Delete
            </h3>
            <button class="modal-close" onclick="closeModal('delete-expense-modal')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <p style="margin:1rem 0 1.5rem; color:var(--text-muted); line-height:1.6;">
            Are you sure you want to delete expense <strong id="delete_expense_name" style="color:var(--text-primary);"></strong>?
            <br>This action <strong>cannot be undone</strong>.
        </p>
        <form method="POST">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="expense_id" id="delete_id">
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn glass-card" onclick="closeModal('delete-expense-modal')">Cancel</button>
                <button type="submit" class="btn btn-danger">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:4px;vertical-align:middle;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6l-1 14H6L5 6"></path></svg>
                    Yes, Delete
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     JAVASCRIPT
══════════════════════════════════════════════════════════════ -->
<script>
/* ── Modal helpers ── */
function openModal(id) {
    document.getElementById(id).classList.add('open');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

/* Close modal when clicking overlay backdrop */
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            overlay.classList.remove('open');
        }
    });
});

/* ── Add modal ── */
function openAddModal() {
    openModal('add-expense-modal');
}

/* ── Edit modal ── */
function openEditModal(data) {
    document.getElementById('edit_id').value           = data.id;
    document.getElementById('edit_title').value        = data.title;
    document.getElementById('edit_amount').value       = data.amount;
    document.getElementById('edit_paid_to').value      = data.paid_to || '';
    document.getElementById('edit_expense_date').value = data.expense_date
        ? data.expense_date.substring(0, 10)
        : '';

    // Set category select
    var catSel = document.getElementById('edit_category');
    for (var i = 0; i < catSel.options.length; i++) {
        catSel.options[i].selected = (catSel.options[i].value === data.category);
    }

    // Set payment_mode select
    var modeSel = document.getElementById('edit_payment_mode');
    for (var j = 0; j < modeSel.options.length; j++) {
        modeSel.options[j].selected = (modeSel.options[j].value === data.payment_mode);
    }

    openModal('edit-expense-modal');
}

/* ── Delete confirm modal ── */
function confirmDelete(id, name) {
    document.getElementById('delete_id').value        = id;
    document.getElementById('delete_expense_name').textContent = name;
    openModal('delete-expense-modal');
}

/* ── Auto-dismiss alerts after 5s ── */
setTimeout(function() {
    var alert = document.getElementById('alertBox');
    if (alert) {
        alert.style.transition = 'opacity 0.5s';
        alert.style.opacity    = '0';
        setTimeout(function() { alert.remove(); }, 500);
    }
}, 5000);
</script>

<?php include_once '../../includes/footer.php'; ?>
