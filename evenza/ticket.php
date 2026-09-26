<?php
require_once __DIR__ . '/config/db.php';

$ticket_number = trim($_GET['ticket'] ?? '');
$reg_id        = isset($_GET['reg_id']) ? intval($_GET['reg_id']) : 0;

if (empty($ticket_number) && $reg_id <= 0) {
    render_404("No valid ticket number or registration ID was provided.");
}

// If viewing by internal reg_id instead of unique ticket number, enforce login
if (empty($ticket_number) && $reg_id > 0) {
    if (!is_student_logged_in() && !is_admin_logged_in()) {
        set_flash('warning', 'Please log in to view your Evenza ticket.');
        header('Location: login.php');
        exit;
    }
}

if (!empty($ticket_number)) {
    $stmt = $conn->prepare("
        SELECT r.registration_id, r.ticket_number, r.registered_at, r.status, r.checked_in, r.checked_in_at,
               s.student_id, s.full_name, s.usn, s.email, s.phone, s.course,
               e.event_id, e.event_name, e.category, e.event_date, e.event_time, e.venue, e.description
        FROM registrations r
        JOIN students s ON r.student_id = s.student_id
        JOIN events e ON r.event_id = e.event_id
        WHERE r.ticket_number = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $ticket_number);
} else {
    $stmt = $conn->prepare("
        SELECT r.registration_id, r.ticket_number, r.registered_at, r.status, r.checked_in, r.checked_in_at,
               s.student_id, s.full_name, s.usn, s.email, s.phone, s.course,
               e.event_id, e.event_name, e.category, e.event_date, e.event_time, e.venue, e.description
        FROM registrations r
        JOIN students s ON r.student_id = s.student_id
        JOIN events e ON r.event_id = e.event_id
        WHERE r.registration_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $reg_id);
}

$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    render_404("The requested event pass was not found in Evenza. Please check your ticket number or dashboard.");
}

$can_view = false;
if (is_admin_logged_in()) {
    $can_view = true;
} elseif (is_student_logged_in() && (int)$_SESSION['student_id'] === (int)$ticket['student_id']) {
    $can_view = true;
} elseif (!empty($ticket_number)) {
    // Allow verified pass scan directly via its unique cryptographic ticket number
    $can_view = true;
}

if (!$can_view) {
    set_flash('danger', 'Access denied. You can only view your own issued event passes.');
    header('Location: dashboard.php');
    exit;
}

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$base_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$ticket_verify_url = $protocol . $host . ($base_dir ? $base_dir : '') . "/ticket.php?ticket=" . urlencode($ticket['ticket_number']);

$qr_payload = $ticket_verify_url;
$qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=" . urlencode($qr_payload) . "&color=0a192f";

$page_title = "Official Pass — " . $ticket['ticket_number'];
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Top Action Toolbar (Hidden in Print) -->
    <div class="no-print d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Return to Registrations
            </a>
            <a href="index.php" class="btn btn-outline-dark rounded-pill btn-sm ms-2">
                <i class="bi bi-calendar3 me-1"></i> Browse Other Events
            </a>
        </div>
        <div class="d-flex gap-2">
            <button type="button" id="copyPassIdBtn" class="btn btn-outline-dark rounded-pill px-3 fw-semibold btn-sm shadow-sm" onclick="navigator.clipboard.writeText('<?= escape($ticket['ticket_number']) ?>'); showEvenzaToast('Pass ID copied: <?= escape($ticket['ticket_number']) ?>');">
                <i class="bi bi-clipboard me-1"></i> Copy Pass ID
            </button>
            <button type="button" id="shareTicketBtn" class="btn btn-outline-primary rounded-pill px-3 fw-semibold btn-sm shadow-sm">
                <i class="bi bi-share me-1"></i> Share
            </button>
            <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm btn-sm">
                <i class="bi bi-printer me-2"></i> Print Official E-Ticket
            </button>
        </div>
    </div>

    <!-- Official Pass Layout -->
    <div class="ticket-wrapper">
        <div class="ticket-pass">
            <!-- Left Credential Body -->
            <div class="ticket-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <div class="ticket-college-header">Evenza Campus Events</div>
                        <div class="small text-muted fw-semibold">Official Admission Credential</div>
                    </div>
                    <?php if ((int)$ticket['checked_in'] === 1): ?>
                        <span class="badge bg-success text-white px-2 py-1 small fw-bold shadow-sm">
                            <i class="bi bi-check2-all me-1"></i> ADMISSION VERIFIED (<?= date('d M, h:i A', strtotime($ticket['checked_in_at'])) ?>)
                        </span>
                    <?php elseif ($ticket['status'] === 'cancelled'): ?>
                        <span class="badge bg-danger text-white px-2 py-1 small fw-bold">
                            <i class="bi bi-x-circle me-1"></i> PASS CANCELLED
                        </span>
                    <?php else: ?>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 small fw-bold">
                            <i class="bi bi-check-circle-fill me-1"></i> CONFIRMED ENTRY
                        </span>
                    <?php endif; ?>
                </div>

                <h2 class="ticket-event-heading"><?= escape($ticket['event_name']) ?></h2>

                <div class="row g-3 mb-4">
                    <div class="col-sm-4 col-6">
                        <div class="ticket-field-label">Date</div>
                        <div class="ticket-field-value text-primary">
                            <i class="bi bi-calendar3 me-1"></i><?= format_date($ticket['event_date']) ?>
                        </div>
                    </div>
                    <div class="col-sm-4 col-6">
                        <div class="ticket-field-label">Time</div>
                        <div class="ticket-field-value">
                            <i class="bi bi-clock me-1 text-secondary"></i><?= format_time($ticket['event_time']) ?>
                        </div>
                    </div>
                    <div class="col-sm-4 col-12">
                        <div class="ticket-field-label">Discipline</div>
                        <div class="ticket-field-value">
                            <span class="badge bg-light text-dark border"><?= escape($ticket['category']) ?></span>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="ticket-field-label">Venue Location</div>
                        <div class="ticket-field-value text-danger">
                            <i class="bi bi-geo-alt me-1"></i><?= escape($ticket['venue']) ?>
                        </div>
                    </div>
                </div>

                <!-- Attendee Verified Section -->
                <div class="border-top pt-3">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="ticket-field-label">Attendee Name</div>
                            <div class="ticket-field-value"><?= escape($ticket['full_name']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="ticket-field-label">University Seat Number (USN)</div>
                            <div class="ticket-field-value mono-font text-uppercase"><?= escape($ticket['usn']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="ticket-field-label">Academic Course</div>
                            <div class="small fw-semibold text-secondary"><?= escape($ticket['course']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="ticket-field-label">Issued At</div>
                            <div class="small text-secondary"><?= date('d M Y, h:i A', strtotime($ticket['registered_at'])) ?></div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-2 border-top text-muted small">
                    <i class="bi bi-shield-check text-success me-1"></i> Present this digital pass or physical printout alongside your valid college ID card at the entrance desk.
                </div>
            </div>

            <!-- Right Verification Stub -->
            <div class="ticket-stub">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="brand-monogram" style="width: 32px; height: 32px; font-size: 0.95rem;">E</div>
                    <span class="brand-text fs-5">Evenza</span>
                </div>

                <div class="qr-container bg-white p-2 rounded-3 shadow-sm mb-3">
                    <img src="<?= $qr_code_url ?>" alt="Evenza QR Code" class="img-fluid" style="width: 140px; height: 140px;">
                </div>

                <div class="text-center w-100">
                    <span class="d-block small text-muted mb-1" style="font-size: 0.65rem; text-transform: uppercase; letter-spacing: 1px;">Serial Number</span>
                    <div class="ticket-code-pill text-truncate mb-2" title="<?= escape($ticket['ticket_number']) ?>">
                        <?= escape($ticket['ticket_number']) ?>
                    </div>
                    <div class="barcode-strip mb-2" title="Barcode Representation"></div>
                    <span class="badge bg-warning text-dark fw-bold" style="font-size: 0.68rem; letter-spacing: 0.8px;">DESK VERIFICATION</span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const shareBtn = document.getElementById('shareTicketBtn');
    if (shareBtn) {
        shareBtn.addEventListener('click', async () => {
            const shareData = {
                title: 'Evenza Event Pass: <?= escape($ticket['event_name']) ?>',
                text: 'Here is my official Evenza admission pass for <?= escape($ticket['event_name']) ?>.',
                url: window.location.href
            };
            if (navigator.share) {
                try {
                    await navigator.share(shareData);
                } catch (err) {
                    // Ignored if cancelled
                }
            } else {
                navigator.clipboard.writeText(window.location.href);
                showEvenzaToast('Ticket link copied to clipboard!');
            }
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

