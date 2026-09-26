<?php
if (!isset($conn)) {
    require_once __DIR__ . '/../config/db.php';
}

$current_page = basename($_SERVER['PHP_SELF']);
$page_title = isset($page_title) ? $page_title . " | BMSCCM Events" : "BMS College of Commerce & Management - Events Portal";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="sticky-top">
    <nav class="navbar navbar-expand-lg bms-navbar navbar-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <span class="bms-brand-badge">
                    <i class="bi bi-mortarboard-fill text-warning"></i> BMSCCM
                </span>
                <div class="d-flex flex-column ms-1">
                    <span class="bms-brand-title">Campus Events</span>
                    <span class="bms-brand-sub">BMS College of Commerce & Mgmt</span>
                </div>
            </a>
            
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page === 'index.php') ? 'active' : '' ?>" href="index.php">
                            <i class="bi bi-calendar-event me-1"></i> Browse Events
                        </a>
                    </li>
                    <?php if (is_student_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>" href="dashboard.php">
                            <i class="bi bi-ticket-perforated me-1"></i> My Registrations
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex align-items-center gap-2 mt-2 mt-lg-0 flex-wrap">
                    <?php if (is_student_logged_in()): ?>
                        <a href="admin/admin_login.php" class="btn btn-dark px-3 py-1 rounded-pill fw-semibold shadow-sm d-inline-flex align-items-center gap-1">
                            <i class="bi bi-shield-lock-fill text-warning"></i> Admin Login
                        </a>
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center gap-2 py-1 px-3 rounded-pill" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle text-warning fs-5"></i>
                                <span class="fw-semibold text-truncate" style="max-width: 140px;"><?= escape($_SESSION['student_name']) ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                                <li class="px-3 py-2 border-bottom">
                                    <small class="text-muted d-block">Signed in as USN:</small>
                                    <span class="fw-bold text-dark"><?= escape($_SESSION['student_usn']) ?></span>
                                    <div class="badge bg-light text-primary border mt-1"><?= escape($_SESSION['student_course']) ?></div>
                                </li>
                                <li><a class="dropdown-item py-2" href="dashboard.php"><i class="bi bi-speedometer2 me-2"></i> Student Dashboard</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Sign Out</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-light px-3 py-1 rounded-pill fw-semibold">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Login
                        </a>
                        <a href="register_student.php" class="btn btn-warning px-3 py-1 rounded-pill fw-bold text-dark shadow-sm">
                            <i class="bi bi-person-plus-fill me-1"></i> Register
                        </a>
                        <a href="admin/admin_login.php" class="btn btn-dark px-3 py-1 rounded-pill fw-semibold shadow-sm d-inline-flex align-items-center gap-1">
                            <i class="bi bi-shield-lock-fill text-warning"></i> Admin Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>

<div class="container mt-2">
    <?php render_flash(); ?>
</div>
