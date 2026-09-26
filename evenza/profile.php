<?php
require_once __DIR__ . '/config/db.php';
require_student_login();

$student_id = (int)$_SESSION['student_id'];
$errors = [];
$success_msg = '';

// Handle Profile Update (Phone & Course)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $course    = trim($_POST['course'] ?? '');

    if (empty($full_name)) $errors[] = "Full name cannot be empty.";
    if (empty($phone) || !preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = "Please provide a valid 10-digit phone number.";
    }
    if (empty($course)) $errors[] = "Please select your degree/course.";

    if (empty($errors)) {
        $stmt_upd = $conn->prepare("UPDATE students SET full_name = ?, phone = ?, course = ? WHERE student_id = ?");
        $stmt_upd->bind_param("sssi", $full_name, $phone, $course, $student_id);
        if ($stmt_upd->execute()) {
            $_SESSION['student_name']   = $full_name;
            $_SESSION['student_course'] = $course;
            set_flash('success', 'Profile details updated successfully.');
            header('Location: profile.php');
            exit;
        } else {
            $errors[] = "Failed to update profile. Please try again.";
        }
        $stmt_upd->close();
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass     = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (empty($current_pass)) $errors[] = "Current password is required.";
    if (strlen($new_pass) < 6) $errors[] = "New password must be at least 6 characters.";
    if ($new_pass !== $confirm_pass) $errors[] = "New password and confirmation do not match.";

    if (empty($errors)) {
        $stmt_chk = $conn->prepare("SELECT password FROM students WHERE student_id = ? LIMIT 1");
        $stmt_chk->bind_param("i", $student_id);
        $stmt_chk->execute();
        $hash = $stmt_chk->get_result()->fetch_assoc()['password'];
        $stmt_chk->close();

        if (password_verify($current_pass, $hash)) {
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmt_pwd = $conn->prepare("UPDATE students SET password = ? WHERE student_id = ?");
            $stmt_pwd->bind_param("si", $new_hash, $student_id);
            $stmt_pwd->execute();
            $stmt_pwd->close();

            set_flash('success', 'Your password was changed successfully.');
            header('Location: profile.php');
            exit;
        } else {
            $errors[] = "Current password is incorrect.";
        }
    }
}

// Fetch Fresh Student Data
$stmt_stu = $conn->prepare("SELECT student_id, full_name, usn, email, phone, course, created_at FROM students WHERE student_id = ? LIMIT 1");
$stmt_stu->bind_param("i", $student_id);
$stmt_stu->execute();
$student = $stmt_stu->get_result()->fetch_assoc();
$stmt_stu->close();

// Registration Metrics
$stmt_stats = $conn->prepare("
    SELECT COUNT(*) AS total_regs,
           SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed_regs,
           SUM(CASE WHEN checked_in = 1 THEN 1 ELSE 0 END) AS checked_in_regs
    FROM registrations
    WHERE student_id = ?
");
$stmt_stats->bind_param("i", $student_id);
$stmt_stats->execute();
$stats = $stmt_stats->get_result()->fetch_assoc();
$stmt_stats->close();

$page_title = "My Profile & Security";
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-1">
                <i class="bi bi-person-gear text-primary me-2"></i>Account &amp; Security Settings
            </h3>
            <p class="text-muted small mb-0">Manage your student profile information and login credentials.</p>
        </div>
        <div>
            <a href="dashboard.php" class="btn btn-outline-dark rounded-pill px-3 fw-bold">
                <i class="bi bi-pass me-1"></i> My Registrations
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger shadow-sm border-0 mb-4">
            <h6 class="fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>Unable to save changes:</h6>
            <ul class="mb-0 ps-3 small">
                <?php foreach ($errors as $err): ?>
                    <li><?= escape($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left: Profile Info Form -->
        <div class="col-lg-7">
            <div class="card evenza-card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-person-lines-fill text-primary me-2"></i>Student Details
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="profile.php">
                        <input type="hidden" name="update_profile" value="1">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">University Seat Number (USN)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control bg-light font-monospace fw-bold text-primary" id="profileUsnVal" value="<?= escape($student['usn']) ?>" readonly>
                                    <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= escape($student['usn']) ?>'); showEvenzaToast('USN copied to clipboard: <?= escape($student['usn']) ?>');" title="Copy USN">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <div class="form-text small">USN is permanent and verified with college records.</div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">College Email ID</label>
                                <div class="input-group">
                                    <input type="email" class="form-control bg-light text-muted" id="profileEmailVal" value="<?= escape($student['email']) ?>" readonly>
                                    <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('<?= escape($student['email']) ?>'); showEvenzaToast('Email copied to clipboard!');" title="Copy Email">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                                <div class="form-text small">Registered institutional email address.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="full_name" class="form-label small fw-bold text-secondary">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?= escape($student['full_name']) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label small fw-bold text-secondary">Contact Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?= escape($student['phone']) ?>" maxlength="10" pattern="[0-9]{10}" oninput="this.value = this.value.replace(/[^0-9]/g, '')" placeholder="10-digit mobile" required>
                            </div>

                            <div class="col-md-6">
                                <label for="course" class="form-label small fw-bold text-secondary">Degree / Department</label>
                                <select class="form-select" id="course" name="course" required>
                                    <?php 
                                    $courses = ['BCA', 'BBA', 'B.Com', 'B.Sc', 'MCA', 'MBA', 'M.Com'];
                                    foreach ($courses as $c):
                                    ?>
                                        <option value="<?= $c ?>" <?= ($student['course'] === $c) ? 'selected' : '' ?>><?= $c ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-secondary">Registered Since</label>
                                <input type="text" class="form-control bg-light text-muted" value="<?= date('d M Y', strtotime($student['created_at'])) ?>" readonly>
                            </div>

                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                                    <i class="bi bi-check-lg me-1"></i> Save Profile Changes
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Security & Stats -->
        <div class="col-lg-5">
            <!-- Password Change Card -->
            <div class="card evenza-card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-shield-lock text-primary me-2"></i>Change Password
                    </h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST" action="profile.php">
                        <input type="hidden" name="change_password" value="1">

                        <div class="mb-3">
                            <label for="current_password" class="form-label small fw-bold text-secondary">Current Password</label>
                            <div class="password-toggle-group">
                                <input type="password" class="form-control pe-5" id="current_password" name="current_password" required>
                                <button type="button" class="password-toggle-btn" data-target="current_password" aria-label="Toggle password visibility" title="Show or hide password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="new_password" class="form-label small fw-bold text-secondary">New Password</label>
                            <div class="password-toggle-group">
                                <input type="password" class="form-control pe-5" id="new_password" name="new_password" minlength="6" placeholder="At least 6 characters" required>
                                <button type="button" class="password-toggle-btn" data-target="new_password" aria-label="Toggle password visibility" title="Show or hide password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="strength-meter">
                                <div class="strength-bar" id="profStrengthBar"></div>
                            </div>
                            <span class="strength-feedback text-muted" id="profStrengthText">Min 6 characters</span>
                        </div>

                        <div class="mb-4">
                            <label for="confirm_password" class="form-label small fw-bold text-secondary">Confirm New Password</label>
                            <div class="password-toggle-group">
                                <input type="password" class="form-control pe-5" id="confirm_password" name="confirm_password" minlength="6" required>
                                <button type="button" class="password-toggle-btn" data-target="confirm_password" aria-label="Toggle password visibility" title="Show or hide password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="small mt-1" id="profMatchStatus"></div>
                        </div>

                        <button type="submit" class="btn btn-dark rounded-pill w-100 fw-bold shadow-sm">
                            <i class="bi bi-key me-1"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Participation Summary Card -->
            <div class="card evenza-card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-award text-warning me-2"></i>Event Participation Overview
                    </h6>
                </div>
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-secondary small">Total Event Bookings:</span>
                        <span class="fw-bold fs-6 text-dark"><?= (int)$stats['total_regs'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-secondary small">Confirmed Registrations:</span>
                        <span class="fw-bold fs-6 text-success"><?= (int)$stats['confirmed_regs'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <span class="text-secondary small">Attended (Checked In):</span>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 fw-bold">
                            <?= (int)$stats['checked_in_regs'] ?> Events
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const newPwd = document.getElementById('new_password');
    const confPwd = document.getElementById('confirm_password');
    const bar = document.getElementById('profStrengthBar');
    const txt = document.getElementById('profStrengthText');
    const matchStatus = document.getElementById('profMatchStatus');

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

<?php include __DIR__ . '/includes/footer.php'; ?>

