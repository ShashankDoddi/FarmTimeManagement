<?php
// roster/index.php
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
    $id = intval($_GET['delete']);
    $conn->prepare("DELETE FROM roster WHERE roster_id = ?")->bind_param('i', $id) && $conn->prepare("DELETE FROM roster WHERE roster_id = ?")->execute();
    $stmt = $conn->prepare("DELETE FROM roster WHERE roster_id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    auditLog($conn, 'DELETE', 'roster', $id, 'Roster entry deleted');
    $message = 'Roster entry deleted.';
    $msgType = 'success';
}

// ── CREATE / UPDATE ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = intval($_POST['roster_id'] ?? 0);
    $staff_id   = intval($_POST['staff_id']);
    $site_id    = intval($_POST['site_id']);
    $work_date  = $_POST['work_date'];
    $shift_type = $_POST['shift_type'];
    $start_time = $_POST['start_time'];
    $end_time   = $_POST['end_time'];
    $adminId    = currentAdmin();

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE roster SET staff_id=?,site_id=?,work_date=?,shift_type=?,start_time=?,end_time=?,admin_id=? WHERE roster_id=?");
        $stmt->bind_param('iissssii', $staff_id, $site_id, $work_date, $shift_type, $start_time, $end_time, $adminId, $id);
        if ($stmt->execute()) {
            auditLog($conn, 'UPDATE', 'roster', $id, 'Roster updated');
            $message = 'Roster updated.'; $msgType = 'success';
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO roster (staff_id,site_id,admin_id,work_date,shift_type,start_time,end_time,created_by) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('iiissssi', $staff_id, $site_id, $adminId, $work_date, $shift_type, $start_time, $end_time, $adminId);
        if ($stmt->execute()) {
            auditLog($conn, 'CREATE', 'roster', $conn->insert_id, 'Roster created');
            $message = 'Roster entry added.'; $msgType = 'success';
        }
    }
    $stmt->close();
}

// Edit
$editRoster = null;
if (isset($_GET['edit'])) {
    $stmt = $conn->prepare("SELECT * FROM roster WHERE roster_id = ?");
    $stmt->bind_param('i', $_GET['edit']);
    $stmt->execute();
    $editRoster = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Filters
$filterDate = $_GET['date'] ?? date('Y-m-d');
$filterStaff = intval($_GET['staff_id'] ?? 0);

$sql = "
    SELECT r.*, CONCAT(s.first_name,' ',s.last_name) AS staff_name,
           s.staff_number, ro.role_name, si.site_name
    FROM roster r
    JOIN staff s ON r.staff_id = s.staff_id
    LEFT JOIN roles ro ON s.role_id = ro.role_id
    LEFT JOIN sites si ON r.site_id = si.site_id
    WHERE r.work_date = '$filterDate'
";
if ($filterStaff > 0) $sql .= " AND r.staff_id = $filterStaff";
$sql .= " ORDER BY r.start_time";
$rosterList = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$staffList = $conn->query("SELECT staff_id, staff_number, first_name, last_name FROM staff WHERE status='active' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);
$sites     = $conn->query("SELECT site_id, site_name FROM sites ORDER BY site_name")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Roster</title>
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
        .grid-2 { display:grid; grid-template-columns:1fr 1.5fr; gap:20px; margin-bottom:24px; }
        .card { background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.06); overflow:hidden; margin-bottom:24px; }
        .card-header { padding:16px 22px; border-bottom:1px solid #f0f0f0; font-size:16px; font-weight:600; color:#1a1a2e; }
        .card-body { padding:22px; }
        .message { padding:12px 16px; border-radius:8px; margin-bottom:18px; font-size:14px; }
        .message.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#16a34a; }
        .form-group { margin-bottom:14px; }
        label { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:5px; }
        input, select { width:100%; padding:10px 14px; border:1.5px solid #e0e0e0; border-radius:8px; font-size:14px; outline:none; }
        input:focus, select:focus { border-color:#4f46e5; }
        .btn { padding:10px 20px; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary { background:#4f46e5; color:#fff; }
        .btn-warning { background:#d97706; color:#fff; }
        .btn-danger  { background:#dc2626; color:#fff; }
        .btn-sm { padding:6px 12px; font-size:12px; }
        .filters { display:flex; gap:12px; margin-bottom:20px; align-items:center; flex-wrap:wrap; }
        .filters input, .filters select { width:auto; padding:8px 12px; }
        table { width:100%; border-collapse:collapse; }
        th { background:#f8f9fa; padding:12px 16px; text-align:left; font-size:12px; font-weight:600; color:#888; text-transform:uppercase; }
        td { padding:13px 16px; font-size:14px; border-bottom:1px solid #f5f5f5; vertical-align:middle; }
        .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; }
        .badge-standard  { background:#eff6ff; color:#2563eb; }
        .badge-overtime  { background:#fff5f5; color:#dc2626; }
        .badge-night     { background:#1a1a2e; color:#fff; }
        .badge-on_call   { background:#fefce8; color:#d97706; }
        .actions { display:flex; gap:8px; }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="navbar-brand">⏱ Workforce Management</div>
    <div class="navbar-right">
        <a href="../dashboard.php" class="btn-nav">🏠 Dashboard</a>
        <a href="../staff/index.php" class="btn-nav">👥 Staff</a>
        <a href="../attendance.php" class="btn-nav">⏱ Attendance</a>
        <a href="../logout.php" class="btn-logout">🚪 Logout</a>
    </div>
</nav>

<div class="content">
    <div class="page-title">📅 Roster Management</div>

    <?php if ($message): ?>
        <div class="message <?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <!-- Form -->
    <div class="card">
        <div class="card-header"><?= $editRoster ? '✏️ Edit Shift' : '➕ Add Shift' ?></div>
        <div class="card-body">
            <form method="POST">
                <?php if ($editRoster): ?>
                    <input type="hidden" name="roster_id" value="<?= $editRoster['roster_id'] ?>">
                <?php endif; ?>
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;">
                    <div class="form-group">
                        <label>Staff Member *</label>
                        <select name="staff_id" required>
                            <option value="">— Select —</option>
                            <?php foreach ($staffList as $s): ?>
                                <option value="<?= $s['staff_id'] ?>" <?= ($editRoster['staff_id'] ?? '') == $s['staff_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Site *</label>
                        <select name="site_id" required>
                            <?php foreach ($sites as $site): ?>
                                <option value="<?= $site['site_id'] ?>" <?= ($editRoster['site_id'] ?? '') == $site['site_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($site['site_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Work Date *</label>
                        <input type="date" name="work_date" value="<?= $editRoster['work_date'] ?? date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Shift Type</label>
                        <select name="shift_type">
                            <option value="standard" <?= ($editRoster['shift_type'] ?? '') === 'standard' ? 'selected' : '' ?>>Standard</option>
                            <option value="overtime" <?= ($editRoster['shift_type'] ?? '') === 'overtime' ? 'selected' : '' ?>>Overtime</option>
                            <option value="night"    <?= ($editRoster['shift_type'] ?? '') === 'night'    ? 'selected' : '' ?>>Night</option>
                            <option value="on_call"  <?= ($editRoster['shift_type'] ?? '') === 'on_call'  ? 'selected' : '' ?>>On Call</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Start Time *</label>
                        <input type="time" name="start_time" value="<?= $editRoster['start_time'] ?? '09:00' ?>" required>
                    </div>
                    <div class="form-group">
                        <label>End Time *</label>
                        <input type="time" name="end_time" value="<?= $editRoster['end_time'] ?? '17:00' ?>" required>
                    </div>
                </div>
                <div style="margin-top:16px;display:flex;gap:10px;">
                    <button type="submit" class="btn btn-primary"><?= $editRoster ? '💾 Update' : '➕ Add Shift' ?></button>
                    <?php if ($editRoster): ?><a href="index.php" class="btn btn-warning">Cancel</a><?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Filters -->
    <form method="GET">
        <div class="filters">
            <label style="margin:0;">📅 Date:</label>
            <input type="date" name="date" value="<?= $filterDate ?>">
            <select name="staff_id">
                <option value="">All Staff</option>
                <?php foreach ($staffList as $s): ?>
                    <option value="<?= $s['staff_id'] ?>" <?= $filterStaff == $s['staff_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
        </div>
    </form>

    <!-- Table -->
    <div class="card">
        <div class="card-header">
            📋 Roster for <?= date('l, F j, Y', strtotime($filterDate)) ?>
            <span style="margin-left:auto;background:#eff6ff;color:#2563eb;padding:3px 10px;border-radius:20px;font-size:13px;"><?= count($rosterList) ?> shifts</span>
        </div>
        <?php if (empty($rosterList)): ?>
            <div style="text-align:center;padding:40px;color:#aaa;">No roster entries for this date.</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Staff</th><th>Site</th><th>Shift</th><th>Start</th><th>End</th><th>Hours</th><th>Actions</th></tr></thead>
            <tbody>
                <?php foreach ($rosterList as $r): ?>
                <?php
                    $start = strtotime($r['start_time']);
                    $end   = strtotime($r['end_time']);
                    $hours = round(($end - $start) / 3600, 1);
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['staff_name']) ?></strong><br><span style="font-size:12px;color:#888;"><?= $r['staff_number'] ?> • <?= $r['role_name'] ?></span></td>
                    <td><?= htmlspecialchars($r['site_name'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= $r['shift_type'] ?>"><?= ucfirst(str_replace('_',' ',$r['shift_type'])) ?></span></td>
                    <td><strong><?= date('h:i A', strtotime($r['start_time'])) ?></strong></td>
                    <td><strong><?= date('h:i A', strtotime($r['end_time'])) ?></strong></td>
                    <td><?= $hours ?>h</td>
                    <td>
                        <div class="actions">
                            <a href="index.php?edit=<?= $r['roster_id'] ?>" class="btn btn-warning btn-sm">✏️</a>
                            <a href="index.php?delete=<?= $r['roster_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this shift?')">🗑</a>
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
