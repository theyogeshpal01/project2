<?php
include_once '../../includes/header.php';

$user_id  = $_SESSION['user_id'];
$role_id  = $_SESSION['role_id'];
$is_admin = ($role_id == 1 || $role_id == 8);

$success = '';
$error   = '';

/* ═══════════════════════════════════════════════════════════
   POST HANDLERS
═══════════════════════════════════════════════════════════ */

// ── 1. User requests a withdrawal ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_withdrawal'])) {
    try {
        $amount = (float) ($_POST['amount'] ?? 0);
        $upi    = trim($_POST['upi_id']         ?? '');
        $bank   = trim($_POST['bank_name']       ?? '');
        $acc    = trim($_POST['account_number']  ?? '');
        $ifsc   = trim($_POST['ifsc_code']       ?? '');

        if ($amount <= 0) {
            $error = 'Please enter a valid amount greater than 0.';
        } elseif (empty($upi) && (empty($bank) || empty($acc) || empty($ifsc))) {
            $error = 'Please provide either a UPI ID or complete bank details (Bank Name, Account Number & IFSC).';
        } else {
            $balStmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
            $balStmt->execute([$user_id]);
            $currentBalance = (float) $balStmt->fetchColumn();

            if ($amount > $currentBalance) {
                $error = "Insufficient wallet balance. Your current balance is ₹" . number_format($currentBalance, 2) . ".";
            } else {
                // Check for existing pending request
                $pendingChk = $pdo->prepare("SELECT COUNT(*) FROM withdrawal_requests WHERE user_id = ? AND status = 'pending'");
                $pendingChk->execute([$user_id]);
                if ((int) $pendingChk->fetchColumn() > 0) {
                    $error = 'You already have a pending withdrawal request. Please wait for it to be processed.';
                } else {
                    $ins = $pdo->prepare(
                        "INSERT INTO withdrawal_requests (user_id, amount, bank_name, account_number, ifsc_code, upi_id, status)
                         VALUES (?, ?, ?, ?, ?, ?, 'pending')"
                    );
                    $ins->execute([$user_id, $amount, $bank, $acc, $ifsc, $upi]);
                    $success = "✅ Withdrawal request of ₹" . number_format($amount, 2) . " submitted successfully! Admin will process it shortly.";
                }
            }
        }
    } catch (Exception $e) {
        $error = 'Failed to submit withdrawal request. Please try again.';
    }
}

// ── 2. Admin approves / rejects withdrawal ─────────────────
if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_withdrawal'])) {
    try {
        $wid    = (int) ($_POST['wid']           ?? 0);
        $action = trim($_POST['action']           ?? '');
        $remark = trim($_POST['admin_remarks']    ?? '');

        if (!in_array($action, ['approved', 'rejected'])) {
            $error = 'Invalid action.';
        } else {
            $wrStmt = $pdo->prepare("SELECT * FROM withdrawal_requests WHERE id = ?");
            $wrStmt->execute([$wid]);
            $wr = $wrStmt->fetch(PDO::FETCH_ASSOC);

            if (!$wr) {
                $error = 'Withdrawal request not found.';
            } elseif ($wr['status'] !== 'pending') {
                $error = 'This request has already been processed.';
            } else {
                $pdo->beginTransaction();

                if ($action === 'approved') {
                    // Verify user still has enough balance
                    $balChk = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                    $balChk->execute([$wr['user_id']]);
                    $userBal = (float) $balChk->fetchColumn();

                    if ($wr['amount'] > $userBal) {
                        $pdo->rollBack();
                        $error = "User has insufficient balance (₹" . number_format($userBal, 2) . ") for this withdrawal of ₹" . number_format($wr['amount'], 2) . ".";
                    } else {
                        // Deduct from wallet
                        $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")
                            ->execute([$wr['amount'], $wr['user_id']]);

                        // Record debit transaction
                        $pdo->prepare(
                            "INSERT INTO wallet_transactions (user_id, amount, type, source, description, created_at)
                             VALUES (?, ?, 'debit', 'withdrawal', 'Withdrawal Processed', NOW())"
                        )->execute([$wr['user_id'], $wr['amount']]);

                        // Update request status
                        $pdo->prepare(
                            "UPDATE withdrawal_requests SET status = 'processed', admin_remarks = ? WHERE id = ?"
                        )->execute([$remark ?: 'Approved by admin', $wid]);

                        // Notify user
                        try {
                            $pdo->prepare(
                                "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'success')"
                            )->execute([
                                $wr['user_id'],
                                'Withdrawal Processed',
                                "₹" . number_format($wr['amount'], 2) . " has been transferred to your account."
                            ]);
                        } catch (Exception $ne) { /* notifications table may not exist */ }

                        $pdo->commit();
                        $success = "✅ Withdrawal of ₹" . number_format($wr['amount'], 2) . " approved and processed successfully!";
                    }
                } else {
                    // Rejected
                    $pdo->prepare(
                        "UPDATE withdrawal_requests SET status = 'rejected', admin_remarks = ? WHERE id = ?"
                    )->execute([$remark ?: 'Rejected by admin', $wid]);

                    try {
                        $pdo->prepare(
                            "INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'danger')"
                        )->execute([
                            $wr['user_id'],
                            'Withdrawal Rejected',
                            "Your withdrawal of ₹" . number_format($wr['amount'], 2) . " was rejected." . ($remark ? " Reason: $remark" : '')
                        ]);
                    } catch (Exception $ne) { /* notifications table may not exist */ }

                    $pdo->commit();
                    $success = "Withdrawal request has been rejected.";
                }
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $error = 'An error occurred: ' . $e->getMessage();
    }
}

/* ═══════════════════════════════════════════════════════════
   FETCH DATA
═══════════════════════════════════════════════════════════ */

try {
    if ($is_admin) {
        // Admin stats
        $totalWallet = (float) $pdo->query("SELECT COALESCE(SUM(wallet_balance),0) FROM users")->fetchColumn();
        $totalPaidOut = (float) $pdo->query(
            "SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE type='debit' AND source='withdrawal'"
        )->fetchColumn();

        $pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM withdrawal_requests WHERE status='pending'")->fetchColumn();

        // All withdrawal requests (all statuses) for admin tab
        $allWithdrawals = $pdo->query(
            "SELECT wr.*, u.name AS user_name, u.email AS user_email
             FROM withdrawal_requests wr
             JOIN users u ON wr.user_id = u.id
             ORDER BY FIELD(wr.status,'pending','approved','processed','rejected'), wr.requested_at DESC
             LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);

        // Pending only (quick list)
        $pendingWithdrawals = array_filter($allWithdrawals, fn($r) => $r['status'] === 'pending');

        // All agent wallets
        $walletData = $pdo->query(
            "SELECT u.id, u.name, u.email, u.wallet_balance,
                    COALESCE(r.role_name, 'N/A') AS role_name,
                    (SELECT COUNT(*) FROM wallet_transactions wt WHERE wt.user_id = u.id) AS txn_count
             FROM users u
             LEFT JOIN roles r ON u.role_id = r.id
             ORDER BY u.wallet_balance DESC"
        )->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // Current user balance
        $balStmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
        $balStmt->execute([$user_id]);
        $myBalance = (float) $balStmt->fetchColumn();

        // Total credits
        $credStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE user_id = ? AND type='credit'");
        $credStmt->execute([$user_id]);
        $totalCredits = (float) $credStmt->fetchColumn();

        // Total debits
        $debStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM wallet_transactions WHERE user_id = ? AND type='debit'");
        $debStmt->execute([$user_id]);
        $totalDebits = (float) $debStmt->fetchColumn();

        // Pending withdrawals amount
        $pendStmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM withdrawal_requests WHERE user_id = ? AND status='pending'");
        $pendStmt->execute([$user_id]);
        $pendingWithdrawalAmt = (float) $pendStmt->fetchColumn();

        // Transaction history
        $txnStmt = $pdo->prepare(
            "SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 50"
        );
        $txnStmt->execute([$user_id]);
        $myTxns = $txnStmt->fetchAll(PDO::FETCH_ASSOC);

        // My withdrawal requests
        $wrStmt = $pdo->prepare(
            "SELECT * FROM withdrawal_requests WHERE user_id = ? ORDER BY requested_at DESC LIMIT 20"
        );
        $wrStmt->execute([$user_id]);
        $myWithdrawals = $wrStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $error = 'Failed to load wallet data: ' . $e->getMessage();
    // Provide safe defaults
    $myBalance = $totalCredits = $totalDebits = $pendingWithdrawalAmt = 0;
    $myTxns = $myWithdrawals = [];
    if ($is_admin) {
        $totalWallet = $totalPaidOut = $pendingCount = 0;
        $allWithdrawals = $pendingWithdrawals = $walletData = [];
    }
}

// Active tab for admin
$activeTab = $_GET['tab'] ?? 'requests';
?>

<!-- ══════════════════════════ PAGE HEADER ══════════════════════════ -->
<div class="page-header">
    <div>
        <h1><?php echo $is_admin ? '💰 Wallet &amp; Payout Management' : '💰 My Wallet'; ?></h1>
        <p><?php echo $is_admin
            ? 'Approve/reject withdrawal requests, monitor all agent wallets and payouts.'
            : 'View your balance, track transactions, and request withdrawals.';
        ?></p>
    </div>
    <?php if (!$is_admin): ?>
        <button class="btn btn-primary" onclick="openModal('withdraw-modal')">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:6px"><path d="M12 5v14M5 12h14"/></svg>
            Request Withdrawal
        </button>
    <?php endif; ?>
</div>

<!-- Alerts -->
<?php if ($success): ?>
    <div class="alert alert-success" id="pageAlert"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger" id="pageAlert"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>


<?php if ($is_admin): ?>
<!-- ╔══════════════════════════════════════════════════════════╗
     ║                   ADMIN VIEW                           ║
     ╚══════════════════════════════════════════════════════════╝ -->

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr); margin-bottom:2rem;">
    <div class="stat-card glass-card" style="border-bottom:3px solid var(--primary);">
        <div class="stat-label">Total Wallet (All Users)</div>
        <div class="stat-value" style="color:var(--primary);">₹<?php echo number_format($totalWallet, 2); ?></div>
        <div style="font-size:0.75rem; color:var(--text-muted);">Combined balance across all accounts</div>
    </div>
    <div class="stat-card glass-card" style="border-bottom:3px solid var(--success);">
        <div class="stat-label">Total Paid Out</div>
        <div class="stat-value" style="color:var(--success);">₹<?php echo number_format($totalPaidOut, 2); ?></div>
        <div style="font-size:0.75rem; color:var(--text-muted);">All processed withdrawals</div>
    </div>
    <div class="stat-card glass-card" style="border-bottom:3px solid var(--warning);">
        <div class="stat-label">Pending Withdrawals</div>
        <div class="stat-value" style="color:var(--warning);"><?php echo $pendingCount; ?></div>
        <div style="font-size:0.75rem; color:var(--text-muted);">Awaiting your action</div>
    </div>
</div>

<!-- Admin Tabs -->
<div style="display:flex; gap:0; margin-bottom:1.5rem; border-bottom:2px solid var(--border);">
    <a href="?tab=requests" class="tab-btn <?php echo $activeTab==='requests'?'tab-active':''; ?>"
       style="padding:10px 22px; font-weight:600; font-size:0.875rem; text-decoration:none; color:<?php echo $activeTab==='requests'?'var(--primary)':'var(--text-muted)'; ?>; border-bottom:<?php echo $activeTab==='requests'?'2px solid var(--primary)':'2px solid transparent'; ?>; margin-bottom:-2px; transition:all .2s;">
        📋 Withdrawal Requests
        <?php if ($pendingCount > 0): ?>
            <span class="badge badge-warning" style="margin-left:6px;"><?php echo $pendingCount; ?></span>
        <?php endif; ?>
    </a>
    <a href="?tab=wallets" class="tab-btn <?php echo $activeTab==='wallets'?'tab-active':''; ?>"
       style="padding:10px 22px; font-weight:600; font-size:0.875rem; text-decoration:none; color:<?php echo $activeTab==='wallets'?'var(--primary)':'var(--text-muted)'; ?>; border-bottom:<?php echo $activeTab==='wallets'?'2px solid var(--primary)':'2px solid transparent'; ?>; margin-bottom:-2px; transition:all .2s;">
        👛 Agent Wallets
    </a>
</div>

<?php if ($activeTab === 'requests'): ?>
<!-- ── Withdrawal Requests Tab ── -->

<?php if (!empty($pendingWithdrawals)): ?>
<div class="glass-card" style="padding:1.5rem; margin-bottom:2rem; border-left:4px solid var(--warning);">
    <h3 style="margin-bottom:1.25rem; color:var(--warning); display:flex; align-items:center; gap:8px;">
        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Pending Requests (<?php echo count($pendingWithdrawals); ?>)
    </h3>

    <!-- Filter bar for pending -->
    <div class="filter-bar" style="margin-bottom:1rem;">
        <input type="text" id="pendingSearch" class="form-control" placeholder="🔍 Search by name, amount, bank..." style="max-width:320px;" oninput="filterTable('pendingSearch','pendingTable')">
    </div>

    <div class="data-table-container">
        <table id="pendingTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Amount</th>
                    <th>Payment Details</th>
                    <th>Requested</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pendingWithdrawals as $idx => $wr): ?>
                <tr>
                    <td style="color:var(--text-muted); font-size:0.8rem;"><?php echo $idx + 1; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($wr['user_name']); ?></strong><br>
                        <span style="font-size:0.75rem; color:var(--text-muted);"><?php echo htmlspecialchars($wr['user_email']); ?></span>
                    </td>
                    <td>
                        <strong style="font-size:1.1rem; color:var(--primary);">₹<?php echo number_format($wr['amount'], 2); ?></strong>
                    </td>
                    <td style="font-size:0.8rem; line-height:1.6;">
                        <?php if (!empty($wr['upi_id'])): ?>
                            <span style="background:var(--bg-card); padding:2px 8px; border-radius:4px; border:1px solid var(--border);">
                                📱 UPI: <?php echo htmlspecialchars($wr['upi_id']); ?>
                            </span>
                        <?php else: ?>
                            🏦 <?php echo htmlspecialchars($wr['bank_name'] ?: '—'); ?><br>
                            Acc: <?php echo htmlspecialchars($wr['account_number'] ?: '—'); ?><br>
                            IFSC: <?php echo htmlspecialchars($wr['ifsc_code'] ?: '—'); ?>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.8rem; color:var(--text-muted);">
                        <?php echo !empty($wr['requested_at']) ? date('d M Y', strtotime($wr['requested_at'])) . '<br>' . date('H:i', strtotime($wr['requested_at'])) : '—'; ?>
                    </td>
                    <td>
                        <!-- Approve -->
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Approve ₹<?php echo number_format($wr['amount'],2); ?> withdrawal for <?php echo addslashes(htmlspecialchars($wr['user_name'])); ?>? This will deduct the amount from their wallet.');">
                            <input type="hidden" name="wid"    value="<?php echo (int)$wr['id']; ?>">
                            <input type="hidden" name="action" value="approved">
                            <input type="hidden" name="admin_remarks" value="Approved by admin">
                            <button type="submit" name="process_withdrawal" value="1" class="btn btn-success" style="padding:5px 12px; font-size:0.75rem; margin-right:4px;">
                                ✓ Approve
                            </button>
                        </form>
                        <!-- Reject -->
                        <button type="button" class="btn btn-danger" style="padding:5px 12px; font-size:0.75rem;"
                            onclick="openRejectModal(<?php echo (int)$wr['id']; ?>, '<?php echo addslashes(htmlspecialchars($wr['user_name'])); ?>', '<?php echo number_format($wr['amount'],2); ?>')">
                            ✕ Reject
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="glass-card" style="padding:3rem; text-align:center; margin-bottom:2rem;">
    <svg width="48" height="48" fill="none" stroke="var(--success)" stroke-width="1.5" viewBox="0 0 24 24" style="margin-bottom:1rem;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    <h3 style="color:var(--success); margin-bottom:0.5rem;">All Clear!</h3>
    <p style="color:var(--text-muted);">No pending withdrawal requests at this time.</p>
</div>
<?php endif; ?>

<!-- All Requests History -->
<div class="glass-card" style="padding:1.5rem;">
    <h3 style="margin-bottom:1.25rem;">All Withdrawal Requests History</h3>
    <div class="filter-bar" style="margin-bottom:1rem; display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
        <input type="text" id="allReqSearch" class="form-control" placeholder="🔍 Search requests..." style="max-width:280px;" oninput="filterTable('allReqSearch','allReqTable')">
        <select id="statusFilter" class="form-control" style="max-width:180px;" onchange="filterByStatus()">
            <option value="">All Statuses</option>
            <option value="pending">Pending</option>
            <option value="processed">Processed</option>
            <option value="rejected">Rejected</option>
        </select>
    </div>
    <div class="data-table-container">
        <table id="allReqTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>User</th>
                    <th>Amount</th>
                    <th>Payment Method</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Admin Note</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($allWithdrawals)): ?>
                <tr><td colspan="7" style="text-align:center; padding:2rem; color:var(--text-muted);">No withdrawal requests yet.</td></tr>
            <?php else: ?>
            <?php foreach ($allWithdrawals as $idx => $wr):
                $sc = ['pending'=>'badge-warning','processed'=>'badge-success','rejected'=>'badge-danger','approved'=>'badge-success'];
                $bc = $sc[$wr['status']] ?? 'badge-accent';
            ?>
                <tr data-status="<?php echo $wr['status']; ?>">
                    <td style="color:var(--text-muted); font-size:0.8rem;"><?php echo $idx + 1; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($wr['user_name']); ?></strong><br>
                        <span style="font-size:0.75rem; color:var(--text-muted);"><?php echo htmlspecialchars($wr['user_email']); ?></span>
                    </td>
                    <td><strong style="color:var(--primary);">₹<?php echo number_format($wr['amount'], 2); ?></strong></td>
                    <td style="font-size:0.8rem;">
                        <?php echo !empty($wr['upi_id'])
                            ? '📱 UPI: ' . htmlspecialchars($wr['upi_id'])
                            : '🏦 ' . htmlspecialchars($wr['bank_name'] ?: '—'); ?>
                    </td>
                    <td><span class="badge <?php echo $bc; ?>"><?php echo strtoupper($wr['status']); ?></span></td>
                    <td style="font-size:0.8rem; color:var(--text-muted);">
                        <?php echo !empty($wr['requested_at']) ? date('d M Y, H:i', strtotime($wr['requested_at'])) : '—'; ?>
                    </td>
                    <td style="font-size:0.8rem; color:var(--text-muted);">
                        <?php echo htmlspecialchars($wr['admin_remarks'] ?? '—'); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($activeTab === 'wallets'): ?>
<!-- ── Agent Wallets Tab ── -->
<div class="glass-card" style="padding:1.5rem;">
    <h3 style="margin-bottom:1.25rem;">All Agent Wallets</h3>
    <div class="filter-bar" style="margin-bottom:1rem;">
        <input type="text" id="walletSearch" class="form-control" placeholder="🔍 Search by name, role, email..." style="max-width:320px;" oninput="filterTable('walletSearch','walletTable')">
    </div>
    <div class="data-table-container">
        <table id="walletTable">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Agent</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Wallet Balance</th>
                    <th>Transactions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($walletData)): ?>
                <tr><td colspan="6" style="text-align:center; padding:2rem; color:var(--text-muted);">No users found.</td></tr>
            <?php else: ?>
            <?php foreach ($walletData as $idx => $w): ?>
                <tr>
                    <td style="color:var(--text-muted); font-size:0.8rem;"><?php echo $idx + 1; ?></td>
                    <td><strong><?php echo htmlspecialchars($w['name']); ?></strong></td>
                    <td style="font-size:0.8rem; color:var(--text-muted);"><?php echo htmlspecialchars($w['email']); ?></td>
                    <td><span class="badge badge-accent"><?php echo htmlspecialchars($w['role_name']); ?></span></td>
                    <td>
                        <strong style="font-size:1rem; color:<?php echo $w['wallet_balance'] > 0 ? 'var(--success)' : 'var(--text-muted)'; ?>;">
                            ₹<?php echo number_format($w['wallet_balance'], 2); ?>
                        </strong>
                    </td>
                    <td style="color:var(--text-muted);"><?php echo (int)$w['txn_count']; ?> txns</td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Reject Modal (Admin) -->
<div class="modal-overlay" id="reject-modal">
    <div class="modal-box" style="width:480px;">
        <div class="modal-header">
            <h3>Reject Withdrawal Request</h3>
            <button class="modal-close" onclick="closeModal('reject-modal')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" onsubmit="return confirm('Are you sure you want to reject this request?');">
            <input type="hidden" name="wid"    id="rejectWid">
            <input type="hidden" name="action" value="rejected">
            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label">Rejecting withdrawal for: <strong id="rejectUserName"></strong></label>
                <p style="margin:4px 0; color:var(--primary); font-weight:600;">Amount: ₹<span id="rejectAmount"></span></p>
            </div>
            <div class="form-group">
                <label class="form-label">Reason / Admin Remarks <span style="color:var(--text-muted); font-size:0.8rem;">(optional)</span></label>
                <textarea name="admin_remarks" class="form-control" rows="3" placeholder="Enter reason for rejection..."></textarea>
            </div>
            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:1rem;">
                <button type="button" class="btn glass-card" onclick="closeModal('reject-modal')">Cancel</button>
                <button type="submit" name="process_withdrawal" value="1" class="btn btn-danger">Confirm Reject</button>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<!-- ╔══════════════════════════════════════════════════════════╗
     ║                    USER VIEW                           ║
     ╚══════════════════════════════════════════════════════════╝ -->

<!-- Big Balance Display -->
<div class="glass-card" style="padding:2.5rem; text-align:center; margin-bottom:2rem; background:linear-gradient(135deg, var(--primary) 0%, #7c3aed 100%); border:none;">
    <p style="color:rgba(255,255,255,0.8); font-size:0.9rem; margin-bottom:0.5rem; font-weight:500; letter-spacing:1px; text-transform:uppercase;">Available Balance</p>
    <h1 style="font-size:3rem; font-weight:800; color:#fff; margin:0; letter-spacing:-1px;">
        ₹<?php echo number_format($myBalance, 2); ?>
    </h1>
    <p style="color:rgba(255,255,255,0.7); font-size:0.8rem; margin-top:0.75rem;">
        <?php echo htmlspecialchars($_SESSION['user_name']); ?>'s Wallet
    </p>
    <button class="btn" onclick="openModal('withdraw-modal')"
        style="margin-top:1.25rem; background:rgba(255,255,255,0.2); color:#fff; border:1px solid rgba(255,255,255,0.4); backdrop-filter:blur(4px);">
        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="margin-right:6px"><path d="M12 5v14M5 12h14"/></svg>
        Request Withdrawal
    </button>
</div>

<!-- Stats -->
<div class="stats-grid" style="grid-template-columns:repeat(3,1fr); margin-bottom:2rem;">
    <div class="stat-card glass-card" style="border-bottom:3px solid var(--success);">
        <div class="stat-label">Total Credits</div>
        <div class="stat-value" style="color:var(--success);">₹<?php echo number_format($totalCredits, 2); ?></div>
        <div style="font-size:0.75rem; color:var(--text-muted);">Lifetime earnings &amp; credits</div>
    </div>
    <div class="stat-card glass-card" style="border-bottom:3px solid var(--danger);">
        <div class="stat-label">Total Debits</div>
        <div class="stat-value" style="color:var(--danger);">₹<?php echo number_format($totalDebits, 2); ?></div>
        <div style="font-size:0.75rem; color:var(--text-muted);">Lifetime withdrawals &amp; debits</div>
    </div>
    <div class="stat-card glass-card" style="border-bottom:3px solid var(--warning);">
        <div class="stat-label">Pending Withdrawals</div>
        <div class="stat-value" style="color:var(--warning);">₹<?php echo number_format($pendingWithdrawalAmt, 2); ?></div>
        <div style="font-size:0.75rem; color:var(--text-muted);">Awaiting admin approval</div>
    </div>
</div>

<!-- Main Content Grid -->
<div style="display:grid; grid-template-columns:2fr 1fr; gap:2rem; align-items:start;">

    <!-- Transaction History -->
    <div class="glass-card" style="padding:1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem;">
            <h3 style="margin:0;">Transaction History</h3>
            <div class="filter-bar" style="margin:0;">
                <input type="text" id="txnSearch" class="form-control" placeholder="🔍 Search transactions..."
                    style="max-width:220px; font-size:0.8rem; padding:6px 12px;"
                    oninput="filterTable('txnSearch','txnTable')">
            </div>
        </div>
        <div class="data-table-container">
            <table id="txnTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Description</th>
                        <th>Source</th>
                        <th>Type</th>
                        <th>Amount</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($myTxns)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center; padding:3rem; color:var(--text-muted);">
                            <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="display:block;margin:0 auto 1rem;opacity:0.4"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                            No transactions yet.
                        </td>
                    </tr>
                <?php else: ?>
                <?php foreach ($myTxns as $t): ?>
                    <tr>
                        <td style="font-size:0.8rem; color:var(--text-muted); white-space:nowrap;">
                            <?php echo !empty($t['created_at']) ? date('d M Y', strtotime($t['created_at'])) . '<br><span style="font-size:0.7rem;">' . date('H:i', strtotime($t['created_at'])) . '</span>' : '—'; ?>
                        </td>
                        <td style="font-size:0.875rem;"><?php echo htmlspecialchars($t['description'] ?? '—'); ?></td>
                        <td style="font-size:0.75rem; color:var(--text-muted);"><?php echo htmlspecialchars($t['source'] ?? '—'); ?></td>
                        <td>
                            <span class="badge <?php echo $t['type'] === 'credit' ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo strtoupper($t['type']); ?>
                            </span>
                        </td>
                        <td style="font-weight:700; white-space:nowrap; color:<?php echo $t['type'] === 'credit' ? 'var(--success)' : 'var(--danger)'; ?>;">
                            <?php echo $t['type'] === 'credit' ? '+' : '−'; ?>₹<?php echo number_format($t['amount'], 2); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Withdrawal Requests Sidebar -->
    <div class="glass-card" style="padding:1.5rem;">
        <h3 style="margin-bottom:1.25rem;">My Withdrawal Requests</h3>
        <?php if (empty($myWithdrawals)): ?>
            <div style="text-align:center; padding:2rem 0; color:var(--text-muted);">
                <svg width="36" height="36" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="display:block;margin:0 auto 0.75rem;opacity:0.4"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <p style="font-size:0.875rem;">No withdrawal requests yet.</p>
            </div>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap:10px;">
            <?php
            $statusColors = [
                'pending'   => ['badge-warning', '#f59e0b'],
                'approved'  => ['badge-success', 'var(--success)'],
                'processed' => ['badge-success', 'var(--success)'],
                'rejected'  => ['badge-danger',  'var(--danger)'],
            ];
            foreach ($myWithdrawals as $wr):
                [$badgeClass, $borderColor] = $statusColors[$wr['status']] ?? ['badge-accent', 'var(--border)'];
            ?>
            <div style="padding:14px 16px; background:var(--bg-main); border-radius:10px; border:1px solid var(--border); border-left:3px solid <?php echo $borderColor; ?>;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:6px;">
                    <strong style="font-size:1rem;">₹<?php echo number_format($wr['amount'], 2); ?></strong>
                    <span class="badge <?php echo $badgeClass; ?>"><?php echo strtoupper($wr['status']); ?></span>
                </div>
                <?php if (!empty($wr['upi_id'])): ?>
                    <div style="font-size:0.75rem; color:var(--text-muted);">📱 <?php echo htmlspecialchars($wr['upi_id']); ?></div>
                <?php elseif (!empty($wr['bank_name'])): ?>
                    <div style="font-size:0.75rem; color:var(--text-muted);">🏦 <?php echo htmlspecialchars($wr['bank_name']); ?></div>
                <?php endif; ?>
                <div style="font-size:0.72rem; color:var(--text-muted); margin-top:4px;">
                    <?php echo !empty($wr['requested_at']) ? date('d M Y, H:i', strtotime($wr['requested_at'])) : '—'; ?>
                </div>
                <?php if (!empty($wr['admin_remarks'])): ?>
                    <div style="font-size:0.75rem; color:var(--text-muted); margin-top:6px; padding-top:6px; border-top:1px solid var(--border);">
                        💬 <?php echo htmlspecialchars($wr['admin_remarks']); ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Withdrawal Modal ── -->
<div class="modal-overlay" id="withdraw-modal">
    <div class="modal-box" style="width:520px;">
        <div class="modal-header">
            <h3>💸 Request Withdrawal</h3>
            <button class="modal-close" onclick="closeModal('withdraw-modal')">
                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <!-- Balance indicator inside modal -->
        <div style="background:var(--bg-main); border-radius:8px; padding:12px 16px; margin-bottom:1.5rem; display:flex; justify-content:space-between; align-items:center; border:1px solid var(--border);">
            <span style="color:var(--text-muted); font-size:0.875rem;">Available Balance</span>
            <strong style="color:var(--primary); font-size:1.1rem;">₹<?php echo number_format($myBalance, 2); ?></strong>
        </div>

        <form method="POST" onsubmit="return validateWithdrawForm()">
            <div class="form-group">
                <label class="form-label">Withdrawal Amount <span style="color:var(--danger);">*</span></label>
                <input type="number" name="amount" id="withdrawAmount" class="form-control"
                    step="0.01" min="1" max="<?php echo $myBalance; ?>"
                    required placeholder="Enter amount (Max: ₹<?php echo number_format($myBalance, 2); ?>)">
                <small style="color:var(--text-muted);">Minimum: ₹1.00 &nbsp;|&nbsp; Maximum: ₹<?php echo number_format($myBalance, 2); ?></small>
            </div>

            <div style="margin:1.25rem 0; border-top:1px dashed var(--border); padding-top:1rem;">
                <p style="font-size:0.85rem; font-weight:600; color:var(--text-primary); margin-bottom:1rem;">Payment Method <span style="color:var(--danger);">*</span> (choose one)</p>

                <!-- UPI -->
                <div style="background:var(--bg-main); border-radius:8px; padding:14px; border:1px solid var(--border); margin-bottom:1rem;">
                    <label style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:0.875rem; margin-bottom:10px; cursor:pointer;">
                        <input type="radio" name="pay_method" value="upi" id="payUPI" onchange="togglePayMethod('upi')" style="accent-color:var(--primary);"> 📱 UPI Transfer
                    </label>
                    <div id="upiFields">
                        <input type="text" name="upi_id" id="upiId" class="form-control" placeholder="yourname@upi or yourname@bank">
                    </div>
                </div>

                <!-- Bank -->
                <div style="background:var(--bg-main); border-radius:8px; padding:14px; border:1px solid var(--border);">
                    <label style="display:flex; align-items:center; gap:8px; font-weight:600; font-size:0.875rem; margin-bottom:10px; cursor:pointer;">
                        <input type="radio" name="pay_method" value="bank" id="payBank" onchange="togglePayMethod('bank')" style="accent-color:var(--primary);"> 🏦 Bank Transfer (NEFT/IMPS)
                    </label>
                    <div id="bankFields" style="display:grid; grid-template-columns:1fr 1fr; gap:10px; display:none;">
                        <div class="form-group" style="margin:0;">
                            <label class="form-label" style="font-size:0.8rem;">Bank Name</label>
                            <input type="text" name="bank_name" id="bankName" class="form-control" placeholder="SBI / HDFC / ICICI...">
                        </div>
                        <div class="form-group" style="margin:0;">
                            <label class="form-label" style="font-size:0.8rem;">Account Number</label>
                            <input type="text" name="account_number" id="accountNo" class="form-control" placeholder="Account No.">
                        </div>
                        <div class="form-group" style="margin:0; grid-column:span 2;">
                            <label class="form-label" style="font-size:0.8rem;">IFSC Code</label>
                            <input type="text" name="ifsc_code" id="ifscCode" class="form-control" placeholder="SBIN0001234">
                        </div>
                    </div>
                </div>
            </div>

            <div id="withdrawError" style="color:var(--danger); font-size:0.85rem; margin-bottom:10px; display:none;"></div>

            <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:1rem;">
                <button type="button" class="btn glass-card" onclick="closeModal('withdraw-modal')">Cancel</button>
                <button type="submit" name="request_withdrawal" value="1" class="btn btn-primary">
                    Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

<!-- ══════════════════════════ SCRIPTS ══════════════════════════ -->
<script>
/* ── Modal Helpers ── */
function openModal(id) {
    document.getElementById(id).classList.add('open');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}

/* Close modal when clicking backdrop */
document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) {
            overlay.classList.remove('open');
        }
    });
});

/* ── Admin: open reject modal ── */
function openRejectModal(wid, userName, amount) {
    document.getElementById('rejectWid').value       = wid;
    document.getElementById('rejectUserName').textContent = userName;
    document.getElementById('rejectAmount').textContent   = amount;
    openModal('reject-modal');
}

/* ── Client-side table filter ── */
function filterTable(inputId, tableId) {
    var q     = document.getElementById(inputId).value.toLowerCase();
    var rows  = document.querySelectorAll('#' + tableId + ' tbody tr');
    rows.forEach(function(row) {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

/* ── Status filter for all requests ── */
function filterByStatus() {
    var status = document.getElementById('statusFilter').value;
    var q      = document.getElementById('allReqSearch') ? document.getElementById('allReqSearch').value.toLowerCase() : '';
    var rows   = document.querySelectorAll('#allReqTable tbody tr');
    rows.forEach(function(row) {
        var matchStatus = !status || (row.dataset.status === status);
        var matchSearch = !q || row.textContent.toLowerCase().includes(q);
        row.style.display = (matchStatus && matchSearch) ? '' : 'none';
    });
}

/* Re-apply search when status filter changes */
var allReqSearch = document.getElementById('allReqSearch');
if (allReqSearch) {
    allReqSearch.addEventListener('input', filterByStatus);
}

/* ── Withdrawal form: toggle UPI / Bank fields ── */
function togglePayMethod(method) {
    var upiFields  = document.getElementById('upiFields');
    var bankFields = document.getElementById('bankFields');
    if (method === 'upi') {
        upiFields.style.display  = 'block';
        bankFields.style.display = 'none';
    } else {
        upiFields.style.display  = 'none';
        bankFields.style.display = 'grid';
    }
}

/* ── Withdrawal form validation ── */
function validateWithdrawForm() {
    var errEl   = document.getElementById('withdrawError');
    var amount  = parseFloat(document.getElementById('withdrawAmount').value);
    var maxBal  = <?php echo $is_admin ? 0 : (float)$myBalance; ?>;
    var method  = document.querySelector('input[name="pay_method"]:checked');
    var upiId   = document.getElementById('upiId')     ? document.getElementById('upiId').value.trim()     : '';
    var bankName= document.getElementById('bankName')   ? document.getElementById('bankName').value.trim()  : '';
    var accNo   = document.getElementById('accountNo')  ? document.getElementById('accountNo').value.trim() : '';
    var ifsc    = document.getElementById('ifscCode')   ? document.getElementById('ifscCode').value.trim()  : '';

    errEl.style.display = 'none';

    if (!amount || amount <= 0) {
        errEl.textContent = 'Please enter a valid amount.';
        errEl.style.display = 'block';
        return false;
    }
    if (amount > maxBal) {
        errEl.textContent = 'Amount exceeds your available balance of ₹' + maxBal.toFixed(2) + '.';
        errEl.style.display = 'block';
        return false;
    }
    if (!method) {
        errEl.textContent = 'Please select a payment method (UPI or Bank Transfer).';
        errEl.style.display = 'block';
        return false;
    }
    if (method.value === 'upi' && !upiId) {
        errEl.textContent = 'Please enter your UPI ID.';
        errEl.style.display = 'block';
        return false;
    }
    if (method.value === 'bank') {
        if (!bankName || !accNo || !ifsc) {
            errEl.textContent = 'Please fill in all bank details (Bank Name, Account Number, IFSC Code).';
            errEl.style.display = 'block';
            return false;
        }
        if (!/^[A-Z]{4}0[A-Z0-9]{6}$/.test(ifsc.toUpperCase())) {
            errEl.textContent = 'Please enter a valid IFSC code (e.g. SBIN0001234).';
            errEl.style.display = 'block';
            return false;
        }
    }
    return true;
}

/* ── Auto-dismiss alerts after 6 seconds ── */
(function() {
    var alert = document.getElementById('pageAlert');
    if (alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity    = '0';
            setTimeout(function() { alert.remove(); }, 500);
        }, 6000);
    }
})();

/* ── If page loads with error on withdraw form, reopen modal ── */
<?php if (!empty($error) && isset($_POST['request_withdrawal'])): ?>
document.addEventListener('DOMContentLoaded', function() {
    openModal('withdraw-modal');
});
<?php endif; ?>
</script>

<?php include_once '../../includes/footer.php'; ?>
