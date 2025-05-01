<?php
require_once "rabbitMQLib.inc";
require_once "testRabbitMQ_response.ini";

function processRequest($request)
{
    $mydb = new mysqli("localhost", "testUser", "12345", "it490db");
    if ($mydb->connect_error) {
        return ["status" => "error", "message" => "Database connection failed: " . $mydb->connect_error];
    }

    if (!isset($request['action'])) {
        return ["status" => "error", "message" => "Invalid request format."];
    }

    switch ($request['action']) {
        /*case "get_stock":
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

            error_log("Fetching ticker for user: IT DID NOT FINISH GET_STOCK " . $ticker);*/

            case "retrieve_stock":
                if (!isset($request['data']['ticker'])) {
                    return ["status" => "error", "message" => "Ticker not provided"];
                }
            
                $ticker = strtoupper(trim($request['data']['ticker']));
                error_log("Checking stock in database: " . $ticker);
            
                // Check if stock exists in the database
                $query = "SELECT * FROM stocks WHERE ticker = ?";
                $stmt = $mydb->prepare($query);
                $stmt->bind_param("s", $ticker);
                $stmt->execute();
                $result = $stmt->get_result();
            
                if ($result->num_rows > 0) {
                    error_log("Stock found in DB: " . $ticker);
                    $stockData = $result->fetch_assoc();
                    $stmt->close(); 
                    return ["status" => "success", "data" => $stockData];
                }
                $stmt->close(); 
            
                // If stock is not found, request from DMZ server
                error_log("Stock not found in DB, requesting from DMZ: " . $ticker);
                $dmzClient = new rabbitMQClient("/home/rabbitdb/IT490-Project/RabbitDMZ.ini", "dmzServer");
                $dmzRequest = ['type' => 'fetch_stock', 'data' => ['ticker' => $ticker]]; 
                $dmzResponse = $dmzClient->send_request($dmzRequest);
            
                if (!$dmzResponse || $dmzResponse['status'] !== 'success') {
                    error_log("ERROR: Failed to fetch stock from DMZ!");
                    return ["status" => "error", "message" => "Failed to fetch stock data from API."];
                }
            
                // Step 3: Extract stock data from DMZ response
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
            
                // Insert new stock into database
                error_log("Inserting new stock into DB: " . $ticker);
                $insertQuery = "INSERT INTO stocks (ticker, company, price, timestamp, `52weekchangepercent`, `52weekhigh`, `52weeklow`, marketcap, region, currency) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                if ($stmt = $mydb->prepare($insertQuery)) {
                    $stmt->bind_param("ssdsdddsss", $ticker, $company, $price, $timestamp, $weekChange, $weekHigh, $weekLow, $marketCap, $region, $currency);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    error_log("ERROR: Database insert failed!");
                    return ["status" => "error", "message" => "Database insert error: " . $mydb->error];
                }
            
                error_log("Stock retrieved from DMZ and inserted: " . json_encode($stockData));
            
                // Step 6: Return success response with newly inserted data
                return ["status" => "success", "data" => json_decode(json_encode($stockData), true)];
            

            

        /*case "get_balance":
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
            }*/

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

        case "verifyAndGetPhone":
            $token = $request['token'] ?? '';
            if (!$token) {
                return ["status" => "error", "message" => "No token provided"];
            }

            // Look‑up username from the token
            $sqlToken = "SELECT username FROM tokens WHERE token = ? LIMIT 1";
            $stmt = $mydb->prepare($sqlToken);
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $resToken = $stmt->get_result();

            if ($resToken->num_rows < 1) {
                return ["status" => "error", "message" => "Invalid token"];
            }

            $username = $resToken->fetch_assoc()['username'];

            // Fetch phone number and carrier from users
            $sqlPhone = "SELECT phone, carrier FROM users WHERE username = ? LIMIT 1";
            $stmt2 = $mydb->prepare($sqlPhone);
            $stmt2->bind_param("s", $username);
            $stmt2->execute();
            $resPhone = $stmt2->get_result();

            // Check if phone exists and is not empty
            $phoneRow = $resPhone->fetch_assoc();
            $carrier = $phoneRow['carrier'] ?? '';
            if ($resPhone->num_rows < 1 || !$phoneRow || !$phoneRow['phone']) {
                return ["status" => "error", "message" => "Phone number not found"];
            }

            $phone = $phoneRow['phone'];

            return [
                "status"  => "success",
                "phone"   => $phone,
                "carrier" => $carrier
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

        case "buy_stock":
            // 1) Validate input
            if (!isset($request['data']['username'], $request['data']['ticker'], $request['data']['quantity'])) {
                return ["status" => "error", "message" => "Missing buy_stock fields (username, ticker, quantity)."];
            }
            $username   = trim($request['data']['username']);
            $ticker     = strtoupper(trim($request['data']['ticker']));
            $quantity   = (int)$request['data']['quantity'];
            $orderType  = strtoupper($request['data']['orderType'] ?? 'MARKET');
            $limitPrice = (float)($request['data']['limitPrice'] ?? 0);
        
            // 2) Get user info
            $stmt = $mydb->prepare("SELECT id, balance FROM users WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $resUser = $stmt->get_result();
            if ($resUser->num_rows < 1) {
                return ["status" => "error", "message" => "User not found"];
            }
            $rowUser  = $resUser->fetch_assoc();
            $userId   = (int)$rowUser['id'];
            $balance  = (float)$rowUser['balance'];
        
            // 3) Check local DB for the stock
            $stmt = $mydb->prepare("SELECT id, price FROM stocks WHERE ticker = ? LIMIT 1");
            $stmt->bind_param("s", $ticker);
            $stmt->execute();
            $resStock = $stmt->get_result();
        
            $stockId      = null;
            $currentPrice = 0.0;
        
            if ($resStock->num_rows > 0) {
                // Found locally
                $rowStock     = $resStock->fetch_assoc();
                $stockId      = (int)$rowStock['id'];
                $currentPrice = (float)$rowStock['price'];
            } else {
                // 4) Not found => fetch from DMZ
                $stmt->close(); // close prior statement
                error_log("Ticker '$ticker' not found locally. Requesting from DMZ...");
        
                // Make sure you have the correct path for rabbitMQLib.inc if not already included
                require_once "rabbitMQLib.inc";
                $dmzClient = new rabbitMQClient("/home/rabbitdb/IT490-Project/RabbitDMZ.ini", "dmzServer");
                // Must match how your DMZ expects requests
                $dmzRequest = [
                    'type' => 'fetch_stock',
                    'data' => ['ticker' => $ticker]
                ];
                $dmzResponse = $dmzClient->send_request($dmzRequest);
        
                if (!$dmzResponse || $dmzResponse['status'] !== 'success') {
                    error_log("ERROR: DMZ fetch for '$ticker' failed");
                    return ["status"=>"error","message"=>"Unable to fetch '$ticker' from DMZ."];
                }
        
                // Insert into 'stocks'
                $stockData = $dmzResponse['data'];
                $company   = trim($stockData['company'] ?? '');
                $price     = (float)($stockData['price'] ?? 0);
                $timestamp = date("Y-m-d H:i:s");
                $weekChange= $stockData['52weekchangepercent'] ?? null;
                $weekHigh  = $stockData['52weekhigh'] ?? null;
                $weekLow   = $stockData['52weeklow']  ?? null;
                $marketCap = $stockData['marketcap']  ?? null;
                $region    = $stockData['region']     ?? 'N/A';
                $currency  = $stockData['currency']   ?? 'N/A';
        
                $insQ = "
                    INSERT INTO stocks (ticker, company, price, timestamp,
                                        `52weekchangepercent`, `52weekhigh`, `52weeklow`,
                                        marketcap, region, currency)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                $stmt2 = $mydb->prepare($insQ);
                if (!$stmt2) {
                    error_log("DB insert prepare failed: " . $mydb->error);
                    return ["status"=>"error","message"=>"Database insert failed."];
                }
                $stmt2->bind_param("ssdsdddsss",
                    $ticker, $company, $price, $timestamp,
                    $weekChange, $weekHigh, $weekLow,
                    $marketCap, $region, $currency
                );
                $stmt2->execute();
                $newId = $stmt2->insert_id;
                $stmt2->close();
        
                // Now we have a new stock row
                $stockId      = $newId;
                $currentPrice = $price;
            }
        
            // 5) Now proceed with the normal buy logic
            if ($orderType === 'MARKET') {
                $totalCost = $currentPrice * $quantity;
                if ($balance < $totalCost) {
                    return ["status" => "error", "message" => "Insufficient balance for Market Buy."];
                }
        
                // Deduct user balance
                $newBalance = $balance - $totalCost;
                $stmt = $mydb->prepare("UPDATE users SET balance = ? WHERE id = ?");
                $stmt->bind_param("di", $newBalance, $userId);
                $stmt->execute();
        
                // Insert into user_stocks
                $stmt = $mydb->prepare("
                    INSERT INTO user_stocks (user_id, stock_id, purchase_price, quantity, purchase_date)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("iidi", $userId, $stockId, $currentPrice, $quantity);
                $stmt->execute();
        
                return [
                    "status"    => "success",
                    "message"   => "Market Buy executed.",
                    "newBalance"=> (float)$newBalance
                ];
            }
            elseif ($orderType === 'LIMIT') {
                // ...
                return [
                    "status"    => "success",
                    "message"   => "Limit Buy placed (pending).",
                    "newBalance"=> (float)$balance
                ];
            }
            else {
                return ["status" => "error", "message" => "Invalid orderType for buy_stock."];
            }
        
            break;
        
        case "sell_stock":
            // 1) Validate input
            if (!isset($request['data']['username'], $request['data']['ticker'], $request['data']['quantity'])) {
                return ["status" => "error", "message" => "Missing sell_stock fields (username, ticker, quantity)."];
            }
            $username  = trim($request['data']['username']);
            $ticker    = strtoupper(trim($request['data']['ticker']));
            $quantity  = (int)$request['data']['quantity'];
            $orderType = strtoupper($request['data']['orderType'] ?? 'MARKET');
        
            // 2) Get user info
            $stmt = $mydb->prepare("SELECT id, balance FROM users WHERE username = ? LIMIT 1");
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $resUser = $stmt->get_result();
            if ($resUser->num_rows < 1) {
                return ["status" => "error", "message" => "User not found"];
            }
            $rowUser  = $resUser->fetch_assoc();
            $userId   = (int)$rowUser['id'];
            $balance  = (float)$rowUser['balance'];
        
            // 3) Check local DB for the stock
            $stmt = $mydb->prepare("SELECT id, price FROM stocks WHERE ticker = ? LIMIT 1");
            $stmt->bind_param("s", $ticker);
            $stmt->execute();
            $resStock = $stmt->get_result();
        
            $stockId      = null;
            $currentPrice = 0.0;
        
            if ($resStock->num_rows > 0) {
                // Found locally
                $rowStock     = $resStock->fetch_assoc();
                $stockId      = (int)$rowStock['id'];
                $currentPrice = (float)$rowStock['price'];
            } else {
                // 4) Not found => fetch from DMZ
                $stmt->close();
                error_log("Ticker '$ticker' not found locally. Requesting from DMZ...");
        
                require_once "rabbitMQLib.inc";
                $dmzClient = new rabbitMQClient("/home/rabbitdb/IT490-Project/RabbitDMZ.ini", "dmzServer");
                $dmzRequest = [
                    'type' => 'fetch_stock',
                    'data' => ['ticker' => $ticker]
                ];
                $dmzResponse = $dmzClient->send_request($dmzRequest);
        
                if (!$dmzResponse || $dmzResponse['status'] !== 'success') {
                    error_log("ERROR: DMZ fetch for '$ticker' failed");
                    return ["status"=>"error","message"=>"Unable to fetch '$ticker' from DMZ."];
                }
        
                // Insert into 'stocks'
                $stockData = $dmzResponse['data'];
                $company   = trim($stockData['company'] ?? '');
                $price     = (float)($stockData['price'] ?? 0);
                $timestamp = date("Y-m-d H:i:s");
                $weekChange= $stockData['52weekchangepercent'] ?? null;
                $weekHigh  = $stockData['52weekhigh'] ?? null;
                $weekLow   = $stockData['52weeklow']  ?? null;
                $marketCap = $stockData['marketcap']  ?? null;
                $region    = $stockData['region']     ?? 'N/A';
                $currency  = $stockData['currency']   ?? 'N/A';
        
                $insQ = "
                    INSERT INTO stocks (ticker, company, price, timestamp,
                                        `52weekchangepercent`, `52weekhigh`, `52weeklow`,
                                        marketcap, region, currency)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";
                $stmt2 = $mydb->prepare($insQ);
                if (!$stmt2) {
                    error_log("DB insert prepare failed: " . $mydb->error);
                    return ["status"=>"error","message"=>"Database insert failed."];
                }
                $stmt2->bind_param("ssdsdddsss",
                    $ticker, $company, $price, $timestamp,
                    $weekChange, $weekHigh, $weekLow,
                    $marketCap, $region, $currency
                );
                $stmt2->execute();
                $newId = $stmt2->insert_id;
                $stmt2->close();
        
                $stockId      = $newId;
                $currentPrice = $price;
            }
        
            // 5) Now proceed with the normal sell logic
            if ($orderType === 'MARKET') {
                $totalProceeds = $currentPrice * $quantity;
                $newBalance    = $balance + $totalProceeds;
        
                // Update user balance
                $stmt = $mydb->prepare("UPDATE users SET balance = ? WHERE id = ?");
                $stmt->bind_param("di", $newBalance, $userId);
                $stmt->execute();
        
                // Subtract from user_stocks
                $stmt = $mydb->prepare("
                    SELECT id, quantity
                    FROM user_stocks
                    WHERE user_id = ? AND stock_id = ?
                    ORDER BY purchase_date ASC
                    LIMIT 1
                ");
                $stmt->bind_param("ii", $userId, $stockId);
                $stmt->execute();
                $resStockRow = $stmt->get_result();
                if ($resStockRow->num_rows < 1) {
                    return ["status" => "error", "message" => "No holdings found for that stock."];
                }
        
                $rowHold   = $resStockRow->fetch_assoc();
                $rowHoldId = (int)$rowHold['id'];
                $oldQty    = (int)$rowHold['quantity'];
        
                if ($oldQty < $quantity) {
                    return ["status" => "error", "message" => "Not enough shares to sell."];
                }
        
                $newQty = $oldQty - $quantity;
                if ($newQty > 0) {
                    $stmt = $mydb->prepare("UPDATE user_stocks SET quantity = ? WHERE id = ?");
                    $stmt->bind_param("ii", $newQty, $rowHoldId);
                    $stmt->execute();
                } else {
                    $stmt = $mydb->prepare("DELETE FROM user_stocks WHERE id = ?");
                    $stmt->bind_param("i", $rowHoldId);
                    $stmt->execute();
                }
        
                return [
                    "status"    => "success",
                    "message"   => "Market Sell executed.",
                    "newBalance"=> (float)$newBalance
                ];
            }
            elseif ($orderType === 'LIMIT') {
                // ...
                return [
                    "status"    => "success",
                    "message"   => "Limit Sell placed (pending).",
                    "newBalance"=> (float)$balance
                ];
            }
            else {
                return ["status" => "error", "message" => "Invalid orderType."];
            }
        
            break;

        case "verifyAndGetBalanceAndPortfolio":
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
                return ["status" => "error", "message" => "Invalid token"];
            }
        
            // 3) Get the username
            $row      = $resToken->fetch_assoc();
            $username = $row['username'];
        
            // 4) Retrieve the user's balance from 'users' table
            $sqlBalance = "SELECT id, balance FROM users WHERE username = ? LIMIT 1";
            $stmt2      = $mydb->prepare($sqlBalance);
            $stmt2->bind_param("s", $username);
            $stmt2->execute();
            $resBal = $stmt2->get_result();
        
            if ($resBal->num_rows < 1) {
                return ["status" => "error", "message" => "User not found"];
            }
        
            $rowBal   = $resBal->fetch_assoc();
            $userId   = (int)$rowBal['id'];
            $balance  = (float)$rowBal['balance'];
        
            // 5) Now retrieve the user's portfolio from user_stocks
            //    We'll join with stocks to get ticker, current price, etc.
            $sqlPortfolio = "
                SELECT us.quantity, us.purchase_price, us.purchase_date,
                        s.ticker, s.price AS current_price
                FROM user_stocks us
                JOIN stocks s ON us.stock_id = s.id
                WHERE us.user_id = ?
                ORDER BY us.purchase_date DESC
            ";
            $stmt3 = $mydb->prepare($sqlPortfolio);
            $stmt3->bind_param("i", $userId);
            $stmt3->execute();
            $resPortfolio = $stmt3->get_result();
        
            $portfolioData = [];
            while ($rowPort = $resPortfolio->fetch_assoc()) {
                $portfolioData[] = [
                    "ticker"         => $rowPort["ticker"],
                    "quantity"       => (int)$rowPort["quantity"],
                    "purchase_price" => (float)$rowPort["purchase_price"],
                    "purchase_date"  => $rowPort["purchase_date"],
                    "current_price"  => (float)$rowPort["current_price"]
                ];
            }
        
            // 6) Return a single response with both balance + portfolio
            return [
                "status"   => "success",
                "username" => $username,
                "balance"  => $balance,
                "portfolio"=> $portfolioData
            ];
        // Optionally add a "logout" case to remove token if needed
        // case "logout":
        //    ...
            break;

            case "add_to_watchlist":
                // 1) validate token & ticker
                $token  = $request['token'] ?? '';
                $ticker = strtoupper(trim($request['data']['ticker'] ?? ''));
                if (!$token || !$ticker) {
                    return ["status"=>"error","message"=>"Token & ticker required"];
                }
                // 2) lookup user
                $stmt = $mydb->prepare("SELECT username FROM tokens WHERE token=? LIMIT 1");
                $stmt->bind_param("s",$token);
                $stmt->execute();
                $res = $stmt->get_result();
                if (!$res->num_rows) {
                    return ["status"=>"error","message"=>"Invalid token"];
                }
                $user = $res->fetch_assoc()['username'];
                // 3) insert watchlist entry (ignore duplicates)
                $ins = $mydb->prepare("
                  INSERT IGNORE INTO watchlist (username, stock_symbol)
                  VALUES (?, ?)
                ");
                $ins->bind_param("ss",$user,$ticker);
                if (!$ins->execute()) {
                    return ["status"=>"error","message"=>$mydb->error];
                }
                return ["status"=>"success","message"=>"$ticker added"];
        
            case "get_watchlist":
                $token = $request['token'] ?? '';
                if (!$token) {
                    return ["status"=>"error","message"=>"No token provided"];
                }
                $stmt = $mydb->prepare("SELECT username FROM tokens WHERE token=? LIMIT 1");
                $stmt->bind_param("s",$token);
                $stmt->execute();
                $res = $stmt->get_result();
                if (!$res->num_rows) {
                    return ["status"=>"error","message"=>"Invalid token"];
                }
                $user = $res->fetch_assoc()['username'];
                $q = $mydb->prepare("
                  SELECT id, stock_symbol
                    FROM watchlist
                   WHERE username=?
                ORDER BY id DESC
                ");
                $q->bind_param("s",$user);
                $q->execute();
                $r = $q->get_result();
                $out = [];
                while ($row = $r->fetch_assoc()) {
                    $out[] = $row;
                }
                return ["status"=>"success","data"=>$out];
        
            case "remove_from_watchlist":
                $token = $request['token'] ?? '';
                $id    = intval($request['data']['id'] ?? 0);
                if (!$token || !$id) {
                    return ["status"=>"error","message"=>"Token & watchlist ID required"];
                }
                $stmt = $mydb->prepare("SELECT username FROM tokens WHERE token=? LIMIT 1");
                $stmt->bind_param("s",$token);
                $stmt->execute();
                $res = $stmt->get_result();
                if (!$res->num_rows) {
                    return ["status"=>"error","message"=>"Invalid token"];
                }
                $user = $res->fetch_assoc()['username'];
                $del = $mydb->prepare("
                  DELETE FROM watchlist
                   WHERE id=? AND username=?
                ");
                $del->bind_param("is",$id,$user);
                if (!$del->execute()) {
                    return ["status"=>"error","message"=>$mydb->error];
                }
                return ["status"=>"success","message"=>"Removed"];
        
            default:
                return ["status"=>"error","message"=>"Unknown action: {$request['action']}"];
        }
    }   


// Start the RabbitMQ server
$server = new rabbitMQServer("testRabbitMQ_response.ini", "responseServer");
$server->process_requests("processRequest");
?>
