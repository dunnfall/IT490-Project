<?php
require_once('rabbitMQLib.inc');

$versionTrackerFile = "/home/website/IT490-Project/bundles/versionTracker.txt";

$version_number = "";
if (file_exists($versionTrackerFile)) {
    $lines = file($versionTrackerFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $version_number = end($lines);
} else {
    die("Error: versionTracker.txt file not found.\n");
}

if (empty($version_number)) {
    die("Error: Could not determine the latest version from versionTracker.txt.\n");
}

$bundlingDir = "/home/website/IT490-Project/bundles";
$deploymentDir = "/home/deployment/IT490-Project/bundles";

$bundleFiles = glob("$bundlingDir/*-version-$version_number.tar.gz");

if (empty($bundleFiles)) {
    die("Error: No bundle found for version $version_number in $bundlingDir.\n");
}

$bundleFileName = basename($bundleFiles[0]);
preg_match('/^(website|rabbitdb|dmz)-version-/', $bundleFileName, $matches);

if (empty($matches[1])) {
    die("Error: Could not determine the bundle type from the file name.\n");
}

$bundleType = $matches[1];
$bundlePath = "$deploymentDir/$bundleFileName";


echo "Transferring $bundleFileName to the deployment machine...\n";
$scpCommand = "scp $bundlingDir/$bundleFileName deployment@100.76.144.81:$bundlePath";
exec($scpCommand, $output, $result);

if ($result !== 0) {
    die("Error: Failed to transfer the bundle to the deployment machine.\n");
}

try {
    $client = new rabbitMQClient("deploymentServer.ini", "deploymentServer");

    $request = [
        "type" => "deploy",
        "version_number" => $version_number,
        "bundle_path" => $bundlePath,
        "bundle_type" => $bundleType
    ];

    echo "Sending deployment request for version $version_number ($bundleType)...\n";
    $response = $client->send_request($request);

    if ($response['success']) {
        echo "Deployment Successful: " . $response['message'] . "\n";
    } else {
        echo "Deployment Failed: " . $response['message'] . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}