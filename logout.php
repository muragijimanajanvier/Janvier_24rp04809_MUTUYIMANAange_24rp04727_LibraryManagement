<?php
// logout.php
require_once 'config.php';

// Store logout message before destroying session
$_SESSION['flash_message'] = 'You have been successfully logged out!';
$_SESSION['flash_type'] = 'info';

// Clear all session variables
$_SESSION = array();

// If it's desired to kill the session, also delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally, destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit();
?>