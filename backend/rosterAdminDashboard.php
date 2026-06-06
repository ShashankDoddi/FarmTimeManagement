<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit(); }
$level = strtolower($_SESSION['permission_level']??'');
if (!in_array($level,['superadmin','manager','rosteradmin','siteadmin'])) { header('Location: dashboard.php'); exit(); }

$conn    = getConnection();
$adminId = $_SESSION['admin_id'];
$msg = ''; $msgType = '';

// CREATE STAFF
if (isset($_POST['action']) && $_POST['action'] === 'create_staff') {
    $sn=trim($_POST['staff_number']??''); $fn=trim($_POST['first_name']??''); $ln=trim($_POST['last_name']??'');
    $rid=intval($_POST['role_id']??0); $ct=$_POST['contract_type']??'Casual'; $ph=trim($_POST['contact_number']??'');
    $em=trim($_POST['contact_email']??''); $hd=date('Y-m-d');
    if ($sn && $fn && $ln && $rid) {
        $chk=$conn->prepare("SELECT staff_id FROM staff WHERE staff_number=? LIMIT 1");
        $chk->bind_param('s',$sn); $chk->execute(); $chk->store_result();
        if ($chk->num_rows>0) { $msg='Staff number exists!'; $msgType='error'; }
        else {
            $chk->close();
            $stmt=$conn->prepare("INSERT INTO staff (staff_number,first_name,last_name,role_id,contact_number,contact_email,address,hire_date,status,created_by) VALUES (?,?,?,?,?,?,?,?,'Active',?)");
            $stmt->bind_param('sssississi',$sn,$fn,$ln,$rid,$ph,$em,'',$hd,$adminId);
            if ($stmt->execute()) {
                $nid=$conn->insert_id;
                if ($ct) {
                    $cs=$conn->prepare("INSERT INTO contracts (staff_id,contract_type,pay_type,standard_pay_rate,overtime_pay_rate,start_date,standard_weekly_hours,annual_leave_rate,is_active,created_by) VALUES (?,?,'Hourly',25,37.5,?,38,0.0769,1,?)");
                    $cs->bind_param('issi',$nid,$ct,$hd,$adminId); $cs->execute(); $cid=$conn->insert_id; $cs->close();
                    $conn->query("UPDATE staff SET contract_id=$cid WHERE staff_id=$nid");
                }
                $msg="$fn $ln registered successfully!"; $msgType='success';
            }
            $stmt->close();
        }
    } else { $msg='Please fill in all required fields.'; $msgType='error'; }
}

// CREATE ROSTER
if (isset($_POST['action']) && $_POST['action'] === 'create_roster') {
    $sid=intval($_POST['staff_id']??0); $siteid=intval($_POST['site_id']??0);
    $wd=$_POST['work_date']??''; $st=$_POST['start_time']??''; $et=$_POST['end_time']??''; $sft=$_POST['shift_type']??'morning';
    if ($sid && $siteid && $wd && $st && $et) {
        $stmt=$conn->prepare("INSERT INTO roster (staff_id,site_id,admin_id,work_date,shift_type,start_time,end_time,created_by) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('iiissssi',$sid,$siteid,$adminId,$wd,$sft,$st,$et,$adminId);
        if ($stmt->execute()) { $msg='Shift added successfully!'; $msgType='success'; }
        else { $msg='Failed — staff may already be rostered on this date.'; $msgType='error'; }
        $stmt->close();
    } else { $msg='Please fill in all required fields.'; $msgType='error'; }
}

$staffList  = $conn->query("SELECT s.*,r.role_name FROM staff s LEFT JOIN roles r ON s.role_id=r.role_id WHERE LOWER(s.status)='active' ORDER BY s.first_name")->fetch_all(MYSQLI_ASSOC);
$roles      = $conn->query("SELECT role_id,role_name FROM roles ORDER BY role_name")->fetch_all(MYSQLI_ASSOC);
$sites      = $conn->query("SELECT site_id,site_name FROM sites ORDER BY site_name")->fetch_all(MYSQLI_ASSOC);
$weekStart  = date('Y-m-d',strtotime('monday this week'));
$weekEnd    = date('Y-m-d',strtotime('sunday this week'));
$todayRoster= $conn->query("SELECT ro.*,CONCAT(s.first_name,' ',s.last_name) AS staff_name,s.staff_number,r.role_name,si.site_name,LEFT(s.first_name,1) AS fi,LEFT(s.last_name,1) AS li FROM roster ro JOIN staff s ON ro.staff_id=s.staff_id LEFT JOIN roles r ON s.role_id=r.role_id LEFT JOIN sites si ON ro.site_id=si.site_id WHERE ro.work_date=CURDATE() ORDER BY ro.start_time")->fetch_all(MYSQLI_ASSOC);
$weekRoster = $conn->query("SELECT ro.*,CONCAT(s.first_name,' ',s.last_name) AS staff_name,r.role_name,si.site_name,LEFT(s.first_name,1) AS fi,LEFT(s.last_name,1) AS li FROM roster ro JOIN staff s ON ro.staff_id=s.staff_id LEFT JOIN roles r ON s.role_id=r.role_id LEFT JOIN sites si ON ro.site_id=si.site_id WHERE ro.work_date BETWEEN '$weekStart' AND '$weekEnd' ORDER BY ro.work_date,ro.start_time")->fetch_all(MYSQLI_ASSOC);

$last    = $conn->query("SELECT staff_number FROM staff ORDER BY staff_id DESC LIMIT 1")->fetch_assoc();
$nextNum = 'STF-001';
if ($last) { preg_match('/\d+/',$last['staff_number'],$m); $nextNum='STF-'.str_pad((intval($m[0]??0)+1),3,'0',STR_PAD_LEFT); }
$conn->close();
$initials = strtoupper(substr($_SESSION['username'],0,2));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Dashboard — Farm TMS</title>
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
            <a href="rosterAdminDashboard.php" class="nav-link active"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="clockinout.php" class="nav-link"><i class="bi bi-clock"></i> Timesheets</a>
            <a href="exceptions.php" class="nav-link"><i class="bi bi-exclamation-circle"></i> Exceptions</a>
            <span class="nav-section-label">People</span>
            <a href="staff.php" class="nav-link"><i class="bi bi-people"></i> Staff</a>
            <a href="settings.php?tab=roles" class="nav-link"><i class="bi bi-person-badge"></i> Roles</a>
            <span class="nav-section-label">System</span>
            <a href="reports.php" class="nav-link"><i class="bi bi-bar-chart-line"></i> Reports</a>
            <a href="settings.php" class="nav-link"><i class="bi bi-gear"></i> Settings</a>
        </nav>
        <div class="mt-auto p-3" style="border-top:1px solid rgba(255,255,255,0.15)">
            <a href="logout.php" class="nav-link" style="color:rgba(255,100,100,0.85)"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
        </div>
    </aside>

    <div class="main-content">
        <header class="topbar">
            <span class="page-title">Dashboard</span>
            <div class="topbar-right">
                <span class="topbar-date"><i class="bi bi-calendar3 me-1"></i><?= date('D, d M Y') ?></span>
                <span class="badge-status badge-on-time" style="font-size:0.75rem;"><?= ucfirst($level) ?></span>
                <div class="admin-badge"><div class="admin-avatar"><?= $initials ?></div><?= htmlspecialchars($_SESSION['username']) ?></div>
            </div>
        </header>

        <div class="page-body">
            <?php if ($msg): ?>
            <div class="toast-<?= $msgType ?>"><i class="bi bi-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>-fill"></i><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card"><div class="icon-box"><i class="bi bi-people-fill"></i></div><div><div class="stat-value"><?= count($staffList) ?></div><div class="stat-label">Active Staff</div></div></div>
                <div class="stat-card"><div class="icon-box"><i class="bi bi-calendar-check"></i></div><div><div class="stat-value"><?= count($todayRoster) ?></div><div class="stat-label">Shifts Today</div></div></div>
                <div class="stat-card"><div class="icon-box"><i class="bi bi-calendar-week"></i></div><div><div class="stat-value"><?= count($weekRoster) ?></div><div class="stat-label">Shifts This Week</div></div></div>
            </div>

            <!-- Actions -->
            <div class="quick-actions">
                <button class="btn-brand quick-btn" onclick="document.getElementById('staffModal').classList.add('open')"><i class="bi bi-person-plus me-1"></i> Register New Staff</button>
                <button class="btn-outline-brand quick-btn" onclick="document.getElementById('rosterModal').classList.add('open')"><i class="bi bi-calendar-plus me-1"></i> Add Roster Shift</button>
            </div>

            <!-- Today's Roster -->
            <div class="card-box mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="section-title mb-0">Today's Roster — <?= date('l, d M Y') ?></p>
                        <span class="badge-status badge-on-time"><?= count($todayRoster) ?> shifts</span>
                    </div>
                    <?php if (empty($todayRoster)): ?>
                    <div class="text-center py-4" style="color:var(--text-muted)"><i class="bi bi-calendar-x" style="font-size:2rem;display:block;margin-bottom:8px;"></i>No shifts scheduled for today.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Staff</th><th>Role</th><th>Shift</th><th>Site</th><th>Type</th></tr></thead>
                            <tbody>
                                <?php foreach ($todayRoster as $r): ?>
                                <tr>
                                    <td><div class="d-flex align-items-center gap-2"><div class="admin-avatar" style="width:30px;height:30px;font-size:0.7rem;"><?= htmlspecialchars($r['fi'].$r['li']) ?></div><div><div style="font-weight:600;"><?= htmlspecialchars($r['staff_name']) ?></div><div style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($r['staff_number']) ?></div></div></div></td>
                                    <td><?= htmlspecialchars($r['role_name']??'—') ?></td>
                                    <td style="font-weight:600;"><?= date('g:i A',strtotime($r['start_time'])) ?> – <?= date('g:i A',strtotime($r['end_time'])) ?></td>
                                    <td><span class="badge-status badge-leave"><?= htmlspecialchars($r['site_name']??'—') ?></span></td>
                                    <td style="font-size:0.85rem;"><?= ucfirst($r['shift_type']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Week Roster -->
            <div class="card-box mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="section-title mb-0">This Week's Roster</p>
                        <span class="badge-status badge-on-time"><?= count($weekRoster) ?> shifts</span>
                    </div>
                    <?php if (empty($weekRoster)): ?>
                    <div class="text-center py-4" style="color:var(--text-muted)">No shifts this week.</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Staff</th><th>Role</th><th>Date</th><th>Shift</th><th>Site</th></tr></thead>
                            <tbody>
                                <?php foreach ($weekRoster as $r): $isToday=$r['work_date']===date('Y-m-d'); ?>
                                <tr <?= $isToday?'style="background:#f0f9f0;"':''?>>
                                    <td><div class="d-flex align-items-center gap-2"><div class="admin-avatar" style="width:30px;height:30px;font-size:0.7rem;"><?= htmlspecialchars($r['fi'].$r['li']) ?></div><span style="font-weight:600;"><?= htmlspecialchars($r['staff_name']) ?></span></div></td>
                                    <td><?= htmlspecialchars($r['role_name']??'—') ?></td>
                                    <td><?= date('D d M',strtotime($r['work_date'])) ?><?= $isToday?' <span class="badge-status badge-on-time" style="font-size:0.7rem;">Today</span>':'' ?></td>
                                    <td style="font-weight:600;"><?= date('g:i A',strtotime($r['start_time'])) ?> – <?= date('g:i A',strtotime($r['end_time'])) ?></td>
                                    <td><?= htmlspecialchars($r['site_name']??'—') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Staff List -->
            <div class="card-box">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="section-title mb-0">Active Staff</p>
                        <span class="badge-status badge-on-time"><?= count($staffList) ?> staff</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Staff</th><th>Role</th><th>Contact</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($staffList as $s): ?>
                                <tr>
                                    <td><div class="d-flex align-items-center gap-2"><div class="admin-avatar" style="width:30px;height:30px;font-size:0.7rem;"><?= strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1)) ?></div><div><div style="font-weight:600;"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div><div style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($s['staff_number']) ?></div></div></div></td>
                                    <td><?= htmlspecialchars($s['role_name']??'—') ?></td>
                                    <td style="font-size:0.85rem;"><?= htmlspecialchars($s['contact_number']??'—') ?></td>
                                    <td><span class="badge-active">Active</span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- STAFF MODAL -->
<div class="modal-overlay" id="staffModal">
    <div class="modal-card">
        <div class="modal-header"><h5 class="modal-title">Register New Staff</h5><button class="modal-close" onclick="document.getElementById('staffModal').classList.remove('open')"><i class="bi bi-x-lg"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="create_staff">
            <div class="modal-body">
                <div class="form-row-2"><div class="form-field"><label>First Name *</label><input type="text" name="first_name" class="form-input" placeholder="e.g. Sarah" required></div><div class="form-field"><label>Last Name *</label><input type="text" name="last_name" class="form-input" placeholder="e.g. Miller" required></div></div>
                <div class="form-row-2"><div class="form-field"><label>Staff ID *</label><input type="text" name="staff_number" class="form-input" value="<?= $nextNum ?>" required></div><div class="form-field"><label>Email</label><input type="email" name="contact_email" class="form-input" placeholder="name@farm.local"></div></div>
                <div class="form-row-2">
                    <div class="form-field"><label>Role *</label><select name="role_id" class="form-input" required><option value="">Select role...</option><?php foreach ($roles as $r): ?><option value="<?= $r['role_id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-field"><label>Contract Type *</label><select name="contract_type" class="form-input" required><option value="">Select...</option><option value="Full Time">Full Time</option><option value="Part Time">Part Time</option><option value="Casual">Casual</option></select></div>
                </div>
                <div class="form-field"><label>Phone</label><input type="text" name="contact_number" class="form-input" placeholder="0412 345 678"></div>
                <div class="modal-footer"><button type="button" class="btn-outline-brand" onclick="document.getElementById('staffModal').classList.remove('open')">Cancel</button><button type="submit" class="btn-brand"><i class="bi bi-person-plus me-1"></i> Register Staff</button></div>
            </div>
        </form>
    </div>
</div>

<!-- ROSTER MODAL -->
<div class="modal-overlay" id="rosterModal">
    <div class="modal-card">
        <div class="modal-header"><h5 class="modal-title">Add Roster Shift</h5><button class="modal-close" onclick="document.getElementById('rosterModal').classList.remove('open')"><i class="bi bi-x-lg"></i></button></div>
        <form method="POST"><input type="hidden" name="action" value="create_roster">
            <div class="modal-body">
                <div class="form-field"><label>Staff Member *</label><select name="staff_id" class="form-input" required><option value="">Select staff...</option><?php foreach ($staffList as $s): ?><option value="<?= $s['staff_id'] ?>"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?> — <?= htmlspecialchars($s['role_name']??'') ?></option><?php endforeach; ?></select></div>
                <div class="form-row-2"><div class="form-field"><label>Work Date *</label><input type="date" name="work_date" class="form-input" value="<?= date('Y-m-d') ?>" required></div><div class="form-field"><label>Site *</label><select name="site_id" class="form-input" required><option value="">Select site...</option><?php foreach ($sites as $s): ?><option value="<?= $s['site_id'] ?>"><?= htmlspecialchars($s['site_name']) ?></option><?php endforeach; ?></select></div></div>
                <div class="form-row-2"><div class="form-field"><label>Start Time *</label><input type="time" name="start_time" class="form-input" value="07:00" required></div><div class="form-field"><label>End Time *</label><input type="time" name="end_time" class="form-input" value="15:00" required></div></div>
                <div class="form-field"><label>Shift Type</label><select name="shift_type" class="form-input"><option value="morning">Morning</option><option value="afternoon">Afternoon</option></select></div>
                <div class="modal-footer"><button type="button" class="btn-outline-brand" onclick="document.getElementById('rosterModal').classList.remove('open')">Cancel</button><button type="submit" class="btn-brand"><i class="bi bi-calendar-plus me-1"></i> Add Shift</button></div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
['staffModal','rosterModal'].forEach(id=>{ document.getElementById(id).addEventListener('click',e=>{ if(e.target===e.currentTarget)e.currentTarget.classList.remove('open'); }); });
<?php if ($msg): ?>setTimeout(()=>{ const t=document.querySelector('.toast-success,.toast-error'); if(t){t.style.opacity='0';setTimeout(()=>t.remove(),300);} },4000);<?php endif; ?>
</script>
</body>
</html>
