# Ace Academy - Android WebView App

A professional WebView application for Ace Academy's student portal, featuring enhanced UI design and interactive elements.

## Features

- 🌐 Full WebView implementation for https://aceacademybd.com/student_portal/
- 🔒 Screenshot and screen recording protection
- 🎨 Beautiful gradient-based UI design with animations
- 📱 Pull-to-refresh functionality
- 📄 Automatic PDF handling via Google Docs viewer
- 🕐 Real-time clock display in footer
- 📊 Progress bar for page loading
- 🔄 Smooth page transitions with animations
- 📲 Deep linking support for portal URLs (Android 12+ verified)
- 🎯 Material Design components
- 🖼️ Custom navigation bar with logo and teacher photo
- ⚡ Optimized for Android 15 (API 35)

## App Details

- **App Name**: Ace Academy
- **Package**: com.arif.sir.ace_academy
- **Target SDK**: 35 (Android 15 and above)
- **Min SDK**: 21 (Android 5.0 Lollipop)
- **Version**: 1.0

## Setup Instructions

### Prerequisites

- Android Studio Arctic Fox or later
- JDK 8 or higher
- Android SDK 35

### Building the App

1. **Download Assets** (Logo and Teacher Photo):
   ```powershell
   .\download_assets.ps1
   ```

2. **Setup Local Properties**:
   - Copy `local.properties.example` to `local.properties`
   - Update SDK path if needed

3. **Setup Keystore** (for release builds):
   - Generate keystore:
     ```powershell
     .\generate_keystore.ps1
     ```
   - Copy `keystore.properties.example` to `keystore.properties`
   - Update with your keystore credentials

4. **Build Release AAB**:
   ```bash
   ./build.sh
   ```
   Or use Gradle directly:
   ```bash
   ./gradlew bundleRelease
   ```

5. **Setup Deep Links** (Required for Android 12+ and Google Play):
   ```powershell
   # Get SHA-256 fingerprint
   .\get_sha256_fingerprint.ps1
   ```
   Then follow the detailed instructions in [DEEP_LINKS_SETUP.md](DEEP_LINKS_SETUP.md)
   
   **Quick Summary:**
   - Update `assetlinks.json` with your SHA-256 fingerprints
   - Upload it to `https://aceacademybd.com/.well-known/assetlinks.json`
   - This enables direct app opening from web URLs on Android 12+

### Installing on Device

#### Debug Build:
```bash
./gradlew installDebug
```

#### Release Build:
1. Build the AAB file (see above)
2. Upload to Google Play Console, OR
3. Generate APK for direct installation:
   ```bash
   ./gradlew assembleRelease
   ```

## Project Structure

```
ace_academy/
├── app/
│   ├── src/main/
│   │   ├── java/com/arif/sir/ace_academy/
│   │   │   └── MainActivity.java
│   │   ├── res/
│   │   │   ├── layout/
│   │   │   │   └── activity_main.xml
│   │   │   ├── drawable/
│   │   │   │   ├── gradient_header.xml
│   │   │   │   ├── gradient_footer.xml
│   │   │   │   ├── circle_border.xml
│   │   │   │   └── ic_*.xml (icons)
│   │   │   ├── anim/
│   │   │   │   ├── fade_in.xml
│   │   │   │   ├── slide_down.xml
│   │   │   │   └── pulse.xml
│   │   │   └── values/
│   │   │       ├── colors.xml
│   │   │       ├── strings.xml
│   │   │       └── styles.xml
│   │   └── AndroidManifest.xml
│   ├── build.gradle
│   └── proguard-rules.pro
├── build.gradle
├── settings.gradle
├── gradle.properties
└── Scripts (PowerShell)
```

## Enhanced UI Features

### Navigation Bar
- Gradient background with custom colors
- Circular logo with card elevation
- Company name with subtitle
- Circular teacher photo with border and pulse animation
- Slide-down entrance animation

### WebView Area
- Pull-to-refresh with custom color scheme
- Smooth progress bar
- Fade-in animation on page load
- Automatic PDF handling

### Footer
- Gradient background
- Real-time date/time with clock icon
- Android version display with Android icon
- Copyright notice with divider
- Fade-in entrance animation

## Color Scheme

- **Primary**: #1565C0 (Blue)
- **Primary Dark**: #0D47A1 (Dark Blue)
- **Accent**: #FF6F00 (Orange)
- **Background**: #F5F5F5 (Light Gray)

## Security Features

- Screenshot protection (FLAG_SECURE)
- Screen recording prevention
- Secure WebView configuration
- HTTPS enforcement with cleartext traffic support for compatibility

## WebView Configuration

- JavaScript enabled
- DOM Storage enabled
- Cookie support for sessions
- File access enabled
- Mixed content allowed
- Zoom controls enabled
- PDF handling via Google Docs viewer

## Support

For issues or questions, contact:
- **Developer**: Arif Sir
- **Organization**: Ace Academy
- **Website**: https://aceacademybd.com

## License

© 2026 Ace Academy - Arif Sir. All rights reserved.
