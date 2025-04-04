<?php
require_once('rabbitMQLib.inc');
require_once('get_host_info.inc');
require_once('path.inc');

// Database configuration
$dbHost = 'localhost';
$dbName = 'it490db';
$dbUser = 'testUser';
$dbPassword = '12345';

/**
 * 1. Pull the latest bundle with status = 'passed'
 *    from the bundles table
 */
function getLatestPassedVersion() {
    global $dbHost, $dbName, $dbUser, $dbPassword;

    try {
        $db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPassword);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Get the latest bundle where status='passed'
        $stmt = $db->query("SELECT * FROM bundles WHERE status = 'passed' ORDER BY id DESC LIMIT 1");
        $latestPassed = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($latestPassed) {
            return [
                "success"        => true,
                "version_number" => $latestPassed['version_number'],
                "bundle_name"    => $latestPassed['bundle_name']
            ];
        } else {
            return ["success" => false, "message" => "No bundles found with status = 'passed'."];
        }
    } catch (Exception $e) {
        return ["success" => false, "message" => $e->getMessage()];
    }
}

/**
 * 2. Insert a new bundle into the bundles table,
 *    then record an entry in deployment_history
 *    for environment logs or tracking.
 */
function addVersionToDatabase($versionNumber, $bundlePath) {
    global $dbHost, $dbName, $dbUser, $dbPassword;

    try {
        $db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPassword);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Insert into bundles: we assume a default bundle_name or you can pass it as a param
        $bundleName = "DEAA";

        // 1) Insert the new bundle as status 'new'
        $stmt = $db->prepare("
            INSERT INTO bundles (bundle_name, version_number, status)
            VALUES (:bundle_name, :version_number, 'new')
        ");
        $stmt->bindParam(':bundle_name', $bundleName);
        $stmt->bindParam(':version_number', $versionNumber);
        $stmt->execute();

        // Get the newly inserted bundle's ID
        $bundleId = $db->lastInsertId();

        // 2) Insert a record in deployment_history (optional environment = 'Development', logs = bundlePath)
        //    If you prefer a different environment or no environment, adjust as needed.
        $environment = 'Development';
        $historyStatus = 'success';  // or 'failure' if something goes wrong
        $logs = "Bundle path: " . $bundlePath;

        $histStmt = $db->prepare("
            INSERT INTO deployment_history (bundle_id, environment, status, logs)
            VALUES (:bundle_id, :environment, :status, :logs)
        ");
        $histStmt->bindParam(':bundle_id', $bundleId);
        $histStmt->bindParam(':environment', $environment);
        $histStmt->bindParam(':status', $historyStatus);
        $histStmt->bindParam(':logs', $logs);
        $histStmt->execute();

        // Optionally set the status in bundles to 'new' (already done in the insert).
        // If you want a follow-up action, you can do it here.
        updateDeploymentStatus($versionNumber, "new");

        return ["success" => true, "message" => "Version $versionNumber added to bundles and deployment_history."];
    } catch (Exception $e) {
        return ["success" => false, "message" => $e->getMessage()];
    }
}

/**
 * 3. Update the bundle's status in the bundles table
 *    (e.g., from 'new' to 'passed' or 'failed').
 */
function updateDeploymentStatus($versionNumber, $status) {
    global $dbHost, $dbName, $dbUser, $dbPassword;

    try {
        $db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPassword);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Check if the version exists in bundles
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM bundles WHERE version_number = :version");
        $checkStmt->bindParam(':version', $versionNumber);
        $checkStmt->execute();

        if ($checkStmt->fetchColumn() == 0) {
            return ["success" => false, "message" => "Version $versionNumber not found in bundles."];
        }

        // Update the status in bundles
        $updateStmt = $db->prepare("
            UPDATE bundles
            SET status = :status
            WHERE version_number = :version
        ");
        $updateStmt->bindParam(':status', $status);
        $updateStmt->bindParam(':version', $versionNumber);
        $updateStmt->execute();

        return ["success" => true, "message" => "Bundle $versionNumber status updated to $status."];
    } catch (Exception $e) {
        return ["success" => false, "message" => $e->getMessage()];
    }
}

/**
 * 4. Pull the very latest bundle entry from bundles (regardless of status).
 */
function getLatestVersion() {
    global $dbHost, $dbName, $dbUser, $dbPassword;

    try {
        $db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPassword);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $db->query("SELECT * FROM bundles ORDER BY id DESC LIMIT 1");
        $latest = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($latest) {
            return [
                "success"        => true,
                "version_number" => $latest['version_number'],
                "bundle_name"    => $latest['bundle_name'],
                "status"         => $latest['status']
            ];
        } else {
            return ["success" => false, "message" => "No versions found in bundles."];
        }
    } catch (Exception $e) {
        return ["success" => false, "message" => $e->getMessage()];
    }
}

/**
 * 5. Pull a specific version from bundles by version_number.
 */
function getSpecificVersion($versionNumber) {
    global $dbHost, $dbName, $dbUser, $dbPassword;

    try {
        $db = new PDO("mysql:host=$dbHost;dbname=$dbName", $dbUser, $dbPassword);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $db->prepare("SELECT * FROM bundles WHERE version_number = :version");
        $stmt->bindParam(':version', $versionNumber);
        $stmt->execute();
        $specific = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($specific) {
            return [
                "success"        => true,
                "version_number" => $specific['version_number'],
                "bundle_name"    => $specific['bundle_name'],
                "status"         => $specific['status']
            ];
        } else {
            return ["success" => false, "message" => "Version $versionNumber not found in bundles."];
        }
    } catch (Exception $e) {
        return ["success" => false, "message" => $e->getMessage()];
    }
}

/**
 * Main request handler for RabbitMQ
 */
function handleRequest($request) {
    echo "Received request: ";
    var_dump($request);

    if (!isset($request['type'])) {
        return ["error" => "Invalid request type"];
    }

    switch ($request['type']) {

        case "pullLatestPassedVersion":
            return getLatestPassedVersion();

        case "updateStatus":
            $versionNumber = $request['version_number'];
            $status       = $request['status'];  // e.g. 'passed' or 'failed'
            return updateDeploymentStatus($versionNumber, $status);

        case "pullLatestVersion":
            return getLatestVersion();

        case "pullSpecificVersion":
            $versionNumber = $request['version_number'];
            return getSpecificVersion($versionNumber);

        case "deploy":
            // 'deploy' expects a version_number and bundle_path from the request
            $versionNumber = $request['version_number'];
            $bundlePath    = $request['bundle_path'];
            return addVersionToDatabase($versionNumber, $bundlePath);

        default:
            return ["error" => "Unsupported request type"];
    }
}

$server = new rabbitMQServer("deploymentServer.ini", "deploymentServer");
echo "Deployment Server is running...\n";
$server->process_requests('handleRequest');
?>
