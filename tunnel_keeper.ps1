# LHPRIA - Persistent Tunnel Supervisor
# Keeps https://lhpria-prms.loca.lt active 24/7 independently of any IDE or terminal.

$subdomain = "lhpria-prms"
$port = 80
$logPath = "C:\Users\Administrator\OneDrive\Desktop\LHPRIA deploy\tunnel.log"

function Write-Log($msg) {
    $time = (Get-Date).ToString("yyyy-MM-dd HH:mm:ss")
    $line = "[$time] $msg"
    Add-Content -Path $logPath -Value $line -Encoding utf8
}

Write-Log "=== LHPRIA Tunnel Supervisor Initialized ==="

# 1. Ensure Apache and MySQL Windows Services are running
try {
    $apache = Get-Service -Name "Apache2.4" -ErrorAction SilentlyContinue
    if ($apache -and $apache.Status -ne "Running") {
        Write-Log "Starting Apache2.4 service..."
        Start-Service "Apache2.4"
    }

    $mysql = Get-Service -Name "mysql" -ErrorAction SilentlyContinue
    if ($mysql -and $mysql.Status -ne "Running") {
        Write-Log "Starting MySQL service..."
        Start-Service "mysql"
    }
} catch {
    Write-Log "Service check warning: $_"
}

# 2. Main Keep-Alive Loop
while ($true) {
    try {
        Write-Log "Connecting tunnel (port $port, subdomain: $subdomain)..."
        
        # Run localtunnel
        $process = Start-Process -FilePath "cmd.exe" -ArgumentList "/c lt --port $port --subdomain $subdomain" -NoNewWindow -PassThru -RedirectStandardOutput "$PSScriptRoot\tunnel_stdout.tmp" -RedirectStandardError "$PSScriptRoot\tunnel_stderr.tmp"

        # Monitor process
        while (-not $process.HasExited) {
            Start-Sleep -Seconds 5
            # Log any new output
            if (Test-Path "$PSScriptRoot\tunnel_stdout.tmp") {
                $content = Get-Content "$PSScriptRoot\tunnel_stdout.tmp" -Raw
                if ($content -and $content.Trim() -ne "") {
                    Write-Log "Localtunnel Output: $($content.Trim())"
                    Clear-Content "$PSScriptRoot\tunnel_stdout.tmp"
                }
            }
        }

        Write-Log "Tunnel process exited with code $($process.ExitCode). Reconnecting in 3 seconds..."
    } catch {
        Write-Log "Supervisor error: $_"
    }

    Start-Sleep -Seconds 3
}
