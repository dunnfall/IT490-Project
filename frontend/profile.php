<!DOCTYPE html>
<html>
<head>
    <title>Stock Information</title>
    <script>
        async function fetchStock() {
            let ticker = document.getElementById("ticker").value.trim().toUpperCase();
            if (!ticker) {
                document.getElementById("errorMessage").textContent = "Please enter a valid ticker.";
                return;
            }

            try {
                let response = await fetch("../API/fetch_stock.php?ticker=" + ticker);
                let data = await response.json();

                console.log("API Response:", data);

                if (data.error) {
                    document.getElementById("errorMessage").textContent = data.error;
                    document.getElementById("stockTable").style.display = "none";
                    return;
                }

                document.getElementById("errorMessage").textContent = "";
                document.getElementById("stockTable").style.display = "table";

                let row = document.getElementById("stockRow");

                // Converted to float for proper formatting
                let price = parseFloat(data.price);

                row.innerHTML = `<td>${data.ticker}</td>
                                 <td>${data.company}</td>
                                 <td>$${!isNaN(price) ? price.toFixed(2) : "N/A"}</td>
                                 <td>${data.timestamp}</td>`;
            } catch (error) {
                console.error("Fetch Error:", error);
                document.getElementById("errorMessage").textContent = "Error fetching stock data.";
                document.getElementById("stockTable").style.display = "none";
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
