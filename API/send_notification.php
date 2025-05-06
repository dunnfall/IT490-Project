<?php
// /home/website/IT490-Project/API/send_notification.php

require_once "/home/website/IT490-Project/rabbitMQLib.inc";
require_once "/home/website/IT490-Project/testRabbitMQ.ini";
require __DIR__ . '/../vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// --- Auth token check ---
$token = $_COOKIE['authToken'] ?? '';
if (!$token) {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . "/frontend/login.html");;
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

// --- Get user's phone & carrier ---
$phResp = $client->send_request([
    "action" => "verifyAndGetPhone",
    "token"  => $token
]);
$userPhone   = ($phResp['status'] ?? '') === 'success' ? $phResp['phone']   : null;
$userCarrier = ($phResp['status'] ?? '') === 'success' ? $phResp['carrier'] : null;

// 3) If invalid token, redirect with an error
if (!isset($response["status"]) || $response["status"] !== "success") {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . urlencode($response["message"] ?? "Invalid token"));
    exit();
}
$userEmail = $response["email"];

// 4) Build the email subject/body
if ($tradeType && $ticker && $quantity) {
    // It's a trade notification
    $subject = "Trade Notification: $tradeType $ticker";
    $body    = "<p>Hello! </p>";
    $body   .= "<p>You have just executed a <strong>$tradeType</strong> order. </p>";
    $body   .= "<ul>";
    $body   .= "<li>Ticker: $ticker </li>";
    $body   .= "<li>Quantity: $quantity </li>";
    if ($totalCost !== '') {
        $body .= "<li>Total Cost/Proceeds: \$$totalCost</li>";
    }
    if ($newBal !== '') {
        $body .= "<li> New Balance: \$$newBal</li>";
    }
    $body .= "</ul>";
    $body .= "<p> Thank you for using our service!</p>";
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

    /* ---- Send SMS via carrier e‑mail gateway ---- */
    $gateways = [
        'verizon'  => '@vtext.com',
        'att'      => '@txt.att.net',
        'tmobile'  => '@tmomail.net',
        'sprint'   => '@messaging.sprintpcs.com',
        'googlefi' => '@msg.fi.google.com'
    ];
    if ($userPhone && $userCarrier && isset($gateways[$userCarrier])) {
        // keep only digits and trim leading country code if present
        $digits = preg_replace('/\D/', '', $userPhone);
        // if number is 11‑digits and starts with a leading 1, drop the 1 (US country code)
        if (strlen($digits) === 11 && $digits[0] === '1') {
            $digits = substr($digits, 1);
        }
        // T‑Mobile and most US gateways require exactly 10 digits
        $smsAddr = $digits . $gateways[$userCarrier];
        error_log("Email‑to‑SMS address resolved to: $smsAddr");
        try {
            $mail->addAddress($smsAddr);
        } catch (Exception $smsEx) {
            error_log("Email‑to‑SMS addAddress error: ".$smsEx->getMessage());
        }
    }

    $mail->send();

    // 6) Redirect with success
    header("Location: https://" . $_SERVER['HTTP_HOST'] . "/frontend/profile.php?success=1");
    exit();
} catch (Exception $e) {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . "/frontend/profile.php" . urlencode($mail->ErrorInfo));
    exit();
}
?>