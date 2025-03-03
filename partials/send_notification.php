<?php
// Secure session cookie settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);

session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

// Fetch the username
$username = $_SESSION['username'];

// 1) If the user's email is also stored in the session:
$userEmail = $_SESSION['email'] ?? ''; 
// or
// 2) If you need to retrieve it from your DB using $username, do so here:
// $userEmail = fetchEmailFromDB($username);

// Basic email details
$to      = $userEmail;
$subject = "Notification from MyApp";
$message = "Hello $username,\n\nThis is an automated notification.";
$headers = "From: no-reply@yourdomain.com\r\n";  // Make sure this is a valid from-address on your server

if (!empty($to)) {
    // Attempt to send the email
    if (mail($to, $subject, $message, $headers)) {
        // Redirect or display success
        header("Location: home.php?success=1");
        exit();
    } else {
        // Mail sending failed; handle error
        header("Location: home.php?error=mailfail");
        exit();
    }
} else {
    // No email address found; handle error
    header("Location: home.php?error=noemail");
    exit();
}
?>