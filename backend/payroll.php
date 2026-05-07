<?php
// payroll/index.php
require_once '../includes/auth.php';
require_once '../config/database.php';
requireLogin();

$conn    = getConnection();
$message = '';
$msgType = '';

// ── CREATE PAY PERIOD ────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'create_period') {
    $period_name  = trim($_POST['period_name']);
    $start_date   = $_POST['period_start_date'];
    $end_date     = $_POST['period_end_date'];
    $pay_date     = $_POST['pay_date'];
    $adminId      = currentAdmin();

    $stmt = $conn->prepare("INSERT INTO pay_periods (period_name,period_start_date,period_end_date,pay_date,status,created_by) VALUES (?,?,?,?,'open',?)");
    $stmt->bind_param('ssssi', $period_name, $start_date, $end_date, $pay_date, $adminId);
    if ($stmt->execute()) {
        auditLog($conn, 'CREATE', 'pay_periods', $conn->insert_id, 'Pay period created');
        $message = 'Pay period created.'; $msgType = 'success';
    }
    $stmt->close();
}

// ── GENERATE PAYSLIPS ────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'generate') {
    $pay_period_id = intval($_POST['pay_period_id']);
    $adminId       = currentAdmin();

    $period = $conn->query("SELECT * FROM pay_periods WHERE pay_period_id = $pay_period_id")->fetch_assoc();

    if ($period) {
        $staff = $conn->query("SELECT s.*, c.standard_pay_rate, c.overtime_pay_rate, c.standard_weekly_hours, c.annual_leave_rate FROM staff s LEFT JOIN contracts c ON s.contract_id = c.contract_id WHERE s.status='active'")->fetch_all(MYSQLI_ASSOC);

        $generated = 0;
        foreach ($staff as $s) {
            // Skip if payslip already exists
            $exists = $conn->query("SELECT payslip_id FROM payslips WHERE staff_id={$s['staff_id']} AND pay_period_id=$pay_period_id")->num_rows;
            if ($exists) continue;

            // Calculate total hours worked in period
            $attResult = $conn->query("
                SELECT SUM(TIMESTAMPDIFF(MINUTE, clock_in, IFNULL(clock_out, NOW()))) / 60 AS total_hours
                FROM attendance
                WHERE staff_id = {$s['staff_id']}
                AND DATE(clock_in) BETWEEN '{$period['period_start_date']}' AND '{$period['period_end_date']}'
                AND clock_out IS NOT NULL
            ")->fetch_assoc();

            $totalHours   = round(floatval($attResult['total_hours'] ?? 0), 2);
            $stdWeeklyHrs = floatval($s['standard_weekly_hours'] ?? 38);
            $weeks        = max(1, round((strtotime($period['period_end_date']) - strtotime($period['period_start_date'])) / (7 * 86400)));
            $stdPeriodHrs = $stdWeeklyHrs * $weeks;

            $overtimeHours = max(0, $totalHours - $stdPeriodHrs);
            $regularHours  = $totalHours - $overtimeHours;

            $stdRate  = floatval($s['standard_pay_rate'] ?? 0);
            $otRate   = floatval($s['overtime_pay_rate'] ?? $stdRate * 1.5);
            $totalPay = ($regularHours * $stdRate) + ($overtimeHours * $otRate);

            // Tax (simple PAYG estimate — replace with real brackets)
            $annualised = $totalPay * (52 / max(1, $weeks));
            $taxRate    = $annualised < 18201 ? 0 : ($annualised < 45001 ? 0.19 : ($annualised < 120001 ? 0.325 : 0.37));
            $taxAmount  = round($totalPay * $taxRate, 2);

            // Super (11% SG rate)
            $superRate = 11.00;

            // Leave accrual
            $leaveRate    = floatval($s['annual_leave_rate'] ?? 0.0769);
            $leaveAccrued = round($totalHours * $leaveRate, 4);

            // YTD gross
            $ytd = $conn->query("SELECT IFNULL(SUM(total_pay),0) AS ytd FROM payslips WHERE staff_id={$s['staff_id']}")->fetch_assoc()['ytd'];
            $ytdGross = floatval($ytd) + $totalPay;

            $netPay = round($totalPay - $taxAmount, 2);

            $stmt = $conn->prepare("
                INSERT INTO payslips (staff_id, pay_period_id, period_start_date, period_end_date, pay_date,
                ytd_gross_pay, total_hours, total_pay, super_rate, tax_rate, tax_amount, night_pay,
                annual_leave_accrued, annual_leave_used, annual_leave_balance, net_pay, generated_by)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,0,?,0,?,?,?)
            ");
            $taxRatePct    = round($taxRate * 100, 2);
            $leaveBalance  = $leaveAccrued; // simplified

            $stmt->bind_param('iisssdddddddddi',
                $s['staff_id'], $pay_period_id,
                $period['period_start_date'], $period['period_end_date'], $period['pay_date'],
                $ytdGross, $totalHours, $totalPay, $superRate, $taxRatePct, $taxAmount,
                $leaveAccrued, $leaveBalance, $netPay, $adminId
            );
            if ($stmt->execute()) {
                auditLog($conn, 'CREATE', 'payslips', $conn->insert_id, "Payslip generated for staff {$s['staff_id']}");
                $generated++;
            }
            $stmt->close();
        }

        $conn->query("UPDATE pay_periods SET status='processed' WHERE pay_period_id=$pay_period_id");
        $message = "$generated payslip(s) generated successfully."; $msgType = 'success';
    }
}

// Load pay periods
$periods  = $conn->query("SELECT * FROM pay_periods ORDER BY period_start_date DESC")->fetch_all(MYSQLI_ASSOC);

// Load payslips for selected period
$selectedPeriod = intval($_GET['period'] ?? 0);
$payslips = [];
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payroll</title>
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
        .grid-2 { display:grid; grid-template-columns:380px 1fr; gap:20px; margin-bottom:24px; }
        .card { background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.06); overflow:hidden; margin-bottom:20px; }
        .card-header { padding:16px 22px; border-bottom:1px solid #f0f0f0; font-size:16px; font-weight:600; color:#1a1a2e; display:flex; align-items:center; }
        .card-body { padding:22px; }
        .message { padding:12px 16px; border-radius:8px; margin-bottom:18px; font-size:14px; }
        .message.success { background:#f0fdf4; border:1px solid #bbf7d0; color:#16a34a; }
        .form-group { margin-bottom:14px; }
        label { display:block; font-size:13px; font-weight:600; color:#555; margin-bottom:5px; }
        input, select { width:100%; padding:10px 14px; border:1.5px solid #e0e0e0; border-radius:8px; font-size:14px; outline:none; }
        input:focus, select:focus { border-color:#4f46e5; }
        .btn { padding:10px 20px; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary { background:#4f46e5; color:#fff; }
        .btn-success { background:#16a34a; color:#fff; }
        .btn-sm { padding:6px 12px; font-size:12px; }
        table { width:100%; border-collapse:collapse; }
        th { background:#f8f9fa; padding:12px 16px; text-align:left; font-size:12px; font-weight:600; color:#888; text-transform:uppercase; }
        td { padding:12px 16px; font-size:14px; border-bottom:1px solid #f5f5f5; vertical-align:middle; }
        .badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:600; }
        .badge-open       { background:#eff6ff; color:#2563eb; }
        .badge-processed  { background:#f0fdf4; color:#16a34a; }
        .badge-closed     { background:#f5f5f5; color:#888; }
        .period-item { padding:14px 16px; border-bottom:1px solid #f5f5f5; display:flex; justify-content:space-between; align-items:center; cursor:pointer; }
        .period-item:hover { background:#fafafa; }
        .period-item.active { background:#eff6ff; border-left:3px solid #4f46e5; }
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
    <div class="page-title">💰 Payroll Management</div>

    <?php if ($message): ?>
        <div class="message <?= $msgType ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <div class="grid-2">
        <!-- Left: Create Period + Generate -->
        <div>
            <div class="card">
                <div class="card-header">➕ New Pay Period</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="create_period">
                        <div class="form-group">
                            <label>Period Name *</label>
                            <input type="text" name="period_name" placeholder="e.g. May 2026 - Week 1" required>
                        </div>
                        <div class="form-group">
                            <label>Start Date *</label>
                            <input type="date" name="period_start_date" required>
                        </div>
                        <div class="form-group">
                            <label>End Date *</label>
                            <input type="date" name="period_end_date" required>
                        </div>
                        <div class="form-group">
                            <label>Pay Date *</label>
                            <input type="date" name="pay_date" required>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width:100%;">➕ Create Period</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">⚙️ Generate Payslips</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="generate">
                        <div class="form-group">
                            <label>Select Pay Period *</label>
                            <select name="pay_period_id" required>
                                <option value="">— Select Period —</option>
                                <?php foreach ($periods as $p): ?>
                                    <?php if ($p['status'] !== 'processed'): ?>
                                    <option value="<?= $p['pay_period_id'] ?>"><?= htmlspecialchars($p['period_name']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-success" style="width:100%;" onclick="return confirm('Generate payslips for all active staff?')">
                            ⚙️ Generate Payslips
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Pay Periods List -->
        <div class="card">
            <div class="card-header">📅 Pay Periods</div>
            <?php foreach ($periods as $p): ?>
            <a href="?period=<?= $p['pay_period_id'] ?>" style="text-decoration:none;color:inherit;">
                <div class="period-item <?= $selectedPeriod == $p['pay_period_id'] ? 'active' : '' ?>">
                    <div>
                        <strong><?= htmlspecialchars($p['period_name']) ?></strong><br>
                        <span style="font-size:12px;color:#888;">
                            <?= date('d M', strtotime($p['period_start_date'])) ?> — <?= date('d M Y', strtotime($p['period_end_date'])) ?>
                            • Pay: <?= date('d M Y', strtotime($p['pay_date'])) ?>
                        </span>
                    </div>
                    <span class="badge badge-<?= $p['status'] ?>"><?= ucfirst($p['status']) ?></span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Payslips Table -->
    <?php if ($selectedPeriod && !empty($payslips)): ?>
    <div class="card">
        <div class="card-header">
            📄 Payslips
            <span style="margin-left:auto;background:#eff6ff;color:#2563eb;padding:3px 10px;border-radius:20px;font-size:13px;"><?= count($payslips) ?> payslips</span>
        </div>
        <table>
            <thead>
                <tr><th>Staff</th><th>Hours</th><th>Gross Pay</th><th>Tax</th><th>Super (11%)</th><th>Leave Accrued</th><th>Net Pay</th><th>YTD Gross</th></tr>
            </thead>
            <tbody>
                <?php
                $totalGross = 0; $totalNet = 0; $totalTax = 0;
                foreach ($payslips as $p):
                    $totalGross += $p['total_pay'];
                    $totalNet   += $p['net_pay'];
                    $totalTax   += $p['tax_amount'];
                    $super = round($p['total_pay'] * 0.11, 2);
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($p['staff_name']) ?></strong><br><span style="font-size:12px;color:#888;"><?= $p['staff_number'] ?></span></td>
                    <td><?= number_format($p['total_hours'], 2) ?>h</td>
                    <td style="color:#16a34a;font-weight:600;">$<?= number_format($p['total_pay'], 2) ?></td>
                    <td style="color:#dc2626;">$<?= number_format($p['tax_amount'], 2) ?></td>
                    <td style="color:#2563eb;">$<?= number_format($super, 2) ?></td>
                    <td><?= number_format($p['annual_leave_accrued'], 2) ?>h</td>
                    <td style="font-weight:700;font-size:15px;">$<?= number_format($p['net_pay'], 2) ?></td>
                    <td style="color:#888;">$<?= number_format($p['ytd_gross_pay'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr style="background:#f8f9fa;font-weight:700;">
                    <td colspan="2">TOTALS</td>
                    <td style="color:#16a34a;">$<?= number_format($totalGross, 2) ?></td>
                    <td style="color:#dc2626;">$<?= number_format($totalTax, 2) ?></td>
                    <td></td><td></td>
                    <td style="font-size:15px;">$<?= number_format($totalNet, 2) ?></td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>
    <?php elseif ($selectedPeriod): ?>
        <div class="card"><div style="text-align:center;padding:40px;color:#aaa;">No payslips generated for this period yet. Click "Generate Payslips" above.</div></div>
    <?php endif; ?>
</div>
</body>
</html>
