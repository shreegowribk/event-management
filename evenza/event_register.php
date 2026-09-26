<?php
require_once __DIR__ . '/config/db.php';

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($event_id <= 0) {
    render_404("Invalid event identifier specified.");
}

if (!is_student_logged_in()) {
    set_flash('warning', 'Please login to complete your event registration.');
    header('Location: login.php?redirect=' . urlencode('event_register.php?event_id=' . $event_id));
    exit;
}

$student_id = (int)$_SESSION['student_id'];

$stmt_event = $conn->prepare("SELECT event_id, event_name, category, event_date, event_time, venue, description, max_seats, status FROM events WHERE event_id = ? LIMIT 1");
$stmt_event->bind_param("i", $event_id);
$stmt_event->execute();
$event_res = $stmt_event->get_result();
$event = $event_res->fetch_assoc();
$stmt_event->close();

if (!$event) {
    render_404("The campus event requested for registration was not found in Evenza.");
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
    set_flash('info', 'You are already registered for this event! Here is your official Evenza pass.');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $registration_error = "Security token expired or invalid (CSRF protection). Please refresh and try again.";
    } elseif ($event['status'] === 'cancelled' || $event['status'] === 'completed') {
        $registration_error = "Registration closed: This event is marked as " . htmlspecialchars($event['status']) . ".";
    } else {
        $conn->begin_transaction();
        try {
            // Lock event row for atomic seat verification
            $stmt_lock = $conn->prepare("SELECT max_seats, status FROM events WHERE event_id = ? FOR UPDATE");
            $stmt_lock->bind_param("i", $event_id);
            $stmt_lock->execute();
            $lock_res = $stmt_lock->get_result()->fetch_assoc();
            $stmt_lock->close();

            if (!$lock_res || $lock_res['status'] !== 'upcoming') {
                $conn->rollback();
                $registration_error = "This event is no longer open for registration.";
            } else {
                $stmt_atomic_seats = $conn->prepare("SELECT COUNT(*) AS booked FROM registrations WHERE event_id = ? AND status = 'confirmed' FOR UPDATE");
                $stmt_atomic_seats->bind_param("i", $event_id);
                $stmt_atomic_seats->execute();
                $atomic_booked = (int)$stmt_atomic_seats->get_result()->fetch_assoc()['booked'];
                $stmt_atomic_seats->close();

                if ($atomic_booked >= (int)$lock_res['max_seats']) {
                    $conn->rollback();
                    $registration_error = "Registration closed: this event has reached its maximum capacity of " . $lock_res['max_seats'] . " seats.";
                } else {
                    $ticket_number = generate_ticket_number($event_id, $student_id);

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

                    $conn->commit();

                    set_flash('success', 'Registration confirmed! Download or print your Evenza E-Ticket below.');
                    header('Location: ticket.php?ticket=' . urlencode($ticket_number));
                    exit;
                }
            }
        } catch (mysqli_sql_exception $e) {
            $conn->rollback();
            if ($e->getCode() === 1062) {
                set_flash('warning', 'You are already registered for this event on Evenza.');
                header('Location: dashboard.php');
                exit;
            } else {
                $registration_error = "Registration error: " . $e->getMessage();
            }
        }
    }
}

$page_title = "Confirm Registration";
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                    <i class="bi bi-arrow-left me-1"></i> Back to Schedule
                </a>
                <span class="small text-muted"><i class="bi bi-shield-check text-success me-1"></i> Secure Reservation</span>
            </div>

            <!-- Multi-Step Stepper -->
            <div class="evenza-stepper mb-4">
                <div class="step-item completed">
                    <div class="step-circle"><i class="bi bi-check-lg"></i></div>
                    <div class="step-label">1. Select Event</div>
                </div>
                <div class="step-item active">
                    <div class="step-circle">2</div>
                    <div class="step-label">2. Confirm Booking</div>
                </div>
                <div class="step-item">
                    <div class="step-circle">3</div>
                    <div class="step-label">3. E-Ticket Pass</div>
                </div>
            </div>

            <?php if (!empty($registration_error)): ?>
                <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center mb-4">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                    <div><?= escape($registration_error) ?></div>
                </div>
            <?php endif; ?>

            <div class="card event-card border-0 shadow-sm overflow-hidden">
                <div class="card-header p-4 d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: linear-gradient(135deg, #fbf9f5 0%, #f4ede1 100%) !important; border-bottom: 1px solid rgba(197, 160, 89, 0.3) !important; color: #1a1714 !important;">
                    <div>
                        <span class="badge bg-warning text-white fw-bold mb-2">Step 2: Confirm Attendance</span>
                        <h3 class="fw-bold mb-1" style="color: #1a1714;"><?= escape($event['event_name']) ?></h3>
                        <div class="small" style="color: #6b6359;">
                            <span class="badge bg-light text-dark me-2"><?= escape($event['category']) ?></span>
                            Evenza Campus Events
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge px-3 py-2 rounded-pill fs-6" style="background: #ffffff; color: #9e7b34; border: 1px solid rgba(197, 160, 89, 0.4);">
                            <?= $seats_left ?> Seats Available
                        </span>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded-3 text-center border">
                                <span class="text-muted small d-block mb-1"><i class="bi bi-calendar3 me-1 text-primary"></i> Date</span>
                                <span class="fw-bold text-dark"><?= format_date($event['event_date']) ?></span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded-3 text-center border">
                                <span class="text-muted small d-block mb-1"><i class="bi bi-clock me-1 text-primary"></i> Time</span>
                                <span class="fw-bold text-dark"><?= format_time($event['event_time']) ?></span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="p-3 bg-light rounded-3 text-center border">
                                <span class="text-muted small d-block mb-1"><i class="bi bi-geo-alt me-1 text-danger"></i> Location</span>
                                <span class="fw-bold text-dark text-truncate d-block"><?= escape($event['venue']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h6 class="fw-bold text-dark mb-2">Event Overview</h6>
                        <p class="text-secondary small bg-light p-3 rounded-3 mb-0" style="line-height: 1.6;">
                            <?= nl2br(escape($event['description'])) ?>
                        </p>
                    </div>

                    <div class="border rounded-3 p-4 mb-4 bg-white shadow-sm">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="bi bi-person-check text-primary me-2"></i>Registrant Information
                            </h6>
                            <span class="badge bg-light text-secondary border px-2 py-1 small">
                                Auto-filled from profile
                            </span>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="small text-muted fw-semibold">Full Name</label>
                                <div class="form-control bg-light border-0 fw-semibold"><?= escape($student['full_name']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="small text-muted fw-semibold">University Seat Number (USN)</label>
                                <div class="form-control bg-light border-0 fw-semibold text-uppercase mono-font"><?= escape($student['usn']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="small text-muted fw-semibold">Program / Course</label>
                                <div class="form-control bg-light border-0 fw-semibold"><?= escape($student['course']) ?></div>
                            </div>
                            <div class="col-md-6">
                                <label class="small text-muted fw-semibold">Email</label>
                                <div class="form-control bg-light border-0 fw-semibold"><?= escape($student['email']) ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if ($is_full): ?>
                        <div class="alert alert-warning text-center">
                            <strong>Capacity Reached:</strong> All <?= $event['max_seats'] ?> seats have been booked.
                        </div>
                        <a href="index.php" class="btn btn-secondary w-100 py-2 rounded-pill">Browse Other Events</a>
                    <?php else: ?>
                        <form method="POST" action="event_register.php?event_id=<?= (int)$event['event_id'] ?>" id="confirmRegForm">
                            <?= csrf_field() ?>
                            <input type="hidden" name="confirm_registration" value="1">
                            <div class="form-check mb-4 p-3 bg-light rounded-3 border">
                                <input class="form-check-input ms-0 me-2" type="checkbox" id="termsCheck" required checked>
                                <label class="form-check-label small text-dark fw-medium" for="termsCheck">
                                    I confirm my attendance and understand an electronic Evenza pass with a unique verification QR code will be generated for campus entrance.
                                </label>
                            </div>

                            <button type="submit" id="confirmRegSubmitBtn" class="btn btn-primary w-100 py-3 fw-semibold shadow rounded-pill fs-5">
                                <i class="bi bi-pass me-2"></i> Confirm Attendance &amp; Issue E-Ticket
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('confirmRegForm');
    const submitBtn = document.getElementById('confirmRegSubmitBtn');
    if (form && submitBtn) {
        form.addEventListener('submit', () => {
            submitBtn.style.pointerEvents = 'none';
            submitBtn.style.opacity = '0.85';
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Reserving Seat & Issuing Pass...';
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

