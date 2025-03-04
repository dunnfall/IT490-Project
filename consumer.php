<?php
require_once "rabbitMQLib.inc";
require_once "testRabbitMQ.ini";

$server = new rabbitMQServer("testRabbitMQ.ini", "testServer"); // Receiving requests
$responseClient = new rabbitMQClient("testRabbitMQ_response.ini", "responseServer"); // Sending responses

function processRequest($request)
{
    global $responseClient;

    $mydb = new mysqli("localhost", "testUser", "12345", "it490db");

    if ($mydb->connect_error) {
        return ["status" => "error", "message" => "Database connection failed: " . $mydb->connect_error];
    }

    switch ($request['action']) {
        case "login":
            $identifier = $request['identifier'];
            $password = $request['password'];

            $sql = "SELECT username, password FROM users WHERE username=? OR email=?";
            $stmt = $mydb->prepare($sql);
            $stmt->bind_param("ss", $identifier, $identifier);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    $response = ["status" => "success", "message" => "User authenticated.", "username" => $user['username']];
                } else {
                    $response = ["status" => "error", "message" => "Invalid password."];
                }
            } else {
                $response = ["status" => "error", "message" => "User not found."];
            }
            break;

        case "register":
            $username = $mydb->real_escape_string($request['data']['username']);
            $email = $mydb->real_escape_string($request['data']['email']);
            $hashedPassword = $request['data']['password'];

            $sql = "INSERT INTO users (username, email, password) VALUES (?, ?, ?)";
            $stmt = $mydb->prepare($sql);
            $stmt->bind_param("sss", $username, $email, $hashedPassword);

            if ($stmt->execute()) {
                $response = ["status" => "success", "message" => "New user registered."];
            } else {
                $response = ["status" => "error", "message" => "Registration failed: " . $stmt->error];
            }
            break;

        default:
            $response = ["status" => "error", "message" => "Unknown action: " . $request['action']];
    }

    // Send response back to the client via response queue
    $responseClient->send_request($response);
}

// Start Consumer to Listen for Messages
$server->process_requests("processRequest");
?>
