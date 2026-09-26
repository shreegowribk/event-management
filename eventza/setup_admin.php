<?php
require_once __DIR__ . '/config/db.php';

$is_cli = (php_sapi_name() === 'cli');

// Security check: Refuse to execute if an admin account already exists
$admin_count_check = $conn->query("SELECT COUNT(*) AS total FROM admins");
$existing_admins = $admin_count_check ? (int)$admin_count_check->fetch_assoc()['total'] : 0;
if ($existing_admins > 0) {
    if ($is_cli) {
        fwrite(STDERR, "========================================================\n Evenza Setup Utility Refused\n========================================================\n An administrator account already exists in evenza_db.\n For security reasons, setup_admin.php cannot be re-run.\n Please login at: http://localhost:8000/admin/admin_login.php\n========================================================\n\n");
        exit(1);
    } else {
        http_response_code(403);
        die("
        <div style='font-family: system-ui, sans-serif; max-width: 600px; margin: 60px auto; padding: 30px; border-radius: 12px; background: #fff; box-shadow: 0 10px 25px rgba(0,0,0,0.08); border-left: 6px solid #ef4444;'>
            <h2 style='color: #991b1b; margin-top: 0;'>Setup Utility Locked</h2>
            <p style='color: #4b5563;'>An administrator account is already configured for Evenza in the database. For security reasons, <code>setup_admin.php</code> is restricted from running again.</p>
            <div style='margin-top: 20px;'>
                <a href='admin/admin_login.php' style='display: inline-block; background: #0b192c; color: #fff; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: bold;'>Go to Admin Login &rarr;</a>
                <a href='index.php' style='display: inline-block; margin-left: 10px; color: #6b7280; text-decoration: none;'>Return to Home</a>
            </div>
        </div>
        ");
    }
}

$default_admin_name  = "Evenza Administrator";
$default_admin_email = "admin@evenza.com";
$default_admin_pass  = "Admin@123";

$default_student_name  = "Rahul Sharma";
$default_student_usn   = "1EV21MC001";
$default_student_email = "rahul@evenza.com";
$default_student_phone = "9876543210";
$default_student_course= "BCA";
$default_student_pass  = "Student@123";

$messages = [];
$status = 'success';

try {
    // ----------------------------------------------------------
    // Schema Migrations (Non-destructive column additions)
    // ----------------------------------------------------------
    $check_status_col = $conn->query("SHOW COLUMNS FROM `events` LIKE 'status'");
    if ($check_status_col && $check_status_col->num_rows === 0) {
        $conn->query("ALTER TABLE `events` ADD COLUMN `status` ENUM('upcoming', 'completed', 'cancelled') NOT NULL DEFAULT 'upcoming' AFTER `max_seats`");
        $messages[] = "Database migrated: Added 'status' column to events table.";
    }

    $check_checked_in = $conn->query("SHOW COLUMNS FROM `registrations` LIKE 'checked_in'");
    if ($check_checked_in && $check_checked_in->num_rows === 0) {
        $conn->query("ALTER TABLE `registrations` 
            ADD COLUMN `checked_in` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`,
            ADD COLUMN `checked_in_at` DATETIME DEFAULT NULL AFTER `checked_in`,
            ADD COLUMN `checked_in_by` INT DEFAULT NULL AFTER `checked_in_at`");
        $messages[] = "Database migrated: Added 'checked_in', 'checked_in_at', and 'checked_in_by' audit columns to registrations table.";
    }

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
        $messages[] = "Default Administrator updated with fresh bcrypt hash.";
    } else {
        $stmt->close();
        $ins_stmt = $conn->prepare("INSERT INTO admins (full_name, email, password) VALUES (?, ?, ?)");
        $ins_stmt->bind_param("sss", $default_admin_name, $default_admin_email, $admin_hash);
        $ins_stmt->execute();
        $ins_stmt->close();
        $messages[] = "Default Administrator created successfully in evenza_db.";
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
        $messages[] = "Demo Student ({$default_student_usn}) verified and ready.";
    } else {
        $stmt_s->close();
        $ins_s = $conn->prepare("INSERT INTO students (full_name, usn, email, phone, course, password) VALUES (?, ?, ?, ?, ?, ?)");
        $ins_s->bind_param("ssssss", $default_student_name, $default_student_usn, $default_student_email, $default_student_phone, $default_student_course, $student_hash);
        $ins_s->execute();
        $ins_s->close();
        $messages[] = "Demo Student ({$default_student_usn}) created for immediate testing.";
    }

} catch (Exception $e) {
    $status = 'error';
    $messages[] = "Setup failed: " . $e->getMessage();
}

if ($is_cli) {
    echo "========================================================\n";
    echo " Evenza - College Event Management Setup Utility\n";
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
    <title>Evenza — Setup Utility</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="bg-light">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7 col-md-9">
            <div class="card evenza-card border-0 shadow-lg">
                <div class="card-header text-center py-4" style="background: linear-gradient(135deg, #fbf9f5 0%, #f4ede1 100%) !important; border-bottom: 1px solid rgba(197, 160, 89, 0.3) !important; color: #1a1714 !important;">
                    <i class="bi bi-shield-check fs-1 mb-2 d-block" style="color: #c5a059;"></i>
                    <h3 class="fw-bold mb-1" style="color: #1a1714;">Evenza System Initialization</h3>
                    <p class="small mb-0" style="color: #6b6359;">Bcrypt password hashing &amp; default account provisioning</p>
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
                                    <span class="badge bg-danger mb-2">Evenza Administrator</span>
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
                                    <span class="badge bg-primary mb-2">Demo Student</span>
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
                                <i class="bi bi-house-door me-1"></i> Go to Evenza Homepage
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger mb-4">
                            <h5 class="fw-bold"><i class="bi bi-exclamation-triangle-fill me-2"></i>Setup Failed</h5>
                            <p class="mb-2"><?= escape($messages[0] ?? 'Unknown error.') ?></p>
                            <hr>
                            <p class="small mb-0">Verify that <code>evenza_db</code> is created and <code>database.sql</code> is imported.</p>
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
