<?php
require_once 'rabbitMQLib.inc';

/* --------------------------------------------------------------------------
 * CONFIGURATION – edit once and commit
 * -------------------------------------------------------------------------- */
$home           = getenv('HOME');
$localTarPath   = $home . '/IT490-Project/bundles';
$environments = [
    'qa' => [
        // QA Web Server
        [
            'user'       => 'website',
            'ip'         => '100.81.254.20',
            'destDir'    => '/home/website/IT490-Project/bundles',
            'extractDir' => '/home/website/IT490-Project',
        ],
        // QA RabbitMQ
        [
            'user'       => 'rabbitdb',
            'ip'         => '100.118.122.58',
            'destDir'    => '/home/rabbitdb/IT490-Project/bundles',
            'extractDir' => '/home/rabbitdb/IT490-Project',
        ],
        // QA DMZ/API
        [
            'user'       => 'dmz',
            'ip'         => '100.79.203.96',
            'destDir'    => '/home/dmz/IT490-Project/bundles',
            'extractDir' => '/home/dmz/IT490-Project',
        ],
    ],

    'prod' => [
        // Prod Web Server
        [
            'user'       => 'website',
            'ip'         => '100.125.78.20',
            'destDir'    => '/home/website/IT490-Project/bundles',
            'extractDir' => '/home/website/IT490-Project',
        ],
        // Prod RabbitMQ
        [
            'user'       => 'rabbitdb',
            'ip'         => '100.80.16.14',
            'destDir'    => '/home/rabbitdb/IT490-Project/bundles',
            'extractDir' => '/home/rabbitdb/IT490-Project',
        ],
        // Prod DMZ/API
        [
            'user'       => 'dmz',
            'ip'         => '100.116.129.14',
            'destDir'    => '/home/dmz/IT490-Project/bundles',
            'extractDir' => '/home/dmz/IT490-Project',
        ],
    ],
];

/* --------------------------------------------------------------------------
 * PUSH HELPERS
 * -------------------------------------------------------------------------- */
function pushBundleToHost(string $localBundle, array $host): bool
{
    // Ensure destination path is a directory on the remote host
    $sanitizeCmd = "ssh {$host['user']}@{$host['ip']} "
                 . "\"if [ -f '{$host['destDir']}' ]; then rm -f '{$host['destDir']}'; fi; "
                 . "mkdir -p '{$host['destDir']}'\"";
    exec($sanitizeCmd, $_, $code);
    if ($code !== 0) {
        echo "    ❌  Failed to prepare destDir on {$host['ip']}\n";
        return false;
    }

    $remote = "{$host['user']}@{$host['ip']}:{$host['destDir']}";
    echo "  • Copying to $remote …\n";
    exec("scp -q -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null $localBundle $remote", $_, $code);
    if ($code !== 0) {
        echo "    ❌  SCP failed\n";
        return false;
    }

    $bundleName   = basename($localBundle);
    $remoteBundle = $host['destDir'].'/'.$bundleName;
    $cmd          = "ssh -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null "
                  . "{$host['user']}@{$host['ip']} "
                  . "'tar -xzvf $remoteBundle --strip-components=2 -C {$host['extractDir']}'";
    exec($cmd, $_, $code);
    if ($code !== 0) {
        echo "    ❌  Extract failed on {$host['ip']}\n";
        return false;
    }

    echo "    ✅  Finished on {$host['ip']}\n";
    return true;
}

function pushVersion(string $version, string $env, array $environments, string $localTarPath): bool
{
    // Attempt to find the bundle file by expected naming convention
    $localBundleBase = "website-version-{$version}.tar.gz";
    $localBundlePath = "$localTarPath/$localBundleBase";
    if (!file_exists($localBundlePath)) {
        echo "Bundle not found at $localBundlePath\n";
        return false;
    }
    $localBundle = $localBundlePath;
    if (!isset($environments[$env])) {
        echo "Unknown environment: $env\n";
        return false;
    }

    echo "\n▶ Pushing version $version to $env …\n";
    foreach ($environments[$env] as $host) {
        if (!pushBundleToHost($localBundle, $host)) {
            return false;
        }
    }
    echo "✔ Version $version deployed to $env successfully.\n";
    return true;
}

/* --------------------------------------------------------------------------
 * STATUS / ROLLBACK HELPERS
 * -------------------------------------------------------------------------- */
function sendStatus(string $version, string $status): void
{
    $client  = new rabbitMQClient('deploymentServer.ini', 'deploymentServer');
    $request = [
        'type'           => 'updateStatus',
        'version_number' => $version,
        'status'         => $status,
    ];
    $resp = $client->send_request($request);

    if (isset($resp['success']) && $resp['success']) {
        echo "Status '$status' recorded.\n";
        // if the server kicked off a rollback, surface that
        if (isset($resp['rollback']) && $resp['rollback'] === 'success') {
            echo "Rollback to {$resp['previous_version']} completed successfully.\n";
        } elseif (isset($resp['rollback']) && $resp['rollback'] !== 'success') {
            echo "Rollback status: {$resp['rollback']}.\n";
            if (isset($resp['message'])) {
                echo "Server message: {$resp['message']}\n";
            }
        }
    } else {
        echo "Failed to record status.\n";
        if (isset($resp['message'])) {
            echo "Server message: {$resp['message']}\n";
        }
    }
}

function revertToPrevious(string $version): void
{
    $maxTries = 3;
    $delayUs  = 200_000;  // 0.2 s between retries

    for ($attempt = 1; $attempt <= $maxTries; $attempt++) {
        try {
            $client  = new rabbitMQClient('deploymentServer.ini', 'deploymentServer');
            $resp    = $client->send_request([
                'type'            => 'revertVersion',
                'current_version' => $version,
            ]);

            echo isset($resp['success']) && $resp['success']
                 ? "Rollback to {$resp['previous_version']} initiated.\n"
                 : "Rollback failed: " . ($resp['message'] ?? 'Unknown error') . "\n";
            return; // success path (or graceful failure with message)
        } catch (Throwable $e) {
            if ($attempt === $maxTries) {
                echo "Rollback failed: " . $e->getMessage() . "\n";
                return;
            }
            usleep($delayUs); // wait then retry
        }
    }
}

/* --------------------------------------------------------------------------
 * INTERACTIVE FLOW
 * -------------------------------------------------------------------------- */
echo "Enter version number: ";
$version = trim(fgets(STDIN));

echo "Target environment (qa / prod / both): ";
$envInput = strtolower(trim(fgets(STDIN)));
if (!in_array($envInput, ['qa', 'prod', 'both'], true)) {
    echo "Invalid environment selection.\n";
    exit(1);
}

/* Step 1: always handle QA first if requested */
$qaNeeded = ($envInput === 'qa' || $envInput === 'both');
if ($qaNeeded && !pushVersion($version, 'qa', $environments, $localTarPath)) {
    exit(1);
}

/* Step 2: if QA was involved, get pass/fail */
if ($qaNeeded) {
    echo "\nDid QA pass? (passed/failed): ";
    $qaStatus = strtolower(trim(fgets(STDIN)));
    if (!in_array($qaStatus, ['passed', 'failed'], true)) {
        echo "Invalid QA status.\n";
        exit(1);
    }

    sendStatus($version, $qaStatus);

    if ($qaStatus === 'failed') {
        revertToPrevious($version);
        exit;       // stop; nothing should go to prod
    }
}

/* Step 3: push to prod if requested */
if ($envInput === 'prod' || $envInput === 'both') {
    pushVersion($version, 'prod', $environments, $localTarPath);
}