<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit(); }

$conn = getConnection();
$selectedPeriod = intval($_GET['period_id']??0);
$filterRole     = intval($_GET['role_id']??0);
$reportType     = $_GET['report_type']??'hours_worked';

$periodsRes = $conn->query("SELECT * FROM pay_periods ORDER BY period_start_date DESC");
$periods    = $periodsRes ? $periodsRes->fetch_all(MYSQLI_ASSOC) : [];
if (!$selectedPeriod && !empty($periods)) $selectedPeriod = $periods[0]['pay_period_id'];

$currentPeriod = null;
if ($selectedPeriod) {
    $pr = $conn->query("SELECT * FROM pay_periods WHERE pay_period_id=$selectedPeriod");
    if ($pr) $currentPeriod = $pr->fetch_assoc();
}

$startDate = $currentPeriod['period_start_date'] ?? date('Y-m-01');
$endDate   = $currentPeriod['period_end_date']   ?? date('Y-m-d');
$roles     = $conn->query("SELECT role_id,role_name FROM roles ORDER BY role_name")->fetch_all(MYSQLI_ASSOC);
$rWhere    = $filterRole ? "AND s.role_id=$filterRole" : "";

$staffData = $conn->query("
    SELECT s.staff_id,s.staff_number,CONCAT(s.first_name,' ',s.last_name) AS staff_name,
           LEFT(s.first_name,1) AS fi,LEFT(s.last_name,1) AS li,r.role_name,
           (SELECT COUNT(*) FROM roster ro WHERE ro.staff_id=s.staff_id AND ro.work_date BETWEEN '$startDate' AND '$endDate') AS shift_count,
           (SELECT IFNULL(SUM(TIMESTAMPDIFF(MINUTE,ro.start_time,ro.end_time)),0)/60 FROM roster ro WHERE ro.staff_id=s.staff_id AND ro.work_date BETWEEN '$startDate' AND '$endDate') AS rostered_hrs,
           (SELECT IFNULL(SUM(TIMESTAMPDIFF(MINUTE,a.clock_in,IFNULL(a.clock_out,a.clock_in))),0)/60 FROM attendance a WHERE a.staff_id=s.staff_id AND DATE(a.clock_in) BETWEEN '$startDate' AND '$endDate' AND a.clock_out IS NOT NULL) AS actual_hrs,
           IFNULL(c.standard_weekly_hours,38) AS std_weekly_hrs
    FROM staff s LEFT JOIN roles r ON s.role_id=r.role_id LEFT JOIN contracts c ON s.contract_id=c.contract_id
    WHERE LOWER(s.status)='active' $rWhere ORDER BY s.first_name
")->fetch_all(MYSQLI_ASSOC);

$weeks=$currentPeriod?max(1,round((strtotime($endDate)-strtotime($startDate))/(7*86400))):2;
$totalHours=0; $totalOvertime=0;
foreach ($staffData as &$s) {
    $actual=round(floatval($s['actual_hrs']),1); $rostered=round(floatval($s['rostered_hrs']),1);
    $stdPeriod=round($s['std_weekly_hrs']*$weeks,1); $ot=max(0,round($actual-$stdPeriod,1));
    $s['actual_hrs']=$actual; $s['rostered_hrs']=$rostered; $s['overtime_hrs']=$ot; $s['variance']=round($actual-$rostered,1);
    $totalHours+=$actual; $totalOvertime+=$ot;
} unset($s);
$avgHours = count($staffData)>0 ? round($totalHours/count($staffData),1) : 0;
$conn->close();
$initials = strtoupper(substr($_SESSION['username'],0,2));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Reports — Farm TMS</title>
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
            <a href="clockinout.php" class="nav-link"><i class="bi bi-clock"></i> Timesheets</a>
            <a href="exceptions.php" class="nav-link"><i class="bi bi-exclamation-circle"></i> Exceptions</a>
            <span class="nav-section-label">People</span>
            <a href="staff.php" class="nav-link"><i class="bi bi-people"></i> Staff</a>
            <a href="settings.php?tab=roles" class="nav-link"><i class="bi bi-person-badge"></i> Roles</a>
            <span class="nav-section-label">System</span>
            <a href="reports.php" class="nav-link active"><i class="bi bi-bar-chart-line"></i> Reports</a>
            <a href="payslips.php" class="nav-link"><i class="bi bi-receipt"></i> Payslips</a>
            <a href="settings.php" class="nav-link"><i class="bi bi-gear"></i> Settings</a>
        </nav>
        <div class="mt-auto p-3" style="border-top:1px solid rgba(255,255,255,0.15)">
            <a href="logout.php" class="nav-link" style="color:rgba(255,100,100,0.85)"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
        </div>
    </aside>

    <div class="main-content">
        <header class="topbar">
            <span class="page-title">Reports</span>
            <div class="topbar-right">
                <span class="topbar-date"><i class="bi bi-calendar3 me-1"></i><?= date('D, d M Y') ?></span>
                <div class="admin-badge"><div class="admin-avatar"><?= $initials ?></div><?= htmlspecialchars($_SESSION['username']) ?></div>
            </div>
        </header>

        <div class="page-body">
            <!-- Filters -->
            <div class="card-box mb-4">
                <div class="card-body">
                    <form method="GET" class="d-flex flex-wrap gap-3 align-items-end">
                        <div class="filter-group">
                            <label>Report Type</label>
                            <select name="report_type" class="filter-input" onchange="this.form.submit()">
                                <option value="hours_worked" <?= $reportType==='hours_worked'?'selected':''?>>Hours Worked</option>
                                <option value="attendance" <?= $reportType==='attendance'?'selected':''?>>Attendance Summary</option>
                                <option value="overtime" <?= $reportType==='overtime'?'selected':''?>>Overtime Report</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Pay Period</label>
                            <select name="period_id" class="filter-input" onchange="this.form.submit()">
                                <option value="">— Select Period —</option>
                                <?php foreach ($periods as $p): ?>
                                <option value="<?= $p['pay_period_id'] ?>" <?= $selectedPeriod==$p['pay_period_id']?'selected':''?>>
                                    <?= htmlspecialchars($p['period_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Role</label>
                            <select name="role_id" class="filter-input" onchange="this.form.submit()">
                                <option value="">All Roles</option>
                                <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['role_id'] ?>" <?= $filterRole==$r['role_id']?'selected':''?>><?= htmlspecialchars($r['role_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="align-self:flex-end;">
                            <button type="button" class="btn-outline-brand" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card"><div class="icon-box"><i class="bi bi-people-fill"></i></div><div><div class="stat-value"><?= count($staffData) ?></div><div class="stat-label">Total Staff</div></div></div>
                <div class="stat-card"><div class="icon-box"><i class="bi bi-clock-history"></i></div><div><div class="stat-value"><?= number_format($totalHours,1) ?>h</div><div class="stat-label">Total Hours</div></div></div>
                <div class="stat-card"><div class="icon-box"><i class="bi bi-lightning-charge-fill"></i></div><div><div class="stat-value"><?= number_format($totalOvertime,1) ?>h</div><div class="stat-label">Overtime Hours</div></div></div>
                <div class="stat-card"><div class="icon-box"><i class="bi bi-graph-up"></i></div><div><div class="stat-value"><?= number_format($avgHours,1) ?>h</div><div class="stat-label">Avg Hours/Staff</div></div></div>
            </div>

            <!-- Table -->
            <div class="card-box">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="section-title mb-0">Hours Worked Report</p>
                        <?php if ($currentPeriod): ?>
                        <span style="font-size:0.82rem;color:var(--text-muted);">
                            Period: <?= date('d M',strtotime($startDate)) ?> – <?= date('d M Y',strtotime($endDate)) ?>
                        </span>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($staffData)): ?>
                    <div class="text-center py-5" style="color:var(--text-muted)">
                        <i class="bi bi-bar-chart" style="font-size:2.5rem;display:block;margin-bottom:10px;"></i>
                        Select a pay period to generate the report.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr><th>Staff</th><th>Role</th><th>Shifts</th><th>Rostered Hrs</th><th>Actual Hrs</th><th>Overtime</th><th>Variance</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staffData as $s): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="admin-avatar" style="width:30px;height:30px;font-size:0.7rem;"><?= htmlspecialchars($s['fi'].$s['li']) ?></div>
                                            <div>
                                                <div style="font-weight:600;"><?= htmlspecialchars($s['staff_name']) ?></div>
                                                <div style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($s['staff_number']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($s['role_name']??'—') ?></td>
                                    <td><?= intval($s['shift_count']) ?></td>
                                    <td><?= number_format($s['rostered_hrs'],0) ?>h</td>
                                    <td style="font-weight:700;"><?= number_format($s['actual_hrs'],1) ?>h</td>
                                    <td><?php if($s['overtime_hrs']>0): ?><span style="color:#d97706;font-weight:600;"><?= number_format($s['overtime_hrs'],1) ?>h</span><?php else: ?><span style="color:var(--text-muted);">0.0h</span><?php endif; ?></td>
                                    <td><?php $v=$s['variance']; if($v>0): ?><span style="color:#16a34a;font-weight:600;">+<?= number_format($v,1) ?>h</span><?php elseif($v<0): ?><span style="color:#dc3545;font-weight:600;"><?= number_format($v,1) ?>h</span><?php else: ?><span style="color:var(--text-muted);">+0.0h</span><?php endif; ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr style="background:var(--bg-surface);font-weight:700;">
                                    <td colspan="4" style="color:var(--text-muted);">TOTALS</td>
                                    <td><?= number_format($totalHours,1) ?>h</td>
                                    <td><?php if($totalOvertime>0): ?><span style="color:#d97706;"><?= number_format($totalOvertime,1) ?>h</span><?php else: ?>0.0h<?php endif; ?></td>
                                    <td>—</td>
                                </tr>
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
</body>
</html>
