<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login if not logged in at all
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// If the session role isn't set, default to Staff for safety (never default to Admin)
if (!isset($_SESSION['role'])) {
    $_SESSION['role'] = 'Staff';
}
?>