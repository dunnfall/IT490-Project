<?php

require_once "rabbitMQLib.inc";

function fetchStockData($ticker)
{
    $client = new rabbitMQClient("testRabbitMQ_response.ini", "responseServer");
    $request = [
        'action' => 'get_stock',
        'data' => ['ticker' => $ticker]
    ];


    $response = $client->send_request($request);

    if($response['status'] === "success") {
        return $response['data'];
    } else {
        return null;
    }
}

function getRecommendation($stock)
{
    if (!stock) {
        return "Error - No data availble for recommendation.";
    }

    $price = $stock["price"];
    $change = $stock["52weekchangepercent"];
    $high = $stock["52weekhigh"];
    $low = $stock["52weeklow"];
    $marketcap = $stock["marketcap"];

    $price_position = ($price - $low) / ($high - $low) * 100;

    if($change > 50 && $price_position > 80) {
        return "BUY - Strong uptrend and near breakout zone.";
    } elseif ($change < -20) {
        return "SELL - Heavy decline in past year.";
    } elseif ($price_position < 30) {
        return "HOLD - Possible undervaluation, but wait for confirmation.";
    } else {
        return "WAIT - No strong signal yet.";
    }
}


if (isset($_GET['ticker'])) {
    $searchedTicker = strtoupper(trim($_GET['ticker']));
    $stockData = fetchStockData($searchedTicker);
    $recommendation = getRecommendation($stockData); 

    echo "<h2>Stock Recommendation for $searchedTicker</h2>";
    echo "<p>$recommendation</p>";
} else {
    echo "<h2>Error</h2>";
    echo "<p>Please provide a stock ticker in the search.</p>";
}
?>