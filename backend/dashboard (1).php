<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

$conn    = getConnection();
$adminId = $_SESSION['admin_id'];

// Stats
$totalStaff     = $conn->query("SELECT COUNT(*) AS c FROM staff WHERE status='active'")->fetch_assoc()['c'] ?? 0;
$todayAtt       = $conn->query("SELECT COUNT(*) AS c FROM attendance WHERE DATE(clock_in)=CURDATE()")->fetch_assoc()['c'] ?? 0;
$clockedIn      = $conn->query("SELECT COUNT(*) AS c FROM attendance WHERE DATE(clock_in)=CURDATE() AND clock_out IS NULL")->fetch_assoc()['c'] ?? 0;
$pendingLeave   = $conn->query("SELECT COUNT(*) AS c FROM leave_records WHERE status='pending'")->fetch_assoc()['c'] ?? 0;
$shiftsToday    = $conn->query("SELECT COUNT(*) AS c FROM roster WHERE work_date=CURDATE()")->fetch_assoc()['c'] ?? 0;
$exceptions     = $conn->query("SELECT COUNT(*) AS c FROM exceptions")->fetch_assoc()['c'] ?? 0;

// Recent attendance
$recentAtt = $conn->query("
    SELECT CONCAT(s.first_name,' ',s.last_name) AS name,
           a.clock_in, a.clock_out, a.attendance_status
    FROM attendance a
    JOIN staff s ON a.staff_id = s.staff_id
    WHERE DATE(a.clock_in) = CURDATE()
    ORDER BY a.clock_in DESC LIMIT 6
")->fetch_all(MYSQLI_ASSOC);

// Recent audit logs
$logs = $conn->query("
    SELECT l.action_type, l.target_table, l.reason, l.created_at, a.username
    FROM audit_logs l
    LEFT JOIN admin a ON l.admin_id = a.admin_id
    ORDER BY l.created_at DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Workforce</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',sans-serif; background:#f0f2f5; display:flex; min-height:100vh; }

        /* ── Sidebar ── */
        .sidebar {
            width:230px; background:#1a1a2e; color:#fff;
            min-height:100vh; position:fixed; top:0; left:0;
            display:flex; flex-direction:column;
        }
        .sidebar-brand { padding:22px 20px; font-size:17px; font-weight:700; border-bottom:1px solid rgba(255,255,255,0.08); display:flex; align-items:center; gap:8px; }
        .sidebar-user  { padding:14px 20px; border-bottom:1px solid rgba(255,255,255,0.08); font-size:13px; }
        .sidebar-user .name { font-weight:600; font-size:14px; }
        .sidebar-user .role { color:#aaa; font-size:12px; margin-top:2px; }
        .nav-group { padding:10px 20px 2px; font-size:10px; font-weight:700; color:#555; text-transform:uppercase; letter-spacing:1px; margin-top:6px; }
        .nav-link {
            display:flex; align-items:center; gap:10px;
            padding:10px 20px; color:#bbb; text-decoration:none;
            font-size:14px; transition:all 0.15s;
            border-left:3px solid transparent;
        }
        .nav-link:hover  { background:rgba(255,255,255,0.07); color:#fff; }
        .nav-link.active { background:rgba(79,70,229,0.18); color:#fff; border-left-color:#4f46e5; }
        .sidebar-footer { margin-top:auto; padding:16px 20px; border-top:1px solid rgba(255,255,255,0.08); }
        .btn-logout { display:block; text-align:center; background:#dc2626; color:#fff; padding:9px; border-radius:8px; text-decoration:none; font-size:14px; font-weight:600; }

        /* ── Main ── */
        .main { margin-left:230px; flex:1; }
        .topbar { background:#fff; padding:0 28px; height:58px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 1px 4px rgba(0,0,0,0.08); }
        .topbar-title { font-size:18px; font-weight:700; color:#1a1a2e; }
        .topbar-right  { font-size:13px; color:#888; }
        .content { padding:24px 28px; }

        /* ── Stats ── */
        .stats { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; }
        .stat {
            background:#fff; border-radius:12px; padding:20px;
            box-shadow:0 2px 8px rgba(0,0,0,0.06);
            display:flex; align-items:center; gap:14px;
            text-decoration:none; color:inherit;
            transition:transform 0.15s, box-shadow 0.15s;
        }
        .stat:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(0,0,0,0.1); }
        .stat-icon { font-size:28px; width:48px; height:48px; display:flex; align-items:center; justify-content:center; border-radius:10px; }
        .blue   { background:#eff6ff; }
        .green  { background:#f0fdf4; }
        .orange { background:#fff7ed; }
        .purple { background:#f5f3ff; }
        .yellow { background:#fefce8; }
        .red    { background:#fff5f5; }
        .stat-info h3 { font-size:26px; font-weight:700; color:#1a1a2e; }
        .stat-info p  { font-size:12px; color:#888; margin-top:2px; }

        /* ── Cards ── */
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        .card { background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.06); overflow:hidden; }
        .card-head { padding:14px 20px; border-bottom:1px solid #f0f0f0; font-size:14px; font-weight:600; color:#1a1a2e; display:flex; align-items:center; justify-content:space-between; }
        .card-head a { font-size:12px; color:#4f46e5; text-decoration:none; }
        .item { padding:12px 20px; border-bottom:1px solid #f5f5f5; display:flex; align-items:center; justify-content:space-between; font-size:13px; }
        .item:last-child { border-bottom:none; }
        .item-name  { font-weight:600; color:#1a1a2e; }
        .item-sub   { font-size:11px; color:#888; margin-top:2px; }
        .badge { display:inline-block; padding:2px 8px; border-radius:20px; font-size:11px; font-weight:600; }
        .b-present  { background:#f0fdf4; color:#16a34a; }
        .b-late     { background:#fffbeb; color:#d97706; }
        .b-absent   { background:#fff5f5; color:#dc2626; }
        .b-LOGIN    { background:#f0fdf4; color:#16a34a; }
        .b-LOGOUT   { background:#eff6ff; color:#2563eb; }
        .b-CREATE   { background:#f5f3ff; color:#7c3aed; }
        .b-UPDATE   { background:#fefce8; color:#d97706; }
        .b-DELETE   { background:#fff5f5; color:#dc2626; }
        .b-LOGIN_FAILED { background:#fff5f5; color:#dc2626; }
        .empty { text-align:center; padding:32px; color:#bbb; font-size:13px; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-brand">⏱ Workforce</div>
    <div class="sidebar-user">
        <div class="name">👤 <?= htmlspecialchars($_SESSION['username']) ?></div>
        <div class="role"><?= htmlspecialchars($_SESSION['permission_level']) ?> • <?= htmlspecialchars($_SESSION['site_name'] ?? '') ?></div>
    </div>

    <div class="nav-group">Main</div>
    <a href="dashboard.php"   class="nav-link active">🏠 Dashboard</a>
    <a href="clockinout.php"  class="nav-link">⏱ Clock In / Out</a>

    <div class="nav-group">Management</div>
    <a href="staff.php"       class="nav-link">👥 Staff</a>
    <a href="contracts.php"   class="nav-link">📄 Contracts</a>
    <a href="roster.php"      class="nav-link">📅 Roster</a>
    <a href="leave.php"       class="nav-link">🏖️ Leave</a>

    <div class="nav-group">Payroll</div>
    <a href="payroll.php"     class="nav-link">💰 Payroll</a>

    <div class="nav-group">System</div>
    <a href="auditlogs.php"   class="nav-link">🔍 Audit Logs</a>

    <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout">🚪 Logout</a>
    </div>
</div>

<!-- Main -->
<div class="main">
    <div class="topbar">
        <div class="topbar-title">Dashboard</div>
        <div class="topbar-right"><?= date('l, F j, Y') ?></div>
    </div>

    <div class="content">
        <!-- Stats -->
        <div class="stats">
            <a href="staff.php" class="stat">
                <div class="stat-icon blue">👥</div>
                <div class="stat-info"><h3><?= $totalStaff ?></h3><p>Active Staff</p></div>
            </a>
            <a href="clockinout.php" class="stat">
                <div class="stat-icon green">✅</div>
                <div class="stat-info"><h3><?= $todayAtt ?></h3><p>Today's Attendance</p></div>
            </a>
            <a href="clockinout.php" class="stat">
                <div class="stat-icon orange">🟢</div>
                <div class="stat-info"><h3><?= $clockedIn ?></h3><p>Currently Clocked In</p></div>
            </a>
            <a href="roster.php" class="stat">
                <div class="stat-icon purple">📅</div>
                <div class="stat-info"><h3><?= $shiftsToday ?></h3><p>Shifts Today</p></div>
            </a>
            <a href="leave.php" class="stat">
                <div class="stat-icon yellow">📋</div>
                <div class="stat-info"><h3><?= $pendingLeave ?></h3><p>Pending Leave</p></div>
            </a>
            <a href="#" class="stat">
                <div class="stat-icon red">⚠️</div>
                <div class="stat-info"><h3><?= $exceptions ?></h3><p>Exceptions</p></div>
            </a>
        </div>

        <div class="grid-2">
            <!-- Today's Attendance -->
            <div class="card">
                <div class="card-head">
                    ⏱ Today's Attendance
                    <a href="clockinout.php">View all →</a>
                </div>
                <?php if (empty($recentAtt)): ?>
                    <div class="empty">No attendance recorded today.</div>
                <?php else: ?>
                    <?php foreach ($recentAtt as $a): ?>
                    <div class="item">
                        <div>
                            <div class="item-name"><?= htmlspecialchars($a['name']) ?></div>
                            <div class="item-sub">
                                In: <?= date('h:i A', strtotime($a['clock_in'])) ?>
                                <?= $a['clock_out']
                                    ? ' • Out: '.date('h:i A', strtotime($a['clock_out']))
                                    : ' • <span style="color:#16a34a;">Still In</span>' ?>
                            </div>
                        </div>
                        <span class="badge b-<?= $a['attendance_status'] ?>"><?= ucfirst($a['attendance_status']) ?></span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-head">
                    🔍 Recent Activity
                    <a href="auditlogs.php">View all →</a>
                </div>
                <?php foreach ($logs as $log): ?>
                <div class="item">
                    <div>
                        <span class="badge b-<?= $log['action_type'] ?>"><?= $log['action_type'] ?></span>
                        <span style="color:#666;font-size:12px;margin-left:6px;"><?= htmlspecialchars($log['target_table']) ?></span>
                        <div class="item-sub"><?= htmlspecialchars($log['username'] ?? '—') ?> • <?= date('h:i A', strtotime($log['created_at'])) ?></div>
                    </div>
                    <span style="font-size:11px;color:#aaa;"><?= htmlspecialchars(substr($log['reason'] ?? '', 0, 25)) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

</body>
</html>
