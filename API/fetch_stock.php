<?php
header('Content-Type: application/json');
require_once "/home/dmz/IT490-Project/rabbitMQLib.inc";
require_once "/home/dmz/IT490-Project/testRabbitMQ.ini";

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . "/fetch_stock_error.log");

// Ensure a ticker symbol is provided
if (!isset($_GET['ticker']) || empty($_GET['ticker'])) {
    echo json_encode(['error' => 'No ticker provided.']);
    exit();
}

$ticker = strtoupper(trim($_GET['ticker']));

// Function to fetch stock data from the API
function getStockFromAPI($ticker) {
    $apiKey = 'c445a9ff73msh1ba778fa2e6e77bp1681cbjsn1e7785aa5761';
    $apiUrl = "https://yahoo-finance15.p.rapidapi.com/api/v1/markets/stock/quotes?ticker=" . urlencode($ticker);

    error_log("Fetching stock data from API: " . $apiUrl);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10, // Timeout to prevent hanging requests
        CURLOPT_HTTPHEADER => [
            "x-rapidapi-host: yahoo-finance15.p.rapidapi.com",
            "x-rapidapi-key: $apiKey"
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) {
        error_log("API cURL Error: " . $err);
        return null;
    }

    return json_decode($response, true);
}

// Fetch stock data from the API
$stockData = getStockFromAPI($ticker);

if (!$stockData || !isset($stockData['body']) || empty($stockData['body'])) {
    error_log("Stock not found in API response.");
    echo json_encode(['error' => 'Ticker not found in the API response']);
    exit();
}

// Extract relevant stock data
$foundStock = $stockData['body'][0] ?? null;

if ($foundStock) {
    $company = $foundStock['displayName'] ?? 'N/A';
    $price = floatval($foundStock['regularMarketOpen'] ?? 0);
    $timestamp = date("Y-m-d H:i:s");
    $weekChange = $foundStock['fiftyTwoWeekChangePercent'] ?? null;
    $weekHigh = $foundStock['fiftyTwoWeekHigh'] ?? null;
    $weekLow = $foundStock['fiftyTwoWeekLow'] ?? null;
    $marketCap = $foundStock['marketCap'] ?? null;
    $region = $foundStock['region'] ?? 'N/A';
    $currency = $foundStock['currency'] ?? 'N/A';

    // Prepare stock data for RabbitMQ
    $dataToStore = [
        'action' => 'store_stock',
        'data' => [
            'ticker' => $ticker,
            'company' => $company,
            'price' => $price,
            'timestamp' => $timestamp,
            '52weekchangepercent' => $weekChange,
            '52weekhigh' => $weekHigh,
            '52weeklow' => $weekLow,
            'marketcap' => $marketCap,
            'region' => $region,
            'currency' => $currency
        ]
    ];

    // Send stock data to RabbitMQ (handled by the Rabbit VM)
    $client = new rabbitMQClient("/home/dmz/IT490-Project/testRabbitMQ.ini", "testServer");
    $response = $client->send_request($dataToStore);

    // Validate RabbitMQ response
    if (!$response || !isset($response['status']) || $response['status'] !== 'success') {
        error_log("Stock store failed via RabbitMQ. Response: " . print_r($response, true));
        echo json_encode(['error' => 'Failed to store stock in database.']);
        exit();
    }

    // Return success message to the frontend
    echo json_encode([
        'success' => 'Stock successfully added to database.',
        'ticker' => $ticker,
        'company' => $company,
        'price' => $price
    ]);
    exit();
}
