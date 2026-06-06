<?php
session_start();

// Path to backend config — frontend/client/ is 2 levels up from backend/
require_once __DIR__ . '/../../backend/config/database.php';

// If no staff session, auto-login first active staff for testing
if (!isset($_SESSION['staff_id'])) {
    $conn  = getConnection();
    $staff = $conn->query("SELECT staff_id, first_name, last_name, staff_number FROM staff WHERE LOWER(status)='active' LIMIT 1")->fetch_assoc();
    if ($staff) {
        $_SESSION['staff_id']     = $staff['staff_id'];
        $_SESSION['staff_name']   = $staff['first_name'] . ' ' . $staff['last_name'];
        $_SESSION['staff_number'] = $staff['staff_number'];
    }
    $conn->close();
}

$conn    = getConnection();
$staffId = intval($_SESSION['staff_id'] ?? 0);

// ── CLOCK IN ─────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'clock_in') {
    $check = $conn->query("SELECT attendance_id FROM attendance WHERE staff_id=$staffId AND clock_out IS NULL AND DATE(clock_in)=CURDATE()");
    if ($check && $check->num_rows === 0) {
        $rosterRes = $conn->query("SELECT roster_id, start_time FROM roster WHERE staff_id=$staffId AND work_date=CURDATE() LIMIT 1");
        $roster    = $rosterRes ? $rosterRes->fetch_assoc() : null;
        $rid       = $roster ? intval($roster['roster_id']) : null;
        $status    = 'present';
        if ($roster && time() > strtotime(date('Y-m-d').' '.$roster['start_time']) + 300) $status = 'late';
        if ($rid) {
            $conn->query("INSERT INTO attendance (roster_id, staff_id, clock_in, clock_in_method, attendance_status) VALUES ($rid, $staffId, NOW(), 'manual', '$status')");
        } else {
            $conn->query("INSERT INTO attendance (staff_id, clock_in, clock_in_method, attendance_status) VALUES ($staffId, NOW(), 'manual', '$status')");
        }
    }
    header('Location: dashboard.php'); exit();
}

// ── CLOCK OUT ────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'clock_out') {
    $conn->query("UPDATE attendance SET clock_out=NOW(), clock_out_method='manual' WHERE staff_id=$staffId AND clock_out IS NULL AND DATE(clock_in)=CURDATE()");
    header('Location: dashboard.php'); exit();
}

// ── BREAK START ───────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'break_start') {
    $attRes = $conn->query("SELECT attendance_id FROM attendance WHERE staff_id=$staffId AND clock_out IS NULL AND DATE(clock_in)=CURDATE() LIMIT 1");
    $att    = $attRes ? $attRes->fetch_assoc() : null;
    if ($att) {
        $aid = intval($att['attendance_id']);
        $conn->query("INSERT INTO attendance_breaks (attendance_id, break_start, break_end, break_reason, created_by) VALUES ($aid, NOW(), NOW(), 'Rest', $staffId)");
        $_SESSION['on_break']  = true;
        $_SESSION['break_att'] = $aid;
    }
    header('Location: dashboard.php'); exit();
}

// ── BREAK END ─────────────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'break_end') {
    if (isset($_SESSION['break_att'])) {
        $aid = intval($_SESSION['break_att']);
        $conn->query("UPDATE attendance_breaks SET break_end=NOW() WHERE attendance_id=$aid AND break_end=break_start ORDER BY break_id DESC LIMIT 1");
        unset($_SESSION['on_break'], $_SESSION['break_att']);
    }
    header('Location: dashboard.php'); exit();
}

// ── LOAD STAFF DATA ───────────────────────────────────────────
$staffRes  = $conn->query("SELECT s.*, r.role_name FROM staff s LEFT JOIN roles r ON s.role_id=r.role_id WHERE s.staff_id=$staffId LIMIT 1");
$staffData = $staffRes ? $staffRes->fetch_assoc() : [];

// Today's attendance
$attRes   = $conn->query("SELECT attendance_id, clock_in, clock_out, TIMESTAMPDIFF(MINUTE, clock_in, IFNULL(clock_out, NOW())) AS mins_worked FROM attendance WHERE staff_id=$staffId AND DATE(clock_in)=CURDATE() ORDER BY clock_in DESC LIMIT 1");
$todayAtt = $attRes ? $attRes->fetch_assoc() : null;

$isClockedIn = $todayAtt && !$todayAtt['clock_out'];
$isOnBreak   = $_SESSION['on_break'] ?? false;
$workedMins  = $todayAtt ? intval($todayAtt['mins_worked']) : 0;
$workedH     = floor($workedMins / 60);
$workedM     = $workedMins % 60;

// Next shift
$nextShiftRes = $conn->query("SELECT ro.work_date, ro.start_time, ro.end_time, si.site_name FROM roster ro LEFT JOIN sites si ON ro.site_id=si.site_id WHERE ro.staff_id=$staffId AND ro.work_date >= CURDATE() ORDER BY ro.work_date ASC LIMIT 1");
$nextShift    = $nextShiftRes ? $nextShiftRes->fetch_assoc() : null;

$conn->close();

$staffName = $staffData['first_name'] ?? ($_SESSION['staff_name'] ?? 'Worker');
$roleName  = $staffData['role_name'] ?? 'Field Worker';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Farm Time — Employee Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"/>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #f0f0e8; display: flex; min-height: 100vh; font-size: 14px; color: #333; }

        /* Sidebar */
        .sidebar { width: 220px; background: #5a5e2a; color: #fff; min-height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; z-index: 100; }
        .sidebar-brand { padding: 20px 20px 16px; font-size: 18px; font-weight: 700; color: #fff; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar a { display: flex; align-items: center; gap: 10px; padding: 12px 20px; color: rgba(255,255,255,0.8); text-decoration: none; font-size: 14px; transition: all 0.15s; }
        .sidebar a:hover  { background: rgba(255,255,255,0.1); color: #fff; }
        .sidebar a.active { background: rgba(255,255,255,0.15); color: #fff; font-weight: 600; }
        .sidebar-footer { margin-top: auto; padding: 16px 20px; border-top: 1px solid rgba(255,255,255,0.1); }
        .sidebar-footer a { color: rgba(255,100,100,0.85); font-size: 13px; display: flex; align-items: center; gap: 8px; text-decoration: none; }

        /* Main */
        .main { margin-left: 220px; flex: 1; display: flex; flex-direction: column; }

        /* Topbar */
        .topbar { background: #fff; border-bottom: 1px solid #e0e0d8; padding: 14px 28px; display: flex; align-items: center; justify-content: space-between; }
        .topbar-left h4 { font-size: 20px; font-weight: 700; margin: 0; color: #2c2c1a; }
        .topbar-left small { font-size: 12px; color: #888; }
        .topbar-right { font-size: 13px; color: #555; display: flex; align-items: center; gap: 14px; }
        .role-tag { font-weight: 700; color: #5a5e2a; }

        /* Content */
        .content { padding: 24px 28px; flex: 1; }

        /* Status Card */
        .status-card { background: #fff; border: 1px solid #e0e0d8; border-radius: 10px; padding: 18px 22px; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }
        .status-label { font-size: 12px; color: #888; margin-bottom: 4px; }
        .status-value { font-size: 16px; font-weight: 700; }
        .not-clocked { color: #dc2626; }
        .working     { color: #16a34a; }
        .on-break-st { color: #d97706; }
        .status-icon { width: 40px; height: 40px; border-radius: 8px; background: #f0f0e8; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #5a5e2a; }

        /* Clock Button */
        .clock-section { text-align: center; margin-bottom: 24px; padding: 10px 0; }
        .btn-clock-in  { background: #2563eb; color: #fff; border: none; padding: 14px 70px; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; letter-spacing: 1px; }
        .btn-clock-in:hover  { background: #1d4ed8; }
        .btn-clock-out { background: #dc2626; color: #fff; border: none; padding: 14px 70px; border-radius: 8px; font-size: 16px; font-weight: 700; cursor: pointer; letter-spacing: 1px; }
        .btn-clock-out:hover { background: #b91c1c; }
        .worked-today { font-size: 13px; color: #888; margin-top: 10px; }

        /* Action Cards */
        .action-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px; }
        .action-card { background: #fff; border: 1px solid #e0e0d8; border-radius: 10px; padding: 18px 16px; text-align: center; }
        .action-card h6 { font-size: 14px; font-weight: 600; color: #333; margin-bottom: 14px; }
        .btn-ac { width: 100%; padding: 9px 14px; border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all 0.15s; text-decoration: none; display: block; text-align: center; }
        .btn-ac-break    { background: #fff; border: 1px solid #ddd; color: #333; }
        .btn-ac-break:hover { border-color: #5a5e2a; color: #5a5e2a; }
        .btn-ac-break.active-break { background: #fef3c7; border-color: #d97706; color: #d97706; }
        .btn-ac-blue  { background: #fff; border: 1px solid #2563eb; color: #2563eb; }
        .btn-ac-blue:hover { background: #2563eb; color: #fff; }
        .btn-ac-red   { background: #fff; border: 1px solid #dc2626; color: #dc2626; }
        .btn-ac-red:hover { background: #dc2626; color: #fff; }

        /* Next Shift */
        .next-shift-card { background: #fff; border: 1px solid #e0e0d8; border-radius: 10px; padding: 30px; text-align: center; }
        .next-shift-card h5 { font-size: 16px; font-weight: 600; color: #333; margin-bottom: 10px; }
        .shift-day  { font-size: 14px; color: #666; }
        .shift-time { font-size: 18px; font-weight: 700; color: #2c2c1a; margin: 4px 0; }
        .shift-loc  { font-size: 13px; color: #888; }
        .no-shift   { color: #aaa; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-brand">Farm Time</div>
    <a href="dashboard.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a href="roster.php"><i class="bi bi-calendar-week"></i> Rosters</a>
    <a href="attendance.php"><i class="bi bi-clock-history"></i> Attendance</a>
    <a href="payslips.php"><i class="bi bi-receipt"></i> Payslips</a>
    <a href="setting.php"><i class="bi bi-gear"></i> Settings</a>
    <div class="sidebar-footer">
        <a href="../../backend/logout.php">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</div>

<!-- Main -->
<div class="main">
    <div class="topbar">
        <div class="topbar-left">
            <h4>👋 Welcome <?= htmlspecialchars($staffName) ?></h4>
            <small>Employee Dashboard</small>
        </div>
        <div class="topbar-right">
            <?= date('d M Y') ?>
            <span class="role-tag"><?= htmlspecialchars($roleName) ?></span>
        </div>
    </div>

    <div class="content">

        <!-- Work Status -->
        <div class="status-card">
            <div>
                <div class="status-label">Work Status</div>
                <?php if ($isOnBreak): ?>
                    <div class="status-value on-break-st">On Break</div>
                <?php elseif ($isClockedIn): ?>
                    <div class="status-value working">
                        Working — since <?= date('g:i A', strtotime($todayAtt['clock_in'])) ?>
                    </div>
                <?php else: ?>
                    <div class="status-value not-clocked">Not Clocked In</div>
                <?php endif; ?>
            </div>
            <div class="status-icon">
                <i class="bi bi-person-workspace"></i>
            </div>
        </div>

        <!-- Clock Button -->
        <div class="clock-section">
            <?php if ($isClockedIn): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="clock_out">
                    <button type="submit" class="btn-clock-out"
                        onclick="return confirm('Clock out now?')">CLOCK OUT</button>
                </form>
            <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="clock_in">
                    <button type="submit" class="btn-clock-in">CLOCK IN</button>
                </form>
            <?php endif; ?>
            <div class="worked-today">
                Worked Today: <strong id="workedDisplay"><?= $workedH ?>h <?= $workedM ?>m</strong>
            </div>
        </div>

        <!-- Action Cards -->
        <div class="action-cards">
            <div class="action-card">
                <h6>Break</h6>
                <?php if ($isClockedIn): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="<?= $isOnBreak ? 'break_end' : 'break_start' ?>">
                        <button type="submit" class="btn-ac btn-ac-break <?= $isOnBreak ? 'active-break' : '' ?>">
                            <?= $isOnBreak ? 'End Break' : 'Start Break' ?>
                        </button>
                    </form>
                <?php else: ?>
                    <button class="btn-ac btn-ac-break" disabled style="opacity:0.5;cursor:not-allowed;">Start Break</button>
                <?php endif; ?>
            </div>

            <div class="action-card">
                <h6>Payslips</h6>
                <a href="payslips.php" class="btn-ac btn-ac-blue">View Payslips</a>
            </div>

            <div class="action-card">
                <h6>Timesheet</h6>
                <a href="attendance.php" class="btn-ac btn-ac-blue">View Timesheet</a>
            </div>

            <div class="action-card">
                <h6>Leave</h6>
                <a href="leave.php" class="btn-ac btn-ac-red">Request Leave</a>
            </div>
        </div>

        <!-- Next Shift -->
        <div class="next-shift-card">
            <h5>Next Shift</h5>
            <?php if ($nextShift): ?>
                <div class="shift-day"><?= date('l', strtotime($nextShift['work_date'])) ?></div>
                <div class="shift-time">
                    <?= date('g:i A', strtotime($nextShift['start_time'])) ?> – <?= date('g:i A', strtotime($nextShift['end_time'])) ?>
                </div>
                <?php if ($nextShift['site_name']): ?>
                    <div class="shift-loc"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($nextShift['site_name']) ?></div>
                <?php endif; ?>
                <div style="font-size:12px;color:#aaa;margin-top:4px;"><?= date('d M Y', strtotime($nextShift['work_date'])) ?></div>
            <?php else: ?>
                <div class="no-shift">No upcoming shifts scheduled.</div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
<?php if ($isClockedIn && !$todayAtt['clock_out']): ?>
// Live worked time counter
let startMs  = <?= strtotime($todayAtt['clock_in']) * 1000 ?>;
let baseWorked = <?= $workedMins * 60 ?>;
function updateWorked() {
    let diff = Math.floor((Date.now() - startMs) / 1000);
    let h = Math.floor(diff / 3600);
    let m = Math.floor((diff % 3600) / 60);
    document.getElementById('workedDisplay').textContent = h + 'h ' + m + 'm';
}
updateWorked();
setInterval(updateWorked, 10000);
<?php endif; ?>
</script>
</body>
</html>
