<?php
include_once '../../includes/header.php';

// ─── Handle POST Actions ────────────────────────────────────────────────────

$success = '';
$error   = '';

// --- CREATE Invoice ---
$company_id = $_SESSION['company_id'] ?? 1;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invoice'])) {
    try {
        $inv_no   = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), 0, 4));
        $client   = trim($_POST['client_name']);
        $email    = trim($_POST['client_email']);
        $subtotal = (float) $_POST['subtotal'];
        $gst_pct  = (float) $_POST['gst_percent'];
        $gst_amt  = round($subtotal * $gst_pct / 100, 2);
        $total    = round($subtotal + $gst_amt, 2);
        $due_date = $_POST['due_date'];
        $status   = $_POST['status'] ?? 'draft';
        $items    = json_encode([]);   // items column kept for compatibility

        if (empty($client)) throw new Exception('Client name is required.');
        if (empty($due_date)) throw new Exception('Due date is required.');
        if ($subtotal < 0) throw new Exception('Subtotal cannot be negative.');

        $stmt = $pdo->prepare(
            "INSERT INTO invoices
                (company_id, invoice_number, client_name, client_email, items, subtotal,
                 gst_percent, gst_amount, total_amount, status, due_date, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $company_id, $inv_no, $client, $email, $items,
            $subtotal, $gst_pct, $gst_amt, $total,
            $status, $due_date, $_SESSION['user_id']
        ]);
        $success = "Invoice <strong>{$inv_no}</strong> created successfully!";
    } catch (Exception $e) {
        $error = 'Error creating invoice: ' . htmlspecialchars($e->getMessage());
    }
}

// --- EDIT Invoice ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_invoice'])) {
    try {
        $id       = (int) $_POST['inv_id'];
        $client   = trim($_POST['client_name']);
        $email    = trim($_POST['client_email']);
        $subtotal = (float) $_POST['subtotal'];
        $gst_pct  = (float) $_POST['gst_percent'];
        $gst_amt  = round($subtotal * $gst_pct / 100, 2);
        $total    = round($subtotal + $gst_amt, 2);
        $due_date = $_POST['due_date'];
        $status   = $_POST['status'] ?? 'draft';

        if (empty($client)) throw new Exception('Client name is required.');
        if (empty($due_date)) throw new Exception('Due date is required.');
        if ($subtotal < 0) throw new Exception('Subtotal cannot be negative.');

        $stmt = $pdo->prepare(
            "UPDATE invoices
             SET client_name=?, client_email=?, subtotal=?, gst_percent=?,
                 gst_amount=?, total_amount=?, status=?, due_date=?
             WHERE id=? AND company_id=?"
        );
        $stmt->execute([
            $client, $email, $subtotal, $gst_pct,
            $gst_amt, $total, $status, $due_date, $id, $company_id
        ]);
        $success = 'Invoice updated successfully!';
    } catch (Exception $e) {
        $error = 'Error updating invoice: ' . htmlspecialchars($e->getMessage());
    }
}

// --- DELETE Invoice ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_invoice'])) {
    try {
        $id = (int) $_POST['inv_id'];
        $pdo->prepare("DELETE FROM invoices WHERE id=? AND company_id=?")->execute([$id, $company_id]);
        $success = 'Invoice deleted successfully!';
    } catch (Exception $e) {
        $error = 'Error deleting invoice: ' . htmlspecialchars($e->getMessage());
    }
}

// ─── Fetch Data ─────────────────────────────────────────────────────────────

try {
    $invoices = $pdo->query(
        "SELECT i.*, u.name AS created_by_name
         FROM invoices i
         LEFT JOIN users u ON i.created_by = u.id
         WHERE i.company_id = $company_id
         ORDER BY i.created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $invoices = [];
    $error = 'Could not load invoices: ' . htmlspecialchars($e->getMessage());
}

// Stats
try {
    $total_invoices = count($invoices);
    $paid_sum       = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE status='paid' AND company_id=$company_id")->fetchColumn();
    $pending_sum    = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE status IN ('draft','sent') AND company_id=$company_id")->fetchColumn();
    $overdue_count  = $pdo->query("SELECT COUNT(*) FROM invoices WHERE status='overdue' AND company_id=$company_id")->fetchColumn();
} catch (Exception $e) {
    $paid_sum = $pending_sum = $overdue_count = 0;
}

// Status badge map
$badge_map = [
    'draft'     => 'badge-warning',
    'sent'      => 'badge-accent',
    'paid'      => 'badge-success',
    'overdue'   => 'badge-danger',
    'cancelled' => 'badge-danger',
];
$statuses = ['draft', 'sent', 'paid', 'overdue', 'cancelled'];
?>

<!-- ══════════════════════════════════════════════════════════════
     PAGE HEADER
═══════════════════════════════════════════════════════════════ -->
<div class="page-header">
    <div>
        <h1>Invoices</h1>
        <p>Create and manage client invoices with GST calculation.</p>
    </div>
    <button class="btn btn-primary" onclick="openCreateModal()">+ New Invoice</button>
</div>

<!-- Alerts -->
<?php if ($success): ?>
    <div class="alert alert-success" id="alert-box">
        <?php echo $success; ?>
        <button onclick="document.getElementById('alert-box').remove()"
                style="float:right; background:none; border:none; cursor:pointer; font-size:1.1rem; color:inherit;">×</button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger" id="alert-box">
        <?php echo $error; ?>
        <button onclick="document.getElementById('alert-box').remove()"
                style="float:right; background:none; border:none; cursor:pointer; font-size:1.1rem; color:inherit;">×</button>
    </div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════════
     STATS GRID
═══════════════════════════════════════════════════════════════ -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:2rem;">
    <div class="stat-card glass-card">
        <div class="stat-label">Total Invoices</div>
        <div class="stat-value"><?php echo $total_invoices; ?></div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-label">Paid</div>
        <div class="stat-value" style="color:var(--success);">
            ₹<?php echo number_format($paid_sum, 0); ?>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-label">Pending (Draft + Sent)</div>
        <div class="stat-value" style="color:var(--warning);">
            ₹<?php echo number_format($pending_sum, 0); ?>
        </div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-label">Overdue</div>
        <div class="stat-value" style="color:var(--danger);"><?php echo $overdue_count; ?></div>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     FILTER BAR
═══════════════════════════════════════════════════════════════ -->
<div class="filter-bar" style="margin-bottom:1.5rem; display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
    <input type="text" id="search-input" class="form-control" placeholder="Search by invoice # or client…"
           style="max-width:300px;" oninput="filterTable()">
    <select id="status-filter" class="form-control" style="max-width:180px;" onchange="filterTable()">
        <option value="">All Statuses</option>
        <?php foreach ($statuses as $s): ?>
            <option value="<?php echo $s; ?>"><?php echo ucfirst($s); ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn glass-card" onclick="clearFilters()" style="font-size:0.85rem; padding:6px 14px;">
        Clear Filters
    </button>
</div>

<!-- ══════════════════════════════════════════════════════════════
     INVOICES TABLE
═══════════════════════════════════════════════════════════════ -->
<div class="glass-card" style="padding:1.5rem;">
    <div class="data-table-container">
        <table id="invoices-table">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Client</th>
                    <th>Subtotal</th>
                    <th>GST</th>
                    <th>Total</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th style="text-align:center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($invoices)): ?>
                    <tr id="no-data-row">
                        <td colspan="9" style="text-align:center; padding:3rem; color:var(--text-muted);">
                            No invoices found. Click <strong>+ New Invoice</strong> to create one.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($invoices as $inv):
                    $bc = $badge_map[$inv['status']] ?? 'badge-warning';
                    $due_fmt = $inv['due_date'] ? date('d M Y', strtotime($inv['due_date'])) : '—';
                    // JSON encode row data for edit modal
                    $row_data = htmlspecialchars(json_encode([
                        'id'            => $inv['id'],
                        'client_name'   => $inv['client_name'],
                        'client_email'  => $inv['client_email'],
                        'subtotal'      => $inv['subtotal'],
                        'gst_percent'   => $inv['gst_percent'],
                        'due_date'      => $inv['due_date'],
                        'status'        => $inv['status'],
                        'invoice_number'=> $inv['invoice_number'],
                    ]), ENT_QUOTES);
                ?>
                <tr data-status="<?php echo $inv['status']; ?>"
                    data-search="<?php echo strtolower(htmlspecialchars($inv['invoice_number'] . ' ' . $inv['client_name'])); ?>">
                    <td>
                        <strong style="color:var(--primary); font-family:monospace;">
                            <?php echo htmlspecialchars($inv['invoice_number']); ?>
                        </strong>
                    </td>
                    <td>
                        <div style="font-weight:600;"><?php echo htmlspecialchars($inv['client_name']); ?></div>
                        <div style="font-size:0.75rem; color:var(--text-muted);">
                            <?php echo htmlspecialchars($inv['client_email']); ?>
                        </div>
                    </td>
                    <td>₹<?php echo number_format($inv['subtotal'], 2); ?></td>
                    <td>
                        ₹<?php echo number_format($inv['gst_amount'], 2); ?>
                        <span style="color:var(--text-muted); font-size:0.75rem;">
                            (<?php echo $inv['gst_percent']; ?>%)
                        </span>
                    </td>
                    <td><strong>₹<?php echo number_format($inv['total_amount'], 2); ?></strong></td>
                    <td style="font-size:0.85rem;"><?php echo $due_fmt; ?></td>
                    <td>
                        <span class="badge <?php echo $bc; ?>">
                            <?php echo strtoupper($inv['status']); ?>
                        </span>
                    </td>
                    <td style="font-size:0.8rem; color:var(--text-muted);">
                        <?php echo htmlspecialchars($inv['created_by_name'] ?? '—'); ?>
                    </td>
                    <td style="text-align:center; white-space:nowrap;">
                        <!-- Edit -->
                        <button class="btn btn-warning"
                                style="padding:4px 10px; font-size:0.78rem; margin-right:4px;"
                                onclick='openEditModal(<?php echo $row_data; ?>)'>
                            ✏ Edit
                        </button>
                        <!-- Delete -->
                        <button class="btn btn-danger"
                                style="padding:4px 10px; font-size:0.78rem;"
                                onclick="confirmDelete(<?php echo (int)$inv['id']; ?>, '<?php echo htmlspecialchars(addslashes($inv['invoice_number'])); ?>')">
                            🗑 Delete
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     CREATE INVOICE MODAL
═══════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="create-modal">
    <div class="modal-box" style="width:660px; max-width:95vw;">
        <div class="modal-header">
            <h3>Create New Invoice</h3>
            <button class="modal-close" onclick="closeModal('create-modal')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form method="POST" id="create-form">
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Client Name <span style="color:var(--danger);">*</span></label>
                    <input type="text" name="client_name" class="form-control" placeholder="e.g. Acme Corp" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Client Email</label>
                    <input type="email" name="client_email" class="form-control" placeholder="client@example.com">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Subtotal (₹) <span style="color:var(--danger);">*</span></label>
                    <input type="number" name="subtotal" id="c-subtotal" class="form-control"
                           placeholder="0.00" step="0.01" min="0" required oninput="calcCreate()">
                </div>
                <div class="form-group">
                    <label class="form-label">GST %</label>
                    <input type="number" name="gst_percent" id="c-gst-pct" class="form-control"
                           value="18" step="0.01" min="0" oninput="calcCreate()">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo $s === 'draft' ? 'selected' : ''; ?>>
                                <?php echo ucfirst($s); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:0.5rem;">
                <div class="form-group">
                    <label class="form-label">Due Date <span style="color:var(--danger);">*</span></label>
                    <input type="date" name="due_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Calculated Total</label>
                    <div id="c-total-display"
                         style="padding:0.55rem 0.9rem; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12);
                                border-radius:8px; font-weight:700; font-size:1rem; color:var(--primary);">
                        ₹0.00
                    </div>
                </div>
            </div>

            <!-- Hidden computed fields -->
            <input type="hidden" name="gst_amount" id="c-gst-amt">
            <input type="hidden" name="total_amount" id="c-total">

            <div style="background:rgba(255,255,255,0.04); border-radius:8px; padding:0.75rem; margin-bottom:1rem; font-size:0.85rem;">
                <span style="color:var(--text-muted);">GST Amount:</span>
                <strong id="c-gst-amt-display" style="margin-left:6px;">₹0.00</strong>
                <span style="margin-left:1.5rem; color:var(--text-muted);">Total:</span>
                <strong id="c-total-display2" style="margin-left:6px; color:var(--success);">₹0.00</strong>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:1rem;">
                <button type="button" class="btn glass-card" onclick="closeModal('create-modal')">Cancel</button>
                <button type="submit" name="create_invoice" class="btn btn-primary">Create Invoice</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     EDIT INVOICE MODAL
═══════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="edit-modal">
    <div class="modal-box" style="width:660px; max-width:95vw;">
        <div class="modal-header">
            <h3>Edit Invoice — <span id="edit-inv-number" style="color:var(--primary); font-family:monospace;"></span></h3>
            <button class="modal-close" onclick="closeModal('edit-modal')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form method="POST" id="edit-form">
            <input type="hidden" name="inv_id" id="edit-inv-id">

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Client Name <span style="color:var(--danger);">*</span></label>
                    <input type="text" name="client_name" id="edit-client-name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Client Email</label>
                    <input type="email" name="client_email" id="edit-client-email" class="form-control">
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Subtotal (₹) <span style="color:var(--danger);">*</span></label>
                    <input type="number" name="subtotal" id="e-subtotal" class="form-control"
                           step="0.01" min="0" required oninput="calcEdit()">
                </div>
                <div class="form-group">
                    <label class="form-label">GST %</label>
                    <input type="number" name="gst_percent" id="e-gst-pct" class="form-control"
                           step="0.01" min="0" oninput="calcEdit()">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="edit-status" class="form-control">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?php echo $s; ?>"><?php echo ucfirst($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:0.5rem;">
                <div class="form-group">
                    <label class="form-label">Due Date <span style="color:var(--danger);">*</span></label>
                    <input type="date" name="due_date" id="edit-due-date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Calculated Total</label>
                    <div id="e-total-display"
                         style="padding:0.55rem 0.9rem; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.12);
                                border-radius:8px; font-weight:700; font-size:1rem; color:var(--primary);">
                        ₹0.00
                    </div>
                </div>
            </div>

            <!-- Hidden computed fields -->
            <input type="hidden" name="gst_amount" id="e-gst-amt">
            <input type="hidden" name="total_amount" id="e-total">

            <div style="background:rgba(255,255,255,0.04); border-radius:8px; padding:0.75rem; margin-bottom:1rem; font-size:0.85rem;">
                <span style="color:var(--text-muted);">GST Amount:</span>
                <strong id="e-gst-amt-display" style="margin-left:6px;">₹0.00</strong>
                <span style="margin-left:1.5rem; color:var(--text-muted);">Total:</span>
                <strong id="e-total-display2" style="margin-left:6px; color:var(--success);">₹0.00</strong>
            </div>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:1rem;">
                <button type="button" class="btn glass-card" onclick="closeModal('edit-modal')">Cancel</button>
                <button type="submit" name="edit_invoice" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     DELETE CONFIRM MODAL
═══════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="delete-modal">
    <div class="modal-box" style="width:420px; max-width:95vw;">
        <div class="modal-header">
            <h3>Confirm Delete</h3>
            <button class="modal-close" onclick="closeModal('delete-modal')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <p style="margin:1rem 0; color:var(--text-muted); line-height:1.6;">
            Are you sure you want to delete invoice
            <strong id="delete-inv-number" style="color:var(--danger);"></strong>?
            This action cannot be undone.
        </p>
        <form method="POST" id="delete-form">
            <input type="hidden" name="inv_id" id="delete-inv-id">
            <div style="display:flex; gap:10px; justify-content:flex-end;">
                <button type="button" class="btn glass-card" onclick="closeModal('delete-modal')">Cancel</button>
                <button type="submit" name="delete_invoice" class="btn btn-danger">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════════════════ -->
<script>
// ── Modal Helpers ──────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Close modal on overlay click
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) overlay.classList.remove('open');
    });
});

// ── Create Modal ───────────────────────────────────────────────
function openCreateModal() {
    document.getElementById('create-form').reset();
    // Reset displays
    document.getElementById('c-total-display').textContent  = '₹0.00';
    document.getElementById('c-gst-amt-display').textContent = '₹0.00';
    document.getElementById('c-total-display2').textContent  = '₹0.00';
    document.getElementById('c-gst-amt').value = '0';
    document.getElementById('c-total').value   = '0';
    openModal('create-modal');
}

function calcCreate() {
    var sub     = parseFloat(document.getElementById('c-subtotal').value)  || 0;
    var gstPct  = parseFloat(document.getElementById('c-gst-pct').value)   || 0;
    var gstAmt  = +(sub * gstPct / 100).toFixed(2);
    var total   = +(sub + gstAmt).toFixed(2);

    document.getElementById('c-gst-amt').value          = gstAmt;
    document.getElementById('c-total').value            = total;
    document.getElementById('c-total-display').textContent  = '₹' + formatNum(total);
    document.getElementById('c-gst-amt-display').textContent = '₹' + formatNum(gstAmt);
    document.getElementById('c-total-display2').textContent  = '₹' + formatNum(total);
}

// ── Edit Modal ─────────────────────────────────────────────────
function openEditModal(data) {
    document.getElementById('edit-inv-id').value        = data.id;
    document.getElementById('edit-inv-number').textContent = data.invoice_number;
    document.getElementById('edit-client-name').value   = data.client_name;
    document.getElementById('edit-client-email').value  = data.client_email;
    document.getElementById('e-subtotal').value         = data.subtotal;
    document.getElementById('e-gst-pct').value          = data.gst_percent;
    document.getElementById('edit-due-date').value      = data.due_date;
    document.getElementById('edit-status').value        = data.status;

    // Trigger calculation display
    calcEdit();
    openModal('edit-modal');
}

function calcEdit() {
    var sub     = parseFloat(document.getElementById('e-subtotal').value) || 0;
    var gstPct  = parseFloat(document.getElementById('e-gst-pct').value)  || 0;
    var gstAmt  = +(sub * gstPct / 100).toFixed(2);
    var total   = +(sub + gstAmt).toFixed(2);

    document.getElementById('e-gst-amt').value          = gstAmt;
    document.getElementById('e-total').value            = total;
    document.getElementById('e-total-display').textContent  = '₹' + formatNum(total);
    document.getElementById('e-gst-amt-display').textContent = '₹' + formatNum(gstAmt);
    document.getElementById('e-total-display2').textContent  = '₹' + formatNum(total);
}

// ── Delete Modal ───────────────────────────────────────────────
function confirmDelete(id, invNumber) {
    document.getElementById('delete-inv-id').value          = id;
    document.getElementById('delete-inv-number').textContent = invNumber;
    openModal('delete-modal');
}

// ── Filter / Search ─────────────────────────────────────────────
function filterTable() {
    var query  = document.getElementById('search-input').value.toLowerCase().trim();
    var status = document.getElementById('status-filter').value;
    var rows   = document.querySelectorAll('#invoices-table tbody tr[data-status]');
    var visible = 0;

    rows.forEach(function(row) {
        var searchText  = row.getAttribute('data-search') || '';
        var rowStatus   = row.getAttribute('data-status') || '';
        var matchSearch = !query  || searchText.includes(query);
        var matchStatus = !status || rowStatus === status;
        var show = matchSearch && matchStatus;
        row.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    // Show/hide no-data row
    var noDataRow = document.getElementById('no-data-row');
    if (noDataRow) noDataRow.style.display = visible === 0 ? '' : 'none';
}

function clearFilters() {
    document.getElementById('search-input').value = '';
    document.getElementById('status-filter').value = '';
    filterTable();
}

// ── Utility ────────────────────────────────────────────────────
function formatNum(n) {
    return n.toLocaleString('en-IN', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

// Auto-dismiss alerts after 5 seconds
(function() {
    var alert = document.getElementById('alert-box');
    if (alert) setTimeout(function() { alert.style.opacity='0'; setTimeout(function(){alert.remove();}, 400); }, 5000);
})();
</script>

<?php include_once '../../includes/footer.php'; ?>
