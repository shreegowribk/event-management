<?php
?>
<footer class="bms-footer mt-auto">
    <div class="container">
        <div class="row g-4 mb-4">
            <div class="col-lg-5 col-md-6">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="bms-brand-badge py-1 px-2">
                        <i class="bi bi-mortarboard-fill text-warning"></i> BMSCCM
                    </span>
                    <h6 class="text-white mb-0 fw-bold">Event Management System</h6>
                </div>
                <p class="text-secondary small mb-2">
                    Official digital platform for event announcements, student registration, and instant e-ticket generation at BMS College of Commerce & Management, Bengaluru.
                </p>
                <div class="text-white-50 small">
                    <i class="bi bi-geo-alt-fill text-warning me-1"></i> VV Puram, Bengaluru, Karnataka 560004
                </div>
            </div>

            <div class="col-lg-3 col-md-3 col-6">
                <h6 class="text-white fw-bold mb-3">Quick Navigation</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="index.php"><i class="bi bi-chevron-right me-1 text-warning"></i> Browse Events</a></li>
                    <li class="mb-2"><a href="dashboard.php"><i class="bi bi-chevron-right me-1 text-warning"></i> My Registrations</a></li>
                    <li class="mb-2"><a href="register_student.php"><i class="bi bi-chevron-right me-1 text-warning"></i> Student Sign Up</a></li>
                    <li class="mb-2"><a href="login.php"><i class="bi bi-chevron-right me-1 text-warning"></i> Student Login</a></li>
                </ul>
            </div>

            <div class="col-lg-4 col-md-3 col-6">
                <h6 class="text-white fw-bold mb-3">Project & Administration</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="admin/admin_login.php"><i class="bi bi-shield-lock-fill me-1 text-warning"></i> Admin Portal</a></li>
                    <li class="mb-2"><a href="setup_admin.php"><i class="bi bi-tools me-1 text-warning"></i> One-Time Admin Setup</a></li>
                </ul>
                <div class="p-2 rounded bg-dark border border-secondary border-opacity-25 small text-secondary mt-2">
                    <i class="bi bi-code-slash text-warning me-1"></i> BCA Final Year Capstone Project
                </div>
            </div>
        </div>

        <div class="border-top border-secondary border-opacity-25 pt-3 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 small text-secondary">
            <div>
                &copy; <?= date('Y') ?> BMS College of Commerce and Management. All rights reserved.
            </div>
            <div>
                Built with PHP (MySQLi) &amp; Bootstrap 5
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
