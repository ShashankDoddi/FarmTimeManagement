<?php
// config/database.php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'workforce_db');

function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die('Database connection failed: ' . $conn->connect_error);
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

function auditLog(mysqli $conn, string $action, string $table, int $targetId, string $reason = '') {
    if (!isset($_SESSION['admin_id'])) return;
    $adminId = (int) $_SESSION['admin_id'];
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (admin_id, action_type, target_table, target_id, reason, source_channel)
        VALUES (?, ?, ?, ?, ?, 'web')
    ");
    $stmt->bind_param('issss', $adminId, $action, $table, $targetId, $reason);
    $stmt->execute();
    $stmt->close();
}
