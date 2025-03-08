<?php
// profile.php

require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";

$token = $_COOKIE['authToken'] ?? '';
if (!$token) {
    header("Location: login.html");
    exit();
}

// 1) Make a single request: "verifyAndGetBalanceAndPortfolio"
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
$response = $client->send_request([
    "action" => "verifyAndGetBalanceAndPortfolio",
    "token"  => $token
]);

if (!isset($response["status"]) || $response["status"] !== "success") {
    // Invalid token => go to login
    header("Location: login.html");
    exit();
}

// 2) Extract username, balance, portfolio
$username  = $response["username"];
$balance   = (float)$response["balance"];
$portfolio = $response["portfolio"]; // array of holdings
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

    <!-- 3) Display the portfolio -->
    <h2>Your Portfolio</h2>
    <?php if (!empty($portfolio)): ?>
        <table border="1" cellpadding="5" cellspacing="0">
            <thead>
                <tr>
                    <th>Ticker</th>
                    <th>Quantity</th>
                    <th>Purchase Price</th>
                    <th>Current Price</th>
                    <th>Purchase Date</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($portfolio as $row): ?>
                <?php
                    $ticker        = htmlspecialchars($row["ticker"]);
                    $quantity      = (int)$row["quantity"];
                    $purchasePrice = (float)$row["purchase_price"];
                    $currentPrice  = (float)$row["current_price"];
                    $purchaseDate  = htmlspecialchars($row["purchase_date"]);
                    $currentValue  = $quantity * $currentPrice;
                ?>
                <tr>
                    <td><?php echo $ticker; ?></td>
                    <td><?php echo $quantity; ?></td>
                    <td><?php echo "$" . number_format($purchasePrice, 2); ?></td>
                    <td><?php echo "$" . number_format($currentPrice, 2); ?></td>
                    <td><?php echo $purchaseDate; ?></td>
                    <td><?php echo "$" . number_format($currentValue, 2); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>You have no holdings at the moment.</p>
    <?php endif; ?>
</body>
</html>