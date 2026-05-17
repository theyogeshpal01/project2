<?php
session_start();
include_once '../../core/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'];
    $password = $_POST['password'];

    // 1. Check for Super Admin Bypass (Setup Phase)
    if ($email === 'admin@contractum.com' && $password === 'admin123') {
        $_SESSION['user_id'] = 1;
        $_SESSION['role_id'] = 1;
        $_SESSION['role_name'] = 'Admin';
        $_SESSION['user_name'] = 'System Admin';
        $_SESSION['kyc_status'] = 'verified';
        
        header("Location: ../../index.php");
        exit();
    }

    // 2. Database Check
    $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role_id'] = $user['role_id'];
        $_SESSION['role_name'] = $user['role_name'];
        $_SESSION['user_name'] = $user['name'];

        header("Location: ../../index.php");
        exit();
    } else {
        $error = "Invalid credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Login - Contractum ERP</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background: #0f172a;
        }

        .login-card {
            width: 400px;
            padding: 2.5rem;
        }
    </style>
</head>

<body>
    <div class="glass-card login-card">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div
                style="width: 50px; height: 50px; background: var(--primary); color: white; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.5rem; margin: 0 auto 1rem;">
                C</div>
            <h2 style="color: black;">Contractum ERP</h2>
            <p style="color: #94a3b8; font-size: 0.875rem;">Sign in to your account</p>
        </div>

        <?php if (isset($error)): ?>
            <div
                style="padding: 0.75rem; background: rgba(239, 68, 68, 0.1); color: var(--danger); border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.875rem; text-align: center;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div style="margin-bottom: 1.25rem;">
                <label style="display: block; margin-bottom: 0.5rem; color: #94a3b8; font-size: 0.875rem;">Email
                    Address</label>
                <input type="email" name="email" required placeholder="name@company.com"
                    style="width: 100%; padding: 0.875rem; border-radius: 10px; background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); color: white;">
            </div>
            <div style="margin-bottom: 2rem;">
                <label
                    style="display: block; margin-bottom: 0.5rem; color: #94a3b8; font-size: 0.875rem;">Password</label>
                <input type="password" name="password" required placeholder="••••••••"
                    style="width: 100%; padding: 0.875rem; border-radius: 10px; background: rgba(255,255,255,0.05); border-color: rgba(255,255,255,0.1); color: white;">
            </div>
            <button type="submit" class="btn btn-primary text-center" style="width: 100%;text-align:Center;">Sign
                In</button>
        </form>

        <p style="text-align: center; margin-top: 2rem; color: #64748b; font-size: 0.75rem;">
            Role-based access will be assigned automatically.
        </p>
    </div>
</body>

</html>