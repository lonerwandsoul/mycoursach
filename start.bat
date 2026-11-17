@echo off
setlocal
set "ROOT=%~dp0"
set "PORT=8082"

rem --- PHP: сначала ищем portable-сборку рядом с проектом, затем в PATH ---
set "PHP="
if exist "%ROOT%php\php.exe" set "PHP=%ROOT%php\php.exe"
if not defined PHP if exist "E:\dickpick\tools\php\php\php.exe" set "PHP=E:\dickpick\tools\php\php\php.exe"
if not defined PHP (
    where php >nul 2>&1
    if not errorlevel 1 set "PHP=php"
)
if not defined PHP (
    echo PHP not found. Install PHP 8.2+ or put portable PHP into "%ROOT%php\".
    echo Or just open the project in OpenServer: http://favorit/install.php
    pause
    exit /b 1
)

rem --- Каталог данных: рядом с проектом, чтобы SQLite и сессии точно работали ---
set "DATADIR=%ROOT%data"
if not exist "%DATADIR%" mkdir "%DATADIR%" >nul 2>&1
if not exist "%DATADIR%\sessions" mkdir "%DATADIR%\sessions" >nul 2>&1
set "FAVORIT_DATA_DIR=%DATADIR%"

echo ============================================
echo   FAVORIT: analytics of enterprise efficiency
echo   Step 1: http://127.0.0.1:%PORT%/install.php   (create database)
echo   Step 2: http://127.0.0.1:%PORT%/analytics.php (dashboard)
echo   Login:  manager / manager123
echo   Data:   %DATADIR%
echo   Close this window to stop the server.
echo ============================================

start "" cmd /c "timeout /t 2 >nul & start http://127.0.0.1:%PORT%/install.php"

"%PHP%" -d session.save_path="%DATADIR%\sessions" -S 127.0.0.1:%PORT% -t "%ROOT%"

echo Server stopped.
pause
