<?php 
include_once '../../includes/header.php'; 
include_once '../../core/functions.php';

// Handle Campaign Creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_campaign'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $cpl = $_POST['cpl_amount'];
    $start = $_POST['start_date'];
    $end = $_POST['end_date'];

    try {
        $stmt = $pdo->prepare("INSERT INTO campaigns (title, description, cpl_amount, start_date, end_date) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $description, $cpl, $start, $end]);
        $success = "Campaign launched successfully!";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$campaigns = $pdo->query("SELECT * FROM campaigns ORDER BY created_at DESC")->fetchAll();

// Dynamic Stats
$avg_payout = $pdo->query("SELECT AVG(cpl_amount) FROM campaigns")->fetchColumn() ?: 0;
$total_spend = $pdo->query("SELECT SUM(cpl_amount) FROM campaigns")->fetchColumn() ?: 0;
?>

<div class="header-actions" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <div>
        <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem;">Referral Campaigns (CPL)</h1>
        <p style="color: var(--text-muted); font-size: 0.875rem;">Set up Cost-Per-Lead (CPL) models for your distribution partners.</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('add-campaign-modal').style.display='flex'">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg>
        Create Campaign
    </button>
</div>

<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 2rem;">
    <div class="stat-card glass-card">
        <div class="stat-label">Active Campaigns</div>
        <div class="stat-value"><?php echo count($campaigns); ?></div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-label">Avg. Payout</div>
        <div class="stat-value">₹<?php echo number_format($avg_payout, 2); ?></div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-label">Total CPL Allocation</div>
        <div class="stat-value">₹<?php echo number_format($total_spend / 1000, 1); ?>K</div>
    </div>
</div>

<div class="glass-card" style="padding: 1.5rem;">
    <div class="data-table-container">
        <table>
            <thead>
                <tr>
                    <th>Campaign</th>
                    <th>CPL Rate</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Leads</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($campaigns)): ?>
                    <tr><td colspan="6" style="text-align: center; padding: 3rem; color: var(--text-muted);">No campaigns found. Launch your first CPL model!</td></tr>
                <?php endif; ?>
                <?php foreach($campaigns as $camp): ?>
                <tr>
                    <td>
                        <strong style="color: var(--primary);"><?php echo $camp['title']; ?></strong>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo substr($camp['description'], 0, 50); ?>...</div>
                    </td>
                    <td><strong><?php echo formatCurrency($camp['cpl_amount']); ?></strong></td>
                    <td><?php echo date('M d', strtotime($camp['start_date'])); ?> - <?php echo date('M d, Y', strtotime($camp['end_date'])); ?></td>
                    <td><?php echo getStatusBadge($camp['status']); ?></td>
                    <td>0</td>
                    <td>
                        <div style="display: flex; gap: 10px;">
                            <button title="Copy Referral Link" style="background:none; border:none; color:var(--accent); cursor:pointer;"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path></svg></button>
                            <button title="Edit" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Campaign Modal -->
<div id="add-campaign-modal" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 1000; justify-content: center; align-items: center;">
    <div class="glass-card" style="width: 550px; padding: 2rem;">
        <h2 style="margin-bottom: 1.5rem;">Launch New CPL Campaign</h2>
        <form method="POST">
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Campaign Title</label>
                <input type="text" name="title" required placeholder="e.g. Bank Account Referral" style="width: 100%; padding: 0.75rem; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: white;">
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">CPL Amount (₹ per valid lead)</label>
                <input type="number" name="cpl_amount" required step="0.01" style="width: 100%; padding: 0.75rem; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: white;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Start Date</label>
                    <input type="date" name="start_date" required style="width: 100%; padding: 0.75rem; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: white;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">End Date</label>
                    <input type="date" name="end_date" required style="width: 100%; padding: 0.75rem; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: white;">
                </div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: var(--text-muted);">Description & Rules</label>
                <textarea name="description" style="width: 100%; height: 80px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: white; padding: 0.75rem;"></textarea>
            </div>

            <div style="display: flex; gap: 1rem; justify-content: flex-end;">
                <button type="button" class="btn" style="background: var(--bg-card);" onclick="document.getElementById('add-campaign-modal').style.display='none'">Cancel</button>
                <button type="submit" name="add_campaign" class="btn btn-primary">Launch Campaign</button>
            </div>
        </form>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
