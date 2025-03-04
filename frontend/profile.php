<?php
// Secure session cookie settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
session_start();

require_once "/home/website/IT490-Project/rabbitMQLib.inc";

$client = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ.ini", "testServer");

// Check if the user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

// Store the session username
$username = $_SESSION['username'];

// If here, user is logged in
$username = $_SESSION['username'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Profile</title>
    <?php require(__DIR__ . "/../partials/nav.php"); ?>
    <script>
        async function fetchBalance(retries = 3) {
            try {
                let response = await fetch("../API/get_balance.php");
                let data = await response.json();

                if (data.error) {
                    if (retries > 0) {
                        console.warn("Retrying fetchBalance... Remaining retries:", retries);
                        setTimeout(() => fetchBalance(retries - 1), 2000); // Retry after 2 seconds
                        return;
                    }
                    document.getElementById("balance").textContent = "Error: " + data.error;
                } else {
                    document.getElementById("balance").textContent = "$" + data.balance;
                }
            } catch (error) {
                console.error("Fetch Error:", error);
                document.getElementById("balance").textContent = "Error fetching balance";
            }
        }

        // Fetch balance on page load
        document.addEventListener("DOMContentLoaded", () => fetchBalance(3));
    </script>
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
    <p><strong>Your Balance:</strong> <span id="balance">Loading...</span></p>

    <form action="send_email.php" method="post">
        <button type="submit" name="send_notification">Send Email Notification</button>
    </form>
</body>
</html>