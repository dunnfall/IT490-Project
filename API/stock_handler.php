<?php
header('Content-Type: application/json');
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
$ini = parse_ini_file("/home/website/IT490-Project/testRabbitMQ.ini");

if (!isset($_GET['ticker']) || empty($_GET['ticker'])) {
    echo json_encode(['error' => 'No ticker provided.']);
    exit();
}

$ticker = strtoupper(trim($_GET['ticker']));

$client = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ.ini", "testServer");

// Step 1: Check if stock exists in the database first
$request = ['action' => 'get_stock', 'data' => ['ticker' => $ticker]];
$response = $client->send_request($request);

if ($response && isset($response['status']) && $response['status'] === 'success') {
    echo json_encode($response['data']);
    exit();
}

echo json_encode(['error' => 'Stock not found. Requesting update.']);
exit();

// Wait for the stock data to be updated in the database
sleep(3);

// Step 3: Fetch the stock from the database again
$response = $client->send_request(['action' => 'get_stock', 'data' => ['ticker' => $ticker]]);
if ($response && isset($response['status']) && $response['status'] === 'success') {
    echo json_encode($response['data']);
} else {
    echo json_encode(['error' => 'Stock not found or request failed.']);
}
?>
