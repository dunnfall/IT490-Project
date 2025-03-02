<?php
header('Content-Type: application/json');
require_once "/home/website/IT490-Project/rabbitMQLib.inc";

// Prevent unwanted output
ob_start();
require_once "/home/website/IT490-Project/testRabbitMQ.ini";
ob_end_clean();

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . "/update_stock_error.log");

// Ensure ticker is provided
if (!isset($_GET['ticker']) || empty($_GET['ticker'])) {
    echo json_encode(['error' => 'No ticker provided.']);
    exit();
}

$ticker = strtoupper(trim($_GET['ticker']));
error_log("Updating stock price for: " . $ticker);

function updateStockPrice($ticker) {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

    // Fetch stock data from API
    $apiKey = 'c445a9ff73msh1ba778fa2e6e77bp1681cbjsn1e7785aa5761';
    $apiUrl = "https://yahoo-finance15.p.rapidapi.com/api/v1/markets/stock/quotes?ticker=" . urlencode($ticker);

    error_log("Fetching stock data from API: " . $apiUrl);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
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
        return ['error' => 'API request failed.'];
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("API JSON Decode Error: " . json_last_error_msg());
        return ['error' => 'Invalid API response.'];
    }

    if (!isset($data['body'][0]['symbol'])) {
        return ['error' => 'Stock not found in API.'];
    }

    $company = $data['body'][0]['displayName'] ?? 'N/A';
    $price = floatval($data['body'][0]['regularMarketOpen'] ?? 0);
    $timestamp = date("Y-m-d H:i:s");

    // Send update request to RabbitMQ
    $updateRequest = [
        'action' => 'store_stock',
        'data' => [
            'ticker' => $ticker,
            'company' => $company,
            'price' => $price,
            'timestamp' => $timestamp
        ]
    ];

    error_log("Sending stock update request to RabbitMQ...");
    $response = $client->send_request($updateRequest);

    return $response;
}

// Update stock in DB
$updateResponse = updateStockPrice($ticker);
echo json_encode($updateResponse);
exit();
