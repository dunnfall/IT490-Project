<?php
// profile.php

require_once "/home/website/IT490-Project/rabbitMQLib.inc";
$ini = parse_ini_file("/home/website/IT490-Project/testRabbitMQ.ini");
if (!$ini) {
    die("Error: Unable to load configuration file.");
}

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile</title>

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">

    <?php require(__DIR__ . "/../partials/nav.php"); ?>
</head>
<body class="bg-light">

    <div class="container mt-5">
        <div class="card shadow p-4">
            <h1 class="text-center">Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
            <p class="text-center lead">Your balance is: <strong>$<?php echo number_format($balance, 2); ?></strong></p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success text-center mt-3">
                Email sent successfully!
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger text-center mt-3">
                Error sending email: <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <!-- Portfolio Section -->
        <div class="card shadow mt-4 p-4">
            <h2 class="text-center">Your Portfolio</h2>
            <?php if (!empty($portfolio)): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped mt-3">
                        <thead class="thead-dark">
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
                </div>
            <?php else: ?>
                <p class="text-center text-muted">You have no holdings at the moment.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

</body>
</html>