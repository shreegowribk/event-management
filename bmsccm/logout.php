<?php
require_once __DIR__ . '/config/db.php';

unset($_SESSION['student_id']);
unset($_SESSION['student_name']);
unset($_SESSION['student_usn']);
unset($_SESSION['student_email']);
unset($_SESSION['student_course']);

if (!is_admin_logged_in()) {
    session_destroy();
    session_start();
}

set_flash('info', 'You have been successfully logged out.');
header('Location: login.php');
exit;
?>