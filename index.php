<?php
/**
 * Secure Login Page — Hotel Management System
 * Features: bcrypt hashing, rate limiting, account lockout, CSRF,
 *           session regeneration, activity logging, role-based redirect
 */
require_once __DIR__ . '/config/db.php';
startSecureSession();

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . getRoleRedirect($_SESSION['role']));
    exit;
}

$error   = '';
$success = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF verification (if token present in form)
    $csrf = $_POST['csrf_token'] ?? '';
    if (!empty($csrf) && !verifyCsrf($csrf)) {
        $error = 'Security token mismatch. Please refresh the page and try again.';
    } else {
        $username = clean($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $stmt = $conn->prepare("SELECT user_id, username, password, full_name, email, role, status FROM users WHERE username = ? LIMIT 1");
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Check if account is locked
                $lockSec = getAccountLockSeconds($user['user_id']);
                if ($lockSec > 0) {
                    $mins = ceil($lockSec / 60);
                    $error = "Account locked due to too many failed attempts. Try again in {$mins} minute(s).";
                    logActivity($user['user_id'], 'failed_login', "Account locked — {$lockSec}s remaining");
                } elseif (!password_verify($password, $user['password'])) {
                    // Failed password
                    recordFailedLogin($user['user_id']);
                    logActivity($user['user_id'], 'failed_login', 'Wrong password');
                    $remaining = 5 - $user['failed_attempts'] - 1;
                    if ($remaining > 0) {
                        $error = "Invalid password. {$remaining} attempt(s) remaining before lockout.";
                    } elseif ($remaining <= 0) {
                        $error = 'Too many failed attempts. Account locked for 15 minutes.';
                    } else {
                        $error = 'Invalid username or password.';
                    }
                } elseif ($user['status'] !== 'active') {
                    $error = 'Your account is ' . $user['status'] . '. Contact the administrator.';
                    logActivity($user['user_id'], 'failed_login', 'Account status: ' . $user['status']);
                } else {
                    // ---- Successful login ----
                    session_regenerate_id(true);

                    $_SESSION['user_id']   = $user['user_id'];
                    $_SESSION['username']  = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email']     = $user['email'];
                    $_SESSION['role']      = $user['role'];
                    $_SESSION['login_time'] = time();

                    clearFailedLogins($user['user_id']);
                    logActivity($user['user_id'], 'login', 'Successful login');

                    header('Location: ' . getRoleRedirect($user['role']));
                    exit;
                }
            } else {
                // Username not found — don't reveal whether account exists
                $error = 'Invalid username or password.';
                logActivity(null, 'failed_login', "Unknown username: $username");
            }
            $stmt->close();
        }
    }
}

// Generate fresh CSRF token for this form
$csrfToken = csrfToken();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hotel Management System — Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/hotel-management/assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="login-wrapper">
    <div class="login-card card">
        <div class="card-header">
            <h3><i class="bi bi-building"></i> Hotel Management</h3>
            <small class="text-white-50">Sign in to your account</small>
        </div>
        <div class="card-body p-4">

            <?php if ($flash): ?>
                <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username"
                               placeholder="Enter your username" required autofocus
                               value="<?= htmlspecialchars($username) ?>" autocomplete="username">
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Enter your password" required autocomplete="current-password">
                        <button type="button" class="btn btn-outline-secondary" id="togglePassword" tabindex="-1">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 mt-2">
                    <i class="bi bi-box-arrow-in-right"></i> Sign In
                </button>
            </form>

            <div class="d-flex justify-content-between mt-3">
                <a href="forgot_password.php" class="text-decoration-none small">
                    <i class="bi bi-question-circle"></i> Forgot Password?
                </a>
                <a href="register.php" class="text-decoration-none small">
                    <i class="bi bi-person-plus"></i> Create Account
                </a>
            </div>

            <hr>
            <div class="text-center text-muted small">
                <strong>Demo Credentials:</strong><br>
                admin / password &nbsp;|&nbsp; reception / password<br>
                accountant / password &nbsp;|&nbsp; customer1 / password
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/hotel-management/assets/js/main.js"></script>
</body>
</html>
