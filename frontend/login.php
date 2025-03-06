<?php
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = $_POST['identifier'];
    $password   = $_POST['password'];

    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $request = [
        'action'     => 'login',
        'identifier' => $identifier,
        'password'   => $password
    ];
    $response = $client->send_request($request);

    if ($response['status'] === 'success') {
        // Store token in a cookie, for example
        $token = $response['token'];
        setcookie("authToken", $token, time()+3600, "/");
        header("Location: home.php");
        exit();
    } else {
        echo "Error: " . $response['message'];
    }
}
?>
