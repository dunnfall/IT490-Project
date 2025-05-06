<?php
// sell.php

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
        header("Location: https://" . $_SERVER['HTTP_HOST'] . "/frontend/login.html");
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
        header("Location: https://" . $_SERVER['HTTP_HOST'] . "/API/send_notification.php?$qs");
        exit();
    } else {
        $message = "Sell error: " . ($sellResp["message"] ?? "Unknown");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sell Stocks</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

    <?php require(__DIR__ . "/../partials/nav.php"); ?>
</head>
<body class="bg-light">

    <div class="container mt-5">
        <div class="card shadow p-4">
            <h1 class="text-center">Sell Stocks</h1>
            <p class="text-center lead">Logged in as: <strong><?php echo htmlspecialchars($username); ?></strong></p>
            <p class="text-center">Your current balance: <strong>$<?php echo number_format($balance, 2); ?></strong></p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert <?php echo strpos($message, 'success') !== false ? 'alert-success' : 'alert-danger'; ?> text-center mt-3">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Sell Form -->
        <div class="card shadow mt-4 p-4">
            <h2 class="text-center">Place a Sell Order</h2>
            <form method="POST" class="mt-3">
                <!-- Preserve username across POST -->
                <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">

                <div class="form-group">
                    <label for="ticker">Stock Ticker:</label>
                    <input type="text" id="ticker" name="ticker" class="form-control" required>
                </div>

                <div class="form-group">
                    <label for="quantity">Quantity:</label>
                    <input type="number" id="quantity" name="quantity" class="form-control" min="1" required>
                </div>

                <div class="form-group">
                    <label for="orderType">Order Type:</label>
                    <select id="orderType" name="orderType" class="form-control">
                        <option value="MARKET">Market</option>
                        <option value="LIMIT">Limit</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="limitPrice">Limit Price (if Limit Order):</label>
                    <input type="number" step="0.01" id="limitPrice" name="limitPrice" class="form-control" placeholder="0.00">
                </div>

                <button type="submit" class="btn btn-danger btn-block">Sell</button>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>