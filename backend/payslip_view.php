<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$payslip_id = intval($_GET['id'] ?? 0);
if (!$payslip_id) {
    header('Location: payslips.php');
    exit();
}

$conn   = getConnection();
$result = $conn->query("
    SELECT p.*,
           s.first_name, s.last_name, s.staff_number,
           s.contact_number, s.contact_email, s.address,
           s.bank_name, s.bsb, s.account_number, s.tfn,
           c.contract_type, c.pay_type, c.standard_pay_rate,
           r.role_name,
           pp.period_name, pp.period_start_date, pp.period_end_date, pp.pay_date,
           si.site_name, si.site_address, si.site_contact_number
    FROM payslips p
    JOIN staff s ON p.staff_id = s.staff_id
    LEFT JOIN contracts c  ON s.contract_id     = c.contract_id
    LEFT JOIN roles r      ON s.role_id          = r.role_id
    LEFT JOIN pay_periods pp ON p.pay_period_id  = pp.pay_period_id
    LEFT JOIN sites si ON si.site_id = (
        SELECT site_id FROM admin WHERE admin_id = {$_SESSION['admin_id']} LIMIT 1
    )
    WHERE p.payslip_id = $payslip_id
    LIMIT 1
");

if (!$result || $result->num_rows === 0) {
    echo '<p>Payslip not found.</p>';
    exit();
}

$p     = $result->fetch_assoc();
$conn->close();

$super        = round($p['total_pay'] * ($p['super_rate'] / 100), 2);
$staffName    = $p['first_name'] . ' ' . $p['last_name'];
$employStatus = ucfirst($p['contract_type'] ?? 'Full time');
$hourlyRate   = '$' . number_format($p['standard_pay_rate'] ?? 0, 2);
$periodStart  = date('d M Y', strtotime($p['period_start_date']));
$periodEnd    = date('d M Y', strtotime($p['period_end_date']));
$payDate      = date('d M Y', strtotime($p['pay_date']));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Payslip — <?= htmlspecialchars($staffName) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: Arial, sans-serif;
            background: #e8edf2;
            padding: 20px;
            font-size: 13px;
            color: #222;
        }

        /* Screen controls */
        .screen-controls {
            max-width: 720px;
            margin: 0 auto 12px;
            display: flex;
            gap: 8px;
            justify-content: flex-end;
        }

        .btn-back {
            background: #fff;
            color: #555;
            border: 1px solid #ccc;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }

        .btn-print {
            background: #696c2b;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-download {
            background: #1d4ed8;
            color: #fff;
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        /* Payslip document */
        .payslip {
            max-width: 720px;
            margin: 0 auto;
            background: #fff;
            padding: 40px 48px;
            border-radius: 4px;
        }

        /* Title */
        .doc-title {
            text-align: center;
            margin-bottom: 6px;
        }

        .doc-title h1 {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .doc-title p {
            font-size: 13px;
            color: #333;
            line-height: 1.6;
        }

        /* Employee info grid */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            margin: 24px 0 20px;
        }

        .info-row {
            display: flex;
            gap: 8px;
            padding: 3px 0;
            font-size: 13px;
        }

        .info-label {
            font-weight: bold;
            min-width: 140px;
        }

        .info-value {
            color: #333;
        }

        /* Section heading */
        .section-heading {
            font-weight: bold;
            font-size: 13px;
            margin: 16px 0 4px;
        }

        /* Tables */
        .pay-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .pay-table thead th {
            font-weight: bold;
            padding: 6px 8px;
            border-top: 1px solid #999;
            border-bottom: 1px solid #999;
            text-align: left;
        }

        .pay-table thead th:not(:first-child) {
            text-align: right;
        }

        .pay-table tbody td {
            padding: 5px 8px;
            border-bottom: 1px solid #e0e0e0;
        }

        .pay-table tbody td:not(:first-child) {
            text-align: right;
        }

        .pay-table tfoot td {
            padding: 6px 8px;
            font-weight: bold;
            border-top: 1px solid #999;
            background: #f5f5f5;
        }

        .pay-table tfoot td:not(:first-child) {
            text-align: right;
        }

        /* Deductions table — only 2 visible columns */
        .deduct-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            margin-bottom: 20px;
        }

        .deduct-table thead th {
            font-weight: bold;
            padding: 6px 8px;
            border-top: 1px solid #999;
            border-bottom: 1px solid #999;
            text-align: left;
        }

        .deduct-table thead th.right { text-align: right; }

        .deduct-table tbody td {
            padding: 5px 8px;
            border-bottom: 1px solid #e0e0e0;
        }

        .deduct-table tbody td.right { text-align: right; }

        .deduct-table tfoot td {
            padding: 6px 8px;
            font-weight: bold;
            border-top: 1px solid #999;
            background: #f5f5f5;
        }

        .deduct-table tfoot td.right { text-align: right; }

        /* Net pay section */
        .net-section {
            margin-top: 16px;
            font-size: 13px;
        }

        .net-section .net-title {
            font-weight: bold;
            margin-bottom: 6px;
        }

        .net-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
        }

        .net-row.total {
            font-weight: bold;
            border-top: 1px solid #999;
            margin-top: 6px;
            padding-top: 6px;
        }

        /* Print */
        @media print {
            body { background: white; padding: 0; }
            .screen-controls { display: none !important; }
            .payslip { box-shadow: none; border-radius: 0; max-width: 100%; padding: 20px 28px; }
            @page { size: A4; margin: 10mm; }
        }
    </style>
</head>
<body>

<!-- Controls -->
<div class="screen-controls">
    <a href="payslips.php" class="btn-back">← Back</a>
    <a href="download_payslip.php?id=<?= $payslip_id ?>" class="btn-download">⬇ Download PDF</a>
    <button class="btn-print" onclick="window.print()">🖨 Print</button>
</div>

<!-- Payslip Document -->
<div class="payslip">

    <!-- Title -->
    <div class="doc-title">
        <h1>Payslip</h1>
        <p>
            <?= htmlspecialchars($p['site_name'] ?? 'Business name') ?><br>
            <?= htmlspecialchars($p['site_contact_number'] ?? 'Organization number: [Enter organization number]') ?><br>
            <?= htmlspecialchars($p['site_address'] ?? 'Address: [Enter address]') ?>
        </p>
    </div>

    <!-- Employee Info Grid -->
    <div class="info-grid">
        <!-- Left column -->
        <div>
            <div class="info-row">
                <span class="info-label">Employee name:</span>
                <span class="info-value"><?= htmlspecialchars($staffName) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Employment status:</span>
                <span class="info-value"><?= htmlspecialchars($employStatus) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Award/agreement:</span>
                <span class="info-value"><?= htmlspecialchars($p['role_name'] ?? '—') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Classification:</span>
                <span class="info-value"><?= htmlspecialchars($p['role_name'] ?? '—') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Hourly rate:</span>
                <span class="info-value"><?= $hourlyRate ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Annual salary:</span>
                <span class="info-value">$<?= number_format(($p['standard_pay_rate'] ?? 0) * 52 * 38, 2) ?></span>
            </div>
        </div>

        <!-- Right column -->
        <div>
            <div class="info-row">
                <span class="info-label">Pay period:</span>
                <span class="info-value"><?= $periodStart ?> to <?= $periodEnd ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Pay date:</span>
                <span class="info-value"><?= $payDate ?></span>
            </div>
            <div class="info-row" style="margin-top:16px;">
                <span class="info-label">Annual leave balance:</span>
                <span class="info-value"><?= number_format($p['annual_leave_balance'], 2) ?> hrs</span>
            </div>
            <div class="info-row">
                <span class="info-label">Sick/carer's leave balance:</span>
                <span class="info-value">0.00 hrs</span>
            </div>
        </div>
    </div>

    <!-- Entitlements -->
    <div class="section-heading">Entitlements</div>
    <table class="pay-table">
        <thead>
            <tr>
                <th>Description</th>
                <th>Hours / units</th>
                <th>Rate</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Ordinary hours</td>
                <td><?= number_format($p['total_hours'], 2) ?></td>
                <td>$<?= number_format($p['standard_pay_rate'] ?? 0, 2) ?></td>
                <td>$<?= number_format($p['total_pay'], 2) ?></td>
            </tr>
            <?php if ($p['night_pay'] > 0): ?>
            <tr>
                <td>Night pay allowance</td>
                <td>—</td>
                <td>—</td>
                <td>$<?= number_format($p['night_pay'], 2) ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td>Superannuation (<?= number_format($p['super_rate'], 2) ?>%)</td>
                <td>—</td>
                <td><?= number_format($p['super_rate'], 2) ?>%</td>
                <td>$<?= number_format($super, 2) ?></td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td><?= number_format($p['total_hours'], 2) ?></td>
                <td>$<?= number_format($p['standard_pay_rate'] ?? 0, 2) ?></td>
                <td>$<?= number_format($p['total_pay'], 2) ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Deductions -->
    <div class="section-heading">Deductions</div>
    <table class="deduct-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="right">Hours / units</th>
                <th class="right">Rate</th>
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Income tax (PAYG <?= number_format($p['tax_rate'], 2) ?>%)</td>
                <td class="right">0</td>
                <td class="right">$<?= number_format($p['tax_amount'], 2) ?></td>
                <td class="right">$<?= number_format($p['tax_amount'], 2) ?></td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td>Total</td>
                <td class="right">0</td>
                <td class="right">$<?= number_format($p['tax_amount'], 2) ?></td>
                <td class="right">$<?= number_format($p['tax_amount'], 2) ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- Net Pay -->
    <div class="net-section">
        <div class="net-title">Net pay</div>
        <div class="net-row">
            <span>Bank details:</span>
            <span><?= htmlspecialchars($p['bank_name'] ?? '[Employee\'s bank name]') ?></span>
        </div>
        <div class="net-row">
            <span>Account number:</span>
            <span><?= htmlspecialchars($p['account_number'] ?? '[Employee\'s account number]') ?></span>
        </div>
        <?php if ($p['bsb']): ?>
        <div class="net-row">
            <span>BSB:</span>
            <span><?= htmlspecialchars($p['bsb']) ?></span>
        </div>
        <?php endif; ?>
        <div class="net-row total">
            <span>Total net pay:</span>
            <span>$<?= number_format($p['net_pay'], 2) ?></span>
        </div>
    </div>

</div>

<script>
if (new URLSearchParams(window.location.search).get('print') === '1') {
    window.onload = () => window.print();
}
</script>
</body>
</html>
