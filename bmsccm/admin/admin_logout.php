<?php
require_once __DIR__ . '/../config/db.php';

unset($_SESSION['admin_id']);
unset($_SESSION['admin_name']);
unset($_SESSION['admin_email']);

if (!is_student_logged_in()) {
    session_destroy();
    session_start();
}

set_flash('info', 'Administrator logged out safely.');
header('Location: admin_login.php');
exit;
?>