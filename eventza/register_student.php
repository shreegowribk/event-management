<?php
require_once __DIR__ . '/config/db.php';

if (is_student_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$full_name = '';
$usn = '';
$email = '';
$phone = '';
$course = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = "Security token expired or invalid (CSRF protection). Please refresh and submit again.";
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $usn       = strtoupper(trim($_POST['usn'] ?? ''));
        $email     = strtolower(trim($_POST['email'] ?? ''));
        $phone     = trim($_POST['phone'] ?? '');
        $course    = trim($_POST['course'] ?? '');
        $password  = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($full_name)) $errors[] = "Full name is required.";
    if (empty($usn)) $errors[] = "University Seat Number (USN) is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (empty($phone) || !preg_match('/^[0-9]{10}$/', $phone)) $errors[] = "Phone number must be exactly 10 digits.";
    if (empty($course)) $errors[] = "Please select your course.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters long.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT student_id FROM students WHERE usn = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $usn, $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $errors[] = "A student with this USN or Email already exists. Please sign in.";
        }
        $stmt->close();
    }

    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("INSERT INTO students (full_name, usn, email, phone, course, password) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $full_name, $usn, $email, $phone, $course, $hashed_password);

        if ($stmt->execute()) {
            $new_student_id = $stmt->insert_id;
            $stmt->close();

            $_SESSION['student_id']     = $new_student_id;
            $_SESSION['student_name']   = $full_name;
            $_SESSION['student_usn']    = $usn;
            $_SESSION['student_email']  = $email;
            $_SESSION['student_course'] = $course;

            set_flash('success', 'Account created successfully! Welcome to Evenza.');
            $redirect = $_GET['redirect'] ?? 'dashboard.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $errors[] = "Registration failed due to a database error. Please try again.";
            $stmt->close();
        }
    }
}
}

$page_title = "Student Registration";
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card event-card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-4 pb-0 text-center">
                    <div class="d-inline-flex align-items-center justify-content-center brand-monogram mb-2" style="width: 44px; height: 44px; font-size: 1.25rem;">
                        E
                    </div>
                    <h4 class="fw-bold text-dark mb-1">Create Student Profile</h4>
                    <p class="text-muted small">Register with your college details to book event seats</p>
                </div>

                <div class="card-body p-4 pt-2">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger shadow-sm border-0 mb-4">
                            <h6 class="alert-heading fw-bold mb-2 small"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please resolve the following:</h6>
                            <ul class="mb-0 ps-3 small">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= escape($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="register_student.php<?= isset($_GET['redirect']) ? '?redirect='.urlencode($_GET['redirect']) : '' ?>" novalidate>
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="full_name" class="form-label small fw-bold text-secondary">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?= escape($full_name) ?>" placeholder="e.g. Rahul Sharma" required>
                            </div>

                            <div class="col-md-6">
                                <label for="usn" class="form-label small fw-bold text-secondary">University Seat Number (USN)</label>
                                <input type="text" class="form-control text-uppercase mono-font" id="usn" name="usn" value="<?= escape($usn) ?>" placeholder="1EV21MC001" required>
                            </div>

                            <div class="col-md-6">
                                <label for="course" class="form-label small fw-bold text-secondary">Program / Degree</label>
                                <select class="form-select" id="course" name="course" required>
                                    <option value="">Select Degree</option>
                                    <option value="BCA" <?= $course === 'BCA' ? 'selected' : '' ?>>BCA (Bachelor of Computer Applications)</option>
                                    <option value="B.Com" <?= $course === 'B.Com' ? 'selected' : '' ?>>B.Com (Bachelor of Commerce)</option>
                                    <option value="BBA" <?= $course === 'BBA' ? 'selected' : '' ?>>BBA (Bachelor of Business Admin)</option>
                                    <option value="M.Com" <?= $course === 'M.Com' ? 'selected' : '' ?>>M.Com (Master of Commerce)</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label small fw-bold text-secondary">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?= escape($email) ?>" placeholder="student@example.com" required>
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label small fw-bold text-secondary">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?= escape($phone) ?>" placeholder="10-digit mobile" maxlength="10" pattern="[0-9]{10}" oninput="this.value = this.value.replace(/[^0-9]/g, '')" required>
                            </div>

                            <div class="col-md-6">
                                <label for="password" class="form-label small fw-bold text-secondary">Password</label>
                                <div class="password-toggle-group">
                                    <input type="password" class="form-control pe-5" id="password" name="password" placeholder="Min 6 characters" required>
                                    <button type="button" class="password-toggle-btn" data-target="password" aria-label="Toggle password visibility" title="Show or hide password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="strength-meter">
                                    <div class="strength-bar" id="regStrengthBar"></div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="strength-feedback text-muted" id="regStrengthText">Enter a strong password</span>
                                    <span class="small text-muted" style="font-size: 0.72rem;">Min 6 chars</span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label small fw-bold text-secondary">Confirm Password</label>
                                <div class="password-toggle-group">
                                    <input type="password" class="form-control pe-5" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>
                                    <button type="button" class="password-toggle-btn" data-target="confirm_password" aria-label="Toggle password visibility" title="Show or hide password">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="small mt-1" id="regMatchStatus"></div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" id="submitRegBtn" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm rounded-pill">
                                Create Account
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <p class="small text-muted mb-0">Already have an account? 
                            <a href="login.php<?= isset($_GET['redirect']) ? '?redirect='.urlencode($_GET['redirect']) : '' ?>" class="fw-semibold text-primary text-decoration-none">Sign In</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const pwd = document.getElementById('password');
    const confirmPwd = document.getElementById('confirm_password');
    const bar = document.getElementById('regStrengthBar');
    const txt = document.getElementById('regStrengthText');
    const matchStatus = document.getElementById('regMatchStatus');
    const usnInput = document.getElementById('usn');

    if (usnInput) {
        usnInput.addEventListener('input', () => {
            usnInput.value = usnInput.value.toUpperCase().trim();
        });
    }

    if (pwd) {
        pwd.addEventListener('input', () => {
            const val = pwd.value;
            let score = 0;
            if (val.length >= 6) score++;
            if (val.length >= 9) score++;
            if (/[0-9]/.test(val) && /[a-zA-Z]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            bar.className = 'strength-bar';
            if (val.length === 0) {
                bar.style.width = '0%';
                txt.textContent = 'Enter a strong password';
                txt.className = 'strength-feedback text-muted';
            } else if (val.length < 6 || score <= 1) {
                bar.classList.add('weak');
                txt.textContent = 'Weak (add numbers & symbols)';
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

    if (confirmPwd) {
        confirmPwd.addEventListener('input', checkMatch);
    }

    function checkMatch() {
        if (!confirmPwd || !pwd) return;
        if (confirmPwd.value.length === 0) {
            matchStatus.innerHTML = '';
            return;
        }
        if (pwd.value === confirmPwd.value) {
            matchStatus.innerHTML = '<span class="text-success fw-semibold"><i class="bi bi-check-circle-fill me-1"></i> Passwords match</span>';
        } else {
            matchStatus.innerHTML = '<span class="text-danger fw-semibold"><i class="bi bi-x-circle-fill me-1"></i> Passwords do not match</span>';
        }
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
