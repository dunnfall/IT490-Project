<?php
require_once("rabbitMQLib.inc");

function log_to_file($msg) {
    $log = "[" . $msg["timestamp"] . "] ";
    $log .= $msg["hostname"] . " - ";
    $log .= $msg["level"] . ": ";
    $log .= $msg["message"] . "\n";

    file_put_contents("/var/log/cluster_errors.log", $log, FILE_APPEND);
}

$server = new rabbitMQServer("host.ini","loggingMachine");
$server->process_requests("log_to_file");
?>
