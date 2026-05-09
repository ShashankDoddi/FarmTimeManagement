<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$conn = getConnection();

// ── Real data from database ──────────────────────────────────
$totalStaff    = $conn->query("SELECT COUNT(*) AS c FROM staff WHERE status='active'")->fetch_assoc()['c'] ?? 0;
$rosteredToday = $conn->query("SELECT COUNT(*) AS c FROM roster WHERE work_date=CURDATE()")->fetch_assoc()['c'] ?? 0;
$clockedIn     = $conn->query("SELECT COUNT(*) AS c FROM attendance WHERE DATE(clock_in)=CURDATE() AND clock_out IS NULL")->fetch_assoc()['c'] ?? 0;
$exceptions    = $conn->query("SELECT COUNT(*) AS c FROM exceptions")->fetch_assoc()['c'] ?? 0;

// Today's attendance
$todayAtt = $conn->query("
    SELECT
        CONCAT(s.first_name,' ',s.last_name) AS staff_name,
        CONCAT(DATE_FORMAT(r.start_time,'%h:%i %p'),' - ',DATE_FORMAT(r.end_time,'%h:%i %p')) AS shift,
        a.attendance_status,
        DATE_FORMAT(a.clock_in,'%h:%i %p') AS clock_in_time
    FROM attendance a
    JOIN staff s ON a.staff_id = s.staff_id
    LEFT JOIN roster r ON a.roster_id = r.roster_id
    WHERE DATE(a.clock_in) = CURDATE()
    ORDER BY a.clock_in DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Farm Time Admin Dashboard</title>
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
        .quick-btn { min-width: 150px; }
        .table td, .table th { vertical-align: middle; }
        @media (max-width: 991.98px) { .sidebar { min-height: auto; } }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <aside class="col-lg-2 col-md-3 sidebar p-3">
            <div class="brand">Farm Time Admin</div>
            <nav class="nav flex-column mt-4">
                <a class="nav-link active" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                <a class="nav-link" href="staff.php"><i class="bi bi-people me-2"></i>Staff</a>
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
                    <h4 class="mb-0">Admin Dashboard</h4>
                    <small class="text-muted">Farm Time Management System</small>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted"><?= date('d M Y') ?></span>
                    <div class="fw-semibold"><?= htmlspecialchars($_SESSION['username']) ?></div>
                </div>
            </div>

            <div class="p-4">

                <!-- Stats Cards -->
                <div class="row g-4 mb-4">
                    <div class="col-md-6 col-xl-3">
                        <div class="card card-box p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="section-title">Total Staff</div>
                                    <h3 class="mb-0"><?= $totalStaff ?></h3>
                                </div>
                                <div class="icon-box"><i class="bi bi-people"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card card-box p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="section-title">Rostered Today</div>
                                    <h3 class="mb-0"><?= $rosteredToday ?></h3>
                                </div>
                                <div class="icon-box"><i class="bi bi-calendar-check"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card card-box p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="section-title">Clocked In</div>
                                    <h3 class="mb-0"><?= $clockedIn ?></h3>
                                </div>
                                <div class="icon-box"><i class="bi bi-clock"></i></div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-3">
                        <div class="card card-box p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="section-title">Open Exceptions</div>
                                    <h3 class="mb-0 text-danger"><?= $exceptions ?></h3>
                                </div>
                                <div class="icon-box"><i class="bi bi-exclamation-circle"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bottom Row -->
                <div class="row g-4">

                    <!-- Attendance Table -->
                    <div class="col-lg-8">
                        <div class="card card-box p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Today's Attendance</h5>
                                <span class="badge text-bg-success">Live</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Staff Name</th>
                                            <th>Shift</th>
                                            <th>Status</th>
                                            <th>Clock In</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($todayAtt)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted py-4">
                                                    No attendance recorded today yet.
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($todayAtt as $a): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($a['staff_name']) ?></td>
                                                <td><?= htmlspecialchars($a['shift'] ?? '—') ?></td>
                                                <td>
                                                    <?php
                                                    $badgeClass = match($a['attendance_status']) {
                                                        'present' => 'text-bg-success',
                                                        'late'    => 'text-bg-warning',
                                                        'absent'  => 'text-bg-danger',
                                                        'partial' => 'text-bg-info',
                                                        default   => 'text-bg-secondary'
                                                    };
                                                    $label = match($a['attendance_status']) {
                                                        'present' => 'On Time',
                                                        'late'    => 'Late',
                                                        'absent'  => 'Missing',
                                                        'partial' => 'Partial',
                                                        default   => ucfirst($a['attendance_status'])
                                                    };
                                                    ?>
                                                    <span class="badge <?= $badgeClass ?>"><?= $label ?></span>
                                                </td>
                                                <td><?= htmlspecialchars($a['clock_in_time']) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="col-lg-4">
                        <div class="card card-box p-4 mb-4">
                            <h5 class="mb-3">Quick Actions</h5>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="staff.php" class="btn btn-success quick-btn">Add Staff</a>
                                <a href="roster.php" class="btn btn-outline-success quick-btn">Create Roster</a>
                                <a href="clockinout.php" class="btn btn-outline-success quick-btn">Manual Clock</a>
                                <a href="payroll.php" class="btn btn-outline-success quick-btn">Generate Report</a>
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