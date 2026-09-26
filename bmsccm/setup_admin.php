<?php
require_once __DIR__ . '/config/db.php';

$is_cli = (php_sapi_name() === 'cli');

$default_admin_name  = "BMSCCM Event Administrator";
$default_admin_email = "admin@bmsccm.edu.in";
$default_admin_pass  = "Admin@123";

$default_student_name  = "Rahul Sharma";
$default_student_usn   = "1BM21MC001";
$default_student_email = "rahul.sharma@bmsccm.edu.in";
$default_student_phone = "9876543210";
$default_student_course= "BCA";
$default_student_pass  = "Student@123";

$messages = [];
$status = 'success';

try {
    $admin_hash = password_hash($default_admin_pass, PASSWORD_BCRYPT);

    $stmt = $conn->prepare("SELECT admin_id FROM admins WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $default_admin_email);
    $stmt->execute();
    $admin_res = $stmt->get_result();

    if ($row = $admin_res->fetch_assoc()) {
        $stmt->close();
        $upd_stmt = $conn->prepare("UPDATE admins SET full_name = ?, password = ? WHERE admin_id = ?");
        $upd_stmt->bind_param("ssi", $default_admin_name, $admin_hash, $row['admin_id']);
        $upd_stmt->execute();
        $upd_stmt->close();
        $messages[] = "Default Administrator updated with fresh bcrypt hash for password.";
    } else {
        $stmt->close();
        $ins_stmt = $conn->prepare("INSERT INTO admins (full_name, email, password) VALUES (?, ?, ?)");
        $ins_stmt->bind_param("sss", $default_admin_name, $default_admin_email, $admin_hash);
        $ins_stmt->execute();
        $ins_stmt->close();
        $messages[] = "Default Administrator created successfully in database.";
    }

    $student_hash = password_hash($default_student_pass, PASSWORD_BCRYPT);

    $stmt_s = $conn->prepare("SELECT student_id FROM students WHERE usn = ? OR email = ? LIMIT 1");
    $stmt_s->bind_param("ss", $default_student_usn, $default_student_email);
    $stmt_s->execute();
    $stu_res = $stmt_s->get_result();

    if ($stu_row = $stu_res->fetch_assoc()) {
        $stmt_s->close();
        $upd_s = $conn->prepare("UPDATE students SET full_name = ?, password = ?, phone = ?, course = ? WHERE student_id = ?");
        $upd_s->bind_param("ssssi", $default_student_name, $student_hash, $default_student_phone, $default_student_course, $stu_row['student_id']);
        $upd_s->execute();
        $upd_s->close();
        $messages[] = "Sample Demo Student ({$default_student_usn}) verified and ready.";
    } else {
        $stmt_s->close();
        $ins_s = $conn->prepare("INSERT INTO students (full_name, usn, email, phone, course, password) VALUES (?, ?, ?, ?, ?, ?)");
        $ins_s->bind_param("ssssss", $default_student_name, $default_student_usn, $default_student_email, $default_student_phone, $default_student_course, $student_hash);
        $ins_s->execute();
        $ins_s->close();
        $messages[] = "Sample Demo Student ({$default_student_usn}) created for immediate testing.";
    }

} catch (Exception $e) {
    $status = 'error';
    $messages[] = "Setup failed: " . $e->getMessage();
}

if ($is_cli) {
    echo "========================================================\n";
    echo " BMSCCM Event Management System - Setup Utility\n";
    echo "========================================================\n";
    foreach ($messages as $msg) {
        echo " - " . $msg . "\n";
    }
    echo "\nDefault Credentials:\n";
    echo " Admin Email:    " . $default_admin_email . "\n";
    echo " Admin Password: " . $default_admin_pass . "\n";
    echo " Student USN:    " . $default_student_usn . "\n";
    echo " Student Pass:   " . $default_student_pass . "\n";
    echo "========================================================\n";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Setup Utility | BMSCCM Events</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            <div class="card bms-card border-0 shadow-lg">
                <div class="card-header bg-dark text-white text-center py-4" style="background: linear-gradient(135deg, #0b192c 0%, #1e3e62 100%) !important;">
                    <i class="bi bi-shield-check text-warning fs-1 mb-2 d-block"></i>
                    <h3 class="fw-bold mb-1">BMSCCM System Initialization</h3>
                    <p class="text-white-50 small mb-0">Secure password hashing &amp; default account provisioning</p>
                </div>

                <div class="card-body p-4">
                    <?php if ($status === 'success'): ?>
                        <div class="alert alert-success d-flex align-items-center mb-4">
                            <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                            <div>
                                <strong>Initialization Successful!</strong>
                                <ul class="mb-0 mt-1 ps-3 small">
                                    <?php foreach ($messages as $m): ?>
                                        <li><?= escape($m) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="p-3 bg-white rounded-3 border h-100 shadow-sm">
                                    <span class="badge bg-danger mb-2">Administrator Account</span>
                                    <div class="small text-muted">Email:</div>
                                    <div class="font-monospace fw-bold text-dark mb-2"><?= escape($default_admin_email) ?></div>
                                    <div class="small text-muted">Password:</div>
                                    <div class="font-monospace fw-bold text-primary mb-3"><?= escape($default_admin_pass) ?></div>
                                    <a href="admin/admin_login.php" class="btn btn-sm btn-outline-dark w-100 rounded-pill">
                                        <i class="bi bi-shield-lock me-1"></i> Admin Portal
                                    </a>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-3 bg-white rounded-3 border h-100 shadow-sm">
                                    <span class="badge bg-primary mb-2">Demo Student Account</span>
                                    <div class="small text-muted">USN / Email:</div>
                                    <div class="font-monospace fw-bold text-dark mb-2"><?= escape($default_student_usn) ?></div>
                                    <div class="small text-muted">Password:</div>
                                    <div class="font-monospace fw-bold text-primary mb-3"><?= escape($default_student_pass) ?></div>
                                    <a href="login.php" class="btn btn-sm btn-primary w-100 rounded-pill">
                                        <i class="bi bi-person-circle me-1"></i> Student Login
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <a href="index.php" class="btn btn-outline-primary py-2 rounded-pill fw-bold">
                                <i class="bi bi-house-door me-1"></i> Go to Public Campus Events Homepage
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger mb-4">
                            <h5 class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Setup Failed</h5>
                            <p class="mb-2"><?= escape($messages[0] ?? 'Unknown error occurred.') ?></p>
                            <hr>
                            <p class="small mb-0">Please verify that you have created the database <code>bmsccm_events</code> and imported <code>database.sql</code>.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
