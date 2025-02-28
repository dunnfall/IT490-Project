<?php
require_once "rabbitMQLib.inc";
require_once "testRabbitMQ.ini";

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
        case "store_stock":
            $table = $request['table'];
            $columns = implode(", ", array_keys($request['data']));
            $values = "'" . implode("', '", array_map([$mydb, 'real_escape_string'], array_values($request['data']))) . "'";

            $sql = "INSERT INTO $table ($columns) VALUES ($values)";

            if ($mydb->query($sql) === TRUE) {
                return ["status" => "success", "message" => "Stock data stored successfully."];
            } else {
                return ["status" => "error", "message" => $mydb->error];
            }

        default:
            return ["status" => "error", "message" => "Unknown action: " . $request['action']];
    }
}

$server = new rabbitMQServer("testRabbitMQ.ini", "testServer");
$server->process_requests("processRequest");
?>