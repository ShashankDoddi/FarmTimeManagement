<?php
session_start();
require_once 'config/database.php';

// Already logged in
if (isset($_SESSION['admin_id'])) {
    $level = strtolower($_SESSION['permission_level'] ?? '');
    if ($level === 'superadmin') header('Location: adminDashboard.php');
    elseif (in_array($level, ['rosteradmin','manager','siteadmin'])) header('Location: exception_roster.php');
    else header('Location: dashboard.php');
    exit();
}
if (isset($_SESSION['staff_id']) && !isset($_SESSION['must_change_password'])) {
    header('Location: ../frontend/client/dashboard.php');
    exit();
}

$error   = '';
$page    = 'login';

// ── FORCE PASSWORD CHANGE (staff first login) ─────────────────
if (isset($_SESSION['must_change_password']) && isset($_SESSION['staff_id'])) {
    $page = 'change_password';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'change_password') {
        $newPass  = trim($_POST['new_password'] ?? '');
        $confPass = trim($_POST['confirm_password'] ?? '');

        if (strlen($newPass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($newPass === '123456') {
            $error = 'You cannot keep the default password. Please choose a new one.';
        } elseif ($newPass !== $confPass) {
            $error = 'Passwords do not match.';
        } else {
            $conn    = getConnection();
            $hash    = password_hash($newPass, PASSWORD_BCRYPT);
            $staffId = $_SESSION['staff_id'];
            $stmt    = $conn->prepare("UPDATE staff SET password_hash=? WHERE staff_id=?");
            $stmt->bind_param('si', $hash, $staffId);
            $stmt->execute();
            $stmt->close();
            $conn->close();

            // Destroy session — force re-login with new password
            session_unset();
            session_destroy();
            session_start();
            header('Location: login.php?msg=password_changed');
            exit();
        }
    }
}

// ── LOGIN ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action']??'') === 'login') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $conn = getConnection();

        // ── Check ADMIN table first ──
        $stmt = $conn->prepare("SELECT a.*,s.site_name FROM admin a LEFT JOIN sites s ON a.site_id=s.site_id WHERE a.email=? OR a.username=? LIMIT 1");
        $stmt->bind_param('ss', $email, $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($admin) {
            // Found in admin table
            if (strtolower($admin['status']) !== 'active') {
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
                elseif (in_array($level, ['rosteradmin','manager','siteadmin'])) header('Location: exception_roster.php');
                else header('Location: dashboard.php');
                exit();
            }
        } else {
            // ── Check STAFF table ──
            $stmt = $conn->prepare("SELECT staff_id,first_name,last_name,staff_number,contact_email,password_hash,status FROM staff WHERE contact_email=? LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $staff = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$staff) {
                $error = 'No account found with that email address.';
            } elseif (strtolower($staff['status']) !== 'active') {
                $error = 'Your account is inactive. Contact your administrator.';
            } else {
                // Auto-set default hash if empty
                $storedHash = $staff['password_hash'] ?? '';
                if (empty($storedHash)) {
                    $defHash = password_hash('123456', PASSWORD_BCRYPT);
                    $upd = $conn->prepare("UPDATE staff SET password_hash=? WHERE staff_id=?");
                    $upd->bind_param('si', $defHash, $staff['staff_id']);
                    $upd->execute(); $upd->close();
                    $storedHash = $defHash;
                }

                if (!password_verify($password, $storedHash)) {
                    $error = 'Incorrect password. Please try again.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['staff_id']     = $staff['staff_id'];
                    $_SESSION['staff_name']   = $staff['first_name'] . ' ' . $staff['last_name'];
                    $_SESSION['staff_number'] = $staff['staff_number'];
                    $_SESSION['staff_email']  = $staff['contact_email'];

                    // First time login — default password
                    if (password_verify('123456', $storedHash)) {
                        $_SESSION['must_change_password'] = true;
                        $conn->close();
                        header('Location: login.php');
                        exit();
                    }

                    $conn->close();
                    header('Location: ../frontend/client/dashboard.php');
                    exit();
                }
            }
        }
        if (isset($conn) && $conn) $conn->close();
    }
}

$pwChanged = isset($_GET['msg']) && $_GET['msg'] === 'password_changed';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title><?= $page==='change_password'?'Set New Password':'Login' ?> — Farm TMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
    <style>
        :root { --brand:#696c2b; --brand-hover:#5b5e24; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:Arial,sans-serif; background:var(--brand); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .card { background:#fff; border-radius:14px; padding:40px 36px; width:100%; max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,0.25); }
        .brand { text-align:center; margin-bottom:28px; }
        .brand i { font-size:2.5rem; color:var(--brand); display:block; margin-bottom:8px; }
        .brand-name { font-size:1.5rem; font-weight:700; color:var(--brand); }
        .brand-sub  { font-size:0.85rem; color:#6c757d; margin-top:4px; }
        .card-title { font-size:1.1rem; font-weight:700; color:#212529; margin-bottom:4px; }
        .card-sub   { font-size:0.85rem; color:#6c757d; margin-bottom:24px; }
        .error-box   { background:#fff5f5; border:1px solid #fecaca; color:#dc2626; padding:10px 14px; border-radius:8px; font-size:0.88rem; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
        .success-box { background:#f0fdf4; border:1px solid #bbf7d0; color:#16a34a; padding:10px 14px; border-radius:8px; font-size:0.88rem; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
        .warn-box    { background:#fffbeb; border:1px solid #fde68a; color:#92400e; padding:12px 14px; border-radius:8px; font-size:0.88rem; margin-bottom:18px; }
        .form-group  { margin-bottom:16px; }
        .form-group label { display:block; font-size:0.85rem; font-weight:600; color:#374151; margin-bottom:6px; }
        .input-wrap  { position:relative; }
        .input-icon  { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#aaa; font-size:0.9rem; }
        .form-input  { width:100%; padding:11px 38px; border:1.5px solid #e5e7eb; border-radius:8px; font-size:0.9rem; outline:none; transition:border-color 0.2s,box-shadow 0.2s; }
        .form-input:focus { border-color:var(--brand); box-shadow:0 0 0 3px rgba(105,108,43,0.12); }
        .toggle-pass { position:absolute; right:12px; top:50%; transform:translateY(-50%); background:none; border:none; color:#aaa; cursor:pointer; }
        .btn-login   { width:100%; padding:12px; background:var(--brand); color:#fff; border:none; border-radius:8px; font-size:0.95rem; font-weight:600; cursor:pointer; transition:background 0.2s; display:flex; align-items:center; justify-content:center; gap:8px; }
        .btn-login:hover { background:var(--brand-hover); }
        .redirect-info { margin-top:18px; background:#f8f9f0; border:1px solid #e8e8d0; border-radius:8px; padding:12px 14px; font-size:0.82rem; color:#555; }
        .redirect-row { display:flex; align-items:center; gap:8px; padding:2px 0; }
        .dot { width:6px; height:6px; border-radius:50%; flex-shrink:0; }
        .footer { text-align:center; margin-top:16px; font-size:0.82rem; color:rgba(255,255,255,0.5); }
        .steps { display:flex; align-items:center; margin-bottom:24px; }
        .step-item   { display:flex; flex-direction:column; align-items:center; flex:1; }
        .step-circle { width:28px; height:28px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700; background:#e9ecef; color:#aaa; }
        .step-circle.active { background:var(--brand); color:#fff; }
        .step-circle.done   { background:#16a34a; color:#fff; }
        .step-label  { font-size:11px; color:#aaa; margin-top:3px; }
        .step-label.active { color:var(--brand); font-weight:600; }
        .step-line   { flex:1; height:2px; background:#e9ecef; margin:0 4px 14px; }
        .step-line.done { background:#16a34a; }
        .strength-bar  { height:4px; background:#eee; border-radius:4px; margin-top:6px; overflow:hidden; }
        .strength-fill { height:100%; border-radius:4px; transition:width 0.3s,background 0.3s; width:0; }
        .strength-text { font-size:11px; color:#aaa; margin-top:3px; }
    </style>
</head>
<body>
<div style="width:100%;max-width:420px;">
<div class="card">
    <div class="brand">
        <i class="bi bi-clock-history"></i>
        <div class="brand-name">Farm TMS</div>
        <div class="brand-sub">Farm Time Management System</div>
    </div>

    <?php if ($page === 'login'): ?>
    <!-- ── LOGIN ── -->
    <div class="card-title">Welcome back</div>
    <div class="card-sub">Sign in with your email address</div>

    <?php if ($pwChanged): ?>
    <div class="success-box"><i class="bi bi-check-circle-fill"></i> Password changed! Please log in with your new password.</div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="error-box"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="action" value="login">
        <div class="form-group">
            <label>Email Address</label>
            <div class="input-wrap">
                <i class="bi bi-envelope input-icon"></i>
                <input type="email" name="email" class="form-input"
                    placeholder="your@email.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required autofocus/>
            </div>
        </div>
        <div class="form-group">
            <label>Password</label>
            <div class="input-wrap">
                <i class="bi bi-lock input-icon"></i>
                <input type="password" name="password" id="pwdInput" class="form-input"
                    placeholder="Enter your password" required/>
                <button type="button" class="toggle-pass" onclick="togglePwd('pwdInput','eye1')">
                    <i class="bi bi-eye" id="eye1"></i>
                </button>
            </div>
        </div>
        <button type="submit" class="btn-login">
            <i class="bi bi-box-arrow-in-right"></i> Sign In
        </button>
    </form>

    <div class="redirect-info">
        <div style="font-weight:700;color:var(--brand);margin-bottom:6px;"><i class="bi bi-info-circle me-1"></i> Auto-redirect by role:</div>
        <div class="redirect-row"><div class="dot" style="background:#d97706;"></div><strong>Super Admin</strong> → Admin Dashboard</div>
        <div class="redirect-row"><div class="dot" style="background:#2563eb;"></div><strong>Manager / Roster Admin</strong> → Roster Dashboard</div>
        <div class="redirect-row"><div class="dot" style="background:#16a34a;"></div><strong>Staff / Worker</strong> → Staff Dashboard</div>
    </div>

    <?php elseif ($page === 'change_password'): ?>
    <!-- ── FORCE CHANGE PASSWORD ── -->
    <div class="steps">
        <div class="step-item">
            <div class="step-circle done">✓</div>
            <div class="step-label">Login</div>
        </div>
        <div class="step-line done"></div>
        <div class="step-item">
            <div class="step-circle active">2</div>
            <div class="step-label active">New Password</div>
        </div>
        <div class="step-line"></div>
        <div class="step-item">
            <div class="step-circle">3</div>
            <div class="step-label">Done</div>
        </div>
    </div>

    <div class="card-title">Set Your Password</div>
    <div class="card-sub">Welcome! Please set a personal password before continuing.</div>

    <div class="warn-box">
        <i class="bi bi-shield-exclamation me-1"></i>
        <strong>First time login detected.</strong> You must set a new personal password to continue.
    </div>

    <?php if ($error): ?>
    <div class="error-box"><i class="bi bi-exclamation-circle-fill"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="action" value="change_password">
        <div class="form-group">
            <label>New Password</label>
            <div class="input-wrap">
                <i class="bi bi-lock input-icon"></i>
                <input type="password" name="new_password" id="newPwd" class="form-input"
                    placeholder="At least 6 characters" required
                    oninput="checkStrength(this.value)"/>
                <button type="button" class="toggle-pass" onclick="togglePwd('newPwd','eye2')">
                    <i class="bi bi-eye" id="eye2"></i>
                </button>
            </div>
            <div class="strength-bar"><div class="strength-fill" id="sBar"></div></div>
            <div class="strength-text" id="sText">Enter a password</div>
        </div>
        <div class="form-group">
            <label>Confirm New Password</label>
            <div class="input-wrap">
                <i class="bi bi-lock input-icon"></i>
                <input type="password" name="confirm_password" id="confPwd" class="form-input"
                    placeholder="Re-enter your new password" required/>
                <button type="button" class="toggle-pass" onclick="togglePwd('confPwd','eye3')">
                    <i class="bi bi-eye" id="eye3"></i>
                </button>
            </div>
        </div>
        <button type="submit" class="btn-login">
            <i class="bi bi-check-circle me-1"></i> Set Password & Go to Login
        </button>
    </form>
    <?php endif; ?>
</div>
<div class="footer">Farm Time Management System &copy; <?= date('Y') ?></div>
</div>

<script>
function togglePwd(inputId, iconId) {
    const i = document.getElementById(inputId);
    const e = document.getElementById(iconId);
    if (!i || !e) return;
    i.type = i.type === 'password' ? 'text' : 'password';
    e.className = i.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
function checkStrength(p) {
    let s = 0;
    if (p.length >= 6)           s++;
    if (p.length >= 10)          s++;
    if (/[A-Z]/.test(p))         s++;
    if (/[0-9]/.test(p))         s++;
    if (/[^A-Za-z0-9]/.test(p)) s++;
    const lv = [
        {w:'0%',  c:'#eee',    t:'Enter a password'},
        {w:'25%', c:'#ef4444', t:'Weak'},
        {w:'50%', c:'#f97316', t:'Fair'},
        {w:'75%', c:'#eab308', t:'Good'},
        {w:'100%',c:'#22c55e', t:'Strong ✓'}
    ];
    const idx = Math.min(s, 4);
    document.getElementById('sBar').style.cssText = `width:${lv[idx].w};background:${lv[idx].c}`;
    document.getElementById('sText').textContent  = lv[idx].t;
    document.getElementById('sText').style.color  = lv[idx].c;
}
</script>
</body>
</html>
