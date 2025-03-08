<?php
// sell.php

$token = $_COOKIE['authToken'] ?? '';
if (!$token) {
    header("Location: login.html");
    exit();
}

require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";

$client   = new rabbitMQClient("testRabbitMQ.ini", "testServer");
$username = "";
$balance  = 0.00;
$message  = "";

// On GET, verify token + get balance
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $verifyReq = [
        "action" => "verifyAndGetBalance",
        "token"  => $token
    ];
    $verifyResp = $client->send_request($verifyReq);

    if (isset($verifyResp["status"]) && $verifyResp["status"] === "success") {
        $username = $verifyResp["username"];
        $balance  = (float)$verifyResp["balance"];
    } else {
        header("Location: login.html");
        exit();
    }
}

// On POST, place the sell order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = $_POST['username']   ?? '';
    $ticker     = $_POST['ticker']     ?? '';
    $quantity   = $_POST['quantity']   ?? 0;
    $orderType  = $_POST['orderType']  ?? 'MARKET';
    $limitPrice = $_POST['limitPrice'] ?? 0.0;

    $sellReq = [
        "action" => "sell_stock",
        "data"   => [
            "username"   => $username,
            "ticker"     => strtoupper(trim($ticker)),
            "quantity"   => (int)$quantity,
            "orderType"  => strtoupper($orderType),
            "limitPrice" => (float)$limitPrice
        ]
    ];
    $sellResp = $client->send_request($sellReq);

    if (isset($sellResp["status"]) && $sellResp["status"] === "success") {
        // If success, get updated balance from the consumer response
        $balance = isset($sellResp["newBalance"]) ? (float)$sellResp["newBalance"] : 0.0;
        $message = "Sell success: " . $sellResp["message"];

        // Redirect to send_notification.php with trade details
        $qs = http_build_query([
            'tradeType' => 'SELL',
            'ticker'    => $ticker,
            'quantity'  => $quantity,
            'newBal'    => $balance
        ]);
        header("Location: ../API/send_notification.php?$qs");
        exit();
    } else {
        $message = "Sell error: " . ($sellResp["message"] ?? "Unknown");
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

    <?php if (!empty($message)): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <!-- Sell form -->
    <form method="POST">
        <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">

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