<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit(); }

$conn    = getConnection();
$adminId = $_SESSION['admin_id'];
$msg = ''; $msgType = '';

// MANUAL CLOCK IN
if (isset($_POST['action']) && $_POST['action'] === 'clock_in') {
    $staffId = intval($_POST['staff_id']??0);
    if ($staffId) {
        $exists = $conn->query("SELECT attendance_id FROM attendance WHERE staff_id=$staffId AND DATE(clock_in)=CURDATE() AND clock_out IS NULL");
        if ($exists && $exists->num_rows > 0) { $msg='Staff already clocked in today.'; $msgType='error'; }
        else {
            $roster = $conn->query("SELECT roster_id,start_time FROM roster WHERE staff_id=$staffId AND work_date=CURDATE() LIMIT 1")->fetch_assoc();
            $rid    = $roster ? intval($roster['roster_id']) : null;
            $status = 'present';
            if ($roster && time() > strtotime(date('Y-m-d').' '.$roster['start_time'])+300) $status='late';
            if ($rid) {
                $stmt = $conn->prepare("INSERT INTO attendance (roster_id,staff_id,clock_in,clock_in_method,attendance_status) VALUES (?,?,NOW(),'manual',?)");
                $stmt->bind_param('iis',$rid,$staffId,$status); $stmt->execute(); $stmt->close();
            } else {
                $stmt = $conn->prepare("INSERT INTO attendance (staff_id,clock_in,clock_in_method,attendance_status) VALUES (?,NOW(),'manual',?)");
                $stmt->bind_param('is',$staffId,$status); $stmt->execute(); $stmt->close();
            }
            $msg='Clocked in successfully!'; $msgType='success';
        }
    }
}

// MANUAL CLOCK OUT
if (isset($_POST['action']) && $_POST['action'] === 'clock_out') {
    $attId = intval($_POST['attendance_id']??0);
    $conn->query("UPDATE attendance SET clock_out=NOW(),clock_out_method='manual' WHERE attendance_id=$attId");
    $msg='Clocked out successfully!'; $msgType='success';
}

// Filters
$search    = trim($_GET['search']??'');
$dateFrom  = $_GET['date_from']??date('Y-m-d', strtotime('-7 days'));
$dateTo    = $_GET['date_to']??date('Y-m-d');
$statusF   = $_GET['status']??'all';

$where = "WHERE DATE(a.clock_in) BETWEEN '$dateFrom' AND '$dateTo'";
if ($search) { $ss=$conn->real_escape_string($search); $where.=" AND (s.first_name LIKE '%$ss%' OR s.last_name LIKE '%$ss%' OR s.staff_number LIKE '%$ss%')"; }
if ($statusF!=='all') { $sf=$conn->real_escape_string($statusF); $where.=" AND a.attendance_status='$sf'"; }

$attRes = $conn->query("
    SELECT a.*,CONCAT(s.first_name,' ',s.last_name) AS staff_name,s.staff_number,
           LEFT(s.first_name,1) AS fi,LEFT(s.last_name,1) AS li,r.role_name,
           ro.start_time AS shift_start,ro.end_time AS shift_end,
           TIMESTAMPDIFF(MINUTE,a.clock_in,IFNULL(a.clock_out,NOW())) AS mins_worked
    FROM attendance a
    JOIN staff s ON a.staff_id=s.staff_id
    LEFT JOIN roles r ON s.role_id=r.role_id
    LEFT JOIN roster ro ON a.roster_id=ro.roster_id
    $where
    ORDER BY a.clock_in DESC
    LIMIT 100
");
$attendance = $attRes ? $attRes->fetch_all(MYSQLI_ASSOC) : [];

$staffList = $conn->query("SELECT staff_id,first_name,last_name FROM staff WHERE LOWER(status)='active' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);

function sc3($c,$s){ $r=$c->query($s); return($r&&$r->num_rows>0)?intval($r->fetch_assoc()['c']):0; }
$todayTotal   = sc3($conn,"SELECT COUNT(*) AS c FROM attendance WHERE DATE(clock_in)=CURDATE()");
$todayClocked = sc3($conn,"SELECT COUNT(*) AS c FROM attendance WHERE DATE(clock_in)=CURDATE() AND clock_out IS NULL");
$todayOut     = sc3($conn,"SELECT COUNT(*) AS c FROM attendance WHERE DATE(clock_in)=CURDATE() AND clock_out IS NOT NULL");
$todayLate    = sc3($conn,"SELECT COUNT(*) AS c FROM attendance WHERE DATE(clock_in)=CURDATE() AND attendance_status='late'");

$conn->close();
$initials = strtoupper(substr($_SESSION['username'],0,2));

function fmtMins($m){ if(!$m) return '—'; $h=floor($m/60); $mn=$m%60; return $h>0?"{$h}h {$mn}m":"{$mn}m"; }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Timesheets — Farm TMS</title>
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
            <a href="clockinout.php" class="nav-link active"><i class="bi bi-clock"></i> Timesheets</a>
            <a href="exceptions.php" class="nav-link"><i class="bi bi-exclamation-circle"></i> Exceptions</a>
            <span class="nav-section-label">People</span>
            <a href="staff.php" class="nav-link"><i class="bi bi-people"></i> Staff</a>
            <a href="settings.php?tab=roles" class="nav-link"><i class="bi bi-person-badge"></i> Roles</a>
            <span class="nav-section-label">System</span>
            <a href="reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reports</a>
            <a href="payslips.php" class="nav-link"><i class="bi bi-receipt"></i> Payslips</a>
            <a href="settings.php" class="nav-link"><i class="bi bi-gear"></i> Settings</a>
        </nav>
        <div class="mt-auto p-3" style="border-top:1px solid rgba(255,255,255,0.15)">
            <a href="logout.php" class="nav-link" style="color:rgba(255,100,100,0.85)"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
        </div>
    </aside>

    <div class="main-content">
        <header class="topbar">
            <span class="page-title">Timesheets</span>
            <div class="topbar-right">
                <span class="topbar-date"><i class="bi bi-calendar3 me-1"></i><?= date('D, d M Y') ?></span>
                <div class="admin-badge"><div class="admin-avatar"><?= $initials ?></div><?= htmlspecialchars($_SESSION['username']) ?></div>
            </div>
        </header>

        <div class="page-body">
            <?php if ($msg): ?>
            <div class="toast-<?= $msgType ?>"><i class="bi bi-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>-fill"></i><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card"><div class="icon-box"><i class="bi bi-people-fill"></i></div><div><div class="stat-value"><?= $todayTotal ?></div><div class="stat-label">Today's Records</div></div></div>
                <div class="stat-card"><div class="icon-box"><i class="bi bi-person-check-fill"></i></div><div><div class="stat-value"><?= $todayClocked ?></div><div class="stat-label">Currently In</div></div></div>
                <div class="stat-card"><div class="icon-box"><i class="bi bi-box-arrow-right"></i></div><div><div class="stat-value"><?= $todayOut ?></div><div class="stat-label">Clocked Out</div></div></div>
                <div class="stat-card"><div class="icon-box"><i class="bi bi-clock-history"></i></div><div><div class="stat-value"><?= $todayLate ?></div><div class="stat-label">Late Today</div></div></div>
            </div>

            <!-- Quick Clock In -->
            <div class="card-box mb-4">
                <div class="card-body">
                    <p class="section-title mb-3"><i class="bi bi-clock me-1"></i> Quick Clock In</p>
                    <form method="POST" class="d-flex gap-3 flex-wrap">
                        <input type="hidden" name="action" value="clock_in">
                        <div class="filter-group flex-grow-1">
                            <label>Select Staff Member</label>
                            <select name="staff_id" class="filter-input" required>
                                <option value="">Select staff member...</option>
                                <?php foreach ($staffList as $s): ?>
                                <option value="<?= $s['staff_id'] ?>"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="align-self:flex-end;">
                            <button type="submit" class="btn-brand"><i class="bi bi-box-arrow-in-right me-1"></i> Clock In</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Filters + Table -->
            <div class="card-box">
                <div class="card-body">
                    <!-- Filters -->
                    <form method="GET" class="d-flex flex-wrap gap-3 align-items-end mb-4">
                        <div class="filter-group">
                            <label>Search</label>
                            <input type="text" name="search" class="filter-input" placeholder="Name or Staff #" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="filter-group">
                            <label>From Date</label>
                            <input type="date" name="date_from" class="filter-input" value="<?= $dateFrom ?>">
                        </div>
                        <div class="filter-group">
                            <label>To Date</label>
                            <input type="date" name="date_to" class="filter-input" value="<?= $dateTo ?>">
                        </div>
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status" class="filter-input" onchange="this.form.submit()">
                                <option value="all" <?= $statusF==='all'?'selected':''?>>All Status</option>
                                <option value="present" <?= $statusF==='present'?'selected':''?>>Present</option>
                                <option value="late" <?= $statusF==='late'?'selected':''?>>Late</option>
                                <option value="absent" <?= $statusF==='absent'?'selected':''?>>Absent</option>
                            </select>
                        </div>
                        <div style="align-self:flex-end;"><button type="submit" class="btn-brand">Search</button></div>
                    </form>

                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="section-title mb-0">Attendance Records</p>
                        <span style="font-size:0.82rem;color:var(--text-muted);"><?= count($attendance) ?> record(s)</span>
                    </div>

                    <?php if (empty($attendance)): ?>
                    <div class="text-center py-5" style="color:var(--text-muted)">
                        <i class="bi bi-clock-history" style="font-size:2.5rem;display:block;margin-bottom:10px;"></i>
                        No attendance records found.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr><th>Staff</th><th>Date</th><th>Shift</th><th>Clock In</th><th>Clock Out</th><th>Duration</th><th>Status</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance as $a):
                                    $st = $a['attendance_status']??'present';
                                    $sc = match($st) { 'late'=>'late', 'absent'=>'missing', 'incomplete'=>'missing', default=>'on-time' };
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="admin-avatar" style="width:28px;height:28px;font-size:0.7rem;"><?= htmlspecialchars($a['fi'].$a['li']) ?></div>
                                            <div><div style="font-weight:600;"><?= htmlspecialchars($a['staff_name']) ?></div><div style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($a['staff_number']) ?></div></div>
                                        </div>
                                    </td>
                                    <td style="font-size:0.85rem;"><?= date('D d M', strtotime($a['clock_in'])) ?></td>
                                    <td style="font-size:0.82rem;color:var(--text-muted);">
                                        <?= $a['shift_start']?date('g:i A',strtotime($a['shift_start'])).' – '.date('g:i A',strtotime($a['shift_end'])):'—' ?>
                                    </td>
                                    <td style="font-weight:600;color:var(--brand);"><?= date('g:i A', strtotime($a['clock_in'])) ?></td>
                                    <td><?= $a['clock_out'] ? date('g:i A', strtotime($a['clock_out'])) : '<span style="color:var(--text-muted);">—</span>' ?></td>
                                    <td style="font-size:0.85rem;"><?= fmtMins($a['mins_worked']) ?></td>
                                    <td><span class="badge-status badge-<?= $sc ?>"><?= ucfirst($st) ?></span></td>
                                    <td>
                                        <?php if (!$a['clock_out']): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="clock_out">
                                            <input type="hidden" name="attendance_id" value="<?= $a['attendance_id'] ?>">
                                            <button type="submit" class="btn-brand" style="padding:0.3rem 0.8rem;font-size:0.78rem;"
                                                onclick="return confirm('Clock out now?')">
                                                <i class="bi bi-box-arrow-right"></i> Clock Out
                                            </button>
                                        </form>
                                        <?php else: ?>
                                        <span style="font-size:0.82rem;color:var(--text-muted);">Complete</span>
                                        <?php endif; ?>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
<?php if ($msg): ?>setTimeout(()=>{ const t=document.querySelector('.toast-success,.toast-error'); if(t){t.style.opacity='0';setTimeout(()=>t.remove(),300);} },4000);<?php endif; ?>
</script>
</body>
</html>
