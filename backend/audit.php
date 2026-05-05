<?php
// audit/index.php
require_once '../includes/auth.php';
require_once '../config/database.php';
requirePermission('superadmin', 'manager');

$conn = getConnection();

$filterTable  = $_GET['table']  ?? '';
$filterAction = $_GET['action'] ?? '';
$filterAdmin  = intval($_GET['admin_id'] ?? 0);
$limit        = 100;

$sql = "
    SELECT l.*, a.username
    FROM audit_logs l
    LEFT JOIN admin a ON l.admin_id = a.admin_id
    WHERE 1=1
";
if ($filterTable)  $sql .= " AND l.target_table = '{$conn->real_escape_string($filterTable)}'";
if ($filterAction) $sql .= " AND l.action_type = '{$conn->real_escape_string($filterAction)}'";
if ($filterAdmin)  $sql .= " AND l.admin_id = $filterAdmin";
$sql .= " ORDER BY l.created_at DESC LIMIT $limit";

$logs   = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
$admins = $conn->query("SELECT admin_id, username FROM admin ORDER BY username")->fetch_all(MYSQLI_ASSOC);
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI',sans-serif; background:#f0f2f5; }
        .navbar { background:#1a1a2e; color:#fff; padding:0 32px; height:60px; display:flex; align-items:center; justify-content:space-between; }
        .navbar-brand { font-size:18px; font-weight:700; }
        .navbar-right { display:flex; gap:12px; }
        .btn-nav { background:#0f3460; color:#fff; padding:7px 14px; border-radius:6px; text-decoration:none; font-size:13px; }
        .btn-logout { background:#dc2626; color:#fff; padding:7px 14px; border-radius:6px; text-decoration:none; font-size:13px; }
        .content { padding:28px 32px; }
        .page-title { font-size:22px; font-weight:700; color:#1a1a2e; margin-bottom:20px; }
        .filters { display:flex; gap:12px; margin-bottom:20px; flex-wrap:wrap; align-items:center; }
        select, input { padding:9px 14px; border:1.5px solid #e0e0e0; border-radius:8px; font-size:14px; outline:none; }
        .btn { padding:9px 18px; border:none; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn-primary { background:#4f46e5; color:#fff; }
        .card { background:#fff; border-radius:12px; box-shadow:0 2px 8px rgba(0,0,0,0.06); overflow:hidden; }
        .card-header { padding:16px 22px; border-bottom:1px solid #f0f0f0; font-size:16px; font-weight:600; color:#1a1a2e; display:flex; align-items:center; }
        table { width:100%; border-collapse:collapse; }
        th { background:#f8f9fa; padding:12px 16px; text-align:left; font-size:12px; font-weight:600; color:#888; text-transform:uppercase; }
        td { padding:12px 16px; font-size:13px; border-bottom:1px solid #f5f5f5; vertical-align:top; }
        .badge { display:inline-block; padding:3px 9px; border-radius:20px; font-size:11px; font-weight:600; }
        .badge-CREATE  { background:#f0fdf4; color:#16a34a; }
        .badge-UPDATE  { background:#eff6ff; color:#2563eb; }
        .badge-DELETE  { background:#fff5f5; color:#dc2626; }
        .badge-LOGIN   { background:#f0fdf4; color:#16a34a; }
        .badge-LOGOUT  { background:#eff6ff; color:#2563eb; }
        .badge-LOGIN_FAILED { background:#fff5f5; color:#dc2626; }
        .json-preview { font-size:11px; background:#f8f9fa; padding:4px 8px; border-radius:4px; max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; cursor:pointer; }
    </style>
</head>
<body>
<nav class="navbar">
    <div class="navbar-brand">⏱ Workforce Management</div>
    <div class="navbar-right">
        <a href="../dashboard.php" class="btn-nav">🏠 Dashboard</a>
        <a href="../logout.php" class="btn-logout">🚪 Logout</a>
    </div>
</nav>

<div class="content">
    <div class="page-title">🔍 Audit Logs</div>

    <form method="GET">
        <div class="filters">
            <select name="table">
                <option value="">All Tables</option>
                <?php foreach (['admin','staff','contracts','roster','attendance','leave_records','payslips','pay_periods','devices'] as $t): ?>
                    <option value="<?= $t ?>" <?= $filterTable===$t?'selected':'' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select>
            <select name="action">
                <option value="">All Actions</option>
                <?php foreach (['CREATE','UPDATE','DELETE','LOGIN','LOGOUT','LOGIN_FAILED'] as $act): ?>
                    <option value="<?= $act ?>" <?= $filterAction===$act?'selected':'' ?>><?= $act ?></option>
                <?php endforeach; ?>
            </select>
            <select name="admin_id">
                <option value="">All Admins</option>
                <?php foreach ($admins as $a): ?>
                    <option value="<?= $a['admin_id'] ?>" <?= $filterAdmin==$a['admin_id']?'selected':'' ?>><?= htmlspecialchars($a['username']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="index.php" class="btn" style="background:#e5e7eb;color:#333;">Reset</a>
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            📋 Activity Log (last <?= $limit ?>)
            <span style="margin-left:auto;background:#eff6ff;color:#2563eb;padding:3px 10px;border-radius:20px;font-size:13px;"><?= count($logs) ?> records</span>
        </div>
        <table>
            <thead>
                <tr><th>Time</th><th>Admin</th><th>Action</th><th>Table</th><th>Record ID</th><th>Reason</th><th>Changes</th></tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td style="white-space:nowrap;"><?= date('d M Y H:i:s', strtotime($log['created_at'])) ?></td>
                    <td><strong><?= htmlspecialchars($log['username'] ?? '—') ?></strong></td>
                    <td><span class="badge badge-<?= $log['action_type'] ?>"><?= $log['action_type'] ?></span></td>
                    <td style="color:#666;"><?= htmlspecialchars($log['target_table']) ?></td>
                    <td style="color:#888;"><?= $log['target_id'] ?? '—' ?></td>
                    <td style="color:#666;font-size:12px;"><?= htmlspecialchars($log['reason'] ?? '—') ?></td>
                    <td>
                        <?php if ($log['old_values'] || $log['new_values']): ?>
                            <div class="json-preview" title="<?= htmlspecialchars($log['new_values'] ?? $log['old_values']) ?>">
                                <?= htmlspecialchars(substr($log['new_values'] ?? $log['old_values'], 0, 60)) ?>...
                            </div>
                        <?php else: ?>
                            <span style="color:#ccc;">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
