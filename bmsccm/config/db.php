<?php
// ==========================================================
// BMS College of Commerce & Management - Event Management System
// Database Configuration & Global Helper Functions
// ==========================================================

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

define('DB_SERVER', '127.0.0.1');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'bmsccm_events');
define('DB_PORT', 3306);

try {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    die("
    <div style='font-family: system-ui, sans-serif; max-width: 650px; margin: 50px auto; padding: 25px; border: 1px solid #f87171; border-radius: 10px; background-color: #fef2f2; color: #991b1b;'>
        <h2 style='margin-top: 0;'>Database Connection Error</h2>
        <p>Could not connect to MySQL database <strong>" . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') . "</strong>.</p>
        <p><strong>System Error:</strong> " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>
    </div>
    ");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function escape($data) {
    if ($data === null) return '';
    return htmlspecialchars((string)$data, ENT_QUOTES, 'UTF-8');
}

function set_flash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function get_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function render_flash() {
    $flash = get_flash();
    if ($flash) {
        $type = escape($flash['type']);
        $message = escape($flash['message']);
        $icon = 'bi-info-circle-fill';
        if ($type === 'success') $icon = 'bi-check-circle-fill';
        if ($type === 'danger')  $icon = 'bi-exclamation-triangle-fill';
        if ($type === 'warning') $icon = 'bi-exclamation-circle-fill';

        echo "
        <div class='alert alert-{$type} alert-dismissible fade show d-flex align-items-center shadow-sm my-3' role='alert'>
            <i class='bi {$icon} me-2 fs-5 flex-shrink-0'></i>
            <div>{$message}</div>
            <button type='button' class='btn-close ms-auto' data-bs-dismiss='alert' aria-label='Close'></button>
        </div>";
    }
}

function is_student_logged_in() {
    return isset($_SESSION['student_id']) && !empty($_SESSION['student_id']);
}

function is_admin_logged_in() {
    return isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']);
}

function require_student_login($redirect_target = null) {
    if (!is_student_logged_in()) {
        $target = $redirect_target ?: $_SERVER['REQUEST_URI'];
        set_flash('warning', 'Please login to access this service.');
        header('Location: login.php?redirect=' . urlencode($target));
        exit;
    }
}

function require_admin_login() {
    if (!is_admin_logged_in()) {
        set_flash('danger', 'Unauthorized access! Please login as an administrator.');
        header('Location: admin_login.php');
        exit;
    }
}

function format_date($date_str) {
    return date('d M Y', strtotime($date_str));
}

function format_time($time_str) {
    return date('h:i A', strtotime($time_str));
}

function generate_ticket_number($event_id, $student_id) {
    $datePart = date('Ymd');
    $randomPart = strtoupper(bin2hex(random_bytes(3)));
    return sprintf("BMS-%s-E%03d-S%03d-%s", $datePart, (int)$event_id, (int)$student_id, $randomPart);
}
?>