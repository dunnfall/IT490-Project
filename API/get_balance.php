<?php
header('Content-Type: application/json');
require_once "/home/website/IT490-Project/rabbitMQLib.inc";

// Start session to get logged-in user
session_start();

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit();
}

// Get the username from session
$username = $_SESSION['username'];

// Create RabbitMQ client
$client = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ.ini", "testServer");

// Prepare the request to get balance
$request = [
    "action" => "get_balance",
    "data" => [
        "username" => $username
    ]
];

// Send request to RabbitMQ and wait for response
$response = $client->send_request($request);

// Validate response and return JSON
if ($response && isset($response["status"]) && $response["status"] === "success") {
    echo json_encode(["balance" => number_format($response["balance"], 2)]);
} else {
    echo json_encode(["error" => $response["message"] ?? "Failed to fetch balance"]);
}
?>
