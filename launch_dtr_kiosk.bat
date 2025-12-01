@echo off
REM ============================================
REM ELMS DTR Kiosk Launcher
REM Uses separate Chrome profile for kiosk only
REM Does NOT affect your other Chrome windows
REM ============================================

SET DTR_URL=http://localhost/ELMS/app/modules/admin/views/dtr_kiosk.php
SET KIOSK_PROFILE=%TEMP%\ELMS_DTR_Kiosk_Profile

REM Launch Chrome in Kiosk Mode with separate profile
start "" "C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk --user-data-dir="%KIOSK_PROFILE%" --no-first-run --disable-pinch --overscroll-history-navigation=0 "%DTR_URL%"
