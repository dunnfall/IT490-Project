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
<input type="text" id="ticker" value="">
<button onclick="fetchStock()">Get Stock Info</button>

<table id="stockTable" border="1" style="margin-top:10px; display:none;">
    <tr><th>Ticker</th><th>Company</th><th>Price</th><th>Last Updated</th></tr>
    <tr id="stockRow"></tr>
</table>

<p id="errorMessage" style="color: red;"></p>

<script>
async function fetchStock() {
    let ticker = document.getElementById("ticker").value.trim();
    if (!ticker) {
        document.getElementById("errorMessage").textContent = "Please enter a valid ticker.";
        return;
    }

    try {
        let response = await fetch("../API/fetch_stock.php?ticker=" + ticker);
        let data = await response.json();

        if (data.error) {
            document.getElementById("errorMessage").textContent = data.error;
            document.getElementById("stockTable").style.display = "none";
            return;
        }

        document.getElementById("errorMessage").textContent = "";
        document.getElementById("stockTable").style.display = "table";

        let row = document.getElementById("stockRow");
        row.innerHTML = `<td>${data.ticker}</td>
                         <td>${data.company}</td>
                         <td>$${data.price.toFixed(2)}</td>
                         <td>${data.timestamp}</td>`;
    } catch (error) {
        document.getElementById("errorMessage").textContent = "Error fetching stock data.";
        document.getElementById("stockTable").style.display = "none";
    }
}
</script>
<script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
