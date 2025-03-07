<?php
// buy.php

// 1) Check for an auth token in the cookie
$token = $_COOKIE['authToken'] ?? '';
if (!$token) {
    // If no token, redirect to login
    header("Location: login.html");
    exit();
}

// 2) Load RabbitMQ client/config
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini"; 
// IMPORTANT: Now using testRabbitMQ.ini instead of testRabbitMQ_response.ini

// 3) Verify the token + get user balance
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
$verifyRequest = [
    "action" => "verifyAndGetBalance",
    "token"  => $token
];
$verifyResponse = $client->send_request($verifyRequest);

if (!isset($verifyResponse["status"]) || $verifyResponse["status"] !== "success") {
    // Invalid token => redirect to login
    header("Location: login.html");
    exit();
}

// 4) We have the username and balance
$username = $verifyResponse["username"];
$balance  = $verifyResponse["balance"];

// 5) Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Gather form inputs
    $ticker     = $_POST['ticker']     ?? '';
    $quantity   = $_POST['quantity']   ?? '';
    $orderType  = $_POST['orderType']  ?? 'MARKET';
    $limitPrice = $_POST['limitPrice'] ?? 0;

    // Basic validation
    if (!$ticker || !$quantity) {
        header("Location: buy.php?error=" . urlencode("Please provide a ticker and quantity."));
        exit();
    }

    // 6) Build and send a RabbitMQ request to "buy_stock"
    $buyRequest = [
        "action" => "buy_stock",
        "data"   => [
            "username"   => $username,
            "ticker"     => strtoupper(trim($ticker)),
            "quantity"   => (int)$quantity,
            "orderType"  => strtoupper($orderType), // 'MARKET' or 'LIMIT'
            "limitPrice" => (float)$limitPrice
        ]
    ];
    $buyResponse = $client->send_request($buyRequest);

    // 7) Handle the response
    if (isset($buyResponse["status"]) && $buyResponse["status"] === "success") {
        header("Location: buy.php?success=" . urlencode($buyResponse["message"]));
        exit();
    } else {
        $errorMsg = $buyResponse["message"] ?? "Failed to buy stock.";
        header("Location: buy.php?error=" . urlencode($errorMsg));
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Buy Stocks</title>
    <?php require(__DIR__ . "/../partials/nav.php"); ?>
</head>
<body>
    <h1>Buy Stocks</h1>

    <p>Logged in as: <strong><?php echo htmlspecialchars($username); ?></strong></p>
    <p>Your current balance: $<?php echo number_format($balance, 2); ?></p>

    <?php if (isset($_GET['success'])): ?>
        <p style="color: green;"><?php echo htmlspecialchars($_GET['success']); ?></p>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <p style="color: red;"><?php echo htmlspecialchars($_GET['error']); ?></p>
    <?php endif; ?>

    <form action="buy.php" method="POST">
        <label for="ticker">Ticker:</label>
        <input type="text" id="ticker" name="ticker" required>

        <br><br>

        <label for="quantity">Quantity:</label>
        <input type="number" id="quantity" name="quantity" min="1" required>

        <br><br>

        <label for="orderType">Order Type:</label>
        <select id="orderType" name="orderType">
            <option value="MARKET">Market</option>
            <option value="LIMIT">Limit</option>
        </select>

        <br><br>

        <label for="limitPrice">Limit Price (if Limit Order):</label>
        <input type="number" step="0.01" id="limitPrice" name="limitPrice" placeholder="0.00">

        <br><br>

        <button type="submit">Buy</button>
    </form>
</body>
</html>