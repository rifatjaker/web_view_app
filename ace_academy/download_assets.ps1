# PowerShell script to download the logo from the website
# Usage: .\download_logo.ps1

$logoUrl = "https://aceacademybd.com/student_portal/ace-logo.png"
$outputPath = "app\src\main\res\drawable\ace_logo.png"
$teacherUrl = "https://aceacademybd.com/student_portal/arif_sir.png"
$teacherPath = "app\src\main\res\drawable\arif_sir.png"

Write-Host "======================================"
Write-Host "Downloading Ace Academy Assets"
Write-Host "======================================"

# Create directory if it doesn't exist
$directory = Split-Path -Parent $outputPath
if (!(Test-Path $directory)) {
    New-Item -ItemType Directory -Path $directory -Force | Out-Null
}

try {
    # Download logo
    Write-Host "Downloading logo from: $logoUrl"
    Invoke-WebRequest -Uri $logoUrl -OutFile $outputPath
    Write-Host "Logo downloaded successfully to: $outputPath"
    
    # Download teacher photo
    Write-Host "Downloading teacher photo from: $teacherUrl"
    Invoke-WebRequest -Uri $teacherUrl -OutFile $teacherPath
    Write-Host "Teacher photo downloaded successfully to: $teacherPath"
    
    Write-Host ""
    Write-Host "======================================"
    Write-Host "Download Complete!"
    Write-Host "======================================"
} catch {
    Write-Host "Error downloading assets: $_" -ForegroundColor Red
    Write-Host ""
    Write-Host "Please ensure:"
    Write-Host "1. You have internet connection"
    Write-Host "2. The URLs are accessible"
    Write-Host "3. You have write permissions"
    exit 1
}
