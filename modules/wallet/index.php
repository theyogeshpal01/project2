<?php
include_once '../../includes/header.php';
include_once '../../core/functions.php';

$user_id  = $_SESSION['user_id'];
$role_id  = $_SESSION['role_id'];
$is_admin = ($role_id == 1 || $role_id == 8);

// Handle withdrawal request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_withdrawal'])) {
    $amount  = (float)$_POST['amount'];
    $upi     = trim($_POST['upi_id'] ?? '');
    $bank    = trim($_POST['bank_name'] ?? '');
    $acc     = trim($_POST['account_number'] ?? '');
    $ifsc    = trim($_POST['ifsc_code'] ?? '');

    $balance = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $balance->execute([$user_id]);
    $bal = (float)$balance->fetchColumn();

    if ($amount <= 0) {
        $error = "Enter a valid amount.";
    } elseif ($amount > $bal) {
        $error = "Insufficient wallet balance.";
    } else {
        $pdo->prepare("INSERT INTO withdrawal_requests (user_id, amount, upi_id, bank_name, account_number, ifsc_code) VALUES (?,?,?,?,?,?)")
            ->execute([$user_id, $amount, $upi, $bank, $acc, $ifsc]);
        $success = "Withdrawal request submitted! Admin will process it shortly.";
    }
}

// Admin: approve/reject withdrawal
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_withdrawal'])) {
    $wid    = (int)$_POST['wid'];
    $action = $_POST['action'];
    $remark = trim($_POST['admin_remarks'] ?? '');

    $wr = $pdo->prepare("SELECT * FROM withdrawal_requests WHERE id = ?");
    $wr->execute([$wid]);
    $wr = $wr->fetch();

    if ($wr && $wr['status'] === 'pending') {
        if ($action === 'approved') {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")
                ->execute([$wr['amount'], $wr['user_id']]);
            $pdo->prepare("INSERT INTO wallet_transactions (user_id, amount, type, source, description) VALUES (?,?,'debit','withdrawal','Withdrawal Processed')")
                ->execute([$wr['user_id'], $wr['amount']]);
            $pdo->prepare("UPDATE withdrawal_requests SET status='processed', admin_remarks=?, processed_at=NOW() WHERE id=?")
                ->execute([$remark, $wid]);
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,'success')")
                ->execute([$wr['user_id'], 'Withdrawal Processed', "₹{$wr['amount']} has been transferred to your account."]);
            $pdo->commit();
            $success = "Withdrawal approved and processed!";
        } else {
            $pdo->prepare("UPDATE withdrawal_requests SET status='rejected', admin_remarks=? WHERE id=?")
                ->execute([$remark, $wid]);
            $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,'danger')")
                ->execute([$wr['user_id'], 'Withdrawal Rejected', "Your withdrawal of ₹{$wr['amount']} was rejected. Reason: {$remark}"]);
            $success = "Withdrawal rejected.";
        }
    }
}

// Fetch data
if ($is_admin) {
    $wallet_data = $pdo->query("SELECT u.id, u.name, u.role_id, r.role_name, u.wallet_balance,
        (SELECT COUNT(*) FROM wallet_transactions WHERE user_id = u.id) as txn_count
        FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.wallet_balance DESC")->fetchAll();
    $pending_withdrawals = $pdo->query("SELECT wr.*, u.name, u.email FROM withdrawal_requests wr JOIN users u ON wr.user_id = u.id WHERE wr.status='pending' ORDER BY wr.requested_at DESC")->fetchAll();
    $total_wallet = $pdo->query("SELECT SUM(wallet_balance) FROM users")->fetchColumn() ?: 0;
    $total_paid   = $pdo->query("SELECT SUM(amount) FROM wallet_transactions WHERE type='debit' AND source='withdrawal'")->fetchColumn() ?: 0;
} else {
    $my_balance = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $my_balance->execute([$user_id]);
    $my_balance = (float)$my_balance->fetchColumn();

    $my_txns = $pdo->prepare("SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 20");
    $my_txns->execute([$user_id]);
    $my_txns = $my_txns->fetchAll();

    $my_withdrawals = $pdo->prepare("SELECT * FROM withdrawal_requests WHERE user_id = ? ORDER BY requested_at DESC LIMIT 10");
    $my_withdrawals->execute([$user_id]);
    $my_withdrawals = $my_withdrawals->fetchAll();

    $total_earned = $pdo->prepare("SELECT SUM(amount) FROM wallet_transactions WHERE user_id = ? AND type='credit'");
    $total_earned->execute([$user_id]);
    $total_earned = (float)$total_earned->fetchColumn();

    $total_withdrawn = $pdo->prepare("SELECT SUM(amount) FROM wallet_transactions WHERE user_id = ? AND type='debit'");
    $total_withdrawn->execute([$user_id]);
    $total_withdrawn = (float)$total_withdrawn->fetchColumn();
}
?>

<div class="page-header">
    <div>
        <h1><?php echo $is_admin ? 'Wallet & Payout Management' : 'My Wallet'; ?></h1>
        <p><?php echo $is_admin ? 'Manage agent wallets, approve withdrawals, track payouts.' : 'Track your earnings, commissions, and withdrawal history.'; ?></p>
    </div>
    <?php if (!$is_admin): ?>
        <button class="btn btn-primary" onclick="document.getElementById('withdraw-modal').classList.add('open')">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"></path></svg>
            Request Withdrawal
        </button>
    <?php endif; ?>
</div>

<?php if (isset($success)): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>
<?php if (isset($error)):   ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

<?php if ($is_admin): ?>
<!-- ADMIN VIEW -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr); margin-bottom:2rem;">
    <div class="stat-card glass-card">
        <div class="stat-label">Total Wallet Balance</div>
        <div class="stat-value">₹<?php echo number_format($total_wallet, 2); ?></div>
        <div style="font-size:0.75rem; color:var(--text-muted);">Across all agents</div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-label">Total Paid Out</div>
        <div class="stat-value" style="color:var(--success);">₹<?php echo number_format($total_paid, 2); ?></div>
    </div>
    <div class="stat-card glass-card">
        <div class="stat-label">Pending Withdrawals</div>
        <div class="stat-value" style="color:var(--warning);"><?php echo count($pending_withdrawals); ?></div>
    </div>
</div>

<?php if (!empty($pending_withdrawals)): ?>
<div class="glass-card" style="padding:1.5rem; margin-bottom:2rem;">
    <h3 style="margin-bottom:1.5rem; color:var(--warning);">⚠ Pending Withdrawal Requests</h3>
    <div class="data-table-container">
        <table>
            <thead><tr><th>User</th><th>Amount</th><th>UPI / Bank</th><th>Requested</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($pending_withdrawals as $wr): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($wr['name']); ?></strong><br><span style="font-size:0.75rem; color:var(--text-muted);"><?php echo $wr['email']; ?></span></td>
                    <td><strong style="color:var(--primary);">₹<?php echo number_format($wr['amount'],2); ?></strong></td>
                    <td style="font-size:0.8rem;"><?php echo $wr['upi_id'] ?: ($wr['bank_name'] . ' — ' . $wr['account_number']); ?></td>
                    <td style="font-size:0.8rem; color:var(--text-muted);"><?php echo date('d M Y, H:i', strtotime($wr['requested_at'])); ?></td>
                    <td>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="wid" value="<?php echo $wr['id']; ?>">
                            <input type="hidden" name="admin_remarks" value="Approved by admin">
                            <button type="submit" name="process_withdrawal" value="1" onclick="this.form.action.value='approved'" class="btn" style="background:var(--success); color:white; padding:5px 12px; font-size:0.75rem; margin-right:5px;" onclick="this.previousElementSibling.value='approved'">Approve</button>
                        </form>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="wid" value="<?php echo $wr['id']; ?>">
                            <input type="hidden" name="action" value="rejected">
                            <input type="hidden" name="admin_remarks" value="Rejected by admin">
                            <button type="submit" name="process_withdrawal" value="1" class="btn" style="background:var(--danger); color:white; padding:5px 12px; font-size:0.75rem;">Reject</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="glass-card" style="padding:1.5rem;">
    <h3 style="margin-bottom:1.5rem;">All Agent Wallets</h3>
    <div class="data-table-container">
        <table>
            <thead><tr><th>Agent</th><th>Role</th><th>Wallet Balance</th><th>Transactions</th><th>Action</th></tr></thead>
            <tbody>
                <?php foreach ($wallet_data as $w): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($w['name']); ?></strong></td>
                    <td><span class="badge badge-primary"><?php echo $w['role_name']; ?></span></td>
                    <td><strong style="color:var(--success);">₹<?php echo number_format($w['wallet_balance'],2); ?></strong></td>
                    <td><?php echo $w['txn_count']; ?> txns</td>
                    <td>
                        <form method="POST" style="display:inline-flex; gap:8px; align-items:center;">
                            <input type="hidden" name="wid" value="0">
                            <button type="button" onclick="creditWallet(<?php echo $w['id']; ?>, '<?php echo htmlspecialchars($w['name']); ?>')" class="btn glass-card" style="padding:4px 10px; font-size:0.75rem;">+ Credit</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php else: ?>
<!-- USER VIEW -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr); margin-bottom:2rem;">
    <div class="stat-card glass-card" style="border-bottom:3px solid var(--primary);">
        <div class="stat-label">Current Balance</div>
        <div class="stat-value" style="color:var(--primary);">₹<?php echo number_format($my_balance,2); ?></div>
    </div>
    <div class="stat-card glass-card" style="border-bottom:3px solid var(--success);">
        <div class="stat-label">Total Earned</div>
        <div class="stat-value" style="color:var(--success);">₹<?php echo number_format($total_earned,2); ?></div>
    </div>
    <div class="stat-card glass-card" style="border-bottom:3px solid var(--warning);">
        <div class="stat-label">Total Withdrawn</div>
        <div class="stat-value" style="color:var(--warning);">₹<?php echo number_format($total_withdrawn,2); ?></div>
    </div>
</div>

<div style="display:grid; grid-template-columns:2fr 1fr; gap:2rem;">
    <!-- Transactions -->
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="margin-bottom:1.5rem;">Transaction History</h3>
        <div class="data-table-container">
            <table>
                <thead><tr><th>Date</th><th>Description</th><th>Type</th><th>Amount</th></tr></thead>
                <tbody>
                    <?php if (empty($my_txns)): ?>
                        <tr><td colspan="4" style="text-align:center; padding:2rem; color:var(--text-muted);">No transactions yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($my_txns as $t): ?>
                    <tr>
                        <td style="font-size:0.8rem; color:var(--text-muted);"><?php echo date('d M Y, H:i', strtotime($t['created_at'])); ?></td>
                        <td style="font-size:0.875rem;"><?php echo htmlspecialchars($t['description']); ?></td>
                        <td><span class="badge badge-<?php echo $t['type']==='credit'?'success':'danger'; ?>"><?php echo strtoupper($t['type']); ?></span></td>
                        <td style="font-weight:700; color:<?php echo $t['type']==='credit'?'var(--success)':'var(--danger)'; ?>;">
                            <?php echo $t['type']==='credit'?'+':'-'; ?>₹<?php echo number_format($t['amount'],2); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Withdrawal History -->
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="margin-bottom:1.5rem;">Withdrawal Requests</h3>
        <div style="display:flex; flex-direction:column; gap:10px;">
            <?php if (empty($my_withdrawals)): ?>
                <p style="color:var(--text-muted); font-size:0.875rem; text-align:center; padding:2rem 0;">No withdrawal requests.</p>
            <?php endif; ?>
            <?php foreach ($my_withdrawals as $wr): ?>
            <div style="padding:12px; background:var(--bg-main); border-radius:10px; border:1px solid var(--border);">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <strong>₹<?php echo number_format($wr['amount'],2); ?></strong>
                    <?php
                    $wc = ['pending'=>'warning','processed'=>'success','rejected'=>'danger','approved'=>'success'];
                    echo '<span class="badge badge-'.($wc[$wr['status']]??'muted').'">'.strtoupper($wr['status']).'</span>';
                    ?>
                </div>
                <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;"><?php echo date('d M Y', strtotime($wr['requested_at'])); ?></div>
                <?php if ($wr['admin_remarks']): ?>
                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:4px;"><?php echo htmlspecialchars($wr['admin_remarks']); ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Withdrawal Modal -->
<div class="modal-overlay" id="withdraw-modal">
    <div class="modal-box" style="width:500px;">
        <div class="modal-header">
            <h3>Request Withdrawal</h3>
            <button class="modal-close" onclick="document.getElementById('withdraw-modal').classList.remove('open')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST">
            <div class="form-group">
                <label class="form-label">Amount (Available: ₹<?php echo number_format($my_balance,2); ?>)</label>
                <input type="number" name="amount" class="form-control" step="0.01" min="1" max="<?php echo $my_balance; ?>" required placeholder="Enter amount">
            </div>
            <div class="form-group">
                <label class="form-label">UPI ID (Preferred)</label>
                <input type="text" name="upi_id" class="form-control" placeholder="yourname@upi">
            </div>
            <p style="text-align:center; color:var(--text-muted); font-size:0.8rem; margin:0.5rem 0;">— OR Bank Transfer —</p>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Bank Name</label>
                    <input type="text" name="bank_name" class="form-control" placeholder="SBI / HDFC...">
                </div>
                <div class="form-group">
                    <label class="form-label">Account Number</label>
                    <input type="text" name="account_number" class="form-control" placeholder="Account No.">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">IFSC Code</label>
                <input type="text" name="ifsc_code" class="form-control" placeholder="SBIN0001234">
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:1rem;">
                <button type="button" class="btn glass-card" onclick="document.getElementById('withdraw-modal').classList.remove('open')">Cancel</button>
                <button type="submit" name="request_withdrawal" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include_once '../../includes/footer.php'; ?>
