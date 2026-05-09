<?php
// forgot_password.php
// Allows admin to reset their own password by verifying username + email
session_start();
require_once 'config/database.php';

$step    = 1; // Step 1: verify identity, Step 2: reset password
$error   = '';
$success = '';
$token   = '';

// ── STEP 1: Verify username + email ──────────────────────────
if (isset($_POST['step']) && $_POST['step'] === '1') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');

    if (empty($username) || empty($email)) {
        $error = 'Please enter both username and email.';
    } else {
        $conn = getConnection();
        $stmt = $conn->prepare("
            SELECT admin_id, username, email, status
            FROM admin
            WHERE username = ? AND email = ?
            LIMIT 1
        ");
        $stmt->bind_param('ss', $username, $email);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();

        if (!$admin) {
            $error = 'No account found with that username and email combination.';
        } elseif ($admin['status'] !== 'active') {
            $error = 'This account is deactivated. Contact your system administrator.';
        } else {
            // Identity verified — move to step 2
            $step  = 2;
            $token = base64_encode($admin['admin_id'] . ':' . md5($admin['email'] . 'farmtime_salt'));
        }
    }
}

// ── STEP 2: Reset password ────────────────────────────────────
if (isset($_POST['step']) && $_POST['step'] === '2') {
    $token            = $_POST['token'] ?? '';
    $new_password     = trim($_POST['new_password'] ?? '');
    $confirm_password = trim($_POST['confirm_password'] ?? '');

    // Decode token to get admin_id
    $decoded  = base64_decode($token);
    $parts    = explode(':', $decoded);
    $admin_id = intval($parts[0] ?? 0);

    if ($admin_id <= 0) {
        $error = 'Invalid reset token. Please start over.';
        $step  = 1;
    } elseif (strlen($new_password) < 8) {
        $error = 'Password must be at least 8 characters.';
        $step  = 2;
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
        $step  = 2;
    } else {
        $conn          = getConnection();
        $password_hash = password_hash($new_password, PASSWORD_BCRYPT);

        $stmt = $conn->prepare("UPDATE admin SET password_hash = ? WHERE admin_id = ?");
        $stmt->bind_param('si', $password_hash, $admin_id);

        if ($stmt->execute()) {
            // Log the password reset
            $log = $conn->prepare("
                INSERT INTO audit_logs (admin_id, action_type, target_table, target_id, reason, source_channel)
                VALUES (?, 'UPDATE', 'admin', ?, 'Password reset via forgot password', 'web')
            ");
            $log->bind_param('ii', $admin_id, $admin_id);
            $log->execute();
            $log->close();

            $success = 'Password reset successfully! You can now login with your new password.';
            $step    = 3;
        } else {
            $error = 'Failed to reset password. Please try again.';
            $step  = 2;
        }
        $stmt->close();
        $conn->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Forgot Password — Farm Time</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background-color: #696c2b;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .wrapper { width: 100%; padding: 20px; }
        .card {
            max-width: 440px;
            margin: 0 auto;
            background: #fff;
            border-radius: 14px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            padding: 36px 32px;
        }
        .brand { font-size: 1.4rem; font-weight: 700; color: #696c2b; margin-bottom: 6px; }
        .subtitle { color: #6c757d; font-size: 0.9rem; margin-bottom: 28px; }

        /* Steps indicator */
        .steps {
            display: flex;
            align-items: center;
            margin-bottom: 28px;
            gap: 0;
        }
        .step-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
        }
        .step-circle {
            width: 32px; height: 32px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px; font-weight: 700;
            background: #e9ecef; color: #aaa;
            border: 2px solid #e9ecef;
        }
        .step-circle.active { background: #696c2b; color: #fff; border-color: #696c2b; }
        .step-circle.done   { background: #22c55e; color: #fff; border-color: #22c55e; }
        .step-label { font-size: 11px; color: #aaa; margin-top: 4px; text-align: center; }
        .step-label.active { color: #696c2b; font-weight: 600; }
        .step-line { flex: 1; height: 2px; background: #e9ecef; margin: 0 4px; margin-bottom: 18px; }
        .step-line.done { background: #22c55e; }

        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; margin-bottom: 7px; font-weight: 600; color: #343a40; font-size: 0.9rem; }
        .form-group input {
            width: 100%; padding: 12px 14px;
            border: 1px solid #dcdfe3; border-radius: 10px;
            font-size: 0.95rem; outline: none; box-sizing: border-box;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-group input:focus { border-color: #696c2b; box-shadow: 0 0 0 3px rgba(105,108,43,0.12); }

        .btn {
            width: 100%; border: none; border-radius: 10px;
            background: #696c2b; color: white;
            font-size: 1rem; font-weight: 600; padding: 12px;
            cursor: pointer; transition: background 0.2s;
        }
        .btn:hover { background: #5b5e24; }

        .btn-success-green {
            width: 100%; border: none; border-radius: 10px;
            background: #16a34a; color: white;
            font-size: 1rem; font-weight: 600; padding: 12px;
            cursor: pointer; transition: background 0.2s;
            text-align: center; text-decoration: none;
            display: block; margin-top: 12px;
        }
        .btn-success-green:hover { background: #15803d; color: white; }

        .error-box {
            background: #fff5f5; border: 1px solid #fecaca;
            color: #dc2626; padding: 12px 14px;
            border-radius: 10px; font-size: 0.9rem; margin-bottom: 18px;
        }
        .success-box {
            background: #f0fdf4; border: 1px solid #bbf7d0;
            color: #16a34a; padding: 16px 14px;
            border-radius: 10px; font-size: 0.95rem; margin-bottom: 18px;
            text-align: center;
        }
        .back-link {
            text-align: center; margin-top: 20px;
            font-size: 0.9rem; color: #6c757d;
        }
        .back-link a { color: #696c2b; font-weight: 600; text-decoration: none; }
        .back-link a:hover { text-decoration: underline; }

        .info-box {
            background: rgba(105,108,43,0.08);
            border: 1px solid rgba(105,108,43,0.2);
            border-radius: 10px; padding: 12px 14px;
            font-size: 0.85rem; color: #555; margin-bottom: 20px;
        }

        /* Password strength */
        .strength-bar { height: 4px; background: #eee; border-radius: 4px; margin-top: 6px; overflow: hidden; }
        .strength-fill { height: 100%; border-radius: 4px; transition: width 0.3s, background 0.3s; width: 0; }
        .strength-text { font-size: 11px; margin-top: 3px; color: #aaa; }

        @media (max-width: 480px) { .card { padding: 24px 20px; } }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">

        <div class="brand">Farm Time Admin</div>
        <p class="subtitle">Reset your password</p>

        <!-- Steps Indicator -->
        <div class="steps">
            <div class="step-item">
                <div class="step-circle <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>">
                    <?= $step > 1 ? '✓' : '1' ?>
                </div>
                <div class="step-label <?= $step === 1 ? 'active' : '' ?>">Verify</div>
            </div>
            <div class="step-line <?= $step > 1 ? 'done' : '' ?>"></div>
            <div class="step-item">
                <div class="step-circle <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>">
                    <?= $step > 2 ? '✓' : '2' ?>
                </div>
                <div class="step-label <?= $step === 2 ? 'active' : '' ?>">New Password</div>
            </div>
            <div class="step-line <?= $step > 2 ? 'done' : '' ?>"></div>
            <div class="step-item">
                <div class="step-circle <?= $step === 3 ? 'done' : '' ?>">
                    <?= $step === 3 ? '✓' : '3' ?>
                </div>
                <div class="step-label <?= $step === 3 ? 'active' : '' ?>">Done</div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="error-box">⚠️ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <!-- ── STEP 1: Verify Identity ── -->
        <?php if ($step === 1): ?>
            <div class="info-box">
                ℹ️ Enter your <strong>username</strong> and <strong>email address</strong> to verify your identity.
            </div>
            <form method="POST" action="forgot_password.php">
                <input type="hidden" name="step" value="1">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        placeholder="Your username" required autofocus>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        placeholder="Your registered email" required>
                </div>
                <button type="submit" class="btn">Verify Identity →</button>
            </form>

        <!-- ── STEP 2: Set New Password ── -->
        <?php elseif ($step === 2): ?>
            <div class="info-box">
                ✅ Identity verified! Now enter your new password.
            </div>
            <form method="POST" action="forgot_password.php">
                <input type="hidden" name="step" value="2">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" id="newpass"
                        placeholder="At least 8 characters" required
                        oninput="checkStrength(this.value)">
                    <div class="strength-bar"><div class="strength-fill" id="sBar"></div></div>
                    <div class="strength-text" id="sText">Enter a password</div>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password"
                        placeholder="Re-enter your new password" required>
                </div>
                <button type="submit" class="btn">Reset Password →</button>
            </form>

        <!-- ── STEP 3: Done ── -->
        <?php elseif ($step === 3): ?>
            <div class="success-box">
                🎉 <strong>Password reset successfully!</strong><br>
                <small>You can now login with your new password.</small>
            </div>
            <a href="login.php" class="btn-success-green">
                → Go to Login
            </a>
        <?php endif; ?>

        <div class="back-link">
            Remember your password? <a href="login.php">Back to Login</a>
        </div>

    </div>
</div>

<script>
function checkStrength(p) {
    let s = 0;
    if (p.length >= 8)           s++;
    if (/[A-Z]/.test(p))         s++;
    if (/[0-9]/.test(p))         s++;
    if (/[^A-Za-z0-9]/.test(p)) s++;
    const levels = [
        {w:'0%',   c:'#eee',    t:'Enter a password'},
        {w:'25%',  c:'#ef4444', t:'Weak'},
        {w:'50%',  c:'#f97316', t:'Fair'},
        {w:'75%',  c:'#eab308', t:'Good'},
        {w:'100%', c:'#22c55e', t:'Strong ✓'},
    ];
    document.getElementById('sBar').style.cssText = `width:${levels[s].w};background:${levels[s].c}`;
    document.getElementById('sText').textContent  = levels[s].t;
    document.getElementById('sText').style.color  = levels[s].c;
}
</script>
</body>
</html>
