<?php
// leave/index.php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$conn    = getConnection();
$message = '';
$msgType = '';

// ── APPROVE / REJECT ─────────────────────────────────────────
if (isset($_GET['approve'])) {
    $id = intval($_GET['approve']);
    $adminId = currentAdmin();
    $stmt = $conn->prepare("UPDATE leave_records SET status='approved', approved_by=? WHERE leave_id=?");
    $stmt->bind_param('ii', $adminId, $id);
    $stmt->execute();
    auditLog($conn, 'UPDATE', 'leave_records', $id, 'Leave approved');
    $message = 'Leave approved.'; $msgType = 'success';
    $stmt->close();
}

if (isset($_GET['reject'])) {
    $id = intval($_GET['reject']);
    $stmt = $conn->prepare("UPDATE leave_records SET status='rejected' WHERE leave_id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    auditLog($conn, 'UPDATE', 'leave_records', $id, 'Leave rejected');
    $message = 'Leave rejected.'; $msgType = 'error';
    $stmt->close();
}

// ── CREATE ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staff_id   = intval($_POST['staff_id']);
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date   = $_POST['end_date'];
    $hours      = floatval($_POST['hours']);
    $note       = trim($_POST['note'] ?? '');
    $adminId    = currentAdmin();

    $stmt = $conn->prepare("
        INSERT INTO leave_records (staff_id, leave_type, start_date, end_date, hours, status, note, created_by)
        VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
    ");
    $stmt->bind_param('isssds i', $staff_id, $leave_type, $start_date, $end_date, $hours, $note, $adminId);

    // Fix bind
    $stmt->close();
    $stmt = $conn->prepare("INSERT INTO leave_records (staff_id,leave_type,start_date,end_date,hours,status,note,created_by) VALUES (?,?,?,?,?,'pending',?,?)");
    $stmt->bind_param('isssdsi', $staff_id, $leave_type, $start_date, $end_date, $hours, $note, $adminId);

    if ($stmt->execute()) {
        auditLog($conn, 'CREATE', 'leave_records', $conn->insert_id, 'Leave request created');
        $message = 'Leave request submitted.'; $msgType = 'success';
    }
    $stmt->close();
}

// Load
$statusFilter = $_GET['status'] ?? 'all';
$sql = "
    SELECT l.*, CONCAT(s.first_name,' ',s.last_name) AS staff_name, s.staff_number,
           CONCAT(a.username) AS approved_by_name
    FROM leave_records l
    JOIN staff s ON l.staff_id = s.staff_id
    LEFT JOIN admin a ON l.approved_by = a.admin_id
    WHERE 1=1
";
if ($statusFilter !== 'all') $sql .= " AND l.status = '{$conn->real_escape_string($statusFilter)}'";
$sql .= " ORDER BY l.created_at DESC";
$leaveList = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$staffList = $conn->query("SELECT staff_id, staff_number, first_name, last_name FROM staff WHERE status='active' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leave Records</title>
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
        .grid-2 { display:grid; grid-template-columns:400px 1fr; gap:20px; margin-bottom:24px; }
        .card { background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.06); overflow:hidden; margin-bottom:24px; }
        .card-header { padding:16px 22px; border-bottom:1px solid #f0f0f0; font-size:16px; font-weight:600; color:#1a1a2e; display:flex; align-items:center; }
        .card-body { padding:22px; }
        .message { padding:12px 16px; border-radius:8px; margin-bottom:18px; font-size:14px; }
        .message.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#16a34a; }
        .message.error   { background:#fff5f5; border:1px solid #fecaca; color:#dc2626; }
        .form-group { margin-bottom:14px; }
        label { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:5px; }
        input, select, textarea { width:100%; padding:10px 14px; border:1.5px solid #e0e0e0; border-radius:8px; font-size:14px; outline:none; }
        input:focus, select:focus { border-color:#4f46e5; }
        .btn { padding:10px 20px; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary { background:#4f46e5; color:#fff; }
        .btn-success { background:#16a34a; color:#fff; }
        .btn-danger  { background:#dc2626; color:#fff; }
        .btn-sm { padding:5px 10px; font-size:12px; }
        .filters { display:flex; gap:12px; margin-bottom:20px; align-items:center; }
        .filters select { width:auto; padding:8px 12px; }
        table { width:100%; border-collapse:collapse; }
        th { background:#f8f9fa; padding:12px 16px; text-align:left; font-size:12px; font-weight:600; color:#888; text-transform:uppercase; }
        td { padding:12px 16px; font-size:14px; border-bottom:1px solid #f5f5f5; vertical-align:middle; }
        .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; }
        .badge-pending  { background:#fefce8; color:#d97706; }
        .badge-approved { background:#f0fdf4; color:#16a34a; }
        .badge-rejected { background:#fff5f5; color:#dc2626; }
        .badge-cancelled{ background:#f5f5f5; color:#888; }
        .actions { display:flex; gap:6px; }
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
    <div class="page-title">🏖️ Leave Management</div>

    <?php if ($message): ?>
        <div class="message <?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="grid-2">
        <!-- New Leave Request -->
        <div class="card">
            <div class="card-header">➕ New Leave Request</div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label>Staff Member *</label>
                        <select name="staff_id" required>
                            <option value="">— Select —</option>
                            <?php foreach ($staffList as $s): ?>
                                <option value="<?= $s['staff_id'] ?>"><?= htmlspecialchars($s['first_name'].' '.$s['last_name'].' ('.$s['staff_number'].')') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Leave Type *</label>
                        <select name="leave_type" required>
                            <option value="annual">Annual Leave</option>
                            <option value="sick">Sick Leave</option>
                            <option value="personal">Personal Leave</option>
                            <option value="unpaid">Unpaid Leave</option>
                            <option value="maternity">Maternity/Paternity</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Date *</label>
                        <input type="date" name="start_date" required>
                    </div>
                    <div class="form-group">
                        <label>End Date *</label>
                        <input type="date" name="end_date" required>
                    </div>
                    <div class="form-group">
                        <label>Hours</label>
                        <input type="number" step="0.5" name="hours" placeholder="e.g. 7.6">
                    </div>
                    <div class="form-group">
                        <label>Note</label>
                        <textarea name="note" rows="2" style="resize:vertical;" placeholder="Optional note..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width:100%;">➕ Submit Request</button>
                </form>
            </div>
        </div>

        <!-- Stats -->
        <div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                <?php
                $statuses = ['pending'=>['🟡','#fefce8','#d97706'], 'approved'=>['✅','#f0fdf4','#16a34a'], 'rejected'=>['❌','#fff5f5','#dc2626']];
                foreach ($statuses as $st => [$icon,$bg,$color]):
                    $count = array_reduce($leaveList, fn($c,$l) => $c + ($l['status'] === $st ? 1 : 0), 0);
                ?>
                <div style="background:#fff;border-radius:12px;padding:18px;box-shadow:0 2px 8px rgba(0,0,0,0.06);text-align:center;">
                    <div style="font-size:28px;"><?= $icon ?></div>
                    <div style="font-size:26px;font-weight:700;color:<?= $color ?>;"><?= $count ?></div>
                    <div style="font-size:13px;color:#888;"><?= ucfirst($st) ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" style="display:inline;">
        <div class="filters">
            <label style="margin:0;">Filter:</label>
            <select name="status" onchange="this.form.submit()">
                <option value="all"      <?= $statusFilter==='all'      ? 'selected':'' ?>>All</option>
                <option value="pending"  <?= $statusFilter==='pending'  ? 'selected':'' ?>>Pending</option>
                <option value="approved" <?= $statusFilter==='approved' ? 'selected':'' ?>>Approved</option>
                <option value="rejected" <?= $statusFilter==='rejected' ? 'selected':'' ?>>Rejected</option>
            </select>
        </div>
    </form>

    <!-- Table -->
    <div class="card">
        <div class="card-header">
            📋 Leave Requests
            <span style="margin-left:auto;background:#eff6ff;color:#2563eb;padding:3px 10px;border-radius:20px;font-size:13px;"><?= count($leaveList) ?> records</span>
        </div>
        <table>
            <thead><tr><th>Staff</th><th>Type</th><th>Start</th><th>End</th><th>Hours</th><th>Note</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($leaveList as $l): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($l['staff_name']) ?></strong><br><span style="font-size:12px;color:#888;"><?= $l['staff_number'] ?></span></td>
                    <td><?= ucfirst($l['leave_type']) ?></td>
                    <td><?= date('d M Y', strtotime($l['start_date'])) ?></td>
                    <td><?= date('d M Y', strtotime($l['end_date'])) ?></td>
                    <td><?= $l['hours'] ? $l['hours'].'h' : '—' ?></td>
                    <td style="font-size:13px;color:#666;max-width:150px;"><?= htmlspecialchars($l['note'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= $l['status'] ?>"><?= ucfirst($l['status']) ?></span></td>
                    <td>
                        <div class="actions">
                            <?php if ($l['status'] === 'pending'): ?>
                                <a href="?approve=<?= $l['leave_id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Approve this leave?')">✅ Approve</a>
                                <a href="?reject=<?= $l['leave_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Reject this leave?')">❌ Reject</a>
                            <?php else: ?>
                                <span style="font-size:12px;color:#aaa;"><?= $l['approved_by_name'] ?? '—' ?></span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
