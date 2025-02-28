<?php
header('Content-Type: application/json');
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";

error_reporting(E_ALL);
ini_set('display_errors', 1);

function getStockData($ticker) {
    $apiKey = 'c445a9ff73msh1ba778fa2e6e77bp1681cbjsn1e7785aa5761'; // Replace with your actual API key
    $apiUrl = "https://yahoo-finance15.p.rapidapi.com/api/v1/markets/stock/modules?ticker=$ticker&module=asset-profile"; // Replace with the actual API URL

    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
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

    if ($response === false) {
        error_log("API response is false");
        return null;
    }

    $data = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        return null;
    }

    // Log the API response for debugging
    error_log("API Response: " . print_r($data, true));

    return $data;
}

if (isset($_GET['ticker'])) {
    $ticker = $_GET['ticker'];
    $stock_data = getStockData($ticker);

    // Log the stock data for debugging
    error_log("Stock Data: " . print_r($stock_data, true));

    if ($stock_data && isset($stock_data['price'])) {
        $company = $stock_data['company'];
        $price = $stock_data['price'];
        $timestamp = time();

        $data = [
            'action' => 'store_stock',
            'data' => [
                'ticker' => $ticker,
                'company' => $company,
                'price' => $price,
                'timestamp' => $timestamp
            ]
        ];

        // Send the request to RabbitMQ
        $client = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ.ini", "testServer");
        $response = $client->send_request($data);

        // Handle Response
        if ($response['status'] === 'success') {
            echo json_encode([
                'ticker' => $ticker,
                'company' => $company,
                'price' => $price,
                'timestamp' => $timestamp,
                'message' => 'Stock data stored successfully'
            ]);
        } else {
            echo json_encode([
                'error' => 'Failed to store stock data',
                'details' => $response
            ]);
        }
    } else {
        echo json_encode(['error' => 'Failed to fetch stock data']);
    }
} else {
    echo json_encode(['error' => 'Ticker not provided']);
}
?>