<?php
// /home/website/IT490-Project/API/send_notification.php

require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";
require __DIR__ . '/../vendor/autoload.php'; // PHPMailer autoloader

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$token = $_COOKIE['authToken'] ?? '';
if (!$token) {
    header("Location: ../frontend/login.html");
    exit();
}

// Only proceed if user clicked the button
if (!isset($_POST['send_notification'])) {
    header("Location: ../frontend/profile.php");
    exit();
}

// 1) Use RabbitMQ to verify token & get user email
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
$request = [
    "action" => "verifyAndGetEmail",
    "token"  => $token
];
$response = $client->send_request($request);

// 2) If invalid, redirect with an error
if (!isset($response["status"]) || $response["status"] !== "success") {
    header("Location: ../frontend/profile.php?error=" . urlencode($response["message"] ?? "Invalid token"));
    exit();
}

$userEmail = $response["email"];

// 3) Send the email via PHPMailer
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'YOUR_GMAIL@gmail.com';
    $mail->Password   = 'YOUR_APP_PASSWORD';
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('YOUR_GMAIL@gmail.com', 'My App Notifications');
    $mail->addAddress($userEmail);

    $mail->isHTML(true);
    $mail->Subject = 'Notification from My App';
    $mail->Body    = "<p>Hello!</p><p>This is a notification email to your account.</p>";
    $mail->AltBody = "Hello!\n\nThis is a notification email to your account.";

    $mail->send();

    // 4) Redirect with success
    header("Location: ../frontend/profile.php?success=1");
    exit();
} catch (Exception $e) {
    header("Location: ../frontend/profile.php?error=" . urlencode($mail->ErrorInfo));
    exit();
}
?>