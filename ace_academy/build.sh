#!/bin/bash

# Build script for Ace Academy Android App
# This script builds the release version of the app

echo "======================================"
echo "Building Ace Academy Android App"
echo "======================================"

# Clean previous builds
echo "Cleaning previous builds..."
./gradlew clean

# Build release AAB (Android App Bundle)
echo "Building release AAB..."
./gradlew bundleRelease

# Check if build was successful
if [ $? -eq 0 ]; then
    echo ""
    echo "======================================"
    echo "Build Successful!"
    echo "======================================"
    echo "Release AAB location:"
    echo "app/release/app-release.aab"
    echo ""
    echo "You can now upload this to Google Play Console"
else
    echo ""
    echo "======================================"
    echo "Build Failed!"
    echo "======================================"
    exit 1
fi
