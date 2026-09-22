# Deep Links Setup Guide for Android 12+

## Overview
Android 12 and above require proper verification of deep links to accept traffic from web URLs. This guide will help you set up Android App Links correctly.

## What Changed?
- Changed `android:autoVerify="false"` to `android:autoVerify="true"` in AndroidManifest.xml
- This enables automatic verification of your app links on Android 12+

## Steps to Complete Setup

### Step 1: Get SHA-256 Fingerprints

You need TWO SHA-256 fingerprints:

#### A. Upload Key Fingerprint (from your local keystore)
Run the provided script:
```powershell
.\get_sha256_fingerprint.ps1
```

This will display your upload key's SHA-256 fingerprint and copy it to clipboard.

#### B. App Signing Key Fingerprint (from Google Play Console)
1. Go to [Google Play Console](https://play.google.com/console)
2. Select your app (Ace Academy)
3. Navigate to: **Release** → **Setup** → **App Integrity**
4. Under "App signing key certificate", copy the **SHA-256 certificate fingerprint**

### Step 2: Update assetlinks.json

Edit `assetlinks.json` and replace the placeholder fingerprints:

```json
[
  {
    "relation": ["delegate_permission/common.handle_all_urls"],
    "target": {
      "namespace": "android_app",
      "package_name": "com.arif.sir.ace_academy",
      "sha256_cert_fingerprints": [
        "YOUR_UPLOAD_KEY_SHA256_HERE",
        "YOUR_GOOGLE_PLAY_SIGNING_KEY_SHA256_HERE"
      ]
    }
  }
]
```

**Important Notes:**
- Fingerprints should be in uppercase with colons (e.g., `AB:CD:EF:12:34:...`)
- Include BOTH fingerprints if using Google Play App Signing
- If only testing locally without Google Play, you can use just the upload key fingerprint

### Step 3: Upload assetlinks.json to Your Website

Upload the `assetlinks.json` file to your domain at this exact location:
```
https://aceacademybd.com/.well-known/assetlinks.json
```

**Requirements:**
- Must be accessible via HTTPS (not HTTP)
- Must return `Content-Type: application/json`
- Must be publicly accessible (no authentication required)
- Must not redirect

**On cPanel or similar hosting:**
1. Create folder: `public_html/.well-known/`
2. Upload `assetlinks.json` to that folder
3. Verify it's accessible at the URL above

### Step 4: Add .htaccess Rules (if needed)

If you're on Apache/cPanel, create or update `.htaccess` in the `.well-known` directory:

```apache
# Allow access to .well-known directory
<Files "assetlinks.json">
    Header set Content-Type "application/json"
    Header set Access-Control-Allow-Origin "*"
</Files>
```

### Step 5: Verify the Setup

#### Test the assetlinks.json URL
Visit in browser: `https://aceacademybd.com/.well-known/assetlinks.json`

You should see the JSON content with your fingerprints.

#### Test with Google's Tool
Use Google's App Links Assistant:
1. Open your project in Android Studio
2. Go to: **Tools** → **App Links Assistant**
3. Click "Test App Links" and enter your domain
4. It will verify your configuration

#### Test on Device
1. Build and install your app
2. Open a browser on the device
3. Navigate to: `https://aceacademybd.com/student_portal`
4. The app should open directly (not show app chooser)

### Step 6: Rebuild and Test Your App

```powershell
# Clean and rebuild
.\gradlew clean
.\gradlew assembleRelease

# Or use the build script
.\build.sh
```

## Troubleshooting

### Deep links not working on Android 12+
- Verify `android:autoVerify="true"` is set in AndroidManifest.xml
- Check assetlinks.json is accessible via HTTPS
- Verify both SHA-256 fingerprints are correct
- Clear app data and reinstall the app

### assetlinks.json returns 404
- Ensure the `.well-known` directory exists
- Check file permissions (should be readable)
- Verify no redirects are happening

### App chooser still appears
- Wait a few minutes for Android to verify (can take up to 20 seconds after install)
- Force verification: `adb shell pm verify-app-links --re-verify com.arif.sir.ace_academy`
- Check verification status: `adb shell pm get-app-links com.arif.sir.ace_academy`

### Wrong SHA-256 fingerprint
- Make sure you're using the correct keystore (not debug keystore)
- If using Google Play App Signing, you MUST include the signing key fingerprint from Play Console
- Fingerprints are case-insensitive but should include colons

## Testing Commands

Check app link verification status:
```bash
adb shell pm get-app-links com.arif.sir.ace_academy
```

Force re-verification:
```bash
adb shell pm verify-app-links --re-verify com.arif.sir.ace_academy
```

Test opening a link:
```bash
adb shell am start -a android.intent.action.VIEW -d "https://aceacademybd.com/student_portal"
```

## Additional Resources
- [Android App Links Documentation](https://developer.android.com/training/app-links)
- [Verify Android App Links](https://developer.android.com/training/app-links/verify-android-applinks)
- [Digital Asset Links](https://developers.google.com/digital-asset-links/v1/getting-started)

## Support
If you encounter issues:
1. Check that assetlinks.json is publicly accessible
2. Verify both SHA-256 fingerprints are correct
3. Ensure the app package name matches: `com.arif.sir.ace_academy`
4. Test on a real device running Android 12+ (API 31+)
