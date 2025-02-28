<?php
require_once "rabbitMQLib.inc";
require_once "testRabbitMQ.ini";

function processRequest($request)
{
    $mydb = new mysqli("192.168.1.142", "testUser", "12345", "it490db");

    if ($mydb->connect_error) {
        error_log("Database connection failed: " . $mydb->connect_error);
        return ["status" => "error", "message" => "Database connection failed."];
    }

    // Validate request format
    if (!isset($request['action']) || !isset($request['data'])) {
        return ["status" => "error", "message" => "Invalid request format."];
    }

    switch ($request['action']) {
        case "store_stock":
            if (!isset($request['data']['ticker']) || !isset($request['data']['company']) || !isset($request['data']['price']) || !isset($request['data']['timestamp'])) {
                return ["status" => "error", "message" => "Missing required stock data fields."];
            }

            $ticker = strtoupper(trim($request['data']['ticker']));
            $company = trim($request['data']['company']);
            $price = floatval($request['data']['price']);
            $timestamp = trim($request['data']['timestamp']);

            // Insert stock data safely using prepared statements
            $insertQuery = "INSERT INTO stocks (ticker, company, price, timestamp) VALUES (?, ?, ?, ?)";
            $stmt = $mydb->prepare($insertQuery);
            $stmt->bind_param("ssds", $ticker, $company, $price, $timestamp);

            if ($stmt->execute()) {
                return ["status" => "success", "message" => "Stock data stored successfully."];
            } else {
                error_log("Database insert error: " . $stmt->error);
                return ["status" => "error", "message" => "Database insert failed."];
            }

        default:
            return ["status" => "error", "message" => "Unknown action: " . $request['action']];
    }
}

$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");
$server->process_requests("processRequest");
?>