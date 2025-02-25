<?php
session_start();
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";

if ($_SERVER["REQUEST_METHOD"] === "GET") {
    if (isset($_SESSION['username'])) {
        header("Location: home.html");
        exit();
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $identifier = $_POST['identifier'];
    $password = $_POST['password'];

    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

    $data = [
        'action' => 'login',
        'identifier' => $identifier,
        'password' => $password
    ];

    $response = $client->send_request($data);

    if ($response['status'] === 'success') {
        $_SESSION['username'] = $response['username']; // Assuming the response contains the username
        header("Location: home.html");
        exit();
    } else {
        echo "Error: " . $response['message'];
    }
}
?>