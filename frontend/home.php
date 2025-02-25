<?php
session_start();

// Check if the user is logged in by verifying the session token
if (!isset($_SESSION['username'])) {
    // If the user is not logged in, redirect to the login page
    header("Location: login.html");
    exit();
}

// Debugging: Print session variables
echo '<pre>';
print_r($_SESSION);
echo '</pre>';

// Store the session and username in variables
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home</title>
    <style>
        .username {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 1.2em;
        }
    </style>
</head>
<body>
    <div class="username" id="username">
        <?php
        echo "Logged in as: " . htmlspecialchars($username);
        ?>
    </div>
    <h1>Welcome to the Home Page</h1>
    <!-- Your content here -->
</body>
</html>