<?php
require_once __DIR__ . '/config/db.php';

if (is_student_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = "Security token expired or invalid (CSRF protection). Please refresh and submit again.";
    } else {
        $identifier = trim($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = "Please enter both USN/Email and Password.";
    } else {
        $stmt = $conn->prepare("SELECT student_id, full_name, usn, email, course, password FROM students WHERE usn = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $identifier, $identifier);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            if (password_verify($password, $row['password'])) {
                $_SESSION['student_id']     = $row['student_id'];
                $_SESSION['student_name']   = $row['full_name'];
                $_SESSION['student_usn']    = $row['usn'];
                $_SESSION['student_email']  = $row['email'];
                $_SESSION['student_course'] = $row['course'];

                set_flash('success', "Welcome back, " . $row['full_name'] . "!");
                $redirect = $_GET['redirect'] ?? 'dashboard.php';
                header('Location: ' . $redirect);
                exit;
            } else {
                $error = "Invalid password entered. Please try again.";
            }
        } else {
            $error = "No student account found with that USN or Email.";
        }
        $stmt->close();
    }
}
}

$page_title = "Student Sign In";
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            <div class="card event-card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-4 pb-0 text-center">
                    <div class="d-inline-flex align-items-center justify-content-center brand-monogram mb-2" style="width: 44px; height: 44px; font-size: 1.25rem;">
                        E
                    </div>
                    <h4 class="fw-bold text-dark mb-1">Student Sign In</h4>
                    <p class="text-muted small">Access your Evenza event passes and registrations</p>
                </div>

                <div class="card-body p-4 pt-2">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center mb-4">
                            <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
                            <div><?= escape($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php<?= isset($_GET['redirect']) ? '?redirect='.urlencode($_GET['redirect']) : '' ?>">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label for="identifier" class="form-label small fw-bold text-secondary">University Seat Number (USN) or Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control border-start-0" id="identifier" name="identifier" value="<?= escape($identifier) ?>" placeholder="e.g. 1EV21MC001" required autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label small fw-bold text-secondary mb-1">Password</label>
                            <div class="input-group password-toggle-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-key"></i></span>
                                <input type="password" class="form-control border-start-0 pe-5" id="password" name="password" placeholder="Enter password" required>
                                <button type="button" class="password-toggle-btn" data-target="password" aria-label="Toggle password visibility" title="Show or hide password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold shadow-sm rounded-pill">
                            Sign In to Evenza
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <p class="small text-muted mb-0">Don't have an account? 
                            <a href="register_student.php<?= isset($_GET['redirect']) ? '?redirect='.urlencode($_GET['redirect']) : '' ?>" class="fw-semibold text-primary text-decoration-none">Register here</a>
                        </p>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="admin/admin_login.php" class="text-secondary small text-decoration-none">
                    <i class="bi bi-shield-lock me-1"></i> Faculty Coordinator Portal &rarr;
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
