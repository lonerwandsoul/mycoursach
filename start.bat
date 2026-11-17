@echo off
setlocal
set "ROOT=%~dp0"
set "PORT=8082"

set "PHP=E:\dickpick\tools\php\php\php.exe"
if exist "%PHP%" goto have_php
where php >nul 2>&1
if errorlevel 1 (
    echo PHP not found. Install PHP 8.2+ or add php.exe to PATH.
    pause
    exit /b 1
)
set "PHP=php"
:have_php

set "DATADIR=E:\dickpick\favorit_data"
if not exist "%DATADIR%" set "DATADIR=%ROOT%data"
if not exist "%DATADIR%" mkdir "%DATADIR%" >nul 2>&1
if not exist "%DATADIR%\sessions" mkdir "%DATADIR%\sessions" >nul 2>&1
set "FAVORIT_DATA_DIR=%DATADIR%"

echo ============================================
echo   FAVORIT: analytics of enterprise efficiency
echo   Site:  http://127.0.0.1:%PORT%/analytics.php
echo   Login: manager / manager123
echo   Data:  %DATADIR%
echo   Close this window to stop the server.
echo ============================================

start "" cmd /c "timeout /t 2 >nul & start http://127.0.0.1:%PORT%/analytics.php"

"%PHP%" -d session.save_path="%DATADIR%\sessions" -S 127.0.0.1:%PORT% -t "%ROOT%"

echo Server stopped.
pause
