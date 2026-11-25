# LHPRIA - 24/7 Persistent Tunnel Supervisor
# Keeps the application online continuously outside the IDE with auto-recovery on restart.

$logPath = "C:\Users\Administrator\OneDrive\Desktop\LHPRIA deploy\tunnel_live.log"
$urlPath = "C:\Users\Administrator\OneDrive\Desktop\LHPRIA deploy\CURRENT_ONLINE_URL.txt"

function Write-Log($msg) {
    $time = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
    $line = "[$time] $msg"
    Add-Content -Path $logPath -Value $line -Encoding utf8
}

Write-Log "=== LHPRIA 24/7 Persistent Tunnel Daemon Initialized ==="

# 1. Ensure Apache and MySQL Windows Services are running
try {
    $ap = Get-Service -Name "Apache2.4" -ErrorAction SilentlyContinue
    if ($ap -and $ap.Status -ne "Running") {
        Write-Log "Starting Apache2.4 service..."
        Start-Service "Apache2.4"
    }
    $my = Get-Service -Name "mysql" -ErrorAction SilentlyContinue
    if ($my -and $my.Status -ne "Running") {
        Write-Log "Starting MySQL service..."
        Start-Service "mysql"
    }
} catch {
    Write-Log "Service check warning: $_"
}

# 2. Main Auto-Reconnect Loop
while ($true) {
    try {
        Write-Log "Connecting secure HTTPS tunnel on port 443..."
        
        & ssh.exe -p 443 -R0:127.0.0.1:80 -o StrictHostKeyChecking=no -o ServerAliveInterval=30 -o ServerAliveCountMax=3 free.pinggy.io 2>&1 | ForEach-Object {
            $line = $_.ToString()
            Write-Log $line
            if ($line -match "https://[a-zA-Z0-9\.\-]+\.(free\.pinggy\.net|run\.pinggy-free\.link)") {
                $fullUrl = $matches[0] + "/land/"
                Set-Content -Path $urlPath -Value $fullUrl -Encoding utf8
                Write-Log ">>> ACTIVE LIVE PUBLIC URL: $fullUrl"
            }
        }

        Write-Log "Tunnel connection reset. Auto-reconnecting in 2 seconds..."
    } catch {
        Write-Log "Supervisor exception: $_"
    }
    Start-Sleep -Seconds 2
}
