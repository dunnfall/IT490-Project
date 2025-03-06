<?php
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";

$token = $_COOKIE['authToken'] ?? '';
if (!$token) {
    header("Location: login.html");
    exit();
}

// Verify token + get balance
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
$response = $client->send_request([
    "action" => "verifyAndGetBalance",
    "token"  => $token
]);

if (!isset($response["status"]) || $response["status"] !== "success") {
    header("Location: login.html");
    exit();
}

$username = $response["username"];
$balance  = $response["balance"];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Profile</title>
    <?php require(__DIR__ . "/../partials/nav.php"); ?>
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
    <p>Your balance is: $<?php echo number_format($balance, 2); ?></p>

    <!-- Optional success/error messages from send_notification.php -->
    <?php if (isset($_GET['success'])): ?>
        <p style="color: green;">Email sent successfully!</p>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <p style="color: red;">Error sending email: <?php echo htmlspecialchars($_GET['error']); ?></p>
    <?php endif; ?>

    <!-- HTML form posting to /API/send_notification.php -->
    <form action="../API/send_notification.php" method="post">
        <button type="submit" name="send_notification">Send Email Notification</button>
    </form>
</body>
</html>