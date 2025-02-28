<?php
require_once "rabbitMQLib.inc";
require_once "testRabbitMQ.ini";

function processRequest($request)
{
    // Establish database connection
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
            // Ensure data has the required fields
            if (!isset($request['data']['ticker']) || !isset($request['data']['company']) || !isset($request['data']['price']) || !isset($request['data']['timestamp'])) {
                return ["status" => "error", "message" => "Missing required stock data fields."];
            }

            $ticker = strtoupper(trim($request['data']['ticker']));
            $company = trim($request['data']['company']);
            $price = floatval($request['data']['price']);
            $timestamp = trim($request['data']['timestamp']);
            $table = "stocks"; // Assuming stocks table

            // Check if stock already exists
            $checkQuery = "SELECT id FROM $table WHERE ticker = ?";
            $stmt = $mydb->prepare($checkQuery);
            $stmt->bind_param("s", $ticker);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                return ["status" => "error", "message" => "Stock already exists in database."];
            }

            // Insert stock data safely using prepared statements
            $insertQuery = "INSERT INTO $table (ticker, company, price, timestamp) VALUES (?, ?, ?, ?)";
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

// Set up RabbitMQ server to process requests
$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");
$server->process_requests("processRequest");
?>
