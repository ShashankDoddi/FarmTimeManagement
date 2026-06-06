<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['admin_id'])) {
    $level = strtolower($_SESSION['permission_level'] ?? '');
    if ($level === 'superadmin') header('Location: adminDashboard.php');
    elseif (in_array($level, ['rosteradmin','manager','siteadmin'])) header('Location: rosterAdminDashboard.php');
    else header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        $conn = getConnection();
        $stmt = $conn->prepare("SELECT a.*, s.site_name FROM admin a LEFT JOIN sites s ON a.site_id=s.site_id WHERE a.username=? OR a.email=? LIMIT 1");
        $stmt->bind_param('ss', $username, $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$admin) {
            $error = 'No account found with that username or email.';
        } elseif (strtolower($admin['status']) !== 'active') {
            $error = 'Your account is deactivated. Contact your administrator.';
        } elseif (!password_verify($password, $admin['password_hash'])) {
            $error = 'Incorrect password. Please try again.';
        } else {
            session_regenerate_id(true);
            $_SESSION['admin_id']         = $admin['admin_id'];
            $_SESSION['username']         = $admin['username'];
            $_SESSION['email']            = $admin['email'];
            $_SESSION['permission_level'] = strtolower($admin['permission_level']);
            $_SESSION['site_id']          = $admin['site_id'];
            $_SESSION['site_name']        = $admin['site_name'] ?? '';
            $conn->close();

            $level = strtolower($admin['permission_level']);
            if ($level === 'superadmin') header('Location: adminDashboard.php');
            elseif (in_array($level, ['rosteradmin','manager','siteadmin'])) header('Location: rosterAdminDashboard.php');
            else header('Location: dashboard.php');
            exit();
        }
        if (isset($conn)) $conn->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Login — Farm TMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
    <style>
        :root { --brand:#696c2b; --brand-hover:#5b5e24; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:Arial,sans-serif; background:var(--brand); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .login-card { background:#fff; border-radius:14px; padding:40px 36px; width:100%; max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,0.25); }
        .brand { text-align:center; margin-bottom:28px; }
        .brand-icon { font-size:2.5rem; color:var(--brand); display:block; margin-bottom:8px; }
        .brand-name { font-size:1.5rem; font-weight:700; color:var(--brand); }
        .brand-sub { font-size:0.85rem; color:#6c757d; margin-top:4px; }
        .card-title { font-size:1.1rem; font-weight:700; color:#212529; margin-bottom:4px; }
        .card-sub { font-size:0.85rem; color:#6c757d; margin-bottom:24px; }
        .error-box { background:#fff5f5; border:1px solid #fecaca; color:#dc2626; padding:10px 14px; border-radius:8px; font-size:0.88rem; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
        .form-group { margin-bottom:16px; }
        .form-group label { display:block; font-size:0.85rem; font-weight:600; color:#374151; margin-bottom:6px; }
        .input-wrap { position:relative; }
        .input-icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#aaa; font-size:0.9rem; }
        .form-input { width:100%; padding:11px 14px 11px 36px; border:1.5px solid #e5e7eb; border-radius:8px; font-size:0.9rem; outline:none; transition:border-color 0.2s, box-shadow 0.2s; }
        .form-input:focus { border-color:var(--brand); box-shadow:0 0 0 3px rgba(105,108,43,0.12); }
        .toggle-pass { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; color:#aaa; cursor:pointer; font-size:0.9rem; }
        .options-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; font-size:0.85rem; }
        .remember { display:flex; align-items:center; gap:6px; color:#555; cursor:pointer; }
        .remember input { accent-color:var(--brand); }
        .forgot { color:var(--brand); text-decoration:none; font-weight:600; }
        .forgot:hover { text-decoration:underline; }
        .btn-login { width:100%; padding:12px; background:var(--brand); color:#fff; border:none; border-radius:8px; font-size:0.95rem; font-weight:600; cursor:pointer; transition:background 0.2s; display:flex; align-items:center; justify-content:center; gap:8px; }
        .btn-login:hover { background:var(--brand-hover); }
        .redirect-info { margin-top:18px; background:#f8f9f0; border:1px solid #e8e8d0; border-radius:8px; padding:12px 14px; font-size:0.82rem; color:#555; }
        .redirect-info strong { color:var(--brand); }
        .redirect-row { display:flex; align-items:center; gap:8px; padding:2px 0; }
        .dot { width:6px; height:6px; border-radius:50%; flex-shrink:0; }
        .footer { text-align:center; margin-top:18px; font-size:0.78rem; color:rgba(255,255,255,0.5); }
    </style>
</head>
<body>
<div style="width:100%;max-width:420px;">
    <div class="brand">
        <i class="bi bi-clock-history brand-icon"></i>
        <div class="brand-name">Farm TMS</div>
        <div class="brand-sub">Farm Time Management System</div>
    </div>

    <div class="login-card">
        <div class="card-title">Welcome back</div>
        <div class="card-sub">Sign in to continue to your dashboard</div>

        <?php if ($error): ?>
        <div class="error-box"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Username or Email</label>
                <div class="input-wrap">
                    <i class="bi bi-person input-icon"></i>
                    <input type="text" name="username" class="form-input"
                        placeholder="e.g. superadmin01"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        required autofocus/>
                </div>
            </div>
            <div class="form-group">
                <label>Password</label>
                <div class="input-wrap">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" name="password" id="pwdInput" class="form-input"
                        placeholder="Enter your password" required/>
                    <button type="button" class="toggle-pass" onclick="togglePwd()">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <div class="options-row">
                <label class="remember"><input type="checkbox" name="remember"/> Remember me</label>
                <a href="forgot_password.php" class="forgot">Forgot password?</a>
            </div>
            <button type="submit" class="btn-login">
                <i class="bi bi-box-arrow-in-right"></i> Sign In
            </button>
        </form>

        <div class="redirect-info">
            <div style="font-weight:700;color:var(--brand);margin-bottom:6px;"><i class="bi bi-info-circle me-1"></i> Auto-redirect by role:</div>
            <div class="redirect-row"><div class="dot" style="background:#d97706;"></div><strong>Super Admin</strong> → Admin Dashboard</div>
            <div class="redirect-row"><div class="dot" style="background:#2563eb;"></div><strong>Roster Admin / Manager</strong> → Roster Dashboard</div>
            <div class="redirect-row"><div class="dot" style="background:#16a34a;"></div><strong>Others</strong> → Main Dashboard</div>
        </div>
    </div>

    <div class="footer">Farm Time Management System &copy; <?= date('Y') ?></div>
</div>
<script>
function togglePwd() {
    const i = document.getElementById('pwdInput');
    const e = document.getElementById('eyeIcon');
    i.type = i.type==='password' ? 'text' : 'password';
    e.className = i.type==='password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>
</body>
</html>
