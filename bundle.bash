#!/bin/bash

echo "Choose the type of bundle:"
echo "1. website"
echo "2. rabbitdb"
echo "3. dmz"
read -p "Enter the number corresponding to your choice: " bundleChoice

case $bundleChoice in
    1)
        bundleName="website"
        ;;
    2)
        bundleName="rabbitdb"
        ;;
    3)
        bundleName="dmz"
        ;;
    *)
        echo "Invalid choice. Exiting..."
        exit 1
        ;;
esac

versionTrackerFile="/home/website/IT490-Project/bundles/versionTracker.txt"
latestVersionNum=$(tail -n 1 "$versionTrackerFile")

newVersionNum=$(($latestVersionNum + 1))
echo "New version number: $newVersionNum"

echo $newVersionNum > "$versionTrackerFile"

if [ $# -eq 0 ]; then
    read -p "Enter the full paths of files to bundle, separated by spaces: " inputPaths
    set -- $inputPaths
fi

# Define the directory where bundles are stored on this machine
bundlesDir="/home/website/IT490-Project/bundles"
bundleFileName="${bundlesDir}/${bundleName}-version-${newVersionNum}.tar.gz"

# Create the tar.gz archive in the bundles directory (removing the leading ./ from file names)
tar --transform='s|./||' -czf "$bundleFileName" "$@"

# Transfer the bundle to the deployment machine
#scp "$bundleFileName" deployment@100.76.144.81:/home/deployment/IT490-Project/bundles

# Trigger the deployment process
php "/home/website/IT490-Project/triggerDeployment2.php"

echo "Bundling and deployment process completed for $bundleName (version $newVersionNum)."
