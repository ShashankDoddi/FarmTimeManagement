<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit(); }

$conn    = getConnection();
$adminId = $_SESSION['admin_id'];
$tab     = $_GET['tab'] ?? 'profile';
$msg = ''; $msgType = '';

// UPDATE PROFILE
if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $username = trim($_POST['username']??'');
    $email    = trim($_POST['email']??'');
    $phone    = trim($_POST['contact_number']??'');
    if ($username && $email) {
        $stmt = $conn->prepare("UPDATE admin SET username=?,email=?,contact_number=? WHERE admin_id=?");
        $stmt->bind_param('sssi',$username,$email,$phone,$adminId);
        if ($stmt->execute()) { $_SESSION['username']=$username; $msg='Profile updated!'; $msgType='success'; }
        $stmt->close();
    }
}

// CHANGE PASSWORD
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current = $_POST['current_password']??'';
    $new     = $_POST['new_password']??'';
    $confirm = $_POST['confirm_password']??'';
    $adminRow = $conn->query("SELECT password_hash FROM admin WHERE admin_id=$adminId")->fetch_assoc();
    if (!password_verify($current, $adminRow['password_hash'])) {
        $msg='Current password is incorrect.'; $msgType='error';
    } elseif (strlen($new) < 8) {
        $msg='New password must be at least 8 characters.'; $msgType='error';
    } elseif ($new !== $confirm) {
        $msg='Passwords do not match.'; $msgType='error';
    } else {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE admin SET password_hash=? WHERE admin_id=?");
        $stmt->bind_param('si',$hash,$adminId); $stmt->execute(); $stmt->close();
        $msg='Password changed successfully!'; $msgType='success';
    }
}

// ADD ROLE
if (isset($_POST['action']) && $_POST['action'] === 'add_role') {
    $rn = trim($_POST['role_name']??''); $rd = trim($_POST['description']??'');
    if ($rn) {
        $stmt = $conn->prepare("INSERT INTO roles (role_name,description) VALUES (?,?)");
        $stmt->bind_param('ss',$rn,$rd); $stmt->execute(); $stmt->close();
        $msg='Role added!'; $msgType='success'; $tab='roles';
    }
}

// ADD SITE
if (isset($_POST['action']) && $_POST['action'] === 'add_site') {
    $sn = trim($_POST['site_name']??''); $sa = trim($_POST['site_address']??''); $sp = trim($_POST['site_contact_number']??'');
    if ($sn) {
        $stmt = $conn->prepare("INSERT INTO sites (site_name,site_address,site_contact_number) VALUES (?,?,?)");
        $stmt->bind_param('sss',$sn,$sa,$sp); $stmt->execute(); $stmt->close();
        $msg='Site added!'; $msgType='success'; $tab='sites';
    }
}

$adminRow = $conn->query("SELECT * FROM admin WHERE admin_id=$adminId")->fetch_assoc();
$roles    = $conn->query("SELECT * FROM roles ORDER BY role_name")->fetch_all(MYSQLI_ASSOC);
$sites    = $conn->query("SELECT * FROM sites ORDER BY site_name")->fetch_all(MYSQLI_ASSOC);
$conn->close();
$initials = strtoupper(substr($_SESSION['username'],0,2));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Settings — Farm TMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="adminStyle.css"/>
</head>
<body>
<div class="dashboard-wrapper">
    <aside class="sidebar">
        <div class="brand"><i class="bi bi-clock-history me-2"></i>Farm TMS</div>
        <nav class="nav flex-column">
            <span class="nav-section-label">Main</span>
            <a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="roster.php" class="nav-link"><i class="bi bi-calendar3"></i> Roster</a>
            <a href="clockinout.php" class="nav-link"><i class="bi bi-clock"></i> Timesheets</a>
            <a href="exceptions.php" class="nav-link"><i class="bi bi-exclamation-circle"></i> Exceptions</a>
            <span class="nav-section-label">People</span>
            <a href="staff.php" class="nav-link"><i class="bi bi-people"></i> Staff</a>
            <a href="settings.php?tab=roles" class="nav-link"><i class="bi bi-person-badge"></i> Roles</a>
            <span class="nav-section-label">System</span>
            <a href="reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reports</a>
            <a href="payslips.php" class="nav-link"><i class="bi bi-receipt"></i> Payslips</a>
            <a href="settings.php" class="nav-link active"><i class="bi bi-gear"></i> Settings</a>
        </nav>
        <div class="mt-auto p-3" style="border-top:1px solid rgba(255,255,255,0.15)">
            <a href="logout.php" class="nav-link" style="color:rgba(255,100,100,0.85)"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
        </div>
    </aside>

    <div class="main-content">
        <header class="topbar">
            <span class="page-title">Settings</span>
            <div class="topbar-right">
                <span class="topbar-date"><i class="bi bi-calendar3 me-1"></i><?= date('D, d M Y') ?></span>
                <div class="admin-badge"><div class="admin-avatar"><?= $initials ?></div><?= htmlspecialchars($_SESSION['username']) ?></div>
            </div>
        </header>

        <div class="page-body">
            <?php if ($msg): ?>
            <div class="toast-<?= $msgType ?>"><i class="bi bi-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>-fill"></i><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <!-- Tabs -->
            <div class="status-tabs mb-4">
                <a href="?tab=profile"><button class="status-tab <?= $tab==='profile'?'active':''?>"><i class="bi bi-person me-1"></i> My Profile</button></a>
                <a href="?tab=roles"><button class="status-tab <?= $tab==='roles'?'active':''?>"><i class="bi bi-person-badge me-1"></i> Roles</button></a>
                <a href="?tab=sites"><button class="status-tab <?= $tab==='sites'?'active':''?>"><i class="bi bi-geo-alt me-1"></i> Sites</button></a>
            </div>

            <?php if ($tab==='profile'): ?>
            <div class="row g-4">
                <!-- Profile -->
                <div class="col-lg-7">
                    <div class="card-box">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3 mb-4">
                                <div class="admin-avatar" style="width:48px;height:48px;font-size:1.1rem;"><?= $initials ?></div>
                                <div>
                                    <div style="font-weight:700;font-size:1.05rem;"><?= htmlspecialchars($adminRow['username']) ?></div>
                                    <div class="d-flex gap-2 mt-1">
                                        <span class="badge-status badge-on-time"><?= htmlspecialchars($adminRow['permission_level']) ?></span>
                                        <span class="badge-status badge-active"><?= htmlspecialchars($adminRow['status']) ?></span>
                                    </div>
                                </div>
                            </div>
                            <p class="section-title">Update Profile</p>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="form-field"><label>Username *</label><input type="text" name="username" class="form-input" value="<?= htmlspecialchars($adminRow['username']) ?>" required></div>
                                <div class="form-field"><label>Email Address *</label><input type="email" name="email" class="form-input" value="<?= htmlspecialchars($adminRow['email']) ?>" required></div>
                                <div class="form-field"><label>Contact Number</label><input type="text" name="contact_number" class="form-input" value="<?= htmlspecialchars($adminRow['contact_number']??'') ?>"></div>
                                <div class="form-field"><label>Permission Level</label><input type="text" class="form-input" value="<?= htmlspecialchars($adminRow['permission_level']) ?>" disabled><small style="color:var(--text-muted);">Contact superadmin to change permission level.</small></div>
                                <button type="submit" class="btn-brand"><i class="bi bi-save me-1"></i> Save Profile</button>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- Password -->
                <div class="col-lg-5">
                    <div class="card-box">
                        <div class="card-body">
                            <p class="section-title"><i class="bi bi-shield-lock me-1"></i> Change Password</p>
                            <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:16px;">Update your account password</p>
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                <div class="form-field"><label>Current Password *</label><input type="password" name="current_password" class="form-input" placeholder="Enter current password" required></div>
                                <div class="form-field"><label>New Password *</label><input type="password" name="new_password" class="form-input" placeholder="At least 8 characters" required></div>
                                <div class="form-field"><label>Confirm New Password *</label><input type="password" name="confirm_password" class="form-input" placeholder="Re-enter new password" required></div>
                                <button type="submit" class="btn-brand w-100"><i class="bi bi-key me-1"></i> Change Password</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($tab==='roles'): ?>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card-box">
                        <div class="card-body">
                            <p class="section-title">Roles</p>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead><tr><th>Role Name</th><th>Description</th><th>Created</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($roles as $r): ?>
                                        <tr>
                                            <td style="font-weight:600;"><?= htmlspecialchars($r['role_name']) ?></td>
                                            <td style="color:var(--text-muted);font-size:0.88rem;"><?= htmlspecialchars($r['description']??'—') ?></td>
                                            <td style="font-size:0.82rem;color:var(--text-muted);"><?= date('d M Y',strtotime($r['created_at'])) ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($roles)): ?><tr><td colspan="3" class="text-center py-4" style="color:var(--text-muted);">No roles yet.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card-box">
                        <div class="card-body">
                            <p class="section-title">Add New Role</p>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_role">
                                <div class="form-field"><label>Role Name *</label><input type="text" name="role_name" class="form-input" placeholder="e.g. Farm Hand" required></div>
                                <div class="form-field"><label>Description</label><textarea name="description" class="form-input" rows="2" placeholder="Optional description..."></textarea></div>
                                <button type="submit" class="btn-brand w-100"><i class="bi bi-plus me-1"></i> Add Role</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php elseif ($tab==='sites'): ?>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card-box">
                        <div class="card-body">
                            <p class="section-title">Sites</p>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead><tr><th>Site Name</th><th>Address</th><th>Contact</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($sites as $s): ?>
                                        <tr>
                                            <td style="font-weight:600;"><?= htmlspecialchars($s['site_name']) ?></td>
                                            <td style="font-size:0.85rem;color:var(--text-muted);"><?= htmlspecialchars($s['site_address']??'—') ?></td>
                                            <td style="font-size:0.85rem;"><?= htmlspecialchars($s['site_contact_number']??'—') ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($sites)): ?><tr><td colspan="3" class="text-center py-4" style="color:var(--text-muted);">No sites yet.</td></tr><?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card-box">
                        <div class="card-body">
                            <p class="section-title">Add New Site</p>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_site">
                                <div class="form-field"><label>Site Name *</label><input type="text" name="site_name" class="form-input" placeholder="e.g. North Farm" required></div>
                                <div class="form-field"><label>Address</label><input type="text" name="site_address" class="form-input" placeholder="Street address"></div>
                                <div class="form-field"><label>Contact Number</label><input type="text" name="site_contact_number" class="form-input" placeholder="0412 345 678"></div>
                                <button type="submit" class="btn-brand w-100"><i class="bi bi-plus me-1"></i> Add Site</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
<?php if ($msg): ?>setTimeout(()=>{ const t=document.querySelector('.toast-success,.toast-error'); if(t){t.style.opacity='0';setTimeout(()=>t.remove(),300);} },4000);<?php endif; ?>
</script>
</body>
</html>
