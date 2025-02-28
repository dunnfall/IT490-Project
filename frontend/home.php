<?php
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

<!-- Added Code EAC-->
<h2>Stock Information</h2>
<label for="ticker">Enter Stock Ticker:</label>
<input type="text" id="ticker" value="AAPL">
<button onclick="fetchStock()">Get Stock Info</button>

<pre id="stockData"></pre>

<script>
    async function fetchStock() {
        let ticker = document.getElementById("ticker").value;
        try {
            let response = await fetch("../API/fetch_stock.php?ticker=" + ticker);
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            let data = await response.json();
            document.getElementById("stockData").innerHTML = JSON.stringify(data, null, 2);
        } catch (error) {
            document.getElementById("stockData").innerHTML = 'Error: ' + error.message;
            console.error('Error fetching stock data:', error);
        }
    }
</script>
</html>
?>