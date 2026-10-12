<?php
if (!isset($conn)) {
    require_once __DIR__ . '/../config/db.php';
}

$current_page = basename($_SERVER['PHP_SELF']);
$page_title = isset($page_title) ? "Evenza — " . $page_title : "Evenza — College Event Management Portal";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($page_title) ?></title>
    <!-- DNS prefetch & preconnect for lightning fast CDN & font resolution -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- Main Navigation Header -->
<header class="sticky-top">
    <nav class="navbar navbar-expand-lg evenza-navbar navbar-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
                <div class="brand-monogram">E</div>
                <div class="d-flex flex-column">
                    <span class="brand-text">Evenza</span>
                    <span class="brand-subtext">Campus Events Portal</span>
                </div>
            </a>
            
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page === 'index.php') ? 'active' : '' ?>" href="index.php">
                            <i class="bi bi-calendar3 me-1"></i> Browse Events
                        </a>
                    </li>
                    <?php if (is_student_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>" href="dashboard.php">
                            <i class="bi bi-pass me-1"></i> My Registrations
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex align-items-center gap-2 mt-2 mt-lg-0 flex-wrap">
                    <?php if (is_student_logged_in()): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle d-flex align-items-center gap-2 py-1 px-3 rounded-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="badge bg-warning text-dark px-2 py-1 fw-bold"><?= escape($_SESSION['student_course']) ?></span>
                                <span class="fw-semibold text-truncate" style="max-width: 130px;"><?= escape($_SESSION['student_name']) ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                                <li class="px-3 py-2 border-bottom">
                                    <small class="text-muted d-block">USN:</small>
                                    <span class="fw-bold text-dark mono-font"><?= escape($_SESSION['student_usn']) ?></span>
                                </li>
                                <li><a class="dropdown-item py-2" href="dashboard.php"><i class="bi bi-speedometer2 me-2 text-primary"></i> Student Dashboard</a></li>
                                <li><a class="dropdown-item py-2" href="profile.php"><i class="bi bi-person-gear me-2 text-warning"></i> My Profile &amp; Security</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2 text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i> Sign Out</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline-light px-3 py-1 rounded-pill fw-medium">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Login
                        </a>
                        <a href="register_student.php" class="btn btn-warning px-3 py-1 rounded-pill fw-bold text-dark shadow-sm">
                            Register
                        </a>
                        <a href="admin/admin_login.php" class="btn btn-dark px-3 py-1 rounded-pill fw-semibold shadow-sm border border-secondary border-opacity-50 d-inline-flex align-items-center gap-1">
                            <i class="bi bi-shield-lock-fill text-warning"></i> Admin
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
