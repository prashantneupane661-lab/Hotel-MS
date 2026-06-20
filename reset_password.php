<?php
/**
 * Reset Password — Token validation + new password form
 * Validates the token from forgot_password.php, enforces password strength,
 * then updates the user's password and marks the token as used.
 */
require_once __DIR__ . '/config/db.php';
startSecureSession();

$token   = clean($_GET['token'] ?? '');
$error   = '';
$success = '';
$tokenData = null;

// Validate token
if (empty($token)) {
    $error = 'Invalid or missing reset token.';
} else {
    $tokenData = validateResetToken($token);
    if (!$tokenData) {
        $error = 'This reset link has expired or has already been used. Please request a new one.';
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenData) {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrf($csrf)) {
        $error = 'Security token mismatch.';
    } else {
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        $pwdErrors = validatePassword($password);
        if (!empty($pwdErrors)) {
            $error = implode(' ', $pwdErrors);
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $userId = (int)$tokenData['user_id'];

            $stmt = $conn->prepare("UPDATE users SET password = ?, failed_attempts = 0, locked_until = NULL WHERE user_id = ?");
            $stmt->bind_param('si', $hash, $userId);

            if ($stmt->execute()) {
                // Mark token as used
                $stmt2 = $conn->prepare("UPDATE password_reset_tokens SET used = 1, used_at = NOW() WHERE token = ?");
                $stmt2->bind_param('s', $token);
                $stmt2->execute();

                // Invalidate all sessions for this user (force re-login)
                logActivity($userId, 'password_reset', 'Password changed via reset link');

                $success = 'Password updated successfully! You can now sign in with your new password.';
                $tokenData = null; // Prevent form from showing again
            } else {
                $error = 'Failed to update password: ' . $conn->error;
            }
        }
    }
}

$csrfToken = csrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password — Hotel Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/hotel-management/assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="login-wrapper">
    <div class="login-card card" style="max-width:480px;">
        <div class="card-header">
            <h3><i class="bi bi-shield-lock"></i> Reset Password</h3>
            <?php if ($tokenData): ?>
                <small class="text-white-50">Resetting password for: <strong><?= htmlspecialchars($tokenData['username']) ?></strong></small>
            <?php endif; ?>
        </div>
        <div class="card-body p-4">

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                </div>
                <div class="text-center mt-3">
                    <a href="forgot_password.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-repeat"></i> Request New Link
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary ms-2">
                        <i class="bi bi-arrow-left"></i> Back to Login
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
                <div class="text-center mt-3">
                    <a href="index.php" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right"></i> Sign In Now
                    </a>
                </div>
            <?php endif; ?>

            <?php if ($tokenData && !$success && !$error): ?>
                <form method="POST" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" id="reset_password" class="form-control"
                                   required minlength="8" autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary toggle-password" tabindex="-1">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength mt-1" id="passwordStrength"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                                   required autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary toggle-password" tabindex="-1">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div id="matchIndicator" class="mt-1 small"></div>
                    </div>

                    <div class="p-2 bg-light rounded small text-muted">
                        <strong>Password must have:</strong> 8+ chars, uppercase, lowercase, number, special character.
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 mt-3">
                        <i class="bi bi-shield-check"></i> Update Password
                    </button>
                </form>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/hotel-management/assets/js/main.js"></script>
</body>
</html>
