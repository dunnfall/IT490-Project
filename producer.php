<?php
require_once "rabbitMQLib.inc";
require_once "testRabbitMQ.ini";

$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");

$data = [
    'action' => 'insert',
    'table' => 'users',
    'data' => [
        'username' => 'testUser',
        'email' => 'test@example.com',
        'password' => password_hash('securepassword', PASSWORD_BCRYPT)
    ]
];

$response = $client->send_request($data);

echo "Producer sent data, received response: \n";
print_r($response);
?>
