# PowerShell script to export certificate from keystore for Google Play App Signing
# This script will prompt you for the keystore password

Write-Host "Exporting certificate from keystore..." -ForegroundColor Green
Write-Host ""

$keystoreFile = "mq-release-key.jks"
$alias = "key0"  # Change this if your alias is different
$outputFile = "upload_certificate.pem"

# Check if keystore exists
if (-not (Test-Path $keystoreFile)) {
    Write-Host "Error: Keystore file '$keystoreFile' not found!" -ForegroundColor Red
    exit 1
}

# First, check what aliases exist
Write-Host "Checking keystore contents..." -ForegroundColor Cyan
keytool -list -keystore $keystoreFile

Write-Host ""
Write-Host "Attempting to export certificate with alias: $alias" -ForegroundColor Yellow
Write-Host "You will be prompted for the keystore password." -ForegroundColor Yellow
Write-Host ""

# Export certificate in RFC (PEM) format
keytool -export -rfc -keystore $keystoreFile -alias $alias -file $outputFile

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "Certificate exported successfully!" -ForegroundColor Green
    Write-Host "Certificate file: $outputFile" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "Next steps:" -ForegroundColor Yellow
    Write-Host "1. Upload this certificate to Google Play Console" -ForegroundColor White
    Write-Host "2. Go to: Play Console > Your App > Setup > App Signing" -ForegroundColor White
    Write-Host "3. Upload the upload_certificate.pem file" -ForegroundColor White
} else {
    Write-Host ""
    Write-Host "Export failed. Possible reasons:" -ForegroundColor Red
    Write-Host "- Wrong keystore password" -ForegroundColor Yellow
    Write-Host "- Alias doesn't exist (check the alias name above)" -ForegroundColor Yellow
    Write-Host "- Keystore entry has no certificate" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "If the keystore has no certificate, you need to:" -ForegroundColor Yellow
    Write-Host "1. Create a new keystore with: keytool -genkeypair ..." -ForegroundColor White
    Write-Host "2. Or import an existing certificate into the keystore" -ForegroundColor White
}

