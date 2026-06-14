<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit(); }

$conn    = getConnection();
$adminId = $_SESSION['admin_id'];
$msg = ''; $msgType = '';

// ADD STAFF
if (isset($_POST['action']) && $_POST['action'] === 'add_staff') {
    $sn = trim($_POST['staff_number']??''); $fn = trim($_POST['first_name']??'');
    $ln = trim($_POST['last_name']??''); $rid = intval($_POST['role_id']??0);
    $ct = $_POST['contract_type']??'Casual'; $ph = trim($_POST['contact_number']??'');
    $em = trim($_POST['contact_email']??''); $hd = date('Y-m-d');
    if ($sn && $fn && $ln && $rid) {
        $chk = $conn->prepare("SELECT staff_id FROM staff WHERE staff_number=? LIMIT 1");
        $chk->bind_param('s',$sn); $chk->execute(); $chk->store_result();
        if ($chk->num_rows > 0) { $msg='Staff number already exists!'; $msgType='error'; }
        else {
            $chk->close();
            $defHash = password_hash('123456', PASSWORD_BCRYPT);
            $stmt = $conn->prepare("INSERT INTO staff (staff_number,first_name,last_name,role_id,contact_number,contact_email,address,hire_date,status,password_hash,created_by) VALUES (?,?,?,?,?,?,?,?,'Active',?,?)");
            $addr = '';
            $stmt->bind_param('sssisssssi',$sn,$fn,$ln,$rid,$ph,$em,$addr,$hd,$defHash,$adminId);
            if ($stmt->execute()) {
                $nid = $conn->insert_id;
                if ($ct) {
                    $cs = $conn->prepare("INSERT INTO contracts (staff_id,contract_type,pay_type,standard_pay_rate,overtime_pay_rate,start_date,standard_weekly_hours,annual_leave_rate,is_active,created_by) VALUES (?,?,'Hourly',25.00,37.50,?,38.00,0.0769,1,?)");
                    $cs->bind_param('issi',$nid,$ct,$hd,$adminId); $cs->execute();
                    $cid=$conn->insert_id; $cs->close();
                    $conn->query("UPDATE staff SET contract_id=$cid WHERE staff_id=$nid");
                }
                $msg="$fn $ln added successfully!"; $msgType='success';
            }
            $stmt->close();
        }
    } else { $msg='Please fill in all required fields.'; $msgType='error'; }
}

$search = trim($_GET['search']??''); $rfil = intval($_GET['role_id']??0);
$sfil   = $_GET['status']??'all';
$sql = "SELECT s.*,r.role_name,c.contract_type FROM staff s LEFT JOIN roles r ON s.role_id=r.role_id LEFT JOIN contracts c ON s.contract_id=c.contract_id WHERE 1=1";
if ($search) { $ss=$conn->real_escape_string($search); $sql.=" AND (s.first_name LIKE '%$ss%' OR s.last_name LIKE '%$ss%' OR s.staff_number LIKE '%$ss%')"; }
if ($rfil)   $sql.=" AND s.role_id=$rfil";
if ($sfil!=='all') $sql.=" AND LOWER(s.status)=LOWER('$sfil')";
$sql.=" ORDER BY s.first_name";
$staffList = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

$roles = $conn->query("SELECT role_id,role_name FROM roles ORDER BY role_name")->fetch_all(MYSQLI_ASSOC);
$last  = $conn->query("SELECT staff_number FROM staff ORDER BY staff_id DESC LIMIT 1")->fetch_assoc();
$next  = 'STF-001';
if ($last) { preg_match('/\d+/',$last['staff_number'],$m); $next='STF-'.str_pad((intval($m[0]??0)+1),3,'0',STR_PAD_LEFT); }

$totalActive     = intval($conn->query("SELECT COUNT(*) AS c FROM staff WHERE LOWER(status)='active'")->fetch_assoc()['c']??0);
$totalInactive   = intval($conn->query("SELECT COUNT(*) AS c FROM staff WHERE LOWER(status)='inactive'")->fetch_assoc()['c']??0);
$totalTerminated = intval($conn->query("SELECT COUNT(*) AS c FROM staff WHERE LOWER(status)='terminated'")->fetch_assoc()['c']??0);
$conn->close();
$initials = strtoupper(substr($_SESSION['username'],0,2));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Staff — Farm TMS</title>
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
            <a href="staff.php" class="nav-link active"><i class="bi bi-people"></i> Staff</a>
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
            <span class="page-title">Staff</span>
            <div class="topbar-right">
                <span class="topbar-date"><i class="bi bi-calendar3 me-1"></i><?= date('D, d M Y') ?></span>
                <div class="admin-badge"><div class="admin-avatar"><?= $initials ?></div><?= htmlspecialchars($_SESSION['username']) ?></div>
            </div>
        </header>

        <div class="page-body">
            <?php if ($msg): ?>
            <div class="toast-<?= $msgType ?>"><i class="bi bi-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>-fill"></i><?= htmlspecialchars($msg) ?></div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card"><div class="icon-box"><i class="bi bi-person-check"></i></div><div><div class="stat-value"><?= $totalActive ?></div><div class="stat-label">Active Staff</div></div></div>
                <div class="stat-card"><div class="icon-box"><i class="bi bi-person-dash"></i></div><div><div class="stat-value"><?= $totalInactive ?></div><div class="stat-label">Inactive</div></div></div>
                <div class="stat-card"><div class="icon-box"><i class="bi bi-person-x"></i></div><div><div class="stat-value"><?= $totalTerminated ?></div><div class="stat-label">Terminated</div></div></div>
            </div>

            <div class="card-box">
                <div class="card-body">
                    <!-- Filter bar -->
                    <div class="d-flex flex-wrap gap-3 align-items-end mb-4">
                        <form method="GET" class="d-flex flex-wrap gap-3 flex-grow-1">
                            <div class="filter-group">
                                <label>Search</label>
                                <input type="text" name="search" class="filter-input" placeholder="Name or Staff #" value="<?= htmlspecialchars($search) ?>">
                            </div>
                            <div class="filter-group">
                                <label>Role</label>
                                <select name="role_id" class="filter-input" onchange="this.form.submit()">
                                    <option value="">All Roles</option>
                                    <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r['role_id'] ?>" <?= $rfil==$r['role_id']?'selected':''?>><?= htmlspecialchars($r['role_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="status" class="filter-input" onchange="this.form.submit()">
                                    <option value="all" <?= $sfil==='all'?'selected':''?>>All</option>
                                    <option value="active" <?= $sfil==='active'?'selected':''?>>Active</option>
                                    <option value="inactive" <?= $sfil==='inactive'?'selected':''?>>Inactive</option>
                                    <option value="terminated" <?= $sfil==='terminated'?'selected':''?>>Terminated</option>
                                </select>
                            </div>
                            <div style="align-self:flex-end;"><button type="submit" class="btn-brand">Search</button></div>
                        </form>
                        <div style="align-self:flex-end;">
                            <button class="btn-brand" onclick="document.getElementById('addModal').classList.add('open')">
                                <i class="bi bi-plus-lg"></i> Add Staff Member
                            </button>
                        </div>
                    </div>

                    <?php if (empty($staffList)): ?>
                    <div class="text-center py-5" style="color:var(--text-muted)">
                        <i class="bi bi-people" style="font-size:2.5rem;display:block;margin-bottom:10px;"></i>
                        No staff found.
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Staff</th><th>Role</th><th>Contract</th><th>Contact</th><th>Hire Date</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($staffList as $s):
                                    $st = strtolower($s['status']??'active');
                                    $ct = strtolower(str_replace(' ','',$s['contract_type']??''));
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="admin-avatar" style="width:30px;height:30px;font-size:0.7rem;">
                                                <?= strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1)) ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:600;"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
                                                <div style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($s['staff_number']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($s['role_name']??'—') ?></td>
                                    <td><span class="badge-contract badge-<?= $ct ?>"><?= htmlspecialchars($s['contract_type']??'—') ?></span></td>
                                    <td>
                                        <div><?= htmlspecialchars($s['contact_number']??'—') ?></div>
                                        <div style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($s['contact_email']??'') ?></div>
                                    </td>
                                    <td style="font-size:0.85rem;"><?= $s['hire_date']?date('d M Y',strtotime($s['hire_date'])):'—' ?></td>
                                    <td><span class="badge-<?= $st === 'active' ? 'active' : 'inactive' ?>"><?= ucfirst($st) ?></span></td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="staff.php?edit=<?= $s['staff_id'] ?>" class="icon-btn" title="Edit"><i class="bi bi-pencil"></i></a>
                                            <a href="staff.php?delete=<?= $s['staff_id'] ?>" class="icon-btn icon-btn-danger" title="Terminate"
                                               onclick="return confirm('Terminate <?= htmlspecialchars($s['first_name']) ?>?')">
                                               <i class="bi bi-person-x"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-2" style="font-size:0.82rem;color:var(--text-muted)">
                        Showing <?= count($staffList) ?> staff member(s)
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ADD STAFF MODAL -->
<div class="modal-overlay" id="addModal">
    <div class="modal-card">
        <div class="modal-header">
            <h5 class="modal-title">Add Staff Member</h5>
            <button class="modal-close" onclick="document.getElementById('addModal').classList.remove('open')">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_staff">
            <div class="modal-body">
                <div class="form-row-2">
                    <div class="form-field">
                        <label>First Name <span class="required">*</span></label>
                        <input type="text" name="first_name" class="form-input" placeholder="e.g. Sarah" required>
                    </div>
                    <div class="form-field">
                        <label>Last Name <span class="required">*</span></label>
                        <input type="text" name="last_name" class="form-input" placeholder="e.g. Miller" required>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-field">
                        <label>Staff ID <span class="required">*</span></label>
                        <input type="text" name="staff_number" class="form-input" value="<?= $next ?>" placeholder="e.g. STF-006" required>
                    </div>
                    <div class="form-field">
                        <label>Email</label>
                        <input type="email" name="contact_email" class="form-input" placeholder="e.g. name@farm.local">
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-field">
                        <label>Role <span class="required">*</span></label>
                        <select name="role_id" class="form-input" required>
                            <option value="">Select role...</option>
                            <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['role_id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label>Contract Type <span class="required">*</span></label>
                        <select name="contract_type" class="form-input" required>
                            <option value="">Select type...</option>
                            <option value="Full Time">Full Time</option>
                            <option value="Part Time">Part Time</option>
                            <option value="Casual">Casual</option>
                        </select>
                    </div>
                </div>
                <div class="form-field">
                    <label>Phone</label>
                    <input type="text" name="contact_number" class="form-input" placeholder="e.g. 0412 345 678">
                </div>
                <div class="form-field">
                    <label>Notes</label>
                    <textarea name="notes" class="form-input" rows="2" placeholder="Any additional notes..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-brand" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn-brand"><i class="bi bi-person-plus me-1"></i> Add Staff Member</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('addModal').addEventListener('click', e => { if(e.target===e.currentTarget) e.currentTarget.classList.remove('open'); });
<?php if ($msg): ?>setTimeout(()=>{ const t=document.querySelector('.toast-success,.toast-error'); if(t){t.style.opacity='0';setTimeout(()=>t.remove(),300);} },4000);<?php endif; ?>
</script>
</body>
</html>
