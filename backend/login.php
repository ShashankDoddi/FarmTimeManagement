<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

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
            WHERE a.username = ?
            LIMIT 1
        ");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$admin) {
            $error = 'Invalid username or password.';
        } elseif ($admin['status'] !== 'active') {
            $error = 'Your account is deactivated. Contact your administrator.';
        } elseif (!password_verify($password, $admin['password_hash'])) {
            // Log failed attempt
            $log = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, target_table, target_id, reason, source_channel) VALUES (?, 'LOGIN_FAILED', 'admin', ?, 'Wrong password', 'web')");
            $log->bind_param('ii', $admin['admin_id'], $admin['admin_id']);
            $log->execute();
            $log->close();
            $error = 'Invalid username or password.';
        } else {
            // ✅ Success
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Workforce</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family:'Segoe UI',sans-serif;
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);
            min-height:100vh;
            display:flex; align-items:center; justify-content:center;
        }
        .box {
            background:#fff; border-radius:16px; padding:44px 40px;
            width:100%; max-width:400px;
            box-shadow:0 20px 60px rgba(0,0,0,0.3);
        }
        .logo { text-align:center; margin-bottom:32px; }
        .logo .icon { font-size:42px; display:block; margin-bottom:8px; }
        .logo h1 { font-size:22px; font-weight:700; color:#1a1a2e; }
        .logo p  { font-size:13px; color:#888; margin-top:4px; }
        .form-group { margin-bottom:20px; }
        label { display:block; font-size:13px; font-weight:600; color:#444; margin-bottom:6px; }
        input {
            width:100%; padding:12px 16px;
            border:1.5px solid #e0e0e0; border-radius:8px;
            font-size:15px; outline:none;
            transition: border-color 0.2s;
        }
        input:focus { border-color:#4f46e5; box-shadow:0 0 0 3px rgba(79,70,229,0.12); }
        .btn {
            width:100%; padding:13px;
            background:#4f46e5; color:#fff;
            border:none; border-radius:8px;
            font-size:15px; font-weight:600;
            cursor:pointer; transition:background 0.2s;
        }
        .btn:hover { background:#4338ca; }
        .error { background:#fff5f5; border:1px solid #fecaca; color:#dc2626; padding:12px 16px; border-radius:8px; font-size:13px; margin-bottom:20px; }
        .link-row { text-align:center; margin-top:22px; font-size:13px; color:#888; }
        .link-row a { color:#4f46e5; font-weight:600; text-decoration:none; }
    </style>
</head>
<body>
<div class="box">
    <div class="logo">
        <span class="icon">⏱</span>
        <h1>Workforce Management</h1>
        <p>Sign in to your account</p>
    </div>

    <?php if ($error): ?>
        <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username"
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                placeholder="Enter your username" required autofocus>
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password"
                placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn">Sign In</button>
    </form>

    <div class="link-row">Don't have an account? <a href="register.php">Register here</a></div>
</div>
</body>
</html>
