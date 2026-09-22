# Quick Start: Fix Deep Links for Android 12+

## What's the Problem?
Android 12+ requires apps to verify deep links using a digital asset links file hosted on your website. Without this, users will see an app chooser dialog instead of your app opening directly.

## What Was Fixed?
✅ Changed `android:autoVerify="true"` in AndroidManifest.xml

## What You Need to Do Now:

### 1️⃣ Get Your SHA-256 Fingerprints
```powershell
.\get_sha256_fingerprint.ps1
```
This gives you the fingerprint from your local keystore.

**ALSO** get the Google Play signing key fingerprint:
- Go to Google Play Console → Your App → Release → Setup → App Integrity
- Copy the SHA-256 certificate fingerprint

### 2️⃣ Update assetlinks.json
Edit `assetlinks.json` and replace both placeholder fingerprints with:
- Your upload key SHA-256 (from step 1)
- Your Google Play signing key SHA-256 (from Play Console)

### 3️⃣ Upload to Your Website
Upload `assetlinks.json` to:
```
https://aceacademybd.com/.well-known/assetlinks.json
```

**Important:** Must be accessible via HTTPS!

### 4️⃣ Test
Visit in browser: https://aceacademybd.com/.well-known/assetlinks.json
- Should display the JSON with your fingerprints

### 5️⃣ Rebuild and Test
```powershell
.\build.sh
```

Install on Android 12+ device and test opening: https://aceacademybd.com/student_portal

## Need More Help?
See detailed guide: [DEEP_LINKS_SETUP.md](DEEP_LINKS_SETUP.md)

## Quick Check
☐ Ran get_sha256_fingerprint.ps1  
☐ Got Google Play signing key from Play Console  
☐ Updated assetlinks.json with BOTH fingerprints  
☐ Uploaded to https://aceacademybd.com/.well-known/assetlinks.json  
☐ Verified file is accessible via HTTPS  
☐ Rebuilt the app  
☐ Tested on Android 12+ device  
