<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);  // Hide deprecation warnings


// Load RabbitMQ config
$ini = parse_ini_file("/home/website/IT490-Project/testRabbitMQ.ini");

// Validate input
if (!isset($_GET['ticker']) || empty($_GET['ticker'])) {
    echo json_encode(['error' => 'No ticker provided.']);
    exit();
}

$ticker = strtoupper(trim($_GET['ticker']));
error_log("Fetching ticker for user: " . $ticker);

// Initialize RabbitMQ client
$client = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ.ini", "testServer");
error_log("Fetching ticker for user still working here: " . $ticker);

// Check if stock exists in the database
$request = ['action' => 'get_stock', 'data' => ['ticker' => $ticker]];
error_log("Fetching ticker for user is it still working?: " . $ticker);
$response = $client->send_request($request);
error_log("Fetching ticker for user: How about now " . $ticker);

if ($response && isset($response['status']) && $response['status'] === 'success') {
    echo json_encode($response['data']);
    exit();
}
error_log("Fetching ticker for user: IS it failing yet " . $ticker);
// If stock is missing, request a new stock entry
$updateRequest = ['type' => 'retrieve_stock', 'data' => ['ticker' => $ticker]];
$updateResponse = $client->send_request($updateRequest);

error_log("For sure has failed " . $ticker);

if (!$updateResponse || $updateResponse['status'] !== 'success') {
    echo json_encode(['error' => 'Stock not found and failed to retrieve.']);
    exit();
}

error_log("Defintly now " . $ticker);

// Return the newly added stock data
echo json_encode($updateResponse['data']);
?>