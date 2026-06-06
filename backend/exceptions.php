<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit(); }

$conn    = getConnection();
$adminId = $_SESSION['admin_id'];

// Clock out
if (isset($_POST['action']) && $_POST['action'] === 'clock_out') {
    $aid = intval($_POST['attendance_id']);
    $conn->query("UPDATE attendance SET clock_out=NOW(),clock_out_method='manual' WHERE attendance_id=$aid AND clock_out IS NULL");
    header('Location: exceptions.php?msg=clocked_out'); exit();
}

// Resolve exception
if (isset($_POST['action']) && $_POST['action'] === 'resolve') {
    $eid = intval($_POST['exception_id']);
    $stmt = $conn->prepare("UPDATE exceptions SET status='resolved',authorised_by=? WHERE exception_id=?");
    $stmt->bind_param('ii',$adminId,$eid); $stmt->execute(); $stmt->close();
    header('Location: exceptions.php?msg=resolved'); exit();
}

function sc($c,$s){ $r=$c->query($s); return($r&&$r->num_rows>0)?intval($r->fetch_assoc()['c']):0; }
$openEx    = sc($conn,"SELECT COUNT(*) AS c FROM exceptions WHERE status='open'");
$curClocked= sc($conn,"SELECT COUNT(*) AS c FROM attendance WHERE DATE(clock_in)=CURDATE() AND clock_out IS NULL");
$resToday  = sc($conn,"SELECT COUNT(*) AS c FROM exceptions WHERE status='resolved' AND DATE(updated_at)=CURDATE()");
$totalLog  = sc($conn,"SELECT COUNT(*) AS c FROM exceptions");

$search = trim($_GET['search']??''); $typeF = $_GET['type']??'all';
$cWhere = "WHERE DATE(a.clock_in)=CURDATE() AND a.clock_out IS NULL";
if ($search) { $ss=$conn->real_escape_string($search); $cWhere.=" AND (s.first_name LIKE '%$ss%' OR s.last_name LIKE '%$ss%' OR s.staff_number LIKE '%$ss%')"; }

$clockedRes = $conn->query("SELECT a.attendance_id,a.clock_in,a.attendance_status,CONCAT(s.first_name,' ',s.last_name) AS staff_name,s.staff_number,LEFT(s.first_name,1) AS fi,LEFT(s.last_name,1) AS li,r.role_name,ro.start_time,ro.end_time,si.site_name,TIMESTAMPDIFF(MINUTE,a.clock_in,NOW()) AS mins FROM attendance a JOIN staff s ON a.staff_id=s.staff_id LEFT JOIN roles r ON s.role_id=r.role_id LEFT JOIN roster ro ON a.roster_id=ro.roster_id LEFT JOIN sites si ON ro.site_id=si.site_id $cWhere ORDER BY a.clock_in");
$clockedIn = $clockedRes ? $clockedRes->fetch_all(MYSQLI_ASSOC) : [];

$eWhere = "WHERE 1=1";
if ($search) { $ss=$conn->real_escape_string($search); $eWhere.=" AND (s.first_name LIKE '%$ss%' OR s.last_name LIKE '%$ss%')"; }
if ($typeF!=='all') { $tf=$conn->real_escape_string($typeF); $eWhere.=" AND e.exception_type='$tf'"; }
$excRes = $conn->query("SELECT e.*,CONCAT(s.first_name,' ',s.last_name) AS staff_name,LEFT(s.first_name,1) AS fi,LEFT(s.last_name,1) AS li,ad.username AS auth_name FROM exceptions e JOIN staff s ON e.staff_id=s.staff_id LEFT JOIN admin ad ON e.authorised_by=ad.admin_id $eWhere ORDER BY e.created_at DESC LIMIT 50");
$exceptions = $excRes ? $excRes->fetch_all(MYSQLI_ASSOC) : [];
$conn->close();

function fmtMins($m){ $h=floor($m/60); $mn=$m%60; return $h>0?"{$h}h {$mn}m":"{$mn}m"; }
$initials = strtoupper(substr($_SESSION['username'],0,2));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Exceptions — Farm TMS</title>
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
            <a href="exceptions.php" class="nav-link active"><i class="bi bi-exclamation-circle"></i> Exceptions</a>
            <span class="nav-section-label">People</span>
            <a href="staff.php" class="nav-link"><i class="bi bi-people"></i> Staff</a>
            <a href="settings.php?tab=roles" class="nav-link"><i class="bi bi-person-badge"></i> Roles</a>
            <span class="nav-section-label">System</span>
            <a href="reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reports</a>
            <a href="payslips.php" class="nav-link"><i class="bi bi-receipt"></i> Payslips</a>
            <a href="settings.php" class="nav-link"><i class="bi bi-gear"></i> Settings</a>
        </nav>
        <div class="mt-auto p-3" style="border-top:1px solid rgba(255,255,255,0.15)">
            <a href="logout.php" class="nav-link" style="color:rgba(255,100,100,0.85)"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
        </div>
    </aside>

    <div class="main-content">
        <header class="topbar">
            <span class="page-title">Exceptions</span>
            <div class="topbar-right">
                <span class="topbar-date"><i class="bi bi-calendar3 me-1"></i><?= date('D, d M Y') ?></span>
                <div class="admin-badge"><div class="admin-avatar"><?= $initials ?></div><?= htmlspecialchars($_SESSION['username']) ?></div>
            </div>
        </header>

        <div class="page-body">
            <?php if (isset($_GET['msg'])): ?>
            <div class="toast-success"><i class="bi bi-check-circle-fill"></i>
                <?= $_GET['msg']==='clocked_out'?'Staff clocked out successfully.':'Exception resolved.' ?>
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card"><div class="icon-box"><i class="bi bi-exclamation-triangle-fill"></i></div><div><div class="stat-value"><?= $openEx ?></div><div class="stat-label">Open Exceptions</div></div></div>
                <div class="stat-card"><div class="icon-box"><i class="bi bi-person-check-fill"></i></div><div><div class="stat-value"><?= $curClocked ?></div><div class="stat-label">Currently Clocked In</div></div></div>
                <div class="stat-card"><div class="icon-box"><i class="bi bi-check-circle-fill"></i></div><div><div class="stat-value"><?= $resToday ?></div><div class="stat-label">Resolved Today</div></div></div>
                <div class="stat-card"><div class="icon-box"><i class="bi bi-clipboard-data-fill"></i></div><div><div class="stat-value"><?= $totalLog ?></div><div class="stat-label">Total Logged</div></div></div>
            </div>

            <!-- Search -->
            <div class="card-box mb-4">
                <div class="card-body">
                    <p class="section-title mb-1"><i class="bi bi-clock-history me-1"></i> Manual Early Clock-Out</p>
                    <p style="font-size:0.85rem;color:var(--text-muted);margin-bottom:14px;">Search for a currently clocked-in employee and clock them out early with a reason.</p>
                    <form method="GET" class="d-flex gap-3">
                        <div class="filter-group flex-grow-1">
                            <label>Search Staff (Name or Staff #)</label>
                            <input type="text" name="search" class="filter-input" placeholder="e.g. Sarah Miller or FT-001" value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <input type="hidden" name="type" value="<?= htmlspecialchars($typeF) ?>">
                        <div style="align-self:flex-end;"><button type="submit" class="btn-brand"><i class="bi bi-search me-1"></i> Search</button></div>
                    </form>
                </div>
            </div>

            <!-- Currently Clocked In -->
            <div class="card-box mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="section-title mb-0"><i class="bi bi-people me-1"></i> Currently Clocked In</p>
                        <span class="badge-status badge-on-time"><?= count($clockedIn) ?> staff</span>
                    </div>
                    <?php if (empty($clockedIn)): ?>
                    <div class="text-center py-4" style="color:var(--text-muted)"><i class="bi bi-person-check" style="font-size:2rem;display:block;margin-bottom:8px;"></i>No staff currently clocked in.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Staff</th><th>Role</th><th>Rostered Shift</th><th>Clocked In</th><th>Duration</th><th>Location</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($clockedIn as $row):
                                    $isLate = $row['start_time'] && strtotime($row['clock_in']) > strtotime(date('Y-m-d').' '.$row['start_time'])+300;
                                    $lateMins = $isLate ? round((strtotime($row['clock_in'])-strtotime(date('Y-m-d').' '.$row['start_time']))/60) : 0;
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="admin-avatar" style="width:30px;height:30px;font-size:0.7rem;"><?= htmlspecialchars($row['fi'].$row['li']) ?></div>
                                            <div><div style="font-weight:600;"><?= htmlspecialchars($row['staff_name']) ?></div><div style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($row['staff_number']) ?></div></div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($row['role_name']??'—') ?></td>
                                    <td><?= $row['start_time']?date('H:i',strtotime($row['start_time'])).' – '.date('H:i',strtotime($row['end_time'])):'—' ?></td>
                                    <td style="color:var(--brand);font-weight:600;"><?= date('H:i',strtotime($row['clock_in'])) ?></td>
                                    <td><?= fmtMins($row['mins']) ?></td>
                                    <td><?= htmlspecialchars($row['site_name']??'—') ?></td>
                                    <td><span class="badge-status <?= $isLate?'badge-late':'badge-on-time' ?>"><?= $isLate?"Late {$lateMins}m":'On Time' ?></span></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="clock_out">
                                            <input type="hidden" name="attendance_id" value="<?= $row['attendance_id'] ?>">
                                            <button type="submit" class="btn-brand" style="padding:0.3rem 0.8rem;font-size:0.8rem;"
                                                onclick="return confirm('Clock out <?= htmlspecialchars($row['staff_name']) ?> now?')">
                                                <i class="bi bi-box-arrow-right"></i> Clock Out
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Exception Log -->
            <div class="card-box">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="section-title mb-0"><i class="bi bi-journal-text me-1"></i> Exception Log</p>
                        <form method="GET" class="d-flex gap-2">
                            <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                            <select name="type" class="filter-input" style="min-width:140px;" onchange="this.form.submit()">
                                <option value="all" <?= $typeF==='all'?'selected':''?>>All Types</option>
                                <option value="unrostered" <?= $typeF==='unrostered'?'selected':''?>>Unrostered</option>
                                <option value="no_clock_out" <?= $typeF==='no_clock_out'?'selected':''?>>No Clock-Out</option>
                                <option value="early_clock_out" <?= $typeF==='early_clock_out'?'selected':''?>>Early Clock-Out</option>
                                <option value="late_clock_in" <?= $typeF==='late_clock_in'?'selected':''?>>Late Clock-In</option>
                            </select>
                        </form>
                    </div>
                    <?php if (empty($exceptions)): ?>
                    <div class="text-center py-4" style="color:var(--text-muted)"><i class="bi bi-journal-check" style="font-size:2rem;display:block;margin-bottom:8px;"></i>No exceptions logged yet.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Staff</th><th>Date</th><th>Type</th><th>Rostered End</th><th>Actual Clock-Out</th><th>Reason</th><th>Authorised By</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach ($exceptions as $e):
                                    $typeLabel = match($e['exception_type']) {
                                        'unrostered'=>['Unrostered','#dbeafe','#1e40af'],
                                        'no_clock_out'=>['No Clock-Out','#fff3cd','#856404'],
                                        'early_clock_out'=>['Early Clock-Out','#fee2e2','#991b1b'],
                                        'late_clock_in'=>['Late Clock-In','#fff3cd','#856404'],
                                        default=>[ucfirst(str_replace('_',' ',$e['exception_type'])),'#f3f4f6','#374151']
                                    };
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="admin-avatar" style="width:30px;height:30px;font-size:0.7rem;"><?= htmlspecialchars($e['fi'].$e['li']) ?></div>
                                            <?= htmlspecialchars($e['staff_name']) ?>
                                        </div>
                                    </td>
                                    <td style="font-size:0.85rem;"><?= date('d M Y',strtotime($e['created_at'])) ?></td>
                                    <td><span class="badge-status" style="background:<?= $typeLabel[1] ?>;color:<?= $typeLabel[2] ?>;"><?= $typeLabel[0] ?></span></td>
                                    <td><?= $e['rostered_end_time']?date('H:i',strtotime($e['rostered_end_time'])):'—' ?></td>
                                    <td><?= $e['actual_clock_out']?date('H:i',strtotime($e['actual_clock_out'])):'—' ?></td>
                                    <td style="font-size:0.85rem;"><?= htmlspecialchars($e['reason']??'—') ?></td>
                                    <td style="font-size:0.85rem;"><?= htmlspecialchars($e['auth_name']??'—') ?></td>
                                    <td>
                                        <?php if (($e['status']??'open')==='open'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="resolve">
                                            <input type="hidden" name="exception_id" value="<?= $e['exception_id'] ?>">
                                            <button type="submit" class="btn-brand" style="padding:0.3rem 0.8rem;font-size:0.8rem;background:#16a34a;"
                                                onclick="return confirm('Mark as resolved?')">✓ Resolve</button>
                                        </form>
                                        <?php else: ?>
                                        <span style="color:#16a34a;font-size:0.82rem;font-weight:600;">✓ Resolved</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
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
<script>
<?php if (isset($_GET['msg'])): ?>
setTimeout(()=>{ const t=document.querySelector('.toast-success'); if(t){t.style.opacity='0';setTimeout(()=>t.remove(),300);} },3000);
<?php endif; ?>
</script>
</body>
</html>
