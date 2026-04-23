<?php
// ============================================================
// logout.php — Logout Handler
// Destroys the PHP session completely and redirects to index
// ============================================================

// Always start session first before you can destroy it
session_start();

// Unset all session variables (clear all stored data)
$_SESSION = [];

// Destroy the session cookie in the browser
// This prevents the old session ID from being reused
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,   // Set expiry in the past — deletes the cookie
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Finally, destroy the session on the server
session_destroy();

// Redirect to home page
header('Location: index.php');
exit;
?>
