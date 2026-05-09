<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$conn    = getConnection();
$message = '';
$msgType = '';

// ── DELETE (terminate) ───────────────────────────────────────
if (isset($_GET['delete'])) {
    $id   = intval($_GET['delete']);
    $stmt = $conn->prepare("UPDATE staff SET status='terminated' WHERE staff_id=?");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $adminId = $_SESSION['admin_id'];
        $log = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, target_table, target_id, reason, source_channel) VALUES (?, 'DELETE', 'staff', ?, 'Staff terminated', 'web')");
        $log->bind_param('ii', $adminId, $id);
        $log->execute();
        $log->close();
        $message = 'Staff member terminated successfully.';
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
    $adminId        = $_SESSION['admin_id'];

    if ($id > 0) {
        // UPDATE
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
            $log = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, target_table, target_id, reason, source_channel) VALUES (?, 'UPDATE', 'staff', ?, 'Staff updated', 'web')");
            $log->bind_param('ii', $adminId, $id);
            $log->execute();
            $log->close();
            $message = 'Staff member updated successfully.';
            $msgType = 'success';
        }
        $stmt->close();
    } else {
        // CREATE
        $stmt = $conn->prepare("
            INSERT INTO staff (staff_number, first_name, last_name, role_id,
            contact_number, contact_email, address, hire_date,
            bank_name, bsb, account_number, tfn, status, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('sssississsssi',
            $staff_number, $first_name, $last_name, $role_id,
            $contact_number, $contact_email, $address, $hire_date,
            $bank_name, $bsb, $account_number, $tfn, $status, $adminId
        );
        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            $log = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, target_table, target_id, reason, source_channel) VALUES (?, 'CREATE', 'staff', ?, 'New staff created', 'web')");
            $log->bind_param('ii', $adminId, $newId);
            $log->execute();
            $log->close();
            $message = 'Staff member added successfully.';
            $msgType = 'success';
        }
        $stmt->close();
    }
}

// ── LOAD EDIT DATA ────────────────────────────────────────────
$editStaff = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM staff WHERE staff_id=?");
    $stmt->bind_param('i', $_GET['edit']);
    $stmt->execute();
    $editStaff = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// ── FILTERS ───────────────────────────────────────────────────
$search       = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? 'active';

$sql = "
    SELECT s.*, r.role_name
    FROM staff s
    LEFT JOIN roles r ON s.role_id = r.role_id
    WHERE 1=1
";
if ($search) {
    $s   = $conn->real_escape_string($search);
    $sql .= " AND (s.first_name LIKE '%$s%' OR s.last_name LIKE '%$s%' OR s.staff_number LIKE '%$s%' OR s.contact_email LIKE '%$s%')";
}
if ($statusFilter !== 'all') {
    $sf  = $conn->real_escape_string($statusFilter);
    $sql .= " AND s.status = '$sf'";
}
$sql      .= " ORDER BY s.first_name";
$staffList = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// Roles for dropdown
$roles = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_name")->fetch_all(MYSQLI_ASSOC);

// Stats
$totalActive     = $conn->query("SELECT COUNT(*) AS c FROM staff WHERE status='active'")->fetch_assoc()['c'] ?? 0;
$totalInactive   = $conn->query("SELECT COUNT(*) AS c FROM staff WHERE status='inactive'")->fetch_assoc()['c'] ?? 0;
$totalTerminated = $conn->query("SELECT COUNT(*) AS c FROM staff WHERE status='terminated'")->fetch_assoc()['c'] ?? 0;

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Staff — Farm Time</title>
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
        .icon-box { width: 42px; height: 42px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; background: rgba(105,108,43,0.12); color: #696c2b; font-size: 1.2rem; }
        .section-title { font-size: 0.95rem; font-weight: 600; color: #6c757d; }
        .table td, .table th { vertical-align: middle; }
        @media (max-width: 991.98px) { .sidebar { min-height: auto; } }
        .form-label { font-weight: 600; font-size: 0.9rem; color: #343a40; }
        .form-control:focus, .form-select:focus { border-color: #696c2b; box-shadow: 0 0 0 3px rgba(105,108,43,0.12); }
        .btn-farm { background: #696c2b; color: white; border: none; }
        .btn-farm:hover { background: #5b5e24; color: white; }
        .btn-farm-outline { border: 1px solid #696c2b; color: #696c2b; background: transparent; }
        .btn-farm-outline:hover { background: #696c2b; color: white; }
        .section-divider { font-size: 11px; font-weight: 700; color: #696c2b; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid rgba(105,108,43,0.2); padding-bottom: 6px; margin: 20px 0 14px; }
        .avatar { width: 36px; height: 36px; border-radius: 50%; background: rgba(105,108,43,0.15); color: #696c2b; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; }
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
                <a class="nav-link active" href="staff.php"><i class="bi bi-people me-2"></i>Staff</a>
                <a class="nav-link" href="roster.php"><i class="bi bi-calendar-week me-2"></i>Rosters</a>
                <a class="nav-link" href="clockinout.php"><i class="bi bi-clock-history me-2"></i>Attendance</a>
                <a class="nav-link" href="devices.php"><i class="bi bi-hdd-network me-2"></i>Clock Stations</a>
                <a class="nav-link" href="exceptions.php"><i class="bi bi-exclamation-triangle me-2"></i>Exceptions</a>
                <a class="nav-link" href="reports.php"><i class="bi bi-file-earmark-bar-graph me-2"></i>Reports</a>
                <a class="nav-link" href="payroll.php"><i class="bi bi-receipt me-2"></i>Payslips</a>
                <a class="nav-link" href="settings.php"><i class="bi bi-gear me-2"></i>Settings</a>
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
                    <h4 class="mb-0">Staff Management</h4>
                    <small class="text-muted">Farm Time Management System</small>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted"><?= date('d M Y') ?></span>
                    <div class="fw-semibold"><?= htmlspecialchars($_SESSION['username']) ?></div>
                </div>
            </div>

            <div class="p-4">

                <!-- Stats -->
                <div class="row g-4 mb-4">
                    <div class="col-md-4">
                        <div class="card card-box p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="section-title">Active Staff</div>
                                    <h3 class="mb-0 text-success"><?= $totalActive ?></h3>
                                </div>
                                <div class="icon-box"><i class="bi bi-person-check"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-box p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="section-title">Inactive</div>
                                    <h3 class="mb-0 text-warning"><?= $totalInactive ?></h3>
                                </div>
                                <div class="icon-box"><i class="bi bi-person-dash"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-box p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="section-title">Terminated</div>
                                    <h3 class="mb-0 text-danger"><?= $totalTerminated ?></h3>
                                </div>
                                <div class="icon-box"><i class="bi bi-person-x"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alert -->
                <?php if ($message): ?>
                <div class="alert alert-<?= $msgType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row g-4">

                    <!-- Add / Edit Form -->
                    <div class="col-lg-4">
                        <div class="card card-box p-4">
                            <h5 class="mb-1"><?= $editStaff ? '✏️ Edit Staff' : '➕ Add New Staff' ?></h5>
                            <small class="text-muted mb-3 d-block">Fill in the worker/farmer details below</small>

                            <form method="POST" action="staff.php">
                                <?php if ($editStaff): ?>
                                    <input type="hidden" name="staff_id" value="<?= $editStaff['staff_id'] ?>">
                                <?php endif; ?>

                                <div class="section-divider">Personal Info</div>

                                <div class="row g-2">
                                    <div class="col-12">
                                        <label class="form-label">Staff Number *</label>
                                        <input type="text" class="form-control" name="staff_number"
                                            value="<?= htmlspecialchars($editStaff['staff_number'] ?? '') ?>"
                                            placeholder="e.g. EMP001" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">First Name *</label>
                                        <input type="text" class="form-control" name="first_name"
                                            value="<?= htmlspecialchars($editStaff['first_name'] ?? '') ?>"
                                            placeholder="First name" required>
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Last Name *</label>
                                        <input type="text" class="form-control" name="last_name"
                                            value="<?= htmlspecialchars($editStaff['last_name'] ?? '') ?>"
                                            placeholder="Last name" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Role *</label>
                                        <select class="form-select" name="role_id" required>
                                            <option value="">— Select Role —</option>
                                            <?php foreach ($roles as $r): ?>
                                                <option value="<?= $r['role_id'] ?>"
                                                    <?= ($editStaff['role_id'] ?? '') == $r['role_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($r['role_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Hire Date</label>
                                        <input type="date" class="form-control" name="hire_date"
                                            value="<?= $editStaff['hire_date'] ?? '' ?>">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="active"     <?= ($editStaff['status'] ?? 'active') === 'active'     ? 'selected' : '' ?>>Active</option>
                                            <option value="inactive"   <?= ($editStaff['status'] ?? '') === 'inactive'   ? 'selected' : '' ?>>Inactive</option>
                                            <option value="terminated" <?= ($editStaff['status'] ?? '') === 'terminated' ? 'selected' : '' ?>>Terminated</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="section-divider">Contact</div>

                                <div class="row g-2">
                                    <div class="col-12">
                                        <label class="form-label">Phone</label>
                                        <input type="text" class="form-control" name="contact_number"
                                            value="<?= htmlspecialchars($editStaff['contact_number'] ?? '') ?>"
                                            placeholder="0412 345 678">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="contact_email"
                                            value="<?= htmlspecialchars($editStaff['contact_email'] ?? '') ?>"
                                            placeholder="email@example.com">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Address</label>
                                        <input type="text" class="form-control" name="address"
                                            value="<?= htmlspecialchars($editStaff['address'] ?? '') ?>"
                                            placeholder="Street address">
                                    </div>
                                </div>

                                <div class="section-divider">Bank & Tax</div>

                                <div class="row g-2">
                                    <div class="col-12">
                                        <label class="form-label">Bank Name</label>
                                        <input type="text" class="form-control" name="bank_name"
                                            value="<?= htmlspecialchars($editStaff['bank_name'] ?? '') ?>"
                                            placeholder="e.g. Commonwealth Bank">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">BSB</label>
                                        <input type="text" class="form-control" name="bsb"
                                            value="<?= htmlspecialchars($editStaff['bsb'] ?? '') ?>"
                                            placeholder="000-000">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label">Account No.</label>
                                        <input type="text" class="form-control" name="account_number"
                                            value="<?= htmlspecialchars($editStaff['account_number'] ?? '') ?>"
                                            placeholder="12345678">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">TFN</label>
                                        <input type="text" class="form-control" name="tfn"
                                            value="<?= htmlspecialchars($editStaff['tfn'] ?? '') ?>"
                                            placeholder="Tax File Number">
                                    </div>
                                </div>

                                <div class="mt-3 d-flex gap-2">
                                    <button type="submit" class="btn btn-farm flex-fill">
                                        <i class="bi bi-<?= $editStaff ? 'save' : 'person-plus' ?> me-1"></i>
                                        <?= $editStaff ? 'Update Staff' : 'Add Staff' ?>
                                    </button>
                                    <?php if ($editStaff): ?>
                                        <a href="staff.php" class="btn btn-outline-secondary">Cancel</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Staff List -->
                    <div class="col-lg-8">
                        <div class="card card-box p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Staff List</h5>
                                <span class="badge" style="background:#696c2b;"><?= count($staffList) ?> records</span>
                            </div>

                            <!-- Search & Filter -->
                            <form method="GET" action="staff.php" class="d-flex gap-2 mb-3 flex-wrap">
                                <input type="text" class="form-control" name="search"
                                    placeholder="🔍 Search name, number, email..."
                                    value="<?= htmlspecialchars($search) ?>"
                                    style="max-width:280px;">
                                <select class="form-select" name="status" style="max-width:140px;">
                                    <option value="active"     <?= $statusFilter==='active'     ? 'selected':'' ?>>Active</option>
                                    <option value="inactive"   <?= $statusFilter==='inactive'   ? 'selected':'' ?>>Inactive</option>
                                    <option value="terminated" <?= $statusFilter==='terminated' ? 'selected':'' ?>>Terminated</option>
                                    <option value="all"        <?= $statusFilter==='all'        ? 'selected':'' ?>>All</option>
                                </select>
                                <button type="submit" class="btn btn-farm">Filter</button>
                                <a href="staff.php" class="btn btn-outline-secondary">Reset</a>
                            </form>

                            <!-- Table -->
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Staff</th>
                                            <th>Role</th>
                                            <th>Contact</th>
                                            <th>Hire Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($staffList)): ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">
                                                    No staff found. Add your first staff member!
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($staffList as $s): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="avatar">
                                                            <?= strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1)) ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-semibold"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
                                                            <small class="text-muted"><?= htmlspecialchars($s['staff_number']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($s['role_name'] ?? '—') ?></td>
                                                <td>
                                                    <div style="font-size:13px;"><?= htmlspecialchars($s['contact_number'] ?? '—') ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($s['contact_email'] ?? '') ?></small>
                                                </td>
                                                <td style="font-size:13px;">
                                                    <?= $s['hire_date'] ? date('d M Y', strtotime($s['hire_date'])) : '—' ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $badgeColor = match($s['status']) {
                                                        'active'     => 'success',
                                                        'inactive'   => 'warning',
                                                        'terminated' => 'danger',
                                                        default      => 'secondary'
                                                    };
                                                    ?>
                                                    <span class="badge text-bg-<?= $badgeColor ?>">
                                                        <?= ucfirst($s['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <div class="d-flex gap-1">
                                                        <a href="staff.php?edit=<?= $s['staff_id'] ?>"
                                                           class="btn btn-sm btn-farm-outline">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <a href="staff.php?delete=<?= $s['staff_id'] ?>"
                                                           class="btn btn-sm btn-outline-danger"
                                                           onclick="return confirm('Terminate <?= htmlspecialchars($s['first_name']) ?>?')">
                                                            <i class="bi bi-person-x"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
