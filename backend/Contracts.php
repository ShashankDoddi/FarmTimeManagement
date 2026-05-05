<?php
// contracts/index.php
require_once '../includes/auth.php';
require_once '../config/database.php';
requireLogin();

$conn    = getConnection();
$message = '';
$msgType = '';

// ── CREATE / UPDATE ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id                   = intval($_POST['contract_id'] ?? 0);
    $staff_id             = intval($_POST['staff_id']);
    $contract_type        = $_POST['contract_type'];
    $pay_type             = $_POST['pay_type'];
    $standard_pay_rate    = floatval($_POST['standard_pay_rate']);
    $overtime_pay_rate    = floatval($_POST['overtime_pay_rate']);
    $start_date           = $_POST['start_date'];
    $end_date             = $_POST['end_date'] ?: null;
    $standard_weekly_hours= floatval($_POST['standard_weekly_hours']);
    $annual_leave_rate    = floatval($_POST['annual_leave_rate']);
    $is_active            = isset($_POST['is_active']) ? 1 : 0;
    $adminId              = currentAdmin();

    if ($id > 0) {
        $old  = $conn->query("SELECT * FROM contracts WHERE contract_id = $id")->fetch_assoc();
        $stmt = $conn->prepare("
            UPDATE contracts SET staff_id=?, contract_type=?, pay_type=?, standard_pay_rate=?,
            overtime_pay_rate=?, start_date=?, end_date=?, standard_weekly_hours=?,
            annual_leave_rate=?, is_active=?
            WHERE contract_id=?
        ");
        $stmt->bind_param('issddssddi', $staff_id, $contract_type, $pay_type,
            $standard_pay_rate, $overtime_pay_rate, $start_date, $end_date,
            $standard_weekly_hours, $annual_leave_rate, $is_active, $id
        );
        if ($stmt->execute()) {
            $new = $conn->query("SELECT * FROM contracts WHERE contract_id = $id")->fetch_assoc();
            auditLog($conn, 'UPDATE', 'contracts', $id, 'Contract updated', $old, $new);
            $message = 'Contract updated.';
            $msgType = 'success';
        }
    } else {
        $stmt = $conn->prepare("
            INSERT INTO contracts (staff_id, contract_type, pay_type, standard_pay_rate,
            overtime_pay_rate, start_date, end_date, standard_weekly_hours, annual_leave_rate, is_active, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('issddssddi', $staff_id, $contract_type, $pay_type,
            $standard_pay_rate, $overtime_pay_rate, $start_date, $end_date,
            $standard_weekly_hours, $annual_leave_rate, $is_active, $adminId
        );
        if ($stmt->execute()) {
            $newId = $conn->insert_id;
            // Link contract to staff
            $conn->query("UPDATE staff SET contract_id = $newId WHERE staff_id = $staff_id");
            auditLog($conn, 'CREATE', 'contracts', $newId, 'New contract created');
            $message = 'Contract created.';
            $msgType = 'success';
        }
    }
    $stmt->close();
}

// Edit
$editContract = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM contracts WHERE contract_id = ?");
    $stmt->bind_param('i', $_GET['edit']);
    $stmt->execute();
    $editContract = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// List
$contracts = $conn->query("
    SELECT c.*, CONCAT(s.first_name,' ',s.last_name) AS staff_name, s.staff_number
    FROM contracts c
    JOIN staff s ON c.staff_id = s.staff_id
    ORDER BY c.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

$staffList = $conn->query("SELECT staff_id, staff_number, first_name, last_name FROM staff WHERE status='active' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contracts</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',sans-serif; background:#f0f2f5; }
        .navbar { background:#1a1a2e; color:#fff; padding:0 32px; height:60px; display:flex; align-items:center; justify-content:space-between; }
        .navbar-brand { font-size:18px; font-weight:700; }
        .navbar-right { display:flex; gap:12px; }
        .btn-nav { background:#0f3460; color:#fff; padding:7px 14px; border-radius:6px; text-decoration:none; font-size:13px; }
        .btn-logout { background:#dc2626; color:#fff; padding:7px 14px; border-radius:6px; text-decoration:none; font-size:13px; }
        .content { padding:28px 32px; }
        .page-title { font-size:22px; font-weight:700; color:#1a1a2e; margin-bottom:20px; }
        .card { background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.06); overflow:hidden; margin-bottom:24px; }
        .card-header { padding:16px 22px; border-bottom:1px solid #f0f0f0; font-size:16px; font-weight:600; color:#1a1a2e; }
        .card-body { padding:22px; }
        .message { padding:12px 16px; border-radius:8px; margin-bottom:18px; font-size:14px; }
        .message.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#16a34a; }
        .form-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
        label { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:5px; }
        input, select { width:100%; padding:10px 14px; border:1.5px solid #e0e0e0; border-radius:8px; font-size:14px; outline:none; }
        input:focus, select:focus { border-color:#4f46e5; }
        .btn { padding:10px 20px; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary { background:#4f46e5; color:#fff; }
        .btn-warning { background:#d97706; color:#fff; }
        .btn-danger  { background:#dc2626; color:#fff; }
        .btn-sm { padding:6px 12px; font-size:12px; }
        table { width:100%; border-collapse:collapse; }
        th { background:#f8f9fa; padding:12px 16px; text-align:left; font-size:12px; font-weight:600; color:#888; text-transform:uppercase; }
        td { padding:13px 16px; font-size:14px; border-bottom:1px solid #f5f5f5; }
        .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; }
        .badge-active   { background:#f0fdf4; color:#16a34a; }
        .badge-inactive { background:#fff5f5; color:#dc2626; }
        .actions { display:flex; gap:8px; }
        .section-divider { font-size:12px; font-weight:700; color:#888; text-transform:uppercase; letter-spacing:1px; margin:16px 0 10px; grid-column:1/-1; border-bottom:1px solid #eee; padding-bottom:6px; }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="navbar-brand">⏱ Workforce Management</div>
    <div class="navbar-right">
        <a href="../dashboard.php" class="btn-nav">🏠 Dashboard</a>
        <a href="../staff/index.php" class="btn-nav">👥 Staff</a>
        <a href="../logout.php" class="btn-logout">🚪 Logout</a>
    </div>
</nav>

<div class="content">
    <div class="page-title">📄 Contracts</div>

    <?php if ($message): ?>
        <div class="message <?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header"><?= $editContract ? '✏️ Edit Contract' : '➕ New Contract' ?></div>
        <div class="card-body">
            <form method="POST">
                <?php if ($editContract): ?>
                    <input type="hidden" name="contract_id" value="<?= $editContract['contract_id'] ?>">
                <?php endif; ?>
                <div class="form-grid">
                    <div class="section-divider">Contract Details</div>
                    <div>
                        <label>Staff Member *</label>
                        <select name="staff_id" required>
                            <option value="">— Select Staff —</option>
                            <?php foreach ($staffList as $s): ?>
                                <option value="<?= $s['staff_id'] ?>" <?= ($editContract['staff_id'] ?? '') == $s['staff_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['first_name'].' '.$s['last_name'].' ('.$s['staff_number'].')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Contract Type *</label>
                        <select name="contract_type" required>
                            <option value="full_time"  <?= ($editContract['contract_type'] ?? '') === 'full_time'  ? 'selected' : '' ?>>Full Time</option>
                            <option value="part_time"  <?= ($editContract['contract_type'] ?? '') === 'part_time'  ? 'selected' : '' ?>>Part Time</option>
                            <option value="casual"     <?= ($editContract['contract_type'] ?? '') === 'casual'     ? 'selected' : '' ?>>Casual</option>
                        </select>
                    </div>
                    <div>
                        <label>Pay Type *</label>
                        <select name="pay_type" required>
                            <option value="hourly"  <?= ($editContract['pay_type'] ?? '') === 'hourly'  ? 'selected' : '' ?>>Hourly</option>
                            <option value="salary"  <?= ($editContract['pay_type'] ?? '') === 'salary'  ? 'selected' : '' ?>>Salary</option>
                        </select>
                    </div>
                    <div>
                        <label>Standard Pay Rate ($/hr) *</label>
                        <input type="number" step="0.01" name="standard_pay_rate" value="<?= $editContract['standard_pay_rate'] ?? '' ?>" required>
                    </div>
                    <div>
                        <label>Overtime Pay Rate ($/hr)</label>
                        <input type="number" step="0.01" name="overtime_pay_rate" value="<?= $editContract['overtime_pay_rate'] ?? '' ?>">
                    </div>
                    <div>
                        <label>Standard Weekly Hours</label>
                        <input type="number" step="0.5" name="standard_weekly_hours" value="<?= $editContract['standard_weekly_hours'] ?? '38' ?>">
                    </div>
                    <div>
                        <label>Annual Leave Rate</label>
                        <input type="number" step="0.0001" name="annual_leave_rate" value="<?= $editContract['annual_leave_rate'] ?? '0.0769' ?>">
                    </div>
                    <div>
                        <label>Start Date *</label>
                        <input type="date" name="start_date" value="<?= $editContract['start_date'] ?? '' ?>" required>
                    </div>
                    <div>
                        <label>End Date (optional)</label>
                        <input type="date" name="end_date" value="<?= $editContract['end_date'] ?? '' ?>">
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;padding-top:20px;">
                        <input type="checkbox" name="is_active" id="is_active" style="width:auto;" <?= ($editContract['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label for="is_active" style="margin:0;">Active Contract</label>
                    </div>
                </div>
                <div style="margin-top:20px;display:flex;gap:10px;">
                    <button type="submit" class="btn btn-primary"><?= $editContract ? '💾 Update' : '➕ Create Contract' ?></button>
                    <?php if ($editContract): ?><a href="index.php" class="btn btn-warning">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">📄 All Contracts</div>
        <table>
            <thead>
                <tr><th>Staff</th><th>Type</th><th>Pay Type</th><th>Pay Rate</th><th>Hours/Week</th><th>Start</th><th>End</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($contracts as $c): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($c['staff_name']) ?></strong><br><span style="font-size:12px;color:#888;"><?= $c['staff_number'] ?></span></td>
                    <td><?= ucfirst(str_replace('_',' ',$c['contract_type'])) ?></td>
                    <td><?= ucfirst($c['pay_type']) ?></td>
                    <td>$<?= number_format($c['standard_pay_rate'],2) ?>/hr<br><span style="font-size:12px;color:#888;">OT: $<?= number_format($c['overtime_pay_rate'],2) ?></span></td>
                    <td><?= $c['standard_weekly_hours'] ?>h</td>
                    <td><?= date('d M Y', strtotime($c['start_date'])) ?></td>
                    <td><?= $c['end_date'] ? date('d M Y', strtotime($c['end_date'])) : 'Ongoing' ?></td>
                    <td><span class="badge badge-<?= $c['is_active'] ? 'active' : 'inactive' ?>"><?= $c['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                    <td><div class="actions"><a href="index.php?edit=<?= $c['contract_id'] ?>" class="btn btn-warning btn-sm">✏️ Edit</a></div></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
