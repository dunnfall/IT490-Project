<?php
require_once "rabbitMQLib.inc";
require_once "testRabbitMQ.ini";

function processRequest($request)
{
    error_log("Received request: " . print_r($request, true));

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

    $action = trim($request['action']);

    switch ($action) {
        case "store_stock":
            if (!isset($request['data']['ticker'], $request['data']['company'], $request['data']['price'], $request['data']['timestamp'])) {
                return ["status" => "error", "message" => "Missing required stock data fields."];
            }
        
            $ticker = strtoupper(trim($request['data']['ticker']));
            $company = trim($request['data']['company']);
            $price = floatval($request['data']['price']);
            $timestamp = date("Y-m-d H:i:s", intval($request['data']['timestamp']));
            $table = "stocks";
        
            //Always update stock if it exists
            $updateQuery = "INSERT INTO $table (ticker, company, price, timestamp)
                            VALUES (?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE 
                            company = VALUES(company), 
                            price = VALUES(price), 
                            timestamp = VALUES(timestamp)";
            $stmt = $mydb->prepare($updateQuery);
            $stmt->bind_param("ssds", $ticker, $company, $price, $timestamp);
        
            if ($stmt->execute()) {
                return ["status" => "success", "message" => "Stock data updated successfully."];
            } else {
                error_log("Database update error: " . $stmt->error);
                return ["status" => "error", "message" => "Database update failed."];
            }
        

            case "get_stock":
                if (!isset($request['data']['ticker'])) {
                    return ["status" => "error", "message" => "Ticker not provided"];
                }
            
                $ticker = strtoupper(trim($request['data']['ticker']));
                error_log("Checking database for stock: " . $ticker);
            
                $query = "SELECT ticker, company, price, timestamp FROM stocks WHERE ticker = ?";
                $stmt = $mydb->prepare($query);
                if (!$stmt) {
                    error_log("Prepare statement failed: " . $mydb->error);
                    return ["status" => "error", "message" => "Database query preparation failed"];
                }
            
                $stmt->bind_param("s", $ticker);
                if (!$stmt->execute()) {
                    error_log("Query execution failed: " . $stmt->error);
                    return ["status" => "error", "message" => "Query execution failed"];
                }
            
                $result = $stmt->get_result();
                if (!$result) {
                    error_log("Query result retrieval failed: " . $mydb->error);
                    return ["status" => "error", "message" => "Query result retrieval failed"];
                }
            
                if ($result->num_rows > 0) {
                    $stockData = $result->fetch_assoc();
                    error_log("Stock found in DB: " . print_r($stockData, true));
                    return ["status" => "success", "data" => $stockData];
                } else {
                    error_log("Stock not found in DB");
                    return ["status" => "error", "message" => "Stock not found"];
                }
            
        default:
            error_log("Unknown action received: '" . $action . "'");
            return ["status" => "error", "message" => "Unknown action: " . $action];
    }
}

// Set up RabbitMQ server to process requests
$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");
$server->process_requests("processRequest");
