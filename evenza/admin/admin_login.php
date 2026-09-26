<?php
require_once __DIR__ . '/../config/db.php';

if (is_admin_logged_in()) {
    header('Location: admin_dashboard.php');
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $error = "Security token expired or invalid (CSRF protection). Please refresh and submit again.";
    } else {
        $email    = strtolower(trim($_POST['email'] ?? ''));
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = "Please enter both administrator email and password.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Please enter a valid administrator email address.";
        } else {
        $stmt = $conn->prepare("SELECT admin_id, full_name, email, password FROM admins WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($admin = $result->fetch_assoc()) {
            if (password_verify($password, $admin['password'])) {
                $_SESSION['admin_id']    = $admin['admin_id'];
                $_SESSION['admin_name']  = $admin['full_name'];
                $_SESSION['admin_email'] = $admin['email'];

                set_flash('success', "Welcome to Evenza Console, " . $admin['full_name'] . "!");
                header('Location: admin_dashboard.php');
                exit;
            } else {
                $error = "Invalid administrator password entered.";
            }
        } else {
            $error = "No administrator account found with this email.";
        }
        $stmt->close();
    }
}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evenza — Administrator Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-5 col-md-7">
            <div class="text-center mb-4">
                <a href="../index.php" class="text-decoration-none">
                    <span class="evenza-brand-badge py-2 px-3 fs-5" style="background: #ffffff; color: #9e7b34; border: 1px solid rgba(197, 160, 89, 0.35); box-shadow: 0 4px 12px rgba(179, 139, 67, 0.1);">
                        <i class="bi bi-stars" style="color: #c5a059;"></i> Evenza
                    </span>
                    <h4 class="fw-bold text-dark mt-2 mb-0">Evenza Administration</h4>
                    <span class="text-muted small">Faculty &amp; Coordinator Console</span>
                </a>
            </div>

            <?php render_flash(); ?>

            <div class="card evenza-card border-0 shadow-lg">
                <div class="card-header text-center py-3" style="background: linear-gradient(135deg, #fbf9f5 0%, #f4ede1 100%) !important; border-bottom: 1px solid rgba(197, 160, 89, 0.3) !important; color: #1a1714 !important;">
                    <i class="bi bi-shield-lock-fill fs-2 mb-1 d-block" style="color: #c5a059;"></i>
                    <h5 class="fw-bold mb-0" style="color: #1a1714;">Administrator Sign In</h5>
                </div>

                <div class="card-body p-4">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center mb-4">
                            <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
                            <div><?= escape($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="admin_login.php">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label for="email" class="form-label small fw-bold text-secondary">Admin Email</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                                <input type="email" class="form-control border-start-0" id="email" name="email" value="<?= escape($email) ?>" placeholder="admin@evenza.com" required autofocus>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label small fw-bold text-secondary">Password</label>
                            <div class="input-group password-toggle-group">
                                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-key"></i></span>
                                <input type="password" class="form-control border-start-0 pe-5" id="password" name="password" placeholder="Enter password" required>
                                <button type="button" class="password-toggle-btn" data-target="password" aria-label="Toggle password visibility" title="Show or hide password">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold shadow-sm rounded-3">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Evenza
                        </button>
                    </form>

                    <div class="alert alert-light border rounded-3 mt-4 mb-0 small text-secondary">
                        <i class="bi bi-info-circle text-primary me-1"></i>
                        Default admin setup available via <a href="../setup_admin.php" class="fw-bold text-decoration-none">setup_admin.php</a>:
                        <div class="mt-1 font-monospace"><strong>admin@evenza.com</strong> / <strong>Admin@123</strong></div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-4">
                <a href="../index.php" class="text-secondary small text-decoration-none">
                    <i class="bi bi-arrow-left me-1"></i> Return to Evenza Portal
                </a>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.password-toggle-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.getAttribute('data-target');
        const input = document.getElementById(targetId);
        if (!input) return;
        const icon = this.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.replace('bi-eye', 'bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.replace('bi-eye-slash', 'bi-eye');
        }
    });
});
</script>
</body>
</html>
