<?php
require_once "rabbitMQLib.inc";

// Connect to the MySQL database
$mydb = new mysqli("192.168.1.142", "testUser", "12345", "it490db");
if ($mydb->connect_error) {
    die("Database connection failed: " . $mydb->connect_error);
}

// Retrieve all stock tickers from the stocks table
$query = "SELECT ticker FROM stocks";
$result = $mydb->query($query);
if (!$result) {
    die("Error fetching stocks: " . $mydb->error);
}

$tickers = [];
while ($row = $result->fetch_assoc()) {
    $tickers[] = $row['ticker'];
}

if (empty($tickers)) {
    die("No tickers found in stocks table.");
}

// Initialize the DMZ RabbitMQ client
$dmzClient = new rabbitMQClient("/home/database/IT490-Project/RabbitDMZ.ini", "dmzServer");

// Prepare and send a single DMZ request with all tickers
$dmzRequest = [
    'type' => 'fetch_stock_batch', 
    'data' => ['tickers' => $tickers]
];
$dmzResponse = $dmzClient->send_request($dmzRequest);

if (!$dmzResponse || $dmzResponse['status'] !== 'success') {
    error_log("ERROR: Failed to fetch stock data from DMZ API in batch request.");
    die("Failed to fetch stock data from DMZ API.");
}

// Assume the response returns an associative array keyed by ticker symbol
$stockDataBatch = $dmzResponse['data'];

foreach ($stockDataBatch as $ticker => $stockData) {
    // Extract and sanitize data from the DMZ response for each ticker
    $company    = isset($stockData['company']) ? trim($stockData['company']) : "";
    $price      = isset($stockData['price']) ? floatval($stockData['price']) : 0.0;
    $timestamp  = date("Y-m-d H:i:s"); // Current server time
    $weekChange = isset($stockData['52weekchangepercent']) ? $stockData['52weekchangepercent'] : null;
    $weekHigh   = isset($stockData['52weekhigh']) ? $stockData['52weekhigh'] : null;
    $weekLow    = isset($stockData['52weeklow']) ? $stockData['52weeklow'] : null;
    $marketCap  = isset($stockData['marketcap']) ? $stockData['marketcap'] : null;
    $region     = isset($stockData['region']) ? $stockData['region'] : 'N/A';
    $currency   = isset($stockData['currency']) ? $stockData['currency'] : 'N/A';
    
    // Prepare an update statement to refresh the stock record
    $stmt = $mydb->prepare("
    UPDATE stocks
    SET company = ?,
        price = ?,
        timestamp = ?,
        52weekchangepercent = ?,
        52weekhigh = ?,
        52weeklow = ?,
        marketcap = ?,
        region = ?,
        currency = ?
    WHERE ticker = ?
");
    if (!$stmt) {
        error_log("Prepare failed for ticker: $ticker. Error: " . $mydb->error);
        continue;
    }
    
    // Bind parameters:
    $stmt->bind_param("sdsddddsss", 
    $company, 
    $price, 
    $timestamp, 
    $weekChange, 
    $weekHigh, 
    $weekLow, 
    $marketCap, 
    $region, 
    $currency, 
    $ticker
);
    
    if (!$stmt->execute()) {
        error_log("Execution failed for ticker: $ticker. Error: " . $stmt->error);
    }
    
    $stmt->close();
}

echo "Batch stock update completed.";

$mydb->close();
?>
