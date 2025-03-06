<?php
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";

// 1) Get the token from cookie
$token = $_COOKIE['authToken'] ?? '';
if (!$token) {
    header("Location: login.html");
    exit();
}

// 2) Verify the token via RabbitMQ
$client   = new rabbitMQClient("testRabbitMQ.ini", "testServer");
$response = $client->send_request([
    'action' => 'verifyToken',
    'token'  => $token
]);

// 3) Check response
if (!isset($response['status']) || $response['status'] !== 'success') {
    header("Location: login.html");
    exit();
}

// 4) We have a valid token
$username = $response['username'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Profile</title>
    <?php require(__DIR__ . "/../partials/nav.php"); ?>
    <script>
      // This function calls "../API/get_balance.php?user=USERNAME"
      // and uses retries if an error occurs.
      async function fetchBalance(retries = 3) {
        try {
          // Single fetch call using the username from PHP
          let response = await fetch("../API/get_balance.php?user=<?php echo urlencode($username); ?>");
          let data = await response.json();

          if (data.error) {
            if (retries > 0) {
              console.warn("Retrying fetchBalance... Remaining retries:", retries);
              setTimeout(() => fetchBalance(retries - 1), 2000); 
              return;
            }
            document.getElementById("balance").textContent = "Error: " + data.error;
          } else {
            // Successfully got the balance
            document.getElementById("balance").textContent = "$" + data.balance;
          }
        } catch (error) {
          console.error("Fetch Error:", error);
          document.getElementById("balance").textContent = "Error fetching balance";
        }
      }

      // Run on page load
      document.addEventListener("DOMContentLoaded", () => fetchBalance(3));
    </script>
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
    <p><strong>Your Balance:</strong> <span id="balance">Loading...</span></p>

    <form action="send_email.php" method="post">
        <button type="submit" name="send_notification">Send Email Notification</button>
    </form>
</body>
</html>