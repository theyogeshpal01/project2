<?php 
include_once '../../includes/header.php'; 
include_once '../../core/functions.php';

// Fetch Dynamic Leads from form responses
$leads = $pdo->query("SELECT r.*, u.name as agent_name 
                      FROM form_responses r 
                      LEFT JOIN users u ON r.agent_id = u.id 
                      ORDER BY r.created_at DESC")->fetchAll();

// Dynamic Counters
$new_count = $pdo->query("SELECT COUNT(*) FROM form_responses WHERE status = 'pending'")->fetchColumn();
$process_count = $pdo->query("SELECT COUNT(*) FROM form_responses WHERE status = 'under_review'")->fetchColumn();
$approved_count = $pdo->query("SELECT COUNT(*) FROM form_responses WHERE status = 'approved'")->fetchColumn();
$rejected_count = $pdo->query("SELECT COUNT(*) FROM form_responses WHERE status = 'rejected'")->fetchColumn();
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
        <div style="display: flex; gap: 10px;">
            <input type="text" placeholder="Search leads..." class="form-control" style="width: 250px;">
            <button class="btn glass-card">Filter</button>
        </div>
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
                        <button class="btn btn-primary" style="padding: 4px 8px; font-size: 0.75rem;">Manage</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
