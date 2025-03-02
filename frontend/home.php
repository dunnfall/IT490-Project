<!DOCTYPE html>
<html>
<head>
    <title>Stock Information</title>
    <script>
        async function fetchStock() {
            let ticker = document.getElementById("ticker").value.trim().toUpperCase();
            let errorMessage = document.getElementById("errorMessage");
            let stockTable = document.getElementById("stockTable");
            let row = document.getElementById("stockRow");

            if (!ticker) {
                errorMessage.textContent = "Please enter a valid ticker.";
                return;
            }

            errorMessage.textContent = "Fetching latest stock data...";
            stockTable.style.display = "none";

            try {
                console.log("Checking database...");
                let response = await fetch(`../API/get_stock.php?ticker=${ticker}`);
                let data = await response.json();

                if (data.error) {
                    console.log("Stock not found. Fetching from API...");
                    let fetchResponse = await fetch(`../API/fetch_stock.php?ticker=${ticker}`);
                    let fetchData = await fetchResponse.json();

                    if (fetchData.error) {
                        errorMessage.textContent = fetchData.error;
                        return;
                    }

                    console.log("Stock added to database, waiting for update...");
                    await new Promise(resolve => setTimeout(resolve, 2000));
                } else {
                    console.log("Stock found in database. Updating latest price...");
                    await fetch(`../API/update_stock.php?ticker=${ticker}`);
                }

                // Retry fetching stock data after update
                await new Promise(resolve => setTimeout(resolve, 2000));
                response = await fetch(`../API/get_stock.php?ticker=${ticker}`);
                data = await response.json();

                if (data.error) {
                    errorMessage.textContent = "Stock data not available.";
                    return;
                }

                errorMessage.textContent = "";
                stockTable.style.display = "table";

                let price = parseFloat(data.price);

                row.innerHTML = `<td>${data.ticker}</td>
                                 <td>${data.company}</td>
                                 <td>$${!isNaN(price) ? price.toFixed(2) : "N/A"}</td>
                                 <td>${data.timestamp}</td>`;
            } catch (error) {
                console.error("Fetch Error:", error);
                errorMessage.textContent = "Error fetching stock data.";
                stockTable.style.display = "none";
            }
        }
    </script>
</head>
<body>
    <h2>Stock Information</h2>
    <label for="ticker">Enter Stock Ticker:</label>
    <input type="text" id="ticker">
    <button onclick="fetchStock()">Get Stock Info</button>

    <table id="stockTable" border="1" style="margin-top:10px; display:none;">
        <tr><th>Ticker</th><th>Company</th><th>Price</th><th>Last Updated</th></tr>
        <tr id="stockRow"></tr>
    </table>

    <p id="errorMessage" style="color: red;"></p>
</body>
</html>
