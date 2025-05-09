<?php
require_once "rabbitMQLib.inc";
require_once "testRabbitMQ.ini";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $code = $_POST['code'];

    $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
    $response = $client->send_request([
        "action" => "verify2fa",
        "username" => $username,
        "code" => $code
    ]);

    if ($response['status'] === "success") {
        setcookie("authToken", $response['token'], time() + 3600, "/");
        header("Location: home.php");
    } else {
        header("Location: 2fa.html?message=invalid_code");
    }
}
?>
