<?php
header('Content-Type: application/json');
require_once "/home/website/IT490-Project/rabbitMQLib.inc";

// Load RabbitMQ config
$ini = parse_ini_file("/home/website/IT490-Project/testRabbitMQ.ini");

// Validate input
if (!isset($_GET['ticker']) || empty($_GET['ticker'])) {
    echo json_encode(['error' => 'No ticker provided.']);
    exit();
}

$ticker = strtoupper(trim($_GET['ticker']));

// Initialize RabbitMQ client
$client = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ.ini", "testServer");

// Check if stock exists in the database
$request = ['type' => 'get_stock', 'data' => ['ticker' => $ticker]];
$response = $client->send_request($request);

if ($response && isset($response['status']) && $response['status'] === 'success') {
    echo json_encode($response['data']);
    exit();
}

// If stock is missing, request a new stock entry
$updateRequest = ['type' => 'retrieve_stock', 'data' => ['ticker' => $ticker]];
$updateResponse = $client->send_request($updateRequest);

if (!$updateResponse || $updateResponse['status'] !== 'success') {
    echo json_encode(['error' => 'Stock not found and failed to retrieve.']);
    exit();
}

// Return the newly added stock data
echo json_encode($updateResponse['data']);
?>