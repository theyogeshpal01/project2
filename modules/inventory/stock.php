<?php
include_once '../../includes/header.php';

// ── Ensure 'category' column exists ───────────────────────────────────────────
try {
    $pdo->exec("ALTER TABLE inventory ADD COLUMN IF NOT EXISTS category VARCHAR(100) DEFAULT 'General'");
} catch (Exception $e) {
    // Column may already exist or DB doesn't support IF NOT EXISTS – silently continue
}

// ── Ensure 'cost_price' column exists ─────────────────────────────────────────
try {
    $pdo->exec("ALTER TABLE inventory ADD COLUMN IF NOT EXISTS cost_price DECIMAL(10,2) DEFAULT 0.00");
} catch (Exception $e) { /* silently continue */ }

$company_id = $_SESSION['company_id'] ?? 1;

// ── Handle DELETE ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    try {
        $del_id = (int)$_POST['delete_id'];
        $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = ? AND company_id = ?");
        $stmt->execute([$del_id, $company_id]);
        $success = "Item deleted successfully.";
    } catch (Exception $e) {
        $error = "Delete failed: " . $e->getMessage();
    }
}

// ── Handle EDIT ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_item'])) {
    try {
        $stmt = $pdo->prepare("UPDATE inventory SET item_name=?, sku=?, category=?, total_qty=?, available_qty=?, cost_price=? WHERE id=? AND company_id=?");
        $stmt->execute([
            trim($_POST['item_name']),
            trim($_POST['sku']),
            trim($_POST['category'] ?: 'General'),
            (int)$_POST['total_qty'],
            (int)$_POST['available_qty'],
            (float)$_POST['cost_price'],
            (int)$_POST['edit_id'],
            $company_id
        ]);
        $success = "Item updated successfully.";
    } catch (Exception $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

// ── Handle ADD ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO inventory (company_id, item_name, sku, category, total_qty, available_qty, cost_price) VALUES (?,?,?,?,?,?,?)");
        $stmt->execute([
            $company_id,
            trim($_POST['item_name']),
            trim($_POST['sku']),
            trim($_POST['category'] ?: 'General'),
            (int)$_POST['total_qty'],
            (int)$_POST['available_qty'],
            (float)$_POST['cost_price']
        ]);
        $success = "Item added successfully.";
    } catch (Exception $e) {
        $error = "Add failed: " . $e->getMessage();
    }
}

// ── Fetch inventory ───────────────────────────────────────────────────────────
try {
    $inventory   = $pdo->query("SELECT * FROM inventory WHERE company_id = $company_id ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $total_items = count($inventory);
    $total_qty   = array_sum(array_column($inventory, 'available_qty'));
    $low_stock   = count(array_filter($inventory, fn($i) => (int)($i['available_qty'] ?? 0) < 5));
    $total_value = array_sum(array_map(fn($i) => (float)($i['available_qty'] ?? 0) * (float)($i['cost_price'] ?? 0), $inventory));
} catch (Exception $e) {
    $inventory = [];
    $total_items = $total_qty = $low_stock = $total_value = 0;
    $error = "Could not load inventory: " . $e->getMessage();
}
?>

<!-- ── Page Header ─────────────────────────────────────────────────────────── -->
<div class="page-header">
    <div class="page-header-left">
        <div class="page-header-icon" style="background:rgba(8,145,178,0.1);color:var(--accent);">
            <svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"></path>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>
        </div>
        <div>
            <h1>Inventory Management</h1>
            <p>Track stock levels, manage items, and monitor low inventory</p>
        </div>
    </div>
    <div class="page-header-actions">
        <button class="btn btn-primary" onclick="openAddModal()">
            <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add Item
        </button>
    </div>
</div>

<!-- ── Alerts ──────────────────────────────────────────────────────────────── -->
<?php if (isset($success)): ?>
<div class="alert alert-success" style="margin-bottom:1rem;"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
<div class="alert alert-danger" style="margin-bottom:1rem;"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<!-- ── Stat Cards ──────────────────────────────────────────────────────────── -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:1.5rem;">

    <div class="stat-card glass-card">
        <div class="stat-card-text">
            <div class="stat-label">Total Items</div>
            <div class="stat-value"><?php echo $total_items; ?></div>
        </div>
        <div class="stat-card-icon" style="background:rgba(37,99,235,0.08);color:var(--primary);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z"></path>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>
        </div>
    </div>

    <div class="stat-card glass-card">
        <div class="stat-card-text">
            <div class="stat-label">Total Qty Available</div>
            <div class="stat-value" style="color:var(--success);"><?php echo number_format($total_qty); ?></div>
        </div>
        <div class="stat-card-icon" style="background:rgba(16,185,129,0.08);color:var(--success);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"></path>
            </svg>
        </div>
    </div>

    <div class="stat-card glass-card">
        <div class="stat-card-text">
            <div class="stat-label">Low Stock Items</div>
            <div class="stat-value" style="color:var(--danger);"><?php echo $low_stock; ?></div>
        </div>
        <div class="stat-card-icon" style="background:rgba(239,68,68,0.08);color:var(--danger);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="12"></line>
                <line x1="12" y1="16" x2="12.01" y2="16"></line>
            </svg>
        </div>
    </div>

    <div class="stat-card glass-card">
        <div class="stat-card-text">
            <div class="stat-label">Total Stock Value</div>
            <div class="stat-value" style="color:var(--accent);">₹<?php echo number_format($total_value, 2); ?></div>
        </div>
        <div class="stat-card-icon" style="background:rgba(8,145,178,0.08);color:var(--accent);">
            <svg fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <line x1="12" y1="1" x2="12" y2="23"></line>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
            </svg>
        </div>
    </div>

</div>

<!-- ── Filter Bar ──────────────────────────────────────────────────────────── -->
<div class="filter-bar glass-card" style="display:flex;gap:1rem;align-items:center;margin-bottom:1.2rem;padding:0.9rem 1.2rem;flex-wrap:wrap;">
    <input type="text" id="searchInput" class="form-control" placeholder="Search by name or category…"
           style="max-width:320px;" oninput="filterTable()">
    <select id="categoryFilter" class="form-control" style="max-width:200px;" onchange="filterTable()">
        <option value="">All Categories</option>
        <?php
        $cats = array_unique(array_column($inventory, 'category'));
        sort($cats);
        foreach ($cats as $cat): ?>
            <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat ?: 'General'); ?></option>
        <?php endforeach; ?>
    </select>
    <select id="stockFilter" class="form-control" style="max-width:180px;" onchange="filterTable()">
        <option value="">All Stock Levels</option>
        <option value="low">Low Stock (&lt; 5)</option>
        <option value="ok">In Stock (≥ 5)</option>
    </select>
    <button class="btn" onclick="clearFilters()" style="margin-left:auto;">Clear Filters</button>
</div>

<!-- ── Inventory Table ─────────────────────────────────────────────────────── -->
<div class="glass-card table-card">
    <div class="table-header" style="padding:1rem 1.2rem 0.5rem;">
        <div>
            <h3 style="font-size:0.95rem;font-weight:700;">Inventory List</h3>
            <p id="rowCount" style="font-size:0.78rem;color:var(--text-muted);margin-top:2px;">
                Showing <?php echo $total_items; ?> item<?php echo $total_items !== 1 ? 's' : ''; ?>
            </p>
        </div>
    </div>

    <div class="data-table-container">
        <table id="inventoryTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Name</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th>Total Qty</th>
                    <th>Available Qty</th>
                    <th>Cost Price</th>
                    <th>Stock Status</th>
                    <th>Added On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="inventoryBody">
                <?php if (empty($inventory)): ?>
                    <tr id="emptyRow">
                        <td colspan="10" style="text-align:center;padding:3rem;color:var(--text-muted);">
                            No inventory items found. Click <strong>Add Item</strong> to get started.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($inventory as $idx => $item):
                    $avail     = (int)($item['available_qty'] ?? 0);
                    $is_low    = $avail < 5;
                    $badge_cls = $avail < 1 ? 'badge-danger' : ($is_low ? 'badge-warning' : 'badge-success');
                    $badge_lbl = $avail < 1 ? 'Out of Stock' : ($is_low ? 'Low Stock' : 'In Stock');
                    $item_json = htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8');
                ?>
                <tr class="inv-row"
                    data-name="<?php echo htmlspecialchars(strtolower($item['item_name'])); ?>"
                    data-category="<?php echo htmlspecialchars(strtolower($item['category'] ?? '')); ?>"
                    data-stock="<?php echo $is_low ? 'low' : 'ok'; ?>">
                    <td style="font-family:monospace;color:var(--text-muted);font-size:0.82rem;"><?php echo $idx + 1; ?></td>
                    <td style="font-weight:600;"><?php echo htmlspecialchars($item['item_name']); ?>
                        <?php if ($is_low): ?>
                            <span class="badge badge-warning" style="margin-left:6px;font-size:0.65rem;">LOW STOCK</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-family:monospace;font-size:0.82rem;"><?php echo htmlspecialchars($item['sku'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($item['category'] ?? 'General'); ?></td>
                    <td style="font-weight:600;"><?php echo number_format((int)($item['total_qty'] ?? 0)); ?></td>
                    <td style="font-weight:700;color:<?php echo $avail < 1 ? 'var(--danger)' : ($is_low ? 'var(--warning)' : 'var(--success)'); ?>;">
                        <?php echo number_format($avail); ?>
                    </td>
                    <td>₹<?php echo number_format((float)($item['cost_price'] ?? 0), 2); ?></td>
                    <td><span class="badge <?php echo $badge_cls; ?>"><?php echo $badge_lbl; ?></span></td>
                    <td style="color:var(--text-muted);font-size:0.8rem;">
                        <?php echo isset($item['created_at']) ? date('d M Y', strtotime($item['created_at'])) : '—'; ?>
                    </td>
                    <td>
                        <div style="display:flex;gap:6px;">
                            <!-- Edit Button -->
                            <button class="btn btn-sm btn-warning" title="Edit"
                                    style="padding:0.35rem 0.6rem;"
                                    data-item="<?php echo $item_json; ?>"
                                    onclick="openEditModal(this)">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                </svg>
                            </button>
                            <!-- Delete Button -->
                            <button class="btn btn-sm btn-danger" title="Delete"
                                    style="padding:0.35rem 0.6rem;"
                                    onclick="confirmDelete(<?php echo (int)$item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['item_name'])); ?>')">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <polyline points="3 6 5 6 21 6"></polyline>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!--  ADD ITEM MODAL                                                           -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box" style="width:520px;max-width:95vw;">
        <div class="modal-header">
            <h3 style="font-size:1rem;font-weight:700;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;">
                    <line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add New Item
            </h3>
            <button class="modal-close" onclick="closeModal('addModal')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <form method="POST" autocomplete="off">
            <div class="form-group">
                <label class="form-label">Item Name <span style="color:var(--danger);">*</span></label>
                <input type="text" name="item_name" class="form-control" required placeholder="e.g. Dell Laptop">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">SKU</label>
                    <input type="text" name="sku" class="form-control" placeholder="e.g. LAP-001">
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <input type="text" name="category" class="form-control" placeholder="e.g. Electronics">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Total Qty <span style="color:var(--danger);">*</span></label>
                    <input type="number" name="total_qty" class="form-control" required min="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Available Qty <span style="color:var(--danger);">*</span></label>
                    <input type="number" name="available_qty" class="form-control" required min="0" value="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Cost Price (₹)</label>
                    <input type="number" name="cost_price" class="form-control" min="0" step="0.01" value="0.00" placeholder="0.00">
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:0.5rem;">
                <button type="button" class="btn" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" name="add_item" class="btn btn-primary">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:4px;">
                        <line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    Add Item
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!--  EDIT ITEM MODAL                                                          -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box" style="width:520px;max-width:95vw;">
        <div class="modal-header">
            <h3 style="font-size:1rem;font-weight:700;">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                Edit Item
            </h3>
            <button class="modal-close" onclick="closeModal('editModal')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <form method="POST" autocomplete="off">
            <input type="hidden" name="edit_id" id="editId">
            <div class="form-group">
                <label class="form-label">Item Name <span style="color:var(--danger);">*</span></label>
                <input type="text" name="item_name" id="editItemName" class="form-control" required>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">SKU</label>
                    <input type="text" name="sku" id="editSku" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <input type="text" name="category" id="editCategory" class="form-control">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Total Qty <span style="color:var(--danger);">*</span></label>
                    <input type="number" name="total_qty" id="editTotalQty" class="form-control" required min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Available Qty <span style="color:var(--danger);">*</span></label>
                    <input type="number" name="available_qty" id="editAvailQty" class="form-control" required min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">Cost Price (₹)</label>
                    <input type="number" name="cost_price" id="editCostPrice" class="form-control" min="0" step="0.01">
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:0.5rem;">
                <button type="button" class="btn" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" name="edit_item" class="btn btn-primary">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:4px;">
                        <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                        <polyline points="17 21 17 13 7 13 7 21"></polyline>
                        <polyline points="7 3 7 8 15 8"></polyline>
                    </svg>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ══════════════════════════════════════════════════════════════════════════ -->
<!--  DELETE CONFIRMATION MODAL                                                -->
<!-- ══════════════════════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box" style="width:420px;max-width:95vw;">
        <div class="modal-header">
            <h3 style="font-size:1rem;font-weight:700;color:var(--danger);">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle;margin-right:6px;">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"></path>
                </svg>
                Confirm Delete
            </h3>
            <button class="modal-close" onclick="closeModal('deleteModal')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <p style="margin-bottom:1.5rem;color:var(--text-muted);">
            Are you sure you want to delete <strong id="deleteItemName" style="color:var(--text);"></strong>?
            This action <strong>cannot be undone</strong>.
        </p>
        <form method="POST" id="deleteForm">
            <input type="hidden" name="delete_id" id="deleteId">
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" name="delete_item" class="btn btn-danger">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<!-- ── JavaScript ──────────────────────────────────────────────────────────── -->
<script>
// ── Modal helpers ──────────────────────────────────────────────────────────
function openModal(id)  { document.getElementById(id).classList.add('open');    }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// Close modals when clicking the dark overlay backdrop
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});

// ── Open Add Modal ────────────────────────────────────────────────────────
function openAddModal() { openModal('addModal'); }

// ── Open Edit Modal (pre-fill with item data) ─────────────────────────────
function openEditModal(btn) {
    try {
        var item = JSON.parse(btn.getAttribute('data-item'));
        document.getElementById('editId').value        = item.id        || '';
        document.getElementById('editItemName').value  = item.item_name || '';
        document.getElementById('editSku').value       = item.sku       || '';
        document.getElementById('editCategory').value  = item.category  || '';
        document.getElementById('editTotalQty').value  = item.total_qty || 0;
        document.getElementById('editAvailQty').value  = item.available_qty || 0;
        document.getElementById('editCostPrice').value = item.cost_price || '0.00';
        openModal('editModal');
    } catch(e) {
        alert('Could not load item data. Please try again.');
        console.error(e);
    }
}

// ── Open Delete Confirmation Modal ────────────────────────────────────────
function confirmDelete(id, name) {
    document.getElementById('deleteId').value      = id;
    document.getElementById('deleteItemName').textContent = name;
    openModal('deleteModal');
}

// ── Search / Filter ───────────────────────────────────────────────────────
function filterTable() {
    var search   = document.getElementById('searchInput').value.toLowerCase().trim();
    var catVal   = document.getElementById('categoryFilter').value.toLowerCase();
    var stockVal = document.getElementById('stockFilter').value;
    var rows     = document.querySelectorAll('#inventoryBody .inv-row');
    var visible  = 0;

    rows.forEach(function(row) {
        var name     = row.getAttribute('data-name')     || '';
        var category = row.getAttribute('data-category') || '';
        var stock    = row.getAttribute('data-stock')    || '';

        var matchSearch = !search   || name.includes(search) || category.includes(search);
        var matchCat    = !catVal   || category === catVal;
        var matchStock  = !stockVal || stock === stockVal;

        if (matchSearch && matchCat && matchStock) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
        }
    });

    // Update visible count
    var countEl = document.getElementById('rowCount');
    if (countEl) {
        countEl.textContent = 'Showing ' + visible + ' item' + (visible !== 1 ? 's' : '');
    }

    // Show/hide empty message
    var emptyRow = document.getElementById('emptyRow');
    if (emptyRow) emptyRow.style.display = visible === 0 ? '' : 'none';
}

function clearFilters() {
    document.getElementById('searchInput').value    = '';
    document.getElementById('categoryFilter').value = '';
    document.getElementById('stockFilter').value    = '';
    filterTable();
}

// Auto-dismiss alerts after 5 seconds
document.querySelectorAll('.alert').forEach(function(el) {
    setTimeout(function() {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity    = '0';
        setTimeout(function() { el.remove(); }, 500);
    }, 5000);
});
</script>

<?php include_once '../../includes/footer.php'; ?>
