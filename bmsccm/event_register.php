<?php
require_once __DIR__ . '/config/db.php';

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
if ($event_id <= 0) {
    set_flash('danger', 'Invalid event selected for registration.');
    header('Location: index.php');
    exit;
}

if (!is_student_logged_in()) {
    set_flash('warning', 'Please login to complete your event registration.');
    header('Location: login.php?redirect=' . urlencode('event_register.php?event_id=' . $event_id));
    exit;
}

$student_id = (int)$_SESSION['student_id'];

$stmt_event = $conn->prepare("SELECT event_id, event_name, category, event_date, event_time, venue, description, max_seats FROM events WHERE event_id = ? LIMIT 1");
$stmt_event->bind_param("i", $event_id);
$stmt_event->execute();
$event_res = $stmt_event->get_result();
$event = $event_res->fetch_assoc();
$stmt_event->close();

if (!$event) {
    set_flash('danger', 'The selected event could not be found.');
    header('Location: index.php');
    exit;
}

$stmt_stu = $conn->prepare("SELECT student_id, full_name, usn, email, phone, course FROM students WHERE student_id = ? LIMIT 1");
$stmt_stu->bind_param("i", $student_id);
$stmt_stu->execute();
$student = $stmt_stu->get_result()->fetch_assoc();
$stmt_stu->close();

$stmt_check = $conn->prepare("SELECT registration_id, ticket_number, status FROM registrations WHERE student_id = ? AND event_id = ? LIMIT 1");
$stmt_check->bind_param("ii", $student_id, $event_id);
$stmt_check->execute();
$existing_reg = $stmt_check->get_result()->fetch_assoc();
$stmt_check->close();

if ($existing_reg && $existing_reg['status'] === 'confirmed') {
    set_flash('info', 'You are already registered for this event! Here is your official e-ticket.');
    header('Location: ticket.php?ticket=' . urlencode($existing_reg['ticket_number']));
    exit;
}

$stmt_seats = $conn->prepare("SELECT COUNT(*) AS booked FROM registrations WHERE event_id = ? AND status = 'confirmed'");
$stmt_seats->bind_param("i", $event_id);
$stmt_seats->execute();
$booked_count = (int)$stmt_seats->get_result()->fetch_assoc()['booked'];
$stmt_seats->close();

$seats_left = max(0, (int)$event['max_seats'] - $booked_count);
$is_full = ($seats_left <= 0);

$registration_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_registration'])) {
    if ($is_full) {
        $registration_error = "Registration closed! This event has reached maximum capacity of " . $event['max_seats'] . " seats.";
    } else {
        $ticket_number = generate_ticket_number($event_id, $student_id);

        try {
            if ($existing_reg && $existing_reg['status'] === 'cancelled') {
                $stmt_upd = $conn->prepare("UPDATE registrations SET status = 'confirmed', ticket_number = ?, registered_at = CURRENT_TIMESTAMP WHERE registration_id = ?");
                $stmt_upd->bind_param("si", $ticket_number, $existing_reg['registration_id']);
                $stmt_upd->execute();
                $stmt_upd->close();
            } else {
                $stmt_ins = $conn->prepare("INSERT INTO registrations (student_id, event_id, ticket_number, status) VALUES (?, ?, ?, 'confirmed')");
                $stmt_ins->bind_param("iis", $student_id, $event_id, $ticket_number);
                $stmt_ins->execute();
                $stmt_ins->close();
            }

            set_flash('success', 'Congratulations! Your event registration is confirmed. Download or print your E-Ticket below.');
            header('Location: ticket.php?ticket=' . urlencode($ticket_number));
            exit;

        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                set_flash('warning', 'You are already registered for this event.');
                header('Location: dashboard.php');
                exit;
            } else {
                $registration_error = "Registration error: " . $e->getMessage();
            }
        }
    }
}

$page_title = "Confirm Registration - " . $event['event_name'];
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="mb-3">
                <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                    <i class="bi bi-arrow-left me-1"></i> Back to Events Catalog
                </a>
            </div>

            <?php if (!empty($registration_error)): ?>
                <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center mb-4">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                    <div><?= escape($registration_error) ?></div>
                </div>
            <?php endif; ?>

            <div class="card bms-card border-0 shadow-lg overflow-hidden">
                <div class="card-header bg-dark text-white p-4 d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: linear-gradient(135deg, #0b192c 0%, #1e3e62 100%) !important;">
                    <div>
                        <span class="badge bg-warning text-dark fw-bold mb-2">Step 2: Confirm Registration</span>
                        <h3 class="fw-bold mb-1 text-white"><?= escape($event['event_name']) ?></h3>
                        <div class="text-white-50 small">
                            <i class="bi bi-tag-fill me-1"></i> <?= escape($event['category']) ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-white bg-opacity-20 text-white px-3 py-2 rounded-pill fs-6">
                            <i class="bi bi-people-fill me-1"></i> <?= $seats_left ?> Seats Available
                        </span>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded-3 text-center border">
                                <span class="text-muted small d-block mb-1"><i class="bi bi-calendar3 me-1 text-primary"></i> Event Date</span>
                                <span class="fw-bold text-dark"><?= format_date($event['event_date']) ?></span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded-3 text-center border">
                                <span class="text-muted small d-block mb-1"><i class="bi bi-clock me-1 text-primary"></i> Event Time</span>
                                <span class="fw-bold text-dark"><?= format_time($event['event_time']) ?></span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded-3 text-center border">
                                <span class="text-muted small d-block mb-1"><i class="bi bi-geo-alt-fill me-1 text-danger"></i> Campus Venue</span>
                                <span class="fw-bold text-dark text-truncate d-block"><?= escape($event['venue']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="fw-bold text-dark mb-2">Event Brief</h6>
                        <p class="text-secondary small bg-light p-3 rounded-3 mb-0" style="line-height: 1.6;">
                            <?= nl2br(escape($event['description'])) ?>
                        </p>
                    </div>

                    <div class="border rounded-3 p-4 mb-4 bg-white shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold text-dark mb-0">
                                <i class="bi bi-person-badge-fill text-primary me-2"></i>Registrant Information
                            </h5>
                            <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 small">
                                <i class="bi bi-check-circle-fill me-1"></i> Auto-Verified from Profile
                            </span>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="small text-muted fw-semibold">Full Name</label>
                                <div class="form-control bg-light border-0 fw-bold"><?= escape($student['full_name']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="small text-muted fw-semibold">University Seat Number (USN)</label>
                                <div class="form-control bg-light border-0 fw-bold text-uppercase"><?= escape($student['usn']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="small text-muted fw-semibold">Course / Degree</label>
                                <div class="form-control bg-light border-0 fw-bold"><?= escape($student['course']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="small text-muted fw-semibold">Email ID</label>
                                <div class="form-control bg-light border-0 fw-bold"><?= escape($student['email']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="small text-muted fw-semibold">Contact Phone</label>
                                <div class="form-control bg-light border-0 fw-bold"><?= escape($student['phone']) ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if ($is_full): ?>
                        <div class="alert alert-warning text-center">
                            <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
                            <strong>Housefull:</strong> All <?= $event['max_seats'] ?> seats for this event have been claimed. Please check other campus events!
                        </div>
                        <a href="index.php" class="btn btn-secondary w-100 py-2 rounded-pill">Browse Other Events</a>
                    <?php else: ?>
                        <form method="POST" action="event_register.php?event_id=<?= (int)$event['event_id'] ?>">
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" id="termsCheck" required checked>
                                <label class="form-check-label small text-muted" for="termsCheck">
                                    I confirm that I will present my digital or printed E-Ticket with QR code at the event venue entrance for attendance verification.
                                </label>
                            </div>

                            <button type="submit" name="confirm_registration" class="btn btn-primary w-100 py-3 fw-bold fs-6 shadow rounded-pill">
                                <i class="bi bi-ticket-perforated-fill me-2"></i> Confirm Registration &amp; Generate E-Ticket
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
