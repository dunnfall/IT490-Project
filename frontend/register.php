<?php
require_once "rabbitMQLib.inc";
require_once "testRabbitMQ.ini";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $email = $_POST['email'];

    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

    $data = [
        'action' => 'register',
        'table' => 'users',
        'data' => [
            'username' => $username,
            'email' => $email,
            'password' => $password
        ]
    ];

    $response = $client->send_request($data);
    
    if ($response['status'] === 'success') {
        echo "Registration successful!";
    } else {
        echo "Error: " . $response['message'];
    }
}
?>
