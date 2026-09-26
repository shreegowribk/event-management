<?php
require_once __DIR__ . '/../config/db.php';
require_admin_login();

$event_id_filter = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$search_query    = trim($_GET['q'] ?? '');
$status_filter   = trim($_GET['status'] ?? 'all');

$all_events_res = $conn->query("SELECT event_id, event_name, event_date FROM events ORDER BY event_date DESC");
$all_events = [];
while ($row = $all_events_res->fetch_assoc()) {
    $all_events[] = $row;
}

$sql = "
    SELECT r.registration_id, r.ticket_number, r.registered_at, r.status,
           s.student_id, s.full_name, s.usn, s.email, s.phone, s.course,
           e.event_id, e.event_name, e.event_date, e.event_time, e.venue
    FROM registrations r
    JOIN students s ON r.student_id = s.student_id
    JOIN events e ON r.event_id = e.event_id
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
while ($row = $registrations_res->fetch_assoc()) {
    $registrations[] = $row;
}
$stmt->close();

$current_event_meta = null;
if ($event_id_filter > 0) {
    foreach ($all_events as $ev) {
        if ((int)$ev['event_id'] === $event_id_filter) {
            $current_event_meta = $ev;
            break;
        }
    }
}

$page_title = "Registrations Roster";
include __DIR__ . '/admin_header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-1">Event Registrations Roster</h3>
            <p class="text-muted small mb-0">
                <?php if ($current_event_meta): ?>
                    Showing attendees for: <strong><?= escape($current_event_meta['event_name']) ?></strong> (<?= format_date($current_event_meta['event_date']) ?>)
                <?php else: ?>
                    Showing all attendee registrations across college events.
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2 no-print">
            <button onclick="window.print()" class="btn btn-outline-dark rounded-pill px-3 fw-bold">
                <i class="bi bi-printer me-1"></i> Print Attendee Sheet
            </button>
            <a href="admin_dashboard.php" class="btn btn-primary rounded-pill px-3 fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <div class="card bms-card border-0 shadow-sm mb-4 no-print">
        <div class="card-body p-3">
            <form method="GET" action="view_registrations.php" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <label class="visually-hidden">Select Event</label>
                    <select name="event_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">All Campus Events</option>
                        <?php foreach ($all_events as $ev): ?>
                            <option value="<?= (int)$ev['event_id'] ?>" <?= $event_id_filter === (int)$ev['event_id'] ? 'selected' : '' ?>>
                                <?= escape($ev['event_name']) ?> (<?= format_date($ev['event_date']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <input type="text" name="q" class="form-control" placeholder="Search USN, Name, Ticket..." value="<?= escape($search_query) ?>">
                </div>

                <div class="col-md-2">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="confirmed" <?= $status_filter === 'confirmed' ? 'selected' : '' ?>>Confirmed Only</option>
                        <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>

                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-search me-1"></i> Filter</button>
                    <?php if ($event_id_filter > 0 || !empty($search_query) || $status_filter !== 'all'): ?>
                        <a href="view_registrations.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card bms-card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap">
            <h5 class="fw-bold text-dark mb-0">
                <i class="bi bi-card-checklist text-primary me-2"></i>Registrant Records
            </h5>
            <span class="badge bg-primary rounded-pill px-3"><?= count($registrations) ?> Registrations Found</span>
        </div>

        <div class="card-body p-0">
            <?php if (empty($registrations)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-people fs-1 d-block mb-2"></i>
                    <h5>No Registrations Found</h5>
                    <p class="small">No student registrations match the selected criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student Details</th>
                                <th>Course &amp; Contact</th>
                                <th>Event Details</th>
                                <th>Ticket Number</th>
                                <th>Status</th>
                                <th class="text-end no-print">Pass</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            foreach ($registrations as $r): 
                            ?>
                            <tr>
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
                                    <div class="font-monospace small fw-bold bg-light p-1 rounded border text-nowrap">
                                        <?= escape($r['ticket_number']) ?>
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
                                <td class="text-end no-print">
                                    <a href="../ticket.php?ticket=<?= urlencode($r['ticket_number']) ?>" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-2" title="Inspect E-Ticket">
                                        <i class="bi bi-qr-code"></i> Ticket
                                    </a>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
