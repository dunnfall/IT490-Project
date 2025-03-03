<?php
// Secure session cookie settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
session_start();

// Check if the user is logged in by verifying the session token
if (!isset($_SESSION['username'])) {
    // If the user is not logged in, redirect to the login page
    header("Location: login.html");
    exit();
}

// Store the session username in a variable
$username = $_SESSION['username'];
?>

<?php
// Only if you're on HTTPS:
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);

session_start();

if (!isset($_SESSION['username'])) {
    // Not logged in:
    header("Location: login.html");
    exit();
}

// If here, user is logged in
$username = $_SESSION['username'];
?>
<?php
session_start();
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Home</title>
    <?php require(__DIR__ . "/../partials/nav.php"); ?>
</head>
<body>
<h1>Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>

<!-- This form posts to send_email.php -->
<form action="send_email.php" method="post">
    <button type="submit" name="send_notification">Send Email Notification</button>
</form>

<?php
// Show success/error messages (if redirected back here)
if (isset($_GET['success'])) {
    echo "<p style='color: green;'>Email sent successfully!</p>";
}
if (isset($_GET['error'])) {
    echo "<p style='color: red;'>Error sending email: " . htmlspecialchars($_GET['error']) . "</p>";
}
?>
</body>
</html>