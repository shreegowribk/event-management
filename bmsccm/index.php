<?php
require_once __DIR__ . '/config/db.php';

$search_query    = trim($_GET['q'] ?? '');
$category_filter = trim($_GET['category'] ?? 'all');
$time_filter     = trim($_GET['time'] ?? 'upcoming');

$sql = "SELECT e.event_id, e.event_name, e.category, e.event_date, e.event_time, e.venue, e.description, e.max_seats,
               COUNT(r.registration_id) AS registered_count
        FROM events e
        LEFT JOIN registrations r ON e.event_id = r.event_id AND r.status = 'confirmed'
        WHERE 1=1";

$types = "";
$params = [];

if (!empty($search_query)) {
    $sql .= " AND (e.event_name LIKE ? OR e.venue LIKE ? OR e.description LIKE ?)";
    $like = '%' . $search_query . '%';
    $types .= "sss";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

if (!empty($category_filter) && $category_filter !== 'all') {
    $sql .= " AND e.category = ?";
    $types .= "s";
    $params[] = $category_filter;
}

if ($time_filter === 'upcoming') {
    $sql .= " AND e.event_date >= CURDATE()";
}

$sql .= " GROUP BY e.event_id ORDER BY e.event_date ASC, e.event_time ASC";

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$events_result = $stmt->get_result();

$events = [];
while ($row = $events_result->fetch_assoc()) {
    $events[] = $row;
}
$stmt->close();

$student_registered_map = [];
if (is_student_logged_in()) {
    $student_id = (int)$_SESSION['student_id'];
    $stmt_reg = $conn->prepare("SELECT event_id, ticket_number, registration_id FROM registrations WHERE student_id = ? AND status = 'confirmed'");
    $stmt_reg->bind_param("i", $student_id);
    $stmt_reg->execute();
    $res_reg = $stmt_reg->get_result();
    while ($r = $res_reg->fetch_assoc()) {
        $student_registered_map[$r['event_id']] = [
            'ticket_number'   => $r['ticket_number'],
            'registration_id' => $r['registration_id']
        ];
    }
    $stmt_reg->close();
}

$page_title = "Upcoming Events & Registrations";
include __DIR__ . '/includes/header.php';
?>

<section class="bms-hero">
    <div class="container hero-content">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <div class="hero-pill">
                    <i class="bi bi-stars"></i> BMSCCM Campus Life 2026
                </div>
                <h1 class="display-4 fw-extrabold text-white mb-3">
                    Discover, Participate &amp; Excel at <span class="text-warning">BMSCCM</span>
                </h1>
                <p class="lead text-light opacity-90 mb-4" style="max-width: 650px;">
                    Explore technical hackathons, cultural festivals, management conclaves, and athletic tournaments. Register in seconds and carry your instant e-ticket on your mobile.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="#events-section" class="btn btn-warning btn-lg px-4 py-2 fw-bold text-dark rounded-pill shadow">
                        <i class="bi bi-ticket-perforated-fill me-2"></i> Explore Events
                    </a>
                    <?php if (!is_student_logged_in()): ?>
                        <a href="register_student.php" class="btn btn-outline-light btn-lg px-4 py-2 fw-bold rounded-pill">
                            <i class="bi bi-person-plus-fill me-2"></i> Student Sign Up
                        </a>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-outline-light btn-lg px-4 py-2 fw-bold rounded-pill">
                            <i class="bi bi-speedometer2 me-2"></i> My Passes
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-4 d-none d-lg-block text-end">
                <div class="p-4 bg-white bg-opacity-10 rounded-4 border border-white border-opacity-25 text-start backdrop-blur shadow-lg">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle bg-warning text-dark p-3 fs-3 d-flex align-items-center justify-content-center" style="width: 54px; height: 54px;">
                            <i class="bi bi-calendar-check-fill"></i>
                        </div>
                        <div>
                            <h4 class="text-white fw-bold mb-0"><?= count($events) ?> Events</h4>
                            <span class="text-white-50 small">Open for Registration</span>
                        </div>
                    </div>
                    <p class="text-light small mb-0">
                        <i class="bi bi-shield-check text-warning me-1"></i> Official verified e-tickets with anti-duplicate QR verification.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-4 bg-white border-bottom shadow-sm" id="events-section">
    <div class="container">
        <form method="GET" action="index.php#events-section" class="row g-3 align-items-center">
            <div class="col-lg-5 col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Search by event title, venue, or keyword..." value="<?= escape($search_query) ?>">
                </div>
            </div>

            <div class="col-lg-5 col-md-6">
                <select name="category" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?= $category_filter === 'all' ? 'selected' : '' ?>>All Categories (Tech, Cultural, Sports...)</option>
                    <option value="Technical" <?= $category_filter === 'Technical' ? 'selected' : '' ?>>Technical &amp; Coding</option>
                    <option value="Cultural" <?= $category_filter === 'Cultural' ? 'selected' : '' ?>>Cultural &amp; Arts</option>
                    <option value="Management" <?= $category_filter === 'Management' ? 'selected' : '' ?>>Management &amp; Business</option>
                    <option value="Workshop" <?= $category_filter === 'Workshop' ? 'selected' : '' ?>>Workshops &amp; Seminars</option>
                    <option value="Sports" <?= $category_filter === 'Sports' ? 'selected' : '' ?>>Sports &amp; Athletics</option>
                </select>
            </div>

            <div class="col-lg-2 col-12 d-flex gap-2">
                <select name="time" class="form-select" onchange="this.form.submit()">
                    <option value="upcoming" <?= $time_filter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                    <option value="all" <?= $time_filter === 'all' ? 'selected' : '' ?>>All Dates</option>
                </select>
                <button type="submit" class="btn btn-primary px-3"><i class="bi bi-arrow-clockwise"></i></button>
            </div>
        </form>
    </div>
</section>

<main class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold text-dark mb-1">Campus Events Roster</h3>
            <p class="text-muted small mb-0">Showing <?= count($events) ?> available events</p>
        </div>
        <?php if (!empty($search_query) || $category_filter !== 'all' || $time_filter !== 'upcoming'): ?>
            <a href="index.php" class="btn btn-sm btn-outline-secondary rounded-pill">
                <i class="bi bi-x-circle me-1"></i> Clear Filters
            </a>
        <?php endif; ?>
    </div>

    <?php if (empty($events)): ?>
        <div class="card bms-card border-0 text-center py-5 shadow-sm">
            <div class="card-body">
                <div class="text-muted fs-1 mb-3"><i class="bi bi-calendar-x"></i></div>
                <h4 class="fw-bold text-dark">No Events Found</h4>
                <p class="text-muted">We couldn't find any events matching your search or category criteria.</p>
                <a href="index.php" class="btn btn-primary rounded-pill px-4">View All Events</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($events as $evt): 
                $seats_left = max(0, $evt['max_seats'] - $evt['registered_count']);
                $is_full = ($seats_left <= 0);
                $is_registered = isset($student_registered_map[$evt['event_id']]);
                
                $cat = strtolower($evt['category']);
                $cat_class = 'badge-def';
                if (str_contains($cat, 'tech')) $cat_class = 'badge-tech';
                elseif (str_contains($cat, 'cult')) $cat_class = 'badge-cult';
                elseif (str_contains($cat, 'mgmt') || str_contains($cat, 'manag')) $cat_class = 'badge-mgmt';
                elseif (str_contains($cat, 'work')) $cat_class = 'badge-work';
                elseif (str_contains($cat, 'spor')) $cat_class = 'badge-spor';

                $percent = ($evt['max_seats'] > 0) ? min(100, round(($evt['registered_count'] / $evt['max_seats']) * 100)) : 100;
                $bar_color = ($percent >= 90) ? 'bg-danger' : (($percent >= 65) ? 'bg-warning' : 'bg-success');
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="card bms-card h-100 d-flex flex-column border-0 shadow-sm">
                    <div class="card-header-accent d-flex justify-content-between align-items-center">
                        <span class="badge-category <?= $cat_class ?>">
                            <i class="bi bi-tag-fill me-1"></i><?= escape($evt['category']) ?>
                        </span>
                        <span class="small text-muted fw-semibold">
                            <i class="bi bi-calendar3 me-1 text-primary"></i><?= format_date($evt['event_date']) ?>
                        </span>
                    </div>

                    <div class="card-body d-flex flex-column p-4 flex-grow-1">
                        <h5 class="fw-bold text-dark mb-2"><?= escape($evt['event_name']) ?></h5>
                        <p class="text-muted small mb-3 flex-grow-1" style="line-height: 1.55;">
                            <?= escape(mb_strimwidth($evt['description'], 0, 140, '...')) ?>
                        </p>

                        <div class="p-3 bg-light rounded-3 mb-3 small">
                            <div class="d-flex align-items-center gap-2 mb-2 text-secondary">
                                <i class="bi bi-clock-fill text-primary"></i>
                                <span><?= format_time($evt['event_time']) ?></span>
                            </div>
                            <div class="d-flex align-items-center gap-2 text-secondary">
                                <i class="bi bi-geo-alt-fill text-danger"></i>
                                <span class="text-truncate"><?= escape($evt['venue']) ?></span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center seat-indicator mb-1">
                                <?php if ($is_full): ?>
                                    <span class="text-danger fw-bold"><i class="bi bi-x-octagon me-1"></i>Housefull</span>
                                <?php else: ?>
                                    <span class="text-success fw-bold"><i class="bi bi-people-fill me-1"></i><?= $seats_left ?> seats left</span>
                                <?php endif; ?>
                                <span class="text-muted small"><?= $evt['registered_count'] ?> / <?= $evt['max_seats'] ?> booked</span>
                            </div>
                            <div class="progress progress-seats">
                                <div class="progress-bar <?= $bar_color ?>" role="progressbar" style="width: <?= $percent ?>%;"></div>
                            </div>
                        </div>

                        <div class="mt-auto">
                            <?php if ($is_registered): ?>
                                <a href="ticket.php?ticket=<?= urlencode($student_registered_map[$evt['event_id']]['ticket_number']) ?>" class="btn btn-success w-100 fw-bold py-2 shadow-sm rounded-pill">
                                    <i class="bi bi-qr-code-scan me-1"></i> View E-Ticket
                                </a>
                            <?php elseif ($is_full): ?>
                                <button class="btn btn-secondary w-100 fw-bold py-2 rounded-pill" disabled>
                                    <i class="bi bi-lock-fill me-1"></i> Registration Closed
                                </button>
                            <?php else: ?>
                                <a href="event_register.php?event_id=<?= (int)$evt['event_id'] ?>" class="btn btn-primary w-100 fw-bold py-2 shadow-sm rounded-pill">
                                    <i class="bi bi-pencil-square me-1"></i> Register Now
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/footer.php'; ?>
