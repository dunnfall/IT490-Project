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

            // Try fetching from the database first
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                return ["status" => "success", "data" => $result->fetch_assoc()];
            }

            // If not found, request data from the DMZ
            error_log("Stock not found. Requesting from DMZ...");
            $dmzClient = new rabbitMQClient("/home/database/IT490-Project/RabbitDMZ.ini", "dmzServer");

            $dmzRequest = ['action' => 'request_stock', 'data' => ['ticker' => $ticker]];
            $dmzClient->send_request($dmzRequest);

            // Wait for DMZ to process and insert the stock into the database
            for ($i = 0; $i < 5; $i++) { // Retry 5 times (total wait = 10 seconds)
                sleep(2); // Wait 2 seconds before retrying
                error_log("Retrying database query after waiting for DMZ update...");

                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows > 0) {
                    return ["status" => "success", "data" => $result->fetch_assoc()];
                }
            }

            return ["status" => "error", "message" => "Stock not found after multiple retries."];

        case "store_stock":
            if (!isset($request['data']['ticker'], $request['data']['company'], $request['data']['price'])) {
                return ["status" => "error", "message" => "Missing required stock data fields."];
            }

            $ticker    = strtoupper(trim($request['data']['ticker']));
            $company   = trim($request['data']['company']);
            $price     = floatval($request['data']['price']);
            $timestamp = date("Y-m-d H:i:s");
            $weekChange = $request['data']['52weekchangepercent'] ?? null;
            $weekHigh   = $request['data']['52weekhigh'] ?? null;
            $weekLow    = $request['data']['52weeklow'] ?? null;
            $marketCap  = $request['data']['marketcap'] ?? null;
            $region     = $request['data']['region'] ?? 'N/A';
            $currency   = $request['data']['currency'] ?? 'N/A';

            // Check if stock exists
            $checkQuery = "SELECT id FROM stocks WHERE ticker = ?";
            $stmt = $mydb->prepare($checkQuery);
            $stmt->bind_param("s", $ticker);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                // Update existing stock data
                $updateQuery = "UPDATE stocks 
                                SET price = ?, timestamp = ?, `52weekchangepercent` = ?, 
                                    `52weekhigh` = ?, `52weeklow` = ?, marketcap = ?, 
                                    region = ?, currency = ?
                                WHERE ticker = ?";

                $stmt = $mydb->prepare($updateQuery);
                $stmt->bind_param(
                    "dssdddsss",
                    $price,
                    $timestamp,
                    $weekChange,
                    $weekHigh,
                    $weekLow,
                    $marketCap,
                    $region,
                    $currency,
                    $ticker
                );
                return $stmt->execute()
                    ? ["status" => "success", "message" => "Stock updated"]
                    : ["status" => "error", "message" => "Update failed"];

            } else {
                // Insert new stock
                $insertQuery = "INSERT INTO stocks 
                                (ticker, company, price, timestamp, 
                                 `52weekchangepercent`, `52weekhigh`, `52weeklow`, 
                                 marketcap, region, currency)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $mydb->prepare($insertQuery);
                $stmt->bind_param(
                    "ssdsdddsss",
                    $ticker,
                    $company,
                    $price,
                    $timestamp,
                    $weekChange,
                    $weekHigh,
                    $weekLow,
                    $marketCap,
                    $region,
                    $currency
                );
                return $stmt->execute()
                    ? ["status" => "success", "message" => "Stock stored"]
                    : ["status" => "error", "message" => "Insert failed"];
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
            $table    = $request['table'];
            $columns  = implode(", ", array_keys($request['data']));
            $escaped  = array_map([$mydb, 'real_escape_string'], array_values($request['data']));
            $values   = "'" . implode("', '", $escaped) . "'";

            $sql = "INSERT INTO $table ($columns) VALUES ($values)";

            if ($mydb->query($sql) === TRUE) {
                return ["status" => "success", "message" => "New user registered."];
            } else {
                return ["status" => "error", "message" => $mydb->error];
            }

        case "login":
            $identifier = $request['identifier'];
            $password   = $request['password'];

            // Check if the user exists (by username or email)
            $sql    = "SELECT * FROM users WHERE username='$identifier' OR email='$identifier'";
            $result = $mydb->query($sql);
            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    // Generate a token and store it
                    $token = bin2hex(random_bytes(16)); // 32-char hex

                    $insertToken = "INSERT INTO tokens (token, username) VALUES (?, ?)";
                    $stmtToken   = $mydb->prepare($insertToken);
                    $stmtToken->bind_param("ss", $token, $user['username']);
                    $stmtToken->execute();

                    return [
                        "status"   => "success",
                        "message"  => "User authenticated.",
                        "username" => $user['username'],
                        "token"    => $token
                    ];
                } else {
                    return ["status" => "error", "message" => "Invalid password."];
                }
            } else {
                return ["status" => "error", "message" => "User not found."];
            }

        case "buy_stock":
            /*
                Expects:
                $request['data'] = [
                    'username'   => 'someuser',
                    'ticker'     => 'AAPL',
                    'quantity'   => 10,
                    'orderType'  => 'MARKET' or 'LIMIT',
                    'limitPrice' => 150.00 (only if orderType == 'LIMIT')
                ];
            */
            if (!isset($request['data']['username'], $request['data']['ticker'], $request['data']['quantity'], $request['data']['orderType'])) {
                return ["status" => "error", "message" => "Missing fields for buy_stock (username, ticker, quantity, orderType)."];
            }
        
            $username   = trim($request['data']['username']);
            $ticker     = strtoupper(trim($request['data']['ticker']));
            $quantity   = (int)$request['data']['quantity'];
            $orderType  = strtoupper(trim($request['data']['orderType']));
            $limitPrice = isset($request['data']['limitPrice']) ? (float)$request['data']['limitPrice'] : 0.0;
        
            // 1) Get current stock price from 'stocks' table
            $stmt = $mydb->prepare("SELECT price FROM stocks WHERE ticker = ? LIMIT 1");
            $stmt->bind_param("s", $ticker);
            $stmt->execute();
            $resPrice = $stmt->get_result();
            if ($resPrice->num_rows < 1) {
                return ["status" => "error", "message" => "Ticker '$ticker' not found in 'stocks' table."];
            }
            $rowPrice     = $resPrice->fetch_assoc();
            $currentPrice = (float)$rowPrice['price'];
        
            // 2) Get user balance
            $stmt = $mydb->prepare("SELECT balance FROM users WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $resUser = $stmt->get_result();
            if ($resUser->num_rows < 1) {
                return ["status" => "error", "message" => "User '$username' not found in 'users' table."];
            }
            $rowUser  = $resUser->fetch_assoc();
            $userBal  = (float)$rowUser['balance'];
        
            // 3) Market or Limit?
            if ($orderType === 'MARKET') {
                // -- MARKET BUY --
                $totalCost = $currentPrice * $quantity;
                if ($userBal < $totalCost) {
                    return ["status" => "error", "message" => "Insufficient balance for Market Buy: Need $totalCost, have $userBal."];
                }
        
                // Deduct balance
                $newBal = $userBal - $totalCost;
                $stmt   = $mydb->prepare("UPDATE users SET balance = ? WHERE username = ?");
                $stmt->bind_param("ds", $newBal, $username);
                $stmt->execute();
        
                // (Optional) Insert a record in an 'orders' or 'transactions' table
                // Example:
                // $stmt = $mydb->prepare("INSERT INTO orders (username, ticker, quantity, price, order_type, created_at)
                //                        VALUES (?, ?, ?, ?, 'MARKET_BUY', NOW())");
                // $stmt->bind_param("ssid", $username, $ticker, $quantity, $currentPrice);
                // $stmt->execute();
        
                return ["status" => "success", "message" => "Market Buy executed successfully."];
        
            } elseif ($orderType === 'LIMIT') {
                // -- LIMIT BUY --
                // Decide if you want to reserve funds now or only check upon execution.
                // Example: Reserve them now
                $totalCost = $limitPrice * $quantity;
                if ($userBal < $totalCost) {
                    return ["status" => "error", "message" => "Insufficient balance for Limit Buy: Need $totalCost, have $userBal."];
                }
        
                // Deduct or "hold" the balance
                $newBal = $userBal - $totalCost;
                $stmt   = $mydb->prepare("UPDATE users SET balance = ? WHERE username = ?");
                $stmt->bind_param("ds", $newBal, $username);
                $stmt->execute();
        
                // Insert the limit order in a 'limit_orders' table
                // (You must create this table if you haven't yet)
                $stmt = $mydb->prepare("
                    INSERT INTO limit_orders (username, ticker, limit_price, quantity, is_buy, created_at)
                    VALUES (?, ?, ?, ?, 1, NOW())
                ");
                $stmt->bind_param("ssdi", $username, $ticker, $limitPrice, $quantity);
                $stmt->execute();
        
                return ["status" => "success", "message" => "Limit Buy placed (pending execution)."];
            } else {
                return ["status" => "error", "message" => "Invalid orderType for buy_stock. Must be 'MARKET' or 'LIMIT'."];
            }
        
            break; // end buy_stock
        
        
        case "sell_stock":
            /*
                Expects:
                $request['data'] = [
                    'username'   => 'someuser',
                    'ticker'     => 'AAPL',
                    'quantity'   => 10,
                    'orderType'  => 'MARKET' or 'LIMIT',
                    'limitPrice' => 150.00 (only if orderType == 'LIMIT')
                ];
            */
            if (!isset($request['data']['username'], $request['data']['ticker'], $request['data']['quantity'], $request['data']['orderType'])) {
                return ["status" => "error", "message" => "Missing fields for sell_stock (username, ticker, quantity, orderType)."];
            }
        
            $username   = trim($request['data']['username']);
            $ticker     = strtoupper(trim($request['data']['ticker']));
            $quantity   = (int)$request['data']['quantity'];
            $orderType  = strtoupper(trim($request['data']['orderType']));
            $limitPrice = isset($request['data']['limitPrice']) ? (float)$request['data']['limitPrice'] : 0.0;
        
            // 1) Get current stock price
            $stmt = $mydb->prepare("SELECT price FROM stocks WHERE ticker = ? LIMIT 1");
            $stmt->bind_param("s", $ticker);
            $stmt->execute();
            $resPrice = $stmt->get_result();
            if ($resPrice->num_rows < 1) {
                return ["status" => "error", "message" => "Ticker '$ticker' not found in 'stocks' table."];
            }
            $rowPrice     = $resPrice->fetch_assoc();
            $currentPrice = (float)$rowPrice['price'];
        
            // 2) Get user balance
            $stmt = $mydb->prepare("SELECT balance FROM users WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $resUser = $stmt->get_result();
            if ($resUser->num_rows < 1) {
                return ["status" => "error", "message" => "User '$username' not found in 'users' table."];
            }
            $rowUser = $resUser->fetch_assoc();
            $userBal = (float)$rowUser['balance'];
        
            // (Optional) Check if user actually owns the shares if you have a 'portfolio' table
        
            // 3) Market or Limit?
            if ($orderType === 'MARKET') {
                // -- MARKET SELL --
                $totalProceeds = $currentPrice * $quantity;
                $newBal        = $userBal + $totalProceeds;
        
                // Update user balance
                $stmt = $mydb->prepare("UPDATE users SET balance = ? WHERE username = ?");
                $stmt->bind_param("ds", $newBal, $username);
                $stmt->execute();
        
                // (Optional) Insert record in 'orders' or 'transactions'
                // $stmt = $mydb->prepare("INSERT INTO orders (username, ticker, quantity, price, order_type, created_at)
                //                        VALUES (?, ?, ?, ?, 'MARKET_SELL', NOW())");
                // $stmt->bind_param("ssid", $username, $ticker, $quantity, $currentPrice);
                // $stmt->execute();
        
                return ["status" => "success", "message" => "Market Sell executed successfully."];
        
            } elseif ($orderType === 'LIMIT') {
                // -- LIMIT SELL --
                // Typically, we do NOT hold or deduct anything from the user’s balance for a sell limit.
                // But you might want to confirm the user has enough shares in their portfolio.
        
                $stmt = $mydb->prepare("
                    INSERT INTO limit_orders (username, ticker, limit_price, quantity, is_buy, created_at)
                    VALUES (?, ?, ?, ?, 0, NOW())
                ");
                $stmt->bind_param("ssdi", $username, $ticker, $limitPrice, $quantity);
                $stmt->execute();
        
                return ["status" => "success", "message" => "Limit Sell placed (pending execution)."];
            } else {
                return ["status" => "error", "message" => "Invalid orderType for sell_stock. Must be 'MARKET' or 'LIMIT'."];
            }
        
            break; // end sell_stock

        case "verifyToken":
            if (!isset($request['token'])) {
                return ["status" => "error", "message" => "No token provided"];
            }
            $token = $request['token'];

            $sqlToken = "SELECT username FROM tokens WHERE token = ? LIMIT 1";
            $stmt     = $mydb->prepare($sqlToken);
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
                $stmt     = $mydb->prepare($sqlToken);
                $stmt->bind_param("s", $token);
                $stmt->execute();
                $resToken = $stmt->get_result();
            
                if ($resToken->num_rows < 1) {
                    return ["status" => "error", "message" => "Invalid token"];
                }
            
                $row      = $resToken->fetch_assoc();
                $username = $row['username'];
            
                // users table for email
                $sqlEmail = "SELECT email FROM users WHERE username = ? LIMIT 1";
                $stmt2    = $mydb->prepare($sqlEmail);
                $stmt2->bind_param("s", $username);
                $stmt2->execute();
                $resEmail = $stmt2->get_result();
            
                if ($resEmail->num_rows < 1) {
                    return ["status" => "error", "message" => "User not found"];
                }
            
                $rowEmail = $resEmail->fetch_assoc();
                return [
                    "status" => "success",
                    "email"  => $rowEmail['email']
                ];

        case "verifyAndGetBalance":
            // 1) Check if a token was provided
            $token = $request['token'] ?? '';
            if (!$token) {
                return ["status" => "error", "message" => "No token provided"];
            }
        
            // 2) Verify the token in 'tokens' table
            $sqlToken = "SELECT username FROM tokens WHERE token = ? LIMIT 1";
            $stmt     = $mydb->prepare($sqlToken);
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $resToken = $stmt->get_result();
        
            if ($resToken->num_rows < 1) {
                // Token not found => invalid
                return ["status" => "error", "message" => "Invalid token"];
            }
        
            // 3) Get the username from the tokens table
            $row      = $resToken->fetch_assoc();
            $username = $row['username'];
        
            // 4) Now retrieve the user's balance from 'users' table
            $sqlBalance = "SELECT balance FROM users WHERE username = ? LIMIT 1";
            $stmt2      = $mydb->prepare($sqlBalance);
            $stmt2->bind_param("s", $username);
            $stmt2->execute();
            $resBal = $stmt2->get_result();
        
            if ($resBal->num_rows < 1) {
                // If no user row found, return error
                return ["status" => "error", "message" => "User not found"];
            }
        
            // 5) Get the balance
            $rowBal  = $resBal->fetch_assoc();
            $balance = $rowBal['balance'];
        
            // 6) Return a success array with balance (and username if needed)
            return [
                "status"   => "success",
                "username" => $username,
                "balance"  => $balance
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