<?php
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";

// 1) Check for token in cookie
$token = $_COOKIE['authToken'] ?? '';
if (!$token) {
    header("Location: login.html");
    exit();
}

// 2) Verify token with consumer
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
$request = ['action'=>'verifyToken','token'=>$token];
$response = $client->send_request($request);

// 3) If invalid, redirect
if (!isset($response['status']) || $response['status'] !== 'success') {
    header("Location: login.html");
    exit();
}

// If valid, we have $response['username']
$username = $response['username'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Stock Lookup</title>
    <?php require(__DIR__ . "/../partials/nav.php"); ?>
    
    <!-- Include Chart.js for Graph Rendering -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        async function searchStock() {
            let ticker = document.getElementById("ticker").value.trim().toUpperCase();
            let errorMessage = document.getElementById("errorMessage");
            let stockTable = document.getElementById("stockTable");
            let row = document.getElementById("stockRow");
            let stockDetails = document.getElementById("stockDetails");
            let recommendationContainer = document.getElementById("recommendationContainer");


            console.log("Elements Found:", {
                errorMessage, stockTable, row, stockDetails, recommendationContainer, recommendationText
            });

            if (!ticker) {
                errorMessage.textContent = "Please enter a valid ticker.";
                return;
            }

            errorMessage.textContent = "Fetching stock data...";
            stockTable.style.display = "none";
            stockDetails.style.display = "none";
            recommendationContainer.style.display = "none"; 

            try {
                let response = await fetch(`../API/stock_handler.php?ticker=${ticker}`);
                let data = await response.json();

                if (data.error) {
                    errorMessage.textContent = data.error;
                    return;
                }

                errorMessage.textContent = "";
                stockTable.style.display = "table";
                row.innerHTML = `<td>${data.ticker}</td>
                                 <td>${data.company}</td>
                                 <td>$${parseFloat(data.price).toFixed(2)}</td>
                                 <td>${data.timestamp}</td>
                                 <td><button onclick="showMoreInfo('${data.ticker}')">More Info</button></td>`;

                recommendationContainer.style.display = "block";
                document.getElementById("getRecommendationButton").setAttribute("data-ticker", ticker);

            } catch (error) {
                console.error("Fetch Error:", error);
                errorMessage.textContent = "Error fetching stock data.";
                stockTable.style.display = "none";
            }
        }

        async function getRecommendation() {
        let ticker = document.getElementById("getRecommendationButton").getAttribute("data-ticker");
        let recommendationText = document.getElementById("recommendationText");

            if (!ticker) {
                recommendationText.innerHTML = "Error: No stock selected.";
                return;
            }

            recommendationText.innerHTML = "Fetching recommendation...";

            try {
                let response = await fetch(`../backend/algorithm.php?ticker=${ticker}`);
                let recommendationTextData = await response.text();

                recommendationText.innerHTML = `<strong>Recommendation:</strong> ${recommendationTextData}`;
            } catch (error) {
                console.error("Fetch Error:", error);
                recommendationText.innerHTML = "Error fetching recommendation.";
            }
        }

        async function showMoreInfo(ticker) {
    let stockDetails = document.getElementById("stockDetails");
    let detailsContent = document.getElementById("detailsContent");
    let stockChartContainer = document.getElementById("stockChartContainer");
    stockDetails.style.display = "block";
    detailsContent.innerHTML = "Loading additional stock details...";

    try {
        let response = await fetch(`../API/stock_handler.php?ticker=${ticker}`);
        let data = await response.json();

        if (data.error) {
            detailsContent.innerHTML = "Stock details not available.";
            return;
        }

        let weekChange = data["52weekchangepercent"] ? data["52weekchangepercent"].toFixed(2) : "N/A";
        let weekChangeValue = parseFloat(data["52weekchangepercent"]) || 0; // Ensure it's a number

        detailsContent.innerHTML = `
            <p><strong>Ticker:</strong> ${data.ticker}</p>
            <p><strong>Company:</strong> ${data.company}</p>
            <p><strong>Price:</strong> $${parseFloat(data.price).toFixed(2)}</p>
            <p><strong>52-Week High:</strong> $${parseFloat(data['52weekhigh']).toFixed(2)}</p>
            <p><strong>52-Week Change (%):</strong> ${weekChange}%</p>
            <p><strong>52-Week Low:</strong> $${parseFloat(data['52weeklow']).toFixed(2)}</p>
            <p><strong>Market Cap:</strong> $${data.marketcap}</p>
            <p><strong>Region:</strong> ${data.region}</p>
            <p><strong>Currency:</strong> ${data.currency}</p>
        `;

        // Show the chart container
        stockChartContainer.style.display = "block";

        // Render the stock line chart
        renderStockChart(data['52weeklow'], data['52weekhigh'], weekChangeValue);
        
    } catch (error) {
        console.error("Fetch Error:", error);
        detailsContent.innerHTML = "Error loading stock details.";
    }
}


function renderStockChart(low, high, percentChange) {
    let ctx = document.getElementById('stockChart').getContext('2d');

    // Destroy existing chart if it exists (prevents duplication)
    if (window.stockChartInstance) {
        window.stockChartInstance.destroy();
    }

    window.stockChartInstance = new Chart(ctx, {
        type: 'line',  // Use a line graph
        data: {
            labels: ["52-Week Low", "52-Week High", "52-Week % Change"],
            datasets: [{
                label: "Stock Price Trends",
                data: [low, high, high * (1 + percentChange / 100)],  // Adjust percent change as a value
                borderColor: 'blue',  // Line color
                backgroundColor: 'rgba(0, 0, 255, 0.1)', // Light blue fill
                fill: true,  // Fill under the line
                tension: 0.3, // Smooth curve
                pointRadius: 5, // Circle points on the line
                pointBackgroundColor: ['blue', 'blue', 'red'], // Different color for % change point
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: false  // Keep values realistic
                }
            }
        }
    });
}

    </script>
</head>
<body>
    <div>
        <h2>Search for Stock Information</h2>
        <label for="ticker">Enter Stock Ticker:</label>
        <input type="text" id="ticker">
        <button onclick="searchStock()">Search</button>
    </div>

    <table id="stockTable" border="1" style="margin-top:10px; display:none;">
        <tr>
            <th>Ticker</th>
            <th>Company</th>
            <th>Price</th>
            <th>Last Updated</th>
            <th>More Info</th>
        </tr>
        <tr id="stockRow"></tr>
    </table>

    <div id="stockDetails" style="display:none; margin-top:10px; border:1px solid #ddd; padding:10px;">
        <h3>Stock Details</h3>
        <div id="detailsContent"></div>

        <!-- Chart Container -->
        <div id="stockChartContainer" style="display:none; margin-top:20px;">
            <h3>Stock Price Range (52 Weeks)</h3>
            <canvas id="stockChart"></canvas>
        </div>
    </div>

    <div id="recommendationContainer" style="display:none; margin-top:10px; border:1px solid #ddd; padding:10px;">
        <h3>Stock Recommendation</h3>
        <button id="getRecommendationButton" onclick="getRecommendation()">Get Recommendation</button>
        <p id="recommendationText"></p>
    </div>


    <p id="errorMessage" style="color: red;"></p>
</body>
</html>
