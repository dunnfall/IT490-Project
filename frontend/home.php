<?php 
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
$ini = parse_ini_file("/home/website/IT490-Project/testRabbitMQ.ini");
if (!$ini) {
    die("Error: Unable to load configuration file.");
}

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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Lookup</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
                errorMessage, stockTable, row, stockDetails, recommendationContainer
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
                 <td>
                   <button class="btn btn-sm btn-info" onclick="showMoreInfo('${data.ticker}')">
                     More Info
                   </button>
                   <button class="btn btn-sm btn-success ml-2"
                           onclick="addToWatchlist('${data.ticker}')">
                     + Watchlist
                   </button>
                 </td>`;

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
                        data: [low, high, high * (1 + percentChange / 100)],
                        borderColor: 'blue',
                        backgroundColor: 'rgba(0, 0, 255, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 5,
                        pointBackgroundColor: ['blue', 'blue', 'red'],
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: false
                        }
                    }
                }
            });
        }

            async function addToWatchlist(ticker) {
                try {
                    const res = await fetch("../backend/addToWatchlist.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ ticker })
                    });
                    const json = await res.json();
                    if (json.success) {
                    alert(`${ticker} added to your watchlist!`);
                    } else {
                    alert(`Error: ${json.error || json.message}`);
                    }
                    } catch (err) {
                        console.error(err);
                        alert("Could not add to watchlist.");
                    }
                }
        
    </script>
</head>
<body>
    <div class="container my-4">
        <div class="mb-4">
            <h2 class="mb-3">Search for Stock Information</h2>
            <div class="input-group">
                <input type="text" class="form-control" id="ticker" placeholder="Insert ticker">
                <button class="btn btn-primary" onclick="searchStock()">Search</button>
            </div>
        </div>

        <table id="stockTable" class="table table-striped mt-3" style="display:none;">
            <thead>
                <tr>
                    <th>Ticker</th>
                    <th>Company</th>
                    <th>Price</th>
                    <th>Last Updated</th>
                    <th>More Info</th>
                </tr>
            </thead>
            <tbody>
                <tr id="stockRow"></tr>
            </tbody>
        </table>

        <div id="stockDetails" class="card mt-3" style="display:none;">
            <div class="card-body">
                <h3 class="card-title">Stock Details</h3>
                <div id="detailsContent"></div>
            </div>
            <div id="stockChartContainer" class="mt-3" style="display:none;">
                <h3>Stock Price Range (52 Weeks)</h3>
                <canvas id="stockChart"></canvas>
            </div>
        </div>

        <div id="recommendationContainer" class="card mt-3" style="display:none;">
            <div class="card-body">
                <h3 class="card-title">Stock Recommendation</h3>
                <button id="getRecommendationButton" class="btn btn-secondary" onclick="getRecommendation()">Get Recommendation</button>
                <p id="recommendationText" class="mt-2"></p>
            </div>
        </div>

        <p id="errorMessage" class="text-danger mt-3"></p>
    </div>
    
    <!-- Bootstrap JS Bundle Im adding this comment to see if it the deployment works with the changed paths i did-->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>