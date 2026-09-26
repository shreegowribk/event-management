<?php
require_once __DIR__ . '/../config/db.php';
require_admin_login();

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
if ($event_id <= 0) {
    set_flash('danger', 'Invalid event selected for editing.');
    header('Location: admin_dashboard.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM events WHERE event_id = ? LIMIT 1");
$stmt->bind_param("i", $event_id);
$stmt->execute();
$event = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$event) {
    set_flash('danger', 'Event not found.');
    header('Location: admin_dashboard.php');
    exit;
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_name  = trim($_POST['event_name'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $event_date  = trim($_POST['event_date'] ?? '');
    $event_time  = trim($_POST['event_time'] ?? '');
    $venue       = trim($_POST['venue'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $max_seats   = (int)($_POST['max_seats'] ?? 0);

    if (empty($event_name))  $errors[] = "Event title is required.";
    if (empty($category))    $errors[] = "Please specify a category.";
    if (empty($event_date))  $errors[] = "Event date is required.";
    if (empty($event_time))  $errors[] = "Event time is required.";
    if (empty($venue))       $errors[] = "Campus venue / location is required.";
    if ($max_seats <= 0)     $errors[] = "Maximum seats capacity must be at least 1.";
    if ($max_seats < $current_booked) {
        $errors[] = "Cannot reduce max seats to {$max_seats} because {$current_booked} students have already confirmed registrations.";
    }
    if (empty($description)) $errors[] = "Please provide an event description.";

    if (empty($errors)) {
        $stmt_upd = $conn->prepare("
            UPDATE events 
            SET event_name = ?, category = ?, event_date = ?, event_time = ?, venue = ?, description = ?, max_seats = ?
            WHERE event_id = ?
        ");
        $stmt_upd->bind_param("ssssssii", $event_name, $category, $event_date, $event_time, $venue, $description, $max_seats, $event_id);

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

$page_title = "Edit Event - " . $event['event_name'];
include __DIR__ . '/admin_header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="mb-3">
                <a href="admin_dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill">
                    <i class="bi bi-arrow-left me-1"></i> Back to Admin Dashboard
                </a>
            </div>

            <div class="card bms-card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h4 class="fw-bold text-dark mb-0">
                            <i class="bi bi-pencil-square text-primary me-2"></i>Edit Event Details
                        </h4>
                        <span class="text-muted small">Update schedule, venue, capacity, or content.</span>
                    </div>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 px-3 py-2 rounded-pill">
                        <i class="bi bi-person-check-fill me-1"></i> <?= $current_booked ?> Confirmed Registrations
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
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="event_name" class="form-label small fw-bold text-secondary">Event Title / Name</label>
                                <input type="text" class="form-control" id="event_name" name="event_name" value="<?= escape($event_name) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="category" class="form-label small fw-bold text-secondary">Event Category</label>
                                <select class="form-select" id="category" name="category" required>
                                    <option value="Technical" <?= $category === 'Technical' ? 'selected' : '' ?>>Technical &amp; Coding</option>
                                    <option value="Cultural" <?= $category === 'Cultural' ? 'selected' : '' ?>>Cultural &amp; Arts</option>
                                    <option value="Management" <?= $category === 'Management' ? 'selected' : '' ?>>Management &amp; Business</option>
                                    <option value="Workshop" <?= $category === 'Workshop' ? 'selected' : '' ?>>Workshop / Hands-on Bootcamp</option>
                                    <option value="Sports" <?= $category === 'Sports' ? 'selected' : '' ?>>Sports &amp; Athletics</option>
                                    <option value="Academic" <?= $category === 'Academic' ? 'selected' : '' ?>>Academic Seminar</option>
                                    <option value="Other" <?= $category === 'Other' ? 'selected' : '' ?>>Other Event</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="max_seats" class="form-label small fw-bold text-secondary">Max Seating Capacity</label>
                                <input type="number" class="form-control" id="max_seats" name="max_seats" value="<?= (int)$max_seats ?>" min="<?= max(1, $current_booked) ?>" required>
                                <div class="form-text small">Minimum allowed: <?= $current_booked ?> (matches currently confirmed bookings).</div>
                            </div>

                            <div class="col-md-6">
                                <label for="event_date" class="form-label small fw-bold text-secondary">Event Date</label>
                                <input type="date" class="form-control" id="event_date" name="event_date" value="<?= escape($event_date) ?>" required>
                            </div>

                            <div class="col-md-6">
                                <label for="event_time" class="form-label small fw-bold text-secondary">Event Time</label>
                                <input type="time" class="form-control" id="event_time" name="event_time" value="<?= escape($event_time) ?>" required>
                            </div>

                            <div class="col-12">
                                <label for="venue" class="form-label small fw-bold text-secondary">Campus Venue / Location</label>
                                <input type="text" class="form-control" id="venue" name="venue" value="<?= escape($venue) ?>" required>
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label small fw-bold text-secondary">Detailed Description &amp; Rules</label>
                                <textarea class="form-control" id="description" name="description" rows="5" required><?= escape($description) ?></textarea>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                            <a href="admin_dashboard.php" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                                <i class="bi bi-check-lg me-1"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
