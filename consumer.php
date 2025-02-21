<?php
require_once "rabbitMQLib.inc";
require_once "testRabbitMQ.ini";

function processRequest($request)
{
    $mysqli = new mysqli("192.168.1.136", "root", "12345", "it490db");

    if ($mysqli->connect_error) {
        return ["status" => "error", "message" => "Database connection failed: " . $mysqli->connect_error];
    }

    if (!isset($request['action'])) {
        return ["status" => "error", "message" => "Invalid request format."];
    }

    switch ($request['action']) {
        case "insert":
            $table = $request['table'];
            $columns = implode(", ", array_keys($request['data']));
            $values = "'" . implode("', '", array_map([$mysqli, 'real_escape_string'], array_values($request['data']))) . "'";

            $sql = "INSERT INTO $table ($columns) VALUES ($values)";

            if ($mysqli->query($sql) === TRUE) {
                return ["status" => "success", "message" => "New record inserted."];
            } else {
                return ["status" => "error", "message" => $mysqli->error];
            }

        default:
            return ["status" => "error", "message" => "Unknown action: " . $request['action']];
    }
}

$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");
$server->process_requests("processRequest");
?>
