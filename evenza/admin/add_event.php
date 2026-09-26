<?php
require_once __DIR__ . '/../config/db.php';
require_admin_login();

$errors = [];
$event_name  = '';
$category    = 'Technical';
$event_date  = '';
$event_time  = '';
$venue       = '';
$description = '';
$max_seats   = 50;

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

        if (empty($event_name))  $errors[] = "Event title is required.";
        if (empty($category))    $errors[] = "Please specify a category.";
        if (empty($event_date) || !is_valid_date($event_date))  $errors[] = "A valid event date (YYYY-MM-DD) is required.";
        if (empty($event_time) || !is_valid_time($event_time))  $errors[] = "A valid event time (HH:MM) is required.";
        if (empty($venue))       $errors[] = "Venue location is required.";
        if ($max_seats <= 0)     $errors[] = "Maximum seats capacity must be at least 1.";
        if (empty($description)) $errors[] = "Please provide an event description.";

    if (empty($errors)) {
        $admin_id = (int)$_SESSION['admin_id'];

        $stmt = $conn->prepare("
            INSERT INTO events (event_name, category, event_date, event_time, venue, description, max_seats, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssssii", $event_name, $category, $event_date, $event_time, $venue, $description, $max_seats, $admin_id);

        if ($stmt->execute()) {
            $stmt->close();
            set_flash('success', 'New campus event published successfully on Evenza!');
            header('Location: admin_dashboard.php');
            exit;
        } else {
            $errors[] = "Database error creating event. Please try again.";
            $stmt->close();
        }
    }
}
}

$page_title = "Add Campus Event";
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
                <div class="card-header bg-white py-3 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">
                        <i class="bi bi-calendar-plus text-primary me-2"></i>Create New Campus Event
                    </h4>
                    <span class="text-muted small">Publish an event to the Evenza catalog.</span>
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

                    <form method="POST" action="add_event.php">
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="event_name" class="form-label small fw-bold text-secondary">Event Title</label>
                                <input type="text" class="form-control" id="event_name" name="event_name" value="<?= escape($event_name) ?>" placeholder="e.g. CodeArena: Coding Contest" required>
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
                                <label for="max_seats" class="form-label small fw-bold text-secondary">Max Seating Capacity</label>
                                <input type="number" class="form-control" id="max_seats" name="max_seats" value="<?= (int)$max_seats ?>" min="1" max="5000" required>
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
                                <label for="venue" class="form-label small fw-bold text-secondary">Venue / Hall Location</label>
                                <input type="text" class="form-control" id="venue" name="venue" value="<?= escape($venue) ?>" placeholder="e.g. Campus Main Auditorium" required>
                            </div>

                            <div class="col-12">
                                <label for="description" class="form-label small fw-bold text-secondary">Description &amp; Guidelines</label>
                                <textarea class="form-control" id="description" name="description" rows="5" placeholder="Details regarding agenda, prerequisites, and rules..." required><?= escape($description) ?></textarea>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                            <a href="admin_dashboard.php" class="btn btn-outline-secondary rounded-pill px-4">Cancel</a>
                            <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                                <i class="bi bi-cloud-upload me-1"></i> Publish on Evenza
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
