<?php
header('Content-Type: application/json');
require_once "/home/website/IT490-Project/rabbitMQLib.inc";

session_start();

if (!isset($_SESSION['username'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit();
}

$username = $_SESSION['username'];

// Create RabbitMQ client
$client = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ.ini", "testServer");

// Retry logic for RabbitMQ request
$maxRetries = 3;
$retryDelay = 2; // Seconds
$response = null;

for ($i = 0; $i < $maxRetries; $i++) {
    $request = [
        "action" => "get_email",
        "data" => ["username" => $username]
    ];

    $response = $client->send_request($request);

    if ($response && isset($response["status"]) && $response["status"] === "success") {
        break; // Exit loop if successful
    }

    error_log("Retrying get_email request ($i)... waiting $retryDelay sec");
    sleep($retryDelay);
}

// Final output
if ($response && isset($response["status"]) && $response["status"] === "success") {
    echo json_encode(["email" => $response["email"]]);
} else {
    echo json_encode(["error" => $response["message"] ?? "Failed to fetch email after retries"]);
}
?>