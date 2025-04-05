<?php
require_once('rabbitMQLib.inc');

$localTarPath = "/home/website/IT490-Project/bundles"; 
$extractionPath = "/home/website/IT490-Project"; 

$deploymentServerUser = "deployment";    
$deploymentServerIP = "100.76.144.81"; 

function pullVersion($versionNumber, $bundlePath) {
    global $localTarPath, $extractionPath, $deploymentServerUser, $deploymentServerIP;

    echo "Pulling version $versionNumber from $bundlePath...\n";

    $command = "scp $deploymentServerUser@$deploymentServerIP:$bundlePath $localTarPath";
    exec($command, $output, $status);

    if ($status === 0) {
        echo "Successfully pulled version $versionNumber.\n";
        
        // Local path of the tarball
        $localBundle = $localTarPath . '/' . basename($bundlePath);

        // Adjust the strip-components value to match your tar structure
        $install = "tar -xzvf $localBundle --strip-components=2 -C $extractionPath";
        exec($install);
        return true;
    } else {
        echo "Failed to pull version $versionNumber.\n";
        return false;
    }
}

function pullSpecificVersion($versionNumber) {
    $client = new rabbitMQClient("deploymentServer.ini", "deploymentServer");

    $request = [
        "type" => "pullSpecificVersion",
        "version_number" => $versionNumber
    ];
    $response = $client->send_request($request);

    if ($response['success']) {
        $bundlePath = $response['bundle_path'];
        return pullVersion($versionNumber, $bundlePath);
    } else {
        echo "Error: " . $response['message'] . "\n";
        return false;
    }
}

echo "Enter the version number to pull (e.g., v1.0.0): ";
$versionNumber = trim(fgets(STDIN));

if (!empty($versionNumber)) {
    pullSpecificVersion($versionNumber);
} else {
    echo "Invalid version number.\n";
}
?>
