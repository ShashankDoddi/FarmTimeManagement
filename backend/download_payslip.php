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
           c.contract_type, c.standard_pay_rate,
           r.role_name,
           pp.period_name, pp.period_start_date, pp.period_end_date, pp.pay_date,
           si.site_name, si.site_address, si.site_contact_number
    FROM payslips p
    JOIN staff s ON p.staff_id = s.staff_id
    LEFT JOIN contracts c ON s.contract_id = c.contract_id
    LEFT JOIN roles r ON s.role_id = r.role_id
    LEFT JOIN pay_periods pp ON p.pay_period_id = pp.pay_period_id
    LEFT JOIN sites si ON si.site_id = (
        SELECT site_id FROM admin WHERE admin_id = {$_SESSION['admin_id']} LIMIT 1
    )
    WHERE p.payslip_id = $payslip_id
    LIMIT 1
");

if (!$result || $result->num_rows === 0) {
    die('Payslip not found.');
}

$p    = $result->fetch_assoc();
$conn->close();

$super        = round($p['total_pay'] * ($p['super_rate'] / 100), 2);
$staffName    = $p['first_name'] . ' ' . $p['last_name'];
$employStatus = ucfirst($p['contract_type'] ?? 'Full time');
$periodStart  = date('d M Y', strtotime($p['period_start_date']));
$periodEnd    = date('d M Y', strtotime($p['period_end_date']));
$payDate      = date('d M Y', strtotime($p['pay_date']));

// ── Check mPDF ───────────────────────────────────────────────
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    ?>
    <!doctype html>
    <html><head><meta charset="UTF-8"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
    </head><body class="p-5">
    <div class="alert alert-warning">
        <h4>⚠️ mPDF not installed</h4>
        <p>Run this in your terminal:</p>
        <code class="d-block bg-dark text-white p-3 rounded mb-3">
            cd C:\xampp\htdocs\FarmTimeManagement\backend<br>
            composer require mpdf/mpdf
        </code>
        <a href="payslip_view.php?id=<?= $payslip_id ?>" class="btn btn-primary">← View & Print instead</a>
    </div>
    </body></html>
    <?php
    exit();
}

require_once $autoloadPath;

$mpdf = new \Mpdf\Mpdf([
    'margin_top'    => 15,
    'margin_bottom' => 15,
    'margin_left'   => 18,
    'margin_right'  => 18,
    'format'        => 'A4',
]);

$mpdf->SetTitle('Payslip - ' . $staffName);

$html = '
<style>
    body { font-family: Arial, sans-serif; font-size: 12px; color: #222; }

    .doc-title { text-align: center; margin-bottom: 20px; }
    .doc-title h1 { font-size: 18px; font-weight: bold; margin-bottom: 4px; }
    .doc-title p  { font-size: 12px; line-height: 1.6; color: #333; }

    .info-table { width: 100%; margin-bottom: 16px; border-collapse: collapse; }
    .info-table td { padding: 3px 4px; font-size: 12px; vertical-align: top; }
    .info-label { font-weight: bold; width: 38%; }
    .info-value { color: #333; width: 22%; }
    .info-label-r { font-weight: bold; width: 22%; }
    .info-value-r { color: #333; width: 18%; }

    .section-heading { font-weight: bold; font-size: 12px; margin: 14px 0 4px; }

    .pay-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 16px; }
    .pay-table th { font-weight: bold; padding: 6px 6px; border-top: 1px solid #888; border-bottom: 1px solid #888; text-align: left; }
    .pay-table th.r { text-align: right; }
    .pay-table td { padding: 5px 6px; border-bottom: 1px solid #ddd; }
    .pay-table td.r { text-align: right; }
    .pay-table tfoot td { font-weight: bold; background: #f5f5f5; border-top: 1px solid #888; padding: 5px 6px; }
    .pay-table tfoot td.r { text-align: right; }

    .net-section { margin-top: 14px; font-size: 12px; }
    .net-title { font-weight: bold; margin-bottom: 6px; }
    .net-row { display: flex; justify-content: space-between; padding: 3px 0; border-bottom: 1px solid #eee; }
    .net-total { font-weight: bold; border-top: 1px solid #888; margin-top: 6px; padding-top: 6px; display: flex; justify-content: space-between; }
</style>

<div class="doc-title">
    <h1>Payslip</h1>
    <p>
        '. htmlspecialchars($p['site_name'] ?? 'Business name') .'<br>
        '. htmlspecialchars($p['site_contact_number'] ?? '') .'<br>
        '. htmlspecialchars($p['site_address'] ?? '') .'
    </p>
</div>

<table class="info-table">
    <tr>
        <td class="info-label">Employee name:</td>
        <td class="info-value">'. htmlspecialchars($staffName) .'</td>
        <td class="info-label-r">Pay period:</td>
        <td class="info-value-r">'. $periodStart .' to '. $periodEnd .'</td>
    </tr>
    <tr>
        <td class="info-label">Employment status:</td>
        <td class="info-value">'. htmlspecialchars($employStatus) .'</td>
        <td class="info-label-r">Pay date:</td>
        <td class="info-value-r">'. $payDate .'</td>
    </tr>
    <tr>
        <td class="info-label">Award/agreement:</td>
        <td class="info-value">'. htmlspecialchars($p['role_name'] ?? '—') .'</td>
        <td class="info-label-r"></td>
        <td class="info-value-r"></td>
    </tr>
    <tr>
        <td class="info-label">Classification:</td>
        <td class="info-value">'. htmlspecialchars($p['role_name'] ?? '—') .'</td>
        <td class="info-label-r">Annual leave balance:</td>
        <td class="info-value-r">'. number_format($p['annual_leave_balance'], 2) .' hrs</td>
    </tr>
    <tr>
        <td class="info-label">Hourly rate:</td>
        <td class="info-value">$'. number_format($p['standard_pay_rate'] ?? 0, 2) .'</td>
        <td class="info-label-r">Sick/carer leave balance:</td>
        <td class="info-value-r">0.00 hrs</td>
    </tr>
    <tr>
        <td class="info-label">Annual salary:</td>
        <td class="info-value">$'. number_format(($p['standard_pay_rate'] ?? 0) * 52 * 38, 2) .'</td>
        <td></td><td></td>
    </tr>
</table>

<div class="section-heading">Entitlements</div>
<table class="pay-table">
    <thead>
        <tr>
            <th>Description</th>
            <th class="r">Hours / units</th>
            <th class="r">Rate</th>
            <th class="r">Total</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Ordinary hours</td>
            <td class="r">'. number_format($p['total_hours'], 2) .'</td>
            <td class="r">$'. number_format($p['standard_pay_rate'] ?? 0, 2) .'</td>
            <td class="r">$'. number_format($p['total_pay'], 2) .'</td>
        </tr>
        '. ($p['night_pay'] > 0 ? '<tr><td>Night pay allowance</td><td class="r">—</td><td class="r">—</td><td class="r">$'. number_format($p['night_pay'], 2) .'</td></tr>' : '') .'
        <tr>
            <td>Superannuation ('. number_format($p['super_rate'], 2) .'%)</td>
            <td class="r">—</td>
            <td class="r">'. number_format($p['super_rate'], 2) .'%</td>
            <td class="r">$'. number_format($super, 2) .'</td>
        </tr>
    </tbody>
    <tfoot>
        <tr>
            <td>Total</td>
            <td class="r">'. number_format($p['total_hours'], 2) .'</td>
            <td class="r">$'. number_format($p['standard_pay_rate'] ?? 0, 2) .'</td>
            <td class="r">$'. number_format($p['total_pay'], 2) .'</td>
        </tr>
    </tfoot>
</table>

<div class="section-heading">Deductions</div>
<table class="pay-table">
    <thead>
        <tr>
            <th>Description</th>
            <th class="r">Hours / units</th>
            <th class="r">Rate</th>
            <th class="r">Total</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Income tax (PAYG '. number_format($p['tax_rate'], 2) .'%)</td>
            <td class="r">0</td>
            <td class="r">$'. number_format($p['tax_amount'], 2) .'</td>
            <td class="r">$'. number_format($p['tax_amount'], 2) .'</td>
        </tr>
    </tbody>
    <tfoot>
        <tr>
            <td>Total</td>
            <td class="r">0</td>
            <td class="r">$'. number_format($p['tax_amount'], 2) .'</td>
            <td class="r">$'. number_format($p['tax_amount'], 2) .'</td>
        </tr>
    </tfoot>
</table>

<div class="net-section">
    <div class="net-title">Net pay</div>
    <table style="width:100%;font-size:12px;border-collapse:collapse;">
        <tr><td style="padding:3px 0;">Bank details:</td><td style="text-align:right;">'. htmlspecialchars($p['bank_name'] ?? '—') .'</td></tr>
        <tr><td style="padding:3px 0;">Account number:</td><td style="text-align:right;">'. htmlspecialchars($p['account_number'] ?? '—') .'</td></tr>
        '. ($p['bsb'] ? '<tr><td style="padding:3px 0;">BSB:</td><td style="text-align:right;">'. htmlspecialchars($p['bsb']) .'</td></tr>' : '') .'
        <tr style="font-weight:bold;border-top:1px solid #888;">
            <td style="padding:6px 0 3px;"><strong>Total net pay:</strong></td>
            <td style="text-align:right;padding:6px 0 3px;"><strong>$'. number_format($p['net_pay'], 2) .'</strong></td>
        </tr>
    </table>
</div>
';

$mpdf->WriteHTML($html);
$filename = 'Payslip_' . $p['staff_number'] . '_' . str_replace([' ', '/'], '_', $p['period_name'] ?? 'period') . '.pdf';
$mpdf->Output($filename, 'D');
exit();
