<?php
header('Content-Type: application/json');
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
$ini = parse_ini_file("/home/website/IT490-Project/testRabbitMQ.ini");

if (!isset($_GET['ticker']) || empty($_GET['ticker'])) {
    echo json_encode(['error' => 'No ticker provided.']);
    exit();
}

$ticker = strtoupper(trim($_GET['ticker']));

// Connect to RabbitMQ
$client = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ.ini", "testServer");

// Request the stock data (fetch from API & store in DB)
$request = ['action' => 'fetch_stock', 'data' => ['ticker' => $ticker]];
$response = $client->send_request($request);

// If stock data is returned, send it to the frontend
if ($response && isset($response['status']) && $response['status'] === 'success') {
    echo json_encode($response['data']);
} else {
    echo json_encode(['error' => 'Stock not found or request failed.']);
}
exit();
?>