<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$conn    = getConnection();
$adminId = $_SESSION['admin_id'];
$message = '';
$msgType = '';
$activeTab = $_GET['tab'] ?? 'profile';

// ── UPDATE PROFILE ────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $username       = trim($_POST['username'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');

    if (empty($username) || empty($email)) {
        $message = 'Username and email are required.';
        $msgType = 'danger';
    } else {
        // Check duplicate username (excluding self)
        $check = $conn->prepare("SELECT admin_id FROM admin WHERE username = ? AND admin_id != ? LIMIT 1");
        $check->bind_param('si', $username, $adminId);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = 'Username already taken by another account.';
            $msgType = 'danger';
        } else {
            $check->close();
            $stmt = $conn->prepare("UPDATE admin SET username=?, email=?, contact_number=? WHERE admin_id=?");
            $stmt->bind_param('sssi', $username, $email, $contact_number, $adminId);
            if ($stmt->execute()) {
                $_SESSION['username'] = $username;
                $_SESSION['email']    = $email;
                $log = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, target_table, target_id, reason, source_channel) VALUES (?, 'UPDATE', 'admin', ?, 'Profile updated', 'web')");
                $log->bind_param('ii', $adminId, $adminId);
                $log->execute();
                $log->close();
                $message = 'Profile updated successfully!';
                $msgType = 'success';
            }
            $stmt->close();
        }
    }
    $activeTab = 'profile';
}

// ── CHANGE PASSWORD ───────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = trim($_POST['current_password'] ?? '');
    $new_password     = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    $admin = $conn->query("SELECT password_hash FROM admin WHERE admin_id = $adminId")->fetch_assoc();

    if (!password_verify($current_password, $admin['password_hash'])) {
        $message = 'Current password is incorrect.';
        $msgType = 'danger';
    } elseif (strlen($new_password) < 8) {
        $message = 'New password must be at least 8 characters.';
        $msgType = 'danger';
    } elseif ($new_password !== $confirm_password) {
        $message = 'New passwords do not match.';
        $msgType = 'danger';
    } else {
        $hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE admin SET password_hash=? WHERE admin_id=?");
        $stmt->bind_param('si', $hash, $adminId);
        if ($stmt->execute()) {
            $log = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, target_table, target_id, reason, source_channel) VALUES (?, 'UPDATE', 'admin', ?, 'Password changed', 'web')");
            $log->bind_param('ii', $adminId, $adminId);
            $log->execute();
            $log->close();
            $message = 'Password changed successfully!';
            $msgType = 'success';
        }
        $stmt->close();
    }
    $activeTab = 'profile';
}

// ── CREATE / UPDATE SITE ──────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'save_site') {
    $site_id      = intval($_POST['site_id'] ?? 0);
    $site_name    = trim($_POST['site_name'] ?? '');
    $site_address = trim($_POST['site_address'] ?? '');
    $site_contact = trim($_POST['site_contact_number'] ?? '');

    if (empty($site_name)) {
        $message = 'Site name is required.';
        $msgType = 'danger';
    } else {
        if ($site_id > 0) {
            $stmt = $conn->prepare("UPDATE sites SET site_name=?, site_address=?, site_contact_number=? WHERE site_id=?");
            $stmt->bind_param('sssi', $site_name, $site_address, $site_contact, $site_id);
            if ($stmt->execute()) { $message = 'Site updated!'; $msgType = 'success'; }
        } else {
            $stmt = $conn->prepare("INSERT INTO sites (site_name, site_address, site_contact_number) VALUES (?, ?, ?)");
            $stmt->bind_param('sss', $site_name, $site_address, $site_contact);
            if ($stmt->execute()) {
                $newId = $conn->insert_id;
                $log = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, target_table, target_id, reason, source_channel) VALUES (?, 'CREATE', 'sites', ?, 'New site added', 'web')");
                $log->bind_param('ii', $adminId, $newId);
                $log->execute();
                $log->close();
                $message = 'Site added successfully!';
                $msgType = 'success';
            }
        }
        $stmt->close();
    }
    $activeTab = 'sites';
}

// ── DELETE SITE ───────────────────────────────────────────────
if (isset($_GET['delete_site'])) {
    $id   = intval($_GET['delete_site']);
    $stmt = $conn->prepare("DELETE FROM sites WHERE site_id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $message   = 'Site deleted.';
    $msgType   = 'success';
    $activeTab = 'sites';
}

// ── CREATE / UPDATE ROLE ──────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'save_role') {
    $role_id     = intval($_POST['role_id'] ?? 0);
    $role_name   = trim($_POST['role_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($role_name)) {
        $message = 'Role name is required.';
        $msgType = 'danger';
    } else {
        if ($role_id > 0) {
            $stmt = $conn->prepare("UPDATE roles SET role_name=?, description=? WHERE role_id=?");
            $stmt->bind_param('ssi', $role_name, $description, $role_id);
            if ($stmt->execute()) { $message = 'Role updated!'; $msgType = 'success'; }
        } else {
            $stmt = $conn->prepare("INSERT INTO roles (role_name, description) VALUES (?, ?)");
            $stmt->bind_param('ss', $role_name, $description);
            if ($stmt->execute()) { $message = 'Role added!'; $msgType = 'success'; }
        }
        $stmt->close();
    }
    $activeTab = 'roles';
}

// ── DELETE ROLE ───────────────────────────────────────────────
if (isset($_GET['delete_role'])) {
    $id   = intval($_GET['delete_role']);
    $stmt = $conn->prepare("DELETE FROM roles WHERE role_id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    $message   = 'Role deleted.';
    $msgType   = 'success';
    $activeTab = 'roles';
}

// ── LOAD DATA ─────────────────────────────────────────────────
$adminProfile = $conn->query("SELECT * FROM admin WHERE admin_id = $adminId")->fetch_assoc();
$sites        = $conn->query("SELECT * FROM sites ORDER BY site_name")->fetch_all(MYSQLI_ASSOC);
$roles        = $conn->query("SELECT * FROM roles ORDER BY role_name")->fetch_all(MYSQLI_ASSOC);

// Edit site/role data
$editSite = null;
$editRole = null;
if (isset($_GET['edit_site'])) {
    $id       = intval($_GET['edit_site']);
    $editSite = $conn->query("SELECT * FROM sites WHERE site_id=$id")->fetch_assoc();
    $activeTab = 'sites';
}
if (isset($_GET['edit_role'])) {
    $id       = intval($_GET['edit_role']);
    $editRole = $conn->query("SELECT * FROM roles WHERE role_id=$id")->fetch_assoc();
    $activeTab = 'roles';
}

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Settings — Farm Time</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        body { background-color: #f6f7fb; font-family: Arial, sans-serif; }
        .sidebar { min-height: 100vh; background: #696c2b; color: white; }
        .sidebar .nav-link { color: rgba(255,255,255,0.85); border-radius: 8px; margin-bottom: 6px; }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active { background: rgba(255,255,255,0.15); color: #fff; }
        .brand { font-size: 1.2rem; font-weight: 700; padding: 1.25rem 1rem; border-bottom: 1px solid rgba(255,255,255,0.15); }
        .topbar { background: white; border-bottom: 1px solid #e9ecef; }
        .card-box { border: none; border-radius: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .form-control:focus, .form-select:focus { border-color: #696c2b; box-shadow: 0 0 0 3px rgba(105,108,43,0.12); }
        .btn-farm { background: #696c2b; color: white; border: none; }
        .btn-farm:hover { background: #5b5e24; color: white; }
        .btn-farm-outline { border: 1px solid #696c2b; color: #696c2b; background: transparent; }
        .btn-farm-outline:hover { background: #696c2b; color: white; }
        .table td, .table th { vertical-align: middle; }

        /* Settings Tabs */
        .settings-nav { display: flex; gap: 4px; margin-bottom: 24px; background: #fff; padding: 6px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
        .settings-tab {
            flex: 1; padding: 10px 16px; border: none; border-radius: 8px;
            background: transparent; cursor: pointer; font-size: 14px;
            font-weight: 600; color: #888; transition: all 0.2s;
            display: flex; align-items: center; justify-content: center; gap: 6px;
        }
        .settings-tab:hover { background: rgba(105,108,43,0.08); color: #696c2b; }
        .settings-tab.active { background: #696c2b; color: white; }

        .tab-content-section { display: none; }
        .tab-content-section.active { display: block; }

        .section-divider { font-size: 11px; font-weight: 700; color: #696c2b; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid rgba(105,108,43,0.2); padding-bottom: 6px; margin: 20px 0 16px; }

        .avatar-circle { width: 64px; height: 64px; border-radius: 50%; background: rgba(105,108,43,0.15); color: #696c2b; display: flex; align-items: center; justify-content: center; font-size: 24px; font-weight: 700; }

        .strength-bar { height: 4px; background: #eee; border-radius: 4px; margin-top: 6px; overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 4px; transition: width 0.3s, background 0.3s; width: 0; }
        .strength-text { font-size: 11px; margin-top: 3px; color: #aaa; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
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
                <?php if ($_SESSION['permission_level'] === 'superadmin'): ?>
                <a class="nav-link" href="register.php"><i class="bi bi-person-plus me-2"></i>Create Admin</a>
                <?php endif; ?>
                <a class="nav-link active" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a>
                <a class="nav-link mt-3" href="logout.php" style="color:rgba(255,100,100,0.9);">
                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                </a>
            </nav>
        </aside>

        <!-- Main -->
        <main class="col-lg-10 col-md-9 px-0">

            <!-- Topbar -->
            <div class="topbar d-flex justify-content-between align-items-center px-4 py-3">
                <div>
                    <h4 class="mb-0">Settings</h4>
                    <small class="text-muted">Farm Time Management System</small>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted"><?= date('d M Y') ?></span>
                    <div class="fw-semibold"><?= htmlspecialchars($_SESSION['username']) ?></div>
                </div>
            </div>

            <div class="p-4">

                <!-- Alert -->
                <?php if ($message): ?>
                <div class="alert alert-<?= $msgType ?> alert-dismissible fade show" role="alert">
                    <?= $msgType === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Tab Navigation -->
                <div class="settings-nav">
                    <button class="settings-tab <?= $activeTab === 'profile' ? 'active' : '' ?>"
                        onclick="switchTab('profile')">
                        <i class="bi bi-person-circle"></i> My Profile
                    </button>
                    <?php if ($_SESSION['permission_level'] === 'superadmin' || $_SESSION['permission_level'] === 'manager'): ?>
                    <button class="settings-tab <?= $activeTab === 'sites' ? 'active' : '' ?>"
                        onclick="switchTab('sites')">
                        <i class="bi bi-building"></i> Sites
                    </button>
                    <button class="settings-tab <?= $activeTab === 'roles' ? 'active' : '' ?>"
                        onclick="switchTab('roles')">
                        <i class="bi bi-briefcase"></i> Roles
                    </button>
                    <?php endif; ?>
                </div>

                <!-- ── TAB: My Profile ── -->
                <div id="tab-profile" class="tab-content-section <?= $activeTab === 'profile' ? 'active' : '' ?>">
                    <div class="row g-4">

                        <!-- Profile Info -->
                        <div class="col-lg-6">
                            <div class="card card-box p-4">
                                <div class="d-flex align-items-center gap-3 mb-4">
                                    <div class="avatar-circle">
                                        <?= strtoupper(substr($adminProfile['username'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <h5 class="mb-1"><?= htmlspecialchars($adminProfile['username']) ?></h5>
                                        <span class="badge" style="background:#696c2b;">
                                            <?= ucfirst($adminProfile['permission_level']) ?>
                                        </span>
                                        <span class="badge bg-success ms-1">
                                            <?= ucfirst($adminProfile['status']) ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="section-divider">Update Profile</div>

                                <form method="POST" action="settings.php">
                                    <input type="hidden" name="action" value="update_profile">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Username *</label>
                                        <input type="text" class="form-control" name="username"
                                            value="<?= htmlspecialchars($adminProfile['username']) ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Email Address *</label>
                                        <input type="email" class="form-control" name="email"
                                            value="<?= htmlspecialchars($adminProfile['email'] ?? '') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Contact Number</label>
                                        <input type="text" class="form-control" name="contact_number"
                                            value="<?= htmlspecialchars($adminProfile['contact_number'] ?? '') ?>"
                                            placeholder="0412 345 678">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Permission Level</label>
                                        <input type="text" class="form-control" value="<?= ucfirst($adminProfile['permission_level']) ?>" disabled style="background:#f8f9fa;">
                                        <small class="text-muted">Contact superadmin to change permission level.</small>
                                    </div>
                                    <button type="submit" class="btn btn-farm w-100">
                                        <i class="bi bi-save me-2"></i> Save Profile
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="col-lg-6">
                            <div class="card card-box p-4">
                                <h5 class="mb-1"><i class="bi bi-shield-lock me-2"></i>Change Password</h5>
                                <small class="text-muted d-block mb-4">Update your account password</small>

                                <form method="POST" action="settings.php">
                                    <input type="hidden" name="action" value="change_password">
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Current Password *</label>
                                        <input type="password" class="form-control" name="current_password"
                                            placeholder="Enter current password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">New Password *</label>
                                        <input type="password" class="form-control" name="new_password"
                                            placeholder="At least 8 characters" required
                                            oninput="checkStrength(this.value)">
                                        <div class="strength-bar"><div class="strength-fill" id="sBar"></div></div>
                                        <div class="strength-text" id="sText">Enter a password</div>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label fw-semibold">Confirm New Password *</label>
                                        <input type="password" class="form-control" name="confirm_password"
                                            placeholder="Re-enter new password" required>
                                    </div>
                                    <button type="submit" class="btn btn-farm w-100">
                                        <i class="bi bi-key me-2"></i> Change Password
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── TAB: Sites ── -->
                <div id="tab-sites" class="tab-content-section <?= $activeTab === 'sites' ? 'active' : '' ?>">
                    <div class="row g-4">

                        <!-- Add/Edit Site Form -->
                        <div class="col-lg-4">
                            <div class="card card-box p-4">
                                <h5 class="mb-1">
                                    <i class="bi bi-<?= $editSite ? 'pencil' : 'plus-circle' ?> me-2"></i>
                                    <?= $editSite ? 'Edit Site' : 'Add New Site' ?>
                                </h5>
                                <small class="text-muted d-block mb-4">
                                    <?= $editSite ? 'Update site details' : 'Add a new farm/work location' ?>
                                </small>

                                <form method="POST" action="settings.php?tab=sites">
                                    <input type="hidden" name="action" value="save_site">
                                    <?php if ($editSite): ?>
                                        <input type="hidden" name="site_id" value="<?= $editSite['site_id'] ?>">
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Site Name *</label>
                                        <input type="text" class="form-control" name="site_name"
                                            value="<?= htmlspecialchars($editSite['site_name'] ?? '') ?>"
                                            placeholder="e.g. Main Farm" required autofocus>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Address</label>
                                        <input type="text" class="form-control" name="site_address"
                                            value="<?= htmlspecialchars($editSite['site_address'] ?? '') ?>"
                                            placeholder="e.g. 123 Farm Road, Adelaide">
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label fw-semibold">Contact Number</label>
                                        <input type="text" class="form-control" name="site_contact_number"
                                            value="<?= htmlspecialchars($editSite['site_contact_number'] ?? '') ?>"
                                            placeholder="08 8000 0001">
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-farm flex-fill">
                                            <i class="bi bi-<?= $editSite ? 'save' : 'plus' ?> me-1"></i>
                                            <?= $editSite ? 'Update Site' : 'Add Site' ?>
                                        </button>
                                        <?php if ($editSite): ?>
                                            <a href="settings.php?tab=sites" class="btn btn-outline-secondary">Cancel</a>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Sites List -->
                        <div class="col-lg-8">
                            <div class="card card-box">
                                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-building me-2"></i>All Sites</h5>
                                    <span class="badge" style="background:#696c2b;"><?= count($sites) ?> sites</span>
                                </div>
                                <?php if (empty($sites)): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="bi bi-building" style="font-size:2rem;"></i>
                                        <p class="mt-2">No sites added yet. Add your first site!</p>
                                    </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Site Name</th>
                                                <th>Address</th>
                                                <th>Contact</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($sites as $site): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($site['site_name']) ?></strong></td>
                                                <td class="text-muted" style="font-size:13px;"><?= htmlspecialchars($site['site_address'] ?? '—') ?></td>
                                                <td style="font-size:13px;"><?= htmlspecialchars($site['site_contact_number'] ?? '—') ?></td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <a href="settings.php?tab=sites&edit_site=<?= $site['site_id'] ?>"
                                                           class="btn btn-sm btn-farm-outline">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="settings.php?tab=sites&delete_site=<?= $site['site_id'] ?>"
                                                           class="btn btn-sm btn-outline-danger"
                                                           onclick="return confirm('Delete this site?')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── TAB: Roles ── -->
                <div id="tab-roles" class="tab-content-section <?= $activeTab === 'roles' ? 'active' : '' ?>">
                    <div class="row g-4">

                        <!-- Add/Edit Role Form -->
                        <div class="col-lg-4">
                            <div class="card card-box p-4">
                                <h5 class="mb-1">
                                    <i class="bi bi-<?= $editRole ? 'pencil' : 'plus-circle' ?> me-2"></i>
                                    <?= $editRole ? 'Edit Role' : 'Add New Role' ?>
                                </h5>
                                <small class="text-muted d-block mb-4">
                                    <?= $editRole ? 'Update role details' : 'Add a new staff role/position' ?>
                                </small>

                                <form method="POST" action="settings.php?tab=roles">
                                    <input type="hidden" name="action" value="save_role">
                                    <?php if ($editRole): ?>
                                        <input type="hidden" name="role_id" value="<?= $editRole['role_id'] ?>">
                                    <?php endif; ?>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Role Name *</label>
                                        <input type="text" class="form-control" name="role_name"
                                            value="<?= htmlspecialchars($editRole['role_name'] ?? '') ?>"
                                            placeholder="e.g. Farmer, Supervisor" required autofocus>
                                    </div>
                                    <div class="mb-4">
                                        <label class="form-label fw-semibold">Description</label>
                                        <input type="text" class="form-control" name="description"
                                            value="<?= htmlspecialchars($editRole['description'] ?? '') ?>"
                                            placeholder="Brief description of this role">
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-farm flex-fill">
                                            <i class="bi bi-<?= $editRole ? 'save' : 'plus' ?> me-1"></i>
                                            <?= $editRole ? 'Update Role' : 'Add Role' ?>
                                        </button>
                                        <?php if ($editRole): ?>
                                            <a href="settings.php?tab=roles" class="btn btn-outline-secondary">Cancel</a>
                                        <?php endif; ?>
                                    </div>
                                </form>

                                <!-- Suggested Roles -->
                                <?php if (!$editRole): ?>
                                <div class="mt-4">
                                    <div class="section-divider">Quick Add Roles</div>
                                    <p style="font-size:12px;color:#888;">Click to pre-fill the form:</p>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php
                                        $suggested = ['Farmer', 'Supervisor', 'Casual Worker', 'Picker', 'Packer', 'Driver', 'Manager', 'Warehouse'];
                                        foreach ($suggested as $s):
                                        ?>
                                        <button type="button" class="btn btn-sm btn-farm-outline"
                                            onclick="document.querySelector('[name=role_name]').value='<?= $s ?>'">
                                            + <?= $s ?>
                                        </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Roles List -->
                        <div class="col-lg-8">
                            <div class="card card-box">
                                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0"><i class="bi bi-briefcase me-2"></i>All Roles</h5>
                                    <span class="badge" style="background:#696c2b;"><?= count($roles) ?> roles</span>
                                </div>
                                <?php if (empty($roles)): ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="bi bi-briefcase" style="font-size:2rem;"></i>
                                        <p class="mt-2">No roles added yet. Add roles before creating staff!</p>
                                    </div>
                                <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Role Name</th>
                                                <th>Description</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($roles as $role): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div style="width:32px;height:32px;border-radius:8px;background:rgba(105,108,43,0.12);display:flex;align-items:center;justify-content:center;color:#696c2b;">
                                                            <i class="bi bi-briefcase"></i>
                                                        </div>
                                                        <strong><?= htmlspecialchars($role['role_name']) ?></strong>
                                                    </div>
                                                </td>
                                                <td class="text-muted" style="font-size:13px;"><?= htmlspecialchars($role['description'] ?? '—') ?></td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <a href="settings.php?tab=roles&edit_role=<?= $role['role_id'] ?>"
                                                           class="btn btn-sm btn-farm-outline">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="settings.php?tab=roles&delete_role=<?= $role['role_id'] ?>"
                                                           class="btn btn-sm btn-outline-danger"
                                                           onclick="return confirm('Delete this role? Make sure no staff are using it.')">
                                                            <i class="bi bi-trash"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchTab(tab) {
    // Hide all tabs
    document.querySelectorAll('.tab-content-section').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.settings-tab').forEach(el => el.classList.remove('active'));

    // Show selected tab
    document.getElementById('tab-' + tab).classList.add('active');
    event.target.closest('.settings-tab').classList.add('active');
}

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
        {w:'100%', c:'#22c55e', t:'Strong ✓'},
    ];
    document.getElementById('sBar').style.cssText = `width:${levels[s].w};background:${levels[s].c}`;
    document.getElementById('sText').textContent  = levels[s].t;
    document.getElementById('sText').style.color  = levels[s].c;
}
</script>
</body>
</html>
