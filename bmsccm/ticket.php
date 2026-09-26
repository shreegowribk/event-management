<?php
require_once __DIR__ . '/config/db.php';

$ticket_number = trim($_GET['ticket'] ?? '');
$reg_id        = (int)($_GET['reg_id'] ?? 0);

if (empty($ticket_number) && $reg_id <= 0) {
    set_flash('danger', 'No valid ticket specified.');
    header('Location: index.php');
    exit;
}

if (!empty($ticket_number)) {
    $stmt = $conn->prepare("
        SELECT r.registration_id, r.ticket_number, r.registered_at, r.status,
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
        SELECT r.registration_id, r.ticket_number, r.registered_at, r.status,
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
    set_flash('danger', 'Ticket record not found in the database.');
    header('Location: index.php');
    exit;
}

$can_view = false;
if (is_admin_logged_in()) {
    $can_view = true;
} elseif (is_student_logged_in() && (int)$_SESSION['student_id'] === (int)$ticket['student_id']) {
    $can_view = true;
}

if (!$can_view) {
    set_flash('danger', 'Access denied. You can only view your own event tickets.');
    header('Location: login.php');
    exit;
}

$qr_payload = "BMSCCM-TICKET:" . $ticket['ticket_number'] . "|USN:" . $ticket['usn'] . "|EVENT:" . $ticket['event_id'] . "|STATUS:" . $ticket['status'];
$qr_code_url = "https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=" . urlencode($qr_payload) . "&color=0b192c";

$page_title = "E-Ticket - " . $ticket['ticket_number'];
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <div class="no-print d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <a href="dashboard.php" class="btn btn-outline-secondary rounded-pill">
                <i class="bi bi-arrow-left me-1"></i> My Registrations
            </a>
            <a href="index.php" class="btn btn-outline-primary rounded-pill ms-2">
                <i class="bi bi-calendar-event me-1"></i> Browse More Events
            </a>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                <i class="bi bi-printer-fill me-2"></i> Print / Download PDF
            </button>
        </div>
    </div>

    <div class="ticket-wrapper">
        <div class="ticket-card">
            <div class="ticket-main">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="ticket-header-college">BMS College of Commerce &amp; Management</div>
                        <div class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-2 py-1 small">
                            <i class="bi bi-patch-check-fill me-1"></i> Verified Official Pass
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-success px-3 py-1 rounded-pill fw-bold">
                            <i class="bi bi-check-circle-fill me-1"></i> <?= strtoupper(escape($ticket['status'])) ?>
                        </span>
                    </div>
                </div>

                <h2 class="ticket-event-title"><?= escape($ticket['event_name']) ?></h2>

                <div class="row g-3 mb-4">
                    <div class="col-sm-4 col-6">
                        <div class="ticket-detail-label">Event Date</div>
                        <div class="ticket-detail-value text-primary">
                            <i class="bi bi-calendar-check me-1"></i><?= format_date($ticket['event_date']) ?>
                        </div>
                    </div>
                    <div class="col-sm-4 col-6">
                        <div class="ticket-detail-label">Event Time</div>
                        <div class="ticket-detail-value">
                            <i class="bi bi-clock me-1 text-secondary"></i><?= format_time($ticket['event_time']) ?>
                        </div>
                    </div>
                    <div class="col-sm-4 col-12">
                        <div class="ticket-detail-label">Category</div>
                        <div class="ticket-detail-value">
                            <span class="badge bg-light text-dark border"><?= escape($ticket['category']) ?></span>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="ticket-detail-label">Campus Venue</div>
                        <div class="ticket-detail-value text-danger">
                            <i class="bi bi-geo-alt-fill me-1"></i><?= escape($ticket['venue']) ?>
                        </div>
                    </div>
                </div>

                <div class="border-top pt-3">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <div class="ticket-detail-label">Student Attendee</div>
                            <div class="ticket-detail-value fs-6"><?= escape($ticket['full_name']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="ticket-detail-label">University Seat Number (USN)</div>
                            <div class="ticket-detail-value fs-6 text-uppercase"><?= escape($ticket['usn']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="ticket-detail-label">Program / Course</div>
                            <div class="text-secondary small fw-bold"><?= escape($ticket['course']) ?></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="ticket-detail-label">Registration Timestamp</div>
                            <div class="text-secondary small"><?= date('d M Y, h:i A', strtotime($ticket['registered_at'])) ?></div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-2 border-top text-muted small">
                    <i class="bi bi-info-circle me-1"></i> Please carry this e-ticket on your mobile phone or as a printout along with your College ID card.
                </div>
            </div>

            <div class="ticket-stub">
                <div class="text-center mb-2">
                    <i class="bi bi-mortarboard fs-3 text-warning"></i>
                    <div class="small fw-bold text-white-50 text-uppercase mt-1" style="font-size: 0.7rem; letter-spacing: 1.5px;">BMSCCM Entry</div>
                </div>

                <div class="qr-container">
                    <img src="<?= $qr_code_url ?>" alt="Ticket Verification QR Code" class="img-fluid">
                </div>

                <div class="text-center">
                    <span class="d-block small text-white-50 mb-1" style="font-size: 0.68rem; text-transform: uppercase;">Ticket Identifier</span>
                    <div class="ticket-number-display text-nowrap">
                        <?= escape($ticket['ticket_number']) ?>
                    </div>
                    <span class="badge bg-warning text-dark mt-2" style="font-size: 0.65rem;">SCAN FOR ENTRY</span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
