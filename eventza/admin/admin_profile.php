<?php
require_once __DIR__ . '/../config/db.php';
require_admin_login();

$admin_id = (int)$_SESSION['admin_id'];
$errors = [];

// Handle Admin Name Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    if (!verify_csrf_token()) {
        $errors[] = "Security token expired or invalid (CSRF protection). Please refresh and submit again.";
    } else {
        $full_name = trim($_POST['full_name'] ?? '');

        if (empty($full_name)) {
            $errors[] = "Administrator name cannot be empty.";
        } else {
            $stmt_u = $conn->prepare("UPDATE admins SET full_name = ? WHERE admin_id = ?");
            $stmt_u->bind_param("si", $full_name, $admin_id);
            if ($stmt_u->execute()) {
                $_SESSION['admin_name'] = $full_name;
                set_flash('success', 'Administrator profile name updated.');
                header('Location: admin_profile.php');
                exit;
            } else {
                $errors[] = "Failed to update profile name.";
            }
            $stmt_u->close();
        }
    }
}

// Handle Admin Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    if (!verify_csrf_token()) {
        $errors[] = "Security token expired or invalid (CSRF protection). Please refresh and submit again.";
    } else {
        $current_pass = $_POST['current_password'] ?? '';
        $new_pass     = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        if (empty($current_pass)) $errors[] = "Current password is required.";
        if (strlen($new_pass) < 6) $errors[] = "New password must be at least 6 characters.";
        if ($new_pass !== $confirm_pass) $errors[] = "New password and confirmation do not match.";

    if (empty($errors)) {
        $stmt_chk = $conn->prepare("SELECT password FROM admins WHERE admin_id = ? LIMIT 1");
        $stmt_chk->bind_param("i", $admin_id);
        $stmt_chk->execute();
        $hash = $stmt_chk->get_result()->fetch_assoc()['password'];
        $stmt_chk->close();

        if (password_verify($current_pass, $hash)) {
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmt_pwd = $conn->prepare("UPDATE admins SET password = ? WHERE admin_id = ?");
            $stmt_pwd->bind_param("si", $new_hash, $admin_id);
            $stmt_pwd->execute();
            $stmt_pwd->close();

            set_flash('success', 'Admin master password changed successfully.');
            header('Location: admin_profile.php');
            exit;
        } else {
            $errors[] = "Current administrator password is incorrect.";
        }
    }
}
}

// Fetch Admin Record
$stmt_admin = $conn->prepare("SELECT admin_id, full_name, email, created_at FROM admins WHERE admin_id = ? LIMIT 1");
$stmt_admin->bind_param("i", $admin_id);
$stmt_admin->execute();
$admin = $stmt_admin->get_result()->fetch_assoc();
$stmt_admin->close();

// System Statistics for Admin
$total_events_created = $conn->query("SELECT COUNT(*) AS total FROM events WHERE created_by = {$admin_id}")->fetch_assoc()['total'];
$total_checkins_verified = $conn->query("SELECT COUNT(*) AS total FROM registrations WHERE checked_in_by = {$admin_id}")->fetch_assoc()['total'];

$page_title = "Admin Profile & Credentials";
include __DIR__ . '/admin_header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-1">
                <i class="bi bi-person-badge text-primary me-2"></i>Administrator Account &amp; Credentials
            </h3>
            <p class="text-muted small mb-0">Update console display name and manage administrative credentials.</p>
        </div>
        <div>
            <a href="admin_dashboard.php" class="btn btn-outline-dark rounded-pill px-3 fw-bold">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger shadow-sm border-0 mb-4">
            <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>Error saving changes:</h6>
            <ul class="mb-0 ps-3 small">
                <?php foreach ($errors as $err): ?>
                    <li><?= escape($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left: Profile Info -->
        <div class="col-lg-6">
            <div class="card evenza-card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-person-circle text-primary me-2"></i>Admin Profile
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="admin_profile.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="update_profile" value="1">

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">Administrator Email</label>
                            <input type="email" class="form-control bg-light font-monospace text-muted" value="<?= escape($admin['email']) ?>" readonly>
                            <div class="form-text small">Admin email is fixed to maintain system integrity.</div>
                        </div>

                        <div class="mb-3">
                            <label for="full_name" class="form-label small fw-bold text-secondary">Administrator Display Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?= escape($admin['full_name']) ?>" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-secondary">Account Created</label>
                            <input type="text" class="form-control bg-light text-muted" value="<?= date('d M Y, h:i A', strtotime($admin['created_at'])) ?>" readonly>
                        </div>

                        <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                            <i class="bi bi-check-lg me-1"></i> Save Display Name
                        </button>
                    </form>
                </div>
            </div>

            <div class="card evenza-card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-activity text-warning me-2"></i>Administrative Audit Logs
                    </h6>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-secondary small">Campus Events Created by You:</span>
                        <span class="fw-bold fs-6 text-dark"><?= (int)$total_events_created ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <span class="text-secondary small">Student Check-Ins Verified by You:</span>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 fw-bold">
                            <?= (int)$total_checkins_verified ?> Attendees
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: Change Admin Password -->
        <div class="col-lg-6">
            <div class="card evenza-card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-shield-lock text-primary me-2"></i>Change Admin Master Password
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="admin_profile.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="change_password" value="1">

                        <div class="mb-3">
                            <label for="current_password" class="form-label small fw-bold text-secondary">Current Admin Password</label>
                            <div class="password-toggle-group">
                                <input type="password" class="form-control pe-5" id="current_password" name="current_password" required>
                                <button type="button" class="password-toggle-btn" data-target="current_password" aria-label="Toggle password visibility" title="Show or hide password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label small fw-bold text-secondary">New Master Password</label>
                            <div class="password-toggle-group">
                                <input type="password" class="form-control pe-5" id="new_password" name="new_password" minlength="6" placeholder="At least 6 characters" required>
                                <button type="button" class="password-toggle-btn" data-target="new_password" aria-label="Toggle password visibility" title="Show or hide password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="strength-meter">
                                <div class="strength-bar" id="admStrengthBar"></div>
                            </div>
                            <span class="strength-feedback text-muted" id="admStrengthText">Min 6 characters</span>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label small fw-bold text-secondary">Confirm New Password</label>
                            <div class="password-toggle-group">
                                <input type="password" class="form-control pe-5" id="confirm_password" name="confirm_password" minlength="6" required>
                                <button type="button" class="password-toggle-btn" data-target="confirm_password" aria-label="Toggle password visibility" title="Show or hide password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="small mt-1" id="admMatchStatus"></div>
                        </div>

                        <button type="submit" class="btn btn-dark rounded-pill w-100 fw-bold shadow-sm">
                            <i class="bi bi-key me-1"></i> Update Admin Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const newPwd = document.getElementById('new_password');
    const confPwd = document.getElementById('confirm_password');
    const bar = document.getElementById('admStrengthBar');
    const txt = document.getElementById('admStrengthText');
    const matchStatus = document.getElementById('admMatchStatus');

    if (newPwd) {
        newPwd.addEventListener('input', () => {
            const val = newPwd.value;
            let score = 0;
            if (val.length >= 6) score++;
            if (val.length >= 9) score++;
            if (/[0-9]/.test(val) && /[a-zA-Z]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            bar.className = 'strength-bar';
            if (val.length === 0) {
                bar.style.width = '0%';
                txt.textContent = 'Min 6 characters';
                txt.className = 'strength-feedback text-muted';
            } else if (val.length < 6 || score <= 1) {
                bar.classList.add('weak');
                txt.textContent = 'Weak password';
                txt.className = 'strength-feedback text-danger';
            } else if (score === 2 || score === 3) {
                bar.classList.add('medium');
                txt.textContent = 'Good strength';
                txt.className = 'strength-feedback text-warning';
            } else {
                bar.classList.add('strong');
                txt.textContent = 'Strong password';
                txt.className = 'strength-feedback text-success';
            }
            checkMatch();
        });
    }

    if (confPwd) {
        confPwd.addEventListener('input', checkMatch);
    }

    function checkMatch() {
        if (!confPwd || !newPwd) return;
        if (confPwd.value.length === 0) {
            matchStatus.innerHTML = '';
            return;
        }
        if (newPwd.value === confPwd.value) {
            matchStatus.innerHTML = '<span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i> Passwords match</span>';
        } else {
            matchStatus.innerHTML = '<span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill me-1"></i> Passwords do not match</span>';
        }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
