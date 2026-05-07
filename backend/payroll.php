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
$adminId = $_SESSION['admin_id'];

// ── CREATE PAY PERIOD ────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'create_period') {
    $period_name = trim($_POST['period_name']);
    $start_date  = $_POST['period_start_date'];
    $end_date    = $_POST['period_end_date'];
    $pay_date    = $_POST['pay_date'];

    $stmt = $conn->prepare("
        INSERT INTO pay_periods (period_name, period_start_date, period_end_date, pay_date, status, created_by)
        VALUES (?, ?, ?, ?, 'open', ?)
    ");
    $stmt->bind_param('ssssi', $period_name, $start_date, $end_date, $pay_date, $adminId);
    if ($stmt->execute()) {
        $newId = $conn->insert_id;
        $log = $conn->prepare("INSERT INTO audit_logs (admin_id, action_type, target_table, target_id, reason, source_channel) VALUES (?, 'CREATE', 'pay_periods', ?, 'Pay period created', 'web')");
        $log->bind_param('ii', $adminId, $newId);
        $log->execute();
        $log->close();
        $message = 'Pay period created successfully.';
        $msgType = 'success';
    }
    $stmt->close();
}

// ── GENERATE PAYSLIPS ────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'generate') {
    $pay_period_id = intval($_POST['pay_period_id']);
    $period = $conn->query("SELECT * FROM pay_periods WHERE pay_period_id = $pay_period_id")->fetch_assoc();

    if ($period) {
        $staff = $conn->query("
            SELECT s.*, c.standard_pay_rate, c.overtime_pay_rate, c.standard_weekly_hours, c.annual_leave_rate
            FROM staff s
            LEFT JOIN contracts c ON s.contract_id = c.contract_id
            WHERE s.status = 'active'
        ")->fetch_all(MYSQLI_ASSOC);

        $generated = 0;
        foreach ($staff as $s) {
            $exists = $conn->query("SELECT payslip_id FROM payslips WHERE staff_id={$s['staff_id']} AND pay_period_id=$pay_period_id")->num_rows;
            if ($exists) continue;

            $attResult = $conn->query("
                SELECT SUM(TIMESTAMPDIFF(MINUTE, clock_in, IFNULL(clock_out, NOW()))) / 60 AS total_hours
                FROM attendance
                WHERE staff_id = {$s['staff_id']}
                AND DATE(clock_in) BETWEEN '{$period['period_start_date']}' AND '{$period['period_end_date']}'
                AND clock_out IS NOT NULL
            ")->fetch_assoc();

            $totalHours    = round(floatval($attResult['total_hours'] ?? 0), 2);
            $stdWeeklyHrs  = floatval($s['standard_weekly_hours'] ?? 38);
            $weeks         = max(1, round((strtotime($period['period_end_date']) - strtotime($period['period_start_date'])) / (7 * 86400)));
            $stdPeriodHrs  = $stdWeeklyHrs * $weeks;
            $overtimeHours = max(0, $totalHours - $stdPeriodHrs);
            $regularHours  = $totalHours - $overtimeHours;
            $stdRate       = floatval($s['standard_pay_rate'] ?? 0);
            $otRate        = floatval($s['overtime_pay_rate'] ?? $stdRate * 1.5);
            $totalPay      = ($regularHours * $stdRate) + ($overtimeHours * $otRate);
            $annualised    = $totalPay * (52 / max(1, $weeks));
            $taxRate       = $annualised < 18201 ? 0 : ($annualised < 45001 ? 0.19 : ($annualised < 120001 ? 0.325 : 0.37));
            $taxAmount     = round($totalPay * $taxRate, 2);
            $superRate     = 11.00;
            $leaveRate     = floatval($s['annual_leave_rate'] ?? 0.0769);
            $leaveAccrued  = round($totalHours * $leaveRate, 4);
            $ytd           = $conn->query("SELECT IFNULL(SUM(total_pay),0) AS ytd FROM payslips WHERE staff_id={$s['staff_id']}")->fetch_assoc()['ytd'];
            $ytdGross      = floatval($ytd) + $totalPay;
            $netPay        = round($totalPay - $taxAmount, 2);
            $taxRatePct    = round($taxRate * 100, 2);
            $leaveBalance  = $leaveAccrued;

            $stmt = $conn->prepare("
                INSERT INTO payslips (staff_id, pay_period_id, period_start_date, period_end_date, pay_date,
                ytd_gross_pay, total_hours, total_pay, super_rate, tax_rate, tax_amount, night_pay,
                annual_leave_accrued, annual_leave_used, annual_leave_balance, net_pay, generated_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,0,?,0,?,?,?)
            ");
            $stmt->bind_param('iisssdddddddddi',
                $s['staff_id'], $pay_period_id,
                $period['period_start_date'], $period['period_end_date'], $period['pay_date'],
                $ytdGross, $totalHours, $totalPay, $superRate, $taxRatePct, $taxAmount,
                $leaveAccrued, $leaveBalance, $netPay, $adminId
            );
            if ($stmt->execute()) {
                $generated++;
            }
            $stmt->close();
        }

        $conn->query("UPDATE pay_periods SET status='processed' WHERE pay_period_id=$pay_period_id");
        $message = "$generated payslip(s) generated successfully.";
        $msgType = 'success';
    }
}

// Load data
$periods        = $conn->query("SELECT * FROM pay_periods ORDER BY period_start_date DESC")->fetch_all(MYSQLI_ASSOC);
$selectedPeriod = intval($_GET['period'] ?? 0);
$payslips       = [];

if ($selectedPeriod) {
    $payslips = $conn->query("
        SELECT p.*, CONCAT(s.first_name,' ',s.last_name) AS staff_name, s.staff_number
        FROM payslips p
        JOIN staff s ON p.staff_id = s.staff_id
        WHERE p.pay_period_id = $selectedPeriod
        ORDER BY s.first_name
    ")->fetch_all(MYSQLI_ASSOC);
}

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payroll — Farm Time</title>
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
        .form-label { font-weight: 600; font-size: 0.9rem; }
        .form-control:focus, .form-select:focus { border-color: #696c2b; box-shadow: 0 0 0 3px rgba(105,108,43,0.12); }
        .btn-farm { background: #696c2b; color: white; border: none; }
        .btn-farm:hover { background: #5b5e24; color: white; }
        .period-item { padding: 14px 16px; border-bottom: 1px solid #f5f5f5; display: flex; justify-content: space-between; align-items: center; cursor: pointer; text-decoration: none; color: inherit; }
        .period-item:hover { background: #fafafa; }
        .period-item.active { background: rgba(105,108,43,0.08); border-left: 3px solid #696c2b; }
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
                <a class="nav-link" href="staff.php"><i class="bi bi-people me-2"></i>Staff</a>
                <a class="nav-link" href="roster.php"><i class="bi bi-calendar-week me-2"></i>Rosters</a>
                <a class="nav-link" href="clockinout.php"><i class="bi bi-clock-history me-2"></i>Attendance</a>
                <a class="nav-link" href="devices.php"><i class="bi bi-hdd-network me-2"></i>Clock Stations</a>
                <a class="nav-link" href="exceptions.php"><i class="bi bi-exclamation-triangle me-2"></i>Exceptions</a>
                <a class="nav-link" href="reports.php"><i class="bi bi-file-earmark-bar-graph me-2"></i>Reports</a>
                <a class="nav-link active" href="payroll.php"><i class="bi bi-receipt me-2"></i>Payslips</a>
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
                    <h4 class="mb-0">Payroll Management</h4>
                    <small class="text-muted">Farm Time Management System</small>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="text-muted"><?= date('d M Y') ?></span>
                    <div class="fw-semibold"><?= htmlspecialchars($_SESSION['username']) ?></div>
                </div>
            </div>

            <div class="p-4">

                <?php if ($message): ?>
                <div class="alert alert-<?= $msgType === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show">
                    <?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row g-4 mb-4">

                    <!-- Create Pay Period -->
                    <div class="col-lg-3">
                        <div class="card card-box p-4 mb-4">
                            <h5 class="mb-3"><i class="bi bi-plus-circle me-2"></i>New Pay Period</h5>
                            <form method="POST">
                                <input type="hidden" name="action" value="create_period">
                                <div class="mb-3">
                                    <label class="form-label">Period Name *</label>
                                    <input type="text" class="form-control" name="period_name" placeholder="e.g. May 2026 - Week 1" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Start Date *</label>
                                    <input type="date" class="form-control" name="period_start_date" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">End Date *</label>
                                    <input type="date" class="form-control" name="period_end_date" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Pay Date *</label>
                                    <input type="date" class="form-control" name="pay_date" required>
                                </div>
                                <button type="submit" class="btn btn-farm w-100">
                                    <i class="bi bi-plus me-1"></i> Create Period
                                </button>
                            </form>
                        </div>

                        <!-- Generate Payslips -->
                        <div class="card card-box p-4">
                            <h5 class="mb-3"><i class="bi bi-gear me-2"></i>Generate Payslips</h5>
                            <form method="POST">
                                <input type="hidden" name="action" value="generate">
                                <div class="mb-3">
                                    <label class="form-label">Select Pay Period *</label>
                                    <select class="form-select" name="pay_period_id" required>
                                        <option value="">— Select Period —</option>
                                        <?php foreach ($periods as $p): ?>
                                            <?php if ($p['status'] !== 'processed'): ?>
                                            <option value="<?= $p['pay_period_id'] ?>">
                                                <?= htmlspecialchars($p['period_name']) ?>
                                            </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-success w-100"
                                    onclick="return confirm('Generate payslips for all active staff?')">
                                    <i class="bi bi-play-fill me-1"></i> Generate Payslips
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Pay Periods List -->
                    <div class="col-lg-9">
                        <div class="card card-box">
                            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-calendar3 me-2"></i>Pay Periods</h5>
                                <span class="badge" style="background:#696c2b;"><?= count($periods) ?> periods</span>
                            </div>

                            <?php if (empty($periods)): ?>
                                <div class="text-center text-muted py-5">No pay periods created yet.</div>
                            <?php else: ?>
                                <?php foreach ($periods as $p): ?>
                                <a href="payroll.php?period=<?= $p['pay_period_id'] ?>"
                                   class="period-item d-flex <?= $selectedPeriod == $p['pay_period_id'] ? 'active' : '' ?>">
                                    <div>
                                        <strong><?= htmlspecialchars($p['period_name']) ?></strong><br>
                                        <small class="text-muted">
                                            <?= date('d M Y', strtotime($p['period_start_date'])) ?> —
                                            <?= date('d M Y', strtotime($p['period_end_date'])) ?>
                                            &nbsp;•&nbsp; Pay date: <?= date('d M Y', strtotime($p['pay_date'])) ?>
                                        </small>
                                    </div>
                                    <?php
                                    $bc = match($p['status']) {
                                        'open'      => 'text-bg-primary',
                                        'processed' => 'text-bg-success',
                                        'closed'    => 'text-bg-secondary',
                                        default     => 'text-bg-light'
                                    };
                                    ?>
                                    <span class="badge <?= $bc ?> ms-auto"><?= ucfirst($p['status']) ?></span>
                                </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Payslips Table -->
                <?php if ($selectedPeriod && !empty($payslips)): ?>
                <div class="card card-box">
                    <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Payslips</h5>
                        <span class="badge" style="background:#696c2b;"><?= count($payslips) ?> payslips</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Staff</th>
                                    <th>Hours</th>
                                    <th>Gross Pay</th>
                                    <th>Tax</th>
                                    <th>Super (11%)</th>
                                    <th>Leave Accrued</th>
                                    <th>Net Pay</th>
                                    <th>YTD Gross</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $totalGross = 0;
                                $totalNet   = 0;
                                $totalTax   = 0;
                                foreach ($payslips as $p):
                                    $totalGross += $p['total_pay'];
                                    $totalNet   += $p['net_pay'];
                                    $totalTax   += $p['tax_amount'];
                                    $super       = round($p['total_pay'] * 0.11, 2);
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($p['staff_name']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($p['staff_number']) ?></small>
                                    </td>
                                    <td><?= number_format($p['total_hours'], 2) ?>h</td>
                                    <td class="text-success fw-semibold">$<?= number_format($p['total_pay'], 2) ?></td>
                                    <td class="text-danger">$<?= number_format($p['tax_amount'], 2) ?></td>
                                    <td class="text-primary">$<?= number_format($super, 2) ?></td>
                                    <td><?= number_format($p['annual_leave_accrued'], 2) ?>h</td>
                                    <td class="fw-bold">$<?= number_format($p['net_pay'], 2) ?></td>
                                    <td class="text-muted">$<?= number_format($p['ytd_gross_pay'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="table-light fw-bold">
                                    <td colspan="2">TOTALS</td>
                                    <td class="text-success">$<?= number_format($totalGross, 2) ?></td>
                                    <td class="text-danger">$<?= number_format($totalTax, 2) ?></td>
                                    <td></td><td></td>
                                    <td>$<?= number_format($totalNet, 2) ?></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php elseif ($selectedPeriod): ?>
                <div class="card card-box p-5 text-center text-muted">
                    No payslips generated for this period yet. Click "Generate Payslips" above.
                </div>
                <?php endif; ?>

            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>