<?php 
include_once '../../includes/header.php'; 
include_once '../../core/functions.php';

$form_id = $_GET['id'] ?? null;
$where = $form_id ? "WHERE fr.form_id = ?" : "";
$params = $form_id ? [$form_id] : [];

$sql = "SELECT fr.*, f.title as form_title, u.name as agent_name 
        FROM form_responses fr 
        JOIN forms f ON fr.form_id = f.id 
        JOIN users u ON fr.agent_id = u.id 
        $where 
        ORDER BY fr.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$responses = $stmt->fetchAll();

// Handle QC Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qc_action'])) {
    $response_id = $_POST['response_id'];
    $status = $_POST['status'];
    $remarks = $_POST['remarks'];

    try {
        $stmt = $pdo->prepare("UPDATE form_responses SET status = ?, qc_remarks = ? WHERE id = ?");
        $stmt->execute([$status, $remarks, $response_id]);
        
        // If approved, update associated lead status if exists
        if ($status == 'approved') {
            // Find lead by agent and timestamp (approximate for this simple logic)
            // In a real system, we'd have a link table or response_id in leads
        }
        
        $success = "QC status updated!";
        // Refresh data
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $responses = $stmt->fetchAll();
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="header-actions" style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem;">Quality Check & Data Verification</h1>
    <p style="color: var(--text-muted); font-size: 0.875rem;">Review field submissions, verify documents, and approve data.</p>
</div>

<div class="glass-card" style="padding: 1.5rem;">
    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Form / Project</th>
                    <th>Agent</th>
                    <th>Data Summary</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Submitted On</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($responses as $resp): ?>
                <?php $data = json_decode($resp['response_data'], true); ?>
                <tr>
                    <td>#FR-<?php echo $resp['id']; ?></td>
                    <td><strong style="color: var(--primary);"><?php echo $resp['form_title']; ?></strong></td>
                    <td><?php echo $resp['agent_name']; ?></td>
                    <td style="max-width: 250px;">
                        <div style="font-size: 0.8125rem;">
                            <?php 
                            $count = 0;
                            foreach($data as $key => $val) {
                                if($count++ > 2) break;
                                echo "<strong>$key:</strong> $val<br>";
                            }
                            ?>
                        </div>
                    </td>
                    <td>
                        <?php if($resp['latitude']): ?>
                            <a href="https://www.google.com/maps?q=<?php echo $resp['latitude']; ?>,<?php echo $resp['longitude']; ?>" target="_blank" style="color: var(--accent); display: flex; align-items: center; gap: 4px;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                View Map
                            </a>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">No GPS</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo getStatusBadge($resp['status']); ?></td>
                    <td><?php echo date('M d, H:i', strtotime($resp['created_at'])); ?></td>
                    <td>
                        <button class="btn glass-card" onclick="openQCModal(<?php echo htmlspecialchars(json_encode($resp)); ?>)" style="padding: 5px 10px; font-size: 0.75rem; border-color: var(--primary); color: var(--primary);">Review</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- QC Review Modal -->
<div id="qc-modal" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center;">
    <div class="glass-card" style="width: 700px; max-height: 90vh; overflow-y: auto; padding: 2rem;">
        <h2 style="margin-bottom: 1.5rem;" id="modal-title">Review Submission</h2>
        
        <div id="modal-data-content" style="background: var(--bg-main); padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem;">
            <!-- Data will be injected here -->
        </div>

        <form method="POST">
            <input type="hidden" name="response_id" id="modal-response-id">
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">QC Remarks / Rejection Reason</label>
                <textarea name="remarks" required style="width: 100%; height: 100px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: white; padding: 0.75rem;"></textarea>
            </div>
            
            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" class="btn" style="background: var(--bg-card);" onclick="document.getElementById('qc-modal').style.display='none'">Close</button>
                <button type="submit" name="qc_action" value="1" onclick="this.form.status.value='rework'" class="btn" style="background: var(--warning); color: black;">Need Rework</button>
                <button type="submit" name="qc_action" value="1" onclick="this.form.status.value='rejected'" class="btn" style="background: var(--danger); color: white;">Reject</button>
                <button type="submit" name="qc_action" value="1" onclick="this.form.status.value='approved'" class="btn" style="background: var(--success); color: white;">Approve Submission</button>
                <input type="hidden" name="status" id="modal-status">
            </div>
        </form>
    </div>
</div>

<script>
function openQCModal(resp) {
    document.getElementById('modal-response-id').value = resp.id;
    document.getElementById('modal-title').innerText = 'Review: ' + resp.form_title;
    
    const data = JSON.parse(resp.response_data);
    let html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">';
    for (const [key, value] of Object.entries(data)) {
        html += `<div><div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">${key}</div><div style="font-weight: 600;">${value || 'N/A'}</div></div>`;
    }
    html += '</div>';
    
    document.getElementById('modal-data-content').innerHTML = html;
    document.getElementById('qc-modal').style.display = 'flex';
}
</script>

<?php include_once '../../includes/footer.php'; ?>
