<?php
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $identifier = $_POST['identifier'];
    $password = $_POST['pword'];

    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

    $data = [
        'action' => 'login',
        'identifier' => $identifier,
        'password' => $password
    ];

    $response = $client->send_request($data);

    if ($response['status'] === 'success') {
        echo "Login successful!";
    } else {
        echo "Error: " . $response['message'];
    }
}
?>