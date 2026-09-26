<?php
require_once __DIR__ . '/../config/db.php';
require_admin_login();

$event_id_filter = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$status_filter   = trim($_GET['status'] ?? 'all');
$attendance_filter = trim($_GET['attendance'] ?? 'all');
$search_query    = trim($_GET['q'] ?? '');

$sql = "
    SELECT r.ticket_number, r.status, r.registered_at, r.checked_in, r.checked_in_at,
           s.full_name, s.usn, s.email, s.phone, s.course,
           e.event_name, e.category, e.event_date, e.event_time, e.venue,
           a.full_name AS verified_by_admin
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
$res = $stmt->get_result();

$event_slug = 'all_events';
if ($event_id_filter > 0) {
    $ev_stmt = $conn->prepare("SELECT event_name FROM events WHERE event_id = ?");
    $ev_stmt->bind_param("i", $event_id_filter);
    $ev_stmt->execute();
    $ev_data = $ev_stmt->get_result()->fetch_assoc();
    $ev_stmt->close();
    if ($ev_data) {
        $event_slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower($ev_data['event_name']));
    }
}

$filename = sprintf("Evenza_Attendees_%s_%s.csv", $event_slug, date('Ymd_His'));

// Set headers for download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// Output CSV Header
fputcsv($out, [
    'Sl. No',
    'Ticket Number',
    'Registration Status',
    'Student USN',
    'Full Name',
    'Course',
    'Email Address',
    'Phone Number',
    'Event Title',
    'Category',
    'Event Date',
    'Event Time',
    'Venue',
    'Registered Timestamp',
    'Attendance (Checked In)',
    'Check-in Timestamp',
    'Verified By'
], ",", '"', "\\");

$counter = 1;
while ($row = $res->fetch_assoc()) {
    fputcsv($out, [
        $counter++,
        $row['ticket_number'],
        strtoupper($row['status']),
        $row['usn'],
        $row['full_name'],
        $row['course'],
        $row['email'],
        $row['phone'],
        $row['event_name'],
        $row['category'],
        $row['event_date'],
        $row['event_time'],
        $row['venue'],
        $row['registered_at'],
        ($row['checked_in'] == 1 ? 'YES' : 'NO'),
        $row['checked_in_at'] ?? 'N/A',
        $row['verified_by_admin'] ?? 'N/A'
    ], ",", '"', "\\");
}

fclose($out);
exit;
