@echo off
REM ============================================================================
REM  SBMS queue worker - drains queued jobs (notification emails) then exits.
REM
REM  Runs from a Scheduled Task every 1 minute.
REM    --stop-when-empty : exit immediately when there is nothing to do,
REM                        so no php.exe stays resident between runs
REM    --max-time=50     : hard stop before the next run starts, never overlap
REM    --tries=3         : a job that keeps failing gives up instead of looping
REM
REM  Nothing is written to the log on a quiet run - the log only grows when the
REM  worker actually processed something or hit an error.
REM
REM  English only on purpose: cmd reads .bat as ANSI, and Thai text without a BOM
REM  breaks parsing (lesson from scripts\disk-check.bat).
REM ============================================================================
setlocal

set "PHP=C:\xampp\php\php.exe"
set "APP=%~dp0.."
set "LOG=%APP%\storage\logs\queue-work.log"

if not exist "%PHP%" goto nophp

REM  Keep the log bounded. PHP on the server prints two php.ini startup warnings
REM  on every run, and this script runs every minute forever - left alone the file
REM  grows by roughly a megabyte a day.
REM  🔴 No parentheses blocks here on purpose: a ")" inside an if-block closes it
REM     early and breaks the whole .bat (lesson from scripts\disk-check.bat).
if not exist "%LOG%" goto run
for %%A in ("%LOG%") do set "LOGSIZE=%%~zA"
if "%LOGSIZE%"=="" goto run
if %LOGSIZE% LSS 1048576 goto run
move /y "%LOG%" "%LOG%.old" >nul

:run
cd /d "%APP%"

REM  Pick up mail that could not be sent earlier because the recipient had no
REM  email address yet - they may have added one at Insight since. Prints
REM  nothing when there is nothing to do, so the log stays quiet.
"%PHP%" artisan bms:mail-pending >> "%LOG%" 2>&1

"%PHP%" artisan queue:work --stop-when-empty --tries=3 --max-time=50 >> "%LOG%" 2>&1
goto end

:nophp
echo php.exe not found at %PHP% >> "%LOG%"

:end
endlocal
