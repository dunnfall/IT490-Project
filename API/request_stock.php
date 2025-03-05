<?php
header('Content-Type: application/json');
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";

if (!isset($_GET['ticker']) || empty($_GET['ticker'])) {
    echo json_encode(['error' => 'No ticker provided.']);
    exit();
}

$ticker = strtoupper(trim($_GET['ticker']));

$dataToSend = [
    'action' => 'request_stock',
    'data' => ['ticker' => $ticker]
];

$client = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ.ini", "testServer");
$response = $client->send_request($dataToSend);

if (!$response || !isset($response['status']) || $response['status'] !== 'success') {
    echo json_encode(['error' => 'Failed to process stock request.']);
    exit();
}

echo json_encode(['success' => 'Stock request sent. Processing...']);
