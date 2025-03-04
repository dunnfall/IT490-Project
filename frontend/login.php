<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once "/home/website/IT490-Project/rabbitMQLib.inc";

// Use the request INI for sending and response INI for receiving
$requestClient = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ.ini", "testServer");
$responseClient = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ_response.ini", "responseServer");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $identifier = $_POST['identifier'] ?? '';
    $password   = $_POST['password']   ?? '';

    $data = [
        'action'     => 'login',
        'identifier' => $identifier,
        'password'   => $password
    ];

    // Send login request to RabbitMQ
    $requestClient->send_request($data);

    // Wait for response from responseQueue
    $response = $responseClient->wait_for_response();

    if (isset($response['status']) && $response['status'] === 'success') {
        $_SESSION['username'] = $response['username'] ?? $identifier;
        header("Location: home.php");
        exit();
    } else {
        header("Location: login.html?message=login_failed");
        exit();
    }
}
?>
