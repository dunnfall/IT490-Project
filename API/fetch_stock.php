<?php
require_once "/home/dmz/IT490-Project/rabbitMQLib.inc";
require_once "/home/dmz/IT490-Project/RabbitDMZ.ini";
require_once "/home/dmz/IT490-Project/testRabbitMQ.ini";

function processStockRequest($request)
{
    if (!isset($request['data']['ticker'])) {
        return ["status" => "error", "message" => "No ticker provided"];
    }

    $ticker = strtoupper(trim($request['data']['ticker']));

    function getStockFromAPI($ticker) {
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
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            error_log("API cURL Error: " . $err);
            return null;
        }

        return json_decode($response, true);
    }

    $stockData = getStockFromAPI($ticker);

    if (!$stockData || !isset($stockData['body']) || empty($stockData['body'])) {
        error_log("Stock not found in API response.");
        return ["status" => "error", "message" => "Ticker not found in the API response"];
    }

    $foundStock = $stockData['body'][0] ?? null;

    if ($foundStock) {
        $dataToStore = [
            'action' => 'store_stock',
            'data' => [
                'ticker' => $ticker,
                'company' => $foundStock['displayName'] ?? 'N/A',
                'price' => floatval($foundStock['regularMarketOpen'] ?? 0),
                'timestamp' => date("Y-m-d H:i:s"),
                '52weekchangepercent' => $foundStock['fiftyTwoWeekChangePercent'] ?? null,
                '52weekhigh' => $foundStock['fiftyTwoWeekHigh'] ?? null,
                '52weeklow' => $foundStock['fiftyTwoWeekLow'] ?? null,
                'marketcap' => $foundStock['marketCap'] ?? null,
                'region' => $foundStock['region'] ?? 'N/A',
                'currency' => $foundStock['currency'] ?? 'N/A'
            ]
        ];

        $databaseClient = new rabbitMQClient("/home/dmz/IT490-Project/testRabbitMQ.ini", "testServer");
        $response = $databaseClient->send_request($dataToStore);

        return $response;
    }
}

// Start the DMZ Consumer
$server = new rabbitMQServer("/home/dmz/IT490-Project/RabbitDMZ.ini", "dmzServer");
$server->process_requests("processStockRequest");
