<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

// Handle QC Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qc_action'])) {
    $response_id = (int)$_POST['response_id'];
    $status      = $_POST['status'];
    $remarks     = trim($_POST['remarks']);
    $qc_agent    = $_SESSION['user_id'];

    try {
        $pdo->beginTransaction();

        $pdo->prepare("UPDATE form_responses SET status = ?, qc_remarks = ? WHERE id = ?")
            ->execute([$status, $remarks, $response_id]);

        $pdo->prepare("INSERT INTO qc_reviews (response_id, qc_agent_id, status, remarks) VALUES (?,?,?,?)")
            ->execute([$response_id, $qc_agent, $status, $remarks]);

        // If approved — credit commission to agent wallet
        if ($status === 'approved') {
            $agent = $pdo->prepare("SELECT agent_id, form_id FROM form_responses WHERE id = ?");
            $agent->execute([$response_id]);
            $row = $agent->fetch();

            if ($row) {
                // Get CPL from campaign linked to form (if any)
                $cpl = $pdo->prepare("SELECT c.cpl_amount FROM campaigns c JOIN forms f ON f.id = ? WHERE c.status = 'active' LIMIT 1");
                $cpl->execute([$row['form_id']]);
                $cpl_amount = $cpl->fetchColumn() ?: 0;

                if ($cpl_amount > 0) {
                    $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")
                        ->execute([$cpl_amount, $row['agent_id']]);
                    $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, source, description) VALUES (?,?,'credit','commission','QC Approved — Commission Credited')")
                        ->execute([$row['agent_id'], $cpl_amount]);
                    // Notify agent
                    $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,'success')")
                        ->execute([$row['agent_id'], 'Submission Approved!', "Your submission #FR-{$response_id} was approved. ₹{$cpl_amount} credited to wallet."]);
                }
            }
        } elseif ($status === 'rejected' || $status === 'rework') {
            $agent = $pdo->prepare("SELECT agent_id FROM form_responses WHERE id = ?");
            $agent->execute([$response_id]);
            $row = $agent->fetch();
            if ($row) {
                $msg = $status === 'rework' ? "Rework required on submission #FR-{$response_id}: {$remarks}" : "Submission #FR-{$response_id} was rejected: {$remarks}";
                $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)")
                    ->execute([$row['agent_id'], ucfirst($status) . ' — Action Required', $msg, $status === 'rework' ? 'warning' : 'danger']);
            }
        }

        $pdo->commit();
        $success = "QC action saved successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}

// Filters
$filter_status = $_GET['status'] ?? 'pending';
$allowed = ['pending', 'approved', 'rejected', 'rework', 'all'];
if (!in_array($filter_status, $allowed)) $filter_status = 'pending';

$where = $filter_status !== 'all' ? "WHERE fr.status = ?" : "WHERE 1";
$params = $filter_status !== 'all' ? [$filter_status] : [];

$responses = $pdo->prepare("SELECT fr.*, f.title as form_title, u.name as agent_name, u.id as uid
    FROM form_responses fr
    JOIN forms f ON fr.form_id = f.id
    LEFT JOIN users u ON fr.agent_id = u.id
    $where ORDER BY fr.created_at DESC");
$responses->execute($params);
$responses = $responses->fetchAll();

// Stats
$stats = $pdo->query("SELECT status, COUNT(*) as cnt FROM form_responses GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="page-header">
    <div>
        <h1>QC Panel — Quality Control</h1>
        <p>Review, verify, approve or reject field agent submissions.</p>
    </div>
</div>

<?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
<?php if (isset($error)):   ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns: repeat(5,1fr); margin-bottom:2rem;">
    <?php
    $stat_items = [
        ['label'=>'Pending',   'key'=>'pending',      'color'=>'var(--warning)'],
        ['label'=>'Approved',  'key'=>'approved',     'color'=>'var(--success)'],
        ['label'=>'Rejected',  'key'=>'rejected',     'color'=>'var(--danger)'],
        ['label'=>'Rework',    'key'=>'rework',       'color'=>'var(--accent)'],
        ['label'=>'Total',     'key'=>'__total__',    'color'=>'var(--primary)'],
    ];
    $total = array_sum(array_column($pdo->query("SELECT COUNT(*) as c FROM form_responses")->fetchAll(), 'c'));
    foreach ($stat_items as $si):
        $val = $si['key'] === '__total__' ? array_sum(array_values($stats)) : ($stats[$si['key']] ?? 0);
    ?>
    <a href="?status=<?php echo $si['key'] === '__total__' ? 'all' : $si['key']; ?>" style="text-decoration:none;">
        <div class="stat-card glass-card" style="text-align:center; border-bottom:3px solid <?php echo $si['color']; ?>;">
            <div class="stat-label"><?php echo $si['label']; ?></div>
            <div class="stat-value" style="color:<?php echo $si['color']; ?>"><?php echo $val; ?></div>
        </div>
    </a>
    <?php endforeach; ?>
</div>

<!-- Filter Tabs -->
<div style="display:flex; gap:8px; margin-bottom:1.5rem; flex-wrap:wrap;">
    <?php foreach (['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','rework'=>'Rework','all'=>'All'] as $k=>$v): ?>
        <a href="?status=<?php echo $k; ?>" class="btn <?php echo $filter_status===$k ? 'btn-primary' : 'glass-card'; ?>" style="padding:6px 16px; font-size:0.8rem;"><?php echo $v; ?></a>
    <?php endforeach; ?>
</div>

<!-- Table -->
<div class="glass-card" style="padding:1.5rem;">
    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Form / Project</th>
                    <th>Agent</th>
                    <th>Customer</th>
                    <th>GPS</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($responses)): ?>
                    <tr><td colspan="8" style="text-align:center; padding:3rem; color:var(--text-muted);">No submissions found.</td></tr>
                <?php endif; ?>
                <?php foreach ($responses as $resp):
                    $data = json_decode($resp['response_data'] ?? '{}', true) ?: [];
                ?>
                <tr>
                    <td><span style="font-family:monospace; color:var(--text-muted);">#FR-<?php echo str_pad($resp['id'],4,'0',STR_PAD_LEFT); ?></span></td>
                    <td><strong style="color:var(--primary);"><?php echo htmlspecialchars($resp['form_title']); ?></strong></td>
                    <td>
                        <div style="display:flex; align-items:center; gap:8px;">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($resp['agent_name']); ?>&background=4f46e5&color=fff&size=32" style="width:28px; height:28px; border-radius:50%;">
                            <span style="font-size:0.875rem;"><?php echo htmlspecialchars($resp['agent_name'] ?? 'N/A'); ?></span>
                        </div>
                    </td>
                    <td>
                        <div style="font-size:0.875rem; font-weight:600;"><?php echo htmlspecialchars($resp['customer_name'] ?? ($data['customer_name'] ?? 'N/A')); ?></div>
                        <div style="font-size:0.75rem; color:var(--text-muted);"><?php echo htmlspecialchars($resp['mobile'] ?? ($data['mobile'] ?? '')); ?></div>
                    </td>
                    <td>
                        <?php if ($resp['latitude']): ?>
                            <a href="https://www.google.com/maps?q=<?php echo $resp['latitude']; ?>,<?php echo $resp['longitude']; ?>" target="_blank" style="color:var(--accent); font-size:0.8rem; display:flex; align-items:center; gap:4px;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                View Map
                            </a>
                        <?php else: ?>
                            <span style="color:var(--text-muted); font-size:0.8rem;">No GPS</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $sc = ['approved'=>'success','rejected'=>'danger','rework'=>'warning','pending'=>'primary'];
                        $cls = $sc[$resp['status']] ?? 'muted';
                        ?>
                        <span class="badge badge-<?php echo $cls; ?>"><?php echo str_replace('_',' ',$resp['status']); ?></span>
                    </td>
                    <td style="font-size:0.8rem; color:var(--text-muted);"><?php echo date('d M, H:i', strtotime($resp['created_at'])); ?></td>
                    <td>
                        <?php if ($resp['status'] === 'pending' || $resp['status'] === 'rework'): ?>
                            <button class="btn btn-primary" onclick='openQC(<?php echo htmlspecialchars(json_encode($resp), ENT_QUOTES); ?>)' style="padding:5px 12px; font-size:0.75rem;">Review</button>
                        <?php else: ?>
                            <button class="btn glass-card" onclick='openQC(<?php echo htmlspecialchars(json_encode($resp), ENT_QUOTES); ?>)' style="padding:5px 12px; font-size:0.75rem;">View</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- QC Modal -->
<div class="modal-overlay" id="qc-modal">
    <div class="modal-box" style="width:720px;">
        <div class="modal-header">
            <h3 id="qc-modal-title">Review Submission</h3>
            <button class="modal-close" onclick="closeQC()">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>

        <div id="qc-data-view" style="background:var(--bg-main); border-radius:12px; padding:1.5rem; margin-bottom:1.5rem; max-height:300px; overflow-y:auto;"></div>

        <form method="POST" id="qc-form">
            <input type="hidden" name="response_id" id="qc-resp-id">
            <input type="hidden" name="status" id="qc-status">
            <div class="form-group">
                <label class="form-label">QC Remarks / Reason</label>
                <textarea name="remarks" id="qc-remarks" class="form-control" rows="3" placeholder="Add your review notes here..."></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap;">
                <button type="button" class="btn glass-card" onclick="closeQC()">Close</button>
                <button type="button" class="btn" style="background:var(--warning); color:#000;" onclick="submitQC('rework')">Need Rework</button>
                <button type="button" class="btn" style="background:var(--danger); color:white;" onclick="submitQC('rejected')">Reject</button>
                <button type="button" class="btn" style="background:var(--success); color:white;" onclick="submitQC('approved')">✓ Approve</button>
            </div>
        </form>
    </div>
</div>

<script>
function openQC(resp) {
    document.getElementById('qc-resp-id').value = resp.id;
    document.getElementById('qc-modal-title').innerText = 'Review: ' + resp.form_title + ' — #FR-' + String(resp.id).padStart(4,'0');
    document.getElementById('qc-remarks').value = resp.qc_remarks || '';

    let data = {};
    try { data = JSON.parse(resp.response_data || '{}'); } catch(e) {}

    let html = '<div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">';
    // Show key fields
    const extras = {
        'Customer': resp.customer_name,
        'Mobile': resp.mobile,
        'Business': resp.business_name,
        'Category': resp.category,
        'Agent': resp.agent_name,
        'Submitted': resp.created_at,
        'GPS': resp.latitude ? resp.latitude + ', ' + resp.longitude : 'N/A',
    };
    for (const [k,v] of Object.entries(extras)) {
        if (v) html += `<div><div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; margin-bottom:2px;">${k}</div><div style="font-weight:600; font-size:0.875rem;">${v}</div></div>`;
    }
    for (const [k,v] of Object.entries(data)) {
        if (typeof v === 'string' && !v.startsWith('data:')) {
            html += `<div><div style="font-size:0.7rem; color:var(--text-muted); text-transform:uppercase; margin-bottom:2px;">${k}</div><div style="font-weight:600; font-size:0.875rem;">${v || 'N/A'}</div></div>`;
        }
    }
    html += '</div>';

    // Show images from response_data
    let imgHtml = '';
    for (const [k,v] of Object.entries(data)) {
        if (typeof v === 'string' && v.startsWith('data:image')) {
            imgHtml += `<div style="margin-top:1rem;"><div style="font-size:0.7rem; color:var(--text-muted); margin-bottom:4px;">${k}</div><img src="${v}" style="max-width:200px; border-radius:8px;"></div>`;
        }
    }

    document.getElementById('qc-data-view').innerHTML = html + imgHtml;
    document.getElementById('qc-modal').classList.add('open');
}

function closeQC() {
    document.getElementById('qc-modal').classList.remove('open');
}

function submitQC(status) {
    document.getElementById('qc-status').value = status;
    document.getElementById('qc-form').submit();
}
</script>

<?php include_once '../../includes/footer.php'; ?>
