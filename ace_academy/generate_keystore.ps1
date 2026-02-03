# PowerShell script to generate keystore for app signing
# Usage: .\generate_keystore.ps1

$keystoreName = "ace-academy-release-key.jks"
$keyAlias = "ace_academy_key"

Write-Host "======================================"
Write-Host "Generate Keystore for Ace Academy"
Write-Host "======================================"
Write-Host ""
Write-Host "This will create a keystore file for signing your app."
Write-Host "You will be asked to provide:"
Write-Host "  - Keystore password"
Write-Host "  - Key password"
Write-Host "  - Your name and organization details"
Write-Host ""

# Check if keytool is available
$keytoolPath = "keytool"
try {
    & $keytoolPath -help 2>&1 | Out-Null
} catch {
    Write-Host "Error: keytool not found!" -ForegroundColor Red
    Write-Host "Please ensure Java JDK is installed and in your PATH"
    exit 1
}

# Generate keystore
Write-Host "Generating keystore..."
& $keytoolPath -genkey -v -keystore $keystoreName -alias $keyAlias -keyalg RSA -keysize 2048 -validity 10000

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "======================================"
    Write-Host "Keystore Generated Successfully!"
    Write-Host "======================================"
    Write-Host ""
    Write-Host "Keystore file: $keystoreName"
    Write-Host "Key alias: $keyAlias"
    Write-Host ""
    Write-Host "IMPORTANT: Keep this file and passwords safe!"
    Write-Host "You will need them to sign future updates."
    Write-Host ""
    Write-Host "Now update keystore.properties file with your passwords."
} else {
    Write-Host ""
    Write-Host "Failed to generate keystore!" -ForegroundColor Red
    exit 1
}
