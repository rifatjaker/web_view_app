#!/bin/bash

echo "========================================"
echo "  Building AKB Chemistry WebView App"
echo "========================================"
echo ""

# Check if gradlew exists
if [ ! -f "./gradlew" ]; then
    echo "ERROR: gradlew not found!"
    echo "Please generate Gradle wrapper first:"
    echo "  gradle wrapper --gradle-version 8.2"
    echo ""
    echo "Or open the project in Android Studio once to generate it automatically."
    exit 1
fi

# Make gradlew executable
chmod +x ./gradlew

# Build the APK
echo "Building debug APK..."
./gradlew assembleDebug

if [ $? -ne 0 ]; then
    echo ""
    echo "Build failed! Check errors above."
    exit 1
fi

echo ""
echo "========================================"
echo "  Build Successful!"
echo "========================================"
echo ""
echo "APK location: app/build/outputs/apk/debug/app-debug.apk"
echo ""

# Check if ADB is available
if command -v adb &> /dev/null; then
    echo "Checking for connected devices..."
    adb devices
    
    echo ""
    read -p "Install on connected device? (Y/N): " INSTALL
    if [[ "$INSTALL" == "Y" || "$INSTALL" == "y" ]]; then
        echo ""
        echo "Installing APK..."
        adb install -r app/build/outputs/apk/debug/app-debug.apk
        
        if [ $? -eq 0 ]; then
            echo ""
            echo "Launching app..."
            adb shell am start -n com.akbchemistry.webview/.MainActivity
            echo ""
            echo "App installed and launched!"
        else
            echo ""
            echo "Installation failed. Make sure:"
            echo "- USB Debugging is enabled on your device"
            echo "- Device is connected via USB"
            echo "- ADB is in your PATH"
        fi
    fi
else
    echo ""
    echo "ADB not found in PATH."
    echo "To install on device, add Android SDK platform-tools to PATH"
    echo "Or manually install: app/build/outputs/apk/debug/app-debug.apk"
fi

echo ""

