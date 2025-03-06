<?php
// verifyAndGetBalance.php

// 1) Always output JSON
header("Content-Type: application/json");

require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";

// 2) Grab the token from the cookie
$token = $_COOKIE['authToken'] ?? '';
if (!$token) {
    // If no token is found, return JSON error instead of redirecting
    echo json_encode(["error" => "No token provided (not logged in)."]);
    exit();
}

// 3) Create the RabbitMQ client
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

// 4) Send a single request to verify token + get balance
$request = [
    "action" => "verifyAndGetBalance",
    "token"  => $token
];
$response = $client->send_request($request);

// 5) Convert the consumer’s response to JSON
//    If consumer returns ["status"=>"success","balance"=>..., ...], we output that
if (isset($response["status"]) && $response["status"] === "success") {
    // e.g. { "status":"success","balance":123.45 }
    echo json_encode([
        "balance" => number_format($response["balance"], 2)
    ]);
} else {
    // e.g. { "status":"error","message":"Invalid token" }
    // Return as {"error":"Invalid token"}
    echo json_encode([
        "error" => $response["message"] ?? "Failed to fetch balance"
    ]);
}
?>