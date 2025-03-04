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
    $stmt = $db->prepare("SELECT username FROM tokens WHERE token = ? LIMIT 1");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();
    $db->close();

    if ($row) {
        return $row['username'];
    }
    return false;
}

// Check cookie
$token = $_COOKIE['authToken'] ?? '';
if (!$token) {
    header("Location: login.html");
    exit();
}
$username = verifyToken($token);
if (!$username) {
    header("Location: login.html");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Profile</title>
    <?php require(__DIR__ . "/../partials/nav.php"); ?>
    <script>
        async function fetchBalance(retries = 3) {
    try {
        let response = await fetch("../API/get_balance.php");
        let data = await response.json();

        if (data.error) {
            if (retries > 0) {
                console.warn("Retrying fetchBalance... Remaining retries:", retries);
                setTimeout(() => fetchBalance(retries - 1), 2000); // Retry after 2 seconds
                return;
            }
            document.getElementById("balance").textContent = "Error: " + data.error;
        } else {
            document.getElementById("balance").textContent = "$" + data.balance;
        }
    } catch (error) {
        console.error("Fetch Error:", error);
        document.getElementById("balance").textContent = "Error fetching balance";
    }
}

// Fetch balance on page load
document.addEventListener("DOMContentLoaded", () => fetchBalance(3));
    </script>
</head>
<body>
    <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
    <p><strong>Your Balance:</strong> <span id="balance">Loading...</span></p>

    <form action="send_notification.php" method="post">
        <button type="submit" name="send_notification">Send Email Notification</button>
    </form>
</body>
</html>