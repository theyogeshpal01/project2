<?php 
include_once '../../includes/header.php'; 
include_once '../../core/functions.php';

// Handle Lead Status Update (Manage Action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_lead_status'])) {
    $lead_id = trim($_POST['lead_id']);
    $new_status = $_POST['status'];
    try {
        $pdo->prepare("UPDATE leads SET status = ? WHERE id = ?")->execute([$new_status, $lead_id]);
        $success = "Lead status updated successfully.";
    } catch (Exception $e) {
        $error = "Error updating lead: " . $e->getMessage();
    }
}

// Handle Filters
$search = $_GET['search'] ?? '';
$where_clauses = [];
$params = [];
if (!empty($search)) {
    $where_clauses[] = "(r.customer_name LIKE ? OR r.business_name LIKE ? OR r.mobile LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : "";

// Fetch Dynamic Leads
$stmt = $pdo->prepare("SELECT r.*, u.name as agent_name 
                      FROM leads r 
                      LEFT JOIN users u ON r.agent_id = u.id 
                      $where_sql
                      ORDER BY r.created_at DESC");
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Dynamic Counters
$new_count = $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'new'")->fetchColumn();
$process_count = $pdo->query("SELECT COUNT(*) FROM leads WHERE status IN ('assigned', 'qualified', 'in_process')")->fetchColumn();
$approved_count = $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'qc_approved'")->fetchColumn();
$rejected_count = $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'rejected'")->fetchColumn();
?>

<div class="page-header" style="align-items:flex-start;">
    <div>
        <h1 style="font-size:1.5rem;font-weight:700;color:var(--text-dark);">Lead Management</h1>
        <p style="color:var(--text-muted);font-size:0.875rem;">Track, qualify, and convert your leads through the lifecycle.</p>
    </div>
    <div style="display:flex;gap:10px;">
        <a href="create_lead.php" class="btn btn-primary">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg>
            New Lead (Typeform)
        </a>
    </div>
</div>

<?php if (isset($success)): ?>
    <div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Lead Lifecycle Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 1.5rem;">
    <div class="stat-card glass-card" style="text-align: center; padding: 1.5rem; border-bottom: 3px solid var(--primary);">
        <div style="font-size: 2rem; font-weight: 700; color: var(--primary);"><?php echo $new_count; ?></div>
        <div style="font-size: 0.875rem; color: var(--text-muted); margin-top: 5px;">Pending</div>
    </div>
    <div class="stat-card glass-card" style="text-align: center; padding: 1.5rem; border-bottom: 3px solid var(--warning);">
        <div style="font-size: 2rem; font-weight: 700; color: var(--warning);"><?php echo $process_count; ?></div>
        <div style="font-size: 0.875rem; color: var(--text-muted); margin-top: 5px;">In Review</div>
    </div>
    <div class="stat-card glass-card" style="text-align: center; padding: 1.5rem; border-bottom: 3px solid var(--success);">
        <div style="font-size: 2rem; font-weight: 700; color: var(--success);"><?php echo $approved_count; ?></div>
        <div style="font-size: 0.875rem; color: var(--text-muted); margin-top: 5px;">QC Approved</div>
    </div>
    <div class="stat-card glass-card" style="text-align: center; padding: 1.5rem; border-bottom: 3px solid var(--danger);">
        <div style="font-size: 2rem; font-weight: 700; color: var(--danger);"><?php echo $rejected_count; ?></div>
        <div style="font-size: 0.875rem; color: var(--text-muted); margin-top: 5px;">Rejected</div>
    </div>
</div>

<!-- Leads List -->
<div class="glass-card" style="padding: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h3 style="font-size: 1rem; font-weight: 600;">Recent Leads</h3>
        <form method="GET" style="display: flex; gap: 10px;">
            <input type="text" name="search" placeholder="Search leads..." class="form-control" style="width: 250px;" value="<?php echo htmlspecialchars($search); ?>">
            <button type="submit" class="btn glass-card">Filter</button>
        </form>
    </div>

    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>Lead ID</th>
                    <th>Customer / Business</th>
                    <th>Mobile</th>
                    <th>Agent</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($leads as $lead): ?>
                <tr>
                    <td><span style="font-family: monospace; color: var(--text-muted);"><?php echo $lead['id']; ?></span></td>
                    <td>
                        <div style="font-weight: 600;"><?php echo $lead['customer_name']; ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo $lead['business_name']; ?></div>
                    </td>
                    <td><?php echo $lead['mobile']; ?></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($lead['agent_name']); ?>&background=random" style="width: 24px; height: 24px; border-radius: 50%;">
                            <?php echo $lead['agent_name']; ?>
                        </div>
                    </td>
                    <td>
                        <?php 
                            $status_class = '';
                            switch($lead['status']) {
                                case 'qc_approved': $status_class = 'success'; break;
                                case 'in_process': $status_class = 'primary'; break;
                                case 'new': $status_class = 'warning'; break;
                                default: $status_class = 'muted';
                            }
                        ?>
                        <span class="badge badge-<?php echo $status_class; ?>">
                            <?php echo str_replace('_', ' ', $lead['status']); ?>
                        </span>
                    </td>
                    <td style="font-size: 0.875rem; color: var(--text-muted);"><?php echo date('M d, H:i', strtotime($lead['created_at'])); ?></td>
                    <td>
                        <button onclick="openLeadModal('<?php echo htmlspecialchars($lead['id']); ?>', '<?php echo htmlspecialchars($lead['status']); ?>')" class="btn btn-primary" style="padding: 4px 8px; font-size: 0.75rem;">Manage</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Manage Lead Modal -->
<div class="modal-overlay" id="manage-lead-modal">
    <div class="modal-box" style="width:400px;">
        <div class="modal-header">
            <h3>Manage Lead</h3>
            <button class="modal-close" onclick="document.getElementById('manage-lead-modal').classList.remove('open')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="update_lead_status" value="1">
            <input type="hidden" name="lead_id" id="modal_lead_id">
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" id="modal_lead_status" class="form-control">
                    <option value="new">New</option>
                    <option value="assigned">Assigned</option>
                    <option value="qualified">Qualified</option>
                    <option value="in_process">In Process</option>
                    <option value="submitted">Submitted</option>
                    <option value="qc_approved">QC Approved</option>
                    <option value="rejected">Rejected</option>
                    <option value="active">Active</option>
                </select>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:1rem;">
                <button type="button" class="btn glass-card" onclick="document.getElementById('manage-lead-modal').classList.remove('open')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openLeadModal(id, status) {
    document.getElementById('modal_lead_id').value = id;
    document.getElementById('modal_lead_status').value = status;
    document.getElementById('manage-lead-modal').classList.add('open');
}
</script>

<?php include_once '../../includes/footer.php'; ?>
