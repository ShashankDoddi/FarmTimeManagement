<?php
session_start();
require_once 'config/database.php';
if (!isset($_SESSION['admin_id'])) { header('Location: login.php'); exit(); }
if (strtolower($_SESSION['permission_level']??'') !== 'superadmin') { header('Location: dashboard.php'); exit(); }

$conn    = getConnection();
$adminId = $_SESSION['admin_id'];
$msg = ''; $msgType = '';

// CREATE ADMIN
if (isset($_POST['action']) && $_POST['action'] === 'create_admin') {
    $un=trim($_POST['username']??''); $em=trim($_POST['email']??''); $pw=trim($_POST['password']??'');
    $pl=$_POST['permission_level']??'manager'; $sid=intval($_POST['site_id']??1); $ph=trim($_POST['contact_number']??'');
    if ($un && $em && $pw) {
        $chk=$conn->prepare("SELECT admin_id FROM admin WHERE username=? OR email=? LIMIT 1");
        $chk->bind_param('ss',$un,$em); $chk->execute(); $chk->store_result();
        if ($chk->num_rows>0) { $msg='Username or email already exists.'; $msgType='error'; }
        else {
            $chk->close(); $hash=password_hash($pw,PASSWORD_BCRYPT);
            $stmt=$conn->prepare("INSERT INTO admin (site_id,username,password_hash,permission_level,contact_number,email,status) VALUES (?,?,?,?,?,?,'Active')");
            $stmt->bind_param('isssss',$sid,$un,$hash,$pl,$ph,$em);
            if ($stmt->execute()) { $msg="Admin <strong>$un</strong> created!"; $msgType='success'; }
            $stmt->close();
        }
    } else { $msg='Please fill in all required fields.'; $msgType='error'; }
}

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
            $defHash = password_hash('123456', PASSWORD_BCRYPT);
            $stmt=$conn->prepare("INSERT INTO staff (staff_number,first_name,last_name,role_id,contact_number,contact_email,address,hire_date,status,password_hash,created_by) VALUES (?,?,?,?,?,?,?,?,'Active',?,?)");
            $addr = '';
            $stmt->bind_param('sssisssssi',$sn,$fn,$ln,$rid,$ph,$em,$addr,$hd,$defHash,$adminId);
            if ($stmt->execute()) {
                $nid=$conn->insert_id;
                if ($ct) {
                    $cs=$conn->prepare("INSERT INTO contracts (staff_id,contract_type,pay_type,standard_pay_rate,overtime_pay_rate,start_date,standard_weekly_hours,annual_leave_rate,is_active,created_by) VALUES (?,?,'Hourly',25,37.5,?,38,0.0769,1,?)");
                    $cs->bind_param('issi',$nid,$ct,$hd,$adminId); $cs->execute(); $cid=$conn->insert_id; $cs->close();
                    $conn->query("UPDATE staff SET contract_id=$cid WHERE staff_id=$nid");
                }
                $msg="$fn $ln registered!"; $msgType='success';
            }
            $stmt->close();
        }
    } else { $msg='Please fill in all required fields.'; $msgType='error'; }
}

$admins    = $conn->query("SELECT a.*,s.site_name FROM admin a LEFT JOIN sites s ON a.site_id=s.site_id ORDER BY a.created_at DESC")->fetch_all(MYSQLI_ASSOC);
$staffList = $conn->query("SELECT s.*,r.role_name,c.contract_type FROM staff s LEFT JOIN roles r ON s.role_id=r.role_id LEFT JOIN contracts c ON s.contract_id=c.contract_id WHERE LOWER(s.status)='active' ORDER BY s.first_name")->fetch_all(MYSQLI_ASSOC);
$roles     = $conn->query("SELECT role_id,role_name FROM roles ORDER BY role_name")->fetch_all(MYSQLI_ASSOC);
$sites     = $conn->query("SELECT site_id,site_name FROM sites ORDER BY site_name")->fetch_all(MYSQLI_ASSOC);
$last      = $conn->query("SELECT staff_number FROM staff ORDER BY staff_id DESC LIMIT 1")->fetch_assoc();
$nextNum   = 'STF-001';
if ($last) { preg_match('/\d+/',$last['staff_number'],$m); $nextNum='STF-'.str_pad((intval($m[0]??0)+1),3,'0',STR_PAD_LEFT); }
$conn->close();
$initials = strtoupper(substr($_SESSION['username'],0,2));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>Admin Management — Farm TMS</title>
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
            <a href="adminDashboard.php" class="nav-link active"><i class="bi bi-speedometer2"></i> Dashboard</a>
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
            <span class="page-title">Admin Management</span>
            <div class="topbar-right">
                <span class="topbar-date"><i class="bi bi-calendar3 me-1"></i><?= date('D, d M Y') ?></span>
                <div class="admin-badge"><div class="admin-avatar"><?= $initials ?></div><?= htmlspecialchars($_SESSION['username']) ?></div>
            </div>
        </header>

        <div class="page-body">
            <?php if ($msg): ?>
            <div class="toast-<?= $msgType ?>"><i class="bi bi-<?= $msgType==='success'?'check-circle':'exclamation-circle' ?>-fill"></i><?= $msg ?></div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-row">
                <div class="stat-card"><div class="icon-box"><i class="bi bi-shield-check"></i></div><div><div class="stat-value"><?= count($admins) ?></div><div class="stat-label">Total Admins</div></div></div>
                <div class="stat-card"><div class="icon-box"><i class="bi bi-people-fill"></i></div><div><div class="stat-value"><?= count($staffList) ?></div><div class="stat-label">Active Staff</div></div></div>
                <div class="stat-card"><div class="icon-box"><i class="bi bi-building"></i></div><div><div class="stat-value"><?= count($sites) ?></div><div class="stat-label">Sites</div></div></div>
            </div>

            <!-- Action Buttons -->
            <div class="quick-actions">
                <button class="btn-brand quick-btn" onclick="document.getElementById('adminModal').classList.add('open')">
                    <i class="bi bi-shield-plus me-1"></i> Create New Admin
                </button>
                <button class="btn-outline-brand quick-btn" onclick="document.getElementById('staffModal').classList.add('open')">
                    <i class="bi bi-person-plus me-1"></i> Register New Worker
                </button>
            </div>

            <!-- Admins Table -->
            <div class="card-box mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="section-title mb-0"><i class="bi bi-shield-check me-1"></i> Admin Accounts</p>
                        <span class="badge-status badge-on-time"><?= count($admins) ?> admins</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Admin</th><th>Email</th><th>Permission</th><th>Site</th><th>Status</th><th>Created</th></tr></thead>
                            <tbody>
                                <?php foreach ($admins as $a):
                                    $pl=strtolower($a['permission_level']);
                                    $pc=match($pl){ 'superadmin'=>['#fef3c7','#92400e'], 'manager'=>['#dbeafe','#1e40af'], 'rosteradmin'=>['#d1fae5','#065f46'], default=>['#f3f4f6','#374151'] };
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="admin-avatar" style="width:30px;height:30px;font-size:0.7rem;"><?= strtoupper(substr($a['username'],0,2)) ?></div>
                                            <div><div style="font-weight:600;"><?= htmlspecialchars($a['username']) ?></div><div style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($a['contact_number']??'') ?></div></div>
                                        </div>
                                    </td>
                                    <td style="font-size:0.85rem;"><?= htmlspecialchars($a['email']) ?></td>
                                    <td><span class="badge-status" style="background:<?= $pc[0] ?>;color:<?= $pc[1] ?>;"><?= htmlspecialchars($a['permission_level']) ?></span></td>
                                    <td style="font-size:0.85rem;"><?= htmlspecialchars($a['site_name']??'—') ?></td>
                                    <td><span class="badge-active"><?= htmlspecialchars($a['status']) ?></span></td>
                                    <td style="font-size:0.82rem;color:var(--text-muted);"><?= date('d M Y',strtotime($a['created_at'])) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Staff Table -->
            <div class="card-box">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <p class="section-title mb-0"><i class="bi bi-people me-1"></i> Active Staff</p>
                        <span class="badge-status badge-active"><?= count($staffList) ?> staff</span>
                    </div>
                    <?php if (empty($staffList)): ?>
                    <div class="text-center py-4" style="color:var(--text-muted)">No active staff. Click "Register New Worker".</div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead><tr><th>Staff</th><th>Role</th><th>Contract</th><th>Contact</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($staffList as $s): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="admin-avatar" style="width:30px;height:30px;font-size:0.7rem;"><?= strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1)) ?></div>
                                            <div><div style="font-weight:600;"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div><div style="font-size:0.78rem;color:var(--text-muted);"><?= htmlspecialchars($s['staff_number']) ?></div></div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($s['role_name']??'—') ?></td>
                                    <td style="font-size:0.85rem;"><?= htmlspecialchars($s['contract_type']??'—') ?></td>
                                    <td style="font-size:0.85rem;"><?= htmlspecialchars($s['contact_number']??'—') ?></td>
                                    <td><span class="badge-active">Active</span></td>
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

<!-- CREATE ADMIN MODAL -->
<div class="modal-overlay" id="adminModal">
    <div class="modal-card">
        <div class="modal-header"><h5 class="modal-title">Create New Admin</h5><button class="modal-close" onclick="document.getElementById('adminModal').classList.remove('open')"><i class="bi bi-x-lg"></i></button></div>
        <form method="POST">
            <input type="hidden" name="action" value="create_admin">
            <div class="modal-body">
                <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;font-size:0.82rem;color:#92400e;margin-bottom:16px;">
                    🔴 <strong>SuperAdmin</strong> — Full access &nbsp;|&nbsp; 🟡 <strong>Manager</strong> — Staff & roster &nbsp;|&nbsp; 🟢 <strong>RosterAdmin</strong> — Roster only
                </div>
                <div class="form-row-2">
                    <div class="form-field"><label>Username *</label><input type="text" name="username" class="form-input" placeholder="e.g. john_manager" required></div>
                    <div class="form-field"><label>Contact Number</label><input type="text" name="contact_number" class="form-input" placeholder="0412 345 678"></div>
                </div>
                <div class="form-field"><label>Email *</label><input type="email" name="email" class="form-input" placeholder="admin@farmtime.com" required></div>
                <div class="form-row-2">
                    <div class="form-field"><label>Permission Level *</label>
                        <select name="permission_level" class="form-input" required>
                            <option value="manager">Manager</option>
                            <option value="RosterAdmin">Roster Admin</option>
                            <option value="superadmin">Super Admin</option>
                        </select>
                    </div>
                    <div class="form-field"><label>Site *</label>
                        <select name="site_id" class="form-input" required>
                            <?php foreach ($sites as $s): ?><option value="<?= $s['site_id'] ?>"><?= htmlspecialchars($s['site_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row-2">
                    <div class="form-field"><label>Password *</label><input type="password" name="password" class="form-input" placeholder="Min 8 characters" required></div>
                    <div class="form-field"><label>Confirm Password *</label><input type="password" name="confirm_password" class="form-input" placeholder="Re-enter password" required></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-brand" onclick="document.getElementById('adminModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn-brand"><i class="bi bi-shield-plus me-1"></i> Create Admin</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- REGISTER STAFF MODAL -->
<div class="modal-overlay" id="staffModal">
    <div class="modal-card">
        <div class="modal-header"><h5 class="modal-title">Register New Worker</h5><button class="modal-close" onclick="document.getElementById('staffModal').classList.remove('open')"><i class="bi bi-x-lg"></i></button></div>
        <form method="POST">
            <input type="hidden" name="action" value="create_staff">
            <div class="modal-body">
                <div class="form-row-2">
                    <div class="form-field"><label>First Name *</label><input type="text" name="first_name" class="form-input" placeholder="e.g. Sarah" required></div>
                    <div class="form-field"><label>Last Name *</label><input type="text" name="last_name" class="form-input" placeholder="e.g. Miller" required></div>
                </div>
                <div class="form-row-2">
                    <div class="form-field"><label>Staff ID *</label><input type="text" name="staff_number" class="form-input" value="<?= $nextNum ?>" required></div>
                    <div class="form-field"><label>Email</label><input type="email" name="contact_email" class="form-input" placeholder="name@farm.local"></div>
                </div>
                <div class="form-row-2">
                    <div class="form-field"><label>Role *</label>
                        <select name="role_id" class="form-input" required>
                            <option value="">Select role...</option>
                            <?php foreach ($roles as $r): ?><option value="<?= $r['role_id'] ?>"><?= htmlspecialchars($r['role_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field"><label>Contract Type *</label>
                        <select name="contract_type" class="form-input" required>
                            <option value="">Select type...</option>
                            <option value="Full Time">Full Time</option>
                            <option value="Part Time">Part Time</option>
                            <option value="Casual">Casual</option>
                        </select>
                    </div>
                </div>
                <div class="form-field"><label>Phone</label><input type="text" name="contact_number" class="form-input" placeholder="0412 345 678"></div>
                <div class="modal-footer">
                    <button type="button" class="btn-outline-brand" onclick="document.getElementById('staffModal').classList.remove('open')">Cancel</button>
                    <button type="submit" class="btn-brand"><i class="bi bi-person-plus me-1"></i> Register Worker</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('adminModal').addEventListener('click',e=>{ if(e.target===e.currentTarget)e.currentTarget.classList.remove('open'); });
document.getElementById('staffModal').addEventListener('click',e=>{ if(e.target===e.currentTarget)e.currentTarget.classList.remove('open'); });
<?php if ($msg): ?>setTimeout(()=>{ const t=document.querySelector('.toast-success,.toast-error'); if(t){t.style.opacity='0';setTimeout(()=>t.remove(),300);} },4000);<?php endif; ?>
</script>
</body>
</html>
