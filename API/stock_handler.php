<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE); // Hide deprecation warnings
header('Content-Type: application/json');

require_once "/home/website/IT490-Project/rabbitMQLib.inc";

// Validate input
if (!isset($_GET['ticker']) || empty($_GET['ticker'])) {
    echo json_encode(['error' => 'No ticker provided.']);
    exit();
}

$ticker = strtoupper(trim($_GET['ticker']));
error_log("Stock Handler: Received ticker request for " . $ticker);

// Initialize RabbitMQ client
$client = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ.ini", "testServer");
error_log("Stock Handler: Sending 'retrieve_stock' request for " . $ticker);

//Send single request to "retrieve_stock"
$request = ['action' => 'retrieve_stock', 'data' => ['ticker' => $ticker]];
$response = $client->send_request($request);

//Check if any response was received
if (!$response) {
    error_log("ERROR: No response from 'retrieve_stock' for " . $ticker);
    echo json_encode(['error' => 'No response received from stock retrieval.']);
    exit();
}

//Check if response indicates success
if (!isset($response['status']) || $response['status'] !== 'success') {
    error_log("ERROR: 'retrieve_stock' failed for " . $ticker . " => " . json_encode($response));
    echo json_encode(['error' => 'Stock retrieval failed.']);
    exit();
}

//Return the stock data
error_log("Stock Handler: Successfully retrieved stock => " . json_encode($response['data']));
echo json_encode($response['data']);
exit();
