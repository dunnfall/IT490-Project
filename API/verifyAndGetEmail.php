<?php
header('Content-Type: application/json');

// Load RabbitMQ client
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ_response.ini";

// 1) Check for auth token in the cookie
$token = $_COOKIE['authToken'] ?? '';
if (!$token) {
    echo json_encode(["error" => "No token found"]);
    exit();
}

// 2) Create RabbitMQ client and request "verifyAndGetEmail"
$client = new rabbitMQClient("testRabbitMQ_response.ini", "responseServer");
$request = [
    "action" => "verifyAndGetEmail",
    "token"  => $token
];
$response = $client->send_request($request);

// 3) Return JSON to the caller
if (isset($response["status"]) && $response["status"] === "success") {
    echo json_encode(["email" => $response["email"]]);
} else {
    echo json_encode(["error" => $response["message"] ?? "Failed to fetch email"]);
}
?>