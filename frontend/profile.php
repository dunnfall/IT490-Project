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
        let ticker = document.getElementById("ticker").value;
        let response = await fetch("fetch_stock.php?ticker=" + ticker);
        let data = await response.json();
        document.getElementById("stockData").innerHTML = JSON.stringify(data, null, 2);
    }
</script>
</html>