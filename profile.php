<?php
/**
 * Profile Page — All authenticated roles
 * View profile info, change password, view login activity
 */
require_once __DIR__ . '/config/db.php';
startSecureSession();
$pageTitle  = 'My Profile';
$activePage = 'profile';
requireLogin();

// ---- Handle password change ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    enforceCsrf();

    $action = cleanEnum($_POST['action'] ?? '', ['update_profile','change_password'], '');

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $newPwd  = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $uid = (int)$_SESSION['user_id'];
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!password_verify($current, $row['password'])) {
            setFlash('danger', 'Current password is incorrect.');
        } else {
            $pwdErrors = validatePassword($newPwd);
            if (!empty($pwdErrors)) {
                setFlash('danger', implode(' ', $pwdErrors));
            } elseif ($newPwd !== $confirm) {
                setFlash('danger', 'New passwords do not match.');
            } elseif ($current === $newPwd) {
                setFlash('danger', 'New password must be different from current password.');
            } else {
                $hash = password_hash($newPwd, PASSWORD_BCRYPT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $stmt->bind_param('si', $hash, $uid);
                if ($stmt->execute()) {
                    logActivity($uid, 'password_reset', 'Password changed from profile');
                    setFlash('success', 'Password changed successfully.');
                } else {
                    setFlash('danger', 'Failed to update password.');
                }
            }
        }
    } elseif ($action === 'update_profile') {
        $fullName = clean($_POST['full_name'] ?? '');
        $email    = cleanEmail($_POST['email'] ?? '');
        $phone    = clean($_POST['phone'] ?? '');
        $uid      = (int)$_SESSION['user_id'];

        // Check email uniqueness
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->bind_param('si', $email, $uid);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            setFlash('danger', 'Email is already used by another account.');
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE user_id = ?");
            $stmt->bind_param('sssi', $fullName, $email, $phone, $uid);
            if ($stmt->execute()) {
                $_SESSION['full_name'] = $fullName;
                $_SESSION['email']     = $email;
                setFlash('success', 'Profile updated.');
            } else {
                setFlash('danger', 'Failed to update profile.');
            }
        }
    }
    header('Location: profile.php');
    exit;
}

require_once __DIR__ . '/includes/header.php';

// Fetch current user data
$uid = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT user_id, username, full_name, email, phone, role, status, created_at FROM users WHERE user_id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Recent login activity for this user
$stmt = $conn->prepare("SELECT * FROM login_activity WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->bind_param('i', $uid);
$stmt->execute();
$activity = $stmt->get_result();
?>

<div class="container-fluid px-4 py-3">
    <h4 class="mb-4"><i class="bi bi-person-circle"></i> My Profile</h4>

    <div class="row g-4">
        <!-- Profile Card -->
        <div class="col-lg-5">
            <div class="card table-card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-person"></i> Profile Info</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="update_profile">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" disabled>
                            <small class="text-muted">Username cannot be changed.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control"
                                   value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="<?= htmlspecialchars($user['email']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control"
                                   value="<?= htmlspecialchars($user['phone']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="<?= ucfirst($user['role']) ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Account Status</label>
                            <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'secondary') ?> ms-1">
                                <?= ucfirst($user['status']) ?>
                            </span>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Member Since</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['created_at']) ?>" disabled>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Update Profile
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="col-lg-7">
            <div class="card table-card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <div class="input-group">
                                <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                                <button type="button" class="btn btn-outline-secondary toggle-password" tabindex="-1"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="new_pwd" class="form-control" required minlength="8" autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary toggle-password" tabindex="-1"><i class="bi bi-eye"></i></button>
                            </div>
                            <div class="password-strength mt-1" id="passwordStrength"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control" required autocomplete="new-password">
                                <button type="button" class="btn btn-outline-secondary toggle-password" tabindex="-1"><i class="bi bi-eye"></i></button>
                            </div>
                            <div id="matchIndicator" class="mt-1 small"></div>
                        </div>
                        <div class="p-2 bg-light rounded small text-muted mb-3">
                            <strong>Requirements:</strong> 8+ characters, uppercase, lowercase, number, and special character.
                        </div>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-shield-check"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Login Activity -->
            <div class="card table-card">
                <div class="card-header bg-white">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activity</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Action</th><th>IP Address</th><th>Date</th><th>Details</th></tr></thead>
                        <tbody>
                            <?php if ($activity && $activity->num_rows > 0): while ($a = $activity->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $iconClass = match($a['action']) {
                                            'login'          => 'text-success',
                                            'logout'         => 'text-secondary',
                                            'failed_login'   => 'text-danger',
                                            'password_reset' => 'text-warning',
                                            'register'       => 'text-info',
                                            default          => 'text-muted',
                                        };
                                        ?>
                                        <i class="bi bi-<?= match($a['action']) {
                                            'login' => 'box-arrow-in-right',
                                            'logout' => 'box-arrow-left',
                                            'failed_login' => 'exclamation-triangle',
                                            'password_reset' => 'key',
                                            'register' => 'person-plus',
                                            default => 'circle',
                                        } ?> <?= $iconClass ?>"></i>
                                        <?= ucfirst(str_replace('_',' ',$a['action'])) ?>
                                    </td>
                                    <td><code><?= htmlspecialchars($a['ip_address']) ?></code></td>
                                    <td><?= $a['created_at'] ?></td>
                                    <td><?= htmlspecialchars($a['details']) ?></td>
                                </tr>
                            <?php endwhile; else: ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No activity yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
