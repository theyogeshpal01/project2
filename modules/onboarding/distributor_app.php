<?php 
include_once '../../includes/header.php'; 
include_once '../../core/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_app'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $biz_name = $_POST['business_name'];
    $gst = $_POST['gst'];
    $city = $_POST['city'];

    try {
        $pdo->beginTransaction();
        
        // 1. Create User (Step 2)
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role_id, status) VALUES (?, ?, 'pending_setup', 5, 'inactive')");
        $stmt->execute([$name, $email]);
        $user_id = $pdo->lastInsertId();

        // 2. Add Business Details
        $stmtBiz = $pdo->prepare("INSERT INTO distributor_details (user_id, business_name, gst_number, city) VALUES (?, ?, ?, ?)");
        $stmtBiz->execute([$user_id, $biz_name, $gst, $city]);

        $pdo->commit();
        $success = "Application submitted! Next step: Complete KYC.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="header-actions" style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.5rem; margin-bottom: 0.25rem;">Distributor Application Portal</h1>
    <p style="color: var(--text-muted); font-size: 0.875rem;">Capture business information and initiate the distributor onboarding lifecycle.</p>
</div>

<div style="max-width: 800px; margin: 0 auto;">
    <div class="glass-card" style="padding: 2.5rem;">
        <div style="display: flex; gap: 2rem; margin-bottom: 3rem; justify-content: center;">
            <div style="text-align: center; opacity: 1;">
                <div style="width: 40px; height: 40px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-weight: bold;">1</div>
                <div style="font-size: 0.75rem; font-weight: 600;">Application</div>
            </div>
            <div style="width: 60px; height: 2px; background: var(--border); margin-top: 20px;"></div>
            <div style="text-align: center; opacity: 0.3;">
                <div style="width: 40px; height: 40px; background: var(--bg-main); color: var(--text-muted); border-radius: 50%; border: 2px solid var(--border); display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-weight: bold;">2</div>
                <div style="font-size: 0.75rem; font-weight: 600;">KYC</div>
            </div>
            <div style="width: 60px; height: 2px; background: var(--border); margin-top: 20px;"></div>
            <div style="text-align: center; opacity: 0.3;">
                <div style="width: 40px; height: 40px; background: var(--bg-main); color: var(--text-muted); border-radius: 50%; border: 2px solid var(--border); display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-weight: bold;">3</div>
                <div style="font-size: 0.75rem; font-weight: 600;">Activation</div>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div style="padding: 1.5rem; background: rgba(16, 185, 129, 0.1); border: 1px solid var(--success); color: var(--success); border-radius: 12px; margin-bottom: 2rem; text-align: center;">
                <h3 style="margin-bottom: 0.5rem;">Success!</h3>
                <p><?php echo $success; ?></p>
                <a href="../kyc/verify.php" class="btn btn-primary" style="margin-top: 1rem;">Go to KYC Portal</a>
            </div>
        <?php else: ?>

        <form method="POST">
            <h3 style="margin-bottom: 1.5rem; font-size: 1.125rem;">Basic Information</h3>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--text-muted);">Contact Name</label>
                    <input type="text" name="name" required placeholder="Full Name" style="width: 100%; padding: 0.875rem; border-radius: 10px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--text-muted);">Email Address</label>
                    <input type="email" name="email" required placeholder="email@business.com" style="width: 100%; padding: 0.875rem; border-radius: 10px;">
                </div>
            </div>

            <h3 style="margin-top: 2.5rem; margin-bottom: 1.5rem; font-size: 1.125rem;">Business Information</h3>
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--text-muted);">Legal Business Name</label>
                <input type="text" name="business_name" required placeholder="Enter Company/Entity Name" style="width: 100%; padding: 0.875rem; border-radius: 10px;">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem;">
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--text-muted);">GST Number (Optional)</label>
                    <input type="text" name="gst" placeholder="22AAAAA0000A1Z5" style="width: 100%; padding: 0.875rem; border-radius: 10px;">
                </div>
                <div>
                    <label style="display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: var(--text-muted);">Operating City</label>
                    <input type="text" name="city" required placeholder="e.g. Mumbai" style="width: 100%; padding: 0.875rem; border-radius: 10px;">
                </div>
            </div>

            <div style="border-top: 1px solid var(--border); padding-top: 2rem; display: flex; justify-content: flex-end;">
                <button type="submit" name="submit_app" class="btn btn-primary" style="padding: 1rem 3rem;">
                    Submit Application
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?>
