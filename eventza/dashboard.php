<?php
require_once __DIR__ . '/config/db.php';
require_student_login();

$student_id = (int)$_SESSION['student_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_reg_id'])) {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security token expired or invalid (CSRF protection). Please try again.');
        header('Location: dashboard.php');
        exit;
    }

    $cancel_id = intval($_POST['cancel_reg_id']);
    if ($cancel_id <= 0) {
        set_flash('danger', 'Invalid registration ID specified.');
        header('Location: dashboard.php');
        exit;
    }

    $stmt_cancel = $conn->prepare("UPDATE registrations SET status = 'cancelled' WHERE registration_id = ? AND student_id = ?");
    $stmt_cancel->bind_param("ii", $cancel_id, $student_id);
    if ($stmt_cancel->execute() && $stmt_cancel->affected_rows > 0) {
        set_flash('info', 'Your event registration was cancelled successfully.');
    } else {
        error_log("Cancellation failed for registration ID {$cancel_id}, student ID {$student_id}: " . $stmt_cancel->error);
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
    SELECT r.registration_id, r.ticket_number, r.registered_at, r.status, r.checked_in, r.checked_in_at,
           e.event_id, e.event_name, e.category, e.event_date, e.event_time, e.venue, e.status AS event_status
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
$attended_count  = 0;
$today = date('Y-m-d');

while ($r = $regs_res->fetch_assoc()) {
    $registrations[] = $r;
    if ($r['status'] === 'confirmed') {
        $confirmed_count++;
        if ($r['event_date'] >= $today && ($r['event_status'] ?? 'upcoming') === 'upcoming') {
            $upcoming_count++;
        }
    }
    if ((int)$r['checked_in'] === 1) {
        $attended_count++;
    }
}
$stmt_regs->close();

$page_title = "Student Dashboard";
include __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Student Profile Header Card -->
    <div class="card event-card mb-4">
        <div class="card-body p-4">
            <div class="row align-items-center g-3">
                <div class="col-md-auto text-center">
                    <div class="brand-monogram" style="width: 60px; height: 60px; font-size: 1.6rem; border-radius: 12px;">
                        <?= strtoupper(substr($student['full_name'], 0, 1)) ?>
                    </div>
                </div>
                <div class="col-md">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <h3 class="fw-bold text-dark mb-0"><?= escape($student['full_name']) ?></h3>
                        <span class="badge bg-light text-primary border fw-semibold px-2 py-1"><?= escape($student['course']) ?></span>
                    </div>
                    <div class="text-secondary small d-flex flex-wrap gap-3">
                        <span><strong class="text-dark">USN:</strong> <code class="text-uppercase"><?= escape($student['usn']) ?></code></span>
                        <span><i class="bi bi-envelope text-muted me-1"></i><?= escape($student['email']) ?></span>
                        <span><i class="bi bi-telephone text-muted me-1"></i><?= escape($student['phone']) ?></span>
                    </div>
                </div>
                <div class="col-md-auto text-md-end">
                    <a href="index.php" class="btn btn-primary rounded-pill px-4 fw-semibold">
                        <i class="bi bi-plus-lg me-1"></i> Browse Campus Events
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Numerical Overview -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card event-card p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted fw-semibold text-uppercase">Total Registrations</div>
                        <div class="fs-3 fw-bold text-dark brand-font"><?= count($registrations) ?></div>
                    </div>
                    <div class="rounded-circle bg-light p-3 text-primary fs-4">
                        <i class="bi bi-journal-check"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card event-card p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted fw-semibold text-uppercase">Confirmed Passes</div>
                        <div class="fs-3 fw-bold text-success brand-font"><?= $confirmed_count ?></div>
                    </div>
                    <div class="rounded-circle bg-light p-3 text-success fs-4">
                        <i class="bi bi-pass"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card event-card p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="small text-muted fw-semibold text-uppercase">Upcoming Events</div>
                        <div class="fs-3 fw-bold text-dark brand-font"><?= $upcoming_count ?></div>
                    </div>
                    <div class="rounded-circle bg-light p-3 text-warning fs-4">
                        <i class="bi bi-calendar3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Registrations Roster Table -->
    <div class="card event-card">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="fw-bold text-dark mb-0">My Event Passes &amp; Registrations</h5>
                <span class="text-muted small" id="dashboardCountBadge"><?= count($registrations) ?> Total Records</span>
            </div>
            <?php if (!empty($registrations)): ?>
                <div style="max-width: 280px; width: 100%;">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" id="dashboardFilterInput" class="form-control border-start-0" placeholder="Filter passes...">
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="card-body p-0">
            <?php if (empty($registrations)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-pass fs-1 d-block mb-2 text-warning"></i>
                    <h5 class="fw-bold text-dark">No registered events yet</h5>
                    <p class="small mb-3">Explore the schedule and secure your seat for upcoming campus fests.</p>
                    <a href="index.php" class="btn btn-primary rounded-pill px-4 fw-semibold shadow-sm">Browse Campus Events</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom mb-0" id="dashboardTable">
                        <thead>
                            <tr>
                                <th>Event Name</th>
                                <th>Date &amp; Location</th>
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
                            <tr class="dashboard-reg-row" data-search="<?= strtolower(escape($r['event_name'] . ' ' . $r['ticket_number'] . ' ' . $r['venue'] . ' ' . $r['category'])) ?>">
                                <td>
                                    <div class="fw-bold text-dark fs-6"><?= escape($r['event_name']) ?></div>
                                    <span class="badge bg-light text-secondary border small"><?= escape($r['category']) ?></span>
                                    <?php if ($is_past): ?>
                                        <span class="badge bg-secondary text-white small ms-1">Concluded</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark"><i class="bi bi-calendar3 text-primary me-1"></i><?= format_date($r['event_date']) ?></div>
                                    <div class="small text-muted"><i class="bi bi-clock me-1"></i><?= format_time($r['event_time']) ?></div>
                                    <div class="small text-muted text-truncate" style="max-width: 180px;"><i class="bi bi-geo-alt me-1 text-danger"></i><?= escape($r['venue']) ?></div>
                                </td>
                                <td>
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <code class="fw-bold text-primary bg-light px-2 py-1 rounded small">
                                            <?= escape($r['ticket_number']) ?>
                                        </code>
                                        <button type="button" class="btn btn-link btn-sm text-secondary p-0" onclick="navigator.clipboard.writeText('<?= escape($r['ticket_number']) ?>'); showEvenzaToast('Copied: <?= escape($r['ticket_number']) ?>');" title="Copy Pass ID">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                    <div class="text-muted small mt-1" style="font-size: 0.72rem;">
                                        Booked: <?= date('d M Y', strtotime($r['registered_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($r['status'] === 'confirmed'): ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill">
                                            Confirmed
                                        </span>
                                        <?php if ((int)$r['checked_in'] === 1): ?>
                                            <div class="badge bg-success text-white px-2 py-1 rounded-pill d-block mt-1" style="font-size: 0.7rem;">
                                                <i class="bi bi-check-circle-fill me-1"></i> Verified Entry
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border px-2 py-1 rounded-pill">
                                            Cancelled
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?php if ($is_active): ?>
                                        <a href="ticket.php?ticket=<?= urlencode($r['ticket_number']) ?>" class="btn btn-sm btn-primary rounded-pill px-3 me-1 shadow-sm">
                                            <i class="bi bi-pass me-1"></i> E-Ticket
                                        </a>
                                        <?php if ((int)$r['checked_in'] === 0): ?>
                                            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill" onclick="openCancelModal(<?= (int)$r['registration_id'] ?>, '<?= escape(addslashes($r['event_name'])) ?>')" title="Cancel Pass">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        <?php endif; ?>
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

<!-- Cancellation Confirmation Modal -->
<div class="modal fade" id="cancelConfirmModal" tabindex="-1" aria-labelledby="cancelModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold text-danger" id="cancelModalTitle">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>Cancel Pass Reservation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="mb-2">Are you sure you want to cancel your seat for:</p>
                <div class="alert alert-light border fw-bold text-dark" id="cancelModalEventName"></div>
                <p class="small text-muted mb-0">
                    <i class="bi bi-info-circle me-1 text-warning"></i> Your reserved seat will be immediately released back into the available pool for other students.
                </p>
            </div>
            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Keep My Pass</button>
                <form method="POST" action="dashboard.php" id="modalCancelForm" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="cancel_reg_id" id="modalCancelRegId" value="">
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">
                        Yes, Cancel Seat
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
let cancelModal = null;

function openCancelModal(regId, eventName) {
    if (!cancelModal) {
        cancelModal = new bootstrap.Modal(document.getElementById('cancelConfirmModal'));
    }
    document.getElementById('modalCancelRegId').value = regId;
    document.getElementById('cancelModalEventName').textContent = eventName;
    cancelModal.show();
}

document.addEventListener('DOMContentLoaded', () => {
    const filterInput = document.getElementById('dashboardFilterInput');
    const rows = document.querySelectorAll('.dashboard-reg-row');
    const badge = document.getElementById('dashboardCountBadge');

    if (filterInput) {
        filterInput.addEventListener('input', () => {
            const query = filterInput.value.toLowerCase().trim();
            let count = 0;
            rows.forEach(r => {
                const text = r.getAttribute('data-search') || '';
                if (!query || text.includes(query)) {
                    r.style.display = '';
                    count++;
                } else {
                    r.style.display = 'none';
                }
            });
            if (badge) {
                badge.textContent = `${count} Records Found`;
            }
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

