<?php
require_once "rabbitMQLib.inc";
require_once "host.ini";

function log_error($message, $level = "ERROR") {
        $client = new rabbitMQClient("host.ini","loggingMachine");
        $client->publish([
            "hostname" => gethostname(),
            "timestamp" => date("Y-m-d H:i:s"),
            "level" => strtoupper($level),
            "message" => $message
        ]);
}
