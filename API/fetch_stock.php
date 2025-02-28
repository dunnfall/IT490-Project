<?php
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";
include 'config.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $ticker = $_POST['ticker'];

    // This is used to fetch the stock info from API
    function getStockData($ticker) {
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://yahoo-finance15.p.rapidapi.com/api/v1/markets/stock/modules?ticker=$ticker&module=asset-profile",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "x-rapidapi-host: yahoo-finance15.p.rapidapi.com",
                "x-rapidapi-key: " . RAPIDAPI_KEY
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            return ["error" => "cURL Error: " . $err];
        } else {
            return json_decode($response, true);
        }
    }

    $stockData = getStockData($ticker);

    // SPecifiy the data we can change this to get more data if we need for now just this.
    $companyName = isset($stockData["asset-profile"]["companyName"]) ? $stockData["asset-profile"]["companyName"] : "Unknown";
    $price = isset($stockData["asset-profile"]["price"]) ? $stockData["asset-profile"]["price"] : 0;
    $timestamp = date("Y-m-d H:i:s");

    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

    $data = [
        'action' => 'store_stock',
        'table' => 'stocks',
        'data' => [
            'ticker' => $ticker,
            'company' => $companyName,
            'price' => $price,
            'timestamp' => $timestamp
        ]
    ];

    // Send the request to RabbitMQ
    $response = $client->send_request($data);

    // Handle Response
    if ($response['status'] === 'success') {
        echo json_encode(["message" => "Stock data stored successfully"]);
    } else {
        echo json_encode(["error" => "Failed to store stock data", "details" => $response['message']]);
    }
}
?>