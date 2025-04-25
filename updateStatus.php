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

/**
 * Ask the deployment server to roll back to the last passed version.
 */
function revertToPreviousVersion($currentVersion) {
    $client = new rabbitMQClient("deploymentServer.ini", "deploymentServer");

    $request = [
        "type"           => "revertVersion",
        "current_version"=> $currentVersion
    ];

    $response = $client->send_request($request);

    if (isset($response['success']) && $response['success']) {
        echo "Rollback to version {$response['previous_version']} initiated successfully.\n";
    } else {
        echo "Rollback failed: " . ($response['message'] ?? 'Unknown error') . "\n";
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

// Automatically trigger rollback if this build failed
if ($status === 'failed') {
    revertToPreviousVersion($version);
}
?>
