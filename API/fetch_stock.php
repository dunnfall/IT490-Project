<?php
require_once "/home/dmz/IT490-Project/rabbitMQLib.inc";

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function fetchStockData($ticker) {
    $apiKey = 'c445a9ff73msh1ba778fa2e6e77bp1681cbjsn1e7785aa5761';
    $apiUrl = "https://yahoo-finance15.p.rapidapi.com/api/v1/markets/stock/quotes?ticker=" . urlencode($ticker);

    error_log("Fetching stock data from API: " . $apiUrl);

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            "x-rapidapi-host: yahoo-finance15.p.rapidapi.com",
            "x-rapidapi-key: $apiKey"
        ],
    ]);

    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($httpCode !== 200 || !$response) {
        return ["status" => "error", "message" => "Failed to fetch stock data from API."];
    }

    $stockData = json_decode($response, true);
    
    if (!$stockData || empty($stockData['body'])) {
        return ["status" => "error", "message" => "No stock data found for ticker: $ticker"];
    }

    $foundStock = $stockData['body'][0] ?? null;
    if (!$foundStock) {
        return ["status" => "error", "message" => "Stock information not found in API response"];
    }

    return [
        "status" => "success",
        "data" => [
            "ticker" => $ticker,
            "company" => $foundStock['displayName'] ?? 'N/A',
            "price" => floatval($foundStock['regularMarketOpen'] ?? 0),
            "timestamp" => date("Y-m-d H:i:s"),
            "52weekchangepercent" => $foundStock['fiftyTwoWeekChangePercent'] ?? null,
            "52weekhigh" => $foundStock['fiftyTwoWeekHigh'] ?? null,
            "52weeklow" => $foundStock['fiftyTwoWeekLow'] ?? null,
            "marketcap" => $foundStock['marketCap'] ?? null,
            "region" => $foundStock['region'] ?? 'N/A',
            "currency" => $foundStock['currency'] ?? 'N/A'
        ]
    ];
}

function processRequest($request) {
    if (!isset($request['type'])) {
        return ["status" => "error", "message" => "No request type specified"];
    }

    switch ($request['type']) {
        case "fetch_stock":
            if (!isset($request['data']['ticker'])) {
                return ["status" => "error", "message" => "Ticker symbol is required"];
            }
            $ticker = strtoupper(trim($request['data']['ticker']));
            error_log(message: "Fetching ticker for user: " . $ticker);
            return fetchStockData($ticker);

        default:
            return ["status" => "error", "message" => "Invalid request type"];
    }
}

// Start the RabbitMQ server on the DMZ
$server = new rabbitMQServer("/home/dmz/IT490-Project/RabbitDMZ.ini", "dmzServer");
$server->process_requests("processRequest");
?>