# PowerShell script to download AKB logo and set up as app icon

$logoUrl = "https://akbchemistry.com/akb-logo.png"
$outputDir = "app\src\main\res\drawable"
$logoFile = "$outputDir\akb_logo.png"

Write-Host "Downloading AKB logo from $logoUrl..." -ForegroundColor Green

# Create directory if it doesn't exist
if (-not (Test-Path $outputDir)) {
    New-Item -ItemType Directory -Path $outputDir -Force | Out-Null
}

# Download the logo
try {
    Invoke-WebRequest -Uri $logoUrl -OutFile $logoFile -UseBasicParsing
    Write-Host "Logo downloaded successfully to: $logoFile" -ForegroundColor Green
    
    # Check file size
    $fileInfo = Get-Item $logoFile
    Write-Host "File size: $($fileInfo.Length) bytes" -ForegroundColor Cyan
    
    Write-Host "`nNext steps:" -ForegroundColor Yellow
    Write-Host "1. The logo has been downloaded to: $logoFile" -ForegroundColor White
    Write-Host "2. Update ic_launcher_foreground.xml to reference this logo" -ForegroundColor White
    Write-Host "3. Or use Android Asset Studio to generate proper launcher icons" -ForegroundColor White
    
} catch {
    Write-Host "Error downloading logo: $_" -ForegroundColor Red
    Write-Host "You may need to download it manually and place it in: $outputDir" -ForegroundColor Yellow
}

