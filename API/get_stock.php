<?php
header('Content-Type: application/json');
require_once "/home/website/IT490-Project/rabbitMQLib.inc";

// 🔹 Prevent `testRabbitMQ.ini` from printing anything
ob_start();
require_once "/home/website/IT490-Project/testRabbitMQ.ini";
ob_end_clean();

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . "/get_stock_error.log");

// Clear any previous output before returning JSON
ob_clean();

// Ensure ticker is provided
if (!isset($_GET['ticker']) || empty($_GET['ticker'])) {
    error_log("No ticker provided.");
    echo json_encode(['error' => 'No ticker provided.']);
    exit();
}

$ticker = strtoupper(trim($_GET['ticker']));
error_log("Retrieving stock from database: " . $ticker);

function getStockDataFromDB($ticker) {
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

    $request = [
        'action' => 'get_stock',
        'data' => ['ticker' => $ticker]
    ];

    error_log("Sending request to RabbitMQ: " . print_r($request, true));

    $response = $client->send_request($request);

    error_log("Received response from RabbitMQ: " . print_r($response, true));

    return $response;
}

// Fetch from DB
$storedStock = getStockDataFromDB($ticker);

if ($storedStock && isset($storedStock['status']) && $storedStock['status'] === 'success') {
    error_log("Stock found in DB: " . print_r($storedStock['data'], true));
    ob_clean(); //  Ensure no unwanted output
    echo json_encode($storedStock['data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} else {
    error_log("Stock not found in the database.");
    ob_clean(); //  Ensure no unwanted output
    echo json_encode(['error' => 'Stock not found in the database.']);
}

exit();
