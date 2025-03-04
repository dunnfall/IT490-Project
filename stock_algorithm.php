<?php

require_once "rabbitMQLib.inc"; 

// Function to send a request to stock_consumer.php via RabbitMQ
function fetchStockData($ticker)
{
    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $request = [
        'action' => 'get_stock',
        'data' => ['ticker' => $ticker]
    ];
    
    $response = $client->send_request($request);

    if ($response['status'] === "success") {
        return $response['data']; // Return stock data
    } else {
        echo "Error fetching stock data: " . $response['message'] . "\n";
        return null;
    }
}

// Function to generate a recommendation based on stock data
function getRecommendation($stock)
{
    if (!$stock) {
        return "Error - No data available for recommendation.";
    }

    $price = $stock["price"];
    $change = $stock["52weekchangepercent"];
    $high = $stock["52weekhigh"];
    $low = $stock["52weeklow"];
    $marketcap = $stock["marketcap"];

    // Calculate current price position in 52-week range
    $price_position = ($price - $low) / ($high - $low) * 100;

    // Basic logic for recommendations
    if ($change > 50 && $price_position > 80) {
        return "BUY - Strong uptrend and near breakout zone.";
    } elseif ($change < -20) {
        return "SELL - Heavy decline in past year.";
    } elseif ($price_position < 30) {
        return "HOLD - Possible undervaluation, but wait for confirmation.";
    } else {
        return "WAIT - No strong signal yet.";
    }
}

// List of stock tickers to analyze
$tickers = ["MSFT", "TSM", "AAPL", "GOOG", "ASTS", "XOM", "RKLB", "PYPL", "TSLA", "CAT", "APD", "INTC", "BX", "CB", "ADI", "NKE", "DAL", "AZO", "EBAY", "CSGP", "AMZN", "IP", "EA", "FTV", "GME", "RIVN",]; 

echo "Stock Recommendations:\n";
foreach ($tickers as $ticker) {
    $stockData = fetchStockData($ticker);
    $recommendation = getRecommendation($stockData);
    echo "$ticker: $recommendation\n";
}

?>