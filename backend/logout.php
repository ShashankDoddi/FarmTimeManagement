<?php
session_start();
require_once 'config/database.php';

if (isset($_SESSION['admin_id'])) {
    $conn    = getConnection();
    $adminId = $_SESSION['admin_id'];

    $stmt = $conn->prepare("
        INSERT INTO audit_logs (admin_id, action_type, target_table, target_id, reason, source_channel)
        VALUES (?, 'LOGOUT', 'admin', ?, 'Admin logged out', 'web')
    ");
    $stmt->bind_param('ii', $adminId, $adminId);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

session_unset();
session_destroy();
header('Location: login.php');
exit();
