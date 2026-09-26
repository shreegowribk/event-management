<?php
require_once __DIR__ . '/../config/db.php';
require_admin_login();

$admin_id = (int)$_SESSION['admin_id'];

// Handle Attendance / Status Toggle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['reg_id'])) {
    if (!verify_csrf_token()) {
        set_flash('danger', 'Security token expired or invalid (CSRF protection). Please try again.');
        header('Location: view_registrations.php?' . http_build_query($_GET));
        exit;
    }
    $target_reg_id = intval($_POST['reg_id']);
    if ($target_reg_id <= 0) {
        set_flash('danger', 'Invalid registration ID specified.');
        header('Location: view_registrations.php?' . http_build_query($_GET));
        exit;
    }
    $action = $_POST['action'];

    if ($action === 'checkin') {
        $stmt_c = $conn->prepare("UPDATE registrations SET checked_in = 1, checked_in_at = NOW(), checked_in_by = ? WHERE registration_id = ?");
        $stmt_c->bind_param("ii", $admin_id, $target_reg_id);
        $stmt_c->execute();
        $stmt_c->close();
        set_flash('success', 'Student marked as Checked In.');
    } elseif ($action === 'undo_checkin') {
        $stmt_u = $conn->prepare("UPDATE registrations SET checked_in = 0, checked_in_at = NULL, checked_in_by = NULL WHERE registration_id = ?");
        $stmt_u->bind_param("i", $target_reg_id);
        $stmt_u->execute();
        $stmt_u->close();
        set_flash('info', 'Check-in status removed.');
    } elseif ($action === 'cancel_registration') {
        $stmt_can = $conn->prepare("UPDATE registrations SET status = 'cancelled' WHERE registration_id = ?");
        $stmt_can->bind_param("i", $target_reg_id);
        $stmt_can->execute();
        $stmt_can->close();
        set_flash('warning', 'Registration status changed to Cancelled.');
    } elseif ($action === 'confirm_registration') {
        $stmt_con = $conn->prepare("UPDATE registrations SET status = 'confirmed' WHERE registration_id = ?");
        $stmt_con->bind_param("i", $target_reg_id);
        $stmt_con->execute();
        $stmt_con->close();
        set_flash('success', 'Registration status reinstated to Confirmed.');
    }

    $redirect_url = 'view_registrations.php?' . http_build_query($_GET);
    header('Location: ' . $redirect_url);
    exit;
}

$event_id_filter   = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$search_query      = trim($_GET['q'] ?? '');
$status_filter     = trim($_GET['status'] ?? 'all');
$attendance_filter = trim($_GET['attendance'] ?? 'all');

$all_events_res = $conn->query("SELECT event_id, event_name, event_date FROM events ORDER BY event_date DESC");
$all_events = [];
while ($row = $all_events_res->fetch_assoc()) {
    $all_events[] = $row;
}

$sql = "
    SELECT r.registration_id, r.ticket_number, r.registered_at, r.status, r.checked_in, r.checked_in_at,
           s.student_id, s.full_name, s.usn, s.email, s.phone, s.course,
           e.event_id, e.event_name, e.event_date, e.event_time, e.venue,
           a.full_name AS verified_by_name
    FROM registrations r
    JOIN students s ON r.student_id = s.student_id
    JOIN events e ON r.event_id = e.event_id
    LEFT JOIN admins a ON r.checked_in_by = a.admin_id
    WHERE 1=1
";

$types = "";
$params = [];

if ($event_id_filter > 0) {
    $sql .= " AND r.event_id = ?";
    $types .= "i";
    $params[] = $event_id_filter;
}

if (!empty($status_filter) && $status_filter !== 'all') {
    $sql .= " AND r.status = ?";
    $types .= "s";
    $params[] = $status_filter;
}

if ($attendance_filter === 'checked_in') {
    $sql .= " AND r.checked_in = 1";
} elseif ($attendance_filter === 'pending') {
    $sql .= " AND r.checked_in = 0";
}

if (!empty($search_query)) {
    $sql .= " AND (s.full_name LIKE ? OR s.usn LIKE ? OR s.email LIKE ? OR r.ticket_number LIKE ?)";
    $like = '%' . $search_query . '%';
    $types .= "ssss";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY r.registered_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$registrations_res = $stmt->get_result();

$registrations = [];
$total_confirmed = 0;
$total_checked_in = 0;

while ($row = $registrations_res->fetch_assoc()) {
    $registrations[] = $row;
    if ($row['status'] === 'confirmed') {
        $total_confirmed++;
        if ((int)$row['checked_in'] === 1) {
            $total_checked_in++;
        }
    }
}
$stmt->close();

$attendance_rate = ($total_confirmed > 0) ? round(($total_checked_in / $total_confirmed) * 100, 1) : 0;

$current_event_meta = null;
if ($event_id_filter > 0) {
    foreach ($all_events as $ev) {
        if ((int)$ev['event_id'] === $event_id_filter) {
            $current_event_meta = $ev;
            break;
        }
    }
}

// Build Export Link
$export_params = $_GET;
$export_url = 'export_registrations.php?' . http_build_query($export_params);

$page_title = "Registrations Roster";
include __DIR__ . '/admin_header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-1">
                <i class="bi bi-card-checklist text-primary me-2"></i>Evenza Registrations Roster
            </h3>
            <p class="text-muted small mb-0">
                <?php if ($current_event_meta): ?>
                    Showing attendees for: <strong><?= escape($current_event_meta['event_name']) ?></strong>
                <?php else: ?>
                    Showing all registered attendees across events.
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap no-print">
            <a href="verify_ticket.php<?= $event_id_filter > 0 ? '?event_id='.$event_id_filter : '' ?>" class="btn btn-warning rounded-pill px-3 fw-bold text-dark shadow-sm">
                <i class="bi bi-qr-code-scan me-1"></i> Scan QR Passes
            </a>
            <a href="<?= escape($export_url) ?>" class="btn btn-success rounded-pill px-3 fw-bold shadow-sm">
                <i class="bi bi-file-earmark-excel me-1"></i> Export to CSV
            </a>
            <button onclick="window.print()" class="btn btn-outline-dark rounded-pill px-3 fw-bold">
                <i class="bi bi-printer me-1"></i> Print Sheet
            </button>
            <a href="admin_dashboard.php" class="btn btn-dark rounded-pill px-3 fw-semibold">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <!-- Attendance Key Numerical Metrics -->
    <div class="row g-3 mb-4 no-print">
        <div class="col-md-3 col-sm-6">
            <div class="admin-stat-card">
                <div class="admin-stat-icon stat-icon-blue">
                    <i class="bi bi-people"></i>
                </div>
                <div>
                    <h3 class="fw-bold text-dark mb-0"><?= count($registrations) ?></h3>
                    <span class="text-muted small">Total Found</span>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="admin-stat-card">
                <div class="admin-stat-icon stat-icon-green">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <div>
                    <h3 class="fw-bold text-dark mb-0"><?= $total_confirmed ?></h3>
                    <span class="text-muted small">Confirmed Bookings</span>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="admin-stat-card">
                <div class="admin-stat-icon stat-icon-purple">
                    <i class="bi bi-door-open"></i>
                </div>
                <div>
                    <h3 class="fw-bold text-dark mb-0"><?= $total_checked_in ?></h3>
                    <span class="text-muted small">Checked In At Venue</span>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="admin-stat-card">
                <div class="admin-stat-icon stat-icon-gold">
                    <i class="bi bi-percent"></i>
                </div>
                <div>
                    <h3 class="fw-bold text-dark mb-0"><?= $attendance_rate ?>%</h3>
                    <span class="text-muted small">Live Attendance Rate</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Floating Bar -->
    <div class="card evenza-card border-0 shadow-sm mb-4 no-print">
        <div class="card-body p-3">
            <form method="GET" action="view_registrations.php" class="row g-2 align-items-center">
                <div class="col-lg-4 col-md-6">
                    <select name="event_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">All Campus Events</option>
                        <?php foreach ($all_events as $ev): ?>
                            <option value="<?= (int)$ev['event_id'] ?>" <?= $event_id_filter === (int)$ev['event_id'] ? 'selected' : '' ?>>
                                <?= escape($ev['event_name']) ?> (<?= format_date($ev['event_date']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-lg-3 col-md-6">
                    <input type="text" name="q" id="rosterSearchInput" class="form-control" placeholder="Search USN, Name, Ticket..." value="<?= escape($search_query) ?>" autocomplete="off">
                </div>

                <div class="col-lg-2 col-md-4">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed Only</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <div class="col-lg-2 col-md-4">
                    <select name="attendance" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $attendance_filter === 'all' ? 'selected' : '' ?>>All Attendance</option>
                        <option value="checked_in" <?= $attendance_filter === 'checked_in' ? 'selected' : '' ?>>Checked In</option>
                        <option value="pending" <?= $attendance_filter === 'pending' ? 'selected' : '' ?>>Pending Entry</option>
                    </select>
                </div>

                <div class="col-lg-1 col-md-4 d-flex gap-1">
                    <button type="submit" class="btn btn-primary w-100" title="Search"><i class="bi bi-search"></i></button>
                    <?php if ($event_id_filter > 0 || !empty($search_query) || $status_filter !== 'all' || $attendance_filter !== 'all'): ?>
                        <a href="view_registrations.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Attendees Table Card -->
    <div class="card evenza-card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0">Registered Attendees List</h5>
            <span class="badge bg-light text-dark border px-3 py-2" id="rosterCountBadge">
                <?= count($registrations) ?> Records Found
            </span>
        </div>

        <div class="card-body p-0">
            <?php if (empty($registrations)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                    <h6>No registrations found</h6>
                    <p class="small mb-0">Try changing your filters or search terms.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="rosterTable">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Student Details</th>
                                <th>Course &amp; Contact</th>
                                <th>Event Details</th>
                                <th>Ticket Number</th>
                                <th>Status</th>
                                <th>Venue Entry</th>
                                <th class="text-end no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            foreach ($registrations as $r): 
                            ?>
                            <tr class="roster-row" data-roster-search="<?= strtolower(escape($r['full_name'] . ' ' . $r['usn'] . ' ' . $r['email'] . ' ' . $r['phone'] . ' ' . $r['event_name'] . ' ' . $r['ticket_number'])) ?>">
                                <td class="text-muted small"><?= $counter++ ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= escape($r['full_name']) ?></div>
                                    <code class="text-uppercase fw-bold text-primary small"><?= escape($r['usn']) ?></code>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border mb-1"><?= escape($r['course']) ?></span>
                                    <div class="small text-muted"><i class="bi bi-envelope me-1"></i><?= escape($r['email']) ?></div>
                                    <div class="small text-muted"><i class="bi bi-phone me-1"></i><?= escape($r['phone']) ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= escape($r['event_name']) ?></div>
                                    <div class="small text-secondary"><i class="bi bi-calendar3 me-1"></i><?= format_date($r['event_date']) ?></div>
                                    <div class="small text-muted"><i class="bi bi-geo-alt me-1"></i><?= escape($r['venue']) ?></div>
                                </td>
                                <td>
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <div class="font-monospace small fw-bold bg-light p-1 rounded border text-nowrap">
                                            <?= escape($r['ticket_number']) ?>
                                        </div>
                                        <button type="button" class="btn btn-link btn-sm text-secondary p-0 no-print" onclick="navigator.clipboard.writeText('<?= escape($r['ticket_number']) ?>'); showEvenzaToast('Copied: <?= escape($r['ticket_number']) ?>');" title="Copy Ticket">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                    <div class="text-muted small mt-1" style="font-size: 0.72rem;">
                                        <?= date('d M Y, h:i A', strtotime($r['registered_at'])) ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($r['status'] === 'confirmed'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
                                            Confirmed
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill">
                                            Cancelled
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int)$r['checked_in'] === 1): ?>
                                        <div class="badge bg-success text-white px-2 py-1 rounded-pill mb-1">
                                            <i class="bi bi-check-circle-fill me-1"></i> Checked In
                                        </div>
                                        <div class="small text-muted" style="font-size: 0.72rem;">
                                            <?= date('d M, h:i A', strtotime($r['checked_in_at'])) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill">
                                            Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end no-print">
                                    <div class="d-inline-flex gap-1 align-items-center">
                                        <!-- Quick Check-in Toggle Form -->
                                        <form method="POST" action="view_registrations.php?<?= http_build_query($_GET) ?>" class="d-inline">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="reg_id" value="<?= (int)$r['registration_id'] ?>">
                                            <?php if ((int)$r['checked_in'] === 1): ?>
                                                <input type="hidden" name="action" value="undo_checkin">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-2" title="Undo Check-In">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </button>
                                            <?php else: ?>
                                                <input type="hidden" name="action" value="checkin">
                                                <button type="submit" class="btn btn-sm btn-success rounded-pill px-2" title="Mark Checked In" <?= $r['status'] === 'cancelled' ? 'disabled' : '' ?>>
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>

                                        <!-- Status Toggle Dropdown -->
                                        <div class="dropdown d-inline">
                                            <button class="btn btn-sm btn-light border rounded-pill px-2" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                <li>
                                                    <a class="dropdown-item py-1 small" href="../ticket.php?ticket=<?= urlencode($r['ticket_number']) ?>" target="_blank">
                                                        <i class="bi bi-qr-code text-primary me-2"></i> View E-Ticket
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <?php if ($r['status'] === 'confirmed'): ?>
                                                    <li>
                                                        <form method="POST" action="view_registrations.php?<?= http_build_query($_GET) ?>">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="reg_id" value="<?= (int)$r['registration_id'] ?>">
                                                            <input type="hidden" name="action" value="cancel_registration">
                                                            <button type="submit" class="dropdown-item text-danger py-1 small" onclick="return confirm('Cancel this registration?');">
                                                                <i class="bi bi-x-circle me-2"></i> Cancel Registration
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php else: ?>
                                                    <li>
                                                        <form method="POST" action="view_registrations.php?<?= http_build_query($_GET) ?>">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="reg_id" value="<?= (int)$r['registration_id'] ?>">
                                                            <input type="hidden" name="action" value="confirm_registration">
                                                            <button type="submit" class="dropdown-item text-success py-1 small">
                                                                <i class="bi bi-check2 me-2"></i> Reinstate Registration
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                            </ul>
                                        </div>
                                    </div>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('rosterSearchInput');
    const rows = document.querySelectorAll('.roster-row');
    const countBadge = document.getElementById('rosterCountBadge');

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.toLowerCase().trim();
            let visibleCount = 0;

            rows.forEach(row => {
                const searchData = row.getAttribute('data-roster-search') || '';
                if (!query || searchData.includes(query)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (countBadge) {
                countBadge.textContent = `${visibleCount} Records Found`;
            }
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
