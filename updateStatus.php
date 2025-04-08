<?php
require_once('rabbitMQLib.inc');

function updateStatus($version, $status) {
    $client = new rabbitMQClient("deploymentServer.ini", "deploymentServer");

    $request = [
        "type" => "updateStatus",
        "version_number" => $version,
        "status" => $status
    ];

    $response = $client->send_request($request);

    if (isset($response['success']) && $response['success']) {
        echo "Status for version $version updated to $status successfully.\n";
    } else {
        echo "Error: " . ($response['message'] ?? "Unknown error") . "\n";
    }
}

echo "Enter the version number to update: ";
$version = trim(fgets(STDIN));

echo "Enter the status (passed/failed/new): ";
$status = trim(fgets(STDIN));

if (!in_array($status, ['passed', 'failed', 'new'])) {
    echo "Invalid status. Please enter 'passed', 'failed', or 'new'.\n";
    exit;
}

updateStatus($version, $status);
?>
