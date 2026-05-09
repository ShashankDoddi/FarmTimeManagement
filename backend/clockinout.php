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

// ── CLOCK IN ─────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'clock_in') {
    $staff_id        = intval($_POST['staff_id']);
    $device_id       = intval($_POST['device_id'] ?? 0) ?: null;
    $clock_in_method = $_POST['clock_in_method'] ?? 'manual';

    // Already clocked in?
    $check = $conn->prepare("SELECT attendance_id FROM attendance WHERE staff_id=? AND clock_out IS NULL LIMIT 1");
    $check->bind_param('i', $staff_id);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $message = 'This staff member is already clocked in!';
        $msgType = 'warning';
    } else {
        // Check roster for lateness
        $rStmt = $conn->prepare("SELECT roster_id, start_time FROM roster WHERE staff_id=? AND work_date=CURDATE() LIMIT 1");
        $rStmt->bind_param('i', $staff_id);
        $rStmt->execute();
        $roster = $rStmt->get_result()->fetch_assoc();
        $rStmt->close();

        $roster_id = $roster['roster_id'] ?? null;
        $status    = 'present';
        if ($roster) {
            $scheduled = strtotime(date('Y-m-d').' '.$roster['start_time']);
            if (time() > $scheduled + 300) $status = 'late';
        }

        $stmt = $conn->prepare("
            INSERT INTO attendance (roster_id, staff_id, device_id, clock_in, clock_in_method, attendance_status)
            VALUES (?, ?, ?, NOW(), ?, ?)
        ");
        $stmt->bind_param('iiiss', $roster_id, $staff_id, $device_id, $clock_in_method, $status);

        if ($stmt->execute()) {
            auditLog($conn, 'CREATE', 'attendance', $conn->insert_id, 'Staff clocked in');
            $message = 'Clock in recorded!' . ($status === 'late' ? ' ⚠️ Marked as Late.' : '');
            $msgType = $status === 'late' ? 'warning' : 'success';
        }
        $stmt->close();
    }
    $check->close();
}

// ── CLOCK OUT ────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'clock_out') {
    $attendance_id    = intval($_POST['attendance_id']);
    $clock_out_method = $_POST['clock_out_method'] ?? 'manual';

    $stmt = $conn->prepare("UPDATE attendance SET clock_out=NOW(), clock_out_method=? WHERE attendance_id=? AND clock_out IS NULL");
    $stmt->bind_param('si', $clock_out_method, $attendance_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        auditLog($conn, 'UPDATE', 'attendance', $attendance_id, 'Staff clocked out');
        $message = 'Clock out recorded successfully!';
        $msgType = 'success';
    } else {
        $message = 'Clock out failed or already clocked out.';
        $msgType = 'error';
    }
    $stmt->close();
}

// ── LOAD DATA ─────────────────────────────────────────────────
// Active staff
$staff = $conn->query("
    SELECT s.staff_id, s.staff_number, s.first_name, s.last_name, r.role_name
    FROM staff s
    LEFT JOIN roles r ON s.role_id = r.role_id
    WHERE s.status = 'active'
    ORDER BY s.first_name
")->fetch_all(MYSQLI_ASSOC);

// Devices
$devices = $conn->query("SELECT device_id, device_name, location FROM devices ORDER BY device_name")->fetch_all(MYSQLI_ASSOC);

// Currently clocked in
$clockedIn = $conn->query("
    SELECT a.attendance_id, a.staff_id, a.clock_in, a.clock_in_method,
           CONCAT(s.first_name,' ',s.last_name) AS staff_name,
           s.staff_number, r.role_name,
           TIMESTAMPDIFF(MINUTE, a.clock_in, NOW()) AS minutes_worked
    FROM attendance a
    JOIN staff s ON a.staff_id = s.staff_id
    LEFT JOIN roles r ON s.role_id = r.role_id
    WHERE DATE(a.clock_in) = CURDATE() AND a.clock_out IS NULL
    ORDER BY a.clock_in
")->fetch_all(MYSQLI_ASSOC);

// Today's full attendance
$todayAll = $conn->query("
    SELECT a.attendance_id, a.clock_in, a.clock_out,
           a.clock_in_method, a.clock_out_method, a.attendance_status,
           CONCAT(s.first_name,' ',s.last_name) AS staff_name,
           s.staff_number, r.role_name, d.device_name,
           TIMESTAMPDIFF(MINUTE, a.clock_in, IFNULL(a.clock_out, NOW())) AS mins
    FROM attendance a
    JOIN staff s ON a.staff_id = s.staff_id
    LEFT JOIN roles r ON s.role_id = r.role_id
    LEFT JOIN devices d ON a.device_id = d.device_id
    WHERE DATE(a.clock_in) = CURDATE()
    ORDER BY a.clock_in DESC
")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clock In / Out — Workforce</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',sans-serif; background:#f0f2f5; display:flex; min-height:100vh; }

        /* Sidebar */
        .sidebar { width:230px; background:#1a1a2e; color:#fff; min-height:100vh; position:fixed; top:0; left:0; display:flex; flex-direction:column; }
        .sidebar-brand { padding:22px 20px; font-size:17px; font-weight:700; border-bottom:1px solid rgba(255,255,255,0.08); }
        .sidebar-user  { padding:14px 20px; border-bottom:1px solid rgba(255,255,255,0.08); font-size:13px; }
        .sidebar-user .name { font-weight:600; font-size:14px; }
        .sidebar-user .role { color:#aaa; font-size:12px; margin-top:2px; }
        .nav-group { padding:10px 20px 2px; font-size:10px; font-weight:700; color:#555; text-transform:uppercase; letter-spacing:1px; margin-top:6px; }
        .nav-link { display:flex; align-items:center; gap:10px; padding:10px 20px; color:#bbb; text-decoration:none; font-size:14px; transition:all 0.15s; border-left:3px solid transparent; }
        .nav-link:hover  { background:rgba(255,255,255,0.07); color:#fff; }
        .nav-link.active { background:rgba(79,70,229,0.18); color:#fff; border-left-color:#4f46e5; }
        .sidebar-footer { margin-top:auto; padding:16px 20px; border-top:1px solid rgba(255,255,255,0.08); }
        .btn-logout { display:block; text-align:center; background:#dc2626; color:#fff; padding:9px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600; }

        /* Main */
        .main { margin-left:230px; flex:1; }
        .topbar { background:#fff; padding:0 28px; height:58px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 1px 4px rgba(0,0,0,0.08); }
        .topbar-title { font-size:18px; font-weight:700; color:#1a1a2e; }
        .content { padding:24px 28px; }

        /* Messages */
        .msg { padding:13px 18px; border-radius:8px; margin-bottom:20px; font-size:14px; font-weight:500; }
        .msg.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#16a34a; }
        .msg.error   { background:#fff5f5; border:1px solid #fecaca; color:#dc2626; }
        .msg.warning { background:#fffbeb; border:1px solid #fde68a; color:#d97706; }

        /* Grid */
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:24px; }

        /* Cards */
        .card { background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.06); overflow:hidden; }
        .card-head { padding:16px 20px; border-bottom:1px solid #f0f0f0; font-size:15px; font-weight:600; color:#1a1a2e; display:flex; align-items:center; justify-content:space-between; }
        .card-body { padding:20px; }

        /* Live Clock */
        .live-clock { text-align:center; font-size:40px; font-weight:700; color:#1a1a2e; letter-spacing:3px; padding:10px 0 4px; }
        .live-date  { text-align:center; font-size:13px; color:#888; margin-bottom:18px; }

        /* Form */
        .form-group { margin-bottom:14px; }
        label { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:5px; }
        select { width:100%; padding:10px 14px; border:1.5px solid #e0e0e0; border-radius:8px; font-size:14px; outline:none; }
        select:focus { border-color:#4f46e5; box-shadow:0 0 0 3px rgba(79,70,229,0.1); }

        .btn-clock-in  { width:100%; padding:13px; background:#16a34a; color:#fff; border:none; border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; transition:background 0.2s; }
        .btn-clock-in:hover { background:#15803d; }
        .btn-clock-out { padding:7px 12px; background:#dc2626; color:#fff; border:none; border-radius:6px; font-size:13px; font-weight:600; cursor:pointer; }
        .btn-clock-out:hover { background:#b91c1c; }

        /* Clocked in list */
        .clocked-item { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px 16px; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between; }
        .clocked-item:last-child { margin-bottom:0; }
        .clocked-name { font-weight:600; font-size:14px; color:#1a1a2e; }
        .clocked-sub  { font-size:12px; color:#666; margin-top:2px; }
        .clocked-form { display:flex; align-items:center; gap:8px; }
        .clocked-form select { width:auto; padding:6px 10px; font-size:12px; }

        /* Table */
        table { width:100%; border-collapse:collapse; }
        th { background:#f8f9fa; padding:11px 16px; text-align:left; font-size:12px; font-weight:600; color:#888; text-transform:uppercase; }
        td { padding:12px 16px; font-size:13px; color:#333; border-bottom:1px solid #f5f5f5; vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tr:hover td { background:#fafafa; }

        .badge { display:inline-block; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:600; }
        .b-present { background:#f0fdf4; color:#16a34a; }
        .b-late    { background:#fffbeb; color:#d97706; }
        .b-absent  { background:#fff5f5; color:#dc2626; }
        .b-partial { background:#eff6ff; color:#2563eb; }

        .still-in { color:#16a34a; font-size:12px; font-weight:600; }
        .empty { text-align:center; padding:32px; color:#bbb; font-size:13px; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-brand">⏱ Workforce</div>
    <div class="sidebar-user">
        <div class="name">👤 <?= htmlspecialchars($_SESSION['username']) ?></div>
        <div class="role"><?= htmlspecialchars($_SESSION['permission_level']) ?> • <?= htmlspecialchars($_SESSION['site_name'] ?? '') ?></div>
    </div>
    <div class="nav-group">Main</div>
    <a href="dashboard.php"  class="nav-link">🏠 Dashboard</a>
    <a href="clockinout.php" class="nav-link active">⏱ Clock In / Out</a>
    <div class="nav-group">Management</div>
    <a href="staff.php"      class="nav-link">👥 Staff</a>
    <a href="contracts.php"  class="nav-link">📄 Contracts</a>
    <a href="roster.php"     class="nav-link">📅 Roster</a>
    <a href="leave.php"      class="nav-link">🏖️ Leave</a>
    <div class="nav-group">Payroll</div>
    <a href="payroll.php"    class="nav-link">💰 Payroll</a>
    <div class="nav-group">System</div>
    <a href="auditlogs.php"  class="nav-link">🔍 Audit Logs</a>
    <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout">🚪 Logout</a>
    </div>
</div>

<!-- Main -->
<div class="main">
    <div class="topbar">
        <div class="topbar-title">⏱ Clock In / Clock Out</div>
        <div style="font-size:13px;color:#888;"><?= date('l, F j, Y') ?></div>
    </div>

    <div class="content">

        <?php if ($message): ?>
            <div class="msg <?= $msgType ?>">
                <?= $msgType==='success' ? '✅' : ($msgType==='warning' ? '⚠️' : '❌') ?>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="grid-2">

            <!-- Clock In Form -->
            <div class="card">
                <div class="card-head">✅ Clock In</div>
                <div class="card-body">
                    <div class="live-clock" id="clock">--:--:--</div>
                    <div class="live-date"  id="cdate"></div>

                    <form method="POST" action="clockinout.php">
                        <input type="hidden" name="action" value="clock_in">

                        <div class="form-group">
                            <label>Staff Member *</label>
                            <select name="staff_id" required>
                                <option value="">— Select Staff Member —</option>
                                <?php foreach ($staff as $s): ?>
                                    <option value="<?= $s['staff_id'] ?>">
                                        <?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?>
                                        (<?= htmlspecialchars($s['staff_number']) ?>)
                                        — <?= htmlspecialchars($s['role_name'] ?? '') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Clock In Method *</label>
                            <select name="clock_in_method" required>
                                <option value="manual">Manual</option>
                                <option value="biometric">Biometric</option>
                                <option value="card">Card</option>
                                <option value="pin">PIN</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Device (optional)</label>
                            <select name="device_id">
                                <option value="">— No Device —</option>
                                <?php foreach ($devices as $d): ?>
                                    <option value="<?= $d['device_id'] ?>">
                                        <?= htmlspecialchars($d['device_name']) ?>
                                        <?= $d['location'] ? '('.$d['location'].')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button type="submit" class="btn-clock-in">✅ Clock In Now</button>
                    </form>
                </div>
            </div>

            <!-- Currently Clocked In -->
            <div class="card">
                <div class="card-head">
                    🟢 Currently Clocked In
                    <span style="background:#f0fdf4;color:#16a34a;padding:3px 10px;border-radius:20px;font-size:12px;">
                        <?= count($clockedIn) ?> staff
                    </span>
                </div>
                <div class="card-body">
                    <?php if (empty($clockedIn)): ?>
                        <div class="empty">No staff currently clocked in.</div>
                    <?php else: ?>
                        <?php foreach ($clockedIn as $a): ?>
                        <div class="clocked-item">
                            <div>
                                <div class="clocked-name">👤 <?= htmlspecialchars($a['staff_name']) ?></div>
                                <div class="clocked-sub">
                                    <?= htmlspecialchars($a['staff_number']) ?> •
                                    <?= htmlspecialchars($a['role_name'] ?? '') ?> •
                                    In: <?= date('h:i A', strtotime($a['clock_in'])) ?> •
                                    <?= $a['minutes_worked'] >= 60
                                        ? floor($a['minutes_worked']/60).'h '.($a['minutes_worked']%60).'m'
                                        : $a['minutes_worked'].'m' ?>
                                </div>
                            </div>
                            <form method="POST" action="clockinout.php" class="clocked-form">
                                <input type="hidden" name="action" value="clock_out">
                                <input type="hidden" name="attendance_id" value="<?= $a['attendance_id'] ?>">
                                <select name="clock_out_method">
                                    <option value="manual">Manual</option>
                                    <option value="biometric">Biometric</option>
                                    <option value="card">Card</option>
                                    <option value="pin">PIN</option>
                                </select>
                                <button type="submit" class="btn-clock-out">🚪 Out</button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Today's Attendance Table -->
        <div class="card">
            <div class="card-head">
                📋 Today's Attendance — <?= date('l, F j, Y') ?>
                <span style="background:#eff6ff;color:#2563eb;padding:3px 10px;border-radius:20px;font-size:12px;">
                    <?= count($todayAll) ?> records
                </span>
            </div>
            <?php if (empty($todayAll)): ?>
                <div class="empty">No attendance records for today yet.</div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Clock In</th>
                        <th>Clock Out</th>
                        <th>Duration</th>
                        <th>Method</th>
                        <th>Device</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todayAll as $a): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($a['staff_name']) ?></strong><br>
                            <span style="font-size:11px;color:#888;"><?= $a['staff_number'] ?> • <?= $a['role_name'] ?></span>
                        </td>
                        <td><strong><?= date('h:i A', strtotime($a['clock_in'])) ?></strong></td>
                        <td>
                            <?php if ($a['clock_out']): ?>
                                <strong><?= date('h:i A', strtotime($a['clock_out'])) ?></strong>
                            <?php else: ?>
                                <span class="still-in">● Still In</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $h = floor($a['mins'] / 60);
                            $m = $a['mins'] % 60;
                            echo $h > 0 ? "{$h}h {$m}m" : "{$m}m";
                            ?>
                        </td>
                        <td style="font-size:12px;color:#666;">
                            <?= ucfirst($a['clock_in_method']) ?>
                            <?= $a['clock_out_method'] ? ' / '.ucfirst($a['clock_out_method']) : '' ?>
                        </td>
                        <td style="font-size:12px;color:#888;"><?= htmlspecialchars($a['device_name'] ?? '—') ?></td>
                        <td><span class="badge b-<?= $a['attendance_status'] ?>"><?= ucfirst($a['attendance_status']) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
function tick() {
    const now  = new Date();
    document.getElementById('clock').textContent = now.toLocaleTimeString('en-AU', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
    document.getElementById('cdate').textContent = now.toLocaleDateString('en-AU', {weekday:'long', year:'numeric', month:'long', day:'numeric'});
}
tick();
setInterval(tick, 1000);
</script>

</body>
</html>
