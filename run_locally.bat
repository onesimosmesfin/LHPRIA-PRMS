@echo off
echo ===================================================
echo   Starting LHPRIA - PRMS Portal (Local Deployment)
echo ===================================================
echo.

rem Check if Apache is running
tasklist /fi "imagename eq httpd.exe" | find /i "httpd.exe" >nul
if %errorlevel% neq 0 (
    echo Starting Apache Web Server...
    start "" "C:\xampp\apache_start.bat"
) else (
    echo Apache Web Server is already running.
)

rem Check if MySQL is running
tasklist /fi "imagename eq mysqld.exe" | find /i "mysqld.exe" >nul
if %errorlevel% neq 0 (
    echo Starting MySQL Database Server...
    start "" "C:\xampp\mysql_start.bat"
) else (
    echo MySQL Database Server is already running.
)

echo.
echo Opening browser to http://localhost/land/ ...
start http://localhost/land/
echo.
echo System is running!
pause
