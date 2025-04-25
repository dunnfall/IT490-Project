<?php
session_start();

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Delete authentication cookie
setcookie("authToken", "", time() - 3600, "/"); // Expire the cookie

// Redirect to login page
header("Location: login.html");
exit();
?>