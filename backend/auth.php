<?php
// includes/auth.php

if (session_status() === PHP_SESSION_NONE) session_start();

function requireLogin() {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: /DEVOPS_TIMESHEET/Login.php');
        exit();
    }
}

function requirePermission(string ...$levels) {
    requireLogin();
    if (!in_array($_SESSION['permission_level'], $levels)) {
        http_response_code(403);
        die('<h2>403 — Access Denied</h2>');
    }
}

function currentAdmin(): int {
    return (int) ($_SESSION['admin_id'] ?? 0);
}

function auditLog(mysqli $conn, string $action, string $table, int $targetId, string $reason = '', $oldValues = null, $newValues = null) {
    $adminId = currentAdmin();
    $old     = $oldValues ? json_encode($oldValues) : null;
    $new     = $newValues ? json_encode($newValues) : null;

    $stmt = $conn->prepare("
        INSERT INTO audit_logs (admin_id, action_type, target_table, target_id, reason, source_channel, old_values, new_values)
        VALUES (?, ?, ?, ?, ?, 'web', ?, ?)
    ");
    $stmt->bind_param('issssss', $adminId, $action, $table, $targetId, $reason, $old, $new);
    $stmt->execute();
    $stmt->close();
}

function jsonResponse(bool $success, string $message, array $data = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit();
}
