@echo off
REM ============================================
REM ELMS DTR Kiosk - Remove Auto-Start
REM Run this to remove automatic startup
REM ============================================

echo.
echo ============================================
echo   ELMS DTR Kiosk - Remove Auto-Start
echo ============================================
echo.

REM Get startup folder path and delete shortcut
set "STARTUP_FOLDER=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"

if exist "%STARTUP_FOLDER%\ELMS DTR Kiosk.lnk" (
    del "%STARTUP_FOLDER%\ELMS DTR Kiosk.lnk"
    echo Startup shortcut removed successfully!
) else (
    echo No startup shortcut found.
)

echo.
echo ============================================
echo   Done!
echo ============================================
echo.
echo The DTR Kiosk will no longer start
echo automatically when Windows starts.
echo.
pause
