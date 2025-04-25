<?php
header('Content-Type: application/json');
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";

// single request: verify + get balance
$token = $_COOKIE['authToken'] ?? '';

$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
$response = $client->send_request([
    'action' => 'verifyAndGetBalance',
    'token'  => $token
]);

if ($response && isset($response['status']) && $response['status']==='success') {
    echo json_encode(["balance"=> number_format($response["balance"], 2)]);
} else {
    echo json_encode(["error"=>$response['message'] ?? "Failed"]);
}
?>
//this is just for testing purposes to see if the deployment is actually working.