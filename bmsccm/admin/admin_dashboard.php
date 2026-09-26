<?php
require_once __DIR__ . '/../config/db.php';
require_admin_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event_id'])) {
    $delete_id = (int)$_POST['delete_event_id'];

    $stmt_del = $conn->prepare("DELETE FROM events WHERE event_id = ?");
    $stmt_del->bind_param("i", $delete_id);
    if ($stmt_del->execute()) {
        set_flash('success', 'Event and associated registrations deleted successfully.');
    } else {
        set_flash('danger', 'Failed to delete event.');
    }
    $stmt_del->close();
    header('Location: admin_dashboard.php');
    exit;
}

$today = date('Y-m-d');

$res_events = $conn->query("SELECT COUNT(*) AS total FROM events");
$total_events = (int)$res_events->fetch_assoc()['total'];

$res_regs = $conn->query("SELECT COUNT(*) AS total FROM registrations WHERE status = 'confirmed'");
$total_regs = (int)$res_regs->fetch_assoc()['total'];

$res_students = $conn->query("SELECT COUNT(*) AS total FROM students");
$total_students = (int)$res_students->fetch_assoc()['total'];

$stmt_upcoming = $conn->prepare("SELECT COUNT(*) AS total FROM events WHERE event_date >= ?");
$stmt_upcoming->bind_param("s", $today);
$stmt_upcoming->execute();
$total_upcoming = (int)$stmt_upcoming->get_result()->fetch_assoc()['total'];
$stmt_upcoming->close();

$sql_list = "
    SELECT e.event_id, e.event_name, e.category, e.event_date, e.event_time, e.venue, e.max_seats, e.created_at,
           COUNT(r.registration_id) AS booked_count
    FROM events e
    LEFT JOIN registrations r ON e.event_id = r.event_id AND r.status = 'confirmed'
    GROUP BY e.event_id
    ORDER BY e.event_date DESC, e.event_time DESC
";
$events_list = $conn->query($sql_list);

$page_title = "Admin Dashboard";
include __DIR__ . '/admin_header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-1">Administrative Overview</h3>
            <p class="text-muted small mb-0">Manage campus events, monitor seat bookings, and track attendee rosters.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="add_event.php" class="btn btn-primary rounded-pill px-3 shadow-sm fw-bold">
                <i class="bi bi-plus-lg me-1"></i> Create New Event
            </a>
            <a href="view_registrations.php" class="btn btn-outline-dark rounded-pill px-3 fw-bold">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Full Attendee Roster
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-sm-6">
            <div class="admin-stat-card">
                <div class="admin-stat-icon stat-icon-blue">
                    <i class="bi bi-calendar-event"></i>
                </div>
                <div>
                    <h3 class="fw-bold text-dark mb-0"><?= $total_events ?></h3>
                    <span class="text-muted small">Total Events Hosted</span>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="admin-stat-card">
                <div class="admin-stat-icon stat-icon-green">
                    <i class="bi bi-ticket-perforated"></i>
                </div>
                <div>
                    <h3 class="fw-bold text-dark mb-0"><?= $total_regs ?></h3>
                    <span class="text-muted small">Confirmed Bookings</span>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="admin-stat-card">
                <div class="admin-stat-icon stat-icon-purple">
                    <i class="bi bi-mortarboard"></i>
                </div>
                <div>
                    <h3 class="fw-bold text-dark mb-0"><?= $total_students ?></h3>
                    <span class="text-muted small">Registered Students</span>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-sm-6">
            <div class="admin-stat-card">
                <div class="admin-stat-icon stat-icon-amber">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div>
                    <h3 class="fw-bold text-dark mb-0"><?= $total_upcoming ?></h3>
                    <span class="text-muted small">Active Upcoming Events</span>
                </div>
            </div>
        </div>
    </div>

    <div class="card bms-card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="fw-bold text-dark mb-0">
                <i class="bi bi-gear-wide-connected text-primary me-2"></i>Campus Events Inventory
            </h5>
            <span class="badge bg-light text-dark border"><?= $events_list->num_rows ?> events listed</span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-custom mb-0">
                    <thead>
                        <tr>
                            <th>Event Name &amp; Category</th>
                            <th>Date &amp; Schedule</th>
                            <th>Venue</th>
                            <th>Capacity / Seats</th>
                            <th class="text-end">Management Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($events_list->num_rows === 0): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-calendar-x fs-1 d-block mb-2"></i>
                                    No events created yet. Click <strong>"Create New Event"</strong> to publish your first campus fest!
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php while ($evt = $events_list->fetch_assoc()): 
                                $is_full = ($evt['booked_count'] >= $evt['max_seats']);
                                $percent = ($evt['max_seats'] > 0) ? min(100, round(($evt['booked_count'] / $evt['max_seats']) * 100)) : 100;
                                $is_past = ($evt['event_date'] < $today);
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= escape($evt['event_name']) ?></div>
                                    <span class="badge bg-light text-primary border small"><?= escape($evt['category']) ?></span>
                                    <?php if ($is_past): ?>
                                        <span class="badge bg-secondary small ms-1">Past</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success small ms-1">Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark"><i class="bi bi-calendar3 me-1 text-primary"></i><?= format_date($evt['event_date']) ?></div>
                                    <div class="small text-muted"><i class="bi bi-clock me-1"></i><?= format_time($evt['event_time']) ?></div>
                                </td>
                                <td>
                                    <div class="small text-truncate" style="max-width: 180px;" title="<?= escape($evt['venue']) ?>">
                                        <i class="bi bi-geo-alt text-danger me-1"></i><?= escape($evt['venue']) ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-between align-items-center small mb-1">
                                        <span class="fw-bold <?= $is_full ? 'text-danger' : 'text-dark' ?>">
                                            <?= $evt['booked_count'] ?> / <?= $evt['max_seats'] ?>
                                        </span>
                                        <span class="text-muted"><?= $percent ?>%</span>
                                    </div>
                                    <div class="progress" style="height: 6px; width: 120px;">
                                        <div class="progress-bar <?= ($percent >= 90) ? 'bg-danger' : (($percent >= 60) ? 'bg-warning' : 'bg-primary') ?>" style="width: <?= $percent ?>%"></div>
                                    </div>
                                </td>
                                <td class="text-end text-nowrap">
                                    <a href="view_registrations.php?event_id=<?= (int)$evt['event_id'] ?>" class="btn btn-sm btn-info text-dark rounded-pill px-2" title="View Registered Students">
                                        <i class="bi bi-people-fill me-1"></i> Attendees (<?= $evt['booked_count'] ?>)
                                    </a>
                                    <a href="edit_event.php?event_id=<?= (int)$evt['event_id'] ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-2 ms-1" title="Edit Event">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    <form method="POST" action="admin_dashboard.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this event? This will also remove all its student registrations.');">
                                        <input type="hidden" name="delete_event_id" value="<?= (int)$evt['event_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-2 ms-1" title="Delete Event">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
