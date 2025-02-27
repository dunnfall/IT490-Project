<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $identifier = $_POST['identifier'] ?? '';
    $password   = $_POST['password']   ?? '';

    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

    $data = [
        'action'     => 'login',
        'identifier' => $identifier,
        'password'   => $password
    ];

    $response = $client->send_request($data);

    // Debug:
    // var_dump($response); exit;

    if (isset($response['status']) && $response['status'] === 'success') {
        $_SESSION['username'] = $response['username'] ?? $identifier;
        header("Location: home.php");
        exit();
    } else {
        // Show error
        echo "Error: " . ($response['message'] ?? 'Unknown error');
        // Possibly link back:
        // echo "<p><a href='login.html'>Return to login page</a></p>";
    }
}
?>