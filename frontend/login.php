<?php
// rabbitMQLib includes (adjust paths as necessary)
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";

// Connect to DB with MySQLi
function getDB() {
    // Provided info:
    $db = new mysqli("192.168.1.142", "testUser", "12345", "it490db");
    if ($db->connect_error) {
        die("Database connection failed: " . $db->connect_error);
    }
    return $db;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $identifier = $_POST['identifier'] ?? '';
    $password   = $_POST['password']   ?? '';

    // 1) Validate via RabbitMQ
    $client   = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $data     = [
        'action'     => 'login',
        'identifier' => $identifier,
        'password'   => $password
    ];
    $response = $client->send_request($data);

    // 2) Check the response
    if (isset($response['status']) && $response['status'] === 'success') {
        // Generate a random token (32 hex chars)
        $token = bin2hex(random_bytes(16));

        // Insert token->username into 'tokens' table
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO tokens (token, username) VALUES (?, ?)");
        $stmt->bind_param("ss", $token, $response['username']);
        $stmt->execute();
        $stmt->close();
        $db->close();

        // Set token in a cookie (lasts 1 hour)
        // For production: use secure=>true, httponly=>true if HTTPS
        setcookie("authToken", $token, time() + 3600, "/");

        // Redirect to home.php
        header("Location: home.php");
        exit();
    } else {
        // Invalid credentials
        echo "Error: " . ($response['message'] ?? 'Invalid credentials');
        echo "<br><a href='login.html'>Go Back</a>";
        exit();
    }
}

// If we reach here without POST, just redirect
header("Location: login.html");
exit();
