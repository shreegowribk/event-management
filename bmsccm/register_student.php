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
    $full_name = trim($_POST['full_name'] ?? '');
    $usn       = strtoupper(trim($_POST['usn'] ?? ''));
    $email     = strtolower(trim($_POST['email'] ?? ''));
    $phone     = trim($_POST['phone'] ?? '');
    $course    = trim($_POST['course'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($full_name)) $errors[] = "Full name is required.";
    if (empty($usn)) $errors[] = "University Seat Number (USN) is required.";
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid college/personal email is required.";
    if (empty($phone) || !preg_match('/^[0-9]{10}$/', $phone)) $errors[] = "Phone number must be exactly 10 digits.";
    if (empty($course)) $errors[] = "Please select your academic course.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters long.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT student_id FROM students WHERE usn = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $usn, $email);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res->num_rows > 0) {
            $errors[] = "A student with this USN or Email is already registered. Please login instead.";
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

            set_flash('success', 'Registration successful! Welcome to BMSCCM Events Portal.');
            $redirect = $_GET['redirect'] ?? 'dashboard.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $errors[] = "Registration failed due to a database error. Please try again.";
            $stmt->close();
        }
    }
}

$page_title = "Student Registration";
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card bms-card border-0 shadow-lg">
                <div class="card-header bg-white border-0 pt-4 pb-0 text-center">
                    <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold mb-2">
                        <i class="bi bi-mortarboard-fill me-1"></i> Student Portal
                    </span>
                    <h3 class="fw-bold text-dark mb-1">Create Student Account</h3>
                    <p class="text-muted small">Register to browse campus fests and get your instant e-tickets.</p>
                </div>

                <div class="card-body p-4 pt-3">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger shadow-sm border-0 mb-4">
                            <h6 class="alert-heading fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please resolve the following:</h6>
                            <ul class="mb-0 ps-3 small">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= escape($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="register_student.php<?= isset($_GET['redirect']) ? '?redirect='.urlencode($_GET['redirect']) : '' ?>" novalidate>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="full_name" class="form-label small fw-bold text-secondary">Full Name</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control border-start-0" id="full_name" name="full_name" value="<?= escape($full_name) ?>" placeholder="e.g. Rahul Sharma" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="usn" class="form-label small fw-bold text-secondary">USN (University Seat No.)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-card-heading"></i></span>
                                    <input type="text" class="form-control border-start-0 text-uppercase" id="usn" name="usn" value="<?= escape($usn) ?>" placeholder="1BM21MC001" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="course" class="form-label small fw-bold text-secondary">Course / Program</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-book"></i></span>
                                    <select class="form-select border-start-0" id="course" name="course" required>
                                        <option value="">Select Course</option>
                                        <option value="BCA" <?= $course === 'BCA' ? 'selected' : '' ?>>BCA (Bachelor of Computer Applications)</option>
                                        <option value="B.Com" <?= $course === 'B.Com' ? 'selected' : '' ?>>B.Com (Bachelor of Commerce)</option>
                                        <option value="BBA" <?= $course === 'BBA' ? 'selected' : '' ?>>BBA (Bachelor of Business Admin)</option>
                                        <option value="M.Com" <?= $course === 'M.Com' ? 'selected' : '' ?>>M.Com (Master of Commerce)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="email" class="form-label small fw-bold text-secondary">Email Address</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-envelope"></i></span>
                                    <input type="email" class="form-control border-start-0" id="email" name="email" value="<?= escape($email) ?>" placeholder="name@example.com" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="phone" class="form-label small fw-bold text-secondary">Phone Number</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-phone"></i></span>
                                    <input type="tel" class="form-control border-start-0" id="phone" name="phone" value="<?= escape($phone) ?>" placeholder="10-digit mobile" maxlength="10" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="password" class="form-label small fw-bold text-secondary">Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock"></i></span>
                                    <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="Min 6 chars" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label small fw-bold text-secondary">Confirm Password</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock-fill"></i></span>
                                    <input type="password" class="form-control border-start-0" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm rounded-3">
                                <i class="bi bi-person-check-fill me-1"></i> Complete Registration
                            </button>
                        </div>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <p class="small text-muted mb-0">Already registered? 
                            <a href="login.php<?= isset($_GET['redirect']) ? '?redirect='.urlencode($_GET['redirect']) : '' ?>" class="fw-bold text-primary text-decoration-none">Sign In Here</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
