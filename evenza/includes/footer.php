<?php
$in_admin = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
$base_url = $in_admin ? '../' : '';
$admin_url = $in_admin ? '' : 'admin/';
?>
<footer class="evenza-footer mt-auto">
    <div class="container">
        <div class="row g-4 mb-4">
            <div class="col-lg-5 col-md-6">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div class="brand-monogram" style="width: 32px; height: 32px; font-size: 0.95rem;">E</div>
                    <span class="brand-text fs-5">Evenza</span>
                </div>
                <p class="text-secondary small mb-2" style="max-width: 400px; line-height: 1.6;">
                    Evenza is the official campus event management and verified ticket issuance portal.
                </p>
                <div class="text-white-50 small">
                    <i class="bi bi-geo-alt-fill text-warning me-1"></i> Campus Event Center, Bengaluru
                </div>
            </div>

            <div class="col-lg-3 col-md-3 col-6">
                <h6 class="text-white fw-bold mb-3 small text-uppercase letter-spacing-1">Portal Links</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="<?= $base_url ?>index.php"><i class="bi bi-chevron-right me-1 text-warning"></i> Event Schedule</a></li>
                    <li class="mb-2"><a href="<?= $base_url ?>dashboard.php"><i class="bi bi-chevron-right me-1 text-warning"></i> My Registrations</a></li>
                    <li class="mb-2"><a href="<?= $base_url ?>register_student.php"><i class="bi bi-chevron-right me-1 text-warning"></i> Student Sign Up</a></li>
                    <li class="mb-2"><a href="<?= $base_url ?>login.php"><i class="bi bi-chevron-right me-1 text-warning"></i> Login</a></li>
                </ul>
            </div>

            <div class="col-lg-4 col-md-3 col-6">
                <h6 class="text-white fw-bold mb-3 small text-uppercase letter-spacing-1">Administration</h6>
                <ul class="list-unstyled small">
                    <li class="mb-2"><a href="<?= $admin_url ?>admin_dashboard.php"><i class="bi bi-shield-lock me-1 text-warning"></i> Admin Console</a></li>
                    <li class="mb-2"><a href="<?= $base_url ?>setup_admin.php"><i class="bi bi-gear me-1 text-warning"></i> System Setup Utility</a></li>
                </ul>
                <div class="p-2 rounded bg-dark border border-secondary border-opacity-25 small text-secondary mt-2">
                    <span class="text-white-50 fw-semibold">Campus Events Portal</span> &bull; Evenza System
                </div>
            </div>
        </div>

        <div class="border-top border-secondary border-opacity-25 pt-3 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 small text-secondary">
            <div>
                &copy; <?= date('Y') ?> Evenza Event Management. All rights reserved.
            </div>
            <div>
                Built with PHP (MySQLi Prepared Statements) &amp; Bootstrap 5
            </div>
        </div>
    </div>
</footer>

<!-- Floating Toast Container -->
<div class="evenza-toast-container" id="evenzaToastContainer"></div>

<!-- Scroll to Top Button -->
<button type="button" id="scrollToTopBtn" aria-label="Scroll to top" title="Scroll to Top">
    <i class="bi bi-chevron-up"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Evenza Global Toast Notification Utility
function showEvenzaToast(message, type = 'success') {
    const container = document.getElementById('evenzaToastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `evenza-toast toast-${type}`;
    
    let icon = 'bi-check-circle-fill text-success';
    if (type === 'warning') icon = 'bi-exclamation-triangle-fill text-warning';
    if (type === 'danger') icon = 'bi-x-circle-fill text-danger';
    if (type === 'info') icon = 'bi-info-circle-fill text-info';
    
    toast.innerHTML = `
        <i class="bi ${icon} fs-5"></i>
        <div class="flex-grow-1">${message}</div>
        <button type="button" class="btn-close btn-close-white ms-2 small" aria-label="Close" onclick="this.parentElement.remove()"></button>
    `;
    
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

// Global Password Show/Hide Toggle Handler
function initPasswordToggles() {
    document.querySelectorAll('.password-toggle-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            if (!input) return;
            
            const icon = this.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) {
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                }
            } else {
                input.type = 'password';
                if (icon) {
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            }
        });
    });
}

// Scroll to Top Logic
const scrollBtn = document.getElementById('scrollToTopBtn');
if (scrollBtn) {
    window.addEventListener('scroll', () => {
        if (window.scrollY > 300) {
            scrollBtn.classList.add('show');
        } else {
            scrollBtn.classList.remove('show');
        }
    });
    scrollBtn.addEventListener('click', () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

// Initialize on DOM Ready
document.addEventListener('DOMContentLoaded', () => {
    initPasswordToggles();
});
</script>
</body>
</html>

