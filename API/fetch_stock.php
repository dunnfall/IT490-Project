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

    $response = $client->send_request($request);
    return $response;
}

function getStockDataFromAPI($ticker) {
    $apiKey = 'c445a9ff73msh1ba778fa2e6e77bp1681cbjsn1e7785aa5761';
    $apiUrl = "https://yahoo-finance15.p.rapidapi.com/api/v1/markets/stock/quotes?ticker=" . urlencode($ticker);

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
        error_log("cURL Error #: $err");
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

    // First, try to fetch stock data from the database
    $storedStock = getStockDataFromDB($ticker);

    if ($storedStock && isset($storedStock['status']) && $storedStock['status'] === 'success') {
        echo json_encode($storedStock['data']);
        exit();
    }

    // eac look to see if its in db
    $stock_data = getStockDataFromAPI($ticker);

    if (!$stock_data || !isset($stock_data['body']) || empty($stock_data['body'])) {
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

        $dataToStore = [
            'action' => 'store_stock',
            'data' => [
                'ticker' => $ticker,
                'company' => $company,
                'price' => $price,
                'timestamp' => $timestamp
            ]
        ];

        // Send data to RabbitMQ
        $client = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ.ini", "testServer");
        $client->send_request($dataToStore);

        echo json_encode([
            'ticker' => $ticker,
            'company' => $company,
            'price' => $price,
            'timestamp' => date("Y-m-d H:i:s", $timestamp),
            'message' => 'Stock data retrieved successfully'
        ]);
    } else {
        echo json_encode(['error' => 'Ticker not found in the API response']);
    }
} else {
    echo json_encode(['error' => 'Ticker not provided']);
}
?>
