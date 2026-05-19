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

$conn = getConnection();

$result = $conn->query("
    SELECT p.*,
           CONCAT(s.first_name,' ',s.last_name) AS staff_name,
           s.staff_number, s.contact_number, s.contact_email,
           s.address, s.bank_name, s.bsb, s.account_number,
           r.role_name,
           pp.period_name, pp.period_start_date, pp.period_end_date, pp.pay_date,
           si.site_name, si.site_address, si.site_contact_number
    FROM payslips p
    JOIN staff s       ON p.staff_id      = s.staff_id
    LEFT JOIN roles r  ON s.role_id       = r.role_id
    LEFT JOIN pay_periods pp ON p.pay_period_id = pp.pay_period_id
    LEFT JOIN sites si ON si.site_id = (SELECT site_id FROM admin WHERE admin_id = {$_SESSION['admin_id']} LIMIT 1)
    WHERE p.payslip_id = $payslip_id
    LIMIT 1
");

if (!$result || $result->num_rows === 0) {
    echo "Payslip not found.";
    exit();
}

$p    = $result->fetch_assoc();
$super = round($p['total_pay'] * ($p['super_rate'] / 100), 2);

$conn->close();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Payslip — <?= htmlspecialchars($p['staff_name']) ?></title>
    <style>
        /* ── Screen styles ── */
        body {
            font-family: Arial, sans-serif;
            background: #f0f0e8;
            margin: 0;
            padding: 20px;
        }

        .screen-controls {
            max-width: 800px;
            margin: 0 auto 16px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-print {
            background: #696c2b;
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-print:hover { background: #5b5e24; }

        .btn-back {
            background: #fff;
            color: #555;
            border: 1px solid #ddd;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* ── Payslip document ── */
        .payslip {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        /* Header */
        .payslip-header {
            background: #696c2b;
            color: #fff;
            padding: 28px 32px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .company-name {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .company-details {
            font-size: 12px;
            opacity: 0.85;
            line-height: 1.6;
        }

        .payslip-title {
            text-align: right;
        }

        .payslip-title h2 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .payslip-title p {
            font-size: 12px;
            opacity: 0.85;
        }

        /* Period bar */
        .period-bar {
            background: #f0f0e0;
            padding: 12px 32px;
            display: flex;
            gap: 32px;
            border-bottom: 1px solid #ddd;
        }

        .period-item {
            font-size: 12px;
        }

        .period-item label {
            color: #888;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: block;
            margin-bottom: 2px;
        }

        .period-item span {
            color: #333;
            font-weight: 600;
        }

        /* Body */
        .payslip-body {
            padding: 28px 32px;
        }

        /* Employee info */
        .employee-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 28px;
            padding-bottom: 24px;
            border-bottom: 2px solid #f0f0e0;
        }

        .info-block h4 {
            font-size: 11px;
            font-weight: 700;
            color: #696c2b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 4px 0;
            border-bottom: 1px solid #f5f5f0;
        }

        .info-row label { color: #888; }
        .info-row span  { font-weight: 600; color: #333; }

        /* Earnings table */
        .earnings-section { margin-bottom: 24px; }

        .earnings-section h4 {
            font-size: 11px;
            font-weight: 700;
            color: #696c2b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 12px;
        }

        .pay-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .pay-table thead th {
            background: #f8f8f0;
            padding: 9px 12px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e8e8d8;
        }

        .pay-table tbody td {
            padding: 10px 12px;
            border-bottom: 1px solid #f0f0e8;
            color: #333;
        }

        .pay-table tbody tr:last-child td { border-bottom: none; }
        .pay-table .amount { text-align: right; font-weight: 600; }
        .pay-table .deduction { color: #dc2626; }

        /* Summary box */
        .summary-box {
            background: #f8f8f0;
            border: 2px solid #696c2b;
            border-radius: 10px;
            padding: 20px 24px;
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }

        .summary-item { text-align: center; }

        .summary-item label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }

        .summary-item .amount {
            font-size: 22px;
            font-weight: 700;
            color: #696c2b;
        }

        .summary-item.net-pay .amount {
            font-size: 28px;
            color: #2c5e1a;
        }

        .summary-item.deduction .amount { color: #dc2626; }

        /* Bank details */
        .bank-section {
            background: #f0f0e8;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 20px;
        }

        .bank-section h4 {
            font-size: 11px;
            font-weight: 700;
            color: #696c2b;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }

        .bank-row {
            display: flex;
            gap: 32px;
            font-size: 13px;
        }

        .bank-item label { color: #888; font-size: 11px; display: block; margin-bottom: 2px; }
        .bank-item span  { font-weight: 600; color: #333; }

        /* Leave */
        .leave-section {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }

        .leave-card {
            background: #f8f8f0;
            border-radius: 8px;
            padding: 12px 16px;
            text-align: center;
        }

        .leave-card label {
            display: block;
            font-size: 10px;
            font-weight: 700;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }

        .leave-card span {
            font-size: 18px;
            font-weight: 700;
            color: #696c2b;
        }

        /* Footer */
        .payslip-footer {
            background: #f8f8f0;
            border-top: 2px solid #e8e8d8;
            padding: 16px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 11px;
            color: #888;
        }

        .payslip-footer strong { color: #696c2b; }

        /* ── Print styles ── */
        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }

            .screen-controls { display: none !important; }

            .payslip {
                max-width: 100%;
                border-radius: 0;
                box-shadow: none;
                margin: 0;
            }

            @page {
                size: A4;
                margin: 10mm;
            }
        }
    </style>
</head>
<body>

<!-- Screen Controls (hidden when printing) -->
<div class="screen-controls">
    <a href="payslips.php" class="btn-back">← Back</a>
    <button class="btn-print" onclick="window.print()">
        🖨️ Print / Save as PDF
    </button>
</div>

<!-- Payslip Document -->
<div class="payslip">

    <!-- Header -->
    <div class="payslip-header">
        <div>
            <div class="company-name">🌾 <?= htmlspecialchars($p['site_name'] ?? 'Farm Time Management') ?></div>
            <div class="company-details">
                <?= htmlspecialchars($p['site_address'] ?? '') ?><br>
                <?= htmlspecialchars($p['site_contact_number'] ?? '') ?>
            </div>
        </div>
        <div class="payslip-title">
            <h2>PAYSLIP</h2>
            <p>Payslip #<?= str_pad($p['payslip_id'], 6, '0', STR_PAD_LEFT) ?></p>
            <p>Generated: <?= date('d M Y', strtotime($p['generated_at'])) ?></p>
        </div>
    </div>

    <!-- Period Bar -->
    <div class="period-bar">
        <div class="period-item">
            <label>Pay Period</label>
            <span><?= htmlspecialchars($p['period_name'] ?? '') ?></span>
        </div>
        <div class="period-item">
            <label>Period Start</label>
            <span><?= date('d M Y', strtotime($p['period_start_date'])) ?></span>
        </div>
        <div class="period-item">
            <label>Period End</label>
            <span><?= date('d M Y', strtotime($p['period_end_date'])) ?></span>
        </div>
        <div class="period-item">
            <label>Pay Date</label>
            <span><?= date('d M Y', strtotime($p['pay_date'])) ?></span>
        </div>
    </div>

    <div class="payslip-body">

        <!-- Employee Info -->
        <div class="employee-section">
            <div class="info-block">
                <h4>Employee Details</h4>
                <div class="info-row"><label>Full Name</label><span><?= htmlspecialchars($p['staff_name']) ?></span></div>
                <div class="info-row"><label>Staff Number</label><span><?= htmlspecialchars($p['staff_number']) ?></span></div>
                <div class="info-row"><label>Role / Position</label><span><?= htmlspecialchars($p['role_name'] ?? '—') ?></span></div>
                <div class="info-row"><label>Email</label><span><?= htmlspecialchars($p['contact_email'] ?? '—') ?></span></div>
                <div class="info-row"><label>Phone</label><span><?= htmlspecialchars($p['contact_number'] ?? '—') ?></span></div>
            </div>
            <div class="info-block">
                <h4>Payment Summary</h4>
                <div class="info-row"><label>Total Hours</label><span><?= number_format($p['total_hours'], 2) ?> hrs</span></div>
                <div class="info-row"><label>Gross Pay</label><span>$<?= number_format($p['total_pay'], 2) ?></span></div>
                <div class="info-row"><label>Tax Rate</label><span><?= number_format($p['tax_rate'], 2) ?>%</span></div>
                <div class="info-row"><label>Super Rate</label><span><?= number_format($p['super_rate'], 2) ?>%</span></div>
                <div class="info-row"><label>YTD Gross</label><span>$<?= number_format($p['ytd_gross_pay'], 2) ?></span></div>
            </div>
        </div>

        <!-- Earnings & Deductions -->
        <div class="earnings-section">
            <h4>Earnings & Deductions</h4>
            <table class="pay-table">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th>Hours</th>
                        <th>Rate</th>
                        <th style="text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Base Pay</td>
                        <td><?= number_format($p['total_hours'], 2) ?> hrs</td>
                        <td>—</td>
                        <td class="amount">$<?= number_format($p['total_pay'], 2) ?></td>
                    </tr>
                    <?php if ($p['night_pay'] > 0): ?>
                    <tr>
                        <td>Night Pay Allowance</td>
                        <td>—</td>
                        <td>—</td>
                        <td class="amount">$<?= number_format($p['night_pay'], 2) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr style="background:#fff5f5;">
                        <td class="deduction">Tax (PAYG Withholding)</td>
                        <td>—</td>
                        <td><?= number_format($p['tax_rate'], 2) ?>%</td>
                        <td class="amount deduction">-$<?= number_format($p['tax_amount'], 2) ?></td>
                    </tr>
                    <tr style="background:#f0f8ff;">
                        <td style="color:#2563eb;">Superannuation (<?= number_format($p['super_rate'], 2) ?>%)</td>
                        <td>—</td>
                        <td><?= number_format($p['super_rate'], 2) ?>%</td>
                        <td class="amount" style="color:#2563eb;">$<?= number_format($super, 2) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Net Pay Summary -->
        <div class="summary-box">
            <div class="summary-item">
                <label>Gross Pay</label>
                <div class="amount">$<?= number_format($p['total_pay'], 2) ?></div>
            </div>
            <div class="summary-item deduction">
                <label>Total Deductions</label>
                <div class="amount">-$<?= number_format($p['tax_amount'], 2) ?></div>
            </div>
            <div class="summary-item net-pay">
                <label>Net Pay</label>
                <div class="amount">$<?= number_format($p['net_pay'], 2) ?></div>
            </div>
        </div>

        <!-- Leave Balances -->
        <div class="earnings-section">
            <h4>Leave Balances</h4>
            <div class="leave-section">
                <div class="leave-card">
                    <label>Leave Accrued</label>
                    <span><?= number_format($p['annual_leave_accrued'], 2) ?> hrs</span>
                </div>
                <div class="leave-card">
                    <label>Leave Used</label>
                    <span><?= number_format($p['annual_leave_used'], 2) ?> hrs</span>
                </div>
                <div class="leave-card">
                    <label>Leave Balance</label>
                    <span><?= number_format($p['annual_leave_balance'], 2) ?> hrs</span>
                </div>
            </div>
        </div>

        <!-- Bank Details -->
        <?php if ($p['bank_name'] || $p['bsb'] || $p['account_number']): ?>
        <div class="bank-section">
            <h4>Bank Payment Details</h4>
            <div class="bank-row">
                <?php if ($p['bank_name']): ?>
                <div class="bank-item"><label>Bank</label><span><?= htmlspecialchars($p['bank_name']) ?></span></div>
                <?php endif; ?>
                <?php if ($p['bsb']): ?>
                <div class="bank-item"><label>BSB</label><span><?= htmlspecialchars($p['bsb']) ?></span></div>
                <?php endif; ?>
                <?php if ($p['account_number']): ?>
                <div class="bank-item"><label>Account Number</label><span><?= htmlspecialchars($p['account_number']) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Footer -->
    <div class="payslip-footer">
        <div>
            This is a computer-generated payslip. No signature required.<br>
            <strong><?= htmlspecialchars($p['site_name'] ?? 'Farm Time Management') ?></strong>
        </div>
        <div style="text-align:right;">
            Payslip #<?= str_pad($p['payslip_id'], 6, '0', STR_PAD_LEFT) ?><br>
            Generated: <?= date('d M Y H:i', strtotime($p['generated_at'])) ?>
        </div>
    </div>

</div>

<script>
// Auto print if ?print=1 in URL
if (new URLSearchParams(window.location.search).get('print') === '1') {
    window.onload = () => window.print();
}
</script>

</body>
</html>
