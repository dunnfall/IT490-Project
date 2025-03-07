<?php
// sell.php

// 1) Check for auth token
$token = $_COOKIE['authToken'] ?? '';
if (!$token) {
    header("Location: login.html");
    exit();
}

// 2) Load RabbitMQ client/config
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";
// Same change here, using testRabbitMQ.ini and testServer

// 3) Verify token + get balance
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
$verifyRequest = [
    "action" => "verifyAndGetBalance",
    "token"  => $token
];
$verifyResponse = $client->send_request($verifyRequest);

if (!isset($verifyResponse["status"]) || $verifyResponse["status"] !== "success") {
    header("Location: login.html");
    exit();
}

$username = $verifyResponse["username"];
$balance  = $verifyResponse["balance"];

// 4) Check if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticker     = $_POST['ticker']     ?? '';
    $quantity   = $_POST['quantity']   ?? '';
    $orderType  = $_POST['orderType']  ?? 'MARKET';
    $limitPrice = $_POST['limitPrice'] ?? 0;

    if (!$ticker || !$quantity) {
        header("Location: sell.php?error=" . urlencode("Please provide a ticker and quantity."));
        exit();
    }

    // 5) Send RabbitMQ request to "sell_stock"
    $sellRequest = [
        "action" => "sell_stock",
        "data"   => [
            "username"   => $username,
            "ticker"     => strtoupper(trim($ticker)),
            "quantity"   => (int)$quantity,
            "orderType"  => strtoupper($orderType),
            "limitPrice" => (float)$limitPrice
        ]
    ];
    $sellResponse = $client->send_request($sellRequest);

    // 6) Handle response
    if (isset($sellResponse["status"]) && $sellResponse["status"] === "success") {
        header("Location: sell.php?success=" . urlencode($sellResponse["message"]));
        exit();
    } else {
        $errorMsg = $sellResponse["message"] ?? "Failed to sell stock.";
        header("Location: sell.php?error=" . urlencode($errorMsg));
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Sell Stocks</title>
    <?php require(__DIR__ . "/../partials/nav.php"); ?>
</head>
<body>
    <h1>Sell Stocks</h1>

    <p>Logged in as: <strong><?php echo htmlspecialchars($username); ?></strong></p>
    <p>Your current balance: $<?php echo number_format($balance, 2); ?></p>

    <?php if (isset($_GET['success'])): ?>
        <p style="color: green;"><?php echo htmlspecialchars($_GET['success']); ?></p>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <p style="color: red;"><?php echo htmlspecialchars($_GET['error']); ?></p>
    <?php endif; ?>

    <form action="sell.php" method="POST">
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

        <button type="submit">Sell</button>
    </form>
</body>
</html>