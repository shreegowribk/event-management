<?php
if (!isset($conn)) {
    require_once __DIR__ . '/config/db.php';
}

$page_title = "404 - Page Not Found";
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5 my-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 text-center">
            <div class="card evenza-card border-0 shadow-lg p-5">
                <div class="mb-4">
                    <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center" style="width: 90px; height: 90px;">
                        <i class="bi bi-exclamation-triangle-fill fs-1"></i>
                    </div>
                </div>
                <h1 class="display-5 fw-bold text-dark mb-2">404</h1>
                <h4 class="fw-bold text-secondary mb-3">Resource Not Found</h4>
                <p class="text-muted mb-4">
                    <?= isset($error_message) && !empty($error_message) ? escape($error_message) : "The event, ticket, or page you are looking for does not exist or may have been relocated." ?>
                </p>
                <div class="d-flex justify-content-center gap-2 flex-wrap">
                    <a href="index.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                        <i class="bi bi-house-door me-1"></i> Browse Evenza Events
                    </a>
                    <?php if (is_student_logged_in()): ?>
                        <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill px-3">
                            <i class="bi bi-person-circle me-1"></i> My Dashboard
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
