# Script to get SHA-256 fingerprint for Android App Links
# This fingerprint is needed for the assetlinks.json file

Write-Host "=====================================" -ForegroundColor Cyan
Write-Host "Get SHA-256 Fingerprint for App Links" -ForegroundColor Cyan
Write-Host "=====================================" -ForegroundColor Cyan
Write-Host ""

# Load keystore properties
$keystorePropsFile = ".\keystore.properties"
if (-Not (Test-Path $keystorePropsFile)) {
    Write-Host "ERROR: keystore.properties file not found!" -ForegroundColor Red
    Write-Host "Please create keystore.properties or run generate_keystore.ps1 first" -ForegroundColor Yellow
    exit 1
}

# Read properties
$props = @{}
Get-Content $keystorePropsFile | ForEach-Object {
    if ($_ -match '^\s*([^#][^=]+)\s*=\s*(.+)\s*$') {
        $props[$matches[1]] = $matches[2]
    }
}

$storeFile = $props['storeFile']
$storePassword = $props['storePassword']
$keyAlias = $props['keyAlias']
$keyPassword = $props['keyPassword']

if (-Not $storeFile -or -Not (Test-Path $storeFile)) {
    Write-Host "ERROR: Keystore file not found at: $storeFile" -ForegroundColor Red
    exit 1
}

Write-Host "Keystore: $storeFile" -ForegroundColor Green
Write-Host "Key Alias: $keyAlias" -ForegroundColor Green
Write-Host ""

# Get SHA-256 fingerprint
Write-Host "Getting SHA-256 fingerprint..." -ForegroundColor Yellow
Write-Host ""

$keytool = "keytool"
if ($env:JAVA_HOME) {
    $keytool = Join-Path $env:JAVA_HOME "bin\keytool.exe"
}

try {
    $output = & $keytool -list -v -keystore $storeFile -alias $keyAlias -storepass $storePassword -keypass $keyPassword 2>&1
    
    $sha256Line = $output | Select-String "SHA256:" | Select-Object -First 1
    
    if ($sha256Line) {
        $sha256 = $sha256Line -replace ".*SHA256:\s*", "" -replace "\s", ""
        
        Write-Host "=====================================" -ForegroundColor Green
        Write-Host "Your SHA-256 Fingerprint:" -ForegroundColor Green
        Write-Host $sha256 -ForegroundColor Yellow
        Write-Host "=====================================" -ForegroundColor Green
        Write-Host ""
        
        Write-Host "IMPORTANT: Copy this fingerprint and:" -ForegroundColor Cyan
        Write-Host "1. Update assetlinks.json with this fingerprint" -ForegroundColor White
        Write-Host "2. Upload assetlinks.json to your website at:" -ForegroundColor White
        Write-Host "   https://aceacademybd.com/.well-known/assetlinks.json" -ForegroundColor Yellow
        Write-Host ""
        Write-Host "NOTE: If you're using Google Play App Signing, you also need to:" -ForegroundColor Cyan
        Write-Host "1. Go to Google Play Console -> Your App -> Release -> Setup -> App Integrity" -ForegroundColor White
        Write-Host "2. Copy the SHA-256 certificate fingerprint from 'App signing key certificate'" -ForegroundColor White
        Write-Host "3. Add BOTH fingerprints to assetlinks.json" -ForegroundColor White
        Write-Host ""
        
        # Copy to clipboard if possible
        if (Get-Command Set-Clipboard -ErrorAction SilentlyContinue) {
            $sha256 | Set-Clipboard
            Write-Host "✓ SHA-256 fingerprint copied to clipboard!" -ForegroundColor Green
        }
        
    } else {
        Write-Host "ERROR: Could not extract SHA-256 fingerprint" -ForegroundColor Red
        Write-Host "Raw output:" -ForegroundColor Yellow
        Write-Host $output
    }
    
} catch {
    Write-Host "ERROR: Failed to get fingerprint: $_" -ForegroundColor Red
    Write-Host ""
    Write-Host "Make sure keytool is in your PATH or JAVA_HOME is set correctly" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
