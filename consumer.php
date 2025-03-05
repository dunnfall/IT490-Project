<?php
require_once "rabbitMQLib.inc";
require_once "testRabbitMQ_response.ini";

function processRequest($request)
{
    $mydb = new mysqli("192.168.1.142", "testUser", "12345", "it490db");

    if ($mydb->connect_error) {
        return ["status" => "error", "message" => "Database connection failed: " . $mydb->connect_error];
    }

    if (!isset($request['action'])) {
        return ["status" => "error", "message" => "Invalid request format."];
    }

    switch ($request['action']) {
        case "get_stock":
            if (!isset($request['data']['ticker'])) {
                return ["status" => "error", "message" => "Ticker not provided"];
            }
            
            $ticker = strtoupper(trim($request['data']['ticker']));
            $query = "SELECT * FROM stocks WHERE ticker = ?";
            $stmt = $mydb->prepare($query);
            $stmt->bind_param("s", $ticker);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                return ["status" => "success", "data" => $result->fetch_assoc()];
            }
            return ["status" => "error", "message" => "Stock not found"];
        
        case "store_stock":
            if (!isset($request['data']['ticker'], $request['data']['company'], $request['data']['price'])) {
                return ["status" => "error", "message" => "Missing required stock data fields."];
            }
            
            $ticker = strtoupper(trim($request['data']['ticker']));
            $company = trim($request['data']['company']);
            $price = floatval($request['data']['price']);
            $timestamp = date("Y-m-d H:i:s");
            $weekChange = $request['data']['52weekchangepercent'] ?? null;
            $weekHigh = $request['data']['52weekhigh'] ?? null;
            $weekLow = $request['data']['52weeklow'] ?? null;
            $marketCap = $request['data']['marketcap'] ?? null;
            $region = $request['data']['region'] ?? 'N/A';
            $currency = $request['data']['currency'] ?? 'N/A';
            
            // Check if stock exists
            $checkQuery = "SELECT id FROM stocks WHERE ticker = ?";
            $stmt = $mydb->prepare($checkQuery);
            $stmt->bind_param("s", $ticker);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing stock data
                $updateQuery = "UPDATE stocks SET price = ?, timestamp = ?, `52weekchangepercent` = ?, `52weekhigh` = ?, `52weeklow` = ?, marketcap = ?, region = ?, currency = ? WHERE ticker = ?";
                $stmt = $mydb->prepare($updateQuery);
                $stmt->bind_param("dssdddsss", $price, $timestamp, $weekChange, $weekHigh, $weekLow, $marketCap, $region, $currency, $ticker);
                return $stmt->execute() ? ["status" => "success", "message" => "Stock updated"] : ["status" => "error", "message" => "Update failed"];
            } else {
                // Insert new stock
                $insertQuery = "INSERT INTO stocks (ticker, company, price, timestamp, `52weekchangepercent`, `52weekhigh`, `52weeklow`, marketcap, region, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $mydb->prepare($insertQuery);
                $stmt->bind_param("ssdsdddsss", $ticker, $company, $price, $timestamp, $weekChange, $weekHigh, $weekLow, $marketCap, $region, $currency);
                return $stmt->execute() ? ["status" => "success", "message" => "Stock stored"] : ["status" => "error", "message" => "Insert failed"];
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
        case "register":
            $table = $request['table'];
            $columns = implode(", ", array_keys($request['data']));
            $values = "'" . implode("', '", array_map([$mydb, 'real_escape_string'], array_values($request['data']))) . "'";

            $sql = "INSERT INTO $table ($columns) VALUES ($values)";

            if ($mydb->query($sql) === TRUE) {
                return ["status" => "success", "message" => "New user registered."];
            } else {
                return ["status" => "error", "message" => $mydb->error];
            }

        case "login":
            $identifier = $request['identifier'];
            $password = $request['password'];

            // Check if the user exists (by username or email)
            $sql = "SELECT * FROM users WHERE username='$identifier' OR email='$identifier'";
            $result = $mydb->query($sql);

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    return ["status" => "success", "message" => "User authenticated.", "username" => $user['username']];
                } else {
                    return ["status" => "error", "message" => "Invalid password."];
                }
            } else {
                return ["status" => "error", "message" => "User not found."];
            }

        default:
            return ["status" => "error", "message" => "Unknown action: " . $request['action']];
    }
}

// Start Consumer to Listen for Messages
$server = new rabbitMQServer("testRabbitMQ_response.ini", "responseServer");
$server->process_requests("processRequest");
?>