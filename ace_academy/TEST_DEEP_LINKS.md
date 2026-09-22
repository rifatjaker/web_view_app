# Test Deep Links Configuration

## Your Current Configuration:
✅ File uploaded: https://aceacademybd.com/.well-known/assetlinks.json
✅ Package name: com.arif.sir.ace_academy
✅ SHA-256 fingerprint: 74:B2:F5:34:C5:A4:53:98:5A:B1:C7:83:DB:D2:0C:33:37:AF:13:6B:4E:B9:4F:D9:17:D3:F9:33:74:4F:65:CF

## Important: Verify Your JSON

The JSON should look EXACTLY like this (no trailing commas):
```json
[
  {
    "relation": [
      "delegate_permission/common.handle_all_urls",
      "delegate_permission/common.get_login_creds"
    ],
    "target": {
      "namespace": "android_app",
      "package_name": "com.arif.sir.ace_academy",
      "sha256_cert_fingerprints": [
        "74:B2:F5:34:C5:A4:53:98:5A:B1:C7:83:DB:D2:0C:33:37:AF:13:6B:4E:B9:4F:D9:17:D3:F9:33:74:4F:65:CF"
      ]
    }
  }
]
```

## Note About Fingerprints

If you're using Google Play App Signing:
- Google Play Console shows the **App signing key** fingerprint
- This is the MAIN fingerprint you need (which you have)
- You may also need your **Upload key** fingerprint for testing before publishing

To check if you need both:
1. In Play Console → App Integrity → "App signing key certificate" (this is what you have)
2. Look for "Upload key certificate" - if it exists and is different, add it too

## Testing Steps

### Step 1: Rebuild Your App
```powershell
cd ace_academy
.\build.sh
```

### Step 2: Install on Android 12+ Device
Upload the AAB to Play Console or generate APK for testing

### Step 3: Test the Deep Link

**Method 1: Via ADB**
```bash
# Clear app data first
adb shell pm clear com.arif.sir.ace_academy

# Install the app
adb install app-release.apk

# Wait 10-20 seconds for Android to verify

# Check verification status
adb shell pm get-app-links com.arif.sir.ace_academy

# Should show: "aceacademybd.com: 1024 (verified)"
```

**Method 2: On Device**
1. Open Chrome/Browser on your Android device
2. Navigate to: https://aceacademybd.com/student_portal
3. App should open directly (no app chooser dialog)

### Step 4: Force Re-verification (if needed)
```bash
adb shell pm verify-app-links --re-verify com.arif.sir.ace_academy
```

### Step 5: Check Status
```bash
adb shell pm get-app-links com.arif.sir.ace_academy
```

Expected output:
```
com.arif.sir.ace_academy:
    ID: [random-id]
    Signatures: [...]
    Domain verification state:
      aceacademybd.com: 1024
```

Status codes:
- 0 = none
- 1024 = verified ✅
- 2048 = selected
- 4096 = legacy_failure

## Troubleshooting

### If verification fails:
1. ✅ Check JSON syntax (no trailing commas)
2. ✅ Verify file is accessible: https://aceacademybd.com/.well-known/assetlinks.json
3. ✅ Ensure it returns `Content-Type: application/json`
4. ✅ Fingerprint matches exactly (including colons)
5. ✅ Wait at least 20 seconds after install for verification
6. ✅ Clear app data and reinstall

### If app chooser still appears:
- Android caches verification results
- Uninstall completely
- Reinstall and wait 20+ seconds
- Try force verification command above

## Next Steps

1. Rebuild your app with the updated AndroidManifest.xml
2. Upload to Play Console (Internal Testing track first)
3. Install and test on Android 12+ device
4. Verify deep links work before production release

## Ready to Test?

Run these commands to rebuild and prepare for testing:
```powershell
cd ace_academy
.\gradlew clean
.\build.sh
```

The AAB will be in: `app\release\app-release.aab`
