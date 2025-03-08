<?php
// /home/website/IT490-Project/API/send_notification.php

require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";
require __DIR__ . '/../vendor/autoload.php'; // PHPMailer autoloader

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$token = $_COOKIE['authToken'] ?? '';
if (!$token) {
    // If no token, redirect or exit
    header("Location: ../frontend/login.html");
    exit();
}

// 1) Determine if this is a "trade notification" or a "generic" one
$tradeType = $_REQUEST['tradeType'] ?? ''; // e.g. "BUY" or "SELL"
$ticker    = $_REQUEST['ticker']    ?? '';
$quantity  = $_REQUEST['quantity']  ?? '';
$totalCost = $_REQUEST['totalCost'] ?? '';
$newBal    = $_REQUEST['newBal']    ?? '';

// 2) Create RabbitMQ client and request "verifyAndGetEmail"
$client = new rabbitMQClient("testRabbitMQ.ini", "testServer");
$request = [
    "action" => "verifyAndGetEmail",
    "token"  => $token
];
$response = $client->send_request($request);

// 3) If invalid token, redirect with an error
if (!isset($response["status"]) || $response["status"] !== "success") {
    header("Location: ../frontend/profile.php?error=" . urlencode($response["message"] ?? "Invalid token"));
    exit();
}
$userEmail = $response["email"];

// 4) Build the email subject/body
if ($tradeType && $ticker && $quantity) {
    // It's a trade notification
    $subject = "Trade Notification: $tradeType $ticker";
    $body    = "<p>Hello!</p>";
    $body   .= "<p>You have just executed a <strong>$tradeType</strong> order.</p>";
    $body   .= "<ul>";
    $body   .= "<li>Ticker: $ticker</li>";
    $body   .= "<li>Quantity: $quantity</li>";
    if ($totalCost !== '') {
        $body .= "<li>Total Cost/Proceeds: \$$totalCost</li>";
    }
    if ($newBal !== '') {
        $body .= "<li>New Balance: \$$newBal</li>";
    }
    $body .= "</ul>";
    $body .= "<p>Thank you for using our service!</p>";
} else {
    // Fallback: generic notification (the old logic)
    $subject = 'Notification from My App';
    $body    = "<p>Hello!</p><p>This is a generic notification email to your account.</p>";
}

// 5) Send the email via PHPMailer
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'alucky0140@gmail.com';
    $mail->Password   = 'fvts edcz wnmy izgi';
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    $mail->setFrom('alucky0140@gmail.com', 'My App Notifications');
    $mail->addAddress($userEmail);

    $mail->isHTML(true);
    $mail->Subject = $subject;
    $mail->Body    = $body;
    $mail->AltBody = strip_tags($body);

    $mail->send();
    // 6) Redirect with success
    header("Location: ../frontend/profile.php?success=1");
    exit();
} catch (Exception $e) {
    header("Location: ../frontend/profile.php?error=" . urlencode($mail->ErrorInfo));
    exit();
}
?>