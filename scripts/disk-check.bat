@echo off
REM ============================================================================
REM  disk-check.bat - read-only NTFS health scan for drive C:
REM
REM  WHY THIS FILE EXISTS
REM    MariaDB data files on this machine have been corrupted four times in one
REM    month (14 Aug / 26 Aug / 15 Sep / 18 Sep 2026). The last one was an Aria
REM    table (mysql\db.MAI) whose size no longer matched its block layout, which
REM    points at the file system, not at MariaDB. The SSD reports Healthy with
REM    plenty of free space, so the next thing to rule out is NTFS metadata.
REM
REM  WHY A .BAT AND NOT AN AI COMMAND
REM    chkdsk needs Administrator rights, which the AI session does not have.
REM    Run this yourself: right-click the file -> "Run as administrator".
REM
REM  ENGLISH ONLY ON PURPOSE
REM    Windows PowerShell 5.1 / cmd.exe read files in the system ANSI code page,
REM    so Thai text inside a .bat or .ps1 without a BOM turns into garbage and
REM    can break parsing. Notes in Thai belong in the .md docs instead.
REM
REM  WHAT IT DOES
REM    chkdsk C: /scan   - ONLINE scan. Read-only, no reboot, no repair.
REM                        Safe to run while Windows, XAMPP and VS Code are up.
REM    The full output is saved to storage\logs\disk-check-<date>-<time>.log
REM
REM  WHAT IT DOES NOT DO
REM    It never repairs anything. If the scan reports errors, the fix is
REM      chkdsk C: /spotfix     (short, needs the volume briefly locked)
REM    or, for a full offline pass scheduled at the next boot,
REM      chkdsk C: /f /r        (can take hours - plan for downtime)
REM    Decide that after reading the log. Do not let it run unattended.
REM ============================================================================

setlocal

REM --- must be elevated, otherwise chkdsk /scan fails halfway with no log ----
net session >nul 2>&1
if errorlevel 1 (
  echo.
  echo   This script needs Administrator rights.
  echo   Close this window, right-click disk-check.bat
  echo   and choose "Run as administrator".
  echo.
  pause
  exit /b 1
)

REM --- timestamp: yyyymmdd-hhmmss, independent of regional date format -------
set STAMP=
for /f "tokens=2 delims==" %%i in ('wmic os get LocalDateTime /value 2^>nul') do set LDT=%%i
if defined LDT set STAMP=%LDT:~0,8%-%LDT:~8,6%
REM WMIC is deprecated and may be missing on newer Windows builds
if not defined STAMP for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd-HHmmss"') do set STAMP=%%i
if not defined STAMP set STAMP=unknown

set LOGDIR=%~dp0..\storage\logs
if not exist "%LOGDIR%" mkdir "%LOGDIR%"
set LOGFILE=%LOGDIR%\disk-check-%STAMP%.log

echo.
echo   Scanning drive C: (read-only, no repair, no reboot)
echo   This usually takes a few minutes. Leave the window open.
echo   Log: %LOGFILE%
echo.

echo === disk-check.bat  %STAMP% === > "%LOGFILE%"
echo === command: chkdsk C: /scan === >> "%LOGFILE%"
echo. >> "%LOGFILE%"

chkdsk C: /scan >> "%LOGFILE%" 2>&1
set RC=%ERRORLEVEL%

echo. >> "%LOGFILE%"
echo === exit code: %RC% === >> "%LOGFILE%"

echo.
if "%RC%"=="0" (
  echo   RESULT: no problems found on C:.
  echo   File-system corruption is ruled out. If MariaDB breaks again,
  echo   look at power loss / unclean shutdown instead.
) else (
  echo   RESULT: chkdsk exit code %RC% - it found something.
  echo   Open the log and read the summary near the end:
  echo     %LOGFILE%
  echo   Then decide on a repair pass - see the notes at the top of this file.
)
echo.
pause
endlocal
