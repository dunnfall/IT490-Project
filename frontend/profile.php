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
        //Fetch latest stock data from API & store in DB
        console.log("Fetching latest stock data from API...");
        let fetchResponse = await fetch(`../API/fetch_stock.php?ticker=${ticker}`);

        if (!fetchResponse.ok) {
            errorMessage.textContent = "Failed to fetch stock from API.";
            return;
        }

        //Wait a moment for DB update
        await new Promise(resolve => setTimeout(resolve, 2000)); // 2-second delay

        // Retrieve latest stock from database
        console.log(" Retrieving latest stock from database...");
        
        try {
            let response = await fetch(`../API/get_stock.php?ticker=${ticker}`);
            let text = await response.text(); // Get raw response
            console.log(" Raw API Response:", text); // Debugging raw output

            let data = JSON.parse(text); // Try parsing as JSON
            console.log(" Parsed API Response:", data); // Debugging parsed JSON

            if (data.error) {
                errorMessage.textContent = "Stock data not available.";
                return;
            }

            //  Display updated stock data
            errorMessage.textContent = "";
            stockTable.style.display = "table";

            let price = parseFloat(data.price);

            row.innerHTML = `<td>${data.ticker}</td>
                             <td>${data.company}</td>
                             <td>$${!isNaN(price) ? price.toFixed(2) : "N/A"}</td>
                             <td>${data.timestamp}</td>`;

        } catch (jsonError) {
            console.error("JSON Parse Error:", jsonError);
            errorMessage.textContent = "Error processing stock data.";
            stockTable.style.display = "none";
        }

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
