<?php
header('Content-Type: application/json');
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
$ini = parse_ini_file("/home/website/IT490-Project/testRabbitMQ.ini");

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);

// error_reporting(E_ALL);
// ini_set('display_errors', 1);
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . "/get_stock_error.log");

// Check if ticker is provided
if (!isset($_GET['ticker']) || empty($_GET['ticker'])) {
    echo json_encode(['error' => 'No ticker provided.']);
    exit();
}

$ticker = strtoupper(trim($_GET['ticker']));

// Prepare request for RabbitMQ
$request = [
    'action' => 'get_stock',  // Ensure this matches stock_consumer.php
    'data' => ['ticker' => $ticker]
];

// Send request to RabbitMQ
$client = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ.ini", "testServer");
$response = $client->send_request($request);

if (!$response || !isset($response['status'])) {
    echo json_encode(['error' => 'Failed to retrieve stock data.']);
    exit();
}

if ($response['status'] === 'error') {
    echo json_encode(['error' => $response['message']]);
    exit();
}

// Output the stock data
echo json_encode($response['data']);
?>