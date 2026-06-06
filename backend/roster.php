<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit(); }

$conn    = getConnection();
$adminId = $_SESSION['admin_id'];
$msg = ''; $msgType = '';

// ADD SHIFT
if (isset($_POST['action']) && $_POST['action'] === 'add_shift') {
    $staffId   = intval($_POST['staff_id']??0);
    $siteId    = intval($_POST['site_id']??0);
    $workDate  = $_POST['work_date']??'';
    $startTime = $_POST['start_time']??'';
    $endTime   = $_POST['end_time']??'';
    $shiftType = $_POST['shift_type']??'morning';
    if ($staffId && $siteId && $workDate && $startTime && $endTime) {
        $stmt = $conn->prepare("INSERT INTO roster (staff_id,site_id,admin_id,work_date,shift_type,start_time,end_time,created_by) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param('iiissssi',$staffId,$siteId,$adminId,$workDate,$shiftType,$startTime,$endTime,$adminId);
        if ($stmt->execute()) { $msg='Shift added successfully!'; $msgType='success'; }
        else { $msg='Failed — staff may already be rostered on this date.'; $msgType='error'; }
        $stmt->close();
    } else { $msg='Please fill in all required fields.'; $msgType='error'; }
    header("Location: roster.php?msg=$msgType"); exit();
}

// DELETE SHIFT
if (isset($_GET['delete'])) {
    $rid = intval($_GET['delete']);
    $conn->query("DELETE FROM roster WHERE roster_id=$rid");
    header('Location: roster.php?msg=deleted'); exit();
}

// Week navigation
$weekOffset = intval($_GET['week']??0);
$weekStart  = date('Y-m-d', strtotime("monday this week +{$weekOffset} weeks"));
$weekEnd    = date('Y-m-d', strtotime("sunday this week +{$weekOffset} weeks"));
$days       = [];
for ($i=0; $i<7; $i++) { $days[] = date('Y-m-d', strtotime($weekStart." +$i days")); }

// Load roster for week
$rosterRes = $conn->query("
    SELECT ro.*,CONCAT(s.first_name,' ',s.last_name) AS staff_name,
           s.staff_number,r.role_name,si.site_name,
           LEFT(s.first_name,1) AS fi,LEFT(s.last_name,1) AS li
    FROM roster ro
    JOIN staff s ON ro.staff_id=s.staff_id
    LEFT JOIN roles r ON s.role_id=r.role_id
    LEFT JOIN sites si ON ro.site_id=si.site_id
    WHERE ro.work_date BETWEEN '$weekStart' AND '$weekEnd'
    ORDER BY ro.work_date, ro.start_time
");
$rosterList = $rosterRes ? $rosterRes->fetch_all(MYSQLI_ASSOC) : [];

// Group by date
$byDate = [];
foreach ($rosterList as $r) { $byDate[$r['work_date']][] = $r; }

$staffList = $conn->query("SELECT staff_id,first_name,last_name,role_id FROM staff WHERE LOWER(status)='active' ORDER BY first_name")->fetch_all(MYSQLI_ASSOC);
$roles     = $conn->query("SELECT role_id,role_name FROM roles ORDER BY role_name")->fetch_all(MYSQLI_ASSOC);
$sites     = $conn->query("SELECT site_id,site_name FROM sites ORDER BY site_name")->fetch_all(MYSQLI_ASSOC);

$totalThisWeek = count($rosterList);
$conn->close();
$initials = strtoupper(substr($_SESSION['username'],0,2));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Roster — Farm TMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet"/>
    <link rel="stylesheet" href="adminStyle.css"/>
    <style>
        .day-col { min-width: 140px; }
        .shift-chip { background: var(--brand-alpha-12); border: 1px solid rgba(105,108,43,0.2); border-radius: 8px; padding: 8px 10px; margin-bottom: 6px; font-size: 0.82rem; }
        .shift-chip .shift-name { font-weight: 600; color: var(--text-primary); }
        .shift-chip .shift-time { color: var(--text-muted); font-size: 0.78rem; }
        .week-nav { display: flex; align-items: center; gap: 12px; }
        .week-nav a { color: var(--brand); font-weight: 600; text-decoration: none; }
        .today-col { background: rgba(105,108,43,0.04); }
        .day-header { font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); padding: 8px 10px; }
        .day-header.today { color: var(--brand); }
        .day-date { font-size: 1.1rem; font-weight: 700; color: var(--text-primary); }
        .day-date.today { color: var(--brand); }
        .empty-day { color: var(--text-faint); font-size: 0.82rem; padding: 8px 10px; }
    </style>
</head>
<body>
<div class="dashboard-wrapper">
    <aside class="sidebar">
        <div class="brand"><i class="bi bi-clock-history me-2"></i>Farm TMS</div>
        <nav class="nav flex-column">
            <span class="nav-section-label">Main</span>
            <a href="dashboard.php" class="nav-link"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="roster.php" class="nav-link active"><i class="bi bi-calendar3"></i> Roster</a>
            <a href="clockinout.php" class="nav-link"><i class="bi bi-clock"></i> Timesheets</a>
            <a href="exceptions.php" class="nav-link"><i class="bi bi-exclamation-circle"></i> Exceptions</a>
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
            <span class="page-title">Roster</span>
            <div class="topbar-right">
                <span class="topbar-date"><i class="bi bi-calendar3 me-1"></i><?= date('D, d M Y') ?></span>
                <div class="admin-badge"><div class="admin-avatar"><?= $initials ?></div><?= htmlspecialchars($_SESSION['username']) ?></div>
            </div>
        </header>

        <div class="page-body">
            <?php if ($msg || isset($_GET['msg'])): $m=$msg?:$_GET['msg']; ?>
            <div class="toast-<?= $m==='success'||$m==='deleted'?'success':'error' ?>">
                <i class="bi bi-<?= $m==='success'||$m==='deleted'?'check-circle':'exclamation-circle' ?>-fill"></i>
                <?= $m==='deleted'?'Shift removed.':(htmlspecialchars($msg)?:'Done!') ?>
            </div>
            <?php endif; ?>

            <!-- Controls -->
            <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
                <div class="week-nav">
                    <a href="?week=<?= $weekOffset-1 ?>"><i class="bi bi-chevron-left"></i> Prev</a>
                    <span style="font-weight:700;font-size:1rem;">
                        <?= date('d M', strtotime($weekStart)) ?> – <?= date('d M Y', strtotime($weekEnd)) ?>
                    </span>
                    <a href="?week=<?= $weekOffset+1 ?>">Next <i class="bi bi-chevron-right"></i></a>
                    <?php if ($weekOffset !== 0): ?>
                    <a href="?week=0" style="color:var(--text-muted);font-size:0.85rem;">Today</a>
                    <?php endif; ?>
                </div>
                <div class="d-flex gap-2">
                    <span style="font-size:0.85rem;color:var(--text-muted);align-self:center;"><?= $totalThisWeek ?> shift(s) this week</span>
                    <button class="btn-brand" onclick="document.getElementById('addShiftModal').classList.add('open')">
                        <i class="bi bi-plus-lg me-1"></i> Add Shift
                    </button>
                </div>
            </div>

            <!-- Weekly Calendar -->
            <div class="card-box mb-4">
                <div style="overflow-x:auto;">
                    <table style="width:100%;border-collapse:collapse;min-width:900px;">
                        <thead>
                            <tr>
                                <?php foreach ($days as $day):
                                    $isToday = $day === date('Y-m-d');
                                ?>
                                <th class="day-col <?= $isToday?'today-col':'' ?>" style="border-bottom:1px solid var(--border-light);vertical-align:top;padding:0;">
                                    <div class="day-header <?= $isToday?'today':'' ?>">
                                        <?= date('D', strtotime($day)) ?>
                                        <div class="day-date <?= $isToday?'today':'' ?>"><?= date('d', strtotime($day)) ?></div>
                                    </div>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <?php foreach ($days as $day): $isToday=$day===date('Y-m-d'); ?>
                                <td class="<?= $isToday?'today-col':'' ?>" style="vertical-align:top;padding:8px;border-right:1px solid var(--border-light);">
                                    <?php if (!empty($byDate[$day])): ?>
                                        <?php foreach ($byDate[$day] as $r): ?>
                                        <div class="shift-chip">
                                            <div class="shift-name"><?= htmlspecialchars($r['fi'].$r['li']) ?> <?= htmlspecialchars(explode(' ',$r['staff_name'])[0]) ?></div>
                                            <div class="shift-time"><?= date('g:i A',strtotime($r['start_time'])) ?>–<?= date('g:i A',strtotime($r['end_time'])) ?></div>
                                            <div style="font-size:0.74rem;color:var(--text-muted);"><?= htmlspecialchars($r['site_name']??'') ?></div>
                                            <a href="roster.php?delete=<?= $r['roster_id'] ?>&week=<?= $weekOffset ?>"
                                               style="font-size:0.72rem;color:#dc3545;"
                                               onclick="return confirm('Remove this shift?')">✕ remove</a>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="empty-day">—</div>
                                    <?php endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Shift List -->
            <div class="card-box">
                <div class="card-body">
                    <p class="section-title">All Shifts This Week</p>
                    <?php if (empty($rosterList)): ?>
                    <div class="text-center py-4" style="color:var(--text-muted)">
                        <i class="bi bi-calendar-x" style="font-size:2rem;display:block;margin-bottom:8px;"></i>
                        No shifts scheduled for this week.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Staff</th><th>Date</th><th>Shift</th><th>Site</th><th>Type</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($rosterList as $r): $isToday=$r['work_date']===date('Y-m-d'); ?>
                                <tr <?= $isToday?'style="background:rgba(105,108,43,0.04);"':''?>>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="admin-avatar" style="width:28px;height:28px;font-size:0.7rem;"><?= htmlspecialchars($r['fi'].$r['li']) ?></div>
                                            <div><div style="font-weight:600;"><?= htmlspecialchars($r['staff_name']) ?></div><div style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($r['role_name']??'') ?></div></div>
                                        </div>
                                    </td>
                                    <td>
                                        <?= date('D d M', strtotime($r['work_date'])) ?>
                                        <?php if ($isToday): ?><span class="badge-status badge-on-time" style="font-size:0.7rem;">Today</span><?php endif; ?>
                                    </td>
                                    <td style="font-weight:600;"><?= date('g:i A',strtotime($r['start_time'])) ?> – <?= date('g:i A',strtotime($r['end_time'])) ?></td>
                                    <td><?= htmlspecialchars($r['site_name']??'—') ?></td>
                                    <td style="font-size:0.85rem;"><?= ucfirst($r['shift_type']) ?></td>
                                    <td>
                                        <a href="roster.php?delete=<?= $r['roster_id'] ?>&week=<?= $weekOffset ?>"
                                           class="icon-btn icon-btn-danger"
                                           onclick="return confirm('Remove this shift?')"
                                           title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </a>
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

<!-- ADD SHIFT MODAL -->
<div class="modal-overlay" id="addShiftModal">
    <div class="modal-card">
        <div class="modal-header">
            <h5 class="modal-title">Add Roster Shift</h5>
            <button class="modal-close" onclick="document.getElementById('addShiftModal').classList.remove('open')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_shift">
            <div class="modal-body">
                <div class="form-field">
                    <label>Staff Member *</label>
                    <select name="staff_id" class="form-input" required>
                        <option value="">Select staff member...</option>
                        <?php foreach ($staffList as $s): ?>
                        <option value="<?= $s['staff_id'] ?>"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-row-2">
                    <div class="form-field">
                        <label>Work Date *</label>
                        <input type="date" name="work_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-field">
                        <label>Site *</label>
                        <select name="site_id" class="form-input" required>
                            <option value="">Select site...</option>
                            <?php foreach ($sites as $s): ?>
                            <option value="<?= $s['site_id'] ?>"><?= htmlspecialchars($s['site_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-field">
                        <label>Start Time *</label>
                        <input type="time" name="start_time" class="form-input" value="07:00" required>
                    </div>
                    <div class="form-field">
                        <label>End Time *</label>
                        <input type="time" name="end_time" class="form-input" value="15:00" required>
                    </div>
                </div>
                <div class="form-field">
                    <label>Shift Type</label>
                    <select name="shift_type" class="form-input">
                        <option value="morning">Morning</option>
                        <option value="afternoon">Afternoon</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-brand" onclick="document.getElementById('addShiftModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn-brand"><i class="bi bi-calendar-plus me-1"></i> Add Shift</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('addShiftModal').addEventListener('click',e=>{ if(e.target===e.currentTarget)e.currentTarget.classList.remove('open'); });
<?php if ($msg): ?>setTimeout(()=>{ const t=document.querySelector('.toast-success,.toast-error'); if(t){t.style.opacity='0';setTimeout(()=>t.remove(),300);} },4000);<?php endif; ?>
</script>
</body>
</html>
