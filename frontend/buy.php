<?php
// buy.php

// 1) Check if the user has a valid auth token
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

// 2) On GET, verify token + get balance
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
        // Invalid token => redirect
        header("Location: login.html");
        exit();
    }
}

// 3) On POST, place the buy order
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve hidden username (preserved from GET)
    $username   = $_POST['username']   ?? '';
    $ticker     = $_POST['ticker']     ?? '';
    $quantity   = $_POST['quantity']   ?? 0;
    $orderType  = $_POST['orderType']  ?? 'MARKET';
    $limitPrice = $_POST['limitPrice'] ?? 0.0;

    // Build request for "buy_stock"
    $buyReq = [
        "action" => "buy_stock",
        "data"   => [
            "username"   => $username,
            "ticker"     => strtoupper(trim($ticker)),
            "quantity"   => (int)$quantity,
            "orderType"  => strtoupper($orderType),
            "limitPrice" => (float)$limitPrice
        ]
    ];
    $buyResp = $client->send_request($buyReq);

    if (isset($buyResp["status"]) && $buyResp["status"] === "success") {
        // 4) On success, get the updated balance (if returned by the consumer)
        $balance = isset($buyResp["newBalance"]) ? (float)$buyResp["newBalance"] : 0.0;
        $message = "Buy success: " . $buyResp["message"];

        // 5) Redirect to send_notification.php with trade details
        // We'll pass tradeType=BUY, ticker, quantity, newBal so the email can be customized
        $qs = http_build_query([
            'tradeType' => 'BUY',
            'ticker'    => $ticker,
            'quantity'  => $quantity,
            'newBal'    => $balance
        ]);
        header("Location: ../API/send_notification.php?$qs");
        exit();
    } else {
        $message = "Buy error: " . ($buyResp["message"] ?? "Unknown");
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

    <?php if (!empty($message)): ?>
        <p><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>

    <!-- Buy form -->
    <form method="POST">
        <!-- Preserve username across POST -->
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

        <button type="submit">Buy</button>
    </form>
</body>
</html>