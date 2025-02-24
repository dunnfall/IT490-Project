<?php
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";

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
        header("Location: login.html?message=registration_successful");
        exit();
    } else {
        echo "Error: " . $response['message'];
    }
}
?>
