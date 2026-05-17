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

<div class="header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem;">Lead Management</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">Track, qualify, and convert your leads through the lifecycle.</p>
    </div>
    <a href="create_lead.php" class="btn btn-primary">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg>
        New Lead (Typeform)
    </a>
</div>

<!-- Lead Lifecycle Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(5, 1fr); margin-bottom: 2rem;">
    <div class="stat-card glass-card" style="text-align: center; border-bottom: 3px solid var(--primary);">
        <div class="stat-label">Pending</div>
        <div class="stat-value"><?php echo $new_count; ?></div>
    </div>
    <div class="stat-card glass-card" style="text-align: center; border-bottom: 3px solid var(--warning);">
        <div class="stat-label">In Review</div>
        <div class="stat-value"><?php echo $process_count; ?></div>
    </div>
    <div class="stat-card glass-card" style="text-align: center; border-bottom: 3px solid var(--success);">
        <div class="stat-label">QC Approved</div>
        <div class="stat-value"><?php echo $approved_count; ?></div>
    </div>
    <div class="stat-card glass-card" style="text-align: center; border-bottom: 3px solid var(--danger);">
        <div class="stat-label">Rejected</div>
        <div class="stat-value"><?php echo $rejected_count; ?></div>
    </div>
</div>

<!-- Leads List -->
<div class="glass-card" style="padding: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <h3 style="font-size: 1.125rem;">Recent Leads</h3>
        <div style="display: flex; gap: 10px;">
            <input type="text" placeholder="Search leads..." style="background: var(--bg-main); border: 1px solid var(--border); padding: 8px 15px; border-radius: 8px; color: white; font-size: 0.875rem;">
            <button class="btn glass-card" style="background: rgba(255,255,255,0.05); padding: 8px 12px;">Filter</button>
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
                        <span style="display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; background: var(--bg-card); border: 1px solid var(--border); color: var(--text-main);">
                            <?php echo str_replace('_', ' ', $lead['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('M d, H:i', strtotime($lead['created_at'])); ?></td>
                    <td>
                        <button class="btn glass-card" style="padding: 5px 10px; font-size: 0.75rem;">Manage</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
