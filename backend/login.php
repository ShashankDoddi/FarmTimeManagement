<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if (isset($_GET['error']) && $_GET['error'] === 'access_denied') {
    $error = 'Access denied. Your account does not have permission to access this system.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $conn = getConnection();

        $stmt = $conn->prepare("
            SELECT a.admin_id, a.username, a.password_hash, a.permission_level,
                   a.email, a.status, a.site_id, s.site_name
            FROM admin a
            LEFT JOIN sites s ON a.site_id = s.site_id
            WHERE a.username = ? OR a.email = ?
            LIMIT 1
        ");
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$admin) {
            $error = 'Invalid username or password.';
        } elseif ($admin['status'] !== 'active') {
            $error = 'Your account is deactivated. Contact your administrator.';
        } elseif ($admin['permission_level'] === 'viewer') {
            // ❌ Viewer cannot access admin dashboard
            $error = 'Access denied. Viewer accounts cannot access the admin dashboard.';
        } elseif (!password_verify($password, $admin['password_hash'])) {
            $log = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, target_table, target_id, reason, source_channel) VALUES (?, 'LOGIN_FAILED', 'admin', ?, 'Wrong password', 'web')");
            $log->bind_param('ii', $admin['admin_id'], $admin['admin_id']);
            $log->execute();
            $log->close();
            $error = 'Invalid username or password.';
        } else {
            session_regenerate_id(true);
            $_SESSION['admin_id']         = $admin['admin_id'];
            $_SESSION['username']         = $admin['username'];
            $_SESSION['email']            = $admin['email'];
            $_SESSION['permission_level'] = $admin['permission_level'];
            $_SESSION['site_id']          = $admin['site_id'];
            $_SESSION['site_name']        = $admin['site_name'];

            $log = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, target_table, target_id, reason, source_channel) VALUES (?, 'LOGIN', 'admin', ?, 'Admin logged in', 'web')");
            $log->bind_param('ii', $admin['admin_id'], $admin['admin_id']);
            $log->execute();
            $log->close();

            $conn->close();
            header('Location: dashboard.php');
            exit();
        }
        $conn->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Farm Time Login</title>
    <style>
        body { margin:0; font-family:Arial,sans-serif; background-color:#696c2b; display:flex; align-items:center; justify-content:center; min-height:100vh; }
        .login-wrapper { width:100%; padding:20px; }
        .login-card { max-width:420px; margin:0 auto; background:#fff; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,0.05); padding:32px 28px; }
        .brand { font-size:1.5rem; font-weight:700; color:#696c2b; margin-bottom:8px; }
        .subtitle { color:#6c757d; margin-bottom:24px; font-size:0.95rem; }
        .form-group { margin-bottom:18px; }
        .form-group label { display:block; margin-bottom:8px; font-weight:600; color:#343a40; font-size:0.95rem; }
        .form-group input { width:100%; padding:12px 14px; border:1px solid #dcdfe3; border-radius:10px; font-size:0.95rem; outline:none; box-sizing:border-box; transition:border-color 0.2s, box-shadow 0.2s; }
        .form-group input:focus { border-color:#696c2b; box-shadow:0 0 0 3px rgba(105,108,43,0.12); }
        .form-options { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; font-size:0.9rem; }
        .remember { display:flex; align-items:center; gap:8px; color:#495057; }
        .form-options a { color:#696c2b; text-decoration:none; font-weight:600; }
        .login-btn { width:100%; border:none; border-radius:10px; background:#696c2b; color:white; font-size:1rem; font-weight:600; padding:12px; cursor:pointer; transition:background 0.2s; }
        .login-btn:hover { background:#5b5e24; }
        .error-box { background:#fff5f5; border:1px solid #fecaca; color:#dc2626; padding:12px 14px; border-radius:10px; font-size:0.9rem; margin-bottom:18px; }
        @media (max-width:480px) { .login-card { padding:24px 20px; } .form-options { flex-direction:column; gap:10px; } }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="login-card">
        <div class="brand">Farm Time Admin</div>
        <p class="subtitle">Sign in to continue to the dashboard</p>

        <?php if ($error): ?>
            <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label>Username or Email</label>
                <input type="text" name="username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    placeholder="admin@farmtime.com" required autofocus />
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required />
            </div>
            <div class="form-options">
                <label class="remember">
                    <input type="checkbox" style="width:auto;" />
                    <span>Remember me</span>
                </label>
                <a href="forgot_password.php">Forgot password?</a>
            </div>
            <button type="submit" class="login-btn">Sign In</button>
        </form>
    </div>
</div>
</body>
</html>