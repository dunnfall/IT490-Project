<?php
require_once "/home/website/IT490-Project/rabbitMQLib.inc";

// Use the request INI for sending and response INI for receiving
$requestClient = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ.ini", "testServer");
$responseClient = new rabbitMQClient("/home/website/IT490-Project/testRabbitMQ_response.ini", "responseServer");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $email = $_POST['email'];

    $data = [
        'action' => 'register',
        'table' => 'users',
        'data' => [
            'username' => $username,
            'email' => $email,
            'password' => $password
        ]
    ];

    // Send registration request to RabbitMQ
    $requestClient->send_request($data);

    // Wait for response from responseQueue
    $response = $responseClient->wait_for_response();

    if ($response['status'] === 'success') {
        header("Location: login.html?message=registration_successful");
        exit();
    } else {
        echo "Error: " . $response['message'];
    }
}
?>
