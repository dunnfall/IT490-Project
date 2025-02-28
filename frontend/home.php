<?php
// If here, user is logged in
$username = $_SESSION['username'];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Home</title>
    <script>
        function fetchStock() {
            const ticker = document.getElementById('ticker').value;
            fetch(`fetch_stock.php?ticker=${ticker}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('stockData').textContent = `Error: ${data.error}`;
                    } else {
                        document.getElementById('stockData').textContent = JSON.stringify(data, null, 2);
                    }
                })
                .catch(error => {
                    document.getElementById('stockData').textContent = `Fetch error: ${error}`;
                });
        }
    </script>
</head>
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
</body>
</html>