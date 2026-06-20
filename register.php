<?php
/**
 * Customer Registration Page
 * Self-service: new customers create their account here.
 * Includes: password strength validation, duplicate check, activity logging
 */
require_once __DIR__ . '/config/db.php';
startSecureSession();

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: ' . getRoleRedirect($_SESSION['role']));
    exit;
}

$errors  = [];
$success = '';
$old = ['username' => '', 'full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrf($csrf)) {
        $errors[] = 'Security token mismatch. Please refresh and try again.';
    } else {
        $username = clean($_POST['username'] ?? '');
        $fullName = clean($_POST['full_name'] ?? '');
        $email    = clean($_POST['email'] ?? '');
        $phone    = clean($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        $old = compact('username', 'email', 'phone');
        $old['full_name'] = $fullName;

        // ---- Validation ----
        if (empty($username) || strlen($username) < 4) {
            $errors[] = 'Username must be at least 4 characters.';
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Username can only contain letters, numbers, and underscores.';
        }
        if (empty($fullName) || strlen($fullName) < 3) {
            $errors[] = 'Full name is required (min 3 characters).';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if (empty($phone) || strlen($phone) < 7) {
            $errors[] = 'Phone number must be at least 7 digits.';
        }

        // Password strength
        $pwdErrors = validatePassword($password);
        $errors = array_merge($errors, $pwdErrors);

        if ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
        }

        // Duplicate username check
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Username already taken. Choose another.';
        }

        // Duplicate email check
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Email already registered.';
        }

        if (empty($errors)) {
            $hash   = password_hash($password, PASSWORD_BCRYPT);
            $role   = 'customer';
            $status = 'active';

            $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, phone, role, status) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('sssssss', $username, $hash, $fullName, $email, $phone, $role, $status);

            if ($stmt->execute()) {
                $newUserId = $conn->insert_id;
                logActivity($newUserId, 'register', "New customer registered: $username");

                // Also create a matching record in the customers table
                $parts = explode(' ', $fullName, 2);
                $fname = $parts[0];
                $lname = $parts[1] ?? '';
                $stmt2 = $conn->prepare("INSERT INTO customers (first_name, last_name, email, phone) VALUES (?,?,?,?)");
                $stmt2->bind_param('ssss', $fname, $lname, $email, $phone);
                $stmt2->execute();

                $success = 'Registration successful! You can now sign in.';
                logActivity($newUserId, 'register', 'Customer record also created');
                // Clear old input
                $old = ['username' => '', 'full_name' => '', 'email' => '', 'phone' => ''];
            } else {
                $errors[] = 'Registration failed: ' . $conn->error;
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
    <title>Register — Hotel Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/hotel-management/assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="login-wrapper">
    <div class="login-card card" style="max-width:520px;">
        <div class="card-header">
            <h3><i class="bi bi-person-plus"></i> Create Account</h3>
            <small class="text-white-50">Register as a new customer</small>
        </div>
        <div class="card-body p-4">

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                    <a href="index.php" class="alert-link">Sign in now</a>.
                </div>
            <?php endif; ?>

            <?php foreach ($errors as $err): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($err) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endforeach; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-at"></i></span>
                            <input type="text" name="username" class="form-control" required
                                   value="<?= htmlspecialchars($old['username']) ?>"
                                   pattern="[a-zA-Z0-9_]+" minlength="4" autocomplete="username">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Full Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" name="full_name" class="form-control" required
                                   value="<?= htmlspecialchars($old['full_name']) ?>" minlength="3">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                            <input type="email" name="email" class="form-control" required
                                   value="<?= htmlspecialchars($old['email']) ?>">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                            <input type="tel" name="phone" class="form-control" required
                                   value="<?= htmlspecialchars($old['phone']) ?>" minlength="7">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" id="reg_password" class="form-control"
                                   required minlength="8" autocomplete="new-password">
                            <button type="button" class="btn btn-outline-secondary toggle-password" tabindex="-1">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength mt-1" id="passwordStrength"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirm Password</label>
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
                </div>

                <!-- Password requirements hint -->
                <div class="mt-2 p-2 bg-light rounded small text-muted">
                    <strong>Password must have:</strong> 8+ characters, uppercase, lowercase, number, and special character.
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 mt-3">
                    <i class="bi bi-person-plus"></i> Register
                </button>
            </form>

            <div class="text-center mt-3">
                <span class="text-muted small">Already have an account?</span>
                <a href="index.php" class="text-decoration-none small">Sign In</a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/hotel-management/assets/js/main.js"></script>
</body>
</html>
