<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$conn = getConnection();

function safeCount($conn, $sql) {
    $r = $conn->query($sql);
    return ($r && $r->num_rows > 0) ? intval($r->fetch_assoc()['c']) : 0;
}

$totalStaff     = safeCount($conn, "SELECT COUNT(*) AS c FROM staff WHERE LOWER(status)='active'");
$rosteredToday  = safeCount($conn, "SELECT COUNT(*) AS c FROM roster WHERE work_date=CURDATE()");
$clockedIn      = safeCount($conn, "SELECT COUNT(*) AS c FROM attendance WHERE DATE(clock_in)=CURDATE() AND clock_out IS NULL");
$openExceptions = safeCount($conn, "SELECT COUNT(*) AS c FROM exceptions WHERE status='open'");

$attRes = $conn->query("
    SELECT s.staff_id, CONCAT(s.first_name,' ',s.last_name) AS staff_name,
           LEFT(s.first_name,1) AS fi, LEFT(s.last_name,1) AS li,
           ro.start_time, ro.end_time,
           a.attendance_id, a.clock_in, a.clock_out, a.attendance_status
    FROM roster ro
    JOIN staff s ON ro.staff_id=s.staff_id
    LEFT JOIN attendance a ON a.staff_id=s.staff_id AND DATE(a.clock_in)=CURDATE()
    WHERE ro.work_date=CURDATE()
    ORDER BY s.first_name
    LIMIT 5
");
$todayAtt = $attRes ? $attRes->fetch_all(MYSQLI_ASSOC) : [];
$totalRostered = safeCount($conn, "SELECT COUNT(*) AS c FROM roster WHERE work_date=CURDATE()");
$conn->close();

$level    = strtolower($_SESSION['permission_level'] ?? '');
$initials = strtoupper(substr($_SESSION['username'],0,2));

function getStatusBadge($row) {
    if (!$row['attendance_id']) return ['Missing','missing'];
    if ($row['clock_out'])      return ['Clocked Out','on-time'];
    if ($row['attendance_status']==='late') return ['Late','late'];
    return ['On Time','on-time'];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Dashboard — Farm TMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="adminStyle.css"/>
</head>
<body>
<div class="dashboard-wrapper">

    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="brand"><i class="bi bi-clock-history me-2"></i>Farm TMS</div>
        <nav class="nav flex-column">
            <span class="nav-section-label">Main</span>
            <a href="dashboard.php" class="nav-link active"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="roster.php" class="nav-link"><i class="bi bi-calendar3"></i> Roster</a>
            <a href="clockinout.php" class="nav-link"><i class="bi bi-clock"></i> Timesheets</a>
            <a href="exceptions.php" class="nav-link"><i class="bi bi-exclamation-circle"></i> Exceptions</a>
            <span class="nav-section-label">People</span>
            <a href="staff.php" class="nav-link"><i class="bi bi-people"></i> Staff</a>
            <a href="settings.php?tab=roles" class="nav-link"><i class="bi bi-person-badge"></i> Roles</a>
            <span class="nav-section-label">System</span>
            <a href="reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reports</a>
            <a href="payslips.php" class="nav-link"><i class="bi bi-receipt"></i> Payslips</a>
            <a href="settings.php" class="nav-link"><i class="bi bi-gear"></i> Settings</a>
            <?php if ($level === 'superadmin'): ?>
            <a href="adminDashboard.php" class="nav-link"><i class="bi bi-shield-check"></i> Admin Mgmt</a>
            <?php endif; ?>
        </nav>
        <div class="mt-auto p-3" style="border-top:1px solid rgba(255,255,255,0.15)">
            <a href="logout.php" class="nav-link" style="color:rgba(255,100,100,0.85)">
                <i class="bi bi-box-arrow-right me-2"></i>Logout
            </a>
        </div>
    </aside>

    <!-- MAIN -->
    <div class="main-content">
        <header class="topbar">
            <span class="page-title">Dashboard</span>
            <div class="topbar-right">
                <span class="topbar-date"><i class="bi bi-calendar3 me-1"></i><?= date('D, d M Y') ?></span>
                <div class="admin-badge">
                    <div class="admin-avatar"><?= $initials ?></div>
                    <?= htmlspecialchars($_SESSION['username']) ?>
                </div>
            </div>
        </header>

        <div class="page-body">

            <!-- STATS -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="icon-box"><i class="bi bi-people-fill"></i></div>
                    <div>
                        <div class="stat-value"><?= $totalStaff ?></div>
                        <div class="stat-label">Total Staff</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon-box"><i class="bi bi-calendar-check"></i></div>
                    <div>
                        <div class="stat-value"><?= $rosteredToday ?></div>
                        <div class="stat-label">Rostered Today</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon-box"><i class="bi bi-person-check-fill"></i></div>
                    <div>
                        <div class="stat-value"><?= $clockedIn ?></div>
                        <div class="stat-label">Clocked In</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon-box"><i class="bi bi-exclamation-triangle-fill"></i></div>
                    <div>
                        <div class="stat-value"><?= $openExceptions ?></div>
                        <div class="stat-label">Open Exceptions</div>
                    </div>
                </div>
            </div>

            <!-- QUICK ACTIONS -->
            <div class="quick-actions mb-4">
                <a href="roster.php" class="btn-brand quick-btn">
                    <i class="bi bi-plus-circle me-1"></i> Add Shift
                </a>
                <a href="clockinout.php" class="btn-outline-brand quick-btn">
                    <i class="bi bi-clock-history me-1"></i> View Timesheets
                </a>
                <a href="reports.php" class="btn-outline-brand quick-btn">
                    <i class="bi bi-download me-1"></i> Export Report
                </a>
            </div>

            <!-- TODAY'S ATTENDANCE -->
            <div class="card-box">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <p class="section-title mb-0">Today's Attendance</p>
                        <span class="live-dot">LIVE</span>
                    </div>

                    <?php if (empty($todayAtt)): ?>
                    <div class="text-center py-5" style="color:var(--text-muted)">
                        <i class="bi bi-calendar-x" style="font-size:2.5rem;display:block;margin-bottom:10px;"></i>
                        No staff rostered for today yet.
                        <br><br>
                        <a href="roster.php" class="btn-brand" style="display:inline-block;padding:0.5rem 1.5rem;">+ Add Shifts</a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Staff Name</th>
                                    <th>Shift</th>
                                    <th>Status</th>
                                    <th>Clock In</th>
                                    <th>Clock Out</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todayAtt as $row):
                                    [$statusLabel, $statusClass] = getStatusBadge($row);
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="admin-avatar" style="width:30px;height:30px;font-size:0.7rem;">
                                                <?= htmlspecialchars($row['fi'].$row['li']) ?>
                                            </div>
                                            <?= htmlspecialchars($row['staff_name']) ?>
                                        </div>
                                    </td>
                                    <td><?= $row['start_time'] ? date('g:i A', strtotime($row['start_time'])).' – '.date('g:i A', strtotime($row['end_time'])) : '—' ?></td>
                                    <td><span class="badge-status badge-<?= $statusClass ?>"><?= $statusLabel ?></span></td>
                                    <td><?= $row['clock_in'] ? date('g:i A', strtotime($row['clock_in'])) : '—' ?></td>
                                    <td><?= $row['clock_out'] ? date('g:i A', strtotime($row['clock_out'])) : '—' ?></td>
                                    <td>
                                        <a href="clockinout.php" class="btn-outline-brand py-1 px-2" style="font-size:0.78rem;">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3 d-flex justify-content-between align-items-center">
                        <span style="font-size:0.82rem;color:var(--text-muted)">
                            Showing <?= count($todayAtt) ?> of <?= $totalRostered ?> rostered staff
                        </span>
                        <a href="clockinout.php" style="font-size:0.85rem;font-weight:600;">View all &rarr;</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>setTimeout(() => location.reload(), 60000);</script>
</body>
</html>
