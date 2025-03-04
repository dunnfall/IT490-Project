<?php
function getDB() {
    $db = new mysqli("192.168.1.142", "testUser", "12345", "it490db");
    if ($db->connect_error) {
        die("Database connection failed: " . $db->connect_error);
    }
    return $db;
}

function verifyToken($token) {
    $db = getDB();
    // Retrieve username by token
    $stmt = $db->prepare("SELECT username FROM tokens WHERE token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();
    $db->close();

    if ($row) {
        // Token found => valid
        return $row['username'];
    } else {
        // Not found => invalid
        return false;
    }
}

// 1) Read the token from cookie
$token = $_COOKIE['authToken'] ?? '';
if (!$token) {
    // No cookie => not logged in
    header("Location: login.html");
    exit();
}

// 2) Verify token in DB
$username = verifyToken($token);
if (!$username) {
    // Invalid token => redirect
    header("Location: login.html");
    exit();
}

// If valid, show the home page
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
    let stockDetails = document.getElementById("stockDetails");

    if (!ticker) {
        errorMessage.textContent = "Please enter a valid ticker.";
        return;
    }

    errorMessage.textContent = "Loading...";
    stockTable.style.display = "none";
    stockDetails.style.display = "none"; // Hide stock details initially

    let retries = 3;
    let delay = 500; // 2 seconds delay between retries
    let stockFound = false;
    let data = null;

    for (let i = 0; i < retries; i++) {
        try {
            let response = await fetch(`../API/get_stock.php?ticker=${ticker}`);
            data = await response.json();

            if (!data.error) {
                stockFound = true;
                break;
            }
        } catch (error) {
            console.error(`Fetch attempt ${i + 1} failed:`, error);
        }

        await new Promise(resolve => setTimeout(resolve, delay)); // Wait before retrying
    }

    if (!stockFound) {
        errorMessage.textContent = "Stock not found or failed to fetch data.";
        return;
    }

    // Display stock data
    errorMessage.textContent = "";
    stockTable.style.display = "table";

    row.innerHTML = `<td>${data.ticker}</td>
                     <td>${data.company}</td>
                     <td>$${parseFloat(data.price).toFixed(2)}</td>
                     <td>${data.timestamp}</td>
                     <td><button onclick="showMoreInfo('${data.ticker}')">More Info</button></td>`;

    stockDetails.style.display = "block"; // Show stock details if needed
}
async function showMoreInfo(ticker) {
    let stockDetails = document.getElementById("stockDetails");
    let detailsContent = document.getElementById("detailsContent");

    stockDetails.style.display = "block";
    detailsContent.innerHTML = "Loading additional stock details...";

    let retries = 3;
    let delay = 500; // 2 seconds delay between retries
    let stockFound = false;
    let data = null;

    for (let i = 0; i < retries; i++) {
        try {
            let response = await fetch(`../API/get_stock.php?ticker=${ticker}`);
            data = await response.json();

            if (!data.error) {
                stockFound = true;
                break;
            }
        } catch (error) {
            console.error(`Fetch attempt ${i + 1} failed:`, error);
        }

        await new Promise(resolve => setTimeout(resolve, delay)); // Wait before retrying
    }

    if (!stockFound) {
        detailsContent.innerHTML = "Stock details not available or failed to fetch data.";
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
}

    </script>
</head>
<body>
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
    <tr>
        <th>Ticker</th>
        <th>Company</th>
        <th>Price</th>
        <th>Last Updated</th>
        <th>More Info</th>
    </tr>
    <tr id="stockRow"></tr>
</table>

<!-- New Section for More Stock Details -->
<div id="stockDetails" style="display:none; margin-top:10px; border:1px solid #ddd; padding:10px;">
    <h3>Stock Details</h3>
    <div id="detailsContent"></div>
</div>

<p id="errorMessage" style="color: red;"></p>
</body>
</html>