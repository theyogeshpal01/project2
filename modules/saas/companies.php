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
    if (isset($_POST['add_company'])) {
        $name = trim($_POST['company_name']);
        $domain = trim($_POST['domain']);
        $status = $_POST['status'];

        try {
            $stmt = $pdo->prepare("INSERT INTO companies (name, domain, status, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([$name, $domain, $status]);
            $success = "Company added successfully.";
        } catch (Exception $e) {
            $error = "Error adding company: " . $e->getMessage();
        }
    } elseif (isset($_POST['edit_company'])) {
        $id = (int)$_POST['company_id'];
        $name = trim($_POST['company_name']);
        $domain = trim($_POST['domain']);
        $status = $_POST['status'];

        try {
            $stmt = $pdo->prepare("UPDATE companies SET name = ?, domain = ?, status = ? WHERE id = ?");
            $stmt->execute([$name, $domain, $status, $id]);
            $success = "Company updated successfully.";
        } catch (Exception $e) {
            $error = "Error updating company: " . $e->getMessage();
        }
    } elseif (isset($_POST['delete_company'])) {
        $id = (int)$_POST['company_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM companies WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Company deleted successfully.";
        } catch (Exception $e) {
            $error = "Error deleting company (it may have linked data): " . $e->getMessage();
        }
    }
}

// Fetch companies
try {
    $companies = $pdo->query("SELECT * FROM companies ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $companies = [];
    $error = "Could not load companies.";
}

?>

<div class="page-header" style="align-items:flex-start;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-dark);">Manage Companies</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">SaaS Tenant Management</p>
    </div>
    <div style="display:flex;gap:10px;">
        <button class="btn btn-primary" onclick="openModal('addModal')">+ Add Company</button>
    </div>
</div>

<?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="glass-card table-card" style="padding:1.5rem;">
    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Company Name</th>
                    <th>Domain</th>
                    <th>Status</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($companies)): ?>
                    <tr><td colspan="6" style="text-align:center;padding:2rem;">No companies found.</td></tr>
                <?php endif; ?>
                <?php foreach ($companies as $comp): ?>
                    <tr>
                        <td><?php echo $comp['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($comp['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($comp['domain'] ?: '—'); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $comp['status'] === 'active' ? 'success' : 'danger'; ?>">
                                <?php echo ucfirst($comp['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('d M Y', strtotime($comp['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-primary" style="padding:4px 8px;" onclick='editCompany(<?php echo json_encode($comp); ?>)'>Edit</button>
                            <button class="btn btn-danger" style="padding:4px 8px;" onclick='deleteCompany(<?php echo $comp["id"]; ?>)'>Delete</button>
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
            <h3>Add New Company</h3>
            <button class="modal-close" onclick="closeModal('addModal')">x</button>
        </div>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Company Name</label>
                <input type="text" name="company_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Domain</label>
                <input type="text" name="domain" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn" onclick="closeModal('addModal')">Cancel</button>
                <button type="submit" name="add_company" class="btn btn-primary">Save Company</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit Company</h3>
            <button class="modal-close" onclick="closeModal('editModal')">x</button>
        </div>
        <form method="POST">
            <input type="hidden" name="company_id" id="edit_id">
            <div class="form-group">
                <label class="form-label">Company Name</label>
                <input type="text" name="company_name" id="edit_name" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Domain</label>
                <input type="text" name="domain" id="edit_domain" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" id="edit_status" class="form-control">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button type="button" class="btn" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" name="edit_company" class="btn btn-primary">Update Company</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Delete Company</h3>
            <button class="modal-close" onclick="closeModal('deleteModal')">x</button>
        </div>
        <p>Are you sure you want to delete this company? All data linked to it might be orphaned or deleted.</p>
        <form method="POST">
            <input type="hidden" name="company_id" id="delete_id">
            <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:1rem;">
                <button type="button" class="btn" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" name="delete_company" class="btn btn-danger">Yes, Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function editCompany(data) {
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_name').value = data.name;
    document.getElementById('edit_domain').value = data.domain || '';
    document.getElementById('edit_status').value = data.status;
    openModal('editModal');
}
function deleteCompany(id) {
    document.getElementById('delete_id').value = id;
    openModal('deleteModal');
}
</script>

<?php include_once '../../includes/footer.php'; ?>
