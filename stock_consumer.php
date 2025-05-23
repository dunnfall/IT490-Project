<?php
require_once "rabbitMQLib.inc";
require_once "testRabbitMQ.ini";

error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);

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
            $timestamp = date("Y-m-d H:i:s", strtotime($request['data']['timestamp']));
            $weekChange = $request['data']['52weekchangepercent'] ?? null;
            $weekHigh = $request['data']['52weekhigh'] ?? null;
            $weekLow = $request['data']['52weeklow'] ?? null;
            $marketCap = $request['data']['marketcap'] ?? null;
            $region = $request['data']['region'] ?? 'N/A';
            $currency = $request['data']['currency'] ?? 'N/A';
            $table = "stocks";
        
            // Check if stock already exists
            $checkQuery = "SELECT id FROM $table WHERE ticker = ?";
            $stmt = $mydb->prepare($checkQuery);
            $stmt->bind_param("s", $ticker);
            $stmt->execute();
            $result = $stmt->get_result();
        
            if ($result->num_rows > 0) {
                //If stock exists, update it instead of inserting a duplicate
                $updateQuery = "UPDATE $table SET price = ?, timestamp = ?, `52weekchangepercent` = ?, `52weekhigh` = ?, `52weeklow` = ?, marketcap = ?, region = ?, currency = ? WHERE ticker = ?";
                $stmt = $mydb->prepare($updateQuery);
                $stmt->bind_param("dssdddsss", $price, $timestamp, $weekChange, $weekHigh, $weekLow, $marketCap, $region, $currency, $ticker);
                
                if ($stmt->execute()) {
                    return ["status" => "success", "message" => "Stock price updated successfully."];
                } else {
                    error_log("Stock update error: " . $stmt->error);
                    return ["status" => "error", "message" => "Stock update failed."];
                }
            } else {
                // If stock doesn't exist, insert it
                $insertQuery = "INSERT INTO $table (ticker, company, price, timestamp, `52weekchangepercent`, `52weekhigh`, `52weeklow`, marketcap, region, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $mydb->prepare($insertQuery);
                $stmt->bind_param("ssdsdddsss", $ticker, $company, $price, $timestamp, $weekChange, $weekHigh, $weekLow, $marketCap, $region, $currency);
        
                if ($stmt->execute()) {
                    return ["status" => "success", "message" => "Stock data stored successfully."];
                } else {
                    error_log("Database insert error: " . $stmt->error);
                    return ["status" => "error", "message" => "Database insert failed."];
                }
            }

            case "get_stock":
                if (!isset($request['data']['ticker'])) {
                    return ["status" => "error", "message" => "Ticker not provided"];
                }
            
                $ticker = strtoupper(trim($request['data']['ticker']));
                error_log("Checking database for stock: " . $ticker);
            
                $query = "SELECT ticker, company, price, timestamp, `52weekchangepercent`, `52weekhigh`, `52weeklow`, marketcap, region, currency FROM stocks WHERE ticker = ?";
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
                case "get_balance":
                    if (!isset($request['data']['username'])) {
                        return ["status" => "error", "message" => "Username not provided"];
                    }
        
                    $username = trim($request['data']['username']);
                    error_log("Fetching balance for user: " . $username);
        
                    $query = "SELECT balance FROM users WHERE username = ?";
                    $stmt = $mydb->prepare($query);
                    if (!$stmt) {
                        return ["status" => "error", "message" => "Database query preparation failed"];
                    }
        
                    $stmt->bind_param("s", $username);
                    if (!$stmt->execute()) {
                        return ["status" => "error", "message" => "Query execution failed"];
                    }
        
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $userData = $result->fetch_assoc();
                        return ["status" => "success", "balance" => $userData["balance"]];
                    } else {
                        return ["status" => "error", "message" => "User not found"];
                    }
    default:
        error_log("Unknown action received: '" . $action . "'");
        return ["status" => "error", "message" => "Unknown action: " . $action];
    }
}

// Set up RabbitMQ server to process requests
$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");
$server->process_requests("processRequest");
