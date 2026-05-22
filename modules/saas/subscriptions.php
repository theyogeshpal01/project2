<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

// Only Super Admin can access this page
$role_id = $_SESSION['role_id'] ?? 1;
if ($role_id != 7) { // Assuming 7 is Super Admin
    echo "<div class='alert alert-danger'>Access Denied. Super Admin only.</div>";
    include_once '../../includes/footer.php';
    exit;
}

$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_plan'])) {
        $name = trim($_POST['plan_name']);
        $price = (float)$_POST['price'];
        $billing_cycle = $_POST['billing_cycle'];
        $max_users = (int)$_POST['max_users'];
        $features = trim($_POST['features']);
        
        try {
            $stmt = $pdo->prepare("INSERT INTO subscription_plans (name, price, billing_cycle, max_users, features, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $price, $billing_cycle, $max_users, $features]);
            $success = "Plan added successfully.";
        } catch (Exception $e) {
            $error = "Error adding plan: " . $e->getMessage();
        }
    } elseif (isset($_POST['edit_plan'])) {
        $id = (int)$_POST['plan_id'];
        $name = trim($_POST['plan_name']);
        $price = (float)$_POST['price'];
        $billing_cycle = $_POST['billing_cycle'];
        $max_users = (int)$_POST['max_users'];
        $features = trim($_POST['features']);
        
        try {
            $stmt = $pdo->prepare("UPDATE subscription_plans SET name=?, price=?, billing_cycle=?, max_users=?, features=? WHERE id=?");
            $stmt->execute([$name, $price, $billing_cycle, $max_users, $features, $id]);
            $success = "Plan updated successfully.";
        } catch (Exception $e) {
            $error = "Error updating plan: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_plan'])) {
        $id = (int)$_POST['plan_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM subscription_plans WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Plan deleted successfully.";
        } catch (Exception $e) {
            $error = "Error deleting plan: " . $e->getMessage();
        }
    }
}

// Fetch plans
try {
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS subscription_plans (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        price DECIMAL(10,2),
        billing_cycle ENUM('monthly','yearly'),
        max_users INT DEFAULT 10,
        features TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $plans = $pdo->query("SELECT * FROM subscription_plans ORDER BY price ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $plans = [];
    $error = "Could not load plans.";
}

?>

<div class="page-header" style="align-items:flex-start;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-dark);">Subscription Plans</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Manage SaaS Pricing</p>
    </div>
    <div style="display:flex;gap:10px;">
        <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Plan</button>
    </div>
</div>

<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="glass-card table-card" style="padding:1.5rem;">
    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>Plan Name</th>
                    <th>Price</th>
                    <th>Billing Cycle</th>
                    <th>Max Users</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($plans)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:2rem;">No plans found.</td></tr>
                <?php endif; ?>
                <?php foreach ($plans as $p): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($p['name']); ?></strong></td>
                        <td>₹<?php echo number_format($p['price'], 2); ?></td>
                        <td><?php echo ucfirst($p['billing_cycle']); ?></td>
                        <td><?php echo $p['max_users']; ?></td>
                        <td>
                            <button class="btn btn-primary" style="padding:4px 8px;" onclick='editPlan(<?php echo json_encode($p); ?>)'>Edit</button>
                            <button class="btn btn-danger" style="padding:4px 8px;" onclick='deletePlan(<?php echo $p["id"]; ?>)'>Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Modal -->
<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add New Plan</h3>
            <button class="modal-close" onclick="closeModal('addModal')">x</button>
        </div>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Plan Name</label>
                <input type="text" name="plan_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Price</label>
                <input type="number" step="0.01" name="price" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Billing Cycle</label>
                <select name="billing_cycle" class="form-control">
                    <option value="monthly">Monthly</option>
                    <option value="yearly">Yearly</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Max Users</label>
                <input type="number" name="max_users" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Features (JSON or comma separated)</label>
                <textarea name="features" class="form-control" rows="3"></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" name="add_plan" class="btn btn-primary">Save Plan</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit Plan</h3>
            <button class="modal-close" onclick="closeModal('editModal')">x</button>
        </div>
        <form method="POST">
            <input type="hidden" name="plan_id" id="edit_id">
            <div class="form-group">
                <label class="form-label">Plan Name</label>
                <input type="text" name="plan_name" id="edit_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Price</label>
                <input type="number" step="0.01" name="price" id="edit_price" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Billing Cycle</label>
                <select name="billing_cycle" id="edit_cycle" class="form-control">
                    <option value="monthly">Monthly</option>
                    <option value="yearly">Yearly</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Max Users</label>
                <input type="number" name="max_users" id="edit_users" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Features</label>
                <textarea name="features" id="edit_features" class="form-control" rows="3"></textarea>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" name="edit_plan" class="btn btn-primary">Update Plan</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Delete Plan</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')">x</button>
        </div>
        <p>Are you sure you want to delete this plan?</p>
        <form method="POST">
            <input type="hidden" name="plan_id" id="delete_id">
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:1rem;">
                <button type="button" class="btn" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" name="delete_plan" class="btn btn-danger">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function editPlan(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_name').value = data.name;
    document.getElementById('edit_price').value = data.price;
    document.getElementById('edit_cycle').value = data.billing_cycle;
    document.getElementById('edit_users').value = data.max_users;
    document.getElementById('edit_features').value = data.features;
    openModal('editModal');
}
function deletePlan(id) {
    document.getElementById('delete_id').value = id;
    openModal('deleteModal');
}
</script>

<?php include_once '../../includes/footer.php'; ?>
