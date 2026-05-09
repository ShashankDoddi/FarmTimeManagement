<?php
session_start();
require_once 'config/database.php';

// Must be logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// Only superadmin can create new admins
if ($_SESSION['permission_level'] !== 'superadmin') {
    header('Location: dashboard.php?error=no_permission');
    exit();
}

$conn    = getConnection();
$error   = '';
$success = '';
$adminId = $_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username         = trim($_POST['username'] ?? '');
    $email            = trim($_POST['email'] ?? '');
    $password         = trim($_POST['password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');
    $permission_level = $_POST['permission_level'] ?? 'manager';
    $site_id          = intval($_POST['site_id'] ?? 0);
    $contact_number   = trim($_POST['contact_number'] ?? '');

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
        $check = $conn->prepare("SELECT admin_id FROM admin WHERE username = ? LIMIT 1");
        $check->bind_param('s', $username);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = 'Username already taken.';
            $check->close();
        } else {
            $check->close();
            $check2 = $conn->prepare("SELECT admin_id FROM admin WHERE email = ? LIMIT 1");
            $check2->bind_param('s', $email);
            $check2->execute();
            $check2->store_result();

            if ($check2->num_rows > 0) {
                $error = 'Email already in use.';
                $check2->close();
            } else {
                $check2->close();
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("
                    INSERT INTO admin (site_id, username, password_hash, permission_level, contact_number, email, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'active')
                ");
                $stmt->bind_param('isssss', $site_id, $username, $password_hash, $permission_level, $contact_number, $email);

                if ($stmt->execute()) {
                    $newId = $conn->insert_id;
                    $log = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, target_table, target_id, reason, source_channel) VALUES (?, 'CREATE', 'admin', ?, 'New admin created', 'web')");
                    $log->bind_param('ii', $adminId, $newId);
                    $log->execute();
                    $log->close();
                    $success = "Admin account <strong>$username</strong> created successfully!";
                } else {
                    $error = 'Failed to create account.';
                }
                $stmt->close();
            }
        }
    }
}

$sites = $conn->query("SELECT site_id, site_name FROM sites ORDER BY site_name")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Create Admin — Farm Time</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        body { background-color:#f6f7fb; font-family:Arial,sans-serif; }
        .sidebar { min-height:100vh; background:#696c2b; color:white; }
        .sidebar .nav-link { color:rgba(255,255,255,0.85); border-radius:8px; margin-bottom:6px; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { background:rgba(255,255,255,0.15); color:#fff; }
        .brand { font-size:1.2rem; font-weight:700; padding:1.25rem 1rem; border-bottom:1px solid rgba(255,255,255,0.15); }
        .topbar { background:white; border-bottom:1px solid #e9ecef; }
        .card-box { border:none; border-radius:14px; box-shadow:0 2px 10px rgba(0,0,0,0.05); }
        .form-control:focus, .form-select:focus { border-color:#696c2b; box-shadow:0 0 0 3px rgba(105,108,43,0.12); }
        .btn-farm { background:#696c2b; color:white; border:none; }
        .btn-farm:hover { background:#5b5e24; color:white; }
        .strength-bar { height:4px; background:#eee; border-radius:4px; margin-top:6px; overflow:hidden; }
        .strength-fill { height:100%; border-radius:4px; transition:width 0.3s,background 0.3s; width:0; }
        .strength-text { font-size:11px; margin-top:3px; color:#aaa; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <aside class="col-lg-2 col-md-3 sidebar p-3">
            <div class="brand">Farm Time Admin</div>
            <nav class="nav flex-column mt-4">
                <a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                <a class="nav-link" href="staff.php"><i class="bi bi-people me-2"></i>Staff</a>
                <a class="nav-link" href="roster.php"><i class="bi bi-calendar-week me-2"></i>Rosters</a>
                <a class="nav-link" href="clockinout.php"><i class="bi bi-clock-history me-2"></i>Attendance</a>
                <a class="nav-link" href="devices.php"><i class="bi bi-hdd-network me-2"></i>Clock Stations</a>
                <a class="nav-link" href="exceptions.php"><i class="bi bi-exclamation-triangle me-2"></i>Exceptions</a>
                <a class="nav-link" href="reports.php"><i class="bi bi-file-earmark-bar-graph me-2"></i>Reports</a>
                <a class="nav-link" href="payroll.php"><i class="bi bi-receipt me-2"></i>Payslips</a>
                <a class="nav-link active" href="register.php"><i class="bi bi-person-plus me-2"></i>Create Admin</a>
                <a class="nav-link" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a>
                <a class="nav-link mt-3" href="logout.php" style="color:rgba(255,100,100,0.9);">
                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                </a>
            </nav>
        </aside>

        <main class="col-lg-10 col-md-9 px-0">
            <div class="topbar d-flex justify-content-between align-items-center px-4 py-3">
                <div>
                    <h4 class="mb-0">Create Admin Account</h4>
                    <small class="text-muted">Superadmin only — create new admin users</small>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted"><?= date('d M Y') ?></span>
                    <div class="fw-semibold"><?= htmlspecialchars($_SESSION['username']) ?></div>
                </div>
            </div>

            <div class="p-4">
                <div class="row justify-content-center">
                    <div class="col-lg-7">

                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show">
                                ⚠️ <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                ✅ <?= $success ?>
                                <a href="register.php" class="alert-link ms-2">Create another →</a>
                            </div>
                        <?php endif; ?>

                        <div class="card card-box p-4">
                            <form method="POST">

                                <div class="mb-2 pb-2 border-bottom" style="color:#696c2b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Account Info</div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Username *</label>
                                        <input type="text" class="form-control" name="username"
                                            value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                            placeholder="e.g. john_manager" required autofocus>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Contact Number</label>
                                        <input type="text" class="form-control" name="contact_number"
                                            value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>"
                                            placeholder="0412 345 678">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-semibold">Email *</label>
                                        <input type="email" class="form-control" name="email"
                                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                            placeholder="john@farmtime.com" required>
                                    </div>
                                </div>

                                <div class="mb-2 pb-2 border-bottom" style="color:#696c2b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Site & Permission</div>
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Site *</label>
                                        <select class="form-select" name="site_id" required>
                                            <option value="">— Select Site —</option>
                                            <?php foreach ($sites as $site): ?>
                                                <option value="<?= $site['site_id'] ?>"
                                                    <?= (($_POST['site_id'] ?? '') == $site['site_id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($site['site_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Permission Level *</label>
                                        <select class="form-select" name="permission_level">
                                            <option value="manager"    <?= (($_POST['permission_level'] ?? 'manager') === 'manager')    ? 'selected':'' ?>>Manager</option>
                                            <option value="hr"         <?= (($_POST['permission_level'] ?? '') === 'hr')         ? 'selected':'' ?>>HR</option>
                                            <option value="viewer"     <?= (($_POST['permission_level'] ?? '') === 'viewer')     ? 'selected':'' ?>>Viewer (no dashboard access)</option>
                                            <option value="superadmin" <?= (($_POST['permission_level'] ?? '') === 'superadmin') ? 'selected':'' ?>>Super Admin</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="alert py-2 mb-3" style="background:rgba(105,108,43,0.08);border:1px solid rgba(105,108,43,0.2);font-size:13px;">
                                    🔴 <strong>Super Admin</strong> — Full access + create admins &nbsp;|&nbsp;
                                    🟡 <strong>Manager</strong> — Staff, roster, payroll &nbsp;|&nbsp;
                                    🟢 <strong>HR</strong> — Staff & leave only &nbsp;|&nbsp;
                                    ⚪ <strong>Viewer</strong> — No dashboard access
                                </div>

                                <div class="mb-2 pb-2 border-bottom" style="color:#696c2b;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Password</div>
                                <div class="row g-3 mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Password *</label>
                                        <input type="password" class="form-control" name="password" id="password"
                                            placeholder="At least 8 characters" required
                                            oninput="checkStrength(this.value)">
                                        <div class="strength-bar"><div class="strength-fill" id="sBar"></div></div>
                                        <div class="strength-text" id="sText">Enter a password</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Confirm Password *</label>
                                        <input type="password" class="form-control" name="confirm_password"
                                            placeholder="Re-enter password" required>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-farm w-100 py-2">
                                    <i class="bi bi-person-plus me-2"></i> Create Admin Account
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function checkStrength(p) {
    let s = 0;
    if (p.length >= 8) s++;
    if (/[A-Z]/.test(p)) s++;
    if (/[0-9]/.test(p)) s++;
    if (/[^A-Za-z0-9]/.test(p)) s++;
    const levels = [
        {w:'0%', c:'#eee', t:'Enter a password'},
        {w:'25%', c:'#ef4444', t:'Weak'},
        {w:'50%', c:'#f97316', t:'Fair'},
        {w:'75%', c:'#eab308', t:'Good'},
        {w:'100%', c:'#22c55e', t:'Strong'},
    ];
    document.getElementById('sBar').style.cssText = `width:${levels[s].w};background:${levels[s].c}`;
    document.getElementById('sText').textContent = levels[s].t;
    document.getElementById('sText').style.color = levels[s].c;
}
</script>
</body>
</html>