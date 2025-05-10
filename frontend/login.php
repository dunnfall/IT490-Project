<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";
require_once "/home/website/IT490-Project/error_logger.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = $_POST['identifier'];
    $password = $_POST['password'];

    try {
        $client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
        $request = [
            'action'    => 'login',
            'identifier'=> $identifier,
            'password'  => $password
        ];
        $response = $client->send_request($request);

        if ($response['status'] === 'success') {
            $token = $response['token'];
            setcookie("authToken", $token, time() + 3600, "/");
            header("Location: https://" . $_SERVER['HTTP_HOST'] . "/frontend/home.php");;
            exit();
        } elseif ($response['status'] === '2fa_required') {
            $username = urlencode($response['username']);
            header("Location: 2fa.php?username={$username}");
            exit();
        } else {
            log_error("Login failed for user: $identifier");
            header("Location: login.html?message=login_failed");
            exit();
        }
    } catch (Exception $e) {
        log_error("Login process error: " . $e->getMessage());
        header("Location: login.html?message=server_error");
        exit();
    }
}
?>
