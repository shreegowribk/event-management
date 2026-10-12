<?php
require_once __DIR__ . '/../config/db.php';
require_admin_login();

$event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($event_id <= 0) {
    render_404("Invalid event identifier specified for editing.");
}

$stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ? LIMIT 1");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    render_404("The campus event requested for modification was not found in Evenza.");
}

$stmt_booked = $conn->prepare("SELECT COUNT(*) AS booked FROM registrations WHERE event_id = ? AND status = 'confirmed'");
$stmt_booked->bind_param("i", $event_id);
$stmt_booked->execute();
$current_booked = (int)$stmt_booked->get_result()->fetch_assoc()['booked'];
$stmt_booked->close();

$errors = [];
$event_name  = $event['event_name'];
$category    = $event['category'];
$event_date  = $event['event_date'];
$event_time  = $event['event_time'];
$venue       = $event['venue'];
$description = $event['description'];
$max_seats   = (int)$event['max_seats'];
$status      = $event['status'] ?? 'upcoming';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token()) {
        $errors[] = "Security token expired or invalid (CSRF protection). Please refresh and submit again.";
    } else {
        $event_name  = trim($_POST['event_name'] ?? '');
        $category    = trim($_POST['category'] ?? '');
        $event_date  = trim($_POST['event_date'] ?? '');
        $event_time  = trim($_POST['event_time'] ?? '');
        $venue       = trim($_POST['venue'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $max_seats   = (int)($_POST['max_seats'] ?? 0);
        $status      = trim($_POST['status'] ?? 'upcoming');
        if (!in_array($status, ['upcoming', 'completed', 'cancelled'])) {
            $status = 'upcoming';
        }

        if (empty($event_name))  $errors[] = "Event title is required.";
        if (empty($category))    $errors[] = "Please specify a category.";
        if (empty($event_date) || !is_valid_date($event_date))  $errors[] = "A valid event date (YYYY-MM-DD) is required.";
        if (empty($event_time) || !is_valid_time($event_time))  $errors[] = "A valid event time (HH:MM) is required.";
        if (empty($venue))       $errors[] = "Venue location is required.";
        if ($max_seats <= 0)     $errors[] = "Maximum seats capacity must be at least 1.";
        if ($max_seats < $current_booked) {
            $errors[] = "Cannot reduce max seats to {$max_seats} because {$current_booked} students have already registered.";
        }
        if (empty($description)) $errors[] = "Please provide an event description.";

    if (empty($errors)) {
        $stmt_upd = $conn->prepare("
            UPDATE events 
            SET event_name = ?, category = ?, event_date = ?, event_time = ?, venue = ?, description = ?, max_seats = ?, status = ?
            WHERE event_id = ?
        ");
        $stmt_upd->bind_param("ssssssisi", $event_name, $category, $event_date, $event_time, $venue, $description, $max_seats, $status, $event_id);

        if ($stmt_upd->execute()) {
            $stmt_upd->close();
            set_flash('success', 'Event logistics updated successfully.');
            header('Location: admin_dashboard.php');
            exit;
        } else {
            $errors[] = "Database update error. Please try again.";
            $stmt_upd->close();
        }
    }
}
}

$page_title = "Edit Event";
include __DIR__ . '/admin_header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="mb-3">
                <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                    <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>

            <div class="card evenza-card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h4 class="fw-bold text-dark mb-0">
                            <i class="bi bi-pencil-square text-primary me-2"></i>Edit Event Logistics
                        </h4>
                        <span class="text-muted small">Update event schedule or capacity.</span>
                    </div>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill">
                        <i class="bi bi-person-check-fill me-1"></i> <?= $current_booked ?> Booked
                    </span>
                </div>

                <div class="card-body p-4">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger shadow-sm border-0 mb-4">
                            <h6 class="alert-heading fw-bold mb-2"><i class="bi bi-exclamation-triangle-fill me-2"></i>Please resolve the following:</h6>
                            <ul class="mb-0 ps-3 small">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= escape($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="edit_event.php?event_id=<?= $event_id ?>">
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="event_name" class="form-label small fw-bold text-secondary">Event Title</label>
                                <input type="text" class="form-control" id="event_name" name="event_name" value="<?= escape($event_name) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="category" class="form-label small fw-bold text-secondary">Category</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="Technical" <?= $category === 'Technical' ? 'selected' : '' ?>>Technical &amp; Coding</option>
                                    <option value="Cultural" <?= $category === 'Cultural' ? 'selected' : '' ?>>Cultural &amp; Arts</option>
                                    <option value="Management" <?= $category === 'Management' ? 'selected' : '' ?>>Management &amp; Business</option>
                                    <option value="Workshop" <?= $category === 'Workshop' ? 'selected' : '' ?>>Workshop / Bootcamp</option>
                                    <option value="Sports" <?= $category === 'Sports' ? 'selected' : '' ?>>Sports &amp; Athletics</option>
                                    <option value="Academic" <?= $category === 'Academic' ? 'selected' : '' ?>>Academic Seminar</option>
                                    <option value="Other" <?= $category === 'Other' ? 'selected' : '' ?>>Other Event</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="max_seats" class="form-label small fw-bold text-secondary">Max Capacity</label>
                                <input type="number" class="form-control" id="max_seats" name="max_seats" value="<?= (int)$max_seats ?>" min="<?= max(1, $current_booked) ?>" required>
                                <div class="form-text small">Minimum allowed: <?= $current_booked ?> (already confirmed).</div>
                            </div>

                            <div class="col-md-6">
                                <label for="status" class="form-label small fw-bold text-secondary">Event Lifecycle Status</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="upcoming" <?= $status === 'upcoming' ? 'selected' : '' ?>>Upcoming (Registrations Open)</option>
                                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed / Closed</option>
                                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                </select>
                                <div class="form-text small">Closing an event stops new student registrations.</div>
                            </div>

                            <div class="col-md-6">
                                <label for="event_date" class="form-label small fw-bold text-secondary">Date</label>
                                <input type="date" class="form-control" id="event_date" name="event_date" value="<?= escape($event_date) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="event_time" class="form-label small fw-bold text-secondary">Time</label>
                                <input type="time" class="form-control" id="event_time" name="event_time" value="<?= escape($event_time) ?>" required>
                            </div>

                            <div class="col-12">
                                <label for="venue" class="form-label small fw-bold text-secondary">Venue</label>
                                <input type="text" class="form-control" id="venue" name="venue" value="<?= escape($venue) ?>" required>
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label small fw-bold text-secondary">Description &amp; Guidelines</label>
                                <textarea class="form-control" id="description" name="description" rows="5" required><?= escape($description) ?></textarea>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <a href="admin_dashboard.php" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-danger rounded-pill px-3" onclick="if(confirm('Are you sure you want to permanently delete this event and all its registrations?')) { document.getElementById('deleteEventForm').submit(); }">
                                    <i class="bi bi-trash me-1"></i> Delete Event
                                </button>
                                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                                    <i class="bi bi-check-lg me-1"></i> Save Changes
                                </button>
                            </div>
                        </div>
                    </form>
                    <form id="deleteEventForm" method="POST" action="admin_dashboard.php" style="display: none;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="delete_event_id" value="<?= (int)$event_id ?>">
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
