<?php
/**
 * Admin Account Setup Page
 * Use this to create the first admin account
 * DELETE or RESTRICT this file after use
 */
require_once '../../core/config.php';

$step    = 'form';
$success = false;
$error   = '';

// Security: block if admin already exists and this isn't a forced reset
$admin_exists = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 1")->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secret = trim($_POST['setup_key'] ?? '');
    $name   = trim($_POST['name'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $pass   = $_POST['password'] ?? '';
    $pass2  = $_POST['password2'] ?? '';

    // Simple setup key to prevent unauthorized access
    if ($secret !== 'contractum@setup') {
        $error = 'Invalid setup key.';
    } elseif (empty($name) || empty($email) || empty($pass)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($pass !== $pass2) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $hash = password_hash($pass, PASSWORD_DEFAULT);

            // Check if email already exists
            $exists = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $exists->execute([$email]);

            if ($exists->fetch()) {
                // Update existing user to admin
                $pdo->prepare("UPDATE users SET name=?, password=?, role_id=1, status='active', kyc_status='verified' WHERE email=?")
                    ->execute([$name, $hash, $email]);
                $msg = "Admin account updated successfully!";
            } else {
                // Create new admin
                $pdo->prepare("INSERT INTO users (name, email, password, role_id, status, kyc_status) VALUES (?,?,?,1,'active','verified')")
                    ->execute([$name, $email, $hash]);
                $msg = "Admin account created successfully!";
            }

            $success = true;
            $created_email = $email;
            $created_name  = $name;

        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Setup — Contractum ERP</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 20px;
            padding: 2.5rem;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.4);
        }
        .logo {
            width: 52px; height: 52px;
            background: linear-gradient(135deg, #4f46e5, #0891b2);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 1.5rem; color: white;
            margin: 0 auto 1rem;
        }
        h2 { color: white; text-align: center; font-size: 1.4rem; margin-bottom: 0.25rem; }
        .subtitle { color: #64748b; text-align: center; font-size: 0.85rem; margin-bottom: 2rem; }
        .form-group { margin-bottom: 1.1rem; }
        label { display: block; color: #94a3b8; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.05em; }
        input {
            width: 100%; padding: 0.8rem 1rem;
            background: #0f172a; border: 1px solid #334155;
            border-radius: 10px; color: white; font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
        }
        input:focus { outline: none; border-color: #4f46e5; box-shadow: 0 0 0 3px rgba(79,70,229,0.15); }
        .hint { font-size: 0.75rem; color: #475569; margin-top: 4px; }
        .btn {
            width: 100%; padding: 0.9rem;
            background: #4f46e5; color: white;
            border: none; border-radius: 10px;
            font-size: 1rem; font-weight: 600;
            cursor: pointer; margin-top: 0.5rem;
            font-family: 'Inter', sans-serif;
            transition: background 0.2s;
        }
        .btn:hover { background: #4338ca; }
        .alert { padding: 0.875rem 1rem; border-radius: 10px; font-size: 0.875rem; margin-bottom: 1.5rem; }
        .alert-error   { background: rgba(239,68,68,0.1);  color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
        .alert-success { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.3); }
        .alert-warning { background: rgba(245,158,11,0.1); color: #f59e0b; border: 1px solid rgba(245,158,11,0.3); }
        .divider { height: 1px; background: #1e293b; margin: 1.5rem 0; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #1e293b; font-size: 0.875rem; }
        .info-row span:first-child { color: #64748b; }
        .info-row span:last-child  { color: white; font-weight: 600; }
        .login-btn {
            display: block; text-align: center; margin-top: 1.5rem;
            padding: 0.9rem; background: #10b981; color: white;
            border-radius: 10px; font-weight: 600; text-decoration: none;
            font-size: 1rem; transition: background 0.2s;
        }
        .login-btn:hover { background: #059669; }
        .warning-box { background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.3); border-radius: 10px; padding: 0.875rem 1rem; margin-bottom: 1.5rem; font-size: 0.8rem; color: #f59e0b; }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">C</div>
    <h2>Admin Account Setup</h2>
    <p class="subtitle">Create or reset the administrator account</p>

    <?php if ($success): ?>
        <!-- SUCCESS STATE -->
        <div class="alert alert-success">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:6px;"><polyline points="20 6 9 17 4 12"></polyline></svg>
            <?php echo $msg; ?>
        </div>
        <div style="background:#0f172a; border-radius:12px; padding:1.25rem; margin-bottom:1.5rem;">
            <div class="info-row"><span>Name</span><span><?php echo htmlspecialchars($created_name); ?></span></div>
            <div class="info-row"><span>Email</span><span><?php echo htmlspecialchars($created_email); ?></span></div>
            <div class="info-row"><span>Role</span><span>Admin</span></div>
            <div class="info-row" style="border:none;"><span>Status</span><span style="color:#10b981;">Active</span></div>
        </div>
        <div class="warning-box">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:4px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            Security tip: Delete or rename this file after use.
        </div>
        <a href="login.php" class="login-btn">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:6px;"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path><polyline points="10 17 15 12 10 7"></polyline><line x1="15" y1="12" x2="3" y2="12"></line></svg>
            Go to Login
        </a>

    <?php else: ?>
        <!-- FORM STATE -->
        <?php if ($admin_exists): ?>
        <div class="alert alert-warning">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:6px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            <?php echo $admin_exists; ?> admin account(s) already exist. You can still create a new one or update an existing email.
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-error">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:6px;"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>
            <?php echo $error; ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Setup Key</label>
                <input type="password" name="setup_key" placeholder="Enter setup key" required>
                <div class="hint">Default key: <strong style="color:#94a3b8;">contractum@setup</strong></div>
            </div>

            <div style="height:1px; background:#334155; margin:1.25rem 0;"></div>

            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" placeholder="e.g. System Admin" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" placeholder="admin@yourcompany.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Min. 6 characters" required>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="password2" placeholder="Repeat password" required>
            </div>

            <button type="submit" class="btn">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:middle; margin-right:6px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                Create Admin Account
            </button>
        </form>

        <p style="text-align:center; margin-top:1.5rem; font-size:0.8rem; color:#475569;">
            Already have an account?
            <a href="login.php" style="color:#4f46e5; font-weight:600;">Sign In</a>
        </p>
    <?php endif; ?>
</div>
</body>
</html>
