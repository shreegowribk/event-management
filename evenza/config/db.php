<?php
// ==========================================================
// Evenza - College Event Management System
// Database Configuration & Global Helpers
// ==========================================================

// Production error logging configuration
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../error.log');
error_reporting(E_ALL);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

define('DB_SERVER', '127.0.0.1');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'evenza_db');
define('DB_PORT', 3306);

try {
    $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME, DB_PORT);
    $conn->set_charset('utf8mb4');
} catch (mysqli_sql_exception $e) {
    error_log("Evenza DB Connection Error: " . $e->getMessage());
    http_response_code(500);
    die("
    <div style='font-family: system-ui, sans-serif; max-width: 650px; margin: 50px auto; padding: 25px; border: 1px solid #f87171; border-radius: 10px; background-color: #fef2f2; color: #991b1b;'>
        <h2 style='margin-top: 0;'>Evenza Database Connection Error</h2>
        <p>Could not connect to MySQL database <strong>" . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') . "</strong>.</p>
        <p><strong>Fix:</strong> Verify that MySQL is active and database <code>evenza_db</code> exists.</p>
        <p style='font-size: 13px; color: #b91c1c; border-top: 1px solid #fca5a5; padding-top: 10px; margin-top: 20px;'>
            <strong>System Error Logged.</strong>
        </p>
    </div>
    ");
}

// Secure session cookies before session_start()
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_only_cookies', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ----------------------------------------------------------
// Security: CSRF Protection
// ----------------------------------------------------------
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

function verify_csrf_token() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (empty($submitted_token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $submitted_token);
}

// ----------------------------------------------------------
// Input Validation Helpers
// ----------------------------------------------------------
function is_valid_date($date_str, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date_str);
    return $d && $d->format($format) === $date_str;
}

function is_valid_time($time_str, $format = 'H:i') {
    $d = DateTime::createFromFormat($format, $time_str);
    if (!$d) {
        $d = DateTime::createFromFormat('H:i:s', $time_str);
        return $d !== false;
    }
    return $d && $d->format($format) === $time_str;
}

// ----------------------------------------------------------
// Custom 404 Handler
// ----------------------------------------------------------
function render_404($message = "The requested resource was not found.") {
    http_response_code(404);
    $error_message = $message;
    $file_404 = __DIR__ . '/../404.php';
    if (file_exists($file_404)) {
        include $file_404;
    } else {
        echo "<!DOCTYPE html><html><head><title>404 Not Found - Evenza</title></head><body style='font-family:sans-serif;padding:40px;text-align:center;'><h1>404 - Not Found</h1><p>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</p><p><a href='index.php'>Return to Evenza Home</a></p></body></html>";
    }
    exit;
}

// ----------------------------------------------------------
// Flash & Sanitization Helpers
// ----------------------------------------------------------
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
        $in_admin = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
        $login_url = $in_admin ? '../login.php' : 'login.php';
        header('Location: ' . $login_url . '?redirect=' . urlencode($target));
        exit;
    }
}

function require_admin_login() {
    if (!is_admin_logged_in()) {
        set_flash('danger', 'Unauthorized access! Please login as an administrator.');
        $in_admin = (strpos($_SERVER['PHP_SELF'], '/admin/') !== false);
        $admin_login_url = $in_admin ? 'admin_login.php' : 'admin/admin_login.php';
        header('Location: ' . $admin_login_url);
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
    return sprintf("EVZ-%s-E%03d-S%03d-%s", $datePart, (int)$event_id, (int)$student_id, $randomPart);
}