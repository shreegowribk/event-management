<?php
require_once __DIR__ . '/config/db.php';
require_student_login();

$student_id = (int)$_SESSION['student_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_reg_id'])) {
    $cancel_id = (int)$_POST['cancel_reg_id'];
    $stmt_cancel = $conn->prepare("UPDATE registrations SET status = 'cancelled' WHERE registration_id = ? AND student_id = ?");
    $stmt_cancel->bind_param("ii", $cancel_id, $student_id);
    if ($stmt_cancel->execute() && $stmt_cancel->affected_rows > 0) {
        set_flash('info', 'Your registration has been cancelled successfully.');
    } else {
        set_flash('danger', 'Unable to cancel registration. Please try again.');
    }
    $stmt_cancel->close();
    header('Location: dashboard.php');
    exit;
}

$stmt_stu = $conn->prepare("SELECT student_id, full_name, usn, email, phone, course, created_at FROM students WHERE student_id = ? LIMIT 1");
$stmt_stu->bind_param("i", $student_id);
$stmt_stu->execute();
$student = $stmt_stu->get_result()->fetch_assoc();
$stmt_stu->close();

$stmt_regs = $conn->prepare("
    SELECT r.registration_id, r.ticket_number, r.registered_at, r.status,
           e.event_id, e.event_name, e.category, e.event_date, e.event_time, e.venue
    FROM registrations r
    JOIN events e ON r.event_id = e.event_id
    WHERE r.student_id = ?
    ORDER BY e.event_date DESC, r.registered_at DESC
");
$stmt_regs->bind_param("i", $student_id);
$stmt_regs->execute();
$regs_res = $stmt_regs->get_result();

$registrations = [];
$confirmed_count = 0;
$upcoming_count  = 0;
$today = date('Y-m-d');

while ($r = $regs_res->fetch_assoc()) {
    $registrations[] = $r;
    if ($r['status'] === 'confirmed') {
        $confirmed_count++;
        if ($r['event_date'] >= $today) {
            $upcoming_count++;
        }
    }
}
$stmt_regs->close();

$page_title = "Student Dashboard";
include __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="card bms-card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center g-3">
                <div class="col-md-auto text-center">
                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary p-3 d-inline-flex align-items-center justify-content-center" style="width: 72px; height: 72px;">
                        <i class="bi bi-person-circle fs-1"></i>
                    </div>
                </div>
                <div class="col-md">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <h3 class="fw-bold text-dark mb-0"><?= escape($student['full_name']) ?></h3>
                        <span class="badge bg-primary rounded-pill px-3"><?= escape($student['course']) ?></span>
                    </div>
                    <div class="text-secondary small d-flex flex-wrap gap-3">
                        <span><i class="bi bi-card-heading text-warning me-1"></i> USN: <strong><?= escape($student['usn']) ?></strong></span>
                        <span><i class="bi bi-envelope text-primary me-1"></i> <?= escape($student['email']) ?></span>
                        <span><i class="bi bi-phone text-success me-1"></i> <?= escape($student['phone']) ?></span>
                    </div>
                </div>
                <div class="col-md-auto text-md-end">
                    <a href="index.php" class="btn btn-outline-primary rounded-pill fw-semibold">
                        <i class="bi bi-plus-circle me-1"></i> Browse More Events
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="admin-stat-card">
                <div class="admin-stat-icon stat-icon-blue">
                    <i class="bi bi-ticket-detailed"></i>
                </div>
                <div>
                    <h3 class="fw-bold text-dark mb-0"><?= count($registrations) ?></h3>
                    <span class="text-muted small">Total Registrations</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-stat-card">
                <div class="admin-stat-icon stat-icon-green">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <h3 class="fw-bold text-dark mb-0"><?= $confirmed_count ?></h3>
                    <span class="text-muted small">Confirmed Passes</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="admin-stat-card">
                <div class="admin-stat-icon stat-icon-purple">
                    <i class="bi bi-calendar-event"></i>
                </div>
                <div>
                    <h3 class="fw-bold text-dark mb-0"><?= $upcoming_count ?></h3>
                    <span class="text-muted small">Upcoming Campus Events</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card bms-card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0">
                <i class="bi bi-list-check text-primary me-2"></i>My Registered Events &amp; Passes
            </h5>
            <span class="text-muted small"><?= count($registrations) ?> total records</span>
        </div>

        <div class="card-body p-0">
            <?php if (empty($registrations)): ?>
                <div class="text-center py-5">
                    <div class="text-muted fs-1 mb-2"><i class="bi bi-ticket-perforated"></i></div>
                    <h5 class="fw-bold text-dark">No Event Registrations Yet</h5>
                    <p class="text-muted small mb-3">You haven't registered for any campus events yet. Explore events happening at BMSCCM!</p>
                    <a href="index.php" class="btn btn-primary rounded-pill px-4">Browse Campus Events</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th>Event Details</th>
                                <th>Date &amp; Venue</th>
                                <th>Ticket ID</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registrations as $r): 
                                $is_active = ($r['status'] === 'confirmed');
                                $is_past = ($r['event_date'] < $today);
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= escape($r['event_name']) ?></div>
                                    <span class="badge bg-light text-secondary border small"><?= escape($r['category']) ?></span>
                                    <?php if ($is_past): ?>
                                        <span class="badge bg-secondary text-white small ms-1">Past Event</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark"><i class="bi bi-calendar3 text-primary me-1"></i><?= format_date($r['event_date']) ?></div>
                                    <div class="small text-muted"><i class="bi bi-clock text-secondary me-1"></i><?= format_time($r['event_time']) ?></div>
                                    <div class="small text-muted text-truncate" style="max-width: 200px;"><i class="bi bi-geo-alt text-danger me-1"></i><?= escape($r['venue']) ?></div>
                                </td>
                                <td>
                                    <code class="fw-bold text-primary bg-light px-2 py-1 rounded small">
                                        <?= escape($r['ticket_number']) ?>
                                    </code>
                                    <div class="text-muted small mt-1" style="font-size: 0.72rem;">
                                        Reg: <?= date('d M Y', strtotime($r['registered_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($r['status'] === 'confirmed'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
                                            <i class="bi bi-check-circle-fill me-1"></i> Confirmed
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border px-2 py-1 rounded-pill">
                                            <i class="bi bi-x-circle me-1"></i> Cancelled
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?php if ($is_active): ?>
                                        <a href="ticket.php?ticket=<?= urlencode($r['ticket_number']) ?>" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm me-1">
                                            <i class="bi bi-qr-code me-1"></i> E-Ticket
                                        </a>
                                        <form method="POST" action="dashboard.php" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this registration? Your seat will be released for other students.');">
                                            <input type="hidden" name="cancel_reg_id" value="<?= (int)$r['registration_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" title="Cancel Registration">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <a href="event_register.php?event_id=<?= (int)$r['event_id'] ?>" class="btn btn-sm btn-outline-primary rounded-pill">
                                            Re-register
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
