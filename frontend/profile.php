<?php
if (!ob_get_level()) ob_start();
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
// 2) Extract username, balance, portfolio
$username  = $response["username"];
$balance   = (float)$response["balance"];
$portfolio = $response["portfolio"]; // array of holdings


// Tell FPDF where to find font definitions
define('FPDF_FONTPATH', __DIR__ . '/../partials/fpdf181/font');
require_once __DIR__ . "/../partials/fpdf181/fpdf.php";

// PDF export via FPDF
if (isset($_GET['export']) && $_GET['export'] === 'pdf') {
    if (ob_get_level()) ob_clean();
    // Initialize PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    // Title
    $pdf->SetFont('Arial','B',16);
    $pdf->Cell(0,10,'Your Portfolio',0,1,'C');
    $pdf->Ln(5);
    // Column headers
    $pdf->SetFont('Arial','B',12);
    $widths = [25, 20, 30, 30, 50, 30];
    $headers = ['Ticker','Quantity','Buy Price','Curr Price','Date','Value'];
    foreach ($headers as $i => $col) {
        $pdf->Cell($widths[$i],7,$col,1,0,'C');
    }
    $pdf->Ln();
    // Data rows
    $pdf->SetFont('Arial','',12);
    $total = 0;
    foreach ($portfolio as $row) {
        $value = $row['quantity'] * $row['current_price'];
        $total += $value;
        $pdf->Cell($widths[0],6,$row['ticker'],1,0,'L');
        $pdf->Cell($widths[1],6,$row['quantity'],1,0,'C');
        $pdf->Cell($widths[2],6,number_format($row['purchase_price'],2),1,0,'R');
        $pdf->Cell($widths[3],6,number_format($row['current_price'],2),1,0,'R');
        $pdf->Cell($widths[4],6,$row['purchase_date'],1,0,'L');
        $pdf->Cell($widths[5],6,number_format($value,2),1,0,'R');
        $pdf->Ln();
    }
    // Total row
    $pdf->SetFont('Arial','B',12);
    // Empty cells before total label
    $pdf->Cell(array_sum(array_slice($widths, 0, 5)),7,'Total',1,0,'R');
    $pdf->Cell($widths[5],7,number_format($total,2),1,0,'R');
    // Output: use username in filename
    $filename = $username . 's_portfolio.pdf';
    $pdf->Output('D', $filename);
    exit;
}
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
        <div class="text-right mb-2">
            <a href="?export=pdf" class="btn btn-secondary">
                <i class="fas fa-file-pdf"></i> Export PDF
            </a>
        </div>
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
                        <?php 
                            $totalPortfolioValue = 0;
                            foreach ($portfolio as $row): 
                                $ticker        = htmlspecialchars($row["ticker"]);
                                $quantity      = (int)$row["quantity"];
                                $purchasePrice = (float)$row["purchase_price"];
                                $currentPrice  = (float)$row["current_price"];
                                $purchaseDate  = htmlspecialchars($row["purchase_date"]);
                                $currentValue  = $quantity * $currentPrice;
                                $totalPortfolioValue += $currentValue;
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
                <div class="mt-3 text-right">
                    <h5>Total Portfolio Value: <strong>$<?php echo number_format($totalPortfolioValue, 2); ?></strong></h5>
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
