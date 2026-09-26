<?php
require_once __DIR__ . '/../config/db.php';
require_admin_login();

$admin_id = (int)$_SESSION['admin_id'];

// Handle AJAX Verification Request
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'verify') {
    header('Content-Type: application/json');
    $raw_input = trim($_POST['ticket_input'] ?? '');
    $selected_event_id = (int)($_POST['event_id'] ?? 0);

    // Robust extraction: handles full URLs, query parameters, custom payloads, and raw codes
    $ticket_clean = $raw_input;
    if (preg_match('/[?&]ticket=([A-Za-z0-9\-]+)/i', $raw_input, $matches)) {
        $ticket_clean = $matches[1];
    } elseif (preg_match('/EVENZA:PASS#([A-Za-z0-9\-]+)/i', $raw_input, $matches)) {
        $ticket_clean = $matches[1];
    } elseif (preg_match('/EVENTZA:([A-Za-z0-9\-]+)/i', $raw_input, $matches)) {
        $ticket_clean = $matches[1];
    } elseif (preg_match('/(EVZ\-[A-Za-z0-9\-]+)/i', $raw_input, $matches)) {
        $ticket_clean = $matches[1];
    }

    if (empty($ticket_clean)) {
        echo json_encode(['success' => false, 'message' => 'No usable ticket data or QR code provided.']);
        exit;
    }

    $sql = "
        SELECT r.registration_id, r.ticket_number, r.status, r.checked_in, r.checked_in_at, r.registered_at,
               s.student_id, s.full_name, s.usn, s.email, s.phone, s.course,
               e.event_id, e.event_name, e.category, e.event_date, e.event_time, e.venue,
               a.full_name AS verified_by_name
        FROM registrations r
        JOIN students s ON r.student_id = s.student_id
        JOIN events e ON r.event_id = e.event_id
        LEFT JOIN admins a ON r.checked_in_by = a.admin_id
        WHERE (r.ticket_number = ? OR s.usn = ?)
    ";

    if ($selected_event_id > 0) {
        $sql .= " AND r.event_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssi", $ticket_clean, $ticket_clean, $selected_event_id);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $ticket_clean, $ticket_clean);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $record = $res->fetch_assoc();
    $stmt->close();

    if (!$record) {
        // Helpful diagnostic: check if the ticket exists for a different event
        if ($selected_event_id > 0) {
            $stmt_diff = $conn->prepare("
                SELECT r.ticket_number, e.event_name 
                FROM registrations r 
                JOIN events e ON r.event_id = e.event_id 
                JOIN students s ON r.student_id = s.student_id 
                WHERE (r.ticket_number = ? OR s.usn = ?) LIMIT 1
            ");
            $stmt_diff->bind_param("ss", $ticket_clean, $ticket_clean);
            $stmt_diff->execute();
            $diff_res = $stmt_diff->get_result()->fetch_assoc();
            $stmt_diff->close();

            if ($diff_res) {
                echo json_encode([
                    'success' => false,
                    'status' => 'wrong_event',
                    'message' => 'Event Mismatch: This pass is valid for "' . $diff_res['event_name'] . '", not the currently selected filter.'
                ]);
                exit;
            }
        }

        echo json_encode(['success' => false, 'status' => 'not_found', 'message' => 'No registration record found for: ' . htmlspecialchars($ticket_clean)]);
        exit;
    }

    if ($record['status'] === 'cancelled') {
        echo json_encode([
            'success' => false,
            'status' => 'cancelled',
            'message' => 'REGISTRATION CANCELLED — Entry Denied!',
            'data' => $record
        ]);
        exit;
    }

    if ((int)$record['checked_in'] === 1) {
        echo json_encode([
            'success' => false,
            'status' => 'already_checked_in',
            'message' => 'DUPLICATE ENTRY DETECTED! Pass was already scanned.',
            'data' => $record,
            'checked_in_time' => date('d M Y, h:i:s A', strtotime($record['checked_in_at'])),
            'verified_by' => $record['verified_by_name'] ?? 'Admin'
        ]);
        exit;
    }

    // Mark as Checked In
    $stmt_upd = $conn->prepare("UPDATE registrations SET checked_in = 1, checked_in_at = NOW(), checked_in_by = ? WHERE registration_id = ?");
    $stmt_upd->bind_param("ii", $admin_id, $record['registration_id']);
    $stmt_upd->execute();
    $stmt_upd->close();

    $record['checked_in'] = 1;
    $record['checked_in_at'] = date('Y-m-d H:i:s');

    echo json_encode([
        'success' => true,
        'status' => 'confirmed',
        'message' => 'ENTRY GRANTED! Welcome, ' . htmlspecialchars($record['full_name']) . '.',
        'data' => $record,
        'check_in_time' => date('d M Y, h:i:s A')
    ]);
    exit;
}

// Handle Manual Check-in / Undo via standard POST
$search_result = null;
$search_error  = '';
$search_query  = trim($_GET['q'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action_reg_id = (int)$_POST['reg_id'];
    if ($_POST['action'] === 'manual_checkin') {
        $stmt_checkin = $conn->prepare("UPDATE registrations SET checked_in = 1, checked_in_at = NOW(), checked_in_by = ? WHERE registration_id = ?");
        $stmt_checkin->bind_param("ii", $admin_id, $action_reg_id);
        $stmt_checkin->execute();
        $stmt_checkin->close();
        set_flash('success', 'Student successfully marked as Checked In.');
    } elseif ($_POST['action'] === 'undo_checkin') {
        $stmt_undo = $conn->prepare("UPDATE registrations SET checked_in = 0, checked_in_at = NULL, checked_in_by = NULL WHERE registration_id = ?");
        $stmt_undo->bind_param("i", $action_reg_id);
        $stmt_undo->execute();
        $stmt_undo->close();
        set_flash('info', 'Check-in status reverted.');
    }
    header('Location: verify_ticket.php' . (!empty($search_query) ? '?q=' . urlencode($search_query) : ''));
    exit;
}

// Perform manual query if specified via GET
if (!empty($search_query)) {
    $clean_q = $search_query;
    if (preg_match('/EVENZA:PASS#([A-Za-z0-9\-]+)/', $search_query, $m)) {
        $clean_q = $m[1];
    }

    $stmt_find = $conn->prepare("
        SELECT r.registration_id, r.ticket_number, r.status, r.checked_in, r.checked_in_at, r.registered_at,
               s.student_id, s.full_name, s.usn, s.email, s.phone, s.course,
               e.event_id, e.event_name, e.category, e.event_date, e.event_time, e.venue,
               a.full_name AS verified_by_name
        FROM registrations r
        JOIN students s ON r.student_id = s.student_id
        JOIN events e ON r.event_id = e.event_id
        LEFT JOIN admins a ON r.checked_in_by = a.admin_id
        WHERE r.ticket_number = ? OR s.usn = ?
        LIMIT 1
    ");
    $stmt_find->bind_param("ss", $clean_q, $clean_q);
    $stmt_find->execute();
    $search_result = $stmt_find->get_result()->fetch_assoc();
    $stmt_find->close();

    if (!$search_result) {
        $search_error = "No registration found for ticket or USN: " . htmlspecialchars($clean_q);
    }
}

// Fetch active events for filter dropdown
$events_res = $conn->query("SELECT event_id, event_name, event_date FROM events ORDER BY event_date DESC");
$events = [];
while ($e = $events_res->fetch_assoc()) {
    $events[] = $e;
}

// Recent check-ins list (last 8)
$recent_checkins_res = $conn->query("
    SELECT r.registration_id, r.ticket_number, r.checked_in_at,
           s.full_name, s.usn, s.course,
           e.event_name,
           a.full_name AS admin_name
    FROM registrations r
    JOIN students s ON r.student_id = s.student_id
    JOIN events e ON r.event_id = e.event_id
    LEFT JOIN admins a ON r.checked_in_by = a.admin_id
    WHERE r.checked_in = 1
    ORDER BY r.checked_in_at DESC
    LIMIT 8
");
$recent_checkins = [];
while ($rc = $recent_checkins_res->fetch_assoc()) {
    $recent_checkins[] = $rc;
}

$page_title = "QR Scanner & Live Check-In";
include __DIR__ . '/admin_header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold text-dark mb-1">
                <i class="bi bi-qr-code-scan text-primary me-2"></i>Live Attendance &amp; QR Verification
            </h3>
            <p class="text-muted small mb-0">Scan student pass QR codes with your device camera or enter ticket number to grant entry.</p>
        </div>
        <div>
            <a href="view_registrations.php" class="btn btn-outline-dark rounded-pill px-3 fw-bold">
                <i class="bi bi-people me-1"></i> View Registrations Roster
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Left Column: Camera QR Scanner -->
        <div class="col-lg-6">
            <div class="card evenza-card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-camera me-2 text-primary"></i>Camera QR Scanner
                    </h5>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 small fw-semibold" id="scannerStatusBadge">
                        Ready
                    </span>
                </div>
                <div class="card-body p-3 text-center">
                    <div class="row g-2 mb-3 text-start">
                        <div class="col-sm-6">
                            <label for="scannerEventSelect" class="form-label small fw-bold text-secondary mb-1">Target Event:</label>
                            <select id="scannerEventSelect" class="form-select form-select-sm">
                                <option value="0">All Active Events</option>
                                <?php foreach ($events as $ev): ?>
                                    <option value="<?= (int)$ev['event_id'] ?>"><?= escape($ev['event_name']) ?> (<?= format_date($ev['event_date']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label for="cameraSourceSelect" class="form-label small fw-bold text-secondary mb-1">Camera Device:</label>
                            <select id="cameraSourceSelect" class="form-select form-select-sm">
                                <option value="auto">Auto-Detect (Any Camera)</option>
                            </select>
                        </div>
                    </div>

                    <div class="scanner-container-wrapper" style="position: relative;">
                        <div id="scannerLaser" class="scanner-laser d-none"></div>
                        <div id="reader" style="width: 100%; min-height: 280px; background: #1a1714; border-radius: 12px; overflow: hidden; position: relative;" class="d-flex align-items-center justify-content-center text-white-50">
                            <div id="reader-placeholder">
                                <i class="bi bi-qr-code-scan fs-1 d-block mb-2 text-warning"></i>
                                <p class="small mb-0">Click "Start Camera Scanner" or choose a QR image below</p>
                            </div>
                        </div>
                    </div>

                    <!-- Scanner Error / Diagnostic Banner -->
                    <div id="scannerAlertMsg" class="alert alert-warning small text-start d-none mt-3"></div>

                    <div class="d-flex justify-content-center gap-2 mt-3 flex-wrap">
                        <button type="button" id="startScannerBtn" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                            <i class="bi bi-play-circle me-1"></i> Start Camera Scanner
                        </button>
                        <button type="button" id="stopScannerBtn" class="btn btn-outline-danger rounded-pill px-4 fw-semibold d-none">
                            <i class="bi bi-stop-circle me-1"></i> Stop Camera
                        </button>
                        <label for="qrFileInput" class="btn btn-outline-secondary rounded-pill px-3 fw-semibold">
                            <i class="bi bi-image me-1"></i> Scan QR Image File
                        </label>
                        <input type="file" id="qrFileInput" accept="image/*" class="d-none">
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Manual Lookup & Real-Time Scan Result -->
        <div class="col-lg-6">
            <div class="card evenza-card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-keyboard me-2 text-primary"></i>Manual Ticket / USN Verification
                    </h5>
                </div>
                <div class="card-body p-3">
                    <form method="GET" action="verify_ticket.php" class="row g-2">
                        <div class="col-8">
                            <input type="text" name="q" id="manualInput" class="form-control" placeholder="e.g. EVZ-20260921-E001-S001-XXXX or USN" value="<?= escape($search_query) ?>" required autofocus>
                        </div>
                        <div class="col-4">
                            <button type="submit" class="btn btn-primary w-100 fw-bold">
                                <i class="bi bi-search me-1"></i> Verify
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Dynamic Scan Feedback Box -->
            <div id="scanFeedbackCard" class="card evenza-card border-0 shadow-sm" style="min-height: 240px;">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0">Verification Result</h5>
                    <span id="feedbackBadge" class="badge bg-secondary">Awaiting Scan</span>
                </div>
                <div class="card-body p-4" id="feedbackBody">
                    <?php if ($search_result): ?>
                        <div class="alert <?= ($search_result['checked_in'] == 1) ? 'alert-warning' : 'alert-success' ?> shadow-sm border-0 mb-3">
                            <h5 class="fw-bold mb-1">
                                <?php if ($search_result['checked_in'] == 1): ?>
                                    <i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Already Checked In
                                <?php else: ?>
                                    <i class="bi bi-check-circle-fill text-success me-2"></i>Valid Registration Found
                                <?php endif; ?>
                            </h5>
                            <div class="small">
                                <?php if ($search_result['checked_in'] == 1): ?>
                                    Entry granted at: <strong><?= date('d M Y, h:i A', strtotime($search_result['checked_in_at'])) ?></strong>
                                    (by <?= escape($search_result['verified_by_name'] ?? 'Admin') ?>)
                                <?php else: ?>
                                    Pass verified and confirmed for entry.
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h4 class="fw-bold text-dark mb-0"><?= escape($search_result['full_name']) ?></h4>
                                    <span class="text-primary fw-bold font-monospace"><?= escape($search_result['usn']) ?></span>
                                    &bull; <span class="badge bg-white text-dark border"><?= escape($search_result['course']) ?></span>
                                </div>
                                <span class="badge bg-dark text-warning font-monospace p-2"><?= escape($search_result['ticket_number']) ?></span>
                            </div>
                            <div class="small text-secondary">
                                <div><i class="bi bi-calendar-event me-1"></i> <strong><?= escape($search_result['event_name']) ?></strong></div>
                                <div><i class="bi bi-geo-alt me-1"></i> <?= escape($search_result['venue']) ?> (<?= format_date($search_result['event_date']) ?> at <?= format_time($search_result['event_time']) ?>)</div>
                                <div><i class="bi bi-telephone me-1"></i> <?= escape($search_result['phone']) ?> &bull; <?= escape($search_result['email']) ?></div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <?php if ($search_result['checked_in'] == 0): ?>
                                <form method="POST" action="verify_ticket.php<?= !empty($search_query) ? '?q=' . urlencode($search_query) : '' ?>" class="w-100">
                                    <input type="hidden" name="action" value="manual_checkin">
                                    <input type="hidden" name="reg_id" value="<?= (int)$search_result['registration_id'] ?>">
                                    <button type="submit" class="btn btn-success w-100 py-2 fw-bold shadow-sm rounded-pill">
                                        <i class="bi bi-check-lg me-1"></i> Confirm &amp; Grant Entry Now
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="verify_ticket.php<?= !empty($search_query) ? '?q=' . urlencode($search_query) : '' ?>" class="w-100">
                                    <input type="hidden" name="action" value="undo_checkin">
                                    <input type="hidden" name="reg_id" value="<?= (int)$search_result['registration_id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger w-100 py-2 fw-semibold rounded-pill">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> Revert Check-in Status
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php elseif (!empty($search_error)): ?>
                        <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center">
                            <i class="bi bi-x-octagon-fill fs-3 text-danger me-3"></i>
                            <div>
                                <h6 class="fw-bold mb-1">Pass Verification Failed</h6>
                                <div class="small"><?= escape($search_error) ?></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-shield-check fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            <h6 class="fw-bold">Ready to Check-in Attendees</h6>
                            <p class="small mb-0">Use the camera scanner on the left or search manually by Ticket Number or USN above.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Section: Recent Check-in Activity -->
    <div class="card evenza-card border-0 shadow-sm">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0">
                <i class="bi bi-clock-history text-primary me-2"></i>Recent Check-in Logs
            </h5>
            <span class="badge bg-light text-dark border"><?= count($recent_checkins) ?> Logged</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($recent_checkins)): ?>
                <div class="p-4 text-center text-muted small">No check-in entries logged yet today.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-custom mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Student Details</th>
                                <th>USN &amp; Course</th>
                                <th>Event</th>
                                <th>Ticket #</th>
                                <th>Check-in Time</th>
                                <th>Verified By</th>
                            </tr>
                        </thead>
                        <tbody id="recentLogsTableBody">
                            <?php foreach ($recent_checkins as $log): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= escape($log['full_name']) ?></td>
                                    <td>
                                        <code class="fw-bold text-primary"><?= escape($log['usn']) ?></code>
                                        <span class="badge bg-light text-secondary border ms-1"><?= escape($log['course']) ?></span>
                                    </td>
                                    <td><?= escape($log['event_name']) ?></td>
                                    <td class="font-monospace small"><?= escape($log['ticket_number']) ?></td>
                                    <td class="small text-muted">
                                        <i class="bi bi-check2-circle text-success me-1"></i>
                                        <?= date('h:i:s A', strtotime($log['checked_in_at'])) ?>
                                    </td>
                                    <td class="small text-secondary"><?= escape($log['admin_name'] ?? 'System') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Include HTML5-QRCode Library via CDN -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

<script>
let html5QrCode = null;
let isScanning = false;
let lastScannedCode = null;
let scanCooldown = false;

const startBtn = document.getElementById('startScannerBtn');
const stopBtn = document.getElementById('stopScannerBtn');
const placeholder = document.getElementById('reader-placeholder');
const statusBadge = document.getElementById('scannerStatusBadge');
const feedbackCard = document.getElementById('scanFeedbackCard');
const feedbackBadge = document.getElementById('feedbackBadge');
const feedbackBody = document.getElementById('feedbackBody');
const scannerLaser = document.getElementById('scannerLaser');
const cameraSelect = document.getElementById('cameraSourceSelect');
const scannerAlertMsg = document.getElementById('scannerAlertMsg');
const qrFileInput = document.getElementById('qrFileInput');

// Enumerate available cameras on page load
if (typeof Html5Qrcode !== 'undefined') {
    Html5Qrcode.getCameras().then(devices => {
        if (devices && devices.length > 0) {
            cameraSelect.innerHTML = '<option value="auto">Auto-Detect Camera</option>';
            devices.forEach((device, index) => {
                const opt = document.createElement('option');
                opt.value = device.id;
                opt.textContent = device.label || `Camera ${index + 1}`;
                cameraSelect.appendChild(opt);
            });
        }
    }).catch(err => {
        // Ignored on load; permissions requested when user clicks Start
    });
}

// Web Audio API Sound Synthesizer for Turnstile Beeps
function playAudioFeedback(type) {
    try {
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        if (!AudioContext) return;
        const ctx = new AudioContext();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);

        if (type === 'success') {
            // Pleasant turnstile dual chime
            osc.type = 'sine';
            osc.frequency.setValueAtTime(784, ctx.currentTime); // G5
            osc.frequency.setValueAtTime(1046.5, ctx.currentTime + 0.1); // C6
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.35);
        } else {
            // Low buzz for duplicate / invalid entry
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(220, ctx.currentTime); // A3
            osc.frequency.setValueAtTime(160, ctx.currentTime + 0.12);
            gain.gain.setValueAtTime(0.35, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.4);
        }
    } catch(e) {
        // Audio synthesis fallback
    }
}

function onScannerRunning() {
    isScanning = true;
    startBtn.classList.add('d-none');
    stopBtn.classList.remove('d-none');
    if (scannerLaser) scannerLaser.classList.remove('d-none');
    statusBadge.className = 'badge bg-success text-white';
    statusBadge.textContent = 'Scanning Active';
    if (scannerAlertMsg) scannerAlertMsg.classList.add('d-none');
}

async function startCameraScanner() {
    if (!html5QrCode) {
        html5QrCode = new Html5Qrcode("reader");
    }

    placeholder.classList.add('d-none');
    if (scannerAlertMsg) scannerAlertMsg.classList.add('d-none');
    statusBadge.className = 'badge bg-warning text-dark';
    statusBadge.textContent = 'Camera Starting...';

    const config = { fps: 10, qrbox: { width: 250, height: 250 } };
    const selectedSource = cameraSelect ? cameraSelect.value : 'auto';

    // Step 1: If user chose a specific camera ID, use it directly
    if (selectedSource && selectedSource !== 'auto') {
        try {
            await html5QrCode.start(selectedSource, config, onScanSuccess, onScanFailure);
            onScannerRunning();
            return;
        } catch(e) {
            console.warn("Specific camera start failed, falling back to auto...", e);
        }
    }

    // Step 2: Try environment (rear) camera
    try {
        await html5QrCode.start({ facingMode: "environment" }, config, onScanSuccess, onScanFailure);
        onScannerRunning();
        return;
    } catch (errEnv) {
        console.warn("Environment camera failed (typical on macOS/laptops), trying user/front camera...", errEnv);
    }

    // Step 3: Try user (front / FaceTime HD) camera
    try {
        await html5QrCode.start({ facingMode: "user" }, config, onScanSuccess, onScanFailure);
        onScannerRunning();
        return;
    } catch (errUser) {
        console.warn("User camera failed, attempting camera device enumeration...", errUser);
    }

    // Step 4: Try enumerating devices and picking first available camera ID
    try {
        const devices = await Html5Qrcode.getCameras();
        if (devices && devices.length > 0) {
            await html5QrCode.start(devices[0].id, config, onScanSuccess, onScanFailure);
            onScannerRunning();
            return;
        } else {
            throw new Error("No camera video devices detected on this computer.");
        }
    } catch (errFinal) {
        console.error("All camera initialization attempts failed:", errFinal);
        statusBadge.className = 'badge bg-danger text-white';
        statusBadge.textContent = 'Camera Blocked / Not Found';
        placeholder.classList.remove('d-none');
        if (scannerLaser) scannerLaser.classList.add('d-none');

        if (scannerAlertMsg) {
            scannerAlertMsg.classList.remove('d-none');
            scannerAlertMsg.innerHTML = `
                <div class="fw-bold mb-1 text-danger">
                    <i class="bi bi-camera-video-off me-1"></i> Camera Could Not Be Opened
                </div>
                <div class="small">
                    <strong>Cause:</strong> ${errFinal.message || errFinal}<br>
                    <div class="mt-2 fw-semibold">Troubleshooting Steps:</div>
                    <ul class="mb-2 mt-1 ps-3">
                        <li>Click the <strong>Tune / Lock icon</strong> in your browser address bar &rarr; ensure <strong>Camera is set to Allow</strong>.</li>
                        <li>Check that no other app (FaceTime, Photo Booth, Zoom, Teams) is currently using your webcam.</li>
                        <li>Alternatively, click <strong>"Scan QR Image File"</strong> below to verify passes from a screenshot, or use the <strong>Manual Ticket Verification</strong> input on the right!</li>
                    </ul>
                </div>
            `;
        }
    }
}

startBtn.addEventListener('click', startCameraScanner);

stopBtn.addEventListener('click', () => {
    if (html5QrCode && isScanning) {
        html5QrCode.stop().then(() => {
            isScanning = false;
            stopBtn.classList.add('d-none');
            startBtn.classList.remove('d-none');
            placeholder.classList.remove('d-none');
            if (scannerLaser) scannerLaser.classList.add('d-none');
            statusBadge.className = 'badge bg-secondary';
            statusBadge.textContent = 'Stopped';
        });
    }
});

// Scan from QR Image File / Screenshot
if (qrFileInput) {
    qrFileInput.addEventListener('change', async (e) => {
        if (!e.target.files || e.target.files.length === 0) return;
        const file = e.target.files[0];

        statusBadge.className = 'badge bg-info text-dark';
        statusBadge.textContent = 'Analyzing Image...';

        try {
            if (!html5QrCode) {
                html5QrCode = new Html5Qrcode("reader");
            }
            const decodedText = await html5QrCode.scanFile(file, true);
            statusBadge.className = 'badge bg-success text-white';
            statusBadge.textContent = 'QR Code Detected';
            onScanSuccess(decodedText, null);
        } catch (err) {
            statusBadge.className = 'badge bg-warning text-dark';
            statusBadge.textContent = 'No QR Found';
            renderFeedback({
                success: false,
                status: 'not_found',
                message: 'No readable QR code found in the selected image file. Please upload a clear pass image or enter the ticket code manually.'
            });
        }
        qrFileInput.value = '';
    });
}

function onScanSuccess(decodedText, decodedResult) {
    if (scanCooldown) return;
    scanCooldown = true;
    setTimeout(() => { scanCooldown = false; }, 2500);

    lastScannedCode = decodedText;
    statusBadge.className = 'badge bg-info text-dark';
    statusBadge.textContent = 'Processing...';

    const eventId = document.getElementById('scannerEventSelect').value;

    const formData = new FormData();
    formData.append('ajax_action', 'verify');
    formData.append('ticket_input', decodedText);
    formData.append('event_id', eventId);

    fetch('verify_ticket.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        renderFeedback(data);
        statusBadge.className = 'badge bg-success text-white';
        statusBadge.textContent = 'Scanning Active';
    })
    .catch(err => {
        statusBadge.className = 'badge bg-danger text-white';
        statusBadge.textContent = 'Network Error';
    });
}

function onScanFailure(error) {
    // Normal noise during frame analysis
}

function renderFeedback(res) {
    if (res.success) {
        playAudioFeedback('success');
        feedbackBadge.className = 'badge bg-success';
        feedbackBadge.textContent = 'ENTRY GRANTED';

        feedbackBody.innerHTML = `
            <div class="alert alert-success shadow-sm border-0 mb-3">
                <h5 class="fw-bold mb-1"><i class="bi bi-check-circle-fill me-2"></i>${res.message}</h5>
                <div class="small">Check-in recorded at: <strong>${res.check_in_time}</strong></div>
            </div>
            <div class="p-3 bg-light rounded-3 border mb-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h4 class="fw-bold text-dark mb-0">${res.data.full_name}</h4>
                        <span class="text-primary fw-bold font-monospace">${res.data.usn}</span>
                        &bull; <span class="badge bg-white text-dark border">${res.data.course}</span>
                    </div>
                    <span class="badge bg-dark text-warning font-monospace p-2">${res.data.ticket_number}</span>
                </div>
                <div class="small text-secondary">
                    <div><i class="bi bi-calendar-event me-1"></i> <strong>${res.data.event_name}</strong></div>
                    <div><i class="bi bi-geo-alt me-1"></i> ${res.data.venue}</div>
                </div>
            </div>
        `;
    } else {
        playAudioFeedback('error');
        if (res.status === 'already_checked_in') {
            feedbackBadge.className = 'badge bg-warning text-dark';
            feedbackBadge.textContent = 'ALREADY CHECKED IN';
            feedbackBody.innerHTML = `
                <div class="alert alert-warning shadow-sm border-0 mb-3">
                    <h5 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>${res.message}</h5>
                    <div class="small">Previously scanned on: <strong>${res.checked_in_time}</strong> by <strong>${res.verified_by}</strong></div>
                </div>
                <div class="p-3 bg-light rounded-3 border">
                    <h5 class="fw-bold text-dark mb-0">${res.data.full_name} (${res.data.usn})</h5>
                    <div class="text-muted small">${res.data.event_name}</div>
                </div>
            `;
        } else {
            feedbackBadge.className = 'badge bg-danger';
            feedbackBadge.textContent = 'DENIED';
            feedbackBody.innerHTML = `
                <div class="alert alert-danger shadow-sm border-0">
                    <h5 class="fw-bold mb-1"><i class="bi bi-x-circle-fill me-2"></i>Verification Failed</h5>
                    <div class="small">${res.message}</div>
                </div>
            `;
        }
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
