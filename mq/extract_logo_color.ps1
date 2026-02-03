# PowerShell script to extract dominant color from MQ logo
Add-Type -AssemblyName System.Drawing

$logoPath = "app\src\main\res\drawable\mq_logo.png"

if (Test-Path $logoPath) {
    try {
        $bitmap = New-Object System.Drawing.Bitmap($logoPath)
        $colorCounts = @{}
        
        # Sample colors from the image (check every 10th pixel for performance)
        for ($x = 0; $x -lt $bitmap.Width; $x += 10) {
            for ($y = 0; $y -lt $bitmap.Height; $y += 10) {
                $pixel = $bitmap.GetPixel($x, $y)
                # Skip transparent/white pixels
                if ($pixel.A -gt 200 -and ($pixel.R + $pixel.G + $pixel.B) -lt 750) {
                    $colorKey = "$($pixel.R),$($pixel.G),$($pixel.B)"
                    if ($colorCounts.ContainsKey($colorKey)) {
                        $colorCounts[$colorKey]++
                    } else {
                        $colorCounts[$colorKey] = 1
                    }
                }
            }
        }
        
        $bitmap.Dispose()
        
        if ($colorCounts.Count -gt 0) {
            $dominantColor = $colorCounts.GetEnumerator() | Sort-Object Value -Descending | Select-Object -First 1
            $rgb = $dominantColor.Key -split ','
            $hexColor = "#{0:X2}{1:X2}{2:X2}" -f [int]$rgb[0], [int]$rgb[1], [int]$rgb[2]
            
            Write-Host "Dominant color found: $hexColor" -ForegroundColor Green
            Write-Host "RGB: R=$($rgb[0]), G=$($rgb[1]), B=$($rgb[2])" -ForegroundColor Cyan
            Write-Host ""
            Write-Host "Update colors.xml with: <color name=\"ic_launcher_background\">$hexColor</color>" -ForegroundColor Yellow
        } else {
            Write-Host "Could not extract color. Using default blue." -ForegroundColor Yellow
        }
    } catch {
        Write-Host "Error processing image: $_" -ForegroundColor Red
    }
} else {
    Write-Host "Logo file not found at: $logoPath" -ForegroundColor Red
}

