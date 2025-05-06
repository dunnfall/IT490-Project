<?php
// buy.php

// 1) Check if the user has a valid auth token
$token = $_COOKIE['authToken'] ?? '';
if (!$token) {
    header("Location: login.html");
    exit();
}

require_once "/home/website/IT490-Project/rabbitMQLib.inc";
$ini = parse_ini_file("/home/website/IT490-Project/testRabbitMQ.ini");
if (!$ini) {
    die("Error: Unable to load configuration file.");
}

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
        header("Location: https://" . $_SERVER['HTTP_HOST'] . "/frontend/login.html");
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
        $qs = http_build_query([
            'tradeType' => 'BUY',
            'ticker'    => $ticker,
            'quantity'  => $quantity,
            'newBal'    => $balance
        ]);
        header("Location: https://" . $_SERVER['HTTP_HOST'] . "/API/send_notification.php?$qs");
        exit();
    } else {
        $message = "Buy error: " . ($buyResp["message"] ?? "Unknown");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buy Stocks</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    
    <?php require(__DIR__ . "/../partials/nav.php"); ?>
</head>
<body class="bg-light">

    <div class="container mt-5">
        <div class="card shadow p-4">
            <h2 class="text-center">Buy Stocks</h2>
            
            <p class="text-center"><strong>Logged in as:</strong> <?php echo htmlspecialchars($username); ?></p>
            <p class="text-center"><strong>Your Balance:</strong> $<?php echo number_format($balance, 2); ?></p>

            <?php if (!empty($message)): ?>
                <div class="alert <?php echo strpos($message, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> text-center">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">

                <div class="form-group">
                    <label for="ticker">Stock Ticker:</label>
                    <input type="text" id="ticker" name="ticker" class="form-control" placeholder="Enter stock symbol" required>
                </div>

                <div class="form-group">
                    <label for="quantity">Quantity:</label>
                    <input type="number" id="quantity" name="quantity" class="form-control" min="1" required>
                </div>

                <div class="form-group">
                    <label for="orderType">Order Type:</label>
                    <select id="orderType" name="orderType" class="form-control">
                        <option value="MARKET">Market Order</option>
                        <option value="LIMIT">Limit Order</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="limitPrice">Limit Price (For Limit Orders Only):</label>
                    <input type="number" step="0.01" id="limitPrice" name="limitPrice" class="form-control" placeholder="Enter limit price">
                </div>

                <button type="submit" class="btn btn-primary btn-block">Buy</button>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>