<?php
header('Content-Type: application/json');
require_once "/home/website/IT490-Project/rabbitMQLib.inc";

//Prevent `testRabbitMQ.ini` from printing anything
ob_start();
require_once "/home/website/IT490-Project/testRabbitMQ.ini";
ob_end_clean();

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . "/fetch_stock_error.log");

//Start output buffering to prevent unwanted text before JSON
ob_start();

function getStockDataFromDB($ticker) {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

    $request = [
        'action' => 'get_stock',
        'data' => ['ticker' => $ticker]
    ];

    error_log("Sending request to RabbitMQ to fetch stock data: " . print_r($request, true));

    $response = $client->send_request($request);

    error_log("Received response from RabbitMQ: " . print_r($response, true));

    return $response;
}

//Ensure ticker is provided
if (!isset($_GET['ticker']) || empty($_GET['ticker'])) {
    ob_end_clean(); //Ensure no extra output
    echo json_encode(['error' => 'No ticker provided.']);
    exit();
}

$ticker = strtoupper(trim($_GET['ticker']));
error_log("Processing stock request for ticker: " . $ticker);

// Fetch stock from RabbitMQ (database)
$storedStock = getStockDataFromDB($ticker);

// Ensure no extra output before returning JSON
ob_clean();

if ($storedStock && isset($storedStock['status']) && $storedStock['status'] === 'success') {
    echo json_encode($storedStock['data']);
} else {
    echo json_encode(['error' => 'Stock not found in the database.']);
}
exit();
