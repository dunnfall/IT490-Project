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
<head><title>Home</title></head>
<body>
<?php require(__DIR__ . "/../partials/nav.php"); ?>
<h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
<p>This is protected content.</p>
</body>

<!-- Added Code EAC-->
<h2>Stock Information</h2>
<label for="ticker">Enter Stock Ticker:</label>
<input type="text" id="ticker" value="AAPL">
<button onclick="fetchStock()">Get Stock Info</button>

<pre id="stockData"></pre>

<script>
    async function fetchStock() {
        let ticker = document.getElementById("ticker").value.trim().toUpperCase();
        let stockDataElement = document.getElementById("stockData");

        stockDataElement.innerHTML = "Fetching stock data...";

        try {
            let response = await fetch("../API/fetch_stock.php?ticker=" + ticker, {
                method: "GET"
            });

            if (!response.ok) {
                throw new Error("Network response was not ok");
            }

            let data = await response.json();

            if (data.error) {
                stockDataElement.innerHTML = <strong>Error:</strong> ${data.error};
            } else {
                stockDataElement.innerHTML = 
                    <strong>Ticker:</strong> ${data.ticker} <br>
                    <strong>Company:</strong> ${data.company} <br>
                    <strong>Price:</strong> $${parseFloat(data.price).toFixed(2)} <br>
                    <strong>Timestamp:</strong> ${data.timestamp}
                ;
            }
        } catch (error) {
            stockDataElement.innerHTML = <strong>Error:</strong> ${error.message};
        }
    }
</script>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>