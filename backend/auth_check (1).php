<?php
// auth_check.php
// Include this at the top of EVERY protected page
// Usage: require_once 'auth_check.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Not logged in → go to login ──────────────────────────────
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit();
}

// ── Viewer cannot access dashboard at all ────────────────────
if ($_SESSION['permission_level'] === 'viewer') {
    session_unset();
    session_destroy();
    header('Location: login.php?error=access_denied');
    exit();
}

// ── Permission check function ────────────────────────────────
// Usage: checkPermission('superadmin', 'manager');
function checkPermission(string ...$allowedRoles): void {
    if (!in_array($_SESSION['permission_level'], $allowedRoles)) {
        header('Location: dashboard.php?error=no_permission');
        exit();
    }
}

// ── Get current admin ID ─────────────────────────────────────
function adminId(): int {
    return (int) $_SESSION['admin_id'];
}

// ── Audit log helper ─────────────────────────────────────────
function writeAuditLog(mysqli $conn, string $action, string $table, int $targetId, string $reason = ''): void {
    $adminId = adminId();
    $stmt = $conn->prepare("
        INSERT INTO audit_logs (admin_id, action_type, target_table, target_id, reason, source_channel)
        VALUES (?, ?, ?, ?, ?, 'web')
    ");
    $stmt->bind_param('issss', $adminId, $action, $table, $targetId, $reason);
    $stmt->execute();
    $stmt->close();
}
