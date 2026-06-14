<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit(); }

$conn    = getConnection();
$adminId = $_SESSION['admin_id'];

// CREATE PERIOD
if (isset($_POST['action']) && $_POST['action'] === 'create_period') {
    $pn=$_POST['period_name']??''; $ps=$_POST['period_start']??''; $pe=$_POST['period_end']??''; $pd=$_POST['pay_date']??'';
    if ($pn && $ps && $pe && $pd) {
        $stmt=$conn->prepare("INSERT INTO pay_periods (period_name,period_start_date,period_end_date,pay_date,status,created_by) VALUES (?,?,?,?,'open',?)");
        $stmt->bind_param('ssssi',$pn,$ps,$pe,$pd,$adminId); $stmt->execute(); $newId=$conn->insert_id; $stmt->close();
        header("Location: payslips.php?pay_period_id=$newId&msg=period_created"); exit();
    }
}

// GENERATE
if (isset($_POST['action']) && $_POST['action'] === 'generate') {
    $pid=intval($_POST['pay_period_id']);
    $per=$conn->query("SELECT * FROM pay_periods WHERE pay_period_id=$pid")->fetch_assoc();
    if ($per) {
        $staffList=$conn->query("SELECT s.*,c.standard_pay_rate,c.overtime_pay_rate,c.standard_weekly_hours,c.annual_leave_rate FROM staff s LEFT JOIN contracts c ON s.contract_id=c.contract_id WHERE LOWER(s.status)='active'")->fetch_all(MYSQLI_ASSOC);
        $gen=0; $weeks=max(1,round((strtotime($per['period_end_date'])-strtotime($per['period_start_date']))/(7*86400)));
        foreach ($staffList as $s) {
            $ex=$conn->query("SELECT payslip_id FROM payslips WHERE staff_id={$s['staff_id']} AND pay_period_id=$pid");
            if ($ex && $ex->num_rows>0) continue;
            $attR=$conn->query("SELECT IFNULL(SUM(TIMESTAMPDIFF(MINUTE,clock_in,IFNULL(clock_out,NOW()))),0)/60 AS th FROM attendance WHERE staff_id={$s['staff_id']} AND DATE(clock_in) BETWEEN '{$per['period_start_date']}' AND '{$per['period_end_date']}' AND clock_out IS NOT NULL")->fetch_assoc();
            $th=round(floatval($attR['th']??0),2); $rate=floatval($s['standard_pay_rate']??25);
            $stdPer=floatval($s['standard_weekly_hours']??38)*$weeks; $ot=max(0,$th-$stdPer);
            $tp=($th-$ot)*$rate+$ot*($rate*1.5); $ann=$tp*(52/max(1,$weeks));
            $taxRate=$ann<18201?0:($ann<45001?0.19:($ann<120001?0.325:0.37));
            $tax=round($tp*$taxRate,2); $net=round($tp-$tax,2);
            $ytdR=$conn->query("SELECT IFNULL(SUM(total_pay),0) AS ytd FROM payslips WHERE staff_id={$s['staff_id']}")->fetch_assoc();
            $ytd=floatval($ytdR['ytd']??0)+$tp; $la=round($th*floatval($s['annual_leave_rate']??0.0769),4);
            $stmt=$conn->prepare("INSERT INTO payslips (staff_id,pay_period_id,period_start_date,period_end_date,pay_date,ytd_gross_pay,total_hours,total_pay,super_rate,tax_rate,tax_amount,night_pay,annual_leave_accrued,annual_leave_used,annual_leave_balance,net_pay,generated_by) VALUES (?,?,?,?,?,?,?,?,11,?,?,0,?,0,?,?,?)");
            $taxPct=round($taxRate*100,2);
            $stmt->bind_param('iisssdddddddddi',$s['staff_id'],$pid,$per['period_start_date'],$per['period_end_date'],$per['pay_date'],$ytd,$th,$tp,$taxPct,$tax,$la,$la,$net,$adminId);
            if ($stmt->execute()) $gen++; $stmt->close();
        }
        $conn->query("UPDATE pay_periods SET status='processed' WHERE pay_period_id=$pid");
        header("Location: payslips.php?pay_period_id=$pid&msg=generated&count=$gen"); exit();
    }
}

$selectedPeriod = intval($_GET['pay_period_id']??0);
$statusFilter   = $_GET['status']??'all';
$periods = $conn->query("SELECT * FROM pay_periods ORDER BY period_start_date DESC")->fetch_all(MYSQLI_ASSOC);
if (!$selectedPeriod && !empty($periods)) $selectedPeriod=$periods[0]['pay_period_id'];

$currentPeriod = $selectedPeriod ? $conn->query("SELECT * FROM pay_periods WHERE pay_period_id=$selectedPeriod")->fetch_assoc() : null;

function sc2($c,$s){ $r=$c->query($s); return($r&&$r->num_rows>0)?intval($r->fetch_assoc()['c']):0; }
$cAll=$selectedPeriod?sc2($conn,"SELECT COUNT(*) AS c FROM payslips p JOIN staff s ON p.staff_id=s.staff_id WHERE p.pay_period_id=$selectedPeriod"):0;
$cPend=$selectedPeriod?sc2($conn,"SELECT COUNT(*) AS c FROM payslips p JOIN staff s ON p.staff_id=s.staff_id WHERE p.pay_period_id=$selectedPeriod AND (p.status='draft' OR p.status IS NULL)"):0;
$cPaid=$selectedPeriod?sc2($conn,"SELECT COUNT(*) AS c FROM payslips p JOIN staff s ON p.staff_id=s.staff_id WHERE p.pay_period_id=$selectedPeriod AND p.status='paid'"):0;

$pWhere=$selectedPeriod?"WHERE p.pay_period_id=$selectedPeriod":"WHERE 1=0";
if ($statusFilter==='pending') $pWhere.=" AND (p.status='draft' OR p.status IS NULL)";
if ($statusFilter==='paid') $pWhere.=" AND p.status='paid'";

$payslips=[];
if ($selectedPeriod) {
    $pr=$conn->query("SELECT p.*,CONCAT(s.first_name,' ',s.last_name) AS staff_name,s.staff_number,LEFT(s.first_name,1) AS fi,LEFT(s.last_name,1) AS li,r.role_name,pp.period_name FROM payslips p JOIN staff s ON p.staff_id=s.staff_id LEFT JOIN roles r ON s.role_id=r.role_id LEFT JOIN pay_periods pp ON p.pay_period_id=pp.pay_period_id $pWhere ORDER BY s.first_name");
    $payslips=$pr?$pr->fetch_all(MYSQLI_ASSOC):[];
}
$conn->close();
$initials=strtoupper(substr($_SESSION['username'],0,2));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Payslips — Farm TMS</title>
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
            <a href="reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reports</a>
            <a href="payslips.php" class="nav-link active"><i class="bi bi-receipt"></i> Payslips</a>
            <a href="settings.php" class="nav-link"><i class="bi bi-gear"></i> Settings</a>
        </nav>
        <div class="mt-auto p-3" style="border-top:1px solid rgba(255,255,255,0.15)">
            <a href="logout.php" class="nav-link" style="color:rgba(255,100,100,0.85)"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
        </div>
    </aside>

    <div class="main-content">
        <header class="topbar">
            <span class="page-title">Payslips</span>
            <div class="topbar-right">
                <span class="topbar-date"><i class="bi bi-calendar3 me-1"></i><?= date('D, d M Y') ?></span>
                <div class="admin-badge"><div class="admin-avatar"><?= $initials ?></div><?= htmlspecialchars($_SESSION['username']) ?></div>
            </div>
        </header>

        <div class="page-body">
            <?php if (isset($_GET['msg'])): ?>
            <div class="toast-success"><i class="bi bi-check-circle-fill"></i>
                <?= $_GET['msg']==='generated'?intval($_GET['count']??0).' payslip(s) generated!':($_GET['msg']==='period_created'?'Pay period created! Click "Generate Payslips" to process.':'Done!') ?>
            </div>
            <?php endif; ?>

            <!-- Controls -->
            <div class="card-box mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-3 align-items-end">
                        <form method="GET" class="d-flex gap-3 flex-grow-1" id="periodForm">
                            <div class="filter-group">
                                <label>Pay Period</label>
                                <select name="pay_period_id" class="filter-input" onchange="this.form.submit()">
                                    <option value="">— Select Period —</option>
                                    <?php foreach ($periods as $p): ?>
                                    <option value="<?= $p['pay_period_id'] ?>" <?= $selectedPeriod==$p['pay_period_id']?'selected':''?>><?= htmlspecialchars($p['period_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>">
                        </form>
                        <div class="d-flex gap-2" style="align-self:flex-end;">
                            <button class="btn-outline-brand" onclick="document.getElementById('periodModal').classList.add('open')"><i class="bi bi-plus me-1"></i> New Period</button>
                            <?php if ($selectedPeriod && $currentPeriod && strtolower($currentPeriod['status']??'')!=='processed'): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="generate">
                                <input type="hidden" name="pay_period_id" value="<?= $selectedPeriod ?>">
                                <button type="submit" class="btn-brand" onclick="return confirm('Generate payslips for all active staff?')"><i class="bi bi-play-fill me-1"></i> Generate Payslips</button>
                            </form>
                            <?php else: ?>
                            <button class="btn-brand" disabled style="opacity:0.5;"><?= $selectedPeriod?'Already Generated':'Select a Period' ?></button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="status-tabs">
                <a href="?pay_period_id=<?= $selectedPeriod ?>&status=all"><button class="status-tab <?= $statusFilter==='all'?'active':'' ?>">All <span class="tab-count"><?= $cAll ?></span></button></a>
                <a href="?pay_period_id=<?= $selectedPeriod ?>&status=pending"><button class="status-tab <?= $statusFilter==='pending'?'active':'' ?>">Pending <span class="tab-count"><?= $cPend ?></span></button></a>
                <a href="?pay_period_id=<?= $selectedPeriod ?>&status=paid"><button class="status-tab <?= $statusFilter==='paid'?'active':'' ?>">Paid <span class="tab-count"><?= $cPaid ?></span></button></a>
            </div>

            <!-- Table -->
            <div class="card-box">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="section-title mb-0"><?= $currentPeriod?htmlspecialchars(strtoupper($currentPeriod['period_name'])):'NO PERIOD SELECTED' ?></p>
                        <span style="font-size:0.82rem;color:var(--text-muted);"><?= count($payslips) ?> payslip(s)</span>
                    </div>

                    <?php if (empty($payslips)): ?>
                    <div class="text-center py-5" style="color:var(--text-muted)">
                        <i class="bi bi-receipt" style="font-size:2.5rem;display:block;margin-bottom:10px;"></i>
                        <?= !$selectedPeriod?'Select a pay period above.':'Click "Generate Payslips" to create them.' ?>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Staff</th><th>Role</th><th>Hours</th><th>Gross Pay</th><th>Tax</th><th>Super</th><th>Net Pay</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php $gGross=0;$gNet=0;$gTax=0;
                                foreach ($payslips as $p):
                                    $super=round($p['total_pay']*0.11,2); $st=$p['status']??'draft';
                                    $gGross+=$p['total_pay']; $gNet+=$p['net_pay']; $gTax+=$p['tax_amount'];
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="admin-avatar" style="width:30px;height:30px;font-size:0.7rem;"><?= htmlspecialchars($p['fi'].$p['li']) ?></div>
                                            <div><div style="font-weight:600;"><?= htmlspecialchars($p['staff_name']) ?></div><div style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($p['staff_number']) ?></div></div>
                                        </div>
                                    </td>
                                    <td style="font-size:0.85rem;"><?= htmlspecialchars($p['role_name']??'—') ?></td>
                                    <td><?= number_format($p['total_hours'],1) ?>h</td>
                                    <td style="color:#16a34a;font-weight:600;">$<?= number_format($p['total_pay'],2) ?></td>
                                    <td style="color:#dc3545;">$<?= number_format($p['tax_amount'],2) ?></td>
                                    <td style="color:#2563eb;">$<?= number_format($super,2) ?></td>
                                    <td style="font-weight:700;">$<?= number_format($p['net_pay'],2) ?></td>
                                    <td><span class="badge-<?= $st==='paid'?'paid':'pending' ?>"><?= $st==='paid'?'Paid':'Pending' ?></span></td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="payslip_view.php?id=<?= $p['payslip_id'] ?>" target="_blank" class="icon-btn" title="View"><i class="bi bi-eye"></i></a>
                                            <a href="download_payslip.php?id=<?= $p['payslip_id'] ?>" class="icon-btn" title="Download PDF"><i class="bi bi-download"></i></a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <tr style="background:var(--bg-surface);font-weight:700;">
                                    <td colspan="3" style="color:var(--text-muted);">TOTALS</td>
                                    <td style="color:#16a34a;">$<?= number_format($gGross,2) ?></td>
                                    <td style="color:#dc3545;">$<?= number_format($gTax,2) ?></td>
                                    <td></td>
                                    <td>$<?= number_format($gNet,2) ?></td>
                                    <td colspan="2"></td>
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

<!-- NEW PERIOD MODAL -->
<div class="modal-overlay" id="periodModal">
    <div class="modal-card">
        <div class="modal-header">
            <h5 class="modal-title">Create New Pay Period</h5>
            <button class="modal-close" onclick="document.getElementById('periodModal').classList.remove('open')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="create_period">
            <div class="modal-body">
                <div class="form-field"><label>Period Name *</label><input type="text" name="period_name" class="form-input" placeholder="e.g. June 2026 Week 1" required></div>
                <div class="form-row-2">
                    <div class="form-field"><label>Start Date *</label><input type="date" name="period_start" class="form-input" required></div>
                    <div class="form-field"><label>End Date *</label><input type="date" name="period_end" class="form-input" required></div>
                </div>
                <div class="form-field"><label>Pay Date *</label><input type="date" name="pay_date" class="form-input" required></div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-brand" onclick="document.getElementById('periodModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn-brand">Create Period</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('periodModal').addEventListener('click',e=>{ if(e.target===e.currentTarget)e.currentTarget.classList.remove('open'); });
<?php if (isset($_GET['msg'])): ?>setTimeout(()=>{ const t=document.querySelector('.toast-success'); if(t){t.style.opacity='0';setTimeout(()=>t.remove(),300);} },4000);<?php endif; ?>
</script>
</body>
</html>
