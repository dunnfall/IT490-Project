<?php
require_once("rabbitMQLib.inc");

function log_error($message, $level = "ERROR") {
        $client = new rabbitMQClient("loggingMachine");
        $client->publish([
            "hostname" => gethostname(),
            "timestamp" => date("Y-m-d H:i:s"),
            "level" => strtoupper($level),
            "message" => $message
        ]);
}
