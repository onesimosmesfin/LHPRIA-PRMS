@echo off
echo =======================================================
echo   Starting Secure Public HTTPS Tunnel for LHPRIA-PRMS
echo =======================================================
echo.
echo Forwarding port 80 to a live public HTTPS address...
echo Look for the https://... URL below and share it with users:
echo Press Ctrl+C anytime to stop sharing.
echo.
ssh -p 443 -R0:127.0.0.1:80 -o StrictHostKeyChecking=no -o ServerAliveInterval=30 free.pinggy.io
pause
