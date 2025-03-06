<?php
header('Content-Type: application/json'); // Return JSON

require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";

// We assume profile.php already checked the token. 
// So we do NOT call "verifyToken" again here.

// Instead, we need the username. You can pass it from profile.php 
// in the fetch URL, e.g. fetch("get_balance.php?user=bob")

$username = $_GET['user'] ?? '';
if (!$username) {
    echo json_encode(["error" => "No username specified"]);
    exit();
}

// Create RabbitMQ client
$client = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ.ini", "testServer");

// Prepare the request to get balance
$request = [
    "action" => "get_balance",
    "data" => [
        "username" => $username
    ]
];

// Send request
$response = $client->send_request($request);

// Return JSON
if ($response && isset($response["status"]) && $response["status"] === "success") {
    echo json_encode(["balance" => number_format($response["balance"], 2)]);
} else {
    echo json_encode(["error" => $response["message"] ?? "Failed to fetch balance"]);
}
?>