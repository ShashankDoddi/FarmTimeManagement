<?php
session_start();
require_once 'config/database.php';

// Already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username         = trim($_POST['username'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $permission_level = $_POST['permission_level'] ?? 'manager';
    $site_id          = intval($_POST['site_id'] ?? 0);
    $contact_number   = trim($_POST['contact_number'] ?? '');

    // Validate
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif ($site_id <= 0) {
        $error = 'Please select a site.';
    } else {
        $conn = getConnection();

        // Check duplicate username
        $stmt = $conn->prepare("SELECT admin_id FROM admin WHERE username = ? LIMIT 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $error = 'Username already taken. Please choose another.';
            $stmt->close();
        } else {
            $stmt->close();

            // Check duplicate email
            $stmt2 = $conn->prepare("SELECT admin_id FROM admin WHERE email = ? LIMIT 1");
            $stmt2->bind_param('s', $email);
            $stmt2->execute();
            $stmt2->store_result();

            if ($stmt2->num_rows > 0) {
                $error = 'Email address already in use.';
                $stmt2->close();
            } else {
                $stmt2->close();

                // Insert new admin
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $insertStmt = $conn->prepare("
                    INSERT INTO admin (site_id, username, password_hash, permission_level, contact_number, email, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'active')
                ");
                $insertStmt->bind_param('isssss',
                    $site_id, $username, $password_hash,
                    $permission_level, $contact_number, $email
                );

                if ($insertStmt->execute()) {
                    $newId = $conn->insert_id;
                    auditLog($conn, 'CREATE', 'admin', $newId, 'New admin registered');
                    $success = "Account <strong>$username</strong> created! You can now login.";
                } else {
                    $error = 'Registration failed. Please try again.';
                }
                $insertStmt->close();
            }
        }
        $conn->close();
    }
}

// Load sites
$conn  = getConnection();
$sites = $conn->query("SELECT site_id, site_name FROM sites ORDER BY site_name")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — Workforce</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1a1a2e, #16213e, #0f3460);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .box {
            background: #fff;
            border-radius: 16px;
            padding: 40px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo { text-align:center; margin-bottom:28px; }
        .logo .icon { font-size:38px; display:block; margin-bottom:8px; }
        .logo h1 { font-size:22px; font-weight:700; color:#1a1a2e; }
        .logo p  { font-size:13px; color:#888; margin-top:4px; }

        .row-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .form-group { margin-bottom:16px; }
        label { display:block; font-size:13px; font-weight:600; color:#444; margin-bottom:5px; }
        label span { color:#dc2626; }
        input, select {
            width:100%; padding:11px 14px;
            border:1.5px solid #e0e0e0; border-radius:8px;
            font-size:14px; outline:none;
            transition: border-color 0.2s;
        }
        input:focus, select:focus {
            border-color:#4f46e5;
            box-shadow:0 0 0 3px rgba(79,70,229,0.1);
        }
        .btn {
            width:100%; padding:13px;
            background:#4f46e5; color:#fff;
            border:none; border-radius:8px;
            font-size:15px; font-weight:600;
            cursor:pointer; transition:background 0.2s;
            margin-top:4px;
        }
        .btn:hover { background:#4338ca; }
        .error   { background:#fff5f5; border:1px solid #fecaca; color:#dc2626; padding:12px 16px; border-radius:8px; font-size:13px; margin-bottom:18px; }
        .success { background:#f0fdf4; border:1px solid #bbf7d0; color:#16a34a; padding:12px 16px; border-radius:8px; font-size:13px; margin-bottom:18px; }
        .link-row { text-align:center; margin-top:20px; font-size:13px; color:#888; }
        .link-row a { color:#4f46e5; font-weight:600; text-decoration:none; }
        .strength-bar { height:4px; background:#eee; border-radius:4px; margin-top:6px; overflow:hidden; }
        .strength-fill { height:100%; border-radius:4px; transition:width 0.3s, background 0.3s; width:0; }
        .strength-text { font-size:11px; margin-top:3px; color:#aaa; }
        .section-label { font-size:11px; font-weight:700; color:#aaa; text-transform:uppercase; letter-spacing:1px; margin:18px 0 10px; border-bottom:1px solid #f0f0f0; padding-bottom:6px; }
    </style>
</head>
<body>
<div class="box">
    <div class="logo">
        <span class="icon">⏱</span>
        <h1>Create Account</h1>
        <p>Workforce Management System</p>
    </div>

    <?php if ($error):   ?><div class="error">⚠️ <?= $error ?></div><?php endif; ?>
    <?php if ($success): ?><div class="success">✅ <?= $success ?> <a href="login.php">Login →</a></div><?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST" action="register.php">

        <div class="section-label">Account Details</div>
        <div class="row-2">
            <div class="form-group">
                <label>Username <span>*</span></label>
                <input type="text" name="username"
                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    placeholder="e.g. john_doe" required autofocus>
            </div>
            <div class="form-group">
                <label>Contact Number</label>
                <input type="text" name="contact_number"
                    value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>"
                    placeholder="0412 345 678">
            </div>
        </div>

        <div class="form-group">
            <label>Email Address <span>*</span></label>
            <input type="email" name="email"
                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                placeholder="john@company.com" required>
        </div>

        <div class="section-label">Role & Site</div>
        <div class="row-2">
            <div class="form-group">
                <label>Site <span>*</span></label>
                <select name="site_id" required>
                    <option value="">— Select Site —</option>
                    <?php foreach ($sites as $site): ?>
                        <option value="<?= $site['site_id'] ?>"
                            <?= (($_POST['site_id'] ?? '') == $site['site_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($site['site_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Permission Level <span>*</span></label>
                <select name="permission_level">
                    <option value="viewer"     <?= (($_POST['permission_level'] ?? '') === 'viewer')     ? 'selected' : '' ?>>Viewer</option>
                    <option value="hr"         <?= (($_POST['permission_level'] ?? '') === 'hr')         ? 'selected' : '' ?>>HR</option>
                    <option value="manager"    <?= (($_POST['permission_level'] ?? 'manager') === 'manager') ? 'selected' : '' ?>>Manager</option>
                    <option value="superadmin" <?= (($_POST['permission_level'] ?? '') === 'superadmin') ? 'selected' : '' ?>>Super Admin</option>
                </select>
            </div>
        </div>

        <div class="section-label">Password</div>
        <div class="form-group">
            <label>Password <span>*</span></label>
            <input type="password" name="password" id="password"
                placeholder="At least 8 characters" required
                oninput="checkStrength(this.value)">
            <div class="strength-bar"><div class="strength-fill" id="sBar"></div></div>
            <div class="strength-text" id="sText">Enter a password</div>
        </div>
        <div class="form-group">
            <label>Confirm Password <span>*</span></label>
            <input type="password" name="confirm_password"
                placeholder="Re-enter your password" required>
        </div>

        <button type="submit" class="btn">Create Account</button>
    </form>
    <?php endif; ?>

    <div class="link-row">Already have an account? <a href="login.php">Login here</a></div>
</div>

<script>
function checkStrength(p) {
    let s = 0;
    if (p.length >= 8)           s++;
    if (/[A-Z]/.test(p))         s++;
    if (/[0-9]/.test(p))         s++;
    if (/[^A-Za-z0-9]/.test(p)) s++;
    const levels = [
        {w:'0%',   c:'#eee',    t:'Enter a password'},
        {w:'25%',  c:'#ef4444', t:'Weak'},
        {w:'50%',  c:'#f97316', t:'Fair'},
        {w:'75%',  c:'#eab308', t:'Good'},
        {w:'100%', c:'#22c55e', t:'Strong'},
    ];
    document.getElementById('sBar').style.cssText  = `width:${levels[s].w};background:${levels[s].c}`;
    document.getElementById('sText').textContent   = levels[s].t;
    document.getElementById('sText').style.color   = levels[s].c;
}
</script>
</body>
</html>
