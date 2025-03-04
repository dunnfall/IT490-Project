<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['username'])) {
    echo json_encode(["error" => "User not logged in"]);
    exit();
}

// Fetch email dynamically using a relative path like `get_balance.php`
$emailResponse = file_get_contents("../API/get_email.php"); // Using the WORKING fetch method
$emailData = json_decode($emailResponse, true);

// Debugging: Log response
error_log("Email Response: " . print_r($emailData, true));

if (!isset($emailData["email"])) {
    echo json_encode(["error" => $emailData["error"] ?? "Failed to fetch email"]);
    exit();
}

$userEmail = $emailData["email"];

// Include Composer's autoloader
require __DIR__ . '/../vendor/autoload.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$mail = new PHPMailer(true);

try {
    // MAILER SETUP
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'alucky0140@gmail.com';
    $mail->Password   = 'znrl ceaf xlqr zeop';
    $mail->SMTPSecure = 'tls';
    $mail->Port       = 587;

    // SENDER & RECIPIENT
    $mail->setFrom('alucky0140@gmail.com', 'My App Notifications');
    $mail->addAddress($userEmail);

    // EMAIL CONTENT
    $mail->isHTML(true);
    $mail->Subject = 'Notification from My App';
    $mail->Body    = "<p>Hello!</p><p>This is a notification email to your account.</p>";
    $mail->AltBody = "Hello!\n\nThis is a notification email to your account.";

    // SEND MESSAGE
    $mail->send();

    // Return JSON response (no redirect)
    echo json_encode(["success" => "Notification email sent successfully!"]);
    exit();

} catch (Exception $e) {
    echo json_encode(["error" => $mail->ErrorInfo]);
    exit();
}
?>