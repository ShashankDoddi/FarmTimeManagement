<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$conn    = getConnection();
$adminId = $_SESSION['admin_id'];

// ── GENERATE PAYSLIPS ────────────────────────────────────────
if (isset($_POST['action']) && $_POST['action'] === 'generate') {
    $pay_period_id = intval($_POST['pay_period_id']);
    $result        = $conn->query("SELECT * FROM pay_periods WHERE pay_period_id = $pay_period_id");
    $period        = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : null;

    if ($period) {
        $staffResult = $conn->query("
            SELECT s.*, c.standard_pay_rate, c.overtime_pay_rate,
                   c.standard_weekly_hours, c.annual_leave_rate
            FROM staff s
            LEFT JOIN contracts c ON s.contract_id = c.contract_id
            WHERE s.status = 'active'
        ");
        $staff     = $staffResult ? $staffResult->fetch_all(MYSQLI_ASSOC) : [];
        $generated = 0;

        foreach ($staff as $s) {
            $existsRes = $conn->query("SELECT payslip_id FROM payslips WHERE staff_id={$s['staff_id']} AND pay_period_id=$pay_period_id");
            if ($existsRes && $existsRes->num_rows > 0) continue;

            $attRes = $conn->query("
                SELECT SUM(TIMESTAMPDIFF(MINUTE, clock_in, IFNULL(clock_out, NOW()))) / 60 AS total_hours
                FROM attendance
                WHERE staff_id = {$s['staff_id']}
                AND DATE(clock_in) BETWEEN '{$period['period_start_date']}' AND '{$period['period_end_date']}'
                AND clock_out IS NOT NULL
            ");
            $att        = ($attRes) ? $attRes->fetch_assoc() : ['total_hours' => 0];
            $totalHours = round(floatval($att['total_hours'] ?? 0), 2);

            $stdRate       = floatval($s['standard_pay_rate'] ?? 0);
            $otRate        = floatval($s['overtime_pay_rate'] ?? $stdRate * 1.5);
            $stdWeekly     = floatval($s['standard_weekly_hours'] ?? 38);
            $weeks         = max(1, round((strtotime($period['period_end_date']) - strtotime($period['period_start_date'])) / (7 * 86400)));
            $stdPeriodHrs  = $stdWeekly * $weeks;
            $overtimeHours = max(0, $totalHours - $stdPeriodHrs);
            $regularHours  = $totalHours - $overtimeHours;
            $totalPay      = ($regularHours * $stdRate) + ($overtimeHours * $otRate);
            $annualised    = $totalPay * (52 / max(1, $weeks));
            $taxRate       = $annualised < 18201 ? 0 : ($annualised < 45001 ? 0.19 : ($annualised < 120001 ? 0.325 : 0.37));
            $taxAmount     = round($totalPay * $taxRate, 2);
            $superRate     = 11.00;
            $leaveRate     = floatval($s['annual_leave_rate'] ?? 0.0769);
            $leaveAccrued  = round($totalHours * $leaveRate, 4);

            $ytdRes   = $conn->query("SELECT IFNULL(SUM(total_pay),0) AS ytd FROM payslips WHERE staff_id={$s['staff_id']}");
            $ytd      = $ytdRes ? floatval($ytdRes->fetch_assoc()['ytd']) : 0;
            $ytdGross = $ytd + $totalPay;
            $netPay   = round($totalPay - $taxAmount, 2);
            $taxRatePct = round($taxRate * 100, 2);

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
                $leaveAccrued, $leaveAccrued, $netPay, $adminId
            );
            if ($stmt->execute()) $generated++;
            $stmt->close();
        }

        $conn->query("UPDATE pay_periods SET status='processed' WHERE pay_period_id=$pay_period_id");
        header("Location: payslips.php?pay_period_id=$pay_period_id&generated=$generated");
        exit();
    }
}

// ── FILTERS ───────────────────────────────────────────────────
$selectedPeriod = intval($_GET['pay_period_id'] ?? 0);
$statusFilter   = $_GET['status'] ?? 'all';
$search         = trim($_GET['search'] ?? '');
$page           = max(1, intval($_GET['page'] ?? 1));
$perPage        = 10;

// Load pay periods safely
$periodsResult = $conn->query("SELECT * FROM pay_periods ORDER BY period_start_date DESC");
$periods       = $periodsResult ? $periodsResult->fetch_all(MYSQLI_ASSOC) : [];

// Auto-select latest period
if (!$selectedPeriod && !empty($periods)) {
    $selectedPeriod = $periods[0]['pay_period_id'];
}

// Build where clause
$where = $selectedPeriod ? "WHERE p.pay_period_id = $selectedPeriod" : "WHERE 1=0";
if ($statusFilter === 'pending') $where .= " AND p.status = 'draft'";
if ($statusFilter === 'paid')    $where .= " AND p.status = 'paid'";
if ($search) {
    $s      = $conn->real_escape_string($search);
    $where .= " AND (s.first_name LIKE '%$s%' OR s.last_name LIKE '%$s%' OR s.staff_number LIKE '%$s%')";
}

// Count tabs safely
function safeCount($conn, $sql) {
    $r = $conn->query($sql);
    return ($r && $r->num_rows > 0) ? intval($r->fetch_assoc()['c']) : 0;
}

$countAll     = $selectedPeriod ? safeCount($conn, "SELECT COUNT(*) AS c FROM payslips p JOIN staff s ON p.staff_id=s.staff_id WHERE p.pay_period_id=$selectedPeriod") : 0;
$countPending = $selectedPeriod ? safeCount($conn, "SELECT COUNT(*) AS c FROM payslips p JOIN staff s ON p.staff_id=s.staff_id WHERE p.pay_period_id=$selectedPeriod AND p.status='draft'") : 0;
$countPaid    = $selectedPeriod ? safeCount($conn, "SELECT COUNT(*) AS c FROM payslips p JOIN staff s ON p.staff_id=s.staff_id WHERE p.pay_period_id=$selectedPeriod AND p.status='paid'") : 0;

// Paginate safely
$totalRecords = safeCount($conn, "SELECT COUNT(*) AS c FROM payslips p JOIN staff s ON p.staff_id=s.staff_id $where");
$totalPages   = max(1, ceil($totalRecords / $perPage));
$offset       = ($page - 1) * $perPage;

// Load payslips safely
$payslips = [];
if ($selectedPeriod) {
    $payResult = $conn->query("
        SELECT p.*,
               CONCAT(s.first_name,' ',s.last_name) AS staff_name,
               s.staff_number,
               LEFT(s.first_name,1) AS fi,
               LEFT(s.last_name,1)  AS li,
               r.role_name,
               pp.period_name
        FROM payslips p
        JOIN staff s      ON p.staff_id      = s.staff_id
        LEFT JOIN roles r ON s.role_id       = r.role_id
        LEFT JOIN pay_periods pp ON p.pay_period_id = pp.pay_period_id
        $where
        ORDER BY s.first_name
        LIMIT $perPage OFFSET $offset
    ");
    $payslips = $payResult ? $payResult->fetch_all(MYSQLI_ASSOC) : [];
}

// Load current period safely
$currentPeriod = null;
if ($selectedPeriod) {
    $cpResult = $conn->query("SELECT * FROM pay_periods WHERE pay_period_id=$selectedPeriod");
    if ($cpResult && $cpResult->num_rows > 0) {
        $currentPeriod = $cpResult->fetch_assoc();
    }
}

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Payslips — Farm TMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #f5f5f0; display: flex; min-height: 100vh; }

        /* Sidebar */
        .sidebar { width: 200px; background: #4a4e1f; color: #fff; min-height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; }
        .sidebar-brand { padding: 18px 20px; font-size: 15px; font-weight: 700; color: #fff; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-section { padding: 14px 16px 4px; font-size: 10px; font-weight: 700; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 1px; }
        .sidebar a { display: flex; align-items: center; gap: 10px; padding: 9px 16px; color: rgba(255,255,255,0.75); text-decoration: none; font-size: 13.5px; border-left: 3px solid transparent; transition: all 0.15s; }
        .sidebar a:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .sidebar a.active { background: rgba(255,255,255,0.12); color: #fff; border-left-color: #c8cc6e; }
        .sidebar-footer { margin-top: auto; padding: 16px; border-top: 1px solid rgba(255,255,255,0.1); }
        .sidebar-footer a { color: rgba(255,100,100,0.85); font-size: 13px; }

        /* Main */
        .main { margin-left: 200px; flex: 1; display: flex; flex-direction: column; }

        /* Topbar */
        .topbar { background: #fff; border-bottom: 1px solid #e8e8e0; padding: 12px 28px; display: flex; align-items: center; justify-content: space-between; }
        .topbar-title { font-size: 18px; font-weight: 700; color: #2c2c1a; }
        .topbar-right { display: flex; align-items: center; gap: 16px; font-size: 13px; color: #888; }
        .avatar-sm { width: 30px; height: 30px; border-radius: 50%; background: #4a4e1f; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; }

        /* Content */
        .content { padding: 24px 28px; flex: 1; }

        /* Filter Bar */
        .filter-bar { background: #fff; border: 1px solid #e8e8e0; border-radius: 10px; padding: 16px 20px; display: flex; align-items: flex-end; gap: 16px; margin-bottom: 16px; flex-wrap: wrap; }
        .filter-group { display: flex; flex-direction: column; gap: 4px; }
        .filter-label { font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 0.5px; }
        .filter-bar select, .filter-bar input { border: 1px solid #ddd; border-radius: 7px; padding: 8px 12px; font-size: 13.5px; outline: none; transition: border-color 0.2s; }
        .filter-bar select:focus, .filter-bar input:focus { border-color: #696c2b; }
        .filter-bar select { min-width: 200px; }
        .filter-bar input  { min-width: 220px; }
        .btn-generate { background: #696c2b; color: #fff; border: none; padding: 9px 18px; border-radius: 8px; font-size: 13.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: background 0.2s; white-space: nowrap; }
        .btn-generate:hover { background: #5b5e24; }
        .btn-generate:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-export { background: #fff; color: #555; border: 1px solid #ddd; padding: 9px 16px; border-radius: 8px; font-size: 13.5px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: all 0.2s; }
        .btn-export:hover { border-color: #696c2b; color: #696c2b; }
        .filter-actions { margin-left: auto; display: flex; gap: 8px; align-items: center; }

        /* Tabs */
        .tabs { display: flex; border-bottom: 2px solid #e8e8e0; margin-bottom: 20px; }
        .tabs a { text-decoration: none; }
        .tab-btn { padding: 10px 18px; font-size: 13.5px; font-weight: 600; color: #888; background: none; border: none; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.15s; display: flex; align-items: center; gap: 6px; }
        .tab-btn:hover { color: #696c2b; }
        .tab-btn.active { color: #696c2b; border-bottom-color: #696c2b; }
        .tab-count { background: #e8e8e0; color: #555; padding: 2px 7px; border-radius: 20px; font-size: 11px; }
        .tab-btn.active .tab-count { background: rgba(105,108,43,0.15); color: #696c2b; }

        /* Table */
        .table-card { background: #fff; border: 1px solid #e8e8e0; border-radius: 10px; overflow: hidden; }
        .table-header { padding: 14px 20px; border-bottom: 1px solid #e8e8e0; display: flex; align-items: center; justify-content: space-between; }
        .table-header h6 { font-size: 13px; font-weight: 700; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        thead th { padding: 11px 16px; font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #e8e8e0; background: #fafaf7; white-space: nowrap; }
        tbody td { padding: 13px 16px; font-size: 13.5px; color: #333; border-bottom: 1px solid #f0f0e8; vertical-align: middle; }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #fafaf7; }

        .staff-avatar { width: 32px; height: 32px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #fff; flex-shrink: 0; }
        .net-pay { font-weight: 700; color: #2c2c1a; }

        .badge-pending { background: #fef3c7; color: #92400e; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-paid    { background: #d1fae5; color: #065f46; padding: 3px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }

        .action-btn { background: none; border: none; cursor: pointer; color: #aaa; font-size: 16px; padding: 4px 6px; border-radius: 6px; transition: all 0.15s; }
        .action-btn:hover { background: #f0f0e8; color: #696c2b; }

        /* Pagination */
        .pagination-bar { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; border-top: 1px solid #e8e8e0; font-size: 13px; color: #888; }
        .pagination-btns { display: flex; gap: 6px; }
        .page-btn { padding: 6px 14px; border: 1px solid #ddd; border-radius: 7px; background: #fff; font-size: 13px; cursor: pointer; transition: all 0.15s; color: #555; text-decoration: none; display: inline-block; }
        .page-btn:hover { border-color: #696c2b; color: #696c2b; }
        .page-btn.disabled { opacity: 0.4; pointer-events: none; }
        .page-btn.current { background: #696c2b; color: #fff; border-color: #696c2b; }

        .empty-state { text-align: center; padding: 60px 20px; color: #aaa; }
        .empty-state i { font-size: 3rem; margin-bottom: 12px; display: block; }

        .toast-msg { background: #d1fae5; border: 1px solid #6ee7b7; color: #065f46; padding: 12px 20px; border-radius: 8px; margin-bottom: 16px; font-size: 13.5px; display: flex; align-items: center; gap: 8px; }
        .no-periods-msg { background: #fef3c7; border: 1px solid #fde68a; color: #92400e; padding: 14px 20px; border-radius: 8px; margin-bottom: 16px; font-size: 13.5px; }

        .av-0 { background: #696c2b; } .av-1 { background: #0369a1; }
        .av-2 { background: #7c3aed; } .av-3 { background: #dc2626; }
        .av-4 { background: #d97706; } .av-5 { background: #0891b2; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-brand">🌾 Farm TMS</div>
    <div class="sidebar-section">Main</div>
    <a href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
    <a href="clockinout.php"><i class="bi bi-clock"></i> Timesheets</a>
    <a href="exceptions.php"><i class="bi bi-exclamation-circle"></i> Exceptions</a>
    <div class="sidebar-section">People</div>
    <a href="staff.php"><i class="bi bi-people"></i> Staff</a>
    <a href="settings.php?tab=roles"><i class="bi bi-briefcase"></i> Roles</a>
    <div class="sidebar-section">System</div>
    <a href="reports.php"><i class="bi bi-bar-chart"></i> Reports</a>
    <a href="payslips.php" class="active"><i class="bi bi-receipt"></i> Payslips</a>
    <a href="settings.php"><i class="bi bi-gear"></i> Settings</a>
    <div class="sidebar-footer">
        <a href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
    </div>
</div>

<!-- Main -->
<div class="main">
    <div class="topbar">
        <div class="topbar-title">Payslips</div>
        <div class="topbar-right">
            <i class="bi bi-calendar3"></i> <?= date('D, d M Y') ?>
            <span class="avatar-sm"><?= strtoupper(substr($_SESSION['username'],0,2)) ?></span>
            <?= htmlspecialchars($_SESSION['username']) ?>
        </div>
    </div>

    <div class="content">

        <?php if (isset($_GET['generated'])): ?>
        <div class="toast-msg">
            <i class="bi bi-check-circle-fill"></i>
            <?= intval($_GET['generated']) ?> payslip(s) generated successfully!
        </div>
        <?php endif; ?>

        <?php if (empty($periods)): ?>
        <div class="no-periods-msg">
            ⚠️ No pay periods found. Please
            <a href="payroll.php" style="color:#92400e;font-weight:600;">create a pay period first</a>
            before generating payslips.
        </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-group">
                <div class="filter-label">Pay Period</div>
                <form method="GET">
                    <select name="pay_period_id" onchange="this.form.submit()">
                        <option value="">— Select Period —</option>
                        <?php foreach ($periods as $p): ?>
                            <option value="<?= $p['pay_period_id'] ?>"
                                <?= $selectedPeriod == $p['pay_period_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($p['period_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                </form>
            </div>

            <div class="filter-group">
                <div class="filter-label">Search Staff</div>
                <form method="GET">
                    <input type="hidden" name="pay_period_id" value="<?= $selectedPeriod ?>">
                    <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                    <input type="text" name="search" placeholder="Search by name..."
                        value="<?= htmlspecialchars($search) ?>"
                        onchange="this.form.submit()">
                </form>
            </div>

            <div class="filter-actions">
                <?php if ($selectedPeriod && $currentPeriod && $currentPeriod['status'] !== 'processed'): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="generate">
                    <input type="hidden" name="pay_period_id" value="<?= $selectedPeriod ?>">
                    <button type="submit" class="btn-generate"
                        onclick="return confirm('Generate payslips for all active staff?')">
                        <i class="bi bi-play-fill"></i> Generate Payslips
                    </button>
                </form>
                <?php else: ?>
                <button class="btn-generate" disabled>
                    <i class="bi bi-check-lg"></i>
                    <?= $selectedPeriod ? 'Already Generated' : 'Select a Period' ?>
                </button>
                <?php endif; ?>

                <button class="btn-export" onclick="window.print()">
                    <i class="bi bi-download"></i> Export All
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <a href="?pay_period_id=<?= $selectedPeriod ?>&status=all&search=<?= urlencode($search) ?>">
                <button class="tab-btn <?= $statusFilter === 'all' ? 'active' : '' ?>">
                    All <span class="tab-count"><?= $countAll ?></span>
                </button>
            </a>
            <a href="?pay_period_id=<?= $selectedPeriod ?>&status=pending&search=<?= urlencode($search) ?>">
                <button class="tab-btn <?= $statusFilter === 'pending' ? 'active' : '' ?>">
                    Pending <span class="tab-count"><?= $countPending ?></span>
                </button>
            </a>
            <a href="?pay_period_id=<?= $selectedPeriod ?>&status=paid&search=<?= urlencode($search) ?>">
                <button class="tab-btn <?= $statusFilter === 'paid' ? 'active' : '' ?>">
                    Paid <span class="tab-count"><?= $countPaid ?></span>
                </button>
            </a>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-header">
                <h6>Payslips — <?= $currentPeriod ? htmlspecialchars(strtoupper($currentPeriod['period_name'])) : 'NO PERIOD SELECTED' ?></h6>
                <small><?= count($payslips) ?> payslip(s)</small>
            </div>

            <?php if (empty($payslips)): ?>
                <div class="empty-state">
                    <i class="bi bi-receipt"></i>
                    <?= $selectedPeriod
                        ? 'No payslips yet. Click "Generate Payslips" above.'
                        : 'Select a pay period to view payslips.' ?>
                    <?php if (empty($periods)): ?>
                        <br><br>
                        <a href="payroll.php" style="color:#696c2b;font-weight:600;">
                            → Create a Pay Period first
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="width:40px;"><input type="checkbox"></th>
                        <th>Staff Name</th>
                        <th>Role</th>
                        <th>Pay Period</th>
                        <th>Hours</th>
                        <th>Gross Pay</th>
                        <th>Tax</th>
                        <th>Net Pay</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payslips as $i => $p): ?>
                    <tr>
                        <td><input type="checkbox"></td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="staff-avatar av-<?= $i % 6 ?>">
                                    <?= htmlspecialchars($p['fi'] . $p['li']) ?>
                                </div>
                                <?= htmlspecialchars($p['staff_name']) ?>
                            </div>
                        </td>
                        <td class="text-muted"><?= htmlspecialchars($p['role_name'] ?? '—') ?></td>
                        <td class="text-muted"><?= htmlspecialchars($p['period_name'] ?? '—') ?></td>
                        <td><?= number_format($p['total_hours'], 0) ?> hrs</td>
                        <td>$<?= number_format($p['total_pay'], 2) ?></td>
                        <td class="text-muted">$<?= number_format($p['tax_amount'], 2) ?></td>
                        <td class="net-pay">$<?= number_format($p['net_pay'], 2) ?></td>
                        <td>
                            <?php if (($p['status'] ?? '') === 'paid' || ($p['status'] ?? '') === 'approved'): ?>
                                <span class="badge-paid">Paid</span>
                            <?php else: ?>
                                <span class="badge-pending">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="action-btn" title="View">
                                <i class="bi bi-eye"></i>
                            </button>
                            <button class="action-btn" title="Download" onclick="window.print()">
                                <i class="bi bi-download"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <div class="pagination-bar">
                <span>Showing <?= count($payslips) ?> of <?= $totalRecords ?> payslips &nbsp;•&nbsp; Page <?= $page ?> of <?= $totalPages ?></span>
                <div class="pagination-btns">
                    <a href="?pay_period_id=<?= $selectedPeriod ?>&status=<?= $statusFilter ?>&page=<?= $page-1 ?>"
                       class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">← Prev</a>
                    <span class="page-btn current"><?= $page ?></span>
                    <a href="?pay_period_id=<?= $selectedPeriod ?>&status=<?= $statusFilter ?>&page=<?= $page+1 ?>"
                       class="page-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">Next →</a>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>