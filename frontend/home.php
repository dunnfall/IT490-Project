<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Stock Lookup</title>
    <?php require(__DIR__ . "/../partials/nav.php"); ?>
    <script>
        async function searchStock() {
            let ticker = document.getElementById("ticker").value.trim().toUpperCase();
            let errorMessage = document.getElementById("errorMessage");
            let stockTable = document.getElementById("stockTable");
            let row = document.getElementById("stockRow");
            let stockDetails = document.getElementById("stockDetails");

            if (!ticker) {
                errorMessage.textContent = "Please enter a valid ticker.";
                return;
            }

            errorMessage.textContent = "Fetching stock data...";
            stockTable.style.display = "none";
            stockDetails.style.display = "none";

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
            } catch (error) {
                console.error("Fetch Error:", error);
                errorMessage.textContent = "Error fetching stock data.";
                stockTable.style.display = "none";
            }
        }

        async function showMoreInfo(ticker) {
            let stockDetails = document.getElementById("stockDetails");
            let detailsContent = document.getElementById("detailsContent");
            stockDetails.style.display = "block";
            detailsContent.innerHTML = "Loading additional stock details...";

            try {
                let response = await fetch(`../API/stock_handler.php?ticker=${ticker}`);
                let data = await response.json();

                if (data.error) {
                    detailsContent.innerHTML = "Stock details not available.";
                    return;
                }

                detailsContent.innerHTML = `
                    <p><strong>Ticker:</strong> ${data.ticker}</p>
                    <p><strong>Company:</strong> ${data.company}</p>
                    <p><strong>Price:</strong> $${parseFloat(data.price).toFixed(2)}</p>
                    <p><strong>52-Week High:</strong> $${parseFloat(data['52weekhigh']).toFixed(2)}</p>
                    <p><strong>52-Week Change (%):</strong> ${data["52weekchangepercent"] ? data["52weekchangepercent"].toFixed(2) + "%" : "N/A"}</p>
                    <p><strong>52-Week Low:</strong> $${parseFloat(data['52weeklow']).toFixed(2)}</p>
                    <p><strong>Market Cap:</strong> $${data.marketcap}</p>
                    <p><strong>Region:</strong> ${data.region}</p>
                    <p><strong>Currency:</strong> ${data.currency}</p>
                `;
            } catch (error) {
                console.error("Fetch Error:", error);
                detailsContent.innerHTML = "Error loading stock details.";
            }
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
    </div>

    <p id="errorMessage" style="color: red;"></p>
</body>
</html>
