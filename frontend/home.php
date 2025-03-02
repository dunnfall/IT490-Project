<?php
// Secure session cookie settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
session_start();

// Check if the user is logged in by verifying the session token
if (!isset($_SESSION['username'])) {
    // If the user is not logged in, redirect to the login page
    header("Location: login.html");
    exit();
}

// Store the session username in a variable
$username = $_SESSION['username'];
?>

<?php
// Only if you're on HTTPS:
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_httponly', 1);

session_start();

if (!isset($_SESSION['username'])) {
    // Not logged in:
    header("Location: login.html");
    exit();
}

// If here, user is logged in
$username = $_SESSION['username'];
?>


<!DOCTYPE html>
<html>
<head>
    <title>Stock Information</title>
    <?php require(__DIR__ . "/../partials/nav.php"); ?>
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
                }

                // Now fetch the stock from the database after update
                await getStock();
            } catch (error) {
                console.error("Fetch Error:", error);
                errorMessage.textContent = "Error fetching stock data.";
                stockTable.style.display = "none";
            }
        }

        async function getStock() {
    let ticker = document.getElementById("ticker2").value.trim().toUpperCase();
    let errorMessage = document.getElementById("errorMessage");
    let stockTable = document.getElementById("stockTable");
    let row = document.getElementById("stockRow");

    if (!ticker) {
        errorMessage.textContent = "Please enter a valid ticker.";
        return;
    }

    errorMessage.textContent = "Fetching stock data...";
    stockTable.style.display = "none";

    try {
        let response = await fetch(`../API/get_stock.php?ticker=${ticker}`);
        let data = await response.json();

        if (data.error) {
            errorMessage.textContent = "Stock not found.";
            return;
        }

        errorMessage.textContent = "";
        stockTable.style.display = "table";

        row.innerHTML = `<td>${data.ticker}</td>
                         <td>${data.company}</td>
                         <td>$${parseFloat(data.price).toFixed(2)}</td>
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
    <div style="margin-top:10px;">
        <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
        <a href="logout.php">Logout</a>
    </div>
    
    <div>
        <h2>Fetch Stock Data From API</h2>
        <label for="ticker">Enter Stock Ticker:</label>
        <input type="text" id="ticker">
        <button onclick="fetchStock()">Get Stock Info</button>
    </div>

    <div>
        <h2>Display Stock Information</h2>
        <label for="ticker">Enter Stock Ticker:</label>
        <input type="text" id="ticker2">
        <button onclick="getStock()">Display Stock</button>
    </div>

    <table id="stockTable" border="1" style="margin-top:10px; display:none;">
        <tr><th>Ticker</th><th>Company</th><th>Price</th><th>Last Updated</th></tr>
        <tr id="stockRow"></tr>
    </table>

    <p id="errorMessage" style="color: red;"></p>
</body>
</html>