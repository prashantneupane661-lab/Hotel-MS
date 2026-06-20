<?php
/**
 * Forgot Password — Email-based token generation
 * Generates a secure reset token and displays the reset link.
 * In production, the link would be sent via email (PHPMailer).
 * For this local demo, the link is shown on screen after submission.
 */
require_once __DIR__ . '/config/db.php';
startSecureSession();

if (isLoggedIn()) {
    header('Location: ' . getRoleRedirect($_SESSION['role']));
    exit;
}

$error   = '';
$success = '';
$resetLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf_token'] ?? '';
    if (!verifyCsrf($csrf)) {
        $error = 'Security token mismatch.';
    } else {
        $email = clean($_POST['email'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $stmt = $conn->prepare("SELECT user_id, username, full_name FROM users WHERE email = ? AND status != 'inactive'");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                $token = generateResetToken($user['user_id']);

                logActivity($user['user_id'], 'password_reset', 'Reset token generated');

                // Build the reset URL
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $resetLink = "$protocol://$host/hotel-management/reset_password.php?token=$token";

                /*
                 * ---- PRODUCTION EMAIL (uncomment & configure SMTP) ----
                 *
                 * require_once __DIR__ . '/vendor/PHPMailer/src/PHPMailer.php';
                 * $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                 * $mail->isSMTP();
                 * $mail->Host = 'smtp.example.com';
                 * $mail->SMTPAuth = true;
                 * $mail->Username = 'noreply@hotel.com';
                 * $mail->Password = 'your_smtp_pass';
                 * $mail->SMTPSecure = 'tls';
                 * $mail->Port = 587;
                 * $mail->setFrom('noreply@hotel.com', 'Hotel Management');
                 * $mail->addAddress($email, $user['full_name']);
                 * $mail->isHTML(true);
                 * $mail->Subject = 'Password Reset Request';
                 * $mail->Body = "<p>Hello {$user['full_name']},</p>
                 *     <p>Click the link below to reset your password (valid for 1 hour):</p>
                 *     <p><a href=\"$resetLink\">$resetLink</a></p>
                 *     <p>If you did not request this, ignore this email.</p>";
                 * $mail->send();
                 */

                $success = 'A password reset link has been generated. Check your email.';
            } else {
                // Don't reveal whether email exists — always show the same message
                $success = 'If an account with that email exists, a reset link has been sent.';
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
    <title>Forgot Password — Hotel Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/hotel-management/assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="login-wrapper">
    <div class="login-card card">
        <div class="card-header">
            <h3><i class="bi bi-key"></i> Forgot Password</h3>
            <small class="text-white-50">Enter your email to receive a reset link</small>
        </div>
        <div class="card-body p-4">

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($resetLink): ?>
                <!-- DEMO ONLY: show reset link on screen (production sends via email) -->
                <div class="alert alert-info">
                    <strong><i class="bi bi-link-45deg"></i> Demo Reset Link:</strong><br>
                    <a href="<?= htmlspecialchars($resetLink) ?>" class="text-break"><?= htmlspecialchars($resetLink) ?></a>
                </div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email"
                               placeholder="your@email.com" required autofocus>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-2 mt-2">
                    <i class="bi bi-send"></i> Send Reset Link
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="index.php" class="text-decoration-none small">
                    <i class="bi bi-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
