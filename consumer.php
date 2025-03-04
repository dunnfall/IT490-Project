<?php
require_once "rabbitMQLib.inc";
require_once "testRabbitMQ_response.ini";

function processRequest($request)
{
    $mydb = new mysqli("192.168.1.142", "testUser", "12345", "it490db");

    if ($mydb->connect_error) {
        return ["status" => "error", "message" => "Database connection failed: " . $mydb->connect_error];
    }

    if (!isset($request['action'])) {
        return ["status" => "error", "message" => "Invalid request format."];
    }

    switch ($request['action']) {
        case "register":
            $table = $request['table'];
            $columns = implode(", ", array_keys($request['data']));
            $values = "'" . implode("', '", array_map([$mydb, 'real_escape_string'], array_values($request['data']))) . "'";

            $sql = "INSERT INTO $table ($columns) VALUES ($values)";

            if ($mydb->query($sql) === TRUE) {
                return ["status" => "success", "message" => "New user registered."];
            } else {
                return ["status" => "error", "message" => $mydb->error];
            }

        case "login":
            $identifier = $request['identifier'];
            $password = $request['password'];

            // Check if the user exists (by username or email)
            $sql = "SELECT * FROM users WHERE username='$identifier' OR email='$identifier'";
            $result = $mydb->query($sql);

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password'])) {
                    return ["status" => "success", "message" => "User authenticated.", "username" => $user['username']];
                } else {
                    return ["status" => "error", "message" => "Invalid password."];
                }
            } else {
                return ["status" => "error", "message" => "User not found."];
            }

        default:
            return ["status" => "error", "message" => "Unknown action: " . $request['action']];
    }
}

// Start Consumer to Listen for Messages
$server = new rabbitMQServer("testRabbitMQ_response.ini", "responseServer");
$server->process_requests("processRequest");
?>