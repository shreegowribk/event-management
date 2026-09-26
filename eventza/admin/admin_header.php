<?php
require_once __DIR__ . '/../config/db.php';
require_admin_login();

$current_page = basename($_SERVER['PHP_SELF']);
$page_title = isset($page_title) ? "Evenza Admin — " . $page_title : "Evenza — Administrator Console";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escape($page_title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="bg-light">

<!-- Admin Navigation Header -->
<header class="sticky-top">
    <nav class="navbar navbar-expand-lg evenza-navbar navbar-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="admin_dashboard.php">
                <div class="brand-monogram" style="width: 34px; height: 34px; font-size: 1rem;">E</div>
                <div class="d-flex flex-column">
                    <span class="brand-text fs-5">Evenza <span class="badge bg-warning text-dark small fw-bold px-2 ms-1" style="font-size: 0.65rem;">CONSOLE</span></span>
                </div>
            </a>

            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="adminNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-4">
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page === 'admin_dashboard.php') ? 'active text-white' : '' ?>" href="admin_dashboard.php">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page === 'add_event.php') ? 'active text-white' : '' ?>" href="add_event.php">
                            <i class="bi bi-plus-circle me-1"></i> Add Event
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page === 'view_registrations.php') ? 'active text-white' : '' ?>" href="view_registrations.php">
                            <i class="bi bi-people me-1"></i> Registrations Roster
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= ($current_page === 'verify_ticket.php') ? 'active text-white' : '' ?>" href="verify_ticket.php">
                            <i class="bi bi-qr-code-scan me-1 text-warning"></i> QR Check-In
                        </a>
                    </li>
                </ul>

                <div class="d-flex align-items-center gap-3">
                    <div class="dropdown">
                        <button class="btn btn-outline-light dropdown-toggle btn-sm rounded-pill px-3 py-1" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-shield-check text-warning me-1"></i>
                            <span class="fw-semibold"><?= escape($_SESSION['admin_name']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2">
                            <li class="px-3 py-2 border-bottom">
                                <small class="text-muted d-block">Administrator Account:</small>
                                <span class="fw-bold text-dark small"><?= escape($_SESSION['admin_email']) ?></span>
                            </li>
                            <li><a class="dropdown-item py-2" href="admin_profile.php"><i class="bi bi-person-badge text-primary me-2"></i> Admin Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2 text-danger" href="admin_logout.php"><i class="bi bi-box-arrow-right me-2"></i> Sign Out</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>
</header>

<div class="container mt-3">
    <?php render_flash(); ?>
</div>
