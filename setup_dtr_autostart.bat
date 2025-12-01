@echo off
REM ============================================
REM ELMS DTR Kiosk - Auto-Start Setup
REM Run this as Administrator to set up
REM automatic startup of DTR kiosk
REM ============================================

echo.
echo ============================================
echo   ELMS DTR Kiosk - Auto-Start Setup
echo ============================================
echo.

REM Get the current directory
set "CURRENT_DIR=%~dp0"

REM Create the startup shortcut
echo Creating startup shortcut...

REM Create VBS script to make shortcut
echo Set oWS = WScript.CreateObject("WScript.Shell") > "%TEMP%\CreateShortcut.vbs"
echo sLinkFile = oWS.SpecialFolders("Startup") ^& "\ELMS DTR Kiosk.lnk" >> "%TEMP%\CreateShortcut.vbs"
echo Set oLink = oWS.CreateShortcut(sLinkFile) >> "%TEMP%\CreateShortcut.vbs"
echo oLink.TargetPath = "%CURRENT_DIR%launch_dtr_kiosk.bat" >> "%TEMP%\CreateShortcut.vbs"
echo oLink.WorkingDirectory = "%CURRENT_DIR%" >> "%TEMP%\CreateShortcut.vbs"
echo oLink.Description = "ELMS DTR Kiosk" >> "%TEMP%\CreateShortcut.vbs"
echo oLink.WindowStyle = 7 >> "%TEMP%\CreateShortcut.vbs"
echo oLink.Save >> "%TEMP%\CreateShortcut.vbs"

REM Run the VBS script
cscript //nologo "%TEMP%\CreateShortcut.vbs"
del "%TEMP%\CreateShortcut.vbs"

echo.
echo ============================================
echo   Setup Complete!
echo ============================================
echo.
echo The DTR Kiosk will now start automatically
echo when Windows starts.
echo.
echo To test it now, double-click:
echo   launch_dtr_kiosk.bat
echo.
echo To remove auto-start:
echo   Run remove_dtr_autostart.bat
echo.
echo ============================================
echo.
pause
