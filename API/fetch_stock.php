<?php
header('Content-Type: application/json');
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";

error_reporting(E_ALL);
ini_set('display_errors', 1);

function getStockDataFromDB($ticker) {
    $client = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ.ini", "testServer");

    $request = [
        'action' => 'get_stock',
        'data' => ['ticker' => $ticker]
    ];

    error_log("Sending request to RabbitMQ to fetch stock data from DB: " . print_r($request, true));

    $response = $client->send_request($request);

    error_log("Received response from RabbitMQ: " . print_r($response, true));

    return $response;
}

function getStockDataFromAPI($ticker) {
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
        error_log("cURL Error: " . $err);
        return null;
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        return null;
    }

    return $data;
}

if (isset($_GET['ticker'])) {
    $ticker = strtoupper(trim($_GET['ticker']));
    error_log("Processing stock request for ticker: " . $ticker);

    // check the database for existing stock data
    $storedStock = getStockDataFromDB($ticker);

    if ($storedStock && isset($storedStock['status']) && $storedStock['status'] === 'success') {
        error_log("Stock found in database: " . print_r($storedStock, true));
        echo json_encode($storedStock['data']);
        exit();
    } else {
        error_log("Stock NOT found in database, fetching from API...");
    }

    //If not found fetch from API
    $stock_data = getStockDataFromAPI($ticker);

    if (!$stock_data || !isset($stock_data['body']) || empty($stock_data['body'])) {
        error_log("Stock not found in API response.");
        echo json_encode(['error' => 'Ticker not found in the API response']);
        exit();
    }

    $foundStock = null;
    foreach ($stock_data['body'] as $stock) {
        if (isset($stock['symbol']) && strcasecmp($stock['symbol'], $ticker) === 0) {
            $foundStock = $stock;
            break;
        }
    }

    if ($foundStock) {
        $company = $foundStock['displayName'] ?? 'N/A';
        $price = $foundStock['regularMarketOpen'] ?? 0;
        $timestamp = time();

        error_log("Stock data fetched from API: " . print_r($foundStock, true));

        $dataToStore = [
            'action' => 'store_stock',
            'data' => [
                'ticker' => $ticker,
                'company' => $company,
                'price' => $price,
                'timestamp' => $timestamp
            ]
        ];

        //Send data to RabbitMQ
        $client = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ.ini", "testServer");
        $client->send_request($dataToStore);
        error_log("Sent stock data to RabbitMQ for storage.");

        echo json_encode([
            'ticker' => $ticker,
            'company' => $company,
            'price' => $price,
            'timestamp' => date("Y-m-d H:i:s", $timestamp),
            'message' => 'Stock data retrieved successfully'
        ]);
    } else {
        error_log("Final error: Stock not found in API response.");
        echo json_encode(['error' => 'Ticker not found in the API response']);
    }
} else {
    error_log("Error: No ticker provided in request.");
    echo json_encode(['error' => 'Ticker not provided']);
}
?>
