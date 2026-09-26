<?php
require_once __DIR__ . '/config/db.php';

if (is_student_logged_in()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            $error = "No student found with that USN or Email.";
        }
        $stmt->close();
    }
}

$page_title = "Student Login";
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            <div class="card bms-card border-0 shadow-lg">
                <div class="card-header bg-white border-0 pt-4 pb-0 text-center">
                    <div class="mb-2">
                        <span class="badge bg-primary bg-opacity-10 text-primary px-3 py-2 rounded-pill fw-bold">
                            <i class="bi bi-person-lock me-1"></i> Student Access
                        </span>
                    </div>
                    <h3 class="fw-bold text-dark mb-1">Sign In to BMSCCM</h3>
                    <p class="text-muted small">Enter your college credentials to manage event passes</p>
                </div>

                <div class="card-body p-4 pt-3">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center mb-4">
                            <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
                            <div><?= escape($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="login.php<?= isset($_GET['redirect']) ? '?redirect='.urlencode($_GET['redirect']) : '' ?>">
                        <div class="mb-3">
                            <label for="identifier" class="form-label small fw-bold text-secondary">USN or College Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-person"></i></span>
                                <input type="text" class="form-control border-start-0" id="identifier" name="identifier" value="<?= escape($identifier) ?>" placeholder="e.g. 1BM21MC001 or email@bmsccm.edu.in" required autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="password" class="form-label small fw-bold text-secondary mb-0">Password</label>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control border-start-0" id="password" name="password" placeholder="Enter your password" required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm rounded-3">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <p class="small text-muted mb-0">New to BMSCCM Events? 
                            <a href="register_student.php<?= isset($_GET['redirect']) ? '?redirect='.urlencode($_GET['redirect']) : '' ?>" class="fw-bold text-primary text-decoration-none">Create an Account</a>
                        </p>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="admin/admin_login.php" class="text-muted small text-decoration-none">
                    <i class="bi bi-shield-lock me-1"></i> Staff or Faculty Coordinator? Admin Portal
                </a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
