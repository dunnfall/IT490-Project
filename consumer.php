<?php
require_once "rabbitMQLib.inc";
require_once "testRabbitMQ.ini";

function processRequest($request) 
{
    $mydb = new mysqli('192.168.1.141', 'testUser', '12345', 'it490db');

    if ($mydb->connect_error) {
        return ["status" => "error", "message" => "Database connection failed: " . $mydb->connect_error];
    }

    if (!isset($request['action'])) {
        return ["status" => "error", "message" => "Invalid request format."];
    }

    switch ($request['action']) {
        case "insert":
            $table = $request['table'];
            $columns = implode(", ", array_keys($request['data']));
            $values = "'" . implode("', '", array_map([$mydb, 'real_escape_string'], array_values($request['data']))) . "'";

            $sql = "INSERT INTO $table ($columns) VALUES ($values)";

            if ($mydb->query($sql) === TRUE) {
                return ["status" => "success", "message" => "New record inserted."];
            } else {
                return ["status" => "error", "message" => $mydb->error];
            }

        case "select":
            $table = $request['table'];
            $sql = "SELECT * FROM $table";
            $result = $mydb->query($sql);
            if ($result) {
                $rows = $result->fetch_all(MYSQLI_ASSOC);
                return ["status" => "success", "data" => $rows];
            } else {
                return ["status" => "error", "message" => $mydb->error];
            }

        default:
            return ["status" => "error", "message" => "Unknown action: " . $request['action']];
    }
}

// Start Consumer to Listen for Messages
$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");
$server->process_requests("processRequest");
?>
