<?php
session_start();
require_once 'config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: Login.php');
    exit();
}

$conn    = getConnection();
$adminId = $_SESSION['admin_id'];

// Stats
$stats = [
    'staff'       => $conn->query("SELECT COUNT(*) AS c FROM staff WHERE status='active'")->fetch_assoc()['c'] ?? 0,
    'today_att'   => $conn->query("SELECT COUNT(*) AS c FROM attendance WHERE DATE(clock_in)=CURDATE()")->fetch_assoc()['c'] ?? 0,
    'clocked_in'  => $conn->query("SELECT COUNT(*) AS c FROM attendance WHERE DATE(clock_in)=CURDATE() AND clock_out IS NULL")->fetch_assoc()['c'] ?? 0,
    'pending_leave'=> $conn->query("SELECT COUNT(*) AS c FROM leave_records WHERE status='pending'")->fetch_assoc()['c'] ?? 0,
    'roster_today'=> $conn->query("SELECT COUNT(*) AS c FROM roster WHERE work_date=CURDATE()")->fetch_assoc()['c'] ?? 0,
    'exceptions'  => $conn->query("SELECT COUNT(*) AS c FROM exceptions")->fetch_assoc()['c'] ?? 0,
];

// Recent audit logs
$logs = $conn->query("
    SELECT l.action_type, l.target_table, l.reason, l.created_at, a.username
    FROM audit_logs l
    LEFT JOIN admin a ON l.admin_id = a.admin_id
    ORDER BY l.created_at DESC LIMIT 8
")->fetch_all(MYSQLI_ASSOC);

// Today's attendance summary
$todayAtt = $conn->query("
    SELECT CONCAT(s.first_name,' ',s.last_name) AS name, a.clock_in, a.clock_out, a.attendance_status
    FROM attendance a
    JOIN staff s ON a.staff_id = s.staff_id
    WHERE DATE(a.clock_in) = CURDATE()
    ORDER BY a.clock_in DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Workforce Management</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',sans-serif; background:#f0f2f5; display:flex; min-height:100vh; }

        /* Sidebar */
        .sidebar {
            width: 240px;
            background: #1a1a2e;
            color: #fff;
            min-height: 100vh;
            position: fixed;
            top: 0; left: 0;
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            padding: 22px 20px;
            font-size: 18px;
            font-weight: 700;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-brand span { font-size: 22px; }

        .sidebar-user {
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            font-size: 13px;
        }

        .sidebar-user .username { font-weight: 600; font-size: 14px; }
        .sidebar-user .role { color: #aaa; font-size: 12px; margin-top: 2px; }

        .nav-section {
            padding: 12px 20px 4px;
            font-size: 10px;
            font-weight: 700;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 20px;
            color: #ccc;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.2s, color 0.2s;
            border-left: 3px solid transparent;
        }

        .nav-item:hover  { background: rgba(255,255,255,0.08); color: #fff; }
        .nav-item.active { background: rgba(79,70,229,0.2); color: #fff; border-left-color: #4f46e5; }

        .nav-item .icon { width: 20px; text-align: center; font-size: 16px; }

        .sidebar-footer {
            margin-top: auto;
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .btn-logout {
            display: block;
            text-align: center;
            background: #dc2626;
            color: #fff;
            padding: 9px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        /* Main */
        .main { margin-left: 240px; flex: 1; }

        .topbar {
            background: #fff;
            padding: 0 28px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }

        .topbar-title { font-size: 18px; font-weight: 700; color: #1a1a2e; }
        .topbar-date  { font-size: 13px; color: #888; }

        .content { padding: 24px 28px; }

        /* Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }

        .stat-card {
            background: #fff;
            border-radius: 12px;
            padding: 20px 22px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex;
            align-items: center;
            gap: 16px;
            text-decoration: none;
            color: inherit;
            transition: transform 0.1s, box-shadow 0.1s;
        }

        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 16px rgba(0,0,0,0.1); }

        .stat-icon { font-size: 30px; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 10px; }
        .blue   { background: #eff6ff; }
        .green  { background: #f0fdf4; }
        .yellow { background: #fefce8; }
        .red    { background: #fff5f5; }
        .purple { background: #f5f3ff; }
        .orange { background: #fff7ed; }

        .stat-info h3 { font-size: 28px; font-weight: 700; color: #1a1a2e; }
        .stat-info p  { font-size: 13px; color: #888; margin-top: 2px; }

        /* Cards */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { background: #fff; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden; }
        .card-header { padding: 16px 20px; border-bottom: 1px solid #f0f0f0; font-size: 15px; font-weight: 600; color: #1a1a2e; display: flex; align-items: center; justify-content: space-between; }
        .card-body { padding: 0; }

        .list-item { padding: 12px 20px; border-bottom: 1px solid #f5f5f5; display: flex; align-items: center; justify-content: space-between; font-size: 13px; }
        .list-item:last-child { border-bottom: none; }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-LOGIN   { background: #f0fdf4; color: #16a34a; }
        .badge-LOGOUT  { background: #eff6ff; color: #2563eb; }
        .badge-CREATE  { background: #f5f3ff; color: #7c3aed; }
        .badge-UPDATE  { background: #fefce8; color: #d97706; }
        .badge-DELETE  { background: #fff5f5; color: #dc2626; }
        .badge-LOGIN_FAILED { background: #fff5f5; color: #dc2626; }

        .att-present { color: #16a34a; }
        .att-late    { color: #d97706; }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-brand"><span>⏱</span> Workforce</div>
    <div class="sidebar-user">
        <div class="username">👤 <?= htmlspecialchars($_SESSION['username']) ?></div>
        <div class="role"><?= htmlspecialchars($_SESSION['permission_level']) ?> • <?= htmlspecialchars($_SESSION['site_name'] ?? '') ?></div>
    </div>

    <div class="nav-section">Main</div>
    <a href="dashboard.php" class="nav-item active"><span class="icon">🏠</span> Dashboard</a>
    <a href="attendance.php" class="nav-item"><span class="icon">⏱</span> Attendance</a>

    <div class="nav-section">Management</div>
    <a href="staff/index.php" class="nav-item"><span class="icon">👥</span> Staff</a>
    <a href="contracts/index.php" class="nav-item"><span class="icon">📄</span> Contracts</a>
    <a href="roster/index.php" class="nav-item"><span class="icon">📅</span> Roster</a>
    <a href="leave/index.php" class="nav-item"><span class="icon">🏖️</span> Leave</a>

    <div class="nav-section">Payroll</div>
    <a href="payroll/index.php" class="nav-item"><span class="icon">💰</span> Payroll</a>

    <div class="nav-section">System</div>
    <a href="audit/index.php" class="nav-item"><span class="icon">🔍</span> Audit Logs</a>

    <div class="sidebar-footer">
        <a href="logout.php" class="btn-logout">🚪 Logout</a>
    </div>
</div>

<!-- Main Content -->
<div class="main">
    <div class="topbar">
        <div class="topbar-title">Dashboard</div>
        <div class="topbar-date"><?= date('l, F j, Y') ?></div>
    </div>

    <div class="content">
        <!-- Stats -->
        <div class="stats-grid">
            <a href="staff/index.php" class="stat-card">
                <div class="stat-icon blue">👥</div>
                <div class="stat-info"><h3><?= $stats['staff'] ?></h3><p>Active Staff</p></div>
            </a>
            <a href="attendance.php" class="stat-card">
                <div class="stat-icon green">✅</div>
                <div class="stat-info"><h3><?= $stats['today_att'] ?></h3><p>Today's Attendance</p></div>
            </a>
            <a href="attendance.php" class="stat-card">
                <div class="stat-icon orange">🟢</div>
                <div class="stat-info"><h3><?= $stats['clocked_in'] ?></h3><p>Currently Clocked In</p></div>
            </a>
            <a href="roster/index.php" class="stat-card">
                <div class="stat-icon purple">📅</div>
                <div class="stat-info"><h3><?= $stats['roster_today'] ?></h3><p>Shifts Today</p></div>
            </a>
            <a href="leave/index.php" class="stat-card">
                <div class="stat-icon yellow">📋</div>
                <div class="stat-info"><h3><?= $stats['pending_leave'] ?></h3><p>Pending Leave</p></div>
            </a>
            <a href="#" class="stat-card">
                <div class="stat-icon red">⚠️</div>
                <div class="stat-info"><h3><?= $stats['exceptions'] ?></h3><p>Exceptions</p></div>
            </a>
        </div>

        <div class="grid-2">
            <!-- Today's Attendance -->
            <div class="card">
                <div class="card-header">
                    ⏱ Today's Attendance
                    <a href="attendance.php" style="font-size:12px;color:#4f46e5;text-decoration:none;">View all →</a>
                </div>
                <div class="card-body">
                    <?php if (empty($todayAtt)): ?>
                        <div style="text-align:center;padding:30px;color:#aaa;font-size:14px;">No attendance today yet.</div>
                    <?php else: ?>
                        <?php foreach ($todayAtt as $a): ?>
                        <div class="list-item">
                            <div>
                                <strong><?= htmlspecialchars($a['name']) ?></strong><br>
                                <span style="color:#888;font-size:12px;">
                                    In: <?= date('h:i A', strtotime($a['clock_in'])) ?>
                                    <?= $a['clock_out'] ? ' • Out: '.date('h:i A', strtotime($a['clock_out'])) : ' • <span style="color:#16a34a;">Still In</span>' ?>
                                </span>
                            </div>
                            <span class="att-<?= $a['attendance_status'] ?>" style="font-size:12px;font-weight:600;">
                                <?= ucfirst($a['attendance_status']) ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    🔍 Recent Activity
                    <a href="audit/index.php" style="font-size:12px;color:#4f46e5;text-decoration:none;">View all →</a>
                </div>
                <div class="card-body">
                    <?php foreach ($logs as $log): ?>
                    <div class="list-item">
                        <div>
                            <span class="badge badge-<?= $log['action_type'] ?>"><?= $log['action_type'] ?></span>
                            <span style="color:#555;margin-left:6px;font-size:12px;"><?= htmlspecialchars($log['target_table']) ?></span>
                            <br>
                            <span style="color:#888;font-size:11px;"><?= htmlspecialchars($log['username'] ?? '—') ?> • <?= date('H:i', strtotime($log['created_at'])) ?></span>
                        </div>
                        <span style="font-size:11px;color:#aaa;"><?= htmlspecialchars(substr($log['reason'] ?? '', 0, 30)) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
