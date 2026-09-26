<?php
require_once __DIR__ . '/config/db.php';

$search_query    = trim($_GET['q'] ?? '');
$category_filter = trim($_GET['category'] ?? 'all');
$time_filter     = trim($_GET['time'] ?? 'upcoming');

// Build query
$sql = "SELECT e.event_id, e.event_name, e.category, e.event_date, e.event_time, e.venue, e.description, e.max_seats, e.status,
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
$total_available_seats = 0;
while ($row = $events_result->fetch_assoc()) {
    $events[] = $row;
    $total_available_seats += max(0, $row['max_seats'] - $row['registered_count']);
}
$stmt->close();

// Student registered events map
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

$page_title = "Browse Campus Events";
include __DIR__ . '/includes/header.php';
?>

<!-- Hero Banner -->
<section class="evenza-hero">
    <div class="container hero-content">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="hero-tag">
                    <i class="bi bi-stars me-1 text-warning"></i> Evenza Events Portal &bull; Academic Year 2026
                </div>
                <h1 class="hero-title mb-3">
                    Campus Events, Fests &amp; Competitions
                </h1>
                <p class="hero-lead mb-4">
                    The official digital platform to explore campus activities, reserve attendance, and generate verified electronic entry passes.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="#events-roster" class="btn btn-warning px-4 py-2 fw-bold text-dark rounded-pill">
                        <i class="bi bi-calendar-event me-2"></i> Explore Events Schedule
                    </a>
                    <?php if (!is_student_logged_in()): ?>
                        <a href="register_student.php" class="btn btn-outline-light px-4 py-2 fw-semibold rounded-pill">
                            <i class="bi bi-person-plus me-2"></i> Register Student
                        </a>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-outline-light px-4 py-2 fw-semibold rounded-pill">
                            <i class="bi bi-pass me-2"></i> View My Event Passes
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-4 d-none d-lg-block">
                <div class="hero-stat-card">
                    <h6 class="text-white-50 text-uppercase small fw-bold mb-3">Quick Overview</h6>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-white border-opacity-10">
                        <span class="text-light small">Listed Events:</span>
                        <span class="fw-bold fs-5 text-white brand-font"><?= count($events) ?> Active</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-white border-opacity-10">
                        <span class="text-light small">Open Seats Remaining:</span>
                        <span class="fw-bold fs-5 text-warning brand-font"><?= $total_available_seats ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom border-white border-opacity-10">
                        <span class="text-light small">Entry Verification:</span>
                        <span class="badge bg-success bg-opacity-25 text-success border border-success border-opacity-50">QR Verified</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center pt-1">
                        <span class="text-light small">Portal Status:</span>
                        <span class="text-warning small fw-semibold"><i class="bi bi-check-circle-fill me-1"></i> Live &amp; Active</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Filter & Search Floating Bar -->
<div class="container" id="events-roster">
    <div class="filter-container">
        <form method="GET" action="index.php#events-roster" class="row g-2 align-items-center mb-3">
            <div class="col-lg-5 col-md-6">
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" id="eventLiveSearchInput" class="form-control border-start-0" placeholder="Type to search events, venues, topics..." value="<?= escape($search_query) ?>" autocomplete="off">
                </div>
            </div>

            <div class="col-lg-4 col-md-6">
                <select name="category" id="categorySelectDropdown" class="form-select" onchange="this.form.submit()">
                    <option value="all" <?= $category_filter === 'all' ? 'selected' : '' ?>>All Categories (Tech, Cultural, Sports...)</option>
                    <option value="Technical" <?= $category_filter === 'Technical' ? 'selected' : '' ?>>Technical &amp; Coding</option>
                    <option value="Cultural" <?= $category_filter === 'Cultural' ? 'selected' : '' ?>>Cultural &amp; Fine Arts</option>
                    <option value="Management" <?= $category_filter === 'Management' ? 'selected' : '' ?>>Management &amp; Business</option>
                    <option value="Workshop" <?= $category_filter === 'Workshop' ? 'selected' : '' ?>>Workshops &amp; Masterclasses</option>
                    <option value="Sports" <?= $category_filter === 'Sports' ? 'selected' : '' ?>>Sports &amp; Athletics</option>
                </select>
            </div>

            <div class="col-lg-3 col-12 d-flex gap-2">
                <select name="time" class="form-select" onchange="this.form.submit()">
                    <option value="upcoming" <?= $time_filter === 'upcoming' ? 'selected' : '' ?>>Upcoming Events</option>
                    <option value="all" <?= $time_filter === 'all' ? 'selected' : '' ?>>All Past &amp; Upcoming</option>
                </select>
                <button type="submit" class="btn btn-dark px-3" title="Search"><i class="bi bi-funnel"></i></button>
                <?php if (!empty($search_query) || $category_filter !== 'all' || $time_filter !== 'upcoming'): ?>
                    <a href="index.php#events-roster" class="btn btn-outline-secondary" title="Reset Filters"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Category Pills Filter Bar -->
        <div class="d-flex flex-wrap gap-2 pt-2 border-top align-items-center" id="categoryPillsContainer">
            <span class="small text-muted fw-bold me-1">Quick Filter:</span>
            <button type="button" class="category-filter-badge <?= ($category_filter === 'all') ? 'active' : '' ?>" data-filter-cat="all">
                <i class="bi bi-grid-fill"></i> All Events
            </button>
            <button type="button" class="category-filter-badge <?= ($category_filter === 'Technical') ? 'active' : '' ?>" data-filter-cat="technical">
                <i class="bi bi-laptop"></i> Technical
            </button>
            <button type="button" class="category-filter-badge <?= ($category_filter === 'Cultural') ? 'active' : '' ?>" data-filter-cat="cultural">
                <i class="bi bi-palette"></i> Cultural
            </button>
            <button type="button" class="category-filter-badge <?= ($category_filter === 'Management') ? 'active' : '' ?>" data-filter-cat="management">
                <i class="bi bi-briefcase"></i> Management
            </button>
            <button type="button" class="category-filter-badge <?= ($category_filter === 'Workshop') ? 'active' : '' ?>" data-filter-cat="workshop">
                <i class="bi bi-tools"></i> Workshops
            </button>
            <button type="button" class="category-filter-badge <?= ($category_filter === 'Sports') ? 'active' : '' ?>" data-filter-cat="sports">
                <i class="bi bi-trophy"></i> Sports
            </button>
        </div>
    </div>
</div>

<!-- Events Catalog Grid -->
<main class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-0">Upcoming Campus Schedule</h3>
            <span class="text-muted small">Verified campus events hosted across college departments and student committees</span>
        </div>
        <span class="badge bg-light text-dark border px-3 py-2 fs-6" id="visibleCountBadge">
            Showing <?= count($events) ?> Events
        </span>
    </div>

    <!-- Live Search No Matches Container (Hidden by default) -->
    <div id="liveNoMatchAlert" class="card event-card text-center py-5 shadow-sm d-none mb-4">
        <div class="card-body">
            <div class="text-muted fs-1 mb-2"><i class="bi bi-search"></i></div>
            <h5 class="fw-bold text-dark">No Events Match Your Search</h5>
            <p class="text-muted small mb-3">No campus events match your current filter or keyword.</p>
            <button type="button" class="btn btn-primary rounded-pill px-4" id="clearLiveFiltersBtn">Reset Search</button>
        </div>
    </div>

    <?php if (empty($events)): ?>
        <div class="card event-card text-center py-5 shadow-sm">
            <div class="card-body">
                <div class="text-muted fs-1 mb-2"><i class="bi bi-calendar2-x"></i></div>
                <h5 class="fw-bold text-dark">No Events Match Your Query</h5>
                <p class="text-muted small mb-3">Try adjusting your search terms or view all categories.</p>
                <a href="index.php" class="btn btn-primary rounded-pill px-4">View Complete Schedule</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4" id="eventsGridRow">
            <?php foreach ($events as $evt): 
                $seats_left = max(0, $evt['max_seats'] - $evt['registered_count']);
                $is_full = ($seats_left <= 0);
                $is_registered = isset($student_registered_map[$evt['event_id']]);
                
                $timestamp = strtotime($evt['event_date']);
                $day_num   = date('d', $timestamp);
                $month_str = date('M', $timestamp);

                // Urgency Calculation
                $today_ts = strtotime(date('Y-m-d'));
                $event_ts = strtotime($evt['event_date']);
                $diff_days = (int)round(($event_ts - $today_ts) / 86400);

                $cat = strtolower($evt['category']);
                $cat_class = 'cat-def';
                if (str_contains($cat, 'tech')) $cat_class = 'cat-tech';
                elseif (str_contains($cat, 'cult')) $cat_class = 'cat-cult';
                elseif (str_contains($cat, 'mgmt') || str_contains($cat, 'manag')) $cat_class = 'cat-mgmt';
                elseif (str_contains($cat, 'work')) $cat_class = 'cat-work';
                elseif (str_contains($cat, 'spor')) $cat_class = 'cat-spor';

                $percent = ($evt['max_seats'] > 0) ? min(100, round(($evt['registered_count'] / $evt['max_seats']) * 100)) : 100;
                $bar_color = ($percent >= 90) ? 'bg-danger' : (($percent >= 65) ? 'bg-warning' : 'bg-success');
            ?>
            <div class="col-lg-4 col-md-6 event-card-col" 
                 data-title="<?= strtolower(escape($evt['event_name'])) ?>" 
                 data-desc="<?= strtolower(escape($evt['description'])) ?>" 
                 data-venue="<?= strtolower(escape($evt['venue'])) ?>" 
                 data-category="<?= strtolower(escape($evt['category'])) ?>">
                <div class="event-card">
                    <div class="event-card-header">
                        <div class="calendar-date-badge">
                            <span class="calendar-month"><?= $month_str ?></span>
                            <span class="calendar-day"><?= $day_num ?></span>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="category-pill <?= $cat_class ?>">
                                    <?= escape($evt['category']) ?>
                                </span>
                                <?php if ($diff_days === 0): ?>
                                    <span class="urgency-pill urgency-today"><i class="bi bi-broadcast"></i> Today</span>
                                <?php elseif ($diff_days === 1): ?>
                                    <span class="urgency-pill urgency-tomorrow"><i class="bi bi-clock-history"></i> Tomorrow</span>
                                <?php elseif ($diff_days > 1): ?>
                                    <span class="urgency-pill urgency-upcoming"><i class="bi bi-hourglass-split"></i> In <?= $diff_days ?> days</span>
                                <?php else: ?>
                                    <span class="urgency-pill urgency-past"><i class="bi bi-check2"></i> Concluded</span>
                                <?php endif; ?>
                            </div>
                            <h5 class="event-title mb-0"><?= escape($evt['event_name']) ?></h5>
                        </div>
                    </div>

                    <div class="event-card-body">
                        <p class="event-description">
                            <?= escape(mb_strimwidth($evt['description'], 0, 130, '...')) ?>
                        </p>

                        <div class="event-meta-row">
                            <div>
                                <i class="bi bi-clock text-secondary me-1"></i>
                                <span><?= format_time($evt['event_time']) ?></span>
                            </div>
                            <div class="text-truncate" title="<?= escape($evt['venue']) ?>">
                                <i class="bi bi-geo-alt text-danger me-1"></i>
                                <span><?= escape($evt['venue']) ?></span>
                            </div>
                        </div>

                        <!-- Capacity Bar -->
                        <div class="capacity-row">
                            <div class="d-flex justify-content-between align-items-center small mb-1">
                                <?php if ($is_full): ?>
                                    <span class="text-danger fw-bold"><i class="bi bi-slash-circle me-1"></i>Capacity Reached</span>
                                <?php else: ?>
                                    <span class="text-success fw-semibold"><i class="bi bi-people me-1"></i><?= $seats_left ?> seats open</span>
                                <?php endif; ?>
                                <span class="text-muted"><?= $evt['registered_count'] ?> / <?= $evt['max_seats'] ?></span>
                            </div>
                            <div class="capacity-progress">
                                <div class="progress-bar <?= $bar_color ?>" style="width: <?= $percent ?>%"></div>
                            </div>
                        </div>

                        <!-- Action Button -->
                        <div class="mt-auto">
                            <?php if ($is_registered): ?>
                                <a href="ticket.php?ticket=<?= urlencode($student_registered_map[$evt['event_id']]['ticket_number']) ?>" class="btn btn-success w-100 fw-semibold rounded-pill py-2 shadow-sm">
                                    <i class="bi bi-pass me-1"></i> View Evenza Pass
                                </a>
                            <?php elseif (($evt['status'] ?? 'upcoming') === 'cancelled'): ?>
                                <button class="btn btn-outline-danger w-100 fw-medium rounded-pill py-2" disabled>
                                    <i class="bi bi-x-circle me-1"></i> Event Cancelled
                                </button>
                            <?php elseif (($evt['status'] ?? 'upcoming') === 'completed'): ?>
                                <button class="btn btn-secondary w-100 fw-medium rounded-pill py-2" disabled>
                                    <i class="bi bi-check2-all me-1"></i> Event Concluded
                                </button>
                            <?php elseif ($is_full): ?>
                                <button class="btn btn-secondary w-100 fw-medium rounded-pill py-2" disabled>
                                    Registration Closed
                                </button>
                            <?php else: ?>
                                <a href="event_register.php?event_id=<?= (int)$evt['event_id'] ?>" class="btn btn-primary w-100 fw-semibold rounded-pill py-2 shadow-sm">
                                    Register for Event
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

<script>
// Client-Side Zero-Latency Live Filtering & Category Pills
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('eventLiveSearchInput');
    const cards = document.querySelectorAll('.event-card-col');
    const badge = document.getElementById('visibleCountBadge');
    const noMatch = document.getElementById('liveNoMatchAlert');
    const clearBtn = document.getElementById('clearLiveFiltersBtn');
    const pills = document.querySelectorAll('.category-filter-badge');

    let activeCategory = 'all';

    function applyLiveFilter() {
        const query = (searchInput ? searchInput.value : '').toLowerCase().trim();
        let visibleCount = 0;

        cards.forEach(card => {
            const title = card.getAttribute('data-title') || '';
            const desc  = card.getAttribute('data-desc') || '';
            const venue = card.getAttribute('data-venue') || '';
            const cat   = card.getAttribute('data-category') || '';

            const matchesQuery = (!query || title.includes(query) || desc.includes(query) || venue.includes(query) || cat.includes(query));
            const matchesCat = (activeCategory === 'all' || cat.includes(activeCategory));

            if (matchesQuery && matchesCat) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (badge) {
            badge.textContent = `Showing ${visibleCount} Events`;
        }

        if (noMatch) {
            if (visibleCount === 0 && cards.length > 0) {
                noMatch.classList.remove('d-none');
            } else {
                noMatch.classList.add('d-none');
            }
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', applyLiveFilter);
    }

    pills.forEach(pill => {
        pill.addEventListener('click', function() {
            pills.forEach(p => p.classList.remove('active'));
            this.classList.add('active');
            activeCategory = this.getAttribute('data-filter-cat') || 'all';
            applyLiveFilter();
        });
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            activeCategory = 'all';
            pills.forEach(p => p.classList.remove('active'));
            const allPill = document.querySelector('[data-filter-cat="all"]');
            if (allPill) allPill.classList.add('active');
            applyLiveFilter();
        });
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

