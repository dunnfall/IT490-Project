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
            error_log("Fetching ticker for user: " . $ticker);

            // Query the database to retrieve stock information
            $query = "SELECT * FROM stocks WHERE ticker = ?";
            $stmt = $mydb->prepare($query);
            $stmt->bind_param("s", $ticker);
            $stmt->execute();
            $result = $stmt->get_result();

            error_log("Fetching ticker for user: " . $ticker);

            if ($result->num_rows > 0) {
                return ["status" => "success", "data" => $result->fetch_assoc()];
            } else {
                return ["status" => "error", "message" => "Stock not found"];
            }

            error_log("Fetching ticker for user: " . $ticker);

        case "retrieve_stock":
            if (!isset($request['data']['ticker'])) {
                return ["status" => "error", "message" => "Ticker not provided"];
            }

            $ticker = strtoupper(trim($request['data']['ticker']));

            //Request latest stock data from DMZ Server
            $dmzClient = new rabbitMQClient("/home/database/IT490-Project/RabbitDMZ.ini", "dmzServer");
            $dmzRequest = ['type' => 'fetch_stock', 'data' => ['ticker' => $ticker]];  // Corrected type field
            $dmzResponse = $dmzClient->send_request($dmzRequest);

            // Handle API response failure
            if (!$dmzResponse || $dmzResponse['status'] !== 'success') {
                return ["status" => "error", "message" => "Failed to fetch stock data from API."];
            }

            //Step 3: Extract stock data from the DMZ response
            $stockData = $dmzResponse['data'];
            $company = trim($stockData['company']);
            $price = floatval($stockData['price']);
            $timestamp = date("Y-m-d H:i:s"); // Current server time
            $weekChange = $stockData['52weekchangepercent'] ?? null;
            $weekHigh = $stockData['52weekhigh'] ?? null;
            $weekLow = $stockData['52weeklow'] ?? null;
            $marketCap = $stockData['marketcap'] ?? null;
            $region = $stockData['region'] ?? 'N/A';
            $currency = $stockData['currency'] ?? 'N/A';

            //Step 4: Check if stock already exists in the database
            $checkQuery = "SELECT id FROM stocks WHERE ticker = ?";
            if ($stmt = $mydb->prepare($checkQuery)) {
                $stmt->bind_param("s", $ticker);
                $stmt->execute();
                $result = $stmt->get_result();
                $stockExists = ($result->num_rows > 0);
                $stmt->close();
            } else {
                return ["status" => "error", "message" => "Database query error: " . $mydb->error];
            }

            //Update if exists, insert if not
            if ($stockExists) {
                // Update stock in the database
                $updateQuery = "UPDATE stocks 
                                SET price = ?, timestamp = ?, `52weekchangepercent` = ?, `52weekhigh` = ?, `52weeklow` = ?, marketcap = ?, region = ?, currency = ? 
                                WHERE ticker = ?";
                if ($stmt = $mydb->prepare($updateQuery)) {
                    $stmt->bind_param("dssdddsss", $price, $timestamp, $weekChange, $weekHigh, $weekLow, $marketCap, $region, $currency, $ticker);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    return ["status" => "error", "message" => "Database update error: " . $mydb->error];
                }
            } else {
                // Insert new stock into database
                $insertQuery = "INSERT INTO stocks (ticker, company, price, timestamp, `52weekchangepercent`, `52weekhigh`, `52weeklow`, marketcap, region, currency) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                if ($stmt = $mydb->prepare($insertQuery)) {
                    $stmt->bind_param("ssdsdddsss", $ticker, $company, $price, $timestamp, $weekChange, $weekHigh, $weekLow, $marketCap, $region, $currency);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    return ["status" => "error", "message" => "Database insert error: " . $mydb->error];
                }
            }

            // Step 6: Return success response
            return ["status" => "success", "message" => "Stock data updated successfully", "data" => $stockData];


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
            $escaped = array_map([$mydb, 'real_escape_string'], array_values($request['data']));
            $values = "'" . implode("', '", $escaped) . "'";

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
                    // Generate a token and store it
                    $token = bin2hex(random_bytes(16)); // 32-char hex

                    $insertToken = "INSERT INTO tokens (token, username) VALUES (?, ?)";
                    $stmtToken = $mydb->prepare($insertToken);
                    $stmtToken->bind_param("ss", $token, $user['username']);
                    $stmtToken->execute();

                    return [
                        "status" => "success",
                        "message" => "User authenticated.",
                        "username" => $user['username'],
                        "token" => $token
                    ];
                } else {
                    return ["status" => "error", "message" => "Invalid password."];
                }
            } else {
                return ["status" => "error", "message" => "User not found."];
            }

        case "verifyToken":
            if (!isset($request['token'])) {
                return ["status" => "error", "message" => "No token provided"];
            }
            $token = $request['token'];

            $sqlToken = "SELECT username FROM tokens WHERE token = ? LIMIT 1";
            $stmt = $mydb->prepare($sqlToken);
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                return ["status" => "success", "username" => $row['username']];
            } else {
                return ["status" => "error", "message" => "Invalid token"];
            }

        case "verifyAndGetEmail":
            $token = $request['token'] ?? '';
            if (!$token) {
                return ["status" => "error", "message" => "No token provided"];
            }

            // tokens table
            $sqlToken = "SELECT username FROM tokens WHERE token = ? LIMIT 1";
            $stmt = $mydb->prepare($sqlToken);
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $resToken = $stmt->get_result();

            if ($resToken->num_rows < 1) {
                return ["status" => "error", "message" => "Invalid token"];
            }

            $row = $resToken->fetch_assoc();
            $username = $row['username'];

            // users table for email
            $sqlEmail = "SELECT email FROM users WHERE username = ? LIMIT 1";
            $stmt2 = $mydb->prepare($sqlEmail);
            $stmt2->bind_param("s", $username);
            $stmt2->execute();
            $resEmail = $stmt2->get_result();

            if ($resEmail->num_rows < 1) {
                return ["status" => "error", "message" => "User not found"];
            }

            $rowEmail = $resEmail->fetch_assoc();
            return [
                "status" => "success",
                "email" => $rowEmail['email']
            ];

        case "verifyAndGetBalance":
            // 1) Check if a token was provided
            $token = $request['token'] ?? '';
            if (!$token) {
                return ["status" => "error", "message" => "No token provided"];
            }

            // 2) Verify the token in 'tokens' table
            $sqlToken = "SELECT username FROM tokens WHERE token = ? LIMIT 1";
            $stmt = $mydb->prepare($sqlToken);
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $resToken = $stmt->get_result();

            if ($resToken->num_rows < 1) {
                // Token not found => invalid
                return ["status" => "error", "message" => "Invalid token"];
            }

            // 3) Get the username from the tokens table
            $row = $resToken->fetch_assoc();
            $username = $row['username'];

            // 4) Now retrieve the user's balance from 'users' table
            $sqlBalance = "SELECT balance FROM users WHERE username = ? LIMIT 1";
            $stmt2 = $mydb->prepare($sqlBalance);
            $stmt2->bind_param("s", $username);
            $stmt2->execute();
            $resBal = $stmt2->get_result();

            if ($resBal->num_rows < 1) {
                // If no user row found, return error
                return ["status" => "error", "message" => "User not found"];
            }

            // 5) Get the balance
            $rowBal = $resBal->fetch_assoc();
            $balance = $rowBal['balance'];

            // 6) Return a success array with balance (and username if needed)
            return [
                "status" => "success",
                "username" => $username,
                "balance" => $balance
            ];

        // Optionally add a "logout" case to remove token if needed
        // case "logout":
        //    ...

        default:
            return ["status" => "error", "message" => "Unknown action: " . $request['action']];
    }
}

// Start the RabbitMQ server
$server = new rabbitMQServer("testRabbitMQ_response.ini", "responseServer");
$server->process_requests("processRequest");
?>