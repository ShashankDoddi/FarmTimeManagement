<?php
// staff/index.php — Staff Management (List + Add + Edit + Delete)
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}
$conn    = getConnection();
$message = '';
$msgType = '';

// ── DELETE ──────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id   = intval($_GET['delete']);
    $stmt = $conn->prepare("UPDATE staff SET status = 'terminated' WHERE staff_id = ?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        auditLog($conn, 'DELETE', 'staff', $id, 'Staff terminated');
        $message = 'Staff member terminated.';
        $msgType = 'success';
    }
    $stmt->close();
}

// ── CREATE / UPDATE ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id             = intval($_POST['staff_id'] ?? 0);
    $staff_number   = trim($_POST['staff_number']);
    $first_name     = trim($_POST['first_name']);
    $last_name      = trim($_POST['last_name']);
    $role_id        = intval($_POST['role_id']);
    $contact_number = trim($_POST['contact_number'] ?? '');
    $contact_email  = trim($_POST['contact_email'] ?? '');
    $address        = trim($_POST['address'] ?? '');
    $hire_date      = $_POST['hire_date'] ?? null;
    $bank_name      = trim($_POST['bank_name'] ?? '');
    $bsb            = trim($_POST['bsb'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $tfn            = trim($_POST['tfn'] ?? '');
    $status         = $_POST['status'] ?? 'active';
    $adminId        = currentAdmin();

    if ($id > 0) {
        // UPDATE
        $old = $conn->query("SELECT * FROM staff WHERE staff_id = $id")->fetch_assoc();
        $stmt = $conn->prepare("
            UPDATE staff SET staff_number=?, first_name=?, last_name=?, role_id=?,
            contact_number=?, contact_email=?, address=?, hire_date=?,
            bank_name=?, bsb=?, account_number=?, tfn=?, status=?
            WHERE staff_id=?
        ");
        $stmt->bind_param('sssississsssi',
            $staff_number, $first_name, $last_name, $role_id,
            $contact_number, $contact_email, $address, $hire_date,
            $bank_name, $bsb, $account_number, $tfn, $status, $id
        );
        if ($stmt->execute()) {
            $new = $conn->query("SELECT * FROM staff WHERE staff_id = $id")->fetch_assoc();
            auditLog($conn, 'UPDATE', 'staff', $id, 'Staff updated', $old, $new);
            $message = 'Staff member updated successfully.';
            $msgType = 'success';
        }
        $stmt->close();
    } else {
        // CREATE
        $stmt = $conn->prepare("
            INSERT INTO staff (staff_number, first_name, last_name, role_id, contact_number,
            contact_email, address, hire_date, bank_name, bsb, account_number, tfn, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssississsssi',
            $staff_number, $first_name, $last_name, $role_id,
            $contact_number, $contact_email, $address, $hire_date,
            $bank_name, $bsb, $account_number, $tfn, $status, $adminId
        );
        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            auditLog($conn, 'CREATE', 'staff', $newId, 'New staff created');
            $message = 'Staff member added successfully.';
            $msgType = 'success';
        }
        $stmt->close();
    }
}

// ── LOAD EDIT DATA ───────────────────────────────────────────
$editStaff = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM staff WHERE staff_id = ?");
    $stmt->bind_param('i', $_GET['edit']);
    $stmt->execute();
    $editStaff = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ── LOAD LIST ─────────────────────────────────────────────────
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'active';

$sql = "
    SELECT s.*, r.role_name
    FROM staff s
    LEFT JOIN roles r ON s.role_id = r.role_id
    WHERE 1=1
";
if ($search) {
    $s = $conn->real_escape_string($search);
    $sql .= " AND (s.first_name LIKE '%$s%' OR s.last_name LIKE '%$s%' OR s.staff_number LIKE '%$s%')";
}
if ($statusFilter !== 'all') {
    $sf = $conn->real_escape_string($statusFilter);
    $sql .= " AND s.status = '$sf'";
}
$sql .= " ORDER BY s.first_name";
$staffList = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Roles for dropdown
$roles = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_name")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',sans-serif; background:#f0f2f5; }

        .navbar { background:#1a1a2e; color:#fff; padding:0 32px; height:60px; display:flex; align-items:center; justify-content:space-between; }
        .navbar-brand { font-size:18px; font-weight:700; }
        .navbar-right { display:flex; gap:12px; align-items:center; }
        .btn-nav { background:#0f3460; color:#fff; padding:7px 14px; border-radius:6px; text-decoration:none; font-size:13px; }
        .btn-logout { background:#dc2626; color:#fff; padding:7px 14px; border-radius:6px; text-decoration:none; font-size:13px; }

        .content { padding:28px 32px; }
        .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; }
        .page-title { font-size:22px; font-weight:700; color:#1a1a2e; }

        .card { background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.06); overflow:hidden; margin-bottom:24px; }
        .card-header { padding:16px 22px; border-bottom:1px solid #f0f0f0; font-size:16px; font-weight:600; color:#1a1a2e; }
        .card-body { padding:22px; }

        .message { padding:12px 16px; border-radius:8px; margin-bottom:18px; font-size:14px; }
        .message.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#16a34a; }
        .message.error   { background:#fff5f5; border:1px solid #fecaca; color:#dc2626; }

        .form-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
        .form-group { margin-bottom:0; }
        .form-group.full { grid-column:1/-1; }
        .form-group.half { grid-column:span 2; }
        label { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:5px; }
        input, select { width:100%; padding:10px 14px; border:1.5px solid #e0e0e0; border-radius:8px; font-size:14px; outline:none; }
        input:focus, select:focus { border-color:#4f46e5; box-shadow:0 0 0 3px rgba(79,70,229,0.1); }

        .btn { padding:10px 20px; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary  { background:#4f46e5; color:#fff; }
        .btn-primary:hover  { background:#4338ca; }
        .btn-success  { background:#16a34a; color:#fff; }
        .btn-danger   { background:#dc2626; color:#fff; }
        .btn-warning  { background:#d97706; color:#fff; }
        .btn-sm { padding:6px 12px; font-size:12px; }

        .filters { display:flex; gap:12px; margin-bottom:20px; align-items:center; }
        .filters input, .filters select { width:auto; padding:8px 12px; }

        table { width:100%; border-collapse:collapse; }
        th { background:#f8f9fa; padding:12px 16px; text-align:left; font-size:12px; font-weight:600; color:#888; text-transform:uppercase; }
        td { padding:13px 16px; font-size:14px; color:#333; border-bottom:1px solid #f5f5f5; vertical-align:middle; }
        tr:hover td { background:#fafafa; }

        .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; }
        .badge-active     { background:#f0fdf4; color:#16a34a; }
        .badge-inactive   { background:#fff5f5; color:#dc2626; }
        .badge-terminated { background:#f5f5f5; color:#888; }

        .actions { display:flex; gap:8px; }

        .section-divider { font-size:12px; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:1px; margin:16px 0 10px; grid-column:1/-1; border-bottom:1px solid #eee; padding-bottom:6px; }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="navbar-brand">⏱ Workforce Management</div>
    <div class="navbar-right">
        <span style="font-size:14px;">👤 <?= htmlspecialchars($_SESSION['username']) ?></span>
        <a href="../dashboard.php" class="btn-nav">🏠 Dashboard</a>
        <a href="../attendance.php" class="btn-nav">⏱ Attendance</a>
        <a href="../logout.php" class="btn-logout">🚪 Logout</a>
    </div>
</nav>

<div class="content">
    <div class="page-header">
        <div class="page-title">👥 Staff Management</div>
    </div>

    <?php if ($message): ?>
        <div class="message <?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Add / Edit Form -->
    <div class="card">
        <div class="card-header"><?= $editStaff ? '✏️ Edit Staff Member' : '➕ Add New Staff Member' ?></div>
        <div class="card-body">
            <form method="POST" action="index.php">
                <?php if ($editStaff): ?>
                    <input type="hidden" name="staff_id" value="<?= $editStaff['staff_id'] ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="section-divider">Personal Information</div>

                    <div class="form-group">
                        <label>Staff Number *</label>
                        <input type="text" name="staff_number" value="<?= htmlspecialchars($editStaff['staff_number'] ?? '') ?>" required placeholder="e.g. EMP001">
                    </div>
                    <div class="form-group">
                        <label>First Name *</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($editStaff['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name *</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($editStaff['last_name'] ?? '') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Role *</label>
                        <select name="role_id" required>
                            <option value="">— Select Role —</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['role_id'] ?>" <?= ($editStaff['role_id'] ?? '') == $r['role_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($r['role_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Hire Date</label>
                        <input type="date" name="hire_date" value="<?= $editStaff['hire_date'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="active"     <?= ($editStaff['status'] ?? 'active') === 'active'     ? 'selected' : '' ?>>Active</option>
                            <option value="inactive"   <?= ($editStaff['status'] ?? '') === 'inactive'   ? 'selected' : '' ?>>Inactive</option>
                            <option value="terminated" <?= ($editStaff['status'] ?? '') === 'terminated' ? 'selected' : '' ?>>Terminated</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact_number" value="<?= htmlspecialchars($editStaff['contact_number'] ?? '') ?>" placeholder="0412 345 678">
                    </div>
                    <div class="form-group">
                        <label>Contact Email</label>
                        <input type="email" name="contact_email" value="<?= htmlspecialchars($editStaff['contact_email'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" value="<?= htmlspecialchars($editStaff['address'] ?? '') ?>">
                    </div>

                    <div class="section-divider">Bank & Tax Details</div>

                    <div class="form-group">
                        <label>Bank Name</label>
                        <input type="text" name="bank_name" value="<?= htmlspecialchars($editStaff['bank_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>BSB</label>
                        <input type="text" name="bsb" value="<?= htmlspecialchars($editStaff['bsb'] ?? '') ?>" placeholder="000-000">
                    </div>
                    <div class="form-group">
                        <label>Account Number</label>
                        <input type="text" name="account_number" value="<?= htmlspecialchars($editStaff['account_number'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>TFN</label>
                        <input type="text" name="tfn" value="<?= htmlspecialchars($editStaff['tfn'] ?? '') ?>" placeholder="Tax File Number">
                    </div>
                </div>

                <div style="margin-top:20px; display:flex; gap:10px;">
                    <button type="submit" class="btn btn-primary">
                        <?= $editStaff ? '💾 Update Staff' : '➕ Add Staff' ?>
                    </button>
                    <?php if ($editStaff): ?>
                        <a href="index.php" class="btn btn-warning">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="index.php">
        <div class="filters">
            <input type="text" name="search" placeholder="🔍 Search name or staff number..." value="<?= htmlspecialchars($search) ?>">
            <select name="status">
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="terminated" <?= $statusFilter === 'terminated' ? 'selected' : '' ?>>Terminated</option>
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All</option>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="index.php" class="btn btn-warning">Reset</a>
        </div>
    </form>

    <!-- Staff Table -->
    <div class="card">
        <div class="card-header">
            👥 Staff List
            <span style="margin-left:auto;background:#eff6ff;color:#2563eb;padding:3px 10px;border-radius:20px;font-size:13px;"><?= count($staffList) ?> records</span>
        </div>
        <?php if (empty($staffList)): ?>
            <div style="text-align:center;padding:40px;color:#aaa;">No staff found.</div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Staff #</th>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Contact</th>
                    <th>Hire Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($staffList as $s): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['staff_number']) ?></strong></td>
                    <td><?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?></td>
                    <td><?= htmlspecialchars($s['role_name'] ?? '—') ?></td>
                    <td style="font-size:13px;">
                        <?= htmlspecialchars($s['contact_number'] ?? '—') ?><br>
                        <span style="color:#888;"><?= htmlspecialchars($s['contact_email'] ?? '') ?></span>
                    </td>
                    <td><?= $s['hire_date'] ? date('d M Y', strtotime($s['hire_date'])) : '—' ?></td>
                    <td><span class="badge badge-<?= $s['status'] ?>"><?= ucfirst($s['status']) ?></span></td>
                    <td>
                        <div class="actions">
                            <a href="index.php?edit=<?= $s['staff_id'] ?>" class="btn btn-warning btn-sm">✏️ Edit</a>
                            <a href="index.php?delete=<?= $s['staff_id'] ?>" class="btn btn-danger btn-sm"
                               onclick="return confirm('Terminate this staff member?')">🗑 Remove</a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
